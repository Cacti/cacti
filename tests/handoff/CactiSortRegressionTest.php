<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/include/global.php';

beforeEach(function () {
	if (session_status() === PHP_SESSION_NONE) {
		@session_start();
	}
	$_SESSION = [];
});

test('Regression: Standard User Admin sorting works', function () {
	// 1. Simulate rendering the table header in user_admin.php
	$_SERVER['SCRIPT_NAME'] = '/user_admin.php';
	$_SERVER['PHP_SELF'] = '/user_admin.php';
	
	$display_text = array(
		'username'  => array(__('User Name'), 'ASC'),
		'full_name' => array(__('Full Name'), 'ASC'),
		'id'        => array(__('User ID'), 'ASC')
	);
	
	// This call should register the columns in the session
	html_header_sort_checkbox($display_text, 'username', 'ASC', false);
	
	// 2. Verify columns are registered
	$page = '0_user_admin';
	expect($_SESSION['valid_sort_columns'])->toHaveKey($page);
	expect($_SESSION['valid_sort_columns'][$page])->toContain('username');
	expect($_SESSION['valid_sort_columns'][$page])->toContain('full_name');
	
	// 3. Simulate a sort request
	set_request_var('sort_column', 'full_name');
	set_request_var('sort_direction', 'DESC');
	
	update_order_string();
	$sql_order = get_order_string();
	
	// 4. Verify standard sorting works
	expect($sql_order)->toBe('ORDER BY `full_name` DESC');
});

test('Regression: Host sorting handles complex column names', function () {
	$_SERVER['SCRIPT_NAME'] = '/host.php';
	$_SERVER['PHP_SELF'] = '/host.php';
	
	$display_text = array(
		'h.description' => array(__('Description'), 'ASC'),
		'h.hostname'    => array(__('Hostname'), 'ASC'),
		'ht.name'       => array(__('Template'), 'ASC')
	);
	
	html_header_sort($display_text, 'h.description', 'ASC');
	
	$page = '0_host';
	expect($_SESSION['valid_sort_columns'])->toHaveKey($page);
	
	// Test dot notation in column names
	set_request_var('sort_column', 'h.hostname');
	set_request_var('sort_direction', 'ASC');
	
	update_order_string();
	$sql_order = get_order_string();
	
	// update_order_string handles hostname specifically with INET_ATON
	expect($sql_order)->toContain('INET_ATON(h.hostname)');
	expect($sql_order)->toContain('ASC');
});

test('Regression: Sorting defaults to ASC for invalid directions', function () {
	$_SERVER['SCRIPT_NAME'] = '/user_admin.php';
	
	$display_text = ['username' => ['User', 'ASC']];
	html_header_sort($display_text, 'username', 'ASC');
	
	set_request_var('sort_column', 'username');
	set_request_var('sort_direction', 'invalid');
	
	update_order_string();
	$sql_order = get_order_string();
	
	expect($sql_order)->toContain('ASC');
	expect($sql_order)->not->toContain('invalid');
});
