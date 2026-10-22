#!/usr/bin/env python3
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""Validate and render the existing IF-MIB query without changing runtime files."""
from __future__ import annotations

import argparse
import base64
import gzip
import hashlib
import io
import json
from pathlib import Path
import re
import sys
from defusedxml import ElementTree as ET
from html import escape

from jsonschema import Draft202012Validator
from jsonschema.exceptions import SchemaError, ValidationError
from referencing import Registry

ROOT = Path(__file__).resolve().parents[3]
CATALOG = Path('resource/catalogs/interface.json')
SCHEMA = Path('resource/catalogs/interface.schema.json')
HASH = re.compile(r'hash_[a-f0-9]{38}')


def unique_pairs(pairs: list[tuple[str, object]]) -> dict:
    result = {}
    for key, value in pairs:
        if key in result:
            raise ValueError(f'Duplicate JSON key: {key}')
        result[key] = value
    return result


def load_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding='utf-8'), object_pairs_hook=unique_pairs)


def schema_validate(catalog: dict, schema: dict) -> None:
    Draft202012Validator.check_schema(schema)
    Draft202012Validator(schema, registry=Registry()).validate(catalog)


def properties(items: list[dict]) -> dict[str, str]:
    result = {}
    for item in items:
        if item['key'] in result:
            raise ValueError(f"Duplicate XML property: {item['key']}")
        result[item['key']] = item['value']
    return result


def validate_oid(value: str) -> None:
    if not re.fullmatch(r'\.[0-2](?:\.(?:0|[1-9][0-9]*))+', value):
        raise ValueError('Malformed OID')
    arcs = [int(arc) for arc in value[1:].split('.')]
    if len(arcs) > 128 or any(arc > 4294967295 for arc in arcs) or (arcs[0] < 2 and arcs[1] > 39):
        raise ValueError('OID arc or length exceeds SNMP range')


def render_query(catalog: dict) -> bytes:
    query = catalog['query']
    lines = [f"<{query['root']}>"]
    lines.extend(f"\t<{p['key']}>{escape(p['value'], quote=False)}</{p['key']}>" for p in query['header'])
    lines.extend(['', '\t<fields>'])
    for field in query['fields']:
        lines.append(f"\t\t<{field['name']}>")
        lines.extend(f"\t\t\t<{p['key']}>{escape(p['value'], quote=False)}</{p['key']}>" for p in field['properties'])
        lines.append(f"\t\t</{field['name']}>")
    lines.extend(['\t</fields>', f"</{query['root']}>", ''])
    return '\n'.join(lines).encode('utf-8')


def artifact(root: Path, path: str, digest: str) -> bytes:
    source = root / path
    if not source.resolve().is_relative_to(root.resolve()):
        raise ValueError(f'Artifact escapes repository: {path}')
    raw = source.read_bytes()
    if hashlib.sha256(raw).hexdigest() != digest:
        raise ValueError(f'Artifact fingerprint changed: {path}')
    return raw


def parse_xml(raw: bytes):
    if re.search(rb'<!\s*(?:DOCTYPE|ENTITY)\b', raw, re.I):
        raise ValueError('Catalog artifacts must not contain DTD/entity declarations')
    return ET.fromstring(raw, forbid_dtd=True, forbid_entities=True, forbid_external=True)


def package_files(raw: bytes) -> dict[str, bytes]:
    with gzip.GzipFile(fileobj=io.BytesIO(raw)) as source:
        xml = source.read(8 * 1024 * 1024 + 1)
    if len(xml) > 8 * 1024 * 1024:
        raise ValueError('Template package exceeds the build-time bound')
    files = {}
    for node in parse_xml(xml).findall('./files/file'):
        name = node.findtext('name')
        if not name or name in files:
            raise ValueError('Missing or duplicate packaged file name')
        files[name] = base64.b64decode(node.findtext('data', ''), validate=True)
    return files


def validate(catalog: dict, root: Path = ROOT) -> dict[str, int]:
    schema_validate(catalog, load_json(root / SCHEMA))
    query = catalog['query']
    header = properties(query['header'])
    for key, value in header.items():
        if key in ['oid_index', 'oid_num_indexes']:
            validate_oid(value)
    names = [field['name'] for field in query['fields']]
    if len(names) != len(set(names)):
        raise ValueError('Duplicate query field')
    fields = {field['name']: properties(field['properties']) for field in query['fields']}
    if any(name not in fields for name in header['index_order'].split(':')):
        raise ValueError('Index order references an absent query field')
    for field in fields.values():
        if not {'name', 'method', 'source', 'direction', 'oid'}.issubset(field):
            raise ValueError('Incomplete native query field')
        if field['direction'] not in ['input', 'output', 'input-output']:
            raise ValueError('Unsupported query direction')
        validate_oid(field['oid'])
    expected = artifact(root, query['path'], query['sha256'])
    if render_query(catalog) != expected:
        raise ValueError('Rendered query differs from the shipped query')
    package = catalog['package']
    files = package_files(artifact(root, package['path'], package['sha256']))
    embedded = files[package['embedded_query_path']]
    if hashlib.sha256(embedded).hexdigest() != package['embedded_query_sha256']:
        raise ValueError('Package-embedded query fingerprint changed')
    document = parse_xml(files[package['template_file']])
    if document.tag != 'cacti':
        raise ValueError('Expected a native Cacti template document')
    objects = {node.tag: node for node in document}
    nodes = {node.tag for node in document.iter() if HASH.fullmatch(node.tag)}
    if len(catalog['identities']) != 3 or {value[5:7] for value in catalog['identities']} != {'00', '01', '04'}:
        raise ValueError('Expected explicit existing graph, data and query identities')
    dependencies = set()
    for identity in catalog['identities']:
        if identity not in objects:
            raise ValueError(f'Unknown native template identity: {identity}')
        for node in objects[identity].iter():
            for reference in HASH.findall(node.text or ''):
                # Legacy graph-input item lists encode graph-item references with
                # type00; the importer resolves these through graph_templates_item.
                graph_item_alias = node.tag == 'items' and reference.startswith('hash_00') and 'hash_10' + reference[7:] in nodes
                if reference not in nodes and not graph_item_alias:
                    raise ValueError(f'Dangling package reference: {reference}')
                dependencies.add((identity, reference))
    declared = {(item['from'], item['to']) for item in catalog['dependencies']}
    if dependencies != declared:
        raise ValueError('Declared dependencies differ from existing native templates')
    query_object = next(objects[identity] for identity in catalog['identities'] if identity.startswith('hash_04'))
    if query_object.findtext('xml_path') != '<path_cacti>/' + query['path']:
        raise ValueError('Native query identity points to a different resource')
    graph_identity = next(identity for identity in catalog['identities'] if identity.startswith('hash_00'))
    graph_items = {node.findtext('task_item_id') for node in objects[graph_identity].findall('./items/*')}
    if not {metric['rrd_item'] for metric in catalog['metrics']}.issubset(graph_items):
        raise ValueError('Selected graph does not reference both declared metric RRD items')
    associations = [node for node in query_object.findall('./graphs/*') if node.findtext('graph_template_id') == graph_identity]
    if {metric['name'] for metric in catalog['metrics']} != {'traffic_in', 'traffic_out'}:
        raise ValueError('Missing or duplicate traffic metric')
    for metric in catalog['metrics']:
        if metric['data_template'] not in catalog['identities']:
            raise ValueError('Metric data template is not an explicit existing identity')
        item = objects[metric['data_template']].find('./items/' + metric['rrd_item'])
        if item is None:
            raise ValueError('Metric references an absent native RRD item')
        expected = {'data_source_name': metric['name'], 'data_source_type_id': '2',
                    'rrd_minimum': metric['minimum'], 'rrd_maximum': metric['maximum'], 'rrd_heartbeat': str(metric['heartbeat'])}
        if any(item.findtext(key) != value for key, value in expected.items()):
            raise ValueError('Catalog changes existing RRD semantics')
        if {binding['counter_width'] for binding in metric['query_fields']} != {32, 64}:
            raise ValueError('Expected both 32-bit and 64-bit native bindings')
        for binding in metric['query_fields']:
            field, width = binding['name'], binding['counter_width']
            if field not in fields or fields[field]['direction'] != 'output':
                raise ValueError('Metric references an absent output field')
            prefix = '.1.3.6.1.2.1.31.1.1.1.' if width == 64 else '.1.3.6.1.2.1.2.2.1.'
            if not fields[field]['oid'].startswith(prefix):
                raise ValueError('Counter width differs from the IF-MIB table')
            if not any(relation.findtext('snmp_field_name') == field and relation.findtext('data_template_rrd_id') == metric['rrd_item'] and relation.findtext('data_template_id') == metric['data_template'] for association in associations for relation in association.findall('./rrd/*')):
                raise ValueError('Native query does not bind this metric field')
    return {'fields': len(fields), 'identities': len(catalog['identities']), 'dependencies': len(dependencies), 'metrics': len(catalog['metrics'])}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--render', action='store_true', help='Write validated query XML to stdout; never rewrite a shipped artifact')
    args = parser.parse_args()
    try:
        catalog = load_json(ROOT / CATALOG)
        result = validate(catalog)
        if args.render:
            sys.stdout.buffer.write(render_query(catalog))
        else:
            print(json.dumps(result, sort_keys=True))
    except (ValueError, OSError, ET.ParseError, KeyError, ValidationError, SchemaError) as error:
        parser.exit(1, f'Catalog validation failed: {error}\n')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
