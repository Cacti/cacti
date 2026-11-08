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

/**
 * Read access to stored VDEF definitions for VdefResolver.
 */
interface VdefRepository {
	/**
	 * Return existing database rows without normalizing their stored values.
	 *
	 * @param int $vdef_id The VDEF whose items are read.
	 *
	 * @return array<array<string, mixed>>|false The items in sequence order, or false when the read fails.
	 */
	public function items(int $vdef_id) : array|false;

	/**
	 * Return the text form of one non-nested VDEF item.
	 *
	 * @param int $vdef_item_id The vdef_items row id.
	 *
	 * @return string The item's function name, special data source or literal value.
	 */
	public function itemName(int $vdef_item_id) : string;
}
