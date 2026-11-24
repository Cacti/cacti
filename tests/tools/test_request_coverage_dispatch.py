"""Verify request coverage dispatch using only the runner's required tools.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

GROUPS = Path(__file__).with_name('request_coverage.d')

# The probes that existed when the list moved into request_coverage.d, which
# the expectations in RequestDispatchTest name in full. A group added later
# declares its own changed paths in "# dispatch:" lines instead, so it adds a
# file to that directory and leaves these lists and expectations alone.
SHELL_PROBES = {'tests/e2e/queue/run.sh': 'queue', 'tests/e2e/accessibility-coverage.sh': 'accessibility',
                'tests/e2e/installer-coverage.sh': 'installer'}
PHP_PROBES = ['tests/fixtures/' + script for script in [
    'Core/Auth/BoundaryContracts.php', 'Core/Rrd/FallbackSourceIdentity.php',
    'UserAdministrationAuditCoverage.php', 'Core/ScriptSubstitutionMatrix.php', 'Core/ReportMessageDelivery.php', 'Core/DeviceCollectorMessages.php',
    'Core/MessageFormatting.php', 'Core/ExternalLinkMessage.php', 'Core/TrustedActionMessages.php',
    'Core/ConstantTimeGuardMatrix.php', 'Core/PackageManifestMatrix.php',
    'Core/PackageSaveMatrix.php', 'Core/PackageArchive.php', 'Core/PackageDiffBoundary.php',
    'Core/PackageRepositoryWorkspace.php', 'Core/PackageImportFileStatus.php',
    'Installer/RetiredThemeCleanup.php', 'ScriptServerLogMaskingCoverage.php', 'BoostBulkDrainCoverage.php']
] + ['tests/Unit/fixtures/graph_group_probe.php']


def probes_run(changed, shell_probes, php_probes):
    """Return what the runner starts for one changed path, in order.

    A shell probe reports its marker and a PHP probe its file name.
    """
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
                       'for script in "$@"; do [[ -f "$script" ]] && break; done\n'
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
        for script, name in shell_probes.items():
            path = repository / script
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('printf "%s\\n" ' + name + ' >> "$CACTI_PR_COVERAGE_OUTPUT/probes"\n')
        for script in php_probes:
            path = repository / script
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('<?php // dispatch fixture\n')
        git('add', '.')
        git('commit', '--quiet', '-m', 'fixture base')
        base = git('rev-parse', 'HEAD')
        path = repository / changed
        path.parent.mkdir(parents=True, exist_ok=True)
        # Appending keeps a changed shell probe runnable.
        with path.open('a') as file:
            file.write('# changed fixture\n')
        git('add', '.')
        git('commit', '--quiet', '-m', 'fixture change')
        output = root / 'coverage'
        runner = Path(__file__).with_name('run_request_coverage.sh').resolve()
        subprocess.run(['bash', str(runner)], cwd=repository, env=dict(
            env, BASE_COMMIT=base, CACTI_PR_COVERAGE_OUTPUT=str(output)), check=True)
        probe = output / 'probes'
        return probe.read_text().splitlines() if probe.exists() else []


def group_probes(group):
    """Return the scripts one probe group names, as the runner would see them."""
    listing = 'run_probe() { printf "%s\\n" "$1"; }; run_php_probe() { run_probe "$1"; }; source "$1"'
    return sorted(set(subprocess.check_output(
        ['bash', '-c', 'set -euo pipefail; ' + listing, 'groups', str(group)], text=True).split()))


class RequestDispatchTest(unittest.TestCase):
    def test_package_change_runs_the_signed_archive_probe(self):
        self.dispatch('lib/package.php', ['MessageFormatting.php', 'PackageArchive.php', 'PackageRepositoryWorkspace.php', 'PackageImportFileStatus.php'])
        self.dispatch('tests/fixtures/Core/PackageArchive.php', 'PackageArchive.php')

    def test_queue_change_runs_the_queue_probe_without_ripgrep(self):
        self.dispatch('cli/queue_worker.php', 'queue')

    def test_html_change_runs_the_browser_probe(self):
        self.dispatch('lib/html.php', 'accessibility')

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

    def test_device_change_runs_the_collector_notification_probe(self):
        self.dispatch('lib/api_device.php', ['DeviceCollectorMessages.php', 'MessageFormatting.php'])

    def test_ui_messages_run_their_rendering_probes(self):
        self.dispatch('link.php', 'ExternalLinkMessage.php')
        self.dispatch('lib/plugins.php', ['TrustedActionMessages.php', 'MessageFormatting.php'])

    def test_account_change_runs_the_native_audit_probe(self):
        self.dispatch('lib/functions.php', ['ScriptSubstitutionMatrix.php', 'ReportMessageDelivery.php', 'MessageFormatting.php', 'UserAdministrationAuditCoverage.php', 'graph_group_probe.php'])

    def test_auth_change_runs_the_native_auth_probe(self):
        self.dispatch('lib/auth.php', 'BoundaryContracts.php')

    def test_script_server_change_runs_the_log_masking_probe(self):
        for path in ['script_server.php', 'lib/poller.php', 'tests/fixtures/ScriptServerLogMaskingCoverage.php']:
            with self.subTest(path=path):
                self.dispatch(path, 'ScriptServerLogMaskingCoverage.php')

    def test_two_factor_change_runs_the_guard_fixture(self):
        self.dispatch('auth_2fa.php', 'ConstantTimeGuardMatrix.php')

    def test_boost_drain_change_runs_the_drain_probe(self):
        for path in ['poller_boost.php', 'lib/boost.php', 'tests/fixtures/ProbeLoader.php', 'tests/fixtures/BoostOnDemandFailureProbe.php']:
            with self.subTest(path=path):
                self.dispatch(path, 'BoostBulkDrainCoverage.php')

    def test_repository_change_runs_manifest_and_save_fixtures(self):
        self.dispatch('package_repos.php', ['MessageFormatting.php', 'PackageManifestMatrix.php', 'PackageSaveMatrix.php'])

    def test_import_change_runs_the_manifest_fixture(self):
        self.dispatch('lib/import.php', ['PackageManifestMatrix.php', 'PackageImportFileStatus.php'])

    def test_package_import_change_runs_the_diff_boundary_probe(self):
        self.dispatch('tests/fixtures/Core/PackageDiffBoundary.php', 'PackageDiffBoundary.php')

    def dispatch(self, changed, expected):
        self.assertEqual(probes_run(changed, SHELL_PROBES, PHP_PROBES),
                         expected if isinstance(expected, list) else [expected] if expected else [])


class ProbeGroupTest(unittest.TestCase):
    def test_each_group_declares_changed_paths_that_reach_every_probe(self):
        for group in sorted(GROUPS.glob('*.sh')):
            with self.subTest(group=group.name):
                paths = [path for line in group.read_text().splitlines() if line.startswith('# dispatch:')
                         for path in line.split()[2:]]
                scripts = group_probes(group)
                self.assertTrue(paths, 'the group needs a "# dispatch:" line naming changed paths')
                self.assertTrue(scripts, 'the group starts no probe')
                shell = {script: script for script in scripts if script.endswith('.sh')}
                php = [script for script in scripts if script not in shell]
                reached = set()
                for path in paths:
                    ran = set(probes_run(path, shell, php))
                    self.assertTrue(ran, path + ' reaches no probe of this group')
                    reached |= ran
                self.assertEqual(reached, set(shell) | {script.rsplit('/', 1)[-1] for script in php})

    def test_documentation_change_reaches_no_group(self):
        scripts = [script for group in GROUPS.glob('*.sh') for script in group_probes(group)]
        shell = {script: script for script in scripts if script.endswith('.sh')}
        self.assertEqual(probes_run('README.md', shell, [script for script in scripts if script not in shell]), [])


if __name__ == '__main__':
    unittest.main()
