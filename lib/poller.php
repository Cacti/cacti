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
 | This program is distributed in the hope that it will be useful,
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

/* exec_poll - executes a command and returns its output
   @arg $command - the command to execute
   @returns - the output of $command after execution */
function exec_poll($command) {
	return `$command`;
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
	/* execute using php process */
	if ($using_proc_function == 1) {
		if (is_resource($proc_fd)) {
			/* $pipes now looks like this:
			 * 0 => writeable handle connected to child stdin
			 * 1 => readable handle connected to child stdout
			 * 2 => any error output will be sent to child stderr */

			/* send command to the php server */
			fwrite($pipes[0], $command . "\r\n");

			/* get result from server */
			$output = fgets($pipes[1], 1024);

			if (substr_count($output, "ERROR") > 0) {
				$output = "U";
			}
		}
	/* execute the old fashion way */
	}else{
		$command = read_config_option("path_php_binary") . " " . $command;
		$output = `$command`;
	}

	return $output;
}

/* exec_background - executes a program in the background so that php can continue
     to execute code in the foreground
   @arg $filename - the full pathname to the script to execute
   @arg $args - any additional arguments that must be passed onto the executable */
function exec_background($filename, $args = "") {
	global $config;

	if (read_config_option("log_verbosity") == POLLER_VERBOSITY_DEBUG) {
		cacti_log("POLLER: About to Spawn a Remote Process\n");
	}

	if (file_exists($filename)) {
		if ($config["cacti_server_os"] == "win32") {
			pclose(popen("start \"Cactiplus\" /I /B \"" . $filename . "\" " . $args, "r"));
		}else{
			exec($filename . " " . $args . " > /dev/null &");
		}
	}
}

/* update_reindex_cache - builds a cache that is used by the poller to determine if the
     indexes for a particular data query/host have changed
   @arg $host_id - the id of the host to which the data query belongs
   @arg $data_query_id - the id of the data query to rebuild the reindex cache for */
function update_reindex_cache($host_id, $data_query_id) {
	global $config;

	include_once($config["library_path"] . "/data_query.php");

	/* will be used to keep track of sql statements to execute later on */
	$recache_stack = array();

	$data_query = db_fetch_row("select reindex_method,sort_field from host_snmp_query where host_id=$host_id and snmp_query_id=$data_query_id");
	$data_query_type = db_fetch_cell("select data_input.type_id from data_input,snmp_query where data_input.id=snmp_query.data_input_id and snmp_query.id=$data_query_id");
	$data_query_xml = get_data_query_array($data_query_id);

	switch ($data_query["reindex_method"]) {
		case DATA_QUERY_AUTOINDEX_NONE:
			break;
		case DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME:
			$assert_value = db_fetch_cell("select assert_value from poller_reindex where host_id=$host_id and data_query_id=$data_query_id and arg1='.1.3.6.1.2.1.1.3.0'");

			/* default uptime */
			if (empty($assert_value)) {
				$assert_value = time();
			}

			array_push($recache_stack, "insert into poller_reindex (host_id,data_query_id,action,op,assert_value,arg1) values ($host_id,$data_query_id,0,'<','$assert_value','.1.3.6.1.2.1.1.3.0')");

			break;
		case DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE:
			/* this method requires that some command/oid can be used to determine the
			 * current number of indexes in the data query */
			$assert_value = sizeof(db_fetch_assoc("select snmp_index from host_snmp_cache where host_id=$host_id and snmp_query_id=$data_query_id group by snmp_index"));

			if ($data_query_type == DATA_INPUT_TYPE_SNMP_QUERY) {
				if (isset($data_query_xml["oid_num_indexes"])) {
					array_push($recache_stack, "insert into poller_reindex (host_id,data_query_id,action,op,assert_value,arg1) values ($host_id,$data_query_id,0,'=','$assert_value','" . $data_query_xml["oid_num_indexes"] . "')");
				}
			}else if ($data_query_type == DATA_INPUT_TYPE_SCRIPT_QUERY) {
				if (isset($data_query_xml["arg_num_indexes"])) {
					array_push($recache_stack, "insert into poller_reindex (host_id,data_query_id,action,op,assert_value,arg1) values ($host_id,$data_query_id,1,'=','$assert_value','" . get_script_query_path((isset($data_query_xml["arg_prepend"]) ? $data_query_xml["arg_prepend"] . " ": "") . $data_query_xml["arg_num_indexes"], $data_query_xml["script_path"], $host_id) . "')");
				}
			}

			break;
		case DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION:
			$primary_indexes = db_fetch_assoc("select snmp_index,oid,field_value from host_snmp_cache where host_id=$host_id and snmp_query_id=$data_query_id and field_name='" . $data_query["sort_field"] . "'");

			if (sizeof($primary_indexes) > 0) {
				foreach ($primary_indexes as $index) {
					$assert_value = $index["field_value"];

					if ($data_query_type == DATA_INPUT_TYPE_SNMP_QUERY) {
						array_push($recache_stack, "insert into poller_reindex (host_id,data_query_id,action,op,assert_value,arg1) values ($host_id,$data_query_id,0,'=','$assert_value','" . $data_query_xml["fields"]{$data_query["sort_field"]}["oid"] . "." . $index["snmp_index"] . "')");
					}else if ($data_query_type == DATA_INPUT_TYPE_SCRIPT_QUERY) {
						array_push($recache_stack, "insert into poller_reindex (host_id,data_query_id,action,op,assert_value,arg1) values ($host_id,$data_query_id,1,'=','$assert_value','" . get_script_query_path((isset($data_query_xml["arg_prepend"]) ? $data_query_xml["arg_prepend"] . " ": "") . $data_query_xml["arg_get"] . " " . $data_query_xml["fields"]{$data_query["sort_field"]}["query_name"] . " " . $index["snmp_index"], $data_query_xml["script_path"], $host_id) . "')");
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
   @arg $rrdtool_pipe - the array of pipes containing the file descriptor for rrdtool */
function process_poller_output($rrdtool_pipe) {
	global $config;

	include_once($config["library_path"] . "/rrd.php");

	/* create/update the rrd files */
	$results = db_fetch_assoc("select
		poller_output.output,
		poller_output.time,
		poller_output.local_data_id,
		poller_item.rrd_path,
		poller_item.rrd_name,
		poller_item.rrd_num
		from poller_output,poller_item
		where (poller_output.local_data_id=poller_item.local_data_id and poller_output.rrd_name=poller_item.rrd_name)");

	if (sizeof($results) > 0) {
		/* create an array keyed off of each .rrd file */
		foreach ($results as $item) {
			$rrd_update_array{$item["rrd_path"]}["time"] = strtotime($item["time"]);
			$rrd_update_array{$item["rrd_path"]}["local_data_id"] = $item["local_data_id"];
			$rrd_update_array{$item["rrd_path"]}["items"]{$item["rrd_name"]} = rtrim(strtr(strtr($item["output"],'\r',''),'\n',''));
		}

		/* make sure each .rrd file has complete data */
		reset($results);
		foreach ($results as $item) {
			if (isset($rrd_update_array{$item["rrd_path"]})) {
				if ($item["rrd_num"] <= sizeof($rrd_update_array{$item["rrd_path"]}["items"])) {
					db_execute("delete from poller_output where local_data_id='" . $item["local_data_id"] . "' and rrd_name='" . $item["rrd_name"] . "' and time='" . $item["time"] . "'");
				}else{
					unset($rrd_update_array{$item["rrd_path"]});
				}
			}
		}

		rrdtool_function_update($rrd_update_array, $rrdtool_pipe);
	}
}

?>