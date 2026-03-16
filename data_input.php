<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

require('./include/auth.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/template.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

$actions = [
	1 => __('Delete'),
	2 => __('Duplicate')
];

// set default action
set_default_action();

switch (grv('action')) {
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

		header('Location: data_input.php?action=edit&id=' . gfrv('data_input_id'));

		break;
	case 'field_edit':
		top_header();

		field_edit();

		bottom_footer();

		break;
	case 'edit':
		top_header();

		data_input_edit();

		bottom_footer();

		break;
	default:
		top_header();

		data();

		bottom_footer();

		break;
}

/**
 * form_save - Saves the data input method
 */
function form_save() : void {
	global $registered_cacti_names;

	if (isrv('save_component_data_input')) {
		// ================= input validation =================
		gfrv('id');
		// ====================================================

		$save['id']           = gnrv('id');
		$save['hash']         = get_hash_data_input(gnrv('id'));
		$save['name']         = form_input_validate(gnrv('name'), 'name', '', false, 3);
		$save['input_string'] = form_input_validate(gnrv('input_string'), 'input_string', '', true, 3);
		$save['type_id']      = form_input_validate(gnrv('type_id'), 'type_id', '^[0-9]+$', true, 3);

		if (!is_error_message()) {
			$data_input_id = sql_save($save, 'data_input');

			if ($data_input_id) {
				data_input_save_message($data_input_id);

				// get a list of each field so we can note their sequence of occurrence in the database
				if (!ierv('id')) {
					db_execute_prepared('UPDATE data_input_fields SET sequence = 0 WHERE data_input_id = ?', [gnrv('id')]);

					generate_data_input_field_sequences(gnrv('input_string'), gnrv('id'));

					update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
				}

				push_out_data_input_method($data_input_id);
			} else {
				raise_message(2);
			}
		}

		header('Location: data_input.php?action=edit&id=' . (empty($data_input_id) ? gnrv('id') : $data_input_id));
	} elseif (isrv('save_component_field')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('data_input_id');
		gfrv('sequence');
		gfrv('input_output', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^(in|out)$/']]);
		// ====================================================

		$save['id']            = grv('id');
		$save['hash']          = get_hash_data_input(gnrv('id'), 'data_input_field');
		$save['data_input_id'] = grv('data_input_id');
		$save['name']          = form_input_validate(gnrv('fname'), 'fname', '', false, 3);
		$save['data_name']     = form_input_validate(gnrv('data_name'), 'data_name', '', false, 3);
		$save['input_output']  = gnrv('input_output');
		$save['update_rra']    = form_input_validate((isrv('update_rra') ? gnrv('update_rra') : ''), 'update_rra', '', true, 3);
		$save['sequence']      = grv('sequence');
		$save['type_code']     = form_input_validate((isrv('type_code') ? gnrv('type_code') : ''), 'type_code', '', true, 3);
		$save['regexp_match']  = form_input_validate((isrv('regexp_match') ? gnrv('regexp_match') : ''), 'regexp_match', '', true, 3);
		$save['allow_nulls']   = form_input_validate((isrv('allow_nulls') ? gnrv('allow_nulls') : ''), 'allow_nulls', '', true, 3);

		if (is_error_message() === false) {
			$data_input_field_id = sql_save($save, 'data_input_fields');

			if ($data_input_field_id) {
				data_input_save_message(grv('data_input_id'), 'field');

				if ($data_input_field_id > 0 && grv('input_output') == 'in') {
					generate_data_input_field_sequences(db_fetch_cell_prepared('SELECT input_string FROM data_input WHERE id = ?', [grv('data_input_id')]), grv('data_input_id'));
				}

				update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
			} else {
				raise_message(2);
			}
		} else {
			$data_input_field_id = 0;
		}

		if (is_error_message()) {
			header('Location: data_input.php?action=field_edit&data_input_id=' . grv('data_input_id') . '&id=' . (empty($data_input_field_id) ? grv('id') : $data_input_field_id) . (!ierv('input_output') ? '&type=' . grv('input_output') : ''));
		} else {
			header('Location: data_input.php?action=edit&id=' . grv('data_input_id'));
		}
	}
}

function data_input_save_message(int $data_input_id, string $type = 'input') : void {
	$counts = db_fetch_row_prepared('SELECT
		SUM(CASE WHEN dtd.local_data_id=0 THEN 1 ELSE 0 END) AS templates,
		SUM(CASE WHEN dtd.local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
		FROM data_input AS di
		LEFT JOIN data_template_data AS dtd
		ON di.id=dtd.data_input_id
		WHERE di.id = ?',
		[$data_input_id]);

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

function form_actions() : void {
	global $actions;

	// ================= input validation =================
	gfrv('drp_action', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-zA-Z0-9_]+)$/']]);
	// ====================================================

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (grv('drp_action') == '1') { // delete
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					api_data_input_remove($selected_items[$i]);
				}
			} elseif (grv('drp_action') == '2') { // duplicate
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					api_data_input_duplicate($selected_items[$i], gnrv('input_title'));
				}
			}
		}

		header('Location: data_input.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		// loop through each of the data inputs and process them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM data_input WHERE id = ?', [$matches[1]])) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'data_input.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following Data Input Method.'),
					'pmessage' => __('Click \'Continue\' to Delete following Data Input Methods.'),
					'scont'    => __('Delete Data Input Method'),
					'pcont'    => __('Delete Data Input Methods')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Duplicate the following Data Input Method.'),
					'pmessage' => __('Click \'Continue\' to Duplicate following Data Input Methods.'),
					'scont'    => __('Duplicate Data Input Method'),
					'pcont'    => __('Duplicate Data Input Methods'),
					'extra'    => [
						'input_title' => [
							'method'  => 'textbox',
							'title'   => __('Input Name'),
							'default' => '<input_title> (1)',
							'width'   => 25
						]
					]
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}

function field_remove_confirm() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('data_input_id');
	// ====================================================

	form_start('data_input.php?action=edit&id' . grv('data_input_id'));

	html_start_box('', '100%', false, 3, 'center', '');

	$field = db_fetch_row_prepared('SELECT *
		FROM data_input_fields
		WHERE id = ?',
		[grv('id')]);

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following Data Input Field.'); ?></p>
			<p><?php print __esc('Field Name: %s', $field['data_name']); ?><br>
			<p><?php print __esc('Friendly Name: %s', $field['name']); ?><br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel' name='cancel'><?php print __esc('Cancel'); ?></button>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='continue' name='continue' title='<?php print __esc('Remove Data Input Field'); ?>'><?php print __esc('Continue'); ?></button>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#continue').unbind('click').click(function(data) {
			var options = {
				url: 'data_input.php?action=field_remove',
				funcEnd: 'removeDataInputFieldFinalize'
			}

			var data = {
				__csrf_magic: csrfMagicToken,
				data_input_id: <?php print grv('data_input_id'); ?>,
				id: <?php print grv('id'); ?>
			}

			postUrl(options, data);
		});
	});

	function removeDataInputFieldFinalize(data) {
		loadUrl({url:'data_input.php?action=edit&id=<?php print grv('data_input_id'); ?>'})
	}

	</script>
	<?php
}

function field_remove() : void {
	global $registered_cacti_names;

	// ================= input validation =================
	gfrv('id');
	gfrv('data_input_id');
	// ====================================================

	// get information about the field we're going to delete so we can re-order the seqs
	$field = db_fetch_row_prepared('SELECT input_output, data_input_id
		FROM data_input_fields
		WHERE id = ?',
		[grv('id')]);

	db_execute_prepared('DELETE FROM data_input_fields WHERE id = ?', [grv('id')]);
	db_execute_prepared('DELETE FROM data_input_data WHERE data_input_field_id = ?', [grv('id')]);

	// when a field is deleted; we need to re-order the field sequences
	if (($field['input_output'] == 'in') && (preg_match_all('/<([_a-zA-Z0-9]+)>/', db_fetch_cell_prepared('SELECT input_string FROM data_input WHERE id = ?', [$field['data_input_id']]), $matches))) {
		$j = 0;

		for ($i = 0; ($i < cacti_count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names, true) == false) {
				$j++;
				db_execute_prepared("UPDATE data_input_fields SET sequence = ? WHERE data_input_id = ? AND input_output = 'in' AND data_name = ?", [$j, $field['data_input_id'], $matches[1][$i]]);
			}
		}
	}

	update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
}

function field_edit() : void {
	global $registered_cacti_names, $fields_data_input_field_edit_1, $fields_data_input_field_edit_2, $fields_data_input_field_edit;

	// ================= input validation =================
	gfrv('id');
	gfrv('data_input_id');
	gfrv('type', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^(in|out)$/']]);
	// ====================================================

	$array_field_names = [];

	if (!ierv('id')) {
		$field = db_fetch_row_prepared('SELECT *
			FROM data_input_fields
			WHERE id = ?',
			[grv('id')]);
	} else {
		$field              = [];
		$current_field_type = '';
	}

	if (!ierv('type')) {
		$current_field_type = grv('type');
	} elseif (cacti_sizeof($field)) {
		$current_field_type = $field['input_output'];
	} else {
		$current_field_type = '';
	}

	$data_input = db_fetch_row_prepared('SELECT type_id, name
		FROM data_input
		WHERE id = ?',
		[grv('data_input_id')]);

	$input_string = db_fetch_cell_prepared('SELECT input_string
		FROM data_input
		WHERE id = ?',
		[!ierv('data_input_id') ? grv('data_input_id') : $field['data_input_id']]);

	// obtain a list of available fields for this given field type (input/output)
	if ($current_field_type == 'in' && (preg_match_all('/<([_a-zA-Z0-9]+)>/', $input_string, $matches))) {
		for ($i = 0; ($i < cacti_count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names, true) == false) {
				$current_field_name                     = $matches[1][$i];
				$array_field_names[$current_field_name] = $current_field_name;

				if (!cacti_sizeof($field)) {
					$field_id = db_fetch_cell_prepared('SELECT id FROM data_input_fields
						WHERE data_name = ?
						AND data_input_id = ?',
						[$current_field_name, gfrv('data_input_id')]);

					if (!$field_id > 0) {
						$field              = [];
						$field['name']      = ucwords($current_field_name);
						$field['data_name'] = $current_field_name;
					}
				}
			}
		}
	}

	// if there are no input fields to choose from, complain
	if ((!cacti_sizeof($array_field_names)) && (isrv('type') ? grv('type') == 'in' : false) && ($data_input['type_id'] == 1)) {
		raise_message('invalid_inputs', __('This script appears to have no input values, therefore there is nothing to add.'), MESSAGE_LEVEL_WARN);
		header('Location: data_input.php?action=edit&id=' . gfrv('data_input_id'));

		exit;
	}

	if ($current_field_type == 'out') {
		$header_name = __esc('Output Fields [edit: %s]', $data_input['name']);
		$dfield      = __('Output Field');
	} elseif ($current_field_type == 'in') {
		$header_name = __esc('Input Fields [edit: %s]', $data_input['name']);
		$dfield      = __('Input Field');
	} else {
		$dfield      = __('Unknown');
		$header_name = '';
	}

	if (isset($field['data_name'])) {
		$dfield .= ' ' . htmle($field['data_name']);
	}

	form_start('data_input.php', 'data_input');

	html_start_box($header_name, '100%', true, 3, 'center', '');

	$form_array = [];

	// field name
	if ((($data_input['type_id'] == 1) || ($data_input['type_id'] == 5)) && ($current_field_type == 'in')) { // script
		$form_array = inject_form_variables($fields_data_input_field_edit_1, $dfield, $array_field_names, $field);
	} elseif ($current_field_type == 'out' || ($data_input['type_id'] != 1 && $data_input['type_id'] != 5)) {
		$form_array = inject_form_variables($fields_data_input_field_edit_2, $dfield, $field);
	}

	// ONLY if the field is an input
	if ($current_field_type == 'in') {
		unset($fields_data_input_field_edit['update_rra']);
	} elseif ($current_field_type == 'out') {
		unset($fields_data_input_field_edit['regexp_match']);
		unset($fields_data_input_field_edit['allow_nulls']);
		unset($fields_data_input_field_edit['type_code']);
	}

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => $form_array + inject_form_variables($fields_data_input_field_edit, $field, $current_field_type, $_REQUEST)
		]
	);

	html_end_box(true, true);

	form_save_button('data_input.php?action=edit&id=' . grv('data_input_id'));
}

function data_input_edit() : void {
	global $fields_data_input_edit;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	if (!ierv('id')) {
		$data_id = get_nonsystem_data_input(grv('id'));

		if ($data_id == 0 || $data_id == null) {
			header('Location: data_input.php');

			return;
		}

		$data_input = db_fetch_row_prepared('SELECT *
			FROM data_input
			WHERE id = ?',
			[grv('id')]);

		$header_label = __esc('Data Input Method [edit: %s]', $data_input['name']);
	} else {
		$data_input = [];

		$header_label = __('Data Input Method [new]');
	}

	if (!defined('CACTI_WHITELIST')) {
		unset($fields_data_input_edit['whitelist_verification']);
	}

	form_start('data_input.php', 'data_input');

	html_start_box($header_label, '100%', true, 3, 'center', '');

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

		if (defined('CACTI_WHITELIST') && isset($data_input['hash'])) {
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
		[
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($fields_data_input_edit, $data_input)
		]
	);

	html_end_box(true, true);

	if (!ierv('id')) {
		if (api_data_input_more_inputs(grv('id'), $data_input['input_string'])) {
			$url = 'data_input.php?action=field_edit&type=in&data_input_id=' . grv('id');
		} else {
			$url = '';
		}

		$display_text = [
			__('Name'),
			__('Friendly Name'),
			__('Field Order')
		];

		html_start_box(__('Input Fields'), '100%', false, 3, 'center', $url);

		html_header($display_text, 2);

		$fields = db_fetch_assoc_prepared("SELECT id, data_name, name, sequence
			FROM data_input_fields
			WHERE data_input_id = ?
			AND input_output = 'in'
			ORDER BY sequence, data_name",
			[grv('id')]);

		$counts = db_fetch_row_prepared('SELECT
			SUM(CASE WHEN dtd.local_data_id=0 THEN 1 ELSE 0 END) AS templates,
			SUM(CASE WHEN dtd.local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
			FROM data_input AS di
			LEFT JOIN data_template_data AS dtd
			ON di.id=dtd.data_input_id
			WHERE di.id = ?',
			[grv('id')]);

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
				form_alternate_row('line' . $i, true);

				$url = 'data_input.php?action=field_edit&id=' . $field['id'] . '&data_input_id=' . grv('id');

				form_selectable_cell(filter_value($field['data_name'], '', $url), $i);

				form_selectable_ecell($field['name'], $i);

				if ($field['sequence'] == '0') {
					$field['sequence'] .= ' ' . __('(Not In Use)');
				}

				form_selectable_cell($field['sequence'], $i);

				form_selectable_cell("<a class='delete deleteMarker ti ti-x' href='" . htmle('data_input.php?action=field_remove_confirm&id=' . $field['id'] . '&data_input_id=' . grv('id')) . "' title='" . __esc('Delete') . "'></a>", $i, '', 'right');

				form_end_row();

				$i++;
			}
		} else {
			print '<tr class="tableRow odd"><td colspan="4"><em>' . __('No Input Fields') . '</em></td></tr>';
		}

		html_end_box();

		$display_text = [
			__('Name'),
			__('Friendly Name'),
			__('Update RRA')
		];

		html_start_box(__('Output Fields'), '100%', false, 3, 'center', 'data_input.php?action=field_edit&type=out&data_input_id=' . grv('id'));

		html_header($display_text, 2);

		$fields = db_fetch_assoc_prepared("SELECT id, name, data_name, update_rra, sequence
			FROM data_input_fields
			WHERE data_input_id = ?
			AND input_output = 'out'
			ORDER BY sequence, data_name",
			[grv('id')]);

		$i = 0;

		if (cacti_sizeof($fields)) {
			foreach ($fields as $field) {
				form_alternate_row('line' . $i, true);

				$url = 'data_input.php?action=field_edit&id=' . $field['id'] . '&data_input_id=' . grv('id');

				form_selectable_cell(filter_value($field['data_name'], '', $url), $i);

				form_selectable_ecell($field['name'], $i);

				form_selectable_cell(html_boolean_friendly($field['update_rra']), $i);

				if ($output_disabled) {
					form_selectable_cell("<a class='deleteMarkerDisabled ti ti-x' href='#' title='" . __esc('Output Fields can not be removed when Data Sources are present') . "'></a>", $i);
				} else {
					$url = htmle('data_input.php?action=field_remove_confirm&id=' . $field['id'] . '&data_input_id=' . grv('id'));

					form_selectable_cell("<a class='delete deleteMarker ti ti-x' href='$url' title='" . __esc('Delete') . "'></a>", $i, '', 'right');
				}

				form_end_row();

				$i++;
			}
		} else {
			print '<tr class="tableRow odd"><td colspan="4"><em>' . __('No Output Fields') . '</em></td></tr>';
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
						title: '<?php print __('Delete Data Input Field'); ?>',
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

function data() : void {
	global $input_types, $actions, $item_rows, $hash_system_data_inputs;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Data Input Methods'), 'data_input.php', 'form_data_input', 'sess_data_input', 'data_input.php?action=edit');

	$pageFilter->rows_label = __('Input Methods');
	$pageFilter->render();

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = 'WHERE (di.name LIKE ' . db_qstr('%' . grv('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' (di.hash NOT IN ("' . implode('","', $hash_system_data_inputs) . '"))';

	$sql_where  = api_plugin_hook_function('data_input_sql_where', $sql_where);

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM data_input AS di
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$data_inputs = db_fetch_assoc("SELECT di.*
		FROM data_input AS di
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('data_input.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 6, __('Input Methods'), 'page', 'main');

	form_start('data_input.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'name'         => ['display' => __('Data Input Name'),    'align' => 'left', 'sort' => 'ASC', 'tip' => __('The name of this Data Input Method.')],
		'id'           => [
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal database ID for this Data Input Method.  Useful when performing automation or debugging.')
		],
		'nosort' => [
			'display' => __('Deletable'),
			'align'   => 'right',
			'tip'     => __('Data Inputs that are in use cannot be Deleted. In use is defined as being referenced either by a Data Source or a Data Template.')
		],
		'data_sources' => [
			'display' => __('Data Sources Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Data Sources that use this Data Input Method.')
		],
		'templates' => [
			'display' => __('Templates Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Data Templates that use this Data Input Method.')
		],
		'type_id' => [
			'display' => __('Data Input Method'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The method used to gather information for this Data Input Method.')
		]
	];

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($data_inputs)) {
		foreach ($data_inputs as $data_input) {
			// hide system types
			if ($data_input['templates'] > 0 || $data_input['data_sources'] > 0) {
				$disabled = true;
			} else {
				$disabled = false;
			}

			form_alternate_row('line' . $data_input['id'], true, $disabled);

			form_selectable_cell(filter_value($data_input['name'], grv('filter'), 'data_input.php?action=edit&id=' . $data_input['id']), $data_input['id']);
			form_selectable_cell($data_input['id'], $data_input['id'], '', 'right');
			form_selectable_cell($disabled ? __('No') : __('Yes'), $data_input['id'], '', 'right');
			form_selectable_cell(number_format_i18n($data_input['data_sources'], -1), $data_input['id'],'', 'right');
			form_selectable_cell(number_format_i18n($data_input['templates'], -1), $data_input['id'],'', 'right');
			form_selectable_cell($input_types[$data_input['type_id']], $data_input['id'], '', 'right');

			form_checkbox_cell($data_input['name'], $data_input['id'], $disabled);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Data Input Methods Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($data_inputs)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
