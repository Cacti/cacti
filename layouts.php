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
	case 'layout_remove_confirm':
		layouts_remove_confirm();

		break;
	case 'layout_rename_dialog':
		layouts_rename_dialog();

		break;
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
		$layouts = db_fetch_assoc('SELECT ul.id, ul.user_id, ul.page, ul.name, ul.data, ua.username
			FROM user_layouts AS ul
			LEFT JOIN user_auth AS ua
			ON ua.id = ul.user_id
			ORDER BY ul.page, (ul.user_id = 0), ul.name');
	} else {
		$layouts = db_fetch_assoc_prepared('SELECT ul.id, ul.user_id, ul.page, ul.name, ul.data, ua.username
			FROM user_layouts AS ul
			LEFT JOIN user_auth AS ua
			ON ua.id = ul.user_id
			WHERE ul.user_id = ?
			ORDER BY ul.page, ul.name', [$user_id]);
	}

	html_start_box(__('Filter Layouts'), '100%', false, 3, 'center', '');

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

			$document = filter_layouts_decode($layout['data']);
			$summary  = $document !== false ? filter_layouts_document_url($document) : '';

			form_selectable_cell(filter_value($layout['name'], ''), $layout['id']);
			form_selectable_cell(html_escape($layout['page']), $layout['id']);
			form_selectable_cell($owner, $layout['id']);
			form_selectable_cell(html_escape($summary), $layout['id']);

			$rename = "<a class='pic layoutRename' href='#' data-id='" . $layout['id'] . "' title='" . __esc('Rename') . "'><i class='fa fa-pencil'></i></a>";
			$delete = "<a class='pic layoutDelete' href='#' data-id='" . $layout['id'] . "' title='" . __esc('Delete') . "'><i class='fa fa-times deviceDown'></i></a>";

			$actions = $rename . ' ' . $delete;

			if ($is_admin) {
				if ($layout['user_id'] == 0) {
					$actions = "<span title='" . __esc('Published to all users') . "'><i class='fa fa-globe'></i></span> " . $actions;
				} else {
					$actions = "<a class='pic layoutPublish' href='#' data-id='" . $layout['id'] . "' title='" . __esc('Publish to all users') . "'><i class='fa fa-upload'></i></a> " . $actions;
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
	<div id='cdialog'></div>
	<script type='text/javascript'>
	var layoutFailMsg = <?php print json_encode(__('The layout operation failed.')); ?>;

	function layoutCloseDialog() {
		if ($('#cdialog').hasClass('ui-dialog-content')) {
			$('#cdialog').dialog('close');
		}
	}

	function layoutActionPost(action, data) {
		data.action       = action;
		data.__csrf_magic = csrfMagicToken;

		$.post('layouts.php', data, function(result) {
			layoutCloseDialog();

			if (result && result.ok) {
				document.location.reload();
			} else {
				alert(layoutFailMsg);
			}
		}, 'json').fail(function() {
			layoutCloseDialog();
			alert(layoutFailMsg);
		});
	}

	$(function() {
		$('.layoutDelete').click(function(event) {
			event.preventDefault();

			var id = $(this).attr('data-id');

			$.get('layouts.php?action=layout_remove_confirm&id=' + id).done(function(data) {
				$('#cdialog').html(data);

				applySkin();

				$('#continue').off('click').on('click', function() {
					layoutActionPost('layout_delete', { id: $('#my_id').val() });
				});

				$('#cdialog').dialog({
					title: <?php print json_encode(__('Delete Filter Layout')); ?>,
					modal: true,
					minHeight: 80,
					minWidth: 400
				});
			}).fail(function(data) {
				getPresentHTTPError(data);
			});
		});

		$('.layoutRename').click(function(event) {
			event.preventDefault();

			var id = $(this).attr('data-id');

			$.get('layouts.php?action=layout_rename_dialog&id=' + id).done(function(data) {
				$('#cdialog').html(data);

				applySkin();

				$('#layout_new_name').focus().select();

				$('#continue').off('click').on('click', function() {
					var name = $('#layout_new_name').val();

					if (name == '') {
						return;
					}

					layoutActionPost('layout_rename', { id: $('#my_id').val(), name: name });
				});

				$('#layout_new_name').off('keydown').on('keydown', function(e) {
					if (e.keyCode == 13) {
						e.preventDefault();
						$('#continue').click();
					}
				});

				$('#cdialog').dialog({
					title: <?php print json_encode(__('Rename Filter Layout')); ?>,
					modal: true,
					minHeight: 80,
					minWidth: 400
				});
			}).fail(function(data) {
				getPresentHTTPError(data);
			});
		});

		$('.layoutPublish').click(function(event) {
			event.preventDefault();

			layoutActionPost('layout_publish', { id: $(this).attr('data-id') });
		});
	});
	</script>
	<?php
}

/**
 * Render the jQuery UI confirmation body shown before deleting a layout. The
 * markup is fetched over ajax into the management page's dialog container.
 *
 * @return void
 */
function layouts_remove_confirm() : void {
	/* ==== input validation ==== */
	$id = get_filter_request_var('id');
	/* ========================== */

	$layout = filter_layouts_get($id);

	if ($layout === false || !filter_layouts_user_can_edit($layout)) {
		print "<tr><td class='topBoxAlt'>" . __('The layout operation failed.') . '</td></tr>';

		return;
	}

	html_start_box('', '100%', false, 3, 'center', '');

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following Filter Layout.'); ?></p>
			<p><?php print __esc('Layout Name: %s', $layout['name']); ?></p>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel' onClick='$("#cdialog").dialog("close");'><?php print __esc('Cancel'); ?></button>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='continue' title='<?php print __esc('Delete Filter Layout'); ?>'><?php print __esc('Continue'); ?></button>
			<input type='hidden' id='my_id' value='<?php print $layout['id']; ?>'>
		</td>
	</tr>
	<?php

	html_end_box();
}

/**
 * Render the jQuery UI rename body shown before renaming a layout. The markup
 * is fetched over ajax into the management page's dialog container.
 *
 * @return void
 */
function layouts_rename_dialog() : void {
	/* ==== input validation ==== */
	$id = get_filter_request_var('id');
	/* ========================== */

	$layout = filter_layouts_get($id);

	if ($layout === false || !filter_layouts_user_can_edit($layout)) {
		print "<tr><td class='topBoxAlt'>" . __('The layout operation failed.') . '</td></tr>';

		return;
	}

	html_start_box('', '100%', false, 3, 'center', '');

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Enter a new name for the Filter Layout.'); ?></p>
			<p><?php print __('Name'); ?>
			<input type='text' class='ui-state-default ui-corner-all' id='layout_new_name' size='40' maxlength='128' value='<?php print html_escape($layout['name']); ?>'></p>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel' onClick='$("#cdialog").dialog("close");'><?php print __esc('Cancel'); ?></button>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='continue' title='<?php print __esc('Rename Filter Layout'); ?>'><?php print __esc('Continue'); ?></button>
			<input type='hidden' id='my_id' value='<?php print $layout['id']; ?>'>
		</td>
	</tr>
	<?php

	html_end_box();
}
