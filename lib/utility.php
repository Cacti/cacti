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
	global $config;
	
	include_once($config["library_path"] . "/data_query.php");
	
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
		host.hostname,
		host.snmp_community,
		host.snmp_version,
		host.snmp_username,
		host.snmp_password,
		host.snmp_port,
		host.snmp_timeout
		from
		data_local,host
		where data_local.host_id=host.id
		and data_local.id=$local_data_id
		and host.disabled=''");
	
	/* we have to perform some additional sql queries if this is a "query" */
	if (($data_input["type_id"] == "3") || ($data_input["type_id"] == "4")) {
		$field = data_query_field_list($data_input["data_template_data_id"]);
		
		if (empty($field)) { return; }
		
		$query = data_query_index($field["index_type"], $field["index_value"], $host["id"]);
		
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
				
				db_execute("delete from data_input_data_fcache where local_data_id=$local_data_id");
				
				/* update the field cache (fcache) */
				$names = db_fetch_assoc("select
					data_template_rrd.data_source_name,
					data_input_fields.data_name
					from data_template_rrd,data_input_fields
					where data_template_rrd.data_input_field_id=data_input_fields.id
					and data_template_rrd.local_data_id=$local_data_id");
				
				if (sizeof($names) > 0) {
				foreach ($names as $name) {
					db_execute("insert into data_input_data_fcache (local_data_id,data_input_field_name,rrd_data_source_name)
						values ($local_data_id,'" . $name["data_name"] . "','" . $name["data_source_name"] . "')");
				}
				}
			}
			
			if ($action_type) {
				db_execute("insert into data_input_data_cache (local_data_id,host_id,data_input_id,action,hostname,
					snmp_community,snmp_version,snmp_timeout,snmp_username,snmp_password,snmp_port,rrd_name,rrd_path,
					command,rrd_num) values ($local_data_id," . (empty($host["id"]) ? 0 : $host["id"]) . "," . $data_input["id"] . ",$action_type,'" . $host["hostname"] . "',
					'" . $host["snmp_community"] . "','" . $host["snmp_version"] . "','" . $host["snmp_timeout"] . "',
					'" . $host["snmp_username"] . "','" . $host["snmp_password"] . "','" . $host["snmp_port"] . "',
					'" . get_data_source_name($data_template_rrd_id) . "',
					'" . addslashes(clean_up_path(get_data_source_path($local_data_id,true))) . "','" . addslashes($command) . "',1)");
			}
			
			break;
		case '2': /* snmp */
			$field = db_fetch_assoc("select
				data_input_fields.type_code,
				data_input_data.value
				from data_input_fields left join data_input_data
				on (data_input_fields.id=data_input_data.data_input_field_id and data_input_data.data_template_data_id=" . $data_input["data_template_data_id"] . ")
				where data_input_fields.type_code='snmp_oid'");
			$field = array_rekey($field, "type_code", "value");
			
			$data_template_rrd_id = db_fetch_cell("select id from data_template_rrd where local_data_id=$local_data_id");
			
			db_execute("insert into data_input_data_cache (local_data_id,host_id,data_input_id,action,hostname,
				snmp_community,snmp_version,snmp_timeout,snmp_username,snmp_password,snmp_port,rrd_name,rrd_path,
				arg1,rrd_num) values ($local_data_id," . (empty($host["id"]) ? 0 : $host["id"]) . "," . $data_input["id"]. ",0,'" . $host["hostname"] . "',
				'" . $host["snmp_community"] . "','" . $host["snmp_version"] . "','" . $host["snmp_timeout"] . "',
				'" . $host["snmp_username"] . "','" . $host["snmp_password"] . "','" . $host["snmp_port"] . "',
				'" . get_data_source_name($data_template_rrd_id) . "',
				'" . addslashes(clean_up_path(get_data_source_path($local_data_id,true,1))) . "','" . $field["snmp_oid"] . "',1)");
			
			break;
		case '3': /* snmp query */
			$snmp_queries = get_data_query_array($query["snmp_query_id"]);
			
			if (sizeof($outputs) > 0) {
			foreach ($outputs as $output) {
				if (isset($snmp_queries["fields"]{$output["snmp_field_name"]}["oid"])) {
					$oid = $snmp_queries["fields"]{$output["snmp_field_name"]}["oid"] . "." . $query["snmp_index"];
				}
				
				if (!empty($oid)) {
					db_execute("insert into data_input_data_cache (local_data_id,host_id,data_input_id,action,hostname,
						snmp_community,snmp_version,snmp_timeout,snmp_username,snmp_password,snmp_port,rrd_name,rrd_path,
						arg1,rrd_num) values ($local_data_id," . (empty($host["id"]) ? 0 : $host["id"]) . "," . $data_input["id"]. ",0,'" . $host["hostname"] . "',
						'" . $host["snmp_community"] . "','" . $host["snmp_version"] . "','" . $host["snmp_timeout"] . "',
						'" . $host["snmp_username"] . "','" . $host["snmp_password"] . "','" . $host["snmp_port"] . "',
						'" . get_data_source_name($output["data_template_rrd_id"]) . "',
						'" . addslashes(clean_up_path(get_data_source_path($local_data_id,true))) . "','$oid'," . sizeof($outputs) . ")");
				}
			}
			}
			
			break;
		case '4': /* script query */
			$script_queries = get_data_query_array($query["snmp_query_id"]);
			
			if (sizeof($outputs) > 0) {
			foreach ($outputs as $output) {
				if (isset($script_queries["fields"]{$output["snmp_field_name"]}["query_name"])) {
					$identifier = $script_queries["fields"]{$output["snmp_field_name"]}["query_name"];
					
					/* get any extra arguments that need to be passed to the script */
					if (!empty($script_queries["arg_prepend"])) {
						$extra_arguments = substitute_host_data($script_queries["arg_prepend"], "|", "|", $host["id"]);
					}else{
						$extra_arguments = "";
					}
					
					/* get a complete path for out target script */
					$script_path = substitute_data_query_path($script_queries["script_path"]);
					$script_path .= " $extra_arguments " . $script_queries["arg_get"] . " " . $identifier . " " . $query["snmp_index"];
				}
			
				if (isset($script_path)) {
					db_execute("insert into data_input_data_cache (local_data_id,host_id,data_input_id,action,hostname,
						snmp_community,snmp_version,snmp_timeout,snmp_username,snmp_password,snmp_port,rrd_name,rrd_path,command,rrd_num) values 
						($local_data_id," . (empty($host["id"]) ? 0 : $host["id"]) . "," . $data_input["id"]. ",1,'" . $host["hostname"] . "',
						'" . $host["snmp_community"] . "','" . $host["snmp_version"] . "','" . $host["snmp_timeout"] . "',
						'" . $host["snmp_username"] . "','" . $host["snmp_password"] . "','" . $host["snmp_port"] . "',
						'" . get_data_source_name($output["data_template_rrd_id"]) . "',
						'" . addslashes(clean_up_path(get_data_source_path($local_data_id,true))) . "','" . addslashes($script_path) . "'," . sizeof($outputs) . ")");
				}
			}
			}
			
			break;
		}
	}
}

function update_graph_snmp_query_cache($local_graph_id) {
	global $config;
	
	include_once($config["library_path"] . "/data_query.php");
	
	$host_id = db_fetch_cell("select host_id from graph_local where id=$local_graph_id");
	
	$field = data_query_field_list(db_fetch_cell("select
		data_template_data.id
		from graph_templates_item,data_template_rrd,data_template_data 
		where graph_templates_item.task_item_id=data_template_rrd.id
		and data_template_rrd.local_data_id=data_template_data.local_data_id
		and graph_templates_item.local_graph_id=$local_graph_id
		limit 0,1"));
	
	if (empty($field)) { return; }
	
	$query = data_query_index($field["index_type"], $field["index_value"], $host_id);
	
	if (($query["snmp_query_id"] != "0") && ($query["snmp_index"] != "")) {
		db_execute("update graph_local set snmp_query_id=" . $query["snmp_query_id"] . ",snmp_index='" . $query["snmp_index"] . "' where id=$local_graph_id");
		
		/* update data source/graph title cache */
		update_data_source_title_cache_from_query($query["snmp_query_id"], $query["snmp_index"]);
		update_graph_title_cache_from_query($query["snmp_query_id"], $query["snmp_index"]);
	}
}

function update_data_source_snmp_query_cache($local_data_id) {
	global $config;
	
	include_once($config["library_path"] . "/data_query.php");
	
	$host_id = db_fetch_cell("select host_id from data_local where id=$local_data_id");
	
	$field = data_query_field_list(db_fetch_cell("select
		data_template_data.id
		from data_template_data 
		where data_template_data.local_data_id=$local_data_id"));
	
	if (empty($field)) { return; }
	
	$query = data_query_index($field["index_type"], $field["index_value"], $host_id);
	
	if (($query["snmp_query_id"] != "0") && ($query["snmp_index"] != "")) {
		db_execute("update data_local set snmp_query_id=" . $query["snmp_query_id"] . ",snmp_index='" . $query["snmp_index"] . "' where id=$local_data_id");
		
		/* update graph title cache */
		update_graph_title_cache_from_query($query["snmp_query_id"], $query["snmp_index"]);
	}
}



function push_out_host($host_id, $local_data_id = 0, $data_template_id = 0) {
	/* ok here's the deal: first we need to find every data source that uses this host.
	then we go through each of those data sources, finding each one using a data input method
	with "special fields". if we find one, fill it will the data here from this host */
	
	if (!empty($data_template_id)) {
		$hosts = db_fetch_assoc("select host_id from data_local where data_template_id=$data_template_id group by host_id");
		
		if (sizeof($hosts) > 0) {
		foreach ($hosts as $host) {
			push_out_host($host["host_id"]);
		}
		}
	}
	
	if (empty($host_id)) { return 0; }
	
	/* get all information about this host so we can write it to the data source */
	$host = db_fetch_row("select * from host where id=$host_id");
	
	$data_sources = db_fetch_assoc("select
		data_template_data.id,
		data_template_data.data_input_id,
		data_template_data.local_data_id,
		data_template_data.local_data_template_data_id
		from data_local,data_template_data
		where " . (empty($local_data_id) ? "data_local.host_id=$host_id" : "data_local.id=$local_data_id") . "
		and data_local.id=data_template_data.local_data_id
		and data_template_data.data_input_id>0");
	
	/* loop through each matching data source */
	if (sizeof($data_sources) > 0) {
	foreach ($data_sources as $data_source) {
		/* get field information from the data template */
		if (!isset($template_fields{$data_source["local_data_template_data_id"]})) {
			$template_fields{$data_source["local_data_template_data_id"]} = db_fetch_assoc("select
				data_input_data.value,
				data_input_data.t_value,
				data_input_fields.id,
				data_input_fields.type_code
				from data_input_fields left join data_input_data
				on (data_input_fields.id=data_input_data.data_input_field_id and data_input_data.data_template_data_id=" . $data_source["local_data_template_data_id"] . ")
				where data_input_fields.data_input_id=" . $data_source["data_input_id"] . "
				and (data_input_data.t_value='' or data_input_data.t_value is null)
				and data_input_fields.input_output='in'");
		}
		
		reset($template_fields{$data_source["local_data_template_data_id"]});
		
		/* loop through each field contained in the data template and push out a host value if:
		 - the field is a valid "host field"
		 - the value of the field is empty
		 - the field is set to 'templated' */
		if (sizeof($template_fields{$data_source["local_data_template_data_id"]})) {
		foreach ($template_fields{$data_source["local_data_template_data_id"]} as $template_field) {
			if ((eregi('^' . VALID_HOST_FIELDS . '$', $template_field["type_code"])) && ($template_field["value"] == "") && ($template_field["t_value"] == "")) {
				db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,value) values (" . $template_field["id"] . "," . $data_source["id"] . ",'" . $host{$template_field["type_code"]} . "')");
			}
		}
		}
		
		/* make sure to update the poller cache as well */
		update_poller_cache($data_source["local_data_id"]);
	}
	}
}

function duplicate_graph($_local_graph_id, $_graph_template_id, $graph_title) {
	global $config;
	
	include($config["include_path"] . "/config_form.php");
	
	if (!empty($_local_graph_id)) {
		$graph_local = db_fetch_row("select * from graph_local where id=$_local_graph_id");
		$graph_template_graph = db_fetch_row("select * from graph_templates_graph where local_graph_id=$_local_graph_id");
		$graph_template_items = db_fetch_assoc("select * from graph_templates_item where local_graph_id=$_local_graph_id");
		
		/* create new entry: graph_local */
		$save["id"] = 0;
		$save["graph_template_id"] = $graph_local["graph_template_id"];
		$save["host_id"] = $graph_local["host_id"];
		$save["snmp_query_id"] = $graph_local["snmp_query_id"];
		$save["snmp_index"] = $graph_local["snmp_index"];
		
		$local_graph_id = sql_save($save, "graph_local");
		
		$graph_template_graph["title"] = str_replace("<graph_title>", $graph_template_graph["title"], $graph_title);
	}elseif (!empty($_graph_template_id)) {
		$graph_template = db_fetch_row("select * from graph_templates where id=$_graph_template_id");
		$graph_template_graph = db_fetch_row("select * from graph_templates_graph where graph_template_id=$_graph_template_id and local_graph_id=0");
		$graph_template_items = db_fetch_assoc("select * from graph_templates_item where graph_template_id=$_graph_template_id and local_graph_id=0");
		$graph_template_inputs = db_fetch_assoc("select * from graph_template_input where graph_template_id=$_graph_template_id");
		
		/* create new entry: graph_templates */
		$save["id"] = 0;
		$save["name"] = str_replace("<template_title>", $graph_template["name"], $graph_title);
		
		$graph_template_id = sql_save($save, "graph_templates");
	}
	
	unset($save);
	reset($struct_graph);
	
	/* create new entry: graph_templates_graph */
	$save["id"] = 0;
	$save["local_graph_id"] = (isset($local_graph_id) ? $local_graph_id : 0);
	$save["local_graph_template_graph_id"] = (isset($graph_template_graph["local_graph_template_graph_id"]) ? $graph_template_graph["local_graph_template_graph_id"] : 0);
	$save["graph_template_id"] = (!empty($_local_graph_id) ? $graph_template_graph["graph_template_id"] : $graph_template_id);
	$save["title_cache"] = $graph_template_graph["title_cache"];
	
	while (list($field, $array) = each($struct_graph)) {
		$save{$field} = $graph_template_graph{$field};
		$save{"t_" . $field} = $graph_template_graph{"t_" . $field};
	}
	
	$graph_templates_graph_id = sql_save($save, "graph_templates_graph");
	
	/* create new entry(s): graph_templates_item */
	if (sizeof($graph_template_items) > 0) {
	foreach ($graph_template_items as $graph_template_item) {
		unset($save);
		reset($struct_graph_item);
		
		$save["id"] = 0;
		$save["local_graph_id"] = (isset($local_graph_id) ? $local_graph_id : 0);
		$save["graph_template_id"] = (!empty($_local_graph_id) ? $graph_template_item["graph_template_id"] : $graph_template_id);
		$save["local_graph_template_item_id"] = (isset($graph_template_item["local_graph_template_item_id"]) ? $graph_template_item["local_graph_template_item_id"] : 0);
		
		while (list($field, $array) = each($struct_graph_item)) {
			$save{$field} = $graph_template_item{$field};
		}
		
		$graph_item_mappings{$graph_template_item["id"]} = sql_save($save, "graph_templates_item");
	}
	}
	
	if (!empty($_graph_template_id)) {
		/* create new entry(s): graph_template_input (graph template only) */
		if (sizeof($graph_template_inputs) > 0) {
		foreach ($graph_template_inputs as $graph_template_input) {
			unset($save);
			
			$save["id"] = 0;
			$save["graph_template_id"] = $graph_template_id;
			$save["name"] = $graph_template_input["name"];
			$save["description"] = $graph_template_input["description"];
			$save["column_name"] = $graph_template_input["column_name"];
			
			$graph_template_input_id = sql_save($save, "graph_template_input");
			
			$graph_template_input_defs = db_fetch_assoc("select * from graph_template_input_defs where graph_template_input_id=" . $graph_template_input["id"]);
			
			/* create new entry(s): graph_template_input_defs (graph template only) */
			if (sizeof($graph_template_input_defs) > 0) {
			foreach ($graph_template_input_defs as $graph_template_input_def) {
				db_execute("insert into graph_template_input_defs (graph_template_input_id,graph_template_item_id)
					values ($graph_template_input_id," . $graph_item_mappings{$graph_template_input_def["graph_template_item_id"]} . ")");
			}
			}
		}
		}
	}
	
	if (!empty($_local_graph_id)) {
		update_graph_title_cache($local_graph_id);
	}
}

function duplicate_data_source($_local_data_id, $_data_template_id, $data_source_title) {
	global $config;
	
	include($config["include_path"] . "/config_form.php");
	
	if (!empty($_local_data_id)) {
		$data_local = db_fetch_row("select * from data_local where id=$_local_data_id");
		$data_template_data = db_fetch_row("select * from data_template_data where local_data_id=$_local_data_id");
		$data_template_rrds = db_fetch_assoc("select * from data_template_rrd where local_data_id=$_local_data_id");
		
		$data_input_datas = db_fetch_assoc("select * from data_input_data where data_template_data_id=" . $data_template_data["id"]);
		$data_template_data_rras = db_fetch_assoc("select * from data_template_data_rra where data_template_data_id=" . $data_template_data["id"]);
		
		/* create new entry: data_local */
		$save["id"] = 0;
		$save["data_template_id"] = $data_local["data_template_id"];
		$save["host_id"] = $data_local["host_id"];
		$save["snmp_query_id"] = $data_local["snmp_query_id"];
		$save["snmp_index"] = $data_local["snmp_index"];
		
		$local_data_id = sql_save($save, "data_local");
		
		$data_template_data["name"] = str_replace("<ds_title>", $data_template_data["name"], $data_source_title);
	}elseif (!empty($_data_template_id)) {
		$data_template = db_fetch_row("select * from data_template where id=$_data_template_id");
		$data_template_data = db_fetch_row("select * from data_template_data where data_template_id=$_data_template_id and local_data_id=0");
		$data_template_rrds = db_fetch_assoc("select * from data_template_rrd where data_template_id=$_data_template_id and local_data_id=0");
		
		$data_input_datas = db_fetch_assoc("select * from data_input_data where data_template_data_id=" . $data_template_data["id"]);
		$data_template_data_rras = db_fetch_assoc("select * from data_template_data_rra where data_template_data_id=" . $data_template_data["id"]);
		
		/* create new entry: data_template */
		$save["id"] = 0;
		$save["name"] = str_replace("<template_title>", $data_template["name"], $data_source_title);
		
		$data_template_id = sql_save($save, "data_template");
	}
	
	unset($save);
	unset($struct_data_source["rra_id"]);
	unset($struct_data_source["data_source_path"]);
	reset($struct_data_source);
	
	/* create new entry: data_template_data */
	$save["id"] = 0;
	$save["local_data_id"] = (isset($local_data_id) ? $local_data_id : 0);
	$save["local_data_template_data_id"] = (isset($data_template_data["local_data_template_data_id"]) ? $data_template_data["local_data_template_data_id"] : 0);
	$save["data_template_id"] = (!empty($_local_data_id) ? $data_template_data["data_template_id"] : $data_template_id);
	$save["name_cache"] = $data_template_data["name_cache"];
	
	while (list($field, $array) = each($struct_data_source)) {
		$save{$field} = $data_template_data{$field};
		
		if ($array["flags"] != "ALWAYSTEMPLATE") {
			$save{"t_" . $field} = $data_template_data{"t_" . $field};
		}
	}
	
	$data_template_data_id = sql_save($save, "data_template_data");
	
	/* create new entry(s): data_template_rrd */
	if (sizeof($data_template_rrds) > 0) {
	foreach ($data_template_rrds as $data_template_rrd) {
		unset($save);
		reset($struct_data_source_item);
		
		$save["id"] = 0;
		$save["local_data_id"] = (isset($local_data_id) ? $local_data_id : 0);
		$save["local_data_template_rrd_id"] = (isset($data_template_rrd["local_data_template_rrd_id"]) ? $data_template_rrd["local_data_template_rrd_id"] : 0);
		$save["data_template_id"] = (!empty($_local_data_id) ? $data_template_rrd["data_template_id"] : $data_template_id);
		
		while (list($field, $array) = each($struct_data_source_item)) {
			$save{$field} = $data_template_rrd{$field};
		}
		
		$data_template_rrd_id = sql_save($save, "data_template_rrd");
	}
	}
	
	/* create new entry(s): data_input_data */
	if (sizeof($data_input_datas) > 0) {
	foreach ($data_input_datas as $data_input_data) {
		$save["data_input_field_id"] = $data_input_data["data_input_field_id"];
		$save["data_template_data_id"] = $data_template_data_id;
		$save["t_value"] = $data_input_data["t_value"];
		$save["value"] = $data_input_data["value"];
		
		db_execute("insert into data_input_data (data_input_field_id,data_template_data_id,t_value,value) values
			(" . $data_input_data["data_input_field_id"] . ",$data_template_data_id,'" . $data_input_data["t_value"] . 
			"','" . $data_input_data["value"] . "')");
	}
	}
	
	/* create new entry(s): data_template_data_rra */
	if (sizeof($data_template_data_rras) > 0) {
	foreach ($data_template_data_rras as $data_template_data_rra) {
		$save["data_template_data_id"] = $data_template_data_id;
		$save["rra_id"] = $data_template_data_rra["rra_id"];
		
		db_execute("insert into data_template_data_rra (data_template_data_id,rra_id) values ($data_template_data_id,
			" . $data_template_data_rra["rra_id"] . ")");
	}
	}
	
	if (!empty($_local_data_id)) {
		update_data_source_title_cache($local_data_id);	
	}
}

?>
