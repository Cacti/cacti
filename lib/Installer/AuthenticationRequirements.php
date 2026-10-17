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

final readonly class AuthenticationRequirements {
	/** @param list<array{id: int|string, type: int|string, enabled: string, parameters: ?string}> $providers
	 * @param  list<int>    $realms
	 * @return list<string>
	 */
	public function errors(array $providers, array $realms) : array {
		$errors = [];
		$ids    = [];

		foreach ($providers as $provider) {
			$id    = (int) $provider['id'];
			$ids[] = $id;

			if ($provider['enabled'] !== 'on') {
				continue;
			}
			$parameters = json_decode($provider['parameters'] ?? '', true);

			if (!is_array($parameters)) {
				$errors[] = 'Provider ' . $id . ' has invalid parameters. Restore its configuration before retrying.';
			} elseif ((int) $provider['type'] === 1 && empty($parameters['server'])) {
				$errors[] = 'LDAP provider ' . $id . ' has no server. Restore its configuration before retrying.';
			}
		}

		foreach (array_unique($realms) as $realm) {
			if ($realm >= 1000 && !in_array($realm - 1000, $ids, true)) {
				$errors[] = 'User realm ' . $realm . ' has no login provider. Restore its provider mapping before retrying.';
			}
		}

		return $errors;
	}
}
