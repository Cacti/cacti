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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include(dirname(__FILE__) . '/../include/cli_check.php');
include_once($config['base_path'] . '/lib/utility.php');
include_once($config['base_path'] . '/lib/api_data_source.php');
include_once($config['base_path'] . '/lib/poller.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug = false;
$host_id = 0;
$host_template_id = 0;
$data_template_id = 0;

foreach($parms as $parameter) {
	@list($arg, $value) = @explode('=', $parameter);

	switch ($arg) {
		case '--host-id':
			$host_id = trim($value);

			if (!is_numeric($host_id)) {
				print 'ERROR: You must supply a valid Device Id to run this script!' . PHP_EOL;
				exit(1);
			}

			break;
		case '--host-template-id':
			$host_template_id = trim($value);

			if (!is_numeric($host_template_id)) {
				print 'ERROR: You must supply a valid Device Template Id to run this script!' . PHP_EOL;
				exit(1);
			}

			break;
		case '--data-template-id':
			$data_template_id = trim($value);

			if (!is_numeric($data_template_id)) {
				print 'ERROR: You must supply a valid Data Template Id to run this script!' . PHP_EOL;
				exit(1);
			}

			break;
		case '-d':
		case '--debug':
			$debug = true;

			break;
		case '-h':
		case '-H':
		case '--help':
			display_help();

			exit;
		case '-v':
		case '-V':
		case '--version':
			display_version();

			exit;
		default:
			print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;

			display_help();
			exit;
	}
}

/* obtain timeout settings */
$max_execution = ini_get('max_execution_time');
$max_memory    = ini_get('memory_limit');

/* set new timeout and memory settings */
ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');

$sql_where = '';
$params    = array();

if ($host_id > 0) {
	$sql_where = ' AND h.id = ?';
	$params[] = $host_id;
}

if ($host_template_id > 0) {
	$sql_where .= ' AND h.host_template_id = ?';
	$params[] = $host_template_id;
}

/* clear the poller cache first */
$hosts = db_fetch_assoc_prepared("SELECT h.id
	FROM host AS h
	WHERE h.disabled = ''
	$sql_where",
	$params);

/* initialize some variables */
$current_host = 1;
$total_hosts  = sizeof($hosts);

/* issue warnings and start message if applicable */
print 'WARNING: Do not interrupt this script.  Rebuilding the Poller Cache can take quite some time' . PHP_EOL;
debug("There are '$total_hosts' hosts to push out.");

/* start rebuilding the poller cache */
if (cacti_sizeof($hosts) > 0) {
	foreach ($hosts as $host) {
		if (!$debug) print '.';
		push_out_host($host['id'], 0, $data_template_id);
		debug("Host ID '" . $host['id'] . "' or '$current_host' of '$total_hosts' updated");
		$current_host++;
	}
}
if (!$debug) {
	print PHP_EOL;
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Push Out Host Poller Cache Script, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL . 'usage: push_out_hosts.php [--host-id=N] [--host-template-id=N] [--debug]' . PHP_EOL . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print ' --host-id=N           - Run for a specific Device' . PHP_EOL;
	print ' --host-template-id=N  - Run for a specific Device Template' . PHP_EOL;
	print ' --data-template-id=N  - Run for a specific Data Template' . PHP_EOL;
	print ' --debug               - Display verbose output during execution' . PHP_EOL . PHP_EOL;
}

function debug($message) {
	global $debug;

	if ($debug) {
		print('DEBUG: ' . trim($message) . PHP_EOL);
	}
}
