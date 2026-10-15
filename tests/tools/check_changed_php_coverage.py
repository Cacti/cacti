#!/usr/bin/env python3
"""Require measured statement coverage for PHP lines added by a pull request.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import xml.etree.ElementTree as ET


def added_lines(base, root):
    merge_base = subprocess.check_output(
        ['git', 'merge-base', base, 'HEAD'], cwd=root, text=True).strip()
    diff = subprocess.check_output(
        ['git', '-c', 'core.quotePath=false', 'diff', '--no-ext-diff',
         '--no-textconv', '--unified=0', merge_base, 'HEAD', '--', '*.php',
         ':(exclude)tests/**', ':(exclude)include/vendor/**'], cwd=root, text=True)
    changed = {}
    name = None
    line = 0
    for record in diff.splitlines():
        if record.startswith('diff --git '):
            name = None
        elif record.startswith('+++ b/'):
            name = record[6:]
            changed.setdefault(name, set())
        elif record.startswith('@@'):
            line = int(re.search(r'\+(\d+)', record).group(1))
        elif record.startswith('+') and name is not None:
            changed[name].add(line)
            line += 1
        elif not record.startswith(('-', '\\')):
            line += 1
    return {name: lines for name, lines in changed.items() if lines}


def prepare_config(config, output, changed):
    """Keep the test suite, but include every changed production PHP file."""
    tree = ET.parse(config)
    config_dir = Path(config).resolve().parent
    output_dir = Path(output).resolve().parent
    for element in tree.iter():
        for attribute in ('bootstrap', 'cacheDirectory', 'cacheResultFile',
                          'outputDirectory', 'outputFile', 'target'):
            value = element.get(attribute)
            if value and not Path(value).is_absolute():
                element.set(attribute, os.path.relpath(config_dir / value, output_dir))
        if element.tag in ('file', 'directory', 'exclude') and element.text:
            value = element.text
            if not Path(value).is_absolute():
                element.text = os.path.relpath(config_dir / value, output_dir)
    source = tree.getroot().find('source')
    if source is None:
        source = tree.getroot().find('coverage')
    if source is None:
        source = ET.SubElement(tree.getroot(), 'coverage')
    source.clear()
    include = ET.SubElement(source, 'include')
    for name in sorted(changed):
        ET.SubElement(include, 'file').text = os.path.relpath(Path.cwd() / name, output_dir)
    ET.indent(tree, space='    ')
    tree.write(output, encoding='utf-8', xml_declaration=True)


def measure(changed, report, root, request_reports=None):
    if not changed and request_reports is None:
        return {'covered': 0, 'executable': 0, 'percent': None, 'files': {}}
    evidence = {}
    for file in ET.parse(report).iter('file'):
        try:
            name = str(Path(file.attrib['name']).resolve().relative_to(root.resolve()))
        except ValueError:
            continue
        statements = evidence.setdefault(name, {})
        for item in file.findall('line'):
            if item.attrib.get('type') == 'stmt':
                number = int(item.attrib['num'])
                statements[number] = max(statements.get(number, 0), int(item.attrib['count']))
    if request_reports:
        reports = list(request_reports.glob('*.json'))
        if not reports:
            raise ValueError('Request coverage directory contains no reports')
        for path in reports:
            request = json.loads(path.read_text())
            for file, lines in (request['coverage'] or {}).items():
                name = str(Path(file).relative_to(request['root']))
                if name not in changed:
                    continue
                if hashlib.sha256((root / name).read_bytes()).hexdigest() != request['hashes'].get(file):
                    raise ValueError(f'Request coverage source does not match {name}')
                statements = evidence.get(name, {})
                for number, hits in (lines or {}).items():
                    number = int(number)
                    if number in statements and hits > 0:
                        statements[number] = max(statements[number], hits)
                for start, end in request.get('html_spans', {}).get(file, []):
                    if (lines or {}).get(str(end), 0) > 0:
                        for number in changed[name] & statements.keys():
                            if start <= number <= end:
                                statements[number] = max(statements[number], 1)
    files = {}
    for name, added in changed.items():
        if name not in evidence:
            files[name] = {'missing': True}
            continue
        statements = evidence[name]
        executable = added & statements.keys()
        covered = {number for number in executable if statements[number] > 0}
        files[name] = {
            'covered': len(covered), 'executable': len(executable),
            'uncovered': sorted(executable - covered),
        }
    covered = sum(item.get('covered', 0) for item in files.values())
    total = sum(item.get('executable', 0) for item in files.values())
    return {
        'covered': covered, 'executable': total,
        'percent': covered / total * 100 if total else None, 'files': files,
    }


def passes(result, minimum):
    if any(item.get('missing') for item in result['files'].values()):
        return False
    return result['executable'] == 0 or result['covered'] * 100 >= minimum * result['executable']


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base', required=True)
    parser.add_argument('--prepare-config', type=Path)
    parser.add_argument('--output-config', type=Path)
    parser.add_argument('--report', type=Path)
    parser.add_argument('--request-reports', type=Path)
    parser.add_argument('--minimum', type=float, default=80)
    parser.add_argument('--json-output', type=Path)
    args = parser.parse_args()
    if not 0 <= args.minimum <= 100:
        parser.error('--minimum must be between 0 and 100')
    root = Path.cwd()
    changed = added_lines(args.base, root)
    if args.prepare_config:
        if not args.output_config:
            parser.error('--prepare-config requires --output-config')
        prepare_config(args.prepare_config, args.output_config, changed)
        print(f'Coverage scope includes {len(changed)} changed production PHP files.')
        return 0
    if not args.report:
        parser.error('--report is required when measuring coverage')
    result = measure(changed, args.report, root, args.request_reports)
    output = json.dumps(result, indent=2)
    print(output)
    if args.json_output:
        args.json_output.write_text(output + '\n')
    return 0 if passes(result, args.minimum) else 1


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (OSError, ValueError, KeyError, ET.ParseError, subprocess.CalledProcessError) as error:
        print(f'Changed PHP coverage could not be verified: {error}', file=sys.stderr)
        sys.exit(1)
