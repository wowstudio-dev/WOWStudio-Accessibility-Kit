# WOWStudio Accessibility Kit — Product & Build Spec
### Accessibility Remediation + Conformance
*Publisher: WOWStudio · Status: partly superseded, see below · Monetization: none, the plugin is free*

---

## ⚠️ Superseded on 2026-09-06 — read this before anything else

This document was written for a two-tier product with an AI remediation engine
sold through Freemius. **That product does not exist.** As of 0.16.0 there is
one plugin, it is free, nothing is gated, there is no licensing SDK, and there
is no AI of any kind. The plugin makes no outbound requests at all.

The decision, in short: held against an established free competitor
(Equalize Digital's Accessibility Checker — 44 checks, 11 free site-wide fixes,
version 1.49.0), a paywall in the same place theirs is buys nothing. Market
validation belongs to the incumbent, and an unknown plugin is only ever judged
on its free tier. So the free tier is the whole product for now.

**These sections no longer describe the code and must not be built from:**

| Section | Status |
|---|---|
| Monetization & licensing — Freemius | Gone. No SDK, no plans, no gating, no telemetry. |
| C. AI Remediation Engine | Gone. `src/AI/` and the generative fix path were deleted. |
| D. AI Alt-Text & Media SEO | Replaced by a manual bulk alt-text editor. Nothing is generated. |
| H. Agency / Multisite | Not started, and not a tier. |
| Tier boundary (mapped to Freemius plans) | Gone. Nothing is tiered. |
| Freemius integration details | Gone. |
| AI integration | Gone. |
| Every `[Free]` / `[Pro]` / `[Agency]` tag below | Ignore. Everything is free. |

**These sections still stand and are still the source of truth:** the guiding
product rules, B (scanning engine), E (conformance documentation), F
(monitoring — still entirely unbuilt), G (dashboard), I (manual-testing aids),
K (platform, performance & privacy), L (dogfooding), the anti-features
stop-list, the repository structure, the data model, the coding standards, and
the whole Phase 2 and Phase 3 decision record from "Phase 2, step 1" onward.
Those decisions were made against real problems and the reasoning is still good;
where one of them turns on a tier or on a model, the tier is gone and the model
is gone, and the rest of the reasoning holds.

**For what is being built now**, see `CLAUDE.md` → "Where the work is". For the
planned paid add-on and the line it would draw, see `PRO-NOTES.md` on the frozen
`Pro` branch. The tag `pro-seed-0.15.1` is the last tree containing the removed
Freemius and AI code.

---

> This file doubles as the Claude Code project brief. Drop it in the repo root as `SPEC.md` (or reference it from `CLAUDE.md`). Build in the order given under **Getting started for Claude Code**.

**Positioning (one line):** A real-remediation accessibility plugin that *finds, fixes, documents, and monitors* WCAG issues at the code level — explicitly **not** an overlay, and it never claims to "make you compliant."

### Plugin metadata
| Field | Value |
|---|---|
| Plugin name | WOWStudio Accessibility Kit |
| WordPress.org slug | `wowstudio-accessibility-kit` |
| Text domain | `wowstudio-accessibility-kit` |
| Namespace / prefix | `WOWStudio\AccessibilityKit` · `wsak_` · DB tables `wp_wsak_*` |
| Requires WP | 6.8+ — raised from 6.6 by decision F10 (Action Scheduler 4.x) |
| Requires PHP | 8.1+ (min declared 8.0). Held at 8.1 on 2026-09-06 despite the competitor's 7.4 floor. |
| License | GPLv2 or later (WordPress.org requirement) |
| Monetization | None. The plugin is free and nothing is gated. |

**Tier tags are dead.** `[Free]`, `[Pro]` and `[Agency]` appear throughout the
sections below and mean nothing now — everything is free. They are left in place
rather than stripped because the paid add-on will need to know which features
were once thought worth charging for, and because rewriting 900 lines to delete
a bracket would bury the decisions the lines actually record.

---

## Guiding product rules (non-negotiable)
- **Assist, never guarantee.** No feature, label, or output says "compliant," "ADA/EAA-compliant," "lawsuit-proof," or "100%." Language is always "helps you find / fix / document / monitor." (The accessiBe/FTC lesson — it's existential.)
- **Human-in-the-loop by default.** Every automated fix is a reviewable *suggestion*; nothing irreversible happens silently.
- **Honesty about coverage.** The UI always distinguishes machine-detectable issues from those needing human review, and states that automated testing covers only part of WCAG.
- **Not an overlay.** No front-end accessibility widget/toolbar is injected. Fixes are real code/markup changes.
- **Draft, attested documents.** Conformance reports are generated as *drafts the site owner reviews and attests to* — the plugin never asserts conformance on the user's behalf.
- **The plugin dogfoods.** Its own admin UI must pass WCAG 2.2 AA.

---

## Monetization & licensing — Freemius *(SUPERSEDED — none of this exists)*

> Removed in 0.16.0. Kept only as the record of what was built and then taken
> out; do not implement any of it. See the superseded block at the top.

- **Free version** ships to WordPress.org (the funnel). **Pro** is delivered and licensed through Freemius (checkout, subscriptions, trials, auto-updates, license management, analytics). Freemius deploy produces both the free (.org) zip and the premium zip from one codebase.
- **Feature gating** uses the Freemius SDK, not homegrown license checks. Pattern:
  - `wsak_fs()->can_use_premium_code()` — wrap all Pro code paths.
  - `wsak_fs()->is_plan( 'pro', true )` / `is_paying()` / `is_free_plan()` — plan-specific checks.
  - Pro-only methods use the `__premium_only` suffix and Pro-only files use the `-premium.php` suffix so Freemius strips them from the free zip. Partial premium blocks use the `@fs_premium_only` doc tag.
- **Plans in Freemius dashboard:** `free`, `pro` (add `agency` in Phase 3). Map each `[Pro]`/`[Agency]` feature below to the matching plan.
- **Trials:** offer a Pro free trial (no card) via Freemius to drive conversion from the alt-text funnel.
- **Telemetry:** Freemius opt-in is **opt-in only** and clearly disclosed — consistent with our privacy stance. Never enable silent tracking.
- **AI stays BYOK** (see AI integration) — Freemius handles *plugin* monetization, not AI cost. Managed AI credits, if ever added, would be sold later as a Freemius credit pack/add-on.

---

## A. Onboarding & Compliance Profile
- `[Free]` Setup wizard: target standard selector (**WCAG 2.2 AA** default; 2.1 AA option).
- `[Free]` "Which laws may apply to me" advisor — EAA, ADA Title III, Section 508, AODA, UK Equality Act — based on sectors served and where customers are. Educational, clearly labeled *not legal advice*.
- `[Free]` Microenterprise / scope self-check (EAA: <10 employees AND ≤€2M) with plain-English result.
- `[Free]` Baseline scan on activation + starting accessibility score.
- `[Pro]` Risk profile & priority weighting (e.g., e-commerce checkout flows first).

## B. Automated Scanning Engine *(core)*
- `[Free]` Per-page scan against WCAG 2.1 / 2.2 A & AA rules (server-side PHP DOM analysis, no per-scan API cost — see *Scanning engine approach*).
- `[Pro]` Full-site crawler + bulk scan across all published content, templates, block patterns, and WooCommerce templates.
- `[Pro]` Scheduled / automatic re-scans (daily / weekly / on-publish, or a **custom scheduler** — user-defined intervals, specific days/times, and per-section schedules via custom cron).
- `[Free]` In-editor checks for Gutenberg & Elementor — flag issues on the post being edited, before publish.
- `[Free]` Issue classification by WCAG success criterion, severity, page, and element, with **"auto-detected" vs "needs manual review"** honesty tags.
- `[Free]` Coverage-transparency panel: shows which criteria automation can and cannot verify.
- `[Free]` False-positive / ignore management (per-issue and rule-level), with notes.
- `[Pro]` Custom scan rules & per-rule severity overrides (hooks/filters).

## C. AI Remediation Engine *(SUPERSEDED — removed in 0.16.0)*

> `src/AI/`, the four provider adapters, the WP AI Client bridge, the encrypted
> key store and the generative fix path were all deleted. The override layer,
> the preview/diff/undo contract and `FixKind` × `FixTarget` survive and are
> still correct — it is only the thing that produced the suggestion that is
> gone.

- `[Free]` One-at-a-time AI fix suggestions (capped) via WP 7.0 AI Client / BYOK (OpenAI, Anthropic, Gemini, OpenRouter).
- `[Pro]` **Bulk AI remediation** across the site.
- Fix types (AI-generated, reviewable):
  - `[Free]` Alt text for images (see Module D).
  - `[Pro]` ARIA roles/labels, landmark regions, button/link accessible names.
  - `[Pro]` Form field labels & error-message associations.
  - `[Pro]` Heading-structure correction (skipped levels, multiple H1s).
  - `[Pro]` Descriptive link text ("read more" → meaningful).
  - `[Pro]` Table header/scope fixes.
  - `[Pro]` Language attribute & document-title fixes.
  - `[Free]` Contrast fixes with compliant colour proposals (preview swatches), one page at a time; `[Pro]` in bulk. See Phase 2 step 2, decision E8.
  - `[Pro]` Skip-links / keyboard-focus fixes.
- `[Free]` **Preview + diff** for every fix; apply / reject; full undo.
- `[Pro]` Non-destructive apply: fixes stored as overrides/filters where possible; optional "write to source" mode with backup.
- `[Pro]` Auto re-scan after applying to confirm resolution.
- `[Free]` "Explain this issue" AI tutor — why it fails, who it affects, how to fix.
- `[Pro]` Remediation changelog / rollback history.

## D. Alt Text *(rewritten in 0.16.0 — nothing is generated)*

> Replaced by a manual bulk editor: every image in the media library that has
> never been described, listed with a field beside each. It writes
> `_wp_attachment_image_alt`, so a description applies wherever the image is
> used and survives the plugin being deleted. Images already marked decorative
> are excluded — an empty alt is somebody's decision, not a gap.

- `[Free]` **AI alt-text generation** for images via vision model (BYOK) — single image + limited/capped bulk. *This is the funnel.*
- `[Free]` Context-aware alt text (uses surrounding text, page/post title).
- `[Free]` Decorative-image detection → correct empty `alt=""` handling.
- `[Free]` Auto-generate on upload; "only fill empty" / overwrite-protection modes.
- `[Pro]` Full media-library bulk backfill (thousands of images, queued via Action Scheduler).
- `[Pro]` WooCommerce product-image alt text at scale (uses product title/attributes).
- `[Pro]` **Media SEO layer:** filename suggestions, image title/caption/description, image structured data (schema), image-sitemap hints.
- `[Pro]` Multi-language alt text aligned to site locale (WPML/Polylang aware).
- `[Pro]` Technical media checks: missing width/height, lazy-load, oversized images.

## E. Conformance Documentation
- `[Free]` **Accessibility Statement generator** (EAA/ADA-aware, editable) — hosted page + block/shortcode, with the user-feedback contact mechanism the EAA expects.
- `[Pro]` **Draft Accessibility Conformance Report (ACR / VPAT-style)** — clearly watermarked *DRAFT*, timestamped, states automated-coverage limits, requires explicit human attestation before finalizing.
- `[Pro]` WCAG audit report export (PDF / HTML / CSV), per page and whole-site.
- `[Pro]` **Evidence log / audit trail** — every scan, fix, and date, exportable, to support "disproportionate burden" or due-diligence records.
- `[Pro]` Statement & report versioning.

## F. Monitoring & Alerts *(STILL UNBUILT — and "monitor" is in the plugin header)*

> The largest gap between what this product says and what it does. No longer a
> paid tier; it is simply missing.

- Scheduled re-scans with drift detection.
- Change-detection: new/edited content and updated plugins/themes re-checked automatically.
- Regression alerts (a previously-fixed issue reappears).
- Notifications: email, Slack, webhook.
- Compliance-score trend over time.
- `[Pro]` Optional publish-gate: warn (or block) publishing content with critical issues.

## G. Dashboard & Reporting
- `[Free]` Central dashboard: score, issues by severity & WCAG SC, top offending pages.
- `[Free]` Per-page drill-down with element highlighting.
- `[Pro]` Impact-ranked remediation queue (fix highest-impact first).
- `[Free]` Progress tracking: open / fixed / ignored counts.
- `[Pro]` Trend charts and historical snapshots.
- `[Agency]` White-label, client-branded PDF reports + scheduled delivery.

## H. Agency / Multisite *(not started, and no longer a tier)*

- WordPress Multisite network support.
- Per-site licensing / central management (via Freemius multisite licensing).
- White-label branding (plugin name, logo, report headers).
- Roles & permissions: who can scan, who can apply fixes, who can only view.
- Bulk operations across sites; consolidated cross-site dashboard.

## I. Manual-Testing Aids *(the honesty layer)*
- `[Free]` Guided checklists for non-automatable criteria (keyboard nav, focus order, meaningful sequence, screen-reader spot checks).
- `[Free]` Contrast checker + accessible palette suggestions.
- `[Pro]` Keyboard-navigation / focus-order visualizer.
- `[Pro]` Simulators: color-blindness and low-vision previews for spot-checking.
- `[Pro]` Screen-reader output hints for a given page region.

## J. Integrations
- `[Free]` Gutenberg & Elementor; `[Pro]` Bricks / Divi.
- `[Pro]` WooCommerce (product media + checkout-flow checks).
- `[Pro]` SEO plugins (Yoast / Rank Math) — alt-text & schema handoff, conflict avoidance.
- `[Pro]` WP-CLI: `scan`, `generate-alt`, `remediate`, `export-report`.
- `[Pro]` REST API + **WP 7.0 Abilities API** registration (`scan_accessibility`, `generate_alt_text`, `remediate_issue`) so AI agents / MCP clients can drive it.
- `[Pro]` Multilingual: WPML / Polylang.

## K. Platform, Performance & Privacy
- `[Free]` WP 7.0 AI Client integration (provider-agnostic) with **BYOK** fallback for older WP versions; API keys stored encrypted.
- `[Free]` Server-side, queued/batched scanning (Action Scheduler); **no front-end overlay injected**.
- `[Free]` Privacy controls: exactly what content is sent to the AI provider is disclosed and configurable; "only send what's needed" mode; option to skip AI for sensitive pages.
- `[Pro]` Data-processing disclosure + DPA-friendly settings; AI-subprocessor transparency.
- `[Free]` Backup/rollback for any applied fix; clean uninstall.

## L. Product's Own Compliance *(dogfooding)*
- Ships with `security.txt`, a vulnerability-disclosure policy, and an SBOM — your own CRA hygiene (reuse the CRA-kit outputs).
- In-product "assist not guarantee" disclaimers on every conformance/report screen.
- Accessible admin UI (the plugin itself must pass WCAG 2.2 AA — table stakes for credibility).
- Freemius telemetry opt-in only, clearly disclosed.

---

## Tier boundary *(SUPERSEDED — there are no tiers)*

> Nothing below is gated. Kept as the record of where the line was once drawn.


*Revised 2026-08-31 — see Phase 3, decision F1. The line is scale, not
capability: **the free tier fixes a page, the paid tier fixes a site and keeps
it fixed.***

**Free plan (WordPress.org, the funnel):** unlimited single-page scanning from
the dashboard and from the editor; the full inspector — live preview, hover
placement, both scanning passes, every check; deterministic auto-fixes on one
page; AI fixes and alt text one at a time under the daily cap; CSS fixes one page
at a time; the accessibility-statement generator; the coverage panel.

**Pro plan (Freemius):** bulk scanning by post type, the bulk review queue,
deterministic fixes applied across a site, uncapped and bulk alt text, scheduled
re-scans, monitoring/alerts/trends, history and export, conformance report +
evidence log, WooCommerce at scale, WP-CLI + Abilities API, priority support.

**Agency plan (Freemius, Phase 3):** multisite, white-label client reports, roles/permissions, cross-site management, unlimited sites.

---

## Build phasing (don't build it all at once)

**Phase 1 — MVP / launch (the wedge):** AI alt-text (Free) + single-page WCAG 2.2 scanner + issue list with auto/manual honesty tags + one-at-a-time AI fix + basic dashboard + accessibility-statement generator + Freemius Free/Pro scaffold. Ships the funnel and proves the AI-remediation angle.

**Phase 2 — see the page, not just the markup** *(built):* a browser pass of our
own for the render-dependent checks, the inspector that shows a finding on the
page it came from, and CSS remediation written into the site's own Additional
CSS and verified by re-measuring. Steps 1 and 2 above.

**Phase 3 — from one page to a whole site:** the queue, bulk scanning by post
type, bulk alt text with a review screen, checks inside the editor, fixes that
write to the content, theme triage, and the plain-language layer that makes any
of it readable by the people who run the site. Full record below.

**Phase 4 — expand & defend:** WooCommerce depth, agency/multisite + white-label,
WP-CLI + Abilities API, simulators & focus visualizer, extra page-builder
integrations.

---

## Explicitly NOT included (anti-features)
- ❌ No overlay / accessibility widget or toolbar on the front end. **Reaffirmed 2026-09-06** against a specific proposal to add one: a toolbar with font-size, contrast, greyscale and link-highlight controls. Rejected, and the reasoning is worth keeping because the proposal will recur. None of the four fixes anything — they sit on top of a site that is still broken underneath. All four are already done better by the browser and the operating system (page zoom, forced-colors, OS greyscale, reader mode). And the audience this product is trying to win is the accessibility community, which treats overlays as actively harmful; shipping one is the fastest available way to be dismissed by exactly the people whose word-of-mouth we need. What *was* taken from that proposal is the half that is real: skip links, focus indicators, keyboard-navigation fixes and ARIA repairs, all of which are genuine code changes and all of which are on the build list.
- ❌ No "guaranteed compliance," "ADA/EAA compliant," or "lawsuit protection" claims anywhere.
- ❌ No silent, unreviewed auto-fixes to live content.
- ❌ No auto-published conformance report asserting a level without human attestation.
- ❌ No outbound requests at all. There is no AI, no API, no account and no telemetry, and `readme.txt` promises as much. Adding one would break that promise.
- ❌ No gating. No locked control, no upgrade prompt, no usage cap, no tier check. If a paid add-on ever ships it attaches to this plugin and only ever adds.
- ❌ No buffering and rewriting the whole front-end page at runtime. See Phase 3, decision F6: it would "fix" far more than a filter can reach, and it is the accessiBe pattern wearing our clothes.

---

# ── For Claude Code: development ──

## Tech stack & requirements
- **WordPress** ≥ 6.8 *(raised from 6.6 — see decision F10)*, with first-class support for **WP 7.0** (AI Client, Abilities API, Command Palette); graceful degradation below 7.0 via the BYOK adapter.
- **PHP** ≥ 8.1 (declare 8.0 minimum). PSR-4 autoloading via Composer.
- **Admin UI:** React through `@wordpress/scripts` (wp-scripts / webpack), `@wordpress/components`, `@wordpress/data`, `@wordpress/api-fetch`. Gutenberg block for the accessibility statement.
- **Background jobs:** Action Scheduler (bundled) for queued bulk scans, remediation, and alt-text.
- **Licensing/payments:** Freemius WordPress SDK (`/freemius`).
- **AI:** WP 7.0 AI Client abstraction when present; BYOK provider adapters (OpenAI, Anthropic, Gemini, OpenRouter) otherwise. Vision model for alt text.
- **Storage:** custom tables via `dbDelta`; settings in options; per-post scan cache in postmeta.
- **Tooling:** Composer (autoload + PHPCS), npm (wp-scripts). Testing: PHPUnit + `wp-env`; Playwright optional for e2e.
- **i18n:** text domain `wowstudio-accessibility-kit`, all strings translatable.

## Scanning engine approach *(resolves open decision #1)*
- **MVP = PHP server-side static analysis** with `DOMDocument` / `DOMXPath` over rendered post/page HTML. Covers the machine-detectable subset that does **not** need CSS rendering: missing/empty `alt`, unlabeled form controls, empty/undescriptive links & buttons, heading order + multiple `H1`, missing landmarks, `lang` attribute, document `title`, table headers/scope, basic ARIA misuse. Zero per-scan cost, no headless browser.
- **Contrast & CSS-dependent checks** can't be done reliably server-side → a **browser pass of our own**, run in the admin against the live page in the inspector's frame. Do **not** bundle a headless browser, and do **not** bundle axe-core — see Phase 2 step 1, decision D1, which reversed the original plan. Fully specified below.
- Keep rules in a registry (id → WCAG SC → severity → detector → `auto|manual` flag) so new rules and per-rule overrides are trivial.

### How the competition does it *(measured, 2026-08-30)*

Equalize Digital Accessibility Checker 1.48.0 — 10,000+ installs, the incumbent — does **not** do server-side analysis. It bundles **axe-core 4.11.1** in a 635 KB script, opens a hidden same-origin `<iframe>` on the rendered page from the editor, and runs axe against the live DOM. All 44 of its rules are `'ruleset' => 'js'`; none is licence-gated.

Two consequences shape our plan:

1. **They see computed style and we do not.** Colour contrast, small text, justified text, blink/scroll — an entire class we cannot reach from PHP at any rule count. This is the gap that gets the plugin dismissed in a review, and no amount of new PHP rules closes it.
2. **We scan headlessly and they cannot.** Their scan only exists while a browser holds the page open. Ours queues through Action Scheduler, runs from WP-CLI, and scales to a whole site unattended. Their bulk scan is a paid feature partly because it is architecturally awkward for them.

The plan below keeps the server pass as the spine and adds the browser pass as an augmentation — deliberately the inverse of their architecture, so we gain their coverage without losing our unattended scanning.

## Proposed repository structure
```
wowstudio-accessibility-kit/
├── wowstudio-accessibility-kit.php     # main file: headers, constants, Freemius init, bootstrap
├── uninstall.php
├── composer.json  package.json  .wp-env.json  phpcs.xml.dist
├── freemius/                           # Freemius SDK (added via SDK)
├── src/
│   ├── Core/            (Plugin.php, Activator.php, Deactivator.php, Assets.php)
│   ├── Scanner/         (Engine.php, RuleRegistry.php, Rules/*.php, Result.php)
│   ├── Remediation/     (FixManager.php, OverrideStore.php, Diff.php)
│   ├── AI/              (ProviderInterface.php, Providers/{OpenAI,Anthropic,Gemini,OpenRouter}.php, AiClientBridge.php, KeyStore.php)
│   ├── AltText/         (Generator.php, MediaSeo.php)
│   ├── Conformance/     (StatementGenerator.php, ReportBuilder.php, EvidenceLog.php)
│   ├── Rest/            (ScanController.php, FixController.php, AltTextController.php)
│   ├── Admin/           (Menu.php, SettingsPage.php)
│   ├── Db/              (Schema.php, ScanRepository.php, IssueRepository.php)
│   └── Support/         (Capabilities.php, Nonce.php, I18n.php)
├── assets/src/          (React app: dashboard, issue list, settings; block)
├── build/               (compiled JS/CSS — wp-scripts output)
└── languages/
```

## Freemius integration details *(SUPERSEDED — the SDK is gone)*

Init in the main plugin file **before** loading the rest of the plugin. **Copy the current integration snippet from the Freemius Developer Dashboard** rather than hand-typing this — since Aug 2025 the snippet embeds a wp.org gatekeeper marker that blocks accidental premium-code submissions (and is stripped from the generated free build on deploy). The block below is illustrative of the shape only:
```php
if ( ! function_exists( 'wsak_fs' ) ) {
    function wsak_fs() {
        global $wsak_fs;
        if ( ! isset( $wsak_fs ) ) {
            require_once __DIR__ . '/freemius/start.php';
            $wsak_fs = fs_dynamic_init( array(
                'id'                  => 'REPLACE_WITH_FS_ID',
                'slug'                => 'wowstudio-accessibility-kit',
                'type'                => 'plugin',
                'public_key'          => 'REPLACE_WITH_FS_PUBLIC_KEY',
                'is_premium'          => true,
                'premium_suffix'      => 'Pro',
                'has_premium_version' => true,
                'has_paid_plans'      => true,
                'trial'               => array( 'days' => 14, 'is_require_payment' => false ),
                'menu'                => array( 'slug' => 'wowstudio-accessibility-kit' ),
            ) );
        }
        return $wsak_fs;
    }
    wsak_fs();
    do_action( 'wsak_fs_loaded' );
}
```
Gating conventions:
- Wrap Pro logic: `if ( wsak_fs()->can_use_premium_code() ) { /* Pro */ }`.
- Plan checks: `wsak_fs()->is_plan( 'pro', true )`, `->is_paying()`, `->is_free_plan()`.
- Pro-only methods → `bulk_remediate__premium_only()`; Pro-only files → `class-bulk-remediation-premium.php`; partial premium blocks → `@fs_premium_only` doc tag. Freemius strips these from the free zip on deploy.
- Configure `free`, `pro` (and later `agency`) plans in the Freemius dashboard; map every `[Pro]`/`[Agency]` feature accordingly.

## AI integration *(SUPERSEDED — there is no AI)*

- One `ProviderInterface` (`generateText`, `generateAltText(image, context)`), with adapters per provider and an `AiClientBridge` that prefers the WP 7.0 AI Client when available, else BYOK.
- **Key storage:** encrypt at rest (libsodium via `sodium_crypto_secretbox`, key derived from `wp_salt()`); never log keys; never expose via REST.
- **Privacy:** "only send what's needed" mode (send the failing node + minimal context, not whole pages); per-page opt-out for sensitive content; disclose provider/subprocessor in settings.
- **Cost guardrails:** enforce the Free alt-text cap here; batch via Action Scheduler; expose token/usage estimates.

## Data model (custom tables via dbDelta)
- `wp_wsak_scans` — id, scope (page/site), target_id, started_at, finished_at, score, summary.
- `wp_wsak_issues` — id, scan_id, post_id, wcag_sc, rule_id, severity, selector/xpath, message, detection (`auto|manual`), status (`open|fixed|ignored`).
- `wp_wsak_fixes` — id, issue_id, type, provider, before, after, applied_at, applied_by, reverted_at.
- `wp_wsak_evidence` — append-only audit log (scan/fix/report events) for conformance records.
- Settings in options (`wsak_settings`); per-post latest-scan cache in postmeta.

## Local dev setup
```bash
composer install            # PHP autoload + PHPCS
npm install                 # wp-scripts + React deps
npm run start               # watch/build admin app  (npm run build for release)
npx wp-env start            # local WordPress at http://localhost:8888
composer run phpcs          # WordPress Coding Standards
```
Use Freemius **sandbox/dev mode** for licensing during development. Add the Freemius SDK via their dashboard (don't hand-vendor it).

## Coding standards & quality floor
- WordPress Coding Standards (PHPCS `WordPress` ruleset), PHP 8.1 typing.
- Security: verify nonces + `current_user_can()` on every write/REST route; sanitize input, escape output, `$wpdb->prepare()` for all queries.
- i18n: wrap all strings with the text domain; load translations on init.
- **Accessible admin UI** (dogfood): WCAG 2.2 AA, full keyboard operability, visible focus, `prefers-reduced-motion` respected, semantic markup, ARIA only where needed.
- Performance: batch heavy work through Action Scheduler; never block admin requests on AI calls.
- Guarantee no premium code ships in the free zip (rely on Freemius stripping + a build check).

## Getting started for Claude Code (Phase 1 build order)
1. **Scaffold** the plugin: headers, constants, PSR-4 autoload, activation/deactivation, `uninstall.php`, text domain; wire the **Freemius SDK** with `free`/`pro` plans and a 14-day no-card trial.
2. **DB schema** (`wp_wsak_scans`, `wp_wsak_issues`) via `dbDelta`; repositories.
3. **PHP DOM scanner** with the MVP rule registry; REST `POST /wsak/v1/scan` for a single page; store issues with `auto|manual` tags.
4. **Admin dashboard** (React): trigger a scan, list issues with honesty tags, per-page drill-down, score, coverage-transparency panel.
5. **AI provider abstraction + BYOK settings** (encrypted key storage); **alt-text generation** (single + capped bulk) with the Free cap enforced.
6. **One-at-a-time AI fix** with preview/diff/apply/undo, stored in the **override layer** (non-destructive).
7. **Accessibility-statement generator** (Gutenberg block + settings) with the EAA feedback mechanism.
8. **"Assist, not guarantee" disclaimers** on all conformance/report surfaces; confirm the admin UI passes its own WCAG checks.
9. **QA gate:** PHPCS clean, i18n complete, security review (nonces/caps/escaping), free-zip contains no premium code.

---

## Phase 2, step 1 — CSS-aware scanning *(the contrast gap)*

**Goal:** the scanner sees computed style, so colour contrast and the other
render-dependent criteria stop being invisible — without giving up unattended
server-side scanning, and without shipping one byte of JavaScript to visitors.

**Why this first:** it is the single gap a reviewer uses to dismiss the plugin.
Contrast is the most common real-world accessibility failure and we currently
detect none of it. Every other gap against the incumbent is a matter of degree;
this one is a matter of kind.

### Decisions

**D1 — Write our own browser pass. Do not bundle axe-core.** *(reversed
2026-08-30, on an explicit "best and risk-free" instruction.)*

The original decision was to bundle axe-core and run a scoped subset. That trade
does not survive contact with the scope we actually chose. We run **five**
checks. axe ships around a hundred, at roughly 600 KB, under a licence that
would be the only third-party legal question anywhere in this plugin — and the
only thing standing between us and a WordPress.org submission that requires no
legal judgement call at all.

For the record, the licence itself is almost certainly fine: axe-core is plain
MPL-2.0 with the Exhibit B "Incompatible With Secondary Licenses" notice **not**
asserted, verified against the shipped v4.11.1 headers and the upstream
`LICENSE`, neither of which contains the phrase; MPL-2.0 is GPL-compatible via
§ 3.3; and the incumbent already ships it inside a GPL plugin on wordpress.org.
But "almost certainly fine, pending a legal read" is a different thing from
risk-free, and there is an open upstream question about whether the SPDX
identifier should be `MPL-2.0-no-copyleft-exception`. Carrying that tail risk to
gain breadth we deliberately are not shipping is a bad bargain.

Three further reasons the reversal is not merely defensive:

1. **The maths is not the hard part, and we already own it.** The WCAG relative
   luminance and contrast-ratio formulae are ~30 lines and already implemented
   and validated in `bin/check-contrast.js`, where they check 42 pairings of our
   own UI on every CI run.
2. **Uncertainty is our product.** The genuinely hard part of contrast is the
   cases where the effective background cannot be determined — image
   backgrounds, gradients, stacked translucency. axe marks those "incomplete",
   and most tools built on it quietly drop them. We report them as **needs
   manual review**, which is the honest answer and the thing this plugin exists
   to do. Owning the checker is what makes that possible.
3. **It is the competitor's engine.** Shipping the same 600 KB of axe makes
   "architecturally different" a harder claim to make and an easier one to
   dismiss.

*Accepted cost, stated plainly.* Our checker will be less battle-tested than
axe on exotic CSS. The mitigation is the honesty fallback in point 2: where we
cannot determine a background with confidence we say so rather than guessing,
so the failure mode is an unnecessary manual review, never a false pass. Breadth
beyond the five checks is a later decision, not a licence we have to take now.

**D2 — Admin-only, in a hidden same-origin iframe. Never the front end.**
The scanner script is enqueued on our admin screen and nowhere else. This is a
hard product rule, not a preference: the incumbent enqueues a fix bundle for
every visitor, and "we ship nothing to your visitors" is a claim we only keep by
never making an exception. A build guard asserts no scanner or fix script is
registered on a front-end hook.

**D3 — Two engines, one issue model.**
`wp_wsak_issues` gains `engine` (`php` | `css`). The rule registry declares an
engine per rule so the coverage panel can list both kinds. Findings merge on
`(rule_id, selector)`; the CSS pass may add findings but never overrides or
silently removes a PHP finding.

**D4 — A scan without the CSS pass is visibly partial. Non-negotiable.**
This is where the honesty rule bites hardest. If the iframe is blocked and we
report "nothing found", we have told the user their page is clean when we did
not look — precisely the failure this product exists to avoid. Therefore:

- Every scan records `css_pass` as `ran`, `blocked`, or `skipped`, with a reason.
- A PHP-only scan **cannot present as complete**. The score is annotated as
  partial, and the results screen says which checks did not run and why.
- The coverage panel gains a third group: *Needs the browser pass*.
- `bin/check-disclosures.php` gains an entry for the partial-coverage wording, so
  it cannot be dropped in a refactor.

**D5 — The async boundary.**
The server pass always runs and stays queueable, WP-CLI-drivable and cron-safe.
The CSS pass runs only where a browser is present. Scheduled and bulk scans are
therefore PHP-only **and say so** on every surface that displays them. This is a
real limitation, stated plainly, not hidden.

**D6 — Contrast is Free.** The incumbent gives it away; gating it would make our
free tier visibly worse than theirs on the most-searched check in the category.

### The inspector *(supersedes the hidden-iframe design)*

The browser pass needs the page open in a frame. The original plan hid that
frame, which made it pure cost — infrastructure the user pays for in complexity
and never sees. Instead the frame becomes the primary interface:

**Issues on the left, the live page on the right.** Selecting or focusing an
issue highlights the element it refers to, inside the preview. The same frame
runs the browser pass. One piece of machinery, two jobs.

This also fixes the weakest part of the existing results screen. A finding
currently ends at a selector like `/html/body/div[3]/p[2]`, which is unusable by
anyone who does not read XPath for a living — the difference between a report
and a tool is being shown the thing.

And it turns the honesty surface from words into evidence. A blocked frame is
currently a notice people skim; an empty preview pane carrying the reason is
impossible to miss.

**Four problems this design has to solve, all verified against the code.**

1. **The admin bar shifts every selector.** `PageSource` fetches logged out, so
   the scanned HTML has no admin bar. An iframe loading the same permalink while
   the user is logged in gets `<div id="wpadminbar">` as the body's first child,
   and every positional XPath after it is off by one — so every highlight would
   point confidently at the wrong element. The preview therefore loads with the
   admin bar suppressed, so the two DOMs agree.
2. **Resolving is not the same as resolving correctly.** JavaScript can reshape
   the page before we look, so a selector can resolve to a different element than
   the one scanned. Findings already store the offending markup in `context`; the
   preview verifies the resolved element against it and reports *could not locate
   this on the live page* rather than highlighting the wrong thing. Highlighting
   the wrong element is worse than highlighting nothing, because it teaches the
   user to distrust every highlight.
3. **Hover alone is a mouse-only affordance.** For this product that is
   indefensible. Highlighting fires on focus as well as hover, issues are real
   buttons in the tab order, and nothing is reachable only by pointer.
4. **wp-admin is narrow.** The two panes stack below a breakpoint, and the
   preview can be widened.

**Non-negotiable, restated because the frame is now visible:** the preview and
its scanner run in the admin only. Nothing is enqueued for visitors, ever.

### Data model *(built — schema 1.2.0)*

- `wp_wsak_issues`: `found_by varchar(10) NOT NULL DEFAULT 'server'`, indexed.
  Values `server` | `browser`, modelled as `Scanner\ScanPass`.
- `wp_wsak_scans`: `browser_pass varchar(20) NOT NULL DEFAULT 'skipped'`. Values
  `ran` | `blocked` | `skipped`, modelled as `Scanner\BrowserPassStatus`; the
  reason travels in the existing summary JSON.
- `Schema::VERSION` 1.1.0 → 1.2.0; `Installer::maybe_upgrade()` migrates on
  `admin_init` via `dbDelta`.

**Naming, changed during implementation.** The plan said `engine` with values
`php`/`css`. Both were wrong in practice. `wp_wsak_fixes` already has an `engine`
column meaning *which AI path produced this fix*, so a join between issues and
fixes would have carried two `engine` columns meaning different things. And
`php`/`css` names our implementation rather than what the user is told —
the surfaces say "found in the markup" and "found in the rendered page", so the
stored values say `server` and `browser` to match. Defaults are chosen to be
*true* of existing rows, not merely safe: every finding already in the table was
produced by the server pass, and no scan already recorded ever had a browser
pass. Verified against the live database — 36 existing findings and 10 existing
scans migrated with accurate values.

### Build order

1. Schema migration, `Engine` enum, repository plumbing, `SchemaTest` coverage.
2. Rule registry declares an engine per rule; `/coverage` payload and the
   coverage panel grow the third group.
3. Build the browser runner in `assets/src/scanner/` — no third-party runtime.
   Admin-only enqueue, loaded on demand rather than on every admin page.
4. Iframe harness: preview URL for non-public statuses, hard timeout, and an
   explicit result for every failure mode rather than a silent empty array.
5. `POST /wsak/v1/scans/{id}/css` — capability + nonce, rule IDs validated
   against the registry so the route cannot be used to write arbitrary findings.
6. Merge and dedupe; persist with `engine = 'css'`.
7. Partial-coverage surfaces: score annotation, results notice, coverage panel.
8. Each browser check reports our own rule ID, WCAG SC, severity and
   remediation copy, and reports *uncertain* rather than *pass* whenever it
   cannot determine the answer.
9. Tests: merge/dedupe, blocked-iframe degradation, score honesty, and a guard
   that no scanner script is enqueued for visitors.

### Risks

| Risk | Handling |
| --- | --- |
| Our contrast checker is less battle-tested than axe on exotic CSS | Where the effective background cannot be determined, report *needs manual review* rather than guessing. The failure mode is an unnecessary review, never a false pass. |
| Iframe blocked by `X-Frame-Options` / CSP | Detected and reported as `blocked` with a plain-language reason. Degrades to a partial scan, never a false clean one. |
| wp.org review of a bundled third-party library | No longer applicable: the browser pass ships no third-party runtime code. |
| Admin bar or logged-in styles contaminating results | Scan the preview URL in a logged-out-equivalent context; verify against a known page before trusting output. |
| Duplicate findings across engines | Scope the axe ruleset to what PHP cannot do; assert non-overlap in a test. |

### Deliberately deferred to step 2

Cheap parity wins, worth doing straight after and not worth delaying step 1 for:
dismissal with a recorded reason (user, date, reason, comment — we have no
equivalent and it strengthens attestation), `affected_disabilities` on every rule,
and the richer per-rule metadata the incumbent carries (`why_it_matters`,
`references[]`).

## Phase 2, step 2 — remediating what CSS causes

**Goal:** the findings the browser pass made possible become fixable, not just
visible.

**Why now:** contrast, target size and link-marked-by-colour are the most
valuable checks the plugin has, and every one of them currently ends in a
sentence telling the user to go and edit their theme. The markup override layer
cannot help: it substitutes markup, and none of these are markup faults. Left
alone, the browser pass is a better *reporter* and no better a *fixer*, which is
the wrong half of the product to have improved.

### Decisions

**E1 — Write to WordPress's own Additional CSS.**
Approved rules go into the Customiser's Additional CSS (`wp_update_custom_css_post()`),
not into a stylesheet of ours. Three reasons, in order of weight:

1. **It keeps the "nothing for visitors" line intact.** A stylesheet we enqueue
   ourselves would be this plugin outputting to the front end — the line the
   incumbent crosses and we have refused to. Additional CSS is the site's own
   CSS, which the site was already serving.
2. **It is somewhere the user already owns and can see.** Appearance →
   Customise → Additional CSS. They can read it, edit it, or delete it without
   us, including after uninstalling the plugin. A fix that only our plugin can
   undo is a fix that holds the site hostage.
3. **It is what a developer would actually do.** Which is the whole positioning:
   real changes at the code level, not a runtime patch.

**E2 — Write only inside a delimited block, and never outside it.**
Our rules live between marker comments. On every write the existing CSS is
parsed, our block replaced, and everything else preserved byte for byte. If the
markers are missing or malformed, we append a fresh block rather than guessing —
we would rather leave a duplicate for a human to reconcile than eat somebody's
stylesheet. The user's own CSS is never reformatted, reordered or touched.

**E3 — Require the capability the change actually needs.**
Applying needs `wsak_apply_fix` **and** core's `edit_css`. Our own capability is
not enough on its own: `edit_css` is what gates the Customiser, and this must
never become a way for someone to change site-wide CSS who could not already do
it by hand. Capabilities are a floor, not a shortcut.

**E4 — The selector is part of the fix, and part of the review.**
A markup override affects one element in one post. A CSS rule affects everything
the selector matches, site-wide, forever. That is a categorically larger blast
radius and the interface has to treat it as one.

So the proposed selector is shown, editable, and explained: how many elements on
this page it currently matches, and that it will also apply to pages we have not
scanned. Selector preference, most stable first: an `id`; then a class
combination that is not obviously generated; then, only as a last resort, a
positional selector — which is offered with a warning that it will break the
next time the content around it changes.

Often the right rule is broader than the element that triggered it. One
paragraph failing contrast usually means a colour is wrong everywhere it is
used, and fixing `.entry-content a` beats fixing one link. The proposal should
lean towards the class rather than the instance, and say which it chose.

**E5 — Close the loop in the frame.**
The preview frame is already open and the checks already run there, so an
applied fix can be re-measured immediately: apply, re-run contrast on that
element, show the new ratio next to the old one. Most tools tell you what to
change; this can show you it worked, in the page, seconds later. This is the
most persuasive thing in the feature and it costs almost nothing, because both
halves already exist.

**E6 — Undo removes the rule, not the block.**
Reverting a single fix removes its rule and leaves the rest of the managed block
intact, exactly as reverting one markup override leaves the others.

**E7 — Say that it is theme-scoped.**
WordPress stores Additional CSS per theme. Switching themes silently leaves the
fixes behind — they are not lost, but they stop applying. That is core's
behaviour and not something to hide: the statement and the fix list both say so,
and switching themes is a good moment to re-scan.

**E8 — Free to fix one page, Pro to fix a site.** *(Resolved 2026-08-30.)*
Module C listed contrast fixes as `[Pro]`, written before contrast detection
existed. That is now the wrong line. Detection is table stakes — the free
competitor has it — so a free tier that finds contrast problems and then refuses
to help with any of them is worse than the thing it is competing against.

Fixing, meanwhile, has no free equivalent anywhere, which makes it the thing
worth paying for rather than the thing worth withholding. So: applying CSS fixes
one at a time on a page is **Free**, and bulk application across a site is
**Pro** — the same shape as the markup fixes, and the same shape as D6. Somebody
evaluating the plugin can fix a real problem on a real page before paying, which
is the only convincing demonstration this product has.

### Build order

1. ✅ `Remediation\CustomCss` — read, parse the managed block, write it back with
   everything else preserved. Tests first, including malformed and missing
   markers, and CSS containing our marker text inside a string.
2. ✅ Selector proposal with the stability ranking, and a match count from the
   live frame. `assets/src/scanner/selector.js`.
3. ✅ `POST /wsak/v1/fixes/css` — capability pair, validated rule, rejects any
   rule whose finding did not come from the browser pass. `Rest\CssFixController`
   with `Remediation\CssFixManager` and the `Remediation\CssRules` allowlist.
4. ✅ Contrast fix generation: propose the closest colour to the original that
   meets the ratio rather than a stock dark one, so the design survives the fix.
   `colour.js::nearestAccessible` moves lightness only and keeps hue and
   saturation. At the AA thresholds this always has an answer — lightness 0 and
   1 are black and white whatever the hue, and the worst background in the
   colour space still leaves one of them at 4.58:1.
5. ✅ Review UI: the rule, the selector, its blast radius, the before and after
   swatches, and the measured ratio each way. `components/css-fix-action.js`.
6. ✅ Apply, then reload the frame, re-measure, and report honestly when a more
   specific rule in the theme beat ours.
7. ✅ Uninstall leaves the CSS in place unless data removal was opted into — it is
   the user's stylesheet now.

Three of the five browser-pass rules have a CSS answer: contrast, links marked
by colour alone, and undersized targets. The other two — a scrolling region with
nothing focusable in it, and an `aria-hidden` element still in the tab order —
need an attribute added to the markup, which no stylesheet can do. Those say so
in their own words rather than sharing a generic "not supported" message, because
a reader told a fix is coming waits for it and a reader told to edit their
template goes and does it.

### Risks

| Risk | Handling |
| --- | --- |
| Damaging existing Additional CSS | Only ever rewrite between our markers; preserve the remainder byte for byte; append rather than guess when markers are malformed. Tested against hostile input. |
| A selector matching far more than intended | Show the match count before applying, prefer the narrowest stable selector, and make the selector editable. |
| Specificity losing to the theme | Verify by re-measuring after applying, and report honestly when the rule did not take effect rather than claiming success. |
| Fixes silently stopping on a theme switch | Stated in the interface; a theme switch prompts a re-scan. |

## Phase 3 — from one page to a whole site *(and from "found" to "fixed")*

**Goal:** the plugin stops being a thing you point at one page at a time. Bulk
scanning, bulk alt text, checks inside the editor, and fixes that write to the
content rather than filtering it on the way out.

**Why now:** everything in Phases 1 and 2 improved what happens on *one* page.
A real site has hundreds. The plugin currently has no queue, no bulk anything,
no editor integration, and one entry point — pick a page, scan it, read a list
of WCAG success criteria. That serves a developer auditing a page. It does not
serve the person who runs the site, which is who most of them are.

### Decisions

**F1 — The free tier fixes a page. The paid tier fixes a site.**

The line is scale and repetition, not capability. Everything about *one* page is
free, including the inspector; bulk, scheduling and history are paid.

The tempting alternative was to put the in-editor inspector behind Pro, since it
is the most impressive thing here. That is the wrong wall, for the reason
already recorded in E8: detection is table stakes, and the free competitor has
it. A free tier that scans a page and then will not show you where the problem
is would be weaker than the free plugin we are competing against, at the thing
that category is known for.

It is also our best acquisition surface. The inspector lives where the user
already works, it creates the habit, and it is where "oh, *that* is what is
wrong" happens. Charging for it means nobody meets the product's best idea
before paying.

So: **one page, everything. Many pages, and keeping them that way, is Pro.**
It states in one sentence, it needs nothing crippled, and it maps cleanly onto
what actually costs us something to run.

**F2 — Bulk work goes through Action Scheduler, or it does not ship.**

Action Scheduler is currently a dependency in name only: it is named in
CLAUDE.md and absent from `composer.json`. Every scan today runs synchronously
inside one admin request.

Ten pages is ten loopback HTTP fetches in a single request, and bulk alt text is
worse — every image is a multi-second call to a vision model. On shared hosting
that times out part-way through, leaving a half-finished scan, no record of
where it stopped, and no way to resume. This is the largest piece of work in the
phase, it is invisible in the interface, and everything else depends on it, so
it goes first.

The queue owes the interface four things, all of which have to be real rather
than animated: progress, resume after a timeout, cancel, and a visible account
of anything that failed.

**F3 — "Auto fix" means deterministic. Anything a model wrote gets reviewed.**

The instinct behind "user clicks auto fix and the plugin fixes it" is right, and
it collides with product rule #3 only because one phrase is covering two
different kinds of change:

| | Deterministic | Generative |
| --- | --- | --- |
| Examples | `lang` on `<html>`, a missing `<title>`, `tabindex="0"` on a scroll region, an underline on a link, a 24px minimum target | alt text, button names, link text |
| Produced by | a rule; there is one right answer | a model; it is a guess |
| Review before applying | not needed | always |

*Corrected while implementing, 2026-09-01.* This decision originally said
"roughly half of the current checks have a deterministic answer". Classifying
all seventeen says otherwise, and the gap matters enough to record rather than
quietly fix.

Two questions were being conflated. **Do we know the right answer?** and **can we
write it where the fault is?** A one-click fix needs both, and several rules have
the first without the second: `lang="en"` has exactly one correct value and lives
on an `<html>` element printed by the theme, which no filter reaches. Knowing the
answer is not the same as being able to apply it.

The honest tally, pinned in `FixPlanTest` so it cannot drift upward one
reasonable-looking rule at a time:

| | count | |
| --- | --- | --- |
| **One click** | 4 | colour, link underline, target size — all CSS and already shipped — plus a missing `<title>`, which WordPress writes itself once a plugin declares `title-tag` support on the theme's behalf |
| **Draft, then review** | 6 | alt text, and the five other checks whose fix is a piece of writing |
| **Hand off to the theme** | 4 | a known answer, or an obvious one, in a file nothing here reaches |
| **Only the owner can settle it** | 3 | which cells are headers, which heading should be demoted |

So the one-click story is thin on the markup pass and rich on the browser pass,
which is the opposite of what the original wording implied. It does not change
the design — the split between computed and generated is exactly as necessary as
it was — but it does change what the interface can promise, and it means the
value of "fix automatically" rests on the CSS work already done rather than on
markup fixes still to come.

The generative half can still be *bulk* without being *unreviewed*: generate for
forty items in the background, then show one **review queue** — a grid of
suggestions, editable, approve all or amend individually. One screen and one
click for the user, and a person still read every string. It is also a far
better interface than forty modals.

Each rule therefore declares which kind it is. A rule with no deterministic fix
never grows an auto-fix button, and no amount of interface work can give it one.

**F4 — A bulk scan is a partial scan, and the badge has to say which.**

The browser pass needs a rendered page in a frame. It cannot run in a background
job, so anything scanned in bulk is server-pass only: no contrast, no target
size, no colour-only links.

A badge reading "Scanned ✓ 12 Feb" after such a run would be a lie by omission.
Somebody bulk-scans, sees no contrast findings, and concludes they have none —
which is the exact failure this product exists not to commit.

So the badge has two states, and the difference is stated plainly:

- **Content checked** — the markup pass ran. Colour, size and layout were not
  looked at.
- **Fully checked** — somebody opened this page in the inspector and the browser
  pass ran too.

The honest version is also the useful one: it gives people a reason to open
pages individually, which is where the product is at its best.

*Amended by F9:* "fully checked" is reachable in bulk after all, through the
rendered strategy — the admin's own browser working through a queue of framed
pages. What stays true is that it cannot be reached **unattended**: a scheduled
scan has no browser, so scheduled runs earn "content checked" and nothing more.

**F5 — In the editor, check the block tree. Do not render a preview.**

A button in the editor is the right idea. An iframe of a preview URL is the
wrong way to serve it: unsaved drafts need a preview nonce, the request has to
go out and come back, and loopback is exactly what fails on the hosts that need
us most.

Inside the block editor the content is already in the browser, structured, in
`core/block-editor`. We can check it as the user types — no HTTP request, no
loopback, no preview URL — and point at the offending block.

That changes what "fix" means, and for the better. Setting `alt` on an image
block writes to the **actual post content**, not to an override layer. It is a
genuine source fix, it is instant, and it is undoable with the editor's own
undo, because it is just another editor change. This is the first place the
plugin does real remediation rather than reversible interception.

Classic editor and page builders still need the framed-preview route. Gutenberg
does not, and pretending otherwise would make the common case worse to serve the
rare one.

**F6 — Theme problems get three different answers, and one refusal.**

Most accessibility failures on a real WordPress site are not in the post
content. They are in the theme: navigation, header, footer, search form, social
icons. On our own proving ground, fourteen of nineteen findings were theme
navigation. A policy of "fix pages and posts only" is correct and, left there,
means finding three hundred problems and fixing twenty.

The refusal first, because it is the tempting shortcut. **We will not buffer the
front-end page and rewrite it.** `ob_start()` on `template_redirect`, parse,
patch, flush would reach everything — and it would put a DOM parse in front of
every visitor on every request, and turn the plugin into something that silently
rewrites what visitors receive at runtime. That is the accessiBe pattern with
better intentions, and rule #2 exists to prevent exactly it.

What we do instead, decided by what the problem actually is:

1. **It is a setting, not code.** A logo with no alt, a menu item with a useless
   label, a widget title. Deep-link to the exact screen with the exact
   instruction. Best outcome available: permanent, no code, and a non-technical
   user can do it unaided.
2. **A core filter genuinely reaches it.** `wp_nav_menu_items`,
   `get_search_form`, `post_thumbnail_html`, and their neighbours. Narrow,
   named, reversible — the same shape as today's content overrides. No general
   page rewriting hides in this tier.
3. **It is hardcoded in a template.** No filter exists and none should be
   invented. Hand it off: the corrected snippet, the template it lives in where
   we can tell, and an export for whoever maintains the theme.

Tier 1 is the one most likely to be underrated. For the audience this phase is
for, "go to Appearance → Menus and rename this item" beats any override we could
write.

**F7 — Alt text in the media library is a real fix, and the one bulk write that
is unambiguously safe.**

Writing `_wp_attachment_image_alt` is not an override and not an interception.
It sets the alt text on the attachment, so it applies everywhere that image is
used, on every page, under any theme, and it survives uninstalling this plugin.

That makes bulk alt text the highest-leverage thing in the product: it is
theme-independent, it is a genuine repair, and it is the one bulk operation
where applying at scale carries no risk of being wrong about *where* the change
lands. It still goes through the F3 review queue, because what the model wrote
is still a guess about the image.

**F8 — Plain language is a feature in this phase, not a copy pass at the end.**

Every item above is about *where* scanning happens. None of them is about
whether the result can be understood, which is the actual goal. Scanning in bulk
without this just delivers "Heading level is skipped · WCAG 1.3.1 · Moderate"
next to an XPath, faster and in larger quantities.

Three parts, and they are build work rather than wording:

- **A plain title and a plain consequence for every rule.** "Screen readers
  announce this image as `IMG_4021.jpg`" earns its place; "img-alt-missing" does
  not. The success criterion stays, one level down, for the people who want it.
- **An order that means something.** Findings sorted by what to do first —
  cheapest real improvement at the top — rather than by severity enum. Severity
  answers "how bad"; it never answers "what now".
- **Visible progress.** How many pages are checked, how many findings closed,
  what changed since last time. Without it there is no way to feel that any of
  this is working.

This lands before the three new surfaces are built, not after. Bulk results, the
review queue and the editor panel all render findings, and changing how a
finding presents itself after the fact means rewriting three interfaces.

**F9 — Bulk scanning fetches differently, because fetching per page does not
survive contact with a real site.**

Today a scan is one loopback `wp_remote_get()` of the permalink, with a 20-second
timeout and a per-post fallback to post content. That is the right design for
one page and the wrong one for two hundred:

- **It fails per page instead of once.** Loopback is blocked on plenty of hosts,
  and the current code rediscovers that on every post. A hundred unreachable
  pages is roughly half an hour of the queue waiting for timeouts, to learn one
  fact that could have been established in five seconds.
- **It has no user.** A queued job runs as nobody, so a loopback request for a
  draft or a private post gets a 404. The single-page scan gets away with this
  because it runs inside an authenticated admin request; bulk does not.
- **It doubles the site's own load.** Every scanned page is a second full
  WordPress bootstrap and theme render, requested by the site, of itself.
- **It reports the same theme fault hundreds of times.** A missing `main`
  landmark is one problem in one template. Filed against four hundred posts it
  is four hundred findings, and the list becomes unreadable at exactly the
  moment it was supposed to become useful.

So bulk does not fetch pages. It uses three strategies, chosen by what is being
asked and what the host allows.

| | How it gets the markup | Needs | Sees | Used by |
| --- | --- | --- | --- | --- |
| **Content** | `the_content` filters, in process | nothing | the post's own content | every bulk scan, always |
| **Template** | loopback of a handful of representative URLs, once per theme version | loopback | the whole page, chrome included | site-level findings, and the profile below |
| **Rendered** | the admin's own browser, one framed page at a time | an open tab, and a framable site | the whole page **as painted** | deep scans, drafts, anything needing the browser pass |

**The content strategy is the spine.** It needs no HTTP, so it works on every
host, in cron, and for drafts and private posts — none of which loopback manages.
It is also fast enough that scanning is no longer the expensive part of a bulk
run.

**The template strategy runs a handful of times, not once per post.** One
representative URL per public post type, plus the front page and one archive.
Roughly six requests for a whole site rather than one per post. What it finds —
a missing `lang`, no `title`, no `main` landmark, an unlabelled search form in
the header — is filed **once, against the theme**, not against every post that
happens to use it. That is both far less noise and a truer description of the
problem: it is one fault, in one template, and fixing it fixes every page.

**The template profile is why this is accurate rather than merely cheap.**

*Corrected while implementing, 2026-09-01.* This decision originally claimed that
scanning content alone would produce false **positives** on heading structure.
Reading the two rules says otherwise: `HeadingLevelSkipped` guards its first
heading with `0 !== $previous`, and `MultipleTopHeadings` only reports from the
second `h1` onwards. Neither invents a finding when the theme's heading is out of
sight.

What they do instead is miss real ones, which for these two is the more likely
failure and the harder one to notice. A post whose content runs `h1` under a
theme that already printed one is a genuine second top-level heading, and
content alone counts one and says nothing. A post starting at `<h3>` under a
theme's `<h1>` is a genuine skipped level, and content alone treats `h3` as the
beginning and says nothing. Bulk scanning without the profile would therefore be
quietly *less* thorough on exactly the well-built themes where the remaining
faults are subtle.

So the template pass records the few facts the content pass is missing — the
heading level the theme establishes before the content, whether the page already
has a `main`, a `title`, a `lang` — and the content pass is seeded with them. A
missing profile means those rules behave as they do today rather than guessing:
under-reporting is recoverable, and inventing findings is not.

**Attribution is by subtraction, and the code for it exists.**
`Substitution::locatable()` already answers "does this recorded markup appear in
this post's content", which is how applied fixes decide whether an override can
reach them. The same test separates a finding that belongs to the content from
one that belongs to the template.

**Probe once, and say so.** Before a bulk run, one request to the front page with
a short timeout establishes whether loopback works at all. The answer is cached,
re-testable on demand, and shown to the user *before* they start — with what it
means for coverage, not as an error. A host that blocks loopback loses the
template pass; it does not lose bulk scanning.

**The rendered strategy is how bulk gets the browser pass.** The admin page
queues framed pages one at a time, reusing `Preview::url_for()` and the existing
`runBrowserPass()`. Because it is the administrator's own browser it carries
their cookies, so drafts and private posts work with no token scheme and no
authentication bypass to design. Findings are recorded server-side as they
arrive, so closing the tab loses the remaining queue and nothing already found.
It cannot be scheduled — a cron job has no browser — which is a real limit and
is stated rather than worked around.

**Rejected: rendering the template in-process.** Simulating the main query and
running WordPress's template loader inside the same request would get a full page
with no HTTP at all. It also means firing `wp_head` and `wp_footer` inside an
admin or cron request, with every plugin's side effects attached, while
clobbering `$wp_query` for whatever else that request was doing — and some themes
call `exit`. It would work on the machines we test and break on somebody's site
in a way we could not reproduce. Not worth it when the content strategy already
covers the common case honestly.

**Rejected: collecting markup from real visitors.** Cheap, complete, and always
current, because the site renders those pages anyway. It also means shipping code
to the front end, which is the one thing this plugin does not do.

**F10 — Action Scheduler 4.x, and the WordPress floor moves to 6.8 to get it.**

The queue is Action Scheduler, as CLAUDE.md has said since the start. What was
not anticipated is that taking it costs a supported WordPress version.

Action Scheduler 4.0.0 raised its own requirement to **WordPress 6.8**. The 3.9
line still supports 6.4 and would have let the floor stay at 6.6 — but 4.1.0
carries a fix described as *"protections to guard against the risk of
object-injection/deserialization attacks when retrieving stored schedule data"*,
and that hardening was never backported. There is no 3.9.4.

So the choice is between an older WordPress floor and shipping a queue without
a security fix its authors thought worth making. For a plugin whose whole
position is being the trustworthy option in a category with a bad reputation,
that is not a close call. **Take 4.1.0, move the floor to 6.8.**

WordPress 6.8 shipped in April 2025, seventeen months before this decision. This
plugin already declares itself first-class on 7.0. Holding a two-year-old floor
in order to avoid a security fix would be the wrong trade in both directions at
once.

*Licence, for the record:* Action Scheduler is GPLv3-or-later and this plugin is
GPLv2-or-later. Those are compatible in this direction — "or later" permits
distribution of the combined work under v3 — which is exactly what WooCommerce
and every other plugin bundling it relies on. Our own source stays GPLv2+; the
shipped zip, taken as a whole, is GPLv3. Noted in the release checklist so
nobody has to re-derive it.

*Loading, for the record:* Action Scheduler is a plugin wearing a library's
clothes. It is `require_once`d at file scope rather than autoloaded, because
Composer's autoloader would never reach it — nothing in our code names one of
its classes — and because several plugins on one site may each bundle a copy.
They all register, and Action Scheduler decides which version runs, during
`plugins_loaded`. Registering any later than file scope means not being
considered at all.

### Build order

1. **Action Scheduler.** Add the dependency, model a job, and give the interface
   progress, resume, cancel and a failure account. Nothing bulk exists until
   this does. *(F2)*
2. **The fetch strategies.** Split `PageSource` into content, template and
   rendered; add the loopback probe with its cached verdict; scan representative
   URLs once per theme version and record the template profile the content pass
   is seeded with; attribute findings to content or template with
   `Substitution::locatable()`. Bulk scanning is built on this, so it comes
   before it. *(F9)*
3. **Fix classification on the rule.** Every rule declares deterministic,
   generative, or hand-off, and deterministic rules carry their fix. This is a
   change to the rule model and it gates every auto-fix button in the phase.
   *(F3)*
4. **The plain-language layer.** Titles, consequences, do-this-first ordering,
   progress. Before three new surfaces start rendering findings. *(F8)*
5. **Bulk scan.** Post-type tabs, a selection list with a "first 10" shortcut,
   queued execution, the two-state badge, results grouped per page, and theme
   findings in their own list rather than repeated against every post. Badges are
   invalidated when a post is saved. *(F4, F9)*
6. **Fix actions in bulk results.** One-click for deterministic; review queue for
   generative; mark as false positive with a note — `IssueStatus::Ignored`
   already exists and needs the interface and the audit trail. *(F3)*
7. **Bulk alt text.** Selection, queue, cost estimate before the run, review
   queue, write to the media library. *(F7)*
8. **Editor integration.** Block-tree checks as the user types; fixes written to
   block attributes; framed preview only for the editors that need it. Can run in
   parallel with 5–7 once 4 is done. *(F5)*
9. **The rendered queue.** The admin browser working through framed pages one at
   a time, so "fully checked" is reachable in bulk. Reuses `Preview::url_for()`
   and `runBrowserPass()`. *(F9)*
10. **Theme triage.** The three tiers: setting deep-links, the named filters, the
   developer hand-off and export. *(F6)*
11. **Tier gating.** Apply F1 across the new surfaces, extend the free-build
    check to cover them.

### Risks

| Risk | Handling |
| --- | --- |
| A bulk run times out and leaves the site in an unknown state | The queue owns resumption; a scan records where it stopped, and the interface says so rather than showing a stalled bar. |
| Bulk alt text runs up somebody's provider bill | Estimate the cost of the selected run before it starts, and keep the daily cap enforced in the generation path rather than the interface. |
| Users read "Scanned" as "fully checked" | Two badge states, worded as coverage rather than completion. *(F4)* |
| A host blocks loopback and bulk scanning looks broken | Probe once before the run, cache the verdict, and say what coverage the site will get before it starts. The content strategy needs no HTTP, so bulk still works — it sees less, and says so. *(F9)* |
| The template profile goes stale after a theme update | Keyed to theme and version, invalidated on `switch_theme` and on plugin/theme updates, re-runnable by hand, and expiring on its own. A missing profile means the heading rules abstain rather than guess. *(F9)* |
| Auto-fix applied at scale turns out to be wrong | Only deterministic fixes are ever applied unreviewed, and every applied fix stays revertible. |
| Theme findings dominate the results and nothing can be fixed | The three-tier triage, and honest counts: how many are ours to fix, how many are settings, how many need a developer. |
| Block-attribute writes corrupt a post | They go through the editor's own store, so they are ordinary undoable edits and are never written from a background job. |


## Open decisions (updated)
1. **Scanning engine** — ✅ *Resolved, specified and built.* PHP DOM static analysis is the spine; a browser pass we wrote ourselves adds the render-dependent checks. axe-core was considered and **rejected** — see Phase 2 step 1, decision D1 — so the licence sign-off that used to hang off this decision no longer exists.
2. **Fix storage model** — ✅ *Resolved.* Three layers, each chosen by what the problem is rather than by preference: the reversible **override layer** for markup in post content; the site's own **Additional CSS** for anything caused by styling (E1); and **write-to-source** in the two places it is safe and honest — block attributes edited in the editor, where the user is present and the editor's own undo applies (F5), and `_wp_attachment_image_alt` in the media library, which is a real repair rather than an interception (F7). Whole-page output rewriting is refused outright (F6).
3. **Monetization / licensing** — ✅ *Resolved:* **Freemius** now (Free on WordPress.org, Pro via Freemius). AI stays **BYOK** (cost ≈ 0, and it keeps you out of AI-data-processor liability). Managed AI credits deferred — could be sold later as a Freemius credit pack/add-on if BYOK friction hurts conversion.
4. **Free alt-text cap** — ✅ *Resolved and built:* **20 images per day** on Free, enforced in the generation path through one filterable constant rather than in the interface. Pro is uncapped, with the bulk queue and review screen in Phase 3.
5. **Agency tier** — ✅ *Deferred* to Phase 4 as a third Freemius plan.
6. **Tier boundary** — ✅ *Resolved 2026-08-31:* the free tier fixes a page, the paid tier fixes a site and keeps it fixed. Full reasoning in Phase 3, decision F1, including why the in-editor inspector stays free.
