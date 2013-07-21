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
include_once("./lib/cdef.php");

define("MAX_DISPLAY_PAGES", 21);

$cdef_actions = array(
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
	case 'item_movedown':
		item_movedown();

		header("Location: cdef.php?action=edit&id=" . $_GET["cdef_id"]);
		break;
	case 'item_moveup':
		item_moveup();

		header("Location: cdef.php?action=edit&id=" . $_GET["cdef_id"]);
		break;
	case 'item_remove':
		item_remove();

		header("Location: cdef.php?action=edit&id=" . $_GET["cdef_id"]);
		break;
	case 'item_edit':
		include_once("./include/top_header.php");

		item_edit();

		include_once("./include/bottom_footer.php");
		break;
	case 'remove':
		cdef_remove();

		header ("Location: cdef.php");
		break;
	case 'edit':
		include_once("./include/top_header.php");

		cdef_edit();

		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");

		cdef();

		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function draw_cdef_preview($cdef_id) {
	global $colors; ?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
			<pre>cdef=<?php print get_cdef($cdef_id, true);?></pre>
		</td>
	</tr>
<?php }


/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_cdef"])) {
		$save["id"] = $_POST["id"];
		$save["hash"] = get_hash_cdef($_POST["id"]);
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);

		if (!is_error_message()) {
			$cdef_id = sql_save($save, "cdef");

			if ($cdef_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		header("Location: cdef.php?action=edit&id=" . (empty($cdef_id) ? $_POST["id"] : $cdef_id));
	}elseif (isset($_POST["save_component_item"])) {
		$sequence = get_sequence($_POST["id"], "sequence", "cdef_items", "cdef_id=" . $_POST["cdef_id"]);

		$save["id"] = $_POST["id"];
		$save["hash"] = get_hash_cdef($_POST["id"], "cdef_item");
		$save["cdef_id"] = $_POST["cdef_id"];
		$save["sequence"] = $sequence;
		$save["type"] = $_POST["type"];
		$save["value"] = $_POST["value"];

		if (!is_error_message()) {
			$cdef_item_id = sql_save($save, "cdef_items");

			if ($cdef_item_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header("Location: cdef.php?action=item_edit&cdef_id=" . $_POST["cdef_id"] . "&id=" . (empty($cdef_item_id) ? $_POST["id"] : $cdef_item_id));
		}else{
			header("Location: cdef.php?action=edit&id=" . $_POST["cdef_id"]);
		}
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $cdef_actions;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* delete */
			db_execute("delete from cdef where " . array_to_sql_or($selected_items, "id"));
			db_execute("delete from cdef_items where " . array_to_sql_or($selected_items, "cdef_id"));

		}elseif ($_POST["drp_action"] == "2") { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				duplicate_cdef($selected_items[$i], $_POST["title_format"]);
			}
		}

		header("Location: cdef.php");
		exit;
	}

	/* setup some variables */
	$cdef_list = ""; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$cdef_list .= "<li>" . db_fetch_cell("select name from cdef where id=" . $matches[1]) . "<br>";
			$cdef_array[$i] = $matches[1];

			$i++;
		}
	}

	include_once("./include/top_header.php");

	print "<form action='cdef.php' method='post'>\n";

	html_start_box("<strong>" . $cdef_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	if (isset($cdef_array) && sizeof($cdef_array)) {
		if ($_POST["drp_action"] == "1") { /* delete */
			print "	<tr>
					<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click \"Continue\", the folling CDEF(s) will be deleted.</p>
						<p><ul>$cdef_list</ul></p>
					</td>
				</tr>\n
				";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete CDEF(s)'>";
		}elseif ($_POST["drp_action"] == "2") { /* duplicate */
			print "	<tr>
					<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click \"Continue\", the following CDEFs will be duplicated. You can
						optionally change the title format for the new CDEFs.</p>
						<p><ul>$cdef_list</ul></p>
						<p><strong>Title Format:</strong><br>"; form_text_box("title_format", "<cdef_title> (1)", "", "255", "30", "text"); print "</p>
					</td>
				</tr>\n
				";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Duplicate CDEF(s)'>";
		}
	}else{
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one CDEF.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($cdef_array) ? serialize($cdef_array) : '') . "'>
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

function item_movedown() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("cdef_id"));
	/* ==================================================== */

	move_item_down("cdef_items", $_GET["id"], "cdef_id=" . $_GET["cdef_id"]);
}

function item_moveup() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("cdef_id"));
	/* ==================================================== */

	move_item_up("cdef_items", $_GET["id"], "cdef_id=" . $_GET["cdef_id"]);
}

function item_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("cdef_id"));
	/* ==================================================== */

	db_execute("delete from cdef_items where id=" . $_GET["id"]);
}

function item_edit() {
	global $colors, $cdef_item_types, $cdef_functions, $cdef_operators, $custom_data_source_types;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("cdef_id"));
	/* ==================================================== */

	if (!empty($_GET["id"])) {
		$cdef = db_fetch_row("select * from cdef_items where id=" . $_GET["id"]);
		$current_type = $cdef["type"];
		$values[$current_type] = $cdef["value"];
	}

	html_start_box("", "100%", "aaaaaa", "3", "center", "");
	draw_cdef_preview($_GET["cdef_id"]);
	html_end_box();

	print "<form method='post' action='cdef.php' name='form_cdef'>\n";

	html_start_box("<strong>CDEF Items</strong> [edit: " . htmlspecialchars(db_fetch_cell("select name from cdef where id=" . $_GET["cdef_id"])) . "]", "100%", $colors["header"], "3", "center", "");

	if (isset($_GET["type_select"])) {
		$current_type = $_GET["type_select"];
	}elseif (isset($cdef["type"])) {
		$current_type = $cdef["type"];
	}else{
		$current_type = "1";
	}

	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">CDEF Item Type</font><br>
			Choose what type of CDEF item this is.
		</td>
		<td>
			<select name="type_select" onChange="window.location=document.form_cdef.type_select.options[document.form_cdef.type_select.selectedIndex].value">
				<?php
				while (list($var, $val) = each($cdef_item_types)) {
					print "<option value='cdef.php?action=item_edit" . (isset($_GET["id"]) ? "&id=" . $_GET["id"] : "") . "&cdef_id=" . $_GET["cdef_id"] . "&type_select=$var'"; if ($var == $current_type) { print " selected"; } print ">$val</option>\n";
				}
				?>
			</select>
		</td>
	</tr>
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">CDEF Item Value</font><br>
			Enter a value for this CDEF item.
		</td>
		<td>
			<?php
			switch ($current_type) {
			case '1':
				form_dropdown("value", $cdef_functions, "", "", (isset($cdef["value"]) ? $cdef["value"] : ""), "", "");
				break;
			case '2':
				form_dropdown("value", $cdef_operators, "", "", (isset($cdef["value"]) ? $cdef["value"] : ""), "", "");
				break;
			case '4':
				form_dropdown("value", $custom_data_source_types, "", "", (isset($cdef["value"]) ? $cdef["value"] : ""), "", "");
				break;
			case '5':
				form_dropdown("value", db_fetch_assoc("select name,id from cdef order by name"), "name", "id", (isset($cdef["value"]) ? $cdef["value"] : ""), "", "");
				break;
			case '6':
				form_text_box("value", (isset($cdef["value"]) ? $cdef["value"] : ""), "", "255", 30, "text", (isset($_GET["id"]) ? $_GET["id"] : "0"));
				break;
			}
			?>
		</td>
	</tr>
	<?php

	form_hidden_box("id", (isset($_GET["id"]) ? $_GET["id"] : "0"), "");
	form_hidden_box("type", $current_type, "");
	form_hidden_box("cdef_id", $_GET["cdef_id"], "");
	form_hidden_box("save_component_item", "1", "");

	html_end_box();

	form_save_button("cdef.php?action=edit&id=" . $_GET["cdef_id"]);
}

/* ---------------------
    CDEF Functions
   --------------------- */

function cdef_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if ((read_config_option("deletion_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the CDEF <strong>'" . htmlspecialchars(db_fetch_cell("select name from cdef where id=" . $_GET["id"])) . "'</strong>?", htmlspecialchars("cdef.php"), htmlspecialchars("cdef.php?action=remove&id=" . $_GET["id"]));
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("deletion_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from cdef where id=" . $_GET["id"]);
		db_execute("delete from cdef_items where cdef_id=" . $_GET["id"]);
	}
}

function cdef_edit() {
	global $colors, $cdef_item_types, $fields_cdef_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if (!empty($_GET["id"])) {
		$cdef = db_fetch_row("select * from cdef where id=" . $_GET["id"]);
		$header_label = "[edit: " . htmlspecialchars($cdef["name"]) . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>CDEF's</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_cdef_edit, (isset($cdef) ? $cdef : array()))
		));

	html_end_box();

	if (!empty($_GET["id"])) {
		html_start_box("", "100%", "aaaaaa", "3", "center", "");
		draw_cdef_preview($_GET["id"]);
		html_end_box();

		html_start_box("<strong>CDEF Items</strong>", "100%", $colors["header"], "3", "center", "cdef.php?action=item_edit&cdef_id=" . $cdef["id"]);

		print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
			DrawMatrixHeaderItem("Item",$colors["header_text"],1);
			DrawMatrixHeaderItem("Item Value",$colors["header_text"],1);
			DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],2);
		print "</tr>";

		$cdef_items = db_fetch_assoc("select * from cdef_items where cdef_id=" . $_GET["id"] . " order by sequence");

		$i = 0;
		if (sizeof($cdef_items) > 0) {
			foreach ($cdef_items as $cdef_item) {
				form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
					?>
					<td>
						<a class="linkEditMain" href="<?php print htmlspecialchars("cdef.php?action=item_edit&id=" . $cdef_item["id"] . "&cdef_id=" . $cdef["id"]);?>">Item #<?php print htmlspecialchars($i);?></a>
					</td>
					<td>
						<em><?php $cdef_item_type = $cdef_item["type"]; print $cdef_item_types[$cdef_item_type];?></em>: <strong><?php print get_cdef_item_name($cdef_item["id"]);?></strong>
					</td>
					<td>
						<a href="<?php print htmlspecialchars("cdef.php?action=item_movedown&id=" . $cdef_item["id"] . "&cdef_id=" . $cdef["id"]);?>"><img src="images/move_down.gif" border="0" alt="Move Down"></a>
						<a href="<?php print htmlspecialchars("cdef.php?action=item_moveup&id=" . $cdef_item["id"] . "&cdef_id=" . $cdef["id"]);?>"><img src="images/move_up.gif" border="0" alt="Move Up"></a>
					</td>
					<td align="right">
						<a href="<?php print htmlspecialchars("cdef.php?action=item_remove&id=" . $cdef_item["id"] . "&cdef_id=" . $cdef["id"]);?>"><img src="images/delete_icon.gif" style="height:10px;width:10px;" border="0" alt="Delete"></a>
					</td>
				</tr>
			<?php
			}
		}
		html_end_box();
	}

	form_save_button("cdef.php", "return");
}

function cdef() {
	global $colors, $cdef_actions;

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_cdef_current_page");
		kill_session_var("sess_cdef_filter");
		kill_session_var("sess_cdef_sort_column");
		kill_session_var("sess_cdef_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);

	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_cdef_current_page", "1");
	load_current_session_value("filter", "sess_cdef_filter", "");
	load_current_session_value("sort_column", "sess_cdef_sort_column", "name");
	load_current_session_value("sort_direction", "sess_cdef_sort_direction", "ASC");

	html_start_box("<strong>CDEF's</strong>", "100%", $colors["header"], "3", "center", "cdef.php?action=edit");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
			<form name="form_cdef" action="cdef.php">
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
	$sql_where = "WHERE (cdef.name LIKE '%%" . get_request_var_request("filter") . "%%')";

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='cdef.php'>\n";

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT
		COUNT(cdef.id)
		FROM cdef
		$sql_where");

	$cdef_list = db_fetch_assoc("SELECT
		cdef.id,cdef.name
		FROM cdef
		$sql_where
		ORDER BY " . get_request_var_request("sort_column") . " " . get_request_var_request("sort_direction") .
		" LIMIT " . (read_config_option("num_rows_device")*(get_request_var_request("page")-1)) . "," . read_config_option("num_rows_device"));

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, read_config_option("num_rows_device"), $total_rows, "cdef.php?filter=" . get_request_var_request("filter"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
		<td colspan='7'>
			<table width='100%' cellspacing='0' cellpadding='0' border='0'>
				<tr>
					<td align='left' class='textHeaderDark'>
						<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("cdef.php?filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
					</td>\n
					<td align='center' class='textHeaderDark'>
						Showing Rows " . ((read_config_option("num_rows_device")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (read_config_option("num_rows_device")*get_request_var_request("page")))) ? $total_rows : (read_config_option("num_rows_device") * get_request_var_request("page"))) . " of $total_rows [$url_page_select]
					</td>\n
					<td align='right' class='textHeaderDark'>
						<strong>"; if ((get_request_var_request("page") * read_config_option("num_rows_device")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("cdef.php?filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * read_config_option("num_rows_device")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
					</td>\n
				</tr>
			</table>
		</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"name" => array("CDEF Title", "ASC"));

	html_header_sort_checkbox($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), false);

	$i = 0;
	if (sizeof($cdef_list) > 0) {
		foreach ($cdef_list as $cdef) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $cdef["id"]);$i++;
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("cdef.php?action=edit&id=" . $cdef["id"]) . "'>" . (strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($cdef["name"])) : htmlspecialchars($cdef["name"])) . "</a>", $cdef["id"]);
			form_checkbox_cell($cdef["name"], $cdef["id"]);
			form_end_row();
		}
		print $nav;
	}else{
		print "<tr><td><em>No CDEFs</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($cdef_actions);

	print "</form>\n";
}
?>

