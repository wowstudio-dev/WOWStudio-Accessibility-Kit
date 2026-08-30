# Changelog

All notable changes to WOWStudio Accessibility Kit are documented here.
This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Still outstanding

- Capped **bulk** alt-text generation. Single-image generation is complete and
  the cap is enforced, but a bulk run means many multi-second provider calls in
  one request, which is exactly the kind of work CLAUDE.md says must go through
  Action Scheduler. Shipping a synchronous loop that times out halfway would be
  worse than not shipping it, so bulk lands with the queue.

## [0.9.0] - 2026-08-30

Phase 1, step 9: the QA gate. One real security finding, the i18n hole that let
it hide, and the WordPress.org disclosure that should have shipped in 0.5.0.

### Security

- **An unauthenticated caller could enumerate which post IDs existed, drafts
  included.** `POST /wsak/v1/scan` checked post existence and readability in the
  argument's `validate_callback`. WordPress runs argument validation *before*
  `permission_callback`, so that check answered anonymous callers — and answered
  them differently: "you do not have permission to read that content" for a post
  that existed, "that content could not be found" for an ID that did not. Titles
  and content were never exposed, only existence, but walking the integers
  mapped every private and draft post on the site.

  The check now runs in the handler, behind `can_scan()`. Anonymous callers get
  an identical 401 for every ID; a subscriber gets an identical 403; only a
  caller already entitled to scan can tell 404 from 422.
  `ScanControllerTest::test_no_argument_validator_performs_authorisation` fails
  the build if any route argument regains a `validate_callback`, because the
  rule worth encoding is the blunt one: validators check shape, handlers decide
  access.
- Full review recorded in `docs/security-review.md` — authorisation, SQL,
  secrets, escaping, CSRF, transport, uninstall, and a list of what was not
  checked.

### Fixed

- **JavaScript translator strings were not linted at all.** The i18n rules ship
  with `@wordpress/eslint-plugin` but are not in the config `wp-scripts lint-js`
  loads by default, and CI never ran `lint:js` in the first place. A translator
  call missing its text domain passed lint, passed the build, and was then
  dropped from the `.pot` in silence, because `make-pot` extracts only calls
  carrying the domain it was given — leaving the string permanently
  untranslatable with nothing anywhere saying so. Confirmed by removing a domain
  and watching the string vanish from the template. `eslint.config.cjs` now
  enables the i18n rules with the plugin's domain, and CI runs `lint:js` and
  `lint:css`.
- `(%d)` was declared with `_n()` and identical singular and plural forms, which
  costs every translator a plural form that can never differ, and carried two
  different translator comments for one string. Now a single `__()` with one
  comment. Same for "You can contact %s.", which had two comments.
- The translation template was stale. Regenerated: 311 strings, no warnings.
- **The CI check for a stale translation template could never pass.** It ran
  `make-pot` and then `git diff --exit-code`, but WP-CLI stamps
  `POT-Creation-Date` with the current time on every run, so the file always
  differed from the committed one whether or not a string had changed. The step
  would have failed on the first push — it has not bitten yet only because
  nothing has been pushed. `bin/makepot.sh` now strips that header, which makes
  the template reproducible; verified both ways, that two consecutive runs agree
  and that a changed string is still caught.

### Added

- `== External services ==` in readme.txt. Required by WordPress.org whenever a
  plugin contacts a third party, and missing since the provider adapters landed
  in 0.5.0 — submitting without it is a rejection. Covers all four providers,
  the exact endpoint each request goes to, what is sent and when, the
  300-character cap on page context, the `_wsak_skip_ai` opt-out, the site's own
  AI switch, and Freemius telemetry.

### Verified

- PHPCS clean across 94 files; PHPStan level 6 clean; 132 tests, 361 assertions.
- Plugin Check: **0 errors** against the built plugin. All 9 warnings are known:
  2 assembled-SQL statements and 7 direct-provider calls, both re-read for this
  review and documented in the release checklist. (The checklist had recorded
  five provider warnings; there are seven.)
- Free zip audited as a **zip**, not just as a source tree: 70 PHP files, no
  premium-only code, no Freemius gatekeeper secret.
- Every route called unauthenticated and as a subscriber; none returned data.

### Notes

- The terms and privacy-policy links in the new readme section were written from
  knowledge and have not been fetched. The endpoint URLs come from the adapters
  and are correct. Checking the policy links is a release-checklist item.
- `composer test` needs PHP 8.1–8.4; Brain Monkey does not run on 8.5, which is
  what a current Homebrew PHP installs.

## [0.8.0] - 2026-08-30

Phase 1, step 8: dogfooding. No new features — this step holds the plugin's own
surfaces to the standard it reports on, and makes both halves of the honesty
rule mechanical rather than a matter of review.

### Added

- `docs/accessibility-audit.md` — the audit of our own admin UI against WCAG 2.2
  AA: what was checked, how, what was found, and, at length, what has **not**
  been checked. Section 4 is the one to read before quoting any of the rest.
- `tests/Unit/DogfoodTest.php` — the accessibility statement, in every
  configuration it supports and both signed off and not, is scanned by the
  plugin's own engine and must come back clean. It also asserts the checks
  really ran, so the suite cannot pass silently if the rule registry is ever
  emptied by accident.
- `bin/check-disclosures.php` — asserts 17 required disclosures across 10
  conformance surfaces are still present. The companion to `check-claims.php`:
  that one stops us saying what we must not, this one stops us quietly dropping
  what we must say. The caveat under the score, the coverage lede, the warning
  above a suggested fix and the draft banner are all ordinary strings that a
  routine refactor could delete without breaking a test.
- `bin/check-contrast.js` — reads the palette out of `style.scss` and computes
  the real ratio for all 41 foreground/background pairings the admin UI renders,
  each at the size and weight it is actually rendered at. All 41 meet WCAG 2.2
  AA. Also available as `npm run lint:contrast`.

### Fixed

- **Focusable code blocks had no accessible name.** The issue-context and diff
  blocks are `tabindex="0"` so their overflow can be scrolled without a mouse,
  but landing on one announced raw markup with no indication of what it belonged
  to. Both now carry `role="group"` and a naming `aria-label` — a group rather
  than a region, because a page with thirty findings would otherwise add thirty
  landmarks to the list a screen reader user navigates by. (SC 4.1.2, 2.4.6)
- **The statement preview competed with the panel around it.** It injected its
  own `<h2>Accessibility statement</h2>` into a screen that already had one, so
  the outline showed two identically-named headings at the same rank and the
  preview's sections read as siblings of the panel's own. `StatementGenerator::render()`
  now takes a heading offset, clamped to 0–3 so output can never exceed `h6`;
  the published statement is unchanged and the preview nests below it. (SC 1.3.1)
- **Three live regions announced nothing.** Fix applied, fix undone and alt text
  saved each mounted a `role="status"` element with its text already in it, and
  a live region has to be in the document *before* its content changes or the
  announcement goes to a node the screen reader was not yet watching. Both
  components now keep one always-mounted, initially-empty region and write into
  it. (SC 4.1.3)
- `bin/check-claims.php` read `$argv`, which is not defined when
  `register_argc_argv` is off, so `composer lint` failed static analysis before
  reaching the tests. It reads `$_SERVER['argv']` now.

### Notes

- Contrast is measured but stacking is not: the guard checks the pairings named
  in its list, and a newly coloured element has to be added to that list to be
  covered. Same for `check-disclosures.php` and new conformance surfaces. Both
  guards are only as complete as their lists.
- The admin dashboard has still not been driven in a browser with axe-core, nor
  tested with any screen reader, nor tested with disabled users. Sections 3 and
  4 of the audit are explicit about which claims rest on source review rather
  than observed behaviour.

## [0.7.0] - 2026-08-23

Phase 1, step 7: the accessibility statement generator.

### Added

- A statement generator covering what the site owner says about their own site,
  the known problems, how to report a barrier, who to escalate to, and how the
  assessment was made.
- A `wowstudio/accessibility-statement` block and a
  `[wsak_accessibility_statement]` shortcode. Both render on the server from
  current settings, so a published statement can never be a stale copy of one.
- Sign-off: a named person records that they have read the statement and stand
  behind it.
- REST routes for reading, writing, signing off, and withdrawing.

### The honesty model

- **Nothing claims conformance on the owner's behalf.** Every conformance
  sentence begins with the organisation's name — "Example Ltd considers this
  website to be…" — because the plugin cannot verify any of it and should not
  appear to.
- **An unsigned statement publishes as a draft**, with a notice saying nobody
  has checked it. A document a plugin wrote is not a statement anybody made.
- **Editing withdraws the sign-off automatically.** An approved statement can
  never quietly come to say something its approver never read. Saving unchanged
  values does not withdraw it.
- **Sign-off is refused while the statement is unfinished** — no contact route,
  or a claim of partial conformance with no account of what falls short.
- **The limits of automated testing are stated in every rendering** and cannot
  be switched off, whatever status the owner selects.

### Fixed during development

- Adding the block silently stopped the admin app being built. wp-scripts
  discovers entry points from `block.json`, and once one existed it became the
  only entry — a successful build producing a blank dashboard. `webpack.config.js`
  now declares both entries explicitly. The `bin/build.sh` guard added in 0.4.0
  would have caught this at release; it is better caught here.

## [0.6.0] - 2026-08-23

Phase 1, step 6: one fix at a time, with preview, diff, apply, and undo.

### Added

- `wp_wsak_fixes` table, applied to an existing install by the versioned
  migration added in 0.2.0 — schema 1.0.0 to 1.1.0 with the other tables
  untouched.
- `Remediation\FixManager`: asks the configured provider for a corrected
  fragment, refuses proposals that change nothing or that balloon to several
  times the original, and records the result only when a person approves it.
- `Remediation\OverrideStore`: a non-destructive override layer. Applying a fix
  never edits post content. The correction is substituted as the page renders,
  so undo is a flag rather than a restore, and deactivating the plugin returns
  every page to its original markup by simply ceasing to filter.
- `Remediation\Diff`: a word-level comparison, returned as structured segments
  so the interface can label additions and removals in text rather than relying
  on colour.
- `generate_text()` on every provider, alongside the existing vision call.
- REST: preview, apply, revert, and list, all gated on `wsak_apply_fix`.

### Fixed during development

- The override layer was written as a string replacement, and it did not work.
  A scan reads the **rendered** page, where WordPress has already added
  attributes such as `decoding="async"`; the same element in post content
  carries fewer. The recorded markup therefore never matched byte-for-byte, and
  overrides stored cleanly and then silently did nothing. Caught by fetching a
  real page and looking at the img tag, not by any test.

  Matching now happens in the DOM (`Remediation\Substitution`) and is tolerant
  in one direction: a candidate matches when it is the same element and every
  attribute *it* carries also appears, with the same value, in the recorded
  markup. That accepts "WordPress added something on the way out" while still
  refusing to touch a genuinely different element. The filter also moved to
  priority 5, ahead of `wp_filter_content_tags`.

### Security

- Proposed markup is passed through `wp_kses` before it is stored. It came from
  a language model and will be substituted into a public page, so it is
  untrusted regardless of how carefully it was reviewed. Verified: a `<script>`
  tag in a proposal is stripped rather than stored.

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
