<?php

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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

include_once ('./include/auth.php');
include_once ($config['library_path'] . '/functions.php');

define('MAX_DISPLAY_PAGES', 21);

$ds_actions = array (
	1 => 'Delete',
	3 => 'Archive'
);

$rra_path = $config['rra_path'] . '/';

/* set default action */
if (!isset ($_REQUEST['action'])) {
	$_REQUEST['action'] = '';
}

if (isset($_REQUEST['rescan'])) {
	$_REQUEST['action'] = 'restart';
}

switch ($_REQUEST['action']) {
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
	}else{
		$archive = false;
		$arcbase = '';
	}

	/* insert the files into the table from cacti */
	db_execute("INSERT INTO data_source_purge_temp
		(local_data_id, data_template_id, name_cache, name, in_cacti) 
		SELECT local_data_id, data_template_id, name_cache, replace(data_source_path, '<path_rra>/', '') AS file, '1' AS in_cacti
		FROM data_template_data
		WHERE local_data_id>0
		ON DUPLICATE KEY UPDATE local_data_id = VALUES(local_data_id)");

	$dir_iterator = new RecursiveDirectoryIterator($rra_path);
	$iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);

	$size = 0;
	$sql  = array();
	foreach ($iterator as $file) {
		if (substr($file->getPathname(),-3) == 'rrd' && !($archive && strstr($file->getPathname(), $arcbase . '/') !== false)) {
			$sql[] = "('" . str_replace($rra_path, '', $file->getPathname()) . "', " . $file->getSize() . ", '" . date('Y-m-d H:i:s', $file->getMTime()) . "',0)";
			$size++;

			if ($size == 1000) {
				db_execute('INSERT INTO data_source_purge_temp 
					(name, size, last_mod, in_cacti) 
					VALUES ' . implode(',', $sql) . ' 
					ON DUPLICATE KEY UPDATE siz e =VALUES(size), last_mod = VALUES(last_mod)');
	
				$size = 0;
				$sql  = array();
			}
		}
	}

	if ($size > 0) {
		db_execute('INSERT INTO data_source_purge_temp
			(name, size, last_mod, in_cacti) 
			VALUES ' . implode(',', $sql) . ' 
			ON DUPLICATE KEY UPDATE size = VALUES(size), last_mod = VALUES(last_mod)');
	}

	/* restore original error handler */
	restore_error_handler();
}

/*
 * Display all rrd file entries
 */
function list_rrd() {
	global $config, $item_rows, $ds_actions, $rra_path, $hash_version_codes;
 
	/* suppress warnings */
	error_reporting(0);

	/* install the rrdclean error handler */
	set_error_handler('rrdclean_error_handler');

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('age'));
	input_validate_input_number(get_request_var_request('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset ($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* clean up sort_column string */
	if (isset ($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	}

	/* clean up sort_direction string */
	if (isset ($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset ($_REQUEST['clear_x'])) {
		kill_session_var('sess_rrdclean_current_page');
		kill_session_var('sess_rrdclean_age');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_rrdclean_filter');
		kill_session_var('sess_rrdclean_sort_column');
		kill_session_var('sess_rrdclean_sort_direction');

		unset ($_REQUEST['page']);
		unset ($_REQUEST['age']);
		unset ($_REQUEST['rows']);
		unset ($_REQUEST['filter']);
		unset ($_REQUEST['sort_column']);
		unset ($_REQUEST['sort_direction']);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_rrdclean_current_page', '1');
	load_current_session_value('age', 'sess_rrdclean_current_age', '1');
	load_current_session_value('rows', 'sess_rrdclean_rows', read_config_option('num_rows_table'));
	load_current_session_value('filter', 'sess_rrdclean_filter', '');
	load_current_session_value('sort_column', 'sess_rrdclean_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_rrdclean_sort_direction', 'ASC');

	$width = '100%';
	if (isset ($hash_version_codes[$config['cacti_version']])) {
		if ($hash_version_codes[$config['cacti_version']] > 13) {
			$width = '100%';
		}
	}

	html_start_box('<strong>RRD Cleaner</strong>', $width, '', '3', 'center', '');
	filter();
	html_end_box();

	$sql_where = 'WHERE in_cacti=0';
	/* form the 'where' clause for our main sql query */
	if ($_REQUEST['filter'] != '') {
		$sql_where .= " AND (rc.name LIKE '%" . $_REQUEST['filter'] . "%' OR rc.name_cache LIKE '%" . $_REQUEST['filter'] . "%' OR dt.name LIKE '%" . $_REQUEST['filter'] . "%')";
	}

	$secsback = $_REQUEST['age'];

	if ($REQUEST['age'] != 0) {
		$sql_where .= " AND last_mod>='" . date("Y-m-d H:i:s", time()-(86400*7)) . "'";
	}else{
		$sql_where .= " AND last_mod<='" . date("Y-m-d H:i:s", (time() - $secsback)) . "'";
	}

	print "<form action='rrdcleaner.php' method='post'>\n";

	html_start_box('', $width, '', '3', 'center', '');

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

	$file_list = db_fetch_assoc("SELECT rc.id, rc.name, rc.last_mod, rc.size, 
		rc.name_cache, rc.local_data_id, rc.data_template_id, dt.name AS data_template_name
		FROM data_source_purge_temp AS rc
		LEFT JOIN data_template AS dt
		ON dt.id = rc.data_template_id
		$sql_where 
		ORDER BY " . $_REQUEST['sort_column'] . ' ' . $_REQUEST['sort_direction'] . '
		LIMIT ' . ($_REQUEST['rows'] * ($_REQUEST['page'] - 1)) . ',' . $_REQUEST['rows']);

	$nav = html_nav_bar($config['url_path'] . 'rrdcleaner.php?filter'. get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 8, 'RRD Files', 'page', 'main');

	print $nav;

	$display_text = array(
		'name'               => array('RRD File Name', 'ASC'),
		'name_cache'         => array('DS Name', 'ASC'),
		'local_data_id'      => array('DS ID', 'ASC'),
		'data_template_id'   => array('Template ID', 'ASC'),
		'data_template_name' => array('Template', 'ASC'),
		'last_mod'           => array('Last Modified', 'DESC'),
		'size'               => array('Size [KB]', 'DESC')
	);

	html_header_sort_checkbox($display_text, $_REQUEST['sort_column'], $_REQUEST['sort_direction'], false);

	if (sizeof($file_list)) {
		foreach($file_list as $file) {
			$data_template_name = ((empty($file['data_template_name'])) ? '<em>None</em>' : $file['data_template_name']);
			form_alternate_row('line' . $file['id'], true);
			form_selectable_cell((($_REQUEST['filter'] != '') ? eregi_replace('(' . preg_quote($_REQUEST['filter']) . ')', "<span class='filteredValue'>\\1</span>", $file['name']) : $file['name']) . '</a>', $file['id']);
			form_selectable_cell(($file['local_data_id'] != 0) ? "<a class='linkEditMain' href='../../data_sources.php?action=ds_edit&id=" . $file['local_data_id'] . "'>" . (($_REQUEST['filter'] != '') ? eregi_replace('(' . preg_quote($_REQUEST['filter']) . ')', "<span class='filteredValue'>\\1</span>", title_trim(htmlentities($file['name_cache']), read_config_option('max_title_length'))) : title_trim(htmlentities($file['name_cache']), read_config_option('max_title_length'))) . '</a>' : '<i>Deleted</i>', $file['id']);
			form_selectable_cell($file['local_data_id'] > 0 ? $file['local_data_id']:'<i>Deleted</i>', $file['id']);
			form_selectable_cell($file['data_template_id'] > 0 ? $file['data_template_id']:'<i>Deleted</i>', $file['id']);
			form_selectable_cell($file['data_template_id'] > 0 ? ($_REQUEST['filter'] != '' ? eregi_replace('(' . preg_quote($_REQUEST['filter']) . ')', "<span class='filteredValue'>\\1</span>", $file['data_template_name']) . '</a>': $file['data_template_name']):'<i>Deleted</i>', $file['id']);
			form_selectable_cell($file['last_mod'], $file['id']);
			form_selectable_cell($file['size'], $file['id']);
			form_checkbox_cell($file['id'], $file['id']);
			form_end_row();
			$i++;
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	} else {
		print "<tr><td><em>No unused RRD Files</em></td></tr>\n";
	}

	html_end_box(false);

	rrdcleaner_legend($total_size);

	draw_actions_dropdown($ds_actions);

	print "</form>\n";

	/* restore original error handler */
	restore_error_handler();
}

function rrdcleaner_legend($total_size) {
	html_start_box('', '100%', '', '3', 'center', '');
	print '<tr>';
	print '<td><b>Total Size [mb]:</b> ' . round($total_size,2) . '</td>';
	print '</tr><tr>';
	print '<td><b>Last Scan:</b> ' . rrdcleaner_lastupdate() . '</td>';
	print '</tr>';
	html_end_box(false);
}

function remove_all_rrds() {
	global $config, $rra_path;

	/* suppress warnings */
	error_reporting(0);

	/* install the rrdclean error handler */
	set_error_handler('rrdclean_error_handler');

	$action = $_REQUEST['raction'];

	/* add to data_source_purge_action table */
	db_execute("INSERT INTO data_source_purge_action SELECT '' AS id, name, local_data_id, '$action' AS action FROM data_source_purge_temp WHERE in_cacti = 0 ON DUPLICATE KEY UPDATE action = VALUES(action)");

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

	while (list ($var, $val) = each($_POST)) {
		if (ereg('^chk_(.*)$', $var, $matches)) {
			/* recreate the file name */
			$unused_file = db_fetch_row_prepared('SELECT id, name, local_data_id 
				FROM data_source_purge_temp
				WHERE id = ?', array($matches[1]));

			/* add to data_source_purge_action table */
			$sql = "INSERT INTO data_source_purge_action VALUES('', ?, ?, ?) ON DUPLICATE KEY UPDATE local_data_id = VALUES(local_data_id)";
			db_execute_prepared($sql, array($unused_file['name'], $unused_file['local_data_id'], $_POST['drp_action']));

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
			<form id='form_rrdclean' name='form_rrdclean' method='get' action='rrdcleaner.php'>
			<table cellpadding='2' cellspacing='0'>
				<tr>
					<td width='55'>
						Search:
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print $_REQUEST['filter'];?>'>
					</td>
					<td style='white-space: nowrap;'>
						Time Since Update:
					</td>
					<td>
						<select id='age' name='age' onChange='refreshForm()'>
							<option value='0'   <?php print ($_REQUEST['age'] == '0'   ? ' selected':'');?>>&lt; 1 Week</option>
							<option value='604800'   <?php print ($_REQUEST['age'] == '604800'   ? ' selected':'');?>>&gt; 1 Week</option>
							<option value='1209600'  <?php print ($_REQUEST['age'] == '1209600'  ? ' selected':'');?>>&gt; 2 Weeks</option>
							<option value='1814400'  <?php print ($_REQUEST['age'] == '1814400'  ? ' selected':'');?>>&gt; 3 Weeks</option>
							<option value='2628000'  <?php print ($_REQUEST['age'] == '2628000'  ? ' selected':'');?>>&gt; 1 Month</option>
							<option value='5256000'  <?php print ($_REQUEST['age'] == '5256000'  ? ' selected':'');?>>&gt; 2 Months</option>
							<option value='10512000' <?php print ($_REQUEST['age'] == '10512000' ? ' selected':'');?>>&gt; 4 Months</option>
							<option value='15768000' <?php print ($_REQUEST['age'] == '15768000' ? ' selected':'');?>>&gt; 6 Months</option>
							<option value='31536000' <?php print ($_REQUEST['age'] == '31536000' ? ' selected':'');?>>&gt; 1 Year</option>
						</select>
					</td>
					<td>
						RRDfiles:
					</td>
					<td>
						<select id='rows' name='rows'>
						<?php
						if (sizeof($item_rows) > 0) {
						foreach ($item_rows as $key => $value) {
							print '<option value="' . $key . '"'; if ($_REQUEST['rows'] == $key) { print ' selected'; } print '>' . $value . "</option>\n";
						}
						}
						?>
						</select>
					</td>
					<td>
						<input id='go' type='submit' value='Go'>
					</td>
					<td>
						<input id='clear' type='button' value='Clear' name='clear_x'>
					</td>
					<td>
						<input id='rescan' type='button' value='Rescan' name='rescan'>
					</td>
					<td>
						<input id='remall' type='button' value='Delete All' title='Delete All Unknown RRDfiles'>
						<input id='arcall' type='button' value='Archive All' title='Archive All Unknown RRDfiles'>
					</td>
					<td id='text'></td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			</form>
			<script type="text/javascript">
			function refreshForm() {
				$.get('rrdcleaner.php?header=false&filter='+$('#filter').val()+'&age='+$('#age').val()+'&rows='+$('#rows').val(), function(data) {
					$('#main').html(data);
					applySkin();
				});
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
					$.get('rrdcleaner.php?header=false&clear_x=1', function(data) {
						$('#main').html(data);
						applySkin();
					});
				});

				$('#rescan').click(function() {
					$('#text').text('Rebuilding RRDfile Listing');
					pulsate('#text');
					$.get('rrdcleaner.php?header=false&rescan=1&clear_x=1', function(data) {
						$('#main').html(data);
						$('#text').text('Finished').fadeOut(2000);
						applySkin();
					});
				});

				$('#arcall').click(function() {
					$('#text').text('Scheduling Archiving of All Unknowns');
					pulsate('#text');
					$.get('rrdcleaner.php?header=false&action=arcall&raction=3&clear_x=1', function(data) {
						$('#main').html(data);
						$('#text').text('Finished').fadeOut(2000);
						applySkin();
					});
				});

				$('#remall').click(function() {
					$('#text').text('Scheduling Purging of All Unknowns');
					pulsate('#text');
					$.get('rrdcleaner.php?header=false&action=remall&raction=1&clear_x=1', function(data) {
						$('#main').html(data);
						$('#text').text('Finished').fadeOut(2000);
						applySkin();
					});
				});
			});
			</script>
		</td>
	</tr>
	<?php
}

