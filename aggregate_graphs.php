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
include_once('./lib/api_tree.php');
include_once('./lib/api_data_source.php');
include_once('./lib/api_aggregate.php');
include_once('./lib/data_query.php');
include_once('./lib/html_tree.php');
include_once('./lib/html_form_template.php');
include_once('./lib/poller.php');
include_once('./lib/reports.php');
include_once('./lib/rrd.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');

aggregate_prune_graphs();

$graph_actions = array(
	1 => __('Delete'),
	5 => __('Convert to Normal Graph'),
	4 => __('Place Graphs on Report'),
	2 => __('Migrate Aggregate to use a Template'),
	3 => __('Create New Aggregate from Aggregates')
);

$agg_item_actions = array(
	10 => __('Associate with Aggregate'),
	11 => __('Disassociate with Aggregate')
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
	case 'edit':
		top_header();
		graph_edit();
		bottom_footer();

		break;
	case 'item_remove':
		item_remove();

		header('Location: aggregate_graphs.php?action=edit&id=' . get_filter_request_var('local_graph_id'));

		break;
	case 'item_edit':
		top_header();

		item_edit();

		bottom_footer();

		break;
	case 'item_movedown':
		item_movedown();

		header('Location: aggregate_graphs.php?action=edit&id=' . get_filter_request_var('local_graph_id'));

		break;
	case 'item_moveup':
		item_moveup();

		header('Location: aggregate_graphs.php?action=edit&id=' . get_filter_request_var('local_graph_id'));

		break;
	default:
		top_header();
		aggregate_graph();
		bottom_footer();

		break;
}

function add_tree_names_to_actions_array() {
	global $graph_actions;

	/* add a list of tree names to the actions dropdown */
	$trees = db_fetch_assoc('SELECT id,name FROM graph_tree ORDER BY name');

	if (cacti_sizeof($trees)) {
		foreach ($trees as $tree) {
			$graph_actions['tr_' . $tree['id']] = __('Place on a Tree (%s)', $tree['name']);
		}
	}
}

function form_save() {
	if (!isset_request_var('save_component_graph') && !isset_request_var('save_component_input')) {
		header('Location: aggregate_graphs.php?action=edit&id=' . get_nfilter_request_var('id'));

		return null;
	}

	if (isset_request_var('save_component_graph')) {
		/* remember some often used values */
		$local_graph_id        = get_nfilter_request_var('local_graph_id', 0);
		$graph_template_id     = get_nfilter_request_var('graph_template_id', 0);
		$aggregate_template_id = get_nfilter_request_var('aggregate_template_id', 0);
		$graph_title           = form_input_validate(get_nfilter_request_var('title_format'), 'title_format', '', false, 3);

		if (is_error_message()) {
			raise_message(2);
			header('Location: aggregate_graphs.php?action=edit&id=' . $local_graph_id);

			return null;
		}

		/* get the aggregate graph id */
		$aggregate_graph_id  = db_fetch_cell_prepared('SELECT id
			FROM aggregate_graphs
			WHERE local_graph_id = ?', array($local_graph_id));

		/* if user disabled template propagation we need to get graph data from form */
		if (!isset_request_var('template_propogation')) {
			$aggregate_template_id = 0;
			$new_data              = aggregate_validate_graph_params($_POST, false);
		} else {
			$new_data = array();
		}

		if (is_error_message()) {
			raise_message(2);
			header('Location: aggregate_graphs.php?action=edit&id=' . $local_graph_id);

			return null;
		}

		/* save graph data to cacti tables */
		$graph_templates_graph_id = aggregate_graph_templates_graph_save(
			$local_graph_id,
			$graph_template_id,
			$graph_title,
			$aggregate_template_id,
			$new_data
		);

		/* update title in aggregate graphs table */
		db_execute_prepared(
			'UPDATE aggregate_graphs
			SET title_format = ?
			WHERE id = ?',
			array($graph_title, $aggregate_graph_id)
		);

		/* next lets see if any of the aggregate has changed and save as applicable
		 * if the graph is templates, we can simply ignore.  A simple check will
		 * determine if aggregation propagation is enabled
		 */
		if (!isset_request_var('template_propogation')) {
			/* template propagation is disabled */
			$save                          = array();
			$save['id']                    = $aggregate_graph_id;
			$save['aggregate_template_id'] = $aggregate_template_id;
			$save['template_propogation']  = '';
			$save['gprint_prefix']         = get_nfilter_request_var('gprint_prefix');
			$save['gprint_format']         = isset_request_var('gprint_format') ? 'on' : '';
			$save['total_prefix']          = get_nfilter_request_var('total_prefix');

			$save['total']                 = get_filter_request_var('total');
			$save['graph_type']            = get_filter_request_var('graph_type');
			$save['total_type']            = get_filter_request_var('total_type');
			$save['order_type']            = get_filter_request_var('order_type');

			/* see if anything changed, if so, we will have to push out the aggregate */
			if (!empty($aggregate_graph_id)) {
				$old = db_fetch_row_prepared(
					'SELECT *
					FROM aggregate_graphs
					WHERE id = ?',
					array($aggregate_graph_id)
				);

				$save_me = 0;

				$save_me += ($old['aggregate_template_id'] != $save['aggregate_template_id']);
				$save_me += ($old['template_propogation']  != $save['template_propogation']);
				$save_me += ($old['gprint_prefix']         != $save['gprint_prefix']);
				$save_me += ($old['gprint_format']         != $save['gprint_format']);
				$save_me += ($old['graph_type']            != $save['graph_type']);
				$save_me += ($old['total']                 != $save['total']);
				$save_me += ($old['total_type']            != $save['total_type']);
				$save_me += ($old['total_prefix']          != $save['total_prefix']);
				$save_me += ($old['order_type']            != $save['order_type']);

				if ($save_me) {
					$aggregate_graph_id = sql_save($save, 'aggregate_graphs');
				}

				/* save the template items now */
				/* get existing item ids and sequences from graph template */
				$graph_templates_items = array_rekey(
					db_fetch_assoc_prepared('SELECT id, sequence
						FROM graph_templates_item
						WHERE local_graph_id=0
						AND graph_template_id = ?
						ORDER BY sequence', array($graph_template_id)),
					'id',
					array('sequence')
				);

				/* get existing aggregate template items */
				$aggregate_graph_items_old = array_rekey(
					db_fetch_assoc_prepared('SELECT *
						FROM aggregate_graphs_graph_item
						WHERE aggregate_graph_id = ?
						ORDER BY sequence', array($aggregate_graph_id)),
					'graph_templates_item_id',
					array('aggregate_graph_id', 'graph_templates_item_id', 'sequence', 'color_template', 't_graph_type_id', 'graph_type_id', 't_cdef_id', 'cdef_id', 'item_skip', 'item_total')
				);

				/* update graph template item values with posted values */
				aggregate_validate_graph_items($_POST, $graph_templates_items);

				$items_changed = false;
				$items_to_save = array();
				$sequence      = 1;

				foreach ($graph_templates_items as $item_id => $data) {
					$item_new                            = array();
					$item_new['aggregate_graph_id']      = $aggregate_graph_id;
					$item_new['graph_templates_item_id'] = $item_id;

					$item_new['color_template'] = isset($data['color_template']) ? $data['color_template'] : 0;
					$item_new['item_skip']      = isset($data['item_skip']) ? 'on' : '';
					$item_new['item_total']     = isset($data['item_total']) ? 'on' : '';
					$item_new['sequence']       = $sequence;

					/* compare with old item to see if we need to push out. */
					if (!isset($aggregate_graph_items_old[$item_id])) {
						/* this item does not yet exist */
						$items_changed = true;
					} else {
						/* compare data from user to data from DB */
						foreach ($item_new as $field => $new_value) {
							if ($aggregate_graph_items_old[$item_id][$field] != $new_value) {
								$items_changed = true;
							}
						}
						/* fill in missing fields with db values */
						$item_new = array_merge($aggregate_graph_items_old[$item_id], $item_new);
					}
					$items_to_save[] = $item_new;

					$sequence++;
				}

				if ($items_changed) {
					aggregate_graph_items_save($items_to_save, 'aggregate_graphs_graph_item');
				}

				if ($save_me || $items_changed) {
					push_out_aggregates(0, $local_graph_id);
				}
			}
		}

		raise_message(1);

		header('Location: aggregate_graphs.php?action=edit&id=' . $local_graph_id);
	} elseif (isset_request_var('save_component_item')) {
		global $graph_item_types;

		$items[0] = array();

		// handle saving aggregate graph items in separate function
		if (get_request_var('aggregate_template_id') > 0 || get_request_var('aggregate_graph_id') > 0) {
			form_save_aggregate();
		}

		if ($graph_item_types[get_request_var('graph_type_id')] == 'LEGEND') {
			/* this can be a major time saver when creating lots of graphs with the typical
			GPRINT LAST/AVERAGE/MAX legends */
			$items = array(
				0 => array(
					'color_id'                  => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '4',
					'text_format'               => 'Current:',
					'hard_return'               => ''
					),
				1 => array(
					'color_id'                  => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '1',
					'text_format'               => 'Average:',
					'hard_return'               => ''
					),
				2 => array(
					'color_id'                  => '0',
					'graph_type_id'             => '9',
					'consolidation_function_id' => '3',
					'text_format'               => 'Maximum:',
					'hard_return'               => 'on'
					));
		}

		foreach ($items as $item) {
			/* generate a new sequence if needed */
			if (isempty_request_var('sequence')) {
				$sequence = get_filter_request_var('sequence');
				set_request_var('sequence', get_sequence($sequence, 'sequence', 'graph_templates_item', 'local_graph_id=' . get_request_var('local_graph_id')));
			}
			$save['id']                           = get_filter_request_var('graph_template_item_id');
			$save['graph_template_id']            = get_filter_request_var('graph_template_id');
			$save['local_graph_template_item_id'] = get_filter_request_var('local_graph_template_item_id');
			$save['local_graph_id']               = get_filter_request_var('local_graph_id');
			$save['task_item_id']                 = form_input_validate(get_filter_request_var('task_item_id'), 'task_item_id', '', true, 3);
			$save['color_id']                     = form_input_validate((isset($item['color_id']) ? $item['color_id'] : get_filter_request_var('color_id')), 'color_id', '', true, 3);

			/* if alpha is disabled, use invisible_alpha instead */
			if (!isset_request_var('alpha')) {
				set_request_var('alpha', get_nfilter_request_var('invisible_alpha'));
			}

			$save['alpha']                        = form_input_validate((isset($item['alpha']) ? $item['alpha'] : get_nfilter_request_var('alpha')), 'alpha', '', true, 3);
			$save['graph_type_id']                = form_input_validate((isset($item['graph_type_id']) ? $item['graph_type_id'] : get_filter_request_var('graph_type_id')), 'graph_type_id', '', true, 3);
			$save['cdef_id']                      = form_input_validate(get_filter_request_var('cdef_id'), 'cdef_id', '', true, 3);
			$save['consolidation_function_id']    = form_input_validate((isset($item['consolidation_function_id']) ? $item['consolidation_function_id'] : get_filter_request_var('consolidation_function_id')), 'consolidation_function_id', '', true, 3);
			$save['text_format']                  = form_input_validate((isset($item['text_format']) ? $item['text_format'] : get_nfilter_request_var('text_format')), 'text_format', '', true, 3);
			$save['value']                        = form_input_validate(get_nfilter_request_var('value'), 'value', '', true, 3);
			$save['hard_return']                  = form_input_validate(((isset($item['hard_return']) ? $item['hard_return'] : (isset_request_var('hard_return') ? get_nfilter_request_var('hard_return') : ''))), 'hard_return', '', true, 3);
			$save['gprint_id']                    = form_input_validate(get_filter_request_var('gprint_id'), 'gprint_id', '', true, 3);
			$save['sequence']                     = get_filter_request_var('sequence');

			if (!is_error_message()) {
				$graph_template_item_id = sql_save($save, 'graph_templates_item');

				if ($graph_template_item_id) {
					raise_message(1);
				} else {
					raise_message(2);
				}
			}

			set_request_var('sequence', 0);
		}

		if (is_error_message()) {
			header('Location: ' . CACTI_PATH_URL . 'aggregate_graphs.php?action=item_edit&graph_template_item_id=' . (empty($graph_template_item_id) ? get_filter_request_var('graph_template_item_id') : $graph_template_item_id) . '&id=' . get_filter_request_var('local_graph_id'));

			exit;
		} else {
			header('Location: ' . CACTI_PATH_URL . 'aggregate_graphs.php?action=edit&id=' . get_filter_request_var('local_graph_id'));

			exit;
		}
	}
}

/* -----------------------
	save aggregate graph item
	This saves any overrides to item properties from graph template item.
	Inserting new items here is not possible. Just editing existing ones.
   ----------------------- */
function form_save_aggregate() {
	global $config;

	if (!isset_request_var('save_component_item')) {
		return;
	}

	// two possible tables to save to - aggregate template or aggregate graph
	// with different key column combination
	$save_to          = 'aggregate_graph_templates_item';
	$key_cols         = array('aggregate_template_id', 'graph_templates_item_id');
	$location_success = 'aggregate_templates.php?action=edit&id=' . get_filter_request_var('aggregate_template_id');
	$location_failure = 'aggregate_graphs.php?action=item_edit&aggregate_template_id=' . get_filter_request_var('aggregate_template_id') . '&id=' . get_filter_request_var('graph_template_item_id');

	if (get_filter_request_var('aggregate_graph_id') > 0) {
		$save_to          = 'aggregate_graphs_graph_item';
		$key_cols         = array('aggregate_graph_id', 'graph_templates_item_id');
		$location_success = 'aggregate_graphs.php?action=edit&id=' . get_filter_request_var('local_graph_id');
		$location_failure = 'aggregate_graphs.php?action=item_edit&aggregate_graph_id=' . get_filter_request_var('aggregate_graph_id') . '&id=' . get_filter_request_var('graph_template_item_id');
	}

	// only some properties can be saved here
	$save                    = array();
	$save['t_graph_type_id'] = form_input_validate((isset_request_var('t_graph_type_id') ? get_nfilter_request_var('t_graph_type_id') : ''), 't_graph_type_id', '', true, 3);
	$save['graph_type_id']   = form_input_validate((($save['t_graph_type_id']) ? get_filter_request_var('graph_type_id') : 0), 'graph_type_id', '', true, 3);
	$save['t_cdef_id']       = form_input_validate((isset_request_var('t_cdef_id') ? get_nfilter_request_var('t_cdef_id') : ''), 't_cdef_id', '', true, 3);
	$save['cdef_id']         = form_input_validate((($save['t_cdef_id']) ? get_filter_request_var('cdef_id') : 0), 'cdef_id', '', true, 3);

	if (!is_error_message()) {
		// sql_save will not give useful return values when row key is
		// composed from multiple columns. need to manually build query
		$sql_set = 'SET ';

		foreach ($save as $key => $value) {
			$sql_set .= $key . '=' . db_qstr($value) . ', ';
		}
		$sql_set = substr($sql_set, 0, -2);

		$sql_where = 'graph_templates_item_id = ' . get_filter_request_var('graph_template_item_id') . ' AND ';

		if ($save_to == 'aggregate_graph_templates_item') {
			$sql_where .= 'aggregate_template_id=' . get_filter_request_var('aggregate_template_id');
		} else {
			$sql_where .= 'aggregate_graph_id=' . get_filter_request_var('aggregate_graph_id');
		}
		$sql     = "UPDATE $save_to $sql_set WHERE $sql_where LIMIT 1";
		$success = db_execute($sql);

		if ($success) {
			raise_message(1);
		} else {
			raise_message(2);
		}

		// update existing graphs with the changes to this item
		if ($save_to == 'aggregate_graphs_graph_item') {
			push_out_aggregates(0, get_filter_request_var('local_graph_id'));
		} elseif ($save_to == 'aggregate_graph_templates_item') {
			push_out_aggregates(get_filter_request_var('aggregate_template_id'));
		}
	}

	if (is_error_message()) {
		header('Location: ' . CACTI_PATH_URL . $location_failure);

		exit;
	} else {
		header('Location: ' . CACTI_PATH_URL . $location_success);

		exit;
	}
}

function item_movedown() {
	global $graph_item_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('local_graph_id');
	/* ==================================================== */

	$arr     = get_graph_group(get_request_var('id'));
	$next_id = get_graph_parent(get_request_var('id'), 'next');

	if ((!empty($next_id)) && (isset($arr[get_request_var('id')]))) {
		move_graph_group(get_request_var('id'), $arr, $next_id, 'next');
	} elseif (preg_match('/(GPRINT|VRULE|HRULE|COMMENT)/', $graph_item_types[db_fetch_cell_prepared('SELECT graph_type_id FROM graph_templates_item WHERE id = ?', array(get_request_var('id')))])) {
		move_item_down('graph_templates_item', get_request_var('id'), 'local_graph_id=' . get_request_var('local_graph_id'));
	}
}

function item_moveup() {
	global $graph_item_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('local_graph_id');
	/* ==================================================== */

	$arr         = get_graph_group(get_request_var('id'));
	$previous_id = get_graph_parent(get_request_var('id'), 'previous');

	if ((!empty($previous_id)) && (isset($arr[get_request_var('id')]))) {
		move_graph_group(get_request_var('id'), $arr, $previous_id, 'previous');
	} elseif (preg_match('/(GPRINT|VRULE|HRULE|COMMENT)/', $graph_item_types[db_fetch_cell_prepared('SELECT graph_type_id FROM graph_templates_item WHERE id = ?', array(get_request_var('id')))])) {
		move_item_up('graph_templates_item', get_request_var('id'), 'local_graph_id=' . get_request_var('local_graph_id'));
	}
}

function item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM graph_templates_item WHERE id = ?', array(get_request_var('id')));
}

function item_edit() {
	global $config, $struct_graph_item, $graph_item_types, $consolidation_functions;

	// Remove filter item
	unset($struct_graph_item['data_template_id']);

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('local_graph_id');
	get_filter_request_var('aggregate_graph_id');
	get_filter_request_var('aggregate_template_id');
	/* ==================================================== */

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('local_graph_id', 'sess_local_graph_id', '');

	$id = (!isempty_request_var('id') ? '&id=' . get_request_var('id') : '');

	/* this editor can work on aggregate template graph item or aggregate item */
	if (!isempty_request_var('aggregate_graph_id')) {
		$id_field   = 'aggregate_graph_id';
		$table_name = 'aggregate_graphs_graph_item';
		$page_name  = 'aggregate_graphs.php';
	} elseif (!isempty_request_var('aggregate_template_id')) {
		$id_field   = 'aggregate_template_id';
		$table_name = 'aggregate_graph_templates_item';
		$page_name  = 'aggregate_templates.php';
	} else {
		/* TODO redirect somewhere and show an error message, rather than die */
		die('We should have redirected somewhere but we ended up here instead' . PHP_EOL);
	}

	if (!isempty_request_var('id')) {
		$template_item = db_fetch_row_prepared('SELECT *
			FROM graph_templates_item
			WHERE id = ?',
			array(get_request_var('id')));
	}

	/* override some template_item values from aggregate tables */
	$item_overrides = db_fetch_row_prepared("SELECT *
		FROM $table_name
		WHERE $id_field = ?
		AND graph_templates_item_id = ?",
		array(get_request_var($id_field), get_request_var('id')));

	if (cacti_sizeof($item_overrides) == 0) {
		/* this item is not currently in aggregate tables
		 * item editor will not work in this case, so let's
		 * save it now
		 */
		$item_new = array(
			$id_field                 => get_request_var($id_field),
			'graph_templates_item_id' => get_request_var('id'),
			'sequence'                => $template_item['sequence']
		);

		aggregate_graph_items_save(array($item_new), $table_name);

		$item_overrides = db_fetch_row_prepared("SELECT *
			FROM $table_name
			WHERE $id_field = ?
			AND graph_templates_item_id = ?",
			array(get_request_var($id_field), get_request_var('id')));
	}

	foreach (array_keys($template_item) as $field_name) {
		if (!array_key_exists($field_name, $item_overrides)) {
			continue;
		}
		# t_<field_name> column in aggregate table must be "on" to override
		if (array_key_exists('t_'.$field_name, $item_overrides) && $item_overrides['t_'.$field_name] == 'on') {
			$template_item[$field_name] = $item_overrides[$field_name];
		}
	}

	html_start_box(__('Override Values for Graph Item'), '100%', true, '3', 'center', '');

	$form_array = array();

	foreach ($struct_graph_item as $field_name => $field_array) {
		$form_array += array($field_name => $struct_graph_item[$field_name]);

		/* should we draw an override checkbox */
		if (array_key_exists('t_' . $field_name, $item_overrides)) {
			$form_array[$field_name]['sub_checkbox']  = array(
				'name'          => 't_' . $field_name,
				'friendly_name' => __esc('Override this Value') . '<br>',
				'value'         => ($item_overrides['t_'.$field_name] == 'on' ? 'on' : ''),
				'on_change'     => 'toggleFieldEnabled(this.id);'
			);
		}

		$form_array[$field_name]['value']   = (isset($template_item) ? $template_item[$field_name] : '');
		$form_array[$field_name]['form_id'] = (isset($template_item) ? $template_item['id'] : '0');
	}

	draw_edit_form(
		array(
			'config' => array(
				'post_to' => CACTI_PATH_URL . 'aggregate_graphs.php'
			),
			'fields' => $form_array
		)
	);

	form_hidden_box('local_graph_id', get_request_var('local_graph_id'), '0');
	form_hidden_box('graph_template_item_id', (isset($template_item) ? $template_item['id'] : '0'), '');
	form_hidden_box('local_graph_template_item_id', (isset($template_item) ? $template_item['local_graph_template_item_id'] : '0'), '');
	form_hidden_box('graph_template_id', (isset($template_item) ? $template_item['graph_template_id'] : '0'), '');
	form_hidden_box('sequence', (isset($template_item) ? $template_item['sequence'] : '0'), '');
	form_hidden_box('_graph_type_id', (isset($template_item) ? $template_item['graph_type_id'] : '0'), '');
	form_hidden_box('save_component_item', '1', '');
	form_hidden_box('invisible_alpha', $form_array['alpha']['value'], 'FF');
	form_hidden_box('rrdtool_version', get_rrdtool_version(), '');
	form_hidden_box('aggregate_graph_id', get_request_var('aggregate_graph_id'), '0');
	form_hidden_box('aggregate_template_id', get_request_var('aggregate_template_id'), '0');

	html_end_box(true, true);

	form_save_button(CACTI_PATH_URL . "$page_name?action=edit&id=" . get_request_var('local_graph_id'));

	//Now we need some javascript to make it dynamic
	?>
	<script type='text/javascript'>

	$(function() {
		dynamic();
		setFieldsDisabled();
	});

	function dynamic() {
		$('#alpha').prop('disabled', true);
		if ($('#color_id').val() != 0) {
			$('#alpha').prop('disabled', true);
		}
	}

	function changeColorId() {
		if ($('#color_id').attr('selectedIndex') != 0) {
			$('#alpha').prop('disabled', true);
		}
	}

	// disable all items with sub-checkboxes except
	// where sub-checkbox checked
	function setFieldsDisabled() {
		$('input[id^="t_"]').each(function() {
			if (!$(this).is(':checked')) {
				var fieldId = $(this).attr('id').substr(2);

				$('#'+fieldId).prop('disabled', true);
				$('#'+fieldId).addClass('ui-state-disabled');

				if ($('#'+fieldId).selectmenu('instance')) {
					$('#'+fieldId).selectmenu('disable');
				}
			}
		});
	}

	// enable or disable form field based on state of corresponding checkbox
	function toggleFieldEnabled(toggleFieldId) {
		fieldId  = toggleFieldId.substr(2);

		if ($('#'+fieldId).hasClass('ui-state-disabled')) {
			$('#'+fieldId).prop('disabled', false).removeClass('ui-state-disabled');

			if ($('#'+fieldId).selectmenu('instance')) {
				$('#'+fieldId).selectmenu('enable');
			}
		} else {
			$('#'+fieldId).prop('disabled', true).addClass('ui-state-disabled');

			if ($('#'+fieldId).selectmenu('instance')) {
				$('#'+fieldId).selectmenu('disable');
			}
		}
	}

	</script>
	<?php
}

function form_actions() {
	global $graph_actions, $agg_item_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* we are performing two set's of actions here */
	$graph_actions += $agg_item_actions;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_request_var('drp_action') == '1') { // delete
				api_aggregate_remove_multi($selected_items);
			} elseif (get_request_var('drp_action') == '2') { // migrate to template
				api_aggregate_convert_template($selected_items);
			} elseif (get_request_var('drp_action') == '3') { // create aggregate from aggregate
				$aggregate_name = get_request_var('aggregate_name');
				api_aggregate_create($aggregate_name, $selected_items);
			} elseif (get_request_var('drp_action') == '4') { // add graphs to report
				$good = true;

				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					if (!reports_add_graphs(get_filter_request_var('report_id'), $selected_items[$i], get_request_var('timespan'), get_request_var('align'))) {
						raise_message('reports_add_error');
						$good = false;

						break;
					}
				}

				if ($good) {
					raise_message('reports_graphs_added');
				}
			} elseif (get_request_var('drp_action') == '5') { // Convert to a normal graph
				api_aggregate_convert_to_graph($selected_items);

				header('Location: aggregate_graphs.php');

				exit;
			} elseif (get_request_var('drp_action') == '10') { // associate with aggregate
				$local_graph_id = get_filter_request_var('local_graph_id');
				api_aggregate_associate($local_graph_id, $selected_items);

				header('Location: aggregate_graphs.php?action=edit&tab=items&id=' . $local_graph_id);

				exit;
			} elseif (get_request_var('drp_action') == '11') { // dis-associate with aggregate
				$local_graph_id = get_filter_request_var('local_graph_id');
				api_aggregate_disassociate($local_graph_id, $selected_items);

				header('Location: aggregate_graphs.php?action=edit&tab=items&id=' . $local_graph_id);

				exit;
			} elseif (preg_match('/^tr_([0-9]+)$/', get_request_var('drp_action'), $matches)) { // place on tree
				get_filter_request_var('tree_id');
				get_filter_request_var('tree_item_id');

				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					api_tree_item_save(0, get_nfilter_request_var('tree_id'), TREE_ITEM_TYPE_GRAPH, get_nfilter_request_var('tree_item_id'), '', $selected_items[$i], 0, 0, 0, 0, false);
				}
			}
		}

		header('Location: aggregate_graphs.php');

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

			$graph_list .= '<li>' . html_escape(get_graph_title($matches[1])) . '</li>';
			$graph_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	form_start('aggregate_graphs.php');

	html_start_box($graph_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	$save_html = '';

	if (isset($graph_array) && cacti_sizeof($graph_array)) {
		if (get_request_var('drp_action') == '1') { // delete
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to delete the following Aggregate Graph(s).') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Delete Graph(s)') . "'>";
		} elseif (get_request_var('drp_action') == '2') { // migrate to aggregate
			/* determine the common graph template if any */
			foreach ($_POST as $var => $val) {
				if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
					$local_graph_ids[] = $matches[1];
				}
			}
			$lgid = implode(',', $local_graph_ids);

			/* for whatever reason,  subquery performance in mysql is sub-optimal.  Therefore, let's do this
			 * as a few queries instead.
			 */
			$task_items = array_rekey(db_fetch_assoc("SELECT DISTINCT task_item_id
				FROM graph_templates_item
				WHERE local_graph_id IN($lgid)"), 'task_item_id', 'task_item_id');

			if (cacti_sizeof($task_items)) {
				$task_items = implode(',', $task_items);

				$graph_templates = db_fetch_assoc("SELECT DISTINCT graph_template_id
					FROM graph_templates_item
					WHERE task_item_id IN ($task_items) AND graph_template_id>0");
			} else {
				$graph_templates = array();
			}

			if (cacti_sizeof($graph_templates) > 1) {
				print "<tr>
					<td class='textArea'>
						<p>" . __('The selected Aggregate Graphs represent elements from more than one Graph Template.') . '</p>
						<p>' . __('In order to migrate the Aggregate Graphs below to a Template based Aggregate, they must only be using one Graph Template.  Please press \'Return\' and then select only Aggregate Graph that utilize the same Graph Template.') . "</p>
						<div class='itemlist'><ul>$graph_list</ul></div>
					</td>
				</tr>";

				$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Return') . "' onClick='cactiReturnTo()'>";
			} elseif (cacti_sizeof($graph_templates) == 0) {
				print "<tr>
					<td class='textArea'>
						<p>" . __('The selected Aggregate Graphs does not appear to have any matching Aggregate Templates.') . '</p>
						<p>' . __('In order to migrate the Aggregate Graphs below use an Aggregate Template, one must already exist.  Please press \'Return\' and then first create your Aggregate Template before retrying.') . "</p>
						<div class='itemlist'><ul>$graph_list</ul></div>
					</td>
				</tr>";
			} else {
				$graph_template = $graph_templates[0]['graph_template_id'];

				$aggregate_templates = db_fetch_assoc_prepared(
					'SELECT id, name
					FROM aggregate_graph_templates
					WHERE graph_template_id = ?
					ORDER BY name',
					array($graph_template)
				);

				if (cacti_sizeof($aggregate_templates)) {
					print "<tr>
						<td class='textArea' colspan='2'>
							<p>" . __('Click \'Continue\' and the following Aggregate Graph(s) will be migrated to use the Aggregate Template that you choose below.') . "</p>
							<div class='itemlist'><ul>$graph_list</ul></div>
						</td>
					</tr>";

					print "<tr>
						<td class='textArea' width='170'>" . __('Aggregate Template:') . "</td>
						<td>
							<select name='aggregate_template_id'>";

					html_create_list($aggregate_templates, 'name', 'id', $aggregate_templates[0]['id']);

					print '</select>
						</td>
					</tr>';

					$save_html = "<tr><td colspan='2' class='right'><input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Delete Graph(s)') . "'></td></tr>";
				} else {
					print "<tr>
						<td class='textArea'>
							<p>" . __('There are currently no Aggregate Templates defined for the selected Legacy Aggregates.') . '</p>
							<p>' . __('In order to migrate the Aggregate Graphs below to a Template based Aggregate, first create an Aggregate Template for the Graph Template \'%s\'.', db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE id = ?', array($graph_template))) . '</p>
							<p>' . __('Please press \'Return\' to continue.') . "</p>
							<div class='itemlist'><ul>$graph_list</ul></div>
						</td>
					</tr>";

					$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Return') . "' onClick='cactiReturnTo()'>";
				}
			}
		} elseif (get_request_var('drp_action') == '3') { // create aggregate from aggregates
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to combine the following Aggregate Graph(s) into a single Aggregate Graph.') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
				</td>
			</tr>";

			print "	<tr><td class='textArea' width='170'>" . __('Aggregate Name:') . '</td></tr>';
			print "	<tr><td class='textArea'><input type='text' class='ui-state-default ui-corner-all' name='aggregate_name' size='40' value='" . __esc('New Aggregate') . "'></td></tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Delete Graph(s)') . "'>";
		} elseif (get_request_var('drp_action') == '4') {
			global $alignment, $graph_timespans;

			$reports = db_fetch_assoc_prepared(
				'SELECT id, name
				FROM reports
				WHERE user_id = ?
				ORDER BY name',
				array($_SESSION[SESS_USER_ID])
			);

			if (cacti_sizeof($reports)) {
				print "<tr>
					<td class='textArea'>
						<p>" . __('Click \'Continue\' to add the selected Graphs to the Report below.') . "</p>
						<div class='itemlist'><ul>$graph_list</ul></div>
					</td>
				</tr>
				<tr><td>" . __('Report Name') . '<br>';
				form_dropdown('report_id', $reports, 'name', 'id', '', '', '0');
				print '</td></tr>';

				print '<tr><td>' . __('Timespan') . '<br>';
				form_dropdown('timespan', $graph_timespans, '', '', '', '', read_user_setting('default_timespan'));
				print '</td></tr>';

				print '<tr><td>' . __('Align') . '<br>';
				form_dropdown('align', $alignment, '', '', '', '', REPORTS_ALIGN_CENTER);
				print '</td></tr>';

				$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Add Graphs to Report') . "'>";
			} else {
				print "<tr><td class='even'><span class='textError'>" . __('You currently have no reports defined.') . '</span></td></tr>';
				$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Return') . "' onClick='cactiReturnTo()'>";
			}
		} elseif (get_request_var('drp_action') == '5') { // convert to a normal graph
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to convert the following Aggregate Graph(s) into a normal Graph.') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Convert to normal Graph(s)') . "'>";
		} elseif (get_request_var('drp_action') == '10') { // associate with aggregate
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to associate the following Graph(s) with the Aggregate Graph.') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Associate Graph(s)') . "'>";
		} elseif (get_request_var('drp_action') == '11') { // dis-associate with aggregate
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to disassociate the following Graph(s) from the Aggregate.') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Dis-Associate Graph(s)') . "'>";
		} elseif (preg_match('/^tr_([0-9]+)$/', get_request_var('drp_action'), $matches)) { // place on tree
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to place the following Aggregate Graph(s) under the Tree Branch.') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
					<p>" . __('Destination Branch:') . '<br>';
			grow_dropdown_tree($matches[1], '0', 'tree_item_id', '0');
			print "</p>
				</td>
			</tr>
			<input type='hidden' name='tree_id' value='" . html_escape($matches[1]) . "'>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Place Graph(s) on Tree') . "'>";
		}
	} else {
		raise_message(40);
		header('Location: aggregate_graphs.php');

		exit;
	}

	print "	<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='local_graph_id' value='" . (isset_request_var('local_graph_id') ? get_nfilter_request_var('local_graph_id') : 0) . "'>
			<input type='hidden' name='selected_items' value='" . (isset($graph_array) ? serialize($graph_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box(false);

	form_end();

	bottom_footer();
}

function item() {
	global $consolidation_functions, $graph_item_types, $struct_graph_item;

	// Remove filter item
	unset($struct_graph_item['data_template_id']);

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (isempty_request_var('id')) {
		$template_item_list = array();

		$header_label = __('Graph Items [new]');
	} else {
		$template_item_list = db_fetch_assoc_prepared(
			'SELECT
			gti.id, gti.text_format, gti.value, gti.hard_return, gti.graph_type_id,
			gti.consolidation_function_id, dtr.data_source_name, gti.alpha,
			cdef.name AS cdef_name, vdef.name AS vdef_name, colors.hex,
			gtgp.name AS gprint_name
			FROM graph_templates_item AS gti
			LEFT JOIN data_template_rrd AS dtr
			ON gti.task_item_id=dtr.id
			LEFT JOIN data_local AS dl
			ON dtr.local_data_id=dl.id
			LEFT JOIN data_template_data AS dtd
			ON dl.id=dtd.local_data_id
			LEFT JOIN cdef
			ON gti.cdef_id=cdef.id
			LEFT JOIN vdef
			ON gti.vdef_id=vdef.id
			LEFT JOIN graph_templates_gprint gtgp
			ON gti.gprint_id=gtgp.id
			LEFT JOIN colors
			ON gti.color_id=colors.id
			WHERE gti.local_graph_id = ?
			ORDER BY gti.sequence',
			array(get_request_var('id'))
		);

		$header_label = __esc('Graph Items [edit: %s]', get_graph_title(get_request_var('id')));
	}

	$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
		FROM graph_local
		WHERE id = ?', array(get_request_var('id')));

	if (empty($graph_template_id)) {
		$add_text = 'aggregate_graphs.php?action=item_edit&local_graph_id=' . get_request_var('id');
	} else {
		$add_text = '';
	}

	html_start_box($header_label, '100%', '', '3', 'center', $add_text);
	draw_graph_items_list($template_item_list, 'aggregate_graphs.php', 'local_graph_id=' . get_request_var('id'), (empty($graph_template_id) ? false : true));
	html_end_box(false);
}

/* ------------------------------------
	graph - Graphs
   ------------------------------------ */

function graph_edit() {
	global $config, $struct_graph, $struct_aggregate_graph, $image_types, $consolidation_functions, $graph_item_types, $struct_graph_item;

	// Remove filter item
	unset($struct_graph_item['data_template_id']);

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	/* purge any old graphs */
	aggregate_prune_graphs(get_request_var('id'));

	if (isset_request_var('reset')) {
		$_SESSION['aggregate_referer'] = 'aggregate_graphs.php';
	} elseif (isset($_SERVER['HTTP_REFERER']) && !substr_count($_SERVER['HTTP_REFERER'], 'aggregate_graphs.php')) {
		$_SESSION['aggregate_referer'] = $_SERVER['HTTP_REFERER'];
	} elseif (isset($_SERVER['HTTP_REFERER']) && !isset($_SESSION['aggregate_referer'])) {
		$_SESSION['aggregate_referer'] = $_SERVER['HTTP_REFERER'];
	}

	$referer = isset($_SESSION['aggregate_referer']) ? $_SESSION['aggregate_referer'] : 'aggregate_graphs.php';

	$use_graph_template = false;
	$aginfo             = array();
	$graphs             = array();

	if (!isempty_request_var('id')) {
		$graphs = db_fetch_row_prepared(
			'SELECT *
			FROM graph_templates_graph
			WHERE local_graph_id = ?',
			array(get_request_var('id'))
		);

		if (cacti_sizeof($graphs)) {
			$aginfo = db_fetch_row_prepared(
				'SELECT *
				FROM aggregate_graphs
				WHERE local_graph_id = ?',
				array($graphs['local_graph_id'])
			);

			if ($aginfo['title_format'] == '') {
				$aginfo['title_format'] = get_graph_title($graphs['local_graph_id']);
			}

			$header_label = __esc('[edit: %s]', get_graph_title(get_request_var('id')));
		} else {
			$header_label = __('Aggregate Graph does not Exist');
		}
	}

	if (cacti_sizeof($aginfo)) {
		if ($aginfo['aggregate_template_id'] > 0) {
			$template = db_fetch_row_prepared(
				'SELECT *
				FROM aggregate_graph_templates
				WHERE id = ?',
				array($aginfo['aggregate_template_id'])
			);
		} else {
			$template = $aginfo;
		}

		$aggregate_tabs = array(
			'details' => __('Details'),
			'items'   => __('Items'),
			'preview' => __('Preview')
		);
	} elseif (cacti_sizeof($graphs)) {
		$template       = array();
		$aggregate_tabs = array(
			'details' => __('Details'),
			'preview' => __('Preview')
		);
	} else {
		raise_message('missing_aggregate', __('Aggregate Graphs Accessed does not Exist'), MESSAGE_LEVEL_ERROR);

		if (isset($_SERVER['HTTP_REFERER'])) {
			$referer = $_SERVER['HTTP_REFERER'];
			header('Location: ' . $referer);
		} else {
			header('Location: aggregate_graphs.php');
		}

		exit;
	}

	/* ================= input validation and session storage ================= */
	if (isset_request_var('tab')) {
		switch (get_nfilter_request_var('tab')) {
			case 'details':
			case 'items':
			case 'preview':
				$_SESSION['agg_tab'] = get_nfilter_request_var('tab');
				set_request_var('tab', get_nfilter_request_var('tab'));

				break;

			default:
				if (isset($_SESSION['agg_tab'])) {
					set_request_var('tab', $_SESSION['agg_tab']);
				} else {
					$_SESSION['agg_tab'] = 'details';
					set_request_var('tab', 'details');
				}
		}
	} elseif (isset($_SESSION['agg_tab'])) {
		set_request_var('tab', $_SESSION['agg_tab']);
	} else {
		set_request_var('tab', 'details');
	}
	/* ================= input validation ================= */

	$current_tab = get_nfilter_request_var('tab');

	/* draw the categories tabs on the top of the page */
	print "<div class='tabs'>";
	print "<div class='aggtabs'><nav><ul role='tablist'>";

	$i = 0;

	if (cacti_sizeof($aggregate_tabs)) {
		foreach (array_keys($aggregate_tabs) as $tab_short_name) {
			if ($tab_short_name == 'details' || (!isempty_request_var('id'))) {
				print "<li class='subTab'><a id='agg_" . $tab_short_name . "' class='tab " . ($tab_short_name == $current_tab ? "selected'" : "'") . ($tab_short_name == 'preview' ? ' style="display:none"' : '') .
					" href='" . html_escape(CACTI_PATH_URL . 'aggregate_graphs.php?action=edit&id=' . get_request_var('id') . "&tab=$tab_short_name") . "'>" . $aggregate_tabs[$tab_short_name] . '</a></li>';
			}

			$i++;
		}
	}
	print '</ul>';

	/* handle debug mode */
	if (isset_request_var('debug')) {
		if (get_filter_request_var('debug') == '0') {
			kill_session_var('graph_debug_mode');
		} elseif (get_filter_request_var('debug') == '1') {
			$_SESSION['graph_debug_mode'] = true;
		}
	}

	if (isset($_SESSION['graph_debug_mode'])) {
		$message = __('Turn Off Graph Debug Mode');
	} else {
		$message = __('Turn On Graph Debug Mode');
	}

	if (!isempty_request_var('id') && $current_tab == 'preview') {
		print "<ul style='float:right;'><li><a class='pic' href='" . html_escape('aggregate_graphs.php?action=edit&id=' . get_request_var('id') . '&tab=' . get_request_var('tab') .  '&debug=' . (isset($_SESSION['graph_debug_mode']) ? '0' : '1')) . "'>" . $message . '</a></li></ul></nav></div></div>';
	} elseif (!isempty_request_var('id') && $current_tab == 'details' && (!cacti_sizeof($template))) {
		print "<ul style='float:right;'><li><a id='toggle_items' class='pic' href='#'>" . __('Show Item Details') . '</a></li></ul></nav></div></div>';
	} else {
		print '</nav></div></div>';
	}

	if (!isempty_request_var('id') && $current_tab == 'preview') {
		$graph = db_fetch_row_prepared(
			'SELECT *
			FROM graph_local
			WHERE id = ?',
			array(get_request_var('id'))
		);

		if (!cacti_sizeof($graph)) {
			html_start_box(__('Aggregate Preview Does Not Exist'), '100%', '', '3', 'center', '');
			print "<tr><td id='imagewindow' class='center'>" . __('Aggregate Graph does not Exist') . '</tr></tr>';
			html_end_box(false);
			raise_message('noaggregate', __('Aggregate Graph does not Exist'), MESSAGE_LEVEL_ERROR);

			return false;
		}

		html_start_box(__('Aggregate Preview %s', $header_label), '100%', '', '3', 'center', '');
		?>
		<tr>
			<td id='imagewindow' class='center'>
				<img src='<?php print html_escape(CACTI_PATH_URL . 'graph_image.php?action=edit&disable_cache=1&local_graph_id=' . get_request_var('id') . '&rra_id=' . read_user_setting('default_rra_id') . '&random=' . mt_rand()); ?>' alt=''>
				<script type='text/javascript'>
					$(function() {
						$('#agg_preview').show();
					});
				</script>
			</td>
		</tr>
		<?php
				if (isset($_SESSION['graph_debug_mode']) && isset_request_var('id')) {
					$graph_data_array['output_flag']  = RRDTOOL_OUTPUT_STDERR;
					$graph_data_array['print_source'] = 1;
					$null_param                       = array();
					?>
			<tr>
				<td id='rrdtoolinfo' class='left' style='padding-left:15px;max-width:900px;overflow:scroll'>
					<div style='overflow:auto;'>
						<span class='textInfo'><?php print __('RRDtool Command:'); ?></span><br>
						<?php print @rrdtool_function_graph(get_request_var('id'), 1, $graph_data_array, '', $null_param, $_SESSION[SESS_USER_ID]); ?>
						<span class='textInfo'><?php print __('RRDtool Says:'); ?></span><br><?php unset($graph_data_array['print_source']); ?>
						<pre class='monoSpace tableRow left'><?php print($config['poller_id'] == 1 ? @rrdtool_function_graph(get_request_var('id'), 1, $graph_data_array, '', $null_param, $_SESSION[SESS_USER_ID]) : __esc('Not Checked')); ?></pre>
					</div>
					<script type='text/javascript'>
						$(function() {
							var rrdwidth = $(window).width() - $('.cactiConsoleNavigationArea').width();
							$('#agg_preview').show();
							$('#rrdtoolinfo, #imagewindow').css('max-width', rrdwidth);
						});
					</script>
				</td>
			</tr>
		<?php
				}
		?>
		<?php
		html_end_box(false);
	}

	if (!isempty_request_var('id') && $current_tab == 'items') {
		aggregate_items();
		bottom_footer();

		exit;
	}

	form_start('aggregate_graphs.php', 'template_edit');

	/* we will show the templated representation only when there is a template and propagation is enabled */
	if (!isempty_request_var('id') && $current_tab == 'details') {
		if (cacti_sizeof($template)) {
			print "<div id='templated'>";

			html_start_box(__('Aggregate Graph %s', $header_label), '100%', true, '3', 'center', '');

			$helper_string = '|host_description|';

			if (isset($template)) {
				$data_query = db_fetch_cell_prepared(
					'SELECT snmp_query_id
					FROM snmp_query_graph
					WHERE graph_template_id = ?',
					array($template['graph_template_id'])
				);

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
			$struct_aggregate_graph['suggestions'] = array(
				'method'        => 'other',
				'friendly_name' => __('Prefix Replacement Values'),
				'description'   => __('You may use these replacement values for the Prefix in the Aggregate Graph'),
				'value'         => $helper_string
			);

			/* add template propagation to the structure */
			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => inject_form_variables($struct_aggregate_graph, (isset($aginfo) ? $aginfo : array()))
				)
			);

			html_end_box(true, true);

			if (isset($template)) {
				draw_aggregate_graph_items_list(0, $template['graph_template_id'], $aginfo);
			}

			form_hidden_box('save_component_template', '1', '');

			?>
			<script type='text/javascript'>
				var templated_selectors = [
					'#gprint_prefix',
					'#gprint_format',
					'#graph_type',
					'#total',
					'#total_type',
					'#total_prefix',
					'#order_type',
					'select[id^="agg_color"]',
					'input[id^="agg_total"]',
					'input[id^="agg_skip"]',
					'#image_format_id',
					'#height',
					'#width',
					'#slope_mode',
					'#auto_scale',
					'#auto_scale_opts',
					'#auto_scale_log',
					'#scale_log_units',
					'#auto_scale_rigid',
					'#auto_padding',
					'#export',
					'#upper_limit',
					'#lower_limit',
					'#base_value',
					'#unit_value',
					'#unit_exponent_value',
					'#alt_y_grid',
					'#right_axis',
					'#right_axis_label',
					'#right_axis_format',
					'#right_axis_formatter',
					'#left_axis_format',
					'#left_axis_formatter',
					'#no_gridfit',
					'#unit_length',
					'#tab_width',
					'#dynamic_labels',
					'#force_rules_legend',
					'#legend_position',
					'#legend_direction',
					'#vertical_label'
				];

				$(function() {
					if ($('input[id^="agg_total"]').is(':checked') || $('#template_propogation').is(':checked')) {
						$('#agg_preview').show();
					}

					if ($('#template_propogation').is(':checked')) {
						for (var i = 0; i < templated_selectors.length; i++) {
							$(templated_selectors[i]).prop('disabled', true).addClass('ui-state-disabled');
							if ($(templated_selectors[i]).selectmenu('instance')) {
								$(templated_selectors[i]).selectmenu('disable');
							}
						}
					} else {
						$('#row_template_propogation').hide();
						$('#row_spacer0').hide();
					}

					$('input[id^="agg_total"], input[id^="agg_skip"]').click(function() {
						id = $(this).attr('id');

						if (id.indexOf('skip') > 0) {
							altId = id.replace('skip', 'total');
						} else {
							altId = id.replace('total', 'skip');
						}

						if ($('#' + id).is(':checked')) {
							$('#' + altId).prop('checked', false);
						} else {
							$('#' + altId).prop('checked', true);
						}

						changeTotals();

						updateSaveButton();
					});

					$('#total').change(function() {
						changeTotals();
					});

					$('#total_type').change(function() {
						changeTotalsType();
					});

					$('#template_propogation').change(function() {
						if (!$('#template_propogation').is(':checked')) {
							for (var i = 0; i < templated_selectors.length; i++) {
								$(templated_selectors[i]).prop('disabled', false);
							}
						} else {
							for (var i = 0; i < templated_selectors.length; i++) {
								$(templated_selectors[i]).prop('disabled', true);
							}
						}
					});

					updateSaveButton();
				});

				function updateSaveButton() {
					if ($('input[id^="agg_total"]').is(':checked')) {
						$('#submit').prop('disabled', false);

						if ($('#submit').button('instance')) {
							$('#submit').button('enable');
						}
					} else {
						$('#submit').prop('disabled', true);

						if ($('#submit').button('instance')) {
							$('#submit').button('disable');
						}
					}
				}

				function changeTotals() {
					var totalMode = $('#total').val();
					var showField = totalMode != <?= AGGREGATE_ORDER_NONE ?>;

					toggleFields({
						total_type: showField,
						total_prefix: showField,
						order_type: showField,
					});

					changeTotalsType();
				}

				function changeTotalsType() {
					if ($('#total_type').val() == <?php print AGGREGATE_TOTAL_TYPE_SIMILAR; ?>) {
						if ($('#total_prefix').val() == '') {
							$('#total_prefix').attr('value', '<?php print __('Total'); ?>');
						}
					} else if ($('#total_type').val() == <?php print AGGREGATE_TOTAL_TYPE_ALL; ?>) {
						if ($('#total_prefix').val() == '') {
							$('#total_prefix').attr('value', '<?php print __('All Items'); ?>');
						}
					}
				}
			</script>
		<?php
				print '</div>';
		}

		/* we will show the classic representation only when we are not templating */
		print "<div id='classic'>";

		?>
		<input type='hidden' id='graph_template_graph_id' name='graph_template_graph_id' value='<?php print(isset($graphs) ? $graphs['id'] : '0'); ?>'>
		<input type='hidden' id='local_graph_template_graph_id' name='local_graph_template_graph_id' value='<?php print(isset($graphs) ? $graphs['local_graph_template_graph_id'] : '0'); ?>'>
		<?php

		/* graph item list goes here */
		if (empty($graphs['graph_template_id']) && cacti_sizeof($template) == 0) {
			item();
		}

		if (empty($graphs['graph_template_id'])) {
			html_start_box(__('Graph Configuration'), '100%', true, '3', 'center', '');

			$form_array = array();

			foreach ($struct_graph as $field_name => $field_array) {
				if ($field_array['method'] != 'spacer') {
					if ($field_name != 'title') {
						$form_array += array($field_name => $struct_graph[$field_name]);

						$form_array[$field_name]['value']   = (isset($graphs) ? $graphs[$field_name] : '');
						$form_array[$field_name]['form_id'] = (isset($graphs) ? $graphs['id'] : '0');

						if (!(($use_graph_template == false) || ($graphs['t_' . $field_name] == 'on'))) {
							$form_array[$field_name]['method']      = 'template_' . $form_array[$field_name]['method'];
							$form_array[$field_name]['description'] = '';
						}
					}
				} else {
					$form_array += array($field_name => $struct_graph[$field_name]);
				}
			}

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => $form_array
				)
			);

			html_end_box(true, true);
		}

		form_hidden_box('save_component_graph', '1', '');
		form_hidden_box('save_component_input', '1', '');
		form_hidden_box('rrdtool_version', get_rrdtool_version(), '');
		form_save_button($referer, 'return', 'id');

		print '</div>';

		?>
		<script type='text/javascript'>
			$(function() {
				dynamic();
				if (!$('#templated')) {
					$('#local_graph_template_graph_id').next('table').css('display', 'none');
				}
			});

			$('#toggle_items').click(function() {
				if ($('#toggle_items').is(":contains('<?php print __('Show'); ?>')")) {
					$('#local_graph_template_graph_id').next('table').css('display', '');
					$('#toggle_items').text('<?php print __('Hide Item Details'); ?>');
				} else {
					$('#local_graph_template_graph_id').next('table').css('display', 'none');
					$('#toggle_items').text('<?php print __('Show Item Details'); ?>');
				}
			});

			function dynamic() {
				if ($('#scale_log_units')) {
					$('#scale_log_units').prop('disabled', true);
					if (($('#rrdtool_version').val() != '1.0.0') &&
						($('#auto_scale_log').is(':checked'))) {
						$('#scale_log_units').prop('disabled', true);
					}
				}
			}

			function changeScaleLog() {
				if ($('#scale_log_units')) {
					$('#scale_log_units').prop('disabled', true);
					if (($('#rrdtool_version').val() != '1.0.0') &&
						($('#auto_scale_log').is(':checked'))) {
						$('#scale_log_units').prop('disabled', false);
					}
				}
			}
		</script>
	<?php
	}
}

function aggregate_items() {
	global $agg_item_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'template_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'rfilter' => array(
			'filter'  => FILTER_VALIDATE_IS_REGEX,
			'pageset' => true,
			'default' => ''
		),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'matching' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'on',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'title_cache',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		),
		'custom' => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => ''
		),
		'local_graph_ids' => array(
			'filter'  => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'pageset' => true,
			'default' => ''
		)
	);

	validate_store_request_vars($filters, 'sess_agraph_item');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	/* form the 'where' clause for our main sql query */
	if (get_request_var('rfilter') == '') {
		$sql_where = '';
	} elseif (validate_is_regex(get_request_var('rfilter'))) {
		$sql_where = "WHERE gtg.title_cache RLIKE '" . get_request_var('rfilter') . "'";
	} else {
		$filters   = explode(' ', get_request_var('rfilter'));
		$sql_where = '';
		$sql_where = aggregate_make_sql_where($sql_where, $filters, 'gtg.title_cache');
	}

	if (get_request_var('matching') != 'false') {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' (agi.local_graph_id IS NOT NULL)';
	}

	$graph_template = db_fetch_cell_prepared(
		'SELECT graph_template_id
		FROM aggregate_graphs AS ag
		WHERE ag.local_graph_id = ?',
		array(get_request_var('id'))
	);

	$aggregate_id = db_fetch_cell_prepared(
		'SELECT id
		FROM aggregate_graphs
		WHERE local_graph_id = ?',
		array(get_request_var('id'))
	);

	$total_items = db_fetch_cell_prepared(
		'SELECT COUNT(*)
		FROM aggregate_graphs_items
		WHERE aggregate_graph_id = ?',
		array($aggregate_id)
	);

	if (!empty($graph_template)) {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . " (gtg.graph_template_id=$graph_template)";
	}

	if (get_request_var('local_graph_ids') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' agi.local_graph_id IN(' . get_request_var('local_graph_ids') . ')';
	}

	$sql = "SELECT COUNT(DISTINCT gl.id) AS total
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gtg.local_graph_id=gl.id
		LEFT JOIN (
			SELECT DISTINCT local_graph_id
			FROM aggregate_graphs_items
			WHERE aggregate_graph_id=$aggregate_id) AS agi
		ON gtg.local_graph_id=agi.local_graph_id
		$sql_where";

	$total_rows = get_total_row_data($_SESSION[SESS_USER_ID], $sql, array(), 'aggregate_graph');

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$graph_list = db_fetch_assoc("SELECT
		gtg.id, gtg.local_graph_id, gtg.height, gtg.width, gtg.title_cache, agi.local_graph_id AS agg_graph_id
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gtg.local_graph_id=gl.id
		LEFT JOIN (
			SELECT DISTINCT local_graph_id
			FROM aggregate_graphs_items
			WHERE aggregate_graph_id=$aggregate_id) AS agi
		ON gtg.local_graph_id=agi.local_graph_id
		$sql_where
		$sql_order
		$sql_limit");

	?>
	<script type='text/javascript'>
		var totalItems = <?php print $total_items; ?>;

		function applyFilter() {
			strURL = 'aggregate_graphs.php' +
				'?action=edit&tab=items&id=' + $('#id').val() +
				'&rows=' + $('#rows').val() +
				'&rfilter=' + base64_encode($('#rfilter').val()) +
				'&matching=' + $('#matching').is(':checked');
			loadUrl({
				url: strURL
			})
		}

		function clearFilter() {
			strURL = 'aggregate_graphs.php?action=edit&tab=items&id=' + $('#id').val() + '&clear=true';
			loadUrl({
				url: strURL
			})
		}

		$(function() {
			if (totalItems > 0) {
				$('#agg_preview').show();
			}

			$('#clear').click(function() {
				clearFilter();
			});

			$('#rfilter').change(function() {
				applyFilter();
			});

			$('#forms').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
	</script>
	<?php

	html_start_box(__('Matching Graphs'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form id='forms' action='aggregate_graphs.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search'); ?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='rfilter' size='45' onChange='applyFilter()' value='<?php print get_request_var('rfilter'); ?>'>
						</td>
						<td>
							<?php print __('Graphs'); ?>
						</td>
						<td>
							<select id='rows' onChange='applyFilter()'>
								<option value='-1' <?php print(get_request_var('rows') == '-1' ? ' selected>' : '>') . __('Default'); ?></option>
									<?php
									if (cacti_sizeof($item_rows) > 0) {
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
						<td>
							<span>
								<input type='checkbox' id='matching' onChange='applyFilter()' <?php print(get_request_var('matching') == 'on' || get_request_var('matching') == 'true' ? ' checked' : ''); ?>>
								<label for='matching'><?php print __('Part of Aggregate'); ?></label>
							</span>
						</td>
						<td>
							<span>
								<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __esc('Go'); ?>' title='<?php print __esc('Set/Refresh Filters'); ?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' onClick='clearFilter()' value='<?php print __esc('Clear'); ?>' title='<?php print __esc('Clear Filters'); ?>'>
							</span>
						</td>
					</tr>
				</table>
				<input type='hidden' name='action' value='edit'>
				<input type='hidden' name='tab' value='items'>
				<input type='hidden' id='id' value='<?php print get_request_var('id'); ?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box(false);

	/* print checkbox form for validation */
	form_start('aggregate_graphs.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$nav = html_nav_bar('aggregate_graphs.php?action=edit&tab=items&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Graphs'), 'page', 'main');

	print $nav;

	$display_text = array(
		'title_cache'    => array('display' => __('Graph Title'), 'align' => 'left', 'sort' => 'ASC'),
		'local_graph_id' => array('display' => __('ID'), 'align' => 'right', 'sort' => 'ASC'),
		'agg_graph_id'   => array('display' => __('Included in Aggregate'), 'align' => 'left', 'sort' => 'ASC'),
		'height'         => array('display' => __('Size'), 'align' => 'right', 'sort' => 'ASC')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'aggregate_graphs.php?action=edit&id=' . get_request_var('id'));

	if (cacti_sizeof($graph_list) > 0) {
		foreach ($graph_list as $graph) {
			/* we're escaping strings here, so no need to escape them on form_selectable_cell */
			form_alternate_row('line' . $graph['local_graph_id'], true);

			if (validate_is_regex(get_request_var('rfilter'))) {
				form_selectable_cell(filter_value($graph['title_cache'], get_request_var('rfilter')), $graph['local_graph_id']);
			} else {
				form_selectable_ecell(get_request_var('rfilter') != '' ? aggregate_format_text($graph['title_cache'], get_request_var('rfilter')) : $graph['title_cache'], $graph['local_graph_id']);
			}

			form_selectable_cell($graph['local_graph_id'], $graph['local_graph_id'], '', 'right');
			form_selectable_cell(($graph['agg_graph_id'] != '' ? "<span class='associated'>" . __('Yes') . '</span>' : "<span class='notAssociated'>" . __('No') . '</span>'), $graph['local_graph_id']);
			form_selectable_ecell($graph['height'] . 'x' . $graph['width'], $graph['local_graph_id'], '', 'right');
			form_checkbox_cell($graph['title_cache'], $graph['local_graph_id']);
			form_end_row();
		}
	} else {
		print '<tr><td><em>' . __('No Graphs Found') . '</em></td></tr>';
	}

	html_end_box(false);

	/* put the nav bar on the bottom as well */
	print $nav;

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	/* draw the dropdown containing a list of available actions for this form */
	form_hidden_box('local_graph_id', get_request_var('id'), '');

	draw_actions_dropdown($agg_item_actions);

	form_end();
}

function aggregate_make_sql_where($sql_where, $items, $field) {
	if ($sql_where != '') {
		$sql_where .= ' AND (';
	} else {
		$sql_where  = 'WHERE (';
	}

	$indentation = 0;
	$termcount   = 0;

	if (cacti_sizeof($items)) {
		foreach ($items as $i) {
			$i = trim($i);

			while (substr($i, 0, 1) == '(') {
				$indentation++;
				$termcount = 0;
				$sql_where .= '(';
				$i = substr($i, 1);
			}

			$split = strpos($i, ')');

			if ($split !== false) {
				$end = trim(substr($i, $split));
				$i   = substr($i, 0, $split);
			} else {
				$end = '';
			}

			if ($i != '') {
				if (strtolower($i) == 'and') {
					$sql_where .= ' AND ';
				} elseif (strtolower($i) == 'or') {
					$sql_where .= ' OR ';
				} else {
					$sql_where .= ($termcount > 0 ? ' OR ' : '') . $field . " LIKE '%" . trim($i) . "%'";
					$termcount++;
				}
			}

			if ($end != '') {
				while (substr($end, 0, 1) == ')') {
					$indentation--;
					$termcount = 0;
					$sql_where .= ')';
					$end = trim(substr($end, 1));
				}
			}
		}
	}

	$sql_where .= ')';

	return trim($sql_where);
}

function aggregate_format_text($text, $filter) {
	$items = explode(' ', $filter);
	$tags  = array();

	foreach ($items as $i) {
		$i = trim($i);
		$i = str_replace('(', '', $i);
		$i = str_replace(')', '', $i);

		if (strtolower($i) == 'and' || strtolower($i) == 'or') {
			continue;
		}

		if (substr_count($text, $i) !== false) {
			$tagno        = rand();
			$tags[$tagno] = $i;
			$text         = str_replace($i, "<<$tagno>>", $text);
		}
	}

	if (cacti_sizeof($tags)) {
		foreach ($tags as $k => $t) {
			$text = str_replace("<<$k>>", "<span class='filteredValue'>" . html_escape($t) . '</span>', $text);
		}
	}

	return $text;
}

function aggregate_graph() {
	global $graph_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'template_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => '',
		),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'title_cache',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		),
		'local_graph_ids' => array(
			'filter'  => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'pageset' => true,
			'default' => ''
		)
	);

	validate_store_request_vars($filters, 'sess_agraph');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
		function applyFilter() {
			strURL = 'aggregate_graphs.php';
			strURL += '?rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			strURL += '&template_id=' + $('#template_id').val();
			loadUrl({
				url: strURL
			})
		}

		function clearFilter() {
			strURL = 'aggregate_graphs.php?clear=1';
			loadUrl({
				url: strURL
			})
		}

		$(function() {
			if ($('input[id^="agg_total"]').is(':checked') || $('#template_propogation').is(':checked')) {
				$('#agg_preview').show();
			}

			$('#clear').click(function() {
				clearFilter();
			});

			$('#forms').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
	</script>
	<?php

	html_start_box(__('Aggregate Graphs') . (get_request_var('local_graph_ids') != '' ? __(' [ Custom Graphs List Applied - Clear to Reset ]') : ''), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form id='forms' action='aggregate_graphs.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search'); ?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter'); ?>'>
						</td>
						<td>
							<?php print __('Template'); ?>
						</td>
						<td>
							<select id='template_id' name='template_id' onChange='applyFilter()'>
								<option value='-1' <?php if (get_request_var('template_id') == '-1') { ?> selected<?php } ?>><?php print __('Any'); ?></option>
								<option value='0' <?php if (get_request_var('template_id') == '0') { ?> selected<?php } ?>><?php print __('None'); ?></option>
								<?php
								$templates = db_fetch_assoc('SELECT DISTINCT at.id, at.name
								FROM aggregate_graph_templates AS at
								INNER JOIN aggregate_graphs AS ag
								ON ag.aggregate_template_id=at.id
								ORDER BY name');

	if (cacti_sizeof($templates) > 0) {
		foreach ($templates as $template) {
			print "<option value='" . $template['id'] . "'";

			if (get_request_var('template_id') == $template['id']) {
				print ' selected';
			}
			print '>' . html_escape($template['name']) . '</option>';
		}
	}
	?>
							</select>
						</td>
						<td>
							<?php print __('Graphs'); ?>
						</td>
						<td>
							<select id='rows' onChange='applyFilter()'>
								<option value='-1' <?php print(get_request_var('rows') == '-1' ? ' selected>' : '>') . __('Default'); ?></option>
									<?php
		if (cacti_sizeof($item_rows) > 0) {
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
						<td>
							<span>
								<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __esc('Go'); ?>' title='<?php print __esc('Set/Refresh Filters'); ?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear'); ?>' title='<?php print __esc('Clear Filters'); ?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
		</td>
	</tr>
<?php

	html_end_box();

	$sql_where = 'WHERE (gtg.graph_template_id=0 AND gl.host_id=0)';
	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (gtg.title_cache LIKE ' . db_qstr('%' . get_request_var('filter') . '%') .
			' OR ag.title_format LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	if (get_request_var('template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('template_id') == '0') {
		$sql_where .= ' AND (ag.aggregate_template_id=0 OR ag.aggregate_template_id IS NULL)';
	} elseif (!isempty_request_var('template_id')) {
		$sql_where .= ' AND ag.aggregate_template_id=' . get_request_var('template_id');
	}

	$sql = "SELECT COUNT(DISTINCT gl.id) AS total
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gtg.local_graph_id=gl.id
		INNER JOIN aggregate_graphs AS ag
		ON gtg.local_graph_id=ag.local_graph_id
		LEFT JOIN aggregate_graph_templates AS agt
		ON agt.id=ag.aggregate_template_id
		$sql_where";

	$total_rows = get_total_row_data($_SESSION[SESS_USER_ID], $sql, array(), 'aggregate_graph');

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$graph_list = db_fetch_assoc("SELECT
		gtg.id, gtg.local_graph_id, gtg.height, gtg.width, gtg.title_cache, agt.name
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gtg.local_graph_id=gl.id
		INNER JOIN aggregate_graphs AS ag
		ON gl.id=ag.local_graph_id
		LEFT JOIN aggregate_graph_templates AS agt
		ON agt.id=ag.aggregate_template_id
		$sql_where
		AND ag.id IS NOT NULL
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('aggregate_graphs.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Aggregate Graphs'), 'page', 'main');

	form_start('aggregate_graphs.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'title_cache'    => array('display' => __('Graph Title'), 'align' => 'left', 'sort' => 'ASC', 'tip' => __('The title for the Aggregate Graphs')),
		'local_graph_id' => array('display' => __('ID'), 'align' => 'right', 'sort' => 'ASC', 'tip' => __('The internal database identifier for this object')),
		'name'           => array('display' => __('Aggregate Template'), 'align' => 'left', 'sort' => 'ASC', 'tip' => __('The Aggregate Template that this Aggregate Graphs is based upon')),
		'height'         => array('display' => __('Size'), 'align' => 'right', 'sort' => 'ASC')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'aggregate_graphs.php?filter=' . get_request_var('filter'));

	if (cacti_sizeof($graph_list)) {
		foreach ($graph_list as $graph) {
			/* we're escaping strings here, so no need to escape them on form_selectable_cell */
			$template_name = html_escape($graph['name']);
			form_alternate_row('line' . $graph['local_graph_id'], true);
			form_selectable_cell(filter_value(title_trim($graph['title_cache'], read_config_option('max_title_length')), get_request_var('filter'), 'aggregate_graphs.php?action=edit&id=' . $graph['local_graph_id']), $graph['local_graph_id']);
			form_selectable_cell($graph['local_graph_id'], $graph['local_graph_id'], '', 'right');
			form_selectable_cell((empty($graph['name']) ? '<em>' . __('None') . '</em>' : filter_value($template_name, get_request_var('filter'))), $graph['local_graph_id']);
			form_selectable_ecell($graph['height'] . 'x' . $graph['width'], $graph['local_graph_id'], '', 'right');
			form_checkbox_cell($graph['title_cache'], $graph['local_graph_id']);
			form_end_row();
		}
	} else {
		print '<tr class="tableRow"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Aggregate Graphs Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($graph_list)) {
		print $nav;
	}

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($graph_actions);

	/* remove old graphs */
	purge_old_graphs();

	form_end();
}

function purge_old_graphs() {
	/* workaround to handle purged graphs */
	$old_graphs = array_rekey(db_fetch_assoc('SELECT DISTINCT local_graph_id
		FROM aggregate_graphs_items AS pagi
		LEFT JOIN graph_local AS gl ON pagi.local_graph_id=gl.id
		WHERE gl.id IS NULL AND local_graph_id>0'), 'local_graph_id',  'local_graph_id');

	if (cacti_sizeof($old_graphs)) {
		db_execute('DELETE FROM aggregate_graphs_items
			WHERE local_graph_id IN (' . implode(',', $old_graphs) . ')');
	}

	$old_aggregates = array_rekey(db_fetch_assoc('SELECT DISTINCT local_graph_id
		FROM aggregate_graphs AS pag
		LEFT JOIN graph_local AS gl
		ON pag.local_graph_id=gl.id
		WHERE gl.id IS NULL AND local_graph_id>0'), 'local_graph_id', 'local_graph_id');

	$old_agg_ids = array_rekey(db_fetch_assoc('SELECT DISTINCT pag.id
		FROM aggregate_graphs AS pag
		LEFT JOIN graph_local AS gl
		ON pag.local_graph_id=gl.id
		WHERE gl.id IS NULL'), 'id', 'id');

	if (cacti_sizeof($old_aggregates)) {
		db_execute('DELETE FROM graph_templates_item
			WHERE local_graph_id IN (' . implode(',', $old_aggregates) . ')');

		db_execute('DELETE FROM graph_templates_graph
			WHERE local_graph_id IN (' . implode(',', $old_aggregates) . ')');

		db_execute('DELETE FROM aggregate_graphs
			WHERE local_graph_id IN (' . implode(',', $old_aggregates) . ')');
	}

	if (cacti_sizeof($old_agg_ids)) {
		db_execute('DELETE FROM aggregate_graphs_items
			WHERE aggregate_graph_id IN (' . implode(',', $old_agg_ids) . ')');
	}
}
