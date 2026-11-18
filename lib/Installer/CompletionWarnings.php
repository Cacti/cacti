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

/** A serializable completion report; it holds only collector, provider and realm IDs. */
final readonly class CompletionWarnings {
	private const COLLECTOR_STATES = ['failed', 'skipped', 'timeout', 'deferred'];

	private const AUTHENTICATION_CHECKS = ['ldap_without_server', 'realms_without_provider'];

	/** @param array<string, list<int>> $collectors
	 * @param array<string, list<int>> $authentication
	 */
	public function __construct(public array $collectors = [], public array $authentication = []) {
	}

	/** @param array<string, mixed> $status */
	public function withCollectors(array $status) : self {
		return new self(self::ids($status, self::COLLECTOR_STATES), $this->authentication);
	}

	/** @param array<string, mixed> $checks */
	public function withAuthentication(array $checks) : self {
		return new self($this->collectors, self::ids($checks, self::AUTHENTICATION_CHECKS));
	}

	public function hasWarnings() : bool {
		return $this->collectors !== [] || $this->authentication !== [];
	}

	public function encode() : string {
		return json_encode(['collectors' => $this->collectors, 'authentication' => $this->authentication], JSON_THROW_ON_ERROR);
	}

	public static function decode(string $value) : self {
		$data = json_decode($value, true);

		if (!is_array($data)) {
			return new self();
		}

		return (new self())->withCollectors(self::positive($data['collectors'] ?? null, self::COLLECTOR_STATES))
			->withAuthentication(self::positive($data['authentication'] ?? null, self::AUTHENTICATION_CHECKS));
	}

	/**
	 * @param  array<string, mixed>     $source
	 * @param  list<string>             $keys
	 * @return array<string, list<int>>
	 */
	private static function ids(array $source, array $keys) : array {
		$result = [];

		foreach ($keys as $key) {
			$ids = array_values(array_unique(array_map('intval', $source[$key] ?? [])));

			if ($ids !== []) {
				$result[$key] = $ids;
			}
		}

		return $result;
	}

	/**
	 * Keep only positive integer IDs from decoded, untrusted settings data.
	 *
	 * @param  list<string>             $keys
	 * @return array<string, list<int>>
	 */
	private static function positive(mixed $source, array $keys) : array {
		$result = [];

		foreach ($keys as $key) {
			$ids = is_array($source) ? ($source[$key] ?? null) : null;

			if (is_array($ids)) {
				$result[$key] = array_values(array_filter($ids, static fn (mixed $id) : bool => is_int($id) && $id > 0));
			}
		}

		return $result;
	}
}
