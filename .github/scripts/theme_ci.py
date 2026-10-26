#!/usr/bin/env python3
"""Prepare pinned theme jobs and summarize their browser evidence.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import argparse
import json
import os
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
ARTIFACTS = ROOT / 'tests/e2e/theme-results'


def fixtures(root=ROOT):
    plugins = json.loads((root / 'tests/e2e/plugin-theme-revisions.json').read_text())
    rtm = json.loads((root / 'tests/e2e/rtm-theme-fixtures.json').read_text())
    if not isinstance(plugins, dict) or not plugins:
        raise ValueError('Plugin revision manifest must be a nonempty object')
    entries = []
    for plugin, revision in plugins.items():
        if not re.fullmatch(r'[a-z][a-z0-9_]*', plugin) or plugin == 'rtm':
            raise ValueError(f'Invalid or reserved plugin ID: {plugin}')
        entries.append({'id': plugin, 'repository': f'Cacti/plugin_{plugin}', 'revision': revision})
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_.-]*/[A-Za-z0-9][A-Za-z0-9_.-]*', rtm['repository']):
        raise ValueError('Invalid RTM repository')
    entries.append({'id': 'rtm', 'repository': rtm['repository'], 'revision': rtm['revision']})
    for entry in entries:
        if not isinstance(entry['revision'], str) or not re.fullmatch(r'[0-9a-f]{40}', entry['revision']):
            raise ValueError(f"Fixture {entry['id']} must use a full immutable commit SHA")
    return entries


def matrix(root=ROOT):
    entries = fixtures(root)
    return {
        'plugins': json.dumps({'include': entries[:-1]}, separators=(',', ':')),
        'rtm_repository': entries[-1]['repository'],
        'rtm_revision': entries[-1]['revision'],
    }


def summarize(directory, suite, fixture_id, test_outcome, coverage_outcome, root=ROOT):
    directory.mkdir(parents=True, exist_ok=True)
    metadata = {'suite': suite, 'core_revision': os.environ.get('GITHUB_SHA', 'local checkout')}
    if suite != 'core':
        selected = next((entry for entry in fixtures(root) if entry['id'] == fixture_id), None)
        if selected is None:
            raise ValueError(f'Unknown fixture: {fixture_id}')
        metadata['fixture'] = selected
    report_path = directory / 'results.json'
    report = json.loads(report_path.read_text()) if report_path.exists() else {}
    metadata['playwright_version'] = report.get('config', {}).get('version', 'not recorded')
    base_path = directory / 'coverage-base.txt'
    if base_path.exists():
        metadata['coverage_base_revision'] = base_path.read_text().strip()
    stats = report.get('stats', {})
    counts = {key: int(stats.get(key, 0)) for key in ('expected', 'unexpected', 'flaky', 'skipped')}
    executed = counts['expected'] + counts['unexpected'] + counts['flaky']
    successful = test_outcome == 'success' and executed > 0 and counts['unexpected'] == 0 and counts['skipped'] == 0 and not report.get('errors')
    requires_coverage = suite == 'core' and os.environ.get('GITHUB_EVENT_NAME') in ('pull_request', 'merge_group')
    if requires_coverage and coverage_outcome != 'success':
        successful = False
    if coverage_outcome not in ('success', 'skipped'):
        successful = False
    status = 'PASS' if successful else 'FAIL'
    lines = [f"## Theme validation: {fixture_id or suite}", '', f'**{status}**', '',
             '| Passed | Failed | Flaky | Skipped |', '| --- | --- | --- | --- |',
             f"| {counts['expected']} | {counts['unexpected']} | {counts['flaky']} | {counts['skipped']} |", '',
             f'Browser step: `{test_outcome}`. CSS coverage step: `{coverage_outcome}`.', '']
    if not report:
        lines += ['No Playwright JSON report was produced. Check setup and browser logs.', '']
    if executed == 0:
        lines += ['No tests executed; this job cannot establish compatibility.', '']
    if report.get('errors'):
        lines += ['Playwright reported a setup or collection error; inspect results.json.', '']
    coverage_path = directory / 'css-coverage.json'
    if coverage_outcome == 'success':
        if not coverage_path.exists():
            successful = False
            lines += ['CSS coverage evidence is missing.', '']
        else:
            coverage = json.loads(coverage_path.read_text())
            total = sum(row['executable'] for row in coverage.values())
            covered = sum(row['covered'] for row in coverage.values())
            complete = all(row['instrumented'] and not row['uncovered'] for row in coverage.values())
            if not complete or covered != total:
                successful = False
            lines += [f'Changed executable CSS lines: **{covered}/{total}**.' if total else
                      'No changed executable CSS lines in this PR.', '']
    elif suite == 'core' and coverage_outcome == 'skipped':
        lines += ['Changed CSS coverage did not run. PR and merge-queue core checks require it; pushes and manual runs have no comparison base.', '']
    if suite == 'rtm':
        lines += ['Scope: tables, console, filters, settings, and plugin inventory. '
                  'FusionCharts rendering is not covered because the public fixture omits the runtime assets.', '']
    lines += [f"Core revision: `{metadata['core_revision']}`.", '']
    if 'fixture' in metadata:
        entry = metadata['fixture']
        lines += [f"Fixture: `{entry['repository']}@{entry['revision']}`.", '']
    images = sum(1 for _ in (directory / 'test-results').rglob('*.png'))
    traces = sum(1 for _ in (directory / 'test-results').rglob('trace.zip'))
    lines += [f'Evidence: {images} screenshots and {traces} failure traces, HTML/JSON/JUnit reports, '
              'and available contrast/CSS coverage JSON.', '',
              'Fixtures contain synthetic monitoring data. Browser component checks do not exercise plugin backends or pollers.', '']
    # Coverage evidence can invalidate an otherwise successful browser run.
    lines[2] = '**PASS**' if successful else '**FAIL**'
    text = '\n'.join(lines)
    (directory / 'summary.md').write_text(text)
    (directory / 'fixture.json').write_text(json.dumps(metadata, indent=2) + '\n')
    return text, successful


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest='command', required=True)
    sub.add_parser('matrix')
    summary = sub.add_parser('summary')
    summary.add_argument('--suite', choices=('core', 'plugin', 'rtm'), required=True)
    summary.add_argument('--fixture', default='')
    summary.add_argument('--test-outcome', required=True)
    summary.add_argument('--coverage-outcome', default='skipped')
    args = parser.parse_args()
    if args.command == 'matrix':
        values = matrix()
        output = '\n'.join(f'{key}={value}' for key, value in values.items()) + '\n'
        if os.environ.get('GITHUB_OUTPUT'):
            with Path(os.environ['GITHUB_OUTPUT']).open('a') as handle:
                handle.write(output)
        else:
            print(output, end='')
        return 0
    text, success = summarize(ARTIFACTS, args.suite, args.fixture, args.test_outcome, args.coverage_outcome)
    if os.environ.get('GITHUB_STEP_SUMMARY'):
        with Path(os.environ['GITHUB_STEP_SUMMARY']).open('a') as handle:
            handle.write(text)
    print(text)
    return 0 if success else 1


if __name__ == '__main__':
    raise SystemExit(main())
