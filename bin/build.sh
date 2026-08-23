#!/usr/bin/env bash
#
# Builds the distributable plugin directory and zip.
#
# The dev tree is not shippable: it carries node_modules, dev Composer
# dependencies, tests, and tooling config. Plugin Check and `wp i18n make-pot`
# both walk whatever directory you point them at, so they have to run against
# this output rather than the working tree.
#
# Usage:
#   bin/build.sh                Build the premium plugin into dist/
#   bin/build.sh --zip          ...and zip it
#   bin/build.sh --free         Build a FREE-flavoured copy for local testing
#   bin/build.sh --free --zip
#
# About --free: Freemius generates the real WordPress.org build server-side on
# deploy, and that output is the authoritative one. This flag produces a local
# approximation so the free experience can be installed and tested without a
# deploy. It applies the two transformations Freemius applies to the plugin
# header (is_premium => false, gatekeeper removed) and drops *-premium.php
# files. It deliberately REFUSES to guess at __premium_only / @fs_premium_only
# stripping — if our code ever contains those, use a Freemius-generated zip.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="wowstudio-accessibility-kit"

FREE=0
ZIP=0
for arg in "$@"; do
	case "${arg}" in
		--free) FREE=1 ;;
		--zip)  ZIP=1 ;;
		*) echo "Unknown option: ${arg}" >&2; exit 1 ;;
	esac
done

if [ "${FREE}" -eq 1 ]; then
	NAME="${SLUG}-free"
	FLAVOUR="free"
else
	NAME="${SLUG}"
	FLAVOUR="premium"
fi

OUT="${ROOT}/dist/${NAME}"

cd "${ROOT}"

echo "==> Building the ${FLAVOUR} flavour"
rm -rf "${OUT}" "${ROOT}/dist/${NAME}.zip"
mkdir -p "${OUT}"

echo "==> Copying source (honouring .distignore)"
rsync -a \
	--exclude-from="${ROOT}/.distignore" \
	--exclude='.git' \
	--exclude='dist' \
	--exclude='vendor' \
	--exclude='node_modules' \
	"${ROOT}/" "${OUT}/"

echo "==> Installing production dependencies"
cp "${ROOT}/composer.lock" "${OUT}/"
composer install \
	--working-dir="${OUT}" \
	--no-dev \
	--optimize-autoloader \
	--classmap-authoritative \
	--no-interaction \
	--quiet
# composer.json stays in the build: Plugin Check warns when a vendor/ directory
# appears without the manifest that produced it. composer.lock is dev-only.
rm -f "${OUT}/composer.lock"

if [ "${FREE}" -eq 1 ]; then
	echo "==> Applying free-build transformations"

	# Refuse to guess. Freemius's stripper is the authority for these.
	LEAKS="$(grep -rl --exclude-dir=freemius --exclude-dir=vendor \
		-e '__premium_only' -e '@fs_premium_only' "${OUT}" 2>/dev/null || true)"
	if [ -n "${LEAKS}" ]; then
		echo "    ERROR: this build contains Pro-only markers:" >&2
		echo "${LEAKS}" | sed 's/^/      /' >&2
		echo "    A local free build cannot replicate Freemius's stripping." >&2
		echo "    Deploy through Freemius and test the zip it generates." >&2
		exit 1
	fi

	MAIN="${OUT}/${SLUG}.php"

	perl -0pi -e "s/('is_premium'\s*=>\s*)true/\${1}false/" "${MAIN}"
	# Drop the gatekeeper line and the comment that introduces it.
	perl -0pi -e "s/\n[ \t]*\/\/ Automatically removed from the free version by Freemius on deploy\.\n[ \t]*'wp_org_gatekeeper'.*?,\n/\n/s" "${MAIN}"

	find "${OUT}" -name '*-premium.php' -not -path '*/freemius/*' -delete

	grep -q "'is_premium'          => false," "${MAIN}" \
		|| { echo "    ERROR: is_premium was not flipped to false" >&2; exit 1; }
	grep -q 'wp_org_gatekeeper' "${MAIN}" \
		&& { echo "    ERROR: gatekeeper survived the transformation" >&2; exit 1; }

	echo "    is_premium => false, gatekeeper removed"
fi

echo "==> Verifying the build"
php "${ROOT}/bin/check-claims.php" > /dev/null
echo "    no unqualified compliance claims"

if [ -d "${OUT}/tests" ] || [ -d "${OUT}/node_modules" ] || [ -f "${OUT}/phpcs.xml.dist" ]; then
	echo "    ERROR: development files leaked into the build" >&2
	exit 1
fi
echo "    no development files in the build"

if [ "${FREE}" -eq 1 ]; then
	php "${ROOT}/bin/check-free-build.php" "${OUT}" | sed 's/^/    /'
fi

echo "==> Built ${OUT} ($(du -sh "${OUT}" | cut -f1))"

if [ "${ZIP}" -eq 1 ]; then
	echo "==> Zipping"
	( cd "${ROOT}/dist" && zip -qr "${NAME}.zip" "${NAME}" )
	echo "==> Built ${ROOT}/dist/${NAME}.zip ($(du -h "${ROOT}/dist/${NAME}.zip" | cut -f1))"
fi
