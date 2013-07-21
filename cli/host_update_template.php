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
include_once($config["base_path"] . "/lib/snmp.php");
include_once($config["base_path"] . "/lib/data_query.php");
include_once($config["base_path"] . "/lib/api_automation_tools.php");

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

/* utility requires input parameters */
if (sizeof($parms) == 0) {
	print "ERROR: You must supply input parameters\n\n";
	display_help();
	exit;
}

$debug    = FALSE;
$template = "";
$hostid   = "";

foreach($parms as $parameter) {
	@list($arg, $value) = @explode("=", $parameter);

	switch ($arg) {
	case "--host-template":
	case "--host-template-id":
		$template = $value;
		break;
	case "--host-id":
		$host_id = $value;
		break;
	case "--list-host-templates":
		displayHostTemplates(getHostTemplates());
		exit(0);
	case "-d":
	case "--debug":
		$debug = TRUE;
		break;
	case "-h":
	case "-v":
	case "--version":
	case "--help":
		display_help();
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
		exit;
	}
}

/* determine the hosts to reindex */
if (strtolower($host_id) == "all") {
	$sql_where = "";
}else if (is_numeric($host_id)) {
	$sql_where = " WHERE id='$host_id'";
}else{
	print "ERROR: You must specify either a host_id or 'all' to proceed.\n\n";
	display_help();
	exit;
}

/* determine data queries to rerun */
if (is_numeric($template)) {
	$sql_where .= (strlen($sql_where) ? " AND host_template_id=$template": "WHERE host_template_id=$template");
}else{
	print "ERROR: You must specify a Host Template to proceed.\n\n";
	display_help();
	exit;
}

/* verify that the host template is accurate */
if (db_fetch_cell("SELECT id FROM host_template WHERE id=$template") > 0) {
	$hosts = db_fetch_assoc("SELECT * FROM host $sql_where");

	if (sizeof($hosts)) {
	foreach($hosts as $host) {
		echo "NOTE: Updating Host '" . $host["description"] . "'\n";
		$snmp_queries = db_fetch_assoc("SELECT snmp_query_id
			FROM host_template_snmp_query
			WHERE host_template_id=" . $host["host_template_id"]);

		if (sizeof($snmp_queries) > 0) {
			echo "NOTE: Updating Data Queries. There were '" . sizeof($snmp_queries) . "' Found\n";
			foreach ($snmp_queries as $snmp_query) {
				echo "NOTE: Updating Data Query ID '" . $snmp_query["snmp_query_id"] . "'\n";
				db_execute("REPLACE INTO host_snmp_query (host_id,snmp_query_id,reindex_method)
					VALUES (" . $host["id"] . ", " . $snmp_query["snmp_query_id"] . "," . DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME . ")");

				/* recache snmp data */
				run_data_query($host["id"], $snmp_query["snmp_query_id"]);
			}
		}

		$graph_templates = db_fetch_assoc("SELECT graph_template_id FROM host_template_graph WHERE host_template_id=" . $host["host_template_id"]);

		if (sizeof($graph_templates) > 0) {
			echo "NOTE: Updating Graph Templates. There were '" . sizeof($graph_templates) . "' Found\n";

			foreach ($graph_templates as $graph_template) {
				db_execute("REPLACE INTO host_graph (host_id, graph_template_id) VALUES (" . $host["id"] . ", " . $graph_template["graph_template_id"] . ")");
				api_plugin_hook_function('add_graph_template_to_host', array("host_id" => $host["id"], "graph_template_id" => $graph_template["graph_template_id"]));
			}
		}
	}
	}
}else{
	echo "ERROR: The selected Host Template does not exist, try --list-host-templates\n\n";
	exit(1);
}


/*	display_help - displays the usage of the function */
function display_help () {
print "Cacti Retemplate Host Script 1.0, Copyright 2004-2013 - The Cacti Group\n";
	print "usage: host_update_template.php --host-id=[host-id|All] [--host-template=[ID]] [-d] [-h] [--help] [-v] [--version]\n\n";
	print "--host-id=host_id  - The host_id to have templates reapplied 'all' to do all hosts\n";
	print "--host-template=ID - Which Host Template to Refresh\n\n";
	print "Optional:\n";
	print "-d --debug    - Display verbose output during execution\n";
	print "-v --version  - Display this help message\n";
	print "-h --help     - Display this help message\n";
	print "List Options:\n\n";
	print "--list-host-templates - Lists all available Host Templates\n\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		print("DEBUG: " . $message . "\n");
	}
}

?>
