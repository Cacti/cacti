# ATR-008: Federated Deployment Identity

**Status:** Proposed

**Date:** 2026-07-13

## Context

Long-lived credentials in CI or deployment configuration are difficult to
rotate, over-broad, and hard to attribute.

## Decision

Use GitHub Actions OIDC federation for cloud and package-publishing access.
Trust policies must bind repository, branch or protected environment, workflow,
and audience. Permissions are short-lived, scoped to one deployment action,
and independently auditable. Keep a time-boxed break-glass path with review.

## Consequences

Credential rotation and blast radius improve. Each target provider needs a
maintained trust policy and deployment environments need protection rules.

## Review triggers

Review after an identity incident, provider migration, or changes to protected
deployment environments.
