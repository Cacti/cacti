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
 * Tests for db_commit_transaction() in lib/database.php.
 *
 * The in-transaction check was `db_fetch_cell('SELECT @@in_transaction')`,
 * called without the 4th ($db_conn) argument, so it always queried the
 * default pooled connection instead of the connection actually passed in.
 * On a multi-connection setup, committing a transaction on a non-default
 * connection would read the wrong connection's transaction state and
 * silently skip the commit. The fix passes $db_conn through:
 * `db_fetch_cell('SELECT @@in_transaction', '', true, $db_conn)`.
 *
 * FakeDbConn/FakeStatement stand in for PDO so the test can tell which
 * connection object actually received the query, without depending on
 * '@@in_transaction' (a MySQL-only construct SQLite can't parse).
 */

if (!function_exists('clean_up_lines')) {
	// Stub of lib/functions.php's clean_up_lines(); db_strip_control_chars()
	// calls it unconditionally and lib/functions.php isn't loaded here.
	function clean_up_lines($string) {
		return $string;
	}
}

require_once dirname(__DIR__, 2) . '/lib/database.php';

class FakeDbCommitStatement {
	public $cell_value;

	public function __construct($cell_value) {
		$this->cell_value = $cell_value;
	}

	public function execute($params = array()) {
		return true;
	}

	public function errorCode() {
		return '00000';
	}

	public function errorInfo() {
		return array(0 => '00000');
	}

	public function fetchAll($mode = null) {
		return array(array(0 => $this->cell_value));
	}

	public function rowCount() {
		return 1;
	}

	public function closeCursor() {
		return true;
	}
}

class FakeDbCommitConn {
	public $cell_value;
	public $prepared      = array();
	public $commit_called = false;

	public function __construct($cell_value) {
		$this->cell_value = $cell_value;
	}

	public function prepare($sql) {
		$this->prepared[] = $sql;

		return new FakeDbCommitStatement($this->cell_value);
	}

	public function commit() {
		$this->commit_called = true;

		return true;
	}

	public function errorCode() {
		return '00000';
	}

	public function errorInfo() {
		return array(0 => '00000');
	}
}

beforeEach(function () {
	global $database_hostname, $database_port, $database_default, $database_sessions;

	$database_hostname = 'default-host';
	$database_port     = '3306';
	$database_default  = 'cacti';

	// The pooled default connection: no active transaction (cell_value 0),
	// so it must never be committed by this test.
	$this->default_conn = new FakeDbCommitConn(0);
	$database_sessions["$database_hostname:$database_port:$database_default"] = $this->default_conn;

	// The connection explicitly passed to db_commit_transaction(): has an
	// active transaction (cell_value 1) and must be the one queried/committed.
	$this->passed_conn = new FakeDbCommitConn(1);
});

test('db_commit_transaction queries the passed connection, not the default one', function () {
	db_commit_transaction($this->passed_conn);

	expect($this->passed_conn->prepared)->toContain('SELECT @@in_transaction');
	expect($this->default_conn->prepared)->toBe(array());
});

test('db_commit_transaction commits the passed connection when it has an active transaction', function () {
	db_commit_transaction($this->passed_conn);

	expect($this->passed_conn->commit_called)->toBeTrue();
	expect($this->default_conn->commit_called)->toBeFalse();
});

test('db_commit_transaction does not commit when the passed connection has no active transaction', function () {
	$idle_conn = new FakeDbCommitConn(0);

	db_commit_transaction($idle_conn);

	expect($idle_conn->prepared)->toContain('SELECT @@in_transaction');
	expect($idle_conn->commit_called)->toBeFalse();
});
