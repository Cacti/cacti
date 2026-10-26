# Contributing to Cacti

## Setup

Cacti needs PHP (see `composer.json` for the supported range) and Node for the
front-end assets. `.nvmrc` pins the Node version CI uses.

    composer install
    npm ci

`npm ci` builds the JavaScript libraries under `include/js/` from the versions
pinned in `package.json`; see `build/README.md`.

## Checks

Run everything CI enforces:

    composer check

That chains the individual targets, each of which can be run on its own:

| Command | What it checks |
| --- | --- |
| `composer lint` | PHP syntax |
| `composer phpstan` | Static analysis at level 6 |
| `composer php-cs-fixer` | Formatting, without writing changes |
| `composer php-cs-fixit` | Formatting, applying changes |
| `composer hardening-patterns` | Required security patterns |
| `composer changelog` | CHANGELOG and `changelog.d` fragment format |
| `composer sink-guard` | Dangerous-call inventory against its baseline |
| `composer hotspot-guard` | Architectural hotspot baseline |
| `composer test` | PHP test suite |
| `composer test-js` | JavaScript unit tests |

Install the pre-commit hook with `composer install-hooks`.

## Theme browser checks

Theme fixtures run without a Cacti database, poller, or web server. From the
repository root, install the locked browser tooling:

```sh
cd tests/e2e
mise exec node@22 -- npm ci --ignore-scripts
mise exec node@22 -- npx --no-install playwright install chromium
CACTI_THEME_COVERAGE=1 mise exec node@22 -- npx --no-install playwright test --config playwright.theme.config.ts tests/develop-theme-correctness.spec.ts tests/develop-theme-cleanup.spec.ts --project=chromium
```

Install the pinned [plugin fixtures](docs/validation/plugin-theme-compatibility.md#reproduce)
and [RTM fixtures](docs/validation/rtm-theme-compatibility.md#reproduce) before
running their suites. From `tests/e2e`:

```sh
mise exec node@22 -- npx --no-install playwright test --config playwright.theme.config.ts tests/develop-plugin-theme-compatibility.spec.ts --project=chromium
mise exec node@22 -- npx --no-install playwright test --config playwright.theme.config.ts tests/develop-rtm-theme-compatibility.spec.ts tests/develop-rtm-surfaces.spec.ts --project=chromium
mise exec node@22 -- npx --no-install playwright show-report theme-results/html
```

Each command replaces `theme-results`; set `THEME_ARTIFACT_DIR` to keep runs
separate. Use `--grep 'thold '` to run just Thold, as its matrix job does.
`CACTI_CHROMIUM_EXECUTABLE` can select an existing Chromium executable.
Tests verify fixture revisions before using their assets.

The [Actions guide](docs/validation/github-actions.md) explains check names,
100% changed executable CSS coverage, artifacts, failure diagnosis, and manual
runs. [Validation scope](docs/validation/README.md) distinguishes these browser
component fixtures from backend E2E and describes the unavailable RTM chart
runtime. New screenshots must contain populated synthetic monitoring data.

Run the workflow helper checks from the repository root:

```sh
mise exec python@3.12 -- python tests/tools/test_theme_ci.py
actionlint .github/workflows/develop-theme-correctness.yml .github/workflows/theme-browser-suite.yml
```

## Changelog

Do not edit `CHANGELOG` directly. Add a fragment under `changelog.d/` named for
the issue or pull request number, as described in `changelog.d/README.md`:

    changelog.d/7692.issue
    changelog.d/7634.feature
    changelog.d/GHSA-wpjq-m269-mghj.security

Each fragment is a new file, so concurrent pull requests do not conflict.

## Baselines

`composer sink-guard` and `composer hotspot-guard` compare against checked-in
baselines. If a change legitimately adds or removes an entry, refresh it:

    tests/security/build_sink_inventory.sh | tr -d '\r' | LC_ALL=C sort > tests/security/baselines/sink_inventory.baseline.tsv
    tests/security/build_architectural_helper_report.sh --hotspots | tr -d '\r' | LC_ALL=C sort > tests/security/baselines/architectural_hotspots.baseline.tsv
