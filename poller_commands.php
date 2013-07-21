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

define("MAX_RECACHE_RUNTIME", 296);

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

/* We are not talking to the browser */
$no_http_headers = true;

/* Start Initialization Section */
include(dirname(__FILE__) . "/include/global.php");
include_once($config["base_path"] . "/lib/poller.php");
include_once($config["base_path"] . "/lib/data_query.php");
include_once($config["base_path"] . "/lib/rrd.php");

/* Record Start Time */
list($micro,$seconds) = explode(" ", microtime());
$start = $seconds + $micro;

$poller_commands = db_fetch_assoc("select
	poller_command.action,
	poller_command.command
	from poller_command
	where poller_command.poller_id=0");

$last_host_id = 0;
$first_host = true;
$recached_hosts = 0;

if (sizeof($poller_commands) > 0) {
	foreach ($poller_commands as $command) {
		switch ($command["action"]) {
		case POLLER_COMMAND_REINDEX:
			list($host_id, $data_query_id) = explode(":", $command["command"]);
				if ($last_host_id != $host_id) {
				$last_host_id = $host_id;
				$first_host = true;
				$recached_hosts++;
			} else {
				$first_host = false;
			}

			if ($first_host) {
				cacti_log("Host[$host_id] WARNING: Recache Event Detected for Host", true, "PCOMMAND");
			}

			if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
				cacti_log("Host[$host_id] RECACHE: Recache for Host, data query #$data_query_id", true, "PCOMMAND");
			}

			run_data_query($host_id, $data_query_id);

			if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
				cacti_log("Host[$host_id] RECACHE: Recache successful.", true, "PCOMMAND");
			}
			break;
		default:
			cacti_log("ERROR: Unknown poller command issued", true, "PCOMMAND");
		}

		/* record current_time */
		list($micro,$seconds) = explode(" ", microtime());
		$current = $seconds + $micro;

		/* end if runtime has been exceeded */
		if (($current-$start) > MAX_RECACHE_RUNTIME) {
			cacti_log("ERROR: Poller Command processing timed out after processing '" . $command . "'",true,"PCOMMAND");
			break;
		}
	}

	db_execute("delete from poller_command where poller_id=0");
}

/* take time to log performance data */
list($micro,$seconds) = explode(" ", microtime());
$recache = $seconds + $micro;

$recache_stats = sprintf("RecacheTime:%01.4f HostsRecached:%s",	round($recache - $start, 4), $recached_hosts);

if ($recached_hosts > 0) {
	cacti_log("STATS: " . $recache_stats, true, "RECACHE");
}

/* insert poller stats into the settings table */
db_execute("replace into settings (name,value) values ('stats_recache','$recache_stats')");

?>
