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
 * The probe runs the unchanged remote_agent_validate_effective_user() and
 * get_graph_data() from remote_agent.php with the request and the database
 * answered there. User 3 is enabled, user 4 is disabled, no other user
 * exists.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function remote_agent_effective_user(...$arguments) {
	return cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/RemoteAgentEffectiveUserProbe.php', array(json_encode($arguments)));
}

test('only an existing enabled user is accepted', function ($user_id, $expected) {
	expect(remote_agent_effective_user('validate', $user_id)['user'])->toBe($expected);
})->with(array(
	'enabled'      => array('3', 3),
	'enabled int'  => array(3, 3),
	'disabled'     => array('4', false),
	'unknown'      => array('99', false),
	'zero'         => array('0', false),
	'negative'     => array('-3', false),
	'empty'        => array('', false),
	'not a number' => array('3 OR 1', false),
));

test('a graph for an enabled user is drawn for that user', function () {
	expect(remote_agent_effective_user('graph', array('effective_user' => '3')))->toBe(array(
		'status'   => 200,
		'log'      => array(),
		'graphs'   => array(array(12, 3)),
		'returned' => true,
		'output'   => 'image = 1',
	));
});

test('a graph for a disabled or unknown user is not drawn', function ($user_id) {
	expect(remote_agent_effective_user('graph', array('effective_user' => $user_id)))->toBe(array(
		'status'   => 403,
		'log'      => array('SECURITY: WARNING: Rejected a Remote Agent graph request for an unknown or disabled effective user.'),
		'graphs'   => array(),
		'returned' => false,
		'output'   => '',
	));
})->with(array(
	'disabled' => array('4'),
	'unknown'  => array('99'),
	'zero'     => array('0'),
));

test('a request that names no user is unchanged', function () {
	$result = remote_agent_effective_user('graph', array('graph_height' => 0));

	expect($result['returned'])->toBeTrue()
		->and($result['graphs'])->toBe(array(array(12, 0)));
});
