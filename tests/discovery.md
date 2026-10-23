# Discovery regression checks

These tests exercise network discovery through production PHP functions,
MariaDB queries, simulated SNMP agents and the browser. They protect device
matching, address enumeration and the handoff from network configuration to
worker processes and graph creation.

## Native checks

Provide a disposable local MariaDB database through `CACTI_MATCH_DB_DSN`,
`CACTI_MATCH_DB_USER` and `CACTI_MATCH_DB_PASSWORD`. The database probes create
connection-local temporary tables from the branch's schema. Do not use a
production database. Without the DSN, the database tests skip; the dedicated
CI job supplies it and rejects skipped or empty test reports.

On develop, install the locked root Composer dependencies and run:

```sh
php include/vendor/bin/pest --configuration tests/phpunit-discovery.xml
```

On 1.2.x, use its existing test dependency sandbox:

```sh
cd tests
composer install --no-interaction --prefer-dist
php vendor/bin/pest --configuration phpunit-discovery.xml
```

Use the PHP versions supported by the checked-out branch. The discovery
suite does not require a running Cacti installation or a real device.

| Component | Regression checks |
| --- | --- |
| Identification | Missing required fields, nullable criteria, misleading evidence, sequence, unchanged matching operators and unknown devices, using actual MariaDB SQL |
| Range parsing | IPv4 addresses, host routes, CIDR, dotted masks, trailing wildcards, malformed prefixes, unsupported IPv6 input and octet carries |
| Priming | Overlapping ranges, unique targets, repeated calls and preservation of existing job ownership, using the actual MEMORY-table schema |
| SNMP forms | Reentrant change notifications, exception cleanup, credential retention across version changes and security-level field visibility |
| Scheduling | Existing calendar and year-rollover tests execute the branch-specific production scheduling functions |
| Develop graph criteria | Creation and updates retain the validated item identity and existing hashes through the actual save and hash functions |
| Maintenance form bindings | Generated handlers retain their nonce and run after layout initialization; the browser verifies working controls |
| Workers | Valid `.255` hosts, managed and deleted devices, duplicate system names and cancellation before address claims |
| Develop exclusions | First, middle, last and all addresses excluded; bounded inserts and no empty batches |
| Develop launch options | Force, debug and dry-run combinations for network masters and workers, including paths containing spaces |
| Develop SNMP probes | Credential order, v1/v2c/v3 parameters, failure and recovery, partial metadata, missing objects, zero uptime and session cleanup |

Transport adapters in native probes do not establish network behavior. The
browser suite supplies that additional evidence for its simulated agents.

## Browser checks

With Docker, the Compose plugin and the Node version used by the repository,
run from the repository root:

```sh
tests/e2e/discovery/run.sh
```

The runner creates a private application/database/SNMP stack and removes its
own containers and volumes on exit. It rejects unrelated Compose project
names and unexpected scan targets. It checks that the application files in
the container match the working tree. No local Cacti configuration is mounted
writable into this stack.

The SNMP form checks also run with `node --test tests/js/snmp_controls.test.js`.
They supplement browser coverage of the real Select2 controls.

The browser creates ordinary discovery configuration through the actual UI.
The tests inspect persisted device, graph and data-source records after CLI
master and worker processes finish. They check known, unknown and partial
SNMP agents, selective interface graphs, repeated scans and SNMPv3 collection
on a valid `.255` host. Both branches test denied automation access for an
authenticated user and manual addition of an unmatched device. Develop
additionally exercises exclusions, propagation of dry-run mode and cancellation
through the UI.

Reports must contain executed tests. An empty report, skipped required
scenario, failed bootstrap or timeout is a failed verification. Native tests
and browser results are separate evidence; neither substitutes for the other.

## Compatibility and limits

The fixes preserve the existing matching operators, rule sequence,
administrator controls, template identities and poller protocols. An unknown
device remains available for manual handling unless an administrator has
configured an applicable fallback rule.

Nontrailing wildcards such as `10.1.*.1` are now rejected instead of silently
broadening the scan. Use an explicit supported IPv4 range or individual
addresses. Decimal/exponent prefixes and extra slash segments are rejected.
IPv6 and `/31` discovery are not added by this patch. In particular, helper
results for `/31` must not be interpreted as a complete scan guarantee.

Simulated agents do not qualify vendor hardware or firmware. These tests do
not establish production-scale throughput, exhaustive branch coverage,
remote-collector failover or recovery from every database and process failure.

---

Copyright (c) 2004-2026 The Cacti Group
