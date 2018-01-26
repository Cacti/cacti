<?php

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

include_once ('./include/auth.php');
include_once ($config['library_path'] . '/functions.php');

$ds_actions = array (
	1 => __x('dropdown action', 'Delete'),
	3 => __x('dropdown action', 'Archive')
);

$rra_path = $config['rra_path'] . '/';

/* set default action */
set_default_action();

if (isset_request_var('rescan')) {
	set_request_var('action', 'restart');
}

switch(get_request_var('action')) {
case 'actions' :
	top_header();
	do_rrd();
	/* show current table again */
	list_rrd();
	bottom_footer();

	break;
case 'restart' :
	top_header();
	/* fill files name table */
	rrdclean_fill_table();
	list_rrd();
	bottom_footer();

	break;
case 'remall' :
case 'arcall' :
	top_header();
	/* fill files name table */
	remove_all_rrds();
	list_rrd();
	bottom_footer();

	break;
default :
	top_header();
	/* fill files name table */
	list_rrd();
	bottom_footer();

	break;
}

/*
 * Fill RRDCleaner's table
 */
function rrdclean_fill_table() {
 	global $config, $rra_path;

 	/* suppress warnings */
 	error_reporting(0);

 	/* install the rrdclean error handler */
 	set_error_handler('rrdclean_error_handler');

	/* delete old file names table */
	rrdclean_truncate_tables();

	get_files();

	clearstatcache();

	/* restore original error handler */
	restore_error_handler();
}

/*
 * Determine the last time the rrdcleaner table was updated
 */
function rrdcleaner_lastupdate() {
	$status = db_fetch_row("SHOW TABLE STATUS LIKE 'data_source_purge_temp'");

	if (sizeof($status)) {
		return $status['Update_time'];
	}
}

/*
 * Delete RRDCleaner's intermediate tables
 */
function rrdclean_truncate_tables() {
	global $config;

	/* suppress warnings */
	error_reporting(0);

	/* install the rrdclean error handler */
	set_error_handler('rrdclean_error_handler');

	$sql = 'TRUNCATE TABLE `data_source_purge_temp`';
	db_execute($sql);

	/* clear old data_source_purge_action table */
	$sql = 'TRUNCATE TABLE `data_source_purge_action`';
	db_execute($sql);

	/* restore original error handler */
	restore_error_handler();
}

/*
 * PHP Error Handler
 */
function rrdclean_error_handler($errno, $errmsg, $filename, $linenum, $vars) {
	global $debug;
	if ($debug) {
		/* define all error types */
		$errortype = array (
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
		#			E_STRICT            => 'Runtime Notice',
		#			E_RECOVERABLE_ERROR => 'Catchable Fatal Error'

		);

		/* create an error string for the log */
		$err = "ERRNO:'"  . $errno   . "' TYPE:'"    . $errortype[$errno] .
		"' MESSAGE:'" . $errmsg  . "' IN FILE:'" . $filename .
		"' LINE NO:'" . $linenum . "'";

		/* let's ignore some lesser issues */
		if (substr_count($errmsg, 'date_default_timezone'))
			return;
		if (substr_count($errmsg, 'Only variables'))
			return;

		print ('PROGERR: ' . $err . "\n"); # print_r($vars); print('</pre>');
	}

	return;
}

/*
 * Find all unused files from Cacti tables
 * and get file system information for them
 */
function get_files() {
	global $config, $rra_path;

	/* suppress warnings */
	error_reporting(0);

	/* install the rrdclean error handler */
	set_error_handler('rrdclean_error_handler');

	$files_unused = array ();
	$arc_path = read_config_option('rrd_archive');
	if (substr_count($arc_path, $rra_path)) {
		$archive = true;
		$arcbase = basename($arc_path);
	} else {
		$archive = false;
		$arcbase = '';
	}

	/* insert the files into the table from cacti */
	db_execute("INSERT INTO data_source_purge_temp
		(local_data_id, data_template_id, name_cache, name, in_cacti)
		SELECT local_data_id, data_template_id, name_cache, replace(data_source_path, '<path_rra>/', '') AS file, '1' AS in_cacti
		FROM data_template_data
		WHERE local_data_id>0
		ON DUPLICATE KEY UPDATE local_data_id=VALUES(local_data_id)");

	$dir_iterator = new RecursiveDirectoryIterator($rra_path);
	$iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);

	$size = 0;
	$sql  = array();
	foreach ($iterator as $file) {
		if (substr($file->getPathname(),-3) == 'rrd' && !($archive && strstr($file->getPathname(), $arcbase . '/') !== false)) {
			$sql[] = "('" . str_replace($rra_path, '', $file->getPathname()) . "', " . $file->getSize() . ", '" . date('Y-m-d H:i:s', $file->getMTime()) . "',0)";
			$size++;

			if ($size == 400) {
				db_execute('INSERT INTO data_source_purge_temp
					(name, size, last_mod, in_cacti)
					VALUES ' . implode(',', $sql) . '
					ON DUPLICATE KEY UPDATE size=VALUES(size), last_mod=VALUES(last_mod)');

				$size = 0;
				$sql  = array();
			}
		}
	}

	if ($size > 0) {
		db_execute('INSERT INTO data_source_purge_temp
			(name, size, last_mod, in_cacti)
			VALUES ' . implode(',', $sql) . '
			ON DUPLICATE KEY UPDATE size=VALUES(size), last_mod=VALUES(last_mod)');
	}

	/* restore original error handler */
	restore_error_handler();
}

/*
 * Display all rrd file entries
 */
function list_rrd() {
	global $config, $item_rows, $ds_actions, $rra_path;

	/* suppress warnings */
	error_reporting(0);

	/* install the rrdclean error handler */
	set_error_handler('rrdclean_error_handler');

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'age' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '0'
			)
	);

	validate_store_request_vars($filters, 'sess_rrdc');

	/* ================= input validation and session storage ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box( __('RRD Cleaner'), '100%', '', '3', 'center', '');
	filter();
	html_end_box();

	$sql_where = 'WHERE in_cacti=0';
	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where .= " AND (rc.name LIKE '%" . get_request_var('filter') . "%' OR rc.name_cache LIKE '%" . get_request_var('filter') . "%' OR dt.name LIKE '%" . get_request_var('filter') . "%')";
	}

	$secsback = get_request_var('age');

	if (get_request_var('age') == 0) {
		$sql_where .= " AND last_mod>='" . date("Y-m-d H:i:s", time()-(86400*7)) . "'";
	} else {
		$sql_where .= " AND last_mod<='" . date("Y-m-d H:i:s", (time() - $secsback)) . "'";
	}

	$total_rows = db_fetch_cell("SELECT COUNT(rc.name)
		FROM data_source_purge_temp AS rc
		LEFT JOIN data_template AS dt
		ON dt.id = rc.data_template_id
		$sql_where");

	$total_size = db_fetch_cell("SELECT ROUND(SUM(size),2)
		FROM data_source_purge_temp AS rc
		LEFT JOIN data_template AS dt
		ON dt.id = rc.data_template_id
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$file_list = db_fetch_assoc("SELECT rc.id, rc.name, rc.last_mod, rc.size,
		rc.name_cache, rc.local_data_id, rc.data_template_id, dt.name AS data_template_name
		FROM data_source_purge_temp AS rc
		LEFT JOIN data_template AS dt
		ON dt.id = rc.data_template_id
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar($config['url_path'] . 'rrdcleaner.php?filter'. get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('RRD Files'), 'page', 'main');

	form_start('rrdcleaner.php');

	print $nav;

	html_start_box('', $width, '', '3', 'center', '');

	$display_text = array(
		'name'               => array( __('RRD File Name'), 'ASC'),
		'name_cache'         => array( __('DS Name'), 'ASC'),
		'local_data_id'      => array( __('DS ID'), 'ASC'),
		'data_template_id'   => array( __('Template ID'), 'ASC'),
		'data_template_name' => array( __('Template'), 'ASC'),
		'last_mod'           => array( __('Last Modified'), 'DESC'),
		'size'               => array( __('Size [KB]'), 'DESC')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (sizeof($file_list)) {
		foreach($file_list as $file) {
			$data_template_name = ((empty($file['data_template_name'])) ? '<em>None</em>' : $file['data_template_name']);
			form_alternate_row('line' . $file['id'], true);
			form_selectable_cell(filter_value($file['name'], get_request_var('filter')), $file['id']);
			form_selectable_cell(filter_value($file['name_cache'], get_request_var('filter'), $config['url_path'] . 'data_sources.php?action=ds_edit&id=' . $file['local_data_id']), $file['id']);
			form_selectable_cell($file['local_data_id'] > 0 ? $file['local_data_id']:'<i>' . __('Deleted') . '</i>', $file['id']);
			form_selectable_cell($file['data_template_id'] > 0 ? $file['data_template_id']: '<i>' . __('Deleted') . '</i>', $file['id']);
			form_selectable_cell(filter_value($file['data_template_name'], get_request_var('filter')), $file['id']);
			form_selectable_cell($file['last_mod'], $file['id']);
			form_selectable_cell(round($file['size']/1024,2), $file['id']);
			form_checkbox_cell($file['id'], $file['id']);
			form_end_row();
		}
	} else {
		print "<tr><td><em>" . __('No unused RRD Files') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (sizeof($file_list)) {
		print $nav;
	}

	rrdcleaner_legend($total_size);

	draw_actions_dropdown($ds_actions);

	form_end();

	/* restore original error handler */
	restore_error_handler();
}

function rrdcleaner_legend($total_size) {
	html_start_box('', '100%', '', '3', 'center', '');
	print '<tr>';
	print '<td><b>' . __('Total Size [MB]:') . '</b> ' . round($total_size/1024/1024,2) . '</td>';
	print '</tr><tr>';
	print '<td><b>' . __('Last Scan:') . '</b> ' . rrdcleaner_lastupdate() . '</td>';
	print '</tr>';
	html_end_box(false);
}

function remove_all_rrds() {
	global $config, $rra_path;

	/* suppress warnings */
	error_reporting(0);

	/* install the rrdclean error handler */
	set_error_handler('rrdclean_error_handler');

	$action = get_nfilter_request_var('raction');

	/* add to data_source_purge_action table */
	db_execute_prepared('INSERT INTO data_source_purge_action
		(name, local_data_id, action)
		SELECT name, local_data_id, ? AS action
		FROM data_source_purge_temp
		WHERE in_cacti = 0
		ON DUPLICATE KEY UPDATE action = VALUES(action)', array($action));

	/* remove the entries from the data_source_purge_temp location */
	db_execute('DELETE FROM data_source_purge_temp WHERE in_cacti = 0');

	/* restore original error handler */
	restore_error_handler();
}

/*
 * Read all checked list items and put them into
 * a temporary table for the poller
 */
function do_rrd() {
	global $config, $rra_path;

	/* suppress warnings */
	error_reporting(0);

	/* install the rrdclean error handler */
	set_error_handler('rrdclean_error_handler');

	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_(.*)$/', $var, $matches)) {
			/* recreate the file name */
			$unused_file = db_fetch_row_prepared('SELECT id, name, local_data_id
				FROM data_source_purge_temp
				WHERE id = ?', array($matches[1]));

			/* add to data_source_purge_action table */
			$sql = "INSERT INTO data_source_purge_action
				(name, local_data_id, action)
				VALUES(?, ?, ?)
				ON DUPLICATE KEY UPDATE local_data_id = VALUES(local_data_id)";

			db_execute_prepared($sql, array($unused_file['name'], $unused_file['local_data_id'], get_nfilter_request_var('drp_action')));

			/* drop from data_source_purge table */
			db_execute_prepared('DELETE FROM data_source_purge_temp WHERE id = ?', array($matches[1]));
		}
	}

	/* restore original error handler */
	restore_error_handler();
}

function filter() {
	global $item_rows;

	?>
	<tr class='even'>
		<td>
			<form id='form_rrdclean' method='get' action='rrdcleaner.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Time Since Update');?>
					</td>
					<td>
						<select id='age' onChange='refreshForm()'>
							<option value='0'   <?php print (get_request_var('age') == '0'   ? ' selected':'');?>>&lt; <?php print __('%d Week', 1);?></option>
							<option value='604800'   <?php print (get_request_var('age') == '604800'   ? ' selected':'');?>>&gt; <?php print __('%d Week', 1);?></option>
							<option value='1209600'  <?php print (get_request_var('age') == '1209600'  ? ' selected':'');?>>&gt; <?php print __('%d Weeks',2);?></option>
							<option value='1814400'  <?php print (get_request_var('age') == '1814400'  ? ' selected':'');?>>&gt; <?php print __('%d Weeks', 3);?></option>
							<option value='2628000'  <?php print (get_request_var('age') == '2628000'  ? ' selected':'');?>>&gt; <?php print __('%d Month', 1);?></option>
							<option value='5256000'  <?php print (get_request_var('age') == '5256000'  ? ' selected':'');?>>&gt; <?php print __('%d Months', 2);?></option>
							<option value='10512000' <?php print (get_request_var('age') == '10512000' ? ' selected':'');?>>&gt; <?php print __('%d Months', 4);?></option>
							<option value='15768000' <?php print (get_request_var('age') == '15768000' ? ' selected':'');?>>&gt; <?php print __('%d Months', 6);?></option>
							<option value='31536000' <?php print (get_request_var('age') == '31536000' ? ' selected':'');?>>&gt; <?php print __('%d Year', 1);?></option>
						</select>
					</td>
					<td>
						<?php print __('RRDfiles');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print '<option value="' . $key . '"'; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input id='go' type='submit' value='<?php print __x('filter: use', 'Go');?>'>
							<input id='clear' type='button' value='<?php print __x('filter: reset', 'Clear');?>'>
							<input id='rescan' type='button' value='<?php print __esc('Rescan');?>' name='rescan'>
						</span>
					</td>
					<td>
						<span>
							<input id='remall' type='button' value='<?php print __esc('Delete All');?>' title='<?php print __esc('Delete All Unknown RRDfiles');?>'>
							<input id='arcall' type='button' value='<?php print __esc('Archive All');?>' title='<?php print __esc('Archive All Unknown RRDfiles');?>'>
						</span>
					</td>
					<td id='text'></td>
				</tr>
			</table>
			</form>
			<script type="text/javascript">
			function refreshForm() {
				strURL = 'rrdcleaner.php?header=false'+
					'&filter='+$('#filter').val()+
					'&age='+$('#age').val()+
					'&rows='+$('#rows').val();
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#form_rrdclean').submit(function() {
					refreshForm();
					return false;
				});

				$('#rows').change(function() {
					refreshForm();
				});

				$('#clear').click(function() {
					strURL = 'rrdcleaner.php?header=false&clear=1';
					loadPageNoHeader(strURL);
				});

				$('#rescan').click(function() {
					$('#text').text('Rebuilding RRDfile Listing');
					pulsate('#text');
					$.get('rrdcleaner.php?header=false&rescan=1&clear=1')
						.done(function(data) {
							checkForLogout(data);

							$('#main').html(data);
							$('#text').text('Finished').fadeOut(2000);
							applySkin();
						})
						.fail(function(data) {
							getPresentHTTPError(data);
						});
				});

				$('#arcall').click(function() {
					$('#text').text('Scheduling Archiving of All Unknowns');
					pulsate('#text');
					$.get('rrdcleaner.php?header=false&action=arcall&raction=3&clear=1')
						.done(function(data) {
							checkForLogout(data);

							$('#main').html(data);
							$('#text').text('Finished').fadeOut(2000);
							applySkin();
						})
						.fail(function(data) {
							getPresentHTTPError(data);
						});
				});

				$('#remall').click(function() {
					$('#text').text('Scheduling Purging of All Unknowns');
					pulsate('#text');
					$.get('rrdcleaner.php?header=false&action=remall&raction=1&clear=1')
						.done(function(data) {
							checkForLogout(data);

							$('#main').html(data);
							$('#text').text('Finished').fadeOut(2000);
							applySkin();
						})
						.fail(function(data) {
							getPresentHTTPError(data);
						});
				});
			});
			</script>
		</td>
	</tr>
	<?php
}

