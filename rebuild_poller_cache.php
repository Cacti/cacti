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

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

include(dirname(__FILE__) . "/include/config.php");
include_once("./lib/utility.php");

/* obtain timeout settings */
$max_execution = ini_get("max_execution_time");
$max_memory = ini_get("memory_limit");

ini_set("max_execution_time", "0");
ini_set("memory_limit", "64M");

db_execute("truncate table poller_item");

$poller_data = db_fetch_assoc("select id from data_local");

$current_ds = 1;
$total_ds = sizeof($poller_data);

print "There are '" . sizeof($poller_data) . "' data source elements to update.\n";

if (sizeof($poller_data) > 0) {
	foreach ($poller_data as $data) {
		update_poller_cache($data["id"], true);
		print "Data Source Item '$current_ds' of '$total_ds' updated\n";
		$current_ds++;
	}
}

ini_set("max_execution_time", $max_execution);
ini_set("memory_limit", $max_memory);

?>