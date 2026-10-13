#!/usr/bin/env python3
"""Gate committed executable added lines using Xdebug and Chromium evidence.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
Run in the PR checkout, with --base set to the PR base commit. This reports
changed-line coverage, not whole-project, branch or path coverage.
"""
import argparse
import json
from pathlib import Path
import re
import subprocess


def added_lines(base):
    base = subprocess.check_output(['git', 'merge-base', base, 'HEAD'], text=True).strip()
    diff = subprocess.check_output(
        ['git', 'diff', '--no-ext-diff', '--no-textconv', '--unified=0', base, 'HEAD', '--', '*.php', '*.js', '*.css',
         ':(exclude)tests/**', ':(exclude)include/vendor/**'], text=True)
    files = {}
    name = None
    line = 0
    for record in diff.splitlines():
        if record.startswith('+++ b/'):
            name = record[6:]
            files[name] = set()
        elif record.startswith('@@'):
            line = int(re.search(r'\+(\d+)', record).group(1))
        elif record.startswith('+'):
            files[name].add(line)
            line += 1
        elif not record.startswith(('-', '\\')):
            line += 1
    return {name: lines for name, lines in files.items() if lines}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base', required=True)
    parser.add_argument('--php-coverage', nargs='*', default=[])
    parser.add_argument('--browser-root', type=Path)
    args = parser.parse_args()
    root = Path.cwd().resolve()
    changed = added_lines(args.base)
    php = {}
    browser = []
    for name in args.php_coverage:
        for file, data in json.loads(Path(name).read_text()).items():
            lines = data.get('lines', data)
            target = php.setdefault(str(Path(file).resolve()), {})
            for number, hits in lines.items():
                target[int(number)] = max(hits, target.get(int(number), -2))
    if args.browser_root:
        for file in args.browser_root.rglob('changed-coverage.json'):
            browser.append(json.loads(file.read_text()))
    report = {}
    for name, added in changed.items():
        file = root / name
        source = file.read_text()
        lines = source.splitlines(keepends=True)
        covered = set()
        executable = set()
        instrumented = False
        if file.suffix == '.php':
            evidence = php.get(str(file.resolve()), {})
            instrumented = bool(evidence)
            executable = {n for n in added if n in evidence and evidence[n] != -2}
            # Source slices may instrument only one function in an entry point.
            # Missing statements elsewhere in that same file must fail closed.
            for number in added:
                if number not in evidence and re.match(r'^\s*(?:\$|return\b|throw\b|if\b|elseif\b|foreach\b|while\b|for\b|[A-Za-z_\\][\w\\]*\()', lines[number - 1]):
                    executable.add(number)
            covered = {n for n in executable if evidence.get(n, -1) > 0}
        else:
            offsets = []
            offset = 0
            in_comment = False
            for number, text in enumerate(lines, 1):
                stripped = text.strip()
                if stripped.startswith('/*'):
                    in_comment = True
                comment = in_comment
                if '*/' in stripped:
                    in_comment = False
                if number in added and stripped and not comment and not stripped.startswith('//') and stripped not in ['}', '});', '];', '};']:
                    executable.add(number)
                    offsets.append((number, offset + len(text) - len(text.lstrip())))
                offset += len(text)
            for session in browser:
                entries = session['js' if file.suffix == '.js' else 'css']
                for entry in entries:
                    content = entry.get('text', entry.get('source', ''))
                    if file.suffix == '.js':
                        if not entry.get('url', '').endswith('/' + name):
                            continue
                        ranges = [r for fn in entry['functions'] for r in fn['ranges']]
                    else:
                        if not content.startswith(source):
                            continue
                        ranges = [dict(startOffset=r['start'], endOffset=r['end'], count=1) for r in entry['ranges']]
                    instrumented = True
                    content_lines = content.splitlines(keepends=True)
                    content_offsets = [0]
                    for text in content_lines:
                        content_offsets.append(content_offsets[-1] + len(text))
                    for number, original_offset in offsets:
                        if number > len(content_lines):
                            continue
                        text = content_lines[number - 1]
                        if file.suffix == '.js' and text.strip() != lines[number - 1].strip():
                            continue
                        offset = content_offsets[number - 1] + len(text) - len(text.lstrip())
                        enclosing = [r for r in ranges if r['startOffset'] <= offset < r['endOffset']]
                        if enclosing and min(enclosing, key=lambda r: r['endOffset'] - r['startOffset'])['count'] > 0:
                            covered.add(number)
        report[name] = {
            'instrumented': instrumented,
            'covered': len(covered), 'executable': len(executable),
            'uncovered': sorted(executable - covered),
        }
    print(json.dumps(report, indent=2))
    return 0 if report and all(row['instrumented'] and not row['uncovered'] for row in report.values()) else 1


if __name__ == '__main__':
    raise SystemExit(main())
