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
 * A provider that can authenticate a username+password pair synchronously,
 * without leaving Cacti's own login form. Implemented by LdapLoginProvider
 * and ActiveDirectoryLoginProvider.
 */
interface CredentialLoginProviderInterface extends LoginProviderInterface {
	/**
	 * Authenticate the given credentials and, when successful, resolve group
	 * membership and claims (full name / email) for account provisioning.
	 */
	public function authenticate(string $username, string $password): LoginResult;
}
