# CSP Nonce Migration — Requirements (autopilot phase 0.1)

Branch: security/csp-nonce-migration (cut from feat/security-architecture-1.2.x).
PHP floor: 7.4 (matches branch).

## Locked scope decisions (based on analyst's recommendations)

- Target: 1.2.x, PHP 7.4. Stay on this branch.
- Feature flag: extend existing `content_security_policy_script` config option.
  - Current values: empty | `unsafe-eval`
  - New values: `nonce-report` (emit Report-Only CSP), `nonce` (enforce)
  - Default: empty (current `'unsafe-inline'` behavior preserved)
- Pilot file list (<=3 pages, <=10 tag edits): `about.php`, `logout.php`, one light admin page.
- DO NOT migrate all 184 tags in this branch. Ship infrastructure + pilot + tests.
- Public plugin API: commit to `CactiSecureHeaders::getNonceAttribute()` + `::getNonce()`.
- Playwright scope: `tests/e2e/package.json` only, no root-level Node.

## Functional requirements

1. `CactiSecureHeaders::emitHeaders()` reads `content_security_policy_script` and:
   - empty → emit current policy with `'unsafe-inline'`
   - `unsafe-eval` → add `'unsafe-eval'` to script-src (existing behavior)
   - `nonce` → replace `'unsafe-inline'` with `'nonce-<b64>'` in script-src AND style-src
   - `nonce-report` → same as `nonce` but use `Content-Security-Policy-Report-Only` header
2. `getNonce()` / `getNonceAttribute()` are stable public API for plugin authors.
3. Every inline `<script>` and `<style>` in the pilot pages carries `nonce="<b64>"` attribute matching the CSP header.
4. Rollback is clean: flip the flag off, behavior reverts to unsafe-inline.

## Non-functional requirements

- Nonce generation cost: p99 <50µs per request (one `random_bytes(16)` call, cached static).
- Header must ship on every HTML response (assert via regression test).
- CLI scripts that include global.php must not break (emitHeaders short-circuits on CLI).
- Plugin-emitted inline scripts continue to work when flag is empty (default).
- Plugin-emitted inline scripts are documented as breaking when flag is `nonce`; plugin authors opt in via `getNonceAttribute()`.

## Implicit requirements

- CI workflow extension for E2E leg (docker-compose spinning up MariaDB + PHP-FPM + Cacti).
- Report-URI endpoint for `nonce-report` mode to accept `application/csp-report` payloads.
- `{$alternates}` header-injection sanitization (same block, orthogonal but trivially bundled).
- Documentation update to `docs/security-headers.md` covering the new flag values and plugin contract.

## Out of scope

- Plugin-author coordination (separate effort).
- Migrating every inline tag (explicit deferral; follow-up branches per-page).
- Twig / htmx / framework changes.
- Form rendering changes (TheWitness: "forms are not broken").
- On* event-handler migration (separate branch — requires addEventListener refactor).

## Acceptance criteria

- Unit tests: `getNonce()` idempotent within request, base64, 24 chars; `emitHeaders()` emits expected header set; `getNonceAttribute()` is HTML-safe.
- Integration tests: with flag=`nonce`, login flow works, dashboard renders, header contains nonce matching rendered HTML.
- E2E (Playwright): login submits; `graph_view.php` loads without console CSP violation; headers assertion extracts nonce and matches inline tags.
- Pre-push gates: `composer run-script phpcsfixit` clean; `php -l` clean; no `.claude/`/`.omc/`/`.worktrees/`/`include/vendor/**` leakage in diff.
- Header present on every HTML response (regression test).
- Default behavior (empty flag) unchanged.

## Open guardrails from analyst (to be decided in spec)

- Strict-dynamic: defer. Keep 'self' + alternates + nonce for now.
- IE11: not supported in strict mode (browser ignores nonces). Acceptable; Cacti UI is modern.
- base64 vs base64url: use base64url (`strtr('+/', '-_')`) to avoid WAF/regex issues.
