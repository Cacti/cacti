#!/usr/bin/env python3
# SPDX-License-Identifier: GPL-2.0-or-later
"""Run the PO validator against filenames that can resemble command options."""
from __future__ import annotations

import importlib.util
import os
from pathlib import Path
import shutil
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location('cacti_po_validator', ROOT / 'tests/tools/validate_cacti_po.py')
TOOL = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = TOOL
SPEC.loader.exec_module(TOOL)

HEADER = 'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n'


@unittest.skipUnless(shutil.which('msgfmt'), 'GNU gettext msgfmt is required')
class PoValidatorTests(unittest.TestCase):
    def test_filenames_are_inputs_to_msgfmt(self):
        previous = Path.cwd()
        with tempfile.TemporaryDirectory() as directory:
            try:
                os.chdir(directory)
                for name in ['--output-file=unexpected.po', '-catalog.po', 'catalog with spaces.po', 'ordinary.po']:
                    with self.subTest(filename=name):
                        path = Path(name)
                        path.write_text(HEADER, encoding='utf-8')
                        self.assertEqual(TOOL.validate_file(path), [])
                        self.assertEqual(TOOL.main(['validator', name]), 0)
                self.assertEqual(TOOL.validate_file(Path('ordinary.po').resolve()), [])
                self.assertFalse(Path('unexpected.po').exists())
            finally:
                os.chdir(previous)

    def test_invalid_catalog_still_fails_msgfmt(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'invalid.po'
            path.write_text(HEADER + '\nmsgid "broken"\nmsgstr "unterminated\n', encoding='utf-8')
            self.assertTrue(any('msgfmt failed:' in error for error in TOOL.validate_file(path)))

    def test_placeholder_mismatch_still_fails(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'mismatch.po'
            path.write_text(HEADER + '\nmsgid "Value: %s"\nmsgstr "Value: %d"\n', encoding='utf-8')
            self.assertTrue(any('format mismatch' in error for error in TOOL.validate_file(path)))

    def test_entries_keep_their_strings_flags_and_plural_forms(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'plural.po'
            path.write_text(HEADER + '\n#, python-format\nmsgid "One %s"\nmsgid_plural "Many %s"\nmsgstr[0] "Un %s"\nmsgstr[1] "Plusieurs %s"\n', encoding='utf-8')
            entries = TOOL.parse_po(path)
            self.assertEqual(len(entries), 2)
            self.assertEqual(entries[0].msgid, '')
            self.assertEqual(entries[1].msgid, 'One %s')
            self.assertEqual(entries[1].msgid_plural, 'Many %s')
            self.assertEqual(entries[1].flags, {'python-format'})
            self.assertEqual(entries[1].msgstr, {0: 'Un %s', 1: 'Plusieurs %s'})


if __name__ == '__main__':
    unittest.main()
