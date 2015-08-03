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

$automation_graph_rules_actions = array(
	AUTOMATION_ACTION_GRAPH_DUPLICATE => 'Duplicate',
	AUTOMATION_ACTION_GRAPH_ENABLE => 'Enable',
	AUTOMATION_ACTION_GRAPH_DISABLE => 'Disable',
	AUTOMATION_ACTION_GRAPH_DELETE => 'Delete',
);

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
	case 'save':
		automation_graph_rules_form_save();

		break;
	case 'actions':
		automation_graph_rules_form_actions();

		break;
	case 'item_movedown':
		automation_graph_rules_item_movedown();

		header('Location: automation_graph_rules.php?header=false&action=edit&id=' . $_GET['id']);
		break;
	case 'item_moveup':
		automation_graph_rules_item_moveup();

		header('Location: automation_graph_rules.php?header=false&action=edit&id=' . $_GET['id']);
		break;
	case 'item_remove':
		automation_graph_rules_item_remove();

		header('Location: automation_graph_rules.php?header=false&action=edit&id=' . $_GET['id']);
		break;
	case 'item_edit':
		top_header();
		automation_graph_rules_item_edit();
		bottom_footer();
		break;
	case 'remove':
		automation_graph_rules_remove();

		header ('Location: automation_graph_rules.php');
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

function automation_graph_rules_form_save() {

	if (isset($_POST['save_component_automation_graph_rule'])) {

		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		/* ==================================================== */

		$save['id'] = $_POST['id'];
		$save['name'] = form_input_validate($_POST['name'], 'name', '', false, 3);
		$save['snmp_query_id'] = form_input_validate($_POST['snmp_query_id'], 'snmp_query_id', '^[0-9]+$', false, 3);
		$save['graph_type_id'] = (isset($_POST['graph_type_id'])) ? form_input_validate($_POST['graph_type_id'], 'graph_type_id', '^[0-9]+$', false, 3) : 0;
		$save['enabled'] = (isset($_POST['enabled']) ? 'on' : '');
		if (!is_error_message()) {
			$rule_id = sql_save($save, 'automation_graph_rules');

			if ($rule_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if ((is_error_message()) || (empty($_POST["id"]))) {
			header('Location: automation_graph_rules.php?header=false&action=edit&id=' . (empty($rule_id) ? $_POST['id'] : $rule_id));
		}else{
			header('Location: automation_graph_rules.php?header=false');
		}
	}elseif (isset($_POST['save_component_automation_graph_rule_item'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		input_validate_input_number(get_request_var_post('item_id'));
		/* ==================================================== */
		$save = array();
		$save['id']        = form_input_validate($_POST['item_id'], 'item_id', '^[0-9]+$', false, 3);
		$save['rule_id']   = form_input_validate($_POST['id'], 'id', '^[0-9]+$', false, 3);
		$save['sequence']  = form_input_validate($_POST['sequence'], 'sequence', '^[0-9]+$', false, 3);
		$save['operation'] = form_input_validate($_POST['operation'], 'operation', '^[-0-9]+$', true, 3);
		$save['field']     = form_input_validate(((isset($_POST['field']) && $_POST['field'] != '0') ? $_POST['field'] : ''), 'field', '', true, 3);
		$save['operator']  = form_input_validate((isset($_POST['operator']) ? $_POST['operator'] : ''), 'operator', '^[0-9]+$', true, 3);
		$save['pattern']   = form_input_validate((isset($_POST['pattern']) ? $_POST['pattern'] : ''), 'pattern', '', true, 3);

		if (!is_error_message()) {
			$item_id = sql_save($save, 'automation_graph_rule_items');

			if ($item_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: automation_graph_rules.php?header=false&action=item_edit&id=' . $_POST['id'] . '&item_id=' . (empty($item_id) ? $_POST['item_id'] : $item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_ACTION);
		}else{
			header('Location: automation_graph_rules.php?header=false&action=edit&id=' . $_POST['id'] . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_ACTION);
		}
	}elseif (isset($_POST['save_component_automation_match_item'])) {

		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		input_validate_input_number(get_request_var_post('item_id'));
		/* ==================================================== */
		unset($save);
		$save['id'] = form_input_validate($_POST['item_id'], 'item_id', '^[0-9]+$', false, 3);
		$save['rule_id'] = form_input_validate($_POST['id'], 'id', '^[0-9]+$', false, 3);
		$save['rule_type'] = AUTOMATION_RULE_TYPE_GRAPH_MATCH;
		$save['sequence'] = form_input_validate($_POST['sequence'], 'sequence', '^[0-9]+$', false, 3);
		$save['operation'] = form_input_validate($_POST['operation'], 'operation', '^[-0-9]+$', true, 3);
		$save['field'] = form_input_validate(((isset($_POST['field']) && $_POST['field'] != '0') ? $_POST['field'] : ''), 'field', '', true, 3);
		$save['operator'] = form_input_validate((isset($_POST['operator']) ? $_POST['operator'] : ''), 'operator', '^[0-9]+$', true, 3);
		$save['pattern'] = form_input_validate((isset($_POST['pattern']) ? $_POST['pattern'] : ''), 'pattern', '', true, 3);

		if (!is_error_message()) {
			$item_id = sql_save($save, 'automation_match_rule_items');

			if ($item_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: automation_graph_rules.php?header=false&action=item_edit&id=' . $_POST['id'] . '&item_id=' . (empty($item_id) ? $_POST['item_id'] : $item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_MATCH);
		}else{
			header('Location: automation_graph_rules.php?header=false&action=edit&id=' . $_POST['id'] . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_MATCH);
		}
	} else {
		raise_message(2);
		header('Location: automation_graph_rules.php?header=false');
	}
}

/* ------------------------
 The 'actions' function
 ------------------------ */

function automation_graph_rules_form_actions() {
	global $config, $colors, $automation_graph_rules_actions;

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = sanitize_unserialize_selected_items($_POST['selected_items']);

		if ($selected_items != false) {
			if ($_POST['drp_action'] == AUTOMATION_ACTION_GRAPH_DELETE) { /* delete */
				db_execute('DELETE FROM automation_graph_rules WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM automation_graph_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
				db_execute('DELETE FROM automation_match_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
			}elseif ($_POST['drp_action'] == AUTOMATION_ACTION_GRAPH_DUPLICATE) { /* duplicate */
				for ($i=0;($i<count($selected_items));$i++) {
					cacti_log('form_actions duplicate: ' . $selected_items[$i] . ' name: ' . $_POST['name_format'], true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
					duplicate_automation_graph_rules($selected_items[$i], $_POST['name_format']);
				}
			}elseif ($_POST['drp_action'] == AUTOMATION_ACTION_GRAPH_ENABLE) { /* enable */
				for ($i=0;($i<count($selected_items));$i++) {
					cacti_log('form_actions enable: ' . $selected_items[$i], true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

					db_execute("UPDATE automation_graph_rules SET enabled='on' WHERE id=" . $selected_items[$i]);
				}
			}elseif ($_POST['drp_action'] == AUTOMATION_ACTION_GRAPH_DISABLE) { /* disable */
				for ($i=0;($i<count($selected_items));$i++) {
					cacti_log('form_actions disable: ' . $selected_items[$i], true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

					db_execute("UPDATE automation_graph_rules SET enabled='' WHERE id=" . $selected_items[$i]);
				}
			}
		}

		header('Location: automation_graph_rules.php?header=false');

		exit;
	}

	/* setup some variables */
	$automation_graph_rules_list = ''; $i = 0;
	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$automation_graph_rules_list .= '<li>' . db_fetch_cell('SELECT name FROM automation_graph_rules WHERE id=' . $matches[1]) . '</li>';
			$automation_graph_rules_array[] = $matches[1];
		}
	}

	top_header();

	form_start('automation_graph_rules.php', 'automation_graph_rules');

	html_start_box('<strong>' . $automation_graph_rules_actions{$_POST['drp_action']} . '</strong>', '60%', $colors['header_panel'], '3', 'center', '');

	if ($_POST['drp_action'] == AUTOMATION_ACTION_GRAPH_DELETE) { /* delete */
		print "	<tr>
			<td class='textArea'>
				<p>Are you sure you want to delete the following Rules?  If so, press 'Continue'.</p>
				<ul>$automation_graph_rules_list</ul>
			</td>
		</tr>";
	}elseif ($_POST['drp_action'] == AUTOMATION_ACTION_GRAPH_DUPLICATE) { /* duplicate */
		print "<tr>
			<td class='textArea'>
				<p>Click 'Continue' to duplicate the following Rule(s). You can
				optionally change the title format for the new Rules.</p>
				<ul>$automation_graph_rules_list</ul>
				<p><strong>Title Format:</strong><br>"; form_text_box('name_format', '<rule_name> (1)', '', '255', '30', 'text'); print "</p>
			</td>
		</tr>\n";
	}elseif ($_POST['drp_action'] == AUTOMATION_ACTION_GRAPH_ENABLE) { /* enable */
		print "<tr>
			<td class='textArea'>
				<p>Click 'Continue' to enable the following Rule(s).</p>
				<ul>$automation_graph_rules_list</ul>
				<p><strong>Make sure, that those rules have successfully been tested!</strong></p>
			</td>
		</tr>\n";
	}elseif ($_POST['drp_action'] == AUTOMATION_ACTION_GRAPH_DISABLE) { /* disable */
		print "<tr>
			<td class='textArea'>
				<p>Click 'Continue' to disable the following Rule(s).</p>
				<ul>$automation_graph_rules_list</ul>
			</td>
		</tr>\n";
	}

	if (!isset($automation_graph_rules_array)) {
		print "<tr class='even'><td><span class='textError'>You must select at least one Rule.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}else {
		$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Apply requested action'>";
	}

	print "	<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($automation_graph_rules_array) ? serialize($automation_graph_rules_array) : '') . "'>
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
function automation_graph_rules_item_movedown() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('item_id'));
	input_validate_input_number(get_request_var('rule_type'));
	/* ==================================================== */

	if ( $_GET['rule_type'] == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		move_item_down('automation_match_rule_items', $_GET['item_id'], 'rule_id=' . $_GET['id'] . ' AND rule_type=' . $_GET['rule_type']);
	} elseif ($_GET['rule_type'] == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		move_item_down('automation_graph_rule_items', $_GET['item_id'], 'rule_id=' . $_GET['id']);
	}
}



function automation_graph_rules_item_moveup() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('item_id'));
	input_validate_input_number(get_request_var('rule_type'));
	/* ==================================================== */

	if ( $_GET['rule_type'] == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		move_item_up('automation_match_rule_items', $_GET['item_id'], 'rule_id=' . $_GET['id'] . ' AND rule_type=' . $_GET['rule_type']);
	} elseif ($_GET['rule_type'] == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		move_item_up('automation_graph_rule_items', $_GET['item_id'], 'rule_id=' . $_GET['id']);
	}
}



function automation_graph_rules_item_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('item_id'));
	input_validate_input_number(get_request_var('rule_type'));
	/* ==================================================== */

	if ( $_GET['rule_type'] == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		db_execute('delete from automation_match_rule_items where id=' . $_GET['item_id']);
	} elseif ($_GET['rule_type'] == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		db_execute('delete from automation_graph_rule_items where id=' . $_GET['item_id']);
	}

}



function automation_graph_rules_item_edit() {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('item_id'));
	input_validate_input_number(get_request_var('rule_type'));
	/* ==================================================== */

	global_item_edit($_GET['id'], (isset($_GET['item_id']) ? $_GET['item_id'] : ''), $_GET['rule_type']);

	form_hidden_box('rule_type', $_GET['rule_type'], $_GET['rule_type']);
	form_hidden_box('id', (isset($_GET['id']) ? $_GET['id'] : '0'), '');
	form_hidden_box('item_id', (isset($_GET['item_id']) ? $_GET['item_id'] : '0'), '');
	if($_GET['rule_type'] == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		form_hidden_box('save_component_automation_match_item', '1', '');
	} else {
		form_hidden_box('save_component_automation_graph_rule_item', '1', '');
	}
	form_save_button(htmlspecialchars('automation_graph_rules.php?action=edit&id=' . $_GET['id'] . '&rule_type='. $_GET['rule_type']));
//Now we need some javascript to make it dynamic
?>
<script type='text/javascript'>

toggle_operation();
toggle_operator();

function toggle_operation() {
	if ($('#operation').val() == '<?php print AUTOMATION_OPER_RIGHT_BRACKET;?>') {
		$('#field').val() = '';
		$('#field').prop('disabled', true);
		$('#operator').val() = 0;
		$('#operator').prop('disabled', true);
		$('#pattern').val() = '';
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
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	if ((read_config_option('deletion_verification') == 'on') && (!isset($_GET['confirm']))) {
		top_header();
		form_confirm('Are You Sure?', "Are you sure you want to delete the Rule <strong>'" . db_fetch_cell('SELECT name FROM automation_graph_rules WHERE id=' . $_GET['id']) . "'</strong>?", 'automation_graph_rules.php', 'automation_graph_rules.php?action=remove&id=' . $_GET['id']);
		bottom_footer();
		exit;
	}

	if ((read_config_option('deletion_verification') == '') || (isset($_GET['confirm']))) {
		db_execute('DELETE FROM automation_match_rule_items WHERE rule_id=' . $_GET['id'] .  ' AND rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_MATCH);
		db_execute('DELETE FROM automation_graph_rule_items WHERE rule_id=' . $_GET['id']);
		db_execute('DELETE FROM automation_graph_rules WHERE id=' . $_GET['id']);
	}
}


function automation_graph_rules_edit() {
	global $colors, $config;
	global $fields_automation_graph_rules_edit1, $fields_automation_graph_rules_edit2, $fields_automation_graph_rules_edit3;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	input_validate_input_number(get_request_var_request('snmp_query_id'));
	input_validate_input_number(get_request_var_request('graph_type_id'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up rule name */
	if (isset($_REQUEST['name'])) {
		$_REQUEST['name'] = sanitize_search_string(get_request_var('name'));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_automation_graph_rule_current_page', '1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	/* handle show_graphs mode */
	if (isset($_GET['show_graphs'])) {
		if ($_GET['show_graphs'] == '0') {
			kill_session_var('automation_graph_rules_show_graphs');
		}elseif ($_GET['show_graphs'] == '1') {
			$_SESSION['automation_graph_rules_show_graphs'] = true;
		}
	}

	/* handle show_hosts mode */
	if (isset($_GET['show_hosts'])) {
		if ($_GET['show_hosts'] == '0') {
			kill_session_var('automation_graph_rules_show_hosts');
		}elseif ($_GET['show_hosts'] == '1') {
			$_SESSION['automation_graph_rules_show_hosts'] = true;
		}
	}


	/*
	 * display the rule -------------------------------------------------------------------------------------
	 */
	$rule = array();
	if (!empty($_GET['id'])) {
		$rule = db_fetch_row('SELECT * FROM automation_graph_rules where id=' . $_GET['id']);
		if (!empty($_GET['graph_type_id'])) {
			$rule['graph_type_id'] = $_GET['graph_type_id']; # set query_type for display
		}
		# setup header
		$header_label = '[edit: ' . $rule['name'] . ']';
	}else{
		$header_label = '[new]';
	}


	/*
	 * show hosts? ------------------------------------------------------------------------------------------
	 */
	if (!empty($_GET['id'])) {
		?>
<table style='width:100%;text-align:center;'>
	<tr>
		<td class='textInfo' align='right' valign='top'><span class='linkMarker'>*<a class='linkEditMain' href='<?php print htmlspecialchars('automation_graph_rules.php?action=edit&id=' . (isset($_GET['id']) ? $_GET['id'] : 0) . '&show_hosts=') . (isset($_SESSION['automation_graph_rules_show_hosts']) ? '0' : '1');?>'><strong><?php print (isset($_SESSION['automation_graph_rules_show_hosts']) ? 'Dont Show' : 'Show');?></strong> Matching Devices.</a></span><br>
		</td>
	</tr>
		<?php
	}

	/*
	 * show graphs? -----------------------------------------------------------------------------------------
	 */
	if (!empty($rule['graph_type_id']) && $rule['graph_type_id'] > 0) {
		?>
	<tr>
		<td class='textInfo' align='right' valign='top'>
			<span class='linkMarker'>*<a class='linkEditMain' href='<?php print htmlspecialchars('automation_graph_rules.php?action=edit&id=' . (isset($_GET['id']) ? $_GET['id'] : 0) . '&show_graphs=') . (isset($_SESSION['automation_graph_rules_show_graphs']) ? '0' : '1');?>'><strong><?php print (isset($_SESSION['automation_graph_rules_show_graphs']) ? 'Dont Show' : 'Show');?></strong> Matching Graphs.</a></span><br>
		</td>
	</tr>
</table>
		<?php
	}

	form_start('form_automation_graph_rule_edit.php', 'automation_graph_rules');

	html_start_box("<strong>Rule Selection</strong> $header_label", '100%', $colors['header'], '3', 'center', '');

	if (!empty($_GET['id'])) {
		/* display whole rule */
		$form_array = $fields_automation_graph_rules_edit1 + $fields_automation_graph_rules_edit2 + $fields_automation_graph_rules_edit3;
	} else {
		/* display first part of rule only and request user to proceed */
		$form_array = $fields_automation_graph_rules_edit1;
	}

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($form_array, (isset($rule) ? $rule : array()))
	));

	html_end_box();
	form_hidden_box('id', (isset($rule['id']) ? $rule['id'] : '0'), '');
	form_hidden_box('item_id', (isset($rule['item_id']) ? $rule['item_id'] : '0'), '');
	form_hidden_box('save_component_automation_graph_rule', '1', '');

	/*
	 * display the rule items -------------------------------------------------------------------------------
	 */
	if (!empty($rule['id'])) {
		# display graph rules for host match
		display_match_rule_items('Device Selection Criteria',
			$rule['id'],
			AUTOMATION_RULE_TYPE_GRAPH_MATCH,
			basename($_SERVER['PHP_SELF']));

		# fetch graph action rules
		display_graph_rule_items('Graph Creation Criteria',
			$rule['id'],
			AUTOMATION_RULE_TYPE_GRAPH_ACTION,
			basename($_SERVER['PHP_SELF']));
	}

	form_save_button('automation_graph_rules.php');
	print '<br>';

	if (!empty($rule['id'])) {
		/* display list of matching hosts */
		if (isset($_SESSION['automation_graph_rules_show_hosts'])) {
			if ($_SESSION['automation_graph_rules_show_hosts']) {
				display_matching_hosts($rule, AUTOMATION_RULE_TYPE_GRAPH_MATCH, basename($_SERVER['PHP_SELF']) . '?action=edit&id=' . $_GET['id']);
			}
		}

		/* display list of new graphs */
		if (isset($_SESSION['automation_graph_rules_show_graphs'])) {
			if ($_SESSION['automation_graph_rules_show_graphs']) {
				display_new_graphs($rule);
			}
		}
	}

	?>
	<script type='text/javascript'>
	function applySNMPQueryIdChange() {
		strURL = 'automation_graph_rules.php?action=edit&id=' + $('#id').val();
		strURL = strURL + '&snmp_query_id=' + $('#snmp_query_id').val();
		strURL = strURL + '&name=' + $('#name').val();
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applyFilter();
		});
	}

	function applySNMPQueryTypeChange() {
		strURL = 'automation_graph_rules.php?action=edit&id=' + $('#id').val();
		strURL = strURL + '&snmp_query_id=' + $('#snmp_query_id').val();
		strURL = strURL + '&name=' + $('#name').val();
		strURL = strURL + '&snmp_query_type' + $('#name').val();
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applyFilter();
		});
	}
	</script>
	<?php
}

function automation_graph_rules() {
	global $colors, $automation_graph_rules_actions, $config, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('status'));
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('snmp_query_id'));
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
		kill_session_var('sess_autom_gr_current_page');
		kill_session_var('sess_autom_gr_filter');
		kill_session_var('sess_autom_gr_sort_column');
		kill_session_var('sess_autom_gr_sort_direction');
		kill_session_var('sess_autom_gr_status');
		kill_session_var('sess_autom_gr_rows');
		kill_session_var('sess_autom_gr_snmp_query_id');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		unset($_REQUEST['status']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['snmp_query_id']);
	}else{
		$changed = 0;
		$changed += check_changed('status',   'sess_autom_gr_status');
		$changed += check_changed('snmp_query_id', 'sess_autom_gr_snmp_query_id');
		$changed += check_changed('rows',   'sess_default_rows');
		$changed += check_changed('filter', 'sess_autom_gr_filter');

		if ($changed) {
			$_REQUEST['page'] = 1;
		}
	}

	if ((!empty($_SESSION['sess_autom_gr_status'])) && (!empty($_REQUEST['status']))) {
		if ($_SESSION['sess_autom_gr_status'] != $_REQUEST['status']) {
			$_REQUEST['page'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_autom_gr_current_page', '1');
	load_current_session_value('filter', 'sess_autom_gr_filter', '');
	load_current_session_value('sort_column', 'sess_autom_gr_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_autom_gr_sort_direction', 'ASC');
	load_current_session_value('status', 'sess_autom_gr_status', '-1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
	load_current_session_value('snmp_query_id', 'sess_autom_gr_snmp_query_id', '');

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$_REQUEST['rows'] = read_config_option('num_rows_table');
	}


	html_start_box('<strong>Graph Rules</strong>', '100%', $colors['header'], '3', 'center', 'automation_graph_rules.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_automation' action='automation_graph_rules.php'>
				<table class='filterTable'>
					<tr>
						<td>
							Search
						</td>
						<td>
							<input type='text' id='filter' size='25' value='<?php print get_request_var_request('filter');?>'>
						</td>
						<td class='nowrap'>
							Data Query
						</td>
						<td>
							<select id='snmp_query_id'>
								<option value='-1'<?php print (get_request_var_request('snmp_query_id') == '-1' ? ' selected':'');?>>Any</option>
								<?php 
								$available_data_queries = db_fetch_assoc('SELECT DISTINCT
									ar.snmp_query_id, sq.name 
									FROM automation_graph_rules AS ar
									LEFT JOIN snmp_query AS sq
									ON (ar.snmp_query_id=sq.id)
									ORDER BY sq.name');
	
								if (sizeof($available_data_queries)) {
									foreach ($available_data_queries as $data_query) {
										print "<option value='" . $data_query['snmp_query_id'] . "'" . (get_request_var_request('snmp_query_id') == $data_query['snmp_query_id'] ? ' selected':'') .  '>' . $data_query['name'] . "</option>\n";
									}
								}
								?>
							</select>
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
							Rules
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
							<input type='submit' id='refresh' name'go' value='Go'>
						</td>
						<td>
							<input type='button' id='clear' value='Clear'></td>
					</tr>
				</table>
			<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL = 'automation_graph_rules.php?status='+$('#status').val()+'&filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&snmp_query_id='+$('#snmp_query_id').val()+'&header=false';
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'automation_graph_rules.php?clear=1&header=false';
			loadPageNoHeader(strURL);
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

	form_end();

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request('filter'))) {
		$sql_where = "WHERE (agr.name LIKE '%%" . get_request_var_request('filter') . "%%')";
	}else{
		$sql_where = '';
	}

	if (get_request_var_request('status') == '-1') {
		/* Show all items */
	}elseif (get_request_var_request('status') == '-2') {
		$sql_where .= (strlen($sql_where) ? " and agr.enabled='on'" : "where agr.enabled='on'");
	}elseif (get_request_var_request('status') == '-3') {
		$sql_where .= (strlen($sql_where) ? " and agr.enabled=''" : "where agr.enabled=''");
	}

	if (get_request_var_request('snmp_query_id') == '-1') {
		/* show all items */
	} elseif (!empty($_REQUEST['snmp_query_id'])) {
		$sql_where .= (strlen($sql_where) ? ' AND ' : ' WHERE ');
		$sql_where .= 'agr.snmp_query_id=' . get_request_var_request('snmp_query_id');
	}

	form_start('automation_graph_rules.php', 'chk');

	html_start_box('', '100%', $colors['header'], '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(agr.id)
		FROM automation_graph_rules AS agr
		LEFT JOIN snmp_query AS sq
		ON (agr.snmp_query_id=sq.id)
		$sql_where");

	$automation_graph_rules_list = db_fetch_assoc("SELECT agr.id, agr.name, agr.snmp_query_id, agr.graph_type_id, 
		agr.enabled, sq.name AS snmp_query_name, sqg.name AS graph_type_name 
		FROM automation_graph_rules AS agr
		LEFT JOIN snmp_query AS sq
		ON (agr.snmp_query_id=sq.id) 
		LEFT JOIN snmp_query_graph AS sqg	
		ON (agr.graph_type_id=sqg.id) 
		$sql_where 
		ORDER BY " . get_request_var_request('sort_column') . ' ' . get_request_var_request('sort_direction') . "
		LIMIT " . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows'));

	$nav = html_nav_bar('automation_graph_rules.php?filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 7, 'Graphs', 'page', 'main');

	print $nav;

	$display_text = array(
		'name'            => array('display' => 'Rule Name', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The name of this rule.'),
		'id'              => array('display' => 'Id', 'align' => 'right', 'sort' => 'ASC', 'tip' => 'The internal database ID for this rule.  Useful in performing debugging and automation.'),
		'snmp_query_name' => array('display' => 'Data Query', 'align' => 'left', 'sort' => 'ASC'),
		'graph_type_name' => array('display' => 'Graph Type', 'align' => 'left', 'sort' => 'ASC'),
		'enabled'         => array('display' => 'Enabled', 'align' => 'right', 'sort' => 'ASC'),
	);

	html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

	$i = 0;
	if (sizeof($automation_graph_rules_list) > 0) {
		foreach ($automation_graph_rules_list as $automation_graph_rules) {
			$snmp_query_name 		= ((empty($automation_graph_rules['snmp_query_name'])) 	 ? '<em>None</em>' : htmlspecialchars($automation_graph_rules['snmp_query_name']));
			$graph_type_name 		= ((empty($automation_graph_rules['graph_type_name'])) 	 ? '<em>None</em>' : htmlspecialchars($automation_graph_rules['graph_type_name']));

			form_alternate_row_color($colors['alternate'], $colors['light'], $i, 'line' . $automation_graph_rules['id']); $i++;

			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('automation_graph_rules.php?action=edit&id=' . $automation_graph_rules['id'] . "&page=1") . "'>" . ((get_request_var_request('filter') != '') ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($automation_graph_rules['name'])) : htmlspecialchars($automation_graph_rules['name'])) . '</a>', $automation_graph_rules['id']);
			form_selectable_cell($automation_graph_rules['id'], $automation_graph_rules['id'], '', 'text-align:right');
			form_selectable_cell(((get_request_var_request('filter') != '') ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $snmp_query_name) : $snmp_query_name), $automation_graph_rules['id']);
			form_selectable_cell(((get_request_var_request('filter') != '') ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $graph_type_name) : $graph_type_name), $automation_graph_rules['id']);
			form_selectable_cell($automation_graph_rules['enabled'] ? 'Enabled' : 'Disabled', $automation_graph_rules['id'], '', 'text-align:right');
			form_checkbox_cell($automation_graph_rules['name'], $automation_graph_rules['id']);

			form_end_row();
		}

		print $nav;
	}else{
		print "<tr><td><em>No Graph Rules</em></td></tr>\n";
	}

	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($automation_graph_rules_actions);

	form_end();
}

?>
