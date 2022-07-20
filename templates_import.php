<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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
include_once('./lib/import.php');
include_once('./lib/poller.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');

/* set default action */
set_default_action();

$action = get_request_var('action');
$is_save = isset_request_var('save_component_import');

$tmp_dir = sys_get_temp_dir();
$tmp_len = strlen($tmp_dir);
$tmp_dir .= ($tmp_len !== 0 && substr($tmp_dir, -$tmp_len) === '/') ? '': '/';
$is_tmp = is_tmp_writable(sys_get_temp_dir());

if ($is_tmp && $is_save && $action == 'save') {
	form_save();
} else {
	top_header();

	if ($is_tmp) {
		import();
	} else {
		bad_tmp();
	}

	bottom_footer();
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
		if ($debug_data !== false && cacti_sizeof($debug_data)) {
			$_SESSION['import_debug_info'] = $debug_data;
		} else {
			cacti_log("ERROR: Import or Preview failed!", false, 'IMPORT');
			raise_message('import_error', __('The Template Import Failed.  See the cacti.log for more details.', MESSAGE_LEVEL_ERROR));
		}

		header('Location: templates_import.php?preview=' . $preview_only);
	}
}

function bad_tmp() {
	html_start_box(__('Import Template'), '60%', '', '1', 'center', '');
	form_alternate_row();
	print "<td class='textarea'><p><strong>" . __('ERROR') . ":</strong> " .__('Failed to access temporary folder, import functionality is disabled') . "</p></td></tr>\n";
	html_end_box();
}
/* ---------------------------
    Template Import Functions
   --------------------------- */

function import() {
	global $hash_type_names, $fields_template_import;

	form_start('templates_import.php', 'import', true);

	$display_hideme = false;

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		import_display_results($_SESSION['import_debug_info'], array(), true, get_filter_request_var('preview'));

		form_save_button('', 'close');
		kill_session_var('import_debug_info');
	} else {
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

		form_save_button('', 'import', 'import', false);

		?>
		<script type='text/javascript'>
		$(function() {
			<?php if ($display_hideme) { ?>
			$('#templates_import1').find('.cactiTableButton > span').html('<a href="#" id="hideme"><?php print __('Hide');?></a>');
			$('#hideme').click(function() {
				$('#templates_import1').hide();
			});
			<?php } ?>
		});
		</script>
		<?php
	}
}

function is_tmp_writable($tmp_dir) {
	$tmp_dir = sys_get_temp_dir();
	$tmp_len = strlen($tmp_dir);
	$tmp_dir .= ($tmp_len !== 0 && substr($tmp_dir, -$tmp_len) === '/') ? '': '/';
	$is_tmp = is_resource_writable($tmp_dir);
	return $is_tmp;
}
