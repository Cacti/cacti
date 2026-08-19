# Instructions for coding agents

Applies to Claude, Codex, and any other agent working in this repository.
`.github/copilot-instructions.md` is the source of truth and Copilot reads it
directly. Read that first and follow it. This file covers the points that are
easiest to get wrong, and does not repeat the rest.

`CLAUDE.md` is gitignored on purpose (it holds local security-research notes),
so shared agent guidance belongs here.

## Where tests go

Unit tests live under `tests/Unit/` in a category subfolder. Do not add a flat
file to `tests/Unit/` root.

- `Security/` — subfoldered by vulnerability class (`SqlInjection/`, `Xss/`,
  `Shell/`, `ShellInjection/`, `Auth/`, `Csrf/`, `Csp/`, `Redirect/`,
  `PathTraversal/`, `Ssrf/`, `Crypto/`, `InputValidation/`, `Session/`, `Ldap/`,
  `Type/`, `Misc/`)
- `DataCollection/` — `Poller/`, `Snmp/`, `Cmd/`, `DataQuery/`, `DataInput/`
- `Ui/` — `Aggregate/`, `Graph/`, `Html/`, `Theme/`, `Colour/`, `Tree/`,
  `Spikekill/`, `Utility/`
- `Core/` — `Rrd/`, `RemoteAgent/`, `Automation/`, `Cli/`, `Mailer/`, `Boost/`,
  `Availability_Tests/`
- `Database/`, `Installer/`, `Scripts/`, `Plugin/` at the top level

If a new domain is needed, create a folder for it. Name the file after the
behaviour it tests, not the issue number: `PercentileContractTest.php`, not
`Issue7070PercentileContractTest.php`. The GHSA or issue ID goes in the
`test('...')` description or a file docblock.

## Path depth

The depth depends on the folder's nesting level and on what the path points at.
Paths that reach the repository root need one more level than paths that stop at
`tests/`, so one file often needs two different depths.

| test file location | repo root (`lib/`, `include/`) | `tests/` (`Helpers/`, `fixtures/`, `tools/`) |
| --- | --- | --- |
| `tests/Unit/` | `dirname(__DIR__, 2)` | `dirname(__DIR__)` |
| `tests/Unit/<Category>/` | `dirname(__DIR__, 3)` | `dirname(__DIR__, 2)` |
| `tests/Unit/<Category>/<Subcategory>/` | `dirname(__DIR__, 4)` | `dirname(__DIR__, 3)` |

Setting every path in a moved file to the repo-root depth breaks the
`tests/`-internal ones. A `require` that resolves to a missing file is fatal
during bootstrap and stops the entire suite rather than failing one test.

## Do not shadow core functions

Test files must not declare Cacti functions (`db_fetch_assoc()`,
`read_config_option()`, `cacti_log()`, `__()`) at file scope, and must not wrap
production declarations in `function_exists()`.

Pest's `TestSuiteLoader` includes every test file in one process while
collecting, so the first definition wins and later ones are skipped. Behaviour
then depends on load order, and moving a file changes results. `processIsolation`
does not help: it isolates execution, not collection.

Configure a stub instead of redefining it. See
[`tests/Helpers/CactiStubs.php`](tests/Helpers/CactiStubs.php) for the
repository's working example.

## Running tests

Pest, through Cacti's own toolchain:

```
composer test -- --testsuite=Unit
```

Match the PHP version declared by `composer.json` and the CI matrix rather than
whatever is on `PATH`. Report which version produced a result.
