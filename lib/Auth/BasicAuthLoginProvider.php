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
 * Web Basic Authentication Login Provider.
 *
 * The web server (Apache/nginx) has already verified the credential before
 * Cacti ever sees the request; Cacti only trusts the asserted username
 * (PHP_AUTH_USER/REMOTE_USER, see get_basic_auth_username()) and looks it up
 * against realm 2. There is no password for Cacti to check, so this
 * implements PreAuthenticatedLoginProviderInterface rather than
 * CredentialLoginProviderInterface.
 *
 * A thin adapter around basic_auth_login_process() in lib/auth.php, which
 * stays untouched.
 *
 * Like Local auth, this is not a login_providers-table row - it is a single
 * global mode, mutually exclusive with every other auth_method.
 */
final class BasicAuthLoginProvider implements PreAuthenticatedLoginProviderInterface {
	public function getType() : int {
		return AUTH_METHOD_BASIC;
	}

	public function getId() : int {
		return 2;
	}

	public function getName() : string {
		return __('Web Basic Authentication');
	}

	public function isEnabled() : bool {
		return (int) read_config_option('auth_method') === AUTH_METHOD_BASIC;
	}

	public function allowsAuthCookies() : bool {
		// Never for Basic Auth: there is no Cacti-owned login form to offer
		// "remember me" on, and the browser/server already re-sends the
		// credential on every request.
		return false;
	}

	public function resolve(string $username) : LoginResult {
		$user = basic_auth_login_process($username);

		return cacti_sizeof($user) ? LoginResult::authenticated($username, [], $user) : LoginResult::failure('');
	}
}
