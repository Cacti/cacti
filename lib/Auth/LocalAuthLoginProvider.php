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
 * A thin adapter around local_auth_login_process()/secpass_login_process()
 * in lib/auth.php, which stay untouched: they carry security-sensitive,
 * regression-tested behavior (constant-time verification against username
 * enumeration, atomic lockout counting, realm-0-scoped password rehash) that
 * has no reason to move just to gain a class wrapper.
 *
 * Unlike the login_providers-table providers, Local auth is not a row an
 * admin can add/remove - it is always available as the fallback realm, so
 * isEnabled() is always true and getId()/getType() are fixed constants.
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
		$user = local_auth_login_process($username);

		return cacti_sizeof($user) ? LoginResult::authenticated($username, [], $user) : LoginResult::failure('');
	}
}
