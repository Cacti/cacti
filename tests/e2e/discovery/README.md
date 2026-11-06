# Discovery integration tests

Run from the repository root with Docker Compose 2.24.4 or later and Node 22:

```sh
mise exec node@22 -- tests/e2e/discovery/run.sh
```

The runner builds the existing Cacti browser-test image, creates a disposable
MariaDB database and private SNMP agents, imports the bundled Generic SNMP
Device package, and signs in through the normal login form. It binds the web
server to `127.0.0.1:18093`. The Docker project is `cacti-discovery-review`;
CI uses `cacti-discovery-ci`. Keep `172.30.252.0/23` available for this private
Docker network. The runner removes its containers and database on exit.
All account passwords and SNMP credentials in this directory are test fixtures.

Three browser scenarios exercise:

- Credential, identification, graph-rule and network configuration through
  the application forms, followed by CLI master and child discovery. A bad
  community precedes the working credentials. One agent requires SNMPv3
  SHA/AES authentication and privacy. Its `.255` address is inside a `/23`.
- Dry runs, explicit exclusions, an unknown description, a restricted system-only
  SNMP view, selective interface graphs and repeated scans without duplicate
  hosts, graphs or data sources. The unknown device is then added through an
  explicit manual device-template selection.
- Discover Now and cancellation through the UI, plus authenticated realm-denied
  reads, saves and discovery launches using a valid CSRF token.

The CLI observer checks the actual persisted state. The runner verifies that
its container uses the current discovery implementation before starting the
browser. JUnit output and Playwright failure evidence go under
`tests/e2e/test-results/`. Failed container logs go to
`/tmp/cacti-discovery-review-failure.log`.

These scenarios verify discovery and graph creation against Net-SNMP fixture
agents. Device firmware, remote collectors, Spine polling and RRD updates require
separate validation. The dedicated native Discovery suite also exercises the
matcher against a disposable MariaDB connection in CI.
