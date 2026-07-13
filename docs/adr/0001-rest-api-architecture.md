# 0001 - REST API architecture and framework

- Status: Proposed
- Date: 2026-07-12
- Deciders: Cacti maintainers

## Context

The `api/` scaffold exposes Cacti data over Slim 4 with no authentication and full
error details on the wire (`api/public/index.php`). `api/README.md` marks it not
production ready. A prior advisory covered an unauthenticated REST surface leaking
data. Cacti already has an identity model: session users, realms via
`is_realm_allowed()`, and the `user_auth_realm` / `user_auth_group_realm` tables. The
remember-me path stores hashed tokens in `user_auth_cache`. The root `composer.json`
already carries `slim/slim ^4.14`, `symfony/http-foundation ^6.0`, and `psr/log ^1.1`,
so no new framework dependency is required to proceed. We need an architecture that is
secure by default and testable.

## Decision

Adopt Slim 4 with an OpenAPI 3.1 spec-first contract and token authentication that is
deny-by-default.

- Slim 4 stays. It is already a dependency and matches the scaffold.
- The OpenAPI 3.1 spec is the source of truth. Request and response validation run
  against it via PSR-15 middleware.
- Authentication uses user-bound, scoped, revocable API tokens sent as
  `Authorization: Bearer`. Only a hash of each token is stored, mirroring
  `user_auth_cache`.
- Authorization is deny-by-default. Each route declares a required realm, checked
  against the existing realm model.
- Errors use RFC 9457 `application/problem+json`. Logging uses PSR-3.

## Considered options

**Framework**
- Slim 4 (chosen). Already vendored, small, PSR-7/15 native, low migration cost.
- Symfony API Platform. Richer, but a large new dependency and a heavier runtime than
  Cacti's current footprint warrants.
- Hand-rolled router. Rejected. Reinvents middleware, validation, and error handling.

**Spec approach**
- Spec-first OpenAPI 3.1 (chosen). Contract is fixed before code; validation and
  client generation derive from one artifact.
- Code-first with generated docs. Rejected. Docs drift from behavior and cannot gate
  responses in tests.

## Consequences

Positive
- Anonymous access to data routes is impossible by construction.
- The spec gates both requests and responses, so drift is a test failure.
- Token model reuses an existing hashed-store pattern and the realm system.

Negative
- Spec-first adds authoring overhead for each endpoint.
- Retrofitting auth touches every route and the middleware order.
- Validation middleware adds per-request cost.

## Testing strategy

- Contract tests generated from the OpenAPI spec assert every response against its
  schema.
- Auth tests: `401` for no or revoked token, `403` for missing realm, rejection of
  tokens passed in query strings.
- Characterization tests pin current payload shapes from `api/include/db_functions.php`
  so the auth retrofit does not alter output.
- Tests use Pest under `tests/Unit`, consistent with `tests/Pest.php`, and run before
  implementation.
