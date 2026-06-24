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

$root = dirname(__DIR__, 2);

if (!defined('CACTI_PATH_INCLUDE')) {
	define('CACTI_PATH_INCLUDE', $root . '/include');
}

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($array) {
		return ($array === false || !is_array($array)) ? 0 : sizeof($array);
	}
}

if (!function_exists('__')) {
	function __($text, ...$args) {
		return vsprintf($text, $args);
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option($name) {
		return $GLOBALS['auth_integration_config'][$name] ?? '';
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared($sql, $params = []) {
		if (str_contains($sql, 'FROM user_auth_realm')) {
			return $GLOBALS['auth_integration_realms'] ?? 0;
		}

		if (str_contains($sql, 'FROM user_auth_group_realm')) {
			return $GLOBALS['auth_integration_group_realms'] ?? 0;
		}

		if (str_contains($sql, 'FROM user_auth_cache')) {
			$cache = $GLOBALS['auth_integration_cache'] ?? [];

			foreach ($cache as $row) {
				if ($row['user_id'] == $params[0] && $row['token'] == $params[1]) {
					return $row['user_id'];
				}
			}
		}

		return 0;
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared($sql, $params = []) {
		return $GLOBALS['auth_integration_groups'] ?? [];
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared($sql, $params = []) {
		return $GLOBALS['auth_integration_users'][$params[0]] ?? [];
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = []) {
		$GLOBALS['auth_integration_executed'][] = [
			'sql'    => $sql,
			'params' => $params,
		];

		return true;
	}
}

if (!function_exists('db_table_exists')) {
	function db_table_exists($table) {
		return $table == 'user_auth_cache';
	}
}

if (!function_exists('get_guest_account')) {
	function get_guest_account() {
		return (int) read_config_option('guest_user');
	}
}

require_once $root . '/lib/auth.php';

test('auth subsystem regression coverage spans cookie login, 2fa, reset tokens, basic auth, and profile mutations', function () use ($root) {
	$files = [
		'include/auth.php'        => file_get_contents($root . '/include/auth.php'),
		'auth_2fa.php'           => file_get_contents($root . '/auth_2fa.php'),
		'auth_resetpassword.php' => file_get_contents($root . '/auth_resetpassword.php'),
		'auth_profile.php'       => file_get_contents($root . '/auth_profile.php'),
		'lib/auth.php'           => file_get_contents($root . '/lib/auth.php'),
		'lib/functions.php'      => file_get_contents($root . '/lib/functions.php'),
	];

	foreach ($files as $path => $contents) {
		expect($contents)->not->toBeFalse("Unable to read $path");
	}

	expect($files['include/auth.php'])->toContain('$cookie_user = check_auth_cookie();')
		->and($files['include/auth.php'])->toContain('if (empty($_SESSION[SESS_USER_2FA])')
		->and($files['auth_2fa.php'])->toContain("read_config_option('secpass_2fatime')")
		->and($files['auth_resetpassword.php'])->toContain('AND expiry > NOW()')
		->and($files['lib/functions.php'])->toContain("'cacti_remembers'")
		->and($files['auth_profile.php'])->toContain('auth_profile_require_post();')
		->and($files['auth_profile.php'])->toContain('__csrf_magic: csrfMagicToken')
		->and($files['include/auth.php'])->toContain('auth_user_has_access($current_user)')
		->and($files['lib/auth.php'])->toContain('auth_cookie_user_currently_allowed($user_info)')
		->and($files['lib/auth.php'])->not->toContain('$result[\'secret\']');
});

test('remember-me cookie authorization rejects disabled and permissionless accounts at runtime', function () {
	$GLOBALS['auth_integration_config'] = ['guest_user' => 0];
	$GLOBALS['auth_integration_realms'] = 0;
	$GLOBALS['auth_integration_groups'] = [];

	expect(auth_cookie_user_currently_allowed([
		'id'           => 10,
		'username'     => 'disabled',
		'enabled'      => '',
		'show_tree'    => '',
		'show_list'    => '',
		'show_preview' => '',
	]))->toBeFalse()
		->and(auth_cookie_user_currently_allowed([
			'id'           => 11,
			'username'     => 'noaccess',
			'enabled'      => 'on',
			'show_tree'    => '',
			'show_list'    => '',
			'show_preview' => '',
		]))->toBeFalse();

	$GLOBALS['auth_integration_realms'] = 1;

	expect(auth_cookie_user_currently_allowed([
		'id'           => 12,
		'username'     => 'allowed',
		'enabled'      => 'on',
		'show_tree'    => '',
		'show_list'    => '',
		'show_preview' => '',
	]))->toBeTrue();
});

test('remember-me cookie authorization verifies token before deleting cache rows', function () {
	$GLOBALS['auth_integration_config'] = [
		'auth_cache_enabled' => 'on',
		'guest_user'         => 0,
	];
	$GLOBALS['auth_integration_realms']  = 1;
	$GLOBALS['auth_integration_groups']  = [];
	$GLOBALS['auth_integration_users']   = [
		42 => [
			'id'           => 42,
			'username'     => 'disabled',
			'realm'        => 0,
			'enabled'      => '',
			'show_tree'    => '',
			'show_list'    => '',
			'show_preview' => '',
		],
	];
	$GLOBALS['auth_integration_cache']    = [
		[
			'user_id' => 42,
			'token'   => hash('sha512', 'valid-token', false),
		],
	];
	$GLOBALS['auth_integration_executed'] = [];

	$_COOKIE['cacti_remembers'] = '42,-1,forged-token';

	expect(check_auth_cookie())->toBeFalse()
		->and($GLOBALS['auth_integration_executed'])->toBeEmpty();

	$_COOKIE['cacti_remembers'] = '42,-1,valid-token';

	expect(check_auth_cookie())->toBeFalse()
		->and($GLOBALS['auth_integration_executed'])->toHaveCount(1)
		->and($GLOBALS['auth_integration_executed'][0]['sql'])->toContain('DELETE FROM user_auth_cache')
		->and($GLOBALS['auth_integration_executed'][0]['params'])->toBe([42]);

	unset($_COOKIE['cacti_remembers']);
});
