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

$start = date("Y-n-d H:i:s");
print $start . "\n";
ini_set("max_execution_time", "0");
$no_http_headers = true;

include(dirname(__FILE__) . "/include/config.php");
include_once($config["base_path"] . "/lib/snmp.php");
include_once($config["base_path"] . "/lib/rrd.php");
include_once($config["base_path"] . "/lib/graph_export.php");

if ( $_SERVER["argc"] == 1 ) {
	$polling_items = db_fetch_assoc("SELECT * from poller_item ORDER by host_id");
}else{
	if ($_SERVER["argc"] == "3") {
		if ($_SERVER["argv"][1] <= $_SERVER["argv"][2]) {
			$polling_items = db_fetch_assoc("SELECT * from poller_item " .
					"WHERE (host_id >= " .
					$_SERVER["argv"][1] .
					" and host_id <= " .
					$_SERVER["argv"][2] . ") ORDER by host_id");
		}else{
			print "ERROR: Invalid Arguments.  The first argument must be less than or equal to the first.\n";
			print "USAGE: CMD.PHP [[first_host] [second_host]]\n";

			if (read_config_option("log_perror") == "on") {
				log_data("ERROR: Invalid Arguments.  This rist argument must be less than or equal to the first.");
			}
		}
	}else{
		print "ERROR: Invalid Number of Arguments.  You must specify 0 or 2 arguments.\n";

		if (read_config_option("log_perror") == "on") {
			log_data("ERROR: Invalid Number of Arguments.  You must specify 0 or 2 arguments.");
		}
	}
}

if ((sizeof($polling_items) > 0) && (read_config_option("poller_enabled") == "on")) {
	$host_down = false;
	$new_host  = true;
	$last_host = $current_host = "";

	// startup Cacti php polling server and include the include file for script processing
	$cactides = array(
		0 => array("pipe", "r"), // stdin is a pipe that the child will read from
		1 => array("pipe", "w"), // stdout is a pipe that the child will write to
		2 => array("pipe", "w")  // stderr is a pipe to write to
		);

	if (function_exists("proc_open")) {
		$cactiphp = proc_open(read_config_option("path_php_binary") . " " . $config["base_path"] . "/script_server.php", $cactides, $pipes);
		$using_proc_function = true;

		// step below calls the include function with the script file
		fwrite($pipes[0], "include_once " . dirname(__FILE__) . "/scripts/script_functions.php\r\n");
	}else {
		$using_proc_function = False;
		if (read_config_option("log_perror") == "on") {
			log_data("WARNING: PHP version 4.3 or above is recommended for performance considerations.\n");
		}
	}

	foreach ($polling_items as $item) {
		$current_host = $item["hostname"];

		if ($current_host != $last_host) {
			$new_host = true;
			$host_down = false;
		}

		if ($new_host) {
			/* Perform an SNMP test to validate the host is alive */
			/* Wanted to do PING, but will have to wait.          */
			$last_host = $current_host;
			$output = cacti_snmp_get($item["hostname"],
				$item["snmp_community"],
				".1.3.6.1.2.1.1.5.0" ,
				$item["snmp_version"],
				$item["snmp_username"],
				$item["snmp_password"],
				$item["snmp_port"],
				$item["snmp_timeout"]);

			if ((substr_count($output, "ERROR") != 0) || ($output == "")) {
				$host_down = True;
				print "ERROR: Host is not respoding to SNMP query.\n";

				if (read_config_option("log_perror") == "on") {
					log_data(sprintf("ERROR: host '%s' is not responding to SNMP query, assumed down.", $current_host));
				}

				$new_host = false;
			}
		}

		if (!$host_down) {
			switch ($item["action"]) {
			case '0': /* snmp */
				$output = cacti_snmp_get($item["hostname"],
					$item["snmp_community"],
					$item["arg1"],
					$item["snmp_version"],
					$item["snmp_username"],
					$item["snmp_password"],
					$item["snmp_port"],
					$item["snmp_timeout"]);

				print "SNMP: " .
					$item["hostname"] . ":" .
					$item["snmp_port"] .
					", dsname: " .
					$item["rrd_name"] .
					", oid: " .
					$item["arg1"] .
					", value: $output\n";
				break;
			case '1': /* script (popen) */
				$command = $item["arg1"];
				$output = `$command`;

				print "CMD: $command, output: $output\n";

				break;
			case '2': /* script (php script server) */
				$command = $item["arg1"];

				// execute using php process
				if ($using_proc_function == 1) {
				   if (is_resource($cactiphp)) {
					   // $pipes now looks like this:
					   // 0 => writeable handle connected to child stdin
					   // 1 => readable handle connected to child stdout
					   // 2 => any error output will be sent to child stderr

						// send command to the php server
					   fwrite($pipes[0], $command . "\r\n");

						// get result from server
						$output = fgets($pipes[1], 1024);

						if (substr_count($output, "ERROR") > 0) {
							$output = "";
						}
				   }
				// execute the old fashion way
				} else {
					$command = read_config_option("path_php_binary") - " " . $command;
					$output = `$command`;
				}

				print "CMD: $command, output: $output";

				break;
			} /* End Switch */

			if (isset($output)) {
				db_execute("insert into poller_output (local_data_id,rrd_name,time,output) values (" . $item["local_data_id"] . ",'" . $item["rrd_name"] . "',NOW(),'" . addslashes($output) . "')");
			}
		} /* Next Cache Item */
	} /* End foreach */

	if ($using_proc_function == true) {
		// close php server process
		fwrite($pipes[0], "quit\r\n");
		fclose($pipes[0]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$return_value = proc_close($cactiphp);
	}

	db_execute("insert into poller_time (poller_id, start_time, end_time) values (0, NOW(), NOW())");
}else{
	print "Either there are no items in the cache or polling is disabled\n";
}

?>