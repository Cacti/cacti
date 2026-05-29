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

require_once('include/auth.php');
require_once('lib/rrd.php');
require_once('lib/dsdebug.php');

$actions = [
	1 => __('Run Check'),
	2 => __('Delete Check')
];

ini_set('memory_limit', '-1');

set_default_action();

draw_data_debug_filter(false);

switch (grv('action')) {
	case 'actions':
		form_actions();

		break;
	case 'run_debug':
		$id = gfrv('id');

		if ($id > 0) {
			$selected_items = [$id];
			debug_delete($selected_items);
			debug_rerun($selected_items);
			raise_message('rerun', __('Data Source debug started.'), MESSAGE_LEVEL_INFO);
			header('Location: data_debug.php?action=view&id=' . gfrv('id'));
		} else {
			raise_message('repair_error', __('Data Source debug received an invalid Data Source ID.'), MESSAGE_LEVEL_ERROR);
		}

		break;
	case 'run_repair':
		$id = gfrv('id');

		if ($id > 0) {
			if (dsdebug_run_repair($id)) {
				raise_message('repair', __('All RRDfile repairs succeeded.'), MESSAGE_LEVEL_INFO);
			} else {
				raise_message('repair', __('One or more RRDfile repairs failed.  See Cacti log for errors.'), MESSAGE_LEVEL_ERROR);
			}

			$selected_items = [$id];

			debug_delete($selected_items);
			debug_rerun($selected_items);

			raise_message('rerun', __('Automatic Data Source debug being rerun after repair.'), MESSAGE_LEVEL_INFO);

			header('Location: data_debug.php?action=view&id=' . gfrv('id'));
		} else {
			raise_message('repair_error', __('Data Source repair received an invalid Data Source ID.'), MESSAGE_LEVEL_ERROR);
		}

		break;
	case 'view':
		$id = gfrv('id');

		$debug_status = debug_process_status($id);

		if ($debug_status == 'notset') {
			$selected_items = [$id];
			debug_delete($selected_items);
			debug_rerun($selected_items);
			$debug_status = 'waiting';
		}

		if ($debug_status == 'waiting' || $debug_status == 'analysis') {
			$refresh = [
				'seconds' => 30,
				'page'    => 'data_debug.php?action=view&id=' . $id,
				'logout'  => 'false'
			];

			set_page_refresh($refresh);
		}

		top_header();
		debug_view();
		bottom_footer();

		break;
	case 'ajax_hosts':
		$sql_where = '';

		if (gfrv('site_id') > 0) {
			$sql_where = 'site_id = ' . gfrv('site_id');
		}

		get_allowed_ajax_hosts(true, true, $sql_where);

		break;
	case 'ajax_hosts_noany':
		$sql_where = '';

		if (gfrv('site_id') > 0) {
			$sql_where = 'site_id = ' . gfrv('site_id');
		}

		get_allowed_ajax_hosts(false, true, $sql_where);

		break;
	case 'runall':
		debug_runall_filtered();

	default:
		$refresh = [
			'seconds' => grv('refresh'),
			'page'    => 'data_debug.php',
			'logout'  => 'false'
		];

		set_page_refresh($refresh);

		top_header();
		debug_wizard();
		bottom_footer();

		break;
}

function debug_runall_filtered() : void {
	$info = [
		'rrd_folder_writable' => '',
		'rrd_exists'          => '',
		'rrd_writable'        => '',
		'active'              => '',
		'owner'               => '',
		'runas_poller'        => '',
		'runas_website'       => get_running_user(),
		'last_result'         => '',
		'valid_data'          => '',
		'rra_timestamp'       => '',
		'rra_timestamp2'      => '',
		'rrd_match'           => ''
	];

	$info = serialize($info);

	$sql_where  = '';
	$sql_params = [];
	$dd_join    = '';
	$now        = time();

	debug_get_filter($sql_where, $sql_params, $dd_join);

	db_execute_prepared("DELETE dd
		FROM data_debug AS dd
		INNER JOIN data_local AS dl
		ON dd.datasource = dl.id
		INNER JOIN data_template_data AS dtd
		ON dl.id=dtd.local_data_id
		INNER JOIN data_template AS dt
		ON dt.id=dl.data_template_id
		INNER JOIN host AS h
		ON h.id = dl.host_id
		$sql_where",
		$sql_params);

	$new_params = [$now, $info, $_SESSION[SESS_USER_ID]];

	$sql_params = $new_params + $sql_params;

	db_execute_prepared("INSERT INTO data_debug
		(started, done, info, user, datasource)
		SELECT ?, '0', ?, ?, dl.id
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id=dtd.local_data_id
		INNER JOIN data_template AS dt
		ON dt.id=dl.data_template_id
		INNER JOIN host AS h
		ON h.id = dl.host_id
		$sql_where",
		$sql_params);
}

function debug_process_status(int $id) : string {
	$status = db_fetch_row_prepared('SELECT done, IFNULL(issue, "waiting") AS issue
		FROM data_debug
		WHERE datasource = ?',
		[$id]);

	if (cacti_sizeof($status) == 0) {
		return 'notset';
	}

	if ($status['issue'] == 'waiting') {
		return 'waiting';
	}

	if ($status['done'] == 1) {
		return 'complete';
	} else {
		return 'analysis';
	}
}

function form_actions() : void {
	global $actions, $assoc_actions;

	// ================= input validation =================
	gfrv('id');
	gfrv('drp_action', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-zA-Z0-9_]+)$/']]);
	// ================= input validation =================

	$selected_items = [];

	if (isrv('save_list')) {
		// loop through each of the lists selected on the previous page and get more info about them
		foreach ($_POST as $var=>$val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$selected_items[] = $matches[1];
			}
		}

		// if we are to save this form, instead of display it
		if (isrv('save_list')) {
			if (grv('drp_action') == '2') { // delete
				debug_delete($selected_items);
				header('Location: data_debug.php?debug=-1');
			} elseif (grv('drp_action') == '1') { // Rerun
				debug_rerun($selected_items);
				header('Location: data_debug.php?debug=1');
			}

			exit;
		}
	}
}

function debug_rerun(array $selected_items) : void {
	$info = [
		'rrd_folder_writable' => '',
		'rrd_exists'          => '',
		'rrd_writable'        => '',
		'active'              => '',
		'owner'               => '',
		'runas_poller'        => '',
		'runas_website'       => get_running_user(),
		'last_result'         => '',
		'valid_data'          => '',
		'rra_timestamp'       => '',
		'rra_timestamp2'      => '',
		'rrd_match'           => ''
	];

	$info = serialize($info);

	if (!empty($selected_items)) {
		foreach ($selected_items as $id) {
			$exists = db_fetch_cell_prepared('SELECT id
				FROM data_debug
				WHERE datasource = ?',
				[$id]);

			if (!$exists) {
				$save               = [];
				$save['id']         = 0;
				$save['datasource'] = $id;

				$save['info']       = $info;
				$save['started']    = time();
				$save['user']       = intval($_SESSION[SESS_USER_ID]);

				$id = sql_save($save, 'data_debug');
			} else {
				$stime = time();

				db_execute_prepared('UPDATE data_debug
					SET started = ?,
					done = 0,
					info = ?,
					issue = ""
					WHERE id = ?',
					[$stime, $info, $exists]);
			}
		}
	}
}

function debug_delete(array $selected_items) : void {
	if (!empty($selected_items)) {
		foreach ($selected_items as $id) {
			db_execute_prepared('DELETE
				FROM data_debug
				WHERE datasource = ?',
				[$id]);
		}
	}
}

function debug_get_filter(string &$sql_where, array &$sql_params, string &$dd_join) : void {
	// form the 'where' clause for our main sql query
	if (grv('rfilter') != '') {
		$sql_where    = 'WHERE (dtd.name_cache RLIKE ? OR dtd.local_data_id RLIKE ? OR dt.name RLIKE ?)';

		$sql_params[] = grv('rfilter');
		$sql_params[] = grv('rfilter');
		$sql_params[] = grv('rfilter');
	}

	if (ierv('host_id')) {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' (dl.host_id = 0 OR dl.host_id IS NULL)';
	} elseif (grv('host_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' dl.host_id = ?';
		$sql_params[] = grv('host_id');
	}

	if (ierv('site_id')) {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' (h.site_id = 0 OR h.site_id IS NULL)';
	} elseif (grv('site_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' h.site_id = ?';
		$sql_params[] = grv('site_id');
	}

	if (grv('template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' dtd.data_template_id = 0';
	} elseif (grv('template_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' dtd.data_template_id = ?';
		$sql_params[] = grv('template_id');
	}

	if (grv('profile') > '-1') {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' dtd.data_source_profile_id = ?';
		$sql_params[] = grv('profile');
	}

	if (grv('status') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' dd.issue != ""';
	} elseif (grv('status') == '1') {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' dtd.active = "on"';
	} elseif (grv('status') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' dtd.active = ""';
	}

	if (grv('debug') == '-1') {
		$dd_join = 'LEFT';
	} elseif (grv('debug') == 0) {
		$dd_join = 'LEFT';
		$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' dd.datasource IS NULL';
	} else {
		$dd_join = 'INNER';
	}
}

function debug_wizard() : void {
	global $actions;

	$display_text = [
		'name_cache' => [
			'display' => __('Data Source'),
			'sort'    => 'ASC',
			'tip'     => __('The Data Source to Debug'),
			],
		'username' => [
			'display' => __('User'),
			'sort'    => 'ASC',
			'tip'     => __('The User who requested the Debug.'),
			],
		'started' => [
			'display' => __('Started'),
			'sort'    => 'DESC',
			'align'   => 'right',
			'tip'     => __('The Date that the Debug was Started.'),
			],
		'local_data_id' => [
			'display' => __('ID'),
			'sort'    => 'ASC',
			'align'   => 'right',
			'tip'     => __('The Data Source internal ID.'),
			],
		'nosort1' => [
			'display' => __('Status'),
			'sort'    => 'ASC',
			'align'   => 'center',
			'tip'     => __('The Status of the Data Source Debug Check.'),
			],
		'nosort2' => [
			'display' => __('Writable'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('Determines if the Data Collector or the Web Site have Write access.'),
		],
		'nosort3' => [
			'display' => __('Exists'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('Determines if the Data Source is located in the Poller Cache.'),
		],
		'nosort4' => [
			'display' => __('Active'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('Determines if the Data Source is Enabled.'),
		],
		'nosort5' => [
			'display' => __('RRD Match'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('Determines if the RRDfile matches the Data Source Template.'),
		],
		'nosort6' => [
			'display' => __('Valid Data'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('Determines if the RRDfile has been getting good recent Data.'),
		],
		'nosort7' => [
			'display' => __('RRD Updated'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('Determines if the RRDfile has been written to properly.'),
		],
		'nosort8' => [
			'display' => __('Issues'),
			'align'   => 'right',
			'sort'    => '',
			'tip'     => __('Summary of issues found for the Data Source.'),
		]
	];

	if (isrv('purge')) {
		db_execute('TRUNCATE TABLE data_debug');
	}

	// fill in the current date for printing in the log
	if (defined('CACTI_DATE_TIME_FORMAT')) {
		$datefmt = CACTI_DATE_TIME_FORMAT;
	} else {
		$datefmt = 'Y-m-d H:i:s';
	}

	draw_data_debug_filter(true);

	$total_rows = 0;
	$checks     = [];

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$sql_where  = '';
	$sql_params = [];
	$dd_join    = '';

	debug_get_filter($sql_where, $sql_params, $dd_join);

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id=dtd.local_data_id
		INNER JOIN data_template AS dt
		ON dt.id=dl.data_template_id
		INNER JOIN host AS h
		ON h.id = dl.host_id
		$dd_join JOIN data_debug AS dd
		ON dl.id = dd.datasource
		$sql_where",
		$sql_params);

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$checks = db_fetch_assoc_prepared("SELECT dd.*, dtd.local_data_id,
		dtd.name_cache, u.username
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id=dtd.local_data_id
		INNER JOIN data_template AS dt
		ON dt.id=dl.data_template_id
		INNER JOIN host AS h
		ON h.id = dl.host_id
		$dd_join JOIN data_debug AS dd
		ON dl.id = dd.datasource
		LEFT JOIN user_auth AS u
		ON u.id = dd.user
		$sql_where
		$sql_order
		$sql_limit",
		$sql_params);

	$nav = html_nav_bar('data_debug.php', MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Data Sources'), 'page', 'main');

	form_start('data_debug.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($checks)) {
		foreach ($checks as $check) {
			if (isset($check['info']) && $check['info'] != '') {
				$info = cacti_unserialize($check['info']);
			} else {
				$info = '';
			}

			if (isset($check['issue']) && $check['issue'] != '') {
				$issues = explode("\n", $check['issue']);
			} else {
				$issues = [];
			}

			$iline  = '';

			if (cacti_sizeof($issues)) {
				$iline = $issues[0];
			}

			$issue_title = implode('<br/>',$issues);

			form_alternate_row('line' . $check['local_data_id']);

			$url = 'data_debug.php?action=view&id=' . $check['local_data_id'];

			form_selectable_cell(filter_value($check['name_cache'], grv('rfilter'), $url), $check['local_data_id']);

			if (!empty($check['datasource'])) {
				form_selectable_ecell($check['username'], $check['local_data_id']);
				form_selectable_cell(date($datefmt, $check['started']), $check['local_data_id'], '', 'right');
				form_selectable_cell($check['local_data_id'], $check['local_data_id'], '', 'right');
				form_selectable_cell(debug_icon(($check['done'] ? ($iline != '' ? 'off' : 'on') : '')), $check['local_data_id'], '', 'center');
				form_selectable_cell(debug_icon($info['rrd_writable']), $check['local_data_id'], '', 'center');
				form_selectable_cell(debug_icon($info['rrd_exists']), $check['local_data_id'], '', 'center');
				form_selectable_cell(debug_icon($info['active']), $check['local_data_id'], '', 'center');
				form_selectable_cell(debug_icon($info['rrd_match']), $check['local_data_id'], '', 'center');
				form_selectable_cell(debug_icon($info['valid_data']), $check['local_data_id'], '', 'center');

				if ($check['done'] && $info['rrd_writable'] == '') {
					form_selectable_cell(debug_icon('blah'), $check['local_data_id'], '', 'center');
				} else {
					form_selectable_cell(debug_icon(($info['rra_timestamp2'] != '' ? 1 : '')), $check['local_data_id'], '', 'center');
				}

				form_selectable_cell(filter_value($iline != '' ? __('Issues') : __('N/A'), '', '#', $issue_title), $check['local_data_id'], '', 'right');
			} else {
				form_selectable_cell('-', $check['local_data_id']);
				form_selectable_cell(__('Not Debugging'), $check['local_data_id'], '', 'right');
				form_selectable_cell($check['local_data_id'], $check['local_data_id'], '', 'right');
				form_selectable_cell('-', $check['local_data_id'], '', 'center');
				form_selectable_cell('-', $check['local_data_id'], '', 'center');
				form_selectable_cell('-', $check['local_data_id'], '', 'center');
				form_selectable_cell('-', $check['local_data_id'], '', 'center');
				form_selectable_cell('-', $check['local_data_id'], '', 'center');
				form_selectable_cell('-', $check['local_data_id'], '', 'center');
				form_selectable_cell('-', $check['local_data_id'], '', 'center');
				form_selectable_cell('-', $check['local_data_id'], '', 'right');
			}

			form_checkbox_cell($check['local_data_id'], $check['local_data_id']);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Data Source Checks Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($checks)) {
		print $nav;
	}

	form_hidden_box('save_list', '1', '');

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}

function debug_view() : void {
	global $refresh;

	$refresh = 60;

	$id = gfrv('id');

	$check = db_fetch_row_prepared('SELECT *
		FROM data_debug
		WHERE datasource = ?',
		[$id]);

	$check_exists = cacti_sizeof($check);

	if (cacti_sizeof($check)) {
		$check['info'] = cacti_unserialize($check['info']);
	}

	$dtd = db_fetch_row_prepared('SELECT *
		FROM data_template_data
		WHERE local_data_id = ?',
		[$id]);

	if (cacti_sizeof($dtd)) {
		$real_path = htmle(str_replace('<path_rra>', CACTI_PATH_RRA, $dtd['data_source_path']));
	} else {
		$real_path = __('Not Found');
	}

	$poller_data = [];

	if (!empty($check['info']['last_result'])) {
		foreach ($check['info']['last_result'] as $a => $l) {
			$poller_data[] = "$a = $l";
		}
	}
	$poller_data = implode('<br>', $poller_data);

	$rra_updated = '';

	if (isset($check['info']['rra_timestamp2'])) {
		$rra_updated = $check['info']['rra_timestamp2'] != '' ? __('Yes') : '';
	}

	$rrd_exists = '';

	if (isset($check['info']['rrd_exists'])) {
		$rrd_exists = $check['info']['rrd_exists'] == '1' ? __('Yes') : __('Not Checked Yet');
	}

	$active = '';

	if (isset($check['info']['active'])) {
		$active = $check['info']['active'] == 'on' ? __('Yes') : __('Not Checked Yet');
	}

	$issue = '';

	if (isset($check['issue'])) {
		$issue = $check['issue'];
	}

	if ($check['done'] == 1) {
		if ($issue != '') {
			$issue_icon = debug_icon(0);
		} else {
			$issue_icon = debug_icon(1);
		}
	} else {
		if (isset($check['info']['rrd_match_array']['ds'])) {
			if ($check['info']['rrd_match'] == 0) {
				$issue_icon = debug_icon('blah');
				$issue      = __('Issues found!  Waiting on RRDfile update');
			} else {
				$issue_icon = debug_icon('');
				$issue      = __('No Initial found!  Waiting on RRDfile update');
			}
		} else {
			$issue_icon = debug_icon('');
			$issue      = __('Waiting on analysis and RRDfile update');
		}
	}

	$fields = [
		[
			'name'  => 'owner',
			'title' => __('RRDfile Owner'),
			'icon'  => '-'
		],
		[
			'name'  => 'runas_website',
			'title' => __('Website runs as'),
			'icon'  => '-'
		],
		[
			'name'  => 'runas_poller',
			'title' => __('Poller runs as'),
			'icon'  => '-'
		],
		[
			'name'  => 'rrd_folder_writable',
			'title' => __('Is RRA Folder writeable by poller?'),
			'value' => dirname($real_path)
		],
		[
			'name'  => 'rrd_writable',
			'title' => __('Is RRDfile writeable by poller?'),
			'value' => $real_path
		],
		[
			'name'  => 'rrd_exists',
			'title' => __('Does the RRDfile Exist?'),
			'value' => $rrd_exists
		],
		[
			'name'  => 'active',
			'title' => __('Is the Data Source set as Active?'),
			'value' => $active
		],
		[
			'name'  => 'last_result',
			'title' => __('Did the poller receive valid data?'),
			'value' => $poller_data
		],
		[
			'name'  => 'rra_updated',
			'title' => __('Was the RRDfile updated?'),
			'value' => '',
			'icon'  => $rra_updated
		],
		[
			'name'  => 'rra_timestamp',
			'title' => __('First Check TimeStamp'),
			'icon'  => '-'
		],
		[
			'name'  => 'rra_timestamp2',
			'title' => __('Second Check TimeStamp'),
			'icon'  => '-'
		],
		[
			'name'  => 'convert_name',
			'title' => __('Were we able to convert the title?'),
			'value' => htmle(get_data_source_title($check['datasource']))
		],
		[
			'name'  => 'rrd_match',
			'title' => __('Data Source matches the RRDfile?'),
			'value' => ''
		],
		[
			'name'  => 'issue',
			'title' => __('Issues'),
			'value' => $issue,
			'icon'  => $issue_icon
		],
	];

	$debug_status = debug_process_status($id);

	if ($debug_status == 'waiting') {
		html_start_box(__('Data Source Troubleshooter [ Auto Refreshing till Complete ] %s', '<i class="reloadquery ti ti-refresh" data-id="' . $id . '" title="' . __esc('Refresh Now') . '"></i>'), '100%', false, 3, 'center', '');
	} elseif ($debug_status == 'analysis') {
		html_start_box(__('Data Source Troubleshooter [ Auto Refreshing till RRDfile Update ] %s', '<i class="reloadquery ti ti-refresh" data-id="' . $id . '" title="' . __esc('Refresh Now') . '"></i>'), '100%', false, 3, 'center', '');
	} else {
		html_start_box(__('Data Source Troubleshooter [ Analysis Complete! %s ]', '<a href="#" class="rerun linkEditMain" data-id="' . $id . '" style="cursor:pointer;">' . __('Rerun Analysis') . '</a>'), '100%', false, 3, 'center', '');
	}

	html_header(
		[
			__('Check'),
			__('Value'),
			__('Results')
		]
	);

	$i = 1;

	foreach ($fields as $field) {
		$field_name = $field['name'];

		form_alternate_row('line' . $i);
		form_selectable_ecell($field['title'], $i);

		$value = __('<not set>');
		$icon  = '';

		if (array_key_exists($field_name, $check['info'])) {
			$value = $check['info'][$field_name];

			if ($field_name == 'last_result') {
				$icon  = debug_icon_valid_result($value);
			} else {
				$icon  = debug_icon($value);
			}
		}

		if (array_key_exists('value', $field)) {
			$value = $field['value'];
		}

		if (array_key_exists('icon', $field)) {
			$icon = $field['icon'];
		}

		$value_title = htmle((string) $value);

		if (strlen($value) > 100) {
			$value = substr($value, 0, 100);
		}

		form_selectable_ecell($value, $i, '', '', $value_title);
		form_selectable_cell($icon, $i);

		form_end_row();
		$i++;
	}

	html_end_box();

	if ($check_exists > 0 && isset($check['info']['rrd_match_array']['ds']) && $check['info']['rrd_match'] == 0) {
		html_start_box(__('Data Source Repair Recommendations'), '100%', false, 3, 'center', '');

		html_header(
			[
				__('Data Source'),
				__('Issue')
			]
		);

		if (isset($check['info']['rrd_match_array']['ds'])) {
			$i = 0;

			foreach ($check['info']['rrd_match_array']['ds'] as $data_source => $details) {
				form_alternate_row('line2_' . $i, true);
				form_selectable_ecell($data_source, $i);

				$output = '';

				foreach ($details as $attribute => $recommendation) {
					$output .= __('For attribute \'%s\', issue found \'%s\'', $attribute, $recommendation);
				}

				form_selectable_ecell($output, 'line_2' . $i);
				form_end_row();
				$i++;
			}
		}

		html_end_box();

		if (isset($check['info']['rrd_match_array']['tune'])) {
			$path = get_data_source_path($id, true);

			if (is_writeable($path)) {
				html_start_box(__('Repair Steps [ %s ]', '<a href="#" class="repairme linkEditMain" data-id="' . $id . '" style="cursor:pointer;">' . __('Apply Suggested Fixes') . '</a>'), '100%', false, 3, 'center', '');
			} else {
				html_start_box(__('Repair Steps [ Run Fix from Command Line ]', $path), '100%', false, 3, 'center', '');
			}

			html_header([__('Command')]);
			$rrdtool_path = read_config_option('path_rrdtool');

			$i = 0;

			foreach ($check['info']['rrd_match_array']['tune'] as $options) {
				form_alternate_row('line3_' . $i, true);
				form_selectable_ecell($rrdtool_path . ' tune ' . $options, 'line3_' . $i);
				form_end_row();
				$i++;
			}

			html_end_box();
		}
	} else {
		html_start_box(__('Data Source Repair Recommendations'), '100%', false, 3, 'center', '');
		form_alternate_row('line3_0', true);
		form_selectable_cell(__('Waiting on Data Source Check to Complete'), 'line3_0');
		form_end_row();
		html_end_box();
	}

	?>
	<script type='text/javascript'>
	$(function() {
		$('.repairme').click(function(event) {
			event.preventDefault();
			id = $(this).attr('data-id');
			loadUrl({url:'data_debug.php?action=run_repair&id=' + id})
		});

		$('.reloadquery').click(function() {
			id = $(this).attr('data-id');
			loadUrl({url:'data_debug.php?action=view&id=' + id})
		});

		$('.rerun').click(function(event) {
			event.preventDefault();
			id = $(this).attr('data-id');
			loadUrl({url:'data_debug.php?action=run_debug&id=' + id})
		});
	});
	</script>
	<?php
}

function debug_icon_valid_result(mixed $result) : string {
	if ($result === '' || $result === false) {
		return '<i class="ti ti-loader fa-pulse fa-fw"></i>';
	}

	if ($result === '-') {
		return '<i class="ti ti-info-circle-filled"></i>';
	}

	if (is_array($result)) {
		foreach ($result as $variable => $value) {
			if (!prepare_validate_result($value)) {
				return '<i class="ti ti-x" style="color:red"></i>';
			}
		}

		return '<i class="ti ti-check" style="color:green"></i>';
	}

	if (prepare_validate_result($result)) {
		return '<i class="ti ti-check" style="color:green"></i>';
	} else {
		return '<i class="ti ti-x" style="color:red"></i>';
	}
}

function debug_icon(mixed $result) : string {
	if ($result === '' || $result === false) {
		return '<i class="ti ti-loader fa-pulse fa-fw"></i>';
	}

	if ($result === '-') {
		return '<i class="ti ti-info-circle-filled"></i>';
	}

	if ($result === 1 || $result === 'on') {
		return '<i class="ti ti-check" style="color:green"></i>';
	}

	if ($result === 0 || $result === 'off') {
		return '<i class="ti ti-x" style="color:red"></i>';
	}

	return '<i class="ti ti-alert-triangle-filled" style="color:orange"></i>';
}

function create_data_debug_filter(string $session_var) : array {
	global $item_rows, $page_refresh_interval;

	$all     = ['-1' => __('All')];
	$any     = ['-1' => __('Any')];
	$none    = ['0'  => __('None')];
	$deleted = ['-2' => __('Deleted/Invalid')];

	$sites   = array_rekey(
		db_fetch_assoc('SELECT id, name
			FROM sites
			ORDER BY name'),
		'id', 'name'
	);
	$sites   = $any + $sites;

	$profiles = array_rekey(
		db_fetch_assoc('SELECT id, name
			FROM data_source_profiles
			ORDER BY name'),
		'id', 'name'
	);
	$profiles = $all + $profiles;

	$status = [
		'-1' => __('All'),
		'0'  => __('Failed'),
		'1'  => __('Enabled'),
		'2'  => __('Disabled')
	];

	$debugging = [
		'-1' => __('All'),
		'1'  => __('Debugging'),
		'0'  => __('Not Debugging')
	];

	unset($page_refresh_interval[5]);
	unset($page_refresh_interval[10]);
	unset($page_refresh_interval[20]);

	$sql_where  = '';
	$sql_params = [];

	if (isrv('host_id')) {
		$host_id = grv('host_id');
	} elseif (isset($_SESSION[$session_var . '_host_id'])) {
		$host_id = $_SESSION[$session_var . '_host_id'];
	} else {
		$host_id = '-1';
	}

	if ($host_id > 0) {
		// for the templates dropdown
		$sql_where    = 'AND h.id = ?';
		$sql_params[] = $host_id;

		$hostname = db_fetch_cell_prepared('SELECT description
			FROM host
			WHERE id = ?',
			[$host_id]);
	} elseif ($host_id == 0) {
		$host_id  = '0';
		$hostname = __('None');
	} else {
		$host_id  = '-1';
		$hostname = __('Any');
	}

	if (grv('site_id') > 0) {
		$sql_where    = 'AND site_id = ?';
		$sql_params[] = grv('site_id');
	}

	$templates = array_rekey(
		db_fetch_assoc_prepared("SELECT DISTINCT dt.id, dt.name
			FROM data_template AS dt
			INNER JOIN data_template_data AS dtd
			ON dt.id = dtd.data_template_id
			LEFT JOIN data_local AS dl
			ON dtd.local_data_id = dl.id
			LEFT JOIN host AS h
			ON dl.host_id = h.id
			WHERE dtd.local_data_id > 0
			$sql_where
			ORDER BY dt.name",
			$sql_params),
		'id', 'name'
	);

	$templates = $any + $templates;

	return [
		'rows' => [
			[
				'site_id' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Site'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $sites,
					'value'         => '-1'
				],
				'host_id' => [
					'method'        => 'drop_callback',
					'friendly_name' => __('Device'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'sql'           => 'SELECT DISTINCT id, description AS name FROM host ORDER BY description',
					'action'        => 'ajax_hosts',
					'id'            => $host_id,
					'value'         => $hostname,
					'on_change'     => 'applyFilter()'
				],
				'template_id' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Template'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $templates,
					'value'         => '-1'
				]
			],
			[
				'profile' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Profile'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $profiles,
					'value'         => '-1'
				],
				'status' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Status'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $status,
					'value'         => '-1'
				],
				'debug' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Debug'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $debugging,
					'value'         => '-1'
				],
				'refresh' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Refresh'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '30',
					'pageset'       => true,
					'array'         => $page_refresh_interval,
					'value'         => '30'
				]
			],
			[
				'rfilter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_VALIDATE_IS_REGEX,
					'placeholder'    => __('Enter a search term'),
					'size'           => '55',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				],
				'rows' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Attempts'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $item_rows,
					'value'         => '-1'
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply filter to table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset filter to default values'),
			],
			'purge' => [
				'method'  => 'button',
				'display' => __('Purge'),
				'action'  => 'default',
				'title'   => __('Purge User log of all but the last login attempt'),
			],
			'runall' => [
				'method'  => 'button',
				'display' => __('Run All'),
				'action'  => 'default',
				'title'   => __('Run a Debug Check on all Data Sources'),
			]
		],
		'sort' => [
			'sort_column'    => 'name_cache',
			'sort_direction' => 'DESC'
		]
	];
}

function draw_data_debug_filter(bool $render = false) : void {
	$filters = create_data_debug_filter('sess_data_debug');

	if (grv('host_id') > 0) {
		$hostname = db_fetch_cell_prepared('SELECT CONCAT(description, " ( ", hostname, " )")
			FROM host WHERE id = ?',
			[grv('host_id')]);
	} else {
		$hostname = '';
	}

	if (empty($hostname)) {
		if (grv('host_id') == -1) {
			$header = __('All Devices');
		} else {
			$header = __('No Devices');
		}
	} else {
		$header = htmle($hostname);
	}

	// create the page filter
	$pageFilter = new CactiTableFilter($header, 'data_debug.php', 'form_data_debug', 'sess_data_debug');

	$pageFilter->rows_label = __('Data Sources');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}
