"""Unit and integration tests for tests/tools/run_msgcheck.py.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""

from __future__ import annotations

import contextlib
import importlib.util
import io
import os
from pathlib import Path
import shutil
import stat
import sys
import tempfile
import unittest

TOOLS = Path(__file__).resolve().parent
SPEC = importlib.util.spec_from_file_location('run_msgcheck', TOOLS / 'run_msgcheck.py')
assert SPEC and SPEC.loader
RUN_MSGCHECK = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(RUN_MSGCHECK)

HI_HEADER = (
    'msgid ""\n'
    'msgstr ""\n'
    '"Language: hi_IN\\n"\n'
    '"MIME-Version: 1.0\\n"\n'
    '"Content-Type: text/plain; charset=UTF-8\\n"\n'
    '"Content-Transfer-Encoding: 8bit\\n"\n'
    '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"\n'
)

DE_HEADER = (
    'msgid ""\n'
    'msgstr ""\n'
    '"Language: de_DE\\n"\n'
    '"MIME-Version: 1.0\\n"\n'
    '"Content-Type: text/plain; charset=UTF-8\\n"\n'
    '"Content-Transfer-Encoding: 8bit\\n"\n'
)


class DevanagariNormalizationTest(unittest.TestCase):
    def test_detects_devanagari_languages_from_po_header(self) -> None:
        self.assertTrue(RUN_MSGCHECK.is_devanagari_po(HI_HEADER))
        self.assertTrue(RUN_MSGCHECK.is_devanagari_po('"Language: hi\\n"'))
        self.assertTrue(RUN_MSGCHECK.is_devanagari_po('"Language: mr-IN\\n"'))
        self.assertFalse(RUN_MSGCHECK.is_devanagari_po(DE_HEADER))
        self.assertFalse(RUN_MSGCHECK.is_devanagari_po('msgid "a"\nmsgstr "b"\n'))

    def test_normalizes_single_multiline_and_plural_terminal_danda_only(self) -> None:
        source = (
            HI_HEADER
            + '\n'
            + '# Comment ending in ।\n'
            + 'msgid "First sentence. Second sentence."\n'
            + 'msgstr "पहला वाक्य। दूसरा वाक्य।"\n'
            + '\n'
            + 'msgid "Wrapped sentence."\n'
            + 'msgstr ""\n'
            + '"पहली पंक्ति। "\n'
            + '"दूसरी पंक्ति।"\n'
            + '""\n'
            + '\n'
            + 'msgid "One graph."\n'
            + 'msgid_plural "%d graphs."\n'
            + 'msgstr[0] "एक ग्राफ़।"\n'
            + 'msgstr[1] "%d ग्राफ़।"\n'
            + '\n'
            + '#~ msgid "Obsolete."\n'
            + '#~ msgstr "अप्रचलित।"\n'
        )
        normalized = RUN_MSGCHECK.normalize_devanagari_danda(source)

        self.assertEqual(len(normalized.splitlines()), len(source.splitlines()))
        self.assertIn('# Comment ending in ।\n', normalized)
        self.assertIn('msgstr "पहला वाक्य। दूसरा वाक्य."\n', normalized)
        self.assertIn('"पहली पंक्ति। "\n"दूसरी पंक्ति."\n""\n', normalized)
        self.assertIn('msgstr[0] "एक ग्राफ़."\n', normalized)
        self.assertIn('msgstr[1] "%d ग्राफ़."\n', normalized)
        self.assertIn('#~ msgstr "अप्रचलित।"\n', normalized)

    def test_preserves_trailing_space_after_danda_so_msgcheck_still_flags_it(self) -> None:
        source = HI_HEADER + '\nmsgid "Sentence."\nmsgstr "वाक्य। "\n'
        self.assertEqual(RUN_MSGCHECK.normalize_devanagari_danda(source), source)


class RunnerBehaviorTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory(prefix='cacti-run-msgcheck-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)

    def _write_fake_msgcheck(self, body: str) -> Path:
        script = self.root / 'fake-msgcheck'
        script.write_text(f'#!{sys.executable}\n{body}\n', encoding='utf-8')
        script.chmod(script.stat().st_mode | stat.S_IXUSR)
        return script

    def test_stages_hindi_copy_rewrites_paths_and_leaves_german_untouched(self) -> None:
        hi_po = self.root / 'hi-IN.po'
        de_po = self.root / 'de-DE.po'
        hi_po.write_text(HI_HEADER + '\nmsgid "Hello."\nmsgstr "नमस्ते।"\n', encoding='utf-8')
        de_po.write_text(DE_HEADER + '\nmsgid "Hello."\nmsgstr "Hallo."\n', encoding='utf-8')

        fake = self._write_fake_msgcheck(
            'import pathlib, sys\n'
            'args = sys.argv[1:]\n'
            'hi_path = pathlib.Path(args[-2])\n'
            'de_path = pathlib.Path(args[-1])\n'
            'assert \'msgstr "नमस्ते."\' in hi_path.read_text(encoding="utf-8")\n'
            'print(f"{hi_path.resolve()}:9: sample diagnostic")\n'
            'print(f"DE={de_path}")\n'
            'sys.exit(1)\n'
        )

        rc, stdout, stderr = RUN_MSGCHECK.run_msgcheck(
            ['--error-on-fuzzy', '-o', 'full', '--', str(hi_po), str(de_po)],
            msgcheck_cmd=str(fake),
        )
        self.assertEqual(rc, 1)
        self.assertEqual(stderr, '')
        self.assertIn(f'{hi_po.resolve()}:9: sample diagnostic', stdout)
        self.assertIn(f'DE={de_po}', stdout)
        # Original file on disk must not be modified.
        self.assertIn('msgstr "नमस्ते।"', hi_po.read_text(encoding='utf-8'))

    def test_missing_msgcheck_returns_127(self) -> None:
        rc, stdout, stderr = RUN_MSGCHECK.run_msgcheck(
            ['--no-lines', 'locales/po/hi-IN.po'],
            msgcheck_cmd='nonexistent-msgcheck-binary-for-test',
        )
        self.assertEqual(rc, 127)
        self.assertEqual(stdout, '')
        self.assertIn('not found in PATH', stderr)

    def test_main_forwards_output_and_exit_code(self) -> None:
        fake = self._write_fake_msgcheck(
            'import sys\n'
            'print("OK")\n'
            'print("warn", file=sys.stderr)\n'
            'sys.exit(0)\n'
        )
        orig_path = os.environ.get('PATH', '')
        bin_dir = self.root / 'bin'
        bin_dir.mkdir()
        link = bin_dir / 'msgcheck'
        link.symlink_to(fake)
        os.environ['PATH'] = f'{bin_dir}{os.pathsep}{orig_path}'
        self.addCleanup(os.environ.__setitem__, 'PATH', orig_path)

        out = io.StringIO()
        err = io.StringIO()
        with contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
            rc = RUN_MSGCHECK.main(['--no-lines', str(self.root / 'missing.po')])
        self.assertEqual(rc, 0)
        self.assertEqual(out.getvalue(), 'OK\n')
        self.assertEqual(err.getvalue(), 'warn\n')


class BaseFilteringTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory(prefix='cacti-run-msgcheck-base-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)

    def test_filters_unchanged_fuzzy_and_translated_entries_preserving_line_count(self) -> None:
        base = (
            DE_HEADER
            + '\n'
            + '#, fuzzy, php-format\n'
            + 'msgid "Old fuzzy %s."\n'
            + 'msgstr ""\n'
            + '"Alt unscharf %s"\n'
            + '\n'
            + 'msgid "Unchanged."\n'
            + 'msgstr "Unveraendert"\n'
            + '\n'
            + 'msgid "Updated."\n'
            + 'msgstr "Alt"\n'
            + '\n'
            + '#~ msgid "Obsolete"\n'
            + '#~ msgstr "Veraltet"\n'
        )
        head = base.replace('msgstr "Alt"\n', 'msgstr "Neu."\n')

        filtered = RUN_MSGCHECK.filter_po_to_changed_entries(head, base)

        self.assertEqual(len(filtered.splitlines()), len(head.splitlines()))
        self.assertIn('#, php-format\nmsgid "Old fuzzy %s."\nmsgstr ""\n""\n', filtered)
        self.assertIn('msgid "Unchanged."\nmsgstr ""\n', filtered)
        self.assertIn('msgid "Updated."\nmsgstr "Neu."\n', filtered)
        self.assertIn('#~ msgstr "Veraltet"\n', filtered)

    def test_base_option_reads_git_commit_and_strips_base_flag_from_msgcheck_args(self) -> None:
        import subprocess

        repo = self.root / 'repo'
        repo.mkdir()
        subprocess.run(['git', 'init', '-q'], cwd=repo, check=True)
        subprocess.run(['git', 'config', 'user.email', 'test@example.com'], cwd=repo, check=True)
        subprocess.run(['git', 'config', 'user.name', 'Test'], cwd=repo, check=True)
        po_file = repo / 'hi-IN.po'
        po_file.write_text(
            HI_HEADER
            + '\n#, fuzzy\nmsgid "Old."\nmsgstr "पुराना"\n'
            + '\nmsgid "Changed."\nmsgstr "पहले"\n',
            encoding='utf-8',
        )
        subprocess.run(['git', 'add', 'hi-IN.po'], cwd=repo, check=True)
        subprocess.run(['git', 'commit', '-q', '--no-gpg-sign', '-m', 'base'], cwd=repo, check=True)
        base_sha = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=repo, text=True).strip()

        po_file.write_text(
            HI_HEADER
            + '\n#, fuzzy\nmsgid "Old."\nmsgstr "पुराना"\n'
            + '\nmsgid "Changed."\nmsgstr "बाद में।"\n',
            encoding='utf-8',
        )

        fake = self.root / 'fake-msgcheck'
        fake.write_text(
            f'#!{sys.executable}\n'
            'import pathlib, sys\n'
            'args = sys.argv[1:]\n'
            'assert "--base" not in args\n'
            'staged = pathlib.Path(args[-1]).read_text(encoding="utf-8")\n'
            'assert "fuzzy" not in staged\n'
            'assert \'msgstr "बाद में."\' in staged\n'
            'print("CLEAN")\n',
            encoding='utf-8',
        )
        fake.chmod(fake.stat().st_mode | stat.S_IXUSR)

        orig_cwd = os.getcwd()
        os.chdir(repo)
        self.addCleanup(os.chdir, orig_cwd)

        rc, stdout, stderr = RUN_MSGCHECK.run_msgcheck(
            [f'--base={base_sha}', '--error-on-fuzzy', '--no-lines', 'hi-IN.po'],
            msgcheck_cmd=str(fake),
        )
        self.assertEqual(rc, 0, stderr)
        self.assertEqual(stdout, 'CLEAN\n')

        # Missing base ref falls back cleanly to checking the full file
        rc2, stdout2, _ = RUN_MSGCHECK.run_msgcheck(
            ['--base', '0000000000000000000000000000000000000000', '--no-lines', 'hi-IN.po'],
            msgcheck_cmd=str(fake),
        )
        self.assertNotEqual(rc2, 0)


@unittest.skipUnless(shutil.which('msgcheck'), 'msgcheck required')
class RealMsgcheckIntegrationTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory(prefix='cacti-msgcheck-real-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)

    def test_accepts_hindi_danda_and_rejects_missing_or_extra_punctuation(self) -> None:
        good_po = self.root / 'hi-IN.po'
        good_po.write_text(
            HI_HEADER + '\nmsgid "The name of this rule."\nmsgstr "इस नियम का नाम।"\n',
            encoding='utf-8',
        )
        rc, stdout, stderr = RUN_MSGCHECK.run_msgcheck(
            ['--error-on-fuzzy', '--no-lines', str(good_po)],
        )
        self.assertEqual(rc, 0, stdout + stderr)

        bad_po = self.root / 'hi-IN-bad.po'
        bad_po.write_text(
            HI_HEADER
            + '\nmsgid "The name of this rule."\nmsgstr "इस नियम का नाम"\n'
            + '\nmsgid "Rule Name"\nmsgstr "नियम का नाम।"\n',
            encoding='utf-8',
        )
        rc, stdout, stderr = RUN_MSGCHECK.run_msgcheck(
            ['--error-on-fuzzy', '--no-lines', str(bad_po)],
        )
        self.assertNotEqual(rc, 0, stdout + stderr)
        self.assertIn(str(bad_po.resolve()), stdout)
        self.assertIn('2 errors', stdout)


if __name__ == '__main__':
    unittest.main()
