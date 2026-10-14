# Plugin test dependencies

The shared `setup-plugin-dependencies` action installs the Cacti fixture's
committed Composer lock without changing it. LTS fixtures use this separate
locked Pest 3 harness. Fixtures targeting develop use the development tools in
the core lock, so two PHPUnit dependency graphs are never loaded together.
Both installs run as the runner user. Only downloaded Composer archives are
cached; installed vendor trees are rebuilt from the locks.

Plugin callers pin the tooling checkout and Cacti fixture to full commit SHAs.
Cacti source archives exclude `.github` and `tests`, so callers sparse-checkout
those tooling paths into `ci-tools` and invoke the composite action locally.
Do not use a remote `uses: Cacti/cacti/.github/actions/...@SHA` reference: the
GitHub Action archive does not contain these paths. They keep
their existing jobs, matrices, PHPUnit configuration, coverage thresholds,
database/schema setup, localization checks and polling steps. Keeping those
steps in the callers preserves check names and makes plugin differences visible.
No plugin-local Composer manifest is needed for test tooling. Stream retains
its separate runtime Composer manifest and lock.

The action exports `CACTI_PEST_BIN`; callers invoke `"$CACTI_PEST_BIN"` from
the Cacti directory with their existing PHPUnit configuration. The action does
not install PHP: callers use their existing pinned setup-php action and matrix.
Callers must request the extensions required by both the plugin and the core
fixture, including LDAP for the current LTS manifest.

Dependabot updates this harness independently. Regenerate the lock using
Composer with the configured PHP 8.2 platform, then validate and test it:

```sh
composer update --working-dir=tests/tools/plugin-harness
composer validate --strict --working-dir=tests/tools/plugin-harness
composer install --working-dir=tests/tools/plugin-harness
```

Before advancing the fixture/action pins, run Syslog, Thold, FlowView and Stream
against the supported PHP versions, including Linux polling/database checks.
Existing failing assertions must be reported separately; do not lower coverage
thresholds or suppress failures to migrate the harness.
