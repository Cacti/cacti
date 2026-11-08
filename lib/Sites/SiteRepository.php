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

namespace Cacti\Sites;

/**
 * Storage operations SiteService needs for site management.
 */
interface SiteRepository {
	/**
	 * Read one site row.
	 *
	 * @param mixed $id The site id.
	 *
	 * @return array<string, mixed>|bool The row, an empty array when it is missing, or false on a database error.
	 */
	public function find(mixed $id) : array|bool;

	/**
	 * Insert or update a site.
	 *
	 * @param array<string, mixed> $values Column values; an empty id inserts a new site.
	 *
	 * @return mixed The saved site id, or a falsy value on failure.
	 */
	public function save(array $values) : mixed;

	/**
	 * Enable or disable one site.
	 *
	 * @param mixed $id       The site id.
	 * @param bool  $disabled True to disable the site, false to enable it.
	 *
	 * @return void
	 */
	public function setDisabled(mixed $id, bool $disabled) : void;

	/**
	 * Delete sites and detach the active devices that used them.
	 *
	 * @param array<mixed> $ids The site ids.
	 *
	 * @return void
	 */
	public function delete(array $ids) : void;
}
