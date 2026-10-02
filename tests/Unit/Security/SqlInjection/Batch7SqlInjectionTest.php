<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Batch 7 develop-only SQL-injection consolidation. Each assertion pins a
 * sibling-of-already-fixed SQLi sink: db_column_exists (GHSA-rp5g), the
 * automation pivot builders (GHSA-vx2m), aggregate_make_sql_where (GHSA-929h),
 * cli/add_graphs REGEXP (GHSA-mfw8), the plugin DDL identifiers (GHSA-h5wg),
 * the user_admin sort_column pivot (GHSA-m49v), and the already-bound group
 * filter (GHSA-wfcf).
 */

$root = dirname(__DIR__, 4);

test('db_column_exists quotes its LIKE term (GHSA-rp5g)', function () use ($root) {
	$s = file_get_contents($root . '/lib/database.php');
	expect($s)->toContain('db_qstr($column)')
		->and($s)->not->toContain("LIKE '$column'");
});

test('automation pivot builders escape field_name (GHSA-vx2m)', function () use ($root) {
	$s = file_get_contents($root . '/lib/api_automation.php');
	expect($s)->not->toContain("CASE WHEN field_name='")
		->and($s)->not->toContain("CASE WHEN field_name ='")
		->and(substr_count($s, 'CASE WHEN field_name = " . db_qstr('))->toBeGreaterThanOrEqual(3);
});

test('aggregate LIKE filter term is quoted (GHSA-929h)', function () use ($root) {
	$s = file_get_contents($root . '/aggregate_graphs.php');
	expect($s)->toContain("db_qstr('%'")
		->and($s)->not->toContain("LIKE '%\" . trim(\$i)");
});

test('cli add_graphs uses db_qstr not addslashes for REGEXP (GHSA-mfw8)', function () use ($root) {
	$s = file_get_contents($root . '/cli/add_graphs.php');
	expect($s)->not->toContain('addslashes');
});

test('plugin drop/create confine the table identifier (GHSA-h5wg)', function () use ($root) {
	$s = file_get_contents($root . '/lib/plugins.php');
	expect($s)->not->toContain('"DROP TABLE IF EXISTS $table"')
		->and($s)->toContain("preg_replace('/[^a-zA-Z0-9_]/', '', $table)");
});

test('user_admin clamps sort_column to displayed columns (GHSA-m49v)', function () use ($root) {
	$s = file_get_contents($root . '/user_admin.php');
	expect($s)->toContain("set_request_var('sort_column', 'username')");
});

test('user_admin group filter is a bound parameter (GHSA-wfcf)', function () use ($root) {
	$s = file_get_contents($root . '/user_admin.php');
	expect($s)->toContain('ug.group_id = ?');
});
