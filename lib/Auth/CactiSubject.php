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

declare(strict_types = 1);

namespace Cacti\Auth;

/**
 * CactiSubject - the thing an authorization decision is made about.
 *
 * This is the value object a Cacti authorization Voter votes on.  Today it
 * carries only what a realm/page-access check needs: the realm id being
 * requested, and the user the decision is for ($check_user, matching the
 * second argument of the legacy is_realm_allowed()).  A $check_user of false
 * means "the current session user", exactly as the legacy function treats it.
 *
 * Deliberately free of any Symfony dependency so it can be referenced from
 * legacy procedural code and autoloaded even when symfony/security-core is
 * not installed.  As more check types migrate behind the Voter pattern this
 * object grows additional, still-optional fields (object id, graph id, tree
 * branch, ...) without the call sites needing to know about voters at all.
 */
final class CactiSubject {
	/**
	 * Attribute naming the "may this user use this realm?" decision.
	 *
	 * Kept here, on the dependency-free subject, rather than on the Voter so a
	 * call site can name the decision without forcing the Voter class (and its
	 * Symfony parent interface) to autoload.  See cacti_access_granted().
	 */
	public const ATTR_REALM = 'CACTI_REALM';

	/**
	 * @param int      $realm     The realm id being requested (e.g. 29 = Package Management).
	 * @param int|bool $checkUser The user id to check, or false for the current session user.
	 */
	public function __construct(
		private readonly int $realm,
		private readonly int|bool $checkUser = false,
	) {
	}

	public function getRealm(): int {
		return $this->realm;
	}

	public function getCheckUser(): int|bool {
		return $this->checkUser;
	}
}
