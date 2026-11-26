"""Unit tests for tests/tools/audit_pot.py.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""

from __future__ import annotations

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
    import polib

    SPEC = importlib.util.spec_from_file_location('audit_pot', TOOLS / 'audit_pot.py')
    assert SPEC and SPEC.loader
    AUDIT = importlib.util.module_from_spec(SPEC)
    SPEC.loader.exec_module(AUDIT)

HEADER = 'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n'
BAD_PLURAL = (
    '\nmsgid "Leave the Graph Untouched"\n'
    'msgid_plural "Leave all Graphs untouched."\n'
    'msgstr[0] ""\n'
    'msgstr[1] ""\n'
)
GOOD_ENTRY = '\nmsgid "Device %s"\nmsgstr ""\n'


def template(*entries: str) -> str:
    return HEADER + ''.join(entries)


@unittest.skipUnless(HAVE_POLIB, 'polib required')
class AuditPotRulesTest(unittest.TestCase):
    def audit_entry(
        self,
        msgid: str,
        msgid_plural: str = '',
        flags: list[str] | None = None,
    ) -> tuple[int, str]:
        entry = polib.POEntry(
            msgid=msgid,
            msgid_plural=msgid_plural,
            flags=flags or [],
        )
        output = io.StringIO()
        with contextlib.redirect_stdout(output):
            errors = AUDIT._audit_entry(entry, 'cacti.pot:1')
        return errors, output.getvalue()

    def test_mismatched_plural_trailing_period_is_rejected(self) -> None:
        errors, out = self.audit_entry(
            'Delete all Graphs that reference this Data Source.',
            'Delete all Graphs that reference these Data Sources',
        )
        self.assertEqual(errors, 1)
        self.assertIn('Mismatched trailing "."', out)

    def test_matching_plural_trailing_period_passes(self) -> None:
        errors, out = self.audit_entry(
            'Delete all Graphs that reference this Data Source.',
            'Delete all Graphs that reference these Data Sources.',
        )
        self.assertEqual(errors, 0, out)

    def test_balanced_and_void_html_tags_pass(self) -> None:
        errors, out = self.audit_entry(
            'See <a href="%s"><strong>docs</strong></a><br>for <device> details.',
            flags=['php-format'],
        )
        self.assertEqual(errors, 0, out)

    def test_unbalanced_and_malformed_html_tags_are_rejected(self) -> None:
        cases = [
            ('Default Ping type for all new Devices.</i>', 'unexpected closing </i>'),
            ('Mode<blockquote><i>No Search</i>', 'unclosed HTML tag(s): <blockquote>'),
            ('See <a href="https://example.invalid"', 'malformed/unterminated HTML tag <a>'),
        ]
        for text, expected in cases:
            with self.subTest(text=text):
                errors, out = self.audit_entry(text)
                self.assertGreaterEqual(errors, 1)
                self.assertIn(expected, out)

    def test_literal_percent_flagged_php_format_is_rejected(self) -> None:
        for text in [
            'Reduce Memory to less than 80% of System Memory.',
            'LIKE uses % for any number of characters.',
            'We recommend 25%% of system memory.',
        ]:
            with self.subTest(text=text):
                errors, out = self.audit_entry(text, flags=['php-format'])
                self.assertEqual(errors, 1)
                self.assertIn('flagged php-format contains no printf specifiers', out)

    def test_no_php_format_or_real_specifier_passes(self) -> None:
        self.assertEqual(
            self.audit_entry('LIKE uses % for any number of characters.', flags=['no-php-format'])[0],
            0,
        )
        self.assertEqual(
            self.audit_entry('Using %d%% of %s memory.', flags=['php-format'])[0],
            0,
        )

    def test_duplicate_mixed_and_malformed_source_specifiers_are_rejected(self) -> None:
        cases = [
            ('%1$s and %s', 'mixed positional and non-positional'),
            ('%0$s and %1$d', 'zero-indexed positional'),
            ('%1$s and %1$d', 'duplicate positional'),
            ('%2$s only', 'gapped positional'),
            ('%1$ and %2$s', 'malformed positional'),
            ('%$s', 'malformed positional'),
        ]
        for text, expected in cases:
            with self.subTest(text=text):
                errors, out = self.audit_entry(text, flags=['php-format'])
                self.assertGreaterEqual(errors, 1)
                self.assertIn(expected, out)

    def test_valid_positional_source_specifiers_pass(self) -> None:
        errors, out = self.audit_entry('%1$s has %2$d devices.', flags=['php-format'])
        self.assertEqual(errors, 0, out)


@unittest.skipUnless(HAVE_POLIB and shutil.which('git'), 'polib and git required')
class AuditPotBaseFilterTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory(prefix='cacti audit pot ')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.git('init', '-q')
        self.pot = self.root / 'cacti.pot'
        self.pot.write_text(template(BAD_PLURAL, GOOD_ENTRY), encoding='utf-8')
        self.commit('base')
        cwd = os.getcwd()
        os.chdir(self.root)
        self.addCleanup(os.chdir, cwd)

    def git(self, *args: str) -> None:
        subprocess.run(['git', *args], cwd=self.root, check=True, capture_output=True)

    def commit(self, message: str) -> None:
        self.git('add', 'cacti.pot')
        self.git(
            '-c', 'user.name=t', '-c', 'user.email=t@example.invalid',
            '-c', 'commit.gpgsign=false', 'commit', '-q', '-m', message,
        )

    def audit(self, *args: str) -> tuple[int, str]:
        output = io.StringIO()
        with contextlib.redirect_stdout(output):
            status = AUDIT.main(list(args))
        return status, output.getvalue()

    def test_whole_file_audit_reports_existing_errors(self) -> None:
        status, output = self.audit('cacti.pot')
        self.assertEqual(status, 1)
        self.assertIn('Mismatched trailing "."', output)

    def test_unchanged_entries_are_skipped_against_base(self) -> None:
        self.pot.write_text(
            template(BAD_PLURAL, GOOD_ENTRY, '\nmsgid "Added clean string."\nmsgstr ""\n'),
            encoding='utf-8',
        )
        status, output = self.audit('--base', 'HEAD', 'cacti.pot')
        self.assertEqual(status, 0, output)
        self.assertIn('OK: 1 file(s) passed.', output)

    def test_new_or_reflagged_bad_entry_is_audited_against_base(self) -> None:
        self.pot.write_text(
            template(
                BAD_PLURAL,
                GOOD_ENTRY,
                '\n#, php-format\nmsgid "Use 80% of memory."\nmsgstr ""\n',
            ),
            encoding='utf-8',
        )
        status, output = self.audit('--base', 'HEAD', 'cacti.pot')
        self.assertEqual(status, 1)
        self.assertIn('flagged php-format contains no printf specifiers', output)

    def test_unreadable_base_audits_every_entry(self) -> None:
        self.assertIsNone(AUDIT.base_entries('cacti.pot', 'no-such-ref'))
        status, output = self.audit('--base', 'no-such-ref', 'cacti.pot')
        self.assertEqual(status, 1)
        self.assertIn('Mismatched trailing "."', output)


if __name__ == '__main__':
    unittest.main()
