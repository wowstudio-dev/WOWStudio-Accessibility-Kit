# PROMPT.md — Claude Code kickoff (WOWStudio Accessibility Kit)

> **Historical.** This is the prompt that started the project, kept as a record.
> It describes a two-tier Freemius product with an AI remediation engine, none
> of which exists: 0.16.0 removed the licensing SDK and the whole AI layer, and
> there is one free plugin with nothing gated. Do not follow the steps below.
> `CLAUDE.md` is the current brief, and the superseded block at the top of
> `SPEC.md` explains what changed and why.

> Paste the block below as your first message in Claude Code. It assumes `CLAUDE.md` and `SPEC.md`
> are in the repo root (they'll be auto-loaded / referenced). Leave out "continue automatically" to
> have Claude pause after each of the 9 Phase-1 steps for your review; add it once you trust the rhythm.

---

```markdown
You are the lead engineer building the "WOWStudio Accessibility Kit" WordPress plugin.

STEP 0 — GROUND YOURSELF
Read CLAUDE.md and SPEC.md in full before writing any code. Then reply with:
(a) a 5–8 line restatement of what we're building and the non-negotiable product rules,
(b) your plan for Phase 1 step 1, and
(c) any blocking questions.
Do not start coding until you've done this.

MISSION
Build the Phase 1 MVP in the exact 9-step order in SPEC.md, one step at a time.
After each step: run the quality gate (below), give me a short summary + what changed,
then stop and wait for me to say "continue" (unless I've said "continue automatically").

STANDING REQUIREMENTS (apply to every line of code, every step)

1. WordPress coding standards — compliant, not "close":
   - PHPCS must pass clean against WordPress-Core, WordPress-Extra, and WordPress-Docs rulesets.
   - The plugin must pass the official Plugin Check (PCP) plugin with no errors.
   - PHP 8.1 typed signatures; full inline docblocks; no PHP notices/warnings/deprecations.

2. Maintainable by design:
   - PSR-4, small single-responsibility classes, follow the repo structure in SPEC.md exactly.
   - No god objects, no premature abstraction, DRY, dependency-light.
   - Extensibility via hooks/filters. Every non-obvious decision gets a short code comment.
   - Unit tests written alongside the code, not after.

3. Compatible with every standard metric + built for a global market:
   - Static analysis: PHPStan (target level 6+) or Psalm — clean.
   - PHPCompatibility: PHP 8.0 through 8.3. WordPress 6.6 through 7.x. MySQL + MariaDB.
   - Full internationalization: text domain "wowstudio-accessibility-kit" on EVERY user-facing
     string, translator comments, printf placeholders (never concatenate), generate the .pot,
     load_plugin_textdomain on init. Provide RTL stylesheets. Locale-aware dates/numbers.
     mb-/UTF-8-safe throughout. No region-locked assumptions.
   - Multisite compatible. Works alongside major themes and page builders (Gutenberg + Elementor
     first). Privacy-by-design (GDPR/CCPA): no data leaves the site without disclosed consent.
   - Security as a gate, not an afterthought: nonce + current_user_can() on every write/REST route,
     sanitize input, escape output, $wpdb->prepare() for all SQL, least-privilege capabilities,
     encrypted API-key storage, no secrets in logs or REST responses.

4. UI/UX to a high, fresh, intuitive standard:
   - Build the admin app in React via @wordpress/scripts using @wordpress/components for a native
     feel, but apply a deliberate, distinctive design system from the WOWStudio palette
     (Ink #0E1525, Indigo #4F46E5, Iris #7C3AED, Cyan #22D3EE) — not a templated default.
   - Clear information architecture and navigation; progressive disclosure over dense screens.
   - Every screen has: a helpful empty state, loading/skeleton states, and actionable error states.
   - Microcopy in plain language, active voice, sentence case; the button that says "Scan" leads to
     a result that says "Scanned." Include a first-run onboarding wizard.
   - Fully accessible admin UI (we dogfood): WCAG 2.2 AA, complete keyboard operability, visible
     focus, prefers-reduced-motion respected, semantic markup. Responsive down to mobile.

5. Quality bar ("100% user satisfaction" — operationalized):
   - Defensive coding: no fatal errors, graceful degradation, never lose or corrupt user data
     (every applied fix is previewable, reversible, backed up).
   - Performance budget: never block admin/request threads on AI or heavy work — queue via Action
     Scheduler; batch AI calls; lazy-load admin JS.
   - Ship real docs: readme.txt (wp.org format), tooltips/help text, and a CHANGELOG.
   - Treat this as iterative: after the MVP, we'll do real accessibility + usability testing and fix
     what we learn. Flag anything you think will hurt real-world users.

NON-NEGOTIABLE PRODUCT RULES (from CLAUDE.md — never break these):
Assist, never guarantee ("helps you find/fix/document/monitor", never "compliant"/"lawsuit-proof").
Not an overlay. Human-in-the-loop for all fixes. Draft, user-attested conformance docs.
No premium code in the free WordPress.org zip.

QUALITY GATE (run and report after every step)
- composer phpcs (WordPress rulesets) — clean
- Plugin Check (PCP) — no errors
- PHPStan — clean at target level
- Unit tests — green
- i18n check — all strings wrapped, .pot regenerated
- Confirm: no premium code would ship in the free build; admin UI passes its own a11y checks

PREREQUISITES — ask me, don't guess:
Freemius product ID + public key; the exact Free alt-text cap (images/day); confirm the fix-storage
model is the reversible override layer for MVP. If I haven't provided them, stub with clearly marked
// TODO(human): … and keep going.

FIRST TASK
Phase 1, Step 1 from SPEC.md: set up tooling (Composer with PHPCS+WPCS+PHPCompatibility+PHPStan,
wp-scripts, wp-env, Plugin Check), then scaffold the plugin (headers, constants, PSR-4 autoload,
activation/deactivation, uninstall.php, text domain) and wire the Freemius SDK with free/pro plans
and a 14-day no-card trial. Show me the plan first.
```

---

## How to use this

- **First run:** paste the fenced block above as your first message. Claude will ground itself on
  `CLAUDE.md` + `SPEC.md`, then plan Step 1 before coding.
- **Drive each step:** reply `continue` after you've reviewed a step. Say `continue automatically`
  once you trust the rhythm and want it to run the 9 steps without pausing.
- **Prerequisites:** have your Freemius product ID + public key, the Free alt-text cap number, and
  the fix-storage confirmation ready — or let Claude stub them as `// TODO(human): …`.

## A note on requirement #5
No prompt can *guarantee* "100% user satisfaction." What this prompt does is force the practices that
earn it — no data loss, no fatals, real accessibility, honest microcopy, performance budgets — and
then you close the remaining gap with real usability + accessibility testing after the MVP ships.
