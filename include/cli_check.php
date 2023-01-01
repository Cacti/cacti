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

/* The following define forces the system to  NOT run
 * through a web browser at all, because that would be
 * bad.
 *
 * CLI scripts use this file as a way to predefine that
 * they are not to be run from a browser
 *
 * Any source wishing to check whether the system is in
 * CLI or WEB mode should use the CACTI_WEB and
 * CACTI_CLI constants
define('CACTI_CLI_ONLY', true);

/* We are not talking to the browser */
$no_http_headers = true;

/* Make sure CLI's are have minimum settings */
$default_limit  = -1;
$default_time   = -1;
$memory_limit   = ini_get('memory_limit');
$execution_time = ini_get('max_execution_time');

if ($memory_limit != $default_limit) {
	ini_set('memory_limit', $default_limit);
}

if ($execution_time < $default_time && $execution_time >= 0) {
	ini_set('max_execution_time', $default_time);
}

include(__DIR__ . '/global.php');
