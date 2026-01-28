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
require_once(CACTI_PATH_LIBRARY . '/cdef.php');

$actions = [
	1 => __('Delete'),
	2 => __('Duplicate')
];

// set default action
set_default_action();

switch (grv('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'item_remove_confirm':
		cdef_item_remove_confirm();

		break;
	case 'item_remove':
		cdef_item_remove();

		header('Location: cdef.php?action=edit&id=' . grv('cdef_id'));

		break;
	case 'item_movedown':
		gfrv('cdef_id');

		item_movedown();

		header('Location: cdef.php?action=edit&id=' . grv('cdef_id'));

		break;
	case 'item_moveup':
		gfrv('cdef_id');

		item_moveup();

		header('Location: cdef.php?action=edit&id=' . grv('cdef_id'));

		break;
	case 'item_edit':
		top_header();

		item_edit();

		bottom_footer();

		break;
	case 'edit':
		top_header();

		cdef_edit();

		bottom_footer();

		break;
	case 'ajax_dnd':
		cdef_item_dnd();

		break;
	default:
		top_header();

		cdef();

		bottom_footer();

		break;
}

function draw_cdef_preview(int $cdef_id) : void {
	?>
	<tr class='even'>
		<td style='padding:4px'>
			<pre>cdef=<?php print htmle(get_cdef($cdef_id)); ?></pre>
		</td>
	</tr>
	<?php
}

function form_save() : void {
	// make sure ids are numeric
	if (isrv('id') && ! is_numeric(gfrv('id'))) {
		srv('id', 0);
	}

	if (isrv('cdef_id') && ! is_numeric(gfrv('cdef_id'))) {
		srv('cdef_id', 0);
	}

	if (isrv('save_component_cdef')) {
		$save['id']     = form_input_validate(gnrv('id'), 'id', '^[0-9]+$', false, 3);
		$save['hash']   = get_hash_cdef(gnrv('id'));
		$save['name']   = form_input_validate(gnrv('name'), 'name', '', false, 3);
		$save['system'] = 0;

		if (!is_error_message()) {
			$cdef_id = sql_save($save, 'cdef');

			if ($cdef_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: cdef.php?action=edit&id=' . (empty($cdef_id) ? gnrv('id') : $cdef_id));
	} elseif (isrv('save_component_item')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('cdef_id');
		gfrv('type');
		// ====================================================

		$sequence = get_sequence(gnrv('id'), 'sequence', 'cdef_items', 'cdef_id=' . gnrv('cdef_id'));

		$save['id']       = form_input_validate(gnrv('id'), 'id', '^[0-9]+$', false, 3);
		$save['hash']     = get_hash_cdef(gnrv('id'), 'cdef_item');
		$save['cdef_id']  = form_input_validate(gnrv('cdef_id'), 'cdef_id', '^[0-9]+$', false, 3);
		$save['sequence'] = $sequence;
		$save['type']     = form_input_validate(gnrv('type'), 'type', '^[0-9]+$', false, 3);
		$save['value']    = form_input_validate(gnrv('value'), 'value', '', false, 3);

		$cdef_item_id = null;

		if (!is_error_message()) {
			$cdef_item_id = sql_save($save, 'cdef_items');

			if ($cdef_item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: cdef.php?action=item_edit&cdef_id=' . gnrv('cdef_id') . '&id=' . ($cdef_item_id === null ? gnrv('id') : $cdef_item_id));
		} else {
			header('Location: cdef.php?action=edit&id=' . gnrv('cdef_id'));
		}
	}
}

function duplicate_cdef(int $_cdef_id, string $cdef_title) : void {
	global $fields_cdef_edit;

	$cdef       = db_fetch_row_prepared('SELECT * FROM cdef WHERE id = ?', [$_cdef_id]);
	$cdef_items = db_fetch_assoc_prepared('SELECT * FROM cdef_items WHERE cdef_id = ?', [$_cdef_id]);

	// substitute the title variable
	$cdef['name'] = str_replace('<cdef_title>', $cdef['name'], $cdef_title);

	// create new entry: host_template
	$save['id']   = 0;
	$save['hash'] = get_hash_cdef(0);

	foreach ($fields_cdef_edit as $field => $array) {
		if (!preg_match('/^hidden/', $array['method'])) {
			$save[$field] = $cdef[$field];
		}
	}

	$cdef_id = sql_save($save, 'cdef');

	// create new entry(s): cdef_items
	if (cacti_sizeof($cdef_items) > 0) {
		foreach ($cdef_items as $cdef_item) {
			unset($save);

			$save['id']       = 0;
			$save['hash']     = get_hash_cdef(0, 'cdef_item');
			$save['cdef_id']  = $cdef_id;
			$save['sequence'] = $cdef_item['sequence'];
			$save['type']     = $cdef_item['type'];
			$save['value']    = $cdef_item['value'];

			sql_save($save, 'cdef_items');
		}
	}
}

function form_actions() : void {
	global $actions;

	// ================= input validation =================
	gfrv('drp_action', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-zA-Z0-9_]+)$/']]);
	// ====================================================

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == '1') { // delete
				db_execute('DELETE FROM cdef WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM cdef_items WHERE ' . array_to_sql_or($selected_items, 'cdef_id'));
			} elseif (gnrv('drp_action') == '2') { // duplicate
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					duplicate_cdef($selected_items[$i], gnrv('title_format'));
				}
			}
		}

		header('Location: cdef.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		// loop through each of the graphs selected on the previous page and get more info about them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM cdef WHERE id = ?', [$matches[1]])) . '</li>';
				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'cdef.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following CDEF.'),
					'pmessage' => __('Click \'Continue\' to Delete following CDEFs.'),
					'scont'    => __('Delete CDEF'),
					'pcont'    => __('Delete CDEFs')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Duplicate the following CDEF.'),
					'pmessage' => __('Click \'Continue\' to Duplicate following CDEFs.'),
					'scont'    => __('Duplicate CDEF'),
					'pcont'    => __('Duplicate CDEFs'),
					'extra'    => [
						'title_format' => [
							'method'  => 'textbox',
							'title'   => __('Title Format'),
							'default' => '<cdef_title>',
							'width'   => 25
						]
					]
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}

function cdef_item_remove_confirm() : void {
	global $cdef_functions, $cdef_item_types, $custom_cdef_data_source_types;

	// ================= input validation =================
	gfrv('id');
	gfrv('cdef_id');
	// ====================================================

	// sort the cdef functions
	asort($cdef_functions);

	form_start('cdef.php');

	html_start_box('', '100%', false, 3, 'center', '');

	$cdef       = db_fetch_row_prepared('SELECT * FROM cdef WHERE id = ?', [grv('id')]);
	$cdef_item  = db_fetch_row_prepared('SELECT * FROM cdef_items WHERE id = ?', [grv('cdef_id')]);

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following CDEF Item.'); ?></p>
			<p><?php print __esc('CDEF Name: %s', $cdef['name']); ?><br>
			<em><?php $cdef_item_type = $cdef_item['type'];
	print $cdef_item_types[$cdef_item_type]; ?></em>: <strong><?php print htmle(get_cdef_item_name($cdef_item['id'])); ?></strong></p>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel' onClick='$("#cdialog").dialog("close");$(".deleteMarker").blur();' name='cancel'><?php print __esc('Cancel'); ?></button>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='continue' name='continue' title='<?php print __esc('Remove CDEF Item'); ?>'><?php print __esc('Continue'); ?></button>
			<input type='hidden' id='my_cdef_id' value='<?php print $cdef['id']; ?>'>
			<input type='hidden' id='my_id' value='<?php print $cdef_item['id']; ?>'>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#continue').click(function(data) {
			var options = {
				url:'cdef.php?action=item_remove',
				funcEnd: 'remoteCdefItemFinalize'
			}

			var data = {
				__csrf_magic: csrfMagicToken,
				cdef_id: <?php print grv('cdef_id'); ?>,
				id: <?php print grv('id'); ?>
			}

			postUrl(options, data);
		});
	});

	function removeCdefItemFinalize(data) {
		$('#cdialog').dialog('close');
		$('.deleteMarker').blur();
		loadUrl({url:'cdef.php?action=edit&id=<?php print grv('id'); ?>'})
	};
	</script>
	<?php
}

function item_movedown() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('cdef_id');
	// ====================================================

	move_item_down('cdef_items', grv('id'), 'cdef_id=' . grv('cdef_id'));
}

function item_moveup() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('cdef_id');
	// ====================================================

	move_item_up('cdef_items', grv('id'), 'cdef_id=' . grv('cdef_id'));
}

function cdef_item_remove() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('cdef_id');
	// ====================================================

	db_execute_prepared('DELETE FROM cdef_items
		WHERE cdef_id = ?
		AND id = ?',
		[grv('cdef_id'), grv('id')]);
}

function item_edit() : void {
	global $cdef_item_types, $cdef_functions, $cdef_operators, $custom_data_source_types;

	// ================= input validation =================
	gfrv('id');
	gfrv('cdef_id');
	// ====================================================

	// sort the cdef functions
	asort($cdef_functions);

	if (!ierv('id')) {
		$cdef = db_fetch_row_prepared('SELECT *
			FROM cdef_items
			WHERE id = ?',
			[grv('id')]);

		if (cacti_sizeof($cdef)) {
			$current_type          = $cdef['type'];
			$values[$current_type] = $cdef['value'];
		}
	} else {
		$cdef = [];
	}

	html_start_box(__('CDEF Preview'), '100%', false, 3, 'center', '');
	draw_cdef_preview(grv('cdef_id'));
	html_end_box();

	form_start('cdef.php', 'chk');

	$cdef_name = db_fetch_cell_prepared('SELECT name
		FROM cdef
		WHERE id = ?',
		[grv('cdef_id')]);

	html_start_box(__esc('CDEF Items [edit: %s]', $cdef_name), '100%', false, 3, 'center', '');

	if (isrv('type_select')) {
		$current_type = grv('type_select');
	} elseif (isset($cdef['type'])) {
		$current_type = $cdef['type'];
	} else {
		$current_type = '1';
	}

	$form_cdef = [
		'type_select' => [
			'method'        => 'drop_array',
			'friendly_name' => __('CDEF Item Type'),
			'description'   => __('Choose what type of CDEF item this is.'),
			'value'         => $current_type,
			'array'         => $cdef_item_types
		],
		'value' => [
			'method'        => 'drop_array',
			'friendly_name' => __('CDEF Item Value'),
			'description'   => __('Enter a value for this CDEF item.'),
			'value'         => (isset($cdef['value']) ? $cdef['value'] : '')
		],
		'id' => [
			'method'        => 'hidden',
			'value'         => isrv('id') ? grv('id') : '0',
		],
		'type' => [
			'method'        => 'hidden',
			'value'         => $current_type
		],
		'cdef_id' => [
			'method'        => 'hidden',
			'value'         => grv('cdef_id')
		],
		'save_component_item' => [
			'method'        => 'hidden',
			'value'         => '1'
		]
	];

	switch ($current_type) {
		case '1':
			$form_cdef['value']['array'] = $cdef_functions;

			break;
		case '2':
			$form_cdef['value']['array'] = $cdef_operators;

			break;
		case '4':
			$form_cdef['value']['array'] = $custom_data_source_types;

			break;
		case '5':
			$form_cdef['value']['method'] = 'drop_sql';
			$form_cdef['value']['sql']    = 'SELECT name, id FROM cdef WHERE `system` = 0 ORDER BY name';

			break;
		case '6':
			$form_cdef['value']['method']     = 'textbox';
			$form_cdef['value']['max_length'] = '255';
			$form_cdef['value']['size']       = '30';

			break;
	}

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($form_cdef, $cdef)
		]
	);

	?>
	<script type='text/javascript'>
	$(function() {
		$('#type_select').unbind().change(function() {
			strURL  = 'cdef.php?action=item_edit';
			strURL += '&id=' + $('#id').val();
			strURL += '&cdef_id=' + $('#cdef_id').val();
			strURL += '&type_select=' + $('#type_select').val();
			loadUrl({url:strURL})
		});
	});
	</script>
	<?php

	html_end_box();

	form_save_button('cdef.php?action=edit&id=' . grv('cdef_id'));
}

function cdef_item_dnd() : void {
	// ================= Input validation =================
	gfrv('id');
	// ================= Input validation =================

	$continue = true;

	if (isrv('cdef_item') && is_array(gnrv('cdef_item'))) {
		$cdef_ids = gnrv('cdef_item');

		if (cacti_sizeof($cdef_ids)) {
			$sequence = 1;

			foreach ($cdef_ids as $cdef_id) {
				$cdef_id = str_replace('line', '', $cdef_id);
				input_validate_input_number($cdef_id, 'cdef_id');

				db_execute_prepared('UPDATE cdef_items
					SET sequence = ?
					WHERE id = ?',
					[$sequence, $cdef_id]);

				$sequence++;
			}
		}
	}

	header('Location: cdef.php?action=edit&id=' . grv('id'));
}

function cdef_edit() : void {
	global $cdef_item_types, $fields_cdef_edit;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	if (!ierv('id')) {
		$cdef         = db_fetch_row_prepared('SELECT * FROM cdef WHERE id = ?', [grv('id')]);
		$header_label = __esc('CDEF [edit: %s]', $cdef['name']);
	} else {
		$cdef         = [];
		$header_label = __('CDEF [new]');
	}

	form_start('cdef.php', 'cdef');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($fields_cdef_edit, $cdef)
		]
	);

	html_end_box(true, true);

	if (!ierv('id') && cacti_sizeof($cdef)) {
		html_start_box('', '100%', false, 3, 'center', '');
		draw_cdef_preview(grv('id'));
		html_end_box();

		html_start_box(__('CDEF Items'), '100%', false, 3, 'center', 'cdef.php?action=item_edit&cdef_id=' . $cdef['id'], false, false);

		$display_text = [
			['display' => __('Item'), 'align' => 'left'],
			['display' => __('Item Value'), 'align' => 'left']
		];

		html_header($display_text, 2);

		$cdef_items = db_fetch_assoc_prepared('SELECT *
			FROM cdef_items
			WHERE cdef_id = ?
			ORDER BY sequence',
			[grv('id')]);

		$i = 1;

		$total_items = cacti_sizeof($cdef_items);

		if (cacti_sizeof($cdef_items)) {
			foreach ($cdef_items as $cdef_item) {
				form_alternate_row('line' . $cdef_item['id'], true);

				form_selectable_cell(filter_value(__('Item # %d', $i), '', 'cdef.php?action=item_edit&id=' . $cdef_item['id'] . '&cdef_id=' . $cdef['id']), $cdef_item['id']);

				$item_value = '<em>' . $cdef_item_types[$cdef_item['type']] . '</em>' . htmle(get_cdef_item_name($cdef_item['id']));

				form_selectable_cell($item_value, $cdef_item['id']);

				$actions = '';

				if (read_config_option('drag_and_drop') == '') {
					if ($i < $total_items) {
						$actions .= '<a class="pic ti ti-caret-down-filled moveArrow" href="' . htmle('cdef.php?action=item_movedown&id=' . $cdef_item['id'] . '&cdef_id=' . $cdef_item['cdef_id']) . '" title="' . __esc('Move Down') . '"></a>';
					} else {
						$actions .= '<span class="moveArrowNone"></span>';
					}

					if ($i > 1 && $i <= $total_items) {
						$actions .= '<a class="pic ti ti-caret-up-filled moveArrow" href="' . htmle('cdef.php?action=item_moveup&id=' . $cdef_item['id'] . '&cdef_id=' . $cdef_item['cdef_id']) . '" title="' . __esc('Move Up') . '"></a>';
					} else {
						$actions .= '<span class="moveArrowNone"></span>';
					}
				}

				$actions .= "<a id='{$cdef['id']}_{$cdef_item['id']}' class='delete deleteMarker ti ti-x' title='" . __esc('Delete') . "' href='#'></a>";

				form_selectable_cell($actions, $cdef_item['id'], '', 'right');

				form_end_row();

				$i++;
			}
		}

		html_end_box();
	}

	form_save_button('cdef.php', 'return');

	?>
	<script type='text/javascript'>

	$(function() {
		$('#cdef_edit3').find('.cactiTable').attr('id', 'cdef_item');
		$('.cdialog').remove();
		$('#main').append("<div class='cdialog' id='cdialog'></div>");

		<?php if (read_config_option('drag_and_drop') == 'on') { ?>
		$('#cdef_item').find('tr:first').addClass('nodrag').addClass('nodrop');
		$('#cdef_item').tableDnD({
			onDrop: function(table, row) {
				loadUrl({url:'cdef.php?action=ajax_dnd&id=<?php isrv('id') ? print grv('id') : print 0; ?>&'+$.tableDnD.serialize()})
			}
		});
		<?php } ?>

		$('.delete').click(function(event) {
			event.preventDefault();

			id = $(this).attr('id').split('_');
			request = 'cdef.php?action=item_remove_confirm&id='+id[0]+'&cdef_id='+id[1];
			$.get(request)
				.done(function(data) {
					$('#cdialog').html(data);

					applySkin();

					$('#continue').off('click').on('click', function(data) {
						$.post('cdef.php?action=item_remove', {
							__csrf_magic: csrfMagicToken,
							cdef_id: $('#my_cdef_id').val(),
							id: $('#my_id').val()
						}).done(function(data) {
							$('#cdialog').dialog('close');
							$('.deleteMarker').blur();
							loadUrl({url:'cdef.php?action=edit&id='+$('#my_cdef_id').val()});
						});
					});

					$('#cdialog').dialog({
						title: '<?php print __('Delete CDEF Item'); ?>',
						minHeight: 80,
						minWidth: 500
					});
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		});
	});
	</script>
	<?php
}

function cdef() : void {
	global $actions, $item_rows;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('CDEFs'), 'cdef.php', 'form_cdef', 'sess_cdef', 'cdef.php?action=edit');

	$pageFilter->rows_label = __('CDEFs');
	$pageFilter->has_graphs = true;
	$pageFilter->render();

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = 'WHERE (name LIKE ' . db_qstr('%' . grv('filter') . '%') . ' AND `system` = 0)';
	} else {
		$sql_where = 'WHERE `system` = 0';
	}

	if (grv('has_graphs') == 'true') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' graphs > 0';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM cdef
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$cdef_list = db_fetch_assoc("SELECT *
		FROM cdef
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('cdef.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 5, __('CDEFs'), 'page', 'main');

	form_start('cdef.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'name' => [
			'display' => __('CDEF Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this CDEF.')
		],
		'nosort' => [
			'display' => __('Deletable'),
			'align'   => 'right',
			'tip'     => __('CDEFs that are in use cannot be Deleted.  In use is defined as being referenced by a Graph or a Graph Template.')
		],
		'graphs' => [
			'display' => __('Graphs Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Graphs using this CDEF.')
		],
		'templates' => [
			'display' => __('Templates Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Graphs Templates using this CDEF.')
		]
	];

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($cdef_list)) {
		foreach ($cdef_list as $cdef) {
			if ($cdef['graphs'] == 0 && $cdef['templates'] == 0) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			$graphs_url    = 'graphs.php?reset=1&cdef_id=' . $cdef['id'];
			$templates_url = 'graph_templates.php?reset=1&cdef_id=' . $cdef['id'];

			form_alternate_row('line' . $cdef['id'], false, $disabled);

			form_selectable_cell(filter_value($cdef['name'], grv('filter'), 'cdef.php?action=edit&id=' . $cdef['id']), $cdef['id']);
			form_selectable_cell($disabled ? __('No') : __('Yes'), $cdef['id'], '', 'right');
			form_selectable_cell(filter_value(number_format_i18n($cdef['graphs'], -1), '', $graphs_url), $cdef['id'], '', 'right');
			form_selectable_cell(filter_value(number_format_i18n($cdef['templates'], -1), '', $templates_url), $cdef['id'], '', 'right');

			form_checkbox_cell($cdef['name'], $cdef['id'], $disabled);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No CDEFs') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($cdef_list)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
