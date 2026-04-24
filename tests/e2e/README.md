# Cacti E2E Tests (Playwright)

Browser-level checks for the CSP nonce pilot. Six tests split across header
shape, pilot-page nonce match, and browser behavior per mode.

## What this directory ships

- `docker-compose.yml` + `Dockerfile` + `entrypoint.sh` + `nginx.conf`:
  a three-service stack (MariaDB 10.11, PHP 7.4-FPM, nginx) that serves
  Cacti with `content_security_policy_script` preset via
  `CACTI_CSP_MODE` env (default `nonce-report`).
- `docker-compose.enforce.yml`: overlay that flips `CACTI_CSP_MODE` to
  `nonce` (enforcing). Use for asserting the blocking path.
- `playwright.config.ts`: default `baseURL` is `http://localhost:8080`,
  overridable via `E2E_BASE_URL`.
- `tests/csp.spec.ts`: six tests covering both modes via `E2E_CSP_ENFORCE`.

## Running locally

Requirements: Docker (with Compose v2) and Node.js 18+.

### Report-only mode (default, realistic rollout posture)

```bash
cd tests/e2e

# First run builds the PHP image (~90s).
docker compose up -d --build

# Wait for the stack; compose has a healthcheck but this is the
# human-readable signal.
until curl -fsS http://localhost:8080/ >/dev/null; do sleep 2; done

# Install Playwright and run the suite. npm install (not npm ci) because
# package-lock.json is gitignored under tests/e2e/.
npm install
npm run install-browsers   # chromium + OS deps, once per machine
npm test

docker compose down -v
```

### Enforce mode (blocking)

Stop the report-only stack first, then compose with the overlay:

```bash
docker compose down -v

docker compose -f docker-compose.yml -f docker-compose.enforce.yml up -d --build
until curl -fsS http://localhost:8080/ >/dev/null; do sleep 2; done

E2E_CSP_ENFORCE=1 npm test
```

The `E2E_CSP_ENFORCE=1` flag tells the spec to look for
`Content-Security-Policy` (enforcing) instead of
`Content-Security-Policy-Report-Only`, and to assert that un-migrated
inline scripts are visibly blocked in the browser console.

## Port collision workaround

If `8080` is already bound locally (OrbStack pre-binds it on macOS,
for instance), override the host port:

```bash
HOST_PORT=8090 docker compose up -d
E2E_BASE_URL=http://localhost:8090 npm test
```

## What the stack does at boot

1. MariaDB seeds `/docker-entrypoint-initdb.d/01-schema.sql` (the repo's
   `cacti.sql`) on first start.
2. The PHP entrypoint waits for MariaDB, writes `include/config.php`
   from the `.dist` template with compose-network credentials and
   `url_path='/'`, marks `version` as installed (the stock dump ships
   `new_install` which otherwise triggers the web wizard), writes
   `settings.content_security_policy_script=<CACTI_CSP_MODE>`, and
   clears the default admin's `must_change_password` flag so
   `admin/admin` works through the login form.
3. nginx proxies `*.php` to `php:9000` and serves static files directly
   from the repo mount with correct MIME types.

`CACTI_FORCE_CONFIG=1` (default in the shipped compose) overwrites an
existing `include/config.php` on every container start. Unset it if
you want the container to keep hand-edited credentials across
restarts.

## Environment overrides

| Variable | Scope | Effect |
|---|---|---|
| `E2E_BASE_URL` | Playwright | Where tests point. Default `http://localhost:8080`. |
| `E2E_CSP_ENFORCE` | Playwright | `1` tells tests to expect the enforcing header. |
| `HOST_PORT` | compose | Host port mapped to nginx:80. Default `8080`. |
| `CACTI_CSP_MODE` | php env | Value written to `content_security_policy_script`. |
| `CACTI_FORCE_CONFIG` | php env | `1` overwrites an existing `include/config.php`. |

## CI

Wired into `.github/workflows/csp-e2e.yml` as the `e2e` job. Runs the
report-only path; enforce-mode is a manual dev check.
