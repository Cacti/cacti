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

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

/* utility requires input parameters */
if (sizeof($parms) == 0) {
	print "ERROR: You must supply input parameters\n\n";
	display_help();
	exit;
}

$debug		= FALSE;
$host_id	= "all";
$host_descr	= "";

foreach($parms as $parameter) {
	@list($arg, $value) = @explode("=", $parameter);
	
	switch ($arg) {
		case "-id":
		case "--id":			$host_id = $value;			break;
		case "-qid":
		case "--qid":			$query_id = $value;			break;
		case "-d":
		case "--debug":			$debug = TRUE;				break;
		case "-h":
		case "-v":
		case "--version":
		case "--help":			display_help();				exit;
		default:
								print "ERROR: Invalid Parameter " . $parameter . "\n\n";
								display_help();
															exit;
	}
}


$sql_where = "WHERE data_input_fields.type_code='output_type'";

/* determine the hosts to reindex */
if (strtolower($host_id) == "all") {
	/* NOP */
}else if (is_numeric($host_id)) {
	$sql_where .= (strlen($sql_where) ? " AND " : " WHERE " ) . "data_local.host_id = '$host_id'";
}else{
	print "ERROR: You must specify either a host_id or 'All' to proceed.\n";
	display_help();
	exit;
}

/* determine data queries to rerun */
if (is_numeric($query_id)) {
	$sql_where .= (strlen($sql_where) ? " AND " : " WHERE " ) . "data_local.snmp_query_id='$query_id'";
}else{
	print "ERROR: You must specify either a query_id or 'all' to proceed.\n";
	display_help();
	exit;
}


/* get all object that have to be scanned */
$data_queries = db_fetch_assoc("SELECT " .
		"`data_local`.`host_id`, " .
		"`data_local`.`snmp_query_id`, " .
		"`data_local`.`snmp_index`, " .
		"`data_template_data`.`local_data_id`, " .
		"`data_template_data`.`data_input_id`, " .
		"`data_input_data`.`data_template_data_id`, " .
		"`data_input_data`.`data_input_field_id`, " .
		"`data_input_data`.`value` " .
		"FROM data_local " .
		"LEFT JOIN data_template_data ON data_local.id=data_template_data.local_data_id " .
		"LEFT JOIN data_input_fields ON data_template_data.data_input_id = data_input_fields.data_input_id " .
		"LEFT JOIN data_input_data ON ( " .
			"data_template_data.id = data_input_data.data_template_data_id " .
			"AND data_input_fields.id = data_input_data.data_input_field_id " .
		") " .
		$sql_where . " ");

/* issue warnings and start message if applicable */
print "WARNING: Do not interrupt this script.  Reordering can take quite some time\n";
debug("There are '" . sizeof($data_queries) . "' data query index items to run");

$i = 1;
if (sizeof($data_queries)) {
	foreach ($data_queries as $data_query) {
		if (!$debug) print ".";
		/* fetch current index_order from data_query XML definition and put it into host_snmp_query */
		update_data_query_sort_cache($data_query["host_id"], $data_query["snmp_query_id"]);
		/* build array required for function call */
		$data_query["snmp_index_on"] = get_best_data_query_index_type($data_query["host_id"], $data_query["snmp_query_id"]);
		/* as we request the output_type, "value" gives the snmp_query_graph_id */
		$data_query["snmp_query_graph_id"] = $data_query["value"]; 
		debug("Data Query #'" . $i . "' host: '" . $data_query["host_id"] .
			"' SNMP Query Id: '" . $data_query["snmp_query_id"] .
			"' Index: " . $data_query["snmp_index"] .
			"' Index On: " . $data_query["snmp_index_on"]
			);
		update_snmp_index_order($data_query);
		$i++;
	}
}

/**
 * perform sql updates for all required tables for new index_sort_order
 * @arg array $snmp_query_array
 * 				$host_id
 * 				snmp_query_id
 * 				snmp_index_on
 * 				snmp_query_graph_id
 * 				snmp_index
 * 				$data_template_data_id	
 * 				$local_data_id
 * 
 * this code stems from lib/template.php, function create_complete_graph_from_template
 */
function update_snmp_index_order($data_query) {
	if (is_array($data_query)) {
		$data_input_field = array_rekey(db_fetch_assoc("SELECT " .
				"data_input_fields.id, " .
				"data_input_fields.type_code " .
				"FROM (snmp_query,data_input,data_input_fields) " .
				"WHERE snmp_query.data_input_id=data_input.id " .
				"AND data_input.id=data_input_fields.data_input_id " .
				"AND (data_input_fields.type_code='index_type' " .
				"OR data_input_fields.type_code='index_value' " .
				"OR data_input_fields.type_code='output_type') " .
				"AND snmp_query.id=" . $data_query["snmp_query_id"]), "type_code", "id");
		
		$snmp_cache_value = db_fetch_cell("SELECT field_value " .
				"FROM host_snmp_cache " .
				"WHERE host_id='" . $data_query["host_id"] . "' " . 
				"AND snmp_query_id='" . $data_query["snmp_query_id"] . "' " .
				"AND field_name='" . $data_query["snmp_index_on"] . "' " .
				"AND snmp_index='" . $data_query["snmp_index"] . "'");
		
		/* save the value to index on (ie. ifindex, ifip, etc) */
		db_execute("REPLACE INTO data_input_data " .
				"(data_input_field_id, data_template_data_id, t_value, value) " .
				"VALUES (" . 
				$data_input_field["index_type"] . ", " . 
				$data_query["data_template_data_id"] . ", '', '" . 
				$data_query["snmp_index_on"] . "')");
		
		/* save the actual value (ie. 3, 192.168.1.101, etc) */
		db_execute("REPLACE INTO data_input_data " .
				"(data_input_field_id,data_template_data_id,t_value,value) " .
				"VALUES (" . 
				$data_input_field["index_value"] . "," . 
				$data_query["data_template_data_id"] . ",'','" . 
				addslashes($snmp_cache_value) . "')");
		
		/* set the expected output type (ie. bytes, errors, packets) */
		db_execute("REPLACE INTO data_input_data " .
				"(data_input_field_id,data_template_data_id,t_value,value) " .
				"VALUES (" . 
				$data_input_field["output_type"] . "," . 
				$data_query["data_template_data_id"] . ",'','" . 
				$data_query["snmp_query_graph_id"] . "')");
		
		/* now that we have put data into the 'data_input_data' table, update the snmp cache for ds's */
		update_data_source_data_query_cache($data_query["local_data_id"]);
	}
}

/*	display_help - displays the usage of the function */
function display_help () {
	print "Cacti Reorder Data Query Script 1.0, Copyright 2004-2013 - The Cacti Group\n\n";
	print "usage: reorder_data_query.php --id=[host_id|All] [--qid=[query_id]]\n";
	print "                           [-d] [-h] [--help] [-v] [--version]\n\n";
	print "--id=host_id             - The host_id to have data queries reindexed; defaults to 'All' to reindex all hosts\n";
	print "--qid=query_id           - Only index on a specific data query id\n";
	print "--debug                  - Display verbose output during execution\n";
	print "-v --version             - Display this help message\n";
	print "-h --help                - Display this help message\n";
}

function debug($message) {
	global $debug;
	
	if ($debug) {
		print("DEBUG: " . $message . "\n");
	}
}

?>
