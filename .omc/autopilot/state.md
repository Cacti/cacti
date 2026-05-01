# Autopilot state: CSP nonce migration

## Status: AUTOPILOT_COMPLETE

## Branch
`security/csp-nonce-migration` (off `feat/security-architecture-1.2.x`)

## Commits
- aea648894 style(csp): drop em dashes, register report-uri setting, prune dead test helper
- 4afaddfff security(csp): close three review blockers before merge
- 379351f57 test(csp): integration fixtures, Playwright scaffolding, CI workflow
- 9114d9326 security(csp): complete nonce-mode wiring and pilot page migration
- 2eef313ad security(csp): nonce mode behind config flag; pilot migration

## Phase results
- EXPANSION_COMPLETE: requirements + spec saved to .omc/autopilot/
- PLANNING_COMPLETE: spec embedded in requirements.md + spec.md
- EXECUTION_COMPLETE: 16 files changed (+1268/-36 lines)
- QA_COMPLETE: php -l clean; runtime smoke tests pass; all three blockers patched
- VALIDATION: 3 architects reviewed
  - Functional (medium): APPROVE
  - Security (high): MERGE-WITH-GUARDS → all 3 blockers fixed
  - Code quality (standard): REQUEST-CHANGES → all 4 blockers fixed

## What shipped
- CactiSecureHeaders extended with getCspMode, isNonceMode, buildCspPolicy, sanitizeCspSources
- Four-value feature flag (content_security_policy_script)
- New config option content_security_report_uri
- Nonce: 18 bytes base64url (24 chars, 144 bits entropy)
- Report endpoint lib/csp_report_endpoint.php with CR/LF/control-byte scrubber
- Pilot migration: logout.php and permission_denied.php
- Unit tests (Pest): HeadersSecureTest.php (16 tests), CspReportEndpointTest.php (5 tests)
- Integration tests: ContentSecurityPolicyTest.php (8 tests) with php -S fixture
- Playwright E2E scaffold: 6 tests skipped pending docker stack (tranche C)
- CI: .github/workflows/csp-e2e.yml with unit + integration jobs (e2e deferred)
- Docs: docs/security-headers.md expanded with nonce mode section

## What's deferred (tranche C, next session)
- docker-compose stack for E2E (MariaDB + PHP-FPM + nginx + Cacti install)
- E2E test.describe.skip wrapper flipped on
- Plugin author advisory / migration guide

## Rollback
Single flag flip: set content_security_policy_script back to empty via settings UI.
