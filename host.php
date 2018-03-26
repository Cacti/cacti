<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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
include_once('./lib/utility.php');
include_once('./lib/api_data_source.php');
include_once('./lib/api_tree.php');
include_once('./lib/html_tree.php');
include_once('./lib/api_graph.php');
include_once('./lib/snmp.php');
include_once('./lib/ping.php');
include_once('./lib/data_query.php');
include_once('./lib/api_automation.php');
include_once('./lib/api_device.php');

$device_actions = array(
	1 => __('Delete'),
	2 => __('Enable'),
	3 => __('Disable'),
	4 => __('Change Device Settings'),
	5 => __('Clear Statistics'),
	6 => __('Apply Automation Rules'),
	7 => __('Sync to Device Template')
);

$device_actions = api_plugin_hook_function('device_action_array', $device_actions);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'export':
		host_export();

		break;
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'gt_add':
		get_filter_request_var('host_id');

		host_add_gt();

		header('Location: host.php?header=false&action=edit&id=' . get_request_var('host_id'));
		break;
	case 'gt_remove':
		get_filter_request_var('host_id');

		host_remove_gt();

		header('Location: host.php?header=false&action=edit&id=' . get_request_var('host_id'));
		break;
	case 'query_add':
		get_filter_request_var('host_id');

		host_add_query();

		header('Location: host.php?header=false&action=edit&id=' . get_request_var('host_id'));
		break;
	case 'query_remove':
		get_filter_request_var('host_id');

		host_remove_query();

		header('Location: host.php?header=false&action=edit&id=' . get_request_var('host_id'));
		break;
	case 'query_change':
		get_filter_request_var('host_id');

		host_change_query();

		header('Location: host.php?header=false&action=edit&id=' . get_request_var('host_id'));
		break;
	case 'query_reload':
		get_filter_request_var('host_id');

		host_reload_query();

		header('Location: host.php?header=false&action=edit&id=' . get_request_var('host_id'));
		break;
	case 'query_verbose':
		get_filter_request_var('host_id');

		host_reload_query();

		header('Location: host.php?header=' . (isset_request_var('header') && get_nfilter_request_var('header') == 'true' ? 'true':'false') . '&action=edit&id=' . get_request_var('host_id') . '&display_dq_details=true');
		break;
	case 'edit':
		top_header();

		host_edit();

		bottom_footer();
		break;
	case 'ping_host':
		$host_id = get_filter_request_var('id');
		api_device_ping_device($host_id);
		break;
	case 'enable_debug':
		enable_device_debug(get_filter_request_var('host_id'));
		header('Location: host.php?header=false&action=edit&id=' . get_request_var('host_id'));
		break;
	case 'disable_debug':
		disable_device_debug(get_filter_request_var('host_id'));
		header('Location: host.php?header=false&action=edit&id=' . get_request_var('host_id'));
		break;
	default:
		top_header();

		host();

		bottom_footer();
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function add_tree_names_to_actions_array() {
	global $device_actions;

	/* add a list of tree names to the actions dropdown */
	$trees = db_fetch_assoc('SELECT id, name FROM graph_tree ORDER BY name');

	if (sizeof($trees)) {
		foreach ($trees as $tree) {
			$device_actions['tr_' . $tree['id']] = 'Place on a Tree (' . $tree['name'] . ')';
		}
	}
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset_request_var('save_component_host')) {
		if (get_nfilter_request_var('snmp_version') == 3 && (get_nfilter_request_var('snmp_password') != get_nfilter_request_var('snmp_password_confirm'))) {
			raise_message(14);
		} else if (get_nfilter_request_var('snmp_version') == 3 && (get_nfilter_request_var('snmp_priv_passphrase') != get_nfilter_request_var('snmp_priv_passphrase_confirm'))) {
			raise_message(13);
		} else {
			get_filter_request_var('id');
			get_filter_request_var('host_template_id');

			$host_id = api_device_save(get_nfilter_request_var('id'), get_nfilter_request_var('host_template_id'), get_nfilter_request_var('description'),
				trim(get_nfilter_request_var('hostname')), get_nfilter_request_var('snmp_community'), get_nfilter_request_var('snmp_version'),
				get_nfilter_request_var('snmp_username'), get_nfilter_request_var('snmp_password'),
				get_nfilter_request_var('snmp_port'), get_nfilter_request_var('snmp_timeout'),
				(isset_request_var('disabled') ? get_nfilter_request_var('disabled') : ''),
				get_nfilter_request_var('availability_method'), get_nfilter_request_var('ping_method'),
				get_nfilter_request_var('ping_port'), get_nfilter_request_var('ping_timeout'),
				get_nfilter_request_var('ping_retries'), get_nfilter_request_var('notes'),
				get_nfilter_request_var('snmp_auth_protocol'), get_nfilter_request_var('snmp_priv_passphrase'),
				get_nfilter_request_var('snmp_priv_protocol'), get_nfilter_request_var('snmp_context'),
				get_nfilter_request_var('snmp_engine_id'), get_nfilter_request_var('max_oids'),
				get_nfilter_request_var('device_threads'), get_nfilter_request_var('poller_id'),
				get_nfilter_request_var('site_id'), get_nfilter_request_var('external_id'));

			if ($host_id !== false) {
				api_plugin_hook_function('host_save', array('host_id' => $host_id));
			}
		}

		header('Location: host.php?header=false&action=edit&id=' . (empty($host_id) ? get_nfilter_request_var('id') : $host_id));
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $device_actions, $fields_host_edit;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_request_var('drp_action') == '2') { // Enable Selected Devices
				foreach ($selected_items as $selected_item) {
					db_execute_prepared("UPDATE host SET disabled = '' WHERE id = ?", array($selected_item));

					/* update poller cache */
					$data_sources = db_fetch_assoc_prepared('SELECT id FROM data_local WHERE host_id = ?', array($selected_item));
					$poller_items = $local_data_ids = array();

					if (sizeof($data_sources)) {
						foreach ($data_sources as $data_source) {
							$local_data_ids[] = $data_source['id'];
							$poller_items     = array_merge($poller_items, update_poller_cache($data_source['id']));
						}
					}

					if (sizeof($local_data_ids)) {
						poller_update_poller_cache_from_buffer($local_data_ids, $poller_items);
					}
				}
			} elseif (get_request_var('drp_action') == '3') { // Disable Selected Devices
				foreach ($selected_items as $selected_item) {
					db_execute_prepared("UPDATE host SET disabled='on' WHERE id = ?", array($selected_item));

					/* update poller cache */
					db_execute_prepared('DELETE FROM poller_item WHERE host_id = ?', array($selected_item));
					db_execute_prepared('DELETE FROM poller_reindex WHERE host_id = ?', array($selected_item));
				}
			} elseif (get_request_var('drp_action') == '4') { // change device options
				foreach ($selected_items as $selected_item) {
					foreach ($fields_host_edit as $field_name => $field_array) {
						if (isset_request_var("t_$field_name")) {
							db_execute_prepared("UPDATE host SET $field_name = ? WHERE id = ?", array(get_nfilter_request_var($field_name), $selected_item));
							if ($field_name == 'host_template_id') {
								api_device_update_host_template($selected_item, get_nfilter_request_var($field_name));
							}
						}
					}

					push_out_host($selected_item);
				}
			} elseif (get_request_var('drp_action') == '5') { // Clear Statisitics for Selected Devices
				foreach ($selected_items as $selected_item) {
					db_execute_prepared("UPDATE host SET min_time = '9.99999', max_time = '0', cur_time = '0', avg_time = '0',
						total_polls = '0', failed_polls = '0',	availability = '100.00'
						where id = ?", array($selected_item));
				}
			} elseif (get_request_var('drp_action') == '7') { // sync to device template
				foreach ($selected_items as $selected_item) {
					$device_template_id = db_fetch_cell_prepared('SELECT host_template_id FROM host WHERE id = ?', array($selected_item));

					if ($device_template_id > 0) {
						api_device_update_host_template($selected_item, $device_template_id);
					}
				}
			} elseif (get_request_var('drp_action') == '1') { // delete
				if (!isset_request_var('delete_type')) {
					set_request_var('delete_type', 2);
				}

				$data_sources_to_act_on = array();
				$graphs_to_act_on       = array();
				$devices_to_act_on      = array();

				foreach ($selected_items as $selected_item) {
					$data_sources = db_fetch_assoc('SELECT
						data_local.id AS local_data_id
						FROM data_local
						WHERE ' . array_to_sql_or($selected_items, 'data_local.host_id'));

					if (sizeof($data_sources)) {
						foreach ($data_sources as $data_source) {
							$data_sources_to_act_on[] = $data_source['local_data_id'];
						}
					}

					if (get_nfilter_request_var('delete_type') == 2) {
						$graphs = db_fetch_assoc('SELECT
							graph_local.id AS local_graph_id
							FROM graph_local
							WHERE ' . array_to_sql_or($selected_items, 'graph_local.host_id'));

						if (sizeof($graphs)) {
							foreach ($graphs as $graph) {
								$graphs_to_act_on[] = $graph['local_graph_id'];
							}
						}
					}

					$devices_to_act_on[] = $selected_item;
				}

				switch (get_nfilter_request_var('delete_type')) {
					case '1': /* leave graphs and data_sources in place, but disable the data sources */
						api_data_source_disable_multi($data_sources_to_act_on);

						api_plugin_hook_function('data_source_remove', $data_sources_to_act_on);

						break;
					case '2': /* delete graphs/data sources tied to this device */
						api_data_source_remove_multi($data_sources_to_act_on);

						api_graph_remove_multi($graphs_to_act_on);

						api_plugin_hook_function('graphs_remove', $graphs_to_act_on);

						break;
				}

				api_device_remove_multi($devices_to_act_on);

				api_plugin_hook_function('device_remove', $devices_to_act_on);
			} elseif (preg_match('/^tr_([0-9]+)$/', get_request_var('drp_action'), $matches)) { // place on tree
				get_filter_request_var('tree_id');
				get_filter_request_var('tree_item_id');

				foreach ($selected_items as $selected_item) {
					api_tree_item_save(0, get_nfilter_request_var('tree_id'), TREE_ITEM_TYPE_HOST, get_nfilter_request_var('tree_item_id'), '', 0, $selected_item, 1, 1, false);
				}
			} elseif (get_request_var('drp_action') == 6) { // automation
				cacti_log(__FUNCTION__ . ' called, action: ' . get_request_var('drp_action'), true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

				cacti_log(__FUNCTION__ . ', items: ' . get_nfilter_request_var('selected_items'), true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

				/* work on all selected hosts */
				foreach ($selected_items as $host_id) {
					automation_update_device($host_id);
				}
			} else {
				api_plugin_hook_function('device_action_execute', get_nfilter_request_var('drp_action'));
			}
		}

		/* update snmpcache */
		snmpagent_device_action_bottom(array(get_nfilter_request_var('drp_action'), $selected_items));

		api_plugin_hook_function('device_action_bottom', array(get_nfilter_request_var('drp_action'), $selected_items));

		header('Location: host.php?header=false');
		exit;
	}

	/* setup some variables */
	$host_list = '';
	$host_array = array();

	/* loop through each of the host templates selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$host_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($matches[1]))) . '</li>';
			$host_array[] = $matches[1];
		}
	}

	top_header();

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	form_start('host.php');

	html_start_box($device_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($host_array) && sizeof($host_array)) {
		if (get_request_var('drp_action') == '2') { // Enable Devices
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to enable the following Device(s).') . "</p>
					<div class='itemlist'><ul>$host_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Enable Device(s)') . "'>";
		} elseif (get_nfilter_request_var('drp_action') == '3') { // Disable Devices
			print "	<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to disable the following Device(s).') . "</p>
					<div class='itemlist'><ul>$host_list</ul></div>
				</td>
				</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Disable Device(s)') ."'>";
		} elseif (get_nfilter_request_var('drp_action') == '4') { // Change Device options
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to change the Device options below for multiple Device(s).  Please check the box next to the fields you want to update, and then fill in the new value.') . "</p>
					<div class='itemlist'><ul>$host_list</ul></div>
				</td>
			</tr>\n";

			$form_array = array();

			foreach ($fields_host_edit as $field_name => $field_array) {
				if ((preg_match('/^snmp_/', $field_name)) ||
					(preg_match('/^ping_/', $field_name)) ||
					($field_name == 'poller_id') ||
					($field_name == 'site_id') ||
					($field_name == 'host_template_id') ||
					($field_name == 'availability_method') ||
					($field_name == 'device_threads') ||
					($field_name == 'max_oids')) {
					$form_array += array($field_name => $fields_host_edit[$field_name]);

					$form_array[$field_name]['value'] = '';

					if (read_config_option('hide_form_description') == 'on') {
						$form_array[$field_name]['description'] = '';
					}

					$form_array[$field_name]['form_id'] = 0;
					$form_array[$field_name]['sub_checkbox'] = array(
						'name' => 't_' . $field_name,
						'friendly_name' => __('Update this Field'),
						'value' => ''
						);
				}
			}

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => $form_array
				)
			);

			device_javascript();

			device_change_javascript();

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Change Device(s) SNMP Options') . "'>";
		} elseif (get_request_var('drp_action') == '5') { // Clear Statisitics for Selected Devices
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to clear the counters for the following Device(s).') . "</p>
					<div class='itemlist'><ul>$host_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Clear Statistics on Device(s)') . "'>";
		} elseif (get_nfilter_request_var('drp_action') == '7') { // sync device template
			print "	<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to Synchronize the following Device(s) to their Device Template.') . "</p>
					<div class='itemlist'><ul>$host_list</ul></div>
				</td>
				</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Synchronize Device(s)') ."'>";
		} elseif (get_request_var('drp_action') == '1') { // Delete
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to delete the following Device(s).') . "</p>
					<div class='itemlist'><ul>$host_list</ul></div>\n";

					form_radio_button('delete_type', '2', '1', __('Leave all Graph(s) and Data Source(s) untouched.  Data Source(s) will be disabled however.'), '1'); print '<br>';
					form_radio_button('delete_type', '2', '2', __('Delete all associated Graph(s) and Data Source(s).'), '1'); print '<br>';

			print "</td></tr>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Delete Device(s)') . "'>";
		} elseif (preg_match('/^tr_([0-9]+)$/', get_request_var('drp_action'), $matches)) { // place on tree
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to place the following Device(s) under the branch selected below.') . "</p>
					<div class='itemlist'><ul>$host_list</ul></div>
					<p>" . __('Destination Branch:') . "<br>\n";
					grow_dropdown_tree($matches[1], '0', 'tree_item_id', '0');

			print "</p>
				</td>
			</tr>
			<input type='hidden' name='tree_id' value='" . $matches[1] . "'>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Place Device(s) on Tree') . "'>";
		} elseif (get_request_var('drp_action') == 6) { // automation
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to apply Automation Rules to the following Devices(s).'). "</p>
					<div class='itemlist'><ul>$host_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel'). "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Run Automation on Device(s)') . "'>";
		} else {
			$save['drp_action'] = get_request_var('drp_action');
			$save['host_list']  = $host_list;
			$save['host_array'] = $host_array;

			api_plugin_hook_function('device_action_prepare', $save);

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "'>";
		}
	} else {
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one device.') . "</span></td></tr>\n";
		$save_html = "<input type='button' value='" . __esc('Return') . "' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td colspan='2' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . serialize($host_array) . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* -------------------
	Device Export Function
   ------------------- */

function host_export() {
	host_validate_vars();

	$hosts = get_device_records($total_rows, 9999999);

	$stdout = fopen('php://output', 'w');

	header('Content-type: application/excel');
	header('Content-Disposition: attachment; filename=cacti-devices-' . time() . '.csv');

	if (sizeof($hosts)) {
		$columns = array_keys($hosts[0]);
		fputcsv($stdout, $columns);

		foreach($hosts as $h) {
			fputcsv($stdout, $h);
		}
	}

	fclose($stdout);
}

/* -------------------
    Data Query Functions
   ------------------- */

function host_add_query() {
	/* ================= input validation ================= */
	get_filter_request_var('host_id');
	get_filter_request_var('snmp_query_id');
	get_filter_request_var('reindex_method');
	/* ==================================================== */

	api_device_dq_add(get_request_var('host_id'), get_request_var('snmp_query_id'), get_request_var('reindex_method'));
}

function host_reload_query() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_id');
	/* ==================================================== */

	run_data_query(get_request_var('host_id'), get_request_var('id'));
}

function host_remove_query() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_id');
	/* ==================================================== */

	api_device_dq_remove(get_request_var('host_id'), get_request_var('id'));
}

function host_change_query() {
	/* ================= input validation ================= */
	get_filter_request_var('data_query_id');
	get_filter_request_var('host_id');
	get_filter_request_var('reindex_method');
	/* ==================================================== */

	api_device_dq_change(get_request_var('host_id'), get_request_var('data_query_id'), get_request_var('reindex_method'));
}

function host_add_gt() {
	/* ================= input validation ================= */
	get_filter_request_var('host_id');
	get_filter_request_var('graph_template_id');
	/* ==================================================== */

	db_execute_prepared('REPLACE INTO host_graph
		(host_id, graph_template_id)
		VALUES (?, ?)',
		array(get_nfilter_request_var('host_id'), get_nfilter_request_var('graph_template_id')));

	automation_hook_graph_template(get_nfilter_request_var('host_id'), get_nfilter_request_var('graph_template_id'));

	api_plugin_hook_function('add_graph_template_to_host', array('host_id' => get_nfilter_request_var('host_id'), 'graph_template_id' => get_nfilter_request_var('graph_template_id')));
}

function host_remove_gt() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_id');
	/* ==================================================== */

	api_device_gt_remove(get_request_var('host_id'), get_request_var('id'));
}

/* ---------------------
    Device Functions
   --------------------- */

function host_edit() {
	global $fields_host_edit, $reindex_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	api_plugin_hook('host_edit_top');

	$header_label = __('Device [new]');
	$debug_link   = '';
	if (!isempty_request_var('id')) {
		$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array(get_request_var('id')));

		if (sizeof($host)) {
			$header_label = __('Device [edit: %s]', htmlspecialchars($host['description']));
			if (is_device_debug_enabled($host['id'])) {
				$debug_link = "<span class='linkMarker'>*</span><a class='hyperLink' href='" . htmlspecialchars('host.php?action=disable_debug&host_id=' . $host['id']) . "'>" . __('Disable Device Debug') . "</a><br>";
			} else {
				$debug_link = "<span class='linkMarker'>*</span><a class='hyperLink' href='" . htmlspecialchars('host.php?action=enable_debug&host_id=' . $host['id']) . "'>" . __('Enable Device Debug') . "</a><br>";
			}
		}
	}

	if (!empty($host['id'])) {
		?>
		<table class='hostInfoHeader' style='width:100%'>
			<tr>
				<td class='textInfo left'>
					<?php print htmlspecialchars($host['description']);?> (<?php print htmlspecialchars($host['hostname']);?>)
				</td>
				<td rowspan='2' class='textInfo right' style='vertical-align:top'>
					<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print htmlspecialchars('graphs_new.php?host_id=' . $host['id']);?>'><?php print __('Create Graphs for this Device');?></a><br>
					<?php print $debug_link;?>
					<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print htmlspecialchars('data_sources.php?host_id=' . $host['id'] . '&ds_rows=30&filter=&template_id=-1&method_id=-1&page=1');?>'><?php print __('Data Source List');?></a><br>
					<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print htmlspecialchars('graphs.php?host_id=' . $host['id'] . '&graph_rows=30&filter=&template_id=-1&page=1');?>'><?php print __('Graph List');?></a>
					<?php api_plugin_hook('device_edit_top_links'); ?>
				</td>
			</tr>
			<tr>
				<td style='vertical-align:top;' class='textHeader'>
					<div id='ping_results'><?php print __('Contacting Device');?>&nbsp;<i style='font-size:12px;' class='fa fa-spin fa-spinner'></i><br><br></div>
				</td>
			</tr>
		</table>
		<?php
	}

	form_start('host.php', 'host_form');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	/* preserve the host template id if passed in via a GET variable */
	if (!isempty_request_var('host_template_id')) {
		$fields_host_edit['host_template_id']['value'] = get_filter_request_var('host_template_id');
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_host_edit, (isset($host) ? $host : array()))
		)
	);

	html_end_box(true, true);

	device_javascript();

	if ((isset_request_var('display_dq_details')) && (isset($_SESSION['debug_log']['data_query']))) {
		$dbg_copy_uid = generate_hash();
		?>
		<div id='dqdebug' class='cactiTable'>
			<div id='clipboardHeader<?php print $dbg_copy_uid;?>'>
				<div class='cactiTableTitle'>
					<span style='padding:3px;'><?php print __('Data Query Debug Information');?></span>
				</div>
				<div class='cactiTableButton'>
					<span>
						<a class='linkCopyDark cactiTableCopy' id='copyToClipboard<?php print $dbg_copy_uid;?>'><?php print __('Copy');?></a>
						<a id='dbghide' class='fa fa-remove' href='#'><?php print __('Hide');?></a>
					</span>
				</div>
			</div>
			<table class='cactiTable' id='clipboardData<?php print $dbg_copy_uid;?>'>
				<tr>
					<td class='debug'>
						<span><?php print debug_log_return('data_query');?></span>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	if (!empty($host['id'])) {
		html_start_box(__('Associated Graph Templates'), '100%', '', '3', 'center', '');

		html_header(
			array(
				array('display' => __('Graph Template Name'), 'align' => 'left', 'nohide' => true),
				array('display' => __('Status'), 'align' => 'left', 'nohide' => true)
			), 2);

		$selected_graph_templates = db_fetch_assoc_prepared("
			SELECT result.id, result.name, graph_local.id AS graph_local_id
			FROM (
				SELECT DISTINCT gt.id, gt.name
				FROM graph_templates AS gt
				INNER JOIN host_graph AS hg
				ON gt.id = hg.graph_template_id
				WHERE hg.host_id = ?
			) AS result
			LEFT JOIN graph_local
			ON graph_local.graph_template_id = result.id
			AND graph_local.host_id = ?
			ORDER BY result.name",
			array(get_request_var('id'), get_request_var('id'))
		);

		$available_graph_templates = db_fetch_assoc_prepared("SELECT DISTINCT gt.id, gt.name
			FROM graph_templates AS gt
			LEFT JOIN snmp_query_graph AS sqg
			ON sqg.graph_template_id = gt.id
			INNER JOIN graph_templates_item AS gti
			ON gti.graph_template_id = gt.id
			INNER JOIN data_template_rrd AS dtr
			ON gti.task_item_id = dtr.id
			INNER JOIN data_template_data AS dtd
			ON dtd.data_template_id = dtr.data_template_id
			WHERE sqg.name IS NULL
			AND gti.local_graph_id = 0
			AND dtr.local_data_id = 0
			AND gt.id NOT IN (SELECT graph_template_id FROM host_graph WHERE host_id = ?)
			ORDER BY gt.name",
			array(get_request_var('id'))
		);

		$i = 0;
		$displayed_templates = array();
		if (sizeof($selected_graph_templates)) {
			foreach ($selected_graph_templates as $item) {
				if (isset($displayed_templates[$item['id']])) {
					continue;
				} else {
					$displayed_templates[$item['id']] = true;
				}

				$i++;

				form_alternate_row("gt$i", true);

				/* get status information for this graph template */
				$is_being_graphed = $item['graph_local_id'] > 0;
				?>
					<td class='nowrap' style="padding: 4px;">
						<strong><?php print $i;?>)</strong> <?php print htmlspecialchars($item['name']);?>
					</td>
					<td class='nowrap'>
						<?php print (($is_being_graphed == true) ? "<span class='beingGraphed'>" . __('Is Being Graphed') . "</span> (<a class='linkEditMain' href='" . htmlspecialchars('graphs.php?action=graph_edit&id=' . $item['graph_local_id']) . "'>" . __('Edit') . "</a>)" : "<span class='notBeingGraphed'>" . __('Not Being Graphed') ."</span>");?>
					</td>
					<td class='nowrap right'>
						<span title='<?php print __esc('Delete Graph Template Association');?>' class='deletequery fa fa-remove' id='gtremove<?php print $item['id'];?>' data-id='<?php print $item['id'];?>'></span>
					</td>
				<?php

				form_end_row();
			}
		} else {
			print "<tr class='tableRow'><td colspan='3'><em>" . __('No associated graph templates.') . "</em></td></tr>";
		}

		?>
		<tr class='odd'>
			<td class='saveRow' colspan='3'>
				<table>
					<tr style='line-height:10px;'>
						<td class='nowrap templateAdd' style='padding-right:15px;'>
							<?php print __('Add Graph Template');?>
						</td>
						<td class='noHide'>
							<?php form_dropdown('graph_template_id',$available_graph_templates,'name','id','','','');?>
						</td>
						<td class='noHide'>
							<input type='button' value='<?php print __esc('Add');?>' id='add_gt' title='<?php print __esc('Add Graph Template to Device');?>'>
						</td>
					</tr>
				</table>
			</td>
		</tr>

		<?php
		html_end_box();

		html_start_box(__('Associated Data Queries'), '100%', '', '3', 'center', '');

		html_header(
			array(
				array('display' => __('Data Query Name'), 'align' => 'left', 'nohide' => true),
				array('display' => __('Re-Index Method'), 'align' => 'left', 'nohide' => true),
				array('display' => __('Status'), 'align' => 'left'),
				array('display' => __('Actions'), 'align' => 'right')
			)
		);

		if ($host['snmp_version'] == 0) {
			$sql_where1 = ' AND snmp_query.data_input_id != 2';
			$sql_where2 = ' WHERE snmp_query.data_input_id != 2 AND';
		} else {
			$sql_where1 = '';
			$sql_where2 = ' WHERE';
		}

		$sql_where2 .= ' snmp_query.id NOT IN(SELECT snmp_query_id FROM host_snmp_query WHERE host_id = ' . get_request_var('id') . ')';

		$selected_data_queries = db_fetch_assoc_prepared("SELECT snmp_query.id,
			snmp_query.name, host_snmp_query.reindex_method, IFNULL(`items`.`itemCount`, 0) AS itemCount, IFNULL(`rows`.`rowCount`, 0) AS rowCount
			FROM snmp_query
			INNER JOIN host_snmp_query
			ON snmp_query.id = host_snmp_query.snmp_query_id
			AND host_snmp_query.host_id = ?
			LEFT JOIN (
				SELECT snmp_query_id, COUNT(*) AS `itemCount`
				FROM host_snmp_cache
				WHERE host_id = ?
				GROUP BY snmp_query_id
			) AS `items`
			ON items.snmp_query_id = snmp_query.id
			LEFT JOIN (
				SELECT snmp_query_id, COUNT(DISTINCT snmp_index) AS `rowCount`
				FROM host_snmp_cache
				WHERE host_id = ?
				GROUP BY snmp_query_id
			) AS `rows`
			ON rows.snmp_query_id = snmp_query.id
			$sql_where1
			ORDER BY snmp_query.name",
			array(get_request_var('id'), get_request_var('id'), get_request_var('id'))
		);

		$available_data_queries = db_fetch_assoc("SELECT snmp_query.id, snmp_query.name
			FROM snmp_query
			$sql_where2
			ORDER BY snmp_query.name");

		$i = 0;
		if (sizeof($selected_data_queries)) {
			foreach ($selected_data_queries as $item) {
				$i++;

				form_alternate_row("dg$i", true);

				$status = 'success';

				?>
					<td style='padding:4px;'>
						<strong><?php print $i;?>)</strong> <?php print htmlspecialchars($item['name']);?>
					</td>
					<td class='nowrap'>
					<?php device_reindex_methods($item, $host);?>
					</td>
					<td>
						<?php print (($status == 'success') ? "<span class='success'>" . __('Success') . "</span>" : "<span class='failed'>" . __('Fail')) . "</span>" . __(' [%d Items, %d Rows]', $item['itemCount'], $item['rowCount']);?>
					</td>
					<td class='nowrap right' style='vertical-align:middle;'>
						<span class='reloadquery fa fa-refresh' id='reload<?php print $item['id'];?>' title='<?php print __esc('Reload Query');?>' data-id='<?php print $item['id'];?>'></span>
						<span class='verbosequery fa fa-refresh' id='verbose<?php print $item['id'];?>' title='<?php print __esc('Verbose Query');?>' data-id='<?php print $item['id'];?>'></span>
						<span class='deletequery fa fa-remove' id='remove<?php print $item['id'];?>' title='<?php print __esc('Remove Query');?>' data-id='<?php print $item['id'];?>'></span>
					</td>
				<?php
				form_end_row();
			}
		} else {
			print "<tr class='tableRow'><td colspan='4'><em>" . __('No Associated Data Queries.') . "</em></td></tr>";
		}

		if ($host['snmp_version'] == 0) {
			unset($reindex_types[1]);
			$default = 0;
		} else {
			$default = read_config_option('reindex_method');
		}

		?>
		<tr class='odd'>
			<td class='saveRow' colspan='4'>
				<table style='width:20%'>
					<tr style='line-height:10px;'>
						<td class='nowrap queryAdd' style='padding-right:15px;'>
							<?php print __('Add Data Query');?>
						</td>
						<td>
							<?php form_dropdown('snmp_query_id',$available_data_queries,'name','id','','','');?>
						</td>
						<td class='nowrap' style='padding-right:15px;'>
							<?php print __('Re-Index Method');?>
						</td>
						<td>
							<?php form_dropdown('reindex_method',$reindex_types,'','',$default,'','');?>
						</td>
						<td>
							<input type='button' value='<?php print __esc('Add');?>' id='add_dq' title='<?php print __esc('Add Data Query to Device');?>'>
						</td>
					</tr>
				</table>
				<a style='display:none;' id='dqtop'></a>
			</td>
		</tr>

		<?php
		html_end_box();

		api_plugin_hook('device_edit_pre_bottom');
	}

	form_save_button('host.php', 'return');

	api_plugin_hook('host_edit_bottom');
}

function device_reindex_methods($item, $host) {
	global $config, $reindex_types, $reindex_types_tips;

	$selectedTheme = get_selected_theme();

	$i = 0;
	foreach($reindex_types as $key => $type) {
		if ($selectedTheme != 'classic') {
			if ($i == 0) {
				print "<fieldset class='reindex_methods'>\n";
			}
			print "<input name='reindex_radio_" . $item['id'] . "' type='radio' data-device-id='" . $host['id'] . "' data-query-id='" . $item['id'] . "' data-reindex-method='" . $key . "' id='reindex_" . $item['id'] . "_" . $key . "'" . ($item['reindex_method'] == $key ? ' checked="checked"':'') . " />\n";
			print "<label title='" . htmlspecialchars($reindex_types_tips[$key], ENT_QUOTES, 'UTF-8') . "' for='reindex_" . $item['id'] . "_" . $key . "'>" . $type . "</label>\n";
		} else {
			print $reindex_types[$item['reindex_method']];
			break;
		}

		$i++;
	}

	if ($selectedTheme != 'classic') {
		print "</fieldset>\n";
	}
}

function device_change_javascript() {
	?>
	<script type="text/javascript">
	function disableField(id) {
		$('#'+id).prop('disabled', true).addClass('ui-state-disabled');;

		if ($('#'+id).button('instance')) {
			$('#'+id).button('disable');
		} else if ($('#'+id).selectmenu('instance')) {
			$('#'+id).selectmenu('disable');
		}
	}

	function enableField(id) {
		$('#'+id).prop('disabled', false).removeClass('ui-state-disabled');

		if ($('#'+id).button('instance')) {
			$('#'+id).button('enable');
		} else if ($('#'+id).selectmenu('instance')) {
			$('#'+id).selectmenu('enable');
		}
	}

	$(function() {
		$('input[id^="t_"]').click(function() {
			id = $(this).attr('id').substring(2);
			if ($(this).is(':checked')) {
				enableField(id);
			} else {
				disableField(id);
			}
		});

		$('input[id^="t_"]').each(function() {
			id = $(this).attr('id').substring(2);
			disableField(id);
		});
	});
	</script>
	<?php
}

function device_javascript() {
	?>
	<script type="text/javascript">

	// default snmp information
	var snmp_community       = $('#snmp_community').val();
	var snmp_username        = $('#snmp_username').val();
	var snmp_password        = $('#snmp_password').val();
	var snmp_auth_protocol   = $('#snmp_auth_protocol').val();
	var snmp_priv_passphrase = $('#snmp_priv_passphrase').val();
	var snmp_priv_protocol   = $('#snmp_priv_protocol').val();
	var snmp_context         = $('#snmp_context').val();
	var snmp_engine_id       = $('#snmp_engine_id').val();
	var snmp_port            = $('#snmp_port').val();
	var snmp_timeout         = $('#snmp_timeout').val();
	var max_oids             = $('#max_oids').val();

	// default ping methods
	var ping_method    = $('#ping_method').val();
	var ping_port      = $('#ping_port').val();
	var ping_timeout   = $('#ping_timeout').val();
	var ping_retries   = $('#ping_retries').val();

	function setPing() {
		if ($('#row_host_avail_head').hasClass('collapsed')) {
			return false;
		}

		availability_method = $('#availability_method').val();
		ping_method         = $('#ping_method').val();

		switch(availability_method) {
		case '0': // none
			$('#row_ping_method').hide();
			$('#row_ping_port').hide();
			$('#row_ping_timeout').hide();
			$('#row_ping_retries').hide();

			break;
		case '2': // snmp
		case '5': // snmp sysDesc
		case '6': // snmp getNext
			$('#row_ping_method').hide();
			$('#row_ping_port').hide();
			$('#row_ping_timeout').show();
			$('#row_ping_retries').show();

			break;
		default: // ping ok
			switch(ping_method) {
			case '1': // ping icmp
				$('#row_ping_method').show();
				$('#row_ping_port').hide();
				$('#row_ping_timeout').show();
				$('#row_ping_retries').show();

				break;
			case '2': // ping udp
			case '3': // ping tcp
				$('#row_ping_method').show();
				$('#row_ping_port').show();
				$('#row_ping_timeout').show();
				$('#row_ping_retries').show();

				break;
			}

			break;
		}
	}

	function setAvailability() {
		if ($('#row_host_avail_head').hasClass('collapsed')) {
			return false;
		}

		if ($('#snmp_version').val() == '0') {
			methods = [
				{ value: '0', text: '<?php print __('None');?>' },
				{ value: '3', text: '<?php print __('Ping');?>' }
			];

			if ($('#availability_method').val() != '3' && $('#availability_method').val() != '0') {
				$('#availability_method').val('3');
			}

			$('#availability_method').replaceOptions(methods, $('#availability_method').val());
		} else {
			methods = [
				{ value: '0', text: '<?php print __('None');?>' },
				{ value: '1', text: '<?php print __('Ping and SNMP Uptime');?>' },
				{ value: '2', text: '<?php print __('SNMP Uptime');?>' },
				{ value: '3', text: '<?php print __('Ping');?>' },
				{ value: '4', text: '<?php print __('Ping or SNMP Uptime');?>' },
				{ value: '5', text: '<?php print __('SNMP Desc');?>' },
				{ value: '6', text: '<?php print __('SNMP GetNext');?>' }
			];

			$('#availability_method').replaceOptions(methods, $('#availability_method').val());
		}

		switch($('#availability_method').val()) {
			case '0': // availability none
				$('#row_ping_method').hide();
				$('#ping_method').val('1');
				$('#row_ping_timeout').hide();
				$('#row_ping_port').hide();
				$('#row_ping_timeout').hide();
				$('#row_ping_retrie').hide();

				break;
			case '1': // ping and snmp sysUptime
			case '3': // ping
			case '4': // ping or snmp sysUptime
				$('#row_ping_method').show();

				break;
			case '2': // snmp sysUptime
			case '5': // snmp sysDesc
			case '6': // snmp getNext
				$('#row_ping_method').hide();
				$('#ping_method').val('1');

				break;
		}

		if ($('#availability_method-button').length) {
			$('#availability_method').selectmenu('refresh');
		}
	}

	function changeHostForm() {
		setSNMP();
		setAvailability();
		setPing();
	}

	$(function() {
		// Need to set this for global snmpv3 functions to remain sane between edits
		snmp_security_initialized = false;

		if (typeof hostInfoHeight != 'undefined') {
			if ($(window).scrollTop() == 0) {
				$('.hostInfoHeader').css('height', '');
			} else {
				$('.hostInfoHeader').css('height', hostInfoHeight);
			}
		}

		if ($('#snmp_version').val() == '3') {
			if ($('#snmp_auth_protocol').val() == '[None]') {
				if ($('#snmp_priv_protocol').val() == '[None]') {
					$('#snmp_security_level').val('noAuthNoPriv');
				} else {
					$('#snmp_security_level').val('authNoPriv');
				}
			} else {
				$('#snmp_security_level').val('authPriv');
			}
		}

		$('[id^="reload"]').click(function(data) {
			$(this).addClass('fa-spin');
			strURL = 'host.php?action=query_reload&id='+$(this).attr('data-id')+'&host_id='+$('#id').val();
			loadPageNoHeader(strURL, true);
		});

		$('[id^="verbose"]').click(function(data) {
			$(this).addClass('fa-spin');
			strURL = 'host.php?action=query_verbose&id='+$(this).attr('data-id')+'&host_id='+$('#id').val();
			loadPageNoHeader(strURL, true);
		});

		$('[id^="remove"]').click(function(data) {
			strURL = 'host.php?action=query_remove&id='+$(this).attr('data-id')+'&host_id='+$('#id').val();
			loadPageNoHeader(strURL, true);
		});

		$('[id^="gtremove"]').click(function(data) {
			strURL = 'host.php?action=gt_remove&id='+$(this).attr('data-id')+'&host_id='+$('#id').val();
			loadPageNoHeader(strURL, true);
		});

		$('#add_dq').click(function() {
			scrollTop = $(window).scrollTop();
			$.post('host.php?action=query_add', { host_id: $('#id').val(), snmp_query_id: $('#snmp_query_id').val(), reindex_method: $('#reindex_method').val(), __csrf_magic: csrfMagicToken }).done(function(data) {
				$('#main').html(data);
				applySkin();
				$(window).scrollTop(scrollTop);
			});
		});

		$('#add_gt').click(function() {
			scrollTop = $(window).scrollTop();
			$.post('host.php?action=gt_add', { host_id: $('#id').val(), graph_template_id: $('#graph_template_id').val(), __csrf_magic: csrfMagicToken }).done(function(data) {
				$('#main').html(data);
				applySkin();
				$(window).scrollTop(scrollTop);
			});
		});

		changeHostForm();
		$('#dbghide').click(function(data) {
			$('#dqdebug').empty().fadeOut('fast');
		});

		$('[id$="spacer"]').click(function() {
			changeHostForm();
		});

		$.get(urlPath+'host.php?action=ping_host&id='+$('#id').val())
			.done(function(data) {
				$('#ping_results').html(data);
				hostInfoHeight = $('.hostInfoHeader').height();
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			});

		$('input[id^="reindex_"]').change(function() {
			strURL  = urlPath+'host.php?action=query_change&header=false';
			strURL += '&host_id='+$(this).attr('data-device-id');
			strURL += '&data_query_id='+$(this).attr('data-query-id');
			strURL += '&reindex_method='+$(this).attr('data-reindex-method');

			height = $('.hostInfoHeader').height();

			loadPageNoHeader(strURL, true);

			$('.hostInfoHeader').css('height', height);
		});
	});

	</script>
	<?php
}

function host_validate_vars() {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'host_status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'site_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'poller_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	$filters = api_plugin_hook_function('device_filters', $filters);

	validate_store_request_vars($filters, 'sess_host');
	/* ================= input validation ================= */
}

function get_device_records(&$total_rows, $rows) {
	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE (host.hostname LIKE '%" . get_request_var('filter') . "%' OR host.description LIKE '%" . get_request_var('filter') . "%')";
	} else {
		$sql_where = '';
	}

	if (get_request_var('host_status') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_status') == '-2') {
		$sql_where .= ($sql_where != '' ? " AND host.disabled='on'" : " WHERE host.disabled='on'");
	} elseif (get_request_var('host_status') == '-3') {
		$sql_where .= ($sql_where != '' ? " AND host.disabled=''" : " WHERE host.disabled=''");
	} elseif (get_request_var('host_status') == '-4') {
		$sql_where .= ($sql_where != '' ? " AND (host.status!='3' OR host.disabled='on')" : " WHERE (host.status!='3' OR host.disabled='on')");
	}else {
		$sql_where .= ($sql_where != '' ? ' AND (host.status=' . get_request_var('host_status') . " AND host.disabled = '')" : 'where (host.status=' . get_request_var('host_status') . " AND host.disabled = '')");
	}

	if (get_request_var('host_template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND host.host_template_id=0' : ' WHERE host.host_template_id=0');
	} elseif (!isempty_request_var('host_template_id')) {
		$sql_where .= ($sql_where != '' ? ' AND host.host_template_id=' . get_request_var('host_template_id') : ' WHERE host.host_template_id=' . get_request_var('host_template_id'));
	}

	if (get_request_var('site_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('site_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND host.site_id=0' : ' WHERE host.site_id=0');
	} elseif (!isempty_request_var('site_id')) {
		$sql_where .= ($sql_where != '' ? ' AND host.site_id=' . get_request_var('site_id') : ' WHERE host.site_id=' . get_request_var('site_id'));
	}

	if (get_request_var('poller_id') == '-1') {
		/* Show all items */
	} else {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' host.poller_id=' . get_request_var('poller_id');
	}

	$sql_where = api_plugin_hook_function('device_sql_where', $sql_where);

	$total_rows = db_fetch_cell("SELECT
		COUNT(host.id)
		FROM host
		$sql_where");

	$poller_interval = read_config_option('poller_interval');

	$sql_order = get_order_string();
	$sql_limit = 'LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$sql_query = "SELECT host.*, graphs, data_sources,
		IF(status_event_count>0, status_event_count*$poller_interval,
			IF(UNIX_TIMESTAMP(status_rec_date)>943916400,UNIX_TIMESTAMP()-UNIX_TIMESTAMP(status_rec_date),
			IF(snmp_sysUptimeInstance>0 AND snmp_version > 0, snmp_sysUptimeInstance,UNIX_TIMESTAMP()))) AS instate
		FROM host
		LEFT JOIN (SELECT host_id, COUNT(*) AS graphs FROM graph_local GROUP BY host_id) AS gl
		ON host.id=gl.host_id
		LEFT JOIN (SELECT host_id, COUNT(*) AS data_sources FROM data_local GROUP BY host_id) AS dl
		ON host.id=dl.host_id
		$sql_where
		GROUP BY host.id
		$sql_order
		$sql_limit";

	return db_fetch_assoc($sql_query);
}

function host() {
	global $device_actions, $item_rows;

	if ((!empty($_SESSION['sess_host_status'])) && (!isempty_request_var('host_status'))) {
		if ($_SESSION['sess_host_status'] != get_nfilter_request_var('host_status')) {
			set_request_var('page', '1');
		}
	}

	host_validate_vars();

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'host.php?host_status=' + $('#host_status').val();
		strURL += '&host_template_id=' + $('#host_template_id').val();
		strURL += '&site_id=' + $('#site_id').val();
		strURL += '&poller_id=' + $('#poller_id').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'host.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	function exportRecords() {
		strURL = 'host.php?action=export';
		document.location = strURL;
	}

	$(function() {
		$('#rows, #site_id, #poller_id, #host_template_id, #host_status').change(function() {
			applyFilter();
		});

		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#export').click(function() {
			exportRecords();
		});

		$('#form_devices').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Devices'), '100%', '', '3', 'center', 'host.php?action=edit&host_template_id=' . htmlspecialchars(get_request_var('host_template_id')) . '&host_status=' . htmlspecialchars(get_request_var('host_status')));

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_devices' action='host.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Site');?>
					</td>
					<td>
						<select id='site_id'>
							<option value='-1'<?php if (get_request_var('site_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('site_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT id, name FROM sites ORDER BY name');

							if (sizeof($sites)) {
								foreach ($sites as $site) {
									print "<option value='" . $site['id'] . "'"; if (get_request_var('site_id') == $site['id']) { print ' selected'; } print '>' . htmlspecialchars($site['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Data Collector');?>
					</td>
					<td>
						<select id='poller_id'>
							<option value='-1'<?php if (get_request_var('poller_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<?php
							$pollers = db_fetch_assoc('SELECT id, name FROM poller ORDER BY name');

							if (sizeof($pollers)) {
								foreach ($pollers as $poller) {
									print "<option value='" . $poller['id'] . "'"; if (get_request_var('poller_id') == $poller['id']) { print ' selected'; } print '>' . htmlspecialchars($poller['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='host_template_id'>
							<option value='-1'<?php if (get_request_var('host_template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('host_template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php
							$host_templates = db_fetch_assoc('SELECT id, name FROM host_template ORDER BY name');

							if (sizeof($host_templates)) {
								foreach ($host_templates as $host_template) {
									print "<option value='" . $host_template['id'] . "'"; if (get_request_var('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . htmlspecialchars($host_template['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' id='refresh' value='<?php print __('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' id='clear' value='<?php print __('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							<input type='button' id='export' value='<?php print __('Export');?>' title='<?php print __esc('Export Devices');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Status');?>
					</td>
					<td>
						<select id='host_status'>
							<option value='-1'<?php if (get_request_var('host_status') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='-3'<?php if (get_request_var('host_status') == '-3') {?> selected<?php }?>><?php print __('Enabled');?></option>
							<option value='-2'<?php if (get_request_var('host_status') == '-2') {?> selected<?php }?>><?php print __('Disabled');?></option>
							<option value='-4'<?php if (get_request_var('host_status') == '-4') {?> selected<?php }?>><?php print __('Not Up');?></option>
							<option value='3'<?php if (get_request_var('host_status') == '3') {?> selected<?php }?>><?php print __('Up');?></option>
							<option value='1'<?php if (get_request_var('host_status') == '1') {?> selected<?php }?>><?php print __('Down');?></option>
							<option value='2'<?php if (get_request_var('host_status') == '2') {?> selected<?php }?>><?php print __('Recovering');?></option>
							<option value='0'<?php if (get_request_var('host_status') == '0') {?> selected<?php }?>><?php print __('Unknown');?></option>
						</select>
					</td>
					<td>
						<?php print __('Devices');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$display_text = array(
		'description' => array(
			'display' => __('Device Description'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The name by which this Device will be referred to.')
		),
		'hostname' => array(
			'display' => __('Hostname'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('Either an IP address, or hostname.  If a hostname, it must be resolvable by either DNS, or from your hosts file.')
		),
		'id' => array(
			'display' => __('ID'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The internal database ID for this Device.  Useful when performing automation or debugging.')
		),
		'graphs' => array(
			'display' => __('Graphs'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The total number of Graphs generated from this Device.')
		),
		'data_sources' => array(
			'display' => __('Data Sources'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The total number of Data Sources generated from this Device.')
		),
		'status' => array(
			'display' => __('Status'),
			'align' => 'center',
			'sort' => 'ASC',
			'tip' => __('The monitoring status of the Device based upon ping results.  If this Device is a special type Device, by using the hostname "localhost", or due to the setting to not perform an Availability Check, it will always remain Up.  When using cmd.php data collector, a Device with no Graphs, is not pinged by the data collector and will remain in an "Unknown" state.')
		),
		'instate' => array(
			'display' => __('In State'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The amount of time that this Device has been in its current state.')
		),
		'snmp_sysUpTimeInstance' => array(
			'display' => __('Uptime'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The current amount of time that the host has been up.')
		),
		'polling_time' => array(
			'display' => __('Poll Time'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The amount of time it takes to collect data from this Device.')
		),
		'cur_time' => array(
			'display' => __('Current (ms)'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The current ping time in milliseconds to reach the Device.')
		),
		'avg_time' => array(
			'display' => __('Average (ms)'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The average ping time in milliseconds to reach the Device since the counters were cleared for this Device.')
		),
		'availability' => array(
			'display' => __('Availability'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The availability percentage based upon ping results since the counters were cleared for this Device.')
		)
	);

	$hosts = get_device_records($total_rows, $rows);

	$nav = html_nav_bar('host.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, sizeof($display_text) + 1, __('Devices'), 'page', 'main');

	form_start('host.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (sizeof($hosts)) {
		foreach ($hosts as $host) {
			if ($host['disabled'] == '' &&
				($host['status'] == HOST_RECOVERING || $host['status'] == HOST_UP) &&
				($host['availability_method'] != AVAIL_NONE && $host['availability_method'] != AVAIL_PING)) {
				$snmp_uptime = $host['snmp_sysUpTimeInstance'];
				$days      = intval($snmp_uptime / (60*60*24*100));
				$remainder = $snmp_uptime % (60*60*24*100);
				$hours     = intval($remainder / (60*60*100));
				$remainder = $remainder % (60*60*100);
				$minutes   = intval($remainder / (60*100));
				$uptime    = $days . 'd:' . $hours . 'h:' . $minutes . 'm';
			} else {
				$uptime    = "N/A";
			}

			form_alternate_row('line' . $host['id'], true);
			form_selectable_cell(filter_value($host['description'], get_request_var('filter'), 'host.php?action=edit&id=' . $host['id']), $host['id']);
			form_selectable_cell(filter_value($host['hostname'], get_request_var('filter')), $host['id']);
			form_selectable_cell($host['id'], $host['id'], '', 'text-align:right');
			form_selectable_cell(number_format_i18n($host['graphs'], '-1'), $host['id'], '', 'text-align:right');
			form_selectable_cell(number_format_i18n($host['data_sources'], '-1'), $host['id'], '', 'text-align:right');
			form_selectable_cell(get_colored_device_status(($host['disabled'] == 'on' ? true : false), $host['status']), $host['id'], '', 'text-align:center');
			form_selectable_cell(get_timeinstate($host), $host['id'], '', 'text-align:right');
			form_selectable_cell($uptime, $host['id'], '', 'text-align:right');
			form_selectable_cell(round($host['polling_time'],2), $host['id'], '', 'text-align:right');
			form_selectable_cell(round(($host['cur_time']), 2), $host['id'], '', 'text-align:right');
			form_selectable_cell(round(($host['avg_time']), 2), $host['id'], '', 'text-align:right');
			form_selectable_cell(round($host['availability'], 2) . ' %', $host['id'], '', 'text-align:right');
			form_checkbox_cell($host['description'], $host['id']);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='11'><em>" . __('No Devices Found') . "</em></td></tr>";
	}

	html_end_box(false);

	if (sizeof($hosts)) {
		print $nav;
	}

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($device_actions);

	form_end();

	api_plugin_hook('device_table_bottom');
}

