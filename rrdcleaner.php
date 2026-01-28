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

require_once('./include/auth.php');
require_once(CACTI_PATH_LIBRARY . '/functions.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');

$ds_actions = [
	1 => __x('dropdown action', 'Delete'),
	3 => __x('dropdown action', 'Archive')
];

$rra_path = CACTI_PATH_RRA . '/';

// set default action
set_default_action();

switch (grv('action')) {
	case 'actions':
		top_header();
		do_rrd();
		// show current table again
		list_rrd();
		bottom_footer();

		break;
	case 'restart':
	case 'rescan':
		top_header();
		// fill files name table
		rrdclean_fill_table();
		list_rrd();
		bottom_footer();

		break;
	case 'remall':
	case 'arcall':
		top_header();
		// fill files name table
		remove_all_rrds();
		list_rrd();
		bottom_footer();

		break;
	default:
		top_header();
		// fill files name table
		list_rrd();
		bottom_footer();

		break;
}

// Fill RRDCleaner's table
function rrdclean_fill_table() : void {
	global $rra_path;

	// suppress warnings
	error_reporting(0);

	// install the rrdclean error handler
	set_error_handler('rrdclean_error_handler');

	// delete old file names table
	rrdclean_truncate_tables();

	get_files();

	clearstatcache();

	// restore original error handler
	restore_error_handler();
}

// Determine the last time the rrdcleaner table was updated
function rrdcleaner_lastupdate() : mixed {
	$status = db_fetch_row("SHOW TABLE STATUS LIKE 'data_source_purge_temp'");

	if (cacti_sizeof($status)) {
		return $status['Update_time'];
	}

	return false;
}

// Delete RRDCleaner's intermediate tables
function rrdclean_truncate_tables() : void {
	// suppress warnings
	error_reporting(0);

	// install the rrdclean error handler
	set_error_handler('rrdclean_error_handler');

	$sql = 'TRUNCATE TABLE `data_source_purge_temp`';
	db_execute($sql);

	// clear old data_source_purge_action table
	$sql = 'TRUNCATE TABLE `data_source_purge_action`';
	db_execute($sql);

	// restore original error handler
	restore_error_handler();
}

// PHP Error Handler
function rrdclean_error_handler(int $errno, string $errmsg, string $filename, int $linenum, array $vars = []) : bool {
	global $debug;

	if ($debug) {
		// define all error types
		$errortype = [
			E_ERROR             => 'Error',
			E_WARNING           => 'Warning',
			E_PARSE             => 'Parsing Error',
			E_NOTICE            => 'Notice',
			E_CORE_ERROR        => 'Core Error',
			E_CORE_WARNING      => 'Core Warning',
			E_COMPILE_ERROR     => 'Compile Error',
			E_COMPILE_WARNING   => 'Compile Warning',
			E_USER_ERROR        => 'User Error',
			E_USER_WARNING      => 'User Warning',
			E_USER_NOTICE       => 'User Notice',
			//			E_STRICT            => 'Runtime Notice',
			//			E_RECOVERABLE_ERROR => 'Catchable Fatal Error'
		];

		// create an error string for the log
		$err = "ERRNO:'" . $errno . "' TYPE:'" . $errortype[$errno] .
			"' MESSAGE:'" . $errmsg . "' IN FILE:'" . $filename .
			"' LINE NO:'" . $linenum . "'";

		// let's ignore some lesser issues
		if (substr_count($errmsg, 'date_default_timezone')) {
			return true;
		}

		if (substr_count($errmsg, 'Only variables')) {
			return true;
		}

		print('PROGERR: ' . $err . "\n"); // print_r($vars); print('</pre>');
	}

	return true;
}

/*
 * Find all unused files from Cacti tables
 * and get file system information for them
 */
function get_files() : void {
	global $rra_path;

	// suppress warnings
	error_reporting(0);

	// install the rrdclean error handler
	set_error_handler('rrdclean_error_handler');

	$files_unused = [];
	$arc_path     = read_config_option('rrd_archive');

	if (substr_count($arc_path, $rra_path)) {
		$archive = true;
		$arcbase = basename($arc_path);
	} else {
		$archive = false;
		$arcbase = '';
	}

	// insert the files into the table from cacti
	db_execute("INSERT INTO data_source_purge_temp
		(local_data_id, data_template_id, name_cache, name, in_cacti)
		SELECT local_data_id, data_template_id, name_cache, replace(data_source_path, '<path_rra>/', '') AS file, '1' AS in_cacti
		FROM data_template_data
		WHERE local_data_id>0
		ON DUPLICATE KEY UPDATE local_data_id=VALUES(local_data_id)");

	$size = 0;
	$sql  = [];

	if (read_config_option('storage_location')) {
		$rrdtool_pipe = rrd_init();
		rrdtool_execute('setcnn timeout off', false, RRDTOOL_OUTPUT_NULL, $rrdtool_pipe, $logopt = 'POLLER');
		$scan = rrdtool_execute('rrd-list', false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, $logopt = 'POLLER');
		rrd_close($rrdtool_pipe);

		if ($scan) {
			$scan  = str_replace("\r\n", "\n", $scan); // Replace \r\n with \n
			$files = explode("\n", $scan);            // Split based on \n

			foreach ($files as $file) {
				[$pathname, $size, $mtime] = explode(',', $file);

				$sql[] = "('" . str_replace($rra_path, '', $pathname) . "', " . $size . ", '" . date('Y-m-d H:i:s', intval($mtime)) . "',0)";
				$size++;

				if ($size == 400) {
					db_execute('INSERT INTO data_source_purge_temp
					(name, size, last_mod, in_cacti)
					VALUES ' . implode(',', $sql) . '
					ON DUPLICATE KEY UPDATE size=VALUES(size), last_mod=VALUES(last_mod)');

					$size = 0;
					$sql  = [];
				}
			}
		}
	} else {
		$dir_iterator = new RecursiveDirectoryIterator($rra_path);
		$iterator     = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);

		foreach ($iterator as $file) {
			if (substr($file->getPathname(), -3) == 'rrd' && !($archive && strstr($file->getPathname(), $arcbase . '/') !== false)) {
				$sql[] = "('" . str_replace($rra_path, '', $file->getPathname()) . "', " . $file->getSize() . ", '" . date('Y-m-d H:i:s', $file->getMTime()) . "',0)";
				$size++;

				if ($size == 400) {
					db_execute('INSERT INTO data_source_purge_temp
					(name, size, last_mod, in_cacti)
					VALUES ' . implode(',', $sql) . '
					ON DUPLICATE KEY UPDATE size=VALUES(size), last_mod=VALUES(last_mod)');

					$size = 0;
					$sql  = [];
				}
			}
		}
	}

	if ($size > 0) {
		db_execute('INSERT INTO data_source_purge_temp
			(name, size, last_mod, in_cacti)
			VALUES ' . implode(',', $sql) . '
			ON DUPLICATE KEY UPDATE size=VALUES(size), last_mod=VALUES(last_mod)');
	}

	// restore original error handler
	restore_error_handler();
}

// Display all rrd file entries
function list_rrd() : void {
	global $item_rows, $ds_actions, $rra_path;

	// suppress warnings
	error_reporting(0);

	// install the rrdclean error handler
	set_error_handler('rrdclean_error_handler');

	draw_rrdcleaner_filter(true);

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$sql_where  = 'WHERE in_cacti = 0';
	$sql_params = [];

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where .= ' AND (rc.name LIKE ? OR rc.name_cache LIKE ? OR dt.name LIKE ?)';

		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
	}

	$secsback = grv('age');

	if (grv('age') == 0) {
		$sql_where .= ' AND last_mod >= ?';
		$sql_params[] = date('Y-m-d H:i:s', time() - (86400 * 7));
	} else {
		$sql_where .= ' AND last_mod <= ?';
		$sql_params[] = date('Y-m-d H:i:s', (time() - $secsback));
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(rc.name)
		FROM data_source_purge_temp AS rc
		LEFT JOIN data_template AS dt
		ON dt.id = rc.data_template_id
		$sql_where",
		$sql_params);

	$total_size = db_fetch_cell_prepared("SELECT ROUND(SUM(size),2)
		FROM data_source_purge_temp AS rc
		LEFT JOIN data_template AS dt
		ON dt.id = rc.data_template_id
		$sql_where",
		$sql_params);
	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$file_list = db_fetch_assoc_prepared("SELECT rc.id, rc.name, rc.last_mod, rc.size,
		rc.name_cache, rc.local_data_id, rc.data_template_id, dt.name AS data_template_name
		FROM data_source_purge_temp AS rc
		LEFT JOIN data_template AS dt
		ON dt.id = rc.data_template_id
		$sql_where
		$sql_order
		$sql_limit",
		$sql_params);

	$nav = html_nav_bar(CACTI_PATH_URL . 'rrdcleaner.php?filter' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 8, __('RRDfiles'), 'page', 'main');

	form_start('rrdcleaner.php');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'name'               => [__('RRDfile Name'), 'ASC'],
		'name_cache'         => [__('DS Name'), 'ASC'],
		'local_data_id'      => [__('DS ID'), 'ASC'],
		'data_template_id'   => [__('Template ID'), 'ASC'],
		'data_template_name' => [__('Template'), 'ASC'],
		'last_mod'           => [__('Last Modified'), 'DESC'],
		'size'               => [__('Size [KB]'), 'DESC']
	];

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($file_list)) {
		foreach ($file_list as $file) {
			$data_template_name = ((empty($file['data_template_name'])) ? '<em>None</em>' : $file['data_template_name']);
			form_alternate_row('line' . $file['id'], true);

			form_selectable_cell(filter_value($file['name'], grv('filter')), $file['id']);
			form_selectable_cell(filter_value($file['name_cache'], grv('filter'), CACTI_PATH_URL . 'data_sources.php?action=ds_edit&id=' . $file['local_data_id']), $file['id']);
			form_selectable_cell($file['local_data_id'] > 0 ? $file['local_data_id'] : '<i>' . __('Deleted') . '</i>', $file['id']);
			form_selectable_cell($file['data_template_id'] > 0 ? $file['data_template_id'] : '<i>' . __('Deleted') . '</i>', $file['id']);
			form_selectable_cell(filter_value($file['data_template_name'], grv('filter')), $file['id']);
			form_selectable_cell($file['last_mod'], $file['id']);
			form_selectable_cell(number_format_i18n($file['size'] / 1024, 2), $file['id']);
			form_checkbox_cell($file['id'], $file['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Unused RRDfiles') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($file_list)) {
		print $nav;
	}

	rrdcleaner_legend($total_size);

	draw_actions_dropdown($ds_actions);

	form_end();

	// restore original error handler
	restore_error_handler();
}

function rrdcleaner_legend(mixed $total_size) : void {
	html_start_box('', '100%', false, 3, 'center', '');
	print '<tr>';
	print '<td><b>' . __('Total Size [MB]:') . '</b> ' . round($total_size / 1024 / 1024, 2) . '</td>';
	print '</tr><tr>';
	print '<td><b>' . __('Last Scan:') . '</b> ' . rrdcleaner_lastupdate() . '</td>';
	print '</tr>';
	html_end_box(false);
}

function remove_all_rrds() : void {
	global $rra_path;

	// suppress warnings
	error_reporting(0);

	// install the rrdclean error handler
	set_error_handler('rrdclean_error_handler');

	$action = gnrv('raction');

	// add to data_source_purge_action table
	db_execute_prepared('INSERT INTO data_source_purge_action
		(name, local_data_id, action)
		SELECT name, local_data_id, ? AS action
		FROM data_source_purge_temp
		WHERE in_cacti = 0
		ON DUPLICATE KEY UPDATE action = VALUES(action)', [$action]);

	// remove the entries from the data_source_purge_temp location
	db_execute('DELETE FROM data_source_purge_temp WHERE in_cacti = 0');

	// restore original error handler
	restore_error_handler();
}

/*
 * Read all checked list items and put them into
 * a temporary table for the poller
 */
function do_rrd() : void {
	global $rra_path;

	// suppress warnings
	error_reporting(0);

	// install the rrdclean error handler
	set_error_handler('rrdclean_error_handler');

	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_(.*)$/', $var, $matches)) {
			// recreate the file name
			$unused_file = db_fetch_row_prepared('SELECT id, name, local_data_id
				FROM data_source_purge_temp
				WHERE id = ?', [$matches[1]]);

			// add to data_source_purge_action table
			$sql = 'INSERT INTO data_source_purge_action
				(name, local_data_id, action)
				VALUES(?, ?, ?)
				ON DUPLICATE KEY UPDATE local_data_id = VALUES(local_data_id)';

			db_execute_prepared($sql, [$unused_file['name'], $unused_file['local_data_id'], gnrv('drp_action')]);

			// drop from data_source_purge table
			db_execute_prepared('DELETE FROM data_source_purge_temp WHERE id = ?', [$matches[1]]);
		}
	}

	// restore original error handler
	restore_error_handler();
}

function create_rrdcleaner_filter() : array {
	global $item_rows;

	$ages = [
		'0'        => '&lt; ' . __('%d Week', 1),
		'604800'   => '&gt; ' . __('%d Week', 1),
		'1209600'  => '&gt; ' . __('%d Weeks', 2),
		'1814400'  => '&gt; ' . __('%d Weeks', 3),
		'2628000'  => '&gt; ' . __('%d Month', 1),
		'5256000'  => '&gt; ' . __('%d Months', 2),
		'10512000' => '&gt; ' . __('%d Months', 4),
		'15768000' => '&gt; ' . __('%d Months', 6),
		'31536000' => '&gt; ' . __('%d Year', 1)
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
				'age' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Time Since Update'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '0',
					'pageset'       => true,
					'array'         => $ages,
					'value'         => '0'
				],
				'rows' => [
					'method'        => 'drop_array',
					'friendly_name' => __('RRDfiles'),
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
			'rescan' => [
				'method'  => 'button',
				'display' => __('Rescan'),
				'action'  => 'default',
				'title'   => __('Rescan RRDfiles and their status'),
				'url'     => 'rrdcleaner.php?action=rescan'
			],
			'remall' => [
				'method'  => 'button',
				'display' => __('Delete All'),
				'action'  => 'default',
				'title'   => __('Delete All RRDfiles'),
				'status'  => __('Scheduling Purging of All Unknowns'),
				'url'     => 'rrdcleaner.php?action=remall&raction=3&clear=1'
			],
			'arcall' => [
				'method'  => 'button',
				'display' => __('Archive All'),
				'action'  => 'default',
				'title'   => __('Archive All RRDfiles'),
				'status'  => __('Scheduling Archiving of All Unknowns'),
				'url'     => 'rrdcleaner.php?action=arcall&raction=3&clear=1'
			]
		],
		'sort' => [
			'sort_column'    => 'name',
			'sort_direction' => 'ASC'
		]
	];
}

function draw_rrdcleaner_filter(bool $render = false) : void {
	$filters = create_rrdcleaner_filter();

	// create the page filter
	$pageFilter = new CactiTableFilter(__('RRDfile Cleaner'), 'rrdcleaner.php', 'form_rrdclean', 'sess_rrdclean');

	$pageFilter->rows_label = __('RRDfiles');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}
