---
name: cacti-database-upgrade
description: Develop and verify a Cacti core or plugin migration against fresh, populated and interrupted installations.
---

Identify the earliest supported source schema for the change, its version marker, and the actual installer or plugin upgrade entry point. Compare the [fresh core schema](../../../cacti.sql) with the destination reached by [the upgrade chain](../../../install/upgrades). For a plugin, use its install and upgrade callbacks and its own version state.

Seed a disposable previous-version database with values that exercise the transformation. Include rows whose keys, defaults or relationships could change. For multi-step migrations, identify which operations can commit independently and when the installer considers the version complete. Use the target database engine's actual DDL behavior when designing retries.

Exercise a fresh install and an upgrade. Interrupt the upgrade after an intermediate write, inspect persisted state, then rerun the same entry point. Compare schema, indexes, representative values, relationships and version state with the uninterrupted result. Check that the failure reaches the caller and a retry preserves completed work. For background migrations, also distinguish a departed worker from an active owner before resuming. The [installer migration status tests](../../../tests/Unit/Installer/InstallerMigrationStatusTest.php) cover failure reporting; a populated database is needed to demonstrate data preservation.
