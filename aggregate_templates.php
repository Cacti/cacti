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

require_once('./include/auth.php');
require_once(CACTI_PATH_LIBRARY . '/api_aggregate.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');

$actions = [
	1 => __('Delete')
];

// set default action
set_default_action();

if (grv('action') == 'save') {
	if (isrv('id') && gfrv('id') == 0 && isrv('graph_template_id_prev') && gfrv('graph_template_id_prev') == 0) {
		srv('action', 'edit');
	}
}

switch (grv('action')) {
	case 'save':
		aggregate_form_save();

		break;
	case 'actions':
		aggregate_form_actions();

		break;
	case 'edit':
		top_header();
		aggregate_template_edit();
		bottom_footer();

		break;
	default:
		top_header();
		aggregate_template();
		bottom_footer();

		break;
}

/**
 * aggregate_form_save	the save function
 */
function aggregate_form_save() : void {
	// make sure we are saving aggregate template
	if (!isrv('save_component_template')) {
		header('Location: aggregate_templates.php?action=edit&id=' . gnrv('id'));

		exit();
	}

	$save1 = [];

	// updating existing template or creating a new one?
	if (isrv('id') && grv('id') > 0) {
		$is_new      = false;
		$save1['id'] = gnrv('id');
	} else {
		$is_new      = true;
		$save1['id'] = 0;
	}

	// set some defaults for possibly disabled values
	if (!isrv('total')) {
		srv('total', 0);
	}

	if (!isrv('total_type')) {
		srv('total_type', 0);
	}

	if (!isrv('order_type')) {
		srv('order_type', 0);
	}

	if (!isrv('total_prefix')) {
		srv('total_prefix', '');
	}

	// populate aggregate template save array and validate posted values
	$save1['name']              = form_input_validate(gnrv('name'), 'name', '', false, 3);
	$save1['graph_template_id'] = gfrv('graph_template_id_prev');
	$save1['gprint_prefix']     = form_input_validate(gnrv('gprint_prefix'), 'gprint_prefix', '', true, 3);
	$save1['gprint_format']     = isrv('gprint_format') ? 'on' : '';
	$save1['graph_type']        = form_input_validate(gnrv('graph_type'), 'graph_type', '', false, 3);
	$save1['total']             = form_input_validate(gnrv('total'), 'total', '', false, 3);
	$save1['total_type']        = form_input_validate(gnrv('total_type'), 'total_type', '', false, 3);
	$save1['total_prefix']      = form_input_validate(gnrv('total_prefix'), 'total_prefix', '', true, 3);
	$save1['order_type']        = form_input_validate(gnrv('order_type'), 'order_type', '', false, 3);
	$save1['user_id']           = $_SESSION[SESS_USER_ID];

	// form validation failed
	if (is_error_message()) {
		header('Location: aggregate_templates.php?action=edit&id=' . gnrv('id'));

		exit;
	}

	cacti_log('AGGREGATE GRAPH TEMPLATE Saved ID: ' . $save1['id'] . ' Name: ' . $save1['name'], false, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	// do a quick comparison to see if anything changed
	if ($is_new == false) {
		$old = db_fetch_row_prepared('SELECT *
			FROM aggregate_graph_templates
			WHERE id = ?',
			[$save1['id']]
		);

		$save_me = 0;

		$save_me += ($old['name']          != $save1['name']);
		$save_me += ($old['gprint_prefix'] != $save1['gprint_prefix']);
		$save_me += ($old['gprint_format'] != $save1['gprint_format']);
		$save_me += ($old['graph_type']    != $save1['graph_type']);
		$save_me += ($old['total']         != $save1['total']);
		$save_me += ($old['total_type']    != $save1['total_type']);
		$save_me += ($old['total_prefix']  != $save1['total_prefix']);
		$save_me += ($old['order_type']    != $save1['order_type']);
	} else {
		$save_me = 1;
	}

	if ($save_me) {
		$id = sql_save($save1, 'aggregate_graph_templates', 'id');

		// update children of the template
		db_execute_prepared("UPDATE aggregate_graphs
			SET gprint_prefix = ?, gprint_format = ?, graph_type = ?, total = ?, total_prefix = ?, order_type = ?
			WHERE aggregate_template_id = ?
			AND template_propogation='on'",
			[
				$save1['gprint_prefix'], $save1['gprint_format'], $save1['graph_type'],
				$save1['total'], $save1['total_prefix'],  $save1['order_type'], $id
			]
		);

		cacti_log('AGGREGATE GRAPH TEMPLATE Saved ID: ' . $id, false, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);
	} else {
		$id = $save1['id'];
	}

	if (!$id) {
		raise_message(2);

		header('Location: aggregate_templates.php?action=edit&id=' . gnrv('id'));

		exit;
	}

	// save extra graph parameters
	// validate posted graph params
	$params_new                          = aggregate_validate_graph_params($_POST, true);
	$params_new['aggregate_template_id'] = $id;

	/* compare new graph param values with existing in DB.
	 * We need to know if there were changes so we only
	 * rebuild existing graphs if needed. */
	$params_changed = false;

	$params_old = db_fetch_row_prepared('SELECT *
		FROM aggregate_graph_templates_graph
		WHERE aggregate_template_id = ?',
		[$id]
	);

	if (!empty($params_old)) {
		foreach ($params_old as $field => $value_old) {
			if (isset($params_new[$field]) && $params_new[$field] != $value_old) {
				$params_changed = true;

				break;
			}
		}
	} else {
		$params_changed = true;
	}

	// save only if all posted form fields passed validation
	if ($params_changed && is_error_message() == false) {
		sql_save($params_new, 'aggregate_graph_templates_graph', 'aggregate_template_id', false);
	}

	// save the template items now
	// get existing item ids and sequences from graph template
	$graph_templates_items = array_rekey(
		db_fetch_assoc_prepared('SELECT id, sequence
			FROM graph_templates_item
			WHERE local_graph_id = 0
			AND graph_template_id = ?',
			[$save1['graph_template_id']]
		),
		'id', ['sequence']
	);

	// get existing aggregate template items
	$aggregate_template_items_old = array_rekey(
		db_fetch_assoc_prepared('SELECT *
			FROM aggregate_graph_templates_item
			WHERE aggregate_template_id = ?', [$id]),
		'graph_templates_item_id',
		['sequence', 'color_template', 't_graph_type_id', 'graph_type_id', 't_cdef_id', 'cdef_id', 'item_skip', 'item_total']
	);

	// update graph template item values with posted values
	aggregate_validate_graph_items($_POST, $graph_templates_items);

	$items_changed = false;
	$items_to_save = [];

	foreach ($graph_templates_items as $item_id => $data) {
		$item_new                            = [];
		$item_new['aggregate_template_id']   = $id;
		$item_new['graph_templates_item_id'] = $item_id;

		$item_new['color_template'] = isset($data['color_template']) ? $data['color_template'] : 0;
		$item_new['item_skip']      = isset($data['item_skip']) ? 'on' : '';
		$item_new['item_total']     = isset($data['item_total']) ? 'on' : '';
		$item_new['sequence']       = isset($data['sequence']) ? $data['sequence'] : 0;

		// compare with old item to see if we need to push out.
		if (!isset($aggregate_template_items_old[$item_id])) {
			// this item does not yet exist
			$items_changed = true;
		} else {
			// fill in missing fields with db values
			$item_new = array_merge($aggregate_template_items_old[$item_id], $item_new);

			// compare data from user to data from DB
			foreach ($data as $field => $new_value) {
				if ($aggregate_template_items_old[$item_id][$field] != $new_value) {
					$items_changed = true;
				}
			}
		}
		$items_to_save[] = $item_new;
	}

	if ($items_changed) {
		aggregate_graph_items_save($items_to_save, 'aggregate_graph_templates_item');
	}

	if ($save_me || $params_changed || $items_changed) {
		push_out_aggregates($id);
	}

	raise_message(1);

	header('Location: aggregate_templates.php?action=edit&id=' . $id);
}

/**
 * aggregate_form_actions - the action function
 */
function aggregate_form_actions() : void {
	global $actions;

	// ================= input validation =================
	gfrv('drp_action');
	// ====================================================

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == '1') { // delete
				db_execute('DELETE FROM aggregate_graph_templates WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM aggregate_graph_templates_item WHERE ' . array_to_sql_or($selected_items, 'aggregate_template_id'));
				db_execute('DELETE FROM aggregate_graph_templates_graph WHERE ' . array_to_sql_or($selected_items, 'aggregate_template_id'));
				db_execute("UPDATE aggregate_graphs SET aggregate_template_id=0, template_propogation='' WHERE " . array_to_sql_or($selected_items, 'aggregate_template_id'));
			}
		}

		header('Location: aggregate_templates.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		// loop through each of the color templates selected on the previous page and get more info about them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM aggregate_graph_templates WHERE id = ?', [$matches[1]])) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'aggregate_templates.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following Aggregate Graph Template.'),
					'pmessage' => __('Click \'Continue\' to Delete following Aggregate Graph Templates.'),
					'scont'    => __('Delete Aggregate Graph Template'),
					'pcont'    => __('Delete Aggregate Graph Templates')
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}

/**
 * aggregate_template_edit	edit the color template
 */
function aggregate_template_edit() : void {
	global $image_types, $struct_aggregate_template;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	if (!ierv('id')) {
		$template = db_fetch_row_prepared('SELECT *
			FROM aggregate_graph_templates
			WHERE id = ?',
			[grv('id')]
		);

		$header_label = __esc('Aggregate Template [edit: %s]', $template['name']);
	} else {
		$header_label = __('Aggregate Template [new]');
	}

	// populate the graph template id if it's set
	if (isrv('graph_template_id') && !isset($template)) {
		// ================= input validation =================
		gfrv('graph_template_id');
		// ====================================================
		$template['graph_template_id'] = gnrv('graph_template_id');
		$template['id']                = 0;
	}

	form_start('aggregate_templates.php', 'template_edit');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	$helper_string = '|host_description|';

	if (isset($template)) {
		$data_query = db_fetch_cell_prepared('SELECT snmp_query_id
			FROM snmp_query_graph
			WHERE graph_template_id = ?',
			[$template['graph_template_id']]
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
	$struct_aggregate_template['suggestions'] = [
		'method'        => 'other',
		'friendly_name' => __('Prefix Replacement Values'),
		'description'   => __('You may use these replacement values for the Prefix in the Aggregate Graph'),
		'value'         => $helper_string
	];

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($struct_aggregate_template, (isset($template) ? $template : []))
		]
	);

	html_end_box(true, true);

	if (isset($template)) {
		draw_aggregate_graph_items_list(0, $template['graph_template_id'], $template);

		// Draw Graph Configuration form, so user can override some parameters from graph template
		draw_aggregate_template_graph_config($template['id'], $template['graph_template_id']);
	}

	form_hidden_box('id', (isset($template['id']) ? $template['id'] : '0'), '0');
	form_hidden_box('save_component_template', '1', '');
	form_save_button('aggregate_templates.php', 'return', 'id');

	?>
	<script type='text/javascript'>
		$(function() {
			if ($('#id').val() == 0) {
				$('[id^="agg_total_"]').prop('checked', true);
			}

			var showField = $('#graph_template_id').val() != 0;
			toggleField({
				name: showField,
				spacer1: showField,
				gprint_prefix: showField,
				gprint_format: showField,
				graph_type: showField,
				total: showField,
				total_type: showField,
				total_prefix: showField,
				order_type: showField,
			});

			if ($('#graph_template_id').val() == 0) {
				$('#graph_template_id').change(function() {
					$('#template_edit').submit();
				});

				$('#save_component_template').parent().next('table').css('display', 'none');
			} else {
				$('#graph_template_id').prop('disabled', true);
				if ($('#graph_template_id').selectmenu('instance') !== undefined) {
					$('#graph_template_id').selectmenu('disable');
				}
			}

			$('#total').change(function() {
				changeTotals();
			});

			$('#total_type').change(function() {
				changeTotalsType();
			});

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

				updateSaveButton();
			});

			changeTotals();

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
			var showField = $('#total').val() == '<?php print AGGREGATE_TOTAL_NONE; ?>';

			toggleField({
				total_type: showField,
				total_prefix: showField,
				order_type: showField,
			});

			changeTotalsType();
		}

		function changeTotalsType() {
			if ($('#total_type').val() == <?php print AGGREGATE_TOTAL_TYPE_SIMILAR; ?>) {
				if ($('#total_prefix').val() == '' && $('#id').val() == 0) {
					$('#total_prefix').attr('value', '<?php print __('Total'); ?>');
				}
			} else if ($('#total_type').val() == <?php print AGGREGATE_TOTAL_TYPE_ALL; ?>) {
				if ($('#total_prefix').val() == '' && $('#id').val() == 0) {
					$('#total_prefix').attr('value', '<?php print __('All Items'); ?>');
				}
			}
		}
	</script>
<?php
}

/**
 * aggregate_template
 */
function aggregate_template() : void {
	global $actions, $item_rows;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Aggregate Templates'), 'aggregate_templates.php', 'forms', 'sess_agg_tmp', 'aggregate_templates.php?action=edit');

	$pageFilter->rows_label = __('Templates');
	$pageFilter->set_sort_array('pgt.name', 'ASC');
	$pageFilter->render();

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	$sql_where = '';

	if (grv('filter') != '') {
		$sql_where = 'WHERE (pgt.name LIKE ' . db_qstr('%' . grv('filter') . '%') . ' OR gt.name LIKE ' . db_qstr('%' . grv('filter') . '%') . ')';
	}

	if (grv('has_graphs') == 'true') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'gt.graphs > 0';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM aggregate_graph_templates AS pgt
		LEFT JOIN graph_templates AS gt
		ON pgt.graph_template_id = gt.id
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$template_list = db_fetch_assoc("SELECT pgt.*, gt.name AS graph_template_name
		FROM aggregate_graph_templates AS pgt
		LEFT JOIN graph_templates AS gt
		ON pgt.graph_template_id = gt.id
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('aggregate_templates.php', MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 5, __('Aggregate Templates'), 'page', 'main');

	form_start('aggregate_templates.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'pgt.name' => [
			'display' => __('Template Title'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'nosort' => [
			'display' => __('Deletable'),
			'align'   => 'right',
			'tip'     => __('Aggregate Templates that are in use can not be Deleted.  In use is defined as being referenced by an Aggregate.')
		],
		'nosort1' => [
			'display' => __('Graphs Using'),
			'align'   => 'right',
			'sort'    => 'DESC'
		],
		'graph_template_name' => [
			'display' => __('Graph Template'),
			'align'   => 'left',
			'sort'    => 'ASC'
		]
	];

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($template_list)) {
		foreach ($template_list as $template) {
			if ($template['graphs'] > 0) {
				$disabled = true;
			} else {
				$disabled = false;
			}

			form_alternate_row('line' . $template['id'], true, $disabled);

			form_selectable_cell(filter_value($template['name'], grv('filter'), 'aggregate_templates.php?action=edit&id=' . $template['id'] . '&page=1'), $template['id']);
			form_selectable_cell($disabled ? __('No') : __('Yes'), $template['id'], '', 'right');

			$url = 'aggregate_graphs.php?reset=true&template_id=' . $template['id'];

			form_selectable_cell(filter_value(number_format_i18n($template['graphs'], -1), '', $url), $template['id'], '', 'right');

			form_selectable_cell(filter_value($template['graph_template_name'], grv('filter')), $template['id']);

			form_checkbox_cell($template['graph_template_name'], $template['id'], $disabled);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Aggregate Templates Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($template_list)) {
		// put the nav bar on the bottom as well
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();

	?>
	<script type='text/javascript'>
		function applyFilter() {
			strURL = 'aggregate_templates.php';
			strURL += '?rows=' + $('#rows').val();
			strURL += '&has_graphs=' + $('#has_graphs').is(':checked');
			strURL += '&filter=' + $('#filter').val();
			loadUrl({
				url: strURL
			})
		}

		function clearFilter() {
			strURL = 'aggregate_templates.php?clear=1';
			loadUrl({
				url: strURL
			})
		}

		$(function() {
			$('#clear').click(function() {
				clearFilter();
			});

			$('#template').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});

			$('#forms').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
	</script>
<?php
}
