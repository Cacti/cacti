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
include_once('./lib/api_automation.php');
include_once('./lib/api_data_source.php');
include_once('./lib/api_device.php');
include_once('./lib/api_graph.php');
include_once('./lib/api_tree.php');
include_once('./lib/data_query.php');
include_once('./lib/html_tree.php');
include_once('./lib/ping.php');
include_once('./lib/poller.php');
include_once('./lib/reports.php');
include_once('./lib/snmp.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');

$actions = array(
	1 => __('Delete'),
	2 => __('Enable'),
	3 => __('Disable'),
	4 => __('Change Device Settings'),
	5 => __('Clear Statistics'),
	6 => __('Apply Automation Rules'),
	7 => __('Sync to Device Template')
);

$reports = db_fetch_cell_prepared('SELECT COUNT(*)
	FROM reports
	WHERE user_id = ?
	ORDER BY name',
	array($_SESSION[SESS_USER_ID])
);

if ($reports > 0) {
	$actions += array(
		8 => __('Place Device on Report')
	);
}

$actions = api_plugin_hook_function('device_action_array', $actions);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'export':
		host_export();

		break;
	case 'save':
		form_save();

		break;
	case 'reindex':
		host_reindex();

		header('Location: host.php?action=edit&id=' . get_request_var('host_id'));

		break;
	case 'actions':
		form_actions();

		break;
	case 'gt_add':
		get_filter_request_var('host_id');

		host_add_gt();

		header('Location: host.php?action=edit&id=' . get_request_var('host_id'));

		break;
	case 'gt_remove':
		get_filter_request_var('host_id');

		host_remove_gt();

		header('Location: host.php?action=edit&id=' . get_request_var('host_id'));

		break;
	case 'query_add':
		get_filter_request_var('host_id');

		host_add_query();

		header('Location: host.php?action=edit&id=' . get_request_var('host_id'));

		break;
	case 'query_remove':
		get_filter_request_var('host_id');

		host_remove_query();

		header('Location: host.php?action=edit&id=' . get_request_var('host_id'));

		break;
	case 'query_change':
		get_filter_request_var('host_id');

		host_change_query();

		header('Location: host.php?action=edit&id=' . get_request_var('host_id'));

		break;
	case 'query_reload':
		get_filter_request_var('host_id');

		host_reload_query();
		raise_message('query_reloaded', __('Data Query Re-indexed.'), MESSAGE_LEVEL_INFO);

		header('Location: host.php?action=edit&id=' . get_request_var('host_id'));

		break;
	case 'query_verbose':
		get_filter_request_var('host_id');

		host_reload_query();
		raise_message('query_reloaded', __('Device Data Query Re-indexed.  Verbose output displayed.'), MESSAGE_LEVEL_INFO);

		header('Location: host.php?action=edit&id=' . get_request_var('host_id') . '&display_dq_details=true');

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
		raise_message('enable_debug', __('Device Debugging Enabled for Device.'), MESSAGE_LEVEL_INFO);

		header('Location: host.php?action=edit&id=' . get_request_var('host_id'));

		break;
	case 'disable_debug':
		disable_device_debug(get_filter_request_var('host_id'));
		raise_message('disable_debug', __('Device Debugging Disabled for Device.'), MESSAGE_LEVEL_INFO);

		header('Location: host.php?action=edit&id=' . get_request_var('host_id'));

		break;
	case 'repopulate':
		if (get_filter_request_var('host_id') > 0) {
			push_out_host(get_request_var('host_id'));
			raise_message('repopulate_message', __('Poller Cache for Device Refreshed.'), MESSAGE_LEVEL_INFO);
		} else {
			raise_message('repopulate_error', __('ERROR: Invalid Device ID.'), MESSAGE_LEVEL_ERROR);
		}

		header('Location: host.php?action=edit&id=' . get_request_var('host_id'));

		break;
	case 'ajax_locations':
		get_site_locations();

		break;

	default:
		top_header();

		host();

		bottom_footer();

		break;
}

function host_reindex() {
	global $config;

	$start = microtime(true);

	shell_exec(read_config_option('path_php_binary') . ' -q ' . CACTI_PATH_CLI . '/poller_reindex_hosts.php --qid=all --id=' . get_filter_request_var('host_id'));

	$end = microtime(true);

	$total_time = $end - $start;

	$items = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM host_snmp_cache
		WHERE host_id = ?',
		array(get_filter_request_var('host_id'))
	);

	raise_message('host_reindex', __('Device Reindex Completed in %0.2f seconds.  There were %d items updated.', $total_time, $items), MESSAGE_LEVEL_INFO);
}

function add_tree_names_to_actions_array() {
	global $actions;

	/* add a list of tree names to the actions dropdown */
	$trees = db_fetch_assoc('SELECT id, name FROM graph_tree ORDER BY name');

	if (cacti_sizeof($trees)) {
		foreach ($trees as $tree) {
			$actions['tr_' . $tree['id']] = __esc('Place on a Tree (%s)', $tree['name']);
		}
	}
}

function get_site_locations() {
	$return  = array();
	$term    = get_nfilter_request_var('term');
	$host_id = $_SESSION['cur_device_id'];

	$args  = ["%$term%"];
	$where = '';

	if (read_config_option('site_location_filter') && $_SESSION['cur_device_id']) {
		$site_id = db_fetch_cell_prepared('SELECT site_id
			FROM host
			WHERE id = ?',
			array($host_id));
		$args []= $site_id;
		$where = 'AND site_id = ?';
	}

	$locations = db_fetch_assoc_prepared("SELECT DISTINCT location
		FROM host
		WHERE location LIKE ?
		AND location != ''
		AND location IS NOT NULL
		$where
		ORDER BY location",
		$args);

	if (cacti_sizeof($locations)) {
		foreach ($locations as $l) {
			$return[] = array('label' => $l['location'], 'value' => $l['location'], 'id' => $l['location']);
		}
	}

	if (!cacti_sizeof($return)) {
		$return[] = array('label' => __('None'), 'value' => '', 'id' => __('None'));
	}

	print json_encode($return);
}

function form_save() {
	if (isset_request_var('save_component_host')) {
		if (get_nfilter_request_var('snmp_version') == 3 && (get_nfilter_request_var('snmp_password') != get_nfilter_request_var('snmp_password_confirm'))) {
			raise_message(14);
		} elseif (get_nfilter_request_var('snmp_version') == 3 && (get_nfilter_request_var('snmp_priv_passphrase') != get_nfilter_request_var('snmp_priv_passphrase_confirm'))) {
			raise_message(13);
		} else {
			get_filter_request_var('id');
			get_filter_request_var('host_template_id');

			$host_id = api_device_save(
				get_nfilter_request_var('id'),
				get_nfilter_request_var('host_template_id'),
				get_nfilter_request_var('description'),
				trim(get_nfilter_request_var('hostname')),
				get_nfilter_request_var('snmp_community'),
				get_nfilter_request_var('snmp_version'),
				get_nfilter_request_var('snmp_username'),
				get_nfilter_request_var('snmp_password'),
				get_nfilter_request_var('snmp_port'),
				get_nfilter_request_var('snmp_timeout'),
				(isset_request_var('disabled') ? get_nfilter_request_var('disabled') : ''),
				get_nfilter_request_var('availability_method'),
				get_nfilter_request_var('ping_method'),
				get_nfilter_request_var('ping_port'),
				get_nfilter_request_var('ping_timeout'),
				get_nfilter_request_var('ping_retries'),
				get_nfilter_request_var('notes'),
				get_nfilter_request_var('snmp_auth_protocol'),
				get_nfilter_request_var('snmp_priv_passphrase'),
				get_nfilter_request_var('snmp_priv_protocol'),
				get_nfilter_request_var('snmp_context'),
				get_nfilter_request_var('snmp_engine_id'),
				get_nfilter_request_var('max_oids'),
				get_nfilter_request_var('device_threads'),
				get_nfilter_request_var('poller_id'),
				get_nfilter_request_var('site_id'),
				get_nfilter_request_var('external_id'),
				get_nfilter_request_var('location'),
				get_nfilter_request_var('bulk_walk_size')
			);

			if ($host_id !== false) {
				api_plugin_hook_function('host_save', array('host_id' => $host_id));
			}
		}

		header('Location: host.php?action=edit&id=' . (empty($host_id) ? get_nfilter_request_var('id') : $host_id));
	}
}

function form_actions() {
	global $actions, $device_change_fields, $fields_host_edit;
	global $alignment, $graph_timespans;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_request_var('drp_action') == '2') { // Enable Selected Devices
				api_device_enable_devices($selected_items);
			} elseif (get_request_var('drp_action') == '3') { // Disable Selected Devices
				api_device_disable_devices($selected_items);
			} elseif (get_request_var('drp_action') == '4') { // change device options
				ini_set('max_execution_time', '-1');

				api_device_change_options($selected_items, $_POST);
			} elseif (get_request_var('drp_action') == '5') { // Clear Statistics for Selected Devices
				api_device_clear_statistics($selected_items);
			} elseif (get_request_var('drp_action') == '7') { // sync to device template
				ini_set('max_execution_time', '-1');

				api_device_sync_device_templates($selected_items);
			} elseif (get_request_var('drp_action') == '8') { // place device on report
				if (!reports_add_devices(get_filter_request_var('report_id'), $selected_items, get_filter_request_var('timespan'), get_filter_request_var('align'))) {
					$name = db_fetch_cell_prepared('SELECT name
						FROM reports
						WHERE id = ?',
						array(get_request_var('report_id'))
					);

					raise_message('reports_add_error', __('Unable to add some Devices to Report \'%s\'', $name), MESSAGE_LEVEL_WARN);
				}
			} elseif (get_request_var('drp_action') == '1') { // delete
				ini_set('max_execution_time', '-1');

				if (!isset_request_var('delete_type')) {
					set_request_var('delete_type', 2);
				}

				api_device_remove_multi($selected_items, get_filter_request_var('delete_type'));
			} elseif (preg_match('/^tr_([0-9]+)$/', get_request_var('drp_action'), $matches)) { // place on tree
				get_filter_request_var('tree_id');
				get_filter_request_var('tree_item_id');

				foreach ($selected_items as $selected_item) {
					api_tree_item_save(0, get_nfilter_request_var('tree_id'), TREE_ITEM_TYPE_HOST, get_nfilter_request_var('tree_item_id'), '', 0, $selected_item, 0, 1, 1, false);
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

		header('Location: host.php');

		exit;
	} else {
		$ilist   = '';
		$iarray  = array();
		$footer  = '';
		$reports = array();

		add_tree_names_to_actions_array();

		/* loop through each of the host templates selected on the previous page and get more info about them */
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1], 'chk[1]');
				/* ==================================================== */

				$ilist .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($matches[1]))) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		if (get_nfilter_request_var('drp_action') == '4') { // Change Device options
			$form_array = array();

			foreach ($fields_host_edit as $field_name => $field_array) {
				if (api_device_change_field_match($field_name)) {
					$form_array += array($field_name => $fields_host_edit[$field_name]);

					$form_array[$field_name]['value'] = '';

					if (read_config_option('hide_form_description') == 'on') {
						$form_array[$field_name]['description'] = '';
					}

					$form_array[$field_name]['form_id']      = 0;
					$form_array[$field_name]['sub_checkbox'] = array(
						'name'          => 't_' . $field_name,
						'friendly_name' => __('Update this Field'),
						'class'         => 'ui-state-disabled',
						'value'         => ''
					);

					$form_array['location']['method']     = 'textbox';
					$form_array['location']['default']    = '';
					$form_array['location']['size']       = '20';
					$form_array['location']['max_length'] = '40';
				}
			}

			ob_start();

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => $form_array
				)
			);

			device_change_javascript();
			device_javascript();

			$footer = ob_get_clean();
		} elseif (get_request_var('drp_action') == '8') { // Place on Report
			$reports = db_fetch_assoc_prepared('SELECT id, name
				FROM reports
				WHERE user_id = ?
				ORDER BY name',
				array($_SESSION[SESS_USER_ID])
			);

			if (cacti_sizeof($reports)) {
				$reports = array_rekey($reports, 'id', 'name');
			}
		}

		$form_data = array(
			'general' => array(
				'page'       => 'host.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			),
			'options' => array(
				1 => array(
					'smessage' => __('Click \'Continue\' to Delete the following Device.'),
					'pmessage' => __('Click \'Continue\' to Delete the following Devices.'),
					'scont'    => __('Delete Device'),
					'pcont'    => __('Delete Devices'),
					'extra'    => array(
						'delete_type' => array(
							'method' => 'radio_button',
							'options' => array(
								'1' => array(
									'default' => 2,
									'title' => __('Leave all Graph(s) and Data Source(s) untouched.  Data Source(s) will be disabled however.')
								),
								'2' => array(
									'default' => 2,
									'title' => __('Delete all associated Graph(s) and Data Source(s).')
								)
							)
						)
					)
				),
				2 => array(
					'smessage' => __('Click \'Continue\' to Enable the following Device.'),
					'pmessage' => __('Click \'Continue\' to Enable the following Devices.'),
					'scont'    => __('Enable Device'),
					'pcont'    => __('Enable Devices')
				),
				3 => array(
					'smessage' => __('Click \'Continue\' to Disable the following Device.'),
					'pmessage' => __('Click \'Continue\' to Disable the following Devices.'),
					'scont'    => __('Disable Device'),
					'pcont'    => __('Disable Devices')
				),
				4 => array(
					'smessage' => __('Click \'Continue\' to Change settings for the following Device.'),
					'pmessage' => __('Click \'Continue\' to Change settings for the following Devices.'),
					'scont'    => __('Change Device'),
					'pcont'    => __('Change Devices'),
					'footer'   => $footer,
				),
				5 => array(
					'smessage' => __('Click \'Continue\' to Clear Statistics the following Device.'),
					'pmessage' => __('Click \'Continue\' to Clear Statistics the following Devices.'),
					'scont'    => __('Clear Statistics for Device'),
					'pcont'    => __('Clear Statistics for Devices')
				),
				7 => array(
					'smessage' => __('Click \'Continue\' to Synchronize Device to its Device Template.'),
					'pmessage' => __('Click \'Continue\' to Synchronize Devices to their Device Templates.'),
					'scont'    => __('Synchronize Device Template'),
					'pcont'    => __('Synchronize Devices Templates')
				),
                8 => array(
					'smessage' => __('Click \'Continue\' to Place the following Device on a Report.'),
					'pmessage' => __('Click \'Continue\' to Place the following Devices on a Report.'),
					'scont'    => __('Place Device on Report'),
					'pcont'    => __('Place Devices on Report'),
					'extra'    => array(
						'report_id' => array(
							'method'  => 'drop_array',
							'title'   => __('Report Name:'),
							'array'   => $reports,
							'default' => array_key_first($reports)
						),
						'timespan' => array(
							'method'  => 'drop_array',
							'title'   => __('Timespan:'),
							'array'   => $graph_timespans,
							'default' => read_user_setting('default_timespan')
						),
						'align' => array(
							'method'  => 'drop_array',
							'title'   => __('Align:'),
							'array'   => $alignment,
							'default' => REPORTS_ALIGN_CENTER
						)
					)
				),
			)
		);

		$trees = db_fetch_assoc('SELECT id, name FROM graph_tree ORDER BY name');

		if (cacti_sizeof($trees)) {
			foreach($trees as $tree) {
				$form_data['options']['tr_' . $tree['id']] = array(
					'smessage' => __esc('Click \'Continue\' to Place the following Device on Tree %s.', $tree['name']),
					'pmessage' => __esc('Click \'Continue\' to Duplicate following Devices on Tree %s.', $tree['name']),
					'scont'    => __('Place Device on Tree'),
					'pcont'    => __('Place Devices on Tree'),
					'extra'    => array(
						'tree_item_id' => array(
							'method'  => 'drop_branch',
							'title'   => __('Desination Branch:'),
							'id'      => $tree['id']
						)
					),
					'eaction'   => 'tree_id',
					'eactionid' => $tree['id'],
				);
			}
		}

		$form_data = api_plugin_hook_function('device_confirmation_form', $form_data);

		form_continue_confirmation($form_data);
	}
}

function host_export() {
	host_validate_vars();

	$hosts = get_device_records($total_rows, 9999999);

	$stdout = fopen('php://output', 'w');

	header('Content-type: application/excel');
	header('Content-Disposition: attachment; filename=cacti-devices-' . time() . '.csv');

	if (cacti_sizeof($hosts)) {
		$columns = array_keys($hosts[0]);
		fputcsv($stdout, $columns);

		foreach ($hosts as $h) {
			fputcsv($stdout, $h);
		}
	}

	fclose($stdout);
}

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
		array(get_nfilter_request_var('host_id'), get_nfilter_request_var('graph_template_id'))
	);

	if (get_request_var('host_id') > 0) {
		object_cache_get_totals('device_state', get_request_var('host_id'));
	}

	automation_hook_graph_template(get_nfilter_request_var('host_id'), get_nfilter_request_var('graph_template_id'));

	api_plugin_hook_function('add_graph_template_to_host', array('host_id' => get_nfilter_request_var('host_id'), 'graph_template_id' => get_nfilter_request_var('graph_template_id')));

	if (get_request_var('host_id') > 0) {
		object_cache_get_totals('device_state', get_request_var('host_id'), true);
		object_cache_update_totals('diff');
	}
}

function host_remove_gt() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_id');
	/* ==================================================== */

	api_device_gt_remove(get_request_var('host_id'), get_request_var('id'));
}

function host_edit() {
	global $fields_host_edit, $reindex_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	api_plugin_hook('host_edit_top');

	$header_label = __('Device [new]');
	$debug_link   = '';
	$repop_link   = '';

	if (!isempty_request_var('id')) {
		$_SESSION['cur_device_id'] = get_request_var('id');

		$host = db_fetch_row_prepared('SELECT *
			FROM host
			WHERE id = ?',
			array(get_request_var('id'))
		);

		if (cacti_sizeof($host)) {
			$header_label = __esc('Device [edit: %s]', $host['description']);

			if (is_device_debug_enabled($host['id'])) {
				$debug_link = "<span class='linkMarker'>*</span><a class='hyperLink' href='" . html_escape('host.php?action=disable_debug&host_id=' . $host['id']) . "'>" . __('Disable Device Debug') . '</a><br>';
			} else {
				$debug_link = "<span class='linkMarker'>*</span><a class='hyperLink' href='" . html_escape('host.php?action=enable_debug&host_id=' . $host['id']) . "'>" . __('Enable Device Debug') . '</a><br>';
			}

			$repop_link = "<span class='linkMarker'>*</span><a class='hyperLink' href='" . html_escape('host.php?action=repopulate&host_id=' . $host['id']) . "'>" . __('Repopulate Poller Cache') . '</a><br>';
			$repop_link .= "<span class='linkMarker'>*</span><a class='hyperLink' href='" . html_escape('utilities.php?poller_action=-1&action=view_poller_cache&host_id=' . $host['id'] . '&template_id=-1&filter=&rows=-1') . "'>" . __('View Poller Cache') . '</a><br>';

			/* append uptime data */
			$header_label .= __(' [ In state since \'%s\', Uptime since \'%s\' ]', get_timeinstate($host, true), get_uptime($host, true));
		}
	} else {
		$_SESSION['cur_device_id'] = 0;
	}

	if (!empty($host['id'])) {
		?>
		<table class='hostInfoHeader' style='width:100%'>
			<tr>
				<td class='textInfo left'>
					<?php print html_escape($host['description']); ?> (<?php print html_escape($host['hostname']); ?>)<br />
				</td>
				<td rowspan='2' class='textInfo right' style='vertical-align:top'>
					<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print html_escape('host.php?action=edit'); ?>'><?php print __('Create New Device'); ?></a><br>
					<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print html_escape('graphs_new.php?reset=true&host_id=' . $host['id']); ?>'><?php print __('Create Graphs for this Device'); ?></a><br>
					<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print html_escape('host.php?action=reindex&host_id=' . $host['id']); ?>'><?php print __('Re-Index Device'); ?></a><br>
					<?php print $debug_link; ?>
					<?php print $repop_link; ?>
					<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print html_escape('data_sources.php?reset=true&host_id=' . $host['id'] . '&ds_rows=30&filter=&template_id=-1&method_id=-1&page=1'); ?>'><?php print __('Data Source List'); ?></a><br>
					<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print html_escape('graphs.php?reset=true&host_id=' . $host['id'] . '&graph_rows=30&filter=&template_id=-1&page=1'); ?>'><?php print __('Graph List'); ?></a>
					<?php api_plugin_hook('device_edit_top_links'); ?>
				</td>
			</tr>
			<tr>
				<td style='vertical-align:top;' class='textHeader'>
					<div id='ping_results'><?php print __('Contacting Device'); ?>&nbsp;<i style='font-size:12px;' class='fa fa-spin fa-spinner'></i><br><br></div>
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

	device_javascript(!empty($host['id']));

	if (!empty($host['id'])) {
		html_start_box(__('Associated Graph Templates'), '100%', '', '3', 'center', '');

		html_header(
			array(
				array('display' => __('Graph Template Name'), 'align' => 'left', 'nohide' => true),
				array('display' => __('Status'), 'align' => 'left', 'nohide' => true)
			),
			2
		);

		$selected_graph_templates = db_fetch_assoc_prepared('SELECT result.id, result.name, graph_local.id AS graph_local_id
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
			ORDER BY result.name',
			array(get_request_var('id'), get_request_var('id'))
		);

		$available_graph_templates = db_fetch_assoc_prepared('SELECT DISTINCT gt.id, gt.name
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
			ORDER BY gt.name',
			array(get_request_var('id'))
		);

		$i                   = 0;
		$displayed_templates = array();

		if (cacti_sizeof($selected_graph_templates)) {
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
					<strong><?php print $i; ?>)</strong> <?php print html_escape($item['name']); ?>
				</td>
				<td class='nowrap'>
					<?php print(($is_being_graphed == true) ? "<span class='beingGraphed'>" . __('Is Being Graphed') . "</span> (<a class='linkEditMain' href='" . html_escape('graphs.php?action=graph_edit&id=' . $item['graph_local_id']) . "'>" . __('Edit') . '</a>)' : "<span class='notBeingGraphed'>" . __('Not Being Graphed') . '</span>'); ?>
				</td>
				<td class='nowrap right'>
					<span title='<?php print __esc('Delete Graph Template Association'); ?>' class='deletequery fa fa-times' id='gtremove<?php print $item['id']; ?>' data-id='<?php print $item['id']; ?>'></span>
				</td>
		<?php

						form_end_row();
			}
		} else {
			print "<tr class='tableRow'><td colspan='3'><em>" . __('No associated graph templates.') . '</em></td></tr>';
		}

		?>
		<tr class='odd'>
			<td class='saveRow' colspan='3'>
				<table>
					<tr style='line-height:10px;'>
						<td class='nowrap templateAdd' style='padding-right:15px;'>
							<?php print __('Add Graph Template'); ?>
						</td>
						<td class='noHide'>
							<?php form_dropdown('graph_template_id', $available_graph_templates, 'name', 'id', '', '', ''); ?>
						</td>
						<td class='noHide'>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Add'); ?>' id='add_gt' title='<?php print __esc('Add Graph Template to Device'); ?>'>
						</td>
					</tr>
				</table>
			</td>
		</tr>

		<?php
		html_end_box();

		if ((isset_request_var('display_dq_details')) && (isset($_SESSION['debug_log']['data_query']))) {
			$dbg_copy_uid = generate_hash();
			?>
			<div id='dqdebug' class='cactiTable'>
				<div id='clipboardHeader<?php print $dbg_copy_uid; ?>'>
					<div class='cactiTableTitle'>
						<span style='padding:3px;'><?php print __('Data Query Debug Information'); ?></span>
					</div>
					<div class='cactiTableButton'>
						<span>
							<a class='linkCopyDark cactiTableCopy' id='copyToClipboard<?php print $dbg_copy_uid; ?>'><?php print __('Copy'); ?></a>
							<a id='dbghide' class='fa fa-times' href='#'><?php print __('Hide'); ?></a>
						</span>
					</div>
					<table class='cactiTable' id='clipboardData<?php print $dbg_copy_uid; ?>'>
						<tr class='tableRow'>
							<td class='debug'>
								<span><?php print debug_log_return('data_query'); ?></span>
							</td>
						</tr>
					</table>
				</div>
			</div>
			<?php
		}

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

		if (cacti_sizeof($selected_data_queries)) {
			foreach ($selected_data_queries as $item) {
				$i++;

				form_alternate_row("dg$i", true);

				$status = 'success';

				?>
				<td style='padding:4px;'>
					<strong><?php print $i; ?>)</strong> <?php print html_escape($item['name']); ?>
				</td>
				<td class='nowrap'>
					<?php device_reindex_methods($item, $host); ?>
				</td>
				<td>
					<?php print(($status == 'success') ? "<span class='success'>" . __('Success') . '</span>' : "<span class='failed'>" . __('Fail')) . '</span>' . __(' [%d Items, %d Rows]', $item['itemCount'], $item['rowCount']); ?>
				</td>
				<td class='nowrap right' style='vertical-align:middle;'>
					<span class='reloadquery fa fa-sync' id='reload<?php print $item['id']; ?>' title='<?php print __esc('Reload Query'); ?>' data-id='<?php print $item['id']; ?>'></span>
					<span class='verbosequery fa fa-sync' id='verbose<?php print $item['id']; ?>' title='<?php print __esc('Verbose Query'); ?>' data-id='<?php print $item['id']; ?>'></span>
					<span class='deletequery fa fa-times' id='remove<?php print $item['id']; ?>' title='<?php print __esc('Remove Query'); ?>' data-id='<?php print $item['id']; ?>'></span>
				</td>
		<?php
					form_end_row();
			}
		} else {
			print "<tr class='tableRow'><td colspan='4'><em>" . __('No Associated Data Queries.') . '</em></td></tr>';
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
							<?php print __('Add Data Query'); ?>
						</td>
						<td>
							<?php form_dropdown('snmp_query_id', $available_data_queries, 'name', 'id', '', '', ''); ?>
						</td>
						<td class='nowrap' style='padding-right:15px;'>
							<?php print __('Re-Index Method'); ?>
						</td>
						<td>
							<?php form_dropdown('reindex_method', $reindex_types, '', '', $default, '', ''); ?>
						</td>
						<td>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Add'); ?>' id='add_dq' title='<?php print __esc('Add Data Query to Device'); ?>'>
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

	foreach ($reindex_types as $key => $type) {
		if ($selectedTheme != 'classic') {
			if ($i == 0) {
				print "<fieldset class='reindex_methods'>";
			}
			print "<input name='reindex_radio_" . $item['id'] . "' type='radio' data-device-id='" . $host['id'] . "' data-query-id='" . $item['id'] . "' data-reindex-method='" . $key . "' id='reindex_" . $item['id'] . '_' . $key . "'" . ($item['reindex_method'] == $key ? ' checked="checked"' : '') . ' />';
			print "<label title='" . html_escape($reindex_types_tips[$key]) . "' for='reindex_" . $item['id'] . '_' . $key . "'>" . $type . '</label>';
		} else {
			print $reindex_types[$item['reindex_method']];

			break;
		}

		$i++;
	}

	if ($selectedTheme != 'classic') {
		print '</fieldset>';
	}
}

function device_change_javascript() {
	?>
	<script type="text/javascript">
		function disableField(id) {
			$('#' + id).prop('disabled', true).addClass('ui-state-disabled');;

			if ($('#' + id).button('instance')) {
				$('#' + id).button('disable');
			} else if ($('#' + id).selectmenu('instance')) {
				$('#' + id).selectmenu('disable');
			}
		}

		function enableField(id) {
			$('#' + id).prop('disabled', false).removeClass('ui-state-disabled');

			if ($('#' + id).button('instance')) {
				$('#' + id).button('enable');
			} else if ($('#' + id).selectmenu('instance')) {
				$('#' + id).selectmenu('enable');
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

function device_javascript(bool $hasHost = true) {
	?>
	<script type='text/javascript'>
		// default snmp information
		var snmp_community = $('#snmp_community').val();
		var snmp_username = $('#snmp_username').val();
		var snmp_password = $('#snmp_password').val();
		var snmp_auth_protocol = $('#snmp_auth_protocol').val();
		var snmp_priv_passphrase = $('#snmp_priv_passphrase').val();
		var snmp_priv_protocol = $('#snmp_priv_protocol').val();
		var snmp_context = $('#snmp_context').val();
		var snmp_engine_id = $('#snmp_engine_id').val();
		var snmp_port = $('#snmp_port').val();
		var snmp_timeout = $('#snmp_timeout').val();
		var max_oids = $('#max_oids').val();

		// default ping methods
		var ping_method = $('#ping_method').val();
		var ping_port = $('#ping_port').val();
		var ping_timeout = $('#ping_timeout').val();
		var ping_retries = $('#ping_retries').val();

		function changeHostForm() {
			setSNMP();
			setAvailability();
			setPing();
		}

		$(function() {
			// Need to set this for global snmpv3 functions to remain sane between edits
			snmp_security_initialized = false;

			<?php if (!$hasHost) { ?>
			$('#row_created').hide();
			<?php } ?>
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
						$('#snmp_priv_passphrase').val('');
						$('#snmp_priv_passphrase_confirm').val('');
						$('#snmp_password').val('');
						$('#snmp_password_confirm').val('');
					} else {
						$('#snmp_security_level').val('authNoPriv');
						$('#snmp_priv_passphrase').val('');
						$('#snmp_priv_passphrase_confirm').val('');
					}
				} else {
					$('#snmp_security_level').val('authPriv');
				}
			}

			$('[id^="reload"]').click(function(data) {
				$(this).addClass('fa-spin');
				strURL = 'host.php?action=query_reload&id=' + $(this).attr('data-id') + '&host_id=' + $('#id').val();
				loadUrl({
					url: strURL,
					scroll: true,
					nostate: true
				});
			});

			$('[id^="verbose"]').click(function(data) {
				$(this).addClass('fa-spin');
				strURL = 'host.php?action=query_verbose&id=' + $(this).attr('data-id') + '&host_id=' + $('#id').val();
				loadUrl({
					url: strURL,
					scroll: true,
					nostate: true
				});
			});

			$('[id^="remove"]').click(function(data) {
				strURL = 'host.php?action=query_remove&id=' + $(this).attr('data-id') + '&host_id=' + $('#id').val();
				loadUrl({
					url: strURL,
					scroll: true,
					nostate: true
				});
			});

			$('[id^="gtremove"]').click(function(data) {
				strURL = 'host.php?action=gt_remove&id=' + $(this).attr('data-id') + '&host_id=' + $('#id').val();
				loadUrl({
					url: strURL,
					scroll: true,
					nostate: true
				});
			});

			$('#add_dq').click(function() {
				var options = {
					url: 'host.php?action=query_add',
					scrollTop: $(window).scrollTop()
				}

				var data = {
					host_id: $('#id').val(),
					snmp_query_id: $('#snmp_query_id').val(),
					reindex_method: $('#reindex_method').val(),
					__csrf_magic: csrfMagicToken
				}

				postUrl(options, data);
			});

			$('#add_gt').click(function() {
				var options = {
					url: 'host.php?action=gt_add',
					scrollTop: $(window).scrollTop()
				}

				var data = {
					host_id: $('#id').val(),
					graph_template_id: $('#graph_template_id').val(),
					__csrf_magic: csrfMagicToken
				}

				postUrl(options, data);
			});

			changeHostForm();
			$('#dbghide').click(function(data) {
				$('#dqdebug').empty().fadeOut('fast');
			});

			if ($('#dbghide').length) {
				var dbgloc = parseInt($('#dbghide').offset().top - $('.breadCrumbBar').outerHeight() - $('.cactiPageHead').outerHeight());
				$('.cactiConsoleContentArea').scrollTop(dbgloc);
			}

			$('[id$="spacer"]').click(function() {
				changeHostForm();
			});

			$('#snmp_version').change(function() {
				setAvailability();
				setPing();
			});

			$('#location_input').keyup(function() {
				$('#location').val($('#location_input').val());
			}).mouseup(function() {
				$('#location').val($('#location_input').val());
			});

			loadUrl({
				url: urlPath + 'host.php?action=ping_host&id=' + $('#id').val(),
				elementId: 'ping_results',
				noState: true,
				funcEnd: 'ping_results_finalize',
			});

			$('input[id^="reindex_"]').change(function() {
				strURL = urlPath + 'host.php?action=query_change';
				strURL += '&host_id=' + $(this).attr('data-device-id');
				strURL += '&data_query_id=' + $(this).attr('data-query-id');
				strURL += '&reindex_method=' + $(this).attr('data-reindex-method');

				height = $('.hostInfoHeader').height();

				loadUrl({
					url: strURL,
					noState: true,
					scroll: true
				})

				$('.hostInfoHeader').css('height', height);
			});
		});

		function ping_results_finalize(options, html) {
			hostInfoHeight = $('.hostInfoHeader').height();
		}
	</script>
	<?php
}

function host_validate_vars() {
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
		'location' => array(
			'filter'  => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '-1',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		),
		'host_status' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'availability_method' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'host_template_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'site_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'poller_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		)
	);

	$filters = api_plugin_hook_function('device_filters', $filters);

	validate_store_request_vars($filters, 'sess_host');
	/* ================= input validation ================= */
}

function get_device_records(&$total_rows, $rows) {
	$sql_where = '';

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (deleted = "" AND (
			host.hostname LIKE '	   . db_qstr('%' . get_request_var('filter') . '%') . '
			OR host.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR host.id = '			 . db_qstr(get_request_var('filter')) . '))';
	} else {
		$sql_where = "WHERE deleted = ''";
	}

	if (get_request_var('location') == __('Undefined') || get_request_var('location') == '') {
		$sql_where .= ($sql_where != '' ? ' AND' : ' WHERE') . ' IFNULL(host.location,"") = ""';
	} elseif (get_request_var('location') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND' : ' WHERE') . ' host.location = ' . db_qstr(get_request_var('location'));
	}

	if (db_column_exists('sites', 'disabled')) {
		$host_where_disabled = "(IFNULL(TRIM(s.disabled), '') = 'on' OR IFNULL(TRIM(host.disabled),'') = 'on')";
	} else {
		$host_where_disabled = "(IFNULL(TRIM(host.disabled), '') = 'on')";
	}

	$host_where_status   = get_request_var('host_status');

	if ($host_where_status == '-1') {
		/* Show all items */
	} elseif ($host_where_status == '-2') {
		$sql_where .= ($sql_where == '' ? ' WHERE ' : ' AND ') . "$host_where_disabled";
	} elseif ($host_where_status == '-3') {
		$sql_where .= ($sql_where == '' ? ' WHERE ' : ' AND ') . "NOT $host_where_disabled";
	} elseif ($host_where_status == '-4') {
		$sql_where .= ($sql_where == '' ? ' WHERE ' : ' AND ') . "(host.status!='3' OR $host_where_disabled)";
	} else {
		$sql_where .= ($sql_where == '' ? ' WHERE ' : ' AND ') . "(host.status=$host_where_status AND NOT $host_where_disabled)";
	}

	if (get_request_var('availability_method') != '-1') {
		$sql_where .= ($sql_where == '' ? ' WHERE ' : ' AND ') . 'host.availability_method=' . get_request_var('availability_method');
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
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' host.poller_id=' . get_request_var('poller_id');
	}

	$sql_where = api_plugin_hook_function('device_sql_where', $sql_where);

	$sql = "SELECT
		COUNT(host.id)
		FROM host
		LEFT JOIN sites s
		ON s.id = host.site_id
		$sql_where";

	$total_rows = get_total_row_data($_SESSION[SESS_USER_ID], $sql, array(), 'device');

	$poller_interval = read_config_option('poller_interval');

	$sql_order = get_order_string();
	$sql_limit = 'LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$sql_query = "SELECT host.*,
		CAST(IF(availability_method = 0, '0',
			IF(status_event_count > 0 AND status IN (1, 2), status_event_count*$poller_interval,
			IF(UNIX_TIMESTAMP(status_rec_date) < 943916400 AND status IN (0, 3), total_polls*$poller_interval,
			IF(UNIX_TIMESTAMP(status_rec_date) > 943916400, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(status_rec_date),
			IF(snmp_sysUptimeInstance>0 AND snmp_version > 0, snmp_sysUptimeInstance/100, UNIX_TIMESTAMP()
		))))) AS unsigned) AS instate,
		s.name as site_name,
		s.disabled as site_disabled
		FROM host
		LEFT JOIN sites AS s
		ON host.site_id = s.id
		$sql_where
		$sql_order
		$sql_limit";

	return db_fetch_assoc($sql_query);
}

function host() {
	global $actions, $item_rows, $config, $availability_options;

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

	if (get_request_var('host_template_id') > 0) {
		$url = 'host.php?action=edit&host_template_id=' . get_request_var('host_template_id');
	} else {
		$url = 'host.php?action=edit';
	}

	html_start_box(__('Devices'), '100%', '', '3', 'center', $url);

	?>
	<tr class='even noprint'>
		<td>
			<form id='form_devices' action='host.php'>
				<table class='filterTable'>
					<tr>
						<?php api_plugin_hook('device_filter_start'); ?>
						<td>
							<?php print __('Site'); ?>
						</td>
						<td>
							<select id='site_id'>
								<option value='-1' <?php if (get_request_var('site_id') == '-1') { ?> selected<?php } ?>><?php print __('Any'); ?></option>
								<option value='0' <?php if (get_request_var('site_id') == '0') { ?> selected<?php } ?>><?php print __('None'); ?></option>
								<?php
								$sites = db_fetch_assoc('SELECT id, name FROM sites ORDER BY name');

								if (cacti_sizeof($sites)) {
									foreach ($sites as $site) {
										print "<option value='" . $site['id'] . "'";

										if (get_request_var('site_id') == $site['id']) {
											print ' selected';
										}
										print '>' . html_escape($site['name']) . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('Data Collector'); ?>
						</td>
						<td>
							<select id='poller_id'>
								<option value='-1' <?php if (get_request_var('poller_id') == '-1') { ?> selected<?php } ?>><?php print __('Any'); ?></option>
								<?php
								$pollers = db_fetch_assoc('SELECT id, name FROM poller ORDER BY name');

								if (cacti_sizeof($pollers)) {
									foreach ($pollers as $poller) {
										print "<option value='" . $poller['id'] . "'";

										if (get_request_var('poller_id') == $poller['id']) {
											print ' selected';
										}
										print '>' . html_escape($poller['name']) . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('Template'); ?>
						</td>
						<td>
							<select id='host_template_id'>
								<option value='-1' <?php if (get_request_var('host_template_id') == '-1') { ?> selected<?php } ?>><?php print __('Any'); ?></option>
								<option value='0' <?php if (get_request_var('host_template_id') == '0') { ?> selected<?php } ?>><?php print __('None'); ?></option>
								<?php
								$host_templates = db_fetch_assoc('SELECT DISTINCT ht.id, ht.name
									FROM host_template ht
									JOIN host h ON h.host_template_id = ht.id
									ORDER BY ht.name');

								if (cacti_sizeof($host_templates)) {
									foreach ($host_templates as $host_template) {
										print "<option value='" . $host_template['id'] . "'";

										if (get_request_var('host_template_id') == $host_template['id']) {
											print ' selected';
										}
										print '>' . html_escape($host_template['name']) . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('Location'); ?>
						</td>
						<td>
							<select id='location'>
								<option value='-1' <?php if (get_request_var('location') == '-1') { ?> selected<?php } ?>><?php print __('All'); ?></option>
								<?php
								if (get_request_var('site_id') >= '0') {
									$sql_where = 'WHERE site_id = ' . db_qstr(get_request_var('site_id'));
								} else {
									$sql_where = '';
								}

								$locations = db_fetch_assoc("SELECT DISTINCT
									IF(IFNULL(host.location,'') = '', '" . __('Undefined') . "', location) AS location
									FROM host
									$sql_where
									ORDER BY location");

								if (cacti_sizeof($locations)) {
									foreach ($locations as $l) {
										print "<option value='" . $l['location'] . "'";

										if (get_request_var('location') == $l['location']) {
											print ' selected';
										}
										print '>' . html_escape($l['location']) . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __('Go'); ?>' title='<?php print __esc('Set/Refresh Filters'); ?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __('Clear'); ?>' title='<?php print __esc('Clear Filters'); ?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='export' value='<?php print __('Export'); ?>' title='<?php print __esc('Export Devices'); ?>'>
							</span>
						</td>
					</tr>
				</table>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search'); ?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter'); ?>'>
						</td>
						<td>
							<?php print __('Status'); ?>
						</td>
						<td>
							<select id='host_status'>
								<option value='-1' <?php if (get_request_var('host_status') == '-1') { ?> selected<?php } ?>><?php print __('Any'); ?></option>
								<option value='-3' <?php if (get_request_var('host_status') == '-3') { ?> selected<?php } ?>><?php print __('Enabled'); ?></option>
								<option value='-2' <?php if (get_request_var('host_status') == '-2') { ?> selected<?php } ?>><?php print __('Disabled'); ?></option>
								<option value='-4' <?php if (get_request_var('host_status') == '-4') { ?> selected<?php } ?>><?php print __('Not Up'); ?></option>
								<option value='3' <?php if (get_request_var('host_status') == '3') { ?> selected<?php } ?>><?php print __('Up'); ?></option>
								<option value='1' <?php if (get_request_var('host_status') == '1') { ?> selected<?php } ?>><?php print __('Down'); ?></option>
								<option value='2' <?php if (get_request_var('host_status') == '2') { ?> selected<?php } ?>><?php print __('Recovering'); ?></option>
								<option value='0' <?php if (get_request_var('host_status') == '0') { ?> selected<?php } ?>><?php print __('Unknown'); ?></option>
							</select>
						</td>
						<td>
							<?php print __('Service Check'); ?>
						</td>
						<td>
							<select id='availability_method'>
								<option value='-1' <?php if (get_request_var('host_status') == '-1') { ?> selected<?php } ?>><?php print __('Any'); ?></option>
								<?php
								if (get_request_var('host_template_id') > 0) {
									$sql_where = 'WHERE host_template_id = ' . get_request_var('host_template_id');
								} else {
									$sql_where = '';
								}

								$options = array_rekey(
									db_fetch_assoc("SELECT DISTINCT availability_method AS id FROM host $sql_where"),
									'id', 'id'
								);

								if (cacti_sizeof($options)) {
									foreach ($options as $option) {
										print "<option value='" . $option . "'";

										if (get_request_var('availability_method') == $option) {
											print ' selected';
										}
										print '>' . html_escape($availability_options[$option]) . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('Devices'); ?>
						</td>
						<td>
							<select id='rows'>
								<option value='-1' <?php print(get_request_var('rows') == '-1' ? ' selected>' : '>') . __('Default'); ?></option>
									<?php
									if (cacti_sizeof($item_rows)) {
										foreach ($item_rows as $key => $value) {
											print "<option value='" . $key . "'";

											if (get_request_var('rows') == $key) {
												print ' selected';
											}
											print '>' . html_escape($value) . '</option>';
										}
									}
								?>
							</select>
						</td>
						<?php api_plugin_hook('device_filter_end'); ?>
					</tr>
				</table>
			</form>
			<script type='text/javascript'>
				function applyFilter() {
					strURL = 'host.php';
					strURL += '?host_status=' + $('#host_status').val();
					strURL += '&availability_method=' + $('#availability_method').val();
					strURL += '&host_template_id=' + $('#host_template_id').val();
					strURL += '&site_id=' + $('#site_id').val();
					strURL += '&poller_id=' + $('#poller_id').val();
					strURL += '&location=' + $('#location').val();
					strURL += '&rows=' + $('#rows').val();
					strURL += '&filter=' + $('#filter').val();
					loadUrl({
						url: strURL
					});
				}

				function clearFilter() {
					strURL = 'host.php?clear=1';
					loadUrl({
						url: strURL
					});
				}

				function exportRecords() {
					strURL = 'host.php?action=export';
					document.location = strURL;
					Pace.stop();
				}

				$(function() {
					$('#rows, #site_id, #poller_id, #location, #host_template_id, #host_status, #availability_method').change(function() {
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
		</td>
	</tr>
	<?php

	html_end_box();

	$display_text = array(
		'description' => array(
			'display' => __('Device Description'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name by which this Device will be referred to.')
		),
		'hostname' => array(
			'display' => __('Hostname'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('Either an IP address, or hostname.  If a hostname, it must be resolvable by either DNS, or from your hosts file.')
		),
		'id' => array(
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal database ID for this Device.  Useful when performing automation or debugging.')
		),
		'device_threads' => array(
			'display' => __('Threads'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The number of threads to use to collect information for this Device.  Applies to spine only.')
		),
		'graphs' => array(
			'display' => __('Graphs'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The total number of Graphs generated from this Device.')
		),
		'data_sources' => array(
			'display' => __('Data Sources'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The total number of Data Sources generated from this Device.')
		),
		'status' => array(
			'display' => __('Status'),
			'align'   => 'center',
			'sort'    => 'ASC',
			'tip'     => __('The monitoring status of the Device based upon ping results.  If this Device is a special type Device, by using the hostname "localhost", or due to the setting to not perform an Availability Check, it will always remain Up.  When using cmd.php data collector, a Device with no Graphs, is not pinged by the data collector and will remain in an "Unknown" state.')
		),
		'site' => array(
			'display' => __('Site'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The site associated to this Device'),
		),
		'availability_method' => array(
			'display' => __('Service Check'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The Availability/Reachability method used to communicate with the device.  In some cases, the Availability/Reachability method will be \'none\', which is not uncommon for some devices'),
		),
		'instate' => array(
			'display' => __('In State'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The amount of time that this Device has been in its current state.')
		),
		'snmp_sysUpTimeInstance' => array(
			'display' => __('Uptime'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The current amount of time that the host has been up.')
		),
		'polling_time' => array(
			'display' => __('Poll Time'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The amount of time it takes to collect data from this Device.')
		),
		'cur_time' => array(
			'display' => __('Current (ms)'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The current ping time in milliseconds to reach the Device.')
		),
		'avg_time' => array(
			'display' => __('Average (ms)'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The average ping time in milliseconds to reach the Device since the counters were cleared for this Device.')
		),
		'availability' => array(
			'display' => __('Availability'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The availability percentage based upon ping results since the counters were cleared for this Device.')
		),
		'created' => array(
			'display' => __('Create Date'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The Date that the Device was added to Cacti.')
		)
	);

	$display_text_size = sizeof($display_text);
	$display_text      = api_plugin_hook_function('device_display_text', $display_text);

	$hosts = get_device_records($total_rows, $rows);

	$nav = html_nav_bar('host.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Devices'), 'page', 'main');

	form_start('host.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (sizeof($display_text) != $display_text_size && cacti_sizeof($hosts)) { //display_text changed
		api_plugin_hook_function('device_table_replace', $hosts);
	} elseif (cacti_sizeof($hosts)) {
		foreach ($hosts as $host) {
			if (
				$host['disabled'] == '' &&
				($host['status'] == HOST_RECOVERING || $host['status'] == HOST_UP) &&
				($host['availability_method'] != AVAIL_NONE && $host['availability_method'] != AVAIL_PING)
			) {
				$uptime    = get_uptime($host);
			} else {
				$uptime    = __('N/A');
			}

			$sites_url       = CACTI_PATH_URL . 'sites.php?action=edit&id=' . $host['site_id'];
			$graphs_url      = CACTI_PATH_URL . 'graphs.php?reset=1&host_id=' . $host['id'];
			$data_source_url = CACTI_PATH_URL . 'data_sources.php?reset=1&host_id=' . $host['id'];

			if (empty($host['graphs'])) {
				$host['graphs'] = 0;
			}

			if (empty($host['data_sources'])) {
				$host['data_sources'] = 0;
			}

			form_alternate_row('line' . $host['id'], true);
			form_selectable_cell(filter_value($host['description'], get_request_var('filter'), 'host.php?action=edit&id=' . $host['id']), $host['id']);
			form_selectable_cell(filter_value($host['hostname'], get_request_var('filter')), $host['id']);
			form_selectable_cell(filter_value($host['id'], get_request_var('filter')), $host['id'], '', 'right');
			form_selectable_cell($host['device_threads'], $host['id'], '', 'right');
			form_selectable_cell('<a class="linkEditMain" href="' . $graphs_url . '">' . number_format_i18n($host['graphs'], '-1') . '</a>', $host['id'], '', 'right');
			form_selectable_cell('<a class="linkEditMain" href="' . $data_source_url . '">' . number_format_i18n($host['data_sources'], '-1') . '</a>', $host['id'], '', 'right');
			form_selectable_cell(get_colored_device_status(($host['disabled'] == 'on' ? true : false), $host['status']), $host['id'], '', 'center');
			form_selectable_cell('<a class="linkEditMain" href="' . $sites_url . '">' . get_colored_site_status(($host['site_disabled'] == 'on' ? true : false), $host['site_name']) .'</a>', $host['id'], '', '');
			form_selectable_cell($availability_options[$host['availability_method']], $host['id'], '', 'right');
			form_selectable_cell(get_timeinstate($host), $host['id'], '', 'right');
			form_selectable_cell($uptime, $host['id'], '', 'right');
			form_selectable_cell(round($host['polling_time'], 2), $host['id'], '', 'right');
			form_selectable_cell(round(($host['cur_time']), 2), $host['id'], '', 'right');
			form_selectable_cell(round(($host['avg_time']), 2), $host['id'], '', 'right');
			form_selectable_cell(round($host['availability'], 2) . ' %', $host['id'], '', 'right');
			form_selectable_cell($host['created'] == '' ? __('Unknown') : substr($host['created'], 0, 10), $host['id'], '', 'right');
			form_checkbox_cell($host['description'], $host['id']);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Devices Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($hosts)) {
		print $nav;
	}

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();

	api_plugin_hook('device_table_bottom');
}
