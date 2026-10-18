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
    def test_library_evidence_requires_completion_and_every_current_producer(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            producers = ['tests/fixtures/Core/LibraryBehavior.php',
                         'tests/fixtures/Core/LibraryCollection.php',
                         'tests/fixtures/Core/LibraryAuthentication.php',
                         'tests/fixtures/Core/LibraryStorage.php',
                         'tests/fixtures/Core/LibraryForms.php',
                         'tests/fixtures/Core/LibraryDatabase.php',
                         'tests/fixtures/Core/LibraryPlugins.php',
                         'tests/fixtures/Core/LibraryReports.php',
                         'tests/fixtures/Core/LibraryDevices.php',
                         'tests/fixtures/Core/LibraryAccess.php',
                         'tests/fixtures/Core/LibraryWeb.php',
                         'tests/fixtures/Core/LibraryLogs.php',
        'tests/fixtures/Core/LibraryPluginCatalog.php',
        'tests/fixtures/Core/LibraryUtilities.php',
        'tests/fixtures/Core/LibraryLdap.php',
        'tests/fixtures/Core/LibraryRemote.php',
        'tests/fixtures/Core/LibraryGraphOptions.php',
        'tests/fixtures/Core/LibraryProfiles.php',
        'tests/fixtures/Core/LibraryPropagation.php',
        'tests/fixtures/Core/LibraryGraphCache.php',
        'tests/fixtures/Core/LibraryBoundaries.php',
        'tests/fixtures/Core/LibraryValidation.php',
        'tests/fixtures/Core/LibraryRrdStorage.php',
        'tests/fixtures/Core/LibraryLoginRouting.php',
        'tests/fixtures/Core/LibraryAdministration.php',
        'tests/fixtures/Core/LibraryResources.php',
        'tests/fixtures/Core/LibraryProbe.php',
        'tests/fixtures/Core/LibraryTransfer.php',
        'tests/fixtures/Core/LibraryRemoval.php',
        'tests/fixtures/Core/LibraryQueues.php',
        'tests/fixtures/Core/LibraryErrorHandling.php',
        'tests/fixtures/Core/LibraryReindex.php',
        'tests/fixtures/Core/LibraryRuntime.php',
        'tests/fixtures/Core/LibraryProvider.php',
        'tests/fixtures/Core/LibraryMib.php',
        'tests/fixtures/Core/LibraryLifecycle.php',
        'tests/fixtures/Core/LibraryReportDelivery.php',
        'tests/fixtures/Core/LibraryDiagnostics.php',
        'tests/fixtures/Core/LibraryNotifications.php',
        'tests/fixtures/Core/LibraryMaintenance.php',
        'tests/fixtures/Core/LibraryWorkers.php',
        'tests/fixtures/Core/LibraryWorker.php',
                         'tests/fixtures/Core/PluginProducer.php',
                         'tests/fixtures/Core/QueryProducer.php',
                         'tests/e2e/library-behavior.sh', 'tests/Helpers/RequestCoverage.php',
        'tests/Helpers/SourceSlice.php',
        'tests/Helpers/PhpSource.php',
        'cli/fetch_plugins.php',
                         'tests/e2e/Dockerfile', 'tests/e2e/entrypoint.sh',
                         'tests/e2e/docker-compose.yml', 'tests/e2e/ldap/50-e2e.ldif',
        'tests/fixtures/snmp-coverage/Dockerfile',
                         'tests/fixtures/snmp-coverage/snmpd.conf', 'cacti.sql', 'composer.lock',
                         'resource/snmp_queries/interface.xml',
                         'install/templates/Local_Linux_Machine.xml.gz',
                         'install/templates/NetSNMP_Device.xml.gz']
            hashes = {}
            for name in producers:
                source = root / name
                source.parent.mkdir(parents=True, exist_ok=True)
                source.write_text('fixture source\n')
                hashes[name] = hashlib.sha256(source.read_bytes()).hexdigest()
            contract = {'name': 'library-behavior', 'complete': True, 'assertions': 5600,
                        'producers': producers, 'hashes': hashes}
            path = root / 'library-behavior-test.json'
            GATE.validate_fixture_contract({'contract': contract}, path, root)
            for missing in ['name', 'complete', 'assertions', 'producers', 'hashes']:
                invalid = {key: value for key, value in contract.items() if key != missing}
                with self.subTest(missing=missing), self.assertRaises(ValueError):
                    GATE.validate_fixture_contract({'contract': invalid}, path, root)
            for value in [0, 616, 737, 748, 749, 940, 1252, 1264, 1310, 1358, 1453, 1454, 1738, 1739, 2707, 2708, 2932, 2933, 3129, 5599, True, '5600']:
                with self.subTest(assertions=value), self.assertRaises(ValueError):
                    GATE.validate_fixture_contract({'contract': dict(contract, assertions=value)}, path, root)
            with self.assertRaises(ValueError):
                GATE.validate_fixture_contract({'contract': dict(contract, complete=False)}, path, root)
            for name in producers:
                invalid = dict(contract, hashes={key: value for key, value in hashes.items() if key != name})
                with self.subTest(producer=name), self.assertRaises(ValueError):
                    GATE.validate_fixture_contract({'contract': invalid}, path, root)
                (root / name).write_text('changed fixture source\n')
                with self.subTest(stale=name), self.assertRaises(ValueError):
                    GATE.validate_fixture_contract({'contract': contract}, path, root)
                (root / name).write_text('fixture source\n')

    def test_package_evidence_requires_both_scenarios_and_current_producers(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            producers = ['tests/fixtures/Core/PackageArchive.php', 'tests/Helpers/RequestCoverage.php']
            hashes = {}
            for name in producers:
                source = root / name
                source.parent.mkdir(parents=True, exist_ok=True)
                source.write_text('fixture source\n')
                hashes[name] = hashlib.sha256(source.read_bytes()).hexdigest()
            contract = {'name': 'package-archive', 'complete': True, 'assertions': 2,
                        'producers': producers, 'hashes': hashes}
            path = root / 'package-archive-test.json'
            GATE.validate_package_contract({'contract': contract}, path, root)
            for field in contract:
                with self.subTest(missing=field), self.assertRaises(ValueError):
                    GATE.validate_package_contract({'contract': {k: v for k, v in contract.items() if k != field}}, path, root)
            for count in [0, 1, 3, True, '2']:
                with self.subTest(count=count), self.assertRaises(ValueError):
                    GATE.validate_package_contract({'contract': dict(contract, assertions=count)}, path, root)
            with self.assertRaises(ValueError):
                GATE.validate_package_contract({'contract': dict(contract, complete=False)}, path, root)
            for name in producers:
                with self.subTest(missing_producer=name), self.assertRaises(ValueError):
                    GATE.validate_package_contract({'contract': dict(contract, hashes={k: v for k, v in hashes.items() if k != name})}, path, root)
                (root / name).write_text('changed source\n')
                with self.subTest(stale=name), self.assertRaises(ValueError):
                    GATE.validate_package_contract({'contract': contract}, path, root)
                (root / name).write_text('fixture source\n')

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

    def test_deletion_only_changes_pass_without_a_coverage_report(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            result = GATE.measure({}, root / 'coverage-changed.xml', root)
            self.assertEqual(result['executable'], 0)
            self.assertTrue(GATE.passes(result, 80))

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

    def test_normalized_reports_require_current_tool_and_lock_without_changing_denominator(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / 'library.php'
            source.write_text('<?php\nreturn true;\n')
            (root / 'tests/tools').mkdir(parents=True)
            normalizer = root / 'tests/tools/normalize_request_coverage.php'
            lock = root / 'composer.lock'
            normalizer.write_text('normalizer fixture')
            lock.write_text('locked dependencies fixture')
            report = root / 'coverage.xml'
            report.write_text(f'<coverage><project><file name="{source}">'
                              '<line num="2" type="stmt" count="0"/>'
                              '<line num="3" type="stmt" count="0"/>'
                              '</file></project></coverage>')
            requests = root / 'requests'
            requests.mkdir()
            metadata = {'normalizer_sha256': hashlib.sha256(normalizer.read_bytes()).hexdigest(),
                        'composer_lock_sha256': hashlib.sha256(lock.read_bytes()).hexdigest(),
                        'input_sha256': 'a' * 64}
            snapshot = {'root': '/app', 'coverage': {'/app/library.php': {'2': 1}},
                        'hashes': {'/app/library.php': hashlib.sha256(source.read_bytes()).hexdigest()},
                        'normalization': metadata}
            path = requests / 'one.json'
            path.write_text(json.dumps(snapshot))
            result = GATE.measure({'library.php': {2, 3}}, report, root, requests)
            self.assertEqual((result['covered'], result['executable']), (1, 2))
            self.assertFalse(GATE.passes(result, 80))
            for field in metadata:
                for value in ['', '0' * 63, None, True]:
                    with self.subTest(field=field, value=value):
                        snapshot['normalization'] = dict(metadata, **{field: value})
                        path.write_text(json.dumps(snapshot))
                        with self.assertRaises(ValueError):
                            GATE.measure({'library.php': {2, 3}}, report, root, requests)
            for value in [None, [], True]:
                snapshot['normalization'] = value
                path.write_text(json.dumps(snapshot))
                with self.assertRaises(ValueError):
                    GATE.measure({'library.php': {2, 3}}, report, root, requests)

    def test_bootstrap_instructions_remain_in_the_coverage_denominator(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'include').mkdir()
            source = root / 'include/global.php'
            source.write_text('<?php\n$theme = "modern";\n$_SESSION["theme"] = $theme;\n')
            report = root / 'coverage.xml'
            report.write_text(f'<coverage><project><file name="{source}"/></project></coverage>')
            result = GATE.measure({'include/global.php': {2, 3}}, report, root)
            self.assertEqual(result['executable'], 2)
            self.assertFalse(GATE.passes(result, 80))
            requests = root / 'requests'
            requests.mkdir()
            snapshot = {'root': '/var/www/html', 'coverage': {
                '/var/www/html/include/global.php': {'2': 1, '3': -1}}, 'hashes': {
                '/var/www/html/include/global.php': hashlib.sha256(source.read_bytes()).hexdigest()}}
            (requests / 'one.json').write_text(json.dumps(snapshot))
            result = GATE.measure({'include/global.php': {2, 3}}, report, root, requests)
            self.assertEqual(result['covered'], 1)
            self.assertEqual(result['executable'], 2)
            self.assertFalse(GATE.passes(result, 80))

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
