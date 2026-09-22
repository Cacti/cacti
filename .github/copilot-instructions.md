Copilot instructions for this repo (Cacti 1.2.x)

Use these notes to navigate and contribute productively to this PHP codebase.

## Big picture
- Cacti is a PHP web app + CLI poller/daemon that stores state in MySQL/MariaDB and graphs via RRDtool.
- Bootstrap: `include/global.php` loads config, DB connection (`lib/database.php`), core libs, and sets `$config` globals. Web pages typically include `include/auth.php` first; CLI scripts include `include/cli_check.php`.
- Major areas:
  - Web UI (top-level `*.php`, helpers in `lib/html*.php`, `include/top_*_header.php`/`bottom_footer.php`).
  - Core APIs in `lib/api_*.php` and domain modules in `lib/*.php` (devices, data sources, graphs, templates, poller, plugins).
  - CLI tools in `cli/` (install, manage, poller operations), used by CI and admins.
  - Poller runners: `poller.php` (single run) and `cactid.php` (systemd-friendly loop; see `service/README.md`).
  - Plugin framework in `lib/plugins.php` with hooks; plugins live under `plugins/`.

## Data access and patterns
- Always use DB helpers from `lib/database.php`. Prefer prepared variants: `db_fetch_cell_prepared`, `db_fetch_assoc_prepared`, `db_execute_prepared`. For inserts/updates, use `sql_save($array, $table)` when possible.
- Under the hood uses PDO; `global.php` decides between local/remote DB for remote pollers.
- Logging: `cacti_log($msg, $echo=false, $subsystem='SYSTEM', $verbosity=POLLER_VERBOSITY_MEDIUM)`; log path via `cacti_log_file()`.

## Web page conventions
- Start with `include('./include/auth.php');` to enforce auth/session/CSRF.
- Flow pattern: `set_default_action(); switch (get_request_var('action')) { ... }` with helpers like `top_header()` and `bottom_footer()` for layout (see `data_input.php`).
- Request/validation: use `get_request_var/get_filter_request_var/get_nfilter_request_var`, `form_input_validate(...)`, and utilities like `sanitize_unserialize_selected_items(...)`. Don’t read `$_REQUEST` directly.
- CSRF: AJAX posts include `__csrf_magic: csrfMagicToken` (see usages in `host_templates.php`, `data_queries.php`).
- i18n: wrap UI strings with `__('...')`.

## CLI and daemon workflows
- Install/upgrade: `php -q cli/install_cacti.php --accept-eula --install --force`; DB upgrade when needed: `php -q cli/upgrade_database.php --forcever=$(cat include/cacti_version)` (see README).
- Polling:
  - One-shot: `php poller.php --poller=1 --force --debug`.
  - Daemon loop: `./cactid.php --foreground --debug` or systemd via `tests/tools/cactid.service` and `service/README.md`.
- Common maintenance: `cli/rebuild_poller_cache.php`, `cli/poller_reindex_hosts.php`, `cli/plugin_manage.php`, etc.

## Plugin framework
- Hooks are declared in DB and executed via `api_plugin_hook(...)` and `api_plugin_hook_function(...)` (see `lib/plugins.php`).
- Plugins must reside in `plugins/<name>/` with `setup.php` and `INFO`; enabled/ordered via `plugin_config` table. CI fetches core plugins (`.github/workflows/syntax.yml`).
- Use hooks like `page_head`, `poller_top`, `device_remove`, `create_complete_graph_from_template` to integrate (grep for `api_plugin_hook_function` in `lib/`).

## Testing, CI, and local checks
- No PHPUnit; CI runs syntax checks and an end-to-end smoke: sets up Apache+MySQL, installs Cacti, enables plugins, runs poller, and spiders pages (see `.github/workflows/syntax.yml`, scripts in `tests/tools/`).
- Local quick checks:
  - PHP lint: `find . -name '*.php' -exec php -l {} \; | grep -iv 'no syntax errors detected'` (CI uses similar).
  - Minimal smoke: create `include/config.php` from `.dist`, import `cacti.sql`, then run install + `poller.php` as above; tail `log/cacti.log` for `SYSTEM STATS`.

### Unit test organization (Pest)
- Unit tests live in `tests/Unit/` and use [Pest](https://pestphp.com/) (`test()` / `it()` functions, not PHPUnit classes). The Pest bootstrap is `tests/Pest.php`; a lightweight source-scan bootstrap is `tests/bootstrap-unit.php`.
- Tests are organized into category subfolders by domain. New tests **must** follow this structure — do not add flat files to `tests/Unit/` root:
  - `Security/` — security-related tests, subfoldered by vulnerability class (`SqlInjection/`, `Xss/`, `Shell/`, `Auth/`, `Csrf/`, `Csp/`, `Redirect/`, `PathTraversal/`, `Ssrf/`, `Crypto/`, `InputValidation/`, `Session/`, `Ldap/`, `Type/`, `ShellInjection/`, `Misc/`).
  - `DataCollection/` — poller/SNMP/cmd/data-query tests (`Poller/`, `Snmp/`, `Cmd/`, `DataQuery/`).
  - `Ui/` — front-end/graph rendering tests (`Aggregate/`, `Graph/`, `Html/`, `Theme/`, `Colour/`, `Tree/`, `Spikekill/`, `Utility/`).
  - `Core/` — core library tests, with subfolders for distinct subsystems (`Rrd/`, `RemoteAgent/`, `Automation/`, `Cli/`, `Mailer/`, `Boost/`, `Availability_Tests/`).
  - `Database/`, `Installer/`, `Scripts/`, `Plugin/` — domain-specific folders at the top level.
- When adding a new test, place it in the matching category folder. If a new domain is needed, create a folder for it rather than leaving the file flat in `tests/Unit/`.
- **File naming:** do not encode GitHub issue or GHSA numbers in filenames. Name the file after the behavior it tests (e.g. `PercentileContractTest.php`, not `Issue7070PercentileContractTest.php`). The GHSA/issue ID belongs in the test's `test('...')` description string or a file-level docblock, not the filename.
- **`__DIR__` path depth:** tests read source files via `file_get_contents(__DIR__ . '/../../../...')` or `dirname(__DIR__, N)`. The depth must match the folder's nesting level:
  - `tests/Unit/<Category>/` → 3 levels (`'/../../../'` or `dirname(__DIR__, 3)`)
  - `tests/Unit/<Category>/<Subcategory>/` → 4 levels (`'/../../../../'` or `dirname(__DIR__, 4)`)
  - After moving a test between folders, always update the `__DIR__` relative paths to match the new depth.

## Coding do’s in this repo
- Use prepared DB helpers and input validators; follow patterns in `data_input.php`, `host_templates.php`.
- Reuse HTML helpers (`lib/html_*.php`) for forms, filters, pagination.
- Raise UI messages via `raise_message(...)` and redirect with `header('Location: ...')`.
- Respect remote poller modes and `$config['is_web']`/CLI guards (`$no_http_header_files` in `include/global.php`).

## Coding standards
- Follow PHP PSR standards (PSR-12/PSR-1) for new or standalone code where practical, but match the file’s existing conventions.
  - Preserve the file’s indentation (tabs vs spaces) and brace style; do not reformat unrelated code.
  - Keep the Cacti GPL header block at the top of PHP files.
  - Use snake_case functions and procedural structure consistent with the codebase; avoid introducing namespaces unless integrating vendor code.
  - Maintain PHP 5.4+ compatibility (CI tests 7.0–8.4). Avoid using features requiring >7.0 (e.g., union types, attributes, typed properties) in core code.
  - Don’t change public function signatures in `lib/api_*.php` or widely used helpers without auditing usages.
  - For dependencies, prefer Composer-managed libs under `include/vendor` and keep versions pinned by `composer.lock`.

## Security advisories and CVE policy
- `1.2.x` is the released/LTS branch. A security fix landing here means real, released installations are affected, so **a CVE is requested** for any GitHub Security Advisory (GHSA) whose vulnerable code is reachable on `1.2.x` (in addition to `develop`, if also affected there).
- `develop`/`1.3.x` is unreleased. A vulnerability that exists **only** on `develop` (confirmed absent/already-mitigated on `1.2.x`, e.g. the feature doesn't exist yet, or `1.2.x` already has an equivalent guard) does **not** get a CVE requested, since no released version is affected.
- Before deciding CVE vs. no-CVE for a given advisory, verify the claim against both branches' actual current source (don't rely solely on the reporter's stated `vulnerable_version_range` — it can be wrong, e.g. describing a bug already fixed on `1.2.x` that only regressed on `develop`).
- When an advisory turns out to be `develop`-only, record that determination directly in the GHSA description (a dated "Triage Note") rather than requesting a CVE, and note that the policy applies specifically because no released version is affected.
- If a `develop`-only advisory is later found to also affect a released `1.2.x` version, request a CVE at that point.

## Useful references
- Bootstrap/config: `include/global.php`, `include/config.php.dist`.
- Core libs: `lib/database.php`, `lib/functions.php`, `lib/poller.php`, `lib/template.php`, `lib/plugins.php`.
- Exemplars: `data_input.php` (full CRUD page), `host_templates.php` (AJAX + CSRF + validation), `cactid.php` (daemon loop), `cli/install_cacti.php` (installer flow).

## Pending upstream dependency: select2 multi-select dropdown search
- `include/layout.js` (`select2-multi-count` init, both this branch and `feature/select2-select-conversion`)
  works around select2 4.1.0 not natively supporting "search from the dropdown
  panel instead of an inline box" for multi-selects. It manually builds custom
  `dropdownAdapter`/`selectionAdapter` combinations via
  `$.fn.select2.amd.require('select2/...')` (synchronous string-form require -
  see the comment at that call site for why array-form must not be used).
- TheWitness has an upstream pull request open against `select2/select2`
  proposing this behavior as a first-class option (search here for the PR once
  filed and fill in the URL below):
  `<select2/select2 PR URL - TBD, opened by TheWitness>`
- **Action item for any future Copilot session working in this repo:** when
  starting work here, check whether a select2 release has shipped that
  includes that PR (check `select2/select2` releases/CHANGELOG for a
  first-class dropdown-search-for-multiple-selects option). If it has:
  1. Bump the vendored `include/js/select2.js` to that release.
  2. Replace the manual adapter-composition workaround in `include/layout.js`
     with the new native option on both `feature/select2-select-conversion`
     (develop) and `feature/select2-select-conversion-1.2.x` (1.2.x), or their
     successor branches/develop/1.2.x if already merged.
  3. Remove the now-unnecessary `multiCountDropdownAdapter`/
     `multiCountSelectionAdapter` wiring and related comments once the native
     option covers it.
  4. Open a Cacti PR for this cleanup as a matter of course - don't wait to be
     asked.