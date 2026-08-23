#!/usr/bin/env bash
#
# Clears Freemius state in the local wp-env site and reactivates the plugin.
#
# Freemius stores opt-in and activation state in options. If a request dies
# part-way through activation — a fatal in the plugin, a dropped connection —
# that state can be left saying an opt-in is still in flight
# (is_pending_activation), which shows a licence prompt that no amount of
# clicking will clear. Nothing is wrong with the plugin at that point; the
# stored state is simply stale. This resets it.
#
# Usage: bin/reset-freemius.sh [plugin-dir-name]

set -euo pipefail

PLUGIN="${1:-wowstudio-accessibility-kit}"

echo "==> Deactivating ${PLUGIN}"
npx wp-env run cli wp plugin deactivate "${PLUGIN}" > /dev/null 2>&1 || true

echo "==> Clearing Freemius options"
for option in fs_accounts fs_active_plugins fs_debug_mode fs_api_cache; do
	npx wp-env run cli wp option delete "${option}" > /dev/null 2>&1 || true
done

echo "==> Clearing the debug log"
npx wp-env run cli sh -c 'rm -f /var/www/html/wp-content/debug.log' > /dev/null 2>&1 || true

echo "==> Reactivating ${PLUGIN}"
npx wp-env run cli wp plugin activate "${PLUGIN}" 2>&1 | grep -vE '^(ℹ|✔)' || true

echo "==> Freemius state"
npx wp-env run cli wp eval '$f = wsak_fs(); printf(
	"    build            : %s\n    licence required : %s\n    blocking admin   : %s\n    plan             : %s\n",
	$f->is_premium() ? "premium" : "free",
	$f->is_only_premium() ? "YES" : "no",
	$f->is_activation_mode() ? "YES" : "no",
	$f->is_free_plan() ? "free" : "paid"
);' 2>&1 | grep -E '^    (build|licence|blocking|plan) '
