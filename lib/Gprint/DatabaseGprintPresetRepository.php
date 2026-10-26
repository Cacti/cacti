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

final class DatabaseGprintPresetRepository implements GprintPresetRepository {
	public function find(mixed $id): array|bool {
		return db_fetch_row_prepared('SELECT * FROM graph_templates_gprint WHERE id = ?', [$id]);
	}

	public function save(array $values): mixed {
		return sql_save($values, 'graph_templates_gprint');
	}

	public function delete(array $ids): void {
		db_execute('DELETE FROM graph_templates_gprint WHERE ' . array_to_sql_or($ids, 'id'));
	}
}
