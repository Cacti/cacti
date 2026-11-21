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
 * A graph request relayed between data collectors names the user it is made
 * for. The graph is only drawn when that user exists and is enabled.
 *
 * Both functions are the unchanged ones from remote_agent.php, loaded into
 * this namespace so the request and the database can be answered here.
 */

namespace RemoteAgentEffectiveUserTest;

function db_fetch_cell_prepared($sql, $params = array()) {
	$users = array(3 => 'on', 4 => '');

	return isset($users[$params[0]]) ? $users[$params[0]] : false;
}

function get_filter_request_var($name) {
	return get_request_var($name);
}

function get_request_var($name) {
	return isset($GLOBALS['remote_agent_request'][$name]) ? $GLOBALS['remote_agent_request'][$name] : '';
}

function isset_request_var($name) {
	return isset($GLOBALS['remote_agent_request'][$name]);
}

function isempty_request_var($name) {
	return empty($GLOBALS['remote_agent_request'][$name]);
}

function http_response_code($code = 0) {
	$GLOBALS['remote_agent_result']['status'] = $code;

	return true;
}

function cacti_log($message, $output = false, $facility = '') {
	$GLOBALS['remote_agent_result']['log'][] = $facility . ': ' . $message;
}

function rrdtool_function_graph($local_graph_id, $rra_id, $graph_data_array, $rrdtool_pipe, &$xport_meta, $user) {
	$GLOBALS['remote_agent_result']['graphs'][] = array($local_graph_id, $user);

	return 'image = 1';
}

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

\cacti_fixture_source_function(dirname(__DIR__, 4) . '/remote_agent.php', 'remote_agent_validate_effective_user', __NAMESPACE__);
\cacti_fixture_source_function(dirname(__DIR__, 4) . '/remote_agent.php', 'get_graph_data', __NAMESPACE__);

function remote_agent_graph_request(array $request) {
	$GLOBALS['remote_agent_request'] = $request + array('local_graph_id' => 12, 'rra_id' => 0);
	$GLOBALS['remote_agent_result']  = array('status' => 200, 'log' => array(), 'graphs' => array());

	ob_start();

	try {
		$returned = get_graph_data();
		$output   = ob_get_contents();
	} finally {
		ob_end_clean();
	}

	return array($returned, $output, $GLOBALS['remote_agent_result']);
}

test('only an existing enabled user is accepted', function ($user_id, $expected) {
	expect(remote_agent_validate_effective_user($user_id))->toBe($expected);
})->with(array(
	'enabled'       => array('3', 3),
	'enabled int'   => array(3, 3),
	'disabled'      => array('4', false),
	'unknown'       => array('99', false),
	'zero'          => array('0', false),
	'negative'      => array('-3', false),
	'empty'         => array('', false),
	'not a number'  => array('3 OR 1', false),
));

test('a graph for an enabled user is drawn for that user', function () {
	[$returned, $output, $result] = remote_agent_graph_request(array('effective_user' => '3'));

	expect($returned)->toBeTrue()
		->and($output)->toBe('image = 1')
		->and($result)->toBe(array('status' => 200, 'log' => array(), 'graphs' => array(array(12, 3))));
});

test('a graph for a disabled or unknown user is not drawn', function ($user_id) {
	[$returned, $output, $result] = remote_agent_graph_request(array('effective_user' => $user_id));

	expect($returned)->toBeFalse()
		->and($output)->toBe('')
		->and($result['status'])->toBe(403)
		->and($result['graphs'])->toBe(array())
		->and($result['log'])->toBe(array('SECURITY: WARNING: Rejected a Remote Agent graph request for an unknown or disabled effective user.'));
})->with(array(
	'disabled' => array('4'),
	'unknown'  => array('99'),
	'zero'     => array('0'),
));

test('a request that names no user is unchanged', function () {
	[$returned, , $result] = remote_agent_graph_request(array());

	expect($returned)->toBeTrue()
		->and($result['graphs'])->toBe(array(array(12, 0)));
});
