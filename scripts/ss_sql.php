#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

function ss_sql() {
	global $database_username;
	global $database_password;
	global $database_hostname;

	if ($database_password != '') {
		$result = `mysqladmin --host=$database_hostname --user=$database_username --password=$database_password status`;
	} else {
		$result = `mysqladmin --host=$database_hostname --user=$database_username status`;
	}

	$result = preg_replace('/: /', ':', $result);
	$result = preg_replace('/  /', ' ', $result);
	$result = preg_replace('/Slow queries/', 'SlowQueries', $result);
	$result = preg_replace('/Open tables/', 'OpenTables', $result);
	$result = preg_replace('/Queries per second avg/', 'QPS', $result);
	$result = preg_replace('/Flush tables/', 'FlushTables', $result);

	return trim($result);
}
