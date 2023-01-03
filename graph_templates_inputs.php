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
include_once('./lib/template.php');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'input_remove':
		input_remove();

		header('Location: graph_templates.php?action=template_edit&id=' . get_request_var('graph_template_id'));

		break;
	case 'input_edit':
		top_header();

		input_edit();

		bottom_footer();

		break;
}

function form_save() {
	if ((isset_request_var('save_component_input')) && (!is_error_message())) {
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
			header('Location: graph_templates_inputs.php?action=input_edit&graph_template_input_id=' . (empty($graph_template_input_id) ? get_nfilter_request_var('graph_template_input_id') : $graph_template_input_id) . '&graph_template_id=' . get_nfilter_request_var('graph_template_id'));

			exit;
		} else {
			header('Location: graph_templates.php?action=template_edit&id=' . get_nfilter_request_var('graph_template_id'));

			exit;
		}
	}
}

/* ------------------------------------
	input - Graph Template Item Inputs
   ------------------------------------ */

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

	form_start('graph_templates_inputs.php');

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
