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
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

$actions = [
	1 => __('Delete'),
	2 => __('Duplicate'),
	3 => __('Export')
];

// set default action
set_default_action();

switch (grv('action')) {
	case 'save':
		if (isrv('save_component_import')) {
			profile_import_process();
		} else {
			form_save();
		}

		break;
	case 'import':
		top_header();
		profile_import();
		bottom_footer();

		break;
	case 'export':
		profile_export();

		break;
	case 'actions':
		form_actions();

		break;
	case 'item_remove_confirm':
		profile_item_remove_confirm();

		break;
	case 'item_remove':
		profile_item_remove();

		break;
	case 'ajax_span':
		gfrv('profile_id');
		gfrv('span');
		gfrv('rows');

		if (is_numeric(grv('rows')) && grv('rows') > 0) {
			gfrv('rows');

			$sampling_interval = db_fetch_cell_prepared('SELECT step
				FROM data_source_profiles
				WHERE id = ?',
				[grv('profile_id')]);

			if (grv('span') == 1) {
				print get_span(grv('rows') * $sampling_interval);
			} else {
				print get_span(grv('rows') * grv('span'));
			}
		} else {
			print __('N/A');
		}

		break;
	case 'ajax_size':
		gfrv('id');
		gfrv('cfs');
		gfrv('rows');
		print get_size(intval(grv('id')), gnrv('type'), grv('cfs'), intval(grv('rows')));

		break;
	case 'item_edit':
		top_header();

		item_edit();

		bottom_footer();

		break;
	case 'edit':
		top_header();

		profile_edit();

		bottom_footer();

		break;
	default:
		top_header();

		profile();

		bottom_footer();

		break;
}

function profile_export() : void {
	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (cacti_sizeof($selected_items) == 1) {
				$export_data = profile_export_execute($selected_items[0]);
			} else {
				$profiles = [];

				foreach ($selected_items as $id) {
					$profiles[] = $id;
				}

				$export_data = profile_export_execute($profiles);
			}

			if (cacti_sizeof($export_data)) {
				$export_file_name = $export_data['export_name'];

				header('Content-type: application/json');
				header('Content-Disposition: attachment; filename=' . $export_file_name);

				$output = json_encode($export_data, JSON_PRETTY_PRINT);

				print $output;
			}
		}
	}
}

function profile_import() : void {
	$form_data = [
		'import_file' => [
			'friendly_name' => __('Import Data Source Profile from Local File'),
			'description'   => __('If the JSON file containing the Data Source Profile data is located on your local machine, select it here.'),
			'method'        => 'file',
			'accept'        => '.json'
		],
		'import_text' => [
			'method'        => 'textarea',
			'friendly_name' => __('Import Data Source Profile from Text'),
			'description'   => __('If you have the JSON file containing the Data Source Profile data as text, you can paste it into this box to import it.'),
			'value'         => '',
			'default'       => '',
			'textarea_rows' => '10',
			'textarea_cols' => '80',
			'class'         => 'textAreaNotes'
		]
	];

	form_start('data_source_profiles.php', 'chk', true);

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box(__('Import Results'), '60%', false, 3, 'center', '');

		print '<tr class="tableHeader"><th>' . __('Cacti has imported the following items:') . '</th></tr>';

		foreach ($_SESSION['import_debug_info'] as $line) {
			print '<tr><td>' . $line . '</td></tr>';
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box(__('Import Data Source Profiles'), '60%', false, 3, 'center', '');

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => $form_data
		]
	);

	form_hidden_box('save_component_import', '1', '');

	print "	<tr><td><hr/></td></tr><tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='save'>
			<input type='submit' value='" . __esc('Import') . "' title='" . __esc('Import Data Source Profiles') . "' class='ui-button ui-corner-all ui-widget ui-state-active'>
		</td>
		<script type='text/javascript'>
		$(function() {
			Pace.stop();
			clearAllTimeouts();
		});
		</script>
	</tr>";

	html_end_box();
}

function profile_import_execute(mixed $json_data) : array {
	$debug_data = [];

	/**
	 * This version of the import process does not need to concern itself with
	 * hashes, so we will use a top down approach.
	 */
	if (is_array($json_data) && cacti_sizeof($json_data) && isset($json_data['profile'])) {
		$error = false;
		$save  = [];

		foreach ($json_data['profile'] as $data) {
			$perror = false;
			$name   = $data['name'];

			// mark the columns in the data that need to be excluded
			$exclude[] = 'rras';
			$exclude[] = 'cfs';

			if (!profile_validate_import_columns('data_source_profiles', $data, $debug_data, $exclude)) {
				$debug_data['errors'][] = __('The Data Source Profile import columns do not match the database schema');
				$error                  = true;
				$perror                 = true;
			}

			if (!cacti_sizeof($data['cfs'])) {
				$error                  = true;
				$debug_data['errors'][] = __('The Data Source Profile export %s did not include any CFs!', $data['name']);
				$perror                 = true;
			}

			if (!cacti_sizeof($data['rras'])) {
				$error                  = true;
				$debug_data['errors'][] = __('The Data Source Profile export %s did not include any RRAs!', $data['name']);
				$perror                 = true;
			}

			// skip the import if there were precheck errors
			if ($perror) {
				continue;
			}

			// check to see if the profile exists already
			$id = db_fetch_cell_prepared('SELECT id
				FROM data_source_profiles
				WHERE hash = ?',
				[$data['hash']]);

			// save the core data
			$save = $data;

			// unset the related data
			unset($save['id']);
			unset($save['rras']);
			unset($save['cfs']);

			if ($id > 0) {
				$exists     = true;
				$save['id'] = $id;
			} else {
				$exists     = false;
				$save['id'] = 0;
			}

			$data_source_profile_id = sql_save($save, 'data_source_profiles');

			/**
			 * next we will update the consolidation functions
			 * and then remove any that were not present
			 * in the import file.
			 */
			foreach ($data['cfs'] as $cf) {
				db_execute_prepared('REPLACE INTO data_source_profiles_cf
					(data_source_profile_id, consolidation_function_id)
					VALUES (?, ?)',
					[$data_source_profile_id, $cf]);
			}

			// next do the removal of non-found cfs
			db_execute_prepared('DELETE FROM data_source_profiles_cf
				WHERE data_source_profile_id = ?
				AND consolidation_function_id NOT IN (' . implode(',', array_map('intval', array_values($data['cfs']))) . ')',
				[$data_source_profile_id]);

			/**
			 * next we will do the RRA's one at a time noting their id if they exist
			 * and the ID's of those that were added.
			 */
			$ids = [];

			foreach ($data['rras'] as $rra) {
				$id = db_fetch_cell_prepared('SELECT id
					FROM data_source_profiles_rra
					WHERE name = ?
					AND steps = ?
					AND `rows` = ?
					AND timespan = ?
					AND data_source_profile_id = ?',
					[
						$rra['name'],
						$rra['steps'],
						$rra['rows'],
						$rra['timespan'],
						$data_source_profile_id
					]
				);

				$save                           = $rra;
				$save['data_source_profile_id'] = $data_source_profile_id;

				if ($id > 0) {
					$save['id'] = $id;
					$ids[$id]   = $id;
				} else {
					$save['id'] = 0;
				}

				$rra_id = sql_save($save, 'data_source_profiles_rra');

				$ids[$rra_id] = $rra_id;
			}

			// finally, remove any rras that were not updated
			db_execute_prepared('DELETE FROM data_source_profiles_rra
				WHERE data_source_profile_id = ?
				AND id NOT IN (' . implode(',', array_values($ids)) . ')',
				[$data_source_profile_id]);

			if ($data_source_profile_id > 0) {
				if (CACTI_WEB) {
					$debug_data['success'][] = __esc('Data Source Profile \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
				} else {
					$debug_data['success'][] = __('Data Source Profile \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
				}
			} else {
				if (CACTI_WEB) {
					$debug_data['failure'][] = __esc('Data Source Profile \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
				} else {
					$debug_data['failure'][] = __('Data Source Profile \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
				}
			}
		}
	} else {
		$debug_data['failure'][] = __('Data Source Profile Import data is either for another object type or not JSON formatted.');
	}

	return $debug_data;
}

function profile_validate_import_columns(string $table, array &$data, array &$debug_data, array $exclude = []) : bool {
	if (cacti_sizeof($data)) {
		foreach ($data as $column => $cdata) {
			if (!db_column_exists($table, $column) && !in_array($column, $exclude, true)) {
				$debug_data['errors'][] = __('Template column \'' . $column . '\' is not valid column.');

				cacti_log('Template column \'' . $column . '\' is not valid column.', false, 'AUTOM8');

				return false;
			}
		}
	} else {
		return false;
	}

	return true;
}

function profile_import_process() : void {
	$json_data  = json_decode(gnrv('import_text'), true);
	$debug_data = [];

	// If we have text, then we were trying to import text, otherwise we are uploading a file for import
	if (empty($json_data)) {
		$json_data = profile_validate_upload();
	}

	$return_data = profile_import_execute($json_data);

	if (sizeof($return_data) && isset($return_data['success'])) {
		foreach ($return_data['success'] as $message) {
			$debug_data[] = '<span class="deviceUp">' . __('NOTE:') . '</span> ' . $message;
			cacti_log('NOTE: Data Source Profile Import Succeeded!.  Message: ' . $message, false, 'AUTOM8');
		}
	}

	if (isset($return_data['errors'])) {
		foreach ($return_data['errors'] as $error) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $error;
			cacti_log('NOTE: Data Source Profile Import Error!.  Message: ' . $error, false, 'AUTOM8');
		}
	}

	if (isset($return_data['failure'])) {
		foreach ($return_data['failure'] as $message) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $message;
			cacti_log('NOTE: Data Source Profile Import Failed!.  Message: ' . $message, false, 'AUTOM8');
		}
	}

	if (cacti_sizeof($debug_data)) {
		$_SESSION['import_debug_info'] = $debug_data;
	}

	header('Location: data_source_profiles.php?action=import');

	exit();
}

function profile_validate_upload() : bool {
	// check file transfer if used
	if (isset($_FILES['import_file'])) {
		// check for errors first
		if ($_FILES['import_file']['error'] != 0) {
			switch ($_FILES['import_file']['error']) {
				case 1:
					raise_message('ftb', __('The file is too big.'), MESSAGE_LEVEL_ERROR);

					break;
				case 2:
					raise_message('ftb2', __('The file is too big.'), MESSAGE_LEVEL_ERROR);

					break;
				case 3:
					raise_message('ift', __('Incomplete file transfer.'), MESSAGE_LEVEL_ERROR);

					break;
				case 4:
					raise_message('nfu', __('No file uploaded.'), MESSAGE_LEVEL_ERROR);

					break;
				case 6:
					raise_message('tfm', __('Temporary folder missing.'), MESSAGE_LEVEL_ERROR);

					break;
				case 7:
					raise_message('ftwf', __('Failed to write file to disk'), MESSAGE_LEVEL_ERROR);

					break;
				case 8:
					raise_message('fusbe', __('File upload stopped by extension'), MESSAGE_LEVEL_ERROR);

					break;
			}

			if (is_error_message()) {
				return false;
			}
		}

		// check mine type of the uploaded file
		if ($_FILES['import_file']['type'] != 'application/json') {
			raise_message('ife', __('Invalid file extension.'), MESSAGE_LEVEL_ERROR);

			return false;
		}

		return json_decode(file_get_contents($_FILES['import_file']['tmp_name']), true);
	}

	raise_message('nfu2', __('No file uploaded.'), MESSAGE_LEVEL_ERROR);

	return false;
}

function profile_export_execute(mixed $profile_ids) : array {
	/**
	 * Tables for export include:
	 *
	 * data_source_profiles
	 * data_source_profiles_cf
	 * data_source_profiles_rra
	 *
	 * There are no hashes in the sub-tables.  On import
	 * we will simply remove any non-matching entries
	 * in the sub-tables if the hash of the data source
	 * profile is found on the foreign system.
	 *
	 */
	if (!is_array($profile_ids)) {
		$export_name = db_fetch_cell_prepared("SELECT CONCAT('profile_', name)
			FROM data_source_profiles
			WHERE id = ?",
			[$profile_ids]);

		$profile_ids = [$profile_ids];
	} else {
		$export_name = 'profiles_multiple';
	}

	$json_array = [];

	$json_array['name']        = clean_up_name(cacti_strtolower($export_name));
	$json_array['export_name'] = $json_array['name'] . '.json';

	if (cacti_sizeof($profile_ids)) {
		$profiles = [];

		foreach ($profile_ids as $id) {
			// get the row of data
			$profile = db_fetch_row_prepared('SELECT *
				FROM data_source_profiles
				WHERE id = ?',
				[$id]);

			unset($profile['id']);
			unset($profile['default']);
			unset($profile['data_sources']);
			unset($profile['templates']);

			$tmp_cfs = array_rekey(
				db_fetch_assoc_prepared('SELECT consolidation_function_id AS id
					FROM data_source_profiles_cf
					WHERE data_source_profile_id = ?',
					[$id]),
				'id', 'id'
			);

			$consolidations = array_values($tmp_cfs);

			$rras = db_fetch_assoc_prepared('SELECT name, steps, `rows`, timespan
				FROM data_source_profiles_rra
				WHERE data_source_profile_id = ?',
				[$id]);

			$profile['cfs']  = $consolidations;
			$profile['rras'] = $rras;

			$profiles[] = $profile;
		}

		$json_array['profile'] = $profiles;
	}

	return $json_array;
}

function form_save() : void {
	// make sure ids are numeric
	if (isrv('id') && ! is_numeric(gfrv('id'))) {
		srv('id', 0);
	}

	if (isrv('profile_id') && ! is_numeric(gfrv('profile_id'))) {
		srv('profile_id', 0);
	}

	if (grv('id') > 0) {
		$prev_heartbeat = db_fetch_cell_prepared('SELECT heartbeat
			FROM data_source_profiles
			WHERE id = ?',
			[grv('id')]);
	} else {
		$prev_heartbeat = grv('heartbeat');
	}

	if (isrv('save_component_profile')) {
		$save['id']             = form_input_validate(grv('id'), 'id', '^[0-9]+$', false, 3);
		$save['hash']           = get_hash_data_source_profile(grv('id'));

		$save['name']           = form_input_validate(gnrv('name'), 'name', '', false, 3);

		if (isrv('step')) {
			$save['step']           = form_input_validate(gnrv('step'), 'step', '', false, 3);
			$save['heartbeat']      = form_input_validate(gnrv('heartbeat'), 'heartbeat', '', false, 3);
			$save['x_files_factor'] = form_input_validate(gnrv('x_files_factor'), 'x_files_factor', '', false, 3);
		}

		if (isrv('default')) {
			$save['default'] = isrv('default') == true ? 'on' : '';
			db_execute('UPDATE data_source_profiles SET `default` = ""');
		}

		if (!is_error_message()) {
			$profile_id = sql_save($save, 'data_source_profiles');

			if ($profile_id) {
				if (isrv('step')) {
					// Validate consolidation functions
					$cfs = gnrv('consolidation_function_id');

					if (cacti_sizeof($cfs) && !empty($cfs)) {
						foreach ($cfs as $cf) {
							input_validate_input_number($cf, 'consolidation_function_id');
						}

						db_execute_prepared('DELETE FROM data_source_profiles_cf
							WHERE data_source_profile_id = ?
							AND consolidation_function_id NOT IN (' . implode(',', array_map('intval', $cfs)) . ')', [$profile_id]);
					}

					// Validate consolidation functions
					$cfs = gnrv('consolidation_function_id');

					if (cacti_sizeof($cfs) && !empty($cfs)) {
						foreach ($cfs as $cf) {
							db_execute_prepared('REPLACE INTO data_source_profiles_cf
								(data_source_profile_id, consolidation_function_id)
								VALUES (?, ?)', [$profile_id, $cf]);
						}
					}
				}

				if ($prev_heartbeat != grv('heartbeat')) {
					$existing = db_fetch_cell_prepared('SELECT COUNT(*)
						FROM data_template_data
						WHERE data_source_profile_id = ?
						AND local_data_id > 0',
						[grv('id')]);

					if ($existing) {
						db_execute_prepared('UPDATE data_template_rrd AS dtr
							INNER JOIN data_template_data AS dtd
							ON dtd.local_data_id = dtr.local_data_id
							SET dtr.rrd_heartbeat = ?
							WHERE dtd.data_source_profile_id = ?',
							[grv('heartbeat'), grv('id')]);

						raise_message('heartbeat_change', __('Changing the Heartbeat from this page, does not change the Heartbeat for your existing Data Sources.  Use RRDtool\'s \'tune\' function to make that change to your existing RRDfiles heartbeats, or run the CLI utility update_heartbeat.php to correct.<br>'), MESSAGE_LEVEL_WARN);
					}
				}

				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: data_source_profiles.php?action=edit&id=' . (empty($profile_id) ? grv('id') : $profile_id));
	} elseif (isrv('save_component_rra')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('profile_id');
		// ====================================================

		$sampling_interval = db_fetch_cell_prepared('SELECT step
			FROM data_source_profiles
			WHERE id = ?',
			[grv('profile_id')]);

		$save['id']                      = form_input_validate(grv('id'), 'id', '^[0-9]+$', false, 3);
		$save['name']                    = form_input_validate(gnrv('name'), 'name', '', true, 3);
		$save['data_source_profile_id']  = form_input_validate(grv('profile_id'), 'profile_id', '^[0-9]+$', false, 3);
		$save['timespan']                = form_input_validate(gnrv('timespan'), 'timespan', '^[0-9]+$', false, 3);

		if (isrv('steps')) {
			$save['steps'] = form_input_validate(gnrv('steps'), 'steps', '^[0-9]+$', false, 3);

			if ($save['steps'] != '1') {
				$save['steps'] /= $sampling_interval;
			}
		}

		if (isrv('rows')) {
			$save['rows'] = form_input_validate(gnrv('rows'), 'rows', '^[0-9]+$', false, 3);
		}

		if (!is_error_message()) {
			$profile_rra_id = sql_save($save, 'data_source_profiles_rra');

			if ($profile_rra_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		} else {
			$profile_rra_id = 0;
		}

		if (is_error_message()) {
			header('Location: data_source_profiles.php?action=item_edit&profile_id=' . grv('profile_id') . '&id=' . (empty($profile_rra_id) ? grv('id') : $profile_rra_id));
		} else {
			header('Location: data_source_profiles.php?action=edit&id=' . grv('profile_id'));
		}
	}
}

function form_actions() : void {
	global $actions;

	// ================= input validation =================
	gfrv('drp_action', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-zA-Z0-9_]+)$/']]);
	// ====================================================

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (grv('drp_action') == '1') { // delete
				db_execute('DELETE FROM data_source_profiles WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM data_source_profiles_rra WHERE ' . array_to_sql_or($selected_items, 'data_source_profile_id'));
				db_execute('DELETE FROM data_source_profiles_cf WHERE ' . array_to_sql_or($selected_items, 'data_source_profile_id'));
			} elseif (grv('drp_action') == '2') { // duplicate
				duplicate_data_source_profile($selected_items, gnrv('title_format'));
			} elseif (grv('drp_action') == '3') { // export
				top_header();

				print '<script text="text/javascript">
					function DownloadStart(url) {
						document.getElementById("download_iframe").src = url;
						setTimeout(function() {
							loadUrl({ url: "data_source_profiles.php" });
							Pace.stop();
						}, 500);
					}

					$(function() {
						//debugger;
						DownloadStart(\'data_source_profiles.php?action=export&selected_items=' . gnrv('selected_items') . '\');
					});
				</script>
				<iframe id="download_iframe" style="display:none;"></iframe>';

				bottom_footer();

				exit;
			}
		}

		header('Location: data_source_profiles.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		// loop through each of the graphs selected on the previous page and get more info about them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM data_source_profiles WHERE id = ?', [$matches[1]])) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'data_source_profiles.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following Data Source Profile.'),
					'pmessage' => __('Click \'Continue\' to Delete following Data Source Profiles.'),
					'scont'    => __('Delete Data Source Profile'),
					'pcont'    => __('Delete Data Source Profiles')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Duplicate the following Data Source Profile.'),
					'pmessage' => __('Click \'Continue\' to Duplicate following Data Source Profiles.'),
					'scont'    => __('Duplicate Data Source Profile'),
					'pcont'    => __('Duplicate Data Source Profiles'),
					'extra'    => [
						'title_format' => [
							'method'  => 'textbox',
							'title'   => __('Title Format'),
							'default' => '<profile_title> (1)',
							'width'   => 25
						]
					]
				],
				3 => [
					'smessage' => __('Click \'Continue\' to Export the following Data Source Profile.'),
					'pmessage' => __('Click \'Continue\' to Export following Data Source Profiles.'),
					'scont'    => __('Export Data Source Profile'),
					'pcont'    => __('Export Data Source Profiles')
				],
			]
		];

		form_continue_confirmation($form_data);
	}
}

function duplicate_data_source_profile(mixed $source_profile, string $title_format) : void {
	if (!is_array($source_profile)) {
		$source_profile = [$source_profile];
	}

	foreach ($source_profile as $id) {
		$profile = db_fetch_row_prepared('SELECT *
			FROM data_source_profiles
			WHERE id = ?',
			[$id]);

		if (cacti_sizeof($profile)) {
			$save = [];

			$save['id']   = 0;

			foreach ($profile as $column => $value) {
				if ($column == 'id') {
					continue;
				}

				if ($column == 'hash') {
					$save['hash'] = get_hash_data_source_profile(0);
				} elseif ($column == 'name') {
					$save['name'] = str_replace('<profile_title>', $value, $title_format);
				} elseif ($column == 'default') {
					$save['default'] = '';
				} else {
					$save[$column] = $value;
				}
			}

			$newid = sql_save($save, 'data_source_profiles');

			if ($newid > 0) {
				db_execute_prepared("INSERT INTO data_source_profiles_cf
					SELECT '$newid' AS data_source_profile_id, consolidation_function_id
					FROM data_source_profiles_cf
					WHERE data_source_profile_id = ?",
					[$id]);

				db_execute_prepared("INSERT INTO data_source_profiles_rra
					(`data_source_profile_id`, `name`, `steps`, `rows`, `timespan`)
					SELECT '$newid', `name`, `steps`, `rows`, `timespan`
					FROM data_source_profiles_rra
					WHERE data_source_profile_id = ?",
					[$id]);

				raise_message(1);
			} else {
				raise_message(2);
			}
		} else {
			raise_message('profile_error', __('Unable to duplicate Data Source Profile.  Check Cacti Log for errors.'), MESSAGE_LEVEL_ERROR);
		}
	}
}

function profile_item_remove_confirm() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('profile_id');
	// ====================================================

	form_start('data_source_profiles.php');

	html_start_box('', '100%', false, 3, 'center', '');

	$profile = db_fetch_row_prepared('SELECT *
		FROM data_source_profiles_rra
		WHERE id = ?',
		[grv('id')]);

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following Data Source Profile RRA.'); ?></p>
			<p><?php print __esc('Profile Name: %s', $profile['name']); ?><br>
		</td>
	</tr>
	<tr>
		<td class='right'>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel'  onClick='$("#cdialog").dialog("close");' name='cancel'><?php print __esc('Cancel'); ?></button>
			<button type='button' class='ui-button ui-corner-all ui-widget' id='continue' title='<?php print __esc('Remove Data Source Profile RRA'); ?>'><?php print __esc('Continue'); ?></button>
			<input type='hidden' id='rra_profile_id' value='<?php print $profile['data_source_profile_id']; ?>'>
			<input type='hidden' id='rra_id' value='<?php print grv('id'); ?>'>
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
				url: 'data_source_profiles.php?action=item_remove',
				funcEnd: 'removeDataSourceProfilesItemFinalize'
			}

			var data = {
				__csrf_magic: csrfMagicToken,
				id: <?php print grv('id'); ?>
			}

			postUrl(options, data);
		});
	});

	function removeDataSourceProfilesItemFinalize(data) {
		$('#cdialog').dialog('close');
		loadUrl({url:'data_source_profiles.php?action=edit&id=<?php print $profile['data_source_profile_id']; ?>'})
	}

	</script>
	<?php
}

function profile_item_remove() : void {
	// ================= input validation =================
	gfrv('id');
	// ====================================================

	db_execute_prepared('DELETE FROM data_source_profiles_rra WHERE id = ?', [grv('id')]);
}

function item_edit() : void {
	global $fields_profile_rra_edit, $aggregation_levels;

	// ================= input validation =================
	gfrv('id');
	gfrv('profile_id');
	// ====================================================

	$sampling_interval = db_fetch_cell_prepared('SELECT step
		FROM data_source_profiles
		WHERE id = ?',
		[grv('profile_id')]);

	$readonly = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM data_template_data AS dtd
		WHERE data_source_profile_id = ?
		AND local_data_id > 0',
		[grv('profile_id')]);

	if (!ierv('id')) {
		$rra = db_fetch_row_prepared('SELECT *
			FROM data_source_profiles_rra
			WHERE id = ?',
			[grv('id')]);

		if ($rra['steps'] == '1') {
			$fields_profile_rra_edit['steps']['array'] = ['1' => __('Each Insert is New Row')];
		} else {
			foreach ($aggregation_levels as $interval => $name) {
				if ($interval <= $sampling_interval) {
					unset($aggregation_levels[$interval]);
				}
			}
			$fields_profile_rra_edit['steps']['array'] = $aggregation_levels;
		}

		$fields_profile_rra_edit['steps']['value'] = $rra['steps'] * $sampling_interval;
	} else {
		$oneguy = db_fetch_cell_prepared('SELECT id
			FROM data_source_profiles_rra
			WHERE data_source_profile_id = ?
			AND steps = 1',
			[grv('profile_id')]);

		if (empty($oneguy)) {
			$fields_profile_rra_edit['steps']['array'] = ['1' => __('Each Insert is New Row')];
		} else {
			$max = db_fetch_cell_prepared('SELECT MAX(steps) * ?
				FROM data_source_profiles_rra
				WHERE data_source_profile_id = ?',
				[$sampling_interval, grv('profile_id')]);

			foreach ($aggregation_levels as $interval => $name) {
				if ($interval <= $max) {
					unset($aggregation_levels[$interval]);
				}
			}

			$fields_profile_rra_edit['steps']['array'] = $aggregation_levels;
		}
	}

	form_start('data_source_profiles.php', 'form_rra');

	$name = db_fetch_cell_prepared('SELECT name
		FROM data_source_profiles_rra
		WHERE id = ?',
		[grv('id')]);

	html_start_box(__esc('RRA [edit: %s %s]', $name, ($readonly ? __('(Some Elements Read Only)') : '')), '100%', true, 3, 'center', '');

	draw_edit_form([
		'config' => ['no_form_tag' => true],
		'fields' => inject_form_variables($fields_profile_rra_edit, (isset($rra) ? $rra : []))
		]
	);

	html_end_box(true, true);

	form_hidden_box('profile_id', grv('profile_id'), '');

	form_save_button('data_source_profiles.php?action=edit&id=' . grv('profile_id'));

	?>
	<script type='text/javascript'>

	var profile_id=<?php print grv('profile_id') != '' ? grv('profile_id') : 0; ?>;
	var readonly = <?php print($readonly ? 'true' : 'false'); ?>;

	$(function() {
		get_span();
		get_size();

		$('#steps').change(function() {
			get_span();
			get_size();
		});

        $('#rows').delayKeyup(function() {
            get_span();
			get_size();
        });

		if (readonly) {
			$('#steps').prop('disabled', true);
			if ($('#steps').selectmenu('instance')) {
				$('#steps').selectmenu('disable');
			}

			$('#rows').prop('disabled', true);
			if ($('#rows').selectmenu('instance')) {
				$('#rows').selectmenu('disable');
			}
		}
	});

	function get_size() {
		$.get('data_source_profiles.php?action=ajax_size&type=rra&id='+profile_id+'&rows='+$('#rows').val())
			.done(function(data) {
				$('#row_size').find('.formColumnRight').empty().html('<em>'+data+'</em>');
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			});
	}

	function get_span() {
		$.get('data_source_profiles.php?action=ajax_span&profile_id='+profile_id+'&span='+$('#steps').val()+'&rows='+$('#rows').val())
			.done(function(data) {
				$('#row_retention').find('.formColumnRight').empty().html('<em>'+data+'</em>');
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			});

	}
	</script>
	<?php
}

function profile_edit() : void {
	global $fields_profile_edit, $timespans;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	if (!ierv('id')) {
		$profile = db_fetch_row_prepared('SELECT *
			FROM data_source_profiles
			WHERE id = ?',
			[grv('id')]);

		$readonly     = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM data_template_data AS dtd
			WHERE data_source_profile_id = ?
			AND local_data_id > 0',
			[grv('id')]);

		$header_label = __esc('Data Source Profile [edit: %s]', $profile['name'] . ($readonly ? ' (Read Only)' : ''));
	} else {
		$profile      = [];
		$readonly     = 0;
		$header_label = __('Data Source Profile [new]');
		$readonly     = false;
	}

	form_start('data_source_profiles.php', 'profile');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($fields_profile_edit, $profile)
		]
	);

	html_end_box(true, true);

	if (!ierv('id')) {
		if (!$readonly) {
			html_start_box(__('Data Source Profile RRAs (press save to update timespans)'), '100%', false, 3, 'center', 'data_source_profiles.php?action=item_edit&profile_id=' . $profile['id']);
		} else {
			html_start_box(__('Data Source Profile RRAs (Read Only)'), '100%', false, 3, 'center', '');
		}

		$display_text = [
			['display' => __('Name'),           'align' => 'left'],
			['display' => __('Data Retention'), 'align' => 'left'],
			['display' => __('Graph Timespan'), 'align' => 'left'],
			['display' => __('Steps'),          'align' => 'left'],
			['display' => __('Rows'),           'align' => 'left'],
		];

		html_header($display_text, 2);

		$profile_rras = db_fetch_assoc_prepared('SELECT *
			FROM data_source_profiles_rra
			WHERE data_source_profile_id = ?
			ORDER BY steps',
			[grv('id')]);

		$i = 0;

		if (cacti_sizeof($profile_rras)) {
			foreach ($profile_rras as $rra) {
				form_alternate_row('line' . $rra['id']);

				$url = 'data_source_profiles.php?action=item_edit&id=' . $rra['id'] . '&profile_id=' . $rra['data_source_profile_id'];

				form_selectable_cell(filter_value($rra['name'], '', $url), $i);

				form_selectable_cell('<em>' . get_span($profile['step'] * $rra['steps'] * $rra['rows']) . '</em>', $i);
				form_selectable_cell('<em>' . isset($timespans[$rra['timespan']]) ? $timespans[$rra['timespan']] : get_span($rra['timespan']) . '</em>', $i); // @phpstan-ignore-line
				form_selectable_cell('<em>' . $rra['steps'] . '</em>', $i);
				form_selectable_cell('<em>' . $rra['rows'] . '</em>', $i);

				if (!$readonly) {
					form_selectable_cell("<a id='" . $profile['id'] . '_' . $rra['id'] . "' class='delete deleteMarker ti ti-x' title='" . __esc('Delete') . "' href='#'></a>", $i, '', 'right');
				} else {
					form_selectable_cell('', $i, '', 'right');
				}

				form_end_row();

				$i++;
			}
		}

		html_end_box();
	}

	form_save_button('data_source_profiles.php', 'return');

	?>
	<script type='text/javascript'>

	var profile_id=<?php print grv('id') != '' ? grv('id') : 0; ?>;

	$(function() {
		$('.cdialog').remove();
		$('#main').append("<div class='cdialog' id='cdialog'></div>");

        $('#consolidation_function_id').multiselect({
            selectedList: 4,
            noneSelectedText: '<?php print __('Select Consolidation Function(s)'); ?>',
            header: false,
            groupColumns: true,
            groupColumnsWidth: 90,
            height: 28,
            menuWidth: 400,
			click: function(event, ui){
				get_size();
			}
        });

		get_size();
		$('consolidation_function_id').change(function() {
			get_size();
		});

		<?php if (!$readonly) {?>
		$('.delete').click(function (event) {
			event.preventDefault();

			id = $(this).attr('id').split('_');
			request = 'data_source_profiles.php?action=item_remove_confirm&id='+id[1]+'&profile_id='+id[0];
			$.get(request)
				.done(function(data) {
					$('#cdialog').html(data);

					applySkin();

					$('#continue').off('click').on('click', function(data) {
						$.post('data_source_profiles.php?action=item_remove', {
							__csrf_magic: csrfMagicToken,
							id: $('#rra_id').val()
						}).done(function(data) {
							$('#cdialog').dialog('close');
							loadUrl({url:'data_source_profiles.php?action=edit&id=' + $('#rra_profile_id').val()});
						});
					});

					$('#cdialog').dialog({
						title: '<?php print __('Delete Data Source Profile Item'); ?>',
						close: function () { $('.delete').blur(); $('.selectable').removeClass('selected'); },
						minHeight: 80,
						minWidth: 500
					});
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		}).css('cursor', 'pointer');
		<?php } else { ?>
		$('#step').prop('disabled', true);
		if ($('#step').selectmenu('instance')) {
			$('#step').selectmenu('disable')
		}

		$('#x_files_factor').prop('disabled', true);

		$('#consolidation_function_id').prop('disabled', true);
		if ($('#consolidation_function_id').multiselect('instance')) {
			$('#consolidation_function_id').multiselect('disable');
		}
		<?php } ?>
	});

	function get_size() {
		checked = $('#consolidation_function_id').multiselect('getChecked').length;
		$.get('data_source_profiles.php?action=ajax_size&type=profile&id='+profile_id+'&cfs='+checked)
			.done(function(data) {
				$('#row_size').find('.formColumnRight').empty().html('<em>'+data+'</em>');
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			});
	}

	</script>
	<?php
}

function get_size(int $id, string $type, string $cfs = '', int $rows = 1) : string {
	// On x86_64 platform, here is the equation
	// file_size = $header + (# data sources * 300) + (# cfs * #rows in all RRAs)
	$header   = 284;
	$dsheader = 300;
	$row      = 8;

	if ($type == 'profile') {
		if (empty($cfs)) {
			$cfs  = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM data_source_profiles_cf
				WHERE data_source_profile_id = ?',
				[$id]);
		}

		$rows = db_fetch_cell_prepared('SELECT SUM(`rows`)
			FROM data_source_profiles_rra
			WHERE data_source_profile_id = ?',
			[$id]);

		return __('%s KBytes per Data Sources and %s Bytes for the Header', number_format_i18n(($rows * $row * $cfs + $dsheader) / 1000), $header);
	}

	if ($rows > 0) {
		$cfs  = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM data_source_profiles_cf
			WHERE data_source_profile_id = ?',
			[$id]);

		return __('%s KBytes per Data Source', number_format_i18n(($rows * $row * $cfs) / 1000));
	} else {
		return __('Enter a valid number of Rows to obtain the RRA size.');
	}
}

function get_span(int $duration) : string {
	$years  = '';
	$months = '';
	$weeks  = '';
	$days   = '';
	$output = '';

	if ($duration > 31536000) {
		if (floor($duration / 31536000) > 0) {
			$years     = floor($duration / 31536000);
			$years	    = ($years == 1) ? __('1 Year') : __('%d Years', $years);
			$duration %= 31536000;
			$output    = $years;
		}
	}

	if ($duration > 2592000) {
		if (floor($duration / 2592000)) {
			$months    = floor($duration / 2592000);
			$months    = ($months == 1) ? __('%d Month', 1) : __('%d Months', $months);
			$duration %= 2592000;
			$output .= ($output != '' ? ', ' : '') . $months;
		}
	}

	if ($duration > 604800) {
		if (floor($duration / 604800) > 0) {
			$weeks     = floor($duration / 604800);
			$weeks     = ($weeks == 1) ? __('%d Week', 1) : __('%d Weeks', $weeks);
			$duration %= 604800;
			$output .= ($output != '' ? ', ' : '') . $weeks;
		}
	}

	if ($duration > 86400) {
		if (floor($duration / 86400) > 0) {
			$days      = floor($duration / 86400);
			$days      = ($days == 1) ? __('%d Day', 1) : __('%d Days', $days);
			$duration %= 86400;
			$output .= ($output != '' ? ', ' : '') . $days;
		}
	}

	if (floor($duration / 3600) > 0) {
		$hours   = floor($duration / 3600);
		$hours   = ($hours == 1) ? __('1 Hour') : __('%d Hours', $hours);
		$output .= ($output != '' ? ', ' : '') . $hours;
	}

	return $output;
}

function profile() : void {
	global $actions, $item_rows, $sampling_intervals, $heartbeats;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Data Source Profiles'), 'data_source_profiles.php', 'snmp_dsp', 'sess_dsp', 'data_source_profiles.php?action=edit');

	$pageFilter->rows_label = __('Profiles');
	$pageFilter->set_sort_array('step', 'ASC');
	$pageFilter->has_data   = true;
	$pageFilter->has_import = true;
	$pageFilter->render();

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = 'WHERE (dsp.name LIKE ' . db_qstr('%' . grv('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (grv('has_data') == 'true') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' (dsp.data_sources > 0)';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM data_source_profiles AS dsp
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$profile_list = db_fetch_assoc("SELECT *
		FROM data_source_profiles AS dsp
		$sql_where
		$sql_order
		$sql_limit");

	$display_text = [
		'name' => [
			'display' => __('Data Source Profile Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The name of this Data Source Profile.')
		],
		'nosort00' => [
			'display' => __('Default'),
			'align'   => 'right',
			'tip'     => __('Is this the default Profile for all new Data Templates?')
		],
		'nosort01' => [
			'display' => __('Deletable'),
			'align'   => 'right',
			'tip'     => __('Profiles that are in use cannot be Deleted. In use is defined as being referenced by a Data Source or a Data Template.')
		],
		'nosort02' => [
			'display' => __('Read Only'),
			'align'   => 'right',
			'tip'     => __('Profiles that are in use by Data Sources become read only for now.')
		],
		'step' => [
			'display' => __('Poller Interval'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The Polling Frequency for the Profile')
		],
		'heartbeat' => [
			'display' => __('Heartbeat'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The Amount of Time, in seconds, without good data before Data is stored as Unknown')
		],
		'data_sources' => [
			'display' => __('Data Sources Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Data Sources using this Profile.')
		],
		'templates' => [
			'display' => __('Templates Using'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Data Templates using this Profile.')
		]
	];

	$nav = html_nav_bar('data_source_profiles.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Profiles'), 'page', 'main');

	form_start('data_source_profiles.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($profile_list)) {
		foreach ($profile_list as $profile) {
			if ($profile['data_sources'] == 0 && $profile['templates'] == 0) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			if ($profile['data_sources']) {
				$readonly = true;
			} else {
				$readonly = false;
			}

			if ($profile['data_sources'] > 0) {
				$ds = '<a class="linkEditMain" href="' . CACTI_PATH_URL . 'data_sources.php?reset=true&profile=' . $profile['id'] . '">' . number_format_i18n($profile['data_sources'], -1) . '</a>';
			} else {
				$ds = number_format_i18n($profile['data_sources'], -1);
			}

			if ($profile['templates'] > 0) {
				$dt = '<a class="linkEditMain" href="' . CACTI_PATH_URL . 'data_templates.php?reset=true&profile=' . $profile['id'] . '">' . number_format_i18n($profile['templates'], -1) . '</a>';
			} else {
				$dt = number_format_i18n($profile['templates'], -1);
			}

			form_alternate_row('line' . $profile['id'], false, $disabled);

			form_selectable_cell(filter_value($profile['name'], grv('filter'), 'data_source_profiles.php?action=edit&id=' . $profile['id']), $profile['id']);
			form_selectable_cell($profile['default'] == 'on' ? __('Yes') : '', $profile['id'], '', 'right');
			form_selectable_cell($disabled ? __('No') : __('Yes'), $profile['id'], '', 'right');
			form_selectable_cell($readonly ? __('Yes') : __('No'), $profile['id'], '', 'right');
			form_selectable_cell($sampling_intervals[$profile['step']], $profile['id'], '', 'right');
			form_selectable_cell($heartbeats[$profile['heartbeat']], $profile['id'], '', 'right');
			form_selectable_cell($ds, $profile['id'], '', 'right');
			form_selectable_cell($dt, $profile['id'], '', 'right');

			form_checkbox_cell($profile['name'], $profile['id'], $disabled);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Data Source Profiles Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($profile_list)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
