# ATR-001: Enterprise CI Architecture

**Status:** Accepted

**Date:** 2026-07-13

## Context

Cacti's workflows combined fast checks, long-running installation work, and
issue housekeeping. That made pull-request feedback slow, duplicated setup,
and gave routine jobs more permissions than required.

## Decision

CI is split by responsibility:

| Workflow | Responsibility |
| --- | --- |
| `quality.yml` | Dependency contract, syntax, lint, PHPStan, workflow lint |
| `test.yml` | Installer bounded-context PHPUnit and strict PHPStan |
| `e2e.yml` | Reusable, scheduled, and manual installation smoke test |
| `security.yml` | CodeQL and pull-request dependency review |
| `release.yml` | Tag-only archive, checksum, provenance, and release |
| `maintenance.yml` | Conservative stale and lock lifecycle |

Every job has minimum permissions, a timeout, and concurrency appropriate to
its purpose. Composer cache keys derive from the committed lock file.

## Support policy

The required development lane is PHP 8.4. The integration lane uses MySQL 8.4.
Compatibility expansion requires a documented support decision and a passing
suite; it is not inferred from a runner image.

## Issue and pull-request lifecycle

Issues become stale after 120 inactive days and close 30 days later. Pull
requests become stale after 90 inactive days and close 30 days later. Security,
pinned, enhancement, and future-release work is exempt. Closed issues and pull
requests are locked only after one year of inactivity.

## Consequences

Required pull-request feedback is independent of scheduled integration work.
Workflow responsibility is visible from the file name, security authority is
scoped, and release artifacts are attestable. This creates a larger workflow
surface that must be reviewed whenever support policy or release practice
changes.
