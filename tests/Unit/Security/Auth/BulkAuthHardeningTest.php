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

namespace CactiBulkAuthHardeningTest;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 4);

$GLOBALS['bulk_session_rows'] = array();
$GLOBALS['bulk_session_sql']  = array();

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function db_fetch_row_prepared($sql, $params = array()) {
	$GLOBALS['bulk_session_sql'][] = array($sql, $params);
	$id = isset($params[0]) ? $params[0] : '';

	return isset($GLOBALS['bulk_session_rows'][$id]) ? $GLOBALS['bulk_session_rows'][$id] : array();
}

function db_execute_prepared($sql, $params = array()) {
	$GLOBALS['bulk_session_sql'][] = array($sql, $params);

	if (stripos($sql, 'DELETE FROM sessions') !== false && isset($params[0])) {
		unset($GLOBALS['bulk_session_rows'][$params[0]]);
	} elseif (stripos($sql, 'UPDATE IGNORE sessions') !== false && isset($params[1]) && isset($GLOBALS['bulk_session_rows'][$params[1]])) {
		$GLOBALS['bulk_session_rows'][$params[1]]['access'] = $params[0];
	}

	return true;
}

if (!function_exists(__NAMESPACE__ . '\\cacti_db_session_read')) {
	\cacti_fixture_source_function($root . '/include/session.php', 'cacti_db_session_read', __NAMESPACE__);
}

beforeEach(function () {
	$GLOBALS['bulk_session_rows'] = array();
	$GLOBALS['bulk_session_sql']  = array();
});

test('cacti_db_session_read returns active session data and refreshes access timestamp', function () {
	$old_access = time() - 30;
	$GLOBALS['bulk_session_rows']['active-sid'] = array(
		'data'   => 'sess_user_id|i:1;',
		'access' => $old_access,
	);

	$result = cacti_db_session_read('active-sid');

	expect($result)->toBe('sess_user_id|i:1;')
		->and($GLOBALS['bulk_session_rows']['active-sid']['access'])->toBeGreaterThanOrEqual($old_access);
});

test('cacti_db_session_read deletes and rejects expired sessions past session.gc_maxlifetime', function () {
	$maxlifetime = max(1, (int) ini_get('session.gc_maxlifetime'));
	$GLOBALS['bulk_session_rows']['expired-sid'] = array(
		'data'   => 'sess_user_id|i:1;',
		'access' => time() - $maxlifetime - 120,
	);

	$result = cacti_db_session_read('expired-sid');

	expect($result)->toBe('')
		->and(isset($GLOBALS['bulk_session_rows']['expired-sid']))->toBeFalse();
});

test('cacti_db_session_read returns empty string for unknown session IDs', function () {
	expect(cacti_db_session_read('missing-sid'))->toBe('');
});

test('include/session.php checks sess_user_id spelling on write fallback', function () use ($root) {
	$source = file_get_contents($root . '/include/session.php');

	expect($source)->toContain("strpos(\$data, 'sess_user_id') !== false")
		->and($source)->not->toContain("strpos(\$data, 'ses_user_id')");
});

test('graph_image.php checks local graph permissions and forwards effective_user to remote_agent', function () use ($root) {
	$source = file_get_contents($root . '/graph_image.php');

	expect($source)->toContain("!is_graph_allowed(get_request_var('local_graph_id'), \$_SESSION['sess_user_id'])")
		->and($source)->toContain("\$graph_data_array['effective_user'] = \$_SESSION['sess_user_id'];")
		->and($source)->toContain("strpos(\$output, 'image = ')");
});

test('include/csrf.php explicitly disables IP-based CSRF token fallback', function () use ($root) {
	$source = file_get_contents($root . '/include/csrf.php');

	expect($source)->toContain("csrf_conf('allow-ip', false);");
});

test('poller_reports.php skips and disables reports for missing, disabled, locked, or unauthorized owners', function () use ($root) {
	$source = file_get_contents($root . '/poller_reports.php');

	expect($source)->toContain("\$current_user['enabled'] != 'on'")
		->and($source)->toContain("\$current_user['locked'] == 'on'")
		->and($source)->toContain("!is_realm_allowed(21, \$report['user_id']) && !is_realm_allowed(22, \$report['user_id'])")
		->and($source)->toContain("reports_html_report_disable(\$report['id']);");
});
