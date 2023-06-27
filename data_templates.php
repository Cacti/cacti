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
include_once('./lib/api_tree.php');
include_once('./lib/html_tree.php');
include_once('./lib/poller.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');

$ds_actions = array(
	1 => __('Delete'),
	2 => __('Duplicate'),
	3 => __('Change Profile')
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
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

		header('Location: data_templates.php?header=false');
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
	if (isset_request_var('save_component_template')) {
		/* ================= input validation ================= */
		get_filter_request_var('data_input_id');
		get_filter_request_var('data_template_id');
		get_filter_request_var('data_template_data_id');
		get_filter_request_var('data_template_rrd_id');
		get_filter_request_var('data_source_type_id');
		get_filter_request_var('data_source_profile_id');
		get_filter_request_var('rrd_heartbeat');
		/* ==================================================== */

		/* save: data_template */
		$save1['id']   = get_request_var('data_template_id');
		$save1['hash'] = get_hash_data_template(get_request_var('data_template_id'));
		$save1['name'] = form_input_validate(get_nfilter_request_var('template_name'), 'template_name', '', false, 3);

		/* save: data_template_data */
		$save2['id']            = get_request_var('data_template_data_id');
		$save2['local_data_template_data_id'] = 0;
		$save2['local_data_id'] = 0;

		$save2['data_input_id'] = form_input_validate(get_request_var('data_input_id'), 'data_input_id', '^[0-9]+$', true, 3);
		$save2['t_name']        = form_input_validate((isset_request_var('t_name') ? get_nfilter_request_var('t_name') : ''), 't_name', '', true, 3);
		$save2['name']          = form_input_validate(get_nfilter_request_var('name'), 'name', '', (isset_request_var('t_name') ? true : false), 3);
		$save2['t_active']      = form_input_validate((isset_request_var('t_active') ? get_nfilter_request_var('t_active') : ''), 't_active', '', true, 3);
		$save2['active']        = form_input_validate((isset_request_var('active') ? get_nfilter_request_var('active') : ''), 'active', '', true, 3);

		$rrd_step = db_fetch_cell_prepared('SELECT step
			FROM data_source_profiles
			WHERE id = ?',
			array(get_request_var('data_source_profile_id')));

		$rrd_heartbeat = db_fetch_cell_prepared('SELECT heartbeat
			FROM data_source_profiles
			WHERE id = ?',
			array(get_request_var('data_source_profile_id')));

		$save2['rrd_step'] = $rrd_step;

		$save2['t_data_source_profile_id'] = form_input_validate((isset_request_var('t_data_source_profile_id') ? get_nfilter_request_var('t_data_source_profile_id') : ''), 't_data_source_profile_id', '', true, 3);
		$save2['data_source_profile_id']   = form_input_validate(get_request_var('data_source_profile_id'), 'data_source_profile_id', '^[0-9]+$', (isset_request_var('data_source_profile_id') ? true : false), 3);

		/* save: data_template_rrd */
		$save3['id']                    = get_request_var('data_template_rrd_id');
		$save3['hash']                  = get_hash_data_template(get_request_var('data_template_rrd_id'), 'data_template_item');
		$save3['local_data_template_rrd_id'] = 0;
		$save3['local_data_id']         = 0;

		$save3['t_rrd_maximum']         = form_input_validate((isset_request_var('t_rrd_maximum') ? get_nfilter_request_var('t_rrd_maximum') : ''), 't_rrd_maximum', '', true, 3);

		$save3['rrd_maximum']           = form_input_validate(get_nfilter_request_var('rrd_maximum'), 'rrd_maximum', '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U|\|query_ifSpeed\|$', (isset_request_var('t_rrd_maximum') ? true : false), 3);

		$save3['t_rrd_minimum']         = form_input_validate((isset_request_var('t_rrd_minimum') ? get_nfilter_request_var('t_rrd_minimum') : ''), 't_rrd_minimum', '', true, 3);

		$save3['rrd_minimum']           = form_input_validate(get_nfilter_request_var('rrd_minimum'), 'rrd_minimum', '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$', (isset_request_var('t_rrd_minimum') ? true : false), 3);

		$save3['rrd_heartbeat']         = $rrd_heartbeat;

		$save3['t_data_source_type_id'] = form_input_validate((isset_request_var('t_data_source_type_id') ? get_nfilter_request_var('t_data_source_type_id') : ''), 't_data_source_type_id', '', true, 3);

		$save3['data_source_type_id']   = form_input_validate(get_request_var('data_source_type_id'), 'data_source_type_id', '^[0-9]+$', true, 3);

		$save3['t_data_source_name']    = form_input_validate((isset_request_var('t_data_source_name') ? get_nfilter_request_var('t_data_source_name') : ''), 't_data_source_name', '', true, 3);

		$save3['data_source_name']      = form_input_validate(get_nfilter_request_var('data_source_name'), 'data_source_name', '^[a-zA-Z0-9_]{1,19}$', (isset_request_var('t_data_source_name') ? true : false), 3);

		$save3['t_data_input_field_id'] = form_input_validate((isset_request_var('t_data_input_field_id') ? get_nfilter_request_var('t_data_input_field_id') : ''), 't_data_input_field_id', '', true, 3);

		$save3['data_input_field_id']   = form_input_validate((isset_request_var('data_input_field_id') ? get_nfilter_request_var('data_input_field_id') : '0'), 'data_input_field_id', '', true, 3);

		if ($save3['rrd_minimum'] != 'U' && $save3['rrd_maximum'] != 'U') {
			if ($save3['rrd_minimum'] >= $save3['rrd_maximum']) {
				raise_message(43);

				$_SESSION['sess_error_fields']['rrd_maximum'] = 'rrd_maximum';

				header('Location: data_templates.php?header=false&action=template_edit&id=' . (empty($data_template_id) ? get_request_var('data_template_id') : $data_template_id) . (isempty_request_var('current_rrd') ? '' : '&view_rrd=' . (get_nfilter_request_var('current_rrd') ? get_nfilter_request_var('current_rrd') : get_request_var('data_template_rrd_id'))));
				exit;
			}
		}

		/* ok, first pull out all 'input' values so we know how much to save */
		$input_fields = db_fetch_assoc_prepared("SELECT id, input_output, regexp_match,
			allow_nulls, type_code, data_name
			FROM data_input_fields
			WHERE data_input_id = ?
			AND input_output = 'in'",
			array(get_request_var('data_input_id')));

		/* pass 1 for validation */
		if (cacti_sizeof($input_fields)) {
			foreach ($input_fields as $input_field) {
				$form_value = 'value_' . $input_field['data_name'];

				if ((isset_request_var($form_value)) && ($input_field['type_code'] == '')) {
					if ((isset_request_var('t_' . $form_value)) &&
						(get_nfilter_request_var('t_' . $form_value) == 'on')) {
						$not_required = true;
					} elseif ($input_field['allow_nulls'] == 'on') {
						$not_required = true;
					} else {
						$not_required = false;
					}

					form_input_validate(get_nfilter_request_var($form_value), 'value_' . $input_field['data_name'], $input_field['regexp_match'], $not_required, 3);
				}
			}
		}

		if (!is_error_message()) {
			$data_template_id = sql_save($save1, 'data_template');

			if ($data_template_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (!is_error_message()) {
			$save2['data_template_id'] = $data_template_id;
			$data_template_data_id = sql_save($save2, 'data_template_data');

			if ($data_template_data_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		/* update actual host template information for live hosts */
		if ((!is_error_message()) && ($save2['id'] > 0)) {
			db_execute_prepared('UPDATE data_template_data
				SET data_input_id = ?
				WHERE data_template_id = ?',
				array(get_request_var('data_input_id'), $data_template_id));

			db_execute_prepared('UPDATE data_template_rrd
				SET rrd_heartbeat = ?
				WHERE data_template_id = ?
				AND local_data_id = 0',
				array($rrd_heartbeat, $data_template_id));
		}

		if (!is_error_message()) {
			$save3['data_template_id'] = $data_template_id;
			$data_template_rrd_id = sql_save($save3, 'data_template_rrd');

			if ($data_template_rrd_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (!is_error_message()) {
			/* Lets make sure we don't have any fields not set */
			$data_template_fields = db_fetch_assoc_prepared('SELECT
					dt.id, dt.name, dtd.name, di.hash, di.name, di.type_id,
					dtr.id dtr_id, dtr.data_source_name, dif.id dif_id, dif.name, dif.data_name,
					dif.input_output, dif.update_rra
				FROM data_template dt
				INNER JOIN data_template_data dtd
				ON dt.id = dtd.data_template_id
				INNER JOIN data_input di
				ON dtd.data_input_id = di.id
				INNER JOIN data_template_rrd dtr
				ON dt.id = dtr.data_template_id
				LEFT OUTER JOIN data_input_fields dif
				ON dtr.data_input_field_id = dif.id
				WHERE di.type_id in (1,5) AND dt.id = ? AND dif.id IS NULL',
				array($data_template_id));
			if (cacti_sizeof($data_template_fields)) {
				foreach ($data_template_fields as $data_template_field) {
					raise_message('data_template_rrd_' . $data_template_field['dtr_id'], __('Field "%s" is missing an Output Field', $data_template_field['data_source_name']), MESSAGE_LEVEL_WARN);
				}
			}
		}

		if (!is_error_message()) {
			if (!isempty_request_var('data_template_id')) {
				/* push out all data source settings to child data source using this template */
				push_out_data_source($data_template_data_id);
				push_out_data_source_item($data_template_rrd_id);

				db_execute_prepared('DELETE
					FROM data_input_data
					WHERE data_template_data_id = ?',
					array($data_template_data_id));

				if (cacti_sizeof($input_fields)) {
					foreach ($input_fields as $input_field) {
						$form_value = 'value_' . $input_field['data_name'];

						if (isset_request_var($form_value)) {
							/* save the data into the 'host_template_data' table */
							if (isset_request_var('t_value_' . $input_field['data_name'])) {
								$template_this_item = 'on';
							} else {
								$template_this_item = '';
							}

							if ((!empty($form_value)) || (!isempty_request_var('t_value_' . $input_field['data_name']))) {
								/* unusual case where a form value comes back as an array
								 * this should be cleaned up in the database repair script. */
								$value = get_nfilter_request_var($form_value);
								if (is_array($value)) {
									$value = trim($value[0]);
								} else {
									$value = trim($value);
								}

								db_execute_prepared('INSERT INTO data_input_data
									(data_input_field_id, data_template_data_id, t_value, value)
									VALUES (?, ?, ?, ?)',
									array($input_field['id'], $data_template_data_id, $template_this_item, $value));
							}
						}
					}
				}

				/* push out all "custom data" for this data source template */
				push_out_data_source_custom_data($data_template_id);

				/* push out the hosts that use the data template */
				$hosts = array_rekey(
					db_fetch_assoc_prepared('SELECT DISTINCT host_id
						FROM data_local
						WHERE data_template_id = ?',
						array($data_template_id)),
					'host_id', 'host_id'
				);

				if (cacti_sizeof($hosts)) {
					foreach($hosts as $host_id) {
						push_out_host($host_id, 0, $data_template_id);
					}
				}

				/* push out field mappings for the data collector */
				/* its important to delete first due to the possibility that
				 * the field names were changed */
				db_execute_prepared('DELETE FROM poller_data_template_field_mappings
					WHERE data_template_id = ?',
					array($data_template_id));

				db_execute_prepared('REPLACE INTO poller_data_template_field_mappings
					SELECT dtr.data_template_id, dif.data_name,
					GROUP_CONCAT(DISTINCT dtr.data_source_name ORDER BY dtr.data_source_name) AS data_source_names,
					NOW() AS last_updated
					FROM graph_templates_item AS gti
					INNER JOIN data_template_rrd AS dtr
					ON gti.task_item_id = dtr.id
					INNER JOIN data_input_fields AS dif
					ON dtr.data_input_field_id = dif.id
					WHERE dtr.local_data_id = 0
					AND gti.local_graph_id = 0
					AND dtr.data_template_id = ?
					GROUP BY dtr.data_template_id, dif.data_name',
					array($data_template_id));
			}
		}

		header('Location: data_templates.php?header=false&action=template_edit&id=' . (empty($data_template_id) ? get_request_var('data_template_id') : $data_template_id) . (isempty_request_var('current_rrd') ? '' : '&view_rrd=' . (get_nfilter_request_var('current_rrd') ? get_nfilter_request_var('current_rrd') : $data_template_rrd_id)));
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
			if (get_nfilter_request_var('drp_action') == '1') { // delete
				$data_template_data_ids = db_fetch_cell('SELECT GROUP_CONCAT(id)
					FROM data_template_data
					WHERE ' . array_to_sql_or($selected_items, 'data_template_id') . '
					AND local_data_id=0');

				db_execute('DELETE FROM data_template_data WHERE ' . array_to_sql_or($selected_items, 'data_template_id') . ' AND local_data_id=0');
				db_execute('DELETE FROM data_template_rrd WHERE ' . array_to_sql_or($selected_items, 'data_template_id') . ' AND local_data_id=0');
				db_execute('DELETE FROM snmp_query_graph_rrd WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));
				db_execute('DELETE FROM snmp_query_graph_rrd_sv WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));
				db_execute('DELETE FROM data_template WHERE ' . array_to_sql_or($selected_items, 'id'));

				/* "undo" any graph that is currently using this template */
				db_execute('UPDATE data_template_data
					SET local_data_template_data_id = 0, data_template_id = 0
					WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));

				db_execute('UPDATE data_template_rrd
					SET local_data_template_rrd_id = 0, data_template_id = 0
					WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));

				db_execute('UPDATE data_local
					SET data_template_id = 0
					WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));

				/* delete data_input_data information */
				if ($data_template_data_ids != '') {
					db_execute('DELETE FROM data_input_data WHERE data_template_data_id IN(' . $data_template_data_ids . ')');
				}
			} elseif (get_nfilter_request_var('drp_action') == '2') { // duplicate
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					api_duplicate_data_source(0, $selected_items[$i], get_nfilter_request_var('title_format'));
				}
			} elseif (get_nfilter_request_var('drp_action') == '3') { // change data source profile
				$step = db_fetch_cell_prepared('SELECT step
					FROM data_source_profiles
					WHERE id = ?',
					array(get_filter_request_var('data_source_profile_id')));

				$heartbeat = db_fetch_cell_prepared('SELECT heartbeat
					FROM data_source_profiles
					WHERE id = ?',
					array(get_filter_request_var('data_source_profile_id')));

				if (!empty($step)) {
					for ($i=0;($i<cacti_count($selected_items));$i++) {
						db_execute_prepared('UPDATE data_template_data
							SET data_source_profile_id = ?,
							rrd_step = ?
							WHERE data_template_id = ?
							AND local_data_id = 0',
							array(get_filter_request_var('data_source_profile_id'), $step, $selected_items[$i]));

						db_execute_prepared('UPDATE data_template_rrd
							SET rrd_heartbeat = ?
							WHERE data_template_id = ?
							AND local_data_id = 0',
							array($heartbeat, $selected_items[$i]));
					}
				}
			}
		}

		header('Location: data_templates.php?header=false');
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

			$ds_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM data_template WHERE id = ?', array($matches[1]))) . '</li>';
			$ds_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('data_templates.php');

	html_start_box($ds_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($ds_array) && cacti_sizeof($ds_array)) {
		if (get_request_var('drp_action') == '1') { // delete
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to delete the following Data Template(s).  Any data sources attached to these templates will become individual Data Source(s) and all Templating benefits will be removed.') . "</p>
					<div class='itemlist'><ul>$ds_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Delete Data Template(s)') . "'>";
		} elseif (get_request_var('drp_action') == '2') { // duplicate
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to duplicate the following Data Template(s). You can optionally change the title format for the new Data Template(s).') . "</p>
					<div class='itemlist'><ul>$ds_list</ul></div>
					<p>" . __('Title Format:') . '<br>'; form_text_box('title_format', '<' . __('template_title') . '> (1)', '', '255', '30', 'text'); print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Duplicate Data Template(s)') . "'>";
		} elseif (get_request_var('drp_action') == '3') { // change profile
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to change the default Data Source Profile for the following Data Template(s).') . "</p>
					<div class='itemlist'><ul>$ds_list</ul></div>
					<p>" . __('New Data Source Profile') . '<br>';

					$available_profiles = db_fetch_assoc('SELECT id, name FROM data_source_profiles ORDER BY name');
					form_dropdown('data_source_profile_id',$available_profiles, 'name', 'id', '', '', '');

			print "</p>
				<p>" . __('NOTE: This change only will affect future Data Sources and does not alter existing Data Sources.') . "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Change Data Source Profile') . "'>";
		}
	} else {
		raise_message(40);
		header('Location: data_templates.php?header=false');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($ds_array) ? serialize($ds_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* ----------------------------
    template - Data Templates
   ---------------------------- */

function template_rrd_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('data_template_id');
	/* ==================================================== */

	$children = db_fetch_assoc_prepared('SELECT id
		FROM data_template_rrd
		WHERE local_data_template_rrd_id = ?
		OR id = ?',
		array(get_request_var('id'), get_request_var('id')));

	if (cacti_sizeof($children)) {
		foreach ($children as $item) {
			db_execute_prepared('DELETE FROM data_template_rrd WHERE id = ?', array($item['id']));
			db_execute_prepared('DELETE FROM snmp_query_graph_rrd WHERE data_template_rrd_id = ?', array($item['id']));
			db_execute_prepared('UPDATE graph_templates_item SET task_item_id = 0 WHERE task_item_id = ?', array($item['id']));
		}
	}

	header('Location: data_templates.php?action=template_edit&id=' . get_request_var('data_template_id'));
}

function template_rrd_add() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('local_data_id');
	/* ==================================================== */

	$hash = get_hash_data_template(0, 'data_template_item');

	/* check for duplicated data source name */
	$i = 0;
	$dsname = 'ds';
	while (true) {
		$exists = db_fetch_cell_prepared('SELECT data_source_name
			FROM data_template_rrd
			WHERE data_source_name = ?
			AND data_template_id = ?',
			array($dsname, get_request_var('id')));

		if (empty($exists)) {
			break;
		} else {
			$i++;
			$dsname = 'ds (' . $i . ')';

			if ($i > 100) break;
		}
	}

	db_execute_prepared('INSERT IGNORE INTO data_template_rrd
		(hash, data_template_id, rrd_maximum, rrd_minimum, rrd_heartbeat, data_source_type_id, data_source_name)
	    VALUES (?, ?, "U", 0, 600, 1, ?)', array($hash, get_request_var('id'), $dsname));

	$data_template_rrd_id = db_fetch_insert_id();

	/* add this data template item to each data source using this data template */
	$children = db_fetch_assoc_prepared('SELECT local_data_id
		FROM data_template_data
		WHERE data_template_id = ?
		AND local_data_id > 0',
		array(get_request_var('id')));

	if (cacti_sizeof($children)) {
		foreach ($children as $item) {
			db_execute_prepared('INSERT IGNORE INTO data_template_rrd
				(local_data_template_rrd_id, local_data_id, data_template_id, rrd_maximum, rrd_minimum, rrd_heartbeat, data_source_type_id, data_source_name)
				VALUES (?, ?, ?, 0, 0, 600, 1, ?)',
				array($data_template_rrd_id, $item['local_data_id'], get_request_var('id'), $dsname));
		}
	}

	header('Location: data_templates.php?action=template_edit&id=' . get_request_var('id') . "&view_rrd=$data_template_rrd_id");
}

function template_edit() {
	global $struct_data_source, $struct_data_source_item, $data_source_types, $fields_data_template_template_edit, $fields_host_edit, $hash_system_data_inputs;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('view_rrd');
	/* ==================================================== */

	$isSNMPGet = false;

	if (!isempty_request_var('id')) {
		$template_data = db_fetch_row_prepared('SELECT dtd.*, data_sources
			FROM (
				SELECT COUNT(*) AS data_sources FROM data_local AS dl
				LEFT JOIN data_template_data AS idtd ON dl.id=idtd.local_data_id
				WHERE idtd.data_template_id = ?
			) AS ds
			INNER JOIN (
				SELECT * FROM data_template_data
				WHERE data_template_id = ? AND local_data_id = 0
			) AS dtd',
			array(get_request_var('id'), get_request_var('id')));

		$template = db_fetch_row_prepared('SELECT *
			FROM data_template
			WHERE id = ?',
			array(get_request_var('id')));

		if (cacti_sizeof($template_data)) {
			$snmp_data = db_fetch_row_prepared('SELECT *
				FROM data_input
				WHERE hash="3eb92bb845b9660a7445cf9740726522"
				AND id = ?',
				array($template_data['data_input_id']));

			if (cacti_sizeof($snmp_data)) {
				$isSNMPGet = true;
			}
		}

		$header_label = __esc('Data Templates [edit: %s]', $template['name']);

		?>
		<table style='width:100%'>
			<tr>
				<td class='textInfo left' style='vertical-align:top;'>
					<?php print html_escape($template['name']);?>
				</td>
				<td class='textInfo right' style='vertical-align:top;'>
					<?php
						$data_input_id = 0;
						if (!empty($template_data['data_input_id'])) {
							$data_input_id = get_nonsystem_data_input($template_data['data_input_id']);
							if (!isset($data_input_id) || $data_input_id == NULL) {
								$data_input_id = 0;
							}
						}

						if ($data_input_id > 0) {
							?><span class='linkMarker'>*</span><a class='hyperLink' href='<?php print htmlspecialchars('data_input.php?action=edit&id=' . $data_input_id);?>'><?php print __('Edit Data Input Method.');?></a><br><?php
						}
					?>
				</td>
			</tr>
		</table>
		<br>
		<?php
	} else {
		$header_label = __('Data Templates [new]');
	}

	form_start('data_templates.php', 'data_templates');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => 'true'),
		'fields' => inject_form_variables($fields_data_template_template_edit, (isset($template) ? $template : array()), (isset($template_data) ? $template_data : array()), $_REQUEST)
		)
	);

	html_end_box(true, true);

	html_start_box(__('Data Source'), '100%', true, '3', 'center', '');

	/* make sure 'data source path' doesn't show up for a template... we should NEVER template this field */
	unset($struct_data_source['data_source_path']);

	$form_array = array();

	foreach ($struct_data_source as $field_name => $field_array) {
		$form_array += array($field_name => $struct_data_source[$field_name]);

		if ($form_array[$field_name]['method'] != 'spacer') {
			$form_array[$field_name]['value'] = (isset($template_data[$field_name]) ? $template_data[$field_name] : '');
		}

		$form_array[$field_name]['form_id'] = (isset($template_data) ? $template_data['data_template_id'] : '0');

		if ($field_array['flags'] == 'ALWAYSTEMPLATE') {
			$form_array[$field_name]['description'] .= '<br><em>' . __('This field is always templated.') . '</em>';
		} else {
			$form_array[$field_name]['sub_checkbox'] = array(
				'name' => 't_' . $field_name,
				'friendly_name' => __esc('Check this checkbox if you wish to allow the user to override the value on the right during Data Source creation.'),
				'value' => (isset($template_data['t_' . $field_name]) ? $template_data['t_' . $field_name] : '')
			);
		}
	}

	$form_array['data_source_profile_id']['sql'] = 'SELECT id, name FROM data_source_profiles ORDER BY name';

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($form_array, (isset($template_data) ? $template_data : array()))
		)
	);

	html_end_box(true, true);

	/* fetch ALL rrd's for this data source */
	if (!isempty_request_var('id')) {
		$template_data_rrds = db_fetch_assoc_prepared('SELECT id, data_source_name
			FROM data_template_rrd
			WHERE data_template_id = ?
			AND local_data_id = 0
			ORDER BY data_source_name',
			array(get_request_var('id')));
	}

	/* select the first "rrd" of this data source by default */
	if (isempty_request_var('view_rrd')) {
		set_request_var('view_rrd', (isset($template_data_rrds[0]['id']) ? $template_data_rrds[0]['id'] : '0'));
	}

	/* get more information about the rrd we chose */
	if (!isempty_request_var('view_rrd')) {
		$template_rrd = db_fetch_row_prepared('SELECT *
			FROM data_template_rrd
			WHERE id = ?',
			array(get_request_var('view_rrd')));
	}

	$i = 0;
	if (isset($template_data_rrds)) {
		if (cacti_sizeof($template_data_rrds) > 1) {
			/* draw the data source tabs on the top of the page */
			print "<div class='tabs' style='float:left;'><nav><ul role='tablist'>\n";

			foreach ($template_data_rrds as $template_data_rrd) {
				print "<li class='subTab'><a " . (($template_data_rrd['id'] == get_request_var('view_rrd')) ? "class='pic selected'" : "class='pic'") . " href='" . html_escape('data_templates.php?action=template_edit&id=' . get_request_var('id') . '&view_rrd=' . $template_data_rrd['id']) . "'>" . ($i+1) . ": " . html_escape($template_data_rrd['data_source_name']) . "</a>" . ($template_data['data_sources'] == 0 ? "<a class='pic deleteMarker fa fa-times' title='" . __esc('Delete') . "' href='" . html_escape('data_templates.php?action=rrd_remove&id=' . $template_data_rrd['id'] . '&data_template_id=' . get_request_var('id')) . "'></a>":"<a class='deleteMarkerDisabled fa fa-times' href='#' title='" . __esc('Data Templates in use can not be modified') . "'></a>") . "</li>\n";

				$i++;
			}

			print "
			</ul></nav>\n
			</div>\n";
		} elseif (cacti_sizeof($template_data_rrds) == 1) {
			set_request_var('view_rrd', $template_data_rrds[0]['id']);
		}
	}

	if (get_request_var('id') > 0) {
		$readOnly = db_fetch_cell_prepared('SELECT id
			FROM data_local
			WHERE data_template_id = ?
			LIMIT 1',
			array(get_request_var('id')));
	} else {
		$readOnly = false;
	}

	if (!$isSNMPGet && !$readOnly) {
		html_start_box(__('Data Source Item [%s]', (isset($template_rrd) ? html_escape($template_rrd['data_source_name']) : '')), '100%', true, '0', 'center', (!isempty_request_var('id') ? 'data_templates.php?action=rrd_add&id=' . get_request_var('id'):''), __('New'));
	} else {
		html_start_box(__('Data Source Item [%s]', (isset($template_rrd) ? html_escape($template_rrd['data_source_name']) : '')), '100%', true, '0', 'center', '', '');
	}

	/* data input fields list */
	if (empty($template_data['data_input_id'])) {
		unset($struct_data_source_item['data_input_field_id']);
	} else {
		$input_type = db_fetch_cell_prepared('SELECT type_id
			FROM data_input
			WHERE id = ?',
			array($template_data['data_input_id']));

		if ($input_type != 1 && $input_type != 5) {
			unset($struct_data_source_item['data_input_field_id']);
		} else {
			$struct_data_source_item['data_input_field_id']['sql'] = "SELECT id, CONCAT(data_name, ' - ', name) AS name FROM data_input_fields WHERE data_input_id=" . $template_data['data_input_id'] . " AND input_output='out' AND update_rra='on' ORDER BY data_name, name";
		}
	}

	$form_array = array();

	foreach ($struct_data_source_item as $field_name => $field_array) {
		$form_array += array($field_name => $struct_data_source_item[$field_name]);

		$form_array[$field_name]['value'] = (isset($template_rrd) ? $template_rrd[$field_name] : '');
		$form_array[$field_name]['sub_checkbox'] = array(
			'name' => 't_' . $field_name,
			'friendly_name' => __esc('Check this checkbox if you wish to allow the user to override the value on the right during Data Source creation.'),
			'value' => (isset($template_rrd) ? $template_rrd['t_' . $field_name] : '')
		);
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $form_array + array(
				'data_template_rrd_id' => array(
					'method' => 'hidden',
					'value' => (isset($template_rrd) ? $template_rrd['id'] : '0')
				)
			)
		)
	);

	html_end_box(true, true);

	$i = 0;
	if (!isempty_request_var('id')) {
		/* get each INPUT field for this data input source */
		$fields = db_fetch_assoc_prepared('SELECT *
			FROM data_input_fields
			WHERE data_input_id = ?
			AND input_output="in" ORDER BY name',
			array($template_data['data_input_id']));

		$name = db_fetch_cell_prepared('SELECT name
			FROM data_input
			WHERE id = ?',
			array($template_data['data_input_id']));

		html_start_box(__('Custom Data [data input: %s]', html_escape($name)), '100%', true, '3', 'center', '');

		/* loop through each field found */
		if (cacti_sizeof($fields) > 0) {
			$class = 'odd';

			foreach ($fields as $field) {
				$data_input_data = db_fetch_row_prepared('SELECT t_value, value
					FROM data_input_data AS did
					WHERE data_template_data_id = ?
					AND data_input_field_id = ?',
					array($template_data['id'], $field['id']));

				// Data Query Key fields
				if (data_input_field_always_checked($field['id'])) {
					$message = __esc('This value is disabled due to it either it value being derived from the Device or special Data Query object that keeps track of critical data Data Query associations.');

					if (cacti_sizeof($data_input_data)) {
						$old_value  = $data_input_data['value'];
						$old_tvalue = 'on';
						$disable    = 'disable';
					} else {
						$old_value  = '';
						$old_tvalue = 'on';
						$disable    = 'disable';
					}
				} elseif ($field['type_code'] == 'host_id') {
					$message = __esc('This value is disabled due to it being derived from the Device and read only.');

					$old_value  = $data_input_data['value'];
					$old_tvalue = '';
					$disable    = 'disable';
				} else {
					$message = __esc('Check this checkbox if you wish to allow the user to override the value on the right during Data Source creation.');

					if (cacti_sizeof($data_input_data)) {
						$old_value  = $data_input_data['value'];
						$old_tvalue = $data_input_data['t_value'];
						$disable    = '';
					} else {
						$old_value  = '';
						$old_tvalue = '';
						$disable    = '';
					}
				}

				if ($field['data_name'] == 'management_ip') {
					$help = $fields_host_edit['hostname']['description'];
				} elseif (isset($fields_host_edit[$field['data_name']])) {
					$help = $fields_host_edit[$field['data_name']]['description'];
				} else {
					$help = $field['name'];
				}

				print "<div class='formRow $class'>";

				if ($class == 'odd') {
					$class = 'even';
				} else {
					$class = 'odd';
				}

				if (preg_match('/^' . VALID_HOST_FIELDS . '$/i', $field['type_code']) && $old_tvalue  == '') {
					$title = __esc('Value will be derived from the Device if this field is left empty.');
				} else {
					$title = '';
				}

				?>
				<div class='formColumnLeft'>
					<div class='formFieldName customDataCheckbox <?php print $disable;?>'><?php form_checkbox('t_value_' . $field['data_name'], $old_tvalue, '', '', '', get_request_var('id'), '', $message);?><?php print html_escape($field['name']);?><div class='formTooltip'><?php print display_tooltip($help);?></div>
					</div>
				</div>
				<div class='formColumnRight <?php print $disable;?>'>
					<?php form_text_box('value_' . $field['data_name'], $old_value, '', '', 30, 'text', 0, '', $title);?>
				</div>
				<?php
				print "</div>";

				$i++;
			}
		} else {
			print '<div style="width:100%;float:left;"><em>' . __('No Input Fields for the Selected Data Input Source') . '</em></div>';
		}

		html_end_box(true, true);
	}

	form_save_button('data_templates.php', 'return');

	?>
	<script type='text/javascript'>

	var readOnly = <?php print $readOnly ? 'true':'false';?>;

	$(function() {
		if (readOnly) {
			// Data Source
			$('#data_input_id').prop('disabled', true).addClass('ui-state-disabled');
			$('#t_data_input_id').prop('disabled', true).addClass('ui-state-disabled');

			if ($('#data_input_id').selectmenu('instance')) {
				$('#data_input_id').selectmenu('disable');
			}

			// Data source attributes
			$('#data_source_name').prop('disabled', true).addClass('ui-state-disabled');
			$('#t_data_source_name').prop('disabled', true).addClass('ui-state-disabled');
			$('#data_source_type_id').prop('disabled', true).addClass('ui-state-disabled');
			$('#t_data_source_type_id').prop('disabled', true).addClass('ui-state-disabled');

			// Custom Data
			$('#value_index_type').prop('disabled', true).addClass('ui-state-disabled');
			$('#t_value_index_type').prop('disabled', true).addClass('ui-state-disabled');
			$('#value_index_value').prop('disabled', true).addClass('ui-state-disabled');
			$('#t_value_index_value').prop('disabled', true).addClass('ui-state-disabled');
			$('#value_output_type').prop('disabled', true).addClass('ui-state-disabled');
			$('#t_value_output_type').prop('disabled', true).addClass('ui-state-disabled');

			if ($('#data_source_type_id').selectmenu('instance')) {
				$('#data_source_type_id').selectmenu('disable');
			}
		}

		// Disable Data Query Input Field Changes
		$('.disable').find('input').each(function() {
			$(this).prop('disabled', true).addClass('ui-stat-disabled');
		});

		$('.customDataCheckbox').find('input').on('change', function() {
			if ($(this).prop('checked') == false) {
				mixedReasonTitle = '<?php print __('Custom Data Warning Message');?>';
				mixedOnPage      = '<?php print __esc('WARNING: Data Loss can Occur');?>';
				sessionMessage   = {
					message: '<?php print __esc('After you uncheck this checkbox and then Save the Data Template, any existing Data Sources based on this Data Template will loose their Custom Data.  This can result in broken Data Collection and Graphs');?>',
					level: MESSAGE_LEVEL_MIXED
				};

				displayMessages();
			}
		});
	});

	</script>
	<?php
}

function template() {
	global $ds_actions, $item_rows;

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
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'profile' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'has_data' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => read_config_option('default_has') == 'on' ? 'true':'false'
			)
	);

	validate_store_request_vars($filters, 'sess_dt');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Data Templates'), '100%', '', '3', 'center', 'data_templates.php?action=template_edit');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_data_template' action='data_templates.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Profile');?>
					</td>
					<td>
						<select id='profile' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('profile') == '-1' ? ' selected>':'>') . __('All');?></option>
							<?php
							$profiles = array_rekey(db_fetch_assoc('SELECT id, name FROM data_source_profiles ORDER BY name'), 'id', 'name');
							if (cacti_sizeof($profiles)) {
								foreach ($profiles as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('profile') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Data Templates');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='has_data' <?php print (get_request_var('has_data') == 'true' ? 'checked':'');?>>
							<label for='has_data'><?php print __('Has Data Sources');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		</td>
		<script type='text/javascript'>
		function applyFilter() {
			strURL  = 'data_templates.php?header=false';
			strURL += '&filter='+$('#filter').val();
			strURL += '&rows='+$('#rows').val();
			strURL += '&profile='+$('#profile').val();
			strURL += '&has_data='+$('#has_data').is(':checked');
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'data_templates.php?clear=1&header=false';
			loadPageNoHeader(strURL);
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
	if (get_request_var('filter') != '') {
		$sql_where = ' WHERE (dt.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (get_request_var('profile') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' dsp.id=' . get_request_var('profile');
	}

	if (get_request_var('has_data') == 'true') {
		$sql_having = 'HAVING data_sources>0';
	} else {
		$sql_having = '';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(`rows`)
		FROM (SELECT
			COUNT(dt.id) `rows`,
			SUM(CASE WHEN dtd.local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
			FROM data_template AS dt
			INNER JOIN data_template_data AS dtd
			ON dt.id=dtd.data_template_id
			LEFT JOIN data_source_profiles AS dsp
			ON dtd.data_source_profile_id=dsp.id
			LEFT JOIN data_input AS di
			ON dtd.data_input_id=di.id
			$sql_where
			GROUP BY dt.id
			$sql_having
		) AS rs");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$template_list_sql = "SELECT dt.id, dt.name,
		di.name AS data_input_method, dtd.active AS active, dsp.name AS profile_name,
		SUM(CASE WHEN dtd.local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
		FROM data_template AS dt
		INNER JOIN data_template_data AS dtd
		ON dt.id=dtd.data_template_id
		LEFT JOIN data_source_profiles AS dsp
		ON dtd.data_source_profile_id=dsp.id
		LEFT JOIN data_input AS di
		ON dtd.data_input_id=di.id
		$sql_where
		GROUP BY dt.id
		$sql_having
		$sql_order
		$sql_limit";
	$template_list = db_fetch_assoc($template_list_sql);

	$display_text = array(
		'name' => array(
			'display' => __('Data Template Name'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The name of this Data Template.')
		),
		'id' => array(
			'display' => __('ID'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The internal database ID for this Data Template.  Useful when performing automation or debugging.')
		),
		'nosort' => array(
			'display' => __('Deletable'),
			'align' => 'right',
			'tip' => __('Data Templates that are in use cannot be Deleted.  In use is defined as being referenced by a Data Source.')
		),
		'data_sources' => array(
			'display' => __('Data Sources Using'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The number of Data Sources using this Data Template.')
		),
		'data_input_method' => array(
			'display' => __('Input Method'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The method that is used to place Data into the Data Source RRDfile.')
		),
		'profile_name' => array(
			'display' => __('Profile Name'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The default Data Source Profile for this Data Template.')
		),
		'active' => array(
			'display' => __('Status'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('Data Sources based on Inactive Data Templates will not be updated when the poller runs.')
		)
	);

	$nav = html_nav_bar('data_templates.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Data Templates'), 'page', 'main');

	form_start('data_templates.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($template_list)) {
		foreach ($template_list as $template) {
			if ($template['data_sources'] > 0) {
				$disabled = true;
			} else {
				$disabled = false;
			}

			$ds_url = 'data_sources.php?reset=true&template_id=' . $template['id'];
			if (get_request_var('profile') != '-1') {
				$ds_url .= '&profile=' . get_request_var('profile');
			}
			form_alternate_row('line' . $template['id'], true, $disabled);
			form_selectable_cell(filter_value($template['name'], get_request_var('filter'), 'data_templates.php?action=template_edit&id=' . $template['id']), $template['id']);
			form_selectable_cell($template['id'], $template['id'], '', 'right');
			form_selectable_cell($disabled ? __('No'):__('Yes'), $template['id'], '', 'right');
			form_selectable_cell('<a class="linkEditMain" href="' . html_escape($ds_url) . '">' . number_format_i18n($template['data_sources']) . '</a>', $template['id'], '', 'right');
			form_selectable_cell((empty($template['data_input_method']) ? '<em>' . __('None') .'</em>': html_escape($template['data_input_method'])), $template['id']);
			form_selectable_cell((empty($template['profile_name']) ? __('External'):html_escape($template['profile_name'])), $template['id']);
			form_selectable_cell((($template['active'] == 'on') ? __('Active'):__('Disabled')), $template['id']);
			form_checkbox_cell($template['name'], $template['id'], $disabled);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . __('No Data Templates Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($template_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($ds_actions);

	form_end();
}

