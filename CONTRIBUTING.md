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
