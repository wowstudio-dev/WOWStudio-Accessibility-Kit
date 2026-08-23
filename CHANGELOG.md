# Changelog

All notable changes to WOWStudio Accessibility Kit are documented here.
This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `bin/build.sh --free` builds a locally installable free flavour, so the free
  experience can be tested without a Freemius deploy. It refuses to run if the
  code contains Pro-only markers, rather than guessing at Freemius's stripping.
- `bin/reset-freemius.sh` (`npm run fs:reset`) clears stale Freemius activation
  state, which otherwise shows a licence prompt that cannot be dismissed.
- `WP_FS__DEV_MODE` in the wp-env config.

### Fixed

- The plugin vanished from the admin sidebar as soon as a user opted in or
  skipped the Freemius opt-in. Freemius shows a temporary top-level menu of its
  own while the opt-in is pending, then removes it and expects the plugin's real
  menu at the configured slug. We had pointed Freemius at
  `wowstudio-accessibility-kit` without ever registering that menu, so it
  disappeared and the Freemius Account, Upgrade, and Contact pages were left
  orphaned. `Admin\Menu` now registers it, and a test asserts the slug stays in
  sync with the one handed to Freemius.
- `bin/check-free-build.php` skipped no directories when auditing a **zip**,
  only when auditing a directory. The Freemius SDK contains `__premium_only`
  and `@fs_premium_only` as part of its own machinery and is not stripped by
  Freemius, so every genuine free zip would have failed the guard.

## [0.1.0] - 2026-08-23

Phase 1, step 1: tooling and plugin scaffold. Nothing user-facing yet.

### Added

- Plugin bootstrap with headers, constants, and a graceful runtime requirements
  check that shows an admin notice instead of a fatal error on unsupported PHP
  or WordPress versions.
- PSR-4 autoloading and a thin `Plugin` orchestrator with a `Registrable`
  service contract, so no single class accumulates every hook.
- Four least-privilege capabilities (`wsak_manage_settings`, `wsak_run_scan`,
  `wsak_apply_fix`, `wsak_view_reports`) with a filterable role map. Editors can
  scan and read results but cannot change markup or settings.
- Activation that is safe to re-run and multisite-aware, and an uninstall
  routine that removes data only when the site owner has opted in. The default
  is to keep everything.
- Freemius SDK 2.13.4 wired with the free/pro plans and a 7-day trial.
- Quality tooling: PHPCS (WordPress-Core, -Extra, -Docs, PHPCompatibilityWP),
  PHPStan level 6, PHPUnit with Brain Monkey, wp-env, and Plugin Check.
- `bin/check-free-build.php` — fails the build if premium-only code or the
  Freemius gatekeeper secret could reach the free WordPress.org zip.
- `bin/check-claims.php` — fails the build on any unqualified "compliant",
  "certified", or "guaranteed" claim, enforcing the assist-never-guarantee rule
  mechanically rather than by review alone.
- `bin/build.sh` — builds the distributable plugin from `.distignore`, installs
  production-only Composer dependencies, and refuses to produce a build that
  contains development files.
- Translation template at `languages/wowstudio-accessibility-kit.pot`, with CI
  failing if it drifts from the source.

### Notes

- No `load_plugin_textdomain()` call. The `Domain Path` header lets WordPress
  load the bundled catalogue just in time, and calling it explicitly is what
  Plugin Check flags as discouraged since WordPress 4.6.

[Unreleased]: https://github.com/wowstudio-dev/WOWStudio-Accessibility-Kit/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/wowstudio-dev/WOWStudio-Accessibility-Kit/releases/tag/v0.1.0
