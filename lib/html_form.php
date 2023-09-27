<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
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
     for the extract syntax of this array */
function draw_edit_form($array) {
	if (cacti_sizeof($array)) {
		foreach ($array as $top_branch => $top_children) {
			if ($top_branch == 'config') {
				$config_array = $top_children;
			} elseif ($top_branch == 'fields') {
				$fields_array = $top_children;
			}
		}
	}

	if (cacti_sizeof($fields_array)) {
		if (!isset($config_array['no_form_tag'])) {
			print "<form class='cactiForm' method='post' autocomplete='off' action='" . ((isset($config_array['post_to'])) ? $config_array['post_to'] : get_current_page()) . "'" . ((isset($config_array['form_name'])) ? " name='" . $config_array['form_name'] . "'" : '') . ((isset($config_array['enctype'])) ? " enctype='" . $config_array['enctype'] . "'" : '') . ">\n";
		}

		$i = 0;
		$row_class = 'odd';

		foreach ($fields_array as $field_name => $field_array) {
			if ($field_array['method'] == 'hidden') {
				print '<div class="hidden formRow">';
				form_hidden_box($field_name, $field_array['value'], ((isset($field_array['default'])) ? $field_array['default'] : ''), true);
				print '</div>';
			} elseif ($field_array['method'] == 'hidden_zero') {
				print '<div class="hidden formRow">';
				form_hidden_box($field_name, $field_array['value'], '0', true);
				print '</div>';
			} elseif ($field_array['method'] == 'spacer') {
				$collapsible = (isset($field_array['collapsible']) && $field_array['collapsible'] == 'true');

				print "<div class='spacer formHeader" . ($collapsible ? ' collapsible':'') . "' id='row_$field_name'><div class='formHeaderText'>" . html_escape($field_array['friendly_name']);
				print '<div class="formTooltip">' . (isset($field_array['description']) ? display_tooltip($field_array['description']):'') . '</div>';
				print ($collapsible ? "<div class='formHeaderAnchor'><i class='fa fa-angle-double-up'></i></div>":'') . '</div></div>';
			} else {
				// Make a row using a div
				if (isset($config_array['force_row_color'])) {
					print "<div id='row_$field_name' class='formRow even-alternate $row_class'>";
				} else {
					print "<div id='row_$field_name' class='formRow $row_class'>";
					if ($row_class == 'even') {
						$row_class = 'odd';
					} else {
						$row_class = 'even';
					}
				}

				// Make a form cell
				print "<div class='formColumnLeft'>";

				print "<div class='formFieldName'>";

				if (isset($field_array['sub_checkbox'])) {
					form_checkbox($field_array['sub_checkbox']['name'],
						$field_array['sub_checkbox']['value'],
						'',
						((isset($field_array['sub_checkbox']['default'])) 	? $field_array['sub_checkbox']['default'] : ''),
						((isset($field_array['sub_checkbox']['form_id'])) 	? $field_array['sub_checkbox']['form_id'] : ''),
						((isset($field_array['sub_checkbox']['class'])) 	? $field_array['sub_checkbox']['class'] : ''),
						((isset($field_array['sub_checkbox']['on_change'])) ? $field_array['sub_checkbox']['on_change'] : ''),
						((isset($field_array['sub_checkbox']['friendly_name'])) ? $field_array['sub_checkbox']['friendly_name'] : ''));
				}

				print html_escape($field_array['friendly_name']);

				if (read_config_option('hide_form_description') == 'on') {
					print '<br><span class="formFieldDescription">' . ((isset($field_array['description'])) ? $field_array['description'] : '') . "</span>\n";
				} else {
					print '<div class="formTooltip">';
					print display_tooltip((isset($field_array['description'])) ? $field_array['description'] : '');
					print '</div>';
				}

				print '</div>';

				// End form cell
				print '</div>';

				// New form column for content
				print '<div class="formColumnRight"><div class="formData">';

				draw_edit_control($field_name, $field_array);

				// End content column
				print '</div></div>';

				// End form row
				print '</div>';
			}

			$i++;
		}

		if (isset($_SESSION['sess_error_fields'])) {
			kill_session_var('sess_error_fields');
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
	case 'drop_language':
		form_droplanguage(
			$field_name,
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
		$array_files = array();

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

				if (cacti_sizeof($files)) {
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
			((isset($field_array['none_value'])) ? $field_array['none_value'] : ''),
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
			__('None'),
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
			((isset($field_array['on_change'])) ? $field_array['on_change'] : ''),
			$field_array['friendly_name']
		);

		break;
	case 'checkbox_group':
		if (isset($field_array['type']) && $field_array['type'] == 'flex') {
			print "</td></tr><tr><td><div id='{$field_name}_group' class='checkboxgroup1 flexContainer'>" . PHP_EOL;
		} else {
			print "<div id='{$field_name}_group' class='checkboxgroup1'>" . PHP_EOL;
		}

		foreach ($field_array['items'] as $check_name => $check_array) {
			if (isset($field_array['type']) && $field_array['type'] == 'flex') {
				print '<div class="flexChild">';
			}

			form_checkbox(
				$check_name,
				$check_array['value'],
				$check_array['friendly_name'],
				((isset($check_array['default'])) ? $check_array['default'] : ''),
				((isset($check_array['form_id'])) ? $check_array['form_id'] : ''),
				((isset($field_array['class'])) ? $field_array['class'] : ''),
				((isset($check_array['on_change'])) ? $check_array['on_change'] : (((isset($field_array['on_change'])) ? $field_array['on_change'] : ''))),
				$field_array['friendly_name'],
				true
			);

			if (isset($field_array['type']) && $field_array['type'] == 'flex') {
				print '</div>';
			} else {
				print '<br>';
			}
		}

		if (isset($field_array['type']) && $field_array['type'] == 'flex') {
			print '</div>' . PHP_EOL;
		} else {
			print '</div>' . PHP_EOL;
		}

		break;
	case 'radio':
		print "<div style='formRadio'>" . PHP_EOL;

		foreach ($field_array['items'] as $radio_index => $radio_array) {
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

		print '</div>' . PHP_EOL;

		break;
	case 'custom':
		print $field_array['value'];

		break;
	case 'template_checkbox':
		print '<em>' . html_boolean_friendly($field_array['value']) . '</em>';

		form_hidden_box($field_name, $field_array['value'], '', true);

		break;
	case 'template_drop_array':
		print '<em>' . $field_array['array'][$field_array['value']] . '</em>';

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
			((isset($field_array['size'])) ? $field_array['size'] : '40'),
			((isset($field_array['accept'])) ? $field_array['accept'] : ''));

		break;
	case 'button':
		form_button(
			$field_name,
			((isset($field_array['value'])) ? $field_array['value'] : ''),
			((isset($field_array['title'])) ? $field_array['title'] : ''),
			((isset($field_array['on_click'])) ? $field_array['on_click'] : '')
		);

		break;
	case 'submit':
		form_submit(
			$field_name,
			((isset($field_array['value'])) ? $field_array['value'] : ''),
			((isset($field_array['title'])) ? $field_array['title'] : ''),
			((isset($field_array['on_click'])) ? $field_array['on_click'] : '')
		);

		break;
	default:
		if (isset($field_array['value'])) {
			print '<em>' . html_escape($field_array['value']) . '</em>';

			form_hidden_box($field_name, $field_array['value'], '', true);
		} else {
			cacti_log('ERROR: Field Name: ' . $field_name . ' includes Method: ' . $field_array['method'] . ' does not include a value \'value\' element.', false);
		}

		break;
	}
}

/* form_button - draws a standard button form element
   @arg $form_name - the name of this form element
   @arg $value - the display value for the button
   @arg $title - the hover title for the button
   @arg $action - the onClick action for the button */
function form_button($form_name, $value, $title = '', $action = '') {
	print "<input type='button' class='ui-button ui-corner-all ui-widget' " .
		"id='$form_name' " .
		"name='$form_name' " .
		"value='" . html_escape($value) . "' " .
		($action!='' ? "onClick='$action'":"") .
		($title!='' ? "title='" . html_escape($title) . "'":"") . ">";
}

/* form_button - draws a standard button form element
   @arg $form_name - the name of this form element
   @arg $value - the display value for the button
   @arg $title - the hover title for the button
   @arg $action - the onClick action for the button */
function form_submit($form_name, $value, $title = '', $action = '') {
	print "<input type='submit' class='ui-button ui-corner-all ui-widget' " .
		"id='$form_name' " .
		"name='$form_name' " .
		"value='" . html_escape($value) . "' " .
		($action!='' ? "onClick='$action'":"") .
		($title!='' ? "title='" . html_escape($title) . "'":"") . ">";
}

/* form_file - draws a standard html file input element
   @arg $form_name - the name of this form element
   @arg $form_size - the size (width) of the textbox
   @arg $form_accept - the file types permitted
 */
function form_file($form_name, $form_size = 30, $form_accept = '') {
	print "<div>\n";
	print "<label class='import_label' for='$form_name'>" . __('Select a File'). "</label>\n";
	print "<input type='file'";

	if (isset($_SESSION['sess_error_fields']) && !empty($_SESSION['sess_error_fields'][$form_name])) {
		print " class='import_button ui-state-default ui-corner-all txtErrorTextBox'";
		unset($_SESSION['sess_error_fields'][$form_name]);
	} else {
		print " class='import_button ui-state-default ui-corner-all'";
	}

	print " id='$form_name' name='$form_name' size='$form_size'" . ($form_accept != '' ? " accept='$form_accept'":'') . ">\n";
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
     a non-zero value indicates that a current value does exist
   @arg data - array containing 'text' element for display and if 'error' element present, shows failure */
function form_filepath_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30, $type = 'text', $current_id = 0, $data = false) {
	if (($form_previous_value == '') && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}

	print "<input type='$type'";

	$extra_text = '';
	$extra_color = '';
	$extra_class = '';
	$error_class = '';
	if (is_array($data)) {
		$extra_text = $data['text'];
		$extra_class = (isset($data['error']) ? 'fa-times-circle' : 'fa-check-circle');
		$extra_color = (isset($data['error']) ? 'red' : 'green');
		$error_class = (isset($data['error']) ? 'txtErrorTextBox' : '');
	} else {
		if (isset($_SESSION['sess_field_values'])) {
			if (!empty($_SESSION['sess_field_values'][$form_name])) {
				$form_previous_value = $_SESSION['sess_field_values'][$form_name];
			}
		}

		if (isset($_SESSION['sess_error_fields'])) {
			if (!empty($_SESSION['sess_error_fields'][$form_name])) {
				$error_class = 'txtErrorTextBox';
				unset($_SESSION['sess_error_fields'][$form_name]);
			}
		}

		if ($form_previous_value == '') {
			$extra_text  = '';
		} else if (is_file(trim($form_previous_value))) {
			$extra_class = 'fa-check-circle';
			$extra_color = 'green';
			$extra_text  = __esc('File Found');
		} else if (is_dir(trim($form_previous_value))) {
			$extra_class = 'fa-times-circle';
			$extra_color = 'red';
			$extra_text  = __esc('Path is a Directory and not a File');
		} else {
			$extra_class = 'fa-times-circle';
			$extra_color = 'red';
			$extra_text  = __esc('File is Not Found');
		}
	}

	$extra_data = '';
	if ($extra_text != '') {
		$extra_data = "<span class='cactiTooltipHint fa $extra_class' style='padding:5px;font-size:16px;color:$extra_color' title='$extra_text'></span>";
	}

	print " class='ui-state-default ui-corner-all$error_class'";

	print " id='$form_name' placeholder='" . __esc('Enter a valid file path') . "' name='$form_name' size='$form_size'" . (!empty($form_max_length) ? " maxlength='$form_max_length'" : '') . " value='" . html_escape($form_previous_value) . "'>" . $extra_data;
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
			print " class='ui-state-default ui-corner-all txtErrorTextBox'";
			unset($_SESSION['sess_error_fields'][$form_name]);
		} else {
			print " class='ui-state-default ui-corner-all'";
		}
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if (is_dir($form_previous_value)) {
		$extra_data = "<span class='cactiTooltipHint fa fa-check-circle' style='padding:5px;font-size:16px;color:green' title='" . __esc('Directory Found') ."'></span>";
	}else if (is_file($form_previous_value)) {
		$extra_data = "<span class='cactiTooltipHint fa fa-times-circle' style='padding:5px;font-size:16px;color:red' title='" . __esc('Path is a File and not a Directory'). "></span>";
	}else if ($form_previous_value == '') {
		$extra_data = '';
	} else {
		$extra_data = "<span class='cactiTooltipHint fa fa-times-circle' style='padding:5px;font-size:16px;color:red' title='" . __esc('Directory is Not found'). "'></span>";
	}

	print " id='$form_name' name='$form_name' placeholder='" . __esc('Enter a valid directory path'). "' size='$form_size'" . (!empty($form_max_length) ? " maxlength='$form_max_length'" : '') . " value='" . html_escape($form_previous_value) . "'>" . $extra_data;
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
     a non-zero value indicates that a current value does exist
   @arg $placeholder - place a placeholder over an empty field
   @arg $title - use a title attribute when hovering over the textbox
 */
function form_text_box($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30, $type = 'text', $current_id = 0, $placeholder = '', $title = '') {
	if (($form_previous_value == '') && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}

	if ($type == 'password') {
		print "<input type='text' style='display:none' value=''><input type='password' style='display:none' autocomplete='current-password' value=''>";
	}

	print "<input type='$type' " . ($type == 'password' || $type == 'password_confirm' ? 'autocomplete="current-password"':'off') . ($title != '' ? ' title="' . $title . '"':'');

	if (isset($_SESSION['sess_error_fields'])) {
		if (!empty($_SESSION['sess_error_fields'][$form_name])) {
			print " class='ui-state-default ui-corner-all txtErrorTextBox'";
			unset($_SESSION['sess_error_fields'][$form_name]);
		} else {
			print " class='ui-state-default ui-corner-all'";
		}
	} else {
		print " class='ui-state-default ui-corner-all'";
	}

	if (isset($_SESSION['sess_field_values']) && isset($_SESSION['sess_error_fields'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	print " id='$form_name' " . ($placeholder != '' ? "placeholder='" . html_escape($placeholder) . "'":'') . " name='$form_name' size='$form_size'" . (!empty($form_max_length) ? " maxlength='$form_max_length'" : '') . " value='" . html_escape($form_previous_value) . "'>\n";
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

	print "<div style='display:none;'><input style='height:0px;' type='hidden' id='$form_name' name='$form_name' value='" . html_escape($form_previous_value) . "'></div>";
}

/* form_dropdown - draws a standard html dropdown box
   @arg $form_name - the name of this form element
   @arg $form_data - an array containing data for this dropdown. it can be formatted
     in one of two ways:
     $array["id"] = "value";
     -- or --
     $array[0]["id"] = 43;
     $array[0]["name"] = "Red";
   @arg $column_display - used to identify the key to be used for display data. this
     is only applicable if the array is formatted using the second method above
   @arg $column_id - used to identify the key to be used for id data. this
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
			$class .= ($class != '' ? ' ':'') . 'txtErrorTextBox';
			unset($_SESSION['sess_error_fields'][$form_name]);
		}
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if ($class != '') {
		$class = " class='$class' ";
	}

	if ($on_change != '') {
		$on_change = " onChange='$on_change' ";
	}

	print "<select id='" . html_escape($form_name) . "' name='" . html_escape($form_name) . "'" . $class . $on_change . '>';

	if (!empty($form_none_entry)) {
		print "<option value='0'" . (empty($form_previous_value) ? ' selected' : '') . ">$form_none_entry</option>\n";
	}

	html_create_list($form_data, $column_display, $column_id, html_escape($form_previous_value));

	print "</select>\n";
}

function form_droplanguage($form_name, $column_display, $column_id, $form_previous_value, $form_none_entry, $form_default_value, $class = '', $on_change = '') {
	if ($form_previous_value == '') {
		$form_previous_value = $form_default_value;
	}

	if (isset($_SESSION['sess_error_fields'])) {
		if (!empty($_SESSION['sess_error_fields'][$form_name])) {
			$class .= ($class != '' ? ' ':'') . 'txtErrorTextBox';
			unset($_SESSION['sess_error_fields'][$form_name]);
		}
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if ($class != '') {
		$class = " class='$class' ";
	}

	if ($on_change != '') {
		$on_change = " onChange='$on_change' ";
	}

	$languages = get_installed_locales();

	print "<select id='" . html_escape($form_name) . "' name='" . html_escape($form_name) . "'" . $class . $on_change . '>';

	foreach ($languages as $key => $value) {
		$selected = '';
		if ($form_previous_value == $key) {
			$selected = ' selected';
		}

		$flags = explode('-', $key);

		if (cacti_count($flags) > 1) {
			$flagName = strtolower($flags[1]);
		} else {
			$flagName = strtolower($flags[0]);
		}

		print '<option value=\'' . $key . '\'' . $selected . ' data-class=\'fi-' . $flagName . '\'><span class="fi fis fi-' . $flagName . '"></span>' . __($value) . '</option>';
	}

	print '</select>';
}

function form_callback($form_name, $classic_sql, $column_display, $column_id, $callback, $previous_id, $previous_value, $none_entry, $default_value, $class = '', $on_change = '') {
	if ($previous_value == '') {
		$previous_value = $default_value;
	}

	if (isset($_SESSION['sess_error_fields'])) {
		if (!empty($_SESSION['sess_error_fields'][$form_name])) {
			$class .= ($class != '' ? ' ':'') . 'txtErrorTextBox';
			unset($_SESSION['sess_error_fields'][$form_name]);
		}
	}

	if ($class != '') {
		$class = " class='$class' ";
	}

	$theme = get_selected_theme();
	if ($theme == 'classic' || read_config_option('autocomplete') > 0) {
		print "<select id='" . html_escape($form_name) . "' name='" . html_escape($form_name) . "'" . $class . '>';

		if (!empty($none_entry)) {
			print "<option value='0'" . (empty($previous_value) ? ' selected' : '') . ">$none_entry</option>\n";
		}

		$form_data = db_fetch_assoc($classic_sql);

		html_create_list($form_data, $column_display, $column_id, html_escape($previous_id));

		print "</select>\n";
	} else {
		if (empty($previous_id) && $previous_value == '') {
			$previous_value = $none_entry;
		}

		print "<span id='$form_name" . "_wrap' class='autodrop ui-selectmenu-button ui-selectmenu-button-closed ui-corner-all ui-corner-all ui-button ui-widget'>";
		print "<span id='$form_name" . "_click' style='z-index:4' class='ui-selectmenu-icon ui-icon ui-icon-triangle-1-s'></span>";
		print "<span class='ui-select-text'>";
		print "<input type='text' class='ui-state-default ui-corner-all' id='$form_name" . "_input' value='" . html_escape($previous_value) . "'>";
		print "</span>";

		if (!empty($none_entry) && empty($previous_value)) {
			$previous_value = $none_entry;
		}

		print "</span>";
		print "<input type='hidden' id='" . $form_name . "' name='" . $form_name . "' value='" . html_escape($previous_id) . "'>";
		?>
		<script type='text/javascript'>
		var <?php print $form_name;?>Timer;
		var <?php print $form_name;?>ClickTimer;
		var <?php print $form_name;?>Open = false;

		$(function() {
		    $('#<?php print $form_name;?>_input').autocomplete({
				source: '<?php print get_current_page();?>?action=<?php print $callback;?>',
				autoFocus: true,
				minLength: 0,
				select: function(event,ui) {
					$('#<?php print $form_name;?>_input').val(ui.item.label);
					if (ui.item.id) {
						$('#<?php print $form_name;?>').val(ui.item.id);
					} else {
						$('#<?php print $form_name;?>').val(ui.item.value);
					}
					<?php print $on_change;?>;
				}
			}).css('border', 'none').css('background-color', 'transparent');

			$('#<?php print $form_name;?>_wrap').on('dblclick', function() {
				<?php print $form_name;?>Open = false;
				clearTimeout(<?php print $form_name;?>Timer);
				clearTimeout(<?php print $form_name;?>ClickTimer);
				$('#<?php print $form_name;?>_input').autocomplete('close').select();
			}).on('click', function() {
				if (<?php print $form_name;?>Open) {
					$('#<?php print $form_name;?>_input').autocomplete('close');
					clearTimeout(<?php print $form_name;?>Timer);
					<?php print $form_name;?>Open = false;
				} else {
					<?php print $form_name;?>ClickTimer = setTimeout(function() {
						$('#<?php print $form_name;?>_input').autocomplete('search', '');
						clearTimeout(<?php print $form_name;?>Timer);
						<?php print $form_name;?>Open = true;
					}, 200);
				}
				$('#<?php print $form_name;?>_input').select();
			}).on('mouseleave', function() {
				<?php print $form_name;?>Timer = setTimeout(function() { $('#<?php print $form_name;?>_input').autocomplete('close'); }, 800);
			});

			width = $('#<?php print $form_name;?>_input').textBoxWidth();
			if (width < 100) {
				width = 100;
			}

			$('#<?php print $form_name;?>_wrap').css('width', width+20);
			$('#<?php print $form_name;?>_input').css('width', width);

			$('ul[id^="ui-id"]').on('mouseenter', function() {
				clearTimeout(<?php print $form_name;?>Timer);
			}).on('mouseleave', function() {
				<?php print $form_name;?>Timer = setTimeout(function() { $('#<?php print $form_name;?>_input').autocomplete('close'); }, 800);
			});

			$('ul[id^="ui-id"] > li').each().on('mouseenter', function() {
				$(this).addClass('ui-state-hover');
			}).on('mouseleave', function() {
				$(this).removeClass('ui-state-hover');
			});

			$('#<?php print $form_name;?>_wrap').on('mouseenter', function() {
				$(this).addClass('ui-state-hover');
				$('input#<?php print $form_name;?>_input').addClass('ui-state-hover');
			}).on('mouseleave', function() {
				$(this).removeClass('ui-state-hover');
				$('input#<?php print $form_name;?>_input').removeClass('ui-state-hover');
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
   @param string $on_change - specify a javascript onchange action
   @param string $title - specify a title for the checkbox on hover
   @param boolean $show_label - show the form caption in the checkbox
*/
function form_checkbox($form_name, $form_previous_value, $form_caption, $form_default_value, $current_id = 0, $class = '', $on_change = '', $title = '', $show_label = false) {
	if (($form_previous_value == '') && (empty($current_id))) {
		$form_previous_value = $form_default_value;
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if ($class != '') {
		$class = ' ' . trim($class);
	}

	if ($on_change != '') {
		$on_change = " onChange='$on_change'";
	}

	if ($form_previous_value == 'on') {
		$checked = " checked aria-checked='true'";
	} else {
		$checked = " aria-checked='false'";
	}

	$labelClass = '';
	if ($show_label) {
		$labelClass = ' checkboxLabelWanted';
	}

	print "<span class='nowrap'>";
	print "<label class='checkboxSwitch' " . ($title != '' ? " title='" . html_escape($title) . "'":'') . '><input ' . ($title != '' ? " title='" . html_escape($title) . "'":'') . " class='formCheckbox$class' type='checkbox' id='$form_name' name='$form_name'" . $on_change . $checked . "><span class='checkboxSlider checkboxRound'></span></label>";
	print "<label class='checkboxLabel$labelClass' for='$form_name'>" . html_escape($form_caption) . '</label>';
	print '</span>';
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

	if ($class != '') {
		$class = " $class";
	}

	if ($on_change != '') {
		$on_change = " onChange='$on_change' ";
	}

	if ($form_previous_value == $form_current_value) {
		$checked = " checked aria-checked='true'";
	} else {
		$checked = " aria-checked='false'";
	}

	$css_id = $form_name . '_' . $form_current_value;


	print "<span class='nowrap'>";
	print "<label class='radioSwitch'><input value='" . html_escape($form_current_value) .
		"' class='formCheckbox$class' type='radio' id='$css_id' name='$form_name'" .
		$on_change . $checked . "><span class='radioSlider radioRound'></span></label>";
	print "<label class='radioLabelWanted' for='$css_id'>" . html_escape($form_caption) . "</label>";
	print "</span>";
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
			$class .= ($class != '' ? ' ':'') . 'txtErrorTextBox';
			unset($_SESSION['sess_error_fields'][$form_name]);
		}
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if ($on_change != '') {
		$on_change = " onChange='$on_change' ";
	}

	if ($placeholder != '') {
		$placeholder = " placeholder='" . html_escape($placeholder) . "'";
	}

	print "<textarea class='$class ui-state-default ui-corner-all' aria-multiline='true' cols='$form_columns' rows='$form_rows' id='$form_name' name='$form_name'" . $on_change . $placeholder . '>' . html_escape($form_previous_value) . "</textarea>\n";
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
	} elseif ($sql_previous_values == '') {
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
			$class .= ($class != '' ? ' ':'') . 'txtErrorTextBox';
			unset($_SESSION['sess_error_fields'][$form_name]);
		}
	}

	$class = 'multiselect';
	if ($class != '') {
		$class .= " $class";
	}

	if ($on_change != '') {
		$on_change = " onChange='$on_change' ";
	}

	print "<select style='height:20px;' size='1' class='$class' id='$form_name' name='$form_name" . "[]' multiple>\n";

	foreach (array_keys($array_display) as $id) {
		print "<option value='" . $id . "'";

		if (is_array($sql_previous_values) && cacti_sizeof($sql_previous_values)) {
			for ($i=0; ($i < cacti_count($sql_previous_values)); $i++) {
				if ($sql_previous_values[$i][$column_id] == $id) {
					print ' selected';
				}
			}
		}

		print '>' . html_escape($array_display[$id]);
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

	if ($class != '') {
		$class = " class='colordropdown $class' ";
	} else {
		$class = " class='colordropdown'";
	}

	$current_color = db_fetch_cell_prepared('SELECT hex
		FROM colors
		WHERE id = ?',
		array($form_previous_value));

	if ($on_change != '') {
		$on_change = ' ' . $on_change . ';';
	}

	$on_change = " onChange='this.style.backgroundColor=this.options[this.selectedIndex].style.backgroundColor;$on_change'";

	$colors_sql = 'SELECT *
		FROM colors
		ORDER BY
			SUBSTRING(hex,0,2) ASC,
			SUBSTRING(hex,2,2) ASC,
			SUBSTRING(hex,4,2) ASC';

	$colors_list = db_fetch_assoc($colors_sql);

	print "<select style='background-color: #$current_color;' id='$form_name' name='$form_name'" . $class . $on_change . ">";

	if ($form_none_entry != '') {
		print "<option value='0'>$form_none_entry</option>";
	}

	if (cacti_sizeof($colors_list)) {
		foreach ($colors_list as $color) {
			if ($color['name'] == '') {
				$display = __('Cacti Color (%s)', $color['hex']);
			} else {
				$display = $color['name'] . ' (' . $color['hex'] . ')';
			}

			print "<option data-color='" . html_escape($color['hex']) . "' style='background-color: #" . html_escape($color['hex']) . ";' value='" . $color['id'] . "'";

			if ($form_previous_value == $color['id']) {
				print ' selected';
			}

			print '>' . html_escape($display) . '</option>';
		}
	}

	print '</select>';
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
			print " class='ui-state-default ui-corner-all txtErrorTextBox'";
			unset($_SESSION['sess_error_fields'][$form_name]);
		} else {
			print " class='ui-state-default ui-corner-all'";
		}
	}

	if (isset($_SESSION['sess_field_values'])) {
		if (!empty($_SESSION['sess_field_values'][$form_name])) {
			$form_previous_value = $_SESSION['sess_field_values'][$form_name];
		}
	}

	if ($form_previous_value == '') { # no data: defaults are used; everything is fine
		$extra_data = '';
	} else {
		/* verifying all possible pango font params is too complex to be tested here
		 * so we only escape the font
		 */
		$extra_data = "<span style='color:green'><br>[" . __('NO FONT VERIFICATION POSSIBLE') . ']</span>';
	}

	print " id='$form_name' " . ($placeholder != '' ? "placeholder='" . html_escape($placeholder) . "'":'') . " name='$form_name' size='$form_size'" . (!empty($form_max_length) ? " maxlength='$form_max_length'" : '') . " value='" . html_escape($form_previous_value) . "'>" . $extra_data;
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
		<td class='right'>
			<input type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo("<?php print html_escape($config['url_path'] . $cancel_url);?>")' value='<?php print __esc('Cancel');?>'>
			<input type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo("<?php print html_escape($config['url_path'] . $action_url . '&confirm=true');?>")' value='<?php print __esc('Delete');?>'>
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
			$alt = __esc('Create');
		} else {
			$alt = __esc('Save');

			if ($force_type != '') {
				$calt   = __esc('Return');
			} else {
				$calt   = __esc('Cancel');
			}
		}
	} elseif ($force_type == 'save') {
		$alt = __esc('Save');
	} elseif ($force_type == 'create') {
		$alt = __esc('Create');
	} elseif ($force_type == 'close') {
		$alt = __esc('Close');
	} elseif ($force_type == 'import') {
		$alt = __esc('Import');
	} elseif ($force_type == 'export') {
		$alt = __esc('Export');
	}

	if ($force_type != 'import' && $force_type != 'export' && $force_type != 'save' && $force_type != 'close' && $cancel_url != '') {
		$cancel_action = "<input type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo(\"" . html_escape(sanitize_uri($cancel_url)) . "\")' value='" . $calt . "'>";
	} else {
		$cancel_action = '';
	}

	?>
	<table style='width:100%;text-align:center;'>
		<tr>
			<td class='saveRow'>
				<input type='hidden' name='action' value='save'>
				<?php print $cancel_action;?>
				<input type='submit' class='<?php print $force_type;?> ui-button ui-corner-all ui-widget' id='submit' value='<?php print $alt;?>'>
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
					print "<input type='button' class='ui-button ui-corner-all ui-widget' id='" . $b['id'] . "' value='" . html_escape($b['value']) . "'>\n";
				} ?>
			</td>
		</tr>
	</table>
	<?php
}

/* form_start - draws post form start. To be combined with form_end()
   @arg $action - a mandatory php file URI
   @arg $id     - an optional id, if empty, one will be generated */
function form_start($action, $id = '', $multipart = false) {
	global $form_id, $form_action;
	static $counter = 1;

	if ($id == '') {
		$form_id = 'form' . $counter;
		$counter++;
	} else {
		$form_id = trim($id);
	}

	$form_action = $action;

	print "<form class='cactiFormStart' id='$form_id' name='$form_id' action='$form_action' autocomplete='off' method='post'" . ($multipart ? " enctype='multipart/form-data'":'') . ">\n";
}

/* form_end - draws post form end. To be combined with form_start() */
function form_end($ajax = true) {
	global $form_id, $form_action;

	print '</form>' . PHP_EOL;

	if ($ajax) { ?>
		<script type='text/javascript'>
		var formArray = [];
		var changed = false;

		function warningMessage(href, type, scroll_or_id) {
			title='<?php print __esc('Warning Unsaved Form Data');?>';
			returnStr = '<div id="messageContainer" style="display:none">' +
				'<h4><?php print __('Unsaved Changes Detected');?></h4>' +
				'<p style="display:table-cell;overflow:auto"><?php print __('You have unsaved changes on this form.  If you press &#39;Continue&#39; these changes will be discarded.  Press &#39;Cancel&#39; to continue editing the form.');?></p>' +
				'</div>';

			$('#messageContainer').remove();
			$('body').append(returnStr);

			var messageButtons = {
				'Cancel': {
					text: sessionMessageCancel,
					id: 'messageCancel',
					click: function() {
						$(this).dialog('close');
						$('#messageContainer').remove();
					}
				},
				'Continue': {
					text: sessionMessageContinue,
					id: 'messageContinue',
					click: function() {
						$('#messageContainer').remove();

						if (type == 'noheader') {
							loadPageNoHeader(href, scroll_or_id, true);
						} else if (type == 'toptab') {
							loadTopTab(href, scroll_or_id, true);
						} else {
							loadPage(href, true);
						}
					}
				}
			};

			messageWidth = $(window).width();
			if (messageWidth > 600) {
				messageWidth = 600;
			} else {
				messageWidth -= 50;
			}

			$('#messageContainer').dialog({
				draggable: true,
				resizable: false,
				height: 'auto',
				minWidth: messageWidth,
				maxWidth: 800,
				maxHeight: 600,
				title: title,
				buttons: messageButtons
			});
		}

		$(function() {
			formArray['<?php print $form_id;?>'] = $('#<?php print $form_id;?>').serializeForm();
			changed = false;

			$('#<?php print $form_id;?>').submit(function(event) {
				event.preventDefault();

				// Enable the form if it's disabled
				$(this).find('input, textarea, select').prop('disabled', false);

				strURL  = '<?php print $form_action;?>';
				strURL += (strURL.indexOf('?') >= 0 ? '&':'?') + 'header=false';

				json =  $(this).serializeObject();
				$.post(strURL, json).done(function(data) {
					checkForLogout(data);
					var htmlObject  = $(data);
					var matches     = data.match(/<title>(.*?)<\/title>/);

					if (matches != null) {
						var htmlTitle   = matches[1];
						var breadCrumbs = htmlObject.find('#breadcrumbs').html();
						var data        = htmlObject.find('#main').html();

						$('#main').empty().hide();
						$('title').text(htmlTitle);
						$('#breadcrumbs').html(breadCrumbs);
						$('div[class^="ui-"]').remove();
						$('#main').html(data);
						applySkin();
					} else {
						$('#main').empty().hide().html(data);
						applySkin();
					}

					if (isMobile.any() != null) {
						window.scrollTo(0,1);
					} else {
						window.scrollTo(0,0);
					}
				});
			});
		});
		</script>
		<?php
	}
}

