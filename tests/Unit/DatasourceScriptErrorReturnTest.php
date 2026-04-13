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

/**
 * Source-scan tests verifying that data source scripts return 'U' on error.
 *
 * Cacti project rule: data source scripts must return 'U' for unknown/error
 * samples, never '0'. Returning '0' causes RRDtool to graph a false zero data
 * point rather than an unknown sample, silently corrupting gauge metrics.
 */

test('ss_webseer.php does not initialise $value to 0', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_webseer.php');
	expect($src)->not->toContain("\$value = '0'")
		->and($src)->toContain("\$value = 'U'");
});

test('ss_webseer.php returns U not 0 on empty/missing value', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_webseer.php');
	expect($src)->not->toContain("? '0' : \$value")
		->and($src)->toContain("? 'U' : \$value")
		// empty('0') === true in PHP; guard must use strict string comparison
		->and($src)->not->toContain('empty($value)')
		->and($src)->toContain("\$value === ''");
});

test('ss_webseer.php does not return false on fallthrough', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_webseer.php');
	expect($src)->not->toContain('return false');
});

test('ss_gexport.php does not initialise $value to 0', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_gexport.php');
	expect($src)->not->toContain("\$value = '0'")
		->and($src)->toContain("\$value = 'U'");
});

test('ss_gexport.php returns U not 0 on empty value', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_gexport.php');
	expect($src)->not->toContain("? '0' : \$value")
		->and($src)->toContain("? 'U' : \$value");
});

test('ss_gexport.php does not return null on fallthrough', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_gexport.php');
	expect($src)->not->toContain('return null');
});

test('query_host_cpu.php prints U when get index is absent', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/query_host_cpu.php');
	// The get handler must have an else branch that prints 'U'; a comment
	// containing the literal would satisfy toContain but not the structural check.
	expect($src)->toContain("print 'U'")
		->and($src)->toContain('} else {')
		->and($src)->not->toContain("/* print 'U'");
});

test('ss_webseer.php guard covers false and null from db_fetch_cell_prepared', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_webseer.php');
	expect($src)->toContain('$value === false')
		->and($src)->toContain('$value === null');
});

test('ss_gexport.php guard covers false and null from db_fetch_cell_prepared', function () {
	$src = file_get_contents(__DIR__ . '/../../scripts/ss_gexport.php');
	expect($src)->toContain('$value === false')
		->and($src)->toContain('$value === null');
});

// Behavioural tests for the return guard expression used in both scripts:
//   ($value === '' || $value === false || $value === null ? 'U' : $value)
// Tested via an inline closure so no DB connection is required.

dataset('datasource_guard_cases', [
	'non-empty string passes through' => ['123', '123'],
	'empty string maps to U'          => ['', 'U'],
	'false maps to U'                 => [false, 'U'],
	'null maps to U'                  => [null, 'U'],
	'numeric 0 passes through'        => [0, 0],
]);

test('datasource return guard behaves correctly', function ($value, $expected) {
	$guard = static fn ($v) => ($v === '' || $v === false || $v === null ? 'U' : $v);
	expect($guard($value))->toBe($expected);
})->with('datasource_guard_cases');

test('datasource return guard does not throw on non-scalar input', function () {
	$guard = static fn ($v) => ($v === '' || $v === false || $v === null ? 'U' : $v);
	// Arrays pass strict === checks without error; result is the array itself.
	expect($guard([]))->toBe([]);
});
