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
 * Runs the unchanged remote_agent_validate_effective_user() and
 * get_graph_data() from remote_agent.php in their own process, with the
 * request and the database answered from the scenario.
 */

namespace CactiRemoteAgentEffectiveUserFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';

function db_fetch_cell_prepared($sql, $params = array()) {
	$users = array(3 => 'on', 4 => '');

	return isset($users[$params[0]]) ? $users[$params[0]] : false;
}

function get_filter_request_var($name) {
	return get_request_var($name);
}

function get_request_var($name) {
	return isset($GLOBALS['request'][$name]) ? $GLOBALS['request'][$name] : '';
}

function isset_request_var($name) {
	return isset($GLOBALS['request'][$name]);
}

function isempty_request_var($name) {
	return empty($GLOBALS['request'][$name]);
}

function http_response_code($code = 0) {
	$GLOBALS['result']['status'] = $code;

	return true;
}

function cacti_log($message, $output = false, $facility = '') {
	$GLOBALS['result']['log'][] = $facility . ': ' . $message;
}

function rrdtool_function_graph($local_graph_id, $rra_id, $graph_data_array, $rrdtool_pipe, &$xport_meta, $user) {
	$GLOBALS['result']['graphs'][] = array($local_graph_id, $user);

	return 'image = 1';
}

\cacti_fixture_source_function($root . '/remote_agent.php', 'remote_agent_validate_effective_user', __NAMESPACE__);
\cacti_fixture_source_function($root . '/remote_agent.php', 'get_graph_data', __NAMESPACE__);

function remote_agent_effective_user($mode, $value) {
	if ($mode == 'validate') {
		return array('user' => remote_agent_validate_effective_user($value));
	}

	$GLOBALS['request'] = $value + array('local_graph_id' => 12, 'rra_id' => 0);
	$GLOBALS['result']  = array('status' => 200, 'log' => array(), 'graphs' => array());

	ob_start();

	$GLOBALS['result']['returned'] = get_graph_data();
	$GLOBALS['result']['output']   = ob_get_clean();

	return $GLOBALS['result'];
}

print json_encode(remote_agent_effective_user(...json_decode($argv[1], true)), JSON_THROW_ON_ERROR);
