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

include ('include/config.php');
include_once ("include/snmp_functions.php");
include_once ("include/rrd_functions.php");
include_once ("include/functions.php");

$polling_items = db_fetch_assoc("select * from data_input_data_cache");

if (sizeof($polling_items) > 0) {
foreach ($polling_items as $item) {
	switch ($item["action"]) {
	case '0': /* snmp */
		$output = cacti_snmp_get($item["management_ip"], $item["snmp_community"], $item["arg1"], $item["snmp_version"], $item["snmp_username"], $item["snmp_password"]);
		print "snmp: " . $item["management_ip"] . ", dsname: " . $item["rrd_name"]. ", oid: " . $item["arg1"] . ", value: $output\n";
		
		break;
	case '1': /* one output script */
		$command = get_full_script_path($item["local_data_id"]);
		$output = `$command`;
		print "command: $command, output: $output\n";
		
		break;
	case '2': /* multi output script */
		
		break;
	}
	
	$update_cache_array{$item["local_data_id"]}{$item["rrd_name"]} = trim($output);
	
	rrdtool_function_create($item["local_data_id"], false);
}
}

rrdtool_function_update($update_cache_array);
//print_r($update_cache_array);


/* dump static images/html file if user wants it */
if (read_config_option("path_html_export") != "") {
	if (read_config_option("path_html_export_skip") == "1") {
		include("export.php");
	}else{
		if (read_config_option("path_html_export_skip") == read_config_option("path_html_export_ctr")) {
			mysql_query("update settings set value=1 where name=\"path_html_export_ctr\"", $cnn_id);
			include("export.php");
		}else{
			if (read_config_option("path_html_export_ctr") == "") {
				mysql_query("update settings set value=1 where name=\"path_html_export_ctr\"", $cnn_id);
			}else{
				mysql_query("update settings set value=" . (read_config_option("path_html_export_ctr") + 1) . " where name=\"path_html_export_ctr\"", $cnn_id);
			}
		}
	}
}

?>
