# WOWStudio Accessibility Kit — Product & Build Spec
### Accessibility Remediation + Conformance, with AI Alt-Text & Media SEO
*Publisher: WOWStudio · Status: ready for development · Monetization: Freemius (Free + Pro)*

> This file doubles as the Claude Code project brief. Drop it in the repo root as `SPEC.md` (or reference it from `CLAUDE.md`). Build in the order given under **Getting started for Claude Code**.

**Positioning (one line):** A real-remediation accessibility plugin that *finds, fixes, documents, and monitors* WCAG issues at the code level — explicitly **not** an overlay, and it never claims to "make you compliant."

### Plugin metadata
| Field | Value |
|---|---|
| Plugin name | WOWStudio Accessibility Kit |
| WordPress.org slug | `wowstudio-accessibility-kit` |
| Text domain | `wowstudio-accessibility-kit` |
| Namespace / prefix | `WOWStudio\AccessibilityKit` · `wsak_` · DB tables `wp_wsak_*` |
| Requires WP | 6.6+ (first-class support for WP 7.0 AI Client / Abilities API) |
| Requires PHP | 8.1+ (min declared 8.0) |
| License | GPLv2 or later (WordPress.org requirement) |
| Monetization | **Freemius** — Free on WordPress.org, Pro via Freemius checkout |

**Tiers = Freemius plans:** `[Free]` = funnel (WordPress.org) · `[Pro]` = paid Freemius plan · `[Agency]` = future 3rd Freemius plan (multisite / white-label). Where a whole module is one tier it's noted at the header; per-feature tags override.

---

## Guiding product rules (non-negotiable)
- **Assist, never guarantee.** No feature, label, or output says "compliant," "ADA/EAA-compliant," "lawsuit-proof," or "100%." Language is always "helps you find / fix / document / monitor." (The accessiBe/FTC lesson — it's existential.)
- **Human-in-the-loop by default.** Every automated fix is a reviewable *suggestion*; nothing irreversible happens silently.
- **Honesty about coverage.** The UI always distinguishes machine-detectable issues from those needing human review, and states that automated testing covers only part of WCAG.
- **Not an overlay.** No front-end accessibility widget/toolbar is injected. Fixes are real code/markup changes.
- **Draft, attested documents.** Conformance reports are generated as *drafts the site owner reviews and attests to* — the plugin never asserts conformance on the user's behalf.
- **The plugin dogfoods.** Its own admin UI must pass WCAG 2.2 AA.

---

## Monetization & licensing — Freemius

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

## C. AI Remediation Engine *(the differentiator)*
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
  - `[Pro]` Contrast fixes with compliant color proposals (preview swatches).
  - `[Pro]` Skip-links / keyboard-focus fixes.
- `[Free]` **Preview + diff** for every fix; apply / reject; full undo.
- `[Pro]` Non-destructive apply: fixes stored as overrides/filters where possible; optional "write to source" mode with backup.
- `[Pro]` Auto re-scan after applying to confirm resolution.
- `[Free]` "Explain this issue" AI tutor — why it fails, who it affects, how to fix.
- `[Pro]` Remediation changelog / rollback history.

## D. AI Alt-Text & Media SEO *(free on-ramp + Pro depth)*
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

## F. Monitoring & Alerts *(recurring-revenue core — `[Pro]`)*
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

## H. Agency / Multisite *(`[Agency]`)*
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

## Tier boundary (mapped to Freemius plans)

**Free plan (WordPress.org, the funnel):** AI alt-text (BYOK, capped), single-page WCAG 2.2 scan, in-editor checks, basic dashboard & score, accessibility-statement generator, manual checklists + contrast checker, one-at-a-time AI fixes (capped).

**Pro plan (Freemius):** full-site + scheduled + bulk scanning, full AI remediation engine, Media SEO, monitoring/alerts/trends, conformance report + evidence log, WooCommerce at scale, WP-CLI + Abilities API, priority support.

**Agency plan (Freemius, Phase 3):** multisite, white-label client reports, roles/permissions, cross-site management, unlimited sites.

---

## Build phasing (don't build it all at once)

**Phase 1 — MVP / launch (the wedge):** AI alt-text (Free) + single-page WCAG 2.2 scanner + issue list with auto/manual honesty tags + one-at-a-time AI fix + basic dashboard + accessibility-statement generator + Freemius Free/Pro scaffold. Ships the funnel and proves the AI-remediation angle.

**Phase 2 — monetize:** full-site & scheduled scanning, bulk AI remediation, Media SEO, monitoring + alerts, conformance-report draft + evidence log, client-side axe-core contrast pass.

**Phase 3 — expand & defend:** WooCommerce depth, agency/multisite + white-label, WP-CLI + Abilities API, simulators & focus visualizer, extra page-builder integrations.

---

## Explicitly NOT included (anti-features)
- ❌ No overlay / accessibility widget or toolbar on the front end.
- ❌ No "guaranteed compliance," "ADA/EAA compliant," or "lawsuit protection" claims anywhere.
- ❌ No silent, unreviewed auto-fixes to live content.
- ❌ No auto-published conformance report asserting a level without human attestation.
- ❌ No storing/transmitting user content to AI beyond what a fix requires, without disclosure.
- ❌ No premium code shipped in the free WordPress.org zip (enforced by Freemius stripping).

---

# ── For Claude Code: development ──

## Tech stack & requirements
- **WordPress** ≥ 6.6, with first-class support for **WP 7.0** (AI Client, Abilities API, Command Palette); graceful degradation below 7.0 via the BYOK adapter.
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
- **Contrast & CSS-dependent checks** can't be done reliably server-side → **Phase 2** optional **client-side axe-core** pass run in the admin against the live page. Do **not** bundle a headless browser. Fully specified below.
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

## Freemius integration details
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

## AI integration
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

**D1 — Bundle axe-core; run a deliberately scoped subset.**
Deque's axe is the engine auditors actually use, and re-implementing contrast
means owning gradients, background images, opacity stacking and pseudo-elements
forever. We run **only the rules our PHP engine cannot do**, so the two engines
never produce competing findings and the coverage story stays legible.

*Licensing.* axe-core is MPL-2.0. The Exhibit B "Incompatible With Secondary
Licenses" notice is **not** asserted — verified against the shipped v4.11.1
headers and the upstream `LICENSE`, neither of which contains the phrase. Plain
MPL-2.0 is GPL-compatible via § 3.3, so bundling it in a GPLv2+ plugin is sound,
and the incumbent already ships it inside a GPL plugin on wordpress.org. There
is an open upstream discussion about whether `package.json` should carry
`MPL-2.0-no-copyleft-exception` instead; if that were ever asserted the position
changes. **Get a human sign-off before release** — recorded in the release
checklist. Fallback if it goes the wrong way: our own contrast checker, roughly
5 KB, covering 1.4.3 and 1.4.11 only, at the cost of the rest of the pass.

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
3. Vendor axe-core through npm; build a scoped runner in `assets/src/scanner/`.
   Admin-only enqueue, loaded on demand rather than on every admin page.
4. Iframe harness: preview URL for non-public statuses, hard timeout, and an
   explicit result for every failure mode rather than a silent empty array.
5. `POST /wsak/v1/scans/{id}/css` — capability + nonce, rule IDs validated
   against the registry so the route cannot be used to write arbitrary findings.
6. Merge and dedupe; persist with `engine = 'css'`.
7. Partial-coverage surfaces: score annotation, results notice, coverage panel.
8. Map axe rule IDs to ours, with our own WCAG SC, severity and remediation
   copy. We do not pass axe's wording through to users — the voice and the
   honesty framing are ours.
9. Tests: merge/dedupe, blocked-iframe degradation, score honesty, and a guard
   that no scanner script is enqueued for visitors.

### Risks

| Risk | Handling |
| --- | --- |
| ~600 KB bundle | Admin-only, loaded on demand, never on every screen. |
| Iframe blocked by `X-Frame-Options` / CSP | Detected and reported as `blocked` with a plain-language reason. Degrades to a partial scan, never a false clean one. |
| wp.org review of a minified third-party library | Source available via npm and the build is reproducible from it; note it for the reviewer up front. |
| Admin bar or logged-in styles contaminating results | Scan the preview URL in a logged-out-equivalent context; verify against a known page before trusting output. |
| Duplicate findings across engines | Scope the axe ruleset to what PHP cannot do; assert non-overlap in a test. |

### Deliberately deferred to step 2

Cheap parity wins, worth doing straight after and not worth delaying step 1 for:
dismissal with a recorded reason (user, date, reason, comment — we have no
equivalent and it strengthens attestation), `affected_disabilities` on every rule,
and the richer per-rule metadata the incumbent carries (`why_it_matters`,
`references[]`).

## Open decisions (updated)
1. **Scanning engine** — ✅ *Resolved and specified.* PHP DOM static analysis is the spine; a scoped client-side axe-core pass adds the render-dependent checks in Phase 2, step 1. Full decision record above. The one open sub-question is the axe-core licence sign-off, tracked in the release checklist.
2. **Fix storage model** — 🟡 *Recommended:* override/filter layer first (safe, reversible); add optional write-to-source in Phase 2 with backups. *(confirm)*
3. **Monetization / licensing** — ✅ *Resolved:* **Freemius** now (Free on WordPress.org, Pro via Freemius). AI stays **BYOK** (cost ≈ 0, and it keeps you out of AI-data-processor liability). Managed AI credits deferred — could be sold later as a Freemius credit pack/add-on if BYOK friction hurts conversion.
4. **Free alt-text cap** — 🟡 *Recommended:* Free = BYOK alt-text, single image + a small daily/volume cap; Pro = uncapped + full-library bulk queue. *(confirm the exact Free cap number)*
5. **Agency tier** — ✅ *Deferred* to Phase 3 as a third Freemius plan.
