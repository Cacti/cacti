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

namespace AuthCachePermissionInvalidationTest;

if (!defined('SESS_USER_ID')) {
	define('SESS_USER_ID', 'sess_user_id');
}

if (!defined('SESS_USER_REALMS')) {
	define('SESS_USER_REALMS', 'sess_user_realms');
}

if (!defined('OPTIONS_USER')) {
	define('OPTIONS_USER', 'options_user');
}

if (!defined('OPTIONS_WEB')) {
	define('OPTIONS_WEB', 'options_web');
}

if (!defined('SESS_AUTH_NAMES')) {
	define('SESS_AUTH_NAMES', 'sess_auth_names');
}

$GLOBALS['auth_cache_queries']       = [];
$GLOBALS['auth_cache_session_kills'] = [];
$GLOBALS['auth_cache_group_users']   = [];

/**
 * Returns deterministic group membership rows.
 *
 * @param string $sql    Prepared SQL text.
 * @param array  $params Bound query parameters.
 *
 * @return array Group membership rows.
 */
if (!function_exists(__NAMESPACE__ . '\\db_fetch_assoc_prepared') && !function_exists('\\db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared(string $sql, array $params = []) : array {
		return $GLOBALS['auth_cache_group_users'];
	}
}

/**
 * Rekeys group membership rows for the extracted production helper.
 *
 * @param array  $rows      Membership rows.
 * @param string $key       Source key.
 * @param string $value_key Value key.
 *
 * @return array Rekeyed user IDs.
 */
function array_rekey(array $rows, string $key, string $value_key) : array {
	$result = [];

	foreach ($rows as $row) {
		$result[$row[$key]] = $row[$value_key];
	}

	return $result;
}

/**
 * Returns the number of group members.
 *
 * @param mixed $value Value to count.
 *
 * @return int Number of group members.
 */
if (!function_exists(__NAMESPACE__ . '\\cacti_sizeof') && !function_exists('\\cacti_sizeof')) {
	function cacti_sizeof(mixed $value) : int {
		return is_countable($value) ? count($value) : 0;
	}
}

/**
 * Captures prepared permission-reset queries.
 *
 * @param string $sql    Prepared SQL text.
 * @param array  $params Bound query parameters.
 *
 * @return bool Always true for the unit test.
 */
if (!function_exists(__NAMESPACE__ . '\\db_execute_prepared') && !function_exists('\\db_execute_prepared')) {
	function db_execute_prepared(string $sql, array $params = []) : bool {
		$GLOBALS['auth_cache_queries'][] = [$sql, $params];

		return true;
	}
}

/**
 * Captures session cache invalidation requests.
 *
 * @param string $name Session variable name.
 *
 * @return void
 */
if (!function_exists(__NAMESPACE__ . '\\kill_session_var') && !function_exists('\\kill_session_var')) {
	function kill_session_var(string $name) : void {
		$GLOBALS['auth_cache_session_kills'][] = $name;
	}
}

$source = file_get_contents(CACTI_PATH_LIBRARY . '/auth.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read lib/auth.php for the permission invalidation test.');
}

if (preg_match('/function reset_user_perms\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract reset_user_perms() for the permission invalidation test.');
}

$function = str_replace('function reset_user_perms(', 'function reset_user_perms_under_test(', $matches[0]);
eval('namespace AuthCachePermissionInvalidationTest;' . $function);

if (preg_match('/function reset_group_perms\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract reset_group_perms() for the permission invalidation test.');
}

$function = str_replace('function reset_group_perms(', 'function reset_group_perms_under_test(', $matches[0]);
eval('namespace AuthCachePermissionInvalidationTest;' . $function);

beforeEach(function () : void {
	$GLOBALS['auth_cache_queries']       = [];
	$GLOBALS['auth_cache_session_kills'] = [];
	$GLOBALS['auth_cache_group_users']   = [];
	$_SESSION[SESS_USER_ID]              = 7;
});

test('permission resets invalidate persistent authentication tokens', function () : void {
	reset_user_perms_under_test(42);

	expect($GLOBALS['auth_cache_queries'])->toHaveCount(2)
		->and($GLOBALS['auth_cache_queries'][0][0])->toBe('DELETE FROM user_auth_cache WHERE user_id = ?')
		->and($GLOBALS['auth_cache_queries'][0][1])->toBe([42])
		->and($GLOBALS['auth_cache_queries'][1][0])->toContain('UPDATE user_auth')
		->and($GLOBALS['auth_cache_queries'][1][1])->toBe([42])
		->and($GLOBALS['auth_cache_session_kills'])->toBe([]);
});

test('permission resets still clear the current user session caches', function () : void {
	reset_user_perms_under_test(7);

	expect($GLOBALS['auth_cache_session_kills'])->toBe([
		SESS_USER_REALMS,
		OPTIONS_USER,
		OPTIONS_WEB,
		SESS_AUTH_NAMES
	]);
});

test('group permission resets invalidate every member token', function () : void {
	$GLOBALS['auth_cache_group_users'] = [['user_id' => 10], ['user_id' => 11]];

	reset_group_perms_under_test(5);

	expect($GLOBALS['auth_cache_queries'])->toHaveCount(2)
		->and($GLOBALS['auth_cache_queries'][0][0])->toContain('DELETE FROM user_auth_cache')
		->and($GLOBALS['auth_cache_queries'][0][0])->toContain('user_id IN (?,?)')
		->and($GLOBALS['auth_cache_queries'][0][1])->toBe([10, 11])
		->and($GLOBALS['auth_cache_queries'][1][0])->toContain('UPDATE user_auth')
		->and($GLOBALS['auth_cache_queries'][1][0])->toContain('id IN (?,?)')
		->and($GLOBALS['auth_cache_queries'][1][1])->toBe([10, 11]);
});

test('empty groups do not issue invalidation queries', function () : void {
	reset_group_perms_under_test(5);

	expect($GLOBALS['auth_cache_queries'])->toBe([]);
});
