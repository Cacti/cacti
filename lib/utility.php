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

function repopulate_poller_cache() {
	db_execute("truncate table data_input_data_cache");
	
	$poller_data = db_fetch_assoc("select id from data_local");
	
	if (sizeof($poller_data) > 0) {
	foreach ($poller_data as $data) {
		update_poller_cache($data["id"]);
	}
	}
}

function update_poller_cache($local_data_id) {
	include_once ("snmp_functions.php");
	include ("config.php");
	
	$data_input = db_fetch_row("select
		data_input.id,
		data_input.type_id,
		data_template_data.id as data_template_data_id,
		data_template_data.data_template_id,
		data_template_data.active
		from data_template_data,data_input
		where data_template_data.data_input_id=data_input.id
		and data_template_data.local_data_id=$local_data_id");
	
	$host = db_fetch_row("select
		host.id,
		host.management_ip,
		host.snmp_community,
		host.snmp_version,
		host.snmp_username,
		host.snmp_password
		from
		data_local,host
		where data_local.host_id=host.id
		and data_local.id=$local_data_id");
	
	/* we have to perform some additional sql queries if this is a "query" */
	if (($data_input["type_id"] == "3") || ($data_input["type_id"] == "4")) {
		$field = db_fetch_assoc("select
			data_input_fields.type_code,
			data_input_data.value
			from data_input_fields,data_input_data
			where data_input_fields.id=data_input_data.data_input_field_id
			and data_input_data.data_template_data_id=" . $data_input["data_template_data_id"] . "
			and (data_input_fields.type_code='index_type' or data_input_fields.type_code='index_value' or data_input_fields.type_code='output_type')");
		$field = array_rekey($field, "type_code", "value");
		
		$query = db_fetch_row("select
			host_snmp_cache.snmp_query_id,
			host_snmp_cache.snmp_index
			from host_snmp_cache
			where host_snmp_cache.field_name='" . $field["index_type"] . "'
			and host_snmp_cache.field_value='" . $field["index_value"] . "'
			and host_snmp_cache.host_id=" . $host["id"]);
		
		$outputs = db_fetch_assoc("select
			snmp_query_graph_rrd.snmp_field_name,
			data_template_rrd.id as data_template_rrd_id
			from snmp_query_graph_rrd,data_template_rrd
			where snmp_query_graph_rrd.data_template_rrd_id=data_template_rrd.local_data_template_rrd_id
			and snmp_query_graph_rrd.snmp_query_graph_id=" . $field["output_type"] . "
			and snmp_query_graph_rrd.data_template_id=" . $data_input["data_template_id"] . "
			and data_template_rrd.local_data_id=$local_data_id");
	}
	
	/* clear cache for this local_data_id */
	db_execute("delete from data_input_data_cache where local_data_id=$local_data_id");
	
	if ($data_input["active"] == "on") {
		switch ($data_input["type_id"]) {
		case '1': /* script */
			$action_type = 0;
			$data_template_rrd_id = 0;
			
			$command = get_full_script_path($local_data_id);
			
			$num_output_fields = sizeof(db_fetch_assoc("select id from data_input_fields where data_input_id=" . $data_input["id"] . " and input_output='out'"));
			
			if ($num_output_fields == 1) {
				$action_type = 1; /* one ds */
				
				$data_template_rrd = db_fetch_assoc("select id from data_template_rrd where local_data_id=$local_data_id");
				$data_template_rrd_id = $data_template_rrd[0]["id"];
			}elseif ($num_output_fields > 1) {
				$action_type = 2; /* >= two ds */
			}
			
			if ($action_type) {
				db_execute("insert into data_input_data_cache (local_data_id,data_input_id,action,management_ip,
					snmp_community,snmp_version,snmp_username,snmp_password,rrd_name,rrd_path,
					command) values ($local_data_id," . $data_input["id"] . ",$action_type,'" . $host["management_ip"] . "',
					'" . $host["snmp_community"] . "','" . $host["snmp_version"] . "',
					'" . $host["snmp_username"] . "','" . $host["snmp_password"] . "',
					'" . get_data_source_name($data_template_rrd_id) . "',
					'" . get_data_source_path($local_data_id,true) . "','$command')");
			}
			
			break;
		case '2': /* snmp */
			$field = db_fetch_assoc("select
				data_input_fields.type_code,
				data_input_data.value
				from data_input_fields,data_input_data
				where data_input_fields.id=data_input_data.data_input_field_id
				and data_input_data.data_template_data_id=" . $data_input["data_template_data_id"] . "
				and (data_input_fields.type_code='snmp_oid')");
			$field = array_rekey($field, "type_code", "value");
			
			$data_template_rrd_id = db_fetch_cell("select id from data_template_rrd where local_data_id=$local_data_id");
			
			db_execute("insert into data_input_data_cache (local_data_id,data_input_id,action,management_ip,
				snmp_community,snmp_version,snmp_username,snmp_password,rrd_name,rrd_path,
				arg1) values ($local_data_id," . $data_input["id"]. ",0,'" . $host["management_ip"] . "',
				'" . $host["snmp_community"] . "','" . $host["snmp_version"] . "',
				'" . $host["snmp_username"] . "','" . $host["snmp_password"] . "',
				'" . get_data_source_name($data_template_rrd_id) . "',
				'" . get_data_source_path($local_data_id,true) . "','" . $field["snmp_oid"] . "')");
			
			break;
		case '3': /* snmp query */
			$snmp_queries = get_data_query_array($query["snmp_query_id"]);
			
			if (sizeof($outputs) > 0) {
			foreach ($outputs as $output) {
				if (isset($snmp_queries["fields"][0]{$output["snmp_field_name"]}[0]["oid"])) {
					$oid = $snmp_queries["fields"][0]{$output["snmp_field_name"]}[0]["oid"] . "." . $query["snmp_index"];
				}
				
				if (!empty($oid)) {
					db_execute("insert into data_input_data_cache (local_data_id,data_input_id,action,management_ip,
						snmp_community,snmp_version,snmp_username,snmp_password,rrd_name,rrd_path,
						arg1) values ($local_data_id," . $data_input["id"]. ",0,'" . $host["management_ip"] . "',
						'" . $host["snmp_community"] . "','" . $host["snmp_version"] . "',
						'" . $host["snmp_username"] . "','" . $host["snmp_password"] . "',
						'" . get_data_source_name($output["data_template_rrd_id"]) . "',
						'" . get_data_source_path($local_data_id,true) . "','$oid')");
				}
			}
			}
			
			break;
		case '4': /* script query */
			$script_queries = get_data_query_array($query["snmp_query_id"]);
			
			if (sizeof($outputs) > 0) {
			foreach ($outputs as $output) {
				if (isset($script_queries["fields"][0]{$output["snmp_field_name"]}[0]["query_name"])) {
					$identifier = $script_queries["fields"][0]{$output["snmp_field_name"]}[0]["query_name"];
					
					/* get a complete path for out target script */
					$script_path = str_replace("|path_cacti|", $paths["cacti"], $script_queries["script_path"]) . " " . $script_queries["arg_get"] . " " . $identifier . " " . $query["snmp_index"];
				}
				
				if (isset($script_path)) {
					db_execute("insert into data_input_data_cache (local_data_id,data_input_id,action,management_ip,
						snmp_community,snmp_version,snmp_username,snmp_password,rrd_name,rrd_path,command) values 
						($local_data_id," . $data_input["id"]. ",1,'" . $host["management_ip"] . "',
						'" . $host["snmp_community"] . "','" . $host["snmp_version"] . "',
						'" . $host["snmp_username"] . "','" . $host["snmp_password"] . "',
						'" . get_data_source_name($output["data_template_rrd_id"]) . "',
						'" . get_data_source_path($local_data_id,true) . "','$script_path')");
				}
			}
			}
			
			break;
		}
	}
}

function push_out_data_template($data_template_id) {
	/* get data_input_id */
	$data_input_id = db_fetch_cell("select
		data_input_id
		from data_template_data
		where id=$data_template_id");
	
	/* must be a data template */
	if ((empty($data_template_id)) || (empty($data_input_id))) { return 0; }
	
	/* get a list of data sources using this template */
	$data_sources = db_fetch_assoc("select
		data_template_data.id
		from data_template_data
		where data_template_id=$data_template_id
		and local_data_id>0");
	
	/* pull out all 'input' values so we know how much to save */
	$input_fields = db_fetch_assoc("select
		data_input_fields.id,
		data_input_data.value,
		data_input_data.t_value
		from data_input_fields left join data_input_data
		on data_input_fields.id=data_input_data.data_input_field_id
		where data_input_data.data_template_data_id=$data_template_id
		and data_input_fields.input_output='in'
		and (data_input_fields.type_code = '' or data_input_fields.type_code is null)");
	
	if (sizeof($data_sources) > 0) {
	foreach ($data_sources as $data_source) {
		reset($input_fields);
		
		if (sizeof($input_fields) > 0) {
		foreach ($input_fields as $input_field) {
			if (empty($input_field["t_value"])) { /* template this value */
				db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,t_value,value) values (" . $input_field["id"] . "," . $data_source["id"] . ",'','" . $input_field["value"] . "')");
			}else{
				db_execute("update data_input_data set t_value='on' where data_input_field_id=" . $input_field["id"] . " and data_template_data_id=" . $data_source["id"]);
			}
		}
		}
	}
	}	
}

function push_out_data_source_item($data_template_rrd_id) {
	include ("config_arrays.php");
	include_once ("functions.php");
	
	/* get information about this data template */
	$data_template_rrd = db_fetch_row("select * from data_template_rrd where id=$data_template_rrd_id");
	
	/* must be a data template */
	if (empty($data_template_rrd["data_template_id"])) { return 0; }
	
	/* loop through each data source column name (from the above array) */
	while (list($field_name, $field_array) = each($struct_data_source_item)) {
		/* are we allowed to push out the column? */
		if (((empty($data_template_rrd{"t_" . $field_name})) || (ereg("FORCE:", $field_name))) && ((isset($data_template_rrd{"t_" . $field_name})) && (isset($data_template_rrd[$field_name])))) {
			db_execute("update data_template_rrd set $field_name='" . $data_template_rrd[$field_name] . "' where local_data_template_rrd_id=" . $data_template_rrd["id"]); 
		}
	}
}

function push_out_data_source($data_template_data_id) {
	include ("config_arrays.php");
	include_once ("functions.php");
	
	/* get information about this data template */
	$data_template_data = db_fetch_row("select * from data_template_data where id=$data_template_data_id");
	
	/* must be a data template */
	if (empty($data_template_data["data_template_id"])) { return 0; }
	
	/* loop through each data source column name (from the above array) */
	while (list($field_name, $field_array) = each($struct_data_source)) {
		/* are we allowed to push out the column? */
		if (((empty($data_template_data{"t_" . $field_name})) || (ereg("FORCE:", $field_name))) && ((isset($data_template_data{"t_" . $field_name})) && (isset($data_template_data[$field_name])))) {
			db_execute("update data_template_data set $field_name='" . $data_template_data[$field_name] . "' where local_data_template_data_id=" . $data_template_data["id"]); 
		}
	}
}

function push_out_host($host_id, $local_data_id = 0) {
	/* ok here's the deal: first we need to find every data source that uses this host.
	then we go through each of those data sources, finding each one using a data input method
	with "special fields". if we find one, fill it will the data here from this host */
	
	if (empty($host_id)) { return 0; }
	
	/* get all information about this host so we can write it to the data source */
	$host = db_fetch_row("select hostname,management_ip,snmp_community,snmp_username,snmp_password,snmp_version from host where id=$host_id");
	
	$data_sources = db_fetch_assoc("select
		data_template_data.id,
		data_template_data.data_input_id,
		data_template_data.local_data_id
		from data_local,data_template_data
		where " . (empty($local_data_id) ? "data_local.host_id=$host_id" : "data_local.id=$local_data_id") . "
		and data_local.id=data_template_data.local_data_id
		and data_template_data.data_input_id>0");
	
	/* loop through each matching data source */
	if (sizeof($data_sources) > 0) {
	foreach ($data_sources as $data_source) {
		$input_fields = db_fetch_assoc("select
			data_input_fields.id,
			data_input_fields.type_code
			from data_input_fields
			where data_input_fields.data_input_id=" . $data_source["data_input_id"] . "
			and data_input_fields.input_output='in'
			and data_input_fields.type_code!=''");
		
		/* loop through each matching field (must be special field) */
		if (sizeof($input_fields) > 0) {
		foreach ($input_fields as $input_field) {
			/* fetch the appropriate data from this host based on the 'type_code'
			  -- note: the type code name comes straight from the column names of the 'host'
			           table, just fyi */
			
			/* make sure it is HOST related type code */
			if (eregi('^(hostname|management_ip|snmp_community|snmp_username|snmp_password|snmp_version)$', $input_field["type_code"])) {
				db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,value) values (" . $input_field["id"] . "," . $data_source["id"] . ",'" . $host{$input_field["type_code"]} . "')");
			}
		}
		}
		
		/* make sure to update the poller cache as well */
		update_poller_cache($data_source["local_data_id"]);
	}
	}
}

function change_data_template($local_data_id, $data_template_id) {
	include("config_arrays.php");
	
	/* always update tables to new data template (or no data template) */
	db_execute("update data_template_data set data_template_id=$data_template_id where local_data_id=$local_data_id");
	db_execute("update data_template_rrd set data_template_id=$data_template_id where local_data_id=$local_data_id");
	db_execute("update data_local set data_template_id=$data_template_id where id=$local_data_id");
	
	/* get data about the template and the data source */
	$data = db_fetch_row("select * from data_template_data where local_data_id=$local_data_id");
	$template_data = db_fetch_row("select * from data_template_data where local_data_id=0 and data_template_id=$data_template_id");
	
	/* determine if we are here for the first time, or coming back */
	if (db_fetch_cell("select local_data_template_data_id from data_template_data where local_data_id=$local_data_id") == "0") {
		$new_save = true;
	}else{
		$new_save = false;
	}
	
	/* make sure the 'local_data_template_data_id' column is set */
	$local_data_template_data_id = db_fetch_cell("select id from data_template_data where data_template_id=$data_template_id and data_template_id=id");
	
	if ($local_data_template_data_id == "") { $local_data_template_data_id = 0; }
	db_execute("update data_template_data set local_data_template_data_id=$local_data_template_data_id where local_data_id=$local_data_id");
	
	/* some basic field values that ALL data sources should have */
	$save["id"] = $data["id"];
	$save["local_data_template_data_id"] = $template_data["id"];
	$save["local_data_id"] = $local_data_id;
	$save["data_template_id"] = $data_template_id;
	
	/* loop through the "templated field names" to find to the rest... */
	while (list($field_name, $field_array) = each($struct_data_source)) {
		if ($field_array["type"] != "custom") {
			if (!empty($template_data{"t_" . $field_name})) {
				$save[$field_name] = $data[$field_name];
			}else{
				$save[$field_name] = $template_data[$field_name];
			}
		}
	}
	
	/* these fields should never be overwritten by the template */
	$save["data_source_path"] = $data["data_source_path"];
	
	//print "<pre>";print_r($save);print "</pre>";
	$data_template_data_id = sql_save($save, "data_template_data");
	
	$data_rrds_list = db_fetch_assoc("select * from data_template_rrd where local_data_id=$local_data_id");
	$template_rrds_list = db_fetch_assoc("select * from data_template_rrd where local_data_id=0 and data_template_id=$data_template_id");
	
	if (sizeof($data_rrds_list) > 0) {
		/* this data source already has "child" items */
	}else{
		/* this data source does NOT have "child" items; loop through each item in the template
		and write it exactly to each item */
		if (sizeof($template_rrds_list) > 0) {
		foreach ($template_rrds_list as $template_rrd) {
			unset($save);
			reset($struct_data_source_item);
			
			$save["id"] = 0;
			$save["local_data_template_rrd_id"] = $template_rrd["id"];
			$save["local_data_id"] = $local_data_id;
			$save["data_template_id"] = $template_rrd["data_template_id"];
			
			while (list($field_name, $field_array) = each($struct_data_source_item)) {
				$save[$field_name] = $template_rrd[$field_name];
			}
			
			//print "<pre>";print_r($save);print "</pre>";
			sql_save($save, "data_template_rrd");
		}
		}
	}
	
	/* make sure to copy down script data (data_input_data) as well */
	$data_input_data = db_fetch_assoc("select data_input_field_id,t_value,value from data_input_data where data_template_data_id=" . $template_data["id"]);
	
	/* this section is before most everthing else so we can determine if this is a new save, by checking
	the status of the 'local_data_template_data_id' column */
	if (sizeof($data_input_data) > 0) {
	foreach ($data_input_data as $item) {
		/* always propagate on a new save, only propagate templated fields thereafter */
		if (($new_save == true) || (empty($item["t_value"]))) {
			db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,t_value,value) values (" . $item["data_input_field_id"] . ",$data_template_data_id,'" . $item["t_value"] . "','" . $item["value"] . "')");
		}
	}
	}
	
	/* make sure to update the 'data_template_data_rra' table for each data source */
	$data_rra = db_fetch_assoc("select rra_id from data_template_data_rra where data_template_data_id=" . $template_data["id"]);
	db_execute("delete from data_template_data_rra where data_template_data_id=$data_template_data_id");
	
	if (sizeof($data_rra) > 0) {
	foreach ($data_rra as $rra) {
		db_execute("insert into data_template_data_rra (data_template_data_id,rra_id) values ($data_template_data_id," . $rra["rra_id"] . ")");
	}
	}
	
	/* find out if there is a host and a host template involved, if there is... push out the 
	host template's settings */
	$host_id = db_fetch_cell("select host_id from data_local where id=$local_data_id");
	
	if ($host_id != "0") {
		$host_template_id = db_fetch_cell("select host_template_id from host where id=$host_id");
		if ($host_template_id != "0") {
			//push_out_host_template($host_template_id, $data_template_id);
		}
	}
}

/* propagates values from the graph template out to each graph using that template */
function push_out_graph($graph_template_graph_id) {
	include ("config_arrays.php");
	include_once ("functions.php");
	
	/* get information about this graph template */
	$graph_template_graph = db_fetch_row("select * from graph_templates_graph where id=$graph_template_graph_id");
	
	/* must be a graph template */
	if ($graph_template_graph["graph_template_id"] == 0) { return 0; }
	
	/* loop through each graph column name (from the above array) */
	while (list($field_name, $field_array) = each($struct_graph)) {
		/* are we allowed to push out the column? */
		if (empty($graph_template_graph{"t_" . $field_name})) {
			db_execute("update graph_templates_graph set $field_name='$graph_template_graph[$field_name]' where local_graph_template_graph_id=" . $graph_template_graph["id"]); 
		}
	}
}

/* propagates values from the graph template item out to each graph item using that template */
function push_out_graph_item($graph_template_item_id) {
	include ("config_arrays.php");
	include_once ("functions.php");
	
	/* get information about this graph template */
	$graph_template_item = db_fetch_row("select * from graph_templates_item where id=$graph_template_item_id");
	
	/* must be a graph template */
	if ($graph_template_item["graph_template_id"] == 0) { return 0; }
	
	/* this is trickier with graph_items than with the actual graph... we have to make sure not to 
	overright any items covered in the "graph item inputs". the same thing applies to graphs, but
	is easier to detect there (t_* columns). */
	$graph_item_inputs = db_fetch_assoc("select
		graph_template_input.column_name,
		graph_template_input_defs.graph_template_item_id
		from graph_template_input, graph_template_input_defs
		where graph_template_input.graph_template_id=" . $graph_template_item["graph_template_id"] . "
		and graph_template_input.id=graph_template_input_defs.graph_template_input_id
		and graph_template_input_defs.graph_template_item_id=$graph_template_item_id");
	
	$graph_item_inputs = array_rekey($graph_item_inputs, "column_name", "graph_template_item_id");
	
	/* loop through each graph item column name (from the above array) */
	while (list($field_name, $field_array) = each($struct_graph_item)) {
		/* are we allowed to push out the column? */
		if (!isset($graph_item_inputs[$field_name])) {
			db_execute("update graph_templates_item set $field_name='$graph_template_item[$field_name]' where local_graph_template_item_id=" . $graph_template_item["id"]); 
		}
	}
}

function change_graph_template($local_graph_id, $graph_template_id, $intrusive) {
	include("config_arrays.php");
	
	/* always update tables to new graph template (or no graph template) */
	db_execute("update graph_templates_graph set graph_template_id=$graph_template_id where local_graph_id=$local_graph_id");
	db_execute("update graph_templates_item set graph_template_id=$graph_template_id where local_graph_id=$local_graph_id");
	db_execute("update graph_local set graph_template_id=$graph_template_id where id=$local_graph_id");
	
	/* make sure the 'local_graph_template_graph_id' column is set */
	$local_graph_template_graph_id = db_fetch_cell("select id from graph_templates_graph where graph_template_id=$graph_template_id and graph_template_id=id");
	
	if ($local_graph_template_graph_id == "") { $local_graph_template_graph_id = 0; }
	db_execute("update graph_templates_graph set local_graph_template_graph_id=$local_graph_template_graph_id where local_graph_id=$local_graph_id");
	
	/* if the user turned off the template for this graph; there is nothing more
	to do here */
	if ($graph_template_id == "0") { return 0; }
	
	/* get information about both the graph and the graph template we're using */
	$graph_list = db_fetch_row("select * from graph_templates_graph where local_graph_id=$local_graph_id");
	$template_graph_list = db_fetch_row("select * from graph_templates_graph where local_graph_id=0 and graph_template_id=$graph_template_id");
	
	/* some basic field values that ALL graphs should have */
	$save["id"] = $graph_list["id"];
	$save["local_graph_template_graph_id"] = $template_graph_list["id"];
	$save["local_graph_id"] = $local_graph_id;
	$save["graph_template_id"] = $graph_template_id;
	
	/* loop through the "templated field names" to find to the rest... */
	while (list($field_name, $field_array) = each($struct_graph)) {
		$value_type = "t_$field_name";
		
		if ($template_graph_list[$value_type] == "on") { $save[$field_name] = $graph_list[$field_name]; }else{ $save[$field_name] = $template_graph_list[$field_name]; }
	}
	
	//print "<pre>";print_r($save);print "</pre>";
	sql_save($save, "graph_templates_graph");
	
	$graph_items_list = db_fetch_assoc("select * from graph_templates_item where local_graph_id=$local_graph_id order by sequence");
	$template_items_list = db_fetch_assoc("select * from graph_templates_item where local_graph_id=0 and graph_template_id=$graph_template_id order by sequence");
	
	$graph_template_inputs = db_fetch_assoc("select
		graph_template_input.column_name,
		graph_template_input_defs.graph_template_item_id
		from graph_template_input,graph_template_input_defs
		where graph_template_input.id=graph_template_input_defs.graph_template_input_id
		and graph_template_input.graph_template_id=$graph_template_id");
	
	$k=0;
	if (sizeof($template_items_list) > 0) {
	foreach ($template_items_list as $template_item) {
		unset($save);
		reset($struct_graph_item);
		
		$save["local_graph_template_item_id"] = $template_item["id"];
		$save["local_graph_id"] = $local_graph_id;
		$save["graph_template_id"] = $template_item["graph_template_id"];
		
		if (isset($graph_items_list[$k])) {
			/* graph item at this position, "mesh" it in */
			$save["id"] = $graph_items_list[$k]["id"];
			
			/* make a first pass filling in ALL values from template */
			while (list($field_name, $field_array) = each($struct_graph_item)) {
				$save[$field_name] = $template_item[$field_name];
			}
			
			/* go back a second time and fill in the INPUT values from the graph */
			for ($j=0; ($j < count($graph_template_inputs)); $j++) {
				if ($graph_template_inputs[$j]["graph_template_item_id"] == $template_items_list[$k]["id"]) {
					/* if we find out that there is an "input" covering this field/item, use the 
					value from the graph, not the template */
					$graph_item_field_name = $graph_template_inputs[$j]["column_name"];
					$save[$graph_item_field_name] = $graph_items_list[$k][$graph_item_field_name];
				}
			}
		}else{
			/* no graph item at this position, tack it on */
			$save["id"] = 0;
			$save["task_item_id"] = 0;
			
			if ($intrusive == true) {
				while (list($field_name, $field_array) = each($struct_graph_item)) {
					$save[$field_name] = $template_item[$field_name];
				}
			}else{
				unset($save);
			}
			
			
		}
		
		//print "<pre>";print_r($save);print "</pre>";
		if (isset($save)) {
			sql_save($save, "graph_templates_item");
		}
		
		$k++;
	}
	}
	
	/* if there are more graph items then there are items in the template, delete the difference */
	if ((sizeof($graph_items_list) > sizeof($template_items_list)) && ($intrusive == true)) {
		for ($i=(sizeof($graph_items_list) - (sizeof($graph_items_list) - sizeof($template_items_list))); ($i < count($graph_items_list)); $i++) {
			db_execute("delete from graph_templates_item where id=" . $graph_items_list[$i]["id"]);
		}
	}
	
	return true;
}

function graph_to_graph_template($local_graph_id, $graph_title) {
	/* create a new graph template entry */
	db_execute("insert into graph_templates (id,name) values (0,'" . str_replace("<graph_title>", db_fetch_cell("select title from graph_templates_graph where local_graph_id=$local_graph_id"), $graph_title) . "')");
	$graph_template_id = db_fetch_insert_id();
	
	/* update graph to point to the new template */
	db_execute("update graph_templates_graph set local_graph_id=0,local_graph_template_graph_id=0,graph_template_id=$graph_template_id where local_graph_id=$local_graph_id");
	db_execute("update graph_templates_item set local_graph_id=0,local_graph_template_item_id=0,graph_template_id=$graph_template_id where local_graph_id=$local_graph_id");
	
	/* delete the old graph local entry */
	db_execute("delete from graph_local where id=$local_graph_id");
	db_execute("delete from graph_tree_items where local_graph_id=$local_graph_id");
}

function data_source_to_data_template($local_data_id, $data_source_title) {
	/* create a new graph template entry */
	db_execute("insert into data_template (id,name) values (0,'" . str_replace("<ds_title>", db_fetch_cell("select name from data_template_data where local_data_id=$local_data_id"), $data_source_title) . "')");
	$data_template_id = db_fetch_insert_id();
	
	/* update graph to point to the new template */
	db_execute("update data_template_data set local_data_id=0,local_data_template_data_id=0,data_template_id=$data_template_id where local_data_id=$local_data_id");
	db_execute("update data_template_rrd set local_data_id=0,local_graph_template_item_id=0,data_template_id=$data_template_id where local_data_id=$local_data_id");
	
	/* delete the old graph local entry */
	db_execute("delete from data_local where id=$local_data_id");
	db_execute("delete from data_input_data_cache where local_data_id=$local_data_id");
}

function duplicate_graph($_local_graph_id, $graph_title) {
	$graph_local = db_fetch_row("select * from graph_local where id=$_local_graph_id");
	$graph_template_graph = db_fetch_row("select * from graph_templates_graph where local_graph_id=$_local_graph_id");
	$graph_template_items = db_fetch_assoc("select * from graph_templates_item where local_graph_id=$_local_graph_id");
	
	/* create new entry: graph_local */
	$save["id"] = 0;
	$save["graph_template_id"] = $graph_local["graph_template_id"];
	
	$local_graph_id = sql_save($save, "graph_local");
	
	unset($save);
	
	/* create new entry: graph_templates_graph */
	$save["id"] = 0;
	$save["local_graph_id"] = $local_graph_id;
	$save["local_graph_template_graph_id"] = $graph_template_graph["local_graph_template_graph_id"];
	$save["graph_template_id"] = $graph_template_graph["graph_template_id"];
	$save["image_format_id"] = $graph_template_graph["image_format_id"];
	$save["title"] = str_replace("<graph_title>", $graph_template_graph["title"], $graph_title);
	$save["height"] = $graph_template_graph["height"];
	$save["width"] = $graph_template_graph["width"];
	$save["upper_limit"] = $graph_template_graph["upper_limit"];
	$save["lower_limit"] = $graph_template_graph["lower_limit"];
	$save["vertical_label"] = $graph_template_graph["vertical_label"];
	$save["auto_scale"] = $graph_template_graph["auto_scale"];
	$save["auto_scale_opts"] = $graph_template_graph["auto_scale_opts"];
	$save["auto_scale_log"] = $graph_template_graph["auto_scale_log"];
	$save["auto_scale_rigid"] = $graph_template_graph["auto_scale_rigid"];
	$save["auto_padding"] = $graph_template_graph["auto_padding"];
	$save["base_value"] = $graph_template_graph["base_value"];
	$save["export"] = $graph_template_graph["export"];
	$save["unit_value"] = $graph_template_graph["unit_value"];
	$save["unit_exponent_value"] = $graph_template_graph["unit_exponent_value"];

	$graph_templates_graph_id = sql_save($save, "graph_templates_graph");
	
	unset($save);
	
	/* create new entry(s): graph_templates_item */
	if (sizeof($graph_template_items) > 0) {
	foreach ($graph_template_items as $graph_template_item) {
		$save["id"] = 0;
		$save["local_graph_id"] = $local_graph_id;
		$save["graph_template_id"] = $graph_template_item["graph_template_id"];
		$save["local_graph_template_item_id"] = $graph_template_item["local_graph_template_item_id"];
		$save["task_item_id"] = $graph_template_item["task_item_id"];
		$save["color_id"] = $graph_template_item["color_id"];
		$save["graph_type_id"] = $graph_template_item["graph_type_id"];
		$save["cdef_id"] = $graph_template_item["cdef_id"];
		$save["consolidation_function_id"] = $graph_template_item["consolidation_function_id"];
		$save["text_format"] = $graph_template_item["text_format"];
		$save["value"] = $graph_template_item["value"];
		$save["hard_return"] = $graph_template_item["hard_return"];
		$save["gprint_id"] = $graph_template_item["gprint_id"];
		$save["sequence"] = $graph_template_item["sequence"];
		
		$graph_template_item_id = sql_save($save, "graph_templates_item");
	}
	}
}

function duplicate_data_source($_local_data_id, $data_source_title) {
	$data_local = db_fetch_row("select * from data_local where id=$_local_data_id");
	$data_template_data = db_fetch_row("select * from data_template_data where local_data_id=$_local_data_id");
	$data_template_rrds = db_fetch_assoc("select * from data_template_rrd where local_data_id=$_local_data_id");
	
	$data_input_datas = db_fetch_assoc("select * from data_input_data where data_template_data_id=" . $data_template_data["id"]);
	$data_template_data_rras = db_fetch_assoc("select * from data_template_data_rra where data_template_data_id=" . $data_template_data["id"]);
	
	/* create new entry: data_local */
	$save["id"] = 0;
	$save["data_template_id"] = $data_local["data_template_id"];
	$save["host_id"] = $data_local["host_id"];
	
	$local_data_id = sql_save($save, "data_local");
	
	unset($save);
	
	/* create new entry: data_template_data */
	$save["id"] = 0;
	$save["local_data_id"] = $local_data_id;
	$save["local_data_template_data_id"] = $data_template_data["local_data_template_data_id"];
	$save["data_template_id"] = $data_template_data["data_template_id"];
	$save["data_input_id"] = $data_template_data["data_input_id"];
	$save["name"] = str_replace("<ds_title>", $data_template_data["name"], $data_source_title);
	$save["data_source_path"] = $data_template_data["data_source_path"];
	$save["active"] = $data_template_data["active"];
	$save["rrd_step"] = $data_template_data["rrd_step"];
	
	$data_template_data_id = sql_save($save, "data_template_data");
	
	unset($save);
	
	/* create new entry(s): data_template_rrd */
	if (sizeof($data_template_rrds) > 0) {
	foreach ($data_template_rrds as $data_template_rrd) {
		$save["id"] = 0;
		$save["local_data_id"] = $local_data_id;
		$save["local_data_template_rrd_id"] = $data_template_rrd["local_data_template_rrd_id"];
		$save["data_template_id"] = $data_template_rrd["data_template_id"];
		$save["rrd_maximum"] = $data_template_rrd["rrd_maximum"];
		$save["rrd_minimum"] = $data_template_rrd["rrd_minimum"];
		$save["rrd_heartbeat"] = $data_template_rrd["rrd_heartbeat"];
		$save["data_source_type_id"] = $data_template_rrd["data_source_type_id"];
		$save["data_source_name"] = $data_template_rrd["data_source_name"];
		$save["data_input_field_id"] = $data_template_rrd["data_input_field_id"];
		
		$data_template_rrd_id = sql_save($save, "data_template_rrd");
	}
	}
	
	unset($save);
	
	/* create new entry(s): data_input_data */
	if (sizeof($data_input_datas) > 0) {
	foreach ($data_input_datas as $data_input_data) {
		$save["data_input_field_id"] = $data_input_data["data_input_field_id"];
		$save["data_template_data_id"] = $data_template_data_id;
		$save["value"] = $data_input_data["value"];
		
		sql_save($save, "data_input_data");
	}
	}
	
	unset($save);
	
	/* create new entry(s): data_template_data_rra */
	if (sizeof($data_template_data_rras) > 0) {
	foreach ($data_template_data_rras as $data_template_data_rra) {
		$save["data_template_data_id"] = $data_template_data_id;
		$save["rra_id"] = $data_template_data_rra["rra_id"];
		
		sql_save($save, "data_template_data_rra");
	}
	}
}

?>
