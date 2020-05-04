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
include_once('./lib/poller.php');
include_once('./lib/utility.php');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'item_remove':
		get_filter_request_var('local_graph_id');

		item_remove();

		header('Location: graphs.php?action=graph_edit&id=' . get_request_var('local_graph_id'));
		break;
	case 'item_edit':
		top_header();

		item_edit();

		bottom_footer();
		break;
	case 'item_movedown':
		get_filter_request_var('local_graph_id');

		item_movedown();

		header('Location: graphs.php?action=graph_edit&id=' . get_request_var('local_graph_id'));
		break;
	case 'item_moveup':
		get_filter_request_var('local_graph_id');

		item_moveup();

		header('Location: graphs.php?action=graph_edit&id=' . get_request_var('local_graph_id'));
		break;
	case 'ajax_hosts':
		get_allowed_ajax_hosts();

		break;
	case 'ajax_hosts_noany':
		get_allowed_ajax_hosts(false);

		break;
	case 'ajax_graph_items':
		$sql_where = '';

		if (!isempty_request_var('host_id')) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'dl.host_id=' . get_filter_request_var('host_id');
		}

		if (!isempty_request_var('data_template_id')) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'dtd.data_template_id=' . get_filter_request_var('data_template_id');
		}

		get_allowed_ajax_graph_items(true, $sql_where);

		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset_request_var('save_component_item')) {
		global $graph_item_types;

		/* ================= input validation ================= */
		get_filter_request_var('sequence');
		get_filter_request_var('graph_type_id');
		get_filter_request_var('local_graph_id');
		get_filter_request_var('graph_template_item_id');
		get_filter_request_var('graph_template_id');
		get_filter_request_var('local_graph_template_item_id');
		/* ==================================================== */

		$items[0] = array();

		if ($graph_item_types[get_nfilter_request_var('graph_type_id')] == 'LEGEND') {
			/* this can be a major time saver when creating lots of graphs with the typical
			GPRINT LAST/AVERAGE/MAX legends */
			$items = array(
				0 => array(
					'color_id' => '0',
					'graph_type_id' => '9',
					'consolidation_function_id' => '4',
					'text_format' => 'Cur:',
					'hard_return' => ''
					),
				1 => array(
					'color_id' => '0',
					'graph_type_id' => '9',
					'consolidation_function_id' => '1',
					'text_format' => 'Avg:',
					'hard_return' => ''
					),
				2 => array(
					'color_id' => '0',
					'graph_type_id' => '9',
					'consolidation_function_id' => '3',
					'text_format' => 'Max:',
					'hard_return' => 'on'
					));
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

		$sequence = get_nfilter_request_var('sequence');

		foreach ($items as $item) {
			/* generate a new sequence if needed */
			if (empty($sequence)) {
				$sequence = get_sequence($sequence, 'sequence', 'graph_templates_item', 'local_graph_id=' . get_nfilter_request_var('local_graph_id'));
			}
			$save['id']                           = get_nfilter_request_var('graph_template_item_id');
			$save['graph_template_id']            = get_nfilter_request_var('graph_template_id');
			$save['local_graph_template_item_id'] = get_nfilter_request_var('local_graph_template_item_id');
			$save['local_graph_id']               = get_nfilter_request_var('local_graph_id');
			$save['task_item_id']                 = form_input_validate(get_nfilter_request_var('task_item_id'), 'task_item_id', '^[0-9]+$', true, 3);
			$save['color_id']                     = form_input_validate((isset($item['color_id']) ? $item['color_id'] : get_nfilter_request_var('color_id')), 'color_id', '^[0-9]+$', true, 3);

			/* if alpha is disabled, use invisible_alpha instead */
			if (!isset_request_var('alpha')) {
				set_request_var('alpha', get_nfilter_request_var('invisible_alpha'));
			}

			$save['alpha']          = form_input_validate((isset($item['alpha']) ? $item['alpha'] : get_nfilter_request_var('alpha')), 'alpha', '', true, 3);
			$save['graph_type_id']  = form_input_validate((isset($item['graph_type_id']) ? $item['graph_type_id'] : get_nfilter_request_var('graph_type_id')), 'graph_type_id', '^[0-9]+$', true, 3);

			if (isset_request_var('line_width') || isset($item['line_width'])) {
				$save['line_width'] = form_input_validate((isset($item['line_width']) ? $item['line_width'] : get_nfilter_request_var('line_width')), 'line_width', '(^[0-9]+[\.,0-9]+$|^[0-9]+$)', true, 3);
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

			$save['dashes']         = form_input_validate((isset_request_var('dashes') ? get_nfilter_request_var('dashes') : ''), 'dashes', '', true, 3);
			$save['dash_offset']    = form_input_validate((isset_request_var('dash_offset') ? get_nfilter_request_var('dash_offset') : ''), 'dash_offset', '^[0-9]+$', true, 3);
			$save['cdef_id']        = form_input_validate(get_nfilter_request_var('cdef_id'), 'cdef_id', '^[0-9]+$', true, 3);
			$save['vdef_id']        = form_input_validate(get_nfilter_request_var('vdef_id'), 'vdef_id', '^[0-9]+$', true, 3);
			$save['shift']          = form_input_validate((isset_request_var('shift') ? get_nfilter_request_var('shift') : ''), 'shift', '^((on)|)$', true, 3);
			$save['consolidation_function_id'] = form_input_validate((isset($item['consolidation_function_id']) ? $item['consolidation_function_id'] : get_nfilter_request_var('consolidation_function_id')), 'consolidation_function_id', '^[0-9]+$', true, 3);
			$save['textalign']      = form_input_validate((isset_request_var('textalign') ? get_nfilter_request_var('textalign') : ''), 'textalign', '^[a-z]+$', true, 3);
			$save['text_format']    = form_input_validate((isset($item['text_format']) ? $item['text_format'] : get_nfilter_request_var('text_format')), 'text_format', '', true, 3);
			$save['value']          = form_input_validate(get_nfilter_request_var('value'), 'value', '', true, 3);
			$save['hard_return']    = form_input_validate(((isset($item['hard_return']) ? $item['hard_return'] : (isset_request_var('hard_return') ? get_nfilter_request_var('hard_return') : ''))), 'hard_return', '', true, 3);
			$save['gprint_id']      = form_input_validate(get_nfilter_request_var('gprint_id'), 'gprint_id', '^[0-9]+$', true, 3);
			$save['sequence']       = $sequence;

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
			header('Location: graphs.php?action=item_edit&graph_template_item_id=' . (empty($graph_template_item_id) ? get_nfilter_request_var('graph_template_item_id') : $graph_template_item_id) . '&id=' . get_nfilter_request_var('local_graph_id'));
			exit;
		} else {
			header('Location: graphs.php?action=graph_edit&id=' . get_nfilter_request_var('local_graph_id'));
			exit;
		}
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

function validate_item_vars() {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0'
		),
		'local_graph_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0'
		),
		'data_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0'
		)
	);

	validate_store_request_vars($filters, 'sess_gitems');
	/* ================= input validation ================= */
}

function item_edit() {
	global $struct_graph_item, $graph_item_types, $consolidation_functions;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_id');
	get_filter_request_var('local_graph_id');
	get_filter_request_var('data_template_id');
	/* ==================================================== */

	validate_item_vars();

	$id = (!isempty_request_var('id') ? '&id=' . get_request_var('id') : '');

	$host = db_fetch_row_prepared('SELECT hostname
		FROM host
		WHERE id = ?',
		array(get_request_var('host_id')));

	if (empty($host['hostname'])) {
		$header = __('Data Sources [No Device]');
	} else {
		$header = __esc('Data Sources [%s]', $host['hostname']);
	}

	html_start_box($header, '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form name='form_graph_items' action='graphs_items.php'>
			<table class='filterTable'>
				<tr>
					<?php print html_host_filter(get_request_var('host_id'));?>
				</tr>
				<tr>
					<td>
						<?php print __('Data Template');?>
					</td>
					<td>
						<select id='data_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('data_template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('data_template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php
							if (get_request_var('host_id') <= 0) {
								$data_templates = db_fetch_assoc('SELECT id, name
									FROM data_template
									ORDER BY name');
							} else {
								$data_templates = db_fetch_assoc_prepared('SELECT DISTINCT dt.id, dt.name
									FROM data_template AS dt
									INNER JOIN data_local AS dl
									ON dl.data_template_id=dt.id
									WHERE dl.host_id = ?
									ORDER BY name',
									array(get_request_var('host_id')));
							}

							if (cacti_sizeof($data_templates)) {
								foreach ($data_templates as $data_template) {
									print "<option value='" . $data_template['id'] . "'" . (get_request_var('data_template_id') == $data_template['id'] ? ' selected':'') . '>' . html_escape($data_template['name']) . "</option>\n";
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

	load_current_session_value('host_id', 'sess_graph_items_hi', '-1');
	load_current_session_value('data_template_id', 'sess_graph_items_dti', '-1');

	if (get_request_var('host_id') > 0) {
		$sql_where = 'h.id=' . get_request_var('host_id');
	} elseif (get_request_var('host_id') == 0) {
		$sql_where = 'h.id IS NULL';
	} else {
		$sql_where = '';
	}

	if (get_request_var('data_template_id') == '-1') {
		$sql_where .= '';
	} elseif (get_request_var('data_template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . 'dl.data_template_id=0';
	} elseif (!isempty_request_var('data_template_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . 'dl.data_template_id=' . get_request_var('data_template_id');
	}

	if (!isempty_request_var('id')) {
		$template_item = db_fetch_row_prepared('SELECT *
			FROM graph_templates_item
			WHERE id = ?',
			array(get_request_var('id')));
	} else {
		$template_item = array();

		kill_session_var('sess_graph_items_dti');
	}

	$title = db_fetch_cell_prepared('SELECT title_cache
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		array(get_request_var('local_graph_id')));

	$header_label = __esc('Graph Items [graph: %s]', $title);

	form_start('graphs_items.php', 'greph_edit');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	/* by default, select the LAST DS chosen to make everyone's lives easier */
	if (!isempty_request_var('local_graph_id')) {
		$struct_graph_item['task_item_id']['default'] = 0;

		if (isset($template_item['task_item_id'])) {
			$task_item_id = $template_item['task_item_id'];
			$value = db_fetch_cell_prepared("SELECT
				CONCAT_WS('', dtd.name_cache,' (', dtr.data_source_name, ')') as name
				FROM data_local AS dl
				INNER JOIN data_template_data AS dtd
				ON dtd.local_data_id=dl.id
				INNER JOIN data_template_rrd AS dtr
				ON dtr.local_data_id=dl.id
				LEFT JOIN host AS h
				ON dl.host_id=h.id
				WHERE dtr.id = ?",
				array($task_item_id));
		} else {
			$task_item_id = 0;
			$value = '';
		}

		if (get_selected_theme() != 'classic' && read_config_option('autocomplete_enabled') > 0) {
			$action = 'ajax_graph_items';
			if (get_request_var('host_id') > 0) {
				$action .= '&host_id=' . get_filter_request_var('host_id');
			}

			if (get_request_var('data_template_id') > 0) {
				$action .= '&data_template_id=' . get_filter_request_var('data_template_id');
			}

			$struct_graph_item['task_item_id'] = array(
				'method' => 'drop_callback',
				'friendly_name' => __('Data Source'),
				'description' => __('Choose the Data Source to associate with this Graph Item.'),
				'sql' => '',
				'action' => $action,
				'none_value' => __('None'),
				'id' => $task_item_id,
				'value' => $value
			);
		}

		/* modifications to the default graph items array */
		$struct_graph_item['task_item_id']['sql'] = "SELECT
			CONCAT_WS('', dtd.name_cache,' (', dtr.data_source_name, ')') as name, dtr.id
			FROM data_local AS dl
			INNER JOIN data_template_data AS dtd
			ON dtd.local_data_id=dl.id
			INNER JOIN data_template_rrd AS dtr
			ON dtr.local_data_id=dl.id
			LEFT JOIN host AS h
			ON dl.host_id=h.id";

		/* Make sure we don't limit the list so that the selected DS isn't in the list in edit mode */
		if ($sql_where != '') {
			if (!isempty_request_var('id')) {
				$struct_graph_item['task_item_id']['sql'] .= " WHERE ($sql_where) OR (dtr.id=" . $template_item['task_item_id'] . ")";
			} else {
				$struct_graph_item['task_item_id']['sql'] .= " WHERE $sql_where";
			}
		}

		$struct_graph_item['task_item_id']['sql'] .= ' ORDER BY name';
	}

	$form_array = array();

	foreach ($struct_graph_item as $field_name => $field_array) {
		$form_array += array($field_name => $struct_graph_item[$field_name]);

		if (get_selected_theme() != 'classic' && read_config_option('autocomplete_enabled')) {
			if ($field_name != 'task_item_id') {
				$form_array[$field_name]['value'] = (isset($template_item[$field_name]) ? $template_item[$field_name] : '');
			}
		}else{
			$form_array[$field_name]['value'] = (isset($template_item[$field_name]) ? $template_item[$field_name] : '');
		}

		$form_array[$field_name]['form_id'] = (isset($template_item['id']) ? $template_item['id'] : '0');
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $form_array
		)
	);

	form_hidden_box('local_graph_id', get_request_var('local_graph_id'), '0');
	form_hidden_box('graph_template_item_id', (!empty($template_item) ? $template_item['id'] : '0'), '');
	form_hidden_box('local_graph_template_item_id', (!empty($template_item) ? $template_item['local_graph_template_item_id'] : '0'), '');
	form_hidden_box('graph_template_id', (!empty($template_item) ? $template_item['graph_template_id'] : '0'), '');
	form_hidden_box('_graph_type_id', (!empty($template_item) ? $template_item['graph_type_id'] : '0'), '');
	form_hidden_box('save_component_item', '1', '');
	form_hidden_box('invisible_alpha', $form_array['alpha']['value'], 'FF');
	form_hidden_box('rrdtool_version', get_rrdtool_version(), '');

	html_end_box(true, true);

	form_save_button('graphs.php?action=graph_edit&id=' . get_request_var('local_graph_id'));

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

	function applyFilter() {
		strURL = 'graphs_items.php?action=item_edit<?php print $id;?>' +
			'&local_graph_id=<?php print get_request_var('local_graph_id');?>' +
			'&data_template_id='+$('#data_template_id').val()+
			'&host_id='+$('#host_id').val();

		loadUrl({url:strURL})
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

