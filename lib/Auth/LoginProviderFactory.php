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
 * Builds the correct LoginProviderInterface implementation for a
 * `login_providers` table row, keyed on its `type` column.
 */
final class LoginProviderFactory {
	public static function create(array $row): LoginProviderInterface {
		return match ((int) ($row['type'] ?? 0)) {
			PROVIDER_TYPE_LDAP   => new LdapLoginProvider($row),
			PROVIDER_TYPE_AD     => new ActiveDirectoryLoginProvider($row),
			PROVIDER_TYPE_SAML2  => new SamlLoginProvider($row),
			PROVIDER_TYPE_OPENID => new OpenIdLoginProvider($row),
			default              => throw new \InvalidArgumentException('Unknown login provider type: ' . ($row['type'] ?? 'null')),
		};
	}

	/**
	 * Reads and validates the type-specific settings for a submitted
	 * login_providers.php form, dispatching to the right provider class's
	 * own collectParameters() so each type owns its own field set.
	 */
	public static function collectParameters(int $type): array {
		return match ($type) {
			PROVIDER_TYPE_LDAP   => LdapLoginProvider::collectParameters(),
			PROVIDER_TYPE_AD     => ActiveDirectoryLoginProvider::collectParameters(),
			PROVIDER_TYPE_SAML2  => SamlLoginProvider::collectParameters(),
			PROVIDER_TYPE_OPENID => OpenIdLoginProvider::collectParameters(),
			default              => [],
		};
	}

	/**
	 * Loads and instantiates the provider behind a login realm id (realm - 1000).
	 *
	 * @return LoginProviderInterface|null Null when the realm has no matching, enabled provider.
	 */
	public static function fromRealm(int $realm): ?LoginProviderInterface {
		$row = db_fetch_row_prepared('SELECT *
			FROM login_providers
			WHERE id = ?
			AND enabled = "on"',
			[$realm - 1000]);

		$row = is_array($row) ? $row : [];

		if (!cacti_sizeof($row)) {
			return null;
		}

		return self::create($row);
	}
}
