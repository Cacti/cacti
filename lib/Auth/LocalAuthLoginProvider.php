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

declare(strict_types = 1);

namespace Cacti\Auth;

/**
 * Local (Cacti built-in) Login Provider.
 *
 * Unlike the login_providers-table providers, Local auth is not a row an
 * admin can add, disable, or delete - it is always available as the
 * fallback realm, so isEnabled() is always true and getId()/getType() are
 * fixed constants rather than coming from a database row.
 *
 * Carries the full local password verification and enforcement previously
 * split across secpass_login_process()/local_auth_login_process() in
 * lib/auth.php: constant-time verification against username enumeration,
 * atomic lockout counting (via auth_process_lockout()), forced-password-change
 * enforcement, and rehash-on-login. secpass_check_pass() (the password
 * complexity ruleset) stays a shared lib/auth.php function since
 * auth_changepassword.php, auth_resetpassword.php, and user_admin.php all
 * call it outside of any login flow.
 */
final class LocalAuthLoginProvider implements CredentialLoginProviderInterface {
	public function getType() : int {
		return AUTH_METHOD_CACTI;
	}

	public function getId() : int {
		return 0;
	}

	public function getName() : string {
		return __('Local');
	}

	public function isEnabled() : bool {
		return true;
	}

	public function allowsAuthCookies() : bool {
		return true;
	}

	public function authenticate(string $username, string $password) : LoginResult {
		global $error, $error_msg;

		// Lets a plugin fully take over local login (e.g. an external MFA
		// step); when it does, Cacti falls through to template provisioning
		// exactly as if no local account had been found.
		if (api_plugin_hook_function('login_process', false)) {
			return LoginResult::failure('');
		}

		$user = $this->verifyCredential($username, $password);

		if (!cacti_sizeof($user)) {
			return LoginResult::failure($error_msg);
		}

		$this->rehashIfNeeded($username, $password, (string) ($user['password'] ?? ''));

		// Re-fetch the full row: verifyCredential() only selects the columns
		// its own checks need.
		$user = db_fetch_row_prepared('SELECT *
			FROM user_auth
			WHERE username = ?
			AND realm = 0',
			[$username]);

		return cacti_sizeof($user) ? LoginResult::authenticated($username, [], $user) : LoginResult::failure('');
	}

	/**
	 * Verifies the credential and enforces the account-disabled, lockout, and
	 * forced-password-change rules. Ported from the retired
	 * secpass_login_process().
	 *
	 * @return array The partial user_auth row on success, or [] on any failure.
	 */
	private function verifyCredential(string $username, string $password) : array {
		global $error, $error_msg;

		if ($username == '') {
			$error     = true;
			$error_msg = __('Access Denied!  Login Failed.');

			cacti_log(sprintf('LOGIN FAILED: Empty Local Username provided, from IP Address %s', get_client_addr()), false, 'AUTH');

			return [];
		}

		auth_checkclear_lockout($username, 0);

		if (auth_process_lockout_check($username, 0)) {
			return [];
		}

		if (db_column_exists('user_auth', 'lastfail')) {
			$user = db_fetch_row_prepared('SELECT id, username, lastfail, failed_attempts, `locked`, enabled, password
				FROM user_auth
				WHERE username = ?
				AND realm = 0',
				[$username]);
		} else {
			$user = db_fetch_row_prepared('SELECT id, username, password, enabled
				FROM user_auth
				WHERE username = ?
				AND realm = 0',
				[$username]);
		}

		if (cacti_sizeof($user)) {
			if ($user['enabled'] != 'on') {
				$error     = true;
				$error_msg = __('Access Denied!  Login failed, account disabled.');

				cacti_log(sprintf('LOGIN FAILED: Local Login Failed for user %s from IP Address %s, account disabled.', $username, get_client_addr()), false, 'AUTH');

				return [];
			}

			if (trim($password) == '') {
				// error
				$error     = true;
				$error_msg = __('Access Denied!  No password provided by user.');

				cacti_log(sprintf('LOGIN FAILED: No password provided for user %s from IP Address %s', $username, get_client_addr()), false, 'AUTH');

				$valid_pass = false;
			} else {
				$valid_pass = compat_password_verify($password, $user['password']);
			}

			cacti_log('DEBUG: User \'' . $username . '\' valid password = ' . $valid_pass, false, 'AUTH', POLLER_VERBOSITY_DEBUG);

			if (!$valid_pass) {
				auth_process_lockout($username, 0);

				if (!$error) {
					$error     = true;
					$error_msg = __('Access Denied! Login Failed.') . ' <a href="auth_resetpassword.php">' . __('Reset password') . '</a>';

					cacti_log(sprintf('LOGIN FAILED: Local Login Failed for user %s from IP Address %s', $username, get_client_addr()), false, 'AUTH');
				}

				return [];
			}
		} else {
			// Run a throw-away verification against a fixed bcrypt hash so an unknown
			// username costs the same as a known one. Without this, the valid-user path
			// runs bcrypt (tens of ms) while the unknown-user path returns immediately,
			// and the response-time delta lets an attacker enumerate valid usernames.
			// The verify result is discarded; this fixed hash is tied to no account.
			compat_password_verify((string) $password, '$2y$10$VWBpVwPd5enH/FIf0bNNxO0d12/V8EZag/sNP.SQqsyYWyOFXvaV.');

			// error
			$error     = true;
			$error_msg = __('Access Denied!  Login Failed.');

			cacti_log(sprintf('LOGIN FAILED: Invalid user %s specified from IP Address %s', $username, get_client_addr()), false, 'AUTH');

			return [];
		}

		$this->enforcePasswordPolicy($username, $password);

		return $user;
	}

	/**
	 * Forces a password change when the stored password no longer meets the
	 * configured complexity rules, and records the last-login timestamp.
	 * Ported from secpass_login_process()'s tail. Exits directly (as the
	 * original did) when a forced change redirect is required.
	 */
	private function enforcePasswordPolicy(string $username, string $password) : void {
		if (read_config_option('secpass_forceold') == 'on') {
			$message = secpass_check_pass($password);

			if ($message != 'ok') {
				db_execute_prepared("UPDATE user_auth
					SET must_change_password = 'on'
					WHERE username = ?
					AND realm = 0
					AND enabled = 'on'",
					[$username]);

				$forced_msg = __('Your Cacti administrator has forced complex passwords for logins and your current Cacti password does not match the new requirements.  Therefore, you must change your password now.');

				raise_message('forced_password', $forced_msg, MESSAGE_LEVEL_INFO);
				header('Location: auth_changepassword.php');

				exit;
			}
		}

		// Set the last Login time
		if (read_config_option('secpass_expireaccount') > 0) {
			db_execute_prepared("UPDATE user_auth
				SET lastlogin = ?
				WHERE username = ?
				AND realm = 0
				AND enabled = 'on'",
				[time(), $username]);
		}
	}

	/**
	 * Rehashes the stored password if the algorithm/cost has moved on since
	 * it was set. Ported from local_auth_login_process(); no longer
	 * re-verifies the password independently before rehashing it (the
	 * caller only reaches here after verifyCredential() already succeeded).
	 */
	private function rehashIfNeeded(string $username, string $password, string $storedPass) : void {
		if ($storedPass != '' && compat_password_needs_rehash($storedPass, PASSWORD_DEFAULT)) {
			$rehashed = compat_password_hash($password, PASSWORD_DEFAULT);

			db_check_password_length();

			db_execute_prepared('UPDATE user_auth
				SET password = ?
				WHERE username = ?
				AND realm = 0',
				[$rehashed, $username]);
		}
	}
}
