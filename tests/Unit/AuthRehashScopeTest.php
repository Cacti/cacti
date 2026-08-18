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
 * The local-login password rehash must be scoped to realm 0 and must only run
 * when the primary login succeeded. The old code wrote the password column with
 * no realm predicate (overwriting other realms' rows for the same username) and
 * re-verified the password a second time, which also repopulated $user for a
 * correct password on a locked account.
 */

$authSrc = file_get_contents(CACTI_PATH_LIBRARY . '/auth.php');

function _local_login_body(string $src): string {
	$start = strpos($src, 'function local_auth_login_process(');
	expect($start)->not->toBeFalse();
	$end = strpos($src, "\nfunction ", $start + 1);

	return substr($src, $start, ($end === false ? strlen($src) : $end) - $start);
}

test('the rehash update is scoped to realm 0', function () use ($authSrc) {
	$body = _local_login_body($authSrc);

	$update = strpos($body, 'UPDATE user_auth');
	expect($update)->not->toBeFalse();

	// the write that follows must carry the realm predicate
	$tail = substr($body, $update);
	expect($tail)->toContain('SET password = ?');
	expect(preg_match('/UPDATE user_auth\s+SET password = \?\s+WHERE username = \?\s+AND realm = 0/', $body))->toBe(1);
});

test('rehash only runs after a successful login, without a second verify', function () use ($authSrc) {
	$body = _local_login_body($authSrc);

	// gated on the successful secpass result, not an independent re-verify
	expect($body)->toContain('if (cacti_sizeof($user)) {');
	// the redundant second password verification is gone
	expect($body)->not->toContain('$valid = compat_password_verify(');
	// and it no longer re-reads the hash it already has from secpass_login_process()
	expect($body)->not->toContain("db_fetch_cell_prepared('SELECT password");
});
