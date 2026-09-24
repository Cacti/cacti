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

$localAuthSrc = file_get_contents(dirname(__DIR__, 2) . '/lib/Auth/LocalAuthLoginProvider.php');

function _local_auth_fn_body(string $src, string $fn): string {
	$start = strpos($src, "function $fn(");
	expect($start)->not->toBeFalse();

	$depth = 0;
	$len   = strlen($src);

	for ($i = strpos($src, '{', $start); $i < $len; $i++) {
		if ($src[$i] === '{') {
			$depth++;
		} elseif ($src[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($src, $start, $i - $start + 1);
			}
		}
	}

	expect(false)->toBeTrue("$fn() is unbalanced");
}

test('the rehash update is scoped to realm 0', function () use ($localAuthSrc) {
	$body = _local_auth_fn_body($localAuthSrc, 'rehashIfNeeded');

	expect($body)->toContain('SET password = ?');
	expect(preg_match('/UPDATE user_auth\s+SET password = \?\s+WHERE username = \?\s+AND realm = 0/', $body))->toBe(1);
});

test('rehash only runs after a successful login, without a second verify', function () use ($localAuthSrc) {
	$authenticateBody = _local_auth_fn_body($localAuthSrc, 'authenticate');

	// verifyCredential() failure returns before rehashIfNeeded() is ever called
	$guard  = strpos($authenticateBody, '!cacti_sizeof($user)');
	$rehash = strpos($authenticateBody, 'rehashIfNeeded(');
	expect($guard)->not->toBeFalse();
	expect($rehash)->not->toBeFalse();
	expect($guard)->toBeLessThan($rehash);

	$rehashBody = _local_auth_fn_body($localAuthSrc, 'rehashIfNeeded');

	// the redundant second password verification is gone
	expect($rehashBody)->not->toContain('compat_password_verify(');
	// and it no longer re-reads the hash it already has from verifyCredential()
	expect($rehashBody)->not->toContain("db_fetch_cell_prepared('SELECT password");
});
