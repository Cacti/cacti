# Installer Domain Architecture

The installer is being migrated to a PHP 8.1-compatible bounded context under
`lib/Installer`. The legacy `Installer` class remains an interface adapter
until the web, JSON, CLI, and background entry points have moved to the new
application layer.

## Boundaries

`Domain` contains the installation aggregate, state transitions, mode-specific
plans, and task ordering. It has no database, filesystem, process, HTML, or
Cacti global-function dependencies.

`Application` accepts an installation aggregate and coordinates its plan using
ports. It owns task orchestration and makes the failure boundary explicit.

`Infrastructure` implements platform and Cacti-specific concerns. Platform
classification uses `PHP_OS_FAMILY` and explicitly supports Windows, Linux,
FreeBSD, and other Unix-like systems.

`LegacyInstallationFactory` is the anti-corruption layer. It maps the historic
integer mode contract into the domain enum. No new domain type should expose
legacy settings-table or UI concepts.

`CactiInstallationTaskRunner` is the execution anti-corruption layer. It
maps an `InstallTask` to `CactiLegacyInstallOperations`, contains the Cacti
global-function/settings/process vocabulary, and converts legacy exceptions to
the application result protocol. New Cacti adapters belong behind that
interface; they must not be imported into `Domain` or `Application`.

## Required invariants

- A license is accepted before confirmation.
- A mode is selected before confirmation or planning.
- Only confirmed installations can run.
- A task failure stops the plan and marks the installation failed.
- A completed, failed, or running installation cannot be reconfigured.

## Development workflow

Add a failing PHPUnit test under `tests/Installer` before changing the domain
or application code. Run:

```sh
include/vendor/bin/phpunit --configuration phpunit.installer.xml
include/vendor/bin/phpstan analyse --configuration phpstan.installer.neon --no-progress
```

The PHPStan configuration is intentionally level 8 and scopes analysis to the
modern installer bounded context and its tests. Do not weaken the level or add
ignores; fix the model or its types instead.

## Migration sequence

1. Move wizard decisions into domain commands and typed response models.
2. Implement Cacti settings and task-runner adapters for the application ports.
3. Move database conversion, migrations, package import, provisioning, and
   collector synchronisation into idempotent task runners.
4. Replace HTML string generation with a presentation adapter.
5. Switch web, CLI, and background entry points to the application façade and
   remove the legacy `Installer` class.
