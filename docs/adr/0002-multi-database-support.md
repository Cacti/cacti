# 0002 - Multi-database support strategy

- Status: Proposed
- Date: 2026-07-12
- Deciders: Cacti maintainers

## Context

Cacti supports MariaDB/MySQL only. Requests for PostgreSQL recur (forum
thread t=62859) and upstream has declined them, citing maintenance cost.

The connection layer is already driver-neutral. `db_connect_real()` in
`lib/database.php` accepts `$db_type` (default `mysql`) and feeds it to the
PDO DSN, and the `db_fetch_*`/`db_execute_*` wrappers sit on portable PDO
calls. The cost of a second backend is not the driver. It is the SQL dialect:
upsert semantics (`ON DUPLICATE KEY UPDATE`), DDL (backticks, `unsigned`,
`AUTO_INCREMENT`, `ENGINE=`), retry logic keyed on numeric MySQL error codes,
and MySQL-only introspection (`SELECT DATABASE()`, `SHOW GLOBAL VARIABLES`,
`information_schema.processlist`). MySQL-isms appear in ~155 PHP files and
throughout the ~3,557-line `cacti.sql`.

## Decision

Introduce a dialect-abstraction layer behind the existing `db_*` API.
Call-site signatures do not change. The layer owns identifier quoting,
upsert rendering, `LIMIT`/`OFFSET`, type mapping, auto-increment vs identity,
introspection queries, and error-code classification. Driver is chosen from
`$db_type` at connect time.

MySQL/MariaDB stays tier-1: the default backend and the gate for merges.
PostgreSQL is added as opt-in tier-2. New code uses standard SQL; unavoidable
MySQL-isms route through helpers. Existing raw SQL migrates incrementally,
hottest paths first. Plugin-authored MySQL SQL is out of scope.

## Considered options

1. **Dialect-abstraction layer (chosen).** Small surface, keeps the `db_*`
   API, migrates incrementally, no rewrite. Cost: touches many call sites
   over time and adds a second CI backend.
2. **Full ORM (Doctrine/Eloquent).** Portable, but a rewrite of the entire
   data layer, a heavy new dependency, and a performance risk for the poller.
   Rejected.
3. **Status quo, MySQL only.** Zero cost, no portability. The current state.
4. **Query-builder layer.** Lighter than an ORM but still rewrites call
   sites into a fluent API for little gain over targeted helpers. Rejected.

## Consequences

Positive: PostgreSQL becomes reachable without a rewrite; new code trends
toward portable SQL; error handling stops depending on raw MySQL codes.

Negative: a second CI backend to maintain; incremental migration leaves a
period where some paths are MySQL-only; contributors learn the helper API;
PostgreSQL ships with known gaps until coverage completes.

## Testing strategy

Test-first. Dialect conformance tests assert that one logical operation
renders the correct SQL per dialect (upsert, quoting, `LIMIT`/`OFFSET`, type
mapping), written before implementation. A schema round-trip test generates
DDL from the table-definition arrays, applies it to both backends, and reads
the columns back. CI runs the existing Pest suite against MySQL and
PostgreSQL; MySQL remains the gating backend.
