<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

test('GHSA-274c-97hj-pv2v: import package flow enforces signature validation', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/import.php');

	expect($src)->toContain('if (!import_validate_signature($xmlfile))');
});

test('GHSA-6gr7-53g8-vchq: auth login redirect path no longer relies on referer substring trust', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/auth.php');

	expect($src)->toContain('function auth_login_redirect');
	expect($src)->toContain('parse_url');
	expect($src)->toContain('cacti_validate_redirect');
});

test('GHSA-84q3-92xc-c3pf: ORDER BY inputs pass through allowlist validation helper', function () {
	$src = file_get_contents(__DIR__ . '/../../utilities.php');

	expect($src)->toContain('cacti_validate_sort_column(');
});

test('GHSA-84q3-92xc-c3pf: user_group_admin ORDER BY inputs pass through allowlist validation helper', function () {
	$src = file_get_contents(__DIR__ . '/../../user_group_admin.php');

	expect($src)->toContain('cacti_validate_sort_column(');
});

test('GHSA-8522-5p3m-754c: script-server PHP binary path is shell-escaped before execution', function () {
	$src = file_get_contents(__DIR__ . '/../../cmd_realtime.php');

	expect($src)->toContain("cacti_escapeshellcmd(read_config_option('path_php_binary'))");
});

test('GHSA-fwmp-mq8j-4r8f: remote agent proc_open command escapes binary and script path', function () {
	$src = file_get_contents(__DIR__ . '/../../remote_agent.php');

	expect($src)->toContain("\$php_bin  = cacti_escapeshellcmd(read_config_option('path_php_binary'));");
	expect($src)->toContain("\$srv_path = cacti_escapeshellarg(\$config['base_path'] . '/script_server.php');");
});

test('GHSA-j696-m433-87qq: plugin/package extraction rejects stream wrappers', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/import.php');

	expect($src)->toContain("stream wrapper rejected");
	expect($src)->toContain("preg_match('#^[a-z][a-z0-9+.\\-]*://#i', \$name)");
});

test('GHSA-vp35-4h28-r883: package import file write path stays within base path', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/import.php');

	expect($src)->toContain("validate_relative_path_within(\$name, \$config['base_path'])");
	expect($src)->toContain("path traversal rejected");
});

test('graph_realtime nolegend filter is anchored to true/false', function () {
	$src = file_get_contents(__DIR__ . '/../../graph_realtime.php');

	expect($src)->toContain("'regexp' => '^(true|false)$'");
});

test('GHSA-pf37-v86f-5xwp: reports graph_name_regexp filter uses db_qstr_rlike helper', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/reports.php');

	expect($src)->toContain("db_qstr_rlike(\$item['graph_name_regexp'])");
});
