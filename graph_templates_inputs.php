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

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	case 'input_remove':
		input_remove();
		
		header("Location: graph_templates.php?action=template_edit&id=" . $_GET["graph_template_id"]);
		break;
	case 'input_edit':
		include_once("./include/top_header.php");
		
		input_edit();
		
		include_once("./include/bottom_footer.php");
		break;
}

function form_save() {
	if ((isset($_POST["save_component_input"])) && (!is_error_message())) {
		$graph_input_values = array();
		$selected_graph_items = array();
		
		$save["id"] = $_POST["graph_template_input_id"];
		$save["hash"] = get_hash_graph_template($_POST["graph_template_input_id"], "graph_template_input");
		$save["graph_template_id"] = $_POST["graph_template_id"];
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save["description"] = form_input_validate($_POST["description"], "description", "", true, 3);
		$save["column_name"] = form_input_validate($_POST["column_name"], "column_name", "", true, 3);
		
		if (!is_error_message()) {
			$graph_template_input_id = sql_save($save, "graph_template_input");
			
			/* list all select graph items for use down below */
			$i = 0;
			while (list($var, $val) = each($_POST)) {
				if (eregi("^i_", $var)) {
					$selected_graph_items[$i] = str_replace("i_", "", $var);
					$i++;
				}
			}
			
			if (!empty($_POST["any_selected_item"])) {
				/* list each graph using this graph template and find the "current" value for this input */
				$graphs = db_fetch_assoc("select " . $save["column_name"] . ",local_graph_id from graph_templates_item where graph_template_id=" . $save["graph_template_id"] . " and local_graph_id>0 and local_graph_template_item_id=" . $_POST["any_selected_item"]);
				
				if (sizeof($graphs) > 0) {
				foreach ($graphs as $graph) {
					$graph_input_values{$graph["local_graph_id"]} = $graph{$save["column_name"]};
				}
				}
			}
			
			if ($graph_template_input_id) {
				raise_message(1);
				
				db_execute("delete from graph_template_input_defs where graph_template_input_id=$graph_template_input_id");
				
				if (sizeof($selected_graph_items) > 0) {
				foreach ($selected_graph_items as $graph_template_item_id) {
					db_execute("insert into graph_template_input_defs (graph_template_input_id,graph_template_item_id)
						values ($graph_template_input_id,$graph_template_item_id)");
					
				}
				}
				
				/* loop through each graph attached to this graph template and re-apply the current value for this
				input */
				if (sizeof($graph_input_values) > 0) {
				while (list($local_graph_id, $value) = each($graph_input_values)) {
					db_execute("update graph_templates_item set " . $save["column_name"] . "='$value' where local_graph_id=$local_graph_id and " . array_to_sql_or($selected_graph_items, "local_graph_template_item_id"));
				}
				}
			}else{
				raise_message(2);
			}
		}
		
		if (is_error_message()) {
			header("Location: graph_templates_inputs.php?action=input_edit&graph_template_input_id=" . (empty($graph_template_input_id) ? $_POST["graph_template_input_id"] : $graph_template_input_id) . "&graph_template_id=" . $_POST["graph_template_id"]);
			exit;
		}else{
			header("Location: graph_templates.php?action=template_edit&id=" . $_POST["graph_template_id"]);
			exit;
		}
	}
}

/* ------------------------------------
    input - Graph Template Item Inputs 
   ------------------------------------ */

function input_remove() {
	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the input item <strong>'" . db_fetch_cell("select name from graph_template_input where id=" . $_GET["id"]) . "'</strong>? NOTE: Deleting this item will NOT affect graphs that use this template.", $_SERVER["HTTP_REFERER"], "graph_templates_inputs.php?action=input_remove&id=" . $_GET["id"] . "&graph_template_id=" . $_GET["graph_template_id"]);
		include("./include/bottom_footer.php");
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from graph_template_input where id=" . $_GET["id"]);
		db_execute("delete from graph_template_input_defs where graph_template_input_id=" . $_GET["id"]);
	}
}

function input_edit() {
	global $colors, $consolidation_functions, $graph_item_types, $struct_graph_item, $fields_graph_template_input_edit;
	
	$header_label = "[edit graph: " . db_fetch_cell("select name from graph_templates where id=" . $_GET["graph_template_id"]) . "]";
	
	/* get a list of all graph item field names and populate an array for user display */
	while (list($field_name, $field_array) = each($struct_graph_item)) {
		if ($field_array["method"] != "view") {
			$graph_template_items[$field_name] = $field_array["friendly_name"];
		}
	}
	
	if (!empty($_GET["id"])) {
		$graph_template_input = db_fetch_row("select * from graph_template_input where id=" . $_GET["id"]);
	}
	
	start_box("<strong>Graph Item Inputs</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	
	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_graph_template_input_edit, (isset($graph_template_input) ? $graph_template_input : array()), (isset($graph_template_items) ? $graph_template_items : array()), $_GET)
		));
	
	if (!(isset($_GET["id"]))) { $_GET["id"] = 0; }
	
	$item_list = db_fetch_assoc("select
		CONCAT_WS(' - ',data_template_data.name,data_template_rrd.data_source_name) as data_source_name,
		graph_templates_item.text_format,
		graph_templates_item.id as graph_templates_item_id,
		graph_templates_item.graph_type_id,
		graph_templates_item.consolidation_function_id,
		graph_template_input_defs.graph_template_input_id
		from graph_templates_item
		left join graph_template_input_defs on (graph_template_input_defs.graph_template_item_id=graph_templates_item.id and graph_template_input_defs.graph_template_input_id=" . $_GET["id"] . ")
		left join data_template_rrd on graph_templates_item.task_item_id=data_template_rrd.id
		left join data_local on data_template_rrd.local_data_id=data_local.id
		left join data_template_data on data_local.id=data_template_data.local_data_id
		where graph_templates_item.local_graph_id=0
		and graph_templates_item.graph_template_id=" . $_GET["graph_template_id"] . "
		order by graph_templates_item.sequence");
	
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Associated Graph Items</font><br>
			Select the graph items that you want to accept user input for.
		</td>
		<td>
		<?php
		$i = 0; $any_selected_item = "";
		if (sizeof($item_list) > 0) {
		foreach ($item_list as $item) {
			if ($item["graph_template_input_id"] == "") {
				$old_value = "";
			}else{
				$old_value = "on";
				$any_selected_item = $item["graph_templates_item_id"];
			}
			
			if ($graph_item_types{$item["graph_type_id"]} == "GPRINT") {
				$start_bold = "";
				$end_bold = "";
			}else{
				$start_bold = "<strong>";
				$end_bold = "</strong>";
			}
			
			$name = "$start_bold Item #" . ($i+1) . ": " . $graph_item_types{$item["graph_type_id"]} . " (" . $consolidation_functions{$item["consolidation_function_id"]} . ")$end_bold";
			form_checkbox("i_" . $item["graph_templates_item_id"], $old_value, $name,"",$_GET["graph_template_id"],true); print "<br>";
			
			$i++;
		}
		}else{
			print "<em>No Items</em>";
		}
		?>
		</td>
	</tr>
	
	<?php
	end_box();
	
	form_hidden_box("any_selected_item", $any_selected_item, "");
	
	form_save_button("graph_templates.php?action=template_edit&id=" . $_GET["graph_template_id"]);
}
