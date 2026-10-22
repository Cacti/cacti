"""Check the .po audit placeholder rules and the changed-entry base filter.

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

TOOLS = Path(__file__).resolve().parent
HAVE_POLIB = importlib.util.find_spec('polib') is not None

if HAVE_POLIB:
    SPEC = importlib.util.spec_from_file_location('audit_po', TOOLS / 'audit_po.py')
    AUDIT = importlib.util.module_from_spec(SPEC)
    SPEC.loader.exec_module(AUDIT)

HEADER = 'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n'
# Two placeholders without positions in the translation: one audit error.
BAD_ENTRY = '\nmsgid "%s of %s"\nmsgstr "%s von %s"\n'
GOOD_ENTRY = '\nmsgid "Device %s"\nmsgstr "Gerät %s"\n'


def catalog(*entries):
    return HEADER + ''.join(entries)


@unittest.skipUnless(HAVE_POLIB, 'polib required')
class PlaceholderTest(unittest.TestCase):
    def test_prose_percent_signs_are_not_placeholders(self):
        for text in ['100% of the pool', '50 % des hôtes', 'Use 25%-80% of memory', 'at %H:%M']:
            with self.subTest(text=text):
                self.assertEqual(AUDIT._PLACEHOLDER.findall(text), [])

    def test_printf_conversions_and_positions_are_placeholders(self):
        self.assertEqual(len(AUDIT._PLACEHOLDER.findall('%s %d %0.2f %-10s %x')), 5)
        self.assertEqual(AUDIT._POSITIONAL.findall('%2$s then %1$d'), ['%2$s', '%1$d'])

    def test_translation_adding_prose_percent_is_not_an_error(self):
        with contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(AUDIT._audit_pair('Half of the hosts', '50 % des hôtes', 'x'), 0)
            self.assertEqual(AUDIT._audit_pair('%s of %s', '%1$s de %2$s', 'x'), 0)
            self.assertEqual(AUDIT._audit_pair('%s of %s', '%s de %s', 'x'), 1)


@unittest.skipUnless(HAVE_POLIB and shutil.which('git'), 'polib and git required')
class BaseFilterTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='cacti audit po ')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.git('init', '-q')
        self.po = self.root / 'xx.po'
        self.po.write_text(catalog(BAD_ENTRY, GOOD_ENTRY), encoding='utf-8')
        self.commit('base')
        cwd = os.getcwd()
        os.chdir(self.root)
        self.addCleanup(os.chdir, cwd)

    def git(self, *args):
        subprocess.run(['git', *args], cwd=self.root, check=True, capture_output=True)

    def commit(self, message):
        self.git('add', 'xx.po')
        self.git('-c', 'user.name=t', '-c', 'user.email=t@example.invalid',
                 '-c', 'commit.gpgsign=false', 'commit', '-q', '-m', message)

    def audit(self, *args):
        output = io.StringIO()
        with contextlib.redirect_stdout(output):
            status = AUDIT.main(list(args))
        return status, output.getvalue()

    def test_whole_file_audit_reports_existing_errors(self):
        status, output = self.audit('xx.po')
        self.assertEqual(status, 1)
        self.assertIn('Non-positional placeholders', output)

    def test_unchanged_entries_are_skipped_against_the_base(self):
        self.po.write_text(catalog(BAD_ENTRY, '\nmsgid "Device %s"\nmsgstr "Neues Gerät %s"\n'),
                           encoding='utf-8')
        status, output = self.audit('--base', 'HEAD', 'xx.po')
        self.assertEqual(status, 0, output)
        self.assertIn('OK: 1 file(s) passed.', output)

    def test_changed_entries_are_audited_against_the_base(self):
        self.po.write_text(catalog('\nmsgid "%s of %s"\nmsgstr "%s aus %s"\n', GOOD_ENTRY),
                           encoding='utf-8')
        status, output = self.audit('--base', 'HEAD', 'xx.po')
        self.assertEqual(status, 1)
        self.assertIn('%s aus %s', output)

    def test_unreadable_base_audits_every_entry(self):
        self.assertIsNone(AUDIT.base_entries('xx.po', 'no-such-ref'))
        status, output = self.audit('--base', 'no-such-ref', 'xx.po')
        self.assertEqual(status, 1)
        self.assertIn('Non-positional placeholders', output)

    def test_base_parsing_survives_unicode_line_separators(self):
        self.po.write_text(catalog(BAD_ENTRY, '\nmsgid "Line"\nmsgstr "Zeile Zwei"\n'),
                           encoding='utf-8')
        self.commit('separator')
        entries = AUDIT.base_entries('xx.po', 'HEAD')
        self.assertIsNotNone(entries)
        self.assertEqual(entries[(None, 'Line', '')], ('Zeile Zwei', ()))


if __name__ == '__main__':
    unittest.main()
