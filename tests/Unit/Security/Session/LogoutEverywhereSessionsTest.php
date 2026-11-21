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
 * api_auth_logout_everywhere() is the unchanged function from
 * auth_profile.php, loaded into this namespace so the database calls can be
 * recorded here.
 */

namespace LogoutEverywhereSessionsTest;

function db_execute_prepared($sql, $params = array()) {
	$GLOBALS['logout_everywhere_queries'][] = array(preg_replace('/\s+/', ' ', $sql), $params);

	return true;
}

function session_id() {
	return 'current-session-id';
}

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

\cacti_fixture_source_function(dirname(__DIR__, 4) . '/auth_profile.php', 'api_auth_logout_everywhere', __NAMESPACE__);

function logout_everywhere($user_id) {
	$session  = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('sess_user_id' => $user_id);

	$GLOBALS['logout_everywhere_queries'] = array();

	try {
		api_auth_logout_everywhere();
	} finally {
		if ($session === null) {
			unset($_SESSION);
		} else {
			$_SESSION = $session;
		}
	}

	return $GLOBALS['logout_everywhere_queries'];
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
