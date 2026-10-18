<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_authentication(): void {
	global $error, $error_msg, $_CACTI_REQUEST;
	$session  = $_SESSION;
	$requests = [$_CACTI_REQUEST ?? [], $_REQUEST, $_GET, $_POST];
	$options  = ['secpass_lockfailed' => '3', 'secpass_unlocktime' => '1', 'secpass_forceold' => '',
		'secpass_expireaccount'          => '1', 'secpass_minlen' => '8', 'secpass_reqnum' => 'on',
		'secpass_reqmixcase'             => 'on', 'secpass_reqspec' => 'on', 'secpass_pwnedcheck' => '', 'secpass_history' => '2'];
	$previous = [];

	foreach ($options as $name => $value) {
		$previous[$name] = read_config_option($name);
		set_config_option($name, $value);
	}
	$password     = 'Fixture Password 2!';
	$originalHash = password_hash($password, PASSWORD_DEFAULT);
	$user         = (int) sql_save(['id' => 0, 'username' => 'coverage_local', 'realm' => 0, 'enabled' => 'on', 'password' => $originalHash], 'user_auth');
	library_expect($user > 0, true, 'Native local authentication account exists');
	$provider = new \Cacti\Auth\LocalAuthLoginProvider();

	try {
		foreach ([['', $password], ['coverage_missing', ''], ['coverage_missing', $password]] as [$username, $credential]) {
			$error     = false;
			$error_msg = '';
			$result    = $provider->authenticate($username, $credential);
			library_expect($result->success, false, 'An absent account cannot authenticate');
			library_expect($result->user, null, 'Failed authentication does not return an account');
		}

		foreach (['', 'wrong password', 'wrong again'] as $attempt => $credential) {
			$error     = false;
			$error_msg = '';
			library_expect($provider->authenticate('coverage_local', $credential)->success, false, 'Invalid local credential is rejected');
			library_expect((int) db_fetch_cell_prepared('SELECT failed_attempts FROM user_auth WHERE id = ?', [$user]), $attempt + 1, 'Failed credential increments the database counter');
		}
		library_expect(db_fetch_cell_prepared('SELECT locked FROM user_auth WHERE id = ?', [$user]), 'on', 'Threshold locks the account');
		library_expect($provider->authenticate('coverage_local', $password)->success, false, 'A locked account rejects the correct password');
		library_expect(cacti_auth_transition($user), false, 'Locked account cannot transition the session');
		library_expect(db_fetch_cell_prepared('SELECT password FROM user_auth WHERE id = ?', [$user]), $originalHash, 'Rejected authentication preserves password hash');
		db_execute_prepared('UPDATE user_auth SET lastfail = ? WHERE id = ?', [time() - 120, $user]);
		auth_checkclear_lockout('coverage_local', 0);
		library_expect(db_fetch_cell_prepared('SELECT locked FROM user_auth WHERE id = ?', [$user]), '', 'Expired lockout is cleared');
		library_expect((int) db_fetch_cell_prepared('SELECT failed_attempts FROM user_auth WHERE id = ?', [$user]), 0, 'Expired lockout resets its counter');
		$error     = false;
		$error_msg = '';
		$result    = $provider->authenticate('coverage_local', $password);
		library_expect($result->success, true, 'Unlocked account authenticates with the correct password');
		library_expect((int) $result->user['id'], $user, 'Authentication returns the stored account identity');
		library_expect((int) db_fetch_cell_prepared('SELECT lastlogin FROM user_auth WHERE id = ?', [$user]) >= time() - 10, true, 'Successful login records activity');

		foreach ([SESS_USER_REALMS, SESS_AUTH_NAMES, SESS_TREE_PERMS, SESS_SIMPLE_PERMS, SESS_SIMPLE_TEMPLATE_PERMS] as $key) {
			$_SESSION[$key] = ['previous viewer'];
		}
		library_expect(cacti_auth_transition($user), true, 'Unlocked account can transition the session');

		foreach ([SESS_USER_REALMS, SESS_AUTH_NAMES, SESS_TREE_PERMS, SESS_SIMPLE_PERMS, SESS_SIMPLE_TEMPLATE_PERMS] as $key) {
			library_expect(isset($_SESSION[$key]), false, 'Identity transition clears ' . $key);
		}
		user_disable($user);
		library_expect($provider->authenticate('coverage_local', $password)->success, false, 'Disabled account cannot authenticate');
		library_expect((int) db_fetch_cell_prepared('SELECT failed_attempts FROM user_auth WHERE id = ?', [$user]), 0, 'Disabled-account rejection does not consume password attempts');
		user_enable($user);
		// Historical MD5 record for the disposable "Fixture Password 2!" account.
		$legacyHash = '31e814b17e3c758e5fdead1029f9a67f';
		db_execute_prepared('UPDATE user_auth SET password = ? WHERE id = ?', [$legacyHash, $user]);
		$error     = false;
		$error_msg = '';
		library_expect($provider->authenticate('coverage_local', $password)->success, true, 'Legacy password authenticates before transparent rehash');
		$rehashed = db_fetch_cell_prepared('SELECT password FROM user_auth WHERE id = ?', [$user]);
		library_expect(password_verify($password, $rehashed), true, 'Rehash persists a verifiable modern password');
		library_expect($rehashed !== $legacyHash, true, 'Legacy hash is replaced only after success');

		foreach (['short', 'LongPassword!', 'longpassword2!', 'LongPassword2'] as $invalid) {
			library_expect(secpass_check_pass($invalid) !== 'ok', true, 'Password policy rejects a missing requirement');
		}
		library_expect(secpass_check_pass($password), 'ok', 'Password satisfying each requirement is admitted');
		db_execute_prepared('UPDATE user_auth SET password_history = ? WHERE id = ?', [password_hash('Old Fixture 1!', PASSWORD_DEFAULT), $user]);
		library_expect(secpass_check_history($user, $password), false, 'Current password cannot be reused');
		library_expect(secpass_check_history($user, 'Old Fixture 1!'), false, 'Historical password cannot be reused');
		library_expect(secpass_check_history($user, 'New Fixture 3!'), true, 'A new password is permitted');

		foreach ([0, 999999] as $realm) {
			srv('realm', (string) $realm);
			srv('login_password', $password);
			library_expect(login_providers_login_process('coverage_local'), [], 'Unavailable provider realm cannot authenticate a local account');
		}
	} finally {
		user_remove($user);

		foreach ($previous as $name => $value) {
			set_config_option($name, $value);
		}
		$_SESSION                                   = $session;
		[$_CACTI_REQUEST, $_REQUEST, $_GET, $_POST] = $requests;
	}
}
