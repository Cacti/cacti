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
 * Logging out must revoke the server-side remember-me token, and "logout
 * everywhere" must also terminate the user's other database sessions. Neither
 * happened before: cacti_cookie_logout() unsets $_COOKIE['cacti_remembers'], so
 * a clear_auth_cookie() that ran afterwards found no cookie and deleted nothing,
 * and logout_everywhere only cleared remember-me tokens.
 */

$logoutSrc  = file_get_contents(dirname(__DIR__, 2) . '/logout.php');
$profileSrc = file_get_contents(dirname(__DIR__, 2) . '/auth_profile.php');

test('logout revokes the remember-me token before the cookie is unset', function () use ($logoutSrc) {
	$clear  = strpos($logoutSrc, 'clear_auth_cookie();');
	$cookie = strpos($logoutSrc, 'cacti_cookie_logout();');

	expect($clear)->not->toBeFalse();
	expect($cookie)->not->toBeFalse();
	// clear_auth_cookie() reads $_COOKIE['cacti_remembers'], which cacti_cookie_logout() unsets
	expect($clear)->toBeLessThan($cookie);
});

test('logout revokes the token on every branch, not only the default one', function () use ($logoutSrc) {
	// exactly one call, above the timeout/disabled/remote vs default branch split
	expect(substr_count($logoutSrc, 'clear_auth_cookie();'))->toBe(1);

	$clear  = strpos($logoutSrc, 'clear_auth_cookie();');
	$branch = strpos($logoutSrc, "grv('action') == 'timeout'");

	expect($branch)->not->toBeFalse();
	expect($clear)->toBeLessThan($branch);
});

test('logout everywhere terminates other sessions, not just remember-me tokens', function () use ($profileSrc) {
	$start = strpos($profileSrc, 'function api_auth_logout_everywhere(');
	expect($start)->not->toBeFalse();
	$end   = strpos($profileSrc, "\n}", $start);
	expect($end)->not->toBeFalse();
	$body  = substr($profileSrc, $start, $end - $start);

	expect($body)->toContain('DELETE FROM user_auth_cache');
	expect($body)->toContain('DELETE FROM sessions');
	// keep the current session so the action does not log the caller out
	expect($body)->toContain('AND id != ?');
	expect($body)->toContain('session_id()');
});
