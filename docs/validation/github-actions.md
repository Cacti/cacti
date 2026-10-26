# Theme checks in GitHub Actions

The `Develop theme correctness` workflow separates core rendering, independent
plugin compatibility, and the RTM bundle so each failure has a named check and
its own browser evidence. All suites call the same `Theme browser suite`
workflow for locked dependencies, reporting, and artifact handling. The caller
uses GitHub's supported `./.github/workflows/` syntax so the called workflow
comes from the same commit and remains compatible with repository lint tooling.

## Triggers and checks

The workflow runs for pull requests regardless of the base branch name, pushes
to `develop`, merge-queue `merge_group` checks, and manual `workflow_dispatch`
runs. Stacked PRs therefore run the
same checks as ordinary PRs. New commits cancel superseded runs for the same
PR or branch. A canceled run is not a passing validation result.

| Check | Responsibility |
| --- | --- |
| Pinned theme fixtures | Validate the manifests and CI helper regression tests |
| Core theme rendering and CSS coverage / Browser evidence | Core browser suites and the PR's 100% changed executable CSS gate |
| Plugin compatibility (`plugin`) / Browser evidence | One pinned plugin across all twelve theme variants |
| RTM plugin compatibility / Browser evidence | Pinned bundle, source inventory, tables, console, filters, settings |
| Theme validation | Pass only when fixture preparation and every browser job succeed |

`Theme validation` is the stable aggregate check to select in branch protection
or a ruleset. This contribution does not change repository rulesets. A failed,
skipped, or canceled dependency makes the aggregate fail; missing reports or
zero executed tests cannot establish compatibility. Partially skipped suites
also fail rather than claiming all variants were exercised.

Plugin jobs use `fail-fast: false` so one failure does not cancel other plugins'
evidence, with at most three plugin jobs running concurrently. Each browser
job has a 15-minute timeout. Checkout credentials are not persisted, workflow
permissions are limited to `contents: read`, and Actions are pinned to immutable
SHAs. Public fixture checkouts require no additional credentials.

## Fixture revisions

The committed manifests are the source of truth:

- `tests/e2e/plugin-theme-revisions.json` supplies each Cacti plugin's commit.
- `tests/e2e/rtm-theme-fixtures.json` supplies the RTM repository and commit.

The preparation job derives the matrix and RTM checkout inputs from these
files. It rejects empty matrices, invalid plugin identifiers, and mutable or
abbreviated revisions. Tests independently verify the checkout's HEAD. To
update a fixture, review the upstream change and edit its manifest; no second
revision pin needs changing in workflow YAML. Updates do not happen silently
against moving default branches.

## Reports and artifacts

Every browser job publishes a Markdown summary with actual passed, failed,
flaky, and skipped counts, the core and fixture revisions, CSS coverage status,
and an artifact download link. Failure reports remain available when a test or
coverage step fails. Setup failures are reported as failures rather than as a
zero-test pass. A flaky test is listed separately even when a retry succeeds.

Artifacts have unique names per suite or plugin and run attempt:

- `theme-core-attempt-1`
- `theme-thold-attempt-1`, and corresponding names for the other plugins
- `theme-rtm-attempt-1`

A rerun uses its new attempt number. Artifacts are retained for 14 days, subject
to repository availability and manual deletion. Each archive contains:

| Path | Contents |
| --- | --- |
| `html/` | Interactive Playwright report with attachments |
| `results.json` | Complete machine-readable Playwright results |
| `junit.xml` | JUnit test results |
| `test-results/` | Populated fixture screenshots, contrast comparisons, CSS coverage, and failure recordings |
| `coverage-base.txt` | Effective base commit used by the CSS gate on PR/merge-queue runs |
| `css-coverage.json` | Core changed-CSS counts on PR/merge-queue runs, when the gate executes |
| `runtime.txt` | Node, npm, and Python versions |
| `fixture.json` | Core/Playwright versions, effective coverage base, and applicable pinned fixture provenance |
| `summary.md` | The text published in the job summary |

Playwright's GitHub reporter also annotates test failures in Actions. Traces,
screenshots, and videos are retained for failed tests; successful fixture tests
explicitly save populated screenshots. A failure before browser startup may
produce only the summary and fixture metadata. These artifacts contain synthetic
monitoring data; they are not evidence of a production deployment.

The core CSS gate compares the PR checkout against its effective base. On a
GitHub merge checkout it uses the merge commit's first parent, avoiding unrelated
base-branch changes. Merge-queue runs instead compare the whole group to
`merge_group.base_sha`, so earlier queued changes are included. Pushes and manual
runs execute the tests but have no comparison base, so their summary explicitly says the changed-CSS gate did not run. An
empty CSS diff is reported as no changed lines, not as whole-project coverage.

## Inspect a failure or rerun

Open the failed named check, read its job summary and annotations, and download
its evidence artifact. Extract it before opening the HTML report locally:

```sh
gh run download RUN_ID --repo Cacti/cacti --name theme-thold-attempt-1 --dir /tmp/cacti-theme-evidence
cd tests/e2e
mise exec node@22 -- npx --no-install playwright show-report /tmp/cacti-theme-evidence/html
```

Use the artifact name shown on that run for later attempts or other plugins.
The Actions UI can rerun failed jobs without rerunning successful plugin jobs.
Once the workflow is available on the repository default branch, run it
manually against a selected branch:

```sh
gh workflow run develop-theme-correctness.yml --repo Cacti/cacti --ref BRANCH
```

Local commands are in [CONTRIBUTING.md](../../CONTRIBUTING.md#theme-browser-checks).
Local runs use the same configuration and produce `tests/e2e/theme-results`.
Set `THEME_ARTIFACT_DIR` to an absolute path to preserve separate local runs.

## Refresh documentation screenshots

CI artifacts retain every run's evidence. Checked-in documentation keeps a small,
reviewed set so README images do not depend on expiring artifact URLs.

Download the relevant successful artifact, inspect its populated screenshot,
and copy the chosen PNG into `docs/validation/images/`. Preserve existing image
paths when updating a guide. Review the image diff and synthetic labels before
committing it with the relevant CSS or fixture change. Do not copy blank pages,
unrelated reports, browser profiles, or the entire artifact archive into Git.
The [validation index](README.md) links all screenshot guides.
