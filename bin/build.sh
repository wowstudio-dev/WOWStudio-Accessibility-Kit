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

echo "==> Verifying the build"
php "${ROOT}/bin/check-claims.php" > /dev/null
echo "    no unqualified compliance claims"

if [ ! -f "${OUT}/build/index.js" ]; then
	echo "    ERROR: the compiled admin app is missing from the build" >&2
	exit 1
fi
echo "    compiled admin app included"

if [ -d "${OUT}/tests" ] || [ -d "${OUT}/node_modules" ] || [ -f "${OUT}/phpcs.xml.dist" ]; then
	echo "    ERROR: development files leaked into the build" >&2
	exit 1
fi
echo "    no development files in the build"

# The licensing SDK went in 0.16.0, but its directory did not: one leftover
# image stayed behind, .distignore never listed it, and every zip built since
# has shipped a folder called freemius inside a plugin that advertises no
# licensing SDK at all. Checked here rather than trusted to .distignore, so
# that anything reappearing under that name fails the build instead of being
# quietly published.
if [ -e "${OUT}/freemius" ]; then
	echo "    ERROR: a freemius/ directory reached the build" >&2
	exit 1
fi
echo "    no licensing SDK in the build"

echo "==> Built ${OUT} ($(du -sh "${OUT}" | cut -f1))"

if [ "${ZIP}" -eq 1 ]; then
	echo "==> Zipping"
	( cd "${ROOT}/dist" && zip -qr "${NAME}.zip" "${NAME}" )
	echo "==> Built ${ROOT}/dist/${NAME}.zip ($(du -h "${ROOT}/dist/${NAME}.zip" | cut -f1))"
fi
