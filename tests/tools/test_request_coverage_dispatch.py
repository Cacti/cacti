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
    def test_queue_change_runs_the_queue_probe_without_ripgrep(self):
        self.dispatch('cli/queue_worker.php', 'queue')

    def test_html_change_runs_the_browser_probe(self):
        self.dispatch('lib/html.php', 'accessibility')

    def test_documentation_change_runs_no_request_probe(self):
        self.dispatch('README.md', None)

    def test_installer_change_runs_the_installer_probe(self):
        self.dispatch('lib/installer.php', 'installer')

    def test_graph_change_runs_the_native_graph_probe(self):
        self.dispatch('lib/rrd.php', 'FallbackSourceIdentity.php')

    def test_account_change_runs_the_native_audit_probe(self):
        self.dispatch('lib/functions.php', 'UserAdministrationAuditCoverage.php')

    def test_auth_change_runs_the_native_auth_probe(self):
        self.dispatch('lib/auth.php', 'BoundaryContracts.php')

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
                                 ('installer', 'installer-coverage.sh')]:
                path = repository / 'tests/e2e' / script
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text('printf "%s\\n" ' + name + ' >> "$CACTI_PR_COVERAGE_OUTPUT/probes"\n')
            for script in ['Core/Auth/BoundaryContracts.php', 'Core/Rrd/FallbackSourceIdentity.php',
                           'UserAdministrationAuditCoverage.php']:
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
                             [expected] if expected else [])


if __name__ == '__main__':
    unittest.main()
