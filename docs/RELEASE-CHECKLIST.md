# Release checklist

Things that cannot be verified from inside the codebase, and must be confirmed
by a human before any public release. Items marked **TODO(human)** are open.

## Freemius dashboard

- [ ] **TODO(human)** Plans exist with the exact slugs `free` and `pro`. Gating
      calls `wsak_fs()->is_plan( 'pro', true )` by slug; a mismatch silently
      leaves Pro features locked for paying customers.
- [ ] **TODO(human)** Trial on `pro` set to **7 days, payment method required**.
      The `trial` argument in the plugin only mirrors the dashboard; the
      dashboard is authoritative.
- [ ] **TODO(human)** Telemetry configured as opt-in only (SPEC.md rule: never
      silent tracking).
- [ ] Re-run `composer check-free-build -- dist/free.zip` against the **generated
      free zip**, not just the source tree, before submitting to WordPress.org.

## WordPress.org submission

- [ ] **TODO(human)** `Contributors:` in readme.txt is a real WordPress.org
      username. Currently `wowstudio`, unverified.
- [ ] `Tested up to:` in readme.txt reflects the current WordPress release.
      Currently `7.1`, verified against the wp-env environment on 2026-08-23.
      Re-check at each release.
- [ ] Plugin Check passes with no errors (`npm run plugin-check`).

      This runs against the **built** plugin in `dist/`, not the working tree,
      and excludes `freemius/`. That exclusion is deliberate and needs to be
      understood rather than forgotten: the vendored Freemius SDK produces 525
      errors and 1,239 warnings of its own (measured against SDK 2.13.4, mostly
      `WordPress.Security.EscapeOutput`). It is third-party code we cannot
      modify, and it ships inside a large number of plugins already on
      WordPress.org. Our own code is clean. If a reviewer raises the SDK, the
      answer is that it is unmodified upstream Freemius, not our output.
- [ ] **External services disclosure** — required by WordPress.org whenever the
      plugin contacts a third party. Not yet written, because no AI provider
      calls exist yet. **This must be added to readme.txt in Phase 1 step 5**,
      when the BYOK provider adapters land, listing every provider the user can
      configure, what data is sent, when, and links to each provider's terms and
      privacy policy. Submitting without it is a rejection.
- [ ] Screenshots and banner assets prepared.

## Legal and product

- [ ] `composer check-claims` passes, and every entry in
      `bin/claims-allowlist.txt` has been re-read and still reads as a
      disclaimer rather than an assertion.
- [ ] No front-end output is added for visitors (rule: not an overlay).
- [ ] Every conformance surface carries the assist-not-guarantee disclaimer.

## Accessibility

- [ ] The plugin's own admin UI passes WCAG 2.2 AA: keyboard operability,
      visible focus, `prefers-reduced-motion`, semantic markup.
- [ ] Tested with at least one screen reader.
