<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2010 The Cacti Group                                 |
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

/* exec_poll - executes a command and returns its output
   @arg $command - the command to execute
   @returns - the output of $command after execution */
function exec_poll($command) {
	global $config;

	if (function_exists("popen")) {
		if ($config["cacti_server_os"] == "unix") {
			$fp = popen($command, "r");
		}else{
			$fp = popen($command, "rb");
		}

		/* return if the popen command was not successfull */
		if (!is_resource($fp)) {
			cacti_log("WARNING; Problem with POPEN command.");
			return "U";
		}

		$output = fgets($fp, 4096);

		pclose($fp);
	}else{
		$output = `$command`;
	}

	return $output;
}

/* exec_poll_php - sends a command to the php script server and returns the
     output
   @arg $command - the command to send to the php script server
   @arg $using_proc_function - whether or not this version of php is making use
     of the proc_open() and proc_close() functions (php 4.3+)
   @arg $pipes - the array of r/w pipes returned from proc_open()
   @arg $proc_fd - the file descriptor returned from proc_open()
   @returns - the output of $command after execution against the php script
     server */
function exec_poll_php($command, $using_proc_function, $pipes, $proc_fd) {
	global $config;
	/* execute using php process */
	if ($using_proc_function == 1) {
		if (is_resource($proc_fd)) {
			/* $pipes now looks like this:
			 * 0 => writeable handle connected to child stdin
			 * 1 => readable handle connected to child stdout
			 * 2 => any error output will be sent to child stderr */

			/* send command to the php server */
			fwrite($pipes[0], $command . "\r\n");

			$output = fgets($pipes[1], 4096);

			if (substr_count($output, "ERROR") > 0) {
				$output = "U";
			}
		}
	/* execute the old fashion way */
	}else{
		/* formulate command */
		$command = read_config_option("path_php_binary") . " " . $command;

		if (function_exists("popen")) {
			if ($config["cacti_server_os"] == "unix")  {
				$fp = popen($command, "r");
			}else{
				$fp = popen($command, "rb");
			}

			/* return if the popen command was not successfull */
			if (!is_resource($fp)) {
				cacti_log("WARNING; Problem with POPEN command.");
				return "U";
			}

			$output = fgets($fp, 4096);

			pclose($fp);
		}else{
			$output = `$command`;
		}
	}

	return $output;
}

/* exec_background - executes a program in the background so that php can continue
     to execute code in the foreground
   @arg $filename - the full pathname to the script to execute
   @arg $args - any additional arguments that must be passed onto the executable */
function exec_background($filename, $args = "") {
	global $config, $debug;

	if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG || $debug) {
		cacti_log("DEBUG: About to Spawn a Remote Process [CMD: $filename, ARGS: $args]", true, "POLLER");
	}

	if (file_exists($filename)) {
		if ($config["cacti_server_os"] == "win32") {
			pclose(popen("start \"Cactiplus\" /I \"" . $filename . "\" " . $args, "r"));
		}else{
			exec($filename . " " . $args . " > /dev/null &");
		}
	}elseif (file_exists_2gb($filename)) {
		exec($filename . " " . $args . " > /dev/null &");
	}
}

/* file_exists_2gb - fail safe version of the file exists function to correct
     for errors in certain versions of php.
   @arg $filename - the name of the file to be tested. */
function file_exists_2gb($filename) {
	global $config;

	if ($config["cacti_server_os"] != "win32") {
		system("test -f $filename", $rval);
		return ($rval == 0);
	}else{
		return 0;
	}
}

/* update_reindex_cache - builds a cache that is used by the poller to determine if the
     indexes for a particular data query/host have changed
   @arg $host_id - the id of the host to which the data query belongs
   @arg $data_query_id - the id of the data query to rebuild the reindex cache for */
function update_reindex_cache($host_id, $data_query_id) {
	global $config;

	include_once($config["library_path"] . "/data_query.php");
	include_once($config["library_path"] . "/snmp.php");

	/* will be used to keep track of sql statements to execute later on */
	$recache_stack = array();

	$host            = db_fetch_row("select hostname, snmp_community, snmp_version, snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context, snmp_port, snmp_timeout from host where id=$host_id");
	$data_query      = db_fetch_row("select reindex_method, sort_field from host_snmp_query where host_id=$host_id and snmp_query_id=$data_query_id");
	$data_query_type = db_fetch_cell("select data_input.type_id from (data_input,snmp_query) where data_input.id=snmp_query.data_input_id and snmp_query.id=$data_query_id");
	$data_query_xml  = get_data_query_array($data_query_id);

	switch ($data_query["reindex_method"]) {
		case DATA_QUERY_AUTOINDEX_NONE:
			break;
		case DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME:
			/* the uptime backwards method requires snmp, so make sure snmp is actually enabled
			 * on this device first */
			if ($host["snmp_version"] > 0) {
				if (isset($data_query_xml["oid_uptime"])) {
					$oid_uptime = $data_query_xml["oid_uptime"];
				}elseif (isset($data_query_xml["uptime_oid"])) {
					$oid_uptime = $data_query_xml["uptime_oid"];
				}else{
					$oid_uptime = ".1.3.6.1.2.1.1.3.0";
				}

				$assert_value = cacti_snmp_get($host["hostname"],
					$host["snmp_community"],
					$oid_uptime,
					$host["snmp_version"],
					$host["snmp_username"],
					$host["snmp_password"],
					$host["snmp_auth_protocol"],
					$host["snmp_priv_passphrase"],
					$host["snmp_priv_protocol"],
					$host["snmp_context"],
					$host["snmp_port"],
					$host["snmp_timeout"],
					SNMP_POLLER);

				array_push($recache_stack, "insert into poller_reindex (host_id,data_query_id,action,op,assert_value,arg1) values ($host_id,$data_query_id,0,'<','$assert_value','$oid_uptime')");
			}

			break;
		case DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE:
			/* this method requires that some command/oid can be used to determine the
			 * current number of indexes in the data query */
			$assert_value = sizeof(db_fetch_assoc("select snmp_index from host_snmp_cache where host_id=$host_id and snmp_query_id=$data_query_id group by snmp_index"));

			if ($data_query_type == DATA_INPUT_TYPE_SNMP_QUERY) {
				if (isset($data_query_xml["oid_num_indexes"])) {
					array_push($recache_stack, "insert into poller_reindex (host_id, data_query_id, action, op, assert_value, arg1) values ($host_id, $data_query_id, 0, '=', '$assert_value', '" . $data_query_xml["oid_num_indexes"] . "')");
				}
			}else if ($data_query_type == DATA_INPUT_TYPE_SCRIPT_QUERY) {
				if (isset($data_query_xml["arg_num_indexes"])) {
					array_push($recache_stack, "insert into poller_reindex (host_id, data_query_id, action, op, assert_value, arg1) values ($host_id, $data_query_id, 1, '=', '$assert_value', '" . get_script_query_path((isset($data_query_xml["arg_prepend"]) ? $data_query_xml["arg_prepend"] . " ": "") . $data_query_xml["arg_num_indexes"], $data_query_xml["script_path"], $host_id) . "')");
				}
			}

			break;
		case DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION:
			$primary_indexes = db_fetch_assoc("select snmp_index,oid,field_value from host_snmp_cache where host_id=$host_id and snmp_query_id=$data_query_id and field_name='" . $data_query["sort_field"] . "'");

			if (sizeof($primary_indexes) > 0) {
				foreach ($primary_indexes as $index) {
					$assert_value = $index["field_value"];

					if ($data_query_type == DATA_INPUT_TYPE_SNMP_QUERY) {
						array_push($recache_stack, "insert into poller_reindex (host_id, data_query_id, action, op, assert_value, arg1) values ($host_id, $data_query_id, 0, '=', '$assert_value', '" . $data_query_xml["fields"]{$data_query["sort_field"]}["oid"] . "." . $index["snmp_index"] . "')");
					}else if ($data_query_type == DATA_INPUT_TYPE_SCRIPT_QUERY) {
						array_push($recache_stack, "insert into poller_reindex (host_id, data_query_id, action, op, assert_value, arg1) values ($host_id, $data_query_id, 1, '=', '$assert_value', '" . get_script_query_path((isset($data_query_xml["arg_prepend"]) ? $data_query_xml["arg_prepend"] . " ": "") . $data_query_xml["arg_get"] . " " . $data_query_xml["fields"]{$data_query["sort_field"]}["query_name"] . " " . $index["snmp_index"], $data_query_xml["script_path"], $host_id) . "')");
					}
				}
			}

			break;
	}

	/* save the delete for last since we need to reference this table in the code above */
	db_execute("delete from poller_reindex where host_id=$host_id and data_query_id=$data_query_id");

	for ($i=0; $i<count($recache_stack); $i++) {
		db_execute($recache_stack[$i]);
	}
}

/* process_poller_output - grabs data from the 'poller_output' table and feeds the *completed*
     results to RRDTool for processing
  @arg $rrdtool_pipe - the array of pipes containing the file descriptor for rrdtool
  @arg $remainder - don't use LIMIT if TRUE */
function process_poller_output(&$rrdtool_pipe, $remainder = FALSE) {
	global $config, $debug;

	include_once($config["library_path"] . "/rrd.php");

	/* let's count the number of rrd files we processed */
	$rrds_processed = 0;

	if ($remainder) {
		$limit = "";
	}else{
		$limit = "LIMIT 10000";
	}

	/* create/update the rrd files */
	$results = db_fetch_assoc("select
		poller_output.output,
		poller_output.time,
		poller_output.local_data_id,
		poller_item.rrd_path,
		poller_item.rrd_name,
		poller_item.rrd_num
		from (poller_output,poller_item)
		where (poller_output.local_data_id=poller_item.local_data_id and poller_output.rrd_name=poller_item.rrd_name)
		$limit");

	if (sizeof($results) > 0) {
		/* create an array keyed off of each .rrd file */
		foreach ($results as $item) {
			/* trim the default characters, but add single and double quotes */
			$value = trim($item["output"], " \r\n\t\x0B\0\"'");
			$unix_time = strtotime($item["time"]);

			$rrd_update_array{$item["rrd_path"]}["local_data_id"] = $item["local_data_id"];

			/* single one value output */
			if ((is_numeric($value)) || ($value == "U")) {
				$rrd_update_array{$item["rrd_path"]}["times"][$unix_time]{$item["rrd_name"]} = $value;
			/* special case of one value output: hexadecimal to decimal conversion */
			}elseif (is_hexadecimal($value)) {
				/* attempt to accomodate 32bit and 64bit systems */
				$value = str_replace(' ', '', $value);
				if (strlen($value) <= 8 || ((2147483647+1) == intval(2147483647+1))) {
					$rrd_update_array{$item["rrd_path"]}["times"][$unix_time]{$item["rrd_name"]} = hexdec($value);
				}elseif (function_exists("bcpow")) {
					$dec = 0;
					$vallen = strlen($value);
					for ($i = 1; $i <= $vallen; $i++) {
						$dec = bcadd($dec, bcmul(strval(hexdec($value[$i - 1])), bcpow('16', strval($vallen - $i))));
					}
					$rrd_update_array{$item["rrd_path"]}["times"][$unix_time]{$item["rrd_name"]} = $dec;
				}else{
					$rrd_update_array{$item["rrd_path"]}["times"][$unix_time]{$item["rrd_name"]} = "U";
				}
			/* multiple value output */
			}else{
				$values = explode(" ", $value);

				$rrd_field_names = array_rekey(db_fetch_assoc("select
					data_template_rrd.data_source_name,
					data_input_fields.data_name
					from (data_template_rrd,data_input_fields)
					where data_template_rrd.data_input_field_id=data_input_fields.id
					and data_template_rrd.local_data_id=" . $item["local_data_id"]), "data_name", "data_source_name");

				if (sizeof($values)) {
				foreach($values as $value) {
					$matches = explode(":", $value);

					if (sizeof($matches) == 2) {
						if (isset($rrd_field_names{$matches[0]})) {
							if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG || $debug) {
								cacti_log("Parsed MULTI output field '" . $matches[0] . ":" . $matches[1] . "' [map " . $matches[0] . "->" . $rrd_field_names{$matches[0]} . "]" , true, "POLLER");
							}

							$rrd_update_array{$item["rrd_path"]}["times"][$unix_time]{$rrd_field_names{$matches[0]}} = $matches[1];
						}
					}
				}
				}
			}

			/* fallback values */
			if ((!isset($rrd_update_array{$item["rrd_path"]}["times"][$unix_time])) && ($item["rrd_name"] != "")) {
				$rrd_update_array{$item["rrd_path"]}["times"][$unix_time]{$item["rrd_name"]} = "U";
			}else if ((!isset($rrd_update_array{$item["rrd_path"]}["times"][$unix_time])) && ($item["rrd_name"] == "")) {
				unset($rrd_update_array{$item["rrd_path"]});
			}
		}

		/* make sure each .rrd file has complete data */
		reset($results);
		foreach ($results as $item) {
			$unix_time = strtotime($item["time"]);

			if (isset($rrd_update_array{$item["rrd_path"]}["times"][$unix_time])) {
				if ($item["rrd_num"] <= sizeof($rrd_update_array{$item["rrd_path"]}["times"][$unix_time])) {
					db_execute("delete from poller_output where local_data_id='" . $item["local_data_id"] . "' and rrd_name='" . $item["rrd_name"] . "' and time='" . $item["time"] . "'");
				}else{
					unset($rrd_update_array{$item["rrd_path"]}["times"][$unix_time]);
				}
			}
		}

		$rrds_processed = rrdtool_function_update($rrd_update_array, $rrdtool_pipe);
	}

	return $rrds_processed;
}

?>
