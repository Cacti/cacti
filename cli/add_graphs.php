#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2007 The Cacti Group                                 |
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

$no_http_headers = true;

include(dirname(__FILE__)."/../include/global.php");
include_once($config["base_path"]."/lib/api_automation_tools.php");
include_once($config["base_path"]."/lib/data_query.php");
include_once($config["base_path"]."/lib/utility.php");
include_once($config["base_path"]."/lib/sort.php");
include_once($config["base_path"]."/lib/template.php");
include_once($config["base_path"]."/lib/api_data_source.php");
include_once($config["base_path"]."/lib/api_graph.php");
include_once($config["base_path"]."/lib/snmp.php");
include_once($config["base_path"]."/lib/data_query.php");
include_once($config["base_path"]."/lib/api_device.php");

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

if (sizeof($parms)) {
	/* setup defaults */
	$graph_type    = "";
	$templateGraph = array();
	$dsGraph       = array();

	$hosts          = getHosts();
	$graphTemplates = getGraphTemplates();

	$graphTitle = "";

	$hostId     = 0;
	$templateId = 0;
	$force      = 0;

	$listHosts       = 0;
	$listSNMPFields  = 0;
	$listSNMPValues  = 0;
	$listQueryTypes  = 0;
	$listSNMPQueries = 0;

	foreach($parms as $parameter) {
		@list($arg, $value) = @explode("=", $parameter);

		switch($arg) {
		case "--graph-type":
			$i++;
			$graph_type = $value;

			break;
		case "--graph-title":
			$i++;
			$graphTitle = $value;

			break;
		case "--graph-template-id":
			$i++;
			$templateId = $value;

			break;
		case "--host-id":
			$i++;
			$hostId = $value;

			break;
		case "--snmp-query-id":
			$i++;
			$dsGraph["snmpQueryId"] = $value;

			break;
		case "--snmp-query-type-id":
			$i++;
			$dsGraph["snmpQueryType"] = $value;

			break;
		case "--snmp-field":
			$i++;
			$dsGraph["snmpField"] = $value;

			break;
		case "--snmp-value":
			$i++;
			$dsGraph["snmpValue"] = $value;

			break;
		case "--list-hosts":
			$listHosts = 1;

			break;
		case "--list-snmp-fields":
			$listSNMPFields = 1;

			break;
		case "--list-snmp-values":
			$listSNMPValues = 1;

			break;
		case "--list-query-types":
			$listQueryTypes = 1;

			break;
		case "--list-snmp-queries":
			$listSNMPQueries = 1;

			break;
		case "--force":
			$force = 1;

			break;
		case "--list-graph-templates":
			displayGraphTemplates($graphTemplates);

			return 0;
		default:
			display_help();

			return 0;
		}
	}

	if ($listHosts) {
		displayHosts($hosts);

		return 0;
	}

	/* get the existing snmp queries */
	$snmpQueries = getSNMPQueries();

	if ($listSNMPQueries == 1) {
		displaySNMPQueries($snmpQueries);

		return 0;
	}

	/* Some sanity checking... */
	if (isset($dsGraph["snmpQueryId"])) {
		if (!isset($snmpQueries[$dsGraph["snmpQueryId"]])) {
			echo "Unknown snmp-query-id (" . $dsGraph["snmpQueryId"] . ")\n";
			echo "Try --list-snmp-queries\n";

			return 1;
		}

		/* get the snmp query types for comparison */
		$snmp_query_types = getSNMPQueryTypes($dsGraph["snmpQueryId"]);

		if ($listQueryTypes == 1) {
			displayQueryTypes($snmp_query_types);

			return 0;
		}

		if (isset($dsGraph["snmpQueryType"])) {
			if (!isset($snmp_query_types[$dsGraph["snmpQueryType"]])) {
				echo "Unknown snmp-query-type-id (" . $dsGraph["snmpQueryType"] . ")\n";
				echo "Try --snmp-query-id " . $dsGraph["snmpQueryId"] . " --list-query-types\n";

				return 1;
			}
		}
	}


	/* Verify the host's existance */
	if (!isset($hosts[$hostId]) || $hostId == 0) {
		echo "Unknown Host ID ($hostId)\n";
		echo "Try --list-hosts\n";

		return 1;
	}

	/* process the snmp fields */
	$snmpFields = getSNMPFields($hostId);

	if ($listSNMPFields == 1) {
		displaySNMPFields($snmpFields, $hostId);

		return 0;
	}

	$snmpValues = array();

	/* More sanity checking */
	if (isset($dsGraph["snmpField"])) {
		if (!isset($snmpFields[$dsGraph["snmpField"]])) {
			echo "Unknwon snmp-field " . $dsGraph["snmpField"] . " for host $hostId\n";
			echo "Try --list-snmp-fields\n";

			return 1;
		}

		$snmpValues = getSNMPValues($hostId, $dsGraph["snmpField"]);

		if (isset($dsGraph["snmpValue"])) {
			if(!isset($snmpValues[$dsGraph["snmpValue"]])) {
				echo "Unknown snmp-value for field " . $dsGraph["snmpField"] . " - " . $dsGraph["snmpValue"] . "\n";
				echo "Try --snmp-field " . $dsGraph["snmpField"] . " --list-snmp-values\n";

				return 1;
			}
		}
	}

	if ($listSNMPValues == 1)  {
		if (!isset($dsGraph["snmpField"])) {
			echo "You must supply an snmp-field before you can list its values\n";
			echo "Try --list-snmp-fields\n";

			return 1;
		}

		displaySNMPValues($snmpValues, $hostId, $dsGraph["snmpField"]);

		return 0;
	}

	if (!isset($graphTemplates[$templateId])) {
		echo "Unknown graph-template-id (" . $templateId . ")\n";
		echo "Try --list-graph-templates\n";

		return 1;
	}

	if ((!isset($templateId)) || (!isset($hostId))) {
		echo "Must have at least a host-id and a graph-template-id\n\n";

		display_help();

		return 1;
	}

	$returnArray = array();

	if ($graph_type == "cg") {
		$empty = array(); /* Suggested Values are not been implemented */

		$existsAlready = db_fetch_cell("SELECT id FROM graph_local WHERE graph_template_id=$templateId AND host_id=$hostId");
		$dataSourceId = db_fetch_cell("SELECT DISTINCT
			data_template_rrd.local_data_id
			FROM graph_templates_item, data_template_rrd
			WHERE graph_templates_item.local_graph_id = " . $existsAlready . "
			AND graph_templates_item.task_item_id = data_template_rrd.id");

		if ((isset($existsAlready)) &&
			($existsAlready > 0) &&
			(!$force)) {
			echo "Not Adding Graph - this graph already exists - graph-id: ($existsAlready) - data-source-id: ($dataSourceId)\n";

			return 1;
		}else{
			$returnArray = create_complete_graph_from_template($templateId, $hostId, "", $empty);
		}
	}elseif ($graph_type == "ds") {
		if ((!isset($dsGraph["snmpQueryId"])) || (!isset($dsGraph["snmpQueryType"])) || (!isset($dsGraph["snmpField"])) || (!isset($dsGraph["snmpValue"]))) {
			echo "For graph-type of 'ds' you must supply more options\n";

			display_help();

			return 1;
		}

		$snmp_query_array = array();
		$snmp_query_array["snmp_query_id"]       = $dsGraph["snmpQueryId"];
		$snmp_query_array["snmp_index_on"]       = $dsGraph["snmpField"];
		$snmp_query_array["snmp_query_graph_id"] = $dsGraph["snmpQueryType"];

		$snmp_query_array["snmp_index"] = db_fetch_cell("select snmp_index from host_snmp_cache WHERE host_id=" . $hostId . " and snmp_query_id=" . $dsGraph["snmpQueryId"] . " AND field_name='" . $dsGraph["snmpField"] . "' AND field_value='" . $dsGraph["snmpValue"] . "'");

		if (!isset($snmp_query_array["snmp_index"])) {
			echo "Could not find snmp-field " . $dsGraph["snmpField"] . " (" . $dsGraph["snmpValue"] . ") for host-id " . $hostId . " (" . $hosts[$hostId]["hostname"] . ")\n";
			echo "Try --host-id " . $hostId . " --list-snmp-fields\n";

			return 1;
		}

		$existsAlready = db_fetch_cell("SELECT id FROM graph_local WHERE graph_template_id=$templateId AND host_id=$hostId AND snmp_query_id=" . $dsGraph["snmpQueryId"] . " AND snmp_index=" . $snmp_query_array["snmp_index"]);

		if (isset($existsAlready) && $existsAlready > 0) {
			if ($graphTitle != "") {
				db_execute("update graph_templates_graph set title = \"$graphTitle\" where local_graph_id = $existsAlready");
				update_graph_title_cache($existsAlready);
			}

			$dataSourceId = db_fetch_cell("SELECT DISTINCT
				data_template_rrd.local_data_id
				FROM graph_templates_item, data_template_rrd
				WHERE graph_templates_item.local_graph_id = " . $existsAlready . "
				AND graph_templates_item.task_item_id = data_template_rrd.id");

			echo "Not Adding Graph - this graph already exists - graph-id: ($existsAlready) - data-source-id: ($dataSourceId)\n";

			return 1;
		}

		$empty = array(); /* Suggested Values are not been implemented */
		$returnArray = create_complete_graph_from_template($templateId, $hostId, $snmp_query_array, $empty);
	}else{
		echo "Graph Types must be either 'cg' or 'ds'\n";

		return 1;
	}

	if ($graphTitle != "") {
		db_execute("UPDATE graph_templates_graph
			SET title=\"$graphTitle\"
			WHERE local_graph_id=" . $returnArray["local_graph_id"]);

		update_graph_title_cache($returnArray["local_graph_id"]);
	}

	push_out_host($hostId,0);

	$dataSourceId = db_fetch_cell("SELECT DISTINCT
		data_template_rrd.local_data_id
		FROM graph_templates_item, data_template_rrd
		WHERE graph_templates_item.local_graph_id = " . $returnArray["local_graph_id"] . "
		AND graph_templates_item.task_item_id = data_template_rrd.id");

	echo "Graph Added - graph-id: (" . $returnArray["local_graph_id"] . ") - data-source-id: ($dataSourceId)\n";

	return 0;
}else{
	display_help();

	return 1;
}

function display_help() {
	echo "Usage:\n";
	echo "add_graphs.php --graph-type=[cg|ds] --graph-template-id=[ID]\n";
	echo "  --host-id=[ID] [--graph-title=title] [graph options] [--force]\n\n";
	echo "For cg graphs: [--force]\n\n";
	echo "--force is optional - if you set this flag, then new cg graphs will be created, even though they may already exist.\n\n";
	echo "For ds graphs: --snmp-query-id=[ID] --snmp-query-type-id=[ID] --snmp-field=[SNMP Field] --snmp-value=[SNMP Value]\n\n";
	echo "--graph-title is optional - it defaults to what ever is in the graph template/data-source template.\n\n";
	echo "List Options:  --list-hosts\n";
	echo "               --list-graph-templates\n";
	echo "               --list-snmp-queries\n";
	echo "               --snmp-query-id [ID] --list-query-types\n";
	echo "               --host-id=[ID] --list-snmp-fields\n";
	echo "               --host-id=[ID] --snmp-field=[Field] --list-snmp-values\n\n";
	echo "'cg' graphs are for things like CPU temp/fan speed, while 'ds' graphs are for data-source based graphs (interface stats etc.)\n";
}

?>
