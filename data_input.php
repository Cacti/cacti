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

$di_actions = array(
	1 => "Delete"
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
	case 'field_remove':
		field_remove();

		header("Location: data_input.php?action=edit&id=" . $_GET["data_input_id"]);
		break;
	case 'field_edit':
		include_once("./include/top_header.php");

		field_edit();

		include_once("./include/bottom_footer.php");
		break;
	case 'edit':
		include_once("./include/top_header.php");

		data_edit();

		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");

		data();

		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $registered_cacti_names;

	if (isset($_POST["save_component_data_input"])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		/* ==================================================== */

		$save["id"] = $_POST["id"];
		$save["hash"] = get_hash_data_input($_POST["id"]);
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save["input_string"] = form_input_validate($_POST["input_string"], "input_string", "", true, 3);
		$save["type_id"] = form_input_validate($_POST["type_id"], "type_id", "", true, 3);

		if (!is_error_message()) {
			$data_input_id = sql_save($save, "data_input");

			if ($data_input_id) {
				raise_message(1);

				/* get a list of each field so we can note their sequence of occurance in the database */
				if (!empty($_POST["id"])) {
					db_execute("update data_input_fields set sequence=0 where data_input_id=" . $_POST["id"]);

					generate_data_input_field_sequences($_POST["input_string"], $_POST["id"]);
				}

				push_out_data_input_method($data_input_id);
			}else{
				raise_message(2);
			}
		}

		header("Location: data_input.php?action=edit&id=" . (empty($data_input_id) ? $_POST["id"] : $data_input_id));
	}elseif (isset($_POST["save_component_field"])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		input_validate_input_number(get_request_var_post("data_input_id"));
		input_validate_input_regex(get_request_var_post("input_output"), "^(in|out)$");
		/* ==================================================== */

		$save["id"] = $_POST["id"];
		$save["hash"] = get_hash_data_input($_POST["id"], "data_input_field");
		$save["data_input_id"] = $_POST["data_input_id"];
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save["data_name"] = form_input_validate($_POST["data_name"], "data_name", "", false, 3);
		$save["input_output"] = $_POST["input_output"];
		$save["update_rra"] = form_input_validate((isset($_POST["update_rra"]) ? $_POST["update_rra"] : ""), "update_rra", "", true, 3);
		$save["sequence"] = $_POST["sequence"];
		$save["type_code"] = form_input_validate((isset($_POST["type_code"]) ? $_POST["type_code"] : ""), "type_code", "", true, 3);
		$save["regexp_match"] = form_input_validate((isset($_POST["regexp_match"]) ? $_POST["regexp_match"] : ""), "regexp_match", "", true, 3);
		$save["allow_nulls"] = form_input_validate((isset($_POST["allow_nulls"]) ? $_POST["allow_nulls"] : ""), "allow_nulls", "", true, 3);

		if (!is_error_message()) {
			$data_input_field_id = sql_save($save, "data_input_fields");

			if ($data_input_field_id) {
				raise_message(1);

				if ((!empty($data_input_field_id)) && ($_POST["input_output"] == "in")) {
					generate_data_input_field_sequences(db_fetch_cell("select input_string from data_input where id=" . $_POST["data_input_id"]), $_POST["data_input_id"]);
				}
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header("Location: data_input.php?action=field_edit&data_input_id=" . $_POST["data_input_id"] . "&id=" . (empty($data_input_field_id) ? $_POST["id"] : $data_input_field_id) . (!empty($_POST["input_output"]) ? "&type=" . $_POST["input_output"] : ""));
		}else{
			header("Location: data_input.php?action=edit&id=" . $_POST["data_input_id"]);
		}
	}
}

function form_actions() {
	global $colors, $di_actions;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* delete */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				data_remove($selected_items[$i]);
			}
		}

		header("Location: data_input.php");
		exit;
	}

	/* setup some variables */
	$di_list = ""; $i = 0;

	/* loop through each of the data queries and process them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$di_list .= "<li>" . db_fetch_cell("SELECT name FROM data_input WHERE id='" . $matches[1] . "'") . "</li>";
			$di_array[$i] = $matches[1];

			$i++;
		}
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $di_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='data_input.php' method='post'>\n";

	if (isset($di_array) && sizeof($di_array)) {
		if ($_POST["drp_action"] == "1") { /* delete */
			$graphs = array();

			print "
				<tr>
					<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click \"Continue\", the following Data Input Method(s) will be deleted.</p>
						<p><ul>$di_list</ul></p>
					</td>
				</tr>\n";
		}

		$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Data Input Method(s)'>";
	}else{
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one data input method.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($di_array) ? serialize($di_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

/* --------------------------
    CDEF Item Functions
   -------------------------- */

function field_remove() {
	global $registered_cacti_names;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("data_input_id"));
	/* ==================================================== */

	if ((read_config_option("deletion_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the field <strong>'" . db_fetch_cell("select name from data_input_fields where id=" . $_GET["id"]) . "'</strong>?", htmlspecialchars("data_input.php?action=edit&id=" . $_GET["data_input_id"]), htmlspecialchars("data_input.php?action=field_remove&id=" . $_GET["id"] . "&data_input_id=" . $_GET["data_input_id"]));
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("deletion_verification") == "") || (isset($_GET["confirm"]))) {
		/* get information about the field we're going to delete so we can re-order the seqs */
		$field = db_fetch_row("select input_output,data_input_id from data_input_fields where id=" . $_GET["id"]);

		db_execute("delete from data_input_fields where id=" . $_GET["id"]);
		db_execute("delete from data_input_data where data_input_field_id=" . $_GET["id"]);

		/* when a field is deleted; we need to re-order the field sequences */
		if (($field["input_output"] == "in") && (preg_match_all("/<([_a-zA-Z0-9]+)>/", db_fetch_cell("select input_string from data_input where id=" . $field["data_input_id"]), $matches))) {
			$j = 0;
			for ($i=0; ($i < count($matches[1])); $i++) {
				if (in_array($matches[1][$i], $registered_cacti_names) == false) {
					$j++; db_execute("update data_input_fields set sequence=$j where data_input_id=" . $field["data_input_id"] . " and input_output='in' and data_name='" . $matches[1][$i] . "'");
				}
			}
		}
	}
}

function field_edit() {
	global $colors, $registered_cacti_names, $fields_data_input_field_edit_1, $fields_data_input_field_edit_2, $fields_data_input_field_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("data_input_id"));
	input_validate_input_regex(get_request_var("type"), "^(in|out)$");
	/* ==================================================== */

	if (!empty($_GET["id"])) {
		$field = db_fetch_row("select * from data_input_fields where id=" . $_GET["id"]);
	}

	if (!empty($_GET["type"])) {
		$current_field_type = $_GET["type"];
	}else{
		$current_field_type = $field["input_output"];
	}

	if ($current_field_type == "out") {
		$header_name = "Output";
	}elseif ($current_field_type == "in") {
		$header_name = "Input";
	}

	$data_input = db_fetch_row("select type_id,name from data_input where id=" . $_GET["data_input_id"]);

	/* obtain a list of available fields for this given field type (input/output) */
	if (($current_field_type == "in") && (preg_match_all("/<([_a-zA-Z0-9]+)>/", db_fetch_cell("select input_string from data_input where id=" . ($_GET["data_input_id"] ? $_GET["data_input_id"] : $field["data_input_id"])), $matches))) {
		for ($i=0; ($i < count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names) == false) {
				$current_field_name = $matches[1][$i];
				$array_field_names[$current_field_name] = $current_field_name;
			}
		}
	}

	/* if there are no input fields to choose from, complain */
	if ((!isset($array_field_names)) && (isset($_GET["type"]) ? $_GET["type"] == "in" : false) && ($data_input["type_id"] == "1")) {
		display_custom_error_message("This script appears to have no input values, therefore there is nothing to add.");
		return;
	}

	html_start_box("<strong>$header_name Fields</strong> [edit: " . htmlspecialchars($data_input["name"]) . "]", "100%", $colors["header"], "3", "center", "");

	$form_array = array();

	/* field name */
	if ((($data_input["type_id"] == "1") || ($data_input["type_id"] == "5")) && ($current_field_type == "in")) { /* script */
		$form_array = inject_form_variables($fields_data_input_field_edit_1, $header_name, $array_field_names, (isset($field) ? $field : array()));
	}elseif (($data_input["type_id"] == "2") ||
			($data_input["type_id"] == "3") ||
			($data_input["type_id"] == "4") ||
			($data_input["type_id"] == "6") ||
			($data_input["type_id"] == "7") ||
			($data_input["type_id"] == "8") ||
			($current_field_type == "out")) { /* snmp */
		$form_array = inject_form_variables($fields_data_input_field_edit_2, $header_name, (isset($field) ? $field : array()));
	}

	/* ONLY if the field is an input */
	if ($current_field_type == "in") {
		unset($fields_data_input_field_edit["update_rra"]);
	}elseif ($current_field_type == "out") {
		unset($fields_data_input_field_edit["regexp_match"]);
		unset($fields_data_input_field_edit["allow_nulls"]);
		unset($fields_data_input_field_edit["type_code"]);
	}

	draw_edit_form(array(
		"config" => array(),
		"fields" => $form_array + inject_form_variables($fields_data_input_field_edit, (isset($field) ? $field : array()), $current_field_type, $_GET)
		));

	html_end_box();

	form_save_button("data_input.php?action=edit&id=" . $_GET["data_input_id"]);
}

/* -----------------------
    Data Input Functions
   ----------------------- */

function data_remove($id) {
	$data_input_fields = db_fetch_assoc("select id from data_input_fields where data_input_id=" . $id);

	if (is_array($data_input_fields)) {
		foreach ($data_input_fields as $data_input_field) {
			db_execute("delete from data_input_data where data_input_field_id=" . $data_input_field["id"]);
		}
	}

	db_execute("delete from data_input where id=" . $id);
	db_execute("delete from data_input_fields where data_input_id=" . $id);
}

function data_edit() {
	global $colors, $fields_data_input_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if (!empty($_GET["id"])) {
		$data_input = db_fetch_row("select * from data_input where id=" . $_GET["id"]);
		$header_label = "[edit: " . htmlspecialchars($data_input["name"]) . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>Data Input Methods</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_data_input_edit, (isset($data_input) ? $data_input : array()))
		));

	html_end_box();

	if (!empty($_GET["id"])) {
		html_start_box("<strong>Input Fields</strong>", "100%", $colors["header"], "3", "center", "data_input.php?action=field_edit&type=in&data_input_id=" . htmlspecialchars(get_request_var("id")));
		print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
			DrawMatrixHeaderItem("Name",$colors["header_text"],1);
			DrawMatrixHeaderItem("Field Order",$colors["header_text"],1);
			DrawMatrixHeaderItem("Friendly Name",$colors["header_text"],2);
		print "</tr>";

		$fields = db_fetch_assoc("select id,data_name,name,sequence from data_input_fields where data_input_id=" . $_GET["id"] . " and input_output='in' order by sequence, data_name");

		$i = 0;
		if (sizeof($fields) > 0) {
		foreach ($fields as $field) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td>
					<a class="linkEditMain" href="<?php print htmlspecialchars("data_input.php?action=field_edit&id=" . $field["id"] . "&data_input_id=" . $_GET["id"]);?>"><?php print htmlspecialchars($field["data_name"]);?></a>
				</td>
				<td>
					<?php print $field["sequence"]; if ($field["sequence"] == "0") { print " (Not In Use)"; }?>
				</td>
				<td>
					<?php print htmlspecialchars($field["name"]);?>
				</td>
				<td align="right">
					<a href="<?php print htmlspecialchars("data_input.php?action=field_remove&id=" . $field["id"] . "&data_input_id=" . $_GET["id"]);?>"><img src="images/delete_icon.gif" style="height:10px;width:10px;" border="0" alt="Delete"></a>
				</td>
			</tr>
		<?php
		}
		}else{
			print "<tr><td><em>No Input Fields</em></td></tr>";
		}
		html_end_box();

		html_start_box("<strong>Output Fields</strong>", "100%", $colors["header"], "3", "center", "data_input.php?action=field_edit&type=out&data_input_id=" . $_GET["id"]);
		print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
			DrawMatrixHeaderItem("Name",$colors["header_text"],1);
			DrawMatrixHeaderItem("Field Order",$colors["header_text"],1);
			DrawMatrixHeaderItem("Friendly Name",$colors["header_text"],1);
			DrawMatrixHeaderItem("Update RRA",$colors["header_text"],2);
		print "</tr>";

		$fields = db_fetch_assoc("select id,name,data_name,update_rra,sequence from data_input_fields where data_input_id=" . $_GET["id"] . " and input_output='out' order by sequence, data_name");

		$i = 0;
		if (sizeof($fields) > 0) {
		foreach ($fields as $field) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td>
					<a class="linkEditMain" href="<?php print htmlspecialchars("data_input.php?action=field_edit&id=" . $field["id"] . "&data_input_id=" . $_GET["id"]);?>"><?php print htmlspecialchars($field["data_name"]);?></a>
				</td>
				<td>
					<?php print $field["sequence"]; if ($field["sequence"] == "0") { print " (Not In Use)"; }?>
				</td>
				<td>
					<?php print htmlspecialchars($field["name"]);?>
				</td>
				<td>
					<?php print html_boolean_friendly($field["update_rra"]);?>
				</td>
				<td align="right">
					<a href="<?php print htmlspecialchars("data_input.php?action=field_remove&id=" . $field["id"] . "&data_input_id=" . $_GET["id"]);?>"><img src="images/delete_icon.gif" style="height:10px;width:10px;" border="0" alt="Delete"></a>
				</td>
			</tr>
		<?php
		}
		}else{
			print "<tr><td><em>No Output Fields</em></td></tr>";
		}
		html_end_box();
	}

	form_save_button("data_input.php", "return");
}

function data() {
	global $colors, $input_types, $di_actions;

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

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_data_input_filter");
		kill_session_var("sess_data_input_sort_column");
		kill_session_var("sess_data_input_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
		$_REQUEST["page"] = 1;
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("filter", "sess_data_input_filter", "");
	load_current_session_value("sort_column", "sess_data_input_sort_column", "name");
	load_current_session_value("sort_direction", "sess_data_input_sort_direction", "ASC");
	load_current_session_value("page", "sess_data_input_current_page", "1");

	html_start_box("<strong>Data Input Methods</strong>", "100%", $colors["header"], "3", "center", "data_input.php?action=edit");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>" class="noprint">
		<td class="noprint">
		<form name="form_graph_id" method="get" action="data_input.php">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr class="noprint">
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

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='data_input.php'>\n";

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	/* form the 'where' clause for our main sql query */
	$sql_where = "WHERE (data_input.name like '%%" . get_request_var_request("filter") . "%%')";

	$sql_where .= " AND (data_input.name!='Get Script Data (Indexed)'
		AND data_input.name!='Get Script Server Data (Indexed)'
		AND data_input.name!='Get SNMP Data'
		AND data_input.name!='Get SNMP Data (Indexed)')";

	$total_rows = db_fetch_cell("SELECT
		count(*)
		FROM data_input
		$sql_where");

	$data_inputs = db_fetch_assoc("SELECT *
		FROM data_input
		$sql_where
		ORDER BY " . get_request_var_request('sort_column') . " " . get_request_var_request('sort_direction') . "
		LIMIT " . (read_config_option("num_rows_device")*(get_request_var_request("page")-1)) . "," . read_config_option("num_rows_device"));

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, read_config_option("num_rows_device"), $total_rows, "data_input.php?filter=" . get_request_var_request("filter"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='7'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("data_input.php?filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("num_rows_device")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (read_config_option("num_rows_device")*get_request_var_request("page")))) ? $total_rows : (read_config_option("num_rows_device")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if ((get_request_var_request("page") * read_config_option("num_rows_device")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("data_input.php?filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * read_config_option("num_rows_device")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"name" => array("Name", "ASC"),
		"type_id" => array("Data Input Method", "ASC"));

	html_header_sort_checkbox($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), false);

	$i = 0;
	if (sizeof($data_inputs) > 0) {
		foreach ($data_inputs as $data_input) {
			/* hide system types */
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $data_input["id"]); $i++;
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("data_input.php?action=edit&id=" . $data_input["id"]) . "'>" . (strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($data_input["name"])) : htmlspecialchars($data_input["name"])) . "</a>", $data_input["id"]);
			form_selectable_cell($input_types{$data_input["type_id"]}, $data_input["id"]);
			form_checkbox_cell($data_input["name"], $data_input["id"]);
			form_end_row();
		}

		print $nav;
	}else{
		print "<tr><td><em>No Data Input Methods</em></td></tr>";
	}

	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($di_actions);

	print "</form>\n";

}
?>

