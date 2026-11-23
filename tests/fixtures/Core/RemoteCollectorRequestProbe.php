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
 * Runs the unchanged call_remote_data_collector() from lib/functions.php in
 * its own process, with the database, the resolver and the request answered
 * from the scenario, and prints what was requested and logged.
 */

namespace CactiRemoteCollectorRequestFixture;

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

function __($text) {
	return $text;
}

function db_fetch_cell_prepared($sql, $params = array()) {
	return $GLOBALS['scenario']['hostname'];
}

function gethostbyname($hostname) {
	$GLOBALS['result']['lookups'][] = $hostname;

	return isset($GLOBALS['scenario']['dns'][$hostname]) ? $GLOBALS['scenario']['dns'][$hostname] : $hostname;
}

function get_url_type() {
	return $GLOBALS['scenario']['scheme'];
}

function get_default_contextoption() {
	return $GLOBALS['scenario']['context'];
}

function remote_agent_read_response($url, $context) {
	$GLOBALS['result']['requests'][] = array($url, stream_context_get_options($context));

	return $GLOBALS['scenario']['reply'];
}

function cacti_log($message, $output = false, $facility = '') {
	$GLOBALS['result']['log'][] = $facility . ': ' . $message;
}

function debounce_run_notification($id) {
	return true;
}

function admin_email($subject, $message) {
	return true;
}

\cacti_fixture_source_function($root . '/lib/functions.php', 'call_remote_data_collector', __NAMESPACE__);

function remote_collector_request(array $scenario) {
	$GLOBALS['scenario'] = $scenario + array('dns' => array(), 'scheme' => 'http', 'context' => array(), 'reply' => 'collector reply');
	$GLOBALS['result']   = array('lookups' => array(), 'requests' => array(), 'log' => array());

	$GLOBALS['result']['reply'] = call_remote_data_collector(2, '/cacti/remote_agent.php?action=ping&host_id=7');

	return $GLOBALS['result'];
}

print json_encode(remote_collector_request(...json_decode($argv[1], true)), JSON_THROW_ON_ERROR);
