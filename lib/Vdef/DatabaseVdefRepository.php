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

/** Preserve the existing database helpers and live function dictionary. */
final class DatabaseVdefRepository implements VdefRepository {
	public function items(int $vdef_id) : array|false {
		return db_fetch_assoc_prepared('SELECT * FROM vdef_items WHERE vdef_id = ? ORDER BY sequence', [$vdef_id]);
	}

	public function itemName(int $vdef_item_id) : string {
		return \get_vdef_item_name($vdef_item_id);
	}
}
