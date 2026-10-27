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
	global $database_username, $database_password, $database_hostname, $database_port;

	$defaults_file = db_client_defaults_file($database_username, $database_password, $database_hostname, (int) $database_port);

	if ($defaults_file === false) {
		return 'U';
	}

	try {
		$result = shell_exec('mysqladmin --defaults-file=' . cacti_escapeshellarg($defaults_file) . ' status') ?? '';
	} finally {
		unlink($defaults_file);
	}

	$result = preg_replace('/: /', ':', (string) $result);
	$result = preg_replace('/  /', ' ', (string) $result);
	$result = preg_replace('/Slow queries/', 'SlowQueries', (string) $result);
	$result = preg_replace('/Open tables/', 'OpenTables', (string) $result);
	$result = preg_replace('/Queries per second avg/', 'QPS', (string) $result);
	$result = preg_replace('/Flush tables/', 'FlushTables', (string) $result);

	return trim((string) $result) ?: 'U';
}
