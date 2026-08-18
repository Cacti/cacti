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

// End-to-end checks for the SQL interpolation ratchet: run the actual CLI
// against the real tree the way CI does, then confirm the committed baseline
// still matches that tree.

function audit_run(string $args): array {
	$root = dirname(__DIR__, 2);
	$cmd = escapeshellarg(PHP_BINARY) . ' '
		. escapeshellarg($root . '/tests/tools/sql_interpolation_audit.php')
		. ' ' . $args . ' 2>&1';

	$output = array();
	$code = 0;
	exec('cd ' . escapeshellarg($root) . ' && ' . $cmd, $output, $code);

	return array('code' => $code, 'out' => implode("\n", $output));
}

test('ratchet passes on the current tree', function () {
	$result = audit_run('--check');
	expect($result['code'])->toBe(0)
		->and($result['out'])->toContain('ratchet passed');
});

test('json output is well-formed and totals every category', function () {
	$result = audit_run('--json');
	expect($result['code'])->toBe(0);

	$data = json_decode($result['out'], true);
	expect($data)->toBeArray()
		->and($data)->toHaveKey('totals')
		->and($data)->toHaveKey('files')
		->and($data['totals'])->toHaveKeys(
			array('prepared', 'static', 'qstr', 'fragment', 'opaque', 'identifier', 'value')
		);
});

test('committed baseline matches the current tree', function () {
	$root = dirname(__DIR__, 2);
	$baseline = json_decode(file_get_contents(CACTI_PATH_TESTS . '/tools/sql_interpolation_baseline.json'), true);
	expect($baseline)->toBeArray();

	$data = json_decode(audit_run('--json')['out'], true);

	$current = array();
	foreach ($data['files'] as $path => $counts) {
		if (($counts['value'] ?? 0) > 0) {
			$current[$path] = $counts['value'];
		}
	}

	ksort($baseline);
	ksort($current);

	// Drift here means someone changed SQL call sites without refreshing the
	// baseline: php tests/tools/sql_interpolation_audit.php --write-baseline
	expect($current)->toBe($baseline);
});

test('every baseline path still exists', function () {
	$root = dirname(__DIR__, 2);
	$baseline = json_decode(file_get_contents(CACTI_PATH_TESTS . '/tools/sql_interpolation_baseline.json'), true);

	foreach (array_keys($baseline) as $path) {
		expect(file_exists($root . '/' . $path))->toBeTrue("missing baseline path: $path");
	}
});
