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
# Mixed positional and bare placeholders in the translation: one audit error.
BAD_ENTRY = '\nmsgid "%s of %s"\nmsgstr "%1$s von %s"\n'
GOOD_ENTRY = '\nmsgid "Device %s"\nmsgstr "Gerät %s"\n'


def catalog(*entries):
    return HEADER + ''.join(entries)


@unittest.skipUnless(HAVE_POLIB, 'polib required')
class PlaceholderTest(unittest.TestCase):
    def audit_pair(self, source, translated):
        output = io.StringIO()
        with contextlib.redirect_stdout(output):
            errors = AUDIT._audit_pair(source, translated, 'x')
        return errors, output.getvalue()

    def test_prose_percent_signs_are_not_placeholders(self):
        for text in ['100% of the pool', '50 % des hôtes', 'Use 25%-80% of memory', 'at %H:%M']:
            with self.subTest(text=text):
                self.assertEqual(AUDIT._PLACEHOLDER.findall(text), [])

    def test_printf_conversions_and_positions_are_placeholders(self):
        self.assertEqual(len(AUDIT._PLACEHOLDER.findall('%s %d %0.2f %-10s %x')), 5)
        self.assertEqual(AUDIT._POSITIONAL.findall('%2$s then %1$d'), ['%2$s', '%1$d'])

    def test_translation_adding_prose_percent_is_not_an_error(self):
        errors, out = self.audit_pair('Half of the hosts', '50 % des hôtes')
        self.assertEqual(errors, 0, out)

    def test_bare_in_order_and_all_positional_placeholders_are_accepted(self):
        cases = [
            ('%s of %s', '%s de %s'),
            ('%s of %d', '%s de %d'),
            ('%s of %s', '%1$s de %2$s'),
            ('%s of %d', '%1$s de %2$d'),
            ('%s has %d items', '%2$d Elemente in %1$s'),
            ('Device %s', 'Gerät %1$s'),
            ('%1$s has %2$d items', '%2$d Elemente in %1$s'),
            ('%1$s has %2$d items', '%s hat %d Elemente'),
        ]
        for source, translated in cases:
            with self.subTest(source=source, translated=translated):
                errors, out = self.audit_pair(source, translated)
                self.assertEqual(errors, 0, out)

    def test_mixed_positional_and_bare_placeholders_are_rejected(self):
        for translated in ['%1$s de %s', '%s de %2$d']:
            with self.subTest(translated=translated):
                errors, out = self.audit_pair('%s of %d', translated)
                self.assertEqual(errors, 1)
                self.assertIn('Mixed positional and non-positional', out)

    def test_out_of_range_positional_indices_are_rejected(self):
        for translated in ['%0$s de %1$d', '%1$s de %3$d']:
            with self.subTest(translated=translated):
                errors, out = self.audit_pair('%s of %d', translated)
                self.assertGreaterEqual(errors, 1)
                self.assertIn('Out-of-range positional placeholder', out)

    def test_placeholder_count_and_type_mismatches_are_rejected(self):
        cases = [
            ('%s of %d', '%d de %s', 'Placeholder type/order mismatch'),
            ('%s of %d', '%s de %s', 'Placeholder type/order mismatch'),
            ('%s of %d', '%1$d de %2$s', 'Positional placeholder type mismatch'),
            ('%s of %d', '%1$s', 'Missing positional placeholder'),
            ('%s of %s', '%s', 'Placeholder count mismatch'),
            ('%s of %s', '%s %s %s', 'Placeholder count mismatch'),
            ('Device %s', 'Gerät', 'missing format specifier'),
            ('Device', 'Gerät %s', 'msgstr has format specifier'),
        ]
        for source, translated, expected in cases:
            with self.subTest(source=source, translated=translated):
                errors, out = self.audit_pair(source, translated)
                self.assertGreaterEqual(errors, 1)
                self.assertIn(expected, out)

    def test_literal_percent_pairs_and_odd_percent_runs(self):
        self.assertEqual(self.audit_pair('Literal %%s and %%d', 'Literal %%s och %%d')[0], 0)
        self.assertEqual(self.audit_pair('%s / %d (100%%)', '%1$s / %2$d (100%%)')[0], 0)
        self.assertEqual(self.audit_pair('%s / %d (100%%)', '%s / %d (100%%)')[0], 0)
        self.assertEqual(self.audit_pair('%%%s / %d', '%%%s / %d')[0], 0)
        self.assertEqual(self.audit_pair('%%%s / %d', '%%%1$s / %2$d')[0], 0)
        self.assertEqual(self.audit_pair('%%%%s / %%%%d', '%%%%s / %%%%d')[0], 0)
        self.assertGreater(self.audit_pair('literal', '%%%s')[0], 0)
        self.assertGreater(self.audit_pair('%%%s / %d', '%%%1$s / %d')[0], 0)

    def test_html_tag_count_mismatch_is_rejected(self):
        errors, out = self.audit_pair('<b>Hello</b> %s', 'Hallo %s')
        self.assertEqual(errors, 1)
        self.assertIn('HTML tag count mismatch', out)


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
        self.assertIn('Mixed positional and non-positional', output)

    def test_unchanged_entries_are_skipped_against_the_base(self):
        self.po.write_text(catalog(BAD_ENTRY, '\nmsgid "Device %s"\nmsgstr "Neues Gerät %s"\n'),
                           encoding='utf-8')
        status, output = self.audit('--base', 'HEAD', 'xx.po')
        self.assertEqual(status, 0, output)
        self.assertIn('OK: 1 file(s) passed.', output)

    def test_changed_entries_are_audited_against_the_base(self):
        self.po.write_text(catalog('\nmsgid "%s of %s"\nmsgstr "%1$s aus %s"\n', GOOD_ENTRY),
                           encoding='utf-8')
        status, output = self.audit('--base', 'HEAD', 'xx.po')
        self.assertEqual(status, 1)
        self.assertIn('%1$s aus %s', output)

    def test_unreadable_base_audits_every_entry(self):
        self.assertIsNone(AUDIT.base_entries('xx.po', 'no-such-ref'))
        status, output = self.audit('--base', 'no-such-ref', 'xx.po')
        self.assertEqual(status, 1)
        self.assertIn('Mixed positional and non-positional', output)

    def test_base_parsing_survives_unicode_line_separators(self):
        self.po.write_text(catalog(BAD_ENTRY, '\nmsgid "Line"\nmsgstr "Zeile Zwei"\n'),
                           encoding='utf-8')
        self.commit('separator')
        entries = AUDIT.base_entries('xx.po', 'HEAD')
        self.assertIsNotNone(entries)
        self.assertEqual(entries[(None, 'Line', '')], ('Zeile Zwei', ()))


if __name__ == '__main__':
    unittest.main()
