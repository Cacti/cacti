# ATR-002: Composer as the Cacti Developer Platform

**Status:** Accepted

**Date:** 2026-07-13

## Context

Cacti has an established Composer runtime dependency graph under
`include/vendor`. Modernising the installer and CI requires repeatable PHPUnit
and PHPStan tooling without replacing that dependency model or creating a
second vendor directory.

## Decision

The root `composer.json` remains the authoritative runtime and developer
platform manifest.

- `require` continues to declare Cacti runtime dependencies and extensions.
- `require-dev` contains test, code-quality, and static-analysis tools.
- `composer.lock` is committed and is the sole CI installation source.
- The lock and developer-tool jobs target Cacti's PHP 8.1 baseline; runtime
  compatibility with newer PHP versions is validated separately from the
  locked developer toolchain.
- New bounded contexts may opt into PHPStan level 8 with strict rules.
- Strict-rules activation is deferred to each bounded-context configuration;
  the installer DDD foundation is the first to include
  `phpstan-strict-rules/rules.neon`, while the shared level 6 script remains
  unchanged.
- Composer scripts are the stable local and CI interface, beginning with
  `composer:validate`, `security:audit`, and `platform:check`.

## Consequences

Developers and CI execute the same locked toolchain. Dependency security is
auditable locally and in CI. Legacy code does not implicitly claim new PHPStan
or PHPUnit conventions; each migrated bounded context opts in without lowering
the shared standard.

## Non-decisions

This ATR does not introduce a new web framework, replace Cacti's runtime
dependency graph, or add a `config.platform` override. A fake platform value
would hide deployment compatibility errors.

## Review triggers

Review this record when changing the supported PHP range, adding a runtime
package, broadening static analysis beyond a migrated context, or changing the
test execution model.
