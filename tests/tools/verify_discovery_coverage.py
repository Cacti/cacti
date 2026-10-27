#!/usr/bin/env python3
"""Require discovery assertions and source-bound native/worker coverage evidence.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import argparse
import hashlib
import json
from pathlib import Path
import sys
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
NATIVE = (
    'discovery_address_probe.php', 'discovery_network_probe.php',
    'discovery_launch_probe.php', 'discovery_worker_address_probe.php',
    'automation_matching_probe.php', 'discovery_address_database_probe.php',
    'discovery_worker_ping_coverage.php',
)
DEPENDENCIES = (
    'tests/Helpers/RequestCoverage.php', 'tests/Helpers/SourceSlice.php',
    'tests/Helpers/PhpSource.php', 'tests/Helpers/IsolatedProbe.php', 'tests/fixtures/automation_matching_cases.php',
)
SOURCES = ('lib/api_automation.php', 'poller_automation.php', 'automation_graph_rules.php')

SCENARIOS = (
    '.github/workflows/pr-php-coverage.yml',
    'tests/tools/run_discovery_request_coverage.sh', 'tests/tools/verify_discovery_coverage.py',
    'tests/e2e/discovery/run.sh', 'tests/e2e/discovery/discovery.spec.ts',
    'tests/e2e/discovery/probe.php', 'tests/e2e/discovery/playwright.config.ts',
    'tests/e2e/discovery/compose.yml', 'tests/e2e/discovery/coverage.yml',
    'tests/e2e/discovery/coverage.ini', 'tests/e2e/Dockerfile',
    'tests/e2e/docker-compose.yml', 'tests/e2e/entrypoint.sh',
    'tests/e2e/playwright.config.ts', 'tests/e2e/package.json', 'tests/e2e/package-lock.json',
    'tests/fixtures/snmp-coverage/Dockerfile',
    *(f'tests/e2e/discovery/{agent}.conf' for agent in
      ('known', 'partial', 'unknown', 'excluded', 'interior255')),
    *(f'tests/Unit/Core/Automation/{test}.php' for test in
      ('AutomationFindOsTest', 'DiscoveryAddressDatabaseTest', 'DiscoveryAddressTest',
       'DiscoveryNetworkTest', 'DiscoveryLaunchTest', 'DiscoveryWorkerAddressTest')),
    'tests/Coverage/DiscoveryWorkerPingCoverageTest.php',
    *DEPENDENCIES, *('tests/fixtures/' + name for name in NATIVE), *SOURCES,
)


def scenario_hashes(root):
    return {name: hashlib.sha256((root / name).read_bytes()).hexdigest() for name in SCENARIOS}


def prepare(directory, root=ROOT):
    path = directory / 'discovery-scenarios.txt'
    # Exclusive creation prevents silently resetting a running phase's fingerprints.
    with path.open('x') as stream:
        json.dump(scenario_hashes(root), stream)


def successful_cases(path):
    cases = list(ET.parse(path).iter('testcase'))
    if not cases or any(list(case.iter('failure')) or list(case.iter('error'))
                        or list(case.iter('skipped')) for case in cases):
        raise ValueError(f'{path.name} must contain executed, successful cases only')
    return cases


def verify(directory, native_only=False, root=ROOT):
    captured = json.loads((directory / 'discovery-scenarios.txt').read_text())
    if captured != scenario_hashes(root):
        raise ValueError('Discovery scenario sources changed or fingerprints are missing')
    cases = successful_cases(directory / 'discovery-native.xml')
    if len(cases) != 69:
        raise ValueError('All 69 native discovery cases are required')
    for marker in ('discovery enumerates admitted addresses', 'discovery rejects invalid',
                   'discovery worker probes admitted hosts', 'discovery child arguments', 'matches discovery rules', 'overlapping discovery ranges',
                   'discovery records reachable non-snmp', 'discovery records ping outages'):
        if not any(marker in case.get('name', '').lower() for case in cases):
            raise ValueError(f'Missing native discovery assertion: {marker}')
    expected = {name: hashlib.sha256((root / name).read_bytes()).hexdigest()
                for name in (*SOURCES, *DEPENDENCIES)}
    expected.update({'tests/fixtures/' + name:
                     hashlib.sha256((root / 'tests/fixtures' / name).read_bytes()).hexdigest()
                     for name in NATIVE})
    executed = set()
    hits = {name: set() for name in SOURCES}
    reports = list(directory.glob('*.json'))
    if not reports:
        raise ValueError('Missing discovery coverage reports')
    for path in reports:
        report = json.loads(path.read_text())
        if not isinstance(report, dict) or not isinstance(report.get('coverage'), dict) or not isinstance(report.get('hashes'), dict):
            raise ValueError('Malformed discovery coverage envelope')
        for filename, lines in report['coverage'].items():
            name = str(Path(filename).relative_to(report['root']))
            if name not in expected:
                continue
            if report['hashes'].get(filename) != expected[name]:
                raise ValueError(f'Stale discovery source or producer: {name}')
            positive = {int(line) for line, count in (lines or {}).items() if count > 0}
            if positive:
                executed.add(name)
            if name in hits:
                hits[name].update(positive)
    for name in NATIVE:
        if 'tests/fixtures/' + name not in executed:
            raise ValueError(f'Missing executed native producer: {name}')
    for name in DEPENDENCIES:
        if name not in executed:
            raise ValueError(f'Missing executed discovery dependency: {name}')
    if not native_only:
        browser = successful_cases(directory / 'discovery-browser.xml')
        if len(browser) != 3:
            raise ValueError('All three discovery browser scenarios are required')
        for marker in ('real UI config', 'UI cancellation', 'an ordinary account'):
            if not any(marker in case.get('name', '') for case in browser):
                raise ValueError(f'Missing browser assertion scenario: {marker}')
        # These statements are the two real worker launches and both criteria saves.
        # Locate their current source lines instead of embedding revision-specific offsets.
        requirements = {
            'poller_automation.php': 'automation_get_scan_arguments(',
            'automation_graph_rules.php': "get_hash_automation((int) $save['id']",
        }
        for name, marker in requirements.items():
            lines = [number for number, line in enumerate((root / name).read_text().splitlines(), 1)
                     if marker in line]
            if len(lines) != 2 or not set(lines).issubset(hits[name]):
                raise ValueError(f'Missing actual master/worker or criteria execution: {name}')
    junit = ['discovery-native.xml'] + ([] if native_only else ['discovery-browser.xml'])
    return {'scenario_hashes': captured,
            'junit_hashes': {name: hashlib.sha256((directory / name).read_bytes()).hexdigest() for name in junit},
            'native_cases': len(cases), 'executed_producers': sorted(executed),
            'production_hashes': {name: expected[name] for name in SOURCES},
            'producer_hashes': {name: digest for name, digest in expected.items() if name not in SOURCES},
            'covered_lines': {name: len(lines) for name, lines in hits.items()}}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('directory', type=Path)
    parser.add_argument('--native-only', action='store_true')
    parser.add_argument('--prepare', action='store_true')
    parser.add_argument('--proof-output', type=Path)
    args = parser.parse_args()
    if args.prepare:
        prepare(args.directory)
        return
    result = json.dumps(verify(args.directory, args.native_only), indent=2)
    if args.proof_output:
        args.proof_output.write_text(result + '\n')
    print(result)


if __name__ == '__main__':
    try:
        main()
    except (OSError, ValueError, TypeError, KeyError, ET.ParseError) as error:
        print(f'Discovery coverage could not be verified: {error}', file=sys.stderr)
        sys.exit(1)
