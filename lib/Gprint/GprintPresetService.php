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

namespace Cacti\Gprint;

use Closure;

require_once __DIR__ . '/GprintPresetRepository.php';

/**
 * GPRINT preset actions behind gprint_presets.php: lookup, save and delete.
 */
final class GprintPresetService {
	/**
	 * @param GprintPresetRepository $repository Preset storage.
	 * @param Closure(int): void     $message    Raises a Cacti message by id.
	 */
	public function __construct(private readonly GprintPresetRepository $repository, private readonly Closure $message) {
	}

	/**
	 * Read one GPRINT preset for the edit page.
	 *
	 * @param mixed $id The preset id.
	 *
	 * @return array|bool The row, an empty array when it is missing, or false on a database error.
	 */
	public function find(mixed $id) : array|bool {
		return $this->repository->find($id);
	}

	/**
	 * Save a GPRINT preset and raise message 1 on success or 2 on failure.
	 *
	 * @param array<string, mixed> $values Column values; an empty id creates a preset.
	 *
	 * @return mixed The saved preset id, or a falsy value on failure.
	 */
	public function save(array $values) : mixed {
		$id = $this->repository->save($values);
		($this->message)($id ? 1 : 2);

		return $id;
	}

	/**
	 * Delete the selected presets unless any of them is still in use.
	 *
	 * The list page disables the checkbox of a preset in use, but a stale
	 * confirmation page or a direct POST can still name one. Deleting it would
	 * leave graph items pointing at a missing preset, so nothing is deleted.
	 *
	 * @param array<mixed> $ids The preset ids; an empty list changes nothing.
	 *
	 * @return bool False when a selected preset is in use and nothing was deleted.
	 */
	public function delete(array $ids) : bool {
		foreach ($ids as $id) {
			if ($this->repository->isInUse($id)) {
				return false;
			}
		}

		if (count($ids)) {
			$this->repository->delete($ids);
		}

		return true;
	}
}
