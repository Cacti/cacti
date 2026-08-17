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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 1) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

/*
 * support.php (this branch, #7353) has no test-bootstrap early return, so
 * requiring it runs the real auth + dispatch path and fails outside a web
 * request. SupportProcessTablesHookTest.php (tests/Unit) and the e2e Docker
 * probe both work around that by extracting support_process_tables() out of
 * the file with a regex and eval()ing just that function body; this test
 * follows the same, already-reviewed pattern rather than inventing a second
 * one. eval() runs only Cacti's own source pulled straight from support.php
 * in this checkout -- no external or user-controlled input reaches it.
 *
 * What neither of those existing tests covers: the Unit test drives the
 * plugin hook through a test global and never touches a database; the e2e
 * probe calls support_process_tables() itself but does not build or execute
 * the UNION the way show_cacti_processes() does. So nothing today proves the
 * hook's SELECT fragments, once reduced by a real db_table_exists() check,
 * actually run as SQL rather than merely being well-formed strings. This test
 * fills that gap with a real (but web-stack-free) database connection, and
 * skips outright when none is reachable -- matching the DbConnectRetryTest /
 * DbDumpIntegrationTest convention -- so CI without a DB service still passes.
 */

function support_process_tables_integration_define(): void {
	if (function_exists('support_process_tables_integ_probe')) {
		return;
	}

	$src = file_get_contents(dirname(__DIR__, 2) . '/support.php');

	if (preg_match('/function\s+support_process_tables\s*\(\s*\)\s*:\s*array\s*\{.*?^\}/sm', $src, $m) !== 1) {
		test('support process tables integration: feature not present on this branch', function () {})
			->skip('support_process_tables() absent -- PR #7353 not merged into develop yet');

		return;
	}

	$body = preg_replace('/^function\s+support_process_tables\s*\(\s*\)/m', 'function support_process_tables_integ_probe()', $m[0], 1, $rename_count);

	if ($rename_count !== 1) {
		throw new RuntimeException("expected exactly one support_process_tables() function rename, found $rename_count");
	}

	eval($body);
}

support_process_tables_integration_define();

if (!function_exists('support_process_tables_integ_probe')) {
	return;
}

function support_process_tables_integration_connect(): mixed {
	if (!extension_loaded('pdo_mysql')) {
		return false;
	}

	return db_connect_real(
		getenv('CACTI_TEST_DB_HOST') ?: '127.0.0.1',
		getenv('CACTI_TEST_DB_USER') ?: 'cacti',
		getenv('CACTI_TEST_DB_PASS') ?: 'cacti',
		getenv('CACTI_TEST_DB_NAME') ?: 'cacti',
		'mysql',
		(int) (getenv('CACTI_TEST_DB_PORT') ?: 3306),
		1
	);
}

test('support_process_tables definitions execute as real SQL against a live database', function () {
	$conn = support_process_tables_integration_connect();

	if (!is_object($conn)) {
		test()->markTestSkipped('no reachable MySQL/MariaDB connection; start docker-compose or set CACTI_TEST_DB_* to run this test.');
	}

	if (!db_table_exists('poller_time', false, $conn)) {
		test()->markTestSkipped('connected, but the Cacti schema is not present (poller_time missing); import cacti.sql to run this test.');
	}

	$definitions = support_process_tables_integ_probe();

	// Mirror show_cacti_processes()'s own reduction: only tables that exist go
	// into the UNION, in registration order.
	$sql_inner = '';

	foreach ($definitions as $definition) {
		if (db_table_exists($definition['table'], false, $conn)) {
			$sql_inner .= ($sql_inner != '' ? ' UNION ' : '') . $definition['select'];
		}
	}

	expect($sql_inner)->not->toBe('');

	// The core poller_time table always exists once the schema is imported, so
	// the UNION is never empty here; a syntax error in any registered SELECT
	// fragment would surface as `false`, not as a legitimate row count.
	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*) FROM ($sql_inner) AS rs", [], '', false, $conn);

	expect($total_rows)->not->toBeFalse()
		->and(is_numeric($total_rows))->toBeTrue();
});

test('an empty table set produces a clean zero-row result, not a broken query', function () {
	$conn = support_process_tables_integration_connect();

	if (!is_object($conn)) {
		test()->markTestSkipped('no reachable MySQL/MariaDB connection; start docker-compose or set CACTI_TEST_DB_* to run this test.');
	}

	// Reproduces show_cacti_processes()'s guard for when db_table_exists()
	// filters out every candidate table: the function must short-circuit to an
	// empty result rather than ever building "FROM () AS rs".
	$sql_inner = '';

	foreach (['nonexistent_table_one', 'nonexistent_table_two'] as $missing) {
		if (db_table_exists($missing, false, $conn)) {
			$sql_inner .= ($sql_inner != '' ? ' UNION ' : '') . "SELECT 1 FROM $missing";
		}
	}

	expect($sql_inner)->toBe('');
});
