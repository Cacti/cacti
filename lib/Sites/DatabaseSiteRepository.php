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

require_once __DIR__ . '/SiteRepository.php';

final class DatabaseSiteRepository implements SiteRepository {
	public function find(mixed $id) : array|bool {
		return db_fetch_row_prepared('SELECT * FROM sites WHERE id = ?', [$id]);
	}

	public function save(array $values) : mixed {
		return sql_save($values, 'sites');
	}

	public function setDisabled(mixed $id, bool $disabled) : void {
		if ($disabled) {
			db_execute_prepared('UPDATE sites SET disabled = "on" WHERE id = ?', [$id]);
		} else {
			db_execute_prepared('UPDATE sites SET disabled = "" WHERE id = ?', [$id]);
		}
	}

	public function delete(array $ids) : void {
		db_execute('DELETE FROM sites WHERE ' . array_to_sql_or($ids, 'id'));
		db_execute('UPDATE host SET site_id=0 WHERE deleted="" AND ' . array_to_sql_or($ids, 'site_id'));
	}
}
