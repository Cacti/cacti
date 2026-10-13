"""Regression tests for false green results in the changed-code coverage gate.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
from contextlib import redirect_stdout
import importlib.util
import io
import json
from pathlib import Path
import tempfile
import unittest
import sys
from unittest.mock import patch

sys.dont_write_bytecode = True
SPEC = importlib.util.spec_from_file_location('coverage_gate', Path(__file__).with_name('report_changed_coverage.py'))
GATE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(GATE)


class CoverageEvidenceTest(unittest.TestCase):
    def test_deletion_only_files_do_not_require_added_line_coverage(self):
        diff = '--- a/lib/old.php\n+++ b/lib/old.php\n@@ -3 +2,0 @@\n-return true;\n'
        with patch.object(GATE.subprocess, 'check_output', side_effect=['base', diff]):
            self.assertEqual(GATE.added_lines('HEAD^'), {})

    def evaluate(self, evidence, added=(3,)):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            (root / 'lib').mkdir()
            source = root / 'lib/example.php'
            source.write_text('<?php\nfunction example() {\nreturn true;\n}\n')
            coverage = root / 'coverage.json'
            coverage.write_text(json.dumps({str(source): {'lines': evidence}} if evidence is not None else {}))
            with patch.object(GATE.Path, 'cwd', return_value=root), patch.object(GATE, 'added_lines', return_value={'lib/example.php': set(added)}), patch('sys.argv', ['gate', '--base', 'HEAD', '--php-coverage', str(coverage)]), redirect_stdout(io.StringIO()):
                return GATE.main()

    def test_covered_statement_passes(self):
        self.assertEqual(self.evaluate({3: 1}), 0)

    def test_unexecuted_statement_fails(self):
        self.assertEqual(self.evaluate({3: -1}), 1)

    def test_missing_file_evidence_fails(self):
        self.assertEqual(self.evaluate(None), 1)

    def test_partial_file_instrumentation_fails(self):
        self.assertEqual(self.evaluate({2: 1}), 1)

    def test_dead_opcode_is_excluded(self):
        self.assertEqual(self.evaluate({3: -2}), 0)


if __name__ == '__main__':
    unittest.main()
