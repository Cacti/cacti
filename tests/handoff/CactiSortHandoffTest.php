<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/include/global.php';

test('Data Handoff: sort parameters are neutralized before SQL generation', function () {
	// Simulate a request to user_admin.php
	$_SERVER['SCRIPT_NAME'] = '/user_admin.php';
	$_SERVER['PHP_SELF'] = '/user_admin.php';
	
	if (session_status() === PHP_SESSION_NONE) {
		@session_start();
	}

	$_SESSION = [];
	
	// Page ID used by get_order_string_page()
	// static $page_count = 0; 0_user_admin
	$page = '0_user_admin';
	$_SESSION['valid_sort_columns'][$page] = ['username', 'full_name'];
	
	set_request_var('sort_column', 'username` OR 1=1');
	set_request_var('sort_direction', 'ASC --');
	
	update_order_string();
	$sql_order = get_order_string();
	
	expect($sql_order)->not->toContain('OR 1=1');
	expect($sql_order)->not->toContain('--');
	expect($sql_order)->toContain('username');
	expect($sql_order)->toContain('ASC');
});
