<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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

include('./include/auth.php');
include_once('./lib/api_graph.php');
include_once('./lib/api_tree.php');
include_once('./lib/html_tree.php');
include_once('./lib/poller.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');

$graph_actions = array(
	1 => __('Delete'),
	2 => __('Duplicate'),
	3 => __('Change Settings'),
	4 => __('Sync Graphs')
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
	case 'input_remove':
		get_filter_request_var('graph_template_id');

		input_remove();

		header('Location: graph_templates.php?action=template_edit&id=' . get_request_var('graph_template_id'));
		break;
	case 'input_edit':
		top_header();

		input_edit();

		bottom_footer();
		break;
	case 'template_edit':
		top_header();

		template_edit();

		bottom_footer();
		break;
	default:
		top_header();

		template();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	// sanitize ids
	if (isset_request_var('graph_template_id') && !is_numeric(get_nfilter_request_var('graph_template_id'))) {
		$graph_template_id = 0;
	} else {
		$graph_template_id = get_filter_request_var('graph_template_id');
	}

	if (isset_request_var('save_component_template')) {
		/* ================= input validation ================= */
		get_filter_request_var('graph_template_graph_id');
		/* ==================================================== */

		$save1['id']       = $graph_template_id;
		$save1['hash']     = get_hash_graph_template($graph_template_id);
		$save1['name']     = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save1['multiple'] = isset_request_var('multiple') ? 'on':'';

		$save2['id']                     = get_nfilter_request_var('graph_template_graph_id');
		$save2['local_graph_template_graph_id'] = 0;
		$save2['local_graph_id']         = 0;
		$save2['t_image_format_id']      = (isset_request_var('t_image_format_id') ? get_nfilter_request_var('t_image_format_id') : '');
		$save2['image_format_id']        = form_input_validate(get_nfilter_request_var('image_format_id'), 'image_format_id', '^[0-9]+$', true, 3);
		$save2['t_title']                = form_input_validate((isset_request_var('t_title') ? get_nfilter_request_var('t_title') : ''), 't_title', '', true, 3);
		$save2['title']                  = form_input_validate(get_nfilter_request_var('title'), 'title', '', (isset_request_var('t_title') ? true : false), 3);
		$save2['t_height']               = form_input_validate((isset_request_var('t_height') ? get_nfilter_request_var('t_height') : ''), 't_height', '', true, 3);
		$save2['height']                 = form_input_validate(get_nfilter_request_var('height'), 'height', '^[0-9]+$', (isset_request_var('t_height') ? true : false), 3);
		$save2['t_width']                = form_input_validate((isset_request_var('t_width') ? get_nfilter_request_var('t_width') : ''), 't_width', '', true, 3);
		$save2['width']                  = form_input_validate(get_nfilter_request_var('width'), 'width', '^[0-9]+$', (isset_request_var('t_width') ? true : false), 3);
		$save2['t_upper_limit']          = form_input_validate((isset_request_var('t_upper_limit') ? get_nfilter_request_var('t_upper_limit') : ''), 't_upper_limit', '', true, 3);
		$save2['upper_limit']            = form_input_validate(get_nfilter_request_var('upper_limit'), 'upper_limit', '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$', ((isset_request_var('t_upper_limit') || (strlen(get_nfilter_request_var('upper_limit')) === 0)) ? true : false), 3);
		$save2['t_lower_limit']          = form_input_validate((isset_request_var('t_lower_limit') ? get_nfilter_request_var('t_lower_limit') : ''), 't_lower_limit', '', true, 3);
		$save2['lower_limit']            = form_input_validate(get_nfilter_request_var('lower_limit'), 'lower_limit', '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$', ((isset_request_var('t_lower_limit') || (strlen(get_nfilter_request_var('lower_limit')) === 0)) ? true : false), 3);
		$save2['t_vertical_label']       = form_input_validate((isset_request_var('t_vertical_label') ? get_nfilter_request_var('t_vertical_label') : ''), 't_vertical_label', '', true, 3);
		$save2['vertical_label']         = form_input_validate(get_nfilter_request_var('vertical_label'), 'vertical_label', '', true, 3);
		$save2['t_slope_mode']           = form_input_validate((isset_request_var('t_slope_mode') ? get_nfilter_request_var('t_slope_mode') : ''), 't_slope_mode', '', true, 3);
		$save2['slope_mode']             = form_input_validate((isset_request_var('slope_mode') ? get_nfilter_request_var('slope_mode') : ''), 'slope_mode', '', true, 3);
		$save2['t_auto_scale']           = form_input_validate((isset_request_var('t_auto_scale') ? get_nfilter_request_var('t_auto_scale') : ''), 't_auto_scale', '', true, 3);
		$save2['auto_scale']             = form_input_validate((isset_request_var('auto_scale') ? get_nfilter_request_var('auto_scale') : ''), 'auto_scale', '', true, 3);
		$save2['t_auto_scale_opts']      = form_input_validate((isset_request_var('t_auto_scale_opts') ? get_nfilter_request_var('t_auto_scale_opts') : ''), 't_auto_scale_opts', '', true, 3);
		$save2['auto_scale_opts']        = form_input_validate(get_nfilter_request_var('auto_scale_opts'), 'auto_scale_opts', '', true, 3);
		$save2['t_auto_scale_log']       = form_input_validate((isset_request_var('t_auto_scale_log') ? get_nfilter_request_var('t_auto_scale_log') : ''), 't_auto_scale_log', '', true, 3);
		$save2['auto_scale_log']         = form_input_validate((isset_request_var('auto_scale_log') ? get_nfilter_request_var('auto_scale_log') : ''), 'auto_scale_log', '', true, 3);
		$save2['t_scale_log_units']      = form_input_validate((isset_request_var('t_scale_log_units') ? get_nfilter_request_var('t_scale_log_units') : ''), 't_scale_log_units', '', true, 3);
		$save2['scale_log_units']        = form_input_validate((isset_request_var('scale_log_units') ? get_nfilter_request_var('scale_log_units') : ''), 'scale_log_units', '', true, 3);
		$save2['t_auto_scale_rigid']     = form_input_validate((isset_request_var('t_auto_scale_rigid') ? get_nfilter_request_var('t_auto_scale_rigid') : ''), 't_auto_scale_rigid', '', true, 3);
		$save2['auto_scale_rigid']       = form_input_validate((isset_request_var('auto_scale_rigid') ? get_nfilter_request_var('auto_scale_rigid') : ''), 'auto_scale_rigid', '', true, 3);
		$save2['t_auto_padding']         = form_input_validate((isset_request_var('t_auto_padding') ? get_nfilter_request_var('t_auto_padding') : ''), 't_auto_padding', '', true, 3);
		$save2['auto_padding']           = form_input_validate((isset_request_var('auto_padding') ? get_nfilter_request_var('auto_padding') : ''), 'auto_padding', '', true, 3);
		$save2['t_base_value']           = form_input_validate((isset_request_var('t_base_value') ? get_nfilter_request_var('t_base_value') : ''), 't_base_value', '', true, 3);
		$save2['base_value']             = form_input_validate(get_nfilter_request_var('base_value'), 'base_value', '^[0-9]+$', (isset_request_var('t_base_value') ? true : false), 3);
		$save2['t_unit_value']           = form_input_validate((isset_request_var('t_unit_value') ? get_nfilter_request_var('t_unit_value') : ''), 't_unit_value', '', true, 3);
		$save2['unit_value']             = form_input_validate(get_nfilter_request_var('unit_value'), 'unit_value', '', true, 3);
		$save2['t_unit_exponent_value']  = form_input_validate((isset_request_var('t_unit_exponent_value') ? get_nfilter_request_var('t_unit_exponent_value') : ''), 't_unit_exponent_value', '', true, 3);
		$save2['unit_exponent_value']    = form_input_validate(get_nfilter_request_var('unit_exponent_value'), 'unit_exponent_value', '^-?[0-9]+$', true, 3);
		$save2['t_alt_y_grid']           = form_input_validate((isset_request_var('t_alt_y_grid') ? get_nfilter_request_var('t_alt_y_grid') : ''), 't_alt_y_grid', '', true, 3);
		$save2['alt_y_grid']             = form_input_validate((isset_request_var('alt_y_grid') ? get_nfilter_request_var('alt_y_grid') : ''), 'alt_y_grid', '', true, 3);
		$save2['t_right_axis']           = form_input_validate((isset_request_var('t_right_axis') ? get_nfilter_request_var('t_right_axis') : ''), 't_right_axis', '', true, 3);
		$save2['right_axis']             = form_input_validate((isset_request_var('right_axis') ? get_nfilter_request_var('right_axis') : ''), 'right_axis', '^-?([0-9]+(\.[0-9]*)?|\.[0-9]+):-?([0-9]+(\.[0-9]*)?|\.[0-9]+)$', true, 3);
		$save2['t_right_axis_label']     = form_input_validate((isset_request_var('t_right_axis_label') ? get_nfilter_request_var('t_right_axis_label') : ''), 't_right_axis_label', '', true, 3);
		$save2['right_axis_label']       = form_input_validate((isset_request_var('right_axis_label') ? get_nfilter_request_var('right_axis_label') : ''), 'right_axis_label', '', true, 3);
		$save2['t_right_axis_format']    = form_input_validate((isset_request_var('t_right_axis_format') ? get_nfilter_request_var('t_right_axis_format') : ''), 't_right_axis_format', '', true, 3);
		$save2['right_axis_format']      = form_input_validate((isset_request_var('right_axis_format') ? get_nfilter_request_var('right_axis_format') : ''), 'right_axis_format', '^[0-9]+$', true, 3);
		$save2['t_no_gridfit']           = form_input_validate((isset_request_var('t_no_gridfit') ? get_nfilter_request_var('t_no_gridfit') : ''), 't_no_gridfit', '', true, 3);
		$save2['no_gridfit']             = form_input_validate((isset_request_var('no_gridfit') ? get_nfilter_request_var('no_gridfit') : ''), 'no_gridfit', '', true, 3);
		$save2['t_unit_length']          = form_input_validate((isset_request_var('t_unit_length') ? get_nfilter_request_var('t_unit_length') : ''), 't_unit_length', '', true, 3);
		$save2['unit_length']            = form_input_validate((isset_request_var('unit_length') ? get_nfilter_request_var('unit_length') : ''), 'unit_length', '^[0-9]+$', true, 3);
		$save2['t_tab_width']            = form_input_validate((isset_request_var('t_tab_width') ? get_nfilter_request_var('t_tab_width') : ''), 't_tab_width', '', true, 3);
		$save2['tab_width']              = form_input_validate((isset_request_var('tab_width') ? get_nfilter_request_var('tab_width') : ''), 'tab_width', '^[0-9]*$', true, 3);
		$save2['t_dynamic_labels']       = form_input_validate((isset_request_var('t_dynamic_labels') ? get_nfilter_request_var('t_dynamic_labels') : ''), 't_dynamic_labels', '', true, 3);
		$save2['dynamic_labels']         = form_input_validate((isset_request_var('dynamic_labels') ? get_nfilter_request_var('dynamic_labels') : ''), 'dynamic_labels', '', true, 3);
		$save2['t_force_rules_legend']   = form_input_validate((isset_request_var('t_force_rules_legend') ? get_nfilter_request_var('t_force_rules_legend') : ''), 't_force_rules_legend', '', true, 3);
		$save2['force_rules_legend']     = form_input_validate((isset_request_var('force_rules_legend') ? get_nfilter_request_var('force_rules_legend') : ''), 'force_rules_legend', '', true, 3);
		$save2['t_legend_position']      = form_input_validate((isset_request_var('t_legend_position') ? get_nfilter_request_var('t_legend_position') : ''), 't_legend_position', '', true, 3);
		$save2['legend_position']        = form_input_validate((isset_request_var('legend_position') ? get_nfilter_request_var('legend_position') : ''), 'legend_position', '', true, 3);
		$save2['t_legend_direction']     = form_input_validate((isset_request_var('t_legend_direction') ? get_nfilter_request_var('t_legend_direction') : ''), 't_legend_direction', '', true, 3);
		$save2['legend_direction']       = form_input_validate((isset_request_var('legend_direction') ? get_nfilter_request_var('legend_direction') : ''), 'legend_direction', '', true, 3);
		$save2['t_right_axis_formatter'] = form_input_validate((isset_request_var('t_right_axis_formatter') ? get_nfilter_request_var('t_right_axis_formatter') : ''), 't_right_axis_formatter', '', true, 3);
		$save2['right_axis_formatter']   = form_input_validate((isset_request_var('right_axis_formatter') ? get_nfilter_request_var('right_axis_formatter') : ''), 'right_axis_formatter', '', true, 3);
		$save2['t_left_axis_formatter']  = form_input_validate((isset_request_var('t_left_axis_formatter') ? get_nfilter_request_var('t_left_axis_formatter') : ''), 't_left_axis_formatter', '', true, 3);
		$save2['left_axis_formatter']    = form_input_validate((isset_request_var('left_axis_formatter') ? get_nfilter_request_var('left_axis_formatter') : ''), 'left_axis_formatter', '', true, 3);

		if (!is_error_message()) {
			// Clear the Graph Template cache
			if (empty($graph_template_id)) {
				clear_cached_allowed_types();
			}

			$graph_template_id = sql_save($save1, 'graph_templates');

			if ($graph_template_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (!is_error_message()) {
			$save2['graph_template_id'] = $graph_template_id;
			$graph_template_graph_id = sql_save($save2, 'graph_templates_graph');

			if ($graph_template_graph_id) {
				raise_message(1);

				push_out_graph($graph_template_graph_id);
			} else {
				raise_message(2);
			}
		}

		if (!empty($graph_template_id)) {
			resequence_graphs($graph_template_id);
		}
	}

	header('Location: graph_templates.php?action=template_edit&id=' . (empty($graph_template_id) ? get_nfilter_request_var('graph_template_id') : $graph_template_id));
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $graph_actions, $config, $image_types;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_request_var('drp_action') == '1') { // delete
				db_execute('DELETE FROM graph_templates
					WHERE ' . array_to_sql_or($selected_items, 'id'));

				$snmp_graph_ids = array_rekey(
					db_fetch_assoc('SELECT id
						FROM snmp_query_graph
						WHERE ' . array_to_sql_or($selected_items, 'graph_template_id')),
					'id', 'id'
				);

				if (cacti_sizeof($snmp_graph_ids)) {
					db_execute('DELETE FROM snmp_query_graph
						WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));

					db_execute('DELETE FROM snmp_query_graph_rrd
						WHERE snmp_query_graph_id IN (' . implode(', ', $snmp_graph_ids) . ')');

					db_execute('DELETE FROM snmp_query_graph_rrd_sv
						WHERE snmp_query_graph_id IN (' . implode(', ', $snmp_graph_ids) . ')');

					db_execute('DELETE FROM snmp_query_graph_rrd_sv
						WHERE snmp_query_graph_id IN (' . implode(', ', $snmp_graph_ids) . ')');

					db_execute('DELETE FROM snmp_query_graph_sv
						WHERE snmp_query_graph_id IN (' . implode(', ', $snmp_graph_ids) . ')');
				}

				$graph_template_input = db_fetch_assoc('SELECT id
					FROM graph_template_input
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));

				if (cacti_sizeof($graph_template_input) > 0) {
					foreach ($graph_template_input as $item) {
						db_execute_prepared('DELETE FROM graph_template_input_defs
							WHERE graph_template_input_id = ?', array($item['id']));
					}
				}

				db_execute('DELETE FROM graph_template_input
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));

				db_execute('DELETE FROM graph_templates_graph
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id') . ' AND local_graph_id=0');

				db_execute('DELETE FROM graph_templates_item
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id') . ' AND local_graph_id=0');

				db_execute('DELETE FROM host_template_graph
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));

				/* 'undo' any graph that is currently using this template */
				db_execute('UPDATE graph_templates_graph
					SET local_graph_template_graph_id=0, graph_template_id=0
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));

				db_execute('UPDATE graph_templates_item
					SET local_graph_template_item_id=0, graph_template_id=0
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));

				db_execute('UPDATE graph_local
					SET graph_template_id=0
					WHERE ' . array_to_sql_or($selected_items, 'graph_template_id'));

				clear_cached_allowed_types();
			} elseif (get_request_var('drp_action') == '2') { // duplicate
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					api_duplicate_graph(0, $selected_items[$i], get_nfilter_request_var('title_format'));
				}
			} elseif (get_request_var('drp_action') == '3') { // resize
				get_filter_request_var('graph_width');
				get_filter_request_var('graph_height');
				get_filter_request_var('image_format_id');

				for ($i=0;($i<cacti_count($selected_items));$i++) {
					db_execute_prepared('UPDATE graph_templates_graph
						SET width = ?, height = ?, image_format_id = ?
						WHERE graph_template_id = ?',
						array(get_request_var('graph_width'),
						get_request_var('graph_height'),
						get_request_var('image_format_id'),
						$selected_items[$i]));
				}
			} elseif (get_request_var('drp_action') == '4') { // retemplate
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					retemplate_graphs($selected_items[$i]);
				}
			}
		}

		header('Location: graph_templates.php');
		exit;
	}

	/* setup some variables */
	$graph_list = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$graph_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE id = ?', array($matches[1]))) . '</li>';
			$graph_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('graph_templates.php');

	html_start_box($graph_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($graph_array) && cacti_sizeof($graph_array)) {
		if (get_request_var('drp_action') == '1') { // delete
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to delete the following Graph Template(s).  Any Graph(s) associated with the Template(s) will become individual Graph(s).') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Delete Graph Template(s)') . "'>";
		} elseif (get_request_var('drp_action') == '2') { // duplicate
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to duplicate the following Graph Template(s). You can optionally change the title format for the new Graph Template(s).') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
					<p><strong>" . __('Title Format:'). "</strong><br>"; form_text_box('title_format', '<template_title> (1)', '', '255', '30', 'text'); print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue'). "' title='" . __esc('Duplicate Graph Template(s)') . "'>";
		} elseif (get_request_var('drp_action') == '3') { // resize
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to resize the following Graph Template(s) and Graph(s) to the Height and Width below.  The defaults below are maintained in Settings.') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
				</td>
			</tr>
			</table>
			<table class='filterTable'>
			<tr>
				<td>";

			print __('Graph Height') . '</td><td>';
			form_text_box('graph_height', read_config_option('default_graph_height'), '', '5', '5', 'text');
			print '</td></tr><tr><td>' . __('Graph Width') . '</td><td>';
			form_text_box('graph_width', read_config_option('default_graph_width'), '', '5', '5', 'text');
			print '</td></tr><tr><td>' . __('Image Format') . '</td><td>';
			form_dropdown('image_format_id', $image_types, '', '', read_config_option('default_image_format'), '', '');

			print "</td></tr></table><div class='break'></div><table style='width:100%'>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Resize Selected Graph Template(s)') . "'>";
		} elseif (get_request_var('drp_action') == '4') { // retemplate
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to Synchronize your Graphs with the following Graph Template(s). This function is important if you have Graphs that exist with multiple versions of a Graph Template and wish to make them all common in appearance.') . "</p>
					<div class='itemlist'><ul>$graph_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue'). "' title='" . __esc('Synchronize Graphs to Graph Template(s)') . "'>";
		}
	} else {
		raise_message(40);
		header('Location: graph_templates.php');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($graph_array) ? serialize($graph_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function item() {
	global $consolidation_functions, $graph_item_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (isempty_request_var('id')) {
		$template_item_list = array();

		$header_label = 'Graph Template Items [new]';
	} else {
		$template_item_list = db_fetch_assoc_prepared("SELECT gti.id, gti.text_format, gti.alpha,
			gti.value, gti.hard_return, gti.graph_type_id, gti.consolidation_function_id, gti.textalign,
			CONCAT(IFNULL(dt.name, ''), ' (', dtr.data_source_name, ')') AS data_source_name,
			cdef.name AS cdef_name, colors.hex
			FROM graph_templates_item AS gti
			LEFT JOIN data_template_rrd AS dtr
			ON gti.task_item_id=dtr.id
			LEFT JOIN data_local AS dl
			ON dtr.local_data_id=dl.id
			LEFT JOIN data_template AS dt
			ON dt.id=dtr.data_template_id
			LEFT JOIN data_template_data AS dtd
			ON dl.id=dtd.local_data_id
			LEFT JOIN cdef
			ON cdef_id=cdef.id
			LEFT JOIN vdef
			ON vdef_id=vdef.id
			LEFT JOIN colors
			ON color_id=colors.id
			WHERE gti.graph_template_id = ?
			AND gti.local_graph_id=0
			ORDER BY gti.sequence",
			array(get_request_var('id')));

		$header_label = __esc('Graph Template Items [edit: %s]', db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE id = ?', array(get_request_var('id'))));
	}

	html_start_box($header_label, '100%', '', '3', 'center', 'graph_templates_items.php?action=item_edit&graph_template_id=' . get_request_var('id'));
	draw_graph_items_list($template_item_list, 'graph_templates_items.php', 'graph_template_id=' . get_request_var('id'), false);
	html_end_box();

	html_start_box(__('Graph Item Inputs'), '100%', '', '3', 'center', 'graph_templates_inputs.php?action=input_edit&graph_template_id=' . get_request_var('id'));

	print "<tr class='tableHeader'>";
		DrawMatrixHeaderItem(__('Name'),'',2);
	print '</tr>';

	$template_item_list = db_fetch_assoc_prepared('SELECT id, name
		FROM graph_template_input
		WHERE graph_template_id = ?
		ORDER BY name', array(get_request_var('id')));

	$i = 0;
	if (cacti_sizeof($template_item_list) > 0) {
		foreach ($template_item_list as $item) {
			form_alternate_row('', true);
			?>
			<td>
				<a class='linkEditMain' href='<?php print html_escape('graph_templates_inputs.php?action=input_edit&id=' . $item['id'] . '&graph_template_id=' . get_request_var('id'));?>'><?php print html_escape($item['name']);?></a>
			</td>
			<td class='right'>
				<a class='deleteMarker fa fa-times' title='<?php print __esc('Delete');?>' href='<?php print html_escape('graph_templates_inputs.php?action=input_remove&id=' . $item['id'] . '&graph_template_id=' . get_request_var('id'));?>'></a>
			</td>
		</tr>
		<?php
		}
	} else {
		print "<tr><td colspan='2'><em>" . __('No Inputs') . "</em></td></tr>";
	}

	?>
	<script type='text/javascript'>
	$(function() {
		$('.deleteMarker, .moveArrow').click(function(event) {
			event.preventDefault();
			loadUrl({url:$(this).attr('href')})
		});
	});
	</script>
	<?php

	html_end_box();
}

/* ----------------------------
    template - Graph Templates
   ---------------------------- */

function template_edit() {
	global $struct_graph, $image_types, $fields_graph_template_template_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	/* graph item list goes here */
	if (!isempty_request_var('id')) {
		item();
	}

	if (!isempty_request_var('id')) {
		$template = db_fetch_row_prepared('SELECT *
			FROM graph_templates
			WHERE id = ?',
			array(get_request_var('id')));

		$template_graph = db_fetch_row_prepared('SELECT *
			FROM graph_templates_graph
			WHERE graph_template_id = ?
			AND local_graph_id=0',
			array(get_request_var('id')));

		$header_label = __esc('Graph Template [edit: %s]', $template['name']);
	} else {
		$header_label = __('Graph Template [new]');
	}

	form_start('graph_templates.php', 'graph_templates');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_graph_template_template_edit, (isset($template) ? $template : array()), (isset($template_graph) ? $template_graph : array()))
		)
	);

	html_end_box(true, true);

	html_start_box(__('Graph Template Options'), '100%', true, '3', 'center', '');

	$form_array = array();

	foreach ($struct_graph as $field_name => $field_array) {
		$form_array += array($field_name => $struct_graph[$field_name]);

		if ($form_array[$field_name]['method'] != 'spacer') {
			$form_array[$field_name]['value'] = (isset($template_graph[$field_name]) ? $template_graph[$field_name] : '');
		}

		$form_array[$field_name]['form_id'] = (isset($template_graph['id']) ? $template_graph['id'] : '0');

		if ($form_array[$field_name]['method'] != 'spacer') {
			$form_array[$field_name]['sub_checkbox'] = array(
				'name' => 't_' . $field_name,
				'friendly_name' => __esc('Check this checkbox if you wish to allow the user to override the value on the right during Graph creation.'),
				'value' => (isset($template_graph['t_' . $field_name]) ? $template_graph['t_' . $field_name] : '')
			);
		}
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $form_array
		)
	);

	form_hidden_box('rrdtool_version', get_rrdtool_version(), '');

	html_end_box(true, true);

	form_save_button('graph_templates.php', 'return');

	//Now we need some javascript to make it dynamic
	?>
	<script type='text/javascript'>

	$(function() {
		dynamic();
	});

	function dynamic() {
		$('#t_scale_log_units').prop('disabled', true);
		$('#scale_log_units').prop('disabled', true);
		if ($('#auto_scale_log').is(':checked')) {
			$('#t_scale_log_units').prop('disabled', false);
			$('#scale_log_units').prop('disabled', false);
		}
	}

	function changeScaleLog() {
		$('#t_scale_log_units').prop('disabled', true);
		$('#scale_log_units').prop('disabled', true);
		if ($('#auto_scale_log').is(':checked')) {
			$('#t_scale_log_units').prop('disabled', false);
			$('#scale_log_units').prop('disabled', false);
		}
	}
	</script>
	<?php
}

function template() {
	global $graph_actions, $item_rows, $image_types;

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
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'has_graphs' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => read_config_option('default_has') == 'on' ? 'true':'false'
			)
	);

	validate_store_request_vars($filters, 'sess_gt');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Graph Templates'), '100%', '', '3', 'center', 'graph_templates.php?action=template_edit');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_graph_template' action='graph_templates.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default' id='filter' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Graph Templates');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='has_graphs' <?php print (get_request_var('has_graphs') == 'true' ? 'checked':'');?>>
							<label for='has_graphs'><?php print __('Has Graphs');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>
		var disabled = true;

		function applyFilter() {
			strURL  = 'graph_templates.php';
			strURL += '&filter='+$('#filter').val();
			strURL += '&rows='+$('#rows').val();
			strURL += '&has_graphs='+$('#has_graphs').is(':checked');
			loadUrl({url:strURL})
		}

		function clearFilter() {
			strURL = 'graph_templates.php?clear=1';
			loadUrl({url:strURL})
		}

		$(function() {
			$('#refresh').click(function() {
				applyFilter();
			});

			$('#has_graphs').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#form_graph_template').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (gt.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (get_request_var('has_graphs') == 'true') {
		$sql_having = 'HAVING graphs>0';
	} else {
		$sql_having = '';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(`rows`)
		FROM (SELECT
			COUNT(gt.id) AS `rows`,
			COUNT(gl.id) AS graphs
			FROM graph_templates AS gt
			LEFT JOIN graph_local AS gl
			ON gt.id=gl.graph_template_id
			INNER JOIN (
				SELECT *
				FROM graph_templates_graph AS gtg
				WHERE gtg.local_graph_id=0
			) AS gtg
			ON gtg.graph_template_id=gt.id
			$sql_where
			GROUP BY gt.id
			$sql_having
		) AS rs");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$template_list = db_fetch_assoc("SELECT
		gt.id, gt.name, gl.snmp_query_id AS dqid,
		CONCAT(gtg.height,'x',gtg.width) AS size, gtg.vertical_label,
		gtg.image_format_id, COUNT(gl.id) AS graphs
		FROM graph_templates AS gt
		INNER JOIN graph_templates_graph AS gtg
		ON gtg.graph_template_id=gt.id
		AND gtg.local_graph_id=0
		LEFT JOIN graph_local AS gl
		ON gt.id=gl.graph_template_id
		$sql_where
		GROUP BY gt.id
		$sql_having
		$sql_order
		$sql_limit");

	$display_text = array(
		'name' => array(
			'display' => __('Graph Template Name'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The name of this Graph Template.')
		),
		'gt.id' => array(
			'display' => __('ID'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The internal ID for this Graph Template.  Useful when performing automation or debugging.')
		),
		'nosort3' => array(
			'display' => __('Deletable'),
			'align' => 'right',
			'tip' => __('Graph Templates that are in use cannot be Deleted.  In use is defined as being referenced by a Graph.')
		),
		'graphs' => array(
			'display' => __('Graphs Using'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The number of Graphs using this Graph Template.')
		),
		'size' => array(
			'display' => __('Size'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The default size of the resulting Graphs.')
		),
		'image_format_id' => array(
			'display' => __('Image Format'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The default image format for the resulting Graphs.')
		),
		'vertical_label' => array(
			'display' => __('Vertical Label'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The vertical label for the resulting Graphs.')
		)
	);

	$nav = html_nav_bar('graph_templates.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, sizeof($display_text) + 1, __('Graph Templates'), 'page', 'main');

	form_start('graph_templates.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (cacti_sizeof($template_list)) {
		foreach ($template_list as $template) {
			if ($template['graphs'] > 0) {
				$disabled = true;
			} else {
				$disabled = false;
			}
			form_alternate_row('line' . $template['id'], true, $disabled);
			form_selectable_cell(filter_value($template['name'], get_request_var('filter'), 'graph_templates.php?action=template_edit&id=' . $template['id']), $template['id']);
			form_selectable_cell($template['id'], $template['id'], '', 'right');
			form_selectable_cell($disabled ? __('No'):__('Yes'), $template['id'], '', 'right');
			form_selectable_cell(number_format_i18n($template['graphs'], '-1'), $template['id'], '', 'right');
			form_selectable_ecell($template['size'], $template['id'], '', 'right');
			form_selectable_cell($image_types[$template['image_format_id']], $template['id'], '', 'right');
			form_selectable_ecell($template['vertical_label'], $template['id'], '', 'right');
			form_checkbox_cell($template['name'], $template['id'], $disabled);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . __('No Graph Templates Found') . "</em></td></tr>\n";
	}
	html_end_box(false);

	if (cacti_sizeof($template_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($graph_actions);

	form_end();
}

