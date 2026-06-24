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

/*
 * Smoke tests for the boost archive table argument fix.
 *
 * These tests verify the most basic invariants required for the fix to work:
 * PHP syntax is valid, the generated table name format satisfies the
 * validation regex, the SHOW TABLES LIKE pattern covers that format, and
 * the child process receives the argument it needs.
 *
 * Smoke tests are intentionally shallow. Deeper behaviour is covered in
 * BoostProcessBugsTest and BoostArchiveTableIntegrationTest.
 */

$boostPollerPath = __DIR__ . '/../../poller_boost.php';
$boostLibPath    = __DIR__ . '/../../lib/boost.php';

test('poller_boost.php passes PHP syntax check', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);
	expect($contents)->not->toBeFalse("Cannot read $boostPollerPath");

	try {
		$tokens = token_get_all($contents, TOKEN_PARSE);
		expect($tokens)->not->toBeEmpty();
	} catch (\ParseError $e) {
		expect(false)->toBeTrue("Parse error in poller_boost.php: " . $e->getMessage());
	}
});

test('lib/boost.php passes PHP syntax check', function () use ($boostLibPath) {
	$contents = file_get_contents($boostLibPath);
	expect($contents)->not->toBeFalse("Cannot read $boostLibPath");

	try {
		$tokens = token_get_all($contents, TOKEN_PARSE);
		expect($tokens)->not->toBeEmpty();
	} catch (\ParseError $e) {
		expect(false)->toBeTrue("Parse error in lib/boost.php: " . $e->getMessage());
	}
});

test('generated archive table name passes child validation regex', function () {
	// The parent sets: $archive_table = 'poller_output_boost_arch_' . time()
	// The child validates: preg_match('/^poller_output_boost_arch_\d+$/', $value)
	// Verify a realistic generated name passes.
	$pattern = '/^poller_output_boost_arch_\d+$/';

	$generated = 'poller_output_boost_arch_' . time();
	expect(preg_match($pattern, $generated))->toBe(1, "Generated name '$generated' must pass child validation");
});

test('SHOW TABLES LIKE pattern covers the generated archive table name format', function () {
	// The SHOW TABLES LIKE pattern in boost_get_arch_table_names is:
	// 'poller_output_boost_arch%'
	// MySQL LIKE: % matches any sequence, no special meaning for _
	// A generated name 'poller_output_boost_arch_1746000000' must match.
	$pattern    = '/^poller_output_boost_arch/';  // PHP equivalent of LIKE 'poller_output_boost_arch%'
	$generated  = 'poller_output_boost_arch_' . time();

	expect(preg_match($pattern, $generated))->toBe(1, "Generated name '$generated' must match LIKE prefix");
});

test('child validation regex rejects injection attempts', function () {
	$pattern = '/^poller_output_boost_arch_\d+$/';

	$bad = [
		'',
		'; DROP TABLE users',
		'poller_output_boost_arch_',
		'poller_output_boost_arch_abc',
		'poller_output_boost_arch_123; DROP TABLE users',
		'poller_output_boost_arch_1 --extra',
		"poller_output_boost_arch_1\n--extra",
		'../poller_output_boost_arch_1746000000',
	];

	foreach ($bad as $value) {
		expect(preg_match($pattern, $value))->toBe(0, "Pattern must reject '$value'");
	}
});

test('poller_boost.php passes --archive-table to exec_background via $child_args', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// The case label handles the incoming argument on the child side.
	expect($contents)->toContain("case '--archive-table':");

	// The parent builds $child_args with --archive-table= and passes the array
	// to exec_background so each element is individually shell-escaped.
	expect($contents)->toContain("'--archive-table=' . \$archive_table");
	expect($contents)->toContain('exec_background($php_binary, $child_args, $redirect_args)');
});

test('poller_boost.php child arg block handles --archive-table', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// Both the case label and the assignment must be present.
	expect($contents)->toContain("case '--archive-table':");
	expect($contents)->toContain('$archive_table = $value;');
});

test('boost_launch_children has $archive_table in its global declaration', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	$start = strpos($contents, 'function boost_launch_children()');
	expect($start)->not->toBeFalse('boost_launch_children function not found');

	$globalStart = strpos($contents, 'global ', $start);
	$lineEnd     = strpos($contents, ";\n", $globalStart);
	$globalLine  = substr($contents, $globalStart, $lineEnd - $globalStart);

	expect($globalLine)->toContain('$archive_table');
});
