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

// display No errors
error_reporting(0);

if (!isset($called_by_script_server)) {
	include(__DIR__ . '/../include/cli_check.php');
	array_shift($_SERVER['argv']);
	print call_user_func_array('ss_mikrotik_wireless_reg', $_SERVER['argv']);
}

function ss_mikrotik_wireless_reg($host_id, $cmd, $object = '', $index = '') {
	if ($cmd == 'index') {
		$return_arr = db_fetch_assoc_prepared('SELECT `index` AS macAddress
			FROM plugin_mikrotik_wireless_registrations
			WHERE host_id = ?
			ORDER BY macAddress',
			[$host_id]);

		foreach ($return_arr as $index) {
			print $index['macAddress'] . "\n";
		}
	} elseif ($cmd == 'num_indexes') {
		$indexes = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM plugin_mikrotik_wireless_registrations
			WHERE host_id = ?',
			[$host_id]);

		return $indexes;
	} elseif ($cmd == 'query') {
		if ($object == 'macAddress') {
			$return_arr = db_fetch_assoc_prepared('SELECT `index` AS macAddress
				FROM plugin_mikrotik_wireless_registrations
				WHERE host_id = ?
				ORDER BY macAddress',
				[$host_id]);

			if (cacti_sizeof($return_arr)) {
				foreach ($return_arr as $index) {
					print $index['macAddress'] . '!' . $index['macAddress'] . "\n";
				}
			}
		}
	} elseif ($cmd == 'get') {
		return db_fetch_cell_prepared("SELECT `$object`
			FROM plugin_mikrotik_wireless_registrations
			WHERE host_id = ?
			AND `index` = ?",
			[$host_id, $index]);
	}
}
