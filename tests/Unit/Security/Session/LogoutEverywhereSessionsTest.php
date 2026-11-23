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
 * "Log out everywhere" removes the user's stored login tokens and ends the
 * user's other database-backed sessions. The session making the request is
 * kept so its reply can be sent.
 *
 * The probe runs the unchanged api_auth_logout_everywhere() from
 * auth_profile.php and records the statements it sends to the database.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function logout_everywhere(...$arguments) {
	return cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/LogoutEverywhereProbe.php', array(json_encode($arguments)));
}

test('the login tokens and the other sessions of the user are removed', function () {
	expect(logout_everywhere(7))->toBe(array(
		array('DELETE FROM user_auth_cache WHERE user_id = ?', array(7)),
		array('DELETE FROM sessions WHERE user_id = ? AND id != ?', array(7, 'current-session-id')),
	));
});

test('nothing is removed without a logged in user', function () {
	expect(logout_everywhere(0))->toBe(array());
});

test('the sessions table has the columns the query uses', function () {
	$schema = file_get_contents(dirname(__DIR__, 4) . '/cacti.sql');
	$start  = strpos($schema, 'CREATE TABLE `sessions`');
	$table  = substr($schema, $start, strpos($schema, ';', $start) - $start);

	expect($table)->toContain('`id` varchar(32) NOT NULL')
		->and($table)->toContain('`user_id` int(10) unsigned NOT NULL');
});
