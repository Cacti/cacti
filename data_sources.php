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

include ('include/auth.php'); 
include_once ("include/functions.php");
include_once ("include/config_arrays.php");
include_once ('include/form.php');

$ds_actions = array(
	1 => "Delete",
	2 => "Change Data Template",
	3 => "Change Host",
	4 => "Duplicate",
	5 => "Convert to Data Template"
	);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	case 'actions':
		form_actions();
		
		break;
	case 'rrd_add':
		ds_rrd_add();
		
		break;
	case 'rrd_remove':
		ds_rrd_remove();
		
		break;
	case 'data_edit':
		include_once ("include/top_header.php");
		
		data_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'ds_remove':
		ds_remove();
		
		header ("Location: data_sources.php");
		break;
	case 'ds_edit':
		include_once ("include/top_header.php");
		
		ds_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		ds();
		
		include_once ("include/bottom_footer.php");
		break;
}


/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	include_once ("include/utility_functions.php");
	
	if ((isset($_POST["save_component_data_source_new"])) && (isset($_POST["data_template_id"]))) {
		$save["id"] = $_POST["local_data_id"];
		$save["data_template_id"] = $_POST["data_template_id"];
		$save["host_id"] = $_POST["host_id"];
		
		$local_data_id = sql_save($save, "data_local");
		
		change_data_template($local_data_id, $_POST["data_template_id"]);
	}
	
	if (isset($_POST["save_component_data_source"])) {
		$save1["id"] = $_POST["local_data_id"];
		$save1["data_template_id"] = $_POST["data_template_id"];
		$save1["host_id"] = $_POST["host_id"];
		
		$save2["id"] = $_POST["data_template_data_id"];
		$save2["local_data_template_data_id"] = $_POST["local_data_template_data_id"];
		$save2["data_template_id"] = $_POST["data_template_id"];
		$save2["data_input_id"] = form_input_validate($_POST["data_input_id"], "data_input_id", "", true, 3);
		$save2["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save2["data_source_path"] = form_input_validate($_POST["data_source_path"], "data_source_path", "", true, 3);
		$save2["active"] = form_input_validate((isset($_POST["active"]) ? $_POST["active"] : ""), "active", "", true, 3);
		$save2["rrd_step"] = form_input_validate($_POST["rrd_step"], "rrd_step", "^[0-9]+$", false, 3);
		
		/* if this is a new data source and a template has been selected, skip item creation this time
		otherwise it throws off the templatate creation because of the NULL data */
		if ($_POST["data_template_id"] == $_POST["_data_template_id"]) {
			$save3["id"] = $_POST["data_template_rrd_id"];
			$save3["local_data_template_rrd_id"] = $_POST["local_data_template_rrd_id"];
			$save3["data_template_id"] = $_POST["data_template_id"];
			$save3["rrd_maximum"] = form_input_validate($_POST["rrd_maximum"], "rrd_maximum", "^-?[0-9]+$", false, 3);
			$save3["rrd_minimum"] = form_input_validate($_POST["rrd_minimum"], "rrd_minimum", "^-?[0-9]+$", false, 3);
			$save3["rrd_heartbeat"] = form_input_validate($_POST["rrd_heartbeat"], "rrd_heartbeat", "^[0-9]+$", false, 3);
			$save3["data_source_type_id"] = $_POST["data_source_type_id"];
			$save3["data_source_name"] = form_input_validate($_POST["data_source_name"], "data_source_name", "^[a-zA-Z0-9_-]{1,19}$", false, 3);
			$save3["data_input_field_id"] = form_input_validate((isset($_POST["data_input_field_id"]) ? $_POST["data_input_field_id"] : "0"), "data_input_field_id", "", true, 3);
		}
		
		if (!is_error_message()) {
			$local_data_id = sql_save($save1, "data_local");
			
			$save2["local_data_id"] = $local_data_id;
			$data_template_data_id = sql_save($save2, "data_template_data");
			
			if ($data_template_data_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
		
		/* if this is a new data source and a template has been selected, skip item creation this time
		otherwise it throws off the templatate creation because of the NULL data */
		if (($_POST["data_template_id"] == $_POST["_data_template_id"]) && (!is_error_message())){
			$save3["local_data_id"] = $local_data_id;
			$data_template_rrd_id = sql_save($save3, "data_template_rrd");
			
			if ($data_template_rrd_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
		
		
		if (!is_error_message()) {
			if ($_POST["data_template_id"] != $_POST["_data_template_id"]) {
				/* update all nessesary template information */
				change_data_template($local_data_id, $_POST["data_template_id"]);
				
				if ($_POST["host_id"] != $_POST["_host_id"]) {
					/* push out all nessesary host information */
					push_out_host($_POST["host_id"], $local_data_id);
					
					/* reset current host for display purposes */
					$_SESSION["sess_data_source_current_host_id"] = $_POST["host_id"];
				}
			}
			
			/* if no data source path has been entered, generate one */
			if (empty($_POST["data_source_path"])) {
				generate_data_source_path($local_data_id);
			}
			
			/* save entried in 'selected rras' field */
			db_execute("delete from data_template_data_rra where data_template_data_id=$data_template_data_id"); 
			
			if (isset($_POST["rra_id"])) {
				for ($i=0; ($i < count($_POST["rra_id"])); $i++) {
					db_execute("insert into data_template_data_rra (rra_id,data_template_data_id) 
						values (" . $_POST["rra_id"][$i] . ",$data_template_data_id)");
				}
			}
		}
	}
	
	if ((isset($_POST["save_component_data"])) && (!is_error_message())) {
		/* ok, first pull out all 'input' values so we know how much to save */
		$input_fields = db_fetch_assoc("select
			data_template_data.data_input_id,
			data_local.host_id,
			data_input_fields.id,
			data_input_fields.input_output,
			data_input_fields.data_name,
			data_input_fields.regexp_match,
			data_input_fields.allow_nulls,
			data_input_fields.type_code
			from data_template_data
			left join data_input_fields on data_input_fields.data_input_id=data_template_data.data_input_id
			left join data_local on data_template_data.local_data_id=data_local.id
			where data_template_data.id=" . $_POST["data_template_data_id"] . "
			and data_input_fields.input_output='in'");
		
		if (sizeof($input_fields) > 0) {
		foreach ($input_fields as $input_field) {
			if (isset($_POST{"value_" . $input_field["data_name"]})) {
				/* save the data into the 'data_input_data' table */
				$form_name = "value_" . $input_field["data_name"];
				$form_value = $_POST[$form_name];
				
				if ($input_field["allow_nulls"] == "on") {
					$allow_nulls = true;
				}elseif (empty($input_field["allow_nulls"])) {
					$allow_nulls = false;
				}
				
				/* run regexp match on input string */
				$form_value = form_input_validate($form_value, $form_name, $input_field["regexp_match"], $allow_nulls, 3);
				
				if (!is_error_message()) {
					db_execute("replace into data_input_data (data_input_field_id,data_template_data_id,t_value,value) values
						(" . $input_field["id"] . "," . $_POST["data_template_data_id"] . ",'" . db_fetch_cell("select t_value from data_input_data where data_input_field_id=" . $input_field["id"] . " and data_template_data_id=" . $_POST["data_template_data_id"]) . "','$form_value')");
				}
			}
		}
		}
	}
	
	/* update the poller cache last to make sure everything is fresh */
	if ((!is_error_message()) && (!empty($local_data_id))) {
		update_poller_cache($local_data_id);
	}
	
	if ((isset($_POST["save_component_data_source_new"])) && (empty($_POST["data_template_id"]))) {
		header ("Location: data_sources.php?action=ds_edit&host_id=" . $_POST["host_id"] . "&new=1");
	}elseif ((is_error_message()) || ($_POST["data_template_id"] != $_POST["_data_template_id"]) || ($_POST["data_input_id"] != $_POST["_data_input_id"]) || ($_POST["host_id"] != $_POST["_host_id"])) {
		header ("Location: data_sources.php?action=ds_edit&id=" . (empty($local_data_id) ? $_POST["local_data_id"] : $local_data_id) . "&host_id=" . $_POST["host_id"] . "&view_rrd=" . (isset($_POST["view_rrd"]) ? $_POST["view_rrd"] : "0"));
	}else{
		header ("Location: data_sources.php");
	}
}

/* ------------------------
    The "actions" function 
   ------------------------ */

function form_actions() {
	include_once ("include/tree_functions.php");
	include_once ("include/tree_view_functions.php");
	include_once ("include/utility_functions.php");
	
	global $colors, $ds_actions;
	
	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));
		
		if ($_POST["drp_action"] == "1") { /* delete */
			if (!isset($_POST["delete_type"])) { $_POST["delete_type"] = 0; }
			
			switch ($_POST["delete_type"]) {
				case '2': /* delete all graph items tied to this data source */
					$data_template_rrds = db_fetch_assoc("select id from data_template_rrd where " . array_to_sql_or($selected_items, "local_data_id"));
					
					/* loop through each data source item */
					if (sizeof($data_template_rrds) > 0) {
					foreach ($data_template_rrds as $item) {
						db_execute("delete from graph_templates_item where task_item_id=" . $item["id"] . " and local_graph_id > 0");
					}
					}
					
					break;
				case '3': /* delete all graphs tied to this data source */
					$data_template_rrds = db_fetch_assoc("select id from data_template_rrd where " . array_to_sql_or($selected_items, "local_data_id"));
					
					/* loop through each data source item */
					if (sizeof($data_template_rrds) > 0) {
					foreach ($data_template_rrds as $item) {
						$graphs = db_fetch_assoc("select local_graph_id from graph_templates_item where task_item_id=" . $item["id"] . " and local_graph_id > 0");
						
						/* loop through each graph */
						if (sizeof($graphs) > 0) {
						foreach ($graphs as $graph) {
							db_execute("delete from graph_templates_graph where local_graph_id=" . $graph["local_graph_id"]);
							db_execute("delete from graph_templates_item where local_graph_id=" . $graph["local_graph_id"]);
							db_execute("delete from graph_tree_items where local_graph_id=" . $graph["local_graph_id"]);
							db_execute("delete from graph_local where id=" . $graph["local_graph_id"]);
						}
						}
					}
					}
					
					break;
				}
				
				/* retrieve an array indexed on data_template_data_id */
				$data_template_data_ids = db_fetch_assoc("select id from data_template_data where " . array_to_sql_or($selected_items, "local_data_id"));
				
				$selected_dsid_items = array();
				
				for ($i=0;($i<count($data_template_data_ids));$i++) {
					$selected_dsid_items[$i] = $data_template_data_ids[$i]["id"];
				}
				
				/* delete the data source and its data when we're done */
				db_execute("delete from data_template_data_rra where " . array_to_sql_or($selected_dsid_items, "data_template_data_id"));
				db_execute("delete from data_input_data where " . array_to_sql_or($selected_dsid_items, "data_template_data_id"));
				db_execute("delete from data_template_data where " . array_to_sql_or($selected_items, "local_data_id"));
				db_execute("delete from data_template_rrd where " . array_to_sql_or($selected_items, "local_data_id"));
				db_execute("delete from data_input_data_cache where " . array_to_sql_or($selected_items, "local_data_id"));
				db_execute("delete from data_local where " . array_to_sql_or($selected_items, "id"));
		}elseif ($_POST["drp_action"] == "2") { /* change graph template */
			for ($i=0;($i<count($selected_items));$i++) {
				change_data_template($selected_items[$i], $_POST["data_template_id"]);
			}
		}elseif ($_POST["drp_action"] == "3") { /* change host */
			for ($i=0;($i<count($selected_items));$i++) {
				db_execute("update data_local set host_id=" . $_POST["host_id"] . " where id=" . $selected_items[$i]);
				push_out_host($_POST["host_id"], $selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == "4") { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				duplicate_data_source($selected_items[$i], 0, $_POST["title_format"]);
			}
		}elseif ($_POST["drp_action"] == "5") { /* data source -> data template */
			for ($i=0;($i<count($selected_items));$i++) {
				data_source_to_data_template($selected_items[$i], 0, $_POST["title_format"]);
			}
		}
		
		header("Location: data_sources.php");
		exit;
	}
	
	/* setup some variables */
	$ds_list = ""; $i = 0;
	
	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			$ds_list .= "<li>" . get_data_source_title($matches[1]) . "<br>";
			$ds_array[$i] = $matches[1];
		}
		
		$i++;
	}
	
	include_once ("include/top_header.php");
	
	start_box("<strong>" . $ds_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");
	
	print "<form action='data_sources.php' method='post'>\n";
	
	if ($_POST["drp_action"] == "1") { /* delete */
		$graphs = array();
		
		/* find out what (if any) graphs are using this data source, so we can complain to the user */
		if (isset($ds_array)) {
			$graphs = db_fetch_assoc("select
				graph_templates_graph.local_graph_id
				from data_template_rrd
				left join graph_templates_item on graph_templates_item.task_item_id=data_template_rrd.id
				left join graph_templates_graph on graph_templates_item.local_graph_id=graph_templates_graph.local_graph_id
				where " . array_to_sql_or($ds_array, "data_template_rrd.local_data_id") . "
				and graph_templates_item.local_graph_id>0
				and graph_templates_graph.local_graph_id>0
				group by graph_templates_graph.title
				order by graph_templates_graph.title");
		}
		
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to delete the following data sources?</p>
					<p>$ds_list</p>
					";
					if (sizeof($graphs) > 0) {
						print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td class='textArea'><p class='textArea'>The following graphs are using this data source:</p>\n";
						
						foreach ($graphs as $graph) {
							print "<strong>" . get_graph_title($graph["local_graph_id"]) . "</strong><br>\n";
						}
						
						print "<br>";
						form_base_radio_button("delete_type", "1", "1", "Leave the graphs untouched.", "1", true);
						form_base_radio_button("delete_type", "1", "2", "Delete all <strong>graph items</strong> that reference to this data source.", "1", true);
						form_base_radio_button("delete_type", "1", "3", "Delete all <strong>graphs</strong> that reference to this data source.", "1", true);
						print "</td></tr>";
					}
				print "
				</td>
			</tr>\n
			";
	}elseif ($_POST["drp_action"] == "2") { /* change graph template */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Choose a data template and click save to change the data template for
					the following data souces. Be aware that all warnings will be suppressed during the
					conversion, so graph data loss is possible.</p>
					<p>$ds_list</p>
					<p><strong>New Data Template:</strong><br>"; form_base_dropdown("data_template_id",db_fetch_assoc("select data_template.id,data_template.name from data_template order by data_template.name"),"name","id","","","0"); print "</p>
				</td>
			</tr>\n
			";
	}elseif ($_POST["drp_action"] == "3") { /* change host */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Choose a new host for these data sources:</p>
					<p>$ds_list</p>
					<p><strong>New Host:</strong><br>"; form_base_dropdown("host_id",db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname"),"name","id","","","0"); print "</p>
				</td>
			</tr>\n
			";
	}elseif ($_POST["drp_action"] == "4") { /* duplicate */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>When you click save, the following data sources will be duplicated. You can
					optionally change the title format for the new data sources.</p>
					<p>$ds_list</p>
					<p><strong>Title Format:</strong><br>"; form_base_text_box("title_format", "<ds_title> (1)", "", "255", "30", "textbox"); print "</p>
				</td>
			</tr>\n
			";
	}elseif ($_POST["drp_action"] == "5") { /* graph -> graph template */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>When you click save, the following data sources will be converted into data templates. 
					You can optionally change the title format for the new data templates.</p>
					<p>$ds_list</p>
					<p><strong>Title Format:</strong><br>"; form_base_text_box("title_format", "<ds_title> Template", "", "255", "30", "textbox"); print "</p>
				</td>
			</tr>\n
			";
	}
	
	if (!isset($ds_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one data source.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='image' src='images/button_yes.gif' alt='Save' align='absmiddle'>";
	}
	
	print "	<tr>
			<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($ds_array) ? serialize($ds_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				<a href='data_sources.php'><img src='images/button_no.gif' alt='Cancel' align='absmiddle' border='0'></a>
				$save_html
			</td>
		</tr>
		";	
	
	end_box();
	
	include_once ("include/bottom_footer.php");
}

/* ----------------------------
    data - Custom Data
   ---------------------------- */

function data_edit() {
	global $config, $colors;
	
	if (!empty($_GET["id"])) {
		$data = db_fetch_row("select id,data_input_id,data_template_id,name,local_data_id from data_template_data where local_data_id=" . $_GET["id"]);
		$template_data = db_fetch_row("select id,data_input_id from data_template_data where data_template_id=" . $data["data_template_id"] . " and local_data_id=0");
		
		$host = db_fetch_row("select host.id,host.hostname from data_local,host where data_local.host_id=host.id and data_local.id=" . $_GET["id"]);
		
		$header_label = "[edit: " . $data["name"] . "]";
	}else{
		$header_label = "[new]";
	}
	
	print "<form method='post' action='data_sources.php'>\n";
	
	$i = 0;
	if (!empty($data["data_input_id"])) {
		/* get each INPUT field for this data input source */
		$fields = db_fetch_assoc("select * from data_input_fields where data_input_id=" . $data["data_input_id"] . " and input_output='in' order by name");
		
		start_box("<strong>Custom Data</strong> [data input: " . db_fetch_cell("select name from data_input where id=" . $data["data_input_id"]) . "]", "98%", $colors["header"], "3", "center", "");
		
		/* loop through each field found */
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
			
			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
			
			if ((!empty($host["id"])) && (eregi('^(hostname|management_ip|snmp_community|snmp_username|snmp_password|snmp_version)$', $field["type_code"]))) {
				print "<td width='50%'><strong>" . $field["name"] . "</strong> (From Host: " . $host["hostname"] . ")</td>\n";
				print "<td><em>$old_value</em></td>\n";
			}elseif (empty($can_template)) {
				print "<td width='50%'><strong>" . $field["name"] . "</strong> (From Data Template)</td>\n";
				print "<td><em>" . (empty($old_value) ? "Nothing Entered" : $old_value) . "</em></td>\n";
			}else{
				print "<td width='50%'><strong>" . $field["name"] . "</strong></td>\n";
				form_text_box("value_" . $field["data_name"],$old_value,"","");
			}
			
			print "</tr>\n";
			
			$i++;
		}
		}else{
			print "<tr><td><em>No Input Fields for the Selected Data Input Source</em></td></tr>";
		}
		
		end_box();
	}
	
	form_hidden_id("local_data_id",(isset($data) ? $data["local_data_id"] : "0"));
	form_hidden_id("data_template_data_id",(isset($data) ? $data["id"] : "0"));
	form_hidden_box("save_component_data","1","");
}

/* ------------------------
    Data Source Functions
   ------------------------ */

function ds_rrd_remove() {
	db_execute("delete from data_template_rrd where id=" . $_GET["id"]);
	
	header ("Location: data_sources.php?action=ds_edit&id=" . $_GET["local_data_id"]);
}

function ds_rrd_add() {
	db_execute("insert into data_template_rrd (local_data_id,rrd_maximum,rrd_minimum,rrd_heartbeat,data_source_type_id,
		data_source_name) values (" . $_GET["id"] . ",100,0,600,1,'ds')");
	$data_template_rrd_id = db_fetch_insert_id();
	
	header ("Location: data_sources.php?action=ds_edit&id=" . $_GET["id"] . "&view_rrd=$data_template_rrd_id");
}

function ds_edit() {
	include_once ("include/rrd_functions.php");
	
	global $colors, $struct_data_source, $struct_data_source_item, $data_source_types;
	
	$use_data_template = true;
	$host_id = 0;
	
	if (!empty($_GET["id"])) {
		$local_data_template_data_id = db_fetch_cell("select local_data_template_data_id from data_template_data where local_data_id=" . $_GET["id"]);
		
		$data = db_fetch_row("select * from data_template_data where local_data_id=" . $_GET["id"]);
		$data_template = db_fetch_row("select * from data_template_data where id=$local_data_template_data_id");
		
		$host_id = db_fetch_cell("select host_id from data_local where id=" . $_GET["id"]);
		$data_template_name = db_fetch_cell("select name from data_template where id=" . $data["data_template_id"]);
		
		$header_label = "[edit: " . expand_title($host_id, $data["name"]) . "]";
		
		if ($data["data_template_id"] == "0") {
			$use_data_template = false;
		}
	}else{
		$header_label = "[new]";
		
		$use_data_template = false;
	}
	
	/* handle debug mode */
	if (isset($_GET["debug"])) {
		if ($_GET["debug"] == "0") {
			kill_session_var("ds_debug_mode");
		}elseif ($_GET["debug"] == "1") {
			$_SESSION["ds_debug_mode"] = true;
		}
	}
	
	/* display the debug mode box if the user wants it */
	if (isset($_SESSION["ds_debug_mode"])) {
		start_box("<strong>Data Source Debug</strong>", "98%", $colors["header"], "3", "center", "");
		
		?>
		<tr>
			<td>
				<pre><?php print rrdtool_function_create($_GET["id"], true);?></pre>
			</td>
		</tr>
		<?php
		
		end_box();
	}
	
	start_box("<strong>Data Template Selection</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	
	print "<form method='post' action='data_sources.php'>\n";
	
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Selected Data Template</font><br>
			The name given to this data template.
		</td>
		<?php form_dropdown("data_template_id",db_fetch_assoc("select id,name from data_template order by name"),"name","id",(isset($data_template) ? $data_template["data_template_id"] : "0"),"None","0");?>
	</tr>
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Host</font><br>
			Choose the host that this data source belongs to.
		</td>
		<?php form_dropdown("host_id",db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname"),"name","id",$host_id,"None",(isset($_GET["host_id"]) ? $_GET["host_id"] : "0"));?>
	</tr>
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Debug</font><br>
			Turn on/off data source debugging.
		</td>
		<td>
			<a href="data_sources.php?action=ds_edit&id=<?php print $_GET["id"];?>&debug=<?php print isset($_SESSION["ds_debug_mode"]) ? "0" : "1";?>">Turn <strong><?php print isset($_SESSION["ds_debug_mode"]) ? "Off" : "On";?></strong> Data Source Debug Mode.</a>
		</td>
	</tr>
	
	<?php
	end_box();
	
	if ((isset($_GET["id"])) || (isset($_GET["new"]))) {
		start_box("<strong>Data Source</strong>", "98%", $colors["header"], "3", "center", "");
		
		$i = 0;
		while (list($field_name, $field_array) = each($struct_data_source)) {
			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
			
			print "<td width='50%'><font class='textEditTitle'>" . $field_array["title"] . "</font><br>\n";
			if (($use_data_template == false) || (!empty($data_template{"t_" . $field_name})) || ($field_array["flags"] == "NOTEMPLATE")) {
				print $field_array["description"];
			}
			
			print "</td>\n";
			
			if ($field_array["type"] == "custom") {
				$array_rra = array_rekey(db_fetch_assoc("select id,name from rra order by name"), "id", "name");
				form_multi_dropdown("rra_id",$array_rra,db_fetch_assoc("select * from data_template_data_rra where data_template_data_id=" . (isset($data) ? $data["id"] : "0")), "rra_id");
			}else{
				if (($use_data_template == false) || (!empty($data_template{"t_" . $field_name})) || ($field_array["flags"] == "NOTEMPLATE")) {
					draw_nontemplated_item($field_array, $field_name, (isset($data) ? $data[$field_name] : ""));
				}else{
					draw_templated_item($field_array, $field_name, (isset($data) ? $data[$field_name] : ""));
				}
			}
			
			print "</tr>\n";
		}
		
		end_box();
		
		/* fetch ALL rrd's for this data source */
		if (!empty($_GET["id"])) {
			$template_data_rrds = db_fetch_assoc("select id,data_source_name from data_template_rrd where local_data_id=" . $_GET["id"] . " order by data_source_name");
		}
		
		/* select the first "rrd" of this data source by default */
		if (empty($_GET["view_rrd"])) {
			$_GET["view_rrd"] = (isset($template_data_rrds[0]["id"]) ? $template_data_rrds[0]["id"] : "0");
		}
		
		/* get more information about the rrd we chose */
		if (!empty($_GET["view_rrd"])) {
			$local_data_template_rrd_id = db_fetch_cell("select local_data_template_rrd_id from data_template_rrd where id=" . $_GET["view_rrd"]);
			
			$rrd = db_fetch_row("select * from data_template_rrd where id=" . $_GET["view_rrd"]);
			$rrd_template = db_fetch_row("select * from data_template_rrd where id=$local_data_template_rrd_id");
			
			$header_label = "[edit: " . $rrd["data_source_name"] . "]";
		}else{
			$header_label = "";
		}
		
		$i = 0;
		if (isset($template_data_rrds)) {
			if (sizeof($template_data_rrds) > 1) {
			
			/* draw the data source tabs on the top of the page */
			print "	<table class='tabs' width='98%' cellspacing='0' cellpadding='3' align='center'>
					<tr>\n";
					
					foreach ($template_data_rrds as $template_data_rrd) {
						$i++;
						print "	<td " . (($template_data_rrd["id"] == $_GET["view_rrd"]) ? "bgcolor='silver'" : "bgcolor='#DFDFDF'") . " nowrap='nowrap' width='" . ((strlen($template_data_rrd["data_source_name"]) * 9) + 50) . "' align='center' class='tab'>
								<span class='textHeader'><a href='data_sources.php?action=ds_edit&id=" . $_GET["id"] . "&view_rrd=" . $template_data_rrd["id"] . "'>$i: " . $template_data_rrd["data_source_name"] . "</a> <a href='data_sources.php?action=rrd_remove&id=" . $template_data_rrd["id"] . "&local_data_id=" . $_GET["id"] . "'><img src='images/delete_icon.gif' border='0' alt='Delete'></a></span>
							</td>\n
							<td width='1'></td>\n";
					}
					
					print "
					<td></td>\n
					</tr>
				</table>\n";
				
			}elseif (sizeof($template_data_rrds) == 1) {
				$_GET["view_rrd"] = $template_data_rrds[0]["id"];
			}
		}
		
		start_box("", "98%", $colors["header"], "3", "center", "");
		
		print "	<tr>
				<td bgcolor='#" . $colors["header"] . "' class='textHeaderDark'>
					<strong>Data Source Item</strong> $header_label
				</td>
				<td class='textHeaderDark' align='right' bgcolor='" . $colors["header"] . "'>
					" . ((!empty($_GET["id"]) && (empty($data_template["data_template_id"]))) ? "<strong><a class='linkOverDark' href='data_sources.php?action=rrd_add&id=" . $_GET["id"] . "'>New</a>&nbsp;</strong>" : "") . "
				</td>
			</tr>\n";
		
		/* data input fields list */
		if ((empty($data["data_input_id"])) || (db_fetch_cell("select type_id from data_input where id=" . $data["data_input_id"]) > "1")) {
			unset($struct_data_source_item["data_input_field_id"]);
		}else{
			$struct_data_source_item["data_input_field_id"]["sql"] = "select id,CONCAT(data_name,' - ',name) as name from data_input_fields where data_input_id=" . $data["data_input_id"] . " and input_output='out' and update_rra='on' order by data_name,name";
		}
		
		print "<form method='post' action='data_sources.php'>";
		
		$i = 1;
		while (list($field_name, $field_array) = each($struct_data_source_item)) {
			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
			
			print "<td width='50%'><font class='textEditTitle'>" . $field_array["title"] . "</font><br>\n";
			
			if (($use_data_template == false) || ($rrd_template{"t_" . $field_name} == "on")) {
				print $field_array["description"];
			}
			
			print "</td>\n";
			
			if (($use_data_template == false) || ($rrd_template{"t_" . $field_name} == "on")) {
				draw_nontemplated_item($field_array, $field_name, (isset($rrd) ? $rrd[$field_name] : ""));
			}else{
				draw_templated_item($field_array, $field_name, (isset($rrd) ? $rrd[$field_name] : ""));
			}
			
			print "</tr>\n";
		}
		
		end_box();
		
		/* data source data goes here */
		data_edit();
		
		form_hidden_id("current_rrd",$_GET["view_rrd"]);
	}
	
	form_hidden_id("_data_template_id",(isset($data) ? $data["data_template_id"] : "0"));
	form_hidden_id("_host_id",$host_id,(isset($_GET["host_id"]) ? $_GET["host_id"] : "0"));
	form_hidden_id("_data_input_id",(isset($data["data_input_id"]) ? $data["data_input_id"] : "0"));
	form_hidden_id("data_template_data_id",(isset($data) ? $data["id"] : "0"));
	form_hidden_id("data_template_rrd_id",(isset($rrd) ? $rrd["id"] : "0"));
	form_hidden_id("local_data_template_data_id",(isset($data) ? $data["local_data_template_data_id"] : "0"));
	form_hidden_id("local_data_template_rrd_id",(isset($rrd) ? $rrd["local_data_template_rrd_id"] : "0"));
	form_hidden_id("local_data_id",(isset($data) ? $data["local_data_id"] : "0"));
	
	if ((isset($_GET["id"])) || (isset($_GET["new"]))) {
		form_hidden_box("save_component_data_source","1","");
	}else{
		form_hidden_box("save_component_data_source_new","1","");
	}
	
	form_save_button("data_sources.php");	
}

function ds() {
	global $colors, $ds_actions;
	
	/* remember these search fields in session vars so we don't have to keep passing them around */
	if (isset($_REQUEST["page"])) {
		$_SESSION["sess_ds_current_page"] = $_REQUEST["page"];
	}elseif (isset($_SESSION["sess_ds_current_page"])) {
		$_REQUEST["page"] = $_SESSION["sess_ds_current_page"];
	}else{
		$_REQUEST["page"] = "1"; /* default value */
	}
	
	if (isset($_REQUEST["filter"])) {
		$_SESSION["sess_ds_filter"] = $_REQUEST["filter"];
	}elseif (isset($_SESSION["sess_ds_filter"])) {
		$_REQUEST["filter"] = $_SESSION["sess_ds_filter"];
	}else{
		$_REQUEST["filter"] = ""; /* default value */
	}
	
	if (isset($_REQUEST["host_id"])) {
		$_SESSION["sess_ds_host_id"] = $_REQUEST["host_id"];
	}elseif (isset($_SESSION["sess_ds_host_id"])) {
		$_REQUEST["host_id"] = $_SESSION["sess_ds_host_id"];
	}else{
		$_REQUEST["host_id"] = ""; /* default value */
	}
	
	$host = db_fetch_row("select hostname from host where id=" . $_REQUEST["host_id"]);
	
	start_box("<strong>Data Sources</strong> [host: " . (empty($host["hostname"]) ? "No Host" : $host["hostname"]) . "]", "98%", $colors["header"], "3", "center", "data_sources.php?action=ds_edit&host_id=" . $_REQUEST["host_id"]);
	?>
	
	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_graph_id">
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="100">
						Select a host:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
							<option value="data_sources.php?host_id=0"<?php if ($_REQUEST["host_id"] == "0") {?> selected<?php }?>>None</option>
							
							<?php
							$hosts = db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname");
							
							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='data_sources.php?host_id=" . $host["id"] . "&page=1'"; if ($_REQUEST["host_id"] == $host["id"]) { print " selected"; } print ">" . $host["name"] . "</option>\n";
							}
							}
							?>
							
						</select>
					</td>
					<td width="5"></td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td>
						&nbsp;<input type="image" src="images/button_go.gif" alt="Go" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>
	<?php
	end_box();
	
	/* form the 'where' clause for our main sql query */
	$sql_where = "where data_template_data.name like '%%" . $_REQUEST["filter"] . "%%'";
	
	if (!empty($_REQUEST["host_id"])) {
		$sql_where .= " and data_local.host_id=" . $_REQUEST["host_id"];
	}
	
	$total_rows = sizeof(db_fetch_assoc("select
		data_local.id
		from data_local
		left join data_template_data
		on data_local.id=data_template_data.local_data_id
		$sql_where"));
	$data_sources = db_fetch_assoc("select
		data_template_data.local_data_id,
		data_template_data.name,
		data_template_data.active,
		data_input.name as data_input_name,
		data_template.name as data_template_name,
		data_local.host_id
		from data_local
		left join data_template_data
		on data_local.id=data_template_data.local_data_id
		left join data_input
		on data_input.id=data_template_data.data_input_id
		left join data_template
		on data_local.data_template_id=data_template.id
		$sql_where
		order by data_template_data.name, data_local.host_id
		limit " . (read_config_option("num_rows_data_source")*($_REQUEST["page"]-1)) . "," . read_config_option("num_rows_data_source"));
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	
	/* sometimes its a pain to browse throug a long list page by page... so make a list of each page #, so the
	user can jump straight to it */
	$page_number = 0; $url_page_select = "";
	for ($i=0; ($i<$total_rows); $i += read_config_option("num_rows_data_source")) {
		$page_number++;
		
		if ($_REQUEST["page"] == $page_number) {
			$url_page_select .= "<strong><a class='linkOverDark' href='data_sources.php?filter=" . $_REQUEST["filter"] . "&host_id=" . $_REQUEST["host_id"] . "&page=$page_number'>$page_number</a></strong>";
		}else{
			$url_page_select .= "<a class='linkOverDark' href='data_sources.php?filter=" . $_REQUEST["filter"] . "&host_id=" . $_REQUEST["host_id"] . "&page=$page_number'>$page_number</a>";
		}
		
		if (($i+read_config_option("num_rows_data_source")) < $total_rows) { $url_page_select .= ","; }
	}
	
	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='5'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='data_sources.php?filter=" . $_REQUEST["filter"] . "&host_id=" . $_REQUEST["host_id"] . "&page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("num_rows_data_source")*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_data_source")) || ($total_rows < (read_config_option("num_rows_data_source")*$_REQUEST["page"]))) ? $total_rows : (read_config_option("num_rows_data_source")*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["page"] * read_config_option("num_rows_data_source")) < $total_rows) { $nav .= "<a class='linkOverDark' href='data_sources.php?filter=" . $_REQUEST["filter"] . "&host_id=" . $_REQUEST["host_id"] . "&page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * read_config_option("num_rows_data_source")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";
	
	print "	$nav
		<tr bgcolor='#" . $colors["header_panel"] . "'>
			<td class='textSubHeaderDark'>Name</td>
			<td class='textSubHeaderDark'>Data Input Method</td>
			<td class='textSubHeaderDark'>Active</td>
			<td class='textSubHeaderDark'>Template Name</td>
			<td width='1%' align='right' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAll(\"chk_\")'></td>
		<form name='chk' method='post' action='data_sources.php'>
		</tr>\n";
	
	$i = 0;
	if (sizeof($data_sources) > 0) {
	foreach ($data_sources as $data_source) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td>
				<a class='linkEditMain' href='data_sources.php?action=ds_edit&id=<?php print $data_source["local_data_id"];?>'><?php print eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", title_trim(expand_title($data_source["host_id"], $data_source["name"]), read_config_option("max_title_data_source")));?></a>
			</td>
			<td>
				<?php print $data_source["data_input_name"];?>
			</td>
			<td>
				<?php print (($data_source["active"] == "on") ? "Yes" : "<span style='color: red;'>No</span>");?>
			</td>
			<td>
				<?php print ((empty($data_source["data_template_name"])) ? "<em>None</em>" : $data_source["data_template_name"]);?>
			</td>
			<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
				<input type='checkbox' style='margin: 0px;' name='chk_<?php print $data_source["local_data_id"];?>' title="<?php print $data_source["name"];?>">
			</td>
		</tr>
		<?php
	}
		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Data Sources</em></td></tr>";
	}
	
	end_box(false);
	
	?>
	<table align='center' width='98%'>
		<tr>
			<td width='1' valign='top'>
				<img src='images/arrow.gif' alt='' align='absmiddle'>&nbsp;
			</td>
			<td align='right'>
				<?php form_base_dropdown("drp_action",$ds_actions,"","","1","","");?>
			</td>
			<td width='1' align='right'>
				<input type='image' src='images/button_go.gif' alt='Go'>
			</td>
		</tr>
	</table>
	
	<input type='hidden' name='action' value='actions'>
	</form>
	<?php
}
?>
