#!/usr/bin/env bash
#
# Builds the distributable plugin directory and zip.
#
# The dev tree is not shippable: it carries node_modules, dev Composer
# dependencies, tests, and tooling config. Plugin Check and `wp i18n make-pot`
# both walk whatever directory you point them at, so they have to run against
# this output rather than the working tree.
#
# Usage: bin/build.sh [--zip]

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="wowstudio-accessibility-kit"
OUT="${ROOT}/dist/${SLUG}"

cd "${ROOT}"

echo "==> Cleaning dist/"
rm -rf "${ROOT}/dist"
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

if [ -d "${OUT}/tests" ] || [ -d "${OUT}/node_modules" ] || [ -f "${OUT}/phpcs.xml.dist" ]; then
	echo "    ERROR: development files leaked into the build" >&2
	exit 1
fi
echo "    no development files in the build"

echo "==> Built ${OUT} ($(du -sh "${OUT}" | cut -f1))"

if [ "${1:-}" = "--zip" ]; then
	echo "==> Zipping"
	( cd "${ROOT}/dist" && zip -qr "${SLUG}.zip" "${SLUG}" )
	echo "==> Built ${ROOT}/dist/${SLUG}.zip ($(du -h "${ROOT}/dist/${SLUG}.zip" | cut -f1))"
fi
