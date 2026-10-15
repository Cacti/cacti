"""Regression tests for missing files and coverage threshold boundaries.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import importlib.util
import hashlib
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch
import xml.etree.ElementTree as ET

sys.dont_write_bytecode = True
SPEC = importlib.util.spec_from_file_location(
    'changed_php_coverage', Path(__file__).with_name('check_changed_php_coverage.py'))
GATE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(GATE)


class ChangedPhpCoverageTest(unittest.TestCase):
    def test_diff_uses_merge_base_and_ignores_deletion_only_files(self):
        diff = ('diff --git a/lib/a.php b/lib/a.php\n+++ b/lib/a.php\n'
                '@@ -4,0 +5,2 @@\n+return true;\n+}\n'
                'diff --git a/lib/b.php b/lib/b.php\n+++ b/lib/b.php\n'
                '@@ -3 +2,0 @@\n-return false;\n')
        with patch.object(GATE.subprocess, 'check_output', side_effect=['merge-base\n', diff]) as command:
            self.assertEqual(GATE.added_lines('base', Path('.')), {'lib/a.php': {5, 6}})
            self.assertIn('merge-base', command.call_args_list[1].args[0])

    def test_missing_entrypoint_fails_even_if_library_is_fully_covered(self):
        result = {'covered': 100, 'executable': 100, 'files': {
            'lib/a.php': {'covered': 100}, 'plugins.php': {'missing': True}}}
        self.assertFalse(GATE.passes(result, 80))

    def test_threshold_does_not_round_a_failure_up_to_eighty(self):
        self.assertFalse(GATE.passes({'covered': 7999, 'executable': 10000, 'files': {}}, 80))
        self.assertTrue(GATE.passes({'covered': 80, 'executable': 100, 'files': {}}, 80))

    def test_no_executable_changes_are_not_a_missing_report(self):
        self.assertTrue(GATE.passes({'covered': 0, 'executable': 0, 'files': {}}, 80))
        self.assertFalse(GATE.passes({'covered': 0, 'executable': 0, 'files': {
            'plugins.php': {'missing': True}}}, 80))

    def test_configuration_only_change_needs_no_clover_report(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            result = GATE.measure({}, root / 'not-created.xml', root)
            self.assertTrue(GATE.passes(result, 80))
            self.assertEqual(result['files'], {})

    def test_clover_counts_only_statements_on_changed_lines(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            report = root / 'coverage.xml'
            report.write_text(f'<coverage><project><file name="{root}/plugins.php">'
                              '<line num="3" type="method" count="1"/>'
                              '<line num="3" type="stmt" count="0"/>'
                              '<line num="4" type="stmt" count="2"/>'
                              '<line num="9" type="stmt" count="5"/>'
                              '</file></project></coverage>')
            result = GATE.measure({'plugins.php': {3, 4, 5}}, report, root)
            self.assertEqual(result['covered'], 1)
            self.assertEqual(result['executable'], 2)
            self.assertEqual(result['files']['plugins.php']['uncovered'], [3])

    def test_config_keeps_tests_and_adds_entrypoints_to_source_scope(self):
        with tempfile.TemporaryDirectory() as directory:
            config = Path(directory) / 'input.xml'
            output = Path(directory) / 'output.xml'
            config.write_text('<phpunit bootstrap="tests/bootstrap-unit.php">'
                              '<testsuites><testsuite name="Unit"><directory>tests/Unit</directory>'
                              '</testsuite></testsuites><source><include><directory>lib</directory>'
                              '</include></source></phpunit>')
            GATE.prepare_config(config, output, {'lib/plugins.php': {3}, 'plugins.php': {4}})
            tree = ET.parse(output)
            self.assertEqual(tree.getroot().attrib['bootstrap'], 'tests/bootstrap-unit.php')
            self.assertEqual(tree.findtext('testsuites/testsuite/directory'), 'tests/Unit')
            self.assertEqual([(output.parent / file.text).resolve() for file in tree.findall('source/include/file')],
                             [(Path.cwd() / name).resolve() for name in ['lib/plugins.php', 'plugins.php']])

    def test_relocated_config_keeps_bootstrap_and_test_paths(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            tests = root / 'tests'
            tests.mkdir()
            config = tests / 'phpunit.xml'
            output = root / 'phpunit-changed.xml'
            config.write_text('<phpunit bootstrap="../tests/bootstrap-unit.php">'
                              '<testsuites><testsuite name="Unit">'
                              '<directory>../tests/Unit</directory>'
                              '</testsuite></testsuites></phpunit>')
            GATE.prepare_config(config, output, {'lib/plugins.php': {3}})
            tree = ET.parse(output)
            self.assertEqual((output.parent / tree.getroot().get('bootstrap')).resolve(),
                             (tests / 'bootstrap-unit.php').resolve())
            self.assertEqual((output.parent / tree.findtext('testsuites/testsuite/directory')).resolve(),
                             (tests / 'Unit').resolve())

    def test_request_reports_require_matching_source_and_keep_uncovered_lines(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / 'plugins.php'
            source.write_text('<?php\nreturn true;\n')
            report = root / 'coverage.xml'
            report.write_text(f'<coverage><project><file name="{source}">'
                              '<line num="2" type="stmt" count="0"/>'
                              '<line num="3" type="stmt" count="0"/>'
                              '</file></project></coverage>')
            requests = root / 'requests'
            requests.mkdir()
            snapshot = {'root': '/var/www/html', 'coverage': {
                '/var/www/html/plugins.php': {'2': 1}}, 'hashes': {
                '/var/www/html/plugins.php': hashlib.sha256(source.read_bytes()).hexdigest()}}
            (requests / 'one.json').write_text(json.dumps(snapshot))
            result = GATE.measure({'plugins.php': {2, 3}}, report, root, requests)
            self.assertEqual(result['covered'], 1)
            self.assertEqual(result['executable'], 2)
            self.assertEqual(result['files']['plugins.php']['uncovered'], [3])
            source.write_text('<?php\nreturn false;\n')
            with self.assertRaisesRegex(ValueError, 'source does not match'):
                GATE.measure({'plugins.php': {2, 3}}, report, root, requests)

    def test_missing_request_reports_do_not_pass(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            report = root / 'coverage.xml'
            report.write_text('<coverage/>')
            with self.assertRaisesRegex(ValueError, 'contains no reports'):
                GATE.measure({}, report, root, root)

    def test_multiline_html_is_covered_only_when_its_output_opcode_runs(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / 'page.php'
            source.write_text('<?php ?>\n<header>\nName\n</header><?php return;\n')
            report = root / 'coverage.xml'
            report.write_text(f'<coverage><project><file name="{source}">'
                              '<line num="2" type="stmt" count="0"/>'
                              '</file></project></coverage>')
            requests = root / 'requests'
            requests.mkdir()
            snapshot = {'root': '/var/www/html', 'coverage': {
                '/var/www/html/page.php': {'4': -1}}, 'hashes': {
                '/var/www/html/page.php': hashlib.sha256(source.read_bytes()).hexdigest()},
                'html_spans': {'/var/www/html/page.php': [[2, 4]]}}
            (requests / 'one.json').write_text(json.dumps(snapshot))
            self.assertEqual(GATE.measure({'page.php': {2}}, report, root, requests)['covered'], 0)
            snapshot['coverage']['/var/www/html/page.php']['4'] = 1
            (requests / 'one.json').write_text(json.dumps(snapshot))
            self.assertEqual(GATE.measure({'page.php': {2}}, report, root, requests)['covered'], 1)


if __name__ == '__main__':
    unittest.main()
