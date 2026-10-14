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


def inline_script_lines(source):
    """Select static script lines outside PHP; never credit PHP via Chromium."""
    result = set()
    in_php = False
    in_script = False
    for number, line in enumerate(source.splitlines(), 1):
        static_script = in_script and not in_php and '<?' not in line and '?>' not in line
        for token in re.finditer(r'<\?(?:php|=)?|\?>|<script\b|</script\s*>', line, re.I):
            value = token.group().lower()
            if value.startswith('<?'):
                in_php = True
            elif value == '?>':
                in_php = False
            elif not in_php:
                in_script = value.startswith('<script')
        if static_script and '</script' not in line.lower():
            result.add(number)
    return result


def inline_browser_coverage(name, lines, added, browser):
    executable = set()
    covered = set()
    instrumented = False
    in_comment = False
    for number, line in enumerate(lines, 1):
        stripped = line.strip()
        if stripped.startswith('/*'):
            in_comment = True
        comment = in_comment
        if '*/' in stripped:
            in_comment = False
        if number in added and stripped and not comment and not stripped.startswith('//') and stripped not in ['}', '});', '];', '};']:
            executable.add(number)
    for session in browser:
        for entry in session.get('js', []):
            if not entry.get('url', '').endswith('/' + name):
                continue
            content = entry.get('text', entry.get('source', ''))
            content_lines = content.splitlines(keepends=True)
            offsets = [0]
            for line in content_lines:
                offsets.append(offsets[-1] + len(line))
            ranges = [r for function in entry['functions'] for r in function['ranges']]
            for number in executable:
                if number > len(content_lines) or content_lines[number - 1].strip() != lines[number - 1].strip():
                    continue
                instrumented = True
                line = content_lines[number - 1]
                offset = offsets[number - 1] + len(line) - len(line.lstrip())
                enclosing = [r for r in ranges if r['startOffset'] <= offset < r['endOffset']]
                if enclosing and min(enclosing, key=lambda r: r['endOffset'] - r['startOffset'])['count'] > 0:
                    covered.add(number)
    return executable, covered, instrumented


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
            script = inline_script_lines(source) & added
            script_executable, script_covered, script_instrumented = inline_browser_coverage(name, lines, script, browser)
            executable |= script_executable
            covered |= script_covered
            instrumented = instrumented or script_instrumented
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
