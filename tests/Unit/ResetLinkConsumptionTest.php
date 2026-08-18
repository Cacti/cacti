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

$root = dirname(__DIR__, 2);

/* The reset-link consumption path (auth_resetpassword.php, action=resetpassword)
 * boots the full request stack, so it cannot be required in isolation. These
 * tests cover the account-acceptance decision two ways: a stub that mirrors the
 * production SQL constraints, and a source assertion that the consumption query
 * no longer gates on password_change.
 *
 * Coverage note: the hash+expiry validity check and the password-change UPDATE
 * are not exercised here; they remain covered by the source-level assertions in
 * AuthSystemCorrectnessTest. */

/* fetch_user mirrors the WHERE clause of the consumption query after the fix:
 * id match, realm = 0, enabled = "on". password_change is intentionally absent. */
function reset_consume_fetch_user(array $accounts, int $user_id) : array {
	foreach ($accounts as $row) {
		if ($row['id'] === $user_id && $row['realm'] === 0 && $row['enabled'] === 'on') {
			return $row;
		}
	}

	return [];
}

test('admin-issued reset link consumes for an account with password_change off', function () {
	// user_admin.php creates reset hashes for new/notified users that may have
	// password_change unset. The link must still let them set a password.
	$accounts = [
		['id' => 7, 'realm' => 0, 'enabled' => 'on', 'password_change' => ''],
	];

	$user = reset_consume_fetch_user($accounts, 7);

	expect($user)->not->toBeEmpty()
		->and($user['id'])->toBe(7);
});

test('reset link consumes for a self-service account with password_change on', function () {
	$accounts = [
		['id' => 8, 'realm' => 0, 'enabled' => 'on', 'password_change' => 'on'],
	];

	expect(reset_consume_fetch_user($accounts, 8))->not->toBeEmpty();
});

test('reset link is rejected for a disabled account', function () {
	$accounts = [
		['id' => 9, 'realm' => 0, 'enabled' => '', 'password_change' => 'on'],
	];

	expect(reset_consume_fetch_user($accounts, 9))->toBeEmpty();
});

test('reset link is rejected for a non-local realm account', function () {
	$accounts = [
		['id' => 10, 'realm' => 2, 'enabled' => 'on', 'password_change' => 'on'],
	];

	expect(reset_consume_fetch_user($accounts, 10))->toBeEmpty();
});

test('the consumption query in source does not gate on password_change', function () use ($root) {
	$reset   = file_get_contents($root . '/auth_resetpassword.php');
	$consume = substr($reset, strpos($reset, "case 'resetpassword'"));
	$consume = substr($consume, 0, strpos($consume, '// Get passwords entered for change'));

	expect($consume)->toContain('FROM user_auth')
		->and($consume)->toContain('AND realm = 0')
		->and($consume)->toContain('AND enabled = "on"')
		->and($consume)->not->toContain('AND password_change = "on"');
});
