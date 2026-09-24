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
 * Immutable outcome of a login provider authentication/callback attempt.
 *
 * Replaces the ad-hoc `['error_num' => ..., 'error_text' => ...]` arrays and
 * the $error/$error_msg globals pattern used by the legacy domains_* functions,
 * with a single typed value every provider class returns.
 */
final class LoginResult {
	/**
	 * @param bool       $success    Whether authentication (and group gating) succeeded.
	 * @param array|null $user       The matching user_auth row, or null if not yet provisioned.
	 * @param string     $error      Human readable failure reason, empty on success.
	 * @param string     $username   The identity asserted by the provider (login/subject/NameID).
	 * @param array      $claims     Normalized claims: full_name, email, groups (array of strings).
	 * @param bool       $rememberMe Whether the user opted into a "remember me" auth cookie
	 *                               before being redirected to the IdP.
	 */
	public function __construct(
		public readonly bool $success,
		public readonly ?array $user = null,
		public readonly string $error = '',
		public readonly string $username = '',
		public readonly array $claims = [],
		public readonly bool $rememberMe = false,
	) {
	}

	public static function failure(string $error): self {
		return new self(false, null, $error);
	}

	public static function authenticated(string $username, array $claims = [], ?array $user = null, bool $rememberMe = false): self {
		return new self(true, $user, '', $username, $claims, $rememberMe);
	}
}
