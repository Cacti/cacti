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
require_once(CACTI_PATH_LIBRARY . '/vdef.php');

$actions = [
	'1' => __('Delete'),
	'2' => __('Duplicate')
];

set_default_action();

switch (grv('action')) {
	case 'save':
		vdef_form_save();

		break;
	case 'actions':
		vdef_form_actions();

		break;
	case 'item_remove_confirm':
		vdef_item_remove_confirm();

		break;
	case 'item_remove':
		vdef_item_remove();

		break;
	case 'item_movedown':
		gfrv('vdef_id');

		item_movedown();

		header('Location: vdef.php?action=edit&id=' . grv('vdef_id'));

		break;
	case 'item_moveup':
		gfrv('vdef_id');

		item_moveup();

		header('Location: vdef.php?action=edit&id=' . grv('vdef_id'));

		break;
	case 'item_edit':
		top_header();
		vdef_item_edit();
		bottom_footer();

		break;
	case 'edit':
		top_header();

		vdef_edit();

		bottom_footer();

		break;
	case 'ajax_dnd':
		vdef_item_dnd();

		break;
	default:
		top_header();

		vdef();

		bottom_footer();

		break;
}

function draw_vdef_preview(int $vdef_id) : void {
	?>
	<tr class='even'>
		<td style='padding:4px'>
			<pre>vdef=<?php print htmle(get_vdef($vdef_id, true)); ?></pre>
		</td>
	</tr>
	<?php
}

function vdef_form_save() : void {
	if (isrv('save_component_vdef')) {
		$save['id']   = gfrv('id');
		$save['hash'] = get_hash_vdef(grv('id'));
		$save['name'] = form_input_validate(gnrv('name'), 'name', '', false, 3);

		if (!is_error_message()) {
			$vdef_id = sql_save($save, 'vdef');

			if ($vdef_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: vdef.php?action=edit&id=' . (empty($vdef_id) ? grv('id') : $vdef_id));
	} elseif (isrv('save_component_item')) {
		$sequence = get_sequence(gfrv('id'), 'sequence', 'vdef_items', 'vdef_id=' . gfrv('vdef_id'));

		$save['id']       = gfrv('id');
		$save['hash']     = get_hash_vdef(grv('id'), 'vdef_item');
		$save['vdef_id']  = gfrv('vdef_id');
		$save['sequence'] = $sequence;
		$save['type']     = gnrv('type');
		$save['value']    = gnrv('value');

		$vdef_item_id     = 0;

		if (!is_error_message()) {
			$vdef_item_id = sql_save($save, 'vdef_items');

			if ($vdef_item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: vdef.php?action=item_edit&vdef_id=' . grv('vdef_id') . '&id=' . (empty($vdef_item_id) ? grv('id') : $vdef_item_id));
		} else {
			header('Location: vdef.php?action=edit&id=' . grv('vdef_id'));
		}
	}
}

function duplicate_vdef(int $_vdef_id, string $vdef_title) : void {
	global $fields_vdef_edit;

	$vdef       = db_fetch_row_prepared('SELECT * FROM vdef WHERE id = ?', [$_vdef_id]);
	$vdef_items = db_fetch_assoc_prepared('SELECT * FROM vdef_items WHERE vdef_id = ?', [$_vdef_id]);

	// substitute the title variable
	$vdef['name'] = str_replace('<vdef_title>', $vdef['name'], $vdef_title);

	// create new entry: device_template
	$save['id']   = 0;
	$save['hash'] = get_hash_vdef(0);

	$fields_vdef_edit = preset_vdef_form_list();

	foreach ($fields_vdef_edit as $field => $array) {
		if (!preg_match('/^hidden/', $array['method'])) {
			$save[$field] = $vdef[$field];
		}
	}

	$vdef_id = sql_save($save, 'vdef');

	// create new entry(s): vdef_items
	if (cacti_sizeof($vdef_items) > 0) {
		foreach ($vdef_items as $vdef_item) {
			unset($save);

			$save['id']       = 0;
			$save['hash']     = get_hash_vdef(0, 'vdef_item');
			$save['vdef_id']  = $vdef_id;
			$save['sequence'] = $vdef_item['sequence'];
			$save['type']     = $vdef_item['type'];
			$save['value']    = $vdef_item['value'];

			sql_save($save, 'vdef_items');
		}
	}
}

function vdef_form_actions() : void {
	global $actions;

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') === '1') { // delete
				// do a referential integrity check
				if (cacti_sizeof($selected_items)) {
					foreach ($selected_items as $vdef_id) {
						// ================= input validation =================
						input_validate_input_number($vdef_id, 'vdef_id');
						// ====================================================

						$vdef_ids[] = $vdef_id;
					}
				}

				if (isset($vdef_ids)) {
					db_execute('DELETE FROM vdef WHERE ' . array_to_sql_or($vdef_ids, 'id'));
					db_execute('DELETE FROM vdef_items WHERE ' . array_to_sql_or($vdef_ids, 'vdef_id'));
				}
			} elseif (gnrv('drp_action') === '2') { // duplicate
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					// ================= input validation =================
					input_validate_input_number($selected_items[$i], "selected_items[$i]");
					// ====================================================

					duplicate_vdef($selected_items[$i], gnrv('title_format'));
				}
			}
		}

		header('Location: vdef.php');

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

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM vdef WHERE id = ?', [$matches[1]])) . '</li>';
				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'vdef.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following VDEF.'),
					'pmessage' => __('Click \'Continue\' to Delete following VDEFs.'),
					'scont'    => __('Delete VDEF'),
					'pcont'    => __('Delete VDEFs')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Duplicate the following VDEF.'),
					'pmessage' => __('Click \'Continue\' to Duplicate following VDEFs.'),
					'scont'    => __('Duplicate VDEF'),
					'pcont'    => __('Duplicate VDEFs'),
					'extra'    => [
						'title_format' => [
							'method'  => 'textbox',
							'title'   => __('Title Format'),
							'default' => '<vdef_title>',
							'width'   => 25
						]
					]
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}

function vdef_item_remove_confirm() : void {
	global $vdef_functions, $vdef_item_types, $custom_vdef_data_source_types;

	// ================= input validation =================
	gfrv('id');
	gfrv('vdef_id');
	// ====================================================

	// sort the vdef functions
	asort($vdef_functions);

	form_start('vdef.php');

	html_start_box('', '100%', false, 3, 'center', '');

	$vdef       = db_fetch_row_prepared('SELECT * FROM vdef WHERE id = ?', [grv('id')]);
	$vdef_item  = db_fetch_row_prepared('SELECT * FROM vdef_items WHERE id = ?', [grv('vdef_id')]);

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following VDEF\'s.'); ?></p>
			<p><?php print __esc('VDEF Name: %s', $vdef['name']); ?><br>
			<em><?php $vdef_item_type = $vdef_item['type'];
	print $vdef_item_types[$vdef_item_type]; ?></em>: <strong><?php print htmle(get_vdef_item_name($vdef_item['id'])); ?></strong></p>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel' name='cancel' onClick='$("#cdialog").dialog("close");'><?php print __esc('Cancel'); ?></button>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='continue' name='continue' title='<?php print __esc('Remove VDEF Item'); ?>'><?php print __esc('Continue'); ?></button>
			<input type='hidden' id='my_vdef_id' value='<?php print $vdef['id']; ?>'>
			<input type='hidden' id='my_id' value='<?php print $vdef_item['id']; ?>'>
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
				url: 'vdef.php?action=item_remove',
				funcEnd: 'removeVdefItemFinalize'
			}

			var data = {
				__csrf_magic: csrfMagicToken,
				vdef_id: <?php print grv('vdef_id'); ?>,
				id: <?php print grv('id'); ?>
			}
		});
	});

	function removeVdefItemFinalize(data) {
		$('#cdialog').dialog('close');
		loadUrl({url:'vdef.php?action=edit&id=<?php print grv('id'); ?>'})
	}
	</script>
	<?php
}

function vdef_item_remove() : void {
	// ================= input validation =================
	gfrv('vdef_id');
	// ====================================================

	db_execute_prepared('DELETE FROM vdef_items
		WHERE id = ?',
		[grv('id')]);
}

function vdef_item_edit() : void {
	global $vdef_functions, $vdef_item_types, $custom_vdef_data_source_types;

	// ================= input validation =================
	gfrv('id');
	gfrv('vdef_id');
	gfrv('type_select');
	// ====================================================

	// sort the vdef functions
	asort($vdef_functions);

	if (!ierv('id')) {
		$vdef = db_fetch_row_prepared('SELECT *
			FROM vdef_items
			WHERE id = ?',
			[grv('id')]);

		if (cacti_sizeof($vdef)) {
			$current_type          = $vdef['type'];
			$values[$current_type] = $vdef['value'];
		}
	} else {
		$vdef = [];
	}

	html_start_box(__('VDEF Preview'), '100%', false, 3, 'center', '');
	draw_vdef_preview(grv('vdef_id'));
	html_end_box();

	if (!ierv('vdef_id')) {
		$name = db_fetch_cell_prepared('SELECT name
			FROM vdef
			WHERE id = ?',
			[grv('vdef_id')]);

		$header_label = __esc('VDEF Items [edit: %s]', $name);
	} else {
		$header_label = __('VDEF Items [new]');
	}

	form_start('vdef.php', 'chk');

	html_start_box($header_label, '100%', false, 3, 'center', '');

	if (isrv('type_select')) {
		$current_type = grv('type_select');
	} elseif (isset($vdef['type'])) {
		$current_type = $vdef['type'];
	} else {
		$current_type = CVDEF_ITEM_TYPE_FUNCTION;
	}

	$form_vdef = [
		'type_select' => [
			'method'        => 'drop_array',
			'friendly_name' => __('VDEF Item Type'),
			'description'   => __('Choose what type of VDEF item this is.'),
			'value'         => $current_type,
			'array'         => $vdef_item_types
		],
		'value' => [
			'method'        => 'drop_array',
			'friendly_name' => __('VDEF Item Value'),
			'description'   => __('Enter a value for this VDEF item.'),
			'value'         => (isset($vdef['value']) ? $vdef['value'] : '')
		],
		'id' => [
			'method'        => 'hidden',
			'value'         => isrv('id') ? grv('id') : '0',
		],
		'type' => [
			'method'        => 'hidden',
			'value'         => $current_type
		],
		'vdef_id' => [
			'method'        => 'hidden',
			'value'         => grv('vdef_id')
		],
		'save_component_item' => [
			'method'        => 'hidden',
			'value'         => '1'
		]
	];

	switch ($current_type) {
		case '1':
			$form_vdef['value']['array'] = $vdef_functions;

			break;
		case '4':
			$form_vdef['value']['array'] = $custom_vdef_data_source_types;

			break;
		case '6':
			$form_vdef['value']['method']     = 'textbox';
			$form_vdef['value']['max_length'] = '255';
			$form_vdef['value']['size']       = '30';

			break;
	}

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($form_vdef, $vdef)
		]
	);

	?>
	<script type='text/javascript'>
	$(function() {
		$('#type_select').unbind().change(function() {
			strURL  = 'vdef.php?action=item_edit';
			strURL += '&id=' + $('#id').val();
			strURL += '&vdef_id=' + $('#vdef_id').val();
			strURL += '&type_select=' + $('#type_select').val();
			loadUrl({url:strURL})
		});
	});
	</script>
	<?php

	html_end_box();

	form_save_button('vdef.php?action=edit&id=' . grv('vdef_id'));
}

function item_movedown() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('vdef_id');
	// ====================================================

	move_item_down('vdef_items', grv('id'), 'vdef_id=' . grv('vdef_id'));
}

function item_moveup() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('vdef_id');
	// ====================================================

	move_item_up('vdef_items', grv('id'), 'vdef_id=' . grv('vdef_id'));
}

function vdef_item_dnd() : void {
	// ================= Input validation =================
	gfrv('id');
	// ================= Input validation =================

	$continue = true;

	if (isrv('vdef_item') && is_array(gnrv('vdef_item'))) {
		$vdef_ids = gnrv('vdef_item');

		if (cacti_sizeof($vdef_ids)) {
			$sequence = 1;

			foreach ($vdef_ids as $vdef_id) {
				$vdef_id = str_replace('line', '', $vdef_id);
				input_validate_input_number($vdef_id, 'vdef_id');

				db_execute_prepared('UPDATE vdef_items
					SET sequence = ?
					WHERE id = ?',
					[$sequence, $vdef_id]);

				$sequence++;
			}
		}
	}

	header('Location: vdef.php?action=edit&id=' . grv('id'));
}

function vdef_edit() : void {
	global $vdef_item_types;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	if (!ierv('id')) {
		$vdef = db_fetch_row_prepared('SELECT *
			FROM vdef
			WHERE id = ?',
			[grv('id')]);

		$header_label = __esc('VDEFs [edit: %s]', $vdef['name']);
	} else {
		$vdef = [];

		$header_label = __('VDEFs [new]');
	}

	form_start('vdef.php', 'vdef_edit');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	$preset_vdef_form_list = preset_vdef_form_list();
	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($preset_vdef_form_list, $vdef)
		]
	);

	html_end_box(true, true);

	form_hidden_box('id', (isset($vdef['id']) ? $vdef['id'] : '0'), '');
	form_hidden_box('save_component_vdef', '1', '');

	if (cacti_sizeof($vdef) && !ierv('id')) {
		html_start_box('', '100%', false, 3, 'center', '');
		draw_vdef_preview(grv('id'));
		html_end_box();

		html_start_box(__('VDEF Items'), '100%', false, 3, 'center', 'vdef.php?action=item_edit&vdef_id=' . $vdef['id'], false, false);

		$header_items = [
			['display' => __('Item'), 'align' => 'left'],
			['display' => __('Item Value'), 'align' => 'left']
		];

		html_header($header_items, 2);

		$vdef_items = db_fetch_assoc_prepared('SELECT *
			FROM vdef_items
			WHERE vdef_id = ?
			ORDER BY sequence',
			[grv('id')]);

		$i           = 1;
		$total_items = cacti_sizeof($vdef_items);

		if (cacti_sizeof($vdef_items)) {
			foreach ($vdef_items as $vdef_item) {
				form_alternate_row('line' . $vdef_item['id'], true);

				form_selectable_cell(filter_value(__('Item # %d', $i), '', 'vdef.php?action=item_edit&id=' . $vdef_item['id'] . '&vdef_id=' . $vdef['id']), $vdef_item['id']);

				$item_value = '<em>' . $vdef_item_types[$vdef_item['type']] . '</em>' . htmle(get_vdef_item_name($vdef_item['id']));

				form_selectable_cell($item_value, $vdef_item['id']);

				$actions = '';

				if (read_config_option('drag_and_drop') == '') {
					if ($i < $total_items) {
						$actions .= '<a class="pic ti ti-caret-down-filled moveArrow" href="' . htmle('vdef.php?action=item_movedown&id=' . $vdef_item['id'] . '&vdef_id=' . $vdef_item['vdef_id']) . '" title="' . __esc('Move Down') . '"></a>';
					} else {
						$actions .= '<span class="moveArrowNone"></span>';
					}

					if ($i > 1 && $i <= $total_items) {
						$actions .= '<a class="pic ti ti-caret-up-filled moveArrow" href="' . htmle('vdef.php?action=item_moveup&id=' . $vdef_item['id'] . '&vdef_id=' . $vdef_item['vdef_id']) . '" title="' . __esc('Move Up') . '"></a>';
					} else {
						$actions .= '<span class="moveArrowNone"></span>';
					}
				}

				$actions .= "<a id='{$vdef['id']}_{$vdef_item['id']}' class='delete deleteMarker ti ti-x' title='" . __esc('Delete') . "' href='#'></a>";

				form_selectable_cell($actions, $vdef_item['id'], '', 'right');

				form_end_row();

				$i++;
			}
		}

		html_end_box();
	}

	form_save_button('vdef.php', 'return');

	?>
	<script type='text/javascript'>

	$(function() {
		$('#vdef_edit3').find('.cactiTable').attr('id', 'vdef_item');
		$('.cdialog').remove();
		$('#main').append("<div class='cdialog' id='cdialog'></div>");

		<?php if (read_config_option('drag_and_drop') == 'on') { ?>
		$('#vdef_item').find('tr:first').addClass('nodrag').addClass('nodrop');
		$('#vdef_item').unbind().tableDnD({
			onDrop: function(table, row) {
				loadUrl({url:'vdef.php?action=ajax_dnd&id=<?php isrv('id') ? print grv('id') : print 0; ?>&'+$.tableDnD.serialize()})
			}
		});
		<?php } ?>

		$('.delete').unbind().click(function (event) {
			event.preventDefault();

			id = $(this).attr('id').split('_');
			request = 'vdef.php?action=item_remove_confirm&id='+id[0]+'&vdef_id='+id[1];
			$.get(request)
				.done(function(data) {
					$('#cdialog').html(data);

					applySkin();

					$('#continue').off('click').on('click', function(data) {
						$.post('vdef.php?action=item_remove', {
							__csrf_magic: csrfMagicToken,
							vdef_id: $('#my_vdef_id').val(),
							id: $('#my_id').val()
						}).done(function(data) {
							$('#cdialog').dialog('close');
							loadUrl({url:'vdef.php?action=edit&id='+$('#my_vdef_id').val()});
						});
					});

					$('#cdialog').dialog({
						title: '<?php print __esc('Delete VDEF Item'); ?>',
						close: function () { $('.delete').blur(); $('.selectable').removeClass('selected'); },
						minHeight: 80,
						minWidth: 500
					});
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		}).css('cursor', 'pointer');
	});

	</script>
	<?php
}

function get_vdef_records(int &$total_rows, int &$rows) : mixed {
	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = 'WHERE name LIKE ' . db_qstr('%' . grv('filter') . '%');
	} else {
		$sql_where = '';
	}

	if (grv('has_graphs') == 'true') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' graphs > 0';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*)
        FROM vdef
        $sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	return db_fetch_assoc("SELECT *
		FROM vdef
		$sql_where
		$sql_order
		$sql_limit");
}

function vdef(bool $refresh = true) : void {
	global $actions;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('VDEFs'), 'vdef.php', 'form_vdef', 'sess_vdef', 'vdef.php?action=edit');

	$pageFilter->rows_label = __('VDEFs');
	$pageFilter->has_graphs = true;
	$pageFilter->render();

	$total_rows = 0;
	$vdefs      = [];

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$vdefs = get_vdef_records($total_rows, $rows);

	$nav = html_nav_bar('vdef.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 5, __('VDEFs'), 'page', 'main');

	form_start('vdef.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'name' => [
			'display' => __('VDEF Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __esc('The name of this VDEF.')
		],
		'nosort' => [
			'display' => __('Deletable'),
			'align'   => 'right',
			'tip'     => __esc('VDEFs that are in use cannot be Deleted. In use is defined as being referenced by a Graph or a Graph Template.')
		],
		'graphs' => [
			'display' => __('Graphs Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __esc('The number of Graphs using this VDEF.')
		],
		'templates' => [
			'display' => __('Templates Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __esc('The number of Graphs Templates using this VDEF.')
		]
	];

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($vdefs)) {
		foreach ($vdefs as $vdef) {
			if ($vdef['graphs'] == 0 && $vdef['templates'] == 0) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			$graphs_url    = 'graphs.php?reset=1&vdef_id=' . $vdef['id'];
			$templates_url = 'graph_templates.php?reset=1&vdef_id=' . $vdef['id'];

			form_alternate_row('line' . $vdef['id'], false, $disabled);

			form_selectable_cell(filter_value($vdef['name'], grv('filter'), 'vdef.php?action=edit&id=' . $vdef['id']), $vdef['id']);
			form_selectable_cell($disabled ? __('No') : __('Yes'), $vdef['id'], '', 'right');
			form_selectable_cell(filter_value(number_format_i18n($vdef['graphs'], -1), '', $graphs_url), $vdef['id'], '', 'right');
			form_selectable_cell(filter_value(number_format_i18n($vdef['templates'], -1), '', $templates_url), $vdef['id'], '', 'right');

			form_checkbox_cell($vdef['name'], $vdef['id'], $disabled);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No VDEFs') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($vdefs)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
