<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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

include("./include/auth.php");
include_once("./lib/utility.php");

load_current_session_value("page_referrer", "page_referrer", "");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

if (isset($_REQUEST["sort_direction"])) {
	if ($_REQUEST['page_referrer'] == "view_snmp_cache") {
		$_REQUEST["action"] = "view_snmp_cache";
	}else if ($_REQUEST['page_referrer'] == "view_poller_cache") {
		$_REQUEST["action"] = "view_poller_cache";
	}else{
		$_REQUEST["action"] = "view_user_log";
	}
}

if ((isset($_REQUEST["clear_x"])) || (isset($_REQUEST["go_x"]))) {
	if ($_REQUEST['page_referrer'] == "view_snmp_cache") {
		$_REQUEST["action"] = "view_snmp_cache";
	}else if ($_REQUEST['page_referrer'] == "view_poller_cache") {
		$_REQUEST["action"] = "view_poller_cache";
	}else if ($_REQUEST['page_referrer'] == "view_user_log") {
		$_REQUEST["action"] = "view_user_log";
	}else{
		$_REQUEST["action"] = "view_logfile";
	}
}

if (isset($_REQUEST["purge_x"])) {
	if ($_REQUEST['page_referrer'] == "view_user_log") {
		$_REQUEST["action"] = "clear_user_log";
	}else{
		$_REQUEST["action"] = "clear_logfile";
	}
}

switch ($_REQUEST["action"]) {
	case 'clear_poller_cache':
		include_once("./include/top_header.php");

		/* obtain timeout settings */
		$max_execution = ini_get("max_execution_time");
		ini_set("max_execution_time", "0");
		repopulate_poller_cache();
		ini_set("max_execution_time", $max_execution);

		utilities_view_poller_cache();

		include_once("./include/bottom_footer.php");
		break;
	case 'view_snmp_cache':
		include_once("./include/top_header.php");

		utilities_view_snmp_cache();

		include_once("./include/bottom_footer.php");
		break;
	case 'view_poller_cache':
		include_once("./include/top_header.php");

		utilities_view_poller_cache();

		include_once("./include/bottom_footer.php");
		break;
	case 'view_logfile':
		utilities_view_logfile();

		break;
	case 'clear_logfile':
		utilities_clear_logfile();
		utilities_view_logfile();

		include_once("./include/bottom_footer.php");
		break;
	case 'view_user_log':
		include_once("./include/top_header.php");

		utilities_view_user_log();

		include_once("./include/bottom_footer.php");
		break;
	case 'clear_user_log':
		include_once("./include/top_header.php");

		utilities_clear_user_log();
		utilities_view_user_log();

		include_once("./include/bottom_footer.php");
		break;
	case 'view_tech':
		$php_info = utilities_php_modules();

		include_once("./include/top_header.php");

		utilities_view_tech($php_info);

		include_once("./include/bottom_footer.php");
		break;
	default:

		if (!api_plugin_hook_function('utilities_action', $_REQUEST['action'])) {
			include_once('./include/top_header.php');

			utilities();

			include_once('./include/bottom_footer.php');
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
	$php_info = str_replace("\n", "", $php_info);
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
		$val_label = "bytes";
	}elseif ($val < 1048576) {
		$val_label = "K";
		$val /= 1024;
	}elseif ($val < 1073741824) {
		$val_label = "M";
		$val /= 1048576;
	}else{
		$val_label = "G";
		$val /= 1073741824;
	}

    return $val . $val_label;
}


function utilities_view_tech($php_info = "") {
	global $database_default, $colors, $config, $rrdtool_versions, $poller_options, $input_types;

	/* Get table status */
	$tables = db_fetch_assoc("SHOW TABLES");
	$skip_tables  = array();
	$table_status = array();

	if (sizeof($tables)) {
	foreach($tables as $table) {
		$create_syntax = db_fetch_row("SHOW CREATE TABLE " . $table["Tables_in_" . $database_default]);

		if (sizeof($create_syntax)) {
			if (substr_count(strtoupper($create_syntax["Create Table"]), "INNODB")) {
				$skip_tables[] = $table["Tables_in_" . $database_default];
			}else{
				$include_tables[] = $table["Tables_in_" . $database_default];
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
	$poller_item = db_fetch_assoc("SELECT action, count(action) as total FROM poller_item GROUP BY action");

	/* Get system stats */
	$host_count = db_fetch_cell("SELECT COUNT(*) FROM host");
	$graph_count = db_fetch_cell("SELECT COUNT(*) FROM graph_local");
	$data_count = db_fetch_assoc("SELECT i.type_id, COUNT(i.type_id) AS total FROM data_template_data AS d, data_input AS i WHERE d.data_input_id = i.id AND local_data_id <> 0 GROUP BY i.type_id");

	/* Get RRDtool version */
	$rrdtool_version = "Unknown";
	if ((file_exists(read_config_option("path_rrdtool"))) && ((function_exists('is_executable')) && (is_executable(read_config_option("path_rrdtool"))))) {

		$out_array = array();
		exec(cacti_escapeshellcmd(read_config_option("path_rrdtool")), $out_array);

		if (sizeof($out_array) > 0) {
			if (preg_match("/^RRDtool 1\.4/", $out_array[0])) {
				$rrdtool_version = "rrd-1.4.x";
			}else if (preg_match("/^RRDtool 1\.3\./", $out_array[0])) {
				$rrdtool_version = "rrd-1.3.x";
			}else if (preg_match("/^RRDtool 1\.2\./", $out_array[0])) {
				$rrdtool_version = "rrd-1.2.x";
			}else if (preg_match("/^RRDtool 1\.0\./", $out_array[0])) {
				$rrdtool_version = "rrd-1.0.x";
			}
		}
	}

	/* Get SNMP cli version */
	$snmp_version = read_config_option("snmp_version");
	if ((file_exists(read_config_option("path_snmpget"))) && ((function_exists('is_executable')) && (is_executable(read_config_option("path_snmpget"))))) {
		$snmp_version = shell_exec(cacti_escapeshellcmd(read_config_option("path_snmpget")) . " -V 2>&1");
	}

	/* Check RRDTool issues */
	$rrdtool_error = "";
	if ($rrdtool_version != read_config_option("rrdtool_version")) {
		$rrdtool_error .= "<br><font color='red'>ERROR: Installed RRDTool version does not match configured version.<br>Please visit the <a href='" . htmlspecialchars("settings.php?tab=general") . "'>Configuration Settings</a> and select the correct RRDTool Utility Version.</font><br>";
	}
	$graph_gif_count = db_fetch_cell("SELECT COUNT(*) FROM graph_templates_graph WHERE image_format_id = 2");
	if (($graph_gif_count > 0) && (read_config_option("rrdtool_version") != "rrd-1.0.x")) {
		$rrdtool_error .= "<br><font color='red'>ERROR: RRDTool 1.2.x does not support the GIF images format, but " . $graph_gif_count . " graph(s) and/or templates have GIF set as the image format.</font><br>";
	}

	/* Get spine version */
	$spine_version = "Unknown";
	if ((file_exists(read_config_option("path_spine"))) && ((function_exists('is_executable')) && (is_executable(read_config_option("path_spine"))))) {
		$out_array = array();
		exec(read_config_option("path_spine") . " --version", $out_array);
		if (sizeof($out_array) > 0) {
			$spine_version = $out_array[0];
		}
	}

	/* Display tech information */
	html_start_box("<strong>Technical Support</strong>", "100%", $colors["header"], "3", "center", "");
	html_header(array("General Information"), 2);
	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
	print "		<td class='textArea'>Date</td>\n";
	print "		<td class='textArea'>" . date("r") . "</td>\n";
	print "</tr>\n";
	api_plugin_hook_function('custom_version_info');
	print "<tr bgcolor='#" . $colors["form_alternate2"] . "'>\n";
	print "		<td class='textArea'>Cacti Version</td>\n";
	print "		<td class='textArea'>" . $config["cacti_version"] . "</td>\n";
	print "</tr>\n";
	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
	print "		<td class='textArea'>Cacti OS</td>\n";
	print "		<td class='textArea'>" . $config["cacti_server_os"] . "</td>\n";
	print "</tr>\n";
	print "<tr bgcolor='#" . $colors["form_alternate2"] . "'>\n";
	print "		<td class='textArea'>SNMP Version</td>\n";
	print "		<td class='textArea'>" . $snmp_version . "</td>\n";
	print "</tr>\n";

	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
	print "		<td class='textArea'>RRDTool Version</td>\n";
	print "		<td class='textArea'>" . $rrdtool_versions[$rrdtool_version] . " " . $rrdtool_error . "</td>\n";
	print "</tr>\n";
	print "<tr bgcolor='#" . $colors["form_alternate2"] . "'>\n";
	print "		<td class='textArea'>Hosts</td>\n";
	print "		<td class='textArea'>" . $host_count . "</td>\n";
	print "</tr>\n";
	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
	print "		<td class='textArea'>Graphs</td>\n";
	print "		<td class='textArea'>" . $graph_count . "</td>\n";
	print "</tr>\n";
	print "<tr bgcolor='#" . $colors["form_alternate2"] . "'>\n";
	print "		<td class='textArea'>Data Sources</td>\n";
	print "		<td class='textArea'>";
	$data_total = 0;
	if (sizeof($data_count)) {
		foreach ($data_count as $item) {
			print $input_types[$item["type_id"]] . ": " . $item["total"] . "<br>";
			$data_total += $item["total"];
		}
		print "Total: " . $data_total;
	}else{
		print "<font color='red'>0</font>";
	}
	print "</td>\n";
	print "</tr>\n";

	html_header(array("Poller Information"), 2);
	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
	print "		<td class='textArea'>Interval</td>\n";
	print "		<td class='textArea'>" . read_config_option("poller_interval") . "</td>\n";
	if (file_exists(read_config_option("path_spine")) && $poller_options[read_config_option("poller_type")] == 'spine') {
		$type = $spine_version;
	} else {
		$type = $poller_options[read_config_option("poller_type")];
	}
	print "</tr>\n";
	print "<tr bgcolor='#" . $colors["form_alternate2"] . "'>\n";
	print "		<td class='textArea'>Type</td>\n";
	print "		<td class='textArea'>" . $type . "</td>\n";
	print "</tr>\n";
	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
	print "		<td class='textArea'>Items</td>\n";
	print "		<td class='textArea'>";
	$total = 0;
	if (sizeof($poller_item)) {
		foreach ($poller_item as $item) {
			print "Action[" . $item["action"] . "]: " . $item["total"] . "<br>";
			$total += $item["total"];
		}
		print "Total: " . $total;
	}else{
		print "<font color='red'>No items to poll</font>";
	}
	print "</td>\n";
	print "</tr>\n";

	print "<tr bgcolor='#" . $colors["form_alternate2"] . "'>\n";
	print "		<td class='textArea'>Concurrent Processes</td>\n";
	print "		<td class='textArea'>" . read_config_option("concurrent_processes") . "</td>\n";
	print "</tr>\n";

	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
	print "		<td class='textArea'>Max Threads</td>\n";
	print "		<td class='textArea'>" . read_config_option("max_threads") . "</td>\n";
	print "</tr>\n";

	print "<tr bgcolor='#" . $colors["form_alternate2"] . "'>\n";
	print "		<td class='textArea'>PHP Servers</td>\n";
	print "		<td class='textArea'>" . read_config_option("php_servers") . "</td>\n";
	print "</tr>\n";

	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
	print "		<td class='textArea'>Script Timeout</td>\n";
	print "		<td class='textArea'>" . read_config_option("script_timeout") . "</td>\n";
	print "</tr>\n";

	print "<tr bgcolor='#" . $colors["form_alternate2"] . "'>\n";
	print "		<td class='textArea'>Max OID</td>\n";
	print "		<td class='textArea'>" . read_config_option("max_get_size") . "</td>\n";
	print "</tr>\n";


	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
	print "		<td class='textArea'>Last Run Statistics</td>\n";
	print "		<td class='textArea'>" . read_config_option("stats_poller") . "</td>\n";
	print "</tr>\n";


	html_header(array("PHP Information"), 2);
	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
	print "		<td class='textArea'>PHP Version</td>\n";
	print "		<td class='textArea'>" . phpversion() . "</td>\n";
	print "</tr>\n";
	print "<tr bgcolor='#" . $colors["form_alternate2"] . "'>\n";
	print "		<td class='textArea'>PHP OS</td>\n";
	print "		<td class='textArea'>" . PHP_OS . "</td>\n";
	print "</tr>\n";
	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
	print "		<td class='textArea'>PHP uname</td>\n";
	print "		<td class='textArea'>";
	if (function_exists("php_uname")) {
		print php_uname();
	}else{
		print "N/A";
	}
	print "</td>\n";
	print "</tr>\n";
	print "<tr bgcolor='#" . $colors["form_alternate2"] . "'>\n";
	print "		<td class='textArea'>PHP SNMP</td>\n";
	print "		<td class='textArea'>";
	if (function_exists("snmpget")) {
		print "Installed";
	} else {
		print "Not Installed";
	}
	print "</td>\n";
	print "</tr>\n";
	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
	print "		<td class='textArea'>max_execution_time</td>\n";
	print "		<td class='textArea'>" . ini_get("max_execution_time") . "</td>\n";
	print "</tr>\n";
	print "<tr bgcolor='#" . $colors["form_alternate2"] . "'>\n";
	print "		<td class='textArea'>memory_limit</td>\n";
	print "		<td class='textArea'>" . ini_get("memory_limit");

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
		print "It is highly suggested that you alter you php.ini memory_limit to " . memory_readable($memory_suggestion) . " or higher. <br/>
			This suggested memory value is calculated based on the number of data source present and is only to be used as a suggestion, actual values may vary system to system based on requirements.";
		print "</font><br>";
	}
	print "</td>\n";
	print "</tr>\n";

	html_header(array("MySQL Table Information"), 2);
	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
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
			print "  <td>" . $item["Name"] . "</td>\n";
			print "  <td>" . $item["Rows"] . "</td>\n";
			if (isset($item["Engine"])) {
				print "  <td>" . $item["Engine"] . "</td>\n";
			}else{
				print "  <td>Unknown</td>\n";
			}
			if (isset($item["Collation"])) {
				print "  <td>" . $item["Collation"] . "</td>\n";
			} else {
				print "  <td>Unknown</td>\n";
			}
			print "</tr>\n";
		}

		if (sizeof($skip_tables)) {
			print "<tr><td colspan='20' align='center'><strong>The Following Tables were Skipped Due to being INNODB</strong></td></tr>";

			foreach($skip_tables as $table) {
				print "<tr><td colspan='20' align='center'>" . $table . "</td></tr>";
			}
		}

		print "</table>\n";
	}else{
		print "Unable to retrieve table status";
	}

	print "</td>\n";
	print "</tr>\n";

	html_header(array("PHP Module Information"), 2);
	print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>\n";
	print "		<td class='textArea' colspan='2'>" . $php_info . "</td>\n";
	print "</tr>\n";

	html_end_box();

}


function utilities_view_user_log() {
	global $colors, $auth_realms;

	define("MAX_DISPLAY_PAGES", 21);

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("result"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up username */
	if (isset($_REQUEST["username"])) {
		$_REQUEST["username"] = sanitize_search_string(get_request_var("username"));
	}

	/* clean up search filter */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up sort direction */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_userlog_current_page");
		kill_session_var("sess_userlog_username");
		kill_session_var("sess_userlog_result");
		kill_session_var("sess_userlog_filter");
		kill_session_var("sess_userlog_sort_column");
		kill_session_var("sess_userlog_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["result"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["username"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_userlog_current_page", "1");
	load_current_session_value("username", "sess_userlog_username", "-1");
	load_current_session_value("result", "sess_userlog_result", "-1");
	load_current_session_value("filter", "sess_userlog_filter", "");
	load_current_session_value("sort_column", "sess_userlog_sort_column", "time");
	load_current_session_value("sort_direction", "sess_userlog_sort_direction", "DESC");

	$_REQUEST['page_referrer'] = 'view_user_log';
	load_current_session_value('page_referrer', 'page_referrer', 'view_user_log');

	?>
	<script type="text/javascript">
	<!--

	function applyViewLogFilterChange(objForm) {
		strURL = '?username=' + objForm.username.value;
		strURL = strURL + '&result=' + objForm.result.value;
		strURL = strURL + '&action=view_user_log';
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_start_box("<strong>User Login History</strong>", "100%", $colors["header"], "3", "center", "");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
		<form name="form_userlog" action="utilities.php">
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						Username:&nbsp;
					</td>
					<td width="1">
						<select name="username" onChange="applyViewLogFilterChange(document.form_userlog)">
							<option value="-1"<?php if (get_request_var_request("username") == "-1") {?> selected<?php }?>>All</option>
							<option value="-2"<?php if (get_request_var_request("username") == "-2") {?> selected<?php }?>>Deleted/Invalid</option>
							<?php
							$users = db_fetch_assoc("SELECT DISTINCT username FROM user_auth ORDER BY username");

							if (sizeof($users) > 0) {
							foreach ($users as $user) {
								print "<option value='" . $user["username"] . "'"; if (get_request_var_request("username") == $user["username"]) { print " selected"; } print ">" . $user["username"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Result:&nbsp;
					</td>
					<td width="1">
						<select name="result" onChange="applyViewLogFilterChange(document.form_userlog)">
							<option value="-1"<?php if (get_request_var_request("result") == '-1') {?> selected<?php }?>>Any</option>
							<option value="1"<?php if (get_request_var_request("result") == '1') {?> selected<?php }?>>Success</option>
							<option value="0"<?php if (get_request_var_request("result") == '0') {?> selected<?php }?>>Failed</option>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input type="submit" name="go" value="Go" title="Set/Refresh Filters">
						<input type="submit" name="clear_x" value="Clear" title="Clear Filters">
						<input type="submit" name="purge_x" value="Purge" title="Purge User Log">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			<input type='hidden' name='action' value='view_user_log'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = "";

	/* filter by username */
	if (get_request_var_request("username") == "-2") {
		$sql_where = "WHERE user_log.username NOT IN (SELECT DISTINCT username from user_auth)";
	}elseif (get_request_var_request("username") != "-1") {
		$sql_where = "WHERE user_log.username='" . get_request_var_request("username") . "'";
	}

	/* filter by result */
	if (get_request_var_request("result") != "-1") {
		if (strlen($sql_where)) {
			$sql_where .= " AND user_log.result=" . get_request_var_request("result");
		}else{
			$sql_where = "WHERE user_log.result=" . get_request_var_request("result");
		}
	}

	/* filter by search string */
	if (get_request_var_request("filter") <> "") {
		if (strlen($sql_where)) {
			$sql_where .= " AND (user_log.username LIKE '%%" . get_request_var_request("filter") . "%%'
				OR user_log.time LIKE '%%" . get_request_var_request("filter") . "%%'
				OR user_log.ip LIKE '%%" . get_request_var_request("filter") . "%%')";
		}else{
			$sql_where = "WHERE (user_log.username LIKE '%%" . get_request_var_request("filter") . "%%'
				OR user_log.time LIKE '%%" . get_request_var_request("filter") . "%%'
				OR user_log.ip LIKE '%%" . get_request_var_request("filter") . "%%')";
		}
	}

	html_start_box("", "100%", $colors["header"], "3", "center", "");

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
		ORDER BY " . get_request_var_request("sort_column") . " " . get_request_var_request("sort_direction") . "
		LIMIT " . (read_config_option("num_rows_data_source")*(get_request_var_request("page")-1)) . "," . read_config_option("num_rows_data_source");

//	print $user_log_sql;

	$user_log = db_fetch_assoc($user_log_sql);

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, read_config_option("num_rows_data_source"), $total_rows, "utilities.php?action=view_user_log&username=" . get_request_var_request("username") . "&filter=" . get_request_var_request("filter"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='7'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("utilities.php?action=view_user_log&username=" . get_request_var_request("username") . "&filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("num_rows_data_source")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_data_source")) || ($total_rows < (read_config_option("num_rows_data_source")*get_request_var_request("page")))) ? $total_rows : (read_config_option("num_rows_data_source")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if ((get_request_var_request("page") * read_config_option("num_rows_data_source")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("utilities.php?action=view_user_log&username=" . get_request_var_request("username") . "&filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * read_config_option("num_rows_data_source")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"username" => array("Username", "ASC"),
		"full_name" => array("Full Name", "ASC"),
		"realm" => array("Authentication Realm", "ASC"),
		"time" => array("Date", "ASC"),
		"result" => array("Result", "DESC"),
		"ip" => array("IP Address", "DESC"));

	html_header_sort($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"));

	$i = 0;
	if (sizeof($user_log) > 0) {
		foreach ($user_log as $item) {
			if (isset($item["full_name"])) {
				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
			}else{
				form_alternate_row_color("FF9D9D","FFAFAF",$i);
			}
			?>
			<td style='white-space:nowrap;'>
				<?php print (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["username"])) : $item["username"]);?>
			</td>
			<td style='white-space:nowrap;'>
				<?php if (isset($item["full_name"])) {
						print (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["full_name"])) : $item["full_name"]);
					}else{
						print "(User Removed)";
					}
				?>
			</td>
			<td style='white-space:nowrap;'>
				<?php if (isset($auth_realms[$item["realm"]])) {
						print (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $auth_realms[$item["realm"]])) : $auth_realms[$item["realm"]]);
					}else{
						print "N/A";
					}
				?>
			</td>
			<td style='white-space:nowrap;'>
				<?php print (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["time"])) : $item["time"]);?>
			</td>
			<td style='white-space:nowrap;'>
				<?php print $item["result"] == 0 ? "Failed" : "Success";?>
			</td>
			<td style='white-space:nowrap;'>
				<?php print (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["ip"])) : $item["ip"]);?>
			</td>
			</tr>
			<?php
			$i++;
		}
	}

	html_end_box();
}

function utilities_clear_user_log() {
	$users = db_fetch_assoc("SELECT DISTINCT username FROM user_auth");

	if (sizeof($users)) {
		/* remove active users */
		foreach ($users as $user) {
			$total_rows = db_fetch_cell("SELECT COUNT(username) FROM user_log WHERE username = '" . $user['username'] . "' AND result = 1");
			if ($total_rows > 1) {
				db_execute("DELETE FROM user_log WHERE username = '" . $user['username'] . "' AND result = 1 ORDER BY time LIMIT " . ($total_rows - 1));
			}
			db_execute("DELETE FROM user_log WHERE username = '" . $user['username'] . "' AND result = 0");
		}

		/* delete inactive users */
		db_execute("DELETE FROM user_log WHERE user_id NOT IN (SELECT id FROM user_auth) OR username NOT IN (SELECT username FROM user_auth)");

	}
}

function utilities_view_logfile() {
	global $colors, $log_tail_lines, $page_refresh_interval, $refresh;

	$logfile = read_config_option("path_cactilog");

	if ($logfile == "") {
		$logfile = "./log/rrd.log";
	}

	/* helps determine output color */
	$linecolor = True;

	input_validate_input_number(get_request_var_request("tail_files"));
	input_validate_input_number(get_request_var_request("message_type"));
	input_validate_input_number(get_request_var_request("refresh"));
	input_validate_input_number(get_request_var_request("reverse"));

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_logfile_tail_lines");
		kill_session_var("sess_logfile_message_type");
		kill_session_var("sess_logfile_filter");
		kill_session_var("sess_logfile_refresh");
		kill_session_var("sess_logfile_reverse");

		unset($_REQUEST["tail_lines"]);
		unset($_REQUEST["message_type"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["refresh"]);
		unset($_REQUEST["reverse"]);
	}

	load_current_session_value("tail_lines", "sess_logfile_tail_lines", read_config_option("num_rows_log"));
	load_current_session_value("message_type", "sess_logfile_message_type", "-1");
	load_current_session_value("filter", "sess_logfile_filter", "");
	load_current_session_value("refresh", "sess_logfile_refresh", read_config_option("log_refresh_interval"));
	load_current_session_value("reverse", "sess_logfile_reverse", 1);

	$_REQUEST['page_referrer'] = 'view_logfile';
	load_current_session_value('page_referrer', 'page_referrer', 'view_logfile');

	$refresh["seconds"] = get_request_var_request("refresh");
	$refresh["page"] = "utilities.php?action=view_logfile";

	include_once("./include/top_header.php");

	?>
	<script type="text/javascript">
	<!--

	function applyViewLogFilterChange(objForm) {
		strURL = '?tail_lines=' + objForm.tail_lines.value;
		strURL = strURL + '&message_type=' + objForm.message_type.value;
		strURL = strURL + '&refresh=' + objForm.refresh.value;
		strURL = strURL + '&reverse=' + objForm.reverse.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		strURL = strURL + '&action=view_logfile';
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_start_box("<strong>Log File Filters</strong>", "100%", $colors["header"], "3", "center", "");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
		<form name="form_logfile" action="utilities.php">
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="80">
						Tail Lines:&nbsp;
					</td>
					<td width="1">
						<select name="tail_lines" onChange="applyViewLogFilterChange(document.form_logfile)">
							<?php
							foreach($log_tail_lines AS $tail_lines => $display_text) {
								print "<option value='" . $tail_lines . "'"; if (get_request_var_request("tail_lines") == $tail_lines) { print " selected"; } print ">" . $display_text . "</option>\n";
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="100">
						&nbsp;Message Type:&nbsp;
					</td>
					<td width="1">
						<select name="message_type" onChange="applyViewLogFilterChange(document.form_logfile)">
							<option value="-1"<?php if (get_request_var_request("message_type") == '-1') {?> selected<?php }?>>All</option>
							<option value="1"<?php if (get_request_var_request("message_type") == '1') {?> selected<?php }?>>Stats</option>
							<option value="2"<?php if (get_request_var_request("message_type") == '2') {?> selected<?php }?>>Warnings</option>
							<option value="3"<?php if (get_request_var_request("message_type") == '3') {?> selected<?php }?>>Errors</option>
							<option value="4"<?php if (get_request_var_request("message_type") == '4') {?> selected<?php }?>>Debug</option>
							<option value="5"<?php if (get_request_var_request("message_type") == '5') {?> selected<?php }?>>SQL Calls</option>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input type="submit" name="go" value="Go" title="Set/Refresh Filters">
						<input type="submit" name="clear_x" value="Clear" title="Clear Filters">
						<input type="submit" name="purge_x" value="Purge" title="Purge Log File">
					</td>
				</tr>
				<tr>
					<td nowrap style='white-space: nowrap;' width="80">
						Refresh:&nbsp;
					</td>
					<td width="1">
						<select name="refresh" onChange="applyViewLogFilterChange(document.form_logfile)">
							<?php
							foreach($page_refresh_interval AS $seconds => $display_text) {
								print "<option value='" . $seconds . "'"; if (get_request_var_request("refresh") == $seconds) { print " selected"; } print ">" . $display_text . "</option>\n";
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="100">
						&nbsp;Display Order:&nbsp;
					</td>
					<td width="1">
						<select name="reverse" onChange="applyViewLogFilterChange(document.form_logfile)">
							<option value="1"<?php if (get_request_var_request("reverse") == '1') {?> selected<?php }?>>Newest First</option>
							<option value="2"<?php if (get_request_var_request("reverse") == '2') {?> selected<?php }?>>Oldest First</option>
						</select>
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="80">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="75" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			<input type='hidden' name='action' value='view_logfile'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* read logfile into an array and display */
	$logcontents = tail_file($logfile, get_request_var_request("tail_lines"), get_request_var_request("message_type"), get_request_var_request("filter"));

	if (get_request_var_request("reverse") == 1) {
		$logcontents = array_reverse($logcontents);
	}

	if (get_request_var_request("message_type") > 0) {
		$start_string = "<strong>Log File</strong> [Total Lines: " . sizeof($logcontents) . " - Non-Matching Items Hidden]";
	}else{
		$start_string = "<strong>Log File</strong> [Total Lines: " . sizeof($logcontents) . " - All Items Shown]";
	}

	html_start_box($start_string, "100%", $colors["header"], "3", "center", "");

	$i = 0;
	$j = 0;
	$linecolor = false;
	foreach ($logcontents as $item) {
		$host_start = strpos($item, "Host[");
		$ds_start   = strpos($item, "DS[");

		$new_item = "";

		if ((!$host_start) && (!$ds_start)) {
			$new_item = $item;
		}else{
			while ($host_start) {
				$host_end   = strpos($item, "]", $host_start);
				$host_id    = substr($item, $host_start+5, $host_end-($host_start+5));
				$new_item   = $new_item . substr($item, 0, $host_start + 5) . "<a href='" . htmlspecialchars("host.php?action=edit&id=" . $host_id) . "'>" . substr($item, $host_start + 5, $host_end-($host_start + 5)) . "</a>";
				$item       = substr($item, $host_end);
				$host_start = strpos($item, "Host[");
			}

			$ds_start = strpos($item, "DS[");
			while ($ds_start) {
				$ds_end   = strpos($item, "]", $ds_start);
				$ds_id    = substr($item, $ds_start+3, $ds_end-($ds_start+3));
				$new_item = $new_item . substr($item, 0, $ds_start + 3) . "<a href='" . htmlspecialchars("data_sources.php?action=ds_edit&id=" . $ds_id) . "'>" . substr($item, $ds_start + 3, $ds_end-($ds_start + 3)) . "</a>";
				$item     = substr($item, $ds_end);
				$ds_start = strpos($item, "DS[");
			}

			$new_item = $new_item . $item;
		}

		/* get the background color */
		if ((substr_count($new_item, "ERROR")) || (substr_count($new_item, "FATAL"))) {
			$bgcolor = "FF3932";
		}elseif (substr_count($new_item, "WARN")) {
			$bgcolor = "EACC00";
		}elseif (substr_count($new_item, " SQL ")) {
			$bgcolor = "6DC8FE";
		}elseif (substr_count($new_item, "DEBUG")) {
			$bgcolor = "C4FD3D";
		}elseif (substr_count($new_item, "STATS")) {
			$bgcolor = "96E78A";
		}else{
			if ($linecolor) {
				$bgcolor = "CCCCCC";
			}else{
				$bgcolor = "FFFFFF";
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
					<?php print ">>>>  LINE LIMIT OF 1000 LINES REACHED!!  <<<<";?>
				</td>
			</tr>
			<?php

			break;
		}
	}

	html_end_box();

	include_once("./include/bottom_footer.php");
}

function utilities_clear_logfile() {
	global $colors;

	load_current_session_value("refresh", "sess_logfile_refresh", read_config_option("log_refresh_interval"));

	$refresh["seconds"] = get_request_var_request("refresh");
	$refresh["page"] = "utilities.php?action=view_logfile";

	include_once("./include/top_header.php");

	$logfile = read_config_option("path_cactilog");

	if ($logfile == "") {
		$logfile = "./log/cacti.log";
	}

	html_start_box("<strong>Clear Cacti Log File</strong>", "100%", $colors["header"], "1", "center", "");
	if (file_exists($logfile)) {
		if (is_writable($logfile)) {
			$timestamp = date("m/d/Y h:i:s A");
			$log_fh = fopen($logfile, "w");
			fwrite($log_fh, $timestamp . " - WEBUI: Cacti Log Cleared from Web Management Interface\n");
			fclose($log_fh);
			print "<tr><td>Cacti Log File Cleared</td></tr>";
		}else{
			print "<tr><td><font color='red'><b>Error: Unable to clear log, no write permissions.<b></font></td></tr>";
		}
	}else{
		print "<tr><td><font color='red'><b>Error: Unable to clear log, file does not exist.</b></font></td></tr>";
	}
	html_end_box();
}

function utilities_view_snmp_cache() {
	global $colors, $poller_actions;

	define("MAX_DISPLAY_PAGES", 21);

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("host_id"));
	input_validate_input_number(get_request_var_request("snmp_query_id"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("poller_action"));
	/* ==================================================== */

	/* clean up search filter */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_snmp_current_page");
		kill_session_var("sess_snmp_host_id");
		kill_session_var("sess_snmp_snmp_query_id");
		kill_session_var("sess_snmp_filter");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["host_id"]);
		unset($_REQUEST["snmp_query_id"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_snmp_current_page", "1");
	load_current_session_value("host_id", "sess_snmp_host_id", "-1");
	load_current_session_value("snmp_query_id", "sess_snmp_snmp_query_id", "-1");
	load_current_session_value("filter", "sess_snmp_filter", "");

	$_REQUEST['page_referrer'] = 'view_snmp_cache';
	load_current_session_value('page_referrer', 'page_referrer', 'view_snmp_cache');

	?>
	<script type="text/javascript">
	<!--

	function applyViewSNMPFilterChange(objForm) {
		strURL = '?host_id=' + objForm.host_id.value;
		strURL = strURL + '&snmp_query_id=' + objForm.snmp_query_id.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		strURL = strURL + '&action=view_snmp_cache';
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_start_box("<strong>SNMP Cache Items</strong>", "100%", $colors["header"], "3", "center", "");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
		<form name="form_snmpcache" action="utilities.php">
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						Host:&nbsp;
					</td>
					<td width="1">
						<select name="host_id" onChange="applyViewSNMPFilterChange(document.form_snmpcache)">
							<option value="-1"<?php if (get_request_var_request("host_id") == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request("host_id") == "0") {?> selected<?php }?>>None</option>
							<?php
							if (get_request_var_request("snmp_query_id") == -1) {
								$hosts = db_fetch_assoc("SELECT DISTINCT
											host.id,
											host.description,
											host.hostname
											FROM (host_snmp_cache,snmp_query,host)
											WHERE host_snmp_cache.host_id=host.id
											AND host_snmp_cache.snmp_query_id=snmp_query.id
											ORDER by host.description");
							}else{
								$hosts = db_fetch_assoc("SELECT DISTINCT
											host.id,
											host.description,
											host.hostname
											FROM (host_snmp_cache,snmp_query,host)
											WHERE host_snmp_cache.host_id=host.id
											AND host_snmp_cache.snmp_query_id=snmp_query.id
											AND host_snmp_cache.snmp_query_id='" . get_request_var_request("snmp_query_id") . "'
											ORDER by host.description");
							}
							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='" . $host["id"] . "'"; if (get_request_var_request("host_id") == $host["id"]) { print " selected"; } print ">" . $host["description"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="90">
						&nbsp;Query Name:&nbsp;
					</td>
					<td width="1">
						<select name="snmp_query_id" onChange="applyViewSNMPFilterChange(document.form_snmpcache)">
							<option value="-1"<?php if (get_request_var_request("host_id") == "-1") {?> selected<?php }?>>Any</option>
							<?php
							if (get_request_var_request("host_id") == -1) {
								$snmp_queries = db_fetch_assoc("SELECT DISTINCT
											snmp_query.id,
											snmp_query.name
											FROM (host_snmp_cache,snmp_query,host)
											WHERE host_snmp_cache.host_id=host.id
											AND host_snmp_cache.snmp_query_id=snmp_query.id
											ORDER by snmp_query.name");
							}else{
								$snmp_queries = db_fetch_assoc("SELECT DISTINCT
											snmp_query.id,
											snmp_query.name
											FROM (host_snmp_cache,snmp_query,host)
											WHERE host_snmp_cache.host_id=host.id
											AND host_snmp_cache.host_id='" . get_request_var_request("host_id") . "'
											AND host_snmp_cache.snmp_query_id=snmp_query.id
											ORDER by snmp_query.name");
							}
							if (sizeof($snmp_queries) > 0) {
							foreach ($snmp_queries as $snmp_query) {
								print "<option value='" . $snmp_query["id"] . "'"; if (get_request_var_request("snmp_query_id") == $snmp_query["id"]) { print " selected"; } print ">" . $snmp_query["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input type="submit" name="go" value="Go" title="Set/Refresh Filters">
						<input type="submit" name="clear_x" value="Clear" title="Clear Fitlers">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			<input type='hidden' name='action' value='view_snmp_cache'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = "";

	/* filter by host */
	if (get_request_var_request("host_id") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("host_id") == "0") {
		$sql_where .= " AND host.id=0";
	}elseif (!empty($_REQUEST["host_id"])) {
		$sql_where .= " AND host.id=" . get_request_var_request("host_id");
	}

	/* filter by query name */
	if (get_request_var_request("snmp_query_id") == "-1") {
		/* Show all items */
	}elseif (!empty($_REQUEST["snmp_query_id"])) {
		$sql_where .= " AND host_snmp_cache.snmp_query_id=" . get_request_var_request("snmp_query_id");
	}

	/* filter by search string */
	if (get_request_var_request("filter") != "") {
		$sql_where .= " AND (host.description LIKE '%%" . get_request_var_request("filter") . "%%'
			OR snmp_query.name LIKE '%%" . get_request_var_request("filter") . "%%'
			OR host_snmp_cache.field_name LIKE '%%" . get_request_var_request("filter") . "%%'
			OR host_snmp_cache.field_value LIKE '%%" . get_request_var_request("filter") . "%%'
			OR host_snmp_cache.oid LIKE '%%" . get_request_var_request("filter") . "%%')";
	}

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM (host_snmp_cache,snmp_query,host)
		WHERE host_snmp_cache.host_id=host.id
		AND host_snmp_cache.snmp_query_id=snmp_query.id
		$sql_where");

	$snmp_cache_sql = "SELECT
		host_snmp_cache.*,
		host.description,
		snmp_query.name
		FROM (host_snmp_cache,snmp_query,host)
		WHERE host_snmp_cache.host_id=host.id
		AND host_snmp_cache.snmp_query_id=snmp_query.id
		$sql_where
		LIMIT " . (read_config_option("num_rows_data_source")*(get_request_var_request("page")-1)) . "," . read_config_option("num_rows_data_source");

//	print $snmp_cache_sql;

	$snmp_cache = db_fetch_assoc($snmp_cache_sql);

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, read_config_option("num_rows_data_source"), $total_rows, "utilities.php?action=view_snmp_cache&host_id=" . get_request_var_request("host_id") . "&filter=" . get_request_var_request("filter"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='7'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("utilities.php?action=view_snmp_cache&host_id=" . get_request_var_request("host_id") . "&filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("num_rows_data_source")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_data_source")) || ($total_rows < (read_config_option("num_rows_data_source")*get_request_var_request("page")))) ? $total_rows : (read_config_option("num_rows_data_source")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if ((get_request_var_request("page") * read_config_option("num_rows_data_source")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("utilities.php?action=view_snmp_cache&host_id=" . get_request_var_request("host_id") . "&filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * read_config_option("num_rows_data_source")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	html_header(array("Details"));

	$i = 0;
	if (sizeof($snmp_cache) > 0) {
	foreach ($snmp_cache as $item) {
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
		?>
		<td>
			Host: <?php print (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["description"])) : $item["description"]);?>
			, SNMP Query: <?php print (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["name"])) : $item["name"]);?>
		</td>
		</tr>
		<?php
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
		?>
		<td>
			Index: <?php print $item["snmp_index"];?>
			, Field Name: <?php print (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["field_name"])) : $item["field_name"]);?>
			, Field Value: <?php print (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["field_value"])) : $item["field_value"]);?>
		</td>
		</tr>
		<?php
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
		?>
		<td>
			OID: <?php print (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["oid"])) : $item["oid"]);?>
		</td>
		</tr>
		<?php
	}
	}

	print $nav;

	html_end_box();
}

function utilities_view_poller_cache() {
	global $colors, $poller_actions;

	define("MAX_DISPLAY_PAGES", 21);

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("host_id"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("poller_action"));
	/* ==================================================== */

	/* clean up search filter */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up sort direction */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_poller_current_page");
		kill_session_var("sess_poller_host_id");
		kill_session_var("sess_poller_poller_action");
		kill_session_var("sess_poller_filter");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["host_id"]);
		unset($_REQUEST["poller_action"]);
	}

	if ((!empty($_SESSION["sess_poller_action"])) && (!empty($_REQUEST["poller_action"]))) {
		if ($_SESSION["sess_poller_poller_action"] != $_REQUEST["poller_action"]) {
			$_REQUEST["page"] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_poller_current_page", "1");
	load_current_session_value("host_id", "sess_poller_host_id", "-1");
	load_current_session_value("poller_action", "sess_poller_poller_action", "-1");
	load_current_session_value("filter", "sess_poller_filter", "");
	load_current_session_value("sort_column", "sess_poller_sort_column", "data_template_data.name_cache");
	load_current_session_value("sort_direction", "sess_poller_sort_direction", "ASC");

	$_REQUEST['page_referrer'] = 'view_poller_cache';
	load_current_session_value('page_referrer', 'page_referrer', 'view_poller_cache');

	?>
	<script type="text/javascript">
	<!--

	function applyPItemFilterChange(objForm) {
		strURL = '?poller_action=' + objForm.poller_action.value;
		strURL = strURL + '&host_id=' + objForm.host_id.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		strURL = strURL + '&action=view_poller_cache';
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_start_box("<strong>Poller Cache Items</strong>", "100%", $colors["header"], "3", "center", "");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
		<form name="form_pollercache" action="utilities.php">
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						Host:&nbsp;
					</td>
					<td width="1">
						<select name="host_id" onChange="applyPItemFilterChange(document.form_pollercache)">
							<option value="-1"<?php if (get_request_var_request("host_id") == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request("host_id") == "0") {?> selected<?php }?>>None</option>
							<?php
							$hosts = db_fetch_assoc("select id,description,hostname from host order by description");

							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='" . $host["id"] . "'"; if (get_request_var_request("host_id") == $host["id"]) { print " selected"; } print ">" . $host["description"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Action:&nbsp;
					</td>
					<td width="1">
						<select name="poller_action" onChange="applyPItemFilterChange(document.form_pollercache)">
							<option value="-1"<?php if (get_request_var_request("poller_action") == '-1') {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request("poller_action") == '0') {?> selected<?php }?>>SNMP</option>
							<option value="1"<?php if (get_request_var_request("poller_action") == '1') {?> selected<?php }?>>Script</option>
							<option value="2"<?php if (get_request_var_request("poller_action") == '2') {?> selected<?php }?>>Script Server</option>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="40" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input type="submit" name="go" value="Go" title="Set/Refresh Filters">
						<input type="submit" name="clear_x" value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			<input type='hidden' name='action' value='view_poller_cache'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = "WHERE poller_item.local_data_id=data_template_data.local_data_id";

	if (get_request_var_request("poller_action") != "-1") {
		$sql_where .= " AND poller_item.action='" . get_request_var_request("poller_action") . "'";
	}

	if (get_request_var_request("host_id") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("host_id") == "0") {
		$sql_where .= " AND poller_item.host_id=0";
	}elseif (!empty($_REQUEST["host_id"])) {
		$sql_where .= " AND poller_item.host_id=" . get_request_var_request("host_id");
	}

	if (strlen(get_request_var_request("filter"))) {
		$sql_where .= " AND (data_template_data.name_cache LIKE '%%" . get_request_var_request("filter") . "%%'
			OR host.description LIKE '%%" . get_request_var_request("filter") . "%%'
			OR poller_item.arg1 LIKE '%%" . get_request_var_request("filter") . "%%'
			OR poller_item.hostname LIKE '%%" . get_request_var_request("filter") . "%%'
			OR poller_item.rrd_path  LIKE '%%" . get_request_var_request("filter") . "%%')";
	}

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM data_template_data
		RIGHT JOIN (poller_item
		LEFT JOIN host
		ON poller_item.host_id=host.id)
		ON data_template_data.local_data_id=poller_item.local_data_id
		$sql_where");

	$poller_sql = "SELECT
		poller_item.*,
		data_template_data.name_cache,
		host.description
		FROM data_template_data
		RIGHT JOIN (poller_item
		LEFT JOIN host
		ON poller_item.host_id=host.id)
		ON data_template_data.local_data_id=poller_item.local_data_id
		$sql_where
		ORDER BY " . get_request_var_request("sort_column") . " " . get_request_var_request("sort_direction") . ", action ASC
		LIMIT " . (read_config_option("num_rows_data_source")*(get_request_var_request("page")-1)) . "," . read_config_option("num_rows_data_source");

//	print $poller_sql;

	$poller_cache = db_fetch_assoc($poller_sql);

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, read_config_option("num_rows_data_source"), $total_rows, "utilities.php?action=view_poller_cache&host_id=" . get_request_var_request("host_id") . "&poller_action=" . get_request_var_request("poller_action"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='7'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("utilities.php?action=view_poller_cache&host_id=" . get_request_var_request("host_id") . "&poller_action=" . get_request_var_request("poller_action") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("num_rows_data_source")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_data_source")) || ($total_rows < (read_config_option("num_rows_data_source")*get_request_var_request("page")))) ? $total_rows : (read_config_option("num_rows_data_source")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if ((get_request_var_request("page") * read_config_option("num_rows_data_source")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("utilities.php?action=view_poller_cache&host_id=" . get_request_var_request("host_id") . "&poller_action=" . get_request_var_request("poller_action") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * read_config_option("num_rows_data_source")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"data_template_data.name_cache" => array("Data Source Name", "ASC"),
		"nosort" => array("Details", "ASC"));

	html_header_sort($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"));

	$i = 0;
	if (sizeof($poller_cache) > 0) {
	foreach ($poller_cache as $item) {
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
			?>
			<td width="375">
				<a class="linkEditMain" href="<?php print htmlspecialchars("data_sources.php?action=ds_edit&id=" . $item["local_data_id"]);?>"><?php print (strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["name_cache"]):$item["name_cache"]);?></a>
			</td>

			<td>
			<?php
			if ($item["action"] == 0) {
				if ($item["snmp_version"] != 3) {
					$details =
						"SNMP Version: " . $item["snmp_version"] . ", " .
						"Community: " . $item["snmp_community"] . ", " .
						"OID: " . (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["arg1"])) : $item["arg1"]);
				}else{
					$details =
						"SNMP Version: " . $item["snmp_version"] . ", " .
						"User: " . $item["snmp_username"] . ", OID: " . $item["arg1"];
				}
			}elseif ($item["action"] == 1) {
					$details = "Script: " . (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["arg1"])) : $item["arg1"]);
			}else{
					$details = "Script Server: " . (strlen(get_request_var_request("filter")) ? (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["arg1"])) : $item["arg1"]);
			}

			print $details;
			?>
			</td>
		</tr>
		<?php

		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
		?>
			<td>
			</td>
			<td>
				RRD: <?php print $item["rrd_path"];?>
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
	global $colors;

	html_start_box("<strong>Cacti System Utilities</strong>", "100%", $colors["header"], "3", "center", "");

	?>
	<colgroup span="3">
		<col valign="top" width="20%"></col>
		<col valign="top" width="80%"></col>
	</colgroup>

	<?php html_header(array("Technical Support"), 2); ?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='<?php print htmlspecialchars("utilities.php?action=view_tech");?>'>Technical Support</a>
		</td>
		<td class="textArea">
			Cacti technical support page.  Used by developers and technical support persons to assist with issues in Cacti.  Includes checks for common configuration issues.
		</td>
	</tr>

	<?php html_header(array("Log Administration"), 2);?>

	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='<?php print htmlspecialchars("utilities.php?action=view_logfile");?>'>View Cacti Log File</a>
		</td>
		<td class="textArea">
			The Cacti Log File stores statistic, error and other message depending on system settings.  This information can be used to identify problems with the poller and application.
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='<?php print htmlspecialchars("utilities.php?action=view_user_log");?>'>View User Log</a>
		</td>
		<td class="textArea">
			Allows Administrators to browse the user log.  Administrators can filter and export the log as well.
		</td>
	</tr>

	<?php html_header(array("Poller Cache Administration"), 2); ?>

	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='<?php print htmlspecialchars("utilities.php?action=view_poller_cache");?>'>View Poller Cache</a>
		</td>
		<td class="textArea">
			This is the data that is being passed to the poller each time it runs. This data is then in turn executed/interpreted and the results are fed into the rrd files for graphing or the database for display.
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate2"];?>">
		<td class="textArea">
			<a href='<?php print htmlspecialchars("utilities.php?action=view_snmp_cache");?>'>View SNMP Cache</a>
		</td>
		<td class="textArea">
			The SNMP cache stores information gathered from SNMP queries. It is used by cacti to determine the OID to use when gathering information from an SNMP-enabled host.
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='<?php print htmlspecialchars("utilities.php?action=clear_poller_cache");?>'>Rebuild Poller Cache</a>
		</td>
		<td class="textArea">
			The poller cache will be cleared and re-generated if you select this option. Sometimes host/data source data can get out of sync with the cache in which case it makes sense to clear the cache and start over.
		</td>
	</tr>

	<?php

	api_plugin_hook('utilities_list');

	html_end_box();
}

?>


