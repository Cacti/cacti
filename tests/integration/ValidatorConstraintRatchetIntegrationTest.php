<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// End-to-end checks for the validator constraint ratchet: run the actual CLI
// against the real tree the way CI does, then confirm the committed baseline
// still matches that tree.

function validator_audit_run(string $args): array {
	$root = dirname(__DIR__, 2);
	$cmd  = escapeshellarg(PHP_BINARY) . ' '
		. escapeshellarg($root . '/tests/tools/validator_constraint_audit.php')
		. ' ' . $args . ' 2>&1';

	$output = [];
	$code   = 0;
	exec('cd ' . escapeshellarg($root) . ' && ' . $cmd, $output, $code);

	return ['code' => $code, 'out' => implode("\n", $output)];
}

test('ratchet passes on the current tree', function () {
	$result = validator_audit_run('--check');
	expect($result['code'])->toBe(0)
		->and($result['out'])->toContain('ratchet passed');
});

test('json output is well-formed and totals every category', function () {
	$result = validator_audit_run('--json');
	expect($result['code'])->toBe(0);

	$data = json_decode($result['out'], true);
	expect($data)->toBeArray()
		->and($data)->toHaveKey('totals')
		->and($data)->toHaveKey('files')
		->and($data['totals'])->toHaveKeys(['constrained', 'documented-empty', 'empty', 'dynamic']);
});

test('committed baseline matches the current tree', function () {
	$root     = dirname(__DIR__, 2);
	$baseline = json_decode(file_get_contents($root . '/tests/tools/validator_constraint_baseline.json'), true);
	expect($baseline)->toBeArray();

	$data = json_decode(validator_audit_run('--json')['out'], true);

	$current = [];

	foreach ($data['files'] as $path => $counts) {
		if (($counts['empty'] ?? 0) > 0) {
			$current[$path] = $counts['empty'];
		}
	}

	ksort($baseline);
	ksort($current);

	// Drift here means someone changed validator call sites without
	// refreshing the baseline: php tests/tools/validator_constraint_audit.php --write-baseline
	expect($current)->toBe($baseline);
});

test('every baseline path still exists', function () {
	$root     = dirname(__DIR__, 2);
	$baseline = json_decode(file_get_contents($root . '/tests/tools/validator_constraint_baseline.json'), true);

	foreach (array_keys($baseline) as $path) {
		expect(file_exists($root . '/' . $path))->toBeTrue("missing baseline path: $path");
	}
});
