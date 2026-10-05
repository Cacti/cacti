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

set_default_action();

switch (get_nfilter_request_var('action')) {
	default:
		top_header();

		layouts_manage();

		bottom_footer();

		break;
}

/**
 * Render the filter-layout management page. Administrators holding the
 * Settings/Utilities realm manage every user's layouts and may publish them
 * globally; other users manage only their own saved layouts.
 *
 * @return void
 */
function layouts_manage() : void {
	$is_admin = filter_layouts_can_manage_global();
	$user_id  = isset($_SESSION['sess_user_id']) ? (int) $_SESSION['sess_user_id'] : 0;

	if ($is_admin) {
		$layouts = db_fetch_assoc('SELECT ul.*, ua.username
			FROM user_layouts AS ul
			LEFT JOIN user_auth AS ua
			ON ua.id = ul.user_id
			ORDER BY ul.page, (ul.user_id = 0), ul.name');
	} else {
		$layouts = db_fetch_assoc_prepared('SELECT ul.*, ua.username
			FROM user_layouts AS ul
			LEFT JOIN user_auth AS ua
			ON ua.id = ul.user_id
			WHERE ul.user_id = ?
			ORDER BY ul.page, ul.name', [$user_id]);
	}

	html_start_box(__('Filter Layouts'), '100%', true, 3, 'center', '');

	$display_text = [
		'name'   => ['display' => __('Name')],
		'page'   => ['display' => __('Page')],
		'owner'  => ['display' => __('Owner')],
		'url'    => ['display' => __('Filter')],
		'nosort' => ['display' => __('Actions'), 'align' => 'right'],
	];

	html_header($display_text, 1);

	if (cacti_sizeof($layouts)) {
		foreach ($layouts as $layout) {
			if ($layout['user_id'] == 0) {
				$owner = '<em>' . __('Global') . '</em>';
			} elseif (!empty($layout['username'])) {
				$owner = html_escape($layout['username']);
			} else {
				$owner = __('User %d', $layout['user_id']);
			}

			form_alternate_row('line' . $layout['id'], true);

			form_selectable_cell(filter_value($layout['name'], ''), $layout['id']);
			form_selectable_cell(html_escape($layout['page']), $layout['id']);
			form_selectable_cell($owner, $layout['id']);
			form_selectable_cell(html_escape($layout['url']), $layout['id']);

			$actions = "<a class='pic layoutAction' href='#' data-action='layout_delete' data-id='" . $layout['id'] . "' title='" . __esc('Delete') . "'><i class='fa fa-times deviceDown'></i></a>";

			if ($is_admin) {
				if ($layout['user_id'] == 0) {
					$actions = "<span title='" . __esc('Published to all users') . "'><i class='fa fa-globe'></i></span> " . $actions;
				} else {
					$actions = "<a class='pic layoutAction' href='#' data-action='layout_publish' data-id='" . $layout['id'] . "' title='" . __esc('Publish to all users') . "'><i class='fa fa-upload'></i></a> " . $actions;
				}
			}

			form_selectable_cell($actions, $layout['id'], '', 'right');

			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='5'><em>" . __('No Filter Layouts Found') . '</em></td></tr>';
	}

	html_end_box(false);

	?>
	<script type='text/javascript'>
	$(function() {
		$('.layoutAction').click(function(event) {
			event.preventDefault();

			var action = $(this).attr('data-action');
			var id     = $(this).attr('data-id');

			if (action == 'layout_delete' && !confirm(<?php print json_encode(__('Delete the selected layout?')); ?>)) {
				return;
			}

			$.post('layouts.php', { action: action, id: id, __csrf_magic: csrfMagicToken }, function() {
				document.location.reload();
			}, 'json').fail(function() {
				alert(<?php print json_encode(__('The layout operation failed.')); ?>);
			});
		});
	});
	</script>
	<?php
}
