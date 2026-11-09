<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Exercise password policy, lockout and expiry handling with temporarily overridden security options. */
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

		set_config_option('secpass_lockfailed', '0');
		$error     = false;
		$error_msg = '';
		library_expect($provider->authenticate('coverage_local', 'wrong password')->success, false, 'Disabling lockout never permits an invalid credential');
		library_expect($error, true, 'Failed login still sets the authentication error without lockout');
		library_expect($error_msg, __('Access Denied! Login Failed.'), 'Failed login reports the normal error without lockout');
		library_expect((int) db_fetch_cell_prepared('SELECT failed_attempts FROM user_auth WHERE id = ?', [$user]), 0, 'Disabled lockout leaves the failure counter unchanged');
		set_config_option('secpass_lockfailed', '3');

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

/** Exercise login paths that end the request or depend on missing templates in their own processes. */
function library_authentication_boundaries(): void {
	global $error, $error_msg;
	$options  = ['secpass_forceold' => 'on', 'secpass_minlen' => '8', 'secpass_reqnum' => 'on', 'secpass_reqmixcase' => 'on',
		'secpass_reqspec'              => 'on', 'secpass_pwnedcheck' => '', 'secpass_lockfailed' => '0', 'secpass_expireaccount' => '0',
		'auth_cache_enabled'           => 'on', 'auth_method' => '1', 'user_template' => '-1', 'guest_user' => '-1',
		'cn_full_name'                 => 'cn', 'cn_email' => 'mail'];
	$previous = [];

	foreach ($options as $name => $value) {
		$previous[$name] = read_config_option($name, true);
		set_config_option($name, $value);
	}
	$users = [];

	try {
		// A stored password that predates the complexity policy forces a change at the next login.
		$users[] = $forced = (int) sql_save(['id' => 0, 'username' => 'coverage_forced', 'realm' => 0, 'enabled' => 'on', 'password' => password_hash('weak', PASSWORD_DEFAULT)], 'user_auth');
		library_expect($forced > 0, true, 'Forced password fixture owns its account');
		$output = library_child('(new \Cacti\Auth\LocalAuthLoginProvider())->authenticate("coverage_forced", "weak"); print "login continued";');
		library_expect(str_contains($output, 'login continued'), false, 'A noncompliant password stops the login before the session is established');
		library_expect(db_fetch_cell_prepared('SELECT must_change_password FROM user_auth WHERE id = ?', [$forced]), 'on', 'A noncompliant password is flagged for a mandatory change');

		// Once output has started PHP cannot issue a new session id, so the transition must refuse the login.
		$output = library_child('session_start(); print "output before transition|"; var_export(cacti_auth_transition((int) $argv[1])); session_destroy();', [(string) $forced]);
		library_expect($output, 'output before transition|false', 'Session fixation protection refuses a login it cannot rotate');

		// A connection-local table shadows user_auth, so the account, template and guest lookups all fail.
		$output = library_child('$database_sessions["$database_hostname:$database_port:$database_default"]->exec("CREATE TEMPORARY TABLE user_auth (fixture INT)");
basic_auth_login_process("coverage_basic_unknown"); print "login continued";');
		library_expect(str_contains($output, 'both Template and Guest Users are not defined'), true, 'Web server login without a readable account, template or guest explains the denial');
		library_expect(str_contains($output, 'login continued'), false, 'Web server login without a readable account, template or guest stops the request');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM user_auth WHERE username = ?', ['coverage_basic_unknown']), 0, 'A denied web server login creates no account');

		$users[] = $cookie = (int) sql_save(['id' => 0, 'username' => 'coverage_cookie', 'realm' => 0, 'enabled' => 'on', 'password' => ''], 'user_auth');
		library_expect(db_execute_prepared('INSERT INTO user_auth_realm (user_id, realm_id) VALUES (?, 8)', [$cookie]), true, 'Cookie fixture account has a console realm');
		$token = bin2hex(random_bytes(32));
		library_expect(db_execute_prepared('INSERT INTO user_auth_cache (user_id, hostname, last_update, token) VALUES (?, ?, NOW(), ?)', [$cookie, '127.0.0.1', hash('sha512', $token)]), true, 'Remember-me token is stored');
		$output = library_child('$_COOKIE["cacti_remembers"] = $argv[1]; var_export(check_auth_cookie());', [$cookie . ',' . $token]);
		library_expect($output, (string) $cookie, 'A current remember-me token restores its own account');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM user_log WHERE user_id = ? AND result = 2', [$cookie]), 1, 'Cookie authentication is recorded in the login history');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM user_auth_cache WHERE user_id = ? AND token = ?', [$cookie, hash('sha512', $token)]), 0, 'Cookie authentication rotates the stored token');

		// The logout message names an unrelated destination, so the strict SAML settings reject it.
		$log    = read_config_option('path_cactilog');
		$offset = is_file($log) ? filesize($log) : 0;
		$output = library_child('$config[OPTIONS_CLI]["base_url"] = "https://cacti.fixture";
$key = openssl_pkey_new(["private_key_bits" => 2048]);
openssl_x509_export(openssl_csr_sign(openssl_csr_new(["commonName" => "fixture"], $key), null, $key, 1), $certificate);
$provider = new \Cacti\Auth\SamlLoginProvider(["id" => 1, "type" => PROVIDER_TYPE_SAML2, "name" => "Fixture SLO", "enabled" => "on",
	"parameters" => json_encode(["idp_entity_id" => "https://idp.fixture", "idp_sso_url" => "https://idp.fixture/sso", "idp_slo_url" => "https://idp.fixture/slo", "idp_x509cert" => $certificate])]);
$_GET["SAMLRequest"] = base64_encode(gzdeflate("<samlp:LogoutRequest xmlns:samlp=\"urn:oasis:names:tc:SAML:2.0:protocol\" ID=\"_fixture\" Version=\"2.0\" IssueInstant=\"" . gmdate("Y-m-d\\TH:i:s\\Z") . "\" Destination=\"https://elsewhere.invalid/slo\"/>"));
$provider->processLogout();
print "logout continued";');
		library_expect($output, '', 'A rejected SAML logout ends the request without continuing');
		library_expect(str_contains((string) file_get_contents($log, false, null, $offset), "SAML SLO error for provider 'Fixture SLO'"), true, 'A rejected SAML logout records its validation errors');

		$error     = false;
		$error_msg = '';
		library_expect(auth_login_create_user_from_template('coverage_template_absent', 0), [], 'A missing template cannot provision an account');
		library_expect($error, true, 'A missing template reports the login failure');
		library_expect(str_contains($error_msg, 'Template user id -1 does not exist'), true, 'A missing template names the configured template');

		$users[] = $template = (int) sql_save(['id' => 0, 'username' => 'coverage_ldap_template', 'realm' => 0, 'enabled' => 'on', 'password' => '', 'full_name' => 'Template Name'], 'user_auth');
		set_config_option('user_template', (string) $template);
		// cacti_ldap_search_cn() drops empty() arguments, so the legal username "0" reaches
		// the directory without a username and the attribute lookup reports a failure.
		$error = false;
		$user  = auth_login_create_user_from_template('0', 3);
		library_expect($error, false, 'A failed directory attribute lookup still provisions from the template');
		library_expect([$user['username'], (int) $user['realm'], $user['full_name']], ['0', 3, 'Template Name'], 'Provisioning without directory attributes keeps the template identity fields');
		// Remove it while the template setting points elsewhere: an account named "0" matches a template id of 0.
		user_remove((int) $user['id']);
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM user_auth WHERE id = ?', [$user['id']]), 0, 'The provisioned directory account is removed');
	} finally {
		// Restore the template setting first: user_remove() refuses the active template account.
		foreach ($previous as $name => $value) {
			set_config_option($name, (string) $value);
		}

		foreach ($users as $id) {
			db_execute_prepared('DELETE FROM user_log WHERE user_id = ?', [$id]);
			db_execute_prepared('DELETE FROM user_auth_cache WHERE user_id = ?', [$id]);
			user_remove($id);
		}
	}
}
