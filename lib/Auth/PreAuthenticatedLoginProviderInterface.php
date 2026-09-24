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
 * A provider whose identity is asserted by something outside Cacti (e.g. the
 * web server terminating HTTP Basic Auth) rather than a credential Cacti
 * itself verifies. There is no password to check, so resolve() only takes
 * the already-asserted username.
 */
interface PreAuthenticatedLoginProviderInterface extends LoginProviderInterface {
	/**
	 * Look up (and apply lockout/account checks to) the externally-asserted username.
	 */
	public function resolve(string $username): LoginResult;
}
