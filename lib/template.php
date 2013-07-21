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

/* push_out_data_source_custom_data - pushes out the "custom data" associated with a data
	template to all of its children. this includes all fields inhereted from the host
	and the data template
   @arg $data_template_id - the id of the data template to push out values for */
function push_out_data_source_custom_data($data_template_id) {
	
	/* valid data template id? */
	if (empty($data_template_id)) { return 0; }

	/* get data_input_id from template */
	$data_template_data = db_fetch_row("SELECT " .
			"id, " .
			"data_input_id " .
			"FROM data_template_data " .
			"WHERE data_template_id=$data_template_id " .
			"AND local_data_id=0");

	/* must be a data template */
	if (empty($data_template_data["data_input_id"])) { return 0; }

	/* get a list of data sources using this template */
	$data_sources = db_fetch_assoc("SELECT " .
			"data_template_data.id " .
			"FROM data_template_data " .
			"WHERE data_template_id=$data_template_id " .
			"AND local_data_id>0");

	/* pull out all custom templated 'input' values from the template itself 
	 * templated items are selected by querying t_value = '' OR t_value = NULL */
	$template_input_fields = array_rekey(db_fetch_assoc("SELECT " .
			"data_input_fields.id, " .
			"data_input_fields.type_code, " .
			"data_input_data.value, " .
			"data_input_data.t_value " .
			"FROM data_input_fields " .
			"INNER JOIN (data_template_data " .
			"INNER JOIN data_input_data " .
			"ON data_template_data.id = data_input_data.data_template_data_id) " .
			"ON data_input_fields.id = data_input_data.data_input_field_id " .
			"WHERE (data_input_fields.input_output='in') " .
			"AND (data_input_data.t_value='' OR data_input_data.t_value IS NULL) " .
			"AND (data_input_data.data_template_data_id=" . $data_template_data["id"] . ") " .
			"AND (data_template_data.local_data_template_data_id=0)"), "id", array("type_code", "value", "t_value"));

	/* which data_input_fields are templated? */
	$dif_ct = 0;
	$dif_in_str = "";
	if (sizeof($template_input_fields)) {
		$dif_in_str .= "AND data_input_fields.id IN (";
		foreach ($template_input_fields as $key => $value) {
			$dif_in_str .= ($dif_ct == 0 ? "":",") . $key;
			$dif_ct++;
		}
		$dif_in_str .= ") ";
	}

	/* pull out all templated 'input' values from all related data sources 
	 * unfortunately, you can't simply provide the same test as above
	 * all input fields not related to a template ALWAYS are marked with t_value = NULL
	 * so we will verify against the list of data_input_field id's taken from above */
	$input_fields = db_fetch_assoc("SELECT " .
			"data_template_data.id AS data_template_data_id, " .
			"data_input_fields.id, " .
			"data_input_fields.type_code, " .
			"data_input_data.value, " .
			"data_input_data.t_value " .
			"FROM data_input_fields " .
			"INNER JOIN (data_template_data " .
			"INNER JOIN data_input_data " .
			"ON data_template_data.id = data_input_data.data_template_data_id) " .
			"ON data_input_fields.id = data_input_data.data_input_field_id " .
			"WHERE (data_input_fields.input_output='in') " . 
			$dif_in_str .
			"AND (data_input_data.t_value='' OR data_input_data.t_value IS NULL) " .
			"AND (data_template_data.local_data_template_data_id=" . $data_template_data["id"] . ")");

	$data_rra = db_fetch_assoc("SELECT rra_id
		FROM data_template_data_rra
		WHERE data_template_data_id=" . $data_template_data["id"]);

	/* perform bulk update of rra associations */
	$rra_ct = 0;
	$rra_in_str = "";
	if (sizeof($data_rra)) {
		foreach($data_rra as $rra) {
			$rra_in_str .= ($rra_ct == 0 ? "(":",") . $rra["rra_id"];
			$rra_ct++;
		}
		$rra_in_str .= ")";
	}

	$ds_cnt    = 0;
	$did_cnt   = 0;
	$rra_cnt   = 0;
	$ds_in_str = "";
	$did_vals  = "";
	$rra_vals  = "";
	if (sizeof($data_sources)) {
		foreach ($data_sources as $data_source) {
			reset($input_fields);
			
			if (sizeof($input_fields)) {
				foreach ($input_fields as $input_field) {
					if ($data_source["id"] == $input_field["data_template_data_id"] &&
						isset($template_input_fields[$input_field["id"]])) {
						/* do not push out "host fields" */
						if (!preg_match('/^' . VALID_HOST_FIELDS . '$/i', $input_field["type_code"])) {
							/* this is not a "host field", so we should either push out the value if it is templated */
							$did_vals .= ($did_cnt == 0 ? "":",") . "(" . $input_field["id"] . ", " . $data_source["id"] . ", '" . addslashes($template_input_fields[$input_field["id"]]["value"]) . "')";
							$did_cnt++;
						}elseif ($template_input_fields[$input_field["id"]]["value"] != $input_field["value"]) { # templated input field deviates from currenmt data source, so update required
							$did_vals .= ($did_cnt == 0 ? "":",") . "(" . $input_field["id"] . ", " . $data_source["id"] . ", '" . addslashes($template_input_fields[$input_field["id"]]["value"]) . "')";
							$did_cnt++;
						}
					}
				}
			}
			
			/* create large inserts to reduce turns */
			$ds_in_str .= ($ds_cnt == 0 ? "(":",") . $data_source["id"];
			if (sizeof($data_rra)) {
				foreach ($data_rra as $rra) {
					$rra_vals .= ($rra_cnt == 0 ? "":",") . "(" . $data_source["id"] . "," . $rra["rra_id"] . ")";
					$rra_cnt++;
				}
			}
			$ds_cnt++;
			
			/* per 1000 data source, update rows */
			if ($ds_cnt % 1000 == 0) {
				$ds_in_str .= ")";
				push_out_data_source_templates($did_vals, $ds_in_str, $rra_vals, $rra_in_str);
				$ds_cnt    = 0;
				$did_cnt   = 0;
				$rra_cnt   = 0;
				$ds_in_str = "";
				$did_vals  = "";
				$rra_vals  = "";
			}
		}
	}
	
	if ($ds_cnt > 0) {
		$ds_in_str .= ")";
		push_out_data_source_templates($did_vals, $ds_in_str, $rra_vals, $rra_in_str);
	}
}

/* push out changed data template fields to related data sources
 * @parm string $did_vals	- data input data fields
 * @parm string $ds_in_str	- all data sources, formatted as SQL "IN" clause
 * @parm string $rra_vals	- new set of rra associations
 * @parm string $rra_in_str	- all rra associations, formatted as SQL "IN" clause
 */
function push_out_data_source_templates($did_vals, $ds_in_str, $rra_vals, $rra_in_str) {

	/* update all templated input fields */
	if ($did_vals != "") {
		db_execute("INSERT INTO data_input_data
			(data_input_field_id,data_template_data_id,value)
			VALUES $did_vals
			ON DUPLICATE KEY UPDATE value=VALUES(value)");
	}

	/* remove old RRA associations */
	if ($ds_in_str != "" && $rra_in_str != "") {
		db_execute("DELETE
			FROM data_template_data_rra
			WHERE data_template_data_id IN $ds_in_str
			AND rra_id NOT IN $rra_in_str");
	}

	/* ... and add new ones */
	if ($rra_vals != "") {
			db_execute("INSERT INTO data_template_data_rra
				(data_template_data_id,rra_id)
			VALUES $rra_vals
			ON DUPLICATE KEY UPDATE rra_id=VALUES(rra_id)");
	}
}

/* push_out_data_source_item - pushes out templated data template item fields to all matching
	children
   @arg $data_template_rrd_id - the id of the data template item to push out values for */
function push_out_data_source_item($data_template_rrd_id) {
	global $struct_data_source_item;

	/* get information about this data template */
	$data_template_rrd = db_fetch_row("select * from data_template_rrd where id=$data_template_rrd_id");

	/* must be a data template */
	if (empty($data_template_rrd["data_template_id"])) { return 0; }

	/* loop through each data source column name (from the above array) */
	reset($struct_data_source_item);
	while (list($field_name, $field_array) = each($struct_data_source_item)) {
		/* are we allowed to push out the column? */
		if (((empty($data_template_rrd{"t_" . $field_name})) || (preg_match("/FORCE:/", $field_name))) && ((isset($data_template_rrd{"t_" . $field_name})) && (isset($data_template_rrd[$field_name])))) {
			db_execute("update data_template_rrd set $field_name='" . addslashes($data_template_rrd[$field_name]) . "' where local_data_template_rrd_id=" . $data_template_rrd["id"]);
		}
	}
}

/* push_out_data_source - pushes out templated data template fields to all matching children
   @arg $data_template_data_id - the id of the data template to push out values for */
function push_out_data_source($data_template_data_id) {
	global $struct_data_source;

	/* get information about this data template */
	$data_template_data = db_fetch_row("select * from data_template_data where id=$data_template_data_id");

	/* must be a data template */
	if (empty($data_template_data["data_template_id"])) { return 0; }

	/* loop through each data source column name (from the above array) */
	reset($struct_data_source);
	while (list($field_name, $field_array) = each($struct_data_source)) {
		/* are we allowed to push out the column? */
		if (((empty($data_template_data{"t_" . $field_name})) || (preg_match("/FORCE:/", $field_name))) && ((isset($data_template_data{"t_" . $field_name})) && (isset($data_template_data[$field_name])))) {
			db_execute("update data_template_data set $field_name='" . addslashes($data_template_data[$field_name]) . "' where local_data_template_data_id=" . $data_template_data["id"]);

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
	global $struct_data_source, $struct_data_source_item;

	/* always update tables to new data template (or no data template) */
	db_execute("UPDATE data_local SET data_template_id=$data_template_id WHERE id=$local_data_id");

	/* get data about the template and the data source */
	$data = db_fetch_row("SELECT * FROM data_template_data WHERE local_data_id=$local_data_id");
	$template_data = (($data_template_id == "0") ? $data : db_fetch_row("select * from data_template_data where local_data_id=0 and data_template_id=$data_template_id"));

	/* determine if we are here for the first time, or coming back */
	if ((db_fetch_cell("select local_data_template_data_id from data_template_data where local_data_id=$local_data_id") == "0") ||
		(db_fetch_cell("select local_data_template_data_id from data_template_data where local_data_id=$local_data_id") == "")) {
		$new_save = true;
	}else{
		$new_save = false;
	}

	/* some basic field values that ALL data sources should have */
	$save["id"] = (isset($data["id"]) ? $data["id"] : 0);
	$save["local_data_template_data_id"] = $template_data["id"];
	$save["local_data_id"] = $local_data_id;
	$save["data_template_id"] = $data_template_id;

	/* loop through the "templated field names" to find to the rest... */
	reset($struct_data_source);
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
	$data_rrds_list = db_fetch_assoc("SELECT * FROM data_template_rrd WHERE local_data_id=$local_data_id");
	$template_rrds_list = (($data_template_id == "0") ? $data_rrds_list : db_fetch_assoc("SELECT * FROM data_template_rrd WHERE local_data_id=0 AND data_template_id=$data_template_id"));

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
	$data_input_data = db_fetch_assoc("SELECT data_input_field_id, t_value, value
		FROM data_input_data
		WHERE data_template_data_id=" . $template_data["id"]);

	/* this section is before most everthing else so we can determine if this is a new save, by checking
	the status of the 'local_data_template_data_id' column */
	if (sizeof($data_input_data) > 0) {
	foreach ($data_input_data as $item) {
		/* always propagate on a new save, only propagate templated fields thereafter */
		if (($new_save == true) || (empty($item["t_value"]))) {
			db_execute("REPLACE INTO data_input_data
				(data_input_field_id,data_template_data_id,t_value,value)
				VALUES (" . $item["data_input_field_id"] . ", $data_template_data_id, '" . $item["t_value"] . "', '" . $item["value"] . "')");
		}
	}
	}

	/* make sure to update the 'data_template_data_rra' table for each data source */
	$data_rra = db_fetch_assoc("SELECT rra_id
		FROM data_template_data_rra
		WHERE data_template_data_id=" . $template_data["id"]);

	db_execute("DELETE FROM data_template_data_rra
		WHERE data_template_data_id=$data_template_data_id");

	if (sizeof($data_rra) > 0) {
	foreach ($data_rra as $rra) {
		db_execute("INSERT INTO data_template_data_rra
			(data_template_data_id,rra_id)
			VALUES ($data_template_data_id," . $rra["rra_id"] . ")");
	}
	}
}

/* push_out_graph - pushes out templated graph template fields to all matching children
   @arg $graph_template_graph_id - the id of the graph template to push out values for */
function push_out_graph($graph_template_graph_id) {
	global $struct_graph;

	/* get information about this graph template */
	$graph_template_graph = db_fetch_row("select * from graph_templates_graph where id=$graph_template_graph_id");

	/* must be a graph template */
	if ($graph_template_graph["graph_template_id"] == 0) { return 0; }

	/* loop through each graph column name (from the above array) */
	reset($struct_graph);
	while (list($field_name, $field_array) = each($struct_graph)) {
		/* are we allowed to push out the column? */
		if (empty($graph_template_graph{"t_" . $field_name})) {
			db_execute("update graph_templates_graph set $field_name='" . addslashes($graph_template_graph[$field_name]) . "' where local_graph_template_graph_id=" . $graph_template_graph["id"]);

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
			db_execute("update graph_templates_item set " . $graph_input["column_name"] . "='" . addslashes($value{$graph_input["column_name"]}) . "' where local_graph_id=" . $value["local_graph_id"] . " and local_graph_template_item_id=$graph_template_item_id");
		}
	}
	}
}

/* push_out_graph_item - pushes out templated graph template item fields to all matching
	children. if the graph template item is part of a graph input, the field will not be
	pushed out
   @arg $graph_template_item_id - the id of the graph template item to push out values for */
function push_out_graph_item($graph_template_item_id) {
	global $struct_graph_item;

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
		from (graph_template_input, graph_template_input_defs)
		where graph_template_input.graph_template_id=" . $graph_template_item["graph_template_id"] . "
		and graph_template_input.id=graph_template_input_defs.graph_template_input_id
		and graph_template_input_defs.graph_template_item_id=$graph_template_item_id");

	$graph_item_inputs = array_rekey($graph_item_inputs, "column_name", "graph_template_item_id");

	/* loop through each graph item column name (from the above array) */
	reset($struct_graph_item);
	while (list($field_name, $field_array) = each($struct_graph_item)) {
		/* are we allowed to push out the column? */
		if (!isset($graph_item_inputs[$field_name])) {
			db_execute("update graph_templates_item set $field_name='" . addslashes($graph_template_item[$field_name]) . "' where local_graph_template_item_id=" . $graph_template_item["id"]);
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
	global $struct_graph, $struct_graph_item;

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
	$save["id"] = (isset($graph_list["id"]) ? $graph_list["id"] : 0);
	$save["local_graph_template_graph_id"] = $template_graph_list["id"];
	$save["local_graph_id"] = $local_graph_id;
	$save["graph_template_id"] = $graph_template_id;

	/* loop through the "templated field names" to find to the rest... */
	reset($struct_graph);
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
		from (graph_template_input,graph_template_input_defs)
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
	db_execute("insert into graph_templates (id,name,hash) values (0,'" . str_replace("<graph_title>", db_fetch_cell("select title from graph_templates_graph where local_graph_id=$local_graph_id"), $graph_title) . "','" . get_hash_graph_template(0) . "')");
	$graph_template_id = db_fetch_insert_id();

	/* update graph to point to the new template */
	db_execute("update graph_templates_graph set local_graph_id=0,local_graph_template_graph_id=0,graph_template_id=$graph_template_id where local_graph_id=$local_graph_id");
	db_execute("update graph_templates_item set local_graph_id=0,local_graph_template_item_id=0,graph_template_id=$graph_template_id,task_item_id=0 where local_graph_id=$local_graph_id");

	/* create hashes for the graph template items */
	$items = db_fetch_assoc("select id from graph_templates_item where graph_template_id='$graph_template_id' and local_graph_id=0");
	for ($j=0; $j<count($items); $j++) {
		db_execute("update graph_templates_item set hash='" . get_hash_graph_template($items[$j]["id"], "graph_template_item") . "' where id=" . $items[$j]["id"]);
	}

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
	db_execute("insert into data_template (id,name,hash) values (0,'" . str_replace("<ds_title>", db_fetch_cell("select name from data_template_data where local_data_id=$local_data_id"), $data_source_title) . "','" .  get_hash_data_template(0) . "')");
	$data_template_id = db_fetch_insert_id();

	/* update graph to point to the new template */
	db_execute("update data_template_data set local_data_id=0,local_data_template_data_id=0,data_template_id=$data_template_id where local_data_id=$local_data_id");
	db_execute("update data_template_rrd set local_data_id=0,local_data_template_rrd_id=0,data_template_id=$data_template_id where local_data_id=$local_data_id");

	/* create hashes for the data template items */
	$items = db_fetch_assoc("select id from data_template_rrd where data_template_id='$data_template_id' and local_data_id=0");
	for ($j=0; $j<count($items); $j++) {
		db_execute("update data_template_rrd set hash='" . get_hash_data_template($items[$j]["id"], "data_template_item") . "' where id=" . $items[$j]["id"]);
	}

	/* delete the old graph local entry */
	db_execute("delete from data_local where id=$local_data_id");
	db_execute("delete from poller_item where local_data_id=$local_data_id");
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

		$suggested_values_graph = array();
		if (sizeof($suggested_values) > 0) {
		foreach ($suggested_values as $suggested_value) {
			/* once we find a match; don't try to find more */
			if (!isset($suggested_values_graph[$graph_template_id]{$suggested_value["field_name"]})) {
				$subs_string = substitute_snmp_query_data($suggested_value["text"], $host_id, $snmp_query_array["snmp_query_id"], $snmp_query_array["snmp_index"], read_config_option("max_data_query_field_length"));
				/* if there are no '|' characters, all of the substitutions were successful */
				if (!strstr($subs_string, "|query")) {
					db_execute("UPDATE graph_templates_graph SET " . $suggested_value["field_name"] . "='" . $suggested_value["text"] . "' WHERE local_graph_id=" . $cache_array["local_graph_id"]);

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
		from (data_template, data_template_rrd, graph_templates_item)
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

			$suggested_values_ds = array();
			if (sizeof($suggested_values) > 0) {
			foreach ($suggested_values as $suggested_value) {
				/* once we find a match; don't try to find more */
				if (!isset($suggested_values_ds{$data_template["id"]}{$suggested_value["field_name"]})) {
					$subs_string = substitute_snmp_query_data($suggested_value["text"], $host_id, $snmp_query_array["snmp_query_id"], $snmp_query_array["snmp_index"], read_config_option("max_data_query_field_length"));

					/* if there are no '|' characters, all of the substitutions were successful */
					if (!strstr($subs_string, "|query")) {
						if (sizeof(db_fetch_row("show columns from data_template_data like '" . $suggested_value["field_name"] . "'"))) {
							db_execute("UPDATE data_template_data SET " . $suggested_value["field_name"] . "='" . $suggested_value["text"] . "' WHERE local_data_id=" . $cache_array["local_data_id"]{$data_template["id"]});
						}

						/* once we find a working value, stop */
						$suggested_values_ds{$data_template["id"]}{$suggested_value["field_name"]} = true;

						if ((sizeof(db_fetch_row("show columns from data_template_rrd like '" . $suggested_value["field_name"] . "'"))) &&
							(!substr_count($subs_string, "|"))) {
							db_execute("UPDATE data_template_rrd SET " . $suggested_value["field_name"] . "='" . $suggested_value["text"] . "' WHERE local_data_id=" . $cache_array["local_data_id"]{$data_template["id"]});
						}
					}
				}
			}
			}
		}

		if (is_array($snmp_query_array)) {
			$data_input_field = array_rekey(db_fetch_assoc("SELECT
				data_input_fields.id,
				data_input_fields.type_code
				FROM (snmp_query,data_input,data_input_fields)
				WHERE snmp_query.data_input_id=data_input.id
				AND data_input.id=data_input_fields.data_input_id
				AND (data_input_fields.type_code='index_type'
					OR data_input_fields.type_code='index_value'
					OR data_input_fields.type_code='output_type')
				AND snmp_query.id=" . $snmp_query_array["snmp_query_id"]), "type_code", "id");

			$snmp_cache_value = db_fetch_cell("SELECT field_value
				FROM host_snmp_cache
				WHERE host_id='$host_id'
				AND snmp_query_id='" . $snmp_query_array["snmp_query_id"] . "'
				AND field_name='" . $snmp_query_array["snmp_index_on"] . "'
				AND snmp_index='" . $snmp_query_array["snmp_index"] . "'");

			/* save the value to index on (ie. ifindex, ifip, etc) */
			db_execute("REPLACE INTO data_input_data
				(data_input_field_id, data_template_data_id, t_value, value)
				VALUES (" . $data_input_field["index_type"] . ", $data_template_data_id, '', '" . $snmp_query_array["snmp_index_on"] . "')");

			/* save the actual value (ie. 3, 192.168.1.101, etc) */
			db_execute("REPLACE INTO data_input_data
				(data_input_field_id,data_template_data_id,t_value,value)
				VALUES (" . $data_input_field["index_value"] . ",$data_template_data_id,'','" . addslashes($snmp_cache_value) . "')");

			/* set the expected output type (ie. bytes, errors, packets) */
			db_execute("REPLACE INTO data_input_data
				(data_input_field_id,data_template_data_id,t_value,value)
				VALUES (" . $data_input_field["output_type"] . ",$data_template_data_id,'','" . $snmp_query_array["snmp_query_graph_id"] . "')");

			/* now that we have put data into the 'data_input_data' table, update the snmp cache for ds's */
			update_data_source_data_query_cache($cache_array["local_data_id"]{$data_template["id"]});
		}

		/* suggested values: data source */
		if (isset($suggested_values_array[$graph_template_id]["data_template"]{$data_template["id"]})) {
			reset($suggested_values_array[$graph_template_id]["data_template"]{$data_template["id"]});
			while (list($field_name, $field_value) = each($suggested_values_array[$graph_template_id]["data_template"]{$data_template["id"]})) {
				db_execute("UPDATE data_template_data
					SET $field_name='$field_value'
					WHERE local_data_id=" . $cache_array["local_data_id"]{$data_template["id"]});
			}
		}

		/* suggested values: data source item */
		if (isset($suggested_values_array[$graph_template_id]["data_template_item"])) {
			reset($suggested_values_array[$graph_template_id]["data_template_item"]);
			while (list($data_template_item_id, $field_array) = each($suggested_values_array[$graph_template_id]["data_template_item"])) {
				while (list($field_name, $field_value) = each($field_array)) {
					$data_source_item_id = db_fetch_cell("select id from data_template_rrd where local_data_template_rrd_id=$data_template_item_id and local_data_id=" . $cache_array["local_data_id"]{$data_template["id"]});
					db_execute("UPDATE data_template_rrd
						SET $field_name='$field_value'
						WHERE id=$data_source_item_id");
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
		from (graph_templates_item,data_template_rrd)
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

	# now that we have the id of the new host, we may plugin postprocessing code
	$save["id"] = $cache_array["local_graph_id"];
	$save["graph_template_id"] = $graph_template_id;	// attention: unset!
	if (is_array($snmp_query_array)) {
		$save["snmp_query_id"] = $snmp_query_array["snmp_query_id"];
		$save["snmp_index"] = $snmp_query_array["snmp_index"];
	} else {
		$save["snmp_query_id"] = 0;
		$save["snmp_index"] = 0;
	}
	api_plugin_hook_function('create_complete_graph_from_template', $save);

	return $cache_array;
}

?>
