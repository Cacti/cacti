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

include ('./include/auth.php');
include_once('./lib/api_data_source.php');
include_once('./lib/api_device.php');
include_once('./lib/api_graph.php');
include_once('./lib/data_query.php');
include_once('./lib/html_form_template.php');
include_once('./lib/poller.php');
include_once('./lib/rrd.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');

$ds_actions = array(
	1 => __('Delete'),
	3 => __('Change Device'),
	8 => __('Reapply Suggested Names'),
	6 => __('Enable'),
	7 => __('Disable')
);

$ds_actions = api_plugin_hook_function('data_source_action_array', $ds_actions);

/* set default action */
set_default_action();

validate_data_source_vars();

switch (get_request_var('action')) {
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
	case 'ds_remove':
		ds_remove();

		header ('Location: data_sources.php');
		break;
	case 'ds_edit':
		ds_edit();

		break;
	case 'ajax_hosts':
		$sql_where = '';
		if (get_request_var('site_id') > 0) {
			$sql_where = 'site_id = ' . get_request_var('site_id');
		}

		get_allowed_ajax_hosts(true, 'applyFilter', $sql_where);

		break;
	case 'ajax_hosts_noany':

		$sql_where = '';
		if (get_request_var('site_id') > 0) {
			$sql_where = 'site_id = ' . get_request_var('site_id');
		}

		get_allowed_ajax_hosts(false, 'applyFilter', $sql_where);

		break;
	default:
		top_header();

		ds();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset_request_var('save_component_data_source_new')) && (!isempty_request_var('data_template_id'))) {
		$save['id']               = get_filter_request_var('local_data_id');
		$save['host_id']          = get_filter_request_var('host_id');
		$save['data_template_id'] = get_filter_request_var('data_template_id');

		$local_data_id = sql_save($save, 'data_local');

		change_data_template($local_data_id, get_request_var('data_template_id'));

		/* update the title cache */
		update_data_source_title_cache($local_data_id);

		/* update host data */
		if (!isempty_request_var('host_id')) {
			push_out_host(get_request_var('host_id'), $local_data_id);
		}

		if (empty($save['id'])) {
			/**
			 * Save the last time a data source was created/updated
			 * for Caching.
			 */
			set_config_option('time_last_change_data_source', time());
		}
	}

	if ((isset_request_var('save_component_data')) && (!is_error_message())) {
		/* ================= input validation ================= */
		get_filter_request_var('data_template_data_id');
		/* ==================================================== */

		/* ok, first pull out all 'input' values so we know how much to save */
		$input_fields = db_fetch_assoc_prepared("SELECT dtd.data_input_id, dl.host_id, dif.id, dif.input_output,
			dif.data_name, dif.regexp_match, dif.allow_nulls, dif.type_code
			FROM data_template_data AS dtd
			LEFT JOIN data_input_fields AS dif
			ON dif.data_input_id = dtd.data_input_id
			LEFT JOIN data_local AS dl
			ON dtd.local_data_id = dl.id
			WHERE dtd.id = ?
			AND dif.input_output='in'",
			array(get_request_var('data_template_data_id')));

		if (cacti_sizeof($input_fields)) {
			foreach ($input_fields as $input_field) {
				if (isset_request_var('value_' . $input_field['id'])) {
					/* save the data into the 'data_input_data' table */
					$form_value = get_nfilter_request_var('value_' . $input_field['id']);

					/* we shouldn't enforce rules on fields the user cannot see (ie. templated ones) */
					$data_template_id = db_fetch_cell_prepared('SELECT local_data_template_data_id
						FROM data_template_data
						WHERE id = ?',
						array(get_request_var('data_template_data_id'))
					);

					$is_templated = db_fetch_cell_prepared('SELECT t_value
						FROM data_input_data
						WHERE data_input_field_id = ?
						AND data_template_data_id = ?',
						array($input_field['id'], $data_template_id)
					);

					if ($is_templated == '') {
						$allow_nulls = true;
					} elseif ($input_field['allow_nulls'] == 'on') {
						$allow_nulls = true;
					} elseif (empty($input_field['allow_nulls'])) {
						$allow_nulls = false;
					}

					/* run regexp match on input string */
					$form_value = form_input_validate($form_value, 'value_' . $input_field['id'], $input_field['regexp_match'], $allow_nulls, 3);

					if (!is_error_message()) {
						db_execute_prepared("REPLACE INTO data_input_data
							(data_input_field_id, data_template_data_id, t_value, value)
							VALUES
							(?, ?, '', ?)",
							array($input_field['id'], get_request_var('data_template_data_id'), $form_value)
						);
					}
				}
			}
		}
	}

	if ((isset_request_var('save_component_data_source')) && (!is_error_message())) {
		/* ================= input validation ================= */
		get_filter_request_var('current_rrd');
		get_filter_request_var('rrd_step');
		get_filter_request_var('data_input_id');
		get_filter_request_var('data_source_profile_id');
		get_filter_request_var('host_id');
		get_filter_request_var('_host_id');
		get_filter_request_var('_data_template_id');
		/* ==================================================== */

		$save1['id']               = get_filter_request_var('local_data_id');
		$save1['data_template_id'] = get_filter_request_var('data_template_id');
		$save1['host_id']          = get_filter_request_var('host_id');

		$save2['id']                          = get_filter_request_var('data_template_data_id');
		$save2['local_data_template_data_id'] = get_filter_request_var('local_data_template_data_id');
		$save2['data_template_id']            = get_filter_request_var('data_template_id');
		$save2['data_input_id']               = form_input_validate(get_request_var('data_input_id'), 'data_input_id', '^[0-9]+$', true, 3);
		$save2['name']                        = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save2['data_source_path']            = form_input_validate(get_nfilter_request_var('data_source_path'), 'data_source_path', '', true, 3);
		$save2['active']                      = form_input_validate((isset_request_var('active') ? get_nfilter_request_var('active') : ''), 'active', '', true, 3);
		$save2['data_source_profile_id']      = form_input_validate(get_request_var('data_source_profile_id'), 'data_source_profile_id', '^[0-9]+$', false, 3);
		$save2['rrd_step']                    = form_input_validate(get_request_var('rrd_step'), 'rrd_step', '^[0-9]+$', false, 3);

		if (!is_error_message()) {
			$local_data_id = sql_save($save1, 'data_local');

			$save2['local_data_id'] = $local_data_id;
			$data_template_data_id = sql_save($save2, 'data_template_data');

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

		if (!is_error_message()) {
			/* if this is a new data source and a template has been selected, skip item creation this time
			otherwise it throws off the template creation because of the NULL data */
			if (!isempty_request_var('local_data_id') || isempty_request_var('data_template_id')) {
				/* if no template was set before the save, there will be only one data source item to save;
				otherwise there might be >1 */
				if (isempty_request_var('_data_template_id')) {
					$rrds[0]['id'] = get_nfilter_request_var('current_rrd');
				} else {
					$rrds = db_fetch_assoc_prepared('SELECT id
						FROM data_template_rrd
						WHERE local_data_id = ?',
						array(get_filter_request_var('local_data_id')));
				}

				if (cacti_sizeof($rrds)) {
					foreach ($rrds as $rrd) {
						if (isempty_request_var('_data_template_id')) {
							$name_modifier = '';
						} else {
							$name_modifier = '_' . $rrd['id'];
						}

						$save3['id'] = $rrd['id'];
						$save3['local_data_id'] = $local_data_id;

						$save3['local_data_template_rrd_id'] = db_fetch_cell_prepared('SELECT local_data_template_rrd_id
							FROM data_template_rrd
							WHERE id = ?',
							array($rrd['id']));

						$save3['data_template_id'] = get_filter_request_var('data_template_id');

						$save3['rrd_maximum'] = form_input_validate(get_nfilter_request_var("rrd_maximum$name_modifier"), "rrd_maximum$name_modifier", "^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$|\|query_ifSpeed\||\|query_ifHighSpeed\|", false, 3);

						$save3['rrd_minimum'] = form_input_validate(get_nfilter_request_var("rrd_minimum$name_modifier"), "rrd_minimum$name_modifier", "^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$|\|query_ifSpeed\||\|query_ifHighSpeed\|", false, 3);

						$save3['rrd_heartbeat'] = form_input_validate(get_nfilter_request_var("rrd_heartbeat$name_modifier"), "rrd_heartbeat$name_modifier", '^[0-9]+$', false, 3);

						$save3['data_source_type_id'] = form_input_validate(get_nfilter_request_var("data_source_type_id$name_modifier"), "data_source_type_id$name_modifier", '^[0-9]+$', false, 3);

						$save3['data_source_name'] = form_input_validate(get_nfilter_request_var("data_source_name$name_modifier"), "data_source_name$name_modifier", '^[a-zA-Z0-9_-]{1,19}$', false, 3);

						$save3['data_input_field_id'] = form_input_validate((isset_request_var("data_input_field_id$name_modifier") ? get_nfilter_request_var("data_input_field_id$name_modifier") : '0'), "data_input_field_id$name_modifier", '', true, 3);

						if ($save3['rrd_minimum'] != 'U' && $save3['rrd_maximum'] != 'U') {
							if ($save3['rrd_minimum'] >= $save3['rrd_maximum']) {
								raise_message(43);

								$_SESSION['sess_error_fields']['rrd_maximum'] = 'rrd_maximum';

								header('Location: data_sources.php?header=false&action=ds_edit&id=' . (empty($local_data_id) ? get_filter_request_var('local_data_id') : $local_data_id) . '&host_id=' . get_request_var('host_id') . '&view_rrd=' . (isset_request_var('current_rrd') ? get_nfilter_request_var('current_rrd') : '0'));

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

		if (!is_error_message()) {
			if (get_request_var('data_template_id') != get_request_var('_data_template_id')) {
				/* update all necessary template information */
				change_data_template($local_data_id, get_request_var('data_template_id'));
			} elseif (!isempty_request_var('data_template_id')) {
				update_data_source_data_query_cache($local_data_id);
			}

			if (get_request_var('host_id') != get_request_var('_host_id')) {
				/* push out all necessary host information */
				push_out_host(get_request_var('host_id'), $local_data_id);

				/* reset current host for display purposes */
				$_SESSION['sess_data_source_current_host_id'] = get_request_var('host_id');
			}

			/* if no data source path has been entered, generate one */
			if (isempty_request_var('data_source_path')) {
				generate_data_source_path($local_data_id);
			}

			/* update the title cache */
			update_data_source_title_cache($local_data_id);
		}
	}

	/* update the poller cache last to make sure everything is fresh */
	if ((!is_error_message()) && (!empty($local_data_id))) {
		update_poller_cache($local_data_id, true);
	}

	if (isset_request_var('save_component_data_source_new') && isempty_request_var('data_template_id')) {
		header('Location: data_sources.php?header=false&action=ds_edit&host_id=' . get_request_var('host_id') . '&new=1');
	} elseif ((is_error_message()) || (get_filter_request_var('data_template_id') != get_filter_request_var('_data_template_id')) || (get_filter_request_var('data_input_id') != get_filter_request_var('_data_input_id')) || (get_filter_request_var('host_id') != get_filter_request_var('_host_id'))) {
		header('Location: data_sources.php?header=false&action=ds_edit&id=' . (empty($local_data_id) ? get_filter_request_var('local_data_id') : $local_data_id) . '&host_id=' . get_request_var('host_id') . '&view_rrd=' . (isset_request_var('current_rrd') ? get_nfilter_request_var('current_rrd') : '0'));
	} else {
		header('Location: data_sources.php?header=false');
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $ds_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				if (!isset_request_var('delete_type')) {
					set_request_var('delete_type', 1);
				} else {
					get_filter_request_var('delete_type');
				}

				switch (get_request_var('delete_type')) {
					case '2': /* delete all graph items tied to this data source */
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

						/* loop through each data source item */
						if (cacti_sizeof($data_template_rrds) > 0) {
							db_execute('DELETE FROM graph_templates_item
								WHERE task_item_id IN (' . implode(',', $data_template_rrds) . ')
								AND local_graph_id > 0');

							if (cacti_sizeof($poller_ids)) {
								foreach($poller_ids as $poller_id) {
									if (($rcnn_id = poller_push_to_remote_db_connect($poller_id, true)) !== false) {
										db_execute('DELETE FROM graph_templates_item
											WHERE task_item_id IN (' . implode(',', $data_template_rrds) . ')
											AND local_graph_id > 0', true, $rcnn_id);
									}
								}
							}
						}

						break;
					case '3': /* delete all graphs tied to this data source */
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
			} elseif (get_nfilter_request_var('drp_action') == '3') { // change host
				get_filter_request_var('host_id');

				api_data_source_change_host($selected_items, get_request_var('host_id'));
			} elseif (get_nfilter_request_var('drp_action') == '6') { // data source enable
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					api_data_source_enable($selected_items[$i]);
				}
			} elseif (get_nfilter_request_var('drp_action') == '7') { // data source disable
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					api_data_source_disable($selected_items[$i]);
				}
			} elseif (get_nfilter_request_var('drp_action') == '8') { // reapply suggested data source naming
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					api_reapply_suggested_data_source_data($selected_items[$i]);
					update_data_source_title_cache($selected_items[$i]);
				}
			} else {
				api_plugin_hook_function('data_source_action_execute', get_nfilter_request_var('drp_action'));
			}
		}

		/* update snmpcache */
		snmpagent_data_source_action_bottom(array(get_nfilter_request_var('drp_action'), $selected_items));

		api_plugin_hook_function('data_source_action_bottom', array(get_nfilter_request_var('drp_action'), $selected_items));

		header('Location: data_sources.php?header=false');
		exit;
	}

	/* setup some variables */
	$ds_list = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$ds_list .= '<li>' . html_escape(get_data_source_title($matches[1])) . '</li>';
			$ds_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('data_sources.php');

	html_start_box($ds_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($ds_array) && cacti_sizeof($ds_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			$graphs = array();

			/* find out which (if any) graphs are using this data source, so we can tell the user */
			if (isset($ds_array)) {
				$graphs = db_fetch_assoc('SELECT
					graph_templates_graph.local_graph_id,
					graph_templates_graph.title_cache
					FROM (data_template_rrd,graph_templates_item,graph_templates_graph)
					WHERE graph_templates_item.task_item_id=data_template_rrd.id
					AND graph_templates_item.local_graph_id=graph_templates_graph.local_graph_id
					AND ' . array_to_sql_or($ds_array, 'data_template_rrd.local_data_id') . '
					AND graph_templates_graph.local_graph_id > 0
					GROUP BY graph_templates_graph.local_graph_id
					ORDER BY graph_templates_graph.title_cache');
			}

			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to delete the following Data Source', 'Click \'Continue\' to delete following Data Sources', cacti_sizeof($ds_array)) . "</p>
					<div class='itemlist'><ul>$ds_list</ul></div>";

			if (cacti_sizeof($graphs)) {
				print "<tr><td class='textArea'><p class='textArea'>" . __n('The following graph is using these data sources:', 'The following graphs are using these data sources:', cacti_sizeof($graphs)) . "</p>";

				print '<div class="itemlist"><ul>';
				foreach ($graphs as $graph) {
					print '<li>' . html_escape($graph['title_cache']) . "</li>";
				}
				print '</ul></div>';
				print '<br>';

				form_radio_button('delete_type', '3', '1', __n('Leave the <strong>Graph</strong> untouched.', 'Leave all <strong>Graphs</strong> untouched.', cacti_sizeof($graphs)), '1'); print '<br>';
				form_radio_button('delete_type', '3', '2', __n('Delete all <strong>Graph Items</strong> that reference this Data Source.', 'Delete all <strong>Graph Items</strong> that reference these Data Sources.', cacti_sizeof($ds_array)), '1'); print '<br>';
				form_radio_button('delete_type', '3', '3', __n('Delete all <strong>Graphs</strong> that reference this Data Source.', 'Delete all <strong>Graphs</strong> that reference these Data Sources.', cacti_sizeof($ds_array)), '1'); print '<br>';
				print '</td></tr>';
			}

			print "</td>
				</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Delete Data Source', 'Delete Data Sources', cacti_sizeof($ds_array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') == '3') { // change host
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Choose a new Device for this Data Source and click \'Continue\'.', 'Choose a new Device for these Data Sources and click \'Continue\'', cacti_sizeof($ds_array)) . "</p>
					<div class='itemlist'><ul>$ds_list</ul></div>
					<p>" . __('New Device:') . "<br>"; form_dropdown('host_id', db_fetch_assoc("SELECT id, CONCAT_WS('',description,' (',hostname,')') AS name FROM host ORDER BY description, hostname"),'name','id','','','0'); print "</p>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Change Device') . "'>";
		} elseif (get_nfilter_request_var('drp_action') == '6') { // data source enable
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to enable the following Data Source.', 'Click \'Continue\' to enable all following Data Sources.', cacti_sizeof($ds_array)) . "</p>
					<div class='itemlist'><ul>$ds_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Enable Data Source', 'Enable Data Sources', cacti_sizeof($ds_array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') == '7') { // data source disable
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to disable the following Data Source.', 'Click \'Continue\' to disable all following Data Sources.', cacti_sizeof($ds_array)) . "</p>
					<div class='itemlist'><ul>$ds_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Disable Data Source', 'Disable Data Sources', cacti_sizeof($ds_array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') == '8') { // reapply suggested data source naming
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to re-apply the suggested name to the following Data Source.', 'Click \'Continue\' to re-apply the suggested names to all following Data Sources.', cacti_sizeof($ds_array)) . "</p>
					<div class='itemlist'><ul>$ds_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Reapply Suggested Naming to Data Source', 'Reapply Suggested Naming to Data Sources', cacti_sizeof($ds_array)) . "'>";
		} else {
			$save['drp_action'] = get_nfilter_request_var('drp_action');
			$save['ds_list'] = $ds_list;
			$save['ds_array'] = (isset($ds_array)? $ds_array : array());
			api_plugin_hook_function('data_source_action_prepare', $save);
			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "'>";
		}
	} else {
		raise_message(40);
		header('Location: data_sources.php?header=false');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($ds_array) ? serialize($ds_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

/* ----------------------------
    data - Custom Data
   ---------------------------- */

function data_edit($incform = true) {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$data = db_fetch_row_prepared('SELECT id, data_input_id, data_template_id, name, local_data_id
			FROM data_template_data
			WHERE local_data_id = ?',
			array(get_request_var('id')));

		$template_data = db_fetch_row_prepared('SELECT id, data_input_id
			FROM data_template_data
			WHERE data_template_id = ?
			AND local_data_id = 0',
			array($data['data_template_id']));

		$host = db_fetch_row_prepared('SELECT host.id, host.hostname
			FROM (data_local, host)
			WHERE data_local.host_id = host.id
			AND data_local.id = ?',
			array(get_request_var('id')));
	}

	if ($incform) {
		form_start('data_sources.php', 'data_source_edit');
	}

	$i = 0;
	if (!empty($data['data_input_id'])) {
		/* get each INPUT field for this data input source */
		$fields = db_fetch_assoc_prepared('SELECT *
			FROM data_input_fields
			WHERE data_input_id = ?
			AND input_output = "in"
			ORDER BY name',
			array($data['data_input_id'])
		);

		$data_input_name = db_fetch_cell_prepared('SELECT name
			FROM data_input
			WHERE id = ?',
			array($data['data_input_id']));

		html_start_box(__('Custom Data [data input: %s]', html_escape($data_input_name)), '100%', '', '3', 'center', '');

		/* loop through each field found */
		if (cacti_sizeof($fields) > 0) {
			foreach ($fields as $field) {
				$data_input_data = db_fetch_row_prepared('SELECT *
					FROM data_input_data
					WHERE data_template_data_id = ?
					AND data_input_field_id = ?',
					array($data['id'], $field['id'])
				);

				if (cacti_sizeof($data_input_data) > 0) {
					$old_value = $data_input_data['value'];
				} else {
					$old_value = '';
				}

				/* if data template then get t_value FROM template, else always allow user input */
				if (empty($data['data_template_id'])) {
					$can_template = 'on';
				} else {
					$can_template = db_fetch_cell_prepared('SELECT t_value
						FROM data_input_data
						WHERE data_template_data_id = ?
						AND data_input_field_id = ?',
						array($template_data['id'], $field['id'])
					);
				}

				form_alternate_row();

				if ((!empty($host['id'])) && (preg_match('/^' . VALID_HOST_FIELDS . '$/i', $field['type_code']))) {
					print "<td style='width:50%;'><strong>" . html_escape($field['name']) . '</strong> ' . __('(From Device: %s)', html_escape($host['hostname'])) . "</td>";
					print "<td><em>" . html_escape($old_value) . "</em></td>";
				} elseif (empty($can_template)) {
					print "<td style='width:50%;'><strong>" . html_escape($field['name']) . '</strong> ' . __('(From Data Template)') . "</td>";
					print '<td><em>' . (empty($old_value) ? __('Nothing Entered') : html_escape($old_value)) . "</em></td>";
				} else {
					print "<td style='width:50%;'><strong>" . html_escape($field['name']) . "</strong></td>";
					print '<td>';

					draw_custom_data_row('value_' . $field['id'], $field['id'], $data['id'], $old_value);

					print '</td>';
				}

				print "</tr>";

				$i++;
			}
		} else {
			print '<tr><td><em>' . __('No Input Fields for the Selected Data Input Source') . '</em></td></tr>';
		}

		html_end_box();
	}

	if ($incform) {
		form_hidden_box('local_data_id', (isset($data) ? $data['local_data_id'] : '0'), '');
		form_hidden_box('data_template_data_id', (isset($data) ? $data['id'] : '0'), '');
	}

	form_hidden_box('save_component_data', '1', '');
}

/* ------------------------
    Data Source Functions
   ------------------------ */

function ds_rrd_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM data_template_rrd
		WHERE id = ?',
		array(get_request_var('id')));

	db_execute_prepared('UPDATE graph_templates_item
		SET task_item_id = 0
		WHERE task_item_id = ?',
		array(get_request_var('id')));

	header('Location: data_sources.php?header=false&action=ds_edit&id=' . get_request_var('local_data_id'));
}

function ds_rrd_add() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	db_execute_prepared("INSERT INTO data_template_rrd
		(local_data_id, rrd_maximum, rrd_minimum, rrd_heartbeat, data_source_type_id, data_source_name)
		VALUES (?, 100, 0, 600, 1, 'ds')",
		array(get_request_var('id')));

	$data_template_rrd_id = db_fetch_insert_id();

	header('Location: data_sources.php?header=false&action=ds_edit&id=' . get_request_var('id') . "&view_rrd=$data_template_rrd_id");
}

function ds_disable() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	api_data_source_disable(get_request_var('id'));
	header('Location: data_sources.php?header=false&action=ds_edit&id=' . get_request_var('id'));
}

function ds_enable() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	api_data_source_enable(get_request_var('id'));
	header('Location: data_sources.php?header=false&action=ds_edit&id=' . get_request_var('id'));
}

function ds_edit() {
	global $struct_data_source, $struct_data_source_item;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_id');
	/* ==================================================== */

	api_plugin_hook('data_source_edit_top');

	$use_data_template = true;
	$data_template     = array();

	if (!isempty_request_var('id')) {
		$data_local = db_fetch_row_prepared('SELECT host_id, data_template_id
			FROM data_local
			WHERE id = ?',
			array(get_request_var('id')));

		$data = db_fetch_row_prepared('SELECT *
			FROM data_template_data
			WHERE local_data_id = ?',
			array(get_request_var('id')));

		if (isset($data_local['data_template_id']) && $data_local['data_template_id'] >= 0) {
			$data_template = db_fetch_row_prepared('SELECT id, name
				FROM data_template
				WHERE id = ?',
				array($data_local['data_template_id']));

			$data_template_data = db_fetch_row_prepared('SELECT *
				FROM data_template_data
				WHERE data_template_id = ?
				AND local_data_id = 0',
				array($data_local['data_template_id']));
		} else {
			raise_message(11);
			header ('Location: data_sources.php');
			exit;
		}

		$header_label = __esc('Data Template Selection [edit: %s]', get_data_source_title(get_request_var('id')));

		if (empty($data_local['data_template_id'])) {
			$use_data_template = false;
		}
	} else {
		$header_label = __('Data Template Selection [new]');

		$use_data_template = false;
	}

	/* handle debug mode */
	if (isset_request_var('debug')) {
		if (get_nfilter_request_var('debug') == '0') {
			kill_session_var('ds_debug_mode');
		} elseif (get_nfilter_request_var('debug') == '1') {
			$_SESSION['ds_debug_mode'] = true;
		}
	}

	/* handle debug mode */
	if (isset_request_var('info')) {
		if (get_nfilter_request_var('info') == '0') {
			kill_session_var('ds_info_mode');
		} elseif (get_nfilter_request_var('info') == '1') {
			$_SESSION['ds_info_mode'] = true;
		}
	}

	top_header();

	if (!isempty_request_var('id')) {
		$local_graph_ids = db_fetch_assoc_prepared('SELECT DISTINCT local_graph_id
			FROM graph_templates_item AS gti
			INNER JOIN data_template_rrd AS dtr
			ON dtr.id = gti.task_item_id
			WHERE local_data_id = ?',
			array(get_request_var('id')));
		?>
		<table style='width:100%'>
			<tr>
				<td class='textInfo left' style='vertical-align:top;'>
					<?php print html_escape(get_data_source_title(get_request_var('id')));?>
				</td>
				<td class='textInfo right' style='vertical-align:top;'>
					<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print html_escape('data_sources.php?action=ds_edit&id=' . (isset_request_var('id') ? get_request_var('id') : '0') . '&debug=' . (isset($_SESSION['ds_debug_mode']) ? '0' : '1'));?>'><?php print (isset($_SESSION['ds_debug_mode']) ? __('Turn Off Data Source Debug Mode.') : __('Turn On Data Source Debug Mode.'));?></a><br>
					<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print html_escape('data_sources.php?action=ds_edit&id=' . (isset_request_var('id') ? get_request_var('id') : '0') . '&info=' . (isset($_SESSION['ds_info_mode']) ? '0' : '1'));?>'><?php print (isset($_SESSION['ds_info_mode']) ? __('Turn Off Data Source Info Mode.') : __('Turn On Data Source Info Mode.'));?></a><br>
					<?php
						if (cacti_sizeof($local_graph_ids)) {
							foreach($local_graph_ids as $id) {
								$name = db_fetch_cell_prepared('SELECT title_cache
									FROM graph_templates_graph
									WHERE local_graph_id = ?',
									array($id['local_graph_id']));

								?><span class='linkMarker'>*</span><a class='hyperLink' href='<?php print html_escape('graphs.php?action=graph_edit&id=' . $id['local_graph_id']);?>'><?php print __('Edit Graph: \'%s\'.', $name);?></a><br><?php
							}
						}
						if (!empty($data_local['host_id'])) {
							?><span class='linkMarker'>*</span><a class='hyperLink' href='<?php print html_escape('host.php?action=edit&id=' . $data_local['host_id']);?>'><?php print __('Edit Device.');?></a><br><?php
						}
						if (!empty($data_template['id'])) {
							?><span class='linkMarker'>*</span><a class='hyperLink' href='<?php print html_escape('data_templates.php?action=template_edit&id=' . (isset($data_template['id']) ? $data_template['id'] : '0'));?>'><?php print __('Edit Data Template.');?></a><br><?php
						}
						if (isset_request_var('id') && get_request_var('id') > 0) {
							?><span class='linkMarker'>*</span><a class='hyperLink' href='<?php print html_escape('data_sources.php?action=ds_' . ($data['active'] == 'on' ? 'dis' : 'en') . 'able&id=' . get_request_var('id')) ?>'><?php print ($data['active'] == 'on' ? __('Disable Data Source.') : __('Enable Data Source.'));?></a><br>
					<?php
						}
					?>
				</td>
			</tr>
		</table>
		<br>
		<?php
	}

	form_start('data_sources.php', 'data_source');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	if (cacti_sizeof($data_template)) {
		$data_sources = db_fetch_cell_prepared('SELECT
			GROUP_CONCAT(DISTINCT data_source_name ORDER BY data_source_name) AS data_source_names
			FROM data_template_rrd
			WHERE data_template_id = ?
			GROUP BY data_template_id
			ORDER BY data_source_names',
			array($data_template['id'])
		);

		$dts = db_fetch_assoc_prepared('SELECT data_template_id,
			GROUP_CONCAT(DISTINCT data_source_name ORDER BY data_source_name) AS data_source_names
			FROM data_template_rrd
			WHERE local_data_id=0
			GROUP BY data_template_id
			HAVING data_source_names = ?',
			array($data_sources)
		);

		if (cacti_sizeof($dts)) {
			foreach($dts as $dtid) {
				$dtids[] = $dtid['data_template_id'];
			}

			$dtsql = 'SELECT id, name FROM data_template WHERE id IN(' . implode(',', $dtids) . ') ORDER BY name';
		} else {
			$dtsql = 'SELECT id, name FROM data_template ORDER BY name';
		}
	} else {
		$dtsql = 'SELECT id, name FROM data_template ORDER BY name';
	}

	if (get_request_var('host_id') > 0) {
		$hostDescription = db_fetch_cell_prepared('SELECT description
			FROM host
			WHERE id = ?',
			array(get_request_var('host_id'))
		);
	} elseif (isset($data_local['host_id'])) {
		$hostDescription = db_fetch_cell_prepared('SELECT description
			FROM host
			WHERE id = ?',
			array($data_local['host_id'])
		);
	} else {
		$hostDescription = '';
	}

	$form_array = array(
		'data_template_id' => array(
			'method' => 'drop_sql',
			'friendly_name' => __('Selected Data Template'),
			'description' => __('The name given to this data template.  Please note that you may only change Graph Templates to a 100%$ compatible Graph Template, which means that it includes identical Data Sources.'),
			'value' => (cacti_sizeof($data_template) ? $data_template['id'] : '0'),
			'none_value' => (cacti_sizeof($data_template) ? '' : 'None'),
			'sql' => $dtsql
			),
		'host_id' => array(
			'method' => 'drop_callback',
			'friendly_name' => __('Device'),
			'description' => __('Choose the Device that this Data Source belongs to.'),
			'none_value' => __('None'),
			'sql' => 'SELECT id, description AS name FROM host ORDER BY name',
			'action' => 'ajax_hosts_noany',
			'id' => (isset($data_local['host_id']) ? $data_local['host_id'] : 0),
			'value' => $hostDescription
			),
		'_data_template_id' => array(
			'method' => 'hidden',
			'value' => (isset($data_template['id']) ? $data_template['id'] : '0')
			),
		'_host_id' => array(
			'method' => 'hidden',
			'value' => (isset($data_local['host_id']) ? $data_local['host_id'] : '0')
			),
		'_data_input_id' => array(
			'method' => 'hidden',
			'value' => (isset($data['data_input_id']) ? $data['data_input_id'] : '0')
			),
		'data_template_data_id' => array(
			'method' => 'hidden',
			'value' => (isset($data['id']) ? $data['id'] : '0')
			),
		'local_data_template_data_id' => array(
			'method' => 'hidden',
			'value' => (isset($data['local_data_template_data_id']) ? $data['local_data_template_data_id'] : '0')
			),
		'local_data_id' => array(
			'method' => 'hidden',
			'value' => (isset($data['local_data_id']) ? $data['local_data_id'] : '0')
			),
		);

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $form_array
		)
	);

	html_end_box(true, true);

	/* only display the "inputs" area if we are using a data template for this data source */
	if (!empty($data['data_template_id'])) {
		$template_data_rrds = db_fetch_assoc_prepared('SELECT *
			FROM data_template_rrd
			WHERE local_data_id = ?
			ORDER BY data_source_name',
			array(get_request_var('id')));

		html_start_box(__('Supplemental Data Template Data'), '100%', true, '3', 'center', '');

		draw_nontemplated_fields_data_source($data['data_template_id'], $data['local_data_id'], $data, '|field|', __('Data Source Fields'), true, true, 0);
		draw_nontemplated_fields_data_source_item($data['data_template_id'], $template_data_rrds, '|field|_|id|', __('Data Source Item Fields'), true, true, true, 0);
		draw_nontemplated_fields_custom_data($data['id'], 'value_|id|', __('Custom Data'), true, true, 0);

		form_hidden_box('save_component_data','1','');

		html_end_box(true, true);
	}

	if (((isset_request_var('id')) || (isset_request_var('new'))) && (empty($data['data_template_id']))) {
		html_start_box( __('Data Source'), '100%', true, '3', 'center', '');

		$form_array = array();

		foreach ($struct_data_source as $field_name => $field_array) {
			$form_array += array($field_name => $struct_data_source[$field_name]);

			if (($field_array['method'] != 'header') && ($field_array['method'] != 'spacer' )){
				if (!(($use_data_template == false) || (!empty($data_template_data['t_' . $field_name])) || ($field_array['flags'] == 'NOTEMPLATE'))) {
					$form_array[$field_name]['description'] = '';
				}

				$form_array[$field_name]['value'] = (isset($data[$field_name]) ? $data[$field_name] : '');
				$form_array[$field_name]['form_id'] = (empty($data['id']) ? '0' : $data['id']);

				if (!(($use_data_template == false) || (!empty($data_template_data['t_' . $field_name])) || ($field_array['flags'] == 'NOTEMPLATE'))) {
					$form_array[$field_name]['method'] = 'template_' . $form_array[$field_name]['method'];
				}
			}
		}

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => inject_form_variables($form_array, (isset($data) ? $data : array()))
			)
		);

		html_end_box(true, true);

		/* fetch ALL rrd's for this data source */
		if (!isempty_request_var('id')) {
			$template_data_rrds = db_fetch_assoc_prepared('SELECT id, data_source_name
				FROM data_template_rrd
				WHERE local_data_id = ?
				ORDER BY data_source_name',
				array(get_request_var('id')));
		}

		/* select the first "rrd" of this data source by default */
		if (isempty_request_var('view_rrd')) {
			set_request_var('view_rrd', (isset($template_data_rrds[0]['id']) ? $template_data_rrds[0]['id'] : '0'));
		}

		/* get more information about the rrd we chose */
		if (!isempty_request_var('view_rrd')) {
			$local_data_template_rrd_id = db_fetch_cell_prepared('SELECT local_data_template_rrd_id
				FROM data_template_rrd
				WHERE id = ?',
				array(get_request_var('view_rrd')));

			$rrd = db_fetch_row_prepared('SELECT *
				FROM data_template_rrd
				WHERE id = ?',
				array(get_request_var('view_rrd')));

			$rrd_template = db_fetch_row_prepared('SELECT *
				FROM data_template_rrd
				WHERE id = ?',
				array($local_data_template_rrd_id));

			$header_label = __('[edit: %s]', $rrd['data_source_name']);
		} else {
			$header_label = '';
		}

		$i = 0;
		if (isset($template_data_rrds)) {
			if (cacti_sizeof($template_data_rrds) > 1) {
				/* draw the data source tabs on the top of the page */
				print "	<table class='tabs'><tr>";

				foreach ($template_data_rrds as $template_data_rrd) {
					$i++;
					print "	<td " . (($template_data_rrd['id'] == get_request_var('view_rrd')) ? "class='even'" : "class='odd'") . " style='width:" . ((strlen($template_data_rrd['data_source_name']) * 9) + 50) . ";text-align:center;' class='tab'>
						<span class='textHeader'><a href='" . html_escape('data_sources.php?action=ds_edit&id=' . get_request_var('id') . '&view_rrd=' . $template_data_rrd['id']) . "'>$i: " . html_escape($template_data_rrd['data_source_name']) . '</a>' . (($use_data_template == false) ? " <a class='pic deleteMarker fa fa-times' href='" . html_escape('data_sources.php?action=rrd_remove&id=' . $template_data_rrd['id'] . '&local_data_id=' . get_request_var('id')) . "' title='" . __esc('Delete') . "'></a>" : '') . "</span>
						</td>";
					print "<td style='width:1px;'></td>";
				}

				print "<td></td></tr></table>";
			} elseif (cacti_sizeof($template_data_rrds) == 1) {
				set_request_var('view_rrd', $template_data_rrds[0]['id']);
			}
		}

		html_start_box('', '100%', true, '3', 'center', '');

		print "<div class='tableHeader'>
			<div class='tableSubHeaderColumn left'>
				" . __esc('Data Source Item %s', $header_label) . "
			</div>
			<div class='tableSubHeaderColumn right'>
				" . ((!isempty_request_var('id') && (empty($data_template['id']))) ? "<a class='linkOverDark' href='" . html_escape('data_sources.php?action=rrd_add&id=' . get_request_var('id')) . "'>" . __('New') . "</a>&nbsp;" : '') . "
			</div>
		</div>";

		/* data input fields list */
		if ((empty($data['data_input_id'])) || (db_fetch_cell_prepared('SELECT type_id FROM data_input WHERE id = ?', array($data['data_input_id'])) > '1')) {
			unset($struct_data_source_item['data_input_field_id']);
		} else {
			$struct_data_source_item['data_input_field_id']['sql'] = "SELECT id,CONCAT(data_name,' - ',name) as name FROM data_input_fields WHERE data_input_id=" . $data['data_input_id'] . " and input_output='out' and update_rra='on' order by data_name,name";
		}

		$form_array = array();

		foreach ($struct_data_source_item as $field_name => $field_array) {
			$form_array += array($field_name => $struct_data_source_item[$field_name]);

			if (($field_array['method'] != 'header') && ($field_array['method'] != 'spacer' )){
				if (!(($use_data_template == false) || ($rrd_template['t_' . $field_name] == 'on'))) {
					$form_array[$field_name]['description'] = '';
				}

				$form_array[$field_name]['value'] = (isset($rrd) ? $rrd[$field_name] : '');

				if (!(($use_data_template == false) || ($rrd_template['t_' . $field_name] == 'on'))) {
					$form_array[$field_name]['method'] = 'template_' . $form_array[$field_name]['method'];
				}
			}
		}

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => array(
					'data_template_rrd_id' => array(
						'method' => 'hidden',
						'value' => (isset($rrd) ? $rrd['id'] : '0')
					),
					'local_data_template_rrd_id' => array(
						'method' => 'hidden',
						'value' => (isset($rrd) ? $rrd['local_data_template_rrd_id'] : '0')
					)
				) + $form_array
			)
		);

		html_end_box(true, true);

		/* data source data goes here */
		data_edit(false);

		form_hidden_box('current_rrd', get_request_var('view_rrd'), '0');
	}

	/* display the debug mode box if the user wants it */
	if ((isset($_SESSION['ds_debug_mode'])) && (isset_request_var('id'))) {
		?>
		<table style='width:100%'>
			<tr>
				<td>
					<span class='textInfo'><?php print __('Data Source Debug');?></span><br>
					<pre><?php print @rrdtool_function_create(get_request_var('id'), true);?></pre>
				</td>
			</tr>
		</table>
		<?php
	}

	/* display the debug mode box if the user wants it */
	if ((isset($_SESSION['ds_info_mode'])) && (isset_request_var('id'))) {
		?>
		<table style='width:100%'>
			<tr>
				<td><?php
				$rrd_info = rrdtool_function_info(get_request_var('id'));

				if (cacti_sizeof($rrd_info['rra'])) {
					$diff = rrdtool_cacti_compare(get_request_var('id'), $rrd_info);

					rrdtool_info2html($rrd_info, $diff);

					if (cacti_sizeof($diff)) {
						html_start_box(__('RRDtool Tune Info'), '100%', '', '3', 'center', '');

						rrdtool_tune($rrd_info['filename'], $diff, true);

						html_end_box();
					}
				}
				?></td>
			</tr>
		</table>
		<?php
	}

	if ((isset_request_var('id')) || (isset_request_var('new'))) {
		form_hidden_box('save_component_data_source','1','');
	} else {
		form_hidden_box('save_component_data_source_new','1','');
	}

	form_save_button('data_sources.php');

	api_plugin_hook('data_source_edit_bottom');

	bottom_footer();
}

function get_poller_interval($seconds, $data_source_profile_id) {
	if ($seconds == 0 || $data_source_profile_id == 0) {
		return '<em>' . __('External') . '</em>';
	}else if ($seconds < 60) {
		return '<em>' . __('%d Seconds', $seconds) . '</em>';
	}else if ($seconds == 60) {
		return  __('1 Minute');
	} else {
		return '<em>' . __('%d Minutes', ($seconds / 60)) . '</em>';
	}
}

function validate_data_source_vars() {
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
		'rfilter' => array(
			'filter' => FILTER_VALIDATE_IS_REGEX,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name_cache',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'site_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'profile' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'orphans' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_ds');
	/* ================= input validation ================= */
}

function ds() {
	global $ds_actions, $item_rows, $sampling_intervals;

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	if (get_request_var('host_id') > 0) {
		$host = db_fetch_row_prepared('SELECT hostname
			FROM host
			WHERE id = ?',
			array(get_request_var('host_id')));
	} else {
		$host = array();
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'data_sources.php' +
			'?host_id=' + $('#host_id').val() +
			'&site_id=' + $('#site_id').val() +
			'&rfilter=' + base64_encode($('#rfilter').val()) +
			'&rows=' + $('#rows').val() +
			'&status=' + $('#status').val() +
			'&profile=' + $('#profile').val() +
			'&orphans=' + $('#orphans').is(':checked') +
			'&template_id=' + $('#template_id').val() +
			'&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'data_sources.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter()
		});

		$('#clear').click(function() {
			clearFilter()
		});

		$('#form_data_sources').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	if (read_config_option('grds_creation_method') == 1 ) {
		if (get_request_var('host_id') == '-1') {
			$new_host_id = 0;
		} else {
			$new_host_id = get_request_var('host_id');
		}

		$add_url = html_escape('data_sources.php?action=ds_edit&host_id=' . $new_host_id);
	} else {
		$add_url = '';
	}

	if (get_request_var('site_id') > 0) {
		$host_where = 'site_id = ' . get_request_var('site_id');
	} else {
		$host_where = '';
	}

	if (get_request_var('host_id') == -1) {
		$header = __('Data Sources [ All Devices ]');
	} elseif (get_request_var('host_id') == 0) {
		$header = __('Data Sources [ Non Device Based ]');
	} else {
		$description = db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array(get_request_var('host_id')));
		$header = __esc('Data Sources [ %s ]', $description);
	}

	html_start_box($header, '100%', '', '3', 'center', $add_url);

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_data_sources' name='form_data_sources' action='data_sources.php'>
			<table class='filterTable'>
				<tr>
					<?php print html_site_filter(get_request_var('site_id'));?>
					<?php print html_host_filter(get_request_var('host_id'), 'applyFilter', $host_where);?>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='template_id' name='template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php

							$templates = db_fetch_assoc('SELECT DISTINCT data_template.id, data_template.name
								FROM data_template
								INNER JOIN data_template_data
								ON data_template.id = data_template_data.data_template_id
								WHERE data_template_data.local_data_id > 0
								ORDER BY data_template.name');

							if (cacti_sizeof($templates)) {
								foreach ($templates as $template) {
									print "<option value='" . $template['id'] . "'"; if (get_request_var('template_id') == $template['id']) { print ' selected'; } print '>' . html_escape($template['name']) . '</option>';
								}
							}
							?>

						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Profile');?>
					</td>
					<td>
						<select id='profile' name='profile' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('profile') == '-1' ? ' selected>':'>') . __('All');?></option>
							<?php
							$profiles = array_rekey(db_fetch_assoc('SELECT id, name FROM data_source_profiles ORDER BY name'), 'id', 'name');

							if (cacti_sizeof($profiles)) {
								foreach ($profiles as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('profile') == $key) { print ' selected'; } print '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Status');?>
					</td>
					<td>
						<select id='status' name='status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('status') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='1'<?php if (get_request_var('status') == '1') {?> selected<?php }?>><?php print __('Enabled');?></option>
							<option value='2'<?php if (get_request_var('status') == '2') {?> selected<?php }?>><?php print __('Disabled');?></option>
							<option value='3'<?php if (get_request_var('status') == '3') {?> selected<?php }?>><?php print __('Bad Indexes');?></option>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='orphans' onChange='applyFilter()' <?php print (get_request_var('orphans') == 'true' || get_request_var('orphans') == 'on' ? 'checked':'');?>>
   	                    	<label for='orphans' title='<?php print __esc('Note that this query may take some time to run.');?>'><?php print __('Orphaned');?></label>
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
						<input type='text' class='ui-state-default ui-corner-all' id='rfilter' size='55' value='<?php print html_escape_request_var('rfilter');?>' onChange='applyFilter()'>
					</td>
					<td>
						<?php print __('Data Sources');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . '</option>';
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

	/* form the 'where' clause for our main sql query */
	if (get_request_var('rfilter') != '') {
		$sql_where1 = "WHERE (dtd.name_cache RLIKE '" . get_request_var('rfilter') . "'" .
			" OR dtd.local_data_id RLIKE '" . get_request_var('rfilter') . "'" .
			" OR dt.name RLIKE '" . get_request_var('rfilter') . "'" .
			" OR dl.id = '" . get_request_var('rfilter') . "')";
	} else {
		$sql_where1 = '';
	}
	$sql_where2 = '';

	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	} elseif (isempty_request_var('host_id')) {
		$sql_where1 .= ($sql_where1 != '' ? ' AND':'WHERE') . ' (dl.host_id=0 OR dl.host_id IS NULL)';
		$sql_where2 .= ' AND (gl.host_id=0 OR gl.host_id IS NULL)';
	} elseif (!isempty_request_var('host_id')) {
		$sql_where1 .= ($sql_where1 != '' ? ' AND':'WHERE') . ' dl.host_id=' . get_request_var('host_id');
		$sql_where2 .= ' AND gl.host_id=' . get_request_var('host_id');
	}

	if (get_request_var('site_id') == '-1') {
		/* Show all items */
	} elseif (isempty_request_var('site_id')) {
		$sql_where1 .= ($sql_where1 != '' ? ' AND':'WHERE') . ' (h.site_id=0 OR h.site_id IS NULL)';
		$sql_where2 .= ' AND (h.site_id=0 OR h.site_id IS NULL)';
	} elseif (!isempty_request_var('site_id')) {
		$sql_where1 .= ($sql_where1 != '' ? ' AND':'WHERE') . ' h.site_id=' . get_request_var('site_id');
		$sql_where2 .= ' AND h.site_id=' . get_request_var('site_id');
	}

	if (get_request_var('template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('template_id') == '0') {
		$sql_where1 .= ($sql_where1 != '' ? ' AND':'WHERE') . ' dtd.data_template_id=0';
	} elseif (!isempty_request_var('template_id')) {
		$sql_where1 .= ($sql_where1 != '' ? ' AND':'WHERE') . ' dtd.data_template_id=' . get_request_var('template_id');
	}

	if (get_request_var('profile') == '-1') {
		/* Show all items */
	} else {
		$sql_where1 .= ($sql_where1 != '' ? ' AND':'WHERE') . ' dtd.data_source_profile_id=' . get_request_var('profile');
	}

	if (get_request_var('status') == '-1') {
		/* Show all items */
	} elseif (get_request_var('status') == '1') {
		$sql_where1 .= ($sql_where1 != '' ? ' AND':'WHERE') . ' (dtd.active = "on" AND h.disabled = "")';
	} elseif (get_request_var('status') == '2') {
		$sql_where1 .= ($sql_where1 != '' ? ' AND':'WHERE') . ' (dtd.active = "" OR h.disabled != "")';
	} elseif (get_request_var('status') == '3') {
		$sql_where1 .= ($sql_where1 != '' ? ' AND':'WHERE') . ' (dl.snmp_index = "" AND dl.snmp_query_id > 0)';
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	if (get_request_var('orphans') == 'true') {
		$sql_where1 .= ($sql_where1 != '' ? ' AND ':'WHERE ') . '((dl.snmp_index = "" AND dl.snmp_query_id > 0) OR dtr.local_graph_id IS NULL)';

		$orphan_join = "INNER JOIN (
			SELECT DISTINCT dtr.local_data_id, task_item_id, local_graph_id
			FROM graph_templates_item AS gti
			INNER JOIN graph_local AS gl
			ON gl.id = gti.local_graph_id
			LEFT JOIN data_template_rrd AS dtr
			ON dtr.id = gti.task_item_id
			LEFT JOIN host AS h
			ON h.id = gl.host_id
			WHERE graph_type_id IN (4,5,6,7,8,20)
			AND task_item_id IS NULL
			AND cdef_id NOT IN (
				SELECT c.id
				FROM cdef AS c
				INNER JOIN cdef_items AS ci
				ON c.id = ci.cdef_id
				WHERE (ci.type = 4 OR (ci.type = 6 AND value LIKE '%DATA_SOURCE%'))
			)
			$sql_where2
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

		$total_rows = get_total_row_data($_SESSION['sess_user_id'], $sql, array(), 'data_source');

		$data_sources = db_fetch_assoc("SELECT dtr.local_graph_id, dtd.local_data_id,
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
			$sql_limit");
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

		$total_rows = get_total_row_data($_SESSION['sess_user_id'], $sql, array(), 'data_source');

		$data_sources = db_fetch_assoc("SELECT dtd.local_data_id, dtd.name_cache, dtd.active,
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
			$sql_limit");
	}

	$nav = html_nav_bar('data_sources.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 7, __('Data Sources'), 'page', 'main');

	form_start('data_sources.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name_cache' => array(
			'display' => __('Data Source Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this Data Source. Generally programmatically generated from the Data Template definition.')
		),
		'local_data_id' => array(
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal database ID for this Data Source. Useful when performing automation or debugging.')
		),
		'nosort0' => array(
			'display' => __('Graphs'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('The number of Graphs and Aggregate Graphs that are using the Data Source.')
		),
		'nosort1' => array(
			'display' => __('Poller Interval'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The frequency that data is collected for this Data Source.')
		),
		'nosort2' => array(
			'display' => __('Deletable'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('If this Data Source is no long in use by Graphs, it can be Deleted.')
		),
		'active' => array(
			'display' => __('Active'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('Whether or not data will be collected for this Data Source. Controlled at the Data Template level.')
		),
		'data_template_name' => array(
			'display' => __('Template Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Data Template that this Data Source was based upon.')
		)
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (cacti_sizeof($data_sources)) {
		foreach ($data_sources as $data_source) {
			if (api_data_source_deletable($data_source['local_data_id'])) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			$data_source['data_template_name'] = html_escape($data_source['data_template_name']);
			$data_name_cache = title_trim(html_escape($data_source['name_cache']), read_config_option('max_title_length'));

			if (get_request_var('rfilter') != '') {
				$data_name_cache = filter_value($data_name_cache, get_request_var('rfilter'));
			}

			/* keep copy of data source for comparison */
			$data_source_orig = $data_source;
			$data_source = api_plugin_hook_function('data_sources_table', $data_source);

			/* we're escaping strings here, so no need to escape them on form_selectable_cell */
			if (empty($data_source['data_template_name'])) {
				$data_template_name = '<em>' . __('None') . '</em>';
			} elseif ($data_source_orig['data_template_name'] != $data_source['data_template_name']) {
				/* was changed by plugin, plugin has to take care for html-escaping */
				$data_template_name = $data_source['data_template_name'];
			} elseif (get_request_var('rfilter') != '') {
				$data_template_name = filter_value($data_source['data_template_name'], get_request_var('rfilter'));
			} else {
				$data_template_name = html_escape($data_source['data_template_name']);
			}

			$graphs_aggregates_url = get_graphs_aggregates_url($data_source['local_data_id']);

			if ($data_source['name_cache'] == '') {
				$name = __('Damaged Data Source Name');
			} else {
				$name = $data_source['name_cache'];
			}

			form_alternate_row('line' . $data_source['local_data_id'], true, $disabled);
			form_selectable_cell(filter_value(title_trim($name, read_config_option('max_title_length')), get_request_var('rfilter'), 'data_sources.php?action=ds_edit&id=' . $data_source['local_data_id']), $data_source['local_data_id']);
			form_selectable_cell($data_source['local_data_id'], $data_source['local_data_id'], '', 'right');

			// Show link to Graphs and Aggregates
			form_selectable_cell($graphs_aggregates_url, $data_source['local_data_id'], '', 'center');

			form_selectable_cell(get_poller_interval($data_source['rrd_step'], $data_source['data_source_profile_id']), $data_source['local_data_id']);
			form_selectable_cell(api_data_source_deletable($data_source['local_data_id']) ? __('Yes') : __('No'), $data_source['local_data_id']);
			form_selectable_cell(($data_source['active'] == 'on' ? __('Yes'):__('No')), $data_source['local_data_id']);
			form_selectable_cell($data_template_name, $data_source['local_data_id']);
			form_checkbox_cell($name, $data_source['local_data_id'], $disabled);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . __('No Data Sources Found') . "</em></td></tr>";
	}

	html_end_box(false);

	if (cacti_sizeof($data_sources)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($ds_actions);

	form_end();
}

function get_graphs_aggregates_url($local_data_id) {
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
		array($local_data_id));

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
		array($local_data_id));

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

