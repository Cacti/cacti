<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-p3rg-7pc3-2h86: local login must cost the same number of bcrypt
 * verifications whether the username is unknown, disabled, or enabled, so an
 * attacker cannot enumerate accounts by login timing. secpass_login_process()
 * is the single verify path (real hash for an enabled user, a fixed dummy hash
 * for the unknown and disabled branches); local_auth_login_process() must not
 * add a second verification during the rehash step.
 */

$auth = file_get_contents(__DIR__ . '/../../../../lib/auth.php');

function p3rg_function_body(string $src, string $name): string {
	$start = strpos($src, 'function ' . $name . '(');
	expect($start)->not->toBeFalse();
	$end = strpos($src, "\nfunction ", $start + 1);

	return substr($src, $start, ($end === false ? strlen($src) : $end) - $start);
}

test('local_auth_login_process does not re-verify the password after secpass (GHSA-p3rg)', function () use ($auth) {
	$body = p3rg_function_body($auth, 'local_auth_login_process');
	expect($body)->not->toContain('compat_password_verify');
});

test('secpass_login_process keeps the real verify and dummy-verifies the unknown and disabled paths (GHSA-p3rg)', function () use ($auth) {
	$body = p3rg_function_body($auth, 'secpass_login_process');

	// the real verification against the stored hash remains
	expect($body)->toContain('compat_password_verify($password, $user[\'password\'])');

	// a fixed dummy hash is verified on both the disabled and not-found branches
	expect(substr_count($body, 'compat_password_verify($password, \'$2y$10$qgRPCKzfZe'))->toBe(2);
});
