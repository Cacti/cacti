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

    def evaluate(self, evidence, added=(3,), minimum=None):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            (root / 'lib').mkdir()
            source = root / 'lib/example.php'
            source.write_text('<?php\nfunction example() {\nreturn true;\n}\n')
            coverage = root / 'coverage.json'
            coverage.write_text(json.dumps({str(source): {'lines': evidence}} if evidence is not None else {}))
            arguments = ['gate', '--base', 'HEAD', '--php-coverage', str(coverage)]
            if minimum is not None:
                arguments += ['--minimum', str(minimum)]
            with patch.object(GATE.Path, 'cwd', return_value=root), patch.object(GATE, 'added_lines', return_value={'lib/example.php': set(added)}), patch('sys.argv', arguments), redirect_stdout(io.StringIO()):
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

    def inline_evaluate(self, hit=1, stale=False, php_statement=False):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            (root / 'lib').mkdir()
            source = '<?php\nreturn true;\n?>\n<script>\nreturn true;\n</script>\n'
            (root / 'lib/example.php').write_text(source)
            content = '\n' * 4 + ('return false;' if stale else 'return true;') + '\n'
            coverage = {'js': [{'url': 'http://fixture/lib/example.php', 'source': content,
                         'functions': [{'ranges': [{'startOffset': 0, 'endOffset': len(content), 'count': hit}]}]}]}
            (root / 'changed-coverage.json').write_text(json.dumps(coverage))
            added = {2} if php_statement else {5}
            with patch.object(GATE.Path, 'cwd', return_value=root), patch.object(GATE, 'added_lines', return_value={'lib/example.php': added}), patch('sys.argv', ['gate', '--base', 'HEAD', '--browser-root', str(root)]), redirect_stdout(io.StringIO()):
                return GATE.main()

    def test_measured_inline_script_passes(self):
        self.assertEqual(self.inline_evaluate(), 0)

    def test_unexecuted_inline_script_fails(self):
        self.assertEqual(self.inline_evaluate(hit=0), 1)

    def test_stale_inline_script_fails(self):
        self.assertEqual(self.inline_evaluate(stale=True), 1)

    def test_browser_cannot_cover_php_statements(self):
        self.assertEqual(self.inline_evaluate(php_statement=True), 1)

    def test_embedded_php_is_excluded_from_script_lines(self):
        self.assertEqual(GATE.inline_script_lines('<?php\n?>\n<script <?php nonce(); ?>>\naction();\n<?php return true; ?>\n</script>'), {4})

    def test_explicit_threshold_preserves_the_default_security_gate(self):
        self.assertEqual(self.evaluate({2: 1, 3: -1}, (2, 3)), 1)
        self.assertEqual(self.evaluate({2: 1, 3: -1}, (2, 3), 50), 0)
        self.assertEqual(self.evaluate({2: 1, 3: -1}, (2, 3), 80), 1)

    def test_lower_threshold_does_not_allow_missing_evidence(self):
        self.assertEqual(self.evaluate(None, minimum=80), 1)

    def test_no_changed_lines_of_the_language_passes(self):
        # A PR that changes nothing in the gated language has nothing to cover,
        # so the gate must pass rather than fail closed on an empty report.
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            arguments = ['gate', '--base', 'HEAD']
            with patch.object(GATE.Path, 'cwd', return_value=root), patch.object(GATE, 'added_lines', return_value={}), patch('sys.argv', arguments), redirect_stdout(io.StringIO()):
                self.assertEqual(GATE.main(), 0)


if __name__ == '__main__':
    unittest.main()
