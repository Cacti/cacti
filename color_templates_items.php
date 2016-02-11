<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include_once('./include/auth.php');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		aggregate_color_item_form_save();

		break;
	case 'item_remove':
		get_filter_request_var('color_template_id');

		aggregate_color_item_remove();

		header('Location: color_templates.php?header=false&action=template_edit&color_template_id=' . get_request_var('color_template_id'));
		break;
	case 'item_movedown':
		get_filter_request_var('color_template_id');

		aggregate_color_item_movedown();

		header('Location: color_templates.php?header=false&action=template_edit&color_template_id=' . get_request_var('color_template_id'));
		break;
	case 'item_moveup':
		get_filter_request_var('color_template_id');

		aggregate_color_item_moveup();

		header('Location: color_templates.php?header=false&action=template_edit&color_template_id=' . get_request_var('color_template_id'));
		break;
	case 'item_edit':
		top_header();
		aggregate_color_item_edit();
		bottom_footer();
		break;
	case 'item':
		top_header();
		aggregate_color_item();
		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */
/**
 * aggregate_color_item_form_save	the save function
 */
function aggregate_color_item_form_save() {

	if (isset_request_var('save_component_item')) {
		/* ================= input validation ================= */
		get_filter_request_var('color_template_id');
		get_filter_request_var('sequence');
		/* ==================================================== */

		$items[0] = array();
		$sequence = get_nfilter_request_var('sequence');

		foreach ($items as $item) {
			/* generate a new sequence if needed */
			if (empty($sequence)) {
				$sequence = get_next_sequence($sequence, 'sequence', 'color_template_items', 'color_template_id=' . get_nfilter_request_var('color_template_id'), 'color_template_id');
			}

			$save['color_template_item_id'] = htmlspecialchars(get_nfilter_request_var('color_template_item_id'));
			$save['color_template_id'] = htmlspecialchars(get_nfilter_request_var('color_template_id'));
			$save['color_id'] = form_input_validate((isset($item['color_id']) ? $item['color_id'] : get_nfilter_request_var('color_id')), 'color_id', '', true, 3);
			$save['sequence'] = $sequence;

			if (!is_error_message()) {
				$color_template_item_id = sql_save($save, 'color_template_items', 'color_template_item_id');
				if ($color_template_item_id) {
					raise_message(1);
				}else{
					raise_message(2);
				}
			}

			$sequence = 0;
		}

		if (is_error_message()) {
			header('Location: color_templates_items.php?header=false&action=item_edit&color_template_item_id=' . (empty($color_template_item_id) ? get_nfilter_request_var('color_template_item_id') : $color_template_item_id) . '&color_template_id=' . get_nfilter_request_var('color_template_id'));
			exit;
		}else{
			header('Location: color_templates.php?header=false&action=template_edit&color_template_id=' . get_nfilter_request_var('color_template_id'));
			exit;
		}
	}
}

/* -----------------------
    item - Graph Items
   ----------------------- */

/**
 * aggregate_color_item_movedown		move item down
 */
function aggregate_color_item_movedown() {
	/* ================= input validation ================= */
	get_filter_request_var('color_template_item_id');
	get_filter_request_var('color_template_id');
	/* ==================================================== */
	$current_sequence = db_fetch_row('SELECT color_template_item_id, sequence
		FROM color_template_items
		WHERE color_template_item_id=' . get_request_var('color_template_item_id'), true);

	cacti_log('movedown Id: ' . $current_sequence['color_template_item_id'] . ' Seq:' . $current_sequence['sequence'], FALSE, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	$next_sequence = db_fetch_row('SELET color_template_item_id, sequence
		FROM color_template_items
		WHERE sequence>' . $current_sequence['sequence'] . '
		AND color_template_id=' . get_request_var('color_template_id') . '
		ORDER BY sequence ASC limit 1', true);

	cacti_log('movedown Id: ' . $next_sequence['color_template_item_id'] . ' Seq:' . $next_sequence['sequence'], FALSE, POLLER_VERBOSITY_DEBUG);

	db_execute('UPDATE color_template_items
		SET sequence=' . $next_sequence['sequence'] . '
		WHERE color_template_id=' . get_request_var('color_template_id') . '
		AND color_template_item_id=' . $current_sequence['color_template_item_id']);

	db_execute('UPDATE color_template_items
		SET sequence=' . $current_sequence['sequence'] . '
		WHERE color_template_id=' . get_request_var('color_template_id') . '
		AND color_template_item_id=' . $next_sequence['color_template_item_id']);
}


/**
 * aggregate_color_item_moveup		move item up
 */
function aggregate_color_item_moveup() {
	/* ================= input validation ================= */
	get_filter_request_var('color_template_item_id');
	get_filter_request_var('color_template_id');
	/* ==================================================== */

	$current_sequence = db_fetch_row('SELECT color_template_item_id, sequence
		FROM color_template_items
		WHERE color_template_item_id=' . get_request_var('color_template_item_id'), true);

	cacti_log('moveup Id: ' . $current_sequence['color_template_item_id'] . ' Seq:' . $current_sequence['sequence'], FALSE, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	$previous_sequence = db_fetch_row('SELECT color_template_item_id, sequence
		FROM color_template_items
		WHERE sequence < ' . $current_sequence['sequence'] . '
		AND color_template_id=' . get_request_var('color_template_id') . '
		ORDER BY sequence DESC limit 1', true);

	cacti_log('moveup Id: ' . $previous_sequence['color_template_item_id'] . ' Seq:' . $previous_sequence['sequence'], FALSE, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	db_execute('UPDATE color_template_items
		SET sequence=' . $previous_sequence['sequence'] . '
		WHERE color_template_id=' . get_request_var('color_template_id') . '
		AND color_template_item_id=' . $current_sequence['color_template_item_id']);

	db_execute('UPDATE color_template_items
		SET sequence=' . $current_sequence['sequence'] . '
		WHERE color_template_id=' . get_request_var('color_template_id') . '
		AND color_template_item_id=' . $previous_sequence['color_template_item_id']);
}

/**
 * aggregate_color_item_remove		remove item
 */
function aggregate_color_item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('color_template_item_id');
	get_filter_request_var('color_template_id');
	/* ==================================================== */

	db_execute('DELETE FROM color_template_items WHERE color_template_item_id=' . get_request_var('color_template_item_id'));
}


/**
 * aggregate_color_item_edit		edit item
 */
function aggregate_color_item_edit() {
	global $struct_color_template_item;

	/* ================= input validation ================= */
	get_filter_request_var('color_template_item_id');
	get_filter_request_var('color_template_id');
	/* ==================================================== */

	$template = db_fetch_row('SELECT * FROM color_templates WHERE color_template_id=' . get_request_var('color_template_id'));

	if (isset_request_var('color_template_item_id') && (get_request_var('color_template_item_id') > 0)) {
		$template_item = db_fetch_row('select * from color_template_items where color_template_item_id=' . get_request_var('color_template_item_id'));
		$header_label = '[edit Report Item: ' . $template['name'] . ']';
	}else{
		$template_item = array();
		$header_label = '[new Report Item: ' . $template['name'] . ']';
	}

	form_start('color_templates_items.php', 'aggregate_color_item_edit');

	html_start_box("Color Template Items $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($struct_color_template_item, (isset($template_item) ? $template_item : array()))
	));

	html_end_box();

	form_hidden_box('color_template_item_id', (array_key_exists('color_template_item_id', $template_item) ? $template_item['color_template_item_id'] : '0'), '');
	form_hidden_box('color_template_id', get_request_var('color_template_id'), '0');
	form_hidden_box('sequence', (array_key_exists('sequence', $template_item) ? $template_item['sequence'] : '0'), '');
	form_hidden_box('save_component_item', '1', '');

	form_save_button(htmlspecialchars('color_templates.php?header=false&action=template_edit&color_template_id=' . get_request_var('color_template_id')), '', 'color_template_item_id');
}

