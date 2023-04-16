<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

$aggregate_actions = array(
	1 => __('Delete'),
	2 => __('Duplicate'),
	3 => __('Sync Aggregates')
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'template_edit':
		top_header();
		color_template_edit();
		bottom_footer();

		break;
	case 'ajax_dnd':
		color_templates_item_dnd();

		break;
	case 'item_remove_confirm':
		color_item_remove_confirm();

		break;
	case 'item_remove':
		get_filter_request_var('color_template_id');

		color_item_remove();

		header('Location: color_templates.php?action=template_edit&color_template_id=' . get_request_var('id'));

		break;
	case 'item_movedown':
		get_filter_request_var('color_template_id');

		color_item_movedown();

		header('Location: color_templates.php?action=template_edit&color_template_id=' . get_request_var('color_template_id'));

		break;
	case 'item_moveup':
		get_filter_request_var('color_template_id');

		color_item_moveup();

		header('Location: color_templates.php?action=template_edit&color_template_id=' . get_request_var('color_template_id'));

		break;
	case 'item_edit':
		top_header();
		color_item_edit();
		bottom_footer();

		break;
	case 'item':
		top_header();
		color_item();
		bottom_footer();

		break;
	default:
		top_header();
		color_template();
		bottom_footer();

		break;
}

/** draw_color_template_items_list 	- draws a nicely formatted list of color items for display
 *   								  on an edit form
 * @param array $item_list 			- an array representing the list of color items. this array should
 *   								  come directly from the output of db_fetch_assoc()
 * @param string $filename 			- the filename to use when referencing any external url
 * @param string $url_data 			- any extra GET url information to pass on when referencing any
 *   								  external url
 * @param bool $disable_controls 	- whether to hide all edit/delete functionality on this form
 */
function draw_color_template_items_list($item_list, $filename, $url_data, $disable_controls) {
	global $config;
	global $struct_color_template_item;

	$display_text = array(
		array('display' => __('Color Item'), 'align' => 'left', 'nohide' => true),
		array('display' => __('Color'), 'align' => 'left', 'nohide' => true),
		array('display' => __('Hex'), 'align' => 'left', 'nohide' => true),
	);

	html_header($display_text, 2);

	$i           = 1;
	$total_items = cacti_sizeof($item_list);

	if (cacti_sizeof($item_list)) {
		foreach ($item_list as $item) {
			/* alternating row color */
			form_alternate_row('line' . $item['color_template_item_id'], true, true);

			print '<td>';

			if ($disable_controls == false) {
				print "<a class='linkEditMain' href='" . html_escape($filename . '?action=item_edit&color_template_item_id=' . $item['color_template_item_id'] . "&$url_data") . "'>";
			}

			print __('Item # %d', $i);

			if ($disable_controls == false) {
				print '</a>';
			}

			print "</td>\n";

			print "<td style='" . ((isset($item['hex'])) ? 'background-color:#' . $item['hex'] . ";'" : '') . "></td>\n";

			print '<td>' . $item['hex'] . "</td>\n";

			if ($disable_controls == false) {
				print "<td class='right nowrap'>";

				if (read_config_option('drag_and_drop') == '') {
					if ($i < $total_items && $total_items > 1) {
						print '<a class="pic fa fa-caret-down moveArrow" href="' . html_escape('color_templates.php?action=item_movedown&color_template_item_id=' . $item['color_template_item_id'] . '&color_template_id=' . $item['color_template_id']) . '" title="' . __esc('Move Down') . '"></a>';
					} else {
						print '<span class="moveArrowNone"></span>';
					}

					if ($i > 1 && $i <= $total_items) {
						print '<a class="pic fa fa-caret-up moveArrow" href="' . html_escape('color_templates.php?action=item_moveup&color_template_item_id=' . $item['color_template_item_id'] . '&color_template_id=' . $item['color_template_id']) . '" title="' . __esc('Move Up') . '"></a>';
					} else {
						print '<span class="moveArrowNone"></span>';
					}
				}

				print "<a class='delete deleteMarker fa fa-times' id='" .  $item['color_template_id'] . '_' . $item['color_template_item_id'] . "' title='" . __esc('Delete') . "'></a>";

				print "</td>\n";
			}

			form_end_row();

			$i++;
		}
	} else {
		print "<tr><td colspan='7'><em>" . __('No Items') . '</em></td></tr>';
	}
}

/**
 * form_save	the save function
 */
function form_save() {
	if (isset_request_var('save_component_color')) {
		if (isset_request_var('color_template_id')) {
			$save1['color_template_id'] = get_nfilter_request_var('color_template_id');
		} else {
			$save1['color_template_id'] = 0;
		}

		$save1['name'] = form_input_validate(get_filter_request_var('name', FILTER_SANITIZE_SPECIAL_CHARS), 'name', '', false, 3);

		cacti_log('Saved ID: ' . $save1['color_template_id'] . ' Name: ' . $save1['name'], false, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

		if (!is_error_message()) {
			$color_template_id = sql_save($save1, 'color_templates', 'color_template_id');

			cacti_log('Saved ID: ' . $color_template_id, false, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

			if ($color_template_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: color_templates.php?action=template_edit&color_template_id=' . (empty($color_template_id) ? get_nfilter_request_var('color_template_id') : $color_template_id));
	} elseif (isset_request_var('save_component_item')) {
		/* ================= input validation ================= */
		get_filter_request_var('color_template_id');
		get_filter_request_var('color_template_item_id');
		get_filter_request_var('sequence');
		/* ==================================================== */

		$items[0] = array();
		$sequence = get_nfilter_request_var('sequence');

		foreach ($items as $item) {
			/* generate a new sequence if needed */
			if (empty($sequence)) {
				$sequence = get_next_sequence($sequence, 'sequence', 'color_template_items', 'color_template_id=' . get_nfilter_request_var('color_template_id'), 'color_template_id');
			}

			$save['color_template_item_id'] = html_escape_request_var('color_template_item_id');
			$save['color_template_id']      = html_escape_request_var('color_template_id');
			$save['color_id']               = form_input_validate((isset($item['color_id']) ? $item['color_id'] : get_nfilter_request_var('color_id')), 'color_id', '', true, 3);
			$save['sequence']               = $sequence;

			if (!is_error_message()) {
				$color_template_item_id = sql_save($save, 'color_template_items', 'color_template_item_id');

				if ($color_template_item_id) {
					raise_message(1);
				} else {
					raise_message(2);
				}
			}

			$sequence = 0;
		}

		if (is_error_message()) {
			header('Location: color_templates.php?action=item_edit&color_template_item_id=' . (empty($color_template_item_id) ? get_nfilter_request_var('color_template_item_id') : $color_template_item_id) . '&color_template_id=' . get_nfilter_request_var('color_template_id'));

			exit;
		} else {
			header('Location: color_templates.php?action=template_edit&color_template_id=' . get_nfilter_request_var('color_template_id'));

			exit;
		}
	}
}

/**
 * form_actions	the action function
 */
function form_actions() {
	global $aggregate_actions, $config;
	include_once(CACTI_PATH_LIBRARY . '/api_aggregate.php');

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_request_var('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM color_templates WHERE ' . array_to_sql_or($selected_items, 'color_template_id'));
				db_execute('DELETE FROM color_template_items WHERE ' . array_to_sql_or($selected_items, 'color_template_id'));
			} elseif (get_nfilter_request_var('drp_action') == '2') { // duplicate
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					duplicate_color_template($selected_items[$i], get_nfilter_request_var('title_format'));
				}
			} elseif (get_nfilter_request_var('drp_action') == '3') { // sync templates
				for ($i=0;($i < cacti_count($selected_items));$i++) {
					sync_color_templates($selected_items[$i]);
				}
			}
		}

		header('Location: color_templates.php');

		exit;
	}

	/* setup some variables */
	$color_list = '';
	$i          = 0;

	/* loop through each of the color templates selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1], 'chk[1]');
			/* ==================================================== */

			$name = db_fetch_cell_prepared('SELECT name
				FROM color_templates
				WHERE color_template_id = ?',
				array($matches[1]));

			$color_list .= '<li>' . html_escape($name) . '</li>';
			$color_array[] = $matches[1];
		}
	}

	top_header();

	form_start('color_templates.php');

	html_start_box($aggregate_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($color_array) && cacti_sizeof($color_array)) {
		if (get_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to delete the following Color Template', 'Click \'Continue\' to delete following Color Templates', cacti_sizeof($color_array)) . "</p>
					<div class='itemlist'><ul>$color_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Delete Color Template', 'Delete Color Templates', cacti_sizeof($color_array)) . "'>";
		} elseif (get_request_var('drp_action') == '2') { // duplicate
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to duplicate the following Color Template. You can optionally change the title format for the new color template.', 'Click \'Continue\' to duplicate following Color Templates. You can optionally change the title format for the new color templates.', cacti_sizeof($color_array)) . "</p>
					<div class='itemlist'><ul>$color_list</ul></div>
					<p>" . __('Title Format:') . '<br>';
			form_text_box('title_format', '<template_title> (1)', '', '255', '30', 'text');
			print '</p>
				</td>
			</tr>';

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Duplicate Color Template', 'Duplicate Color Templates', cacti_sizeof($color_array)) . "'>";
		} elseif (get_request_var('drp_action') == '3') { // sync
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to Synchronize all Aggregate Graphs with the selected Color Template.', 'Click \'Continue\' to Synchronize all Aggregate Graphs with the selected Color Templates.', cacti_sizeof($color_array)) . "</p>
					<div class='itemlist'><ul>$color_list</ul></div></p>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Synchronize Color Template', 'Synchronize Color Templates', cacti_sizeof($color_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: color_templates.php');

		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($color_array) ? serialize($color_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function color_templates_item_dnd() {
	/* ================= Input validation ================= */
	get_filter_request_var('id');
	/* ================= Input validation ================= */

	if (isset_request_var('color_item') && is_array(get_nfilter_request_var('color_item'))) {
		$color_items = get_nfilter_request_var('color_item');

		if (cacti_sizeof($color_items)) {
			$sequence = 1;

			foreach ($color_items as $option_id) {
				$option = str_replace('line', '', $option_id);

				db_execute_prepared('UPDATE color_template_items
					SET sequence = ?
					WHERE color_template_item_id = ?',
					array($sequence, $option));

				$sequence++;
			}
		}
	}

	header('Location: color_templates.php?action=template_edit&color_template_id=' . get_request_var('id'));

	exit;
}

/**
 * color_item_movedown move item down
 */
function color_item_movedown() {
	/* ================= input validation ================= */
	get_filter_request_var('color_template_item_id');
	get_filter_request_var('color_template_id');
	/* ==================================================== */

	$current_sequence = db_fetch_row_prepared('SELECT color_template_item_id, sequence
		FROM color_template_items
		WHERE color_template_item_id = ?',
		array(get_request_var('color_template_item_id')));

	cacti_log('movedown Id: ' . $current_sequence['color_template_item_id'] . ' Seq:' . $current_sequence['sequence'],
		false, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	$next_sequence = db_fetch_row_prepared('SELECT color_template_item_id, sequence
		FROM color_template_items
		WHERE sequence > ?
		AND color_template_id = ?
		ORDER BY sequence ASC limit 1',
		array($current_sequence['sequence'], get_request_var('color_template_id')));

	cacti_log('movedown Id: ' . $next_sequence['color_template_item_id'] . ' Seq:' . $next_sequence['sequence'],
		false, POLLER_VERBOSITY_DEBUG);

	db_execute_prepared('UPDATE color_template_items
		SET sequence = ?
		WHERE color_template_id = ?
		AND color_template_item_id = ?',
		array($next_sequence['sequence'], get_request_var('color_template_id'), $current_sequence['color_template_item_id']));

	db_execute_prepared('UPDATE color_template_items
		SET sequence = ?
		WHERE color_template_id = ?
		AND color_template_item_id = ?',
		array($current_sequence['sequence'], get_request_var('color_template_id'), $next_sequence['color_template_item_id']));
}

/**
 * color_item_moveup move item up
 */
function color_item_moveup() {
	/* ================= input validation ================= */
	get_filter_request_var('color_template_item_id');
	get_filter_request_var('color_template_id');
	/* ==================================================== */

	$current_sequence = db_fetch_row_prepared('SELECT color_template_item_id, sequence
		FROM color_template_items
		WHERE color_template_item_id = ?',
		array(get_request_var('color_template_item_id')));

	cacti_log('moveup Id: ' . $current_sequence['color_template_item_id'] . ' Seq:' . $current_sequence['sequence'],
		false, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	$previous_sequence = db_fetch_row_prepared('SELECT color_template_item_id, sequence
		FROM color_template_items
		WHERE sequence < ?
		AND color_template_id = ?
		ORDER BY sequence DESC limit 1',
		array($current_sequence['sequence'], get_request_var('color_template_id')));

	cacti_log('moveup Id: ' . $previous_sequence['color_template_item_id'] . ' Seq:' . $previous_sequence['sequence'],
		false, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	db_execute_prepared('UPDATE color_template_items
		SET sequence = ?
		WHERE color_template_id = ?
		AND color_template_item_id = ?',
		array($previous_sequence['sequence'], get_request_var('color_template_id'), $current_sequence['color_template_item_id']));

	db_execute_prepared('UPDATE color_template_items
		SET sequence = ?
		WHERE color_template_id = ?
		AND color_template_item_id = ?',
		array($current_sequence['sequence'], get_request_var('color_template_id'), $previous_sequence['color_template_item_id']));
}

function color_item_remove_confirm() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('color_id');
	/* ==================================================== */

	form_start('color_templates.php');

	html_start_box('', '100%', '', '3', 'center', '');

	$template   = db_fetch_row_prepared('SELECT *
		FROM color_templates
		WHERE color_template_id = ?',
		array(get_request_var('id')));

	$color_item = db_fetch_row_prepared('SELECT *
		FROM color_template_items
		WHERE color_template_item_id = ?',
		array(get_request_var('color_id')));

	$color_hex  = db_fetch_cell_prepared('SELECT hex
		FROM colors
		WHERE id = ?',
		array($color_item['color_id']));

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following Color Template Color.'); ?></p>
			<p><?php print __('Color Name:');?> '<?php print html_escape($template['name']);?>'<br>
			<?php print __('Color Hex:');?><strong><?php print $color_hex;?></p>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='<?php print __esc('Cancel');?>' onClick='$("#cdialog").dialog("close");' name='cancel'>
			<input type='button' class='ui-button ui-corner-all ui-widget' id='continue' value='<?php print __esc('Continue');?>' name='continue' title='<?php print __esc('Remove Color Item');?>'>
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
				url: 'color_templates.php?action=item_remove',
				redirect: 'color_templates.php?action=template_edit&color_template_id=<?php print get_request_var('id');?>'
			}

			var data = {
				__csrf_magic: csrfMagicToken,
				color_id: <?php print get_request_var('color_id');?>,
				id: <?php print get_request_var('id');?>
			}

			postUrl(options, data);
		});
	});
	</script>
	<?php
}

/**
 * color_item_remove remove item
 */
function color_item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('color_id');
	/* ==================================================== */

	db_execute_prepared('DELETE FROM color_template_items
		WHERE color_template_item_id = ?',
		array(get_request_var('color_id')));
}

/**
 * color_item_edit edit item
 */
function color_item_edit() {
	global $struct_color_template_item;

	/* ================= input validation ================= */
	get_filter_request_var('color_template_item_id');
	get_filter_request_var('color_template_id');
	/* ==================================================== */

	$template = db_fetch_row_prepared('SELECT *
		FROM color_templates
		WHERE color_template_id = ?',
		array(get_request_var('color_template_id')));

	if (isset_request_var('color_template_item_id') && (get_request_var('color_template_item_id') > 0)) {
		$template_item = db_fetch_row_prepared('SELECT *
			FROM color_template_items
			WHERE color_template_item_id = ?',
			array(get_request_var('color_template_item_id')));

		$header_label = __esc('Color Template Items [edit Report Item: %s]', $template['name']);
	} else {
		$template_item = array();
		$header_label  = __esc('Color Template Items [new Report Item: %s]', $template['name']);
	}

	form_start('color_templates.php', 'aggregate_color_item_edit');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($struct_color_template_item, (isset($template_item) ? $template_item : array()))
	));

	html_end_box(true, true);

	form_hidden_box('color_template_item_id', (array_key_exists('color_template_item_id', $template_item) ? $template_item['color_template_item_id'] : '0'), '');
	form_hidden_box('color_template_id', get_request_var('color_template_id'), '0');
	form_hidden_box('sequence', (array_key_exists('sequence', $template_item) ? $template_item['sequence'] : '0'), '');
	form_hidden_box('save_component_item', '1', '');

	form_save_button('color_templates.php?action=template_edit&color_template_id=' . get_request_var('color_template_id'), '', 'color_template_item_id');
}

/**
 * color_item show all color template items
 */
function color_item() {
	global $config;

	/* ================= input validation ================= */
	get_filter_request_var('color_template_id');
	/* ==================================================== */

	if (isempty_request_var('color_template_id')) {
		$template_item_list = array();

		$header_label = __('Color Template Items [new]');
	} else {
		$template_item_list = db_fetch_assoc_prepared('SELECT
			cti.color_template_id, cti.color_template_item_id, cti.sequence, colors.hex
			FROM color_template_items AS cti
			LEFT JOIN colors
			ON cti.color_id=colors.id
			WHERE cti.color_template_id = ?
			ORDER BY cti.sequence ASC',
			array(get_request_var('color_template_id')));

		$name = db_fetch_cell_prepared('SELECT name
			FROM color_templates
			WHERE color_template_id = ?',
			array(get_request_var('color_template_id')));

		$header_label = __esc('Color Template Items [edit: %s]', $name);
	}

	html_start_box($header_label, '100%', '', '3', 'center', 'color_templates.php?action=item_edit&color_template_id=' . html_escape_request_var('color_template_id'));

	draw_color_template_items_list($template_item_list, 'color_templates.php', 'color_template_id=' . html_escape_request_var('color_template_id'), false);

	html_end_box();

	?>
	<script type='text/javascript'>

	$(function() {
		$('#color_templates_template_edit2_child').attr('id', 'color_item');
		$('.cdialog').remove();
		$('#main').append("<div class='cdialog' id='cdialog'></div>");

		<?php if (read_config_option('drag_and_drop') == 'on') { ?>
		$('#color_item').tableDnD({
			onDrop: function(table, row) {
				loadUrl({url:'color_templates.php?action=ajax_dnd&id=<?php isset_request_var('color_template_id') ? print get_request_var('color_template_id') : print 0;?>&'+$.tableDnD.serialize()})
			}
		});
		<?php } ?>

		$('.delete').click(function (event) {
			event.preventDefault();

			id = $(this).attr('id').split('_');
			request = 'color_templates.php?action=item_remove_confirm&id='+id[0]+'&color_id='+id[1];
			$.get(request)
				.done(function(data) {
					$('#cdialog').html(data);

					applySkin();

					$('#cdialog').dialog({
						title: '<?php print __('Delete Color Item');?>',
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

/**
 * color_template_edit	edit the color template
 */
function color_template_edit() {
	global $config, $image_types, $fields_color_template_template_edit, $struct_aggregate;

	include_once(CACTI_PATH_LIBRARY . '/api_aggregate.php');

	/* ================= input validation ================= */
	get_filter_request_var('color_template_id');
	/* ==================================================== */

	if (!isempty_request_var('color_template_id')) {
		$template     = db_fetch_row_prepared('SELECT * FROM color_templates WHERE color_template_id = ?', array(get_request_var('color_template_id')));
		$header_label = __esc('Color Template [edit: %s]', $template['name']);
	} else {
		$header_label = __('Color Template [new]');
	}

	form_start('color_templates.php', 'color_template_edit');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_color_template_template_edit, (isset($template) ? $template : array()))
		)
	);

	html_end_box(true, true);

	form_hidden_box('color_template_id', (isset($template['color_template_id']) ? $template['color_template_id'] : '0'), '');
	form_hidden_box('save_component_color', '1', '');

	/* color item list goes here */
	if (!isempty_request_var('color_template_id')) {
		color_item();
	}

	form_save_button('color_templates.php', 'return', 'color_template_id');
}

function sync_color_templates($color_template) {
	global $config;

	include_once(CACTI_PATH_LIBRARY . '/api_aggregate.php');

	$name = db_fetch_cell_prepared('SELECT name
		FROM color_templates
		WHERE color_template_id = ?',
		array($color_template));

	$aggregate_templates = array_rekey(
		db_fetch_assoc_prepared('SELECT DISTINCT aggregate_template_id
			FROM aggregate_graph_templates_item
			WHERE color_template = ?',
			array($color_template)),
		'aggregate_template_id', 'aggregate_template_id'
	);

	$found     = false;
	$templates = 0;
	$graphs    = 0;

	if (cacti_sizeof($aggregate_templates)) {
		$found     = true;
		$templates = cacti_sizeof($aggregate_templates);

		foreach ($aggregate_templates as $id) {
			push_out_aggregates($id);
		}
	}

	$aggregate_graphs = db_fetch_assoc_prepared('SELECT DISTINCT ag.aggregate_template_id, ag.local_graph_id
		FROM aggregate_graphs_graph_item AS agi
		LEFT JOIN aggregate_graphs AS ag
		ON ag.id=agi.aggregate_graph_id
		WHERE (ag.aggregate_template_id > 0 AND ag.template_propogation = "")
		OR ag.aggregate_template_id = 0
		AND agi.color_template = ?',
		array($color_template));

	if (cacti_sizeof($aggregate_graphs)) {
		$found  = true;
		$graphs = cacti_sizeof($aggregate_graphs);

		foreach ($aggregate_templates as $id) {
			push_out_aggregates($id['aggregate_template_id'], $id['local_graph_id']);
		}
	}

	if ($found) {
		raise_message('color_template_sync', __('Color Template \'%s\' had %d Aggregate Templates pushed out and %d Non-Templated Aggregates pushed out', $name, $templates, $graphs), MESSAGE_LEVEL_INFO);
	} else {
		raise_message('color_template_sync', __('Color Template \'%s\' had no Aggregate Templates or Graphs using this Color Template.', $name, $templates, $graphs), MESSAGE_LEVEL_INFO);
	}
}

/**
 * color_template maintain color templates
 */
function color_template() {
	global $aggregate_actions, $item_rows, $config;
	include_once(CACTI_PATH_LIBRARY . '/api_aggregate.php');

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'has_graphs' => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => read_config_option('default_has') == 'on' ? 'true':'false'
			)
	);

	validate_store_request_vars($filters, 'sess_ct');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Color Templates'), '100%', '', '3', 'center', 'color_templates.php?action=template_edit');

	$filter_html = '<tr class="even">
		<td>
			<form id="form_template">
				<table class="filterTable">
					<tr>
						<td>
							' . __('Search') . '
						</td>
						<td>
							<input type="text" class="ui-state-default ui-corner-all" id="filter" size="25" value="' . html_escape_request_var('filter') . '">
						</td>
						<td>
							' . __('Color Templates') . '
						</td>
						<td>
							<select id="rows" onChange="applyFilter()">
								<option value="-1" ';

	if (get_request_var('rows') == '-1') {
		$filter_html .= 'selected';
	}

	$filter_html .= '>' . __('Default') . '</option>';

	if (cacti_sizeof($item_rows)) {
		foreach ($item_rows as $key => $value) {
			$filter_html .= "<option value='" . $key . "'";

			if (get_request_var('rows') == $key) {
				$filter_html .= ' selected';
			}
			$filter_html .= '>' . $value . "</option>\n";
		}
	}

	$filter_html .= '			</select>
							</td>
							<td>
								<span>
									<input type="checkbox" id="has_graphs" ' . (get_request_var('has_graphs') == 'true' ? 'checked':'') . ' onChange="applyFilter()">
									<label for="has_graphs">' . __('Has Graphs') . '</label>
								</span>
							</td>
							<td>
								<span>
									<input type="submit" class="ui-button ui-corner-all ui-widget" id="go" value="' . __esc('Go') . '">
									<input type="button" class="ui-button ui-corner-all ui-widget" id="clear" value="' . __esc('Clear') . '">
								</span>
							</td>
						</tr>
					</table>
				</form>
			</td>
		</tr>';

	print $filter_html;

	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = '';

	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (ct.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	if (get_request_var('has_graphs') == 'true') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' (templates>0 OR graphs>0)';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(ct.color_template_id)
		FROM color_templates AS ct
		LEFT JOIN (
			SELECT color_template, COUNT(*) AS templates
			FROM aggregate_graph_templates_item
			GROUP BY color_template
		) AS templates
		ON ct.color_template_id=templates.color_template
		LEFT JOIN (
			SELECT color_template, COUNT(*) AS graphs
			FROM aggregate_graphs_graph_item
			GROUP BY color_template
		) AS graphs
		ON ct.color_template_id=graphs.color_template
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$template_list = db_fetch_assoc("SELECT
		ct.color_template_id, ct.name, templates.templates, graphs.graphs
		FROM color_templates AS ct
		LEFT JOIN (
			SELECT color_template, COUNT(*) AS templates
			FROM aggregate_graph_templates_item
			GROUP BY color_template
		) AS templates
		ON ct.color_template_id=templates.color_template
		LEFT JOIN (
			SELECT color_template, COUNT(*) AS graphs
			FROM aggregate_graphs_graph_item
			GROUP BY color_template
		) AS graphs
		ON ct.color_template_id=graphs.color_template
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('color_templates.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Color Templates'), 'page', 'main');

	form_start('color_templates.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name' => array(
			'display' => __('Template Title'),
			'sort'    => 'ASC'
		),
		'nosort' => array(
			'display' => __('Deletable'),
			'align'   => 'right',
			'tip'     => __('Color Templates that are in use cannot be Deleted. In use is defined as being referenced by an Aggregate Template.')
		),
		'graphs'    => array(
			'display' => __('Graphs'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'templates' => array(
			'display' => __('Templates'),
			'align'   => 'right',
			'sort'    => 'DESC'
		)
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($template_list)) {
		foreach ($template_list as $template) {
			if ($template['templates'] > 0) {
				$disabled = true;
			} else {
				$disabled = false;
			}

			form_alternate_row('line' . $template['color_template_id'], true);

			form_selectable_cell(filter_value($template['name'], get_request_var('filter'), 'color_templates.php?action=template_edit&color_template_id=' . $template['color_template_id'] . '&page=1'), $template['color_template_id']);
			form_selectable_cell($disabled ? __('No'):__('Yes'), $template['color_template_id'], '', 'right');
			form_selectable_cell(number_format_i18n($template['graphs']), $template['color_template_id'], '', 'right');
			form_selectable_cell(number_format_i18n($template['templates']), $template['color_template_id'], '', 'right');
			form_checkbox_cell($template['name'], $template['color_template_id'], $disabled);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Color Templates Found') ."</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($template_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($aggregate_actions);

	form_end();

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'color_templates.php';
		strURL += '?rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&has_graphs=' + $('#has_graphs').is(':checked');
		loadUrl({url:strURL})
	}

	function clearFilter() {
		strURL = 'color_templates.php?clear=1';
		loadUrl({url:strURL})
	}

	$(function() {
		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_template').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php
}
