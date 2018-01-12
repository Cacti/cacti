<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

include_once('./include/auth.php');

$link_actions = array(
	1 => __('Delete'),
	3 => __('Enable'),
	2 => __('Disable')
);

set_default_action();

switch (get_request_var('action')) {
case 'actions':
	form_actions();

	break;
case 'delete_page':
	if (isset_request_var('id') && get_filter_request_var('id')) {
		page_delete(get_request_var('id'));
	}

	header('Location: links.php?header=false');

	break;
case 'move_page_up':
	if (isset_request_var('id') && get_filter_request_var('id') && isset_request_var('order') && get_filter_request_var('order')) {
		page_move(get_request_var('id'), get_request_var('order'), '-1');
	}

	header('Location: links.php?header=false');

	break;
case 'move_page_down':
	if (isset_request_var('id') && get_filter_request_var('id') && isset_request_var('order') && get_filter_request_var('order')) {
		page_move(get_request_var('id'), get_request_var('order'), '1');
	}

	header('Location: links.php?header=false');

	break;
case 'save':
	$save['id']            = isset_request_var('id') ? get_filter_request_var('id'):0;
	$save['title']         = form_input_validate(get_nfilter_request_var('title'), 'title', '', false, 3);
	$save['style']         = get_nfilter_request_var('style');
	$save['enabled']       = (isset_request_var('enabled') ? 'on':'');

	if (preg_match('/^((((ht|f)tp(s?))\:\/\/){1}\S+)/i', get_nfilter_request_var('fileurl')) && get_nfilter_request_var('filename') == '0') {
		$save['contentfile'] = get_nfilter_request_var('fileurl');
	} else {
		$save['contentfile'] = preg_replace('/[^A-Za-z0-9_\.-]/','_', get_nfilter_request_var('filename'));
	}

	$consolesection    = get_nfilter_request_var('consolesection');
	$consolenewsection = get_nfilter_request_var('consolenewsection');
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

	if (!is_error_message()){
		$id = sql_save($save, 'external_links');

		// always give the login account access
		db_execute_prepared('REPLACE INTO user_auth_realm (user_id, realm_id) VALUES (?, ?)', array($_SESSION['sess_user_id'], $id+10000));

		raise_message(1);

		header('Location: links.php?header=false');
		exit;
	} else {
		raise_message(2);

		header('Location: links.php?action=edit&header=false&id=' . (isset_request_var('id') ? get_filter_request_var('id'):''));
		exit;
	}

	break;
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

function form_actions() {
	global $actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if ($_POST['drp_action'] == '3') { // Enable Page
				for ($i=0;($i<count($selected_items));$i++) {
					db_execute_prepared("UPDATE external_links SET enabled='on' WHERE id = ?", array($selected_items[$i]));
				}
			} elseif ($_POST['drp_action'] == '2') { // Disable Page
				for ($i=0;($i<count($selected_items));$i++) {
					db_execute_prepared("UPDATE external_links SET enabled='' WHERE id = ?", array($selected_items[$i]));
				}
			} elseif ($_POST['drp_action'] == '1') { // Delete Page
				for ($i=0;($i<count($selected_items));$i++) {
					db_execute_prepared('DELETE FROM external_links WHERE id = ?', array($selected_items[$i]));
					db_execute_prepared('DELETE FROM user_auth_realm WHERE realm_id = ?', array($selected_items[$i]+10000));
					db_execute_prepared('DELETE FROM user_auth_group_realm WHERE realm_id = ?', array($selected_items[$i]+10000));
				}
			}
		}

		header('Location: links.php?header=false');
		exit;
	}

	/* setup some variables */
	$page_list = ''; $i = 0;

	/* loop through each of the pages selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$page_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT title FROM external_links WHERE id = ?', array($matches[1]))) . '</li>';
			$pages[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('links.php');

	html_start_box($actions[get_request_var_post('drp_action')], '60%', '', '3', 'center', '');

	if (isset($pages) && sizeof($pages)) {
		if (get_request_var('drp_action') == '3') { // Enable Pages
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to Enable the following Page(s).') . "</p>
					<ul>" . $page_list . "</ul>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __('Continue') . "' title='" . __esc('Enable Page(s)') . "'>";
		} elseif (get_request_var('drp_action') == '2') { // Disable Pages
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to Disable the following Page(s).') . "</p>
					<ul>" . $page_list . "</ul>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __('Continue') . "' title='" . __esc('Disable Page(s)') . "'>";
		} elseif (get_request_var('drp_action') == '1') { // Delete Pages
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to Delete the following Page(s).') . "</p>
					<ul>" . $page_list . "</ul>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __('Continue') . "' title='" . __esc('Delete Page(s)') . "'>";
		}
	} else {
		print "<tr><td><span class='textError'>" . __('You must select at least one page.') . "</span></td></tr>\n";
		$save_html = "<input type='button' value='" . __('Return') . "' onClick='cactiReturnTo()'>";
	}

	print "<tr class='saveRow'>
		<td>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($pages) ? serialize($pages) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function pages() {
	global $item_rows, $config, $link_actions;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'sortorder',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_links');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'links.php?rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL  = 'links.php?clear=true&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#links').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('External Links'), '100%', '', '3', 'center', 'links.php?action=edit');
	?>
	<tr class='even noprint'>
		<td>
			<form id='links' action='links.php' method='post'>
			<table class='filterTable' cellpadding='2' cellspacing='0'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='textbox' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Links');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value=-1 <?php get_request_var('rows') == -1 ? 'selected':'';?>><?php print __('Default');?></option>
							<?php
							foreach ($item_rows as $key => $row) {
								echo "<option value='" . $key . "'" . ($key == get_request_var('rows') ? ' selected' : '') . '>' . $row . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' id='refresh' value='<?php print __('Go');?>' title='<?php print __esc('Apply Filter');?>' onClick='applyFilter()'>
							<input type='button' id='clear' value='<?php print __('Clear');?>' title='<?php print __esc('Reset filters');?>' onClick='clearFilter()'>
						</span>
					</td>
				</tr>
			</table>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$style_translate = array(
		'CONSOLE'    => __('Console'),
		'TAB'        => __('Top Tab'),
		'FRONT'      => __('Bottom Console'),
		'FRONTTOP'   => __('Top Console')
	);

	if (get_request_var('filter') != '') {
		$sql_where = " WHERE title LIKE '%" . get_request_var('filter') . "%' OR contentfile LIKE '%" . get_request_var('filter') . "%'";
	} else {
		$sql_where = '';
	}

	$sql_order = get_order_string();
	$sql_order = str_replace('sortorder DESC', 'sortorder ASC', $sql_order);
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$pages = db_fetch_assoc("SELECT *
		FROM external_links
		$sql_where
		$sql_order
		$sql_limit");

	$total_rows = db_fetch_cell('SELECT COUNT(*) FROM external_links');

	form_start('links.php');

	$nav = html_nav_bar('links.php', MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 8, __('External Links'), 'page', 'main');

	print $nav;

    html_start_box('', '100%', '', '4', 'center', '');

	$display_text = array(
		'nosort0'     => array('display' => __('Actions'), 'align' => 'left',  'sort' => ''),
		'contentfile' => array('display' => __('Page'),    'align' => 'left',  'sort' => 'ASC'),
		'title'       => array('display' => __('Title'),   'align' => 'left',  'sort' => 'ASC'),
		'style'       => array('display' => __('Style'),   'align' => 'left',  'sort' => 'ASC'),
		'disabled'    => array('display' => __('Enabled'), 'align' => 'left',  'sort' => 'ASC'),
		'sortorder'   => array('display' => __('Order'),   'align' => 'center', 'sort' => 'ASC')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	$i = 0;
	if (sizeof($pages)) {
		foreach ($pages as $page) {
			form_alternate_row('line' . $page['id']);

			$actions = '<a class="pic"  href="' . htmlspecialchars('links.php?action=edit&id='.$page['id']) . '" title="' . __esc('Edit Page') . '"><img src="' . $config['url_path'] . 'images/application_edit.png" alt=""></a>';

			if ($page['enabled'] == 'on') {
				$actions .= '<a class="pic" href="' . htmlspecialchars('link.php?id=' . $page['id']) . '" title="' . __esc('View Page') . '"><img src="' . $config['url_path'] . 'images/view_page.png" alt=""></a>';
			}

			form_selectable_cell($actions, $page['id'], '50');
			form_selectable_cell(htmlspecialchars($page['contentfile']), $page['id']);
			form_selectable_cell(htmlspecialchars($page['title']), $page['id']);
			form_selectable_cell(htmlspecialchars($style_translate[$page['style']]) . ($page['style'] == 'CONSOLE' ? ' ( ' . ($page['extendedstyle'] == '' ? 'External Links':$page['extendedstyle']) . ' )':''), $page['id']);
			form_selectable_cell(($page['enabled'] == 'on' ? 'Yes':'No'), $page['id']);

			if (get_request_var('sort_column') == 'sortorder') {
				if ($i != 0) {
					$sort = '<a class="pic fa fa-caret-up moveArrow" href="' . htmlspecialchars('links.php?action=move_page_up&order=' . $page['sortorder'] . '&id='.$page['id']) . '"></a>';
				} else {
					$sort = '<span class="moveArrowNone"></span>';
				}

				if ($i == sizeof($pages)-1) {
					$sort .= '<span class="moveArrowNone"></span>';
				} else {
					$sort .= '<a class="pic fa fa-caret-down moveArrow" href="' . htmlspecialchars('links.php?action=move_page_down&order=' . $page['sortorder'] . '&id=' . $page['id']) . '"></a>';
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
		print "<tr><td><em>" . __('No Pages Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (sizeof($pages)) {
		print $nav;
	}

	draw_actions_dropdown($link_actions);

	form_end();
}

function page_delete($id) {
	db_execute_prepared('DELETE FROM external_links WHERE id = ?', array($id));
	db_execute_prepared('DELETE FROM user_auth_realm WHERE realm_id = ?', array($id+10000));
	db_execute_prepared('DELETE FROM user_auth_group_realm WHERE realm_id = ?', array($id+10000));

	page_resort();
}

function page_resort() {
	$pages = db_fetch_assoc("SELECT * FROM external_links ORDER BY sortorder");

	$i = 1;
	if (sizeof($pages)) {
		foreach ($pages as $page) {
			db_execute_prepared('UPDATE external_links SET sortorder = ? WHERE id = ?' . array($id, $page['id']));
			$i++;
		}
	}
}

function page_move($pageid, $junk, $direction) {
	$oldorder = db_fetch_cell_prepared('SELECT sortorder FROM external_links WHERE id = ?', array($pageid));
	$neworder = $oldorder + $direction;
	$otherid  = db_fetch_cell_prepared('SELECT id FROM external_links WHERE sortorder = ?', array($neworder));

	if (!empty($otherid)) {
		db_execute_prepared('UPDATE external_links SET sortorder = ? WHERE id = ?', array($neworder, $pageid));
		db_execute_prepared('UPDATE external_links SET sortorder = ? WHERE id = ?', array($oldorder, $otherid));
	}
}

function edit_page() {
	global $config;

	$sections = db_fetch_assoc("SELECT extendedstyle
		FROM external_links
		WHERE style='CONSOLE'
		GROUP BY extendedstyle
		ORDER BY extendedstyle");

	$sec_ar = array();
	$sec_ar['External Links'] = __('External Links');

	foreach ($sections as $sec) {
		if ($sec['extendedstyle'] !='') {
			$sec_ar[$sec['extendedstyle']] = $sec['extendedstyle'];
		}
	}
	$sec_ar['__NEW__'] = 'New Name Below';

	if (isset_request_var('id')) {
		$data = db_fetch_row_prepared('SELECT * FROM external_links WHERE id = ?', array(get_filter_request_var('id')));
	} else {
		$data = array();
	}

	$field_array = array(
		'id' => array(
			'friendly_name' => __('Style'),
			'method' => 'hidden',
			'value' => isset_request_var('id') ? get_request_var('id'):0
		),
		'style' => array(
			'friendly_name' => __('Style'),
			'method' => 'drop_array',
			'array' => array(
				'TAB'        => __('Top Tab'),
				'CONSOLE'    => __('Console Menu'),
				'FRONT'      => __('Bottom of Console Page'),
				'FRONTTOP'   => __('Top of Console Page')
			),
			'description' => __('Where should this page appear?'),
			'value' => (isset($data['style']) ? $data['style']:'')
		),
		'consolesection' => array(
			'friendly_name' => __('Console Menu Section'),
			'method' => 'drop_array',
			'array' => $sec_ar,
			'description' => __('Under which Console heading should this item appear? (All External Link menus will appear between Configuration and Utilities)'),
			'value' => (isset($data['extendedstyle']) ? $data['extendedstyle']:'')
		),
		'consolenewsection' => array(
			'friendly_name' => __('New Console Section'),
			'method' => 'textbox',
			'max_length' => 20,
			'description' => __('If you don\'t like any of the choices above, type a new title in here.'),
			'value' => (isset($data['extendedstyle']) ? $data['extendedstyle']:'')
		),
		'title' => array(
			'friendly_name' => __('Tab/Menu Name'),
			'method' => 'textbox',
			'max_length' => 20,
			'description' => __('The text that will appear in the tab or menu.'),
			'value' => (isset($data['title']) ? $data['title']:'')
		),
		'filename' => array(
			'friendly_name' => __('Content File/URL'),
			'method' => 'drop_files',
			'directory' => $config['base_path'] . '/include/content',
			'exclusions' => array('README', 'index.php'),
			'none_value' => __('Web URL Below'),
			'description' => __('The file that contains the content for this page. This file needs to be in the Cacti \'include/content/\' directory.'),
			'value' => (isset($data['contentfile']) ? $data['contentfile']:'')
		),
		'fileurl' => array(
			'friendly_name' => __('Web URL Location'),
			'method' => 'textbox',
			'description' => __('The valid URL to use for this external link.  Must include the type, for example http://www.cacti.net.  Note that many websites do not allow them to be embedded in an iframe from a foreign site, and therefore External Linking may not work.'),
			'max_length' => 255,
			'size' => 80,
			'default' => 'http://www.cacti.net',
			'value' => (isset($data['contentfile']) ? $data['contentfile']:'')
		),
		'enabled' => array(
			'friendly_name' => 'Do you want this page enabled',
			'method' => 'checkbox',
			'description' => "If you wish this page to be viewable immediately, please check the checkbox.",
			'default' => 'on',
			'value' => (isset($data['enabled']) ? 'on':'')
		),
	);

	form_start('links.php');

	if (isset($data['title'])) {
		html_start_box(__('External Links [edit: %s]', $data['title']), '100%', true, '3', 'center', '');
	} else {
		html_start_box(__('External Links [new]'), '100%', true, '3', 'center', '');
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $field_array
		)
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
				setConsoleNewSectionVisibity();
			}
		}).change();

		$('#filename').change(function() {
			changeFilename();
		}).change();

		// if you change the section, make the 'new' textbox reflect it
		// if you change it to 'new', then clear the textbox, and jump to it
		$('#consolesection').change(function() {
			setConsoleNewSectionVisibity();
		}).change();
	});

	function setConsoleNewSectionVisibity() {
		if ($('#consolesection').val() == '__NEW__') {
			$('#row_consolenewsection').show();
			$('#consolenewsection').focus();
		} else {
			$('#row_consolenewsection').hide();
		}
	}

	function changeFilename() {
		if ($('#filename').val() == 0) {
			$('#row_fileurl').show();
		} else {
			$('#row_fileurl').hide();
		}
	}

	</script>
	<?php
}

// vim:ts=4:sw=4:
