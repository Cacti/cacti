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
        self.dispatch('lib/html.php', 'accessibility')

    def test_sites_change_runs_the_sites_browser_probe(self):
        for path in ['sites.php', 'lib/sites.php', 'lib/Sites/SiteService.php',
                     'tests/e2e/sites_verify.php', 'tests/e2e/tests/sites-management.spec.ts',
                     'tests/e2e/definition_resolution.py']:
            with self.subTest(path=path):
                self.dispatch(path, ['sites', 'gprint'] if path == 'tests/e2e/definition_resolution.py' else 'sites')

    def test_gprint_change_runs_the_preset_browser_probe(self):
        for path in ['gprint_presets.php', 'lib/gprint.php', 'lib/Gprint/GprintPresetService.php',
                     'tests/e2e/gprint_verify.php', 'tests/e2e/tests/gprint-presets.spec.ts']:
            with self.subTest(path=path):
                self.dispatch(path, 'gprint')

    def test_documentation_change_runs_no_request_probe(self):
        self.dispatch('README.md', None)

    def test_installer_change_runs_the_installer_probe(self):
        self.dispatch('lib/installer.php', 'installer')

    def test_retired_theme_cleanup_runs_for_upgrade_and_pruning_changes(self):
        for path, expected in [
                ('install/functions.php', ['installer', 'RetiredThemeCleanup.php']),
                ('install/upgrades/1_3_0.php', ['queue', 'RetiredThemeCleanup.php']),
                ('tests/fixtures/Installer/RetiredThemeCleanup.php', ['RetiredThemeCleanup.php'])]:
            with self.subTest(path=path):
                self.dispatch(path, expected)

    def test_graph_change_runs_the_native_graph_probe(self):
        self.dispatch('lib/rrd.php', 'FallbackSourceIdentity.php')

    def test_account_change_runs_the_native_audit_probe(self):
        self.dispatch('lib/functions.php', 'UserAdministrationAuditCoverage.php')

    def test_auth_change_runs_the_native_auth_probe(self):
        self.dispatch('lib/auth.php', 'BoundaryContracts.php')

    def test_cdef_change_runs_the_isolated_suite(self):
        for path in ['lib/cdef.php', 'lib/Cdef/CdefResolver.php',
                     'tests/phpunit-cdef.xml', 'tests/Coverage/CdefCoverageTest.php',
                     'tests/Helpers/CdefCoverageBootstrap.php',
                     'tests/Unit/Core/Rrd/CdefResolverTest.php']:
            with self.subTest(path=path):
                self.dispatch(path, 'pest --configuration=tests/phpunit-cdef.xml')

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
                           'while [[ "$1" == "-d" ]]; do shift 2; done\n'
                           'script="$1"; shift\n'
                           'printf "%s" "${script##*/}" >> "$CACTI_PR_RAW_COVERAGE/probes"\n'
                           'if (( $# )); then printf " %s" "$@" >> "$CACTI_PR_RAW_COVERAGE/probes"; fi\n'
                           'printf "\\n" >> "$CACTI_PR_RAW_COVERAGE/probes"\n')
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
                                 ('installer', 'installer-coverage.sh'), ('sites', 'sites-coverage.sh'),
                                 ('gprint', 'gprint-coverage.sh')]:
                path = repository / 'tests/e2e' / script
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text('printf "%s\\n" ' + name + ' >> "$CACTI_PR_COVERAGE_OUTPUT/probes"\n')
            for script in ['Core/Auth/BoundaryContracts.php', 'Core/Rrd/FallbackSourceIdentity.php',
                           'UserAdministrationAuditCoverage.php', 'Core/PackageArchive.php',
                           'Installer/RetiredThemeCleanup.php']:
                path = repository / 'tests/fixtures' / script
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text('<?php // dispatch fixture\n')
            pest = repository / 'include/vendor/bin/pest'
            pest.parent.mkdir(parents=True)
            pest.write_text('<?php // isolated suite dispatch fixture\n')
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
