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

namespace Cacti\Vdef;

require_once __DIR__ . '/VdefRepository.php';

/**
 * VDEF repository backed by the vdef_items table.
 *
 * Uses the existing database helpers and get_vdef_item_name(), so lib/vdef.php
 * must be loaded first and the live $vdef_functions dictionary is honoured.
 */
final class DatabaseVdefRepository implements VdefRepository {
	/**
	 * Read a VDEF's items in sequence order.
	 *
	 * @param int $vdef_id The VDEF whose items are read.
	 *
	 * @return array<array<string, mixed>>|false The rows, or false when the query fails.
	 */
	public function items(int $vdef_id) : array|false {
		return db_fetch_assoc_prepared('SELECT * FROM vdef_items WHERE vdef_id = ? ORDER BY sequence', [$vdef_id]);
	}

	/**
	 * Resolve one item through the legacy get_vdef_item_name() helper.
	 *
	 * @param int $vdef_item_id The vdef_items row id.
	 *
	 * @return string The item's text form.
	 */
	public function itemName(int $vdef_item_id) : string {
		return \get_vdef_item_name($vdef_item_id);
	}
}
