# ATR-004: Upgrade Compatibility Assurance

**Status:** Proposed

**Date:** 2026-07-13

## Context

Cacti deployments are long-lived and upgrades carry database schema, plugin,
configuration, and operational compatibility risk.

## Decision

Maintain versioned upgrade fixtures for each supported predecessor. Exercise
upgrade migrations in CI against an explicitly supported database version,
then verify idempotence, rollback guidance, configuration preservation, and a
basic post-upgrade health check. Publish compatibility windows and breaking
change notes with each release.

## Consequences

Upgrade safety becomes an executable contract rather than release-note intent.
Fixtures require maintenance and only represent supported predecessor versions.

## Review triggers

Review when support windows, schema migration tooling, or plugin compatibility
policy changes.
