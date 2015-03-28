<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2015 The Cacti Group                                 |
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

chdir('../../');
global $config;
include_once("./include/auth.php");
include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		aggregate_color_item_form_save();

		break;
	case 'item_remove':
		aggregate_color_item_remove();

		header("Location: color_templates.php?action=template_edit&color_template_id=" . $_GET["color_template_id"]);
		break;
	case 'item_movedown':
		aggregate_color_item_movedown();

		header("Location: color_templates.php?action=template_edit&color_template_id=" . $_GET["color_template_id"]);
		break;
	case 'item_moveup':
		aggregate_color_item_moveup();

		header("Location: color_templates.php?action=template_edit&color_template_id=" . $_GET["color_template_id"]);
		break;
	case 'item_edit':
		top_header();
		aggregate_color_item_edit();
		bottom_footer();
		break;
	case 'item':
		top_header();
		aggregate_color_item();
		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */
/**
 * aggregate_color_item_form_save	the save function
 */
function aggregate_color_item_form_save() {

	if (isset($_POST["save_component_item"])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("color_template_id"));
		/* ==================================================== */
		$items[0] = array();

		foreach ($items as $item) {
			/* generate a new sequence if needed */
			if (empty($_POST["sequence"])) {
				$_POST["sequence"] = get_next_sequence($_POST["sequence"], "sequence", "color_template_items", "color_template_id=" . $_POST["color_template_id"], "color_template_id");
			}

			$save["color_template_item_id"] = htmlspecialchars($_POST["color_template_item_id"]);
			$save["color_template_id"] = htmlspecialchars($_POST["color_template_id"]);
			$save["color_id"] = form_input_validate((isset($item["color_id"]) ? $item["color_id"] : htmlspecialchars($_POST["color_id"])), "color_id", "", true, 3);
			$save["sequence"] = htmlspecialchars($_POST["sequence"]);

			if (!is_error_message()) {
				$color_template_item_id = sql_save($save, "color_template_items", "color_template_item_id");
				if ($color_template_item_id) {
					raise_message(1);
				}else{
					raise_message(2);
				}
			}

			$_POST["sequence"] = 0;
		}

		if (is_error_message()) {
			header("Location: color_templates_items.php?action=item_edit&color_template_item_id=" . (empty($color_template_item_id) ? $_POST["color_template_item_id"] : $color_template_item_id) . "&color_template_id=" . $_POST["color_template_id"]);
			exit;
		}else{
			header("Location: color_templates.php?action=template_edit&color_template_id=" . $_POST["color_template_id"]);
			exit;
		}
	}
}

/* -----------------------
    item - Graph Items
   ----------------------- */

/**
 * aggregate_color_item_movedown		move item down
 */
function aggregate_color_item_movedown() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("color_template_item_id"));
	input_validate_input_number(get_request_var("color_template_id"));
	/* ==================================================== */
	$current_sequence = db_fetch_row("select color_template_item_id, sequence
										from color_template_items
										where color_template_item_id=" . $_GET["color_template_item_id"],
										true);
	aggregate_log("AGGREGATE   movedown Id: " . $current_sequence["color_template_item_id"] . " Seq:" . $current_sequence["sequence"], FALSE);
	$next_sequence = db_fetch_row("select color_template_item_id, sequence
										from color_template_items
										where sequence > " . $current_sequence["sequence"] .
										" and color_template_id=" . $_GET["color_template_id"] .
										" order by sequence ASC limit 1",
										true);
	aggregate_log("AGGREGATE   movedown Id: " . $next_sequence["color_template_item_id"] . " Seq:" . $next_sequence["sequence"], FALSE);
	db_execute("update color_template_items
				set sequence					=" . $next_sequence["sequence"] .
				" where color_template_id	=" . $_GET["color_template_id"] .
				" and color_template_item_id=" . $current_sequence["color_template_item_id"]);
	db_execute("update color_template_items
				set sequence					=" . $current_sequence["sequence"] .
				" where color_template_id	=" . $_GET["color_template_id"] .
				" and color_template_item_id=" . $next_sequence["color_template_item_id"]);
}


/**
 * aggregate_color_item_moveup		move item up
 */
function aggregate_color_item_moveup() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("color_template_item_id"));
	input_validate_input_number(get_request_var("color_template_id"));
	/* ==================================================== */
	$current_sequence = db_fetch_row("select color_template_item_id, sequence
										from color_template_items
										where color_template_item_id=" . $_GET["color_template_item_id"],
										true);
	aggregate_log("AGGREGATE   moveup Id: " . $current_sequence["color_template_item_id"] . " Seq:" . $current_sequence["sequence"], FALSE);
	$previous_sequence = db_fetch_row("select color_template_item_id, sequence
										from color_template_items
										where sequence < " . $current_sequence["sequence"] .
										" and color_template_id=" . $_GET["color_template_id"] .
										" order by sequence DESC limit 1",
										true);
	aggregate_log("AGGREGATE   moveup Id: " . $previous_sequence["color_template_item_id"] . " Seq:" . $previous_sequence["sequence"], FALSE);
	db_execute("update color_template_items
				set sequence					=" . $previous_sequence["sequence"] .
				" where color_template_id	=" . $_GET["color_template_id"] .
				" and color_template_item_id=" . $current_sequence["color_template_item_id"]);
	db_execute("update color_template_items
				set sequence					=" . $current_sequence["sequence"] .
				" where color_template_id	=" . $_GET["color_template_id"] .
				" and color_template_item_id=" . $previous_sequence["color_template_item_id"]);
}


/**
 * aggregate_color_item_remove		remove item
 */
function aggregate_color_item_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("color_template_item_id"));
	input_validate_input_number(get_request_var("color_template_id"));
	/* ==================================================== */

	db_execute("delete from color_template_items where color_template_item_id=" . $_GET["color_template_item_id"]);
}


/**
 * aggregate_color_item_edit		edit item
 */
function aggregate_color_item_edit() {
	global $colors, $struct_color_template_item;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("color_template_item_id"));
	input_validate_input_number(get_request_var("color_template_id"));
	/* ==================================================== */

	$template = db_fetch_row("select * from color_templates where color_template_id=" . $_GET["color_template_id"]);
	if (isset($_REQUEST["color_template_item_id"]) && ($_REQUEST["color_template_item_id"] > 0)) {
		$template_item = db_fetch_row("select * from color_template_items where color_template_item_id=" . $_GET["color_template_item_id"]);
		$header_label = "[edit Report Item: " . $template["name"] . "]";
	}else{
		$template_item = array();
		$header_label = "[new Report Item: " . $template["name"] . "]";
	}

	print "<form method='post' action='" .  basename($_SERVER["PHP_SELF"]) . "' name='aggregate_color_item_edit'>\n";

	html_start_box("<strong>Color Template Items</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array("no_form_tag" => true),
		"fields" => inject_form_variables($struct_color_template_item, (isset($template_item) ? $template_item : array()))
	));

	html_end_box();

	form_hidden_box("color_template_item_id", (array_key_exists("color_template_item_id", $template_item) ? $template_item["color_template_item_id"] : "0"), "");
	form_hidden_box("color_template_id", $_GET["color_template_id"], "0");
	form_hidden_box("sequence", (array_key_exists("sequence", $template_item) ? $template_item["sequence"] : "0"), "");
	form_hidden_box("save_component_item", "1", "");

	aggregate_save_button(htmlspecialchars("color_templates.php?action=template_edit&color_template_id=" . $_GET["color_template_id"]), "", "color_template_item_id");
}
?>
