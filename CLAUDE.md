# CLAUDE.md — WOWStudio Accessibility Kit

Operating brief for Claude Code. **Read `SPEC.md` before implementing anything** — it is the source of truth for features, tiers, data model, and build order. This file is the short list of rules and context that always apply.

## What we're building
A **real-remediation** WordPress accessibility plugin that finds, fixes, documents, and monitors WCAG issues at the **code level**. Publisher: **WOWStudio**. Monetization: **Freemius** (Free on WordPress.org + Pro). Current phase: **Phase 1 (MVP)**.

Positioning: *"helps you find, fix, document, and monitor"* — never *"makes you compliant."*

## Non-negotiable product rules (never violate)
1. **Assist, never guarantee.** No string, label, doc, or marketing output may say "compliant," "ADA/EAA compliant," "lawsuit-proof," "certified," or "100%." Use "helps you find / fix / document / monitor." (This is legal-risk critical — the accessiBe/FTC precedent.)
2. **Not an overlay.** Never inject a front-end accessibility widget/toolbar. Fixes are real markup/code changes.
3. **Human-in-the-loop.** Every automated fix is a reviewable suggestion with preview + diff + undo. Nothing irreversible happens silently.
4. **Honesty about coverage.** Always tag issues `auto-detected` vs `needs manual review`; state that automation covers only part of WCAG.
5. **Draft, attested docs.** Conformance reports render as DRAFTs the user reviews and attests to — the plugin never asserts conformance itself.
6. **Dogfood.** The plugin's own admin UI must pass WCAG 2.2 AA.

See `SPEC.md` → "Explicitly NOT included (anti-features)" for the full stop-list.

## Tech stack
- WordPress ≥ 6.8, first-class on WP 7.0 (AI Client, Abilities API); graceful degradation below 7.0 via BYOK adapter. *(Raised from 6.6 on 2026-09-01 — Action Scheduler 4.x requires 6.8, and the 3.9 line never received its deserialization hardening. See SPEC.md, decision F10.)*
- PHP ≥ 8.1 (declare 8.0 min), PSR-4 autoload via Composer.
- Admin UI: React via `@wordpress/scripts` (`@wordpress/components`, `data`, `api-fetch`). Gutenberg block for the accessibility statement.
- Background jobs: **Action Scheduler** (never block admin/AI on request threads).
- Licensing/payments: **Freemius WordPress SDK**.
- AI: WP 7.0 AI Client when present; else **BYOK** adapters (OpenAI, Anthropic, Gemini, OpenRouter). Vision model for alt text.
- Storage: custom tables via `dbDelta`; settings in options; per-post scan cache in postmeta.

## Naming & conventions
- Namespace `WOWStudio\AccessibilityKit` · function/hook prefix `wsak_` · DB tables `wp_wsak_*` · text domain `wowstudio-accessibility-kit` · license GPLv2+.
- Repo layout, data model, and REST routes are defined in `SPEC.md` — follow them.

## Freemius gating rules
- Wrap all Pro logic in `if ( wsak_fs()->can_use_premium_code() ) { … }`; plan checks via `is_plan('pro', true)`, `is_paying()`, `is_free_plan()`.
- Pro-only methods use the `__premium_only` suffix; Pro-only files/folders use the `@fs_premium_only` header tag (auto-stripped from the free build).
- **No premium code may ship in the free WordPress.org zip.** Rely on Freemius stripping **and** add a build check.
- Use the **current integration snippet from the Freemius Developer Dashboard** (it carries the wp.org gatekeeper marker) — don't hand-copy an old one.

## Quality floor (every PR)
- Security: verify nonce + `current_user_can()` on every write/REST route; sanitize input, escape output, `$wpdb->prepare()` for all SQL.
- i18n: all user-facing strings translatable with the text domain.
- Accessible admin UI: WCAG 2.2 AA, full keyboard operability, visible focus, `prefers-reduced-motion` respected, semantic markup.
- Performance: heavy work (bulk scan/remediation/alt-text) through Action Scheduler; batch AI calls; never block a request on an AI call.
- PHPCS clean against the `WordPress` ruleset (PHP 8.1 typing).

## AI / privacy rules
- One `ProviderInterface`; `AiClientBridge` prefers WP 7.0 AI Client, falls back to BYOK.
- Encrypt API keys at rest (libsodium, key from `wp_salt()`); never log keys; never expose via REST.
- "Only send what's needed" — send the failing node + minimal context, not whole pages; per-page opt-out for sensitive content; disclose provider/subprocessor in settings.
- Enforce the Free alt-text cap in the generation path.

## Phase 1 build order (do in sequence — details in `SPEC.md`)
1. Scaffold: headers, constants, PSR-4 autoload, activation/deactivation, `uninstall.php`, text domain; wire Freemius (`free`/`pro` plans, 14-day no-card trial).
2. DB schema (`wp_wsak_scans`, `wp_wsak_issues`) + repositories.
3. PHP `DOMDocument`/`DOMXPath` scanner + MVP rule registry; REST `POST /wsak/v1/scan` (single page); store issues with `auto|manual` tags.
4. React admin dashboard: run scan, list issues with honesty tags, per-page drill-down, score, coverage-transparency panel.
5. AI provider abstraction + BYOK settings (encrypted keys); alt-text generation (single + capped bulk) with the Free cap.
6. One-at-a-time AI fix: preview/diff/apply/undo, stored in the non-destructive **override layer**.
7. Accessibility-statement generator (Gutenberg block + settings) with the EAA feedback mechanism.
8. "Assist, not guarantee" disclaimers on all conformance surfaces; confirm admin UI passes its own WCAG checks.
9. QA gate: PHPCS clean, i18n complete, security review, free zip contains no premium code.

## Prerequisites the human must supply before/while building
- **Freemius product ID + public key** (create the product + `free`/`pro` plans in the Freemius dashboard) → fill the init snippet.
- **Free alt-text cap** — confirm the exact number (e.g. N images/day) that separates Free from Pro.
- **Fix-storage model** — confirmed default is the reversible **override/filter layer** for MVP; write-to-source deferred to Phase 2. Flag if this changes.

## Commands
```bash
composer install        # PHP autoload + PHPCS
npm install             # wp-scripts + React
npm run start           # watch/build admin app  (npm run build for release)
npx wp-env start        # local WordPress → http://localhost:8888
composer run phpcs      # WordPress Coding Standards
```
Use Freemius **sandbox/dev mode** for licensing while developing.
