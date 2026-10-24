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

/** A serializable completion report; it never contains credentials or commands. */
final readonly class CompletionWarnings {
	/** @param array<string, list<int>> $collectors */
	public function __construct(public array $collectors = []) {
	}

	/** @param array<string, mixed> $status */
	public function withCollectors(array $status) : self {
		$collectors = [];

		foreach (['failed', 'skipped', 'timeout', 'deferred'] as $state) {
			$ids = array_values(array_unique(array_map('intval', $status[$state] ?? [])));

			if ($ids !== []) {
				$collectors[$state] = $ids;
			}
		}

		return new self($collectors);
	}

	public function hasWarnings() : bool {
		return $this->collectors !== [];
	}

	public function encode() : string {
		return json_encode(['collectors' => $this->collectors], JSON_THROW_ON_ERROR);
	}

	public static function decode(string $value) : self {
		$data = json_decode($value, true);

		if (!is_array($data)) {
			return new self();
		}

		$status = [];

		foreach (['failed', 'skipped', 'timeout', 'deferred'] as $state) {
			$ids = $data['collectors'][$state] ?? null;

			if (is_array($ids)) {
				$status[$state] = array_values(array_filter($ids, static fn (mixed $id) : bool => is_int($id) && $id > 0));
			}
		}

		return (new self())->withCollectors($status);
	}
}
