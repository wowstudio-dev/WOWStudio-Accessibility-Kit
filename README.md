# WOWStudio Accessibility Kit

A real-remediation WordPress accessibility plugin. It helps you **find, fix,
document, and monitor** WCAG issues at the code level.

It is not an overlay, and it never tells a user their site is compliant. See
[`CLAUDE.md`](CLAUDE.md) for the non-negotiable product rules and
[`SPEC.md`](SPEC.md) for the full feature specification and build order.

## Status

One plugin, free, nothing gated. 0.16.0 removed the Freemius SDK and the whole
AI layer; see [`CHANGELOG.md`](CHANGELOG.md) for what went and why.

What works: the two-pass scanner (29 checks — 24 on the server, 5 in the
browser), the inspector with a live page
preview, the block-editor panel, deterministic CSS fixes with preview and undo,
site-wide scanning, the bulk alt-text editor, theme triage, the overview
dashboard, and the accessibility-statement generator.

What is next, in order: getting the check count from 17 to ~48, building the
site-wide fix layer, then scheduled monitoring — which is the one thing the
plugin header promises and does not yet do. [`CLAUDE.md`](CLAUDE.md) has the
list.

## Requirements

| | |
|---|---|
| WordPress | 6.8+ |
| PHP | 8.1+ |
| Node | 20+ |
| Docker | required for `wp-env` and for running the PHP test suite locally |

## Setup

```bash
composer install
npm install
npx wp-env start      # WordPress at http://localhost:8888 (admin/password)
```

## Quality gate

Run the whole gate before every commit:

```bash
composer lint
```

That runs, in order:

| Command | Checks |
|---|---|
| `composer phpcs` | WordPress-Core, -Extra, -Docs, PHPCompatibilityWP |
| `composer phpstan` | Static analysis at level 6 |
| `composer test` | PHPUnit unit suite |
| `composer check-claims` | No unqualified compliance claims anywhere |
| `composer check-disclosures` | Every honesty caveat is still on the screen that needs it |
| `npm run plugin-check` | The official WordPress Plugin Check |

`composer lint` is the PHP half. The full 10-step gate — which also covers the
JavaScript, the stylesheet, the colour contrast of our own interface, and the
staleness of the translation template — is:

```bash
bash bin/gate.sh
```

`npm run plugin-check` and `npm run makepot` both build the plugin into `dist/`
first and run against that, not the working tree — otherwise they see
`node_modules`, dev Composer dependencies, and tooling config as shipped files.
See [`docs/RELEASE-CHECKLIST.md`](docs/RELEASE-CHECKLIST.md) for what a release
run involves.

Build the release artifact with:

```bash
npm run dist:zip
```

## One build

There is one build and no flavours. `bin/build.sh` produces exactly what ships,
so what the gate tests is what a user installs — which was never quite true
while a server-side stripper generated the real zip from a premium tree.

### Running tests locally

Brain Monkey depends on Patchwork, which does not support PHP 8.5. If your host
PHP is newer than 8.4, run the suite in a container:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.1-cli php vendor/bin/phpunit
```

CI runs the suite on PHP 8.1, 8.2, 8.3, and 8.4.

## Two guards worth knowing about

Both are wired into CI and both fail the build.

**`bin/check-claims.php`** enforces product rule #1 — assist, never guarantee.
It fails on any unqualified use of "compliant", "certified", "guaranteed",
"lawsuit", and similar, in any file that ships. A line is allowed only if it
negates the claim, carries a `wsak:claim-reviewed` annotation, or appears
verbatim in `bin/claims-allowlist.txt`.

**`bin/check-disclosures.php`** is the other half of the same idea. The claim
guard stops the plugin saying something it must not; this stops it quietly
dropping something it must say. Product rules 1, 4 and 5 all rest on ordinary
strings — the caveat under the score, the coverage lede, the draft banner on an
unsigned statement — and any of those can be deleted in a routine refactor
without breaking a test. Each surface it names must keep carrying its
disclosure; reword them freely, but update the pattern when you do.

## Repository layout

```
wowstudio-accessibility-kit.php   Bootstrap: headers, constants, requirement
                                  checks, lifecycle hooks including uninstall
src/Core/                         Orchestrator, activation, installation
src/Db/                           Schema, repositories, typed records
src/Scanner/                      Engine, rules, registry, page fetching
src/AltText/                      Finding images that have never been described
src/Jobs/                         Action Scheduler queue and the bulk-scan worker
src/Remediation/                  The override layer, diffing, and fix review
src/Conformance/                  The accessibility statement and its sign-off
src/Rest/                         REST controllers
assets/src/                       React admin app (source)
build/                            Compiled admin app (generated, not tracked)
src/Admin/                        Admin menu
src/Support/                      Shared helpers (capabilities)
bin/build.sh                      Release build (honours .distignore)
bin/gate.sh                       The 10-step quality gate
bin/                              Product guards
docs/RELEASE-CHECKLIST.md         What a human must verify before release
```

## Scanning

```
POST /wp-json/wsak/v1/scan       { "post_id": 12 }  requires wsak_run_scan
GET  /wp-json/wsak/v1/scans/<id>                    requires wsak_view_reports
GET  /wp-json/wsak/v1/scannable                     requires wsak_view_reports
GET  /wp-json/wsak/v1/coverage                      requires wsak_view_reports
GET  /wp-json/wsak/v1/overview                      requires wsak_view_reports
GET  /wp-json/wsak/v1/media                         requires wsak_apply_fix
POST /wp-json/wsak/v1/media/alt                     requires wsak_apply_fix
GET  /wp-json/wsak/v1/fixes/css                     requires wsak_apply_fix
POST /wp-json/wsak/v1/fixes/css                     requires wsak_apply_fix
DEL  /wp-json/wsak/v1/fixes/css/<issue_id>          requires wsak_apply_fix
POST /wp-json/wsak/v1/runs                          requires wsak_run_scan
GET  /wp-json/wsak/v1/runs/<id>                     requires wsak_view_reports
GET  /wp-json/wsak/v1/statement                     requires wsak_view_reports
POST /wp-json/wsak/v1/statement                     requires wsak_manage_settings
POST /wp-json/wsak/v1/statement/attest              requires wsak_manage_settings
POST /wp-json/wsak/v1/statement/withdraw            requires wsak_manage_settings
```

## The accessibility statement

Published with the `wowstudio/accessibility-statement` block or the
`[wsak_accessibility_statement]` shortcode. Both render on the server from
current settings, so what visitors see is always the statement as it now stands.

The plugin never claims conformance on anybody's behalf: every conformance
sentence is attributed to the organisation by name. An unsigned statement
publishes as a draft saying nobody has checked it, sign-off is refused while the
statement is unfinished, and **editing it withdraws the sign-off** so an approved
statement cannot come to say something its approver never read.

## The override layer

Applying a fix never edits post content. The correction is stored as a pair of
"this element" and "this element instead", and substituted as the page renders.
Undo is a flag, not a restore, and deactivating the plugin returns every page to
its original markup by ceasing to filter.

Matching happens in the DOM, not on the string, because a scan reads the
rendered page — where WordPress has already added attributes like
`decoding="async"` — while the override runs over post content, which has
fewer. See `Remediation\Substitution` for the direction that tolerance runs in
and why.

No route returns an API key. Reading the AI settings tells you whether a key is
stored and shows a masked hint; that is the most any caller can learn.

The scanner fetches the whole rendered page over a loopback request, because the
page language, title, landmarks, and most of the theme's markup live outside
post content. If loopback requests are blocked — many hosts block them, and
`wp-env` cannot reach its own mapped port — the scan falls back to post content
alone and labels the result: `full_page` is `false` and `coverage_notice`
explains what was skipped. The document-level rules recognise a fragment and
stay quiet, so a reduced scan under-reports rather than inventing failures.

To supply markup yourself, or to test the full-page path locally, filter
`wsak_page_html`. To make a loopback failure a hard error instead, return false
from `wsak_allow_content_fallback`.

## The admin app

Source lives in `assets/src`, compiled output in `build/`. `build/` is generated
and not tracked, so `bin/build.sh` compiles it and refuses to produce a release
without it.

```bash
npm run start     # watch
npm run build     # one-off
```

Translatable strings live in the JavaScript sources, not the minified bundle, so
`npm run makepot` copies `assets/src` into the build for the duration of the
extraction. Running `wp i18n make-pot` against the build alone silently drops
every string in the app.

## License

GPL-2.0-or-later. See [`LICENSE`](LICENSE).
