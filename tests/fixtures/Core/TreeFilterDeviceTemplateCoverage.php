<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
namespace CactiTreeFilterDeviceTemplateFixture;

use RuntimeException;

/*
 * Request-coverage probe for the Tree view Graph Templates filter scoping by the
 * drilled Device Template.
 *
 * create_tree_filter() (lib/html_tree.php) reaches its host_template_id branch
 * only when a Site Branch is drilled into by Device Template (host_id == -1 with
 * host_template_id set), a path the page-level e2e suites do not drive. The
 * function is loaded in isolation with its I/O boundaries stubbed so the
 * production statements run and RequestCoverage maps the hits back to source.
 */

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';

$GLOBALS['item_rows']                     = ['-1' => 'All', '15' => '15', '30' => '30'];
$GLOBALS['_SESSION']                      = ['sess_user_id' => 1];
$GLOBALS['_REQUEST']                      = ['host_id' => '-1', 'host_template_id' => '7', 'site_id' => '3', 'graph_template_id' => '-1'];
$GLOBALS['captured_graph_template_where'] = null;

function __($text, ...$args) {
	return $args !== [] ? @vsprintf($text, $args) : $text;
}
function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}
function read_user_setting($name, $default = '') {
	return $default;
}
function html_graph_order_filter_array() {
	return [];
}
function is_view_allowed($view) {
	return true;
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
function get_allowed_graph_templates_normalized($sql_where = '', $order = 'name', $limit = '', &$total_rows = 0) {
	$GLOBALS['captured_graph_template_where'] = $sql_where;
	$total_rows                               = 0;

	return [];
}

cacti_fixture_source_function($root . '/lib/html_tree.php', 'create_tree_filter', __NAMESPACE__);

$filters = create_tree_filter();

$where = $GLOBALS['captured_graph_template_where'];

if ($where !== 'gl.host_id IN (SELECT id FROM host WHERE host_template_id=7 AND site_id=3)') {
	throw new RuntimeException('Device Template tree filter did not scope the Graph Templates query: ' . var_export($where, true));
}

if (!isset($filters['rows'][1]['graph_template_id'])) {
	throw new RuntimeException('create_tree_filter did not build the Graph Template dropdown');
}

print json_encode(['device_template_scope' => true], JSON_THROW_ON_ERROR) . "\n";
