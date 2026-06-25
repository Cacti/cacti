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
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/api_device.php');
require_once(CACTI_PATH_LIBRARY . '/api_graph.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/html_form_template.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');
require_once(CACTI_PATH_LIBRARY . '/template.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

$actions = [
	1 => __('Delete'),
	2 => __('Disable'),
	3 => __('Enable'),
	4 => __('Change Device'),
	5 => __('Reapply Suggested Names'),
	6 => __('Change Data Source Profile')
];

$actions = api_plugin_hook_function('data_source_action_array', $actions);

// set default action
set_default_action();

switch (grv('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'rrd_add':
		ds_rrd_add();

		break;
	case 'rrd_remove':
		ds_rrd_remove();

		break;
	case 'data_edit':
		top_header();

		data_edit();

		bottom_footer();

		break;
	case 'ds_disable':
		ds_disable();

		break;
	case 'ds_enable':
		ds_enable();

		break;
	case 'ds_edit':
		ds_edit();

		break;
	case 'ajax_hosts':
		$sql_where = '';

		if (gfrv('site_id') > 0) {
			$sql_where = 'site_id = ' . gfrv('site_id');
		}

		get_allowed_ajax_hosts(true, true, $sql_where);

		break;
	case 'ajax_hosts_noany':
		$sql_where = '';

		if (gfrv('site_id') > 0) {
			$sql_where = 'site_id = ' . gfrv('site_id');
		}

		get_allowed_ajax_hosts(false, true, $sql_where);

		break;
	default:
		top_header();

		data_sources();

		bottom_footer();

		break;
}

function form_save() : void {
	if ((isrv('save_component_data_source_new')) && (!ierv('data_template_id'))) {
		$save['id']               = gfrv('local_data_id');
		$save['host_id']          = gfrv('host_id');
		$save['data_template_id'] = gfrv('data_template_id');

		$local_data_id = sql_save($save, 'data_local');

		change_data_template($local_data_id, grv('data_template_id'));

		// update the title cache
		update_data_source_title_cache($local_data_id);

		// update host data
		if (!ierv('host_id')) {
			push_out_host(grv('host_id'), $local_data_id);
		}

		if (empty($save['id'])) {
			/**
			 * Save the last time a data source was created/updated
			 * for Caching.
			 */
			set_config_option('time_last_change_data_source', time());
		}
	}

	if ((isrv('save_component_data')) && (!is_error_message())) {
		// ================= input validation =================
		gfrv('data_template_data_id');
		// ====================================================

		// ok, first pull out all 'input' values so we know how much to save
		$input_fields = db_fetch_assoc_prepared("SELECT dtd.data_input_id, dl.host_id, dif.id, dif.input_output,
			dif.data_name, dif.regexp_match, dif.allow_nulls, dif.type_code, dtd.data_template_id, dl.id AS local_data_id
			FROM data_template_data AS dtd
			LEFT JOIN data_input_fields AS dif
			ON dif.data_input_id = dtd.data_input_id
			LEFT JOIN data_local AS dl
			ON dtd.local_data_id = dl.id
			WHERE dtd.id = ?
			AND dif.input_output='in'",
			[grv('data_template_data_id')]);

		if (cacti_sizeof($input_fields)) {
			foreach ($input_fields as $input_field) {
				if (isrv('value_' . $input_field['id'])) {
					// save the data into the 'data_input_data' table
					$form_value = gnrv('value_' . $input_field['id']);

					// we shouldn't enforce rules on fields the user cannot see (ie. templated ones)
					$data_template_id = db_fetch_cell_prepared('SELECT local_data_template_data_id
						FROM data_template_data
						WHERE id = ?',
						[grv('data_template_data_id')]
					);

					$is_templated = db_fetch_cell_prepared('SELECT t_value
						FROM data_input_data
						WHERE data_input_field_id = ?
						AND data_template_data_id = ?',
						[$input_field['id'], $data_template_id]
					);

					if ($is_templated == '') {
						$allow_nulls = true;
					} elseif ($input_field['allow_nulls'] == 'on') {
						$allow_nulls = true;
					} elseif (empty($input_field['allow_nulls'])) {
						$allow_nulls = false;
					} else {
						$allow_nulls = true;
					}

					// run regexp match on input string
					$form_value = form_input_validate($form_value, 'value_' . $input_field['id'], $input_field['regexp_match'], $allow_nulls, 3);

					if (is_error_message() == false) {
						db_execute_prepared("REPLACE INTO data_input_data
							(data_input_field_id, data_template_data_id, data_template_id, local_data_id, host_id, t_value, value)
							VALUES
							(?, ?, ?, ?, ?, '', ?)",
							[
								$input_field['id'],
								grv('data_template_data_id'),
								$input_field['data_template_id'],
								$input_field['local_data_id'],
								$input_field['host_id'],
								$form_value
							]
						);
					}
				}
			}
		}
	}

	if ((isrv('save_component_data_source')) && (!is_error_message())) {
		// ================= input validation =================
		gfrv('current_rrd');
		gfrv('rrd_step');
		gfrv('data_input_id');
		gfrv('data_source_profile_id');
		gfrv('host_id');
		gfrv('_host_id');
		gfrv('_data_template_id');
		// ====================================================

		$save1['id']               = gfrv('local_data_id');
		$save1['data_template_id'] = gfrv('data_template_id');
		$save1['host_id']          = gfrv('host_id');

		$save2['id']                          = gfrv('data_template_data_id');
		$save2['local_data_template_data_id'] = gfrv('local_data_template_data_id');
		$save2['data_template_id']            = gfrv('data_template_id');
		$save2['data_input_id']               = form_input_validate(grv('data_input_id'), 'data_input_id', '^[0-9]+$', true, 3);
		$save2['name']                        = form_input_validate(gnrv('name'), 'name', '', false, 3);
		$save2['data_source_path']            = form_input_validate(gnrv('data_source_path'), 'data_source_path', '', true, 3);
		$save2['active']                      = form_input_validate((isrv('active') ? gnrv('active') : ''), 'active', '', true, 3);
		$save2['data_source_profile_id']      = form_input_validate(grv('data_source_profile_id'), 'data_source_profile_id', '^[0-9]+$', false, 3);
		$save2['rrd_step']                    = form_input_validate(grv('rrd_step'), 'rrd_step', '^[0-9]+$', false, 3);

		if (is_error_message() == false) {
			$local_data_id = sql_save($save1, 'data_local');

			$save2['local_data_id'] = $local_data_id;
			$data_template_data_id  = sql_save($save2, 'data_template_data');

			if ($data_template_data_id) {
				raise_message(1);

				if (empty($save['id'])) {
					/**
					 * Save the last time a data source was created/updated
					 * for Caching.
					 */
					set_config_option('time_last_change_data_source', time());
				}
			} else {
				raise_message(2);
			}
		}

		if (is_error_message() == false) {
			/* if this is a new data source and a template has been selected, skip item creation this time
			otherwise it throws off the template creation because of the NULL data */
			if (!ierv('local_data_id') || ierv('data_template_id')) {
				/* if no template was set before the save, there will be only one data source item to save;
				otherwise there might be >1 */
				if (ierv('_data_template_id')) {
					$rrds[0]['id'] = gnrv('current_rrd');
				} else {
					$rrds = db_fetch_assoc_prepared('SELECT id
						FROM data_template_rrd
						WHERE local_data_id = ?',
						[gfrv('local_data_id')]);
				}

				if (cacti_sizeof($rrds)) {
					foreach ($rrds as $rrd) {
						if (ierv('_data_template_id')) {
							$name_modifier = '';
						} else {
							$name_modifier = '_' . $rrd['id'];
						}

						$save3['id']            = $rrd['id'];
						$save3['local_data_id'] = $local_data_id;

						$save3['local_data_template_rrd_id'] = db_fetch_cell_prepared('SELECT local_data_template_rrd_id
							FROM data_template_rrd
							WHERE id = ?',
							[$rrd['id']]);

						$save3['data_template_id'] = gfrv('data_template_id');

						$save3['rrd_maximum'] = form_input_validate(gnrv("rrd_maximum$name_modifier"), "rrd_maximum$name_modifier", '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?|U|\|query_ifSpeed\||\|query_ifHighSpeed\|)\z', false, 3);

						$save3['rrd_minimum'] = form_input_validate(gnrv("rrd_minimum$name_modifier"), "rrd_minimum$name_modifier", '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?|U|\|query_ifSpeed\||\|query_ifHighSpeed\|)\z', false, 3);

						$save3['rrd_heartbeat'] = form_input_validate(gnrv("rrd_heartbeat$name_modifier"), "rrd_heartbeat$name_modifier", '^[0-9]+$', false, 3);

						$save3['data_source_type_id'] = form_input_validate(gnrv("data_source_type_id$name_modifier"), "data_source_type_id$name_modifier", '^[0-9]+$', false, 3);

						$save3['data_source_name'] = form_input_validate(gnrv("data_source_name$name_modifier"), "data_source_name$name_modifier", '^[a-zA-Z0-9_-]{1,19}$', false, 3);

						$save3['data_input_field_id'] = form_input_validate((isrv("data_input_field_id$name_modifier") ? gnrv("data_input_field_id$name_modifier") : '0'), "data_input_field_id$name_modifier", '', true, 3);

						if ($save3['rrd_minimum'] != 'U' && $save3['rrd_maximum'] != 'U') {
							if ($save3['rrd_minimum'] >= $save3['rrd_maximum']) {
								raise_message(43);

								$_SESSION[SESS_ERROR_FIELDS]['rrd_maximum'] = 'rrd_maximum';

								header('Location: data_sources.php?action=ds_edit&id=' . (empty($local_data_id) ? gfrv('local_data_id') : $local_data_id) . '&host_id=' . grv('host_id') . '&view_rrd=' . (isrv('current_rrd') ? gnrv('current_rrd') : '0'));

								exit;
							}
						}

						$data_template_rrd_id = sql_save($save3, 'data_template_rrd');

						if ($data_template_rrd_id) {
							raise_message(1);
						} else {
							raise_message(2);
						}
					}
				}
			}
		}

		if (is_error_message() == false) {
			if (grv('data_template_id') != grv('_data_template_id')) {
				// update all necessary template information
				change_data_template($local_data_id, grv('data_template_id'));
			} elseif (!ierv('data_template_id')) {
				update_data_source_data_query_cache($local_data_id);
			}

			if (grv('host_id') != grv('_host_id')) {
				// push out all necessary host information
				push_out_host(grv('host_id'), $local_data_id);

				// reset current host for display purposes
				$_SESSION['sess_data_source_current_host_id'] = grv('host_id');
			}

			// if no data source path has been entered, generate one
			if (ierv('data_source_path')) {
				generate_data_source_path($local_data_id);
			}

			// update the title cache
			update_data_source_title_cache($local_data_id);
		}
	}

	// update the poller cache last to make sure everything is fresh
	if ((!is_error_message()) && (!empty($local_data_id))) {
		update_poller_cache($local_data_id, true);
	}

	if (isrv('save_component_data_source_new') && ierv('data_template_id')) {
		header('Location: data_sources.php?action=ds_edit&host_id=' . grv('host_id') . '&new=1');
	} elseif ((is_error_message()) || (gfrv('data_template_id') != gfrv('_data_template_id')) || (gfrv('data_input_id') != gfrv('_data_input_id')) || (gfrv('host_id') != gfrv('_host_id'))) {
		header('Location: data_sources.php?action=ds_edit&id=' . (empty($local_data_id) ? gfrv('local_data_id') : $local_data_id) . '&host_id=' . grv('host_id') . '&view_rrd=' . (isrv('current_rrd') ? gnrv('current_rrd') : '0'));
	} else {
		header('Location: data_sources.php');
	}
}

function form_actions() : void {
	global $actions;

	// ================= input validation =================
	gfrv('drp_action', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-zA-Z0-9_]+)$/']]);
	// ====================================================

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == '1') { // delete
				if (!isrv('delete_type')) {
					srv('delete_type', 1);
				} else {
					gfrv('delete_type');
				}

				switch (grv('delete_type')) {
					case '2': // delete all graph items tied to this data source
						$data_template_rrds = array_rekey(db_fetch_assoc('SELECT id
							FROM data_template_rrd
							WHERE ' . array_to_sql_or($selected_items, 'local_data_id')), 'id', 'id');

						$poller_ids = db_fetch_assoc('SELECT DISTINCT poller_id
							FROM host AS h
							INNER JOIN data_local AS dl
							ON dl.host_id=h.id
							WHERE poller_id > 1
							AND id IN (' . implode(', ', $selected_items) . ')');

						api_plugin_hook_function('graph_items_remove', $data_template_rrds);

						// loop through each data source item
						if (cacti_sizeof($data_template_rrds) > 0) {
							db_execute('DELETE FROM graph_templates_item
								WHERE task_item_id IN (' . implode(',', $data_template_rrds) . ')
								AND local_graph_id > 0');

							if (cacti_sizeof($poller_ids)) {
								foreach ($poller_ids as $poller_id) {
									if (($rcnn_id = poller_push_to_remote_db_connect($poller_id, true)) !== false) {
										db_execute('DELETE FROM graph_templates_item
											WHERE task_item_id IN (' . implode(',', $data_template_rrds) . ')
											AND local_graph_id > 0', true, $rcnn_id);
									}
								}
							}
						}

						break;
					case '3': // delete all graphs tied to this data source
						$graphs = array_rekey(db_fetch_assoc('SELECT
							graph_templates_graph.local_graph_id
							FROM (data_template_rrd,graph_templates_item,graph_templates_graph)
							WHERE graph_templates_item.task_item_id=data_template_rrd.id
							AND graph_templates_item.local_graph_id=graph_templates_graph.local_graph_id
							AND ' . array_to_sql_or($selected_items, 'data_template_rrd.local_data_id') . '
							AND graph_templates_graph.local_graph_id > 0
							GROUP BY graph_templates_graph.local_graph_id'), 'local_graph_id', 'local_graph_id');

						if (cacti_sizeof($graphs) > 0) {
							api_graph_remove_multi($graphs);
						}

						break;
				}

				api_data_source_remove_multi($selected_items);
			} elseif (gnrv('drp_action') == '2') { // data source disable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					api_data_source_disable($selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '3') { // data source enable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					api_data_source_enable($selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '4') { // change host
				gfrv('host_id');

				api_data_source_change_host($selected_items, grv('host_id'));
			} elseif (gnrv('drp_action') == '5') { // reapply suggested data source naming
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					api_reapply_suggested_data_source_data($selected_items[$i]);
					update_data_source_title_cache($selected_items[$i]);
				}
			} elseif (get_nfilter_request_var('drp_action') == '6') { // change data source profile
				get_filter_request_var('data_source_profile_id');

				$new_profile = db_fetch_row_prepared('SELECT * FROM data_source_profiles WHERE id = ?',
					[get_request_var('data_source_profile_id')]);

				if (cacti_sizeof($new_profile)) {
					$rrd_changes  = 0;
					$remote_conns = [];

					foreach ($selected_items as $local_data_id) {
						$data = db_fetch_row_prepared('SELECT dl.host_id, h.poller_id
							FROM host AS h
							INNER JOIN data_local AS dl
							ON dl.host_id = h.id
							AND dl.id = ?',
							[$local_data_id]);

						if ($data['poller_id'] > 1) {
							$remote_conns[$data['poller_id']] = poller_push_to_remote_db_connect($data['host_id']);
						}

						// Get current step value
						$current_step = db_fetch_cell_prepared('SELECT rrd_step
							FROM data_template_data
							WHERE local_data_id = ?',
							[$local_data_id]);

						// Update all database tables
						db_execute_prepared('UPDATE data_template_data
							SET data_source_profile_id = ?, rrd_step = ?
							WHERE local_data_id = ?',
							[get_request_var('data_source_profile_id'), $new_profile['step'], $local_data_id]
						);

						db_execute_prepared('UPDATE data_template_rrd
							SET rrd_heartbeat = ?
							WHERE local_data_id = ?',
							[$new_profile['heartbeat'], $local_data_id]
						);

						db_execute_prepared('UPDATE poller_item
							SET rrd_step = ?
							WHERE local_data_id = ?',
							[$new_profile['step'], $local_data_id]
						);

						if ($data['poller_id'] > 1 && $remote_conns[$data['poller_id']] !== false) {
							$conn = $remote_conns[$data['poller_id']];

							db_execute_prepared('UPDATE data_template_data
								SET data_source_profile_id = ?, rrd_step = ?
								WHERE local_data_id = ?',
								[get_request_var('data_source_profile_id'), $new_profile['step'], $local_data_id], true, $conn
							);

							db_execute_prepared('UPDATE data_template_rrd
								SET rrd_heartbeat = ?
								WHERE local_data_id = ?',
								[$new_profile['heartbeat'], $local_data_id], true, $conn
							);

							db_execute_prepared('UPDATE poller_item
								SET rrd_step = ?
								WHERE local_data_id = ?',
								[$new_profile['step'], $local_data_id], true, $conn
							);
						}

						update_poller_cache($local_data_id, true);

						// Handle RRD file if step changed
						if ($current_step != $new_profile['step']) {
							$rrd_path = get_data_source_path($local_data_id, true);

							if (file_exists($rrd_path)) {
								// Backup with timestamp and delete
								$backup = $rrd_path . '.bak_' . date('Ymd-His');

								if (copy($rrd_path, $backup)) {
									unlink($rrd_path);
									$rrd_changes++;
									cacti_log("RRD backed up and removed: $rrd_path -> $backup", false, 'DATASOURCE');
								} else {
									// Log a warning so administrators are aware the backup did not succeed
									cacti_log("WARNING: Failed to backup RRD file '$rrd_path' to '$backup'", false, 'DATASOURCE');
								}
							}
						}
					}

					raise_message(1);

					if ($rrd_changes > 0) {
						$_SESSION['sess_messages']['custom_info'] = [
							'message' => sprintf(__('%d RRD files were backed up and will be recreated with new step value at next polling.'), $rrd_changes),
							'type'    => 'info'
						];
					}
				} else {
					raise_message(2);
				}
			} else {
				api_plugin_hook_function('data_source_action_execute', gnrv('drp_action'));
			}
		}

		// update snmpcache
		snmpagent_data_source_action_bottom([gnrv('drp_action'), $selected_items]);

		api_plugin_hook_function('data_source_action_bottom', [gnrv('drp_action'), $selected_items]);

		header('Location: data_sources.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		// some global defaults
		$graphs = [];
		$flist  = '';
		$hosts  = array_rekey(
			db_fetch_assoc("SELECT id, CONCAT_WS('',description,' (',hostname,')') AS name
				FROM host
				ORDER BY description, hostname"),
			'id', 'name'
		);

		// loop through each of the graphs selected on the previous page and get more info about them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$ilist .= '<li>' . htmle(get_data_source_title(intval($matches[1]))) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		if (cacti_sizeof($iarray)) {
			if (gnrv('drp_action') == '1') { // delete
				$graphs = db_fetch_assoc('SELECT
					graph_templates_graph.local_graph_id,
					graph_templates_graph.title_cache
					FROM (data_template_rrd,graph_templates_item,graph_templates_graph)
					WHERE graph_templates_item.task_item_id=data_template_rrd.id
					AND graph_templates_item.local_graph_id=graph_templates_graph.local_graph_id
					AND ' . array_to_sql_or($iarray, 'data_template_rrd.local_data_id') . '
					AND graph_templates_graph.local_graph_id > 0
					GROUP BY graph_templates_graph.local_graph_id
					ORDER BY graph_templates_graph.title_cache');

				if (cacti_sizeof($graphs)) {
					foreach ($graphs as $g) {
						$flist .= '<li>' . htmle($g['title_cache']) . '</li>';
					}
				}
			}
		}

		// For use by plugins
		$save['drp_action'] = 1;
		$save['ds_list']    = $ilist;
		$save['ds_array']   = $iarray;

		$form_data = [
			'general' => [
				'page'       => 'data_sources.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage'  => __('Click \'Continue\' to Delete the following Data Source.'),
					'pmessage'  => __('Click \'Continue\' to Delete following Data Sources.'),
					'scont'     => __('Delete Data Source'),
					'pcont'     => __('Delete Data Sources'),
					'flist'     => $flist,
					'sfmessage' => __n('The following Graph is using this Data Source.', 'The following Graphs are using this Data Source.', cacti_sizeof($graphs)),
					'pfmessage' => __n('The following Graph is using these Data Sources.', 'The following Graphs are using these Data Sources.', cacti_sizeof($graphs)),
					'extra'     => [
						'delete_type' => [
							'method'  => 'radio_button',
							'options' => [
								'1' => [
									'default' => 3,
									'title'   => __n('Leave the Graph Untouched', 'Leave all Graphs untouched.', cacti_sizeof($graphs))
								],
								'2' => [
									'default' => 3,
									'title'   => __n('Delete all Graph Items that reference this Data Source.', 'Delete all Graph Items that reference these Data Sources', cacti_sizeof($iarray))
								],
								'3' => [
									'default' => 3,
									'title'   => __n('Delete all Graphs that reference this Data Source.', 'Delete all Graphs that reference these Data Sources', cacti_sizeof($iarray))
								]
							]
						]
					]
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Disable the following Data Source.'),
					'pmessage' => __('Click \'Continue\' to Disable following Data Sources.'),
					'scont'    => __('Disable Data Source'),
					'pcont'    => __('Disable Data Sources')
				],
				3 => [
					'smessage' => __('Click \'Continue\' to Enable the following Data Source.'),
					'pmessage' => __('Click \'Continue\' to Enable following Data Sources.'),
					'scont'    => __('Enable Data Source'),
					'pcont'    => __('Enable Data Sources')
				],
				4 => [
					'smessage' => __('Click \'Continue\' to Change the Device for the following Data Source.'),
					'pmessage' => __('Click \'Continue\' to Change the Device for the following Data Sources.'),
					'scont'    => __('Change Device for Data Source'),
					'pcont'    => __('Change Device for Data Sources'),
					'extra'    => [
						'host_id' => [
							'method'  => 'drop_array',
							'title'   => __('New Device'),
							'default' => '',
							'array'   => $hosts
						]
					]
				],
				5 => [
					'smessage' => __('Click \'Continue\' to Reapply Suggested Names the following Data Source.'),
					'pmessage' => __('Click \'Continue\' to Reapply Suggested Names for the following Data Sources.'),
					'scont'    => __('Reapply Suggested Names for Data Source'),
					'pcont'    => __('Reapply Suggested Names for Data Sources')
				],
				6 => [
					'smessage' => __('Click \'Continue\' to Change the Data Source Profile for the following Data Source.'),
					'pmessage' => __('Click \'Continue\' to Change the Data Source Profile for the following Data Sources.'),
					'scont'    => __('Change Data Source Profile'),
					'pcont'    => __('Change Data Source Profiles'),
					'extra'    => [
						'data_source_profile_id' => [
							'method'  => 'drop_sql',
							'title'   => __('New Data Source Profile'),
							'default' => '',
							'sql'     => 'SELECT id, name FROM data_source_profiles ORDER BY name'
						]
					]
				]
			]
		];

		$form_data = api_plugin_hook_function('data_source_confirmation_form', $form_data);

		form_continue_confirmation($form_data, 'data_source_action_prepare', $save);
	}
}

function data_edit(bool $incform = true) : void {
	// ================= input validation =================
	gfrv('id');
	// ====================================================

	if (!ierv('id')) {
		$data = db_fetch_row_prepared('SELECT id, data_input_id, data_template_id, name, local_data_id
			FROM data_template_data
			WHERE local_data_id = ?',
			[grv('id')]);

		$template_data = db_fetch_row_prepared('SELECT id, data_input_id
			FROM data_template_data
			WHERE data_template_id = ?
			AND local_data_id = 0',
			[$data['data_template_id']]);

		$host = db_fetch_row_prepared('SELECT host.id, host.hostname
			FROM (data_local, host)
			WHERE data_local.host_id = host.id
			AND data_local.id = ?',
			[grv('id')]);
	} else {
		$data          = [];
		$host          = [];
		$template_data = [];
	}

	if ($incform) {
		form_start('data_sources.php', 'data_source_edit');
	}

	$i = 0;

	if ($data['data_input_id'] > 0) {
		// get each INPUT field for this data input source
		$fields = db_fetch_assoc_prepared('SELECT *
			FROM data_input_fields
			WHERE data_input_id = ?
			AND input_output = "in"
			ORDER BY name',
			[$data['data_input_id']]
		);

		$data_input_name = db_fetch_cell_prepared('SELECT name
			FROM data_input
			WHERE id = ?',
			[$data['data_input_id']]);

		html_start_box(__('Custom Data [data input: %s]', htmle($data_input_name)), '100%', false, 3, 'center', '');

		// loop through each field found
		if (cacti_sizeof($fields) > 0) {
			foreach ($fields as $field) {
				$data_input_data = db_fetch_row_prepared('SELECT *
					FROM data_input_data
					WHERE data_template_data_id = ?
					AND data_input_field_id = ?',
					[$data['id'], $field['id']]
				);

				if (cacti_sizeof($data_input_data) > 0) {
					$old_value = $data_input_data['value'];
				} else {
					$old_value = '';
				}

				// if data template then get t_value FROM template, else always allow user input
				if (empty($data['data_template_id'])) {
					$can_template = 'on';
				} else {
					$can_template = db_fetch_cell_prepared('SELECT t_value
						FROM data_input_data
						WHERE data_template_data_id = ?
						AND data_input_field_id = ?',
						[$template_data['id'], $field['id']]
					);
				}

				form_alternate_row();

				if ((!empty($host['id'])) && (preg_match('/^' . VALID_HOST_FIELDS . '$/i', $field['type_code']))) {
					print "<td style='width:50%;'><strong>" . htmle($field['name']) . '</strong> ' . __('(From Device: %s)', htmle($host['hostname'])) . '</td>';
					print '<td><em>' . htmle($old_value) . '</em></td>';
				} elseif (empty($can_template)) {
					print "<td style='width:50%;'><strong>" . htmle($field['name']) . '</strong> ' . __('(From Data Template)') . '</td>';
					print '<td><em>' . (empty($old_value) ? __('Nothing Entered') : htmle($old_value)) . '</em></td>';
				} else {
					print "<td style='width:50%;'><strong>" . htmle($field['name']) . '</strong></td>';
					print '<td>';

					draw_custom_data_row('value_' . $field['id'], $field['id'], $data['id'], $old_value);

					print '</td>';
				}

				print '</tr>';

				$i++;
			}
		} else {
			print '<tr class="tableRow odd"><td><em>' . __('No Input Fields for the Selected Data Input Source') . '</em></td></tr>';
		}

		html_end_box();
	}

	if ($incform) {
		form_hidden_box('local_data_id', $data['local_data_id'] ?? 0, '');
		form_hidden_box('data_template_data_id', $data['id'] ?? 0, '');
	}

	form_hidden_box('save_component_data', '1', '');
}

function ds_rrd_remove() : void {
	// ================= input validation =================
	gfrv('id');
	// ====================================================

	db_execute_prepared('DELETE FROM data_template_rrd
		WHERE id = ?',
		[grv('id')]);

	db_execute_prepared('UPDATE graph_templates_item
		SET task_item_id = 0
		WHERE task_item_id = ?',
		[grv('id')]);

	header('Location: data_sources.php?action=ds_edit&id=' . grv('local_data_id'));
}

function ds_rrd_add() : void {
	// ================= input validation =================
	gfrv('id');
	// ====================================================

	db_execute_prepared("INSERT INTO data_template_rrd
		(local_data_id, rrd_maximum, rrd_minimum, rrd_heartbeat, data_source_type_id, data_source_name)
		VALUES (?, 100, 0, 600, 1, 'ds')",
		[grv('id')]);

	$data_template_rrd_id = db_fetch_insert_id();

	header('Location: data_sources.php?action=ds_edit&id=' . grv('id') . "&view_rrd=$data_template_rrd_id");
}

function ds_disable() : void {
	// ================= input validation =================
	gfrv('id');
	// ====================================================

	api_data_source_disable(grv('id'));
	header('Location: data_sources.php?action=ds_edit&id=' . grv('id'));
}

function ds_enable() : void {
	// ================= input validation =================
	gfrv('id');
	// ====================================================

	api_data_source_enable(grv('id'));
	header('Location: data_sources.php?action=ds_edit&id=' . grv('id'));
}

function ds_edit() : void {
	global $struct_data_source, $struct_data_source_item;

	// ================= input validation =================
	gfrv('id');
	gfrv('host_id');
	// ====================================================

	api_plugin_hook('data_source_edit_top');

	$use_data_template = true;
	$data_template     = [];

	if (!ierv('id')) {
		$data_local = db_fetch_row_prepared('SELECT host_id, data_template_id
			FROM data_local
			WHERE id = ?',
			[grv('id')]);

		$data = db_fetch_row_prepared('SELECT *
			FROM data_template_data
			WHERE local_data_id = ?',
			[grv('id')]);

		if (isset($data_local['data_template_id']) && $data_local['data_template_id'] >= 0) {
			$data_template = db_fetch_row_prepared('SELECT id, name
				FROM data_template
				WHERE id = ?',
				[$data_local['data_template_id']]);

			$data_template_data = db_fetch_row_prepared('SELECT *
				FROM data_template_data
				WHERE data_template_id = ?
				AND local_data_id = 0',
				[$data_local['data_template_id']]);
		} else {
			raise_message(11);
			header('Location: data_sources.php');

			exit;
		}

		$header_label = __esc('Data Template Selection [edit: %s]', get_data_source_title(grv('id')));

		if (empty($data_local['data_template_id'])) {
			$use_data_template = false;
		}
	} else {
		$header_label = __('Data Template Selection [new]');

		$use_data_template = false;
	}

	// handle debug mode
	if (isrv('debug')) {
		if (gnrv('debug') == '0') {
			kill_session_var('ds_debug_mode');
		} elseif (gnrv('debug') == '1') {
			$_SESSION['ds_debug_mode'] = true;
		}
	}

	// handle debug mode
	if (isrv('info')) {
		if (gnrv('info') == '0') {
			kill_session_var('ds_info_mode');
		} elseif (gnrv('info') == '1') {
			$_SESSION['ds_info_mode'] = true;
		}
	}

	if (cacti_sizeof($data_template)) {
		$data_sources = db_fetch_cell_prepared('SELECT
			GROUP_CONCAT(DISTINCT data_source_name ORDER BY data_source_name) AS data_source_names
			FROM data_template_rrd
			WHERE data_template_id = ?
			GROUP BY data_template_id
			ORDER BY data_source_names',
			[$data_template['id']]
		);

		$dts = db_fetch_assoc_prepared('SELECT data_template_id,
			GROUP_CONCAT(DISTINCT data_source_name ORDER BY data_source_name) AS data_source_names
			FROM data_template_rrd
			WHERE local_data_id=0
			GROUP BY data_template_id
			HAVING data_source_names = ?',
			[$data_sources]
		);

		if (cacti_sizeof($dts)) {
			foreach ($dts as $dtid) {
				$dtids[] = $dtid['data_template_id'];
			}

			$dtsql = 'SELECT id, name FROM data_template WHERE id IN(' . implode(',', $dtids) . ') ORDER BY name'; // @phpstan-ignore-line
		} else {
			$dtsql = 'SELECT id, name FROM data_template ORDER BY name';
		}
	} else {
		$dtsql = 'SELECT id, name FROM data_template ORDER BY name';
	}

	if (grv('host_id') > 0) {
		$hostDescription = db_fetch_cell_prepared('SELECT description
			FROM host
			WHERE id = ?',
			[grv('host_id')]
		);
	} elseif (isset($data_local['host_id'])) {
		$hostDescription = db_fetch_cell_prepared('SELECT description
			FROM host
			WHERE id = ?',
			[$data_local['host_id']]
		);
	} else {
		$hostDescription = '';
	}

	if (!ierv('id')) {
		$ins_buttons = [];

		$local_graph_ids = db_fetch_assoc_prepared('SELECT DISTINCT local_graph_id
			FROM graph_templates_item AS gti
			INNER JOIN data_template_rrd AS dtr
			ON dtr.id = gti.task_item_id
			WHERE local_data_id = ?',
			[grv('id')]);

		if (isset($_SESSION['ds_debug_mode'])) {
			$debug_message = __('Turn Off Data Source Debug Mode');
			$debug         = true;
		} else {
			$debug_message = __('Turn On Data Source Debug Mode');
			$debug         = false;
		}

		$debug_url = 'data_sources.php?action=ds_edit&id=' . (isrv('id') ? grv('id') : '0') . '&debug=' . (isset($_SESSION['ds_debug_mode']) ? '0' : '1');

		if (isset($_SESSION['ds_info_mode'])) {
			$info_message = __('Turn Off Data Source Info Mode');
			$info_on      = true;
		} else {
			$info_message =  __('Turn On Data Source Info Mode');
			$info_on      = false;
		}

		$info_url = 'data_sources.php?action=ds_edit&id=' . (isrv('id') ? grv('id') : '0') . '&info=' . (isset($_SESSION['ds_info_mode']) ? '0' : '1');

		$filters = [
			'links' => [
				[
					'display' => $debug_message,
					'url'     => $debug_url,
					'class'   => ($debug ? 'ti ti-bug disableDebug' : 'ti ti-bug enableDebug')
				],
				[
					'display' => $info_message,
					'url'     => $info_url,
					'class'   => ($info_on ? 'ti ti-qrcode disableDebug' : 'ti ti-qrcode editTemplate')
				],
			]
		];

		if (cacti_sizeof($local_graph_ids)) {
			foreach ($local_graph_ids as $id) {
				$name = db_fetch_cell_prepared('SELECT title_cache
					FROM graph_templates_graph
					WHERE local_graph_id = ?',
					[$id['local_graph_id']]);

				$ins_buttons[] = [
					'display' => __esc('Edit Graph: \'%s\'', $name),
					'url'     => 'graphs.php?action=graph_edit&id=' . $id['local_graph_id'],
					'class'   => 'ti ti-chart-area-line-filled editGraph'
				];
			}
		}

		if (!ierv('host_id') || !empty($data_local['host_id'])) {
			$ins_buttons[] = [
				'display' => __('Edit Device'),
				'url'     => 'host.php?action=edit&id=' . ($data_local['host_id'] > 0 ? $data_local['host_id'] : grv('host_id')),
				'class'   => 'ti ti-server editDevice'
			];
		}

		if (!empty($data_template['id'])) {
			$ins_buttons[] = [
				'display' => __('Edit Data Template'),
				'url'     => 'data_templates.php?action=template_edit&id=' . ($data_template['id'] > 0 ? $data_template['id'] : 0),
				'class'   => 'ti ti-copy editTemplate'
			];
		}

		if (cacti_sizeof($ins_buttons)) {
			foreach ($ins_buttons as $button) {
				$filters['links'][] = $button;
			}
		}

		if (isrv('id') && grv('id') > 0) {
			$filters['links'][] = [
				'display' => ($data['active'] == 'on' ? __('Disable Data Source') : __('Enable Data Source')),
				'url'     => 'data_sources.php?action=ds_' . ($data['active'] == 'on' ? 'dis' : 'en') . 'able&id=' . grv('id'),
				'class'   => ($data['active'] == 'on' ? 'ti ti-circle-check deviceRecovering' : 'ti ti-circle-check deviceUp')
			];
		}
	} else {
		$filters = [];
	}

	top_header();

	form_start('data_sources.php', 'data_source');

	$pageFilter = new CactiTableFilter($header_label, 'data_sources.php?action=ds_edit&id=' . grv('id'), 'data_source', 'sess_ds_edit', '', '', false);
	$pageFilter->set_filter_array($filters);
	$pageFilter->render();

	html_start_box('', '100%', true, 3, 'center', '');

	$form_array = [
		'data_template_id' => [
			'method'        => 'drop_sql',
			'friendly_name' => __('Data Template'),
			'description'   => __('The name given to this data template.  Please note that you may only change Graph Templates to a 100%$ compatible Graph Template, which means that it includes identical Data Sources.'),
			'value'         => (cacti_sizeof($data_template) ? $data_template['id'] : '0'),
			'none_value'    => (cacti_sizeof($data_template) ? '' : 'None'),
			'sql'           => $dtsql
		],
		'host_id' => [
			'method'        => 'drop_callback',
			'friendly_name' => __('Device'),
			'description'   => __('Choose the Device that this Data Source belongs to.'),
			'none_value'    => __('None'),
			'sql'           => 'SELECT id, description AS name FROM host ORDER BY name',
			'action'        => 'ajax_hosts_noany',
			'id'            => (isset($data_local['host_id']) ? $data_local['host_id'] : 0),
			'value'         => $hostDescription
		],
		'_data_template_id' => [
			'method' => 'hidden',
			'value'  => (isset($data_template['id']) ? $data_template['id'] : '0')
		],
		'_host_id' => [
			'method' => 'hidden',
			'value'  => (isset($data_local['host_id']) ? $data_local['host_id'] : '0')
		],
		'_data_input_id' => [
			'method' => 'hidden',
			'value'  => (isset($data['data_input_id']) ? $data['data_input_id'] : '0')
		],
		'data_template_data_id' => [
			'method' => 'hidden',
			'value'  => (isset($data['id']) ? $data['id'] : '0')
		],
		'local_data_template_data_id' => [
			'method' => 'hidden',
			'value'  => (isset($data['local_data_template_data_id']) ? $data['local_data_template_data_id'] : '0')
		],
		'local_data_id' => [
			'method' => 'hidden',
			'value'  => (isset($data['local_data_id']) ? $data['local_data_id'] : '0')
		],
	];

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => $form_array
		]
	);

	html_end_box(true, true);

	// only display the "inputs" area if we are using a data template for this data source
	if (!empty($data['data_template_id'])) {
		$template_data_rrds = db_fetch_assoc_prepared('SELECT *
			FROM data_template_rrd
			WHERE local_data_id = ?
			ORDER BY data_source_name',
			[grv('id')]);

		html_start_box(__('Supplemental Data Template Data'), '100%', true, 3, 'center', '');

		draw_nontemplated_fields_data_source($data['data_template_id'], $data['local_data_id'], $data, '|field|', __('Data Source Fields'), true, true, 0);
		draw_nontemplated_fields_data_source_item($data['data_template_id'], $template_data_rrds, '|field|_|id|', __('Data Source Item Fields'), true, true, true, 0);
		draw_nontemplated_fields_custom_data($data['id'], 'value_|id|', __('Custom Data'), true, true, 0);

		form_hidden_box('save_component_data','1','');

		html_end_box(true, true);
	}

	if (((isrv('id')) || (isrv('new'))) && (empty($data['data_template_id']))) {
		html_start_box(__('Data Source'), '100%', true, 3, 'center', '');

		$form_array = [];

		foreach ($struct_data_source as $field_name => $field_array) {
			$form_array += [$field_name => $struct_data_source[$field_name]];

			if (($field_array['method'] != 'header') && ($field_array['method'] != 'spacer')) {
				if (!(($use_data_template == false) || (!empty($data_template_data['t_' . $field_name])) || ($field_array['flags'] == 'NOTEMPLATE'))) {
					$form_array[$field_name]['description'] = '';
				}

				$form_array[$field_name]['value']   = (isset($data[$field_name]) ? $data[$field_name] : '');
				$form_array[$field_name]['form_id'] = (empty($data['id']) ? '0' : $data['id']);

				if (!(($use_data_template == false) || (!empty($data_template_data['t_' . $field_name])) || ($field_array['flags'] == 'NOTEMPLATE'))) {
					$form_array[$field_name]['method'] = 'template_' . $form_array[$field_name]['method'];
				}
			}
		}

		draw_edit_form(
			[
				'config' => ['no_form_tag' => true],
				'fields' => inject_form_variables($form_array, (isset($data) ? $data : []))
			]
		);

		html_end_box(true, true);

		// fetch ALL rrd's for this data source
		if (!ierv('id')) {
			$template_data_rrds = db_fetch_assoc_prepared('SELECT id, data_source_name
				FROM data_template_rrd
				WHERE local_data_id = ?
				ORDER BY data_source_name',
				[grv('id')]);
		}

		// select the first "rrd" of this data source by default
		if (ierv('view_rrd')) {
			srv('view_rrd', (isset($template_data_rrds[0]['id']) ? $template_data_rrds[0]['id'] : '0'));
		}

		// get more information about the rrd we chose
		if (!ierv('view_rrd')) {
			$local_data_template_rrd_id = db_fetch_cell_prepared('SELECT local_data_template_rrd_id
				FROM data_template_rrd
				WHERE id = ?',
				[grv('view_rrd')]);

			$rrd = db_fetch_row_prepared('SELECT *
				FROM data_template_rrd
				WHERE id = ?',
				[grv('view_rrd')]);

			$rrd_template = db_fetch_row_prepared('SELECT *
				FROM data_template_rrd
				WHERE id = ?',
				[$local_data_template_rrd_id]);

			$header_label = __('[edit: %s]', $rrd['data_source_name']);
		} else {
			$header_label = '';

			$local_data_template_rrd_id = 0;

			$rrd          = [];
			$rrd_template = [];
		}

		$i = 0;

		if (isset($template_data_rrds)) {
			if (cacti_sizeof($template_data_rrds)) {
				// draw the data source tabs on the top of the page
				print "<div class='tabs' style='float:left;'><nav><ul role='tablist'>";

				foreach ($template_data_rrds as $template_data_rrd) {
					print "<li class='subTab'><a " . (($template_data_rrd['id'] == grv('view_rrd')) ? "class='pic selected'" : "class='pic'") . " href='" . htmle('data_sources.php?action=ds_edit&id=' . grv('id') . '&view_rrd=' . $template_data_rrd['id']) . "'>$i: " . htmle($template_data_rrd['data_source_name']) . '</a>' . ($use_data_template == false ? " <a class='pic deleteMarker ti ti-x' href='" . htmle('data_sources.php?action=rrd_remove&id=' . $template_data_rrd['id'] . '&local_data_id=' . grv('id')) . "' title='" . __esc('Delete') . "'></a>" : '') . '</li>';

					$i++;
				}

				print '</ul></nav></div>';
			} elseif (cacti_sizeof($template_data_rrds) === 1) {
				srv('view_rrd', $template_data_rrds[0]['id']);
			}
		}

		html_start_box('', '100%', true, 3, 'center', '');

		print "<div class='tableHeader'>
			<div class='tableSubHeaderColumn left'>
				" . __esc('Data Source Item %s', $header_label) . "
			</div>
			<div class='tableSubHeaderColumn right'>
				" . ((!ierv('id') && (empty($data_template['id']))) ? "<a class='linkOverDark' href='" . htmle('data_sources.php?action=rrd_add&id=' . grv('id')) . "'>" . __('New') . '</a>&nbsp;' : '') . '
			</div>
		</div>';

		// data input fields list
		if ((empty($data['data_input_id'])) || (db_fetch_cell_prepared('SELECT type_id FROM data_input WHERE id = ?', [$data['data_input_id']]) > '1')) {
			unset($struct_data_source_item['data_input_field_id']);
		} else {
			$struct_data_source_item['data_input_field_id']['sql'] = "SELECT id,CONCAT(data_name,' - ',name) as name FROM data_input_fields WHERE data_input_id=" . $data['data_input_id'] . " and input_output='out' and update_rra='on' order by data_name,name";
		}

		$form_array = [];

		foreach ($struct_data_source_item as $field_name => $field_array) {
			$form_array += [$field_name => $struct_data_source_item[$field_name]];

			if (($field_array['method'] != 'header') && ($field_array['method'] != 'spacer')) {
				if (!(($use_data_template == false) || ($rrd_template['t_' . $field_name] == 'on'))) {
					$form_array[$field_name]['description'] = '';
				}

				$form_array[$field_name]['value'] = $rrd[$field_name] ?? '';

				if (!(($use_data_template == false) || ($rrd_template['t_' . $field_name] == 'on'))) {
					$form_array[$field_name]['method'] = 'template_' . $form_array[$field_name]['method'];
				}
			}
		}

		draw_edit_form(
			[
				'config' => ['no_form_tag' => true],
				'fields' => [
					'data_template_rrd_id' => [
						'method' => 'hidden',
						'value'  => $rrd['id'] ?? 0
					],
					'local_data_template_rrd_id' => [
						'method' => 'hidden',
						'value'  => $rrd['local_data_template_rrd_id'] ?? 0
					]
				] + $form_array
			]
		);

		html_end_box(true, true);

		// data source data goes here
		data_edit(false);

		form_hidden_box('current_rrd', grv('view_rrd'), '0');
	}

	// display the debug mode box if the user wants it
	if ((isset($_SESSION['ds_debug_mode'])) && (isrv('id'))) {
		print "<div class='cactiTable'>";

		print "<div class='tableHeader'>";
		print __('Data Source Debug');
		print '</div>';

		print "<div class='tableRow'>";
		print '<pre>' . htmle(rrdtool_function_create(grv('id'), true)) . '</pre>';
		print '</div>';

		print '</div>';
	}

	// display the debug mode box if the user wants it
	if (isset($_SESSION['ds_info_mode']) && isrv('id')) {
		print "<div class='cactiTable'><div class='tableRow'>";

		$rrd_info = rrdtool_function_info(grv('id'));

		if (cacti_sizeof($rrd_info) && cacti_sizeof($rrd_info['rra'])) {
			$diff = rrdtool_cacti_compare(grv('id'), $rrd_info);

			rrdtool_info2html($rrd_info, $diff);

			if (cacti_sizeof($diff)) {
				html_start_box(__('RRDtool Tune Info'), '100%', false, 3, 'center', '');

				rrdtool_tune($rrd_info['filename'], $diff, true);

				html_end_box();
			}
		}

		print '</div></div>';
	}

	if ((isrv('id')) || (isrv('new'))) {
		form_hidden_box('save_component_data_source','1','');
	} else {
		form_hidden_box('save_component_data_source_new','1','');
	}

	form_save_button('data_sources.php');

	api_plugin_hook('data_source_edit_bottom');

	bottom_footer();
}

function get_poller_interval(int $seconds, int $data_source_profile_id) : string {
	if ($seconds == 0 || $data_source_profile_id == 0) {
		return '<em>' . __('External') . '</em>';
	}

	if ($seconds < 60) {
		return '<em>' . __('%d Seconds', $seconds) . '</em>';
	}

	if ($seconds == 60) {
		return __('1 Minute');
	} else {
		return '<em>' . __('%d Minutes', ($seconds / 60)) . '</em>';
	}
}

function data_sources() : void {
	global $actions, $item_rows, $sampling_intervals;

	draw_data_source_filter(true);

	if (grv('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	if (gfrv('host_id') > 0) {
		$host = db_fetch_row_prepared('SELECT hostname
			FROM host
			WHERE id = ?',
			[grv('host_id')]);
	} else {
		$host = [];
	}

	$sql_where1  = '';
	$sql_where2  = '';
	$sql_params1 = [];
	$sql_params2 = [];

	// form the 'where' clause for our main sql query
	if (grv('rfilter') != '') {
		$sql_where1 = 'WHERE
		(
			dtd.name_cache RLIKE ? OR
			dtd.local_data_id RLIKE ? OR
			dt.name RLIKE ? OR
			dl.id = ?
		)';

		$sql_params1[] = grv('rfilter');
		$sql_params1[] = grv('rfilter');
		$sql_params1[] = grv('rfilter');
		$sql_params1[] = grv('rfilter');
	}

	if (ierv('host_id')) {
		$sql_where1 .= ($sql_where1 != '' ? ' AND' : 'WHERE') . ' (dl.host_id = 0 OR dl.host_id IS NULL)';
		$sql_where2 .= ' AND (gl.host_id = 0 OR gl.host_id IS NULL)';
	} elseif (grv('host_id') > 0) {
		$sql_where1 .= ($sql_where1 != '' ? ' AND' : 'WHERE') . ' dl.host_id = ?';
		$sql_params1[] = grv('host_id');
		$sql_where2 .= ' AND gl.host_id = ?';
		$sql_params2[] = grv('host_id');
	}

	if (isrv('errored') && grv('errored') == 'true') {
		$sql_where1 .= ($sql_where1 != '' ? ' AND' : 'WHERE') . ' dl.errored = 1';
	}

	if (ierv('site_id')) {
		$sql_where1 .= ($sql_where1 != '' ? ' AND' : 'WHERE') . ' (h.site_id=0 OR h.site_id IS NULL)';
		$sql_where2 .= ' AND (h.site_id=0 OR h.site_id IS NULL)';
	} elseif (grv('site_id') > 0) {
		$sql_where1 .= ($sql_where1 != '' ? ' AND' : 'WHERE') . ' h.site_id = ?';
		$sql_params1[] = grv('site_id');
		$sql_where2 .= ' AND h.site_id = ?';
		$sql_params2[] = grv('site_id');
	}

	if (grv('template_id') == '0') {
		$sql_where1 .= ($sql_where1 != '' ? ' AND' : 'WHERE') . ' dtd.data_template_id = 0';
	} elseif (grv('template_id') > 0) {
		$sql_where1 .= ($sql_where1 != '' ? ' AND' : 'WHERE') . ' dtd.data_template_id = ?';
		$sql_params1[] = grv('template_id');
	}

	if (grv('profile') > 0) {
		$sql_where1 .= ($sql_where1 != '' ? ' AND' : 'WHERE') . ' dtd.data_source_profile_id = ?';
		$sql_params1[] = grv('profile');
	}

	if (grv('status') == '1') {
		$sql_where1 .= ($sql_where1 != '' ? ' AND' : 'WHERE') . ' (dtd.active = "on" AND h.disabled = "")';
	} elseif (grv('status') == '2') {
		$sql_where1 .= ($sql_where1 != '' ? ' AND' : 'WHERE') . ' (dtd.active = "" OR h.disabled != "")';
	} elseif (grv('status') == '3') {
		$sql_where1 .= ($sql_where1 != '' ? ' AND' : 'WHERE') . ' (dl.snmp_index = "" AND dl.snmp_query_id > 0)';
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	if (grv('orphans') == 'true') {
		$sql_where1 .= ($sql_where1 != '' ? ' AND ' : 'WHERE ') . '((dl.snmp_index = "" AND dl.snmp_query_id > 0) OR graph_items = 0 IS NULL OR dl.orphan = 1)';

		$orphan_join = "LEFT JOIN (
			SELECT dtr.local_data_id, COUNT(DISTINCT gti.task_item_id) AS graph_items
			FROM data_template_rrd AS dtr
			LEFT JOIN graph_templates_item AS gti
			ON dtr.id = gti.task_item_id
			INNER JOIN graph_local AS gl
			ON gl.id = gti.local_graph_id
			LEFT JOIN host AS h
			ON h.id = gl.host_id
			WHERE dtr.local_data_id > 0
			$sql_where2
			GROUP BY local_data_id
		) AS dtr
		ON dl.id = dtr.local_data_id";

		$sql = "SELECT COUNT(*)
			FROM data_local AS dl
			INNER JOIN data_template_data AS dtd
			ON dl.id = dtd.local_data_id
			INNER JOIN data_template AS dt
			ON dt.id = dl.data_template_id
			LEFT JOIN host AS h
			ON h.id = dl.host_id
			$orphan_join
			$sql_where1";

		if (cacti_sizeof($sql_params2)) {
			$merged_params = array_merge($sql_params2, $sql_params1);
		} else {
			$merged_params = $sql_params1;
		}

		$total_rows = get_total_row_data($_SESSION[SESS_USER_ID], $sql, $merged_params, 'data_source');

		$data_sources = db_fetch_assoc_prepared("SELECT dtd.local_data_id,
			dtd.name_cache, dtd.active, dtd.rrd_step, dt.name AS data_template_name,
			dl.host_id, dtd.data_source_profile_id
			FROM data_local AS dl
			INNER JOIN data_template_data AS dtd
			ON dl.id = dtd.local_data_id
			INNER JOIN data_template AS dt
			ON dt.id = dl.data_template_id
			LEFT JOIN host AS h
			ON h.id = dl.host_id
			$orphan_join
			$sql_where1
			$sql_order
			$sql_limit",
			$merged_params);
	} else {
		$sql = "SELECT COUNT(*)
			FROM data_local AS dl
			INNER JOIN data_template_data AS dtd
			ON dl.id=dtd.local_data_id
			LEFT JOIN data_template AS dt
			ON dt.id = dl.data_template_id
			LEFT JOIN host AS h
			ON h.id = dl.host_id
			$sql_where1";

		$total_rows = get_total_row_data($_SESSION[SESS_USER_ID], $sql, $sql_params1, 'data_source');

		$data_sources = db_fetch_assoc_prepared("SELECT dtd.local_data_id, dtd.name_cache, dtd.active,
			dtd.rrd_step, dt.name AS data_template_name, dl.host_id, dtd.data_source_profile_id
			FROM data_local AS dl
			INNER JOIN data_template_data AS dtd
			ON dl.id=dtd.local_data_id
			LEFT JOIN data_template AS dt
			ON dt.id = dl.data_template_id
			LEFT JOIN host AS h
			ON h.id = dl.host_id
			$sql_where1
			$sql_order
			$sql_limit",
			$sql_params1);
	}

	$nav = html_nav_bar('data_sources.php', MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 7, __('Data Sources'), 'page', 'main');

	form_start('data_sources.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'name_cache' => [
			'display' => __('Data Source Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this Data Source. Generally programmatically generated from the Data Template definition.')
		],
		'local_data_id' => [
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal database ID for this Data Source. Useful when performing automation or debugging.')
		],
		'nosort0' => [
			'display' => __('Graphs'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('The number of Graphs and Aggregate Graphs that are using the Data Source.')
		],
		'nosort1' => [
			'display' => __('Poller Interval'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The frequency that data is collected for this Data Source.')
		],
		'nosort2' => [
			'display' => __('Deletable'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('If this Data Source is no long in use by Graphs, it can be Deleted.')
		],
		'active' => [
			'display' => __('Active'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('Whether or not data will be collected for this Data Source. Controlled at the Data Template level.')
		],
		'data_template_name' => [
			'display' => __('Template Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Data Template that this Data Source was based upon.')
		]
	];

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($data_sources)) {
		foreach ($data_sources as $data_source) {
			if (api_data_source_deletable($data_source['local_data_id'])) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			$data_source['data_template_name'] = htmle($data_source['data_template_name']);

			// keep copy of data source for comparison
			$data_source_orig = $data_source;
			$data_source      = api_plugin_hook_function('data_sources_table', $data_source);

			// we're escaping strings here, so no need to escape them on form_selectable_cell
			if (empty($data_source['data_template_name'])) {
				$data_template_name = '<em>' . __('None') . '</em>';
			} elseif ($data_source_orig['data_template_name'] != $data_source['data_template_name']) {
				// was changed by plugin, plugin has to take care for html-escaping
				$data_template_name = $data_source['data_template_name'];
			} elseif (grv('rfilter') != '') {
				$data_template_name = filter_value($data_source['data_template_name'], grv('rfilter'));
			} else {
				$data_template_name = htmle($data_source['data_template_name']);
			}

			$graphs_aggregates_url = get_graphs_aggregates_url($data_source['local_data_id']);

			if ($data_source['name_cache'] == '') {
				$name = __('Damaged Data Source Name');
			} else {
				$name = $data_source['name_cache'];
			}

			form_alternate_row('line' . $data_source['local_data_id'], true, $disabled);

			$url = 'data_sources.php?action=ds_edit&id=' . $data_source['local_data_id'];

			form_selectable_cell(filter_value($name, grv('rfilter'), $url), $data_source['local_data_id']);
			form_selectable_cell($data_source['local_data_id'], $data_source['local_data_id'], '', 'right');

			// Show link to Graphs and Aggregates
			form_selectable_cell($graphs_aggregates_url, $data_source['local_data_id'], '', 'center');

			form_selectable_cell(get_poller_interval($data_source['rrd_step'], $data_source['data_source_profile_id']), $data_source['local_data_id']);
			form_selectable_cell(api_data_source_deletable($data_source['local_data_id']) ? __('Yes') : __('No'), $data_source['local_data_id']);
			form_selectable_cell(($data_source['active'] == 'on' ? __('Yes') : __('No')), $data_source['local_data_id']);
			form_selectable_cell($data_template_name, $data_source['local_data_id']);
			form_checkbox_cell($name, $data_source['local_data_id'], $disabled);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Data Sources Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($data_sources)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}

function get_graphs_aggregates_url(int $local_data_id) : string {
	$graphs = db_fetch_row_prepared('SELECT GROUP_CONCAT(DISTINCT gl.id) AS graphs, COUNT(DISTINCT gl.id) AS total
		FROM data_local AS dl
		INNER JOIN data_template_rrd AS dtr
		ON dl.id = dtr.local_data_id
		INNER JOIN graph_templates_item AS gti
		ON gti.task_item_id = dtr.id
		INNER JOIN graph_local AS gl
		ON gl.id = gti.local_graph_id
		LEFT JOIN aggregate_graphs AS ag
		ON ag.local_graph_id = gl.id
		WHERE dl.id = ?
		AND ag.local_graph_id IS NULL',
		[$local_data_id]);

	$aggregates = db_fetch_row_prepared('SELECT GROUP_CONCAT(DISTINCT gl.id) AS graphs, COUNT(DISTINCT gl.id) AS total
		FROM data_local AS dl
		INNER JOIN data_template_rrd AS dtr
		ON dl.id = dtr.local_data_id
		INNER JOIN graph_templates_item AS gti
		ON gti.task_item_id = dtr.id
		INNER JOIN graph_local AS gl
		ON gl.id = gti.local_graph_id
		INNER JOIN aggregate_graphs AS ag
		ON ag.local_graph_id = gl.id
		WHERE dl.id = ?',
		[$local_data_id]);

	$url = '';

	if (cacti_sizeof($graphs) && $graphs['total'] > 0) {
		$url .= '<a class="linkEditMain" title="' . __('Graphs') . '" href="graphs.php?reset=1&custom=true&local_graph_ids=' . $graphs['graphs'] . '">' . $graphs['total'] . '</a>';
	} else {
		$url .= '<span title="' . __('No Graphs') . '">0</span>';
	}

	if (cacti_sizeof($aggregates) && $aggregates['total'] > 0) {
		$url .= ' / <a class="linkEditMain" title="' . __('Aggregates') . '" href="aggregate_graphs.php?reset=1&custom=true&local_graph_ids=' . $aggregates['graphs'] . '">' . $aggregates['total'] . '</a>';
	} else {
		$url .= ' / <span title="' . __('No Aggregates') . '">0</span>';
	}

	return $url;
}

function create_data_sources_filter(string $session_var) : array {
	global $item_rows, $page_refresh_interval;

	$all     = ['-1' => __('All')];
	$any     = ['-1' => __('Any')];
	$none    = ['0'  => __('None')];
	$deleted = ['-2' => __('Deleted/Invalid')];

	$sites = array_rekey(
		db_fetch_assoc('SELECT id, name
			FROM sites
			ORDER BY name'),
		'id', 'name'
	);
	$sites = $any + $sites;

	$profiles = array_rekey(
		db_fetch_assoc('SELECT id, name
			FROM data_source_profiles
			ORDER BY name'),
		'id', 'name'
	);
	$profiles = $all + $profiles;

	$status = [
		'-1' => __('All'),
		'1'  => __('Enabled'),
		'2'  => __('Disabled'),
		'3'  => __('Bad Indexes')
	];

	$sql_where  = '';
	$sql_params = [];

	if (isrv('host_id')) {
		$host_id = gfrv('host_id');
	} elseif (isset($_SESSION[$session_var . '_host_id'])) {
		$host_id = $_SESSION[$session_var . '_host_id'];
	} else {
		$host_id = '-1';
	}

	if ($host_id > 0) {
		// for the templates dropdown
		$sql_where    = 'AND h.id = ?';
		$sql_params[] = $host_id;

		$hostname = db_fetch_cell_prepared('SELECT description
			FROM host
			WHERE id = ?',
			[$host_id]);
	} elseif ($host_id == 0) {
		$host_id  = '0';
		$hostname = __('None');
	} else {
		$host_id  = '-1';
		$hostname = __('Any');
	}

	if (gfrv('site_id') > 0) {
		$sql_where    = 'AND site_id = ?';
		$sql_params[] = grv('site_id');
	}

	$templates = array_rekey(
		db_fetch_assoc_prepared("SELECT DISTINCT dt.id, dt.name
			FROM data_template AS dt
			INNER JOIN data_template_data AS dtd
			ON dt.id = dtd.data_template_id
			LEFT JOIN data_local AS dl
			ON dtd.local_data_id = dl.id
			LEFT JOIN host AS h
			ON dl.host_id = h.id
			WHERE dtd.local_data_id > 0
			$sql_where
			ORDER BY dt.name",
			$sql_params),
		'id', 'name'
	);

	$templates = $any + $templates;

	return [
		'rows' => [
			[
				'site_id' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Site'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $sites,
					'value'         => '-1'
				],
				'host_id' => [
					'method'        => 'drop_callback',
					'friendly_name' => __('Device'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'sql'           => 'SELECT DISTINCT id, description AS name FROM host ORDER BY description',
					'action'        => 'ajax_hosts',
					'id'            => $host_id,
					'value'         => $hostname,
					'on_change'     => 'applyFilter()'
				],
				'template_id' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Template'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $templates,
					'value'         => '-1'
				]
			],
			[
				'profile' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Profile'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $profiles,
					'value'         => '-1'
				],
				'status' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Status'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $status,
					'value'         => '-1'
				],
				'orphans' => [
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Orphaned'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '(true|false)']],
					'default'        => '',
					'pageset'        => true,
					'value'          => gnrv('orphans')
				],
				'errored' => [
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Errored'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '(true|false)']],
					'default'        => '',
					'pageset'        => true,
					'value'          => gnrv('errored')
				]
			],
			[
				'rfilter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_VALIDATE_IS_REGEX,
					'placeholder'    => __('Enter a search term'),
					'size'           => '55',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				],
				'rows' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Data Sources'),
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
			]
		],
		'sort' => [
			'sort_column'    => 'name_cache',
			'sort_direction' => 'DESC'
		]
	];
}

function draw_data_source_filter(bool $render = false) : void {
	$filters = create_data_sources_filter('sess_ds');

	if (read_config_option('grds_creation_method') == 1) {
		if (gfrv('host_id') == '-1') {
			$new_host_id = 0;
		} else {
			$new_host_id = grv('host_id');
		}

		$add_url = htmle('data_sources.php?action=ds_edit&host_id=' . $new_host_id);
	} else {
		$add_url = '';
	}

	if (gfrv('host_id') == -1) {
		$header = __('Data Sources [ All Devices ]');
	} elseif (grv('host_id') == 0) {
		$header = __('Data Sources [ Non Device Based ]');
	} elseif (grv('host_id') > 0) {
		$description = db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', [grv('host_id')]);
		$header      = __esc('Data Sources [ %s ]', $description);
	} else {
		$header = __('Data Sources [ All Devices ]');
	}

	// create the page filter
	$pageFilter = new CactiTableFilter($header, 'data_sources.php', 'form_data_sources', 'sess_ds', $add_url);

	$pageFilter->rows_label = __('Data Sources');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}
