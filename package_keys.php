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
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

$actions = [
	1 => __('Delete')
];

// set default action
set_default_action();

switch (grv('action')) {
	case 'actions':
		form_actions();

		break;
	default:
		top_header();

		public_keys();

		bottom_footer();

		break;
}

function form_actions() : void {
	global $actions;

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == '1') { // delete
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					package_key_remove($selected_items[$i]);
				}
			}
		}

		header('Location: package_keys.php?header=false');

		exit;
	}

	// setup some variables
	$p_list  = '';
	$p_array = [];

	// loop through each of the data queries and process them
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			// ================= input validation =================
			input_validate_input_number($matches[1]);
			// ====================================================

			$p_list .= '<li>' . htmle(db_fetch_cell_prepared('SELECT author FROM package_public_keys WHERE id = ?', [$matches[1]])) . '</li>';
			$p_array[] = $matches[1];
		}
	}

	top_header();

	form_start('package_keys.php', 'action_confirm');

	html_start_box($actions[gnrv('drp_action')], '60%', false, 3, 'center', '');

	if (cacti_sizeof($p_array)) {
		$save_html = '';

		if (gnrv('drp_action') == '1') { // delete
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to delete the following .', 'Click \'Continue\' to delete following Package Repositories.', cacti_sizeof($p_array)) . "</p>
					<div class='itemlist'><ul>$p_list</ul></div>
				</td>
			</tr>";

			$save_html = "<button type='button' class='ui-button ui-corner-all ui-widget' value='cancel' onClick='cactiReturnTo()'>" . __esc('Cancel') . "</button><button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' title='" . __n('Delete Public Key', 'Delete Public Keys', cacti_sizeof($p_array)) . "' value='continue'>" . __esc('Continue') . '</button>';
		}
	} else {
		raise_message(40);
		header('Location: package_keys.php?header=false');

		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . serialize($p_array) . "'>
			<input type='hidden' name='drp_action' value='" . htmle(gnrv('drp_action')) . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function package_key_remove(int $id) : void {
	db_execute_prepared('DELETE FROM package_public_keys WHERE id = ?', [$id]);
}

function public_keys() : void {
	global $actions, $item_rows, $types;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Public Keys'), 'package_keys.php', 'fors', 'sess_package_keys');

	$pageFilter->rows_label = __('Keys');
	$pageFilter->set_sort_array('author', 'ASC');
	$pageFilter->render();

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$sql_where  = '';
	$sql_params = [];

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = ($sql_where != '' ? ' AND ' : 'WHERE ') .
			'(author LIKE ? OR homepage LIKE ? OR email_address LIKE ?)';

		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM package_public_keys
		$sql_where",
		$sql_params);

	$keys = db_fetch_assoc_prepared("SELECT *
		FROM package_public_keys
		$sql_where
		$sql_order
		$sql_limit",
		$sql_params);

	$display_text = [
		'author' => [
			'display' => __('Author'),
			'sort'    => 'ASC'
		],
		'id' => [
			'display' => __('ID'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'homepage' => [
			'display' => __('Home Page'),
			'sort'    => 'ASC'
		],
		'email_address' => [
			'display' => __('Support EMail Address'),
			'sort'    => 'ASC'
		],
		'nosort' => [
			'display' => __('Key Type'),
		],
	];

	$nav = html_nav_bar('package_keys.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, sizeof($display_text) + 1, __('Package Public Keys'), 'page', 'main');

	form_start('package_keys.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($keys)) {
		foreach ($keys as $key) {
			form_alternate_row('line' . $key['id'], true);

			$pkey = $key['public_key'];

			form_selectable_cell(filter_value($key['author'], grv('filter')), $key['id']);
			form_selectable_cell($key['id'], $key['id']);
			form_selectable_cell(filter_value($key['homepage'], grv('filter')), $key['id']);
			form_selectable_cell(filter_value($key['email_address'], grv('filter')), $key['id']);
			form_selectable_cell(strlen($pkey) < 200 ? 'SHA1' : 'SHA256', $key['id']);

			form_checkbox_cell($key['author'], $key['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Package Public Keys Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($keys)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
