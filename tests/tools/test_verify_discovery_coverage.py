"""Regression checks for mandatory discovery coverage evidence.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import copy
import hashlib
import importlib.util
import json
from pathlib import Path
import tempfile
import subprocess
import sys
import unittest
import xml.etree.ElementTree as ET

SPEC = importlib.util.spec_from_file_location(
    'discovery_coverage', Path(__file__).with_name('verify_discovery_coverage.py'))
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class DiscoveryCoverageEvidenceTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.directory = Path(self.temporary.name)
        names = [
            'discovery enumerates admitted addresses', 'discovery rejects invalid',
            'discovery worker probes admitted hosts', 'discovery child arguments',
            'matches discovery rules', 'overlapping discovery ranges',
            'discovery records reachable non-snmp', 'discovery records ping outages',
        ]
        self.native = ET.Element('testsuite')
        for name in names + [f'case {number}' for number in range(61)]:
            ET.SubElement(self.native, 'testcase', name=name)
        self.browser = ET.Element('testsuite')
        for name in ('real UI config', 'UI cancellation', 'an ordinary account'):
            ET.SubElement(self.browser, 'testcase', name=name)
        self.report = {'root': str(MODULE.ROOT), 'coverage': {}, 'hashes': {}}
        for name in [*MODULE.SOURCES, *MODULE.DEPENDENCIES, *('tests/fixtures/' + item for item in MODULE.NATIVE)]:
            path = MODULE.ROOT / name
            lines = {'1': 1}
            marker = {'poller_automation.php': 'automation_get_scan_arguments(',
                      'automation_graph_rules.php': "get_hash_automation((int) $save['id']"}.get(name)
            if marker:
                lines.update({str(number): 1 for number, line in
                              enumerate(path.read_text().splitlines(), 1) if marker in line})
            self.report['coverage'][str(path)] = lines
            self.report['hashes'][str(path)] = hashlib.sha256(path.read_bytes()).hexdigest()

        MODULE.prepare(self.directory)

    def write(self):
        ET.ElementTree(self.native).write(self.directory / 'discovery-native.xml')
        ET.ElementTree(self.browser).write(self.directory / 'discovery-browser.xml')
        (self.directory / 'request.json').write_text(json.dumps(self.report))

    def verify(self, **kwargs):
        self.write()
        return MODULE.verify(self.directory, **kwargs)

    def test_complete_evidence_is_admitted(self):
        self.assertEqual(self.verify()['native_cases'], 69)

    def test_missing_native_report_fails(self):
        self.write()
        (self.directory / 'discovery-native.xml').unlink()
        with self.assertRaises(OSError):
            MODULE.verify(self.directory)

    def test_missing_browser_report_fails(self):
        self.write()
        (self.directory / 'discovery-browser.xml').unlink()
        with self.assertRaises(OSError):
            MODULE.verify(self.directory)

    def test_empty_or_truncated_native_suite_fails(self):
        self.native.remove(list(self.native)[-1])
        with self.assertRaisesRegex(ValueError, '69 native'):
            self.verify()

    def test_missing_native_assertion_marker_fails(self):
        list(self.native)[0].set('name', 'unrelated test')
        with self.assertRaisesRegex(ValueError, 'native discovery assertion'):
            self.verify()

    def test_skipped_native_case_fails(self):
        ET.SubElement(list(self.native)[0], 'skipped')
        with self.assertRaisesRegex(ValueError, 'successful cases'):
            self.verify()

    def test_failed_browser_case_fails(self):
        ET.SubElement(list(self.browser)[0], 'failure')
        with self.assertRaisesRegex(ValueError, 'successful cases'):
            self.verify()

    def test_three_unrelated_browser_cases_fail(self):
        list(self.browser)[0].set('name', 'unrelated test')
        with self.assertRaisesRegex(ValueError, 'browser assertion'):
            self.verify()

    def test_each_native_producer_is_required(self):
        original = copy.deepcopy(self.report)
        for name in MODULE.NATIVE:
            with self.subTest(producer=name):
                self.report = copy.deepcopy(original)
                del self.report['coverage'][str(MODULE.ROOT / 'tests/fixtures' / name)]
                with self.assertRaisesRegex(ValueError, 'native producer'):
                    self.verify()

    def test_stale_sources_and_producers_fail(self):
        for path in self.report['hashes']:
            with self.subTest(path=path):
                original = self.report['hashes'][path]
                self.report['hashes'][path] = '0' * 64
                with self.assertRaisesRegex(ValueError, 'Stale discovery'):
                    self.verify()
                self.report['hashes'][path] = original

    def test_both_worker_launches_must_execute(self):
        self.report['coverage'][str(MODULE.ROOT / 'poller_automation.php')] = {'1': 1}
        with self.assertRaisesRegex(ValueError, 'master/worker'):
            self.verify()

    def test_both_criteria_saves_must_execute(self):
        self.report['coverage'][str(MODULE.ROOT / 'automation_graph_rules.php')] = {'1': 1}
        with self.assertRaisesRegex(ValueError, 'criteria execution'):
            self.verify()

    def test_empty_reports_fail(self):
        self.write()
        (self.directory / 'request.json').unlink()
        with self.assertRaisesRegex(ValueError, 'Missing discovery coverage'):
            MODULE.verify(self.directory)

    def test_malformed_report_fails(self):
        self.write()
        (self.directory / 'request.json').write_text('{')
        with self.assertRaises(ValueError):
            MODULE.verify(self.directory)

    def test_each_native_dependency_is_required(self):
        original = copy.deepcopy(self.report)
        for name in MODULE.DEPENDENCIES:
            with self.subTest(dependency=name):
                self.report = copy.deepcopy(original)
                del self.report['coverage'][str(MODULE.ROOT / name)]
                with self.assertRaisesRegex(ValueError, 'dependency'):
                    self.verify()

    def test_malformed_envelope_fails(self):
        self.report = {'coverage': [], 'hashes': {}}
        with self.assertRaisesRegex(ValueError, 'Malformed'):
            self.verify()

    def test_empty_browser_suite_fails(self):
        self.browser.clear()
        with self.assertRaisesRegex(ValueError, 'successful cases'):
            self.verify()

    def test_cli_success_and_controlled_failure(self):
        self.write()
        command = [sys.executable, str(Path(MODULE.__file__)), str(self.directory)]
        result = subprocess.run(command, text=True, capture_output=True, check=False)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(json.loads(result.stdout)['native_cases'], 69)
        (self.directory / 'request.json').write_text('{')
        result = subprocess.run(command, text=True, capture_output=True, check=False)
        self.assertEqual(result.returncode, 1)
        self.assertIn('Discovery coverage could not be verified', result.stderr)
        self.assertNotIn('Traceback', result.stderr)

    def test_missing_scenario_fingerprints_fail(self):
        (self.directory / 'discovery-scenarios.txt').unlink()
        with self.assertRaises(OSError):
            self.verify()

    def test_stale_or_missing_scenario_fingerprints_fail(self):
        path = self.directory / 'discovery-scenarios.txt'
        original = json.loads(path.read_text())
        for mutation in ('stale', 'missing'):
            with self.subTest(mutation=mutation):
                captured = dict(original)
                name = 'tests/e2e/discovery/discovery.spec.ts'
                if mutation == 'stale':
                    captured[name] = '0' * 64
                else:
                    del captured[name]
                path.write_text(json.dumps(captured))
                with self.assertRaisesRegex(ValueError, 'scenario sources changed'):
                    self.verify()

    def test_scenario_capture_cannot_be_overwritten_mid_run(self):
        with self.assertRaises(FileExistsError):
            MODULE.prepare(self.directory)

    def test_proof_binds_final_junits_and_scenarios(self):
        proof = self.verify()
        self.assertEqual(proof['scenario_hashes'], MODULE.scenario_hashes(MODULE.ROOT))
        for name, digest in proof['junit_hashes'].items():
            self.assertEqual(digest, hashlib.sha256((self.directory / name).read_bytes()).hexdigest())

    def test_native_phase_does_not_claim_browser_execution(self):
        self.assertEqual(self.verify(native_only=True)['native_cases'], 69)


if __name__ == '__main__':
    unittest.main()
