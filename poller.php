#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

/* We are not talking to the browser */
$no_http_headers = true;

/* Start Initialization Section */
include(dirname(__FILE__) . "/include/config.php");
include_once($config["base_path"] . "/lib/poller.php");
include_once($config["base_path"] . "/lib/data_query.php");
include_once($config["base_path"] . "/lib/rrd.php");

/* Record Start Time */
list($micro,$seconds) = split(" ", microtime());
$start = $seconds + $micro;
//$start = date("Y-n-d H:i:s");

/* Let PHP Run Just as Long as It Has To */
ini_set("max_execution_time", "0");

/* Get number of polling items from the database */
$polling_items = db_fetch_assoc("select id from host where disabled = '' order by id");

/* Retreive the number of concurrent process settings */
$concurrent_processes = read_config_option("concurrent_processes");

/* Initialize counters for script file handling */
$host_count = 1;

/* Initialize file creation flags */
$change_files = False;

/* Initialize file and host count pointers */
$process_file_number = 0;
$first_host = 0;
$last_host = 0;

/* Update web paths for the poller */
db_execute("replace into settings (name,value) values ('path_webroot','" . dirname(__FILE__) . "')");
db_execute("replace into settings (name,value) values ('path_php_server','" . dirname(__FILE__) . PATH_DELIMITER . "script_server.php')");

// Obtain some defaults from the database
$poller = read_config_option("poller_type");
$max_threads = read_config_option("max_threads");

// Initialize poller_time and poller_output tables
db_execute("truncate table poller_time");
db_execute("truncate table poller_output");

// Enter Mainline Processing
if ((sizeof($polling_items) > 0) and (read_config_option("poller_enabled") == "on")) {
	/* Determine the number of hosts to process per file */
	$hosts_per_file = ceil(sizeof($polling_items) / $concurrent_processes );

	/* Determine Command Name */
	if (($config["cacti_server_os"] == "unix") and ($poller == "2")) {
		$command_string = read_config_option("path_cactid");
		$extra_args = "";
		$method = "cactid";
	}else if ($config["cacti_server_os"] == "unix") {
		$command_string = read_config_option("path_php_binary");
		$extra_args = $config["base_path"] . "/cmd.php";
		$method = "cmd.php";
	}else if ($poller == "2") {
		$command_string = read_config_option("path_cactid");
		$extra_args = "";
		$method = "cactid";
	}else{
		$command_string = read_config_option("path_php_binary");
		$extra_args = $config["base_path"] . "\\cmd.php";
		$method = "cmd.php";
	}

	/* Populate each execution file with appropriate information */
	foreach ($polling_items as $item) {
		if ($host_count == 1) {
			$first_host = $item["id"];
		}

		if ($host_count == $hosts_per_file) {
			$last_host = $item["id"];
			$change_files = True;
		}

		$host_count ++;

		if ($change_files) {
			exec_background($command_string, "$extra_args $first_host $last_host");

			$host_count = 1;
			$change_files = False;
			$process_file_number++;
			$first_host = 0;
			$last_host = 0;
		} /* End change_files */
	} /* End For Each */

	if ($host_count > 1) {
		$last_host = $item["id"];

		exec_background($command_string, "$extra_args $first_host $last_host");
		$process_file_number++;
	}

	/* insert the current date/time for graphs */
	db_execute("replace into settings (name,value) values ('date',NOW())");

	if ($poller == "1") {
		$max_threads = "N/A";
	}

	$loop_count = 0;
	while (1) {
		$polling_items = db_fetch_assoc("select poller_id,end_time from poller_time where poller_id = 0");

		if (sizeof($polling_items) == $process_file_number) {
			/* create/update the rrd files */
			$results = db_fetch_assoc("select
				poller_output.output,
				poller_output.time,
				poller_output.local_data_id,
				poller_item.rrd_path,
				poller_item.rrd_name
				from poller_output,poller_item
				where (poller_output.local_data_id=poller_item.local_data_id and poller_output.rrd_name=poller_item.rrd_name)");

			if (sizeof($results) > 0) {
				/* open a pipe to rrdtool for writing */
				$rrdtool_pipe = rrd_init();

				foreach ($results as $item) {
					$rrd_update_array{$item["rrd_path"]}["time"] = strtotime($item["time"]);
					$rrd_update_array{$item["rrd_path"]}["local_data_id"] = $item["local_data_id"];
					$rrd_update_array{$item["rrd_path"]}["items"]{$item["rrd_name"]} = rtrim(strtr(strtr($item["output"],'\r',''),'\n',''));
				}

				rrdtool_function_update($rrd_update_array, $rrdtool_pipe);

				rrd_close($rrdtool_pipe);
			}

			/* take time and log performance data */
			list($micro,$seconds) = split(" ", microtime());
			$end = $seconds + $micro;

			if (read_config_option("log_pstats") == "on") {
				log_data(sprintf("STATS: " .
				"Execution Time: %01.4f s, " .
				"Method: %s, " .
				"Max Processes: %s, " .
				"Max Threads/Process: %s, " .
				"Polled Hosts: %s, " .
				"Hosts/Process: %s",
				round($end-$start,4),
				$method,
				$concurrent_processes,
				$max_threads,
				sizeof($polling_items),
				$hosts_per_file));
			}

			break;
		}else {
			print "Waiting on " . ($process_file_number - sizeof($polling_items)) . "/$process_file_number pollers.\n";
			usleep(200000);
			$loop_count++;
		}
	}

	/* process poller commands */
	$poller_commands = db_fetch_assoc("select
		poller_command.action,
		poller_command.command
		from poller_command
		where poller_command.poller_id=0");

	if (sizeof($poller_commands) > 0) {
		foreach ($poller_commands as $command) {
			switch ($command["action"]) {
			case POLLER_COMMAND_REINDEX:
				list($host_id, $data_query_id) = explode(":", $command["command"]);

				print "Command: Re-cache host #$host_id, data query #$data_query_id\n";

				run_data_query($host_id, $data_query_id);
			}
		}

		db_execute("delete from poller_command where poller_id=0");
	}
}else{
	print "There are no items in your poller cache or polling is disabled. Make sure you have at least one data source created. If you do, go to 'Utilities', and select 'Clear Poller Cache'.\n";
}
// End Mainline Processing

?>