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

		return 0;
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared($sql, $params = []) {
		return $GLOBALS['auth_integration_groups'] ?? [];
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
		->and($files['auth_resetpassword.php'])->toContain('AND password_change = "on"')
		->and($files['lib/functions.php'])->toContain("'cacti_remembers'")
		->and($files['auth_profile.php'])->toContain('auth_profile_require_post();')
		->and($files['auth_profile.php'])->toContain('__csrf_magic: csrfMagicToken')
		->and($files['include/auth.php'])->toContain('auth_user_has_access($current_user)')
		->and($files['lib/auth.php'])->toContain('auth_cookie_user_currently_allowed($user_info)');
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
