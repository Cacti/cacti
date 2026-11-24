"""Exercise the repository scripts with real gettext tools in disposable trees."""
import os
import sys
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
from shell_coverage import shell_command

ROOT = Path(__file__).resolve().parents[2]
CHECK = 'check_message_template.sh'
BROKEN_CATALOG = (
    'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n'
    '\n#, php-format\nmsgid "Fixture %s total"\nmsgstr "Fixture %a total"\n'
)
TOOLS = ['xgettext', 'msgmerge', 'msgfmt', 'msgcat', 'realpath']


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
        self.tools = self.root / 'tests/tools'
        self.tools.mkdir(parents=True)
        shutil.copyfile(ROOT / 'tests/tools' / CHECK, self.tools / CHECK)
        self.env = {**os.environ, 'LC_ALL': 'C'}
        # Exercise BSD sed on macOS even when GNU tools are first in PATH.
        if sys.platform == 'darwin':
            tools = self.root / 'tools'
            tools.mkdir()
            (tools / 'sed').symlink_to('/usr/bin/sed')
            self.env['PATH'] = str(tools) + os.pathsep + self.env['PATH']

    def run_script(self, name, cwd=None, env=None):
        return subprocess.run(
            shell_command(self.locales / name, ROOT / 'locales' / name), cwd=cwd or self.root,
            env=env or self.env, capture_output=True, text=True, timeout=30,
        )

    def test_update_pot_handles_checkout_and_source_names_with_spaces(self):
        result = self.run_script('update-pot.sh', cwd=self.locales)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('msgid "Fixture greeting"', (self.locales / 'po/cacti.pot').read_text())

    def test_build_gettext_preserves_translations_and_compiles_spaced_names(self):
        result = self.run_script('build_gettext.sh')
        self.assertEqual(result.returncode, 0, result.stderr)
        pot = (self.locales / 'po/cacti.pot').read_text()
        self.assertIn('Cacti Developers <developers@cacti.net>', pot)
        self.assertIn('charset=UTF-8', pot)
        self.assertNotIn('FULL NAME <EMAIL@ADDRESS>', pot)
        # A GNU-only word-boundary anchor once left the closing bracket behind.
        self.assertNotIn('>>', pot)
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

    def run_check(self, *arguments, env=None):
        return subprocess.run(
            shell_command(self.tools / CHECK, ROOT / 'tests/tools' / CHECK) + list(arguments), cwd=self.root,
            env=env or self.env, capture_output=True, text=True, timeout=60,
        )

    def git(self, *arguments):
        result = subprocess.run(
            ['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid',
             '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', *arguments],
            cwd=self.root, env=self.env, capture_output=True, text=True, timeout=30,
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        return result.stdout.strip()

    def commit_all(self, message):
        self.git('add', '-A')
        self.git('commit', '-q', '-m', message)
        return self.git('rev-parse', 'HEAD')

    def start_history(self, source):
        """Commit a base with the given source and a template that matches it."""
        (self.root / 'input with spaces.php').write_text(source)
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        self.git('init', '-q')
        return self.commit_all('base')

    def set_source(self, source):
        (self.root / 'input with spaces.php').write_text(source)

    def test_update_pot_reads_nested_core_source_and_skips_other_trees(self):
        for folder in ['lib/Auth', 'plugins/fixture', 'tests/Unit', 'include/vendor/package', '.hidden', 'lib/.cache/deep']:
            (self.root / folder).mkdir(parents=True)
            (self.root / folder / 'strings.php').write_text("<?php __('From " + folder + "');\n")
        result = self.run_script('update-pot.sh')
        self.assertEqual(result.returncode, 0, result.stderr)
        pot = (self.locales / 'po/cacti.pot').read_text()
        self.assertIn('msgid "From lib/Auth"', pot)
        self.assertIn('#: lib/Auth/strings.php\n', pot)
        for folder in ['plugins/fixture', 'tests/Unit', 'include/vendor/package', '.hidden', 'lib/.cache/deep']:
            self.assertNotIn('From ' + folder, pot)
        self.assertIn('Cacti Developers <developers@cacti.net>', pot)
        self.assertIn('charset=UTF-8', pot)
        self.assertEqual([path.name for path in (self.locales / 'po').glob('cacti.pot.*')], [])

    def test_update_pot_keeps_the_creation_date_of_the_existing_template(self):
        template = self.locales / 'po/cacti.pot'
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        pot = template.read_text()
        stamped = pot.replace(
            next(line for line in pot.splitlines() if line.startswith('"POT-Creation-Date:')),
            '"POT-Creation-Date: 2001-02-03 04:05+0600\\n"',
        )
        template.write_text(stamped)
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        self.assertEqual(template.read_text(), stamped)
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        self.assertEqual(template.read_text(), stamped)

        # A changed string is picked up while the date stays.
        (self.root / 'input with spaces.php').write_text("<?php __('Fixture farewell');\n")
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        updated = template.read_text()
        self.assertIn('msgid "Fixture farewell"', updated)
        self.assertIn('"POT-Creation-Date: 2001-02-03 04:05+0600\\n"', updated)

        # Without a previous template, or with an unusable date, xgettext's value is used.
        template.write_text('"POT-Creation-Date: not a date\\n"\n')
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        self.assertNotIn('not a date', template.read_text())
        template.unlink()
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        self.assertRegex(template.read_text(), r'"POT-Creation-Date: \d{4}-\d{2}-\d{2} \d{2}:\d{2}[+-]\d{4}\\n"')

    def test_update_pot_writes_a_named_file_and_leaves_the_template_alone(self):
        template = self.locales / 'po/cacti.pot'
        template.write_text('committed\n')
        result = subprocess.run(
            shell_command(self.locales / 'update-pot.sh', ROOT / 'locales/update-pot.sh') + ['po/other.pot'],
            cwd=self.locales, env=self.env, capture_output=True, text=True, timeout=30,
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('msgid "Fixture greeting"', (self.locales / 'po/other.pot').read_text())
        absolute = self.root / 'absolute.pot'
        result = subprocess.run(
            shell_command(self.locales / 'update-pot.sh', ROOT / 'locales/update-pot.sh') + [str(absolute)],
            cwd=self.root, env=self.env, capture_output=True, text=True, timeout=30,
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('msgid "Fixture greeting"', absolute.read_text())
        self.assertEqual(template.read_text(), 'committed\n')

    def test_template_check_fails_until_the_template_is_regenerated(self):
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        result = self.run_check()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn('OK: locales/po/cacti.pot matches the source', result.stdout)

        template = self.locales / 'po/cacti.pot'
        committed = template.read_text()
        self.set_source("<?php __('Fixture farewell');\n")
        result = self.run_check()
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn('-msgid "Fixture greeting"', result.stdout)
        self.assertIn('+msgid "Fixture farewell"', result.stdout)
        self.assertIn("Run 'sh locales/update-pot.sh'", result.stdout)
        self.assertEqual(template.read_text(), committed)

        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        self.assertEqual(self.run_check().returncode, 0)

    def test_template_check_without_a_base_compares_the_whole_tree(self):
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        self.set_source("<?php __('Fixture greeting'); __('Fixture drift');\n")
        # Not a git checkout.
        result = self.run_check()
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn('NOTE: this is not a git checkout; comparing the whole tree', result.stdout)
        self.assertIn('+msgid "Fixture drift"', result.stdout)

        # A git checkout with no develop branch and an unknown base.
        self.git('init', '-q')
        self.commit_all('only commit')
        result = self.run_check()
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn('neither upstream/develop nor origin/develop exists; comparing the whole tree', result.stdout)
        result = self.run_check('no-such-revision')
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn("base 'no-such-revision' is not available in this checkout; comparing the whole tree", result.stdout)
        self.assertIn('+msgid "Fixture drift"', result.stdout)

    def test_template_check_passes_a_clean_change_on_a_drifted_base(self):
        # develop already has a string the template lacks.
        self.start_history("<?php __('Fixture greeting');\n")
        self.set_source("<?php __('Fixture greeting'); __('Fixture drift');\n")
        base = self.commit_all('drifted base')
        (self.root / 'lib').mkdir()
        (self.root / 'lib/other.php').write_text('<?php echo 1;\n')
        self.commit_all('change that has no strings')
        result = self.run_check(base)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn('NOTE: 1 message(s) were already out of sync', result.stdout)
        self.assertNotIn('+msgid', result.stdout)
        self.assertIn('OK: locales/po/cacti.pot matches the source', result.stdout)
        # Without the base the same tree fails, which is what the base removes.
        self.assertEqual(self.run_check('no-such-revision').returncode, 1)

    def test_template_check_fails_a_string_added_without_regenerating(self):
        base = self.start_history("<?php __('Fixture greeting');\n")
        self.git('update-ref', 'refs/remotes/upstream/develop', base)
        self.set_source("<?php __('Fixture greeting'); __('Fixture farewell');\n")
        self.commit_all('add a string')
        # The base comes from upstream/develop when none is named.
        result = self.run_check()
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn('Comparing with the base', result.stdout)
        self.assertIn('+msgid "Fixture farewell"', result.stdout)
        self.assertNotIn('Fixture greeting"', result.stdout)
        self.assertIn("Run 'sh locales/update-pot.sh'", result.stdout)
        # The environment variable names the base as well.
        result = self.run_check(env={**self.env, 'CACTI_I18N_BASE': base})
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn('+msgid "Fixture farewell"', result.stdout)

    def test_template_check_fails_a_string_removed_without_regenerating(self):
        base = self.start_history("<?php __('Fixture greeting'); __('Fixture farewell');\n")
        self.set_source("<?php __('Fixture greeting');\n")
        self.commit_all('remove a string')
        result = self.run_check(base)
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn('-msgid "Fixture farewell"', result.stdout)
        self.assertNotIn('Fixture greeting"', result.stdout)

    def test_template_check_fails_a_template_edit_without_a_source_change(self):
        base = self.start_history("<?php __('Fixture greeting');\n")
        template = self.locales / 'po/cacti.pot'
        template.write_text(template.read_text() + '\nmsgid "Fixture invented"\nmsgstr ""\n')
        self.commit_all('edit the template by hand')
        result = self.run_check(base)
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn('-msgid "Fixture invented"', result.stdout)

    def test_template_check_passes_a_regenerated_template_and_leaves_the_checkout_alone(self):
        base = self.start_history("<?php __('Fixture greeting');\n")
        self.set_source("<?php __('Fixture farewell');\n")
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        self.commit_all('change a string and regenerate')
        self.set_source("<?php __('Fixture farewell'); __x('Fixture context', 'Fixture farewell');\n")
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        self.commit_all('add a contextual string and regenerate')
        before = self.git('status', '--porcelain', '--ignored')
        scratch = Path(self.temp.name) / 'scratch'
        scratch.mkdir()
        result = self.run_check(base, env={**self.env, 'TMPDIR': str(scratch)})
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn('Comparing with the base', result.stdout)
        self.assertNotIn('already out of sync', result.stdout)
        self.assertEqual(self.git('status', '--porcelain', '--ignored'), before)
        self.assertEqual(self.git('worktree', 'list').count('\n'), 0)
        self.assertEqual(list(scratch.iterdir()), [])

    def test_template_check_ignores_date_locations_and_format_flags(self):
        (self.root / 'input with spaces.php').write_text("<?php __('Fixture %s total', 1);\n")
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        template = self.locales / 'po/cacti.pot'
        pot = template.read_text()
        self.assertIn('#, php-format\n', pot)
        lines = [line for line in pot.splitlines() if not line.startswith(('#:', '#,', '"POT-Creation-Date'))]
        lines.insert(lines.index('"MIME-Version: 1.0\\n"'), '"POT-Creation-Date: 2001-01-01 00:00+0000\\n"')
        template.write_text('\n'.join(lines) + '\n')
        result = self.run_check()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_template_check_compares_catalog_failures_with_the_baseline(self):
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        catalog = self.locales / 'po/xx-XX.po'
        catalog.write_text(BROKEN_CATALOG)
        result = self.run_check()
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn('FAIL: locales/po/xx-XX.po does not pass msgfmt --check-format: msgid Fixture %s total', result.stdout)
        self.assertIn('OK: locales/po/cacti.pot matches the source', result.stdout)

        listed = self.run_check('--list-catalog-failures')
        self.assertEqual(listed.returncode, 0, listed.stderr)
        self.assertEqual(listed.stdout, 'locales/po/xx-XX.po\tmsgid Fixture %s total\n')

        baseline = self.tools / 'message_catalog_baseline.txt'
        baseline.write_text('# comment\n\n' + listed.stdout)
        result = self.run_check()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn('KNOWN: 1 msgfmt', result.stdout)

        # Lines moving in the catalog do not change the entry.
        catalog.write_text('# translator comment\n# another\n' + BROKEN_CATALOG)
        result = self.run_check()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

        # A different failure fails, even though the number of failures is the same.
        catalog.write_text(BROKEN_CATALOG.replace('Fixture %s total', 'Another %s total'))
        result = self.run_check()
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn('msgfmt --check-format: msgid Another %s total', result.stdout)
        self.assertIn('NOTE: locales/po/xx-XX.po no longer fails for: msgid Fixture %s total', result.stdout)

        # An additional failure fails next to a known one.
        catalog.write_text(BROKEN_CATALOG + '\n#, php-format\nmsgid "Second %s"\nmsgstr "Second %a"\n')
        result = self.run_check()
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn('msgfmt --check-format: msgid Second %s', result.stdout)
        self.assertNotIn('FAIL: locales/po/xx-XX.po does not pass msgfmt --check-format: msgid Fixture', result.stdout)

        # A fixed entry passes and says to remove it from the baseline.
        catalog.write_text('msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n')
        result = self.run_check()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn('NOTE: locales/po/xx-XX.po no longer fails for: msgid Fixture %s total', result.stdout)
        self.assertIn('Remove those lines from tests/tools/message_catalog_baseline.txt', result.stdout)

        # A catalog msgfmt cannot read fails, named by its error.
        catalog.write_text('msgid "unterminated\n')
        result = self.run_check()
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn('FAIL: locales/po/xx-XX.po', result.stdout)

    def test_template_check_reports_missing_tools_and_failed_extraction(self):
        self.assertEqual(self.run_script('update-pot.sh').returncode, 0)
        (self.root / 'include/cacti_version').unlink()
        (self.root / 'input with spaces.php').unlink()
        result = self.run_check()
        self.assertEqual(result.returncode, 2, result.stdout + result.stderr)
        self.assertIn('ERROR: locales/update-pot.sh failed', result.stderr)

        tools = self.root / 'missing-tools'
        tools.mkdir()
        (tools / 'dirname').symlink_to(shutil.which('dirname'))
        result = subprocess.run(
            shell_command(self.tools / CHECK, ROOT / 'tests/tools' / CHECK), cwd=self.root,
            env={**self.env, 'PATH': str(tools)}, capture_output=True, text=True, timeout=30,
        )
        self.assertEqual(result.returncode, 2)
        self.assertIn('unable to locate xgettext', result.stderr)


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
