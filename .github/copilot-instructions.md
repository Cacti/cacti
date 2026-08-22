# Copilot instructions (Cacti `develop`)

## Big Picture
**Cacti** is a robust, extensible network monitoring and graphing tool built on the LAMP stack.

- **Core Purpose**: Polls devices (SNMP, scripts), stores time-series data in **RRDtool**, and renders historical graphs for network operations.

- **Architecture**:
  - **Frontend**: PHP 8.1+ web UI for configuration and visualization.
  - **Backend**: MySQL/MariaDB for metadata/state/configuration/statistics; 
                 RRD files for metrics.
  - **Polling**: Scalable data collection via `cmd.php` (PHP) or `spine` (C), supporting remote pollers.
- **Key Components**:
  - **Templates**: Abstraction for Graphs, Data Sources, and Devices to simplify management.
  - **Plugins**: Extension system for adding features (e.g., Thold, Weathermap).
  - **Automation**: Rules for discovering and adding devices automatically.

## Entry points & Architecture
- **Web**: Pages start with `include/auth.php` (loads `include/global.php`, enforces auth).
- **CLI**: Scripts load `include/cli_check.php` (enforces CLI context).
- **Polling & Data Flow** (Principles of Operation):
  - **Hierarchy**: Sites > Data Collectors > Devices > Graphs.
  - **Collection**: `poller.php` (cron) or `cactid.php` (daemon) fetch data via SNMP, `script_server.php` (resident PHP pool), or external scripts.
  - **Storage**: Metadata in MySQL/MariaDB; Metrics in RRDfiles (`.rrd`).
  - **Visualization**: RRDtool renders graphs (see `graph_image.php`).
- **API**: REST endpoints in `api/` (Slim framework).
- **CLI Scripts**: Administrative scripts located in `cli/`
- **SNMP Query Templates**: XML templates with snmp OID's to poll located in `resource\snmp_queries`

## Project conventions (follow these)
- **Coding Style**:
  - Indentation: Tabs (4 spaces width).
  - Braces: Start on the same line (`if (...) {`).
  - Quotes: Single quotes `'` preferred over double `"` unless interpolating.
  - Constants: `true`, `false`, `null` should be lowercase.
  - Arrays: Use `foreach` over `while(list() = each())`.
- **Database (MariaDB/Mysql)**:
  - Use helpers in `lib/database.php`; prefer prepared calls like `db_fetch_row_prepared()` / `db_fetch_assoc_prepared()` / `db_execute_prepared()`.
  - **Do NOT quote numeric values** in SQL. Use single quotes for strings.
  - Always use `AS` for aliases.
  - Use explicit `INNER JOIN`, `LEFT JOIN`, etc. (no implicit joins).
- **Request handling (web)**:
  - Avoid direct `$_GET/$_POST/$_REQUEST`.
  - Use `get_request_var()` / `get_filter_request_var()` and `form_input_validate()`.
  - Validation: Use `validate_store_request_vars()` where possible.
  - **Request variable caching** (aliases in `lib/html_utility.php`):
    - `gfrv($name)` / `get_filter_request_var()` — validates input (default: integer via `filter_var`), stores the sanitized value in `$_CACTI_REQUEST`, and **returns** it.
    - `grv($name)` / `get_request_var()` — retrieves from `$_CACTI_REQUEST` cache. Returns pre-validated data if `gfrv()` was called first for that variable.
    - `gnrv($name)` / `get_nfilter_request_var()` — retrieves from cache without filtering. Safe if `gfrv()` was called first.
    - `srv($name, $value)` / `set_request_var()` — stores a value in `$_CACTI_REQUEST`, `$_REQUEST`, `$_POST`, and `$_GET`.
    - **Do NOT flag `grv()`/`gnrv()` calls as unsanitized when `gfrv()` was already called for that variable in the same request.** The validation happens once at the `gfrv()` call site; subsequent retrievals return cached, pre-validated data.
    - Adding redundant `(int)` casts or parameterization after `gfrv()` has validated is unnecessary.
- **Common page flow**: `set_default_action(); switch (get_request_var('action')) { ... }` and render via `top_header()`/`bottom_footer()`.
- **CSRF**: AJAX posts include `__csrf_magic: csrfMagicToken`.
- **Logging**: use `cacti_log(...)` and `cacti_log_file()`.
- **i18n**: wrap UI strings with `__('...')`.
- **Plugins**:
  - Hooks via `api_plugin_hook(...)` in `lib/plugins.php`.
  - Must have an `INFO` file (INI format).
  - Use `top_header()`/`bottom_footer()` (no direct includes).
  - Add `&header=false` to URLs for AJAX requests.
- **CHANGELOG**:
  - Within each release section, keep `-issue` entries first and `-feature` entries after them.
  - Append new issue entries at the end of the issue block and new feature entries at the end of the feature block.
  - Keep numbered entries in their existing numeric order; every new entry must include an issue or pull-request number (`#1234`).
  - Preserve the existing entry prefixes, release headings, and blank-line spacing.

## Workflows you’ll actually use
- Install deps: `composer install` (CI validates via `.github/workflows/syntax.yml`).
- Install/upgrade DB: `php -q cli/install_cacti.php --accept-eula --install --force` and `php -q cli/upgrade_database.php --forcever=$(cat include/cacti_version)`.
- Run poller: `php poller.php --poller=1 --force --debug` (daemon debug: `./cactid.php --foreground --debug`).
- Repo checks: `composer lint`, `composer phpstan`, `composer phpcsfixer` (dry-run).

## Database Optimization (DBA Mode)
- **Context**: The full schema (DDL/DML) is in `cacti.sql`. Check it for table structures and indexes.
- **Optimize**: Proactively look for slow query patterns (e.g., missing indexes, non-sargable `WHERE` clauses).
- **Best Practices**:
  - Prefer `JOIN` over subqueries where efficient.
  - Ensure columns in `WHERE`/`JOIN` clauses are indexed (check `cacti.sql`).
  - Avoid `SELECT *` in production code; list columns explicitly.

## Security Advisor (AppSec Mode)
- **Context**: Cacti Takes Security Seriously consider the security implications when reviewing current and new code base
- **Vulnerability Checks**:
  - **SQL Injection**: STRICTLY enforce prepared statements (`db_*_prepared`). Flag any variable interpolation in SQL strings.
  - **XSS**: Ensure user input is sanitized/escaped before output (use `htmlspecialchars` or Cacti's wrappers).
  - **CSRF**: Verify forms/AJAX include `__csrf_magic`.
  - **Command Injection**: Scrutinize `shell_exec`, `exec`, `passthru`. Ensure arguments are escaped (`escapeshellarg`).
  - **Auth**: Verify `include/auth.php` is loaded and permissions are checked (`api_user_realm_auth`).

## Clean-as-you-code
- While implementing a change, do small, behavior-preserving refactors in the *touched area* (dedupe logic, extract helpers, simplify conditions).
- **Formatting**: Enforce tabs for indentation (no spaces for indentation).
- **Legacy**: Avoid `preg_*` if simple string functions work. Use `preg_*` over `ereg_*`.
- Avoid drive-by rewrites: no repo-wide formatting, no unrelated cleanup, and preserve existing file conventions (keep the GPL header blocks).

## Unit test organization (Pest)
- Unit tests live in `tests/Unit/` and use [Pest](https://pestphp.com/) (`test()` / `it()` functions, not PHPUnit classes). The Pest bootstrap is `tests/Pest.php`; a lightweight source-scan bootstrap is `tests/bootstrap-unit.php`.
- Tests are organized into category subfolders by domain. New tests **must** follow this structure — do not add flat files to `tests/Unit/` root:
  - `Security/` — security-related tests, subfoldered by vulnerability class (`SqlInjection/`, `Xss/`, `Shell/`, `Auth/`, `Csrf/`, `Csp/`, `Redirect/`, `PathTraversal/`, `Ssrf/`, `Crypto/`, `InputValidation/`, `Session/`, `Ldap/`, `Type/`, `ShellInjection/`, `Misc/`).
  - `DataCollection/` — poller/SNMP/cmd/data-query tests (`Poller/`, `Snmp/`, `Cmd/`, `DataQuery/`, `DataInput/`).
  - `Ui/` — front-end/graph rendering tests (`Aggregate/`, `Graph/`, `Html/`, `Theme/`, `Colour/`, `Tree/`, `Spikekill/`, `Utility/`).
  - `Core/` — core library tests, with subfolders for distinct subsystems (`Rrd/`, `RemoteAgent/`, `Automation/`, `Cli/`, `Mailer/`, `Boost/`, `Availability_Tests/`).
  - `Database/`, `Installer/`, `Scripts/`, `Plugin/` — domain-specific folders at the top level.
- When adding a new test, place it in the matching category folder. If a new domain is needed, create a folder for it rather than leaving the file flat in `tests/Unit/`.
- **File naming:** do not encode GitHub issue or GHSA numbers in filenames. Name the file after the behavior it tests (e.g. `PercentileContractTest.php`, not `Issue7070PercentileContractTest.php`). The GHSA/issue ID belongs in the test's `test('...')` description string or a file-level docblock, not the filename.
- **`__DIR__` path depth:** the depth depends on both the folder's nesting level and on what the path points at. Paths that reach the repository root need one more level than paths that stop at `tests/`, so a single file often needs two different depths.

  | test file location | to reach repo root (`lib/`, `include/`) | to reach `tests/` (`Helpers/`, `fixtures/`, `tools/`) |
  | --- | --- | --- |
  | `tests/Unit/` | `dirname(__DIR__, 2)` | `dirname(__DIR__)` |
  | `tests/Unit/<Category>/` | `dirname(__DIR__, 3)` | `dirname(__DIR__, 2)` |
  | `tests/Unit/<Category>/<Subcategory>/` | `dirname(__DIR__, 4)` | `dirname(__DIR__, 3)` |

  - After moving a test between folders, update every path in it, and check each one individually. Setting them all to the repo-root depth silently breaks the `tests/`-internal ones. A `require` that resolves to a missing file is fatal during bootstrap and stops the whole suite rather than failing one test.
  - Verify a move by running the suite, not by reading the diff.
