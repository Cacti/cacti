<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 4) . '/lib/html_utility.php';

beforeEach(function () {
	$this->sortState = [$_SESSION ?? array(), $_REQUEST, $GLOBALS['_CACTI_REQUEST'] ?? array(), $_SERVER['SCRIPT_NAME'] ?? ''];
	$_SESSION = $_REQUEST = $GLOBALS['_CACTI_REQUEST'] = array();
	$_SERVER['SCRIPT_NAME'] = '/data_debug.php';
});

afterEach(function () {
	[$_SESSION, $_REQUEST, $GLOBALS['_CACTI_REQUEST'], $_SERVER['SCRIPT_NAME']] = $this->sortState;
});

test('sort validation rejects credential fields without a page allowlist', function (string $column) {
	expect(validate_sort_column($column, 'page'))->toBe('');
})->with(['password', 'u.password', 'password_history', 'u.password_history', 'dbpass', 'snmp_community', 'snmp_priv_passphrase', 'tfa_secret']);

test('sort validation honors an explicit page allowlist', function () {
	$_SESSION['valid_sort_columns']['page'] = array('description', 'enabled');
	expect(validate_sort_column('description', 'page'))->toBe('description')
		->and(validate_sort_column('username', 'page'))->toBe('');
});

test('request sorts use the production policy and preserve valid ordering', function (string $column, string $expected) {
	$_REQUEST = array('sort_column' => $column, 'sort_direction' => 'DESC');
	expect(get_order_string())->toBe($expected);
})->with([
	['u.password', ''],
	['password_history', ''],
	['description', 'ORDER BY `description` DESC'],
	['u.name', 'ORDER BY `u`.`name` DESC'],
]);

test('persisted multi-column sorts are checked against the current policy', function () {
	$page = get_order_string_page(false);
	$_SESSION['sort_string'][$page] = 'ORDER BY password ASC, description DESC';
	$_SESSION['sort_data'][$page] = array('password' => 'ASC', 'description' => 'DESC');
	expect(get_order_string())->toBe('ORDER BY `description` DESC');
});

test('a changed allowlist and orphaned cached sort fail closed', function () {
	$page = get_order_string_page(false);
	$_SESSION['sort_string'][$page] = 'ORDER BY username ASC';
	$_SESSION['sort_data'][$page] = array('username' => 'ASC');
	$_SESSION['valid_sort_columns'][$page] = array('description');
	expect(get_order_string())->toBe('');

	$page = get_order_string_page(false);
	$_SESSION['sort_string'][$page] = 'ORDER BY password ASC';
	expect(get_order_string())->toBe('');
});
