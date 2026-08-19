# ATR-007: Operational Resilience and Observability

**Status:** Proposed

**Date:** 2026-07-13

## Context

An installer and polling application needs operators to distinguish transient
failure from partial state and to recover safely.

## Decision

Use structured, correlation-aware application logs; expose authenticated
health and readiness checks; and define alerts around installation failures,
poller backlog, database connectivity, and scheduler liveness. Make repair and
rollback procedures idempotent, documented, and tested during incident review.

## Consequences

Detection and recovery improve, while telemetry design must avoid leaking
credentials, customer topology, or sensitive configuration.

## Review triggers

Review after incidents, major observability-platform changes, or new
availability objectives.
