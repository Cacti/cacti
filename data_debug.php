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

include_once('include/auth.php');
include_once('lib/rrd.php');
include_once('lib/dsdebug.php');

$actions = array(
	1 => __('Run Check'),
	2 => __('Delete Check')
);

ini_set('memory_limit', '-1');

set_default_action();

validate_request_vars();

switch (get_request_var('action')) {
	case 'actions':
		form_actions();

		break;
	case 'run_debug':
		$id = get_filter_request_var('id');

		if ($id > 0) {
			$selected_items = array($id);
			debug_delete($selected_items);
			debug_rerun($selected_items);
			raise_message('rerun', __('Data Source debug started.'), MESSAGE_LEVEL_INFO);
			header('Location: data_debug.php?action=view&id=' . get_filter_request_var('id'));
		} else {
			raise_message('repair_error', __('Data Source debug received an invalid Data Source ID.'), MESSAGE_LEVEL_ERROR);
		}

		break;
	case 'run_repair':
		$id = get_filter_request_var('id');

		if ($id > 0) {
			if (dsdebug_run_repair($id)) {
				raise_message('repair', __('All RRDfile repairs succeeded.'), MESSAGE_LEVEL_INFO);
			} else {
				raise_message('repair', __('One or more RRDfile repairs failed.  See Cacti log for errors.'), MESSAGE_LEVEL_ERROR);
			}

			$selected_items = array($id);

			debug_delete($selected_items);
			debug_rerun($selected_items);

			raise_message('rerun', __('Automatic Data Source debug being rerun after repair.'), MESSAGE_LEVEL_INFO);

			header('Location: data_debug.php?action=view&id=' . get_filter_request_var('id'));
		} else {
			raise_message('repair_error', __('Data Source repair received an invalid Data Source ID.'), MESSAGE_LEVEL_ERROR);
		}

		break;
	case 'view':
		$id = get_filter_request_var('id');

		$debug_status = debug_process_status($id);

		if ($debug_status == 'notset') {
			$selected_items = array($id);
			debug_delete($selected_items);
			debug_rerun($selected_items);
			$debug_status = 'waiting';
		}

		if ($debug_status == 'waiting' || $debug_status == 'analysis') {
			$refresh = array(
				'seconds' => 30,
				'page'    => 'data_debug.php?action=view&id=' . $id,
				'logout'  => 'false'
			);

			set_page_refresh($refresh);
		}

		top_header();
		debug_view();
		bottom_footer();

		break;
	case 'ajax_hosts':
		$sql_where = '';

		if (get_request_var('site_id') > 0) {
			$sql_where = 'site_id = ' . get_request_var('site_id');
		}

		get_allowed_ajax_hosts(true, 'applyFilter', $sql_where);

		break;
	case 'ajax_hosts_noany':
		$sql_where = '';

		if (get_request_var('site_id') > 0) {
			$sql_where = 'site_id = ' . get_request_var('site_id');
		}

		get_allowed_ajax_hosts(false, 'applyFilter', $sql_where);

		break;
	case 'runall':
		debug_runall_filtered();

	default:
		$refresh = array(
			'seconds' => get_request_var('refresh'),
			'page'    => 'data_debug.php',
			'logout'  => 'false'
		);

		set_page_refresh($refresh);

		top_header();
		debug_wizard();
		bottom_footer();

		break;
}

function debug_runall_filtered() {
	$info = array(
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
	);

	$info = serialize($info);

	$sql_where = '';
	$dd_join   = '';
	$now       = time();

	debug_get_filter($sql_where, $dd_join);

	db_execute("DELETE dd
		FROM data_debug AS dd
		INNER JOIN data_local AS dl
		ON dd.datasource = dl.id
		INNER JOIN data_template_data AS dtd
		ON dl.id=dtd.local_data_id
		INNER JOIN data_template AS dt
		ON dt.id=dl.data_template_id
		INNER JOIN host AS h
		ON h.id = dl.host_id
		$sql_where");

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
		array($now, $info, $_SESSION[SESS_USER_ID]));
}

function debug_process_status($id) {
	$status = db_fetch_row_prepared('SELECT done, IFNULL(issue, "waiting") AS issue
		FROM data_debug
		WHERE datasource = ?',
		array($id));

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

function form_actions() {
	global $actions, $assoc_actions;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ================= input validation ================= */

	$selected_items = array();

	if (isset_request_var('save_list')) {
		/* loop through each of the lists selected on the previous page and get more info about them */
		foreach ($_POST as $var=>$val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1], 'chk[1]');
				/* ==================================================== */

				$selected_items[] = $matches[1];
			}
		}

		/* if we are to save this form, instead of display it */
		if (isset_request_var('save_list')) {
			if (get_request_var('drp_action') == '2') { /* delete */
				debug_delete($selected_items);
				header('Location: data_debug.php?debug=-1');
			} elseif (get_request_var('drp_action') == '1') { /* Rerun */
				debug_rerun($selected_items);
				header('Location: data_debug.php?debug=1');
			}

			exit;
		}
	}
}

function debug_rerun($selected_items) {
	$info = array(
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
	);

	$info = serialize($info);

	if (!empty($selected_items)) {
		foreach ($selected_items as $id) {
			$exists = db_fetch_cell_prepared('SELECT id
				FROM data_debug
				WHERE datasource = ?',
				array($id));

			if (!$exists) {
				$save               = array();
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
					array($stime, $info, $exists));
			}
		}
	}
}

function debug_delete($selected_items) {
	if (!empty($selected_items)) {
		foreach ($selected_items as $id) {
			db_execute_prepared('DELETE
				FROM data_debug
				WHERE datasource = ?',
				array($id));
		}
	}
}

function validate_request_vars() {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'refresh' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '60'
			),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'rfilter' => array(
			'filter'  => FILTER_VALIDATE_IS_REGEX,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'name_cache',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'site_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true,
			),
		'host_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true,
			),
		'template_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true,
			),
		'status' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'profile' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'debug' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true,
			)
	);

	validate_store_request_vars($filters, 'sess_dd');
	/* ================= input validation ================= */
}

function debug_get_filter(&$sql_where, &$dd_join) {
	/* form the 'where' clause for our main sql query */
	if (get_request_var('rfilter') != '') {
		$sql_where = "WHERE (dtd.name_cache RLIKE '" . get_request_var('rfilter') . "'" .
			" OR dtd.local_data_id RLIKE '" . get_request_var('rfilter') . "'" .
			" OR dt.name RLIKE '" . get_request_var('rfilter') . "')";
	} else {
		$sql_where = '';
	}

	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	} elseif (isempty_request_var('host_id')) {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' (dl.host_id=0 OR dl.host_id IS NULL)';
	} elseif (!isempty_request_var('host_id')) {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' dl.host_id=' . get_request_var('host_id');
	}

	if (get_request_var('site_id') == '-1') {
		/* Show all items */
	} elseif (isempty_request_var('site_id')) {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' (h.site_id=0 OR h.site_id IS NULL)';
	} elseif (!isempty_request_var('site_id')) {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' h.site_id=' . get_request_var('site_id');
	}

	if (get_request_var('template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' dtd.data_template_id=0';
	} elseif (!isempty_request_var('template_id')) {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' dtd.data_template_id=' . get_request_var('template_id');
	}

	if (get_request_var('profile') == '-1') {
		/* Show all items */
	} else {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' dtd.data_source_profile_id=' . get_request_var('profile');
	}

	if (get_request_var('status') == '-1') {
		/* Show all items */
	} elseif (get_request_var('status') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' dd.issue != ""';
	} elseif (get_request_var('status') == '1') {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' dtd.active="on"';
	} else {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' dtd.active=""';
	}

	if (get_request_var('debug') == '-1') {
		$dd_join = 'LEFT';
	} elseif (get_request_var('debug') == 0) {
		$dd_join = 'LEFT';
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' dd.datasource IS NULL';
	} else {
		$dd_join = 'INNER';
	}

	// Get the SQL Where and Join
}

function debug_wizard() {
	global $actions;

	$display_text = array(
		'name_cache' => array(
			'display' => __('Data Source'),
			'sort'    => 'ASC',
			'tip'     => __('The Data Source to Debug'),
			),
		'username' => array(
			'display' => __('User'),
			'sort'    => 'ASC',
			'tip'     => __('The User who requested the Debug.'),
			),
		'started' => array(
			'display' => __('Started'),
			'sort'    => 'DESC',
			'align'   => 'right',
			'tip'     => __('The Date that the Debug was Started.'),
			),
		'local_data_id' => array(
			'display' => __('ID'),
			'sort'    => 'ASC',
			'align'   => 'right',
			'tip'     => __('The Data Source internal ID.'),
			),
		'nosort1' => array(
			'display' => __('Status'),
			'sort'    => 'ASC',
			'align'   => 'center',
			'tip'     => __('The Status of the Data Source Debug Check.'),
			),
		'nosort2' => array(
			'display' => __('Writable'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('Determines if the Data Collector or the Web Site have Write access.'),
		),
		'nosort3' => array(
			'display' => __('Exists'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('Determines if the Data Source is located in the Poller Cache.'),
		),
		'nosort4' => array(
			'display' => __('Active'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('Determines if the Data Source is Enabled.'),
		),
		'nosort5' => array(
			'display' => __('RRD Match'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('Determines if the RRDfile matches the Data Source Template.'),
		),
		'nosort6' => array(
			'display' => __('Valid Data'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('Determines if the RRDfile has been getting good recent Data.'),
		),
		'nosort7' => array(
			'display' => __('RRD Updated'),
			'align'   => 'center',
			'sort'    => '',
			'tip'     => __('Determines if the RRDfile has been written to properly.'),
		),
		'nosort8' => array(
			'display' => __('Issues'),
			'align'   => 'right',
			'sort'    => '',
			'tip'     => __('Summary of issues found for the Data Source.'),
		)
	);

	if (isset_request_var('purge')) {
		db_execute('TRUNCATE TABLE data_debug');
	}

	/* fill in the current date for printing in the log */
	if (defined('CACTI_DATE_TIME_FORMAT')) {
		$datefmt = CACTI_DATE_TIME_FORMAT;
	} else {
		$datefmt = 'Y-m-d H:i:s';
	}

	data_debug_filter();

	$total_rows = 0;
	$checks     = array();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where = '';
	$dd_join   = '';

	debug_get_filter($sql_where, $dd_join);

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id=dtd.local_data_id
		INNER JOIN data_template AS dt
		ON dt.id=dl.data_template_id
		INNER JOIN host AS h
		ON h.id = dl.host_id
		$dd_join JOIN data_debug AS dd
		ON dl.id = dd.datasource
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$checks = db_fetch_assoc("SELECT dd.*, dtd.local_data_id, dtd.name_cache, u.username
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
		$sql_limit");

	$nav = html_nav_bar('data_debug.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Data Sources'), 'page', 'main');

	form_start('data_debug.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

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
				$issues = array();
			}

			$iline  = '';

			if (cacti_sizeof($issues)) {
				$iline = $issues[0];
			}

			$issue_title = implode('<br/>',$issues);

			form_alternate_row('line' . $check['local_data_id']);

			form_selectable_cell(filter_value(title_trim($check['name_cache'], read_config_option('max_title_length')), get_request_var('rfilter'), 'data_debug.php?action=view&id=' . $check['local_data_id']), $check['local_data_id']);

			if (!empty($check['datasource'])) {
				form_selectable_ecell($check['username'], $check['local_data_id']);
				form_selectable_cell(date($datefmt, $check['started']), $check['local_data_id'], '', 'right');
				form_selectable_cell($check['local_data_id'], $check['local_data_id'], '', 'right');
				form_selectable_cell(debug_icon(($check['done'] ? ($iline != '' ? 'off' : 'on'):'')), $check['local_data_id'], '', 'center');
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

				form_selectable_cell('<a class=\'linkEditMain\' href=\'#\' title="' . html_escape($issue_title) . '">' . ($iline != '' ? __esc('Issues') : __esc('N/A')) . '</a>', $check['local_data_id'], '', 'right');
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
		print "<tr><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Checks') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($checks)) {
		print $nav;
	}

	form_hidden_box('save_list', '1', '');

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();
}

function debug_view() {
	global $config, $refresh;

	$refresh = 60;

	$id = get_filter_request_var('id');

	$check = db_fetch_row_prepared('SELECT *
		FROM data_debug
		WHERE datasource = ?',
		array($id));

	$check_exists = cacti_sizeof($check);

	if (isset($check) && is_array($check)) {
		$check['info'] = cacti_unserialize($check['info']);
	}

	$dtd = db_fetch_row_prepared('SELECT *
		FROM data_template_data
		WHERE local_data_id = ?',
		array($id));

	if (cacti_sizeof($dtd)) {
		$real_path = str_replace('<path_rra>', CACTI_PATH_RRA, $dtd['data_source_path']);
	} else {
		$real_path = __('Not Found');
	}

	$poller_data = array();

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

	$fields = array(
		array(
			'name'  => 'owner',
			'title' => __('RRDfile Owner'),
			'icon'  => '-'
		),
		array(
			'name'  => 'runas_website',
			'title' => __('Website runs as'),
			'icon'  => '-'
		),
		array(
			'name'  => 'runas_poller',
			'title' => __('Poller runs as'),
			'icon'  => '-'
		),
		array(
			'name'  => 'rrd_folder_writable',
			'title' => __('Is RRA Folder writeable by poller?'),
			'value' => dirname($real_path)
		),
		array(
			'name'  => 'rrd_writable',
			'title' => __('Is RRDfile writeable by poller?'),
			'value' => $real_path
		),
		array(
			'name'  => 'rrd_exists',
			'title' => __('Does the RRDfile Exist?'),
			'value' => $rrd_exists
		),
		array(
			'name'  => 'active',
			'title' => __('Is the Data Source set as Active?'),
			'value' => $active
		),
		array(
			'name'  => 'last_result',
			'title' => __('Did the poller receive valid data?'),
			'value' => $poller_data
		),
		array(
			'name'  => 'rra_updated',
			'title' => __('Was the RRDfile updated?'),
			'value' => '',
			'icon'  => $rra_updated
		),
		array(
			'name'  => 'rra_timestamp',
			'title' => __('First Check TimeStamp'),
			'icon'  => '-'
		),
		array(
			'name'  => 'rra_timestamp2',
			'title' => __('Second Check TimeStamp'),
			'icon'  => '-'
		),
		array(
			'name'  => 'convert_name',
			'title' => __('Were we able to convert the title?'),
			'value' => html_escape(get_data_source_title($check['datasource']))
		),
		array(
			'name'  => 'rrd_match',
			'title' => __('Data Source matches the RRDfile?'),
			'value' => ''
		),
		array(
			'name'  => 'issue',
			'title' => __('Issues'),
			'value' => $issue,
			'icon'  => $issue_icon
		),
	);

	$debug_status = debug_process_status($id);

	if ($debug_status == 'waiting') {
		html_start_box(__('Data Source Troubleshooter [ Auto Refreshing till Complete ] %s', '<i class="reloadquery fa fa-sync" data-id="' . $id . '" title="' . __esc('Refresh Now') . '"></i>'), '100%', '', '3', 'center', '');
	} elseif ($debug_status == 'analysis') {
		html_start_box(__('Data Source Troubleshooter [ Auto Refreshing till RRDfile Update ] %s', '<i class="reloadquery fa fa-sync" data-id="' . $id . '" title="' . __esc('Refresh Now') . '"></i>'), '100%', '', '3', 'center', '');
	} else {
		html_start_box(__('Data Source Troubleshooter [ Analysis Complete! %s ]', '<a href="#" class="rerun linkEditMain" data-id="' . $id . '" style="cursor:pointer;">' . __('Rerun Analysis') . '</a>'), '100%', '', '3', 'center', '');
	}

	html_header(
		array(
			__('Check'),
			__('Value'),
			__('Results')
		)
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
			$value = html_escape($field['value']);
		}

		if (array_key_exists('icon', $field)) {
			$icon = $field['icon'];
		}

		$value_title = $value;

		if (strlen($value) > 100) {
			$value = substr($value, 0, 100);
		}

		form_selectable_cell($value, $i, '', '', $value_title);
		form_selectable_cell($icon, $i);

		form_end_row();
		$i++;
	}

	html_end_box();

	if ($check_exists > 0 && isset($check['info']['rrd_match_array']['ds']) && $check['info']['rrd_match'] == 0) {
		html_start_box(__('Data Source Repair Recommendations'), '', '', '2', 'center', '');

		html_header(
			array(
				__('Data Source'),
				__('Issue')
			)
		);

		if (isset($check['info']['rrd_match_array']['ds'])) {
			$i = 0;

			foreach ($check['info']['rrd_match_array']['ds'] as $data_source => $details) {
				form_alternate_row('line2_' . $i, true);
				form_selectable_cell($data_source, $i);

				$output = '';

				foreach ($details as $attribute => $recommendation) {
					$output .= __('For attribute \'%s\', issue found \'%s\'', $attribute, $recommendation);
				}

				form_selectable_cell($output, 'line_2' . $i);
				form_end_row();
				$i++;
			}
		}

		html_end_box();

		if (isset($check['info']['rrd_match_array']['tune'])) {
			$path = get_data_source_path($id, true);

			if (is_writeable($path)) {
				html_start_box(__('Repair Steps [ %s ]', '<a href="#" class="repairme linkEditMain" data-id="' . $id . '" style="cursor:pointer;">' . __('Apply Suggested Fixes') . '</a>'), '', '', '2', 'center', '');
			} else {
				html_start_box(__('Repair Steps [ Run Fix from Command Line ]', $path), '', '', '2', 'center', '');
			}

			html_header(array(__('Command')));
			$rrdtool_path = read_config_option('path_rrdtool');

			$i = 0;

			foreach ($check['info']['rrd_match_array']['tune'] as $options) {
				form_alternate_row('line3_' . $i, true);
				form_selectable_cell($rrdtool_path . ' tune ' . $options, 'line3_' . $i);
				form_end_row();
				$i++;
			}

			html_end_box();
		}
	} else {
		html_start_box(__('Data Source Repair Recommendations'), '', '', '2', 'center', '');
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

function debug_icon_valid_result($result) {
	if ($result === '' || $result === false) {
		return '<i class="fa fa-spinner fa-pulse fa-fw"></i>';
	}

	if ($result === '-') {
		return '<i class="fa fa-info-circle"></i>';
	}

	if (is_array($result)) {
		foreach ($result as $variable => $value) {
			if (!prepare_validate_result($value)) {
				return '<i class="fa fa-times" style="color:red"></i>';
			}
		}

		return '<i class="fa fa-check" style="color:green"></i>';
	}

	if (prepare_validate_result($result)) {
		return '<i class="fa fa-check" style="color:green"></i>';
	} else {
		return '<i class="fa fa-times" style="color:red"></i>';
	}
}

function debug_icon($result) {
	if ($result === '' || $result === false) {
		return '<i class="fa fa-spinner fa-pulse fa-fw"></i>';
	}

	if ($result === '-') {
		return '<i class="fa fa-info-circle"></i>';
	}

	if ($result === 1 || $result === 'on') {
		return '<i class="fa fa-check" style="color:green"></i>';
	}

	if ($result === 0 || $result === 'off') {
		return '<i class="fa fa-times" style="color:red"></i>';
	}

	return '<i class="fa fa-exclamation-triangle" style="color:orange"></i>';
}

function data_debug_filter() {
	global $item_rows, $page_refresh_interval;

	if (get_request_var('site_id') > 0) {
		$host_where = 'site_id = ' . get_request_var('site_id');
	} else {
		$host_where = '';
	}

	if (get_request_var('host_id') > 0) {
		$hostname = db_fetch_cell_prepared('SELECT CONCAT(description, " ( ", hostname, " )") FROM host WHERE id = ?', array(get_request_var('host_id')));
	} else {
		$hostname = '';
	}

	html_start_box(__('Data Source Troubleshooter [ %s ]', (empty($hostname) ? (get_request_var('host_id') == -1 ? __('All Devices') :__('No Device')) : html_escape($hostname))), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_data_debug' name='form_data_debug' action='data_debug.php'>
			<table class='filterTable'>
				<tr>
					<?php print html_site_filter(get_request_var('site_id'));?>
					<?php print html_host_filter(get_request_var('host_id'), 'applyFilter', $host_where);?>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='template_id' name='template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php

							$templates = db_fetch_assoc('SELECT DISTINCT data_template.id, data_template.name
								FROM data_template
								INNER JOIN data_template_data
								ON data_template.id = data_template_data.data_template_id
								WHERE data_template_data.local_data_id > 0
								ORDER BY data_template.name');

	if (cacti_sizeof($templates) > 0) {
		foreach ($templates as $template) {
			print "<option value='" . $template['id'] . "'";

			if (get_request_var('template_id') == $template['id']) {
				print ' selected';
			} print '>' . html_escape($template['name']) . '</option>';
		}
	}
	?>

						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc('Purge');?>' title='<?php print __esc('Delete All Checks');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='runall' value='<?php print __esc('Run All');?>' title='<?php print __esc('Run checks on currently filtered Data Sources and preserve other results');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Profile');?>
					</td>
					<td>
						<select id='profile' name='profile' onChange='applyFilter()'>
							<option value='-1'<?php print(get_request_var('profile') == '-1' ? ' selected>':'>') . __('All');?></option>
							<?php
	$profiles = array_rekey(db_fetch_assoc('SELECT id, name FROM data_source_profiles ORDER BY name'), 'id', 'name');

	if (cacti_sizeof($profiles)) {
		foreach ($profiles as $key => $value) {
			print "<option value='" . $key . "'";

			if (get_request_var('profile') == $key) {
				print ' selected';
			} print '>' . html_escape($value) . '</option>';
		}
	}
	?>
						</select>
					</td>
					<td>
						<?php print __('Status');?>
					</td>
					<td>
						<select id='status' name='status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('status') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='0'<?php if (get_request_var('status') == '0') {?> selected<?php }?>><?php print __('Failed');?></option>
							<option value='1'<?php if (get_request_var('status') == '1') {?> selected<?php }?>><?php print __('Enabled');?></option>
							<option value='2'<?php if (get_request_var('status') == '2') {?> selected<?php }?>><?php print __('Disabled');?></option>
						</select>
					</td>
					<td>
						<?php print __('Debugging');?>
					</td>
					<td>
						<select id='debug' name='debug' onChange='applyFilter()'>
							<option value='-1'<?php print(get_request_var('debug') == '-1' ? ' selected>':'>') . __('All');?></option>
							<option value='1'<?php print(get_request_var('debug') == '1' ? ' selected>':'>') . __('Debugging');?></option>
							<option value='0'<?php print(get_request_var('debug') == '0' ? ' selected>':'>') . __('Not Debugging');?></option>
						</select>
					</td>
					<td>
						<?php print __('Refresh');?>
					</td>
					<td>
						<select id='refresh' name='refresh' onChange='applyFilter()'>
							<?php
	unset($page_refresh_interval[5]);
	unset($page_refresh_interval[10]);
	unset($page_refresh_interval[20]);

	foreach ($page_refresh_interval as $seconds => $display_text) {
		print "<option value='" . $seconds . "'";

		if (get_request_var('refresh') == $seconds) {
			print ' selected';
		}
		print '>' . $display_text . '</option>';
	}
	?>
						</select>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='rfilter' size='30' value='<?php print html_escape_request_var('rfilter');?>' onChange='applyFilter()'>
					</td>
					<td>
						<?php print __('Data Sources');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
	if (cacti_sizeof($item_rows) > 0) {
		foreach ($item_rows as $key => $value) {
			print "<option value='" . $key . "'";

			if (get_request_var('rows') == $key) {
				print ' selected';
			} print '>' . html_escape($value) . '</option>';
		}
	}
	?>
						</select>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL  = 'data_debug.php' +
				'?host_id=' + $('#host_id').val() +
				'&site_id=' + $('#site_id').val() +
				'&rfilter=' + base64_encode($('#rfilter').val()) +
				'&rows=' + $('#rows').val() +
				'&status=' + $('#status').val() +
				'&refresh=' + $('#refresh').val() +
				'&profile=' + $('#profile').val() +
				'&debug=' + $('#debug').val() +
				'&template_id=' + $('#template_id').val();
			loadUrl({url:strURL})
		}

		function clearFilter() {
			strURL = 'data_debug.php?clear=1';
			loadUrl({url:strURL})
		}

		function purgeFilter() {
			strURL = 'data_debug.php?purge=1&debug=-1';
			loadUrl({url:strURL})
		}

		function runallFilter() {
			strURL = 'data_debug.php?action=runall&debug=-1';
			loadUrl({url:strURL});
		}

		$(function() {
			$('#go').click(function() {
				applyFilter()
			});

			$('#clear').click(function() {
				clearFilter()
			});

			$('#purge').click(function() {
				purgeFilter()
			});

			$('#runall').click(function() {
				runallFilter()
			});

			$('#form_data_debug').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
		</td>
	</tr>
	<?php

	html_end_box();
}
