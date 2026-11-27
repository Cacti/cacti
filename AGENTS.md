# Instructions for coding agents

Applies to Claude, Codex, and any other agent working in this repository.
`.github/copilot-instructions.md` is the source of truth and Copilot reads it
directly. Read that first and follow it. This file covers the points that are
easiest to get wrong, and does not repeat the rest.

`CLAUDE.md` contains shared repository guidance. Keep private security-research
notes outside tracked instruction files and follow `SECURITY.md` for reporting.

## Contributor skills

Choose a workflow for the task at hand. Use the target branch's code and
contributor instructions when a skill refers to a branch-specific contract.

| Task | Skill |
| --- | --- |
| Create or change a plugin | [cacti-plugin-contract](.agents/skills/cacti-plugin-contract/SKILL.md) |
| Fix core behavior | [cacti-core-fix](.agents/skills/cacti-core-fix/SKILL.md) |
| Develop a data query or diagnose a graph | [cacti-data-query-graph](.agents/skills/cacti-data-query-graph/SKILL.md) |
| Identify when a regression began | [cacti-regression-origin](.agents/skills/cacti-regression-origin/SKILL.md) |
| Assess a maintenance backport | [cacti-backport](.agents/skills/cacti-backport/SKILL.md) |
| Find other affected callers | [cacti-related-failures](.agents/skills/cacti-related-failures/SKILL.md) |
| Describe a patch for maintainers | [cacti-patch-description](.agents/skills/cacti-patch-description/SKILL.md) |
| Design or verify a regression test | [cacti-regression-test](.agents/skills/cacti-regression-test/SKILL.md) |
| Develop a polling or storage change | [cacti-poller-reliability](.agents/skills/cacti-poller-reliability/SKILL.md) |
| Develop a database upgrade | [cacti-database-upgrade](.agents/skills/cacti-database-upgrade/SKILL.md) |
| Implement or verify graph access | [cacti-effective-permissions](.agents/skills/cacti-effective-permissions/SKILL.md) |
| Develop template import or export | [cacti-template-roundtrip](.agents/skills/cacti-template-roundtrip/SKILL.md) |
| Develop a localized UI change | [cacti-localization](.agents/skills/cacti-localization/SKILL.md) |
| Document a feature or correct an existing guide | [cacti-documentation](.agents/skills/cacti-documentation/SKILL.md) |
| Review the security of changed code | [cacti-security-review](.agents/skills/cacti-security-review/SKILL.md) |
| Review a patch for idiomatic Cacti code | [cacti-idiomatic-review](.agents/skills/cacti-idiomatic-review/SKILL.md) |

## Maintenance fix scope

Follow the [maintenance fix scope rules](.github/copilot-instructions.md#maintenance-fix-scope).
Before adding cache lifecycle machinery or hooks across management APIs, show
why the existing mechanism cannot support the fix and which operations change
the derived result. Reconsider designs that introduce dependencies across
unrelated subsystems. Passing tests alone do not justify expanded scope.
Keep maintenance fixes small and compatible with the target branch.

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

## Polling performance is an architectural constraint

Read the polling performance section in
[.github/copilot-instructions.md](.github/copilot-instructions.md) before changing
poller, script-server, SNMP, subprocess or RRD update paths. These paths can serve
millions of data sources; repeated work inside their loops must be justified.

Cache shared template metadata once per process or explicit polling cycle,
including empty results. Do not replace that cache with per-data-source lookups
or load millions of source records to answer a template-level question. Preserve
source-specific correctness and bounded memory. Avoid fixed per-call sleeps and
repeated process launches, includes or SNMP requests introduced by wrappers.

Verify query/call counts, subprocess overhead and cache lifecycle using the
target branch's actual code. Synthetic scale tests are regression evidence,
not proof of production RTM throughput. Credential-cache benefits require
measurement; they do not excuse repeated metadata work or process delays.

## Never vendor dependencies

Do not add, copy, or update third-party package source under `include/vendor/`
or elsewhere in the repository. Existing vendored dependencies may be removed
by an in-scope change, but must not be expanded or refreshed.

Add PHP dependencies through Composer: update `composer.json`, update and commit
`composer.lock`, and let `composer install` install the locked packages in local
development, CI, and release builds. Do not commit Composer-installed package
trees or hand-edit Composer-generated autoload metadata. Validate dependency
changes with `composer validate` and a clean `composer install`.

If a third-party asset cannot be managed through Composer, stop and document
the concrete packaging requirement before adding files. Do not silently create
a vendored exception.

## Confirm the defect before writing the fix

A finding from a review, a scan or another agent is a claim about a branch, not
a fact about this one. `develop` and `1.2.x` diverge, and a defect reported
against one is often already fixed on the other, sometimes by a pull request
that has since merged.

Before writing a fix, check that the target still exists where you intend to
land it:

    git show upstream/develop:path/to/file.php | rg 'the pattern'
    git log -S 'the pattern' --oneline upstream/develop | head

Then choose the branch deliberately, and say in the pull request which branches
the defect affects. Porting a fix to a branch that never had the bug adds review
work and risk for no benefit.

## A pull request with no checks has not been verified

Branches pushed from a fork hold their workflow runs in `action_required` until
someone approves them. Until that happens the pull request reports **no checks
at all**, which is easy to read as passing in both the merge box and
`gh pr checks`. Nothing has run.

    # runs waiting for approval
    gh run list --repo Cacti/cacti --limit 200 --json conclusion \
      --jq '[.[]|select(.conclusion=="action_required")]|length'

    # a pull request with zero checks is unverified, not green
    gh pr checks <pr> --repo Cacti/cacti | wc -l

`gh run list` pages by recency, so one sweep can miss older branches; re-check
with `--branch <name>`. Report a pull request as green only once its checks
exist and have completed.

