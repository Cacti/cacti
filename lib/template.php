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

/* push_out_data_source_custom_data - pushes out the "custom data" associated with a data
     template to all of its children. this includes all fields inhereted from the host
     and the data template
   @arg $data_template_id - the id of the data template to push out values for */
function push_out_data_source_custom_data($data_template_id) {
	/* get data_input_id */
	$data_template = db_fetch_row("select
		id,
		data_input_id
		from data_template_data
		where data_template_id=$data_template_id
		and local_data_id=0");
	
	/* must be a data template */
	if ((empty($data_template_id)) || (empty($data_template["data_input_id"]))) { return 0; }
	
	/* get a list of data sources using this template */
	$data_sources = db_fetch_assoc("select
		data_template_data.id
		from data_template_data
		where data_template_id=$data_template_id
		and local_data_id>0");
	
	/* pull out all 'input' values so we know how much to save */
	$input_fields = db_fetch_assoc("select
		data_input_fields.id,
		data_input_fields.type_code,
		data_input_data.value,
		data_input_data.t_value
		from data_input_fields left join data_input_data
		on data_input_fields.id=data_input_data.data_input_field_id
		where data_input_data.data_template_data_id=" . $data_template["id"] . "
		and data_input_fields.input_output='in'");
	
	$data_rra = db_fetch_assoc("select rra_id from data_template_data_rra where data_template_data_id=" . $data_template["id"]);
	
	if (sizeof($data_sources) > 0) {
	foreach ($data_sources as $data_source) {
		reset($input_fields);
		
		if (sizeof($input_fields) > 0) {
		foreach ($input_fields as $input_field) {
			/* do not push out "host fields" */
			if (!eregi('^' . VALID_HOST_FIELDS . '$', $input_field["type_code"])) {
				/* this is not a "host field", so we should either push out the value if it is templated
				or leave it alone if the user checked "Use Per-Data Source Value". */
				if ($input_field["t_value"] == "") { /* template this value */
					db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,value) values (" . $input_field["id"] . "," . $data_source["id"] . ",'" . $input_field["value"] . "')");
				}
			}elseif (($input_field["t_value"] == "") && ($input_field["value"] != "")) {
				/* we only template a "host field" when the user types something in the field. this way the data
				template always overides the host if the user chooses to do so */
				db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,value) values (" . $input_field["id"] . "," . $data_source["id"] . ",'" . $input_field["value"] . "')");
			}
		}
		}
		
		/* make sure to update the 'data_template_data_rra' table for each data source */
		db_execute("delete from data_template_data_rra where data_template_data_id=" . $data_source["id"]);
		
		reset($data_rra);
		
		if (sizeof($data_rra) > 0) {
		foreach ($data_rra as $rra) {
			db_execute("insert into data_template_data_rra (data_template_data_id,rra_id) values (" . $data_source["id"] . "," . $rra["rra_id"] . ")");
		}
		}
	}
	}
}

/* push_out_data_source_item - pushes out templated data template item fields to all matching
     children
   @arg $data_template_rrd_id - the id of the data template item to push out values for */
function push_out_data_source_item($data_template_rrd_id) {
	global $config;
	
	include($config["include_path"] . "/config_form.php");
	
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

/* push_out_data_source - pushes out templated data template fields to all matching children
   @arg $data_template_data_id - the id of the data template to push out values for */
function push_out_data_source($data_template_data_id) {
	global $config;
	
	include($config["include_path"] . "/config_form.php");
	
	/* get information about this data template */
	$data_template_data = db_fetch_row("select * from data_template_data where id=$data_template_data_id");
	
	/* must be a data template */
	if (empty($data_template_data["data_template_id"])) { return 0; }
	
	/* loop through each data source column name (from the above array) */
	while (list($field_name, $field_array) = each($struct_data_source)) {
		/* are we allowed to push out the column? */
		if (((empty($data_template_data{"t_" . $field_name})) || (ereg("FORCE:", $field_name))) && ((isset($data_template_data{"t_" . $field_name})) && (isset($data_template_data[$field_name])))) {
			db_execute("update data_template_data set $field_name='" . $data_template_data[$field_name] . "' where local_data_template_data_id=" . $data_template_data["id"]); 
			
			/* update the title cache */
			if ($field_name == "name") {
				update_data_source_title_cache_from_template($data_template_data["data_template_id"]);
			}
		}
	}
}

/* change_data_template - changes the data template for a particular data source to 
     $data_template_id
   @arg $local_data_id - the id of the data source to change the data template for
   @arg $data_template_id - id the of the data template to change to. specify '0' for no
     data template */
function change_data_template($local_data_id, $data_template_id) {
	global $config;
	
	include($config["include_path"] . "/config_form.php");
	
	/* always update tables to new data template (or no data template) */
	db_execute("update data_local set data_template_id=$data_template_id where id=$local_data_id");
	
	/* get data about the template and the data source */
	$data = db_fetch_row("select * from data_template_data where local_data_id=$local_data_id");
	$template_data = (($data_template_id == "0") ? $data : db_fetch_row("select * from data_template_data where local_data_id=0 and data_template_id=$data_template_id"));
	
	/* determine if we are here for the first time, or coming back */
	if ((db_fetch_cell("select local_data_template_data_id from data_template_data where local_data_id=$local_data_id") == "0") ||
	(db_fetch_cell("select local_data_template_data_id from data_template_data where local_data_id=$local_data_id") == "")) {
		$new_save = true;
	}else{
		$new_save = false;
	}
	
	/* some basic field values that ALL data sources should have */
	$save["id"] = $data["id"];
	$save["local_data_template_data_id"] = $template_data["id"];
	$save["local_data_id"] = $local_data_id;
	$save["data_template_id"] = $data_template_id;
	
	/* loop through the "templated field names" to find to the rest... */
	while (list($field_name, $field_array) = each($struct_data_source)) {
		if ((isset($data[$field_name])) || (isset($template_data[$field_name]))) {
			if ((!empty($template_data{"t_" . $field_name})) && ($new_save == false)) {
				$save[$field_name] = $data[$field_name];
			}else{
				$save[$field_name] = $template_data[$field_name];
			}
		}
	}
	
	/* these fields should never be overwritten by the template */
	$save["data_source_path"] = $data["data_source_path"];
	
	$data_template_data_id = sql_save($save, "data_template_data");
	
	$data_rrds_list = db_fetch_assoc("select * from data_template_rrd where local_data_id=$local_data_id");
	$template_rrds_list = (($data_template_id == "0") ? $data_rrds_list : db_fetch_assoc("select * from data_template_rrd where local_data_id=0 and data_template_id=$data_template_id"));
	
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
}

/* push_out_graph - pushes out templated graph template fields to all matching children
   @arg $graph_template_graph_id - the id of the graph template to push out values for */
function push_out_graph($graph_template_graph_id) {
	global $config;
	
	include($config["include_path"] . "/config_form.php");
	
	/* get information about this graph template */
	$graph_template_graph = db_fetch_row("select * from graph_templates_graph where id=$graph_template_graph_id");
	
	/* must be a graph template */
	if ($graph_template_graph["graph_template_id"] == 0) { return 0; }
	
	/* loop through each graph column name (from the above array) */
	while (list($field_name, $field_array) = each($struct_graph)) {
		/* are we allowed to push out the column? */
		if (empty($graph_template_graph{"t_" . $field_name})) {
			db_execute("update graph_templates_graph set $field_name='$graph_template_graph[$field_name]' where local_graph_template_graph_id=" . $graph_template_graph["id"]);
			
			/* update the title cache */
			if ($field_name == "title") {
				update_graph_title_cache_from_template($graph_template_graph["graph_template_id"]);
			}
		}
	}
}

/* push_out_graph_input - pushes out the value of a graph input to a single child item. this function
     differs from other push_out_* functions in that it does not push out the value of this element to
     all attached children. instead, it obtains the current value of the graph input based on other
     graph items and pushes out the "active" value
   @arg $graph_template_input_id - the id of the graph input to push out values for
   @arg $graph_template_item_id - the id the graph template item to push out
   @arg $session_members - when looking for the "active" value of the graph input, ignore these graph
     template items. typically you want to ignore all items that were just selected and have yet to be
     saved to the database. this is because these items most likely contain incorrect data */
function push_out_graph_input($graph_template_input_id, $graph_template_item_id, $session_members) {
	$graph_input = db_fetch_row("select graph_template_id,column_name from graph_template_input where id=$graph_template_input_id");
	$graph_input_items = db_fetch_assoc("select graph_template_item_id from graph_template_input_defs where graph_template_input_id=$graph_template_input_id");
	
	$i = 0;
	if (sizeof($graph_input_items) > 0) {
	foreach ($graph_input_items as $item) {
		$include_items[$i] = $item["graph_template_item_id"];
		$i++;
	}
	}
	
	/* we always want to make sure to stay within the same graph item input, so make a list of each
	item included in this input to be included in the sql query */
	if (isset($include_items)) {
		$sql_include_items = "and " . array_to_sql_or($include_items, "local_graph_template_item_id");
	}else{
		$sql_include_items = "and 0=1";
	}
	
	if (sizeof($session_members) == 0) {
		$values_to_apply = db_fetch_assoc("select local_graph_id," . $graph_input["column_name"] . " from graph_templates_item where graph_template_id=" . $graph_input["graph_template_id"] . " $sql_include_items and local_graph_id>0 group by local_graph_id");
	}else{
		$i = 0;
		while (list($item_id, $item_id) = each($session_members)) {
			$new_session_members[$i] = $item_id;
			$i++;
		}
		
		$values_to_apply = db_fetch_assoc("select local_graph_id," . $graph_input["column_name"] . " from graph_templates_item where graph_template_id=" . $graph_input["graph_template_id"] . " and local_graph_id>0 and !(" . array_to_sql_or($new_session_members, "local_graph_template_item_id") . ") $sql_include_items group by local_graph_id");
	}
	
	if (sizeof($values_to_apply) > 0) {
	foreach ($values_to_apply as $value) {
		/* this is just an extra check that i threw in to prevent users' graphs from getting really messed up */
		if (!(($graph_input["column_name"] == "task_item_id") && (empty($value{$graph_input["column_name"]})))) {
			db_execute("update graph_templates_item set " . $graph_input["column_name"] . "='" . $value{$graph_input["column_name"]} . "' where local_graph_id=" . $value["local_graph_id"] . " and local_graph_template_item_id=$graph_template_item_id");
		}
	}
	}
}

/* push_out_graph_item - pushes out templated graph template item fields to all matching
     children. if the graph template item is part of a graph input, the field will not be
     pushed out
   @arg $graph_template_item_id - the id of the graph template item to push out values for */
function push_out_graph_item($graph_template_item_id) {
	global $config;
	
	include($config["include_path"] . "/config_form.php");
	
	/* get information about this graph template */
	$graph_template_item = db_fetch_row("select * from graph_templates_item where id=$graph_template_item_id");
	
	/* must be a graph template */
	if ($graph_template_item["graph_template_id"] == 0) { return 0; }
	
	/* find out if any graphs actual contain this item */
	if (sizeof(db_fetch_assoc("select id from graph_templates_item where local_graph_template_item_id=$graph_template_item_id")) == 0) {
		/* if not, reapply the template to push out the new item */
		$attached_graphs = db_fetch_assoc("select local_graph_id from graph_templates_graph where graph_template_id=" . $graph_template_item["graph_template_id"] . " and local_graph_id>0");
		
		if (sizeof($attached_graphs) > 0) {
		foreach ($attached_graphs as $item) {
			change_graph_template($item["local_graph_id"], $graph_template_item["graph_template_id"], true);
		}
		}
	}
	
	/* this is trickier with graph_items than with the actual graph... we have to make sure not to 
	overwrite any items covered in the "graph item inputs". the same thing applies to graphs, but
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

/* change_graph_template - changes the graph template for a particular graph to 
     $graph_template_id
   @arg $local_graph_id - the id of the graph to change the graph template for
   @arg $graph_template_id - id the of the graph template to change to. specify '0' for no
     graph template
   @arg $intrusive - (true) if the target graph template has more or less graph items than
     the current graph, remove or add the items from the current graph to make them equal.
     (false) leave the graph item count alone */
function change_graph_template($local_graph_id, $graph_template_id, $intrusive) {
	global $config;
	
	include($config["include_path"] . "/config_form.php");
	
	/* always update tables to new graph template (or no graph template) */
	db_execute("update graph_local set graph_template_id=$graph_template_id where id=$local_graph_id");
	
	/* get information about both the graph and the graph template we're using */
	$graph_list = db_fetch_row("select * from graph_templates_graph where local_graph_id=$local_graph_id");
	$template_graph_list = (($graph_template_id == "0") ? $graph_list : db_fetch_row("select * from graph_templates_graph where local_graph_id=0 and graph_template_id=$graph_template_id"));
	
	/* determine if we are here for the first time, or coming back */
	if ((db_fetch_cell("select local_graph_template_graph_id from graph_templates_graph where local_graph_id=$local_graph_id") == "0") ||
	(db_fetch_cell("select local_graph_template_graph_id from graph_templates_graph where local_graph_id=$local_graph_id") == "")) {
		$new_save = true;
	}else{
		$new_save = false;
	}
	
	/* some basic field values that ALL graphs should have */
	$save["id"] = $graph_list["id"];
	$save["local_graph_template_graph_id"] = $template_graph_list["id"];
	$save["local_graph_id"] = $local_graph_id;
	$save["graph_template_id"] = $graph_template_id;
	
	/* loop through the "templated field names" to find to the rest... */
	while (list($field_name, $field_array) = each($struct_graph)) {
		$value_type = "t_$field_name";
		
		if ((!empty($template_graph_list[$value_type])) && ($new_save == false)) {
			$save[$field_name] = $graph_list[$field_name];
		}else{
			$save[$field_name] = $template_graph_list[$field_name];
		}
	}
	
	sql_save($save, "graph_templates_graph");
	
	$graph_items_list = db_fetch_assoc("select * from graph_templates_item where local_graph_id=$local_graph_id order by sequence");
	$template_items_list = (($graph_template_id == "0") ? $graph_items_list : db_fetch_assoc("select * from graph_templates_item where local_graph_id=0 and graph_template_id=$graph_template_id order by sequence"));
	
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

/* graph_to_graph_template - converts a graph to a graph template
   @arg $local_graph_id - the id of the graph to be converted
   @arg $graph_title - the graph title to use for the new graph template. the variable
     <graph_title> will be substituted for the current graph title */
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

/* data_source_to_data_template - converts a data source to a data template
   @arg $local_data_id - the id of the data source to be converted
   @arg $data_source_title - the data source title to use for the new data template. the variable
     <ds_title> will be substituted for the current data source title */
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

/* create_complete_graph_from_template - creates a graph and all necessary data sources based on a 
     graph template
   @arg $graph_template_id - the id of the graph template that will be used to create the new
     graph
   @arg $host_id - the id of the host to associate the new graph and data sources with
   @arg $snmp_query_array - if the new data sources are to be based on a data query, specify the
     necessary data query information here. it must contain the following information:
       $snmp_query_array["snmp_query_id"]
       $snmp_query_array["snmp_index_on"]
       $snmp_query_array["snmp_query_graph_id"]
       $snmp_query_array["snmp_index"]
   @arg $suggested_values_array - any additional information to be included in the new graphs or 
     data sources must be included in the array. data is to be included in the following format:
       $values["cg"][graph_template_id]["graph_template"][field_name] = $value  // graph template
       $values["cg"][graph_template_id]["graph_template_item"][graph_template_item_id][field_name] = $value  // graph template item
       $values["cg"][data_template_id]["data_template"][field_name] = $value  // data template
       $values["cg"][data_template_id]["data_template_item"][data_template_item_id][field_name] = $value  // data template item
       $values["sg"][data_query_id][graph_template_id]["graph_template"][field_name] = $value  // graph template (w/ data query)
       $values["sg"][data_query_id][graph_template_id]["graph_template_item"][graph_template_item_id][field_name] = $value  // graph template item (w/ data query)
       $values["sg"][data_query_id][data_template_id]["data_template"][field_name] = $value  // data template (w/ data query)
       $values["sg"][data_query_id][data_template_id]["data_template_item"][data_template_item_id][field_name] = $value  // data template item (w/ data query) */
function create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, &$suggested_values_array) {
	global $config;
	
	include_once($config["library_path"] . "/data_query.php");
	
	/* create the graph */
	$save["id"] = 0;
	$save["graph_template_id"] = $graph_template_id;
	$save["host_id"] = $host_id;
	
	$cache_array["local_graph_id"] = sql_save($save, "graph_local");
	change_graph_template($cache_array["local_graph_id"], $graph_template_id, true);
	
	if (is_array($snmp_query_array)) {
		/* suggested values for snmp query code */
		$suggested_values = db_fetch_assoc("select text,field_name from snmp_query_graph_sv where snmp_query_graph_id=" . $snmp_query_array["snmp_query_graph_id"] . " order by sequence");
		
		if (sizeof($suggested_values) > 0) {
		foreach ($suggested_values as $suggested_value) {
			/* once we find a match; don't try to find more */
			if (!isset($suggested_values_graph[$graph_template_id]{$suggested_value["field_name"]})) {
				$subs_string = substitute_snmp_query_data($suggested_value["text"], "|", "|", $host_id, $snmp_query_array["snmp_query_id"], $snmp_query_array["snmp_index"]);
				/* if there are no '|' characters, all of the substitutions were successful */
				if (!strstr($subs_string, "|query")) {
					db_execute("update graph_templates_graph set " . $suggested_value["field_name"] . "='" . $suggested_value["text"] . "' where local_graph_id=" . $cache_array["local_graph_id"]);
					
					/* once we find a working value, stop */
					$suggested_values_graph[$graph_template_id]{$suggested_value["field_name"]} = true;
				}
			}
		}
		}
	}
	
	/* suggested values: graph */
	if (isset($suggested_values_array[$graph_template_id]["graph_template"])) {
		while (list($field_name, $field_value) = each($suggested_values_array[$graph_template_id]["graph_template"])) {
			db_execute("update graph_templates_graph set $field_name='$field_value' where local_graph_id=" . $cache_array["local_graph_id"]);
		}
	}
	
	/* suggested values: graph item */
	if (isset($suggested_values_array[$graph_template_id]["graph_template_item"])) {
		while (list($graph_template_item_id, $field_array) = each($suggested_values_array[$graph_template_id]["graph_template_item"])) {
			while (list($field_name, $field_value) = each($field_array)) {
				$graph_item_id = db_fetch_cell("select id from graph_templates_item where local_graph_template_item_id=$graph_template_item_id and local_graph_id=" . $cache_array["local_graph_id"]);
				db_execute("update graph_templates_item set $field_name='$field_value' where id=$graph_item_id");
			}
		}
	}
	
	update_graph_title_cache($cache_array["local_graph_id"]);
	
	/* create each data source */
	$data_templates = db_fetch_assoc("select
		data_template.id,
		data_template.name,
		data_template_rrd.data_source_name
		from data_template, data_template_rrd, graph_templates_item
		where graph_templates_item.task_item_id=data_template_rrd.id
		and data_template_rrd.data_template_id=data_template.id
		and data_template_rrd.local_data_id=0
		and graph_templates_item.local_graph_id=0
		and graph_templates_item.graph_template_id=" . $graph_template_id . "
		group by data_template.id
		order by data_template.name");
	
	if (sizeof($data_templates) > 0) {
	foreach ($data_templates as $data_template) {
		unset($save);
		
		$save["id"] = 0;
		$save["data_template_id"] = $data_template["id"];
		$save["host_id"] = $host_id;
		
		$cache_array["local_data_id"]{$data_template["id"]} = sql_save($save, "data_local");
		change_data_template($cache_array["local_data_id"]{$data_template["id"]}, $data_template["id"]);
		
		$data_template_data_id = db_fetch_cell("select id from data_template_data where local_data_id=" . $cache_array["local_data_id"]{$data_template["id"]});
		
		if (is_array($snmp_query_array)) {
			/* suggested values for snmp query code */
			$suggested_values = db_fetch_assoc("select text,field_name from snmp_query_graph_rrd_sv where snmp_query_graph_id=" . $snmp_query_array["snmp_query_graph_id"] . " and data_template_id=" . $data_template["id"] . " order by sequence");
			
			if (sizeof($suggested_values) > 0) {
			foreach ($suggested_values as $suggested_value) {
				/* once we find a match; don't try to find more */
				if (!isset($suggested_values_ds{$data_template["id"]}{$suggested_value["field_name"]})) {
					$subs_string = substitute_snmp_query_data($suggested_value["text"], "|", "|", $host_id, $snmp_query_array["snmp_query_id"], $snmp_query_array["snmp_index"]);
					
					/* if there are no '|' characters, all of the substitutions were successful */
					if (!strstr($subs_string, "|query")) {
						db_execute("update data_template_data set " . $suggested_value["field_name"] . "='" . $suggested_value["text"] . "' where local_data_id=" . $cache_array["local_data_id"]{$data_template["id"]});
						
						/* once we find a working value, stop */
						$suggested_values_ds{$data_template["id"]}{$suggested_value["field_name"]} = true;
					}
				}
			}
			}
		}
		
		if (is_array($snmp_query_array)) {
			$data_input_field_id_index = db_fetch_cell("select data_input_field_id from snmp_query_field where snmp_query_id=" . $snmp_query_array["snmp_query_id"] . " and action_id=1");
			$data_input_field_id_index_value = db_fetch_cell("select data_input_field_id from snmp_query_field where snmp_query_id=" . $snmp_query_array["snmp_query_id"] . " and action_id=2");
			$data_input_field_id_output_type = db_fetch_cell("select data_input_field_id from snmp_query_field where snmp_query_id=" . $snmp_query_array["snmp_query_id"] . " and action_id=3");
			
			$snmp_cache_value = db_fetch_cell("select field_value from host_snmp_cache where host_id=$host_id and field_name='" . $snmp_query_array["snmp_index_on"] . "' and snmp_index='" . $snmp_query_array["snmp_index"] . "'");
			
			/* save the value to index on (ie. ifindex, ifip, etc) */
			db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,t_value,value) values ($data_input_field_id_index,$data_template_data_id,'','" . $snmp_query_array["snmp_index_on"] . "')");
			
			/* save the actual value (ie. 3, 192.168.1.101, etc) */
			db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,t_value,value) values ($data_input_field_id_index_value,$data_template_data_id,'','$snmp_cache_value')");
			
			/* set the expected output type (ie. bytes, errors, packets) */
			db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,t_value,value) values ($data_input_field_id_output_type,$data_template_data_id,'','" . $snmp_query_array["snmp_query_graph_id"] . "')");
			
			/* now that we have put data into the 'data_input_data' table, update the snmp cache for ds's */
			update_data_source_data_query_cache($cache_array["local_data_id"]{$data_template["id"]});
		}
		
		/* suggested values: data source */
		if (isset($suggested_values_array[$graph_template_id]["data_template"]{$data_template["id"]})) {
			reset($suggested_values_array[$graph_template_id]["data_template"]{$data_template["id"]});
			while (list($field_name, $field_value) = each($suggested_values_array[$graph_template_id]["data_template"]{$data_template["id"]})) {
				db_execute("update data_template_data set $field_name='$field_value' where local_data_id=" . $cache_array["local_data_id"]{$data_template["id"]});
			}
		}
		
		/* suggested values: data source item */
		if (isset($suggested_values_array[$graph_template_id]["data_template_item"])) {
			reset($suggested_values_array[$graph_template_id]["data_template_item"]);
			while (list($data_template_item_id, $field_array) = each($suggested_values_array[$graph_template_id]["data_template_item"])) {
				while (list($field_name, $field_value) = each($field_array)) {
					$data_source_item_id = db_fetch_cell("select id from data_template_rrd where local_data_template_rrd_id=$data_template_item_id and local_data_id=" . $cache_array["local_data_id"]{$data_template["id"]});
					db_execute("update data_template_rrd set $field_name='$field_value' where id=$data_source_item_id");
				}
			}
		}
		
		/* suggested values: custom data */
		if (isset($suggested_values_array[$graph_template_id]["custom_data"]{$data_template["id"]})) {
			reset($suggested_values_array[$graph_template_id]["custom_data"]{$data_template["id"]});
			while (list($data_input_field_id, $field_value) = each($suggested_values_array[$graph_template_id]["custom_data"]{$data_template["id"]})) {
				db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,t_value,value) values ($data_input_field_id,$data_template_data_id,'','$field_value')");
			}
		}
		
		update_data_source_title_cache($cache_array["local_data_id"]{$data_template["id"]});
	}
	}
	
	/* connect the dots: graph -> data source(s) */
	$template_item_list = db_fetch_assoc("select
		graph_templates_item.id,
		data_template_rrd.id as data_template_rrd_id,
		data_template_rrd.data_template_id
		from graph_templates_item,data_template_rrd
		where graph_templates_item.task_item_id=data_template_rrd.id
		and graph_templates_item.graph_template_id=$graph_template_id
		and local_graph_id=0
		and task_item_id>0");
	
	/* loop through each item affected and update column data */
	if (sizeof($template_item_list) > 0) {
	foreach ($template_item_list as $template_item) {
		$local_data_id = $cache_array["local_data_id"]{$template_item["data_template_id"]};
						
		$graph_template_item_id = db_fetch_cell("select id from graph_templates_item where local_graph_template_item_id=" . $template_item["id"] . " and local_graph_id=" . $cache_array["local_graph_id"]);
		$data_template_rrd_id = db_fetch_cell("select id from data_template_rrd where local_data_template_rrd_id=" . $template_item["data_template_rrd_id"] . " and local_data_id=$local_data_id");
		
		if (!empty($data_template_rrd_id)) {
			db_execute("update graph_templates_item set task_item_id='$data_template_rrd_id' where id=$graph_template_item_id");
		}
	}
	}
	
	/* this will not work until the ds->graph dots are connected */
	if (is_array($snmp_query_array)) {
		update_graph_data_query_cache($cache_array["local_graph_id"]);
	}
	
	return $cache_array;
}

/* draw_nontemplated_fields_graph - draws a form that consists of all non-templated graph fields associated
     with a particular graph template
   @arg $graph_template_id - the id of the graph template to base the form after
   @arg $values_array - any values that should be included by default on the form
   @arg $field_name_format - all fields on the form will be named using the following format, the following
     variables can be used:
       |field| - the current field name
   @arg $header_title - the title to use on the header for this form
   @arg $alternate_colors (bool) - whether to alternate colors for each row on the form or not 
   @arg $include_hidden_fields (bool) - should elements that are not to be displayed be represented as hidden
     html input elements or omitted altogether?
   @arg $snmp_query_graph_id - if this graph template is part of a data query, specify the graph id here. this
     will be used to determine if a given field is using suggested values */
function draw_nontemplated_fields_graph($graph_template_id, &$values_array, $field_name_format = "|field|", $header_title = "", $alternate_colors = true, $include_hidden_fields = true, $snmp_query_graph_id = 0) {
	global $struct_graph, $colors;
	
	$form_array = array();
	$draw_any_items = false;
	
	/* fetch information about the graph template */
	$graph_template = db_fetch_row("select * from graph_templates_graph where graph_template_id=$graph_template_id and local_graph_id=0");
	
	while (list($field_name, $field_array) = each($struct_graph)) {
		/* find our field name */
		$form_field_name = str_replace("|field|", $field_name, $field_name_format);
		
		$form_array += array($form_field_name => $struct_graph[$field_name]);
		
		/* modifications to the default form array */
		$form_array[$form_field_name]["value"] = (isset($values_array[$field_name]) ? $values_array[$field_name] : "");
		$form_array[$form_field_name]["form_id"] = (isset($values_array["id"]) ? $values_array["id"] : "0");
		
		if ($graph_template{"t_" . $field_name} != "on") {
			if ($include_hidden_fields == true) {
				$form_array[$form_field_name]["method"] = "hidden";
			}else{
				unset($form_array[$form_field_name]);
			}
		}elseif ((!empty($snmp_query_graph_id)) && (sizeof(db_fetch_assoc("select id from snmp_query_graph_sv where snmp_query_graph_id=$snmp_query_graph_id and field_name='$field_name'")) > 0)) {
			if ($include_hidden_fields == true) {
				$form_array[$form_field_name]["method"] = "hidden";
			}else{
				unset($form_array[$form_field_name]);
			}
		}else{
			if (($draw_any_items == false) && ($header_title != "")) {
				print "<tr bgcolor='#" . $colors["header_panel"] . "'><td colspan='2' style='font-size: 10px; color: white;'>$header_title</td></tr>\n";
			}
			
			$draw_any_items = true;
		}
	}
	
	/* setup form options */
	if ($alternate_colors == true) {
		$form_config_array = array("no_form_tag" => true);
	}else{
		$form_config_array = array("no_form_tag" => true, "force_row_color" => $colors["form_alternate1"]);
	}
	
	draw_edit_form(
		array(
			"config" => $form_config_array,
			"fields" => $form_array
			)
		);
}

/* draw_nontemplated_fields_graph_item - draws a form that consists of all non-templated graph item fields 
     associated with a particular graph template
   @arg $graph_template_id - the id of the graph template to base the form after
   @arg $local_graph_id - specify the id of the associated graph if it exists
   @arg $field_name_format - all fields on the form will be named using the following format, the following
     variables can be used:
       |field| - the current field name
       |id| - the current graph input id
   @arg $header_title - the title to use on the header for this form
   @arg $alternate_colors (bool) - whether to alternate colors for each row on the form or not */
function draw_nontemplated_fields_graph_item($graph_template_id, $local_graph_id, $field_name_format = "|field|_|id|", $header_title = "", $alternate_colors = true) {
	global $struct_graph_item, $colors;
	
	$form_array = array();
	$draw_any_items = false;
	
	/* fetch information about the graph template */
	$input_item_list = db_fetch_assoc("select * from graph_template_input where graph_template_id=$graph_template_id order by column_name,name");
	
	/* modifications to the default graph items array */
	if (!empty($local_graph_id)) {
		$host_id = db_fetch_cell("select host_id from graph_local where id=$local_graph_id");
		
		$struct_graph_item["task_item_id"]["sql"] = "select
			CONCAT_WS('',
			case
			when host.description is null then 'No Host - ' 
			when host.description is not null then ''
			end,data_template_data.name_cache,' (',data_template_rrd.data_source_name,')') as name,
			data_template_rrd.id 
			from data_template_data,data_template_rrd,data_local 
			left join host on data_local.host_id=host.id
			where data_template_rrd.local_data_id=data_local.id 
			and data_template_data.local_data_id=data_local.id
			" . (empty($host_id) ? "" : " and data_local.host_id=$host_id") . "
			order by name";
	}
	
	if (sizeof($input_item_list) > 0) {
		foreach ($input_item_list as $item) {
			if (!empty($local_graph_id)) {
				$current_def_value = db_fetch_row("select 
					graph_templates_item." . $item["column_name"] . ",
					graph_templates_item.id
					from graph_templates_item,graph_template_input_defs 
					where graph_template_input_defs.graph_template_item_id=graph_templates_item.local_graph_template_item_id 
					and graph_template_input_defs.graph_template_input_id=" . $item["id"] . "
					and graph_templates_item.local_graph_id=$local_graph_id
					limit 0,1");
			}else{
				$current_def_value = db_fetch_row("select 
					graph_templates_item." . $item["column_name"] . ",
					graph_templates_item.id
					from graph_templates_item,graph_template_input_defs 
					where graph_template_input_defs.graph_template_item_id=graph_templates_item.id 
					and graph_template_input_defs.graph_template_input_id=" . $item["id"] . "
					and graph_templates_item.graph_template_id=" . $graph_template_id . "
					limit 0,1");
			}
			
			/* find our field name */
			$form_field_name = str_replace("|field|", $item["column_name"], $field_name_format);
			$form_field_name = str_replace("|id|", $item["id"], $form_field_name);
			
			$form_array += array($form_field_name => $struct_graph_item{$item["column_name"]});
			
			/* modifications to the default form array */
			$form_array[$form_field_name]["friendly_name"] = $item["name"];
			$form_array[$form_field_name]["value"] = $current_def_value{$item["column_name"]};
			
			/* if we are drawing the graph input list in the pre-graph stage we should omit the data
			source fields because they are basically meaningless at this point */
			if ((empty($local_graph_id)) && ($item["column_name"] == "task_item_id")) {
				unset($form_array[$form_field_name]);
			}else{
				if (($draw_any_items == false) && ($header_title != "")) {
					print "<tr bgcolor='#" . $colors["header_panel"] . "'><td colspan='2' style='font-size: 10px; color: white;'>$header_title</td></tr>\n";
				}
				
				$draw_any_items = true;
			}
		}
	}
	
	/* setup form options */
	if ($alternate_colors == true) {
		$form_config_array = array("no_form_tag" => true);
	}else{
		$form_config_array = array("no_form_tag" => true, "force_row_color" => $colors["form_alternate1"]);
	}
	
	if (sizeof($input_item_list > 0)) {
		draw_edit_form(
			array(
				"config" => $form_config_array,
				"fields" => $form_array
				)
			);
	}
}

/* draw_nontemplated_fields_data_source - draws a form that consists of all non-templated data source fields 
     associated with a particular data template
   @arg $data_template_id - the id of the data template to base the form after
   @arg $local_data_id - specify the id of the associated data source if it exists
   @arg $values_array - any values that should be included by default on the form
   @arg $field_name_format - all fields on the form will be named using the following format, the following
     variables can be used:
       |field| - the current field name
   @arg $header_title - the title to use on the header for this form
   @arg $alternate_colors (bool) - whether to alternate colors for each row on the form or not 
   @arg $include_hidden_fields (bool) - should elements that are not to be displayed be represented as hidden
     html input elements or omitted altogether?
   @arg $snmp_query_graph_id - if this data template is part of a data query, specify the graph id here. this
     will be used to determine if a given field is using suggested values */
function draw_nontemplated_fields_data_source($data_template_id, $local_data_id, &$values_array, $field_name_format = "|field|", $header_title = "", $alternate_colors = true, $include_hidden_fields = true, $snmp_query_graph_id = 0) {
	global $struct_data_source, $colors;
	
	$form_array = array();
	$draw_any_items = false;
	
	/* fetch information about the data template */
	$data_template = db_fetch_row("select * from data_template_data where data_template_id=$data_template_id and local_data_id=0");
	
	while (list($field_name, $field_array) = each($struct_data_source)) {
		/* find our field name */
		$form_field_name = str_replace("|field|", $field_name, $field_name_format);
		
		$form_array += array($form_field_name => $struct_data_source[$field_name]);
		
		/* modifications to the default form array */
		$form_array[$form_field_name]["value"] = (isset($values_array[$field_name]) ? $values_array[$field_name] : "");
		$form_array[$form_field_name]["form_id"] = (isset($values_array["id"]) ? $values_array["id"] : "0");
		
		$current_flag = (isset($field_array["flags"]) ? $field_array["flags"] : "");
		$current_template_flag = (isset($data_template{"t_" . $field_name}) ? $data_template{"t_" . $field_name} : "on");
		
		if (($current_template_flag != "on") || ($current_flag == "ALWAYSTEMPLATE")) {
			if ($include_hidden_fields == true) {
				$form_array[$form_field_name]["method"] = "hidden";
			}else{
				unset($form_array[$form_field_name]);
			}
		}elseif ((!empty($snmp_query_graph_id)) && (sizeof(db_fetch_assoc("select id from snmp_query_graph_rrd_sv where snmp_query_graph_id=$snmp_query_graph_id and data_template_id=$data_template_id and field_name='$field_name'")) > 0)) {
			if ($include_hidden_fields == true) {
				$form_array[$form_field_name]["method"] = "hidden";
			}else{
				unset($form_array[$form_field_name]);
			}
		}elseif ((empty($local_data_id)) && ($field_name == "data_source_path")) {
			if ($include_hidden_fields == true) {
				$form_array[$form_field_name]["method"] = "hidden";
			}else{
				unset($form_array[$form_field_name]);
			}
		}else{
			if (($draw_any_items == false) && ($header_title != "")) {
				print "<tr bgcolor='#" . $colors["header_panel"] . "'><td colspan='2' style='font-size: 10px; color: white;'>$header_title</td></tr>\n";
			}
			
			$draw_any_items = true;
		}
	}
	
	/* setup form options */
	if ($alternate_colors == true) {
		$form_config_array = array("no_form_tag" => true);
	}else{
		$form_config_array = array("no_form_tag" => true, "force_row_color" => $colors["form_alternate1"]);
	}
	
	draw_edit_form(
		array(
			"config" => $form_config_array,
			"fields" => $form_array
			)
		);
}

/* draw_nontemplated_fields_data_source_item - draws a form that consists of all non-templated data source 
     item fields associated with a particular data template
   @arg $data_template_id - the id of the data template to base the form after
   @arg $values_array - any values that should be included by default on the form
   @arg $field_name_format - all fields on the form will be named using the following format, the following
     variables can be used:
       |field| - the current field name
       |id| - the id of the current data source item
   @arg $header_title - the title to use on the header for this form
   @arg $draw_title_for_each_item (bool) - should a separate header be drawn for each data source item, or
     should all data source items be drawn under one header?
   @arg $alternate_colors (bool) - whether to alternate colors for each row on the form or not 
   @arg $include_hidden_fields (bool) - should elements that are not to be displayed be represented as hidden
     html input elements or omitted altogether?
   @arg $snmp_query_graph_id - if this graph template is part of a data query, specify the graph id here. this
     will be used to determine if a given field is using suggested values */
function draw_nontemplated_fields_data_source_item($data_template_id, &$values_array, $field_name_format = "|field_id|", $header_title = "", $draw_title_for_each_item = true, $alternate_colors = true, $include_hidden_fields = true, $snmp_query_graph_id = 0) {
	global $struct_data_source_item, $colors;
	
	$draw_any_items = false;
	
	/* setup form options */
	if ($alternate_colors == true) {
		$form_config_array = array("no_form_tag" => true);
	}else{
		$form_config_array = array("no_form_tag" => true, "force_row_color" => $colors["form_alternate1"]);
	}
	
	if (sizeof($values_array) > 0) {
	foreach ($values_array as $rrd) {
		reset($struct_data_source_item);
		$form_array = array();
		
		/* if the user specifies a title, we only want to draw that. if not, we should create our
		own title for each data source item */
		if ($draw_title_for_each_item == true) {
			$draw_any_items = false;
		}
		
		if (empty($rrd["local_data_id"])) { /* this is a template */
			$data_template_rrd = $rrd;
		}else{ /* this is not a template */
			$data_template_rrd = db_fetch_row("select * from data_template_rrd where id=" . $rrd["local_data_template_rrd_id"]);
		}
		
		while (list($field_name, $field_array) = each($struct_data_source_item)) {
			/* find our field name */
			$form_field_name = str_replace("|field|", $field_name, $field_name_format);
			$form_field_name = str_replace("|id|", $rrd["id"], $form_field_name);
			
			$form_array += array($form_field_name => $struct_data_source_item[$field_name]);
			
			/* modifications to the default form array */
			$form_array[$form_field_name]["value"] = (isset($rrd[$field_name]) ? $rrd[$field_name] : "");
			$form_array[$form_field_name]["form_id"] = (isset($rrd["id"]) ? $rrd["id"] : "0");
			
			/* append the data source item name so the user will recognize it */
			if ($draw_title_for_each_item == false) {
				$form_array[$form_field_name]["friendly_name"] .= " [" . $rrd["data_source_name"] . "]";
			}
			
			if ($data_template_rrd{"t_" . $field_name} != "on") {
				if ($include_hidden_fields == true) {
					$form_array[$form_field_name]["method"] = "hidden";
				}else{
					unset($form_array[$form_field_name]);
				}
			}elseif ((!empty($snmp_query_graph_id)) && (sizeof(db_fetch_assoc("select id from snmp_query_graph_rrd_sv where snmp_query_graph_id=$snmp_query_graph_id and data_template_id=$data_template_id and field_name='$field_name'")) > 0)) {
				if ($include_hidden_fields == true) {
					$form_array[$form_field_name]["method"] = "hidden";
				}else{
					unset($form_array[$form_field_name]);
				}
			}else{
				if (($draw_any_items == false) && ($draw_title_for_each_item == false) && ($header_title != "")) {
					print "<tr bgcolor='#" . $colors["header_panel"] . "'><td colspan='2' style='font-size: 10px; color: white;'>$header_title</td></tr>\n";
				}elseif (($draw_any_items == false) && ($draw_title_for_each_item == true) && ($header_title != "")) {
					print "<tr bgcolor='#" . $colors["header_panel"] . "'><td colspan='2' style='font-size: 10px; color: white;'>$header_title [" . $rrd["data_source_name"] . "]</td></tr>\n";
				}
				
				$draw_any_items = true;
			}
		}
		
		draw_edit_form(
			array(
				"config" => $form_config_array,
				"fields" => $form_array
				)
			);
	}
	}
}

/* draw_nontemplated_fields_custom_data - draws a form that consists of all non-templated custom data fields 
     associated with a particular data template
   @arg $data_template_id - the id of the data template to base the form after
   @arg $field_name_format - all fields on the form will be named using the following format, the following
     variables can be used:
       |id| - the id of the current field
   @arg $header_title - the title to use on the header for this form
   @arg $draw_title_for_each_item (bool) - should a separate header be drawn for each data source item, or
     should all data source items be drawn under one header?
   @arg $alternate_colors (bool) - whether to alternate colors for each row on the form or not 
   @arg $include_hidden_fields (bool) - should elements that are not to be displayed be represented as hidden
     html input elements or omitted altogether?
   @arg $snmp_query_id - if this graph template is part of a data query, specify the data query id here. this
     will be used to determine if a given field is associated with a suggested value */
function draw_nontemplated_fields_custom_data($data_template_data_id, $field_name_format = "|field|", $header_title = "", $alternate_colors = true, $include_hidden_fields = true, $snmp_query_id = 0) {
	global $colors;
	
	$data = db_fetch_row("select id,data_input_id,data_template_id,name,local_data_id from data_template_data where id=$data_template_data_id");
	$host_id = db_fetch_cell("select host.id from data_local,host where data_local.host_id=host.id and data_local.id=" . $data["local_data_id"]);
	$template_data = db_fetch_row("select id,data_input_id from data_template_data where data_template_id=" . $data["data_template_id"] . " and local_data_id=0");
	
	$draw_any_items = false;
	
	/* get each INPUT field for this data input source */
	$fields = db_fetch_assoc("select * from data_input_fields where data_input_id=" . $data["data_input_id"] . " and input_output='in' order by name");
	
	/* loop through each field found */
	$i = 0;
	if (sizeof($fields) > 0) {
	foreach ($fields as $field) {
		$data_input_data = db_fetch_row("select * from data_input_data where data_template_data_id=" . $data["id"] . " and data_input_field_id=" . $field["id"]);
		
		if (sizeof($data_input_data) > 0) {
			$old_value = $data_input_data["value"];
		}else{
			$old_value = "";
		}
		
		/* if data template then get t_value from template, else always allow user input */
		if (empty($data["data_template_id"])) {
			$can_template = "on";
		}else{
			$can_template = db_fetch_cell("select t_value from data_input_data where data_template_data_id=" . $template_data["id"] . " and data_input_field_id=" . $field["id"]);
		}
		
		/* find our field name */
		$form_field_name = str_replace("|id|", $field["id"], $field_name_format);
		
		if ((!empty($host_id)) && (eregi('^' . VALID_HOST_FIELDS . '$', $field["type_code"])) && (empty($can_template))) { /* no host fields */
			if ($include_hidden_fields == true) {
				form_hidden_box($form_field_name, $old_value, "");
			}
		}elseif ((!empty($snmp_query_id)) && (eregi('^(index_type|index_value|output_type)$', $field["type_code"]))) { /* no data query fields */
			if ($include_hidden_fields == true) {
				form_hidden_box($form_field_name, $old_value, "");
			}
		}elseif (empty($can_template)) { /* no templated fields */
			if ($include_hidden_fields == true) {
				form_hidden_box($form_field_name, $old_value, "");
			}
		}else{
			if (($draw_any_items == false) && ($header_title != "")) {
				print "<tr bgcolor='#" . $colors["header_panel"] . "'><td colspan='2' style='font-size: 10px; color: white;'>$header_title</td></tr>\n";
			}
			
			if ($alternate_colors == true) {
				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
			}else{
				print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
			}
			
			print "<td width='50%'><strong>" . $field["name"] . "</strong></td>\n";
			print "<td>";
			
			draw_custom_data_row($form_field_name, $field["id"], $data["id"], $old_value);
			
			print "</td>";
			print "</tr>\n";
			
			$draw_any_items = true;
			$i++;
		}
	}
	}
}

?>
