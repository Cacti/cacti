<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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

include ('./include/auth.php');
include_once('./lib/tree.php');
include_once('./lib/html_tree.php');
include_once('./lib/utility.php');
include_once('./lib/template.php');

define('MAX_DISPLAY_PAGES', 21);

$ds_actions = array(
	1 => 'Delete',
	2 => 'Duplicate'
	);

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'rrd_add':
		template_rrd_add();

		break;
	case 'rrd_remove':
		template_rrd_remove();

		break;
	case 'template_remove':
		template_remove();

		header('Location: data_templates.php');
		break;
	case 'template_edit':
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

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST['save_component_template'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('data_input_id'));
		input_validate_input_number(get_request_var_post('data_template_id'));
		/* ==================================================== */

		/* save: data_template */
		$save1['id'] = $_POST['data_template_id'];
		$save1['hash'] = get_hash_data_template($_POST['data_template_id']);
		$save1['name'] = form_input_validate($_POST['template_name'], 'template_name', '', false, 3);

		/* save: data_template_data */
		$save2['id'] = $_POST['data_template_data_id'];
		$save2['local_data_template_data_id'] = 0;
		$save2['local_data_id'] = 0;

		$save2['data_input_id'] = form_input_validate($_POST['data_input_id'], 'data_input_id', '', true, 3);
		$save2['t_name'] = form_input_validate((isset($_POST['t_name']) ? $_POST['t_name'] : ''), 't_name', '', true, 3);
		$save2['name'] = form_input_validate($_POST['name'], 'name', '', (isset($_POST['t_name']) ? true : false), 3);
		$save2['t_active'] = form_input_validate((isset($_POST['t_active']) ? $_POST['t_active'] : ''), 't_active', '', true, 3);
		$save2['active'] = form_input_validate((isset($_POST['active']) ? $_POST['active'] : ''), 'active', '', true, 3);
		$save2['t_rrd_step'] = form_input_validate((isset($_POST['t_rrd_step']) ? $_POST['t_rrd_step'] : ''), 't_rrd_step', '', true, 3);
		$save2['rrd_step'] = form_input_validate($_POST['rrd_step'], 'rrd_step', '^[0-9]+$', (isset($_POST['t_rrd_step']) ? true : false), 3);
		$save2['t_rra_id'] = form_input_validate((isset($_POST['t_rra_id']) ? $_POST['t_rra_id'] : ''), 't_rra_id', '', true, 3);

		/* save: data_template_rrd */
		$save3['id'] = $_POST['data_template_rrd_id'];
		$save3['hash'] = get_hash_data_template($_POST['data_template_rrd_id'], 'data_template_item');
		$save3['local_data_template_rrd_id'] = 0;
		$save3['local_data_id'] = 0;

		$save3['t_rrd_maximum'] = form_input_validate((isset($_POST['t_rrd_maximum']) ? $_POST['t_rrd_maximum'] : ''), 't_rrd_maximum', '', true, 3);
		$save3['rrd_maximum'] = form_input_validate($_POST['rrd_maximum'], 'rrd_maximum', '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$', (isset($_POST['t_rrd_maximum']) ? true : false), 3);
		$save3['t_rrd_minimum'] = form_input_validate((isset($_POST['t_rrd_minimum']) ? $_POST['t_rrd_minimum'] : ''), 't_rrd_minimum', '', true, 3);
		$save3['rrd_minimum'] = form_input_validate($_POST['rrd_minimum'], 'rrd_minimum', '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$', (isset($_POST['t_rrd_minimum']) ? true : false), 3);
		$save3['t_rrd_heartbeat'] = form_input_validate((isset($_POST['t_rrd_heartbeat']) ? $_POST['t_rrd_heartbeat'] : ''), 't_rrd_heartbeat', '', true, 3);
		$save3['rrd_heartbeat'] = form_input_validate($_POST['rrd_heartbeat'], 'rrd_heartbeat', '^[0-9]+$', (isset($_POST['t_rrd_heartbeat']) ? true : false), 3);
		$save3['t_data_source_type_id'] = form_input_validate((isset($_POST['t_data_source_type_id']) ? $_POST['t_data_source_type_id'] : ''), 't_data_source_type_id', '', true, 3);
		$save3['data_source_type_id'] = form_input_validate($_POST['data_source_type_id'], 'data_source_type_id', '', true, 3);
		$save3['t_data_source_name'] = form_input_validate((isset($_POST['t_data_source_name']) ? $_POST['t_data_source_name'] : ''), 't_data_source_name', '', true, 3);
		$save3['data_source_name'] = form_input_validate($_POST['data_source_name'], 'data_source_name', '^[a-zA-Z0-9_]{1,19}$', (isset($_POST['t_data_source_name']) ? true : false), 3);
		$save3['t_data_input_field_id'] = form_input_validate((isset($_POST['t_data_input_field_id']) ? $_POST['t_data_input_field_id'] : ''), 't_data_input_field_id', '', true, 3);
		$save3['data_input_field_id'] = form_input_validate((isset($_POST['data_input_field_id']) ? $_POST['data_input_field_id'] : '0'), 'data_input_field_id', '', true, 3);

		/* ok, first pull out all 'input' values so we know how much to save */
		$input_fields = db_fetch_assoc_prepared("SELECT
			id,
			input_output,
			regexp_match,
			allow_nulls,
			type_code,
			data_name
			FROM data_input_fields
			WHERE data_input_id = ?
			AND input_output = 'in'", array($_POST['data_input_id']));

		/* pass 1 for validation */
		if (sizeof($input_fields) > 0) {
			foreach ($input_fields as $input_field) {
				$form_value = 'value_' . $input_field['data_name'];

				if ((isset($_POST[$form_value])) && ($input_field['type_code'] == '')) {
					if ((isset($_POST['t_' . $form_value])) &&
						($_POST['t_' . $form_value] == 'on')) {
						$not_required = true;
					}else if ($input_field['allow_nulls'] == 'on') {
						$not_required = true;
					}else{
						$not_required = false;
					}

					form_input_validate($_POST[$form_value], 'value_' . $input_field['data_name'], $input_field['regexp_match'], $not_required, 3);
				}
			}
		}

		if (!is_error_message()) {
			$data_template_id = sql_save($save1, 'data_template');

			if ($data_template_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (!is_error_message()) {
			$save2['data_template_id'] = $data_template_id;
			$data_template_data_id = sql_save($save2, 'data_template_data');

			if ($data_template_data_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		/* update actual host template information for live hosts */
		if ((!is_error_message()) && ($save2['id'] > 0)) {
			db_execute_prepared('UPDATE data_template_data set data_input_id = ? WHERE data_template_id = ?', array($_POST['data_input_id'], $_POST['data_template_id']));
		}

		if (!is_error_message()) {
			$save3['data_template_id'] = $data_template_id;
			$data_template_rrd_id = sql_save($save3, 'data_template_rrd');

			if ($data_template_rrd_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (!is_error_message()) {
			/* save entries in 'selected rras' field */
			db_execute_prepared('DELETE FROM data_template_data_rra WHERE data_template_data_id = ?', array($data_template_data_id));

			if (isset($_POST['rra_id'])) {
				for ($i=0; ($i < count($_POST['rra_id'])); $i++) {
					/* ================= input validation ================= */
					input_validate_input_number($_POST['rra_id'][$i]);
					/* ==================================================== */

					db_execute_prepared('INSERT INTO data_template_data_rra (rra_id, data_template_data_id)
						VALUES (?, ?)', array($_POST['rra_id'][$i], $data_template_data_id));
				}
			}

			if (!empty($_POST['data_template_id'])) {
				/* push out all data source settings to child data source using this template */
				push_out_data_source($data_template_data_id);
				push_out_data_source_item($data_template_rrd_id);

				db_execute_prepared('DELETE FROM data_input_data WHERE data_template_data_id = ?', array($data_template_data_id));

				reset($input_fields);
				if (sizeof($input_fields) > 0) {
				foreach ($input_fields as $input_field) {
					$form_value = 'value_' . $input_field['data_name'];

					if (isset($_POST[$form_value])) {
						/* save the data into the 'host_template_data' table */
						if (isset($_POST{'t_value_' . $input_field['data_name']})) {
							$template_this_item = 'on';
						}else{
							$template_this_item = '';
						}

						if ((!empty($form_value)) || (!empty($_POST{'t_value_' . $input_field['data_name']}))) {
							db_execute_prepared('INSERT INTO data_input_data (data_input_field_id, data_template_data_id, t_value, value)
								values (?, ?, ?, ?)', array($input_field['id'], $data_template_data_id, $template_this_item, trim($_POST[$form_value]) ));
						}
					}
				}
				}

				/* push out all "custom data" for this data source template */
				push_out_data_source_custom_data($data_template_id);
				push_out_host(0, 0, $data_template_id);
			}
		}

		header('Location: data_templates.php?action=template_edit&id=' . (empty($data_template_id) ? $_POST['data_template_id'] : $data_template_id) . (empty($_POST['current_rrd']) ? '' : '&view_rrd=' . ($_POST['current_rrd'] ? $_POST['current_rrd'] : $data_template_rrd_id)));
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $ds_actions;

	/* ================= input validation ================= */
	input_validate_input_regex(get_request_var_post('drp_action'), '^([a-zA-Z0-9_]+)$');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = unserialize(stripslashes($_POST['selected_items']));

		if ($_POST['drp_action'] == '1') { /* delete */
			$data_template_datas = db_fetch_assoc('SELECT id FROM data_template_data WHERE ' . array_to_sql_or($selected_items, 'data_template_id') . ' AND local_data_id=0');

			if (sizeof($data_template_datas) > 0) {
			foreach ($data_template_datas as $data_template_data) {
				db_execute_prepared('DELETE FROM data_template_data_rra WHERE data_template_data_id = ?', array($data_template_data['id']));
			}
			}

			db_execute('DELETE FROM data_template_data WHERE ' . array_to_sql_or($selected_items, 'data_template_id') . ' AND local_data_id=0');
			db_execute('DELETE FROM data_template_rrd WHERE ' . array_to_sql_or($selected_items, 'data_template_id') . ' AND local_data_id=0');
			db_execute('DELETE FROM snmp_query_graph_rrd WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));
			db_execute('DELETE FROM snmp_query_graph_rrd_sv WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));
			db_execute('DELETE FROM data_template WHERE ' . array_to_sql_or($selected_items, 'id'));

			/* "undo" any graph that is currently using this template */
			db_execute('UPDATE data_template_data set local_data_template_data_id=0,data_template_id=0 WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));
			db_execute('UPDATE data_template_rrd set local_data_template_rrd_id=0,data_template_id=0 WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));
			db_execute('UPDATE data_local set data_template_id=0 WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));
		}elseif ($_POST['drp_action'] == '2') { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				duplicate_data_source(0, $selected_items[$i], $_POST['title_format']);
			}
		}

		header('Location: data_templates.php');
		exit;
	}

	/* setup some variables */
	$ds_list = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$ds_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM data_template WHERE id = ?', array($matches[1]))) . '<br>';
			$ds_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	html_start_box('<strong>' . $ds_actions{$_POST['drp_action']} . '</strong>', '60%', '', '3', 'center', '');

	print "<form action='data_templates.php' method='post'>\n";

	if (isset($ds_array) && sizeof($ds_array)) {
		if ($_POST['drp_action'] == '1') { /* delete */
			print "	<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the following Data Template(s) will be deleted.  Any data sources attached
						to these templates will become individual Data Source(s) and all Templating benefits will be removed.</p>
						<p><ul>$ds_list</ul></p>
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Data Template(s)'>";
		}elseif ($_POST['drp_action'] == '2') { /* duplicate */
			print "	<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the following Data Template(s) will be duplicated. You can
						optionally change the title format for the new Data Template(s).</p>
						<p><ul>$ds_list</ul></p>
						<p><strong>Title Format:</strong><br>"; form_text_box('title_format', '<template_title> (1)', '', '255', '30', 'text'); print "</p>
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Duplicate Data Template(s)'>";
		}
	}else{
		print "<tr><td class='even'><span class='textError'>You must select at least one data template.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($ds_array) ? serialize($ds_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	bottom_footer();
}

/* ----------------------------
    template - Data Templates
   ---------------------------- */

function template_rrd_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('data_template_id'));
	/* ==================================================== */

	$children = db_fetch_assoc_prepared('SELECT id FROM data_template_rrd WHERE local_data_template_rrd_id = ? OR id = ?', array($_GET['id'], $_GET['id']));

	if (sizeof($children) > 0) {
	foreach ($children as $item) {
		db_execute_prepared('DELETE FROM data_template_rrd WHERE id = ?', array($item['id']));
		db_execute_prepared('DELETE FROM snmp_query_graph_rrd WHERE data_template_rrd_id = ?', array($item['id']));
		db_execute_prepared('UPDATE graph_templates_item SET task_item_id = 0 WHERE task_item_id = ?', array($item['id']));
	}
	}

	header('Location: data_templates.php?action=template_edit&id=' . $_GET['data_template_id']);
}

function template_rrd_add() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('local_data_id'));
	/* ==================================================== */

	$hash = get_hash_data_template(0, 'data_template_item');

	db_execute_prepared("INSERT INTO data_template_rrd (hash, data_template_id, rrd_maximum, rrd_minimum, rrd_heartbeat, data_source_type_id, data_source_name) 
		    VALUES (?, ?, 0, 0, 600, 1, 'ds')", array($hash, $_GET['id']));
	$data_template_rrd_id = db_fetch_insert_id();

	/* add this data template item to each data source using this data template */
	$children = db_fetch_assoc_prepared('SELECT local_data_id FROM data_template_data WHERE data_template_id = ? AND local_data_id > 0', array($_GET['id']));

	if (sizeof($children) > 0) {
	foreach ($children as $item) {
		db_execute_prepared("INSERT INTO data_template_rrd (local_data_template_rrd_id, local_data_id, data_template_id, rrd_maximum, rrd_minimum, rrd_heartbeat, data_source_type_id, data_source_name) 
				     VALUES (?, ?, ?, 0, 0, 600, 1, 'ds')", array($data_template_rrd_id, $item['local_data_id'], $_GET['id']));
	}
	}

	header('Location: data_templates.php?action=template_edit&id=' . $_GET['id'] . "&view_rrd=$data_template_rrd_id");
}

function template_edit() {
	global $struct_data_source, $struct_data_source_item, $data_source_types, $fields_data_template_template_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('view_rrd'));
	/* ==================================================== */

	if (!empty($_GET['id'])) {
		$template_data = db_fetch_row_prepared('SELECT * FROM data_template_data WHERE data_template_id = ? AND local_data_id = 0', array($_GET['id']));
		$template = db_fetch_row_prepared('SELECT * FROM data_template WHERE id = ?', array($_GET['id']));

		$header_label = '[edit: ' . $template['name'] . ']';
	}else{
		$header_label = '[new]';
	}

	html_start_box('<strong>Data Templates</strong> ' . htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array(),
		'fields' => inject_form_variables($fields_data_template_template_edit, (isset($template) ? $template : array()), (isset($template_data) ? $template_data : array()), $_GET)
		));

	html_end_box();

	html_start_box('<strong>Data Source</strong>', '100%', '', '3', 'center', '');

	/* make sure 'data source path' doesn't show up for a template... we should NEVER template this field */
	unset($struct_data_source['data_source_path']);

	$form_array = array();

	while (list($field_name, $field_array) = each($struct_data_source)) {
		$form_array += array($field_name => $struct_data_source[$field_name]);

		if ($field_array['flags'] == 'ALWAYSTEMPLATE') {
			$form_array[$field_name]['description'] = '<em>This field is always templated.</em>';
		}else{
			$form_array[$field_name]['description'] = '';
			$form_array[$field_name]['sub_checkbox'] = array(
				'name' => 't_' . $field_name,
				'friendly_name' => 'Use Per-Data Source Value (Ignore this Value)',
				'value' => (isset($template_data{'t_' . $field_name}) ? $template_data{'t_' . $field_name} : '')
				);
		}

		$form_array[$field_name]['value'] = (isset($template_data[$field_name]) ? $template_data[$field_name] : '');
		$form_array[$field_name]['form_id'] = (isset($template_data) ? $template_data['data_template_id'] : '0');
	}

	draw_edit_form(
		array(
			'config' => array(
				'no_form_tag' => true
				),
			'fields' => inject_form_variables($form_array, (isset($template_data) ? $template_data : array()))
			)
		);

	html_end_box();

	/* fetch ALL rrd's for this data source */
	if (!empty($_GET['id'])) {
		$template_data_rrds = db_fetch_assoc_prepared('SELECT id, data_source_name FROM data_template_rrd WHERE data_template_id = ? AND local_data_id = 0 ORDER BY data_source_name', array($_GET['id']));
	}

	/* select the first "rrd" of this data source by default */
	if (empty($_GET['view_rrd'])) {
		$_GET['view_rrd'] = (isset($template_data_rrds[0]['id']) ? $template_data_rrds[0]['id'] : '0');
	}

	/* get more information about the rrd we chose */
	if (!empty($_GET['view_rrd'])) {
		$template_rrd = db_fetch_row_prepared('SELECT * FROM data_template_rrd WHERE id = ?', array($_GET['view_rrd']));
	}

	$i = 0;
	if (isset($template_data_rrds)) {
		if (sizeof($template_data_rrds) > 1) {

		/* draw the data source tabs on the top of the page */
		print "	<div class='tabs' style='float:left;'><nav><ul>\n";

		foreach ($template_data_rrds as $template_data_rrd) {
			$i++;
			print "	<li>
				<a " . (($template_data_rrd['id'] == $_GET['view_rrd']) ? "class='selected'" : "class=''") . " href='" . htmlspecialchars('data_templates.php?action=template_edit&id=' . $_GET['id'] . '&view_rrd=' . $template_data_rrd['id']) . "'>$i: " . htmlspecialchars($template_data_rrd['data_source_name']) . "</a>
				<span><a class='deleteMarker' href='" . htmlspecialchars('data_templates.php?action=rrd_remove&id=' . $template_data_rrd['id'] . '&data_template_id=' . $_GET['id']) . "'><img src='images/delete_icon.gif' border='0' alt='Delete'></a></span></li>\n";
		}

		print "
		</ul></nav>\n
		</div>\n";

		}elseif (sizeof($template_data_rrds) == 1) {
			$_GET['view_rrd'] = $template_data_rrds[0]['id'];
		}
	}

	html_start_box('<strong>Data Source Item</strong> [' . (isset($template_rrd) ? htmlspecialchars($template_rrd['data_source_name']) : '') . ']', '100%', '', '3', 'center', (!empty($_GET['id']) ? htmlspecialchars('data_templates.php?action=rrd_add&id=' . $_GET['id']):''), '<strong>New</scrong>');

	/* data input fields list */
	if ((empty($template_data['data_input_id'])) ||
		((db_fetch_cell('SELECT type_id FROM data_input WHERE id=' . $template_data['data_input_id']) != '1') &&
		(db_fetch_cell('SELECT type_id FROM data_input WHERE id=' . $template_data['data_input_id']) != '5'))) {
		unset($struct_data_source_item['data_input_field_id']);
	}else{
		$struct_data_source_item['data_input_field_id']['sql'] = "SELECT id,CONCAT(data_name,' - ',name) AS name FROM data_input_fields WHERE data_input_id=" . $template_data['data_input_id'] . " AND input_output='out' AND update_rra='on' ORDER BY data_name,name";
	}

	$form_array = array();

	while (list($field_name, $field_array) = each($struct_data_source_item)) {
		$form_array += array($field_name => $struct_data_source_item[$field_name]);

		$form_array[$field_name]['description'] = '';
		$form_array[$field_name]['value'] = (isset($template_rrd) ? $template_rrd[$field_name] : '');
		$form_array[$field_name]['sub_checkbox'] = array(
			'name' => 't_' . $field_name,
			'friendly_name' => 'Use Per-Data Source Value (Ignore this Value)',
			'value' => (isset($template_rrd) ? $template_rrd{'t_' . $field_name} : '')
			);
	}

	draw_edit_form(
		array(
			'config' => array(
				'no_form_tag' => true
				),
			'fields' => $form_array + array(
				'data_template_rrd_id' => array(
					'method' => 'hidden',
					'value' => (isset($template_rrd) ? $template_rrd['id'] : '0')
				)
			)
			)
		);

	html_end_box();

	$i = 0;
	if (!empty($_GET['id'])) {
		/* get each INPUT field for this data input source */
		$fields = db_fetch_assoc('SELECT * FROM data_input_fields WHERE data_input_id=' . $template_data['data_input_id'] . " AND input_output='in' ORDER BY name");

		html_start_box('<strong>Custom Data</strong> [data input: ' . htmlspecialchars(db_fetch_cell('SELECT name FROM data_input WHERE id=' . $template_data['data_input_id'])) . ']', '100%', '', '3', 'center', '');

		/* loop through each field found */
		if (sizeof($fields) > 0) {
		foreach ($fields as $field) {
			$data_input_data = db_fetch_row('SELECT t_value,value FROM data_input_data WHERE data_template_data_id=' . $template_data['id'] . ' AND data_input_field_id=' . $field['id']);

			if (sizeof($data_input_data) > 0) {
				$old_value = $data_input_data['value'];
			}else{
				$old_value = '';
			}

			form_alternate_row();?>
				<td width="50%">
					<strong><?php print $field['name'];?></strong><br>
					<?php form_checkbox('t_value_' . $field['data_name'], $data_input_data['t_value'], 'Use Per-Data Source Value (Ignore this Value)', '', '', $_GET['id']);?>
				</td>
				<td>
					<?php form_text_box('value_' . $field['data_name'],$old_value,'','');?>
					<?php if ((preg_match('/^' . VALID_HOST_FIELDS . '$/i', $field['type_code'])) && ($data_input_data['t_value'] == '')) { print "<br><em>Value will be derived from the host if this field is left empty.</em>\n"; } ?>
				</td>
			</tr>
			<?php

			$i++;
		}
		}else{
			print '<tr><td><em>No Input Fields for the Selected Data Input Source</em></td></tr>';
		}

		html_end_box();
	}

	form_save_button('data_templates.php', 'return');
}

function template() {
	global $ds_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* clean up has_data string */
	if (isset($_REQUEST['has_data'])) {
		$_REQUEST['has_data'] = sanitize_search_string(get_request_var('has_data'));
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
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_data_template_current_page');
		kill_session_var('sess_data_template_has_data');
		kill_session_var('sess_data_template_filter');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_data_template_sort_column');
		kill_session_var('sess_data_template_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['has_data']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('has_data', 'sess_data_template_has_data');
		$changed += check_changed('rows', 'sess_default_rows');
		$changed += check_changed('filter', 'sess_data_template_filter');

		if ($changed) {
			$_REQUEST['page'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_data_template_current_page', '1');
	load_current_session_value('has_data', 'sess_data_template_has_data', 'true');
	load_current_session_value('filter', 'sess_data_template_filter', '');
	load_current_session_value('sort_column', 'sess_data_template_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_data_template_sort_direction', 'ASC');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	html_start_box('<strong>Data Templates</strong>', '100%', '', '3', 'center', 'data_templates.php?action=template_edit');

	?>
	<tr class='even noprint'>
		<td>
		<form id="form_data_template" action="data_templates.php">
			<table cellpadding="2" cellspacing="0">
				<tr>
					<td width="50">
						Search:
					</td>
					<td>
						<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>">
					</td>
					<td style='white-space: nowrap;'>
						Data Templates:
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type="checkbox" id='has_data' <?php print ($_REQUEST['has_data'] == 'true' ? 'checked':'');?>>
					</td>
					<td>
						<label for='has_data' style='white-space:nowrap;'>Has Data Sources</label>
					</td>
					<td>
						<input type="button" id='refresh' value="Go" title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" id='clear' name="clear_x" value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
		</form>
		</td>
		<script type='text/javascript'>
		function applyFilter() {
			strURL = 'data_templates.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&has_data='+$('#has_data').is(':checked')+'&header=false';
			$.get(strURL, function(data) {
				$('#main').html(data);
				applySkin();
			});
		}

		function clearFilter() {
			strURL = 'data_templates.php?clear_x=1&header=false';
			$.get(strURL, function(data) {
				$('#main').html(data);
				applySkin();
			});
		}

		$(function() {
			$('#has_data').click(function() {
				applyFilter();
			});

			$('#refresh').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});
	
			$('#form_data_template').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	$rows_where = '';
	if (strlen($_REQUEST['filter'])) {
		$sql_where = " WHERE (dt.name like '%" . get_request_var_request('filter') . "%')";
	}else{
		$sql_where = '';
	}

	if ($_REQUEST['has_data'] == 'true') {
		$sql_having = 'HAVING data_sources>0';
	}else{
		$sql_having = '';
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='data_templates.php'>\n";

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT COUNT(rows)
		FROM (SELECT
			COUNT(dt.id) rows,
			SUM(CASE WHEN dtd.local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
			FROM data_template AS dt
			INNER JOIN data_template_data AS dtd
			ON dt.id=dtd.data_template_id
			LEFT JOIN data_input AS di
			ON dtd.data_input_id=di.id
			$sql_where
			GROUP BY dt.id
			$sql_having
		) AS rs");

	$template_list = db_fetch_assoc("SELECT dt.id, dt.name, 
		di.name AS data_input_method, dtd.active AS active,
		SUM(CASE WHEN dtd.local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
		FROM data_template AS dt
		INNER JOIN data_template_data AS dtd
		ON dt.id=dtd.data_template_id
		LEFT JOIN data_input AS di
		ON dtd.data_input_id=di.id
		$sql_where
		GROUP BY dt.id
		$sql_having
		ORDER BY " . get_request_var_request('sort_column') . ' ' . get_request_var_request('sort_direction') .
		' LIMIT ' . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows'));

	$nav = html_nav_bar('data_templates.php?filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 6, 'Data Templates', 'page', 'main');

	print $nav;

	$display_text = array(
		'name' => array('Template Name', 'ASC'),
		'data_sources' => array('display' => 'Data Sources', 'align' => 'right', 'sort' => 'ASC'),
		'data_input_method' => array('Data Input Method', 'ASC'),
		'active' => array('Status', 'ASC'),
		'id' => array('display' => 'ID', 'align' => 'right', 'sort' => 'ASC')
	);

	html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

	if (sizeof($template_list) > 0) {
		foreach ($template_list as $template) {
			if ($template['data_sources'] > 0) {
				$disabled = true;
			}else{
				$disabled = false;
			}
			form_alternate_row('line' . $template['id'], true, $disabled);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('data_templates.php?action=template_edit&id=' . $template['id']) . "'>" . (strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($template['name'])) : htmlspecialchars($template['name'])) . '</a>', $template['id']);
			form_selectable_cell($template['data_sources'], $template['id'], '', 'text-align:right');
			form_selectable_cell((empty($template['data_input_method']) ? '<em>None</em>': htmlspecialchars($template['data_input_method'])), $template['id']);
			form_selectable_cell((($template['active'] == 'on') ? 'Active' : 'Disabled'), $template['id']);
			form_selectable_cell($template['id'], $template['id'], '', 'text-align:right');
			form_checkbox_cell($template['name'], $template['id'], $disabled);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr class='tableRow'><td colspan='6'><em>No Data Templates</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($ds_actions);

	print "</form>\n";
}

