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
 * get_script_query_path() returns '' for a rejected script path. This probe
 * runs the real query_script_host(), update_reindex_cache() and
 * update_poller_cache() against stubbed data so each one is shown to stop or
 * skip on '' instead of running or storing an empty command.
 */

namespace CactiScriptQueryCallersFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';

$GLOBALS['fixture_reject']     = '';
$GLOBALS['fixture_commands']   = [];
$GLOBALS['fixture_recache']    = [];
$GLOBALS['fixture_items']      = [];
$GLOBALS['fixture_type_id']    = DATA_INPUT_TYPE_SCRIPT_QUERY;
$GLOBALS['fixture_reindex']    = DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE;
$GLOBALS['fixture_num_column'] = true;

/**
 * Reject the path when the arguments carry the marker under test.
 *
 * @param string $args        The query arguments
 * @param string $script_path The query script path
 * @param int    $host_id     The device id
 *
 * @return string '' when rejected, otherwise a command naming the arguments
 */
function get_script_query_path($args, $script_path, $host_id) {
	if ($GLOBALS['fixture_reject'] !== '' && str_contains($args, $GLOBALS['fixture_reject'])) {
		return '';
	}

	return '/opt/cacti/scripts/query.php ' . $args;
}

/**
 * Return a script query definition that uses every argument kind.
 *
 * @param int $snmp_query_id The data query id
 *
 * @return array
 */
function get_data_query_array($snmp_query_id) {
	$xml = [
		'script_path'     => '|path_cacti|/scripts/query.php',
		'script_function' => 'ss_fixture',
		'arg_index'       => 'IDX',
		'arg_query'       => 'QRY',
		'arg_get'         => 'GET',
		'fields'          => ['ifIndex' => ['query_name' => 'ifIndex', 'direction' => 'input']],
	];

	if ($GLOBALS['fixture_num_column']) {
		$xml['arg_num_indexes'] = 'NUM';
	}

	return $xml;
}

/**
 * Record each command instead of running it.
 *
 * @param string $command     The shell command
 * @param int    $return_code Unused
 *
 * @return array One line of output
 */
function exec_into_array($command, &$return_code = 0) {
	$GLOBALS['fixture_commands'][] = $command;

	return ['1'];
}

/**
 * Record the recache stack instead of writing it.
 *
 * @param int   $host_id       The device id
 * @param int   $data_query_id The data query id
 * @param array $recache_stack The queued entries
 *
 * @return void
 */
function poller_update_poller_reindex_from_buffer($host_id, $data_query_id, &$recache_stack) {
	$GLOBALS['fixture_recache'] = $recache_stack;
}

/**
 * Record each poller item instead of building it.
 *
 * @param mixed ...$arguments The api_poller_cache_item_add() arguments
 *
 * @return string The command argument
 */
function api_poller_cache_item_add(...$arguments) {
	$GLOBALS['fixture_items'][] = $arguments[7];

	return $arguments[7];
}

/**
 * Return the rows each caller asks for.
 *
 * @param string $sql    The query
 * @param array  $params The bound parameters
 *
 * @return array
 */
function db_fetch_row_prepared($sql, $params = []) {
	if (str_contains($sql, 'host_snmp_query')) {
		return ['reindex_method' => $GLOBALS['fixture_reindex'], 'sort_field' => 'ifIndex'];
	}

	if (str_contains($sql, 'data_template_data AS dtd')) {
		return ['id' => 4, 'type_id' => $GLOBALS['fixture_type_id'], 'data_template_data_id' => 5, 'data_template_id' => 3, 'active' => 'on', 'rrd_step' => 300];
	}

	return ['id' => 2, 'snmp_version' => 0];
}

/**
 * Return one cached index and one data source output.
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

	if (str_contains($sql, 'host_snmp_cache')) {
		return [['snmp_index' => '1', 'oid' => '', 'field_value' => '1']];
	}

	return [];
}

/**
 * Return the data input type, or a template id.
 *
 * @param string $sql    The query
 * @param array  $params The bound parameters
 *
 * @return int
 */
function db_fetch_cell_prepared($sql, $params = []) {
	return str_contains($sql, 'data_input.type_id') ? $GLOBALS['fixture_type_id'] : 5;
}

/**
 * Quote without a database connection.
 *
 * @param mixed $value The value
 *
 * @return string
 */
function db_qstr($value) {
	return "'" . addslashes((string) $value) . "'";
}

/**
 * Accept every data input.
 *
 * @param int $data_input_id The data input id
 *
 * @return bool
 */
function data_input_whitelist_check($data_input_id) {
	return true;
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
 * Return a fixed item name.
 *
 * @param int $data_template_rrd_id The data template rrd id
 *
 * @return string
 */
function get_data_source_item_name($data_template_rrd_id) {
	return 'fixture';
}

/**
 * Accept the index order.
 *
 * @param array $raw_xml The query definition
 *
 * @return bool
 */
function verify_index_order($raw_xml) {
	return true;
}

/**
 * Return the sort field.
 *
 * @param int $host_id       The device id
 * @param int $snmp_query_id The data query id
 *
 * @return string
 */
function get_best_data_query_index_type($host_id, $snmp_query_id) {
	return 'ifIndex';
}

/**
 * Discard the data query debug trace.
 *
 * @param mixed ...$arguments The trace arguments
 *
 * @return float
 */
function query_debug_timer_offset(...$arguments) {
	return 0.0;
}

/**
 * Discard the debug log section.
 *
 * @param mixed ...$arguments The section arguments
 *
 * @return void
 */
function debug_log_insert_section_start(...$arguments) {
}

/**
 * Discard the debug log line.
 *
 * @param mixed ...$arguments The line arguments
 *
 * @return void
 */
function debug_log_insert(...$arguments) {
}

/**
 * Discard the debug log section end.
 *
 * @param mixed ...$arguments The section arguments
 *
 * @return void
 */
function debug_log_insert_section_end(...$arguments) {
}

\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/data_query.php', 'query_script_host', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/poller.php', 'update_reindex_cache', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/utility.php', 'update_poller_cache', __NAMESPACE__);

/**
 * Reset the recorders and choose which argument kind is rejected.
 *
 * @param string $reject     The argument marker to reject, '' for none
 * @param bool   $num_column Whether the query defines arg_num_indexes
 *
 * @return void
 */
function fixture_reset($reject, $num_column = true) {
	$GLOBALS['fixture_reject']     = $reject;
	$GLOBALS['fixture_num_column'] = $num_column;
	$GLOBALS['fixture_commands']   = [];
	$GLOBALS['fixture_recache']    = [];
	$GLOBALS['fixture_items']      = [];
}

$results = ['query' => [], 'reindex' => [], 'poller' => []];

// query_script_host() stops at the first rejected command it would run.
foreach (['NUM', 'IDX', 'QRY'] as $reject) {
	fixture_reset($reject);
	$results['query'][$reject] = ['result' => query_script_host(2, 9), 'commands' => $GLOBALS['fixture_commands']];
}

// update_reindex_cache() queues nothing for a rejected command.
$reindex_cases = [
	'script_num'       => [DATA_INPUT_TYPE_SCRIPT_QUERY, DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE, true, 'NUM'],
	'script_count'     => [DATA_INPUT_TYPE_SCRIPT_QUERY, DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE, false, 'IDX'],
	'server_num'       => [DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER, DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE, true, 'NUM'],
	'field_verify_get' => [DATA_INPUT_TYPE_SCRIPT_QUERY, DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION, true, 'GET'],
];

foreach ($reindex_cases as $label => [$type_id, $method, $num_column, $reject]) {
	$GLOBALS['fixture_type_id'] = $type_id;
	$GLOBALS['fixture_reindex'] = $method;

	foreach (['rejected' => $reject, 'accepted' => ''] as $case => $marker) {
		fixture_reset($marker, $num_column);
		update_reindex_cache(2, 9);
		$results['reindex'][$label][$case] = count($GLOBALS['fixture_recache']);
	}
}

// update_poller_cache() adds no poller item for a rejected command.
$data_source = ['id' => 1, 'host_id' => 2, 'snmp_query_id' => 9, 'snmp_index' => '1', 'data_template_id' => 3, 'poller_id' => 1];

foreach (['script' => DATA_INPUT_TYPE_SCRIPT_QUERY, 'server' => DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER] as $label => $type_id) {
	$GLOBALS['fixture_type_id'] = $type_id;

	foreach (['rejected' => 'GET', 'accepted' => ''] as $case => $marker) {
		fixture_reset($marker);
		$results['poller'][$label][$case] = update_poller_cache($data_source);
	}
}

print json_encode($results, JSON_THROW_ON_ERROR) . "\n";
