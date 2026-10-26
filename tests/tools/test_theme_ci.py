"""Regression tests for empty matrices and false green browser summaries.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
from contextlib import redirect_stdout
import importlib.util
import io
import json
import os
from pathlib import Path
import runpy
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.dont_write_bytecode = True
SCRIPT = Path(__file__).resolve().parents[2] / '.github/scripts/theme_ci.py'
SPEC = importlib.util.spec_from_file_location('theme_ci', SCRIPT)
CI = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(CI)
SHA = 'a' * 40


class ThemeCiTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.manifests = self.root / 'tests/e2e'
        self.manifests.mkdir(parents=True)
        self.plugins = {'thold': SHA}
        self.rtm = {'repository': 'TheWitness/ibm-spectrum-lsf-rtm-server', 'revision': SHA}
        self.save_manifests()
        self.output = self.root / 'evidence'
        self.output.mkdir()
        self.report = {'stats': {'expected': 12, 'unexpected': 0, 'flaky': 0, 'skipped': 0}, 'errors': []}
        self.save_report()

    def save_manifests(self):
        (self.manifests / 'plugin-theme-revisions.json').write_text(json.dumps(self.plugins))
        (self.manifests / 'rtm-theme-fixtures.json').write_text(json.dumps(self.rtm))

    def save_report(self):
        (self.output / 'results.json').write_text(json.dumps(self.report))

    def summary(self, suite='core', fixture='', tests='success', coverage='skipped'):
        with patch.dict(os.environ, {'GITHUB_SHA': SHA}, clear=True):
            return CI.summarize(self.output, suite, fixture, tests, coverage, self.root)

    def test_matrix_comes_from_both_manifests(self):
        result = CI.matrix(self.root)
        self.assertEqual(json.loads(result['plugins'])['include'],
                         [{'id': 'thold', 'repository': 'Cacti/plugin_thold', 'revision': SHA}])
        self.assertEqual(result['rtm_repository'], self.rtm['repository'])
        self.assertEqual(result['rtm_revision'], SHA)

    def test_invalid_or_empty_plugin_manifests_fail(self):
        for value in ({}, [], {'../escape': SHA}, {'rtm': SHA}, {'thold': 'develop'}, {'thold': 42}):
            with self.subTest(value=value):
                self.plugins = value
                self.save_manifests()
                with self.assertRaises(ValueError):
                    CI.matrix(self.root)

    def test_invalid_rtm_repository_or_mutable_revision_fails(self):
        for value in ({'repository': '../escape', 'revision': SHA},
                      {'repository': 'owner/repo', 'revision': 'main'}):
            self.rtm = value
            self.save_manifests()
            with self.assertRaises(ValueError):
                CI.matrix(self.root)

    def test_success_records_reports_artifacts_and_core_revision(self):
        results = self.output / 'test-results/case'
        results.mkdir(parents=True)
        (results / 'desktop.png').touch()
        (results / 'trace.zip').touch()
        text, success = self.summary()
        self.assertTrue(success)
        self.assertIn('| 12 | 0 | 0 | 0 |', text)
        self.assertIn('1 screenshots and 1 failure traces', text)
        self.assertEqual((self.output / 'summary.md').read_text(), text)
        self.assertEqual(json.loads((self.output / 'fixture.json').read_text())['core_revision'], SHA)

    def test_missing_report_is_not_a_passing_run(self):
        (self.output / 'results.json').unlink()
        text, success = self.summary(tests='skipped')
        self.assertFalse(success)
        self.assertIn('No Playwright JSON report', text)
        self.assertIn('**FAIL**', text)

    def test_all_skipped_tests_do_not_establish_compatibility(self):
        self.report['stats'] = {'expected': 0, 'unexpected': 0, 'flaky': 0, 'skipped': 12}
        self.save_report()
        self.assertFalse(self.summary()[1])

    def test_failed_tests_global_errors_or_failed_step_cannot_pass(self):
        self.report['stats']['unexpected'] = 1
        self.save_report()
        self.assertFalse(self.summary()[1])
        self.report['stats']['unexpected'] = 0
        self.report['errors'] = [{'message': 'Browser launch failed'}]
        self.save_report()
        text, success = self.summary()
        self.assertFalse(success)
        self.assertIn('setup or collection error', text)
        self.report['errors'] = []
        self.save_report()
        self.assertFalse(self.summary(tests='failure')[1])

    def test_partial_skips_do_not_claim_full_compatibility(self):
        self.report['stats']['skipped'] = 1
        self.save_report()
        self.assertFalse(self.summary()[1])

    def test_pr_and_merge_queue_cannot_pass_if_coverage_was_skipped(self):
        for event in ('pull_request', 'merge_group'):
            with self.subTest(event=event), patch.dict(os.environ, {'GITHUB_EVENT_NAME': event}, clear=True):
                self.assertFalse(CI.summarize(self.output, 'core', '', 'success', 'skipped', self.root)[1])

    def test_provenance_records_effective_coverage_base(self):
        (self.output / 'coverage-base.txt').write_text(SHA + '\n')
        self.summary()
        data = json.loads((self.output / 'fixture.json').read_text())
        self.assertEqual(data['coverage_base_revision'], SHA)

    def test_flaky_result_is_reported_without_hiding_it(self):
        self.report['stats']['flaky'] = 1
        self.save_report()
        text, success = self.summary()
        self.assertTrue(success)
        self.assertIn('| 12 | 0 | 1 | 0 |', text)

    def test_failed_coverage_step_fails_the_summary(self):
        self.assertFalse(self.summary(coverage='failure')[1])

    def test_missing_or_partial_css_evidence_cannot_pass(self):
        text, success = self.summary(coverage='success')
        self.assertFalse(success)
        self.assertIn('CSS coverage evidence is missing', text)
        for row in ({'covered': 0, 'executable': 1, 'instrumented': False, 'uncovered': [1]},
                    {'covered': 0, 'executable': 1, 'instrumented': True, 'uncovered': []}):
            (self.output / 'css-coverage.json').write_text(json.dumps({'main.css': row}))
            self.assertFalse(self.summary(coverage='success')[1])

    def test_complete_css_evidence_and_no_css_changes_are_distinguished(self):
        row = {'covered': 2, 'executable': 2, 'instrumented': True, 'uncovered': []}
        (self.output / 'css-coverage.json').write_text(json.dumps({'main.css': row}))
        text, success = self.summary(coverage='success')
        self.assertTrue(success)
        self.assertIn('**2/2**', text)
        (self.output / 'css-coverage.json').write_text('{}')
        text, success = self.summary(coverage='success')
        self.assertTrue(success)
        self.assertIn('No changed executable CSS lines', text)

    def test_fixture_provenance_and_chart_limit_are_preserved(self):
        text, success = self.summary(suite='rtm', fixture='rtm')
        self.assertTrue(success)
        self.assertIn('FusionCharts rendering is not covered', text)
        metadata = json.loads((self.output / 'fixture.json').read_text())
        self.assertEqual(metadata['fixture']['revision'], SHA)
        self.assertTrue(self.summary(suite='plugin', fixture='thold')[1])
        with self.assertRaises(ValueError):
            self.summary(suite='plugin', fixture='unknown')

    def test_cli_matrix_writes_github_outputs_and_local_stdout(self):
        output = self.root / 'github-output'
        with patch.dict(os.environ, {'GITHUB_OUTPUT': str(output)}, clear=True), \
             patch.object(sys, 'argv', ['theme_ci.py', 'matrix']), \
             patch.object(CI, 'matrix', return_value={'plugins': '{"include":[]}'}):
            self.assertEqual(CI.main(), 0)
        self.assertEqual(output.read_text(), 'plugins={"include":[]}\n')
        with patch.dict(os.environ, {}, clear=True), \
             patch.object(sys, 'argv', ['theme_ci.py', 'matrix']), \
             patch.object(CI, 'matrix', return_value={'plugins': '{}'}), redirect_stdout(io.StringIO()) as stdout:
            self.assertEqual(CI.main(), 0)
        self.assertIn('plugins={}', stdout.getvalue())

    def test_cli_publishes_summary_and_failure_exit_status(self):
        summary = self.root / 'github-summary'
        arguments = ['theme_ci.py', 'summary', '--suite', 'core', '--test-outcome', 'success']
        with patch.dict(os.environ, {'GITHUB_STEP_SUMMARY': str(summary)}, clear=True), \
             patch.object(CI, 'ARTIFACTS', self.output), patch.object(sys, 'argv', arguments), redirect_stdout(io.StringIO()):
            self.assertEqual(CI.main(), 0)
        self.assertIn('**PASS**', summary.read_text())
        arguments[-1] = 'failure'
        with patch.dict(os.environ, {}, clear=True), patch.object(CI, 'ARTIFACTS', self.output), patch.object(sys, 'argv', arguments), redirect_stdout(io.StringIO()):
            self.assertEqual(CI.main(), 1)

    def test_cli_rejects_configurable_output_paths(self):
        arguments = ['theme_ci.py', 'summary', '--suite', 'core', '--test-outcome', 'success', '--directory', '/unexpected']
        with patch.object(sys, 'argv', arguments), patch('sys.stderr', io.StringIO()), self.assertRaises(SystemExit) as result:
            CI.main()
        self.assertEqual(result.exception.code, 2)

    def test_script_entry_point_runs_the_manifest_command(self):
        with patch.dict(os.environ, {}, clear=True), patch.object(sys, 'argv', ['theme_ci.py', 'matrix']), \
             redirect_stdout(io.StringIO()), self.assertRaises(SystemExit) as result:
            runpy.run_path(str(SCRIPT), run_name='__main__')
        self.assertEqual(result.exception.code, 0)


if __name__ == '__main__':
    unittest.main()
