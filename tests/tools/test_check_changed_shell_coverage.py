"""Protect missing evidence, source identity and changed-line accounting.

Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
"""
import hashlib
import json
from pathlib import Path
import tempfile
import unittest
import xml.etree.ElementTree as ET
from check_changed_shell_coverage import measure, passes


class ShellCoverageGateTest(unittest.TestCase):
    def evidence(self, root, hits):
        script = root / 'script.sh'
        script.write_text('echo fixture\n' * len(hits))
        directory = root / 'reports/run'
        directory.mkdir(parents=True)
        (directory / 'source.json').write_text(json.dumps({
            'source': 'script.sh', 'native_source': '/tmp/fixture/script.sh',
            'sha256': hashlib.sha256(script.read_bytes()).hexdigest(),
        }))
        coverage = ET.Element('coverage')
        entry = ET.SubElement(coverage, 'class', filename='/tmp/fixture/script.sh')
        lines = ET.SubElement(entry, 'lines')
        for number, count in enumerate(hits, 1):
            ET.SubElement(lines, 'line', number=str(number), hits=str(count))
        ET.ElementTree(coverage).write(directory / 'cov.xml')
        return root / 'reports'

    def test_missing_evidence_cannot_pass_with_zero_statements(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            result = measure({'script.sh': {1}}, root / 'reports', root)
            self.assertFalse(passes(result, 80))

    def test_uncovered_changed_lines_stay_in_the_denominator(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            reports = self.evidence(root, [1, 0, 1])
            result = measure({'script.sh': {1, 2}}, reports, root)
            self.assertEqual((result['covered'], result['executable']), (1, 2))
            self.assertFalse(passes(result, 80))

    def test_changed_source_cannot_reuse_old_coverage(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            reports = self.evidence(root, [1])
            (root / 'script.sh').write_text('different source\n')
            with self.assertRaisesRegex(ValueError, 'does not match'):
                measure({'script.sh': {1}}, reports, root)

    def test_manifest_without_native_report_fails(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            reports = self.evidence(root, [1])
            (reports / 'run/cov.xml').unlink()
            with self.assertRaisesRegex(ValueError, 'Missing native'):
                measure({'script.sh': {1}}, reports, root)

    def test_threshold_uses_counts(self):
        self.assertTrue(passes({'covered': 8, 'executable': 10, 'files': {}}, 80))
        self.assertFalse(passes({'covered': 7, 'executable': 10, 'files': {}}, 80))


if __name__ == '__main__':
    unittest.main()
