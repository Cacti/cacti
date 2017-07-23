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

include('./include/auth.php');
include_once('./lib/utility.php');

$di_actions = array(
	1 => __('Delete')
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

		header('Location: data_input.php?header=false&action=edit&id=' . get_filter_request_var('data_input_id'));
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
				raise_message(1);

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

		header('Location: data_input.php?header=false&action=edit&id=' . (empty($data_input_id) ? get_nfilter_request_var('id') : $data_input_id));
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
		$save['name']          = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
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
				raise_message(1);

				if ((!empty($data_input_field_id)) && (get_request_var('input_output') == 'in')) {
					generate_data_input_field_sequences(db_fetch_cell_prepared('SELECT input_string FROM data_input WHERE id = ?', array(get_request_var('data_input_id'))), get_request_var('data_input_id'));
				}

				update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: data_input.php?header=false&action=field_edit&data_input_id=' . get_request_var('data_input_id') . '&id=' . (empty($data_input_field_id) ? get_request_var('id') : $data_input_field_id) . (!isempty_request_var('input_output') ? '&type=' . get_request_var('input_output') : ''));
		} else {
			header('Location: data_input.php?header=false&action=edit&id=' . get_request_var('data_input_id'));
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
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				for ($i=0;($i<count($selected_items));$i++) {
					data_remove($selected_items[$i]);
				}
			}
		}

		header('Location: data_input.php?header=false');
		exit;
	}

	/* setup some variables */
	$di_list = ''; $i = 0;

	/* loop through each of the data queries and process them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$di_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM data_input WHERE id = ?', array($matches[1]))) . '</li>';
			$di_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('data_input.php');

	html_start_box($di_actions{get_nfilter_request_var('drp_action')}, '60%', '', '3', 'center', '');

	if (isset($di_array) && sizeof($di_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			$graphs = array();

			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to delete the following Data Input Method', 'Click \'Continue\' to delete the following Data Input Method', sizeof($di_array)) . "</p>
					<div class='itemlist'><ul>$di_list</ul></div>
				</td>
			</tr>\n";
		}

		$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __n('Delete Data Input Method', 'Delete Data Input Methods', sizeof($di_array)) . "'>";
	} else {
		print "<tr><td class='odd'><span class='textError'>" . __('You must select at least one data input method.') . "</span></td></tr>\n";
		$save_html = "<input type='button' value='" . __esc('Return') . "' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($di_array) ? serialize($di_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
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

	form_start('data_intput.php?action=edit&id' . get_request_var('data_input_id'));

	html_start_box('', '100%', '', '3', 'center', '');

	$field = db_fetch_row_prepared('SELECT * FROM data_input_fields WHERE id = ?', array(get_request_var('id')));

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following Data Input Field.');?></p>
			<p><?php print __('Field Name: %s', $field['data_name']);?>'<br>
			<p><?php print __('Friendly Name: %s', $field['name']);?>'<br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<input id='cancel' type='button' value='<?php print __esc('Cancel');?>' onClick='$("#cdialog").dialog("close")' name='cancel'>
			<input id='continue' type='button' value='<?php print __esc('Continue');?>' name='continue' title='<?php print __esc('Remove Data Input Field');?>'>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#cdialog').dialog();

		$('#continue').click(function(data) {
			$.post('data_input.php?action=field_remove', {
				__csrf_magic: csrfMagicToken,
				data_input_id: <?php print get_request_var('data_input_id');?>,
				id: <?php print get_request_var('id');?>
			}, function(data) {
				$('#cdialog').dialog('close');
				loadPageNoHeader('data_input.php?action=edit&header=false&id=<?php print get_request_var('data_input_id');?>');
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
		for ($i=0; ($i < count($matches[1])); $i++) {
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

	if (!isempty_request_var('id')) {
		$field = db_fetch_row_prepared('SELECT * FROM data_input_fields WHERE id = ?', array(get_request_var('id')));
	}

	if (!isempty_request_var('type')) {
		$current_field_type = get_request_var('type');
	} else {
		$current_field_type = $field['input_output'];
	}

	$data_input = db_fetch_row_prepared('SELECT type_id, name FROM data_input WHERE id = ?', array(get_request_var('data_input_id')));

	/* obtain a list of available fields for this given field type (input/output) */
	if (($current_field_type == 'in') && (preg_match_all('/<([_a-zA-Z0-9]+)>/', db_fetch_cell_prepared('SELECT input_string FROM data_input WHERE id = ?', array(!isempty_request_var('data_input_id') ? get_request_var('data_input_id') : $field['data_input_id'])), $matches))) {
		for ($i=0; ($i < count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names) == false) {
				$current_field_name = $matches[1][$i];
				$array_field_names[$current_field_name] = $current_field_name;
			}
		}
	}

	/* if there are no input fields to choose from, complain */
	if ((!isset($array_field_names)) && (isset_request_var('type') ? get_request_var('type') == 'in' : false) && ($data_input['type_id'] == '1')) {
		display_custom_error_message('This script appears to have no input values, therefore there is nothing to add.');
		return;
	}

	if ($current_field_type == 'out') {
		$header_name = __('Output Fields [edit: %s]', htmlspecialchars($data_input['name']));
		$dfield      = __('Output Field');
	} elseif ($current_field_type == 'in') {
		$header_name = __('Input Fields [edit: %s]', htmlspecialchars($data_input['name']));
		$dfield      = __('Input Field');
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
	$data_input_fields = db_fetch_assoc_prepared('SELECT id FROM data_input_fields WHERE data_input_id = ?', array($id));

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

function data_edit() {
	global $fields_data_input_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$data_input = db_fetch_row_prepared('SELECT * FROM data_input WHERE id = ?', array(get_request_var('id')));
		$header_label = __('Data Input Methods [edit: %s]', htmlspecialchars($data_input['name']));
	} else {
		$header_label = __('Data Input Methods [new]');
	}

	form_start('data_input.php', 'data_input');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	if (isset($data_input)) {
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
			$fields_data_input_edit['type_id']['array'][DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER] = __('Script Query - Script Server');
			break;
		}
	}

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_data_input_edit, (isset($data_input) ? $data_input : array()))
		));

	html_end_box(true, true);

	if (!isempty_request_var('id')) {
		html_start_box( __('Input Fields'), '100%', '', '3', 'center', 'data_input.php?action=field_edit&type=in&data_input_id=' . htmlspecialchars(get_request_var('id')));
		print "<tr class='tableHeader'>";
			DrawMatrixHeaderItem( __('Name'),'',1);
			DrawMatrixHeaderItem( __('Field Order'),'',1);
			DrawMatrixHeaderItem( __('Friendly Name'),'',2);
		print '</tr>';

		$fields = db_fetch_assoc_prepared("SELECT id, data_name, name, sequence FROM data_input_fields WHERE data_input_id = ? AND input_output = 'in' ORDER BY sequence, data_name", array(get_request_var('id')));

		$i = 0;
		if (sizeof($fields) > 0) {
			foreach ($fields as $field) {
				form_alternate_row('', true);
					?>
					<td>
						<a class="linkEditMain" href="<?php print htmlspecialchars('data_input.php?action=field_edit&id=' . $field['id'] . '&data_input_id=' . get_request_var('id'));?>"><?php print htmlspecialchars($field['data_name']);?></a>
					</td>
					<td>
						<?php print $field['sequence']; if ($field['sequence'] == '0') { print ' ' . __('(Not In Use)'); }?>
					</td>
					<td>
						<?php print htmlspecialchars($field['name']);?>
					</td>
					<td class="right">
						<a class='delete deleteMarker fa fa-remove' href='<?php print htmlspecialchars('data_input.php?action=field_remove_confirm&id=' . $field['id'] . '&data_input_id=' . get_request_var('id'));?>' title='<?php print __esc('Delete');?>'></a>
					</td>
					<?php
				form_end_row();
			}
		} else {
			print '<tr><td><em>' . __('No Input Fields') . '</em></td></tr>';
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
		if (sizeof($fields) > 0) {
			foreach ($fields as $field) {
				form_alternate_row('', true);
				?>
					<td>
						<a class='linkEditMain' href='<?php print htmlspecialchars('data_input.php?action=field_edit&id=' . $field['id'] . '&data_input_id=' . get_request_var('id'));?>'><?php print htmlspecialchars($field['data_name']);?></a>
					</td>
					<td>
						<?php print htmlspecialchars($field['name']);?>
					</td>
					<td>
						<?php print html_boolean_friendly($field['update_rra']);?>
					</td>
					<td class='right'>
						<a class='delete deleteMarker fa fa-remove' href='<?php print htmlspecialchars('data_input.php?action=field_remove_confirm&id=' . $field['id'] . '&data_input_id=' . get_request_var('id'));?>' title='<?php print __esc('Delete');?>'></a>
					</td>
				<?php
				form_end_row();
			}
		} else {
			print '<tr><td><em>' . __('No Output Fields') . '</em></td></tr>';
		}

		html_end_box();
	}

	form_save_button('data_input.php', 'return');

	?>
	<script type='text/javascript'>

	$(function() {
		$('body').append("<div id='cdialog'></div>");

		$('.delete').click(function (event) {
			event.preventDefault();

			request = $(this).attr('href');
			$.get(request, function(data) {
				$('#cdialog').html(data);
				applySkin();
				$('#cdialog').dialog({
					title: '<?php print __('Delete Data Input Field');?>',
					close: function () { $('.delete').blur(); $('.selectable').removeClass('selected'); },
					minHeight: 80,
					minWidth: 500
				});
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
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
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

	html_start_box( __('Data Input Methods'), '100%', '', '3', 'center', 'data_input.php?action=edit');

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
						<input id='filter' type='text' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Input Methods');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' id='refresh' value='<?php print __esc('Go');?>' title='<?php __esc('Set/Refresh Filters');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>' title='<?php __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'data_input.php?header=false';
			strURL += '&filter='+$('#filter').val();
			strURL += '&rows='+$('#rows').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'data_input.php?clear=1&header=false';
			loadPageNoHeader(strURL);
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
		$sql_where = "WHERE (di.name like '%" . get_request_var('filter') . "%')";
	} else {
		$sql_where = '';
	}

	$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . " (di.hash NOT IN ('3eb92bb845b9660a7445cf9740726522', 'bf566c869ac6443b0c75d1c32b5a350e', '80e9e4c4191a5da189ae26d0e237f015', '332111d8b54ac8ce939af87a7eac0c06'))";

	$sql_where  = api_plugin_hook_function('data_input_sql_where', $sql_where);

	$total_rows = db_fetch_cell("SELECT
		count(*)
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
		'nosort'       => array('display' => __('Deletable'),          'align' => 'right', 'tip' => __('Data Inputs that are in use cannot be Deleted. In use is defined as being referenced either by a Data Source or a Data Template.')),
		'data_sources' => array('display' => __('Data Sources Using'), 'align' => 'right', 'sort' => 'DESC', 'tip' => __('The number of Data Sources that use this Data Input Method.')),
		'templates'    => array('display' => __('Templates Using'),    'align' => 'right', 'sort' => 'DESC', 'tip' => __('The number of Data Templates that use this Data Input Method.')),
		'type_id'      => array('display' => __('Data Input Method'),  'align' => 'left', 'sort' => 'ASC', 'tip' => __('The method used to gather information for this Data Input Method.')));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (sizeof($data_inputs)) {
		foreach ($data_inputs as $data_input) {
			/* hide system types */
			if ($data_input['templates'] > 0 || $data_input['data_sources'] > 0) {
				$disabled = true;
			} else {
				$disabled = false;
			}
			form_alternate_row('line' . $data_input['id'], true, $disabled);
			form_selectable_cell(filter_value($data_input['name'], get_request_var('filter'), 'data_input.php?action=edit&id=' . $data_input['id']), $data_input['id']);
			form_selectable_cell($disabled ? __('No'): __('Yes'), $data_input['id'],'', 'text-align:right');
			form_selectable_cell(number_format_i18n($data_input['data_sources'], '-1'), $data_input['id'],'', 'text-align:right');
			form_selectable_cell(number_format_i18n($data_input['templates'], '-1'), $data_input['id'],'', 'text-align:right');
			form_selectable_cell($input_types{$data_input['type_id']}, $data_input['id']);
			form_checkbox_cell($data_input['name'], $data_input['id'], $disabled);
			form_end_row();
		}
	} else {
		print "<tr><td colspan='5'><em>" . __('No Data Input Methods Found') . "</em></td></tr>";
	}

	html_end_box(false);

	if (sizeof($data_inputs)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($di_actions);

	form_end();
}

