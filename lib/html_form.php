<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

/*
 * Standard HTML form elements
 */

/* draw_edit_form - draws an html edit form
   @arg $array - an array that contains all of the information needed to draw
     the html form. see the arrays contained in include/global_settings.php
     for the extact syntax of this array */
function draw_edit_form($array) {
	if (sizeof($array) > 0) {
		while (list($top_branch, $top_children) = each($array)) {
			if ($top_branch == 'config') {
				$config_array = $top_children;
			}elseif ($top_branch == 'fields') {
				$fields_array = $top_children;
			}
		}
	}

	$i = 0;
	if (sizeof($fields_array) > 0) {
		while (list($field_name, $field_array) = each($fields_array)) {
			if ($i == 0) {
				if (!isset($config_array['no_form_tag'])) {
					print "<tr style='display:none;'><td><form method='post' autocomplete='off' action='" . ((isset($config_array['post_to'])) ? $config_array['post_to'] : basename($_SERVER['PHP_SELF'])) . "'" . ((isset($config_array['form_name'])) ? " name='" . $config_array['form_name'] . "'" : '') . ((isset($config_array['enctype'])) ? " enctype='" . $config_array['enctype'] . "'" : '') . "></td></tr>\n";
				}
			}

			if ($field_array['method'] == 'hidden') {
				form_hidden_box($field_name, $field_array['value'], ((isset($field_array['default'])) ? $field_array['default'] : ''), true);
			}elseif ($field_array['method'] == 'hidden_zero') {
				form_hidden_box($field_name, $field_array['value'], '0', true);
			}elseif ($field_array['method'] == 'spacer') {
				if (isset($field_array['collapsible']) && $field_array['collapsible'] == 'true') {
					$collapsible = true;
				}else{
					$collapsible = false;
				}

				print "<tr class='spacer tableHeader" . ($collapsible ? ' collapsible':'') . "' id='row_$field_name'><td colspan='2' style='cursor:pointer;' class='tableSubHeaderColumn'>" . $field_array['friendly_name'] . ($collapsible ? "<div style='float:right;padding-right:4px;'><i class='fa fa-angle-double-up'></i></div>":'') . "</td></tr>\n";
			}else{
				if (isset($config_array['force_row_color'])) {
					print "<tr class='even-alternate'>";
				}else{
					form_alternate_row('row_' . $field_name);
				}

				print "<td style='width:" . ((isset($config_array['left_column_width'])) ? $config_array['left_column_width'] . 'px;':'50%;') . "'>\n<span class='formItemName'>" . $field_array['friendly_name'] . "</span>\n";

				if (read_config_option('hide_form_description') == 'on') {
					print '<br><span class="formItemDescription">' . ((isset($field_array['description'])) ? $field_array['description'] : '') . "<br></span>\n";
				}else{
					print display_tooltip((isset($field_array['description'])) ? $field_array['description'] : '');
				}

				if (isset($field_array['sub_checkbox'])) {
					print "<br>\n";
					form_checkbox($field_array['sub_checkbox']['name'],
						$field_array['sub_checkbox']['value'],
						$field_array['sub_checkbox']['friendly_name'],
						((isset($field_array['sub_checkbox']['default'])) 	? $field_array['sub_checkbox']['default'] : ''),
						((isset($field_array['sub_checkbox']['form_id'])) 	? $field_array['sub_checkbox']['form_id'] : ''),
						((isset($field_array['sub_checkbox']['class'])) 	? $field_array['sub_checkbox']['class'] : ''),
						((isset($field_array['sub_checkbox']['on_change'])) ? $field_array['sub_checkbox']['on_change'] : ''));
				}

				print "</td>\n<td>\n";

				draw_edit_control($field_name, $field_array);

				print "</td>\n</tr>\n";
			}

			$i++;
		}
	}
}

/* draw_edit_control - draws a single control to be used on an html edit form
   @arg $field_name - the name of the control
   @arg $field_array - an array containing data for this control. see include/global_form.php
     for more specific syntax */
function draw_edit_control($field_name, &$field_array) {
	switch ($field_array['method']) {
	case 'textbox':
		form_text_box(
			$field_name, 
			$field_array['value'],
			((isset($field_array['default'])) ? $field_array['default'] : ''),
			$field_array['max_length'],
			((isset($field_array['size'])) ? $field_array['size'] : '40'), 
			'text',
			((isset($field_array['form_id'])) ? $field_array['form_id'] : ''),
			((isset($field_array['placeholder'])) ? $field_array['placeholder'] : '')
		);

		break;
	case 'filepath':
		form_filepath_box($field_name, 
			$field_array['value'],
			((isset($field_array['default'])) ? $field_array['default'] : ''),
			$field_array['max_length'],
			((isset($field_array['size'])) ? $field_array['size'] : '40'), 
			'text',
			((isset($field_array['form_id'])) ? $field_array['form_id'] : '')
		);

		break;
	case 'dirpath':
		form_dirpath_box(
			$field_name, 
			$field_array['value'],
			((isset($field_array['default'])) ? $field_array['default'] : ''),
			$field_array['max_length'],
			((isset($field_array['size'])) ? $field_array['size'] : '40'), 
			'text',
			((isset($field_array['form_id'])) ? $field_array['form_id'] : '')
		);

		break;
	case 'textbox_password':
		// Fake out firefox so that you don't get pre-set passwords
		print "<input type='text' name='mylogin' style='display:none;' value='' autocomplete='off' disabled='disabled'>\n";
		print "<input type='password' name='mypassword' style='display:none;' value='' autocomplete='off' disabled='disabled'>\n";

		form_text_box(
			$field_name, 
			$field_array['value'],
			((isset($field_array['default'])) ? $field_array['default'] : ''),
			$field_array['max_length'],
			((isset($field_array['size'])) ? $field_array['size'] : '40'), 
			'password',
			((isset($field_array['form_id'])) ? $field_array['form_id'] : ''),
			'********'
		);

		print '<br>';

		form_text_box(
			$field_name . '_confirm', 
			$field_array['value'],
			((isset($field_array['default'])) ? $field_array['default'] : ''),
			$field_array['max_length'],
			((isset($field_array['size'])) ? $field_array['size'] : '40'), 
			'password',
			((isset($field_array['form_id'])) ? $field_array['form_id'] : ''),
			'********'
		);

		break;
	case 'textarea':
		form_text_area(
			$field_name, 
			$field_array['value'], 
			$field_array['textarea_rows'],
			$field_array['textarea_cols'],
			((isset($field_array['default'])) ? $field_array['default'] : ''),
			((isset($field_array['class'])) ? $field_array['class'] : ''),
			((isset($field_array['on_change'])) ? $field_array['on_change'] : ''),
			((isset($field_array['placeholder'])) ? $field_array['placeholder'] : '')
		);

		break;
	case 'drop_array':
		form_dropdown(
			$field_name, 
			$field_array['array'], 
			'', 
			'', 
			$field_array['value'],
			((isset($field_array['none_value'])) ? $field_array['none_value'] : ''),
			((isset($field_array['default'])) ? $field_array['default'] : ''),
			((isset($field_array['class'])) ? $field_array['class'] : ''),
			((isset($field_array['on_change'])) ? $field_array['on_change'] : '')
		);

		break;
	case 'drop_files':
		if (isset($field_array['directory'])) {
			$dir = $field_array['directory'];

			if (is_dir($dir) && is_readable($dir)) {
				if (function_exists('scandir')) {
					$files = scandir($dir);
				} elseif ($dh = opendir($dir)) {
					while (($file = readdir($dh)) !== false) {
						$files[] = $file;
					}
					closedir($dh);
				}

				if (sizeof($files)) {
				foreach($files as $file) {
					if (is_readable($dir . '/' . $file) && $file != '.' && $file != '..') {
						if (!in_array($file, $field_array['exclusions'])) {
							$array_files[basename($file)] = basename($file);
						}
					}
				}
				}
			}
		}

		form_dropdown(
			$field_name, 
			$array_files,
			'', 
			'', 
			$field_array['value'],
			((isset($field_array['none_value'])) ? $field_array['none_value'] : ''),
			((isset($field_array['default'])) ? $field_array['default'] : ''),
			((isset($field_array['class'])) ? $field_array['class'] : ''),
			((isset($field_array['on_change'])) ? $field_array['on_change'] : '')
		);

		break;
	case 'drop_sql':
		form_dropdown(
			$field_name,
			db_fetch_assoc($field_array['sql']), 
			'name', 
			'id', 
			$field_array['value'],
			((isset($field_array['none_value'])) ? $field_array['none_value'] : ''),
			((isset($field_array['default'])) ? $field_array['default'] : ''),
			((isset($field_array['class'])) ? $field_array['class'] : ''),
			((isset($field_array['on_change'])) ? $field_array['on_change'] : '')
		);

		break;
	case 'drop_callback':
		form_callback(
			$field_name,
			$field_array['sql'],
			'name', 
			'id', 
			$field_array['action'],
			$field_array['id'],
			$field_array['value'],
			((isset($field_array['default'])) ? $field_array['default'] : ''),
			((isset($field_array['class'])) ? $field_array['class'] : ''),
			((isset($field_array['on_change'])) ? $field_array['on_change'] : '')
		);

		break;
	case 'drop_multi':
		form_multi_dropdown(
			$field_name, 
			$field_array['array'], 
			(isset($field_array['sql']) ? db_fetch_assoc($field_array['sql']):$field_array['value']), 
			'id',
			((isset($field_array['class'])) ? $field_array['class'] : ''),
			((isset($field_array['on_change'])) ? $field_array['on_change'] : '')
		);

		break;
	case 'drop_tree':
		grow_dropdown_tree(
			$field_array['tree_id'], 
			'0',
			$field_name, 
			$field_array['value']
		);

		break;
	case 'drop_color':
		form_color_dropdown(
			$field_name, 
			$field_array['value'], 
			'None',
			((isset($field_array['default'])) ? $field_array['default'] : ''),
			((isset($field_array['class'])) ? $field_array['class'] : ''),
			((isset($field_array['on_change'])) ? $field_array['on_change'] : '')
		);

		break;
	case 'checkbox':
		form_checkbox(
			$field_name,
			$field_array['value'],
			$field_array['friendly_name'],
			((isset($field_array['default'])) ? $field_array['default'] : ''),
			((isset($field_array['form_id'])) ? $field_array['form_id'] : ''),
			((isset($field_array['class'])) ? $field_array['class'] : ''),
			((isset($field_array['on_change'])) ? $field_array['on_change'] : '')
		);

		break;
	case 'checkbox_group':
		print "<div id='${field_name}_group' class='checkboxgroup'>\n";
		while (list($check_name, $check_array) = each($field_array['items'])) {
			form_checkbox(
				$check_name, 
				$check_array['value'], 
				$check_array['friendly_name'],
				((isset($check_array['default'])) ? $check_array['default'] : ''),
				((isset($check_array['form_id'])) ? $check_array['form_id'] : ''),
				((isset($field_array['class'])) ? $field_array['class'] : ''),
				((isset($check_array['on_change'])) ? $check_array['on_change'] : (((isset($field_array['on_change'])) ? $field_array['on_change'] : '')))
			);

			print '<br>';
		}
		print "</div>\n";

		break;
	case 'radio':
		while (list($radio_index, $radio_array) = each($field_array['items'])) {
			form_radio_button(
				$field_name, 
				$field_array['value'], 
				$radio_array['radio_value'], 
				$radio_array['radio_caption'],
				((isset($field_array['default'])) ? $field_array['default'] : ''),
				((isset($field_array['class'])) ? $field_array['class'] : ''),
				((isset($field_array['on_change'])) ? $field_array['on_change'] : '')
			);

			print '<br>';
		}

		break;
	case 'custom':
		print $field_array['value'];

		break;
	case 'template_checkbox':
		print '<em>' . html_boolean_friendly($field_array['value']) . '</em>';

		form_hidden_box($field_name, $field_array['value'], '', true);

		break;
	case 'template_drop_array':
		print '<em>' . $field_array['array']{$field_array['value']} . '</em>';

		form_hidden_box($field_name, $field_array['value'], '', true);

		break;
	case 'font':
		form_font_box(
			$field_name, 
			$field_array['value'],
			((isset($field_array['default'])) ? $field_array['default'] : ''),
			$field_array['max_length'],
			((isset($field_array['size'])) ? $field_array['size'] : '40'), 'text',
			((isset($field_array['form_id'])) ? $field_array['form_id'] : ''),
			((isset($field_array['placeholder'])) ? $field_array['placeholder'] : '')
		);

		break;
	case 'file':
		form_file($field_name,
			((isset($field_array['size'])) ? $field_array['size'] : '40'));

		break;
	case 'button':
		form_button(
			$field_name, 
			((isset($field_array['value'])) ? $field_array['value'] : ''),
			((isset($field_array['title'])) ? $field_array['title'] : ''),
			((isset($field_array['on_click'])) ? $field_array['on_click'] : '')
		);

		break;
	default:
		print '<em id="' . $field_name . '">' . htmlspecialchars($field_array['value'],ENT_QUOTES) . '</em>';

		form_hidden_box($field_name, $field_array['value'], '', true);

		break;
	}
}

/* form_button - draws a standard button form element
   @arg $form_name - the name of this form element
   @arg $value - the display value for the button
   @arg $title - the hover title for the button
   @arg $action - the onClick action for the button */
function form_button($form_name, $value, $title = '', $action = '') {
	print "<input role='button' type='button' " . 
		"id='$form_name' " . 
		"name='$form_name' " . 
		"value='$value' " . 
		($action!='' ? "onClick='$action'":"") . 
		($title!='' ? "title='$title'":"") . ">";
}

/* form_file - draws a standard html file input element
   @arg $form_name - the name of this form element
   @arg $form_size - the size (width) of the textbox */
function form_file($form_name, $form_size = 30) {
	print "<div>\n";
	print "<label class='import_label' for='import_file'>" . __('Select a File'). "</label>\n";
	print "<input class='import_button' type='file'";

	if (isset($_SESSION["sess_error_fields"]) && !empty($_SESSION["sess_error_fields"][$form_name])) {
		print "class='txtErrorTextBox'";
		unset($_SESSION["sess_error_fields"][$form_name]);
	}

	print " id='$form_name' name='$form_name' size='$form_size'>\n";
	print "<span class='import_text'></span>\n";
	print "</div>\n";
}

/* form_filepath_box - draws a standard html textbox and provides status of a files existence
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
function form_filepath_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30, $type = 'text', $current_id = 0) {
	if (($form_previous_value == '') && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}

	print "<input type='$type'";

	if (isset($_SESSION['sess_error_fields'])) {
		if (!empty($_SESSION['sess_error_fields'][$form_name])) {
			print "class='txtErrorTextBox'";
			unset($_SESSION['sess_error_fields'][$form_name]);
		}
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if (is_file(trim($form_previous_value))) {
		$extra_data = "<span class='cactiTooltipHint fa fa-check-circle' style='padding:5px;font-size:16px;color:green' title='" . __('File Found') . "'></span>";
	}else if (is_dir(trim($form_previous_value))) {
		$extra_data = "<span class='cactiTooltipHint fa fa-times-circle' style='padding:5px;font-size:16px;color:red' title='" . __('Path is a Directory and not a File') . "'></span>";
	}else if (strlen($form_previous_value) == 0) {
		$extra_data = '';
	}else{
		$extra_data = "<span class='cactiTooltipHint fa fa-times-circle' style='padding:5px;font-size:16px;color:red' title='" . __('File is Not Found'). "'></span>";
	}

	print " id='$form_name' placeholder='" . __('Enter a valid file path') . "' name='$form_name' size='$form_size'" . (!empty($form_max_length) ? " maxlength='$form_max_length'" : '') . " value='" . htmlspecialchars($form_previous_value, ENT_QUOTES) . "'>" . $extra_data;
}

/* form_dirpath_box - draws a standard html textbox and provides status of a directories existence
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
function form_dirpath_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30, $type = 'text', $current_id = 0) {
	if (($form_previous_value == '') && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}

	print "<input type='$type'";

	if (isset($_SESSION['sess_error_fields'])) {
		if (!empty($_SESSION['sess_error_fields'][$form_name])) {
			print "class='txtErrorTextBox'";
			unset($_SESSION['sess_error_fields'][$form_name]);
		}
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if (is_dir($form_previous_value)) {
		$extra_data = "<span class='cactiTooltipHint fa fa-check-circle' style='padding:5px;font-size:16px;color:green' title='" . __('Directory Found') ."'></span>";
	}else if (is_file($form_previous_value)) {
		$extra_data = "<span class='cactiTooltipHint fa fa-times-circle' style='padding:5px;font-size:16px;color:red' title='" . __('Path is a File and not a Directory'). "></span>";
	}else if (strlen($form_previous_value) == 0) {
		$extra_data = '';
	}else{
		$extra_data = "<span class='cactiTooltipHint fa fa-times-circle' style='padding:5px;font-size:16px;color:red' title='" . __('Directory is Not found'). "'></span>";
	}

	print " id='$form_name' name='$form_name' placeholder='" . __('Enter a valid directory path'). "' size='$form_size'" . (!empty($form_max_length) ? " maxlength='$form_max_length'" : '') . " value='" . htmlspecialchars($form_previous_value, ENT_QUOTES) . "'>" . $extra_data;
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
function form_text_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30, $type = 'text', $current_id = 0, $placeholder = '') {
	if (($form_previous_value == '') && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}

	print "<input role='textbox' type='$type'";

	if (isset($_SESSION['sess_error_fields'])) {
		if (!empty($_SESSION['sess_error_fields'][$form_name])) {
			print "class='txtErrorTextBox'";
			unset($_SESSION['sess_error_fields'][$form_name]);
		}
	}

	if (isset($_SESSION['sess_field_values']) && isset($_SESSION['sess_error_fields'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	print " id='$form_name' autocomplete='off' " . ($placeholder != '' ? "placeholder='$placeholder'":'') . " name='$form_name' size='$form_size'" . (!empty($form_max_length) ? " maxlength='$form_max_length'" : '') . " value='" . htmlspecialchars($form_previous_value, ENT_QUOTES) . "'>\n";
}

/* form_hidden_box - draws a standard html hidden element
   @arg $form_name - the name of this form element
   @arg $form_previous_value - the current value of this form element
   @arg $form_default_value - the value of this form element to use if there is
     no current value available */
function form_hidden_box($form_name, $form_previous_value, $form_default_value, $in_form = false) {
	if ($form_previous_value == '') {
		$form_previous_value = $form_default_value;
	}

	print ($in_form) ? "<tr style='display:none;'><td colspan='2'>\n":'';
	print "<input style='height:0px;' type='hidden' id='$form_name' name='$form_name' value='" . htmlspecialchars($form_previous_value, ENT_QUOTES) . "'>\n";
	print ($in_form) ? "</td></tr>\n":'';
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
   @arg $css_class - any css that needs to be applied to this form element
   @arg $on_change - onChange modifier */
function form_dropdown($form_name, $form_data, $column_display, $column_id, $form_previous_value, $form_none_entry, $form_default_value, $class = '', $on_change = '') {
	if ($form_previous_value == '') {
		$form_previous_value = $form_default_value;
	}

	if (isset($_SESSION['sess_error_fields'])) {
		if (!empty($_SESSION['sess_error_fields'][$form_name])) {
			$class .= (strlen($class) ? ' ':'') . 'txtErrorTextBox';
			unset($_SESSION['sess_error_fields'][$form_name]);
		}
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if (strlen($class)) {
		$class = " class='$class' ";
	}

	if (strlen($on_change)) {
		$on_change = " onChange='$on_change' ";
	}

	print "<select id='" . htmlspecialchars($form_name) . "' name='" . htmlspecialchars($form_name) . "'" . $class . $on_change . '>';

	if (!empty($form_none_entry)) {
		print "<option value='0'" . (empty($form_previous_value) ? ' selected' : '') . ">$form_none_entry</option>\n";
	}

	html_create_list($form_data, $column_display, $column_id, htmlspecialchars($form_previous_value, ENT_QUOTES));

	print "</select>\n";
}

function form_callback($form_name, $classic_sql, $column_display, $column_id, $callback, $previous_id, $previous_value, $none_entry, $default_value, $class = '', $on_change = '') {
	if ($previous_value == '') {
		$previous_value = $default_value;
	}

	if (isset($_SESSION['sess_error_fields'])) {
		if (!empty($_SESSION['sess_error_fields'][$form_name])) {
			$class .= (strlen($class) ? ' ':'') . 'txtErrorTextBox';
			unset($_SESSION['sess_error_fields'][$form_name]);
		}
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if (strlen($class)) {
		$class = " class='$class' ";
	}

	if (strlen($on_change)) {
		$on_change = " onChange='$on_change' ";
	}

	$theme = get_selected_theme();
	if ($theme == 'classic') {
		print "<select id='" . htmlspecialchars($form_name) . "' name='" . htmlspecialchars($form_name) . "'" . $class . $on_change . '>';

		if (!empty($none_entry)) {
			print "<option value='0'" . (empty($previous_value) ? ' selected' : '') . ">$none_entry</option>\n";
		}

		$form_data = db_fetch_assoc($classic_sql);

		html_create_list($form_data, $column_display, $column_id, htmlspecialchars($previous_id, ENT_QUOTES));

		print "</select>\n";
	}else{
		$form_prefix = htmlspecialchars($form_name);

		print "<span id='$form_prefix" . "_wrap' style='width:200px;' class='ui-selectmenu-button ui-widget ui-state-default ui-corner-all'>\n";
		print "<span id='$form_prefix" . "_click' style='z-index:4' class='ui-icon ui-icon-triangle-1-s'></span>\n";
		print "<input id='$form_prefix" . "_input' class='ui-selectmenu-text ui-state-default' value='" . htmlspecialchars($previous_value) . "'>\n";
		print "</span>\n";
		print "<input type='hidden' id='" . $form_prefix . "' name='" . $form_prefix . "' value='" . $previous_id . "'>\n";
		?>
		<script type='text/javascript'>
		var <?php print $form_name;?>Timer;
		var <?php print $form_name;?>ClickTimer;
		var <?php print $form_name;?>Open = false;

		$(function() {
		    $('#<?php print $form_prefix;?>_input').autocomplete({
		        source: '<?php print $_SERVER['PHP_SELF'];?>?action=<?php print $callback;?>',
				autoFocus: true,
				minLength: 0,
				select: function(event,ui) {
					$('#<?php print $form_prefix;?>').val(ui.item.id);
					<?php print $on_change;?>;
				}
			}).css('border', 'none').css('background-color', 'transparent');

			$('#<?php print $form_prefix;?>_wrap').dblclick(function() {
				<?php print $form_name;?>Open = false;
				clearTimeout(<?php print $form_name;?>Timer);
				clearTimeout(<?php print $form_name;?>ClickTimer);
				$('#<?php print $form_prefix;?>_input').autocomplete('close');
			}).click(function() {
				if (<?php print $form_name;?>Open) {
					$('#<?php print $form_prefix;?>_input').autocomplete('close');
					clearTimeout(<?php print $form_name;?>Timer);
					<?php print $form_name;?>Open = false;
				}else{
					<?php print $form_name;?>ClickTimer = setTimeout(function() {
						$('#<?php print $form_prefix;?>_input').autocomplete('search', $('#<?php print $form_prefix;?>_input').val());
						clearTimeout(<?php print $form_name;?>Timer);
						<?php print $form_name;?>Open = true;
					}, 200);
				}
			}).on('mouseleave', function() {
				<?php print $form_name;?>Timer = setTimeout(function() { $('#<?php print $form_prefix;?>_input').autocomplete('close'); }, 800);
			});

			$('ul[id^="ui-id"]').on('mouseenter', function() {
				clearTimeout(<?php print $form_name;?>Timer);
			}).on('mouseleave', function() {
				<?php print $form_name;?>Timer = setTimeout(function() { $('#<?php print $form_prefix;?>_input').autocomplete('close'); }, 800);
			});

			$('ul[id^="ui-id"] > li').each().on('mouseenter', function() {
				$(this).addClass('ui-state-hover');
			}).on('mouseleave', function() {
				$(this).removeClass('ui-state-hover');
			});

			$('#<?php print $form_prefix;?>_wrap').on('mouseenter', function() {
				$(this).addClass('ui-state-hover');
				$('input#<?php print $form_prefix;?>_input').addClass('ui-state-hover');
			}).on('mouseleave', function() {
				$(this).removeClass('ui-state-hover');
				$('input#<?php print $form_prefix;?>_input').removeClass('ui-state-hover');
			});
		});
		</script>
		<?php
	}
}

/** form_checkbox - draws a standard html checkbox
   @param string $form_name - the name of this form element
   @param string $form_previous_value - the current value of this form element
   @param string $form_caption - the text to display to the right of the checkbox
   @param string $form_default_value - the value of this form element to use if there is
     no current value available
   @param int $current_id - used to determine if a current value for this form element
     exists or not. a $current_id of '0' indicates that no current value exists,
     a non-zero value indicates that a current value does exist
   @param string $class - specify a css class
   @param string $on_change - specify a javascript onchange action */
function form_checkbox($form_name, $form_previous_value, $form_caption, $form_default_value, $current_id = 0, $class = '', $on_change = '') {
	if (($form_previous_value == '') && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if (strlen($class)) {
		$class = " class='$class'";
	}

	if (strlen($on_change)) {
		$on_change = " onChange='$on_change'";
	}

	if ($form_previous_value == 'on') {
		$checked = " checked aria-checked='true'";
	}else{
		$checked = " aria-checked='false'";
	}

	print "<input role='checkbox' type='checkbox' id='$form_name' name='$form_name'" . $on_change . $class . $checked . ">" . ($form_caption != '' ? " <label for='$form_name'>$form_caption</label>\n":"");
}

/* form_radio_button - draws a standard html radio button
   @arg $form_name - the name of this form element
   @arg $form_previous_value - the current value of this form element (selected or not)
   @arg $form_current_value - the current value of this form element (element id)
   @arg $form_caption - the text to display to the right of the checkbox
   @arg $form_default_value - the value of this form element to use if there is
     no current value available */
function form_radio_button($form_name, $form_previous_value, $form_current_value, $form_caption, $form_default_value, $class = '', $on_change = '') {
	if ($form_previous_value == '') {
		$form_previous_value = $form_default_value;
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if (strlen($class)) {
		$class = " class='$class' ";
	}

	if (strlen($on_change)) {
		$on_change = " onChange='$on_change' ";
	}

	if ($form_previous_value == $form_current_value) {
		$checked = " checked aria-checked='true'";
	}else{
		$checked = " aria-checked='false'";
	}

	$css_id = $form_name . '_' . $form_current_value;

	print "<input role='radio' type='radio' id='$css_id' name='$form_name' value='$form_current_value'" . $class . $on_change . $checked . "><label for='$css_id'>$form_caption</label>\n";
}

/* form_text_area - draws a standard html text area box
   @arg $form_name - the name of this form element
   @arg $form_previous_value - the current value of this form element (selected or not)
   @arg $form_rows - the number of rows in the text area box
   @arg $form_columns - the number of columns in the text area box
   @arg $form_default_value - the value of this form element to use if there is
     no current value available */
function form_text_area($form_name, $form_previous_value, $form_rows, $form_columns, $form_default_value, $class = '', $on_change = '', $placeholder = '') {
	if ($form_previous_value == '') {
		$form_previous_value = $form_default_value;
	}

	if (isset($_SESSION['sess_error_fields'])) {
		if (!empty($_SESSION['sess_error_fields'][$form_name])) {
			$class .= (strlen($class) ? ' ':'') . 'txtErrorTextBox';
			unset($_SESSION['sess_error_fields'][$form_name]);
		}
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if (strlen($class)) {
		$class = " class='$class' ";
	}

	if (strlen($on_change)) {
		$on_change = " onChange='$on_change' ";
	}

	if (strlen($placeholder)) {
		$placeholder = " placeholder='$placeholder'";
	}

	print "<textarea role='textbox' aria-multiline='true' cols='$form_columns' rows='$form_rows' id='$form_name' name='$form_name'" . $class . $on_change . $placeholder . '>' . htmlspecialchars($form_previous_value, ENT_QUOTES) . "</textarea>\n";
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
function form_multi_dropdown($form_name, $array_display, $sql_previous_values, $column_id, $class = '', $on_change = '') {
	if (!is_array($sql_previous_values) && $sql_previous_values != '') {
		$values = explode(',', $sql_previous_values);
		$sql_previous_values = array();
		foreach($values as $value) {
			$sql_previous_values[][$column_id] = $value;
		}
	}elseif ($sql_previous_values == '') {
		$values = db_fetch_cell_prepared('SELECT value FROM settings WHERE name = ?', array($form_name));
		if ($values != '') {
			$values = explode(',', $values);
			foreach($values as $value) {
				$sql_previous_values[][$column_id] = $value;
			}
		}
	}

	if (isset($_SESSION['sess_error_fields'])) {
		if (!empty($_SESSION['sess_error_fields'][$form_name])) {
			$class .= (strlen($class) ? ' ':'') . 'txtErrorTextBox';
			unset($_SESSION['sess_error_fields'][$form_name]);
		}
	}

	$class = 'multiselect';
	if (strlen($class)) {
		$class .= " $class";
	}

	if (strlen($on_change)) {
		$on_change = " onChange='$on_change' ";
	}

	print "<select style='height:20px;' size='1' class='$class' id='$form_name' name='$form_name" . "[]' multiple>\n";

	foreach (array_keys($array_display) as $id) {
		print "<option value='" . $id . "'";

		if (is_array($sql_previous_values) && sizeof($sql_previous_values)) {
			for ($i=0; ($i < count($sql_previous_values)); $i++) {
				if ($sql_previous_values[$i][$column_id] == $id) {
					print ' selected';
				}
			}
		}

		print '>' . htmlspecialchars($array_display[$id],ENT_QUOTES);
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
function form_color_dropdown($form_name, $form_previous_value, $form_none_entry, $form_default_value, $class = '', $on_change = '') {
	if ($form_previous_value == '') {
		$form_previous_value = $form_default_value;
	}

	if (strlen($class)) {
		$class = " class='colordropdown $class' ";
	}else{
		$class = " class='colordropdown'";
	}

	$current_color = db_fetch_cell_prepared('SELECT hex FROM colors WHERE id = ?', array($form_previous_value));

	if (strlen($on_change)) {
		$on_change = ' ' . $on_change . ';';
	}

	$on_change = " onChange='this.style.backgroundColor=this.options[this.selectedIndex].style.backgroundColor;$on_change'";

	$colors_list = db_fetch_assoc('SELECT * FROM colors ORDER BY hex DESC');

	print "<select style='background-color: #$current_color;' id='$form_name' name='$form_name'" . $class . $on_change . ">\n";

	if ($form_none_entry != '') {
		print "<option value='0'>$form_none_entry</option>\n";
	}

	if (sizeof($colors_list) > 0) {
		foreach ($colors_list as $color) {
			if ($color['name'] == '') {
				$display = 'Cacti Color (' . $color['hex'] . ')';
			}else{
				$display = $color['name'] . ' (' . $color['hex'] . ')';
			}
			print "<option data-color='" . $color['hex'] . "' data-style='background-color: #" . $color['hex'] . "' style='background-color: #" . $color['hex'] . ";' value='" . $color['id'] . "'";

			if ($form_previous_value == $color['id']) {
				print ' selected';
			}

			print '>' . $display . "</option>\n";
		}
	}

	print "</select>\n";
}

/* form_font_box - draws a standard html textbox and provides status of a fonts existence
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
function form_font_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30, $type = 'text', $current_id = 0, $placeholder = '') {
	global $config;

	if (($form_previous_value == '') && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}

	print "<input type='$type'";

	if (isset($_SESSION['sess_error_fields'])) {
		if (!empty($_SESSION['sess_error_fields'][$form_name])) {
			print "class='txtErrorTextBox'";
			unset($_SESSION['sess_error_fields'][$form_name]);
		}
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if (strlen($form_previous_value) == 0) { # no data: defaults are used; everythings fine
			$extra_data = '';
	} else {

		/* do some simple checks */
		if (read_config_option('rrdtool_version') == 'rrd-1.2.x') { # rrdtool 1.2 uses font files
			if (is_file($form_previous_value)) {
				$extra_data = "<span style='color:green'><br>[" . __('OK: FILE FOUND') . ']</span>';
			}else if (is_dir($form_previous_value)) {
				$extra_data = "<span style='color:red'><br>[" . __('ERROR: IS DIR') . ']</span>';
			}else{
				$extra_data = "<span style='color:red'><br>[" . __('ERROR: FILE NOT FOUND') . ']</span>';
			}
		} else {	# rrdtool 1.3+ use fontconfig
			/* verifying all possible pango font params is too complex to be tested here
			 * so we only escape the font
			 */
			$extra_data = "<span style='color:green'><br>[" . __('NO FONT VERIFICATION POSSIBLE') . ']</span>';
		}
	}

	print " id='$form_name' " . ($placeholder != '' ? "placeholder='" . htmlspecialchars($placeholder) . "'":'') . " name='$form_name' size='$form_size'" . (!empty($form_max_length) ? " maxlength='$form_max_length'" : '') . " value='" . htmlspecialchars($form_previous_value, ENT_QUOTES) . "'>" . $extra_data;
}

/* form_confirm - draws a table presenting the user with some choice and allowing
     them to either proceed (delete) or cancel
   @arg $body_text - the text to prompt the user with on this form
   @arg $cancel_url - the url to go to when the user clicks 'cancel'
   @arg $action_url - the url to go to when the user clicks 'delete' */
function form_confirm($title_text, $body_text, $cancel_url, $action_url) { ?>
	<br>
	<table style="width:60%;">
		<tr>
			<td class='even' colspan='10'>
				<table>
					<tr class='cactiTableTitle'>
						<td class='textHeaderDark'><?php print $title_text;?></td>
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
function form_confirm_buttons($action_url, $cancel_url) {
	global $config;
	?>
	<tr>
		<td align='right'>
			<input type='button' onClick='cactiReturnTo("<?php print $config['url_path'] . $cancel_url;?>")' value='<?php print __('Cancel');?>'>
			<input type='button' onClick='cactiReturnTo("<?php print $config['url_path'] . $action_url;?>&confirm=true")' value='<?php print __('Delete');?>'>
		</td>
	</tr>
<?php }

/* form_save_button - draws a (save|create) and cancel button at the bottom of
     an html edit form
   @arg $cancel_url - the url to go to when the user clicks 'cancel'
   @arg $force_type - if specified, will force the 'action' button to be either
     'save' or 'create'. otherwise this field should be properly auto-detected */
function form_save_button($cancel_url, $force_type = '', $key_field = 'id', $ajax = true) {
	$calt = __('Cancel');

	if (empty($force_type) || $force_type == 'return') {
		if (isempty_request_var($key_field)) {
			$alt = __('Create');
		}else{
			$alt = __('Save');

			if (strlen($force_type)) {
				$calt   = __('Return');
			}else{
				$calt   = __('Cancel');
			}
		}
	}elseif ($force_type == 'save') {
		$alt = __('Save');
	}elseif ($force_type == 'create') {
		$alt = __('Create');
	}elseif ($force_type == 'import') {
		$alt = __('Import');
	}elseif ($force_type == 'export') {
		$alt = __('Export');
	}

	if ($force_type != 'import' && $force_type != 'export' && $force_type != 'save' && $cancel_url != '') {
		$cancel_action = "<input type='button' onClick='cactiReturnTo(\"" . $cancel_url . "\")' value='" . $calt . "'>";
	}else{
		$cancel_action = '';
	}

	?>
	<table style='width:100%;text-align:center;'>
		<tr>
			<td class='saveRow'>
				<input type='hidden' name='action' value='save'>
				<?php print $cancel_action;?>
				<input class='<?php print $force_type;?>' id='submit' type='submit' value='<?php print $alt;?>'>
			</td>
		</tr>
	</table>
	<?php

	form_end($ajax);
}

/* form_save_buttons - draws a set of buttons at the end of a form
     an html edit form
   @arg $buttons - an array of 'id', 'name' buttons */
function form_save_buttons($buttons) {
	?>
	<table style='width:100%;text-align:center;'>
		<tr>
			<td class='saveRow'>
				<input type='hidden' name='action' value='save'>
				<?php foreach($buttons as $b) {
					print "<input type='button' id='" . $b['id'] . "' value='" . $b['value'] . "'>\n";
				} ?>
			</td>
		</tr>
	</table>
	<?php
}

/* form_start - draws post form start. To be combined with form_end()
   @arg $action - a mandatory php file URI
   @arg $id     - an optional id, if empty, one will be generated */
function form_start($action, $id = '') {
	global $form_id, $form_action;
	static $counter = 1;

	if ($id == '') {
		$form_id = 'form' . $counter;
		$counter++;
	}else{
		$form_id = trim($id);
	}

	$form_action = $action;

	print "<form id='$form_id' name='$form_id' action='$form_action' autocomplete='off' method='post'>\n";
}

/* form_end - draws post form end. To be combined with form_start() */
function form_end($ajax = true) {
	global $form_id, $form_action;

	print "</form>\n";

	if ($ajax) { ?>
		<script type='text/javascript'>
		$(function() {
			$('#<?php print $form_id;?>').submit(function(event) {
				event.preventDefault();
				strURL = '<?php print $form_action;?>';
				strURL += (strURL.indexOf('?') >- 0 ? '&':'?') + 'header=false';
				json =  $('#<?php print $form_id;?>').serializeObject();
				$.post(strURL, json).done(function(data) {
					$('#main').html(data);
					applySkin();
					window.scrollTo(0, 0);
				});
			});
		});
		</script>
		<?php
	}
}
