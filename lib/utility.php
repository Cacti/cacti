<?/*
   +-------------------------------------------------------------------------+
   | Copyright (C) 2002 Ian Berry                                            |
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
   | cacti: the rrdtool frontend [php-auth, php-tree, php-form]              |
   +-------------------------------------------------------------------------+
   | This code is currently maintained and debugged by Ian Berry, any        |
   | questions or comments regarding this code should be directed to:        |
   | - iberry@raxnet.net                                                     |
   +-------------------------------------------------------------------------+
   | - raXnet - http://www.raxnet.net/                                       |
   +-------------------------------------------------------------------------+
   */?>
<?

function update_poller_cache($local_data_id) {
	global $paths;
	
	$data_input = db_fetch_row("select
		data_input.type_id,
		data_template_data.id as data_template_data_id,
		data_template_data.data_template_id
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
	
	/* clear cache for this local_data_id */
	db_execute("delete from data_input_data_cache where local_data_id=$local_data_id");
	
	switch ($data_input["type_id"]) {
	case '1': /* script */
		$command = get_full_script_path($local_data_id);
		
		db_execute("insert into data_input_data_cache (local_data_id,action,management_ip,
			snmp_community,snmp_version,snmp_username,snmp_password,rrd_name,rrd_path,
			command) values ($local_data_id,1,'" . $host["management_ip"] . "','
			" . $host["snmp_community"] . "','" . $host["snmp_version"] . "','
			" . $host["snmp_username"] . "','" . $host["snmp_password"] . "','
			" . get_data_source_name($output["data_template_rrd_id"]) . "','
			" . get_data_source_path($local_data_id,true) . "','$command')");
		
		break;
	case '2': /* snmp */
		break;
	case '3': /* snmp query */
		$field = db_fetch_assoc("select
			data_input_fields.type_code,
			data_input_data.value
			from data_input_fields,data_input_data
			where data_input_fields.id=data_input_data.data_input_field_id
			and data_input_data.data_template_data_id=" . $data_input["data_template_data_id"] . "
			and (data_input_fields.type_code='index_type' or data_input_fields.type_code='index_value')");
		$field = array_rekey($field, "type_code", "value");
		
		$query = db_fetch_row("select
			host_snmp_cache.snmp_query_id,
			host_snmp_cache.snmp_index
			from host_snmp_cache
			where host_snmp_cache.field_name='" . $field["index_type"] . "'
			and host_snmp_cache.field_value='" . $field["index_value"] . "'
			and host_snmp_cache.host_id=" . $host["id"]);
		
		$outputs = db_fetch_assoc("select
			snmp_query_dt_rrd.snmp_field_name,
			data_template_rrd.id as data_template_rrd_id
			from snmp_query_dt_rrd,data_template_rrd
			where snmp_query_dt_rrd.data_template_rrd_id=data_template_rrd.local_data_template_rrd_id
			and snmp_query_dt_rrd.snmp_query_id=" . $query["snmp_query_id"] . "
			and snmp_query_dt_rrd.data_template_id=" . $data_input["data_template_id"] . "
			and data_template_rrd.local_data_id=$local_data_id");
		
		if (sizeof($outputs) > 0) {
		foreach ($outputs as $output) {
			$oid = db_fetch_cell("select
				oid
				from
				host_snmp_cache
				where host_id=" . $host["id"] . "
				and field_name='" . $output["snmp_field_name"] . "'
				and snmp_index=" . $query["snmp_index"]);
			
			db_execute("insert into data_input_data_cache (local_data_id,action,management_ip,
				snmp_community,snmp_version,snmp_username,snmp_password,rrd_name,rrd_path,
				arg1) values ($local_data_id,1,'" . $host["management_ip"] . "','
				" . $host["snmp_community"] . "','" . $host["snmp_version"] . "','
				" . $host["snmp_username"] . "','" . $host["snmp_password"] . "','
				" . get_data_source_name($output["data_template_rrd_id"]) . "','
				" . get_data_source_path($local_data_id,true) . "','$oid')");
		}
		}
		
		break;
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
	if ($data_template_rrd[data_template_id] == 0) { return 0; }
	
	/* loop through each data source column name (from the above array) */
	while (list($field_name, $field_array) = each($struct_data_source_item)) {
		//$current_name = str_replace("FORCE:", "", $struct_data_source_item[$i]);
		$value_type = "t_$field_name";
		
		/* are we allowed to push out the column? */
		if (($data_template_rrd[$value_type] == "") || (ereg("FORCE:", $field_name))) {
			db_execute("update data_template_rrd set $field_name='$data_template_rrd[$field_name]' where local_data_template_rrd_id=$data_template_rrd[id]"); 
		}
	}
}

function push_out_data_source($data_template_data_id) {
	include ("config_arrays.php");
	include_once ("functions.php");
	
	/* get information about this data template */
	$data_template_data = db_fetch_row("select * from data_template_data where id=$data_template_data_id");
	
	/* must be a data template */
	if ($data_template_data[data_template_id] == 0) { return 0; }
	
	/* loop through each data source column name (from the above array) */
	while (list($field_name, $field_array) = each($struct_data_source)) {
		//$current_name = str_replace("FORCE:", "", $struct_data_source[$i]);
		$value_type = "t_$field_name";
		
		/* are we allowed to push out the column? */
		if (($data_template_data[$value_type] == "") || (ereg("FORCE:", $field_name))) {
			db_execute("update data_template_data set $field_name='$data_template_data[$field_name]' where local_data_template_data_id=$data_template_data[id]"); 
		}
	}
}

function push_out_host($host_id) {
	/* ok here's the deal: first we need to find every data source that uses this host.
	then we go through each of those data sources, finding each one using a data input method
	with "special fields". if we find one, fill it will the data here from this host */
	
	if (empty($host_id)) { return 0; }
	
	$host = db_fetch_row("select hostname,management_ip,snmp_community,snmp_username,snmp_password,snmp_version from host where id=$host_id");
	
	$data_sources = db_fetch_assoc("select
		data_template_data.id,
		data_template_data.data_input_id
		from data_local,data_template_data
		where data_local.host_id=$host_id
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
			if ($template_data{"t_" . $field_name} == "on") { $save[$field_name] = $data[$field_name]; }else{ $save[$field_name] = $template_data[$field_name]; }
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
	if ($graph_template_graph[graph_template_id] == 0) { return 0; }
	
	/* loop through each graph column name (from the above array) */
	while (list($field_name, $field_array) = each($struct_graph)) {
		$value_type = "t_$field_name";
		
		/* are we allowed to push out the column? */
		if ($graph_template_graph[$value_type] == "") {
			db_execute("update graph_templates_graph set $field_name='$graph_template_graph[$field_name]' where local_graph_template_graph_id=$graph_template_graph[id]"); 
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
	if ($graph_template_item[graph_template_id] == 0) { return 0; }
	
	/* this is trickier with graph_items than with the actual graph... we have to make sure not to 
	overright any items covered in the "graph item inputs". the same thing applies to graphs, but
	is easier to detect there (t_* columns). */
	$graph_item_inputs = db_fetch_assoc("select
		graph_template_input.column_name,
		graph_template_input_defs.graph_template_item_id
		from graph_template_input, graph_template_input_defs
		where graph_template_input.graph_template_id=$graph_template_item[graph_template_id]
		and graph_template_input.id=graph_template_input_defs.graph_template_input_id
		and graph_template_input_defs.graph_template_item_id=$graph_template_item_id");
	
	$graph_item_inputs = array_rekey($graph_item_inputs, "column_name", "graph_template_item_id");
	
	/* loop through each graph item column name (from the above array) */
	while (list($field_name, $field_array) = each($struct_graph_item)) {
		/* are we allowed to push out the column? */
		if ($graph_item_inputs[$field_name] != $graph_template_item_id) {
			db_execute("update graph_templates_item set $field_name='$graph_template_item[$field_name]' where local_graph_template_item_id=$graph_template_item[id]"); 
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
	
	print "<pre>";print_r($save);print "</pre>";
	sql_save($save, "graph_templates_graph");
	
	$graph_items_list = db_fetch_assoc("select * from graph_templates_item where local_graph_id=$local_graph_id");
	$template_items_list = db_fetch_assoc("select * from graph_templates_item where local_graph_id=0 and graph_template_id=$graph_template_id");
	
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
				if ($graph_template_inputs[$j]["graph_template_item_id"] == $template_items_list[$i]["id"]) {
					/* if we find out that there is an "input" covering this field/item, use the 
					value from the graph, not the template */
					$graph_item_field_name = $graph_template_inputs[$j]["column_name"];
					$save[$graph_item_field_name] = $graph_items_list[$k][$graph_item_field_name];
				}
			}
		}else{
			/* no graph item at this position, tack it on */
			$save["id"] = 0;
			
			if ($intrusive == true) {
				while (list($field_name, $field_array) = each($struct_graph_item)) {
					$save[$field_name] = $template_item[$field_name];
				}
			}else{
				unset($save);
			}
			
			
		}
		
		print "<pre>";print_r($save);print "</pre>";
		
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

function DuplicateGraph($graph_id) {
    #    global $cnn_id;
    #include_once("include/database.php");
    $original = db_fetch_row("select * from rrd_graph where id=$graph_id");
    $name = "$original[Title] (Copy)";
    
    /* duplicate graph defs */
    $sql = "insert into rrd_graph (id,imageformatid,title,height,width,upperlimit,
				       lowerlimit,verticallabel,autoscale,autopadding,autoscaleopts,rigid,basevalue,grouping,export) 
      values (0,
	      $original[ImageFormatID],\"$name\""
	       . ",$original[Height]"
	       . ", $original[Width]"
	       . ", $original[UpperLimit]" 
	       . ", $original[LowerLimit]"
	       . ",\"$original[VerticalLabel]\""
	       . ",\"$original[AutoScale]\""
	       . ",\"$original[AutoPadding]\""
	       . ",\"$original[AutoScaleOpts]\""
	       . ",\"$original[Rigid]\""
	       . ",\"$original[BaseValue]\""
	       . ",\"$original[Grouping]\""
	       . ",\"$original[Export]\")";
#    print "$sql<BR>\n";
    db_execute($sql);
    
    $graph_grouping = $original[Grouping];
    
    /* duplicate graph items */
    $id = db_fetch_row("select LAST_INSERT_ID() as ID");
    $last_id = $id[ID];
    
    $items = db_fetch_assoc("select * from rrd_graph_item where graphid=$graph_id");
    
    if (sizeof($items) > 0) {
	foreach ($items as $item) {
	    db_execute("insert into rrd_graph_item (id,dsid,colorid,textformat,value,sequence,
						    graphid,graphtypeid,consolidationfunction,hardreturn,cdefid,sequenceparent,parent) 
	      values (0" 
		       . ",\"$item[DSID]\""
		       . ",\"$item[ColorID]\""
		       . ",\"$item[TextFormat]\""
		       . ",\"$item[Value]\""
		       . ",\"$item[Sequence]\""
		       . ",\"" . $last_id . "\""
		       . ",\"$item[GraphTypeID]\""
		       . ",\"$item[ConsolidationFunction]\""
		       . ",\"$item[HardReturn]\""
		       . ",\"$item[CDEFID]\""
		       . ",\"$item[SequenceParent]\""
		       . ",\"$item[Parent]\")");
	}
	
	/* if graph grouping is turned on; we are going to "reset" it by undoing and redoing
	 the graph grouping. This will force each item to get valid parent items instead of
	 pointing to the old graph items */
	if ($graph_grouping == "on") {
	    ungroup_graph_items($last_id);
	    group_graph_items($last_id);
	}
    }
}

function DuplicateDataSource($datasource_id) {
    
    $ds = db_fetch_row("select * from rrd_ds where id=$datasource_id");
    
    $name = "$ds[Name]_copy";
    /* duplicate data source defs */
    db_execute("insert into rrd_ds (id,name,datasourcetypeid,heartbeat,
				    minvalue,maxvalue,srcid,active,dsname,dspath,step) values (0"
	       . ",\"$name\""
	       . ",\"$ds[DatasourceTypeID]\""
	       . ",\"$ds[Heartbeat]\""
	       . ",\"$ds[MinValue]\""
	       . ",\"$ds[MaxValue]\""
	       . ",\"$ds[SrcID]\""
	       . ",\"$ds[Active]\""
	       . ",\"$ds[DSName]\""
	       . ",\"\""
	       . ",\"$ds[Step]\""
	       . ")");
    
    /* duplicate data source data */
    $last_id = db_fetch_cell("select LAST_INSERT_ID() as ID","ID");
    
    $ds_list = db_fetch_assoc("select * from src_data where dsid=$datasource_id");
    
    if (sizeof($ds_list) > 0) {
	foreach ($ds_list as $ds) {
	    db_execute("insert into src_data (id,fieldid,dsid,value) values (0" 
		       . ",\"$ds[FieldID]\""
		       . ",\"$last_id\""
		       . ",\"$ds[Value]\""
		       . ")");
	}
    }
    
    /* duplicate data source -> rra link data */
    $link_ds_list = db_fetch_assoc("select * from lnk_ds_rra where dsid=$datasource_id");
    
    if (sizeof($link_ds_list) > 0) {
	foreach ($link_ds_list as $link_ds) {
	    db_execute("insert into lnk_ds_rra (dsid,rraid) values ($last_id,\"$link_ds[RRAID]\")");
	}
	
	return $last_id;
    }
}

function CreateGraphDataFromSNMPData($graph_parameters) {
    $int = db_fetch_row("select * from snmp_hosts_interfaces where id=" . $graph_parameters["snmp_interface_id"]);
    
    /* ---- Data Source ---- */
    
    /* defaults */
    $ds_datasourcetype = "COUNTER";
    $ds_heartbeat = "600";
    $ds_minvalue = "0";
    $ds_maxvalue = $int[Speed];
    $ds_srctype = "snmp_net";
    $snmp_interface_index = $int[InterfaceNumber];
    $snmp_interface_description = $int[Description];
    
    /* host info */
    $snmp_host = db_fetch_row("select * from snmp_hosts where id=$int[HostID]");
    $snmp_host_hostname = $snmp_host[Hostname];
    $snmp_host_community = $snmp_host[Community];
    
    $ds_datasourcetype = db_fetch_cell("select ID from def_ds where name=\"$ds_datasourcetype\"","ID");
    
    /* get the src_id given the src_type */
    $tmp = db_fetch_row("select ID from src where type=\"$ds_srctype\"");
    
    if (sizeof($tmp) > 0) {
	$ds_srcid = $tmp[ID];
    }
    
    $o = 0;
    while ($o <= 1) {
	switch ($o) {
	 case 0:
	    $ds_direction = "in";
	    break;
	 case 1:
	    $ds_direction = "out";
	    break;
	}
	
	/* set a path for this new data source [RRDPATH/FullDSName_[in|out].rrd] */
	$data_source_path = "<path_rra>/" . strtolower(CleanUpName($graph_parameters["data_source_name"])) . "_" . "$ds_direction.rrd";
	
	$sql_id = db_execute("insert into rrd_ds (name,datasourcetypeid,heartbeat,minvalue,
						   maxvalue,srcid,active,dsname,dspath) values (\"" . $graph_parameters["data_source_name"] . "_" . "$ds_direction\",
																		      $ds_datasourcetype,$ds_heartbeat,$ds_minvalue,$ds_maxvalue,$ds_srcid,\"on\",\"\",\"$data_source_path\")"
			      ,$cnn_id);
	
	/* get newly saved id */
	$datasource_id[$o] = db_fetch_cell("select LAST_INSERT_ID() ID","ID"); /* store both dsid's */
	
	/* this will do any of the cleanup that is required on the data source name to make
	 sure rrdtool is ok with it. */
	SyncDataSourceName($datasource_id[$o], "", $data_source_path);
	
	/* save rra data: use all rra's */
	$rra_list = db_fetch_assoc("select id from rrd_rra");
	if (sizeof($rra_list) > 0) {
	    foreach ($rra_list as $rra) {
		db_execute("insert into lnk_ds_rra (dsid,rraid) values ($datasource_id[$o],$rra[ID])");
	    }
	}
	
	/* now we have to write the acual data for these ds's to start working, this will
	 only work correctly if the user did not tamper with the data names of the
	 "Gather SNMP Network Data" data input source! */
	$fields = db_fetch_assoc("select ID,DataName from src_fields where srcid=$ds_srcid and inputoutput=\"in\"");
	if (sizeof($fields) > 0) {
	    foreach ($fields as $field) {
		$ds_input_value = "";
		/* get values for the input fields that need them */
		switch ($field[DataName]) {
		 case 'inout':
		    $ds_input_value = $ds_direction;
		    break;
		 case 'ip':
		    $ds_input_value = $snmp_host_hostname;
		    break;
		 case 'community':
		    $ds_input_value = $snmp_host_community;
		    break;
		 case 'ifnum':
		    $ds_input_value = $snmp_interface_index;
		    break;
		}
		
		db_execute("insert into src_data (fieldid,dsid,value) values ($field[ID],$datasource_id[$o],\"$ds_input_value\")");
	    }
	}
	$o++;
    }
    /* ---- Graph Data ---- */
	
    /* Graph Title */
    $graph_title = BuildGraphTitleFromSNMP($graph_parameters);
    
    if ($graph_title == "") {
	$graph_title = "Traffic Analysis for $snmp_interface_description";
    }
    
    /* defaults */
    $graph_image_format = "PNG";
    $graph_height = "120";
    $graph_width = "500";
    $graph_vertical_label = "Bytes Per Second";
    $graph_color_in = "00CF00";
    $graph_color_out = "002A97";
    
    $graph_color_in  = db_fetch_cell("select ID from def_colors where hex=\"$graph_color_in\"", "ID");
    $graph_color_out = db_fetch_cell("select ID from def_colors where hex=\"$graph_color_out\"","ID");
    
    $graph_image_format = db_fetch_cell("select ID from def_image_type where name=\"$graph_image_format\"","ID");
    
    db_execute("insert into rrd_graph (imageformatid,title,height,width,upperlimit,
				       lowerlimit,verticallabel,autoscale,autopadding,autoscaleopts,rigid,basevalue,grouping,export) 
      values ($graph_image_format,\"$graph_title\",$graph_height,$graph_width,0,0,
	      \"$graph_vertical_label\",\"on\",\"on\",2,\"on\",1000,\"on\",\"on\")");
    
    /* get newly saved id */
    $graph_id = db_fetch_cell("select LAST_INSERT_ID() ID","ID");
    
    /* this would be a great place to put some code for graph templates, however it is not
     written yet, so its all going to be manual for now */
    
    $graph_hard_return_array[0] = "";
    $graph_hard_return_array[1] = "";
    $graph_hard_return_array[2] = "";
    
    $graph_color_array[1] = "0";
    $graph_color_array[2] = "0";
    $graph_color_array[3] = "0";
    
    $graph_text_format_array[1] = "Current:";
    $graph_text_format_array[2] = "Average:";
    $graph_text_format_array[3] = "Maximum:";
    
    $graph_graph_type_array[1] = "GPRINT";
    $graph_graph_type_array[2] = "GPRINT";
    $graph_graph_type_array[3] = "GPRINT";
    
    $graph_cf_function_array[0] = "AVERAGE";
    $graph_cf_function_array[1] = "LAST";
    $graph_cf_function_array[2] = "AVERAGE";
    $graph_cf_function_array[3] = "MAX";
    
    $o = 0;
    while ($o <= 1) {
	switch ($o) {
	 case 0:
	    $graph_text_format_array[0] = "Inbound";
	    $graph_graph_type_array[0] = "AREA";
	    $graph_color_array[0] = $graph_color_in;
	    $graph_hard_return_array[3] = "on";
	    break;
	 case 1:
	    $graph_text_format_array[0] = "Outbound";
	    $graph_graph_type_array[0] = "LINE1";
	    $graph_color_array[0] = $graph_color_out;
	    $graph_hard_return_array[3] = "";
	    break;
	}
	
	$s = 0;
	while ($s < count($graph_text_format_array)) {
	    $graph_type = db_fetch_cell("select ID from def_graph_type where name=\"$graph_graph_type_array[$s]\"","ID");
	    
	    $graph_cf_function = db_fetch_cell("select ID from def_cf where name=\"$graph_cf_function_array[$s]\"","ID");
	    
	    db_execute("insert into rrd_graph_item (dsid,colorid,textformat,
		    sequence,graphid,graphtypeid,consolidationfunction,hardreturn) values ($datasource_id[$o],
		   $graph_color_array[$s],\"$graph_text_format_array[$s]\",((($o*4)+$s)+1),$graph_id,$graph_type,$graph_cf_function,
		   \"$graph_hard_return_array[$s]\")");
	    
	    $s++;
	}
	
	$o++;
    }
    
    /* turn on graph grouping */
    group_graph_items($graph_id);
}

?>
