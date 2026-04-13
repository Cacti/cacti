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
 * Source-scan tests for lib/database.php security patterns.
 *
 * These tests verify structural security properties of the database layer
 * by scanning the source code. No database connection is required.
 *
 * Verified patterns:
 * - Prepared statement functions exist and use PDO parameter binding
 * - No raw superglobals ($_GET, $_POST, $_REQUEST) in database layer
 * - db_qstr() escaping exists for non-parameterized paths
 * - SQL input is sanitized via db_strip_control_chars()
 * - Error logging uses cacti_log() at debug verbosity, not echo/print
 * - Input helper functions exist in lib/html_utility.php
 */

$dbSource = file_get_contents(
	dirname(__DIR__, 2) . '/lib/database.php'
);

$htmlUtilSource = file_get_contents(
	dirname(__DIR__, 2) . '/lib/html_utility.php'
);

// --- Prepared statement functions exist ---

test('db_execute_prepared function is defined', function () use ($dbSource) {
	expect($dbSource)->toContain('function db_execute_prepared(');
});

test('db_fetch_cell_prepared function is defined', function () use ($dbSource) {
	expect($dbSource)->toContain('function db_fetch_cell_prepared(');
});

test('db_fetch_row_prepared function is defined', function () use ($dbSource) {
	expect($dbSource)->toContain('function db_fetch_row_prepared(');
});

test('db_fetch_assoc_prepared function is defined', function () use ($dbSource) {
	expect($dbSource)->toContain('function db_fetch_assoc_prepared(');
});

// --- Prepared functions accept a params array ---

test('db_execute_prepared accepts array params parameter', function () use ($dbSource) {
	expect($dbSource)->toMatch('/function db_execute_prepared\(string \$sql, array \$params/');
});

test('db_fetch_cell_prepared accepts array params parameter', function () use ($dbSource) {
	expect($dbSource)->toMatch('/function db_fetch_cell_prepared\(string \$sql, array \$params/');
});

test('db_fetch_row_prepared accepts array params parameter', function () use ($dbSource) {
	expect($dbSource)->toMatch('/function db_fetch_row_prepared\(string \$sql, array \$params/');
});

test('db_fetch_assoc_prepared accepts array params parameter', function () use ($dbSource) {
	expect($dbSource)->toMatch('/function db_fetch_assoc_prepared\(string \$sql, array \$params/');
});

// --- PDO parameter binding is used ---

test('db_execute_prepared calls PDO prepare', function () use ($dbSource) {
	expect($dbSource)->toContain('$db_conn->prepare($sql)');
});

test('db_execute_prepared passes params to PDO execute', function () use ($dbSource) {
	expect($dbSource)->toContain('$query->execute($params)');
});

// --- Non-prepared wrappers delegate to prepared functions ---

test('db_execute delegates to db_execute_prepared', function () use ($dbSource) {
	expect($dbSource)->toContain('function db_execute(');
	expect($dbSource)->toContain('return db_execute_prepared($sql, [],');
});

test('db_fetch_cell delegates to db_fetch_cell_prepared', function () use ($dbSource) {
	expect($dbSource)->toContain('return db_fetch_cell_prepared($sql, [],');
});

test('db_fetch_row delegates to db_fetch_row_prepared', function () use ($dbSource) {
	expect($dbSource)->toContain('return db_fetch_row_prepared($sql, [],');
});

test('db_fetch_assoc delegates to db_fetch_assoc_prepared', function () use ($dbSource) {
	expect($dbSource)->toContain('return db_fetch_assoc_prepared($sql, [],');
});

// --- No raw superglobals in database layer ---

test('database.php does not reference $_GET', function () use ($dbSource) {
	expect($dbSource)->not->toContain('$_GET');
});

test('database.php does not reference $_POST', function () use ($dbSource) {
	expect($dbSource)->not->toContain('$_POST');
});

test('database.php does not reference $_REQUEST', function () use ($dbSource) {
	expect($dbSource)->not->toContain('$_REQUEST');
});

test('database.php does not reference $_COOKIE', function () use ($dbSource) {
	expect($dbSource)->not->toContain('$_COOKIE');
});

// --- db_qstr escaping function ---

test('db_qstr function is defined', function () use ($dbSource) {
	expect($dbSource)->toContain('function db_qstr(');
});

test('db_qstr uses PDO quote when connection is available', function () use ($dbSource) {
	expect($dbSource)->toContain('$db_conn->quote($s)');
});

test('db_qstr has manual escape fallback for backslash and quotes', function () use ($dbSource) {
	expect($dbSource)->toMatch('/str_replace\(\[.*\\\\\\\\.*\'.*\]/s');
});

// --- SQL input sanitization ---

test('db_strip_control_chars function is defined', function () use ($dbSource) {
	expect($dbSource)->toContain('function db_strip_control_chars(');
});

test('db_execute_prepared calls db_strip_control_chars on SQL', function () use ($dbSource) {
	expect($dbSource)->toContain('$sql = db_strip_control_chars($sql)');
});

test('db_strip_control_chars strips trailing semicolons', function () use ($dbSource) {
	expect(str_contains($dbSource, "trim(clean_up_lines(\$sql), ';')"))->toBeTrue();
});

// --- Error logging uses cacti_log at debug verbosity, not user-facing output ---

test('error logging uses cacti_log with DBCALL category', function () use ($dbSource) {
	$matches = [];
	preg_match_all('/cacti_log\(.+?\'DBCALL\'/', $dbSource, $matches);
	expect(count($matches[0]))->toBeGreaterThan(0);
});

test('error logging uses POLLER_VERBOSITY_DEBUG for SQL errors', function () use ($dbSource) {
	$matches = [];
	preg_match_all('/cacti_log\(.*SQL.*POLLER_VERBOSITY_DEBUG\)/', $dbSource, $matches);
	expect(count($matches[0]))->toBeGreaterThan(0);
});

test('db_execute_prepared does not echo or print SQL errors', function () use ($dbSource) {
	// Extract db_execute_prepared function body
	$start = strpos($dbSource, 'function db_execute_prepared(');
	if ($start === false) {
		$this->fail('function db_execute_prepared not found in source');
	}
	$end = strpos($dbSource, "\nfunction ", $start + 1);
	if ($end === false) {
		$this->fail('no function follows db_execute_prepared in source');
	}
	$body = substr($dbSource, $start, $end - $start);

	// No direct echo/print of error info to user
	expect($body)->not->toMatch('/\becho\s+\$errorinfo/');
	expect($body)->not->toMatch('/\bprint\s+\$errorinfo/');
});

// --- Error information stored in global, not exposed directly ---

test('database errors are stored in database_last_error global', function () use ($dbSource) {
	expect($dbSource)->toContain('$database_last_error');
});

test('db_error function returns the last error from global', function () use ($dbSource) {
	expect($dbSource)->toContain('function db_error()');
	expect($dbSource)->toMatch('/function db_error\(\).*\{[^}]*\$database_last_error/s');
});

// --- PDO exception is caught to prevent credential leakage ---

test('db_connect_real catches PDOException to prevent credential leakage', function () use ($dbSource) {
	$start = strpos($dbSource, 'function db_connect_real(');
	if ($start === false) {
		$this->fail('function db_connect_real not found in source');
	}
	$end = strpos($dbSource, "\nfunction ", $start + 1);
	if ($end === false) {
		$this->fail('no function follows db_connect_real in source');
	}
	$body = substr($dbSource, $start, $end - $start);

	expect($body)->toContain('catch (PDOException $e)');
	// The original echo/print lines are commented out
	expect($body)->toContain('// print $e->getMessage()');
});

// --- sql_save uses db_qstr for string column values ---

test('sql_save escapes string values through db_qstr', function () use ($dbSource) {
	$start = strpos($dbSource, 'function sql_save(');
	if ($start === false) {
		$this->fail('function sql_save not found in source');
	}
	$end = strpos($dbSource, "\nfunction ", $start + 1);
	if ($end === false) {
		$this->fail('no function follows sql_save in source');
	}
	$body = substr($dbSource, $start, $end - $start);

	expect($body)->toContain('db_qstr($value)');
});

// --- Connection timeout is set to limit DoS from slow DB ---

test('PDO connection timeout is configured', function () use ($dbSource) {
	expect($dbSource)->toContain('PDO::ATTR_TIMEOUT');
});

// --- Input helper functions exist in html_utility.php ---

test('get_request_var function is defined', function () use ($htmlUtilSource) {
	expect($htmlUtilSource)->toContain('function get_request_var(');
});

test('set_request_var function is defined', function () use ($htmlUtilSource) {
	expect($htmlUtilSource)->toContain('function set_request_var(');
});

test('get_filter_request_var function is defined', function () use ($htmlUtilSource) {
	expect($htmlUtilSource)->toContain('function get_filter_request_var(');
});

test('get_nfilter_request_var function is defined', function () use ($htmlUtilSource) {
	expect($htmlUtilSource)->toContain('function get_nfilter_request_var(');
});

test('get_filter_request_var uses filter_var for validation', function () use ($htmlUtilSource) {
	$start = strpos($htmlUtilSource, 'function get_filter_request_var(');
	if ($start === false) {
		$this->fail('function get_filter_request_var not found in source');
	}
	$end = strpos($htmlUtilSource, "\nfunction ", $start + 1);
	if ($end === false) {
		$this->fail('no function follows get_filter_request_var in source');
	}
	$body = substr($htmlUtilSource, $start, $end - $start);

	expect($body)->toContain('filter_var(');
});

test('get_filter_request_var defaults to FILTER_VALIDATE_INT', function () use ($htmlUtilSource) {
	expect($htmlUtilSource)->toMatch(
		'/function get_filter_request_var\(string \$name, int \$filter = FILTER_VALIDATE_INT/'
	);
});

// --- Shorthand aliases exist for input helpers ---

test('grv alias exists for get_request_var', function () use ($htmlUtilSource) {
	expect($htmlUtilSource)->toContain('function grv(');
	expect($htmlUtilSource)->toContain('return get_request_var($name');
});

test('gfrv alias exists for get_filter_request_var', function () use ($htmlUtilSource) {
	expect($htmlUtilSource)->toContain('function gfrv(');
	expect($htmlUtilSource)->toContain('return get_filter_request_var($name');
});

test('gnrv alias exists for get_nfilter_request_var', function () use ($htmlUtilSource) {
	expect($htmlUtilSource)->toContain('function gnrv(');
	expect($htmlUtilSource)->toContain('return get_nfilter_request_var($name');
});
