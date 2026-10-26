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

require_once __DIR__ . '/CdefRepository.php';

/** Keeps the existing database helpers and item dictionaries at the legacy boundary. */
final class DatabaseCdefRepository implements CdefRepository {
	public function items(int $cdef_id) : array|false {
		return db_fetch_assoc_prepared('SELECT id, type, value FROM cdef_items WHERE cdef_id = ? ORDER BY sequence', [$cdef_id]);
	}

	public function exists(int $cdef_id) : ?bool {
		$rows = db_fetch_assoc_prepared('SELECT id FROM cdef WHERE id = ?', [$cdef_id]);

		return $rows === false ? null : cacti_sizeof($rows) > 0;
	}

	public function itemName(int $cdef_item_id) : ?string {
		global $cdef_functions, $cdef_operators;

		$cdef_item          = db_fetch_row_prepared('SELECT type, value FROM cdef_items WHERE id = ?', [$cdef_item_id]);

		if (!is_array($cdef_item) || !array_key_exists('type', $cdef_item) || !array_key_exists('value', $cdef_item)) {
			cacti_log(sprintf('ERROR: CDEF item %d is missing or corrupt.', $cdef_item_id), false, 'CDEF');

			return null;
		}

		$current_cdef_value = $cdef_item['value'];

		switch ($cdef_item['type']) {
			case '1':
				if (!isset($cdef_functions[$current_cdef_value])) {
					cacti_log(sprintf('ERROR: CDEF item %d references an unknown function.', $cdef_item_id), false, 'CDEF');

					return null;
				}

				return (string) $cdef_functions[$current_cdef_value];
			case '2':
				if (!isset($cdef_operators[$current_cdef_value])) {
					cacti_log(sprintf('ERROR: CDEF item %d references an unknown operator.', $cdef_item_id), false, 'CDEF');

					return null;
				}

				return (string) $cdef_operators[$current_cdef_value];
			case '4':
				return (string) $current_cdef_value;
			case '5':
				$cdef_name = db_fetch_cell_prepared('SELECT name FROM cdef WHERE id = ?', [$current_cdef_value]);

				if ($cdef_name === false || $cdef_name === null || $cdef_name === '') {
					cacti_log(sprintf('ERROR: CDEF item %d references a missing definition.', $cdef_item_id), false, 'CDEF');

					return null;
				}

				return (string) $cdef_name;
			case '6':
				return (string) $current_cdef_value;
		}

		cacti_log(sprintf('ERROR: CDEF item %d has an unknown type.', $cdef_item_id), false, 'CDEF');

		return null;
	}
}
