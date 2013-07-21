#!/usr/bin/php -q
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

/* We are not talking to the browser */
$no_http_headers = true;

include(dirname(__FILE__)."/../include/global.php");
include_once($config["base_path"]."/lib/api_automation_tools.php");

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

if (sizeof($parms)) {
	$displayHosts 			= FALSE;
	$displayGraphTemplates 	= FALSE;
	$quietMode				= FALSE;
	unset($host_id);
	unset($graph_template_id);

	foreach($parms as $parameter) {
		@list($arg, $value) = @explode("=", $parameter);

		switch ($arg) {
		case "-d":
			$debug = TRUE;

			break;
		case "--host-id":
			$host_id = trim($value);
			if (!is_numeric($host_id)) {
				echo "ERROR: You must supply a valid host-id to run this script!\n";
				exit(1);
			}

			break;
		case "--graph-template-id":
			$graph_template_id = $value;
			if (!is_numeric($graph_template_id)) {
				echo "ERROR: You must supply a numeric graph-template-id for all hosts!\n";
				exit(1);
			}

			break;
		case "--version":
		case "-V":
		case "-H":
		case "--help":
			display_help();
			exit(0);
		case "--list-hosts":
			$displayHosts = TRUE;
			break;
		case "--list-graph-templates":
			$displayGraphTemplates = TRUE;
			break;
		case "--quiet":
			$quietMode = TRUE;
			break;
		default:
			echo "ERROR: Invalid Argument: ($arg)\n\n";
			display_help();
			exit(1);
		}
	}

	/* list options, recognizing $quiteMode */
	if ($displayHosts) {
		$hosts = getHosts();
		displayHosts($hosts, $quietMode);
		exit(0);
	}

	if ($displayGraphTemplates) {
		$graphTemplates = getGraphTemplates();
		displayGraphTemplates($graphTemplates, $quietMode);
		exit(0);
	}

	/*
	 * verify required parameters
	 * for update / insert options
	 */
	if (!isset($host_id)) {
		echo "ERROR: You must supply a valid host-id for all hosts!\n";
		exit(1);
	}

	if (!isset($graph_template_id)) {
		echo "ERROR: You must supply a valid data-query-id for all hosts!\n";
		exit(1);
	}

	/*
	 * verify valid host id and get a name for it
	 */
	$host_name = db_fetch_cell("SELECT hostname FROM host WHERE id = " . $host_id);
	if (!isset($host_name)) {
		echo "ERROR: Unknown Host Id ($host_id)\n";
		exit(1);
	}

	/*
	 * verify valid graph template and get a name for it
	 */
	$graph_template_name = db_fetch_cell("SELECT name FROM graph_templates WHERE id = " . $graph_template_id);
	if (!isset($graph_template_name)) {
		echo "ERROR: Unknown Graph Template Id ($graph_template_id)\n";
		exit(1);
	}

	/* check, if graph template was already associated */
	$exists_already = db_fetch_cell("SELECT host_id FROM host_graph WHERE graph_template_id=$graph_template_id AND host_id=$host_id");
	if ((isset($exists_already)) &&
		($exists_already > 0)) {
		echo "ERROR: Graph Template is already associated for host: ($host_id: $host_name) - graph-template: ($graph_template_id: $graph_template_name)\n";
		exit(1);
	}else{
		db_execute("replace into host_graph (host_id,graph_template_id) values (" . $host_id . "," . $graph_template_id . ")");
		api_plugin_hook_function('add_graph_template_to_host', array("host_id" => $host_id, "graph_template_id" => $graph_template_id));
	}

	if (is_error_message()) {
		echo "ERROR: Failed to add this graph template for host: ($host_id: $host_name) - graph-template: ($graph_template_id: $graph_template_name)\n";
		exit(1);
	} else {
		echo "Success: Graph Template associated for host: ($host_id: $host_name) - graph-template: ($graph_template_id: $graph_template_name)\n";
		exit(0);
	}
}else{
	display_help();
	exit(0);
}

function display_help() {
	echo "Add Graph Template Script 1.0, Copyright 2008 - The Cacti Group\n\n";
	echo "A simple command line utility to associate a graph template with a host in Cacti\n\n";
	echo "usage: add_graph_template.php --host-id=[ID] --graph-template-id=[ID]\n";
	echo "    [--quiet]\n\n";
	echo "Required:\n";
	echo "    --host-id             the numerical ID of the host\n";
	echo "    --graph-template-id   the numerical ID of the graph template to be added\n\n";
	echo "List Options:\n";
	echo "    --list-hosts\n";
	echo "    --list-graph-templates\n";
	echo "    --quiet - batch mode value return\n\n";
}

?>
