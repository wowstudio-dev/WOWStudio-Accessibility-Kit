# Changelog

All notable changes to WOWStudio Accessibility Kit are documented here.
This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Not yet done in this step

- Capped **bulk** alt-text generation. Single-image generation is complete and
  the cap is enforced, but a bulk run means many multi-second provider calls in
  one request, which is exactly the kind of work CLAUDE.md says must go through
  Action Scheduler. Shipping a synchronous loop that times out halfway would be
  worse than not shipping it, so bulk lands with the queue.

## [0.5.1] - 2026-08-23

### Fixed

- The plugin ignored `wp_supports_ai()`. WordPress 7.0 lets a site owner or host
  switch AI off through the `WP_AI_SUPPORT` constant or the `wp_supports_ai`
  filter, and we would have called a paid provider on a site that had explicitly
  said not to. The check now runs before any other work in the generation path,
  the settings screen explains the situation rather than silently failing, and
  the plugin does not work around the switch.

  WordPress before 7.0 has no such function, so its absence is treated as
  permission rather than refusal — otherwise AI would break on every site
  between 6.6 and 6.9.

## [0.5.0] - 2026-08-23

Phase 1, step 5: AI providers, encrypted credentials, and alt text.

### Added

- Bring-your-own-key providers for Anthropic, OpenAI, Google Gemini, and
  OpenRouter, behind a single `ProviderInterface`.
- `AI\AiClientBridge`, which prefers the AI client WordPress 7.0 ships when the
  site has one configured, so credentials do not have to be entered twice. The
  bridge is built against the real API, read from a WordPress 7.1 install.
- `AI\KeyStore`: keys encrypted with libsodium before they reach the database,
  with a fresh nonce per write. If libsodium is missing, storage is **refused**
  rather than silently downgraded to plaintext.
- Alt-text generation with a review step. The suggestion always lands in an
  editable field; nothing is written to the media library until a person saves
  it.
- The free allowance of 20 images a day, enforced in the generation path rather
  than in the interface, and consumed only after a provider actually answered.
- AI settings screen: provider, model, write-only key field, a plain statement
  of what is sent, and today's allowance.

### Privacy

- What leaves the site is the image, its file name, and — if the site leaves the
  toggle on — the page title and up to 300 characters of surrounding text. That
  list is the whole of `AI\ImageContext`; there is no other path out.
- A resized copy is sent rather than the original: cheaper, faster, and less
  data.
- Individual posts can be excluded with the `_wsak_skip_ai` post meta, checked
  before any work is done.

## [0.4.0] - 2026-08-23

Phase 1, step 4: the dashboard. The scanner now has a face.

### Added

- React admin app built with `@wordpress/scripts`, mounted on a single admin
  screen and loaded only there.
- Pick a page, run a scan, read the findings. Results are split into what the
  scanner settled and what still needs a person, with a count on each group.
- Score card that never shows the number alone: it sits beside the count of
  items awaiting review and a plain statement that it is not a measure of
  compliance.
- Coverage panel listing all twelve checks and what each can decide, with the
  limitation stated in the summary even when the detail is collapsed.
- `GET /wsak/v1/scans/<id>` and `GET /wsak/v1/scannable`.
- A design system in the WOWStudio palette. Contrast was measured rather than
  assumed: Indigo 6.31:1, Iris 5.70:1, Ink 17.9:1 all pass AA for body text.
  Cyan measures 1.80:1, so it is used only as a decorative accent and never for
  text or for information carried by colour alone.

### Fixed

- `wp i18n make-pot` ran against the built plugin, which excludes `assets/src`.
  Every string in the admin app — 61 of them — would have shipped
  untranslatable. `bin/makepot.sh` now copies the JavaScript sources in for the
  duration of the extraction.
- `bin/build.sh` now compiles the admin app and refuses to produce a build
  without it. `build/` is not in version control, so a release could otherwise
  ship a plugin whose admin screen is blank.
- `POST /wsak/v1/scan` did not return the post title, so the results heading
  read "Scan results" instead of naming the page.

### Accessibility

The dashboard was checked against the rules the plugin itself ships: zero
findings across 146 elements, one h1, and a heading outline that descends one
level at a time. Focus moves to the results heading when a scan finishes,
progress is announced through a live region, `prefers-reduced-motion` is
respected, and the layout reflows to a single column on small screens.

## [0.3.0] - 2026-08-23

Phase 1, step 3: the scanner. Findings are stored and available over REST; the
dashboard that shows them is step 4.

### Added

- PHP `DOMDocument`/`DOMXPath` scan engine, with a `Document` wrapper that
  handles parsing, UTF-8, accessible-name computation, and XPath escaping so no
  rule has to.
- Twelve MVP rules covering images, form labels, link and button names, frame
  titles, page language, document title, heading order, multiple top-level
  headings, table headers, and the main landmark.
- `RuleRegistry`, filterable through `wsak_rules`, exposing a `coverage()`
  report that says what each rule checks and whether it can settle the question
  automatically.
- `POST /wsak/v1/scan` and `GET /wsak/v1/coverage`, gated on `wsak_run_scan` and
  `wsak_view_reports` respectively.
- `PageSource`, which fetches the whole rendered page over a loopback request so
  the theme's markup is scanned, not just post content.

### Notes on honesty

- Eight of the twelve rules are auto-detected; four report `needs manual review`
  because confirming them takes human judgement. Vague link text, tables without
  headers, a missing main landmark, and multiple top-level headings are all
  cases where the markup alone does not settle the question.
- Only auto-detected findings reduce the score. Counting unconfirmed items as
  failures would report a page as worse than we know it to be, so the number is
  always shown next to the review count rather than on its own.
- When loopback requests are blocked, the scan falls back to post content and
  says so. The document-level rules detect the fragment and stay silent instead
  of reporting a missing page title that a fragment never had, and the response
  carries `full_page: false` with a `coverage_notice` explaining what was
  skipped and why.
- A rule that throws is logged and skipped rather than losing the findings of
  the other eleven.

## [0.2.0] - 2026-08-23

Phase 1, step 2: the storage layer. Still nothing user-facing beyond a holding
screen; the scanner that fills these tables arrives in step 3.

### Added

- `wp_wsak_scans` and `wp_wsak_issues` tables, created through `dbDelta` and
  versioned so later changes apply without an activation.
- `ScanRepository` and `IssueRepository`, with findings written as a single
  multi-row INSERT rather than one query per issue.
- Typed domain vocabulary as PHP 8.1 enums: `Severity`, `Detection`,
  `IssueStatus`, `ScanScope`, `ScanStatus`. The auto/manual honesty tag is a
  type rather than a loose string, because the UI has to keep the two apart
  everywhere it shows findings.
- `Scan` and `Issue` readonly records. An unrecognised value from a newer
  version degrades to a safe default instead of throwing, and an unrecognised
  detection degrades to "needs manual review" — when we cannot tell whether a
  machine settled something, the honest answer is that a person should look.
- `Core\Installer`, which installs on activation, on a site joining a network,
  and on a schema change. This closes the multisite gap left open in 0.1.0.
- Uninstall now drops the plugin's tables, still only when the site owner has
  opted in.

### Changed

- Every query uses `prepare()`'s `%i` identifier placeholder for table and
  column names, so no SQL in the plugin is assembled by string interpolation.
  The only exceptions are the `CREATE TABLE` statements, which `dbDelta` has to
  parse as literals.
- `Activator` now delegates per-site work to `Installer` so the activation and
  non-activation paths cannot drift apart.

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
