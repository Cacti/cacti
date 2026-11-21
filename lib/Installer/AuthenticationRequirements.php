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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

namespace Cacti\Installer;

/**
 * Post-upgrade checks of the login provider configuration.
 *
 * Unreadable provider parameters block the upgrade: every provider would run
 * without its stored settings, including any group restriction. A provider
 * without an LDAP server, or a user whose realm has no provider, already
 * denies those logins, so they are reported as warnings to repair.
 */
final readonly class AuthenticationRequirements {
	/** @param list<array{id: int|string, type: int|string, enabled: string, parameters: ?string}> $providers
	 * @return list<string>
	 */
	public function errors(array $providers) : array {
		$errors = [];

		foreach ($providers as $provider) {
			if ($provider['enabled'] === 'on' && !is_array(json_decode($provider['parameters'] ?? '', true))) {
				$errors[] = 'Provider ' . (int) $provider['id'] . ' has invalid parameters. Restore its configuration before retrying.';
			}
		}

		return $errors;
	}

	/** @param list<array{id: int|string, type: int|string, enabled: string, parameters: ?string}> $providers
	 * @param  list<int>                                                                   $realms
	 * @return array{ldap_without_server?: list<int>, realms_without_provider?: list<int>}
	 */
	public function warnings(array $providers, array $realms) : array {
		$ids     = array_map(static fn (array $provider) : int => (int) $provider['id'], $providers);
		$servers = [];

		foreach ($providers as $provider) {
			$parameters = json_decode($provider['parameters'] ?? '', true);

			if ($provider['enabled'] === 'on' && (int) $provider['type'] === 1 && is_array($parameters) && empty($parameters['server'])) {
				$servers[] = (int) $provider['id'];
			}
		}
		$orphans = array_values(array_filter(array_unique($realms), static fn (int $realm) : bool => $realm >= 1000 && !in_array($realm - 1000, $ids, true)));

		return array_filter(['ldap_without_server' => $servers, 'realms_without_provider' => $orphans]);
	}
}
