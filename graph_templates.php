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
include_once('./lib/api_graph.php');
include_once('./lib/api_data_source.php');
include_once('./lib/api_tree.php');
include_once('./lib/html_tree.php');
include_once('./lib/poller.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');

ini_set('max_execution_time', '-1');

$graph_actions = array(
	1 => __('Delete'),
	2 => __('Duplicate'),
	3 => __('Change Settings'),
	4 => __('Full Sync Graphs'),
	5 => __('Quick Sync Graphs')
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
	case 'ajax_data_sources':
		$data_template_id  = get_filter_request_var('data_template_id');
		$task_item_id      = get_filter_request_var('task_item_id');
		$orig_task_item_id = get_filter_request_var('_task_item_id');

		$data_sources = db_fetch_assoc_prepared("SELECT dtr.id,
			CONCAT_WS('', dt.name,' - ',' (', dtr.data_source_name,')') AS name
			FROM data_template_rrd AS dtr
			INNER JOIN data_template AS dt
			ON dtr.data_template_id = dt.id
			WHERE dtr.local_data_id = 0
			AND (dtr.data_template_id = ? OR dtr.id = ?)
			ORDER BY dt.name, dtr.data_source_name",
			array($data_template_id, $task_item_id)
		);

		$output = '';

		if (cacti_sizeof($data_sources)) {
			foreach ($data_sources as $ds) {
				if ($orig_task_item_id == $ds['id']) {
					$output .= '<option value="' . $ds['id'] . '" selected>' . html_escape($ds['name']) . '</option>';
				} elseif ($task_item_id == $ds['id']) {
					$output .= '<option value="' . $ds['id'] . '" selected>' . html_escape($ds['name']) . '</option>';
				} else {
					$output .= '<option value="' . $ds['id'] . '">' . html_escape($ds['name']) . '</option>';
				}
			}
		} else {
			$output .= '<option value="0">' . __('None') . '</option>';
		}

		print $output;

		break;
	case 'item_remove':
		get_filter_request_var('graph_template_id');

		item_remove();

		header('Location: graph_templates.php?action=template_edit&id=' . get_request_var('graph_template_id'));

		break;
	case 'item_movedown':
		get_filter_request_var('graph_template_id');

		item_movedown();

		header('Location: graph_templates.php?action=template_edit&id=' . get_request_var('graph_template_id'));

		break;
	case 'item_moveup':
		get_filter_request_var('graph_template_id');

		item_moveup();

		header('Location: graph_templates.php?action=template_edit&id=' . get_request_var('graph_template_id'));

		break;
	case 'item_edit':
		top_header();

		item_edit();

		bottom_footer();

		break;
	case 'item':
		top_header();

		item();

		bottom_footer();

		break;
	case 'input_remove':
		get_filter_request_var('graph_template_id');

		input_remove();

		header('Location: graph_templates.php?action=template_edit&id=' . get_request_var('graph_template_id'));

		break;
	case 'input_edit':
		top_header();

		input_edit();

		bottom_footer();

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

function form_save() {
	// sanitize ids
	if (isset_request_var('graph_template_id') && !is_numeric(get_nfilter_request_var('graph_template_id'))) {
		$graph_template_id = 0;
	} else {
		$graph_template_id = get_filter_request_var('graph_template_id');
	}

	if (isset_request_var('save_component_template')) {
		/* ================= input validation ================= */
		get_filter_request_var('graph_template_graph_id');
		/* ==================================================== */

		$push_title = true;

		if ($graph_template_id > 0) {
			$prev_title = db_fetch_cell_prepared('SELECT title
				FROM graph_templates_graph
				WHERE graph_template_id = ?
				AND local_graph_id = 0',
				array($graph_template_id));

			if ($prev_title == get_nfilter_request_var('title')) {
				$push_title = false;
			}
		}

		$save1['id']          = $graph_template_id;
		$save1['hash']        = get_hash_graph_template($graph_template_id);
		$save1['name']        = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save1['multiple']    = isset_request_var('multiple') ? 'on':'';
		$save1['test_source'] = isset_request_var('test_source') ? 'on':'';

		$save2['id']                            = get_nfilter_request_var('graph_template_graph_id');
		$save2['local_graph_template_graph_id'] = 0;
		$save2['local_graph_id']                = 0;
		$save2['t_image_format_id']             = (isset_request_var('t_image_format_id') ? get_nfilter_request_var('t_image_format_id') : '');
		$save2['image_format_id']               = form_input_validate(get_nfilter_request_var('image_format_id'), 'image_format_id', '^[0-9]+$', true, 3);
		$save2['t_title']                       = form_input_validate((isset_request_var('t_title') ? get_nfilter_request_var('t_title') : ''), 't_title', '', true, 3);
		$save2['title']                         = form_input_validate(get_nfilter_request_var('title'), 'title', '', (isset_request_var('t_title') ? true : false), 3);
		$save2['t_height']                      = form_input_validate((isset_request_var('t_height') ? get_nfilter_request_var('t_height') : ''), 't_height', '', true, 3);
		$save2['height']                        = form_input_validate(get_nfilter_request_var('height'), 'height', '^[0-9]+$', (isset_request_var('t_height') ? true : false), 3);
		$save2['t_width']                       = form_input_validate((isset_request_var('t_width') ? get_nfilter_request_var('t_width') : ''), 't_width', '', true, 3);
		$save2['width']                         = form_input_validate(get_nfilter_request_var('width'), 'width', '^[0-9]+$', (isset_request_var('t_width') ? true : false), 3);
		$save2['t_upper_limit']                 = form_input_validate((isset_request_var('t_upper_limit') ? get_nfilter_request_var('t_upper_limit') : ''), 't_upper_limit', '', true, 3);
		$save2['upper_limit']                   = form_input_validate(get_nfilter_request_var('upper_limit'), 'upper_limit', '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$', ((isset_request_var('t_upper_limit') || (strlen(get_nfilter_request_var('upper_limit')) === 0)) ? true : false), 3);
		$save2['t_lower_limit']                 = form_input_validate((isset_request_var('t_lower_limit') ? get_nfilter_request_var('t_lower_limit') : ''), 't_lower_limit', '', true, 3);
		$save2['lower_limit']                   = form_input_validate(get_nfilter_request_var('lower_limit'), 'lower_limit', '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$', ((isset_request_var('t_lower_limit') || (strlen(get_nfilter_request_var('lower_limit')) === 0)) ? true : false), 3);
		$save2['t_vertical_label']              = form_input_validate((isset_request_var('t_vertical_label') ? get_nfilter_request_var('t_vertical_label') : ''), 't_vertical_label', '', true, 3);
		$save2['vertical_label']                = form_input_validate(get_nfilter_request_var('vertical_label'), 'vertical_label', '', true, 3);
		$save2['t_slope_mode']                  = form_input_validate((isset_request_var('t_slope_mode') ? get_nfilter_request_var('t_slope_mode') : ''), 't_slope_mode', '', true, 3);
		$save2['slope_mode']                    = form_input_validate((isset_request_var('slope_mode') ? get_nfilter_request_var('slope_mode') : ''), 'slope_mode', '', true, 3);
		$save2['t_auto_scale']                  = form_input_validate((isset_request_var('t_auto_scale') ? get_nfilter_request_var('t_auto_scale') : ''), 't_auto_scale', '', true, 3);
		$save2['auto_scale']                    = form_input_validate((isset_request_var('auto_scale') ? get_nfilter_request_var('auto_scale') : ''), 'auto_scale', '', true, 3);
		$save2['t_auto_scale_opts']             = form_input_validate((isset_request_var('t_auto_scale_opts') ? get_nfilter_request_var('t_auto_scale_opts') : ''), 't_auto_scale_opts', '', true, 3);
		$save2['auto_scale_opts']               = form_input_validate(get_nfilter_request_var('auto_scale_opts'), 'auto_scale_opts', '', true, 3);
		$save2['t_auto_scale_log']              = form_input_validate((isset_request_var('t_auto_scale_log') ? get_nfilter_request_var('t_auto_scale_log') : ''), 't_auto_scale_log', '', true, 3);
		$save2['auto_scale_log']                = form_input_validate((isset_request_var('auto_scale_log') ? get_nfilter_request_var('auto_scale_log') : ''), 'auto_scale_log', '', true, 3);
		$save2['t_scale_log_units']             = form_input_validate((isset_request_var('t_scale_log_units') ? get_nfilter_request_var('t_scale_log_units') : ''), 't_scale_log_units', '', true, 3);
		$save2['scale_log_units']               = form_input_validate((isset_request_var('scale_log_units') ? get_nfilter_request_var('scale_log_units') : ''), 'scale_log_units', '', true, 3);
		$save2['t_auto_scale_rigid']            = form_input_validate((isset_request_var('t_auto_scale_rigid') ? get_nfilter_request_var('t_auto_scale_rigid') : ''), 't_auto_scale_rigid', '', true, 3);
		$save2['auto_scale_rigid']              = form_input_validate((isset_request_var('auto_scale_rigid') ? get_nfilter_request_var('auto_scale_rigid') : ''), 'auto_scale_rigid', '', true, 3);
		$save2['t_auto_padding']                = form_input_validate((isset_request_var('t_auto_padding') ? get_nfilter_request_var('t_auto_padding') : ''), 't_auto_padding', '', true, 3);
		$save2['auto_padding']                  = form_input_validate((isset_request_var('auto_padding') ? get_nfilter_request_var('auto_padding') : ''), 'auto_padding', '', true, 3);
		$save2['t_base_value']                  = form_input_validate((isset_request_var('t_base_value') ? get_nfilter_request_var('t_base_value') : ''), 't_base_value', '', true, 3);
		$save2['base_value']                    = form_input_validate(get_nfilter_request_var('base_value'), 'base_value', '^[0-9]+$', (isset_request_var('t_base_value') ? true : false), 3);
		$save2['t_unit_value']                  = form_input_validate((isset_request_var('t_unit_value') ? get_nfilter_request_var('t_unit_value') : ''), 't_unit_value', '', true, 3);
		$save2['unit_value']                    = form_input_validate(get_nfilter_request_var('unit_value'), 'unit_value', '', true, 3);
		$save2['t_unit_exponent_value']         = form_input_validate((isset_request_var('t_unit_exponent_value') ? get_nfilter_request_var('t_unit_exponent_value') : ''), 't_unit_exponent_value', '', true, 3);
		$save2['unit_exponent_value']           = form_input_validate(get_nfilter_request_var('unit_exponent_value'), 'unit_exponent_value', '^-?[0-9]+$', true, 3);
		$save2['t_alt_y_grid']                  = form_input_validate((isset_request_var('t_alt_y_grid') ? get_nfilter_request_var('t_alt_y_grid') : ''), 't_alt_y_grid', '', true, 3);
		$save2['alt_y_grid']                    = form_input_validate((isset_request_var('alt_y_grid') ? get_nfilter_request_var('alt_y_grid') : ''), 'alt_y_grid', '', true, 3);
		$save2['t_right_axis']                  = form_input_validate((isset_request_var('t_right_axis') ? get_nfilter_request_var('t_right_axis') : ''), 't_right_axis', '', true, 3);
		$save2['right_axis']                    = form_input_validate((isset_request_var('right_axis') ? get_nfilter_request_var('right_axis') : ''), 'right_axis', '^-?([0-9]+(\.[0-9]*)?|\.[0-9]+):-?([0-9]+(\.[0-9]*)?|\.[0-9]+)$', true, 3);
		$save2['t_right_axis_label']            = form_input_validate((isset_request_var('t_right_axis_label') ? get_nfilter_request_var('t_right_axis_label') : ''), 't_right_axis_label', '', true, 3);
		$save2['right_axis_label']              = form_input_validate((isset_request_var('right_axis_label') ? get_nfilter_request_var('right_axis_label') : ''), 'right_axis_label', '', true, 3);
		$save2['t_right_axis_format']           = form_input_validate((isset_request_var('t_right_axis_format') ? get_nfilter_request_var('t_right_axis_format') : ''), 't_right_axis_format', '', true, 3);
		$save2['right_axis_format']             = form_input_validate((isset_request_var('right_axis_format') ? get_nfilter_request_var('right_axis_format') : ''), 'right_axis_format', '^[0-9]+$', true, 3);
		$save2['t_no_gridfit']                  = form_input_validate((isset_request_var('t_no_gridfit') ? get_nfilter_request_var('t_no_gridfit') : ''), 't_no_gridfit', '', true, 3);
		$save2['no_gridfit']                    = form_input_validate((isset_request_var('no_gridfit') ? get_nfilter_request_var('no_gridfit') : ''), 'no_gridfit', '', true, 3);
		$save2['t_unit_length']                 = form_input_validate((isset_request_var('t_unit_length') ? get_nfilter_request_var('t_unit_length') : ''), 't_unit_length', '', true, 3);
		$save2['unit_length']                   = form_input_validate((isset_request_var('unit_length') ? get_nfilter_request_var('unit_length') : ''), 'unit_length', '^[0-9]+$', true, 3);
		$save2['t_tab_width']                   = form_input_validate((isset_request_var('t_tab_width') ? get_nfilter_request_var('t_tab_width') : ''), 't_tab_width', '', true, 3);
		$save2['tab_width']                     = form_input_validate((isset_request_var('tab_width') ? get_nfilter_request_var('tab_width') : ''), 'tab_width', '^[0-9]*$', true, 3);
		$save2['t_dynamic_labels']              = form_input_validate((isset_request_var('t_dynamic_labels') ? get_nfilter_request_var('t_dynamic_labels') : ''), 't_dynamic_labels', '', true, 3);
		$save2['dynamic_labels']                = form_input_validate((isset_request_var('dynamic_labels') ? get_nfilter_request_var('dynamic_labels') : ''), 'dynamic_labels', '', true, 3);
		$save2['t_force_rules_legend']          = form_input_validate((isset_request_var('t_force_rules_legend') ? get_nfilter_request_var('t_force_rules_legend') : ''), 't_force_rules_legend', '', true, 3);
		$save2['force_rules_legend']            = form_input_validate((isset_request_var('force_rules_legend') ? get_nfilter_request_var('force_rules_legend') : ''), 'force_rules_legend', '', true, 3);
		$save2['t_legend_position']             = form_input_validate((isset_request_var('t_legend_position') ? get_nfilter_request_var('t_legend_position') : ''), 't_legend_position', '', true, 3);
		$save2['legend_position']               = form_input_validate((isset_request_var('legend_position') ? get_nfilter_request_var('legend_position') : ''), 'legend_position', '', true, 3);
		$save2['t_legend_direction']            = form_input_validate((isset_request_var('t_legend_direction') ? get_nfilter_request_var('t_legend_direction') : ''), 't_legend_direction', '', true, 3);
		$save2['legend_direction']              = form_input_validate((isset_request_var('legend_direction') ? get_nfilter_request_var('legend_direction') : ''), 'legend_direction', '', true, 3);
		$save2['t_right_axis_formatter']        = form_input_validate((isset_request_var('t_right_axis_formatter') ? get_nfilter_request_var('t_right_axis_formatter') : ''), 't_right_axis_formatter', '', true, 3);
		$save2['right_axis_formatter']          = form_input_validate((isset_request_var('right_axis_formatter') ? get_nfilter_request_var('right_axis_formatter') : ''), 'right_axis_formatter', '', true, 3);
		$save2['t_left_axis_format']            = form_input_validate((isset_request_var('t_left_axis_format') ? get_nfilter_request_var('t_left_axis_format') : ''), 't_left_axis_format', '', true, 3);
		$save2['left_axis_format']              = form_input_validate((isset_request_var('left_axis_format') ? get_nfilter_request_var('left_axis_format') : ''), 'left_axis_format', '^[0-9]+$', true, 3);
		$save2['t_left_axis_formatter']         = form_input_validate((isset_request_var('t_left_axis_formatter') ? get_nfilter_request_var('t_left_axis_formatter') : ''), 't_left_axis_formatter', '', true, 3);
		$save2['left_axis_formatter']           = form_input_validate((isset_request_var('left_axis_formatter') ? get_nfilter_request_var('left_axis_formatter') : ''), 'left_axis_formatter', '', true, 3);

		if (!is_error_message()) {
			$graph_template_id = sql_save($save1, 'graph_templates');

			if ($graph_template_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (!is_error_message()) {
			$save2['graph_template_id'] = $graph_template_id;
			$graph_template_graph_id    = sql_save($save2, 'graph_templates_graph');

			if ($graph_template_graph_id) {
				raise_message(1);

				push_out_graph($graph_template_graph_id, $push_title);
			} else {
				raise_message(2);
			}
		}
	} elseif (isset_request_var('save_component_item')) {
		/* ================= input validation ================= */
		get_filter_request_var('graph_template_id');
		get_filter_request_var('task_item_id');
		get_filter_request_var('sequence');
		get_filter_request_var('color_id');
		get_filter_request_var('graph_template_item_id');
		/* ==================================================== */

		global $graph_item_types;

		$items[0] = array();

		if ($graph_item_types[get_nfilter_request_var('graph_type_id')] == 'LEGEND') {
			/* this can be a major time saver when creating lots of graphs with the typical
			GPRINT LAST/AVERAGE/MAX legends */
			$items = array(
				0 => array(
					'color_id'                  => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '4',
					'text_format'               => __('Cur:'),
					'hard_return'               => ''
				),
				1 => array(
					'color_id'                  => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '1',
					'text_format'               => __('Avg:'),
					'hard_return'               => ''
				),
				2 => array(
					'color_id'                  => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '3',
					'text_format'               => __('Max:'),
					'hard_return'               => 'on'
				)
			);
		} elseif ($graph_item_types[get_nfilter_request_var('graph_type_id')] == 'LEGEND_CAMM') {
			/* this can be a major time saver when creating lots of graphs with the typical
				GPRINT LAST/AVERAGE/MAX legends */
			$items = array(
				0 => array(
					'color_id'                  => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '4',
					'text_format'               => __('Cur:'),
					'hard_return'               => ''
				),
				1 => array(
					'color_id'                  => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '1',
					'text_format'               => __('Avg:'),
					'hard_return'               => ''
				),
				2 => array(
					'color_id'                  => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '2',
					'text_format'               => __('Min:'),
					'hard_return'               => ''
				),
				3 => array(
					'color_id'                  => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '3',
					'text_format'               => __('Max:'),
					'hard_return'               => 'on'
				)
			);
		}

		$sequence = get_request_var('sequence');

		foreach ($items as $item) {
			/* generate a new sequence if needed */
			if (empty($sequence)) {
				$sequence = get_sequence(0, 'sequence', 'graph_templates_item', 'graph_template_id=' . get_request_var('graph_template_id') . ' AND local_graph_id=0');
			}

			$task_item_changed = true;

			if (get_request_var('graph_template_item_id') > 0) {
				$task_item_id = db_fetch_cell_prepared(
					'SELECT task_item_id
					FROM graph_templates_item
					WHERE id = ?',
					array(get_request_var('graph_template_item_id'))
				);

				if (get_nfilter_request_var('task_item_id') == get_nfilter_request_var('_task_item_id')) {
					$task_item_changed = false;
				}
			}

			$save['id']                = get_request_var('graph_template_item_id');
			$save['hash']              = get_hash_graph_template(get_request_var('graph_template_item_id'), 'graph_template_item');
			$save['graph_template_id'] = get_request_var('graph_template_id');
			$save['local_graph_id']    = 0;
			$save['task_item_id']      = form_input_validate(get_request_var('task_item_id'), 'task_item_id', '^[0-9]+$', true, 3);
			$save['color_id']          = form_input_validate((isset($item['color_id']) ? $item['color_id'] : get_request_var('color_id')), 'color_id', '', true, 3);

			/* if alpha is disabled, use invisible_alpha instead */
			if (!isset_request_var('alpha')) {
				set_request_var('alpha', get_nfilter_request_var('invisible_alpha'));
			}

			$save['alpha']             = form_input_validate((isset($item['alpha']) ? $item['alpha'] : get_nfilter_request_var('alpha')), 'alpha', '', true, 3);
			$save['graph_type_id']     = form_input_validate((isset($item['graph_type_id']) ? $item['graph_type_id'] : get_filter_request_var('graph_type_id')), 'graph_type_id', '^[0-9]+$', true, 3);

			if (isset_request_var('line_width') || isset($item['line_width'])) {
				$save['line_width']    = form_input_validate((isset($item['line_width']) ? $item['line_width'] : get_nfilter_request_var('line_width')), 'line_width', '(^[0-9]+[\.,0-9]+$|^[0-9]+$)', true, 3);
			} else {
				// make sure to transfer old LINEx style into line_width on save
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

			$save['dashes']                    = form_input_validate((isset_request_var('dashes') ? get_nfilter_request_var('dashes') : ''), 'dashes', '^[0-9]+[,0-9]*$', true, 3);
			$save['dash_offset']               = form_input_validate((isset_request_var('dash_offset') ? get_nfilter_request_var('dash_offset') : ''), 'dash_offset', '^[0-9]+$', true, 3);
			$save['cdef_id']                   = form_input_validate(get_nfilter_request_var('cdef_id'), 'cdef_id', '^[0-9]+$', true, 3);
			$save['vdef_id']                   = form_input_validate(get_nfilter_request_var('vdef_id'), 'vdef_id', '^[0-9]+$', true, 3);
			$save['shift']                     = form_input_validate((isset_request_var('shift') ? get_nfilter_request_var('shift') : ''), 'shift', '^((on)|)$', true, 3);
			$save['consolidation_function_id'] = form_input_validate((isset($item['consolidation_function_id']) ? $item['consolidation_function_id'] : get_nfilter_request_var('consolidation_function_id')), 'consolidation_function_id', '^[0-9]+$', true, 3);
			$save['textalign']                 = form_input_validate((isset_request_var('textalign') ? get_nfilter_request_var('textalign') : ''), 'textalign', '^[a-z]+$', true, 3);
			$save['text_format']               = form_input_validate((isset($item['text_format']) ? $item['text_format'] : get_nfilter_request_var('text_format')), 'text_format', '', true, 3);
			$save['value']                     = form_input_validate(get_nfilter_request_var('value'), 'value', '', true, 3);
			$save['hard_return']               = form_input_validate(((isset($item['hard_return']) ? $item['hard_return'] : (isset_request_var('hard_return') ? get_nfilter_request_var('hard_return') : ''))), 'hard_return', '', true, 3);
			$save['gprint_id']                 = form_input_validate(get_nfilter_request_var('gprint_id'), 'gprint_id', '^[0-9]+$', true, 3);
			$save['sequence']                  = $sequence;

			if (!is_error_message()) {
				/* Before we save the item, let's get a look at task_item_id <-> input associations */
				$orig_data_source_graph_inputs = db_fetch_assoc_prepared("SELECT
					gtin.id, gtin.name, gti.task_item_id
					FROM graph_template_input AS gtin
					INNER JOIN graph_template_input_defs AS gtid
					ON gtin.id = gtid.graph_template_input_id
					INNER JOIN graph_templates_item AS gti
					ON gtid.graph_template_item_id = gti.id
					WHERE gtin.graph_template_id = ?
					AND gtin.column_name = 'task_item_id'
					GROUP BY gti.task_item_id", array($save['graph_template_id']));

				$orig_data_source_to_input = array_rekey($orig_data_source_graph_inputs, 'task_item_id', 'id');

				$graph_template_item_id = sql_save($save, 'graph_templates_item');

				if ($graph_template_item_id) {
					raise_message(1);

					if (!empty($save['task_item_id'])) {
						/* old item clean-up.  Don't delete anything if the item <-> task_item_id association remains the same. */
						if (get_nfilter_request_var('_task_item_id') != get_nfilter_request_var('task_item_id')) {
							/* It changed.  Delete any old associations */
							db_execute_prepared(
								'DELETE FROM graph_template_input_defs
								WHERE graph_template_item_id = ?',
								array($graph_template_item_id)
							);

							/* Input for current data source exists and has changed.  Update the association */
							if (isset($orig_data_source_to_input[$save['task_item_id']])) {
								db_execute_prepared(
									'REPLACE INTO graph_template_input_defs
									(graph_template_input_id, graph_template_item_id)
									VALUES (?, ?)',
									array($orig_data_source_to_input[$save['task_item_id']], $graph_template_item_id)
								);
							}
						}

						/* an input for the current data source does NOT currently exist, let's create one */
						if (!isset($orig_data_source_to_input[$save['task_item_id']])) {
							$ds_name = db_fetch_cell_prepared(
								'SELECT data_source_name
								FROM data_template_rrd
								WHERE id = ?',
								array(get_nfilter_request_var('task_item_id'))
							);

							db_execute_prepared(
								"REPLACE INTO graph_template_input
								(hash, graph_template_id, name, column_name)
								VALUES (?, ?, ?, 'task_item_id')",
								array(get_hash_graph_template(0, 'graph_template_input'), $save['graph_template_id'], "Data Source [$ds_name]")
							);

							$graph_template_input_id = db_fetch_insert_id();

							$graph_items = db_fetch_assoc_prepared(
								'SELECT id
								FROM graph_templates_item
								WHERE graph_template_id = ?
								AND task_item_id = ?',
								array($save['graph_template_id'], get_nfilter_request_var('task_item_id'))
							);

							if (cacti_sizeof($graph_items)) {
								foreach ($graph_items as $graph_item) {
									db_execute_prepared(
										'REPLACE INTO graph_template_input_defs
										(graph_template_input_id, graph_template_item_id)
										VALUES (?, ?)',
										array($graph_template_input_id, $graph_item['id'])
									);
								}
							}
						}
					}

					push_out_graph_item($graph_template_item_id, $task_item_changed);

					if (isset($orig_data_source_to_input[get_nfilter_request_var('task_item_id')])) {
						/* make sure all current graphs using this graph input are aware of this change */
						push_out_graph_input($orig_data_source_to_input[get_nfilter_request_var('task_item_id')], $graph_template_item_id, array($graph_template_item_id => $graph_template_item_id));
					}
				} else {
					raise_message(2);
				}
			}

			$sequence = 0;
		}

		if (is_error_message()) {
			header('Location: graph_templates.php?action=item_edit&graph_template_item_id=' . (empty($graph_template_item_id) ? get_nfilter_request_var('graph_template_item_id') : $graph_template_item_id) . '&id=' . get_nfilter_request_var('graph_template_id'));

			exit;
		} else {
			header('Location: graph_templates.php?action=template_edit&id=' . get_nfilter_request_var('graph_template_id'));

			exit;
		}
	} elseif ((isset_request_var('save_component_input')) && (!is_error_message())) {
		$graph_input_values   = array();
		$selected_graph_items = array();

		/* ================= input validation ================= */
		get_filter_request_var('graph_template_input_id');
		get_filter_request_var('graph_template_id');
		/* ==================================================== */

		$save['id']                = get_nfilter_request_var('graph_template_input_id');
		$save['hash']              = get_hash_graph_template(get_nfilter_request_var('graph_template_input_id'), 'graph_template_input');
		$save['graph_template_id'] = get_nfilter_request_var('graph_template_id');
		$save['name']              = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['description']       = form_input_validate(get_nfilter_request_var('description'), 'description', '', true, 3);
		$save['column_name']       = form_input_validate(get_nfilter_request_var('column_name'), 'column_name', '', true, 3);

		if (!is_error_message()) {
			$graph_template_input_id = sql_save($save, 'graph_template_input');

			if ($graph_template_input_id) {
				raise_message(1);

				/* list all graph items from the db so we can compare them with the current form */
				$db_selected_graph_item = array_rekey(db_fetch_assoc_prepared('SELECT graph_template_item_id FROM graph_template_input_defs WHERE graph_template_input_id = ?', array($graph_template_input_id)), 'graph_template_item_id', 'graph_template_item_id');

				/* list all select graph items for use down below */
				foreach ($_POST as $var => $val) {
					if (preg_match('/^i_(\d+)$/', $var, $matches)) {
						/* ================= input validation ================= */
						input_validate_input_number($matches[1], 'i[1]');
						/* ==================================================== */

						$selected_graph_items[$matches[1]] = $matches[1];

						if (isset($db_selected_graph_item[$matches[1]])) {
							/* is selected and exists in the db; old item */
							$old_members[$matches[1]] = $matches[1];
						} else {
							/* is selected and does not exist the db; new item */
							$new_members[$matches[1]] = $matches[1];
						}
					}
				}

				if ((isset($new_members)) && (cacti_sizeof($new_members) > 0)) {
					foreach ($new_members as $item_id) {
						push_out_graph_input($graph_template_input_id, $item_id, (isset($new_members) ? $new_members : array()));
					}
				}

				db_execute_prepared('DELETE FROM graph_template_input_defs WHERE graph_template_input_id = ?', array($graph_template_input_id));

				if (cacti_sizeof($selected_graph_items) > 0) {
					foreach ($selected_graph_items as $graph_template_item_id) {
						db_execute_prepared('INSERT INTO graph_template_input_defs (graph_template_input_id, graph_template_item_id) VALUES (?, ?)', array($graph_template_input_id, $graph_template_item_id));
					}
				}
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: graph_templates.php?action=input_edit&graph_template_input_id=' . (empty($graph_template_input_id) ? get_nfilter_request_var('graph_template_input_id') : $graph_template_input_id) . '&graph_template_id=' . get_nfilter_request_var('graph_template_id'));

			exit;
		} else {
			header('Location: graph_templates.php?action=template_edit&id=' . get_nfilter_request_var('graph_template_id'));

			exit;
		}
	}

	header('Location: graph_templates.php?action=template_edit&id=' . (empty($graph_template_id) ? get_nfilter_request_var('graph_template_id') : $graph_template_id));
}

function item_movedown() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('graph_template_id');
	/* ==================================================== */

	global $graph_item_types;

	$arr        = get_graph_group(get_request_var('id'));
	$next_id    = get_graph_parent(get_request_var('id'), 'next');

	$graph_type = db_fetch_cell_prepared('SELECT graph_type_id
		FROM graph_templates_item
		WHERE id = ?',
		array(get_request_var('id'))
	);

	$text_type  = $graph_item_types[$graph_type];

	if (!empty($next_id) && isset($arr[get_request_var('id')])) {
		move_graph_group(get_request_var('id'), $arr, $next_id, 'next');
	} elseif (!preg_match('/(AREA|STACK|LINE)/', $text_type)) {
		/* this is so we know the "other" graph item to propagate the changes to */
		$next_item = get_item('graph_templates_item', 'sequence', get_request_var('id'), 'graph_template_id=' . get_request_var('graph_template_id') . ' AND local_graph_id=0', 'next');

		move_item_down('graph_templates_item', get_request_var('id'), 'graph_template_id=' . get_request_var('graph_template_id') . ' AND local_graph_id=0');
	}

	if (!isempty_request_var('graph_template_id')) {
		resequence_graphs_simple(get_request_var('graph_template_id'));
	}
}

function item_moveup() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('graph_template_id');
	/* ==================================================== */

	global $graph_item_types;

	$arr     = get_graph_group(get_request_var('id'));
	$next_id = get_graph_parent(get_request_var('id'), 'previous');

	$graph_type = db_fetch_cell_prepared('SELECT graph_type_id
		FROM graph_templates_item
		WHERE id = ?',
		array(get_request_var('id'))
	);

	$text_type  = $graph_item_types[$graph_type];

	if (!empty($next_id) && isset($arr[get_request_var('id')])) {
		move_graph_group(get_request_var('id'), $arr, $next_id, 'previous');
	} elseif (!preg_match('/(AREA|STACK|LINE)/', $text_type)) {
		/* this is so we know the "other" graph item to propagate the changes to */
		$last_item = get_item('graph_templates_item', 'sequence', get_request_var('id'), 'graph_template_id=' . get_request_var('graph_template_id') . ' AND local_graph_id=0', 'previous');

		move_item_up('graph_templates_item', get_request_var('id'), 'graph_template_id=' . get_request_var('graph_template_id') . ' AND local_graph_id=0');
	}

	if (!isempty_request_var('graph_template_id')) {
		resequence_graphs_simple(get_request_var('graph_template_id'));
	}
}

function item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('graph_template_id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM graph_templates_item WHERE id = ?', array(get_request_var('id')));
	db_execute_prepared('DELETE FROM graph_templates_item WHERE local_graph_template_item_id = ?', array(get_request_var('id')));

	/* delete the graph item input if it is empty */
	$graph_item_inputs = db_fetch_assoc_prepared('SELECT
		graph_template_input.id
		FROM (graph_template_input, graph_template_input_defs)
		WHERE graph_template_input.id = graph_template_input_defs.graph_template_input_id
		AND graph_template_input.graph_template_id = ?
		AND graph_template_input_defs.graph_template_item_id = ?
		GROUP BY graph_template_input.id', array(get_request_var('graph_template_id'), get_request_var('id')));

	if (cacti_sizeof($graph_item_inputs) > 0) {
		foreach ($graph_item_inputs as $graph_item_input) {
			if (cacti_sizeof(db_fetch_assoc_prepared('SELECT graph_template_input_id FROM graph_template_input_defs WHERE graph_template_input_id = ?', array($graph_item_input['id']))) == 1) {
				db_execute_prepared('DELETE FROM graph_template_input WHERE id = ?', array($graph_item_input['id']));
			}
		}
	}

	db_execute_prepared('DELETE FROM graph_template_input_defs WHERE graph_template_item_id = ?', array(get_request_var('id')));
}

function item_edit() {
	global $struct_graph_item, $graph_item_types, $consolidation_functions;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('graph_template_id');
	get_filter_request_var('data_template_id');
	/* ==================================================== */

	/* ================= input validation and session storage ================= */
	$filters = array(
		'data_template_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '0'
		),
	);

	validate_store_request_vars($filters, 'sess_gti_' . get_filter_request_var('graph_template_id'));
	/* ================= input validation ================= */

	if (get_request_var('graph_template_id') > 0 || isset_request_var('id')) {
		$sql_where  = '';
		$sql_params = array();

		if (get_request_var('id') > 0) {
			$sql_where .= ' AND gti.id = ?';
			$sql_params[] = get_request_var('id');
		}

		if (get_request_var('graph_template_id') > 0) {
			$sql_where .= ' AND gti.graph_template_id = ?';
			$sql_params[] = get_request_var('graph_template_id');
		}

		$data_templates = array_rekey(
			db_fetch_assoc_prepared("SELECT DISTINCT dtr.data_template_id
				FROM data_template_rrd AS dtr
				INNER JOIN graph_templates_item AS gti
				ON dtr.id = gti.task_item_id
				WHERE dtr.local_data_id = 0
				$sql_where
				ORDER BY dtr.data_template_id",
				$sql_params
			),
			'data_template_id',
			'data_template_id'
		);

		if (cacti_sizeof($data_templates)) {
			if (!isset($data_templates[get_request_var('data_template_id')])) {
				foreach ($data_templates as $dt) {
					set_request_var('data_template_id', $dt);

					break;
				}
			}
		}
	}

	form_start('graph_templates.php', 'graph_items');

	$header_label = __esc('Graph Template Items [edit graph: %s]', db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE id = ?', array(get_request_var('graph_template_id'))));

	html_start_box($header_label, '100%', true, '3', 'center', '');

	if (!isempty_request_var('id')) {
		$template_item = db_fetch_row_prepared('SELECT *
			FROM graph_templates_item
			WHERE id = ?',
			array(get_request_var('id'))
		);
	}

	/* by default, select the LAST DS chosen to make everyone's lives easier */
	if (!isempty_request_var('graph_template_id')) {
		$default = db_fetch_row_prepared('SELECT task_item_id
			FROM graph_templates_item
			WHERE graph_template_id = ?
			AND local_graph_id = 0
			ORDER BY sequence DESC',
			array(get_request_var('graph_template_id'))
		);

		if (cacti_sizeof($default) > 0) {
			$struct_graph_item['task_item_id']['default'] = $default['task_item_id'];
		} else {
			$struct_graph_item['task_item_id']['default'] = 0;
		}
	}

	if (isset_request_var('data_template_id')) {
		$sql_where = ' AND dtr.data_template_id = ' . get_filter_request_var('data_template_id');
	} else {
		$sql_where = '';
	}

	$data_template_helper = array(
		'data_template_id' => array(
			'friendly_name' => __('Data Template Filter'),
			'method'        => 'drop_sql',
			'sql'           => 'SELECT id, name FROM data_template ORDER BY name',
			'default'       => '0',
			'value'         => (isset_request_var('data_template_id') ? get_filter_request_var('data_template_id') : '0'),
			'none_value'    => __('Any'),
			'description'   => __('This filter will limit the Data Sources visible in the Data Source dropdown.')
		)
	);

	/* modifications to the default graph items array */
	$struct_graph_item['task_item_id']['sql'] = "SELECT dtr.id,
		CONCAT_WS('', dt.name,' - ',' (', dtr.data_source_name,')') AS name
		FROM data_template_rrd AS dtr
		INNER JOIN data_template AS dt
		ON dtr.data_template_id = dt.id
		WHERE dtr.local_data_id = 0
		$sql_where
		ORDER BY dt.name, dtr.data_source_name";

	$mystruct_graph_item = array_merge($data_template_helper, $struct_graph_item);

	$form_array = array();

	foreach ($mystruct_graph_item as $field_name => $field_array) {
		$form_array += array($field_name => $mystruct_graph_item[$field_name]);

		if ($field_name != 'data_template_id') {
			$form_array[$field_name]['value']   = (isset($template_item) ? $template_item[$field_name] : '');
			$form_array[$field_name]['form_id'] = (isset($template_item) ? $template_item['id'] : '0');
		} else {
			$form_array[$field_name]['value']   = get_request_var('data_template_id');
			$form_array[$field_name]['form_id'] = (isset($template_item) ? $template_item['id'] : '0');
		}
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $form_array
		)
	);

	html_end_box(true, true);

	form_hidden_box('graph_template_item_id', (isset($template_item) ? $template_item['id'] : '0'), '');
	form_hidden_box('graph_template_id', get_request_var('graph_template_id'), '0');
	form_hidden_box('_graph_type_id', (isset($template_item) ? $template_item['graph_type_id'] : '0'), '');
	form_hidden_box('_task_item_id', (isset($template_item) ? $template_item['task_item_id'] : '0'), '');
	form_hidden_box('save_component_item', '1', '');
	form_hidden_box('invisible_alpha', $form_array['alpha']['value'], 'FF');
	form_hidden_box('rrdtool_version', get_rrdtool_version(), '');

	form_save_button('graph_templates.php?action=template_edit&id=' . get_request_var('graph_template_id'));

	?>
	<script type='text/javascript'>
		$(function() {
			$('#shift').click(function(data) {
				toggleFields({
					value: $('#shift').is(':checked'),
				})
			});

			$('#data_template_id').change(function() {
				$.get(urlPath + 'graph_templates.php' +
					'?action=ajax_data_sources' +
					'&data_template_id=' + $('#data_template_id').val() +
					'&task_item_id=' + $('#task_item_id').val() +
					'&_task_item_id=' + $('#_task_item_id').val(),
					function(data) {

						$('#task_item_id').empty().append(data);

						if ($('#task_item_id').selectmenu('instance')) {
							$('#task_item_id').selectmenu('refresh');
						}
					});
			});

			setRowVisibility();
			$('#graph_type_id').change(function(data) {
				setRowVisibility();
			});
		});

		/**
		 * columns - task_item_id color_id alpha graph_type_id consolidation_function_id cdef_id value gprint_id text_format hard_return
		 *
		 * graph_type_ids - 1 - Comment 2 - HRule 3 - Vrule 4 - Line1 5 - Line2 6 - Line3 7 - Area 8 - Stack 9 - Gprint 10 - Legend
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

		function setRowVisibility() {
			var graphType = $('#graph_type_id').val();
			toggleFields({
				data_template_id: graphType != 3 && graphType != 40,
				task_item_id: graphType != 3 && graphType != 40,
				color_id: (graphType > 1 && graphType < 9) || graphType == 20 || graphType == 30,
				line_width: (graphType > 3 && graphType < 7) || graphType == 20,
				dashes: (graphType > 1 && graphType < 7) || graphType == 20,
				dash_offset: (graphType > 1 && graphType < 7) || graphType == 20,
				textalign: graphType == 40,
				shift: (graphType > 3 && graphType < 9) || graphType == 20,
				alpha: (graphType > 3 && graphType < 9) || graphType == 20 || graphType == 40,
				consolidation_function_id: graphType > 3 && graphType != 10 && graphType != 15 && graphType != 30 && graphType != 40,
				cdef_id: graphType > 3 && graphType != 40,
				vdef_id: graphType > 3 && graphType != 40,
				value: graphType == 2 || graphType == 3 || graphType == 30,
				gprint_id: graphType > 8 && graphType < 16,
				text_format: graphType >= 1 && graphType != 10 && graphType != 15 && graphType != 40,
				hard_return: graphType >= 1 && graphType != 10 && graphType != 15 && graphType != 40,
			});

			changeColorId();
		}
	</script>
<?php
}
/* ------------------------
	The 'actions' function
   ------------------------ */

function form_actions() {
	global $graph_actions, $config, $image_types;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_request_var('drp_action') == '1') { // delete
				db_execute('DELETE FROM graph_templates
					WHERE ' . array_to_sql_or($selected_items, 'id'));

				$snmp_graph_ids = array_rekey(
					db_fetch_assoc('SELECT id
						FROM snmp_query_graph
						WHERE ' . array_to_sql_or($selected_items, 'graph_template_id')),
					'id', 'id'
				);

				if (cacti_sizeof($snmp_graph_ids)) {
					db_execute('DELETE FROM snmp_query_graph
						WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));

					db_execute('DELETE FROM snmp_query_graph_rrd
						WHERE snmp_query_graph_id IN (' . implode(', ', $snmp_graph_ids) . ')');

					db_execute('DELETE FROM snmp_query_graph_rrd_sv
						WHERE snmp_query_graph_id IN (' . implode(', ', $snmp_graph_ids) . ')');

					db_execute('DELETE FROM snmp_query_graph_sv
						WHERE snmp_query_graph_id IN (' . implode(', ', $snmp_graph_ids) . ')');
				}

				$graph_template_input = db_fetch_assoc('SELECT id
					FROM graph_template_input
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));

				if (cacti_sizeof($graph_template_input) > 0) {
					foreach ($graph_template_input as $item) {
						db_execute_prepared('DELETE FROM graph_template_input_defs
							WHERE graph_template_input_id = ?', array($item['id']));
					}
				}

				db_execute('DELETE FROM graph_template_input
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));

				db_execute('DELETE FROM graph_templates_graph
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id') . ' AND local_graph_id=0');

				db_execute('DELETE FROM graph_templates_item
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id') . ' AND local_graph_id=0');

				db_execute('DELETE FROM host_template_graph
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));

				/* 'undo' any graph that is currently using this template */
				db_execute('UPDATE graph_templates_graph
					SET local_graph_template_graph_id=0, graph_template_id=0
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));

				db_execute('UPDATE graph_templates_item
					SET local_graph_template_item_id=0, graph_template_id=0
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));

				db_execute('UPDATE graph_local
					SET graph_template_id=0
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));
			} elseif (get_request_var('drp_action') == '2') { // duplicate
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					api_duplicate_graph(0, $selected_items[$i], get_nfilter_request_var('title_format'));
				}
			} elseif (get_request_var('drp_action') == '3') { // resize
				get_filter_request_var('graph_width');
				get_filter_request_var('graph_height');
				get_filter_request_var('image_format_id');

				for ($i=0;($i < cacti_count($selected_items));$i++) {
					db_execute_prepared('UPDATE graph_templates_graph
						SET width = ?, height = ?, image_format_id = ?
						WHERE graph_template_id = ?',
						array(get_request_var('graph_width'),
						get_request_var('graph_height'),
						get_request_var('image_format_id'),
						$selected_items[$i]));
				}
			} elseif (get_request_var('drp_action') == '4') { // retemplate
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					retemplate_graphs($selected_items[$i]);

					$graph_template_name = db_fetch_cell_prepared('SELECT name
						FROM graph_templates
						WHERE id = ?',
						array($selected_items[$i]));

					if (isset($_SESSION['sess_gt_repairs']) && $_SESSION['sess_gt_repairs'] > 0) {
						raise_message('gt_repair' . $selected_items[$i], __('Sync of Graph Template \'%s\' Resulted in %s Repairs!', $graph_template_name, $_SESSION['sess_gt_repairs']), MESSAGE_LEVEL_WARN);
					} else {
						raise_message('gt_repair' . $selected_items[$i], __('Sync of Graph Template \'%s\' Resulted in no Repairs.', $graph_template_name), MESSAGE_LEVEL_INFO);
					}
				}
			} elseif (get_request_var('drp_action') == '5') { // resequence graphs with sequences off
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					retemplate_graphs($selected_items[$i], 0, true);

					$graph_template_name = db_fetch_cell_prepared('SELECT name
						FROM graph_templates
						WHERE id = ?',
						array($selected_items[$i]));

					if (isset($_SESSION['sess_gt_repairs']) && $_SESSION['sess_gt_repairs'] > 0) {
						raise_message('gt_repair' . $selected_items[$i], __('Sync of Graph Template \'%s\' Resulted in %s Repairs!', $graph_template_name, $_SESSION['sess_gt_repairs']), MESSAGE_LEVEL_WARN);
					} else {
						raise_message('gt_repair' . $selected_items[$i], __('Sync of Graph Template \'%s\' Resulted in no Repairs.', $graph_template_name), MESSAGE_LEVEL_INFO);
					}
				}
			}
		}

		header('Location: graph_templates.php');

		exit;
	}

	/* setup some variables */
	$graph_list = '';
	$i          = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1], 'chk[1]');
			/* ==================================================== */

			$graph_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE id = ?', array($matches[1]))) . '</li>';
			$graph_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('graph_templates.php');

	html_start_box($graph_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($graph_array) && cacti_sizeof($graph_array)) {
		if (get_request_var('drp_action') == '1') { // delete
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to delete the following Graph Template(s).  Any Graph(s) associated with the Template(s) will become individual Graph(s).') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Delete Graph Template(s)') . "'>";
		} elseif (get_request_var('drp_action') == '2') { // duplicate
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to duplicate the following Graph Template(s). You can optionally change the title format for the new Graph Template(s).') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
					<p><strong>" . __('Title Format:'). '</strong><br>';
			form_text_box('title_format', '<template_title> (1)', '', '255', '30', 'text');
			print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue'). "' title='" . __esc('Duplicate Graph Template(s)') . "'>";
		} elseif (get_request_var('drp_action') == '3') { // resize
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to resize the following Graph Template(s) and Graph(s) to the Height and Width below.  The defaults below are maintained in Settings.') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
				</td>
			</tr>
			</table>
			<table class='filterTable'>
			<tr>
				<td>";

			print __('Graph Height') . '</td><td>';
			form_text_box('graph_height', read_config_option('default_graph_height'), '', '5', '5', 'text');
			print '</td></tr><tr><td>' . __('Graph Width') . '</td><td>';
			form_text_box('graph_width', read_config_option('default_graph_width'), '', '5', '5', 'text');
			print '</td></tr><tr><td>' . __('Image Format') . '</td><td>';
			form_dropdown('image_format_id', $image_types, '', '', read_config_option('default_image_format'), '', '');

			print "</td></tr></table><div class='break'></div><table style='width:100%'>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Resize Selected Graph Template(s)') . "'>";
		} elseif (get_request_var('drp_action') == '4') { // retemplate
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to perform a Full Synchronization between your Graphs and the chosen Graph Templates(s). If you simply have a situation where the Graph Items don\'t match the Graph Template, try the Quick Sync Graphs option first as it will take much less time.  This function is important if you have Graphs that exist with multiple versions of a Graph Template and wish to make them all common in appearance.') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue'). "' title='" . __esc('Synchronize Graphs to Graph Template(s)') . "'>";
		} elseif (get_request_var('drp_action') == '5') { // retemplate only where sequences are off
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to perform a Quick Synchronization of your Graphs for the following Graph Template(s). Use this option if your Graphs have Graph Items that do not match your Graph Template.  If this option does not work, use the Full Sync Graphs option, which will take more time to complete.') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue'). "' title='" . __esc('Synchronize Graphs to Graph Template(s)') . "'>";
		}
	} else {
		raise_message(40);
		header('Location: graph_templates.php');

		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($graph_array) ? serialize($graph_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function item() {
	global $consolidation_functions, $graph_item_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (isempty_request_var('id')) {
		$template_item_list = array();

		$header_label = 'Graph Template Items [new]';
	} else {
		$template_item_list = db_fetch_assoc_prepared("SELECT gti.id, gti.sequence, gti.text_format, gti.alpha,
			gti.value, gti.hard_return, gti.graph_type_id, gti.consolidation_function_id, gti.textalign,
			CONCAT(IFNULL(dt.name, ''), ' (', dtr.data_source_name, ')') AS data_source_name,
			cdef.name AS cdef_name, vdef.name as vdef_name, colors.hex, gtgp.name as gprint_name
			FROM graph_templates_item AS gti
			LEFT JOIN data_template_rrd AS dtr
			ON gti.task_item_id=dtr.id
			LEFT JOIN data_local AS dl
			ON dtr.local_data_id=dl.id
			LEFT JOIN data_template AS dt
			ON dt.id=dtr.data_template_id
			LEFT JOIN data_template_data AS dtd
			ON dl.id=dtd.local_data_id
			LEFT JOIN graph_templates_gprint as gtgp
			ON gtgp.id=gti.gprint_id
			LEFT JOIN cdef
			ON cdef_id=cdef.id
			LEFT JOIN vdef
			ON vdef_id=vdef.id
			LEFT JOIN colors
			ON color_id=colors.id
			WHERE gti.graph_template_id = ?
			AND gti.local_graph_id=0
			ORDER BY gti.sequence",
			array(get_request_var('id')));

		$header_label = __esc('Graph Template Items [edit: %s]', db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE id = ?', array(get_request_var('id'))));
	}

	html_start_box($header_label, '100%', '', '3', 'center', 'graph_templates.php?action=item_edit&graph_template_id=' . get_request_var('id'));
	draw_graph_items_list($template_item_list, 'graph_templates.php', 'graph_template_id=' . get_request_var('id'), false);
	html_end_box();

	html_start_box(__('Graph Item Inputs'), '100%', '', '3', 'center', 'graph_templates.php?action=input_edit&graph_template_id=' . get_request_var('id'));

	print "<tr class='tableHeader'>";
	DrawMatrixHeaderItem(__('Name'),'',2);
	print '</tr>';

	$template_item_list = db_fetch_assoc_prepared('SELECT id, name
		FROM graph_template_input
		WHERE graph_template_id = ?
		ORDER BY name', array(get_request_var('id')));

	$i = 0;

	if (cacti_sizeof($template_item_list) > 0) {
		foreach ($template_item_list as $item) {
			form_alternate_row('', true);
			?>
			<td>
				<a class='linkEditMain' href='<?php print html_escape('graph_templates.php?action=input_edit&id=' . $item['id'] . '&graph_template_id=' . get_request_var('id'));?>'><?php print html_escape($item['name']);?></a>
			</td>
			<td class='right'>
				<a class='deleteMarker fa fa-times' title='<?php print __esc('Delete');?>' href='<?php print html_escape('graph_templates.php?action=input_remove&id=' . $item['id'] . '&graph_template_id=' . get_request_var('id') . '&nostate=true');?>'></a>
			</td>
		</tr>
		<?php
		}
	} else {
		print "<tr><td colspan='2'><em>" . __('No Inputs') . '</em></td></tr>';
	}

	?>
	<script type='text/javascript'>
	$(function() {
		$('.deleteMarker, .moveArrow').click(function(event) {
			event.preventDefault();
			loadUrl({url:$(this).attr('href')})
		});
	});
	</script>
	<?php

	html_end_box();
}

/* ----------------------------
	template - Graph Templates
   ---------------------------- */

function template_edit() {
	global $struct_graph, $image_types, $fields_graph_template_template_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	/* graph item list goes here */
	if (!isempty_request_var('id')) {
		item();
	}

	if (!isempty_request_var('id')) {
		$template = db_fetch_row_prepared('SELECT *
			FROM graph_templates
			WHERE id = ?',
			array(get_request_var('id')));

		$template_graph = db_fetch_row_prepared('SELECT *
			FROM graph_templates_graph
			WHERE graph_template_id = ?
			AND local_graph_id=0',
			array(get_request_var('id')));

		$header_label = __esc('Graph Template [edit: %s]', $template['name']);
	} else {
		$header_label = __('Graph Template [new]');
	}

	form_start('graph_templates.php', 'graph_templates');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_graph_template_template_edit, (isset($template) ? $template : array()), (isset($template_graph) ? $template_graph : array()))
		)
	);

	html_end_box(true, true);

	html_start_box(__('Graph Template Options'), '100%', true, '3', 'center', '');

	$form_array = array();

	foreach ($struct_graph as $field_name => $field_array) {
		$form_array += array($field_name => $struct_graph[$field_name]);

		if ($form_array[$field_name]['method'] != 'spacer') {
			$form_array[$field_name]['value'] = (isset($template_graph[$field_name]) ? $template_graph[$field_name] : '');
		}

		$form_array[$field_name]['form_id'] = (isset($template_graph['id']) ? $template_graph['id'] : '0');

		if ($form_array[$field_name]['method'] != 'spacer') {
			$form_array[$field_name]['sub_checkbox'] = array(
				'name'          => 't_' . $field_name,
				'friendly_name' => __esc('Check this checkbox if you wish to allow the user to override the value on the right during Graph creation.'),
				'value'         => (isset($template_graph['t_' . $field_name]) ? $template_graph['t_' . $field_name] : '')
			);
		}
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $form_array
		)
	);

	form_hidden_box('rrdtool_version', get_rrdtool_version(), '');

	html_end_box(true, true);

	form_save_button('graph_templates.php', 'return');

	//Now we need some javascript to make it dynamic
	?>
	<script type='text/javascript'>

	$(function() {
		dynamic();
	});

	function dynamic() {
		$('#t_scale_log_units').prop('disabled', true);
		$('#scale_log_units').prop('disabled', true);
		if ($('#auto_scale_log').is(':checked')) {
			$('#t_scale_log_units').prop('disabled', false);
			$('#scale_log_units').prop('disabled', false);
		}
	}

	function changeScaleLog() {
		$('#t_scale_log_units').prop('disabled', true);
		$('#scale_log_units').prop('disabled', true);
		if ($('#auto_scale_log').is(':checked')) {
			$('#t_scale_log_units').prop('disabled', false);
			$('#scale_log_units').prop('disabled', false);
		}
	}
	</script>
	<?php
}

function template() {
	global $graph_actions, $item_rows, $image_types;

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
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'cdef_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'vdef_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'has_graphs' => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => read_config_option('default_has') == 'on' ? 'true':'false'
			)
	);

	validate_store_request_vars($filters, 'sess_gt');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Graph Templates'), '100%', '', '3', 'center', 'graph_templates.php?action=template_edit');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_graph_template' action='graph_templates.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default' id='filter' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<span>
							<input type='checkbox' id='has_graphs' <?php print(get_request_var('has_graphs') == 'true' ? 'checked':'');?>>
							<label for='has_graphs'><?php print __('Has Graphs');?></label>
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
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Graph Templates');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'";

									if (get_request_var('rows') == $key) {
										print ' selected';
									} print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('CDEFs');?>
					</td>
					<td>
						<select id='cdef_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('cdef_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<?php
							$cdefs = db_fetch_assoc('SELECT c.id, c.name
								FROM cdef AS c
								INNER JOIN (SELECT DISTINCT cdef_id FROM graph_templates_item WHERE cdef_id > 0) AS gti
								ON c.id = gti.cdef_id
								ORDER BY name');

							if (cacti_sizeof($cdefs)) {
								foreach ($cdefs as $cdef) {
									print "<option value='" . $cdef['id'] . "'" . (get_request_var('cdef_id') == $cdef['id'] ? ' selected':'') . '>' . html_escape($cdef['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('VDEFs');?>
					</td>
					<td>
						<select id='vdef_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('vdef_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<?php
							$vdefs = db_fetch_assoc('SELECT v.id, v.name
								FROM vdef AS v
								INNER JOIN (SELECT DISTINCT vdef_id FROM graph_templates_item WHERE vdef_id > 0) AS gti
								ON v.id = gti.vdef_id
								ORDER BY name');

							if (cacti_sizeof($vdefs)) {
								foreach ($vdefs as $vdef) {
									print "<option value='" . $vdef['id'] . "'" . (get_request_var('vdef_id') == $vdef['id'] ? ' selected':'') . '>' . html_escape($vdef['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>
		var disabled = true;

		function applyFilter() {
			strURL  = 'graph_templates.php';
			strURL += '?filter='+$('#filter').val();
			strURL += '&rows='+$('#rows').val();
			strURL += '&cdef_id='+$('#cdef_id').val();
			strURL += '&vdef_id='+$('#vdef_id').val();
			strURL += '&has_graphs='+$('#has_graphs').is(':checked');
			loadUrl({url:strURL})
		}

		function clearFilter() {
			strURL = 'graph_templates.php?clear=1';
			loadUrl({url:strURL})
		}

		$(function() {
			$('#refresh').click(function() {
				applyFilter();
			});

			$('#has_graphs').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#form_graph_template').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (gt.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (get_request_var('vdef_id') == '-1') {
		/* Show all items */
	} elseif (!isempty_request_var('vdef_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' gti.vdef_id = ' . get_request_var('vdef_id');
	}

	if (get_request_var('cdef_id') == '-1') {
		/* Show all items */
	} elseif (!isempty_request_var('cdef_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' gti.cdef_id = ' . get_request_var('cdef_id');
	}

	if (get_request_var('has_graphs') == 'true') {
		$sql_having = 'HAVING graphs > 0';
	} else {
		$sql_having = '';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM graph_templates
		$sql_where
		$sql_having");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$template_list = db_fetch_assoc("SELECT
		gt.id, gt.name, gt.graphs,
		CONCAT(gtg.height, 'x', gtg.width) AS size, gtg.vertical_label, gtg.image_format_id
		FROM graph_templates AS gt
		INNER JOIN graph_templates_graph AS gtg
		ON gtg.graph_template_id = gt.id
		AND gtg.local_graph_id = 0
		$sql_where
		$sql_having
		$sql_order
		$sql_limit");

	$display_text = array(
		'name' => array(
			'display' => __('Graph Template Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this Graph Template.')
		),
		'gt.id' => array(
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The internal ID for this Graph Template.  Useful when performing automation or debugging.')
		),
		'nosort3' => array(
			'display' => __('Deletable'),
			'align'   => 'right',
			'tip'     => __('Graph Templates that are in use cannot be Deleted.  In use is defined as being referenced by a Graph.')
		),
		'graphs' => array(
			'display' => __('Graphs Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Graphs using this Graph Template.')
		),
		'image_format_id' => array(
			'display' => __('Image Format'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The default image format for the resulting Graphs.')
		),
		'size' => array(
			'display' => __('Size'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The default size of the resulting Graphs.')
		),
		'vertical_label' => array(
			'display' => __('Vertical Label'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The vertical label for the resulting Graphs.')
		)
	);

	$nav = html_nav_bar('graph_templates.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Graph Templates'), 'page', 'main');

	form_start('graph_templates.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($template_list)) {
		foreach ($template_list as $template) {
			if ($template['graphs'] > 0) {
				$disabled = true;
			} else {
				$disabled = false;
			}

			form_alternate_row('line' . $template['id'], true, $disabled);
			form_selectable_cell(filter_value($template['name'], get_request_var('filter'), 'graph_templates.php?action=template_edit&id=' . $template['id']), $template['id']);
			form_selectable_cell($template['id'], $template['id'], '', 'right');
			form_selectable_cell($disabled ? __('No'):__('Yes'), $template['id'], '', 'right');
			form_selectable_cell(number_format_i18n($template['graphs'], '-1'), $template['id'], '', 'right');
			form_selectable_cell($image_types[$template['image_format_id']], $template['id'], '', 'right');
			form_selectable_ecell($template['size'], $template['id'], '', 'right');
			form_selectable_ecell($template['vertical_label'], $template['id'], '', 'right');
			form_checkbox_cell($template['name'], $template['id'], $disabled);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Graph Templates Found') . "</em></td></tr>\n";
	}
	html_end_box(false);

	if (cacti_sizeof($template_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($graph_actions);

	form_end();
}

function input_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('graph_template_id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM graph_template_input WHERE id = ?', array(get_request_var('id')));
	db_execute_prepared('DELETE FROM graph_template_input_defs WHERE graph_template_input_id = ?', array(get_request_var('id')));
}

function input_edit() {
	global $consolidation_functions, $graph_item_types, $struct_graph_item, $fields_graph_template_input_edit;

	// Remove filter item
	unset($struct_graph_item['data_template_id']);

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('graph_template_id');
	/* ==================================================== */

	$header_label = __esc('Graph Item Inputs [edit graph: %s]', db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE id = ?', array(get_request_var('graph_template_id'))));

	/* get a list of all graph item field names and populate an array for user display */
	foreach ($struct_graph_item as $field_name => $field_array) {
		if ($field_array['method'] != 'view') {
			$graph_template_items[$field_name] = $field_array['friendly_name'];
		}
	}

	if (!isempty_request_var('id')) {
		$graph_template_input = db_fetch_row_prepared('SELECT * FROM graph_template_input WHERE id = ?', array(get_request_var('id')));
	}

	form_start('graph_templates.php');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_graph_template_input_edit, (isset($graph_template_input) ? $graph_template_input : array()), (isset($graph_template_items) ? $graph_template_items : array()), $_REQUEST)
		)
	);

	if (!isset_request_var('id')) {
		set_request_var('id', 0);
	}

	html_end_box(true, true);

	$item_list = db_fetch_assoc_prepared("SELECT
		CONCAT_WS(' - ', dtd.name, dtr.data_source_name) AS data_source_name,
		gti.text_format,
		gti.id AS graph_templates_item_id,
		gti.graph_type_id,
		gti.consolidation_function_id,
		gtid.graph_template_input_id
		FROM graph_templates_item AS gti
		LEFT JOIN graph_template_input_defs AS gtid
		ON gtid.graph_template_item_id = gti.id
		AND gtid.graph_template_input_id = ?
		LEFT JOIN data_template_rrd AS dtr
		ON gti.task_item_id = dtr.id
		LEFT JOIN data_local AS dl
		ON dtr.local_data_id = dl.id
		LEFT JOIN data_template_data AS dtd
		ON dl.id = dtd.local_data_id
		WHERE gti.local_graph_id = 0
		AND gti.graph_template_id = ?
		ORDER BY gti.sequence", array(get_request_var('id'), get_request_var('graph_template_id')));

	html_start_box(__('Associated Graph Items'), '100%', false, '3', 'center', '');

	$i                 = 0;
	$any_selected_item = '';

	if (cacti_sizeof($item_list)) {
		foreach ($item_list as $item) {
			form_alternate_row();

			if ($item['graph_template_input_id'] == '') {
				$old_value = '';
			} else {
				$old_value         = 'on';
				$any_selected_item = $item['graph_templates_item_id'];
			}

			if ($graph_item_types[$item['graph_type_id']] == 'GPRINT') {
				$start_bold = '';
				$end_bold   = '';
			} else {
				$start_bold = '<strong>';
				$end_bold   = '</strong>';
			}

			print '<td>';

			$name = $start_bold . __esc('Item #%s', $i + 1) . ': ' . $graph_item_types[$item['graph_type_id']] . ' (' . $consolidation_functions[$item['consolidation_function_id']] . ')' . $end_bold;

			form_checkbox('i_' . $item['graph_templates_item_id'], $old_value, '', '', '', get_request_var('graph_template_id'));
			print "<label for='i_" . $item['graph_templates_item_id'] . "'>" . $name . '</label>';

			print '</td>';

			$i++;

			form_end_row();
		}
	} else {
		print '<tr><td><em>' . __('No Items') . '</em></td></tr>';
	}

	form_hidden_box('any_selected_item', $any_selected_item, '');

	html_end_box(true, true);

	form_save_button('graph_templates.php?action=template_edit&id=' . get_request_var('graph_template_id'));
}
