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

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();

		break;
	case 'remove':
		rra_remove();

		header("Location: rra.php");
		break;
	case 'edit':
		include_once("./include/top_header.php");

		rra_edit();

		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");

		rra();

		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_rra"])) {
		$save["id"] = $_POST["id"];
		$save["hash"] = get_hash_round_robin_archive($_POST["id"]);
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$dummy = form_input_validate(count($_POST["consolidation_function_id"]), "consolidation_function_id", "^[0-9]*$", false, 3);
		$save["x_files_factor"] = form_input_validate($_POST["x_files_factor"], "x_files_factor", "^[01]?(\.[0-9]+)?$", false, 3);
		$save["steps"] = form_input_validate($_POST["steps"], "steps", "^[0-9]*$", false, 3);
		$save["rows"] = form_input_validate($_POST["rows"], "rows", "^[0-9]*$", false, 3);
		$save["timespan"] = form_input_validate($_POST["timespan"], "timespan", "^[0-9]*$", false, 3);

		if (!is_error_message()) {
			$rra_id = sql_save($save, "rra");

			if ($rra_id) {
				raise_message(1);

				db_execute("delete from rra_cf where rra_id=$rra_id");

				if (isset($_POST["consolidation_function_id"])) {
					for ($i=0; ($i < count($_POST["consolidation_function_id"])); $i++) {
						/* ================= input validation ================= */
						input_validate_input_number($_POST["consolidation_function_id"][$i]);
						/* ==================================================== */

						db_execute("insert into rra_cf (rra_id,consolidation_function_id)
							values ($rra_id," . $_POST["consolidation_function_id"][$i] . ")");
					}
				}else{
					raise_message(2);
				}
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header("Location: rra.php?action=edit&id=" . (empty($rra_id) ? $_POST["id"] : $rra_id));
		}else{
			header("Location: rra.php");
		}
	}
}

/* -------------------
    RRA Functions
   ------------------- */

function rra_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if ((read_config_option("deletion_verification") == "on") && (!isset($_GET["confirm"]))) {
		include_once("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the round robin archive <strong>'" . htmlspecialchars(db_fetch_cell("select name from rra where id=" . $_GET["id"])) . "'</strong>?", htmlspecialchars("rra.php"), htmlspecialchars("rra.php?action=remove&id=" . $_GET["id"]));
		exit;
	}

	if ((read_config_option("deletion_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from rra where id=" . $_GET["id"]);
		db_execute("delete from rra_cf where rra_id=" . $_GET["id"]);
    	}
}

function rra_edit() {
	global $colors, $fields_rra_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if (!empty($_GET["id"])) {
		$rra = db_fetch_row("select * from rra where id=" . $_GET["id"]);
		$header_label = "[edit: " . htmlspecialchars($rra["name"]) . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>Round Robin Archives</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_rra_edit, (isset($rra) ? $rra : array()))
		));

	html_end_box();

	form_save_button("rra.php");
}

function rra() {
	global $colors;

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("sort_column", "sess_rra_sort_column", "timespan");
	load_current_session_value("sort_direction", "sess_rra_sort_direction", "ASC");

	html_start_box("<strong>Round Robin Archives</strong>", "100%", $colors["header"], "3", "center", "rra.php?action=edit");

	$display_text = array(
		"name" => array("Name", "ASC"),
		"steps" => array("Steps", "ASC"),
		"rows" => array("Rows", "ASC"),
		"timespan" => array("Timespan", "ASC"));

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"], 4);

	$rras = db_fetch_assoc("SELECT
		id,
		name,
		rows,
		steps,
		timespan
		FROM rra
		ORDER BY " . $_REQUEST['sort_column'] . " " . $_REQUEST['sort_direction']);

	$i = 0;
	if (sizeof($rras) > 0) {
	foreach ($rras as $rra) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="<?php print htmlspecialchars("rra.php?action=edit&id=" . $rra["id"]);?>"><?php print htmlspecialchars($rra["name"]);?></a>
			</td>
			<td>
				<?php print $rra["steps"];?>
			</td>
			<td>
				<?php print $rra["rows"];?>
			</td>
			<td>
				<?php print $rra["timespan"];?>
			</td>
			<td align="right">
				<a href="<?php print htmlspecialchars("rra.php?action=remove&id=" . $rra["id"]);?>"><img src="images/delete_icon.gif" style="height:10px;width:10px;" border="0" alt="Delete"></a>
			</td>
		</tr>
	<?php
	}
	}
	html_end_box();
}
?>
