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
 * Two brute-force defenses: the failed-attempt counter must increment atomically
 * in the database (a read-modify-write in PHP lets concurrent guesses undercount
 * past the lockout threshold), and an unknown username must cost the same as a
 * known one (otherwise the hash-vs-immediate-return timing delta enumerates
 * valid usernames).
 */

$authSrc      = file_get_contents(dirname(__DIR__, 2) . '/lib/auth.php');
$localAuthSrc = file_get_contents(dirname(__DIR__, 2) . '/lib/Auth/LocalAuthLoginProvider.php');

function _auth_fn_body(string $src, string $fn): string {
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

test('the lockout counter is incremented atomically in SQL', function () use ($authSrc) {
	$body = _auth_fn_body($authSrc, 'auth_process_lockout');

	// atomic increment, then read the authoritative value back for the lock check
	expect($body)->toContain('failed_attempts = failed_attempts + 1');
	// no PHP-side read-modify-write of the counter
	expect($body)->not->toContain("intval(\$user['failed_attempts']) + 1");
	expect($body)->not->toContain('failed_attempts = ?');
});

test('an unknown username is verified against a decoy hash for constant time', function () use ($localAuthSrc) {
	$body = _auth_fn_body($localAuthSrc, 'verifyCredential');

	// the else (no such user) branch runs a throw-away verify so timing matches;
	// the decoy tracks the algorithm and cost of new hashes instead of a literal
	expect($body)->toContain('compat_password_verify((string) $password, cacti_password_decoy_hash())');
	expect($body)->not->toContain("compat_password_verify((string) \$password, '\$");
});
