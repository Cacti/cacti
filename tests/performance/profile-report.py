#!/usr/bin/env python3
"""Summarize Xdebug costs and compare matching diagnostic profiling runs."""
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
import argparse
import json
import math
import statistics
from pathlib import Path


def callgrind(path):
    names, costs, calls = {}, {}, {}
    current = target = None
    pending_calls = None
    events = []
    summary = None

    def symbol(value):
        if value.startswith('('):
            identity, _, name = value.partition(')')
            if name.strip():
                names[identity] = name.strip()
            if identity not in names:
                raise ValueError('Undefined Callgrind function identity')
            return names[identity]
        return value

    # Profiles can contain millions of records. Stream them instead of keeping
    # the full file and a second list of lines in memory.
    with path.open() as stream:
        for raw in stream:
            line = raw.rstrip('\n')
            if line.startswith('events:'):
                events = line.split()[1:]
            elif line.startswith('fn='):
                current = symbol(line[3:])
                target = pending_calls = None
            elif line.startswith('cfn='):
                target = symbol(line[4:])
            elif line.startswith('calls='):
                pending_calls = int(line[6:].split()[0])
            elif line.startswith('summary:'):
                summary = [int(value) for value in line.split()[1:]]
            elif line and (line[0].isdigit() or line[0] in '+-*'):
                values = line.split()
                if len(values) != len(events) + 1 or current is None:
                    continue
                elapsed = int(values[1 + events.index('Time_(10ns)')]) if 'Time_(10ns)' in events else None
                if elapsed is None:
                    raise ValueError('Expected Xdebug Time_(10ns) event units')
                entry = costs.setdefault(current, {'self_ns': 0, 'inclusive_ns': 0})
                if target is not None and pending_calls is not None:
                    entry['inclusive_ns'] += elapsed * 10
                    calls[target] = calls.get(target, 0) + pending_calls
                    target = pending_calls = None
                else:
                    entry['self_ns'] += elapsed * 10
                    entry['inclusive_ns'] += elapsed * 10
    if not costs or summary is None:
        raise ValueError('Incomplete or empty Callgrind profile')

    functions = [
        {'function': name, 'self_ms': value['self_ns'] / 1e6,
         'inclusive_ms': value['inclusive_ns'] / 1e6, 'calls': calls.get(name, 0)}
        for name, value in costs.items()
    ]
    sql = [row for row in functions if row['function'] in ('php::PDOStatement->execute', 'php::PDO->query', 'php::PDO->exec')]
    rrd = [row for row in functions if row['function'] in ('__rrd_init', '__rrd_execute', 'rrdtool_process_command', '__rrd_close')]
    boost = [row for row in functions if row['function'] in ('boost_graph_cache_check', 'boost_process_poller_output', 'boost_rrdtool_function_update')]
    return {
        'profile': path.name, 'scope': 'whole CLI process including bootstrap and fixture queue setup',
        'total_ms': summary[events.index('Time_(10ns)')] / 100000,
        'sql': {'calls': sum(row['calls'] for row in sql), 'self_ms': sum(row['self_ms'] for row in sql), 'functions': sql},
        'rrd_boundaries': rrd, 'boost_boundaries': boost,
        'top_self_costs': sorted(functions, key=lambda row: row['self_ms'], reverse=True)[:20],
        'note': 'Inclusive costs overlap. RRDtool boundary time includes subprocess waits and OS file-cache effects; it is not disk latency.',
    }


def describe(values):
    if not values or any(not math.isfinite(value) for value in values):
        raise ValueError('Missing or invalid timing samples')
    ordered = sorted(values)
    return {'median_ms': statistics.median(values), 'p95_ms': ordered[math.ceil(len(values) * .95) - 1], 'samples': len(values)}


def summarize(root):
    browser = json.loads((root / 'browser.json').read_text())
    if not browser['complete']:
        raise ValueError('Browser profiling did not finish all workloads')
    server = {scenario: json.loads((root / f'{scenario}.json').read_text()) for scenario in ('render', 'boost')}
    profiles = [callgrind(path) for path in sorted(root.glob('cachegrind.out.*'))]
    if len(profiles) != 2:
        raise ValueError('Expected both server Callgrind profiles')
    metrics = {}
    for row in browser['navigation']:
        metrics[f"navigation.{row['theme']}.elapsed"] = describe(row['elapsedMs'])
        metrics[f"navigation.{row['theme']}.applySkin"] = describe(row['applySkinMs'])
    for row in browser['realtime']:
        metrics[f"realtime.{row['graphs']}.{row['mode']}"] = describe([cycle['elapsedMs'] for cycle in row['cycles']])
    for row in browser['themes']:
        for sample in row['samples']:
            for field in ('layoutSeconds', 'styleSeconds', 'scriptSeconds', 'taskSeconds'):
                metrics[f"theme.{row['theme']}.{sample['view']}.{field}"] = describe([
                    (sample['after'][field] - sample['before'][field]) * 1000])
    for scenario, row in server.items():
        metrics[f'server.{scenario}.warm'] = describe([sample['elapsed_ms'] for sample in row['samples'][1:]])
    return {'schema': 1, 'diagnostic_only': True, 'environment': browser['environment'],
            'fixture': browser['fixture'], 'metadata': json.loads((root / 'metadata.json').read_text()),
            'metrics': metrics, 'server_profiles': profiles}


def compare(baseline, candidate):
    for field in ('schema', 'environment', 'fixture'):
        if baseline[field] != candidate[field]:
            raise ValueError(f'Mismatched {field}; compare runs on the same hardware, runtimes and fixture')
    if baseline['metadata']['machine'] != candidate['metadata']['machine']:
        raise ValueError('Different host; timings cannot be compared')
    if baseline['metrics'].keys() != candidate['metrics'].keys():
        raise ValueError('Different profiling scenarios')
    result = {}
    for name, value in candidate['metrics'].items():
        old = baseline['metrics'][name]
        if old['samples'] != value['samples']:
            raise ValueError(f'Mismatched sample count for {name}')
        result[name] = {'baseline_median_ms': old['median_ms'], 'candidate_median_ms': value['median_ms'],
                        'change_percent': (value['median_ms'] / old['median_ms'] - 1) * 100 if old['median_ms'] else None}
    return {'diagnostic_only': True, 'baseline_revision': baseline['metadata']['revision'],
            'candidate_revision': candidate['metadata']['revision'], 'metrics': result}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('directory', type=Path)
    parser.add_argument('--baseline', type=Path)
    args = parser.parse_args()
    summary = summarize(args.directory)
    (args.directory / 'summary.json').write_text(json.dumps(summary, indent=2) + '\n')
    if args.baseline:
        delta = compare(json.loads(args.baseline.read_text()), summary)
        (args.directory / 'comparison.json').write_text(json.dumps(delta, indent=2) + '\n')
    print('Wrote diagnostic profiling summary' + (' and comparison' if args.baseline else '') + '.')
