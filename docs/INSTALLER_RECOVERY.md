# Installing and recovering Cacti develop/1.3

Use the packaged `cacti-*.tar.gz` release asset. GitHub's generated source
archives do not include Composer dependencies. The web installer requires a
complete application tree and does not download or write dependencies.

For a source checkout, select the supported PHP and Node versions through
`mise`, then run `bash cli/prepare_install.sh`. This installs the locked
production Composer dependencies, checks their platform requirements, and
builds the locked frontend assets. Run preparation before deploying the tree;
the web-server account does not need write access to application code.
The shared installer preflight checks the PHP runtime, extensions, installed
package versions, Composer platform check, autoloader, required dependency entry
points, and core frontend assets.
CLI and web must both pass in their own PHP environment.

Before approving the upgrade plan:

1. Stop polling and scheduled tasks on the primary and remote collectors.
   Prevent web, plugin, and external integration writes during the backup and upgrade.
2. Back up the primary database and any independent remote collector databases.
   Use a consistent snapshot appropriate for every table engine in the deployment;
   `--single-transaction` alone does not provide consistency for nontransactional tables.
3. Back up `include/config.php`, external configuration, plugin code and configuration,
   local custom scripts/resources/templates, application files, and RRD data.
   Preserve ownership and permissions. Protect backups as sensitive material.
4. Record the source Cacti release, PHP/database versions, enabled plugins,
   collector topology, and backup locations. Restore the backups in a disposable
   environment before relying on them.
5. Review planned migrations, template imports, table conversions, plugin changes,
   and collectors. Allow enough time and disk space for large-table conversions.

Database DDL generally commits independently; an upgrade is not one reversible
transaction. On failure, keep polling and application writes stopped. Preserve
the report and take a separate snapshot of the failed database before diagnosis.
Do not blindly rerun a partially completed migration: identify its failed step
and any committed changes first.

To restore, stop Cacti and restore the matching pre-upgrade database,
configuration, application/plugin files, and RRD data together. Restore the
corresponding remote collector state before resuming distributed polling.
Verify permissions, connectivity, authentication, graphs and collector operation
in the restored installation before reopening writes.

After an upgrade, check local and configured external authentication, session
and CSRF handling, console/device/graph pages, customized templates, plugin
compatibility, and synchronization of each enabled collector. Completion warnings
identify collectors requiring manual full synchronization. A successful schema
check does not establish external LDAP/IdP or remote database availability.

The installer retains its latest bounded report in `maintenance_lastrun_installer_report`,
outside the temporary installer settings. Administrators with the installer
realm can download it from `install/report.php`. The report includes a run ID,
step timings, success/failure status, and recovery guidance. It deliberately
excludes options, SQL, commands, credentials, database URLs and exception text.
The report is a diagnostic checkpoint record; it does not automatically replay
non-idempotent operations or provide database rollback.

The upgrade test fixtures are synthetic. CI starts from the tagged schemas for
1.2.22 and 1.2.31 and records the source tag commit. It adds an LDAP domain/user,
a custom template, device, data source, RRD file and setting, compatible/missing
plugins, 100,000 buffered polling rows,
and two separate collector databases on the isolated database server. The matrix
uses MariaDB 10.11 and 11.8 with PHP 8.4. The component coverage matrix uses
PHP 8.3, 8.4 and 8.5. These fixtures verify preservation and failure handling;
they are not a production-scale polling or large-table performance benchmark.

Run a disposable fixture with Docker available and the browser dependencies
installed (`mise exec node@22 -- npm ci --prefix tests/e2e` and
`mise exec node@22 -- npx --no-install --prefix tests/e2e playwright install chromium`):

```sh
mise exec node@22 -- bash tests/e2e/installer_resilience_docker.sh 1.2.31 mariadb:10.11
```

The harness reads the starting schema directly from the `release/<version>` git
tag and prints its commit ID. It then applies the synthetic populated fixture;
never generate fixtures by committing an actual installation's database dump.
To add a supported starting release, fetch its tag, review the populated fixture
against that release's schema, add the version to the CI source matrix, and run
the same production upgrade entry points and preservation assertions. Review
authentication, plugin and collector fixture fields when their schema changes.
For local diagnosis only, `INSTALLER_KEEP_FAILURE=1` retains the named disposable
fixture after a failure. Remove its containers, volumes, network and image after
inspection. CI always uses the default cleanup behavior.
