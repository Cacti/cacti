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
include_once('./lib/api_data_source.php');
include_once('./lib/boost.php');
include_once('./lib/rrd.php');
include_once('./lib/clog_webapi.php');
include_once('./lib/poller.php');
include_once('./lib/utility.php');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'clear_poller_cache':
		/* obtain timeout settings */
		$max_execution = ini_get('max_execution_time');
		ini_set('max_execution_time', '0');
		repopulate_poller_cache();
		ini_set('max_execution_time', $max_execution);
		header('Location: utilities.php?action=view_poller_cache');

		exit;

		break;
	case 'rebuild_resource_cache':
		rebuild_resource_cache();
		header('Location: utilities.php');

		exit;

		break;
	case 'view_snmp_cache':
		top_header();
		utilities_view_snmp_cache();
		bottom_footer();

		break;
	case 'view_poller_cache':
		top_header();
		utilities_view_poller_cache();
		bottom_footer();

		break;
	case 'view_logfile':
		utilities_view_logfile();

		break;
	case 'clear_logfile':
		utilities_clear_logfile();
		utilities_view_logfile();

		break;
	case 'purge_logfile':
		clog_purge_logfile();
		utilities_view_logfile();

		break;
	case 'view_user_log':
		header('location: user_log.php');

		break;
	case 'clear_user_log':
		header('location: user_log.php?action=purge');

		break;
	case 'purge_user_log':
		header('location: user_log.php?action=purge');

		break;
	case 'view_tech':
		header('Location: support.php?tab=summary');

		exit();
	case 'view_boost_status':
		top_header();
		boost_display_run_status();
		bottom_footer();

		break;
	case 'view_snmpagent_cache':
		top_header();
		snmpagent_utilities_run_cache();
		bottom_footer();

		break;
	case 'purge_data_source_statistics':
		purge_data_source_statistics();
		raise_message('purge_dss', __('Data Source Statistics Purged.'), MESSAGE_LEVEL_INFO);
		header('Location: utilities.php');

		break;
	case 'rebuild_snmpagent_cache':
		snmpagent_cache_rebuilt();
		header('Location: utilities.php?action=view_snmpagent_cache');

		exit;

		break;
	case 'view_snmpagent_events':
		top_header();
		snmpagent_utilities_run_eventlog();
		bottom_footer();

		break;
	case 'ajax_hosts':
		get_allowed_ajax_hosts();

		break;
	case 'ajax_hosts_noany':
		get_allowed_ajax_hosts(false);

		break;
	default:
		if (!api_plugin_hook_function('utilities_action', get_request_var('action'))) {
			top_header();
			utilities();
			bottom_footer();
		}

		break;
}

function rebuild_resource_cache() {
	db_execute('DELETE FROM settings WHERE name LIKE "md5dirsum%"');
	db_execute('TRUNCATE TABLE poller_resource_cache');

	raise_message('resource_cache_rebuild');

	cacti_log('NOTE: Poller Resource Cache scheduled for rebuild by user ' . get_username($_SESSION[SESS_USER_ID]), false, 'WEBUI');
}

function utilities_view_logfile() {
	global $log_tail_lines, $page_refresh_interval, $config;

	$logfile = basename(get_nfilter_request_var('filename'));
	$logbase = basename(read_config_option('path_cactilog'));

	if ($logfile == '') {
		$logfile = $logbase;
	}

	if ($logfile == '') {
		$logfile = 'cacti.log';
	}

	$logname = '';
	$logpath = '';

	if (!clog_validate_filename($logfile, $logpath, $logname, true)) {
		raise_message('clog_invalid');
		header('Location: utilities.php?action=view_logfile&filename=' . $logbase);

		exit(0);
	} else {
		$logfile = $logpath . '/' . $logfile;
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'tail_lines' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'rfilter' => array(
			'filter'  => FILTER_VALIDATE_IS_REGEX,
			'pageset' => true,
			'default' => ''
			),
		'message_type' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'reverse' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'refresh' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => read_config_option('log_refresh_interval')
			)
	);

	validate_store_request_vars($filters, 'sess_log');
	/* ================= input validation ================= */

	$page_nr = get_request_var('page');

	$page = 'utilities.php?action=view_logfile';
	$page .= '&filename=' . basename($logfile) . '&page=' . $page_nr;

	$refresh = array(
		'seconds' => get_request_var('refresh'),
		'page'    => $page,
		'logout'  => 'false'
	);

	set_page_refresh($refresh);

	top_header();

	?>
	<script type='text/javascript'>

	function purgeLog() {
		strURL = urlPath+'utilities.php?action=purge_logfile&filename='+$('#filename').val();
		loadUrl({url:strURL})
	}

	$(function() {
		$('#refreshme').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#purge').click(function() {
			purgeLog();
		});

		$('#form_logfile').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	function applyFilter() {
		strURL  = urlPath+'utilities.php' +
			'?tail_lines=' + $('#tail_lines').val() +
			'&message_type=' + $('#message_type').val() +
			'&refresh=' + $('#refresh').val() +
			'&reverse=' + $('#reverse').val() +
			'&rfilter=' + base64_encode($('#rfilter').val()) +
			'&filename=' + $('#filename').val() +
			'&action=view_logfile';
		refreshMSeconds=$('#refresh').val()*1000;
		loadUrl({url:strURL})
	}

	function clearFilter() {
		strURL  = urlPath+'utilities.php?clear=1';
		strURL += '&action=view_logfile';
		loadUrl({url:strURL})
	}
	</script>
	<?php

	html_start_box(__('Log Filters'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_logfile' action='utilities.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('File');?>
					</td>
					<td>
						<select id='filename' onChange='applyFilter()'>
							<?php
							$logFileArray = clog_get_logfiles();

							if (cacti_sizeof($logFileArray)) {
								foreach ($logFileArray as $logFile) {
									print "<option value='" . html_escape($logFile) . "'";

									if (get_nfilter_request_var('filename') == $logFile) {
										print ' selected';
									}

									$logParts = explode('-', $logFile);

									$logDate = cacti_count($logParts) < 2 ? '' : $logParts[1] . (isset($logParts[2]) ? '-' . $logParts[2]:'');
									$logName = $logParts[0];

									print '>' . html_escape($logName . ($logDate != '' ? ' [' . substr($logDate,4) . ']':'')) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Tail Lines');?>
					</td>
					<td>
						<select id='tail_lines' onChange='applyFilter()'>
							<?php
							foreach ($log_tail_lines as $tail_lines => $display_text) {
								print "<option value='" . $tail_lines . "'";

								if (get_request_var('tail_lines') == $tail_lines) {
									print ' selected';
								} print '>' . $display_text . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refreshme' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc_x('Button: delete all table entries', 'Purge');?>' title='<?php print __esc('Purge Log');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Type');?>
					</td>
					<td>
						<select id='message_type' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('message_type') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='1'<?php if (get_request_var('message_type') == '1') {?> selected<?php }?>><?php print __('Stats');?></option>
							<option value='2'<?php if (get_request_var('message_type') == '2') {?> selected<?php }?>><?php print __('Warnings');?></option>
							<option value='3'<?php if (get_request_var('message_type') == '3') {?> selected<?php }?>><?php print __('Errors');?></option>
							<option value='4'<?php if (get_request_var('message_type') == '4') {?> selected<?php }?>><?php print __('Debug');?></option>
							<option value='5'<?php if (get_request_var('message_type') == '5') {?> selected<?php }?>><?php print __('SQL Calls');?></option>
						</select>
					</td>
					<td>
						<?php print __('Display Order');?>
					</td>
					<td>
						<select id='reverse' onChange='applyFilter()'>
							<option value='1'<?php if (get_request_var('reverse') == '1') {?> selected<?php }?>><?php print __('Newest First');?></option>
							<option value='2'<?php if (get_request_var('reverse') == '2') {?> selected<?php }?>><?php print __('Oldest First');?></option>
						</select>
					</td>
					<td>
						<?php print __('Refresh');?>
					</td>
					<td>
						<select id='refresh' onChange='applyFilter()'>
							<?php
							foreach ($page_refresh_interval as $seconds => $display_text) {
								print "<option value='" . $seconds . "'";

								if (get_request_var('refresh') == $seconds) {
									print ' selected';
								} print '>' . $display_text . '</option>';
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
						<input type='text' class='ui-state-default ui-corner-all' id='rfilter' size='75' value='<?php print html_escape_request_var('rfilter');?>'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='view_logfile'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* read logfile into an array and display */
	$total_rows      = 0;
	$number_of_lines = get_request_var('tail_lines') < 0 ? read_config_option('max_display_rows') : get_request_var('tail_lines');

	$logcontents = tail_file($logfile, $number_of_lines, get_request_var('message_type'), get_request_var('rfilter'), $page_nr, $total_rows);

	if (get_request_var('reverse') == 1) {
		$logcontents = array_reverse($logcontents);
	}

	if (get_request_var('message_type') > 0) {
		$start_string = __('Log [Total Lines: %d - Non-Matching Items Hidden]', $total_rows);
	} else {
		$start_string = __('Log [Total Lines: %d - All Items Shown]', $total_rows);
	}

	$rfilter      = get_request_var('rfilter');
	$reverse      = get_request_var('reverse');
	$refreshTime  = get_request_var('refresh');
	$message_type = get_request_var('message_type');
	$tail_lines   = get_request_var('tail_lines');
	$base_url     = 'utilities.php?action=view_logfile&filename='.basename($logfile);

	$nav = html_nav_bar($base_url, MAX_DISPLAY_PAGES, $page_nr, $number_of_lines, $total_rows, 13, __('Entries'), 'page', 'main');

	print $nav;

	html_start_box($start_string, '100%', '', '3', 'center', '');

	$linecolor = false;

	foreach ($logcontents as $item) {
		$host_start = strpos($item, 'Device[');
		$ds_start   = strpos($item, 'DS[');

		if (!$host_start && !$ds_start) {
			$new_item = html_escape($item);
		} else {
			$new_item = '';

			while ($host_start) {
				$host_end   = strpos($item, ']', $host_start);
				$host_id    = substr($item, $host_start + 7, $host_end - ($host_start + 7));
				$new_item .= html_escape(substr($item, 0, $host_start + 7)) . "<a href='" . html_escape('host.php?action=edit&id=' . $host_id) . "'>" . html_escape(substr($item, $host_start + 7, $host_end - ($host_start + 7))) . '</a>';
				$item       = substr($item, $host_end);
				$host_start = strpos($item, 'Device[');
			}

			$ds_start = strpos($item, 'DS[');

			while ($ds_start) {
				$ds_end    = strpos($item, ']', $ds_start);
				$ds_id     = substr($item, $ds_start + 3, $ds_end - ($ds_start + 3));
				$new_item .= html_escape(substr($item, 0, $ds_start + 3)) . "<a href='" . html_escape('data_sources.php?action=ds_edit&id=' . $ds_id) . "'>" . html_escape(substr($item, $ds_start + 3, $ds_end - ($ds_start + 3))) . '</a>';
				$item      = substr($item, $ds_end);
				$ds_start  = strpos($item, 'DS[');
			}

			$new_item .= html_escape($item);
		}

		/* get the background color */
		if (strpos($new_item, 'ERROR') !== false || strpos($new_item, 'FATAL') !== false) {
			$class = 'clogError';
		} elseif (strpos($new_item, 'WARN') !== false) {
			$class = 'clogWarning';
		} elseif (strpos($new_item, ' SQL ') !== false) {
			$class = 'clogSQL';
		} elseif (strpos($new_item, 'DEBUG') !== false) {
			$class = 'clogDebug';
		} elseif (strpos($new_item, 'STATS') !== false) {
			$class = 'clogStats';
		} else {
			if ($linecolor) {
				$class = 'odd';
			} else {
				$class = 'even';
			}
			$linecolor = !$linecolor;
		}

		print "<tr class='" . $class . "'><td>" . $new_item . '</td></tr>';
	}

	html_end_box();

	if ($total_rows) {
		print $nav;
	}

	bottom_footer();
}

function utilities_clear_logfile() {
	load_current_session_value('refresh', 'sess_logfile_refresh', read_config_option('log_refresh_interval'));

	$refresh['seconds'] = get_request_var('refresh');
	$refresh['page']    = 'utilities.php?action=view_logfile';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	top_header();

	$logfile = read_config_option('path_cactilog');

	if ($logfile == '') {
		$logfile = './log/cacti.log';
	}

	html_start_box(__('Clear Cacti Log'), '100%', '', '3', 'center', '');

	if (file_exists($logfile)) {
		if (is_writable($logfile)) {
			/* fill in the current date for printing in the log */
			if (defined('CACTI_DATE_TIME_FORMAT')) {
				$date = date(CACTI_DATE_TIME_FORMAT);
			} else {
				$date = date('Y-m-d H:i:s');
			}

			$log_fh = fopen($logfile, 'w');
			fwrite($log_fh, __('%s - WEBUI NOTE: Cacti Log Cleared from Web Management Interface.', $date) . PHP_EOL);
			fclose($log_fh);
			print '<tr><td>' . __('Cacti Log Cleared') . '</td></tr>';
		} else {
			print "<tr><td class='deviceDown'><b>" . __('Error: Unable to clear log, no write permissions.') . '<b></td></tr>';
		}
	} else {
		print "<tr><td class='deviceDown'><b>" . __('Error: Unable to clear log, file does not exist.'). '</b></td></tr>';
	}
	html_end_box();
}

function utilities_view_snmp_cache() {
	global $poller_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'with_index' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '0'
			),
		'host_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'snmp_query_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'poller_action' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_usnmp');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$refresh['seconds'] = '300';
	$refresh['page']    = 'utilities.php?action=view_snmp_cache';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	?>
	<script type="text/javascript">

	function applyFilter() {
		strURL  = urlPath+'utilities.php?host_id=' + $('#host_id').val();
		strURL += '&snmp_query_id=' + $('#snmp_query_id').val();
		if ($('#with_index').is(':checked')) {
			strURL += '&with_index=1';
		} else {
			strURL += '&with_index=0';
		}
		strURL += '&filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&action=view_snmp_cache';
		loadUrl({url:strURL})
	}

	function clearFilter() {
		strURL = urlPath+'utilities.php?action=view_snmp_cache&clear=1';
		loadUrl({url:strURL})
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_snmpcache').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('Data Query Cache Items'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_snmpcache' action='utilities.php'>
			<table class='filterTable'>
				<tr>
					<?php print html_host_filter(get_request_var('host_id'));?>
					<td>
						<?php print __('Query Name');?>
					</td>
					<td>
						<select id='snmp_query_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<?php
							if (get_request_var('host_id') == -1) {
								$snmp_queries = db_fetch_assoc('SELECT DISTINCT sq.id, sq.name
									FROM host_snmp_cache AS hsc
									INNER JOIN snmp_query AS sq
									ON hsc.snmp_query_id = sq.id
									INNER JOIN host AS h
									ON hsc.host_id = h.id
									ORDER by sq.name');
							} else {
								$snmp_queries = db_fetch_assoc_prepared('SELECT DISTINCT sq.id, sq.name
									FROM host_snmp_cache AS hsc
									INNER JOIN snmp_query AS sq
									ON hsc.snmp_query_id = sq.id
									INNER JOIN host AS h
									ON hsc.host_id = h.id
									WHERE h.id = ?
									ORDER by sq.name',
									array(get_request_var('host_id')));
							}

							if (cacti_sizeof($snmp_queries)) {
								foreach ($snmp_queries as $snmp_query) {
									print "<option value='" . $snmp_query['id'] . "'";

									if (get_request_var('snmp_query_id') == $snmp_query['id']) {
										print ' selected';
									} print '>' . html_escape($snmp_query['name']) . '</option>';
								}
							}
	?>
						</select>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Rows');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
	if (cacti_sizeof($item_rows)) {
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
					<td>
						<input type='checkbox' id='with_index' onChange='applyFilter()' title='<?php print __esc('Allow the search term to include the index column');?>' <?php if (get_request_var('with_index') == 1) {
							print ' checked ';
						}?>>
						<label for='with_index'><?php print __('Include Index') ?></label>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='view_snmp_cache'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* filter by host */
	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_id') == '0') {
		$sql_where .= ' AND h.id=0';
	} elseif (!isempty_request_var('host_id')) {
		$sql_where .= ' AND h.id=' . get_request_var('host_id');
	}

	/* filter by query name */
	if (get_request_var('snmp_query_id') == '-1') {
		/* Show all items */
	} elseif (!isempty_request_var('snmp_query_id')) {
		$sql_where .= ' AND hsc.snmp_query_id=' . get_request_var('snmp_query_id');
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (
			h.description LIKE '	  . db_qstr('%' . get_request_var('filter') . '%') . '
			OR sq.name LIKE '		 . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hsc.field_name LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hsc.field_value LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR hsc.oid LIKE '		 . db_qstr('%' . get_request_var('filter') . '%');

		if (get_request_var('with_index') == 1) {
			$sql_where .= ' OR hsc.snmp_index LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
		}

		$sql_where .= ')';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM host_snmp_cache AS hsc
		INNER JOIN snmp_query AS sq
		ON hsc.snmp_query_id = sq.id
		INNER JOIN host AS h
		ON hsc.host_id = h.id
		WHERE hsc.host_id = h.id
		AND hsc.snmp_query_id = sq.id
		$sql_where");

	$snmp_cache_sql = "SELECT hsc.*, h.description, sq.name
		FROM host_snmp_cache AS hsc
		INNER JOIN snmp_query AS sq
		ON hsc.snmp_query_id = sq.id
		INNER JOIN host AS h
		ON hsc.host_id = h.id
		WHERE hsc.host_id = h.id
		AND hsc.snmp_query_id = sq.id
		$sql_where
		LIMIT " . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$snmp_cache = db_fetch_assoc($snmp_cache_sql);

	$nav = html_nav_bar('utilities.php?action=view_snmp_cache&host_id=' . get_request_var('host_id') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 6, __('Entries'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header(array(__('Device'), __('Data Query Name'), __('Index'), __('Field Name'), __('Field Value'), __('OID')));

	$i = 0;

	if (cacti_sizeof($snmp_cache)) {
		foreach ($snmp_cache as $item) {
			form_alternate_row();
			?>
		<td>
			<?php print filter_value($item['description'], get_request_var('filter'));?>
		</td>
		<td>
			<?php print filter_value($item['name'], get_request_var('filter'));?>
		</td>
		<td>
			<?php print html_escape($item['snmp_index']);?>
		</td>
		<td>
			<?php print filter_value($item['field_name'], get_request_var('filter'));?>
		</td>
		<td>
			<?php print filter_value($item['field_value'], get_request_var('filter'));?>
		</td>
		<td>
			<?php print filter_value($item['oid'], get_request_var('filter'));?>
		</td>
		</tr>
		<?php
		}
	}

	html_end_box();

	if (cacti_sizeof($snmp_cache)) {
		print $nav;
	}
}

function utilities_view_poller_cache() {
	global $poller_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'dtd.name_cache',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		),
		'host_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'template_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'poller_action' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'status' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		)
	);

	validate_store_request_vars($filters, 'sess_poller');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$refresh['seconds'] = '300';
	$refresh['page']    = 'utilities.php?action=view_poller_cache';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = urlPath+'utilities.php?poller_action=' + $('#poller_action').val();
		strURL += '&action=view_poller_cache';
		strURL += '&host_id=' + $('#host_id').val();
		strURL += '&template_id=' + $('#template_id').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&status=' + $('#status').val();
		loadUrl({url:strURL})
	}

	function clearFilter() {
		strURL = urlPath+'utilities.php?action=view_poller_cache&clear=1';
		loadUrl({url:strURL})
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_pollercache').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('Poller Cache Items'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_pollercache' action='utilities.php'>
			<table class='filterTable'>
				<tr>
					<?php print html_host_filter(get_request_var('host_id'));?>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php
							if (get_request_var('host_id') > 0) {
								$sql_where = 'WHERE dl.host_id = ' . get_request_var('host_id');
							} else {
								$sql_where = '';
							}

							$templates = db_fetch_assoc("SELECT DISTINCT dt.id, dt.name
								FROM data_template AS dt
								INNER JOIN data_local AS dl
								ON dt.id=dl.data_template_id
								$sql_where
								ORDER BY name");

	if (cacti_sizeof($templates)) {
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
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Status');?>
					</td>
					<td>
						<select id='status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('status') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='1'<?php if (get_request_var('status') == '1') {?> selected<?php }?>><?php print __('Enabled');?></option>
							<option value='0'<?php if (get_request_var('status') == '0') {?> selected<?php }?>><?php print __('Disabled');?></option>
						</select>
					</td>
					<td>
						<?php print __('Action');?>
					</td>
					<td>
						<select id='poller_action' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('poller_action') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('poller_action') == '0') {?> selected<?php }?>><?php print __('SNMP');?></option>
							<option value='1'<?php if (get_request_var('poller_action') == '1') {?> selected<?php }?>><?php print __('Script');?></option>
							<option value='2'<?php if (get_request_var('poller_action') == '2') {?> selected<?php }?>><?php print __('Script Server');?></option>
						</select>
					</td>
					<td>
						<?php print __('Entries');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
	if (cacti_sizeof($item_rows)) {
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
			<input type='hidden' name='action' value='view_poller_cache'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = '';

	if (get_request_var('poller_action') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':' WHERE') . " pi.action='" . get_request_var('poller_action') . "'";
	}

	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':' WHERE') . ' pi.host_id = 0';
	} elseif (!isempty_request_var('host_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ':' WHERE') . ' pi.host_id = ' . get_request_var('host_id');
	}

	if (get_request_var('template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' dtd.data_template_id=0';
	} elseif (!isempty_request_var('template_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' dl.data_template_id=' . get_request_var('template_id');
	}

	if (get_request_var('status') == 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (h.disabled = "on" OR dtd.active = "")';
	} elseif (get_request_var('status') == 1) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (h.disabled = "" AND dtd.active = "on")';
	}

	if (get_request_var('filter') != '') {
		if (get_request_var('host_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':' WHERE') . ' (
				dtd.name_cache LIKE '   . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.arg1 LIKE '	   . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.rrd_path  LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . ')';
		} else {
			$sql_where .= ($sql_where != '' ? ' AND ':' WHERE') . ' (
				dtd.name_cache LIKE '   . db_qstr('%' . get_request_var('filter') . '%') . '
				OR h.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.arg1 LIKE '	   . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.hostname LIKE '   . db_qstr('%' . get_request_var('filter') . '%') . '
				OR pi.rrd_path  LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . ')';
		}
	}

	$sql = "SELECT COUNT(*)
		FROM poller_item AS pi
		INNER JOIN data_local AS dl
		ON dl.id = pi.local_data_id
		LEFT JOIN data_template_data AS dtd
		ON dtd.local_data_id = pi.local_data_id
		LEFT JOIN host AS h
		ON pi.host_id = h.id
		$sql_where";

	$total_rows = get_total_row_data($_SESSION[SESS_USER_ID], $sql, array(), 'poller_item');

	$poller_sql = "SELECT pi.*, dtd.name_cache, h.description, h.id AS host_id
		FROM poller_item AS pi
		INNER JOIN data_local AS dl
		ON dl.id = pi.local_data_id
		LEFT JOIN data_template_data AS dtd
		ON dtd.local_data_id = pi.local_data_id
		LEFT JOIN host AS h
		ON pi.host_id = h.id
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . ', action ASC
		LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$poller_cache = db_fetch_assoc($poller_sql);

	$nav = html_nav_bar('utilities.php?action=view_poller_cache&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 3, __('Entries'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'dtd.name_cache' => array(__('Data Source Name'), 'ASC'),
		'h.description'  => array(__('Device Description'), 'ASC'),
		'nosort'         => array(__('Details'), 'ASC'));

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'utilities.php?action=view_poller_cache');

	$i = 0;

	if (cacti_sizeof($poller_cache)) {
		foreach ($poller_cache as $item) {
			if ($i % 2 == 0) {
				$class = 'odd';
			} else {
				$class = 'even';
			}
			print "<tr class='$class'>";
			?>
				<td>
					<?php print filter_value($item['name_cache'], get_request_var('filter'), 'data_sources.php?action=ds_edit&id=' . $item['local_data_id']);?>
				</td>
				<td>
					<?php print filter_value($item['description'], get_request_var('filter'), 'host.php?action=edit&id=' . $item['host_id']);?>
				</td>

				<td>
				<?php
			if ($item['action'] == 0) {
				if ($item['snmp_version'] != 3) {
					$details =
						__('SNMP Version:') . ' ' . $item['snmp_version'] . ', ' .
						__('Community:') . ' ' . html_escape($item['snmp_community']) . ', ' .
						__('OID:') . ' ' . filter_value($item['arg1'], get_request_var('filter'));
				} else {
					$details =
						__('SNMP Version:') . ' ' . $item['snmp_version'] . ', ' .
						__('User:') . ' ' . html_escape($item['snmp_username']) . ', ' . __('OID:') . ' ' . html_escape($item['arg1']);
				}
			} elseif ($item['action'] == 1) {
				$details = __('Script:') . ' ' . filter_value($item['arg1'], get_request_var('filter'));
			} else {
				$details = __('Script Server:') . ' ' . filter_value($item['arg1'], get_request_var('filter'));
			}

			print $details;

			?>
				</td>
			</tr>
			<?php
			print "<tr class='$class'>";
			?>
				<td colspan='2'>
				</td>
				<td>
					<?php print __('RRD:');?> <?php print html_escape($item['rrd_path']);?>
				</td>
			</tr>
			<?php
			$i++;
		}
	}

	html_end_box();

	if (cacti_sizeof($poller_cache)) {
		print $nav;
	}
}

function utilities() {
	global $config, $utilities;

	$utilities[__('Technical Support')] = array(
		__('Technical Support') => array(
			'link'        => 'support.php?tab=summary',
			'description' => __('Cacti technical support page.  Used by developers and technical support persons to assist with issues in Cacti.  Includes checks for common configuration issues.')
		),
		__('Log Administration') => array(
			'link'        => 'utilities.php?action=view_logfile',
			'description' => __('The Cacti Log stores statistic, error and other message depending on system settings.  This information can be used to identify problems with the poller and application.')
		),
		__('View User Log') => array(
			'link'        => 'utilities.php?action=view_user_log',
			'description' => __('Allows Administrators to browse the user log.  Administrators can filter and export the log as well.')
		)
	);

	$utilities[__('Poller Cache Administration')] = array(
		__('View Poller Cache') => array(
			'link'        => 'utilities.php?action=view_poller_cache',
			'description' => __('This is the data that is being passed to the poller each time it runs. This data is then in turn executed/interpreted and the results are fed into the RRDfiles for graphing or the database for display.')
		),
		__('View Data Query Cache') => array(
			'link'        => 'utilities.php?action=view_snmp_cache',
			'description' => __('The Data Query Cache stores information gathered from Data Query input types. The values from these fields can be used in the text area of Graphs for Legends, Vertical Labels, and GPRINTS as well as in CDEF\'s.')
		),
		__('Rebuild Poller Cache') => array(
			'link'        => 'utilities.php?action=clear_poller_cache',
			'mode'        => 'online',
			'description' => __('The Poller Cache will be re-generated if you select this option. Use this option only in the event of a database crash if you are experiencing issues after the crash and have already run the database repair tools.  Alternatively, if you are having problems with a specific Device, simply re-save that Device to rebuild its Poller Cache.  There is also a command line interface equivalent to this command that is recommended for large systems.'),
			'note'        => array (
				'message' => __('NOTE: On large systems, this command may take several minutes to hours to complete and therefore should not be run from the Cacti UI.  You can simply run \'php -q cli/rebuild_poller_cache.php --help\' at the command line for more information.'),
				'class'   => 'textWarning'
			)
		),
		__('Rebuild Resource Cache') => array(
			'link'        => 'utilities.php?action=rebuild_resource_cache',
			'mode'        => 'online',
			'description' => __('When operating multiple Data Collectors in Cacti, Cacti will attempt to maintain state for key files on all Data Collectors.  This includes all core, non-install related website and plugin files.  When you force a Resource Cache rebuild, Cacti will clear the local Resource Cache, and then rebuild it at the next scheduled poller start.  This will trigger all Remote Data Collectors to recheck their website and plugin files for consistency.')
		),
	);

	$utilities[__('Boost Utilities')] = array(
		__('View Boost Status') => array(
			'link'        => 'utilities.php?action=view_boost_status',
			'description' => __('This menu pick allows you to view various boost settings and statistics associated with the current running Boost configuration.')
		),
	);

	$utilities[__('Data Source Statistics Utilities')] = array(
		__('Purge Data Source Statistics') => array(
			'link'        => 'utilities.php?action=purge_data_source_statistics',
			'mode'        => 'online',
			'description' => __('This menu pick will purge all existing Data Source Statistics from the Database.  If Data Source Statistics is enabled, the Data Sources Statistics will start collection again on the next Data Collector pass.')
		),
	);

	if (snmpagent_enabled()) {
		$utilities[__('SNMP Agent Utilities')] = array(
			__('View SNMP Agent Cache') => array(
				'link'        => 'utilities.php?action=view_snmpagent_cache',
				'mode'        => 'online',
				'description' => __('This shows all objects being handled by the SNMP Agent.')
			),
			__('Rebuild SNMP Agent Cache') => array(
				'link'        => 'utilities.php?action=rebuild_snmpagent_cache',
				'mode'        => 'online',
				'description' => __('The SNMP cache will be cleared and re-generated if you select this option. Note that it takes another poller run to restore the SNMP cache completely.')
			),
			__('View SNMP Agent Notification Log') => array(
				'link'        => 'utilities.php?action=view_snmpagent_events',
				'mode'        => 'online',
				'description' => __('This menu pick allows you to view the latest events SNMP Agent has handled in relation to the registered notification receivers.')
			)
		);
	}

	api_plugin_hook('utilities_array');

	html_start_box(__('Cacti System Utilities'), '100%', '', '3', 'center', '');

	foreach ($utilities as $header => $content) {
		$i = 0;

		foreach ($content as $title => $details) {
			if ((isset($details['mode']) && $details['mode'] == 'online' && $config['connection'] == 'online') || !isset($details['mode'])) {
				if ($i == 0) {
					html_section_header($header, 2);
				}

				form_alternate_row();
				print "<td class='nowrap' style='vertical-align:top;'>";
				print "<a class='hyperLink' href='" . html_escape($details['link']) . "'>" . $title . '</a>';
				print '</td>';
				print '<td>';
				print html_escape($details['description']);

				if(isset($details['note'])) {
					print '<br/><i class="' . $details['note']['class'] . '">' . html_escape($details['note']['message']) . '</i>';
				}

				print '</td>';
				form_end_row();

				$i++;
			}
		}
	}

	api_plugin_hook('utilities_list');

	html_end_box();
}

function purge_data_source_statistics() {
	$tables = array(
		'data_source_stats_daily',
		'data_source_stats_hourly',
		'data_source_stats_hourly_cache',
		'data_source_stats_hourly_last',
		'data_source_stats_monthly',
		'data_source_stats_weekly',
		'data_source_stats_yearly'
	);

	foreach ($tables as $table) {
		db_execute('TRUNCATE TABLE ' . $table);
	}

	if (isset($_SESSION[SESS_USER_ID])) {
		cacti_log('NOTE: Cacti DS Stats purged by user ' . get_username($_SESSION[SESS_USER_ID]), false, 'WEBUI');
	} else {
		cacti_log('NOTE: Cacti DS Stats purged by cli script');
	}
}

function boost_display_run_status() {
	global $config, $refresh_interval, $boost_utilities_interval, $boost_refresh_interval, $boost_max_runtime;

	/* ================= input validation ================= */
	get_filter_request_var('refresh');
	/* ==================================================== */

	load_current_session_value('refresh', 'sess_boost_utilities_refresh', '30');

	$last_run_time   = read_config_option('boost_last_run_time', true);
	$next_run_time   = read_config_option('boost_next_run_time', true);

	$rrd_updates     = read_config_option('boost_rrd_update_enable', true);
	$boost_cache     = read_config_option('boost_png_cache_enable', true);

	$max_records     = read_config_option('boost_rrd_update_max_records', true);
	$max_runtime     = read_config_option('boost_rrd_update_max_runtime', true);
	$update_interval = read_config_option('boost_rrd_update_interval', true);
	$peak_memory     = read_config_option('boost_peak_memory', true);
	$detail_stats    = read_config_option('stats_detail_boost', true);

	$refresh['seconds'] = get_request_var('refresh');
	$refresh['page']    = 'utilities.php?action=view_boost_status';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	html_start_box(__('Boost Status'), '100%', '', '3', 'center', '');

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL = urlPath+'utilities.php?action=view_boost_status&refresh=' + $('#refresh').val();
		loadUrl({url:strURL})
	}
	</script>
	<tr class='even'>
		<form id='form_boost_utilities_stats' method='post'>
		<td>
			<table>
				<tr>
					<td class='nowrap'>
						<?php print __('Refresh Interval');?>
					</td>
					<td>
						<select id='refresh' name='refresh' onChange='applyFilter()'>
						<?php
						foreach ($boost_utilities_interval as $key => $interval) {
							print '<option value="' . $key . '"';

							if (get_request_var('refresh') == $key) {
								print ' selected';
							} print '>' . $interval . '</option>';
						}
	?>
					</td>
					<td>
						<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Refresh');?>' onClick='applyFilter()'>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php
	html_end_box(true);

	html_start_box('', '100%', '', '3', 'center', '');

	/* get the boost table status */
	$boost_table_status = db_fetch_assoc("SELECT *
		FROM INFORMATION_SCHEMA.TABLES
		WHERE table_schema = SCHEMA()
		AND (table_name LIKE 'poller_output_boost_arch_%'
		OR table_name LIKE 'poller_output_boost')");

	$pending_records = 0;
	$arch_records    = 0;
	$data_length     = 0;
	$engine          = '';
	$max_data_length = 0;

	foreach ($boost_table_status as $table) {
		if ($table['TABLE_NAME'] == 'poller_output_boost') {
			$pending_records += $table['TABLE_ROWS'];
		} else {
			$arch_records += $table['TABLE_ROWS'];
		}

		$data_length    += $table['DATA_LENGTH'];
		$data_length    += $table['INDEX_LENGTH'];
		$engine          = $table['ENGINE'];
		$max_data_length = $table['MAX_DATA_LENGTH'];
	}

	if ($config['connection'] == 'online' && db_table_exists('poller_output_boost_local_data_ids')) {
		$pending_ds = db_fetch_cell('SELECT COUNT(local_data_id) FROM poller_output_boost_local_data_ids');
	} else {
		$pending_ds = 0;
	}

	$poller_items = db_fetch_cell('SELECT COUNT(local_data_id)
		FROM poller_item AS pi
		INNER JOIN host AS h
		ON h.id = pi.host_id
		WHERE h.disabled = ""');

	$data_sources = db_fetch_cell('SELECT COUNT(DISTINCT local_data_id)
		FROM poller_item AS pi
		INNER JOIN host AS h
		ON h.id = pi.host_id
		WHERE h.disabled = ""');

	$pi_ds = ($data_sources ? ($poller_items / $data_sources) : 0);

	if ($pending_ds == 0) {
		$remaining = $arch_records;
	} else {
		$remaining = $arch_records * (($pending_ds * $pi_ds) / $data_sources);
	}

	$total_records  = $pending_records + $remaining;
	$avg_row_length = ($total_records ? intval($data_length / $total_records) : 0);

	$boost_status = read_config_option('boost_poller_status', true);
	if ($boost_status != '' && $boost_status != 'disabled') {
		$boost_status_array = explode(':', $boost_status);

		if (isset($boost_status_array[1])) {
			$boost_status_date = $boost_status_array[1];
		} else {
			$boost_status_date = null;
		}

		if (substr_count($boost_status_array[0], 'complete')) {
			$status = '<span class="deviceRecovering">' . __('Idle') . '</span>';
		} elseif (substr_count($boost_status_array[0], 'running')) {
			$status = '<span class="deviceUp">' . __('Running') . '</span>';
		} elseif (substr_count($boost_status_array[0], 'overrun')) {
			$status = '<span class="deviceDown">' . __('Overrun Warning') . '</span>';
		} elseif (substr_count($boost_status_array[0], 'timeout')) {
			$status = '<span class="deviceDown">' . __('Timed Out') . '</span>';
		} else {
			$status = '<span class="deviceDown">' . __('Other') . '</span>';
		}
	} else {
		$status = '<span class="deviceDisabled">' . __('Disabled') . '</span>';
		$boost_status_date = null;
	}

	$stats_boost = read_config_option('stats_boost', true);

	if ($stats_boost != '') {
		$stats_boost_array = explode(' ', $stats_boost);

		$stats_duration          = explode(':', $stats_boost_array[0]);
		$boost_last_run_duration = $stats_duration[1];

		$stats_rrds         = explode(':', $stats_boost_array[1]);
		$boost_rrds_updated = $stats_rrds[1];
	} else {
		$boost_last_run_duration = '';
		$boost_rrds_updated      = '';
	}

	/* get cache directory size/contents */
	$cache_directory    = read_config_option('boost_png_cache_directory', true);
	$directory_contents = array();

	if (is_dir($cache_directory)) {
		if ($handle = @opendir($cache_directory)) {
			/* This is the correct way to loop over the directory. */
			while (false !== ($file = readdir($handle))) {
				$directory_contents[] = $file;
			}

			closedir($handle);

			/* get size of directory */
			$directory_size = 0;
			$cache_files    = 0;

			if (cacti_sizeof($directory_contents)) {
				/* goto the cache directory */
				chdir($cache_directory);

				/* check and fry as applicable */
				foreach ($directory_contents as $file) {
					/* only remove jpeg's and png's */
					if ((substr_count(strtolower($file), '.png')) ||
						(substr_count(strtolower($file), '.jpg'))) {
						$cache_files++;
						$directory_size += filesize($file);
					}
				}
			}

			$directory_size = boost_file_size_display($directory_size);
			$cache_files    = $cache_files . ' Files';
		} else {
			$directory_size = '<strong>' . __('WARNING:') . '</strong>' . __('Cannot open directory');
			$cache_files    = '<strong>' . __('WARNING:') . '</strong> ' . __('Unknown');
		}
	} else {
		$directory_size = '<strong>' . __('WARNING:') . '</strong> ' . __('Directory Does NOT Exist!!');
		$cache_files    = '<strong>' . __('WARNING:') . '</strong> ' . __('N/A');
	}

	$running = db_fetch_cell('SELECT COUNT(*) FROM processes WHERE tasktype="boost" AND taskname="child"');

	$i = 0;

	/* boost status display */
	html_section_header(__('Current Boost Status'), 2);

	if ($config['connection'] == 'online') {
		form_alternate_row();
		print '<td>' . __('Boost On-demand Updating:') . '</td><td><b>' . $status . '</b></td>';

		if ($running > 0) {
			form_alternate_row();
			print '<td>' . __('Running Processes:') . '</td><td>' . ($running) . '</td>';
		}
	}

	form_alternate_row();
	print '<td>' . __('Total Poller Items:') . '</td><td>' . number_format_i18n($poller_items) . '</td>';

	$premaining = ($data_sources ? (round(($pending_ds / $data_sources) * 100, 1)) : 0);

	if ($total_records) {
		form_alternate_row();
		print '<td>' . __('Total Data Sources:') . '</td><td>' . number_format_i18n($data_sources) . '</td>';

		if ($config['connection'] == 'online') {
			form_alternate_row();
			print '<td>' . __('Remaining Data Sources:') . '</td><td>' . ($pending_ds > 0 ? number_format_i18n($pending_ds) . " ($premaining %)":__('TBD')) . '</td>';
		}

		form_alternate_row();
		print '<td>' . __('Queued Boost Records:') . '</td><td>' . number_format_i18n($pending_records) . '</td>';

		if ($config['connection'] == 'online') {
			form_alternate_row();
			print '<td>' . __('Approximate in Process:') . '</td><td>' . number_format_i18n($remaining) . '</td>';

			form_alternate_row();
			print '<td>' . __('Total Boost Records:') . '</td><td>' . number_format_i18n($total_records) . '</td>';
		}
	}

	/* boost status display */
	html_section_header(__('Boost Storage Statistics'), 2);

	/* describe the table format */
	form_alternate_row();
	print '<td>' . __('Database Engine:') . '</td><td>' . $engine . '</td>';

	/* tell the user how big the table is */
	form_alternate_row();
	print '<td>' . __('Current Boost Table(s) Size:') . '</td><td>' . boost_file_size_display($data_length, 2) . '</td>';

	/* tell the user about the average size/record */
	form_alternate_row();
	print '<td>' . __('Avg Bytes/Record:') . '</td><td>' . boost_file_size_display($avg_row_length, 0) . '</td>';

	/* tell the user about the average size/record */
	$output_length = read_config_option('boost_max_output_length');

	if ($output_length != '') {
		$parts = explode(':', $output_length);

		if ((time() - 1200) > $parts[0]) {
			$ref = true;
		} else {
			$ref = false;
		}
	} else {
		$ref = true;
	}

	if ($ref) {
		if (strcmp($engine, 'MEMORY') == 0) {
			$max_length = db_fetch_cell('SELECT MAX(LENGTH(output)) FROM poller_output_boost');
		} else {
			$max_length = '0';
		}
		db_execute("REPLACE INTO settings (name, value) VALUES ('boost_max_output_length', '" . time() . ':' . $max_length . "')");
	} else {
		$max_length = $parts[1];
	}

	if ($max_length != 0) {
		form_alternate_row();
		print '<td>' . __('Max Record Length:') . '</td><td>' . __('%d Bytes', number_format_i18n($max_length)) . '</td>';
	}

	/* tell the user about the "Maximum Size" this table can be */
	form_alternate_row();

	if (strcmp($engine, 'MEMORY')) {
		$max_table_allowed = __('Unlimited');
		$max_table_records = __('Unlimited');
	} else {
		$max_table_allowed = boost_file_size_display($max_data_length, 2);
		$max_table_records = number_format_i18n(($avg_row_length ? $max_data_length / $avg_row_length : 0), 3, 1000);
	}
	print '<td>' . __('Max Allowed Boost Table Size:') . '</td><td>' . $max_table_allowed . '</td>';

	/* tell the user about the estimated records that "could" be held in memory */
	form_alternate_row();
	print '<td>' . __('Estimated Maximum Records:') . '</td><td>' . $max_table_records . ' Records</td>';

	if ($config['connection'] == 'online') {
		/* boost last runtime display */
		html_section_header(__('Previous Runtime Statistics'), 2);

		form_alternate_row();
		print '<td class="utilityPick">' . __('Last Start Time:') . '</td><td>' . (is_numeric($last_run_time) ? date('Y-m-d H:i:s', $last_run_time):$last_run_time) . '</td>';

		/* get the last end time */
		$last_end_time = read_config_option('boost_last_end_time', true);

		form_alternate_row();
		print '<td class="utilityPick">' . __('Last End Time:') . '</td><td>' . ($last_end_time != '' ? date('Y-m-d H:i:s', $last_end_time):__('Never Run')) . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Last Run Duration:') . '</td><td>';

		if (is_numeric($boost_last_run_duration)) {
			print($boost_last_run_duration > 60 ? __('%d minutes', (int)$boost_last_run_duration / 60) . ', ': '') . __('%d seconds', (int) $boost_last_run_duration % 60);

			if ($rrd_updates != '') {
				print ' (' . __('%0.2f percent of update frequency)', round(100 * $boost_last_run_duration / $update_interval / 60));
			}
		} else {
			print __('N/A');
		}
		print '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('RRD Updates:') . '</td><td>' . ($boost_rrds_updated != '' ? number_format_i18n($boost_rrds_updated):'-') . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Peak Poller Memory:') . '</td><td>' . ((read_config_option('boost_peak_memory') != '' && is_numeric(read_config_option('boost_peak_memory'))) ? (round(read_config_option('boost_peak_memory') / 1024 / 1024,2)) . ' ' . __('MBytes') : __('N/A')) . '</td>';

		form_alternate_row();

		$memory_limit = read_config_option('boost_poller_mem_limit');

		if ($memory_limit == -1) {
			$memory_limit = __('Unlimited');
		} elseif ($memory_limit != '') {
			$memory_limit = __('%s MBytes', number_format_i18n($memory_limit));
		} else {
			$memory_limit = __('N/A');
		}

		print '<td class="utilityPick">' . __('Max Poller Memory Allowed:') . '</td><td>' . $memory_limit . '</td>';

		/* boost last runtime display */
		html_section_header(__('Detailed Runtime Statistics'), 2);

		form_alternate_row();

		if ($detail_stats == '') {
			$detail_stats = __('N/A');
		} else {
			$values = explode(' ', $detail_stats);
			$rows   = explode(':', $values[0])[1];
			$time   = explode(':', $values[1])[1];
			$recs   = explode(':', $values[2])[1];
			$rcycle = explode(':', $values[3])[1];
			$fandt  = explode(':', $values[4])[1];
			$lastu  = explode(':', $values[5])[1];
			$update = explode(':', $values[6])[1];
			$delete = explode(':', $values[7])[1];

			$detail_stats = __('Records: %s (ds rows), Time: %s (secs), GetRows: %s (secs), ResultsCycle: %s (secs), FileAndTemplate: %s (secs), LastUpdate: %s (secs), RRDUpdate: %s (secs), Delete: %s (secs)',
				number_format_i18n($rows),
				number_format_i18n($time),
				number_format_i18n($recs),
				number_format_i18n($rcycle),
				number_format_i18n($fandt),
				number_format_i18n($lastu),
				number_format_i18n($update),
				number_format_i18n($delete));
		}

		print '<td class="utilityPick">' . __('Previous Runtime Timers:') . '</td><td>' . (($detail_stats != '') ? $detail_stats:__('N/A')) . '</td>';

		$runtimes = db_fetch_assoc('SELECT name, value, CAST(replace(name, "stats_boost_", "") AS signed) AS ome
			FROM settings
			WHERE name LIKE "stats_boost_%"
			ORDER BY ome');

		if (cacti_sizeof($runtimes)) {
			foreach ($runtimes as $r) {
				$process = str_replace('stats_boost_', '', $r['name']);

				if ($r['value'] != '') {
					$values = explode(' ', $r['value']);
					$time   = explode(':', $values[0])[1];
					$rrds   = explode(':', $values[2])[1];
				} else {
					$time = 0;
					$rrds = 0;
				}

				$rows_to_process = db_fetch_cell_prepared('SELECT COUNT(*)
					FROM poller_output_boost_local_data_ids
					WHERE process_handler = ?',
					array($process));

				$runtime = db_fetch_cell_prepared('SELECT UNIX_TIMESTAMP() - UNIX_TIMESTAMP(started)
					FROM processes
					WHERE tasktype = "boost"
					AND taskname = "child"
					AND taskid = ?',
					array($process));

				form_alternate_row();

				if ($rows_to_process > 0) {
					print '<td class="utilityPick">' . __esc('Process: %d', $process) . '</td><td>' . __('Status: <span class="deviceUp"><b>Running</b></span>, Remaining: %s (dses), CurrentRuntime: %s (secs), PrevRuntime: %s (secs), PrevProcessed: %10s (ds rows)', number_format_i18n($rows_to_process), number_format_i18n($runtime), number_format_i18n($time), number_format_i18n($rrds)) . '</td>';
				} else {
					print '<td class="utilityPick">' . __esc('Process: %d', $process) . '</td><td>' . __('Status: <span class="deviceRecovering"><b>Idle</b></span>, PrevRuntime: %s (secs), PrevProcessed: %10s (ds rows)', number_format_i18n($time), number_format_i18n($rrds)) . '</td>';
				}
			}
		}

		/* boost runtime display */
		html_section_header(__('Run Time Configuration'), 2);

		form_alternate_row();
		print '<td class="utilityPick">' . __('Update Frequency:') . '</td><td>' . ($rrd_updates == '' ? __('N/A') : $boost_refresh_interval[$update_interval]) . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Concurrent Processes:') . '</td><td>' . read_config_option('boost_parallel') . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Next Start Time:') . '</td><td>' . (is_numeric($next_run_time) ? date('Y-m-d H:i:s', $next_run_time):$next_run_time) . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Maximum Records:') . '</td><td>' . number_format_i18n($max_records) . ' ' . __('Records') . '</td>';

		form_alternate_row();
		print '<td class="utilityPick">' . __('Maximum Allowed Runtime:') . '</td><td>' . $boost_max_runtime[$max_runtime] . '</td>';

		/* boost caching */
		html_section_header(__('Image Caching'), 2);

		form_alternate_row();
		print '<td>' . __('Image Caching Status:') . '</td><td>' . ($boost_cache == '' ? __('Disabled') : __('Enabled')) . '</td>';

		form_alternate_row();
		print '<td>' . __('Cache Directory:') . '</td><td>' . $cache_directory . '</td>';

		form_alternate_row();
		print '<td>' . __('Cached Files:') . '</td><td>' . $cache_files . '</td>';

		form_alternate_row();
		print '<td>' . __('Cached Files Size:') . '</td><td>' . $directory_size . '</td>';

		html_end_box(true);
	}
}

/**
 *
 *
 * snmpagent_utilities_run_cache()
 *
 * @param mixed
 * @return
 */
function snmpagent_utilities_run_cache() {
	global $item_rows;

	get_filter_request_var('mib', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));

	$mibs            = db_fetch_assoc('SELECT DISTINCT mib FROM snmpagent_cache');
	$registered_mibs = array();

	if ($mibs && $mibs > 0) {
		foreach ($mibs as $mib) {
			$registered_mibs[] = $mib['mib'];
		}
	}

	/* ================= input validation ================= */
	if (!in_array(get_request_var('mib'), $registered_mibs, true) && get_request_var('mib') != '-1' && get_request_var('mib') != '') {
		die_html_input_error('mib');
	}
	/* ==================================================== */

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'mib' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => '-1',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_snmpac');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'utilities.php?action=view_snmpagent_cache';
		strURL += '&mib=' + $('#mib').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		loadUrl({url:strURL})
	}

	function clearFilter() {
		strURL = 'utilities.php?action=view_snmpagent_cache&clear=1';
		loadUrl({url:strURL})
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_snmpagent_cache').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('SNMP Agent Cache'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
			<form id='form_snmpagent_cache' action='utilities.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('MIB');?>
						</td>
						<td>
							<select id='mib' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('mib') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<?php
								if (cacti_sizeof($mibs) > 0) {
									foreach ($mibs as $mib) {
										print "<option value='" . html_escape($mib['mib']) . "'";

										if (get_request_var('mib') == $mib['mib']) {
											print ' selected';
										} print '>' . html_escape($mib['mib']) . '</option>';
									}
								}
	?>
							</select>
						</td>
						<td>
							<?php print __('OIDs');?>
						</td>
						<td>
							<select id='rows' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
								<?php
	if (cacti_sizeof($item_rows)) {
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
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* filter by host */
	if (get_request_var('mib') == '-1') {
		/* Show all items */
	} elseif (!isempty_request_var('mib')) {
		$sql_where .= " AND snmpagent_cache.mib='" . get_request_var('mib') . "'";
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (
			`oid` LIKE '		   . db_qstr('%' . get_request_var('filter') . '%') . '
			OR `name` LIKE '	   . db_qstr('%' . get_request_var('filter') . '%') . '
			OR `mib` LIKE '		. db_qstr('%' . get_request_var('filter') . '%') . '
			OR `max-access` LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	$sql_where .= ' ORDER by `oid`';

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM snmpagent_cache WHERE 1 $sql_where");

	$snmp_cache_sql = "SELECT * FROM snmpagent_cache WHERE 1 $sql_where LIMIT " . ($rows * (get_request_var('page') - 1)) . ',' . $rows;
	$snmp_cache     = db_fetch_assoc($snmp_cache_sql);

	/* generate page list */
	$nav = html_nav_bar('utilities.php?action=view_snmpagent_cache&mib=' . get_request_var('mib') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Entries'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header(array(__('OID'), __('Name'), __('MIB'), __('Type'), __('Max-Access'), __('Value')));

	if (cacti_sizeof($snmp_cache)) {
		foreach ($snmp_cache as $item) {
			$oid        = filter_value($item['oid'], get_request_var('filter'));
			$name       = filter_value($item['name'], get_request_var('filter'));
			$mib        = filter_value($item['mib'], get_request_var('filter'));
			$max_access = filter_value($item['max-access'], get_request_var('filter'));

			form_alternate_row('line' . $item['oid'], false);
			form_selectable_cell($oid, $item['oid']);

			if ($item['description']) {
				print '<td><a href="#" title="<div class=\'header\'>' . $name . '</div><div class=\'content preformatted\'>' . html_escape($item['description']) . '</div>" class="tooltip">' . $name . '</a></td>';
			} else {
				print "<td>$name</td>";
			}
			form_selectable_cell($mib, $item['oid']);
			form_selectable_cell($item['kind'], $item['oid']);
			form_selectable_cell($max_access, $item['oid']);
			form_selectable_ecell((in_array($item['kind'], array(__('Scalar'), __('Column Data')), true) ? $item['value'] : __('N/A')), $item['oid']);
			form_end_row();
		}
	}

	html_end_box();

	if (cacti_sizeof($snmp_cache)) {
		print $nav;
	}

	?>
	<script type='text/javascript'>
	$(function() {
		$('.tooltip').tooltip({
			track: true,
			show: 250,
			hide: 250,
			position: { collision: 'flipfit' },
			content: function() { return DOMPurify.sanitize($(this).attr('title')); }
		});
	});
	</script>
	<?php
}

function snmpagent_utilities_run_eventlog() {
	global $item_rows;

	$severity_levels = array(
		SNMPAGENT_EVENT_SEVERITY_LOW      => 'LOW',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM   => 'MEDIUM',
		SNMPAGENT_EVENT_SEVERITY_HIGH     => 'HIGH',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => 'CRITICAL'
	);

	$severity_colors = array(
		SNMPAGENT_EVENT_SEVERITY_LOW      => '#00FF00',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM   => '#FFFF00',
		SNMPAGENT_EVENT_SEVERITY_HIGH     => '#FF0000',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => '#FF00FF'
	);

	$receivers = db_fetch_assoc('SELECT DISTINCT manager_id, hostname
		FROM snmpagent_notifications_log
		INNER JOIN snmpagent_managers
		ON snmpagent_managers.id = snmpagent_notifications_log.manager_id');

	/* ================= input validation ================= */
	get_filter_request_var('receiver');

	if (!in_array(get_request_var('severity'), array_keys($severity_levels), true) && get_request_var('severity') != '-1' && get_request_var('severity') != '') {
		die_html_input_error('mib');
	}
	/* ==================================================== */

	if (isset_request_var('purge')) {
		db_execute('TRUNCATE table snmpagent_notifications_log');

		/* reset filters */
		set_request_var('clear', true);
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'severity' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'receiver' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_snmpl');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'utilities.php?action=view_snmpagent_events';
		strURL += '&severity=' + $('#severity').val();
		strURL += '&receiver=' + $('#receiver').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		loadUrl({url:strURL})
	}

	function clearFilter() {
		strURL = 'utilities.php?action=view_snmpagent_events&clear=1';
		loadUrl({url:strURL})
	}

	function purgeFilter() {
		strURL = 'utilities.php?action=view_snmpagent_events&purge=1';
		loadUrl({url:strURL})
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#purge').click(function() {
			purgeFilter();
		});

		$('#form_snmpagent_notifications').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>

	<?php
	html_start_box(__('SNMP Agent Notification Log'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
			<form id='form_snmpagent_notifications' action='utilities.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Severity');?>
						</td>
						<td>
							<select id='severity' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('severity') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<?php
								foreach ($severity_levels as $level => $name) {
									print "<option value='" . $level . "'";

									if (get_request_var('severity') == $level) {
										print ' selected';
									} print '>' . html_escape($name) . '</option>';
								}
	?>
							</select>
						</td>
						<td>
							<?php print __('Receiver');?>
						</td>
						<td>
							<select id='receiver' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('receiver') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<?php
	foreach ($receivers as $receiver) {
		print "<option value='" . $receiver['manager_id'] . "'";

		if (get_request_var('receiver') == $receiver['manager_id']) {
			print ' selected';
		} print '>' . html_escape($receiver['hostname']) . '</option>';
	}
	?>
							</select>
						</td>
						<td>
							<?php print __('Entries');?>
						</td>
						<td>
							<select id='rows' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
								<?php
	if (cacti_sizeof($item_rows)) {
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
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc_x('Button: delete all table entries', 'Purge');?>' title='<?php print __esc('Purge Notification Log');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = ' 1';

	/* filter by severity */
	if (get_request_var('receiver') != '-1') {
		$sql_where .= " AND snl.manager_id='" . get_request_var('receiver') . "'";
	}

	/* filter by severity */
	if (get_request_var('severity') == '-1') {
		/* Show all items */
	} elseif (!isempty_request_var('severity')) {
		$sql_where .= " AND snl.severity='" . get_request_var('severity') . "'";
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (`varbinds` LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
	}

	$sql_where .= ' ORDER by `time` DESC';

	$sql_query  = "SELECT snl.*, sm.hostname, sc.description
		FROM snmpagent_notifications_log AS snl
		INNER JOIN snmpagent_managers AS sm
		ON sm.id = snl.manager_id
		LEFT JOIN snmpagent_cache AS sc
		ON sc.name = snl.notification
		WHERE $sql_where
		LIMIT " . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM snmpagent_notifications_log AS snl
		WHERE $sql_where");

	$logs = db_fetch_assoc($sql_query);

	$nav = html_nav_bar('utilities.php?action=view_snmpagent_events&severity='. get_request_var('severity').'&receiver='. get_request_var('receiver').'&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Log Entries'), 'page', 'main');

	form_start('managers.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header(array(' ', __('Time'), __('Receiver'), __('Notification'), __('Varbinds')));

	if (cacti_sizeof($logs)) {
		foreach ($logs as $item) {
			$varbinds = filter_value($item['varbinds'], get_request_var('filter'));
			form_alternate_row('line' . $item['id'], false);

			print "<td title='" . __esc('Severity Level: %s', $severity_levels[$item['severity']]) . "' style='width:10px;background-color: " . $severity_colors[$item['severity']] . ";border-top:1px solid white;border-bottom:1px solid white;'></td>";
			print "<td class='nowrap'>" . date('Y-m-d H:i:s', $item['time']) . '</td>';
			print '<td>' . html_escape($item['hostname']) . '</td>';

			if ($item['description']) {
				print '<td><a href="#" title="<div class=\'header\'>' . html_escape($item['notification']) . '</div><div class=\'content preformatted\'>' . html_escape($item['description']) . '</div>" class="tooltip">' . html_escape($item['notification']) . '</a></td>';
			} else {
				print '<td>' . html_escape($item['notification']) . '</td>';
			}

			print "<td>$varbinds</td>";

			form_end_row();
		}
	} else {
		print '<tr><td colspan="5"><em>' . __('No SNMP Notification Log Entries') . '</em></td></tr>';
	}

	html_end_box();

	if (cacti_sizeof($logs)) {
		print $nav;
	}

	?>

	<script type='text/javascript' >
	$('.tooltip').tooltip({
		track: true,
		position: { collision: 'flipfit' },
		content: function() { return $(this).attr('title'); }
	});
	</script>
	<?php
}
