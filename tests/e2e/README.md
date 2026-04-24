# Cacti E2E Tests (Playwright)

Browser-level checks for the CSP nonce pilot. Exercises the three pilot
pages (`about.php`, `logout.php`, `permission_denied.php`) plus a header
vs. DOM nonce cross-check.

## Status

Skipped by default. The docker-compose stack that serves Cacti at
`E2E_BASE_URL` is delivered in tranche C of the CSP migration. Until then
the `test.describe` wrapper short-circuits to `.skip` unless
`E2E_STACK_UP=1` is set in the environment.

## Prerequisites

- Node.js 18 or newer.
- The docker-compose stack from tranche C (PHP + Apache + MariaDB + seeded
  admin session), reachable at `E2E_BASE_URL` (default
  `http://localhost:8080`).
- For the `nonce-report` test: `content_security_policy_script` set to
  `nonce-report` in the seeded Cacti settings. That test is currently
  `test.fixme()` pending a seed fixture.

## Running locally

```bash
cd tests/e2e
npm ci
npm run install-browsers   # chromium + OS deps, once per machine
E2E_STACK_UP=1 E2E_BASE_URL=http://localhost:8080 npm test
```

Results land in `tests/e2e/playwright-report/`. HTML report is written but
not auto-opened (open `index.html` manually or run `npx playwright show-report`).

## Skip markers

`test.describe.skip(...)` wraps the whole CSP suite when the stack is not
running. Opt in by exporting `E2E_STACK_UP=1`. One test is
`test.fixme()`; fix it when the nonce-report seed fixture lands.

## CI

Wired into `.github/workflows/csp-e2e.yml` as a stub job (`if: false`). The
job flips on when the docker-compose stack ships in tranche C.
