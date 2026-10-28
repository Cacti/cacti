"""Exercise checkout preparation with controlled external commands.

Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
"""
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
from shell_coverage import shell_command

ROOT = Path(__file__).resolve().parents[2]


class PrepareInstallTest(unittest.TestCase):
    def run_preparation(self, mode):
        with tempfile.TemporaryDirectory(prefix='cacti preparation ') as directory:
            root = Path(directory) / 'checkout with spaces'
            (root / 'cli').mkdir(parents=True)
            tools = root / 'tools'
            tools.mkdir()
            script = root / 'cli/prepare_install.sh'
            shutil.copyfile(ROOT / 'cli/prepare_install.sh', script)
            log = root / 'commands.jsonl'
            command = ('#!' + sys.executable + '\n'
                       'import json, os, sys\nfrom pathlib import Path\n'
                       'name=Path(sys.argv[0]).name\n'
                       'log=Path(os.environ["PREPARATION_LOG"])\n'
                       'old=[json.loads(line) for line in log.read_text().splitlines()] if log.exists() else []\n'
                       'with log.open("a") as output: output.write(json.dumps([name, *sys.argv[1:]])+"\\n")\n'
                       'mode=os.environ["PREPARATION_MODE"]\n'
                       'if name=="composer" and mode=="install-failure" and sys.argv[1]=="install": sys.exit(7)\n'
                       'if name=="composer" and mode=="platform-failure" and sys.argv[1]=="check-platform-reqs": sys.exit(7)\n'
                       'if name=="npm" and mode=="npm-failure": sys.exit(9)\n'
                       'if name=="php" and mode=="retry" and not any(row[0]=="php" for row in old): sys.exit(1)\n')
            for name in ['composer', 'npm', 'php']:
                path = tools / name
                path.write_text(command)
                path.chmod(0o700)
            result = subprocess.run(shell_command(script, ROOT / 'cli/prepare_install.sh', '/bin/bash'),
                                    cwd=root, env={**os.environ, 'PATH': str(tools) + os.pathsep + os.environ['PATH'],
                                                   'PREPARATION_LOG': str(log), 'PREPARATION_MODE': mode},
                                    capture_output=True, text=True, timeout=30)
            calls = [json.loads(line) for line in log.read_text().splitlines()]
            return result, calls

    def test_success_verifies_platform_and_preflight_after_building_assets(self):
        result, calls = self.run_preparation('success')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual([row[0] for row in calls], ['composer', 'composer', 'npm', 'npm', 'php'])
        self.assertEqual(calls[1], ['composer', 'check-platform-reqs', '--no-dev'])
        self.assertEqual(calls[2], ['npm', 'ci', '--ignore-scripts', '--no-audit', '--no-fund'])
        self.assertEqual(calls[3], ['npm', 'run', 'postinstall'])
        self.assertEqual(calls[4], ['php', 'install/preflight.php'])

    def test_missing_package_files_trigger_one_reinstall_and_repeat_preflight(self):
        result, calls = self.run_preparation('retry')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual([row[0] for row in calls], ['composer', 'composer', 'npm', 'npm', 'php', 'composer', 'php'])
        self.assertEqual(calls[5], ['composer', 'reinstall', '--optimize-autoloader', '--prefer-dist',
                                    '--no-interaction', '--no-progress', '*'])

    def test_dependency_failures_stop_before_the_next_preparation_stage(self):
        for mode, expected in [('install-failure', ['composer']),
                               ('platform-failure', ['composer', 'composer']),
                               ('npm-failure', ['composer', 'composer', 'npm'])]:
            with self.subTest(mode=mode):
                result, calls = self.run_preparation(mode)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual([row[0] for row in calls], expected)


if __name__ == '__main__':
    unittest.main()
