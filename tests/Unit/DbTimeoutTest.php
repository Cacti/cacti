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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';

test('MariaDB wraps with SET STATEMENT for an integer timeout', function () {
	expect(db_sql_apply_timeout('SELECT 1', 5, 'MariaDB', '10.6.12'))
		->toBe('SET STATEMENT MAX_STATEMENT_TIME=5 FOR SELECT 1');
});

test('MariaDB keeps decimal seconds', function () {
	expect(db_sql_apply_timeout('SELECT 1', 2.5, 'MariaDB', '10.6.12'))
		->toBe('SET STATEMENT MAX_STATEMENT_TIME=2.5 FOR SELECT 1');
});

test('MariaDB trims trailing zeros on whole-number decimals', function () {
	expect(db_sql_apply_timeout('SELECT 1', 5.0, 'MariaDB', '10.6.12'))
		->toBe('SET STATEMENT MAX_STATEMENT_TIME=5 FOR SELECT 1');
});

test('MariaDB applies to non-SELECT statements', function () {
	expect(db_sql_apply_timeout('DELETE FROM host WHERE id = 1', 3, 'MariaDB', '10.6.12'))
		->toBe('SET STATEMENT MAX_STATEMENT_TIME=3 FOR DELETE FROM host WHERE id = 1');
});

test('MySQL injects the MAX_EXECUTION_TIME hint after SELECT', function () {
	expect(db_sql_apply_timeout('SELECT 1', 2, 'MySQL', '8.0.30'))
		->toBe('SELECT /*+ MAX_EXECUTION_TIME(2000) */ 1');
});

test('MySQL converts fractional seconds to milliseconds', function () {
	expect(db_sql_apply_timeout('SELECT id FROM host', 0.25, 'MySQL', '8.0.30'))
		->toBe('SELECT /*+ MAX_EXECUTION_TIME(250) */ id FROM host');
});

test('MySQL clamps sub-millisecond timeouts to at least 1ms', function () {
	expect(db_sql_apply_timeout('SELECT 1', 0.0001, 'MySQL', '8.0.30'))
		->toBe('SELECT /*+ MAX_EXECUTION_TIME(1) */ 1');
});

test('MySQL does not touch the SELECT keyword inside a larger word', function () {
	expect(db_sql_apply_timeout('SELECTED FROM x', 2, 'MySQL', '8.0.30'))
		->toBe('SELECTED FROM x');
});

test('MySQL leaves non-SELECT statements unchanged', function () {
	expect(db_sql_apply_timeout('UPDATE host SET disabled = 1', 2, 'MySQL', '8.0.30'))
		->toBe('UPDATE host SET disabled = 1');
});

test('MySQL leaves leading-WITH (CTE) statements unchanged', function () {
	expect(db_sql_apply_timeout('WITH cte AS (SELECT 1) SELECT * FROM cte', 2, 'MySQL', '8.0.30'))
		->toBe('WITH cte AS (SELECT 1) SELECT * FROM cte');
});

test('zero timeout is a no-op', function () {
	expect(db_sql_apply_timeout('SELECT 1', 0, 'MariaDB', '10.6.12'))->toBe('SELECT 1');
});

test('negative timeout is a no-op', function () {
	expect(db_sql_apply_timeout('SELECT 1', -1, 'MariaDB', '10.6.12'))->toBe('SELECT 1');
});

test('MariaDB below 10.1 passes through', function () {
	expect(db_sql_apply_timeout('SELECT 1', 5, 'MariaDB', '10.0.38'))->toBe('SELECT 1');
});

test('MySQL below 5.7.8 passes through', function () {
	expect(db_sql_apply_timeout('SELECT 1', 5, 'MySQL', '5.6.51'))->toBe('SELECT 1');
});

test('unknown engine passes through', function () {
	expect(db_sql_apply_timeout('SELECT 1', 5, '', ''))->toBe('SELECT 1');
});

test('MariaDB clamps sub-millisecond timeout to a 1ms floor instead of disabling the limit', function () {
	// MAX_STATEMENT_TIME=0 means "no limit" in MariaDB, so a positive timeout
	// must never format to 0.
	expect(db_sql_apply_timeout('SELECT 1', 0.0001, 'MariaDB', '10.6.12'))
		->toBe('SET STATEMENT MAX_STATEMENT_TIME=0.001 FOR SELECT 1');
});

test('non-finite timeouts pass through (MariaDB)', function () {
	expect(db_sql_apply_timeout('SELECT 1', INF, 'MariaDB', '10.6.12'))->toBe('SELECT 1');
	expect(db_sql_apply_timeout('SELECT 1', NAN, 'MariaDB', '10.6.12'))->toBe('SELECT 1');
});

test('non-finite timeouts pass through (MySQL)', function () {
	expect(db_sql_apply_timeout('SELECT 1', INF, 'MySQL', '8.0.30'))->toBe('SELECT 1');
	expect(db_sql_apply_timeout('SELECT 1', NAN, 'MySQL', '8.0.30'))->toBe('SELECT 1');
});

test('MySQL does not double-inject when a hint block already follows SELECT', function () {
	expect(db_sql_apply_timeout('SELECT /*+ BNL(t1) */ * FROM t1', 2, 'MySQL', '8.0.30'))
		->toBe('SELECT /*+ BNL(t1) */ * FROM t1');
});

test('db_execute_prepared accepts a trailing timeout argument', function () {
	$conn = new PDO('sqlite::memory:');
	$conn->exec('CREATE TABLE host (id INTEGER PRIMARY KEY AUTOINCREMENT, hostname TEXT NOT NULL)');

	$result = db_execute_prepared(
		'INSERT INTO host (hostname) VALUES (?)',
		['h1'], true, $conn, 'Exec', true, 'no_return_function', [], 5
	);

	expect($result)->toBeTruthy()
		->and($conn->query('SELECT hostname FROM host WHERE id = 1')->fetchColumn())->toBe('h1');
});

test('every public wrapper accepts a trailing timeout argument', function () {
	$conn = new PDO('sqlite::memory:');
	$conn->exec('CREATE TABLE host (id INTEGER PRIMARY KEY AUTOINCREMENT, hostname TEXT NOT NULL)');

	expect(db_execute("INSERT INTO host (hostname) VALUES ('h1')", true, $conn, 5))->toBeTruthy();

	expect(db_fetch_cell('SELECT hostname FROM host WHERE id = 1', '', true, $conn, 5))->toBe('h1');
	expect(db_fetch_cell_prepared('SELECT hostname FROM host WHERE id = ?', [1], '', true, $conn, 5))->toBe('h1');

	// SQLite's PDO driver reports rowCount()=0 for SELECT, so db_fetch_row* return [];
	// this only verifies the wrapper accepts and forwards the trailing $timeout argument.
	expect(db_fetch_row('SELECT * FROM host WHERE id = 1', true, $conn, 5))->toBeArray();
	expect(db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', [1], true, $conn, 5))->toBeArray();

	expect(db_fetch_assoc('SELECT hostname FROM host', true, $conn, 5)[0]['hostname'])->toBe('h1');
	expect(db_fetch_assoc_prepared('SELECT hostname FROM host WHERE id = ?', [1], true, $conn, 5)[0]['hostname'])->toBe('h1');
});

test('MariaDB version string with a distro/build suffix still applies the timeout', function () {
	// db_connect_real stores the raw server version (SHOW VARIABLES 'version').
	// version_compare decides on the leading numeric components, so build suffixes
	// do not defeat the >= gate.
	expect(db_sql_apply_timeout('SELECT 1', 5, 'MariaDB', '10.11.18-ubu2204'))
		->toBe('SET STATEMENT MAX_STATEMENT_TIME=5 FOR SELECT 1');
});

test('MySQL version string with a distro/build suffix still applies the hint', function () {
	expect(db_sql_apply_timeout('SELECT 1', 2, 'MySQL', '8.0.46-0ubuntu0.22.04.1'))
		->toBe('SELECT /*+ MAX_EXECUTION_TIME(2000) */ 1');
});

test('MySQL SELECT preceded by a comment block is left untimed', function () {
	// The hint must attach to the leading SELECT token; rather than risk placing it
	// before a comment, a comment-prefixed statement runs untimed. MariaDB is
	// unaffected because SET STATEMENT wraps the whole statement.
	expect(db_sql_apply_timeout('/* app */ SELECT 1', 2, 'MySQL', '8.0.30'))
		->toBe('/* app */ SELECT 1');
});
