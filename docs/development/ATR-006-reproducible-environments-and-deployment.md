# ATR-006: Reproducible Environments and Deployment

**Status:** Proposed

**Date:** 2026-07-13

## Context

Production defects often arise from drift between developer, CI, and deployed
PHP extensions, operating-system packages, or dependency versions.

## Decision

Treat `composer.lock` as the PHP dependency contract. Provide maintained
container or provisioning definitions for supported deployment profiles,
declare required PHP extensions and external binaries, and validate them with
an environment health command before deployment.

## Consequences

Support expectations become reproducible and diagnosable. Deployment profiles
must be maintained as supported platforms evolve.

## Review triggers

Review when PHP support, base images, required extensions, or deployment
topology changes.
