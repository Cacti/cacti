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
	print call_user_func_array('ss_mikrotik_cpu', $_SERVER['argv']);
}

/**
 * Return the average and peak processor load of a MikroTik device.
 *
 * @param int|string $host_id The Cacti host id
 *
 * @return string 'cpu:<avg> max:<max>', U when there is no data
 */
function ss_mikrotik_cpu($host_id = 0) {
	// the table only exists when the mikrotik plugin is installed
	if (!db_table_exists('plugin_mikrotik_processor', false)) {
		return 'cpu:U max:U';
	}

	$cpu = db_fetch_row_prepared('SELECT AVG(`load`) AS avgCPU, MAX(`load`) AS maxCPU
		FROM plugin_mikrotik_processor
		WHERE host_id = ?',
		[(int) $host_id]);

	// the aggregates are NULL when the device has no processors
	$avg = (isset($cpu['avgCPU']) && is_numeric($cpu['avgCPU'])) ? $cpu['avgCPU'] : 'U';
	$max = (isset($cpu['maxCPU']) && is_numeric($cpu['maxCPU'])) ? $cpu['maxCPU'] : 'U';

	return 'cpu:' . $avg . ' max:' . $max;
}
