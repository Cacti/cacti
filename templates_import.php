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
	global $preview_only, $messages, $import_messages;

	if (isset_request_var('save_component_import')) {
		//print '<pre>';print_r($_FILES);print '</pre>';exit;
		if (($_FILES['import_file']['tmp_name'] != 'none') && ($_FILES['import_file']['tmp_name'] != '')) {
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

		if (get_nfilter_request_var('preview_only') == 'true') {
			$preview_only = true;
		} else {
			$preview_only = false;
		}

		if (isset_request_var('remove_orphans') && get_nfilter_request_var('remove_orphans') == 'on') {
			$remove_orphans = true;
		} else {
			$remove_orphans = false;
		}

		if (isset_request_var('replace_svalues') && get_nfilter_request_var('replace_svalues') == 'on') {
			$replace_svalues = true;
		} else {
			$replace_svalues = false;
		}

		$import_hashes = array();

		/* loop through each of the graphs selected on the previous page and get more info about them */
		foreach ($_POST as $var => $val) {
			if (strpos($var, 'chk_') !== false) {
				$id = base64_decode(str_replace('chk_', '', $var));
				$id = json_decode($id, true);

				if (isset($id['hash'])) {
					$import_hashes[] = $id['hash'];
				}
			}
		}

		$import_messages = array();

		/* obtain debug information if it's set */
		$debug_data = import_xml_data($xml_data, $import_as_new, $profile_id, $remove_orphans, $replace_svalues, $import_hashes);

		if (!$preview_only) {
			raise_message('import_success', __('The Template Import Succeeded.'), MESSAGE_LEVEL_INFO);

			header('Location: templates_import.php');
		} elseif ($debug_data !== false && cacti_sizeof($debug_data)) {
			//print '<pre>';print_r($debug_data);print '</pre>';exit;

			$templates = prepare_template_display($debug_data);
			//print '<pre>';print_r($templates);print '</pre>';

			display_template_data($templates);

			exit;
		} else {
			cacti_log(sprintf("ERROR: Import or Preview failed for XML file %s!", $_FILES['import_file']['name']), false, 'IMPORT');

			$message_text = '';

			if (cacti_sizeof($import_messages)) {
				foreach($import_messages as $message) {
					if (isset($messages[$message])) {
						$message_text .= ($message_text != '' ? '<br>':'') . $messages[$message]['message'];
					}
				}
			}

			raise_message_javascript(__('Error in Template', 'package'), __('The Template XML file "%s" validation failed', $_FILES['import_file']['name']), __('See the cacti.log for more information, and review the XML file for proper syntax.  The error details are shown below.<br><br><b>Errors:</b><br>%s', $message_text));
		}
	}
}

function prepare_template_display(&$import_info) {
	global $hash_type_names;

	$templates = array();

	/**
	 * This function will create an array of item types and their status
	 * the user will have an option to import select items based upon
	 * these values.
	 *
	 * $templates['template_hash'] = array(
	 *    'package'      => 'some_package_name',
	 *    'package_file' => 'some_package_filename',
	 *    'type'         => 'some_type',
	 *    'type_name'    => 'some_type_name',
	 *    'name'         => 'some_name',
	 *    'status'       => 'some_status'
	 * );
	 */

	if (cacti_sizeof($import_info)) {
		foreach ($import_info as $type => $type_array) {
			if ($type == 'files') {
				$templates['files'] = $type_array;
				continue;
			}

			foreach ($type_array as $index => $vals) {
				$hash = $vals['hash'];

				if (!isset($templates[$hash])) {
					$templates[$hash]['status'] = $vals['type'];;
				} else {
					$templates[$hash]['status'] .= '<br>' . $vals['type'];;
				}

				$templates[$hash]['type']      = $type;
				$templates[$hash]['type_name'] = $hash_type_names[$type];
				$templates[$hash]['name']      = $vals['title'];

				unset($vals['title']);
				unset($vals['result']);
				unset($vals['hash']);
				unset($vals['type']);

				if (isset($vals['dep'])) {
					$template[$hash]['deps'] = $vals['dep'];
				}

				if (cacti_sizeof($vals)) {
					$templates[$hash]['vals'] = $vals;
				}
			}
		}
	}

	return $templates;
}

function display_template_data(&$templates) {
	global $config;

	if (isset($templates['files'])) {
		$files = $templates['files'];

		unset($templates['files']);

		html_start_box(__('Import Files [ If Files are missing, locate and install before using ]'), '100%', '', '1', 'center', '');

		$display_text = array(
			array(
				'display' => __('File Name')
			),
			array(
				'display' => __('Status')
			)
		);

		html_header($display_text);

		$id = 0;

		foreach($files as $path => $status) {
			if ($status == 'found') {
				$status = "<span class='deviceUp'>" . __('Exists') . '</span>';
			} elseif ($status == 'notreadable') {
				$status = "<span class='deviceRecovering'>" . __('Not Readable') . '</span>';
			} else {
				$status = "<span class='deviceDown'>" . __('Not Found') . '</span>';
			}

			form_alternate_row('line_' . $id);

			form_selectable_cell($path, $id);
			form_selectable_cell($status, $id);

			form_end_row();
		}

		html_end_box();
	}

	if (cacti_sizeof($templates)) {
		html_start_box(__('Import Templates [ None selected imports all, Check to import selectively ]'), '100%', '', '1', 'center', '');

		$display_text = array(
			array(
				'display' => __('Template Type')
			),
			array(
				'display' => __('Template Name')
			),
			array(
				'display' => __('Status')
			),
			array(
				'display' => __('Dependencies')
			),
			array(
				'display' => __('Changes/Diffferences')
			)
		);

		html_header_checkbox($display_text, false, '', true, 'import');

		$templates = array_reverse($templates);

		foreach($templates as $hash => $detail) {
			$id = base64_encode(
				json_encode(
					array(
						'hash'    => $hash,
						'type'    => $detail['type_name'],
						'name'    => $detail['name'],
						'status'  => $detail['status']
					)
				)
			);

			if ($detail['status'] == 'updated') {
				$status = "<span class='updateObject'>" . __('Updated') . '</span>';
			} elseif ($detail['status'] == 'new') {
				$status = "<span class='newObject'>" . __('New') . '</span>';
			} else {
				$status = "<span class='deviceUp'>" . __('Unchanged') . '</span>';
			}

			form_alternate_row('line_import_' . $detail['status'] . '_' . $id);

			form_selectable_cell($detail['type_name'], $id);
			form_selectable_cell($detail['name'], $id);
			form_selectable_cell($status, $id);

			if (isset($detail['deps'])) {
				$dep_details = array();
				$unmet_count = 0;
				$met_count   = 0;

				foreach($detail['deps'] as $hash => $dep) {
					if ($dep == 'met') {
						$dep_details[$dep] = $dep;
						$met_count++;
					} else {
						$dep_details[$dep] = $dep;
						$unmet_count++;
					}
				}

				if (isset($dep_details['met'])) {
					$dep_details['met'] = __('Met: %d', $met_count);
				}

				if (isset($dep_details['unmet'])) {
					$dep_details['unmet'] = __('Unmet: %d', $unmet_count);
				}

				form_selectable_cell(implode(', ', $diff_details), $id, '', 'white-space:pre-wrap');
			} else {
				form_selectable_cell(__('None'), $id);
			}

			if (isset($detail['vals'])) {
				$diff_details = '';
				$diff_array   = array();
				$orphan_array = array();

				foreach($detail['vals'] as $type => $diffs) {
					if ($type == 'differences') {
						foreach($diffs as $item) {
							$diff_array[$item] = $item;
						}
					} elseif ($type == 'orphans') {
						foreach($diffs as $item) {
							$orphan_array[$item] = $item;
						}
					}
				}

				if (cacti_sizeof($diff_array)) {
					$diff_details .= __('Differences', 'package') . '<br>' . implode('<br>', $diff_array);
				}

				if (cacti_sizeof($orphan_array)) {
					$diff_details .= ($diff_details != '' ? '<br>':'') . __('Orphans', 'package') . '<br>' . implode('<br>', $orphan_array);
				}

				form_selectable_cell($diff_details, $id, '', 'white-space:pre-wrap');
			} else {
				form_selectable_cell(__('None'), $id);
			}

			form_checkbox_cell($detail['name'], $id);

			form_end_row();
		}

		html_end_box();
	}
}

function bad_tmp() {
	html_start_box(__('Import Template'), '60%', '', '1', 'center', '');
	form_alternate_row();
	print "<td class='textarea'><p><strong>" . __('ERROR') . ":</strong> " .__('Failed to access temporary folder, import functionality is disabled') . "</p></td></tr>\n";
	html_end_box();
}

function import() {
	global $hash_type_names, $fields_template_import;

	$default_profile = db_fetch_cell('SELECT id FROM data_source_profiles WHERE `default`="on"');
	if (empty($default_profile)) {
		$default_profile = db_fetch_cell('SELECT id FROM data_source_profiles ORDER BY id LIMIT 1');
	}

	form_start('templates_import.php', 'import', true);

	/* ================= input validation and session storage ================= */
	$filters = array(
		'preview_only' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'default' => 'on'
		),
		'replace_svalues' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'default' => ''
		),
		'remove_orphans' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'default' => ''
		),
		'data_source_profile' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => $default_profile
		),
		'image_format' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_config_option('default_image_format')
		),
		'graph_width' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_config_option('default_graph_width')
		),
		'graph_height' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_config_option('default_graph_height')
		),
	);

	validate_store_request_vars($filters, 'sess_pimport');
	/* ================= input validation ================= */

	$fields_template_import['import_data_source_profile']['default'] = $default_profile;

	if (isset_request_var('replace_svalues') && get_nfilter_request_var('replace_svalues') == 'true') {
		$fields_template_import['replace_svalues']['value'] = 'on';
	} else {
		$fields_template_import['replace_svalues']['value'] = '';
	}

	if (isset_request_var('remove_orphans') && get_nfilter_request_var('remove_orphans') == 'true') {
		$fields_template_import['remove_orphans']['value'] = 'on';
	} else {
		$fields_template_import['remove_orphans']['value'] = '';
	}

	if (isset_request_var('image_format')) {
		$fields_template_import['image_format']['value'] = get_filter_request_var('image_format');
	} else {
		$fields_template_import['image_format']['value'] = read_config_option('default_image_format');
	}

	if (isset_request_var('graph_width')) {
		$fields_template_import['graph_width']['value'] = get_filter_request_var('graph_width');
	} else {
		$fields_template_import['graph_width']['value'] = read_config_option('default_graph_width');
	}

	if (isset_request_var('graph_height')) {
		$fields_template_import['graph_height']['value'] = get_filter_request_var('graph_height');
	} else {
		$fields_template_import['graph_height']['value'] = read_config_option('default_graph_height');
	}

	html_start_box(__('Import Template'), '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $fields_template_import
		)
	);

	html_end_box(true, true);

	print '<div id="contents"></div>';

	form_hidden_box('save_component_import','1','');

	form_save_button('', 'import', 'import', false);

	?>
	<script type='text/javascript'>
	$(function() {
		$('#import_file').change(function() {
			var form = $('#import')[0];
			var data = new FormData(form);

			Pace.start();

			$.ajax({
				type: 'POST',
				enctype: 'multipart/form-data',
				url: urlPath + '/templates_import.php?preview_only=true',
				data: data,
				processData: false,
				contentType: false,
				cache: false,
				timeout: 600000,
				success: function (data) {
					if ($('#contents').length == 0) {
						$('#main').append('<div id="contents"></div>');
					} else {
						$('#contents').empty();
					}

					$('#contents').html(data);

					if ($('#templates_import_save2_child').length) {
						applySelectorVisibilityAndActions();

						$('#templates_import_save2_child').find('tr[id^="line_import_new_"]').each(function(event) {
							selectUpdateRow(event, $(this));
						});
					}

					Pace.stop();
				},
				error: function (e) {
					if ($('#contents').length == 0) {
						$('#main').append('<div id="contents"></div>');
					} else {
						$('#contents').empty();
					}

					$('#contents').html(data);

					Pace.stop();
				}
			});
		});
	});
	</script>
	<?php
}

function is_tmp_writable($tmp_dir) {
	$tmp_dir = sys_get_temp_dir();
	$tmp_len = strlen($tmp_dir);
	$tmp_dir .= ($tmp_len !== 0 && substr($tmp_dir, -$tmp_len) === '/') ? '': '/';
	$is_tmp = is_resource_writable($tmp_dir);
	return $is_tmp;
}
