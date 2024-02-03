#!/usr/bin/env php
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

require(__DIR__ . '/../include/cli_check.php');
require_once(CACTI_PATH_LIBRARY . '/api_automation_tools.php');
require_once(CACTI_PATH_LIBRARY . '/api_automation.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/api_graph.php');
require_once(CACTI_PATH_LIBRARY . '/api_device.php');
require_once(CACTI_PATH_LIBRARY . '/api_tree.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/snmp.php');
require_once(CACTI_PATH_LIBRARY . '/sort.php');
require_once(CACTI_PATH_LIBRARY . '/template.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

ini_set('max_execution_time', '0');

/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug		    = false;
$host_id	   = '';
$query_id	  = 'all';		/* just to mimic the old behaviour */
$host_descr	= '';
$force      = false;

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
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
			case '--force':
				$force = true;

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
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
		}
	}
} else {
	print 'ERROR: You must supply input parameters' . PHP_EOL . PHP_EOL;
	display_help();

	exit(1);
}

/* determine the hosts to reindex */
$params = array();

if (strtolower($host_id) == 'all') {
	$sql_where = '';
} elseif (is_numeric($host_id) && $host_id > 0) {
	$sql_where = 'WHERE host_id = ?';
	$params[]  = $host_id;
} else {
	print 'ERROR: You must specify either a host_id or \'all\' to proceed.' . PHP_EOL;

	display_help();

	exit;
}

/* determine data queries to rerun */
if (strtolower($query_id) == 'all') {
	/* do nothing */
} elseif (is_numeric($query_id) && $query_id > 0) {
	$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' hsq.snmp_query_id = ?';
	$params[] = $query_id;
} else {
	print 'ERROR: You must specify either a query_id or \'all\' to proceed.' . PHP_EOL;

	display_help();

	exit;
}

/* allow for additional filtering on host description */
$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' IFNULL(TRIM(s.disabled),"") != "on" AND IFNULL(TRIM(h.disabled),"") != "on"';

if ($host_descr != '') {
	$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' h.description LIKE ?';
	$params[] = '%' . $host_descr . '%';
}

$data_queries = db_fetch_assoc_prepared("SELECT h.description, h.hostname, hsq.host_id, hsq.snmp_query_id
	FROM host_snmp_query hsq
	INNER JOIN host h
	ON h.id = hsq.host_id
	LEFT JOIN sites s
	ON s.id = h.site_id
	$sql_where",
	$params);

/* issue warnings and start message if applicable */
print 'WARNING: Do not interrupt this script.  Reindexing can take quite some time' . PHP_EOL;
debug("There are '" . cacti_sizeof($data_queries) . "' data queries to run");

/* silently end if the registered process is still running  */
if (!$force) {
	if (!register_process_start('reindex', 'master', 0, 86400)) {
		print "FATAL: Detected an already running process.  Use --force to override" . PHP_EOL;
		exit(0);
	}
}

$i = 1;
$total_start = microtime(true);

if (cacti_sizeof($data_queries)) {
	foreach ($data_queries as $data_query) {
		if (!$debug) {
			print '.';
		}

		$start = microtime(true);

		run_data_query($data_query['host_id'], $data_query['snmp_query_id'], false, $force);

		$items = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM host_snmp_cache
			WHERE host_id = ?
			AND snmp_query_id = ?',
			array($data_query['host_id'], $data_query['snmp_query_id']));

		$orphans = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM graph_local
			WHERE host_id = ?
			AND snmp_query_id = ?
			AND snmp_index = ""',
			array($data_query['host_id'], $data_query['snmp_query_id']));

		$end = microtime(true);

		$message = sprintf(
			'Re-Index Complete: Number[%d], TotalTime[%4.2f], QueryTime[%3.2f], Device[%d], Description[%s], DQ[%d], Items[%d], Orphans[%d]',
			$i,
			$end - $total_start,
			$end - $start,
			$data_query['host_id'],
			$data_query['description'],
			$data_query['snmp_query_id'],
			$items,
			$orphans
		);

		debug($message);

		$i++;
	}

	set_config_option('reindex_last_run_time', time());
	unregister_process('reindex', 'master');

}

function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Reindex Host Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/*	display_help - displays the usage of the function */
function display_help() {
	display_version();
	print 'usage: poller_reindex_hosts.php --id=[host_id|all] [--qid=[ID|all]]' . PHP_EOL;
	print '   [--host-descr=[description]] [--debug]' . PHP_EOL . PHP_EOL;
	print '--id=host_id             - The host_id to have data queries reindexed or \'all\' to reindex all hosts\n' . PHP_EOL;
	print '--qid=query_id           - Only index on a specific data query id; defaults to \'all\'' . PHP_EOL;
	print '--host-descr=description - The host description to filter by (SQL filters acknowledged)' . PHP_EOL;
	print '--force                  - Force Graph and Data Source Suggested Name Re-mapping for all items' . PHP_EOL;
	print '--debug                  - Display verbose output during execution' . PHP_EOL;
}

function debug($message) {
	global $debug;

	if ($debug) {
		print 'DEBUG: ' . $message . PHP_EOL;
	}
}
