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

/* api_device_remove - removes a device
   @arg $device_id - the id of the device to remove */
function api_device_remove($device_id) {
	db_execute("delete from host where id=$device_id");
	db_execute("delete from host_graph where host_id=$device_id");
	db_execute("delete from host_snmp_query where host_id=$device_id");
	db_execute("delete from host_snmp_cache where host_id=$device_id");
	db_execute("delete from poller_item where host_id=$device_id");
	db_execute("delete from poller_reindex where host_id=$device_id");
	db_execute("delete from graph_tree_items where host_id=$device_id");

	db_execute("update data_local set host_id=0 where host_id=$device_id");
	db_execute("update graph_local set host_id=0 where host_id=$device_id");
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
	$snmp_username, $snmp_password, $snmp_port, $snmp_timeout, $disabled) {
	/* fetch some cache variables */
	if (empty($id)) {
		$_host_template_id = 0;
	}else{
		$_host_template_id = db_fetch_cell("select host_template_id from host where id=$id");
	}

	$save["id"] = $id;
	$save["host_template_id"] = form_input_validate($host_template_id, "host_template_id", "^[0-9]+$", false, 3);
	$save["description"] = form_input_validate($description, "description", "", false, 3);
	$save["hostname"] = form_input_validate($hostname, "hostname", "", false, 3);
	$save["snmp_community"] = form_input_validate($snmp_community, "snmp_community", "", true, 3);
	$save["snmp_version"] = form_input_validate($snmp_version, "snmp_version", "", true, 3);
	$save["snmp_username"] = form_input_validate($snmp_username, "snmp_username", "", true, 3);
	$save["snmp_password"] = form_input_validate($snmp_password, "snmp_password", "", true, 3);
	$save["snmp_port"] = form_input_validate($snmp_port, "snmp_port", "^[0-9]+$", false, 3);
	$save["snmp_timeout"] = form_input_validate($snmp_timeout, "snmp_timeout", "^[0-9]+$", false, 3);
	$save["disabled"] = form_input_validate($disabled, "disabled", "", true, 3);

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
				db_execute("replace into host_snmp_query (host_id,snmp_query_id,reindex_method) values ($host_id," . $snmp_query["snmp_query_id"] . "," . DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME . ")");

				/* recache snmp data */
				run_data_query($host_id, $snmp_query["snmp_query_id"]);
			}
			}

			$graph_templates = db_fetch_assoc("select graph_template_id from host_template_graph where host_template_id=$host_template_id");

			if (sizeof($graph_templates) > 0) {
			foreach ($graph_templates as $graph_template) {
				db_execute("replace into host_graph (host_id,graph_template_id) values ($host_id," . $graph_template["graph_template_id"] . ")");
			}
			}
		}
	}

	return $host_id;
}

?>