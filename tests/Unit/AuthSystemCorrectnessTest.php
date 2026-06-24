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

test('remember-me restored sessions still require 2fa when enabled', function () use ($root) {
	$auth = file_get_contents($root . '/include/auth.php');
	$auth_lib = file_get_contents($root . '/lib/auth.php');

	expect($auth)->toContain('if (empty($_SESSION[SESS_USER_2FA]) && db_column_exists(\'user_auth\', \'tfa_enabled\'))')
		->and($auth)->not->toContain('if (!$cookie_user && empty($_SESSION[SESS_USER_2FA])')
		->and($auth_lib)->toContain('auth_cookie_user_currently_allowed($user_info)')
		->and($auth_lib)->toContain('function auth_cookie_user_currently_allowed(array $user_info) : bool')
		->and($auth_lib)->toContain("if ((\$user_info['enabled'] ?? '') != 'on')")
		->and($auth_lib)->toContain('return auth_user_has_access($user_info);');
});

test('2fa lifetime uses configured token lifetime in minutes', function () use ($root) {
	$tfa = file_get_contents($root . '/auth_2fa.php');

	expect($tfa)->toContain("read_config_option('secpass_2fatime')")
		->and($tfa)->toContain('$tfaTime = time() - ($tfaMins * 60);')
		->and($tfa)->toContain("explode(':', \$_COOKIE[session_name() . '_otp'], 2)")
		->and($tfa)->not->toContain('secpass_mfatime')
		->and($tfa)->not->toContain('$tfaBase =');
});

test('password reset token validation rejects expired and invalid hashes before use', function () use ($root) {
	$reset = file_get_contents($root . '/auth_resetpassword.php');

	expect($reset)->toContain('AND expiry > NOW()')
		->and($reset)->toContain('$action       = \'formidentity\';' . "\n\n\t\t\tbreak;")
		->and($reset)->toContain('Password reset is not available for this account.')
		->and($reset)->toContain('AND realm = 0');
});

test('reset-link consumption does not gate the account lookup on password_change', function () use ($root) {
	$reset = file_get_contents($root . '/auth_resetpassword.php');

	// admin-issued links (user_admin.php) target accounts without password_change set;
	// the consumption query must accept any account that legitimately received a link.
	$consume = substr($reset, strpos($reset, "case 'resetpassword'"));
	$consume = substr($consume, 0, strpos($consume, '// Get passwords entered for change'));

	expect($consume)->toContain('FROM user_auth')
		->and($consume)->toContain('AND enabled = "on"')
		->and($consume)->not->toContain('AND password_change = "on"');
});

test('logout clears the remember-me and otp cookies by their actual names', function () use ($root) {
	$functions = file_get_contents($root . '/lib/functions.php');

	expect($functions)->toContain("'cacti_remembers'")
		->and($functions)->toContain("(string) session_name() . '_otp'")
		->and($functions)->not->toContain('cacti_rembers');
});

test('basic auth shortcut checks disabled accounts and effective access before creating a session', function () use ($root) {
	$auth = file_get_contents($root . '/include/auth.php');

	expect($auth)->toContain("if (\$current_user['enabled'] != 'on')")
		->and($auth)->toContain('if (!auth_user_has_access($current_user))')
		->and(strpos($auth, "if (\$current_user['enabled'] != 'on')"))->toBeLessThan(strpos($auth, '$_SESSION[SESS_USER_ID]     = $current_user[\'id\'];'))
		->and(strpos($auth, 'if (!auth_user_has_access($current_user))'))->toBeLessThan(strpos($auth, '$_SESSION[SESS_USER_ID]     = $current_user[\'id\'];'));
});

test('2fa profile mutations require post requests and csrf-bearing ajax calls', function () use ($root) {
	$profile = file_get_contents($root . '/auth_profile.php');
	$auth    = file_get_contents($root . '/lib/auth.php');

	expect($profile)->toContain('function auth_profile_require_post()')
		->and($profile)->toContain('REQUEST_METHOD')
		->and($profile)->toContain('$.post(\'auth_profile.php?action=enable_2fa\', {__csrf_magic: csrfMagicToken}')
		->and($profile)->toContain('$.post(\'auth_profile.php?action=disable_2fa\', {__csrf_magic: csrfMagicToken}')
		->and($profile)->toContain('$.post(\'auth_profile.php?action=verify_2fa\', {code: code, __csrf_magic: csrfMagicToken}')
		->and($profile)->not->toContain('$.getJSON(\'auth_profile.php?action=enable_2fa')
		->and($profile)->not->toContain('$.getJSON(\'auth_profile.php?action=disable_2fa')
		->and($profile)->not->toContain('$.getJSON(\'auth_profile.php?action=verify_2fa')
		->and($auth)->not->toContain('$result[\'secret\']');
});

test('remote agent authorization fails closed on fcrdns mismatch', function () use ($root) {
	$remote_agent = file_get_contents($root . '/remote_agent.php');

	// a PTR that exists but does not forward-confirm must still reject after the
	// inline loop was extracted into remote_agent_fcrdns_confirmed().
	expect($remote_agent)->toContain('but forward lookup does not match. Rejecting.')
		->and($remote_agent)->toContain('remote_agent_fcrdns_confirmed($client_addr, $forward_records)')
		->and($remote_agent)->not->toContain('Hostname checks will be ignored for this request.')
		->and($remote_agent)->not->toContain('$client_name = $client_addr;');
});
