<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace AuthUserAccessQueryTest;

$GLOBALS['auth_access_results'] = [];
$GLOBALS['auth_access_queries'] = [];
$GLOBALS['auth_guest_user']     = 0;

/**
 * Return the next deterministic query result.
 *
 * @param string $sql    Prepared SQL text
 * @param array  $params Bound parameters
 *
 * @return mixed Queued scalar result
 */
function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	$GLOBALS['auth_access_queries'][] = [$sql, $params];

	return array_shift($GLOBALS['auth_access_results']);
}

/**
 * Return the configured guest account identifier.
 *
 * @param string $name Configuration option name
 *
 * @return int Guest account identifier
 */
if (!function_exists('read_config_option')) {
function read_config_option(string $name) : int {
	return $GLOBALS['auth_guest_user'];
}
}

$source = file_get_contents(dirname(__DIR__, 4) . '/lib/auth.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read lib/auth.php for the access query test.');
}

if (preg_match('/function auth_user_has_access\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract auth_user_has_access() for the access query test.');
}

$function = str_replace('function auth_user_has_access(', 'function auth_user_has_access_under_test(', $matches[0]);
eval('namespace AuthUserAccessQueryTest;' . $function);

beforeEach(function () {
	$GLOBALS['auth_access_results'] = [];
	$GLOBALS['auth_access_queries'] = [];
	$GLOBALS['auth_guest_user']     = 0;
});

function access_user(array $overrides = []) : array {
	return array_merge([
		'id'           => 42,
		'show_tree'    => '',
		'show_list'    => '',
		'show_preview' => '',
	], $overrides);
}

test('direct realm access returns after one indexed existence query', function () {
	$GLOBALS['auth_access_results'] = [1];

	expect(auth_user_has_access_under_test(access_user()))->toBeTrue()
		->and($GLOBALS['auth_access_queries'])->toHaveCount(1)
		->and($GLOBALS['auth_access_queries'][0][0])->toContain('SELECT EXISTS(')
		->and($GLOBALS['auth_access_queries'][0][1])->toBe([42]);
});

test('guest graph access returns without querying group memberships', function () {
	$GLOBALS['auth_access_results'] = [0];
	$GLOBALS['auth_guest_user']     = 1;

	expect(auth_user_has_access_under_test(access_user(['show_tree' => 'on'])))->toBeTrue()
		->and($GLOBALS['auth_access_queries'])->toHaveCount(1);
});

test('group realms and graph flags are resolved in one query', function () {
	$GLOBALS['auth_access_results'] = [0, 1];
	$GLOBALS['auth_guest_user']     = 1;

	expect(auth_user_has_access_under_test(access_user()))->toBeTrue()
		->and($GLOBALS['auth_access_queries'])->toHaveCount(2)
		->and($GLOBALS['auth_access_queries'][1][0])->toContain('user_auth_group_members AS uagm')
		->toContain('user_auth_group_realm AS uagr')
		->toContain("uag.enabled = 'on'")
		->toContain('uag.show_tree')
		->and($GLOBALS['auth_access_queries'][1][1])->toBe([42, 1]);
});

test('a user without direct or group access is rejected with two queries', function () {
	$GLOBALS['auth_access_results'] = [0, 0];

	expect(auth_user_has_access_under_test(access_user()))->toBeFalse()
		->and($GLOBALS['auth_access_queries'])->toHaveCount(2)
		->and($GLOBALS['auth_access_queries'][1][1])->toBe([42, 0]);
});
