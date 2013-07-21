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
	$dsGraph["snmpFieldSpec"]  = "";
	$dsGraph["snmpQueryId"]    = "";
	$dsGraph["snmpQueryType"]  = "";
	$dsGraph["snmpField"]      = "";
	$dsGraph["snmpValue"]      = "";
	$dsGraph["reindex_method"] = DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME;

	$input_fields  = array();
	$values["cg"]  = array();

	$hosts          = getHosts();
	$graphTemplates = getGraphTemplates();

	$graphTitle = "";
	$cgInputFields = "";

	$hostId     	= 0;
	$templateId 	= 0;
	$hostTemplateId = 0;
	$force      	= 0;

	$listHosts       		= FALSE;
	$listGraphTemplates 	= FALSE;
	$listSNMPFields  		= FALSE;
	$listSNMPValues  		= FALSE;
	$listQueryTypes  		= FALSE;
	$listSNMPQueries 		= FALSE;
	$listInputFields 		= FALSE;

	$quietMode       = FALSE;

	foreach($parms as $parameter) {
		@list($arg, $value) = @explode("=", $parameter, 2);

		switch($arg) {
		case "--graph-type":
			$graph_type = $value;

			break;
		case "--graph-title":
			$graphTitle = $value;

			break;
		case "--graph-template-id":
			$templateId = $value;

			break;
		case "--host-template-id":
			$hostTemplateId = $value;

			break;
		case "--host-id":
			$hostId = $value;

			break;
		case "--input-fields":
			$cgInputFields = $value;

			break;
		case "--snmp-query-id":
			$dsGraph["snmpQueryId"] = $value;

			break;
		case "--snmp-query-type-id":
			$dsGraph["snmpQueryType"] = $value;

			break;
		case "--snmp-field":
			$dsGraph["snmpField"] = $value;

			break;
		case "--snmp-value":
			$dsGraph["snmpValue"] = $value;

			break;
		case "--reindex-method":
			if (is_numeric($value) &&
				($value >= DATA_QUERY_AUTOINDEX_NONE) &&
				($value <= DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION)) {
				$dsGraph["reindex_method"] = $value;
			} else {
				switch (strtolower($value)) {
					case "none":
						$dsGraph["reindex_method"] = DATA_QUERY_AUTOINDEX_NONE;
						break;
					case "uptime":
						$dsGraph["reindex_method"] = DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME;
						break;
					case "index":
						$dsGraph["reindex_method"] = DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE;
						break;
					case "fields":
						$dsGraph["reindex_method"] = DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION;
						break;
					default:
						echo "ERROR: You must supply a valid reindex method for this graph!\n";
						exit(1);
				}
			}

			break;
		case "--list-hosts":
			$listHosts = TRUE;

			break;
		case "--list-snmp-fields":
			$listSNMPFields = TRUE;

			break;
		case "--list-snmp-values":
			$listSNMPValues = TRUE;

			break;
		case "--list-query-types":
			$listQueryTypes = TRUE;

			break;
		case "--list-snmp-queries":
			$listSNMPQueries = TRUE;

			break;
		case "--force":
			$force = TRUE;

			break;
		case "--quiet":
			$quietMode = TRUE;

			break;
		case "--list-input-fields":
			$listInputFields = TRUE;

			break;
		case "--list-graph-templates":
			$listGraphTemplates = TRUE;

			break;
		case "--version":
		case "-V":
		case "-H":
		case "--help":
			display_help();
			exit(0);
		default:
			echo "ERROR: Invalid Argument: ($arg)\n\n";
			display_help();
			exit(1);
		}
	}

	if ($listGraphTemplates) {
		/* is a Host Template Id is given, print the related Graph Templates */
		if ($hostTemplateId > 0) {
			$graphTemplates = getGraphTemplatesByHostTemplate($hostTemplateId);
			if (!sizeof($graphTemplates)) {
				echo "ERROR: You must supply a valid --host-template-id before you can list its graph templates\n";
				echo "Try --list-graph-template-id --host-template-id=[ID]\n";
				exit(1);
			}
		}

		displayGraphTemplates($graphTemplates, $quietMode);

		exit(0);
	}


	if ($listInputFields) {
		if ($templateId > 0) {
			$input_fields = getInputFields($templateId, $quietMode);
			displayInputFields($input_fields, $quietMode);
		} else {
			echo "ERROR: You must supply an graph-template-id before you can list its input fields\n";
			echo "Try --graph-template-id=[ID] --list-input-fields\n";
			exit(1);
		}

		exit(0);
	}

	if ($listHosts) {
		displayHosts($hosts, $quietMode);
		exit(0);
	}

	/* get the existing snmp queries */
	$snmpQueries = getSNMPQueries();

	if ($listSNMPQueries) {
		displaySNMPQueries($snmpQueries, $quietMode);
		exit(0);
	}

	/* Some sanity checking... */
	if ($dsGraph["snmpQueryId"] != "") {
		if (!isset($snmpQueries[$dsGraph["snmpQueryId"]])) {
			echo "ERROR: Unknown snmp-query-id (" . $dsGraph["snmpQueryId"] . ")\n";
			echo "Try --list-snmp-queries\n";
			exit(1);
		}

		/* get the snmp query types for comparison */
		$snmp_query_types = getSNMPQueryTypes($dsGraph["snmpQueryId"]);

		if ($listQueryTypes) {
			displayQueryTypes($snmp_query_types, $quietMode);
			exit(0);
		}

		if ($dsGraph["snmpQueryType"] != "") {
			if (!isset($snmp_query_types[$dsGraph["snmpQueryType"]])) {
				echo "ERROR: Unknown snmp-query-type-id (" . $dsGraph["snmpQueryType"] . ")\n";
				echo "Try --snmp-query-id=" . $dsGraph["snmpQueryId"] . " --list-query-types\n";
				exit(1);
			}
		}

		if (!($listHosts ||			# you really want to create a new graph
			$listSNMPFields || 		# add this check to avoid reindexing on any list option
			$listSNMPValues ||
			$listQueryTypes ||
			$listSNMPQueries ||
			$listInputFields)) {

			/* if data query is not yet associated,
			 * add it and run it once to get the cache filled */

			/* is this data query already associated (independent of the reindex method)? */
			$exists_already = db_fetch_cell("SELECT COUNT(host_id) FROM host_snmp_query WHERE host_id=$hostId AND snmp_query_id=" . $dsGraph["snmpQueryId"]);
			if ((isset($exists_already)) &&
				($exists_already > 0)) {
				/* yes: do nothing, everything's fine */
			}else{
				db_execute("REPLACE INTO host_snmp_query (host_id,snmp_query_id,reindex_method) " .
						   "VALUES (". $hostId . ","
									 . $dsGraph["snmpQueryId"] . ","
									 . $dsGraph["reindex_method"] .
									")");
				/* recache snmp data, this is time consuming,
				 * but should happen only once even if multiple graphs
				 * are added for the same data query
				 * because we checked above, if dq was already associated */
				run_data_query($hostId, $dsGraph["snmpQueryId"]);
			}
		}
	}

	/* Verify the host's existance */
	if (!isset($hosts[$hostId]) || $hostId == 0) {
		echo "ERROR: Unknown Host ID ($hostId)\n";
		echo "Try --list-hosts\n";
		exit(1);
	}

	/* process the snmp fields */
	$snmpFields = getSNMPFields($hostId, $dsGraph["snmpQueryId"]);

	if ($listSNMPFields) {
		displaySNMPFields($snmpFields, $hostId, $quietMode);
		exit(0);
	}

	$snmpValues = array();

	/* More sanity checking */
	if ($dsGraph["snmpField"] != "") {
		if (!isset($snmpFields[$dsGraph["snmpField"]])) {
			echo "ERROR: Unknown snmp-field " . $dsGraph["snmpField"] . " for host $hostId\n";
			echo "Try --list-snmp-fields\n";
			exit(1);
		}

		$snmpValues = getSNMPValues($hostId, $dsGraph["snmpField"], $dsGraph["snmpQueryId"]);

		if ($dsGraph["snmpValue"] != "") {
			if(!isset($snmpValues[$dsGraph["snmpValue"]])) {
				echo "ERROR: Unknown snmp-value for field " . $dsGraph["snmpField"] . " - " . $dsGraph["snmpValue"] . "\n";
				echo "Try --snmp-field=" . $dsGraph["snmpField"] . " --list-snmp-values\n";
				exit(1);
			}
		}
	}

	if ($listSNMPValues)  {
		if ($dsGraph["snmpField"] == "") {
			echo "ERROR: You must supply an snmp-field before you can list its values\n";
			echo "Try --list-snmp-fields\n";
			exit(1);
		}

		displaySNMPValues($snmpValues, $hostId, $dsGraph["snmpField"], $quietMode);
		exit(0);
	}

	if (!isset($graphTemplates[$templateId])) {
		echo "ERROR: Unknown graph-template-id (" . $templateId . ")\n";
		echo "Try --list-graph-templates\n";
		exit(1);
	}

	if ((!isset($templateId)) || (!isset($hostId))) {
		echo "ERROR: Must have at least a host-id and a graph-template-id\n\n";
		display_help();
		exit(1);
	}

	if (strlen($cgInputFields)) {
		$fields = explode(" ", $cgInputFields);
		if ($templateId > 0) {
			$input_fields = getInputFields($templateId, $quietMode);
		}

		if (sizeof($fields)) {
			foreach ($fields as $option) {
				$data_template_id = 0;
				$option_value = explode("=", $option);

				if (substr_count($option_value[0], ":")) {
					$compound = explode(":", $option_value[0]);
					$data_template_id = $compound[0];
					$field_name       = $compound[1];
				}else{
					$field_name       = $option_value[0];
				}

				/* check for the input fields existance */
				$field_found = FALSE;
				if (sizeof($input_fields)) {
					foreach ($input_fields as $key => $row) {
						if (substr_count($key, $field_name)) {
							if ($data_template_id == 0) {
								$data_template_id = $row["data_template_id"];
							}

							$field_found = TRUE;

							break;
						}
					}
				}

				if (!$field_found) {
					echo "ERROR: Unknown input-field (" . $field_name . ")\n";
					echo "Try --list-input-fields\n";
					exit(1);
				}

				$value = $option_value[1];

				$values["cg"][$templateId]["custom_data"][$data_template_id][$input_fields[$data_template_id . ":" . $field_name]["data_input_field_id"]] = $value;
			}
		}
	}

	$returnArray = array();

	if ($graph_type == "cg") {
		$existsAlready = db_fetch_cell("SELECT id FROM graph_local WHERE graph_template_id=$templateId AND host_id=$hostId");

		if ((isset($existsAlready)) &&
			($existsAlready > 0) &&
			(!$force)) {
			$dataSourceId  = db_fetch_cell("SELECT
				data_template_rrd.local_data_id
				FROM graph_templates_item, data_template_rrd
				WHERE graph_templates_item.local_graph_id = " . $existsAlready . "
				AND graph_templates_item.task_item_id = data_template_rrd.id
				LIMIT 1");

			echo "NOTE: Not Adding Graph - this graph already exists - graph-id: ($existsAlready) - data-source-id: ($dataSourceId)\n";
			exit(1);
		}else{
			$returnArray = create_complete_graph_from_template($templateId, $hostId, "", $values["cg"]);
			$dataSourceId = "";
		}

		if ($graphTitle != "") {
			db_execute("UPDATE graph_templates_graph
				SET title=\"$graphTitle\"
				WHERE local_graph_id=" . $returnArray["local_graph_id"]);

			update_graph_title_cache($returnArray["local_graph_id"]);
		}

		foreach($returnArray["local_data_id"] as $item) {
			push_out_host($hostId, $item);

			if (strlen($dataSourceId)) {
				$dataSourceId .= ", " . $item;
			}else{
				$dataSourceId = $item;
			}
		}

		/* add this graph template to the list of associated graph templates for this host */
		db_execute("replace into host_graph (host_id,graph_template_id) values (" . $hostId . "," . $templateId . ")");

		echo "Graph Added - graph-id: (" . $returnArray["local_graph_id"] . ") - data-source-ids: ($dataSourceId)\n";
	}elseif ($graph_type == "ds") {
		if (($dsGraph["snmpQueryId"] == "") || ($dsGraph["snmpQueryType"] == "") || ($dsGraph["snmpField"] == "") || ($dsGraph["snmpValue"] == "")) {
			echo "ERROR: For graph-type of 'ds' you must supply more options\n";
			display_help();
			exit(1);
		}

		$snmp_query_array = array();
		$snmp_query_array["snmp_query_id"]       = $dsGraph["snmpQueryId"];
		$snmp_query_array["snmp_index_on"]       = get_best_data_query_index_type($hostId, $dsGraph["snmpQueryId"]);
		$snmp_query_array["snmp_query_graph_id"] = $dsGraph["snmpQueryType"];

		$snmp_indexes = db_fetch_assoc("SELECT snmp_index
			FROM host_snmp_cache
			WHERE host_id=" . $hostId . "
			AND snmp_query_id=" . $dsGraph["snmpQueryId"] . "
			AND field_name='" . $dsGraph["snmpField"] . "'
			AND field_value='" . $dsGraph["snmpValue"] . "'");

		if (sizeof($snmp_indexes)) {
			foreach ($snmp_indexes as $snmp_index) {
				$snmp_query_array["snmp_index"] = $snmp_index["snmp_index"];

				$existsAlready = db_fetch_cell("SELECT id
					FROM graph_local
					WHERE graph_template_id=$templateId
					AND host_id=$hostId
					AND snmp_query_id=" . $dsGraph["snmpQueryId"] . "
					AND snmp_index='" . $snmp_query_array["snmp_index"] . "'");

				if (isset($existsAlready) && $existsAlready > 0) {
					if ($graphTitle != "") {
						db_execute("UPDATE graph_templates_graph
							SET title = \"$graphTitle\"
							WHERE local_graph_id = $existsAlready");

						update_graph_title_cache($existsAlready);
					}

					$dataSourceId = db_fetch_cell("SELECT
						data_template_rrd.local_data_id
						FROM graph_templates_item, data_template_rrd
						WHERE graph_templates_item.local_graph_id = " . $existsAlready . "
						AND graph_templates_item.task_item_id = data_template_rrd.id
						LIMIT 1");

					echo "NOTE: Not Adding Graph - this graph already exists - graph-id: ($existsAlready) - data-source-id: ($dataSourceId)\n";

					continue;
				}

				$empty = array(); /* Suggested Values are not been implemented */

				$returnArray = create_complete_graph_from_template($templateId, $hostId, $snmp_query_array, $empty);

				if ($graphTitle != "") {
					db_execute("UPDATE graph_templates_graph
						SET title=\"$graphTitle\"
						WHERE local_graph_id=" . $returnArray["local_graph_id"]);

					update_graph_title_cache($returnArray["local_graph_id"]);
				}

				$dataSourceId = db_fetch_cell("SELECT
					data_template_rrd.local_data_id
					FROM graph_templates_item, data_template_rrd
					WHERE graph_templates_item.local_graph_id = " . $returnArray["local_graph_id"] . "
					AND graph_templates_item.task_item_id = data_template_rrd.id
					LIMIT 1");

				foreach($returnArray["local_data_id"] as $item) {
					push_out_host($hostId, $item);

					if (strlen($dataSourceId)) {
						$dataSourceId .= ", " . $item;
					}else{
						$dataSourceId = $item;
					}
				}

				echo "Graph Added - graph-id: (" . $returnArray["local_graph_id"] . ") - data-source-ids: ($dataSourceId)\n";
			}
		}else{
			echo "ERROR: Could not find snmp-field " . $dsGraph["snmpField"] . " (" . $dsGraph["snmpValue"] . ") for host-id " . $hostId . " (" . $hosts[$hostId]["hostname"] . ")\n";
			echo "Try --host-id=" . $hostId . " --list-snmp-fields\n";
			exit(1);
		}
	}else{
		echo "ERROR: Graph Types must be either 'cg' or 'ds'\n";
		exit(1);
	}

	exit(0);
}else{
	display_help();
	exit(1);
}

function display_help() {
	echo "Add Graphs Script 1.2, Copyright 2008 - The Cacti Group\n\n";
	echo "A simple command line utility to add graphs in Cacti\n\n";
	echo "usage: add_graphs.php --graph-type=[cg|ds] --graph-template-id=[ID]\n";
	echo "    --host-id=[ID] [--graph-title=title] [graph options] [--force] [--quiet]\n\n";
	echo "For cg graphs:\n";
	echo "    [--input-fields=\"[data-template-id:]field-name=value ...\"] [--force]\n\n";
	echo "    --input-fields  If your data template allows for custom input data, you may specify that\n";
	echo "                    here.  The data template id is optional and applies where two input fields\n";
	echo "                    have the same name.\n";
	echo "    --force         If you set this flag, then new cg graphs will be created, even though they\n";
	echo "                    may already exist\n\n";
	echo "For ds graphs:\n";
	echo "    --snmp-query-id=[ID] --snmp-query-type-id=[ID] --snmp-field=[SNMP Field] --snmp-value=[SNMP Value]\n\n";
	echo "    [--graph-title=]       Defaults to what ever is in the graph template/data-source template.\n\n";
	echo "    [--reindex-method=]    the reindex method to be used for that data query\n";
	echo "                           if data query already exists, the reindex method will not be changed\n";
	echo "                    0|None   = no reindexing\n";
	echo "                    1|Uptime = Uptime goes Backwards (Default)\n";
	echo "                    2|Index  = Index Count Changed\n";
	echo "                    3|Fields = Verify all Fields\n";
	echo "List Options:\n";
	echo "    --list-hosts\n";
	echo "    --list-graph-templates [--host-template-id=[ID]]\n";
	echo "    --list-input-fields --graph-template-id=[ID]\n";
	echo "    --list-snmp-queries\n";
	echo "    --list-query-types  --snmp-query-id [ID]\n";
	echo "    --list-snmp-fields  --host-id=[ID] [--snmp-query-id=[ID]]\n";
	echo "    --list-snmp-values  --host-id=[ID] [--snmp-query-id=[ID]] --snmp-field=[Field]\n\n";
	echo "'cg' graphs are for things like CPU temp/fan speed, while \n";
	echo "'ds' graphs are for data-source based graphs (interface stats etc.)\n";
}

?>
