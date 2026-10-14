#!/usr/bin/env python3
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
import copy
import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

spec = importlib.util.spec_from_file_location('profile_report', Path(__file__).with_name('profile-report.py'))
report = importlib.util.module_from_spec(spec)
spec.loader.exec_module(report)


class ProfileReportTest(unittest.TestCase):
    def test_xdebug_units_compressed_names_and_nested_costs(self):
        # A parent spends 1ms itself, 2ms in SQL and 3ms at the RRD boundary.
        # Inclusive costs must not be counted again as self time.
        text = '''events: Time_(10ns) Memory_(bytes)
fl=(1) fixture.php
fn=(1) rrdtool_function_graph
1 100000 0
cfn=(2) php::PDOStatement->execute
calls=2 0 0
2 200000 0
cfn=(3) rrdtool_process_command
calls=1 0 0
3 300000 0
fn=(2)
1 200000 0
fn=(3)
1 300000 0
summary: 600000 0
'''
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'cachegrind.out.render'
            path.write_text(text)
            result = report.callgrind(path)
        self.assertEqual(result['total_ms'], 6)
        self.assertEqual(result['sql']['self_ms'], 2)
        self.assertEqual(result['sql']['calls'], 2)
        parent = next(row for row in result['top_self_costs'] if row['function'] == 'rrdtool_function_graph')
        self.assertEqual(parent['self_ms'], 1)
        self.assertEqual(parent['inclusive_ms'], 6)

    def test_rejects_truncated_profiles(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'cachegrind.out.render'
            path.write_text('events: Time_(10ns) Memory_(bytes)\nfn=render\n1 10 0\n')
            with self.assertRaisesRegex(ValueError, 'Incomplete'):
                report.callgrind(path)

    def test_rejects_partial_browser_workloads(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'browser.json').write_text(json.dumps({'complete': False}))
            with self.assertRaisesRegex(ValueError, 'did not finish'):
                report.summarize(root)

    def test_comparison_rejects_different_hardware_or_workload(self):
        baseline = {'schema': 1, 'environment': {'cpu': 'fixture CPU'}, 'fixture': {'graphs': 50},
                    'metadata': {'machine': 'host', 'revision': 'base'},
                    'metrics': {'render': {'median_ms': 100, 'samples': 3}}}
        for field, value in [('environment', {'cpu': 'other CPU'}), ('fixture', {'graphs': 10}),
                             ('metadata', {'machine': 'other host', 'revision': 'head'}),
                             ('metrics', {'render': {'median_ms': 100, 'samples': 4}})]:
            candidate = copy.deepcopy(baseline)
            candidate[field] = value
            with self.subTest(field=field), self.assertRaises(ValueError):
                report.compare(baseline, candidate)

    def test_matching_comparison_reports_direction(self):
        baseline = {'schema': 1, 'environment': {}, 'fixture': {},
                    'metadata': {'machine': 'host', 'revision': 'base'},
                    'metrics': {'render': {'median_ms': 100, 'samples': 3}}}
        candidate = copy.deepcopy(baseline)
        candidate['metrics']['render']['median_ms'] = 125
        self.assertEqual(report.compare(baseline, candidate)['metrics']['render']['change_percent'], 25)

    def test_nearest_rank_p95_and_invalid_samples(self):
        self.assertEqual(report.describe(list(range(1, 101)))['p95_ms'], 95)
        for values in ([], [float('nan')], [float('inf')]):
            with self.assertRaises(ValueError):
                report.describe(values)


if __name__ == '__main__':
    unittest.main()
