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

namespace Cacti\Cdef;

use Closure;

require_once __DIR__ . '/CdefRepository.php';
require_once __DIR__ . '/CdefResolutionState.php';

final class CdefResolver {
	/** @param Closure(string): void $log */
	public function __construct(private readonly CdefRepository $repository, private readonly Closure $log) {
	}

	public function resolve(int $cdef_id) : ?string {
		return $this->resolveWithState($cdef_id, new CdefResolutionState());
	}

	/** The caller owns the state; resolve() always starts a fresh operation. */
	public function resolveWithState(int $cdef_id, CdefResolutionState $state) : ?string {
		if (isset($state->visited[$cdef_id])) {
			($this->log)(sprintf('ERROR: CDEF %d contains a recursive cycle.', $cdef_id));

			return null;
		}

		if (array_key_exists($cdef_id, $state->cache)) {
			return $state->cache[$cdef_id];
		}

		if (count($state->visited) >= 64) {
			($this->log)(sprintf('ERROR: CDEF %d exceeds the resolver nesting depth.', $cdef_id));

			return null;
		}

		if (++$state->expansion > 4096) {
			($this->log)(sprintf('ERROR: CDEF %d exceeds the resolver expansion budget.', $cdef_id));

			return null;
		}

		$state->visited[$cdef_id] = true;
		$cdef_items               = $this->repository->items($cdef_id);

		if ($cdef_items === false) {
			unset($state->visited[$cdef_id]);
			($this->log)(sprintf('ERROR: Unable to load CDEF %d.', $cdef_id));

			return null;
		}

		if (count($cdef_items) === 0) {
			$cdef_exists = $this->repository->exists($cdef_id);

			unset($state->visited[$cdef_id]);

			if ($cdef_exists === null) {
				($this->log)(sprintf('ERROR: Unable to verify CDEF %d.', $cdef_id));

				return null;
			}

			if ($cdef_exists === false) {
				($this->log)(sprintf('ERROR: CDEF %d does not exist.', $cdef_id));

				return null;
			}

			$state->cache[$cdef_id] = '';

			return $state->cache[$cdef_id];
		}

		$parts  = [];
		$length = 0;

		foreach ($cdef_items as $cdef_item) {
			if ($cdef_item['type'] == 5) {
				$current_cdef_id = $cdef_item['value'];
				$nested          = $this->resolveWithState((int) $current_cdef_id, $state);

				if ($nested === null) {
					unset($state->visited[$cdef_id]);

					return null;
				}

				if ($nested === '') {
					($this->log)(sprintf('ERROR: CDEF %d references an empty definition.', $cdef_id));
					unset($state->visited[$cdef_id]);

					return null;
				}

				$length += strlen($nested) + ($parts === [] ? 0 : 1);

				if ($length > 1048576) {
					($this->log)(sprintf('ERROR: CDEF %d exceeds the resolver output budget.', $cdef_id));
					unset($state->visited[$cdef_id]);

					return null;
				}

				$parts[] = $nested;
			} else {
				$item_name = $this->repository->itemName($cdef_item['id']);

				if ($item_name === null) {
					unset($state->visited[$cdef_id]);

					return null;
				}

				if ($item_name === '') {
					($this->log)(sprintf('ERROR: CDEF %d contains an empty item.', $cdef_id));
					unset($state->visited[$cdef_id]);

					return null;
				}

				$length += strlen($item_name) + ($parts === [] ? 0 : 1);

				if ($length > 1048576) {
					($this->log)(sprintf('ERROR: CDEF %d exceeds the resolver output budget.', $cdef_id));
					unset($state->visited[$cdef_id]);

					return null;
				}

				$parts[] = $item_name;
			}
		}

		unset($state->visited[$cdef_id]);

		$resolved = implode(',', $parts);

		if ($state->cache_bytes + strlen($resolved) <= 8388608) {
			$state->cache_bytes    += strlen($resolved);
			$state->cache[$cdef_id] = $resolved;
		}

		return $resolved;
	}
}
