<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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
include_once('./lib/api_automation.php');
include_once('./lib/data_query.php');

$actions = array(
	AUTOMATION_ACTION_GRAPH_DUPLICATE => __('Duplicate'),
	AUTOMATION_ACTION_GRAPH_ENABLE    => __('Enable'),
	AUTOMATION_ACTION_GRAPH_EXPORT    => __('Export'),
	AUTOMATION_ACTION_GRAPH_DISABLE   => __('Disable'),
	AUTOMATION_ACTION_GRAPH_DELETE    => __('Delete'),
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		if (isset_request_var('save_component_import')) {
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

		header('Location: automation_graph_rules.php?action=edit&id=' . get_filter_request_var('id'));

		break;
	case 'item_moveup':
		automation_graph_rules_item_moveup();

		header('Location: automation_graph_rules.php?action=edit&id=' . get_filter_request_var('id'));

		break;
	case 'item_remove':
		automation_graph_rules_item_remove();

		header('Location: automation_graph_rules.php?action=edit&id=' . get_filter_request_var('id'));

		break;
	case 'item_edit':
		top_header();
		automation_graph_rules_item_edit();
		bottom_footer();

		break;
	case 'qedit':
		automation_change_query_type();

		header('Location: automation_graph_rules.php?action=edit&name=' . get_request_var('name') . '&id=' . get_filter_request_var('id') . '&snmp_query_id=' . get_request_var('snmp_query_id'));

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

function automation_export() {
	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if(cacti_sizeof($selected_items) == 1) {
				$export_data = automation_graph_rule_export($selected_items[0]);
			} else {
				foreach($selected_items as $id) {
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
	}
}

function automation_import() {
	$form_data = array(
		'import_file' => array(
			'friendly_name' => __('Import Graph Rules from Local File',),
			'description' => __('If the JSON file containing the Graph Rules data is located on your local machine, select it here.'),
			'method' => 'file',
			'accept' => '.json'
		),
		'import_text' => array(
			'method' => 'textarea',
			'friendly_name' => __('Import Graph Rules from Text'),
			'description' => __('If you have the JSON file containing the Graph Rules data as text, you can paste it into this box to import it.'),
			'value' => '',
			'default' => '',
			'textarea_rows' => '10',
			'textarea_cols' => '80',
			'class' => 'textAreaNotes'
		)
	);

	form_start('automation_graph_rules.php', 'chk', true);

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box(__('Import Results'), '80%', '', '3', 'center', '');

		print '<tr class="tableHeader"><th>' . __('Cacti has Imported the following Graph Rules'). '</th></tr>';

		foreach ($_SESSION['import_debug_info'] as $line) {
			print '<tr><td>' . $line . '</td></tr>';
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box(__('Import Graph Rules'), '80%', false, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $form_data
		)
	);

	form_hidden_box('save_component_import', '1', '');

	print "	<tr><td><hr/></td></tr><tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='save'>
			<input type='submit' value='" . __esc('Import') . "' title='" . __esc('Import Graph Rules') . "' class='ui-button ui-corner-all ui-widget ui-state-active'>
		</td>
		<script type='text/javascript'>
		$(function() {
			clearAllTimeouts();
		});
		</script>
	</tr>";

	html_end_box();
}

function automation_import_process() {
	$json_data = json_decode(get_nfilter_request_var('import_text'), true);

	// If we have text, then we were trying to import text, otherwise we are uploading a file for import
	if (empty($json_data)) {
		$json_data = automation_validate_upload();
	}

	if (is_array($json_data) && cacti_sizeof($json_data) && isset($json_data['graph_rules'])) {
		foreach($json_data['graph_rules'] as $graph_rule) {
			$return_data += automation_graph_rule_import($graph_rule);
		}
	}

	if (sizeof($return_data) && isset($return_data['success'])) {
		foreach ($return_data['success'] as $message) {
			$debug_data[] = '<span class="deviceUp">' . __('NOTE:') . '</span> ' . $message;
			automation_log('NOTE: Automation Graph Rules Import Succeeded!.  Message: '. $message, AUTOMATION_LOG_LOW);
		}
	}

	if (isset($return_data['errors'])) {
		foreach ($return_data['errors'] as $error) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $error;
			automation_log('NOTE: Automation Graph Rules Import Error!.  Message: '. $message, AUTOMATION_LOG_LOW);
		}
	}

	if (isset($return_data['failure'])) {
		foreach ($return_data['failure'] as $message) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $message;
			automation_log('NOTE: Automation Graph Rules Import Failed!.  Message: '. $message, AUTOMATION_LOG_LOW);
		}
	}

	if (cacti_sizeof($debug_data)) {
		$_SESSION['import_debug_info'] = $debug_data;
	}

	header('Location: automation_graph_rules.php?action=import');

	exit();
}

function form_save() {
	if (isset_request_var('save_component_automation_graph_rule')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		/* ==================================================== */

		$save['id']            = get_nfilter_request_var('id');
		$save['hash']          = get_hash_automation(get_request_var('id'), 'automation_graph_rules');
		$save['name']          = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['snmp_query_id'] = form_input_validate(get_nfilter_request_var('snmp_query_id'), 'snmp_query_id', '^[0-9]+$', false, 3);
		$save['graph_type_id'] = (isset_request_var('graph_type_id')) ? form_input_validate(get_nfilter_request_var('graph_type_id'), 'graph_type_id', '^[0-9]+$', false, 3) : 0;
		$save['enabled']       = (isset_request_var('enabled') ? 'on' : '');

		if (!is_error_message()) {
			$rule_id = sql_save($save, 'automation_graph_rules');

			if ($rule_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: automation_graph_rules.php?action=edit&id=' . (empty($rule_id) ? get_nfilter_request_var('id') : $rule_id));
	} elseif (isset_request_var('save_component_automation_graph_rule_item')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('item_id');
		/* ==================================================== */

		$save              = array();
		$save['id']        = form_input_validate(get_request_var('item_id'), 'item_id', '^[0-9]+$', false, 3);
		$save['hash']      = get_hash_automation(get_request_var('id_item'), 'automation_graph_rule_items');
		$save['rule_id']   = form_input_validate(get_request_var('id'), 'id', '^[0-9]+$', false, 3);
		$save['sequence']  = form_input_validate(get_nfilter_request_var('sequence'), 'sequence', '^[0-9]+$', false, 3);
		$save['operation'] = form_input_validate(get_nfilter_request_var('operation'), 'operation', '^[-0-9]+$', true, 3);
		$save['field']     = form_input_validate(((isset_request_var('field') && get_nfilter_request_var('field') != '0') ? get_nfilter_request_var('field') : ''), 'field', '', true, 3);
		$save['operator']  = form_input_validate((isset_request_var('operator') ? get_nfilter_request_var('operator') : ''), 'operator', '^[0-9]+$', true, 3);
		$save['pattern']   = form_input_validate((isset_request_var('pattern') ? get_nfilter_request_var('pattern') : ''), 'pattern', '', true, 3);

		if (!is_error_message()) {
			$item_id = sql_save($save, 'automation_graph_rule_items');

			if ($item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: automation_graph_rules.php?action=item_edit&id=' . get_request_var('id') . '&item_id=' . (empty($item_id) ? get_request_var('item_id') : $item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_ACTION);
		} else {
			header('Location: automation_graph_rules.php?action=edit&id=' . get_request_var('id') . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_ACTION);
		}
	} elseif (isset_request_var('save_component_automation_match_item')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('item_id');
		/* ==================================================== */

		unset($save);
		$save['id']        = form_input_validate(get_request_var('item_id'), 'item_id', '^[0-9]+$', false, 3);
		$save['hash']      = get_hash_automation(get_request_var('item_idid'), 'automation_match_rule_items');
		$save['rule_id']   = form_input_validate(get_request_var('id'), 'id', '^[0-9]+$', false, 3);
		$save['rule_type'] = AUTOMATION_RULE_TYPE_GRAPH_MATCH;
		$save['sequence']  = form_input_validate(get_nfilter_request_var('sequence'), 'sequence', '^[0-9]+$', false, 3);
		$save['operation'] = form_input_validate(get_nfilter_request_var('operation'), 'operation', '^[-0-9]+$', true, 3);
		$save['field']     = form_input_validate(((isset_request_var('field') && get_nfilter_request_var('field') != '0') ? get_nfilter_request_var('field') : ''), 'field', '', true, 3);
		$save['operator']  = form_input_validate((isset_request_var('operator') ? get_nfilter_request_var('operator') : ''), 'operator', '^[0-9]+$', true, 3);
		$save['pattern']   = form_input_validate((isset_request_var('pattern') ? get_nfilter_request_var('pattern') : ''), 'pattern', '', true, 3);

		if (!is_error_message()) {
			$item_id = sql_save($save, 'automation_match_rule_items');

			if ($item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: automation_graph_rules.php?action=item_edit&id=' . get_request_var('id') . '&item_id=' . (empty($item_id) ? get_request_var('item_id') : $item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_MATCH);
		} else {
			header('Location: automation_graph_rules.php?action=edit&id=' . get_request_var('id') . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_MATCH);
		}
	} else {
		raise_message(2);
		header('Location: automation_graph_rules.php');
	}
}

function automation_graph_rules_form_actions() {
	global $config, $actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DELETE) { /* delete */
				db_execute('DELETE FROM automation_graph_rules WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM automation_graph_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
				db_execute('DELETE FROM automation_match_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DUPLICATE) { /* duplicate */
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					automation_log('form_actions duplicate: ' . $selected_items[$i] . ' name: ' . get_nfilter_request_var('name_format'), AUTOMATION_LOG_HIGH, POLLER_VERBOSITY_MEDIUM);
					duplicate_automation_graph_rules($selected_items[$i], get_nfilter_request_var('name_format'));
				}
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_ENABLE) { /* enable */
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					automation_log('form_actions enable: ' . $selected_items[$i], AUTOMATION_LOG_HIGH, POLLER_VERBOSITY_MEDIUM);

					db_execute_prepared("UPDATE automation_graph_rules
						SET enabled='on'
						WHERE id = ?",
						array($selected_items[$i]));
				}
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DISABLE) { /* disable */
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					automation_log('form_actions disable: ' . $selected_items[$i], AUTOMATION_LOG_HIGH, POLLER_VERBOSITY_MEDIUM);

					db_execute_prepared("UPDATE automation_graph_rules
						SET enabled=''
						WHERE id = ?",
						array($selected_items[$i]));
				}
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_EXPORT) { /* export */
				top_header();

				print '<script text="text/javascript">
					function DownloadStart(url) {
						document.getElementById("download_iframe").src = url;
						setTimeout(function() {
							document.location = "automation_graph_rules.php";
							Pace.stop();
						}, 500);
					}

					$(function() {
						//debugger;
						DownloadStart(\'automation_graph_rules.php?action=export&selected_items=' . get_nfilter_request_var('selected_items') . '\');
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
		$iarray = array();

		/* loop through each of the graphs selected on the previous page and get more info about them */
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1], 'chk[1]');
				/* ==================================================== */

				$ilist .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM automation_graph_rules WHERE id = ?', array($matches[1]))) . '</li>';
				$iarray[] = $matches[1];
			}
		}

		$form_data = array(
			'general' => array(
				'page'       => 'automation_graph_rules.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			),
			'options' => array(
				AUTOMATION_ACTION_GRAPH_DELETE => array(
					'smessage' => __('Click \'Continue\' to Delete the following Graph Rule.'),
					'pmessage' => __('Click \'Continue\' to Delete following Graph Rules.'),
					'scont'    => __('Delete Graph Rule'),
					'pcont'    => __('Delete Graph Rules')
				),
				AUTOMATION_ACTION_GRAPH_ENABLE => array(
					'smessage' => __('Click \'Continue\' to Enable the following Graph Rule.'),
					'pmessage' => __('Click \'Continue\' to Enable following Graph Rules.'),
					'scont'    => __('Enable Graph Rule'),
					'pcont'    => __('Enable Graph Rules')
				),
				AUTOMATION_ACTION_GRAPH_DISABLE => array(
					'smessage' => __('Click \'Continue\' to Disable the following Graph Rule.'),
					'pmessage' => __('Click \'Continue\' to Disable following Graph Rules.'),
					'scont'    => __('Disable Graph Rule'),
					'pcont'    => __('Disable Graph Rules')
				),
				AUTOMATION_ACTION_GRAPH_EXPORT => array(
					'smessage' => __('Click \'Continue\' to Export the following Graph Rule.'),
					'pmessage' => __('Click \'Continue\' to Export following Graph Rules.'),
					'scont'    => __('Export Graph Rule'),
					'pcont'    => __('Export Graph Rules')
				),
				AUTOMATION_ACTION_GRAPH_DUPLICATE => array(
					'smessage' => __('Click \'Continue\' to Duplicate the following Graph Rule.'),
					'pmessage' => __('Click \'Continue\' to Duplicate following Graph Rules.'),
					'scont'    => __('Duplicate Graph Rule'),
					'pcont'    => __('Duplicate Graph Rules'),
					'extra'    => array(
						'name_format' => array(
							'method'  => 'textbox',
							'title'   => __('Title Format:'),
							'default' => '<rule_name> (1)',
							'width'   => 25
						)
					)
				)
			)
		);

		form_continue_confirmation($form_data);
	}
}

function automation_graph_rules_item_movedown() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	/* ==================================================== */

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		move_item_down('automation_match_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id') . ' AND rule_type=' . get_request_var('rule_type'));
	} elseif (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		move_item_down('automation_graph_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id'));
	}
}

function automation_graph_rules_item_moveup() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	/* ==================================================== */

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		move_item_up('automation_match_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id') . ' AND rule_type=' . get_request_var('rule_type'));
	} elseif (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		move_item_up('automation_graph_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id'));
	}
}

function automation_graph_rules_item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	/* ==================================================== */

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		db_execute_prepared('DELETE FROM automation_match_rule_items WHERE id = ?', array(get_request_var('item_id')));
	} elseif (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		db_execute_prepared('DELETE FROM automation_graph_rule_items WHERE id = ?', array(get_request_var('item_id')));
	}
}

function automation_graph_rules_item_edit() {
	global $config;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	/* ==================================================== */

	global_item_edit(get_request_var('id'), get_request_var('item_id'), get_request_var('rule_type'));

	form_hidden_box('rule_type', get_request_var('rule_type'), get_request_var('rule_type'));
	form_hidden_box('id', (isset_request_var('id') ? get_request_var('id') : '0'), '');
	form_hidden_box('item_id', (isset_request_var('item_id') ? get_request_var('item_id') : '0'), '');

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		form_hidden_box('save_component_automation_match_item', '1', '');
	} else {
		form_hidden_box('save_component_automation_graph_rule_item', '1', '');
	}

	form_save_button('automation_graph_rules.php?action=edit&id=' . get_request_var('id') . '&rule_type='. get_request_var('rule_type'));

	?>
	<script type='text/javascript'>

	$(function() {
		toggle_operation();
		toggle_operator();
	});

	function toggle_operation() {
		if ($('#operation').val() == '<?php print AUTOMATION_OPER_RIGHT_BRACKET;?>') {
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
		if ($('#operator').val() == '<?php print AUTOMATION_OPER_RIGHT_BRACKET;?>') {
		} else {
		}
	}
	</script>
	<?php
}

function automation_graph_rules_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM automation_match_rule_items
		WHERE rule_id = ?
		AND rule_type = ?',
		array(get_request_var('id'), AUTOMATION_RULE_TYPE_GRAPH_MATCH));

	db_execute_prepared('DELETE FROM automation_graph_rule_items
		WHERE rule_id = ?',
		array(get_request_var('id')));

	db_execute_prepared('DELETE FROM automation_graph_rules
		WHERE id = ?',
		array(get_request_var('id')));
}

function automation_change_query_type() {
	$id = get_filter_request_var('id');

	if (isset_request_var('snmp_query_id') && $id > 0) {
		$snmp_query_id = get_filter_request_var('snmp_query_id');
		$name          = get_nfilter_request_var('name');

		db_execute_prepared('UPDATE automation_graph_rules
			SET snmp_query_id = ?, name = ?
			WHERE id = ?',
			array($snmp_query_id, $name, $id));

		$graph_type = db_fetch_cell_prepared('SELECT id
			FROM snmp_query_graph
			WHERE snmp_query_id = ?
			ORDER BY name
			LIMIT 1', array($snmp_query_id));

		db_execute_prepared('UPDATE automation_graph_rules
			SET graph_type_id = ?
			WHERE id = ?',
			array($graph_type, $id));
	} elseif (isset_request_var('graph_type_id') && $id > 0) {
		$snmp_query_id = get_filter_request_var('graph_type_id');
		$name          = get_nfilter_request_var('name');

		db_execute_prepared('UPDATE automation_graph_rules
			SET graph_type_id = ?, name = ?
			WHERE id = ?',
			array($snmp_query_id, $name, $id));
	}
}

function automation_graph_rules_edit() {
	global $config;
	global $fields_automation_graph_rules_edit1;
	global $fields_automation_graph_rules_edit2;
	global $fields_automation_graph_rules_edit3;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('snmp_query_id');
	get_filter_request_var('graph_type_id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$rule = db_fetch_row_prepared('SELECT *
			FROM automation_graph_rules
			WHERE id = ?',
			array(get_request_var('id')));

		if (!isempty_request_var('graph_type_id')) {
			$rule['graph_type_id'] = get_request_var('graph_type_id');
		}

		$header_label = __esc('Rule Selection [edit: %s]', $rule['name']);

		$tabs = array(
			'rule'    => __('Rule'),
			'hosts'   => __('Matching Devices'),
			'objects' => __('Matching Objects')
		);

		html_sub_tabs($tabs, 'action=edit&id=' . get_request_var('id'));
	} else {
		$rule = array(
			'name'          => get_request_var('name'),
			'snmp_query_id' => get_request_var('snmp_query_id'),
		);

		$header_label = __('Rule Selection [new]');

		$tabs = array(
			'rule'    => __('Rule')
		);

		html_sub_tabs($tabs, 'action=edit&id=' . get_request_var('id'));
	}

	if (get_request_var('tab') == 'rule') {
		form_start('automation_graph_rules.php', 'chk');

		html_start_box($header_label, '100%', true, '3', 'center', '');

		if (!isempty_request_var('id')) {
			/* display whole rule */
			$form_array = $fields_automation_graph_rules_edit1 +
				$fields_automation_graph_rules_edit2 + $fields_automation_graph_rules_edit3;
		} else {
			/* display first part of rule only and request user to proceed */
			$form_array = $fields_automation_graph_rules_edit1;
		}

		if (isset_request_var('name')) {
			$rule['name'] = get_request_var('name');
		}

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => inject_form_variables($form_array, (isset($rule) ? $rule : array()))
			)
		);

		html_end_box(true, true);

		form_hidden_box('id', (isset($rule['id']) ? $rule['id'] : '0'), '');
		form_hidden_box('item_id', (isset($rule['item_id']) ? $rule['item_id'] : '0'), '');
		form_hidden_box('save_component_automation_graph_rule', '1', '');

		/*
		 * display the rule items -------------------------------------------------------------------------------
		 */
		if (isset($rule['id'])) {
			# display graph rules for host match
			display_match_rule_items(__('Device Selection Criteria'), $rule, AUTOMATION_RULE_TYPE_GRAPH_MATCH, 'automation_graph_rules.php');

			# fetch graph action rules
			display_graph_rule_items(__('Graph Creation Criteria'), $rule, AUTOMATION_RULE_TYPE_GRAPH_ACTION, 'automation_graph_rules.php');
		}

		form_save_button('automation_graph_rules.php', 'return');

		print '<br>';
	} elseif (get_request_var('tab') == 'hosts') {
		display_matching_hosts($rule, AUTOMATION_RULE_TYPE_GRAPH_MATCH, 'automation_graph_rules.php?action=edit&id=' . get_request_var('id'));
	} elseif (get_request_var('tab') == 'objects') {
		display_new_graphs($rule, 'automation_graph_rules.php?action=edit&id=' . get_request_var('id'));
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
				'title': '<?php print __('SQL Debug Output');?>',
				'autoOpen': true,
				'width': 700
			});
		});

		$('#show_sql').click(function(event) {
			event.stopPropagation();
			$('#sql_query').dialog({
				'title': '<?php print __('SQL Debug Output');?>',
				'autoOpen': true,
				'width': 700
			});
		});
	});
	</script>
	<?php
}

function automation_graph_rules() {
	global $actions, $config, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
		),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		),
		'status' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'snmp_query_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => ''
		)
	);

	validate_store_request_vars($filters, 'sess_autom_gr');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	if ((!empty($_SESSION['sess_autom_gr_status'])) && (!isempty_request_var('status'))) {
		if ($_SESSION['sess_autom_gr_status'] != get_nfilter_request_var('status')) {
			set_request_var('page', 1);
		}
	}

	html_start_box(__('Graph Rules'), '100%', '', '3', 'center', 'automation_graph_rules.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_automation' action='automation_graph_rules.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Data Query');?>
						</td>
						<td>
							<select id='snmp_query_id' data-defaultLabel='<?php print __('Data Query');?>'>
								<option value='-1'<?php print(get_request_var('snmp_query_id') == '-1' ? ' selected':'');?>><?php print __('Any');?></option>
								<?php
								$available_data_queries = db_fetch_assoc('SELECT DISTINCT
									sq.id, sq.name
									FROM automation_graph_rules AS ar
									LEFT JOIN snmp_query AS sq
									ON ar.snmp_query_id=sq.id
									ORDER BY sq.name');

								if (cacti_sizeof($available_data_queries)) {
									foreach ($available_data_queries as $data_query) {
										print "<option value='" . $data_query['id'] . "'" . (get_request_var('snmp_query_id') == $data_query['id'] ? ' selected':'') .  '>' . html_escape($data_query['name']) . "</option>";
									}
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('Status');?>
						</td>
						<td>
							<select id='status' data-defaultLabel='<?php print __('Status');?>'>
								<option value='-1' <?php print(get_request_var('status') == '-1' ? ' selected':'');?>><?php print __('Any');?></option>
								<option value='-2' <?php print(get_request_var('status') == '-2' ? ' selected':'');?>><?php print __('Enabled');?></option>
								<option value='-3' <?php print(get_request_var('status') == '-3' ? ' selected':'');?>><?php print __('Disabled');?></option>
							</select>
						</td>
						<td>
							<?php print __('Graph Rules');?>
						</td>
						<td>
							<select id='rows' data-defaultLabel='<?php print __('Graph Rules');?>'>
								<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
								<?php
								if (cacti_sizeof($item_rows) > 0) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'" . (get_request_var('rows') == $key ? ' selected':'') . '>' . $value . "</option>";
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' name='go' value='<?php print __esc('Go');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='import' value='<?php print __esc('Import');?>'>
							</span>
					</tr>
				</table>
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL = 'automation_graph_rules.php' +
				'?status='        + $('#status').val()+
				'&filter='        + $('#filter').val()+
				'&rows='          + $('#rows').val()+
				'&snmp_query_id=' + $('#snmp_query_id').val();
			loadUrl({url:strURL})
		}

		function clearFilter() {
			strURL = 'automation_graph_rules.php?clear=1';
			loadUrl({url:strURL})
		}

		function importTemplate() {
			strURL = 'automation_graph_rules.php?action=import';
			loadUrl({url:strURL})
		}

		$(function() {
			$('#refresh, #rules, #rows, #status, #snmp_query_id').change(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#import').click(function() {
				importTemplate();
			});

			$('#form_automation').submit(function(event) {
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
		$sql_where = 'WHERE (agr.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
			'sqg.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
			'sq.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (get_request_var('status') == '-1') {
		/* Show all items */
	} elseif (get_request_var('status') == '-2') {
		$sql_where .= ($sql_where != '' ? " and agr.enabled='on'" : "where agr.enabled='on'");
	} elseif (get_request_var('status') == '-3') {
		$sql_where .= ($sql_where != '' ? " and agr.enabled=''" : "where agr.enabled=''");
	}

	if (get_request_var('snmp_query_id') == '-1') {
		/* show all items */
	} elseif (!isempty_request_var('snmp_query_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ');
		$sql_where .= 'agr.snmp_query_id=' . get_request_var('snmp_query_id');
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(agr.id)
		FROM automation_graph_rules AS agr
		LEFT JOIN snmp_query AS sq
		ON (agr.snmp_query_id=sq.id)
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$automation_graph_rules_list = db_fetch_assoc("SELECT agr.id, agr.name, agr.snmp_query_id, agr.graph_type_id,
		agr.enabled, sq.name AS snmp_query_name, sqg.name AS graph_type_name
		FROM automation_graph_rules AS agr
		LEFT JOIN snmp_query AS sq
		ON (agr.snmp_query_id=sq.id)
		LEFT JOIN snmp_query_graph AS sqg
		ON (agr.graph_type_id=sqg.id)
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('automation_graph_rules.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 7, __('Graph Rules'), 'page', 'main');

	form_start('automation_graph_rules.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name' => array(
			'display' => __('Rule Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this rule.')
		),
		'id' => array(
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal database ID for this rule.  Useful in performing debugging and automation.')
		),
		'snmp_query_name' => array(
			'display' => __('Data Query'),
			'align'   => 'left',
			'sort'    => 'ASC'
		),
		'graph_type_name' => array(
			'display' => __('Graph Type'),
			'align'   => 'left',
			'sort'    => 'ASC'
		),
		'enabled' => array(
			'display' => __('Enabled'),
			'align'   => 'right',
			'sort'    => 'ASC'
		)
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($automation_graph_rules_list)) {
		foreach ($automation_graph_rules_list as $automation_graph_rules) {
			$snmp_query_name 		= ((empty($automation_graph_rules['snmp_query_name'])) 	 ? __('None') : html_escape($automation_graph_rules['snmp_query_name']));
			$graph_type_name 		= ((empty($automation_graph_rules['graph_type_name'])) 	 ? __('None') : html_escape($automation_graph_rules['graph_type_name']));

			form_alternate_row('line' . $automation_graph_rules['id'], true);

			form_selectable_cell(filter_value($automation_graph_rules['name'], get_request_var('filter'), 'automation_graph_rules.php?action=edit&id=' . $automation_graph_rules['id'] . '&page=1'), $automation_graph_rules['id']);
			form_selectable_cell($automation_graph_rules['id'], $automation_graph_rules['id'], '', 'text-align:right');
			form_selectable_cell(filter_value($snmp_query_name, get_request_var('filter')), $automation_graph_rules['id']);
			form_selectable_cell(filter_value($graph_type_name, get_request_var('filter')), $automation_graph_rules['id']);
			form_selectable_cell($automation_graph_rules['enabled'] ? __('Enabled') : __('Disabled'), $automation_graph_rules['id'], '', 'text-align:right');
			form_checkbox_cell($automation_graph_rules['name'], $automation_graph_rules['id']);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Graph Rules Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($automation_graph_rules_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();
}
