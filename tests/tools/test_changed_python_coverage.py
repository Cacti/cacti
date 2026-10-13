"""Guard against missing coverage files and rounded threshold failures.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import unittest
from unittest.mock import patch
import check_changed_python_coverage as gate


class ChangedPythonCoverageTest(unittest.TestCase):
    def test_missing_changed_file_fails(self):
        result = gate.measure({'scripts/a.py': {1}}, {'files': {}})
        self.assertFalse(gate.passes(result, 80))

    def test_old_covered_lines_do_not_inflate_changed_coverage(self):
        result = gate.measure({'scripts/a.py': {3, 4}}, {'files': {
            'scripts/a.py': {'executed_lines': [1, 2, 3], 'missing_lines': [4]}}})
        self.assertEqual(result['covered'], 1)
        self.assertEqual(result['executable'], 2)
        self.assertFalse(gate.passes(result, 80))

    def test_threshold_is_not_rounded_up(self):
        self.assertFalse(gate.passes({'covered': 7999, 'executable': 10000, 'files': {}}, 80))
        self.assertTrue(gate.passes({'covered': 80, 'executable': 100, 'files': {}}, 80))

    def test_deletions_do_not_require_added_line_coverage(self):
        diff = ('diff --git a/scripts/a.py b/scripts/a.py\n+++ b/scripts/a.py\n'
                '@@ -3 +2,0 @@\n-return True\n')
        with patch.object(gate.subprocess, 'check_output', side_effect=['base', diff]):
            self.assertEqual(gate.added_lines('HEAD^'), {})


if __name__ == '__main__':
    unittest.main()
