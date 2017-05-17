#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset ($_SERVER['argv'][0]) || isset ($_SERVER['REQUEST_METHOD']) || isset ($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

ini_set('max_execution_time', '0');

$no_http_headers = true;

include (dirname(__FILE__) . '/../include/global.php');
include_once ($config['base_path'] . '/lib/api_data_source.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

/* utility requires input parameters */
if (sizeof($parms) == 0) {
	print "ERROR: You must supply input parameters\n\n";
	display_help();
	exit;
}

$debug   = FALSE;
$host_id = '';
$filter  = '';

if (sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-id' :
			case '--id' :
			case '--host-id' :
				$host_id = $value;
				break;
			case '-s' :
			case '--filter' :
				$filter = $value;
				break;
			case '-d' :
			case '--debug' :
				$debug = TRUE;
				break;
			case '--version' :
			case '-v' :
			case '-V' :
				display_version();
				exit;
			case '--help' :
			case '-H' :
			case '-h' :
				display_help();
				exit;
			default :
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

/* form the 'where' clause for our main sql query */
if ($filter != '') {
	$sql_where = "AND (data_template_data.name_cache like '%" . $filter . "%'" .
	" OR data_template_data.local_data_id like '%" . $filter . "%'" .
	" OR data_template.name like '%" . $filter . "%'" .
	" OR data_input.name like '%" . $filter . "%')";
} else {
	$sql_where = "";
}

if (strtolower($host_id) == 'all') {
	/* Act on all graphs */
} elseif (substr_count($host_id, ',')) {
	$hosts = explode(',', $host_id);
	$host_str = '';

	foreach ($hosts as $host) {
		if (is_numeric($host) && $host > 0) {
			$host_str .= ($host_str != '' ? ', ':'') . $host;
		}
	}

	$sql_where .= " AND data_local.host_id IN ($host_str)";
} elseif ($host_id == '0') {
	$sql_where .= ' AND data_local.host_id=0';
} elseif (!empty($host_id) && $host_id > 0) {
	$sql_where .= ' AND data_local.host_id=' . $host_id;
} else {
	print "ERROR: You must specify either a host_id or 'all' to proceed.\n";
	display_help();
	exit;
}

$data_source_list = db_fetch_assoc("SELECT data_template_data.local_data_id, data_template_data.name_cache, data_template_data.active, 
	data_input.name as data_input_name, data_template.name as data_template_name, data_local.host_id 
	FROM (data_local,data_template_data)
	LEFT JOIN data_input
	ON (data_input.id=data_template_data.data_input_id)
	LEFT JOIN data_template
	ON (data_local.data_template_id=data_template.id)
	WHERE data_local.id=data_template_data.local_data_id
	$sql_where");

/* issue warnings and start message if applicable */
print "WARNING: Do not interrupt this script.  Interrupting during rename can cause issues\n";
debug("There are '" . sizeof($data_source_list) . "' Data Sources to rename");

$i = 1;
foreach ($data_source_list as $data_source) {
	if (!$debug)
		print ".";
	debug("Data Source Name '" . $data_source['name_cache'] . "' starting");
	api_reapply_suggested_data_source_title($data_source['local_data_id']);
	update_data_source_title_cache($data_source['local_data_id']);
	debug("Data Source Rename Done for Data Source '" . addslashes(get_data_source_title($data_source['local_data_id'])) . "'");
	$i++;
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Reapply Data Source Names Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help() {
	display_version();

	echo "\nusage: poller_data_sources_reapply_names.php --host-id=[id|all][N1,N2,...] [--filter=string] [--debug]\n\n";
	echo "A utility that will recalculate Data Source names for the selected Data Templates.\n\n";
	echo "Required:\n";
	echo "    --host-id=N|all|N1,N2,... - The deivces id, 'all' or a comma delimited list of id's\n\n";
	echo "Optional:\n";
	echo "    --filter=search           - A Data Template name or Data Source Title to search for\n";
	echo "    --debug                   - Display verbose output during execution\n\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		print ('DEBUG: ' . $message . "\n");
	}
}
