---
applyTo: "**/*.php"
---

# Cacti PHP instructions

Use these instructions for PHP changes in Cacti organization repositories. Repository-local `AGENTS.md`, `.github/copilot-instructions.md`, `CONTRIBUTING.md`, Composer scripts, and CI workflows take precedence when they are more specific.

## Establish the repository contract first

- Before editing, inspect the target branch, `composer.json`, CI matrix, test configuration, formatter configuration, and nearby code. Do not assume that Cacti core and every plugin support the same PHP or Cacti versions.
- Write syntax and dependencies for the lowest PHP version supported by the target repository and branch. For plugins, also respect the Cacti branch/version used by plugin CI.
- Preserve the repository's GPL header, copyright form, bootstrap pattern, changelog process, and established file organization. Do not perform unrelated cleanup or repository-wide formatting.
- Confirm that a reported defect exists on the target branch before fixing it. Cacti release branches and `develop` can differ substantially.

## PHP style

- Indent PHP with tabs, displayed at four spaces. Use Unix line endings, remove trailing whitespace, and leave one newline at end of file.
- Put opening braces on the same line for functions, classes, closures, and control structures.
- Prefer single-quoted strings. Use double quotes only when interpolation or escape handling makes them appropriate.
- Use short arrays (`[]`), lowercase PHP keywords and `true`, `false`, and `null`, and strict comparisons when the type is known.
- Put one space around concatenation and binary operators. Do not add spaces inside array offsets.
- Prefer `foreach` and current PHP constructs over removed legacy constructs. Prefer simple string functions when a regular expression is unnecessary; never introduce `ereg_*`.
- Preserve readable alignment already used in a touched block, but do not create large whitespace-only diffs.
- Use the repository's PHP CS Fixer configuration when present. Do not replace Cacti's established style with a generic PSR preset.

## Cacti architecture and entry points

- Web pages must bootstrap through the repository's established authenticated entry point, normally `include/auth.php`; do not bypass authentication or initialize Cacti piecemeal.
- CLI scripts must use the established CLI bootstrap, normally `include/cli_check.php`.
- Use Cacti headers, footers, form helpers, table helpers, URL helpers, and plugin APIs instead of duplicating framework behavior or emitting a parallel UI.
- Follow the common page flow where applicable: initialize defaults, validate request variables, dispatch the action, then render through Cacti's header/footer helpers.
- Plugin changes must preserve the plugin `INFO` metadata and registration conventions. Register hooks and realms through the plugin APIs. AJAX plugin URLs normally include `header=false` when the surrounding Cacti layout must be suppressed.
- Reuse shared functions and existing domain helpers before adding a new abstraction. Keep behavior-preserving refactoring limited to the area already being changed.

## Input, authorization, CSRF, and output

- Treat all request, session, database, device, SNMP, file, and command output as untrusted at the relevant boundary.
- Do not read `$_GET`, `$_POST`, or `$_REQUEST` directly in new code. Use Cacti request helpers such as `get_filter_request_var()` / `gfrv()`, `get_request_var()` / `grv()`, `get_nfilter_request_var()` / `gnrv()`, and the repository's form validation helpers.
- Understand request caching: once `gfrv()` has validated a variable, later `grv()` or `gnrv()` calls for that variable retrieve the cached value. Do not add misleading duplicate validation or casts, but ensure every externally controlled variable has an appropriate validation point.
- Validate by allowlist and expected type, range, or format. Never rely on escaping as input validation.
- Enforce authorization on the server for every privileged page and action. Hiding a control in the UI is not authorization. For plugins, keep realm registration, upgrade/repair behavior, and action-level permission checks consistent.
- Protect state-changing forms and AJAX requests with Cacti's CSRF mechanism. AJAX posts include `__csrf_magic: csrfMagicToken` unless a repository-local helper supplies it.
- Escape at output time for the exact context. Use Cacti's HTML text and attribute helpers; use `html_escape_attr()` for attribute contexts. Do not use a text-context escape helper for JavaScript, JSON, URL, or HTML attributes.
- Build local URLs with `cacti_url()` where available and redirect with `cacti_redirect()`. Do not redirect to unvalidated user-controlled destinations.
- Encode JSON with the repository's safe JSON conventions and return the correct content type. Do not build JSON by concatenating strings.
- Wrap user-visible strings in `__()` and pass the plugin text domain where the surrounding plugin does so.

## Database access

- Use the database helpers supplied by Cacti or the plugin's established database wrapper. Do not add a second connection layer without a repository-specific need.
- Use prepared helpers such as `db_fetch_row_prepared()`, `db_fetch_assoc_prepared()`, and `db_execute_prepared()` whenever values are variable. The same rule applies to plugin-prefixed wrappers.
- Never interpolate request data or other untrusted values into SQL. Placeholders bind values, not identifiers; validate dynamic table names, column names, sort directions, and SQL fragments against a strict allowlist before composing SQL.
- Do not quote numeric SQL values. Quote strings through parameter binding. Use `AS` for aliases and explicit `INNER JOIN`, `LEFT JOIN`, or other join types.
- Avoid `SELECT *` in production queries. Select the required columns and check the schema and indexes before changing a performance-sensitive query.
- Make schema installation and upgrades idempotent and safe for existing installations. Preserve data unless a clearly named, authorized migration intentionally changes it.

## Files, commands, logging, and secrets

- Treat file paths and archive contents as untrusted. Constrain them to the intended base directory and reject traversal or unexpected file types.
- Avoid shell execution when a PHP or Cacti API exists. When execution is necessary, keep the executable fixed, validate arguments, and escape each argument with `escapeshellarg()`; never concatenate raw input into a command.
- Use `cacti_log()` and related repository helpers rather than ad hoc log files or `error_log()` when application logging is intended.
- Do not log credentials, tokens, session identifiers, SNMP secrets, database passwords, or full sensitive request payloads.
- Never commit generated configuration containing secrets. Keep `.dist` examples inert and clearly placeholder-based.

## Dependencies and generated code

- Do not add, copy, or refresh third-party source trees in the repository. Do not hand-edit Composer-generated autoload files.
- Manage PHP dependencies through Composer when the repository uses it. Update `composer.json` and the lock file together when a lock file is part of that repository's policy.
- Validate dependency changes with `composer validate` and a clean `composer install`. Do not run `composer update` unless the change intentionally updates dependency resolution.
- Preserve the configured vendor directory. In Cacti core it may be `include/vendor`; plugins commonly consume the Cacti installation's toolchain instead of owning a separate vendor tree.

## Tests and verification

- Add or update the smallest relevant automated test for changed behavior. Use the repository's existing Pest/PHPUnit structure and bootstrap; do not impose core's directory layout on plugins that use a different layout.
- Name tests after behavior, not an issue or advisory number. Put the tracking identifier in the test description or docblock when useful.
- Do not declare or conditionally redefine Cacti core functions at test-file scope. Shared Pest collection can make those definitions order-dependent; use the repository's configurable stubs or fixtures.
- Test both the success path and important failure boundaries, especially validation, authorization, CSRF, escaping, SQL construction, upgrades, and compatibility behavior.
- Run the repository-provided checks. Prefer Composer scripts and CI-equivalent commands over invented commands. At minimum, run PHP syntax checks and the focused tests for touched code; when available, also run PHPStan and PHP CS Fixer in dry-run mode.
- For plugins, verify inside a compatible Cacti installation when the change depends on Cacti bootstrap, schema, hooks, realms, polling, or rendered UI. A standalone syntax check is not an integration test.
- Report verification precisely: list commands and PHP versions used, distinguish static analysis from runtime/integration/browser checks, and treat a pull request with zero CI checks as unverified rather than passing.

## Completion checklist

- The change supports the target repository's minimum PHP and Cacti versions.
- Request data is validated; privileged actions are authorized and CSRF-protected.
- SQL values use prepared statements and dynamic identifiers are allowlisted.
- Output is escaped for its exact context and visible strings are translatable.
- Existing install and upgrade paths remain safe.
- No secrets, generated dependencies, or unrelated formatting changes were added.
- Relevant lint, formatter, static-analysis, unit, integration, and UI checks were run or explicitly reported as not run.
