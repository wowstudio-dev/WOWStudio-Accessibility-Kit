#!/usr/bin/env bash
#
# The whole quality gate, with an unambiguous verdict.
#
# composer lint chains the same checks, but this exists because reading a gate's
# output is itself a place to make mistakes: a run where PHPCS failed silently
# and PHPStan printed "[OK] No errors" directly underneath was misread as a pass,
# and the failure reached CI. Success is now stated per step and once at the end,
# so a pass is something that gets printed rather than something inferred from
# the absence of a complaint.
#
# Runs the PHP half in a container, because Brain Monkey does not work on the
# PHP 8.5 that a current Homebrew installs.

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT}"

FAILED=()

step() {
	local name="$1"
	shift

	if "$@" > /tmp/wsak-gate.log 2>&1; then
		printf '  PASS  %s\n' "${name}"
	else
		printf '  FAIL  %s\n' "${name}"
		sed 's/^/        /' /tmp/wsak-gate.log | tail -25
		FAILED+=( "${name}" )
	fi
}

php_in_container() {
	docker run --rm -v "${ROOT}":/app -w /app php:8.1-cli php "$@"
}

echo "Quality gate"
step "PHPCS"              php_in_container vendor/bin/phpcs -q --no-cache
step "PHPStan"            php_in_container vendor/bin/phpstan analyse --memory-limit=1G --no-progress
step "PHPUnit"            php_in_container vendor/bin/phpunit
step "Claim guard"        php_in_container bin/check-claims.php
step "Disclosure guard"   php_in_container bin/check-disclosures.php
step "Free-build guard"   php_in_container bin/check-free-build.php
step "Contrast guard"     node bin/check-contrast.js
step "JavaScript lint"    npm run lint:js
step "Stylesheet lint"    npm run lint:css
step "JavaScript tests"   npm run test:js

rm -f /tmp/wsak-gate.log

if [ ${#FAILED[@]} -gt 0 ]; then
	printf '\nGATE FAILED — %d step(s): %s\n' "${#FAILED[@]}" "${FAILED[*]}"
	exit 1
fi

printf '\nGATE PASSED — all 10 steps.\n'
