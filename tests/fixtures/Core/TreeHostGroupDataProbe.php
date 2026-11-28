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

/*
 * Runs the unchanged grow_right_pane_tree() and get_host_graph_list() from
 * lib/html_tree.php in an isolated process so unit tests can assert that
 * host_group_data (hgd) identifiers are coerced to integers and that the
 * data query lookup binds host_id and snmp_query id via prepared parameters.
 */

namespace {
	if (!function_exists('naturally_sort_graphs')) {
		function naturally_sort_graphs($a, $b) {
			return 0;
		}
	}
}

namespace CactiTreeHostGroupDataFixture {

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';

$stub_dir = sys_get_temp_dir() . '/cacti-tree-hgd-probe-' . bin2hex(random_bytes(6));
mkdir($stub_dir, 0700, true);
file_put_contents($stub_dir . '/global_arrays.php', "<?php\n\$graph_timespans = array(GT_LAST_DAY => 'Last Day');\n\$realtime_window = array(1800 => '30 Minutes');\n\$realtime_refresh = array(5 => '5 Seconds');\n\$realtime_sizes = array(100 => '100%');\n\$realtime_default_size = 100;\n");
file_put_contents($stub_dir . '/data_query.php', "<?php\n");
file_put_contents($stub_dir . '/html_utility.php', "<?php\n");

register_shutdown_function(static function () use ($stub_dir) : void {
	foreach (array('global_arrays.php', 'data_query.php', 'html_utility.php') as $file) {
		if (file_exists($stub_dir . '/' . $file)) {
			unlink($stub_dir . '/' . $file);
		}
	}

	if (is_dir($stub_dir)) {
		rmdir($stub_dir);
	}
});

$config = array(
	'include_path' => $stub_dir,
	'library_path' => $stub_dir,
	'url_path'     => '/cacti/',
);
$current_user     = array('id' => 1);
$graphs_per_page  = array(10 => '10');
$graph_timeshifts = array(1 => '1 Hour');

$_SESSION = array(
	'sess_user_id'          => 1,
	'sess_current_timespan' => GT_LAST_DAY,
	'sess_current_timeshift' => 1,
	'sess_realtime_window'  => 1800,
	'sess_realtime_dsstep'  => 5,
);

$GLOBALS['tree_probe_state'] = array(
	'cell_queries'      => array(),
	'assoc_queries'     => array(),
	'raw_assoc_queries' => array(),
	'dqi_calls'         => array(),
	'allowed_wheres'    => array(),
	'leaf_type'         => 'host',
	'leaf'              => array(
		'title'              => 'Device Leaf',
		'host_id'            => 12,
		'site_id'            => 0,
		'host_grouping_type' => HOST_GROUPING_DATA_QUERY_INDEX,
		'parent'             => 0,
	),
	'request'           => array(
		'rfilter'           => '',
		'graph_template_id' => '-1',
		'graphs'            => 10,
		'columns'           => 2,
		'page'              => 1,
		'thumbnails'        => 'false',
		'node'              => 'tbranch-1',
		'leaf_id'           => 1,
	),
);

class CactiSecureHeaders {
	public static function getNonceAttribute() : string {
		return '';
	}
}

function __($text) {
	$args = func_get_args();

	if (count($args) > 1) {
		return vsprintf($text, array_slice($args, 1));
	}

	return $text;
}

function __esc($text) {
	return html_escape(call_user_func_array(__NAMESPACE__ . '\__', func_get_args()));
}

function html_escape($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function html_escape_request_var($name) {
	return html_escape(get_request_var($name));
}

function cacti_js_encode($value) {
	return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

function draw_navigation_text() {
	return '';
}

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function isset_request_var($name) {
	return array_key_exists($name, $GLOBALS['tree_probe_state']['request']);
}

function get_request_var($name, $default = '') {
	return $GLOBALS['tree_probe_state']['request'][$name] ?? $default;
}

function get_nfilter_request_var($name, $default = '') {
	return $GLOBALS['tree_probe_state']['request'][$name] ?? $default;
}

function get_filter_request_var($name) {
	return intval($GLOBALS['tree_probe_state']['request'][$name] ?? 0);
}

function read_user_setting($name, $default = '') {
	if ($name === 'show_aggregates') {
		return 'off';
	}

	return $default;
}

function read_config_option($name, $default = '') {
	return $default;
}

function is_view_allowed($view) {
	return true;
}

function api_tree_get_item_type($leaf_id) {
	return $GLOBALS['tree_probe_state']['leaf_type'];
}

function db_fetch_row_prepared($sql, $params = array()) {
	return $GLOBALS['tree_probe_state']['leaf'];
}

function db_fetch_cell_prepared($sql, $params = array()) {
	$GLOBALS['tree_probe_state']['cell_queries'][] = array(
		'sql'    => trim(preg_replace('/\s+/', ' ', $sql)),
		'params' => $params,
	);

	if (str_contains($sql, 'FROM graph_templates')) {
		return 'Template ' . ($params[0] ?? '');
	}

	if (str_contains($sql, 'FROM snmp_query')) {
		return 'Query ' . ($params[0] ?? '');
	}

	if (str_contains($sql, 'FROM graph_tree')) {
		return 'Default Tree';
	}

	if (str_contains($sql, 'FROM host')) {
		return 'Device One';
	}

	return '';
}

function db_fetch_assoc($sql) {
	$GLOBALS['tree_probe_state']['raw_assoc_queries'][] = trim(preg_replace('/\s+/', ' ', $sql));

	return array();
}

function db_fetch_assoc_prepared($sql, $params = array()) {
	$GLOBALS['tree_probe_state']['assoc_queries'][] = array(
		'sql'    => trim(preg_replace('/\s+/', ' ', $sql)),
		'params' => $params,
	);

	if (isset($params[1]) && $params[1] > 0) {
		return array(
			array(
				'id'   => $params[1],
				'name' => 'Query ' . $params[1],
			),
		);
	}

	return array(
		array(
			'id'   => 1,
			'name' => 'Interface Statistics',
		),
	);
}

function db_qstr($value) {
	return "'" . addslashes((string) $value) . "'";
}

function db_qstr_rlike($value) {
	return "RLIKE '" . addslashes((string) $value) . "'";
}

function get_formatted_data_query_index($host_id, $data_query_id, $data_query_index) {
	$GLOBALS['tree_probe_state']['dqi_calls'][] = array(
		'host_id'          => $host_id,
		'data_query_id'    => $data_query_id,
		'data_query_index' => $data_query_index,
	);

	return 'Index ' . $data_query_index;
}

function get_formatted_data_query_indexes($host_id, $data_query_id) {
	return array();
}

function get_allowed_graph_templates($sql_where = '', $order = 'name', $limit = '', &$total_rows = -1) {
	return array(
		array('id' => 7, 'name' => 'Interface Traffic'),
	);
}

function get_allowed_graphs($sql_where = '') {
	$GLOBALS['tree_probe_state']['allowed_wheres'][] = $sql_where;

	return array();
}

function get_allowed_aggregate_graphs($sql_where = '') {
	return array();
}

function get_allowed_tree_header_graphs($tree_id, $leaf_id, $sql_where = '') {
	return array();
}

function graph_view_template_host_where() {
	return '';
}

function html_start_box($title = '', $width = '', $bg = '', $cell_padding = '', $align = '', $add_text = '') {
}

function html_end_box($trailing_br = true) {
}

function html_nav_bar($base_url = '', $max_pages = 0, $current_page = 1, $rows_per_page = 10, $total_rows = 0, $columns = 1, $object = '', $page_var = 'page', $return_to = '') {
	return '';
}

function html_graph_area($graph_array, $no_graphs_message = '', $extra_url_args = '', $header = '', $columns = 0, $tree_id = 0, $leaf_id = 0) {
}

function html_graph_thumbnail_area($graph_array, $no_graphs_message = '', $extra_url_args = '', $header = '', $columns = 0, $tree_id = 0, $leaf_id = 0) {
}

function html_spikekill_js() {
}

function html_graph_template_multiselect() {
}

function api_plugin_hook_function($name, $param = null) {
	return $param;
}

function get_current_graph_start() {
	return 1700000000;
}

function get_current_graph_end() {
	return 1700086400;
}

\cacti_fixture_source_function($root . '/lib/html_tree.php', 'grow_right_pane_tree', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/html_tree.php', 'get_host_graph_list', __NAMESPACE__);

$payload = json_decode($argv[1], true);
$mode    = $payload['mode'] ?? 'pane';

if ($mode === 'pane') {
	if (isset($payload['grouping'])) {
		$GLOBALS['tree_probe_state']['leaf']['host_grouping_type'] = $payload['grouping'];
	}

	ob_start();
	grow_right_pane_tree(1, 1, (string) ($payload['hgd'] ?? ''));
	$html = ob_get_clean();

	$verdict = array(
		'cell_queries'      => $GLOBALS['tree_probe_state']['cell_queries'],
		'assoc_queries'     => $GLOBALS['tree_probe_state']['assoc_queries'],
		'raw_assoc_queries' => $GLOBALS['tree_probe_state']['raw_assoc_queries'],
		'dqi_calls'         => $GLOBALS['tree_probe_state']['dqi_calls'],
		'allowed_wheres'    => $GLOBALS['tree_probe_state']['allowed_wheres'],
		'has_non_query'     => str_contains($html, 'Non Query Based'),
		'has_template_base' => str_contains($html, 'Template Based'),
	);
} else {
	get_host_graph_list(
		$payload['host_id'] ?? 0,
		$payload['graph_template_id'] ?? '-1',
		$payload['data_query_id'] ?? '-1',
		$payload['grouping'] ?? HOST_GROUPING_DATA_QUERY_INDEX,
		$payload['data_query_index'] ?? ''
	);

	$verdict = array(
		'assoc_queries'     => $GLOBALS['tree_probe_state']['assoc_queries'],
		'raw_assoc_queries' => $GLOBALS['tree_probe_state']['raw_assoc_queries'],
		'allowed_wheres'    => $GLOBALS['tree_probe_state']['allowed_wheres'],
	);
}

print json_encode($verdict, JSON_THROW_ON_ERROR);
}
