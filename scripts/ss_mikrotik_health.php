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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

// display no errors
error_reporting(0);

if (!isset($called_by_script_server)) {
	include(__DIR__ . '/../include/cli_check.php');
	array_shift($_SERVER['argv']);
	print call_user_func_array('ss_mikrotik_health', $_SERVER['argv']);
}

function ss_mikrotik_health(int $host_id = 0, string $column = 'no') : string {
	$allowed_columns = ['voltage', 'temperature', 'processorTemperature', 'current', 'power', 'fanSpeed', 'cpuFrequency'];

	if (!in_array($column, $allowed_columns, true)) {
		return '0';
	}

	$value = db_fetch_cell_prepared("SELECT $column
		FROM plugin_mikrotik_system_health
		WHERE host_id = ?",
		[$host_id]);

	if ($value == '') {
		$value = 'U';
	}

	return $value;
}
