# Pro — what this branch is, and what it is not

This branch is **frozen**. It is a snapshot of the plugin as it stood at 0.15.1,
kept because it is the only place the Freemius integration, the AI provider
layer, the AI alt-text generation, and the tier gating still exist. The free
plugin has had all four removed.

Treat this tree as a **reference to port from**, not a base to build on. Only
the plugin's identity has been renamed so far; nothing underneath has been made
fit for purpose, and the list below says why.

## The decision that shapes the rebuild

Pro is an **add-on plugin that attaches to the free one**, not a stripped build
of it. Free ships complete: no locked controls, no upsell surfaces, no
`@fs_premium_only`, no build-time stripping. Pro installs alongside and only
ever adds.

That ordering matters. A feature that has shipped in a free WordPress.org
release cannot be taken back later without it reading as a bait-and-switch, so
the line has to hold from the first release rather than being negotiated
afterwards.

## Where the line falls

**Free finds everything and fixes it. Pro keeps it fixed and gives you
something to send the client.**

Never gate finding. Every check, every severity, every post type, and the
site-wide scan itself stay free — withholding a check means telling somebody
their site has a barrier that will not be described to them until they pay, and
the person who loses that trade is the disabled visitor rather than the site
owner. What Pro sells is automation, deliverables, and marginal cost:

1. **Scheduled monitoring** — re-scans on a schedule, regression alerts, email
   digests. The only feature here whose value accrues monthly, which is what
   makes a subscription honest rather than merely convenient. Build this first;
   until it works there is no Pro worth selling.
2. **Crawling beyond WordPress content.**
3. **Client deliverables** — CSV and PDF export, branded and white-labelled
   reports, reports sent on a schedule.
4. **AI** — alt-text suggestions and generative fixes. Worth charging for
   because it has a real per-call cost, which is a more defensible reason to
   charge than artificial scarcity. BYOK first, hosted keys as the upsell.
5. **Team controls** — role restrictions on dismissal, approval flow, multisite.

Deliberately *not* gated, though the obvious competitor gates each one: extra
post types, admin columns, the full-site report, the dismissal log, WP-CLI
including bulk, and scan history retention.

## What is owed before this branch can be built

The rename stopped at the plugin header. Everything below is still the free
plugin's, and would collide with it if both were active at once.

- [ ] **Constants.** `WSAK_VERSION`, `WSAK_FILE`, `WSAK_PATH`, `WSAK_URL`,
      `WSAK_BASENAME`, `WSAK_MIN_PHP`, `WSAK_MIN_WP` are all defined
      unconditionally. Two active plugins defining the same constants is a
      fatal error. Pro needs its own `WSAK_PRO_*` set.
- [ ] **Text domain.** Still `wowstudio-accessibility-kit`, in 734 places.
      A separate plugin needs its own.
- [ ] **Namespace.** Still `WOWStudio\AccessibilityKit`. Composer's autoloader
      would be registered twice for the same prefix over two different
      directories.
- [ ] **Strip everything free already does.** This tree is a whole plugin. As
      an add-on it should carry only what the list above describes, and reach
      the rest through the free plugin's extension points.
- [ ] **Extension points.** Free is being built with four deliberate seams for
      this: the scan runner (so a scheduler can drive it), the report screen
      (so exporters can register formats), the fix pipeline (so AI can register
      as a fix provider), and the dismissal flow (so role restrictions can hook
      the capability check). Port against those rather than forking free.
- [ ] **Freemius.** The SDK here is 2.13.4 and will be stale by the time this
      ships. Take the current integration snippet from the Freemius dashboard
      rather than reusing what is vendored here.
- [ ] **A dependency check** that deactivates Pro, with a readable notice, when
      the free plugin is missing or too old.

## Where to look

The tag `pro-seed-0.15.1` marks this same tree. The free plugin's history from
that tag onward is the record of exactly what was removed and why.
