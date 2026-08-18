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

require_once CACTI_PATH_TESTS . '/Helpers/CactiStubs.php';
require_once CACTI_PATH_INCLUDE . '/global.php';

/*
 * support_lockout() (support.php, #7352) reads and writes cacti_lockout_status
 * with read_config_option()/set_config_option(), which only hit a real
 * database, not the PHP_TESTING sentinel. Neither existing test exercises that
 * persistence:
 *
 *   - tests/Unit/Security/Csrf/SupportLockoutCsrfTest.php says so directly: "the stored
 *     lockout state ... under the test bootstrap ... returns the empty
 *     default, so the 'currently locked' path (unlock) cannot be reached
 *     here and is left to the Docker end-to-end test."
 *   - tests/e2e/support_lockout_probe.php says the opposite: it only checks
 *     that a GET and an unauthenticated POST are refused, and deliberately
 *     "never leaves the system in a locked-out state" -- so it never drives
 *     a real lock or unlock either.
 *
 * That leaves the actual compare-and-set write/read round trip -- lock,
 * reject a stale 'expected', then unlock -- unverified against a real
 * database anywhere in the suite. This test fills that gap directly, without
 * the HTTP/session/CSRF layer e2e exercises separately, and restores the
 * setting afterward so a developer's local database is left clean.
 *
 * Extraction mirrors tests/Unit/Security/Csrf/SupportLockoutCsrfTest.php exactly (same
 * source, same header()/cacti_log()/exit rewrites): support.php on this
 * branch has no test-bootstrap early return, so it cannot be required
 * directly. eval() runs only Cacti's own source pulled from support.php in
 * this checkout -- no external or user-controlled input reaches it.
 */

function lockout_csrf_integration_define(): void {
	if (function_exists('lockout_csrf_integration_probe')) {
		return;
	}

	$src = file_get_contents(CACTI_PATH_BASE . '/support.php');

	if (preg_match('/function support_lockout\(\) : void \{.*?^\}/sm', $src, $m) !== 1) {
		test('support lockout csrf integration: feature not present on this branch', function () {})
			->skip('support_lockout() compare-and-set guard absent -- PR #7352 not merged into develop yet');

		return;
	}

	$body = $m[0];
	$body = preg_replace('/^\s*header\([^;]*\);\s*$/m', '', $body);
	$body = preg_replace('/^\s*cacti_log\([^;]*\);\s*$/m', '', $body);
	$body = preg_replace('/\bexit\s*;/', 'return;', $body);

	// This test has a real DB connection, so a forced read genuinely hits the
	// settings table -- unlike the Unit test, which needs this same rewrite to
	// dodge the PHP_TESTING sentinel. Here it keeps the admin identity on the
	// seeded config-cache value instead of the real (unseeded) 'admin_user'
	// row, so the test stays focused on the cacti_lockout_status round trip
	// this PR actually changes rather than on pre-existing admin bootstrapping.
	// The admin comparison's outcome is unaffected: it only changes where the
	// value is read from.
	$body = str_replace("read_config_option('admin_user', true)", "read_config_option('admin_user')", $body);

	$body = preg_replace('/^function support_lockout\(\)/m', 'function lockout_csrf_integration_probe()', $body);

	eval($body);
}

lockout_csrf_integration_define();

if (!function_exists('lockout_csrf_integration_probe')) {
	return;
}

function lockout_csrf_integration_connect(): mixed {
	global $database_hostname, $database_username, $database_password, $database_default, $database_port;

	if (!extension_loaded('pdo_mysql')) {
		return false;
	}

	$database_hostname = getenv('CACTI_TEST_DB_HOST') ?: '127.0.0.1';
	$database_username = getenv('CACTI_TEST_DB_USER') ?: 'cacti';
	$database_password = getenv('CACTI_TEST_DB_PASS') ?: 'cacti';
	$database_default  = getenv('CACTI_TEST_DB_NAME') ?: 'cacti';
	$database_port     = (int) (getenv('CACTI_TEST_DB_PORT') ?: 3306);

	// db_connect_real() caches the handle in $database_sessions keyed by
	// host:port:db; once that happens, set_config_option()/read_config_option()
	// pick it up on their own without any $db_conn argument, exactly as
	// support_lockout() calls them.
	return db_connect_real($database_hostname, $database_username, $database_password, $database_default, 'mysql', $database_port, 1);
}

beforeEach(function () {
	global $no_http_headers, $config;

	$no_http_headers = true;
	$_SESSION        = [];
	$_REQUEST        = [];
	$config[OPTIONS_CLI]['admin_user'] = '1';
});

test('a locked, then unlocked, maintenance toggle round-trips through the real settings table', function () {
	$conn = lockout_csrf_integration_connect();

	if (!is_object($conn)) {
		test()->markTestSkipped('no reachable MySQL/MariaDB connection; start docker-compose or set CACTI_TEST_DB_* to run this test.');
	}

	if (!db_table_exists('settings', false, $conn)) {
		test()->markTestSkipped('connected, but the Cacti schema is not present (settings missing); import cacti.sql to run this test.');
	}

	$original = read_config_option('cacti_lockout_status', true);

	try {
		// Start from a known unlocked state so the compare-and-set below is
		// deterministic regardless of what a prior run left behind.
		set_config_option('cacti_lockout_status', '');
		expect(read_config_option('cacti_lockout_status', true))->toBe('');

		// A stale 'expected' must be rejected and must not touch the row.
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SESSION[SESS_USER_ID]    = '1';
		$_REQUEST['expected']      = 'locked';

		lockout_csrf_integration_probe();

		expect($_SESSION[SESS_MESSAGES])->toHaveKey('lockout')
			->and($_SESSION[SESS_MESSAGES]['lockout']['level'])->toBe(MESSAGE_LEVEL_INFO)
			->and(read_config_option('cacti_lockout_status', true))->toBe('');

		// A matching 'expected' locks Cacti; verify the JSON payload actually
		// persisted to the real database, not just that a message was raised.
		$_SESSION                  = [];
		$_SESSION[SESS_USER_ID]    = '1';
		$_REQUEST['expected']      = 'unlocked';

		lockout_csrf_integration_probe();

		$locked = read_config_option('cacti_lockout_status', true);

		expect($locked)->not->toBe('');

		$decoded = json_decode((string) $locked, true);

		expect($decoded)->toBeArray()->and($decoded)->toHaveKey('time');

		// Unlocking (matching 'expected' = 'locked') clears the row back out.
		$_SESSION               = [];
		$_SESSION[SESS_USER_ID] = '1';
		$_REQUEST['expected']   = 'locked';

		lockout_csrf_integration_probe();

		expect(read_config_option('cacti_lockout_status', true))->toBe('');
	} finally {
		// Leave a developer's local database exactly as found.
		set_config_option('cacti_lockout_status', (string) $original);
	}
});
