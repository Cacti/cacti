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
 * Subprocess fixture for AuthSystemRegressionIntegrationTest.php.
 *
 * check_auth_cookie() and auth_cookie_user_currently_allowed() call
 * db_execute_prepared()/db_fetch_row_prepared()/db_fetch_cell_prepared()/
 * db_fetch_assoc_prepared()/db_table_exists() with no explicit connection,
 * so exercising them means stubbing those global functions. PHP cannot
 * redeclare an already-defined function, and phpunit.xml now runs Unit and
 * Integration in a single process (#7497) -- once any Unit test that
 * requires lib/database.php (e.g. DbExecutePreparedTest) loads first, the
 * *real* db_* functions win the race for the rest of the process, and any
 * in-process stub defined afterwards is silently ignored.
 *
 * Running the fixtures here, in a fresh `php` process per scenario, means
 * these are always the first (and only) definitions of those names the
 * process ever sees, regardless of what pest's file discovery already
 * loaded in the parent process.
 *
 * Reads a JSON scenario from stdin (see runAuthCookieProbe() in the test
 * file for the shape) and writes {"results": [...]} to stdout, one entry
 * per "calls" item, each {"return": ..., "executed": [...]}.
 */

// Keep stdout limited to the JSON result line; deprecation notices from
// vendor code pulled in by lib/auth.php (e.g. GoogleAuthenticator) would
// otherwise land ahead of it and break the parent process's json_decode().
ini_set('display_errors', 'stderr');

$root = dirname(__DIR__, 2);

if (!defined('CACTI_PATH_INCLUDE')) {
	define('CACTI_PATH_INCLUDE', $root . '/include');
}

function cacti_sizeof($array) {
	return ($array === false || !is_array($array)) ? 0 : sizeof($array);
}

function __($text, ...$args) {
	return vsprintf($text, $args);
}

$GLOBALS['auth_integration_config']       = [];
$GLOBALS['auth_integration_realms']       = 0;
$GLOBALS['auth_integration_group_realms'] = 0;
$GLOBALS['auth_integration_groups']       = [];
$GLOBALS['auth_integration_users']        = [];
$GLOBALS['auth_integration_cache']        = [];
$GLOBALS['auth_integration_executed']     = [];

function read_config_option($name) {
	return $GLOBALS['auth_integration_config'][$name] ?? '';
}

function db_fetch_cell_prepared($sql, $params = []) {
	if (str_contains($sql, 'FROM user_auth_realm')) {
		return $GLOBALS['auth_integration_realms'] ?? 0;
	}

	if (str_contains($sql, 'FROM user_auth_group_realm')) {
		return $GLOBALS['auth_integration_group_realms'] ?? 0;
	}

	if (str_contains($sql, 'FROM user_auth_cache')) {
		foreach (($GLOBALS['auth_integration_cache'] ?? []) as $row) {
			if ($row['user_id'] == $params[0] && $row['token'] == $params[1]) {
				return $row['user_id'];
			}
		}
	}

	return 0;
}

function db_fetch_assoc_prepared($sql, $params = []) {
	return $GLOBALS['auth_integration_groups'] ?? [];
}

function db_fetch_row_prepared($sql, $params = []) {
	return $GLOBALS['auth_integration_users'][$params[0]] ?? [];
}

function db_execute_prepared($sql, $params = []) {
	$GLOBALS['auth_integration_executed'][] = [
		'sql'    => $sql,
		'params' => $params,
	];

	return true;
}

function db_table_exists($table) {
	return $table == 'user_auth_cache';
}

function get_guest_account() {
	return (int) read_config_option('guest_user');
}

require_once $root . '/lib/auth.php';

$scenario = json_decode(stream_get_contents(STDIN), true);

if (!is_array($scenario) || !isset($scenario['calls'])) {
	fwrite(STDERR, 'AuthCookieProbe: invalid scenario JSON on stdin');
	exit(1);
}

$GLOBALS['auth_integration_config']       = $scenario['config'] ?? [];
$GLOBALS['auth_integration_group_realms'] = $scenario['group_realms'] ?? 0;
$GLOBALS['auth_integration_groups']       = $scenario['groups'] ?? [];
$GLOBALS['auth_integration_users']        = $scenario['users'] ?? [];
$GLOBALS['auth_integration_cache']        = $scenario['cache'] ?? [];

$results = [];

foreach ($scenario['calls'] as $call) {
	$GLOBALS['auth_integration_realms']   = $call['realms'] ?? $scenario['realms'] ?? 0;
	$GLOBALS['auth_integration_executed'] = [];

	if ($call['type'] === 'auth_cookie_user_currently_allowed') {
		$return = auth_cookie_user_currently_allowed($call['user']);
	} elseif ($call['type'] === 'check_auth_cookie') {
		if (array_key_exists('cookie', $call)) {
			$_COOKIE['cacti_remembers'] = $call['cookie'];
		} else {
			unset($_COOKIE['cacti_remembers']);
		}

		$return = check_auth_cookie();
	} else {
		fwrite(STDERR, 'AuthCookieProbe: unknown call type ' . $call['type']);
		exit(1);
	}

	$results[] = [
		'return'   => $return,
		'executed' => $GLOBALS['auth_integration_executed'],
	];
}

print json_encode(['results' => $results]);
