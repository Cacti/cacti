# Cacti 1.2 discovery acceptance

This suite exercises the branch's discovery forms, master and worker processes,
Net-SNMP agents, device templates and interface graph automation. It uses an
independent Apache PHP 8.3 image, MariaDB 10.11 and five agents on a private
Docker `/23`. Composer installs the branch lock and checks platform
requirements;
the image builds the branch's managed JavaScript with its NPM lock.

Run from the repository root with Docker Compose and mise available:

```sh
mise exec node@22 -- bash tests/e2e/discovery/run.sh
```

The default project is `cacti-discovery-lts-review`, exposed only on
`127.0.0.1:18094`. It uses `172.30.250.0/23`; choose another isolated project
and
port through `DISCOVERY_COMPOSE_PROJECT` and `HOST_PORT` if needed. Do not run
against an existing Cacti installation. The guarded seed removes the shipped
example discovery ranges from this disposable database before forced scans.
Only the fixture directory is mounted into Apache, read-only. Application
source is copied into the image and checked against its host SHA-256 hashes.
Cleanup removes only this project's containers and volumes.

The three browser scenarios cover normal configuration and repeated discovery,
explicit manual addition of an unmatched device, and denied automation access
for an authenticated account with graph-viewing permission. The known switch
uses an interface rule selecting `eth0`; the partial-view agent exposes system
identification without interfaces. The `.255` agent uses SNMPv3 authPriv and is
a valid host within the Docker `/23`. Another agent denies `sysDescr` while
exposing `sysObjectID` and `sysName`. The observed Cacti discovery row stores
that unavailable description as an empty string, and the configured description
rule does not select a template for it. Native SQL tests separately reproduce
the missing-field defect before the matcher correction.

Cacti 1.2 has no discovery `--dryrun` or excluded-address form in this branch.
This suite does not pretend to exercise those development-branch controls.
The task schema is seeded directly, so this is not an installer acceptance test.
No production device or physical hardware is used.

JUnit is written to `tests/e2e/test-results/discovery.xml`; the runner requires
three executed tests with no skips. Playwright keeps failure traces, screenshots
and videos in its test-results directory. Container failure logs are saved
under `/tmp/<project>-failure.log`.

---

Copyright (c) 2004-2026 The Cacti Group
