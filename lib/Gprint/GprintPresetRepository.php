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

/**
 * Storage operations GprintPresetService needs for GPRINT preset management.
 */
interface GprintPresetRepository {
	/**
	 * Read one GPRINT preset row.
	 *
	 * @param mixed $id The preset id.
	 *
	 * @return array<string, mixed>|bool The row, an empty array when it is missing, or false on a database error.
	 */
	public function find(mixed $id) : array|bool;

	/**
	 * Insert or update a GPRINT preset.
	 *
	 * @param array<string, mixed> $values Column values; an empty id inserts a new preset.
	 *
	 * @return mixed The saved preset id, or a falsy value on failure.
	 */
	public function save(array $values) : mixed;

	/**
	 * Report whether any graph or graph template item still uses a preset.
	 *
	 * @param mixed $id The preset id.
	 *
	 * @return bool True when the preset is referenced or the check fails.
	 */
	public function isInUse(mixed $id) : bool;

	/**
	 * Delete GPRINT presets.
	 *
	 * @param array<mixed> $ids The preset ids.
	 *
	 * @return void
	 */
	public function delete(array $ids) : void;
}
