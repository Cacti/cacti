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

include("./include/auth.php");
include_once("./lib/utility.php");
include_once("./lib/snmp.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	case 'query_remove':
		host_remove_query();
		
		header("Location: host.php?action=edit&id=" . $_GET["host_id"]);
		break;
	case 'query_reload':
		host_reload_query();
		
		header("Location: host.php?action=edit&id=" . $_GET["host_id"]);
		break;
	case 'new_graphs':
		include_once("./include/top_header.php");
		
		host_new_graphs();
		
		include_once("./include/bottom_footer.php");
		break;
	case 'remove':
		host_remove();
		
		header ("Location: host.php");
		break;
	case 'edit':
		include_once("./include/top_header.php");
		
		host_edit();
		
		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");
		
		host();
		
		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset($_POST["add_y"])) && (!empty($_POST["snmp_query_id"]))) {
		db_execute("replace into host_snmp_query (host_id,snmp_query_id) values (" . $_POST["id"] . "," . $_POST["snmp_query_id"] . ")");
		
		/* recache snmp data */
		data_query($_POST["id"], $_POST["snmp_query_id"]);
		
		header("Location: host.php?action=edit&id=" . $_POST["id"]);
		exit;
	}
	
	if ((isset($_POST["save_component_host"])) && (!isset($_POST["add_y"]))) {
		$save["id"] = $_POST["id"];
		$save["host_template_id"] = $_POST["host_template_id"];
		$save["description"] = form_input_validate($_POST["description"], "description", "", false, 3);
		$save["hostname"] = form_input_validate($_POST["hostname"], "hostname", "", true, 3);
		$save["management_ip"] = form_input_validate($_POST["management_ip"], "management_ip", "^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$", false, 3);
		$save["snmp_community"] = form_input_validate($_POST["snmp_community"], "snmp_community", "", true, 3);
		$save["snmp_version"] = form_input_validate($_POST["snmp_version"], "snmp_version", "", true, 3);
		$save["snmp_username"] = form_input_validate($_POST["snmp_username"], "snmp_username", "", true, 3);;
		$save["snmp_password"] = form_input_validate($_POST["snmp_password"], "snmp_password", "", true, 3);
		$save["disabled"] = form_input_validate((isset($_POST["disabled"]) ? $_POST["disabled"] : ""), "disabled", "", true, 3);
		
		if (!is_error_message()) {
			$host_id = sql_save($save, "host");
			
			if ($host_id) {
				raise_message(1);
				
				/* push out relavant fields to data sources using this host */
				push_out_host($host_id,0);
				
				/* the host subsitution cache is now stale; purge it */
				kill_session_var("sess_host_cache_array");
				
				/* update title cache for graph and data source */
				update_data_source_title_cache_from_host($host_id);
				update_graph_title_cache_from_host($host_id);
			}else{
				raise_message(2);
			}
			
			/* if the user changes the host template, add each snmp query associated with it */
			if (($_POST["host_template_id"] != $_POST["_host_template_id"]) && ($_POST["host_template_id"] != "0")) {
				$snmp_queries = db_fetch_assoc("select snmp_query_id from host_template_snmp_query where host_template_id=" . $_POST["host_template_id"]);
				
				if (sizeof($snmp_queries) > 0) {
				foreach ($snmp_queries as $snmp_query) {
					db_execute("replace into host_snmp_query (host_id,snmp_query_id) values ($host_id," . $snmp_query["snmp_query_id"] . ")");
					
					/* recache snmp data */
					data_query($host_id, $snmp_query["snmp_query_id"]);
				}
				}
			}
			
			/* summarize the 'create graph from host template/snmp index' stuff into an array */
			while (list($var, $val) = each($_POST)) {
				if (preg_match('/^cg_\d+$/', $var)) {
					$graph_template_id = preg_replace('/^cg_(\d+)$/', "\\1", $var);
					
					$selected_graphs["cg"][$graph_template_id][$graph_template_id] = true;
				}elseif (preg_match('/^ccg$/', $var)) {
					$selected_graphs["cg"]{$_POST["cg_g"]}{$_POST["cg_g"]} = true;
				}elseif (preg_match('/^sg_\d+_\S+$/', $var)) {
					$snmp_query_id = preg_replace('/^sg_(\d+)_(\S+)$/', "\\1", $var);
					$snmp_query_graph_id = $_POST{"sgg_" . $snmp_query_id}; 
					$snmp_index = preg_replace('/^sg_(\d+)_(\S+)$/', "\\2", $var);
					
					$selected_graphs["sg"][$snmp_query_id][$snmp_query_graph_id][$snmp_index] = true;
				}
			}
			
			if (isset($selected_graphs)) {
				host_new_graphs($host_id, $_POST["host_template_id"], $selected_graphs);
				exit;
			}
		}
		
		if ((is_error_message()) || ($_POST["host_template_id"] != $_POST["_host_template_id"])) {
			header("Location: host.php?action=edit&id=" . (empty($host_id) ? $_POST["id"] : $host_id));
		}else{
			header("Location: host.php");
		}
	}
	
	if (isset($_POST["save_component_new_graphs"])) {
		host_new_graphs_save();
		
		header("Location: host.php");
	}
}

/* ---------------------
    Misc Functions
   --------------------- */

function draw_edit_form_row($field_array, $field_name, $previous_value) {
	$field_array["value"] = $previous_value;
	
	draw_edit_form(
		array(
			"config" => array(
				"no_form_tag" => true,
				"force_row_color" => "F5F5F5"
				),
			"fields" => array(
				$field_name => $field_array
				)
			)
		);
}

/* ---------------------
    Host Functions
   --------------------- */

function host_reload_query() {
	data_query($_GET["host_id"], $_GET["id"]);
}

function host_remove_query() {
	db_execute("delete from host_snmp_cache where snmp_query_id=" . $_GET["id"] . " and host_id=" . $_GET["host_id"]);
	db_execute("delete from host_snmp_query where snmp_query_id=" . $_GET["id"] . " and host_id=" . $_GET["host_id"]);
}

function host_remove() {
	global $config;
	
	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the host <strong>'" . db_fetch_cell("select description from host where id=" . $_GET["id"]) . "'</strong>?", $_SERVER["HTTP_REFERER"], "host.php?action=remove&id=" . $_GET["id"]);
		include("./include/bottom_footer.php");
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from host where id=" . $_GET["id"]);
		db_execute("delete from host_snmp_query where host_id=" . $_GET["id"]);
		db_execute("delete from host_snmp_cache where host_id=" . $_GET["id"]);
		
		db_execute("update data_local set host_id=0 where host_id=" . $_GET["id"]);
		db_execute("update graph_local set host_id=0 where host_id=" . $_GET["id"]);
	}
}

function host_new_graphs_save() {
	global $struct_graph, $struct_data_source, $struct_graph_item, $struct_data_source_item, $paths;
	
	$selected_graphs_array = unserialize(stripslashes($_POST["selected_graphs_array"]));
	
	while (list($form_type, $form_array) = each($selected_graphs_array)) {
		$current_form_type = $form_type;
		
		while (list($form_id1, $form_array2) = each($form_array)) {
			if ($form_type == "cg") {
				$graph_template_id = $form_id1;
			}elseif ($form_type == "sg") {
				while (list($form_id2, $form_array3) = each($form_array2)) {
					$snmp_query_id = $form_id1;
					$snmp_query_graph_id = $form_id2;
					$snmp_index_array = $form_array3;
					
					/* we will use this at the very end when saving */
					$snmp_query_to_snmp_query_graphs_array[$snmp_query_id] = $snmp_query_graph_id;
				}
				
				$graph_template_id = db_fetch_cell("select graph_template_id from snmp_query_graph where id=$snmp_query_graph_id");
				
				$snmp_query_array = get_data_query_array($snmp_query_id);
			}
			
			unset($save);
			
			$save["id"] = 0;
			$save["graph_template_id"] = $graph_template_id;
			$save["host_id"] = $_POST["host_id"];
			
			if ($current_form_type == "cg") {
				$local_graph_id = sql_save($save, "graph_local");
				change_graph_template($local_graph_id, $graph_template_id, true);
				
				$new_graph_templates["cg"][$graph_template_id] = $local_graph_id;
			}elseif ($current_form_type == "sg") {
				reset($snmp_index_array);
				
				while (list($snmp_index, $true) = each($snmp_index_array)) {
					$local_graph_id = sql_save($save, "graph_local");
					change_graph_template($local_graph_id, $graph_template_id, true);
					
					/* cache arrays; used down below */
					if (isset($new_graph_templates["sg"][$snmp_query_id][$graph_template_id])) {
						$new_graph_templates["sg"][$snmp_query_id][$graph_template_id] .= $local_graph_id . ":";
					}else{
						$new_graph_templates["sg"][$snmp_query_id][$graph_template_id] = $local_graph_id . ":";
					}
					
					$new_graph_snmp_indexes[$snmp_query_id][$local_graph_id] = $snmp_index;
					$new_graph_index_to_local[$snmp_query_id][$snmp_index] = $local_graph_id;
					
					/* suggested values for snmp query code */
					$suggested_values = db_fetch_assoc("select text,field_name from snmp_query_graph_sv where snmp_query_graph_id=$snmp_query_graph_id order by sequence");
					
					if (sizeof($suggested_values) > 0) {
					foreach ($suggested_values as $suggested_value) {
						/* once we find a match; don't try to find more */
						if (!isset($_POST{"g_" . $snmp_query_id . "_" . $graph_template_id . "_" . $snmp_index . "_" . $suggested_value["field_name"]})) {
							$subs_string = subsitute_snmp_query_data($suggested_value["text"], "|", "|", $_POST["host_id"], $snmp_query_id, $snmp_index);
							
							/* if there are no '|' characters, all of the subsitutions were successful */
							if (!strstr($subs_string, "|query")) {
								$_POST{"g_" . $snmp_query_id . "_" . $graph_template_id . "_" . $snmp_index . "_" . $suggested_value["field_name"]} = $suggested_value["text"];
							}
						}
					}
					}
				}
			}
			
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
				$save["host_id"] = $_POST["host_id"];
				
				if ($current_form_type == "cg") {
					$local_data_id = sql_save($save, "data_local");
					change_data_template($local_data_id, $data_template["id"]);
					
					$new_data_templates["cg"][$graph_template_id]{$data_template["id"]} = $local_data_id;
				}elseif ($current_form_type == "sg") {
					reset($snmp_index_array);
					
					while (list($snmp_index, $true) = each($snmp_index_array)) {
						$local_data_id = sql_save($save, "data_local");
						change_data_template($local_data_id, $data_template["id"]);
						
						/* cache arrays; used down below */
						if (isset($new_data_templates["sg"][$snmp_query_id]{$data_template["id"]})) {
							$new_data_templates["sg"][$snmp_query_id]{$data_template["id"]} .= $local_data_id . ":";
						}else{
							$new_data_templates["sg"][$snmp_query_id]{$data_template["id"]} = $local_data_id . ":";
						}
						
						$new_data_snmp_indexes[$snmp_query_id][$local_data_id] = $snmp_index;
						$new_data_index_to_local[$snmp_query_id]{$data_template["id"]}[$snmp_index] = $local_data_id;
						
						/* suggested values for snmp query code */
						$suggested_values = db_fetch_assoc("select text,field_name from snmp_query_graph_rrd_sv where snmp_query_graph_id=$snmp_query_graph_id and data_template_id=" . $data_template["id"] . " order by sequence");
						
						if (sizeof($suggested_values) > 0) {
						foreach ($suggested_values as $suggested_value) {
							/* once we find a match; don't try to find more */
							if (!isset($_POST{"d_" . $snmp_query_id . "_" . $graph_template_id . "_" . $data_template["id"] . "_" . $snmp_index . "_" . $suggested_value["field_name"]})) {
								$subs_string = subsitute_snmp_query_data($suggested_value["text"], "|", "|", $_POST["host_id"], $snmp_query_id, $snmp_index);
								
								/* if there are no '|' characters, all of the subsitutions were successful */
								if (!strstr($subs_string, "|query")) {
									$_POST{"d_" . $snmp_query_id . "_" . $graph_template_id . "_" . $data_template["id"] . "_" . $snmp_index . "_" . $suggested_value["field_name"]} = $suggested_value["text"];
								}
							}
						}
						}
					}
				}
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
				if ($current_form_type == "cg") {
					$graph_template_item_id = db_fetch_cell("select id from graph_templates_item where local_graph_template_item_id=" . $template_item["id"] . " and local_graph_id=" . $new_graph_templates["cg"][$graph_template_id]);
					$data_template_rrd_id = db_fetch_cell("select id from data_template_rrd where local_data_template_rrd_id=" . $template_item["data_template_rrd_id"] . " and local_data_id=" . $new_data_templates["cg"][$graph_template_id]{$template_item["data_template_id"]});
					
					db_execute("update graph_templates_item set task_item_id='$data_template_rrd_id' where id=$graph_template_item_id");
				}elseif (($current_form_type == "sg") && (isset($new_graph_snmp_indexes[$snmp_query_id])) && (isset($new_data_snmp_indexes[$snmp_query_id]))) {
					reset($new_graph_snmp_indexes[$snmp_query_id]);
					
					while (list($local_graph_id, $snmp_index) = each($new_graph_snmp_indexes[$snmp_query_id])) {
						$local_data_id = $new_data_index_to_local[$snmp_query_id]{$template_item["data_template_id"]}[$snmp_index];
						
						$graph_template_item_id = db_fetch_cell("select id from graph_templates_item where local_graph_template_item_id=" . $template_item["id"] . " and local_graph_id=$local_graph_id");
						$data_template_rrd_id = db_fetch_cell("select id from data_template_rrd where local_data_template_rrd_id=" . $template_item["data_template_rrd_id"] . " and local_data_id=$local_data_id");
						
						if (!empty($data_template_rrd_id)) {
							db_execute("update graph_templates_item set task_item_id='$data_template_rrd_id' where id=$graph_template_item_id");
						}
					}
				}
			}
			}
		}
	}
	
	/* go ahead and write out values from the POST form to our new data */
	
	while (list($var, $val) = each($_POST)) {
		if (preg_match("/^g_(\d+)_(\d+)_([^_]+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: (opt) snmp index, 4: field_name */
			if (empty($matches[3])) { /* ALL new graphs */
				db_execute("update graph_templates_graph set " . $matches[4] . "='$val' where " . (empty($matches[1]) ? "local_graph_id=" . $new_graph_templates["cg"]{$matches[2]} : array_to_sql_or(split(":", $new_graph_templates["sg"]{$matches[1]}{$matches[2]}),"local_graph_id")));
			}else{ /* only new graphs for this snmp_index */
				db_execute("update graph_templates_graph set " . $matches[4] . "='$val' where local_graph_id=" . $new_graph_index_to_local{$matches[1]}{$matches[3]});
			}
		}elseif (preg_match("/^gi_(\d+)_(\d+)_(\d+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: graph_template_input_id, 4:field_name */
			/* we need to find out which graph items will be affected by saving this particular item */
			$item_list = db_fetch_assoc("select
				graph_templates_item.id
				from graph_template_input_defs,graph_templates_item
				where graph_template_input_defs.graph_template_item_id=graph_templates_item.local_graph_template_item_id
				and " . (empty($matches[1]) ? "graph_templates_item.local_graph_id=" . $new_graph_templates["cg"]{$matches[2]} : array_to_sql_or(split(":", $new_graph_templates["sg"]{$matches[1]}{$matches[2]}),"graph_templates_item.local_graph_id")) . "
				and graph_template_input_defs.graph_template_input_id=" . $matches[3]);
			
			/* loop through each item affected and update column data */
			if (sizeof($item_list) > 0) {
			foreach ($item_list as $item) {
				db_execute("update graph_templates_item set " . $matches[4] . "='$val' where id=" . $item["id"]);
			}
			}
		}elseif (preg_match("/^d_(\d+)_(\d+)_(\d+)_([^_]+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4: (opt) snmp_index, 5:field_name */
			if (empty($matches[4])) { /* ALL new graphs */
				db_execute("update data_template_data set " . $matches[5] . "='$val' where " . (empty($matches[1]) ? "local_data_id=" . $new_data_templates["cg"]{$matches[2]}{$matches[3]} : array_to_sql_or(split(":", $new_data_templates["sg"]{$matches[1]}{$matches[3]}),"local_data_id")));
			}else{ /* only new data sources for this snmp_index */
				db_execute("update data_template_data set " . $matches[5] . "='$val' where local_data_id=" . $new_data_index_to_local{$matches[1]}{$matches[3]}{$matches[4]});
			}
		}elseif (preg_match("/^di_(\d+)_(\d+)_(\d+)_(\d+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:local_data_template_rrd_id, 5:field_name */
			db_execute("update data_template_rrd set " . $matches[5] . "='$val' where " . (empty($matches[1]) ? "local_data_id=" . $new_data_templates["cg"]{$matches[2]}{$matches[3]} : array_to_sql_or(split(":", $new_data_templates["sg"]{$matches[1]}{$matches[3]}),"local_data_id")) . " and local_data_template_rrd_id=" . $matches[4]);
		}elseif ((preg_match("/^sg_(\d+)_(\d+)_(\d+)/", $var, $matches)) && (isset($new_data_snmp_indexes{$matches[1]}))) { /* 1: snmp_query_id, 2: data_template_id, 3: data_input_field_id */
			reset($new_data_snmp_indexes{$matches[1]});
			
			while (list($local_data_id, $snmp_index) = each($new_data_snmp_indexes{$matches[1]})) {
				$data_template_data_id = db_fetch_cell("select id from data_template_data where local_data_id=$local_data_id");
				/* save the value to index on (ie. ifindex, ifip, etc) */
				db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,t_value,value) values (" . $matches[3] . ",$data_template_data_id,'','$val')");
				
				$data_input_field_id = db_fetch_cell("select data_input_field_id from snmp_query_field where snmp_query_id=" . $matches[1] . " and action_id=2");
				$snmp_cache_value = db_fetch_cell("select field_value from host_snmp_cache where host_id=" . $_POST["host_id"] . " and field_name='$val' and snmp_index='$snmp_index'");
				
				/* save the actual value (ie. 3, 192.168.1.101, etc) */
				db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,t_value,value) values ($data_input_field_id,$data_template_data_id,'','$snmp_cache_value')");
				
				/* set the expected output type (ie. bytes, errors, packets) */
				$data_input_field_id = db_fetch_cell("select data_input_field_id from snmp_query_field where snmp_query_id=" . $matches[1] . " and action_id=3");
				db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,t_value,value) values ($data_input_field_id,$data_template_data_id,'','" . $snmp_query_to_snmp_query_graphs_array{$matches[1]} . "')");
				
				/* now that we have put data into the 'data_input_data' table, update the snmp cache for ds's */
				update_data_source_snmp_query_cache($local_data_id);
			}
			
			reset($new_graph_snmp_indexes{$matches[1]});
			
			while (list($local_graph_id, $snmp_index) = each($new_graph_snmp_indexes{$matches[1]})) {
				/* now that we have put data into the 'data_input_data' table, update the snmp cache for graphs */
				update_graph_snmp_query_cache($local_graph_id);
			}
		}
	}
	
	/* update title cache */
	update_data_source_title_cache_from_host($_POST["host_id"]);
	update_graph_title_cache_from_host($_POST["host_id"]);
	
	/* lastly push host-specific information to our data sources */
	push_out_host($_POST["host_id"],0);
}

function host_new_graphs($host_id, $host_template_id, $selected_graphs_array) {
	include_once("./include/top_header.php");
	
	global $colors, $paths, $struct_graph, $row_counter, $struct_data_source, $struct_graph_item, $struct_data_source_item;
	
	print "<form method='post' action='host.php'>\n";
	
	$snmp_query_id = 0;
	while (list($form_type, $form_array) = each($selected_graphs_array)) {
		while (list($form_id1, $form_array2) = each($form_array)) {
			if ($form_type == "cg") {
				$graph_template_id = $form_id1;
				
				start_box("<strong>Create 1 Graph from Host Template", "98%", $colors["header"], "3", "center", "");
			}elseif ($form_type == "sg") {
				while (list($form_id2, $form_array3) = each($form_array2)) {
					$snmp_query_id = $form_id1;
					$snmp_query_graph_id = $form_id2;
					$num_graphs = sizeof($form_array3);
					
					$snmp_query = db_fetch_row("select
						snmp_query.name,
						snmp_query.xml_path
						from snmp_query
						where snmp_query.id=$snmp_query_id");
					
					$graph_template_id = db_fetch_cell("select graph_template_id from snmp_query_graph where id=$snmp_query_graph_id");
					
					/* list each index that was selected */
					$i = 0;
					while (list($snmp_index, $one) = each($form_array3)) {
						$snmp_indexes[$snmp_query_id][$i] = $snmp_index;
						$i++;
					}
				}
				
				/* DRAW: SNMP Query */
				start_box("<strong>Create $num_graphs Graph" . (($num_graphs>1) ? "s" : "") . " from SNMP Query", "98%", $colors["header"], "3", "center", "");
				
				print "<tr><td colspan='2' bgcolor='#" . $colors["header_panel"] . "'><span style='font-size: 10px; color: white;'><strong>SNMP Query</strong> [" . $snmp_query["name"] . "]</span></td></tr>";
				
				$fields = db_fetch_assoc("select
					data_template.id as data_template_id,
					data_template.name,
					data_input_fields.name as field_name,
					data_input_fields.id as data_input_field_id
					from snmp_query_field,data_input_fields,data_template,snmp_query_graph,snmp_query_graph_rrd
					where snmp_query_field.data_input_field_id=data_input_fields.id
					and snmp_query_field.snmp_query_id=snmp_query_graph.snmp_query_id
					and snmp_query_graph.id=snmp_query_graph_rrd.snmp_query_graph_id
					and snmp_query_graph_rrd.data_template_id=data_template.id
					and snmp_query_field.snmp_query_id=$snmp_query_id
					and snmp_query_graph_rrd.snmp_query_graph_id=$snmp_query_graph_id
					and snmp_query_field.action_id=1
					group by snmp_query_graph_rrd.data_template_id");
				
				if (sizeof($fields) > 0) {
				foreach ($fields as $field) {
					print "	<tr bgcolor='#" . $colors["form_alternate1"] . "'>
							<td width='50%'>
								<font class='textEditTitle'>" . $field["name"] . " -> " . $field["field_name"] . "</font><br>
								The data for each new graph will be indexed off of the following field. Certain fields may
								have been removed from the list if they do not contain unique data. It is best to choose a field whose
								value never changes.
							</td>";
					
					$snmp_queries = get_data_query_array($snmp_query_id);
					
					$xml_outputs = array();
					
					/* create an SQL string that contains each index in this snmp_index_id */
					$sql_or = array_to_sql_or($snmp_indexes[$snmp_query_id], "snmp_index");
					
					/* list each of the input fields for this snmp query */
					while (list($field_name, $field_array) = each($snmp_queries["fields"][0])) {
						$field_array = $field_array[0];
						
						if ($field_array["direction"] == "input") {
							/* create a list of all values for this index */
							$field_values = db_fetch_assoc("select field_value from host_snmp_cache where host_id=$host_id and snmp_query_id=$snmp_query_id and field_name='$field_name' and $sql_or");
							
							/* aggregate the above list so there is no duplicates */
							$aggregate_field_values = array_rekey($field_values, "field_value", "field_value");
							
							/* fields that contain duplicate or empty values are not suitable to index off of */
							if (!((sizeof($aggregate_field_values) < sizeof($field_values)) || (in_array("", $aggregate_field_values) == true) || (sizeof($aggregate_field_values) == 0))) {
								$xml_outputs[$field_name] = $field_name . " (" . $field_array["name"] . ")";
							}
						}
					}
					
					print "<td>";
					form_dropdown("sg_" . $snmp_query_id . "_" . $field["data_template_id"] . "_" . $field["data_input_field_id"],$xml_outputs,"","","","","");
					print "</td>";
					
					print "</tr>\n";
				}
				}else{ print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td colspan='2' style='font-weight: bold; color: red;'>There appears to be a problem with your data query. Please make sure you have made at least one data template association.</td></tr>";
				}
			}
			
			$data_templates = db_fetch_assoc("select
				data_template.name as data_template_name,
				data_template_rrd.data_source_name,
				data_template_data.*
				from data_template, data_template_rrd, data_template_data, graph_templates_item
				where graph_templates_item.task_item_id=data_template_rrd.id
				and data_template_rrd.data_template_id=data_template.id
				and data_template_data.data_template_id=data_template.id
				and data_template_rrd.local_data_id=0
				and data_template_data.local_data_id=0
				and graph_templates_item.local_graph_id=0
				and graph_templates_item.graph_template_id=" . $graph_template_id . "
				group by data_template.id
				order by data_template.name");
			
			$graph_template = db_fetch_row("select
				graph_templates.name as graph_template_name,
				graph_templates_graph.*
				from graph_templates, graph_templates_graph
				where graph_templates.id=graph_templates_graph.graph_template_id
				and graph_templates.id=" . $graph_template_id . "
				and graph_templates_graph.local_graph_id=0");
			$graph_template_name = db_fetch_cell("select name from graph_templates where id=" . $graph_template_id);
			
			$graph_inputs = db_fetch_assoc("select
				*
				from graph_template_input
				where graph_template_id=" . $graph_template_id . "
				and column_name != 'task_item_id'
				order by name");
			
			reset($struct_graph);
			$drew_items = false;
			
			while (list($field_name, $field_array) = each($struct_graph)) {
				if ($graph_template{"t_" . $field_name} == "on") {
					if ($drew_items == false) {
						print "<tr><td colspan='2' bgcolor='#" . $colors["header_panel"] . "'><span style='font-size: 10px; color: white;'><strong>Graph</strong> [Template: " . $graph_template["graph_template_name"] . "]</span></td></tr>";
					}
					
					$row_counter = 1; /* so we have an all 'light' background */
					
					/* SUGGESTED VALUES: we must treat suggested values for snmp queries different because
					one entry here might might 20 graphs... so we can't automatically fill in the values. 
					if it is not an snmp query, automatically fill in the values right here. */
					if ((!empty($snmp_query_id)) && (sizeof(db_fetch_assoc("select id from snmp_query_graph_sv where snmp_query_graph_id=$snmp_query_graph_id and field_name='$field_name'")) > 0)) {
						print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>";
						print "<td><strong>" . $struct_graph[$field_name]["friendly_name"] . "</strong></td>";
						print "<td><em>Using Suggested Values</em> (see SNMP Query)</td>"; 
						print "</td></tr>\n";
					}elseif ((empty($snmp_query_id)) && (sizeof(db_fetch_assoc("select text from host_template_graph_sv where host_template_id=$host_template_id and graph_template_id=$graph_template_id and field_name='$field_name'")) > 0)) {
						$subs_string = db_fetch_cell("select text from host_template_graph_sv where host_template_id=$host_template_id and graph_template_id=$graph_template_id and field_name='$field_name'");
						draw_edit_form_row($field_array, "g_" . $snmp_query_id . "_" . $graph_template_id . "_0_" . $field_name, $subs_string);
					}else{
						draw_edit_form_row($field_array, "g_" . $snmp_query_id . "_" . $graph_template_id . "_0_" . $field_name, (isset($graph_template[$field_name]) ? $graph_template[$field_name] : ""));
					}
					
					$drew_items = true;
				}
			}
			
			/* DRAW: Graphs Inputs */
			if (sizeof($graph_inputs) > 0) {
			foreach ($graph_inputs as $graph_input) {
				if ($drew_items == false) {
					print "<tr><td colspan='2' bgcolor='#" . $colors["header_panel"] . "'><span style='font-size: 10px; color: white;'><strong>Graph Items</strong> [Template: " . $graph_template_name . "]</span></td></tr>";
				}
				
				$row_counter = 1; /* so we have an all 'light' background */
				
				$current_value = db_fetch_cell("select 
					graph_templates_item." . $graph_input["column_name"] . "
					from graph_templates_item,graph_template_input_defs 
					where graph_template_input_defs.graph_template_item_id=graph_templates_item.id 
					and graph_template_input_defs.graph_template_input_id=" . $graph_input["id"] . "
					and graph_templates_item.graph_template_id=" . $graph_template_id . "
					limit 0,1");
				
				print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>";
				
				print "	<td width='50%'>
						<font class='textEditTitle'>" . $graph_input["name"] . "</font>";
						if (!empty($graph_input["description"])) { print "<br>" . $graph_input["description"]; }
				print "	</td>\n";
				
				draw_edit_form_row($struct_graph_item{$graph_input["column_name"]}, "gi_" . $snmp_query_id . "_" . $graph_template_id . "_" . $graph_input["id"] . "_" . $graph_input["column_name"], $current_value);
				
				print "</tr>\n";
				
				$drew_items = true;
			}
			}
			
			/* DRAW: Data Sources */
			if (sizeof($data_templates) > 0) {
			foreach ($data_templates as $data_template) {
				reset($struct_data_source);
				$drew_items = false;
				
				while (list($field_name, $field_array) = each($struct_data_source)) {
					if (isset($data_template{"t_" . $field_name})) {
						if ($data_template{"t_" . $field_name} == "on") {
							if ($drew_items == false) {
								print "<tr><td colspan='2' bgcolor='#" . $colors["header_panel"] . "'><span style='font-size: 10px; color: white;'><strong>Data Source</strong> [Template: " . $data_template["data_template_name"] . "]</span></td></tr>";
							}
							
							$row_counter = 1; /* so we have an all 'light' background */
							
							/* SUGGESTED VALUES: we must treat suggested values for snmp queries different because
							one entry here might might 20 graphs... so we can't automatically fill in the values. 
							if it is not an snmp query, automatically fill in the values right here. */
							if ((!empty($snmp_query_id)) && (sizeof(db_fetch_assoc("select id from snmp_query_graph_rrd_sv where snmp_query_graph_id=$snmp_query_graph_id and data_template_id=" . $data_template["data_template_id"] . " and field_name='$field_name'")) > 0)) {
								print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>";
								print "<td><strong>" . $struct_data_source[$field_name]["friendly_name"] . "</strong></td>";
								print "<td><em>Using Suggested Values</em> (see SNMP Query)</td>"; 
								print "</td></tr>\n";
							}elseif ((empty($snmp_query_id)) && (sizeof(db_fetch_assoc("select text from host_template_data_sv where host_template_id=$host_template_id and data_template_id=" . $data_template["data_template_id"] . " and graph_template_id=$graph_template_id and field_name='$field_name'")) > 0)) {
								$subs_string = db_fetch_cell("select text from host_template_data_sv where host_template_id=$host_template_id and data_template_id=" . $data_template["data_template_id"] . " and graph_template_id=$graph_template_id and field_name='$field_name'");
								draw_edit_form_row($field_array, "d_" . $snmp_query_id . "_" . $graph_template_id . "_" . $data_template["data_template_id"] . "_0_" . $field_name, $subs_string);
							}else{
								draw_edit_form_row($field_array, "d_" . $snmp_query_id . "_" . $graph_template_id . "_" . $data_template["data_template_id"] . "_0_" . $field_name, $data_template[$field_name]);
							}
							
							$drew_items = true;
						}
					}
				}
				
				/* DRAW: Data Source Items */
				$data_template_items = db_fetch_assoc("select
					data_template_rrd.*
					from data_template_rrd
					where data_template_rrd.data_template_id=" . $data_template["data_template_id"] . "
					and local_data_id=0");
				
				if (sizeof($data_template_items) > 0) {
				foreach ($data_template_items as $data_template_item) {
					reset($struct_data_source_item);
					$drew_items = false;
					
					while (list($field_name, $field_array) = each($struct_data_source_item)) {
						if ($data_template_item{"t_" . $field_name} == "on") {
							$row_counter = 1; /* so we have an all 'light' background */
							
							/* SUGGESTED VALUES: we must treat suggested values for snmp queries different because
							one entry here might might 20 graphs... so we can't automatically fill in the values. 
							if it is not an snmp query, automatically fill in the values right here. */
							if ((!empty($snmp_query_id)) && (sizeof(db_fetch_assoc("select id from snmp_query_graph_rrd_sv where snmp_query_graph_id=$snmp_query_graph_id and data_template_id=" . $data_template["data_template_id"] . " and field_name='$field_name'")) > 0)) {
								print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>";
								print "<td><strong>" . $struct_data_source_item[$field_name]["friendly_name"] . "</strong></td>";
								print "<td><em>Using Suggested Values</em> (see SNMP Query)</td>"; 
								print "</td></tr>\n";
							}elseif ((empty($snmp_query_id)) && (sizeof(db_fetch_assoc("select text from host_template_data_sv where host_template_id=$host_template_id and data_template_id=" . $data_template["data_template_id"] . " and graph_template_id=$graph_template_id and field_name='$field_name'")) > 0)) {
								$subs_string = db_fetch_cell("select text from host_template_data_sv where host_template_id=$host_template_id and data_template_id=" . $data_template["data_template_id"] . " and graph_template_id=$graph_template_id and field_name='$field_name'");
								$subs_string = subsitute_host_data($subs_string, "|", "|", $host_id);
								draw_edit_form_row($field_array, "di_" . $snmp_query_id . "_" . $graph_template_id . "_" . $data_template["data_template_id"] . "_" . $data_template_item["id"] . "_" . $field_name, $subs_string);
							}else{
								draw_edit_form_row($field_array, "di_" . $snmp_query_id . "_" . $graph_template_id . "_" . $data_template["data_template_id"] . "_" . $data_template_item["id"] . "_" . $field_name, $data_template_item[$field_name]);
							}
							
							$drew_items = true;
						}
					}
				}
				}
			}
			}
			
			end_box();
		}
	}
	
	form_hidden_id("host_template_id",$host_template_id);
	form_hidden_id("host_id",$host_id);
	form_hidden_box("save_component_new_graphs","1","");
	print "<input type='hidden' name='selected_graphs_array' value='" . serialize($selected_graphs_array) . "'>\n";
	
	form_save_button("host.php?action=edit&id=$host_id");
	
	include_once("./include/bottom_footer.php");
}

function host_edit() {
	global $colors, $snmp_versions, $paths;
	
	display_output_messages();
	
	if (!empty($_GET["id"])) {
		$host = db_fetch_row("select * from host where id=" . $_GET["id"]);
		$header_label = "[edit: " . $host["description"] . "]";
	}else{
		$header_label = "[new]";
	}
	
	start_box("<strong>Polling Hosts</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	
	draw_edit_form(
		array(
			"config" => array(
				"form_name" => "chk"
				),
			"fields" => array(
				"description" => array(
					"method" => "textbox",
					"friendly_name" => "Description",
					"description" => "Give this host a meaningful description.",
					"value" => (isset($host) ? $host["description"] : ""),
					"max_length" => "250",
					),
				"host_template_id" => array(
					"method" => "drop_sql",
					"friendly_name" => "Host Template",
					"description" => "Choose what type of host, host template this is. The host template will govern what kinds of data should be gathered from this type of host.",
					"value" => (isset($host) ? $host["host_template_id"] : "0"),
					"none_value" => "None",
					"sql" => "select id,name from host_template",
					),
				"hostname" => array(
					"method" => "textbox",
					"friendly_name" => "Hostname",
					"description" => "Fill in the fully qualified hostname for this device.",
					"value" => (isset($host) ? $host["hostname"] : ""),
					"max_length" => "250",
					),
				"management_ip" => array(
					"method" => "textbox",
					"friendly_name" => "Management IP",
					"description" => "Choose the IP address that will be used to gather data from this host. The hostname will be used a fallback in case this fails.",
					"value" => (isset($host) ? $host["management_ip"] : ""),
					"max_length" => "15",
					),
				"snmp_community" => array(
					"method" => "textbox",
					"friendly_name" => "SNMP Community",
					"description" => "Fill in the SNMP read community for this device.",
					"value" => (isset($host) ? $host["snmp_community"] : ""),
					"max_length" => "100",
					),
				"snmp_username" => array(
					"method" => "textbox",
					"friendly_name" => "SNMP Username",
					"description" => "Fill in the SNMP username for this device (v3).",
					"value" => (isset($host) ? $host["snmp_username"] : ""),
					"max_length" => "50",
					),
				"snmp_password" => array(
					"method" => "textbox",
					"friendly_name" => "SNMP Password",
					"description" => "Fill in the SNMP password for this device (v3).",
					"value" => (isset($host) ? $host["snmp_password"] : ""),
					"max_length" => "50",
					),
				"snmp_version" => array(
					"method" => "drop_array",
					"friendly_name" => "SNMP Version",
					"description" => "Choose the SNMP version for this host.",
					"value" => (isset($host) ? $host["snmp_version"] : ""),
					"array" => $snmp_versions,
					),
				"disabled" => array(
					"method" => "checkbox",
					"friendly_name" => "Disable Host",
					"description" => "Check this box to disable all checks for this host.",
					"value" => (isset($host) ? $host["disabled"] : ""),
					"default" => "",
					"form_id" => false
					),
				"id" => array(
					"method" => "hidden",
					"value" => (isset($host) ? $host["id"] : "0")
					),
				"_host_template_id" => array(
					"method" => "hidden",
					"value" => (isset($host) ? $host["host_template_id"] : "0")
					),
				"save_component_host" => array(
					"method" => "hidden",
					"value" => "1"
					)
				)
			)
		);
	
	if (!empty($host["id"])) {
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td colspan="2">
			<table cellspacing="0" cellpadding="0" border="0" width="100%">
				<tr>
					<td width="50%">
						<font class="textEditTitle">Associated Data Query</font><br>
						If you choose to add this data query to this host, information will be queried from this
						host upon addition.
					</td>
					<td width="1">
						<?php form_dropdown("snmp_query_id",db_fetch_assoc("select id,name from snmp_query order by name"),"name","id","","None","");?>
					</td>
					<td>
						&nbsp;<input type="image" src="images/button_add.gif" alt="Add" name="add" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php
	}
	
	end_box();
	
	if (isset($host["id"])) {
		start_box("<strong>Create Graphs + Data Sources</strong>", "98%", $colors["header"], "3", "center", "");
		
		print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
				<td class='textSubHeaderDark'>Graph Template Name</td>
				<td width='1%' align='center' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAll(\"cg\")'></td>\n
			</tr>\n";
		
		$graph_templates = db_fetch_assoc("select
			graph_templates.id as graph_template_id,
			graph_templates.name as graph_template_name
			from host_template_graph, graph_templates
			where host_template_graph.graph_template_id=graph_templates.id
			and host_template_graph.host_template_id=" . $host["host_template_id"] . "
			order by graph_templates.name");
		
		$i = 0;
		
		/* create a row for each graph template associated with the host template */
		if (sizeof($graph_templates) > 0) {
		foreach ($graph_templates as $graph_template) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			print "		<td>
						<strong>Create:</strong> " . $graph_template["graph_template_name"] . "
					</td>
					<td align='right'>";
						form_checkbox("cg_" . $graph_template["graph_template_id"],"","","",0);
			print "		</td>
				</tr>";
		}
		}
		
		/* create a row at the bottom that lets the user create any graph they choose */
		form_alternate_row_color($colors["alternate"],$colors["light"],$i);
		print "		<td width='60' nowrap>
					<strong>Create:</strong>&nbsp;";
					form_dropdown("cg_g", db_fetch_assoc("select id,name from graph_templates order by name"), "name", "id", "", "", "");
		print "		</td>
				<td align='right'>";
					form_checkbox("ccg","","","",0);
		print "		</td>
			</tr>";
		
		end_box();
		
		$snmp_queries = db_fetch_assoc("select
			snmp_query.id,
			snmp_query.name,
			snmp_query.xml_path
			from snmp_query,host_snmp_query
			where host_snmp_query.snmp_query_id=snmp_query.id
			and host_snmp_query.host_id=" . $_GET["id"] . "
			order by snmp_query.name");
		
		print "<script type='text/javascript'>\nvar created_graphs = new Array()\n</script>\n";
		
		if (sizeof($snmp_queries) > 0) {
		foreach ($snmp_queries as $snmp_query) {
			$xml_array = get_data_query_array($snmp_query["id"]);
			$xml_outputs = array();
			
			$num_input_fields = 0;
			
			if ($xml_array != false) {
				/* loop through once so we can find out how many input fields there are */
				while (list($field_name, $field_array) = each($xml_array["fields"][0])) {
					if ($field_array[0]["direction"] == "input") {
						$num_input_fields++;
					}
				}
				
				reset($xml_array["fields"][0]);
				$snmp_query_indexes = array();
				$num_visible_fields{$snmp_query["id"]} = 0;
				$i = 0;
			}
			
			$snmp_query_graphs = db_fetch_assoc("select snmp_query_graph.id,snmp_query_graph.name from snmp_query_graph where snmp_query_graph.snmp_query_id=" . $snmp_query["id"] . " order by snmp_query_graph.name");
			
			if (sizeof($snmp_query_graphs) > 0) {
				print "<script type='text/javascript'>\n<!--\n";
				
				foreach ($snmp_query_graphs as $snmp_query_graph) {
					$created_graphs = db_fetch_assoc("select
						data_local.snmp_index
						from data_local,data_template_data
						left join data_input_data on data_template_data.id=data_input_data.data_template_data_id 
						left join data_input_fields on data_input_data.data_input_field_id=data_input_fields.id
						where data_local.id=data_template_data.local_data_id
						and data_input_fields.type_code='output_type'
						and data_input_data.value='" . $snmp_query_graph["id"] . "'");
					
					
					print "created_graphs[" . $snmp_query_graph["id"] . "] = new Array(";
					
					$cg_ctr = 0;
					if (sizeof($created_graphs) > 0) {
					foreach ($created_graphs as $created_graph) {
						print (($cg_ctr > 0) ? "," : "") . "'" . $created_graph["snmp_index"] . "'"; 
						
						//$disabled_rows_cache{$snmp_query["id"]}{$created_graph["snmp_index"]} = true;
						$cg_ctr++;
					}
					}
					
					print ")\n";
					
				}
				
				print "//-->\n</script>\n";
			}
			
			print "	<table width='98%' style='background-color: #" . $colors["form_alternate2"] . "; border: 1px solid #" . $colors["header"] . ";' align='center' cellpadding='3' cellspacing='0'>\n
					<tr>
						<td bgcolor='#" . $colors["header"] . "' colspan='" . ($num_input_fields+1) . "'>
							<table  cellspacing='0' cellpadding='0' width='100%' >
								<tr>
									<td class='textHeaderDark'>
										<strong>Data Query</strong> [" . $snmp_query["name"] . "]
									</td>
									<td align='right' nowrap>
										<a href='host.php?action=query_reload&id=" . $snmp_query["id"] . "&host_id=" . $_GET["id"] . "'><img src='images/reload_icon_small.gif' alt='Reload Associated Query' border='0' align='absmiddle'></a>&nbsp;
										<a href='host.php?action=query_remove&id=" . $snmp_query["id"] . "&host_id=" . $_GET["id"] . "'><img src='images/delete_icon_large.gif' alt='Delete Associated Query' border='0' align='absmiddle'></a>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr bgcolor='#" . $colors["header_panel"] . "'>";
			
			if ($xml_array != false) {
				while (list($field_name, $field_array) = each($xml_array["fields"][0])) {
					$field_array = $field_array[0];
					
					if ($field_array["direction"] == "input") {
						$i++;
						
						$raw_data = db_fetch_assoc("select field_value,snmp_index from host_snmp_cache where host_id=" . $_GET["id"] . " and field_name='$field_name'");
						
						/* don't even both to display the column if it has no data */
						if (sizeof($raw_data) > 0) {
							/* draw each header item <TD> */
							DrawMatrixHeaderItem($field_array["name"],$colors["header_text"],1);
							
							/* draw the 'check all' box if we are at the end of the row */
							if ($i >= $num_input_fields) {
								print "<td width='1%' align='center' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAll(\"sg_" . $snmp_query["id"] . "\")'></td>\n";
							}
							
							foreach ($raw_data as $data) {
								$snmp_query_data[$field_name]{$data["snmp_index"]} = $data["field_value"];
								$snmp_query_indexes{$data["snmp_index"]} = $data["snmp_index"];
							}
							
							$num_visible_fields{$snmp_query["id"]}++;
						}elseif (sizeof($raw_data) == 0) {
							/* we are choosing to not display this column, so unset the associated
							field in the xml array so it is not drawn */
							unset($xml_array["fields"][0][$field_name]);
						}
					}
				}
				
				print "</tr>";
				
				 $row_counter = 0;
				if (sizeof($snmp_query_indexes) > 0) {
				while (list($snmp_index, $snmp_index) = each($snmp_query_indexes)) {
					//$query_row_hash = md5($snmp_query["id"] . "_" . $snmp_index);
					$query_row = $snmp_query["id"] . "_" . $snmp_index;
					//$query_row = $row_counter;
					//form_alternate_row_color($colors["alternate"],$colors["light"],$i); 
					print "<tr id='line$query_row' bgcolor='#" . (($i % 2 == 0) ? "ffffff" : $colors["light"]) . "'>"; $i++;
					
					$column_counter = 0;
					reset($xml_array["fields"][0]);
					while (list($field_name, $field_array) = each($xml_array["fields"][0])) {
						if ($field_array[0]["direction"] == "input") {
							if (isset($snmp_query_data[$field_name][$snmp_index])) {
								print "<td onClick='select_line(" . $snmp_query["id"] . ",\"$snmp_index\");'><span id='text$query_row" . "_" . $column_counter . "'>" . $snmp_query_data[$field_name][$snmp_index] . "</span></td>";
							}else{
								print "<td></td>";
							}
							
							$column_counter++;
						}
					}
					
					print "<td align='right'>";
					form_checkbox("sg_$query_row","","","",0);
					print "</td>";
					print "</tr>\n";
					
					$row_counter++;
				}
				}
			}else{
				print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td colspan='2' style='color: red; font-size: 12px; font-weight: bold;'>Error in data query.</td></tr>\n";
			}
			
			/* draw the graph template drop down here */
			print "	</table>
				<table align='center' width='98%'>
					<tr>
						<td width='1' valign='top'>
							<img src='images/arrow.gif' alt='' align='absmiddle'>&nbsp;
						</td>
						<td align='right'>
							<select name='sgg_" . $snmp_query["id"] . "' id='sgg_" . $snmp_query["id"] . "' onChange='updateDeps(" . $snmp_query["id"] . "," . $num_visible_fields{$snmp_query["id"]} . ");'>
								"; create_list(db_fetch_assoc("select snmp_query_graph.id,snmp_query_graph.name from snmp_query_graph where snmp_query_graph.snmp_query_id=" . $snmp_query["id"] . " order by snmp_query_graph.name"),"name","id","0"); print "
							</select>
						</td>
					</tr>
				</table>
				<br>";
		}
		}
	}
	
	form_save_button("host.php");
	
	?>
	<script type="text/javascript">
	<!--
	
	
	
	function updateSelectionIndicators() {
	  if (document.getElementById) {
	  
	  there_are_any_unchecked_ones = false;
	  for (var j = 0; j < document.chk.elements.length; j++) {
	    if( document.chk.elements[j].name.substr( 0, 3 ) == 'sg_') {
	      if (document.chk.elements[j].checked == false) there_are_any_unchecked_ones = true;      
	
		  // make the selected ones colored
		  lineid = document.getElementById('line'+ document.chk.elements[j].name.substr(3));
	      if (document.chk.elements[j].checked) lineid.style.backgroundColor = 'gold';
		else lineid.style.backgroundColor = '';
	    }
	  }
	  }
	}
	
	function select_line(snmp_query_id, snmp_index, update) {
		if (isDisabled(snmp_query_id, snmp_index)) { return; }
		
		if (document.getElementById) {
			msgid = document.getElementById('sg_' + snmp_query_id + '_' + snmp_index);
			lineid = document.getElementById('line'+ snmp_query_id + '_' + snmp_index);
			
			if (!update) msgid.checked = !msgid.checked;
			
			updateSelectionIndicators();
		}
	}
	
	function isDisabled(snmp_query_id, snmp_index) {
		dropdown = document.getElementById('sgg_'+ snmp_query_id);
		var snmp_query_graph_id = dropdown.value
		
		for (var i = 0; i < created_graphs[snmp_query_graph_id].length; i++) {
			if (created_graphs[snmp_query_graph_id][i] == snmp_index) {
				return true;
			}
		}
		
		return false;
	}
	
	function updateDeps(snmp_query_id, num_columns) {
		resetDeps(snmp_query_id, num_columns);
		
		dropdown = document.getElementById('sgg_'+ snmp_query_id);
		var snmp_query_graph_id = dropdown.value
		
		for (var i = 0; i < created_graphs[snmp_query_graph_id].length; i++) {
			for (var j = 0; j < num_columns; j++) {
				lineid = document.getElementById('text' + snmp_query_id + '_' + created_graphs[snmp_query_graph_id][i] + '_' + j);
				lineid.style.color = '888888';
			}
			
			chkbx = document.getElementById('sg_'+ snmp_query_id + '_' + created_graphs[snmp_query_graph_id][i]);
			chkbx.style.visibility = 'hidden';
			chkbx.checked = false;
		}
	}
	
	function resetDeps(snmp_query_id, num_columns) {
		var prefix = 'sg_' + snmp_query_id + '_'
		
		for (var i = 0; i < document.chk.elements.length; i++) {
			if (document.chk.elements[i].name.substr( 0, prefix.length ) == prefix) {
				for (var j = 0; j < num_columns; j++) {
					lineid = document.getElementById('text' + snmp_query_id + '_' + document.chk.elements[i].name.substr(prefix.length) + '_' + j);
					lineid.style.color = '000000';
				}
				
				chkbx = document.getElementById('sg_'+ snmp_query_id + '_' + document.chk.elements[i].name.substr(prefix.length));
				chkbx.style.visibility = 'visible';
			}
		}
	}
	
	//-->
	</script>
	<?php
	
	reset($snmp_queries);
	
	if (sizeof($snmp_queries) > 0) {
	foreach ($snmp_queries as $snmp_query) {
		$num_input_fields = $num_visible_fields{$snmp_query["id"]};
		
		print "<script type='text/javascript'>updateDeps(" . $snmp_query["id"] . "," . ($num_input_fields) . ");</script>\n";
	}
	}
}

function host() {
	global $colors;
	
	display_output_messages();
	
	start_box("<strong>Polling Hosts</strong>", "98%", $colors["header"], "3", "center", "host.php?action=edit");
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Description",$colors["header_text"],1);
		DrawMatrixHeaderItem("Hostname",$colors["header_text"],1);
		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
	print "</tr>";
    
	$hosts = db_fetch_assoc("select id,hostname,description from host order by description");
	
	$i = 0;
	if (sizeof($hosts) > 0) {
	foreach ($hosts as $host) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="host.php?action=edit&id=<?php print $host["id"];?>"><?php print $host["description"];?></a>
			</td>
			<td>
				<?php print $host["hostname"];?>
			</td>
			<td width="1%" align="right">
				<a href="host.php?action=remove&id=<?php print $host["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?php
	}
	}else{
		print "<tr><td><em>No Hosts</em></td></tr>";
	}
	end_box();
}

?>
