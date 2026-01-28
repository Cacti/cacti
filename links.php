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

$actions = [
	1 => __('Delete'),
	3 => __('Enable'),
	2 => __('Disable')
];

set_default_action();

switch (grv('action')) {
	case 'actions':
		form_actions();

		break;
	case 'delete_page':
		if (isrv('id') && gfrv('id')) {
			page_delete(grv('id'));
		}

		header('Location: links.php');

		break;
	case 'move_page_up':
		if (isrv('id') && gfrv('id') && isrv('order') && gfrv('order')) {
			page_move(grv('id'), -1);
		}

		header('Location: links.php');

		break;
	case 'move_page_down':
		if (isrv('id') && gfrv('id') && isrv('order') && gfrv('order')) {
			page_move(grv('id'), 1);
		}

		header('Location: links.php');

		break;
	case 'ajax_dnd':
		$new_order = gnrv('dnd');

		links_reorder($new_order);

		header('Location: links.php');

		break;
	case 'save':
		$save['id']      = isrv('id') ? gfrv('id') : 0;
		$save['title']   = form_input_validate(gnrv('title'), 'title', '', false, 3);
		$save['style']   = gnrv('style');
		$save['enabled'] = (isrv('enabled') ? 'on' : '');
		$save['refresh'] = form_input_validate(gnrv('refresh'), 'refresh', '^[0-9]+$', false, 3);

		if (preg_match('/^((((ht|f)tp(s?))\:\/\/){1}\S+)/i', gnrv('fileurl')) && gnrv('filename') == '0') {
			if (filter_var(gnrv('fileurl'), FILTER_VALIDATE_URL)) {
				$save['contentfile'] = gnrv('fileurl');
			} else {
				$_SESSION['sess_error_fields']['contentfile'] = 'contentfile';
				raise_message('badurl', __('Your contentfile is not a valid URL.  Please enter a value URL'), MESSAGE_LEVEL_ERROR);
			}
		} else {
			$save['contentfile'] = preg_replace('/[^A-Za-z0-9_\.-]/', '_', gnrv('filename'));
		}

		$consolesection    = gnrv('consolesection');
		$consolenewsection = gnrv('consolenewsection');
		$extendedstyle     = '';
		$lastsortorder     = db_fetch_cell('SELECT MAX(sortorder) FROM external_links');
		$save['sortorder'] = $lastsortorder + 1;

		if ($save['style'] == 'CONSOLE') {
			if ($consolesection == '__NEW__') {
				$extendedstyle = $consolenewsection;
			} else {
				$extendedstyle = $consolesection;
			}

			if ($extendedstyle == '') {
				$extendedstyle = __('External Links');
			}
		}
		$save['extendedstyle'] = $extendedstyle;

		if (!is_error_message()) {
			$id = sql_save($save, 'external_links');

			// always give the login account access
			db_execute_prepared('REPLACE INTO user_auth_realm (user_id, realm_id) VALUES (?, ?)', [$_SESSION[SESS_USER_ID], $id + 10000]);

			raise_message(1);

			header('Location: links.php');

			exit;
		} else {
			raise_message(2);

			header('Location: links.php?action=edit&id=' . (isrv('id') ? gfrv('id') : ''));

			exit;
		}
	case 'edit':
		top_header();

		edit_page();

		bottom_footer();

		break;
	default:
		top_header();

		pages();

		bottom_footer();

		break;
}

function links_reorder(array $new_order) : void {
	if (cacti_sizeof($new_order)) {
		$sort = 1;

		foreach ($new_order as $l) {
			$link_id = str_replace('line', '', $l);

			db_execute_prepared('UPDATE external_links
				SET sortorder = ?
				WHERE id = ?',
				[$sort, $link_id]);

			$sort++;
		}
	}
}

function form_actions() : void {
	global $actions;

	// ================= input validation =================
	gfrv('drp_action');
	// ====================================================

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (grv('drp_action') == '3') { // Enable Page
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					db_execute_prepared("UPDATE external_links SET enabled='on' WHERE id = ?", [$selected_items[$i]]);
				}
			} elseif (grv('drp_action') == '2') { // Disable Page
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					db_execute_prepared("UPDATE external_links SET enabled='' WHERE id = ?", [$selected_items[$i]]);
				}
			} elseif (grv('drp_action') == '1') { // Delete Page
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					db_execute_prepared('DELETE FROM external_links WHERE id = ?', [$selected_items[$i]]);
					db_execute_prepared('DELETE FROM user_auth_realm WHERE realm_id = ?', [$selected_items[$i] + 10000]);
					db_execute_prepared('DELETE FROM user_auth_group_realm WHERE realm_id = ?', [$selected_items[$i] + 10000]);
				}
			}
		}

		header('Location: links.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		// loop through each of the pages selected on the previous page and get more info about them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT title FROM external_links WHERE id = ?', [$matches[1]])) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'links.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following External Link.'),
					'pmessage' => __('Click \'Continue\' to Delete following External Links.'),
					'scont'    => __('Delete External Link'),
					'pcont'    => __('Delete External Links')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Disable the following External Link.'),
					'pmessage' => __('Click \'Continue\' to Disable following External Links.'),
					'scont'    => __('Disable External Link'),
					'pcont'    => __('Disable External Links')
				],
				3 => [
					'smessage' => __('Click \'Continue\' to Enable the following External Link.'),
					'pmessage' => __('Click \'Continue\' to Enable following External Links.'),
					'scont'    => __('Enable External Link'),
					'pcont'    => __('Enable External Links'),
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}

function pages() : void {
	global $item_rows, $actions;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('External Links'), 'links.php', 'links', 'sess_links', 'links.php?action=edit');

	$pageFilter->rows_label = __('Receivers');
	$pageFilter->set_sort_array('sortorder', 'ASC');
	$pageFilter->render();

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$style_translate = [
		'CONSOLE'    => __('Console'),
		'TAB'        => __('Top Tab'),
		'FRONT'      => __('Bottom Console'),
		'FRONTTOP'   => __('Top Console')
	];

	if (grv('filter') != '') {
		$sql_where = ' WHERE title LIKE ' . db_qstr('%' . grv('filter') . '%') . ' OR contentfile LIKE ' . db_qstr('%' . grv('filter') . '%');
	} else {
		$sql_where = '';
	}

	$sql_order = get_order_string();
	$sql_order = str_replace('sortorder DESC', 'sortorder ASC', $sql_order);
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$pages = db_fetch_assoc("SELECT *
		FROM external_links
		$sql_where
		$sql_order
		$sql_limit");

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM external_links
		$sql_where");

	form_start('links.php', 'chk');

	$nav = html_nav_bar('links.php', MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 8, __('External Links'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'nosort0' => [
			'display' => __('Actions'),
			'align'   => 'left',
			'sort'    => ''
		],
		'contentfile' => [
			'display' => __('Page'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'title' => [
			'display' => __('Title'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'style' => [
			'display' => __('Style'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'disabled' => [
			'display' => __('Enabled'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'sortorder' => [
			'display' => __('Order'),
			'align'   => 'center',
			'sort'    => 'ASC'
		]
	];

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'));

	$i = 0;

	if (cacti_sizeof($pages)) {
		foreach ($pages as $page) {
			form_alternate_row('line' . $page['id']);

			$menuicons = '<a class="pic"  href="' . htmle('links.php?action=edit&id=' . $page['id']) . '" title="' . __esc('Edit Page') . '"><i class="ti ti-edit editTemplate"></i></a>';

			if ($page['enabled'] == 'on') {
				$menuicons .= '<a class="pic" href="' . htmle('link.php?id=' . $page['id']) . '" title="' . __esc('View Page') . '"><i class="ti ti-file deviceUp"></i></a>';
			}

			form_selectable_cell($menuicons, $page['id'], '3%');
			form_selectable_ecell($page['contentfile'], $page['id']);
			form_selectable_ecell($page['title'], $page['id']);
			form_selectable_ecell($style_translate[$page['style']] . ($page['style'] == 'CONSOLE' ? ' ( ' . ($page['extendedstyle'] == '' ? __('External Links') : $page['extendedstyle']) . ' )' : ''), $page['id']);

			form_selectable_cell(($page['enabled'] == 'on' ? __('Yes') : __('No')), $page['id']);

			if (grv('sort_column') == 'sortorder') {
				if ($i != 0) {
					$sort = '<a class="pic ti ti-caret-up-filled moveArrow" href="' . htmle('links.php?action=move_page_up&order=' . $page['sortorder'] . '&id=' . $page['id']) . '"></a>';
				} else {
					$sort = '<span class="moveArrowNone"></span>';
				}

				if ($i == cacti_sizeof($pages) - 1) {
					$sort .= '<span class="moveArrowNone"></span>';
				} else {
					$sort .= '<a class="pic ti ti-caret-down-filled moveArrow" href="' . htmle('links.php?action=move_page_down&order=' . $page['sortorder'] . '&id=' . $page['id']) . '"></a>';
				}

				form_selectable_cell($sort, $page['id'], '', 'center');
			} else {
				form_selectable_cell(__('Sort for Ordering'), $page['id']);
			}

			form_checkbox_cell($page['title'], $page['id']);
			form_end_row();

			$i++;
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($nav) + 1) . "'><em>" . __('No Pages Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($pages)) {
		print $nav;
	}

	draw_actions_dropdown($actions);

	form_end();

	if (grv('sort_column') == 'sortorder' && read_config_option('drag_and_drop') == 'on') {
		?>
		<script type='text/javascript'>
		$(function() {
			$('#links2_child').attr('id', 'dnd');

			$('#dnd').find('tr:first').addClass('nodrag').addClass('nodrop');
			$('#dnd').tableDnD({
				onDrop: function(table, row) {
					loadUrl({url:'links.php?action=ajax_dnd&'+$.tableDnD.serialize()})
				}
			});
		});
		</script>
		<?php
	}
}

function page_delete(int $id) : void {
	db_execute_prepared('DELETE FROM external_links WHERE id = ?', [$id]);
	db_execute_prepared('DELETE FROM user_auth_realm WHERE realm_id = ?', [$id + 10000]);
	db_execute_prepared('DELETE FROM user_auth_group_realm WHERE realm_id = ?', [$id + 10000]);

	page_resort();
}

function page_resort() : void {
	$pages = db_fetch_assoc('SELECT * FROM external_links ORDER BY sortorder');

	$i = 1;

	if (cacti_sizeof($pages)) {
		foreach ($pages as $page) {
			db_execute_prepared('UPDATE external_links SET sortorder = ? WHERE id = ?', [$i, $page['id']]);
			$i++;
		}
	}
}

function page_move(int $pageid, int $direction) : void {
	$oldorder = db_fetch_cell_prepared('SELECT sortorder FROM external_links WHERE id = ?', [$pageid]);
	$neworder = $oldorder + $direction;
	$otherid  = db_fetch_cell_prepared('SELECT id FROM external_links WHERE sortorder = ?', [$neworder]);

	if (!empty($otherid)) {
		db_execute_prepared('UPDATE external_links SET sortorder = ? WHERE id = ?', [$neworder, $pageid]);
		db_execute_prepared('UPDATE external_links SET sortorder = ? WHERE id = ?', [$oldorder, $otherid]);
	}
}

function edit_page() : void {
	global $poller_intervals;

	$sections = db_fetch_assoc("SELECT extendedstyle
		FROM external_links
		WHERE style='CONSOLE'
		GROUP BY extendedstyle
		ORDER BY extendedstyle");

	$sec_ar                   = [];
	$sec_ar['External Links'] = __('External Links');

	foreach ($sections as $sec) {
		if ($sec['extendedstyle'] != '') {
			$sec_ar[$sec['extendedstyle']] = $sec['extendedstyle'];
		}
	}
	$sec_ar['__NEW__'] = 'New Name Below';

	if (isrv('id')) {
		$data = db_fetch_row_prepared('SELECT * FROM external_links WHERE id = ?', [gfrv('id')]);
	} else {
		$data = [];
	}

	$myrefresh[0] = __('Disabled');
	$myrefresh   += $poller_intervals;

	$field_array = [
		'id' => [
			'friendly_name' => __('Style'),
			'method'        => 'hidden',
			'value'         => isrv('id') ? grv('id') : 0
		],
		'style' => [
			'friendly_name' => __('Style'),
			'method'        => 'drop_array',
			'array'         => [
				'TAB'        => __('Top Tab'),
				'CONSOLE'    => __('Console Menu'),
				'FRONT'      => __('Bottom of Console Page'),
				'FRONTTOP'   => __('Top of Console Page')
			],
			'description' => __('Where should this page appear?'),
			'value'       => (isset($data['style']) ? $data['style'] : '')
		],
		'consolesection' => [
			'friendly_name' => __('Console Menu Section'),
			'method'        => 'drop_array',
			'array'         => $sec_ar,
			'description'   => __('Under which Console heading should this item appear? (All External Link menus will appear between Configuration and Utilities)'),
			'value'         => (isset($data['extendedstyle']) ? $data['extendedstyle'] : '')
		],
		'consolenewsection' => [
			'friendly_name' => __('New Console Section'),
			'method'        => 'textbox',
			'max_length'    => 20,
			'description'   => __('If you don\'t like any of the choices above, type a new title in here.'),
			'value'         => (isset($data['extendedstyle']) ? $data['extendedstyle'] : '')
		],
		'title' => [
			'friendly_name' => __('Tab/Menu Name'),
			'method'        => 'textbox',
			'max_length'    => 20,
			'description'   => __('The text that will appear in the tab or menu.'),
			'value'         => (isset($data['title']) ? $data['title'] : '')
		],
		'filename' => [
			'friendly_name' => __('Content File/URL'),
			'method'        => 'drop_files',
			'directory'     => CACTI_PATH_INCLUDE . '/content',
			'exclusions'    => ['README', 'index.php'],
			'none_value'    => __('Web URL Below'),
			'description'   => __('The file that contains the content for this page. This file needs to be in the Cacti \'include/content/\' directory.'),
			'value'         => (isset($data['contentfile']) ? $data['contentfile'] : '')
		],
		'fileurl' => [
			'friendly_name' => __('Web URL Location'),
			'method'        => 'textbox',
			'description'   => __('The valid URL to use for this external link.  Must include the type, for example http://www.cacti.net.  Note that many websites do not allow them to be embedded in an iframe from a foreign site, and therefore External Linking may not work.'),
			'max_length'    => 255,
			'size'          => 80,
			'default'       => 'http://www.cacti.net',
			'value'         => (isset($data['contentfile']) ? $data['contentfile'] : '')
		],
		'enabled' => [
			'friendly_name' => __('Enabled'),
			'method'        => 'checkbox',
			'description'   => __('If checked, the page will be available immediately to the admin user.'),
			'default'       => 'on',
			'value'         => (isset($data['enabled']) ? 'on' : '')
		],
		'refresh' => [
			'friendly_name' => __('Automatic Page Refresh'),
			'method'        => 'drop_array',
			'array'         => $myrefresh,
			'description'   => __('How often do you wish this page to be refreshed automatically.'),
			'value'         => (isset($data['refresh']) ? $data['refresh'] : '')
		],
	];

	form_start('links.php', 'link_edit');

	if (isset($data['title'])) {
		html_start_box(__('External Links [edit: %s]', htmle($data['title'])), '100%', true, 3, 'center', '');
	} else {
		html_start_box(__('External Links [new]'), '100%', true, 3, 'center', '');
	}

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => $field_array
		]
	);

	html_end_box(true, true);

	form_save_button('links.php', 'save');

	?>
	<script type='text/javascript'>
		$(function() {
			// hide and show the extra console fields when necessary
			$('#style').change(function() {
				if ($('#style').val() != 'CONSOLE') {
					$('#row_consolesection').hide();
					$('#row_consolenewsection').hide();
				} else {
					$('#row_consolesection').show();
					setConsoleNewSectionVisibility();
				}
			}).change();

			$('#filename').change(function() {
				changeFilename();
			}).change();

			// if you change the section, make the 'new' textbox reflect it
			// if you change it to 'new', then clear the textbox, and jump to it
			$('#consolesection').change(function() {
				setConsoleNewSectionVisibility();
			}).change();
		});

		function setConsoleNewSectionVisibility() {
			var isNew = $('#consolesection').val() == '__NEW__';
			toggleFields({
				row_consolenewsection: isNew,
			});

			if (isNew) {
				$('#consolenewsection').focus();
			}
		}

		function changeFilename() {
			toggleFields({
				fileurl: $('#filename').val() == 0,
			});
		}
	</script>
	<?php
}
