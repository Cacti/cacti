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

/* creates a new form item with a title and description */
function form_item_label($form_title, $form_description) { 
	print "<td width='50%'>";
	if ($form_title != "") { print "<font class='textEditTitle'>$form_title</font><br>\n"; }
	print "<font class='textEditComment'>$form_description</font>\n</td>\n";
}

/* creates a standard html textbox */
function form_text_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30) {
	if ($form_previous_value == "") {
		$form_previous_value = htmlspecialchars($form_default_value);
	}
	
	print "<td>\n<input type='text'";
	
	if (isset($_SESSION["sess_error_fields"])) {
		$array_error_fields = unserialize($_SESSION["sess_error_fields"]);
		
		if (!empty($array_error_fields[$form_name])) {
			print "class='txtErrorTextBox'";
			unset($array_error_fields[$form_name]);
			$_SESSION["sess_error_fields"] = serialize($array_error_fields);
		}
	}
	
	if (isset($_SESSION["sess_field_values"])) {
		$array_field_values = unserialize($_SESSION["sess_field_values"]);
		
		if (!empty($array_field_values[$form_name])) {
			$form_previous_value = htmlspecialchars($array_field_values[$form_name]);
		}
	}
	
	print " name='$form_name' size='$form_size'" . (!empty($form_max_length) ? "maxlength='$form_max_length'" : "") . " value='$form_previous_value'>\n</td>\n";
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
		$form_previous_value = htmlspecialchars($form_default_value);
	}
	
	if ((isset($_SESSION["sess_field_values"])) && (isset($_SESSION["sess_error_fields"]))) {
		$array_field_values = unserialize($_SESSION["sess_field_values"]);
		$array_error_fields = unserialize($_SESSION["sess_error_fields"]);
		
		if (!empty($array_field_values[$form_name])) {
			$form_previous_value = htmlspecialchars($array_field_values[$form_name]);
		}
	}
	
	print "<td><select name='$form_name'>";
	
	if (!empty($form_none_entry)) {
		print "<option value='0'" . (empty($form_previous_value) ? " selected" : "") . ">$form_none_entry</option>\n";
	}
	
	create_list($form_data,$column_display,$column_id,$form_previous_value);
	
	print "</select>\n</td>\n";
}

/* creates a checkbox */
function form_checkbox($form_name, $form_previous_value, $form_caption, $form_default_value, $current_id = 0) { 
	if (($form_previous_value == "") && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}
	
	if ((isset($_SESSION["sess_field_values"])) && (isset($_SESSION["sess_error_fields"]))) {
		$array_field_values = unserialize($_SESSION["sess_field_values"]);
		$array_error_fields = unserialize($_SESSION["sess_error_fields"]);
		
		if (!empty($array_field_values[$form_name])) {
			$form_previous_value = $array_field_values[$form_name];
		}
	}
	
	print "<td><input type='checkbox' name='$form_name'" . (($form_previous_value == "on") ? " checked" : "") . "> $form_caption\n</td>\n";
}

/* creates a radio */
function form_radio_button($form_name, $form_previous_value, $form_current_value, $form_caption, $form_default_value) { 
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}
	
	if ((isset($_SESSION["sess_field_values"])) && (isset($_SESSION["sess_error_fields"]))) {
		$array_field_values = unserialize($_SESSION["sess_field_values"]);
		$array_error_fields = unserialize($_SESSION["sess_error_fields"]);
		
		if (!empty($array_field_values[$form_name])) {
			$form_previous_value = $array_field_values[$form_name];
		}
	}
	
	print "<td><input type='radio' name='$form_name' value='$form_current_value'" . (($form_previous_value == $form_current_value) ? " checked" : "") . "> $form_caption\n</td>\n";
}

/* creates a text area with a user defined rows and cols */
function form_text_area($form_name, $form_previous_value, $form_rows, $form_columns, $form_default_value) { 
	if ($form_previous_value == "") {
		$form_previous_value = htmlspecialchars($form_default_value);
	}
	
	if ((isset($_SESSION["sess_field_values"])) && (isset($_SESSION["sess_error_fields"]))) {
		$array_field_values = unserialize($_SESSION["sess_field_values"]);
		$array_error_fields = unserialize($_SESSION["sess_error_fields"]);
		
		if (!empty($array_field_values[$form_name])) {
			$form_previous_value = htmlspecialchars($array_field_values[$form_name]);
		}
	}
	
	print "<td><textarea cols='$form_columns' rows='$form_rows' name='$form_name'>$form_previous_value</textarea>\n</td>\n";
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
	
    		?>
		<td>
			<select name="<?php print $form_name;?>">
				<?php if ($form_none_entry != "") {?><option value="0"><?php print $form_none_entry;?></option><?php }?>
				<?php
				if (sizeof($colors_list) > 0) {
					foreach ($colors_list as $color) {
						print "<option style='background: #" . $color["hex"] . ";' value='" . $color["id"] . "'";
						
						if ($form_previous_value == $color["id"]) {
							print " selected";
						}
						
						print ">" . $color["hex"] . "</option>\n";
					}
				}
				?>
			</select>
		</td>
		<?php
}
    
    
/* create a multiselect listbox */
function form_multi_dropdown($form_name, $array_display, $sql_previous_values, $column_id) {
		?>
		<td>
			<select name="<?php print $form_name;?>[]" multiple>
				<?php
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
				?>
			</select>
		</td>
		<?php
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

function form_post_confirm_buttons($cancel_url) { ?>
	<tr>
		<td bgcolor="#E1E1E1">
			<a href="<?php print $cancel_url;?>"><img src="images/button_cancel.gif" border="0" alt="Cancel" align="absmiddle"></a>
			<input type='image' src="images/button_delete.gif" border="0" alt="Delete" align="absmiddle">
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

function form_save_button($cancel_url) {
	?>
	<table align='center' width='98%' style='background-color: #ffffff; border: 1px solid #bbbbbb;'>
		<tr>
			 <td bgcolor="#f5f5f5" align="right">
			 	<input type='hidden' name='action' value='save'>
				<a href='<?php print $cancel_url;?>'><img src='images/button_cancel2.gif' alt='Cancel' align='absmiddle' border='0'></a>
				<input type='image' src='images/button_<?php if (empty($_GET["id"])) { print "create"; }else{ print "save"; } ?>.gif' alt='Save' align='absmiddle'>
			</td>
		</tr>
	</table>
	</form>
	<?php
}

/* ------------------ Stripped Form Objects Data ---------------------- */

/* creates a standard html password textbox */
function form_base_text_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size, $type) {
	if ($form_previous_value == "") {
		$form_previous_value = htmlspecialchars($form_default_value);
	}
	
	print "<input type='$type'";
	
	if (isset($_SESSION["sess_error_fields"])) {
		$array_error_fields = unserialize($_SESSION["sess_error_fields"]);
		
		if (!empty($array_error_fields[$form_name])) {
			print "class='txtErrorTextBox'";
			unset($array_error_fields[$form_name]);
			$_SESSION["sess_error_fields"] = serialize($array_error_fields);
		}
	}
	
	if (isset($_SESSION["sess_field_values"])) {
		$array_field_values = unserialize($_SESSION["sess_field_values"]);
		
		if (!empty($array_field_values[$form_name])) {
			$form_previous_value = htmlspecialchars($array_field_values[$form_name]);
		}
	}
	
	print " name='$form_name' size='$form_size'" . (!empty($form_max_length) ? "maxlength='$form_max_length'" : "") . " value='$form_previous_value'>\n";
}

/* creates a dropdown box from a sql string */
function form_base_dropdown($form_name, $form_data, $column_display,$column_id, $form_previous_value, $form_none_entry, $form_default_value) { 
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}
	
	if ((isset($_SESSION["sess_field_values"])) && (isset($_SESSION["sess_error_fields"]))) {
		$array_field_values = unserialize($_SESSION["sess_field_values"]);
		$array_error_fields = unserialize($_SESSION["sess_error_fields"]);
		
		if (!empty($array_field_values[$form_name])) {
			$form_previous_value = $array_field_values[$form_name];
		}
	}
	
	print "<select name='$form_name'>";
	
	if (!empty($form_none_entry)) {
		print "<option value='0'" . (empty($form_previous_value) ? " selected" : "") . ">$form_none_entry</option>\n";
	}
	
	create_list($form_data,$column_display,$column_id,$form_previous_value);
	
	print "</select>\n";
}

/* creates the options for the select box */
function create_list($data, $name, $value, $prev) {
        if (empty($name)) {
                foreach (array_keys($data) as $id) {
                        print '<option value="' . $id . '"';
			
                        if ($prev == $id) {
                                print " selected";
                        }
			
			print ">" . title_trim(null_out_subsitions($data[$id]), 75) . "</option>\n";
                }
        }else{
                foreach ($data as $row) {
                        print "<option value='$row[$value]'";
			
                        if ($prev == $row[$value]) {
                                print " selected";
                        }
			
			if (isset($row["host_id"])) {
				print ">" . title_trim(expand_title($row["host_id"], "0", "", $row[$name]), 75) . "</option>\n";
			}else{
				print ">" . title_trim(null_out_subsitions($row[$name]), 75) . "</option>\n";
			}
                }
        }
}

/* creates a checkbox */
function form_base_checkbox($form_name, $form_previous_value, $form_caption, $form_default_value, $current_id = 0, $trailing_br) { 
	if (($form_previous_value == "") && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}
	
	if ((isset($_SESSION["sess_field_values"])) && (isset($_SESSION["sess_error_fields"]))) {
		$array_field_values = unserialize($_SESSION["sess_field_values"]);
		$array_error_fields = unserialize($_SESSION["sess_error_fields"]);
		
		if (!empty($array_field_values[$form_name])) {
			$form_previous_value = $array_field_values[$form_name];
		}
	}
	
	print "<input type='checkbox' class='chkNormal' name='$form_name'" . (($form_previous_value == "on") ? " checked" : "") . "> $form_caption" . ($trailing_br ? "<br>" : "")  ."\n";
}

/* creates a radio */
function form_base_radio_button($form_name, $form_previous_value, $form_current_value, $form_caption, $form_default_value, $trailing_br) { 
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}
	
	if ((isset($_SESSION["sess_field_values"])) && (isset($_SESSION["sess_error_fields"]))) {
		$array_field_values = unserialize($_SESSION["sess_field_values"]);
		$array_error_fields = unserialize($_SESSION["sess_error_fields"]);
		
		if (!empty($array_field_values[$form_name])) {
			$form_previous_value = $array_field_values[$form_name];
		}
	}
	
	print "<input type='radio' name='$form_name' value='$form_current_value'" . (($form_previous_value == $form_current_value) ? " checked" : "") . "> $form_caption" . ($trailing_br ? "<br>" : "") . "\n";
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

function draw_nontemplated_item($array_struct, $field_name, $previous_value) {
	global $config;
	
	include ($config["include_path"] . "/config_arrays.php");
	
	switch ($array_struct["type"]) {
	case 'text':
		form_text_box($field_name,$previous_value,$array_struct["default"],$array_struct["text_maxlen"], $array_struct["text_size"]);
		break;
	case 'drop_array':
		form_dropdown($field_name,${$array_struct["array_name"]},"","",$previous_value,"",$array_struct["default"]);
		break;
	case 'drop_sql':
		form_dropdown($field_name,db_fetch_assoc($array_struct["sql"]),"name","id",$previous_value,$array_struct["null_item"],$array_struct["default"]);
		break;
	case 'drop_color':
		form_color_dropdown($field_name,$previous_value,"None",$array_struct["default"]);
		break;
	case 'check':
		$check_id = 0;
		if (isset($_GET{$array_struct["check_id"]})) {
			$check_id = $_GET{$array_struct["check_id"]};
		}
		
		form_checkbox($field_name,$previous_value,$array_struct["check_caption"],$array_struct["default"],$check_id);
		break;
	case 'radio':
		print "<td>";
		
		while (list($radio_index, $radio_array) = each($array_struct["items"])) {
			form_base_radio_button($field_name, $previous_value, $radio_array["radio_value"], $radio_array["radio_caption"],$array_struct["default"],true);
		}
		
		print "</td>";
		break;
	case 'view':
		print "<td>$previous_value</td>\n";
		break;
	}
}

function draw_templated_item($array_struct, $field_name, $previous_value) {
	global $config;
	
	include ($config["include_path"] . "/config_arrays.php");
	
	switch ($array_struct["type"]) {
	case 'check':
		print "<td><em>" . html_boolean_friendly($previous_value) . "</em></td>";
		break;
	case 'drop_array':
		print "<td><em>" . ${$array_struct["array_name"]}[$previous_value] . "</em></td>";
		break;
	default:
		print "<td><em>" . $previous_value . "</em></td>";
		break;
	}
	
	form_hidden_box($field_name,$previous_value,"");
}

function draw_templated_row($array_struct, $field_name, $previous_value) {
	global $colors, $row_counter;
	
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$row_counter); $row_counter++;
	
	print "<td width='50%'><font class='textEditTitle'>" . $array_struct["title"] . "</font><br>\n";
	print $array_struct["description"];
	print "</td>\n";
	
	draw_nontemplated_item($array_struct, $field_name, $previous_value);
	
	print "</tr>\n";
}

function draw_tree_dropdown($current_tree_id) {
	global $colors;
	
	$html = "";
	
	if (read_config_option("global_auth") == "on") {
		$current_user = db_fetch_row("select * from user_auth where id=" . $_SESSION["sess_user_id"]);
		
		if ($current_user["graph_policy"] == "1") {
			$sql_where = "where user_auth_tree.user_id is null";
		}elseif ($current_user["graph_policy"] == "2") {
			$sql_where = "where user_auth_tree.user_id is not null";
		}
		
		$tree_list = db_fetch_assoc("select
			graph_tree.id,
			graph_tree.name,
			user_auth_tree.user_id
			from graph_tree
			left join user_auth_tree on (graph_tree.id=user_auth_tree.tree_id and user_auth_tree.user_id=" . $_SESSION["sess_user_id"] . ") 
			$sql_where
			order by graph_tree.name");
	}else{
		$tree_list = db_fetch_assoc("select * from graph_tree order by name");
	}
	
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

?>
