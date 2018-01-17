#!/usr/bin/php -q
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

ini_set('max_execution_time', '0');

$no_http_headers = true;

include(dirname(__FILE__) . '/../include/global.php');
include_once($config['base_path'] . '/lib/api_graph.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug   = false;
$host_id = '';
$filter  = '';

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
			case '--host-id':
				$host_id = $value;
				break;
			case '-s':
			case '--filter':
				$filter = $value;
				break;
			case '--debug':
			case '-d':
				$debug = true;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
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

/* form the 'where' clause for our main sql query */
if ($filter != '') {
	$sql_where = "AND (graph_templates_graph.title_cache LIKE '%" . $filter . "%'" .
		" OR graph_templates.name LIKE '%" . $filter . "%')";
} else {
	$sql_where = '';
}

if (strtolower($host_id) == 'all') {
	/* Act on all graphs */
} elseif (substr_count($host_id, ',')) {
	$hosts = explode(',', $host_id);
	$host_str = '';

	foreach($hosts as $host) {
		if (is_numeric($host) && $host > 0) {
			$host_str .= ($host_str != '' ? ', ':'') . $host;
		}
	}

	$sql_where .= " AND graph_local.host_id IN ($host_str)";
} elseif ($host_id == '0') {
	$sql_where .= ' AND graph_local.host_id=0';
} elseif (!empty($host_id) && $host_id > 0) {
	$sql_where .= ' AND graph_local.host_id=' . $host_id;
} else {
	print "ERROR: You must specify either a host_id or 'all' to proceed.\n";
	display_help();
	exit;
}

$graph_list = db_fetch_assoc("SELECT
	graph_templates_graph.id,
	graph_templates_graph.local_graph_id,
	graph_templates_graph.height,
	graph_templates_graph.width,
	graph_templates_graph.title_cache,
	graph_templates.name,
	graph_local.host_id
	FROM (graph_local,graph_templates_graph)
	LEFT JOIN graph_templates ON (graph_local.graph_template_id=graph_templates.id)
	WHERE graph_local.id=graph_templates_graph.local_graph_id
	$sql_where");

/* issue warnings and start message if applicable */
print "WARNING: Do not interrupt this script.  Interrupting during rename can cause issues\n";
debug("There are '" . sizeof($graph_list) . "' Graphs to rename");

$i = 1;
foreach ($graph_list as $graph) {
	if (!$debug) print ".";
	debug("Graph Name '" . $graph["title_cache"] . "' starting");
	api_reapply_suggested_graph_title($graph["local_graph_id"]);
	update_graph_title_cache($graph["local_graph_id"]);
	debug("Graph Rename Done for Graph '" . $graph["title_cache"] . "'");
	$i++;
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Reapply graph Names Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	echo "\nusage: poller_graphs_reapply_names.php --host-id=[id|all][N1,N2,...] [--filter=[string] [--debug]\n\n";
	echo "A utility to reapply Cacti Graph naming rules to existing Graphs in bulk.\n\n";
	echo "Required:\n";
	echo "    --host-id=id|all|N1,N2,... - The devices id, 'all' or a comma delimited list of id's\n\n";
	echo "Optional:\n";
	echo "    --filter=string            - A Graph Template name or Graph Title to search for\n";
	echo "    --debug                    - Display verbose output during execution\n\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		print("DEBUG: " . $message . "\n");
	}
}
