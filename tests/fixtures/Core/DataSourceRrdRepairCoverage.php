<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
namespace CactiDataSourceRrdRepairFixture;

use RuntimeException;

/*
 * Request-coverage probe for the Data Source Info mode RRDfile repair handler.
 *
 * ds_repair() (data_sources.php) backs the "Apply Suggested Fixes" POST action.
 * It runs the rrdtool tune recommendations (add/remove Data Sources, correct
 * min/max/type/heartbeat) when the web server can write the RRDfile. The page
 * level e2e suites do not drive a Data Source whose RRDfile mismatches its Cacti
 * definition, so the handler is loaded in isolation with its I/O boundaries
 * stubbed and driven through every branch; RequestCoverage maps the hits back to
 * the source.
 */

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

if (!defined('MESSAGE_LEVEL_INFO')) {
	define('MESSAGE_LEVEL_INFO', 3);
}

if (!defined('MESSAGE_LEVEL_ERROR')) {
	define('MESSAGE_LEVEL_ERROR', 1);
}

if (!defined('RRDTOOL_OUTPUT_BOOLEAN')) {
	define('RRDTOOL_OUTPUT_BOOLEAN', 4);
}

// Mutable boundary state the stubs read, reset per scenario below.
$GLOBALS['ds_repair_state'] = [
	'authorized'  => true,
	'rrd_info'    => ['rra' => [0 => []], 'filename' => '/tmp/fixture.rrd', 'ds' => []],
	'diff'        => ['tune' => ['/tmp/fixture.rrd DS:traffic_in:COUNTER:600:0:U']],
	'writable'    => true,
	'exec_result' => true,
];

$GLOBALS['ds_repair_messages'] = [];

function gfrv($name, $filter = FILTER_VALIDATE_INT, $options = []) {
	return 5;
}
function grv($name, $default = '') {
	return 5;
}
function data_source_authorized($id) {
	return $GLOBALS['ds_repair_state']['authorized'];
}
function raise_message($id, $message = '', $level = 0) {
	$GLOBALS['ds_repair_messages'][] = $id;
}
function header($location, $replace = true, $response_code = 0) {
	// swallow the redirect; this probe runs on the CLI
}
function rrdtool_function_info($id) {
	return $GLOBALS['ds_repair_state']['rrd_info'];
}
function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}
function rrdtool_cacti_compare($id, &$info) {
	return $GLOBALS['ds_repair_state']['diff'];
}
function get_data_source_path($id, $expand) {
	return '/tmp/fixture.rrd';
}
function is_writeable($path) {
	return $GLOBALS['ds_repair_state']['writable'];
}
function read_config_option($name, $force = false) {
	return 'rrdtool';
}
function rrd_strip_control_chars($command_line) {
	return preg_replace('/[\x00-\x1f\x7f]/', '', $command_line);
}
function rrdtool_execute($command, $log_to_stdout, $output_flag, $rrdtool_pipe = false, $logopt = 'WEBLOG') {
	return $GLOBALS['ds_repair_state']['exec_result'];
}
function cacti_log($message, $print = false, $type = '', $level = 0) {
	// no-op
}
function __($text, ...$args) {
	return $args !== [] ? @vsprintf($text, $args) : $text;
}

cacti_fixture_source_function(dirname(__DIR__, 3) . '/data_sources.php', 'ds_repair', __NAMESPACE__);

/**
 * Reset the boundary state, overriding individual keys, then run the handler.
 */
function run_scenario(array $overrides) : void {
	$GLOBALS['ds_repair_state'] = array_merge([
		'authorized'  => true,
		'rrd_info'    => ['rra' => [0 => []], 'filename' => '/tmp/fixture.rrd', 'ds' => []],
		'diff'        => ['tune' => ['/tmp/fixture.rrd DS:traffic_in:COUNTER:600:0:U']],
		'writable'    => true,
		'exec_result' => true,
	], $overrides);

	ds_repair();
}

// unauthorized -> permission_denied, no repair
run_scenario(['authorized' => false]);

// RRDfile unreadable (rrdtool info returns false)
run_scenario(['rrd_info' => false]);

// RRDfile present but no RRA data
run_scenario(['rrd_info' => ['rra' => [], 'filename' => '/tmp/fixture.rrd']]);

// nothing to tune
run_scenario(['diff' => []]);

// tune needed but the RRDfile is not writable by the web server
run_scenario(['writable' => false]);

// writable: one control-character recommendation is skipped, one succeeds
run_scenario([
	'diff'        => ['tune' => ["/tmp/fixture.rrd DEL:control\x01", '/tmp/fixture.rrd DS:traffic_in:COUNTER:600:0:U']],
	'exec_result' => true,
]);

// writable: the tune command fails, so the repair is reported as failed
run_scenario(['exec_result' => false]);

$messages = $GLOBALS['ds_repair_messages'];

foreach (['permission_denied', 'ds_repair'] as $expected) {
	if (!in_array($expected, $messages, true)) {
		throw new RuntimeException('ds_repair did not raise the expected "' . $expected . '" message');
	}
}

print json_encode(['ds_repair_scenarios' => count($messages)], JSON_THROW_ON_ERROR) . "\n";
