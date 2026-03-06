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
require_once(CACTI_PATH_LIBRARY . '/export.php');

// set default action
set_default_action();

switch (grv('action')) {
	case 'save':
		form_save();

		break;
	default:
		top_header();

		export();

		bottom_footer();

		break;
}

function form_save() : void {
	global $export_types, $export_errors;

	// ================= input validation =================
	gfrv('export_item_id');
	// ====================================================

	if (isrv('save_component_export')) {
		$export_errors = 0;
		$xml_data      = get_item_xml(gnrv('export_type'), gnrv('export_item_id'), (((isrv('include_deps') ? gnrv('include_deps') : '') == '') ? false : true));

		if (gnrv('output_format') == '1') {
			top_header();

			print "<table style='width:100%;' class='center'><tr><td style='text-align:left;'><pre>" . htmle($xml_data) . '</pre></td></tr></table>';

			bottom_footer();
		} elseif (gnrv('output_format') == '2') {
			header('Content-type: application/xml');

			if ($export_errors) {
				print __('WARNING: Export Errors Encountered. Refresh Browser Window for Details!') . "\n";
			}
			print $xml_data;
		} elseif (gnrv('output_format') == '3') {
			if ($export_errors) {
				header('Location: templates_export.php');
			} else {
				header('Content-type: application/xml');
				header('Content-Disposition: attachment; filename=cacti_' . gnrv('export_type') . '_' . cacti_strtolower(clean_up_file_name(db_fetch_cell(str_replace('|id|', gnrv('export_item_id'), $export_types[gnrv('export_type')]['title_sql'])))) . '.xml');
				print $xml_data;
			}
		}
	}
}

function export() : void {
	global $export_types;

	// 'graph_template' should be the default
	if (!isrv('export_type')) {
		srv('export_type', 'host_template');
	}

	$type_found   = false;
	$export_array = [];

	foreach ($export_types as $id => $type) {
		$export_array[$id] = $type['name'];

		if (gnrv('export_type') == $id) {
			$type_found = true;
		}
	}

	if (!$type_found) {
		srv('export_type', 'host_template');
	}

	$form_template_export1 = [
		'export_type' => [
			'friendly_name' => __('What would you like to export?'),
			'description'   => __('Select the Template type that you wish to export from Cacti.'),
			'method'        => 'drop_array',
			'value'         => gnrv('export_type'),
			'array'         => $export_array,
			'default'       => 'host_template'
		]
	];

	$form_template_export2 = [
		'export_item_id' => [
			'friendly_name' => __('Device Template to Export'),
			'description'   => __('Choose the Template to export to XML.'),
			'method'        => 'drop_sql',
			'value'         => '0',
			'default'       => '0',
			'sql'           => $export_types[gnrv('export_type')]['dropdown_sql']
		],
		'include_deps' => [
			'friendly_name' => __('Include Dependencies'),
			'description'   => __('Some templates rely on other items in Cacti to function properly. It is highly recommended that you select this box or the resulting import may fail.'),
			'value'         => 'on',
			'method'        => 'checkbox',
			'default'       => 'on'
		],
		'output_format' => [
			'friendly_name' => __('Output Format'),
			'description'   => __('Choose the format to output the resulting XML file in.'),
			'method'        => 'radio',
			'value'         => '3',
			'default'       => '0',
			'items'         => [
				0 => [
					'radio_value'   => '1',
					'radio_caption' => __('Output to the Browser (within Cacti)'),
					],
				1 => [
					'radio_value'   => '2',
					'radio_caption' => __('Output to the Browser (raw XML)'),
					],
				2 => [
					'radio_value'   => '3',
					'radio_caption' => __('Save File Locally')
				]
			]
		]
	];

	form_start('templates_export.php', 'export');

	html_start_box(__('Export Templates'), '100%', false, 3, 'center', '');

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => $form_template_export1
		]
	);

	html_end_box();

	html_start_box(__('Available Templates [%s]', $export_types[gnrv('export_type')]['name']), '100%', false, 3, 'center', '');

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => $form_template_export2
		]
	);

	form_hidden_box('save_component_export','1','');

	html_end_box();

	?>
	<script type='text/javascript'>
	var stopTimer;

	$(function() {
		$('#export_type').change(function() {
			strURL = 'templates_export.php?export_type='+$('#export_type').val();
			loadUrl({url:strURL})
		});

		$('form#export').submit(function(event) {
			stopTimer = setTimeout(function() { Pace.stop() }, 1000);
		});
	});
	</script>
	<?php

	form_save_button('', 'export', '', false);
}
