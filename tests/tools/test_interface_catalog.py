# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""Build-time contract tests; no Cacti bootstrap, database or network requests."""
import copy
import importlib.util
import contextlib
import io
import gzip
import hashlib
import tempfile
from unittest import mock
import json
from pathlib import Path
import subprocess
import sys
import unittest

from jsonschema import ValidationError

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location('interface_catalog', ROOT / '.github/scripts/catalog/interface_catalog.py')
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class InterfaceCatalogTest(unittest.TestCase):
    def setUp(self):
        self.catalog = MODULE.load_json(ROOT / MODULE.CATALOG)

    def rejected(self, catalog):
        with self.assertRaises((ValueError, ValidationError)):
            MODULE.validate(catalog, ROOT)

    def test_validates_nonempty_initial_family(self):
        self.assertEqual(MODULE.validate(self.catalog, ROOT), {'fields': 42, 'identities': 3, 'dependencies': 66, 'metrics': 2})

    def test_renderer_reproduces_shipped_bytes_twice(self):
        before = (ROOT / self.catalog['query']['path']).read_bytes()
        self.assertEqual(MODULE.render_query(self.catalog), before)
        self.assertEqual(MODULE.render_query(self.catalog), before)
        result = subprocess.run([sys.executable, str(ROOT / '.github/scripts/catalog/interface_catalog.py'), '--render'], capture_output=True, check=True)
        self.assertEqual(result.stdout, before)
        self.assertEqual(result.stderr, b'')

    def test_unknown_keys_at_each_boundary_are_rejected(self):
        for path in [[], ['query'], ['package'], ['query', 'fields', 0], ['metrics', 0]]:
            catalog = copy.deepcopy(self.catalog)
            target = catalog
            for key in path:
                target = target[key]
            target['unexpected'] = True
            with self.subTest(path=path):
                self.rejected(catalog)

    def test_duplicate_json_keys_are_not_silently_overwritten(self):
        with self.assertRaises(ValueError):
            json.loads('{"family_id":"one","family_id":"two"}', object_pairs_hook=MODULE.unique_pairs)

    def test_duplicate_native_identities_are_rejected(self):
        self.catalog['identities'].append(self.catalog['identities'][0])
        self.rejected(self.catalog)

    def test_unknown_native_identity_is_rejected(self):
        self.catalog['identities'][0] = 'hash_040104' + 'f' * 32
        self.rejected(self.catalog)

    def test_unrelated_existing_graph_cannot_replace_traffic_graph(self):
        package = self.catalog['package']
        files = MODULE.package_files((ROOT / package['path']).read_bytes())
        document = MODULE.parse_xml(files[package['template_file']])
        objects = {node.tag: node for node in document}
        old = next(value for value in self.catalog['identities'] if value.startswith('hash_00'))
        unrelated = next(node.tag for node in document if node.tag.startswith('hash_00') and node.findtext('name') == 'Device - Polling Time')
        self.catalog['identities'][self.catalog['identities'].index(old)] = unrelated
        self.catalog['dependencies'] = [d for d in self.catalog['dependencies'] if d['from'] != old]
        self.catalog['dependencies'].extend({'from': unrelated, 'to': ref} for ref in sorted({ref for node in objects[unrelated].iter() for ref in MODULE.HASH.findall(node.text or '')}))
        self.rejected(self.catalog)

    def test_duplicate_fields_and_properties_are_rejected(self):
        for duplicate in ['field', 'property']:
            catalog = copy.deepcopy(self.catalog)
            if duplicate == 'field':
                catalog['query']['fields'].append(catalog['query']['fields'][0])
            else:
                catalog['query']['fields'][0]['properties'].append(catalog['query']['fields'][0]['properties'][0])
            with self.subTest(duplicate=duplicate):
                self.rejected(catalog)

    def test_invalid_oid_shapes_are_rejected_by_schema(self):
        for value in ['.1.3.bad.4', '1.3.6', '.1.03.6', '.9.3.6', '.1.3.-1', '.1.3.4294967296']:
            catalog = copy.deepcopy(self.catalog)
            oid = next(p for p in catalog['query']['fields'][0]['properties'] if p['key'] == 'oid')
            oid['value'] = value
            with self.subTest(oid=value):
                self.rejected(catalog)

    def test_dependency_omission_addition_and_alteration_are_rejected(self):
        for change in ['remove', 'add', 'alter']:
            catalog = copy.deepcopy(self.catalog)
            if change == 'remove':
                catalog['dependencies'].pop()
            elif change == 'add':
                catalog['dependencies'].append({'from': catalog['identities'][0], 'to': 'hash_000104' + 'f' * 32})
            else:
                catalog['dependencies'][0]['from'] = 'hash_040104' + 'f' * 32
            with self.subTest(change=change):
                self.rejected(catalog)

    def test_package_and_embedded_query_fingerprints_are_independent(self):
        self.assertNotEqual(self.catalog['query']['sha256'], self.catalog['package']['embedded_query_sha256'])
        for key in ['sha256', 'embedded_query_sha256']:
            catalog = copy.deepcopy(self.catalog)
            catalog['package'][key] = 'f' * 64
            self.rejected(catalog)

    def test_renaming_does_not_generate_new_template_identities(self):
        original = self.catalog['identities'].copy()
        name = next(p for p in self.catalog['query']['header'] if p['key'] == 'name')
        name['value'] = 'Operator label & <example>'
        rendered = MODULE.render_query(self.catalog)
        self.assertIn(b'Operator label &amp; &lt;example&gt;', rendered)
        self.assertEqual(self.catalog['identities'], original)
        self.rejected(self.catalog)  # A changed generated artifact cannot pass the unchanged-output gate.

    def test_existing_rrd_semantics_and_bindings_cannot_drift(self):
        for key, value in [('rrd_type', 'DERIVE'), ('minimum', '-1'), ('maximum', '1000'), ('heartbeat', 42), ('rrd_item', self.catalog['metrics'][1]['rrd_item'])]:
            catalog = copy.deepcopy(self.catalog)
            catalog['metrics'][0][key] = value
            with self.subTest(property=key):
                self.rejected(catalog)

    def test_counter_width_must_match_actual_native_field(self):
        self.catalog['metrics'][0]['query_fields'][0]['counter_width'] = 64
        self.rejected(self.catalog)

    def test_explicit_bindings_do_not_depend_on_list_order(self):
        self.catalog['metrics'][0]['query_fields'].reverse()
        self.assertEqual(MODULE.validate(self.catalog, ROOT)['metrics'], 2)

    def test_oid_bounds_apply_to_headers_and_fields_before_rendering(self):
        for value in ['.1.40', '.0.40', '.1.3.4294967296', '.1.3' + '.1' * 127]:
            with self.subTest(value=value):
                with self.assertRaisesRegex(ValueError, 'OID'):
                    MODULE.validate_oid(value)
                catalog = copy.deepcopy(self.catalog)
                next(p for p in catalog['query']['header'] if p['key'] == 'oid_index')['value'] = value
                with self.assertRaisesRegex(ValueError, 'OID'):
                    MODULE.validate(catalog, ROOT)
        for value in ['.0.39', '.1.39', '.2.4294967295', '.1.3' + '.1' * 126]:
            MODULE.validate_oid(value)

    def test_invalid_index_reference_is_rejected(self):
        index = next(p for p in self.catalog['query']['header'] if p['key'] == 'index_order')
        index['value'] = 'missing_field'
        self.rejected(self.catalog)

    def test_cli_summary_render_and_controlled_error(self):
        output = io.StringIO()
        with mock.patch.object(sys, 'argv', ['interface_catalog']), contextlib.redirect_stdout(output):
            self.assertEqual(MODULE.main(), 0)
        self.assertEqual(json.loads(output.getvalue())['fields'], 42)
        raw = io.BytesIO()
        stream = io.TextIOWrapper(raw, encoding='utf-8', write_through=True)
        with mock.patch.object(sys, 'argv', ['interface_catalog', '--render']), contextlib.redirect_stdout(stream):
            self.assertEqual(MODULE.main(), 0)
        self.assertEqual(raw.getvalue(), (ROOT / self.catalog['query']['path']).read_bytes())
        with tempfile.TemporaryDirectory() as directory:
            error = io.StringIO()
            with mock.patch.object(MODULE, 'ROOT', Path(directory)), mock.patch.object(sys, 'argv', ['interface_catalog']), contextlib.redirect_stderr(error):
                with self.assertRaises(SystemExit) as exit_status:
                    MODULE.main()
            self.assertEqual(exit_status.exception.code, 1)
            self.assertIn('Catalog validation failed:', error.getvalue())
            self.assertNotIn('Traceback', error.getvalue())

    def test_package_parsing_rejects_entities_duplicates_and_oversized_data(self):
        for raw in [b'<!DOCTYPE xml><xml/>', b'<!ENTITY example "value"><xml/>',
                    b'<xml><files><file><name>same</name><data/></file><file><name>same</name><data/></file></files></xml>',
                    b'x' * (8 * 1024 * 1024 + 1)]:
            with self.subTest(prefix=raw[:30]):
                with self.assertRaises(ValueError):
                    MODULE.package_files(gzip.compress(raw))

    def test_xml_text_keeps_quotes_and_escapes_metacharacters(self):
        text = 'Interface "quoted" & <tag> \'single\''
        next(p for p in self.catalog['query']['header'] if p['key'] == 'name')['value'] = text
        rendered = MODULE.render_query(self.catalog)
        self.assertEqual(MODULE.parse_xml(rendered).findtext('name'), text)
        self.assertIn(b'"quoted" &amp; &lt;tag&gt;', rendered)
        self.assertNotIn(b'&quot;', rendered)

    def test_utf16_entity_declaration_is_rejected_with_controlled_cli_error(self):
        raw = '<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE xml [<!ENTITY value "expanded">]><xml>&value;</xml>'.encode('utf-16')
        with self.assertRaises(ValueError):
            MODULE.parse_xml(raw)
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            for path in [MODULE.SCHEMA, Path(self.catalog['query']['path'])]:
                (root / path).parent.mkdir(parents=True, exist_ok=True)
                (root / path).write_bytes((ROOT / path).read_bytes())
            package = root / self.catalog['package']['path']
            package.parent.mkdir(parents=True)
            package.write_bytes(gzip.compress(raw))
            self.catalog['package']['sha256'] = hashlib.sha256(package.read_bytes()).hexdigest()
            (root / MODULE.CATALOG).write_text(json.dumps(self.catalog))
            error = io.StringIO()
            with mock.patch.object(MODULE, 'ROOT', root), mock.patch.object(sys, 'argv', ['interface_catalog']), contextlib.redirect_stderr(error):
                with self.assertRaises(SystemExit) as exit_status:
                    MODULE.main()
            self.assertEqual(exit_status.exception.code, 1)
            self.assertIn('Catalog validation failed:', error.getvalue())
            self.assertNotIn('Traceback', error.getvalue())

    def test_artifact_symlink_cannot_read_outside_repository(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / 'checkout'
            root.mkdir()
            outside = Path(directory) / 'outside'
            outside.write_bytes(b'task owned')
            (root / 'alias').symlink_to(outside)
            with self.assertRaisesRegex(ValueError, 'escapes repository'):
                MODULE.artifact(root, 'alias', hashlib.sha256(b'task owned').hexdigest())

    def test_source_paths_cannot_escape_repository(self):
        self.catalog['query']['path'] = '../outside.xml'
        self.rejected(self.catalog)


if __name__ == '__main__':
    unittest.main()
