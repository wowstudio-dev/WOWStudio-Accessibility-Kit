# CLAUDE.md — WOWStudio Accessibility Kit

Operating brief for Claude Code. **Read `SPEC.md` for the detail** — but read the
"Superseded" block at the top of it first, because the tiering, AI and Freemius
sections of that document describe a product that no longer exists. This file is
the short list of rules and context that always apply, and where the two
disagree, this file wins.

## What we're building
A **real-remediation** WordPress accessibility plugin that finds, fixes,
documents, and monitors WCAG issues at the **code level**. Publisher:
**WOWStudio**. Current phase: rebuilding the free plugin to beat Equalize
Digital's Accessibility Checker.

Positioning: *"helps you find, fix, document, and monitor"* — never *"makes you
compliant."*

## There is one plugin, and it is free
As of 0.16.0 there is **no paid tier, no licensing SDK, and no AI**. Everything
in the plugin is free and nothing is gated. Do not add a locked control, an
upgrade prompt, a usage cap, or a tier check.

A paid add-on is planned but not started. It will be a **separate plugin that
attaches to this one**, never a stripped build of it — so the free plugin ships
complete and nothing is ever taken away from anybody who already had it. The
frozen `Pro` branch and `PRO-NOTES.md` hold that plan; the tag
`pro-seed-0.15.1` is the last tree containing the removed Freemius and AI code.

**The line, for when it matters: never gate finding. Gate automation,
deliverables, and marginal cost.** Withholding a check means telling somebody
their site has a barrier that will not be described until they pay, and the
person who loses that trade is the disabled visitor rather than the site owner.
Every check, every severity, every post type, and the site-wide scan stay free.

## Non-negotiable product rules (never violate)
1. **Assist, never guarantee.** No string, label, doc, or marketing output may
   say "compliant," "ADA/EAA compliant," "lawsuit-proof," "certified," or
   "100%." Use "helps you find / fix / document / monitor." (Legal-risk
   critical — the accessiBe/FTC precedent.) Enforced by `bin/check-claims.php`.
2. **Not an overlay.** Never inject a front-end accessibility widget or
   toolbar. No font-size control, contrast switcher, greyscale mode, or
   link-highlighter. These were considered and deliberately rejected on
   2026-09-06: they fix nothing, the browser and OS already do all of them
   better, and shipping one would forfeit the accessibility community whose
   judgement this product is built to earn. Fixes are real markup and style
   changes.
3. **Human-in-the-loop.** Every automated fix is a reviewable suggestion with
   preview + diff + undo. Nothing irreversible happens silently.
4. **Honesty about coverage.** Always tag issues `auto-detected` vs
   `needs manual review`; state that automation covers only part of WCAG.
   Enforced by `bin/check-disclosures.php`.
5. **Draft, attested docs.** Conformance reports render as DRAFTs the user
   reviews and attests to — the plugin never asserts conformance itself.
6. **Dogfood.** The plugin's own admin UI must pass WCAG 2.2 AA.
7. **No outbound requests.** The plugin contacts nothing. No API, no account,
   no telemetry, no phone-home. This is now a feature we advertise, and adding
   a request would break a promise in `readme.txt`.

See `SPEC.md` → "Explicitly NOT included (anti-features)" for the full stop-list.

## Tech stack
- WordPress ≥ 6.8. PHP ≥ 8.1 (held there deliberately on 2026-09-06 despite the
  competitor's 7.4 floor; the rewrite cost across `src/` is not worth the
  install-base gain). PSR-4 autoload via Composer.
- Admin UI: React via `@wordpress/scripts` (`@wordpress/components`, `data`,
  `api-fetch`). Gutenberg block for the accessibility statement.
- Background jobs: **Action Scheduler** (never block an admin request).
- Storage: custom tables via `dbDelta`; settings in options; per-post scan cache
  in postmeta.
- Charts are hand-rolled accessible SVG. Do not add a charting library: the
  canvas ones draw pixels a screen reader cannot see, which is not defensible
  inside an accessibility plugin.

## Naming & conventions
- Namespace `WOWStudio\AccessibilityKit` · function/hook prefix `wsak_` · DB
  tables `wp_wsak_*` · text domain `wowstudio-accessibility-kit` · license
  GPLv2+.
- Repo layout, data model, and REST routes are defined in `SPEC.md`.

## Where the work is
Roughly in order. The competitor ships 44 checks and 11 free site-wide fixes;
we ship 40 checks, 14 site-wide fixes and 4 one-click fixes, so we are ahead on
both.

1. **Checks, 40 → ~48.** Twelve landed in 0.17.0: alt text that is a file name,
   a placeholder or a repeated caption; alt long enough to be a paragraph;
   image-map regions with no name; links that open a new tab or download a file
   without saying so; in-page links and ARIA references pointing at ids that do
   not exist; anchors that click but cannot be tabbed to; empty headings; pages
   with no headings at all; labels attached to nothing or doubled up.

   Eleven more in 0.18.0: empty table headers, unnamed image buttons,
   duplicate ids, positive tabindex, viewports that block zooming, blinking and
   marquee, justified text, underlines that are not links, bold paragraphs
   standing in for headings, video with no caption track, and audio with no
   transcript.

   Still to come: tiny text (needs the browser pass — it is a computed size,
   not a declared one), carousels, and animated GIFs, which means reading frame
   counts out of the file rather than guessing from the extension.

   **Every new rule needs a pair of tests**, one that trips it and one on the
   nearest correct markup that must not. `tests/Unit/ContentRulesTest.php` is
   the pattern. A check that fires on everything is worse than no check: it
   trains people to skim past the findings that matter.
2. **Site-wide fixes.** Nine shipped in 0.19.0: skip link with its target,
   focus outline, link underline, `lang`/`dir`, page title, comment and search
   labels, new-window warning, download file info, block PDF uploads. They live
   in `src/SiteFixes/` behind the `wsak_site_fixes` filter.

   Five more in 0.20.0, and these needed a decision: scalable viewport,
   stripping positive tabindex, stripping redundant `title`, the empty-search
   message, and naming fields from their placeholder. A server pass cannot
   reach any of them — they correct markup the theme already printed — so they
   run in the browser from `assets/front/site-fixes.js`, marked by the
   `RunsInBrowser` interface so the decision is auditable. **Read that
   interface's docblock before adding a sixth.** It adds no widget, no
   controls and no interface of any kind, which is what keeps it on the right
   side of rule 2, and every such fix must say in its caveat that it does
   nothing with JavaScript off.

   Constraints for anything added here: no writing to user content, no output
   buffering (SPEC F6), and every fix states what it might disturb — a test
   enforces the last one. Nothing may invent content: `label-form-fields`
   promotes a placeholder the author wrote and refuses to manufacture a name
   from an `id`, because a plausible label is worse than a missing one — it
   closes the finding and leaves the reader no better off.
3. **Free-tier features the competitor charges for**: full-site report screen,
   admin columns, dismissal log, extra post types, CSV export.
4. **Monitoring** — scheduled re-scans and regression alerts. `SPEC.md` §F is
   still entirely unbuilt while "monitor" ships in the plugin header, which is
   the largest gap between what we say and what we do.
5. **Readability + simplified summary** (WCAG 3.1.5), **WP-CLI including bulk**,
   per-issue documentation.
6. **Page-builder compatibility**: Gutenberg, Classic, ACF, Avada, Beaver
   Builder, Divi, Elementor, Oxygen, WP Bakery, WooCommerce.
7. **1.0.0 and WordPress.org submission.**

Build the four extension seams as you go, because retrofitting them is
expensive: the scan runner (so a scheduler can drive it), the report screen (so
exporters can register formats), the fix pipeline (so a fix provider can
register), and the dismissal flow (so role restrictions can hook the capability
check).

## Quality floor (every PR)
- Security: verify nonce + `current_user_can()` on every write/REST route;
  sanitize input, escape output, `$wpdb->prepare()` for all SQL.
- i18n: all user-facing strings translatable with the text domain.
- Accessible admin UI: WCAG 2.2 AA, full keyboard operability, visible focus,
  `prefers-reduced-motion` respected, semantic markup.
- Performance: heavy work through Action Scheduler.
- `bin/gate.sh` must pass — 10 steps, all of them.

## Commands
```bash
composer install        # PHP autoload + PHPCS
npm install             # wp-scripts + React
npm run start           # watch/build admin app  (npm run build for release)
npx wp-env start        # local WordPress → http://localhost:8888
bash bin/gate.sh        # the 10-step quality gate — run before every commit
```
The host PHP is 8.5 and cannot run the suite; `gate.sh` uses a `php:8.1-cli`
container for the PHP steps.
