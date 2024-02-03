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

require(__DIR__ . '/include/cli_check.php');

/* include important functions */
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/lib/boost.php');

/* get the boost polling cycle */
$max_run_duration = read_config_option('boost_rrd_update_max_runtime');

/* process calling arguments, first remove the script name */
$parms = $_SERVER['argv'];
array_shift($parms);

/* second, get the socket integer value */
$socket_int_value = $parms[0];
array_shift($parms);

/* last, recombine the arguments */
$command = implode(' ', $parms);

/* execute the command */
if ($config['cacti_server_os'] == 'win32') {
	$handle = popen($command, 'rb');
} else {
	$handle = popen($command, 'r');
}

/* get the results */
$result = fread($handle, 1024);

if (trim($result) == '') {
	$result = 'OK';
} else {
	if (substr_count($result, "\r")) {
		$result = str_replace("\r", '', $result);
	}
	$result_array = explode("\n", $result);

	if (cacti_sizeof($result_array)) {
		$result = $result_array[cacti_sizeof($result_array)-2];
	} else {
		$result = 'ERROR: Detected unknown error';
	}
}

/* add the value to the table */
db_execute_prepared('INSERT INTO poller_output_boost_processes
	(sock_int_value, status)
	VALUES (?, ?)', array($socket_int_value, $result));

/* close the connection */
pclose($handle);

/* return the rrdupdate results */
return $result;

