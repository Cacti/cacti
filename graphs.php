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
include_once("./lib/template.php");
include_once("./lib/tree.php");
include_once("./lib/tree_view.php");
include_once("./lib/rrd.php");

$graph_actions = array(
	1 => "Delete",
	2 => "Change Graph Template",
	5 => "Change Host",
	3 => "Duplicate",
	4 => "Convert to Graph Template"
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
	case 'graph_diff':
		include_once("./include/top_header.php");
		
		graph_diff();
		
		include_once("./include/bottom_footer.php");
		break;
	case 'item':
		include_once("./include/top_header.php");
		
		item();
		
		include_once("./include/bottom_footer.php");
		break;
	case 'graph_remove':
		graph_remove();
		
		header("Location: graphs.php");
		break;
	case 'graph_edit':
		include_once("./include/top_header.php");
		
		graph_edit();
		
		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");
		
		graph();
		
		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function add_tree_names_to_actions_array() {
	global $graph_actions;
	
	/* add a list of tree names to the actions dropdown */
	$trees = db_fetch_assoc("select id,name from graph_tree order by name");
	
	if (sizeof($trees) > 0) {
	foreach ($trees as $tree) {
		$graph_actions{"tr_" . $tree["id"]} = "Place on a Tree (" . $tree["name"] . ")";
	}
	}
}

function draw_user_form_tabs() {
	?>
	<table class='tabs' width='98%' cellspacing='0' cellpadding='3' align='center'>
		<tr>
			<td width='1'></td>
			<td <?php print (strstr($_SERVER["PHP_SELF"], "graphs.php") ? "bgcolor='silver'" : "bgcolor='#DFDFDF'");?> nowrap='nowrap' width='150' align='center' class='tab'>
				<span class='textHeader'><a href='graph_management.php'>Graph Management</a></span>
			</td>
			<td width='1'></td>
			<td <?php print (strstr($_SERVER["PHP_SELF"], "cdef.php") ? "bgcolor='silver'" : "bgcolor='#DFDFDF'");?> nowrap='nowrap' width='80' align='center' class='tab'>
				<span class='textHeader'><a href='cdef.php'>CDEFs</a></span>
			</td>
			<td width='1'></td>
			<td <?php print (strstr($_SERVER["PHP_SELF"], "color.php") ? "bgcolor='silver'" : "bgcolor='#DFDFDF'");?> nowrap='nowrap' width='80' align='center' class='tab'>
				<span class='textHeader'><a href='color.php'>Colors</a></span>
			</td>
			<td width='1'></td>
			<td <?php print (strstr($_SERVER["PHP_SELF"], "gprint_presets.php") ? "bgcolor='silver'" : "bgcolor='#DFDFDF'");?> nowrap='nowrap' width='130' align='center' class='tab'>
				<span class='textHeader'><a href='gprint_presets.php'>GPRINT Presets</a></span>
			</td>
			<td></td>
		</tr>
	</table>
	<br>
<?php }

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset($_POST["save_component_graph_new"])) && (!empty($_POST["graph_template_id"]))) {
		$save["id"] = $_POST["local_graph_id"];
		$save["graph_template_id"] = $_POST["graph_template_id"];
		$save["host_id"] = $_POST["host_id"];
		
		$local_graph_id = sql_save($save, "graph_local");
		
		change_graph_template($local_graph_id, $_POST["graph_template_id"], true);
		
		/* update the title cache */
		update_graph_title_cache($local_graph_id);
	}
	
	if (isset($_POST["save_component_graph"])) {
		$save1["id"] = $_POST["local_graph_id"];
		$save1["host_id"] = $_POST["host_id"];
		$save1["graph_template_id"] = $_POST["graph_template_id"];
		
		$save2["id"] = $_POST["graph_template_graph_id"];
		$save2["local_graph_template_graph_id"] = $_POST["local_graph_template_graph_id"];
		$save2["graph_template_id"] = $_POST["graph_template_id"];
		$save2["image_format_id"] = form_input_validate($_POST["image_format_id"], "image_format_id", "", true, 3);
		$save2["title"] = form_input_validate($_POST["title"], "title", "", false, 3);
		$save2["height"] = form_input_validate($_POST["height"], "height", "^[0-9]+$", false, 3);
		$save2["width"] = form_input_validate($_POST["width"], "width", "^[0-9]+$", false, 3);
		$save2["upper_limit"] = form_input_validate($_POST["upper_limit"], "upper_limit", "^-?[0-9]+$", false, 3);
		$save2["lower_limit"] = form_input_validate($_POST["lower_limit"], "lower_limit", "^-?[0-9]+$", false, 3);
		$save2["vertical_label"] = form_input_validate($_POST["vertical_label"], "vertical_label", "", true, 3);
		$save2["auto_scale"] = form_input_validate((isset($_POST["auto_scale"]) ? $_POST["auto_scale"] : ""), "auto_scale", "", true, 3);
		$save2["auto_scale_opts"] = form_input_validate($_POST["auto_scale_opts"], "auto_scale_opts", "", true, 3);
		$save2["auto_scale_log"] = form_input_validate((isset($_POST["auto_scale_log"]) ? $_POST["auto_scale_log"] : ""), "auto_scale_log", "", true, 3);
		$save2["auto_scale_rigid"] = form_input_validate((isset($_POST["auto_scale_rigid"]) ? $_POST["auto_scale_rigid"] : ""), "auto_scale_rigid", "", true, 3);
		$save2["auto_padding"] = form_input_validate((isset($_POST["auto_padding"]) ? $_POST["auto_padding"] : ""), "auto_padding", "", true, 3);
		$save2["base_value"] = form_input_validate($_POST["base_value"], "base_value", "^[0-9]+$", false, 3);
		$save2["export"] = form_input_validate((isset($_POST["export"]) ? $_POST["export"] : ""), "export", "", true, 3);
		$save2["unit_value"] = form_input_validate($_POST["unit_value"], "unit_value", "", true, 3);
		$save2["unit_exponent_value"] = form_input_validate($_POST["unit_exponent_value"], "unit_exponent_value", "^-?[0-9]+$", false, 3);
		
		if (!is_error_message()) {
			$local_graph_id = sql_save($save1, "graph_local");
		}
		
		if (!is_error_message()) {
			$save2["local_graph_id"] = $local_graph_id;
			$graph_templates_graph_id = sql_save($save2, "graph_templates_graph");
			
			if ($graph_templates_graph_id) {
				raise_message(1);
				
				/* if template information chanegd, update all necessary template information */
				if ($_POST["graph_template_id"] != $_POST["_graph_template_id"]) {
					/* check to see if the number of graph items differs, if it does; we need user input */
					if ((!empty($_POST["graph_template_id"])) && (!empty($_POST["local_graph_id"])) && (sizeof(db_fetch_assoc("select id from graph_templates_item where local_graph_id=$local_graph_id")) != sizeof(db_fetch_assoc("select id from graph_templates_item where local_graph_id=0 and graph_template_id=" . $_POST["graph_template_id"])))) {
						/* set the template back, since the user may choose not to go through with the change
						at this point */
						db_execute("update graph_local set graph_template_id=" . $_POST["_graph_template_id"] . " where id=$local_graph_id");
						db_execute("update graph_templates_graph set graph_template_id=" . $_POST["_graph_template_id"] . " where local_graph_id=$local_graph_id");
						
						header("Location: graphs.php?action=graph_diff&id=$local_graph_id&graph_template_id=" . $_POST["graph_template_id"]);
						exit;
					}
				}
			}else{
				raise_message(2);
			}
			
			/* update the title cache */
			update_graph_title_cache($local_graph_id);
		}
		
		if ((!is_error_message()) && ($_POST["graph_template_id"] != $_POST["_graph_template_id"])) {
			change_graph_template($local_graph_id, $_POST["graph_template_id"], true);
		}elseif (!empty($_POST["graph_template_id"])) {
			update_graph_snmp_query_cache($local_graph_id);
		}
	}
	
	if (isset($_POST["save_component_input"])) {
		/* first; get the current graph template id */
		$graph_template_id = db_fetch_cell("select graph_template_id from graph_local where id=" . $_POST["local_graph_id"]);
		
		/* get all inputs that go along with this graph template */
		$input_list = db_fetch_assoc("select id,column_name from graph_template_input where graph_template_id=$graph_template_id");
		
		if (sizeof($input_list) > 0) {
		foreach ($input_list as $input) {
			/* we need to find out which graph items will be affected by saving this particular item */
			$item_list = db_fetch_assoc("select
				graph_templates_item.id
				from graph_template_input_defs,graph_templates_item
				where graph_template_input_defs.graph_template_item_id=graph_templates_item.local_graph_template_item_id
				and graph_templates_item.local_graph_id=" . $_POST["local_graph_id"] . "
				and graph_template_input_defs.graph_template_input_id=" . $input["id"]);
			
			/* loop through each item affected and update column data */
			if (sizeof($item_list) > 0) {
			foreach ($item_list as $item) {
				/* if we are changing templates, the POST vars we are searching for here will not exist.
				this is because the db and form are out of sync here, but it is ok to just skip over saving
				the inputs in this case. */
				if (isset($_POST{$input["column_name"] . "_" . $input["id"]})) {
					db_execute("update graph_templates_item set " . $input["column_name"] . "='" . $_POST{$input["column_name"] . "_" . $input["id"]} . "' where id=" . $item["id"]);
				}
			}
			}
		}
		}
	}
	
	if (isset($_POST["save_component_graph_diff"])) {
		if ($_POST["type"] == "1") {
			$intrusive = true;
		}elseif ($_POST["type"] == "2") {
			$intrusive = false;
		}
		
		change_graph_template($_POST["local_graph_id"], $_POST["graph_template_id"], $intrusive);
	}
	
	if ((isset($_POST["save_component_graph_new"])) && (empty($_POST["graph_template_id"]))) {
		header("Location: graphs.php?action=graph_edit&host_id=" . $_POST["host_id"] . "&new=1");
	}elseif ((is_error_message()) || (empty($_POST["local_graph_id"])) || (isset($_POST["save_component_graph_diff"])) || ($_POST["graph_template_id"] != $_POST["_graph_template_id"]) || ($_POST["host_id"] != $_POST["_host_id"])) {
		header("Location: graphs.php?action=graph_edit&id=" . (empty($local_graph_id) ? $_POST["local_graph_id"] : $local_graph_id) . (isset($_POST["host_id"]) ? "&host_id=" . $_POST["host_id"] : ""));
	}else{
		header("Location: graphs.php");
	}
}

/* ------------------------
    The "actions" function 
   ------------------------ */

function form_actions() {
	global $colors, $graph_actions;
	
	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));
		
		if ($_POST["drp_action"] == "1") { /* delete */
			db_execute("delete from graph_templates_graph where " . array_to_sql_or($selected_items, "local_graph_id"));
			db_execute("delete from graph_templates_item where " . array_to_sql_or($selected_items, "local_graph_id"));
			db_execute("delete from graph_tree_items where " . array_to_sql_or($selected_items, "local_graph_id"));
			db_execute("delete from graph_local where " . array_to_sql_or($selected_items, "id"));
		}elseif ($_POST["drp_action"] == "2") { /* change graph template */
			for ($i=0;($i<count($selected_items));$i++) {
				change_graph_template($selected_items[$i], $_POST["graph_template_id"], true);	
			}
		}elseif ($_POST["drp_action"] == "3") { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				duplicate_graph($selected_items[$i], 0, $_POST["title_format"]);
			}
		}elseif ($_POST["drp_action"] == "4") { /* graph -> graph template */
			for ($i=0;($i<count($selected_items));$i++) {
				graph_to_graph_template($selected_items[$i], $_POST["title_format"]);
			}
		}elseif (ereg("^tr_([0-9]+)$", $_POST["drp_action"], $matches)) { /* place on tree */
			for ($i=0;($i<count($selected_items));$i++) {
				$old_order_key = db_fetch_cell("select order_key from graph_tree_items where id=" . $_POST["tree_item_id"]);
				$new_order_key = get_next_tree_id($old_order_key,"graph_tree_items","order_key", "graph_tree_id=" . $_POST["tree_id"]);
				
				db_execute("insert into graph_tree_items (graph_tree_id,title,order_key,local_graph_id,rra_id)
					values (" . $_POST["tree_id"] . ",'','$new_order_key'," . $selected_items[$i] . ",1)");
			}
		}elseif ($_POST["drp_action"] == "5") { /* change host */
			for ($i=0;($i<count($selected_items));$i++) {
				db_execute("update graph_local set host_id=" . $_POST["host_id"] . " where id=" . $selected_items[$i]);
			}
		}
		
		header("Location: graphs.php");
		exit;
	}
	
	/* setup some variables */
	$graph_list = ""; $i = 0;
	
	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			$graph_list .= "<li>" . get_graph_title($matches[1]) . "<br>";
			$graph_array[$i] = $matches[1];
		}
		
		$i++;
	}
	
	include_once("./include/top_header.php");
	
	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();
	
	start_box("<strong>" . $graph_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");
	
	print "<form action='graphs.php' method='post'>\n";
	
	if ($_POST["drp_action"] == "1") { /* delete */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to delete the following graphs?</p>
					<p>$graph_list</p>
				</td>
			</tr>\n
			";
	}elseif ($_POST["drp_action"] == "2") { /* change graph template */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Choose a graph template and click save to change the graph template for
					the following graphs. Be aware that all warnings will be suppressed during the
					conversion, so graph data loss is possible.</p>
					<p>$graph_list</p>
					<p><strong>New Graph Template:</strong><br>"; form_dropdown("graph_template_id",db_fetch_assoc("select graph_templates.id,graph_templates.name from graph_templates"),"name","id","","","0"); print "</p>
				</td>
			</tr>\n
			";
	}elseif ($_POST["drp_action"] == "3") { /* duplicate */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>When you click save, the following graphs will be duplicated. You can
					optionally change the title format for the new graphs.</p>
					<p>$graph_list</p>
					<p><strong>Title Format:</strong><br>"; form_base_text_box("title_format", "<graph_title> (1)", "", "255", "30", "textbox"); print "</p>
				</td>
			</tr>\n
			";
	}elseif ($_POST["drp_action"] == "4") { /* graph -> graph template */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>When you click save, the following graphs will be converted into graph templates. 
					You can optionally change the title format for the new graph templates.</p>
					<p>$graph_list</p>
					<p><strong>Title Format:</strong><br>"; form_base_text_box("title_format", "<graph_title> Template", "", "255", "30", "textbox"); print "</p>
				</td>
			</tr>\n
			";
	}elseif (ereg("^tr_([0-9]+)$", $_POST["drp_action"], $matches)) { /* place on tree */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>When you click save, the following graphs will be place under the branch selected
					below.</p>
					<p>$graph_list</p>
					<p><strong>Destination Branch:</strong><br>"; grow_dropdown_tree($matches[1], "tree_item_id", "0"); print "</p>
				</td>
			</tr>\n
			<input type='hidden' name='tree_id' value='" . $matches[1] . "'>\n
			";
	}elseif ($_POST["drp_action"] == "5") { /* change host */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Choose a new host for these graphs:</p>
					<p>$graph_list</p>
					<p><strong>New Host:</strong><br>"; form_dropdown("host_id",db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname"),"name","id","","","0"); print "</p>
				</td>
			</tr>\n
			";
	}
	
	if (!isset($graph_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one graph.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='image' src='images/button_yes.gif' alt='Save' align='absmiddle'>";
	}
	
	print "	<tr>
			<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($graph_array) ? serialize($graph_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				<a href='graphs.php'><img src='images/button_no.gif' alt='Cancel' align='absmiddle' border='0'></a>
				$save_html
			</td>
		</tr>
		";	
	
	end_box();
	
	include_once("./include/bottom_footer.php");
}

/* -----------------------
    item - Graph Items 
   ----------------------- */

function item() {
	global $colors, $consolidation_functions, $graph_item_types, $struct_graph_item;
	
	if (empty($_GET["id"])) {
		$template_item_list = array();
		
		$header_label = "[new]";
	}else{
		$template_item_list = db_fetch_assoc("select
			graph_templates_item.id,
			graph_templates_item.text_format,
			graph_templates_item.value,
			graph_templates_item.hard_return,
			graph_templates_item.graph_type_id,
			graph_templates_item.consolidation_function_id,
			data_template_rrd.data_source_name,
			cdef.name as cdef_name,
			colors.hex
			from graph_templates_item
			left join data_template_rrd on graph_templates_item.task_item_id=data_template_rrd.id
			left join data_local on data_template_rrd.local_data_id=data_local.id
			left join data_template_data on data_local.id=data_template_data.local_data_id
			left join cdef on cdef_id=cdef.id
			left join colors on color_id=colors.id
			where graph_templates_item.local_graph_id=" . $_GET["id"] . "
			order by graph_templates_item.sequence");
		
		$host_id = db_fetch_cell("select host_id from graph_local where id=" . $_GET["id"]);
		$header_label = "[edit: " . get_graph_title($_GET["id"]) . "]";
	}
	
	$graph_template_id = db_fetch_cell("select graph_template_id from graph_local where id=" . $_GET["id"]);
	
	if (empty($graph_template_id)) {
		$add_text = "graphs_items.php?action=item_edit&local_graph_id=" . $_GET["id"] . "&host_id=$host_id";
	}else{
		$add_text = "";
	}
	
	start_box("<strong>Graph Items</strong> $header_label", "98%", $colors["header"], "3", "center", $add_text);
	draw_graph_items_list($template_item_list, "graphs_items.php", "local_graph_id=" . $_GET["id"], (empty($graph_template_id) ? false : true));
	end_box();
	
	/* only display the "inputs" area if we are using a graph template for this graph */
	if ($graph_template_id != "0") {
		start_box("<strong>Graph Item Inputs</strong>", "98%", $colors["header"], "3", "center", "");
		
		print "<form method='post' action='graphs.php'>\n";
		
		$input_item_list = db_fetch_assoc("select * from graph_template_input where graph_template_id=$graph_template_id order by name");
		
		/* modifications to the default graph items array */
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
		
		$form_array = array();
		
		if (sizeof($input_item_list) > 0) {
		foreach ($input_item_list as $item) {
			$current_def_value = db_fetch_row("select 
				graph_templates_item." . $item["column_name"] . ",
				graph_templates_item.id
				from graph_templates_item,graph_template_input_defs 
				where graph_template_input_defs.graph_template_item_id=graph_templates_item.local_graph_template_item_id 
				and graph_template_input_defs.graph_template_input_id=" . $item["id"] . "
				and graph_templates_item.local_graph_id=" . $_GET["id"] . "
				limit 0,1");
			
			$form_array += array($item["column_name"] . "_" . $item["id"] => $struct_graph_item{$item["column_name"]});
			
			$form_array{$item["column_name"] . "_" . $item["id"]}["friendly_name"] = $item["name"];
			$form_array{$item["column_name"] . "_" . $item["id"]}["description"] = $item["description"];
			$form_array{$item["column_name"] . "_" . $item["id"]}["value"] = $current_def_value{$item["column_name"]};
		}
		}else{
			print "<tr bgcolor='#" . $colors["form_alternate2"] . "'><td colspan='2'><em>No Inputs</em></td></tr>";
		}
		
		if (sizeof($input_item_list > 0)) {
			draw_edit_form(
				array(
					"config" => array(
						"left_column_width" => "35%"
						),
					"fields" => $form_array
					)
				);
		}
		
		end_box();
	}
	
	form_hidden_id("local_graph_id",$_GET["id"]);
	form_hidden_box("save_component_input","1","");
}

/* ------------------------------------
    graph - Graphs
   ------------------------------------ */

function graph_diff() {
	global $colors, $struct_graph_item, $graph_item_types, $consolidation_functions;
	
	$template_query = "select
		graph_templates_item.id,
		graph_templates_item.text_format,
		graph_templates_item.value,
		graph_templates_item.hard_return,
		graph_templates_item.consolidation_function_id,
		graph_templates_item.graph_type_id,
		CONCAT_WS(' - ',data_template_data.name,data_template_rrd.data_source_name) as task_item_id,
		cdef.name as cdef_id,
		colors.hex as color_id
		from graph_templates_item left join data_template_rrd on graph_templates_item.task_item_id=data_template_rrd.id
		left join data_local on data_template_rrd.local_data_id=data_local.id
		left join data_template_data on data_local.id=data_template_data.local_data_id
		left join cdef on cdef_id=cdef.id
		left join colors on color_id=colors.id";
	
	/* first, get information about the graph template as that's what we're going to model this
	graph after */
	$graph_template_items = db_fetch_assoc("
		$template_query
		where graph_templates_item.graph_template_id=" . $_GET["graph_template_id"] . "
		and graph_templates_item.local_graph_id=0
		order by graph_templates_item.sequence");
	
	/* next, get information about the current graph so we can make the appropriate comparisons */
	$graph_items = db_fetch_assoc("
		$template_query
		where graph_templates_item.local_graph_id=" . $_GET["id"] . "
		order by graph_templates_item.sequence");
	
	$graph_template_inputs = db_fetch_assoc("select
		graph_template_input.column_name,
		graph_template_input_defs.graph_template_item_id
		from graph_template_input,graph_template_input_defs
		where graph_template_input.id=graph_template_input_defs.graph_template_input_id
		and graph_template_input.graph_template_id=" . $_GET["graph_template_id"]);
	
	/* ok, we want to loop through the array with the GREATEST number of items so we don't have to worry
	about tacking items on the end */
	if (sizeof($graph_template_items) > sizeof($graph_items)) {
		$items = $graph_template_items;
	}else{
		$items = $graph_items;
	}
	
	?>
	<table style="background-color: #f5f5f5; border: 1px solid #aaaaaa;" width="98%" align="center">
		<tr>          
			<td class="textArea">
				The template you have selected requires some changes to be made to the structure of
				your graph. Below is a preview of your graph along with changes that need to be completed
				as shown in the left-hand column.
			</td>
		</tr>
	</table>
	<br>
	<?php
	
	start_box("<strong>Graph Preview</strong>", "98%", $colors["header"], "3", "center", "");
	
	$graph_item_actions = array("normal" => "", "add" => "+", "delete" => "-");
	
	$group_counter = 0; $i = 0; $mode = "normal"; $_graph_type_name = "";
	
	if (sizeof($items) > 0) {
	foreach ($items as $item) {
		reset($struct_graph_item);
		
		/* graph grouping display logic */
		$bold_this_row = false; $use_custom_row_color = false; $action_css = ""; unset($graph_preview_item_values);
		
		if ((sizeof($graph_template_items) > sizeof($graph_items)) && ($i >= sizeof($graph_items))) {
			$mode = "add";
			$user_message = "When you click save, the items marked with a '<strong>+</strong>' will be added <strong>(Recommended)</strong>.";
		}elseif ((sizeof($graph_template_items) < sizeof($graph_items)) && ($i >= sizeof($graph_template_items))) {
			$mode = "delete";
			$user_message = "When you click save, the items marked with a '<strong>-</strong>' will be removed <strong>(Recommended)</strong>.";
		}
		
		/* here is the fun meshing part. first we check the graph template to see if there is an input
		for each field of this row. if there is, we revert to the value stored in the graph, if not
		we revert to the value stored in the template. got that? ;) */
		for ($j=0; ($j < count($graph_template_inputs)); $j++) {
			if ($graph_template_inputs[$j]["graph_template_item_id"] == (isset($graph_template_items[$i]["id"]) ? $graph_template_items[$i]["id"] : "")) {
				/* if we find out that there is an "input" covering this field/item, use the 
				value from the graph, not the template */
				$graph_item_field_name = (isset($graph_template_inputs[$j]["column_name"]) ? $graph_template_inputs[$j]["column_name"] : "");
				$graph_preview_item_values[$graph_item_field_name] = (isset($graph_items[$i][$graph_item_field_name]) ? $graph_items[$i][$graph_item_field_name] : "");
			}
		}
		
		/* go back through each graph field and find out which ones haven't been covered by the 
		"inputs" above. for each one, use the value from the template */
		while (list($field_name, $field_array) = each($struct_graph_item)) {
			if ($mode == "delete") {
				$graph_preview_item_values[$field_name] = (isset($graph_items[$i][$field_name]) ? $graph_items[$i][$field_name] : "");
			}elseif (!isset($graph_preview_item_values[$field_name])) {
				$graph_preview_item_values[$field_name] = (isset($graph_template_items[$i][$field_name]) ? $graph_template_items[$i][$field_name] : "");
			}
		}
		
		/* "prepare" array values */
		$consolidation_function_id = $graph_preview_item_values["consolidation_function_id"];
		$graph_type_id = $graph_preview_item_values["graph_type_id"];
		
		/* color logic */
		if (($graph_item_types[$graph_type_id] != "GPRINT") && ($graph_item_types[$graph_type_id] != $_graph_type_name)) {
			$bold_this_row = true; $use_custom_row_color = true; $hard_return = "";
			
			if ($group_counter % 2 == 0) {
				$alternate_color_1 = "EEEEEE";
				$alternate_color_2 = "EEEEEE";
				$custom_row_color = "D5D5D5";
			}else{
				$alternate_color_1 = $colors["alternate"];
				$alternate_color_2 = $colors["alternate"];
				$custom_row_color = "D2D6E7";
			}
			
			$group_counter++;
		}
		
		$_graph_type_name = $graph_item_types[$graph_type_id];
		
		/* alternating row colors */
		if ($use_custom_row_color == false) {
			if ($i % 2 == 0) {
				$action_column_color = $alternate_color_1;
			}else{
				$action_column_color = $alternate_color_2;
			}
		}else{
			$action_column_color = $custom_row_color;
		}
		
		print "<tr bgcolor='#$action_column_color'>"; $i++;
		
		/* make the left-hand column blue or red depending on if "add"/"remove" mode is set */
		if ($mode == "add") {
			$action_column_color = $colors["header"];
			$action_css = "";
		}elseif ($mode == "delete") {
			$action_column_color = "C63636";
			$action_css = "text-decoration: line-through;";
		}
		
		if ($bold_this_row == true) {
			$action_css .= " font-weight:bold;";
		}
		
		/* draw the TD that shows the user whether we are going to: KEEP, ADD, or DROP the item */
		print "<td width='1%' bgcolor='#$action_column_color' style='font-weight: bold; color: white;'>" . $graph_item_actions[$mode] . "</td>";
		print "<td style='$action_css'><strong>Item # " . $i . "</strong></td>\n";
		
		if (empty($graph_preview_item_values["task_item_id"])) { $graph_preview_item_values["task_item_id"] = "No Task"; }
		
		switch (true) {
		case ereg("(AREA|STACK|GPRINT|LINE[123])", $_graph_type_name):
			$matrix_title = "(" . $graph_preview_item_values["task_item_id"] . "): " . $graph_preview_item_values["text_format"];
			break;
		case ereg("(HRULE|VRULE)", $_graph_type_name):
			$matrix_title = "HRULE: " . $graph_preview_item_values["value"];
			break;
		case ereg("(COMMENT)", $_graph_type_name):
			$matrix_title = "COMMENT: " . $graph_preview_item_values["text_format"];
			break;
		}
		
		/* use the cdef name (if in use) if all else fails */
		if ($matrix_title == "") {
			if ($graph_preview_item_values["cdef_id"] != "") {
				$matrix_title .= "CDEF: " . $graph_preview_item_values["cdef_id"];
			}
		}
		
		if ($graph_preview_item_values["hard_return"] == "on") {
			$hard_return = "<strong><font color=\"#FF0000\">&lt;HR&gt;</font></strong>";
		}
		
		print "<td style='$action_css'>" . htmlspecialchars($matrix_title) . $hard_return . "</td>\n";
		print "<td style='$action_css'>" . $graph_item_types{$graph_preview_item_values["graph_type_id"]} . "</td>\n";
		print "<td style='$action_css'>" . $consolidation_functions{$graph_preview_item_values["consolidation_function_id"]} . "</td>\n";
		print "<td" . ((!empty($graph_preview_item_values["color_id"])) ? " bgcolor='#" . $graph_preview_item_values["color_id"] . "'" : "") . " width='1%'>&nbsp;</td>\n";
		print "<td style='$action_css'>" . $graph_preview_item_values["color_id"] . "</td>\n";
		
		print "</tr>";
	}
	}else{
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
			<td colspan="7">
				<em>No Items</em>
			</td>
		</tr><?php
	}
	end_box();	
	
	?>
	<form action="graphs.php" method="post">
	<table style="background-color: #f5f5f5; border: 1px solid #aaaaaa;" width="98%" align="center">
		<tr>          
			<td class="textArea">
				<input type='radio' name='type' value='1' checked>&nbsp;<?php print $user_message;?><br>
				<input type='radio' name='type' value='2'>&nbsp;When you click save, the graph items will remain untouched (could cause inconsistencies).
			</td>
		</tr>
	</table>
	
	<br>
	
	<input type="hidden" name="action" value="save">
	<input type="hidden" name="save_component_graph_diff" value="1">
	<input type="hidden" name="local_graph_id" value="<?php print $_GET["id"];?>">
	<input type="hidden" name="graph_template_id" value="<?php print $_GET["graph_template_id"];?>">
	<?php
	
	form_save_button("graphs.php?action=graph_edit&id=" . $_GET["id"]);
}

function graph_edit() {
	global $colors, $struct_graph, $image_types;
	
	$use_graph_template = true;
	
	if (!empty($_GET["id"])) {
		$local_graph_template_graph_id = db_fetch_cell("select local_graph_template_graph_id from graph_templates_graph where local_graph_id=" . $_GET["id"]);
		
		$graphs = db_fetch_row("select * from graph_templates_graph where local_graph_id=" . $_GET["id"]);
		$graphs_template = db_fetch_row("select * from graph_templates_graph where id=$local_graph_template_graph_id");
		
		$host_id = db_fetch_cell("select host_id from graph_local where id=" . $_GET["id"]);
		$header_label = "[edit: " . get_graph_title($_GET["id"]) . "]";
		
		if ($graphs["graph_template_id"] == "0") {
			$use_graph_template = false;
		}
	}else{
		$header_label = "[new]";
		$use_graph_template = false;
	}
	
	/* graph item list goes here */
	if (!empty($_GET["id"])) {
		item();
	}
	
	/* handle debug mode */
	if (isset($_GET["debug"])) {
		if ($_GET["debug"] == "0") {
			kill_session_var("graph_debug_mode");
		}elseif ($_GET["debug"] == "1") {
			$_SESSION["graph_debug_mode"] = true;
		}
	}
	
	/* display the debug mode box if the user wants it */
	if ((isset($_SESSION["graph_debug_mode"])) && (isset($_GET["id"]))) {
		start_box("<strong>Graph Debug</strong>", "98%", $colors["header"], "3", "center", "");
		
		$graph_data_array["output_flag"] = 2;
		
		?>
		<tr>
			<td>
				<img src="graph_image.php?local_graph_id=<?php print $_GET["id"];?>&rra_id=1&graph_start=-86400&graph_height=100&graph_width=350" alt="">
			</td>
		</tr>
		<tr>
			<td>
				<pre><?php print rrdtool_function_graph($_GET["id"], 1, $graph_data_array);?></pre>
			</td>
		</tr>
		<?php
		
		end_box();
	}
	
	start_box("<strong>Graph Template Selection</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	
	$form_array = array(
		"graph_template_id" => array(
			"method" => "drop_sql",
			"friendly_name" => "Selected Graph Template",
			"description" => "Choose a graph template to apply to this graph. Please note that graph data may be lost if you change the graph template after one is already applied.",
			"value" => (isset($graphs) ? $graphs["graph_template_id"] : "0"),
			"none_value" => "None",
			"sql" => "select graph_templates.id,graph_templates.name from graph_templates order by name"
			),
		"host_id" => array(
			"method" => "drop_sql",
			"friendly_name" => "Host",
			"description" => "Choose the host that this graph belongs to.",
			"value" => (isset($_GET["host_id"]) ? $_GET["host_id"] : $host_id),
			"none_value" => "None",
			"sql" => "select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname"
			),
		"debug" => array(
			"method" => "custom",
			"friendly_name" => "Debug",
			"description" => "Turn on/off graph debugging.",
			"value" => "<a href='graphs.php?action=graph_edit&id=" . (isset($_GET["id"]) ? $_GET["id"] : 0) . "&debug=" . (isset($_SESSION["graph_debug_mode"]) ? "0" : "1") . "'>Turn <strong>" . (isset($_SESSION["graph_debug_mode"]) ? "Off" : "On") . "</strong> Graph Debug Mode.</a>"
			),
		"graph_template_graph_id" => array(
			"method" => "hidden",
			"value" => (isset($graphs) ? $graphs["id"] : "0")
			),
		"local_graph_id" => array(
			"method" => "hidden",
			"value" => (isset($graphs) ? $graphs["local_graph_id"] : "0")
			),
		"local_graph_template_graph_id" => array(
			"method" => "hidden",
			"value" => (isset($graphs) ? $graphs["local_graph_template_graph_id"] : "0")
			),
		"_graph_template_id" => array(
			"method" => "hidden",
			"value" => (isset($graphs) ? $graphs["graph_template_id"] : "0")
			),
		"_host_id" => array(
			"method" => "hidden",
			"value" => (isset($host_id) ? $host_id : "0")
			)
		);
	
	/* don't display the "debug field" for a new form */
	if (empty($_GET["id"])) {
		unset($form_array["debug"]);
	}
	
	draw_edit_form(
		array(
			"config" => array(),
			"fields" => $form_array
			)
		);
	
	end_box();
	
	if ((isset($_GET["id"])) || (isset($_GET["new"]))) {
		start_box("<strong>Graph Configuration</strong>", "98%", $colors["header"], "3", "center", "");
		
		$form_array = array();
		
		while (list($field_name, $field_array) = each($struct_graph)) {
			$form_array += array($field_name => $struct_graph[$field_name]);
			
			$form_array[$field_name]["value"] = (isset($graphs) ? $graphs[$field_name] : "");
			$form_array[$field_name]["form_id"] = (isset($graphs) ? $graphs["id"] : "0");
			
			if (!(($use_graph_template == false) || ($graphs_template{"t_" . $field_name} == "on"))) {
				$form_array[$field_name]["method"] = "template_" . $form_array[$field_name]["method"];
				$form_array[$field_name]["description"] = "";
			}
		}
		
		draw_edit_form(
			array(
				"config" => array(
					"no_form_tag" => true
					),
				"fields" => $form_array
				)
			);
		
		end_box();
	}
	
	if ((isset($_GET["id"])) || (isset($_GET["new"]))) {
		form_hidden_box("save_component_graph","1","");
	}else{
		form_hidden_box("save_component_graph_new","1","");
	}
	
	form_save_button("graphs.php");
}

function graph() {
	global $colors, $graph_actions;
	
	/* remember these search fields in session vars so we don't have to keep passing them around */
	if (isset($_REQUEST["page"])) {
		$_SESSION["sess_graph_current_page"] = $_REQUEST["page"];
	}elseif (isset($_SESSION["sess_graph_current_page"])) {
		$_REQUEST["page"] = $_SESSION["sess_graph_current_page"];
	}else{
		$_REQUEST["page"] = "1"; /* default value */
	}
	
	if (isset($_REQUEST["filter"])) {
		$_SESSION["sess_graph_filter"] = $_REQUEST["filter"];
	}elseif (isset($_SESSION["sess_graph_filter"])) {
		$_REQUEST["filter"] = $_SESSION["sess_graph_filter"];
	}else{
		$_REQUEST["filter"] = ""; /* default value */
	}
	
	if (isset($_REQUEST["host_id"])) {
		$_SESSION["sess_graph_host_id"] = $_REQUEST["host_id"];
	}elseif (isset($_SESSION["sess_graph_host_id"])) {
		$_REQUEST["host_id"] = $_SESSION["sess_graph_host_id"];
	}else{
		$_REQUEST["host_id"] = ""; /* default value */
	}
	
	start_box("<strong>Graph Management</strong>", "98%", $colors["header"], "3", "center", "graphs.php?action=graph_edit&host_id=" . $_REQUEST["host_id"]);
	
	?>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_graph_id">
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="100">
						Filter by host:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
							<option value="graphs.php?host_id=0&filter=<?php print $_REQUEST["filter"];?>"<?php if ($_REQUEST["host_id"] == "0") {?> selected<?php }?>>None</option>
							
							<?php
							$hosts = db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname");
							
							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='graphs.php?host_id=" . $host["id"] . "&filter=" . $_REQUEST["filter"] . "&page=1'"; if ($_REQUEST["host_id"] == $host["id"]) { print " selected"; } print ">" . $host["name"] . "</option>\n";
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
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	
	$total_rows = db_fetch_cell("select
		COUNT(graph_templates_graph.id)
		from graph_templates_graph
		left join graph_local on graph_templates_graph.local_graph_id=graph_local.id
		where graph_templates_graph.local_graph_id!=0
		and graph_templates_graph.title_cache like '%%" . $_REQUEST["filter"] . "%%'
		" . (empty($_REQUEST["host_id"]) ? "" : " and graph_local.host_id=" . $_REQUEST["host_id"]));
	
	$graph_list = db_fetch_assoc("select 
		graph_templates_graph.id,
		graph_templates_graph.local_graph_id,
		graph_templates_graph.height,
		graph_templates_graph.width,
		graph_templates_graph.title_cache,
		graph_templates.name,
		graph_local.host_id
		from graph_templates_graph left join graph_templates on graph_templates_graph.graph_template_id=graph_templates.id
		left join graph_local on graph_templates_graph.local_graph_id=graph_local.id
		where graph_templates_graph.local_graph_id!=0
		and graph_templates_graph.title_cache like '%%" . $_REQUEST["filter"] . "%%'
		" . (empty($_REQUEST["host_id"]) ? "" : " and graph_local.host_id=" . $_REQUEST["host_id"]) . "
		order by graph_templates_graph.title_cache,graph_local.host_id
		limit " . (read_config_option("num_rows_graph")*($_REQUEST["page"]-1)) . "," . read_config_option("num_rows_graph"));
	
	/* sometimes its a pain to browse throug a long list page by page... so make a list of each page #, so the
	user can jump straight to it */
	$page_number = 0; $url_page_select = "";
	for ($i=0; ($i<$total_rows); $i += read_config_option("num_rows_graph")) {
		$page_number++;
		
		if ($_REQUEST["page"] == $page_number) {
			$url_page_select .= "<strong><a class='linkOverDark' href='graphs.php?filter=" . $_REQUEST["filter"] . "&host_id=" . $_REQUEST["host_id"] . "&page=$page_number'>$page_number</a></strong>";
		}else{
			$url_page_select .= "<a class='linkOverDark' href='graphs.php?filter=" . $_REQUEST["filter"] . "&host_id=" . $_REQUEST["host_id"] . "&page=$page_number'>$page_number</a>";
		}
		
		if (($i+read_config_option("num_rows_graph")) < $total_rows) { $url_page_select .= ","; }
	}
	
	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='4'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='graphs.php?filter=" . $_REQUEST["filter"] . "&host_id=" . $_REQUEST["host_id"] . "&page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("num_rows_graph")*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_graph")) || ($total_rows < (read_config_option("num_rows_graph")*$_REQUEST["page"]))) ? $total_rows : (read_config_option("num_rows_graph")*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["page"] * read_config_option("num_rows_graph")) < $total_rows) { $nav .= "<a class='linkOverDark' href='graphs.php?filter=" . $_REQUEST["filter"] . "&host_id=" . $_REQUEST["host_id"] . "&page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * read_config_option("num_rows_graph")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";
	
	print "	$nav
		<tr bgcolor='#" . $colors["header_panel"] . "'>
			<td class='textSubHeaderDark'>Graph Title</td>
			<td class='textSubHeaderDark'>Template Name</td>
			<td class='textSubHeaderDark'>Size</td>
			<td width='1%' align='right' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAll(\"chk_\")'></td>
		<form name='chk' method='post' action='graphs.php'>
		</tr>";
	
	$i = 0;
	if (sizeof($graph_list) > 0) {
	foreach ($graph_list as $graph) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="graphs.php?action=graph_edit&id=<?php print $graph["local_graph_id"];?>"><?php print eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", title_trim($graph["title_cache"], read_config_option("max_title_graph")));?></a>
			</td>
			<td>
				<?php print ((empty($graph["name"])) ? "<em>None</em>" : $graph["name"]); ?>
			</td>
			<td>
				<?php print $graph["height"];?>x<?php print $graph["width"];?>
			</td>
			<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
				<input type='checkbox' style='margin: 0px;' name='chk_<?php print $graph["local_graph_id"];?>' title="<?php print $graph["title"];?>">
			</td>
		</tr>
	<?php
	$i++;
	}
		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Graphs Found</em></td></tr>";
	}
	
	end_box(false);
	
	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();
	
	?>
	<table align='center' width='98%'>
		<tr>
			<td width='1' valign='top'>
				<img src='images/arrow.gif' alt='' align='absmiddle'>&nbsp;
			</td>
			<td align='right'>
				<?php form_dropdown("drp_action",$graph_actions,"","","1","","");?>
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