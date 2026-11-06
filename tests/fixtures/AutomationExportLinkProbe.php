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

namespace CactiExportLinkFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require dirname(__DIR__) . '/Helpers/SourceSlice.php';
require dirname(__DIR__) . '/Helpers/FixtureCoverage.php';
$root = dirname(__DIR__,2);
require $root . '/include/global_constants.php';
$file   = $argv[1];
$action = match ($file) {
	'automation_graph_rules.php'=> AUTOMATION_ACTION_GRAPH_EXPORT,'automation_networks.php'=>6,'automation_snmp.php'=>3,'automation_templates.php'=>2,'automation_tree_rules.php'=>AUTOMATION_ACTION_TREE_EXPORT,default=>throw new \RuntimeException('Unknown fixture controller')
};
$actions = [];
function isrv($name) {
	return $name === 'selected_items';
}
function gnrv($name,$default = '') {
	return $name === 'selected_items' ? serialize([1, 2]) : ($name === 'drp_action' ? $GLOBALS['action'] : $default);
}
function grv($name,$default = '') {
	return gnrv($name,$default);
}
function gfrv($name,$filter = FILTER_VALIDATE_INT,$options = [],$default = '') {
	return gnrv($name,$default);
}
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : false;
}
function cacti_count($value) {
	return cacti_sizeof($value);
}
function top_header() {
}
function bottom_footer() {
}

foreach (['cacti_url', 'sanitize_unserialize_selected_items'] as $function) {
	cacti_fixture_source_function($root . '/lib/functions.php',$function,__NAMESPACE__);
}
$function = match ($file) {
	'automation_graph_rules.php'=> 'automation_graph_rules_form_actions','automation_tree_rules.php'=>'automation_tree_rules_form_actions',default=>'form_actions'
};
cacti_fixture_source_function($root . '/lib/html.php', 'html_download_start', __NAMESPACE__);
cacti_fixture_source_function($root . '/' . $file,$function,__NAMESPACE__);
ob_start();
register_shutdown_function(static function () {print json_encode(['html'=>ob_get_clean()],JSON_THROW_ON_ERROR) . "\n"; });
$call = __NAMESPACE__ . '\\' . $function;
$call();
