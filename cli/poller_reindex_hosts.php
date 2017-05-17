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
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

ini_set('max_execution_time', '0');

$no_http_headers = true;

include(dirname(__FILE__) . '/../include/global.php');
include_once($config['base_path'] . '/lib/snmp.php');
include_once($config['base_path'] . '/lib/data_query.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug		= FALSE;
$host_id	= '';
$query_id	= 'all';		/* just to mimic the old behaviour */
$host_descr	= '';

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-id':
			case '--id':
				$host_id = $value;
				break;
			case '-qid':
			case '--qid':
				$query_id = $value;
				break;
			case '-host-descr':
			case '--host-descr':
				$host_descr = $value;
				break;
			case '-d':
			case '--debug':
				$debug = TRUE;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
} else {
	print "ERROR: You must supply input parameters\n\n";
	display_help();
	exit;
}

/* determine the hosts to reindex */
if (strtolower($host_id) == 'all') {
	$sql_where = '';
}else if (is_numeric($host_id) && $host_id > 0) {
	$sql_where = ' WHERE host_id = ' . $host_id;
} else {
	print "ERROR: You must specify either a host_id or 'all' to proceed.\n";
	display_help();
	exit;
}

/* determine data queries to rerun */
if (strtolower($query_id) == 'all') {
	/* do nothing */
}else if (is_numeric($query_id) && $query_id > 0) {
	$sql_where .= ($sql_where != '' ? ' AND snmp_query_id=' . $query_id : ' WHERE snmp_query_id=' . $query_id);
} else {
	print "ERROR: You must specify either a query_id or 'all' to proceed.\n";
	display_help();
	exit;
}

/* allow for additional filtering on host description */
if ($host_descr != '') {
	$sql_where .= ($sql_where != '' ? " AND host.description like '%" . $host_descr . "%' AND host.id=host_snmp_query.host_id" : " WHERE host.description like '%" . $host_descr . "%' AND host.id=host_snmp_query.host_id");
	$data_queries = db_fetch_assoc('SELECT host_id, snmp_query_id FROM host_snmp_query,host' . $sql_where);
} else {
	$data_queries = db_fetch_assoc('SELECT host_id, snmp_query_id FROM host_snmp_query' . $sql_where);
}

/* issue warnings and start message if applicable */
print "WARNING: Do not interrupt this script.  Reindexing can take quite some time\n";
debug("There are '" . sizeof($data_queries) . "' data queries to run");

$i = 1;
if (sizeof($data_queries)) {
	foreach ($data_queries as $data_query) {
		if (!$debug) print '.';
		debug("Data query number '" . $i . "' host: '" . $data_query['host_id'] . "' SNMP Query Id: '" . $data_query['snmp_query_id'] . "' starting");
		run_data_query($data_query['host_id'], $data_query['snmp_query_id']);
		debug("Data query number '" . $i . "' host: '" . $data_query['host_id'] . "' SNMP Query Id: '" . $data_query['snmp_query_id'] . "' ending");
		$i++;
	}
}

/*	display_help - displays the usage of the function */
function display_help () {
	$version = get_cacti_version();
	echo "Reindex Host Utility, Version $version, " . COPYRIGHT_YEARS . "\n\n";
	echo "usage: poller_reindex_hosts.php --id=[host_id|all] [--qid=[ID|all]] [--host-descr=[description]]\n";
	echo "                           [-d] [-h] [--help] [-v] [--version]\n\n";
	echo "--id=host_id             - The host_id to have data queries reindexed or 'all' to reindex all hosts\n";
	echo "--qid=query_id           - Only index on a specific data query id; defaults to 'all'\n";
	echo "--host-descr=description - The host description to filter by (SQL filters acknowledged)\n";
	echo "--debug                  - Display verbose output during execution\n";
	echo "-v --version             - Display this help message\n";
	echo "-h --help                - Display this help message\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		print('DEBUG: ' . $message . "\n");
	}
}
