<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__, 2) . '/lib/html_utility.php';

test('sanitize_sql_column() allows valid columns', function () {
	expect(sanitize_sql_column('hostname'))->toBe('hostname');
	expect(sanitize_sql_column('host.id'))->toBe('host.id');
	expect(sanitize_sql_column('ua.full_name'))->toBe('ua.full_name');
});

test('sanitize_sql_column() strips malicious characters', function () {
	expect(sanitize_sql_column("hostname; DROP TABLE users"))->toBe('hostnameDROPTABLEusers');
	expect(sanitize_sql_column("id` OR 1=1 --"))->toBe('idOR11');
	expect(sanitize_sql_column("user_auth.locked"))->toBe('user_auth.locked');
});

test('sanitize_sql_column() handles non-string inputs safely', function () {
	expect(sanitize_sql_column(['a', 'b']))->toBe('Array'); // PHP string conversion behavior
	expect(sanitize_sql_column(null))->toBe('');
	expect(sanitize_sql_column(123))->toBe('123');
});

test('update_order_string() enforces ASC/DESC direction', function () {
	// Initialize session if not set
	if (session_status() === PHP_SESSION_NONE) {
		@session_start();
	}

	set_request_var('sort_column', 'hostname');
	set_request_var('sort_direction', 'ASC; --');
	update_order_string();

	$order = get_order_string();
	expect($order)->toContain('ASC');
	expect($order)->not->toContain(';');
	expect($order)->not->toContain('--');
});

test('update_order_string() handles multi-column sorting', function () {
	if (session_status() === PHP_SESSION_NONE) {
		@session_start();
	}

	$page = get_order_string_page();
	$_SESSION['valid_sort_columns'][$page] = ['col1', 'col2'];

	// Simulate multiple columns in sort_data
	$_SESSION['sort_data'][$page] = [
		'col1' => 'ASC',
		'col2' => 'DESC'
	];
	
	update_order_string(true); // true for inplace update

	$order = get_order_string();
	expect($order)->toContain('`col1` ASC');
	expect($order)->toContain('`col2` DESC');
});

test('update_order_string() uses session allowlist', function () {
	if (session_status() === PHP_SESSION_NONE) {
		@session_start();
	}

	$page = get_order_string_page();
	$_SESSION['valid_sort_columns'][$page] = ['hostname', 'description'];

	set_request_var('sort_column', 'secret_column');
	set_request_var('sort_direction', 'ASC');
	update_order_string();

	$order = get_order_string();
	expect($order)->not->toContain('secret_column');
	
	set_request_var('sort_column', 'description');
	update_order_string();
	$order = get_order_string();
	expect($order)->toContain('description');
});

test('get_order_string() fallback sanitization works', function () {
	$_SESSION = [];
	set_request_var('sort_column', 'dangerous` column');
	set_request_var('sort_direction', 'ASC');
	
	$order = get_order_string();
	expect($order)->not->toContain('`');
	expect($order)->toContain('dangerouscolumn');
});
