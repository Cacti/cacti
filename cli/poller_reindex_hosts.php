#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/snmp.php');
require_once($config['base_path'] . '/lib/sort.php');
require_once($config['base_path'] . '/lib/template.php');
require_once($config['base_path'] . '/lib/utility.php');

ini_set('max_execution_time', '0');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug		= false;
$host_id	= '';
$query_id	= 'all';		/* just to mimic the old behaviour */
$host_descr	= '';

if (cacti_sizeof($parms)) {
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
				$debug = true;
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
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit(1);
		}
	}
} else {
	print "ERROR: You must supply input parameters\n\n";
	display_help();
	exit(1);
}

/* determine the hosts to reindex */
if (strtolower($host_id) == 'all') {
	$sql_where = '';
}else if (is_numeric($host_id) && $host_id > 0) {
	$sql_where = 'WHERE host_id = ' . $host_id;
} else {
	print "ERROR: You must specify either a host_id or 'all' to proceed.\n";
	display_help();
	exit;
}

/* determine data queries to rerun */
if (strtolower($query_id) == 'all') {
	/* do nothing */
}else if (is_numeric($query_id) && $query_id > 0) {
	$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' snmp_query_id=' . $query_id;
} else {
	print "ERROR: You must specify either a query_id or 'all' to proceed.\n";
	display_help();
	exit;
}

/* allow for additional filtering on host description */
if ($host_descr != '') {
	$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . " host.description LIKE '%" . $host_descr . "%' AND host.id=host_snmp_query.host_id";

	$data_queries = db_fetch_assoc('SELECT host_id, snmp_query_id
		FROM host_snmp_query, host ' . $sql_where);
} else {
	$data_queries = db_fetch_assoc('SELECT host_id, snmp_query_id
		FROM host_snmp_query ' . $sql_where);
}

/* issue warnings and start message if applicable */
print "WARNING: Do not interrupt this script.  Reindexing can take quite some time\n";
debug("There are '" . cacti_sizeof($data_queries) . "' data queries to run");

$i = 1;
if (cacti_sizeof($data_queries)) {
	foreach ($data_queries as $data_query) {
		if (!$debug) print '.';
		debug("Data query number '" . $i . "' host: '" . $data_query['host_id'] . "' SNMP Query Id: '" . $data_query['snmp_query_id'] . "' starting");
		run_data_query($data_query['host_id'], $data_query['snmp_query_id']);
		debug("Data query number '" . $i . "' host: '" . $data_query['host_id'] . "' SNMP Query Id: '" . $data_query['snmp_query_id'] . "' ending");
		$i++;
	}
}

function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Reindex Host Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();
	print "usage: poller_reindex_hosts.php --id=[host_id|all] [--qid=[ID|all]]\n";
	print "   [--host-descr=[description]] [--debug]\n\n";
	print "--id=host_id             - The host_id to have data queries reindexed or 'all' to reindex all hosts\n";
	print "--qid=query_id           - Only index on a specific data query id; defaults to 'all'\n";
	print "--host-descr=description - The host description to filter by (SQL filters acknowledged)\n";
	print "--debug                  - Display verbose output during execution\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		print('DEBUG: ' . $message . "\n");
	}
}
