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
 * Probe for AuthSystemRegressionIntegrationTest. Run by that test through
 * cacti_test_isolated_probe(); prints a JSON verdict on stdout.
 *
 * The db_* fakes below carry the names lib/database.php declares, so they
 * must not exist in the PHPUnit process. Keeping them here means the
 * Integration suite can load lib/database.php from any number of sibling
 * files without colliding with this test.
 */

$root = dirname(__DIR__, 3);

define('CACTI_PATH_INCLUDE', $root . '/include');

function cacti_sizeof($array) {
	return ($array === false || !is_array($array)) ? 0 : sizeof($array);
}

function __($text, ...$args) {
	return vsprintf($text, $args);
}

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
		$cache = $GLOBALS['auth_integration_cache'] ?? [];

		foreach ($cache as $row) {
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

switch ($argv[1] ?? '') {
	case 'cookie-authorization':
		$GLOBALS['auth_integration_config'] = ['guest_user' => 0];
		$GLOBALS['auth_integration_realms'] = 0;
		$GLOBALS['auth_integration_groups'] = [];

		$verdict = [
			'disabled' => auth_cookie_user_currently_allowed([
				'id'           => 10,
				'username'     => 'disabled',
				'enabled'      => '',
				'show_tree'    => '',
				'show_list'    => '',
				'show_preview' => '',
			]),
			'noaccess' => auth_cookie_user_currently_allowed([
				'id'           => 11,
				'username'     => 'noaccess',
				'enabled'      => 'on',
				'show_tree'    => '',
				'show_list'    => '',
				'show_preview' => '',
			]),
		];

		$GLOBALS['auth_integration_realms'] = 1;

		$verdict['allowed'] = auth_cookie_user_currently_allowed([
			'id'           => 12,
			'username'     => 'allowed',
			'enabled'      => 'on',
			'show_tree'    => '',
			'show_list'    => '',
			'show_preview' => '',
		]);

		break;

	case 'cookie-token-check':
		$GLOBALS['auth_integration_config'] = [
			'auth_cache_enabled' => 'on',
			'guest_user'         => 0,
		];
		$GLOBALS['auth_integration_realms'] = 1;
		$GLOBALS['auth_integration_groups'] = [];
		$GLOBALS['auth_integration_users']  = [
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
		$GLOBALS['auth_integration_cache'] = [
			[
				'user_id' => 42,
				'token'   => hash('sha512', 'valid-token', false),
			],
		];

		$GLOBALS['auth_integration_executed'] = [];
		$_COOKIE['cacti_remembers']           = '42,-1,forged-token';

		$verdict = [
			'forged' => [
				'result'   => check_auth_cookie(),
				'executed' => $GLOBALS['auth_integration_executed'],
			],
		];

		$GLOBALS['auth_integration_executed'] = [];
		$_COOKIE['cacti_remembers']           = '42,-1,valid-token';

		$verdict['valid'] = [
			'result'   => check_auth_cookie(),
			'executed' => $GLOBALS['auth_integration_executed'],
		];

		break;

	default:
		fwrite(STDERR, 'unknown scenario "' . ($argv[1] ?? '') . '"' . PHP_EOL);

		exit(2);
}

echo json_encode($verdict);
