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

/* since we'll have additional headers, tell php when to flush them */
ob_start();

// Prevnt redirect to /install/
define('IN_CACTI_INSTALL', 1);

/* set the json variable for request validation handling */
include('../lib/html_utility.php');
set_request_var('json', true);

include('../include/auth.php');
include('../lib/installer.php');
include('functions.php');
include('../lib/utility.php');

$debug = false;

/* ================= input validation ================= */
get_filter_request_var('step');
get_filter_request_var('data');

$param_step = -1;
$step = 1;
if (!isempty_request_var('step')) {
	$param_step = get_request_var('step');
	if ($param_step > 0 && $param_step < 10) {
		$step = $param_step;
	}
}

$installer = new Installer();
$installer->setDefaultStep($step);
$output = trim($installer->getOutput());
$oarray = array(
	'param_step' => $param_step,
	'calc_step' => $step,
	'step_data' => $installer->stepData,
	'step' => $installer->stepCurrent,
	'prev' => $installer->buttonPrevious,
	'next' => $installer->buttonNext,
	'test' => $installer->buttonTest,
	'html' => $output
);
$json = json_encode($oarray);

header('Content-Type: application/json');
header('Content-Length: ' . strlen($json));
print $json;
