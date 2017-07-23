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
include_once('./lib/import.php');
include_once('./lib/utility.php');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	default:
		top_header();

		import();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $preview_only;

	if (isset_request_var('save_component_import')) {
		if (trim(get_nfilter_request_var('import_text') != '')) {
			/* textbox input */
			$xml_data = get_nfilter_request_var('import_text');
		} elseif (($_FILES['import_file']['tmp_name'] != 'none') && ($_FILES['import_file']['tmp_name'] != '')) {
			/* file upload */
			$fp = fopen($_FILES['import_file']['tmp_name'],'r');
			$xml_data = fread($fp,filesize($_FILES['import_file']['tmp_name']));
			fclose($fp);
		} else {
			header('Location: templates_import.php'); exit;
		}

		if (get_filter_request_var('import_data_source_profile') == '0') {
			$import_as_new = true;
			$profile_id = db_fetch_cell('SELECT id FROM data_source_profiles ORDER BY `default` DESC LIMIT 1');
		} else {
			$import_as_new = false;
			$profile_id = get_request_var('import_data_source_profile');
		}

		if (get_nfilter_request_var('preview_only') == 'on') {
			$preview_only = true;
		} else {
			$preview_only = false;
		}

		if (isset_request_var('remove_orphans') && get_nfilter_request_var('remove_orphans') == 'on') {
			$remove_orphans = true;
		} else {
			$remove_orphans = false;
		}

		/* obtain debug information if it's set */
		$debug_data = import_xml_data($xml_data, $import_as_new, $profile_id, $remove_orphans);
		if(sizeof($debug_data) > 0) {
			$_SESSION['import_debug_info'] = $debug_data;
		}

		header('Location: templates_import.php?preview=' . $preview_only);
	}
}

/* ---------------------------
    Template Import Functions
   --------------------------- */

function import() {
	global $hash_type_names, $fields_template_import;

	print "<form method='post' action='templates_import.php' enctype='multipart/form-data'>\n";

	$display_hideme = false;

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		import_display_results($_SESSION['import_debug_info'], array(), true, get_filter_request_var('preview'));

		kill_session_var('import_debug_info');

		$display_hideme = true;
	}

	html_start_box(__('Import Template'), '100%', true, '3', 'center', '');

	$default_profile = db_fetch_cell('SELECT id FROM data_source_profiles WHERE `default`="on"');
	if (empty($default_profile)) {
		$default_profile = db_fetch_cell('SELECT id FROM data_source_profiles ORDER BY id LIMIT 1');
	}

	$fields_template_import['import_data_source_profile']['default'] = $default_profile;

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $fields_template_import
		)
	);

	html_end_box(true, true);

	form_hidden_box('save_component_import','1','');

	form_save_button('', 'import');

	?>
	<script type='text/javascript'>
	$(function() {
		<?php if ($display_hideme) { ?>
		$('#templates_import1').find('.cactiTableButton > span').html('<a href="#" id="hideme"><?php print __('Hide');?></a>');
		$('#hideme').click(function() {
			$('#templates_import1').hide();
		});
		<?php } ?>
		$('#remove_orphans').prop('checked', false).prop('disabled', true);
	});
	</script>
	<?php
}

