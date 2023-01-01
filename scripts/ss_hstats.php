#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

	print call_user_func_array('ss_hstats', $_SERVER['argv']);
}

function ss_hstats($host_id = 0, $stat = '') {
	switch ($stat) {
		case 'polling_time':
			$column = $stat;

			break;
		case 'min_time':
			$column = $stat;

			break;
		case 'max_time':
			$column = $stat;

			break;
		case 'cur_time':
			$column = $stat;

			break;
		case 'avg_time':
			$column = $stat;

			break;
		case 'uptime':
			$column = 'snmp_sysUpTimeInstance';

			break;
		case 'failed_polls':
			$column = $stat;

			break;
		case 'availability':
			$column = $stat;

			break;

		default:
			return '0';
	}

	if ($host_id > 0) {
		$value = db_fetch_cell_prepared("SELECT $column
			FROM host
			WHERE id = ?",
			array($host_id));

		return ($value == '' ? 'U' : $value);
	}

	return '0';
}
