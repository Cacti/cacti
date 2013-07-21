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
		gprint_presets_remove();

		header("Location: gprint_presets.php");
		break;
	case 'edit':
		include_once("./include/top_header.php");

		gprint_presets_edit();

		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");

		gprint_presets();

		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_gprint_presets"])) {
		$save["id"] = $_POST["id"];
		$save["hash"] = get_hash_gprint($_POST["id"]);
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save["gprint_text"] = form_input_validate($_POST["gprint_text"], "gprint_text", "", false, 3);

		if (!is_error_message()) {
			$gprint_preset_id = sql_save($save, "graph_templates_gprint");

			if ($gprint_preset_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header("Location: gprint_presets.php?action=edit&id=" . (empty($gprint_preset_id) ? $_POST["id"] : $gprint_preset_id));
			exit;
		}else{
			header("Location: gprint_presets.php");
			exit;
		}
	}
}

/* -----------------------------------
    gprint_presets - GPRINT Presets
   ----------------------------------- */

function gprint_presets_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if ((read_config_option("deletion_verification") == "on") && (!isset($_GET["confirm"]))) {
		include_once("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the GPRINT preset <strong>'" . htmlspecialchars(db_fetch_cell("select name from graph_templates_gprint where id=" . $_GET["id"])) . "'</strong>? This could affect every graph that uses this preset, make sure you know what you are doing first!", htmlspecialchars("gprint_presets.php"), htmlspecialchars("gprint_presets.php?action=remove&id=" . $_GET["id"]));
		exit;
	}

	if ((read_config_option("deletion_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from graph_templates_gprint where id=" . $_GET["id"]);
	}
}

function gprint_presets_edit() {
	global $colors, $fields_grprint_presets_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if (!empty($_GET["id"])) {
		$gprint_preset = db_fetch_row("select * from graph_templates_gprint where id=" . $_GET["id"]);
		$header_label = "[edit: " . htmlspecialchars($gprint_preset["name"]) . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>GPRINT Presets</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_grprint_presets_edit, (isset($gprint_preset) ? $gprint_preset : array()))
		));

	html_end_box();

	form_save_button("gprint_presets.php");
}

function gprint_presets() {
	global $colors;

	html_start_box("<strong>GPRINT Presets</strong>", "100%", $colors["header"], "3", "center", "gprint_presets.php?action=edit");

	print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
			<td colspan='2' class='textSubHeaderDark'>GPRINT Preset Title</td>
		</tr>";

	$template_list = db_fetch_assoc("select
		graph_templates_gprint.id,
		graph_templates_gprint.name
		from graph_templates_gprint");

	$i = 0;
	if (sizeof($template_list) > 0) {
	foreach ($template_list as $template) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="<?php print htmlspecialchars("gprint_presets.php?action=edit&id=" . $template["id"]);?>"><?php print htmlspecialchars($template["name"]);?></a>
			</td>
			<td align="right">
				<a href="<?php print htmlspecialchars("gprint_presets.php?action=remove&id=" . $template["id"]);?>"><img src="images/delete_icon.gif" style="height:10px;width:10px;" border="0" alt="Delete"></a>
			</td>
		</tr>
		<?php
		$i++;
	}
	}else{
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
			<td colspan="2">
				<em>No Items</em>
			</td>
		</tr><?php
	}
	html_end_box();
}
