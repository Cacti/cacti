# ATR-003: Repository Governance and Merge Controls

**Status:** Proposed

**Date:** 2026-07-13

## Context

A mature PHP application needs consistent review evidence without making every
maintainer a release or security administrator.

## Decision

Protect `develop` and supported release branches. Require a passing quality
check, review by a CODEOWNERS-aligned maintainer for sensitive paths, resolved
threads, and a current branch before merge. Use a merge queue when concurrent
merge pressure makes post-approval rebases common. Restrict release creation,
branch-protection changes, and secret administration to separate teams.

## Consequences

The repository gets a repeatable merge boundary and auditable authority. Small
documentation-only changes may take longer; rules should therefore be limited
to controls with a clear failure mode.

## Review triggers

Review after a release incident, maintainer-team change, or sustained merge
queue delay.
