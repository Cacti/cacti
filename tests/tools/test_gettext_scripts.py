"""Exercise the repository scripts with real gettext tools in disposable trees."""
import os
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
TOOLS = ['xgettext', 'msgmerge', 'msgfmt', 'realpath']


@unittest.skipUnless(all(shutil.which(tool) for tool in TOOLS), 'GNU gettext and realpath required')
class GettextScriptsTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='cacti gettext ')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name) / 'checkout with spaces'
        self.locales = self.root / 'locales'
        (self.locales / 'po').mkdir(parents=True)
        (self.locales / 'LC_MESSAGES').mkdir()
        (self.root / 'include').mkdir()
        (self.root / 'include/cacti_version').write_text('1.3.0-dev\n')
        (self.root / 'input with spaces.php').write_text("<?php __('Fixture greeting');\n")
        (self.locales / 'po/language with spaces.po').write_text(
            'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n'
            '\nmsgid "Fixture greeting"\nmsgstr "Fixture translation"\n'
        )
        for name in ['update-pot.sh', 'build_gettext.sh', 'build_mo.sh']:
            shutil.copyfile(ROOT / 'locales' / name, self.locales / name)
        self.env = {**os.environ, 'LC_ALL': 'C'}
        # The build script already requires GNU sed. Homebrew names it gsed.
        if shutil.which('gsed'):
            tools = self.root / 'tools'
            tools.mkdir()
            (tools / 'sed').symlink_to(shutil.which('gsed'))
            self.env['PATH'] = str(tools) + os.pathsep + self.env['PATH']

    def run_script(self, name, cwd=None, env=None):
        return subprocess.run(
            ['/bin/sh', str(self.locales / name)], cwd=cwd or self.root,
            env=env or self.env, capture_output=True, text=True, timeout=30,
        )

    def test_update_pot_handles_checkout_and_source_names_with_spaces(self):
        result = self.run_script('update-pot.sh', cwd=self.locales)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('msgid "Fixture greeting"', (self.locales / 'po/cacti.pot').read_text())

    def test_build_gettext_preserves_translations_and_compiles_spaced_names(self):
        result = self.run_script('build_gettext.sh')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('Fixture translation', (self.locales / 'po/language with spaces.po').read_text())
        self.assertTrue((self.locales / 'LC_MESSAGES/language with spaces.mo').is_file())

    def test_build_mo_accepts_spaced_filenames_and_empty_catalog_directory(self):
        result = self.run_script('build_mo.sh', cwd=self.locales)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertTrue((self.locales / 'LC_MESSAGES/language with spaces.mo').is_file())
        (self.locales / 'po/language with spaces.po').unlink()
        result = self.run_script('build_mo.sh', cwd=self.locales)
        self.assertEqual(result.returncode, 0, result.stderr)

    def test_missing_tools_report_errors_only_to_stderr(self):
        tools = self.root / 'missing-tools'
        tools.mkdir()
        for name in ['update-pot.sh', 'build_gettext.sh']:
            result = self.run_script(name, env={**self.env, 'PATH': str(tools)})
            self.assertNotEqual(result.returncode, 0)
            self.assertIn('unable to locate realpath', result.stderr)
            self.assertNotIn('ERROR:', result.stdout)

        for tool in ['realpath', 'dirname']:
            (tools / tool).symlink_to(shutil.which(tool))
        for name in ['update-pot.sh', 'build_gettext.sh']:
            result = self.run_script(name, env={**self.env, 'PATH': str(tools)})
            self.assertNotEqual(result.returncode, 0)
            self.assertIn('Unable to locate xgettext', result.stderr)
            self.assertNotIn('ERROR:', result.stdout)

class DockerToolArgumentsTest(unittest.TestCase):
    def test_custom_base_image_is_used_by_the_test_build(self):
        with tempfile.TemporaryDirectory(prefix='cacti docker args ') as directory:
            root = Path(directory)
            docker = root / 'docker'
            docker.write_text(
                '#!/usr/bin/env python3\nimport json, os, sys\n'
                'with open(os.environ["CACTI_DOCKER_ARG_LOG"], "a") as log:\n'
                '    log.write(json.dumps(sys.argv[1:]) + "\\n")\n'
            )
            docker.chmod(0o700)
            log = root / 'arguments.jsonl'
            result = subprocess.run(
                ['/bin/bash', str(ROOT / 'tests/tools/docker_pest.sh'), '--testsuite=Unit'],
                env={**os.environ, 'PATH': str(root) + os.pathsep + os.environ['PATH'],
                     'CACTI_DOCKER_BASE_IMAGE': 'fixture-base:1.3',
                     'CACTI_DOCKER_TEST_IMAGE': 'fixture-tests:1.3',
                     'CACTI_DOCKER_ARG_LOG': str(log)},
                capture_output=True, text=True, timeout=30,
            )
            self.assertEqual(result.returncode, 0, result.stderr)
            commands = [json.loads(line) for line in log.read_text().splitlines()]
            self.assertEqual(len(commands), 3)
            self.assertEqual(commands[0][commands[0].index('--tag') + 1], 'fixture-base:1.3')
            self.assertEqual(commands[1][commands[1].index('--build-arg') + 1], 'CACTI_TEST_BASE=fixture-base:1.3')
            self.assertIn('fixture-tests:1.3', commands[2])
            self.assertEqual(commands[2][-1], '--testsuite=Unit')



if __name__ == '__main__':
    unittest.main()
