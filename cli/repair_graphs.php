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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* Original script is located here https://forums.cacti.net/viewtopic.php?t=35816, but this one was modified quite a lot */

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

$no_http_headers = true;

include(dirname(__FILE__) . "/../include/global.php");
include_once("../lib/utility.php");
include_once("../lib/template.php");

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

$execute = FALSE;
$show_sql = FALSE;
unset($host_id);
unset($graph_template_id);
unset($data_template_id);

foreach($parms as $parameter) {
	@list($arg, $value) = @explode("=", $parameter);

	switch ($arg) {
		case "--execute":
			$execute = TRUE;
			break;
		case "--show-sql":
			$show_sql = TRUE;
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
				echo "ERROR: You must supply a numeric graph-template-id!\n";
				exit(1);
			}
			break;
		case "--data-template-id":
			$data_template_id = $value;
			if (!is_numeric($data_template_id)) {
				echo "ERROR: You must supply a numeric data-template-id!\n";
				exit(1);
			}
			break;
		case "-h":
		case "-v":
		case "-V":
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

if (!$show_sql && !$execute) {
	display_help();
	exit;
}

if (!isset($data_template_id)) {
	echo "ERROR: You must supply a valid data-template-id!\n";
	exit(1);
}

if (!isset($graph_template_id)) {
	echo "ERROR: You must supply a valid graph-template-id!\n";
	exit(1);
}

if ($execute) {
	echo "NOTE: Repairing Graphs\n";
} else {
	echo "NOTE: Performing Check of Graphs\n";
}

// Get all graphs for supplied graph template
$graph = db_fetch_assoc("SELECT * FROM graph_local where " . (!isset($host_id) ? '' : "host_id=".$host_id." and ") . " graph_template_id=" . $graph_template_id . "");

if (sizeof($graph)) {
	if(!$show_sql) {
		echo "\nCorrupted graphs:\n";
	}

	foreach($graph as $g) {
		// Get datasource for supplied data template for current host
		$ds = db_fetch_assoc("SELECT * FROM data_local where host_id=" . $g["host_id"] . " and data_template_id=" . $data_template_id);
		if (!sizeof($ds)) {
			continue;
		}
		$ds = $ds[0];

		// Get rrd for found datasource
		$rrd_data = db_fetch_assoc("SELECT * FROM data_template_rrd where local_data_id=" . $ds["id"]);
		if (!sizeof($rrd_data)) {
			echo "Could not get correct rrd id for datasource=" . $ds["id"] . "\n";
			continue;
		}

		/*
		// Here we will find graph items that should point to our data template
		// Get templated rrd id for given data template
		select id from data_template_rrd where local_data_template_rrd_id=0 and local_data_id=0 and data_template_id=520
		// Get templated graph->rrd association
		select id from graph_templates_item where local_graph_template_item_id=0 and local_graph_id=0 and task_item_id=
		// Get graph associations which corresponds to supplied data template
		select id from graph_templates_item where local_graph_id=$g["id"] and local_graph_template_item_id in
		// But I'm too lazy to write such a lot of code, so let's better make one long query below
		*/

		$graph_templates_items_wrong = db_fetch_assoc("select id, task_item_id from graph_templates_item WHERE task_item_id!=" . $rrd_data[0]["id"] . " and graph_template_id=" . $graph_template_id . " and local_graph_id=" . $g["id"] . " and local_graph_template_item_id in (select id from graph_templates_item where local_graph_template_item_id=0 and local_graph_id=0 and task_item_id=(select id from data_template_rrd where local_data_template_rrd_id=0 and local_data_id=0 and data_template_id=" . $data_template_id . "))");
		if (!sizeof($graph_templates_items_wrong)) {
			// Everything correct here.
			continue;
		} else {
			foreach($graph_templates_items_wrong as $graph_templates_item_wrong) {
				// Here is a list of graph_templates_item ids to be fixed and their wrong task_item_id
				$graph_templates_item[] = $graph_templates_item_wrong["id"];
				$task_item_id[] = $graph_templates_item_wrong["task_item_id"];
			}
		}

		echo "Host " . $g["host_id"] . ", graph " . $g["id"] . ", graph item " . implode(",",$graph_templates_item) . ", task_item_id " . implode(",",$task_item_id) . "->" . $rrd_data[0]["id"] . "\n";

		$query = "UPDATE graph_templates_item SET task_item_id=" . $rrd_data[0]["id"] . " WHERE task_item_id!=" . $rrd_data[0]["id"] . " and graph_template_id=" . $graph_template_id . " and local_graph_id=" . $g["id"] . " and id in (" . implode(",",$graph_templates_item) . ")";

		if ($show_sql) {
			echo $query . ";\n";
		}
		if ($execute) {
			db_execute($query);
		}
		unset($graph_templates_item);
		unset($task_item_id);
	}
}

/* display_help - displays the usage of the function */
function display_help () {
	echo "Cacti Graph Repair Tool v0.4\n\n";
	echo "usage: repair_graphs.php [--help] [--host-id=ID] --data-template-id=[ID]\n";
	echo "	--graph-template-id=[ID] [--show-sql] [--execute]\n\n";
	echo "Cacti utility for repairing graph<->datasource relationship via a command line interface.\n\n";
	echo "--execute - Perform the repair\n";
	echo "--show-sql - Show SQL lines for the repair (optional)\n";
	echo "--host-id=id - The host_id to repair or leave empty to process all hosts\n";
	echo "--data-template-id=id - The numerical ID of the data template to be fixed\n";
	echo "--graph-template-id=id - The numerical ID of the graph template to be fixed\n";
}

?>
