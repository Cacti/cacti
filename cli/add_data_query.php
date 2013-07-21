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
include_once($config["base_path"]."/lib/data_query.php");

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

if (sizeof($parms)) {
	$displayHosts 		= FALSE;
	$displayDataQueries = FALSE;
	$quietMode			= FALSE;
	unset($host_id);
	unset($data_query_id);
	unset($reindex_method);

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
		case "--data-query-id":
			$data_query_id = $value;
			if (!is_numeric($data_query_id)) {
				echo "ERROR: You must supply a numeric data-query-id for all hosts!\n";
				exit(1);
			}

			break;
		case "--reindex-method":
			if (is_numeric($value) &&
				($value >= DATA_QUERY_AUTOINDEX_NONE) &&
				($value <= DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION)) {
				$reindex_method = $value;
			} else {
				switch (strtolower($value)) {
					case "none":
						$reindex_method = DATA_QUERY_AUTOINDEX_NONE;
						break;
					case "uptime":
						$reindex_method = DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME;
						break;
					case "index":
						$reindex_method = DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE;
						break;
					case "fields":
						$reindex_method = DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION;
						break;
					default:
						echo "ERROR: You must supply a valid reindex method for all hosts!\n";
						exit(1);
				}
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
		case "--list-data-queries":
			$displayDataQueries = TRUE;
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

	/* list options, recognizing $quietMode */
	if ($displayHosts) {
		$hosts = getHosts();
		displayHosts($hosts, $quietMode);
		exit(0);
	}
	if ($displayDataQueries) {
		$data_queries = getSNMPQueries();
		displaySNMPQueries($data_queries, $quietMode);
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

	if (!isset($data_query_id)) {
		echo "ERROR: You must supply a valid data-query-id for all hosts!\n";
		exit(1);
	}

	if (!isset($reindex_method)) {
		echo "ERROR: You must supply a valid reindex-method for all hosts!\n";
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
	 * verify valid data query and get a name for it
	 */
	$data_query_name = db_fetch_cell("SELECT name FROM snmp_query WHERE id = " . $data_query_id);
	if (!isset($data_query_name)) {
		echo "ERROR: Unknown Data Query Id ($data_query_id)\n";
		exit(1);
	}

	/*
	 * Now, add the data query and run it once to get the cache filled
	 */
	$exists_already = db_fetch_cell("SELECT host_id FROM host_snmp_query WHERE host_id=$host_id AND snmp_query_id=$data_query_id AND reindex_method=$reindex_method");
	if ((isset($exists_already)) &&
		($exists_already > 0)) {
		echo "ERROR: Data Query is already associated for host: ($host_id: $host_name) data query ($data_query_id: $data_query_name) reindex method ($reindex_method: $reindex_types[$reindex_method])\n";
		exit(1);
	}else{
		db_execute("REPLACE INTO host_snmp_query (host_id,snmp_query_id,reindex_method) " .
				   "VALUES (". $host_id . ","
							 . $data_query_id . ","
							 . $reindex_method . "
							)");
		/* recache snmp data */
		run_data_query($host_id, $data_query_id);
	}

	if (is_error_message()) {
		echo "ERROR: Failed to add this data query for host ($host_id: $host_name) data query ($data_query_id: $data_query_name) reindex method ($reindex_method: $reindex_types[$reindex_method])\n";
		exit(1);
	} else {
		echo "Success - Host ($host_id: $host_name) data query ($data_query_id: $data_query_name) reindex method ($reindex_method: $reindex_types[$reindex_method])\n";
		exit(0);
	}
}else{
	display_help();
	exit(0);
}

function display_help() {
	echo "Add Data Query Script 1.0, Copyright 2008 - The Cacti Group\n\n";
	echo "A simple command line utility to add a data query to an existing device in Cacti\n\n";
	echo "usage: add_data_query.php --host-id=[ID] --data-query-id=[dq_id] --reindex-method=[method] [--quiet]\n\n";
	echo "Required:\n";
	echo "    --host-id         the numerical ID of the host\n";
	echo "    --data-query-id   the numerical ID of the data_query to be added\n";
	echo "    --reindex-method  the reindex method to be used for that data query\n";
	echo "                      0|None   = no reindexing\n";
	echo "                      1|Uptime = Uptime goes Backwards\n";
	echo "                      2|Index  = Index Count Changed\n";
	echo "                      3|Fields = Verify all Fields\n";
	echo "List Options:\n";
	echo "    --list-hosts\n";
	echo "    --list-data-queries\n";
	echo "    --quiet - batch mode value return\n\n";
	echo "If the data query was already associated, it will be reindexed.\n\n";
}

?>
