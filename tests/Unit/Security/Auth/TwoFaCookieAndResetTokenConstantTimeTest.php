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
 * GHSA-4qx8-jj2h-p7q2: the 2FA bypass-cookie and password-reset token
 * comparisons used === / != , leaking a timing side-channel. Both must use
 * hash_equals() so the compare time is independent of how many leading bytes
 * match.
 */

$root = dirname(__DIR__, 4);

$twoFaSource = file_get_contents($root . '/auth_2fa.php');
$resetSource = file_get_contents($root . '/auth_resetpassword.php');

test('GHSA-4qx8: the 2FA bypass cookie is compared with hash_equals', function () use ($twoFaSource) {
	expect($twoFaSource)->toContain('hash_equals(hash_hmac(')
		->and($twoFaSource)->not->toContain("\$tfaCookeHash === hash_hmac(");
});

test('GHSA-4qx8: the reset token is compared with hash_equals at both sites', function () use ($resetSource) {
	expect($resetSource)->not->toContain("\$hash['hash'] != \$user_hash")
		->and(substr_count($resetSource, 'hash_equals((string) $hash[\'hash\'], (string) $user_hash)'))->toBe(2);
});
