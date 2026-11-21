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

/** Every enabled collector receives an explicit outcome, even when sync is deferred. */
final readonly class CollectorSynchronizer {
	/** @param list<int> $unavailableStatuses */
	public function __construct(private array $unavailableStatuses) {
	}

	/** @param list<array{id: int|string, status: int|string, gap: int|string|null}> $pollers
	 * @param  callable(int): bool                                                                                                   $replicate
	 * @param  callable(int): void                                                                                                   $progress
	 * @return array{success: list<int>, failed: list<int>, skipped: list<int>, timeout: list<int>, deferred: list<int>, total: int}
	 */
	public function synchronize(array $pollers, int $gapLimit, bool $enabled, callable $replicate, callable $progress) : array {
		$result = ['success' => [], 'failed' => [], 'skipped' => [], 'timeout' => [], 'deferred' => [], 'total' => count($pollers)];

		foreach ($pollers as $poller) {
			$id = (int) $poller['id'];
			$progress($id);
			$state = match (true) {
				!$enabled                                                           => 'deferred',
				in_array((int) $poller['status'], $this->unavailableStatuses, true) => 'skipped',
				$poller['gap'] === null || (int) $poller['gap'] >= $gapLimit        => 'timeout',
				default                                                             => $replicate($id) ? 'success' : 'failed',
			};
			$result[$state][] = $id;
		}

		return $result;
	}
}
