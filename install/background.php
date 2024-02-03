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
error_reporting(E_ALL);

define('IN_CACTI_INSTALL', 1);

include_once(__DIR__ . '/../include/cli_check.php');
include_once(CACTI_PATH_INSTALL . '/functions.php');
include_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
include_once(CACTI_PATH_LIBRARY . '/api_device.php');
include_once(CACTI_PATH_LIBRARY . '/api_automation.php');
include_once(CACTI_PATH_LIBRARY . '/api_automation_tools.php');
include_once(CACTI_PATH_LIBRARY . '/data_query.php');
include_once(CACTI_PATH_LIBRARY . '/import.php');
include_once(CACTI_PATH_LIBRARY . '/installer.php');
include_once(CACTI_PATH_LIBRARY . '/poller.php');
include_once(CACTI_PATH_LIBRARY . '/snmp.php');
include_once(CACTI_PATH_LIBRARY . '/utility.php');

cacti_log('Checking arguments', false, 'INSTALL:');
/* process calling arguments */
$params = $_SERVER['argv'];
array_shift($params);

global $cli_install;

$cli_install = true;

if (cacti_sizeof($params) == 0) {
	log_install_always('','no parameters passed' . PHP_EOL);

	exit();
}

Installer::beginInstall($params[0]);
