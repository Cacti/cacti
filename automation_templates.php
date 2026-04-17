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
require_once(CACTI_PATH_LIBRARY . '/api_tree.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

$actions = [
	1 => __('Delete'),
	2 => __('Export')
];

// set default action
set_default_action();

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
	case 'ajax_dnd':
		automation_template_dnd();

		break;
	case 'graph_dnd':
		automation_template_graph_item_dnd();

		break;
	case 'tree_dnd':
		automation_template_tree_item_dnd();

		break;
	case 'exitonchange':
		automation_template_tree_exit_on_change();

		break;
	case 'item_movedown':
		automation_templates_item_movedown();

		header('Location: automation_templates.php?action=edit&id=' . gfrv('template_id'));

		break;
	case 'item_moveup':
		automation_templates_item_moveup();

		header('Location: automation_templates.php?action=edit&id=' . gfrv('template_id'));

		break;
	case 'actions':
		form_actions();

		break;
	case 'item_add_agr':
		automation_add_graph_rule();

		header('Location: automation_templates.php?action=edit&id=' . gfrv('template_id'));

		break;
	case 'item_remove_agr_confirm':
		automation_remove_agr_confirm();

		break;
	case 'item_remove_ttr_confirm':
		automation_remove_ttr_confirm();

		break;
	case 'item_remove_agr':
		automation_remove_agr();

		header('Location: automation_templates.php?action=edit&id=' . gfrv('template_id'));

		break;
	case 'item_add_atr':
		automation_add_tree_rule();

		header('Location: automation_templates.php?action=edit&id=' . gfrv('template_id'));

		break;
	case 'item_add_ttr':
		automation_add_threshold_template();

		header('Location: automation_templates.php?action=edit&id=' . gfrv('template_id'));

		break;
	case 'item_remove_atr_confirm':
		automation_remove_atr_confirm();

		break;
	case 'item_remove_atr':
		automation_remove_atr();

		header('Location: automation_templates.php?action=edit&id=' . gfrv('template_id'));

		break;
	case 'item_remove_ttr':
		automation_remove_ttr();

		header('Location: automation_templates.php?action=edit&id=' . gfrv('id'));

		break;
	case 'movedown':
		automation_movedown();

		header('Location: automation_templates.php');

		break;
	case 'moveup':
		automation_moveup();

		header('Location: automation_templates.php');

		break;
	case 'remove':
		automation_remove();

		header('Location: automation_templates.php');

		break;
	case 'edit':
		top_header();
		template_edit();
		bottom_footer();

		break;
	default:
		top_header();
		template();
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
				$export_data = automation_device_rule_export($selected_items[0]);
			} else {
				foreach ($selected_items as $id) {
					$snmp_option_ids[] = $id;
				}

				$export_data = automation_device_rule_export($snmp_option_ids);
			}

			if (cacti_sizeof($export_data)) {
				$export_file_name = $export_data['export_name'];

				header('Content-type: application/json');
				header('Content-Disposition: attachment; filename=' . $export_file_name);

				$output = json_encode($export_data, JSON_PRETTY_PRINT);

				print $output;
			}
		}
	}
}

function automation_import() : void {
	$form_data = [
		'import_file' => [
			'friendly_name' => __('Import Device Rules from Local File'),
			'description'   => __('If the JSON file containing the Device Rules data is located on your local machine, select it here.'),
			'method'        => 'file',
			'accept'        => '.json'
		],
		'import_text' => [
			'method'        => 'textarea',
			'friendly_name' => __('Import Device Rules from Text'),
			'description'   => __('If you have the JSON file containing the Device Rules data as text, you can paste it into this box to import it.'),
			'value'         => '',
			'default'       => '',
			'textarea_rows' => '10',
			'textarea_cols' => '80',
			'class'         => 'textAreaNotes'
		],
		'import_trees_branches' => [
			'friendly_name' => __('Import Device Rules Trees and Branches'),
			'description'   => __('Automatically Recreate the Trees and Branches if they do not exist upon Import.'),
			'method'        => 'checkbox',
			'value'         => '',
			'default'       => ''
		]
	];

	form_start('automation_templates.php', 'chk', true);

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box(__('Import Results'), '60%', false, 3, 'center', '');

		print '<tr class="tableHeader"><th>' . __('Cacti has Imported the following Device Rules') . '</th></tr>';

		foreach ($_SESSION['import_debug_info'] as $line) {
			print '<tr><td>' . $line . '</td></tr>';
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box(__('Import Device Rules'), '60%', false, 3, 'center', '');

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
			<button type='submit' value='import' title='" . __esc('Import Network Discovery Rule') . "' class='ui-button ui-corner-all ui-widget ui-state-active'>" . __esc('Import') . "</button>
		</td>
		<script type='text/javascript'>
		$(function() {
			Pace.stop();
			clearAllTimeouts();
		});
		</script>
	</tr>";

	html_end_box();
}

function automation_import_process() : void {
	$json_data = json_decode(gnrv('import_text'), true);

	$debug_data = [];

	// If we have text, then we were trying to import text, otherwise we are uploading a file for import
	if (empty($json_data)) {
		$json_data = automation_validate_upload();
	}

	if (isrv('import_trees_branches')) {
		$trees = true;
	} else {
		$trees = false;
	}

	$return_data = automation_template_import($json_data, $trees);

	if (sizeof($return_data) && isset($return_data['success'])) {
		foreach ($return_data['success'] as $message) {
			$debug_data[] = '<span class="deviceUp">' . __('NOTE:') . '</span> ' . $message;
			automation_log('NOTE: Automation Device Rules Import Succeeded!  Message: ' . $message, AUTOMATION_LOG_LOW);
		}
	}

	if (isset($return_data['errors'])) {
		foreach ($return_data['errors'] as $error) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $error;
			automation_log('NOTE: Automation Device Rules Import Error!  Message: ' . $error, AUTOMATION_LOG_LOW);
		}
	}

	if (isset($return_data['failure'])) {
		foreach ($return_data['failure'] as $message) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $message;
			automation_log('NOTE: Automation Device Rules Import Failed!  Message: ' . $message, AUTOMATION_LOG_LOW);
		}
	}

	if (cacti_sizeof($debug_data)) {
		$_SESSION['import_debug_info'] = $debug_data;
	}

	header('Location: automation_templates.php?action=import');

	exit();
}

function automation_template_dnd() : void {
	// ================= Input validation =================
	gfrv('id');
	// ================= Input validation =================

	if (isrv('template_ids') && is_array(gnrv('template_ids'))) {
		$aids     = gnrv('template_ids');
		$sequence = 1;

		foreach ($aids as $id) {
			$id = str_replace('line', '', $id);
			input_validate_input_number($id, 'id');

			db_execute_prepared('UPDATE automation_templates
				SET sequence = ?
				WHERE id = ?',
				[$sequence, $id]);

			$sequence++;
		}
	}

	header('Location: automation_templates.php');

	exit;
}

function automation_templates_item_movedown() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('template_id');
	gfrv('rule_type');
	// ====================================================

	$cur_sequence = db_fetch_cell_prepared('SELECT sequence
		FROM automation_templates_rules
		WHERE template_id = ?
		AND rule_type = ?
		AND id = ?',
		[grv('template_id'), grv('rule_type'), grv('id')]);

	$other_id = db_fetch_cell_prepared('SELECT id
		FROM automation_templates_rules
		WHERE template_id = ?
		AND rule_type = ?
		AND sequence = ?',
		[grv('template_id'), grv('rule_type'), $cur_sequence + 1]);

	db_execute_prepared('UPDATE automation_templates_rules
		SET sequence = ?
		WHERE id = ?',
		[$cur_sequence + 1, grv('id')]);

	db_execute_prepared('UPDATE automation_templates_rules
		SET sequence = ?
		WHERE id = ?',
		[$cur_sequence, $other_id]);

	automation_resequence_rules(grv('template_id'));
}

function automation_templates_item_moveup() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('template_id');
	gfrv('rule_type');
	// ====================================================

	$cur_sequence = db_fetch_cell_prepared('SELECT sequence
		FROM automation_templates_rules
		WHERE template_id = ?
		AND rule_type = ?
		AND id = ?',
		[grv('template_id'), grv('rule_type'), grv('id')]);

	$other_id = db_fetch_cell_prepared('SELECT id
		FROM automation_templates_rules
		WHERE template_id = ?
		AND rule_type = ?
		AND sequence = ?',
		[grv('template_id'), grv('rule_type'), $cur_sequence - 1]);

	db_execute_prepared('UPDATE automation_templates_rules
		SET sequence = ?
		WHERE id = ?',
		[$cur_sequence - 1, grv('id')]);

	db_execute_prepared('UPDATE automation_templates_rules
		SET sequence = ?
		WHERE id = ?',
		[$cur_sequence, $other_id]);

	automation_resequence_rules(grv('template_id'));
}

function automation_template_graph_item_dnd() : void {
	// ================= Input validation =================
	gfrv('id');
	// ================= Input validation =================

	if (isrv('graph_rules') && is_array(gnrv('graph_rules'))) {
		$aids        = gnrv('graph_rules');
		$sequence    = 1;
		$template_id = grv('id');

		foreach ($aids as $id) {
			$id = str_replace('gr', '', $id);

			input_validate_input_number($id, 'id');

			db_execute_prepared('UPDATE automation_templates_rules
				SET sequence = ?
				WHERE template_id = ?
				AND id = ?
				AND rule_type = 1',
				[$sequence, $template_id, $id]);

			$sequence++;
		}
	}

	header('Location: automation_templates.php?action=edit&id=' . grv('id'));

	exit;
}

function automation_template_tree_item_dnd() : void {
	// ================= Input validation =================
	gfrv('id');
	// ================= Input validation =================

	if (isrv('template_ids') && is_array(gnrv('template_ids'))) {
		$aids        = gnrv('template_ids');
		$sequence    = 1;
		$template_id = grv('id');

		foreach ($aids as $id) {
			$id = str_replace('tr', '', $id);

			input_validate_input_number($id, 'id');

			db_execute_prepared('UPDATE automation_templates_rules
				SET sequence = ?
				WHERE template_id = ?
				AND id = ?
				AND rule_type = 2',
				[$sequence, $template_id, $id]);

			$sequence++;
		}
	}

	header('Location: automation_templates.php?action=edit&id=' . grv('id'));

	exit;
}

function automation_template_tree_exit_on_change() : void {
	$id          = gfrv('id');
	$newvalue    = gfrv('current') == 0 ? 1 : 0;
	$template_id = gfrv('template_id');

	db_execute_prepared('UPDATE automation_templates_rules
		SET exit_rules = ?
		WHERE id = ?',
		[$newvalue, $id]);

	header('Location: automation_templates.php?action=edit&id=' . $template_id);
}

function automation_movedown() : void {
	move_item_down('automation_templates', gfrv('id'));
}

function automation_moveup() : void {
	move_item_up('automation_templates', gfrv('id'));
}

function automation_remove() : void {
	db_execute_prepared('DELETE FROM automation_templates WHERE id = ?', [gfrv('id')]);
}

function automation_add_graph_rule() : void {
	// ================= input validation =================
	gfrv('template_id');
	gfrv('rule_id');
	// ====================================================

	$save = [];

	$save['id']          = 0;
	$save['hash']        = get_hash_automation(0, 'automation_templates_rules');
	$save['template_id'] = grv('template_id');
	$save['rule_type']   = 1;
	$save['rule_id']     = grv('rule_id');
	$save['sequence']    = db_fetch_cell('SELECT MAX(sequence)+1 FROM automation_templates_rules WHERE rule_type = 1');
	$save['exit_rules']  = 0;

	sql_save($save, 'automation_templates_rules');

	automation_resequence_rules(grv('template_id'));

	raise_message('rule_save', __('The Graph Rule has been added to the Device Rule'), MESSAGE_LEVEL_INFO);
}

function automation_add_threshold_template() : void {
	// ================= input validation =================
	gfrv('template_id');
	gfrv('thold_template_id');
	// ====================================================

	$host_template_id = db_fetch_cell_prepared('SELECT host_template
		FROM automation_templates
		WHERE id = ?',
		[grv('template_id')]);

	$save = [];

	$save['id']                = 0;
	$save['host_template_id']  = $host_template_id;
	$save['thold_template_id'] = grv('thold_template_id');

	sql_save($save, 'plugin_thold_host_template');

	raise_message('rule_save', __('The Threshold Template has been added to the Device Rule'), MESSAGE_LEVEL_INFO);
}

function automation_add_tree_rule() : void {
	// ================= input validation =================
	gfrv('template_id');
	gfrv('rule_id');
	// ====================================================

	$save = [];

	$save['id']          = 0;
	$save['hash']        = get_hash_automation(0, 'automation_templates_rules');
	$save['template_id'] = grv('template_id');
	$save['rule_type']   = 2;
	$save['rule_id']     = grv('rule_id');
	$save['sequence']    = db_fetch_cell('SELECT MAX(sequence)+1 FROM automation_templates_rules WHERE rule_type = 2');
	$save['exit_rules']  = 0;

	sql_save($save, 'automation_templates_rules');

	automation_resequence_rules(grv('template_id'));

	raise_message('rule_save', __('The Tree Rule has been added to the Device Rule'), MESSAGE_LEVEL_INFO);
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
			if (gnrv('drp_action') == '1') { // delete
				db_execute('DELETE FROM automation_templates WHERE ' . array_to_sql_or($selected_items, 'id'));
			} elseif (gnrv('drp_action') == '2') { // export
				top_header();

				print '<script text="text/javascript">
					function DownloadStart(url) {
						document.getElementById("download_iframe").src = url;
						setTimeout(function() {
							loadUrl({ url: "automation_templates.php" });
							Pace.stop();
						}, 500);
					}

					$(function() {
						//debugger;
						DownloadStart(\'automation_templates.php?action=export&selected_items=' . gnrv('selected_items') . '\');
					});
				</script>
				<iframe id="download_iframe" style="display:none;"></iframe>';

				bottom_footer();

				exit;
			}
		}

		header('Location: automation_templates.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT ht.name FROM automation_templates AS at INNER JOIN host_template AS ht ON ht.id=at.host_template WHERE at.id = ?', [$matches[1]])) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'automation_templates.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following Device Rule.'),
					'pmessage' => __('Click \'Continue\' to Delete following Device Rules.'),
					'scont'    => __('Delete Device Rule'),
					'pcont'    => __('Delete Device Rules')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Export the following Device Rule.'),
					'pmessage' => __('Click \'Continue\' to Export following Device Rules.'),
					'scont'    => __('Export Device Rule'),
					'pcont'    => __('Export Device Rules'),
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}

function form_save() : void {
	if (isrv('save_component_template')) {
		$redirect_back = false;

		$save['id']                   = gnrv('id');
		$save['hash']                 = get_hash_automation(grv('id'), 'automation_templates');
		$save['host_template']        = form_input_validate(gnrv('host_template'), 'host_template', '', false, 3);
		$save['availability_method']  = form_input_validate(gnrv('availability_method'), 'availability_method', '', false, 3);
		$save['sysDescr']             = gnrv('sysDescr');
		$save['sysName']              = gnrv('sysName');
		$save['sysOid']               = gnrv('sysOid');
		$save['description_pattern']  = gnrv('description_pattern');
		$save['populate_location']    = isrv('populate_location') ? 'on' : '';

		if (function_exists('filter_var')) {
			$save['sysDescr'] = filter_var($save['sysDescr'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		} else {
			$save['sysDescr'] = strip_tags($save['sysDescr']);
		}

		if (!is_error_message()) {
			$template_id = sql_save($save, 'automation_templates');

			if ($template_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message() || ierv('id')) {
			header('Location: automation_templates.php?id=' . (empty($template_id) ? gnrv('id') : $template_id));
		} else {
			header('Location: automation_templates.php');
		}
	}
}

function automation_remove_ttr_confirm() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('rule_id');
	gfrv('template_id');
	// ====================================================

	form_start('automation_templates.php?action=edit&id=' . grv('id'));

	html_start_box('', '100%', false, 3, 'center', '');

	$rule = db_fetch_row_prepared('SELECT *
		FROM plugin_thold_host_template
		WHERE id = ?',
		[grv('rule_id')]);

	if (cacti_sizeof($rule)) {
		$name = db_fetch_cell_prepared('SELECT tt.name
			FROM thold_template AS tt
			INNER JOIN plugin_thold_host_template AS tht
			ON tt.id = tht.thold_template_id
			WHERE tht.id = ?',
			[grv('rule_id')]);
	} else {
		$name = __('Unknown');
	}

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to Delete the following Threshold Template will be disassociated from the Device Rule.'); ?></p>
			<p><?php print __("Threshold Template Name: '%s'", htmle($name)); ?>
			<br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='cancel' onClick='$("#cdialog").dialog("close")' name='cancel'><?php print __esc('Cancel'); ?></button>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='continue' value='continue' name='continue' title='<?php print __esc('Remove Threshold Template'); ?>'><?php print __esc('Continue'); ?></button>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$('#continue').click(function(data) {
		var options = {
			url: 'automation_templates.php?action=item_remove_ttr'
		}

		var data = {
			__csrf_magic: csrfMagicToken,
			id: <?php print grv('id'); ?>,
			template_id: <?php print grv('template_id'); ?>,
			rule_id: <?php print grv('rule_id'); ?>
		}

		$('#cdialog').dialog('close');

		postUrl(options, data);
	});
	</script>
	<?php
}

function automation_remove_agr_confirm() : void {
	// ================= input validation =================
	gfrv('rule_id');
	gfrv('template_id');
	// ====================================================

	form_start('automation_templates.php?action=edit&id=' . grv('template_id'));

	html_start_box('', '100%', false, 3, 'center', '');

	$rule = db_fetch_row_prepared('SELECT *
		FROM automation_templates_rules
		WHERE id = ?',
		[grv('rule_id')]);

	if (cacti_sizeof($rule)) {
		$name = db_fetch_cell_prepared('SELECT name
			FROM automation_graph_rules
			WHERE id = ?',
			[$rule['rule_id']]);
	} else {
		$name = __('Unknown');
	}

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to Delete the following Graph Rule will be disassociated from the Device Rule.'); ?></p>
			<p><?php print __("Graph Rule Name: '%s'", htmle($name)); ?>
			<br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='cancel' onClick='$("#cdialog").dialog("close")' name='cancel'><?php print __esc('Cancel'); ?></button>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='continue' value='continue' name='continue' title='<?php print __esc('Remove Graph Rule'); ?>'><?php print __esc('Continue'); ?></button>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$('#continue').click(function(data) {
		var options = {
			url: 'automation_templates.php?action=item_remove_agr'
		}

		var data = {
			__csrf_magic: csrfMagicToken,
			template_id: <?php print grv('template_id'); ?>,
			rule_id: <?php print grv('rule_id'); ?>
		}

		$('#cdialog').dialog('close');

		postUrl(options, data);
	});
	</script>
	<?php
}

function automation_remove_agr() : void {
	// ================= input validation =================
	gfrv('rule_id');
	gfrv('template_id');
	// ====================================================

	db_execute_prepared('DELETE FROM automation_templates_rules
		WHERE id = ?
		AND rule_type = 1
		AND template_id = ?',
		[grv('rule_id'), grv('template_id')]);

	automation_resequence_rules(grv('template_id'));

	raise_message('rule_remove', __('The Graph Rule has been removed from the Device Rule'), MESSAGE_LEVEL_INFO);
}

function automation_resequence_rules(int $template_id) : void {
	$gr_seq = db_fetch_assoc_prepared('SELECT *
		FROM automation_templates_rules
		WHERE template_id = ?
		AND rule_type = 1
		ORDER BY sequence',
		[$template_id]);

	if (cacti_sizeof($gr_seq)) {
		$sequence = 1;

		foreach ($gr_seq as $s) {
			db_execute_prepared('UPDATE automation_templates_rules
				SET `sequence` = ?
				WHERE id = ?',
				[$sequence, $s['id']]);

			$sequence++;
		}
	}

	$tr_seq = db_fetch_assoc_prepared('SELECT *
		FROM automation_templates_rules
		WHERE template_id = ?
		AND rule_type = 2
		ORDER BY sequence',
		[$template_id]);

	if (cacti_sizeof($tr_seq)) {
		$sequence = 1;

		foreach ($tr_seq as $s) {
			db_execute_prepared('UPDATE automation_templates_rules
				SET `sequence` = ?
				WHERE id = ?',
				[$sequence, $s['id']]);

			$sequence++;
		}
	}
}

function automation_remove_atr_confirm() : void {
	// ================= input validation =================
	gfrv('rule_id');
	gfrv('template_id');
	// ====================================================

	form_start('automation_templates.php?action=edit&id=' . grv('template_id'));

	html_start_box('', '100%', false, 3, 'center', '');

	$rule = db_fetch_row_prepared('SELECT *
		FROM automation_templates_rules
		WHERE id = ?',
		[grv('rule_id')]);

	if (cacti_sizeof($rule)) {
		$name = db_fetch_cell_prepared('SELECT name
			FROM automation_tree_rules
			WHERE id = ?',
			[$rule['rule_id']]);
	} else {
		$name = __('Unknown');
	}

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to Delete the following Tree Rule(s) will be disassociated from the Device Rule.'); ?></p>
			<p><?php print __("Tree Rule Name: '%s'", htmle($name)); ?>
			<br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='cancel' onClick='$("#cdialog").dialog("close")' name='cancel'><?php print __esc('Cancel'); ?></button>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='continue' value='continue' name='continue' title='<?php print __esc('Remove Tree Rule'); ?>'><?php print __esc('Cancel'); ?></button>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$('#continue').click(function(data) {
		var options = {
			url: 'automation_templates.php?action=item_remove_atr'
		}

		var data = {
			__csrf_magic: csrfMagicToken,
			template_id: <?php print grv('template_id'); ?>,
			rule_id: <?php print grv('rule_id'); ?>
		}

		$('#cdialog').dialog('close');

		postUrl(options, data);
	});
	</script>
	<?php
}

function automation_remove_atr() : void {
	// ================= input validation =================
	gfrv('rule_id');
	gfrv('template_id');
	// ====================================================

	db_execute_prepared('DELETE FROM automation_templates_rules
		WHERE id = ?
		AND rule_type = 2
		AND template_id = ?',
		[grv('rule_id'), grv('template_id')]);

	automation_resequence_rules(grv('template_id'));

	raise_message('rule_remove', __('The Tree Rule has been removed from the Device Automation Rule'), MESSAGE_LEVEL_INFO);
}

function automation_remove_ttr() : void {
	// ================= input validation =================
	gfrv('rule_id');
	gfrv('template_id');
	// ====================================================

	db_execute_prepared('DELETE FROM plugin_thold_host_template
		WHERE id = ?
		AND host_template_id = ?',
		[grv('rule_id'), grv('template_id')]);

	raise_message('rule_remove', __('The Threshold Template has been removed from the Device Automation Rule'), MESSAGE_LEVEL_INFO);
}

function automation_get_child_branches(int $tree_id, int $id, string $spaces, array $headers) : array {
	$items = db_fetch_assoc_prepared('SELECT id, title
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND host_id = 0
		AND local_graph_id = 0
		AND parent = ?
		ORDER BY position', [$tree_id, $id]);

	$spaces .= '--';

	if (cacti_sizeof($items)) {
		foreach ($items as $i) {
			$headers['tr_' . $tree_id . '_bi_' . $i['id']] = $spaces . ' ' . $i['title'];

			$headers = automation_get_child_branches($tree_id, $i['id'], $spaces, $headers);
		}
	}

	return $headers;
}

function automation_get_tree_headers() : array {
	$headers = [];
	$trees   = db_fetch_assoc('SELECT id, name FROM graph_tree ORDER BY name');

	foreach ($trees as $tree) {
		$headers['tr_' . $tree['id'] . '_br_0'] = $tree['name'];

		$spaces = '';

		$headers = automation_get_child_branches($tree['id'], 0, $spaces, $headers);
	}

	return $headers;
}

function template_edit() : void {
	global $availability_options;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	$host_template_names = db_fetch_assoc('SELECT id, name FROM host_template');

	$template_names = [];

	if (cacti_sizeof($host_template_names)) {
		foreach ($host_template_names as $ht) {
			$template_names[$ht['id']] = $ht['name'];
		}
	}

	$fields = [
		'spacer0' => [
			'method'        => 'spacer',
			'friendly_name' => __('Matching Settings'),
		],
		'host_template' => [
			'method'        => 'drop_array',
			'friendly_name' => __('Template Name'),
			'description'   => __('Select a Device Template that Devices will be matched to.'),
			'value'         => '|arg1:host_template|',
			'array'         => $template_names,
		],
		'availability_method' => [
			'method'        => 'drop_array',
			'friendly_name' => __('Availability Method'),
			'description'   => __('Choose the Availability Method to use for Discovered Devices.'),
			'value'         => '|arg1:availability_method|',
			'default'       => read_config_option('availability_method'),
			'array'         => $availability_options,
		],
		'sysDescr' => [
			'method'        => 'textbox',
			'friendly_name' => __('System Description Match'),
			'description'   => __('This is a unique string that will be matched to a device\'s sysDescr string to pair it to this Device Rule.  Any Perl regular expression can be used in addition to any SQL Where expression.'),
			'value'         => '|arg1:sysDescr|',
			'max_length'    => '255',
		],
		'sysName' => [
			'method'        => 'textbox',
			'friendly_name' => __('System Name Match'),
			'description'   => __('This is a unique string that will be matched to a device\'s sysName string to pair it to this Device Rule.  Any Perl regular expression can be used in addition to any SQL Where expression.'),
			'value'         => '|arg1:sysName|',
			'max_length'    => '128',
		],
		'sysOid' => [
			'method'        => 'textbox',
			'friendly_name' => __('System OID Match'),
			'description'   => __('This is a unique string that will be matched to a device\'s sysOid string to pair it to this Device Rule.  Any Perl regular expression can be used in addition to any SQL Where expression.'),
			'value'         => '|arg1:sysOid|',
			'max_length'    => '128',
		],
		'spacer1' => [
			'method'        => 'spacer',
			'friendly_name' => __('Device Creation Defaults'),
		],
		'description_pattern' => [
			'method'        => 'textbox',
			'friendly_name' => __('Device Description Pattern'),
			'description'   => __('Represents the final desired Device description to be used in Cacti.  The following replacement values can be used: |sysName|, |ipAddress|, |dnsName|, |dnsShortName|, |sysLocation|.  The following functions can also be used: CONCAT(), SUBSTRING(), SUBSTRING_INDEX().  See the MySQL/MariaDB documentation for examples on how to use these functions.  An example would be: CONCAT(\'|sysName|\', SUBSTRING(\'|sysLocation|\',1,3)).  Take care to include quoting around the variables names when used in the supported MySQL/MariaDB function examples.'),
			'value'         => '|arg1:description_pattern|',
			'default'       => '|sysName|',
			'max_length'    => '128',
			'size'          => '80'
		],
		'populate_location' => [
			'method'        => 'checkbox',
			'friendly_name' => __('Populate Location with sysLocation'),
			'description'   => __('If checked, when the Automation Network is scanned if a Device is found that will be added to Cacti, its Location will be updated to match the Devices sysLocation.'),
			'value'         => '|arg1:populate_location|',
			'default'       => ''
		],
		'id' => [
			'method' => 'hidden_zero',
			'value'  => '|arg1:id|'
		],
		'save_component_template' => [
			'method' => 'hidden',
			'value'  => '1'
		]
	];

	if (!ierv('id')) {
		$template = db_fetch_row_prepared('SELECT *
			FROM automation_templates
			WHERE id = ?',
			[grv('id')]);

		if (isset($template_names[$template['host_template']])) {
			$header_label = __esc('Device Rules [edit: %s]', $template_names[$template['host_template']]);
		} else {
			$header_label = __('Device Rules for [Deleted Template]');
		}
	} else {
		$header_label = __('Device Rules [new]');
		$template     = [];

		srv('id', 0);
	}

	form_start('automation_templates.php', 'form_network');

	html_start_box($header_label, '100%', false, 3, 'center', '');

	draw_edit_form(
		[
			'config' => ['no_form_tag' => 'true'],
			'fields' => inject_form_variables($fields, $template)
		]
	);

	html_end_box();

	if (!ierv('id')) {
		/**
		 * Start Graph Rules here.
		 */
		html_start_box(__('Associated Graph Rules'), '100%', false, 3, 'center', '');

		$graph_rules = db_fetch_assoc_prepared('SELECT atr.*, gr.name
			FROM automation_templates_rules AS atr
			INNER JOIN automation_graph_rules AS gr
			ON atr.rule_id = gr.id
			AND atr.rule_type = 1
			WHERE template_id = ?
			ORDER BY sequence',
			[grv('id')]);

		$display_text = [
			[
				'display' => __('Graph Rule Name'),
				'align'   => 'left',
			],
			[
				'display' => __('Sequence'),
				'align'   => 'right',
			],
			[
				'display' => __('Actions'),
				'align'   => 'right',
			]
		];

		html_header($display_text, 1);

		$dnd = read_config_option('drag_and_drop') == 'on' ? true : false;

		if (cacti_sizeof($graph_rules)) {
			$i = 0;

			foreach ($graph_rules as $rule) {
				$id = "gr{$rule['id']}";

				form_alternate_row($id, true);

				form_selectable_ecell($rule['name'], $id);
				form_selectable_cell($rule['sequence'], $id, '', 'right');

				$action = '';

				if (!$dnd) {
					if ($i != cacti_sizeof($graph_rules) - 1) {
						$action .= '<a class="pic ti ti-caret-down-filled moveArrow" href="' . htmle('automation_templates.php?action=item_movedown&template_id=' . grv('id') . '&id=' . $rule['id'] . '&rule_type=1') . '" title="' . __esc('Move Down') . '"></a>';
					} else {
						$action .= '<a href="#" class="moveArrowNone"></a>';
					}

					if ($i > 0) {
						$action .= '<a class="pic ti ti-caret-up-filled moveArrow" href="' . htmle('automation_templates.php?action=item_moveup&template_id=' . grv('id') . '&id=' . $rule['id'] . '&rule_type=1') . '" title="' . __esc('Move Up') . '"></a>';
					} else {
						$action .= '<a href="#" class="moveArrowNone"></a>';
					}
				}

				form_selectable_cell("$action<a class='delete deleteMarker ti ti-x' title='" . __esc('Delete') . "' href='" . htmle('automation_templates.php?action=item_remove_agr_confirm&template_id=' . grv('id') . '&rule_id=' . $rule['id']) . "'></a>", $id, '40', 'right');

				form_end_row();

				$i++;
			}
		} else {
			print '<tr class="tableRow odd"><td colspan="3"><em>' . __('No Associated Graph Rules') . '</em></td></tr>';
		}

		html_end_box();

		html_start_box('', '100%', false, 3, 'center', '');

		$field_label  = __('Add Graph Rule');
		$field_name   = 'graph_rule';
		$field_array  = db_fetch_assoc_prepared('SELECT DISTINCT ar.id, ar.name
			FROM automation_graph_rules AS ar
			LEFT JOIN automation_templates_rules AS art
			ON ar.id = art.rule_id
			AND art.rule_type = 1
			WHERE ar.id NOT IN (
				SELECT rule_id
				FROM automation_templates_rules
				WHERE rule_type = 1
				AND template_id = ?
			)
			ORDER BY ar.name',
			[grv('id')]);

		$button_id     = 'add_agr';
		$button_label  = __esc('Add');
		$button_title  = __esc('Add Graph Rule to Device Rule');

		create_add_form_dropdown($field_label, $field_name, $field_array, $button_id, $button_label, $button_title);

		html_end_box();

		/**
		 * Start Tree Rules here.
		 */
		html_start_box(__('Associated Tree Rules'), '100%', false, 3, 'center', '');

		$tree_rules = db_fetch_assoc_prepared('SELECT atr.*, tr.name
			FROM automation_templates_rules AS atr
			INNER JOIN automation_tree_rules AS tr
			ON atr.rule_id = tr.id
			AND atr.rule_type = 2
			WHERE template_id = ?
			ORDER BY sequence',
			[grv('id')]);

		$display_text = [
			[
				'display' => __('Tree Rule Name'),
				'align'   => 'left',
			],
			[
				'display' => __('Exit On Match'),
				'align'   => 'left',
			],
			[
				'display' => __('Sequence'),
				'align'   => 'right',
			],
			[
				'display' => __('Actions'),
				'align'   => 'right',
			]
		];

		html_header($display_text, 1);

		$dnd = read_config_option('drag_and_drop') == 'on' ? true : false;

		if (cacti_sizeof($tree_rules)) {
			$i = 0;

			foreach ($tree_rules as $rule) {
				$id = "tr{$rule['id']}";

				$exit_on_url = CACTI_PATH_URL . 'automation_templates.php' .
					'?action=exitonchange' .
					'&template_id=' . grv('id') .
					'&id=' . $rule['id'] .
					'&current=' . $rule['exit_rules'];

				$exit_text   = $rule['exit_rules'] == 0 ? __('No') : __('Yes');

				form_alternate_row($id, true);

				form_selectable_ecell($rule['name'], $id);
				form_selectable_cell(filter_value($exit_text, '', $exit_on_url), $id);
				form_selectable_cell($rule['sequence'], $id, '', 'right');

				$action = '';

				if (!$dnd) {
					if ($i != cacti_sizeof($tree_rules) - 1) {
						$action .= '<a class="pic ti ti-caret-down-filled moveArrow" href="' . htmle('automation_templates.php?action=item_movedown&template_id=' . grv('id') . '&id=' . $rule['id'] . '&rule_type=2') . '" title="' . __esc('Move Down') . '"></a>';
					} else {
						$action .= '<a href="#" class="moveArrowNone"></a>';
					}

					if ($i > 0) {
						$action .= '<a class="pic ti ti-caret-up-filled moveArrow" href="' . htmle('automation_templates.php?action=item_moveup&template_id=' . grv('id') . '&id=' . $rule['id'] . '&rule_type=2') . '" title="' . __esc('Move Up') . '"></a>';
					} else {
						$action .= '<a href="#" class="moveArrowNone"></a>';
					}
				}

				form_selectable_cell("$action<a class='delete deleteMarker ti ti-x' title='" . __esc('Delete') . "' href='" . htmle('automation_templates.php?action=item_remove_atr_confirm&template_id=' . grv('id') . '&rule_id=' . $rule['id']) . "'></a>", $id, '40', 'right');

				form_end_row();

				$i++;
			}
		} else {
			print '<tr class="tableRow odd"><td colspan="4"><em>' . __('No Associated Tree Rules') . '</em></td></tr>';
		}

		html_end_box();

		html_start_box('', '100%', false, 3, 'center', '');

		$field_label  = __('Add Tree Rule');
		$field_name   = 'tree_rule';
		$field_array  = db_fetch_assoc_prepared('SELECT DISTINCT ar.id, ar.name
			FROM automation_tree_rules AS ar
			LEFT JOIN automation_templates_rules AS art
			ON ar.id = art.rule_id
			AND art.rule_type = 2
			WHERE ar.id NOT IN (
				SELECT rule_id
				FROM automation_templates_rules
				WHERE rule_type = 2
				AND template_id = ?
			)
			ORDER BY ar.name',
			[grv('id')]);

		$button_id     = 'add_atr';
		$button_label  = __esc('Add');
		$button_title  = __esc('Add Tree Rule to Device Rule');

		create_add_form_dropdown($field_label, $field_name, $field_array, $button_id, $button_label, $button_title);

		html_end_box();

		if (db_table_exists('plugin_thold_host_template')) {
			/**
			 * Start Threshold Template here.
			 */
			html_start_box(__('Associated Threshold Templates'), '100%', false, 3, 'center', '');

			$thold_rules = db_fetch_assoc_prepared('SELECT tht.id AS rule_id, tt.id, tt.name
				FROM thold_template AS tt
				INNER JOIN plugin_thold_host_template AS tht
				ON tht.thold_template_id = tt.id
				WHERE tht.host_template_id = ?
				ORDER BY name',
				[$template['host_template']]);

			$display_text = [
				[
					'display' => __('Threshold Template Name'),
					'align'   => 'left',
				],
				[
					'display' => __('Actions'),
					'align'   => 'right',
				]
			];

			html_header($display_text, 1);

			$action = '';

			if (cacti_sizeof($graph_rules)) {
				foreach ($thold_rules as $rule) {
					$id = "tt{$rule['rule_id']}";

					form_alternate_row($id, true);

					form_selectable_ecell($rule['name'], $id);

					form_selectable_cell("$action<a class='delete deleteMarker ti ti-x' title='" . __esc('Delete') . "' href='" . htmle('automation_templates.php?action=item_remove_ttr_confirm&id=' . grv('id') . '&template_id=' . $template['host_template'] . '&rule_id=' . $rule['rule_id']) . "'></a>", $id, '40', 'right');

					form_end_row();
				}
			} else {
				print '<tr class="tableRow odd"><td colspan="2"><em>' . __('No Associated Threshold Template Rules') . '</em></td></tr>';
			}

			$host_template_id = db_fetch_cell_prepared('SELECT host_template
				FROM automation_templates
				WHERE id = ?',
				[grv('id')]);

			html_end_box();

			html_start_box('', '100%', false, 3, 'center', '');

			$field_label  = __('Add Threshold Rule');
			$field_name   = 'thold_rule';
			$field_array  = db_fetch_assoc_prepared('SELECT DISTINCT tt.id, tt.name
				FROM thold_template AS tt
				LEFT JOIN plugin_thold_host_template AS tht
				ON tt.id = tht.thold_template_id
				WHERE tt.id NOT IN (
					SELECT thold_template_id
					FROM plugin_thold_host_template
					WHERE host_template_id = ?
				)
				ORDER BY tt.name',
				[$host_template_id]);

			$button_id     = 'add_ttr';
			$button_label  = __esc('Add');
			$button_title  = __esc('Add Threshold Rule to Device Rule');

			create_add_form_dropdown($field_label, $field_name, $field_array, $button_id, $button_label, $button_title);

			html_end_box();
		}
	}

	form_save_button('automation_templates.php');

	?>
	<script type='text/javascript'>

	var dnd = <?php print read_config_option('drag_and_drop') == 'on' ? 'true' : 'false'; ?>;

	$(function() {
		$('#cdialog').remove();
		$('#main').append("<div id='cdialog' class='cdialog'></div>");

		$('.delete').click(function (event) {
			event.preventDefault();

			request = $(this).attr('href');
			$.get(request)
				.done(function(data) {
					$('#cdialog').html(data);

					applySkin();

					$('#cdialog').dialog({
						title: '<?php print __('Delete Item from Device Rule'); ?>',
						close: function () { $('.delete').blur(); $('.selectable').removeClass('selected'); },
						minHeight: 80,
						minWidth: 500
					})
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		}).css('cursor', 'pointer');

		$('#add_agr').click(function() {
			var options = {
				url: 'automation_templates.php?action=item_add_agr'
			}

			if ($('#graph_rule').val() == null) {
				return false;
			}

			var data = {
				template_id: $('#id').val(),
				rule_id: $('#graph_rule').val(),
				__csrf_magic: csrfMagicToken
			}

			postUrl(options, data);
		});

		$('#add_atr').click(function() {
			var options = {
				url: 'automation_templates.php?action=item_add_atr'
			}

			if ($('#tree_rule').val() == null) {
				return false;
			}

			var data = {
				template_id: $('#id').val(),
				rule_id: $('#tree_rule').val(),
				__csrf_magic: csrfMagicToken
			}

			postUrl(options, data);
		});

		$('#add_ttr').click(function() {
			var options = {
				url: 'automation_templates.php?action=item_add_ttr'
			}

			if ($('#thold_rule').val() == null) {
				return false;
			}

			var data = {
				template_id: $('#id').val(),
				thold_template_id: $('#thold_rule').val(),
				__csrf_magic: csrfMagicToken
			}

			postUrl(options, data);
		});

		$('#automation_templates_edit2_child').attr('id', 'graph_rules');
		$('#automation_templates_edit4_child').attr('id', 'tree_rules');

		if (dnd) {
			$('#graph_rules').find('tr:first').addClass('nodrag').addClass('nodrop');
			$('#tree_rules').find('tr:first').addClass('nodrag').addClass('nodrop');

			$('#graph_rules').tableDnD({
				onDrop: function(table, row) {
					loadUrl({url:'automation_templates.php?action=graph_dnd&id='+$('#id').val()+'&'+$.tableDnD.serialize()});
				}
			});

			$('#tree_rules').tableDnD({
				onDrop: function(table, row) {
					loadUrl({url:'automation_templates.php?action=tree_dnd&id='+$('#id').val()+'&'+$.tableDnD.serialize()});
				}
			});
		}
	});
	</script>
	<?php
}

function create_add_form_dropdown(string $field_label, string $field_name, array $field_array, string $button_id, string $button_label, string $button_title) : void {
	?>
	<tr class='odd'>
		<td colspan='2'>
			<table>
				<tr style='line-height:10px'>
					<td class='nowrap templateAdd'>
						<?php print $field_label; ?>
					</td>
					<td class='noHide'>
						<?php form_dropdown($field_name, $field_array, 'name', 'id', '', '', ''); ?>
					</td>
					<td class='noHide'>
						<button type='button' class='ui-button ui-corner-all ui-widget' id='<?php print $button_id; ?>' title='<?php print $button_title; ?>'><?php print $button_label; ?></button>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php
}

function template() : void {
	global $actions, $item_rows, $availability_options;

	automation_update_hashes();

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Device Rules'), 'automation_templates.php', 'snmp_at', 'sess_autot', 'automation_templates.php?action=edit');

	$pageFilter->rows_label = __('Templates');
	$pageFilter->has_import = true;
	$pageFilter->render();

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = 'WHERE (name LIKE ' . db_qstr('%' . grv('filter') . '%') . ' OR ' .
			'sysName LIKE ' . db_qstr('%' . grv('filter') . '%') . ' OR ' .
			'sysDescr LIKE ' . db_qstr('%' . grv('filter') . '%') . ' OR ' .
			'sysOID LIKE ' . db_qstr('%' . grv('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM automation_templates AS at
		LEFT JOIN host_template AS ht
		ON ht.id=at.host_template
		$sql_where");

	$dts = db_fetch_assoc("SELECT at.*, ht.name
		FROM automation_templates AS at
		LEFT JOIN host_template AS ht
		ON ht.id=at.host_template
		$sql_where
		ORDER BY sequence " .
		' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows);

	$nav = html_nav_bar('automation_templates.php', MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 7, __('Templates'), 'page', 'main');

	form_start('automation_templates.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		[
			'display' => __('Template Name'),
			'align'   => 'left'
		],
		[
			'display' => __('Availability Method'),
			'align'   => 'left'
		],
		[
			'display' => __('System Description Match'),
			'align'   => 'left'
		],
		[
			'display' => __('System Name Match'),
			'align'   => 'left'
		],
		[
			'display' => __('System OID Match'),
			'align'   => 'left'
		]
	];

	if (read_config_option('drag_and_drop') == '') {
		$display_text[] = ['display' => __('Order'), 'align' => 'center'];
	}

	html_header_checkbox($display_text, false);

	$total_items = cacti_sizeof($dts);

	if (cacti_sizeof($dts)) {
		$i = 0;

		foreach ($dts as $dt) {
			if ($dt['name'] == '') {
				$name = __('Unknown Template');
			} else {
				$name = $dt['name'];
			}

			form_alternate_row('line' . $dt['id'], true);

			form_selectable_cell(filter_value($name, grv('filter'), 'automation_templates.php?action=edit&id=' . $dt['id']), $dt['id']);
			form_selectable_cell($availability_options[$dt['availability_method']], $dt['id']);
			form_selectable_cell(filter_value($dt['sysDescr'], grv('filter')), $dt['id']);
			form_selectable_cell(filter_value($dt['sysName'], grv('filter')), $dt['id']);
			form_selectable_cell(filter_value($dt['sysOid'], grv('filter')), $dt['id']);

			if (read_config_option('drag_and_drop') == '') {
				$add_text = '';

				if ($i < $total_items && $total_items > 1) {
					$add_text .= '<a class="pic ti ti-caret-down-filled moveArrow" href="' . htmle('automation_templates.php?action=movedown&id=' . $dt['id']) . '" title="' . __esc('Move Down') . '"></a>';
				} else {
					$add_text .= '<span class="moveArrowNone"></span>';
				}

				if ($i > 1 && $i <= $total_items) {
					$add_text .= '<a class="pic ti ti-caret-up-filled moveArrow" href="' . htmle('automation_templates.php?action=moveup&id=' . $dt['id']) . '" title="' . __esc('Move Up') . '"></a>';
				} else {
					$add_text .= '<span class="moveArrowNone"></span>';
				}

				form_selectable_cell($add_text, $dt['id'], '', 'center');
			}

			form_checkbox_cell($name, $dt['id']);

			form_end_row();

			$i++;
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Automation Device Templates Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($dts)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#automation_templates2_child').attr('id', 'template_ids');

		$('img.action').click(function() {
			strURL = $(this).attr('href');
			loadUrl({url:strURL})
		});

		<?php if (read_config_option('drag_and_drop') == 'on') { ?>
		$('#template_ids').find('tr:first').addClass('nodrag').addClass('nodrop');

		$('#template_ids').tableDnD({
			onDrop: function(table, row) {
				loadUrl({url:'automation_templates.php?action=ajax_dnd&'+$.tableDnD.serialize()})
			}
		});
		<?php } ?>

	});
	</script>
	<?php
}
