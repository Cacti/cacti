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
require_once(CACTI_PATH_LIBRARY . '/data_query.php');

$actions = [
	AUTOMATION_ACTION_TREE_DUPLICATE => __('Duplicate'),
	AUTOMATION_ACTION_TREE_ENABLE    => __('Enable'),
	AUTOMATION_ACTION_TREE_EXPORT    => __('Export'),
	AUTOMATION_ACTION_TREE_DISABLE   => __('Disable'),
	AUTOMATION_ACTION_TREE_DELETE    => __('Delete'),
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
	case 'import':
		top_header();
		automation_import();
		bottom_footer();

		break;
	case 'export':
		automation_export();

		break;
	case 'actions':
		automation_tree_rules_form_actions();

		break;
	case 'change_leaf':
		automation_tree_rules_change_leaf();

		header('Location: automation_tree_rules.php?action=edit&id=' . grv('id'));

		break;
	case 'item_movedown':
		automation_tree_rules_item_movedown();

		header('Location: automation_tree_rules.php?action=edit&id=' . grv('id'));

		break;
	case 'item_moveup':
		automation_tree_rules_item_moveup();

		header('Location: automation_tree_rules.php?action=edit&id=' . grv('id'));

		break;
	case 'item_remove':
		automation_tree_rules_item_remove();

		header('Location: automation_tree_rules.php?action=edit&id=' . grv('id'));

		break;
	case 'item_edit':
		top_header();
		automation_tree_rules_item_edit();
		bottom_footer();

		break;
	case 'remove':
		automation_tree_rules_remove();

		header('Location: automation_tree_rules.php');

		break;
	case 'edit':
		top_header();
		automation_tree_rules_edit();
		bottom_footer();

		break;
	default:
		top_header();
		automation_tree_rules();
		bottom_footer();

		break;
}

function automation_export() : void {
	draw_tree_rules_filter(true);

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		$snmp_option_ids = [];

		if ($selected_items != false) {
			if (cacti_sizeof($selected_items) == 1) {
				$export_data = automation_tree_rule_export($selected_items[0]);
			} else {
				foreach ($selected_items as $id) {
					$snmp_option_ids[] = $id;
				}

				$export_data = automation_tree_rule_export($snmp_option_ids);
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
		header('Location: automation_tree_rules.php');

		exit;
	}
}

function automation_import() : void {
	$form_data = [
		'import_file' => [
			'friendly_name' => __('Import Tree Rules from Local File'),
			'description'   => __('If the JSON file containing the Tree Rules data is located on your local machine, select it here.'),
			'method'        => 'file',
			'accept'        => '.json'
		],
		'import_text' => [
			'method'        => 'textarea',
			'friendly_name' => __('Import Tree Rules from Text'),
			'description'   => __('If you have the JSON file containing the Tree Rules data as text, you can paste it into this box to import it.'),
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

	form_start('automation_tree_rules.php', 'chk', true);

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box(__('Import Results'), '60%', false, 3, 'center', '');

		print '<tr class="tableHeader"><th>' . __('Cacti has Imported the following Tree Rules') . '</th></tr>';

		foreach ($_SESSION['import_debug_info'] as $line) {
			print '<tr><td>' . $line . '</td></tr>';
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box(__('Import Tree Rules'), '60%', false, 3, 'center', '');

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
			<button type='submit' value='import' title='" . __esc('Import Tree Rules') . "' class='ui-button ui-corner-all ui-widget ui-state-active'>" . __esc('Import') . "</butotn>
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

	$return_data = automation_tree_rule_import($json_data, $trees);

	if (sizeof($return_data) && isset($return_data['success'])) {
		foreach ($return_data['success'] as $message) {
			$debug_data[] = '<span class="deviceUp">' . __('NOTE:') . '</span> ' . $message;
			automation_log('NOTE: Automation Tree Rules Import Succeeded!  Message: ' . $message, AUTOMATION_LOG_LOW);
		}
	}

	if (isset($return_data['errors'])) {
		foreach ($return_data['errors'] as $error) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $error;
			automation_log('NOTE: Automation Tree Rules Import Error!  Message: ' . $error, AUTOMATION_LOG_LOW);
		}
	}

	if (isset($return_data['failure'])) {
		foreach ($return_data['failure'] as $message) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $message;
			automation_log('NOTE: Automation Tree Rules Import Failed!  Message: ' . $message, AUTOMATION_LOG_LOW);
		}
	}

	if (cacti_sizeof($debug_data)) {
		$_SESSION['import_debug_info'] = $debug_data;
	}

	header('Location: automation_tree_rules.php?action=import');

	exit();
}

function form_save() : void {
	if (isrv('save_component_automation_tree_rule')) {
		// ================= input validation =================
		gfrv('id');
		// ====================================================

		$save['id']                 = grv('id');
		$save['hash']               = get_hash_automation(grv('id'), 'automation_tree_rules');
		$save['name']               = form_input_validate(gnrv('name'), 'name', '', true, 3);
		$save['tree_id']            = form_input_validate(gnrv('tree_id'), 'tree_id', '^[0-9]+$', false, 3);
		$save['tree_item_id']       = isrv('tree_item_id') ? form_input_validate(gnrv('tree_item_id'), 'tree_item_id', '^[0-9]+$', false, 3) : 0;
		$save['leaf_type']          = (isrv('leaf_type')) ? form_input_validate(gnrv('leaf_type'), 'leaf_type', '^[0-9]+$', false, 3) : 0;
		$save['host_grouping_type'] = isrv('host_grouping_type') ? form_input_validate(gnrv('host_grouping_type'), 'host_grouping_type', '^[0-9]+$', false, 3) : 0;
		$save['enabled']            = (isrv('enabled') ? 'on' : '');

		if (!is_error_message()) {
			$rule_id = sql_save($save, 'automation_tree_rules');

			if ($rule_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: automation_tree_rules.php?action=edit&id=' . (empty($rule_id) ? grv('id') : $rule_id));
	} elseif (isrv('save_component_automation_match_item')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('item_id');
		// ====================================================

		$save              = [];
		$save['id']        = form_input_validate(grv('item_id'), 'item_id', '^[0-9]+$', false, 3);
		$save['hash']      = get_hash_automation(grv('item_id'), 'automation_match_rule_items');
		$save['rule_id']   = form_input_validate(grv('id'), 'id', '^[0-9]+$', false, 3);
		$save['rule_type'] = AUTOMATION_RULE_TYPE_TREE_MATCH;
		$save['sequence']  = form_input_validate(gnrv('sequence'), 'sequence', '^[0-9]+$', false, 3);
		$save['operation'] = form_input_validate(gnrv('operation'), 'operation', '^[-0-9]+$', true, 3);
		$save['field']     = form_input_validate(((isrv('field') && gnrv('field') != '0') ? gnrv('field') : ''), 'field', '', true, 3);
		$save['operator']  = form_input_validate((isrv('operator') ? gnrv('operator') : ''), 'operator', '^[0-9]+$', true, 3);
		$save['pattern']   = form_input_validate((isrv('pattern') ? gnrv('pattern') : ''), 'pattern', '', true, 3);

		// Test for SQL injections
		$field_name = str_replace(['ht.', 'h.', 'gt.'], '', $save['field']);

		if (!db_column_exists('host', $field_name) && !db_column_exists('host_template', $field_name) && !db_column_exists('graph_templates', $field_name)) {
			raise_message('sql_injection', __('An attempt was made to perform a SQL injection in Tree automation'), MESSAGE_LEVEL_ERROR);

			cacti_log(sprintf('ERROR: An attempt was made to perform a SQL Injection in Tree automation from client address \'%s\'', get_client_addr()), false, 'SECURITY');

			header('Location: automation_tree_rules.php?header=false&action=item_edit&id=' . grv('id') . '&item_id=' . grv('item_id') . '&rule_type=' . AUTOMATION_RULE_TYPE_TREE_MATCH);

			exit;
		}

		if (!is_error_message()) {
			$item_id = sql_save($save, 'automation_match_rule_items');

			if ($item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: automation_tree_rules.php?action=item_edit&id=' . grv('id') . '&item_id=' . grv('item_id') . '&rule_type=' . AUTOMATION_RULE_TYPE_TREE_MATCH);
		} else {
			header('Location: automation_tree_rules.php?action=edit&id=' . grv('id') . '&rule_type=' . AUTOMATION_RULE_TYPE_TREE_MATCH);
		}
	} elseif (isrv('save_component_automation_tree_rule_item')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('item_id');
		// ====================================================

		$save = [];

		$save['id']                = form_input_validate(grv('item_id'), 'item_id', '^[0-9]+$', false, 3);
		$save['hash']              = get_hash_automation(grv('item_id'), 'automation_tree_rule_items');
		$save['rule_id']           = form_input_validate(grv('id'), 'id', '^[0-9]+$', false, 3);
		$save['sequence']          = form_input_validate(gnrv('sequence'), 'sequence', '^[0-9]+$', false, 3);
		$save['field']             = form_input_validate((isrv('field') ? gnrv('field') : ''), 'field', '', true, 3);
		$save['sort_type']         = form_input_validate(gnrv('sort_type'), 'sort_type', '^[0-9]+$', false, 3);
		$save['propagate_changes'] = (isrv('propagate_changes') ? 'on' : '');
		$save['search_pattern']    = isrv('search_pattern') ? form_input_validate(gnrv('search_pattern'), 'search_pattern', '', false, 3) : '';
		$save['replace_pattern']   = isrv('replace_pattern') ? form_input_validate(gnrv('replace_pattern'), 'replace_pattern', '', true, 3) : '';

		$automation_graph_rule_item_id = null;

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
					raise_message('sql_injection', __('An attempt was made to perform a SQL injection in Graph Tree automation'), MESSAGE_LEVEL_ERROR);

					cacti_log(sprintf('ERROR: An attempt was made to perform a SQL Injection in Graph Tree Automation from client address \'%s\'', get_client_addr()), false, 'SECURITY');

					header('Location: automation_tree_rules.php?header=false&action=edit&id=' . get_request_var('id'));

					exit;
				}
			}
		}

		if (!is_error_message()) {
			$automation_graph_rule_item_id = sql_save($save, 'automation_tree_rule_items');

			if ($automation_graph_rule_item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: automation_tree_rules.php?action=item_edit&id=' . grv('id') . '&item_id=' . ($automation_graph_rule_item_id === null ? grv('item_id') : $automation_graph_rule_item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_TREE_ACTION);
		} else {
			header('Location: automation_tree_rules.php?action=edit&id=' . grv('id') . '&rule_type=' . AUTOMATION_RULE_TYPE_TREE_ACTION);
		}
	} else {
		raise_message(2);
		header('Location: automation_tree_rules.php');
	}
}

function automation_tree_rules_form_actions() : void {
	global $actions;

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == AUTOMATION_ACTION_TREE_DELETE) { // DELETE
				automation_log('form_actions DELETE: ' . serialize($selected_items), AUTOMATION_LOG_HIGH);

				db_execute('DELETE FROM automation_tree_rules WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM automation_tree_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
				db_execute('DELETE FROM automation_match_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
			} elseif (gnrv('drp_action') == AUTOMATION_ACTION_TREE_DUPLICATE) { // duplicate
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					automation_log('form_actions duplicate: ' . $selected_items[$i], AUTOMATION_LOG_HIGH);

					duplicate_automation_tree_rules($selected_items[$i], gnrv('name_format'));
				}
			} elseif (gnrv('drp_action') == AUTOMATION_ACTION_TREE_ENABLE) { // enable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					automation_log('form_actions enable: ' . $selected_items[$i], AUTOMATION_LOG_HIGH);

					db_execute_prepared("UPDATE automation_tree_rules SET enabled='on' WHERE id = ?", [$selected_items[$i]]);
				}
			} elseif (gnrv('drp_action') == AUTOMATION_ACTION_TREE_DISABLE) { // disable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					automation_log('form_actions disable: ' . $selected_items[$i], AUTOMATION_LOG_HIGH);

					db_execute_prepared("UPDATE automation_tree_rules SET enabled='' WHERE id = ?", [$selected_items[$i]]);
				}
			} elseif (gnrv('drp_action') == AUTOMATION_ACTION_TREE_EXPORT) { // export
				top_header();

				print '<script text="text/javascript">
					function DownloadStart(url) {
						document.getElementById("download_iframe").src = url;
						setTimeout(function() {
							loadUrl({ url: "automation_tree_rules.php" });
							Pace.stop();
						}, 500);
					}

					$(function() {
						//debugger;
						DownloadStart(\'automation_tree_rules.php?action=export&selected_items=' . gnrv('selected_items') . '\');
					});
				</script>
				<iframe id="download_iframe" style="display:none;"></iframe>';

				bottom_footer();

				exit;
			}
		}

		header('Location: automation_tree_rules.php');

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

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM automation_tree_rules WHERE id = ?', [$matches[1]])) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'automation_tree_rules.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				AUTOMATION_ACTION_TREE_DELETE => [
					'smessage' => __('Click \'Continue\' to Delete the following Tree Rule.'),
					'pmessage' => __('Click \'Continue\' to Delete following Tree Rules.'),
					'scont'    => __('Delete Tree Rule'),
					'pcont'    => __('Delete Tree Rules')
				],
				AUTOMATION_ACTION_TREE_ENABLE => [
					'smessage' => __('Click \'Continue\' to Enable the following Tree Rule.'),
					'pmessage' => __('Click \'Continue\' to Enable following Tree Rules.'),
					'scont'    => __('Enable Tree Rule'),
					'pcont'    => __('Enable Tree Rules')
				],
				AUTOMATION_ACTION_TREE_DISABLE => [
					'smessage' => __('Click \'Continue\' to Disable the following Tree Rule.'),
					'pmessage' => __('Click \'Continue\' to Disable following Tree Rules.'),
					'scont'    => __('Disable Tree Rule'),
					'pcont'    => __('Disable Tree Rules')
				],
				AUTOMATION_ACTION_TREE_EXPORT => [
					'smessage' => __('Click \'Continue\' to Export the following Tree Rule.'),
					'pmessage' => __('Click \'Continue\' to Export following Tree Rules.'),
					'scont'    => __('Export Tree Rule'),
					'pcont'    => __('Export Tree Rules')
				],
				AUTOMATION_ACTION_TREE_DUPLICATE => [
					'smessage' => __('Click \'Continue\' to Duplicate the following Tree Rule.'),
					'pmessage' => __('Click \'Continue\' to Duplicate following Tree Rules.'),
					'scont'    => __('Duplicate Tree Rule'),
					'pcont'    => __('Duplicate Tree Rules'),
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

function automation_tree_rules_change_leaf() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('leaf_type');
	// ====================================================

	automation_change_tree_rule_leaf_type(grv('leaf_type'), grv('id'));
}

function automation_tree_rules_item_movedown() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('item_id');
	gfrv('rule_type');
	// ====================================================

	if (grv('rule_type') == AUTOMATION_RULE_TYPE_TREE_MATCH) {
		move_item_down('automation_match_rule_items', grv('item_id'), 'rule_id=' . grv('id') . ' AND rule_type=' . grv('rule_type'));
	} elseif (grv('rule_type') == AUTOMATION_RULE_TYPE_TREE_ACTION) {
		move_item_down('automation_tree_rule_items', grv('item_id'), 'rule_id=' . grv('id'));
	}
}

function automation_tree_rules_item_moveup() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('item_id');
	gfrv('rule_type');
	// ====================================================

	if (grv('rule_type') == AUTOMATION_RULE_TYPE_TREE_MATCH) {
		move_item_up('automation_match_rule_items', grv('item_id'), 'rule_id=' . grv('id') . ' AND rule_type=' . grv('rule_type'));
	} elseif (grv('rule_type') == AUTOMATION_RULE_TYPE_TREE_ACTION) {
		move_item_up('automation_tree_rule_items', grv('item_id'), 'rule_id=' . grv('id'));
	}
}

function automation_tree_rules_item_remove() : void {
	// ================= input validation =================
	gfrv('item_id');
	gfrv('rule_type');
	// ====================================================

	if (grv('rule_type') == AUTOMATION_RULE_TYPE_TREE_MATCH) {
		db_execute_prepared('DELETE FROM automation_match_rule_items WHERE id = ?', [grv('item_id')]);
	} elseif (grv('rule_type') == AUTOMATION_RULE_TYPE_TREE_ACTION) {
		db_execute_prepared('DELETE FROM automation_tree_rule_items WHERE id = ?', [grv('item_id')]);
	}
}

function automation_tree_rules_item_edit() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('item_id');
	gfrv('rule_type');
	// ====================================================

	$item = [];

	if (!ierv('id')) {
		$tabs = [
			'rule'    => __('Rule Item')
		];

		if (!ierv('rule_type') && !ierv('item_id')) {
			if (grv('rule_type') == AUTOMATION_RULE_TYPE_TREE_ACTION) {
				$item = db_fetch_row_prepared('SELECT *
					FROM automation_tree_rule_items
					WHERE id = ?',
					[grv('item_id')]);

				if ($item['field'] != AUTOMATION_TREE_ITEM_TYPE_STRING) {
					$tabs['objects'] = __('Matching Items');
				}
			}
		}

		html_sub_tabs($tabs, 'action=item_edit&id=' . grv('id') . '&item_id=' . grv('item_id') . '&rule_type=' . grv('rule_type'));
	} else {
		$tabs = [
			'rule' => __('Rule Item')
		];

		html_sub_tabs($tabs, 'action=item_edit&id=' . grv('id') . '&item_id=' . grv('item_id') . '&rule_type=' . grv('rule_type'));
	}

	if (!isrv('tab') || grv('tab') == 'rule') {
		global_item_edit(grv('id'), grv('item_id'), grv('rule_type'));

		form_hidden_box('rule_type', grv('rule_type'), grv('rule_type'));
		form_hidden_box('id', (isrv('id') ? grv('id') : '0'), '');
		form_hidden_box('item_id', (isrv('item_id') ? grv('item_id') : '0'), '');

		if (grv('rule_type') == AUTOMATION_RULE_TYPE_TREE_MATCH) {
			form_hidden_box('save_component_automation_match_item', '1', '');
		} else {
			form_hidden_box('save_component_automation_tree_rule_item', '1', '');
		}

		form_save_button('automation_tree_rules.php?action=edit&id=' . grv('id') . '&rule_type=' . grv('rule_type'));
	} elseif (!ierv('rule_type') && !ierv('item_id') && cacti_sizeof($item)) {
		// display list of matching trees
		if (grv('rule_type') == AUTOMATION_RULE_TYPE_TREE_ACTION &&
			$item['field'] != AUTOMATION_TREE_ITEM_TYPE_STRING) {
			display_matching_trees(grv('id'), AUTOMATION_RULE_TYPE_TREE_ACTION, $item, 'automation_tree_rules.php?action=item_edit&id=' . grv('id') . '&item_id=' . grv('item_id') . '&rule_type=' . grv('rule_type'));
		}
	}

	?>
	<script type='text/javascript'>

	applyHeaderChange();
	toggle_operation();
	toggle_operator();

	function applyHeaderChange() {
		if ($('#rule_type').val() == '<?php print AUTOMATION_RULE_TYPE_TREE_ACTION; ?>') {
			if ($('#field').val() == '<?php print AUTOMATION_TREE_ITEM_TYPE_STRING; ?>') {
				$('#replace_pattern').val('');
				$('#replace_pattern').prop('disabled', true);
			} else {
				$('#replace_pattern').prop('disabled', false);
			}
		}
	}

	function toggle_operation() {
		// right bracket ')' does not come with a field
		if ($('operation').value == '<?php print AUTOMATION_OPER_RIGHT_BRACKET; ?>') {
			//alert('Sequence is '' + document.getElementById('sequence').value + ''');
			$('#field').val('');
			$('#field').prop('disabled', true);
			$('#operator').val(0);
			$('#operator').prop('disabled', true);
			$('#pattern').val('');
			$('#pattern').prop('disabled', true);
		} else {
			$('#field').prop('disabled', false);
			$('#operator').prop('disabled', false);
			$('#pattern').prop('disabled', false)
		}
	}

	function toggle_operator() {
		// if operator is not 'binary', disable the 'field' for matching strings
		if ($('#operator').val() == '<?php print AUTOMATION_OPER_RIGHT_BRACKET; ?>') {
			//alert('Sequence is '' + document.getElementById('sequence').value + ''');
		} else {
		}
	}
	</script>
	<?php
}

function automation_tree_rules_remove() : void {
	// ================= input validation =================
	gfrv('id');
	// ====================================================

	db_execute_prepared('DELETE FROM automation_match_rule_items
		WHERE rule_id = ?
		AND rule_type = ?',
		[grv('id'), AUTOMATION_RULE_TYPE_TREE_MATCH]);

	db_execute_prepared('DELETE FROM automation_tree_rule_items
		WHERE rule_id = ?',
		[grv('id')]);

	db_execute_prepared('DELETE FROM automation_tree_rules
		WHERE id = ?',
		[grv('id')]);
}

function automation_tree_rules_edit() : void {
	global $fields_automation_tree_rules_edit1, $fields_automation_tree_rules_edit2, $fields_automation_tree_rules_edit3;

	require_once(CACTI_PATH_LIBRARY . '/html_tree.php');

	// ================= input validation =================
	gfrv('id');
	gfrv('rows');
	gfrv('tree_id');
	gfrv('leaf_type');
	gfrv('host_grouping_type');
	gfrv('tree_item_id');
	gfrv('show_hosts');
	// ====================================================

	// clean up rule name
	if (isrv('name')) {
		srv('name', sanitize_search_string(grv('name')));
	}

	// remember these search fields in session vars so we don't have to keep passing them around
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	$rule = [];

	if (grv('id') > 0) {
		$rule = db_fetch_row_prepared('SELECT *
			FROM automation_tree_rules
			WHERE id = ?',
			[grv('id')]);

		if ($rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
			$tabs = [
				'rule'    => __('Rule Name'),
				'hosts'   => __('Matching Devices')
			];
		} else {
			$tabs = [
				'rule'     => __('Rule Name'),
				'graphs'   => __('Matching Graphs')
			];
		}

		html_sub_tabs($tabs, 'action=edit&id=' . gfrv('id'));

		$header_label = __esc('Tree Rule Selection [edit: %s]', $rule['name']);
	} else {
		$tabs = [
			'rule'    => __('Rule Name')
		];

		html_sub_tabs($tabs, 'action=edit&id=' . gfrv('id'));

		$header_label = __('Tree Rules Selection [new]');
	}

	if (gnrv('tab') == 'rule') {
		/**
		 * display the rule
		 */
		form_start('automation_tree_rules.php', 'form_automation_tree_rule_edit');

		html_start_box($header_label, '100%', true, 3, 'center', '');

		if (grv('id') > 0) {
			// display whole rule
			$form_array = $fields_automation_tree_rules_edit1 +
				$fields_automation_tree_rules_edit2 +
				$fields_automation_tree_rules_edit3;
		} else {
			// display first part of rule only and request user to proceed
			$form_array = $fields_automation_tree_rules_edit1;
		}

		if (isrv('name')) {
			$rule['name'] = gnrv('name');
		}

		if (isrv('tree_id')) {
			$rule['tree_id'] = gnrv('tree_id');
		}

		if (isrv('tree_item_id')) {
			$rule['tree_item_id'] = gnrv('tree_item_id');
		}

		if (isrv('leaf_type')) {
			$rule['leaf_type'] = gnrv('leaf_type');
		}

		if (isrv('enabled')) {
			if (gnrv('enabled') == 'true') {
				$rule['enabled'] = 'on';
			} else {
				$rule['enabled'] = '';
			}
		}

		draw_edit_form([
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($form_array, $rule)
		]);

		form_hidden_box('id', (isset($rule['id']) ? $rule['id'] : '0'), '');
		form_hidden_box('item_id', (isset($rule['item_id']) ? $rule['item_id'] : '0'), '');
		form_hidden_box('save_component_automation_tree_rule', '1', '');

		html_end_box(true, true);

		/**
		 * display the rule items
		 */
		if (isset($rule['id'])) {
			// display tree rules for host match
			display_match_rule_items(__('Object Selection Criteria'), $rule, AUTOMATION_RULE_TYPE_TREE_MATCH, 'automation_tree_rules.php');

			// fetch tree action rules
			display_tree_rule_items(__('Tree Creation Criteria'), $rule, $rule['leaf_type'], AUTOMATION_RULE_TYPE_TREE_ACTION, 'automation_tree_rules.php');
		}

		form_save_button('automation_tree_rules.php', 'return');
	} elseif ($rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
		display_matching_hosts($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, 'automation_tree_rules.php?action=edit&tab=hosts&id=' . grv('id'));
	} elseif ($rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
		display_matching_graphs($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, 'automation_tree_rules.php?action=edit&tab=graphs&id=' . grv('id'));
	}

	?>
	<script type='text/javascript'>
	var automationLeafTypeOriginal = $('#leaf_type').val();

	<?php
	if (!empty($rule['id'])) {
		print 'var isNew = false;';
	} else {
		print 'var isNew = true;';
	}
	?>

	$(function() {
		applyItemTypeChange();

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

	function applyTreeChange(action,force) {
		if (force == undefined) {
			force = false;
		}

		if (action == undefined) {
			action = 'edit';
		}

		strURL  = 'automation_tree_rules.php?action=' + action;
		strURL += '&id=' + $('#id').val();
		strURL += '&name=' + $('#name').val();
		strURL += '&tree_id=' + $('#tree_id').val();
		strURL += '&tree_item_id=' + $('#tree_item_id').val();
		strURL += '&leaf_type=' + $('#leaf_type').val();
		strURL += '&enabled=' + $('#enabled').is(':checked');

		loadUrl({url:strURL,undefined,force})
	}

	function applyItemTypeChange() {
		var automationLeafTypeNew = $('#leaf_type').val();
		if (automationLeafTypeOriginal != automationLeafTypeNew && !isNew) {
			if (automationLeafTypeNew == 3) {
				var automationLeafTypeButtons = {
					'Yes': {
						text: '<?php print __('Yes'); ?>',
						id: 'btnAutomationLeafTypeYes',
						click: function() {
							applyTreeChange('change_leaf',true);
						}
					},
					'No': {
						text: '<?php print __('No'); ?>',
						id: 'btnAutomationLeafTypeNo',
						click: function() {
							$(this).dialog('close');
							var leaf = $('#leaf_type');
							if (leaf != null) {
								leaf.val(automationLeafTypeOriginal);
								leaf.selectmenu("refresh");
								leaf.change();
							}
						}
					}
				};

				var automationLeafTypeTitle = '<?php print __('Change Leaf Type'); ?>';
				var automationLeafTypeContainer = '<div id="automationLeafTypeContainer" style="display:none">' +
					'<p style="display:table-cell;overflow:auto"> ' +
					'<b><?php print __('WARNING:'); ?></b>' +
					'<?php print __('You are changing the leaf type to "Device" which does not support Graph-based object matching/creation.'); ?>' +
					'<?php print __('By changing the leaf type, all invalid rules will be automatically removed and will not be recoverable.'); ?> <br/><br/>' +
					'<?php print __('Are you sure you wish to continue?'); ?>' +
					'</p>' +
					'</div>';

				$('#automationLeafTypeContainer').remove();
				$('body').append(automationLeafTypeContainer);

				var automationLeafTypeWidth = $(window).width();
				if (automationLeafTypeWidth > 600) {
					automationLeafTypeWidth = 600;
				} else {
					automationLeafTypeWidth -= 50;
				}

				$('#automationLeafTypeContainer').dialog({
					draggable: true,
					resizable: false,
					height: 'auto',
					minWidth: automationLeafTypeWidth,
					maxWidth: 800,
					maxHeight: 600,
					title: automationLeafTypeTitle,
					buttons: automationLeafTypeButtons
				});
			} else {
				applyTreeChange('change_leaf',true);
			}
		} else {
			if ($('#leaf_type').val() == '<?php print TREE_ITEM_TYPE_HOST; ?>') {
				$('#row_host_grouping_type').show();
			} else if ($('#leaf_type').val() == '<?php print TREE_ITEM_TYPE_GRAPH; ?>') {
				$('#row_host_grouping_type').hide();
			}
			$('#leaf_type').selectmenu("refresh");
		}
	}
	</script>
	<?php
}

function create_tree_rules_filter() : array {
	global $item_rows;

	$status_arr = [
		'-1' => __('Any'),
		'-2' => __('Enabled'),
		'-3' => __('Disabled')
	];

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
					'friendly_name' => __('Tree Rules'),
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
				'title'   => __('Import Tree Rules'),
			]
		],
		'sort' => [
			'sort_column'    => 'name',
			'sort_direction' => 'ASC'
		]
	];
}

function draw_tree_rules_filter(bool $render = false) : void {
	$filters = create_tree_rules_filter();

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Tree Rules'), 'automation_tree_rules.php', 'form_automation', 'sess_autom_tr', 'automation_tree_rules.php?action=edit');

	$pageFilter->rows_label = __('Tree Rules');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function automation_tree_rules() : void {
	global $actions, $item_rows;
	global $automation_tree_item_types, $host_group_types;

	draw_tree_rules_filter(true);

	if ((!empty($_SESSION['sess_autom_tr_status'])) && (!ierv('status'))) {
		if ($_SESSION['sess_autom_tr_status'] != gnrv('status')) {
			srv('page', 1);
		}
	}

	if (grv('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$sql_where  = '';
	$sql_params = [];

	// form the 'WHERE' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where    = 'WHERE (atr.name LIKE ?)';
		$sql_params[] = '%' . grv('filter') . '%';
	}

	if (grv('status') == '-2') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . " AND atr.enabled = 'on'";
	} elseif (grv('status') == '-3') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . " AND atr.enabled = ''";
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(atr.id)
		FROM automation_tree_rules AS atr
		LEFT JOIN graph_tree AS gt
		ON atr.id=gt.id
		$sql_where",
		$sql_params);

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$automation_tree_rules = db_fetch_assoc_prepared("SELECT atr.id, atr.name,
		atr.tree_id, atr.tree_item_id,
		atr.leaf_type, atr.host_grouping_type, atr.enabled,
		gt.name AS tree_name, gti.title AS subtree_name
		FROM automation_tree_rules AS atr
		LEFT JOIN graph_tree AS gt
		ON atr.tree_id=gt.id
		LEFT JOIN graph_tree_items AS gti
		ON atr.tree_item_id = gti.id
		$sql_where
		$sql_order
		$sql_limit",
		$sql_params);

	$nav = html_nav_bar('automation_tree_rules.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 11, __('Tree Rules'), 'page', 'main');

	form_start('automation_tree_rules.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'name' => [
			'display' => __('Rule Name'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'id' => [
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC'
		],
		'tree_name' => [
			'display' => __('Tree'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'subtree_name' => [
			'display' => __('Sub-Tree Item'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'leaf_type' => [
			'display' => __('Leaf Item Type'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'host_grouping_type' => [
			'display' => __('Graph Grouping Style'),
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

	if (cacti_sizeof($automation_tree_rules)) {
		foreach ($automation_tree_rules as $automation_tree_rule) {
			$tree_item_type_name     = ((empty($automation_tree_rule['leaf_type'])) ? '<em>' . __('None') . '</em>' : $automation_tree_item_types[$automation_tree_rule['leaf_type']]);
			$subtree_name            = ((empty($automation_tree_rule['subtree_name'])) ? '<em>' . __('ROOT') . '</em>' : htmle($automation_tree_rule['subtree_name']));
			$tree_host_grouping_type = ((empty($host_group_types[$automation_tree_rule['host_grouping_type']])) ? '' : $host_group_types[$automation_tree_rule['host_grouping_type']]);

			form_alternate_row('line' . $automation_tree_rule['id'], true);

			form_selectable_cell(filter_value($automation_tree_rule['name'], grv('filter'), 'automation_tree_rules.php?action=edit&id=' . $automation_tree_rule['id'] . '&page=1'), $automation_tree_rule['id']);
			form_selectable_cell($automation_tree_rule['id'], $automation_tree_rule['id'], '', 'text-align:right');
			form_selectable_ecell($automation_tree_rule['tree_name'], $automation_tree_rule['id']);
			form_selectable_cell($subtree_name, $automation_tree_rule['id']);
			form_selectable_cell($tree_item_type_name, $automation_tree_rule['id']);
			form_selectable_cell($tree_host_grouping_type, $automation_tree_rule['id']);
			form_selectable_cell($automation_tree_rule['enabled'] ? __('Enabled') : __('Disabled'), $automation_tree_rule['id'], '', 'text-align:right');
			form_checkbox_cell($automation_tree_rule['name'], $automation_tree_rule['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Tree Rules Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($automation_tree_rules)) {
		print $nav;
	}

	draw_actions_dropdown($actions);

	form_end();
}
