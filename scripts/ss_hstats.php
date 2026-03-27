#!/usr/bin/env php
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

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array(ss_hstats(...), $_SERVER['argv']);
}

/**
 * Map stat type to database column name.
 *
 * @param string $stat Stat type to map
 *
 * @return string|null Column name or null if invalid stat
 */
function ss_hstats_map_stat_to_column(string $stat) : ?string {
	return match ($stat) {
		'polling_time', 'min_time', 'max_time', 'cur_time', 'avg_time', 'failed_polls', 'availability', 'current_errors' => $stat,
		'uptime' => 'snmp_sysUpTimeInstance',
		default  => null,
	};
}

function ss_hstats(int $host_id = 0, string $stat = '') : string {
	$column = ss_hstats_map_stat_to_column($stat);

	if ($column === null) {
		return 'U';
	}

	$allowed = ['polling_time', 'min_time', 'max_time', 'cur_time', 'avg_time', 'snmp_sysUpTimeInstance', 'failed_polls', 'availability', 'current_errors'];

	if (!in_array($column, $allowed, true)) {
		return 'U';
	}

	if ($host_id > 0) {
		$value = db_fetch_cell_prepared(
			"SELECT $column
			FROM host
			WHERE id = ?",
			[$host_id]
		);

		return ($value == '' ? 'U' : $value);
	}

	return 'U';
}
