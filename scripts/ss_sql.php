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
	include_once(dirname(__FILE__) . '/../include/cli_check.php');

	print call_user_func('ss_sql');
}

/**
 * Handles the ss SQL. Used as part of Cacti's scripts functionality.
 *
 * Reports the fields of 'mysqladmin status' from the server status variables
 * over Cacti's own database connection, so the database password is never
 * placed on a command line.
 *
 * @return string The resulting string.
 */
function ss_sql() {
	global $local_db_cnn_id;

	$rows = db_fetch_assoc("SHOW GLOBAL STATUS WHERE Variable_name IN
		('Uptime', 'Threads_connected', 'Questions', 'Slow_queries', 'Opened_tables', 'Flush_commands', 'Open_tables')",
		false, (is_object($local_db_cnn_id) ? $local_db_cnn_id : false));

	if (!cacti_sizeof($rows)) {
		return 'U';
	}

	$status = array();

	foreach ($rows as $row) {
		$status[$row['Variable_name']] = $row['Value'];
	}

	if (!isset($status['Uptime']) || !isset($status['Questions'])) {
		return 'U';
	}

	/* mysqladmin prints Questions / Uptime truncated to three decimals */
	$uptime = (int) $status['Uptime'];

	if ($uptime > 0) {
		$qps = intdiv((int) $status['Questions'] * 1000, $uptime);
		$qps = intdiv($qps, 1000) . '.' . sprintf('%03d', $qps % 1000);
	} else {
		$qps = '0.000';
	}

	$fields = array(
		'Uptime'      => 'Uptime',
		'Threads'     => 'Threads_connected',
		'Questions'   => 'Questions',
		'SlowQueries' => 'Slow_queries',
		'Opens'       => 'Opened_tables',
		'FlushTables' => 'Flush_commands',
		'OpenTables'  => 'Open_tables'
	);

	$result = array();

	/* MariaDB has no Flush_commands and mysqladmin leaves the field out there */
	foreach ($fields as $name => $variable) {
		if (isset($status[$variable])) {
			$result[] = $name . ':' . $status[$variable];
		}
	}

	$result[] = 'QPS:' . $qps;

	return implode(' ', $result);
}
