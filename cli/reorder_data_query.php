#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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
require_once($config['base_path'] . '/lib/snmp.php');
require_once($config['base_path'] . '/lib/data_query.php');

ini_set('max_execution_time', '0');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug		= false;
$host_id	= 'all';
$query_id	= '';
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
			case '--host-id':
				$host_id = $value;
				break;
			case '-qid':
			case '--qid':
				$query_id = $value;
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


$sql_where = "WHERE data_input_fields.type_code='output_type'";

/* determine the hosts to reindex */
if (strtolower($host_id) == 'all') {
	/* NOP */
}else if (is_numeric($host_id)) {
	$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ' ) . 'data_local.host_id = ' . $host_id;
} else {
	print "ERROR: You must specify either a host_id or 'all' to proceed.\n";
	display_help();
	exit;
}

/* determine data queries to rerun */
if (strtolower($query_id) == 'all') {
	/* do nothing */
}else if (is_numeric($query_id)) {
	$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ' ) . 'data_local.snmp_query_id= ' . $query_id;
} else {
	print "ERROR: You must specify either a query_id or 'all' to proceed.\n";
	display_help();
	exit;
}

/* get all object that have to be scanned */
$data_queries = db_fetch_assoc("SELECT data_local.host_id, data_local.snmp_query_id,
	data_local.snmp_index, data_template_data.local_data_id, data_template_data.data_input_id,
	data_input_data.data_template_data_id, data_input_data.data_input_field_id, data_input_data.value
	FROM data_local
	LEFT JOIN data_template_data
	ON data_local.id=data_template_data.local_data_id
	LEFT JOIN data_input_fields
	ON data_template_data.data_input_id = data_input_fields.data_input_id
	LEFT JOIN data_input_data
	ON data_template_data.id = data_input_data.data_template_data_id
	AND data_input_fields.id = data_input_data.data_input_field_id
	$sql_where");

/* issue warnings and start message if applicable */
print "WARNING: Do not interrupt this script.  Reordering can take quite some time\n";
debug("There are '" . cacti_sizeof($data_queries) . "' data query index items to run");

$i = 1;
if (cacti_sizeof($data_queries)) {
	foreach ($data_queries as $data_query) {
		if (!$debug) print '.';
		/* fetch current index_order from data_query XML definition and put it into host_snmp_query */
		update_data_query_sort_cache($data_query['host_id'], $data_query['snmp_query_id']);
		/* build array required for function call */
		$data_query['snmp_index_on'] = get_best_data_query_index_type($data_query['host_id'], $data_query['snmp_query_id']);
		/* as we request the output_type, 'value' gives the snmp_query_graph_id */
		$data_query['snmp_query_graph_id'] = $data_query['value'];
		debug("Data Query #'" . $i . "' host: '" . $data_query['host_id'] .
			"' SNMP Query Id: '" . $data_query['snmp_query_id'] .
			"' Index: " . $data_query['snmp_index'] .
			"' Index On: " . $data_query['snmp_index_on']
			);
		update_snmp_index_order($data_query);
		$i++;
	}
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Reorder Data Query Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: reorder_data_query.php --host-id=[id|all] [--qid=[query_id]] [--debug|-d]\n\n";
	print "A utility to Re-order Cacti Data Queries for a Device or system in batch mode.\n\n";
	print "Required:\n";
	print "    --qid=query_id - Only index on a specific data query id; or 'all' to reindex all data query id\n";
	print "Optional:\n";
	print "    --host-id=N    - The Device id to be reindexed; defaults to 'all' to reindex all Devices.\n\n";
	print "    --debug | -d   - Display verbose output during execution\n\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		print "DEBUG: " . trim($message) . "\n";
	}
}
