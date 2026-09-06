# Accessibility audit — the plugin's own admin UI

> **Dated record.** Audited 0.11.0 plus the Phase 3 screens on 2026-09-01. Two
> screens it covers — the AI settings panel and the bulk alt-text generator —
> were removed in 0.16.0, and the alt-text editor that replaced the latter has
> never been audited. Section 4, what has never been checked, is the part to
> read first; it must not shrink by accident.

*Standard audited against: WCAG 2.2 level AA. Last run: 2026-09-01, against 0.11.0 plus every Phase 3 surface. The previous run covered 0.7.0 and predated six screens.*

A tool that reports accessibility failures has no business shipping them, so
this is the audit of our own surfaces. It records what was checked, how, what
was found, and — the part that matters most — **what has not been checked yet**.

Read it the way we ask users to read their own scan results: passing the checks
below means those checks passed, and nothing more. Automated testing settles
part of WCAG. It does not settle whether this admin UI is usable by a disabled
person, and nothing here should be read as saying it does.

---

## 1. What was checked mechanically

### 1.1 Colour contrast — SC 1.4.3 (text), SC 1.4.11 (non-text)

`bin/check-contrast.js` reads the palette straight out of the `:root` block of
`assets/src/style.scss` and computes the real ratio for every foreground/
background pairing the admin UI renders — 41 of them, each evaluated at the size
and weight it is actually rendered at, so 12px semibold tags are held to the
4.5:1 small-text threshold rather than the 3:1 large-text one.

**All 41 pass.** The tightest are the severity tags: serious `#b54708` on
`#fff6ed` at 5.08:1, and critical `#b42318` on `#fef3f2` at 6.05:1. Both clear
4.5:1 with room. It runs in CI and as `npm run lint:contrast`.

Two further pairings were considered during the audit and deliberately left out
of the list, because neither is a failure:

| Pairing | Ratio | Why it is not a failure |
| --- | --- | --- |
| Focus ring (indigo `#4f46e5`) against the dark code block `#0e1525` | 2.90:1 | The ring is never drawn on that background. `outline-offset: 2px` places it entirely outside the border box, so it renders against the parent — `--wsak-surface` (6.29:1) or `--wsak-raised` (5.87:1). Both pass. |
| Card border `--wsak-line` `#d6dae3` against white | 1.40:1 | SC 1.4.11 covers visual information *required to identify* a component or its state. These borders group static content; the cards are not interactive and carry no state, and nothing is conveyed by the border alone. Decorative boundaries are outside the criterion. |

Two notes for future changes. `--wsak-cyan` (1.80:1 on white) is accent-only and
must never carry text or state — the comment at the top of `style.scss` says so;
keep it true. And the guard reads the palette but not the markup, so a newly
coloured element has to be added to its `PAIRINGS` list to be covered; that is
the moment to think about its contrast in any case.

### 1.2 The statement we generate, scanned by the scanner we ship

`tests/Unit/DogfoodTest.php` renders the accessibility statement in every
configuration it supports — each conformance status, signed off and not, with
and without each optional section — and runs the plugin's own `Engine` over it.

All 12 MVP rules run against each variant. **All variants scan clean, score 100.**

The suite also asserts that the checks really ran (`rules_run >= 12`), so the
test cannot pass silently if the rule registry is ever emptied by accident. That
is the one way a dogfooding test can lie, and it is the failure worth guarding.

### 1.3 Disclosure coverage

`bin/check-disclosures.php` asserts that **17 required disclosures across 10
conformance surfaces** are still present in the source. It runs in `composer
lint` and in CI.

This is the companion to `bin/check-claims.php`. That one stops us saying
something we must not; this one stops us quietly *dropping* something we must
say. Both are needed: the caveat under the score, the coverage lede, the warning
above a suggested fix and the draft banner are all ordinary strings that a
routine refactor could delete without breaking a single test.

---

## 2. What was found and fixed

| # | Finding | Criterion | Fix |
| --- | --- | --- | --- |
| 1 | The two `<pre>` blocks (issue markup, fix diff) are `tabindex="0"` so their overflow can be scrolled without a mouse, but had no accessible name. A keyboard or screen reader user landed on an anonymous element and heard raw markup with no indication of what it belonged to. | 4.1.2, 2.4.6 | `role="group"` plus an `aria-label` naming the finding it belongs to. Group rather than region deliberately: a page with thirty findings would otherwise add thirty landmarks to the list a screen reader user navigates by. |
| 2 | The admin statement preview injected its own `<h2>Accessibility statement</h2>` into a panel that already had one, so the outline showed two identically-named headings at the same rank and the preview's sections read as siblings of the panel's own rather than as part of the preview. | 1.3.1 | `StatementGenerator::render()` takes a heading offset, clamped to 0–3 so output can never exceed `h6`. The published statement is unchanged at `h2`/`h3`; the preview renders at `h4`/`h5` and nests correctly. |
| 3 | Three `role="status"` live regions were mounted at the same moment they gained their text (fix applied, fix undone, alt text saved). A live region has to exist in the document *before* its content changes, or the announcement is made to a node the screen reader was not yet watching and is simply lost. | 4.1.3 | `fix-action.js` and `alt-text-action.js` now each keep one always-mounted, initially-empty live region and write into it on every state change. The visible confirmations lost their duplicate `role="status"` so nothing is announced twice. |

---

## 3. What was checked by source review

Verified by reading the source, not by running a browser. Recorded here so the
distinction from section 1 stays visible:

- **Focus visibility (2.4.7, 2.4.11).** `.wsak *:focus-visible` sets a 2px indigo
  outline with a 2px offset; the results heading gets 4px. No `outline: none`
  anywhere in the stylesheet.
- **Reduced motion (2.3.3).** The only animation is the loading skeleton's
  shimmer. `@media (prefers-reduced-motion: reduce)` removes it and neutralises
  animation and transition durations across the whole app.
- **Colour is never the only channel (1.4.1).** Detection tags carry a text label
  and a glyph alongside their colour; diff segments carry visually-hidden
  "Added:"/"Removed:" text as well as background colour and underline or
  strike-through; severity is a word, not a swatch.
- **Structure (1.3.1, 2.4.6).** One `h1` per screen, rendered by the app so the
  page cannot end up with two; every `section` is named by `aria-labelledby`;
  the content table uses `scope` on every header and a visually-hidden caption.
- **Names and state (4.1.2).** The Scan buttons take a per-row `aria-label`
  naming the content; the coverage disclosure sets `aria-expanded` and
  `aria-controls`; the API key field is `type="password"` with `autoComplete="off"`.
- **Focus management (2.4.3).** Finishing a scan moves focus to the results
  heading, so the change is not made silently under someone still sitting on the
  button they pressed.
- **Without JavaScript.** `Menu::render()` carries a `noscript` block with a
  heading and an explanation, rather than an empty page.

---

## 3a. The Phase 3 surfaces

Six screens arrived after the previous run and had never been audited: bulk
scanning, the images screen and its review queue, the theme report, the rendered
queue, the dismissal form, and the block-editor panel. Product rule #6 says this
plugin's own interface meets the standard it reports on, and six unaudited
screens is the point at which that stops being true.

### 3a.1 Colour — measured, not eyeballed

`bin/check-contrast.js` grew from 60 pairings to 91: every colour Phase 3
introduced, taken from what the stylesheet actually declares, against the panel
it actually sits on. A card is `surface`; a run panel and a dismissal record are
`raised`, and pairing them against white would have flattered several of them.

**One real failure, found and fixed.** The progress bar's track was 1.49:1
against the panel behind it, where SC 1.4.11 wants 3:1 for a control's visual
boundary. The fix took a moment's thought because the obvious one does not work:
every track colour dark enough to clear 3:1 against the panel lands within 1.5:1
of the indigo fill, so you gain the outer edge and lose the thing the bar is
for. It now carries a hairline outline in the brand violet — 4.19:1 on a raised
panel, 4.53:1 on white — and the fill keeps 5.19:1 against the track.

Worth recording that the bar was never the only conveyance: the counts are
written out beside it. That is a reason the failure was not serious, and not a
reason to have left it.

### 3a.2 Structure — checked in a live DOM

Each new screen was rendered against the real `@wordpress/components` build and
inspected: heading order, accessible names on every control, alt on every image,
live regions, and whether everything is reachable by keyboard.

| | |
| --- | --- |
| Heading order | No skipped levels on any screen. |
| Unnamed controls | None. |
| Images without `alt` | None. |
| Keyboard reachable | Every focusable element, on every screen. |
| Checkbox labels | Every one in the selection list. |

### 3a.3 What was found: an ARIA tablist that was not one

The post-type filters on the bulk screen were marked `role="tablist"` with
`role="tab"` on each button. None of the tab pattern was implemented — no
`aria-controls`, no element with `role="tabpanel"`, no roving tabindex, and no
arrow-key handling.

That is worse than plain buttons rather than better than them. A screen reader
announces "tab, 1 of 2", the user presses an arrow key as the role invites, and
nothing happens. The roles were describing an interaction that did not exist.

They are now a `role="group"` with an accessible name, and `aria-pressed` on
each button to carry which filter is on — which is what they always were.
Verified after the change: no `tab` roles remain, both buttons expose a pressed
state, and everything on the screen is still reachable.

---

## 4. What has NOT been checked

This is the honest part, and it is the section to read before quoting any of the
above.

1. **The live DOM audit covers the Phase 3 screens and not the earlier ones.**
   Section 3a was run against a real render on 2026-09-01. The AI settings
   panel, the fix preview and diff, and the statement panel have still never
   been in a live run — the last one that touched them was 2026-08-23, against
   the step 4 dashboard. Everything in section 1 is computed from source; section
   3 is source review rather than observed behaviour.
2. **The block-editor panel has never been opened in the block editor.** Its
   REST route, its per-block attribution and its refusal to write anything are
   verified against a live WordPress, and the bundle builds with the right script
   dependencies — but no one has watched the sidebar mount, tabbed through it, or
   confirmed that selecting a block from it moves focus sensibly. It is the
   largest surface here with no end-to-end confirmation, and the audit should not
   be read as covering it.
3. **No assistive-technology testing.** Nothing here has been through NVDA,
   JAWS, VoiceOver, TalkBack, Dragon, or a switch device. Live-region behaviour
   in particular varies between screen readers, and finding 3 above is a fix
   reasoned from the specification, not one confirmed against a real screen
   reader.
4. **No testing with disabled users.** The thing that actually settles whether
   this UI works.
5. **Third-party surfaces are out of scope.** `@wordpress/components` renders
   much of this UI and was not audited here.
6. **Zoom and reflow (1.4.10), text spacing (1.4.12), and target size (2.5.8)**
   need a rendered viewport and have not been measured.
7. **The Gutenberg block editor experience** for the statement block was not
   audited beyond the server-rendered output it shows.

None of these gaps is a reason to delay; all of them are reasons not to overstate
what section 1 proves.

---

## 5. Re-running this

```bash
composer lint                      # phpcs, phpstan, phpunit, and every guard below
composer test                      # includes DogfoodTest
node bin/check-contrast.js -v      # 41 pairings against WCAG 2.2 AA
php bin/check-disclosures.php -v   # 17 disclosures across 10 surfaces
php bin/check-claims.php -v        # no unqualified compliance claims
```

All of these run in CI on every push. Two rules for anyone extending the admin
UI: a new conformance or report surface must be added to
`bin/check-disclosures.php` in the same commit, and a newly coloured element must
be added to `bin/check-contrast.js`. Both guards are only as complete as their
lists, and a guard that silently stops covering something is worse than no guard
at all.

`composer test` needs PHP 8.1–8.4; Brain Monkey does not run on 8.5.
