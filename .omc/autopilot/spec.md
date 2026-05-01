# E2E Docker tests for PR #7054 feedback

## Goal

Reproducible end-to-end harness that exercises the four behaviours TheWitness flagged on PR #7054, against a fresh Cacti install running in Docker. Each test must fail loudly when the corresponding regression is present and pass when the code is correct.

## Branch under test

`feat/security-architecture-1.2.x` at the current tip. The harness lives under `tests/e2e/docker/` and must work without modifying any application code outside `tests/`.

## Topology

Three services in `tests/e2e/docker/docker-compose.yml`:

1. **`cacti-master`** — Cacti web UI + master poller. PHP 8.2 + Apache. The repository is mounted at `/var/www/html` so the harness sees the live source on whichever branch is checked out. A scratch volume holds `include/config.php`, RRDs, and logs.
2. **`cacti-poller`** — secondary remote poller. Same image. Generates a self-signed cert at startup, terminates HTTPS via Apache, points its `poller.hostname` at this container's name on the docker network.
3. **`cacti-db`** — MariaDB. Both Cacti containers connect.

A `setup.sh` script seeds the database (drop + import `cacti.sql`), runs the install CLI silently, creates an admin user with a known password, and registers the secondary as a remote poller.

## Tests

Each test gets its own file under `tests/e2e/docker/tests/`. The orchestrator script `tests/e2e/docker/run.sh` brings the stack up, waits for readiness, runs every test in sequence, tears the stack down, and exits with the worst test's exit code.

### 01-form-description-html

Asserts: `lib/html_form.php` renders `$field_array['description']` strings (which legitimately contain `<strong>`, `<br>`, `<i>`) as HTML elements rather than escaped literal text. Regression: PR #7054 commit `50d5fe3dd` wrapped the value in `html_escape()`, breaking the visual layout TheWitness screenshotted.

How: authenticate as admin, GET `settings.php?tab=path` (and one or two more setting tabs whose framework strings contain literal `<strong>` and `<br>`), grep the response body for the expected raw HTML markers. Fail if the body shows `&lt;strong&gt;` or `&amp;lt;strong&amp;gt;`.

### 02-self-signed-tls

Asserts: the master can fetch from a remote poller that serves an HTTPS endpoint backed by a self-signed certificate. Regression: a `CURLOPT_SSL_VERIFYPEER=true` / `verify_peer => true` default in any remote-fetch helper breaks operators (TheWitness's reported "self-signed certs").

How: the poller container generates a self-signed cert at startup. The master is told the poller's URL is `https://cacti-poller/`. The test calls a helper PHP CLI script (`tests/e2e/docker/probes/probe_remote_fetch.php`) that exercises the same code path the UI uses for cross-poller calls and asserts the response is the expected page body. Fail if any TLS verification error appears in the cacti log or the script returns empty.

### 03-session-persistence

Asserts: after `cacti_auth_transition()` runs at login, the user can navigate to ~5 realm-protected pages without being logged out and without losing permissions. Regression: `session_regenerate_id(true)` plus aggressive remember-me cookie rotation kicks the user back to the login page.

How: Playwright spec. Log in as admin via the web form. Visit `index.php`, `host.php`, `data_sources.php`, `graphs.php`, `user_admin.php` in sequence. After each navigation, assert (a) no redirect to `auth_login.php`, (b) the page contains an admin-only element. Fail on any redirect or any 403.

### 04-realm-enforcement

Asserts: `is_realm_allowed()` is the gate every protected page consults. There must be no parallel realm-check function that some pages use instead. Reframed as a runtime check: a low-privilege user must be denied on admin-realm pages, AND a static grep must confirm no second realm-helper is defined.

How: two parts.
- Runtime: create a non-admin user with no realm grants. Log in. Hit `user_admin.php`, `settings.php`, `host.php`. Assert each returns 403 or redirects to a permission-denied template.
- Static: grep the source for `function is_realm_*` and confirm only `is_realm_allowed()` is defined (not `realm_allowed`, `cacti_realm_check`, `dispatch_realm`, etc.). The previous `lib/cacti_dispatch.php` is already gone (reverted in `f368a0b69`); this is a guardrail against re-introduction.

## Out of scope

- Plugin-realm interactions, multi-tenant setups, LDAP auth flows, or anything beyond the four PR #7054 items.
- Performance benchmarks. The harness aims for correctness, not throughput.
- CI integration. The harness must be runnable locally via `tests/e2e/docker/run.sh`; wiring it into GitHub Actions is a follow-up.

## Deliverables

```
tests/e2e/docker/
├── README.md
├── docker-compose.yml
├── Dockerfile.cacti
├── setup.sh
├── run.sh
├── probes/
│   └── probe_remote_fetch.php
├── tests/
│   ├── 01-form-description-html.sh
│   ├── 02-self-signed-tls.sh
│   ├── 03-session-persistence.spec.js
│   └── 04-realm-enforcement.sh
└── playwright.config.js
```

## Acceptance

1. `tests/e2e/docker/run.sh` exits 0 on a clean checkout where the four behaviours are correct.
2. Each test individually fails (non-zero) when its targeted regression is reintroduced.
3. The harness leaves no stray containers, volumes, or networks behind on success or failure (use `--volumes --remove-orphans` on teardown).
4. Documentation in `tests/e2e/docker/README.md` explains how to run the harness locally and how to interpret each test's output.
