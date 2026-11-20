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
 * Tests for scripts/sql.php and scripts/ss_sql.php.
 *
 * Both scripts report 'mysqladmin status' values from the server status
 * variables over Cacti's own database connection, so the database password
 * is never placed on a process command line.
 */

namespace CactiSqlScriptsFixture;

require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$GLOBALS['sql_scripts_status'] = array();

function db_fetch_assoc($sql, $log = true, $db_conn = false) {
	$GLOBALS['sql_scripts_query'] = $sql;

	return $GLOBALS['sql_scripts_status'];
}

if (!function_exists(__NAMESPACE__ . '\\ss_sql')) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/scripts/ss_sql.php', 'ss_sql', __NAMESPACE__);
}

function sql_scripts_rows(array $status) {
	$rows = array();

	foreach ($status as $name => $value) {
		$rows[] = array('Variable_name' => $name, 'Value' => $value);
	}

	return $rows;
}

$sqlPhpPath   = __DIR__ . '/../../../scripts/sql.php';
$ssSqlPhpPath = __DIR__ . '/../../../scripts/ss_sql.php';

test('neither script runs mysqladmin or passes database credentials to a command', function () use ($sqlPhpPath, $ssSqlPhpPath) {
	foreach (array($sqlPhpPath, $ssSqlPhpPath) as $path) {
		$contents = file_get_contents($path);

		expect($contents)->not->toContain('cacti_exec_string(');
		expect($contents)->not->toContain('shell_exec(');
		expect($contents)->not->toContain('--password=');
		expect($contents)->not->toContain('$database_password');
	}
});

test('sql.php reads the Questions status variable and prints U when it is missing', function () use ($sqlPhpPath) {
	$contents = file_get_contents($sqlPhpPath);

	expect($contents)->toContain("SHOW GLOBAL STATUS LIKE 'Questions'");
	expect($contents)->toContain("print 'U';");
});

test('ss_sql() reports the mysqladmin status fields under their existing names', function () {
	$GLOBALS['sql_scripts_status'] = sql_scripts_rows(array(
		'Uptime'            => '86400',
		'Threads_connected' => '7',
		'Questions'         => '123456',
		'Slow_queries'      => '3',
		'Opened_tables'     => '450',
		'Flush_commands'    => '1',
		'Open_tables'       => '400',
	));

	expect(ss_sql())->toBe('Uptime:86400 Threads:7 Questions:123456 SlowQueries:3 Opens:450 FlushTables:1 OpenTables:400 QPS:1.428');
	expect($GLOBALS['sql_scripts_query'])->toContain('SHOW GLOBAL STATUS');
});

test('ss_sql() truncates QPS to three decimals like mysqladmin', function () {
	$GLOBALS['sql_scripts_status'] = sql_scripts_rows(array('Uptime' => '3', 'Questions' => '2'));

	expect(ss_sql())->toBe('Uptime:3 Questions:2 QPS:0.666');
});

test('ss_sql() leaves out Flush tables on MariaDB, matching its mysqladmin', function () {
	$GLOBALS['sql_scripts_status'] = sql_scripts_rows(array(
		'Open_tables'       => '10',
		'Opened_tables'     => '17',
		'Questions'         => '3',
		'Slow_queries'      => '0',
		'Threads_connected' => '1',
		'Uptime'            => '5',
	));

	/* mariadb-admin status: Uptime: 5  Threads: 1  Questions: 3  Slow queries: 0  Opens: 17  Open tables: 10  Queries per second avg: 0.600 */
	expect(ss_sql())->toBe('Uptime:5 Threads:1 Questions:3 SlowQueries:0 Opens:17 OpenTables:10 QPS:0.600');
});

test('ss_sql() reports zero QPS for a server that has just started', function () {
	$GLOBALS['sql_scripts_status'] = sql_scripts_rows(array('Uptime' => '0', 'Questions' => '5'));

	expect(ss_sql())->toContain('QPS:0.000');
});

test('ss_sql() returns U when the status query returns nothing', function () {
	$GLOBALS['sql_scripts_status'] = array();

	expect(ss_sql())->toBe('U');

	$GLOBALS['sql_scripts_status'] = false;

	expect(ss_sql())->toBe('U');
});
