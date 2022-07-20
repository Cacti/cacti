#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

require(__DIR__ . '/../include/cli_check.php');
require_once($config['base_path'] . '/lib/api_automation_tools.php');
require_once($config['base_path'] . '/lib/api_automation.php');
require_once($config['base_path'] . '/lib/api_data_source.php');
require_once($config['base_path'] . '/lib/api_graph.php');
require_once($config['base_path'] . '/lib/api_device.php');
require_once($config['base_path'] . '/lib/api_tree.php');
require_once($config['base_path'] . '/lib/data_query.php');
require_once($config['base_path'] . '/lib/functions.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/reports.php');
require_once($config['base_path'] . '/lib/template.php');
require_once($config['base_path'] . '/lib/utility.php');

ini_set('max_execution_time', '0');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug;

$debug       = false;
$hostname    = '';
$description = '';
$ids         = array();

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-d':
			case '--debug':
				$debug = true;
				break;
			case '--hostname':
				$hostname = $value;
				break;
			case '--description':
				$description = $value;
				break;
			case '--ids':
				$ids = explode(' ', $value);
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit(0);
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit(0);
			default:
				print "ERROR: Invalid Parameter " . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit(1);
		}
	}
}

// Check for matching like/regex
if (!cacti_sizeof($ids) && $hostname == '' && $description == '') {
	print 'FATAL: You must specify either ids, a hostname or host description pattern' . PHP_EOL;
	exit(1);
}

$sql_where = '';

// Check device id range
if (cacti_sizeof($ids)) {
	foreach($ids as $id) {
		if (!is_numeric($id) || $id <= 0) {
			print 'FATAL: Device id ' . $id . ' is not a valid device.  Can not continue.' . PHP_EOL;
			exit(1);
		}
	}

	$sql_where = 'WHERE h.id IN (' . implode(',', $ids) . ')';
}

// Check hostname
if ($hostname != '') {
	$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(';
	$regex = false;

	if (validate_is_regex($hostname)) {
		$regex = true;
		$sql_where .= 'h.hostname RLIKE ' . db_qstr($hostname);
	}

	$hostname = '%' . $hostname . '%';
	$sql_where .= ($regex == true ? ' OR ':'') . 'h.hostname LIKE ' . db_qstr($hostname) . ')';
}

$dregex = false;
if ($description != '') {
	$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(';
	$regex = false;

	if (validate_is_regex($description)) {
		$regex = true;
		$sql_where .= 'h.description RLIKE ' . db_qstr($description);
	}

	$description = '%' . $description . '%';
	$sql_where .= ($regex == true ? ' OR ':'') . 'h.description LIKE ' . db_qstr($description) . ')';
}

$devices = array_rekey(
	db_fetch_assoc('SELECT id FROM host AS h ' . $sql_where),
	'id', 'id'
);

if (cacti_sizeof($devices)) {
	if ($debug) {
		print 'DEBUG: Found ' . cacti_sizeof($devices) . ' devices to run automation on' . PHP_EOL;
	}

	foreach($devices as $device_id) {
		if ($debug) {
			print 'DEBUG: Running automation for Device ID ' . $device_id . PHP_EOL;
		}

		cacti_log('NOTE: Running CLI based Batch Automation on Device[' . $device_id . ']', false, 'AUTOM8');

		automation_update_device($device_id);
	}

	if ($debug) {
		print 'DEBUG: automation run complete!' . PHP_EOL;
	}
} elseif ($debug) {
	print 'DEBUG: No devices found for this automation pass.' . PHP_EOL;
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Apply Automation Rules Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL;
	print 'usage: apply_automation_rules.php --ids="id1 id2 ..." || --description=S || --hostname=S [--debug]' . PHP_EOL . PHP_EOL;
	print 'A utility to execute Cacti automation rules for a devices or devices.  Any of the following' . PHP_EOL;
	print 'three options can be used, but at least one must be specified.' . PHP_EOL . PHP_EOL;
	print 'Required:' . PHP_EOL;
	print '    --ids="id1 id2 ..." - A space delimited list of device ids.' . PHP_EOL;
	print '    --hostname="S"      - Either a SQL where clause or REGEX of the devices hostname.' . PHP_EOL;
	print '    --description="S"   - Either a SQL where clause or REGEX of the devices description.' . PHP_EOL . PHP_EOL;
	print 'Optional:' . PHP_EOL;
	print '    --debug             - Provide verbose output during automation' . PHP_EOL . PHP_EOL;
}
