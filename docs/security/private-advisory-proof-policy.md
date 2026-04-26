# Private Advisory Proof Policy

## Purpose

This policy defines the minimum evidence required to claim a private security advisory is fully resolved on a branch.

The goal is architectural closure:

- Prefer shared guardrails at trust boundaries.
- Fix entire exploit classes across call sites.
- Use surgical endpoint-only patches only when a shared control is not feasible.

## Required Closure Evidence

An advisory is considered fully proven only when all of the following are present on the target branch:

1. `Fix implementation`
   - A commit that introduces or extends a reusable control at the boundary where the exploit class enters.
   - If a narrow patch is used, include rationale for why a shared control was not possible.

2. `Exploit regression coverage`
   - At least one negative test proving malicious input is rejected or neutralized.
   - At least one positive test proving expected behavior remains intact.
   - Tests should run in normal CI (unit/integration/e2e as appropriate).

3. `Operational proof`
   - Branch evidence for safe runtime behavior (for example: install, poller, recursive page checks, or equivalent integration checks).
   - No unexpected errors in application logs for covered paths.

4. `Cross-call-site containment`
   - Review and cover other call sites with the same sink pattern.
   - Add/extend shared helper usage or central validation so future call sites inherit protection.
   - Track helper adoption and hotspot drift using architectural helper reports.

## Proof Status Levels

- `PROVEN_TEST_BACKED`: fix + regression tests + branch evidence.
- `PROVEN_COMMIT_LINKED`: fix commit and direct code evidence, but test coverage is incomplete.
- `PARTIAL_REFERENCE`: partial branch evidence exists, but closure is incomplete.
- `NO_EVIDENCE`: no branch evidence found.

Production readiness requires no unresolved advisories at `PARTIAL_REFERENCE` or `NO_EVIDENCE`.

## Branch-First Closure Order

Use this order for closure:

1. Resolve and prove on `1.2.x` first.
2. Port controls and tests to `develop`.
3. Re-run proof checks on both branches.

This keeps compatibility and behavior aligned while preserving newer branch hardening.

## Evidence Freeze

For every closure pass:

1. Generate matrix and call-site reports.
2. Freeze artifacts with timestamped output.
3. Upload artifacts in CI for auditability.

Use:

- `tests/security/build_private_advisory_matrix.sh`
- `tests/security/verify_private_advisory_matrix.sh`
- `tests/security/freeze_private_advisory_evidence.sh`
- `tests/security/build_sink_inventory.sh`
- `tests/security/verify_sink_inventory.sh`
- `tests/security/build_architectural_helper_report.sh`
- `tests/security/verify_architectural_hotspots.sh`
