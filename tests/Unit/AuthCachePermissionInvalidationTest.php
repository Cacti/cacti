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

$GLOBALS['auth_cache_queries']       = array();
$GLOBALS['auth_cache_session_kills'] = array();
$GLOBALS['auth_cache_group_users']   = array();

/**
 * Returns deterministic group membership rows.
 *
 * @param string $sql    Prepared SQL text.
 * @param array  $params Bound query parameters.
 *
 * @return array Group membership rows.
 */
function db_fetch_assoc_prepared($sql, $params = array()) {
	return $GLOBALS['auth_cache_group_users'];
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
function array_rekey($rows, $key, $value_key) {
	$result = array();

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
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

/**
 * Captures prepared permission-reset queries.
 *
 * @param string $sql    Prepared SQL text.
 * @param array  $params Bound query parameters.
 *
 * @return bool Always true for the unit test.
 */
function db_execute_prepared($sql, $params = array()) {
	$GLOBALS['auth_cache_queries'][] = array($sql, $params);

	return true;
}

/**
 * Captures session cache invalidation requests.
 *
 * @param string $name Session variable name.
 *
 * @return void
 */
function kill_session_var($name) {
	$GLOBALS['auth_cache_session_kills'][] = $name;
}

$source = file_get_contents(dirname(__DIR__, 2) . '/lib/auth.php');

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

beforeEach(function () {
	$GLOBALS['auth_cache_queries']       = array();
	$GLOBALS['auth_cache_session_kills'] = array();
	$GLOBALS['auth_cache_group_users']   = array();
	$_SESSION['sess_user_id']            = 7;
});

test('permission resets invalidate persistent authentication tokens', function () {
	reset_user_perms_under_test(42);

	expect($GLOBALS['auth_cache_queries'])->toHaveCount(2)
		->and($GLOBALS['auth_cache_queries'][0][0])->toBe('DELETE FROM user_auth_cache WHERE user_id = ?')
		->and($GLOBALS['auth_cache_queries'][0][1])->toBe(array(42))
		->and($GLOBALS['auth_cache_queries'][1][0])->toContain('UPDATE user_auth')
		->and($GLOBALS['auth_cache_queries'][1][1])->toBe(array(42))
		->and($GLOBALS['auth_cache_session_kills'])->toBe(array());
});

test('permission resets still clear the current user session caches', function () {
	reset_user_perms_under_test(7);

	expect($GLOBALS['auth_cache_session_kills'])->toBe(array(
		'sess_user_realms',
		'sess_user_config_array',
		'sess_config_array',
		'sess_auth_names'
	));
});

test('group permission resets invalidate every member token', function () {
	$GLOBALS['auth_cache_group_users'] = array(array('user_id' => 10), array('user_id' => 11));

	reset_group_perms_under_test(5);

	expect($GLOBALS['auth_cache_queries'])->toHaveCount(2)
		->and($GLOBALS['auth_cache_queries'][0][0])->toContain('DELETE FROM user_auth_cache')
		->and($GLOBALS['auth_cache_queries'][0][0])->toContain('user_id IN (?,?)')
		->and($GLOBALS['auth_cache_queries'][0][1])->toBe(array(10, 11))
		->and($GLOBALS['auth_cache_queries'][1][0])->toContain('UPDATE user_auth')
		->and($GLOBALS['auth_cache_queries'][1][0])->toContain('id IN (?,?)')
		->and($GLOBALS['auth_cache_queries'][1][1])->toBe(array(10, 11));
});

test('empty groups do not issue invalidation queries', function () {
	reset_group_perms_under_test(5);

	expect($GLOBALS['auth_cache_queries'])->toBe(array());
});
