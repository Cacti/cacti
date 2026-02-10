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
require_once(CACTI_PATH_LIBRARY . '/api_automation.php');
require_once(CACTI_PATH_LIBRARY . '/snmp.php');

$actions = [
	1 => __('Delete'),
	2 => __('Duplicate'),
	3 => __('Export'),
];

// set default action
set_default_action();

// correct for a cancel button
if (isrv('cancel')) {
	srv('action', '');
}

switch (grv('action')) {
	case 'save':
		if (isrv('save_component_import')) {
			automation_import_process();
		} else {
			form_save();
		}

		break;
	case 'import':
		top_header();
		automation_import();
		bottom_footer();

		break;
	case 'export':
		automation_export();

		break;
	case 'actions':
		form_actions();

		break;
	case 'ajax_dnd':
		automation_snmp_item_dnd();

		break;
	case 'item_movedown':
		gfrv('id');

		automation_snmp_item_movedown();

		header('Location: automation_snmp.php?action=edit&id=' . grv('id'));

		break;
	case 'item_moveup':
		gfrv('id');

		automation_snmp_item_moveup();

		header('Location: automation_snmp.php?action=edit&id=' . grv('id'));

		break;
	case 'item_remove_confirm':
		automation_snmp_item_remove_confirm();

		break;
	case 'item_remove':
		gfrv('id');

		automation_snmp_item_remove();

		header('Location: automation_snmp.php?action=edit&id=' . grv('id'));

		break;
	case 'item_edit':
		top_header();

		automation_snmp_item_edit();

		bottom_footer();

		break;
	case 'edit':
		top_header();

		automation_snmp_edit();

		bottom_footer();

		break;
	default:
		top_header();

		automation_snmp();

		bottom_footer();

		break;
}

function automation_export() : void {
	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		$snmp_option_ids = [];

		if ($selected_items != false) {
			if (cacti_sizeof($selected_items) == 1) {
				$export_data = automation_snmp_option_export($selected_items[0]);
			} else {
				foreach ($selected_items as $id) {
					$snmp_option_ids[] = $id;
				}

				$export_data = automation_snmp_option_export($snmp_option_ids);
			}

			if (cacti_sizeof($export_data)) {
				$export_file_name = $export_data['export_name'];

				header('Content-type: application/json');
				header('Content-Disposition: attachment; filename=' . $export_file_name);

				$output = json_encode($export_data, JSON_PRETTY_PRINT);

				print $output;
			} else {
			}
		}
	}
}

function automation_import() : void {
	$form_data = [
		'import_file' => [
			'friendly_name' => __('Import SNMP Options from Local File'),
			'description'   => __('If the JSON file containing the SNMP Options data is located on your local machine, select it here.'),
			'method'        => 'file',
			'accept'        => '.json'
		],
		'import_text' => [
			'friendly_name' => __('Import SNMP Options from Text'),
			'description'   => __('If you have the JSON file containing the SNMP Options data as text, you can paste it into this box to import it.'),
			'method'        => 'textarea',
			'value'         => '',
			'default'       => '',
			'textarea_rows' => '10',
			'textarea_cols' => '80',
			'class'         => 'textAreaNotes'
		]
	];

	form_start('automation_snmp.php', 'chk', true);

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box(__('Import Results'), '60%', false, 3, 'center', '');

		print '<tr class="tableHeader"><th>' . __('Cacti has Imported the following SNMP Options') . '</th></tr>';

		foreach ($_SESSION['import_debug_info'] as $line) {
			print '<tr><td>' . $line . '</td></tr>';
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box(__('Import SNMP Options'), '60%', false, 3, 'center', '');

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => $form_data
		]
	);

	form_hidden_box('save_component_import', '1', '');

	print "	<tr><td><hr/></td></tr><tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='save'>
			<button type='submit' value='import' title='" . __esc('Import SNMP Options') . "' class='ui-button ui-corner-all ui-widget ui-state-active'>" . __esc('Import') . "</button>
		</td>
		<script type='text/javascript'>
		$(function() {
			Pace.stop();
			clearAllTimeouts();
		});
		</script>
	</tr>";

	form_end(true);

	html_end_box();
}

function automation_import_process() : void {
	$json_data = json_decode(gnrv('import_text'), true);

	$debug_data  = [];
	$return_data = [];

	// If we have text, then we were trying to import text, otherwise we are uploading a file for import
	if (empty($json_data)) {
		$json_data = automation_validate_upload();
	}

	if (is_array($json_data) && cacti_sizeof($json_data) && isset($json_data['snmp'])) {
		foreach ($json_data['snmp'] as $snmp) {
			$return_data += automation_snmp_option_import($snmp);
		}
	}

	if (cacti_sizeof($return_data) && isset($return_data['success'])) {
		foreach ($return_data['success'] as $message) {
			$debug_data[] = '<span class="deviceUp">' . __('NOTE:') . '</span> ' . $message;
			automation_log('NOTE: SNMP Options Import Succeeded!  Message: ' . $message, AUTOMATION_LOG_LOW);
		}
	}

	if (isset($return_data['errors'])) {
		foreach ($return_data['errors'] as $error) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $error;
			automation_log('NOTE: SNMP Options Import Error!  Message: ' . $error, AUTOMATION_LOG_LOW);
		}
	}

	if (isset($return_data['failure'])) {
		foreach ($return_data['failure'] as $message) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $message;
			automation_log('NOTE: Automation SNMP Option Import Failed!  Message: ' . $message, AUTOMATION_LOG_LOW);
		}
	}

	if (cacti_sizeof($debug_data)) {
		$_SESSION['import_debug_info'] = $debug_data;
	}

	header('Location: automation_snmp.php?action=import');

	exit();
}

function form_save() : void {
	if (isrv('save_component_automation_snmp')) {
		// ================= input validation =================
		gfrv('id');
		// ====================================================

		$save['id']     = grv('id');
		$save['hash']   = get_hash_automation(grv('id'), 'automation_snmp');
		$save['name']   = form_input_validate(gnrv('name'), 'name', '', false, 3);

		if (!is_error_message()) {
			$id = sql_save($save, 'automation_snmp');

			if ($id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: automation_snmp.php?action=edit&id=' . (empty($id) ? gnrv('id') : $id));
	} elseif (isrv('save_component_automation_snmp_item')) {
		// ================= input validation =================
		gfrv('item_id');
		gfrv('id');
		// ====================================================

		$save = [];

		$save['id']                   = form_input_validate(grv('item_id'), '', '^[0-9]+$', false, 3);
		$save['hash']                 = get_hash_automation(grv('item_id'), 'automation_snmp_items');
		$save['snmp_id']              = form_input_validate(gnrv('id'), 'snmp_id', '^[0-9]+$', false, 3);
		$save['sequence']             = form_input_validate(gnrv('sequence'), 'sequence', '^[0-9]+$', false, 3);
		$save['snmp_community']       = form_input_validate(gnrv('snmp_community'), 'snmp_community', '', false, 3);
		$save['snmp_version']         = form_input_validate(gnrv('snmp_version'), 'snmp_version', '', false, 3);
		$save['snmp_username']        = form_input_validate(gnrv('snmp_username'), 'snmp_username', '', true, 3);
		$save['snmp_password']        = form_input_validate(gnrv('snmp_password'), 'snmp_password', '', true, 3);
		$save['snmp_auth_protocol']   = form_input_validate(gnrv('snmp_auth_protocol'), 'snmp_auth_protocol', '', true, 3);
		$save['snmp_priv_passphrase'] = form_input_validate(gnrv('snmp_priv_passphrase'), 'snmp_priv_passphrase', '', true, 3);
		$save['snmp_priv_protocol']   = form_input_validate(gnrv('snmp_priv_protocol'), 'snmp_priv_protocol', '', true, 3);
		$save['snmp_context']         = form_input_validate(gnrv('snmp_context'), 'snmp_context', '', true, 3);
		$save['snmp_engine_id']       = form_input_validate(gnrv('snmp_engine_id'), 'snmp_engine_id', '', true, 3);
		$save['snmp_port']            = form_input_validate(gnrv('snmp_port'), 'snmp_port', '^[0-9]+$', false, 3);
		$save['snmp_timeout']         = form_input_validate(gnrv('snmp_timeout'), 'snmp_timeout', '^[0-9]+$', false, 3);
		$save['snmp_retries']         = form_input_validate(gnrv('snmp_retries'), 'snmp_retries', '^[0-9]+$', false, 3);
		$save['max_oids']             = form_input_validate(gnrv('max_oids'), 'max_oids', '^[0-9]+$', false, 3);
		$save['bulk_walk_size']       = form_input_validate(gnrv('bulk_walk_size'), 'bulk_walk_size', '^[\-0-9]+$', false, 3);

		$item_id = null;

		if (!is_error_message()) {
			$item_id = sql_save($save, 'automation_snmp_items');

			if ($item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: automation_snmp.php?action=item_edit&id=' . gnrv('id') . '&item_id=' . ($item_id === null ? gfrv('id') : $item_id));
		} else {
			header('Location: automation_snmp.php?action=edit&id=' . gnrv('id'));
		}
	} else {
		raise_message(2);
		header('Location: automation_snmp.php');
	}
}

function form_actions() : void {
	global $actions;

	// ================= input validation =================
	gfrv('drp_action');
	// ====================================================

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == '1') { // delete
				db_execute('DELETE FROM automation_snmp WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM automation_snmp_items WHERE ' . str_replace('id', 'snmp_id', array_to_sql_or($selected_items, 'id')));
			} elseif (gnrv('drp_action') == '2') { // duplicate
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					automation_duplicate_snmp_option($selected_items[$i], gnrv('name_format'));
				}
			} elseif (gnrv('drp_action') == '3') { // export
				top_header();

				print '<script text="text/javascript">
					function DownloadStart(url) {
						document.getElementById("download_iframe").src = url;
						setTimeout(function() {
							loadUrl({ url: "automation_snmp.php" });
							Pace.stop();
						}, 500);
					}

					$(function() {
						//debugger;
						DownloadStart(\'automation_snmp.php?action=export&selected_items=' . gnrv('selected_items') . '\');
					});
				</script>
				<iframe id="download_iframe" style="display:none;"></iframe>';

				bottom_footer();

				exit;
			}
		}

		header('Location: automation_snmp.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		// loop through each of the graphs selected on the previous page and get more info about them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM automation_snmp WHERE id = ?', [$matches[1]])) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'automation_snmp.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following SNMP Option.'),
					'pmessage' => __('Click \'Continue\' to Delete following SNMP Options.'),
					'scont'    => __('Delete SNMP Option'),
					'pcont'    => __('Delete SNMP Options')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Duplicate the following SNMP Option.'),
					'pmessage' => __('Click \'Continue\' to Duplicate following SNMP Options.'),
					'scont'    => __('Duplicate SNMP Option'),
					'pcont'    => __('Duplicate SNMP Options'),
					'extra'    => [
						'name_format' => [
							'method'  => 'textbox',
							'title'   => __('Name Format'),
							'default' => '<name> (1)',
							'width'   => 25
						]
					]
				],
				3 => [
					'smessage' => __('Click \'Continue\' to Export the following SNMP Option.'),
					'pmessage' => __('Click \'Continue\' to Export following SNMP Options.'),
					'scont'    => __('Export SNMP Option'),
					'pcont'    => __('Export SNMP Options')
				],
			]
		];

		form_continue_confirmation($form_data);
	}
}

function automation_duplicate_snmp_option(int $id, string $new_name) : void {
	$name = db_fetch_cell_prepared('SELECT name
		FROM automation_snmp
		WHERE id = ?', [$id]);

	$new_name = str_replace('<name>', $name, $new_name);

	$save['id']   = 0;
	$save['hash'] = generate_hash();
	$save['name'] = $new_name;

	$newid = sql_save($save, 'automation_snmp');

	if ($newid > 0 && $id > 0) {
		$hash = get_hash_automation($newid, 'automation_snmp_items');

		db_execute_prepared("INSERT INTO automation_snmp_items
			(hash, snmp_id, sequence, snmp_version, snmp_community, snmp_port, snmp_timeout,
			snmp_retries, max_oids, snmp_username, snmp_password, snmp_auth_protocol,
			snmp_priv_passphrase, snmp_priv_protocol, snmp_context, snmp_engine_id)
			SELECT ?, $newid AS snmp_id, sequence, snmp_version, snmp_community, snmp_port, snmp_timeout,
			snmp_retries, max_oids, snmp_username, snmp_password, snmp_auth_protocol,
			snmp_priv_passphrase, snmp_priv_protocol, snmp_context, snmp_engine_id
			FROM automation_snmp_items
			WHERE snmp_id = ?",
			[$hash, $id]);

		raise_message('option_duplicated', __('SNMP Options has been Duplicated.'), MESSAGE_LEVEL_INFO);
	} else {
		raise_message('missing_options', __('Automation Item does not exist.  Can not Duplicate.'), MESSAGE_LEVEL_ERROR);
	}
}

function automation_snmp_item_dnd() : void {
	// ================= Input validation =================
	gfrv('id');
	// ================= Input validation =================

	if (isrv('snmp_item') && is_array(gnrv('snmp_item'))) {
		$items    = grv('snmp_item');
		$sequence = 1;

		foreach ($items as $item) {
			$item = str_replace('line', '', $item);
			input_validate_input_number($item, 'item');

			db_execute_prepared('UPDATE automation_snmp_items
				SET sequence = ?
				WHERE id = ?',
				[$sequence, $item]);

			$sequence++;
		}
	}

	header('Location: automation_snmp.php?action=edit&id=' . grv('id'));

	exit;
}

function automation_snmp_item_movedown() : void {
	// ================= input validation =================
	gfrv('item_id');
	gfrv('id');
	// ====================================================

	move_item_down('automation_snmp_items', grv('item_id'), 'snmp_id=' . grv('id'));
}

function automation_snmp_item_moveup() : void {
	// ================= input validation =================
	gfrv('item_id');
	gfrv('id');
	// ====================================================

	move_item_up('automation_snmp_items', grv('item_id'), 'snmp_id=' . grv('id'));
}

function automation_snmp_item_remove_confirm() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('item_id');
	// ====================================================

	form_start('automation_snmp.php');

	html_start_box('', '100%', false, 3, 'center', '');

	$snmp = db_fetch_row_prepared('SELECT * FROM automation_snmp WHERE id = ?', [grv('id')]);
	$item = db_fetch_row_prepared('SELECT * FROM automation_snmp_items WHERE id = ?', [grv('item_id')]);

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following SNMP Option Item.'); ?></p>
			<p><?php print __('SNMP Option:'); ?> <?php print htmle($snmp['name']); ?><br>
			<?php print __('SNMP Version: <b>%s</b>', $item['snmp_version']); ?><br>
			<?php print __esc('SNMP Community/Username: <b>%s</b>', ($item['snmp_version'] != 3 ? $item['snmp_community'] : $item['snmp_username'])); ?></p>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel' name='cancel' onClick='$("#cdialog").dialog("close");'><?php print __esc('Cancel'); ?></button>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='continue' name='continue' title='<?php print __esc('Remove SNMP Item'); ?>'><?php print __esc('Continue'); ?></button>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#continue').click(function(data) {
			var options = {
				url: 'automation_snmp.php?action=item_remove',
				funcEnd: 'automationSnmpRemoveItemFinalize'
			}

			var data = {
				__csrf_magic: csrfMagicToken,
				item_id: <?php print grv('item_id'); ?>,
				id: <?php print grv('id'); ?>
			}

			postUrl(options, data);
		});
	});

	function automationSnmpRemoveItemFinalize(data) {
		$('#cdialog').dialog('close');
		loadUrl({url:'automation_snmp.php?action=edit&id=<?php print grv('id'); ?>'})
	}

	</script>
	<?php
}

function automation_snmp_item_remove() : void {
	// ================= input validation =================
	gfrv('item_id');
	// ====================================================

	db_execute_prepared('DELETE FROM automation_snmp_items WHERE id = ?', [grv('item_id')]);
}

function automation_snmp_item_edit() : void {
	global $snmp_auth_protocols, $snmp_priv_protocols, $snmp_versions, $snmp_security_levels;

	// require_once(CACTI_PATH_LIBRARY . '/automation_functions.php');

	// ================= input validation =================
	gfrv('id');
	gfrv('item_id');
	// ====================================================

	// fetch the current mactrack snmp record
	$snmp_option = db_fetch_row_prepared('SELECT *
		FROM automation_snmp
		WHERE id = ?', [grv('id')]);

	// if an existing item was requested, fetch data for it
	if (grv('item_id', '') !== '') {
		$automation_snmp_item = db_fetch_row_prepared('SELECT *
			FROM automation_snmp_items
			WHERE id = ?', [grv('item_id')]);

		$header_label = __esc('SNMP Options [edit: %s]', $snmp_option['name']);
	} else {
		$header_label                     = __('SNMP Options [new]');
		$automation_snmp_item             = [];
		$automation_snmp_item['snmp_id']  = grv('id');
		$automation_snmp_item['sequence'] = get_sequence(0, 'sequence', 'automation_snmp_items', 'snmp_id=' . grv('id'));
	}

	form_start('automation_snmp.php', 'automation_item_edit');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	// this is snmp we are talking about here
	unset($snmp_versions[0]);

	global $fields_snmp_item_with_retry;

	// file: mactrack_snmp.php, action: item_edit
	$fields_automation_snmp_item_edit = $fields_snmp_item_with_retry + [
		'sequence' => [
			'method'        => 'view',
			'friendly_name' => __('Sequence'),
			'description'   => __('Sequence of Item.'),
			'value'         => '|arg1:sequence|'],
	];

	draw_edit_form([
		'config' => ['no_form_tag' => true],
		'fields' => inject_form_variables($fields_automation_snmp_item_edit, $automation_snmp_item)
	]);

	html_end_box(true, true);

	form_hidden_box('item_id', (isrv('item_id') ? grv('item_id') : '0'), '');
	form_hidden_box('id', (isset($automation_snmp_item['snmp_id']) ? $automation_snmp_item['snmp_id'] : '0'), '');
	form_hidden_box('save_component_automation_snmp_item', '1', '');

	form_save_button('automation_snmp.php?action=edit&id=' . grv('id'));

	?>
	<script type='text/javascript'>

	$(function() {
		// Need to set this for global snmpv3 functions to remain sane between edits
		snmp_security_initialized = false;

		setSNMP();
	});
	</script>
	<?php
}

function automation_snmp_edit() : void {
	global $fields_automation_snmp_edit;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	// display the mactrack snmp option set
	$snmp_option = [];

	if (!ierv('id')) {
		$snmp_option = db_fetch_row_prepared('SELECT * FROM automation_snmp where id = ?', [grv('id')]);
		// setup header
		$header_label = __esc('SNMP Option Set [edit: %s]', $snmp_option['name']);
	} else {
		$header_label = __('SNMP Option Set [new]');
	}

	form_start('automation_snmp.php', 'automation_snmp_group');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	// file: automation_snmp.php, action: edit
	$fields_automation_snmp_edit = [
		'name' => [
			'method'        => 'textbox',
			'friendly_name' => __('Name'),
			'description'   => __('Fill in the name of this SNMP Option Set.'),
			'value'         => '|arg1:name|',
			'default'       => '',
			'max_length'    => '100',
			'size'          => '40'
		]
	];

	draw_edit_form([
		'config' => ['no_form_tag' => true],
		'fields' => inject_form_variables($fields_automation_snmp_edit, $snmp_option)
	]);

	html_end_box(true, true);

	form_hidden_box('id', (isrv('id') ? grv('id') : '0'), '');
	form_hidden_box('save_component_automation_snmp', '1', '');

	if (!ierv('id')) {
		$items = db_fetch_assoc_prepared('SELECT *
			FROM automation_snmp_items
			WHERE snmp_id = ?
			ORDER BY sequence', [grv('id')]);

		html_start_box(__('SNMP Options'), '100%', false, 3, 'center', 'automation_snmp.php?action=item_edit&id=' . grv('id'));

		$display_text = [
			[
				'display' => __('Item'),
				'align'   => 'left'
			],
			[
				'display' => __('Version'),
				'align'   => 'left'
			],
			[
				'display' => __('Community'),
				'align'   => 'left'
			],
			[
				'display' => __('Port'),
				'align'   => 'right'
			],
			[
				'display' => __('Timeout'),
				'align'   => 'right'
			],
			[
				'display' => __('Retries'),
				'align'   => 'right'
			],
			[
				'display' => __('Max OIDS'),
				'align'   => 'right'
			],
			[
				'display' => __('Auth Username'),
				'align'   => 'left'
			],
			[
				'display' => __('Auth Password'),
				'align'   => 'left'
			],
			[
				'display' => __('Auth Protocol'),
				'align'   => 'left'
			],
			[
				'display' => __('Priv Passphrase'),
				'align'   => 'left'
			],
			[
				'display' => __('Priv Protocol'),
				'align'   => 'left'
			],
			[
				'display' => __('Context'),
				'align'   => 'left'
			],
			[
				'display' => __('Action'),
				'align'   => 'right'
			]
		];

		html_header($display_text);

		$i = 1;

		$total_items = cacti_sizeof($items);

		if (cacti_sizeof($items)) {
			foreach ($items as $item) {
				form_alternate_row('line' . $item['id'], true);

				$form_data = "<td><a class='linkEditMain' href='" . htmle('automation_snmp.php?action=item_edit&item_id=' . $item['id'] . '&id=' . $item['snmp_id']) . "'>" . __('Item # %d', $i) . '</a></td>';
				$form_data .= '<td>' . $item['snmp_version'] . '</td>';
				$form_data .= '<td class="left">' . ($item['snmp_version'] == 3 ? __('none') : htmle($item['snmp_community'])) . '</td>';
				$form_data .= '<td class="right">' . $item['snmp_port'] . '</td>';
				$form_data .= '<td class="right">' . $item['snmp_timeout'] . '</td>';
				$form_data .= '<td class="right">' . $item['snmp_retries'] . '</td>';
				$form_data .= '<td class="right">' . $item['max_oids'] . '</td>';
				$form_data .= '<td>' . ($item['snmp_version'] == 3 ? htmle($item['snmp_username']) : __('N/A')) . '</td>';
				$form_data .= '<td>' . (($item['snmp_version'] == 3 && $item['snmp_password'] !== '') ? '*********' : __('N/A')) . '</td>';
				$form_data .= '<td>' . ($item['snmp_version'] == 3 ? $item['snmp_auth_protocol'] : __('N/A')) . '</td>';
				$form_data .= '<td>' . ($item['snmp_version'] == 3 ? '*********' : __('N/A')) . '</td>';
				$form_data .= '<td>' . ($item['snmp_version'] == 3 ? $item['snmp_priv_protocol'] : __('N/A')) . '</td>';
				$form_data .= '<td>' . ($item['snmp_version'] == 3 ? htmle($item['snmp_context']) : __('N/A')) . '</td>';
				$form_data .= '<td class="nowrap right">';

				if (read_config_option('drag_and_drop') == '') {
					if ($i < $total_items) {
						$form_data .= '<a class="pic ti ti-caret-down-filled moveArrow" href="' . htmle('automation_snmp.php?action=item_movedown&item_id=' . $item['id'] . '&id=' . $item['snmp_id']) . '" title="' . __esc('Move Down') . '"></a>';
					} else {
						$form_data .= '<span class="moveArrowNone"></span>';
					}

					if ($i > 1 && $i <= $total_items) {
						$form_data .= '<a class="pic ti ti-caret-up-filled moveArrow" href="' . htmle('automation_snmp.php?action=item_moveup&item_id=' . $item['id'] . '&id=' . $item['snmp_id']) . '" title="' . __esc('Move Up') . '"></a>';
					} else {
						$form_data .= '<span class="moveArrowNone"></span>';
					}
				}

				$form_data .= '<a class="delete deleteMarker ti ti-x" id="' . $item['id'] . '_' . $item['snmp_id'] . '" title="' . __esc('Delete') . '"></a>';
				$form_data .= '</td>';

				print $form_data;

				form_end_row();

				$i++;
			}
		} else {
			print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No SNMP Items') . '</em></td></tr>';
		}

		html_end_box();
	}

	form_save_button('automation_snmp.php', 'return');

	?>
	<script type='text/javascript'>
	$(function() {
		$('.cdialog').remove();
		$('#main').append("<div class='cdialog' id='cdialog'></div>");
		$('#automation_snmp_edit2_child').attr('id', 'snmp_item');
		$('img.action').click(function() {
			strURL = $(this).attr('href');
			loadUrl({url:strURL})
		});

		<?php if (read_config_option('drag_and_drop') == 'on') { ?>
		$('#snmp_item').tableDnD({
			onDrop: function(table, row) {
				loadUrl({url:'automation_snmp.php?action=ajax_dnd&id=<?php isrv('id') ? print grv('id') : print 0; ?>&'+$.tableDnD.serialize()})
			}
		});
		<?php } ?>

		$('.delete').click(function (event) {
			event.preventDefault();

			id = $(this).attr('id').split('_');
			request = 'automation_snmp.php?action=item_remove_confirm&item_id='+id[0]+'&id='+id[1];
			$.get(request)
				.done(function(data) {
					$('#cdialog').html(data);

					applySkin();

					$('#cdialog').dialog({
						title: '<?php print __('Delete SNMP Option Item'); ?>',
						close: function () { $('.delete').blur(); $('.selectable').removeClass('selected'); },
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

function automation_snmp() : void {
	global $item_rows, $actions;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('SNMP Options'), 'automation_snmp.php', 'snmp_form', 'sess_autom_snmp', 'automation_snmp.php?action=edit');

	$pageFilter->rows_label = __('Rules');
	$pageFilter->has_import = true;
	$pageFilter->render();

	if (grv('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = 'WHERE asnmp.name LIKE ' . db_qstr('%' . grv('filter') . '%');
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(DISTINCT asnmp.id)
		FROM automation_snmp AS asnmp
		LEFT JOIN automation_networks AS anw
		ON asnmp.id=anw.snmp_id
		LEFT JOIN automation_snmp_items AS asnmpi
		ON asnmp.id=asnmpi.snmp_id
		$sql_where
		GROUP BY asnmp.id");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$snmp_options = db_fetch_assoc("SELECT asnmp.*, COUNT(anw.id) AS networks,
		COUNT(asnmpi.snmp_id) AS totals,
		SUM(CASE WHEN asnmpi.snmp_version=1 THEN 1 ELSE 0 END) AS v1entries,
		SUM(CASE WHEN asnmpi.snmp_version=2 THEN 1 ELSE 0 END) AS v2entries,
		SUM(CASE WHEN asnmpi.snmp_version=3 THEN 1 ELSE 0 END) AS v3entries
		FROM automation_snmp AS asnmp
		LEFT JOIN automation_networks AS anw
		ON asnmp.id=anw.snmp_id
		LEFT JOIN automation_snmp_items AS asnmpi
		ON asnmp.id=asnmpi.snmp_id
		$sql_where
		GROUP BY asnmp.id
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('automation_snmp.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 12, __('SNMP Option Sets'), 'page', 'main');

	form_start('automation_snmp.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'name' => [
			'display' => __('SNMP Option Set'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'networks' => [
			'display' => __('Networks Using'),
			'align'   => 'right',
			'sort'    => 'DESC'
		],
		'totals' => [
			'display' => __('SNMP Entries'),
			'align'   => 'right',
			'sort'    => 'DESC'
		],
		'v1entries' => [
			'display' => __('V1 Entries'),
			'align'   => 'right',
			'sort'    => 'DESC'
		],
		'v2entries' => [
			'display' => __('V2 Entries'),
			'align'   => 'right',
			'sort'    => 'DESC'
		],
		'v3entries' => [
			'display' => __('V3 Entries'),
			'align'   => 'right',
			'sort'    => 'DESC'
		]
	];

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($snmp_options)) {
		foreach ($snmp_options as $snmp_option) {
			form_alternate_row('line' . $snmp_option['id'], true);

			form_selectable_cell(filter_value($snmp_option['name'], grv('filter'), 'automation_snmp.php?action=edit&id=' . $snmp_option['id'] . '&page=1'), $snmp_option['id']);
			form_selectable_cell($snmp_option['networks'], $snmp_option['id'], '', 'right');
			form_selectable_cell($snmp_option['totals'], $snmp_option['id'], '', 'right');
			form_selectable_cell($snmp_option['v1entries'], $snmp_option['id'], '', 'right');
			form_selectable_cell($snmp_option['v2entries'], $snmp_option['id'], '', 'right');
			form_selectable_cell($snmp_option['v3entries'], $snmp_option['id'], '', 'right');
			form_checkbox_cell($snmp_option['name'], $snmp_option['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No SNMP Option Sets Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($snmp_options)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
