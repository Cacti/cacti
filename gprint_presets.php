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

$actions = [
	1 => __('Delete')
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
	case 'edit':
		top_header();

		gprint_presets_edit();

		bottom_footer();

		break;
	default:
		top_header();

		gprint_presets();

		bottom_footer();

		break;
}

function form_save() : void {
	if (isrv('save_component_gprint_presets')) {
		// ================= input validation =================
		gfrv('id');
		// ====================================================

		$save['id']          = grv('id');
		$save['hash']        = get_hash_gprint(grv('id'));
		$save['name']        = form_input_validate(gnrv('name'), 'name', '', false, 3);
		$save['gprint_text'] = form_input_validate(gnrv('gprint_text'), 'gprint_text', '', false, 3);

		if (!is_error_message()) {
			$gprint_preset_id = sql_save($save, 'graph_templates_gprint');

			if ($gprint_preset_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		} else {
			$gprint_preset_id = 0;
		}

		if (is_error_message()) {
			header('Location: gprint_presets.php?action=edit&id=' . (empty($gprint_preset_id) ? gnrv('id') : $gprint_preset_id));

			exit;
		} else {
			header('Location: gprint_presets.php');

			exit;
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
				db_execute('DELETE FROM graph_templates_gprint WHERE ' . array_to_sql_or($selected_items, 'id'));
			}
		}

		header('Location: gprint_presets.php');

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

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM graph_templates_gprint WHERE id = ?', [$matches[1]])) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'gprint_presets.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following GPRINT Preset.'),
					'pmessage' => __('Click \'Continue\' to Delete following GPRINT Presets.'),
					'scont'    => __('Delete GPRINT Preset'),
					'pcont'    => __('Delete GPRINT Presets')
				],
			]
		];

		form_continue_confirmation($form_data);
	}
}

function gprint_presets_edit() : void {
	global $fields_grprint_presets_edit;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	if (!ierv('id')) {
		$gprint_preset = db_fetch_row_prepared('SELECT * FROM graph_templates_gprint WHERE id = ?', [grv('id')]);
		$header_label  = __esc('GPRINT Presets [edit: %s]', $gprint_preset['name']);
	} else {
		$header_label = __('GPRINT Presets [new]');
	}

	form_start('gprint_presets.php', 'gprint_presets');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($fields_grprint_presets_edit, (isset($gprint_preset) ? $gprint_preset : []))
		]
	);

	html_end_box(true, true);

	form_save_button('gprint_presets.php');
}

function gprint_presets() : void {
	global $actions, $item_rows;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('GRPRINT Presets'), 'gprint_presets.php', 'form_gprint', 'sess_gp', 'gprint_presets.php?action=edit');

	$pageFilter->rows_label = __('GPRINTs');
	$pageFilter->has_graphs = true;
	$pageFilter->render();

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = 'WHERE (name LIKE ' . db_qstr('%' . grv('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (grv('has_graphs') == 'true') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' graphs > 0';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM graph_templates_gprint
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$gprint_list = db_fetch_assoc("SELECT *
		FROM graph_templates_gprint
		$sql_where
		$sql_order
		$sql_limit");

	$display_text = [
		'name' => [
			'display' => __('GPRINT Preset Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this GPRINT Preset.')
		],
		'gprint_text' => [
			'display' => __('Format'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The GPRINT format string.')
		],
		'nosort' => [
			'display' => __('Deletable'),
			'align'   => 'right',
			'tip'     => __('GPRINTs that are in use cannot be Deleted.  In use is defined as being referenced by either a Graph or a Graph Template.')
		],
		'graphs' => [
			'display' => __('Graphs Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Graphs using this GPRINT.')
		],
		'templates' => [
			'display' => __('Templates Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Graphs Templates using this GPRINT.')
		]
	];

	$nav = html_nav_bar('gprint_presets.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('GPRINTs'), 'page', 'main');

	form_start('gprint_presets.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($gprint_list)) {
		foreach ($gprint_list as $gp) {
			if ($gp['graphs'] == 0 && $gp['templates'] == 0) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			form_alternate_row('line' . $gp['id'], false, $disabled);

			form_selectable_cell(filter_value($gp['name'], grv('filter'), 'gprint_presets.php?action=edit&id=' . $gp['id']), $gp['id']);
			form_selectable_ecell($gp['gprint_text'], $gp['id'], '', 'right');
			form_selectable_cell($disabled ? __('No') : __('Yes'), $gp['id'], '', 'right');
			form_selectable_cell(number_format_i18n($gp['graphs'], -1), $gp['id'], '', 'right');
			form_selectable_cell(number_format_i18n($gp['templates'], -1), $gp['id'], '', 'right');
			form_checkbox_cell($gp['name'], $gp['id'], $disabled);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No GPRINT Presets') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($gprint_list)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
