#!/usr/bin/env python3
"""Require measured coverage of Python statements added by a pull request.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import argparse
import json
from pathlib import Path
import re
import subprocess
import sys


def added_lines(base):
    base = subprocess.check_output(['git', 'merge-base', base, 'HEAD'], text=True).strip()
    diff = subprocess.check_output(
        ['git', '-c', 'core.quotePath=false', 'diff', '--no-ext-diff', '--no-textconv',
         '--unified=0', base, 'HEAD', '--', '*.py', ':(exclude)tests/**'], text=True)
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


def measure(changed, report):
    files = {}
    for name, added in changed.items():
        evidence = report['files'].get(name)
        if evidence is None:
            files[name] = {'missing': True}
            continue
        executed = set(evidence['executed_lines'])
        missing = set(evidence['missing_lines'])
        statements = added & (executed | missing)
        files[name] = {'covered': len(statements & executed), 'executable': len(statements),
                       'uncovered': sorted(statements & missing)}
    covered = sum(item.get('covered', 0) for item in files.values())
    total = sum(item.get('executable', 0) for item in files.values())
    return {'covered': covered, 'executable': total,
            'percent': covered / total * 100 if total else None, 'files': files}


def passes(result, minimum):
    return (not any(row.get('missing') for row in result['files'].values())
            and (not result['executable'] or result['covered'] * 100 >= minimum * result['executable']))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base', required=True)
    parser.add_argument('--report', type=Path, required=True)
    parser.add_argument('--minimum', type=float, default=80)
    parser.add_argument('--json-output', type=Path)
    args = parser.parse_args()
    if not 0 <= args.minimum <= 100:
        parser.error('--minimum must be between 0 and 100')
    result = measure(added_lines(args.base), json.loads(args.report.read_text()))
    output = json.dumps(result, indent=2)
    print(output)
    if args.json_output:
        args.json_output.write_text(output + '\n')
    return 0 if passes(result, args.minimum) else 1


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (OSError, ValueError, KeyError, subprocess.CalledProcessError) as error:
        print(f'Changed Python coverage could not be verified: {error}', file=sys.stderr)
        sys.exit(1)
