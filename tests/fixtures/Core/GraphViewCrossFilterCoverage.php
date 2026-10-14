<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
namespace CactiGraphViewCrossFilterFixture;

use RuntimeException;

/*
 * Request-coverage probe for the Device <-> Graph Template cross-filter.
 *
 * html_make_device_where() (lib/html.php) and the two filter builders in
 * lib/html_graph.php reach their changed lines only when a graph_template_id
 * filter is present, a path the page-level e2e suites do not drive. The
 * functions are loaded in isolation with their I/O boundaries stubbed so the
 * production statements run and RequestCoverage maps the hits back to source.
 */

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';

$GLOBALS['item_rows']  = ['-1' => 'All', '15' => '15', '30' => '30'];
$GLOBALS['_SESSION']   = ['sess_user_id' => 1];
$GLOBALS['_REQUEST']   = ['graph_template_id' => '5'];

function __($text, ...$args) {
	return $args !== [] ? @vsprintf($text, $args) : $text;
}
function array_rekey($rows, $key, $value) {
	$result = [];

	foreach ($rows as $row) {
		$result[$row[$key]] = is_array($value) ? array_intersect_key($row, array_flip($value)) : $row[$value];
	}

	return $result;
}
function db_fetch_assoc($sql, ...$args) {
	return [];
}
function db_fetch_assoc_prepared($sql, $params = [], ...$args) {
	return [];
}
function db_fetch_cell_prepared($sql, $params = []) {
	return 'device';
}
function get_allowed_graph_templates_normalized($sql_where = '', $order = 'name', $limit = '', &$total_rows = 0) {
	$total_rows = 0;

	return [];
}
function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}
function read_user_setting($name, $default = '') {
	return $default;
}
function html_graph_validate_columns($value) {
	$columns = (int) $value;

	return $columns > 0 ? $columns : 2;
}
function html_graph_order_filter_array() {
	return [];
}
function is_view_allowed($view) {
	return true;
}
function raise_message($message, ...$args) {
}
function isrv($name) {
	return isset($GLOBALS['_REQUEST'][$name]);
}
function gnrv($name, $default = '') {
	return $GLOBALS['_REQUEST'][$name] ?? $default;
}
function grv($name, $default = '') {
	return $GLOBALS['_REQUEST'][$name] ?? $default;
}
function gfrv($name, $filter = FILTER_VALIDATE_INT, $options = []) {
	return (int) ($GLOBALS['_REQUEST'][$name] ?? 0);
}
function srv($name, $value) {
	$GLOBALS['_REQUEST'][$name] = $value;
}
function db_qstr($value) {
	return "'" . addslashes((string) $value) . "'";
}
function db_in_clause($column, $ids) {
	return '(' . $column . ' IN (' . (is_array($ids) ? implode(',', $ids) : (string) $ids) . '))';
}
function html_transform_graph_template_ids($ids) {
	return is_array($ids) ? '' : (string) $ids;
}

cacti_fixture_source_function($root . '/lib/html.php', 'html_make_device_where', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/html_graph.php', 'create_graphs_preview_filter', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/html_graph.php', 'create_listview_filter', __NAMESPACE__);

$where = html_make_device_where();

if (!str_contains($where, 'gl.graph_template_id IN (5)')) {
	throw new RuntimeException('Graph Template cross-filter did not constrain the device query');
}

foreach (['create_graphs_preview_filter', 'create_listview_filter'] as $builder) {
	$filters    = __NAMESPACE__ . '\\' . $builder;
	$definition = $filters('sess_probe');
	$host_field = $definition['rows'][0]['host_id'];

	if (!str_contains($host_field['request_vars'], 'graph_template_id')) {
		throw new RuntimeException($builder . ' does not forward graph_template_id to ajax_hosts');
	}
}

print json_encode(['cross_filter' => true], JSON_THROW_ON_ERROR) . "\n";
