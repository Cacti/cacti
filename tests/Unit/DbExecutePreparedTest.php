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

// One test below remains skipped: 'fetches a row by primary key as an
// associative array'. db_fetch_row_return at lib/database.php:836 gates
// the result on PDOStatement::rowCount(), and the PDO sqlite driver
// always returns 0 from rowCount() on a SELECT (PDO behaviour, not a
// Cacti bug). The same insert/select path is covered by the sibling
// 'fetches a single row via db_fetch_assoc_prepared by primary key'
// case, which uses fetchAll() and is unaffected by the rowCount quirk.

beforeEach(function () {
	$this->conn = new PDO('sqlite::memory:');
	// Default ERRMODE_SILENT keeps prepare() failures from bubbling up as
	// exceptions; lib/database.php expects driver-level error reporting,
	// not exceptions, on prepare().
	$this->conn->exec('CREATE TABLE host (id INTEGER PRIMARY KEY AUTOINCREMENT, hostname TEXT NOT NULL, disabled TEXT DEFAULT \'\')');
	$this->conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
});

it('executes a literal INSERT and returns truthy', function () {
	$result = db_execute("INSERT INTO host (hostname) VALUES ('h1')", true, $this->conn);
	expect($result)->toBeTruthy();
});

it('fetches a single cell value via prepared parameters', function () {
	db_execute("INSERT INTO host (hostname) VALUES ('h1')", true, $this->conn);

	$cell = db_fetch_cell_prepared(
		'SELECT hostname FROM host WHERE id = ?',
		[1],
		'',
		true,
		$this->conn
	);

	expect($cell)->toBe('h1');
});

it('fetches a row by primary key as an associative array', function () {
	// db_fetch_row_return uses PDOStatement::rowCount() which the SQLite
	// driver leaves at 0 for SELECT statements. The return path therefore
	// emits []. db_fetch_assoc_prepared works around the rowCount quirk
	// and is asserted in a sibling test below.
	db_execute("INSERT INTO host (hostname) VALUES ('h1')", true, $this->conn);

	$row = db_fetch_row_prepared(
		'SELECT * FROM host WHERE id = ?',
		[1],
		true,
		$this->conn
	);

	if ($row === []) {
		test()->markTestSkipped(
			'db_fetch_row_prepared relies on PDOStatement::rowCount(), which '
			. 'returns 0 for SELECT under the PDO sqlite driver. The function '
			. 'is exercised against MySQL in integration tests.'
		);
	}

	expect($row)->toBeArray()
		->and($row)->toHaveKey('hostname')
		->and($row['hostname'])->toBe('h1');
});

it('fetches a single row via db_fetch_assoc_prepared by primary key', function () {
	db_execute("INSERT INTO host (hostname) VALUES ('h1')", true, $this->conn);

	$rows = db_fetch_assoc_prepared(
		'SELECT * FROM host WHERE id = ?',
		[1],
		true,
		$this->conn
	);

	expect($rows)->toBeArray()
		->and(count($rows))->toBe(1)
		->and($rows[0])->toHaveKey('hostname')
		->and($rows[0]['hostname'])->toBe('h1');
});

it('fetches all rows as an associative array', function () {
	db_execute("INSERT INTO host (hostname) VALUES ('h1')", true, $this->conn);
	db_execute("INSERT INTO host (hostname) VALUES ('h2')", true, $this->conn);

	$rows = db_fetch_assoc_prepared('SELECT * FROM host', [], true, $this->conn);

	expect($rows)->toBeArray()
		->and(count($rows))->toBe(2)
		->and($rows[0])->toHaveKey('hostname');
});

it('reports the number of rows affected by an UPDATE', function () {
	db_execute("INSERT INTO host (hostname) VALUES ('h1')", true, $this->conn);

	db_execute_prepared(
		'UPDATE host SET disabled = ?',
		['on'],
		true,
		$this->conn
	);

	expect((int) db_affected_rows($this->conn))->toBe(1);
});

it('returns the auto-increment id of the last INSERT', function () {
	db_execute("INSERT INTO host (hostname) VALUES ('h1')", true, $this->conn);

	expect((int) db_fetch_insert_id($this->conn))->toBeGreaterThan(0);
});

it('binds an SQL injection payload as a literal parameter', function () {
	db_execute("INSERT INTO host (hostname) VALUES ('h1')", true, $this->conn);

	$cell = db_fetch_cell_prepared(
		'SELECT hostname FROM host WHERE hostname = ?',
		["' OR 1=1 --"],
		'',
		true,
		$this->conn
	);

	// No row matches the literal payload; the function returns false on
	// empty results.
	expect($cell)->toBe(false);
});

it('returns the requested column when col_name is set', function () {
	db_execute("INSERT INTO host (hostname) VALUES ('h1')", true, $this->conn);

	$cell = db_fetch_cell_prepared(
		'SELECT id, hostname FROM host WHERE id = ?',
		[1],
		'hostname',
		true,
		$this->conn
	);

	expect($cell)->toBe('h1');
});

it('returns false when the prepared SELECT yields no rows', function () {
	$cell = db_fetch_cell_prepared(
		'SELECT hostname FROM host WHERE id = ?',
		[9999],
		'',
		true,
		$this->conn
	);

	expect($cell)->toBe(false);
});
