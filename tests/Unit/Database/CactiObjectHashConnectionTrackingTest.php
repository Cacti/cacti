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
 * lib/database.php keys its per-connection bookkeeping ($database_details,
 * $affected_rows) on cacti_object_hash() now that spl_object_hash() is
 * deprecated in PHP 8.6. These tests drive the connection-tracking paths that
 * dereference that key with a real PDO handle so the swap is exercised, not
 * just asserted structurally:
 *   - db_check_reconnect() matches a live handle against $database_details
 *   - db_close() resolves the handle's registration under DEBUG_SQL_CONNECT
 *   - db_execute_prepared() logs the handle's registration under
 *     DEBUG_SQL_CONNECT
 *
 * These functions assign to a broad set of connection globals, so every global
 * they touch is snapshotted and restored around each test. Leaking them would
 * corrupt the shared connection state the rest of the suite depends on.
 */

require_once dirname(__DIR__, 2) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 3) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/lib/database.php';

const CACTI_OBJECT_HASH_TRACKING_GLOBALS = [
	'config', 'database_details', 'database_sessions', 'database_persist',
	'error_logged', 'affected_rows', 'database_last_error',
	'database_hostname', 'database_username', 'database_password',
	'database_default', 'database_type', 'database_port', 'database_retries',
	'database_ssl', 'database_ssl_key', 'database_ssl_cert', 'database_ssl_ca',
	'database_ssl_capath', 'database_ssl_verify_server_cert',
];

beforeEach(function () : void {
	$this->savedGlobals = [];

	foreach (CACTI_OBJECT_HASH_TRACKING_GLOBALS as $name) {
		$this->savedGlobals[$name] = array_key_exists($name, $GLOBALS)
			? [true, $GLOBALS[$name]]
			: [false, null];
	}
});

afterEach(function () : void {
	foreach ($this->savedGlobals as $name => $state) {
		[$existed, $value] = $state;

		if ($existed) {
			$GLOBALS[$name] = $value;
		} else {
			unset($GLOBALS[$name]);
		}
	}
});

function cacti_object_hash_tracking_detail(PDO $conn) : array {
	return [
		'database_conn'                     => $conn,
		'database_hostname'                 => 'localhost',
		'database_username'                 => 'cacti',
		'database_password'                 => 'secret',
		'database_default'                  => 'cacti',
		'database_type'                     => 'mysql',
		'database_port'                     => 3306,
		'database_retries'                  => 2,
		'database_ssl'                      => false,
		'database_ssl_key'                  => '',
		'database_ssl_cert'                 => '',
		'database_ssl_ca'                   => '',
		'database_ssl_capath'               => '',
		'database_ssl_verify_server_cert'   => false,
	];
}

it('matches a live connection against its registered details via cacti_object_hash', function () {
	$conn = new PDO('sqlite::memory:');

	$GLOBALS['database_details'] = [
		cacti_object_hash($conn) => cacti_object_hash_tracking_detail($conn),
	];

	$handle = $conn;

	// The reconnect check hashes every registered handle and compares it to
	// the one passed in; a match copies the stored credentials and the live
	// 'SELECT 1' probe then confirms the handle is still usable.
	expect(db_check_reconnect($handle, false))->toBeTrue();
});

it('resolves a registered connection while closing under DEBUG_SQL_CONNECT', function () {
	$conn = new PDO('sqlite::memory:');

	$GLOBALS['config']['DEBUG_SQL_CONNECT'] = true;
	$GLOBALS['database_persist']            = true;
	$GLOBALS['database_details']            = [
		cacti_object_hash($conn) => cacti_object_hash_tracking_detail($conn),
	];

	$handle = $conn;

	// db_close() hashes the handle to look up its registration before
	// disconnecting; persistence is forced on so no KILL CONNECTION is issued
	// against the SQLite handle.
	expect(db_close($handle))->toBeTrue()
		->and($handle)->toBeNull();
});

it('logs a registered connection while executing under DEBUG_SQL_CONNECT', function () {
	$conn = new PDO('sqlite::memory:');
	$conn->exec('CREATE TABLE host (id INTEGER PRIMARY KEY AUTOINCREMENT, hostname TEXT NOT NULL)');

	$GLOBALS['config']['DEBUG_SQL_CONNECT'] = true;
	$GLOBALS['database_details']            = [
		cacti_object_hash($conn) => cacti_object_hash_tracking_detail($conn),
	];

	// With DEBUG_SQL_CONNECT on, db_execute_prepared() hashes the handle to
	// find its registration for the debug log, then runs the statement and
	// records the affected-row count under the same hash.
	db_execute_prepared("INSERT INTO host (hostname) VALUES ('h1')", [], false, $conn);

	expect((int) db_affected_rows($conn))->toBe(1);
});
