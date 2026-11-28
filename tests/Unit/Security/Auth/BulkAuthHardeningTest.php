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

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

$root = dirname(__DIR__, 4);

test('cacti_db_session_read enforces gc_maxlifetime and cacti_db_session_write uses sess_user_id', function () {
	$res = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/BulkAuthHardeningProbe.php');

	expect($res['active_read'])->toBe('sess_user_id|i:1;')
		->and($res['active_refreshed'])->toBeTrue()
		->and($res['expired_read'])->toBe('')
		->and($res['expired_deleted'])->toBeTrue()
		->and($res['missing_read'])->toBe('')
		->and($res['write_saved'])->toBeTrue();
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
