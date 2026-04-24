# Cacti E2E Tests (Playwright)

Browser-level checks for the CSP nonce pilot. Exercises the three pilot
pages (`about.php`, `logout.php`, `permission_denied.php`) plus a header
vs. DOM nonce cross-check.

## What this directory ships

- `docker-compose.yml` + `Dockerfile` + `entrypoint.sh` + `nginx.conf`:
  a three-service stack (MariaDB 10.11, PHP 7.4-FPM, nginx) that serves
  Cacti at `http://localhost:8080` with `content_security_policy_script`
  preset to `nonce`.
- `playwright.config.ts`: default `baseURL` is `http://localhost:8080`,
  overridable via `E2E_BASE_URL`.
- `tests/csp.spec.ts`: six tests (one currently `test.fixme` for report-only
  mode, pending tranche D).

## Running locally

Requirements: Docker (with Compose v2) and Node.js 18+.

```bash
cd tests/e2e

# Bring up the stack. First run builds the PHP image (~90s).
docker compose up -d --build

# Wait for nginx to serve. The compose healthcheck gates this, but curl
# gives a human-readable signal.
until curl -fsS http://localhost:8080/ >/dev/null; do sleep 2; done

# Install Playwright and the browser, then run the suite.
npm ci
npm run install-browsers      # chromium + OS deps, once per machine
npm test

# Tear down. The -v flag drops the MariaDB volume so the next `up` starts
# from a clean schema.
docker compose down -v
```

Results land in `tests/e2e/playwright-report/`. The HTML report is written
but not auto-opened: run `npx playwright show-report` to view it.

## What the stack does at boot

1. MariaDB seeds `/docker-entrypoint-initdb.d/01-schema.sql` (the repo's
   `cacti.sql`) on first start.
2. The PHP container's entrypoint waits for MariaDB, creates
   `include/config.php` from the `.dist` template with the compose-network
   credentials injected, marks `version` as installed (the stock dump ships
   `new_install` which otherwise triggers the web wizard), writes
   `settings.content_security_policy_script='nonce'`, and clears the
   default admin's `must_change_password` flag so `admin/admin` works.
3. nginx proxies `*.php` to `php:9000` and serves static files directly
   from the repo mount.

## Environment overrides

- `E2E_BASE_URL` - where Playwright points. Default `http://localhost:8080`.
- `CACTI_CSP_MODE` (compose env on the `php` service) - value written to
  `settings.content_security_policy_script`. Default `nonce`. Set to
  `nonce-report` when you need the report-only path.

## CI

Wired into `.github/workflows/csp-e2e.yml` as the `e2e` job. The job
builds the compose stack, waits for `/` to return 200, runs `npx playwright
test`, and uploads `playwright-report/` + `test-results/` as artifacts on
failure.
