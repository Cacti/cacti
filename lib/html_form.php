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

/* draws a vertical space and a save button */
function form_save_button($form_action = "save", $cancel_action) { 
	print "	<input type='hidden' name='action' value='$form_action'>\n
		<a href='$cancel_action'><img src='images/button_cancel2.gif' alt='Cancel' align='absmiddle' border='0'></a>\n
		<input type='image' src='images/button_save.gif' alt='Save' align='absmiddle'>\n";
}

/* creates a new form item with a title and description */
function form_item_label($form_title, $form_description) { 
	include ("config.php");
	
	print "<td width='50%'>";
	if ($form_title != "") { print "<font class='textEditTitle'>$form_title</font><br>\n"; }
	print "<font class='textEditComment'>$form_description</font>\n</td>\n";
}

/* creates a standard html textbox */
function form_text_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30) {
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}
	
	$array_field_values = unserialize($_SESSION["sess_field_values"]);
	$array_error_fields = unserialize($_SESSION["sess_error_fields"]);
	
	if (!empty($array_field_values[$form_name])) {
		$form_previous_value = $array_field_values[$form_name];
	}
	
	print "<td>\n<input type='text'";
	
	if (!empty($array_error_fields[$form_name])) {
		print "class='txtErrorTextBox'";
		unset($array_error_fields[$form_name]);
		$_SESSION["sess_error_fields"] = serialize($array_error_fields);
	}
	
	print " name='$form_name' size='$form_size'" . (!empty($form_max_length) ? "maxlength='$form_max_length'" : "") . " value='$form_previous_value'>\n</td>\n";
}

/* creates a standard html password textbox */
function form_password_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size) { 
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}
	
	print "<td><input type='password' name='$form_name' size='$form_size'" . (!empty($form_max_length) ? "maxlength='$form_max_length'" : "") . " value='$form_previous_value'>\n</td>\n";
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
	include_once ("functions.php");
	
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
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
	
	print "<td><input type='checkbox' name='$form_name'" . (($form_previous_value == "on") ? " checked" : "") . "> $form_caption\n</td>\n";
}

/* creates a radio */
function form_radio_button($form_name, $form_previous_value, $form_current_value, $form_caption, $form_default_value) { 
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}
	
	print "<td><input type='radio' name='$form_name' value='$form_current_value'" . (($form_previous_value == $form_current_value) ? " checked" : "") . "> $form_caption\n</td>\n";
}

/* creates a text area with a user defined rows and cols */
function form_text_area($form_name, $form_previous_value, $form_rows, $form_columns, $form_default_value) { 
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}
	
	print "<td><textarea cols='form_columns' rows='$form_rows' name='$form_name'>$form_previous_value</textarea>\n</td>\n";
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
	include_once ("functions.php");
	
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
						print "<option style='background: #$color[hex];' value='$color[id]'";
						
						if ($form_previous_value == $color[id]) {
							print " selected";
						}
						
						print ">$color[hex]</option>\n";
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

function end_box() { ?>
				</table>
			</td>
		</tr>
	</table>
	<br>
<?php }

/* ------------------ Stripped Form Objects Data ---------------------- */

/* creates a standard html password textbox */
function form_base_password_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size) { 
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}
	
	print "<input type='password' name='$form_name' size='$form_size'" . (!empty($form_max_length) ? "maxlength='$form_max_length'" : "") . " value='$form_previous_value'>\n";
}

/* creates a dropdown box from a sql string */
function form_base_dropdown($form_name, $form_data, $column_display,$column_id, $form_previous_value, $form_none_entry, $form_default_value) { 
	include_once ("functions.php");
	
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}
	
	print "<select name='$form_name'>";
	
	if (!empty($form_none_entry)) {
		print "<option value='0'" . (empty($form_previous_value) ? " selected" : "") . ">$form_none_entry</option>\n";
	}
	
	create_list($form_data,$column_display,$column_id,$form_previous_value);
	
	print "</select>\n";
}

/* creates a checkbox */
function form_base_checkbox($form_name, $form_previous_value, $form_caption, $form_default_value, $current_id = 0, $trailing_br) { 
	if (($form_previous_value == "") && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}
	
	print "<input type='checkbox' name='$form_name'" . (($form_previous_value == "on") ? " checked" : "") . "> $form_caption" . ($trailing_br ? "<br>" : "")  ."\n";
}

/* creates a radio */
function form_base_radio_button($form_name, $form_previous_value, $form_current_value, $form_caption, $form_default_value, $trailing_br) { 
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
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

function draw_nontemplated_item($array_struct, $field_name, $previous_value) {
	include ("config_arrays.php");
	
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
		form_checkbox($field_name,$previous_value,$array_struct["check_caption"],$array_struct["default"]);
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
	include ("config_arrays.php");
	
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

?>
