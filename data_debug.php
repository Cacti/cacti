<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2010-2017 The Cacti Group                                 |
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

include_once('include/auth.php');

$actions = array(
	1 => __('Rerun Check'),
	2 => __('Delete')
);


set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();
		break;
	case 'actions':
		form_actions();
		break;
	case 'edit':
		top_header();
		debug_add();
		bottom_footer();
		break;
	case 'view':
		top_header();
		debug_view();
		bottom_footer();
		break;
	default:
		top_header();
		debug_wizard();
		bottom_footer();
		break;
}

function form_actions() {
	global $actions, $assoc_actions;


	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ================= input validation ================= */



	$selected_items = array();
	if (isset_request_var('save_list')) {
		/* loop through each of the lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$selected_items[] = $matches[1];
			}
		}

	/* if we are to save this form, instead of display it */
		if (isset_request_var('save_list')) {
			if (get_request_var('drp_action') == '2') { /* delete */
				debug_delete($selected_items);
			}elseif (get_request_var('drp_action') == '1') { /* Rerun */
				debug_rerun($selected_items);
			}
			header('Location: data_debug.php?header=false');
			exit;
		}
	}
}


function form_save() {

	if (isset_request_var('save_component')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('local_data_id');

		/* ==================================================== */
		$save = array();
		$save['id']        		= get_nfilter_request_var('id');
		$save['datasource']      = get_nfilter_request_var('local_data_id');

		$info = array(
			'rrd_folder_writable' => '',
			'rrd_exists' => '',
			'rrd_writable' => '',
			'active' => '',
			'owner' => '',
			'runas_poller' => '',
			'runas_website' => get_running_user(),
			'last_result' => '',
			'valid_data' => '',
			'rra_timestamp' => '',
			'rra_timestamp2' => '',
			'rrd_match' => '');
		$save['info'] = serialize($info);
		$save['started'] = time();
		$save['user'] = intval($_SESSION['sess_user_id']);

		if (!is_error_message()) {
			$id = sql_save($save, 'data_debug');
			if ($id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: data_debug.php?header=false');
		exit;
	}
}


function debug_rerun ($selected_items) {

	$info = array(
		'rrd_folder_writable' => '',
		'rrd_exists' => '',
		'rrd_writable' => '',
		'active' => '',
		'owner' => '',
		'runas_poller' => '',
		'runas_website' => get_running_user(),
		'last_result' => '',
		'valid_data' => '',
		'rra_timestamp' => '',
		'rra_timestamp2' => '',
		'rrd_match' => '');
	$info = serialize($info);

	if (!empty($selected_items)) {
		foreach($selected_items as $id) {
			$stime = time();
			db_execute_prepared('UPDATE data_debug SET started = ?, done = 0, info = ?, issue = "" WHERE id = ? LIMIT 1', array($stime, $info, $id));
		}
	}

	header('Location: data_debug.php?header=false');
	exit;
}

function debug_delete ($selected_items) {
	if (!empty($selected_items)) {
		foreach($selected_items as $id) {
			$stime = time();
			db_execute_prepared('DELETE FROM data_debug WHERE id = ? LIMIT 1', array($id));
		}
	}

	header('Location: data_debug.php?header=false');
	exit;
}

function debug_add() {
	global $config;

	$id = 0;
	$header_label = __('New Check');

	form_start('data_debug.php', 'debug');

	html_start_box(htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	$form_array = array(
		'general_header' => array(
			'friendly_name' => __('Debugging Data Source'),
			'method' => 'spacer',
		),
		'local_data_id' => array(
			'friendly_name' => __('Data Source'),
			'method' => 'drop_sql',
			'description' => __('Provide the Maintenance Schedule a meaningful name'),
			'sql' => 'SELECT DISTINCT local_data_id as id, name_cache as name FROM data_template_data WHERE local_data_id > 0 ORDER BY name_cache',
			'value' => ''
		),
		'save_component' => array(
			'method' => 'hidden',
			'value' => '1'
		),
		'save' => array(
			'method' => 'hidden',
			'value'  => 'edit'
		),
		'id' => array(
			'method' => 'hidden',
			'value' => $id
		)
	);

	draw_edit_form(
		array(
			'config' => array(
				'no_form_tag' => true
				),
			'fields' => $form_array
		)
	);
	html_end_box();

	form_save_button('data_debug.php', 'return');


}

function debug_wizard() {
	global $actions, $refresh;
	$refresh = 60;

	$checks = db_fetch_assoc('SELECT * FROM data_debug ORDER BY id');

	form_start('data_debug.php', 'chk');

	html_start_box(__('Data Source Debugger'), '100%', '', '2', 'center', 'data_debug.php?action=edit');

	html_header_checkbox(array(__('ID'), __('User'), __('Started'), __('Data Source'), __('Status'), __('Writable'), __('Exists'), __('Active'), __('RRD Match'), __('Valid Data'), __('RRD Updated'), __('Issue')));

	if (cacti_sizeof($checks)) {
		foreach ($checks as $check) {
			$info = unserialize($check['info']);
			$issues = explode("\n", $check['issue']);
			$issue_line = '';
			if (cacti_sizeof($issues)) {
				$issue_line = $issues[0];
			}
			$issue_title = implode($issues, '<br/>');

			$user = db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array($check['user']), 'username');
			form_alternate_row('line' . $check['id']);
			$name = get_data_source_title($check['datasource']);
			$title = $name;
			if (strlen($name) > 50) {
				$name = substr($name, 0, 50);
			}
			form_selectable_cell('<a class="linkEditMain" title="' . $title .'" href="' . htmlspecialchars('data_debug.php?action=view&id=' . $check['id']) . '">' . $name . '</a>', $check['id']);
			form_selectable_cell($user, $check['id']);
			form_selectable_cell(date('F j, Y, G:i', $check['started']), $check['id']);
			form_selectable_cell($check['datasource'], $check['id']);
			form_selectable_cell(debug_icon(($check['done'] ? (strlen($issue_line) ? 'off' : 'on' ) : '')), $check['id'], '', 'text-align: center;');
			form_selectable_cell(debug_icon($info['rrd_writable']), $check['id'], '', 'text-align: center;');
			form_selectable_cell(debug_icon($info['rrd_exists']), $check['id'], '', 'text-align: center;');
			form_selectable_cell(debug_icon($info['active']), $check['id'], '', 'text-align: center;');
			form_selectable_cell(debug_icon($info['rrd_match']), $check['id'], '', 'text-align: center;');
			form_selectable_cell(debug_icon($info['valid_data']), $check['id'], '', 'text-align: center;');
			form_selectable_cell(debug_icon(($info['rra_timestamp2'] != '' ? 1 : '')), $check['id'], '', 'text-align: center;');
			form_selectable_cell('<a class=\'linkEditMain\' href=\'#\' title="' . html_escape($issue_title) . '">' . html_escape(strlen(trim($issue_line)) ? $issue_line : '<none>') . '</a>', $check['id']);
			form_checkbox_cell($check['id'], $check['id']);
			form_end_row();
		}
	}else{
		print "<tr><td colspan='5'><em>" . __('No Checks') . "</em></td></tr>\n";
	}

	html_end_box(false);

	form_hidden_box('save_list', '1', '');

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();
}

function debug_view() {
	global $config, $refresh;
	$refresh = 60;

	get_filter_request_var('id');
	$id = get_nfilter_request_var('id');

	$check = db_fetch_row_prepared('SELECT * FROM data_debug WHERE id = ?', array($id));
	if (isset($check) && is_array($check)) {
		$check['info'] = unserialize($check['info']);
	}
	$dtd = db_fetch_row_prepared('SELECT * from data_template_data WHERE local_data_id = ?', array($check['datasource']));
	$real_pth = str_replace('<path_rra>', $config['rra_path'], $dtd['data_source_path']);

	$poller_data = array();
	if (!empty($check['info']['last_result'])) {
		foreach ($check['info']['last_result'] as $a => $l) {
			$poller_data[] = "$a: $l";
		}
	}
	$poller_data = implode('<br>', $poller_data);

	$rra_updated = '';
	if (isset($check['info']['rra_timestamp2'])) {
		$rra_updated = $check['info']['rra_timestamp2'] != '' ? 1 : '';
	}

	$issue = '';
	if (isset($check['issue'])) {
		$issue = $check['issue'];
	}

	$fields = array(
		array('name' => 'owner', 'title' => __('RRD Owner'), 'icon' => '-'),
		array('name' => 'runas_website', 'title' => __('Website runs as')),
		array('name' => 'runas_poller', 'title' => __('Poller runs as')),
		array('name' => 'rrd_folder_writable', 'title' => __('Is RRA Folder writeable by poller?'), 'value' => dirname($real_pth)),
		array('name' => 'rrd_writable', 'title' => __('Is RRD writeable by poller?'), 'value' => $real_pth),
		array('name' => 'rrd_exists', 'title' => __('Does the RRD Exist?')),
		array('name' => 'active', 'title' => __('Is the Data Source set as Active?')),
		array('name' => 'last_result', 'title' => __('Did the poller receive valid data?'), 'value' => $poller_data),
		array('name' => 'rra_updated', 'title' => __('Was the RRD File updated?'), 'value' => '', 'icon' => $rra_updated),
		array('name' => 'rra_timestamp', 'title' => __('First Check TimeStamp'), 'icon' => '-'),
		array('name' => 'rra_timestamp2', 'title' => __('Second Check TimeStamp'), 'icon' => '-'),
		array('name' => 'convert_name', 'title' => __('Were we able to convert the title?'), 'value' => get_data_source_title($check['datasource'])),
		array('name' => 'rrd_match', 'title' => __('Does the RRA Profile match the RRD File structure?'), 'value' => ''),
		array('name' => 'issue', 'title' => __('Issue'), 'value' => $issue, 'icon' => '-'),
	);

	html_start_box(__('Data Source Debugger'), '', '', '2', 'center', '');
	html_header(array(__('Check'), __('Value'), __('Results')));

	$i = 1;
	foreach ($fields as $field) {
		$field_name = $field['name'];

		form_alternate_row('line' . $i);
		form_selectable_cell($field['title'], $i);

		$value = '<not set>';
		$icon  = '';

		if (array_key_exists($field_name, $check['info'])) {
			$value = $check['info'][$field_name];
			$icon  = debug_icon($check['info'][$field_name]);
		}

		if (array_key_exists('value', $field)) {
			$value = $field['value'];
		}

		if (array_key_exists('icon', $field)) {
			$icon = $field['icon'];
		}

		$value_title = $value;
		if (strlen($value) > 100) {
			$value = substr($value, 0, 100);
		}

		form_selectable_cell($value, $i, '', '', $value_title);
		form_selectable_cell($icon, $i);

		form_end_row();
		$i++;
	}


	html_end_box(false);

/*
	print "<pre>";
	if (isset($check) && is_array($check)) {
		print_r($check);
	}
	print "</pre>";
*/

}

function debug_icon($result) {
	if ($result === '' || $result === false) {
			return '<i class="fa fa-spinner fa-pulse fa-fw"></i>';
	}
	if ($result === '-') {
			return '<i class="fa fa-info-circle"></i>';
	}
	if ($result === 1 || $result === 'on') {
			return '<i class="fa fa-check" style="color:green"></i>';
	}
	if ($result === 0 || $result === 'off') {
			return '<i class="fa fa-times" style="color:red"></i>';
	}
	return '<i class="fa fa-warn-triagle" style="color:orange"></i>';
}
