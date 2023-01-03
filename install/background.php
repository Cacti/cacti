<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

include_once(dirname(__FILE__) . '/../include/cli_check.php');
include_once($config['base_path'] . '/install/functions.php');
include_once($config['base_path'] . '/lib/api_data_source.php');
include_once($config['base_path'] . '/lib/api_device.php');
include_once($config['base_path'] . '/lib/api_automation.php');
include_once($config['base_path'] . '/lib/api_automation_tools.php');
include_once($config['base_path'] . '/lib/data_query.php');
include_once($config['base_path'] . '/lib/import.php');
include_once($config['base_path'] . '/lib/installer.php');
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/lib/snmp.php');
include_once($config['base_path'] . '/lib/utility.php');

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
