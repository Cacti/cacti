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

//
global $attach_types, $alignment;

// get the format files
$formats = reports_get_format_files();

$fields_reports_edit = [
	'genhead' => [
		'friendly_name' => __('General Settings'),
		'method'        => 'spacer',
		'collapsible'   => 'true'
	],
	'name' => [
		'friendly_name' => __('Report Name'),
		'method'        => 'textbox',
		'default'       => __('New Report'),
		'description'   => __('Give this Report a descriptive Name'),
		'max_length'    => 99,
		'value'         => '|arg1:name|'
	],
	'enabled' => [
		'friendly_name' => __('Enable Report'),
		'method'        => 'checkbox',
		'default'       => '',
		'description'   => __('Check this box to enable this Report.'),
		'value'         => '|arg1:enabled|',
		'form_id'       => false
	],
	'formathead' => [
		'friendly_name' => __('Output Formatting'),
		'method'        => 'spacer',
		'collapsible'   => 'true'
	],
	'cformat' => [
		'friendly_name' => __('Use Custom Format HTML'),
		'method'        => 'checkbox',
		'default'       => '',
		'description'   => __('Check this box if you want to use custom html and CSS for the report.'),
		'value'         => '|arg1:cformat|',
		'form_id'       => false
	],
	'format_file' => [
		'friendly_name' => __('Format File to Use'),
		'method'        => 'drop_array',
		'default'       => 'default.format',
		'description'   => __('Choose the custom html wrapper and CSS file to use.  This file contains both html and CSS to wrap around your report.  If it contains more than simply CSS, you need to place a special <REPORT> tag inside of the file.  This format tag will be replaced by the report content.  These files are located in the \'formats\' directory.'),
		'value'         => '|arg1:format_file|',
		'array'         => $formats
	],
	'font_size' => [
		'friendly_name' => __('Default Text Font Size'),
		'description'   => __('Defines the default font size for all text in the report including the Report Title.'),
		'default'       => 16,
		'method'        => 'drop_array',
		'array'         => [7 => 7, 8 => 8, 10 => 10, 12 => 12, 14 => 14, 16 => 16, 18 => 18, 20 => 20, 24 => 24, 28 => 28, 32 => 32],
		'value'         => '|arg1:font_size|'
	],
	'alignment' => [
		'friendly_name' => __('Default Object Alignment'),
		'description'   => __('Defines the default Alignment for Text and Graphs.'),
		'default'       => 0,
		'method'        => 'drop_array',
		'array'         => $alignment,
		'value'         => '|arg1:alignment|'
	],
	'graph_linked' => [
		'friendly_name' => __('Graph Linked'),
		'method'        => 'checkbox',
		'default'       => '',
		'description'   => __('Should the Graphs be linked back to the Cacti site?'),
		'value'         => '|arg1:graph_linked|'
	],
	'graphhead' => [
		'friendly_name' => __('Graph Settings'),
		'method'        => 'spacer',
		'collapsible'   => 'true'
	],
	'graph_columns' => [
		'friendly_name' => __('Graph Columns'),
		'method'        => 'drop_array',
		'default'       => '1',
		'array'         => [1 => 1, 2, 3, 4, 5],
		'description'   => __('The number of Graph columns.'),
		'value'         => '|arg1:graph_columns|'
	],
	'graph_width' => [
		'friendly_name' => __('Graph Width'),
		'method'        => 'drop_array',
		'default'       => '300',
		'array'         => [100 => 100, 150 => 150, 200 => 200, 250 => 250, 300 => 300, 350 => 350, 400 => 400, 500 => 500, 600 => 600, 700 => 700, 800 => 800, 900 => 900, 1000 => 1000],
		'description'   => __('The Graph width in pixels.'),
		'value'         => '|arg1:graph_width|'
	],
	'graph_height' => [
		'friendly_name' => __('Graph Height'),
		'method'        => 'drop_array',
		'default'       => '125',
		'array'         => [75 => 75, 100 => 100, 125 => 125, 150 => 150, 175 => 175, 200 => 200, 250 => 250, 300 => 300],
		'description'   => __('The Graph height in pixels.'),
		'value'         => '|arg1:graph_height|'
	],
	'thumbnails' => [
		'friendly_name' => __('Thumbnails'),
		'method'        => 'checkbox',
		'default'       => '',
		'description'   => __('Should the Graphs be rendered as Thumbnails?'),
		'value'         => '|arg1:thumbnails|'
	]
];

$fields_reports_edit += api_scheduler_form();

$fields_reports_edit += [
	'emailhead' => [
		'friendly_name' => __('Email Sender/Receiver Details'),
		'method'        => 'spacer',
		'collapsible'   => 'true'
	],
	'subject' => [
		'friendly_name' => __('Subject'),
		'method'        => 'textbox',
		'default'       => __('Cacti Report'),
		'description'   => __('This value will be used as the default Email subject.  The report name will be used if left blank.'),
		'max_length'    => 255,
		'value'         => '|arg1:subject|'
	],
	'from_name' => [
		'friendly_name' => __('From Name'),
		'method'        => 'textbox',
		'default'       => read_config_option('settings_from_name'),
		'description'   => __('This Name will be used as the default E-mail Sender'),
		'max_length'    => 255,
		'value'         => '|arg1:from_name|'
	],
	'from_email' => [
		'friendly_name' => __('From Email Address'),
		'method'        => 'textbox',
		'default'       => read_config_option('settings_from_email'),
		'description'   => __('This Address will be used as the E-mail Senders address'),
		'max_length'    => 255,
		'value'         => '|arg1:from_email|'
	],
	'notify_list' => [
		'friendly_name' => __('Notification List', 'thold'),
		'method'        => 'drop_sql',
		'description'   => __('You may select a Notification List to receive this Report.'),
		'value'         => '|arg1:notify_list|',
		'none_value'    => __('None', 'thold'),
		'sql'           => 'SELECT id, name FROM plugin_notification_lists ORDER BY name'
	],
	'email' => [
		'friendly_name' => __('To Email Address(es)'),
		'method'        => 'textarea',
		'textarea_rows' => '5',
		'textarea_cols' => '60',
		'class'         => 'textAreaNotes',
		'default'       => '',
		'description'   => __('Please separate multiple addresses by comma (,)'),
		'max_length'    => 255,
		'value'         => '|arg1:email|'
	],
	'bcc' => [
		'friendly_name' => __('BCC Address(es)'),
		'method'        => 'textarea',
		'textarea_rows' => '5',
		'textarea_cols' => '60',
		'class'         => 'textAreaNotes',
		'default'       => '',
		'description'   => __('Blind carbon copy. Please separate multiple addresses by comma (,)'),
		'max_length'    => 255,
		'value'         => '|arg1:bcc|'
	],
	'attachment_type' => [
		'friendly_name' => __('Image Attachment Type'),
		'method'        => 'drop_array',
		'default'       => read_config_option('reports_default_image_format'),
		'description'   => __('Select one of the given Types for the Image Attachments'),
		'value'         => '|arg1:attachment_type|',
		'array'         => $attach_types
	],
];

if (!api_plugin_installed('thold')) {
	unset($fields_reports_edit['notify_alert']);
}

/**
 * Updates the sequence of report items based on the provided order.
 *
 * This function validates the input, retrieves the report items from the request,
 * and updates their sequence in the database.
 *
 * @return void
 */
function reports_item_dnd() : void {
	// ================= Input validation =================
	gfrv('id');
	// ================= Input validation =================

	$continue = true;

	if (isrv('report_item') && is_array(gnrv('report_item'))) {
		$report_items = gnrv('report_item');

		if (cacti_sizeof($report_items)) {
			$sequence = 1;

			foreach ($report_items as $item) {
				$item_id = str_replace('line', '', $item);
				input_validate_input_number($item_id, 'item_id');

				db_execute_prepared('UPDATE reports_items
                    SET sequence = ?
                    WHERE id = ?
					AND report_id = ?',
					[$sequence, $item_id, grv('id')]
				);

				$sequence++;
			}
		}
	}
}

/**
 * Save the report form data.
 *
 * This function handles the saving of report form data, including validation and database operations.
 * It processes both the main report and individual report items.
 *
 * @return void
 */
function reports_form_save() : void {
	global $messages;

	if (isrv('save_component_report')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('font_size');
		gfrv('graph_width');
		gfrv('graph_height');
		gfrv('graph_columns');
		// ====================================================

		$post = $_POST;

		if (ierv('id')) {
			$save['user_id'] = $_SESSION[SESS_USER_ID];
		} else {
			$save['user_id'] = db_fetch_cell_prepared('SELECT user_id FROM reports WHERE id = ?', [$post['id']]);
		}

		$save['id']            = $post['id'];
		$save['name']          = form_input_validate($post['name'], 'name', '', false, 3);
		$save['email']         = form_input_validate($post['email'], 'email', '', false, 3);
		$save['enabled']       = (isset($post['enabled']) ? 'on' : '');

		$save['cformat']       = (isset($post['cformat']) ? 'on' : '');
		$save['format_file']   = $post['format_file'];
		$save['font_size']     = form_input_validate($post['font_size'], 'font_size', '^[0-9]+$', false, 3);
		$save['alignment']     = form_input_validate($post['alignment'], 'alignment', '^[0-9]+$', false, 3);
		$save['graph_linked']  = (isset($post['graph_linked']) ? 'on' : '');

		$save['graph_columns'] = form_input_validate($post['graph_columns'], 'graph_columns', '^[0-9]+$', false, 3);
		$save['graph_width']   = form_input_validate($post['graph_width'], 'graph_width', '^[0-9]+$', false, 3);
		$save['graph_height']  = form_input_validate($post['graph_height'], 'graph_height', '^[0-9]+$', false, 3);
		$save['thumbnails']    = (isset($post['thumbnails']) ? 'on' : '');

		$save = api_scheduler_augment_save($save, $post);

		if ($post['subject'] != '') {
			$save['subject'] = $post['subject'];
		} else {
			$save['subject'] = $save['name'];
		}

		$save['from_name']   = $post['from_name'];
		$save['from_email']  = $post['from_email'];
		$save['bcc']         = $post['bcc'];
		$save['notify_list'] = ($post['notify_list'] ?? '');

		$atype = $post['attachment_type'];

		if (($atype != REPORTS_TYPE_INLINE_PNG) &&
			($atype != REPORTS_TYPE_INLINE_JPG) &&
			($atype != REPORTS_TYPE_INLINE_GIF) &&
			($atype != REPORTS_TYPE_ATTACH_PNG) &&
			($atype != REPORTS_TYPE_ATTACH_JPG) &&
			($atype != REPORTS_TYPE_ATTACH_GIF)) {
			$atype = REPORTS_TYPE_INLINE_PNG;
		}

		$save['attachment_type']  = form_input_validate($atype, 'attachment_type', '^[0-9]+$', false, 3);

		if (!is_error_message()) {
			$id = sql_save($save, 'reports');

			if ($id) {
				raise_message('reports_save');
			} else {
				raise_message('reports_save_failed');
			}
		}

		header('Location: ' . get_reports_page() . '?action=edit&id=' . (empty($id) ? $post['id'] : $id));

		exit;
	}

	if (isrv('save_component_report_item')) {
		// ================= input validation =================
		gfrv('report_id');
		gfrv('id');
		// ====================================================

		unset($_SESSION[SESS_ERROR_FIELDS]);

		$save = [];

		$save['id']        = gnrv('id');
		$save['report_id'] = form_input_validate(gnrv('report_id'), 'report_id', '^[0-9]+$', false, 3);

		if (ierv('id')) {
			$save['sequence'] = db_fetch_cell_prepared('SELECT MAX(sequence)+1
				FROM reports_items
				WHERE report_id = ?',
				[grv('report_id')]
			);
		} else {
			$save['sequence'] = form_input_validate(gnrv('sequence'), 'sequence', '^[0-9]+$', false, 3);
		}

		$save['item_type']         = form_input_validate(gnrv('item_type'), 'item_type', '^[-0-9]+$', false, 3);
		$save['tree_id']           = (isrv('tree_id') ? form_input_validate(gnrv('tree_id'), 'tree_id', '^[-0-9]+$', true, 3) : 0);
		$save['branch_id']         = (isrv('branch_id') ? form_input_validate(gnrv('branch_id'), 'branch_id', '^[-0-9]+$', true, 3) : 0);
		$save['tree_cascade']      = (isrv('tree_cascade') ? 'on' : '');
		$save['graph_name_regexp'] = form_input_validate(gnrv('graph_name_regexp'), 'graph_name_regexp', '', true, 3);

		if ($save['graph_name_regexp'] != '') {
			$regex_valid = validate_is_regex($save['graph_name_regexp']);

			if ($regex_valid !== true) {
				$_SESSION[SESS_ERROR_FIELDS]['graph_name_regexp'] = 3;
				raise_message('custom', __('The regular expression "%s" is not valid. Error is %s', htmle($save['graph_name_regexp']), htmle($regex_valid)), MESSAGE_LEVEL_ERROR);
			}
		}
		$save['site_id']           = (isrv('site_id') ? form_input_validate(gnrv('site_id'), 'site_id', '^[-0-9]+$', true, 3) : 0);
		$save['host_template_id']  = (isrv('host_template_id') ? form_input_validate(gnrv('host_template_id'), 'host_template_id', '^[-0-9]+$', true, 3) : 0);
		$save['host_id']           = (isrv('host_id') ? form_input_validate(gnrv('host_id'), 'host_id', '^[-0-9]+$', true, 3) : 0);
		$save['graph_template_id'] = (isrv('graph_template_id') ? form_input_validate(gnrv('graph_template_id'), 'graph_template_id', '^[-0-9]+$', true, 3) : 0);
		$save['local_graph_id']    = (isrv('local_graph_id') ? form_input_validate(gnrv('local_graph_id'), 'local_graph_id', '^[0-9]+$', true, 3) : 0);
		$save['timespan']          = (isrv('timespan') ? form_input_validate(gnrv('timespan'), 'timespan', '^[0-9]+$', true, 3) : 0);
		$save['item_text']         = (isrv('item_text') ? form_input_validate(gnrv('item_text'), 'item_text', '', true, 3) : '');
		$save['align']             = (isrv('align') ? form_input_validate(gnrv('align'), 'align', '^[0-9]+$', true, 3) : REPORTS_ALIGN_LEFT);
		$save['font_size']         = (isrv('font_size') ? form_input_validate(gnrv('font_size'), 'font_size', '^[0-9]+$', true, 3) : REPORTS_FONT_SIZE);

		if (!is_error_message()) {
			$item_id = sql_save($save, 'reports_items');

			reports_item_resequence($save['report_id']);

			if ($item_id) {
				raise_message('reports_item_save');
			} else {
				raise_message('reports_item_save_failed');
			}
		}

		header('Location: ' . get_reports_page() . '?action=item_edit&id=' . gnrv('report_id') . '&item_id=' . (empty($item_id) ? gnrv('id') : $item_id));
	} else {
		header('Location: ' . get_reports_page());
	}

	exit;
}

/**
 * Handles form actions for reports, including delete, take ownership, duplicate, enable, disable, and send now.
 *
 * This function processes the form actions based on the selected items and the action chosen by the user.
 * It performs various operations such as deleting reports, taking ownership, duplicating reports, enabling/disabling reports, and sending reports immediately.
 *
 * @return void
 */
function reports_form_actions() : void {
	global $reports_actions;

	// ================= input validation =================
	gfrv('drp_action');
	// ====================================================

	$current_user_id = (int) $_SESSION[SESS_USER_ID];
	$reports_admin   = is_reports_admin();
	$reportit_exists = db_table_exists('plugin_reportit_reports');

	$can_manage_report = static function (string $type, int $report_id) use ($current_user_id, $reports_admin, $reportit_exists) : bool {
		if ($reports_admin) {
			return true;
		}

		if ($type === 'reports') {
			$owner_id = db_fetch_cell_prepared('SELECT user_id
				FROM reports
				WHERE id = ?',
				[$report_id]
			);

			return ((int) $owner_id === $current_user_id);
		}

		if ($type === 'reportit' && $reportit_exists) {
			$owner_id = db_fetch_cell_prepared('SELECT user_id
				FROM plugin_reportit_reports
				WHERE id = ?',
				[$report_id]
			);

			return ((int) $owner_id === $current_user_id);
		}

		return false;
	};

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = reports_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			foreach ($selected_items as $report) {
				if (!is_string($report) || strpos($report, '_') === false) {
					continue;
				}

				[$type, $report_id] = explode('_', $report, 2);

				if (!in_array($type, ['reports', 'reportit'], true)) {
					continue;
				}

				if (!is_numeric($report_id) || (int) $report_id <= 0) {
					continue;
				}

				if (!$can_manage_report($type, (int) $report_id)) {
					continue;
				}

				if (gnrv('drp_action') == REPORTS_DELETE) { // delete
					if ($type == 'reports') {
						db_execute_prepared('DELETE FROM reports WHERE id = ?', [$report_id]);
						db_execute_prepared('DELETE FROM reports_items WHERE report_id = ?', [$report_id]);
					} elseif ($type == 'reportit') {
						if (function_exists('api_reportit_delete_report')) {
							api_reportit_delete_report($report_id);
						}
					}
				} elseif (gnrv('drp_action') == REPORTS_OWN) { // take ownership
					if ($type == 'reports') {
						db_execute_prepared('UPDATE reports
							SET user_id = ?
							WHERE id = ?',
							[$_SESSION[SESS_USER_ID], $report_id]
						);
					} elseif ($type == 'reportit') {
						if (function_exists('api_reportit_take_ownership')) {
							api_reportit_take_ownership($report_id, $_SESSION[SESS_USER_ID]);
						}
					}
				} elseif (gnrv('drp_action') == REPORTS_DUPLICATE) { // duplicate
					if ($type == 'reports') {
						duplicate_reports((int) $report_id, gnrv('name_format'));
					} elseif ($type == 'reportit') {
						if (function_exists('api_reportit_duplicate_report')) {
							api_reportit_duplicate_report($report_id);
						}
					}
				} elseif (gnrv('drp_action') == REPORTS_ENABLE) { // enable
					if ($type == 'reports') {
						db_execute_prepared('UPDATE reports
							SET enabled = "on"
							WHERE id = ?',
							[$report_id]
						);
					} elseif ($type == 'reportit') {
						if (function_exists('api_reportit_enable_report')) {
							api_reportit_enable_report($report_id);
						}
					}
				} elseif (gnrv('drp_action') == REPORTS_DISABLE) { // disable
					if ($type == 'reports') {
						db_execute_prepared('UPDATE reports
							SET enabled=""
							WHERE id = ?',
							[$report_id]
						);
					} elseif ($type == 'reportit') {
						if (function_exists('api_reportit_disable_report')) {
							api_reportit_disable_report($report_id);
						}
					}
				} elseif (gnrv('drp_action') == REPORTS_SEND_NOW) { // send now
					if ($type == 'reports') {
						reports_send((int) $report_id);
					} elseif ($type == 'reportit') {
						if (function_exists('api_reportit_run_report')) {
							api_reportit_run_report($report_id);
						}
					}
				}
			}
		}

		force_session_data();

		header('Location: ' . get_reports_page());

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		// loop through each of the graphs selected on the previous page and get more info about them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([a-z_0-9]+)$/', $var, $matches)) {
				[$type, $id] = explode('_', $matches[1], 2);

				// Parse and validate identically to the action branch so the
				// confirmed item list cannot diverge from what is acted on.
				if (!in_array($type, ['reports', 'reportit'], true)) {
					continue;
				}

				if (!is_numeric($id) || (int) $id <= 0) {
					continue;
				}

				if (!$can_manage_report($type, (int) $id)) {
					continue;
				}

				if ($type == 'reports') {
					$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM reports WHERE id = ?', [$id])) . '</li>';
				} elseif ($type == 'reportit') {
					$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM plugin_reportit_reports WHERE id = ?', [$id])) . '</li>';
				}

				$iarray[] = "{$type}_{$id}";
			}
		}

		$form_data = [
			'general' => [
				'page'       => get_current_page(),
				'actions'    => $reports_actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				REPORTS_DELETE => [
					'smessage' => __('Click \'Continue\' to Delete the following Report.'),
					'pmessage' => __('Click \'Continue\' to Delete the following Reports.'),
					'scont'    => __('Delete Report'),
					'pcont'    => __('Delete Reports')
				],
				REPORTS_OWN => [
					'smessage' => __('Click \'Continue\' to take ownership of the following Report.'),
					'pmessage' => __('Click \'Continue\' to take ownership of the following Reports.'),
					'scont'    => __('Take Report Ownership'),
					'pcont'    => __('Take Reports Ownership')
				],
				REPORTS_DUPLICATE => [
					'smessage' => __('Click \'Continue\' to Duplicate the following Report.'),
					'pmessage' => __('Click \'Continue\' to Duplicate the following Reports.'),
					'scont'    => __('Duplicate Report'),
					'pcont'    => __('Duplicate Reports'),
					'extra'    => [
						'name_format' => [
							'method'  => 'textbox',
							'title'   => __('Name Format:'),
							'default' => '<name> (1)',
							'width'   => 25
						]
					]
				],
				REPORTS_ENABLE => [
					'smessage' => __('Click \'Continue\' to Enable the following Report.'),
					'pmessage' => __('Click \'Continue\' to Enable the following Reports.'),
					'scont'    => __('Enable Report'),
					'pcont'    => __('Enable Reports')
				],
				REPORTS_DISABLE => [
					'smessage' => __('Click \'Continue\' to Disable the following Report.'),
					'pmessage' => __('Click \'Continue\' to Disable the following Reports.'),
					'scont'    => __('Disable Report'),
					'pcont'    => __('Disable Reports')
				],
				REPORTS_SEND_NOW => [
					'smessage' => __('Click \'Continue\' to Send the following Report now.'),
					'pmessage' => __('Click \'Continue\' to Send the following Reports now.'),
					'scont'    => __('Send Report Now'),
					'pcont'    => __('Send Reports Now')
				],
			]
		];

		form_continue_confirmation($form_data);
	}
}

/**
 * Sends a report via email based on the provided report ID.
 *
 * This function validates the input report ID, fetches the report details from the database,
 * and sends the report via email if all necessary fields are set. If any required field is missing,
 * an appropriate error message is raised.
 *
 * @param int $id The ID of the report to be sent.
 *
 * @return void
 */
function reports_send(int $id) : void {
	// ================= input validation =================
	input_validate_input_number($id, 'id');
	// ====================================================

	$report = db_fetch_row_prepared('SELECT *
		FROM reports
		WHERE id = ?',
		[$id]);

	if (!cacti_sizeof($report)) {
		// set error condition
	} elseif ($report['user_id'] == $_SESSION[SESS_USER_ID]) {
		reports_log(__FUNCTION__ . ', send now, report_id: ' . $id, false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);

		// use report name as default EMail title
		if ($report['subject'] == '') {
			$report['subject'] = $report['name'];
		}

		if ($report['email'] == '') {
			raise_message('report_message', __esc('Unable to send Report \'%s\'.  Please set destination e-mail addresses',  $report['name']), MESSAGE_LEVEL_ERROR);
		} elseif ($report['subject'] == '') {
			raise_message('report_message', __esc('Unable to send Report \'%s\'.  Please set an e-mail subject',  $report['name']), MESSAGE_LEVEL_ERROR);
		} elseif ($report['from_name'] == '') {
			raise_message('report_message', __esc('Unable to send Report \'%s\'.  Please set an e-mail From Name',  $report['name']), MESSAGE_LEVEL_ERROR);
		} elseif ($report['from_email'] == '') {
			raise_message('report_message', __esc('Unable to send Report \'%s\'.  Please set an e-mail from address',  $report['name']), MESSAGE_LEVEL_ERROR);
		} else {
			generate_report(0, $report, true);
		}
	}
}

/**
 * Moves a report item down in the order.
 *
 * This function validates the input parameters and then calls the move_item_down function
 * to move the specified report item down in the order within the report.
 *
 * @return void
 */
function reports_item_movedown() : void {
	// ================= input validation =================
	gfrv('item_id');
	gfrv('id');
	// ====================================================

	move_item_down('reports_items', grv('item_id'), 'report_id=' . grv('id'));
}

/**
 * Moves a report item up in the order.
 *
 * This function handles the movement of a report item up in the order within the reports_items table.
 * It retrieves the item_id and id from the request variables, and then calls the move_item_up function
 * to perform the actual movement.
 *
 * @return void
 */
function reports_item_moveup() : void {
	// ================= input validation =================
	gfrv('item_id');
	gfrv('id');
	// ====================================================
	move_item_up('reports_items', grv('item_id'), 'report_id=' . grv('id'));
}

/**
 * Removes a report item from the database.
 *
 * This function deletes a report item from the `reports_items` table based on the provided `item_id`.
 * The `item_id` is retrieved from the request variables.
 *
 * @return void
 */
function reports_item_remove() : void {
	// ================= input validation =================
	gfrv('item_id');
	// ====================================================
	db_execute_prepared('DELETE FROM reports_items WHERE id = ?', [grv('item_id')]);
}

/**
 * Resequence the items of a report based on their current sequence.
 *
 * This function fetches all items of a given report, ordered by their current sequence,
 * and then updates each item's sequence to ensure they are sequentially numbered starting from 1.
 *
 * @param int $report_id The ID of the report whose items need to be resequenced.
 *
 * @return void
 */
function reports_item_resequence(int $report_id) : void {
	$items = db_fetch_assoc_prepared('SELECT *
		FROM reports_items
		WHERE report_id = ?
		ORDER BY sequence',
		[$report_id]
	);

	if (cacti_sizeof($items)) {
		$sequence = 1;

		foreach ($items as $i) {
			db_execute_prepared('UPDATE reports_items
				SET sequence = ?
				WHERE id = ?',
				[$sequence, $i['id']]
			);

			$sequence++;
		}
	}
}

/**
 * Validates and stores report item request variables in the session.
 *
 * This function performs input validation and session storage for various report item request variables.
 * It checks if the request variables have changed and validates them against the database.
 * If any validation fails, it resets the corresponding request variables.
 *
 * @return string JSON encoded array of reset request variables.
 */
function reports_item_validate() : string {
	// ================= input validation and session storage =================
	$filters = [
		'tree_id' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '0'
		],
		'branch_id' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '0'
		],
		'site_id' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '0'
		],
		'host_id' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '0'
		],
		'host_template_id' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '0'
		],
		'graph_template_id' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '0'
		],
		'local_graph_id' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '0'
		]
	];

	$changed = [];

	foreach ($filters as $item => $filter) {
		if (isset($_SESSION['sess_report_item_' . $item])) {
			if (isrv($item)) {
				if ($_SESSION['sess_report_item_' . $item] != gnrv($item)) {
					$changed[$item] = true;
				}
			}
		}
	}

	validate_store_request_vars($filters, 'sess_report_item');
	// ================= input validation =================

	$reset = [];

	if (cacti_sizeof($changed)) {
		foreach ($changed as $id => $value) {
			switch ($id) {
				case 'host_id':
					if (grv('host_id') != '-1') {
						if (grv('local_graph_id') > 0) {
							$valid = db_fetch_cell_prepared('SELECT id
								FROM graph_local
								WHERE host_id = ?
								AND id = ?',
								[grv('host_id'), grv('local_graph_id')]
							);

							if (empty($valid)) {
								$reset['local_graph_id'] = true;
							}
						}

						if (grv('host_template_id') > 0) {
							$valid = db_fetch_cell_prepared('SELECT id
								FROM host
								WHERE host_template_id = ?
								AND id = ?',
								[grv('host_template_id'), grv('host_id')]
							);

							if (empty($valid)) {
								$reset['host_template_id'] = true;
							}
						}

						if (grv('graph_template_id') > 0) {
							$valid = db_fetch_cell_prepared('SELECT id
								FROM graph_local
								WHERE graph_template_id = ?
								AND id = ?',
								[grv('graph_template_id'), grv('host_id')]
							);

							if (empty($valid)) {
								$reset['graph_template_id'] = true;
							}
						}

						if (grv('site_id') > 0) {
							$valid = db_fetch_cell_prepared('SELECT id
								FROM host
								WHERE site_id = ?
								AND id = ?',
								[grv('site_id'), grv('host_id')]
							);

							if (empty($valid)) {
								$reset['site_id'] = true;
							}
						}
					}

					break;
				case 'site_id':
					if (grv('site_id') != '-1') {
						if (grv('host_id') > 0) {
							$valid = db_fetch_cell_prepared('SELECT id
								FROM host
								WHERE site_id = ?
								AND id = ?',
								[grv('site_id'), grv('host_id')]
							);

							if (empty($valid)) {
								$reset['host_id'] = true;
							}
						}

						if (grv('local_graph_id') > 0) {
							$valid = db_fetch_cell_prepared('SELECT gl.id
								FROM graph_local AS gl
								INNER JOIN host AS h
								ON gl.host_id = h.id
								WHERE site_id = ?
								AND gl.id = ?',
								[grv('site_id'), grv('local_graph_id')]
							);

							if (empty($valid)) {
								$reset['local_graph_id'] = true;
							}
						}
					}

					break;
				case 'host_template_id':
					if (grv('host_template_id') != '-1') {
						if (grv('local_graph_id') > 0) {
							$valid = db_fetch_cell_prepared('SELECT gl.id
								FROM graph_local AS gl
								INNER JOIN host AS h
								ON gl.host_id = h.id
								WHERE host_template_id = ?
								AND gl.id = ?',
								[grv('host_template_id'), grv('local_graph_id')]
							);

							if (empty($valid)) {
								$reset['local_graph_id'] = true;
							}
						}

						if (grv('host_id') > 0) {
							$valid = db_fetch_cell_prepared('SELECT id
								FROM host
								WHERE host_template_id = ?
								AND id = ?',
								[grv('host_template_id'), grv('host_id')]
							);

							if (empty($valid)) {
								$reset['host_id'] = true;
							}
						}
					}

					break;
				case 'graph_template_id':
					if (grv('graph_template_id') != '-1') {
						if (grv('local_graph_id') > 0) {
							$valid = db_fetch_cell_prepared('SELECT id
								FROM graph_local
								WHERE graph_template_id = ?
								AND id = ?',
								[grv('graph_template_id'), grv('local_graph_id')]
							);

							if (empty($valid)) {
								$reset['local_graph_id'] = true;
							}
						}
					}

					break;
			}
		}
	}

	return json_encode($reset);
}

/**
 * Edit a report item.
 *
 * This function handles the editing of a report item, including fetching existing data,
 * initializing form fields, and rendering the form for editing.
 *
 * @return void
 */
function reports_item_edit() : void {
	global $item_types, $graph_timespans, $alignment;

	$trees    = [];
	$branches = [];

	$graph_template_description = '';
	$host_description           = '';

	$report_item                      = [];
	$report_item['item_type']         = REPORTS_ITEM_GRAPH;
	$report_item['site_id']           = -1;
	$report_item['host_template_id']  = -1;
	$report_item['graph_template_id'] = -1;
	$report_item['host_id']           = -1;
	$report_item['tree_id']           = -1;

	if (isrv('item_id') && gfrv('item_id') > 0) {
		$report_item = db_fetch_row_prepared('SELECT *
			FROM reports_items WHERE id = ?',
			[grv('item_id')]
		);
	} else {
		$report_item['report_id']      = grv('id');
		$report_item['local_graph_id'] = 0;

		unset($_SESSION['sess_report_item_host_id']);
		unset($_SESSION['sess_report_item_host_template_id']);
		unset($_SESSION['sess_report_item_site_id']);
		unset($_SESSION['sess_report_item_graph_template_id']);
		unset($_SESSION['sess_report_item_local_graph_id']);
	}

	// Initialize for AutoComplete Values from existing Report Item
	$checks = ['graph_template_id', 'host_id'];

	foreach ($checks as $check) {
		if ($report_item[$check] == '-1') {
			switch ($check) {
				case 'graph_template_id':
					$graph_template_description = __('Any');

					break;
				case 'host_id':
					$host_description = __('Any');

					break;
			}
		} elseif ($report_item[$check] == '0') {
			switch ($check) {
				case 'graph_template_id':
					$graph_template_description = __('None');

					break;
				case 'host_id':
					$host_description = __('None');

					break;
			}
		} else {
			switch ($check) {
				case 'graph_template_id':
					$graph_template_description = db_fetch_cell_prepared('SELECT name
						FROM graph_templates
						WHERE id = ?',
						[$report_item[$check]]
					);

					break;
				case 'host_id':
					$host_description = db_fetch_cell_prepared('SELECT description
						FROM host
						WHERE id = ?',
						[$report_item[$check]]
					);

					break;
			}
		}
	}

	if ($report_item['local_graph_id'] > 0) {
		$title_cache = db_fetch_cell_prepared('SELECT title_cache
			FROM graph_templates_graph
			WHERE local_graph_id = ?',
			[$report_item['local_graph_id']]
		);
	} else {
		$title_cache = __('None');
	}

	// Get Tree Information
	$trees = array_rekey(
		get_allowed_trees(),
		'id',
		'name'
	);

	// Get Branch Information
	// If the tree_id is not set, set the first tree and branch
	$sql_where = '';

	if ($report_item['tree_id'] > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'gt.id=' . $report_item['tree_id'];
	} elseif (cacti_sizeof($trees)) {
		foreach ($trees as $id => $name) {
			$report_item['tree_id'] = $id;
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'gt.id=' . $report_item['tree_id'];

			break;
		}
	}

	if ($report_item['tree_id'] > 0) {
		$branches = array_rekey(
			get_allowed_branches($sql_where),
			'id',
			'name'
		);
	} else {
		$branches = [];
	}

	// Prepare the form with all data
	$fields_reports_item_edit = [
		'item_type' => [
			'friendly_name' => __('Type'),
			'method'        => 'drop_array',
			'default'       => REPORTS_ITEM_GRAPH,
			'description'   => __('Item Type to be added.'),
			'value'         => '|arg1:item_type|',
			'on_change'     => 'toggle_item_type()',
			'array'         => $item_types
		],
		'tree_id' => [
			'friendly_name' => __('Graph Tree'),
			'method'        => 'drop_array',
			'default'       => REPORTS_TREE_NONE,
			'description'   => __('Select a Tree to use.'),
			'value'         => '|arg1:tree_id|',
			'on_change'     => 'changeTree()',
			'array'         => $trees
		],
		'branch_id' => [
			'friendly_name' => __('Graph Tree Branch'),
			'method'        => 'drop_array',
			'default'       => REPORTS_TREE_NONE,
			'none_value'    => __('All'),
			'description'   => __('Select a Tree Branch to use for Graphs and Devices.  Devices will be considered as Branches.'),
			'value'         => '|arg1:branch_id|',
			'array'         => $branches
		],
		'tree_cascade' => [
			'friendly_name' => __('Cascade to Branches'),
			'method'        => 'checkbox',
			'default'       => '',
			'description'   => __('Should all Branch Graphs be rendered?'),
			'value'         => '|arg1:tree_cascade|'
		],
		'site_id' => [
			'friendly_name' => __('Site'),
			'method'        => 'drop_sql',
			'default'       => 0,
			'description'   => __('Select a Site to filter for Devices and Graphs.'),
			'value'         => '|arg1:site_id|',
			'on_change'     => 'changeSite()',
			'sql'           => 'SELECT -1 AS id, "' . __('Any') . '" AS name UNION SELECT 0 AS id, "' . __('None') . '" AS name UNION (SELECT id, name FROM sites ORDER BY name)'
		],
		'host_template_id' => [
			'friendly_name' => __('Device Template'),
			'method'        => 'drop_sql',
			'default'       => REPORTS_HOST_NONE,
			'none_value'    => __('None'),
			'description'   => __('Select a Device Template to use to filter for Devices or Graphs.'),
			'value'         => '|arg1:host_template_id|',
			'on_change'     => 'changeDeviceTemplate()',
			'sql'           => "SELECT -1 AS id, '" . __('Any') . "' AS name UNION SELECT 0 AS id, '" . __('None') . "' AS name UNION (
				SELECT DISTINCT ht.id, ht.name
				FROM host_template AS ht
				INNER JOIN host AS h
				ON h.host_template_id=ht.id
				LEFT JOIN sites AS s
				ON h.site_id = s.id
				WHERE h.deleted = ''
				AND IFNULL(TRIM(s.disabled),'') != 'on'
				AND IFNULL(TRIM(h.disabled),'') != 'on'
				ORDER BY name)",
		],
		'host_id' => [
			'friendly_name' => __('Device'),
			'method'        => 'drop_callback',
			'description'   => __('Select a Device to be used to filter for of select for Graphs in the case of a Device Type.'),
			'sql'           => 'SELECT id, description as name FROM host ORDER BY name',
			'action'        => 'ajax_hosts',
			'none_value'    => __('None'),
			'on_change'     => 'changeDevice()',
			'id'            => $report_item['host_id'],
			'value'         => $host_description
		],
		'graph_template_id' => [
			'friendly_name' => __('Graph Template'),
			'method'        => 'drop_callback',
			'description'   => __('Select a Graph Template for the Device to be used to filter for or select Graphs in the case of a Device Type.'),
			'sql'           => 'SELECT id, name FROM graph_templates ORDER BY name',
			'action'        => 'ajax_graph_template',
			'none_value'    => __('None'),
			'on_change'     => 'changeGraphTemplate()',
			'id'            => $report_item['graph_template_id'],
			'value'         => $graph_template_description
		],
		'graph_name_regexp' => [
			'friendly_name' => __('Graph Name Regular Expression'),
			'method'        => 'textbox',
			'default'       => '',
			'description'   => __('A Perl compatible regular expression (REGEXP) used to select Graphs to include from the Tree or Device.'),
			'max_length'    => 255,
			'size'          => 80,
			'value'         => '|arg1:graph_name_regexp|'
		],
		'local_graph_id' => [
			'friendly_name' => __('Graph Name'),
			'method'        => 'drop_callback',
			'description'   => __('The Graph to use for this report item.'),
			'sql'           => 'SELECT local_graph_id AS id, title_cache AS name FROM graph_templates_graph WHERE local_graph_id > 0 ORDER BY title_cache',
			'action'        => 'ajax_graphs',
			'none_value'    => __('None'),
			'on_change'     => 'changeGraph()',
			'id'            => $report_item['local_graph_id'],
			'value'         => $title_cache
		],
		'timespan' => [
			'friendly_name' => __('Graph Timespan'),
			'method'        => 'drop_array',
			'default'       => GT_LAST_DAY,
			'description'   => __('The Graph End time will be set to the scheduled report send time.  So, if you wish the end time on the various Graphs to be midnight, ensure you send the report at midnight.  The Graph Start time will be the End Time minus the Graph Timespan.'),
			'array'         => $graph_timespans,
			'value'         => '|arg1:timespan|'
		],
		'align' => [
			'friendly_name' => __('Alignment'),
			'method'        => 'drop_array',
			'default'       => REPORTS_ALIGN_LEFT,
			'description'   => __('Alignment of the Item'),
			'value'         => '|arg1:align|',
			'array'         => $alignment
		],
		'item_text' => [
			'friendly_name' => __('Fixed Text'),
			'method'        => 'textbox',
			'default'       => '',
			'description'   => __('Enter descriptive Text'),
			'max_length'    => 255,
			'value'         => '|arg1:item_text|'
		],
		'font_size' => [
			'friendly_name' => __('Font Size'),
			'method'        => 'drop_array',
			'default'       => REPORTS_FONT_SIZE,
			'array'         => [7 => 7, 8 => 8, 10 => 10, 12 => 12, 14 => 14, 16 => 16, 18 => 18, 20 => 20, 24 => 24, 28 => 28, 32 => 32],
			'description'   => __('Font Size of the Item'),
			'value'         => '|arg1:font_size|'
		],
		'sequence' => [
			'method'        => 'view',
			'friendly_name' => __('Sequence'),
			'description'   => __('Sequence of Item.'),
			'value'         => '|arg1:sequence|'
		],
	];

	// fetch the current report record
	$report = db_fetch_row_prepared('SELECT *
		FROM reports
		WHERE id = ?',
		[gfrv('id')]
	);

	// if an existing item was requested, fetch data for it
	if (isrv('item_id') && (gfrv('item_id') > 0)) {
		$header_label = __esc('Report Item [edit Report: %s]', $report['name']);
	} else {
		$header_label = __esc('Report Item [new Report: %s]', $report['name']);
	}

	// set the default item alignment and size
	$fields_reports_item_edit['align']['default']     = $report['alignment'];
	$fields_reports_item_edit['font_size']['default'] = $report['font_size'];

	// draw the tabs
	srv('tab', 'items');
	reports_tabs(grv('id'));

	form_start(get_current_page(), 'chk');

	// ready for displaying the fields
	html_start_box($header_label, '100%', true, 3, 'center', get_reports_page() . '?action=item_edit&id=' . grv('id'));

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($fields_reports_item_edit, $report_item)
		]
	);

	html_end_box(true, true);

	form_hidden_box('id', ($report_item['id'] ?? '0'), '');
	form_hidden_box('report_id', ($report_item['report_id'] ?? '0'), '');
	form_hidden_box('save_component_report_item', '1', '');

	form_save_button(get_reports_page() . '?action=edit&tab=items&id=' . grv('id'), 'return');

	print "<table id='graphdiv' style='text-align:center;width:100%;display:none;'><tr><td class='center' style='padding:5px' id='graph'></td></tr></table>";

	if (isset($report_item['item_type']) && $report_item['item_type'] == REPORTS_ITEM_GRAPH) {
		$timespan = [];
		// get config option for first-day-of-the-week
		$first_weekdayid = read_user_setting('first_weekdayid');

		// get start/end time-since-epoch for actual time (now()) and given current-session-timespan
		if (isset($report_item['timespan'])) {
			$current_ts = $report_item['timespan'];
		} else {
			$current_ts = 7;
		}

		get_timespan($timespan, time(), $current_ts, $first_weekdayid);
	}

	// don't cache previews
	$_SESSION['custom'] = 'true';

	?>
	<script type='text/javascript'>
		useCss = <?php print($report['cformat'] == 'on' ? 'true' : 'false'); ?>;

		function toggle_item_type() {
			$('#chk').hide();
			// right bracket ')' does not come with a field
			var reportsItemGraph = $('#item_type').val() == '<?php print REPORTS_ITEM_GRAPH; ?>';
			var reportsItemHost = $('#item_type').val() == '<?php print REPORTS_ITEM_HOST; ?>';
			var reportsItemText = $('#item_type').val() == '<?php print REPORTS_ITEM_TEXT; ?>';
			var reportsItemTree = $('#item_type').val() == '<?php print REPORTS_ITEM_TREE; ?>';

			if (reportsItemGraph || reportsItemHost) {
				$('#item_text').val('');
			}

			toggleFields({
				align: reportsItemGraph || reportsItemHost || reportsItemText || reportsItemTree,
				site_id: reportsItemGraph || reportsItemHost,
				tree_id: reportsItemTree,
				branch_id: reportsItemTree,
				tree_cascade: reportsItemTree,
				graph_name_regexp: reportsItemHost || reportsItemText || reportsItemTree,
				host_template_id: reportsItemGraph || reportsItemHost || reportsItemText,
				host_id: reportsItemGraph || reportsItemHost,
				graph_template_id: reportsItemGraph || reportsItemHost,
				local_graph_id: reportsItemGraph,
				timespan: reportsItemGraph || reportsItemHost || reportsItemTree,
				item_text: reportsItemText,
				font_size: !useCss && (reportsItemText || reportsItemTree),
			})

			$('#chk').show();
		}

		function changeTree() {
			strURL = '?action=setvar&tree_id=' + $('#tree_id').val();
			$.getJSON(strURL, function(data) {
				$.get('?action=ajax_get_branches&tree_id=' + $('#tree_id').val(), function(data) {
					var selectmenu = $('#branch_id').selectmenu('instance');

					$('#branch_id').replaceWith(data);

					if (selectmenu) {
						$('#branch_id').selectmenu();
					}
				});
			});
		}

		function changeDevice() {
			strURL = '?action=setvar' +
				'&site_id=' + $('#site_id').val() +
				'&host_id=' + $('#host_id').val() +
				'&host_template_id=' + $('#host_template_id').val() +
				'&graph_template_id=' + $('#graph_template_id').val() +
				'&local_graph_id=' + $('#local_graph_id').val();

			$.getJSON(strURL, function(data) {
				resetSelects(data);
			});
		}

		function changeSite() {
			strURL = '?action=setvar' +
				'&site_id=' + $('#site_id').val() +
				'&host_id=' + $('#host_id').val() +
				'&host_template_id=' + $('#host_template_id').val() +
				'&graph_template_id=' + $('#graph_template_id').val() +
				'&local_graph_id=' + $('#local_graph_id').val();

			$.getJSON(strURL, function(data) {
				resetSelects(data);
			});
		}

		function resetSelects(data) {
			if (data.site_id) {
				$('#site_id').val('-1');
				if ($('#site_id').selectmenu('instance')) {
					$('#site_id').selectmenu('refresh');
				}
			}

			if (data.host_template_id) {
				$('#host_template_id').val('-1');
				if ($('#host_template_id').selectmenu('instance')) {
					$('#host_template_id').selectmenu('refresh');
				}
			}

			if (data.host_id) {
				$('#host_id_input').val('<?php print __('None'); ?>');
				$('#host_id').val(0);
			}

			if (data.graph_template_id) {
				$('#graph_template_id_input').val('<?php print __('None'); ?>');
				$('#graph_template_id').val(0);
			}

			if (data.local_graph_id) {
				$('#local_graph_id_input').val('<?php print __('None'); ?>');
				$('#local_graph_id').val(0);
			}
		}

		function changeDeviceTemplate() {
			strURL = '?action=setvar' +
				'&site_id=' + $('#site_id').val() +
				'&host_id=' + $('#host_id').val() +
				'&host_template_id=' + $('#host_template_id').val() +
				'&graph_template_id=' + $('#graph_template_id').val() +
				'&local_graph_id=' + $('#local_graph_id').val();

			$.getJSON(strURL, function(data) {
				resetSelects(data);
			});
		}

		function changeGraphTemplate() {
			strURL = '?action=setvar' +
				'&site_id=' + $('#site_id').val() +
				'&host_id=' + $('#host_id').val() +
				'&host_template_id=' + $('#host_template_id').val() +
				'&graph_template_id=' + $('#graph_template_id').val() +
				'&local_graph_id=' + $('#local_graph_id').val();

			$.getJSON(strURL, function(data) {
				resetSelects(data);
			});
		}

		function changeGraph() {
			graphImage($('#local_graph_id').val());
		}

		function applyChange() {
			strURL = '?action=item_edit'
			strURL += '&id=' + $('#report_id').val();
			strURL += '&item_id=' + $('#id').val();
			strURL += '&item_type=' + $('#item_type').val();
			strURL += '&tree_id=' + $('#tree_id').val();
			strURL += '&branch_id=' + $('#branch_id').val();
			strURL += '&host_template_id=' + $('#host_template_id').val();
			strURL += '&host_id=' + $('#host_id').val();
			strURL += '&graph_template_id=' + $('#graph_template_id').val();
			loadUrl({
				url: strURL
			})
		}

		function graphImage(graphId) {
			if (graphId > 0) {
				$('#graphdiv').show();
				$('#graph').html("<img class='center' src='<?php print CACTI_PATH_URL; ?>graph_image.php" +
					"?local_graph_id=" + graphId +
					"&image_format=png" +
					"<?php print(($report['graph_width'] > 0) ? '&graph_width=' . $report['graph_width'] : ''); ?>" +
					"<?php print(($report['graph_height'] > 0) ? '&graph_height=' . $report['graph_height'] : ''); ?>" +
					"<?php print(($report['thumbnails'] == 'on') ? '&graph_nolegend=true' : ''); ?>" +
					"<?php print((isset($timespan['begin_now'])) ? '&graph_start=' . $timespan['begin_now'] : ''); ?>" +
					"<?php print((isset($timespan['end_now'])) ? '&graph_end=' . $timespan['end_now'] : ''); ?>" +
					"&rra_id=0'>");
			} else {
				$('#graphdiv').hide();
				$('#graph').html('');
			}
		}

		$(function() {
			toggle_item_type();

			if ($('#item_type').val() == 1) {
				graphImage($('#local_graph_id').val());
			}
		});
	</script>
	<?php
}

/**
 * Generates and displays the tabs for the report editing interface.
 *
 * @param int $report_id The ID of the report. If greater than 0, additional tabs are shown.
 *
 * @return void
 */
function reports_tabs(mixed $report_id) : void {
	if ($report_id > 0) {
		$tabs = ['details' => __('Details'), 'items' => __('Items'), 'preview' => __('View Reports')];
	} else {
		$tabs = ['details' => __('Details')];
	}

	// set the default settings category
	if (!isrv('tab')) {
		srv('tab', 'details');
	}

	$current_tab = grv('tab');

	if (cacti_sizeof($tabs) && isrv('id')) {
		// draw the tabs
		print "<div class='tabs'><nav><ul role='tablist'>";

		foreach ($tabs as $tab => $name) {
			print "<li class='subTab'><a class='tab" . ($tab == $current_tab ? " selected'" : "'") .
				" href='" . htmle(CACTI_PATH_URL . get_reports_page() . '?action=edit' .
					'&id=' . grv('id') . '&tab=' . $tab) .
				"'>" . $name . '</a></li>';
		}

		if (!ierv('id')) {
			print "<li style='float:right;position:relative;'><a class='tab' href='" . htmle(get_reports_page() . '?action=send&id=' . grv('id') . '&tab=' . grv('tab')) . "'>" . __('Send Report') . '</a></li>';
		}

		print '</ul></nav></div>';
	}
}

/**
 * Edit and manage reports.
 *
 * This function handles the editing and management of reports, including input validation,
 * session storage, and displaying the report details, items, events, and preview.
 *
 * @return void
 */
function reports_edit() : void {
	global $attach_types, $alignment, $reports_interval, $fields_reports_edit;

	// ================= input validation and session storage =================
	$filters = [
		'rows' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '-1'
		],
		'page' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		],
		'filter' => [
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
		],
		'name' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'name',
			'options' => ['options' => 'sanitize_search_string']
		],
		'tab' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'details',
			'options' => ['options' => 'sanitize_search_string']
		]
	];

	validate_store_request_vars($filters, 'sess_repe');
	// ================= input validation =================

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// display the report
	$report = [];

	if (gfrv('id') > 0) {
		$report = db_fetch_row_prepared('SELECT * FROM reports WHERE id = ?', [grv('id')]);
	}

	reports_tabs(grv('id'));

	if (isset($report['id'])) {
		$header_label = __('[edit: %s]', $report['name']);
	} else {
		$header_label = __('[new]');
	}

	switch (grv('tab')) {
		case 'details':
			form_start(get_reports_page());

			html_start_box(__esc('Details %s', $header_label), '100%', true, 3, 'center', '');

			if (!db_table_exists('plugin_notification_lists')) {
				unset($fields_reports_edit['notify_list']);
			}

			draw_edit_form([
				'config' => ['no_form_tag' => true],
				'fields' => inject_form_variables($fields_reports_edit, $report)
			]);

			html_end_box(true, true);

			form_hidden_box('id', ($report['id'] ?? '0'), '');
			form_hidden_box('save_component_report', '1', '');

			?>
			<script type='text/javascript'>
			function changeFormat() {
				toggleFields({
					font_size: !(cformat && cformat.checked),
					alignment: !(cformat && cformat.checked),
					format_file: (cformat && cformat.checked),
				})
			}

			$(function() {
				$('#cformat').click(function() {
					changeFormat();
				});

				changeFormat();
			});
			</script>
			<?php

			api_scheduler_javascript();

			form_save_button(get_reports_page(), 'return');

			break;
		case 'items':
			html_start_box(__esc('Report Items %s', $header_label), '100%', false, 3, 'center', get_reports_page() . '?action=item_edit&id=' . grv('id'));

			// display the items
			if (!empty($report['id'])) {
				display_reports_items($report['id']);
			}

			html_end_box();

			if (!empty($report['id']) && read_config_option('drag_and_drop') == 'on') {
				?>
				<script type='text/javascript'>
					var reportsPage = '<?php print get_reports_page(); ?>';
					var reportId = <?php print $report['id']; ?>;

					// Switch the table name
					$('#reports_admin_edit1_child, #reports_user_edit1_child').attr('id', 'report_item');

					$(function() {
						$('#report_item').tableDnD({
							onDrop: function(table, row) {
								loadUrl({
									url: reportsPage + '?action=ajax_dnd&id=' + reportId + '&' + $.tableDnD.serialize()
								})
							}
						});
					});
				</script>
				<?php
			}

			break;
		case 'preview':
			draw_preview_filter(true, $header_label);

			if (grv('rdate') == '-1') {
				if (grv('style') == 'false') {
					$theme = get_selected_theme();
				} else {
					$theme = '';
				}
				print '<tr><td>';
				print reports_generate_html($report['id'], REPORTS_OUTPUT_STDOUT, $theme);
				print '</td></tr>';
			} else {
				print '<tr><td>';
				print reports_generate_history_html(grv('rdate'), REPORTS_OUTPUT_STDOUT);
				print '</td></tr>';
			}

			html_end_box(false);

			?>
			<script type='text/javascript'>
			$('#rdate').change(function() {
				if ($('#rdate').val() == '-1') {
					$('#delete').button('disable');
				} else {
					$('#delete').button('enable')
				}
			});
			</script>
			<?php

			break;
	}
}

/**
 * Displays the items of a report based on the given report ID.
 *
 * This function fetches the report items from the database, formats them, and displays them in an HTML table.
 * It supports different item types such as graphs, hosts, text, and trees, and handles their specific details.
 *
 * @param int $report_id The ID of the report whose items are to be displayed.
 *
 * @return void
 */
function display_reports_items(int $report_id) : void {
	global $graph_timespans;
	global $item_types, $alignment;

	$items = db_fetch_assoc_prepared('SELECT *
		FROM reports_items
		WHERE report_id = ?
		ORDER BY sequence', [$report_id]);

	$css = db_fetch_cell_prepared('SELECT cformat FROM reports WHERE id = ?', [$report_id]);

	html_header(
		[
			['display' => __('Item'),         'align' => 'left'],
			['display' => __('Sequence'),     'align' => 'left'],
			['display' => __('Type'),         'align' => 'left'],
			['display' => __('Item Details'), 'align' => 'left'],
			['display' => __('Timespan'),     'align' => 'left'],
			['display' => __('Alignment'),    'align' => 'left'],
			['display' => __('Font Size'),    'align' => 'left'],
			['display' => __('Actions'),      'align' => 'right']
		],
		2
	);

	$i = 1;

	if (cacti_sizeof($items)) {
		foreach ($items as $item) {
			switch ($item['item_type']) {
				case REPORTS_ITEM_GRAPH:
					$item_details = __('Graph: %s', get_graph_title($item['local_graph_id']));

					if ($css == 'on') {
						$align = __('Using CSS');
					} else {
						$align = ($item['align'] > 0 ? $alignment[$item['align']] : '');
					}

					$size     = __('N/A');
					$timespan = ($item['timespan'] > 0 ? $graph_timespans[$item['timespan']] : '');

					break;
				case REPORTS_ITEM_HOST:
					$item_details = __('Device: %s', db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', [$item['host_id']]));

					if ($item['graph_template_id'] == -1) {
						$item_details .= __(', Graph Template: All Templates');
					} else {
						$item_details .= __(', Graph Template: %s', db_fetch_cell_prepared('SELECT name
							FROM graph_templates
							WHERE id = ?',
							[$item['graph_template_id']]
						));
					}

					if ($item['graph_name_regexp'] != '') {
						$item_details .= __(', Using RegEx: "%s"', $item['graph_name_regexp']);
					}

					if ($css == 'on') {
						$align = __('Using CSS');
					} else {
						$align = ($item['align'] > 0 ? $alignment[$item['align']] : '');
					}

					$size     = __('N/A');
					$timespan = ($item['timespan'] > 0 ? $graph_timespans[$item['timespan']] : '');

					break;
				case REPORTS_ITEM_TEXT:
					$item_details = $item['item_text'];

					if ($css == 'on') {
						$align = __('Using CSS');
						$size  = __('Using CSS');
					} else {
						$align = ($item['align'] > 0 ? $alignment[$item['align']] : '');
						$size  = ($item['font_size'] > 0 ? $item['font_size'] : '');
					}

					$timespan = '';

					break;
				case REPORTS_ITEM_TREE:
					if ($item['branch_id'] > 0) {
						$branch_details = db_fetch_row_prepared('SELECT *
							FROM graph_tree_items
							WHERE id = ?',
							[$item['branch_id']]
						);
					} else {
						$branch_details = [];
					}

					$tree_name = db_fetch_cell_prepared('SELECT name
						FROM graph_tree
						WHERE id = ?',
						[$item['tree_id']]
					);

					$item_details = __('Tree: %s', $tree_name);

					if ($item['branch_id'] > 0) {
						if ($branch_details['host_id'] > 0) {
							$description = db_fetch_cell_prepared('SELECT description
								FROM host
								WHERE id = ?',
								[$branch_details['host_id']]
							);

							$item_details .= __(', Device: %s', $description);
						} else {
							$item_details .= __(', Branch: %s', $branch_details['title']);

							if ($item['tree_cascade'] == 'on') {
								$item_details .= ' ' . __('(All Branches)');
							} else {
								$item_details .= ' ' . __('(Current Branch)');
							}
						}
					}

					if ($item['graph_name_regexp'] != '') {
						$item_details .= __(', Using RegEx: "%s"', $item['graph_name_regexp']);
					}

					if ($css == 'on') {
						$align    = __('Using CSS');
						$size     = __('Using CSS');
					} else {
						$align    = ($item['align'] > 0 ? $alignment[$item['align']] : '');
						$size     = ($item['font_size'] > 0 ? $item['font_size'] : '');
					}

					$timespan = ($item['timespan'] > 0 ? $graph_timespans[$item['timespan']] : '');

					break;
				default:
					$item_details = '';

					$align    = __('N/A');
					$size     = __('N/A');
					$timespan = __('N/A');
			}

			form_alternate_row('line' . $item['id'], false);
			$form_data = '<td><a class="linkEditMain" href="' . htmle(get_reports_page() . '?action=item_edit&id=' . $report_id . '&item_id=' . $item['id']) . '">' . __('Item # %d', $i) . '</a></td>';
			$form_data .= '<td>' . $item['sequence'] . '</td>';
			$form_data .= '<td>' . $item_types[$item['item_type']] . '</td>';
			$form_data .= '<td class="nowrap">' . htmle($item_details) . '</td>';
			$form_data .= '<td class="nowrap">' . $timespan . '</td>';
			$form_data .= '<td>' . $align . '</td>';
			$form_data .= '<td>' . $size . '</td>';

			if ($i == 1) {
				$form_data .= '<td class="right nowrap"><a class="pic remover ti ti-caret-down-filled moveArrow" style="padding:3px" title="' . __esc('Move Down') . '" href="' . htmle(get_reports_page() . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $report_id) . '"></a>' . '<span style="padding:5ps" class="moveArrowNone"></span>';
			} elseif ($i < cacti_sizeof($items)) {
				$form_data .= '<td class="right nowrap"><a class="pic remover ti ti-caret-down-filled moveArrow" style="padding:3px" title="' . __esc('Move Down') . '" href="' . htmle(get_reports_page() . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $report_id) . '"></a>' . '<a class="remover ti ti-caret-up-filled moveArrow" style="padding:3px" title="' . __esc('Move Up') . '" href="' . htmle(get_reports_page() . '?action=item_moveup&item_id=' . $item['id'] . '&id=' . $report_id) . '"></a>';
			} else {
				$form_data .= '<td class="right nowrap"><span style="padding:3px" class="moveArrowNone"></span>' . '<a class="remover ti ti-caret-up-filled moveArrow" style="padding:3px" title="' . __esc('Move Up') . '" href="' . htmle(get_reports_page() . '?action=item_moveup&item_id=' . $item['id'] . '&id=' . $report_id) . '"></a>';
			}

			$form_data .= '<a class="pic deleteMarker ti ti-x" style="padding:3px" href="' . htmle(get_reports_page() . '?action=item_remove&item_id=' . $item['id'] . '&id=' . $report_id) . '" title="' . __esc('Delete') . '"></a>' . '</td></tr>';

			print $form_data;

			$i++;
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="9"><em>' . __('No Report Items') . '</em></td></tr>';
	}
}

/**
 * Retrieves the appropriate reports page based on the user's permissions.
 *
 * @return string The path to the appropriate reports page.
 */
function get_reports_page() : string {
	return 'reports.php';
}

/**
 * Checks if the current user has administrative privileges for reports.
 *
 * @return bool Returns true if the user has reports administrative privileges, false otherwise.
 */
function is_reports_admin() : bool {
	return (is_realm_allowed(21) ? true : false);
}

function create_preview_filter() : array {
	$id    = gfrv('id');
	$rdate = gfrv('rdate');

	$any   = ['-1' => __('Any')];
	$none  = ['0'  => __('None')];
	$live  = ['-1' => __('Live Data')];

	$sql_where    = '';
	$sql_params[] = $id;
	$sql_params[] = 'reports';

	if (gnrv('filter') != '') {
		$sql_where    = ' AND (name LIKE ? OR send_time LIKE ?)';
		$sql_params[] = '%' . gnrv('filter') . '%';
		$sql_params[] = '%' . gnrv('filter') . '%';
	}

	$reports = db_fetch_assoc_prepared("SELECT id, source_id, CONCAT(name, ' [ ', send_time, ' ]') AS name
		FROM reports_log
		WHERE source_id = ?
		AND source = ?
		$sql_where
		ORDER BY send_time DESC",
		$sql_params);

	$dreports  = array_rekey($reports, 'id', 'name');
	$dreports  = $live + $dreports;

	if (isrv('style')) {
		$value = gnrv('style');
	} else {
		$value = '';
	}

	return [
		'rows' => [
			[
				'filter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_DEFAULT,
					'placeholder'    => __('Enter a search term'),
					'size'           => '30',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				],
				'rdate' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Report Date'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'array'          => $dreports,
					'value'          => '-1'
				],
				'style' => [
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Apply Report Style'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '(true|false)']],
					'default'        => '',
					'value'          => $value
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply filter to table'),
			],
			'delete' => [
				'method'   => 'button',
				'display'  => __('Delete'),
				'title'    => __('Remove Archived Report'),
				'callback' => "loadUrl({ url: 'reports.php?action=remove_history&rdate=$rdate&id=$id'})"
			],
		]
	];
}

function draw_preview_filter(bool $render = false, string $header_label = '') : void {
	$filters = create_preview_filter();

	$report_id  = grv('id');
	$history_id = grv('rdate');

	$header = __esc('Report Preview %s', $header_label);

	// create the page filter
	$pageFilter = new CactiTableFilter($header, "reports.php?action=edit&id=$report_id&tab=preview", 'forms', 'sess_repprv');

	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function create_reports_filter() : array {
	global $item_rows;

	$any  = ['-1' => __('Any')];
	$none = ['0'  => __('None')];

	$report_types = [
		'-1'       => __('All'),
		'reports'  => __('Classic'),
		'reportit' => __('ReportIt')
	];

	$statuses = [
		'-1' => __('Any'),
		'-2' => __('Enabled'),
		'-3' => __('Disabled')
	];

	return [
		'rows' => [
			[
				'filter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_DEFAULT,
					'placeholder'    => __('Enter a search term'),
					'size'           => '30',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				],
				'report_type' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Report Type'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $report_types,
					'value'          => '-1'
				],
				'status' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Status'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $statuses,
					'value'          => '-1'
				],
				'rows' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Reports'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $item_rows,
					'value'          => '-1'
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply Filter to Table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset Filter to Default Values'),
			]
		],
		'sort' => [
			'sort_column'    => 'name',
			'sort_direction' => 'ASC'
		],
		'javascript' => [
			'global' => '',
			'ready'  => ''
		]
	];
}

function draw_reports_filter(bool $render = false) : void {
	$filters = create_reports_filter();

	$header = __('Reports [%s]', (is_reports_admin() ? __('Administrator Level') : __('User Level')));

	// create the page filter
	$pageFilter = new CactiTableFilter($header, 'reports.php', 'forms', 'sess_repv', 'reports.php?action=edit&tab=details');

	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

/**
 * Generates and displays the reports page with filtering, sorting, and pagination options.
 *
 * @return void
 */
function reports() : void {
	global $item_rows, $reports_interval;
	global $reports_actions, $attach_types, $sched_types;

	draw_reports_filter(true);

	if (grv('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = 'WHERE (report.name LIKE ' . db_qstr('%' . grv('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (grv('status') == '-1') {
		// Show all items
	} elseif (grv('status') == '-2') {
		$sql_where .= ($sql_where != '' ? " AND report.enabled='on'" : " WHERE report.enabled='on'");
	} elseif (grv('status') == '-3') {
		$sql_where .= ($sql_where != '' ? " AND report.enabled=''" : " WHERE report.enabled=''");
	}

	// account for permissions
	if (is_reports_admin()) {
		$sql_join = 'LEFT JOIN user_auth AS ua ON ua.id = report.user_id';
	} else {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' user_auth.id=' . $_SESSION[SESS_USER_ID];
		$sql_join = 'INNER JOIN user_auth AS ua ON ua.id = report.user_id';
	}

	$reports_list = [];

	if (db_table_exists('plugin_reportit_reports')) {
		if (grv('report_type') == '-1') {
			$total_rows = db_fetch_cell("SELECT SUM(row_count)
				FROM (
					SELECT COUNT(report.id) AS row_count
					FROM reports AS report
					$sql_join
					$sql_where
					UNION ALL
					SELECT COUNT(report.id) AS row_count
					FROM plugin_reportit_reports AS report
					$sql_join
					$sql_where
				) AS rs");

			$reports_list = db_fetch_assoc("
				SELECT 'reports' AS type, ua.full_name, ua.username, report.id, report.user_id, report.name, report.enabled,
				report.sched_type, report.last_runtime, report.last_started, report.run_limit,
				report.from_email, report.from_name, report.email, report.bcc, report.next_start
				FROM reports AS report
				$sql_join
				$sql_where
				UNION
				SELECT 'reportit' AS type, ua.full_name, ua.username, report.id, report.user_id, report.name, report.enabled,
				report.sched_type, report.last_runtime, report.last_started, report.run_limit,
				'-' AS from_email, '-' AS from_name, '-' AS email, '-' AS bcc, report.next_start
				FROM plugin_reportit_reports AS report
				$sql_join
				$sql_where
				ORDER BY " . sanitize_sql_column(grv('sort_column')) . ' ' . (strtoupper(grv('sort_direction')) === 'DESC' ? 'DESC' : 'ASC') .
				' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows);
		} elseif (grv('report_type') == 'reports') {
			$total_rows = db_fetch_cell("SELECT COUNT(report.id)
				FROM reports AS report
				$sql_join
				$sql_where");

			$reports_list = db_fetch_assoc("SELECT
				'reports' AS type, ua.full_name, ua.username, report.id, report.user_id, report.name, report.enabled,
				report.sched_type, report.last_runtime, report.last_started, report.run_limit,
				report.from_email, report.from_name, report.email, report.bcc, report.next_start
				FROM reports AS report
				$sql_join
				$sql_where
				ORDER BY " . sanitize_sql_column(grv('sort_column')) . ' ' . (strtoupper(grv('sort_direction')) === 'DESC' ? 'DESC' : 'ASC') .
				' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows);
		} else {
			$total_rows = db_fetch_cell("SELECT COUNT(report.id)
				FROM plugin_reportit_reports AS report
				$sql_join
				$sql_where");

			$reports_list = db_fetch_assoc("SELECT
				'reportit' AS type, ua.full_name, ua.username, report.id, report.user_id, report.name, report.enabled,
				report.sched_type, report.last_runtime, report.last_started, report.run_limit,
				'-' AS from_email, '-' AS from_name, '-' AS email, '-' AS bcc, report.next_start
				FROM plugin_reportit_reports AS report
				$sql_join
				$sql_where
				ORDER BY " . sanitize_sql_column(grv('sort_column')) . ' ' . (strtoupper(grv('sort_direction')) === 'DESC' ? 'DESC' : 'ASC') .
				' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows);
		}
	} else {
		$total_rows = db_fetch_cell("SELECT COUNT(report.id)
			FROM reports AS report
			$sql_join
			$sql_where");

		$reports_list = db_fetch_assoc("SELECT
			'reports' AS type, ua.full_name, ua.username, report.id, report.user_id, report.name, report.enabled,
			report.sched_type, report.last_runtime, report.last_started, report.run_limit,
			report.from_email, report.from_name, report.email, report.bcc, report.next_start
			FROM reports AS report
			$sql_join
			$sql_where
			ORDER BY " . sanitize_sql_column(grv('sort_column')) . ' ' . (strtoupper(grv('sort_direction')) === 'DESC' ? 'DESC' : 'ASC') .
			' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows);
	}

	$display_text = [
		'report.name' => [
			'display' => __('Name'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'type' => [
			'display' => __('Report Type'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'report.full_name' => [
			'display' => __('Owner'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'sched_type' => [
			'display' => __('Schedule'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'nosort' => [
			'display' => __('Notification Detail'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('Email options in use: To, BCC, and Notification List')
		],
		'report.enabled' => [
			'display' => __('Enabled'),
			'align'   => 'right',
			'sort'    => 'ASC'
		],
		'next_start' => [
			'display' => __('Next Start'),
			'align'   => 'right',
			'sort'    => 'ASC'
		],
		'report.last_started' => [
			'display' => __('Last Started'),
			'align'   => 'right',
			'sort'    => 'ASC'
		],
		'report.run_limit' => [
			'display' => __('Run Limit'),
			'align'   => 'right',
			'sort'    => 'DESC'
		],
		'report.last_runtime' => [
			'display' => __('Last Runtime'),
			'align'   => 'right',
			'sort'    => 'DESC'
		],
	];

	$nav = html_nav_bar(get_reports_page() . 'filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, (cacti_sizeof($display_text) + 1), __('Reports'), 'page', 'main');

	form_start(get_reports_page(), 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($reports_list)) {
		$date_format = reports_date_time_format();

		foreach ($reports_list as $report) {
			if (!reports_html_account_exists($report['user_id'])) {
				if ($report['type'] == 'reports') {
					reports_html_report_disable($report['id']);
					$report['enabled'] = '';
				}
			}

			if ($report['type'] == 'reports') {
				$type = __('Classic');
				$url  = get_reports_page() . '?action=edit&tab=details&id=' . $report['id'] . '&page=1';
			} else {
				$type = __('ReportIt');
				$url  = CACTI_PATH_URL . '/plugins/reportit/reportit.php?action=report_edit&tab=general&id=' . $report['id'];
			}

			$id = $report['type'] . '_' . $report['id'];

			form_alternate_row('line' . $id, true);

			form_selectable_cell(filter_value($report['name'], grv('filter'), $url), $id);

			form_selectable_cell($type, $id);

			if (reports_html_account_exists($report['user_id'])) {
				form_selectable_ecell($report['full_name'] ?: $report['username'], $id);
			} else {
				form_selectable_cell(__('Report Disabled - No Owner'), $id);
			}

			$interval = $sched_types[$report['sched_type']];

			form_selectable_cell($interval, $id);

			// iconography
			$notify = '<span>';
			$found  = false;

			if ($report['email'] != '') {
				$notify .= '<i class="ti ti-mail-filled" title="' . __esc('To Emails in Use') . '"></i>';
				$found  = true;
			}

			if ($report['bcc'] != '') {
				$notify .= '<i class="ti ti-eye-off" title="' . __('Blind Copy in Use') . '"></i>';
				$found  = true;
			}

			if ($report['bcc'] != '') {
				$notify .= '<i class="ti ti-flag" title="' . __('Notification List in Use') . '"></i>';
				$found  = true;
			}

			if ($found) {
				$notify .= '</span>';
			}

			form_selectable_cell($notify, $id);

			form_selectable_cell($report['enabled'] ? '<i class="ti ti-check deviceUp"></i>' : '<i class="ti ti-x deviceDown"></i>', $id, '', 'right');

			if ($report['sched_type'] != 1) {
				form_selectable_cell(date($date_format, strtotime($report['next_start'])), $id, '', 'right');
			} else {
				form_selectable_cell(__('N/A'), $id, '', 'right');
			}

			form_selectable_cell($report['last_started'] == '0000-00-00 00:00:00' ? __('Never') : date($date_format, strtotime($report['last_started'])), $id, '', 'right');

			form_selectable_cell(__('%s sec', number_format_i18n($report['run_limit'], 1)), $id, '', 'right');

			form_selectable_cell(__('%0.2f sec', number_format_i18n($report['last_runtime'], 2)), $id, '', 'right');

			form_checkbox_cell($report['name'], $id);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Reports Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($reports_list)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($reports_actions);

	form_end();

	?>
	<script type='text/javascript'>
		function applyFilter() {
			strURL = '<?php print get_reports_page(); ?>?status=' + $('#status').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			loadUrl({
				url: strURL
			})
		}

		function clearFilter() {
			strURL = '<?php print get_reports_page(); ?>?clear=1';
			loadUrl({
				url: strURL
			})
		}

		$(function() {
			$('#refresh').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#form_report').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
	</script>
	<?php
}

/**
 * Checks if an account exists for the given user ID.
 *
 * @param int $user_id The ID of the user to check.
 *
 * @return mixed The ID of the user if the account exists, or false if it does not.
 */
function reports_html_account_exists(int $user_id) : mixed {
	return db_fetch_cell_prepared('SELECT id FROM user_auth WHERE id = ?', [$user_id]);
}

/**
 * Disables an HTML report by setting its 'enabled' field to an empty string.
 *
 * @param int $report_id The ID of the report to disable.
 *
 * @return void
 */
function reports_html_report_disable(int $report_id) : void {
	db_execute_prepared('UPDATE reports SET enabled="" WHERE id = ?', [$report_id]);
}

/**
 * Sets a variable in the reports item array based on the request variable.
 *
 * @param array  $reports_item The reports item array to be modified.
 * @param string $var_id       The ID of the request variable to check and set.
 *
 * @return array The modified reports item array.
 */
function set_reports_item_var(array $reports_item, string $var_id) : array {
	// if a different host_id was selected, use it
	if (isrv($var_id) && gfrv($var_id) >= 0) {
		$reports_item[$var_id] = grv($var_id);
	}

	// Check that we have set a host_id, if not, default to 0
	if (!isset($reports_item[$var_id])) {
		$reports_item[$var_id] = 0;
	}

	return $reports_item;
}

/**
 * Generates a HTML select element for branches based on the provided tree ID.
 *
 * @param int $tree_id The ID of the tree to filter branches by. If 0 or not provided, all branches are shown.
 *
 * @return string The HTML select element as a string.
 */
function reports_get_branch_select(int $tree_id) : string {
	$sql_where = '';

	if ($tree_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'gt.id=' . $tree_id;
	}

	$branches = array_rekey(
		get_allowed_branches($sql_where),
		'id',
		'name'
	);

	$output = '<select id="branch_id">';

	$output .= '<option value="0">' . __('All') . '</option>';

	if (cacti_sizeof($branches)) {
		foreach ($branches as $id => $name) {
			$output .= "<option value='$id'>" . htmle($name) . '</option>';
		}
	}

	$output .= '</select>';

	return $output;
}
