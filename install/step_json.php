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

/* since we'll have additional headers, tell php when to flush them */
ob_start();

// Prevent redirect to /install/
define('IN_CACTI_INSTALL', 1);
chdir(__DIR__ . '/../');

/* set the json variable for request validation handling */
include_once('lib/functions.php');
include_once('lib/html_utility.php');
set_request_var('json', true);
$auth_json = true;

include('include/auth.php');
include('install/functions.php');
include('lib/installer.php');
include('lib/utility.php');

$debug = false;


$initialData = array();
/* ================= input validation ================= */
get_nfilter_request_var('data', array());
if (isset_request_var('data') && get_nfilter_request_var('data')) {
	log_install_debug('json','Using supplied data');
	$initialData = get_nfilter_request_var('data');
	if (!is_array($initialData)) {
		$initialData = array($initialData);
	}
}

$json_level = log_install_level('json',POLLER_VERBOSITY_NONE);
log_install_high('json','Start: ' . clean_up_lines(json_encode($initialData)));

$initialData = array_merge(array('Runtime' => 'Web'), $initialData);
if (isset($initialData['step']) && $initialData['step'] == Installer::STEP_TEST_REMOTE) {
	$json = install_test_remote_database_connection();
	$json_debug = $json;
} else {
	$installer = new Installer($initialData);
	$json = json_encode($installer);

	$json_debug = $json;
	if ($json_level < POLLER_VERBOSITY_DEBUG) {
		$installer->setRuntime('Json');
		$json_debug = json_encode($installer);
	}

}
log_install_high('json','  End: ' . clean_up_lines($json_debug) . PHP_EOL);
header('Content-Type: application/json');
header('Content-Length: ' . strlen($json));
print $json;
