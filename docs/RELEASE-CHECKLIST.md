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
- [ ] The `== Changelog ==` and `Stable tag:` in readme.txt both match the
      release being shipped. These drifted apart once already (readme listed
      0.1.0 while the tag read 0.7.0) because CHANGELOG.md is the file anyone
      actually edits; check both.
- [ ] Plugin Check passes with no errors (`npm run plugin-check`).

      This runs against the **built** plugin in `dist/`, not the working tree,
      and excludes `freemius/`. That exclusion is deliberate and needs to be
      understood rather than forgotten: the vendored Freemius SDK produces 525
      errors and 1,239 warnings of its own (measured against SDK 2.13.4, mostly
      `WordPress.Security.EscapeOutput`). It is third-party code we cannot
      modify, and it ships inside a large number of plugins already on
      WordPress.org. Our own code is clean. If a reviewer raises the SDK, the
      answer is that it is unmodified upstream Freemius, not our output.
- [x] **External services disclosure** — written in 0.9.0. readme.txt now has an
      `== External services ==` section covering all four providers, the exact
      endpoint each request goes to, what is sent and when, the 300-character
      cap on page context, the `_wsak_skip_ai` opt-out, and Freemius. It landed
      four steps later than planned: the item said "step 5" and the adapters
      shipped in 0.5.0 without it.
- [ ] **TODO(human)** Open every link in that section and confirm it resolves.
      The endpoint URLs were read out of the provider adapters and are correct;
      the terms and privacy-policy URLs were written from knowledge and have
      **not** been fetched. Providers move these pages. A dead link in this
      section is a rejection just as surely as a missing section.
- [ ] Screenshots and banner assets prepared.

## Our own accessibility

- [ ] `docs/accessibility-audit.md` re-run and re-dated against the release.
      Section 4 lists what has never been checked; it must not shrink by
      accident, and any claim moved out of it needs the evidence that moved it.
- [ ] **TODO(human)** Drive the admin dashboard in a browser with axe-core, and
      through at least one screen reader. The last live DOM run was 2026-08-23
      (146 elements, zero findings) against the **step 4** dashboard — the AI
      settings panel, the fix preview and diff, the statement panel and every
      0.8.0 change landed afterwards and have never been in a live run.
      Everything since is computed from source or from server-rendered output,
      and the live-region fix in 0.8.0 is reasoned from the specification rather
      than confirmed against a real screen reader. Automating this needs the
      Playwright harness, which is Phase 2.
- [ ] Re-run `node bin/check-contrast.js` and `composer test` (which includes
      `DogfoodTest`) after any change to the admin app or the palette.
- [ ] **TODO(human)** Test with disabled users. Nothing else settles whether
      any of this works, and shipping an accessibility tool that has never been
      near one is the criticism this product exists to avoid.

## Third-party code (from Phase 2 onward)

- [x] **axe-core is not bundled.** The browser pass was going to use it
      (MPL-2.0), which would have been the only item here needing a legal opinion
      rather than an engineering one. Reversed on 2026-08-30: we write the five
      browser checks ourselves. That licence question is not answered, it is
      *gone*. Decision and reasoning in SPEC.md under D1.
- [ ] **Action Scheduler ships, and it is GPLv3-or-later.** Added 2026-09-01 as
      the background queue (decision F10). This plugin is GPLv2-or-later, and
      those combine in this direction: "or later" permits distributing the
      combined work under v3, which is what WooCommerce and every other plugin
      bundling Action Scheduler relies on. **Our source stays GPLv2+; the shipped
      zip taken as a whole is GPLv3.** Nothing to resolve — recorded so nobody
      has to derive it again under time pressure.
      - [ ] `vendor/woocommerce/action-scheduler/license.txt` survives the build.
      - [ ] Attributed in readme.txt.
      - [ ] Its WordPress floor is still ≤ our declared `Requires at least`.
            4.x wants 6.8; ours says 6.8. A future bump on their side is a
            decision on ours, not a silent follow.
- [ ] Re-check this section if any third-party runtime library is ever added.
      The standing rule: PHP dev dependencies and build tooling do not ship, so
      they are not a licensing question; anything that reaches the zip is.
- [ ] Attribution for every bundled library appears in readme.txt and the source
      headers survive the build (wp-scripts writes `*.LICENSE.txt` alongside each
      bundle — confirm those files are not stripped by `.distignore`).

## Known, accepted Plugin Check warnings

Plugin Check reports **0 errors** and 9 warnings. Two are in
`src/Db/IssueRepository.php`, and both are the same finding:
`PluginCheck.Security.DirectDB.UnescapedDBParameter` on `add_many()` and
`find_by_scan()`.

Those two statements genuinely cannot be written as a fixed literal: one builds a
multi-row INSERT whose placeholder count depends on the batch size, the other
builds a WHERE clause from the filters the caller passed. In both cases the
assembled string contains **only** placeholder fragments, and every value —
including the table name, via the `%i` identifier placeholder — goes through
`$wpdb->prepare()`. `IssueRepositoryTest` asserts this directly, including that
an injection attempt in a rule ID lands in the bound values and never in the SQL.

If either method is edited, re-read it before assuming the warning is still
benign.

The other seven are `PluginCheck.CodeAnalysis.AIProvider.DirectIntegration` on
the provider adapters in `src/AI/Providers/` (OpenAI 1, Gemini 2, Anthropic 2,
OpenRouter 2). Plugin Check now nudges plugins
towards the AI client WordPress 7.0 ships rather than calling providers
directly, and that nudge is right. We already follow it: `AI\AiClientBridge`
prefers `WordPress\AiClient\AiClient` whenever it is present and configured,
and only falls back to the direct adapters otherwise.

The adapters exist because the plugin supports WordPress 6.8, and the AI client
does not exist before 7.0. They are the documented graceful degradation, not a
shortcut around the core API.

- [ ] **TODO(human)** Be ready to explain this to a WordPress.org reviewer, and
      revisit once the supported floor rises to 7.0 — at that point the direct
      adapters can be dropped entirely.

## Tooling debt

- [ ] Drop the `PHPCompatibility.Variables.ForbiddenThisUseContexts` exclusion in
      `phpcs.xml.dist` once PHPCompatibility 10 is stable. The stable release is
      9.3.5, which predates PHP 8.1 enums and flags every `match ( $this )` in an
      enum method as a false positive. Until then that sniff is off, so a genuine
      `$this` in a plain function would not be caught by PHPCS.

## Legal and product

- [ ] `composer check-claims` passes, and every entry in
      `bin/claims-allowlist.txt` has been re-read and still reads as a
      disclaimer rather than an assertion.
- [ ] No front-end output is added for visitors (rule: not an overlay).
- [ ] Every conformance surface carries the assist-not-guarantee disclaimer.
