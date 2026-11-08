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

/**
 * Read access to stored CDEF definitions for CdefResolver.
 */
interface CdefRepository {
	/**
	 * Return a CDEF's items in sequence order.
	 *
	 * @param int $cdef_id The CDEF whose items are read.
	 *
	 * @return array<array{id: int, type: string, value: mixed}>|false The items, or false on a database error.
	 */
	public function items(int $cdef_id) : array|false;

	/**
	 * Report whether a CDEF row exists, so an empty CDEF can be told apart from a missing one.
	 *
	 * @param int $cdef_id The CDEF to look up.
	 *
	 * @return bool|null Whether the CDEF exists, or null on a database error.
	 */
	public function exists(int $cdef_id) : ?bool;

	/**
	 * Return the text form of one non-nested CDEF item.
	 *
	 * @param int $cdef_item_id The cdef_items row id.
	 *
	 * @return string|null The item text, or null when the item is missing or invalid.
	 */
	public function itemName(int $cdef_item_id) : ?string;
}
