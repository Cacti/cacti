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
 * Tests for the IN-clause parameterisation in lib/api_data_source.php,
 * lib/api_device.php and lib/utility.php.
 *
 * Those files built delete/update predicates by imploding a caller-supplied
 * id list straight into "column IN (...)". The fix routes every such list
 * through array_to_sql_or(), which quotes each element with db_qstr() before
 * emitting "(column IN('a','b'))".
 *
 * Two things are checked:
 *   1. array_to_sql_or() quotes and escapes values, so an injected quote
 *      stays inside the string literal instead of breaking out of it.
 *   2. Each converted file now calls array_to_sql_or() and no longer carries
 *      the raw implode()/interpolated IN() constructs it replaced. The lib
 *      files run code at include time, so the call sites are asserted against
 *      the file source rather than executed.
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';

$libDir = dirname(__DIR__, 2) . '/lib';

// =====================================================================
// array_to_sql_or(): the shared quoting helper the fix relies on
// =====================================================================

test('array_to_sql_or builds a parenthesised IN() predicate', function () {
	expect(array_to_sql_or([1, 2], 'host_id'))->toBe("(host_id IN('1','2'))");
});

test('array_to_sql_or quotes and escapes a value carrying a single quote', function () {
	// The escaped quote keeps the payload inside the string literal.
	expect(array_to_sql_or(["a'b", 3], 'id'))->toMatch("/^\\(id IN\\('a(?:\\\\'|'')b','3'\\)\\)$/");
});

test('array_to_sql_or wraps every element in quotes, never bare', function () {
	$sql = array_to_sql_or([10, 20, 30], 'local_data_id');

	expect($sql)->toBe("(local_data_id IN('10','20','30'))");
	expect($sql)->not->toContain('IN(10');
});

test('array_to_sql_or keeps a column expression verbatim', function () {
	// api_device.php passes SUBSTRING_INDEX(command, ":", 1) as the column.
	expect(array_to_sql_or([5], 'SUBSTRING_INDEX(command, ":", 1)'))
		->toBe('(SUBSTRING_INDEX(command, ":", 1) IN(\'5\'))');
});

test('array_to_sql_or yields an empty string for an empty list', function () {
	expect(array_to_sql_or([], 'id'))->toBe('');
});

// =====================================================================
// lib/api_data_source.php source guards
// =====================================================================

test('api_data_source.php routes id lists through array_to_sql_or', function () use ($libDir) {
	$src = file_get_contents($libDir . '/api_data_source.php');

	expect($src)->toContain('array_to_sql_or($ids_to_delete, ');
	expect($src)->toContain('array_to_sql_or($dtd_ids_to_delete, ');
	expect($src)->toContain('array_to_sql_or($ids_to_disable, ');
});

test('api_data_source.php drops the raw imploded IN() interpolation', function () use ($libDir) {
	$src = file_get_contents($libDir . '/api_data_source.php');

	expect($src)->not->toContain("IN (' . \$ids_to_delete . ')");
	expect($src)->not->toContain("IN(' . \$ids_to_delete . ')");
	expect($src)->not->toContain("implode(',', \$dtd_ids_to_delete)");
	expect($src)->not->toContain('IN ($ids_to_disable)');
	expect($src)->not->toContain('$ids_to_disable = implode');
});

test('api_data_source.php purge action binds the method as a placeholder', function () use ($libDir) {
	$src = file_get_contents($libDir . '/api_data_source.php');

	// The REPLACE(...) action value moved from inline concat to a bound ?.
	expect($src)->toContain("REPLACE(data_source_path, '<path_rra>/', ''), ?");
	expect($src)->not->toContain("''), '\" . \$acmethod . \"'");
});

// =====================================================================
// lib/api_device.php source guards
// =====================================================================

test('api_device.php routes device and data-query id lists through array_to_sql_or', function () use ($libDir) {
	$src = file_get_contents($libDir . '/api_device.php');

	expect($src)->toContain('array_to_sql_or($int_device_ids, ');
	expect($src)->toContain("array_to_sql_or(array_keys(\$objects['data_queries']), ");
});

test('api_device.php drops the raw imploded/interpolated device IN() clauses', function () use ($libDir) {
	$src = file_get_contents($libDir . '/api_device.php');

	expect($src)->not->toContain("implode(', ', \$int_device_ids)");
	expect($src)->not->toContain("implode(',', array_keys(\$objects['data_queries']))");
	expect($src)->not->toContain('IN ($devices_to_delete)');
	expect($src)->not->toContain('IN($devices_to_delete)');
});

// =====================================================================
// lib/utility.php source guards
// =====================================================================

test('utility.php routes object id lists through array_to_sql_or', function () use ($libDir) {
	$src = file_get_contents($libDir . '/utility.php');

	expect($src)->toContain('array_to_sql_or($object_ids, ');
});

test('utility.php drops the raw imploded object_ids IN() interpolation', function () use ($libDir) {
	$src = file_get_contents($libDir . '/utility.php');

	expect($src)->not->toContain("implode(', ', \$object_ids)");
});
