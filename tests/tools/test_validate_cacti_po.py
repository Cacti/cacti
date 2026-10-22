"""Exercise the PO validator against catalogs on disk with real msgfmt."""
import importlib.util
import os
from pathlib import Path
import shutil
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location('validate_cacti_po', ROOT / 'scripts' / 'validate_cacti_po.py')
validator = importlib.util.module_from_spec(SPEC)
# dataclasses resolves the script's string annotations through sys.modules.
sys.modules[SPEC.name] = validator
SPEC.loader.exec_module(validator)

HEADER = 'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n\n'


@unittest.skipUnless(shutil.which('msgfmt'), 'GNU gettext msgfmt required')
class ValidateCactiPoTest(unittest.TestCase):
    def setUp(self):
        temp = tempfile.TemporaryDirectory(prefix='cacti po ')
        self.addCleanup(temp.cleanup)
        self.addCleanup(os.chdir, os.getcwd())
        os.chdir(temp.name)

    def test_file_name_starting_with_dash_is_checked_as_a_file(self):
        Path('-lang.po').write_text(HEADER + 'msgid "Hello %s"\nmsgstr "Hallo %s"\n')
        self.assertEqual(validator.validate_file(Path('-lang.po')), [])

    def test_msgfmt_reads_the_dash_named_catalog(self):
        Path('-lang.po').write_text(HEADER + '#, php-format\nmsgid "Hello %s"\nmsgstr "Hallo %d"\n')
        errors = validator.validate_file(Path('-lang.po'))
        self.assertTrue(any('msgfmt failed' in error and '-lang.po:' in error for error in errors), errors)


if __name__ == '__main__':
    unittest.main()
