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
$start = time();
ini_set("max_execution_time", "0");
$no_http_headers = true;

include(dirname(__FILE__) . "/include/config.php");
include_once($config["base_path"] . "/lib/snmp.php");
include_once($config["base_path"] . "/lib/rrd.php");
include_once($config["base_path"] . "/lib/functions.php");
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
	$host_down = False;
	$new_host  = True;
	$last_host = $current_host = "";

	// startup Cacti php polling server
	$cactides = array(
   	0 => array("pipe", "r"), // stdin is a pipe that the child will read from
   	1 => array("pipe", "w"), // stdout is a pipe that the child will write to
   	2 => array("pipe", "w")  // stderr is a pipe to write to
	);

	$cactiphp = proc_open(read_config_option("php_path"), $cactides, $pipes);

	foreach ($polling_items as $item) {
		$current_host = $item["hostname"];

		if ($current_host != $last_host) {
			$new_host = True;
			$host_down = False;
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

				$new_host = False;
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
			case '1': /* one output script */
				$command = $item["command"];
				$output = `$command`;

				print "CMD: $command, output: $output\n";

				$data_input_field = db_fetch_row("select id,update_rra from data_input_fields where data_input_id=" . $item["data_input_id"] . " and input_output='out'");

				if ($data_input_field["update_rra"] == "") {
					/* DO NOT write data to rrd; put it in the db instead */
					db_execute("insert into data_input_data (data_input_field_id,data_template_data_id,value)
						values (" . $data_input_field["id"] .
						"," . db_fetch_cell("SELECT id from data_template_data" .
						"where local_data_id=" .
						$item["local_data_id"]) .
						",'$output')");
						$item["rrd_name"] = ""; /* no rrd action here */
				}

				break;
			case '2': /* multi output script */
				$command = $item["command"];
				$output = `$command`;

				print "CMD: $command, output: $output\n";

				$output_array = split(" ", $output);

				for ($i=0;($i<count($output_array));$i++) {
					$data_input_field = db_fetch_row("select id,update_rra from data_input_fields" .
						"where data_name='" .
						ereg_replace("^([a-zA-Z0-9_-]+):.*$", "\\1",
						$output_array[$i]) .
						"' and data_input_id=" .
						$item["data_input_id"] .
						" and input_output='out'");
						$rrd_name = db_fetch_cell("select data_source_name " .
						"from data_template_rrd where local_data_id=" .
						$item["local_data_id"] .
						" and data_input_field_id=" .
						$data_input_field["id"]);

					if ($data_input_field["update_rra"] == "on") {
						print "MULTI expansion: found fieldid: " .
						$data_input_field["id"] .
						", found rrdname: $rrd_name, value: " .
						trim(ereg_replace("^[a-zA-Z0-9_-]+:(.*)$", "\\1",
						$output_array[$i])) . "\n";
						$update_cache_array{$item["local_data_id"]}{$rrd_name} =
						trim(ereg_replace("^[a-zA-Z0-9_-]+:(.*)$", "\\1", $output_array[$i]));
					}else{
						/* DO NOT write data to rrd; put it in the db instead */
						db_execute("insert into data_input_data " .
							"(data_input_field_id,data_template_data_id,value)
							values (" . $data_input_field["id"] . "," .
						db_fetch_cell("select id from data_template_data
							where local_data_id=" . $item["local_data_id"]) .
							",'" . trim(ereg_replace("^[a-zA-Z0-9_-]+:(.*)$", "\\1",
							$output_array[$i])) . "')");
					}
				}

				break;
			case '3': /* one output script */
				$command = $item["command"];
			   if (is_resource($cactiphp)) {
				   // $pipes now looks like this:
				   // 0 => writeable handle connected to child stdin
				   // 1 => readable handle connected to child stdout
				   // 2 => any error output will be sent to child stderr

					// send command to the php server
				   fwrite($pipes[0], $command);

					// get result from server
					$output = fgets($pipes[1], 1024);
					if (substr_count($output, "ERROR") > 0) {
						$output = "U";
					}
			   }
				print "CMD: $command, output: $output\n";

				$data_input_field = db_fetch_row("select id,update_rra from data_input_fields where data_input_id=" . $item["data_input_id"] . " and input_output='out'");

				if ($data_input_field["update_rra"] == "") {
					/* DO NOT write data to rrd; put it in the db instead */
					db_execute("insert into data_input_data (data_input_field_id,data_template_data_id,value)
						values (" . $data_input_field["id"] .
						"," . db_fetch_cell("SELECT id from data_template_data" .
						"where local_data_id=" .
						$item["local_data_id"]) .
						",'$output')");
						$item["rrd_name"] = ""; /* no rrd action here */
				}

				break;
			case '4': /* multi output script */
				$command = $item["command"];
			   if (is_resource($cactiphp)) {
				   // $pipes now looks like this:
				   // 0 => writeable handle connected to child stdin
				   // 1 => readable handle connected to child stdout
				   // 2 => any error output will be sent to child stderr

					// send command to the php server
				   fwrite($pipes[0], $command);

					// get result from server
					$output = fgets($pipes[1], 1024);
					if (substr_count($output, "ERROR") > 0) {
						$output = "U";
					}
			   }
				print "CMD: $command, output: $output\n";

				$output_array = split(" ", $output);

				for ($i=0;($i<count($output_array));$i++) {
					$data_input_field = db_fetch_row("select id,update_rra from data_input_fields" .
						"where data_name='" .
						ereg_replace("^([a-zA-Z0-9_-]+):.*$", "\\1",
						$output_array[$i]) .
						"' and data_input_id=" .
						$item["data_input_id"] .
						" and input_output='out'");
						$rrd_name = db_fetch_cell("select data_source_name " .
						"from data_template_rrd where local_data_id=" .
						$item["local_data_id"] .
						" and data_input_field_id=" .
						$data_input_field["id"]);

					if ($data_input_field["update_rra"] == "on") {
						print "MULTI expansion: found fieldid: " .
						$data_input_field["id"] .
						", found rrdname: $rrd_name, value: " .
						trim(ereg_replace("^[a-zA-Z0-9_-]+:(.*)$", "\\1",
						$output_array[$i])) . "\n";
						$update_cache_array{$item["local_data_id"]}{$rrd_name} =
						trim(ereg_replace("^[a-zA-Z0-9_-]+:(.*)$", "\\1", $output_array[$i]));
					}else{
						/* DO NOT write data to rrd; put it in the db instead */
						db_execute("insert into data_input_data " .
							"(data_input_field_id,data_template_data_id,value)
							values (" . $data_input_field["id"] . "," .
						db_fetch_cell("select id from data_template_data
							where local_data_id=" . $item["local_data_id"]) .
							",'" . trim(ereg_replace("^[a-zA-Z0-9_-]+:(.*)$", "\\1",
							$output_array[$i])) . "')");
					}
				}

				break;
			} /* End Switch */

			if (!empty($item["rrd_name"])) {
				$update_cache_array{$item["local_data_id"]}{$item["rrd_name"]} = trim($output);
			}

			rrdtool_function_create($item["local_data_id"], false);

		} /* Next Cache Item */
	} /* End foreach */

	// close php server process
	fclose($pipes[0]);
	fclose($pipes[1]);
	fclose($pipes[2]);
   $return_value = proc_close($process);

	if (isset($update_cache_array)) {
		rrdtool_function_update($update_cache_array);
	}
}else{
	print "Either there are no items in the cache or polling is disabled\n";
}

/* insert the current date/time for graphs */
db_execute("replace into settings (name,value) values ('date',NOW())");

/* dump static images/html file if user wants it */
graph_export();

?>