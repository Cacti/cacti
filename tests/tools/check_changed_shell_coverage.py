"""Require kcov evidence for every changed production shell script.

Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
"""
import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess
import xml.etree.ElementTree as ET


def added_lines(base):
    base = subprocess.check_output(['git', 'merge-base', base, 'HEAD'], text=True).strip()
    diff = subprocess.check_output([
        'git', 'diff', '--no-ext-diff', '--no-textconv', '--unified=0', base, 'HEAD', '--',
        '*.sh', ':(exclude)tests/**'], text=True)
    result = {}
    name = None
    number = 0
    for record in diff.splitlines():
        if record.startswith('diff --git '):
            name = None
        elif record.startswith('+++ b/'):
            name = record[6:]
            result.setdefault(name, set())
        elif record.startswith('@@'):
            number = int(re.search(r'\+(\d+)', record).group(1))
        elif record.startswith('+') and name is not None:
            result[name].add(number)
            number += 1
        elif not record.startswith(('-', '\\')):
            number += 1
    return {name: lines for name, lines in result.items() if lines}


def measure(changed, directory, root):
    evidence = {}
    for manifest in directory.glob('*/source.json'):
        source = json.loads(manifest.read_text())
        name = source['source']
        if name not in changed:
            continue
        if hashlib.sha256((root / name).read_bytes()).hexdigest() != source['sha256']:
            raise ValueError('Shell coverage source does not match ' + name)
        reports = list(manifest.parent.rglob('cov.xml'))
        if not reports:
            raise ValueError('Missing native shell coverage report for ' + name)
        for report in reports:
            for entry in ET.parse(report).iter('class'):
                filename = entry.attrib['filename']
                if filename not in (name, str(root / name), source['native_source']):
                    continue
                statements = evidence.setdefault(name, {})
                for line in entry.findall('lines/line'):
                    number = int(line.attrib['number'])
                    statements[number] = max(statements.get(number, 0), int(line.attrib['hits']))
    files = {}
    for name, added in changed.items():
        if name not in evidence:
            files[name] = {'missing': True}
            continue
        statements = evidence[name]
        executable = added & statements.keys()
        covered = {number for number in executable if statements[number] > 0}
        files[name] = {'covered': len(covered), 'executable': len(executable),
                       'uncovered': sorted(executable - covered)}
    covered = sum(item.get('covered', 0) for item in files.values())
    total = sum(item.get('executable', 0) for item in files.values())
    return {'covered': covered, 'executable': total,
            'percent': covered / total * 100 if total else None, 'files': files}


def passes(result, minimum):
    return (not any(item.get('missing') for item in result['files'].values()) and
            (result['executable'] == 0 or result['covered'] * 100 >= minimum * result['executable']))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base', required=True)
    parser.add_argument('--reports', type=Path, required=True)
    parser.add_argument('--minimum', type=float, default=80)
    parser.add_argument('--json-output', type=Path, required=True)
    args = parser.parse_args()
    if not 0 <= args.minimum <= 100:
        parser.error('Minimum must be between 0 and 100')
    result = measure(added_lines(args.base), args.reports, Path.cwd())
    output = json.dumps(result, indent=2)
    args.json_output.write_text(output + '\n')
    print(output)
    return 0 if passes(result, args.minimum) else 1


if __name__ == '__main__':
    raise SystemExit(main())
