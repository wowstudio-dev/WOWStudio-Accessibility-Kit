# WOWStudio Accessibility Kit — website

Marketing and documentation site for the WOWStudio Accessibility Kit plugin.

This is an **orphan branch**: it shares no history with `main` and carries none
of the plugin source. Site assets and plugin code are kept apart on purpose, so
that neither one's history, tooling, or release process drags the other along.

Nothing has been built here yet.

## The one rule that carries over

The product's positioning is legally load-bearing and applies to every word on
the site as much as it does in the plugin:

> **Assist, never guarantee.** The plugin helps you *find, fix, document, and
> monitor* accessibility issues. Nothing may claim a site is "compliant",
> "ADA/EAA compliant", "certified", "lawsuit-proof", or "100% accessible".

This is not brand voice, it is risk management — the accessiBe/FTC action is the
precedent. `bin/check-claims.php` on `main` enforces it mechanically for shipped
code; on this branch it is on whoever writes the copy.

Two related claims that also must not be made: this is **not an overlay**, and
automated testing covers only part of WCAG.

See `CLAUDE.md` and `SPEC.md` on `main` for the full product rules.

## Branches

| Branch | What it is |
| --- | --- |
| `main` | Plugin source. The single codebase. |
| `Free` / `Pro` | Track `main`; the free/pro split happens at build time via Freemius, not here. |
| `Website` | This branch. Site only. |
