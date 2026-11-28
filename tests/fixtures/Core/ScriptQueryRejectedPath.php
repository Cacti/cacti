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

namespace CactiScriptQueryRejectedFixture;

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';

$GLOBALS['fixture_type_id']    = DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER;
$GLOBALS['fixture_query_path'] = '';
$GLOBALS['fixture_commands']   = [];

/**
 * Discard the data source debug trace.
 *
 * @param mixed ...$arguments The trace label and value
 *
 * @return void
 */
function dsv_log(...$arguments): void {
}

/**
 * Return the configured interpreter for the script server branch.
 *
 * @param string $name  The option name
 * @param bool   $force Unused; matches the production signature
 *
 * @return string
 */
function read_config_option($name, $force = false) {
	return $name === 'path_php_binary' ? '/usr/bin/php' : '';
}

/**
 * Return an active script query data input of the type under test.
 *
 * @param string $sql    The query
 * @param array  $params The bound parameters
 *
 * @return array
 */
function db_fetch_row_prepared($sql, $params = []) {
	if (str_contains($sql, 'data_template_data')) {
		return ['id' => 1, 'type_id' => $GLOBALS['fixture_type_id'], 'data_template_data_id' => 5, 'data_template_id' => 3, 'active' => 'on', 'rrd_step' => 300, 'name' => 'fixture'];
	}

	return ['id' => 2, 'hostname' => 'localhost'];
}

/**
 * Return one query output for the template and no host overrides.
 *
 * @param string $sql    The query
 * @param array  $params The bound parameters
 *
 * @return array
 */
function db_fetch_assoc_prepared($sql, $params = []) {
	if (str_contains($sql, 'snmp_query_graph_rrd')) {
		return [['snmp_field_name' => 'ifIndex', 'data_template_rrd_id' => 7]];
	}

	return [];
}

/**
 * Return no output type for the data template.
 *
 * @param int $data_template_data_id The data template data id
 *
 * @return array
 */
function data_query_field_list($data_template_data_id) {
	return [];
}

/**
 * Return a script query definition with one get field.
 *
 * @param int $snmp_query_id The data query id
 *
 * @return array
 */
function get_data_query_array($snmp_query_id) {
	return ['fields' => ['ifIndex' => ['query_name' => 'index']], 'arg_get' => 'get', 'arg_prepend' => '', 'script_path' => '|path_cacti|/scripts/query.php'];
}

/**
 * Return the path the case under test assigns, '' meaning rejected.
 *
 * @param string $extra_arguments The query arguments
 * @param string $script_path     The query script path
 * @param int    $host_id         The device id
 *
 * @return string
 */
function get_script_query_path($extra_arguments, $script_path, $host_id) {
	return $GLOBALS['fixture_query_path'];
}

/**
 * Record each command instead of running it.
 *
 * @param string $command The shell command
 *
 * @return string
 */
function shell_exec($command) {
	$GLOBALS['fixture_commands'][] = $command;

	return '42';
}

\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/functions.php', 'test_data_source', __NAMESPACE__);

$results = [];

foreach (['script_server' => DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER, 'script' => DATA_INPUT_TYPE_SCRIPT_QUERY] as $label => $type_id) {
	$GLOBALS['fixture_type_id'] = $type_id;

	foreach (['rejected' => '', 'accepted' => '/opt/cacti/scripts/query.php get index "1"'] as $case => $path) {
		$GLOBALS['fixture_query_path'] = $path;
		$GLOBALS['fixture_commands']   = [];

		$results[$label][$case] = ['result' => test_data_source(3, 2, 9, '1'), 'commands' => $GLOBALS['fixture_commands']];
	}
}

print json_encode($results);
