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

function api_poller_cache_item_add($host_id, $host_field_override, $local_data_id, $rrd_step, $poller_action_id, $data_source_item_name, $num_rrd_items, $arg1 = "", $arg2 = "", $arg3 = "") {
	global $cnn_id;
	static $hosts = array();

	if (!isset($hosts[$host_id])) {
	$host = db_fetch_row("select
		host.id,
		host.hostname,
		host.snmp_community,
		host.snmp_version,
		host.snmp_username,
		host.snmp_password,
		host.snmp_auth_protocol,
		host.snmp_priv_passphrase,
		host.snmp_priv_protocol,
		host.snmp_context,
		host.snmp_port,
		host.snmp_timeout,
		host.disabled
		from host
		where host.id=$host_id");

		$hosts[$host_id] = $host;
	} else {
		$host = $hosts[$host_id];
	}

	/* the $host_field_override array can be used to override certain host fields in the poller cache */
	if (isset($host)) {
		$host = array_merge($host, $host_field_override);
	}

	if (isset($host["id"]) || (isset($host_id))) {
		if (isset($host)) {
			if ($host["disabled"] == "on") {
				return;
			}
		} else {
			if ($poller_action_id == 0) {
				return;
			}

			$host["id"] = 0;
			$host["snmp_community"] = "";
			$host["snmp_timeout"] = "";
			$host["snmp_username"] = "";
			$host["snmp_password"] = "";
			$host["snmp_auth_protocol"] = "";
			$host["snmp_priv_passphrase"] = "";
			$host["snmp_priv_protocol"] = "";
			$host["snmp_context"] = "";
			$host["snmp_version"] = "";
			$host["snmp_port"] = "";
			$host["hostname"] = "None";
		}

		if ($poller_action_id == 0) {
			if (($host["snmp_version"] < 1) || ($host["snmp_version"] > 3) ||
				($host["snmp_community"] == "" && $host["snmp_version"] != 3)) {
				return;
			}
		}

		$rrd_next_step = api_poller_get_rrd_next_step($rrd_step, $num_rrd_items);

		return "($local_data_id, " . "0, " . $host["id"] . ", $poller_action_id," . $cnn_id->qstr($host["hostname"]) . ",
			" . $cnn_id->qstr($host["snmp_community"])       . ", " . $cnn_id->qstr($host["snmp_version"])       . ", " . $cnn_id->qstr($host["snmp_timeout"]) . ",
			" . $cnn_id->qstr($host["snmp_username"])        . ", " . $cnn_id->qstr($host["snmp_password"])      . ", " . $cnn_id->qstr($host["snmp_auth_protocol"]) . ",
			" . $cnn_id->qstr($host["snmp_priv_passphrase"]) . ", " . $cnn_id->qstr($host["snmp_priv_protocol"]) . ", " . $cnn_id->qstr($host["snmp_context"]) . ",
			" . $cnn_id->qstr($host["snmp_port"])            . ", " . $cnn_id->qstr($data_source_item_name) . ", "     . $cnn_id->qstr(clean_up_path(get_data_source_path($local_data_id, true))) . ",
			" . $cnn_id->qstr($num_rrd_items) . ", " . $cnn_id->qstr($rrd_step) . ", " . $cnn_id->qstr($rrd_next_step) . ", " . $cnn_id->qstr($arg1) . ", " . $cnn_id->qstr($arg2) . ", " . $cnn_id->qstr($arg3) . ", '1')";
	}
}

function api_poller_get_rrd_next_step($rrd_step=300, $num_rrd_items=1) {
	global $config;

	$poller_interval = read_config_option("poller_interval");
	$rrd_next_step = 0;
	if (($rrd_step != $poller_interval) && (isset($poller_interval))){
		if (!isset($config["rrd_step_counter"])) {
			$rrd_step_counter = read_config_option("rrd_step_counter");
		}else{
			$rrd_step_counter = $config["rrd_step_counter"];
		}

		if ($num_rrd_items == 1) {
			$config["rrd_num_counter"] = 0;
		}else{
			if (!isset($config["rrd_num_counter"])) {
				$config["rrd_num_counter"] = 1;
			}else{
				$config["rrd_num_counter"]++;
			}
		}

		$modulus = $rrd_step / $poller_interval;

		if (($modulus < 1) || ($rrd_step_counter == 0)) {
			$rrd_next_step = 0;
		}else{
			$rrd_next_step = $poller_interval * ($rrd_step_counter % $modulus);
		}

		if ($num_rrd_items == 1) {
			$rrd_step_counter++;
		}else{
			if ($num_rrd_items == $config["rrd_num_counter"]) {
				$rrd_step_counter++;
				$config["rrd_num_counter"] = 0;
			}
		}

		if ($rrd_step_counter >= $modulus) {
			$rrd_step_counter = 0;
		}

		/* save rrd_step_counter */
		$config["rrd_step_counter"] = $rrd_step_counter;
		db_execute("replace into settings (name, value) values ('rrd_step_counter','$rrd_step_counter')");
	}

	return $rrd_next_step;
}
?>
