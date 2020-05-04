<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

include('./include/auth.php');
include_once('./lib/api_data_source.php');
include_once('./lib/poller.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');

$di_actions = array(
	1 => __('Delete'),
	2 => __('Duplicate')
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'field_remove_confirm':
		field_remove_confirm();

		break;
	case 'field_remove':
		field_remove();

		header('Location: data_input.php?action=edit&id=' . get_filter_request_var('data_input_id'));
		break;
	case 'field_edit':
		top_header();

		field_edit();

		bottom_footer();
		break;
	case 'edit':
		top_header();

		data_edit();

		bottom_footer();
		break;
	default:
		top_header();

		data();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function duplicate_data_input($_data_input_id, $input_title) {
	$orig_input = db_fetch_row_prepared('SELECT *
		FROM data_input
		WHERE id = ?',
		array($_data_input_id));

	if (cacti_sizeof($orig_input)) {
		unset($save);
		$save['id']           = 0;
		$save['hash']         = get_hash_data_input(0);
		$save['name']         = str_replace('<input_title>', $orig_input['name'], $input_title);
		$save['input_string'] = $orig_input['input_string'];
		$save['type_id']      = $orig_input['type_id'];

		$data_input_id = sql_save($save, 'data_input');

		if (!empty($data_input_id)) {
			$data_input_fields = db_fetch_assoc_prepared('SELECT *
				FROM data_input_fields
				WHERE data_input_id = ?',
				array($_data_input_id));

			if (cacti_sizeof($data_input_fields)) {
				foreach($data_input_fields as $dif) {
					unset($save);
					$save['id']            = 0;
					$save['hash']          = get_hash_data_input(0, 'data_input_field');
					$save['data_input_id'] = $data_input_id;
					$save['name']          = $dif['name'];
					$save['data_name']     = $dif['data_name'];
					$save['input_output']  = $dif['input_output'];
					$save['update_rra']    = $dif['update_rra'];
					$save['sequence']      = $dif['sequence'];
					$save['type_code']     = $dif['type_code'];
					$save['regexp_match']  = $dif['regexp_match'];
					$save['allow_nulls']   = $dif['allow_nulls'];

					$data_input_field_id = sql_save($save, 'data_input_fields');
				}
			}
		}
	}
}

function form_save() {
	global $registered_cacti_names;

	if (isset_request_var('save_component_data_input')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		/* ==================================================== */

		$save['id']           = get_nfilter_request_var('id');
		$save['hash']         = get_hash_data_input(get_nfilter_request_var('id'));
		$save['name']         = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['input_string'] = form_input_validate(get_nfilter_request_var('input_string'), 'input_string', '', true, 3);
		$save['type_id']      = form_input_validate(get_nfilter_request_var('type_id'), 'type_id', '^[0-9]+$', true, 3);

		if (!is_error_message()) {
			$data_input_id = sql_save($save, 'data_input');

			if ($data_input_id) {
				data_input_save_message($data_input_id);

				/* get a list of each field so we can note their sequence of occurrence in the database */
				if (!isempty_request_var('id')) {
					db_execute_prepared('UPDATE data_input_fields SET sequence = 0 WHERE data_input_id = ?', array(get_nfilter_request_var('id')));

					generate_data_input_field_sequences(get_nfilter_request_var('input_string'), get_nfilter_request_var('id'));

					update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
				}

				push_out_data_input_method($data_input_id);
			} else {
				raise_message(2);
			}
		}

		header('Location: data_input.php?action=edit&id=' . (empty($data_input_id) ? get_nfilter_request_var('id') : $data_input_id));
	} elseif (isset_request_var('save_component_field')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('data_input_id');
		get_filter_request_var('sequence');
		get_filter_request_var('input_output', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^(in|out)$/')));
		/* ==================================================== */

		$save['id']            = get_request_var('id');
		$save['hash']          = get_hash_data_input(get_nfilter_request_var('id'), 'data_input_field');
		$save['data_input_id'] = get_request_var('data_input_id');
		$save['name']          = form_input_validate(get_nfilter_request_var('fname'), 'fname', '', false, 3);
		$save['data_name']     = form_input_validate(get_nfilter_request_var('data_name'), 'data_name', '', false, 3);
		$save['input_output']  = get_nfilter_request_var('input_output');
		$save['update_rra']    = form_input_validate((isset_request_var('update_rra') ? get_nfilter_request_var('update_rra') : ''), 'update_rra', '', true, 3);
		$save['sequence']      = get_request_var('sequence');
		$save['type_code']     = form_input_validate((isset_request_var('type_code') ? get_nfilter_request_var('type_code') : ''), 'type_code', '', true, 3);
		$save['regexp_match']  = form_input_validate((isset_request_var('regexp_match') ? get_nfilter_request_var('regexp_match') : ''), 'regexp_match', '', true, 3);
		$save['allow_nulls']   = form_input_validate((isset_request_var('allow_nulls') ? get_nfilter_request_var('allow_nulls') : ''), 'allow_nulls', '', true, 3);

		if (!is_error_message()) {
			$data_input_field_id = sql_save($save, 'data_input_fields');

			if ($data_input_field_id) {
				data_input_save_message(get_request_var('data_input_id'), 'field');

				if ((!empty($data_input_field_id)) && (get_request_var('input_output') == 'in')) {
					generate_data_input_field_sequences(db_fetch_cell_prepared('SELECT input_string FROM data_input WHERE id = ?', array(get_request_var('data_input_id'))), get_request_var('data_input_id'));
				}

				update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: data_input.php?action=field_edit&data_input_id=' . get_request_var('data_input_id') . '&id=' . (empty($data_input_field_id) ? get_request_var('id') : $data_input_field_id) . (!isempty_request_var('input_output') ? '&type=' . get_request_var('input_output') : ''));
		} else {
			header('Location: data_input.php?action=edit&id=' . get_request_var('data_input_id'));
		}
	}
}

function data_input_save_message($data_input_id, $type = 'input') {
	$counts = db_fetch_row_prepared("SELECT
		SUM(CASE WHEN dtd.local_data_id=0 THEN 1 ELSE 0 END) AS templates,
		SUM(CASE WHEN dtd.local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
		FROM data_input AS di
		LEFT JOIN data_template_data AS dtd
		ON di.id=dtd.data_input_id
		WHERE di.id = ?",
		array($data_input_id));

	if ($counts['templates'] == 0 && $counts['data_sources'] == 0) {
		raise_message(1);
	} elseif ($counts['templates'] > 0 && $counts['data_sources'] == 0) {
		if ($type == 'input') {
			raise_message('input_save_wo_ds');
		} else {
			raise_message('input_field_save_wo_ds');
		}
	} else {
		if ($type == 'input') {
			raise_message('input_save_w_ds');
		} else {
			raise_message('input_field_save_w_ds');
		}
	}
}

function form_actions() {
	global $di_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_request_var('drp_action') == '1') { // delete
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					data_remove($selected_items[$i]);
				}
			} elseif (get_request_var('drp_action') == '2') { // duplicate
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					duplicate_data_input($selected_items[$i], get_nfilter_request_var('input_title'));
				}
			}
		}

		header('Location: data_input.php');
		exit;
	}

	/* setup some variables */
	$di_list = ''; $i = 0;

	/* loop through each of the data inputs and process them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$di_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM data_input WHERE id = ?', array($matches[1]))) . '</li>';
			$di_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('data_input.php');

	html_start_box($di_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($di_array) && cacti_sizeof($di_array)) {
		if (get_request_var('drp_action') == '1') { // delete
			$graphs = array();

			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to delete the following Data Input Method', 'Click \'Continue\' to delete the following Data Input Method', cacti_sizeof($di_array)) . "</p>
					<div class='itemlist'><ul>$di_list</ul></div>
				</td>
			</tr>\n";
		} elseif (get_request_var('drp_action') == '2') { // duplicate
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to duplicate the following Data Input Method(s). You can optionally change the title format for the new Data Input Method(s).') . "</p>
                    <div class='itemlist'><ul>$di_list</ul></div>
                    <p><strong>" . __('Input Name:'). "</strong><br>"; form_text_box('input_title', '<input_title> (1)', '', '255', '30', 'text'); print "</p>
                </td>
			</tr>\n";
		}

		$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Delete Data Input Method', 'Delete Data Input Methods', cacti_sizeof($di_array)) . "'>";
	} else {
		raise_message(40);
		header('Location: data_input.php');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($di_array) ? serialize($di_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* --------------------------
    CDEF Item Functions
   -------------------------- */

function field_remove_confirm() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('data_input_id');
	/* ==================================================== */

	form_start('data_input.php?action=edit&id' . get_request_var('data_input_id'));

	html_start_box('', '100%', '', '3', 'center', '');

	$field = db_fetch_row_prepared('SELECT *
		FROM data_input_fields
		WHERE id = ?',
		array(get_request_var('id')));

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following Data Input Field.');?></p>
			<p><?php print __esc('Field Name: %s', $field['data_name']);?><br>
			<p><?php print __esc('Friendly Name: %s', $field['name']);?><br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='<?php print __esc('Cancel');?>' name='cancel'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='continue' value='<?php print __esc('Continue');?>' name='continue' title='<?php print __esc('Remove Data Input Field');?>'>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#continue').unbind('click').click(function(data) {
			$.post('data_input.php?action=field_remove', {
				__csrf_magic: csrfMagicToken,
				data_input_id: <?php print get_request_var('data_input_id');?>,
				id: <?php print get_request_var('id');?>
			}, function(data) {
				loadUrl({url:'data_input.php?action=edit&id=<?php print get_request_var('data_input_id');?>'})
			});
		});
	});
	</script>
	<?php
}

function field_remove() {
	global $registered_cacti_names;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('data_input_id');
	/* ==================================================== */

	/* get information about the field we're going to delete so we can re-order the seqs */
	$field = db_fetch_row_prepared('SELECT input_output,data_input_id FROM data_input_fields WHERE id = ?', array(get_request_var('id')));

	db_execute_prepared('DELETE FROM data_input_fields WHERE id = ?', array(get_request_var('id')));
	db_execute_prepared('DELETE FROM data_input_data WHERE data_input_field_id = ?', array(get_request_var('id')));

	/* when a field is deleted; we need to re-order the field sequences */
	if (($field['input_output'] == 'in') && (preg_match_all('/<([_a-zA-Z0-9]+)>/', db_fetch_cell_prepared('SELECT input_string FROM data_input WHERE id = ?', array($field['data_input_id'])), $matches))) {
		$j = 0;
		for ($i=0; ($i < cacti_count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names) == false) {
				$j++;
				db_execute_prepared("UPDATE data_input_fields SET sequence = ? WHERE data_input_id = ? AND input_output = 'in' AND data_name = ?", array($j, $field['data_input_id'], $matches[1][$i]));
			}
		}
	}

	update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
}

function field_edit() {
	global $registered_cacti_names, $fields_data_input_field_edit_1, $fields_data_input_field_edit_2, $fields_data_input_field_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('data_input_id');
	get_filter_request_var('type', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^(in|out)$/')));
	/* ==================================================== */

	$array_field_names = array();

	if (!isempty_request_var('id')) {
		$field = db_fetch_row_prepared('SELECT *
			FROM data_input_fields
			WHERE id = ?',
			array(get_request_var('id')));
	}

	if (!isempty_request_var('type')) {
		$current_field_type = get_request_var('type');
	} else {
		$current_field_type = $field['input_output'];
	}

	$data_input = db_fetch_row_prepared('SELECT type_id, name
		FROM data_input
		WHERE id = ?',
		array(get_request_var('data_input_id')));

	/* obtain a list of available fields for this given field type (input/output) */
	if (($current_field_type == 'in') && (preg_match_all('/<([_a-zA-Z0-9]+)>/', db_fetch_cell_prepared('SELECT input_string FROM data_input WHERE id = ?', array(!isempty_request_var('data_input_id') ? get_request_var('data_input_id') : $field['data_input_id'])), $matches))) {
		for ($i=0; ($i < cacti_count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names) == false) {
				$current_field_name = $matches[1][$i];
				$array_field_names[$current_field_name] = $current_field_name;
				if (!isset($field)) {
					$field_id = db_fetch_cell_prepared('SELECT id FROM data_input_fields
						WHERE data_name = ?
						AND data_input_id = ?',
						array($current_field_name, get_filter_request_var('data_input_id')));
					if (!$field_id > 0) {
						$field = array();
						$field['name'] = ucwords($current_field_name);
						$field['data_name'] = $current_field_name;
					}
				}
			}
		}
	}

	/* if there are no input fields to choose from, complain */
	if ((!isset($array_field_names)) && (isset_request_var('type') ? get_request_var('type') == 'in' : false) && ($data_input['type_id'] == '1')) {
		raise_message('invalid_inputs', __('This script appears to have no input values, therefore there is nothing to add.'), MESSAGE_LEVEL_WARN);
		header('Location: data_input.php?action=edit&id=' . get_filter_request_var('data_input_id'));
		exit;
	}

	if ($current_field_type == 'out') {
		$header_name = __esc('Output Fields [edit: %s]', $data_input['name']);
		$dfield      = __('Output Field');
	} elseif ($current_field_type == 'in') {
		$header_name = __esc('Input Fields [edit: %s]', $data_input['name']);
		$dfield      = __('Input Field');
	}

	if (isset($field)) {
		$dfield .= ' ' . html_escape($field['data_name']);
	}
	form_start('data_input.php', 'data_input');

	html_start_box($header_name, '100%', true, '3', 'center', '');

	$form_array = array();

	/* field name */
	if ((($data_input['type_id'] == '1') || ($data_input['type_id'] == '5')) && ($current_field_type == 'in')) { /* script */
		$form_array = inject_form_variables($fields_data_input_field_edit_1, $dfield, $array_field_names, (isset($field) ? $field : array()));
	} elseif ($current_field_type == 'out' || ($data_input['type_id'] != 1 && $data_input['type_id'] != 5)) {
		$form_array = inject_form_variables($fields_data_input_field_edit_2, $dfield, (isset($field) ? $field : array()));
	}

	/* ONLY if the field is an input */
	if ($current_field_type == 'in') {
		unset($fields_data_input_field_edit['update_rra']);
	} elseif ($current_field_type == 'out') {
		unset($fields_data_input_field_edit['regexp_match']);
		unset($fields_data_input_field_edit['allow_nulls']);
		unset($fields_data_input_field_edit['type_code']);
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $form_array + inject_form_variables($fields_data_input_field_edit, (isset($field) ? $field : array()), $current_field_type, $_REQUEST)
		)
	);

	html_end_box(true, true);

	form_save_button('data_input.php?action=edit&id=' . get_request_var('data_input_id'));
}

/* -----------------------
    Data Input Functions
   ----------------------- */

function data_remove($id) {
	$data_input_fields = db_fetch_assoc_prepared('SELECT id
		FROM data_input_fields
		WHERE data_input_id = ?',
		array($id));

	if (is_array($data_input_fields)) {
		foreach ($data_input_fields as $data_input_field) {
			db_execute_prepared('DELETE FROM data_input_data WHERE data_input_field_id = ?', array($data_input_field['id']));
		}
	}

	db_execute_prepared('DELETE FROM data_input WHERE id = ?', array($id));
	db_execute_prepared('DELETE FROM data_input_fields WHERE data_input_id = ?', array($id));

	update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
	update_replication_crc(0, 'poller_replicate_data_input_crc');
}

function data_input_more_inputs($id, $input_string) {
	$input_string = str_replace('<path_cacti>', '', $input_string);
	$inputs = substr_count($input_string, '<');

	$existing = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM data_input_fields
		WHERE data_input_id = ?
		AND input_output = "in"',
		array($id));

	if ($inputs > $existing) {
		return true;
	} else {
		return false;
	}
}

function data_edit() {
	global $config, $fields_data_input_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$data_id = get_nonsystem_data_input(get_request_var('id'));
		if ($data_id == 0 || $data_id == NULL) {
			header('Location: data_input.php');
			return;
		}

		$data_input = db_fetch_row_prepared('SELECT *
			FROM data_input
			WHERE id = ?',
			array(get_request_var('id')));

		$header_label = __esc('Data Input Method [edit: %s]', $data_input['name']);
	} else {
		$data_input = array();

		$header_label = __('Data Input Method [new]');
	}

	if (!isset($config['input_whitelist'])) {
		unset($fields_data_input_edit['whitelist_verification']);
	}

	form_start('data_input.php', 'data_input');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	if (cacti_sizeof($data_input)) {
		switch ($data_input['type_id']) {
		case DATA_INPUT_TYPE_SNMP:
			$fields_data_input_edit['type_id']['array'][DATA_INPUT_TYPE_SNMP] = __('SNMP Get');
			break;
		case DATA_INPUT_TYPE_SNMP_QUERY:
			$fields_data_input_edit['type_id']['array'][DATA_INPUT_TYPE_SNMP_QUERY] = __('SNMP Query');
			break;
		case DATA_INPUT_TYPE_SCRIPT_QUERY:
			$fields_data_input_edit['type_id']['array'][DATA_INPUT_TYPE_SCRIPT_QUERY] = __('Script Query');
			break;
		case DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER:
			$fields_data_input_edit['type_id']['array'][DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER] = __('Script Server Query');
			break;
		}

		if (isset($config['input_whitelist']) && isset($data_input['hash'])) {
			$aud = verify_data_input_whitelist($data_input['hash'], $data_input['input_string']);

			if ($aud === true) {
				$fields_data_input_edit['whitelist_verification']['value'] = __('White List Verification Succeeded.');
			} elseif ($aud == false) {
				$fields_data_input_edit['whitelist_verification']['value'] = __('White List Verification Failed.  Run CLI script input_whitelist.php to correct.');
			} elseif ($aud == '-1') {
				$fields_data_input_edit['whitelist_verification']['value'] = __('Input String does not exist in White List.  Run CLI script input_whitelist.php to correct.');
			}
		}
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_data_input_edit, $data_input)
		)
	);

	html_end_box(true, true);

	if (!isempty_request_var('id')) {
		if (data_input_more_inputs(get_request_var('id'), $data_input['input_string'])) {
			$url = 'data_input.php?action=field_edit&type=in&data_input_id=' . get_request_var('id');
		} else {
			$url = '';
		}

		html_start_box(__('Input Fields'), '100%', '', '3', 'center', $url);

		print "<tr class='tableHeader'>";
		DrawMatrixHeaderItem(__('Name'), '', 1);
		DrawMatrixHeaderItem(__('Friendly Name'), '', 1);
		DrawMatrixHeaderItem(__('Field Order'), '', 2);
		print '</tr>';

		$fields = db_fetch_assoc_prepared("SELECT id, data_name, name, sequence
			FROM data_input_fields
			WHERE data_input_id = ?
			AND input_output = 'in'
			ORDER BY sequence, data_name",
			array(get_request_var('id')));

		$counts = db_fetch_row_prepared("SELECT
			SUM(CASE WHEN dtd.local_data_id=0 THEN 1 ELSE 0 END) AS templates,
			SUM(CASE WHEN dtd.local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
			FROM data_input AS di
			LEFT JOIN data_template_data AS dtd
			ON di.id=dtd.data_input_id
			WHERE di.id = ?",
			array(get_request_var('id')));

		$output_disabled  = false;
		$save_alt_message = false;
		if (!cacti_sizeof($counts)) {
			$output_disabled  = false;
			$save_alt_message = false;
		} elseif ($counts['data_sources'] > 0) {
			$output_disabled  = true;
			$save_alt_message = true;
		} elseif ($counts['templates'] > 0) {
			$output_disabled  = false;
			$save_alt_message = true;
		}

		$i = 0;
		if (cacti_sizeof($fields)) {
			foreach ($fields as $field) {
				form_alternate_row('', true);
				?>
				<td>
					<a class="linkEditMain" href="<?php print html_escape('data_input.php?action=field_edit&id=' . $field['id'] . '&data_input_id=' . get_request_var('id'));?>"><?php print html_escape($field['data_name']);?></a>
				</td>
				<td>
					<?php print html_escape($field['name']);?>
				</td>
				<td>
					<?php print $field['sequence']; if ($field['sequence'] == '0') { print ' ' . __('(Not In Use)'); }?>
				</td>
				<td class="right">
					<a class='delete deleteMarker fa fa-times' href='<?php print html_escape('data_input.php?action=field_remove_confirm&id=' . $field['id'] . '&data_input_id=' . get_request_var('id'));?>' title='<?php print __esc('Delete');?>'></a>
				</td>
				<?php
				form_end_row();
			}
		} else {
			print '<tr><td colspan="4"><em>' . __('No Input Fields') . '</em></td></tr>';
		}
		html_end_box();

		html_start_box(__('Output Fields'), '100%', '', '3', 'center', 'data_input.php?action=field_edit&type=out&data_input_id=' . get_request_var('id'));
		print "<tr class='tableHeader'>";
		DrawMatrixHeaderItem(__('Name'),'',1);
		DrawMatrixHeaderItem(__('Friendly Name'),'',1);
		DrawMatrixHeaderItem(__('Update RRA'),'',2);
		print '</tr>';

		$fields = db_fetch_assoc_prepared("SELECT id, name, data_name, update_rra, sequence
			FROM data_input_fields
			WHERE data_input_id = ?
			AND input_output = 'out'
			ORDER BY sequence, data_name",
			array(get_request_var('id')));

		$i = 0;
		if (cacti_sizeof($fields)) {
			foreach ($fields as $field) {
				form_alternate_row('', true);
				?>
				<td>
					<a class='linkEditMain' href='<?php print html_escape('data_input.php?action=field_edit&id=' . $field['id'] . '&data_input_id=' . get_request_var('id'));?>'><?php print html_escape($field['data_name']);?></a>
				</td>
				<td>
					<?php print html_escape($field['name']);?>
				</td>
				<td>
					<?php print html_boolean_friendly($field['update_rra']);?>
				</td>
				<td class='right'>
					<?php if ($output_disabled) {?>
					<a class='deleteMarkerDisabled fa fa-times' href='#' title='<?php print __esc('Output Fields can not be removed when Data Sources are present');?>'></a>
					<?php } else { ?>
					<a class='delete deleteMarker fa fa-times' href='<?php print html_escape('data_input.php?action=field_remove_confirm&id=' . $field['id'] . '&data_input_id=' . get_request_var('id'));?>' title='<?php print __esc('Delete');?>'></a>
					<?php } ?>
				</td>
				<?php
				form_end_row();
			}
		} else {
			print '<tr><td colspan="4"><em>' . __('No Output Fields') . '</em></td></tr>';
		}

		html_end_box();
	}

	form_save_button('data_input.php', 'return');

	?>
	<script type='text/javascript'>

	$(function() {
		$('.cdialog').remove();
		$('#main').append("<div id='cdialog' class='cdialog'></div>");

		$('.delete').unbind().click(function (event) {
			event.preventDefault();

			request = $(this).attr('href');
			$.get(request)
				.done(function(data) {
					$('#cdialog').html(data);

					applySkin();

					$('#cdialog').dialog({
						title: '<?php print __('Delete Data Input Field');?>',
						close: function () { $('.delete').blur(); $('.selectable').removeClass('selected'); },
						modal: false,
						minHeight: 80,
						minWidth: 500
					});
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		}).css('cursor', 'pointer');
	});

	</script>
	<?php
}

function data() {
	global $input_types, $di_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_data_input');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Data Input Methods'), '100%', '', '3', 'center', 'data_input.php?action=edit');

	?>
	<tr class='even noprint'>
		<td class='noprint'>
		<form id='form_data_input' method='get' action='data_input.php'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Input Methods');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'data_input.php';
			strURL += '?filter='+$('#filter').val();
			strURL += '&rows='+$('#rows').val();
			loadUrl({url:strURL})
		}

		function clearFilter() {
			strURL = 'data_input.php?clear=1';
			loadUrl({url:strURL})
		}

		$(function() {
			$('#refresh').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#form_data_input').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});

		</script>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (di.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . " (di.hash NOT IN ('3eb92bb845b9660a7445cf9740726522', 'bf566c869ac6443b0c75d1c32b5a350e', '80e9e4c4191a5da189ae26d0e237f015', '332111d8b54ac8ce939af87a7eac0c06'))";

	$sql_where  = api_plugin_hook_function('data_input_sql_where', $sql_where);

	$total_rows = db_fetch_cell("SELECT count(*)
		FROM data_input AS di
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$data_inputs = db_fetch_assoc("SELECT di.*,
		SUM(CASE WHEN dtd.local_data_id=0 THEN 1 ELSE 0 END) AS templates,
		SUM(CASE WHEN dtd.local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
		FROM data_input AS di
		LEFT JOIN data_template_data AS dtd
		ON di.id=dtd.data_input_id
		$sql_where
		GROUP BY di.id
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('data_input.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 6, __('Input Methods'), 'page', 'main');

	form_start('data_input.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name'         => array('display' => __('Data Input Name'),    'align' => 'left', 'sort' => 'ASC', 'tip' => __('The name of this Data Input Method.')),
		'id' => array(
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal database ID for this Data Input Method.  Useful when performing automation or debugging.')
		),
		'nosort' => array(
			'display' => __('Deletable'),
			'align'   => 'right',
			'tip'     => __('Data Inputs that are in use cannot be Deleted. In use is defined as being referenced either by a Data Source or a Data Template.')
		),
		'data_sources' => array(
			'display' => __('Data Sources Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Data Sources that use this Data Input Method.')
		),
		'templates' => array(
			'display' => __('Templates Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Data Templates that use this Data Input Method.')
		),
		'type_id' => array(
			'display' => __('Data Input Method'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The method used to gather information for this Data Input Method.')
		)
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (cacti_sizeof($data_inputs)) {
		foreach ($data_inputs as $data_input) {
			/* hide system types */
			if ($data_input['templates'] > 0 || $data_input['data_sources'] > 0) {
				$disabled = true;
			} else {
				$disabled = false;
			}
			form_alternate_row('line' . $data_input['id'], true, $disabled);
			form_selectable_cell(filter_value($data_input['name'], get_request_var('filter'), 'data_input.php?action=edit&id=' . $data_input['id']), $data_input['id']);
			form_selectable_cell($data_input['id'], $data_input['id'], '', 'right');
			form_selectable_cell($disabled ? __('No'):__('Yes'), $data_input['id'], '', 'right');
			form_selectable_cell(number_format_i18n($data_input['data_sources'], '-1'), $data_input['id'],'', 'right');
			form_selectable_cell(number_format_i18n($data_input['templates'], '-1'), $data_input['id'],'', 'right');
			form_selectable_cell($input_types[$data_input['type_id']], $data_input['id'], '', 'right');
			form_checkbox_cell($data_input['name'], $data_input['id'], $disabled);
			form_end_row();
		}
	} else {
		print '<tr class="tableRow"><td colspan="' . (cacti_sizeof($display_text)+1) . '"><em>' . __('No Data Input Methods Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($data_inputs)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($di_actions);

	form_end();
}

