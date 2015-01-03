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

include('./include/auth.php');
include_once('./lib/utility.php');
include_once('./lib/boost.php');

load_current_session_value('page_referrer', 'page_referrer', '');

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

if (isset($_REQUEST['sort_direction'])) {
	if ($_REQUEST['page_referrer'] == 'view_snmp_cache') {
		$_REQUEST['action'] = 'view_snmp_cache';
	}else if ($_REQUEST['page_referrer'] == 'view_poller_cache') {
		$_REQUEST['action'] = 'view_poller_cache';
	}else{
		$_REQUEST['action'] = 'view_user_log';
	}
}

if ((isset($_REQUEST['clear_x'])) || (isset($_REQUEST['go_x']))) {
	if ($_REQUEST['page_referrer'] == 'view_snmp_cache') {
		$_REQUEST['action'] = 'view_snmp_cache';
	}else if ($_REQUEST['page_referrer'] == 'view_poller_cache') {
		$_REQUEST['action'] = 'view_poller_cache';
	}else if ($_REQUEST['page_referrer'] == 'view_user_log') {
		$_REQUEST['action'] = 'view_user_log';
	}else if ($_REQUEST['page_referrer'] == 'view_snmpagent_cache') {
		$_REQUEST['action'] = 'view_snmpagent_cache';
	}else if ($_REQUEST['page_referrer'] == 'view_snmpagent_events') {
		$_REQUEST['action'] = 'view_snmpagent_events';
	}else{
		$_REQUEST['action'] = 'view_logfile';
	}
}

if (isset($_REQUEST['purge_x'])) {
	if ($_REQUEST['page_referrer'] == 'view_user_log') {
		$_REQUEST['action'] = 'clear_user_log';
	}else if ($_REQUEST['page_referrer'] == 'view_snmpagent_events') {
		$_REQUEST['action'] = 'clear_snmpagent_log';
	}else{
		$_REQUEST['action'] = 'clear_logfile';
	}
}

switch ($_REQUEST['action']) {
	case 'clear_poller_cache':
		/* obtain timeout settings */
		$max_execution = ini_get('max_execution_time');
		ini_set('max_execution_time', '0');
		repopulate_poller_cache();
		ini_set('max_execution_time', $max_execution);
		header('Location: utilities.php?action=view_poller_cache');exit;
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
	case 'view_cleaner':
		top_header();
		utilities_view_cleaner();
		bottom_footer();
		break;
	case 'view_user_log':
		top_header();
		utilities_view_user_log();
		bottom_footer();
		break;
	case 'clear_user_log':
		utilities_clear_user_log();
		utilities_view_user_log();
		break;
	case 'view_tech':
		$php_info = utilities_php_modules();

		top_header();
		utilities_view_tech($php_info);
		bottom_footer();
		break;
	case 'view_boost_status':
		top_header();
		boost_display_run_status();
		bottom_footer();
		break;
	default:
		if (!api_plugin_hook_function('utilities_action', $_REQUEST['action'])) {
			top_header();
			utilities();
			bottom_footer();
		}
		break;
}

/* -----------------------
    Utilities Functions
   ----------------------- */

function utilities_php_modules() {

	/*
	   Gather phpinfo into a string variable - This has to be done before
	   any headers are sent to the browser, as we are going to do some
	   output buffering fun
	*/

	ob_start();
	phpinfo(INFO_MODULES);
	$php_info = ob_get_contents();
	ob_end_clean();

	/* Remove nasty style sheets, links and other junk */
	$php_info = str_replace("\n", '', $php_info);
	$php_info = preg_replace('/^.*\<body\>/', '', $php_info);
	$php_info = preg_replace('/\<\/body\>.*$/', '', $php_info);
	$php_info = preg_replace('/\<a.*\>/U', '', $php_info);
	$php_info = preg_replace('/\<\/a\>/', '<hr>', $php_info);
	$php_info = preg_replace('/\<img.*\>/U', '', $php_info);
	$php_info = preg_replace('/\<\/?address\>/', '', $php_info);

	return $php_info;
}


function memory_bytes($val) {
	$val = trim($val);
	$last = strtolower($val{strlen($val)-1});
	switch($last) {
		// The 'G' modifier is available since PHP 5.1.0
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}

	return $val;
}


function memory_readable($val) {

	if ($val < 1024) {
		$val_label = 'bytes';
	}elseif ($val < 1048576) {
		$val_label = 'K';
		$val /= 1024;
	}elseif ($val < 1073741824) {
		$val_label = 'M';
		$val /= 1048576;
	}else{
		$val_label = 'G';
		$val /= 1073741824;
	}

	return $val . $val_label;
}


function utilities_view_tech($php_info = '') {
	global $database_default, $config, $rrdtool_versions, $poller_options, $input_types;

	/* Get table status */
	$tables = db_fetch_assoc('SHOW TABLES');
	$skip_tables  = array();
	$table_status = array();

	if (sizeof($tables)) {
	foreach($tables as $table) {
		$create_syntax = db_fetch_row('SHOW CREATE TABLE ' . $table['Tables_in_' . $database_default]);

		if (sizeof($create_syntax)) {
			if (substr_count(strtoupper($create_syntax['Create Table']), 'INNODB')) {
				$skip_tables[] = $table['Tables_in_' . $database_default];
			}else{
				$include_tables[] = $table['Tables_in_' . $database_default];
			}
		}
	}
	}

	if (sizeof($include_tables)) {
	foreach($include_tables as $table) {
		$status = db_fetch_row("SHOW TABLE STATUS LIKE '$table'");

		array_push($table_status, $status);
	}
	}

	/* Get poller stats */
	$poller_item = db_fetch_assoc('SELECT action, count(action) AS total FROM poller_item GROUP BY action');

	/* Get system stats */
	$host_count = db_fetch_cell('SELECT COUNT(*) FROM host');
	$graph_count = db_fetch_cell('SELECT COUNT(*) FROM graph_local');
	$data_count = db_fetch_assoc('SELECT i.type_id, COUNT(i.type_id) AS total FROM data_template_data AS d, data_input AS i WHERE d.data_input_id = i.id AND local_data_id <> 0 GROUP BY i.type_id');

	/* Get RRDtool version */
	$rrdtool_version = 'Unknown';
	if ((file_exists(read_config_option('path_rrdtool'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_rrdtool'))))) {

		$out_array = array();
		exec(cacti_escapeshellcmd(read_config_option('path_rrdtool')), $out_array);

		if (sizeof($out_array) > 0) {
			if (preg_match('/^RRDtool 1\.4/', $out_array[0])) {
				$rrdtool_version = 'rrd-1.4.x';
			}else if (preg_match('/^RRDtool 1\.3\./', $out_array[0])) {
				$rrdtool_version = 'rrd-1.3.x';
			}else if (preg_match('/^RRDtool 1\.2\./', $out_array[0])) {
				$rrdtool_version = 'rrd-1.2.x';
			}
		}
	}

	/* Get SNMP cli version */
	$snmp_version = read_config_option('snmp_version');
	if ((file_exists(read_config_option('path_snmpget'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_snmpget'))))) {
		$snmp_version = shell_exec(cacti_escapeshellcmd(read_config_option('path_snmpget')) . ' -V 2>&1');
	}

	/* Check RRDTool issues */
	$rrdtool_error = '';
	if ($rrdtool_version != read_config_option('rrdtool_version')) {
		$rrdtool_error .= "<br><font color='red'>ERROR: Installed RRDTool version does not match configured version.<br>Please visit the <a href='" . htmlspecialchars('settings.php?tab=general') . "'>Configuration Settings</a> and select the correct RRDTool Utility Version.</font><br>";
	}
	$graph_gif_count = db_fetch_cell('SELECT COUNT(*) FROM graph_templates_graph WHERE image_format_id = 2');
	if ($graph_gif_count > 0) {
		$rrdtool_error .= "<br><font color='red'>ERROR: RRDTool 1.2.x+ does not support the GIF images format, but " . $graph_gif_count . ' graph(s) and/or templates have GIF set as the image format.</font><br>';
	}

	/* Get spine version */
	$spine_version = 'Unknown';
	if ((file_exists(read_config_option('path_spine'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_spine'))))) {
		$out_array = array();
		exec(read_config_option('path_spine') . ' --version', $out_array);
		if (sizeof($out_array) > 0) {
			$spine_version = $out_array[0];
		}
	}

	/* Display tech information */
	html_start_box('<strong>Technical Support</strong>', '100%', '', '3', 'center', '');
	html_header(array('General Information'), 2);
	print "<tr class='odd'>\n";
	print "		<td class='textArea'>Date</td>\n";
	print "		<td class='textArea'>" . date('r') . "</td>\n";
	print "</tr>\n";
	api_plugin_hook_function('custom_version_info');
	print "<tr class='even'>\n";
	print "		<td class='textArea'>Cacti Version</td>\n";
	print "		<td class='textArea'>" . $config['cacti_version'] . "</td>\n";
	print "</tr>\n";
	print "<tr class='odd'>\n";
	print "		<td class='textArea'>Cacti OS</td>\n";
	print "		<td class='textArea'>" . $config['cacti_server_os'] . "</td>\n";
	print "</tr>\n";
	print "<tr class='even'>\n";
	print "		<td class='textArea'>SNMP Version</td>\n";
	print "		<td class='textArea'>" . $snmp_version . "</td>\n";
	print "</tr>\n";

	print "<tr class='odd'>\n";
	print "		<td class='textArea'>RRDTool Version</td>\n";
	print "		<td class='textArea'>" . $rrdtool_versions[$rrdtool_version] . ' ' . $rrdtool_error . "</td>\n";
	print "</tr>\n";
	print "<tr class='even'>\n";
	print "		<td class='textArea'>Devices</td>\n";
	print "		<td class='textArea'>" . $host_count . "</td>\n";
	print "</tr>\n";
	print "<tr class='odd'>\n";
	print "		<td class='textArea'>Graphs</td>\n";
	print "		<td class='textArea'>" . $graph_count . "</td>\n";
	print "</tr>\n";
	print "<tr class='even'>\n";
	print "		<td class='textArea'>Data Sources</td>\n";
	print "		<td class='textArea'>";
	$data_total = 0;
	if (sizeof($data_count)) {
		foreach ($data_count as $item) {
			print $input_types[$item['type_id']] . ': ' . $item['total'] . '<br>';
			$data_total += $item['total'];
		}
		print 'Total: ' . $data_total;
	}else{
		print "<font color='red'>0</font>";
	}
	print "</td>\n";
	print "</tr>\n";

	html_header(array('Poller Information'), 2);
	print "<tr class='odd'>\n";
	print "		<td class='textArea'>Interval</td>\n";
	print "		<td class='textArea'>" . read_config_option('poller_interval') . "</td>\n";
	if (file_exists(read_config_option('path_spine')) && $poller_options[read_config_option('poller_type')] == 'spine') {
		$type = $spine_version;
	} else {
		$type = $poller_options[read_config_option('poller_type')];
	}
	print "</tr>\n";
	print "<tr class='even'>\n";
	print "		<td class='textArea'>Type</td>\n";
	print "		<td class='textArea'>" . $type . "</td>\n";
	print "</tr>\n";
	print "<tr class='odd'>\n";
	print "		<td class='textArea'>Items</td>\n";
	print "		<td class='textArea'>";
	$total = 0;
	if (sizeof($poller_item)) {
		foreach ($poller_item as $item) {
			print 'Action[' . $item['action'] . ']: ' . $item['total'] . '<br>';
			$total += $item['total'];
		}
		print 'Total: ' . $total;
	}else{
		print "<font color='red'>No items to poll</font>";
	}
	print "</td>\n";
	print "</tr>\n";

	print "<tr class='even'>\n";
	print "		<td class='textArea'>Concurrent Processes</td>\n";
	print "		<td class='textArea'>" . read_config_option('concurrent_processes') . "</td>\n";
	print "</tr>\n";

	print "<tr class='odd'>\n";
	print "		<td class='textArea'>Max Threads</td>\n";
	print "		<td class='textArea'>" . read_config_option('max_threads') . "</td>\n";
	print "</tr>\n";

	print "<tr class='even'>\n";
	print "		<td class='textArea'>PHP Servers</td>\n";
	print "		<td class='textArea'>" . read_config_option('php_servers') . "</td>\n";
	print "</tr>\n";

	print "<tr class='odd'>\n";
	print "		<td class='textArea'>Script Timeout</td>\n";
	print "		<td class='textArea'>" . read_config_option('script_timeout') . "</td>\n";
	print "</tr>\n";

	print "<tr class='even'>\n";
	print "		<td class='textArea'>Max OID</td>\n";
	print "		<td class='textArea'>" . read_config_option('max_get_size') . "</td>\n";
	print "</tr>\n";


	print "<tr class='odd'>\n";
	print "		<td class='textArea'>Last Run Statistics</td>\n";
	print "		<td class='textArea'>" . read_config_option('stats_poller') . "</td>\n";
	print "</tr>\n";


	html_header(array('PHP Information'), 2);
	print "<tr class='odd'>\n";
	print "		<td class='textArea'>PHP Version</td>\n";
	print "		<td class='textArea'>" . phpversion() . "</td>\n";
	print "</tr>\n";
	print "<tr class='even'>\n";
	print "		<td class='textArea'>PHP OS</td>\n";
	print "		<td class='textArea'>" . PHP_OS . "</td>\n";
	print "</tr>\n";
	print "<tr class='odd'>\n";
	print "		<td class='textArea'>PHP uname</td>\n";
	print "		<td class='textArea'>";
	if (function_exists('php_uname')) {
		print php_uname();
	}else{
		print 'N/A';
	}
	print "</td>\n";
	print "</tr>\n";
	print "<tr class='even'>\n";
	print "		<td class='textArea'>PHP SNMP</td>\n";
	print "		<td class='textArea'>";
	if (function_exists('snmpget')) {
		print 'Installed';
	} else {
		print 'Not Installed';
	}
	print "</td>\n";
	print "</tr>\n";
	print "<tr class='odd'>\n";
	print "		<td class='textArea'>max_execution_time</td>\n";
	print "		<td class='textArea'>" . ini_get('max_execution_time') . "</td>\n";
	print "</tr>\n";
	print "<tr class='even'>\n";
	print "		<td class='textArea'>memory_limit</td>\n";
	print "		<td class='textArea'>" . ini_get('memory_limit');

	/* Calculate memory suggestion based off of data source count */
	$memory_suggestion = $data_total * 32768;
	/* Set minimum - 16M */
	if ($memory_suggestion < 16777216) {
		$memory_suggestion = 16777216;
	}
	/* Set maximum - 512M */
	if ($memory_suggestion > 536870912) {
		$memory_suggestion = 536870912;
	}
	/* Suggest values in 8M increments */
	$memory_suggestion = round($memory_suggestion / 8388608) * 8388608;
	if (memory_bytes(ini_get('memory_limit')) < $memory_suggestion) {
		print "<br><font color='red'>";
		if ((ini_get('memory_limit') == -1)) {
			print "You've set memory limit to 'unlimited'.<br/>";
		}
		print 'It is highly suggested that you alter you php.ini memory_limit to ' . memory_readable($memory_suggestion) . ' or higher. <br/>
			This suggested memory value is calculated based on the number of data source present and is only to be used as a suggestion, actual values may vary system to system based on requirements.';
		print '</font><br>';
	}
	print "</td>\n";
	print "</tr>\n";

	html_header(array('MySQL Table Information'), 2);
	print "<tr class='odd'>\n";
	print "		<td class='textArea' colspan='2' align='center'>";
	if (sizeof($table_status) > 0) {
		print "<table border='1' cellpadding='2' cellspacing='0'>\n";
		print "<tr>\n";
		print "  <th>Name</th>\n";
		print "  <th>Rows</th>\n";
		print "  <th>Engine</th>\n";
		print "  <th>Collation</th>\n";
		print "</tr>\n";
		foreach ($table_status as $item) {
			print "<tr>\n";
			print '  <td>' . $item['Name'] . "</td>\n";
			print '  <td>' . $item['Rows'] . "</td>\n";
			if (isset($item['Engine'])) {
				print '  <td>' . $item['Engine'] . "</td>\n";
			}else{
				print "  <td>Unknown</td>\n";
			}
			if (isset($item['Collation'])) {
				print '  <td>' . $item['Collation'] . "</td>\n";
			} else {
				print "  <td>Unknown</td>\n";
			}
			print "</tr>\n";
		}

		if (sizeof($skip_tables)) {
			print "<tr><td colspan='20' align='center'><strong>The Following Tables were Skipped Due to being INNODB</strong></td></tr>";

			foreach($skip_tables as $table) {
				print "<tr><td colspan='20' align='center'>" . $table . '</td></tr>';
			}
		}

		print "</table>\n";
	}else{
		print 'Unable to retrieve table status';
	}

	print "</td>\n";
	print "</tr>\n";

	html_header(array('PHP Module Information'), 2);
	print "<tr class='odd'>\n";
	print "		<td class='textArea' colspan='2'>" . $php_info . "</td>\n";
	print "</tr>\n";

	html_end_box();

}


function utilities_view_user_log() {
	global $auth_realms, $item_rows;

	define('MAX_DISPLAY_PAGES', 21);

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('result'));
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('rows'));
	/* ==================================================== */

	/* clean up username */
	if (isset($_REQUEST['username'])) {
		$_REQUEST['username'] = sanitize_search_string(get_request_var('username'));
	}

	/* clean up search filter */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* clean up sort_column */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	}

	/* clean up sort direction */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_userlog_current_page');
		kill_session_var('sess_userlog_username');
		kill_session_var('sess_userlog_result');
		kill_session_var('sess_userlog_filter');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_userlog_sort_column');
		kill_session_var('sess_userlog_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['result']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['username']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_userlog_current_page', '1');
	load_current_session_value('username', 'sess_userlog_username', '-1');
	load_current_session_value('result', 'sess_userlog_result', '-1');
	load_current_session_value('filter', 'sess_userlog_filter', '');
	load_current_session_value('sort_column', 'sess_userlog_sort_column', 'time');
	load_current_session_value('sort_direction', 'sess_userlog_sort_direction', 'DESC');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	$_REQUEST['page_referrer'] = 'view_user_log';
	load_current_session_value('page_referrer', 'page_referrer', 'view_user_log');

	?>
	<script type="text/javascript">

	function clearFilter() {
		strURL = '?clear_x=1&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function purgeLog() {
		strURL = '?action=clear_user_log&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#purge').click(function() {
			purgeLog();
		});

		$('#form_userlog').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	function applyFilter() {
		strURL = '?username=' + $('#username').val();
		strURL = strURL + '&result=' + $('#result').val();
		strURL = strURL + '&rows=' + $('#rows').val();
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&action=view_user_log';
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	</script>
	<?php

	html_start_box('<strong>User Login History</strong>', '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id="form_userlog" action="utilities.php">
			<table cellpadding="2" cellspacing="0" border="0">
				<tr>
					<td width="50">
						User
					</td>
					<td>
						<select id='username' name="username" onChange="applyFilter()">
							<option value="-1"<?php if (get_request_var_request('username') == '-1') {?> selected<?php }?>>All</option>
							<option value="-2"<?php if (get_request_var_request('username') == '-2') {?> selected<?php }?>>Deleted/Invalid</option>
							<?php
							$users = db_fetch_assoc('SELECT DISTINCT username FROM user_auth ORDER BY username');

							if (sizeof($users) > 0) {
							foreach ($users as $user) {
								print "<option value='" . $user['username'] . "'"; if (get_request_var_request('username') == $user['username']) { print ' selected'; } print '>' . $user['username'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						Result
					</td>
					<td>
						<select id='result' name="result" onChange="applyFilter()">
							<option value="-1"<?php if (get_request_var_request('result') == '-1') {?> selected<?php }?>>Any</option>
							<option value="1"<?php if (get_request_var_request('result') == '1') {?> selected<?php }?>>Success - Pswd</option>
							<option value="2"<?php if (get_request_var_request('result') == '2') {?> selected<?php }?>>Success - Token</option>
							<option value="0"<?php if (get_request_var_request('result') == '0') {?> selected<?php }?>>Failed</option>
						</select>
					</td>
					<td>
						Attempts
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type="button" id='refresh' name="go" value="Go" title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" id='clear' name="clear_x" value="Clear" title="Clear Filters">
					</td>
					<td>
						<input type="button" id='purge' name="purge_x" value="Purge" title="Purge User Log">
					</td>
				</tr>
			</table>
			<table cellpadding="2" cellspacing="0" border="0">
				<tr>
					<td width='50'>
						Search
					</td>
					<td>
						<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='view_user_log'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* filter by username */
	if (get_request_var_request('username') == '-2') {
		$sql_where = 'WHERE user_log.username NOT IN (SELECT DISTINCT username FROM user_auth)';
	}elseif (get_request_var_request('username') != '-1') {
		$sql_where = "WHERE user_log.username='" . get_request_var_request('username') . "'";
	}

	/* filter by result */
	if (get_request_var_request('result') != '-1') {
		if (strlen($sql_where)) {
			$sql_where .= ' AND user_log.result=' . get_request_var_request('result');
		}else{
			$sql_where = 'WHERE user_log.result=' . get_request_var_request('result');
		}
	}

	/* filter by search string */
	if (get_request_var_request('filter') <> '') {
		if (strlen($sql_where)) {
			$sql_where .= " AND (user_log.username LIKE '%%" . get_request_var_request('filter') . "%%'
				OR user_log.time LIKE '%%" . get_request_var_request('filter') . "%%'
				OR user_auth.full_name LIKE '%%" . get_request_var_request('filter') . "%%'
				OR user_log.ip LIKE '%%" . get_request_var_request('filter') . "%%')";
		}else{
			$sql_where = "WHERE (user_log.username LIKE '%%" . get_request_var_request('filter') . "%%'
				OR user_log.time LIKE '%%" . get_request_var_request('filter') . "%%'
				OR user_auth.full_name LIKE '%%" . get_request_var_request('filter') . "%%'
				OR user_log.ip LIKE '%%" . get_request_var_request('filter') . "%%')";
		}
	}

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM user_auth
		RIGHT JOIN user_log
		ON user_auth.username = user_log.username
		$sql_where");

	$user_log_sql = "SELECT
		user_log.username,
		user_auth.full_name,
		user_auth.realm,
		user_log.time,
		user_log.result,
		user_log.ip
		FROM user_auth
		RIGHT JOIN user_log
		ON user_auth.username = user_log.username
		$sql_where
		ORDER BY " . get_request_var_request('sort_column') . ' ' . get_request_var_request('sort_direction') . '
		LIMIT ' . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows');

	$user_log = db_fetch_assoc($user_log_sql);

	$nav = html_nav_bar('utilities.php?action=view_user_log&username=' . get_request_var_request('username') . '&filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 6, 'User Logins', 'page', 'main');

	print $nav;

	$display_text = array(
		'username' => array('User', 'ASC'),
		'full_name' => array('Full Name', 'ASC'),
		'realm' => array('Authentication Realm', 'ASC'),
		'time' => array('Date', 'ASC'),
		'result' => array('Result', 'DESC'),
		'ip' => array('IP Address', 'DESC'));

	html_header_sort($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'));

	if (sizeof($user_log) > 0) {
		foreach ($user_log as $item) {
			form_alternate_row('', true);
			?>
			<td style='white-space:nowrap;'>
				<?php print (strlen(get_request_var_request('filter')) ? (preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", $item['username'])) : $item['username']);?>
			</td>
			<td style='white-space:nowrap;'>
				<?php if (isset($item['full_name'])) {
						print (strlen(get_request_var_request('filter')) ? (preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span style='filteredValue'>\\1</span>", htmlspecialchars($item['full_name']))) : htmlspecialchars($item['full_name']));
					}else{
						print '(User Removed)';
					}
				?>
			</td>
			<td style='white-space:nowrap;'>
				<?php if (isset($auth_realms[$item['realm']])) {
						print (strlen(get_request_var_request('filter')) ? (preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span style='filteredValue'>\\1</span>", $auth_realms[$item['realm']])) : $auth_realms[$item['realm']]);
					}else{
						print 'N/A';
					}
				?>
			</td>
			<td style='white-space:nowrap;'>
				<?php print (strlen(get_request_var_request('filter')) ? (preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span style='filteredValue'>\\1</span>", $item['time'])) : $item['time']);?>
			</td>
			<td style='white-space:nowrap;'>
				<?php print $item['result'] == 0 ? 'Failed' : $item['result'] == 1 ? 'Success - Pswd':'Success - Token';?>
			</td>
			<td style='white-space:nowrap;'>
				<?php print (strlen(get_request_var_request('filter')) ? (preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", $item['ip'])) : $item['ip']);?>
			</td>
			</tr>
			<?php
		}

		print $nav;
	}

	html_end_box();
}

function utilities_clear_user_log() {
	$users = db_fetch_assoc('SELECT DISTINCT username FROM user_auth');

	if (sizeof($users)) {
		/* remove active users */
		foreach ($users as $user) {
			$total_rows = db_fetch_cell_prepared('SELECT COUNT(username) FROM user_log WHERE username = ? AND result = 1', array($user['username']));
			if ($total_rows > 1) {
				db_execute_prepared('DELETE FROM user_log WHERE username = ? AND result = 1 ORDER BY time LIMIT ' . ($total_rows - 1), array($user['username']));
			}
			db_execute_prepared('DELETE FROM user_log WHERE username = ? AND result = 0', array($user['username']));
		}

		/* delete inactive users */
		db_execute('DELETE FROM user_log WHERE user_id NOT IN (SELECT id FROM user_auth) OR username NOT IN (SELECT username FROM user_auth)');

	}
}

function utilities_view_logfile() {
	global $log_tail_lines, $page_refresh_interval, $refresh;

	$logfile = read_config_option('path_cactilog');

	if ($logfile == '') {
		$logfile = './log/rrd.log';
	}

	/* helps determine output color */
	$linecolor = True;

	input_validate_input_number(get_request_var_request('tail_files'));
	input_validate_input_number(get_request_var_request('message_type'));
	input_validate_input_number(get_request_var_request('refresh'));
	input_validate_input_number(get_request_var_request('reverse'));

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_logfile_tail_lines');
		kill_session_var('sess_logfile_message_type');
		kill_session_var('sess_logfile_filter');
		kill_session_var('sess_logfile_refresh');
		kill_session_var('sess_logfile_reverse');

		unset($_REQUEST['tail_lines']);
		unset($_REQUEST['message_type']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['refresh']);
		unset($_REQUEST['reverse']);
	}

	load_current_session_value('tail_lines', 'sess_logfile_tail_lines', read_config_option('num_rows_log'));
	load_current_session_value('message_type', 'sess_logfile_message_type', '-1');
	load_current_session_value('filter', 'sess_logfile_filter', '');
	load_current_session_value('refresh', 'sess_logfile_refresh', read_config_option('log_refresh_interval'));
	load_current_session_value('reverse', 'sess_logfile_reverse', 1);
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	$_REQUEST['page_referrer'] = 'view_logfile';
	load_current_session_value('page_referrer', 'page_referrer', 'view_logfile');

	$refresh['seconds'] = get_request_var_request('refresh');
	$refresh['page'] = 'utilities.php?action=view_logfile';

	top_header();

	?>
	<script type="text/javascript">
	<!--

	function purgeLog() {
		strURL = '?action=view_logfile&purge_x=1&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
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
		strURL = '?tail_lines=' + $('#tail_lines').val();
		strURL = strURL + '&message_type=' + $('#message_type').val();
		strURL = strURL + '&refresh=' + $('#refresh').val();
		strURL = strURL + '&reverse=' + $('#reverse').val();
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&action=view_logfile';
		strURL = strURL + '&header=false';
		refreshMSeconds=$('#refresh').val()*1000;
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = '?clear_x=1';
		strURL = strURL + '&action=view_logfile';
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	-->
	</script>
	<?php

	html_start_box('<strong>Log File Filters</strong>', '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id="form_logfile" action="utilities.php">
			<table cellpadding="2" cellspacing="0" border="0">
				<tr>
					<td style='white-space: nowrap;' width="80">
						Tail Lines
					</td>
					<td>
						<select id='tail_lines' name="tail_lines" onChange="applyFilter()">
							<?php
							foreach($log_tail_lines AS $tail_lines => $display_text) {
								print "<option value='" . $tail_lines . "'"; if (get_request_var_request('tail_lines') == $tail_lines) { print ' selected'; } print '>' . $display_text . "</option>\n";
							}
							?>
						</select>
					</td>
					<td style='white-space: nowrap;'>
						Message Type
					</td>
					<td>
						<select id='message_type' name="message_type" onChange="applyFilter()">
							<option value="-1"<?php if (get_request_var_request('message_type') == '-1') {?> selected<?php }?>>All</option>
							<option value="1"<?php if (get_request_var_request('message_type') == '1') {?> selected<?php }?>>Stats</option>
							<option value="2"<?php if (get_request_var_request('message_type') == '2') {?> selected<?php }?>>Warnings</option>
							<option value="3"<?php if (get_request_var_request('message_type') == '3') {?> selected<?php }?>>Errors</option>
							<option value="4"<?php if (get_request_var_request('message_type') == '4') {?> selected<?php }?>>Debug</option>
							<option value="5"<?php if (get_request_var_request('message_type') == '5') {?> selected<?php }?>>SQL Calls</option>
						</select>
					</td>
					<td>
						<input type="button" id='refreshme' name="go" value="Go" title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" id='clear' name="clear_x" value="Clear" title="Clear Filters">
					</td>
					<td>
						<input type="button" id='purge' name="purge_x" value="Purge" title="Purge Log File">
					</td>
				</tr>
				<tr>
					<td>
						Refresh
					</td>
					<td>
						<select id='refresh' name="refresh" onChange="applyFilter()">
							<?php
							foreach($page_refresh_interval AS $seconds => $display_text) {
								print "<option value='" . $seconds . "'"; if (get_request_var_request('refresh') == $seconds) { print ' selected'; } print '>' . $display_text . "</option>\n";
							}
							?>
						</select>
					</td>
					<td style='white-space: nowrap;'>
						Display Order
					</td>
					<td>
						<select id='reverse' name="reverse" onChange="applyFilter()">
							<option value="1"<?php if (get_request_var_request('reverse') == '1') {?> selected<?php }?>>Newest First</option>
							<option value="2"<?php if (get_request_var_request('reverse') == '2') {?> selected<?php }?>>Oldest First</option>
						</select>
					</td>
				</tr>
			</table>
			<table cellpadding="2" cellspacing="0" border="0">
				<tr>
					<td width="80">
						Search
					</td>
					<td>
						<input id='filter' type="text" name="filter" size="75" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>">
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
	$logcontents = tail_file($logfile, get_request_var_request('tail_lines'), get_request_var_request('message_type'), get_request_var_request('filter'));

	if (get_request_var_request('reverse') == 1) {
		$logcontents = array_reverse($logcontents);
	}

	if (get_request_var_request('message_type') > 0) {
		$start_string = '<strong>Log File</strong> [Total Lines: ' . sizeof($logcontents) . ' - Non-Matching Items Hidden]';
	}else{
		$start_string = '<strong>Log File</strong> [Total Lines: ' . sizeof($logcontents) . ' - All Items Shown]';
	}

	html_start_box($start_string, '100%', '', '3', 'center', '');

	$i = 0;
	$j = 0;
	$linecolor = false;
	foreach ($logcontents as $item) {
		$host_start = strpos($item, 'Device[');
		$ds_start   = strpos($item, 'DS[');

		$new_item = '';

		if ((!$host_start) && (!$ds_start)) {
			$new_item = $item;
		}else{
			while ($host_start) {
				$host_end   = strpos($item, ']', $host_start);
				$host_id    = substr($item, $host_start+5, $host_end-($host_start+5));
				$new_item   = $new_item . substr($item, 0, $host_start + 5) . "<a href='" . htmlspecialchars('host.php?action=edit&id=' . $host_id) . "'>" . substr($item, $host_start + 5, $host_end-($host_start + 5)) . '</a>';
				$item       = substr($item, $host_end);
				$host_start = strpos($item, 'Device[');
			}

			$ds_start = strpos($item, 'DS[');
			while ($ds_start) {
				$ds_end   = strpos($item, ']', $ds_start);
				$ds_id    = substr($item, $ds_start+3, $ds_end-($ds_start+3));
				$new_item = $new_item . substr($item, 0, $ds_start + 3) . "<a href='" . htmlspecialchars('data_sources.php?action=ds_edit&id=' . $ds_id) . "'>" . substr($item, $ds_start + 3, $ds_end-($ds_start + 3)) . '</a>';
				$item     = substr($item, $ds_end);
				$ds_start = strpos($item, 'DS[');
			}

			$new_item = $new_item . $item;
		}

		/* get the background color */
		if ((substr_count($new_item, 'ERROR')) || (substr_count($new_item, 'FATAL'))) {
			$bgcolor = 'FF3932';
		}elseif (substr_count($new_item, 'WARN')) {
			$bgcolor = 'EACC00';
		}elseif (substr_count($new_item, ' SQL ')) {
			$bgcolor = '6DC8FE';
		}elseif (substr_count($new_item, 'DEBUG')) {
			$bgcolor = 'C4FD3D';
		}elseif (substr_count($new_item, 'STATS')) {
			$bgcolor = '96E78A';
		}else{
			if ($linecolor) {
				$bgcolor = 'CCCCCC';
			}else{
				$bgcolor = 'FFFFFF';
			}
			$linecolor = !$linecolor;
		}

		?>
		<tr bgcolor='#<?php print $bgcolor;?>'>
			<td>
				<?php print $new_item;?>
			</td>
		</tr>
		<?php
		$j++;
		$i++;

		if ($j > 1000) {
			?>
			<tr bgcolor='#EACC00'>
				<td>
					<?php print '>>>>  LINE LIMIT OF 1000 LINES REACHED!!  <<<<';?>
				</td>
			</tr>
			<?php

			break;
		}
	}

	html_end_box();

	bottom_footer();
}

function utilities_clear_logfile() {
	load_current_session_value('refresh', 'sess_logfile_refresh', read_config_option('log_refresh_interval'));

	$refresh['seconds'] = get_request_var_request('refresh');
	$refresh['page'] = 'utilities.php?action=view_logfile';

	top_header();

	$logfile = read_config_option('path_cactilog');

	if ($logfile == '') {
		$logfile = './log/cacti.log';
	}

	html_start_box('<strong>Clear Cacti Log File</strong>', '100%', '', '3', 'center', '');
	if (file_exists($logfile)) {
		if (is_writable($logfile)) {
			$timestamp = date('m/d/Y h:i:s A');
			$log_fh = fopen($logfile, 'w');
			fwrite($log_fh, $timestamp . " - WEBUI: Cacti Log Cleared from Web Management Interface\n");
			fclose($log_fh);
			print '<tr><td>Cacti Log File Cleared</td></tr>';
		}else{
			print "<tr><td><font color='red'><b>Error: Unable to clear log, no write permissions.<b></font></td></tr>";
		}
	}else{
		print "<tr><td><font color='red'><b>Error: Unable to clear log, file does not exist.</b></font></td></tr>";
	}
	html_end_box();
}

function utilities_view_snmp_cache() {
	global $poller_actions, $item_rows;

	define('MAX_DISPLAY_PAGES', 21);

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('host_id'));
	input_validate_input_number(get_request_var_request('snmp_query_id'));
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('poller_action'));
	/* ==================================================== */

	/* clean up search filter */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_snmp_current_page');
		kill_session_var('sess_snmp_host_id');
		kill_session_var('sess_snmp_snmp_query_id');
		kill_session_var('sess_snmp_filter');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['host_id']);
		unset($_REQUEST['snmp_query_id']);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_snmp_current_page', '1');
	load_current_session_value('host_id', 'sess_snmp_host_id', '-1');
	load_current_session_value('snmp_query_id', 'sess_snmp_snmp_query_id', '-1');
	load_current_session_value('filter', 'sess_snmp_filter', '');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	$_REQUEST['page_referrer'] = 'view_snmp_cache';
	load_current_session_value('page_referrer', 'page_referrer', 'view_snmp_cache');

	?>
	<script type="text/javascript">
	<!--

	function applyFilter() {
		strURL = '?host_id=' + $('#host_id').val();
		strURL = strURL + '&snmp_query_id=' + $('#snmp_query_id').val();
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&rows=' + $('#rows').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&action=view_snmp_cache';
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = '?action=view_snmp_cache&clear_x=1&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
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

	-->
	</script>
	<?php

	html_start_box('<strong>SNMP Cache Items</strong>', '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id="form_snmpcache" action="utilities.php">
			<table cellpadding="2" cellspacing="0">
				<tr>
					<td width="50">
						Device
					</td>
					<td>
						<select id='host_id' name="host_id" onChange="applyFilter()">
							<option value="-1"<?php if (get_request_var_request('host_id') == '-1') {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request('host_id') == '0') {?> selected<?php }?>>None</option>
							<?php
							if (get_request_var_request('snmp_query_id') == -1) {
								$hosts = db_fetch_assoc('SELECT DISTINCT
											host.id,
											host.description,
											host.hostname
											FROM (host_snmp_cache, snmp_query,host)
											WHERE host_snmp_cache.host_id = host.id
											AND host_snmp_cache.snmp_query_id = snmp_query.id
											ORDER by host.description');
							}else{
								$hosts = db_fetch_assoc_prepared('SELECT DISTINCT
											host.id,
											host.description,
											host.hostname
											FROM (host_snmp_cache, snmp_query,host)
											WHERE host_snmp_cache.host_id = host.id
											AND host_snmp_cache.snmp_query_id = snmp_query.id
											AND host_snmp_cache.snmp_query_id = ?
											ORDER by host.description', array(get_request_var_request('snmp_query_id')));
							}
							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='" . $host['id'] . "'"; if (get_request_var_request('host_id') == $host['id']) { print ' selected'; } print '>' . $host['description'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						Query Name
					</td>
					<td>
						<select id='snmp_query_id' name="snmp_query_id" onChange="applyFilter()">
							<option value="-1"<?php if (get_request_var_request('host_id') == '-1') {?> selected<?php }?>>Any</option>
							<?php
							if (get_request_var_request('host_id') == -1) {
								$snmp_queries = db_fetch_assoc('SELECT DISTINCT
											snmp_query.id,
											snmp_query.name
											FROM (host_snmp_cache, snmp_query,host)
											WHERE host_snmp_cache.host_id = host.id
											AND host_snmp_cache.snmp_query_id = snmp_query.id
											ORDER by snmp_query.name');
							}else{
								$snmp_queries = db_fetch_assoc_prepared("SELECT DISTINCT
											snmp_query.id,
											snmp_query.name
											FROM (host_snmp_cache, snmp_query,host)
											WHERE host_snmp_cache.host_id = host.id
											AND host_snmp_cache.host_id = ?
											AND host_snmp_cache.snmp_query_id = snmp_query.id
											ORDER by snmp_query.name", array(get_request_var_request('host_id')));
							}
							if (sizeof($snmp_queries) > 0) {
							foreach ($snmp_queries as $snmp_query) {
								print "<option value='" . $snmp_query['id'] . "'"; if (get_request_var_request('snmp_query_id') == $snmp_query['id']) { print ' selected'; } print '>' . $snmp_query['name'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						Rows
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type="button" id='refresh' name="go" value="Go" title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" id='clear' name="clear_x" value="Clear" title="Clear Fitlers">
					</td>
				</tr>
			</table>
			<table cellpadding='2' cellspacing='0' border='0'>
				<tr>
					<td width='50'>
						Search
					</td>
					<td>
						<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='view_snmp_cache'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* filter by host */
	if (get_request_var_request('host_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var_request('host_id') == '0') {
		$sql_where .= ' AND host.id=0';
	}elseif (!empty($_REQUEST['host_id'])) {
		$sql_where .= ' AND host.id=' . get_request_var_request('host_id');
	}

	/* filter by query name */
	if (get_request_var_request('snmp_query_id') == '-1') {
		/* Show all items */
	}elseif (!empty($_REQUEST['snmp_query_id'])) {
		$sql_where .= ' AND host_snmp_cache.snmp_query_id=' . get_request_var_request('snmp_query_id');
	}

	/* filter by search string */
	if (get_request_var_request('filter') != '') {
		$sql_where .= " AND (host.description LIKE '%%" . get_request_var_request('filter') . "%%'
			OR snmp_query.name LIKE '%%" . get_request_var_request('filter') . "%%'
			OR host_snmp_cache.field_name LIKE '%%" . get_request_var_request('filter') . "%%'
			OR host_snmp_cache.field_value LIKE '%%" . get_request_var_request('filter') . "%%'
			OR host_snmp_cache.oid LIKE '%%" . get_request_var_request('filter') . "%%')";
	}

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM (host_snmp_cache, snmp_query,host)
		WHERE host_snmp_cache.host_id = host.id
		AND host_snmp_cache.snmp_query_id = snmp_query.id
		$sql_where");

	$snmp_cache_sql = "SELECT
		host_snmp_cache.*,
		host.description,
		snmp_query.name
		FROM (host_snmp_cache, snmp_query,host)
		WHERE host_snmp_cache.host_id = host.id
		AND host_snmp_cache.snmp_query_id = snmp_query.id
		$sql_where
		LIMIT " . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows');

	$snmp_cache = db_fetch_assoc($snmp_cache_sql);

	$nav = html_nav_bar('utilities.php?action=view_snmp_cache&host_id=' . get_request_var_request('host_id') . '&filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 3, 'Entries', 'page', 'main');

	print $nav;

	html_header(array('Details'));

	$i = 0;
	if (sizeof($snmp_cache) > 0) {
		foreach ($snmp_cache as $item) {
			if ($i % 2 == 0) {
				$class = 'even';
			}else{
				$class = 'odd';
			}

			print "<tr class='$class'>\n";
			?>
			<td>
				Device: <?php print (strlen(get_request_var_request('filter')) ? (preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", $item['description'])) : $item['description']);?>
				, SNMP Query: <?php print (strlen(get_request_var_request('filter')) ? (preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", $item['name'])) : $item['name']);?>
			</td>
			</tr>
			<?php
			print "<tr class='$class'>\n";
			?>
			<td>
				Index: <?php print $item['snmp_index'];?>
				, Field Name: <?php print (strlen(get_request_var_request('filter')) ? (preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", $item['field_name'])) : $item['field_name']);?>
				, Field Value: <?php print (strlen(get_request_var_request('filter')) ? (preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", $item['field_value'])) : $item['field_value']);?>
			</td>
			</tr>
			<?php
			print "<tr class='$class'>\n";
			?>
			<td>
				OID: <?php print (strlen(get_request_var_request('filter')) ? (preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", $item['oid'])) : $item['oid']);?>
			</td>
			</tr>
			<?php
			$i++;
		}

		print $nav;
	}

	html_end_box();
}

function utilities_view_poller_cache() {
	global $poller_actions, $item_rows;

	define('MAX_DISPLAY_PAGES', 21);

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('host_id'));
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('poller_action'));
	/* ==================================================== */

	/* clean up search filter */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* clean up sort_column */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	}

	/* clean up sort direction */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_poller_current_page');
		kill_session_var('sess_poller_host_id');
		kill_session_var('sess_poller_poller_action');
		kill_session_var('sess_poller_filter');
		kill_session_var('sess_default_rows');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['host_id']);
		unset($_REQUEST['poller_action']);
	}

	if ((!empty($_SESSION['sess_poller_action'])) && (!empty($_REQUEST['poller_action']))) {
		if ($_SESSION['sess_poller_poller_action'] != $_REQUEST['poller_action']) {
			$_REQUEST['page'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_poller_current_page', '1');
	load_current_session_value('host_id', 'sess_poller_host_id', '-1');
	load_current_session_value('poller_action', 'sess_poller_poller_action', '-1');
	load_current_session_value('filter', 'sess_poller_filter', '');
	load_current_session_value('sort_column', 'sess_poller_sort_column', 'data_template_data.name_cache');
	load_current_session_value('sort_direction', 'sess_poller_sort_direction', 'ASC');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	$_REQUEST['page_referrer'] = 'view_poller_cache';
	load_current_session_value('page_referrer', 'page_referrer', 'view_poller_cache');

	?>
	<script type="text/javascript">

	function applyFilter() {
		strURL = '?poller_action=' + $('#poller_action').val();
		strURL = strURL + '&host_id=' + $('#host_id').val();
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&rows=' + $('#rows').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&action=view_poller_cache';
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = '?clear_x=1&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
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

	html_start_box('<strong>Poller Cache Items</strong>', '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id="form_pollercache" action="utilities.php">
			<table cellpadding="2" cellspacing="0">
				<tr>
					<td width="50">
						Device
					</td>
					<td>
						<select id='host_id' name="host_id" onChange="applyFilter()">
							<option value="-1"<?php if (get_request_var_request('host_id') == '-1') {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request('host_id') == '0') {?> selected<?php }?>>None</option>
							<?php
							$hosts = db_fetch_assoc('SELECT id, description, hostname FROM host ORDER BY description');

							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='" . $host['id'] . "'"; if (get_request_var_request('host_id') == $host['id']) { print ' selected'; } print '>' . $host['description'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						Action
					</td>
					<td>
						<select id='poller_action' name="poller_action" onChange="applyFilter()">
							<option value="-1"<?php if (get_request_var_request('poller_action') == '-1') {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request('poller_action') == '0') {?> selected<?php }?>>SNMP</option>
							<option value="1"<?php if (get_request_var_request('poller_action') == '1') {?> selected<?php }?>>Script</option>
							<option value="2"<?php if (get_request_var_request('poller_action') == '2') {?> selected<?php }?>>Script Server</option>
						</select>
					</td>
					<td>
						Rows
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type="button" id='refresh' name="go" value="Go" title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" id='clear' name="clear_x" value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
			<table cellpadding='2' cellspacing='2' border='0'>
				<tr>
					<td width='50'>
						Search
					</td>
					<td>
						<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='view_poller_cache'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = 'WHERE poller_item.local_data_id = data_template_data.local_data_id';

	if (get_request_var_request('poller_action') != '-1') {
		$sql_where .= " AND poller_item.action='" . get_request_var_request('poller_action') . "'";
	}

	if (get_request_var_request('host_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var_request('host_id') == '0') {
		$sql_where .= ' AND poller_item.host_id = 0';
	}elseif (!empty($_REQUEST['host_id'])) {
		$sql_where .= ' AND poller_item.host_id = ' . get_request_var_request('host_id');
	}

	if (strlen(get_request_var_request('filter'))) {
		$sql_where .= " AND (data_template_data.name_cache LIKE '%%" . get_request_var_request('filter') . "%%'
			OR host.description LIKE '%%" . get_request_var_request('filter') . "%%'
			OR poller_item.arg1 LIKE '%%" . get_request_var_request('filter') . "%%'
			OR poller_item.hostname LIKE '%%" . get_request_var_request('filter') . "%%'
			OR poller_item.rrd_path  LIKE '%%" . get_request_var_request('filter') . "%%')";
	}

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM data_template_data
		RIGHT JOIN (poller_item
		LEFT JOIN host
		ON poller_item.host_id = host.id)
		ON data_template_data.local_data_id = poller_item.local_data_id
		$sql_where");

	$poller_sql = "SELECT
		poller_item.*,
		data_template_data.name_cache,
		host.description
		FROM data_template_data
		RIGHT JOIN (poller_item
		LEFT JOIN host
		ON poller_item.host_id = host.id)
		ON data_template_data.local_data_id = poller_item.local_data_id
		$sql_where
		ORDER BY " . get_request_var_request('sort_column') . ' ' . get_request_var_request('sort_direction') . ', action ASC
		LIMIT ' . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows');

	$poller_cache = db_fetch_assoc($poller_sql);

	$nav = html_nav_bar('utilities.php?action=view_poller_cache&host_id=' . get_request_var_request('host_id') . '&poller_action=' . get_request_var_request('poller_action'), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 3, 'Entries', 'page', 'main');

	print $nav;

	$display_text = array(
		'data_template_data.name_cache' => array('Data Source Name', 'ASC'),
		'nosort' => array('Details', 'ASC'));

	html_header_sort($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'));

	$i = 0;
	if (sizeof($poller_cache) > 0) {
	foreach ($poller_cache as $item) {
		if ($i % 2 == 0) {
			$class = 'odd';
		}else{
			$class = 'even';
		}
		print "<tr class='$class'>\n";
			?>
			<td width="375">
				<a class="linkEditMain" href="<?php print htmlspecialchars('data_sources.php?action=ds_edit&id=' . $item['local_data_id']);?>"><?php print (strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", $item['name_cache']):$item['name_cache']);?></a>
			</td>

			<td>
			<?php
			if ($item['action'] == 0) {
				if ($item['snmp_version'] != 3) {
					$details =
						'SNMP Version: ' . $item['snmp_version'] . ', ' .
						'Community: ' . $item['snmp_community'] . ', ' .
						'OID: ' . (strlen(get_request_var_request('filter')) ? (preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", $item['arg1'])) : $item['arg1']);
				}else{
					$details =
						'SNMP Version: ' . $item['snmp_version'] . ', ' .
						'User: ' . $item['snmp_username'] . ', OID: ' . $item['arg1'];
				}
			}elseif ($item['action'] == 1) {
					$details = 'Script: ' . (strlen(get_request_var_request('filter')) ? (preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", $item['arg1'])) : $item['arg1']);
			}else{
					$details = 'Script Server: ' . (strlen(get_request_var_request('filter')) ? (preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", $item['arg1'])) : $item['arg1']);
			}

			print $details;
			?>
			</td>
		</tr>
		<?php
		print "<tr class='$class'>\n";
		?>
			<td>
			</td>
			<td>
				RRD: <?php print $item['rrd_path'];?>
			</td>
		</tr>
		<?php
		$i++;
	}
	}

	print $nav;

	html_end_box();
}

function utilities() {
	html_start_box('<strong>Cacti System Utilities</strong>', '100%', '', '3', 'center', '');

	?>
	<colgroup span='3'>
		<col valign='top' width='20%'></col>
		<col valign='top' width='80%'></col>
	</colgroup>

	<?php html_header(array('Technical Support'), 2); form_alternate_row(); ?>
		<td class='textArea'>
			<a href='<?php print htmlspecialchars('utilities.php?action=view_tech');?>'>Technical Support</a>
		</td>
		<td class='textArea'>
			Cacti technical support page.  Used by developers and technical support persons to assist with issues in Cacti.  Includes checks for common configuration issues.
		</td>
	</tr>

	<?php html_header(array('Log Administration'), 2); form_alternate_row(); ?>
		<td class='textArea'>
			<a href='<?php print htmlspecialchars('utilities.php?action=view_logfile');?>'>View Cacti Log File</a>
		</td>
		<td class='textArea'>
			The Cacti Log File stores statistic, error and other message depending on system settings.  This information can be used to identify problems with the poller and application.
		</td>
	</tr>
	<?php form_alternate_row(); ?>
		<td class='textArea'>
			<a href='<?php print htmlspecialchars('utilities.php?action=view_user_log');?>'>View User Log</a>
		</td>
		<td class='textArea'>
			Allows Administrators to browse the user log.  Administrators can filter and export the log as well.
		</td>
	</tr>

	<?php html_header(array('Poller Cache Administration'), 2); form_alternate_row(); ?>
		<td class='textArea'>
			<a href='<?php print htmlspecialchars('utilities.php?action=view_poller_cache');?>'>View Poller Cache</a>
		</td>
		<td class='textArea'>
			This is the data that is being passed to the poller each time it runs. This data is then in turn executed/interpreted and the results are fed into the rrd files for graphing or the database for display.
		</td>
	</tr>
	<?php form_alternate_row(); ?>
		<td class='textArea'>
			<a href='<?php print htmlspecialchars('utilities.php?action=view_snmp_cache');?>'>View SNMP Cache</a>
		</td>
		<td class='textArea'>
			The SNMP cache stores information gathered from SNMP queries. It is used by cacti to determine the OID to use when gathering information from an SNMP-enabled host.
		</td>
	</tr>
	<?php form_alternate_row(); ?>
		<td class='textArea'>
			<a href='<?php print htmlspecialchars('utilities.php?action=clear_poller_cache');?>'>Rebuild Poller Cache</a>
		</td>
		<td class='textArea'>
			The poller cache will be cleared and re-generated if you select this option. Sometimes host/data source data can get out of sync with the cache in which case it makes sense to clear the cache and start over.
		</td>
	</tr>
	<?php html_header(array('Boost Utilities'), 2); form_alternate_row(); ?>
		<td class='textArea'>
			<a href='<?php print htmlspecialchars('utilities.php?action=view_boost_status');?>'>View Boost Status</a>
		</td>
		<td class='textArea'>
			This menu pick allows you to view various boost settings and statistics associated with the current running Boost configuration.	
		</td>
	</tr>
	<?php html_header(array('RRD Utilities'), 2); form_alternate_row(); ?>
		<td class='textArea'>
			<a href='<?php print htmlspecialchars('rrdcleaner.php');?>'>RRDfile Cleaner</a>
		</td>
		<td class='textArea'>
			When you delete Data Sources from Cacti, the corresponding RRDfiles are not removed automatically.  Use this utility to facilitate the removal of these old files.
		</td>
	</tr>
	<?php html_header(array('SNMPAgent Utilities'), 2); form_alternate_row(); ?>
		<td class='textArea'>
			<a href='<?php print htmlspecialchars('utilities.php?action=view_snmpagent_cache');?>'>View SNMPAgent Cache</a>
		</td>
		<td class='textArea'>
			This shows all objects being handled by the SNMPAgent.
		</td>
	</tr>
	<?php form_alternate_row(); ?>
		<td class='textArea'>
			<a href='<?php print htmlspecialchars('utilities.php?action=rebuild_snmpagent_cache');?>'>Rebuild SNMPAgent Cache</a>
		</td>
		<td class='textArea'>
			The snmp cache will be cleared and re-generated if you select this option. Note that it takes another poller run to restore the SNMP cache completely.
		</td>
	</tr>
	<?php form_alternate_row(); ?>
		<td class='textArea'>
			<a href='<?php print htmlspecialchars('utilities.php?action=view_snmpagent_events');?>'>View SNMPAgent Notification Log</a>
		</td>
		<td class='textArea'>
			This menu pick allows you to view the latest events SNMPAgent has handled in relation to the registered notification receivers.
		</td>
	</tr>
	<?php form_alternate_row(); ?>	
		<td class='textArea'>
			<a href='<?php print htmlspecialchars('managers.php');?>'>SNMP Notification Receivers</a>
		</td>
		<td class='textArea'>
			Allows Administrators to maintain SNMP notification receivers.
		</td>
	</tr>
	<?php

	api_plugin_hook('utilities_list');

	html_end_box();
}

function boost_display_run_status() {
	global $refresh, $config, $refresh_interval, $boost_utilities_interval, $boost_refresh_interval, $boost_max_runtime;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('refresh'));
	/* ==================================================== */

	load_current_session_value('refresh', 'sess_boost_utilities_refresh', '30');

	$refresh['seconds'] = $_REQUEST['refresh'];
	$refresh['page'] = 'utilities.php?action=view_boost_status';

	$last_run_time   = read_config_option('boost_last_run_time', TRUE);
	$next_run_time   = read_config_option('boost_next_run_time', TRUE);

	$rrd_updates     = read_config_option('boost_rrd_update_enable', TRUE);
	$boost_server    = read_config_option('boost_server_enable', TRUE);
	$boost_cache     = read_config_option('boost_png_cache_enable', TRUE);

	$max_records     = read_config_option('boost_rrd_update_max_records', TRUE);
	$max_runtime     = read_config_option('boost_rrd_update_max_runtime', TRUE);
	$update_interval = read_config_option('boost_rrd_update_interval', TRUE);
	$peak_memory     = read_config_option('boost_peak_memory', TRUE);
	$detail_stats    = read_config_option('stats_detail_boost', TRUE);

	html_start_box('<strong>Boost Status</strong>', '100%', '', '3', 'center', '');
	?>
	<script type="text/javascript">
	<!--
	function applyStatsRefresh(objForm) {
		strURL = '?action=view_boost_status&refresh=' + objForm.refresh[objForm.refresh.selectedIndex].value;
		document.location = strURL;
	}
	-->
	</script>
	<tr class='even'>
		<form name="form_boost_utilities_stats" method="post">
		<td>
			<table cellpadding="2" cellspacing="0" border="0">
				<tr>
					<td style="white-space:nowrap;">
						Refresh Interval
					</td>
					<td>
						<select name="refresh" onChange="applyStatsRefresh(document.form_boost_utilities_stats)">
						<?php
						foreach ($boost_utilities_interval as $key => $interval) {
							print '<option value="' . $key . '"'; if ($_REQUEST['refresh'] == $key) { print ' selected'; } print '>' . $interval . '</option>';
						}
						?>
					</td>
					<td>
						<input type="submit" value="Refresh">
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php
	html_end_box(TRUE);
	html_start_box('', '100%', '', '3', 'center', '');

	/* get the boost table status */
	$boost_table_status = db_fetch_assoc("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE table_schema=SCHEMA()
						AND (table_name LIKE 'poller_output_boost_arch_%' OR table_name LIKE 'poller_output_boost')");
	$pending_records = 0;
	$arch_records = 0;
	$data_length = 0;
	$engine = '';
	$max_data_length = 0;
	foreach($boost_table_status as $table) {
		if ($table['TABLE_NAME'] == 'poller_output_boost') {
			$pending_records += $table['TABLE_ROWS'];
		} else {
			$arch_records += $table['TABLE_ROWS'];
		}
		$data_length += $table['DATA_LENGTH'];
		$data_length -= $table['DATA_FREE'];
		$engine = $table['ENGINE'];
		$max_data_length = $table['MAX_DATA_LENGTH'];
	}
	$total_records = $pending_records + $arch_records;
	$avg_row_length = ($total_records ? intval($data_length / $total_records) : 0);

	$total_data_sources = db_fetch_cell('SELECT COUNT(*) FROM poller_item');

	$boost_status = read_config_option('boost_poller_status', TRUE);
	if (strlen($boost_status)) {
		$boost_status_array = explode(':', $boost_status);

		$boost_status_date = $boost_status_array[1];

		if (substr_count($boost_status_array[0], 'complete')) $boost_status_text = 'Idle';
		elseif (substr_count($boost_status_array[0], 'running'))  $boost_status_text = 'Running';
		elseif (substr_count($boost_status_array[0], 'overrun'))    $boost_status_text = 'Overrun Warning';
		elseif (substr_count($boost_status_array[0], 'timeout'))  $boost_status_text = 'Timed Out';
		else   $boost_status_text = 'Other';
	}else{
		$boost_status_text = 'Never Run';
		$boost_status_date = '';
	}

	$stats_boost = read_config_option('stats_boost', TRUE);
	if (strlen($stats_boost)) {
		$stats_boost_array = explode(' ', $stats_boost);

		$stats_duration = explode(':', $stats_boost_array[0]);
		$boost_last_run_duration = $stats_duration[1];

		$stats_rrds = explode(':', $stats_boost_array[1]);
		$boost_rrds_updated = $stats_rrds[1];
	}else{
		$boost_last_run_duration = '';
		$boost_rrds_updated = '';
	}


	/* get cache directory size/contents */
	$cache_directory = read_config_option('boost_png_cache_directory', TRUE);
	$directory_contents = array();

	if (is_dir($cache_directory)) {
		if ($handle = @opendir($cache_directory)) {
			/* This is the correct way to loop over the directory. */
			while (FALSE !== ($file = readdir($handle))) {
				$directory_contents[] = $file;
			}

			closedir($handle);

			/* get size of directory */
			$directory_size = 0;
			$cache_files = 0;
			if (sizeof($directory_contents)) {
				/* goto the cache directory */
				chdir($cache_directory);

				/* check and fry as applicable */
				foreach($directory_contents as $file) {
					/* only remove jpeg's and png's */
					if ((substr_count(strtolower($file), '.png')) ||
						(substr_count(strtolower($file), '.jpg'))) {
						$cache_files++;
						$directory_size += filesize($file);
					}
				}
			}

			$directory_size = boost_file_size_display($directory_size);
			$cache_files = $cache_files . ' Files';
		}else{
			$directory_size = '<strong>WARNING:</strong> Can not open directory';
			$cache_files = '<strong>WARNING:</strong> Unknown';
		}
	}else{
		$directory_size = '<strong>WARNING:</strong> Directory Does NOT Exist!!';
		$cache_files = '<strong>WARNING:</strong> N/A';
	}

	$i = 0;

	/* boost status display */
	html_header(array('Current Boost Status'), 2);

	form_alternate_row();
	print '<td><strong>Boost On Demand Updating:</strong></td><td><strong>' . ($rrd_updates == '' ? 'Disabled' : $boost_status_text) . '</strong></td>';

	form_alternate_row();
	print '<td><strong>Total Data Sources:</strong></td><td>' . $total_data_sources . '</td>';

	if ($total_records > 0) {
		form_alternate_row();
		print '<td><strong>Pending Boost Records:</strong></td><td>' . $pending_records . '</td>';

		form_alternate_row();
		print '<td><strong>Archived Boost Records:</strong></td><td>' . $arch_records . '</td>';

		form_alternate_row();
		print '<td><strong>Total Boost Records:</strong></td><td>' . $total_records . '</td>';
	}

	/* boost status display */
	html_header(array('Boost Storage Statistics'), 2);

	/* describe the table format */
	form_alternate_row();
	print '<td><strong>Database Engine:</strong></td><td>' . $engine . '</td>';

	/* tell the user how big the table is */
	form_alternate_row();
	print '<td><strong>Current Boost Tables Size:</strong></td><td>' . boost_file_size_display($data_length, 2) . '</td>';

	/* tell the user about the average size/record */
	form_alternate_row();
	print '<td><strong>Avg Bytes/Record:</strong></td><td>' . boost_file_size_display($avg_row_length) . '</td>';

	/* tell the user about the average size/record */
	$output_length = read_config_option('boost_max_output_length');
	if (strlen($output_length)) {
		$parts = explode(':', $output_length);
		if ((time()-1200) > $parts[0]) {
			$refresh = TRUE;
		}else{
			$refresh = FALSE;
		}
	}else{
		$refresh = TRUE;
	}

	if ($refresh) {
		if (strcmp($engine, 'MEMORY') == 0) {
			$max_length = db_fetch_cell('SELECT MAX(LENGTH(output)) FROM poller_output_boost');
		}else{
			$max_length = '0';
		}
		db_execute("REPLACE INTO settings (name, value) VALUES ('boost_max_output_length', '" . time() . ':' . $max_length . "')");
	}else{
		$max_length = $parts[1];
	}

	if ($max_length != 0) {
		form_alternate_row();
		print '<td><strong>Max Record Length:</strong></td><td>' . $max_length . ' Bytes</td>';
	}

	/* tell the user about the "Maximum Size" this table can be */
	form_alternate_row();
	if (strcmp($engine, 'MEMORY')) {
		$max_table_allowed = 'Unlimited';
		$max_table_records = 'Unlimited';
	}else{
		$max_table_allowed = boost_file_size_display($max_data_length, 2);
		$max_table_records = ($avg_row_length ? round($max_data_length/$avg_row_length, 0) : 0);
	}
	print '<td><strong>Max Allowed Boost Table Size:</strong></td><td>' . $max_table_allowed . '</td>';

	/* tell the user about the estimated records that "could" be held in memory */
	form_alternate_row();
	print '<td><strong>Estimated Maximum Records:</strong></td><td>' . $max_table_records  . ' Records</td>';

	/* boost last runtime display */
	html_header(array('Runtime Statistics'), 2);

	form_alternate_row();
	print '<td width=200><strong>Last Start Time:</strong></td><td>' . $last_run_time . '</td>';

	form_alternate_row();
	print '<td width=200><strong>Last Run Duration:</strong></td><td>';
	print (($boost_last_run_duration > 60) ? (int)($boost_last_run_duration/60) . ' minutes ' : '' ) . $boost_last_run_duration%60 . ' seconds';
	if ($rrd_updates != ''){ print ' (' . round(100*$boost_last_run_duration/$update_interval/60) . '% of update frequency)';}
	print '</td>';

	form_alternate_row();
	print '<td width=200><strong>RRD Updates:</strong></td><td>' . $boost_rrds_updated . '</td>';

	form_alternate_row();
	print '<td width=200><strong>Peak Poller Memory:</strong></td><td>' . ((read_config_option('boost_peak_memory') != '') ? (round(read_config_option('boost_peak_memory')/1024/1024,2)) . ' MBytes' : 'N/A') . '</td>';

	form_alternate_row();
	print '<td width=200><strong>Detailed Runtime Timers:</strong></td><td>' . (($detail_stats != '') ? $detail_stats:'N/A') . '</td>';

	form_alternate_row();
	print '<td width=200><strong>Max Poller Memory Allowed:</strong></td><td>' . ((read_config_option('boost_poller_mem_limit') != '') ? (read_config_option('boost_poller_mem_limit')) . ' MBytes' : 'N/A') . '</td>';

	/* boost runtime display */
	html_header(array('Run Time Configuration'), 2);

	form_alternate_row();
	print '<td width=200><strong>Update Frequency:</strong></td><td><strong>' . ($rrd_updates == '' ? 'N/A' : $boost_refresh_interval[$update_interval]) . '</strong></td>';

	form_alternate_row();
	print '<td width=200><strong>Next Start Time:</strong></td><td>' . $next_run_time . '</td>';

	form_alternate_row();
	print '<td width=200><strong>Maximum Records:</strong></td><td>' . $max_records . ' Records</td>';

	form_alternate_row();
	print '<td width=200><strong>Maximum Allowed Runtime:</strong></td><td>' . $boost_max_runtime[$max_runtime] . '</td>';

	/* boost runtime display */
	html_header(array('Boost Server Details'), 2);

	form_alternate_row();
	print '<td><strong>Server Config Status:</strong></td><td><strong>' . ($boost_server == '' ? 'Disabled' : 'Enabled') . '</strong></td>';

	form_alternate_row();
	print '<td><strong>Multiprocess Server:</td><td>' . (read_config_option('boost_server_multiprocess', TRUE) == '1' ? 'Multiple Process' : 'Single Process') . '</strong></td>';

	form_alternate_row();
	print '<td><strong>Update Timeout:</td><td>' . read_config_option('boost_server_timeout', TRUE) . ' Seconds</strong></td>';

	form_alternate_row();
	print '<td><strong>Server/Port:</td><td>' . read_config_option('boost_server_hostname', TRUE) . '@' . read_config_option('boost_server_listen_port', TRUE) . '</strong></td>';

	form_alternate_row();
	print '<td><strong>Authorized Update Web Servers:</td><td>' . read_config_option('boost_server_clients', TRUE) . '</strong></td>';

	if (strlen(read_config_option('boost_path_rrdupdate')) && (read_config_option('boost_server_multiprocess') == 1)) {
		form_alternate_row();
		print '<td><strong>RRDtool Binary Used:</td><td>' . read_config_option('boost_path_rrdupdate') . '</strong></td>';
	}else{
		form_alternate_row();
		print '<td><strong>RRDtool Binary Used:</td><td>' . read_config_option('path_rrdtool') . '</strong></td>';
	}

	/* boost caching */
	html_header(array('Image Caching'), 2);

	form_alternate_row();
	print '<td><strong>Image Caching Status:</strong></td><td><strong>' . ($boost_cache == '' ? 'Disabled' : 'Enabled') . '</strong></td>';

	form_alternate_row();
	print '<td><strong>Cache Directory:</strong></td><td>' . $cache_directory . '</td>';

	form_alternate_row();
	print '<td><strong>Cached Files:</strong></td><td>' . $cache_files . '</td>';

	form_alternate_row();
	print '<td><strong>Cached Files Size:</strong></td><td>' . $directory_size . '</td>';

	html_end_box(TRUE);
}

/**
 *  
 *
 * snmpagent_utilities_run_cache()
 *
 * @param mixed
 * @return
 */
function snmpagent_utilities_run_cache($rebuild=false) {
	global $item_rows;
	
	define("MAX_DISPLAY_PAGES", 21);

	$mibs = db_fetch_assoc("SELECT DISTINCT mib FROM snmpagent_cache");
	$registered_mibs = array();
	if($mibs && $mibs >0) {
		foreach($mibs as $mib) { $registered_mibs[] = $mib["mib"]; }
	}

	/* ================= input validation ================= */
	if(!in_array(get_request_var_request("mib"), $registered_mibs) && get_request_var_request("mib") != '-1' && get_request_var_request("mib") != "") {
		die_html_input_error();
	}
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("rows"));
	/* ==================================================== */

	/* clean up search filter */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_snmpagent_cache_mib");
		kill_session_var("sess_snmpagent_cache_current_page");
		kill_session_var("sess_snmpagent_cache_filter");
		kill_session_var("sess_default_rows");
		unset($_REQUEST["mib"]);
		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["rows"]);
	}

	/* reset the current page if the user changed the mib filter*/
	if(isset($_SESSION["sess_snmpagent_cache_mib"]) && get_request_var_request("mib") != $_SESSION["sess_snmpagent_cache_mib"]) {
		kill_session_var("sess_snmpagent_cache_current_page");
		unset($_REQUEST["page"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_snmpagent_cache_current_page", "1");
	load_current_session_value("mib", "sess_snmpagent_cache_mib", "-1");
	load_current_session_value("filter", "sess_snmpagent_cache_filter", "");
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["rows"] == -1) {
		$_REQUEST["rows"] = read_config_option("num_rows_table");
	}

	$_REQUEST['page_referrer'] = 'view_snmpagent_cache';
	load_current_session_value('page_referrer', 'page_referrer', 'view_snmpagent_cache');
	
	?>
	<script type="text/javascript">
	<!--

	function applyFilter() {
		strURL = 'utilities.php?action=view_snmpagent_cache';
		strURL = strURL + '&mib=' + $('#mib').val();
		strURL = strURL + '&rows=' + $('#rows').val();
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = 'utilities.php?action=view_snmpagent_cache&clear_x=1&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	$(function(data) {
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

	-->
	</script>
	<?php

	if($rebuild) {
		snmpagent_cache_rebuilt();
	}

	html_start_box("<strong>SNMPAgent Cache</strong>", "100%", "", "3", "center", "");

	?>
	<tr class='even noprint'>
		<td>
			<form id='form_snmpagent_cache' name="form_snmpagent_cache" action="utilities.php">
				<table cellpadding="2" cellspacing="0">
					<tr>
						<td width="50">
							MIB:
						</td>
						<td>
							<select id="mib" name="mib" onChange="applyFilter()">
								<option value="-1"<?php if (get_request_var_request("mib") == "-1") {?> selected<?php }?>>Any</option>
								<?php
								if (sizeof($mibs) > 0) {
									foreach ($mibs as $mib) {
										print "<option value='" . $mib["mib"] . "'"; if (get_request_var_request("mib") == $mib["mib"]) { print " selected"; } print ">" . $mib["mib"] . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							Search:
						</td>
						<td>
							<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>" onChange='applyFilter()'>
						</td>
						<td>
							Rows:
						</td>
						<td>
							<select id='rows' name="rows" onChange="applyFilter()">
								<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
								<?php
								if (sizeof($item_rows) > 0) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . htmlspecialchars($value) . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							<input type="button" id='refresh' value="Go" title="Set/Refresh Filters">
						</td>
						<td>
							<input type="button" id='clear' name="clear_x" value="Clear" title="Clear Filters">
						</td>
					</tr>
				</table>
				<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
			</form>
		</td>
	</tr>
	<?php
	
	html_end_box();

	$sql_where = "";

	/* filter by host */
	if (get_request_var_request("mib") == "-1") {
		/* Show all items */
	}elseif (!empty($_REQUEST["mib"])) {
		$sql_where .= " AND snmpagent_cache.mib='" . get_request_var_request("mib") . "'";
	}

	/* filter by search string */
	if (get_request_var_request("filter") != "") {
		$sql_where .= " AND (`oid` LIKE '%%" . get_request_var_request("filter") . "%%'
			OR `name` LIKE '%%" . get_request_var_request("filter") . "%%'
			OR `mib` LIKE '%%" . get_request_var_request("filter") . "%%'
			OR `max-access` LIKE '%%" . get_request_var_request("filter") . "%%')";
	}
	$sql_where .= ' ORDER by `oid`';
	html_start_box("", "100%", "", "3", "center", "");

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM snmpagent_cache WHERE 1 $sql_where");

	
	$snmp_cache_sql = "SELECT * FROM snmpagent_cache WHERE 1 $sql_where LIMIT " . (get_request_var_request("rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("rows");
	$snmp_cache = db_fetch_assoc($snmp_cache_sql);

	/* generate page list */
	$nav = html_nav_bar("utilities.php?action=view_snmpagent_cache&mib=" . get_request_var_request("mib") . "&filter=" . get_request_var_request("filter"), MAX_DISPLAY_PAGES, get_request_var_request("page"), get_request_var_request("rows"), $total_rows, 11, '', 'page', 'main');

	print $nav;

	
	
	html_header( array( "OID", "Name", "MIB", "Kind", "Max-Access", "Value") );

	if (sizeof($snmp_cache) > 0) {
		foreach ($snmp_cache as $item) {

			$oid = (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["oid"])) : $item["oid"]);
			$name = (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["name"])): $item["name"]);
			$mib = (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["mib"])): $item["mib"]);

			$max_access = (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["max-access"])) : $item["max-access"]);

			form_alternate_row('line' . $item["oid"], false);
			form_selectable_cell( $oid, $item["oid"]);
			if($item["description"]) {
				$description = '';
				$lines = preg_split( '/\r\n|\r|\n/', $item['description']);
				foreach($lines as $line) {
					$description .= addslashes(trim($line)) . '<br>';
				}
				print '<td><a href="#" onMouseOut="hideTooltip(snmpagentTooltip)" onMouseMove="showTooltip(event, snmpagentTooltip, \'' . $item["name"] . '\', \'' . $description . '\')">' . $name . '</a></td>';
			}else {
				print "<td>$name</td>";
			}
			form_selectable_cell( $mib, $item["oid"]);
			form_selectable_cell( $item["kind"], $item["oid"]);
			form_selectable_cell( $max_access, $item["oid"]);
			form_selectable_cell( (in_array($item["kind"], array("Scalar", "Column Data")) ? $item["value"] : "n/a"), $item["oid"]);
			form_end_row();
		}
	}

	print $nav;

	html_end_box();

	/* as long as we are not running 0.8.9 don't make any use of jQuery */
	?>
	<div style="display:none" id="snmpagentTooltip"></div>
	<script language="javascript" type="text/javascript" >
		function showTooltip(e, div, title, desc) {
			div.style.display = 'inline';
			div.style.position = 'fixed';
			div.style.backgroundColor = '#EFFCF0';
			div.style.border = 'solid 1px grey';
			div.style.padding = '10px';
			div.innerHTML = '<b>' + title + '</b><div style="padding-left:10; padding-right:5"><pre>' + desc + '</pre></div>';
			div.style.left = e.clientX + 15 + 'px';
			div.style.top = e.clientY + 15 + 'px';
		}

		function hideTooltip(div) {
			div.style.display = 'none';
		}

	</script>

	<?php
}

function snmpagent_utilities_run_eventlog(){
	global $item_rows;

	define("MAX_DISPLAY_PAGES", 21);

	$severity_levels = array(
		SNMPAGENT_EVENT_SEVERITY_LOW => 'LOW',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM => 'MEDIUM',
		SNMPAGENT_EVENT_SEVERITY_HIGH => 'HIGH',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => 'CRITICAL'
	);

	$severity_colors = array(
		SNMPAGENT_EVENT_SEVERITY_LOW => '#00FF00',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM => '#FFFF00',
		SNMPAGENT_EVENT_SEVERITY_HIGH => '#FF0000',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => '#FF00FF'
	);

	$receivers = db_fetch_assoc("SELECT DISTINCT manager_id, hostname FROM snmpagent_notifications_log INNER JOIN snmpagent_managers ON snmpagent_managers.id = snmpagent_notifications_log.manager_id");

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("receiver"));

	if(!in_array(get_request_var_request("severity"), array_keys($severity_levels)) && get_request_var_request("severity") != '-1' && get_request_var_request("severity") != "") {
		die_html_input_error();
	}
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("rows"));
	/* ==================================================== */

	/* clean up search filter */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	if (isset($_REQUEST["purge_x"])) {
		db_execute("TRUNCATE table snmpagent_notifications_log;");
		/* reset filters */
		$_REQUEST["clear_x"] = true;
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_snmpagent__logs_receiver");
		kill_session_var("sess_snmpagent__logs_severity");
		kill_session_var("sess_snmpagent__logs_current_page");
		kill_session_var("sess_snmpagent__logs_filter");
		kill_session_var("sess_default_rows");
		unset($_REQUEST["receiver"]);
		unset($_REQUEST["severity"]);
		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["rows"]);
	}

	/* reset the current page if the user changed the severity */
	if(isset($_SESSION["sess_snmpagent__logs_severity"]) && get_request_var_request("severity") != $_SESSION["sess_snmpagent__logs_severity"]) {
		kill_session_var("sess_snmpagent__logs_current_page");
		unset($_REQUEST["page"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("receiver", "sess_snmpagent__logs_receiver", "-1");
	load_current_session_value("page", "sess_snmpagent__logs_current_page", "1");
	load_current_session_value("severity", "sess_snmpagent__logs_severity", "-1");
	load_current_session_value("filter", "sess_snmpagent__logs_filter", "");
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["rows"] == -1) {
		$_REQUEST["rows"] = read_config_option("num_rows_table");
	}
	
	$_REQUEST['page_referrer'] = 'view_snmpagent_events';
	load_current_session_value('page_referrer', 'page_referrer', 'view_snmpagent_events');
	
	?>
	<script type="text/javascript">
	<!--

	function applyFilter() {
		strURL = 'utilities.php?action=view_snmpagent_events';
		strURL = strURL + '&severity=' + $('#severity').val();
		strURL = strURL + '&receiver=' + $('#receiver').val();
		strURL = strURL + '&rows=' + $('#rows').val();
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = 'utilities.php?action=view_snmpagent_events&clear_x=1&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	$(function(data) {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_snmpagent_notifications').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	-->
	</script>
	
	<?php
	html_start_box("<strong>SNMPAgent Notification Log</strong>", "100%", "", "3", "center", "");

	?>
	<tr class='even noprint'>
		<td>
			<form id='form_snmpagent_notifications' name="form_snmpagent_notifications" action="utilities.php">
				<table cellpadding="2" cellspacing="0">
					<tr>
						<td>
							Severity:
						</td>
						<td>
							<select id="severity" name="severity" onChange="applyFilter()">
								<option value="-1"<?php if (get_request_var_request("severity") == "-1") {?> selected<?php }?>>Any</option>
								<?php
								foreach ($severity_levels as $level => $name) {
									print "<option value='" . $level . "'"; if (get_request_var_request("severity") == $level) { print " selected"; } print ">" . $name . "</option>\n";
								}
								?>
							</select>
						</td>
						<td>
							Receiver:
						</td>
						<td width="1">
							<select id="receiver" name="receiver" onChange="applyFilter()">
								<option value="-1"<?php if (get_request_var_request("receiver") == "-1") {?> selected<?php }?>>Any</option>
								<?php
								foreach ($receivers as $receiver) {
									print "<option value='" . $receiver["manager_id"] . "'"; if (get_request_var_request("receiver") == $receiver["manager_id"]) { print " selected"; } print ">" . $receiver["hostname"] . "</option>\n";
								}
								?>
							</select>
						</td>
						<td>
							Search:
						</td>
						<td>
							<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>" onChange='applyFilter()'>
						</td>
						<td>
							Rows:
						</td>
						<td>
							<select id='rows' name="rows" onChange="applyFilter()">
								<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
								<?php
								if (sizeof($item_rows) > 0) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . htmlspecialchars($value) . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							<input type="submit" id="refresh" name="go" value="Go" title="Set/Refresh Filters">
							<input type="submit" id="clear" name="clear_x" value="Clear" title="Clear Filters">
							<input type="submit" id="purge" name="purge_x" value="Purge" title="Purge Notification Log">
						</td>
					</tr>
				</table>
				<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = " 1";

	/* filter by severity */
	if(get_request_var_request("receiver") != "-1") {
		$sql_where .= " AND snmpagent_notifications_log.manager_id='" . get_request_var_request("receiver") . "'";
	}

	/* filter by severity */
	if (get_request_var_request("severity") == "-1") {
	/* Show all items */
	}elseif (!empty($_REQUEST["severity"])) {
		$sql_where .= " AND snmpagent_notifications_log.severity='" . get_request_var_request("severity") . "'";
	}

	/* filter by search string */
	if (get_request_var_request("filter") != "") {
		$sql_where .= " AND (`varbinds` LIKE '%%" . get_request_var_request("filter") . "%%')";
	}
	$sql_where .= ' ORDER by `time` DESC';
	$sql_query = "SELECT snmpagent_notifications_log.*, snmpagent_managers.hostname, snmpagent_cache.description FROM snmpagent_notifications_log
					 INNER JOIN snmpagent_managers ON snmpagent_managers.id = snmpagent_notifications_log.manager_id
					 LEFT JOIN snmpagent_cache ON snmpagent_cache.name = snmpagent_notifications_log.notification
					 WHERE $sql_where LIMIT " . (read_config_option("num_rows_data_source")*(get_request_var_request("page")-1)) . "," . read_config_option("num_rows_data_source");

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='managers.php'>\n";
	html_start_box("", "100%", "", "3", "center", "");

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM snmpagent_notifications_log WHERE $sql_where");
	$logs = db_fetch_assoc($sql_query);

	/* generate page list */
	$nav = html_nav_bar("utilities.php?action=view_snmpagent_events&severity=". get_request_var_request("severity")."&receiver=". get_request_var_request("receiver")."&filter=" . get_request_var_request("filter"), MAX_DISPLAY_PAGES, get_request_var_request("page"), get_request_var_request("rows"), $total_rows, 11, '', 'page', 'main');

	print $nav;

	html_header( array(" ", "Time", "Receiver", "Notification", "Varbinds" ), true );

	if (sizeof($logs) > 0) {
		foreach ($logs as $item) {
			$varbinds = (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["varbinds"])): $item["varbinds"]);
			form_alternate_row('line' . $item["id"], false);		
			print "<td title='Severity Level: " . $severity_levels[ $item["severity"] ] . "' style='width:10px;background-color: " . $severity_colors[ $item["severity"] ] . ";border-top:1px solid white;border-bottom:1px solid white;'></td>";
			print "<td style='white-space: nowrap;'>" . date( "Y/m/d H:i:s", $item["time"]) . "</td>";
			print "<td>" . $item["hostname"] . "</td>";
			if($item["description"]) {
				$description = '';
				$lines = preg_split( '/\r\n|\r|\n/', $item['description']);
				foreach($lines as $line) {
					$description .= addslashes(trim($line)) . '<br>';
				}
				print '<td><a href="#" onMouseOut="hideTooltip(snmpagentTooltip)" onMouseMove="showTooltip(event, snmpagentTooltip, \'' . $item["notification"] . '\', \'' . $description . '\')">' . $item["notification"] . '</a></td>';
			}else {
				print "<td>{$item["notification"]}</td>";
			}
			print "<td>$varbinds</td>";
			form_end_row();
		}
		print $nav;
	}else{
		print "<tr><td><em>No SNMP Notification Log Entries</em></td></tr>";
	}

	html_end_box();
	?>
	<div style="display:none" id="snmpagentTooltip"></div>
	<script language="javascript" type="text/javascript" >
	function showTooltip(e, div, title, desc) {
		div.style.display = 'inline';
		div.style.position = 'fixed';
		div.style.backgroundColor = '#EFFCF0';
		div.style.border = 'solid 1px grey';
		div.style.padding = '10px';
		div.innerHTML = '<b>' + title + '</b><div style="padding-left:10; padding-right:5"><pre>' + desc + '</pre></div>';
		div.style.left = e.clientX + 15 + 'px';
		div.style.top = e.clientY + 15 + 'px';
		}

		function hideTooltip(div) {
			div.style.display = 'none';
		}
		function highlightStatus(selectID){
			if (document.getElementById('status_' + selectID).value == 'ON') {
			document.getElementById('status_' + selectID).style.backgroundColor = 'LawnGreen';
		}else {
			document.getElementById('status_' + selectID).style.backgroundColor = 'OrangeRed';
		}
	}
	</script>
	<?php
}
