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

function draw_edit_form($array) {
	global $colors;
	
	//print "<pre>";print_r($array);print "</pre>";
	
	if (sizeof($array) > 0) {
		while (list($top_branch, $top_children) = each($array)) {
			if ($top_branch == "config") {
				$config_array = $top_children;
			}elseif ($top_branch == "fields") {
				$fields_array = $top_children;
			}
		}
	}
	
	$i = 0;
	if (sizeof($fields_array) > 0) {
		while (list($field_name, $field_array) = each($fields_array)) {
			if ($i == 0) {
				if (!isset($config_array["no_form_tag"])) {
					print "<form method='post' action='" . ((isset($config_array["post_to"])) ? $config_array["post_to"] : basename($_SERVER["PHP_SELF"])) . "'" . ((isset($config_array["form_name"])) ? " name='" . $config_array["form_name"] . "'" : "") . ">\n";
				}
			}
			
			if ($field_array["method"] == "hidden") {
				form_hidden_box($field_name, $field_array["value"], ((isset($field_array["default"])) ? $field_array["default"] : ""));
			}elseif ($field_array["method"] == "hidden_zero") {
				form_hidden_box($field_name, $field_array["value"], "0");
			}else{
				if (isset($config_array["force_row_color"])) {
					print "<tr bgcolor='#" . $config_array["force_row_color"] . "'>";
				}else{
					form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
				}
				
				print "<td width='" . ((isset($config_array["left_column_width"])) ? $config_array["left_column_width"] : "50%") . "'>\n<font class='textEditTitle'>" . $field_array["friendly_name"] . "</font><br>\n";
				
				if (isset($field_array["sub_checkbox"])) {
					form_checkbox($field_array["sub_checkbox"]["name"], $field_array["sub_checkbox"]["value"], $field_array["sub_checkbox"]["friendly_name"], "", ((isset($field_array["form_id"])) ? $field_array["form_id"] : ""));
				}
				
				print ((isset($field_array["description"])) ? $field_array["description"] : "") . "</td>\n";
				
				print "<td>";
				
				switch ($field_array["method"]) {
				case 'textbox':
					form_text_box($field_name, $field_array["value"], ((isset($field_array["default"])) ? $field_array["default"] : ""), $field_array["max_length"], ((isset($field_array["size"])) ? $field_array["size"] : "40"), "text", ((isset($field_array["form_id"])) ? $field_array["form_id"] : ""));
					break;
				case 'textbox_password':
					form_text_box($field_name, $field_array["value"], ((isset($field_array["default"])) ? $field_array["default"] : ""), $field_array["max_length"], ((isset($field_array["size"])) ? $field_array["size"] : "40"), "password");
					print "<br>";
					form_text_box($field_name . "_confirm", $field_array["value"], ((isset($field_array["default"])) ? $field_array["default"] : ""), $field_array["max_length"], ((isset($field_array["size"])) ? $field_array["size"] : "40"), "password");
					break;
				case 'textarea':
					form_text_area($field_name, $field_array["value"], $field_array["textarea_rows"], $field_array["textarea_cols"], ((isset($field_array["default"])) ? $field_array["default"] : ""));
					break;
				case 'drop_array':
					form_dropdown($field_name, $field_array["array"], "", "", $field_array["value"], ((isset($field_array["none_value"])) ? $field_array["none_value"] : ""), ((isset($field_array["default"])) ? $field_array["default"] : ""));
					break;
				case 'drop_sql':
					form_dropdown($field_name, db_fetch_assoc($field_array["sql"]), "name", "id", $field_array["value"], ((isset($field_array["none_value"])) ? $field_array["none_value"] : ""), ((isset($field_array["default"])) ? $field_array["default"] : ""));
					break;
				case 'drop_multi':
					form_multi_dropdown($field_name, $field_array["array"], db_fetch_assoc($field_array["sql"]), "id");
					break;
				case 'drop_multi_rra':
					form_multi_dropdown($field_name, array_rekey(db_fetch_assoc("select id,name from rra order by name"), "id", "name"), (empty($field_array["form_id"]) ? db_fetch_assoc($field_array["sql_all"]) : db_fetch_assoc($field_array["sql"])), "id");
					break;
				case 'drop_tree':
					grow_dropdown_tree($field_array["tree_id"], $field_name, $field_array["value"]);
					break;
				case 'drop_color':
					form_color_dropdown($field_name, $field_array["value"], "None", ((isset($field_array["default"])) ? $field_array["default"] : ""));
					break;
				case 'checkbox':
					form_checkbox($field_name, $field_array["value"], $field_array["friendly_name"], ((isset($field_array["default"])) ? $field_array["default"] : ""), ((isset($field_array["form_id"])) ? $field_array["form_id"] : ""));
					break;
				case 'checkbox_group':
					while (list($check_name, $check_array) = each($field_array["items"])) {
						form_checkbox($check_name, $check_array["value"], $check_array["friendly_name"], ((isset($check_array["default"])) ? $check_array["default"] : ""), ((isset($field_array["form_id"])) ? $field_array["form_id"] : ""));
						print "<br>";
					}
					break;
				case 'radio':
					while (list($radio_index, $radio_array) = each($field_array["items"])) {
						form_radio_button($field_name, $field_array["value"], $radio_array["radio_value"], $radio_array["radio_caption"], ((isset($field_array["default"])) ? $field_array["default"] : ""));
						print "<br>";
					}
					break;
				case 'custom':
					print $field_array["value"];
					break;
				case 'template_checkbox':
					print "<em>" . html_boolean_friendly($field_array["value"]) . "</em>";
					form_hidden_box($field_name, $field_array["value"], "");
					break;
				case 'template_drop_array':
					print "<em>" . $field_array["array"]{$field_array["value"]} . "</em>";
					form_hidden_box($field_name, $field_array["value"], "");
					break;
				case 'template_drop_multi_rra':
					$items = db_fetch_assoc($field_array["sql_print"]);
					
					if (sizeof($items) > 0) {
					foreach ($items as $item) {
						print $item["name"] . "<br>";
					}
					}
					break;
				default:
					print "<em>" . $field_array["value"] . "</em>";
					form_hidden_box($field_name, $field_array["value"], "");
					break;
				}
				
				print "</td>\n</tr>\n";
			}
			
			if ($i == sizeof($fields_array)) {
				//print "</form>";
			}
			
			$i++;
		}
	}
}

/* creates a standard html textbox */
function form_text_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30, $type = "text", $current_id = 0) {
	if (($form_previous_value == "") && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}
	
	print "<input type='$type'";
	
	if (isset($_SESSION["sess_error_fields"])) {
		if (!empty($_SESSION["sess_error_fields"][$form_name])) {
			print "class='txtErrorTextBox'";
			unset($_SESSION["sess_error_fields"][$form_name]);
		}
	}
	
	if (isset($_SESSION["sess_field_values"])) {
		if (!empty($_SESSION["sess_field_values"][$form_name])) {
			$form_previous_value = $_SESSION["sess_field_values"][$form_name];
		}
	}
	
	print " name='$form_name' size='$form_size'" . (!empty($form_max_length) ? "maxlength='$form_max_length'" : "") . " value='" . htmlspecialchars($form_previous_value, ENT_QUOTES) . "'>\n";
}

/* creates a standard hidden html textbox */
function form_hidden_box($form_name, $form_previous_value, $form_default_value) { 
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}
	
	print "<input type='hidden' name='$form_name' value='$form_previous_value'>\n";
}

/* creates a dropdown box from a sql string */
function form_dropdown($form_name, $form_data, $column_display,$column_id, $form_previous_value, $form_none_entry, $form_default_value) { 
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}
	
	if (isset($_SESSION["sess_field_values"])) {
		if (!empty($_SESSION["sess_field_values"][$form_name])) {
			$form_previous_value = $_SESSION["sess_field_values"][$form_name];
		}
	}
	
	print "<select name='$form_name'>";
	
	if (!empty($form_none_entry)) {
		print "<option value='0'" . (empty($form_previous_value) ? " selected" : "") . ">$form_none_entry</option>\n";
	}
	
	create_list($form_data,$column_display,$column_id,htmlspecialchars($form_previous_value, ENT_QUOTES));
	
	print "</select>\n";
}

/* creates a checkbox */
function form_checkbox($form_name, $form_previous_value, $form_caption, $form_default_value, $current_id = 0) { 
	if (($form_previous_value == "") && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}
	
	if (isset($_SESSION["sess_field_values"])) {
		if (!empty($_SESSION["sess_field_values"][$form_name])) {
			$form_previous_value = $_SESSION["sess_field_values"][$form_name];
		}
	}
	
	print "<input type='checkbox' name='$form_name' id='$form_name'" . (($form_previous_value == "on") ? " checked" : "") . "> $form_caption\n";
}

/* creates a radio */
function form_radio_button($form_name, $form_previous_value, $form_current_value, $form_caption, $form_default_value) { 
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}
	
	if (isset($_SESSION["sess_field_values"])) {
		if (!empty($_SESSION["sess_field_values"][$form_name])) {
			$form_previous_value = $_SESSION["sess_field_values"][$form_name];
		}
	}
	
	print "<input type='radio' name='$form_name' value='$form_current_value'" . (($form_previous_value == $form_current_value) ? " checked" : "") . "> $form_caption\n";
}

/* creates a text area with a user defined rows and cols */
function form_text_area($form_name, $form_previous_value, $form_rows, $form_columns, $form_default_value) { 
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}
	
	if (isset($_SESSION["sess_field_values"])) {
		if (!empty($_SESSION["sess_field_values"][$form_name])) {
			$form_previous_value = $_SESSION["sess_field_values"][$form_name];
		}
	}
	
	print "<textarea cols='$form_columns' rows='$form_rows' name='$form_name'>" . htmlspecialchars($form_previous_value, ENT_QUOTES) . "</textarea>\n";
}

/* creates a hidden text box containing the ID */
function form_hidden_id($form_name, $form_id) { 
	if ($form_id == "") {
		$form_id = 0;
	}
	
	print "<input type='hidden' name='$form_name' value='$form_id'>\n";
}

/* creates a dropdown box from a sql string */
function form_color_dropdown($form_name, $form_previous_value, $form_none_entry, $form_default_value) { 
	if ($form_previous_value=="") {
		$form_previous_value = $form_default_value;
	}
	
	$colors_list = db_fetch_assoc("select id,hex from colors order by hex desc");
	
	print "<select name='$form_name'>\n";
	
	if ($form_none_entry != "") {
		print "<option value='0'>$form_none_entry</option>\n";
	}
	
	if (sizeof($colors_list) > 0) {
		foreach ($colors_list as $color) {
			print "<option style='background: #" . $color["hex"] . ";' value='" . $color["id"] . "'";
			
			if ($form_previous_value == $color["id"]) {
				print " selected";
			}
			
			print ">" . $color["hex"] . "</option>\n";
		}
	}
	
	print "</select>\n";
}
    
    
/* create a multiselect listbox */
function form_multi_dropdown($form_name, $array_display, $sql_previous_values, $column_id) {
	print "<select name='$form_name" . "[]' multiple>\n";
	
	foreach (array_keys($array_display) as $id) {
		print "<option value='" . $id . "'";
		
		for ($i=0; ($i < count($sql_previous_values)); $i++) {
			if ($sql_previous_values[$i][$column_id] == $id) {
				print " selected";
			}
		}
		
		print ">". $array_display[$id];
		print "</option>\n";
	}
	
	print "</select>\n";
}

function form_area($text) { ?>
	<tr>
		<td bgcolor="#E1E1E1" class="textArea">
			<?php print $text;?>
		</td>
	</tr>
<?php }

function form_confirm($title_text, $body_text, $cancel_url, $action_url) { ?>
		<br>
		<table align="center" cellpadding=1 cellspacing=0 border=0 bgcolor="#B61D22" width="60%">
			<tr>
				<td bgcolor="#B61D22" colspan="10">
					<table width="100%" cellpadding="3" cellspacing="0">
						<tr>
							<td bgcolor="#B61D22" class="textHeaderDark"><strong><?php print $title_text;?></strong></td>
						</tr>
						<?php
						form_area($body_text);
						form_confirm_buttons($action_url, $cancel_url);
						?>
					</table>
				</td>
			</tr>
		</table>

<?php }

function form_confirm_buttons($action_url, $cancel_url) { ?>
	<tr>
		<td bgcolor="#E1E1E1">
			<a href="<?php print $cancel_url;?>"><img src="images/button_cancel.gif" border="0" alt="Cancel" align="absmiddle"></a>
			<a href="<?php print $action_url . "&confirm=yes";?>"><img src="images/button_delete.gif" border="0" alt="Delete" align="absmiddle"></a>
		</td>
	</tr>
<?php }

function start_box($title, $width, $background_color, $cell_padding, $align, $add_text) {
	global $colors; ?>
	<table align="<?php print $align;?>" width="<?php print $width;?>" cellpadding=1 cellspacing=0 border=0 bgcolor="#<?php print $background_color;?>">
		<tr>
			<td>
				<table cellpadding=<?php print $cell_padding;?> cellspacing=0 border=0 bgcolor="#<?php print $colors["form_background_dark"];?>" width="100%">
					<?php if ($title != "") {?><tr>
						<td bgcolor="#<?php print $background_color;?>" colspan="10">
							<table width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td bgcolor="#<?php print $background_color;?>" class="textHeaderDark"><?php print $title;?></td>
										<?php if ($add_text != "") {?><td class="textHeaderDark" align="right" bgcolor="#<?php print $colors["header"];?>"><strong><a class="linkOverDark" href="<?php print $add_text;?>">Add</a>&nbsp;</strong></td><?php }?>
								</tr>
							</table>
						</td>
					</tr><?php }?>

<?php }

function end_box($trailing_br = true) { ?>
				</table>
			</td>
		</tr>
	</table>
	<?php if ($trailing_br == true) { print "<br>"; } ?>
<?php }

function form_save_button($cancel_url, $force_type = "") {
	if (empty($force_type)) {
		if (empty($_GET["id"])) {
			$img = "button_create.gif";
			$alt = "Create";
		}else{
			$img = "button_save.gif";
			$alt = "Save";
		}
	}elseif ($force_type == "save") {
		$img = "button_save.gif";
		$alt = "Save";
	}elseif ($force_type == "save") {
		$img = "button_create.gif";
		$alt = "Create";
	}
	?>
	<table align='center' width='98%' style='background-color: #ffffff; border: 1px solid #bbbbbb;'>
		<tr>
			 <td bgcolor="#f5f5f5" align="right">
			 	<input type='hidden' name='action' value='save'>
				<a href='<?php print $cancel_url;?>'><img src='images/button_cancel2.gif' alt='Cancel' align='absmiddle' border='0'></a>
				<input type='image' src='images/<?php print $img;?>' alt='<?php print $alt;?>' align='absmiddle'>
			</td>
		</tr>
	</table>
	</form>
	<?php
}

/* ------------------ Data Matrix ---------------------- */

function DrawMatrixHeaderItem($matrix_name, $matrix_text_color, $column_span = 1) { ?>
		<td height="1" colspan="<?php print $column_span;?>">
			<strong><font color="#<?php print $matrix_text_color;?>"><?php print $matrix_name;?></font></strong>
		</td>
<?php }

/* ------------------ Useful Functions ---------------------- */

function form_alternate_row_color($row_color1, $row_color2, $row_value) {
	if (($row_value % 2) == 1) {
		$current_color = $row_color1;
	}else{
		$current_color = $row_color2;
	}
	
	print "<tr bgcolor='#$current_color'>\n";
	
	return $current_color;
}

function html_boolean($html_boolean) {
	if ($html_boolean == "on") {
		return true;
	}else{
		return false;
	}
}

function html_boolean_friendly($html_boolean) {
	if ($html_boolean == "on") {
		return "Selected";
	}else{
		return "Not Selected";
	}
}

function get_checkbox_style() {
	if (get_web_browser() == "moz") {
		return "padding: 4px; margin: 4px;";
	}elseif (get_web_browser() == "ie") {
		return "padding: 0px; margin: 0px;";
	}elseif (get_web_browser() == "other") {
		return "padding: 4px; margin: 4px;";
	}
}

/* creates the options for the select box */
function create_list($data, $name, $value, $prev) {
        if (empty($name)) {
                foreach (array_keys($data) as $id) {
                        print '<option value="' . $id . '"';
			
                        if ($prev == $id) {
                                print " selected";
                        }
			
			print ">" . title_trim(null_out_substitutions($data[$id]), 75) . "</option>\n";
                }
        }else{
                foreach ($data as $row) {
                        print "<option value='$row[$value]'";
			
                        if ($prev == $row[$value]) {
                                print " selected";
                        }
			
			if (isset($row["host_id"])) {
				print ">" . title_trim($row[$name], 75) . "</option>\n";
			}else{
				print ">" . title_trim(null_out_substitutions($row[$name]), 75) . "</option>\n";
			}
                }
        }
}

function draw_tree_dropdown($current_tree_id) {
	global $colors;
	
	$html = "";
	
	$tree_list = get_graph_tree_array();
	
	if (isset($_GET["tree_id"])) {
		$_SESSION["sess_view_tree_id"] = $current_tree_id;
	}
	
	/* if there is a current tree, make sure it still exists before going on */
	if ((!empty($_SESSION["sess_view_tree_id"])) && (db_fetch_cell("select id from graph_tree where id=" . $_SESSION["sess_view_tree_id"]) == "")) {
		$_SESSION["sess_view_tree_id"] = 0;
	}
	
	/* set a default tree if none is already selected */
	if (empty($_SESSION["sess_view_tree_id"])) {
		if (db_fetch_cell("select id from graph_tree where id=" . read_graph_config_option("default_tree_id")) > 0) {
			$_SESSION["sess_view_tree_id"] = read_graph_config_option("default_tree_id");
		}else{
			if (sizeof($tree_list) > 0) {
				$_SESSION["sess_view_tree_id"] = $tree_list[0]["id"];
			}
		}
	}
	
	/* make the dropdown list of trees */
	if (sizeof($tree_list) > 1) {
		$html ="<form name='form_tree_id'>
			<td valign='middle' height='30' bgcolor='#" . $colors["panel"] . "'>\n
				<table width='100%' cellspacing='0' cellpadding='0'>\n
					<tr>\n
						<td width='200' class='textHeader'>\n
							&nbsp;&nbsp;Select a Graph Hierarchy:&nbsp;\n
						</td>\n
						<td bgcolor='#" . $colors["panel"] . "'>\n
							<select name='cbo_tree_id' onChange='window.location=document.form_tree_id.cbo_tree_id.options[document.form_tree_id.cbo_tree_id.selectedIndex].value'>\n";
		
		foreach ($tree_list as $tree) {
			$html .= "<option value='graph_view.php?action=tree&tree_id=" . $tree["id"] . "'";
				if ($_SESSION["sess_view_tree_id"] == $tree["id"]) { $html .= " selected"; }
				$html .= ">" . $tree["name"] . "</option>\n";
			}
		
		$html .= "</select>\n";
		$html .= "</td></tr></table></td></form>\n";	
	}elseif (sizeof($tree_list) == 1) {
		/* there is only one tree; use it */
		//print "	<td valign='middle' height='5' colspan='3' bgcolor='#" . $colors["panel"] . "'>";
	}
	
	return $html;
}

function draw_graph_items_list($item_list, $filename, $url_data, $disable_controls) {
	global $colors, $config;
	
	include($config["include_path"] . "/config_arrays.php");
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Graph Item",$colors["header_text"],1);
		DrawMatrixHeaderItem("Data Source",$colors["header_text"],1);
		DrawMatrixHeaderItem("Graph Item Type",$colors["header_text"],1);
		DrawMatrixHeaderItem("CF Type",$colors["header_text"],1);
		DrawMatrixHeaderItem("Item Color",$colors["header_text"],4);
	print "</tr>";
	
	$group_counter = 0; $_graph_type_name = ""; $i = 0;
	$alternate_color_1 = $colors["alternate"]; $alternate_color_2 = $colors["alternate"];
	
	if (sizeof($item_list) > 0) {
	foreach ($item_list as $item) {
		/* graph grouping display logic */
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
		
		/* alternating row color */
		if ($use_custom_row_color == false) {
			form_alternate_row_color($alternate_color_1,$alternate_color_2,$i);
		}else{
			print "<tr bgcolor='#$custom_row_color'>";
		}
		
		print "<td>";
		if ($disable_controls == false) { print "<a href='$filename?action=item_edit&id=" . $item["id"] . "&$url_data'>"; }
		print "<strong>Item # " . ($i+1) . "</strong>";
		if ($disable_controls == false) { print "</a>"; }
		print "</td>\n";
		
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
			$hard_return = "<strong><font color=\"#FF0000\">&lt;HR&gt;</font></strong>";
		}
		
		print "<td style='$this_row_style'>" . htmlspecialchars($matrix_title) . $hard_return . "</td>\n";
		print "<td style='$this_row_style'>" . $graph_item_types{$item["graph_type_id"]} . "</td>\n";
		print "<td style='$this_row_style'>" . $consolidation_functions{$item["consolidation_function_id"]} . "</td>\n";
		print "<td" . ((!empty($item["hex"])) ? " bgcolor='#" . $item["hex"] . "'" : "") . " width='1%'>&nbsp;</td>\n";
		print "<td style='$this_row_style'>" . $item["hex"] . "</td>\n";
		
		if ($disable_controls == false) {
			print "<td><a href='$filename?action=item_movedown&id=" . $item["id"] . "&$url_data'><img src='images/move_down.gif' border='0' alt='Move Down'></a>
					<a href='$filename?action=item_moveup&id=" . $item["id"] . "&$url_data'><img src='images/move_up.gif' border='0' alt='Move Up'></a></td>\n";
			print "<td width='1%' align='right'><a href='$filename?action=item_remove&id=" . $item["id"] . "&$url_data'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a>&nbsp;</td>\n";
		}
		
		print "</tr>";
		
		$i++;
	}
	}else{
		print "<tr bgcolor='#" . $colors["form_alternate2"] . "'><td colspan='7'><em>No Items</em></td></tr>";
	}
}

function draw_menu() {
	global $colors, $config;
	
	include($config["include_path"] . "/config_arrays.php");
	
	print "<tr><td width='100%'><table cellpadding='3' cellspacing='0' border='0' width='100%'>\n";
	
	/* loop through each header */
	while (list($header_name, $header_array) = each($menu)) {
		print "<tr><td class='textMenuHeader'>$header_name</td></tr>\n";
		
		/* loop through each top level item */
		while (list($item_url, $item_title) = each($header_array)) {
			/* if this item is an array, then it contains sub-items. if not, is just
			the title string and needs to be displayed */
			if (is_array($item_title)) {
				$i = 0;
				
				/* if the current page exists in the sub-items array, draw each sub-item */
				if (array_key_exists(basename($_SERVER["PHP_SELF"]), $item_title) == true) {
					$draw_sub_items = true;
				}else{
					$draw_sub_items = false;
				}
				
				while (list($item_sub_url, $item_sub_title) = each($item_title)) {
					/* indent sub-items */
					if ($i > 0) {
						$prepend_string = "---&nbsp;";
					}else{
						$prepend_string = "";
					}
					
					/* do not put a line between each sub-item */
					if (($i == 0) || ($draw_sub_items == false)) {
						$background = "images/menu_line.gif";
					}else{
						$background = "";
					}
					
					/* draw all of the sub-items as selected for ui grouping reasons. we can use the 'bold'
					or 'not bold' to distinguish which sub-item is actually selected */
					if ((basename($_SERVER["PHP_SELF"]) == basename($item_sub_url)) || ($draw_sub_items)) {
						$td_class = "textMenuItemSelected";
					}else{
						$td_class = "textMenuItem";
					}
					
					/* always draw the first item (parent), only draw the children if we are viewing a page
					that is contained in the sub-items array */
					if (($i == 0) || ($draw_sub_items)) {
						if (basename($_SERVER["PHP_SELF"]) == basename($item_sub_url)) {
							print "<tr><td class='$td_class' background='$background'>$prepend_string<strong><a href='$item_sub_url'>$item_sub_title</a></strong></td></tr>\n";
						}else{
							print "<tr><td class='$td_class' background='$background'>$prepend_string<a href='$item_sub_url'>$item_sub_title</a></td></tr>\n";
						}
					}
					
					$i++;
				}
			}else{
				/* draw normal (non sub-item) menu item */
				if (basename($_SERVER["PHP_SELF"]) == basename($item_url)) {
					print "<tr><td class='textMenuItemSelected' background='images/menu_line.gif'><strong><a href='$item_url'>$item_title</a></strong></td></tr>\n";
				}else{
					print "<tr><td class='textMenuItem' background='images/menu_line.gif'><a href='$item_url'>$item_title</a></td></tr>\n";
				}
			}
		}
	}
	
	print "<tr><td class='textMenuItem' background='images/menu_line.gif'></td></tr>\n";
	
	print '</table></td></tr>';
}

function inject_form_variables(&$form_array, $arg1 = array(), $arg2 = array(), $arg3 = array(), $arg4 = array()) {
	$check_fields = array("value", "array", "friendly_name", "description", "sql", "sql_print", "form_id", "items");
	
	/* loop through each available field */
	while (list($field_name, $field_array) = each($form_array)) {
		/* loop through each sub-field that we are going to check for variables */
		foreach ($check_fields as $field_to_check) {
			if (isset($field_array[$field_to_check]) && (is_array($form_array[$field_name][$field_to_check]))) {
				/* if the field/sub-field combination is an array, resolve it recursively */
				$form_array[$field_name][$field_to_check] = inject_form_variables($form_array[$field_name][$field_to_check], $arg1);
			}elseif (isset($field_array[$field_to_check]) && (!is_array($field_array[$field_to_check])) && (ereg("\|(arg[123]):([a-zA-Z0-9_]*)\|", $field_array[$field_to_check], $matches))) {
				/* an empty field name in the variable means don't treat this as an array */
				if ($matches[2] == "") {
					if (is_array(${$matches[1]})) {
						/* the existing value is already an array, leave it alone */
						$form_array[$field_name][$field_to_check] = ${$matches[1]};
					}else{
						/* the existing value is probably a single variable */
						$form_array[$field_name][$field_to_check] = str_replace($matches[0], ${$matches[1]}, $field_array[$field_to_check]);
					}
				}else{
					/* copy the value down from the array/key specified in the variable */
					$form_array[$field_name][$field_to_check] = str_replace($matches[0], ((isset(${$matches[1]}{$matches[2]})) ? ${$matches[1]}{$matches[2]} : ""), $field_array[$field_to_check]);
				}
			}
		}
	}
	
	return $form_array;
}

?>
