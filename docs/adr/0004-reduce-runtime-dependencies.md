<!-- SPDX-License-Identifier: GPL-2.0-or-later -->

# 0004 - Reduce runtime dependencies

- Status: Proposed
- Date: 2026-11-12
- Deciders: Cacti maintainers
- Related: [0003](0003-rendering-security-components.md) (rendering security)

## Context

At develop revision `cb2c788c445724edc5a9f8fe45a97e23e1d0ed2e`,
[composer.json](../../composer.json) requires 27 packages directly, and
[composer.lock](../../composer.lock) resolves them to 62 runtime packages. Twelve
direct requirements are Symfony components, and they bring most of the
transitive packages, including eight polyfills.

Several of those components have few or no callers in core. The counts below are
non-test PHP files outside `include/vendor` that reference the package:

| Package | Files | Use |
|---|---|---|
| `symfony/password-hasher` | 0 | none |
| `symfony/security-core` | 0 | required by `symfony/security-csrf` |
| `php-mqtt/client` | 0 | none |
| `mikepultz/netdns2` | 0 | none |
| `psr/log` | 0 | none as a direct requirement |
| `symfony/finder` | 2 | `CactiMd5FileFinder`, `cli/md5sum.php` |
| `symfony/http-foundation` | 1 | `IpUtils::checkIp()` in `lib/client_address.php` |
| `symfony/lock` | 1 | `CactiProcessLock` behind `register_process_start()` |
| `symfony/filesystem` | 4 | `CactiFilesystem` and manifest CLIs |
| `symfony/notifier` | 4 | email notifications in `lib/api_notification.php` |
| `symfony/console` | 3 | the `CactiApplication` CLI wrapper |
| `symfony/process` | 1 | `LegacyScriptCommand` |
| `symfony/security-csrf` | 2 | `CactiCsrfGuard` behind `include/csrf.php` |
| `symfony/validator` | 22 | `CactiValidator` and form definitions |
| `slim/slim` | 1 | the REST API entry point `api/public/index.php` |

Most of these have older native equivalents that core still contains:
`compat_password_hash()` and `compat_password_verify()`, the realm and
permission checks in `lib/auth.php`, `RecursiveDirectoryIterator` walks in
`rrdcleaner.php` and `lib/plugins.php`, the process-table lock used by
`api_tree_get_lock()`, `boost_atomic_write_cache()` and
`validate_relative_path_within()`, `mailer()`, `form_input_validate()` and
`get_filter_request_var()`, the `cli/` scripts' own argument parsing, and
`exec_with_timeout()`. In 1.2.x, `register_process_start()` and CSRF
(csrf-magic) are native.

Every dependency adds release packaging, audit and upgrade work. Symfony
publishes a new major version every two years, and Symfony 8.1 requires PHP 8.4,
while 1.3 targets PHP 8.3. Cacti can stay on the 7.4 LTS line, which receives
security fixes until November 2029, but the version floor will force a decision
during the 1.3 support period.

Plugins: the 41 plugin repositories in the Cacti organization were searched on
their default branch and, where one exists, their develop branch. None
references a `Symfony\` or `Slim\` class or the `Cacti*` wrapper classes above.
`plugin_stream` uses `php-mqtt/client` and `psr/log` from its own `vendor/`, and
`plugin_mactrack` bundles its own copy of the older `Net_DNS2` library. Plugins
do call core functions that sit on these packages: `csrf_check()`,
`csrf_get_tokens()` and `csrf_conf()` (9 plugins), `mailer()` and `send_mail()`
(13 plugins), and `get_client_addr()` (2 plugins). Plugins outside the
organization were not checked.

## Decision

Reduce the runtime dependency set in three tiers, keep the names and signatures
of the core functions plugins call, and add no package to replace a removed one.

1. **Remove unused packages:** `php-mqtt/client`, `mikepultz/netdns2`, the
   direct `psr/log` requirement, and `symfony/password-hasher`.
   `symfony/security-core` goes with `symfony/security-csrf` in tier 3.
2. **Replace small uses with native code:**
   - `finder`: a directory iterator in `CactiMd5FileFinder`.
   - `http-foundation`: a `cacti_ip_in_cidr()` helper for IPv4 and IPv6.
   - `lock`: the 1.2.x process-table implementation of
     `register_process_start()`, or `flock()` where no database is available.
   - `filesystem`: temporary file plus `rename()` for atomic writes, and the
     existing containment helpers.
   - `notifier`: a native `cacti_notifier` with email (through `mailer()`) and
     webhook-based channels, extensible through a plugin hook.
3. **Replace larger uses with native code:**
   - `validator`: a small `cacti_validate()` covering the constraints core uses
     (choice, type, length, range), applied at the same boundaries.
   - `console` and `process`: the native CLI argument handling and
     `exec_with_timeout()`, with array arguments where a shell is not needed.
   - `security-csrf`: a session-bound HMAC token with `random_bytes()` and
     `hash_equals()`, behind the existing `csrf_*` functions, that still accepts
     the current token format during an upgrade window.
   - `slim/slim`: a small dispatcher for the REST API routes, or removal of the
     REST API if ADR [0001](0001-rest-api-architecture.md) is not accepted.

Keep `symfony/mailer` and `symfony/mime`, `phpseclib/phpseclib`,
`onelogin/php-saml` with `robrichards/xmlseclibs`, `firebase/php-jwt`, the
OAuth2 client and provider packages, `jfcherng/php-diff`, and
`lipis/flag-icons`. Mail transport, SSH and cryptography, XML signature
validation and identity provider protocols are where a native implementation is
most likely to be wrong.

Removing all three tiers leaves 39 runtime packages, by the lockfile's
dependency graph at the revision above.

### Adding a dependency

A new runtime package needs a concrete requirement that native code cannot meet
at reasonable cost, a check of its PHP and extension floor against every
supported branch, license compatibility with GPL-2.0-or-later, a dependency
audit, release packaging verification with `composer install --no-dev`, and a
named owner for upgrades.

## Considered options

1. **Native replacements in tiers (recommended).** Removes about a third of the
   runtime packages and the Symfony version coupling, at the cost of owning the
   replacement code and its tests.
2. **Remove only the unused packages.** Low cost and low risk, but keeps the
   PHP floor coupling and most of the transitive packages.
3. **Keep the current set.** No migration work, but every package keeps its
   packaging, audit and upgrade cost, and the 8.x floor still arrives.

## Delivery

Deliver each tier as independent pull requests. A replacement PR converts the
callers and adds tests but leaves `composer.json` and `composer.lock` unchanged;
one later PR per tier removes the packages, regenerates the lockfile and
verifies a clean `--no-dev` install. This keeps lockfile churn out of code
review and lets a replacement be reverted on its own.

For the replacements where the upstream component handles many edge cases
(`IpUtils`, path containment, CSRF tokens, process execution), port the
component's own test vectors into Cacti's tests, so the native code is checked
against the same cases.

The 1.3 release notes must list the removed packages, because a plugin outside
the Cacti organization could load one of them from core's `include/vendor`.

## Consequences

- Positive: fewer packages to package, audit and upgrade; no coupling of
  Cacti's PHP floor to Symfony's; core behavior lives in code Cacti maintains.
- Negative: Cacti owns more security-relevant code (CSRF, path containment,
  CIDR matching, process execution) and its tests.
- Neutral: plugin-facing function names and signatures do not change.

## Testing strategy

- Each replacement PR keeps the existing tests for the converted callers
  passing and adds focused tests for the native helper, including the ported
  upstream test vectors where listed above.
- Run the plugin API checks and representative plugins that call `mailer()`,
  `send_mail()`, `csrf_check()` and `get_client_addr()` against each PR.
- Each package-removal PR verifies `composer install --no-dev`, the release
  archive build, and the CI PHP version matrix.

## References

- [Symfony releases and support periods](https://symfony.com/releases)
- [Symfony components](https://symfony.com/components)
- [PHP password hashing](https://www.php.net/manual/en/book.password.php)
- [OWASP CSRF prevention guidance](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
