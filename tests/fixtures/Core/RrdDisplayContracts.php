<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
chdir(dirname(__DIR__, 3));
require_once './tests/Helpers/RequestCoverage.php';
require_once './tests/Helpers/SourceSlice.php';
$view = $argv[1] ?? 'aggregate';
$page = array('aggregate' => '/aggregate_graphs.php', 'graph' => '/graphs.php', 'properties' => '/graph.php')[$view];
$_SERVER['PHP_SELF'] = $page;
$_SERVER['SCRIPT_NAME'] = $page;
$_SERVER['REQUEST_URI'] = $page;
$_SERVER['SERVER_NAME'] = 'localhost';
require './include/global.php';
require_once './lib/rrd.php';
require_once './lib/boost.php';
require_once './lib/variables.php';
$_SESSION['sess_user_id'] = 1;
db_execute('START TRANSACTION');
db_execute_prepared('INSERT INTO host (hostname, description) VALUES (?, ?)', array('test.invalid', 'Test router'));
$host_id = db_fetch_insert_id();
db_execute_prepared('INSERT INTO graph_local (host_id) VALUES (?)', array($host_id));
$graph_id = db_fetch_insert_id();
db_execute_prepared('INSERT INTO graph_templates_graph (local_graph_id, title, title_cache) VALUES (?, ?, ?)',
	array($graph_id, 'Fixture graph', 'Fixture graph'));
db_execute_prepared("INSERT INTO aggregate_graphs
	(local_graph_id, title_format, aggregate_template_id, graph_template_id, gprint_prefix, graph_type, total, total_type, total_prefix, order_type, user_id)
	VALUES (?, 'Fixture graph', 0, 0, '', 0, 0, 0, '', 0, 1)", array($graph_id));
$expected = 'Test router / ';
$title = '|host_description| / |host_snmp_community|';
if (expand_title($host_id, 0, '', $title) !== $expected ||
	rrd_substitute_host_query_data($title, array('host_id' => $host_id), array()) !== $expected) {
	throw new RuntimeException('Graph titles and labels must render the public device name only.');
}
$options = array('output_flag' => RRDTOOL_OUTPUT_RETURN_STDERR);
if (boost_graph_cache_check($graph_id, 1, false, $options) !== false) {
	throw new RuntimeException('A cached graph cannot supply an RRDtool diagnostic.');
}

$_SESSION['graph_debug_mode'] = true;
set_request_var('id', $graph_id);
set_request_var('tab', 'preview');
set_request_var('action', 'edit');
if ($view === 'properties') {
	set_request_var('action', 'properties');
	set_request_var('local_graph_id', $graph_id);
	set_request_var('rra_id', 1);
	set_request_var('header', 'false');
	// The properties endpoint ends the request after writing the complete table.
	require './graph.php';
}

ob_start();
if ($view === 'graph') {
	foreach (array('get_current_graph_template', 'get_common_graph_templates', 'is_multi_device_graph', 'item', 'graph_edit') as $function) {
		cacti_fixture_source_function('graphs.php', $function, '');
	}
	graph_edit();
} else {
	cacti_fixture_source_function('aggregate_graphs.php', 'graph_edit', '');
	graph_edit();
}
$output = ob_get_clean();
if (strpos($output, 'RRDtool Says:') === false || strpos($output, 'RRDtool Command:') === false) {
	throw new RuntimeException('Graph preview is missing its command or diagnostic.');
}
db_execute('ROLLBACK');
print "RRD display passed\n";
