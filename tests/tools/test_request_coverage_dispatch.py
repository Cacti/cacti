"""Verify request coverage dispatch using only the runner's required tools.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest


class RequestDispatchTest(unittest.TestCase):
    def test_package_change_runs_the_signed_archive_probe(self):
        for path in ['lib/package.php', 'tests/fixtures/Core/PackageArchive.php']:
            with self.subTest(path=path):
                self.dispatch(path, 'PackageArchive.php')

    def test_queue_change_runs_the_queue_probe_without_ripgrep(self):
        self.dispatch('cli/queue_worker.php', 'queue')

    def test_html_change_runs_the_browser_probe(self):
        self.dispatch('lib/html.php', ['accessibility', 'library'])

    def test_documentation_change_runs_no_request_probe(self):
        self.dispatch('README.md', None)

    def test_installer_change_runs_the_installer_probe(self):
        self.dispatch('lib/installer.php', 'installer')

    def test_graph_change_runs_the_native_graph_probe(self):
        self.dispatch('lib/rrd.php', ['FallbackSourceIdentity.php', 'library'])

    def test_account_change_runs_the_native_audit_probe(self):
        self.dispatch('lib/functions.php', ['UserAdministrationAuditCoverage.php', 'library'])

    def test_auth_change_runs_the_native_auth_probe(self):
        self.dispatch('lib/auth.php', ['BoundaryContracts.php', 'library'])

    def test_device_library_runs_the_database_contracts(self):
        self.dispatch('lib/api_device.php', 'library')

    def test_library_fixture_changes_run_the_database_contracts(self):
        for name in ['LibraryBehavior', 'LibraryCollection', 'LibraryAuthentication',
                     'LibraryStorage', 'LibraryForms', 'LibraryDatabase', 'LibraryPlugins',
                     'LibraryReports', 'LibraryDevices', 'LibraryAccess', 'LibraryWeb', 'LibraryLogs',
                     'QueryProducer', 'PluginProducer']:
            with self.subTest(producer=name):
                self.dispatch(f'tests/fixtures/Core/{name}.php', 'library')

    def test_library_runtime_changes_run_the_database_contracts(self):
        self.dispatch('tests/e2e/Dockerfile', 'library')

    def test_library_schema_changes_run_the_database_contracts(self):
        self.dispatch('cacti.sql', 'library')

    def test_library_dependencies_run_the_database_contracts(self):
        for changed in ['lib/api_tree.php', 'lib/api_aggregate.php', 'lib/data_query.php',
                        'resource/snmp_queries/interface.xml', 'composer.lock',
                        'tests/fixtures/snmp-coverage/snmpd.conf', 'lib/boost.php',
                        'lib/spikekill.php', 'lib/database.php', 'lib/dbparallel.php',
                        'lib/clog_webapi.php', 'lib/dsstats.php', 'lib/remote_agent_transport.php',
                        'lib/html_validate.php', 'lib/html_utility.php', 'lib/html.php',
                        'host.php', 'clog.php', 'graph_templates.php', 'include/global_constants.php',
                        'include/global.php', 'user_admin.php', 'lib/plugins.php',
                        'lib/Auth/LocalAuthLoginProvider.php']:
            with self.subTest(changed=changed):
                additional = {'lib/html.php': ['accessibility'], 'host.php': ['accessibility'],
                              'include/global.php': ['BoundaryContracts.php'],
                              'user_admin.php': ['accessibility', 'UserAdministrationAuditCoverage.php']}
                self.dispatch(changed, additional.get(changed, []) + ['library'])

    def dispatch(self, changed, expected):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            repository = root / 'repo'
            repository.mkdir()
            tools = root / 'tools'
            tools.mkdir()
            for command in ['git', 'bash', 'grep', 'mkdir']:
                (tools / command).symlink_to(shutil.which(command))
            php = tools / 'php'
            php.write_text('#!' + shutil.which('bash') + '\n'
                           'script="${!#}"\n'
                           'printf "%s\\n" "${script##*/}" >> "$CACTI_PR_RAW_COVERAGE/probes"\n')
            php.chmod(0o700)
            env = dict(os.environ, PATH=str(tools))

            def git(*arguments):
                return subprocess.check_output(
                    ['git', '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null',
                     '-c', 'user.name=Coverage fixture', '-c', 'user.email=fixture@example.invalid',
                     *arguments], cwd=repository, env=env, text=True).strip()

            git('init', '--quiet')
            (repository / 'README.md').write_text('fixture\n')
            for name, script in [('queue', 'queue/run.sh'), ('accessibility', 'accessibility-coverage.sh'),
                                 ('installer', 'installer-coverage.sh'), ('library', 'library-behavior.sh')]:
                path = repository / 'tests/e2e' / script
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text('printf "%s\\n" ' + name + ' >> "$CACTI_PR_COVERAGE_OUTPUT/probes"\n')
            for script in ['Core/Auth/BoundaryContracts.php', 'Core/Rrd/FallbackSourceIdentity.php',
                           'UserAdministrationAuditCoverage.php', 'Core/PackageArchive.php']:
                path = repository / 'tests/fixtures' / script
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text('<?php // dispatch fixture\n')
            git('add', '.')
            git('commit', '--quiet', '-m', 'fixture base')
            base = git('rev-parse', 'HEAD')
            path = repository / changed
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('changed fixture\n')
            git('add', '.')
            git('commit', '--quiet', '-m', 'fixture change')
            output = root / 'coverage'
            runner = Path(__file__).with_name('run_request_coverage.sh').resolve()
            subprocess.run(['bash', str(runner)], cwd=repository, env=dict(
                env, BASE_COMMIT=base, CACTI_PR_COVERAGE_OUTPUT=str(output)), check=True)
            probe = output / 'probes'
            self.assertEqual(probe.read_text().splitlines() if probe.exists() else [],
                             expected if isinstance(expected, list) else [expected] if expected else [])


if __name__ == '__main__':
    unittest.main()
