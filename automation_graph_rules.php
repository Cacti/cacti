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
require_once(CACTI_PATH_LIBRARY . '/data_query.php');

$actions = [
	AUTOMATION_ACTION_GRAPH_DUPLICATE => __('Duplicate'),
	AUTOMATION_ACTION_GRAPH_ENABLE    => __('Enable'),
	AUTOMATION_ACTION_GRAPH_EXPORT    => __('Export'),
	AUTOMATION_ACTION_GRAPH_DISABLE   => __('Disable'),
	AUTOMATION_ACTION_GRAPH_DELETE    => __('Delete'),
];

// sanitize the tab
gfrv('tab', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-z_A-Z]+)$/']]);

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
	case 'actions':
		automation_graph_rules_form_actions();

		break;
	case 'import':
		top_header();
		automation_import();
		bottom_footer();

		break;
	case 'export':
		automation_export();

		break;
	case 'item_movedown':
		automation_graph_rules_item_movedown();

		header('Location: automation_graph_rules.php?action=edit&id=' . gfrv('id'));

		break;
	case 'item_moveup':
		automation_graph_rules_item_moveup();

		header('Location: automation_graph_rules.php?action=edit&id=' . gfrv('id'));

		break;
	case 'item_remove':
		automation_graph_rules_item_remove();

		header('Location: automation_graph_rules.php?action=edit&id=' . gfrv('id'));

		break;
	case 'item_edit':
		top_header();
		automation_graph_rules_item_edit();
		bottom_footer();

		break;
	case 'qedit':
		automation_change_query_type();

		header('Location: automation_graph_rules.php?action=edit&name=' . grv('name') . '&id=' . gfrv('id') . '&snmp_query_id=' . grv('snmp_query_id'));

		break;
	case 'remove':
		automation_graph_rules_remove();

		header('Location: automation_graph_rules.php');

		break;
	case 'edit':
		top_header();
		automation_graph_rules_edit();
		bottom_footer();

		break;
	default:
		top_header();
		automation_graph_rules();
		bottom_footer();

		break;
}

function automation_export() : void {
	draw_graph_rules_filter(false);

	$snmp_option_ids = [];

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (cacti_sizeof($selected_items) == 1) {
				$export_data = automation_graph_rule_export($selected_items[0]);
			} else {
				foreach ($selected_items as $id) {
					$snmp_option_ids[] = $id;
				}

				$export_data = automation_graph_rule_export($snmp_option_ids);
			}

			if (cacti_sizeof($export_data)) {
				$export_file_name = $export_data['export_name'];

				header('Content-type: application/json');
				header('Content-Disposition: attachment; filename=' . $export_file_name);

				$output = json_encode($export_data, JSON_PRETTY_PRINT);

				print $output;
			}
		}
	} else {
		raise_message(40);
		header('Location: automation_graph_rules.php');

		exit;
	}
}

function automation_import() : void {
	$form_data = [
		'import_file' => [
			'friendly_name' => __('Import Graph Rules from Local File'),
			'description'   => __('If the JSON file containing the Graph Rules data is located on your local machine, select it here.'),
			'method'        => 'file',
			'accept'        => '.json'
		],
		'import_text' => [
			'method'        => 'textarea',
			'friendly_name' => __('Import Graph Rules from Text'),
			'description'   => __('If you have the JSON file containing the Graph Rules data as text, you can paste it into this box to import it.'),
			'value'         => '',
			'default'       => '',
			'textarea_rows' => '10',
			'textarea_cols' => '80',
			'class'         => 'textAreaNotes'
		]
	];

	form_start('automation_graph_rules.php', 'chk', true);

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box(__('Import Results'), '60%', false, 3, 'center', '');

		print '<tr class="tableHeader"><th>' . __('Cacti has Imported the following Graph Rules') . '</th></tr>';

		foreach ($_SESSION['import_debug_info'] as $line) {
			print '<tr><td>' . $line . '</td></tr>';
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box(__('Import Graph Rules'), '60%', false, 3, 'center', '');

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
			<button type='submit' value='import' title='" . __esc('Import Graph Rules') . "' class='ui-button ui-corner-all ui-widget ui-state-active'>" . __esc('Import') . "</button>
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

	$return_data = automation_graph_rule_import($json_data);

	if (sizeof($return_data) && isset($return_data['success'])) {
		foreach ($return_data['success'] as $message) {
			$debug_data[] = '<span class="deviceUp">' . __('NOTE:') . '</span> ' . $message;
			automation_log('NOTE: Automation Graph Rules Import Succeeded!.  Message: ' . $message, AUTOMATION_LOG_LOW);
		}
	}

	if (isset($return_data['errors'])) {
		foreach ($return_data['errors'] as $error) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $error;
			automation_log('NOTE: Automation Graph Rules Import Error!.  Message: ' . $error, AUTOMATION_LOG_LOW);
		}
	}

	if (isset($return_data['failure'])) {
		foreach ($return_data['failure'] as $message) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $message;
			automation_log('NOTE: Automation Graph Rules Import Failed!.  Message: ' . $message, AUTOMATION_LOG_LOW);
		}
	}

	if (cacti_sizeof($debug_data)) {
		$_SESSION['import_debug_info'] = $debug_data;
	}

	header('Location: automation_graph_rules.php?action=import');

	exit();
}

function form_save() : void {
	if (isrv('save_component_automation_graph_rule')) {
		// ================= input validation =================
		gfrv('id');
		// ====================================================

		$save['id']            = gnrv('id');
		$save['hash']          = get_hash_automation(grv('id'), 'automation_graph_rules');
		$save['name']          = form_input_validate(gnrv('name'), 'name', '', false, 3);
		$save['snmp_query_id'] = form_input_validate(gnrv('snmp_query_id'), 'snmp_query_id', '^[0-9]+$', false, 3);
		$save['graph_type_id'] = (isrv('graph_type_id')) ? form_input_validate(gnrv('graph_type_id'), 'graph_type_id', '^[0-9]+$', false, 3) : 0;
		$save['enabled']       = (isrv('enabled') ? 'on' : '');

		if (!is_error_message()) {
			$rule_id = sql_save($save, 'automation_graph_rules');

			if ($rule_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: automation_graph_rules.php?action=edit&id=' . (empty($rule_id) ? gnrv('id') : $rule_id));
	} elseif (isrv('save_component_automation_graph_rule_item')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('item_id');
		// ====================================================

		$save              = [];
		$save['id']        = form_input_validate(grv('item_id'), 'item_id', '^[0-9]+$', false, 3);
		$save['hash']      = get_hash_automation(grv('id_item'), 'automation_graph_rule_items');
		$save['rule_id']   = form_input_validate(grv('id'), 'id', '^[0-9]+$', false, 3);
		$save['sequence']  = form_input_validate(gnrv('sequence'), 'sequence', '^[0-9]+$', false, 3);
		$save['operation'] = form_input_validate(gnrv('operation'), 'operation', '^[-0-9]+$', true, 3);
		$save['field']     = form_input_validate(((isrv('field') && gnrv('field') != '0') ? gnrv('field') : ''), 'field', '', true, 3);
		$save['operator']  = form_input_validate((isrv('operator') ? gnrv('operator') : ''), 'operator', '^[0-9]+$', true, 3);
		$save['pattern']   = form_input_validate((isrv('pattern') ? gnrv('pattern') : ''), 'pattern', '', true, 3);

		// Test for SQL injections
		$field_name = str_replace(['ht.', 'h.', 'gt.', 'gtg.'], '', $save['field']);

		$exists = db_fetch_cell_prepared('SELECT field_name
			FROM host_snmp_cache
			WHERE field_name = ?
			LIMIT 1',
			[$field_name]);

		if (!$exists) {
			// check the case where there is no entry in the host_snmp_cache table yet
			if ("'$field_name'" != db_qstr($field_name)) {
				if (!db_column_exists('host', $field_name) && !db_column_exists('host_template', $field_name) && !db_column_exists('graph_templates', $field_name) && !db_column_exists('graph_templates_graph', $field_name)) {
					raise_message('sql_injection', __('An attempt was made to perform a SQL injection in Graph automation'), MESSAGE_LEVEL_ERROR);

					cacti_log(sprintf('ERROR: An attempt was made to perform a SQL Injection in Graph Automation from client address \'%s\'', get_client_addr()), false, 'SECURITY');

					header('Location: automation_graph_rules.php?header=false&action=edit&id=' . grv('id'));

					exit;
				}
			}
		}

		$item_id = null;

		if (!is_error_message()) {
			$item_id = sql_save($save, 'automation_graph_rule_items');

			if ($item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: automation_graph_rules.php?action=item_edit&id=' . grv('id') . '&item_id=' . ($item_id === null ? grv('item_id') : $item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_ACTION);
		} else {
			header('Location: automation_graph_rules.php?action=edit&id=' . grv('id') . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_ACTION);
		}
	} elseif (isrv('save_component_automation_match_item')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('item_id');
		// ====================================================

		$save = [];

		$save['id']        = form_input_validate(grv('item_id'), 'item_id', '^[0-9]+$', false, 3);
		$save['hash']      = get_hash_automation(grv('item_idid'), 'automation_match_rule_items');
		$save['rule_id']   = form_input_validate(grv('id'), 'id', '^[0-9]+$', false, 3);
		$save['rule_type'] = AUTOMATION_RULE_TYPE_GRAPH_MATCH;
		$save['sequence']  = form_input_validate(gnrv('sequence'), 'sequence', '^[0-9]+$', false, 3);
		$save['operation'] = form_input_validate(gnrv('operation'), 'operation', '^[-0-9]+$', true, 3);
		$save['field']     = form_input_validate(((isrv('field') && gnrv('field') != '0') ? gnrv('field') : ''), 'field', '', true, 3);
		$save['operator']  = form_input_validate((isrv('operator') ? gnrv('operator') : ''), 'operator', '^[0-9]+$', true, 3);
		$save['pattern']   = form_input_validate((isrv('pattern') ? gnrv('pattern') : ''), 'pattern', '', true, 3);

		// Test for SQL injections
		$field_name = str_replace(['ht.', 'h.', 'gt.'], '', $save['field']);

		if (!db_column_exists('host', $field_name) && !db_column_exists('host_template', $field_name) && !db_column_exists('graph_templates', $field_name)) {
			raise_message('sql_injection', __('An attempt was made to perform a SQL injection in Graph automation'), MESSAGE_LEVEL_ERROR);

			cacti_log(sprintf('ERROR: An attempt was made to perform a SQL Injection in Graph Automation from client address \'%s\'', get_client_addr()), false, 'SECURITY');

			header('Location: automation_graph_rules.php?header=false&action=edit&id=' . grv('id') . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_ACTION);

			exit;
		}

		$item_id = null;

		if (!is_error_message()) {
			$item_id = sql_save($save, 'automation_match_rule_items');

			if ($item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: automation_graph_rules.php?action=item_edit&id=' . grv('id') . '&item_id=' . ($item_id === null ? grv('item_id') : $item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_MATCH);
		} else {
			header('Location: automation_graph_rules.php?action=edit&id=' . grv('id') . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_MATCH);
		}
	} else {
		raise_message(2);
		header('Location: automation_graph_rules.php');
	}
}

function automation_graph_rules_form_actions() : void {
	global $actions;

	// ================= input validation =================
	gfrv('drp_action');
	// ====================================================

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == AUTOMATION_ACTION_GRAPH_DELETE) { // delete
				db_execute('DELETE FROM automation_graph_rules WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM automation_graph_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
				db_execute('DELETE FROM automation_match_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
			} elseif (gnrv('drp_action') == AUTOMATION_ACTION_GRAPH_DUPLICATE) { // duplicate
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					automation_log('form_actions duplicate: ' . $selected_items[$i] . ' name: ' . gnrv('name_format'), AUTOMATION_LOG_HIGH);
					duplicate_automation_graph_rules($selected_items[$i], gnrv('name_format'));
				}
			} elseif (gnrv('drp_action') == AUTOMATION_ACTION_GRAPH_ENABLE) { // enable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					automation_log('form_actions enable: ' . $selected_items[$i], AUTOMATION_LOG_HIGH);

					db_execute_prepared("UPDATE automation_graph_rules
						SET enabled='on'
						WHERE id = ?",
						[$selected_items[$i]]);
				}
			} elseif (gnrv('drp_action') == AUTOMATION_ACTION_GRAPH_DISABLE) { // disable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					automation_log('form_actions disable: ' . $selected_items[$i], AUTOMATION_LOG_HIGH);

					db_execute_prepared("UPDATE automation_graph_rules
						SET enabled=''
						WHERE id = ?",
						[$selected_items[$i]]);
				}
			} elseif (gnrv('drp_action') == AUTOMATION_ACTION_GRAPH_EXPORT) { // export
				top_header();

				print '<script text="text/javascript">
					function DownloadStart(url) {
						document.getElementById("download_iframe").src = url;
						setTimeout(function() {
							loadUrl({ url: "automation_graph_rules.php" });
							Pace.stop();
						}, 500);
					}

					$(function() {
						//debugger;
						DownloadStart(\'automation_graph_rules.php?action=export&selected_items=' . gnrv('selected_items') . '\');
					});
				</script>
				<iframe id="download_iframe" style="display:none;"></iframe>';

				bottom_footer();

				exit;
			}
		}

		header('Location: automation_graph_rules.php');

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

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM automation_graph_rules WHERE id = ?', [$matches[1]])) . '</li>';
				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'automation_graph_rules.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				AUTOMATION_ACTION_GRAPH_DELETE => [
					'smessage' => __('Click \'Continue\' to Delete the following Graph Rule.'),
					'pmessage' => __('Click \'Continue\' to Delete following Graph Rules.'),
					'scont'    => __('Delete Graph Rule'),
					'pcont'    => __('Delete Graph Rules')
				],
				AUTOMATION_ACTION_GRAPH_ENABLE => [
					'smessage' => __('Click \'Continue\' to Enable the following Graph Rule.'),
					'pmessage' => __('Click \'Continue\' to Enable following Graph Rules.'),
					'scont'    => __('Enable Graph Rule'),
					'pcont'    => __('Enable Graph Rules')
				],
				AUTOMATION_ACTION_GRAPH_DISABLE => [
					'smessage' => __('Click \'Continue\' to Disable the following Graph Rule.'),
					'pmessage' => __('Click \'Continue\' to Disable following Graph Rules.'),
					'scont'    => __('Disable Graph Rule'),
					'pcont'    => __('Disable Graph Rules')
				],
				AUTOMATION_ACTION_GRAPH_EXPORT => [
					'smessage' => __('Click \'Continue\' to Export the following Graph Rule.'),
					'pmessage' => __('Click \'Continue\' to Export following Graph Rules.'),
					'scont'    => __('Export Graph Rule'),
					'pcont'    => __('Export Graph Rules')
				],
				AUTOMATION_ACTION_GRAPH_DUPLICATE => [
					'smessage' => __('Click \'Continue\' to Duplicate the following Graph Rule.'),
					'pmessage' => __('Click \'Continue\' to Duplicate following Graph Rules.'),
					'scont'    => __('Duplicate Graph Rule'),
					'pcont'    => __('Duplicate Graph Rules'),
					'extra'    => [
						'name_format' => [
							'method'  => 'textbox',
							'title'   => __('Title Format'),
							'default' => '<rule_name> (1)',
							'width'   => 25
						]
					]
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}

function automation_graph_rules_item_movedown() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('item_id');
	gfrv('rule_type');
	// ====================================================

	if (grv('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		move_item_down('automation_match_rule_items', grv('item_id'), 'rule_id=' . grv('id') . ' AND rule_type=' . grv('rule_type'));
	} elseif (grv('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		move_item_down('automation_graph_rule_items', grv('item_id'), 'rule_id=' . grv('id'));
	}
}

function automation_graph_rules_item_moveup() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('item_id');
	gfrv('rule_type');
	// ====================================================

	if (grv('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		move_item_up('automation_match_rule_items', grv('item_id'), 'rule_id=' . grv('id') . ' AND rule_type=' . grv('rule_type'));
	} elseif (grv('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		move_item_up('automation_graph_rule_items', grv('item_id'), 'rule_id=' . grv('id'));
	}
}

function automation_graph_rules_item_remove() : void {
	// ================= input validation =================
	gfrv('item_id');
	gfrv('rule_type');
	// ====================================================

	if (grv('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		db_execute_prepared('DELETE FROM automation_match_rule_items WHERE id = ?', [grv('item_id')]);
	} elseif (grv('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		db_execute_prepared('DELETE FROM automation_graph_rule_items WHERE id = ?', [grv('item_id')]);
	}
}

function automation_graph_rules_item_edit() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('item_id');
	gfrv('rule_type');
	// ====================================================

	global_item_edit(grv('id'), grv('item_id'), grv('rule_type'));

	form_hidden_box('rule_type', grv('rule_type'), grv('rule_type'));
	form_hidden_box('id', (isrv('id') ? grv('id') : '0'), '');
	form_hidden_box('item_id', (isrv('item_id') ? grv('item_id') : '0'), '');

	if (grv('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		form_hidden_box('save_component_automation_match_item', '1', '');
	} else {
		form_hidden_box('save_component_automation_graph_rule_item', '1', '');
	}

	form_save_button('automation_graph_rules.php?action=edit&id=' . grv('id') . '&rule_type=' . grv('rule_type'));

	?>
	<script type='text/javascript'>

	$(function() {
		toggle_operation();
		toggle_operator();
	});

	function toggle_operation() {
		if ($('#operation').val() == '<?php print AUTOMATION_OPER_RIGHT_BRACKET; ?>') {
			$('#field').val('');
			$('#field').prop('disabled', true);
			$('#operator').val(0);
			$('#operator').prop('disabled', true);
			$('#pattern').val('');
			$('#pattern').prop('disabled', true);
		} else {
			$('#field').prop('disabled', false);
			$('#operator').prop('disabled', false);
			$('#pattern').prop('disabled', false);
		}
	}

	function toggle_operator() {
		if ($('#operator').val() == '<?php print AUTOMATION_OPER_RIGHT_BRACKET; ?>') {
		} else {
		}
	}
	</script>
	<?php
}

function automation_graph_rules_remove() : void {
	// ================= input validation =================
	gfrv('id');
	// ====================================================

	db_execute_prepared('DELETE FROM automation_match_rule_items
		WHERE rule_id = ?
		AND rule_type = ?',
		[grv('id'), AUTOMATION_RULE_TYPE_GRAPH_MATCH]);

	db_execute_prepared('DELETE FROM automation_graph_rule_items
		WHERE rule_id = ?',
		[grv('id')]);

	db_execute_prepared('DELETE FROM automation_graph_rules
		WHERE id = ?',
		[grv('id')]);
}

function automation_change_query_type() : void {
	$id = gfrv('id');

	if (isrv('snmp_query_id') && $id > 0) {
		$snmp_query_id = gfrv('snmp_query_id');
		$name          = gnrv('name');

		db_execute_prepared('UPDATE automation_graph_rules
			SET snmp_query_id = ?, name = ?
			WHERE id = ?',
			[$snmp_query_id, $name, $id]);

		$graph_type = db_fetch_cell_prepared('SELECT id
			FROM snmp_query_graph
			WHERE snmp_query_id = ?
			ORDER BY name
			LIMIT 1', [$snmp_query_id]);

		db_execute_prepared('UPDATE automation_graph_rules
			SET graph_type_id = ?
			WHERE id = ?',
			[$graph_type, $id]);
	} elseif (isrv('graph_type_id') && $id > 0) {
		$snmp_query_id = gfrv('graph_type_id');
		$name          = gnrv('name');

		db_execute_prepared('UPDATE automation_graph_rules
			SET graph_type_id = ?, name = ?
			WHERE id = ?',
			[$snmp_query_id, $name, $id]);
	}
}

function automation_graph_rules_edit() : void {
	global $fields_automation_graph_rules_edit1;
	global $fields_automation_graph_rules_edit2;
	global $fields_automation_graph_rules_edit3;

	// ================= input validation =================
	gfrv('id');
	gfrv('snmp_query_id');
	gfrv('graph_type_id');
	// ====================================================

	if (!ierv('id')) {
		$rule = db_fetch_row_prepared('SELECT *
			FROM automation_graph_rules
			WHERE id = ?',
			[grv('id')]);

		if (!ierv('graph_type_id')) {
			$rule['graph_type_id'] = grv('graph_type_id');
		}

		$header_label = __esc('Rule Selection [edit: %s]', $rule['name']);

		$tabs = [
			'rule'    => __('Rule Name'),
			'hosts'   => __('Matching Devices'),
			'objects' => __('Matching Indexes')
		];

		html_sub_tabs($tabs, 'action=edit&id=' . grv('id'));
	} else {
		$rule = [
			'name'          => grv('name'),
			'snmp_query_id' => grv('snmp_query_id'),
		];

		$header_label = __('Rule Selection [new]');

		$tabs = [
			'rule'    => __('Rule Name')
		];

		html_sub_tabs($tabs, 'action=edit&id=' . grv('id'));
	}

	if (gnrv('tab') == 'rule') {
		form_start('automation_graph_rules.php', 'chk');

		html_start_box($header_label, '100%', true, 3, 'center', '');

		if (!ierv('id')) {
			// display whole rule
			$form_array = $fields_automation_graph_rules_edit1 +
				$fields_automation_graph_rules_edit2 + $fields_automation_graph_rules_edit3;
		} else {
			// display first part of rule only and request user to proceed
			$form_array = $fields_automation_graph_rules_edit1;
		}

		if (isrv('name')) {
			$rule['name'] = grv('name');
		}

		draw_edit_form(
			[
				'config' => ['no_form_tag' => true],
				'fields' => inject_form_variables($form_array, $rule)
			]
		);

		html_end_box(true, true);

		form_hidden_box('id', (isset($rule['id']) ? $rule['id'] : '0'), '');
		form_hidden_box('item_id', (isset($rule['item_id']) ? $rule['item_id'] : '0'), '');
		form_hidden_box('save_component_automation_graph_rule', '1', '');

		// display the rule items -------------------------------------------------------------------------------

		if (isset($rule['id'])) {
			// display graph rules for host match
			display_match_rule_items(__('Device Selection Criteria'), $rule, AUTOMATION_RULE_TYPE_GRAPH_MATCH, 'automation_graph_rules.php');

			// fetch graph action rules
			display_graph_rule_items(__('Graph Creation Criteria'), $rule, AUTOMATION_RULE_TYPE_GRAPH_ACTION, 'automation_graph_rules.php');
		}

		form_save_button('automation_graph_rules.php', 'return');

		print '<br>';
	} elseif (gnrv('tab') == 'hosts') {
		display_matching_hosts($rule, AUTOMATION_RULE_TYPE_GRAPH_MATCH, 'automation_graph_rules.php?action=edit&id=' . grv('id'));
	} elseif (gnrv('tab') == 'objects') {
		display_new_graphs($rule, 'automation_graph_rules.php?action=edit&id=' . grv('id'));
	}

	?>
	<script type='text/javascript'>
	function applySNMPQueryIdChange() {
		strURL  = 'automation_graph_rules.php?action=qedit';
		strURL += '&id=' + $('#id').val();
		strURL += '&name=' + $('#name').val();
		strURL += '&snmp_query_id=' + $('#snmp_query_id').val();
		loadUrl({url:strURL})
	}

	function applySNMPQueryTypeChange() {
		strURL  = 'automation_graph_rules.php?action=qedit'
		strURL += '&id=' + $('#id').val();
		strURL += '&name=' + $('#name').val();
		strURL += '&graph_type_id=' + $('#graph_type_id').val();
		loadUrl({url:strURL})
	}

	$(function() {
		$('#show_device_sql').click(function(event) {
			event.stopPropagation();
			$('#sql_device_query').dialog({
				'title': '<?php print __('SQL Debug Output'); ?>',
				'autoOpen': true,
				'width': 700
			});
		});

		$('#show_sql').click(function(event) {
			event.stopPropagation();
			$('#sql_query').dialog({
				'title': '<?php print __('SQL Debug Output'); ?>',
				'autoOpen': true,
				'width': 700
			});
		});
	});
	</script>
	<?php
}

function create_graph_rules_filter() : array {
	global $item_rows;

	$any = [-1 => __('Any')];

	$data_queries = array_rekey(
		db_fetch_assoc('SELECT DISTINCT sq.id, sq.name
			FROM automation_graph_rules AS ar
			LEFT JOIN snmp_query AS sq
			ON ar.snmp_query_id=sq.id
			ORDER BY sq.name'),
		'id', 'name'
	);

	$status_arr = [
		'-1' => __('Any'),
		'-2' => __('Enabled'),
		'-3' => __('Disabled')
	];

	$queries_arr  = $any + $data_queries;

	return [
		'rows' => [
			[
				'filter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_DEFAULT,
					'placeholder'    => __('Enter a search term'),
					'size'           => '30',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				],
				'snmp_query_id' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Data Query'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $queries_arr,
					'value'         => '-1'
				],
				'status' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Status'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $status_arr,
					'value'         => '-1'
				],
				'rows' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Graph Rules'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $item_rows,
					'value'         => '-1'
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply filter to table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset filter to default values'),
			],
			'import' => [
				'method'  => 'button',
				'display' => __('Import'),
				'action'  => 'default',
				'title'   => __('Import Graph Rules'),
			]
		],
		'sort' => [
			'sort_column'    => 'name',
			'sort_direction' => 'ASC'
		]
	];
}

function draw_graph_rules_filter(bool $render = false) : void {
	$filters = create_graph_rules_filter();

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Graph Rules'), 'automation_graph_rules.php', 'form_automation', 'sess_autom_gr', 'automation_graph_rules.php?action=edit');

	$pageFilter->rows_label = __('Graph Rules');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function automation_graph_rules() : void {
	global $actions, $item_rows;

	draw_graph_rules_filter(true);

	if (grv('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$sql_where  = '';
	$sql_params = [];

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where    = 'WHERE (agr.name LIKE ? OR sqg.name LIKE ? OR sq.name LIKE ?)';

		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
	}

	if (grv('status') == '-2') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . "agr.enabled = 'on'";
	} elseif (grv('status') == '-3') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . "agr.enabled = ''";
	}

	if (grv('snmp_query_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . 'agr.snmp_query_id = ?';
		$sql_params[] = grv('snmp_query_id');
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(agr.id)
		FROM automation_graph_rules AS agr
		LEFT JOIN snmp_query AS sq
		ON agr.snmp_query_id = sq.id
		LEFT JOIN snmp_query_graph AS sqg
		ON agr.graph_type_id = sqg.id
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$automation_graph_rules_list = db_fetch_assoc_prepared("SELECT agr.id, agr.name,
		agr.snmp_query_id, agr.graph_type_id,
		agr.enabled, sq.name AS snmp_query_name, sqg.name AS graph_type_name
		FROM automation_graph_rules AS agr
		LEFT JOIN snmp_query AS sq
		ON agr.snmp_query_id = sq.id
		LEFT JOIN snmp_query_graph AS sqg
		ON agr.graph_type_id = sqg.id
		$sql_where
		$sql_order
		$sql_limit",
		$sql_params);

	$nav = html_nav_bar('automation_graph_rules.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 7, __('Graph Rules'), 'page', 'main');

	form_start('automation_graph_rules.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'name' => [
			'display' => __('Rule Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this rule.')
		],
		'id' => [
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal database ID for this rule.  Useful in performing debugging and automation.')
		],
		'snmp_query_name' => [
			'display' => __('Data Query'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'graph_type_name' => [
			'display' => __('Graph Type'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'enabled' => [
			'display' => __('Enabled'),
			'align'   => 'right',
			'sort'    => 'ASC'
		]
	];

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($automation_graph_rules_list)) {
		foreach ($automation_graph_rules_list as $automation_graph_rules) {
			$snmp_query_name 		 = ((empty($automation_graph_rules['snmp_query_name'])) ? __('None') : htmle($automation_graph_rules['snmp_query_name']));
			$graph_type_name 		 = ((empty($automation_graph_rules['graph_type_name'])) ? __('None') : htmle($automation_graph_rules['graph_type_name']));

			form_alternate_row('line' . $automation_graph_rules['id'], true);

			form_selectable_cell(filter_value($automation_graph_rules['name'], grv('filter'), 'automation_graph_rules.php?action=edit&id=' . $automation_graph_rules['id'] . '&page=1'), $automation_graph_rules['id']);
			form_selectable_cell($automation_graph_rules['id'], $automation_graph_rules['id'], '', 'text-align:right');
			form_selectable_cell(filter_value($snmp_query_name, grv('filter')), $automation_graph_rules['id']);
			form_selectable_cell(filter_value($graph_type_name, grv('filter')), $automation_graph_rules['id']);
			form_selectable_cell($automation_graph_rules['enabled'] ? __('Enabled') : __('Disabled'), $automation_graph_rules['id'], '', 'text-align:right');
			form_checkbox_cell($automation_graph_rules['name'], $automation_graph_rules['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Graph Rules Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($automation_graph_rules_list)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
