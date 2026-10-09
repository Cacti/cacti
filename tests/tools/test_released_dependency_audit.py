"""Exercise the workflow's audit commands without calling package registries."""

import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

import yaml


ROOT = Path(__file__).resolve().parents[2]
WORKFLOW = ROOT / '.github/workflows/released-dependency-audit.yml'


class ReleasedDependencyAuditTest(unittest.TestCase):
    def audit(self, ecosystem, report, status=0):
        workflow = yaml.safe_load(WORKFLOW.read_text())
        command = next(step['run'] for step in workflow['jobs']['audit']['steps']
                       if step['name'] == 'Audit committed dependencies')
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            executable = root / ecosystem
            executable.write_text('#!/bin/sh\ncat "$FIXTURE_REPORT"\nexit "$FIXTURE_STATUS"\n')
            executable.chmod(0o755)
            fixture = root / 'fixture.json'
            fixture.write_text(report if isinstance(report, str) else json.dumps(report))
            result = subprocess.run(['bash', '-c', command], text=True, capture_output=True,
                                    env={**os.environ, 'PATH': f'{root}{os.pathsep}{os.environ["PATH"]}',
                                         'PACKAGE_ECOSYSTEM': ecosystem, 'GITHUB_WORKSPACE': directory,
                                         'FIXTURE_REPORT': str(fixture), 'FIXTURE_STATUS': str(status)})
            self.assertEqual((root / 'dependency-audit.json').read_text(), fixture.read_text())
            return result

    def test_clean_locks_pass(self):
        for ecosystem, report in [('composer', {'advisories': [], 'abandoned': {}}),
                                  ('npm', {'metadata': {'vulnerabilities': {'total': 0}}})]:
            with self.subTest(ecosystem=ecosystem):
                result = self.audit(ecosystem, report)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertNotIn('::warning', result.stdout)

    def test_composer_advisories_are_reported(self):
        result = self.audit('composer', {'advisories': {'vendor/package': [{'title': 'fixture'}]},
                                         'abandoned': {}}, 1)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('1 advisory or abandoned-package records', result.stdout)

    def test_abandoned_packages_are_reported(self):
        result = self.audit('composer', {'advisories': [], 'abandoned': {'vendor/package': None}}, 2)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('1 advisory or abandoned-package records', result.stdout)

    def test_npm_advisories_are_reported(self):
        result = self.audit('npm', {'metadata': {'vulnerabilities': {'total': 3}}}, 1)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('3 advisory or abandoned-package records', result.stdout)

    def test_invalid_reports_fail(self):
        for ecosystem in ['composer', 'npm']:
            for report in ['registry unavailable', {'error': {'message': 'fixture'}}]:
                with self.subTest(ecosystem=ecosystem, report=report):
                    self.assertNotEqual(self.audit(ecosystem, report, 1).returncode, 0)

    def test_tool_failures_without_findings_fail(self):
        for ecosystem, report in [('composer', {'advisories': [], 'abandoned': {}}),
                                  ('npm', {'metadata': {'vulnerabilities': {'total': 0}}})]:
            with self.subTest(ecosystem=ecosystem):
                self.assertEqual(self.audit(ecosystem, report, 42).returncode, 42)


if __name__ == '__main__':
    unittest.main()
