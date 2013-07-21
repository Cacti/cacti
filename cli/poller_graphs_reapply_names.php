<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

ini_set("max_execution_time", "0");

$no_http_headers = true;

include(dirname(__FILE__) . "/../include/global.php");
include_once($config["base_path"] . "/lib/api_graph.php");

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

/* utility requires input parameters */
if (sizeof($parms) == 0) {
	print "ERROR: You must supply input parameters\n\n";
	display_help();
	exit;
}

$debug = FALSE;
$host_id = "";
$filter = "";

foreach($parms as $parameter) {
	@list($arg, $value) = @explode("=", $parameter);

	switch ($arg) {
	case "-id":
		$host_id = $value;
		break;
	case "-s":
		$filter = $value;
		break;
	case "-d":
		$debug = TRUE;
		break;
	case "-h":
		display_help();
		exit;
	case "-v":
		display_help();
		exit;
	case "--version":
		display_help();
		exit;
	case "--help":
		display_help();
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
		exit;
	}
}

/* form the 'where' clause for our main sql query */
if (strlen($filter)) {
	$sql_where = "AND (graph_templates_graph.title_cache like '%%" . $filter . "%%'" .
		" OR graph_templates.name like '%%" . $filter . "%%')";
}else{
	$sql_where = "";
}

if ($host_id == "All") {
	/* Act on all graphs */
}elseif (substr_count($host_id, "|")) {
	$hosts = explode("|", $host_id);
	$host_str = "";

	foreach($hosts as $host) {
		if (strlen($host_str)) {
			$host_str .= ", '" . $host . "'";
		}else{
			$host_str .= "'" . $host . "'";
		}
	}

	$sql_where .= " AND graph_local.host_id IN ($host_str)";
}elseif ($host_id == "0") {
	$sql_where .= " AND graph_local.host_id=0";
}elseif (!empty($host_id)) {
	$sql_where .= " AND graph_local.host_id=" . $host_id;
}else{
	print "ERROR: You must specify either a host_id or 'All' to proceed.\n";
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

/*	display_help - displays the usage of the function */
function display_help () {
	print "Cacti Reapply Graph Names Script 1.0, Copyright 2004-2013 - The Cacti Group\n\n";
	print "usage: poller_graphs_reapply_names.php -id=[host_id|All][host_id1|host_id2|...] [-s=[search_string] [-d] [-h] [--help] [-v] [--version]\n\n";
	print "-id=host_id   - The host_id or 'All' or a pipe delimited list of host_id's\n";
	print "-s=search_str - A graph template name or graph title to search for\n";
	print "-d            - Display verbose output during execution\n";
	print "-v --version  - Display this help message\n";
	print "-h --help     - Display this help message\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		print("DEBUG: " . $message . "\n");
	}
}

?>
