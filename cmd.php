#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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

$polling_items = db_fetch_assoc("select * from data_input_data_cache");

if (sizeof($polling_items) > 0) {
foreach ($polling_items as $item) {
	switch ($item["action"]) {
	case '0': /* snmp */
		$output = cacti_snmp_get($item["hostname"], $item["snmp_community"], $item["arg1"], $item["snmp_version"], $item["snmp_username"], $item["snmp_password"], $item["snmp_port"], $item["snmp_timeout"]);
		print "snmp: " . $item["hostname"] . ":" . $item["snmp_port"] . ", dsname: " . $item["rrd_name"]. ", oid: " . $item["arg1"] . ", value: $output\n";
		
		break;
	case '1': /* one output script */
		$command = $item["command"];
		$output = `$command`;
		print "command: $command, output: $output\n";
		
		$data_input_field = db_fetch_row("select id,update_rra from data_input_fields where data_input_id=" . $item["data_input_id"] . " and input_output='out'");
		
		if ($data_input_field["update_rra"] == "") {
			/* DO NOT write data to rrd; put it in the db instead */
			db_execute("insert into data_input_data (data_input_field_id,data_template_data_id,value)
				values (" . $data_input_field["id"] . "," . db_fetch_cell("select id from data_template_data 
				where local_data_id=" . $item["local_data_id"]) . ",'$output')");
			$item["rrd_name"] = ""; /* no rrd action here */
		}
		
		break;
	case '2': /* multi output script */
		$command = $item["command"];
		$output = `$command`;
		print "MUTLI command: $command, output: $output\n";
		
		$output_array = split(" ", $output);
		
		for ($i=0;($i<count($output_array));$i++) {
			$data_input_field = db_fetch_row("select id,update_rra from data_input_fields where data_name='" . ereg_replace("^([a-zA-Z0-9_-]+):.*$", "\\1", $output_array[$i]) . "' and data_input_id=" . $item["data_input_id"] . " and input_output='out'");
			$rrd_name = db_fetch_cell("select data_source_name from data_template_rrd where local_data_id=" . $item["local_data_id"] . " and data_input_field_id=" . $data_input_field["id"]);
			
			if ($data_input_field["update_rra"] == "on") {
				print "MULTI expansion: found fieldid: " . $data_input_field["id"] . ", found rrdname: $rrd_name, value: " . trim(ereg_replace("^[a-zA-Z0-9_-]+:(.*)$", "\\1", $output_array[$i])) . "\n";
				$update_cache_array{$item["local_data_id"]}{$rrd_name} = trim(ereg_replace("^[a-zA-Z0-9_-]+:(.*)$", "\\1", $output_array[$i]));
			}else{
				/* DO NOT write data to rrd; put it in the db instead */
				db_execute("insert into data_input_data (data_input_field_id,data_template_data_id,value)
					values (" . $data_input_field["id"] . "," . db_fetch_cell("select id from data_template_data 
					where local_data_id=" . $item["local_data_id"]) . ",'" . trim(ereg_replace("^[a-zA-Z0-9_-]+:(.*)$", "\\1", $output_array[$i])) . "')");
			}
		}
		
		break;
	}
	
	
	if (!empty($item["rrd_name"])) {
		$update_cache_array{$item["local_data_id"]}{$item["rrd_name"]} = trim($output);
	}
	
	rrdtool_function_create($item["local_data_id"], false);
}
}

if (isset($update_cache_array)) {
	rrdtool_function_update($update_cache_array);
}else{
	print "There are no items in your poller cache. Make sure you have at least one data source created. If you do, go to 'Utilities', and select 'Clear Poller Cache'.\n";
}

/* insert the current date/time for graphs */
db_execute("replace into settings (name,value) values ('date',NOW())");
print "time: " . (time()-$start) . "\n";
//print_r($update_cache_array);

/* dump static images/html file if user wants it */
graph_export();

?>
