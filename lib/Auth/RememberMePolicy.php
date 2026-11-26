<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace Cacti\Auth;

final class RememberMePolicy {
	/**
	 * Determine whether a login may issue a persistent remember-me token.
	 *
	 * Guest accounts, Basic auth sessions, and accounts required to change
	 * their password must not receive a persistent login cookie.
	 *
	 * @param array<string, mixed> $user                 Authenticated user row
	 * @param int                  $auth_method          Active Cacti authentication method
	 * @param int                  $guest_account_id     Configured guest account ID (0 when disabled)
	 * @param bool                 $has_error            Whether login validation recorded an error
	 * @param bool                 $remember_requested   Whether the user requested remember-me
	 * @param bool                 $auth_cache_enabled   Whether remember-me cookies are enabled globally
	 * @param bool                 $realm_allows_cookies Whether the login realm permits remember-me cookies
	 *
	 * @return bool
	 */
	public static function canIssueToken(
		array $user,
		int $auth_method,
		int $guest_account_id,
		bool $has_error,
		bool $remember_requested,
		bool $auth_cache_enabled,
		bool $realm_allows_cookies
	) : bool {
		if ($has_error || !$remember_requested || !$auth_cache_enabled || !$realm_allows_cookies) {
			return false;
		}

		if ($auth_method === AUTH_METHOD_BASIC) {
			return false;
		}

		$user_id = (int) ($user['id'] ?? 0);

		if ($user_id <= 0 || ($guest_account_id > 0 && $user_id === $guest_account_id)) {
			return false;
		}

		if (($user['must_change_password'] ?? '') === 'on') {
			return false;
		}

		return true;
	}
}
