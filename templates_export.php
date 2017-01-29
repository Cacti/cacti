<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

			print "<table width='100%' align='center'><tr><td><pre>" . htmlspecialchars($xml_data) . '</pre></td></tr></table>';

			bottom_footer();
		}elseif (get_nfilter_request_var('output_format') == '2') {
			header('Content-type: application/xml');
			if ($export_errors) echo __('WARNING: Export Errors Encountered. Refresh Browser Window for Details!') . "\n";
			print $xml_data;
		}elseif (get_nfilter_request_var('output_format') == '3') {
			if ($export_errors) {
				header('Location: templates_export.php');
			}else{
				header('Content-type: application/xml');
				header('Content-Disposition: attachment; filename=cacti_' . get_nfilter_request_var('export_type') . '_' . strtolower(clean_up_file_name(db_fetch_cell(str_replace('|id|', get_nfilter_request_var('export_item_id'), $export_types{get_nfilter_request_var('export_type')}['title_sql'])))) . '.xml');
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
		set_request_var('export_type', 'graph_template');
	}

	html_start_box( __('Export Templates'), '100%', '', '3', 'center', '');
	?>
	<tr class='tableRow'>
		<td>
			<form name='form_graph_id' action='templates_export.php'>
				<table>
					<tr>
						<td style='font-size:1.2em;'><?php print __('What would you like to export?');?></td>
						<td>
							<select id='export_type'>
								<?php
								while (list($key, $array) = each($export_types)) {
									print "<option value='$key'"; if (get_nfilter_request_var('export_type') == $key) { print ' selected'; } print '>' . htmlspecialchars($array['name'], ENT_QUOTES) . "</option>\n";
								}
								?>
							</select>
						</td>
					</tr>
				</table>
			</form>
			<script type='text/javascript'>
			$(function() {
				$('#export_type').change(function() {
					strURL = 'templates_export.php?header=false&export_type='+$('#export_type').val();
					loadPageNoHeader(strURL);
				});
			});
			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	print "<form id='export' method='post' action='templates_export.php'>\n";

	html_start_box( __('Available Templates [%s]', $export_types{get_nfilter_request_var('export_type')}['name']), '100%', '', '3', 'center', '');

	form_alternate_row();?>
		<td width='50%'>
			<font class='textEditTitle'><?php print __('%s to Export', $export_types{get_nfilter_request_var('export_type')}['name']); ?></font><br>
			<?php print __('Choose the exact item to export to XML.'); ?>
		</td>
		<td>
			<?php form_dropdown('export_item_id', db_fetch_assoc($export_types{get_nfilter_request_var('export_type')}['dropdown_sql']),'name','id','','','0');?>
		</td>
	</tr>

	<?php form_alternate_row(); ?>
		<td width='50%'>
			<font class='textEditTitle'><?php print __('Include Dependencies'); ?></font><br>
			<?php print __('Some templates rely on other items in Cacti to function properly. It is highly recommended that you select this box or the resulting import may fail.');?>
		</td>
		<td>
			<?php form_checkbox('include_deps', 'on', __('Include Dependencies'), 'on', '', true);?>
		</td>
	</tr>

	<?php form_alternate_row(); ?>
		<td width='50%'>
			<font class='textEditTitle'><?php print __('Output Format');?></font><br>
			<?php print __('Choose the format to output the resulting XML file in.');?>
		</td>
		<td>
			<?php
			form_radio_button('output_format', '3', '1', __('Output to the Browser (within Cacti)'),'1',true); print '<br>';
			form_radio_button('output_format', '3', '2', __('Output to the Browser (raw XML)'),'1',true); print '<br>';
			form_radio_button('output_format', '3', '3', __('Save File Locally'),'1',true);
			form_hidden_box('export_type', get_nfilter_request_var('export_type'), '');
			form_hidden_box('save_component_export','1','');
			?>
		</td>
	</tr>
	<?php

	html_end_box();

	form_save_button('', 'export');
}

