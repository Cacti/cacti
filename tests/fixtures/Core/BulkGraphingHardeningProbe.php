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

namespace CactiBulkGraphingHardeningProbe;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';
require_once $root . '/lib/graph_template_input.php';

$GLOBALS['bulk_vdef_items']     = array();
$GLOBALS['bulk_vdef_logs']      = array();
$GLOBALS['bulk_vdef_functions'] = array(1 => 'MAXIMUM', 2 => 'MINIMUM', 3 => 'AVERAGE');
$GLOBALS['bulk_tpl_inputs']     = array();
$GLOBALS['bulk_ds_queries']     = array();
$GLOBALS['bulk_prep_queries']   = array();
$GLOBALS['bulk_debug_logs']     = array();
$GLOBALS['bulk_messages']       = array();
$GLOBALS['bulk_req']            = array();
$GLOBALS['bulk_headers']        = array();
$GLOBALS['bulk_sql_saves']      = array();
$GLOBALS['bulk_xport_data']     = '';
$GLOBALS['bulk_whitelist_ok']   = true;

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function cacti_log($message, $output = false, $facility = '', $level = 0) {
	$GLOBALS['bulk_vdef_logs'][] = $facility . ': ' . $message;
}

function db_qstr($value) {
	return "'" . str_replace("'", "\\'", (string) $value) . "'";
}

function db_execute($sql) {
	$GLOBALS['bulk_ds_queries'][] = $sql;
	return true;
}

function db_execute_prepared($sql, $params = array()) {
	$GLOBALS['bulk_prep_queries'][] = array($sql, $params);
	return true;
}

function db_fetch_assoc($sql) {
	if (strpos($sql, 'FROM data_template_rrd') !== false) {
		return array(
			array('data_source_name' => 'traffic_in', 'data_source_type_id' => 1),
		);
	}
	return array();
}

function db_fetch_assoc_prepared($sql, $params = array()) {
	if (strpos($sql, 'FROM vdef_items') !== false) {
		$vid = (int) $params[0];
		return isset($GLOBALS['bulk_vdef_items'][$vid]) ? $GLOBALS['bulk_vdef_items'][$vid] : array();
	}

	if (strpos($sql, 'FROM data_template_rrd') !== false && strpos($sql, 'data_input_id') !== false) {
		return isset($GLOBALS['bulk_tpl_inputs']['ids']) ? $GLOBALS['bulk_tpl_inputs']['ids'] : array();
	}

	if (strpos($sql, 'FROM data_template AS dt') !== false) {
		return array(
			array('id' => 10, 'name' => 'Traffic', 'data_source_name' => 'traffic_in'),
		);
	}

	if (strpos($sql, 'FROM snmp_query_graph_rrd_sv') !== false) {
		return array(
			array('field_name' => 'name', 'text' => 'Interface Traffic'),
			array('field_name' => 'rrd_maximum', 'text' => '1000000'),
			array('field_name' => 'data_source_path', 'text' => '/etc/passwd'),
		);
	}

	if (strpos($sql, 'FROM data_template_rrd') !== false) {
		return array(
			array('id' => 50, 'local_data_id' => 101, 'data_source_name' => 'traffic_in', 'data_source_type_id' => 1),
		);
	}

	return array();
}

function db_fetch_row_prepared($sql, $params = array()) {
	if (strpos($sql, 'FROM vdef_items WHERE id = ?') !== false) {
		$item_id = (int) $params[0];
		foreach ($GLOBALS['bulk_vdef_items'] as $items) {
			foreach ($items as $item) {
				if ((int) $item['id'] === $item_id) {
					return $item;
				}
			}
		}
		return array();
	}

	if (strpos($sql, 'FROM data_input') !== false) {
		$did = (int) $params[0];
		return isset($GLOBALS['bulk_tpl_inputs']['rows'][$did]) ? $GLOBALS['bulk_tpl_inputs']['rows'][$did] : array();
	}

	if (strpos($sql, 'FROM data_local') !== false) {
		return array(
			'id'               => 101,
			'host_id'          => 1,
			'data_template_id' => 10,
			'snmp_query_id'    => 1,
			'snmp_index'       => '1',
		);
	}

	return array();
}

function db_fetch_cell_prepared($sql, $params = array()) {
	if (strpos($sql, 'FROM graph_templates_item') !== false) {
		return ((int) $params[0] === 999) ? false : 77;
	}
	if (strpos($sql, 'FROM graph_templates') !== false) {
		return $params[0];
	}
	if (strpos($sql, 'FROM data_template_rrd') !== false) {
		return ((int) $params[0] === 999) ? false : 88;
	}
	if (strpos($sql, 'FROM data_template_data AS dtd_tpl') !== false) {
		return ((int) $params[1] === 5) ? 5 : false;
	}
	if (strpos($sql, 'FROM data_template_data') !== false) {
		return 200;
	}
	if (strpos($sql, 'FROM data_input_data') !== false) {
		return 15;
	}
	if (strpos($sql, 'FROM snmp_query_graph') !== false) {
		return 7;
	}
	if (strpos($sql, 'FROM host_snmp_cache') !== false) {
		return '';
	}
	return 1;
}

function db_column_exists($table, $column) {
	return $table === 'host' && $column === 'hostname';
}

function graph_template_input_column_is_allowed($column_name) {
	return \graph_template_input_column_is_allowed($column_name);
}

function graph_template_input_value_is_allowed($column_name, $value) {
	return \graph_template_input_value_is_allowed($column_name, $value);
}

function read_config_option($name, $force = false) {
	if ($name === 'storage_location') {
		return 0;
	}
	if ($name === 'dsstats_enable') {
		return 'on';
	}
	if ($name === 'dsstats_hourly_duration') {
		return 60;
	}
	if ($name === 'poller_interval') {
		return 300;
	}
	if ($name === 'max_data_query_field_length') {
		return 40;
	}
	return '';
}

function set_config_option($name, $value) {
}

function sql_save($save, $table) {
	$GLOBALS['bulk_sql_saves'][] = array($table, $save);
	return 101;
}

function change_graph_template($local_graph_id, $graph_template_id, $int) {
}

function change_data_template($local_data_id, $data_template_id, $profile = array()) {
}

function data_source_exists($gt_id, $host_id, $dt, $sq) {
	return array();
}

function create_graph_custom_data_compatible($sv, $prev) {
	return false;
}

function api_plugin_hook_function($hook, $data) {
	return $data;
}

function get_graph_title($id) {
	return 'Graph ' . $id;
}

function push_out_host($host_id, $item) {
}

function automation_hook_graph_create_tree($save) {
}

function api_reapply_suggested_graph_title($local_graph_id) {
}

function update_data_source_title_cache($local_data_id) {
}

function update_graph_title_cache($local_graph_id) {
}

function update_poller_cache($local_data_id, $commit = false) {
}

function generate_data_source_path($local_data_id) {
	return '<path_rra>/1/101.rrd';
}

function data_input_field_always_checked($id) {
	return false;
}

function update_data_source_data_query_cache($local_data_id) {
}

function substitute_snmp_query_data($text, $host_id, $sq_id, $idx, $max) {
	return $text;
}

function input_validate_input_number($val) {
}

function get_best_data_query_index_type($host_id, $sq_id) {
	return 'ifIndex';
}

function decode_data_query_index($idx, $sq_id, $host_id) {
	return $idx;
}

function test_data_sources($gt_id, $host_id, $sq_id, $idx, $params = array()) {
	return false;
}

function debug_log_insert($cat, $msg) {
	$GLOBALS['bulk_debug_logs'][] = $msg;
}

function __($text) {
	return $text;
}

function __esc($text) {
	return $text;
}

function raise_message($id, $msg = '', $level = 0) {
	$GLOBALS['bulk_messages'][] = $id;
}

function is_error_message() {
	return !empty($_SESSION['sess_error_fields']);
}

function csrf_require_post() {
}

function isset_request_var($name) {
	return isset($GLOBALS['bulk_req'][$name]);
}

function get_request_var($name) {
	return isset($GLOBALS['bulk_req'][$name]) ? $GLOBALS['bulk_req'][$name] : '';
}

function get_filter_request_var($name) {
	return isset($GLOBALS['bulk_req'][$name]) ? (int) $GLOBALS['bulk_req'][$name] : 0;
}

function get_nfilter_request_var($name) {
	return isset($GLOBALS['bulk_req'][$name]) ? $GLOBALS['bulk_req'][$name] : '';
}

function form_input_validate($val, $name, $regex, $allow_empty, $msg) {
	return $val;
}

function get_sequence($id, $col, $table, $where) {
	return 1;
}

function get_hash_vdef($id, $type = 'vdef') {
	return 'abcdef0123456789';
}

function get_client_addr() {
	return '127.0.0.1';
}

function header($str) {
	$GLOBALS['bulk_headers'][] = $str;
}

function dsstats_debug($msg) {
}

function dsstats_rrdtool_execute($cmd, &$pipes) {
	if (strpos($cmd, 'info ') === 0) {
		return "ds[traffic_in].type = \"GAUGE\"\nrra[0].cf = \"AVERAGE\"\nrra[1].cf = \"MAX\"\nstep = 300\n";
	}
	return $GLOBALS['bulk_xport_data'];
}

function rrdtool_build_path_command($action, $path) {
	return $action . ' ' . $path;
}

function cacti_rrdtool_valid_ds_name($name) {
	return true;
}

function set_error_handler($cb = null) {
	return null;
}

function restore_error_handler() {
	return true;
}

function rrdtool_execute($cmd, $log, $out, $pipes = false, $cat = '') {
	return $GLOBALS['bulk_xport_data'];
}

function cacti_count($value) {
	return is_array($value) ? count($value) : 0;
}

function array_rekey($arr, $key, $val = '') {
	$out = array();
	foreach ($arr as $row) {
		if (is_array($val) || $val === '') {
			$out[$row[$key]] = $row;
		} else {
			$out[$row[$key]] = $row[$val];
		}
	}
	return $out;
}

function rrdtool_function_info($rrdfile) {
	return array('ds[traffic_in].type' => 'GAUGE');
}

function cacti_rrdtool_valid_path($path) {
	return true;
}

function cacti_escapeshellarg($s) {
	return escapeshellarg((string) $s);
}

function rrdtool_escape_string($s) {
	return (string) $s;
}

function get_data_source_item_name($id) {
	return 'traffic_in';
}

function db_fetch_cell($sql) {
	return '';
}

\cacti_fixture_source_function($root . '/lib/vdef.php', 'get_vdef_item_name', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/vdef.php', 'get_vdef', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/vdef.php', 'get_vdef_recursive', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/template.php', 'template_suggested_field_is_allowed', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/template.php', 'graph_template_whitelist_check', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/template.php', 'create_complete_graph_from_template', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/template.php', 'create_save_graph', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/api_data_source.php', 'api_data_source_suggested_value_target', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/api_data_source.php', 'api_reapply_suggested_data_source_data', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/dsstats.php', 'dsstats_format_sql_numeric', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/dsstats.php', 'dsstats_write_buffer', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/dsstats.php', 'dsstats_obtain_data_source_avgpeak_values', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/dsstats.php', 'dsstats_poller_output', __NAMESPACE__);
\cacti_fixture_source_function($root . '/vdef.php', 'vdef_form_save', __NAMESPACE__);
\cacti_fixture_source_function($root . '/vdef.php', 'vdef_item_remove', __NAMESPACE__);
\cacti_fixture_source_function($root . '/automation_graph_rules.php', 'save', __NAMESPACE__);

$mode = isset($argv[1]) ? $argv[1] : 'main';

if ($mode === 'automation_rule_sqli' || $mode === 'automation_match_sqli') {
	register_shutdown_function(static function () {
		print json_encode(array(
			'messages' => $GLOBALS['bulk_messages'],
			'logs'     => $GLOBALS['bulk_vdef_logs'],
			'headers'  => $GLOBALS['bulk_headers'],
		), JSON_THROW_ON_ERROR);
	});

	if ($mode === 'automation_rule_sqli') {
		$GLOBALS['bulk_req'] = array(
			'save_component_automation_graph_rule_item' => 1,
			'id'        => 1,
			'item_id'   => 0,
			'sequence'  => 1,
			'operation' => 1,
			'field'     => 'h.nonexistent_col',
			'operator'  => 1,
			'pattern'   => 'test',
		);
	} else {
		$GLOBALS['bulk_req'] = array(
			'save_component_automation_match_item' => 1,
			'id'        => 1,
			'item_id'   => 0,
			'sequence'  => 1,
			'operation' => 1,
			'field'     => 'h.nonexistent_col',
			'operator'  => 1,
			'pattern'   => 'test',
		);
	}

	save();
	exit;
}

$tmpDir = sys_get_temp_dir() . '/cacti-graph-probe-' . bin2hex(random_bytes(6));
mkdir($tmpDir, 0700, true);
file_put_contents($tmpDir . '/data_query.php', "<?php\n");
$badWhitelist = $tmpDir . '/bad_wl.json';
$goodWhitelist = $tmpDir . '/good_wl.json';
file_put_contents($badWhitelist, '{not valid json');
file_put_contents($goodWhitelist, json_encode(array(
	'hash_ok'       => '/usr/bin/script.sh',
	'hash_mismatch' => '/usr/bin/expected.sh',
)));

$GLOBALS['config'] = array(
	'base_path'       => $tmpDir,
	'library_path'    => $tmpDir,
	'input_whitelist' => $badWhitelist,
	'cacti_server_os' => 'unix',
);

try {
	// 1. template_suggested_field_is_allowed full coverage
	$tpl_allowed = array(
		'non_scalar'     => template_suggested_field_is_allowed('graph_templates_graph', 'title', array()),
		'ctrl_char'      => template_suggested_field_is_allowed('graph_templates_graph', 'title', "bad\x01val"),
		'gtg_title'      => template_suggested_field_is_allowed('graph_templates_graph', 'title', 'CPU Usage'),
		'gtg_height'     => template_suggested_field_is_allowed('graph_templates_graph', 'height', '120'),
		'gtg_blocked'    => template_suggested_field_is_allowed('graph_templates_graph', 'local_graph_id', '99'),
		'gti_color'      => template_suggested_field_is_allowed('graph_templates_item', 'color_id', '12'),
		'gti_task'       => template_suggested_field_is_allowed('graph_templates_item', 'task_item_id', '5'),
		'gti_blocked'    => template_suggested_field_is_allowed('graph_templates_item', 'local_graph_id', '5'),
		'dtd_name'       => template_suggested_field_is_allowed('data_template_data', 'name', 'Traffic In'),
		'dtd_active'     => template_suggested_field_is_allowed('data_template_data', 'active', 'on'),
		'dtd_profile'    => template_suggested_field_is_allowed('data_template_data', 'data_source_profile_id', '1'),
		'dtd_step'       => template_suggested_field_is_allowed('data_template_data', 'rrd_step', '300'),
		'dtd_blocked'    => template_suggested_field_is_allowed('data_template_data', 'data_source_path', '/etc/passwd'),
		'dtr_max_u'      => template_suggested_field_is_allowed('data_template_rrd', 'rrd_maximum', 'U'),
		'dtr_min_num'    => template_suggested_field_is_allowed('data_template_rrd', 'rrd_minimum', '0'),
		'dtr_heartbeat'  => template_suggested_field_is_allowed('data_template_rrd', 'rrd_heartbeat', '600'),
		'dtr_dstype'     => template_suggested_field_is_allowed('data_template_rrd', 'data_source_type_id', '1'),
		'dtr_bad_max'    => template_suggested_field_is_allowed('data_template_rrd', 'rrd_maximum', 'not-a-number'),
		'dtr_blocked'    => template_suggested_field_is_allowed('data_template_rrd', 'data_source_name', 'injected'),
		'unknown_table'  => template_suggested_field_is_allowed('unknown_table', 'name', 'x'),
	);

	// 2. graph_template_whitelist_check: corrupt JSON first (cached static remains null!)
	$wl_corrupt = graph_template_whitelist_check(1);

	// Switch to valid JSON and test missing row, mismatch, unwhitelisted, and matching
	$GLOBALS['config']['input_whitelist'] = $goodWhitelist;
	$GLOBALS['bulk_tpl_inputs'] = array(
		'ids' => array(
			array('data_input_id' => 1),
			array('data_input_id' => 2),
			array('data_input_id' => 3),
			array('data_input_id' => 4),
		),
		'rows' => array(
			1 => array('hash' => 'hash_ok', 'input_string' => '/usr/bin/script.sh'),
			2 => array('hash' => 'hash_mismatch', 'input_string' => '/usr/bin/tampered.sh'),
			3 => array('hash' => 'hash_unlisted', 'input_string' => '/usr/bin/unlisted.sh'),
		),
	);
	$wl_mixed = graph_template_whitelist_check(1);

	// Also exercise create_save_graph failing whitelist on both cg and sg
	create_save_graph(1, 'cg', 1, array(), array());
	create_save_graph(1, 'sg', 1, array(2 => array('1' => true)), array('sg' => array(1 => array())));

	// Now make whitelist pass for create_complete_graph_from_template and run it with allowed + disallowed fields
	$GLOBALS['bulk_tpl_inputs'] = array(
		'ids'  => array(array('data_input_id' => 1)),
		'rows' => array(1 => array('hash' => 'hash_ok', 'input_string' => '/usr/bin/script.sh')),
	);
	$suggested = array(
		7 => array(
			'graph_template'      => array('title' => 'Valid Title', 'local_graph_id' => '999'),
			'graph_template_item' => array(
				10  => array('color_id' => '12', 'task_item_id' => '5'),
				999 => array('color_id' => '12'),
			),
			'data_template'       => array(10 => array('name' => 'Valid DS', 'data_source_path' => '/etc/passwd')),
			'data_template_item'  => array(
				20  => array('rrd_maximum' => '100', 'data_source_name' => 'bad'),
				999 => array('rrd_maximum' => '100'),
			),
			'custom_data'         => array(10 => array(5 => 'allowed_val', 6 => 'blocked_val')),
		),
	);
	$snmp_q = array();
	create_complete_graph_from_template(7, 1, $snmp_q, $suggested);

	// Also exercise create_save_graph when whitelist passes (falls through to test_data_sources)
	create_save_graph(1, 'cg', 1, array(), array());
	create_save_graph(1, 'sg', 1, array(2 => array('1' => true)), array('sg' => array(1 => array())));

	// 3. lib/vdef.php full coverage (item types, cycles, depth >= 64, expansion > 4096, output > 65536)
	$GLOBALS['vdef_functions'] = $GLOBALS['bulk_vdef_functions'];
	$GLOBALS['bulk_vdef_items'] = array(
		1 => array(
			array('id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE'),
			array('id' => 11, 'type' => '1', 'value' => '1'),
			array('id' => 12, 'type' => '6', 'value' => '95'),
			array('id' => 13, 'type' => '99', 'value' => 'unknown'),
			array('id' => 14, 'type' => '1', 'value' => '999'),
		),
		2 => array(array('id' => 20, 'type' => '5', 'value' => '2')),
		3 => array(array('id' => 30, 'type' => '5', 'value' => '4')),
		4 => array(array('id' => 40, 'type' => '5', 'value' => '3')),
		5 => array(array('id' => 50, 'type' => '6', 'value' => str_repeat('A', 70000))),
	);
	// Build a 66-deep chain (100 -> 101 -> ... -> 165)
	for ($d = 100; $d < 166; $d++) {
		$GLOBALS['bulk_vdef_items'][$d] = array(
			array('id' => 1000 + $d, 'type' => '5', 'value' => (string) ($d + 1)),
		);
	}
	$GLOBALS['bulk_vdef_items'][166] = array(array('id' => 1166, 'type' => '6', 'value' => '1'));

	$vdef_missing_item = get_vdef_item_name(99999);
	$vdef_zero         = get_vdef(0);
	$vdef_valid        = get_vdef(1);
	$vdef_direct_cyc   = get_vdef(2);
	$vdef_indirect_cyc = get_vdef(3);
	$vdef_too_large    = get_vdef(5);
	$vdef_too_deep     = get_vdef(100);
	$visited_exp = array();
	$exp_count   = 4096;
	$vdef_too_exp      = get_vdef_recursive(1, false, $visited_exp, $exp_count);

	// 4. lib/api_data_source.php full coverage
	$ds_sv = array(
		'non_scalar'  => api_data_source_suggested_value_target('name', array()),
		'ctrl_char'   => api_data_source_suggested_value_target('name', "bad\x01"),
		'name'        => api_data_source_suggested_value_target('name', 'Interface Traffic'),
		'active'      => api_data_source_suggested_value_target('active', 'on'),
		'active_bad'  => api_data_source_suggested_value_target('active', 'yes'),
		'rrd_step'    => api_data_source_suggested_value_target('rrd_step', '300'),
		'rrd_max_u'   => api_data_source_suggested_value_target('rrd_maximum', 'U'),
		'rrd_min_0'   => api_data_source_suggested_value_target('rrd_minimum', '0'),
		'rrd_hb'      => api_data_source_suggested_value_target('rrd_heartbeat', '600'),
		'dstype'      => api_data_source_suggested_value_target('data_source_type_id', '1'),
		'path_block'  => api_data_source_suggested_value_target('data_source_path', '/tmp/x.rrd'),
		'bad_max'     => api_data_source_suggested_value_target('rrd_maximum', 'abc'),
	);
	api_reapply_suggested_data_source_data(101);

	// 5. lib/dsstats.php full coverage
	$dsstats_fmt = array(
		'null'   => dsstats_format_sql_numeric(null),
		'int'    => dsstats_format_sql_numeric(12),
		'inf'    => dsstats_format_sql_numeric(INF),
		'array'  => dsstats_format_sql_numeric(array()),
		'numstr' => dsstats_format_sql_numeric('42.5'),
		'nan'    => dsstats_format_sql_numeric('NaN'),
		'u'      => dsstats_format_sql_numeric('U'),
		'sqli'   => dsstats_format_sql_numeric("1'); DROP TABLE host;--"),
	);
	$stats_buf = array(
		'15' => array("traffic'in" => array('AVG' => '12.5', 'MAX' => "bad'sql")),
	);
	dsstats_write_buffer($stats_buf, 'invalid_table');
	$ds_queries_after_invalid = count($GLOBALS['bulk_ds_queries']);
	dsstats_write_buffer($stats_buf, 'daily');
	$ds_query_daily = $GLOBALS['bulk_ds_queries'][0];

	// Exercise dsstats_obtain_data_source_avgpeak_values
	$rrdfile = $tmpDir . '/sample.rrd';
	file_put_contents($rrdfile, 'rrd');
	$GLOBALS['bulk_xport_data'] = "0x0\nERROR: bad line\n12.5\n-nan\nOK u:0.01 s:0.00 r:0.01\n";
	$pipes = array();
	$avgpeak = dsstats_obtain_data_source_avgpeak_values(101, $rrdfile, 'daily', $pipes);

	// Exercise dsstats_poller_output
	$rrd_upd = array(
		$rrdfile => array(
			'local_data_id' => 101,
			'times'         => array(
				1700000000 => array('traffic_in' => '42.5'),
			),
		),
	);
	dsstats_poller_output($rrd_upd);

	// 6. vdef.php vdef_form_save + vdef_item_remove
	$GLOBALS['vdef_item_types'] = array(
		CVDEF_ITEM_TYPE_FUNCTION => 'Function',
		CVDEF_ITEM_TYPE_SPEC_DS  => 'Special Data Source',
		CVDEF_ITEM_TYPE_STRING   => 'Custom String',
	);
	$GLOBALS['custom_vdef_data_source_types'] = array('CURRENT_DATA_SOURCE' => 'Current');
	$vdef_save_errors = array();
	foreach (array(
		'bad_type' => array('type' => 999, 'value' => '1'),
		'bad_fn'   => array('type' => CVDEF_ITEM_TYPE_FUNCTION, 'value' => '999'),
		'bad_ds'   => array('type' => CVDEF_ITEM_TYPE_SPEC_DS, 'value' => 'BAD_DS'),
		'bad_str'  => array('type' => CVDEF_ITEM_TYPE_STRING, 'value' => "bad\nline"),
		'ok_str'   => array('type' => CVDEF_ITEM_TYPE_STRING, 'value' => '100'),
	) as $case => $vals) {
		$_SESSION['sess_error_fields'] = array();
		$GLOBALS['bulk_req'] = array(
			'save_component_item' => 1,
			'id'                  => 0,
			'vdef_id'             => 1,
			'type'                => $vals['type'],
			'value'               => $vals['value'],
		);
		vdef_form_save();
		$vdef_save_errors[$case] = array_keys($_SESSION['sess_error_fields']);
	}
	$GLOBALS['bulk_req'] = array('id' => 10, 'vdef_id' => 1);
	vdef_item_remove();

	// 7. automation_graph_rules.php valid rule item & match item saves
	$_SESSION['sess_error_fields'] = array();
	$GLOBALS['bulk_req'] = array(
		'save_component_automation_graph_rule_item' => 1,
		'id' => 1, 'item_id' => 0, 'sequence' => 1, 'operation' => 1,
		'field' => 'ifDescr', 'operator' => 1, 'pattern' => 'eth',
	);
	save();
	$GLOBALS['bulk_req'] = array(
		'save_component_automation_match_item' => 1,
		'id' => 1, 'item_id' => 0, 'sequence' => 1, 'operation' => 1,
		'field' => 'h.hostname', 'operator' => 1, 'pattern' => 'router',
	);
	save();

	print json_encode(array(
		'tpl_allowed'             => $tpl_allowed,
		'wl_corrupt'              => $wl_corrupt,
		'wl_mixed'                => $wl_mixed,
		'vdef_missing_item'       => $vdef_missing_item,
		'vdef_zero'               => $vdef_zero,
		'vdef_valid'              => $vdef_valid,
		'vdef_direct_cyc'         => $vdef_direct_cyc,
		'vdef_indirect_cyc'       => $vdef_indirect_cyc,
		'vdef_too_large'          => $vdef_too_large,
		'vdef_too_deep'           => $vdef_too_deep,
		'vdef_too_exp'            => $vdef_too_exp,
		'ds_sv'                   => $ds_sv,
		'dsstats_fmt'             => $dsstats_fmt,
		'ds_queries_after_invalid'=> $ds_queries_after_invalid,
		'ds_query_daily'          => $ds_query_daily,
		'vdef_save_errors'        => $vdef_save_errors,
		'logs_count'              => count($GLOBALS['bulk_vdef_logs']),
		'debug_logs_count'        => count($GLOBALS['bulk_debug_logs']),
	), JSON_THROW_ON_ERROR);
} finally {
	@unlink($tmpDir . '/sample.rrd');
	@unlink($tmpDir . '/data_query.php');
	@unlink($badWhitelist);
	@unlink($goodWhitelist);
	@rmdir($tmpDir);
}
