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

/*
 * Standard HTML form elements
 */

/* draw_edit_form - draws an html edit form
   @arg $array - an array that contains all of the information needed to draw
     the html form. see the arrays contained in include/config_settings.php
     for the extact syntax of this array */
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
			}elseif ($field_array["method"] == "spacer") {
				print "<tr bgcolor='" . $colors["header_panel"] . "'><td colspan='2' class='textSubHeaderDark'>" . $field_array["friendly_name"] . "</td></tr>\n";
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
						form_checkbox($check_name, $check_array["value"], $check_array["friendly_name"], ((isset($check_array["default"])) ? $check_array["default"] : ""), ((isset($check_array["form_id"])) ? $check_array["form_id"] : ""));
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

				$i++;
			}

			if ($i == sizeof($fields_array)) {
				//print "</form>";
			}
		}
	}
}

/* form_text_box - draws a standard html textbox
   @arg $form_name - the name of this form element
   @arg $form_previous_value - the current value of this form element
   @arg $form_default_value - the value of this form element to use if there is
     no current value available
   @arg $form_max_length - the maximum number of characters that can be entered
     into this textbox
   @arg $form_size - the size (width) of the textbox
   @arg $type - the type of textbox, either 'text' or 'password'
   @arg $current_id - used to determine if a current value for this form element
     exists or not. a $current_id of '0' indicates that no current value exists,
     a non-zero value indicates that a current value does exist */
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

	print " name='$form_name' size='$form_size'" . (!empty($form_max_length) ? " maxlength='$form_max_length'" : "") . " value='" . htmlspecialchars($form_previous_value, ENT_QUOTES) . "'>\n";
}

/* form_hidden_box - draws a standard html hidden element
   @arg $form_name - the name of this form element
   @arg $form_previous_value - the current value of this form element
   @arg $form_default_value - the value of this form element to use if there is
     no current value available */
function form_hidden_box($form_name, $form_previous_value, $form_default_value) {
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}

	print "<input type='hidden' name='$form_name' value='$form_previous_value'>\n";
}

/* form_dropdown - draws a standard html dropdown box
   @arg $form_name - the name of this form element
   @arg $form_data - an array containing data for this dropdown. it can be formatted
     in one of two ways:
     $array["id"] = "value";
     -- or --
     $array[0]["id"] = 43;
     $array[0]["name"] = "Red";
   @arg $column_display - used to indentify the key to be used for display data. this
     is only applicable if the array is formatted using the second method above
   @arg $column_id - used to indentify the key to be used for id data. this
     is only applicable if the array is formatted using the second method above
   @arg $form_previous_value - the current value of this form element
   @arg $form_none_entry - the name to use for a default 'none' element in the dropdown
   @arg $form_default_value - the value of this form element to use if there is
     no current value available
   @arg $css_style - any css that needs to be applied to this form element */
function form_dropdown($form_name, $form_data, $column_display, $column_id, $form_previous_value, $form_none_entry, $form_default_value, $css_style = "") {
	if ($form_previous_value == "") {
		$form_previous_value = $form_default_value;
	}

	if (isset($_SESSION["sess_field_values"])) {
		if (!empty($_SESSION["sess_field_values"][$form_name])) {
			$form_previous_value = $_SESSION["sess_field_values"][$form_name];
		}
	}

	print "<select name='$form_name' style='$css_style'>";

	if (!empty($form_none_entry)) {
		print "<option value='0'" . (empty($form_previous_value) ? " selected" : "") . ">$form_none_entry</option>\n";
	}

	html_create_list($form_data, $column_display, $column_id, htmlspecialchars($form_previous_value, ENT_QUOTES));

	print "</select>\n";
}

/* form_text_box - draws a standard html checkbox
   @arg $form_name - the name of this form element
   @arg $form_previous_value - the current value of this form element
   @arg $form_caption - the text to display to the right of the checkbox
   @arg $form_default_value - the value of this form element to use if there is
     no current value available
   @arg $current_id - used to determine if a current value for this form element
     exists or not. a $current_id of '0' indicates that no current value exists,
     a non-zero value indicates that a current value does exist */
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

/* form_text_box - draws a standard html radio button
   @arg $form_name - the name of this form element
   @arg $form_previous_value - the current value of this form element (selected or not)
   @arg $form_current_value - the current value of this form element (element id)
   @arg $form_caption - the text to display to the right of the checkbox
   @arg $form_default_value - the value of this form element to use if there is
     no current value available */
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

/* form_text_box - draws a standard html text area box
   @arg $form_name - the name of this form element
   @arg $form_previous_value - the current value of this form element (selected or not)
   @arg $form_rows - the number of rows in the text area box
   @arg $form_columns - the number of columns in the text area box
   @arg $form_default_value - the value of this form element to use if there is
     no current value available */
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

/* form_multi_dropdown - draws a standard html multiple select dropdown
   @arg $form_name - the name of this form element
   @arg $array_display - an array containing display values for this dropdown. it must
     be formatted like:
     $array[id] = display;
   @arg $sql_previous_values - an array containing keys that should be marked as selected.
     it must be formatted like:
     $array[0][$column_id] = key
   @arg $column_id - the name of the key used to reference the keys above */
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

/*
 * Second level form elements
 */

/* form_color_dropdown - draws a dropdown containing a list of colors that uses a bit
     of css magic to make the dropdown item background color represent each color in
     the list
   @arg $form_name - the name of this form element
   @arg $form_previous_value - the current value of this form element
   @arg $form_none_entry - the name to use for a default 'none' element in the dropdown
   @arg $form_default_value - the value of this form element to use if there is
     no current value available */
function form_color_dropdown($form_name, $form_previous_value, $form_none_entry, $form_default_value) {
	if ($form_previous_value == "") {
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

/* form_confirm - draws a table presenting the user with some choice and allowing
     them to either proceed (delete) or cancel
   @arg $body_text - the text to prompt the user with on this form
   @arg $cancel_url - the url to go to when the user clicks 'cancel'
   @arg $action_url - the url to go to when the user clicks 'delete' */
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

/* form_confirm_buttons - draws a cancel and delete button suitable for display
     on a confirmation form
   @arg $cancel_url - the url to go to when the user clicks 'cancel'
   @arg $action_url - the url to go to when the user clicks 'delete' */
function form_confirm_buttons($action_url, $cancel_url) { ?>
	<tr>
		<td bgcolor="#E1E1E1">
			<a href="<?php print $cancel_url;?>"><img src="images/button_cancel.gif" border="0" alt="Cancel" align="absmiddle"></a>
			<a href="<?php print $action_url . "&confirm=yes";?>"><img src="images/button_delete.gif" border="0" alt="Delete" align="absmiddle"></a>
		</td>
	</tr>
<?php }

/* form_save_button - draws a (save|create) and cancel button at the bottom of
     an html edit form
   @arg $cancel_url - the url to go to when the user clicks 'cancel'
   @arg $force_type - if specified, will force the 'action' button to be either
     'save' or 'create'. otherwise this field should be properly auto-detected */
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
	}elseif ($force_type == "create") {
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

?>
