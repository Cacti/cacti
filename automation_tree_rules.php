<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2015 The Cacti Group                                 |
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
include_once('./lib/data_query.php');

define('MAX_DISPLAY_PAGES', 21);

$automation_tree_rules_actions = array(
	AUTOMATION_ACTION_TREE_DUPLICATE => 'Duplicate',
	AUTOMATION_ACTION_TREE_ENABLE => 'Enable',
	AUTOMATION_ACTION_TREE_DISABLE => 'Disable',
	AUTOMATION_ACTION_TREE_DELETE => 'Delete',
);

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
	case 'save':
		automation_tree_rules_form_save();

		break;
	case 'actions':
		automation_tree_rules_form_actions();

		break;
	case 'item_movedown':
		automation_tree_rules_item_movedown();

		header('Location: automation_tree_rules.php?action=edit&id=' . $_GET['id']);
		break;
	case 'item_moveup':
		automation_tree_rules_item_moveup();

		header('Location: automation_tree_rules.php?action=edit&id=' . $_GET['id']);
		break;
	case 'item_remove':
		automation_tree_rules_item_remove();

		header('Location: automation_tree_rules.php?action=edit&id=' . $_GET['id']);
		break;
	case 'item_edit':
		top_header();
		automation_tree_rules_item_edit();
		bottom_footer();
		break;
	case 'remove':
		automation_tree_rules_remove();

		header ('Location: automation_tree_rules.php');
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

/* --------------------------
 The Save Function
 -------------------------- */

function automation_tree_rules_form_save() {

	if (isset($_POST['save_component_automation_tree_rule'])) {

		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		/* ==================================================== */

		$save['id'] = $_POST['id'];
		$save['name'] = form_input_validate($_POST['name'], 'name', '', true, 3);
		$save['tree_id'] = form_input_validate($_POST['tree_id'], 'tree_id', '^[0-9]+$', false, 3);
		$save['tree_item_id'] = isset($_POST['tree_item_id']) ? form_input_validate($_POST['tree_item_id'], 'tree_item_id', '^[0-9]+$', false, 3) : 0;
		$save['leaf_type'] = (isset($_POST['leaf_type'])) ? form_input_validate($_POST['leaf_type'], 'leaf_type', '^[0-9]+$', false, 3) : 0;
		$save['host_grouping_type'] = isset($_POST['host_grouping_type']) ? form_input_validate($_POST['host_grouping_type'], 'host_grouping_type', '^[0-9]+$', false, 3) : 0;
		$save['rra_id'] = isset($_POST['rra_id']) ? form_input_validate($_POST['rra_id'], 'rra_id', '^[0-9]+$', false, 3) : 0;
		$save['enabled'] = (isset($_POST['enabled']) ? 'on' : '');
		if (!is_error_message()) {
			$rule_id = sql_save($save, 'automation_tree_rules');

			if ($rule_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		header('Location: automation_tree_rules.php?header=false&action=edit&id=' . (empty($rule_id) ? $_POST['id'] : $rule_id));

	}elseif (isset($_POST['save_component_automation_match_item'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		input_validate_input_number(get_request_var_post('item_id'));
		/* ==================================================== */
		$save = array();
		$save['id']        = form_input_validate($_POST['item_id'], 'item_id', '^[0-9]+$', false, 3);
		$save['rule_id']   = form_input_validate($_POST['id'], 'id', '^[0-9]+$', false, 3);
		$save['rule_type'] = AUTOMATION_RULE_TYPE_TREE_MATCH;
		$save['sequence']  = form_input_validate($_POST['sequence'], 'sequence', '^[0-9]+$', false, 3);
		$save['operation'] = form_input_validate($_POST['operation'], 'operation', '^[-0-9]+$', true, 3);
		$save['field']     = form_input_validate(((isset($_POST['field']) && $_POST['field'] != '0') ? $_POST['field'] : ''), 'field', '', true, 3);
		$save['operator']  = form_input_validate((isset($_POST['operator']) ? $_POST['operator'] : ''), 'operator', '^[0-9]+$', true, 3);
		$save['pattern']   = form_input_validate((isset($_POST['pattern']) ? $_POST['pattern'] : ''), 'pattern', '', true, 3);

		if (!is_error_message()) {
			$item_id = sql_save($save, 'automation_match_rule_items');

			if ($item_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: automation_tree_rules.php?header=false&action=item_edit&id=' . $_POST['id'] . '&item_id=' . (empty($item_id) ? $_POST['item_id'] : $item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_TREE_MATCH);
		}else{
			header('Location: automation_tree_rules.php?header=false&action=edit&id=' . $_POST['id'] . '&rule_type=' . AUTOMATION_RULE_TYPE_TREE_MATCH);
		}
	}elseif (isset($_POST['save_component_automation_tree_rule_item'])) {

		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		input_validate_input_number(get_request_var_post('item_id'));
		/* ==================================================== */

		unset($save);
		$save['id']                = form_input_validate($_POST['item_id'], 'item_id', '^[0-9]+$', false, 3);
		$save['rule_id']           = form_input_validate($_POST['id'], 'id', '^[0-9]+$', false, 3);
		$save['sequence']          = form_input_validate($_POST['sequence'], 'sequence', '^[0-9]+$', false, 3);
		$save['field']             = form_input_validate((isset($_POST['field']) ? $_POST['field'] : ''), 'field', '', true, 3);
		$save['sort_type']         = form_input_validate($_POST['sort_type'], 'sort_type', '^[0-9]+$', false, 3);
		$save['propagate_changes'] = (isset($_POST['propagate_changes']) ? 'on' : '');
		$save['search_pattern']    = isset($_POST['search_pattern']) ? form_input_validate($_POST['search_pattern'], 'search_pattern', '', false, 3) : '';
		$save['replace_pattern']   = isset($_POST['replace_pattern']) ? form_input_validate($_POST['replace_pattern'], 'replace_pattern', '', true, 3) : '';

		if (!is_error_message()) {
			$automation_graph_rule_item_id = sql_save($save, 'automation_tree_rule_items');

			if ($automation_graph_rule_item_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: automation_tree_rules.php?header=false&action=item_edit&id=' . $_POST['id'] . '&item_id=' . (empty($automation_graph_rule_item_id) ? $_POST['item_id'] : $automation_graph_rule_item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_TREE_ACTION);
		}else{
			header('Location: automation_tree_rules.php?header=false&action=edit&id=' . $_POST['id'] . '&rule_type=' . AUTOMATION_RULE_TYPE_TREE_ACTION);
		}
	} else {
		raise_message(2);
		header('Location: automation_tree_rules.php?header=false');
	}
}

/* ------------------------
 The 'actions' function
 ------------------------ */

function automation_tree_rules_form_actions() {
	global $automation_tree_rules_actions;
	global $config;

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = sanitize_unserialize_selected_items($_POST['selected_items']);

		if ($selected_items != false) {
			if ($_POST['drp_action'] == AUTOMATION_ACTION_TREE_DELETE) { /* DELETE */
				cacti_log('form_actions DELETE: ' . serialize($selected_items), true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

				db_execute('DELETE FROM automation_tree_rules WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM automation_tree_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
				db_execute('DELETE FROM automation_match_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
			}elseif ($_POST['drp_action'] == AUTOMATION_ACTION_TREE_DUPLICATE) { /* duplicate */
				for ($i=0;($i<count($selected_items));$i++) {
					cacti_log('form_actions duplicate: ' . $selected_items[$i], true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
					duplicate_automation_tree_rules($selected_items[$i], $_POST['name_format']);
				}
			}elseif ($_POST['drp_action'] == AUTOMATION_ACTION_TREE_ENABLE) { /* enable */
				for ($i=0;($i<count($selected_items));$i++) {
					cacti_log('form_actions enable: ' . $selected_items[$i], true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
					db_execute("UPDATE automation_tree_rules SET enabled='on' WHERE id=" . $selected_items[$i]);
				}
			}elseif ($_POST['drp_action'] == AUTOMATION_ACTION_TREE_DISABLE) { /* disable */
				for ($i=0;($i<count($selected_items));$i++) {
					cacti_log('form_actions disable: ' . $selected_items[$i], true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
					db_execute("UPDATE automation_tree_rules SET enabled='' WHERE id=" . $selected_items[$i]);
				}
			}
		}

		header('Location: automation_tree_rules.php?header=false');

		exit;
	}

	/* setup some variables */
	$automation_tree_rules_list = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$automation_tree_rules_list .= '<li>' . db_fetch_cell('SELECT name FROM automation_tree_rules WHERE id=' . $matches[1]) . '</li>';
			$automation_tree_rules_array[] = $matches[1];
		}
	}

	top_header();

	form_start('automation_tree_rules.php', 'automation_tree_rules_action');

	html_start_box($automation_tree_rules_actions{$_POST['drp_action']}, '60%', '', '3', 'center', '');

	if ($_POST['drp_action'] == AUTOMATION_ACTION_TREE_DELETE) { /* DELETE */
		print "<tr>
			<td class='textArea'>
				<p>Click 'Continue' to delete the following Rule(s).</p>
				<p><ul>$automation_tree_rules_list</ul></p>
			</td>
		</tr>\n";
	}elseif ($_POST['drp_action'] == AUTOMATION_ACTION_TREE_DUPLICATE) { /* duplicate */
		print "<tr>
			<td class='textArea'>
				<p>Click 'Continue' to duplicate the following Rule(s). You can
				optionally change the title format for the new Rules.</p>
				<p><ul>$automation_tree_rules_list</ul></p>
				<p>Title Format:<br>"; form_text_box('name_format', '<rule_name> (1)', '', '255', '30', 'text'); print "</p>
			</td>
		</tr>\n";
	}elseif ($_POST['drp_action'] == AUTOMATION_ACTION_TREE_ENABLE) { /* enable */
		print "<tr>
			<td class='textArea'>
				<p>Click 'Continue' to enable the following Rule(s).</p>
				<p><ul>$automation_tree_rules_list</ul></p>
				<p>Make sure, that those rules have successfully been tested!</p>
			</td>
		</tr>\n";
	}elseif ($_POST['drp_action'] == AUTOMATION_ACTION_TREE_DISABLE) { /* disable */
		print "<tr>
			<td class='textArea'>
				<p>Click 'Continue' to disable the following Rule(s).</p>
				<p><ul>$automation_tree_rules_list</ul></p>
			</td>
		</tr>\n";
	}

	if (!isset($automation_tree_rules_array)) {
		print "<tr><td class='even'><span class='textError'>You must select at least one Rule.</span></td></tr>";
		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}else {
		$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Apply requested action'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($automation_tree_rules_array) ? serialize($automation_tree_rules_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
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

function automation_tree_rules_item_movedown() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('item_id'));
	input_validate_input_number(get_request_var('rule_type'));
	/* ==================================================== */

	if ($_GET['rule_type'] == AUTOMATION_RULE_TYPE_TREE_MATCH) {
		move_item_down('automation_match_rule_items', $_GET['item_id'], 'rule_id=' . $_GET['id'] . ' AND rule_type=' . $_GET['rule_type']);
	} elseif ($_GET['rule_type'] == AUTOMATION_RULE_TYPE_TREE_ACTION) {
		move_item_down('automation_tree_rule_items', $_GET['item_id'], 'rule_id=' . $_GET['id']);
	}
}

function automation_tree_rules_item_moveup() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('item_id'));
	input_validate_input_number(get_request_var('rule_type'));
	/* ==================================================== */

	if ($_GET['rule_type'] == AUTOMATION_RULE_TYPE_TREE_MATCH) {
		move_item_up('automation_match_rule_items', $_GET['item_id'], 'rule_id=' . $_GET['id'] . ' AND rule_type=' . $_GET['rule_type']);
	} elseif ($_GET['rule_type'] == AUTOMATION_RULE_TYPE_TREE_ACTION) {
		move_item_up('automation_tree_rule_items', $_GET['item_id'], 'rule_id=' . $_GET['id']);
	}
}

function automation_tree_rules_item_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('item_id'));
	input_validate_input_number(get_request_var('rule_type'));
	/* ==================================================== */

	if ($_GET['rule_type'] == AUTOMATION_RULE_TYPE_TREE_MATCH) {
		db_execute('DELETE FROM automation_match_rule_items WHERE id=' . $_GET['item_id']);
	} elseif ($_GET['rule_type'] == AUTOMATION_RULE_TYPE_TREE_ACTION) {
		db_execute('DELETE FROM automation_tree_rule_items WHERE id=' . $_GET['item_id']);
	}


}


function automation_tree_rules_item_edit() {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('item_id'));
	input_validate_input_number(get_request_var('rule_type'));
	/* ==================================================== */


	/* handle show_trees mode */
	if (isset($_GET['show_trees'])) {
		if ($_GET['show_trees'] == '0') {
			kill_session_var('automation_tree_rules_show_trees');
		}elseif ($_GET['show_trees'] == '1') {
			$_SESSION['automation_tree_rules_show_trees'] = true;
		}
	}

	if (!empty($_GET['rule_type']) && !empty($_GET['item_id'])) {
		if ($_GET['rule_type'] == AUTOMATION_RULE_TYPE_TREE_ACTION) {
		$item = db_fetch_row('SELECT * FROM automation_tree_rule_items WHERE id=' . $_GET['item_id']);
			if ($item['field'] != AUTOMATION_TREE_ITEM_TYPE_STRING) {
				?>
<table style='width:100%;text-align:center;'>
	<tr>
		<td class='textInfo' style='text-align:right;vertical-align:top;'><span class='linkMarker'>*<a class='linkEditMain' href='<?php print htmlspecialchars('automation_tree_rules.php?action=item_edit&id=' . (isset($_GET['id']) ? $_GET['id'] : 0) . '&item_id=' . (isset($_GET['item_id']) ? $_GET['item_id'] : 0) . '&rule_type=' . (isset($_GET['rule_type']) ? $_GET['rule_type'] : 0) .'&show_trees=') . (isset($_SESSION['automation_tree_rules_show_trees']) ? '0' : '1');?>'><?php print (isset($_SESSION['automation_tree_rules_show_trees']) ? 'Dont Show' : 'Show');?> Created Trees.</a></span><br>
		</td>
	</tr>
</table>
<br>
				<?php
			}
		}
	}

	global_item_edit($_GET['id'], (isset($_GET['item_id']) ? $_GET['item_id'] : ''), $_GET['rule_type']);

	form_hidden_box('rule_type', $_GET['rule_type'], $_GET['rule_type']);
	form_hidden_box('id', (isset($_GET['id']) ? $_GET['id'] : '0'), '');
	form_hidden_box('item_id', (isset($_GET['item_id']) ? $_GET['item_id'] : '0'), '');
	if($_GET['rule_type'] == AUTOMATION_RULE_TYPE_TREE_MATCH) {
		form_hidden_box('save_component_automation_match_item', '1', '');
	} else {
		form_hidden_box('save_component_automation_tree_rule_item', '1', '');
	}
	form_save_button(htmlspecialchars('automation_tree_rules.php?action=edit&id=' . $_GET['id'] . '&rule_type='. $_GET['rule_type']));
	print '<br>';

	/* display list of matching trees */
	if (!empty($_GET['rule_type']) && !empty($_GET['item_id'])) {
		if ($_GET['rule_type'] == AUTOMATION_RULE_TYPE_TREE_ACTION) {
			if (isset($_SESSION['automation_tree_rules_show_trees']) && ($item['field'] != AUTOMATION_TREE_ITEM_TYPE_STRING)) {
				if ($_SESSION['automation_tree_rules_show_trees']) {
					display_matching_trees($_GET['id'], AUTOMATION_RULE_TYPE_TREE_ACTION, $item, basename($_SERVER['PHP_SELF']) . '?action=item_edit&id=' . $_GET['id']. '&item_id=' . $_GET['item_id'] . '&rule_type=' .$_GET['rule_type']);
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
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	if ((read_config_option('deletion_verification') == 'on') && (!isset($_GET['confirm']))) {
		top_header();
		form_confirm('Are You Sure?', "Are you sure you want to DELETE the Rule '" . db_fetch_cell('SELECT name FROM automation_tree_rules WHERE id=' . $_GET['id']) . "'?", 'automation_tree_rules.php', 'automation_tree_rules.php?action=remove&id=' . $_GET['id']);
		bottom_footer();
		exit;
	}

	if ((read_config_option('deletion_verification') == '') || (isset($_GET['confirm']))) {
		db_execute('DELETE FROM automation_match_rule_items WHERE rule_id=' . $_GET['id'] . ' AND rule_type=' . AUTOMATION_RULE_TYPE_TREE_MATCH);
		db_execute('DELETE FROM automation_tree_rule_items WHERE rule_id=' . $_GET['id']);
		db_execute('DELETE FROM automation_tree_rules WHERE id=' . $_GET['id']);
	}
}

function automation_tree_rules_edit() {
	global $config;
	global $fields_automation_tree_rules_edit1, $fields_automation_tree_rules_edit2, $fields_automation_tree_rules_edit3;

	include_once($config['base_path'].'/lib/html_tree.php');

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('tree_id'));
	input_validate_input_number(get_request_var_request('leaf_type'));
	input_validate_input_number(get_request_var_request('host_grouping_type'));
	input_validate_input_number(get_request_var_request('rra_id'));
	input_validate_input_number(get_request_var_request('tree_item_id'));
	/* ==================================================== */

	/* clean up rule name */
	if (isset($_REQUEST['name'])) {
		$_REQUEST['name'] = sanitize_search_string(get_request_var_request('name'));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	/* handle show_hosts mode */
	if (isset($_GET['show_hosts'])) {
		if ($_GET['show_hosts'] == '0') {
			kill_session_var('automation_tree_rules_show_objects');
		}elseif ($_GET['show_hosts'] == '1') {
			$_SESSION['automation_tree_rules_show_objects'] = true;
		}
	}

	if (!empty($_GET['id'])) {
		?>
<table style='width:100%;text-align:center;'>
	<tr>
		<td class='textInfo' align='right' valign='top'><span class='linkMarker'>*<a class='linkEditMain' href='<?php print htmlspecialchars('automation_tree_rules.php?action=edit&id=' . (isset($_GET['id']) ? $_GET['id'] : 0) . '&show_hosts=') . (isset($_SESSION['automation_tree_rules_show_objects']) ? '0' : '1');?>'><?php print (isset($_SESSION['automation_tree_rules_show_objects']) ? 'Dont Show' : 'Show');?> Eligible Objects.</a></span><br>
		</td>
	</tr>
</table>
		<?php
	}

	/*
	 * display the rule -------------------------------------------------------------------------------------
	 */
	$rule = array();
	if (!empty($_GET['id'])) {
		$rule = db_fetch_row('SELECT * FROM automation_tree_rules WHERE id=' . $_GET['id']);
		$header_label = '[edit: ' . $rule['name'] . ']';
	}else{
		$header_label = '[new]';
	}
	/* if creating a new rule, use all fields that have already been entered on page reload */
	if (isset($_REQUEST['name'])) {$rule['name'] = $_REQUEST['name'];}
	if (isset($_REQUEST['tree_id'])) {$rule['tree_id'] = $_REQUEST['tree_id'];}
	if (isset($_REQUEST['leaf_type'])) {$rule['leaf_type'] = $_REQUEST['leaf_type'];}
	if (isset($_REQUEST['host_grouping_type'])) {$rule['host_grouping_type'] = $_REQUEST['host_grouping_type'];}
	if (isset($_REQUEST['rra_id'])) {$rule['rra_id'] = $_REQUEST['rra_id'];}
	if (isset($_REQUEST['tree_item_id'])) {$rule['tree_item_id'] = $_REQUEST['tree_item_id'];}

	form_start('automation_tree_rules.php', 'form_automation_tree_rule_edit');

	html_start_box("Tree Rule Selection $header_label", '100%', '', '3', 'center', '');

	if (!empty($_GET['id'])) {
		/* display whole rule */
		$form_array = $fields_automation_tree_rules_edit1 + $fields_automation_tree_rules_edit2 + $fields_automation_tree_rules_edit3;
	} else {
		/* display first part of rule only and request user to proceed */
		$form_array = $fields_automation_tree_rules_edit1;
	}

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($form_array, (isset($rule) ? $rule : array()))
	));

	html_end_box();
	form_hidden_box('id', (isset($rule['id']) ? $rule['id'] : '0'), '');
	form_hidden_box('item_id', (isset($rule['item_id']) ? $rule['item_id'] : '0'), '');
	form_hidden_box('save_component_automation_tree_rule', '1', '');

	/*
	 * display the rule items -------------------------------------------------------------------------------
	 */
	if (!empty($rule['id'])) {
		# display tree rules for host match
		display_match_rule_items('Object Selection Criteria',
			$rule['id'],
			AUTOMATION_RULE_TYPE_TREE_MATCH,
			basename($_SERVER['PHP_SELF']));

		# fetch tree action rules
		display_tree_rule_items('Tree Creation Criteria',
			$rule['id'],
			$rule['leaf_type'],
			AUTOMATION_RULE_TYPE_TREE_ACTION,
			basename($_SERVER['PHP_SELF']));
	}

	form_save_button('automation_tree_rules.php');
	print '<br>';

	if (!empty($rule['id'])) {
		/* display list of matching hosts */
		if (isset($_SESSION['automation_tree_rules_show_objects'])) {
			if ($_SESSION['automation_tree_rules_show_objects']) {
				if ($rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
					display_matching_hosts($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, basename($_SERVER['PHP_SELF']) . '?action=edit&id=' . $_GET['id']);
				} elseif ($rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
					display_matching_graphs($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, basename($_SERVER['PHP_SELF']) . '?action=edit&id=' . $_GET['id']);
				}
			}
		}
	}

	?>
	<script type='text/javascript'>
	$(function() {
		applyItemTypeChange();
	});

	function applyTreeChange() {
		strURL  = 'automation_tree_rules.php?action=edit&id=' + $('#id').val();
		strURL += '&name=' + $('#name').val();
		strURL += '&tree_id=' + $('tree_id').val();
		strURL += '&tree_item_id=' + $('#tree_item_id').val();
		strURL += '&leaf_type=' + $('#leaf_type').val();
		strURL += '&host_grouping_type=' + $('#host_grouping_type').val();
		strURL += '&rra_id=' + $('#rra_id').val();
		strURL += '&rows=' + $('#graph_rows').val();

		loadPageNoHeader(strURL);
	}

	function applyItemTypeChange() {
		if ($('#leaf_type').val() == '<?php print TREE_ITEM_TYPE_HOST;?>') {
			$('#host_grouping_type').val('');
			$('#host_grouping_type').prop('disabled', false);
			$('#rra_id').val('');
			$('#rra_id').prop('disabled', true);
		} else if ($('#leaf_type').val() == '<?php print TREE_ITEM_TYPE_GRAPH;?>') {
			$('#host_grouping_type').val('');
			$('#host_grouping_type').prop('disabled', true);
			$('#rra_id').val('');
			$('#rra_id').prop('disabled', false);
		}
	}
	</script>
	<?php
}

function automation_tree_rules() {
	global $automation_tree_rules_actions, $config, $item_rows;
	global $automation_tree_item_types, $host_group_types;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('status'));
	input_validate_input_number(get_request_var_request('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_autom_tr_current_page');
		kill_session_var('sess_autom_tr_filter');
		kill_session_var('sess_autom_tr_sort_column');
		kill_session_var('sess_autom_tr_sort_direction');
		kill_session_var('sess_autom_tr_status');
		kill_session_var('sess_autom_tr_rows');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		unset($_REQUEST['status']);
		unset($_REQUEST['rows']);
	}else{
		$changed = 0;
		$changed += check_changed('status', 'sess_autom_tr_status');
		$changed += check_changed('rows',   'sess_default_rows');
		$changed += check_changed('filter', 'sess_autom_tr_filter');

		if ($changed) {
			$_REQUEST['page'] = 1;
		}
	}

	if ((!empty($_SESSION['sess_autom_tr_status'])) && (!empty($_REQUEST['status']))) {
		if ($_SESSION['sess_autom_tr_status'] != $_REQUEST['status']) {
			$_REQUEST['page'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_autom_tr_current_page', '1');
	load_current_session_value('filter', 'sess_autom_tr_filter', '');
	load_current_session_value('sort_column', 'sess_autom_tr_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_autom_tr_sort_direction', 'ASC');
	load_current_session_value('status', 'sess_autom_tr_status', '-1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$_REQUEST['rows'] = read_config_option('num_rows_table');
	}


	html_start_box('Tree Rules', '100%', '', '3', 'center', 'automation_tree_rules.php?action=edit');
	#print '<pre>'; print_r($_POST); print_r($_GET); print_r($_REQUEST); print '</pre>';

	?>
	<tr class='even'>
		<td>
			<form id='form_automation' action='automation_tree_rules.php'>
				<table class='filterTable'>
					<tr>
						<td>
							Search
						</td>
						<td>
							<input type='text' id='filter' size='25' value='<?php print get_request_var_request('filter');?>'>
						</td>
						<td>
							Status
						</td>
						<td>
							<select id='status'>
								<option value='-1' <?php print (get_request_var_request('status') == '-1' ? ' selected':'');?>>Any</option>
								<option value='-2' <?php print (get_request_var_request('status') == '-2' ? ' selected':'');?>>Enabled</option>
								<option value='-3' <?php print (get_request_var_request('status') == '-3' ? ' selected':'');?>>Disabled</option>
							</select>
						</td>
						<td>
							Tree Rules
						</td>
						<td>
							<select id='rows'>
								<option value='-1' <?php print (get_request_var_request('rows') == '-1' ? ' selected':'');?>>Default</option>
								<?php
								if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'" . (get_request_var_request('rows') == $key ? ' selected':'') . '>' . $value . "</option>\n";
								}
								}
								?>
							</select>
						</td>
						<td>
							<input id='refresh' type='button' value='Go'> 
						</td>
						<td>
							<input id='clear' type='button' value='Clear'>
						</td>
					</tr>
				</table>
				<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
			</form>
			<script type='text/javascript'>
			function applyFilter() {
				strURL = 'automation_tree_rules.php' +
					'?status='+$('#status').val() +
					'&filter='+$('#filter').val() +
					'&rows='+$('#rows').val() +
					'&page='+$('#page').val() +
					'&header=false';

				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'automation_tree_rules.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh, #rows, #status').change(function() {
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

	form_end();

	/* form the 'WHERE' clause for our main sql query */
	if (strlen(get_request_var_request('filter'))) {
		$sql_where = "WHERE (atr.name LIKE '%%" . get_request_var_request('filter') . "%%')";
	}else{
		$sql_where = '';
	}

	if (get_request_var_request('status') == '-1') {
		/* Show all items */
	}elseif (get_request_var_request('status') == '-2') {
		$sql_where .= (strlen($sql_where) ? " AND atr.enabled='on'" : "WHERE .atr.enabled='on'");
	}elseif (get_request_var_request('status') == '-3') {
		$sql_where .= (strlen($sql_where) ? " AND atr.enabled=''" : "WHERE atr.enabled=''");
	}

	form_start('automation_tree_rules.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT COUNT(atr.id) 
		FROM automation_tree_rules AS atr
		LEFT JOIN graph_tree AS gt
		ON atr.id=gt.id 
		$sql_where");

	$automation_tree_rules = db_fetch_assoc("SELECT atr.id, atr.name, atr.tree_id, atr.tree_item_id,
		atr.leaf_type, atr.host_grouping_type, atr.rra_id, atr.enabled,
		gt.name AS tree_name, gti.title AS subtree_name, rra.name AS rra_name 
		FROM automation_tree_rules AS atr
		LEFT JOIN graph_tree AS gt
		ON atr.tree_id=gt.id
		LEFT JOIN graph_tree_items AS gti
		ON atr.tree_item_id = gti.id
		LEFT JOIN rra
		ON atr.rra_id=rra.id
		$sql_where
		ORDER BY " . get_request_var_request('sort_column') . ' ' . get_request_var_request('sort_direction') . "
		LIMIT " . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows'));

	$nav = html_nav_bar('automation_tree_rules.php?filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $_REQUEST['rows'], $total_rows, 11, 'Trees', 'page', 'main');

	print $nav;

	$display_text = array(
		'name' 					=> array('display' => 'Rule Name', 'align' => 'left', 'sort' => 'ASC'),
		'id' 					=> array('display' => 'Id', 'align' => 'right', 'sort' => 'ASC'),
		'tree_name' 			=> array('display' => 'Hook into Tree', 'align' => 'left', 'sort' =>'ASC'),
		'subtree_name'			=> array('display' => 'At Subtree', 'align' => 'left', 'sort' => 'ASC'),
		'leaf_type'				=> array('display' => 'This Type', 'align' => 'left', 'sort' => 'ASC'),
		'host_grouping_type'	=> array('display' => 'Using Grouping', 'align' => 'left', 'sort' => 'ASC'),
		'rra_id'				=> array('display' => 'Using Round Robin Archive', 'align' => 'left', 'sort' => 'ASC'),
		'enabled' 				=> array('display' => 'Enabled', 'align' => 'right', 'sort' => 'ASC'));

	html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

	if (sizeof($automation_tree_rules) > 0) {
		foreach ($automation_tree_rules as 	$automation_tree_rule) {
			$tree_item_type_name = ((empty($automation_tree_rule['leaf_type'])) ? '<em>None</em>' : $automation_tree_item_types{$automation_tree_rule['leaf_type']});
			$subtree_name = ((empty($automation_tree_rule['subtree_name'])) ? '<em>ROOT</em>' : $automation_tree_rule['subtree_name']);
			$tree_host_grouping_type = ((empty($host_group_types{$automation_tree_rule['host_grouping_type']})) ? '' : $host_group_types{$automation_tree_rule['host_grouping_type']});
			form_alternate_row('line' .  $automation_tree_rule['id'], true);

			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('automation_tree_rules.php?action=edit&id=' . $automation_tree_rule['id'] . '&page=1') . "'>" . (get_request_var_request('filter') != '' ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($automation_tree_rule['name'])) : htmlspecialchars($automation_tree_rule['name'])) . '</a>', $automation_tree_rule['id']);
			form_selectable_cell($automation_tree_rule['id'], $automation_tree_rule['id'], '', 'text-align:right');
			form_selectable_cell($automation_tree_rule['tree_name'], $automation_tree_rule['id']);
			form_selectable_cell($subtree_name, $automation_tree_rule['id']);
			form_selectable_cell($tree_item_type_name, $automation_tree_rule['id']);
			form_selectable_cell($tree_host_grouping_type, $automation_tree_rule['id']);
			form_selectable_cell($automation_tree_rule['rra_name'], $automation_tree_rule['id']);
			form_selectable_cell($automation_tree_rule['enabled'] ? 'Enabled' : 'Disabled', $automation_tree_rule['id'], '', 'text-align:right');
			form_checkbox_cell($automation_tree_rule['name'], $automation_tree_rule['id']);

			form_end_row();
		}

		print $nav;
	}else{
		print "<tr><td colspan='9'><em>No Tree Rules</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($automation_tree_rules_actions);

	form_end();
}
?>
