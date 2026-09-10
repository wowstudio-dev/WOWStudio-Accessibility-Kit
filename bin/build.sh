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
#   bin/build.sh                Build the plugin into dist/
#   bin/build.sh --zip          ...and zip it
#
# There is one build. The plugin has no paid tier, no licensing SDK, and no
# code that is stripped on the way out, so what is tested here is exactly what
# ships. The paid add-on, when it exists, will be a separate plugin with a
# build of its own rather than a second flavour of this one.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="wowstudio-accessibility-kit"

ZIP=0
for arg in "$@"; do
	case "${arg}" in
		--zip) ZIP=1 ;;
		*) echo "Unknown option: ${arg}" >&2; exit 1 ;;
	esac
done

NAME="${SLUG}"
OUT="${ROOT}/dist/${NAME}"

cd "${ROOT}"

echo "==> Compiling the admin app"
# build/ is generated and not in version control, so a release build has to
# compile it. Shipping a plugin whose admin screen is blank because nobody ran
# npm run build is a mistake worth making impossible.
npm run build --silent > /dev/null

if [ ! -f "${ROOT}/build/index.js" ] || [ ! -f "${ROOT}/build/index.asset.php" ]; then
	echo "    ERROR: the admin app did not compile" >&2
	exit 1
fi
echo "    build/index.js and build/index.asset.php present"

echo "==> Building ${NAME}"
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

echo "==> Trimming vendor documentation"
#
# Action Scheduler ships its own readme.txt, and it is a plugin readme: it has
# Contributors, Stable tag and Tested up to headers describing a different
# plugin. A second one of those inside the package is a thing WordPress.org
# reviewers ask about, and there is no reading of it that helps anybody
# installing this.
#
# The rest is documentation for people reading the library's repository, which
# is not what a vendor directory is for. None of it is loaded by any code — the
# grep that proved that is worth re-running if this list ever grows.
#
# Every licence file stays. The GPL and the MIT licence both require their text
# to travel with the code, so removing one would be an actual violation rather
# than tidying.
find "${OUT}/vendor" \
	\( -name 'readme.txt' -o -name 'changelog.txt' -o -name 'README.md' \
	   -o -name 'CLAUDE.md' -o -name 'AGENTS.md' -o -name 'CONTRIBUTING.md' \) \
	-type f -delete
echo "    vendor docs removed, licences kept"

echo "==> Verifying the build"
php "${ROOT}/bin/check-claims.php" > /dev/null
echo "    no unqualified compliance claims"

if [ ! -f "${OUT}/build/index.js" ]; then
	echo "    ERROR: the compiled admin app is missing from the build" >&2
	exit 1
fi
echo "    compiled admin app included"

STRAY_READMES="$(find "${OUT}" -name 'readme.txt' -type f | grep -v "^${OUT}/readme.txt$" || true)"
if [ -n "${STRAY_READMES}" ]; then
	echo "    ERROR: a second readme.txt is in the build:" >&2
	echo "${STRAY_READMES}" >&2
	exit 1
fi
echo "    one readme, and it is ours"

if [ ! -f "${OUT}/LICENSE" ]; then
	echo "    ERROR: the plugin licence is missing from the build" >&2
	exit 1
fi
LICENCES="$(find "${OUT}/vendor" -iname 'licen[cs]e*' -type f | wc -l | tr -d ' ')"
if [ "${LICENCES}" -lt 3 ]; then
	echo "    ERROR: vendor licence files were removed; the GPL and MIT both require them" >&2
	exit 1
fi
echo "    licences intact (${LICENCES} in vendor)"

if [ -d "${OUT}/tests" ] || [ -d "${OUT}/node_modules" ] || [ -f "${OUT}/phpcs.xml.dist" ]; then
	echo "    ERROR: development files leaked into the build" >&2
	exit 1
fi
echo "    no development files in the build"

# What ships is an allowlist, not a blocklist. The licensing SDK's directory
# outlived the SDK itself by thirteen versions precisely because nothing was
# looking for it: .distignore excludes what somebody remembered to name, and
# the thing nobody remembers to name is the thing that ships. Anything new at
# the top level now has to be added here on purpose.
EXPECTED="LICENSE assets build composer.json languages readme.txt src vendor wowstudio-accessibility-kit.php"
for entry in "${OUT}"/* "${OUT}"/.[!.]*; do
	[ -e "${entry}" ] || continue
	name="$(basename "${entry}")"
	case " ${EXPECTED} " in
		*" ${name} "*) ;;
		*)
			echo "    ERROR: unexpected file in the build: ${name}" >&2
			exit 1
			;;
	esac
done
echo "    nothing in the build but the plugin"

echo "==> Built ${OUT} ($(du -sh "${OUT}" | cut -f1))"

if [ "${ZIP}" -eq 1 ]; then
	echo "==> Zipping"
	( cd "${ROOT}/dist" && zip -qr "${NAME}.zip" "${NAME}" )
	echo "==> Built ${ROOT}/dist/${NAME}.zip ($(du -h "${ROOT}/dist/${NAME}.zip" | cut -f1))"
fi
