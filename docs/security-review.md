# Security review

*Phase 1, step 9. Reviewed: 2026-08-30, against 0.8.0 + the step 9 changes.
Scope: the plugin's own code — `src/`, `assets/src/`, `uninstall.php`, and the
build scripts. The vendored Freemius SDK in `freemius/` is out of scope.*

One real finding, fixed and regression-tested. Everything else below is a record
of what was checked and how, so the next review starts from evidence rather than
from scratch.

---

## Finding 1 — an unauthenticated caller could tell which post IDs existed

**Severity:** low. An existence oracle, not content disclosure. **Status:** fixed.

`POST /wsak/v1/scan` declared its `post_id` argument with a `validate_callback`
that checked both existence and `read_post`. WordPress validates arguments
**before** it runs `permission_callback`, so that check answered anonymous
callers, and it answered them differently depending on what it found:

| Request (no authentication at all) | Response |
| --- | --- |
| `post_id` of a published post | 400, `wsak_forbidden_post`, "You do not have permission to read that content." |
| `post_id` of a **private draft** | 400, `wsak_forbidden_post`, "You do not have permission to read that content." |
| `post_id` never used | 400, `wsak_unknown_post`, "That content could not be found." |

The difference between the last row and the first two let anyone on the internet
enumerate which post IDs existed, including unpublished and private ones, by
walking the integers. Titles and content were never exposed — only existence.

WordPress compounded it by wrapping the inner error: the carefully-set 401 was
flattened into a 400 `rest_invalid_param`, with the distinguishing message
preserved in `data.details.post_id`.

**Fix.** The check moved out of the argument validator and into the handler,
behind `can_scan()`. Verified after the change:

| Caller | Published | Private draft | Nonexistent |
| --- | --- | --- | --- |
| Anonymous | 401 `wsak_forbidden` | 401 `wsak_forbidden` | 401 `wsak_forbidden` |
| Subscriber (no capability) | 403 `wsak_forbidden` | 403 `wsak_forbidden` | 403 `wsak_forbidden` |
| Administrator | 201 scanned | 422 unparseable¹ | 404 `wsak_unknown_post` |

¹ A draft has no public URL to fetch, so the scan legitimately cannot read it.
That is a separate behaviour, reported only to a caller already entitled to know.

`ScanControllerTest::test_no_argument_validator_performs_authorisation` fails the
build if any argument on any route regains a `validate_callback`. The rule it
encodes is deliberately blunt — argument validators check *shape*; handlers and
permission callbacks decide *access* — because the ordering is a property of
WordPress, not of this plugin, and the blunt rule is the one that survives.

---

## Authorisation

- **16 route handlers across 14 routes**, every one with a `permission_callback`.
  None is `__return_true`. `ScanControllerTest` asserts a route cannot be
  registered without one — WordPress only warns, and still serves it.
- **Four least-privilege capabilities** (`wsak_manage_settings`, `wsak_run_scan`,
  `wsak_apply_fix`, `wsak_view_reports`) rather than `manage_options` for
  everything. Reading results and changing markup are separate rights.
- **Per-object checks in addition to the capability.** Holding `wsak_run_scan`
  does not imply the right to read every post, so the routes that touch specific
  content also check `read_post` (scan, scan retrieval, fix listing) or
  `edit_post` (alt-text writes). This is what stops an authorised low-privilege
  user reading content through the plugin that they could not read directly.
- **Live verification.** Every route was called unauthenticated; all returned 401
  or 403, none returned data. Repeated as a subscriber with the same result.

## SQL

Every statement goes through `$wpdb->prepare()`. Table and column names use the
`%i` identifier placeholder (WordPress 6.2+; the plugin requires 6.6).

Two statements are assembled rather than written as literals, and Plugin Check
flags both. Both were re-read for this review and are safe:

- `IssueRepository::add_many()` builds a multi-row INSERT whose placeholder count
  depends on batch size. Each fragment appended is the constant string
  `'(%d, %d, %s, …)'`; no caller data reaches the SQL text.
- `IssueRepository::find_by_scan()` builds a WHERE clause from the filters given.
  Each fragment is a literal (`'status = %s'`); the `ORDER BY` comes from
  `severity_order()`, which builds `FIELD(severity, …)` from the `Severity` enum's
  own cases and cannot contain input. `LIMIT`/`OFFSET` are clamped as well as
  bound.
- `IssueRepository::count_by()` interpolates a column name for `GROUP BY`, which
  cannot be parameterised. It is allowlisted against `self::GROUPABLE` *and*
  passed through `%i`.

## Secrets

- Provider API keys are encrypted with `sodium_crypto_secretbox`, a fresh random
  nonce per key, under a key derived via `sodium_crypto_generichash` from
  `wp_salt( 'secure_auth' )`. The derived secret is wiped with `sodium_memzero`.
- **No route returns a key.** The settings response carries `has_key` (boolean)
  and `key_hint`, which is `••••` plus the last four characters. Confirmed by
  reading every response builder.
- Keys are never logged. The only `error_log()` in the plugin reports a failing
  scanner rule's message, gated behind `WP_DEBUG`.
- The key field is `type="password"` with `autoComplete="off"`.

## Input, output, and transport

- **Output escaping.** No unescaped `echo`/`print` anywhere in `src/` or the
  bootstrap. The one `dangerouslySetInnerHTML` is the statement preview, which
  renders `StatementGenerator` output — our own markup, with every interpolated
  value passed through `esc_html()`/`esc_url()` — not user input echoed back.
- **Input sanitising.** Every REST argument declares a `sanitize_callback`
  (`absint`, `sanitize_text_field`, `sanitize_textarea_field`, `sanitize_email`,
  `esc_url_raw`) and a type.
- **CSRF.** All writes are REST calls made through `@wordpress/api-fetch`, which
  carries the `X-WP-Nonce` WordPress puts on the page; `wp-api-fetch` is a
  declared dependency of the admin bundle, so the nonce middleware is always
  registered. There are no `admin-post.php` or `admin-ajax.php` handlers and no
  form posts, so there is no second write path to protect.
- **Outbound HTTP** uses `wp_remote_get`/`wp_remote_post` with explicit timeouts.
  TLS verification is on by default; the page-source fetch exposes a
  `wsak_page_source_sslverify` filter so a developer can relax it for a
  self-signed local certificate. Turning it off is a deliberate act.
- **Uninstall** is guarded by both `ABSPATH` and `WP_UNINSTALL_PLUGIN`, and
  removes data only when the owner opted in. The default keeps everything.

## Free build

`bin/check-free-build.php` was run against the **generated zip**, not just the
source tree: 70 PHP files, no premium-only code, no Freemius gatekeeper secret.
The only paths matching `premium` are three unmodified Freemius SDK templates,
which ship in every Freemius free build.

Plugin Check reports **0 errors** against the built plugin.

---

## What was NOT checked

1. **The Freemius SDK.** Vendored third-party code, excluded from PHPCS and
   Plugin Check. Unmodified upstream, but unaudited by us.
2. **No penetration testing, fuzzing, or dependency CVE scan.** This is a code
   review, by reading and by targeted live probes.
3. **The AI providers' own handling** of what we send them. The plugin discloses
   what leaves the site and lets the user turn page context off; what the
   provider then does is the provider's contract with the user.
4. **Multisite.** Activation is multisite-aware, but no network-level privilege
   testing was done.
5. **No review of markup a fix applies.** A fix is model-generated markup stored
   in the override layer, reviewed by a human in the diff before it is applied.
   That human review is the control; there is no server-side sanitiser asserting
   the applied markup is safe, and a user with `wsak_apply_fix` is trusted the
   way a user with `unfiltered_html` is trusted.

Item 5 is the one to revisit first if the capability model ever widens.

---

## Re-running the checks

```bash
composer lint                              # phpcs, phpstan, phpunit, guards
npm run lint:js                            # includes the i18n rules
npm run dist:free:zip
php bin/check-free-build.php dist/wowstudio-accessibility-kit-free.zip
npm run plugin-check
```
