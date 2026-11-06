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

namespace CactiBulkActionGuardFixture;
use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require dirname(__DIR__) . '/Helpers/SourceSlice.php';
require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
$root = dirname(__DIR__, 2);
require $root . '/include/global_constants.php';
define('CACTI_PATH_LIBRARY', $root . '/lib');
$allowed = ['aggregate_graphs.php', 'aggregate_templates.php', 'cdef.php', 'color.php', 'color_templates.php', 'data_input.php', 'data_queries.php', 'data_source_profiles.php', 'data_sources.php', 'data_templates.php', 'gprint_presets.php', 'graph_templates.php', 'graphs.php', 'host_templates.php', 'pollers.php', 'sites.php', 'vdef.php', 'host.php', 'layouts.php', 'lib/html_reports.php', 'links.php', 'login_providers.php', 'managers.php', 'package_keys.php', 'package_repos.php', 'tree.php', 'user_admin.php', 'user_group_admin.php'];
$file    = $argv[1] ?? '';

if (!in_array($file, $allowed, true)) {
	throw new RuntimeException('Unknown fixture controller');
}
$mode                      = $argv[2] ?? 'valid';
$_SERVER['REQUEST_METHOD'] = $mode === 'get' ? 'GET' : 'POST';
$_SESSION                  = [SESS_USER_ID => 1];
$actions                   = $agg_item_actions = [];
$checked                   = false;
$reached                   = false;
function csrf_check(bool $fatal = true) : bool {
	$GLOBALS['checked'] = true;

	return $GLOBALS['mode'] === 'valid';
}
function isrv($name) {
	return in_array($name, ['selected_items', 'action_receivers'], true);
}
function isset_request_var($name) {
	return isrv($name);
}
function gnrv($name, $default = '') {
	return $name === 'selected_items' ? serialize([1]) : ($name === 'action_type' ? 'templates' : '1');
}
function gfrv($name, $filter = FILTER_VALIDATE_INT, $options = [], $default = '') {
	return gnrv($name, $default);
}
function get_filter_request_var($name, $filter = FILTER_VALIDATE_INT, $options = [], $default = '') {
	return gfrv($name, $filter, $options, $default);
}
function get_nfilter_request_var($name, $default = '') {
	return gnrv($name, $default);
}
function is_reports_admin() {
	return true;
}
function db_table_exists($table) {
	return false;
}
class HandlerReached extends RuntimeException {
}
function sanitize_unserialize_selected_items($items) {
	$GLOBALS['reached'] = true;

	throw new HandlerReached('The protected handler was reached');
}
function reports_unserialize_selected_items($items) {
	return sanitize_unserialize_selected_items($items);
}
class PackageListFilter {
	static function decodeSelectedIds($items) {
		return sanitize_unserialize_selected_items($items);
	}
}
register_shutdown_function(static function () {
	print json_encode(['status'=>http_response_code() ?: 200, 'checked'=>$GLOBALS['checked'], 'reached'=>$GLOBALS['reached']], JSON_THROW_ON_ERROR) . "\n";
});
$function = match ($file) {
	'aggregate_templates.php'=> 'aggregate_form_actions', 'vdef.php'=>'vdef_form_actions', 'layouts.php'=>'layouts_form_actions', 'lib/html_reports.php'=>'reports_form_actions', default=>'form_actions'
};
cacti_fixture_source_function($root . '/include/csrf.php', 'csrf_require_post', __NAMESPACE__);
cacti_fixture_source_function($root . '/' . $file, $function, __NAMESPACE__);

try {
	$call = __NAMESPACE__ . '\\' . $function;
	$call();
} catch (HandlerReached $expected) {
}
