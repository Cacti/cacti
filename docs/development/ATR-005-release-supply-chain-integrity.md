# ATR-005: Release Supply-chain Integrity

**Status:** Proposed

**Date:** 2026-07-13

## Context

Release assets must be traceable to reviewed source and protected automation.

## Decision

Create releases only from protected, signed version tags. Build archives in
GitHub Actions from the tag, publish SHA-256 checksums and build provenance,
and use least-privilege OIDC identity instead of long-lived publishing tokens.
Record dependency and security exceptions in release notes.

## Consequences

Consumers can verify origin and integrity. Emergency releases require a
documented break-glass process rather than a maintainer workstation upload.

## Review triggers

Review after a release incident, publisher change, or artifact-distribution
change.
