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
require_once(CACTI_PATH_LIBRARY . '/api_aggregate.php');
require_once(CACTI_PATH_LIBRARY . '/api_automation.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/api_graph.php');
require_once(CACTI_PATH_LIBRARY . '/api_tree.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/graphs.php');
require_once(CACTI_PATH_LIBRARY . '/html_graph.php');
require_once(CACTI_PATH_LIBRARY . '/html_form_template.php');
require_once(CACTI_PATH_LIBRARY . '/html_tree.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/reports.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');
require_once(CACTI_PATH_LIBRARY . '/template.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

// set default action
set_default_action();

$actions = [
	1  => __('Delete')
];

if ((gnrv('template_id') != '' &&
	gnrv('template_id')     != '-1' &&
	gnrv('template_id')     != '0') || gnrv('drp_action') == 2) {
	$actions += [
		2  => __('Change Graph Template')
	];
}

if (read_config_option('grds_creation_method') == 1) {
	$actions += [
		3 => __('Duplicate'),
		4 => __('Convert to Graph Template')
	];
}

$actions += [
	5  => __('Change Device'),
	6  => __('Reapply Suggested Names'),
	8  => __('Apply Automation Rules'),
	9  => __('Create Aggregate Graph'),
	10 => __('Create Aggregate from Template'),
];

$reports = db_fetch_cell_prepared('SELECT COUNT(*)
	FROM reports
	WHERE user_id = ?
	ORDER BY name',
	[$_SESSION[SESS_USER_ID]]
);

if ($reports > 0) {
	$actions += [
		11 => __('Place Graphs on Report')
	];
}

$actions = api_plugin_hook_function('graphs_action_array', $actions);

switch (grv('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'view':
		html_graph_single_view();

		break;
	case 'update_timespan':
		html_graph_update_timespan();

		break;
	case 'item':
		top_header();
		item();
		bottom_footer();

		break;
	case 'ajax_graph_items':
		get_ajax_graph_items();

		break;
	case 'ajax_hosts':
		$sql_where = '';

		if (grv('site_id') > 0) {
			$sql_where = 'site_id = ' . gfrv('site_id');
		}

		get_allowed_ajax_hosts(true, true, $sql_where);

		break;
	case 'ajax_hosts_noany':
		$sql_where = '';

		if (grv('site_id') > 0) {
			$sql_where = 'site_id = ' . gfrv('site_id');
		}

		get_allowed_ajax_hosts(false, true, $sql_where);

		break;
	case 'ajax_graph_items':
		$sql_where = '';

		if (!ierv('host_id')) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'dl.host_id=' . gfrv('host_id');
		}

		if (!ierv('data_template_id')) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'dtd.data_template_id=' . gfrv('data_template_id');
		}

		get_allowed_ajax_graph_items(true, $sql_where);

		break;
	case 'ajax_dnd':
		$local_graph_id = gfrv('id');
		$sequences      = gnrv('item_ids');

		if (cacti_sizeof($sequences)) {
			foreach ($sequences as $index => $s) {
				$new_seq = $index++;

				db_execute_prepared('UPDATE graph_templates_item
					SET sequence = ?
					WHERE id = ?
					AND local_graph_id = ?
					AND graph_template_id = 0',
					[$new_seq, $s, $local_graph_id]);
			}
		}

		header('Location: graphs.php?action=graph_edit&id=' . gfrv('id'));

		break;
	case 'item_remove':
		gfrv('local_graph_id');

		item_remove();

		header('Location: graphs.php?action=graph_edit&id=' . grv('local_graph_id'));

		break;
	case 'item_edit':
		top_header();

		item_edit();

		bottom_footer();

		break;
	case 'item_movedown':
		gfrv('local_graph_id');

		item_movedown();

		header('Location: graphs.php?action=graph_edit&id=' . grv('local_graph_id'));

		break;
	case 'item_moveup':
		gfrv('local_graph_id');

		item_moveup();

		header('Location: graphs.php?action=graph_edit&id=' . grv('local_graph_id'));

		break;
	case 'lock':
	case 'unlock':
		$_SESSION['sess_graph_lock_id'] = gfrv('id');
		$_SESSION['sess_graph_locked']  = (grv('action') == 'lock' ? true : false);
	case 'graph_edit':
		top_header();
		graph_edit();
		bottom_footer();

		break;
	default:
		top_header();
		graphs();
		bottom_footer();

		break;
}

function get_ajax_graph_items() : void {
	$rrd_id  = gfrv('rrd_id');
	$host_id = gfrv('host_id');

	$sql_where    = '';
	$sql_params   = [];
	$sql_params[] = $rrd_id;

	if ($host_id > 0) {
		$sql_where    = ' AND data_local.host_id = ?';
		$sql_params[] = $host_id;
	}

	if (gnrv('term') != '') {
		$sql_where .= ' HAVING name LIKE ?';
		$sql_params[] = '%' . gnrv('term') . '%';
	}

	$items  = db_fetch_assoc_prepared("SELECT *
		FROM (SELECT data_template_rrd.id AS id,
			CONCAT_WS('',
			CASE
			WHEN host.description IS NULL THEN '" . __esc('No Device - ') . "'
			WHEN host.description IS NOT NULL THEN ''
			END,
			data_template_data.name_cache,' (',data_template_rrd.data_source_name,')') AS name
			FROM (data_template_data,data_template_rrd,data_local)
			LEFT JOIN host ON (data_local.host_id=host.id)
			WHERE data_template_rrd.local_data_id=data_local.id
			AND data_template_data.local_data_id=data_local.id
			AND data_template_rrd.id = ?
		) AS a
		UNION
		SELECT *
		FROM (SELECT data_template_rrd.id AS id,
			CONCAT_WS('',
			CASE
			WHEN host.description IS NULL THEN '" . __esc('No Device - ') . "'
			WHEN host.description IS NOT NULL THEN ''
			END,
			data_template_data.name_cache,' (',data_template_rrd.data_source_name,')') AS name
			FROM (data_template_data,data_template_rrd,data_local)
			LEFT JOIN host ON (data_local.host_id=host.id)
			WHERE data_template_rrd.local_data_id=data_local.id
			AND data_template_data.local_data_id=data_local.id
			$sql_where
			ORDER BY name
		) AS b
		LIMIT " . read_config_option('autocomplete_rows'),
		$sql_params);

	foreach ($items as $key => $item) {
		$items[$key]['label'] = $item['name'];
	}

	print json_encode($items);
}

function add_tree_names_to_actions_array() : void {
	global $actions;

	// add a list of tree names to the actions dropdown
	$trees = db_fetch_assoc('SELECT id, name
		FROM graph_tree
		ORDER BY name');

	if (cacti_sizeof($trees)) {
		foreach ($trees as $tree) {
			$actions['tr_' . $tree['id']] = __esc('Place on a Tree (%s)', $tree['name']);
		}
	}
}

function parse_validate_graph_template_id(string $variable) : string {
	$output_type_id = 0;

	if (str_contains(gnrv($variable), '_')) {
		$template_parts = explode('_', gnrv($variable));

		if (is_numeric($template_parts[0]) && is_numeric($template_parts[1])) {
			srv('graph_template_id', $template_parts[0]);
			$output_type_id = $template_parts[1];
		} else {
			cacti_log('ERROR: Unable to parse graph_template_id with value ' . gnrv($variable), false, 'WEBUI');

			exit;
		}
	} else {
		gfrv($variable);
	}

	return $output_type_id;
}

function form_save() : void {
	if (isrv('save_component_graph_new') || isrv('save_component_graph') || isrv('save_component_input')) {
		// ================= input validation =================
		gfrv('local_graph_id');
		gfrv('host_id_prev');
		gfrv('graph_template_graph_id');
		gfrv('local_graph_template_graph_id');
		// ====================================================

		// handle special case of callback on host_id
		if (!is_numeric(gnrv('host_id'))) {
			srv('host_id', grv('host_id_prev'));
		} else {
			gfrv('host_id');
		}

		$gt_id_unparsed      = gnrv('graph_template_id');
		$gt_id_prev_unparsed = gnrv('graph_template_id_prev');
		parse_validate_graph_template_id('graph_template_id');

		if (isrv('save_component_graph_new') && !ierv('graph_template_id')) {
			$snmp_query_array  = [];
			$suggested_values  = [];
			$graph_template_id = grv('graph_template_id');
			$host_id           = grv('host_id');

			if ($host_id > 0) {
				object_cache_get_totals('device_state', $host_id);
			}

			$return_array = create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, $suggested_values);

			if ($return_array !== false) {
				debug_log_insert('new_graphs', __esc('Created graph: %s', get_graph_title($return_array['local_graph_id'])));

				// lastly push host-specific information to our data sources
				if (cacti_sizeof($return_array['local_data_id'])) { // we expect at least one data source associated
					foreach ($return_array['local_data_id'] as $item) {
						push_out_host($host_id, $item);
					}
				} else {
					debug_log_insert('new_graphs', __esc('ERROR: No Data Source associated. Check Template'));
				}
			}

			if ($host_id > 0) {
				object_cache_get_totals('device_state', $host_id, true);
				object_cache_update_totals('diff');
			}

			if (isset($return_array['local_graph_id'])) {
				$local_graph_id = $return_array['local_graph_id'];
				header('Location: graphs.php?action=graph_edit&id=' . $local_graph_id);
			} else {
				header('Location: graphs.php');
			}

			exit;
		}

		if (isrv('save_component_graph')) {
			if (gfrv('host_id') == '-1') {
				srv('host_id', '0');
			}

			if (grv('host_id') > 0) {
				object_cache_get_totals('device_state', grv('host_id'));
			}

			$save1['id']                   = gnrv('local_graph_id');
			$save1['host_id']              = grv('host_id');
			$save1['graph_template_id']    = gnrv('graph_template_id');

			$save2['id']                            = gnrv('graph_template_graph_id');
			$save2['local_graph_template_graph_id'] = gnrv('local_graph_template_graph_id');
			$save2['graph_template_id']             = gnrv('graph_template_id');
			$save2['image_format_id']               = form_input_validate(gnrv('image_format_id'), 'image_format_id', '^[0-9]+$', true, 3);
			$save2['title']                         = form_input_validate(gnrv('title'), 'title', '', false, 3);
			$save2['height']                        = form_input_validate(gnrv('height'), 'height', '^[0-9]+$', false, 3);
			$save2['width']                         = form_input_validate(gnrv('width'), 'width', '^[0-9]+$', false, 3);
			$save2['upper_limit']                   = form_input_validate(gnrv('upper_limit'), 'upper_limit', '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?|U)\z', ((strlen(gnrv('upper_limit')) === 0) ? true : false), 3);
			$save2['lower_limit']                   = form_input_validate(gnrv('lower_limit'), 'lower_limit', '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?|U)\z', ((strlen(gnrv('lower_limit')) === 0) ? true : false), 3);
			$save2['vertical_label']                = form_input_validate(gnrv('vertical_label'), 'vertical_label', '', true, 3);
			$save2['slope_mode']                    = form_input_validate((isrv('slope_mode') ? gnrv('slope_mode') : ''), 'slope_mode', '', true, 3);
			$save2['auto_scale']                    = form_input_validate((isrv('auto_scale') ? gnrv('auto_scale') : ''), 'auto_scale', '', true, 3);
			$save2['auto_scale_opts']               = form_input_validate(gnrv('auto_scale_opts'), 'auto_scale_opts', '', true, 3);
			$save2['auto_scale_log']                = form_input_validate((isrv('auto_scale_log') ? gnrv('auto_scale_log') : ''), 'auto_scale_log', '', true, 3);
			$save2['scale_log_units']               = form_input_validate((isrv('scale_log_units') ? gnrv('scale_log_units') : ''), 'scale_log_units', '', true, 3);
			$save2['auto_scale_rigid']              = form_input_validate((isrv('auto_scale_rigid') ? gnrv('auto_scale_rigid') : ''), 'auto_scale_rigid', '', true, 3);
			$save2['auto_padding']                  = form_input_validate((isrv('auto_padding') ? gnrv('auto_padding') : ''), 'auto_padding', '', true, 3);
			$save2['base_value']                    = form_input_validate(gnrv('base_value'), 'base_value', '^[0-9]+$', false, 3);
			$save2['unit_value']                    = form_input_validate(gnrv('unit_value'), 'unit_value', '', true, 3);
			$save2['unit_exponent_value']           = form_input_validate(gnrv('unit_exponent_value'), 'unit_exponent_value', '^-?[0-9]+$', true, 3);
			$save2['alt_y_grid']                    = form_input_validate((isrv('alt_y_grid') ? gnrv('alt_y_grid') : ''), 'alt_y_grid', '', true, 3);
			$save2['right_axis']                    = form_input_validate((isrv('right_axis') ? gnrv('right_axis') : ''), 'right_axis', '^-?([0-9]+(\.[0-9]*)?|\.[0-9]+):-?([0-9]+(\.[0-9]*)?|\.[0-9]+)$', true, 3);
			$save2['right_axis_label']              = form_input_validate((isrv('right_axis_label') ? gnrv('right_axis_label') : ''), 'right_axis_label', '', true, 3);
			$save2['right_axis_format']             = form_input_validate((isrv('right_axis_format') ? gnrv('right_axis_format') : ''), 'right_axis_format', '^[0-9]+$', true, 3);
			$save2['no_gridfit']                    = form_input_validate((isrv('no_gridfit') ? gnrv('no_gridfit') : ''), 'no_gridfit', '', true, 3);
			$save2['unit_length']                   = form_input_validate((isrv('unit_length') ? gnrv('unit_length') : ''), 'unit_length', '^[0-9]+$', true, 3);
			$save2['tab_width']                     = form_input_validate((isrv('tab_width') ? gnrv('tab_width') : ''), 'tab_width', '^[0-9]*$', true, 3);
			$save2['dynamic_labels']                = form_input_validate((isrv('dynamic_labels') ? gnrv('dynamic_labels') : ''), 'dynamic_labels', '', true, 3);
			$save2['force_rules_legend']            = form_input_validate((isrv('force_rules_legend') ? gnrv('force_rules_legend') : ''), 'force_rules_legend', '', true, 3);
			$save2['legend_position']               = form_input_validate((isrv('legend_position') ? gnrv('legend_position') : ''), 'legend_position', '', true, 3);
			$save2['legend_direction']              = form_input_validate((isrv('legend_direction') ? gnrv('legend_direction') : ''), 'legend_direction', '', true, 3);
			$save2['right_axis_formatter']          = form_input_validate((isrv('right_axis_formatter') ? gnrv('right_axis_formatter') : ''), 'right_axis_formatter', '', true, 3);
			$save2['left_axis_format']              = form_input_validate((isrv('left_axis_format') ? gnrv('left_axis_format') : ''), 'left_axis_format', '^[0-9]+$', true, 3);
			$save2['left_axis_formatter']           = form_input_validate((isrv('left_axis_formatter') ? gnrv('left_axis_formatter') : ''), 'left_axis_formatter', '', true, 3);

			if (!is_error_message()) {
				$local_graph_id = sql_save($save1, 'graph_local');

				/**
				 * Save the last time a graph was created/updated
				 * for Caching.
				 */
				set_config_option('time_last_change_graph', time());
			}

			if (!is_error_message()) {
				$save2['local_graph_id']  = $local_graph_id;
				$graph_templates_graph_id = sql_save($save2, 'graph_templates_graph');

				if ($graph_templates_graph_id) {
					raise_message(1);
				} else {
					raise_message(2);
				}

				// update the title cache
				update_graph_title_cache($local_graph_id);

				// if the host id changes, then update the graph items association too
				if (grv('host_id') != grv('host_id_prev')) {
					if (!api_graph_change_device($local_graph_id, grv('host_id'))) {
						raise_message(34);
					}
				}
			}

			if (!is_error_message()) {
				$lg_template_id = db_fetch_cell_prepared('SELECT graph_template_id
					FROM graph_local
					WHERE id = ?',
					[$local_graph_id]
				);

				if ($lg_template_id > 0) {
					change_graph_template($local_graph_id, $gt_id_unparsed, true);

					$lg_dq_id = db_fetch_cell_prepared('SELECT snmp_query_id
						FROM graph_local
						WHERE id = ?',
						[$local_graph_id]
					);

					if ($lg_dq_id > 0) {
						update_graph_data_query_cache($local_graph_id);
					}
				}
			}

			if (grv('host_id') > 0) {
				object_cache_get_totals('device_state', grv('host_id'), true);
				object_cache_update_totals('diff');
			}
		}

		if (isrv('save_component_input')) {
			// ================= input validation =================
			gfrv('local_graph_id');
			// ====================================================

			// first; get the current graph template id
			$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
				FROM graph_local
				WHERE id = ?',
				[gnrv('local_graph_id')]);

			// get all inputs that go along with this graph template, if templated
			if ($graph_template_id > 0) {
				$input_list = db_fetch_assoc_prepared('SELECT id, column_name
					FROM graph_template_input
					WHERE graph_template_id = ?',
					[$graph_template_id]);

				if (cacti_sizeof($input_list)) {
					foreach ($input_list as $input) {
						// we need to find out which graph items will be affected by saving this particular item
						$item_list = db_fetch_assoc_prepared('SELECT gti.id
							FROM graph_template_input_defs AS gtid
							INNER JOIN graph_templates_item AS gti
							ON gtid.graph_template_item_id=gti.local_graph_template_item_id
							WHERE gti.local_graph_id = ?
							AND gtid.graph_template_input_id = ?',
							[gnrv('local_graph_id'), $input['id']]);

						// loop through each item affected and update column data
						if (cacti_sizeof($item_list)) {
							foreach ($item_list as $item) {
								/* if we are changing templates, the POST vars we are searching for here will not exist.
								 this is because the db and form are out of sync here, but it is ok to just skip over saving
								 the inputs in this case. */
								if (isrv($input['column_name'] . '_' . $input['id'])) {
									db_execute_prepared('UPDATE graph_templates_item
										SET ' . $input['column_name'] . ' = ?
										WHERE id = ?',
										[gnrv($input['column_name'] . '_' . $input['id']), $item['id']]);
								}
							}
						}
					}
				}
			}
		}
	} elseif (isrv('save_component_item')) {
		global $graph_item_types;

		// ================= input validation =================
		gfrv('sequence');
		gfrv('graph_type_id');
		gfrv('local_graph_id');
		gfrv('graph_template_item_id');
		gfrv('graph_template_id');
		gfrv('local_graph_template_item_id');
		// ====================================================

		/* sql_save() inside the items foreach below assigns this; if the
		 * loop never enters the !is_error_message() branch we still need a
		 * defined value for the error-redirect URL fallback. */
		$graph_template_item_id = 0;

		$items[0] = [];

		if ($graph_item_types[gnrv('graph_type_id')] == 'LEGEND') {
			/**
			 * this can be a major time saver when creating lots of
			 * graphs with the typical
			 * GPRINT LAST/AVERAGE/MAX legends
			 */
			$items = [
				0 => [
					'color_id'                  => '0',
					'color2_id'                 => '0',
					'alpha'                     => '0',
					'alpha2'                    => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '4',
					'text_format'               => 'Cur:',
					'hard_return'               => ''
				],
				1 => [
					'color_id'                  => '0',
					'color2_id'                 => '0',
					'alpha'                     => '0',
					'alpha2'                    => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '1',
					'text_format'               => 'Avg:',
					'hard_return'               => ''
				],
				2 => [
					'color_id'                  => '0',
					'color2_id'                 => '0',
					'alpha'                     => '0',
					'alpha2'                    => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '3',
					'text_format'               => 'Max:',
					'hard_return'               => 'on'
				]
			];
		} elseif ($graph_item_types[gnrv('graph_type_id')] == 'LEGEND_CAMM') {
			/**
			 * this can be a major time saver when creating lots of
			 * graphs with the typical
			 * GPRINT LAST/AVERAGE/MAX legends
			 */
			$items = [
				0 => [
					'color_id'                  => '0',
					'color2_id'                 => '0',
					'alpha'                     => '0',
					'alpha2'                    => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '4',
					'text_format'               => __('Cur:'),
					'hard_return'               => ''
				],
				1 => [
					'color_id'                  => '0',
					'color2_id'                 => '0',
					'alpha'                     => '0',
					'alpha2'                    => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '1',
					'text_format'               => __('Avg:'),
					'hard_return'               => ''
				],
				2 => [
					'color_id'                  => '0',
					'color2_id'                 => '0',
					'alpha'                     => '0',
					'alpha2'                    => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '2',
					'text_format'               => __('Min:'),
					'hard_return'               => ''
				],
				3 => [
					'color_id'                  => '0',
					'color2_id'                 => '0',
					'alpha'                     => '0',
					'alpha2'                    => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '3',
					'text_format'               => __('Max:'),
					'hard_return'               => 'on'
				]
			];
		}

		$sequence = gnrv('sequence');

		$graph_template_item_id = '';

		if (empty($sequence) || !is_numeric($sequence)) {
			$sequence = 1;
		}

		foreach ($items as $item) {
			// generate a new sequence if needed
			if (empty($sequence)) {
				$sequence = get_sequence($sequence, 'sequence', 'graph_templates_item', 'local_graph_id=' . gnrv('local_graph_id'));
			}

			$save['id']                           = gnrv('graph_template_item_id');
			$save['graph_template_id']            = gnrv('graph_template_id');
			$save['local_graph_template_item_id'] = gnrv('local_graph_template_item_id');
			$save['local_graph_id']               = gnrv('local_graph_id');
			$save['task_item_id']                 = form_input_validate(gnrv('task_item_id'), 'task_item_id', '^[0-9]+$', true, 3);
			$save['color_id']                     = form_input_validate((isset($item['color_id']) ? $item['color_id'] : gnrv('color_id')), 'color_id', '^[0-9]+$', true, 3);
			$save['color2_id']                    = form_input_validate((isset($item['color2_id']) ? $item['color2_id'] : gnrv('color2_id')), 'color2_id', '^[0-9]+$', true, 3);

			// if alpha is disabled, use invisible_alpha instead
			if (!isrv('alpha')) {
				srv('alpha', gnrv('invisible_alpha'));
			}

			if (!isrv('alpha2')) {
				srv('alpha2', gnrv('invisible_alpha'));
			}

			$save['alpha']          = form_input_validate((isset($item['alpha']) ? $item['alpha'] : gnrv('alpha')), 'alpha', '', true, 3);
			$save['alpha2']         = form_input_validate((isset($item['alpha2']) ? $item['alpha2'] : gnrv('alpha2')), 'alpha2', '', true, 3);
			$save['gradheight']     = form_input_validate(gnrv('gradheight'), 'gradheight', '', true, 3);

			$save['graph_type_id']  = form_input_validate((isset($item['graph_type_id']) ? $item['graph_type_id'] : gnrv('graph_type_id')), 'graph_type_id', '^[0-9]+$', true, 3);

			if (isrv('line_width')) {
				$save['line_width'] = form_input_validate(gnrv('line_width'), 'line_width', '(^[0-9]+[\.,0-9]+$|^[0-9]+$)', true, 3);
			} else { // make sure to transfer old LINEx style into line_width on save
				switch ($save['graph_type_id']) {
					case GRAPH_ITEM_TYPE_LINE1:
						$save['line_width'] = 1;

						break;
					case GRAPH_ITEM_TYPE_LINE2:
						$save['line_width'] = 2;

						break;
					case GRAPH_ITEM_TYPE_LINE3:
						$save['line_width'] = 3;

						break;
					default:
						$save['line_width'] = 0;
				}
			}

			$save['dashes']                    = form_input_validate((isrv('dashes') ? gnrv('dashes') : ''), 'dashes', '', true, 3);
			$save['dash_offset']               = form_input_validate((isrv('dash_offset') ? gnrv('dash_offset') : ''), 'dash_offset', '^[0-9]+$', true, 3);
			$save['cdef_id']                   = form_input_validate(gnrv('cdef_id'), 'cdef_id', '^[0-9]+$', true, 3);
			$save['vdef_id']                   = form_input_validate(gnrv('vdef_id'), 'vdef_id', '^[0-9]+$', true, 3);
			$save['shift']                     = form_input_validate((isrv('shift') ? gnrv('shift') : ''), 'shift', '^((on)|)$', true, 3);
			$save['consolidation_function_id'] = form_input_validate((isset($item['consolidation_function_id']) ? $item['consolidation_function_id'] : gnrv('consolidation_function_id')), 'consolidation_function_id', '^[0-9]+$', true, 3);
			$save['textalign']                 = form_input_validate((isrv('textalign') ? gnrv('textalign') : ''), 'textalign', '^[a-z]+$', true, 3);
			$save['text_format']               = form_input_validate((isset($item['text_format']) ? $item['text_format'] : gnrv('text_format')), 'text_format', '', true, 3);
			$save['legend']                    = form_input_validate(gnrv('legend'), 'legend', '', true, 3);

			$save['value']                     = form_input_validate(gnrv('value'), 'value', '', true, 3);
			$save['hard_return']               = form_input_validate(((isset($item['hard_return']) ? $item['hard_return'] : (isrv('hard_return') ? gnrv('hard_return') : ''))), 'hard_return', '', true, 3);
			$save['gprint_id']                 = form_input_validate(gnrv('gprint_id'), 'gprint_id', '^[0-9]+$', true, 3);
			$save['sequence']                  = $sequence;

			if (!is_error_message()) {
				$graph_template_item_id = sql_save($save, 'graph_templates_item');

				if ($graph_template_item_id) {
					raise_message(1);
				} else {
					raise_message(2);
				}
			}

			$sequence = 0;
		}

		if (is_error_message()) {
			header('Location: graphs.php?action=item_edit&graph_template_item_id=' . (empty($graph_template_item_id) ? gnrv('graph_template_item_id') : $graph_template_item_id) . '&id=' . gnrv('local_graph_id'));

			exit;
		} else {
			header('Location: graphs.php?action=graph_edit&id=' . gnrv('local_graph_id'));

			exit;
		}
	}

	if ((isrv('save_component_graph_new')) && (ierv('graph_template_id'))) {
		header('Location: graphs.php?action=graph_edit&host_id=' . gnrv('host_id') . '&new=1');
	} elseif ((is_error_message()) || (ierv('local_graph_id')) || (gnrv('graph_template_id') != gnrv('graph_template_id_prev')) || (gnrv('host_id') != gnrv('host_id_prev'))) {
		header('Location: graphs.php?action=graph_edit&id=' . (empty($local_graph_id) ? gnrv('local_graph_id') : $local_graph_id) . (isrv('host_id') ? '&host_id=' . gnrv('host_id') : ''));
	} elseif (!empty($local_graph_id)) {
		header('Location: graphs.php?action=graph_edit&id=' . $local_graph_id);
	} else {
		header('Location: graphs.php');
	}

	exit;
}

function item_movedown() : void {
	global $graph_item_types;

	// ================= input validation =================
	gfrv('id');
	gfrv('local_graph_id');
	// ====================================================

	$arr     = get_graph_group(grv('id'));
	$next_id = get_graph_parent(grv('id'), 'next');

	if ((!empty($next_id)) && (isset($arr[grv('id')]))) {
		move_graph_group(grv('id'), $arr, $next_id, 'next');
	} elseif (preg_match('/(GPRINT|VRULE|HRULE|COMMENT)/', $graph_item_types[db_fetch_cell_prepared('SELECT graph_type_id FROM graph_templates_item WHERE id = ?', [grv('id')])])) {
		move_item_down('graph_templates_item', grv('id'), 'local_graph_id=' . grv('local_graph_id'));
	}
}

function item_moveup() : void {
	global $graph_item_types;

	// ================= input validation =================
	gfrv('id');
	gfrv('local_graph_id');
	// ====================================================

	$arr         = get_graph_group(grv('id'));
	$previous_id = get_graph_parent(grv('id'), 'previous');

	if ((!empty($previous_id)) && (isset($arr[grv('id')]))) {
		move_graph_group(grv('id'), $arr, $previous_id, 'previous');
	} elseif (preg_match('/(GPRINT|VRULE|HRULE|COMMENT)/', $graph_item_types[db_fetch_cell_prepared('SELECT graph_type_id FROM graph_templates_item WHERE id = ?', [grv('id')])])) {
		move_item_up('graph_templates_item', grv('id'), 'local_graph_id=' . grv('local_graph_id'));
	}
}

function item_remove() : void {
	// ================= input validation =================
	gfrv('id');
	// ====================================================

	db_execute_prepared('DELETE FROM graph_templates_item WHERE id = ?', [grv('id')]);
}

function validate_item_vars() : void {
	// ================= input validation and session storage =================
	$filters = [
		'host_id' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '0'
		],
		'local_graph_id' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '0'
		],
		'data_template_id' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '0'
		]
	];

	validate_store_request_vars($filters, 'sess_gitems');
	// ================= input validation =================
}

function create_item_filter(string $session_var) : array {
	global $item_rows;

	$all     = ['-1' => __('All')];
	$any     = ['-1' => __('Any')];
	$none    = ['0'  => __('None')];

	if (isrv('host_id')) {
		$host_id = grv('host_id');
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

	if (grv('host_id') <= 0) {
		$data_templates = array_rekey(
			db_fetch_assoc('SELECT id, name
				FROM data_template
				ORDER BY name'),
			'id', 'name'
		);
	} else {
		$data_templates = array_rekey(
			db_fetch_assoc_prepared('SELECT DISTINCT dt.id, dt.name
				FROM data_template AS dt
				INNER JOIN data_local AS dl
				ON dl.data_template_id=dt.id
				WHERE dl.host_id = ?
				ORDER BY name',
				[grv('host_id')]),
			'id', 'name'
		);
	}

	$data_templates = $any + $none + $data_templates;

	return [
		'rows' => [
			[
				'host_id' => [
					'method'         => 'drop_callback',
					'friendly_name'  => __('Device'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'sql'            => 'SELECT DISTINCT id, description AS name FROM host ORDER BY description',
					'action'         => 'ajax_hosts',
					'id'             => $host_id,
					'value'          => $hostname,
					'on_change'      => 'applyFilter()'
				],
			],
			[
				'data_template_id' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Template'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'array'          => $data_templates,
					'value'          => '-1'
				],
				'id' => [
					'method'         => 'validate',
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '',
					'value'          => gfrv('id')
				],
				'local_graph_id' => [
					'method'         => 'validate',
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '',
					'value'          => gfrv('host_id')
				]
			]
		]
	];
}

function draw_item_filter(bool $render = false, array $host = []) : void {
	$filters = create_item_filter('sess_graphs');

	if (empty($host['hostname'])) {
		$header = __('Data Sources [No Device]');
	} else {
		$header = __esc('Data Sources [%s]', $host['hostname']);
	}

	// create the page filter
	$pageFilter = new CactiTableFilter($header, 'graphs.php?action=item_edit&local_graph_id=' . grv('local_graph_id'), 'form_graphs', 'sess_graphs', '', '', false);

	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function item_edit() : void {
	global $struct_graph_item, $graph_item_types, $consolidation_functions;

	$id = (!ierv('id') ? '&id=' . grv('id') : '');

	$host = db_fetch_row_prepared('SELECT hostname
		FROM host
		WHERE id = ?',
		[grv('host_id')]
	);

	draw_item_filter(true, $host);

	// This column is for Graph Templates
	unset($struct_graph_item['data_template_id']);

	validate_item_vars();

	if (empty($host['hostname'])) {
		$header = __('Data Sources [No Device]');
	} else {
		$header = __esc('Data Sources [%s]', $host['hostname']);
	}

	load_current_session_value('host_id', 'sess_graph_items_hi', '-1');
	load_current_session_value('data_template_id', 'sess_graph_items_dti', '-1');

	$sql_where = '';

	if (grv('host_id') > 0) {
		$sql_where = 'h.id = ' . grv('host_id');
	} elseif (grv('host_id') == 0) {
		$sql_where = 'h.id IS NULL';
	}

	if (grv('data_template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'dl.data_template_id=0';
	} elseif (grv('data_template_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'dl.data_template_id = ' . grv('data_template_id');
	}

	if (!ierv('id')) {
		$template_item = db_fetch_row_prepared('SELECT *
			FROM graph_templates_item
			WHERE id = ?',
			[grv('id')]
		);
	} else {
		$template_item = [];

		kill_session_var('sess_graph_items_dti');
	}

	$title = db_fetch_cell_prepared('SELECT title_cache
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		[grv('local_graph_id')]
	);

	$header_label = __esc('Graph Items [graph: %s]', $title);

	form_start('graphs.php', 'graph_edit');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	// by default, select the LAST DS chosen to make everyone's lives easier
	if (!ierv('local_graph_id')) {
		$struct_graph_item['task_item_id']['default'] = 0;

		if (isset($template_item['task_item_id'])) {
			$task_item_id = $template_item['task_item_id'];

			$value = db_fetch_cell_prepared("SELECT CONCAT_WS('', dtd.name_cache,' (', dtr.data_source_name, ')') as name
				FROM data_local AS dl
				INNER JOIN data_template_data AS dtd
				ON dtd.local_data_id=dl.id
				INNER JOIN data_template_rrd AS dtr
				ON dtr.local_data_id=dl.id
				LEFT JOIN host AS h
				ON dl.host_id=h.id
				WHERE dtr.id = ?",
				[$task_item_id]
			);
		} else {
			$task_item_id = 0;

			$value = '';
		}

		if (read_config_option('autocomplete_enabled') > 0) {
			$action = 'ajax_graph_items';

			if (grv('host_id') > 0) {
				$action .= '&host_id=' . gfrv('host_id');
			}

			if (grv('data_template_id') > 0) {
				$action .= '&data_template_id=' . gfrv('data_template_id');
			}

			$struct_graph_item['task_item_id'] = [
				'method'        => 'drop_callback',
				'friendly_name' => __('Data Source'),
				'description'   => __('Choose the Data Source to associate with this Graph Item.'),
				'sql'           => '',
				'action'        => $action,
				'none_value'    => __('None'),
				'id'            => $task_item_id,
				'value'         => $value
			];
		}

		// modifications to the default graph items array
		$struct_graph_item['task_item_id']['sql'] = "SELECT
			CONCAT_WS('', dtd.name_cache,' (', dtr.data_source_name, ')') as name, dtr.id
			FROM data_local AS dl
			INNER JOIN data_template_data AS dtd
			ON dtd.local_data_id=dl.id
			INNER JOIN data_template_rrd AS dtr
			ON dtr.local_data_id=dl.id
			LEFT JOIN host AS h
			ON dl.host_id=h.id";

		// Make sure we don't limit the list so that the selected DS isn't in the list in edit mode
		if ($sql_where != '') {
			if (!ierv('id')) {
				$struct_graph_item['task_item_id']['sql'] .= " WHERE ($sql_where) OR (dtr.id=" . $template_item['task_item_id'] . ')';
			} else {
				$struct_graph_item['task_item_id']['sql'] .= " WHERE $sql_where";
			}
		}

		$struct_graph_item['task_item_id']['sql'] .= ' ORDER BY name';
	}

	$form_array = [];

	foreach ($struct_graph_item as $field_name => $field_array) {
		$form_array += [$field_name => $struct_graph_item[$field_name]];

		if (read_config_option('autocomplete_enabled')) {
			if ($field_name != 'task_item_id') {
				$form_array[$field_name]['value'] = (isset($template_item[$field_name]) ? $template_item[$field_name] : '');
			}
		} else {
			$form_array[$field_name]['value'] = (isset($template_item[$field_name]) ? $template_item[$field_name] : '');
		}

		$form_array[$field_name]['form_id'] = (isset($template_item['id']) ? $template_item['id'] : '0');
	}

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => $form_array
		]
	);

	form_hidden_box('local_graph_id', grv('local_graph_id'), '0');
	form_hidden_box('graph_template_item_id', (!empty($template_item) ? $template_item['id'] : '0'), '');
	form_hidden_box('local_graph_template_item_id', (!empty($template_item) ? $template_item['local_graph_template_item_id'] : '0'), '');
	form_hidden_box('graph_template_id', (!empty($template_item) ? $template_item['graph_template_id'] : '0'), '');
	form_hidden_box('_graph_type_id', (!empty($template_item) ? $template_item['graph_type_id'] : '0'), '');
	form_hidden_box('save_component_item', '1', '');
	form_hidden_box('invisible_alpha', $form_array['alpha']['value'], 'FF');
	form_hidden_box('rrdtool_version', get_rrdtool_version(), '');

	html_end_box(true, true);

	form_save_button('graphs.php?action=graph_edit&id=' . grv('local_graph_id'));

	?>
	<script type='text/javascript'>
		$(function() {
			$('#shift').click(function(data) {
				toggleFields({
					value: $('#shift').is(':checked'),
				});
			});

			setRowVisibility();
			cdefAlignment();

			if ($('#cdef_id').selectmenu('instance') !== undefined) {
				$('#cdef_id').selectmenu('destroy');
				$('#cdef_id').selectmenu({
					open: function() {
						cdefAlignment();
					}
				});
			} else {
				$('#cdef_id').click(function() {
					cdefAlignment();
				});
			}

			$('#graph_type_id').change(function(data) {
				setRowVisibility();
			});
		});

		/**
		 * columns - task_item_id color_id alpha graph_type_id consolidation_function_id cdef_id value gprint_id text_format hard_return
		 *
		 * graph_type_ids
		 *   1  - Comment
		 *   2  - HRule
		 *   3  - Vrule
		 *   4  - Line1
		 *   5  - Line2
		 *   6  - Line3
		 *   7  - Area
		 *   8  - Stack
		 *   9  - Gprint
		 *   10 - Legend
		 *
		 */

		function changeColorId() {
			$('#alpha').prop('disabled', true);

			if ($('#color_id').val() != 0) {
				$('#alpha').prop('disabled', false);
			}

			switch ($('#graph_type_id').val()) {
				case '4':
				case '5':
				case '6':
				case '7':
				case '8':
					$('#alpha').prop('disabled', false);
			}
		}

		function changeColor2Id() {
			$('#alpha2').prop('disabled', true);

			if ($('#color2_id').val() != 0) {
				$('#alpha2').prop('disabled', false);
			}

			switch ($('#graph_type_id').val()) {
				case '7':
				case '8':
					$('#alpha2').prop('disabled', false);
			}
		}

		function cdefAlignment() {
			if ($('#task_item_id').val() == '0') {
				$('#cdef_id option').each(function() {
					if ($(this).text().indexOf('_AGGREGATE') >= 0) {
						$(this).prop('disabled', true);
					}
				});
			} else {
				$('#cdef_id option').each(function() {
					if ($(this).text().indexOf('_AGGREGATE') >= 0) {
						$(this).prop('disabled', false);
						$(this).removeAttr('disabled');
					}
				});
			}

			if ($('#cdef_id').selectmenu('instance') !== undefined) {
				$('#cdef_id').selectmenu('refresh');
			}
		}

		function applyFilter() {
			strURL = 'graphs.php?action=item_edit<?php print $id; ?>' +
				'&local_graph_id=<?php print grv('local_graph_id'); ?>' +
				'&data_template_id=' + $('#data_template_id').val() +
				'&host_id=' + $('#host_id').val();

			loadUrl({
				url: strURL
			})
		}

		var graphType = $('#graph_type_id').val();
		function setRowVisibility() {
			toggleFields({
				data_template_id: graphType != 3 && graphType != 40,
				task_item_id: graphType != 3 && graphType != 40,
				color_id: (graphType > 1 && graphType < 9) || graphType == 20 || graphType == 30,
				color2_id: graphType == 7 || graphType == 8,
				line_width: (graphType > 3 && graphType < 7) || graphType == 20,
				dashes: (graphType > 1 && graphType < 7) || graphType == 20,
				dash_offset: (graphType > 1 && graphType < 7) || graphType == 20,
				textalign: graphType == 40,
				shift: (graphType > 3 && graphType < 9) || graphType == 20,
				alpha: (graphType > 3 && graphType < 9) || graphType == 20 || graphType == 40,
				alpha2: graphType == 7 || graphType == 8,
				gradheight: graphType == 7 || graphType == 8,
				consolidation_function_id: graphType == 1 || (graphType > 3 && graphType != 10 && graphType != 15 && graphType != 30 && graphType != 40),
				cdef_id: graphType > 3 && graphType != 40,
				vdef_id: graphType > 3 && graphType != 40,
				value: graphType == 2 || graphType == 3 || graphType == 30,
				gprint_id: graphType > 8 && graphType < 16,
				text_format: graphType >= 1 && graphType != 10 && graphType != 15 && graphType != 40,
				legend: (graphType > 1 && graphType < 9) || graphType == 20 || graphType == 30,
				hard_return: graphType >= 1 && graphType != 10 && graphType != 15 && graphType != 40,
			});

			changeColorId();
			changeColor2Id();
			cdefAlignment();
		}
	</script>
<?php
}

function get_current_graph_template(int $local_graph_id) : string {
	$graph_local = db_fetch_row_prepared('SELECT *
		FROM graph_local
		WHERE id = ?',
		[$local_graph_id]);

	$task_items = db_fetch_cell_prepared('SELECT GROUP_CONCAT(DISTINCT task_item_id) AS items
		FROM graph_templates_item
		WHERE local_graph_id = ?',
		[$local_graph_id]);

	if ($task_items != '') {
		$local_data_id = db_fetch_cell("SELECT DISTINCT local_data_id
			FROM data_template_rrd
			WHERE id IN($task_items)");
	} else {
		$local_data_id = 0;
	}

	if ($local_data_id > 0) {
		$data = db_fetch_row_prepared('SELECT id, data_input_id, data_template_id, name, local_data_id
			FROM data_template_data
			WHERE local_data_id = ?',
			[$local_data_id]);

		// get each INPUT field for this data input source
		$output_type_field_id = db_fetch_cell_prepared('SELECT id
			FROM data_input_fields
			WHERE data_input_id = ?
			AND input_output="in"
			AND type_code="output_type"
			ORDER BY sequence',
			[$data['data_input_id']]);

		$snmp_query_graph_id = db_fetch_cell_prepared('SELECT value
			FROM data_input_data
			WHERE data_template_data_id = ?
			AND data_input_field_id = ?',
			[$data['id'], $output_type_field_id]);

		if (!empty($snmp_query_graph_id)) {
			return $graph_local['graph_template_id'] . '_' . $snmp_query_graph_id;
		} else {
			return $graph_local['graph_template_id'];
		}
	} else {
		return $graph_local['graph_template_id'];
	}
}

function get_common_graph_templates(array &$graph) : string {
	$dqid = 0;

	if (cacti_sizeof($graph)) {
		$dqid = db_fetch_cell_prepared('SELECT snmp_query_id
			FROM graph_local
			WHERE id = ?',
			[$graph['local_graph_id']]);
	}

	// Default in worst case
	$gtsql = 'SELECT gt.id, gt.name FROM graph_templates AS gt ORDER BY name';

	if ($dqid > 0) {
		$sqgi = db_fetch_cell_prepared('SELECT GROUP_CONCAT(id) AS id
			FROM snmp_query_graph
			WHERE snmp_query_id = ?
			AND graph_template_id = ?',
			[$dqid, $graph['graph_template_id']]);

		if ($sqgi != '') {
			$query_fields = array_rekey(db_fetch_assoc_prepared('SELECT snmp_query_graph_id,
				GROUP_CONCAT(snmp_field_name ORDER BY snmp_field_name) AS columns
				FROM snmp_query_graph_rrd
				WHERE snmp_query_graph_id IN (' . $sqgi . ')
				GROUP BY snmp_query_graph_id'), 'snmp_query_graph_id', 'columns');

			if (cacti_sizeof($query_fields)) {
				$ids = array_to_sql_or(array_values($query_fields), 'columns');

				$common_graph_ids = array_rekey(db_fetch_assoc_prepared('SELECT
					snmp_query_graph_id, GROUP_CONCAT(snmp_field_name ORDER BY snmp_field_name) AS columns
					FROM snmp_query_graph_rrd
					GROUP BY snmp_query_graph_id
					HAVING ' . $ids), 'snmp_query_graph_id', 'columns');

				if (cacti_sizeof($common_graph_ids)) {
					$ids = implode(',', array_keys($common_graph_ids));

					$gtids = db_fetch_cell_prepared('SELECT GROUP_CONCAT(DISTINCT graph_template_id) AS gtids
						FROM snmp_query_graph
						WHERE snmp_query_id = ?
						AND id IN (' . $ids . ')',
						[$dqid]);

					if ($gtids != '') {
						$gtsql = "SELECT CONCAT_WS('', graph_template_id, '_', id, '') AS id, name
							FROM snmp_query_graph
							WHERE (snmp_query_id = $dqid AND id IN ($ids))
							OR graph_template_id IN ($gtids) ORDER BY name";
					} else {
						$gtsql = "SELECT CONCAT_WS('', graph_template_id, '_', id, '') AS id, name
							FROM snmp_query_graph
							WHERE (snmp_query_id = $dqid AND id IN ($ids))
							ORDER BY name";
					}
				}
			}
		}
	}

	return $gtsql;
}

function form_actions() : void {
	global $actions, $struct_aggregate;
	global $alignment, $graph_timespans;

	// ================= input validation =================
	gfrv('drp_action', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-zA-Z0-9_]+)$/']]);
	// ====================================================

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (grv('drp_action') == '1') { // delete
				if (!isrv('delete_type')) {
					srv('delete_type', 2);
				}

				api_delete_graphs($selected_items, gfrv('delete_type'));
			} elseif (grv('drp_action') == '2') { // change graph template
				$gt_id_unparsed      = gnrv('graph_template_id');
				$gt_id_prev_unparsed = gnrv('graph_template_id_prev');
				parse_validate_graph_template_id('graph_template_id');

				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					change_graph_template($selected_items[$i], $gt_id_unparsed, true);
				}
			} elseif (grv('drp_action') == '3') { // duplicate
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					api_duplicate_graph($selected_items[$i], 0, gnrv('title_format'));
				}
			} elseif (grv('drp_action') == '4') { // graph -> graph template
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					graph_to_graph_template($selected_items[$i], gnrv('title_format'));
				}
			} elseif (preg_match('/^tr_([0-9]+)$/', grv('drp_action'), $matches)) { // place on tree
				gfrv('tree_id');
				gfrv('tree_item_id');

				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					api_tree_item_save(0, gnrv('tree_id'), TREE_ITEM_TYPE_GRAPH, gnrv('tree_item_id'), '', $selected_items[$i], 0, 0, 0, 0, false);
				}
			} elseif (grv('drp_action') == '5') { // change host
				gfrv('host_id');
				$failures = false;

				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					if (!api_graph_change_device($selected_items[$i], grv('host_id'))) {
						$failures = true;
					}

					if ($failures) {
						raise_message(33);
					}
				}
			} elseif (grv('drp_action') == '6') { // reapply suggested naming
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					api_reapply_suggested_graph_title($selected_items[$i]);
					update_graph_title_cache($selected_items[$i]);
				}
			} elseif (grv('drp_action') == '9' || grv('drp_action') == '10') {
				// get common info - not dependent on template/no template
				$local_graph_id = 0; // this will be a new graph
				$member_graphs  = $selected_items;
				$graph_title    = form_input_validate(gnrv('title_format'), 'title_format', '', true, 3);

				// future aggregate_graphs entry
				$ag_data                 = [];
				$ag_data['id']           = 0;
				$ag_data['title_format'] = $graph_title;
				$ag_data['user_id']      = $_SESSION[SESS_USER_ID];

				if (grv('drp_action') == '9') {
					if (!isrv('aggregate_total_type')) {
						srv('aggregate_total_type', 0);
					}

					if (!isrv('aggregate_total')) {
						srv('aggregate_total', 0);
					}

					if (!isrv('aggregate_total_prefix')) {
						srv('aggregate_total_prefix', '');
					}

					if (!isrv('aggregate_order_type')) {
						srv('aggregate_order_type', 0);
					}

					$item_no = form_input_validate(gnrv('item_no'), 'item_no', '^[0-9]+$', true, 3);

					$ag_data['aggregate_template_id'] = 0;
					$ag_data['template_propogation']  = '';
					$ag_data['graph_template_id']     = form_input_validate(gnrv('graph_template_id'), 'graph_template_id', '^[0-9]+$', true, 3);
					$ag_data['gprint_prefix']         = form_input_validate(gnrv('gprint_prefix'), 'gprint_prefix', '', true, 3);
					$ag_data['gprint_format']         = isset_request_var('gprint_format') ? 'on' : '';
					$ag_data['graph_type']            = form_input_validate(gnrv('aggregate_graph_type'), 'aggregate_graph_type', '^[0-9]+$', true, 3);
					$ag_data['total']                 = form_input_validate(gnrv('aggregate_total'), 'aggregate_total', '^[0-9]+$', true, 3);
					$ag_data['total_type']            = form_input_validate(gnrv('aggregate_total_type'), 'aggregate_total_type', '^[0-9]+$', true, 3);
					$ag_data['total_prefix']          = form_input_validate(gnrv('aggregate_total_prefix'), 'aggregate_total_prefix', '', true, 3);
					$ag_data['order_type']            = form_input_validate(gnrv('aggregate_order_type'), 'aggregate_order_type', '^[0-9]+$', true, 3);
				} else {
					$template_data = db_fetch_row_prepared('SELECT *
						FROM aggregate_graph_templates
						WHERE id = ?',
						[gnrv('aggregate_template_id')]);

					$item_no = db_fetch_cell_prepared('SELECT COUNT(*)
						FROM aggregate_graph_templates_item
						WHERE aggregate_template_id = ?',
						[gnrv('aggregate_template_id')]);

					$ag_data['aggregate_template_id'] = gnrv('aggregate_template_id');
					$ag_data['template_propogation']  = 'on';
					$ag_data['graph_template_id']     = $template_data['graph_template_id'];
					$ag_data['gprint_prefix']         = $template_data['gprint_prefix'];
					$ag_data['gprint_format']         = $template_data['gprint_format'];
					$ag_data['graph_type']            = $template_data['graph_type'];
					$ag_data['total']                 = $template_data['total'];
					$ag_data['total_type']            = $template_data['total_type'];
					$ag_data['total_prefix']          = $template_data['total_prefix'];
					$ag_data['order_type']            = $template_data['order_type'];
				}

				// create graph in cacti tables
				$local_graph_id = aggregate_graph_save(
					$local_graph_id,
					$ag_data['graph_template_id'],
					$graph_title,
					$ag_data['aggregate_template_id']
				);

				$ag_data['local_graph_id']     = $local_graph_id;
				$aggregate_graph_id            = sql_save($ag_data, 'aggregate_graphs');
				$ag_data['aggregate_graph_id'] = $aggregate_graph_id;

				// 	/* save member graph info */
				// 	$i = 1;
				// 	foreach($member_graphs as $graph_id) {
				// 		db_execute("INSERT INTO aggregate_graphs_items
				// 			(aggregate_graph_id, local_graph_id, sequence)
				// 			VALUES
				// 			($aggregate_graph_id, $graph_id, $i)"
				// 		);
				// 		$i++;
				// 	}

				// save aggregate graph - graph items
				if (grv('drp_action') == '9') {
					// get existing item ids and sequences from graph template
					$graph_templates_items = array_rekey(
						db_fetch_assoc_prepared('SELECT id, sequence
							FROM graph_templates_item
							WHERE local_graph_id=0
							AND graph_template_id = ?',
							[$ag_data['graph_template_id']]),
						'id', ['sequence']
					);

					// update graph template item values with posted values
					aggregate_validate_graph_items($_POST, $graph_templates_items);

					$aggregate_graph_items = [];

					foreach ($graph_templates_items as $item_id => $data) {
						$item_new                            = [];
						$item_new['aggregate_graph_id']      = $aggregate_graph_id;
						$item_new['graph_templates_item_id'] = $item_id;

						$item_new['color_template']          = isset($data['color_template']) ? $data['color_template'] : 0;
						$item_new['item_skip']               = isset($data['item_skip']) ? 'on' : '';
						$item_new['item_total']              = isset($data['item_total']) ? 'on' : '';
						$item_new['sequence']                = isset($data['sequence']) ? $data['sequence'] : 0;

						$aggregate_graph_items[]             = $item_new;
					}

					aggregate_graph_items_save($aggregate_graph_items, 'aggregate_graphs_graph_item');
				} else {
					$aggregate_graph_items = db_fetch_assoc_prepared('SELECT *
						FROM aggregate_graph_templates_item
						WHERE aggregate_template_id = ?',
						[$ag_data['aggregate_template_id']]);
				}

				$attribs                     = $ag_data;
				$attribs['graph_title']      = $ag_data['title_format'];
				$attribs['reorder']          = $ag_data['order_type'];
				$attribs['item_no']          = $item_no;
				$attribs['color_templates']  = [];
				$attribs['skipped_items']    = [];
				$attribs['total_items']      = [];
				$attribs['graph_item_types'] = [];
				$attribs['cdefs']            = [];

				foreach ($aggregate_graph_items as $item) {
					if (isset($item['color_template']) && $item['color_template'] > 0) {
						$attribs['color_templates'][$item['sequence']] = $item['color_template'];
					}

					if (isset($item['item_skip']) && $item['item_skip'] == 'on') {
						$attribs['skipped_items'][$item['sequence']] = $item['sequence'];
					}

					if (isset($item['item_total']) && $item['item_total'] == 'on') {
						$attribs['total_items'][$item['sequence']] = $item['sequence'];
					}

					if (isset($item['cdef_id']) && isset($item['t_cdef_id']) && $item['t_cdef_id'] == 'on') {
						$attribs['cdefs'][$item['sequence']] = $item['cdef_id'];
					}

					if (isset($item['graph_type_id']) && isset($item['t_graph_type_id']) && $item['t_graph_type_id'] == 'on') {
						$attribs['graph_item_types'][$item['sequence']] = $item['graph_type_id'];
					}
				}

				// create actual graph items
				aggregate_create_update($local_graph_id, $member_graphs, $attribs);

				header("Location: aggregate_graphs.php?action=edit&tab=details&id=$local_graph_id");

				exit;
			} elseif (grv('drp_action') == '8') { // automation
				cacti_log('automation_graph_action_execute called: ' . grv('drp_action'), true, 'AUTM8 TRACE', POLLER_VERBOSITY_MEDIUM);

				// work on all selected graphs
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					automation_execute_graph_create_tree($selected_items[$i]);
				}
			} elseif (grv('drp_action') == '11') {
				// Add to a report
				$good = true;

				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					if (!reports_add_graphs(gfrv('report_id'), $selected_items[$i], grv('timespan'), grv('align'))) {
						raise_message('reports_add_error');
						$good = false;

						break;
					}
				}

				if ($good) {
					raise_message('reports_graphs_added');
				}
			} else {
				api_plugin_hook_function('graphs_action_execute', grv('drp_action'));
			}

			// update snmpcache
			snmpagent_graphs_action_bottom([grv('drp_action'), $selected_items]);
			api_plugin_hook_function('graphs_action_bottom', [grv('drp_action'), $selected_items]);
		}

		if (grv('drp_action') == '2') { // change graph template
			header('Location: graphs.php?template_id=-1');
		} else {
			header('Location: graphs.php');
		}

		exit;
	} else {
		$ilist  = '';
		$iarray = [];
		$i      = 0;

		// global variables
		$flist    = '';
		$footer   = '';
		$rbutton1 = '';
		$rbutton2 = '';
		$return   = false;
		$rmessage = '';

		$garray       = [];
		$devices      = [];
		$atemplates   = [];
		$reports      = [];
		$data_sources = [];
		$gtarray      = [];
		$iarray       = [];

		// loop through each of the graphs selected on the previous page and get more info about them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$local_graph_id = intval($matches[1]);

				$ilist .= '<li>' . htmle(get_graph_title($local_graph_id)) . '</li>';

				$iarray[] = $matches[1];

				if ($i == 0) {
					$graph = db_fetch_row_prepared('SELECT id AS local_graph_id, graph_template_id
						FROM graph_local
						WHERE id = ?
						LIMIT 1',
						[$matches[1]]);

					$gtsql = db_fetch_assoc(get_common_graph_templates($graph));

					if (cacti_sizeof($gtsql)) {
						$gtarray = array_rekey($gtsql, 'id', 'name');
					}

					$i++;
				}
			}
		}

		if (cacti_sizeof($iarray)) {
			if (grv('drp_action') == '1') { // delete
				$ds_preselected_delete = read_config_option('ds_preselected_delete');

				if ($ds_preselected_delete == 'on') {
					$rbutton1 = '2';
					$rbutton2 = '1';
				} else {
					$rbutton1 = '1';
					$rbutton2 = '2';
				}

				// find out which (if any) data sources are being used by this graph, so we can tell the user
				if (cacti_sizeof($iarray)) {
					$data_sources = array_rekey(
						db_fetch_assoc('SELECT DISTINCT dtd.local_data_id, dtd.name_cache
							FROM data_template_data AS dtd
							INNER JOIN data_template_rrd AS dtr
							ON dtr.local_data_id=dtd.local_data_id
							INNER JOIN graph_templates_item AS gti
							ON dtr.id=gti.task_item_id
							WHERE ' . array_to_sql_or($iarray, 'gti.local_graph_id') . '
							AND dtd.local_data_id > 0'),
						'local_data_id', ['local_data_id', 'name_cache']);

					// data sources to delete
					$data_array = array_keys($data_sources);

					if (cacti_sizeof($data_array)) {
						$not_deletable = array_rekey(
							db_fetch_assoc('SELECT DISTINCT dtd.local_data_id
								FROM data_template_data AS dtd
								INNER JOIN data_template_rrd AS dtr
								ON dtr.local_data_id=dtd.local_data_id
								INNER JOIN graph_templates_item AS gti
								ON dtr.id=gti.task_item_id
								WHERE gti.local_graph_id NOT IN(' . implode(',', $iarray) . ')
								AND gti.local_graph_id NOT IN(SELECT local_graph_id FROM aggregate_graphs)
								AND dtr.local_data_id IN(' . implode(',', $data_array) . ')
								AND dtd.local_data_id > 0'),
							'local_data_id', 'local_data_id');
					} else {
						$not_deletable = [];
					}

					if (cacti_sizeof($not_deletable)) {
						$data_sources = array_rekey(
							db_fetch_assoc('SELECT DISTINCT dtd.local_data_id, dtd.name_cache
								FROM data_template_data AS dtd
								INNER JOIN data_template_rrd AS dtr
								ON dtr.local_data_id=dtd.local_data_id
								INNER JOIN graph_templates_item AS gti
								ON dtr.id=gti.task_item_id
								WHERE gti.local_graph_id IN (' . implode(',', $iarray) . ')
								AND gti.local_graph_id NOT IN(SELECT local_graph_id FROM aggregate_graphs)
								AND dtr.local_data_id NOT IN (' . implode(',', $not_deletable) . ')
								AND dtd.local_data_id > 0'),
							'local_data_id', ['local_data_id', 'name_cache']);
					}
				}

				if (cacti_sizeof($data_sources)) {
					foreach ($data_sources as $id => $data) {
						$flist .= '<li>' . htmle($data['name_cache']) . '</li>';
					}
				}
			} elseif (grv('drp_action') == '5') {
				$devices = array_rekey(
					db_fetch_assoc("SELECT id, CONCAT_WS('', description, ' (',hostname,')') AS name
						FROM host
						ORDER BY description, hostname"),
					'id', 'name'
				);
			} elseif (grv('drp_action') == '9') {
				$return_code    = false;
				$data_sources   = [];
				$graph_template = '';
				$message        = '';

				if (aggregate_get_data_sources($iarray, $data_sources, $graph_template, $message)) {
					foreach ($data_sources as $ds) {
						$flist .= '<li>' . htmle($ds['name_cache']) . '</li>';
					}

					$gprint_prefix  = '|host_description|';
					$ttitle         = $iarray[0];

					// aggregate form
					$_aggregate_defaults = [
						'title_format'      => auto_title($ttitle),
						'graph_template_id' => $graph_template,
						'gprint_prefix'     => $gprint_prefix
					];

					$helper_string = '|host_description|';

					if ($graph_template > 0) {
						$data_query = db_fetch_cell_prepared('SELECT snmp_query_id
							FROM snmp_query_graph
							WHERE graph_template_id = ?',
							[$graph_template]);

						if ($data_query > 0) {
							$data_query_info = get_data_query_array($data_query);

							foreach ($data_query_info['fields'] as $field_name => $field_array) {
								if ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') {
									$helper_string .= ($helper_string != '' ? ', ' : '') . '|query_' . $field_name . '|';
								}
							}
						}
					}

					// Append the helper string
					$struct_aggregate['suggestions'] = [
						'method'        => 'other',
						'friendly_name' => __('Prefix Replacement Values'),
						'description'   => __('You may use these replacement values for the Prefix in the Aggregate Graph'),
						'value'         => $helper_string
					];

					// We are storing the stdout to a variable here
					ob_start();

					draw_edit_form(
						[
							'config' => ['no_form_tag' => true],
							'fields' => inject_form_variables($struct_aggregate, $_aggregate_defaults)
						]
					);

					// draw all graph items of first graph, including a html_start_box
					draw_aggregate_graph_items_list(0, $graph_template);

					graph_aggregate_javascript();

					$footer = ob_get_clean();
				} else {
					$return   = true;
					$rmessage = $message;
				}
			} elseif (grv('drp_action') == '10') { // aggregate template
				// initialize return code and graphs array
				$data_sources   = [];
				$graph_template = '';
				$message        = '';

				// find out which (if any) data sources are being used by this graph, so we can tell the user
				if (aggregate_get_data_sources($iarray, $data_sources, $graph_template, $message)) {
					$atemplates = db_fetch_assoc_prepared('SELECT id, name
						FROM aggregate_graph_templates
						WHERE graph_template_id = ?
						ORDER BY name', [$graph_template]);

					if (cacti_sizeof($atemplates)) {
						$atemplates = array_rekey($atemplates, 'id', 'name');
					} else {
						$return   = true;
						$rmessage = __('There were not Aggregate Templates found for the selected Graphs');
					}
				} else {
					$return   = true;
					$rmessage = $message;
				}
			} elseif (grv('drp_action') == '11') { // add to report
				$reports = db_fetch_assoc_prepared('SELECT id, name
					FROM reports
					WHERE user_id = ?
					ORDER BY name',
					[$_SESSION[SESS_USER_ID]]);

				if (cacti_sizeof($reports)) {
					$reports = array_rekey($reports, 'id', 'name');
				}
			}
		}

		// for use by plugins
		$save['drp_action']  = gnrv('drp_action');
		$save['graph_list']  = $ilist;
		$save['graph_array'] = $iarray;

		$form_data = [
			'general' => [
				'page'       => 'graphs.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage'  => __('Click \'Continue\' to Delete the following Graph.'),
					'pmessage'  => __('Click \'Continue\' to Delete following Graphs.'),
					'scont'     => __('Delete Graph'),
					'pcont'     => __('Delete Graphs'),
					'flist'     => $flist,
					'sfmessage' => __n('The following Data Source is used by this Graph.', 'The following Data Sources are used by this Graph.', cacti_sizeof($data_sources)),
					'pfmessage' => __n('The following Data Source is used by these Graphs.', 'The following Data Sources are used by these Graphs.', cacti_sizeof($iarray)),
					'extra'     => [
						'delete_type' => [
							'method'  => 'radio',
							'title'   => __('Delete Method'),
							'default' => read_config_option('ds_preselected_delete') == 'on' ? 2 : 1,
							'items'   => [
								0 => [
									'radio_value'   => '2',
									'radio_caption' => __n('Delete the Data Sources referenced by this Graph', 'Delete the Data Sources reference by these Graphs', cacti_sizeof($iarray))
								],
								1 => [
									'radio_value'   => '1',
									'radio_caption' => __n('Leave the Data Source Untouched', 'Leave the Data Sources Untouched', cacti_sizeof($data_sources))
								]
							]
						]
					]
				],
				2 => [
					'smessage' => __('Choose a Graph Template and click \'Continue\' to Change the Graph Template for the following Graph.  Note that only compatible Graph Templates will be displayed.  Compatible is identified by those having identical Data Sources.'),
					'pmessage' => __('Choose a Graph Template and click \'Continue\' to Change the Graph Template for the following Graphs.  Note that only compatible Graph Templates will be displayed.  Compatible is identified by those having identical Data Sources.'),
					'cont'     => __('Change Graph Template'),
					'extra'    => [
						'graph_template_id' => [
							'method'  => 'drop_array',
							'title'   => __('New Graph Template'),
							'default' => '',
							'array'   => $gtarray
						]
					]
				],
				3 => [
					'smessage' => __('Click \'Continue\' to Duplicate the following Graph.'),
					'pmessage' => __('Click \'Continue\' to Duplicate following Graphs.'),
					'scont'    => __('Duplicate Graph'),
					'pcont'    => __('Duplicate Graphs'),
					'extra'    => [
						'title_format' => [
							'method'  => 'textbox',
							'title'   => __('Title Format'),
							'default' => '<graph_title> (1)',
							'width'   => 255,
							'size'    => 30
						]
					]
				],
				4 => [
					'smessage' => __('Click \'Continue\' to Convert the following Graph to a Graph Template.'),
					'pmessage' => __('Click \'Continue\' to Convert the following Graphs to Graph Templates.'),
					'scont'    => __('Convert Graph to Template'),
					'pcont'    => __('Convert Graphs to Templates'),
					'extra'    => [
						'title_format' => [
							'method'  => 'textbox',
							'title'   => __('Title Format'),
							'default' => '<graph_title> Template',
							'width'   => 255,
							'size'    => 30
						]
					]
				],
				5 => [
					'smessage' => __('Choose a Device and click \'Continue\' to Change the Graph to the new Device.'),
					'pmessage' => __('Choose a Device and click \'Continue\' to Change the Graphs to the new Device.'),
					'scont'    => __('Change Graph to new Device'),
					'pcont'    => __('Change Graphs to new Device'),
					'extra'    => [
						'host_id' => [
							'method'  => 'drop_array',
							'title'   => __('New Device'),
							'default' => '',
							'array'   => $devices
						]
					]
				],
				6 => [
					'smessage' => __('Click \'Continue\' to Reapply Suggested Names the following Graph.'),
					'pmessage' => __('Click \'Continue\' to Reapply Suggested Names for the following Graphs.'),
					'scont'    => __('Reapply Suggested Names for Graph'),
					'pcont'    => __('Reapply Suggested Names for Graphs')
				],
				8 => [
					'smessage' => __('Click \'Continue\' to apply Automation Rules to the following Graph.'),
					'pmessage' => __('Click \'Continue\' to apply Automation Rules to the following Graphs.'),
					'scont'    => __('Apply Automation Rules to Graph'),
					'pcont'    => __('Apply Automation Rules to Graphs')
				],
				9 => [
					'smessage'  => __('Click \'Continue\' to Create an Aggregate Graph from the selected Graph.'),
					'pmessage'  => __('Click \'Continue\' to Create an Aggregate Graph from the selected Graphs.'),
					'cont'      => __('Create Aggregate Graph'),
					'return'    => $return,
					'rmessage'  => $rmessage,
					'footer'    => $footer,
					'flist'     => $flist,
					'sfmessage' => __n('The following Data Source is in use by this Graph.', 'The following Data Sources are in use by these Graphs.', cacti_sizeof($data_sources)),
					'pfmessage' => __n('The following Data Source is in use by these Graphs.', 'The following Data Sources are in use by these Graphs.', cacti_sizeof($data_sources)),
				],
				10 => [
					'smessage' => __('Choose an Aggregate Template and click \'Continue\' to Create the Aggregate Graph from the following Graph.'),
					'pmessage' => __('Choose an Aggregate Template and click \'Continue\' to Create the Aggregate Graph from the following Graphs.'),
					'cont'     => __('Create Aggregate Graph'),
					'return'   => $return,
					'rmessage' => $rmessage,
					'extra'    => [
						'aggregate_template_id' => [
							'method'  => 'drop_array',
							'title'   => __('Aggregate Template'),
							'default' => '',
							'array'   => $atemplates,
						]
					]
				],
				11 => [
					'smessage' => __('Click \'Continue\' to Place the following Graph on a Report.'),
					'pmessage' => __('Click \'Continue\' to Place the following Graphs on a Report.'),
					'scont'    => __('Place Graph on Report'),
					'pcont'    => __('Place Graphs on Report'),
					'extra'    => [
						'report_id' => [
							'method'  => 'drop_array',
							'title'   => __('Report Name'),
							'array'   => $reports,
							'default' => array_key_first($reports)
						],
						'timespan' => [
							'method'  => 'drop_array',
							'title'   => __('Timespan'),
							'array'   => $graph_timespans,
							'default' => read_user_setting('default_timespan')
						],
						'align' => [
							'method'  => 'drop_array',
							'title'   => __('Align'),
							'array'   => $alignment,
							'default' => REPORTS_ALIGN_CENTER
						]
					]
				],
			]
		];

		$trees = db_fetch_assoc('SELECT id, name FROM graph_tree ORDER BY name');

		if (cacti_sizeof($trees)) {
			foreach ($trees as $tree) {
				$form_data['options']['tr_' . $tree['id']] = [
					'smessage' => __esc('Click \'Continue\' to Place the following Graph on Tree %s.', $tree['name']),
					'pmessage' => __esc('Click \'Continue\' to Duplicate following Graphs on Tree %s.', $tree['name']),
					'scont'    => __('Place Graph on Tree'),
					'pcont'    => __('Place Graphs on Tree'),
					'extra'    => [
						'tree_item_id' => [
							'method'  => 'drop_branch',
							'title'   => __('Destination Branch'),
							'id'      => $tree['id']
						]
					],
					'eaction'   => 'tree_id',
					'eactionid' => $tree['id'],
				];
			}
		}

		$form_data = api_plugin_hook_function('graphs_confirmation_form', $form_data);

		form_continue_confirmation($form_data, 'graphs_action_prepare', $save);
	}
}

function graph_aggregate_javascript() : void {
	?>
	<script type='text/javascript'>
	function changeTotals() {
		switch ($('#aggregate_total').val()) {
			case '<?php print AGGREGATE_TOTAL_NONE; ?>':
				$('#aggregate_total_type').prop('disabled', true);
				if ($('#aggregate_total_type').selectmenu('instance')) {
					$('#aggregate_total_type').selectmenu('disable');
				}

				$('#aggregate_total_prefix').prop('disabled', true);
				if ($('#aggregate_total_prefix').selectmenu('instance')) {
					$('#aggregate_total_prefix').selectmenu('disable');
				}

				$('#aggregate_order_type').prop('disabled', false);
				if ($('#aggregate_order_type').selectmenu('instance')) {
					$('#aggregate_order_type').selectmenu('enable');
				}

				break;
			case '<?php print AGGREGATE_TOTAL_ALL; ?>':
				$('#aggregate_total_type').prop('disabled', false);
				if ($('#aggregate_total_type').selectmenu('instance')) {
					$('#aggregate_total_type').selectmenu('enable');
				}

				$('#aggregate_total_prefix').prop('disabled', false);
				if ($('#aggregate_total_prefix').selectmenu('instance')) {
					$('#aggregate_total_prefix').selectmenu('enable');
				}

				$('#aggregate_order_type').prop('disabled', false);
				if ($('#aggregate_order_type').selectmenu('instance')) {
					$('#aggregate_order_type').selectmenu('enable');
				}

				changeTotalsType();
				break;
			case '<?php print AGGREGATE_TOTAL_ONLY; ?>':
				$('#aggregate_total_type').prop('disabled', false);
				if ($('#aggregate_total_type').selectmenu('instance')) {
					$('#aggregate_total_type').selectmenu('enable');
				}

				$('#aggregate_total_prefix').prop('disabled', false);
				if ($('#aggregate_total_prefix').selectmenu('instance')) {
					$('#aggregate_total_prefix').selectmenu('enable');
				}

				$('#aggregate_order_type').prop('disabled', true);
				if ($('#aggregate_order_type').selectmenu('instance')) {
					$('#aggregate_order_type').selectmenu('disable');
				}

				changeTotalsType();
				break;
		}
	}

	function changeTotalsType() {
		if (($('#aggregate_total_type').val() == <?php print AGGREGATE_TOTAL_TYPE_SIMILAR; ?>)) {
			$('#aggregate_total_prefix').attr('value', '<?php print __('Total'); ?>');
		} else if (($('#aggregate_total_type').val() == <?php print AGGREGATE_TOTAL_TYPE_ALL; ?>)) {
			$('#aggregate_total_prefix').attr('value', '<?php print __('All Items'); ?>');
		}
	}

	function checkSubmit() {
		if ($('input[id^="agg_total"]:checked').length == 0) {
			$('button[type="submit"]').prop('disable', true).addClass('ui-state-disabled');;
		} else {
			$('button[type="submit"]').prop('disable', true).removeClass('ui-state-disabled');;
		}
	}

	$(function() {
		$('#aggregate_total').change(function() {
			changeTotals();
		});

		$('#aggregate_total_type').change(function() {
			changeTotalsType();
		});

		$('input[id^="agg_total"], input[id^="agg_skip"]').click(function() {
			id = $(this).attr('id');

			if (id.indexOf('skip') > 0) {
				altId = id.replace('skip', 'total');
			} else {
				altId = id.replace('total', 'skip');
			}

			if ($('#'+id).is(':checked')) {
				$('#'+altId).prop('checked', false);
			} else {
				$('#'+altId).prop('checked', true);
			}

			checkSubmit();
		});

		$('input[id^="agg_skip"]').each(function() {
			$(this).prop('checked', true);
		});

		changeTotals();
		checkSubmit();
	});
	</script>
	<?php
}

function item() : void {
	global $consolidation_functions, $graph_item_types, $struct_graph_item;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	if (ierv('id')) {
		$template_item_list = [];

		$header_label = __('Graph Items [new]');
		$add_text     = '';
		$anchor_link  = '';
	} else {
		$template_item_list = db_fetch_assoc_prepared("SELECT
			gti.id, gti.text_format, gti.value, gti.hard_return, gti.graph_type_id, gti.alpha, gti.alpha2, gti.textalign,
			gti.consolidation_function_id, gti.sequence,
			CONCAT(dtd.name_cache, ' (',  dtr.data_source_name, ')') AS data_source_name,
			cd.name AS cdef_name, c.hex, c2.hex AS hex2, gti.legend,
			vd.name AS vdef_name, gtgp.name AS gprint_name
			FROM graph_templates_item AS gti
			LEFT JOIN data_template_rrd AS dtr
			ON gti.task_item_id = dtr.id
			LEFT JOIN data_local AS dl
			ON dtr.local_data_id = dl.id
			LEFT JOIN data_template_data AS dtd
			ON dl.id = dtd.local_data_id
			LEFT JOIN graph_templates_gprint AS gtgp
			ON gprint_id = gtgp.id
			LEFT JOIN cdef AS cd
			ON cdef_id = cd.id
			LEFT JOIN vdef AS vd
			ON vdef_id = vd.id
			LEFT JOIN colors AS c
			ON color_id = c.id
			LEFT JOIN colors AS c2
			ON color_id = c2.id
			WHERE gti.local_graph_id = ?
			ORDER BY gti.sequence", [grv('id')]);

		$template_item_list = api_plugin_hook_function('graphs_item_array', $template_item_list);

		$host_id = db_fetch_cell_prepared('SELECT host_id
			FROM graph_local
			WHERE id = ?',
			[grv('id')]);

		$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
			FROM graph_local
			WHERE id = ?',
			[grv('id')]);

		$header_label = __esc('Graph Items [edit: %s]', get_graph_title(grv('id')));
		$add_text     = 'graphs.php?action=item_edit' . (!empty($host_id) ? '&host_id=' . $host_id : '') . '&local_graph_id=' . grv('id');
		$anchor_link  = 'host_id=' . $host_id . '&local_graph_id=' . grv('id');
	}

	html_start_box($header_label, '100%', false, 3, 'center', $add_text);

	draw_graph_items_list($template_item_list, 'graphs.php', $anchor_link, (empty($graph_template_id) || empty($host_id) ? false : true));

	?>
	<script type='text/javascript'>
	$(function() {
		$('.deleteMarker, .moveArrow').unbind().click(function(event) {
			event.preventDefault();
			loadUrl({url:$(this).attr('href')})
		});

		$('#graphs_graph_edit2_child').attr('id', 'item_ids');
		$('#item_ids').find('tr:first').addClass('nodrag').addClass('nodrop');
		$('#item_ids').tableDnD({
			onDrop: function(table, row) {
				loadUrl({url:'graphs.php?action=ajax_dnd&id=<?php isrv('id') ? print grv('id') : print 0; ?>&'+$.tableDnD.serialize()});
			}
		});
	});
	</script>
	<?php

	html_end_box();
}

function is_multi_device_graph(int $local_graph_id) : bool {
	$devices = db_fetch_cell_prepared('SELECT COUNT(DISTINCT host_id)
		FROM data_template_rrd AS dtr
		INNER JOIN graph_templates_item AS gti
		ON gti.task_item_id = dtr.id
		INNER JOIN data_local AS dl
		ON dl.id = dtr.local_data_id
		WHERE gti.local_graph_id = ?',
		[$local_graph_id]);

	return $devices > 1 ? true : false;
}

function graph_edit() : void {
	global $struct_graph, $image_types, $consolidation_functions, $graph_item_types, $struct_graph_item;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	$use_graph_template = true;

	$locked = 'false';
	$graph  = [];

	if (!ierv('id')) {
		$_SESSION['sess_graph_lock_id'] = grv('id');

		$local_graph_template_graph_id = db_fetch_cell_prepared('SELECT local_graph_template_graph_id
			FROM graph_templates_graph
			WHERE local_graph_id = ?',
			[grv('id')]);

		$auto_unlock = read_config_option('graphs_auto_unlock');

		if (grv('id') != $_SESSION['sess_graph_lock_id'] && !empty($local_graph_template_graph_id)) {
			if ($auto_unlock == 'on') {
				$locked = false;
			} else {
				$locked = true;
			}
			$_SESSION['sess_graph_locked'] = $locked;
		} elseif (empty($local_graph_template_graph_id)) {
			$locked = false;

			$_SESSION['sess_graph_locked'] = $locked;
		} elseif (isset($_SESSION['sess_graph_locked'])) {
			$locked = $_SESSION['sess_graph_locked'];
		} else {
			if ($auto_unlock == 'on') {
				$locked = false;
			} else {
				$locked = true;
			}

			$_SESSION['sess_graph_locked'] = $locked;
		}

		$graph = db_fetch_row_prepared('SELECT *
			FROM graph_templates_graph
			WHERE local_graph_id = ?',
			[grv('id')]);

		$graph_template = db_fetch_row_prepared('SELECT *
			FROM graph_templates_graph
			WHERE id = ?',
			[$local_graph_template_graph_id]);

		$host_id = db_fetch_cell_prepared('SELECT host_id
			FROM graph_local
			WHERE id = ?',
			[grv('id')]);

		// case of a deleted graph
		if (!cacti_sizeof($graph)) {
			raise_message(31);
			header('Location: graphs.php');

			exit;
		}

		$header_label = __esc('Graph [edit: %s]', get_graph_title(grv('id')));

		if ($graph['graph_template_id'] == '0') {
			$use_graph_template = 'false';
		}
	} else {
		$header_label       = __('Graph [new]');
		$use_graph_template = false;

		if (isrv('host_id') && gfrv('host_id') > 0) {
			$host_id = grv('host_id');
		} else {
			$host_id = 0;
		}

		if (ierv('graph_template_id')) {
			$locked = false;
		}
	}

	// handle debug mode
	if (isrv('debug')) {
		if (gnrv('debug') == '0') {
			kill_session_var('graph_debug_mode');
		} elseif (gnrv('debug') == '1') {
			$_SESSION['graph_debug_mode'] = true;
		}
	}

	if (!ierv('id')) {
		if (isset($_SESSION['graph_debug_mode'])) {
			$message = __('Turn Off Graph Debug Mode.');
			$debug   = true;
		} else {
			$message = __('Turn On Graph Debug Mode.');
			$debug   = false;
		}

		$data_sources = db_fetch_assoc_prepared('SELECT DISTINCT local_data_id
			FROM graph_templates_item AS gti
			INNER JOIN data_template_rrd AS dtr
			ON dtr.id = gti.task_item_id
			WHERE local_graph_id = ?',
			[grv('id')]);
	} else {
		$debug   = false;
		$message = '';

		$data_sources = [];
	}

	if (!empty($graph['local_graph_id'])) {
		$graph_template_id = get_current_graph_template($graph['local_graph_id']);

		$gtsql = get_common_graph_templates($graph);
	} else {
		$graph_template_id = 0;

		$gtsql = 'SELECT gt.id, gt.name
			FROM graph_templates AS gt
			WHERE id NOT IN (SELECT graph_template_id FROM snmp_query_graph)
			ORDER BY name';
	}

	$ins_buttons = [];

	if (cacti_sizeof($data_sources)) {
		foreach ($data_sources as $ds) {
			$name = db_fetch_cell_prepared('SELECT name_cache
				FROM data_template_data
				WHERE local_data_id = ?',
				[$ds['local_data_id']]);

			$ins_buttons[] = [
				'display' => __esc('Edit Data Source: \'%s\'.', $name),
				'url'     => 'data_sources.php?action=ds_edit&id=' . $ds['local_data_id'],
				'class'   => 'ti ti-affiliate newDevice'
			];
		}
	}

	if (!ierv('host_id') || !empty($host_id)) {
		$ins_buttons[] = [
			'display' => __('Edit Device'),
			'url'     => 'host.php?action=edit&id=' . ($host_id > 0 ? $host_id : grv('host_id')),
			'class'   => 'ti ti-server editDevice'
		];
	}

	$filters = [
		'rows' => [
			[
				'graph_template_id' => [
					'method'        => 'drop_sql',
					'friendly_name' => __('Selected Graph Template'),
					'description'   => __('Choose a Graph Template to apply to this Graph. Please note that you may only change Graph Templates to a 100%% compatible Graph Template, which means that it includes identical Data Sources.'),
					'filter'        => FILTER_VALIDATE_INT,
					'value'         => $graph_template_id,
					'none_value'    => (!isset($graph['graph_template_id']) || $graph['graph_template_id'] == 0 ? __('None') : ''),
					'sql'           => $gtsql
				],
				'host_id' => [
					'method'        => 'drop_callback',
					'friendly_name' => __('Device'),
					'description'   => __('Choose the Device that this Graph belongs to.'),
					'filter'        => FILTER_VALIDATE_INT,
					'sql'           => 'SELECT id, description as name FROM host ORDER BY name',
					'action'        => 'ajax_hosts_noany',
					'none_value'    => __('None'),
					'id'            => $host_id,
					'value'         => db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', [$host_id]),
				],
				'graph_template_graph_id' => [
					'method' => 'hidden',
					'filter' => FILTER_VALIDATE_INT,
					'value'  => (isset($graph['id']) ? $graph['id'] : '0')
				],
				'local_graph_id' => [
					'method' => 'hidden',
					'filter' => FILTER_VALIDATE_INT,
					'value'  => (isset($graph['local_graph_id']) ? $graph['local_graph_id'] : '0')
				],
				'local_graph_template_graph_id' => [
					'method' => 'hidden',
					'filter' => FILTER_VALIDATE_INT,
					'value'  => (isset($graph['local_graph_template_graph_id']) ? $graph['local_graph_template_graph_id'] : '0')
				],
				'graph_template_id_prev' => [
					'method' => 'hidden',
					'filter' => FILTER_VALIDATE_INT,
					'value'  => $graph_template_id
				],
				'host_id_prev' => [
					'method' => 'hidden',
					'filter' => FILTER_VALIDATE_INT,
					'value'  => (isset($host_id) ? $host_id : '0')
				],
				'id' => [
					'method'  => 'validate',
					'filter'  => FILTER_VALIDATE_INT,
					'default' => '',
				]
			]
		],
		'links' => [
			[
				'display' => $message,
				'url'     => 'graphs.php?action=graph_edit&id=' . (isrv('id') ? grv('id') : '0') . '&debug=' . (isset($_SESSION['graph_debug_mode']) ? '0' : '1'),
				'class'   => ($debug ? 'ti ti-bug disableDebug' : 'ti ti-bug enableDebug')
			],
			[
				'display' => __('Edit Graph Template'),
				'url'     => 'graph_templates.php?action=template_edit&id=' . (isset($graph['graph_template_id']) ? $graph['graph_template_id'] : '0'),
				'class'   => 'ti ti-edit editTemplate'
			],
		]
	];

	if (cacti_sizeof($ins_buttons)) {
		foreach ($ins_buttons as $button) {
			$filters['links'][] = $button;
		}
	}

	$filters['links'][] = [
		'display' => ($locked ? __('Unlock Graph') : __('Lock Graph')),
		'url'     => 'graphs.php?action=' . ($locked ? 'unlock' : 'lock') . '&id=' . grv('id'),
		'class'   => ($locked ? 'ti ti-lock-off' : 'ti ti-lock')
	];

	$filters['links'][] = [
		'display' => __('View Timespan Graphs'),
		'url'     => 'graphs.php?action=view&rra_id=0&local_graph_id=' . grv('id'),
		'class'   => 'ti ti-chart-area-line-filled threeBars'
	];

	if (cacti_sizeof($graph)) {
		if ($graph['graph_template_id'] == 0) {
			$filters['rows'][0]['graph_template_id']['method'] = 'validate';
			$filters['rows'][0]['graph_template_id']['value']  = '0';
		}

		if ($graph['graph_template_id'] > 0 && $host_id > 0) {
			$filters['rows'][0]['graph_template_id']['method'] = 'validate';
			$filters['rows'][0]['host_id']['method']           = 'validate';
		}

		if (is_multi_device_graph($graph['local_graph_id'])) {
			$filters['rows'][0]['host_id']['method'] = 'validate';
		}
	}

	// process plugin links
	form_start('graphs.php');

	$pageFilter = new CactiTableFilter($header_label, 'graphs.php?action=edit&id=' . grv('id'), 'graphs', 'sess_graph_edit', '', '', false);
	$pageFilter->set_filter_array($filters);
	$pageFilter->render();

	// only display the "inputs" area if we are using a graph template for this graph
	if (!empty($graph['graph_template_id'])) {
		html_start_box(__('Supplemental Graph Template Data'), '100%', true, 3, 'center', '');

		draw_nontemplated_fields_graph($graph['graph_template_id'], $graph, '|field|', __('Graph Fields'), true, true, 0);
		draw_nontemplated_fields_graph_item($graph['graph_template_id'], grv('id'), '|field|_|id|', __('Graph Item Fields'), true, $locked);

		html_end_box(true, true);
	}

	// graph item list goes here
	if ((!ierv('id')) && (empty($graph['graph_template_id']))) {
		item();
	}

	$graph_start = -14400;
	$graph_end   = '-' . read_config_option('poller_interval');

	$graph['src'] = CACTI_PATH_URL . 'graph_json.php?local_graph_id=' . grv('id') . '&rra_id=0&graph_start=' . $graph_start . '&graph_end=' . $graph_end . '&v=' . mt_rand();

	if (!ierv('id')) {
		?>
		<div class='cactiTable'>
			<div id='graphLocation' class='center'></div>
		<?php
		if ((isset($_SESSION['graph_debug_mode'])) && (isrv('id'))) {
			$graph_data_array['output_flag']  = RRDTOOL_OUTPUT_STDERR;
			$graph_data_array['print_source'] = 1;
			$graph_data_array['graph_end']    = $graph_end;
			$graph_data_array['graph_start']  = $graph_start;

			$null_param = [];
			?>
		</div>
		<div class='cactiTable'>
			<div style='float:left'>
				<span class='textInfo'><?php print __('RRDtool Command:'); ?></span><br>
				<pre><?php print htmle(rrdtool_function_graph(grv('id'), 1, $graph_data_array, null, $null_param, $_SESSION[SESS_USER_ID])); ?></pre>
				<span class='textInfo'><?php print __('RRDtool Says:'); ?></span><br>
				<?php unset($graph_data_array['print_source']); ?>
				<pre><?php print(POLLER_ID == 1 ? htmle(rrdtool_function_graph(grv('id'), 1, $graph_data_array, null, $null_param, $_SESSION[SESS_USER_ID])) : __esc('Not Checked')); ?></pre>
			</div>
		<?php
		}
		?>
		</div>
		<br>
		<?php
	}

	if (((isrv('id')) || (isrv('new'))) && (empty($graph['graph_template_id']))) {
		html_start_box(__('Graph Configuration'), '100%', true, 3, 'center', '');

		$form_array = [];

		foreach ($struct_graph as $field_name => $field_array) {
			$form_array += [$field_name => $struct_graph[$field_name]];

			if (($field_array['method'] != 'header') && ($field_array['method'] != 'spacer')) {
				$form_array[$field_name]['value']   = (isset($graph[$field_name]) ? $graph[$field_name] : '');
				$form_array[$field_name]['form_id'] = (isset($graph[$field_name]) ? $graph['id'] : '0');

				if ($use_graph_template == true && isset($graph_template['t_' . $field_name]) && ($graph_template['t_' . $field_name] == 'on')) {
					$form_array[$field_name]['method']      = 'template_' . $form_array[$field_name]['method'];
					$form_array[$field_name]['description'] = '';
				}
			}
		}

		draw_edit_form(
			[
				'config' => ['no_form_tag' => true],
				'fields' => $form_array
			]
		);

		html_end_box(true, true);
	}

	if ((isrv('id')) || (isrv('new'))) {
		form_hidden_box('save_component_graph','1','');
		form_hidden_box('save_component_input','1','');
	} else {
		form_hidden_box('save_component_graph_new','1','');
	}

	form_hidden_box('rrdtool_version', get_rrdtool_version(), '');

	form_save_button('graphs.php', 'return');

	// Now we need some javascript to make it dynamic
	?>
	<script type='text/javascript'>

	var locked         = <?php print($locked ? 'true' : 'false'); ?>;
	var imageSource    = '<?php print $graph['src']; ?>';
	var originalWidth  = null;
	var originalHeight = null;

	function dynamic() {
		if ($('#scale_log_units').is(':checked')) {
			$('#scale_log_units').prop('disabled', true);
			if ($('#auto_scale_log').is(':checked')) {
				$('#scale_log_units').prop('disabled', false);
			}
		}
	}

	function changeScaleLog() {
		if ($('#scale_log_units').is(':checked')) {
			$('#scale_log_units').prop('disabled', true);
			if ($('#auto_scale_log').is(':checked')) {
				$('#scale_log_units').prop('disabled', false);
			}
		}
	}

	$(function() {
		dynamic();

		$.getJSON(imageSource)
			.done(function(data) {
				$('#graphLocation').html("<img class='cactiGraphImage' src='data:image/"+data.type+";base64,"+data.image+"' graph_start='"+data.graph_start+"' graph_end='"+data.graph_end+"' graph_left='"+data.graph_left+"' graph_top='"+data.graph_top+"' graph_width='"+data.graph_width+"' graph_height='"+data.graph_height+"' width='"+data.image_width+"' height='"+data.image_height+"' image_width='"+data.image_width+"' image_height='"+data.image_height+"' value_min='"+data.value_min+"' value_max='"+data.value_max+"'>");
				$(window).trigger('resize');
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			});

		$(window).resize(function() {
			var imageWidth  = $('.cactiGraphImage').width();
			var imageHeight = $('.cactiGraphImage').height();
			var aspectRatio = imageWidth/imageHeight;

			if (imageWidth > 0 && originalWidth == null) {
				originalWidth = imageWidth;
				originalHeight = imageHeight;
			}

			$('.cactiGraphImage').hide();

			var mainSize = $('#main').width();

			if (imageWidth > mainSize || mainSize < originalWidth) {
				var newWidth = mainSize - 40;

				aspectRatio = imageWidth / imageHeight;
				imageWidth  = newWidth;
				imageHeight = newWidth / aspectRatio;
				$('.cactiGraphImage').css({ width: imageWidth, height: imageHeight });
			} else if (mainSize > originalWidth) {
				$('.cactiGraphImage').css({ width: originalWidth, height: originalHeight });
			}

			$('.cactiGraphImage').show();
		}).trigger('resize');

		$('.ui-selectmenu-button').css('width', '360px');
		$('.ui-autocomplete-input').css('width', '340px');
	});

	if (locked) {
		$('input').not('input[value="cancel"]').not('input[value="return"]').prop('disabled', true);
		$('.moveArrow, .deleteMarker, .linkOverDark, .linkEditMain').attr('href', '#').removeClass('moveArrow').removeClass('deleteMarker');
		if ($('#submit').button('instance')) {
			$('#submit').button('disable');
		} else {
			$('#submit').prop('disabled', true);
		}
		$('#host_id_wrap').addClass('ui-selectmenu-disabled ui-state-disabled');
		$('#host_id_input').addClass('ui-state-disabled');
	}
	</script>
	<?php
	if (isrv('id')) {
		api_plugin_hook_function('graph_edit_after', grv('id'));
	} else {
		api_plugin_hook_function('graph_edit_after');
	}
}

function validate_graph_request_vars() : void {
	// ================= input validation and session storage =================
	$filters = [
		'custom' => [
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => ['options' => ['regexp' => '(true|false)']],
			'pageset' => true,
			'default' => ''
		],
		'local_graph_ids' => [
			'filter'  => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'pageset' => true,
			'default' => ''
		]
	];

	validate_store_request_vars($filters, 'sess_graph_custom');
	// ================= input validation =================
}

function graphs() : void {
	global $actions, $graph_sources, $item_rows, $image_types;

	// for custom non-stored request vars
	validate_graph_request_vars();

	// for main filter variables
	process_sanifize_draw_graphs_filter(true);

	if (grv('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	$sql_where   = '';
	$sql_where2  = '';
	$sql_params  = [];
	$sql_params2 = [];

	if (grv('rfilter') != '') {
		$sql_where = ' WHERE
		(
			gtg.title_cache RLIKE ? OR
			gt.name RLIKE ? OR
			gl.id = ?
		)';

		$sql_params[] = grv('rfilter');
		$sql_params[] = grv('rfilter');
		$sql_params[] = grv('rfilter');

		$sql_where2    = ' AND (gl.id = ?)';
		$sql_params2[] = grv('rfilter');
	}

	if (ierv('host_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' IFNULL(gl.host_id, 0) = 0';
		$sql_where2 .= ' AND gl.host_id = 0';
	} elseif (grv('host_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' gl.host_id = ?';
		$sql_params[]  = grv('host_id');
		$sql_where2 .= ' AND gl.host_id = ?';
		$sql_params2[] = grv('host_id');
	}

	if (grv('vdef_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' gti.vdef_id = ?';
		$sql_params[]  = grv('vdef_id');
		$sql_where2 .= ' AND gti.vdef_id = ?';
		$sql_params2[] = grv('vdef_id');
	}

	if (grv('cdef_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' gti.cdef_id = ?';
		$sql_params[]  = grv('cdef_id');
		$sql_where2 .= ' AND gti.cdef_id = ?';
		$sql_params2[] = grv('cdef_id');
	}

	if (ierv('site_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' IFNULL(h.site_id, 0) = 0';
		$sql_where2 .= ' AND h.site_id = 0';
	} elseif (grv('site_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' h.site_id = ?';
		$sql_params[]  = grv('site_id');
		$sql_where2 .= ' AND h.site_id = ?';
		$sql_params2[] = grv('site_id');
	}

	if (grv('template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' IFNULL(gtg.graph_template_id, 0) = 0';
		$sql_where2 .= ' AND gtg.graph_template_id = 0';
	} elseif (!ierv('template_id') && grv('template_id') != '-1') {
		$parts = explode('_', grv('template_id'));

		input_validate_input_number($parts[1], 'template_id[1]');

		if ($parts[0] == 'cg') {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' gl.graph_template_id = ?';
			$sql_params[]  = $parts[1];
			$sql_where2 .= ' AND gl.graph_template_id = ?';
			$sql_params2[] = $parts[1];
		} else {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' gl.snmp_query_graph_id = ?';
			$sql_params[]  = $parts[1];
			$sql_where2 .= ' AND gl.snmp_query_graph_id = ?';
			$sql_params2[] = $parts[1];
		}
	}

	if (grv('local_graph_ids') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' gl.id IN(' . grv('local_graph_ids') . ')';
		$sql_where2 .= ' AND gl.id IN(' . grv('local_graph_ids') . ')';
	}

	if (grv('source') >= 0) {
		if (grv('source') == 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' gl.graph_template_id = 0';
		} elseif (grv('source') == 1) {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' (gl.graph_template_id > 0 AND gl.snmp_query_id = 0)';
		} else {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' gl.snmp_query_id > 0';
		}
	}

	if (grv('orphans') == 'true') {
		$orphan_join = "INNER JOIN (
			SELECT DISTINCT gti.local_graph_id, dtr.local_data_id
			FROM graph_templates_item AS gti
			INNER JOIN graph_local AS gl
			ON gl.id = gti.local_graph_id
			LEFT JOIN data_template_rrd AS dtr
			ON dtr.id = gti.task_item_id
			INNER JOIN data_local AS dl
			ON dl.id = dtr.local_data_id
			LEFT JOIN host AS h
			ON h.id = gl.host_id
			WHERE graph_type_id IN (4,5,6,7,8,20)
			AND cdef_id NOT IN (
				SELECT c.id
				FROM cdef AS c
				INNER JOIN cdef_items AS ci
				ON c.id = ci.cdef_id
				WHERE (ci.type = 4 OR (ci.type = 6 AND value LIKE '%DATA_SOURCE%'))
			)
			AND (dl.orphan = 1 OR dtr.id IS NULL OR (gl.snmp_query_id > 0 AND gl.snmp_index = ''))
			$sql_where2
		) AS dtr
		ON gl.id = dtr.local_graph_id";
	} else {
		$orphan_join = '';
		$sql_params2 = [];
	}

	// don't allow aggregates to be view here
	$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' ag.local_graph_id IS NULL';

	// allow plugins to modify sql_where
	$sql_where = api_plugin_hook_function('graphs_sql_where', $sql_where);

	$rows_sql = "SELECT
		COUNT(DISTINCT gtg.id)
		FROM graph_local AS gl
		INNER JOIN graph_templates_graph AS gtg
		ON gl.id = gtg.local_graph_id
		LEFT JOIN graph_templates_item AS gti
		ON gl.graph_template_id = gti.id
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id = gt.id
		LEFT JOIN aggregate_graphs AS ag
		ON ag.local_graph_id = gl.id
		LEFT JOIN host AS h
		ON h.id=gl.host_id
		LEFT JOIN sites AS s
		ON h.site_id=s.id
		$orphan_join
		$sql_where";

	if (cacti_sizeof($sql_params2)) {
		$merged_params = array_merge($sql_params2, $sql_params);
	} else {
		$merged_params = $sql_params;
	}

	$total_rows = get_total_row_data($_SESSION[SESS_USER_ID], $rows_sql, $merged_params, 'graph');

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$sql = "SELECT gtg.id, gl.id AS local_graph_id,
		gtg.height, gtg.width, gtg.title_cache, gtg.image_format_id, gt.name, gl.host_id,
		IF(gl.graph_template_id = 0, 0, IF(gl.snmp_query_id = 0, 2, 1)) AS graph_source
		FROM graph_local AS gl
		INNER JOIN graph_templates_graph AS gtg
		ON gl.id = gtg.local_graph_id
		LEFT JOIN graph_templates_item AS gti
		ON gl.graph_template_id = gti.id
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id = gt.id
		LEFT JOIN aggregate_graphs AS ag
		ON ag.local_graph_id = gl.id
		LEFT JOIN host AS h
		ON h.id = gl.host_id
		LEFT JOIN sites AS s
		ON h.site_id = s.id
		LEFT JOIN snmp_query_graph AS sqg
		ON gl.snmp_query_id = sqg.snmp_query_id
		AND gl.graph_template_id = sqg.graph_template_id
		AND gl.snmp_query_graph_id = sqg.id
		$orphan_join
		$sql_where
		$sql_order
		$sql_limit";

	$graph_list = db_fetch_assoc_prepared($sql, $merged_params);

	$nav = html_nav_bar('graphs.php', MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 5, __('Graphs'), 'page', 'main');

	form_start('graphs.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'title_cache' => [
			'display' => __('Graph Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Title of this Graph.  Generally programmatically generated from the Graph Template definition or Suggested Naming rules.  The max length of the Title is controlled under Settings->Visual.')
		],
		'local_graph_id' => [
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal database ID for this Graph.  Useful when performing automation or debugging.')
		],
		'graph_source' => [
			'display' => __('Source Type'),
			'align'   => 'center',
			'sort'    => 'ASC',
			'tip'     => __('The underlying source that this Graph was based upon.')
		],
		'source_name' => [
			'display' => __('Source Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Graph Template or Data Query that this Graph was based upon.')
		],
		'image_format_id' => [
			'display' => __('Image Format'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The image format of the Graph.')
		],
		'height' => [
			'display' => __('Size'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The size of this Graph when not in Preview mode.')
		]
	];

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($graph_list)) {
		foreach ($graph_list as $graph) {
			$template_details = get_graph_template_details($graph['local_graph_id']);

			if ($graph['graph_source'] == '0') { // Not Templated, customize graph source and template details.
				$template_details = api_plugin_hook_function('customize_template_details', $template_details);
				$graph            = api_plugin_hook_function('customize_graph', $graph);
			}

			if (isset($template_details['graph_name'])) {
				$graph['name'] = $template_details['graph_name'];
			}

			if (isset($template_details['graph_description'])) {
				$graph['description'] = $template_details['graph_description'];
			}

			if (empty($graph['title_cache'])) {
				$graph['title_cache'] = __('Empty Graph');
			}

			form_alternate_row('line' . $graph['local_graph_id'], true);

			form_selectable_cell(filter_value(title_trim($graph['title_cache'], read_config_option('max_title_length')), grv('rfilter'), 'graphs.php?action=graph_edit&id=' . $graph['local_graph_id']), $graph['local_graph_id']);
			form_selectable_cell($graph['local_graph_id'], $graph['local_graph_id'], '', 'right');
			form_selectable_cell(filter_value($graph_sources[$graph['graph_source']], grv('rfilter')), $graph['local_graph_id'], '', 'center');
			form_selectable_cell(filter_value($template_details['name'], grv('rfilter'), $template_details['url']), $graph['local_graph_id'], '', 'left');
			form_selectable_ecell($image_types[$graph['image_format_id']], $graph['local_graph_id'], '', 'right');
			form_selectable_ecell($graph['height'] . 'x' . $graph['width'], $graph['local_graph_id'], '', 'right');

			form_checkbox_cell($graph['title_cache'], $graph['local_graph_id']);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Graphs Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($graph_list)) {
		print $nav;
	}

	// add a list of tree names to the actions dropdown
	add_tree_names_to_actions_array();

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}

function create_graphs_filter(string $session_var) : array {
	global $item_rows;

	$all     = ['-1' => __('All')];
	$any     = ['-1' => __('Any')];
	$none    = ['0'  => __('None')];

	$sites   = array_rekey(
		db_fetch_assoc('SELECT id, name
			FROM sites
			ORDER BY name'),
		'id', 'name'
	);
	$sites   = $any + $sites;

	if (gfrv('host_id') == 0) {
		$templates = get_allowed_graph_templates_normalized('gl.host_id=0', 'name', '', $total_rows);
	} elseif (gfrv('host_id') > 0) {
		$templates = get_allowed_graph_templates_normalized('gl.host_id=' . gfrv('host_id'), 'name', '', $total_rows);
	} else {
		$templates = get_allowed_graph_templates_normalized('', 'name', '', $total_rows);
	}

	$normalized_templates = [];

	if (cacti_sizeof($templates)) {
		foreach ($templates as $t) {
			$normalized_templates[$t['id']] = $t['name'];
		}
	}

	$normalized_templates = $any + $none + $normalized_templates;

	$sources = [
		'-1' => __('All'),
		'0'  => __('Non Templated'),
		'1'  => __('Graph Template'),
		'2'  => __('Data Query'),
	];

	$cdefs = array_rekey(
		db_fetch_assoc('SELECT DISTINCT c.id, c.name
			FROM cdef AS c
			INNER JOIN (SELECT DISTINCT cdef_id FROM graph_templates_item WHERE cdef_id > 0) AS gti
			ON c.id = gti.cdef_id
			ORDER BY name'),
		'id', 'name'
	);

	$cdefs = $all + $cdefs;

	$vdefs = array_rekey(
		db_fetch_assoc('SELECT v.id, v.name
			FROM vdef AS v
			INNER JOIN (SELECT DISTINCT vdef_id FROM graph_templates_item WHERE vdef_id > 0) AS gti
			ON gti.vdef_id = v.id
			ORDER BY name'),
		'id', 'name'
	);

	$vdefs = $all + $vdefs;

	$sql_where  = '';
	$sql_params = [];

	if (isrv('host_id')) {
		$host_id = grv('host_id');
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

	return [
		'rows' => [
			[
				'site_id' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Site'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $sites,
					'value'          => '-1'
				],
				'host_id' => [
					'method'         => 'drop_callback',
					'friendly_name'  => __('Device'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'sql'            => 'SELECT DISTINCT id, description AS name FROM host ORDER BY description',
					'action'         => 'ajax_hosts',
					'id'             => $host_id,
					'value'          => $hostname,
					'on_change'      => 'applyFilter()'
				],
				'template_id' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Template'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '(cg_[0-9]|dq_[0-9]|[\-0-9])']],
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $normalized_templates,
					'value'          => '-1'
				],
				'orphans' => [
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Orphaned'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '(true|false)']],
					'default'        => '',
					'pageset'        => true,
					'value'          => gnrv('orphans')
				]
			],
			[
				'source' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Source'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $sources,
					'value'         => '-1'
				],
				'cdef_id' => [
					'method'        => 'drop_array',
					'friendly_name' => __('CDEFs'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $cdefs,
					'value'         => '-1'
				],
				'vdef_id' => [
					'method'        => 'drop_array',
					'friendly_name' => __('VDEFs'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $vdefs,
					'value'         => '-1'
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
					'friendly_name' => __('Graphs'),
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
			'sort_column'    => 'title_cache',
			'sort_direction' => 'DESC'
		]
	];
}

function process_sanifize_draw_graphs_filter(bool $render = false) : void {
	$filters = create_graphs_filter('sess_graphs');

	if (read_config_option('grds_creation_method') == 1) {
		$add_url = htmle('graphs.php?action=graph_edit&host_id=' . grv('host_id'));
	} else {
		$add_url = '';
	}

	if (grv('local_graph_ids') != '') {
		$header = __('Graph Management [ Custom Graphs List Applied - Clear to Reset ]');
	} elseif (grv('host_id') == -1) {
		$header = __('Graph Management [ All Devices ]');
	} elseif (grv('host_id') == 0) {
		$header = __('Graph Management [ Non Device Based ]');
	} elseif (grv('host_id') > 0) {
		$description = db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', [grv('host_id')]);
		$header      = __esc('Graph Management [ %s ]', $description);
	} else {
		$header      = __esc('Graph Management [ All Devices ]');
	}

	// create the page filter
	$pageFilter = new CactiTableFilter($header, 'graphs.php', 'form_graphs', 'sess_graphs', $add_url);

	$pageFilter->rows_label = __('Data Sources');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}
