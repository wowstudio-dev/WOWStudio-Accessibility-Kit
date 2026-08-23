# WOWStudio Accessibility Kit

A real-remediation WordPress accessibility plugin. It helps you **find, fix,
document, and monitor** WCAG issues at the code level.

It is not an overlay, and it never tells a user their site is compliant. See
[`CLAUDE.md`](CLAUDE.md) for the non-negotiable product rules and
[`SPEC.md`](SPEC.md) for the full feature specification and build order.

## Status

Phase 1, step 1 of 9: tooling and scaffold. Nothing user-facing yet.

## Requirements

| | |
|---|---|
| WordPress | 6.6+ |
| PHP | 8.1+ |
| Node | 20+ |
| Docker | required for `wp-env` and for running the PHP test suite locally |

## Setup

```bash
composer install
npm install
npx wp-env start      # WordPress at http://localhost:8888 (admin/password)
```

## Quality gate

Run the whole gate before every commit:

```bash
composer lint
```

That runs, in order:

| Command | Checks |
|---|---|
| `composer phpcs` | WordPress-Core, -Extra, -Docs, PHPCompatibilityWP |
| `composer phpstan` | Static analysis at level 6 |
| `composer test` | PHPUnit unit suite |
| `composer check-claims` | No unqualified compliance claims anywhere |
| `composer check-free-build` | No premium code or Freemius secret in the free build |
| `npm run plugin-check` | The official WordPress Plugin Check |

`npm run plugin-check` and `npm run makepot` both build the plugin into `dist/`
first and run against that, not the working tree — otherwise they see
`node_modules`, dev Composer dependencies, and tooling config as shipped files.
Plugin Check excludes `freemius/`, the vendored SDK we cannot modify; see
[`docs/RELEASE-CHECKLIST.md`](docs/RELEASE-CHECKLIST.md) for the numbers behind
that decision.

Build the release artifact with:

```bash
npm run dist:zip
```

## Testing the free experience

The working tree is the **premium** codebase (`is_premium => true`), because
Freemius generates the WordPress.org build from it on deploy. Installing it
locally therefore shows you the Pro install, not what a free user sees.

To install and test the free flavour without deploying:

```bash
npm run dist:free:zip
```

That writes `dist/wowstudio-accessibility-kit-free.zip` with `is_premium` set to
`false` and the gatekeeper secret removed. Install it and confirm for yourself
that it activates with no licence and no blocking screen.

Freemius's own deploy output remains authoritative. This local build applies
only the header transformations; it deliberately **refuses to run** if our code
ever contains `__premium_only` or `@fs_premium_only`, because guessing at that
stripping would give false confidence. When that day comes, test the zip
Freemius generates.

### A licence prompt that will not go away

No licence is required for the free plan, and none is required to *use* the
premium codebase either — Pro features simply stay locked. If you do see a
prompt that never clears, the cause is almost certainly stale Freemius state:
if a request dies part-way through activation, Freemius can be left believing an
opt-in is still in flight. Clear it with:

```bash
npm run fs:reset
```

### Running tests locally

Brain Monkey depends on Patchwork, which does not support PHP 8.5. If your host
PHP is newer than 8.4, run the suite in a container:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.1-cli php vendor/bin/phpunit
```

CI runs the suite on PHP 8.1, 8.2, 8.3, and 8.4.

## Two guards worth knowing about

Both are wired into CI and both fail the build.

**`bin/check-claims.php`** enforces product rule #1 — assist, never guarantee.
It fails on any unqualified use of "compliant", "certified", "guaranteed",
"lawsuit", and similar, in any file that ships. A line is allowed only if it
negates the claim, carries a `wsak:claim-reviewed` annotation, or appears
verbatim in `bin/claims-allowlist.txt`.

**`bin/check-free-build.php`** makes sure no Pro-only code and no Freemius
gatekeeper secret can reach the free WordPress.org zip. Freemius strips these on
deploy; this is the second lock, because a stripping failure is only discovered
once the code is already public. Run it against the generated free zip, not just
the source tree:

```bash
php bin/check-free-build.php dist/free.zip
```

## Repository layout

```
wowstudio-accessibility-kit.php   Bootstrap: headers, constants, Freemius init
uninstall.php                     Entry point for src/Uninstaller.php
src/Core/                         Plugin orchestrator, activation, deactivation
src/Support/                      Shared helpers (capabilities)
bin/build.sh                      Release build (honours .distignore)
bin/                              Product guards
freemius/                         Freemius SDK (vendored, committed)
docs/RELEASE-CHECKLIST.md         What a human must verify before release
```

Later steps add `src/Scanner/`, `src/Remediation/`, `src/AI/`, `src/AltText/`,
`src/Conformance/`, `src/Rest/`, `src/Admin/`, `src/Db/`, and the React admin app
under `assets/src/`. See `SPEC.md`.

## License

GPL-2.0-or-later. See [`LICENSE`](LICENSE).
