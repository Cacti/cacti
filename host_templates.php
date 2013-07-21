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

define("MAX_DISPLAY_PAGES", 21);

$host_actions = array(
	1 => "Delete",
	2 => "Duplicate"
	);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'item_remove_gt':
		template_item_remove_gt();

		header("Location: host_templates.php?action=edit&id=" . $_GET["host_template_id"]);
		break;
	case 'item_remove_dq':
		template_item_remove_dq();

		header("Location: host_templates.php?action=edit&id=" . $_GET["host_template_id"]);
		break;
	case 'edit':
		include_once("./include/top_header.php");

		template_edit();

		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");

		template();

		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post("host_template_id"));
	input_validate_input_number(get_request_var_post("snmp_query_id"));
	input_validate_input_number(get_request_var_post("graph_template_id"));
	/* ==================================================== */

	if (isset($_POST["save_component_template"])) {
		$redirect_back = false;

		$save["id"] = $_POST["id"];
		$save["hash"] = get_hash_host_template($_POST["id"]);
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);

		if (!is_error_message()) {
			$host_template_id = sql_save($save, "host_template");

			if ($host_template_id) {
				raise_message(1);

				if (isset($_POST["add_gt_x"])) {
					db_execute("replace into host_template_graph (host_template_id,graph_template_id) values($host_template_id," . $_POST["graph_template_id"] . ")");
					$redirect_back = true;
				}elseif (isset($_POST["add_dq_x"])) {
					db_execute("replace into host_template_snmp_query (host_template_id,snmp_query_id) values($host_template_id," . $_POST["snmp_query_id"] . ")");
					$redirect_back = true;
				}
			}else{
				raise_message(2);
			}
		}

		header("Location: host_templates.php?action=edit&id=" . (empty($host_template_id) ? $_POST["id"] : $host_template_id));
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $host_actions;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* delete */
			db_execute("delete from host_template where " . array_to_sql_or($selected_items, "id"));
			db_execute("delete from host_template_snmp_query where " . array_to_sql_or($selected_items, "host_template_id"));
			db_execute("delete from host_template_graph where " . array_to_sql_or($selected_items, "host_template_id"));

			/* "undo" any device that is currently using this template */
			db_execute("update host set host_template_id=0 where " . array_to_sql_or($selected_items, "host_template_id"));
		}elseif ($_POST["drp_action"] == "2") { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				duplicate_host_template($selected_items[$i], $_POST["title_format"]);
			}
		}

		header("Location: host_templates.php");
		exit;
	}

	/* setup some variables */
	$host_list = ""; $i = 0;

	/* loop through each of the host templates selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$host_list .= "<li>" . db_fetch_cell("select name from host_template where id=" . $matches[1]) . "<br>";
			$host_array[$i] = $matches[1];

			$i++;
		}
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $host_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='host_templates.php' autocomplete='off' method='post'>\n";

	if (isset($host_array) && sizeof($host_array)) {
		if ($_POST["drp_action"] == "1") { /* delete */
			print "	<tr>
					<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>Are you sure you want to delete the following Host Template(s)? All Devices currently associated
						with these Host Template(s) will lose that assocation.</p>
						<p><ul>$host_list</ul></p>
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Host Template(s)'>";
		}elseif ($_POST["drp_action"] == "2") { /* duplicate */
			print "	<tr>
					<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click \"Continue\", the following Host Template(s) will be duplicated. You can
						optionally change the title format for the new Host Template(s).</p>
						<p><ul>$host_list</ul></p>
						<p><strong>Title Format:</strong><br>"; form_text_box("title_format", "<template_title> (1)", "", "255", "30", "text"); print "</p>
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Duplicate Host Template(s)'>";
		}
	}else{
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one host template.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($host_array) ? serialize($host_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

/* ---------------------
    Template Functions
   --------------------- */

function template_item_remove_gt() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("host_template_id"));
	/* ==================================================== */

	db_execute("delete from host_template_graph where graph_template_id=" . $_GET["id"] . " and host_template_id=" . $_GET["host_template_id"]);
}

function template_item_remove_dq() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("host_template_id"));
	/* ==================================================== */

	db_execute("delete from host_template_snmp_query where snmp_query_id=" . $_GET["id"] . " and host_template_id=" . $_GET["host_template_id"]);
}

function template_edit() {
	global $colors, $fields_host_template_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if (!empty($_GET["id"])) {
		$host_template = db_fetch_row("select * from host_template where id=" . $_GET["id"]);
		$header_label = "[edit: " . $host_template["name"] . "]";
	}else{
		$header_label = "[new]";
		$_GET["id"] = 0;
	}

	html_start_box("<strong>Host Templates</strong> " . htmlspecialchars($header_label), "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_host_template_edit, (isset($host_template) ? $host_template : array()))
		));

	/* we have to hide this button to make a form change in the main form trigger the correct
	 * submit action */
	echo "<div style='display:none;'><input type='submit' value='Default Submit Button'></div>";

	html_end_box();

	if (!empty($_GET["id"])) {
		html_start_box("<strong>Associated Graph Templates</strong>", "100%", $colors["header"], "3", "center", "");

		$selected_graph_templates = db_fetch_assoc("select
			graph_templates.id,
			graph_templates.name
			from (graph_templates,host_template_graph)
			where graph_templates.id=host_template_graph.graph_template_id
			and host_template_graph.host_template_id=" . $_GET["id"] . "
			order by graph_templates.name");

		$i = 0;
		if (sizeof($selected_graph_templates) > 0) {
		foreach ($selected_graph_templates as $item) {
			$i++;
			?>
			<tr>
				<td style="padding: 4px;">
					<strong><?php print $i;?>)</strong> <?php print htmlspecialchars($item["name"]);?>
				</td>
				<td align="right">
					<a href='<?php print htmlspecialchars("host_templates.php?action=item_remove_gt&id=" . $item["id"] . "&host_template_id=" . $_GET["id"]);?>'><img src='images/delete_icon.gif' style="height:10px;width:10px;" border='0' alt='Delete'></a>
				</td>
			</tr>
			<?php
		}
		}else{ print "<tr><td><em>No associated graph templates.</em></td></tr>"; }

		?>
		<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
			<td colspan="2">
				<table cellspacing="0" cellpadding="1" width="100%">
					<td nowrap>Add Graph Template:&nbsp;
						<?php form_dropdown("graph_template_id",db_fetch_assoc("select
							graph_templates.id,
							graph_templates.name
							from graph_templates left join host_template_graph
							on (graph_templates.id=host_template_graph.graph_template_id and host_template_graph.host_template_id=" . $_GET["id"] . ")
							where host_template_graph.host_template_id is null
							order by graph_templates.name"),"name","id","","","");?>
					</td>
					<td align="right">
						&nbsp;<input type="submit" value="Add" name="add_gt_x" title="Add Graph Template to Host Template">
					</td>
				</table>
			</td>
		</tr>

		<?php
		html_end_box();

		html_start_box("<strong>Associated Data Queries</strong>", "100%", $colors["header"], "3", "center", "");

		$selected_data_queries = db_fetch_assoc("select
			snmp_query.id,
			snmp_query.name
			from (snmp_query,host_template_snmp_query)
			where snmp_query.id=host_template_snmp_query.snmp_query_id
			and host_template_snmp_query.host_template_id=" . $_GET["id"] . "
			order by snmp_query.name");

		$i = 0;
		if (sizeof($selected_data_queries) > 0) {
		foreach ($selected_data_queries as $item) {
			$i++;
			?>
			<tr>
				<td style="padding: 4px;">
					<strong><?php print $i;?>)</strong> <?php print htmlspecialchars($item["name"]);?>
				</td>
				<td align='right'>
					<a href='<?php print htmlspecialchars("host_templates.php?action=item_remove_dq&id=" . $item["id"] . "&host_template_id=" . $_GET["id"]);?>'><img src='images/delete_icon.gif' style="height:10px;width:10px;" border='0' alt='Delete'></a>
				</td>
			</tr>
			<?php
		}
		}else{ print "<tr><td><em>No associated data queries.</em></td></tr>"; }

		?>
		<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
			<td colspan="2">
				<table cellspacing="0" cellpadding="1" width="100%">
					<td nowrap>Add Data Query:&nbsp;
						<?php form_dropdown("snmp_query_id",db_fetch_assoc("select
							snmp_query.id,
							snmp_query.name
							from snmp_query left join host_template_snmp_query
							on (snmp_query.id=host_template_snmp_query.snmp_query_id and host_template_snmp_query.host_template_id=" . $_GET["id"] . ")
							where host_template_snmp_query.host_template_id is null
							order by snmp_query.name"),"name","id","","","");?>
					</td>
					<td align="right">
						&nbsp;<input type="submit" value="Add" name="add_dq_x" title="Add Data Query to Host Template">
					</td>
				</table>
			</td>
		</tr>

		<?php
		html_end_box();
	}

	form_save_button("host_templates.php", "return");
}

function template() {
	global $colors, $host_actions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_host_template_current_page");
		kill_session_var("sess_host_template_filter");
		kill_session_var("sess_host_template_sort_column");
		kill_session_var("sess_host_template_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_host_template_current_page", "1");
	load_current_session_value("filter", "sess_host_template_filter", "");
	load_current_session_value("sort_column", "sess_host_template_sort_column", "name");
	load_current_session_value("sort_direction", "sess_host_template_sort_direction", "ASC");

	display_output_messages();

	html_start_box("<strong>Host Templates</strong>", "100%", $colors["header"], "3", "center", "host_templates.php?action=edit");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
		<form name="form_graph_template" action="host_templates.php">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="40" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input type="submit" value="Go" title="Set/Refresh Filters">
						<input type="submit" name="clear_x" value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
		<input type='hidden' name='page' value='1'>
		</form>
		</td>
	</tr>
	<?php


	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = "WHERE (host_template.name LIKE '%%" . get_request_var_request("filter") . "%%')";

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='host_templates.php'>\n";

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT
		COUNT(host_template.id)
		FROM host_template
		$sql_where");

	$template_list = db_fetch_assoc("SELECT
		host_template.id,host_template.name
		FROM host_template
		$sql_where
		ORDER BY " . get_request_var_request("sort_column") . " " . get_request_var_request("sort_direction") .
		" LIMIT " . (read_config_option("num_rows_device")*(get_request_var_request("page")-1)) . "," . read_config_option("num_rows_device"));

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, read_config_option("num_rows_device"), $total_rows, "host_templates.php?filter=" . get_request_var_request("filter"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
		<td colspan='7'>
			<table width='100%' cellspacing='0' cellpadding='0' border='0'>
				<tr>
					<td align='left' class='textHeaderDark'>
						<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("host_templates.php?filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
					</td>\n
					<td align='center' class='textHeaderDark'>
						Showing Rows " . ((read_config_option("num_rows_device")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (read_config_option("num_rows_device")*get_request_var_request("page")))) ? $total_rows : (read_config_option("num_rows_device")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
					</td>\n
					<td align='right' class='textHeaderDark'>
						<strong>"; if ((get_request_var_request("page") * read_config_option("num_rows_device")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("host_templates.php?filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * read_config_option("num_rows_device")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
					</td>\n
				</tr>
			</table>
		</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"name" => array("Template Title", "ASC"));

	html_header_sort_checkbox($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), false);

	$i = 0;
	if (sizeof($template_list) > 0) {
		foreach ($template_list as $template) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $template["id"]);$i++;
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("host_templates.php?action=edit&id=" . $template["id"]) . "'>" . (strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($template["name"])) : htmlspecialchars($template["name"])) . "</a>", $template["id"]);
			form_checkbox_cell($template["name"], $template["id"]);
			form_end_row();
		}
		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Host Templates</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($host_actions);

	print "</form>\n";
}
?>

