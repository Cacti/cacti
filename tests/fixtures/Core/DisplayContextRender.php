<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

chdir(dirname(__DIR__, 3));
require_once './tests/Helpers/RequestCoverage.php';
require_once './tests/Helpers/SourceSlice.php';
$_SERVER['PHP_SELF'] = '/graph_view.php';
$_SERVER['SCRIPT_NAME'] = '/graph_view.php';
$_SERVER['REQUEST_URI'] = '/graph_view.php';
$_SERVER['SERVER_NAME'] = 'localhost';
require './include/global.php';
require_once './lib/html_tree.php';
require_once './lib/api_tree.php';
require_once './lib/installer.php';
$_SESSION['sess_user_id'] = 1;
$_SESSION['sess_current_timespan'] = GT_LAST_DAY;
$_SESSION['sess_current_timeshift'] = 86400;
$_SESSION['sess_realtime_window'] = 1800;
$_SESSION['sess_realtime_dsstep'] = 5;

function check_render($html, $text) {
	if (strpos($html, $text) === false) {
		throw new RuntimeException('Expected rendered output to contain ' . $text);
	}
}

// Roll back the temporary profiles when the render is complete or the connection closes.
db_execute('START TRANSACTION');
db_execute_prepared('INSERT INTO automation_snmp (name) VALUES (?)', array('Lab & office'));
$group_id = db_fetch_insert_id();
db_execute_prepared('INSERT INTO automation_snmp_items
	(snmp_id, snmp_version, snmp_community, snmp_username, snmp_auth_protocol, snmp_priv_protocol)
	VALUES (?, 3, ?, ?, ?, ?)', array($group_id, '', 'Lab & field', 'A&B', 'P&Q'));
$item_id = db_fetch_insert_id();

set_request_var('id', $group_id);
set_request_var('item_id', $item_id);
cacti_fixture_source_function('automation_snmp.php', 'automation_snmp_edit', '');
cacti_fixture_source_function('automation_snmp.php', 'automation_snmp_item_remove_confirm', '');
ob_start();
automation_snmp_edit();
$edit = ob_get_clean();
check_render($edit, 'Lab &amp; field');
check_render($edit, 'A&amp;B');
check_render($edit, 'P&amp;Q');
ob_start();
automation_snmp_item_remove_confirm();
check_render(ob_get_clean(), 'Lab &amp; office');
$source_id = db_fetch_cell('SELECT id FROM data_local ORDER BY id LIMIT 1');
$info = array('rrd_match' => 0, 'rrd_match_array' => array('ds' => array(), 'tune' => array('Lab & office')));
db_execute_prepared('INSERT INTO data_debug (datasource, done, info, issue) VALUES (?, 1, ?, ?)',
	array($source_id, serialize($info), 'Check options'));
set_request_var('id', $source_id);
foreach (array('debug_view', 'debug_icon', 'debug_icon_valid_result', 'debug_process_status') as $function) {
	cacti_fixture_source_function('data_debug.php', $function, '');
}
ob_start();
debug_view();
check_render(ob_get_clean(), 'Lab &amp; office');
db_execute('ROLLBACK');

$graphs = array(array('host_id' => 1, 'local_graph_id' => 1, 'disabled' => '', 'data_query_name' => 'Ports & traffic', 'title_cache' => 'Traffic'));
ob_start();
html_graph_thumbnail_area($graphs, '', '', '', '2');
$thumbnail = ob_get_clean();
check_render($thumbnail, "colspan='2'");
check_render($thumbnail, 'Ports &amp; traffic');

$base = $config['base_path'];
$directory = sys_get_temp_dir() . '/cacti-install-log-' . bin2hex(random_bytes(6));
mkdir($directory . '/log', 0700, true);
try {
	file_put_contents($directory . '/log/cacti.log', "2026-01-01 12:00:00 INSTALL: Ready & waiting\n");
	$config['base_path'] = $directory;
	check_render(Installer::getInstallLog(), 'Ready &amp; waiting');
} finally {
	$config['base_path'] = $base;
	unlink($directory . '/log/cacti.log');
	rmdir($directory . '/log');
	rmdir($directory);
}

foreach (array('graph_view_device_template_where', 'graph_view_template_host_where', 'graph_view_has_untemplated_graphs') as $function) {
	cacti_fixture_source_function('graph_view.php', $function, '');
}
set_request_var('action', 'tree');
html_validate_tree_vars();
ob_start();
grow_right_pane_tree(1, 0, '');
$tree = ob_get_clean();
if (!preg_match('/var navBar = (.+);/', $tree, $match)) {
	throw new RuntimeException('Missing graph tree navigation');
}
check_render(json_decode($match[1], true, 512, JSON_THROW_ON_ERROR), "<div id='navBar'");
print "Display rendering passed\n";
