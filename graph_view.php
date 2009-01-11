<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2008 The Cacti Group                                 |
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

$guest_account = true;
include("./include/auth.php");
include_once("./lib/html_tree.php");
include_once("./lib/timespan_settings.php");
include_once("./include/top_graph_header.php");

/* ================= input validation ================= */
input_validate_input_number(get_request_var("branch_id"));
input_validate_input_number(get_request_var("hide"));
input_validate_input_number(get_request_var("tree_id"));
input_validate_input_number(get_request_var("leaf_id"));
input_validate_input_number(get_request_var("rra_id"));
input_validate_input_regex(get_request_var_request('graph_list'), "^([\,0-9]+)$");
input_validate_input_regex(get_request_var_request('graph_add'), "^([\,0-9]+)$");
input_validate_input_regex(get_request_var_request('graph_remove'), "^([\,0-9]+)$");
/* ==================================================== */

if (isset($_GET["hide"])) {
	if (($_GET["hide"] == "0") || ($_GET["hide"] == "1")) {
		/* only update expand/contract info is this user has rights to keep their own settings */
		if ((isset($current_user)) && ($current_user["graph_settings"] == "on")) {
			db_execute("delete from settings_tree where graph_tree_item_id=" . $_GET["branch_id"] . " and user_id=" . $_SESSION["sess_user_id"]);
			db_execute("insert into settings_tree (graph_tree_item_id,user_id,status) values (" . $_GET["branch_id"] . "," . $_SESSION["sess_user_id"] . "," . $_GET["hide"] . ")");
		}
	}
}

if (ereg("action=(tree|preview|list)", get_browser_query_string())) {
	$_SESSION["sess_graph_view_url_cache"] = get_browser_query_string();
}

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
case 'tree':
	if ((read_config_option("auth_method") != 0) && (empty($current_user["show_tree"]))) {
		print "<strong><font size='+1' color='FF0000'>YOU DO NOT HAVE RIGHTS FOR TREE VIEW</font></strong>"; exit;
	}

	/* if cacti's builtin authentication is turned on then make sure to take
	graph permissions into account here. if a user does not have rights to a
	particular graph; do not show it. they will get an access denied message
	if they try and view the graph directly. */

	$access_denied = false;
	$tree_parameters = array();

	if ((!isset($_GET["tree_id"])) && (isset($_SESSION['dhtml_tree']))) {
		unset($_SESSION["dhtml_tree"]);
	}

	if (isset($_GET["tree_id"])) {
		$_SESSION["sess_graph_view_last_tree"] = get_browser_query_string();
	}

	$tree_dropdown_html = draw_tree_dropdown((isset($_GET["tree_id"]) ? $_GET["tree_id"] : "0"));

	/* don't even print the table if there is not >1 tree */
	if ((!empty($tree_dropdown_html)) && (read_graph_config_option("default_tree_view_mode") == "1")) {
		print "
		<table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='3'>
			<tr>
				$tree_dropdown_html
			</tr>
		</table>\n";
	}

	if (isset($_SESSION["sess_view_tree_id"])) {
		if (read_config_option("auth_method") != 0) {
			/* take tree permissions into account here, if the user does not have permission
			give an "access denied" message */
			$access_denied = !(is_tree_allowed($_SESSION["sess_view_tree_id"]));

			if ($access_denied == true) {
				print "<strong><font size='+1' color='FF0000'>ACCESS DENIED</font></strong>"; exit;
			}
		}

		if (read_graph_config_option("default_tree_view_mode") == "1") {
			grow_graph_tree($_SESSION["sess_view_tree_id"], (!empty($start_branch) ? $start_branch : 0), isset($_SESSION["sess_user_id"]) ? $_SESSION["sess_user_id"] : 0, $tree_parameters);
		}elseif (read_graph_config_option("default_tree_view_mode") == "2") {
			grow_right_pane_tree((isset($_GET["tree_id"]) ? $_GET["tree_id"] : 0), (isset($_GET["leaf_id"]) ? $_GET["leaf_id"] : 0), (isset($_GET["host_group_data"]) ? urldecode($_GET["host_group_data"]) : 0));
		}
	}

	break;
case 'preview':
	define("ROWS_PER_PAGE", read_graph_config_option("preview_graphs_per_page"));

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("host_id"));
	input_validate_input_number(get_request_var_request("graph_template_id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	$sql_or = ""; $sql_where = ""; $sql_join = "";

	if ((read_config_option("auth_method") != 0) && (empty($current_user["show_preview"]))) {
		print "<strong><font size='+1' color='FF0000'>YOU DO NOT HAVE RIGHTS FOR PREVIEW VIEW</font></strong>"; exit;
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_graph_view_current_page");
		kill_session_var("sess_graph_view_filter");
		kill_session_var("sess_graph_view_graph_template");
		kill_session_var("sess_graph_view_host");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["host_id"]);
		unset($_REQUEST["graph_template_id"]);
		unset($_REQUEST["graph_list"]);
		unset($_REQUEST["graph_add"]);
		unset($_REQUEST["graph_remove"]);
	}

	/* reset the page counter to '1' if a search in initiated */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["page"] = "1";
	}

	load_current_session_value("host_id", "sess_graph_view_host", "0");
	load_current_session_value("graph_template_id", "sess_graph_view_graph_template", "0");
	load_current_session_value("filter", "sess_graph_view_filter", "");
	load_current_session_value("page", "sess_graph_view_current_page", "1");

	/* graph permissions */
	if (read_config_option("auth_method") != 0) {
		$sql_where = "where " . get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);

		$sql_join = "left join host on (host.id=graph_local.host_id)
			left join graph_templates on (graph_templates.id=graph_local.graph_template_id)
			left join user_auth_perms on ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))";
	}else{
		$sql_where = "";
		$sql_join = "";
	}
	/* the user select a bunch of graphs of the 'list' view and wants them dsplayed here */
	if (isset($_REQUEST["style"])) {
		if (get_request_var_request("style") == "selective") {

			/* process selected graphs */
			if (! empty($_REQUEST["graph_list"])) {
				foreach (explode(",",$_REQUEST["graph_list"]) as $item) {
					$graph_list[$item] = 1;
				}
			}else{
				$graph_list = array();
			}
			if (! empty($_REQUEST["graph_add"])) {
				foreach (explode(",",$_REQUEST["graph_add"]) as $item) {
					$graph_list[$item] = 1;
				}
			}
			/* remove items */
			if (! empty($_REQUEST["graph_remove"])) {
				foreach (explode(",",$_REQUEST["graph_remove"]) as $item) {
					unset($graph_list[$item]);
				}
			}

			$i = 0;
			foreach ($graph_list as $item => $value) {
				$graph_array[$i] = $item;
				$i++;
			}

			if ((isset($graph_array)) && (sizeof($graph_array) > 0)) {
				/* build sql string including each graph the user checked */
				$sql_or = "AND " . array_to_sql_or($graph_array, "graph_templates_graph.local_graph_id");

				/* clear the filter vars so they don't affect our results */
				$_REQUEST["filter"]  = "";
				$_REQUEST["host_id"] = "0";

				$set_rra_id = empty($rra_id) ? read_graph_config_option("default_rra_id") : get_request_var_request("rra_id");
			}
		}
	}

	$sql_base = "FROM (graph_templates_graph,graph_local)
		$sql_join
		$sql_where
		" . (empty($sql_where) ? "WHERE" : "AND") . "   graph_templates_graph.local_graph_id > 0
		AND graph_templates_graph.local_graph_id=graph_local.id
		AND graph_templates_graph.title_cache like '%%" . get_request_var_request("filter") . "%%'
		" . (empty($_REQUEST["host_id"]) ? "" : " and graph_local.host_id=" . get_request_var_request("host_id")) . "
		" . (empty($_REQUEST["graph_template_id"]) ? "" : " and graph_local.graph_template_id=" . get_request_var_request("graph_template_id")) . "
		$sql_or";

	$total_rows = count(db_fetch_assoc("SELECT
		graph_templates_graph.local_graph_id
		$sql_base"));

	/* reset the page if you have changed some settings */
	if (ROWS_PER_PAGE * (get_request_var_request("page")-1) >= $total_rows) {
		$_REQUEST["page"] = "1";
	}

	$graphs = db_fetch_assoc("SELECT
		graph_templates_graph.local_graph_id,
		graph_templates_graph.title_cache
		$sql_base
		GROUP BY graph_templates_graph.local_graph_id
		ORDER BY graph_templates_graph.title_cache
		LIMIT " . (ROWS_PER_PAGE*(get_request_var_request("page")-1)) . "," . ROWS_PER_PAGE);

	?>
	<script type="text/javascript">
	<!--

	function applyGraphPreviewFilterChange(objForm) {
		strURL = '?action=preview';
		strURL = strURL + '&host_id=' + objForm.host_id.value;
		strURL = strURL + '&graph_template_id=' + objForm.graph_template_id.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	-->
	</script>
	<?php

	/* include graph view filter selector */
	html_graph_start_box(3, FALSE);

	?>
	<tr bgcolor="<?php print $colors["panel"];?>" class="noprint">
		<form name="form_graph_view" method="post">
		<td class="noprint">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr class="noprint">
					<td nowrap style='white-space: nowrap;' width="40">
						&nbsp;<strong>Host:</strong>&nbsp;
					</td>
					<td width="1">
						<select name="host_id" onChange="applyGraphPreviewFilterChange(document.form_graph_view)">
							<option value="0"<?php if (get_request_var_request("host_id") == "0") {?> selected<?php }?>>Any</option>

							<?php
							if (read_config_option("auth_method") != 0) {
								/* get policy information for the sql where clause */
								$sql_where = get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);

								$hosts = db_fetch_assoc("SELECT DISTINCT host.id, host.description as name
									FROM (graph_templates_graph,graph_local)
									LEFT JOIN host ON (host.id=graph_local.host_id)
									LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
									LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
									WHERE graph_templates_graph.local_graph_id=graph_local.id
									" . (empty($sql_where) ? "" : "and $sql_where") . "
									ORDER BY name");
							}else{
								$hosts = db_fetch_assoc("SELECT DISTINCT host.id, host.description as name
									FROM host
									ORDER BY name");
							}

							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='" . $host["id"] . "'"; if (get_request_var_request("host_id") == $host["id"]) { print " selected"; } print ">" . $host["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="70">
						&nbsp;<strong>Template:</strong>&nbsp;
					</td>
					<td width="1">
						<select name="graph_template_id" onChange="applyGraphPreviewFilterChange(document.form_graph_view)">
							<option value="0"<?php if (get_request_var_request("graph_template_id") == "0") {?> selected<?php }?>>Any</option>

							<?php
							if (read_config_option("auth_method") != 0) {
								$graph_templates = db_fetch_assoc("SELECT DISTINCT graph_templates.*
									FROM (graph_templates_graph,graph_local)
									LEFT JOIN host ON (host.id=graph_local.host_id)
									LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
									LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
									WHERE graph_templates_graph.local_graph_id=graph_local.id
									" . (empty($sql_where) ? "" : "and $sql_where") . "
									ORDER BY name");
							}else{
								$graph_templates = db_fetch_assoc("SELECT DISTINCT graph_templates.*
									FROM graph_templates
									ORDER BY name");
							}

							if (sizeof($graph_templates) > 0) {
							foreach ($graph_templates as $template) {
								print "<option value='" . $template["id"] . "'"; if (get_request_var_request("graph_template_id") == $template["id"]) { print " selected"; } print ">" . $template["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;<strong>Search:</strong>&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="40" value="<?php print clean_html_output(get_request_var_request("filter"));?>">
					</td>
					<td style='white-space:nowrap;' nowrap>
						&nbsp;<input type="image" src="images/button_go.gif" alt="Go" border="0" align="absmiddle">
						<input type="image" src="images/button_clear.gif" name="clear" alt="Clear" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php

	html_graph_end_box(FALSE);

	/* include time span selector */
	if (read_graph_config_option("timespan_sel") == "on") {
		html_graph_start_box(3, FALSE);

		?>

			<script type='text/javascript'>
			// Initialize the calendar
			calendar=null;

			// This function displays the calendar associated to the input field 'id'
			function showCalendar(id) {
				var el = document.getElementById(id);
				if (calendar != null) {
					// we already have some calendar created
					calendar.hide();  // so we hide it first.
				} else {
					// first-time call, create the calendar.
					var cal = new Calendar(true, null, selected, closeHandler);
					cal.weekNumbers = false;  // Do not display the week number
					cal.showsTime = true;     // Display the time
					cal.time24 = true;        // Hours have a 24 hours format
					cal.showsOtherMonths = false;    // Just the current month is displayed
					calendar = cal;                  // remember it in the global var
					cal.setRange(1900, 2070);        // min/max year allowed.
					cal.create();
				}

				calendar.setDateFormat('%Y-%m-%d %H:%M');    // set the specified date format
				calendar.parseDate(el.value);                // try to parse the text in field
				calendar.sel = el;                           // inform it what input field we use

				// Display the calendar below the input field
				calendar.showAtElement(el, "Br");        // show the calendar

				return false;
			}

			// This function update the date in the input field when selected
			function selected(cal, date) {
				cal.sel.value = date;      // just update the date in the input field.
			}

			// This function gets called when the end-user clicks on the 'Close' button.
			// It just hides the calendar without destroying it.
			function closeHandler(cal) {
				cal.hide();                        // hide the calendar
				calendar = null;
			}
		</script>
		<script type="text/javascript">
		<!--

			function applyTimespanFilterChange(objForm) {
				strURL = '?predefined_timespan=' + objForm.predefined_timespan.value;
				strURL = strURL + '&predefined_timeshift=' + objForm.predefined_timeshift.value;
				document.location = strURL;
			}

		-->
		</script>
			<tr bgcolor="<?php print $colors["panel"];?>" class="noprint">
				<form name="form_timespan_selector" method="post">
				<td class="noprint">
					<table width="100%" cellpadding="0" cellspacing="0">
						<tr>
							<td nowrap style='white-space: nowrap;' width='55'>
								&nbsp;<strong>Presets:</strong>&nbsp;
							</td>
							<td nowrap style='white-space: nowrap;' width='130'>
								<select name='predefined_timespan' onChange="applyTimespanFilterChange(document.form_timespan_selector)">
									<?php
									if ($_SESSION["custom"]) {
										$graph_timespans[GT_CUSTOM] = "Custom";
										$start_val = 0;
										$end_val = sizeof($graph_timespans);
									} else {
										if (isset($graph_timespans[GT_CUSTOM])) {
											asort($graph_timespans);
											array_shift($graph_timespans);
										}
										$start_val = 1;
										$end_val = sizeof($graph_timespans)+1;
									}

									if (sizeof($graph_timespans) > 0) {
										for ($value=$start_val; $value < $end_val; $value++) {
											print "<option value='$value'"; if ($_SESSION["sess_current_timespan"] == $value) { print " selected"; } print ">" . title_trim($graph_timespans[$value], 40) . "</option>\n";
										}
									}
									?>
								</select>
							</td>
							<td nowrap style='white-space: nowrap;' width='30'>
								&nbsp;<strong>From:</strong>&nbsp;
							</td>
							<td width='150' nowrap style='white-space: nowrap;'>
								<input type='text' name='date1' id='date1' title='Graph Begin Timestamp' size='14' value='<?php print (isset($_SESSION["sess_current_date1"]) ? $_SESSION["sess_current_date1"] : "");?>'>
								&nbsp;<input style='padding-bottom: 4px;' type='image' src='images/calendar.gif' alt='Start date selector' title='Start date selector' border='0' align='absmiddle' onclick="return showCalendar('date1');">&nbsp;
							</td>
							<td nowrap style='white-space: nowrap;' width='20'>
								&nbsp;<strong>To:</strong>&nbsp;
							</td>
							<td width='150' nowrap style='white-space: nowrap;'>
								<input type='text' name='date2' id='date2' title='Graph End Timestamp' size='14' value='<?php print (isset($_SESSION["sess_current_date2"]) ? $_SESSION["sess_current_date2"] : "");?>'>
								&nbsp;<input style='padding-bottom: 4px;' type='image' src='images/calendar.gif' alt='End date selector' title='End date selector' border='0' align='absmiddle' onclick="return showCalendar('date2');">
							</td>
							<td width='130' nowrap style='white-space: nowrap;'>
								&nbsp;&nbsp;<input style='padding-bottom: 4px;' type='image' name='move_left' src='images/move_left.gif' alt='Left' border='0' align='absmiddle' title='Shift Left'>
								<select name='predefined_timeshift' title='Define Shifting Interval' onChange="applyTimespanFilterChange(document.form_timespan_selector)">
									<?php
									$start_val = 1;
									$end_val = sizeof($graph_timeshifts)+1;
									if (sizeof($graph_timeshifts) > 0) {
										for ($shift_value=$start_val; $shift_value < $end_val; $shift_value++) {
											print "<option value='$shift_value'"; if ($_SESSION["sess_current_timeshift"] == $shift_value) { print " selected"; } print ">" . title_trim($graph_timeshifts[$shift_value], 40) . "</option>\n";
										}
									}
									?>
								</select>
								<input style='padding-bottom: 4px;' type='image' name='move_right' src='images/move_right.gif' alt='Right' border='0' align='absmiddle' title='Shift Right'>
							</td>
							<td nowrap style='white-space: nowrap;'>
								&nbsp;&nbsp;<input type='image' name='button_refresh' src='images/button_refresh.gif' alt='Refresh selected time span' border='0' align='absmiddle' value='refresh'>
								<input type='image' name='button_clear' src='images/button_clear.gif' alt='Return to the default time span' border='0' align='absmiddle'>
							</td>
						</tr>
					</table>
				</td>
				</form>
			</tr>
		<?php

		html_graph_end_box();
	}

	/* do some fancy navigation url construction so we don't have to try and rebuild the url string */
	if (ereg("page=[0-9]+",basename($_SERVER["QUERY_STRING"]))) {
		$nav_url = str_replace("page=" . get_request_var_request("page"), "page=<PAGE>", basename($_SERVER["PHP_SELF"]) . "?" . $_SERVER["QUERY_STRING"]);
	}else{
		$nav_url = basename($_SERVER["PHP_SELF"]) . "?" . $_SERVER["QUERY_STRING"] . "&page=<PAGE>&host_id=" . get_request_var_request("host_id");
	}

	$nav_url = ereg_replace("((\?|&)host_id=[0-9]+|(\?|&)filter=[a-zA-Z0-9]*)", "", $nav_url);

	html_graph_start_box(1, true);
	html_nav_bar($colors["header_panel"], read_graph_config_option("num_columns"), get_request_var_request("page"), ROWS_PER_PAGE, $total_rows, $nav_url);

	if (read_graph_config_option("thumbnail_section_preview") == "on") {
		html_graph_thumbnail_area($graphs, "","graph_start=" . get_current_graph_start() . "&graph_end=" . get_current_graph_end());
	}else{
		html_graph_area($graphs, "", "graph_start=" . get_current_graph_start() . "&graph_end=" . get_current_graph_end());
	}

	html_nav_bar($colors["header_panel"], read_graph_config_option("num_columns"), get_request_var_request("page"), ROWS_PER_PAGE, $total_rows, $nav_url);

	html_graph_end_box();

	break;
case 'list':
	define("ROWS_PER_PAGE", read_graph_config_option("list_graphs_per_page"));

	if ((read_config_option("auth_method") != 0) && (empty($current_user["show_list"]))) {
		print "<strong><font size='+1' color='FF0000'>YOU DO NOT HAVE RIGHTS FOR LIST VIEW</font></strong>"; exit;
	}

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("host_id"));
	input_validate_input_number(get_request_var_request("graph_template_id"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* reset the page counter to '1' if a search in initiated */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["page"] = "1";
	}

	load_current_session_value("host_id", "sess_graph_view_list_host", "0");
	load_current_session_value("graph_template_id", "sess_graph_view_list_graph_template", "0");
	load_current_session_value("filter", "sess_graph_view_list_filter", "");
	load_current_session_value("page", "sess_graph_view_list_current_page", "");

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_graph_view_list_current_page");
		kill_session_var("sess_graph_view_list_filter");
		kill_session_var("sess_graph_view_list_host");
		kill_session_var("sess_graph_view_list_graph_template");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["host_id"]);
		unset($_REQUEST["graph_template_id"]);
		unset($_REQUEST["graph_list"]);
		unset($_REQUEST["graph_add"]);
		unset($_REQUEST["graph_remove"]);

	}

	/* make sure we have a page set */
	if (! isset($_REQUEST["page"])) {
		$_REQUEST["page"] = 1;
	}
	if (($_REQUEST["page"] == 0 ) || (empty($_REQUEST["page"]))) {
		$_REQUEST["page"] = 1;
	}

	/* save selected graphs into url */
	if (! empty($_REQUEST["graph_list"])) {
		foreach (explode(",",$_REQUEST["graph_list"]) as $item) {
			$graph_list[$item] = 1;
		}
	}else{
		$graph_list = array();
	}
	if (! empty($_REQUEST["graph_add"])) {
		foreach (explode(",",$_REQUEST["graph_add"]) as $item) {
			$graph_list[$item] = 1;
		}
	}
	/* remove items */
	if (! empty($_REQUEST["graph_remove"])) {
		foreach (explode(",",$_REQUEST["graph_remove"]) as $item) {
			unset($graph_list[$item]);
		}
	}

	/* do some fancy navigation url construction so we don't have to try and rebuild the url string */
	if (ereg("page=[0-9]+",basename($_SERVER["QUERY_STRING"]))) {
		$nav_url = str_replace("page=" . get_request_var_request("page"), "page=<PAGE>", basename($_SERVER["PHP_SELF"]) . "?" . $_SERVER["QUERY_STRING"]);
	}else{
		#$nav_url = basename($_SERVER["PHP_SELF"]) . "?" . $_SERVER["QUERY_STRING"] . "&page=<PAGE>&host_id=" . get_request_var_request("host_id");
		$nav_url = basename($_SERVER["PHP_SELF"]) . "?" . $_SERVER["QUERY_STRING"] . "&page=<PAGE>";
	}

	$nav_url = ereg_replace("(\?|&)host_id=[0-9]+|(\?|&)graph_template_id=[0-9]+|(\?|&)filter=[a-zA-Z0-9]*|(\?|&)graph_add=[\,0-9]*|(\?|&)graph_remove=[\,0-9]*|(\?|&)graph_list=[\,0-9]*", "", $nav_url);
	$graph_list_text = "";
	if (! empty($graph_list)) {
		foreach ($graph_list as $item => $value) {
			$graph_list_text .= $item . ",";
		}
		if (substr($graph_list_text,strlen($graph_list_text) - 1, 1) == ",") {
			$graph_list_text = substr($graph_list_text,0,strlen($graph_list_text) - 1);
		}
		$nav_url .= "&graph_list=" . $graph_list_text;
	}

	/* display graph view filter selector */
	html_graph_start_box(3, FALSE);

	if (empty($_REQUEST["host_id"])) { $_REQUEST["host_id"] = 0; }
	if (empty($_REQUEST["graph_template_id"])) { $_REQUEST["graph_template_id"] = 0; }
	if (empty($_REQUEST["filter"])) { $_REQUEST["filter"] = ""; }
	?>
	<script type="text/javascript">
	<!--
	function applyGraphListFilterChange(objForm) {
		strURL = 'graph_view.php?action=list&page=1';
		strURL = strURL + '&host_id=' + objForm.host_id.value;
		strURL = strURL + '&graph_template_id=' + objForm.graph_template_id.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		strURL = strURL + url_graph('');
		document.location = strURL;
		return false;
	}
	-->
	</script>

	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_graph_list" method="POST" onSubmit='form_graph(document.graphs,document.form_graph_list)'>
		<input type='hidden' name='graph_list' value='<?php print $graph_list_text; ?>'>
		<input type='hidden' name='graph_add' value=''>
		<input type='hidden' name='graph_remove' value=''>
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="40">
						&nbsp;<strong>Host:</strong>&nbsp;
					</td>
					<td width="1">
						<select name="host_id" onChange="applyGraphListFilterChange(document.form_graph_list)">
							<option value="0"<?php if (get_request_var_request("host_id") == "0") {?> selected<?php }?>>Any</option>
							<?php
							if (read_config_option("auth_method") != 0) {
								/* get policy information for the sql where clause */
								$sql_where = get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);

								$hosts = db_fetch_assoc("SELECT DISTINCT host.id, host.description as name
									FROM (graph_templates_graph,graph_local)
									LEFT JOIN host ON (host.id=graph_local.host_id)
									LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
									LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
									WHERE graph_templates_graph.local_graph_id=graph_local.id
									" . (empty($sql_where) ? "" : "and $sql_where") . "
									ORDER BY name");
							}else{
								$hosts = db_fetch_assoc("SELECT DISTINCT host.id, host.description as name
									FROM host
									ORDER BY name");
							}

							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='" . $host["id"] . "'"; if (get_request_var_request("host_id") == $host["id"]) { print " selected"; } print ">" . $host["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="70">
						&nbsp;<strong>Template:</strong>&nbsp;
					</td>
					<td width="1">
						<select name="graph_template_id" onChange="applyGraphListFilterChange(document.form_graph_list)">
							<option value="0"<?php print clean_html_output(get_request_var_request("filter"));?><?php if (get_request_var_request("host_id") == "0") {?> selected<?php }?>>Any</option>
							<?php
							if (read_config_option("auth_method") != 0) {
								$graph_templates = db_fetch_assoc("SELECT DISTINCT graph_templates.*
									FROM (graph_templates_graph,graph_local)
									LEFT JOIN host ON (host.id=graph_local.host_id)
									LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
									LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
									WHERE graph_templates_graph.local_graph_id=graph_local.id
									" . (empty($sql_where) ? "" : "and $sql_where") . "
									ORDER BY name");
							}else{
								$graph_templates = db_fetch_assoc("SELECT DISTINCT graph_templates.*
									FROM graph_templates
									ORDER BY name");
							}

							if (sizeof($graph_templates) > 0) {
							foreach ($graph_templates as $template) {
								print "<option value='" . $template["id"] . "'"; if (get_request_var_request("graph_template_id") == $template["id"]) { print " selected"; } print ">" . $template["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;<strong>Search:</strong>&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="40" value="<?php print clean_html_output(get_request_var_request("filter"));?>">
					</td>
					<td style='white-space:nowrap;' nowrap>
						&nbsp;<input type="image" src="images/button_go.gif" alt="Go" border="0" align="absmiddle">
						<input type="image" src="images/button_clear.gif" name="clear" alt="Clear" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php
	html_graph_end_box(TRUE);

	/* create filter for sql */
	$sql_filter = "";
	$sql_filter .= (empty($_REQUEST["filter"]) ? "" : " graph_templates_graph.title_cache like '%" . get_request_var_request("filter") . "%'");
	$sql_filter .= (empty($_REQUEST["host_id"]) ? "" : (empty($sql_filter) ? "" : " and") . " graph_local.host_id=" . get_request_var_request("host_id"));
	$sql_filter .= (empty($_REQUEST["graph_template_id"]) ? "" : (empty($sql_filter) ? "" : " and") . " graph_local.graph_template_id=" . get_request_var_request("graph_template_id"));

	/* graph permissions */
	if (read_config_option("auth_method") != 0) {
		/* get policy information for the sql where clause */
		$sql_where = "where " . get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);
		$sql_join = "left join host on (host.id=graph_local.host_id)
			left join graph_templates on (graph_templates.id=graph_local.graph_template_id)
			left join user_auth_perms on ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))";

	}else{
		$sql_where = "";
		$sql_join = "";
	}

	$sql_base = "from (graph_templates_graph,graph_local)
		$sql_join
		$sql_where
		" . (empty($sql_where) ? "where" : "and") . " graph_templates_graph.local_graph_id > 0
		and graph_templates_graph.local_graph_id=graph_local.id
		and graph_templates_graph.title_cache like '%" . $_REQUEST["filter"] . "%'
		" . (empty($_REQUEST["host_id"]) ? "" : " and graph_local.host_id=" . $_REQUEST["host_id"]) . "
		" . (empty($_REQUEST["graph_template_id"]) ? "" : " and graph_local.graph_template_id=" . $_REQUEST["graph_template_id"]);

	$total_rows = count(db_fetch_assoc("select
		graph_templates_graph.local_graph_id
		$sql_base"));
	$graphs = db_fetch_assoc("select
		graph_templates_graph.local_graph_id,
		graph_templates_graph.title_cache,
		graph_templates_graph.height,
		graph_templates_graph.width
		$sql_base
		group by graph_templates_graph.local_graph_id
		order by graph_templates_graph.title_cache
		limit " . (ROWS_PER_PAGE*($_REQUEST["page"]-1)) . "," . ROWS_PER_PAGE);
	?>

	<script type='text/javascript'>
	<!--
	function url_graph(strNavURL) {
		var strURL = '';
		var strAdd = '';
		var strDel = '';
		for(var i = 0; i < document.graphs.elements.length; i++) {
			if (document.graphs.elements[i].name.substring(0,5) == 'graph') {
				if (document.graphs.elements[i].name != 'graph_list') {
					if (document.graphs.elements[i].checked) {
						strAdd = strAdd + document.graphs.elements[i].value + ',';
					} else {
						if (document.graphs.elements[i].value != '') {
							strDel = strDel + document.graphs.elements[i].value + ',';
						}
					}
				}
			}
		}
		strAdd = strAdd.substring(0,strAdd.length - 1);
		strDel = strDel.substring(0,strDel.length - 1);
		strURL = '&graph_add=' + strAdd + '&graph_remove=' + strDel;
		return strNavURL + strURL;
	}
	function url_go(strURL) {
		document.location = strURL;
		return false;
	}
	function form_graph(objForm,objFormSubmit) {
		var strAdd = '';
		var strDel = '';
		for(var i = 0; i < objForm.elements.length; i++) {
			if (objForm.elements[i].name.substring(0,5) == 'graph') {
				if (objForm.elements[i].name != 'graph_list') {
					if (objForm.elements[i].checked) {
						strAdd = strAdd + objForm.elements[i].value + ',';
					} else {
						if (objForm.elements[i].value != '') {
							strDel = strDel + objForm.elements[i].value + ',';
						}
					}
				}
			}
		}
		strAdd = strAdd.substring(0,strAdd.length - 1);
		strDel = strDel.substring(0,strDel.length - 1);
		objFormSubmit.graph_add.value = strAdd;
		objFormSubmit.graph_remove.value = strDel;
	}
	-->
	</script>
	<form name='graphs' id='graphs' action='graph_view.php' method='get' onSubmit='form_graph(document.graphs,document.graphs)'>

	<?php

	html_graph_start_box(1, TRUE);
	?>
	<tr bgcolor='#<?php print $colors["header_panel"];?>'>
		<td colspan='3'>
			<table width='100%' cellspacing='0' cellpadding='3' border='0'>
				<tr>
					<td align='left' class='textHeaderDark'>
						<strong>&lt;&lt; <?php if (get_request_var_request("page") > 1) { print "<a class='linkOverDark' href='" . str_replace("<PAGE>", (get_request_var_request("page")-1), $nav_url) . "' onClick='return url_go(url_graph(\"" . str_replace("<PAGE>", (get_request_var_request("page")-1), $nav_url) . "\"))'>"; } print "Previous"; if (get_request_var_request("page") > 1) { print "</a>"; } ?></strong>
					</td>
					<td align='center' class='textHeaderDark'>
						Showing Rows <?php print ((ROWS_PER_PAGE*(get_request_var_request("page")-1))+1);?> to <?php print ((($total_rows < ROWS_PER_PAGE) || ($total_rows < (ROWS_PER_PAGE*get_request_var_request("page")))) ? $total_rows : (ROWS_PER_PAGE*get_request_var_request("page")));?> of <?php print $total_rows;?>
					</td>
					<td align='right' class='textHeaderDark'>
						<strong><?php if ((get_request_var_request("page") * ROWS_PER_PAGE) < $total_rows) { print "<a class='linkOverDark' href='" . str_replace("<PAGE>", (get_request_var_request("page")+1), $nav_url) . "' onClick='return url_go(url_graph(\"" . str_replace("<PAGE>", (get_request_var_request("page")+1), $nav_url) . "\"))'>"; } print "Next"; if ((get_request_var_request("page") * ROWS_PER_PAGE) < $total_rows) { print "</a>"; } ?> &gt;&gt;</strong>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr bgcolor='#6d88ad'>
		<td colspan='3'>
			<table width='100%' cellspacing='0' cellpadding='3' border='0'>
				<tr>
					<?php
					print "<td width='1%' align='right' class='textHeaderDark' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAllGraphs(\"graph_\",this.checked)'></td><td bgcolor='#6D88AD'><strong>Select All</strong></td>\n";
					?>
				</tr>
			</table>
		</td>
	</tr>
	<?php

	$i = 0;
	if (sizeof($graphs) > 0) {
		foreach ($graphs as $graph) {
			form_alternate_row_color("f5f5f5", "ffffff", $i);

			print "<td width='1%'>";
			print "<input type='checkbox' name='graph_" . $graph["local_graph_id"] . "' id='graph_" . $graph["local_graph_id"] . "' value='" . $graph["local_graph_id"] . "'";
			if (isset($graph_list[$graph["local_graph_id"]])) {
				print " checked";
			}
			print ">\n";
			print "</td>\n";

			print "<td><strong><a href='graph.php?local_graph_id=" . $graph["local_graph_id"] . "&rra_id=all'>" . $graph["title_cache"] . "</a></strong></td>\n";
			print "<td>" . $graph["height"] . "x" . $graph["width"] . "</td>\n";
			print "</tr>";

			$i++;
		}
	}

	?>
	<tr bgcolor='#6d88ad'>
		<td colspan='3'>
			<table width='100%' cellspacing='0' cellpadding='3' border='0'>
				<tr>
					<?php
					print "<td width='1%' align='right' class='textHeaderDark' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAllGraphs(\"graph_\",this.checked)'></td><td bgcolor='#6D88AD'><strong>Select All</strong></td>\n";
					?>
				</tr>
			</table>
		</td>
	</tr>
	</table>
	<table align='center' width='100%'>
		<tr>
			<td width='1'><img src='images/arrow.gif' alt='' align='absmiddle'>&nbsp;</td>
			<td><input type='image' src='images/button_view.gif' alt='View'></td>
		</tr>
	</table>
	<input type='hidden' name='page' value='1'>
	<input type='hidden' name='style' value='selective'>
	<input type='hidden' name='action' value='preview'>
	<input type='hidden' name='graph_list' value='<?php print $graph_list_text; ?>'>
	<input type='hidden' name='graph_add' value=''>
	<input type='hidden' name='graph_remove' value=''>
	</form><?php

	break;
}

include_once("./include/bottom_footer.php");

?>

