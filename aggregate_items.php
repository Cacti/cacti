<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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
include_once('./lib/api_aggregate.php');
include_once('./lib/poller.php');
include_once('./lib/utility.php');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

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
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $config;

	if (isset_request_var('save_component_item')) {
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
					'color_id' => '0',
					'graph_type_id' => '9',
					'consolidation_function_id' => '4',
					'text_format' => 'Current:',
					'hard_return' => ''
					),
				1 => array(
					'color_id' => '0',
					'graph_type_id' => '9',
					'consolidation_function_id' => '1',
					'text_format' => 'Average:',
					'hard_return' => ''
					),
				2 => array(
					'color_id' => '0',
					'graph_type_id' => '9',
					'consolidation_function_id' => '3',
					'text_format' => 'Maximum:',
					'hard_return' => 'on'
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
			header('Location: ' . $config['url_path'] . 'aggregate_items.php?action=item_edit&graph_template_item_id=' . (empty($graph_template_item_id) ? get_filter_request_var('graph_template_item_id') : $graph_template_item_id) . '&id=' . get_filter_request_var('local_graph_id'));
			exit;
		} else {
			header('Location: ' . $config['url_path'] . 'aggregate_graphs.php?action=edit&id=' . get_filter_request_var('local_graph_id'));
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
	$location_failure = 'aggregate_items.php?action=item_edit&aggregate_template_id=' . get_filter_request_var('aggregate_template_id') . '&id=' . get_filter_request_var('graph_template_item_id');

	if (get_filter_request_var('aggregate_graph_id') > 0) {
		$save_to          = 'aggregate_graphs_graph_item';
		$key_cols         = array('aggregate_graph_id', 'graph_templates_item_id');
		$location_success = 'aggregate_graphs.php?action=edit&id=' . get_filter_request_var('local_graph_id');
		$location_failure = 'aggregate_items.php?action=item_edit&aggregate_graph_id=' . get_filter_request_var('aggregate_graph_id') . '&id=' . get_filter_request_var('graph_template_item_id');
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
			$sql_set .= $key . "=" . db_qstr($value) . ", ";
		}
		$sql_set = substr($sql_set, 0, -2);

		$sql_where = 'graph_templates_item_id = ' . get_filter_request_var('graph_template_item_id') . ' AND ';
		if ($save_to == 'aggregate_graph_templates_item') {
			$sql_where .= 'aggregate_template_id=' . get_filter_request_var('aggregate_template_id');
		} else {
			$sql_where .= 'aggregate_graph_id=' . get_filter_request_var('aggregate_graph_id');
		}
		$sql = "UPDATE $save_to $sql_set WHERE $sql_where LIMIT 1";
		$success = db_execute($sql);

		if ($success) {
			raise_message(1);
		} else {
			raise_message(2);
		}

		// update existing graphs with the changes to this item
		if ($save_to == 'aggregate_graphs_graph_item')
			push_out_aggregates(0, get_filter_request_var('local_graph_id'));
		elseif ($save_to == 'aggregate_graph_templates_item')
			push_out_aggregates(get_filter_request_var('aggregate_template_id'));

	}

	if (is_error_message()) {
		header('Location: ' . $config['url_path'] . $location_failure);
		exit;
	} else {
		header('Location: ' . $config['url_path'] . $location_success);
		exit;
	}
}

/* -----------------------
    item - Graph Items
   ----------------------- */

function item_movedown() {
	global $graph_item_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('local_graph_id');
	/* ==================================================== */

	$arr = get_graph_group(get_request_var('id'));
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

	$arr = get_graph_group(get_request_var('id'));
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
	}else {
		/* TODO redirect somewhere and show an error message, rather than die */
		die("We should have redirected somewhere but we ended up here instead" . PHP_EOL);
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
		array(get_request_var($id_field), get_request_var("id")));

	if (cacti_sizeof($item_overrides) == 0) {
		/* this item is not currently in aggregate tables
		 * item editor will not work in this case, so let's
		 * save it now
		 */
		$item_new = array(
			$id_field => get_request_var($id_field),
			'graph_templates_item_id' => get_request_var("id"),
			'sequence' => $template_item['sequence']
		);

		aggregate_graph_items_save(array($item_new), $table_name);

		$item_overrides = db_fetch_row_prepared("SELECT *
			FROM $table_name
			WHERE $id_field = ?
			AND graph_templates_item_id = ?",
			array(get_request_var($id_field), get_request_var("id")));
	}

	foreach (array_keys($template_item) as $field_name) {
		if (!array_key_exists($field_name, $item_overrides))
			continue;
		# t_<field_name> column in aggregate table must be "on" to override
		if (array_key_exists("t_".$field_name, $item_overrides) && $item_overrides["t_".$field_name] == "on")
			$template_item[$field_name] = $item_overrides[$field_name];
	}

	html_start_box(__('Override Values for Graph Item'), '100%', true, '3', 'center', '');

	$form_array = array();

	foreach ($struct_graph_item as $field_name => $field_array) {
		$form_array += array($field_name => $struct_graph_item[$field_name]);

		/* should we draw an override checkbox */
		if (array_key_exists('t_' . $field_name, $item_overrides)) {
			$form_array[$field_name]['sub_checkbox']  = array(
				'name' => 't_' . $field_name,
				'friendly_name' => __esc('Override this Value') . '<br>',
				'value' => ($item_overrides['t_'.$field_name] == 'on' ? 'on' : ''),
				'on_change' => 'toggleFieldEnabled(this.id);'
			);
		}

		$form_array[$field_name]['value'] = (isset($template_item) ? $template_item[$field_name] : '');
		$form_array[$field_name]['form_id'] = (isset($template_item) ? $template_item['id'] : '0');
	}

	draw_edit_form(
		array(
			'config' => array(
				'post_to' => $config['url_path'] . 'aggregate_items.php'
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

	form_save_button($config['url_path'] . "$page_name?action=edit&id=" . get_request_var('local_graph_id'));

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

