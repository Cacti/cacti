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

include('./include/auth.php');
include_once('./lib/export.php');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	default:
		top_header();

		export();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $export_types, $export_errors;

    /* ================= input validation ================= */
    get_filter_request_var('export_item_id');
    /* ==================================================== */

	if (isset_request_var('save_component_export')) {
		$export_errors = 0;
		$xml_data = get_item_xml(get_nfilter_request_var('export_type'), get_nfilter_request_var('export_item_id'), (((isset_request_var('include_deps') ? get_nfilter_request_var('include_deps') : '') == '') ? false : true));

		if (get_nfilter_request_var('output_format') == '1') {
			top_header();

			print "<table style='width:100%;' class='center'><tr><td style='text-align:left;'><pre>" . html_escape($xml_data) . '</pre></td></tr></table>';

			bottom_footer();
		} elseif (get_nfilter_request_var('output_format') == '2') {
			header('Content-type: application/xml');
			if ($export_errors) echo __('WARNING: Export Errors Encountered. Refresh Browser Window for Details!') . "\n";
			print $xml_data;
		} elseif (get_nfilter_request_var('output_format') == '3') {
			if ($export_errors) {
				header('Location: templates_export.php');
			} else {
				header('Content-type: application/xml');
				header('Content-Disposition: attachment; filename=cacti_' . get_nfilter_request_var('export_type') . '_' . strtolower(clean_up_file_name(db_fetch_cell(str_replace('|id|', get_nfilter_request_var('export_item_id'), $export_types[get_nfilter_request_var('export_type')]['title_sql'])))) . '.xml');
				print $xml_data;
			}
		}
	}
}

/* ---------------------------
    Template Export Functions
   --------------------------- */

function export() {
	global $export_types;

	/* 'graph_template' should be the default */
	if (!isset_request_var('export_type')) {
		set_request_var('export_type', 'host_template');
	}

	$type_found = false;

	foreach($export_types as $id => $type) {
		$export_array[$id] = $type['name'];
		if (get_nfilter_request_var('export_type') == $id) {
			$type_found = true;
		}
	}

	if (!$type_found) {
		set_request_var('export_type', 'host_template');
	}

	$form_template_export1 = array(
		'export_type' => array(
			'friendly_name' => __('What would you like to export?'),
			'description' => __('Select the Template type that you wish to export from Cacti.'),
			'method' => 'drop_array',
			'value' => get_nfilter_request_var('export_type'),
			'array' => $export_array,
			'default' => 'host_template'
		)
	);

	$form_template_export2 = array(
		'export_item_id' => array(
			'friendly_name' => __('Device Template to Export'),
			'description' => __('Choose the Template to export to XML.'),
			'method' => 'drop_sql',
			'value' => '0',
			'default' => '0',
			'sql' => $export_types[get_nfilter_request_var('export_type')]['dropdown_sql']
		),
		'include_deps' => array(
			'friendly_name' => __('Include Dependencies'),
			'description' => __('Some templates rely on other items in Cacti to function properly. It is highly recommended that you select this box or the resulting import may fail.'),
			'value' => 'on',
			'method' => 'checkbox',
			'default' => 'on'
		),
		'output_format' => array(
			'friendly_name' => __('Output Format'),
			'description' => __('Choose the format to output the resulting XML file in.'),
			'method' => 'radio',
			'value' => '3',
			'default' => '0',
			'items' => array(
				0 => array(
					'radio_value' => '1',
					'radio_caption' => __('Output to the Browser (within Cacti)'),
					),
				1 => array(
					'radio_value' => '2',
					'radio_caption' => __('Output to the Browser (raw XML)'),
					),
				2 => array(
					'radio_value' => '3',
					'radio_caption' => __('Save File Locally')
				)
			)
		)
	);

	form_start('templates_export.php', 'export');

	html_start_box( __('Export Templates'), '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $form_template_export1
		)
	);

	html_end_box();

	html_start_box( __('Available Templates [%s]', $export_types[get_nfilter_request_var('export_type')]['name']), '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $form_template_export2
		)
	);

	form_hidden_box('save_component_export','1','');

	html_end_box();

	?>
	<script type='text/javascript'>
	var stopTimer;

	$(function() {
		$('#export_type').change(function() {
			strURL = 'templates_export.php?header=false&export_type='+$('#export_type').val();
			loadPageNoHeader(strURL);
		});

		$('form#export').submit(function(event) {
			stopTimer = setTimeout(function() { Pace.stop() }, 1000);
		});
	});
	</script>
	<?php

	form_save_button('', 'export', '', false);
}
