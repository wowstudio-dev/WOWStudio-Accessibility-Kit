#!/usr/bin/env bash
#
# Regenerates languages/wowstudio-accessibility-kit.pot.
#
# Runs against the built plugin rather than the working tree, because
# `wp i18n make-pot` walks whatever directory it is given and the working tree
# contains node_modules, which is large enough to exhaust its JS parser.
#
# The JavaScript sources are copied in for the duration of the scan. They are
# excluded from the shipped build (see .distignore) but the translatable strings
# live in them, not in the minified bundle, so scanning the build alone would
# silently drop every string in the admin app.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="wowstudio-accessibility-kit"
OUT="${ROOT}/dist/${SLUG}"

cd "${ROOT}"

"${ROOT}/bin/build.sh" > /dev/null

echo "==> Adding JavaScript sources for extraction"
mkdir -p "${OUT}/assets"
cp -R "${ROOT}/assets/src" "${OUT}/assets/src"

echo "==> Extracting strings"
npx wp-env run cli --env-cwd="wp-content/plugins/${SLUG}" \
	wp i18n make-pot "dist/${SLUG}" "languages/${SLUG}.pot" \
	--domain="${SLUG}" \
	--exclude=vendor,freemius,build \
	> /dev/null

echo "==> Removing the extraction-only copy"
rm -rf "${OUT}/assets/src"

echo "==> $(grep -c '^msgid' "${ROOT}/languages/${SLUG}.pot") strings in languages/${SLUG}.pot"
