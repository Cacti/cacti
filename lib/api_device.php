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

/* api_device_remove - removes a device
   @arg $device_id - the id of the device to remove */
function api_device_remove($device_id) {
	db_execute("delete from host             where id=$device_id");
	db_execute("delete from host_graph       where host_id=$device_id");
	db_execute("delete from host_snmp_query  where host_id=$device_id");
	db_execute("delete from host_snmp_cache  where host_id=$device_id");
	db_execute("delete from poller_item      where host_id=$device_id");
	db_execute("delete from poller_reindex   where host_id=$device_id");
	db_execute("delete from poller_command   where command like '$device_id:%'");
	db_execute("delete from graph_tree_items where host_id=$device_id");

	db_execute("update data_local  set host_id=0 where host_id=$device_id");
	db_execute("update graph_local set host_id=0 where host_id=$device_id");
}

/* api_device_remove_multi - removes multiple devices in one call
   @arg $device_ids - an array of device id's to remove */
function api_device_remove_multi($device_ids) {
	$devices_to_delete = "";
	$i = 0;

	if (sizeof($device_ids)) {
		/* build the list */
		foreach($device_ids as $device_id) {
			if ($i == 0) {
				$devices_to_delete .= $device_id;
			}else{
				$devices_to_delete .= ", " . $device_id;
			}

			/* poller commands go one at a time due to trashy logic */
			db_execute("DELETE FROM poller_item      WHERE host_id=$device_id");
			db_execute("DELETE FROM poller_reindex   WHERE host_id=$device_id");
			db_execute("DELETE FROM poller_command   WHERE command like '$device_id:%'");

			$i++;
		}

		db_execute("DELETE FROM host             WHERE id IN ($devices_to_delete)");
		db_execute("DELETE FROM host_graph       WHERE host_id IN ($devices_to_delete)");
		db_execute("DELETE FROM host_snmp_query  WHERE host_id IN ($devices_to_delete)");
		db_execute("DELETE FROM host_snmp_cache  WHERE host_id IN ($devices_to_delete)");

		db_execute("DELETE FROM graph_tree_items WHERE host_id IN ($devices_to_delete)");

		/* for people who choose to leave data sources around */
		db_execute("UPDATE data_local  SET host_id=0 WHERE host_id IN ($devices_to_delete)");
		db_execute("UPDATE graph_local SET host_id=0 WHERE host_id IN ($devices_to_delete)");

	}
}

/* api_device_dq_remove - removes a device->data query mapping
   @arg $device_id - the id of the device which contains the mapping
   @arg $data_query_id - the id of the data query to remove the mapping for */
function api_device_dq_remove($device_id, $data_query_id) {
	db_execute("delete from host_snmp_cache where snmp_query_id=$data_query_id and host_id=$device_id");
	db_execute("delete from host_snmp_query where snmp_query_id=$data_query_id and host_id=$device_id");
	db_execute("delete from poller_reindex where data_query_id=$data_query_id and host_id=$device_id");
}

/* api_device_gt_remove - removes a device->graph template mapping
   @arg $device_id - the id of the device which contains the mapping
   @arg $graph_template_id - the id of the graph template to remove the mapping for */
function api_device_gt_remove($device_id, $graph_template_id) {
	db_execute("delete from host_graph where graph_template_id=$graph_template_id and host_id=$device_id");
}

function api_device_save($id, $host_template_id, $description, $hostname, $snmp_community, $snmp_version,
	$snmp_username, $snmp_password, $snmp_port, $snmp_timeout, $disabled,
	$availability_method, $ping_method, $ping_port, $ping_timeout, $ping_retries,
	$notes, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $max_oids, $device_threads) {
	global $config;
	include_once($config["base_path"]."/lib/utility.php");
	include_once($config["base_path"]."/lib/variables.php");
	include_once($config["base_path"]."/lib/data_query.php");

	/* fetch some cache variables */
	if (empty($id)) {
		$_host_template_id = 0;
	}else{
		$_host_template_id = db_fetch_cell("select host_template_id from host where id=$id");
	}

	$save["id"]                   = form_input_validate($id, "id", "^[0-9]+$", false, 3);
	$save["host_template_id"]     = form_input_validate($host_template_id, "host_template_id", "^[0-9]+$", false, 3);
	$save["description"]          = form_input_validate($description, "description", "", false, 3);
	$save["hostname"]             = form_input_validate(trim($hostname), "hostname", "", false, 3);
	$save["notes"]                = form_input_validate($notes, "notes", "", true, 3);

	$save["snmp_version"]         = form_input_validate($snmp_version, "snmp_version", "", true, 3);
	$save["snmp_community"]       = form_input_validate($snmp_community, "snmp_community", "", true, 3);

	if ($save["snmp_version"] == 3) {
		$save["snmp_username"]        = form_input_validate($snmp_username, "snmp_username", "", true, 3);
		$save["snmp_password"]        = form_input_validate($snmp_password, "snmp_password", "", true, 3);
		$save["snmp_auth_protocol"]   = form_input_validate($snmp_auth_protocol, "snmp_auth_protocol", "^\[None\]|MD5|SHA$", true, 3);
		$save["snmp_priv_passphrase"] = form_input_validate($snmp_priv_passphrase, "snmp_priv_passphrase", "", true, 3);
		$save["snmp_priv_protocol"]   = form_input_validate($snmp_priv_protocol, "snmp_priv_protocol", "^\[None\]|DES|AES128$", true, 3);
		$save["snmp_context"]         = form_input_validate($snmp_context, "snmp_context", "", true, 3);
	} else {
		$save["snmp_username"]        = "";
		$save["snmp_password"]        = "";
		$save["snmp_auth_protocol"]   = "";
		$save["snmp_priv_passphrase"] = "";
		$save["snmp_priv_protocol"]   = "";
		$save["snmp_context"]         = "";
	}

	$save["snmp_port"]            = form_input_validate($snmp_port, "snmp_port", "^[0-9]+$", false, 3);
	$save["snmp_timeout"]         = form_input_validate($snmp_timeout, "snmp_timeout", "^[0-9]+$", false, 3);

	/* disabled = "on"   => regexp "^on$"
	 * not disabled = "" => no regexp, but allow nulls */
	$save["disabled"]             = form_input_validate($disabled, "disabled", "^on$", true, 3);

	$save["availability_method"]  = form_input_validate($availability_method, "availability_method", "^[0-9]+$", false, 3);
	$save["ping_method"]          = form_input_validate($ping_method, "ping_method", "^[0-9]+$", false, 3);
	$save["ping_port"]            = form_input_validate($ping_port, "ping_port", "^[0-9]+$", true, 3);
	$save["ping_timeout"]         = form_input_validate($ping_timeout, "ping_timeout", "^[0-9]+$", true, 3);
	$save["ping_retries"]         = form_input_validate($ping_retries, "ping_retries", "^[0-9]+$", true, 3);
	$save["max_oids"]             = form_input_validate($max_oids, "max_oids", "^[0-9]+$", true, 3);
	$save["device_threads"]       = form_input_validate($device_threads, "device_threads", "^[0-9]+$", true, 3);

	$save = api_plugin_hook_function('api_device_save', $save);

	$host_id = 0;

	if (!is_error_message()) {
		$host_id = sql_save($save, "host");

		if ($host_id) {
			raise_message(1);

			/* push out relavant fields to data sources using this host */
			push_out_host($host_id, 0);

			/* the host substitution cache is now stale; purge it */
			kill_session_var("sess_host_cache_array");

			/* update title cache for graph and data source */
			update_data_source_title_cache_from_host($host_id);
			update_graph_title_cache_from_host($host_id);
		}else{
			raise_message(2);
		}

		/* if the user changes the host template, add each snmp query associated with it */
		if (($host_template_id != $_host_template_id) && (!empty($host_template_id))) {
			$snmp_queries = db_fetch_assoc("select snmp_query_id from host_template_snmp_query where host_template_id=$host_template_id");

			if (sizeof($snmp_queries) > 0) {
			foreach ($snmp_queries as $snmp_query) {
				db_execute("replace into host_snmp_query (host_id,snmp_query_id,reindex_method) values ($host_id," . $snmp_query["snmp_query_id"] . "," . read_config_option("reindex_method") . ")");

				/* recache snmp data */
				run_data_query($host_id, $snmp_query["snmp_query_id"]);
			}
			}

			$graph_templates = db_fetch_assoc("select graph_template_id from host_template_graph where host_template_id=$host_template_id");

			if (sizeof($graph_templates) > 0) {
			foreach ($graph_templates as $graph_template) {
				db_execute("replace into host_graph (host_id,graph_template_id) values ($host_id," . $graph_template["graph_template_id"] . ")");
				api_plugin_hook_function('add_graph_template_to_host', array("host_id" => $host_id, "graph_template_id" => $graph_template["graph_template_id"]));
			}
			}
		}
	}

	# now that we have the id of the new host, we may plugin postprocessing code
	$save["id"] = $host_id;
	api_plugin_hook_function('api_device_new', $save);

	return $host_id;
}

?>
