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
 * Runs the unchanged api_auth_logout_everywhere() from auth_profile.php in
 * its own process and prints the statements it sends to the database.
 */

namespace CactiLogoutEverywhereFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);

function db_execute_prepared($sql, $params = array()) {
	$GLOBALS['queries'][] = array(preg_replace('/\s+/', ' ', $sql), $params);

	return true;
}

function session_id() {
	return 'current-session-id';
}

\cacti_fixture_source_function($root . '/auth_profile.php', 'api_auth_logout_everywhere', __NAMESPACE__);

function logout_everywhere($user_id) {
	$_SESSION           = array('sess_user_id' => $user_id);
	$GLOBALS['queries'] = array();

	api_auth_logout_everywhere();

	return $GLOBALS['queries'];
}

print json_encode(logout_everywhere(...json_decode($argv[1], true)), JSON_THROW_ON_ERROR);
