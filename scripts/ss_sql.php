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

	print call_user_func('ss_sql');
}

function ss_sql() : string {
	$status = ss_sql_status();

	if (!cacti_sizeof($status)) {
		return 'U';
	}

	return ss_sql_format($status);
}

/**
 * ss_sql_status - read the server counters that mysqladmin status reports
 *
 * Reading them over the existing connection avoids starting mysqladmin with
 * the database password on its command line.
 *
 * @return array The status values keyed by variable name, empty on failure
 */
function ss_sql_status() : array {
	$rows = db_fetch_assoc("SHOW GLOBAL STATUS
		WHERE Variable_name IN ('Uptime', 'Threads_connected', 'Queries', 'Questions',
		'Slow_queries', 'Opened_tables', 'Flush_commands', 'Open_tables')");

	$status = [];

	if (is_array($rows)) {
		foreach ($rows as $row) {
			if (isset($row['Variable_name'], $row['Value'])) {
				$status[$row['Variable_name']] = $row['Value'];
			}
		}
	}

	return $status;
}

/**
 * ss_sql_format - format status values the way ss_sql() has always reported them
 *
 * The server's own status line counts statements by query id, which is the
 * Queries counter, and truncates the average rate to three decimals. MariaDB
 * omits the flush count, so it is only reported when the server has one.
 *
 * @param array $status Status values keyed by variable name
 *
 * @return string The status line, or 'U' when a counter is missing
 */
function ss_sql_format(array $status) : string {
	$queries = $status['Queries'] ?? ($status['Questions'] ?? null);

	foreach ([$status['Uptime'] ?? null, $status['Threads_connected'] ?? null, $queries, $status['Slow_queries'] ?? null, $status['Opened_tables'] ?? null, $status['Open_tables'] ?? null] as $value) {
		if ($value === null || !ctype_digit((string) $value)) {
			return 'U';
		}
	}

	$uptime = (int) $status['Uptime'];
	$qps    = ($uptime > 0 ? intdiv((int) $queries * 1000, $uptime) : 0);

	$result = 'Uptime:' . $status['Uptime'] .
		' Threads:' . $status['Threads_connected'] .
		' Questions:' . $queries .
		' SlowQueries:' . $status['Slow_queries'] .
		' Opens:' . $status['Opened_tables'];

	if (isset($status['Flush_commands']) && ctype_digit((string) $status['Flush_commands'])) {
		$result .= ' FlushTables:' . $status['Flush_commands'];
	}

	$result .= ' OpenTables:' . $status['Open_tables'] .
		' QPS:' . sprintf('%d.%03d', intdiv($qps, 1000), $qps % 1000);

	return $result;
}
