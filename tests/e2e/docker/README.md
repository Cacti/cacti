# Cacti PR #7054 e2e harness

Reproducible Docker-based regression checks for the four behaviours TheWitness
flagged on PR #7054 (`feat/security-architecture-1.2.x`). The repo root is
bind-mounted into both Cacti containers, so every run exercises whatever code is
checked out.

## Layout

```
docker-compose.yml         master + remote poller + MariaDB
Dockerfile.cacti           PHP 8.2 + Apache + RRDtool + extensions
setup.sh                   idempotent DB seed + admin/lowpriv user
run.sh                     bring up, seed, run tests, tear down
probes/
  probe_remote_fetch.php   drives call_remote_data_collector() in-process
tests/
  01-form-description-html.sh    html_form.php description rendering
  02-self-signed-tls.sh          self-signed HTTPS poller fetch
  03-session-persistence.sh      cacti_auth_transition() does not bounce (curl-based)
  04-realm-enforcement.sh        is_realm_allowed() guardrail + runtime
```

## Run

```sh
cd tests/e2e/docker
./run.sh
```

The script brings the stack up, seeds the database, runs every test in
`tests/` in lexical order, and tears the stack down on exit. The final exit
code is the worst test exit code.

By default the master Cacti UI binds to `127.0.0.1:8088`. To use a different port, set `CACTI_E2E_PORT` before running: `CACTI_E2E_PORT=8090 ./run.sh`.

## Tests

| File | Asserts | Regression it catches |
| --- | --- | --- |
| `01-form-description-html.sh` | `settings.php?tab=path` body contains literal `<strong>` and not `&lt;strong&gt;`. | `lib/html_form.php` wrapping `$field_array['description']` in `html_escape()` (PR #7054 commit `50d5fe3dd`). |
| `02-self-signed-tls.sh` | `call_remote_data_collector(2, '/index.php')` returns a non-empty body and no TLS verification noise hits `log/cacti.log`. | A future re-introduction of `verify_peer => true` defaults that breaks self-signed remote pollers. |
| `03-session-persistence.sh` | After login via the same CSRF + index.php curl flow as test 01, `/index.php`, `/host.php`, `/data_sources.php`, `/graphs.php`, `/user_admin.php` all return < 400 and the admin layout marker is present. No Playwright dependency. | `cacti_auth_transition()` bouncing the user via `session_regenerate_id(true)` plus aggressive remember-me cookie rotation. |
| `04-realm-enforcement.sh` | (a) `grep -RnE 'function (cacti_realm_check\|realm_allowed\|dispatch_realm\|check_user_realm)\b'` matches nothing in `lib/` or `include/`. (b) The seeded `lowpriv` user is denied on `/user_admin.php`, `/settings.php`, `/host.php`. | A parallel realm-check helper being reintroduced (the earlier `lib/cacti_dispatch.php`) or admin pages skipping `is_realm_allowed()`. |

## Debugging a failure

- `KEEP_UP=1 ./run.sh` -- skips teardown so you can inspect the running stack.
- `docker compose -f tests/e2e/docker/docker-compose.yml logs cacti-master` --
  Apache + PHP error logs.
- `docker compose -f tests/e2e/docker/docker-compose.yml exec cacti-master tail
  -n 200 /var/www/html/log/cacti.log` -- Cacti's own log.
- `docker compose -f tests/e2e/docker/docker-compose.yml exec cacti-db mariadb
  -ucactiuser -pcactiuser cacti` -- direct DB shell.
- Playwright traces land in `tests/e2e/docker/test-results/` (retained on
  failure).

## Credentials seeded by `setup.sh`

| Username | Password | Realm grants |
| --- | --- | --- |
| `admin` | `cacti-e2e-admin` | full |
| `lowpriv` | `cacti-e2e-lowpriv` | none |

Passwords are hashed with `password_hash($pw, PASSWORD_DEFAULT)` inside the
master container so the algorithm matches the running PHP build.

## Constraints

- No application code is modified by the harness.
- Setup is idempotent: re-running `run.sh` drops and recreates the schema, and
  user/poller rows use `ON DUPLICATE KEY UPDATE`.
- Teardown uses `--volumes --remove-orphans` so nothing is left behind.
- All tests are curl-based shell scripts; no Node or Playwright dependency on the host or in CI.

## Current status (work in progress)

The infrastructure layer is solid: image build, network, DNS, healthchecks,
self-signed cert generation in the poller, and `cli/install_cacti.php` running
end-to-end through `setup.sh`. The four test scripts have varying levels of
maturity and are honest regression detectors at different fidelity levels.

| Test | Result | Notes |
| --- | --- | --- |
| 04 realm enforcement (static) | PASS | confirms `is_realm_allowed()` is the only realm helper defined in the tree |
| 04 realm enforcement (runtime) | LENIENT PASS | low-priv user fetch returns status=200 with no admin marker; the script accepts that as denied. A status=200 from a hostile redirect or noop page would also pass — tighten if higher confidence is needed. |
| 02 self-signed TLS | FAIL (correctly) | `get_default_contextoption()` on this branch returns SSL options that verify the peer cert, so `file_get_contents()` against the self-signed poller fails with `error:0A000086:SSL routines::certificate verify failed`. **This is the regression TheWitness reported.** Making it green requires an application-code fix (loosen `verify_peer` to opt-in or honor a setting). |
| 01 form description HTML | FAIL | `curl` round-trips through `auth_login.php` POST but the subsequent `settings.php?tab=path` fetch returns the login page rather than the settings UI; the cookie jar isn't carrying the authenticated session. Likely a missing form field (CSRF token, `action`, or similar) in the POST. |
| 03 session persistence | PASS-state TBD | Curl-based 5-page navigation loop; flips to FAIL if cacti_auth_transition destroys the session at login. Currently PASS-state TBD pending application of the stashed auth fix. |

## CSRF tokens

Cacti's csrf-magic middleware (include/csrf.php) rejects every POST that
does not carry a fresh `__csrf_magic` hidden field whose token contains
a sid hash matching the current PHP session and an ip hash matching the
client. Tests that drive a login over curl must GET /index.php first,
extract the `__csrf_magic` value from the form, and include it as a
form field in the subsequent POST. POSTing directly to
/auth_login.php is unsupported — that file only loads lib/ldap.php and
does not bring in global.php, so calling its set_default_action() at
line 29 fatal-errors. The canonical entry for the auth flow is
/index.php. Test 03 uses the same GET-then-POST CSRF flow as test 01.

## Known follow-up work

1. **Tests 01 + 03 login flow.** Inspect what fields `auth_login.php` POST
   actually requires on this branch (CSRF token, exact `submit` button name).
   Update the curl POST and the Playwright `page.click` selector to match.
2. **Test 02 expectation.** Decide whether the harness should treat
   `get_default_contextoption()`-strict-verify as a permanent FAIL marker
   (until app fix) or whether to add an `EXPECTED_FAIL` mechanism that lets the
   suite still exit 0 while the regression is on file.
3. **Test 04 runtime tightening.** Replace the `status=200 + no admin marker
   == denied` heuristic with a positive assertion against
   `permission_denied.php` markup or a 30x+`Location:` header pointing at
   `auth_login.php`.
4. **CI integration.** This README documents how to run locally; wiring into
   `.github/workflows/` is a follow-up.
