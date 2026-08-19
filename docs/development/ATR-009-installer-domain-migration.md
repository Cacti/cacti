# ATR-009: Installer Domain Migration

**Status:** Proposed

**Date:** 2026-07-13

## Context

The historic installer is a single class that mixes HTTP-facing decisions,
settings-table access, database mutations, process calls, and platform
assumptions. That coupling makes platform support and test isolation difficult.

## Decision

Migrate the installer as a bounded context. Keep business state, plans, and
invariants in `Domain`; coordinate execution through `Application` ports; and
put Cacti globals, persistence, processes, and operating-system detection in
`Infrastructure`. Preserve legacy integer mode and operation contracts only in
anti-corruption adapters. New installer code uses typed PHP 8.1 constructs and
is analysed at PHPStan level 8 with strict rules.

## Consequences

The migration can proceed incrementally while preserving legacy entry points.
There is temporary adapter code and two models during transition. Domain and
application code must not import legacy Cacti functions or infrastructure
types.

## Review triggers

Review when a web, CLI, or background entry point moves to the application
facade, when a new installer operation is needed, or when the legacy class can
be removed.
