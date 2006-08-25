<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

include("./include/auth.php");
include_once("./lib/utility.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }
if (isset($_REQUEST["sort_direction"])) { $_REQUEST["action"] = "view_poller_cache"; }

switch ($_REQUEST["action"]) {
	case 'clear_poller_cache':
		include_once("./include/top_header.php");

		/* obtain timeout settings */
		$max_execution = ini_get("max_execution_time");
		$max_memory = ini_get("memory_limit");

		ini_set("max_execution_time", "0");
		ini_set("memory_limit", "32M");

		repopulate_poller_cache();

		ini_set("max_execution_time", $max_execution);
		ini_set("memory_limit", $max_memory);

		utilities_view_poller_cache();

		include_once("./include/bottom_footer.php");
		break;
	case 'view_snmp_cache':
		include_once("./include/top_header.php");

		utilities();
		utilities_view_snmp_cache();

		include_once("./include/bottom_footer.php");
		break;
	case 'view_poller_cache':
		include_once("./include/top_header.php");

		utilities_view_poller_cache();

		include_once("./include/bottom_footer.php");
		break;
	case 'view_logfile':
		include_once("./include/top_header.php");

		utilities();
		utilities_view_logfile();

		include_once("./include/bottom_footer.php");
		break;
	case 'clear_logfile':
		include_once("./include/top_header.php");

		utilities();

		utilities_clear_logfile();

		#utilities_view_logfile();


		include_once("./include/bottom_footer.php");
		break;
	case 'clear_user_log':
		include_once("./include/top_header.php");

		utilities();
		utilities_clear_user_log();

		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");

		utilities();

		include_once("./include/bottom_footer.php");
		break;
}

/* -----------------------
    Utilities Functions
   ----------------------- */

function utilities_clear_user_log() {
	$users = db_fetch_assoc("SELECT DISTINCT username FROM user_log");
	if (!isset($users[0]))
		return;
	foreach ($users as $user) {
		$total_rows = db_fetch_cell("SELECT COUNT(username) FROM user_log WHERE username = '" . $user['username'] . "'");
		if ($total_rows > 1)
			db_execute("DELETE FROM user_log WHERE username = '" . $user['username'] . "' ORDER BY time LIMIT " . ($total_rows - 1));
	}
}

function utilities_view_logfile() {
	global $colors;

	$logfile = read_config_option("path_cactilog");

	if ($logfile == "") {
		$logfile = "./log/rrd.log";
	}

	/* helps determine output color */
	$linecolor = True;

	/* read logfile into an array and display */
	if (file_exists($logfile)) {
		$logcontents = file($logfile);
	}else{
		touch($logfile);
		$logcontents = file($logfile);
	}

	$logcontents = array_reverse($logcontents);

	html_start_box("<strong>View Cacti Log File</strong> [" . sizeof($logcontents) . " Item" . ((sizeof($logcontents) > 0) ? "s" : "") . "]", "98%", $colors["header"], "3", "center", "");

	$i = 0;
	foreach ($logcontents as $item) {
		if (strpos($item,":") <> 0) {
			if ($linecolor = True) {
				$linecolor = False;
			}else{
				$linecolor = True;
			}
		}

        $host_start = strpos($item, "Host[");
        $ds_start = strpos($item, "DS[");

        $new_item = "";

		if ((!$host_start) && (!$ds_start)) {
			$new_item = $item;
		}else{
	        if ($host_start) {
    	    	$host_end = strpos($item, "]", $host_start);
        		$host_id = substr($item, $host_start+5, $host_end-($host_start+5));
				$new_item = $new_item . substr($item, 0, $host_start + 5) . "<a href='host.php?action=edit&id=" . $host_id . "'>" . substr($item, $host_start + 5, $host_end-($host_start + 5)) . "</a>";
				$item = substr($item, $host_end);
			}

			$ds_start = strpos($item, "DS[");
			if ($ds_start) {
				$ds_end = strpos($item, "]", $ds_start);
				$ds_id = substr($item, $ds_start+3, $ds_end-($ds_start+3));
				$new_item = $new_item . substr($item, 0, $ds_start + 3) . "<a href='data_sources.php?action=ds_edit&id=" . $ds_id . "'>" . substr($item, $ds_start + 3, $ds_end-($ds_start + 3)) . "</a>";
				$item = substr($item, $ds_end);
				$new_item = $new_item . $item;
			}else{
				$new_item = $new_item . $item;
			}
		}

		switch ($linecolor) {
			case True:
				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
				?>
				<td>
				<?php print $new_item;?>
				</td>
				</tr>
				<?php
				break;
			case False:
				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
				?>
				<td>
				<?php print $new_item;?>
				</td>
				</tr>
				<?php
				break;
		}
	}

	html_end_box();
}

function utilities_clear_logfile() {
	global $colors;

	$logfile = read_config_option("path_cactilog");

	if ($logfile == "") {
		$logfile = "./log/cacti.log";
	}

	html_start_box("<strong>Clear Cacti Log File</strong>", "98%", $colors["header"], "1", "center", "");
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
	global $colors;

	$snmp_cache = db_fetch_assoc("select host_snmp_cache.*,
		host.description,
		snmp_query.name
		from (host_snmp_cache,snmp_query,host)
		where host_snmp_cache.host_id=host.id
		and host_snmp_cache.snmp_query_id=snmp_query.id
		order by host_snmp_cache.host_id,host_snmp_cache.snmp_query_id,host_snmp_cache.snmp_index");

	html_start_box("<strong>View SNMP Cache</strong> [" . sizeof($snmp_cache) . " Item" . ((sizeof($snmp_cache) > 0) ? "s" : "") . "]", "98%", $colors["header"], "3", "center", "");

	$i = 0;
	if (sizeof($snmp_cache) > 0) {
	foreach ($snmp_cache as $item) {
			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
			?>
				<td>
					Host: <?php print $item["description"];?>, SNMP Query: <?php print $item["name"];?>
				</td>
			</tr>
			<?php
			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
			?>
				<td>
					Index: <?php print $item["snmp_index"];?>, Field Name: <?php print $item["field_name"];?>, Field Value: <?php print $item["field_value"];?>
				</td>
			</tr>
			<?php
			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
			?>
				<td>
					OID: <?php print $item["oid"];?>
				</td>
			</tr>
			<?php
	}
	}

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

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
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
	load_current_session_value("sort_column", "sess_poller_sort_column", "hostname");
	load_current_session_value("sort_direction", "sess_poller_sort_direction", "ASC");

	html_start_box("<strong>Poller Cache Items</strong>", "98%", $colors["header"], "3", "center", "");

	include("./include/html/inc_poller_item_filter_table.php");

	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = "WHERE poller_item.local_data_id=data_template_data.local_data_id";

	if ($_REQUEST["poller_action"] == "-1") {
		/* Show all items */
	}else {
		$sql_where .= " AND poller_item.action='" . $_REQUEST["poller_action"] . "'";
	}

	if ($_REQUEST["host_id"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["host_id"] == "0") {
		$sql_where .= " AND poller_item.host_id=0";
	}elseif (!empty($_REQUEST["host_id"])) {
		$sql_where .= " AND poller_item.host_id=" . $_REQUEST["host_id"];
	}

	html_start_box("", "98%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM data_template_data
		INNER JOIN (poller_item
		INNER JOIN host
		ON poller_item.host_id = host.id)
		ON data_template_data.local_data_id = poller_item.local_data_id
		$sql_where");

	$poller_sql = "SELECT
		poller_item.*,
		data_template_data.name_cache,
		host.description
		FROM data_template_data
		INNER JOIN (poller_item
		INNER JOIN host
		ON poller_item.host_id = host.id)
		ON data_template_data.local_data_id = poller_item.local_data_id
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"] . ", action ASC
		LIMIT " . (read_config_option("num_rows_device")*($_REQUEST["page"]-1)) . "," . read_config_option("num_rows_device");

	$poller_cache = db_fetch_assoc($poller_sql);

	/* generate page list */
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, read_config_option("num_rows_device"), $total_rows, "utilities.php?action=view_poller_cache&host_id=" . $_REQUEST["host_id"] . "&poller_action=" . $_REQUEST["poller_action"]);

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='7'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='utilities.php?action=view_poller_cache&host_id=" . $_REQUEST["host_id"] . "&poller_action=" . $_REQUEST["poller_action"] . "&page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("num_rows_device")*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (read_config_option("num_rows_device")*$_REQUEST["page"]))) ? $total_rows : (read_config_option("num_rows_device")*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["page"] * read_config_option("num_rows_device")) < $total_rows) { $nav .= "<a class='linkOverDark' href='utilities.php?action=view_poller_cache&host_id=" . $_REQUEST["host_id"] . "&poller_action=" . $_REQUEST["poller_action"] . "&page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * read_config_option("num_rows_device")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"data_template_data.name_cache" => array("Data Source<br>Name", "ASC"),
		"" => array("Details", "ASC"));

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$i = 0;
	if (sizeof($poller_cache) > 0) {
	foreach ($poller_cache as $item) {
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
			?>
			<td width="375">
				<a class="linkEditMain" href="data_sources.php?action=ds_edit&id=<?php print $item["local_data_id"];?>"><?php print $item["name_cache"];?></a>
			</td>

			<td>
			<?php
			if ($item["action"] == 0) {
				if ($item["snmp_version"] != 3) {
					$details = "SNMP Version: " . $item["snmp_version"] . ", Community: " . $item["snmp_community"] . ", OID: " . $item["arg1"];
				}else{
					$details = "SNMP Version: " . $item["snmp_version"] . ", User: " . $item["snmp_username"] . ", OID: " . $item["arg1"];
				}
			}elseif ($item["action"] == 1) {
					$details = "Script: " . $item["arg1"];
			}else{
					$details = "Script Server: " . $item["arg1"];
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

	html_end_box();
}

function utilities() {
	global $colors;

	html_start_box("<strong>Cacti System Utilities</strong>", "98%", $colors["header"], "3", "center", "");

	html_header(array("Poller Cache Administration"), 2);

	?>
	<colgroup span="3">
		<col valign="top" width="20"></col>
		<col valign="top" width="10"></col>
	</colgroup>

	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<p><a href='utilities.php?action=view_poller_cache'>View Poller Cache</a></p>
		</td>
		<td class="textArea">
			<p>This is the data that is being passed to the poller each time it runs. This data is then in turn executed/interpreted and the results are fed into the rrd files for graphing or the database for display.</p>
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate2"];?>">
		<td class="textArea">
			<p><a href='utilities.php?action=view_snmp_cache'>View SNMP Cache</a></p>
		</td>
		<td class="textArea">
			<p>The SNMP cache stores information gathered from SNMP queries. It is used by cacti to determine the OID to use when gathering information from an SNMP-enabled host.</p>
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<p><a href='utilities.php?action=clear_poller_cache'>Clear Poller Cache</a></p>
		</td>
		<td class="textArea">
			<p>The poller cache will be cleared and re-generated if you select this option. Sometimes host/data source data can get out of sync with the cache in which case it makes sense to clear the cache and start over.</p>
		</td>
	</tr>

	<?php html_header(array("User Log Administration"), 2);?>

	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<p><a href='utilities.php?action=clear_user_log'>Clear User Log</a></p>
		</td>
		<td class="textArea">
			<p>The User Log stores an entry for each time a user logs into Cacti.  Clearing this log will purge all entries except the last login for each user.</p>
		</td>
	</tr>

	<?php html_header(array("System Log Administration"), 2);?>

	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<p><a href='utilities.php?action=view_logfile'>View Cacti Log File</a></p>
		</td>
		<td class="textArea">
			<p>The Cacti Log File stores statistic, error and other message depending on system settings.  This information can be used to identify problems with the poller and application.</p>
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate2"];?>">
		<td class="textArea">
			<p><a href='utilities.php?action=clear_logfile'>Clear Cacti Log File</a></p>
		</td>
		<td class="textArea">
			<p>This action will reset the Cacti Log File.  Please note that if you are using the Syslog/Eventlog only, this action will have no effect.</p>
		</td>
	</tr>
	<?php

	html_end_box();
}

?>