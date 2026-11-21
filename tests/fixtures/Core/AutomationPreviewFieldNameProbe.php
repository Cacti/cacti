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
 * Runs the unchanged build_graph_object_sql_having() from
 * lib/api_automation.php in its own process, with the data query field names
 * taken from the scenario, and prints the clause it builds.
 */

namespace CactiAutomationPreviewFieldNameFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';

$config = array('is_web' => false, 'config_options_array' => array());

function get_field_names($snmp_query_id) {
	return array_map(function ($name) {
		return array('field_name' => $name);
	}, $GLOBALS['fields']);
}

function db_qstr($value) {
	return "'" . addslashes($value) . "'";
}

\cacti_fixture_source_function($root . '/lib/api_automation.php', 'build_graph_object_sql_having', __NAMESPACE__);

function automation_preview_having(array $fields, $filter) {
	$GLOBALS['fields'] = $fields;

	return array('having' => build_graph_object_sql_having(array('snmp_query_id' => 1), $filter));
}

print json_encode(automation_preview_having(...json_decode($argv[1], true)), JSON_THROW_ON_ERROR);
