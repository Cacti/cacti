# CSRF Protection Migration Plan

## Current State

Cacti uses the `csrf-magic` library (`include/csrf.php`) for CSRF token
generation and validation. Tokens are tied to the PHP session and
verified on form submission. The library handles most POST forms
automatically via output buffering.

Known gaps:

- AJAX endpoints that accept GET with side effects lack CSRF checks.
- Some plugin hooks bypass the csrf-magic output buffer.
- Token rotation does not occur on privilege transitions (addressed
  by `cacti_auth_transition()` in this PR).

## Short Term (1.2.x)

- Audit all `action=save` and `action=delete` endpoints for CSRF
  token presence. File issues for any missing coverage.
- Ensure `cacti_auth_transition()` is called at login and password
  change to rotate session-bound CSRF tokens.
- Document which AJAX endpoints are POST-only via `cacti_dispatch()`.

## Medium Term (1.3.x)

- Migrate from csrf-magic output buffering to explicit token embedding
  using a `cacti_csrf_field()` helper in form templates.
- Add `SameSite=Lax` as the default cookie attribute for the session
  cookie (already partially done via `session.cookie_samesite`).
- Require POST for all state-changing operations enforced through
  `cacti_dispatch()` method checks.

## Long Term (2.x)

- Evaluate double-submit cookie pattern as a csrf-magic replacement.
- Consider per-request tokens with HMAC binding to the session ID.
- Remove csrf-magic dependency once all forms use explicit tokens.

## References

- [#7051](https://github.com/Cacti/cacti/issues/7051) - CSRF audit tracking issue
- `include/csrf.php` - Current csrf-magic integration
- `lib/cacti_dispatch.php` - Method enforcement dispatcher
- `lib/auth.php:cacti_auth_transition()` - Session rotation on privilege change
