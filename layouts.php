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

// Saved-layout create/rename/delete/publish requests (posted here or to any
// other filter page) are handled by filter_layouts_handle_request(), which is
// invoked from include/auth.php before this point. Only the management view
// remains here.

$actions = [
	1 => __('Delete'),
];

if (filter_layouts_can_manage_global()) {
	$actions[2] = __('Make Global');
	$actions[3] = __('Make Local');
}

set_default_action();

switch (get_nfilter_request_var('action')) {
	case 'actions':
		layouts_form_actions();

		break;
	default:
		top_header();

		layouts_manage();

		bottom_footer();

		break;
}

/**
 * Apply a bulk action (Delete, Make Global, Make Local) to the layouts selected
 * on the management list, showing the standard confirmation first.
 *
 * @return void
 */
function layouts_form_actions() : void {
	global $actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-zA-Z0-9_]+)$/']]);
	/* ==================================================== */

	$user_id = isset($_SESSION['sess_user_id']) ? (int) $_SESSION['sess_user_id'] : 0;

	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			$selected_items = array_values(array_map('intval', $selected_items));

			switch (get_nfilter_request_var('drp_action')) {
				case '1': // delete
					foreach ($selected_items as $id) {
						filter_layouts_delete($id);
					}

					break;
				case '2': // make global (publish to all users)
					foreach ($selected_items as $id) {
						filter_layouts_publish($id);
					}

					break;
				case '3': // make local to the current user
					foreach ($selected_items as $id) {
						filter_layouts_unpublish($id, $user_id);
					}

					break;
			}
		}

		header('Location: layouts.php');

		exit;
	}

	$ilist  = '';
	$iarray = [];

	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ==== input validation ==== */
			input_validate_input_number($matches[1], 'chk[1]');
			/* ========================== */

			$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM user_layouts WHERE id = ?', [$matches[1]])) . '</li>';
			$iarray[] = $matches[1];
		}
	}

	$form_data = [
		'general' => [
			'page'       => 'layouts.php',
			'actions'    => $actions,
			'optvar'     => 'drp_action',
			'item_array' => $iarray,
			'item_list'  => $ilist
		],
		'options' => [
			1 => [
				'smessage' => __('Click \'Continue\' to Delete the following Layout.'),
				'pmessage' => __('Click \'Continue\' to Delete the following Layouts.'),
				'scont'    => __('Delete Layout'),
				'pcont'    => __('Delete Layouts')
			],
			2 => [
				'smessage' => __('Click \'Continue\' to make the following Layout Global to all users.'),
				'pmessage' => __('Click \'Continue\' to make the following Layouts Global to all users.'),
				'scont'    => __('Make Layout Global'),
				'pcont'    => __('Make Layouts Global')
			],
			3 => [
				'smessage' => __('Click \'Continue\' to make the following Layout Local to you.'),
				'pmessage' => __('Click \'Continue\' to make the following Layouts Local to you.'),
				'scont'    => __('Make Layout Local'),
				'pcont'    => __('Make Layouts Local')
			]
		]
	];

	form_continue_confirmation($form_data);
}

/**
 * Render the Layouts management list with the standard preset filter bar and a
 * checkbox-driven actions dropdown. Administrators holding the Settings/Utilities
 * realm manage every user's layouts; other users manage only their own.
 *
 * @return void
 */
function layouts_manage() : void {
	global $actions;

	$is_admin = filter_layouts_can_manage_global();
	$user_id  = isset($_SESSION['sess_user_id']) ? (int) $_SESSION['sess_user_id'] : 0;

	$pageFilter = new CactiTableFilter(__('Layouts'), 'layouts.php', 'form_layouts', 'sess_layouts');
	$pageFilter->rows_label = __('Layouts');
	$pageFilter->render();

	$rows = (grv('rows') == '-1') ? read_config_option('num_rows_table') : grv('rows');
	$rows = (int) $rows;
	$page = (int) grv('page');

	$sql_where  = '';
	$sql_params = [];

	if (grv('filter') != '') {
		$sql_where    = 'WHERE ul.name LIKE ?';
		$sql_params[] = '%' . grv('filter') . '%';
	}

	if (!$is_admin) {
		$sql_where   .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'ul.user_id = ?';
		$sql_params[] = $user_id;
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM user_layouts AS ul
		$sql_where", $sql_params);

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . (($page - 1) * $rows) . ', ' . $rows;

	$layouts = db_fetch_assoc_prepared("SELECT ul.id, ul.user_id, ul.page, ul.name, ul.data, ua.username
		FROM user_layouts AS ul
		LEFT JOIN user_auth AS ua
		ON ua.id = ul.user_id
		$sql_where
		$sql_order
		$sql_limit", $sql_params);

	$nav = html_nav_bar('layouts.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 5, __('Layouts'), 'page', 'main');

	form_start('layouts.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'name' => [
			'display' => __('Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this Layout.')
		],
		'nosort' => [
			'display' => __('Page'),
			'align'   => 'left',
			'tip'     => __('The page this Layout applies to.')
		],
		'nosort2' => [
			'display' => __('Owner'),
			'align'   => 'left',
			'tip'     => __('The owner of this Layout, or Global when shared with all users.')
		],
		'nosort3' => [
			'display' => __('Filter'),
			'align'   => 'left',
			'tip'     => __('The stored filter this Layout applies.')
		]
	];

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($layouts)) {
		foreach ($layouts as $layout) {
			if ($layout['user_id'] == 0) {
				$owner = __('Global');
			} elseif (!empty($layout['username'])) {
				$owner = html_escape($layout['username']);
			} else {
				$owner = __('User %d', $layout['user_id']);
			}

			$document = filter_layouts_decode($layout['data']);
			$summary  = $document !== false ? filter_layouts_document_url($document) : '';

			if ($summary != '') {
				$sep      = (strpos($summary, '?') !== false) ? '&' : '?';
				$edit_url = $summary . $sep . 'filter_layout=' . $layout['id'];
			} else {
				$edit_url = '';
			}

			form_alternate_row('line' . $layout['id'], true);

			form_selectable_cell(filter_value($layout['name'], grv('filter'), $edit_url), $layout['id']);
			form_selectable_cell(html_escape(layouts_page_name($layout['page'])), $layout['id']);
			form_selectable_cell($owner, $layout['id']);
			form_selectable_cell(html_escape($summary), $layout['id']);
			form_checkbox_cell($layout['name'], $layout['id']);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Layouts Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($layouts)) {
		print $nav;
	}

	draw_actions_dropdown($actions);

	form_end();
}

/**
 * Resolve a layout's stored page basename to the friendly name the user sees in
 * the Console navigation (e.g. 'host.php' becomes 'Devices'), falling back to the
 * raw basename when the page is not registered.
 *
 * @param string $page The stored page basename.
 *
 * @return string
 */
function layouts_page_name(string $page) : string {
	global $navigation;

	if (isset($navigation[$page . ':']['title']) && $navigation[$page . ':']['title'] != '') {
		return $navigation[$page . ':']['title'];
	}

	return $page;
}

