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
include_once ("include/form.php");
include_once ("include/config_arrays.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	case 'template_remove':
		template_remove();
		
		header ("Location: graph_templates.php");
		break;
	case 'input_remove':
		input_remove();
		
		if (read_config_option("full_view_graph_template") == "") {
			header ("Location: graph_templates.php?action=item&id=" . $_GET["graph_template_id"]);
			exit;
		}elseif (read_config_option("full_view_graph_template") == "on") {
			header ("Location: graph_templates.php?action=template_edit&id=" . $_GET["graph_template_id"]);
			exit;
		}
		
		break;
	case 'item_remove':
		item_remove();
		
		if (read_config_option("full_view_graph_template") == "") {
			header ("Location: graph_templates.php?action=item&id=" . $_GET["graph_template_id"]);
			exit;
		}elseif (read_config_option("full_view_graph_template") == "on") {
			header ("Location: graph_templates.php?action=template_edit&id=" . $_GET["graph_template_id"]);
			exit;
		}
		
		break;
	case 'input_edit':
		include_once ("include/top_header.php");
		
		input_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'item_movedown':
		item_movedown();
		
		header ("Location: " . $_SERVER["HTTP_REFERER"]);
		break;
	case 'item_moveup':
		item_moveup();
		
		header ("Location: " . $_SERVER["HTTP_REFERER"]);
		break;
	case 'item_edit':
		include_once ("include/top_header.php");
		
		item_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'item':
		include_once ("include/top_header.php");
		
		item();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'template_edit':
		include_once ("include/top_header.php");
		
		template_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		template();
		
		include_once ("include/bottom_footer.php");
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function draw_graph_form_select($main_action) { 
	global $colors; ?>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_graph_id">
		<td colspan="6">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="1%">
						<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
							<option value="graph_templates.php?action=template_edit&id=<?php print $_GET["id"];?>"<?php if (strstr($_GET["action"],"template")) {?> selected<?php }?>>Graph Template Configuration</option>
							<option value="graph_templates.php?action=item&id=<?php print $_GET["id"];?>"<?php if ((strstr($_GET["action"],"item")) || (strstr($_GET["action"],"input"))) {?> selected<?php }?>>Graph Item Template Configuration</option>
						</select>
					</td>
					<td>
						&nbsp;<a href="graph_templates.php<?php print $main_action;?>"><img src="images/button_go.gif" alt="Go" border="0" align="absmiddle"></a><br>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
<?php }

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	include_once ("include/utility_functions.php");
	
	if (isset($_POST["save_component_template"])) {
		$save1["id"] = $_POST["graph_template_id"];
		$save1["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		
		$save2["id"] = $_POST["graph_template_graph_id"];
		$save2["local_graph_template_graph_id"] = 0;
		$save2["local_graph_id"] = 0;
		$save2["t_image_format_id"] = (isset($_POST["t_image_format_id"]) ? $_POST["t_image_format_id"] : "");
		$save2["image_format_id"] = form_input_validate($_POST["image_format_id"], "image_format_id", "", true, 3);
		$save2["t_title"] = form_input_validate((isset($_POST["t_title"]) ? $_POST["t_title"] : ""), "t_title", "", true, 3);
		$save2["title"] = form_input_validate($_POST["title"], "title", "", false, 3);
		$save2["t_height"] = form_input_validate((isset($_POST["t_height"]) ? $_POST["t_height"] : ""), "t_height", "", true, 3);
		$save2["height"] = form_input_validate($_POST["height"], "height", "^[0-9]+$", false, 3);
		$save2["t_width"] = form_input_validate((isset($_POST["t_width"]) ? $_POST["t_width"] : ""), "t_width", "", true, 3);
		$save2["width"] = form_input_validate($_POST["width"], "width", "^[0-9]+$", false, 3);
		$save2["t_upper_limit"] = form_input_validate((isset($_POST["t_upper_limit"]) ? $_POST["t_upper_limit"] : ""), "t_upper_limit", "", true, 3);
		$save2["upper_limit"] = form_input_validate($_POST["upper_limit"], "upper_limit", "^[0-9]+$", false, 3);
		$save2["t_lower_limit"] = form_input_validate((isset($_POST["t_lower_limit"]) ? $_POST["t_lower_limit"] : ""), "t_lower_limit", "", true, 3);
		$save2["lower_limit"] = form_input_validate($_POST["lower_limit"], "lower_limit", "^[0-9]+$", false, 3);
		$save2["t_vertical_label"] = form_input_validate((isset($_POST["t_vertical_label"]) ? $_POST["t_vertical_label"] : ""), "t_vertical_label", "", true, 3);
		$save2["vertical_label"] = form_input_validate($_POST["vertical_label"], "vertical_label", "", true, 3);
		$save2["t_auto_scale"] = form_input_validate((isset($_POST["t_auto_scale"]) ? $_POST["t_auto_scale"] : ""), "t_auto_scale", "", true, 3);
		$save2["auto_scale"] = form_input_validate((isset($_POST["auto_scale"]) ? $_POST["auto_scale"] : ""), "auto_scale", "", true, 3);
		$save2["t_auto_scale_opts"] = form_input_validate((isset($_POST["t_auto_scale_opts"]) ? $_POST["t_auto_scale_opts"] : ""), "t_auto_scale_opts", "", true, 3);
		$save2["auto_scale_opts"] = form_input_validate($_POST["auto_scale_opts"], "auto_scale_opts", "", true, 3);
		$save2["t_auto_scale_log"] = form_input_validate((isset($_POST["t_auto_scale_log"]) ? $_POST["t_auto_scale_log"] : ""), "t_auto_scale_log", "", true, 3);
		$save2["auto_scale_log"] = form_input_validate((isset($_POST["auto_scale_log"]) ? $_POST["auto_scale_log"] : ""), "auto_scale_log", "", true, 3);
		$save2["t_auto_scale_rigid"] = form_input_validate((isset($_POST["t_auto_scale_rigid"]) ? $_POST["t_auto_scale_rigid"] : ""), "t_auto_scale_rigid", "", true, 3);
		$save2["auto_scale_rigid"] = form_input_validate((isset($_POST["auto_scale_rigid"]) ? $_POST["auto_scale_rigid"] : ""), "auto_scale_rigid", "", true, 3);
		$save2["t_auto_padding"] = form_input_validate((isset($_POST["t_auto_padding"]) ? $_POST["t_auto_padding"] : ""), "t_auto_padding", "", true, 3);
		$save2["auto_padding"] = form_input_validate((isset($_POST["auto_padding"]) ? $_POST["auto_padding"] : ""), "auto_padding", "", true, 3);
		$save2["t_base_value"] = form_input_validate((isset($_POST["t_base_value"]) ? $_POST["t_base_value"] : ""), "t_base_value", "", true, 3);
		$save2["base_value"] = form_input_validate($_POST["base_value"], "base_value", "^[0-9]+$", false, 3);
		$save2["t_export"] = form_input_validate((isset($_POST["t_export"]) ? $_POST["t_export"] : ""), "t_export", "", true, 3);
		$save2["export"] = form_input_validate((isset($_POST["export"]) ? $_POST["export"] : ""), "export", "", true, 3);
		$save2["t_unit_value"] = form_input_validate((isset($_POST["t_unit_value"]) ? $_POST["t_unit_value"] : ""), "t_unit_value", "", true, 3);
		$save2["unit_value"] = form_input_validate($_POST["unit_value"], "unit_value", "", true, 3);
		$save2["t_unit_exponent_value"] = form_input_validate((isset($_POST["t_unit_exponent_value"]) ? $_POST["t_unit_exponent_value"] : ""), "t_unit_exponent_value", "", true, 3);
		$save2["unit_exponent_value"] = form_input_validate($_POST["unit_exponent_value"], "unit_exponent_value", "^[0-9]+$", false, 3);
		
		if (!is_error_message()) {
			$graph_template_id = sql_save($save1, "graph_templates");
			
			if ($graph_template_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
		
		if (!is_error_message()) {
			$save2["graph_template_id"] = $graph_template_id;
			$graph_template_graph_id = sql_save($save2, "graph_templates_graph");
			
			if ($graph_template_graph_id) {
				raise_message(1);
				
				push_out_graph($graph_template_graph_id);
			}else{
				raise_message(2);
			}
		}
	}
	
	if (isset($_POST["save_component_item"])) {
		/* generate a new sequence if needed */
		if (empty($_POST["sequence"])) {
			$_POST["sequence"] = get_sequence($_POST["sequence"], "sequence", "graph_templates_item", "graph_template_id=" . $_POST["graph_template_id"] . " and local_graph_id=0");
		}
		
		$save["id"] = $_POST["graph_template_item_id"];
		$save["graph_template_id"] = $_POST["graph_template_id"];
		$save["local_graph_id"] = 0;
		$save["task_item_id"] = form_input_validate($_POST["task_item_id"], "task_item_id", "", true, 3);
		$save["color_id"] = form_input_validate($_POST["color_id"], "color_id", "", true, 3);
		$save["graph_type_id"] = form_input_validate($_POST["graph_type_id"], "graph_type_id", "", true, 3);
		$save["cdef_id"] = form_input_validate($_POST["cdef_id"], "cdef_id", "", true, 3);
		$save["consolidation_function_id"] = form_input_validate($_POST["consolidation_function_id"], "consolidation_function_id", "", true, 3);
		$save["text_format"] = form_input_validate($_POST["text_format"], "text_format", "", true, 3);
		$save["value"] = form_input_validate($_POST["value"], "value", "", true, 3);
		$save["hard_return"] = form_input_validate((isset($_POST["hard_return"]) ? $_POST["hard_return"] : ""), "hard_return", "", true, 3);
		$save["gprint_id"] = form_input_validate($_POST["gprint_id"], "gprint_id", "", true, 3);
		$save["sequence"] = $_POST["sequence"];
		
		if (!is_error_message()) {
			$graph_template_item_id = sql_save($save, "graph_templates_item");
			
			if ($graph_template_item_id) {
				raise_message(1);
				
				push_out_graph_item($graph_template_item_id);
			}else{
				raise_message(2);
			}
		}
		
		if (is_error_message()) {
			header ("Location: graph_templates.php?action=item_edit&graph_template_item_id=" . (empty($graph_template_item_id) ? $_POST["graph_template_item_id"] : $graph_template_item_id) . "&id=" . $_POST["graph_template_id"]);
			exit;
		}elseif (read_config_option("full_view_graph_template") == "") {
			header ("Location: graph_templates.php?action=item&id=" . $_POST["graph_template_id"]);
			exit;
		}elseif (read_config_option("full_view_graph_template") == "on") {
			header ("Location: graph_templates.php?action=template_edit&id=" . $_POST["graph_template_id"]);
			exit;
		}
	}
	
	if ((isset($_POST["save_component_input"])) && (!is_error_message())) {
		$save["id"] = $_POST["graph_template_input_id"];
		$save["graph_template_id"] = $_POST["graph_template_id"];
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save["description"] = form_input_validate($_POST["description"], "description", "", true, 3);
		$save["column_name"] = form_input_validate($_POST["column_name"], "column_name", "", true, 3);
		
		if (!is_error_message()) {
			$graph_template_input_id = sql_save($save, "graph_template_input");
			
			if ($graph_template_input_id) {
				raise_message(1);
				
				db_execute("delete from graph_template_input_defs where graph_template_input_id=$graph_template_input_id");
				
				while (list($var, $val) = each($_POST)) {
					if (eregi("^i_", $var)) {
						db_execute ("insert into graph_template_input_defs (graph_template_input_id,graph_template_item_id)
							values ($graph_template_input_id," . str_replace("i_", "", $var) . ")");
					}
				}
			}else{
				raise_message(2);
			}
		}
		
		if (is_error_message()) {
			header ("Location: graph_templates.php?action=input_edit&graph_template_input_id=" . (empty($graph_template_input_id) ? $_POST["graph_template_input_id"] : $graph_template_input_id) . "&id=" . $_POST["graph_template_id"]);
			exit;
		}elseif (read_config_option("full_view_graph_template") == "") {
			header ("Location: graph_templates.php?action=item&id=" . $_POST["graph_template_id"]);
			exit;
		}elseif (read_config_option("full_view_graph_template") == "on") {
			header ("Location: graph_templates.php?action=template_edit&id=" . $_POST["graph_template_id"]);
			exit;
		}
	}
	
	if ((is_error_message()) || (empty($_POST["graph_template_id"]))) {
		header ("Location: graph_templates.php?action=template_edit&id=" . (empty($graph_template_id) ? $_POST["graph_template_id"] : $graph_template_id));
	}else{
		header ("Location: graph_templates.php");
	}
}

/* -----------------------
    item - Graph Items 
   ----------------------- */

function item_movedown() {
	include_once ("include/functions.php");
	
	$arr = get_graph_group($_GET["id"]);
	$next_id = get_graph_parent($_GET["id"], "next");
	
	if ((!empty($next_id)) && (isset($arr{$_GET["id"]}))) {
		move_graph_group($_GET["id"], $arr, $next_id, "next");
	}elseif (db_fetch_cell("select graph_type_id from graph_templates_item where id=" . $_GET["id"]) == "9") {
		/* this is so we know the "other" graph item to propagate the changes to */
		$next_item = get_item("graph_templates_item", "sequence", $_GET["id"], "graph_template_id=" . $_GET["graph_template_id"] . " and local_graph_id=0", "next");
		
		move_item_down("graph_templates_item", $_GET["id"], "graph_template_id=" . $_GET["graph_template_id"] . " and local_graph_id=0");
		
		db_execute("update graph_templates_item set sequence=" . db_fetch_cell("select sequence from graph_templates_item where id=" . $_GET["id"]) . " where local_graph_template_item_id=" . $_GET["id"]);
		db_execute("update graph_templates_item set sequence=" . db_fetch_cell("select sequence from graph_templates_item where id=" . $next_item). " where local_graph_template_item_id=" . $next_item);
	}
}

function item_moveup() {
	include_once ("include/functions.php");
	
	$arr = get_graph_group($_GET["id"]);
	$next_id = get_graph_parent($_GET["id"], "previous");
	
	if ((!empty($next_id)) && (isset($arr{$_GET["id"]}))) {
		move_graph_group($_GET["id"], $arr, $next_id, "previous");
	}elseif (db_fetch_cell("select graph_type_id from graph_templates_item where id=" . $_GET["id"]) == "9") {
		/* this is so we know the "other" graph item to propagate the changes to */
		$last_item = get_item("graph_templates_item", "sequence", $_GET["id"], "graph_template_id=" . $_GET["graph_template_id"] . " and local_graph_id=0", "previous");
		
		move_item_up("graph_templates_item", $_GET["id"], "graph_template_id=" . $_GET["graph_template_id"] . " and local_graph_id=0");
		
		db_execute("update graph_templates_item set sequence=" . db_fetch_cell("select sequence from graph_templates_item where id=" . $_GET["id"]) . " where local_graph_template_item_id=" . $_GET["id"]);
		db_execute("update graph_templates_item set sequence=" . db_fetch_cell("select sequence from graph_templates_item where id=" . $last_item). " where local_graph_template_item_id=" . $last_item);
	}
}

function item() {
	global $colors, $consolidation_functions, $graph_item_types;
	
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
			CONCAT_WS(' - ',data_template_data.name,data_template_rrd.data_source_name) as data_source_name,
			cdef.name as cdef_name,
			colors.hex
			from graph_templates_item
			left join data_template_rrd on graph_templates_item.task_item_id=data_template_rrd.id
			left join data_local on data_template_rrd.local_data_id=data_local.id
			left join data_template_data on data_local.id=data_template_data.local_data_id
			left join cdef on cdef_id=cdef.id
			left join colors on color_id=colors.id
			where graph_templates_item.graph_template_id=" . $_GET["id"] . "
			and graph_templates_item.local_graph_id=0
			order by graph_templates_item.sequence");
		
		$header_label = "[edit: " . db_fetch_cell("select name from graph_templates where id=" . $_GET["id"]) . "]";
	}
	
	if (read_config_option("full_view_graph_template") == "") {
		start_box("<strong>Graph Template Management</strong> $header_label", "98%", $colors["header"], "3", "center", "");
		draw_graph_form_select("?action=item&id=" . $_GET["id"]);
		end_box();
	}
	
	start_box("<strong>Graph Template Items</strong> $header_label", "98%", $colors["header"], "3", "center", "graph_templates.php?action=item_edit&graph_template_id=" . $_GET["id"]);
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Graph Item",$colors["header_text"],1);
		DrawMatrixHeaderItem("Task Name",$colors["header_text"],1);
		DrawMatrixHeaderItem("Graph Item Type",$colors["header_text"],1);
		DrawMatrixHeaderItem("CF Type",$colors["header_text"],1);
		DrawMatrixHeaderItem("Item Color",$colors["header_text"],4);
	print "</tr>";
	
	$group_counter = 0; $_graph_type_name = ""; $i = 0;
	$alternate_color_1 = $colors["alternate"]; $alternate_color_2 = $colors["alternate"];
	
	if (sizeof($template_item_list) > 0) {
	foreach ($template_item_list as $item) {
		$this_row_style = ""; $use_custom_row_color = false; $hard_return = "";
		
		if ($graph_item_types{$item["graph_type_id"]} != "GPRINT") {
			$this_row_style = "font-weight: bold;"; $use_custom_row_color = true;
			
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
		
		$_graph_type_name = $graph_item_types{$item["graph_type_id"]};
		
		if ($use_custom_row_color == false) {
			form_alternate_row_color($alternate_color_1,$alternate_color_2,$i);
		}else{
			print "<tr bgcolor='#$custom_row_color'>";
		}
		
		print "<td><a class='linkEditMain' href='graph_templates.php?action=item_edit&id=" . $item["id"] . "&graph_template_id=" . $_GET["id"] . "'>Item # " . ($i+1) . "</a></td>\n";
		
		if (empty($item["data_source_name"])) { $item["data_source_name"] = "No Task"; }
		
		switch (true) {
		case ereg("(AREA|STACK|GPRINT|LINE[123])", $_graph_type_name):
			$matrix_title = "(" . $item["data_source_name"] . "): " . $item["text_format"];
			break;
		case ereg("(HRULE|VRULE)", $_graph_type_name):
			$matrix_title = "HRULE: " . $item["value"];
			break;
		case ereg("(COMMENT)", $_graph_type_name):
			$matrix_title = "COMMENT: " . $item["text_format"];
			break;
		}
		
		if ($item["hard_return"] == "on") {
			$hard_return = "<strong><font color='#FF0000'>&lt;HR&gt;</font></strong>";
		}
		
		print "<td style='$this_row_style'>" . htmlspecialchars($matrix_title) . $hard_return . "</td>\n";
		print "<td style='$this_row_style'>" . $graph_item_types{$item["graph_type_id"]} . "</td>\n";
		print "<td style='$this_row_style'>" . $consolidation_functions{$item["consolidation_function_id"]} . "</td>\n";
		print "<td" . ((!empty($item["hex"])) ? " bgcolor='#" . $item["hex"] . "'" : "") . " width='1%'>&nbsp;</td>\n";
		print "<td style='$this_row_style'>" . $item["hex"] . "</td>\n";
		print "<td><a href='graph_templates.php?action=item_movedown&id=" . $item["id"] . "&graph_template_id=" . $_GET["id"] . "'><img src='images/move_down.gif' border='0' alt='Move Down'></a>
		       	   <a href='graph_templates.php?action=item_moveup&id=" . $item["id"] . "&graph_template_id=" . $_GET["id"] . "'><img src='images/move_up.gif' border='0' alt='Move Up'></a></td>\n";
		print "<td width='1%' align='right'><a href='graph_templates.php?action=item_remove&id=" . $item["id"] . "&graph_template_id=" . $_GET["id"] . "'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a>&nbsp;</td>\n";
		
		print "</tr>";
		
		$i++;
	}
	}else{
		print "<tr bgcolor='#" . $colors["form_alternate2"] . "'><td colspan='7'><em>No Items</em></td></tr>";
	}
	
	end_box();
	
	start_box("<strong>Graph Item Inputs</strong>", "98%", $colors["header"], "3", "center", "graph_templates.php?action=input_edit&graph_template_id=" . $_GET["id"]);
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Name",$colors["header_text"],2);
	print "</tr>";
	
	$template_item_list = db_fetch_assoc("select id,name from graph_template_input where graph_template_id=" . $_GET["id"] . " order by name");
	
	if (sizeof($template_item_list) > 0) {
	foreach ($template_item_list as $item) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i);
	?>
			<td>
				<a class="linkEditMain" href="graph_templates.php?action=input_edit&id=<?php print $item["id"];?>&graph_template_id=<?php print $_GET["id"];?>"><?php print $item["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="graph_templates.php?action=input_remove&id=<?php print $item["id"];?>&graph_template_id=<?php print $_GET["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?php
	$i++;
	}
	}else{
		print "<tr bgcolor='#" . $colors["form_alternate2"] . "'><td colspan='2'><em>No Inputs</em></td></tr>";
	}
	
	end_box();
}

function item_remove() {
	db_execute("delete from graph_templates_item where id=" . $_GET["id"]);
}

function item_edit() {
	global $colors, $struct_graph_item, $graph_item_types, $consolidation_functions;
	
	$header_label = "[edit graph: " . db_fetch_cell("select name from graph_templates where id=" . $_GET["graph_template_id"]) . "]";
	
	if (read_config_option("full_view_graph_template") == "") {
		start_box("<strong>Graph Template Management</strong> $header_label", "98%", $colors["header"], "3", "center", "");
		draw_graph_form_select("?action=item&id=" . $_GET["id"]);
		end_box();
	}
	
	start_box("<strong>Graph Template Items</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	
	if (!empty($_GET["id"])) {
		$template_item = db_fetch_row("select * from graph_templates_item where id=" . $_GET["id"]);
	}
	
	print "<form method='post' action='graph_templates.php'>\n";
	
	/* by default, select the LAST DS chosen to make everyone's lives easier */
	if (!empty($_GET["graph_template_id"])) {
		$default = db_fetch_row("select task_item_id from graph_templates_item where graph_template_id=" . $_GET["graph_template_id"] . " and local_graph_id=0 order by sequence DESC");
	
		if (sizeof($default) > 0) {
			$struct_graph_item["task_item_id"]["default"] = $default["task_item_id"];
		}else{
			$struct_graph_item["task_item_id"]["default"] = 0;
		}
	}
	
	/* modifications to the default graph items array */
	$struct_graph_item["task_item_id"]["sql"] = "select
		CONCAT_WS('',data_template.name,' - ',data_template_data.name,' (',data_template_rrd.data_source_name,')') as name,
		data_template_rrd.id 
		from data_template_data,data_template_rrd,data_template 
		where data_template_rrd.data_template_id=data_template.id 
		and data_template_data.data_template_id=data_template.id
		and data_template_data.local_data_id=0
		and data_template_rrd.local_data_id=0
		order by data_template.name,data_template_data.name,data_template_rrd.data_source_name";
	
	$i = 0;
	while (list($field_name, $field_array) = each($struct_graph_item)) {
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
		
		print "<td width='50%'><font class='textEditTitle'>" . $field_array["title"] . "</font><br>\n";
		
		if (isset($field_array["description"])) {
			print $field_array["description"];
		}
		
		print "</td>\n";
		
		draw_nontemplated_item($field_array, $field_name, (isset($template_item) ? $template_item[$field_name] : ""));
		
		print "</tr>\n";
	}
	
	end_box();
	
	form_hidden_id("graph_template_item_id",(isset($template_item) ? $template_item["id"] : "0"));
	form_hidden_id("graph_template_id",$_GET["graph_template_id"]);
	form_hidden_id("sequence",(isset($template_item) ? $template_item["sequence"] : "0"));
	form_hidden_id("_graph_type_id",(isset($template_item) ? $template_item["graph_type_id"] : "0"));
	form_hidden_box("save_component_item","1","");
	
	form_save_button("graph_templates.php?action=template_edit&id=" . $_GET["graph_template_id"]);
}

/* ------------------------------------
    input - Graph Template Item Inputs 
   ------------------------------------ */

function input_remove() {
	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include ('include/top_header.php');
		form_confirm("Are You Sure?", "Are you sure you want to delete the input item <strong>'" . db_fetch_cell("select name from graph_template_input where id=" . $_GET["id"]) . "'</strong>? NOTE: Deleting this item will NOT affect graphs that use this template.", $_SERVER["HTTP_REFERER"], "graph_templates.php?action=input_remove&id=" . $_GET["id"] . "&graph_template_id=" . $_GET["graph_template_id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from graph_template_input where id=" . $_GET["id"]);
		db_execute("delete from graph_template_input_defs where graph_template_input_id=" . $_GET["id"]);
	}
}

function input_edit() {
	global $colors, $consolidation_functions, $graph_item_types, $struct_graph_item;
	
	$header_label = "[edit graph: " . db_fetch_cell("select name from graph_templates where id=" . $_GET["graph_template_id"]) . "]";
	
	if (read_config_option("full_view_graph_template") == "") {
		start_box("<strong>Graph Template Management</strong> $header_label", "98%", $colors["header"], "3", "center", "");
		draw_graph_form_select("?action=item&id=" . $_GET["graph_template_id"]);
		end_box();
	}
	
	/* get a list of all graph item field names and populate an array for user display */
	while (list($field_name, $field_array) = each($struct_graph_item)) {
		if ($field_array["type"] != "view") {
			$graph_template_items[$field_name] = $field_array["title"];
		}
	}
			
	if (!empty($_GET["id"])) {
		$graph_template_input = db_fetch_row("select * from graph_template_input where id=" . $_GET["id"]);
	}
	
	start_box("<strong>Graph Item Inputs</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	
	?>
	<form method="post" action="graph_templates.php">
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			Enter a name for this graph item input, make sure it is something you recognize.
		</td>
		<?php form_text_box("name",(isset($graph_template_input) ? $graph_template_input["name"] : ""),"","50", "40");?>
	</tr>
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Description</font><br>
			Enter a description for this graph item input to describe what this input is used for.
		</td>
		<?php form_text_area("description",(isset($graph_template_input) ? $graph_template_input["description"] : ""),5,40,"");?>
	</tr>
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Field Type</font><br>
			How data is to be represented on the graph.
		</td>
		<?php form_dropdown("column_name",$graph_template_items,"","",(isset($graph_template_input) ? $graph_template_input["column_name"] : ""),"","");?>
	</tr>
	
	<?php
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
		$i = 0;
		if (sizeof($item_list) > 0) {
		foreach ($item_list as $item) {
			if ($item["graph_template_input_id"] == "") {
				$old_value = "";
			}else{
				$old_value = "on";
			}
			
			if ($graph_item_types{$item["graph_type_id"]} == "GPRINT") {
				$start_bold = "";
				$end_bold = "";
			}else{
				$start_bold = "<strong>";
				$end_bold = "</strong>";
			}
			
			$name = "$start_bold Item #" . ($i+1) . ": " . $graph_item_types{$item["graph_type_id"]} . " (" . $consolidation_functions{$item["consolidation_function_id"]} . ")$end_bold";
			form_base_checkbox("i_" . $item["graph_templates_item_id"], $old_value, $name,"",$_GET["graph_template_id"],true);
			
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
	
	form_hidden_id("graph_template_id",$_GET["graph_template_id"]);
	form_hidden_id("graph_template_input_id",$_GET["id"]);
	form_hidden_box("save_component_input","1","");
	
	form_save_button("graph_templates.php?action=template_edit&id=" . $_GET["graph_template_id"]);
}

/* ----------------------------
    template - Graph Templates 
   ---------------------------- */

function template_remove() {
	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include ('include/top_header.php');
		form_confirm("Are You Sure?", "Are you sure you want to delete the graph template <strong>'" . db_fetch_cell("select name from graph_templates where id=" . $_GET["id"]) . "'</strong>? This is generally not a good idea if you have graphs attached to this template even though it should not affect any graphs.", $_SERVER["HTTP_REFERER"], "graph_templates.php?action=template_remove&id=" . $_GET["id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from graph_templates where id=" . $_GET["id"]);
		
		$graph_template_input = db_fetch_assoc("select id from graph_template_input where graph_template_id=" . $_GET["id"]);
		
		if (sizeof($graph_template_input) > 0) {
		foreach ($graph_template_input as $item) {
			db_execute("delete from graph_template_input_defs where graph_template_input_id=" . $item["id"]);
		}
		}
		
		db_execute("delete from graph_template_input where graph_template_id=" . $_GET["id"]);
		db_execute("delete from graph_templates_graph where graph_template_id=" . $_GET["id"] . " and local_graph_id=0");
		db_execute("delete from graph_templates_item where graph_template_id=" . $_GET["id"] . " and local_graph_id=0");
		
		/* "undo" any graph that is currently using this template */
		db_execute("update graph_templates_graph set local_graph_template_graph_id=0,graph_template_id=0 where graph_template_id=" . $_GET["id"]);
		db_execute("update graph_templates_item set local_graph_template_item_id=0,graph_template_id=0 where graph_template_id=" . $_GET["id"]);
	}	
}

function template_edit() {
	global $colors, $struct_graph, $image_types;
	
	if (read_config_option("full_view_graph_template") == "") {
		start_box("<strong>Graph Templates</strong>", "98%", $colors["header"], "3", "center", "");
		draw_graph_form_select("?action=template_edit&id=" . $_GET["id"]);
		end_box();
	}
	
	if (!empty($_GET["id"])) {
		$template = db_fetch_row("select * from graph_templates where id=" . $_GET["id"]);
		$template_graph = db_fetch_row("select * from graph_templates_graph where graph_template_id=" . $_GET["id"] . " and local_graph_id=0");
		
		$header_label = "[edit: " . $template["name"] . "]";
	}else{
		$header_label = "[new]";
	}
	
	if ((read_config_option("full_view_graph_template") == "on") && (!empty($_GET["id"]))) {
		item();	
	}
	
	start_box("<strong>Template</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	?>
	
	<form method="post" action="graph_templates.php">
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			The name given to this graph template.
		</td>
		<?php form_text_box("name",(isset($template) ? $template["name"] : ""),"","50", "40");?>
	</tr>
	
	<?php
	end_box();
	start_box("<strong>Graph Template</strong>", "98%", $colors["header"], "3", "center", "");
	
	$i = 0;
	while (list($field_name, $field_array) = each($struct_graph)) {
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
		
		print "<td width='50%'><font class='textEditTitle'>" . $field_array["title"] . "</font><br>\n";
		form_base_checkbox("t_" . $field_name,(isset($template_graph) ? $template_graph{"t_" . $field_name} : ""),"Use Per-Graph Value (Ignore this Value)","",(isset($template_graph) ? $template_graph["graph_template_id"] : "0"),false);
		print "</td>\n";
		
		draw_nontemplated_item($field_array, $field_name, (isset($template_graph) ? $template_graph[$field_name] : ""));
		
		print "</tr>\n";
	}
	
	end_box();
	
	form_hidden_id("graph_template_id",(isset($template_graph) ? $template_graph["graph_template_id"] : "0"));
	form_hidden_id("graph_template_graph_id",(isset($template_graph) ? $template_graph["id"] : "0"));
	form_hidden_box("save_component_template","1","");
	
	form_save_button("graph_templates.php");
}

function template() {
	global $colors;
	
	start_box("<strong>Graph Templates</strong>", "98%", $colors["header"], "3", "center", "graph_templates.php?action=template_edit");
	
	print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
			<td colspan='2' class='textSubHeaderDark'>Template Title</td>
		</tr>";
	
	$template_list = db_fetch_assoc("select 
		graph_templates.id,graph_templates.name 
		from graph_templates");
	
	$i = 0;
	if (sizeof($template_list) > 0) {
	foreach ($template_list as $template) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="graph_templates.php?action=template_edit&id=<?php print $template["id"];?>"><?php print $template["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="graph_templates.php?action=template_remove&id=<?php print $template["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
		<?php
		$i++;
	}
	}else{
		print "<tr><td><em>No Graph Templates</em></td></tr>\n";
	}
	end_box();
}

?>
