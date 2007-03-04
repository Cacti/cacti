<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
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

include ("./include/auth.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();

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
	case 'remove':
		data_remove();

		header("Location: data_input.php");
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

					generate_data_input_field_sequences($_POST["input_string"], $_POST["id"], "in");
				}
			}else{
				raise_message(2);
			}
		}

		if ((is_error_message()) || (empty($_POST["id"]))) {
			header("Location: data_input.php?action=edit&id=" . (empty($data_input_id) ? $_POST["id"] : $data_input_id));
		}else{
			header("Location: data_input.php");
		}
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

				if (!empty($data_input_field_id)) {
					generate_data_input_field_sequences(db_fetch_cell("select " . $_POST["input_output"] . "put_string from data_input where id=" . $_POST["data_input_id"]), $_POST["data_input_id"], $_POST["input_output"]);
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

/* --------------------------
    CDEF Item Functions
   -------------------------- */

function field_remove() {
	global $registered_cacti_names;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("data_input_id"));
	/* ==================================================== */

	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the field <strong>'" . db_fetch_cell("select name from data_input_fields where id=" . $_GET["id"]) . "'</strong>?", "data_input.php?action=edit&id=" . $_GET["data_input_id"], "data_input.php?action=field_remove&id=" . $_GET["id"] . "&data_input_id=" . $_GET["data_input_id"]);
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		/* get information about the field we're going to delete so we can re-order the seqs */
		$field = db_fetch_row("select input_output,data_input_id from data_input_fields where id=" . $_GET["id"]);

		db_execute("delete from data_input_fields where id=" . $_GET["id"]);
		db_execute("delete from data_input_data where data_input_field_id=" . $_GET["id"]);

		/* when a field is deleted; we need to re-order the field sequences */
		if (preg_match_all("/<([_a-zA-Z0-9]+)>/", db_fetch_cell("select " . $field["input_output"] . "put_string from data_input where id=" . $field["data_input_id"]), $matches)) {
			$j = 0;
			for ($i=0; ($i < count($matches[1])); $i++) {
				if (in_array($matches[1][$i], $registered_cacti_names) == false) {
					$j++; db_execute("update data_input_fields set sequence=$j where data_input_id=" . $field["data_input_id"] . " and input_output='" .  $field["input_output"]. "' and data_name='" . $matches[1][$i] . "'");
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
	if (preg_match_all("/<([_a-zA-Z0-9]+)>/", db_fetch_cell("select $current_field_type" . "put_string from data_input where id=" . ($_GET["data_input_id"] ? $_GET["data_input_id"] : $field["data_input_id"])), $matches)) {
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

	html_start_box("<strong>$header_name Fields</strong> [edit: " . $data_input["name"] . "]", "98%", $colors["header"], "3", "center", "");

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

function data_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the data input method <strong>'" . db_fetch_cell("select name from data_input where id=" . $_GET["id"]) . "'</strong>?", "data_input.php", "data_input.php?action=remove&id=" . $_GET["id"]);
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		$data_input_fields = db_fetch_assoc("select id from data_input_fields where data_input_id=" . $_GET["id"]);

		if (is_array($data_input_fields)) {
			foreach ($data_input_fields as $data_input_field) {
				db_execute("delete from data_input_data where data_input_field_id=" . $data_input_field["id"]);
			}
		}

		db_execute("delete from data_input where id=" . $_GET["id"]);
		db_execute("delete from data_input_fields where data_input_id=" . $_GET["id"]);
	}
}

function data_edit() {
	global $colors, $fields_data_input_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if (!empty($_GET["id"])) {
		$data_input = db_fetch_row("select * from data_input where id=" . $_GET["id"]);
		$header_label = "[edit: " . $data_input["name"] . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>Data Input Methods</strong> $header_label", "98%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_data_input_edit, (isset($data_input) ? $data_input : array()))
		));

	html_end_box();

	if (!empty($_GET["id"])) {
		html_start_box("<strong>Input Fields</strong>", "98%", $colors["header"], "3", "center", "data_input.php?action=field_edit&type=in&data_input_id=" . $_GET["id"]);
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
					<a class="linkEditMain" href="data_input.php?action=field_edit&id=<?php print $field["id"];?>&data_input_id=<?php print $_GET["id"];?>"><?php print $field["data_name"];?></a>
				</td>
				<td>
					<?php print $field["sequence"]; if ($field["sequence"] == "0") { print " (Not In Use)"; }?>
				</td>
				<td>
					<?php print $field["name"];?>
				</td>
				<td align="right">
					<a href="data_input.php?action=field_remove&id=<?php print $field["id"];?>&data_input_id=<?php print $_GET["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>
				</td>
			</tr>
		<?php
		}
		}else{
			print "<tr><td><em>No Input Fields</em></td></tr>";
		}
		html_end_box();

		html_start_box("<strong>Output Fields</strong>", "98%", $colors["header"], "3", "center", "data_input.php?action=field_edit&type=out&data_input_id=" . $_GET["id"]);
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
					<a class="linkEditMain" href="data_input.php?action=field_edit&id=<?php print $field["id"];?>&data_input_id=<?php print $_GET["id"];?>"><?php print $field["data_name"];?></a>
				</td>
				<td>
					<?php print $field["sequence"]; if ($field["sequence"] == "0") { print " (Not In Use)"; }?>
				</td>
				<td>
					<?php print $field["name"];?>
				</td>
				<td>
					<?php print html_boolean_friendly($field["update_rra"]);?>
				</td>
				<td align="right">
					<a href="data_input.php?action=field_remove&id=<?php print $field["id"];?>&data_input_id=<?php print $_GET["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>
				</td>
			</tr>
		<?php
		}
		}else{
			print "<tr><td><em>No Output Fields</em></td></tr>";
		}
		html_end_box();
	}

	form_save_button("data_input.php");
}

function data() {
	global $colors, $input_types;

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("sort_column", "sess_data_input_sort_column", "name");
	load_current_session_value("sort_direction", "sess_data_input_sort_direction", "ASC");

	html_start_box("<strong>Data Input Methods</strong>", "98%", $colors["header"], "3", "center", "data_input.php?action=edit");

	$display_text = array(
		"name" => array("Name", "ASC"),
		"type_id" => array("Data Input Method", "ASC"));

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"], 3);

	$data_inputs = db_fetch_assoc("SELECT * FROM data_input ORDER BY " . $_REQUEST['sort_column'] . " " . $_REQUEST['sort_direction']);

	$i = 0;
	if (sizeof($data_inputs) > 0) {
	foreach ($data_inputs as $data_input) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="data_input.php?action=edit&id=<?php print $data_input["id"];?>"><?php print $data_input["name"];?></a>
			</td>
			<td>
				<?php print $input_types{$data_input["type_id"]};?>
			</td>
			<td align="right">
				<a href="data_input.php?action=remove&id=<?php print $data_input["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>
			</td>
		</tr>
	<?php
	}
	}else{
		print "<tr><td><em>No Data Input Methods</em></td></tr>";
	}
	html_end_box();
}
?>
