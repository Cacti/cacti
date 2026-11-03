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
        source = ET.SubElement(tree.getroot(), 'source')
    source.clear()
    include = ET.SubElement(source, 'include')
    for name in sorted(changed):
        ET.SubElement(include, 'file').text = os.path.relpath(Path.cwd() / name, output_dir)
    ET.indent(tree, space='    ')
    tree.write(output, encoding='utf-8', xml_declaration=True)


def validate_fixture_contract(request, path, root):
    """Reject stale or incomplete native library fixture evidence."""
    contract = request.get('contract') or {}
    if not path.name.startswith('library-behavior-') and contract.get('name') != 'library-behavior':
        return
    producers = {
        'tests/fixtures/Core/LibraryBehavior.php',
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
        'tests/e2e/library-behavior.sh',
        'tests/Helpers/RequestCoverage.php',
        'tests/Helpers/SourceSlice.php',
        'tests/Helpers/PhpSource.php',
        'cli/fetch_plugins.php',
        'tests/e2e/Dockerfile',
        'tests/e2e/entrypoint.sh',
        'tests/e2e/docker-compose.yml',
        'tests/e2e/ldap/50-e2e.ldif',
        'tests/fixtures/snmp-coverage/Dockerfile',
        'tests/fixtures/snmp-coverage/snmpd.conf',
        'cacti.sql',
        'composer.lock',
        'resource/snmp_queries/interface.xml',
        'install/templates/Local_Linux_Machine.xml.gz',
        'install/templates/NetSNMP_Device.xml.gz',
    }
    if (contract.get('name') != 'library-behavior' or contract.get('complete') is not True
            or type(contract.get('assertions')) is not int or contract['assertions'] < 5600):
        raise ValueError('Library behavior coverage requires its completed assertions')
    if set(contract.get('producers', [])) != producers or set(contract.get('hashes', {})) != producers:
        raise ValueError('Library behavior coverage is missing producer evidence')
    for name in producers:
        if hashlib.sha256((root / name).read_bytes()).hexdigest() != contract['hashes'][name]:
            raise ValueError(f'Library behavior producer does not match {name}')


def validate_package_contract(request, path, root):
    contract = request.get('contract') or {}
    if not path.name.startswith('package-archive-') and contract.get('name') != 'package-archive':
        return
    producers = {'tests/fixtures/Core/PackageArchive.php', 'tests/Helpers/RequestCoverage.php'}
    if (contract.get('name') != 'package-archive' or contract.get('complete') is not True
            or type(contract.get('assertions')) is not int or contract['assertions'] != 2):
        raise ValueError('Package archive coverage requires both completed scenarios')
    if set(contract.get('producers', [])) != producers or set(contract.get('hashes', {})) != producers:
        raise ValueError('Package archive coverage is missing producer evidence')
    for name in producers:
        if hashlib.sha256((root / name).read_bytes()).hexdigest() != contract['hashes'][name]:
            raise ValueError(f'Package archive producer does not match {name}')


def measure(changed, report, root, request_reports=None):
    # Deletion-only changes leave no source filter, so PHPUnit writes no report.
    if not changed and not Path(report).exists():
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
            if 'normalization' in request:
                normalization = request['normalization']
                if not isinstance(normalization, dict):
                    raise ValueError('Invalid request coverage normalization evidence')
                for key, name in [('normalizer_sha256', 'tests/tools/normalize_request_coverage.php'),
                                  ('composer_lock_sha256', 'composer.lock')]:
                    if normalization.get(key) != hashlib.sha256((root / name).read_bytes()).hexdigest():
                        raise ValueError(f'Request coverage normalizer does not match {name}')
                raw_hash = normalization.get('input_sha256')
                if not isinstance(raw_hash, str) or not re.fullmatch(r'[0-9a-f]{64}', raw_hash):
                    raise ValueError('Normalized coverage is missing its raw evidence hash')

            validate_fixture_contract(request, path, root)
            validate_package_contract(request, path, root)
            for file, lines in (request['coverage'] or {}).items():
                name = str(Path(file).relative_to(request['root']))
                if name not in changed:
                    continue
                if hashlib.sha256((root / name).read_bytes()).hexdigest() != request['hashes'].get(file):
                    raise ValueError(f'Request coverage source does not match {name}')
                statements = evidence.get(name, {})
                for number, hits in (lines or {}).items():
                    number = int(number)
                    # The application bootstrap runs before PHPUnit starts its driver.
                    # Its native request instructions must still enter the denominator.
                    if name == 'include/global.php' and number in changed[name] and hits != -2:
                        statements.setdefault(number, 0)
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
        if name == 'include/global.php':
            source_lines = (root / name).read_text().splitlines()
            for number in added:
                if re.match(r'^\s*(?:\$|(?:return|throw|if|elseif|foreach|while|for|print|echo|require|require_once|include|include_once)\b|[A-Za-z_\\][\w\\]*\()', source_lines[number - 1]):
                    statements.setdefault(number, 0)
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
