<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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
include_once('./lib/api_data_source.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

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
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset_request_var('save_component_item')) {
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
					'color_id' => '0',
					'graph_type_id' => '9',
					'consolidation_function_id' => '4',
					'text_format' => __('Cur:'),
					'hard_return' => ''
					),
				1 => array(
					'color_id' => '0',
					'graph_type_id' => '9',
					'consolidation_function_id' => '1',
					'text_format' => __('Avg:'),
					'hard_return' => ''
					),
				2 => array(
					'color_id' => '0',
					'graph_type_id' => '9',
					'consolidation_function_id' => '3',
					'text_format' => __('Max:'),
					'hard_return' => 'on'
					)
			);
		} elseif ($graph_item_types[get_nfilter_request_var('graph_type_id')] == 'LEGEND_CAMM') {
	         /* this can be a major time saver when creating lots of graphs with the typical
				GPRINT LAST/AVERAGE/MAX legends */
			$items = array(
				0 => array(
					'color_id' => '0',
					'graph_type_id' => '9',
					'consolidation_function_id' => '4',
					'text_format' => __('Cur:'),
					'hard_return' => ''
				),
				1 => array(
					'color_id' => '0',
					'graph_type_id' => '9',
					'consolidation_function_id' => '1',
					'text_format' => __('Avg:'),
					'hard_return' => ''
				),
				2 => array(
					'color_id' => '0',
					'graph_type_id' => '9',
					'consolidation_function_id' => '2',
					'text_format' => __('Min:'),
					'hard_return' => ''
				),
				3 => array(
					'color_id' => '0',
					'graph_type_id' => '9',
					'consolidation_function_id' => '3',
					'text_format' => __('Max:'),
					'hard_return' => 'on'
				)
			);
		}

		$sequence = get_request_var('sequence');

		foreach ($items as $item) {
			/* generate a new sequence if needed */
			if (empty($sequence)) {
				$sequence = get_sequence($sequence, 'sequence', 'graph_templates_item', 'graph_template_id=' . get_request_var('graph_template_id') . ' AND local_graph_id=0');
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
			}else { # make sure to transfer old LINEx style into line_width on save
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

			$save['dashes']      = form_input_validate((isset_request_var('dashes') ? get_nfilter_request_var('dashes') : ''), 'dashes', '^[0-9]+[,0-9]*$', true, 3);
            $save['dash_offset'] = form_input_validate((isset_request_var('dash_offset') ? get_nfilter_request_var('dash_offset') : ''), 'dash_offset', '^[0-9]+$', true, 3);
			$save['cdef_id']     = form_input_validate(get_nfilter_request_var('cdef_id'), 'cdef_id', '^[0-9]+$', true, 3);
			$save['vdef_id']     = form_input_validate(get_nfilter_request_var('vdef_id'), 'vdef_id', '^[0-9]+$', true, 3);
			$save['shift']       = form_input_validate((isset_request_var('shift') ? get_nfilter_request_var('shift') : ''), 'shift', '^((on)|)$', true, 3);
			$save['consolidation_function_id'] = form_input_validate((isset($item['consolidation_function_id']) ? $item['consolidation_function_id'] : get_nfilter_request_var('consolidation_function_id')), 'consolidation_function_id', '^[0-9]+$', true, 3);
			$save['textalign']   = form_input_validate((isset_request_var('textalign') ? get_nfilter_request_var('textalign') : ''), 'textalign', '^[a-z]+$', true, 3);
			$save['text_format'] = form_input_validate((isset($item['text_format']) ? $item['text_format'] : get_nfilter_request_var('text_format')), 'text_format', '', true, 3);
			$save['value']       = form_input_validate(get_nfilter_request_var('value'), 'value', '', true, 3);
			$save['hard_return'] = form_input_validate(((isset($item['hard_return']) ? $item['hard_return'] : (isset_request_var('hard_return') ? get_nfilter_request_var('hard_return') : ''))), 'hard_return', '', true, 3);
			$save['gprint_id']   = form_input_validate(get_nfilter_request_var('gprint_id'), 'gprint_id', '^[0-9]+$', true, 3);
			$save['sequence']    = $sequence;

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
							db_execute_prepared('DELETE FROM graph_template_input_defs
								WHERE graph_template_item_id = ?',
								array($graph_template_item_id));

							/* Input for current data source exists and has changed.  Update the association */
							if (isset($orig_data_source_to_input[$save['task_item_id']])) {
								db_execute_prepared('REPLACE INTO graph_template_input_defs
									(graph_template_input_id, graph_template_item_id)
									VALUES (?, ?)',
									array($orig_data_source_to_input[$save['task_item_id']], $graph_template_item_id));
							}
						}

						/* an input for the current data source does NOT currently exist, let's create one */
						if (!isset($orig_data_source_to_input[$save['task_item_id']])) {
							$ds_name = db_fetch_cell_prepared('SELECT data_source_name
								FROM data_template_rrd
								WHERE id = ?',
								array(get_nfilter_request_var('task_item_id')));

							db_execute_prepared("REPLACE INTO graph_template_input
								(hash, graph_template_id, name, column_name)
								VALUES (?, ?, ?, 'task_item_id')",
								array(get_hash_graph_template(0, 'graph_template_input'), $save['graph_template_id'], "Data Source [$ds_name]"));

							$graph_template_input_id = db_fetch_insert_id();

							$graph_items = db_fetch_assoc_prepared('SELECT id
								FROM graph_templates_item
								WHERE graph_template_id = ?
								AND task_item_id = ?',
								array($save['graph_template_id'], get_nfilter_request_var('task_item_id')));

							if (cacti_sizeof($graph_items)) {
								foreach ($graph_items as $graph_item) {
									db_execute_prepared('REPLACE INTO graph_template_input_defs
										(graph_template_input_id, graph_template_item_id)
										VALUES (?, ?)',
										array($graph_template_input_id, $graph_item['id']));
								}
							}
						}
					}

					push_out_graph_item($graph_template_item_id);

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
			header('Location: graph_templates_items.php?action=item_edit&graph_template_item_id=' . (empty($graph_template_item_id) ? get_nfilter_request_var('graph_template_item_id') : $graph_template_item_id) . '&id=' . get_nfilter_request_var('graph_template_id'));
			exit;
		} else {
			header('Location: graph_templates.php?action=template_edit&id=' . get_nfilter_request_var('graph_template_id'));
			exit;
		}
	}
}

/* -----------------------
    item - Graph Items
   ----------------------- */

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
		array(get_request_var('id')));

	$text_type  = $graph_item_types[$graph_type];

	if (!empty($next_id) && isset($arr[get_request_var('id')])) {
		move_graph_group(get_request_var('id'), $arr, $next_id, 'next');
	} elseif (!preg_match('/(AREA|STACK|LINE)/', $text_type)) {
		/* this is so we know the "other" graph item to propagate the changes to */
		$next_item = get_item('graph_templates_item', 'sequence', get_request_var('id'), 'graph_template_id=' . get_request_var('graph_template_id') . ' AND local_graph_id=0', 'next');

		move_item_down('graph_templates_item', get_request_var('id'), 'graph_template_id=' . get_request_var('graph_template_id') . ' AND local_graph_id=0');
	}

	if (!isempty_request_var('graph_template_id')) {
		resequence_graphs(get_request_var('graph_template_id'), -1);
	}
}

function item_moveup() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('graph_template_id');
	/* ==================================================== */

	global $graph_item_types;

	$arr = get_graph_group(get_request_var('id'));
	$next_id = get_graph_parent(get_request_var('id'), 'previous');

	$graph_type = db_fetch_cell_prepared('SELECT graph_type_id
		FROM graph_templates_item
		WHERE id = ?',
		array(get_request_var('id')));

	$text_type  = $graph_item_types[$graph_type];

	if (!empty($next_id) && isset($arr[get_request_var('id')])) {
		move_graph_group(get_request_var('id'), $arr, $next_id, 'previous');
	} elseif (!preg_match('/(AREA|STACK|LINE)/', $text_type)) {
		/* this is so we know the "other" graph item to propagate the changes to */
		$last_item = get_item('graph_templates_item', 'sequence', get_request_var('id'), 'graph_template_id=' . get_request_var('graph_template_id') . ' AND local_graph_id=0', 'previous');

		move_item_up('graph_templates_item', get_request_var('id'), 'graph_template_id=' . get_request_var('graph_template_id') . ' AND local_graph_id=0');
	}

	if (!isempty_request_var('graph_template_id')) {
		resequence_graphs(get_request_var('graph_template_id'), -1);
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
	/* ==================================================== */

	form_start('graph_templates_items.php', 'graph_items');

	$header_label = __esc('Graph Template Items [edit graph: %s]', db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE id = ?', array(get_request_var('graph_template_id'))));

	html_start_box($header_label, '100%', true, '3', 'center', '');

	if (!isempty_request_var('id')) {
		$template_item = db_fetch_row_prepared('SELECT *
			FROM graph_templates_item
			WHERE id = ?',
			array(get_request_var('id')));
	}

	/* by default, select the LAST DS chosen to make everyone's lives easier */
	if (!isempty_request_var('graph_template_id')) {
		$default = db_fetch_row_prepared('SELECT task_item_id
			FROM graph_templates_item
			WHERE graph_template_id = ?
			AND local_graph_id = 0
			ORDER BY sequence DESC',
			array(get_request_var('graph_template_id')));

		if (cacti_sizeof($default) > 0) {
			$struct_graph_item['task_item_id']['default'] = $default['task_item_id'];
		} else {
			$struct_graph_item['task_item_id']['default'] = 0;
		}
	}

	/* modifications to the default graph items array */
	$struct_graph_item['task_item_id']['sql'] = "SELECT
		CONCAT_WS('',data_template.name,' - ',' (',data_template_rrd.data_source_name,')') AS name,
		data_template_rrd.id
		FROM (data_template_data,data_template_rrd,data_template)
		WHERE data_template_rrd.data_template_id=data_template.id
		AND data_template_data.data_template_id=data_template.id
		AND data_template_data.local_data_id=0
		AND data_template_rrd.local_data_id=0
		ORDER BY data_template.name,data_template_rrd.data_source_name";

	$form_array = array();

	foreach ($struct_graph_item as $field_name => $field_array) {
		$form_array += array($field_name => $struct_graph_item[$field_name]);

		$form_array[$field_name]['value'] = (isset($template_item) ? $template_item[$field_name] : '');
		$form_array[$field_name]['form_id'] = (isset($template_item) ? $template_item['id'] : '0');

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
			if ($('#shift').is(':checked')) {
				$('#row_value').show();
			} else {
				$('#row_value').hide();
			}
		});

		setRowVisibility();
		$('#graph_type_id').change(function(data) {
			setRowVisibility();
		});
	});

	/*
	columns - task_item_id color_id alpha graph_type_id consolidation_function_id cdef_id value gprint_id text_format hard_return

	graph_type_ids - 1 - Comment 2 - HRule 3 - Vrule 4 - Line1 5 - Line2 6 - Line3 7 - Area 8 - Stack 9 - Gprint 10 - Legend
	*/

	function changeColorId() {
		$('#alpha').prop('disabled', true);
		if ($('#color_id').val() != 0) {
			$('#alpha').prop('disabled', false);
		}
		switch($('#graph_type_id').val()) {
		case '4':
		case '5':
		case '6':
		case '7':
		case '8':
			$('#alpha').prop('disabled', false);
		}
	}

	function setRowVisibility() {
		switch($('#graph_type_id').val()) {
		case '1': // COMMENT
			$('#row_task_item_id').show();
			$('#row_color_id').hide();
			$('#row_line_width').hide();
			$('#row_dashes').hide();
			$('#row_dash_offset').hide();
			$('#row_textalign').hide();
			$('#row_shift').hide();
			$('#row_alpha').hide();
			$('#row_consolidation_function_id').hide();
			$('#row_cdef_id').hide();
			$('#row_vdef_id').hide();
			$('#row_value').hide();
			$('#row_gprint_id').hide();
			$('#row_text_format').show();
			$('#row_hard_return').show();
			break;
		case '2': // HRULE
			$('#row_task_item_id').show();
			$('#row_color_id').show();
			$('#row_line_width').hide();
			$('#row_dashes').show();
			$('#row_dash_offset').show();
			$('#row_textalign').hide();
			$('#row_shift').hide();
			$('#row_alpha').hide();
			$('#row_consolidation_function_id').hide();
			$('#row_cdef_id').hide();
			$('#row_vdef_id').hide();
			$('#row_value').show();
			$('#row_gprint_id').hide();
			$('#row_text_format').show();
			$('#row_hard_return').show();
			break;
		case '3': // VRULE
			$('#row_task_item_id').hide();
			$('#row_color_id').show();
			$('#row_line_width').hide();
			$('#row_dashes').show();
			$('#row_dash_offset').show();
			$('#row_textalign').hide();
			$('#row_shift').hide();
			$('#row_alpha').hide();
			$('#row_consolidation_function_id').hide();
			$('#row_cdef_id').hide();
			$('#row_vdef_id').hide();
			$('#row_value').show();
			$('#row_gprint_id').hide();
			$('#row_text_format').show();
			$('#row_hard_return').show();
			break;
		case '4': // LINE1
		case '5': // LINE2
		case '6': // LINE3
			$('#row_task_item_id').show();
			$('#row_color_id').show();
			$('#row_line_width').show();
			$('#row_dashes').show();
			$('#row_dash_offset').show();
			$('#row_textalign').hide();
			$('#row_shift').show();
			$('#row_alpha').show();
			$('#row_consolidation_function_id').show();
			$('#row_cdef_id').show();
			$('#row_vdef_id').hide();
			$('#row_value').hide();
			$('#row_gprint_id').hide();
			$('#row_text_format').show();
			$('#row_hard_return').show();
			break;
		case '20': // LINE:STACK
			$('#row_task_item_id').show();
			$('#row_color_id').show();
			$('#row_line_width').show();
			$('#row_dashes').show();
			$('#row_dash_offset').show();
			$('#row_textalign').hide();
			$('#row_shift').show();
			$('#row_alpha').show();
			$('#row_consolidation_function_id').show();
			$('#row_cdef_id').show();
			$('#row_vdef_id').hide();
			$('#row_value').hide();
			$('#row_gprint_id').hide();
			$('#row_text_format').show();
			$('#row_hard_return').show();
			break;
		case '7': // AREA
		case '8': // STACK
			$('#row_task_item_id').show();
			$('#row_color_id').show();
			$('#row_line_width').hide();
			$('#row_dashes').hide();
			$('#row_dash_offset').hide();
			$('#row_textalign').hide();
			$('#row_shift').show();
			$('#row_alpha').show();
			$('#row_consolidation_function_id').show();
			$('#row_cdef_id').show();
			$('#row_vdef_id').hide();
			$('#row_value').hide();
			$('#row_gprint_id').hide();
			$('#row_text_format').show();
			$('#row_hard_return').show();
			break;
		case '9':  // GPRINT
		case '11': // GPRINT:MAX
		case '12': // GPRINT:MIN
		case '13': // GPRINT:MIN
		case '14': // GPRINT:AVERAGE
			$('#row_task_item_id').show();
			$('#row_color_id').hide();
			$('#row_line_width').hide();
			$('#row_dashes').hide();
			$('#row_dash_offset').hide();
			$('#row_textalign').hide();
			$('#row_shift').hide();
			$('#row_alpha').hide();
			$('#row_consolidation_function_id').show();
			$('#row_cdef_id').show();
			$('#row_vdef_id').show();
			$('#row_value').hide();
			$('#row_gprint_id').show();
			$('#row_text_format').show();
			$('#row_hard_return').show();
			break;
		case '15': // LEGEND
		case '10': // LEGEND
			$('#row_task_item_id').show();
			$('#row_color_id').hide();
			$('#row_line_width').hide();
			$('#row_dashes').hide();
			$('#row_dash_offset').hide();
			$('#row_textalign').hide();
			$('#row_shift').hide();
			$('#row_alpha').hide();
			$('#row_consolidation_function_id').hide();
			$('#row_cdef_id').show();
			$('#row_vdef_id').show();
			$('#row_value').hide();
			$('#row_gprint_id').show();
			$('#row_text_format').hide();
			$('#row_hard_return').hide();
			break;
		case '30': // TICK
			$('#row_task_item_id').show();
			$('#row_color_id').show();
			$('#row_line_width').hide();
			$('#row_dashes').hide();
			$('#row_dash_offset').hide();
			$('#row_textalign').hide();
			$('#row_shift').hide();
			$('#row_alpha').show();
			$('#row_consolidation_function_id').hide();
			$('#row_cdef_id').show();
			$('#row_vdef_id').show();
			$('#row_value').show();
			$('#row_gprint_id').hide();
			$('#row_text_format').show();
			$('#row_hard_return').show();
			break;
		case '40': // TEXTALIGN
			$('#row_task_item_id').hide();
			$('#row_color_id').hide();
			$('#row_line_width').hide();
			$('#row_dashes').hide();
			$('#row_dash_offset').hide();
			$('#row_textalign').show();
			$('#row_shift').hide();
			$('#row_alpha').hide();
			$('#row_consolidation_function_id').hide();
			$('#row_cdef_id').hide();
			$('#row_vdef_id').hide();
			$('#row_value').hide();
			$('#row_gprint_id').hide();
			$('#row_text_format').hide();
			$('#row_hard_return').hide();
			break;
		}

		changeColorId();
	}

	</script>
<?php

}
