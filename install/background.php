<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

$no_http_headers = true;

include_once(dirname(__FILE__) . '/../include/global.php');
include_once($config['base_path'] . '/lib/api_data_source.php');
include_once($config['base_path'] . '/lib/api_device.php');
include_once($config['base_path'] . '/lib/utility.php');
include_once($config['base_path'] . '/lib/import.php');
include_once($config['base_path'] . '/install/functions.php');
include_once($config['base_path'] . '/lib/installer.php');
include_once($config['base_path'] . '/lib/data_query.php');
include_once($config['base_path'] . '/lib/api_automation.php');
include_once($config['base_path'] . '/lib/api_automation_tools.php');

cacti_log('Checking arguments', false, 'INSTALL:');
/* process calling arguments */
$params = $_SERVER['argv'];
array_shift($params);

if (sizeof($params) == 0) {
	die("no parameters passed\n");
}

$backgroundTime = read_config_option('install_started', true);
if ($backgroundTime === null) {
	$backgroundTime = false;
}

$backgroundArg = $params[0];

if ("$backgroundTime" != "$backgroundArg" && "-b" != "$backgroundArg") {
	$dateTime = DateTime::createFromFormat('U.u', $backgroundTime);
	$dateArg = DateTime::createFromFormat('U.u', $backgroundArg);
	cacti_log(__('Background was already started at %s, this attempt at %s was skipped',
		$dateTime->format('Y-m-d H:i:s.u'),
		$dateArg->format('Y-m-d H:i:s.u')
	), false, 'INSTALL:');
	exit;
}

try {
	$backgroundTime = microtime(true);
	$installer = new Installer();
	$installer->processBackgroundInstall();
} catch (Exception $e) {
	cacti_log(__('Exception occurred during installation:  #' . $e->getErrorCode() . ' - ' . $e->getErrorText()), false, 'INSTALL:');
}

$backgroundDone = microtime(true);
set_config_option('install_complete', $backgroundDone);

$dateBack = DateTime::createFromFormat('U.u', $backgroundTime);
$dateTime = DateTime::createFromFormat('U.u', $backgroundDone);

cacti_log(__('Installation was started at %s, completed at %s', $dateBack->format('Y-m-d H:i:s'), $dateTime->format('Y-m-d H:i:s')), false, 'INSTALL:');
