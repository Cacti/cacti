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

require_once __DIR__ . '/GprintPresetRepository.php';

/**
 * GPRINT preset repository backed by the graph_templates_gprint table.
 */
final class DatabaseGprintPresetRepository implements GprintPresetRepository {
	/**
	 * Read one GPRINT preset row.
	 *
	 * @param mixed $id The preset id.
	 *
	 * @return array<string, mixed>|bool The row, an empty array when it is missing, or false on a database error.
	 */
	public function find(mixed $id) : array|bool {
		return db_fetch_row_prepared('SELECT * FROM graph_templates_gprint WHERE id = ?', [$id]);
	}

	/**
	 * Insert or update a GPRINT preset through sql_save().
	 *
	 * @param array<string, mixed> $values Column values; an empty id inserts a new preset.
	 *
	 * @return mixed The saved preset id, or a falsy value on failure.
	 */
	public function save(array $values) : mixed {
		return sql_save($values, 'graph_templates_gprint');
	}

	/**
	 * Report whether any graph or graph template item still uses a preset.
	 *
	 * Reads graph_templates_item directly rather than the cached graphs and
	 * templates counters, which can be stale. A failed query counts as in use.
	 *
	 * @param mixed $id The preset id.
	 *
	 * @return bool True when the preset is referenced or the check fails.
	 */
	public function isInUse(mixed $id) : bool {
		$references = db_fetch_assoc_prepared('SELECT id FROM graph_templates_item WHERE gprint_id = ? LIMIT 1', [$id]);

		return $references === false || count($references) > 0;
	}

	/**
	 * Delete GPRINT presets.
	 *
	 * @param array<mixed> $ids The preset ids; an empty list deletes nothing.
	 *
	 * @return void
	 */
	public function delete(array $ids) : void {
		// Bind the ids so callers of this public method cannot inject SQL.
		$ids = array_values($ids);

		if (!count($ids)) {
			return;
		}

		db_execute_prepared('DELETE FROM graph_templates_gprint WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
	}
}
