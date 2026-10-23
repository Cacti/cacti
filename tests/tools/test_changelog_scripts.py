"""Exercise the CHANGELOG check and fragment fold in disposable trees.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import contextlib
import importlib.util
import io
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
from unittest import mock

SCRIPTS = Path(__file__).resolve().parents[2] / '.github/scripts'


def load(name):
    spec = importlib.util.spec_from_file_location(name.replace('-', '_'), SCRIPTS / f'{name}.py')
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


CHECK = load('check-changelog')
FOLD = load('fold-changelog')

CHANGELOG = '''Cacti CHANGELOG

1.3.0-dev
-security#100: Older security fix
-issue#200: Older issue fix
-feature#300: Older feature

1.2.30
-issue#1: Released fix
'''


class ChangelogTree(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='cacti changelog ')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        (self.root / 'changelog.d').mkdir()
        (self.root / 'CHANGELOG').write_text(CHANGELOG, encoding='utf-8')
        cwd = os.getcwd()
        os.chdir(self.root)
        self.addCleanup(os.chdir, cwd)

    def fragment(self, name, text):
        (self.root / 'changelog.d' / name).write_text(text, encoding='utf-8')

    def run_main(self, module, *args, env=None):
        stdout, stderr = io.StringIO(), io.StringIO()
        with mock.patch('sys.argv', ['script', *args]), \
                mock.patch.dict(os.environ, env or {}, clear=False), \
                contextlib.redirect_stdout(stdout), contextlib.redirect_stderr(stderr):
            status = module.main()
        return status, stdout.getvalue() + stderr.getvalue()


class FoldChangelogTest(ChangelogTree):
    def test_security_and_issue_fold_newest_first_and_features_oldest_first(self):
        for name in ['201.issue', '205.issue', '301.feature', '302.feature', '101.security', '102.security']:
            self.fragment(name, f'Fragment {name}\n')
        status, output = self.run_main(FOLD)
        self.assertEqual(status, 0, output)
        section = (self.root / 'CHANGELOG').read_text(encoding='utf-8').split('\n\n')[1].splitlines()
        self.assertEqual(section, [
            '1.3.0-dev',
            '-security#102: Fragment 102.security',
            '-security#101: Fragment 101.security',
            '-security#100: Older security fix',
            '-issue#205: Fragment 205.issue',
            '-issue#201: Fragment 201.issue',
            '-issue#200: Older issue fix',
            '-feature#300: Older feature',
            '-feature#301: Fragment 301.feature',
            '-feature#302: Fragment 302.feature',
        ])
        self.assertEqual(sorted(p.name for p in (self.root / 'changelog.d').iterdir()), [])

    def test_invalid_fragments_abort_before_anything_is_written_or_deleted(self):
        cases = {
            '400.issue': '',
            '401.issue': 'first line\nsecond line\n',
            '402.issue': '-issue#402: prefixed description\n',
        }
        for name, text in cases.items():
            with self.subTest(fragment=name):
                self.fragment('399.issue', 'Valid fix\n')
                self.fragment(name, text)
                status, output = self.run_main(FOLD)
                self.assertEqual(status, 1)
                self.assertIn(name, output)
                self.assertEqual((self.root / 'CHANGELOG').read_text(encoding='utf-8'), CHANGELOG)
                self.assertTrue((self.root / 'changelog.d' / '399.issue').exists())
                self.assertTrue((self.root / 'changelog.d' / name).exists())
                (self.root / 'changelog.d' / name).unlink()

    def test_check_mode_reports_without_writing(self):
        self.fragment('210.issue', 'Checked fix\n')
        status, output = self.run_main(FOLD, '--check')
        self.assertEqual(status, 0)
        self.assertIn('1 fragment(s) would be folded', output)
        self.assertEqual((self.root / 'CHANGELOG').read_text(encoding='utf-8'), CHANGELOG)

    def test_missing_release_heading_is_an_error(self):
        (self.root / 'CHANGELOG').write_text('Cacti CHANGELOG\n\nno sections yet\n', encoding='utf-8')
        self.fragment('210.issue', 'Orphan fix\n')
        status, output = self.run_main(FOLD)
        self.assertEqual(status, 1)
        self.assertIn('no release heading', output)
        self.assertTrue((self.root / 'changelog.d' / '210.issue').exists())


@unittest.skipUnless(shutil.which('git'), 'git required')
class CheckChangelogBaseDiffTest(ChangelogTree):
    def git(self, *args):
        subprocess.run(['git', '-c', 'user.name=t', '-c', 'user.email=t@example.invalid',
                        '-c', 'commit.gpgsign=false', *args],
                       cwd=self.root, check=True, capture_output=True)

    def test_failed_base_diff_is_reported_instead_of_skipped(self):
        status, output = self.run_main(CHECK, env={'GITHUB_BASE_REF': 'missing-base'})
        self.assertEqual(status, 1)
        self.assertIn('cannot diff CHANGELOG against origin/missing-base', output)

    def test_unnumbered_entry_added_on_a_branch_is_rejected(self):
        self.git('init', '-q')
        self.git('add', 'CHANGELOG')
        self.git('commit', '-q', '-m', 'base')
        self.git('update-ref', 'refs/remotes/origin/develop', 'HEAD')
        text = CHANGELOG.replace('-issue#200', '-issue: Unnumbered fix\n-issue#200')
        (self.root / 'CHANGELOG').write_text(text, encoding='utf-8')
        self.git('commit', '-q', '-a', '-m', 'change')
        status, output = self.run_main(CHECK, env={'GITHUB_BASE_REF': 'develop'})
        self.assertEqual(status, 1, output)
        self.assertIn('must include an issue or pull-request number: -issue: Unnumbered fix', output)


if __name__ == '__main__':
    unittest.main()
