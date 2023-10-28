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

include('./include/auth.php');
include_once('./lib/data_query.php');

$automation_tree_rules_actions = array(
	AUTOMATION_ACTION_TREE_DUPLICATE => __('Duplicate'),
	AUTOMATION_ACTION_TREE_ENABLE    => __('Enable'),
	AUTOMATION_ACTION_TREE_EXPORT    => __('Export'),
	AUTOMATION_ACTION_TREE_DISABLE   => __('Disable'),
	AUTOMATION_ACTION_TREE_DELETE    => __('Delete'),
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

		header('Location: automation_tree_rules.php?action=edit&id=' . get_request_var('id'));

		break;
	case 'item_movedown':
		automation_tree_rules_item_movedown();

		header('Location: automation_tree_rules.php?action=edit&id=' . get_request_var('id'));

		break;
	case 'item_moveup':
		automation_tree_rules_item_moveup();

		header('Location: automation_tree_rules.php?action=edit&id=' . get_request_var('id'));

		break;
	case 'item_remove':
		automation_tree_rules_item_remove();

		header('Location: automation_tree_rules.php?action=edit&id=' . get_request_var('id'));

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

function automation_export() {
	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if(cacti_sizeof($selected_items) == 1) {
				$export_data = automation_tree_rule_export($selected_items[0]);
			} else {
				foreach($selected_items as $id) {
					$snmp_option_ids[] = $id;
				}

				$export_data = automation_tree_rule_export($snmp_option_ids);
			}

			if (cacti_sizeof($export_data)) {
				$export_file_name = $export_data['name'];

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
			'friendly_name' => __('Import Tree Rules from Local File',),
			'description' => __('If the JSON file containing the Tree Rules data is located on your local machine, select it here.'),
			'method' => 'file',
			'accept' => '.json'
		),
		'import_text' => array(
			'method' => 'textarea',
			'friendly_name' => __('Import Tree Rules from Text'),
			'description' => __('If you have the JSON file containing the Tree Rules data as text, you can paste it into this box to import it.'),
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

		print '<tr class="tableHeader"><th>' . __('Cacti has Imported the following Tree Rules'). '</th></tr>';

		foreach ($_SESSION['import_debug_info'] as $line) {
			print '<tr><td>' . $line . '</td></tr>';
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box(__('Import Tree Rules'), '80%', false, '3', 'center', '');

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
			<input type='submit' value='" . __esc('Import') . "' title='" . __esc('Import Tree Rules') . "' class='ui-button ui-corner-all ui-widget ui-state-active'>
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

	if (is_array($json_data) && cacti_sizeof($json_data) && isset($json_data['tree_rules'])) {
		foreach($json_data['tree_rules'] as $tree_rule) {
			$return_data += automation_tree_rule_import($tree_rule);
		}
	}

	if (sizeof($return_data) && isset($return_data['success'])) {
		foreach ($return_data['success'] as $message) {
			$debug_data[] = '<span class="deviceUp">' . __('NOTE:') . '</span> ' . $message;
			cacti_log('NOTE: Automation Tree Rules Import Succeeded!.  Message: '. $message, false, 'AUTOM8');
		}
	}

	if (isset($return_data['errors'])) {
		foreach ($return_data['errors'] as $error) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $error;
			cacti_log('NOTE: Automation Tree Rules Import Error!.  Message: '. $message, false, 'AUTOM8');
		}
	}

	if (isset($return_data['failure'])) {
		foreach ($return_data['failure'] as $message) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $message;
			cacti_log('NOTE: Automation Tree Rules Import Failed!.  Message: '. $message, false, 'AUTOM8');
		}
	}

	if (cacti_sizeof($debug_data)) {
		$_SESSION['import_debug_info'] = $debug_data;
	}

	header('Location: automation_graph_rules.php?action=import');

	exit();
}

function form_save() {
	if (isset_request_var('save_component_automation_tree_rule')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		/* ==================================================== */

		$save['id']                 = get_request_var('id');
		$save['hash']               = get_hash_automation(get_request_var('id'), 'automation_tree_rules');
		$save['name']               = form_input_validate(get_nfilter_request_var('name'), 'name', '', true, 3);
		$save['tree_id']            = form_input_validate(get_nfilter_request_var('tree_id'), 'tree_id', '^[0-9]+$', false, 3);
		$save['tree_item_id']       = isset_request_var('tree_item_id') ? form_input_validate(get_nfilter_request_var('tree_item_id'), 'tree_item_id', '^[0-9]+$', false, 3) : 0;
		$save['leaf_type']          = (isset_request_var('leaf_type')) ? form_input_validate(get_nfilter_request_var('leaf_type'), 'leaf_type', '^[0-9]+$', false, 3) : 0;
		$save['host_grouping_type'] = isset_request_var('host_grouping_type') ? form_input_validate(get_nfilter_request_var('host_grouping_type'), 'host_grouping_type', '^[0-9]+$', false, 3) : 0;
		$save['enabled']            = (isset_request_var('enabled') ? 'on' : '');

		if (!is_error_message()) {
			$rule_id = sql_save($save, 'automation_tree_rules');

			if ($rule_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: automation_tree_rules.php?action=edit&id=' . (empty($rule_id) ? get_request_var('id') : $rule_id));
	} elseif (isset_request_var('save_component_automation_match_item')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('item_id');
		/* ==================================================== */

		$save              = array();
		$save['id']        = form_input_validate(get_request_var('item_id'), 'item_id', '^[0-9]+$', false, 3);
		$save['hash']      = get_hash_automation(get_request_var('item_id'), 'automation_match_rule_items');
		$save['rule_id']   = form_input_validate(get_request_var('id'), 'id', '^[0-9]+$', false, 3);
		$save['rule_type'] = AUTOMATION_RULE_TYPE_TREE_MATCH;
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
			header('Location: automation_tree_rules.php?action=item_edit&id=' . get_request_var('id') . '&item_id=' . (empty($item_id) ? get_request_var('item_id') : $item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_TREE_MATCH);
		} else {
			header('Location: automation_tree_rules.php?action=edit&id=' . get_request_var('id') . '&rule_type=' . AUTOMATION_RULE_TYPE_TREE_MATCH);
		}
	} elseif (isset_request_var('save_component_automation_tree_rule_item')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('item_id');
		/* ==================================================== */

		unset($save);
		$save['id']                = form_input_validate(get_request_var('item_id'), 'item_id', '^[0-9]+$', false, 3);
		$save['hash']              = get_hash_automation(get_request_var('item_id'), 'automation_tree_rule_items');
		$save['rule_id']           = form_input_validate(get_request_var('id'), 'id', '^[0-9]+$', false, 3);
		$save['sequence']          = form_input_validate(get_nfilter_request_var('sequence'), 'sequence', '^[0-9]+$', false, 3);
		$save['field']             = form_input_validate((isset_request_var('field') ? get_nfilter_request_var('field') : ''), 'field', '', true, 3);
		$save['sort_type']         = form_input_validate(get_nfilter_request_var('sort_type'), 'sort_type', '^[0-9]+$', false, 3);
		$save['propagate_changes'] = (isset_request_var('propagate_changes') ? 'on' : '');
		$save['search_pattern']    = isset_request_var('search_pattern') ? form_input_validate(get_nfilter_request_var('search_pattern'), 'search_pattern', '', false, 3) : '';
		$save['replace_pattern']   = isset_request_var('replace_pattern') ? form_input_validate(get_nfilter_request_var('replace_pattern'), 'replace_pattern', '', true, 3) : '';

		if (!is_error_message()) {
			$automation_graph_rule_item_id = sql_save($save, 'automation_tree_rule_items');

			if ($automation_graph_rule_item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: automation_tree_rules.php?action=item_edit&id=' . get_request_var('id') . '&item_id=' . (empty($automation_graph_rule_item_id) ? get_request_var('item_id') : $automation_graph_rule_item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_TREE_ACTION);
		} else {
			header('Location: automation_tree_rules.php?action=edit&id=' . get_request_var('id') . '&rule_type=' . AUTOMATION_RULE_TYPE_TREE_ACTION);
		}
	} else {
		raise_message(2);
		header('Location: automation_tree_rules.php');
	}
}

/* ------------------------
 The 'actions' function
 ------------------------ */

function automation_tree_rules_form_actions() {
	global $automation_tree_rules_actions;
	global $config;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_TREE_DELETE) { /* DELETE */
				cacti_log('form_actions DELETE: ' . serialize($selected_items), true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

				db_execute('DELETE FROM automation_tree_rules WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM automation_tree_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
				db_execute('DELETE FROM automation_match_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_TREE_DUPLICATE) { /* duplicate */
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					cacti_log('form_actions duplicate: ' . $selected_items[$i], true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

					duplicate_automation_tree_rules($selected_items[$i], get_nfilter_request_var('name_format'));
				}
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_TREE_ENABLE) { /* enable */
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					cacti_log('form_actions enable: ' . $selected_items[$i], true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

					db_execute_prepared("UPDATE automation_tree_rules SET enabled='on' WHERE id = ?", array($selected_items[$i]));
				}
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_TREE_DISABLE) { /* disable */
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					cacti_log('form_actions disable: ' . $selected_items[$i], true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

					db_execute_prepared("UPDATE automation_tree_rules SET enabled='' WHERE id = ?", array($selected_items[$i]));
				}
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_TREE_EXPORT) { /* export */
				top_header();

				print '<script text="text/javascript">
					function DownloadStart(url) {
						document.getElementById("download_iframe").src = url;
						setTimeout(function() {
							document.location = "automation_tree_rules.php";
							Pace.stop();
						}, 500);
					}

					$(function() {
						//debugger;
						DownloadStart(\'automation_tree_rules.php?action=export&selected_items=' . get_nfilter_request_var('selected_items') . '\');
					});
				</script>
				<iframe id="download_iframe" style="display:none;"></iframe>';

				bottom_footer();
				exit;
			}
		}

		header('Location: automation_tree_rules.php');

		exit;
	}

	/* setup some variables */
	$automation_tree_rules_list = '';
	$i                          = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1], 'chk[1]');
			/* ==================================================== */

			$automation_tree_rules_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM automation_tree_rules WHERE id = ?', array($matches[1]))) . '</li>';
			$automation_tree_rules_array[] = $matches[1];
		}
	}

	top_header();

	form_start('automation_tree_rules.php', 'automation_tree_rules_action');

	html_start_box($automation_tree_rules_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_TREE_DELETE) { /* DELETE */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to delete the following Rule(s).') . "</p>
				<div class='itemlist'><ul>$automation_tree_rules_list</ul></div>
			</td>
		</tr>\n";
	} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_TREE_DUPLICATE) { /* duplicate */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to duplicate the following Rule(s). You can optionally change the title format for the new Rules.') . "</p>
				<div class='itemlist'><ul>$automation_tree_rules_list</ul></div>
				<p>" . __('Title Format') . '<br>';
		form_text_box('name_format', '<' . __('rule_name') . '> (1)', '', '255', '30', 'text');
		print "</p>
			</td>
		</tr>\n";
	} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_TREE_ENABLE) { /* enable */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to enable the following Rule(s).') . "</p>
				<div class='itemlist'><ul>$automation_tree_rules_list</ul></div>
				<p>" . __('Make sure, that those rules have successfully been tested!') . "</p>
			</td>
		</tr>\n";
	} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_TREE_DISABLE) { /* disable */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to disable the following Rule(s).') . "</p>
				<div class='itemlist'><ul>$automation_tree_rules_list</ul></div>
			</td>
		</tr>\n";
	} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_TREE_EXPORT) { /* export */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to Export the following Rule(s).') . "</p>
				<div class='itemlist'><ul>$automation_tree_rules_list</ul></div>
			</td>
		</tr>\n";
	}

	if (!isset($automation_tree_rules_array)) {
		raise_message(40);
		header('Location: automation_tree_rules.php');

		exit;
	} else {
		$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Apply requested action') . "'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($automation_tree_rules_array) ? serialize($automation_tree_rules_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function automation_tree_rules_change_leaf() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('leaf_type');
	/* ==================================================== */

	automation_change_tree_rule_leaf_type(get_request_var('leaf_type'), get_request_var('id'));
}

/* --------------------------
 Rule Item Functions
 -------------------------- */

function automation_tree_rules_item_movedown() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	/* ==================================================== */

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_TREE_MATCH) {
		move_item_down('automation_match_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id') . ' AND rule_type=' . get_request_var('rule_type'));
	} elseif (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_TREE_ACTION) {
		move_item_down('automation_tree_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id'));
	}
}

function automation_tree_rules_item_moveup() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	/* ==================================================== */

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_TREE_MATCH) {
		move_item_up('automation_match_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id') . ' AND rule_type=' . get_request_var('rule_type'));
	} elseif (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_TREE_ACTION) {
		move_item_up('automation_tree_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id'));
	}
}

function automation_tree_rules_item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	/* ==================================================== */

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_TREE_MATCH) {
		db_execute_prepared('DELETE FROM automation_match_rule_items WHERE id = ?', array(get_request_var('item_id')));
	} elseif (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_TREE_ACTION) {
		db_execute_prepared('DELETE FROM automation_tree_rule_items WHERE id = ?', array(get_request_var('item_id')));
	}
}

function automation_tree_rules_item_edit() {
	global $config;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	get_filter_request_var('show_trees');
	/* ==================================================== */

	/* handle show_trees mode */
	if (isset_request_var('show_trees')) {
		if (get_request_var('show_trees') == '0') {
			kill_session_var('automation_tree_rules_show_trees');
		} elseif (get_request_var('show_trees') == '1') {
			$_SESSION['automation_tree_rules_show_trees'] = true;
		}
	}

	if (!isempty_request_var('rule_type') && !isempty_request_var('item_id')) {
		if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_TREE_ACTION) {
			$item = db_fetch_row_prepared('SELECT * FROM automation_tree_rule_items WHERE id = ?', array(get_request_var('item_id')));

			if ($item['field'] != AUTOMATION_TREE_ITEM_TYPE_STRING) {
				?>
<table style='width:100%;text-align:center;'>
	<tr>
		<td class='textInfo' style='text-align:right;vertical-align:top;'><span class='linkMarker'>*</span><a class='linkEditMain' href='<?php print html_escape('automation_tree_rules.php?action=item_edit&id=' . (isset_request_var('id') ? get_request_var('id') : 0) . '&item_id=' . (isset_request_var('item_id') ? get_request_var('item_id') : 0) . '&rule_type=' . (isset_request_var('rule_type') ? get_request_var('rule_type') : 0) .'&show_trees=') . (isset($_SESSION['automation_tree_rules_show_trees']) ? '0' : '1');?>'><?php print(isset($_SESSION['automation_tree_rules_show_trees']) ? __('Don\'t Show'):__('Show'));?> <?php print __('Created Trees');?></a><br>
		</td>
	</tr>
</table>
<br>
				<?php
			}
		}
	}

	global_item_edit(get_request_var('id'), get_request_var('item_id'), get_request_var('rule_type'));

	form_hidden_box('rule_type', get_request_var('rule_type'), get_request_var('rule_type'));
	form_hidden_box('id', (isset_request_var('id') ? get_request_var('id') : '0'), '');
	form_hidden_box('item_id', (isset_request_var('item_id') ? get_request_var('item_id') : '0'), '');

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_TREE_MATCH) {
		form_hidden_box('save_component_automation_match_item', '1', '');
	} else {
		form_hidden_box('save_component_automation_tree_rule_item', '1', '');
	}
	form_save_button('automation_tree_rules.php?action=edit&id=' . get_request_var('id') . '&rule_type=' . get_request_var('rule_type'));
	print '<br>';

	/* display list of matching trees */
	if (!isempty_request_var('rule_type') && !isempty_request_var('item_id')) {
		if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_TREE_ACTION) {
			if (isset($_SESSION['automation_tree_rules_show_trees']) && ($item['field'] != AUTOMATION_TREE_ITEM_TYPE_STRING)) {
				if ($_SESSION['automation_tree_rules_show_trees']) {
					display_matching_trees(get_request_var('id'), AUTOMATION_RULE_TYPE_TREE_ACTION, $item, 'automation_tree_rules.php?action=item_edit&id=' . get_request_var('id') . '&item_id=' . get_request_var('item_id') . '&rule_type=' . get_request_var('rule_type'));
				}
			}
		}
	}

	//Now we need some javascript to make it dynamic
	?>
<script type='text/javascript'>

applyHeaderChange();
toggle_operation();
toggle_operator();

function applyHeaderChange() {
	if ($('#rule_type').val() == '<?php print AUTOMATION_RULE_TYPE_TREE_ACTION;?>') {
		if ($('#field').val() == '<?php print AUTOMATION_TREE_ITEM_TYPE_STRING;?>') {
			$('#replace_pattern').val('');
			$('#replace_pattern').prop('disabled', true);
		} else {
			$('#replace_pattern').prop('disabled', false);
		}
	}
}

function toggle_operation() {
	// right bracket ')' does not come with a field
	if ($('operation').value == '<?php print AUTOMATION_OPER_RIGHT_BRACKET;?>') {
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
	if ($('#operator').val() == '<?php print AUTOMATION_OPER_RIGHT_BRACKET;?>') {
		//alert('Sequence is '' + document.getElementById('sequence').value + ''');
	} else {
	}
}
</script>
<?php
}

/* ---------------------
 Rule Functions
 --------------------- */

function automation_tree_rules_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if ((read_config_option('deletion_verification') == 'on') && (!isset_request_var('confirm'))) {
		top_header();
		form_confirm(__('Are You Sure?'), __("Are you sure you want to DELETE the Rule '%s'?", db_fetch_cell_prepared('SELECT name FROM automation_tree_rules WHERE id = ?', array(get_request_var('id')))), 'automation_tree_rules.php', 'automation_tree_rules.php?action=remove&id=' . get_request_var('id'));
		bottom_footer();

		exit;
	}

	if ((read_config_option('deletion_verification') == '') || (isset_request_var('confirm'))) {
		db_execute_prepared('DELETE FROM automation_match_rule_items
			WHERE rule_id = ?
			AND rule_type = ?',
			array(get_request_var('id'), AUTOMATION_RULE_TYPE_TREE_MATCH));

		db_execute_prepared('DELETE FROM automation_tree_rule_items
			WHERE rule_id = ?',
			array(get_request_var('id')));

		db_execute_prepared('DELETE FROM automation_tree_rules
			WHERE id = ?',
			array(get_request_var('id')));
	}
}

function automation_tree_rules_edit() {
	global $config;
	global $fields_automation_tree_rules_edit1, $fields_automation_tree_rules_edit2, $fields_automation_tree_rules_edit3;

	include_once(CACTI_PATH_LIBRARY . '/html_tree.php');

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('rows');
	get_filter_request_var('tree_id');
	get_filter_request_var('leaf_type');
	get_filter_request_var('host_grouping_type');
	get_filter_request_var('tree_item_id');
	get_filter_request_var('show_hosts');
	/* ==================================================== */

	/* clean up rule name */
	if (isset_request_var('name')) {
		set_request_var('name', sanitize_search_string(get_request_var('name')));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	$rule = array();

	if (get_request_var('id') > 0) {
		$rule = db_fetch_row_prepared('SELECT *
			FROM automation_tree_rules
			WHERE id = ?',
			array(get_request_var('id')));

		if ($rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
			$tabs = array(
				'rule'    => __('Rule'),
				'hosts'   => __('Matching Devices')
			);
		} else {
			$tabs = array(
				'rule'    => __('Rule'),
				'graphs'   => __('Matching Graphs')
			);
		}

        html_sub_tabs($tabs, 'action=edit&id=' . get_request_var('id'));

		$header_label = __esc('Tree Rule Selection [edit: %s]', $rule['name']);
	} else {
		$tabs = array(
			'rule'    => __('Rule')
		);

        html_sub_tabs($tabs, 'action=edit&id=' . get_request_var('id'));

		$header_label = __('Tree Rules Selection [new]');
	}

	if (get_request_var('tab') == 'rule') {
		/**
		 * display the rule
		 */
		form_start('automation_tree_rules.php', 'form_automation_tree_rule_edit');

		html_start_box($header_label, '100%', true, '3', 'center', '');

		if (get_request_var('id') > 0) {
			/* display whole rule */
			$form_array = $fields_automation_tree_rules_edit1 +
				$fields_automation_tree_rules_edit2 +
				$fields_automation_tree_rules_edit3;
		} else {
			/* display first part of rule only and request user to proceed */
			$form_array = $fields_automation_tree_rules_edit1;
		}

		draw_edit_form(array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($form_array, (isset($rule) ? $rule : array()))
		));

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
		display_matching_hosts($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, 'automation_tree_rules.php?action=edit&id=' . get_request_var('id'));
	} elseif ($rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
		display_matching_graphs($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, 'automation_tree_rules.php?action=edit&id=' . get_request_var('id'));
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
		strURL += '&enabled=' + $('#enabled').val();

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
					'<?php print __('You are changing the leaf type to "Device" which does not support Graph-based object matching/creation.');?>' +
					'<?php print __('By changing the leaf type, all invalid rules will be automatically removed and will not be recoverable.');?> <br/><br/>' +
					'<?php print __('Are you sure you wish to continue?');?>' +
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
			if ($('#leaf_type').val() == '<?php print TREE_ITEM_TYPE_HOST;?>') {
				$('#row_host_grouping_type').show();
			} else if ($('#leaf_type').val() == '<?php print TREE_ITEM_TYPE_GRAPH;?>') {
				$('#row_host_grouping_type').hide();
			}
			$('#leaf_type').selectmenu("refresh");
		}
	}
	</script>
	<?php
}

function automation_tree_rules() {
	global $automation_tree_rules_actions, $config, $item_rows;
	global $automation_tree_item_types, $host_group_types;

	if ((!empty($_SESSION['sess_autom_tr_status'])) && (!isempty_request_var('status'))) {
		if ($_SESSION['sess_autom_tr_status'] != get_nfilter_request_var('status')) {
			set_request_var('page', 1);
		}
	}

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
			'default' => ''
			)
	);

	validate_store_request_vars($filters, 'sess_autom_tr');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Tree Rules'), '100%', '', '3', 'center', 'automation_tree_rules.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_automation' action='automation_tree_rules.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Status');?>
						</td>
						<td>
							<select id='status'>
								<option value='-1' <?php print(get_request_var('status') == '-1' ? ' selected':'');?>><?php print __('Any');?></option>
								<option value='-2' <?php print(get_request_var('status') == '-2' ? ' selected':'');?>><?php print __('Enabled');?></option>
								<option value='-3' <?php print(get_request_var('status') == '-3' ? ' selected':'');?>><?php print __('Disabled');?></option>
							</select>
						</td>
						<td>
							<?php print __('Tree Rules');?>
						</td>
						<td>
							<select id='rows'>
								<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
								<?php
								if (cacti_sizeof($item_rows) > 0) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'" . (get_request_var('rows') == $key ? ' selected':'') . '>' . $value . "</option>\n";
									}
								}
	?>
							</select>
						</td>
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='import' value='<?php print __esc('Import');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
			<script type='text/javascript'>
			function applyFilter() {
				strURL = 'automation_tree_rules.php' +
					'?status='+$('#status').val() +
					'&filter='+$('#filter').val() +
					'&rows='+$('#rows').val();

				loadUrl({url:strURL})
			}

			function clearFilter() {
				strURL = 'automation_tree_rules.php?clear=1';
				loadUrl({url:strURL})
			}

			function importTemplate() {
				strURL = 'automation_tree_rules.php?action=import';
				loadUrl({url:strURL})
			}

			$(function() {
				$('#rows, #status').change(function() {
					applyFilter();
				});

				$('#refresh').click(function() {
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

	/* form the 'WHERE' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (atr.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (get_request_var('status') == '-1') {
		/* Show all items */
	} elseif (get_request_var('status') == '-2') {
		$sql_where .= ($sql_where != '' ? " AND atr.enabled='on'" : "WHERE atr.enabled='on'");
	} elseif (get_request_var('status') == '-3') {
		$sql_where .= ($sql_where != '' ? " AND atr.enabled=''" : "WHERE atr.enabled=''");
	}

	$total_rows = db_fetch_cell("SELECT COUNT(atr.id)
		FROM automation_tree_rules AS atr
		LEFT JOIN graph_tree AS gt
		ON atr.id=gt.id
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$automation_tree_rules = db_fetch_assoc("SELECT atr.id, atr.name, atr.tree_id, atr.tree_item_id,
		atr.leaf_type, atr.host_grouping_type, atr.enabled,
		gt.name AS tree_name, gti.title AS subtree_name
		FROM automation_tree_rules AS atr
		LEFT JOIN graph_tree AS gt
		ON atr.tree_id=gt.id
		LEFT JOIN graph_tree_items AS gti
		ON atr.tree_item_id = gti.id
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('automation_tree_rules.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Tree Rules'), 'page', 'main');

	form_start('automation_tree_rules.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name'               => array('display' => __('Rule Name'),      'align' => 'left',  'sort' => 'ASC'),
		'id'                 => array('display' => __('ID'),             'align' => 'right', 'sort' => 'ASC'),
		'tree_name'          => array('display' => __('Hook into Tree'), 'align' => 'left',  'sort' => 'ASC'),
		'subtree_name'       => array('display' => __('At Subtree'),     'align' => 'left',  'sort' => 'ASC'),
		'leaf_type'          => array('display' => __('This Type'),      'align' => 'left',  'sort' => 'ASC'),
		'host_grouping_type' => array('display' => __('Using Grouping'), 'align' => 'left',  'sort' => 'ASC'),
		'enabled'            => array('display' => __('Enabled'),        'align' => 'right', 'sort' => 'ASC')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($automation_tree_rules)) {
		foreach ($automation_tree_rules as $automation_tree_rule) {
			$tree_item_type_name     = ((empty($automation_tree_rule['leaf_type'])) ? '<em>' . __('None') . '</em>' : $automation_tree_item_types[$automation_tree_rule['leaf_type']]);
			$subtree_name            = ((empty($automation_tree_rule['subtree_name'])) ? '<em>' . __('ROOT') . '</em>' : html_escape($automation_tree_rule['subtree_name']));
			$tree_host_grouping_type = ((empty($host_group_types[$automation_tree_rule['host_grouping_type']])) ? '' : $host_group_types[$automation_tree_rule['host_grouping_type']]);
			form_alternate_row('line' .  $automation_tree_rule['id'], true);

			form_selectable_cell(filter_value($automation_tree_rule['name'], get_request_var('filter'), 'automation_tree_rules.php?action=edit&id=' . $automation_tree_rule['id'] . '&page=1'), $automation_tree_rule['id']);
			form_selectable_cell($automation_tree_rule['id'], $automation_tree_rule['id'], '', 'text-align:right');
			form_selectable_ecell($automation_tree_rule['tree_name'], $automation_tree_rule['id']);
			form_selectable_cell($subtree_name, $automation_tree_rule['id']);
			form_selectable_cell($tree_item_type_name, $automation_tree_rule['id']);
			form_selectable_cell($tree_host_grouping_type, $automation_tree_rule['id']);
			form_selectable_cell($automation_tree_rule['enabled'] ? __('Enabled'):__('Disabled'), $automation_tree_rule['id'], '', 'text-align:right');
			form_checkbox_cell($automation_tree_rule['name'], $automation_tree_rule['id']);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Tree Rules Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($automation_tree_rules)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($automation_tree_rules_actions);

	form_end();
}
