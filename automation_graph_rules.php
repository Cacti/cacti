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

$automation_graph_rules_actions = array(
	AUTOMATION_ACTION_GRAPH_DUPLICATE => __('Duplicate'),
	AUTOMATION_ACTION_GRAPH_ENABLE    => __('Enable'),
	AUTOMATION_ACTION_GRAPH_DISABLE   => __('Disable'),
	AUTOMATION_ACTION_GRAPH_DELETE    => __('Delete'),
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		save();

		break;
	case 'actions':
		automation_graph_rules_form_actions();

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

/* --------------------------
 The Save Function
 -------------------------- */

function save() {
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

/* ------------------------
 The 'actions' function
 ------------------------ */

function automation_graph_rules_form_actions() {
	global $config, $automation_graph_rules_actions;

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
					cacti_log('form_actions duplicate: ' . $selected_items[$i] . ' name: ' . get_nfilter_request_var('name_format'), true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);
					duplicate_automation_graph_rules($selected_items[$i], get_nfilter_request_var('name_format'));
				}
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_ENABLE) { /* enable */
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					cacti_log('form_actions enable: ' . $selected_items[$i], true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

					db_execute_prepared("UPDATE automation_graph_rules
						SET enabled='on'
						WHERE id = ?",
						array($selected_items[$i]));
				}
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DISABLE) { /* disable */
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					cacti_log('form_actions disable: ' . $selected_items[$i], true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

					db_execute_prepared("UPDATE automation_graph_rules
						SET enabled=''
						WHERE id = ?",
						array($selected_items[$i]));
				}
			}
		}

		header('Location: automation_graph_rules.php');

		exit;
	}

	/* setup some variables */
	$automation_graph_rules_list = '';
	$i                           = 0;
	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1], 'chk[1]');
			/* ==================================================== */

			$automation_graph_rules_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM automation_graph_rules WHERE id = ?', array($matches[1]))) . '</li>';
			$automation_graph_rules_array[] = $matches[1];
		}
	}

	top_header();

	form_start('automation_graph_rules.php', 'automation_graph_rules');

	html_start_box($automation_graph_rules_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DELETE) { /* delete */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Press \'Continue\' to delete the following Graph Rules.') . "</p>
				<ul>$automation_graph_rules_list</ul>
			</td>
		</tr>";
	} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DUPLICATE) { /* duplicate */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to duplicate the following Rule(s). You can optionally change the title format for the new Graph Rules.') . "</p>
				<div class='itemlist'><ul>$automation_graph_rules_list</ul></div>
				<p>" . __('Title Format') . '<br>';
		form_text_box('name_format', '<' . __('rule_name') . '> (1)', '', '255', '30', 'text');
		print "</p>
			</td>
		</tr>\n";
	} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_ENABLE) { /* enable */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to enable the following Rule(s).') . "</p>
				<div class='itemlist'><ul>$automation_graph_rules_list</ul></div>
				<p>" . __('Make sure, that those rules have successfully been tested!') . "</p>
			</td>
		</tr>\n";
	} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DISABLE) { /* disable */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to disable the following Rule(s).') . "</p>
				<div class='itemlist'><ul>$automation_graph_rules_list</ul></div>
			</td>
		</tr>\n";
	}

	if (!isset($automation_graph_rules_array)) {
		raise_message(40);
		header('Location: automation_graph_rules.php');

		exit;
	} else {
		$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Apply requested action') . "'>";
	}

	print "	<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($automation_graph_rules_array) ? serialize($automation_graph_rules_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* --------------------------
 Rule Item Functions
 -------------------------- */
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

/* ---------------------
 Rule Functions
 --------------------- */

function automation_graph_rules_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if ((read_config_option('deletion_verification') == 'on') && (!isset_request_var('confirm'))) {
		top_header();
		form_confirm(__('Are You Sure?'), __("Are you sure you want to delete the Rule '%s'?", db_fetch_cell_prepared('SELECT name FROM automation_graph_rules WHERE id = ?', array(get_request_var('id')))), 'automation_graph_rules.php', 'automation_graph_rules.php?action=remove&id=' . get_request_var('id'));
		bottom_footer();

		exit;
	}

	if ((read_config_option('deletion_verification') == '') || (isset_request_var('confirm'))) {
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
	global $fields_automation_graph_rules_edit1, $fields_automation_graph_rules_edit2, $fields_automation_graph_rules_edit3;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('snmp_query_id');
	get_filter_request_var('graph_type_id');

	get_filter_request_var('show_graphs');
	get_filter_request_var('show_hosts');
	get_filter_request_var('show_rule');
	/* ==================================================== */

	/* clean up rule name */
	if (isset_request_var('name')) {
		set_request_var('name', sanitize_search_string(get_request_var('name')));
	}

	/* handle show_rule mode */
	if (isset_request_var('show_rule')) {
		if (get_request_var('show_rule') == '0') {
			kill_session_var('automation_graph_rules_show_rule');
			$_SESSION['automation_graph_rules_show_rule'] = false;
		} elseif (get_request_var('show_rule') == '1') {
			$_SESSION['automation_graph_rules_show_rule'] = true;
		}
	} elseif (!isset($_SESSION['automation_graph_rules_show_rule'])) {
		$_SESSION['automation_graph_rules_show_rule'] = true;
	}

	/* handle show_graphs mode */
	if (isset_request_var('show_graphs')) {
		if (get_request_var('show_graphs') == '0') {
			kill_session_var('automation_graph_rules_show_graphs');
		} elseif (get_request_var('show_graphs') == '1') {
			$_SESSION['automation_graph_rules_show_graphs'] = true;
		}
	}

	/* handle show_hosts mode */
	if (isset_request_var('show_hosts')) {
		if (get_request_var('show_hosts') == '0') {
			kill_session_var('automation_graph_rules_show_hosts');
		} elseif (get_request_var('show_hosts') == '1') {
			$_SESSION['automation_graph_rules_show_hosts'] = true;
		}
	}

	/*
	 * display the rule -------------------------------------------------------------------------------------
	 */
	$rule = array();

	if (!isempty_request_var('id')) {
		$rule = db_fetch_row_prepared('SELECT * FROM automation_graph_rules where id = ?', array(get_request_var('id')));

		if (!isempty_request_var('graph_type_id')) {
			$rule['graph_type_id'] = get_request_var('graph_type_id'); # set query_type for display
		}

		# setup header
		$header_label = __esc('Rule Selection [edit: %s]', $rule['name']);
	} else {
		$rule = array(
				'name'          => get_request_var('name'),
				'snmp_query_id' => get_request_var('snmp_query_id'),
				);
		$header_label = __('Rule Selection [new]');
	}

	/*
	 * show rule? ------------------------------------------------------------------------------------------
	 */
	if (!isempty_request_var('id')) {
		?>
<table style='width:100%;text-align:center;'>
	<tr>
		<td class='textInfo right' style='vertical-align:top;'><span class='linkMarker'>*</span><a class='linkEditMain' href='<?php print html_escape('automation_graph_rules.php?action=edit&id=' . (isset_request_var('id') ? get_request_var('id') : 0) . '&show_rule=') . ($_SESSION['automation_graph_rules_show_rule'] == true ? '0' : '1');?>'><?php print($_SESSION['automation_graph_rules_show_rule'] == true ? __('Don\'t Show'):__('Show'));?> <?php print __('Rule Details.');?></a><br>
		</td>
	</tr>
</table>

		<?php
	}

	/*
	 * show hosts? ------------------------------------------------------------------------------------------
	 */
	if (!isempty_request_var('id')) {
		?>
<table style='width:100%;text-align:center;'>
	<tr>
		<td class='textInfo right' style='vertical-align:top;'><span class='linkMarker'>*</span><a class='linkEditMain' href='<?php print html_escape('automation_graph_rules.php?action=edit&id=' . (isset_request_var('id') ? get_request_var('id') : 0) . '&show_hosts=') . (isset($_SESSION['automation_graph_rules_show_hosts']) ? '0' : '1');?>'><?php print(isset($_SESSION['automation_graph_rules_show_hosts']) ? __('Don\'t Show'):__('Show'));?> <?php print __('Matching Devices.');?></a><br>
		</td>
	</tr>
</table>

		<?php
	}

	/*
	 * show graphs? -----------------------------------------------------------------------------------------
	 */
	if (!empty($rule['graph_type_id']) && $rule['graph_type_id'] > 0) {
		?>
<table style='width:100%;text-align:center;'>
	<tr>
		<td class='textInfo right' style='vertical-align:top;'>
			<span class='linkMarker'>*</span><a class='linkEditMain' href='<?php print html_escape('automation_graph_rules.php?action=edit&id=' . (isset_request_var('id') ? get_request_var('id') : 0) . '&show_graphs=') . (isset($_SESSION['automation_graph_rules_show_graphs']) ? '0' : '1');?>'><?php print(isset($_SESSION['automation_graph_rules_show_graphs']) ? __('Don\'t Show'):__('Show'));?> <?php print __('Matching Objects.');?></a><br>
		</td>
	</tr>
</table>
		<?php
	}

	if ($_SESSION['automation_graph_rules_show_rule']) {
		form_start('automation_graph_rules.php', 'chk');

		html_start_box($header_label, '100%', true, '3', 'center', '');

		if (!isempty_request_var('id')) {
			/* display whole rule */
			$form_array = $fields_automation_graph_rules_edit1 + $fields_automation_graph_rules_edit2 + $fields_automation_graph_rules_edit3;
		} else {
			/* display first part of rule only and request user to proceed */
			$form_array = $fields_automation_graph_rules_edit1;
		}

		if (isset_request_var('name')) {
			$rule['name'] = get_request_var('name');
		}

		draw_edit_form(array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($form_array, (isset($rule) ? $rule : array()))
		));

		html_end_box(true, true);

		form_hidden_box('id', (isset($rule['id']) ? $rule['id'] : '0'), '');
		form_hidden_box('item_id', (isset($rule['item_id']) ? $rule['item_id'] : '0'), '');
		form_hidden_box('save_component_automation_graph_rule', '1', '');

		/*
		 * display the rule items -------------------------------------------------------------------------------
		 */
		if (!empty($rule['id'])) {
			# display graph rules for host match
			display_match_rule_items(__('Device Selection Criteria'),
				$rule['id'],
				AUTOMATION_RULE_TYPE_GRAPH_MATCH,
				'automation_graph_rules.php');

			# fetch graph action rules
			display_graph_rule_items(__('Graph Creation Criteria'),
				$rule['id'],
				AUTOMATION_RULE_TYPE_GRAPH_ACTION,
				'automation_graph_rules.php');
		}

		form_save_button('automation_graph_rules.php', 'return');

		print '<br>';
	}

	if (!empty($rule['id'])) {
		/* display list of matching hosts */
		if (isset($_SESSION['automation_graph_rules_show_hosts'])) {
			if ($_SESSION['automation_graph_rules_show_hosts']) {
				display_matching_hosts($rule, AUTOMATION_RULE_TYPE_GRAPH_MATCH, 'automation_graph_rules.php?action=edit&id=' . get_request_var('id'));
			}
		}

		/* display list of new graphs */
		if (isset($_SESSION['automation_graph_rules_show_graphs'])) {
			if ($_SESSION['automation_graph_rules_show_graphs']) {
				display_new_graphs($rule, 'automation_graph_rules.php?action=edit&id=' . get_request_var('id'));
			}
		}
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
	</script>
	<?php
}

function automation_graph_rules() {
	global $automation_graph_rules_actions, $config, $item_rows;

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
							<select id='snmp_query_id'>
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
			print "<option value='" . $data_query['id'] . "'" . (get_request_var('snmp_query_id') == $data_query['id'] ? ' selected':'') .  '>' . html_escape($data_query['name']) . "</option>\n";
		}
	}
	?>
							</select>
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
							<?php print __('Graph Rules');?>
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
								<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' name='go' value='<?php print __esc('Go');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>'></td>
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

		$(function() {
			$('#refresh, #rules, #rows, #status, #snmp_query_id').change(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
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
		'name'            => array('display' => __('Rule Name'),  'align' => 'left', 'sort' => 'ASC', 'tip' => __('The name of this rule.')),
		'id'              => array('display' => __('ID'),         'align' => 'right', 'sort' => 'ASC', 'tip' => __('The internal database ID for this rule.  Useful in performing debugging and automation.')),
		'snmp_query_name' => array('display' => __('Data Query'), 'align' => 'left', 'sort' => 'ASC'),
		'graph_type_name' => array('display' => __('Graph Type'), 'align' => 'left', 'sort' => 'ASC'),
		'enabled'         => array('display' => __('Enabled'),    'align' => 'right', 'sort' => 'ASC'),
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
	draw_actions_dropdown($automation_graph_rules_actions);

	form_end();
}
