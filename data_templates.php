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

include ("./include/auth.php");
include_once("./lib/tree.php");
include_once("./lib/html_tree.php");
include_once("./lib/utility.php");
include_once("./lib/template.php");

$ds_actions = array(
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
	case 'rrd_add':
		template_rrd_add();

		break;
	case 'rrd_remove':
		template_rrd_remove();

		break;
	case 'template_remove':
		template_remove();

		header("Location: data_templates.php");
		break;
	case 'template_edit':
		include_once("./include/top_header.php");

		template_edit();

		include_once ("./include/bottom_footer.php");
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
	if (isset($_POST["save_component_template"])) {
		/* save: data_template */
		$save1["id"] = $_POST["data_template_id"];
		$save1["hash"] = get_hash_data_template($_POST["data_template_id"]);
		$save1["name"] = form_input_validate($_POST["template_name"], "template_name", "", false, 3);

		/* save: data_template_data */
		$save2["id"] = $_POST["data_template_data_id"];
		$save2["local_data_template_data_id"] = 0;
		$save2["local_data_id"] = 0;

		$save2["data_input_id"] = form_input_validate($_POST["data_input_id"], "data_input_id", "", true, 3);
		$save2["t_name"] = form_input_validate((isset($_POST["t_name"]) ? $_POST["t_name"] : ""), "t_name", "", true, 3);
		$save2["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save2["t_active"] = form_input_validate((isset($_POST["t_active"]) ? $_POST["t_active"] : ""), "t_active", "", true, 3);
		$save2["active"] = form_input_validate((isset($_POST["active"]) ? $_POST["active"] : ""), "active", "", true, 3);
		$save2["t_rrd_step"] = form_input_validate((isset($_POST["t_rrd_step"]) ? $_POST["t_rrd_step"] : ""), "t_rrd_step", "", true, 3);
		$save2["rrd_step"] = form_input_validate($_POST["rrd_step"], "rrd_step", "^[0-9]+$", false, 3);
		$save2["t_rra_id"] = form_input_validate((isset($_POST["t_rra_id"]) ? $_POST["t_rra_id"] : ""), "t_rra_id", "", true, 3);

		/* save: data_template_rrd */
		$save3["id"] = $_POST["data_template_rrd_id"];
		$save3["hash"] = get_hash_data_template($_POST["data_template_rrd_id"], "data_template_item");
		$save3["local_data_template_rrd_id"] = 0;
		$save3["local_data_id"] = 0;

		$save3["t_rrd_maximum"] = form_input_validate((isset($_POST["t_rrd_maximum"]) ? $_POST["t_rrd_maximum"] : ""), "t_rrd_maximum", "", true, 3);
		$save3["rrd_maximum"] = form_input_validate($_POST["rrd_maximum"], "rrd_maximum", "^-?[0-9]+$", false, 3);
		$save3["t_rrd_minimum"] = form_input_validate((isset($_POST["t_rrd_minimum"]) ? $_POST["t_rrd_minimum"] : ""), "t_rrd_minimum", "", true, 3);
		$save3["rrd_minimum"] = form_input_validate($_POST["rrd_minimum"], "rrd_minimum", "^-?[0-9]+$", false, 3);
		$save3["t_rrd_heartbeat"] = form_input_validate((isset($_POST["t_rrd_heartbeat"]) ? $_POST["t_rrd_heartbeat"] : ""), "t_rrd_heartbeat", "", true, 3);
		$save3["rrd_heartbeat"] = form_input_validate($_POST["rrd_heartbeat"], "rrd_heartbeat", "^[0-9]+$", false, 3);
		$save3["t_data_source_type_id"] = form_input_validate((isset($_POST["t_data_source_type_id"]) ? $_POST["t_data_source_type_id"] : ""), "t_data_source_type_id", "", true, 3);
		$save3["data_source_type_id"] = form_input_validate($_POST["data_source_type_id"], "data_source_type_id", "", true, 3);
		$save3["t_data_source_name"] = form_input_validate((isset($_POST["t_data_source_name"]) ? $_POST["t_data_source_name"] : ""), "t_data_source_name", "", true, 3);
		$save3["data_source_name"] = form_input_validate($_POST["data_source_name"], "data_source_name", "^[a-zA-Z0-9_]{1,19}$", false, 3);
		$save3["t_data_input_field_id"] = form_input_validate((isset($_POST["t_data_input_field_id"]) ? $_POST["t_data_input_field_id"] : ""), "t_data_input_field_id", "", true, 3);
		$save3["data_input_field_id"] = form_input_validate((isset($_POST["data_input_field_id"]) ? $_POST["data_input_field_id"] : "0"), "data_input_field_id", "", true, 3);

		if (!is_error_message()) {
			$data_template_id = sql_save($save1, "data_template");

			if ($data_template_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (!is_error_message()) {
			$save2["data_template_id"] = $data_template_id;
			$data_template_data_id = sql_save($save2, "data_template_data");

			if ($data_template_data_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (!is_error_message()) {
			$save3["data_template_id"] = $data_template_id;
			$data_template_rrd_id = sql_save($save3, "data_template_rrd");

			if ($data_template_rrd_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (!is_error_message()) {
			/* save entries in 'selected rras' field */
			db_execute("delete from data_template_data_rra where data_template_data_id=$data_template_data_id");

			if (isset($_POST["rra_id"])) {
				for ($i=0; ($i < count($_POST["rra_id"])); $i++) {
					db_execute("insert into data_template_data_rra (rra_id,data_template_data_id)
						values (" . $_POST["rra_id"][$i] . ",$data_template_data_id)");
				}
			}

			if (!empty($_POST["data_template_id"])) {
				/* push out all data source settings to child data source using this template */
				push_out_data_source($data_template_data_id);
				push_out_data_source_item($data_template_rrd_id);

				/* ok, first pull out all 'input' values so we know how much to save */
				$input_fields = db_fetch_assoc("select
					id,
					input_output,
					data_name
					from data_input_fields
					where data_input_id=" . $_POST["data_input_id"] . "
					and input_output='in'");

				db_execute("delete from data_input_data where data_template_data_id=$data_template_data_id");

				if (sizeof($input_fields) > 0) {
				foreach ($input_fields as $input_field) {
					$form_value = "value_" . $input_field["data_name"];

					if (isset($_POST[$form_value])) {
						/* save the data into the 'host_template_data' table */
						if (isset($_POST{"t_value_" . $input_field["data_name"]})) {
							$template_this_item = "on";
						}else{
							$template_this_item = "";
						}

						if ((!empty($form_value)) || (!empty($_POST{"t_value_" . $input_field["data_name"]}))) {
							db_execute("insert into data_input_data (data_input_field_id,data_template_data_id,t_value,value)
								values (" . $input_field["id"] . ",$data_template_data_id,'$template_this_item','" . $_POST[$form_value] . "')");
						}
					}
				}
				}

				/* push out all "custom data" for this data source template */
				push_out_data_source_custom_data($data_template_id);
				push_out_host(0, 0, $data_template_id);
			}
		}

		if ((is_error_message()) || (empty($_POST["data_template_id"]))) {
			header("Location: data_templates.php?action=template_edit&id=" . (empty($data_template_id) ? $_POST["data_template_id"] : $data_template_id) . (empty($_POST["current_rrd"]) ? "" : "&view_rrd=" . ($_POST["current_rrd"] ? $_POST["current_rrd"] : $data_template_rrd_id)));
		}else{
			header("Location: data_templates.php");
		}
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $ds_actions;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* delete */
			$data_template_datas = db_fetch_assoc("select id from data_template_data where " . array_to_sql_or($selected_items, "data_template_id") . " and local_data_id=0");

			if (sizeof($data_template_datas) > 0) {
			foreach ($data_template_datas as $data_template_data) {
				db_execute("delete from data_template_data_rra where data_template_data_id=" . $data_template_data["id"]);
			}
			}

			db_execute("delete from data_template_data where " . array_to_sql_or($selected_items, "data_template_id") . " and local_data_id=0");
			db_execute("delete from data_template_rrd where " . array_to_sql_or($selected_items, "data_template_id") . " and local_data_id=0");
			db_execute("delete from snmp_query_graph_rrd where " . array_to_sql_or($selected_items, "data_template_id"));
			db_execute("delete from snmp_query_graph_rrd_sv where " . array_to_sql_or($selected_items, "data_template_id"));
			db_execute("delete from data_template where " . array_to_sql_or($selected_items, "id"));

			/* "undo" any graph that is currently using this template */
			db_execute("update data_template_data set local_data_template_data_id=0,data_template_id=0 where " . array_to_sql_or($selected_items, "data_template_id"));
			db_execute("update data_template_rrd set local_data_template_rrd_id=0,data_template_id=0 where " . array_to_sql_or($selected_items, "data_template_id"));
			db_execute("update data_local set data_template_id=0 where " . array_to_sql_or($selected_items, "data_template_id"));
		}elseif ($_POST["drp_action"] == "2") { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				duplicate_data_source(0, $selected_items[$i], $_POST["title_format"]);
			}
		}

		header("Location: data_templates.php");
		exit;
	}

	/* setup some variables */
	$ds_list = ""; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			$ds_list .= "<li>" . db_fetch_cell("select name from data_template where id=" . $matches[1]) . "<br>";
			$ds_array[$i] = $matches[1];
		}

		$i++;
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $ds_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='data_templates.php' method='post'>\n";

	if ($_POST["drp_action"] == "1") { /* delete */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to delete the following data templates? Any data sources attached
					to these templates will become individual data sources.</p>
					<p>$ds_list</p>
				</td>
			</tr>\n
			";
	}elseif ($_POST["drp_action"] == "2") { /* duplicate */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>When you click save, the following data templates will be duplicated. You can
					optionally change the title format for the new data templates.</p>
					<p>$ds_list</p>
					<p><strong>Title Format:</strong><br>"; form_text_box("title_format", "<template_title> (1)", "", "255", "30", "text"); print "</p>
				</td>
			</tr>\n
			";
	}

	if (!isset($ds_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one data template.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='image' src='images/button_yes.gif' alt='Save' align='absmiddle'>";
	}

	print "	<tr>
			<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($ds_array) ? serialize($ds_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				<a href='data_templates.php'><img src='images/button_no.gif' alt='Cancel' align='absmiddle' border='0'></a>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

/* ----------------------------
    template - Data Templates
   ---------------------------- */

function template_rrd_remove() {
	$children = db_fetch_assoc("select id from data_template_rrd where local_data_template_rrd_id=" . $_GET["id"] . " or id=" . $_GET["id"]);

	if (sizeof($children) > 0) {
	foreach ($children as $item) {
		db_execute("delete from data_template_rrd where id=" . $item["id"]);
		db_execute("delete from snmp_query_graph_rrd where data_template_rrd_id=" . $item["id"]);
		db_execute("update graph_templates_item set task_item_id=0 where task_item_id=" . $item["id"]);
	}
	}

	header("Location: data_templates.php?action=template_edit&id=" . $_GET["data_template_id"]);
}

function template_rrd_add() {
	$hash = get_hash_data_template(0, "data_template_item");

	db_execute("insert into data_template_rrd (hash,data_template_id,rrd_maximum,rrd_minimum,rrd_heartbeat,data_source_type_id,
		data_source_name) values ('$hash'," . $_GET["id"] . ",100,0,600,1,'ds')");
	$data_template_rrd_id = db_fetch_insert_id();

	/* add this data template item to each data source using this data template */
	$children = db_fetch_assoc("select local_data_id from data_template_data where data_template_id=" . $_GET["id"] . " and local_data_id>0");

	if (sizeof($children) > 0) {
	foreach ($children as $item) {
		db_execute("insert into data_template_rrd (local_data_template_rrd_id,local_data_id,data_template_id,rrd_maximum,rrd_minimum,rrd_heartbeat,data_source_type_id,
			data_source_name) values ($data_template_rrd_id," . $item["local_data_id"] . "," . $_GET["id"] . ",100,0,600,1,'ds')");
	}
	}

	header("Location: data_templates.php?action=template_edit&id=" . $_GET["id"] . "&view_rrd=$data_template_rrd_id");
}

function template_edit() {
	global $colors, $struct_data_source, $struct_data_source_item, $data_source_types, $fields_data_template_template_edit;

	if (!empty($_GET["id"])) {
		$template_data = db_fetch_row("select * from data_template_data where data_template_id=" . $_GET["id"] . " and local_data_id=0");
		$template = db_fetch_row("select * from data_template where id=" . $_GET["id"]);

		$header_label = "[edit: " . $template["name"] . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>Data Templates</strong> $header_label", "98%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_data_template_template_edit, (isset($template) ? $template : array()), (isset($template_data) ? $template_data : array()), $_GET)
		));

	html_end_box();

	html_start_box("<strong>Data Source</strong>", "98%", $colors["header"], "3", "center", "");

	/* make sure 'data source path' doesn't show up for a template... we should NEVER template this field */
	unset($struct_data_source["data_source_path"]);

	$form_array = array();

	while (list($field_name, $field_array) = each($struct_data_source)) {
		$form_array += array($field_name => $struct_data_source[$field_name]);

		if ($field_array["flags"] == "ALWAYSTEMPLATE") {
			$form_array[$field_name]["description"] = "<em>This field is always templated.</em>";
		}else{
			$form_array[$field_name]["description"] = "";
			$form_array[$field_name]["sub_checkbox"] = array(
				"name" => "t_" . $field_name,
				"friendly_name" => "Use Per-Data Source Value (Ignore this Value)",
				"value" => (isset($template_data{"t_" . $field_name}) ? $template_data{"t_" . $field_name} : "")
				);
		}

		$form_array[$field_name]["value"] = (isset($template_data[$field_name]) ? $template_data[$field_name] : "");
		$form_array[$field_name]["form_id"] = (isset($template_data) ? $template_data["data_template_id"] : "0");
	}

	draw_edit_form(
		array(
			"config" => array(
				),
			"fields" => inject_form_variables($form_array, (isset($template_data) ? $template_data : array()))
			)
		);

	html_end_box();

	/* fetch ALL rrd's for this data source */
	if (!empty($_GET["id"])) {
		$template_data_rrds = db_fetch_assoc("select id,data_source_name from data_template_rrd where data_template_id=" . $_GET["id"] . " and local_data_id=0 order by data_source_name");
	}

	/* select the first "rrd" of this data source by default */
	if (empty($_GET["view_rrd"])) {
		$_GET["view_rrd"] = (isset($template_data_rrds[0]["id"]) ? $template_data_rrds[0]["id"] : "0");
	}

	/* get more information about the rrd we chose */
	if (!empty($_GET["view_rrd"])) {
		$template_rrd = db_fetch_row("select * from data_template_rrd where id=" . $_GET["view_rrd"]);
	}

	$i = 0;
	if (isset($template_data_rrds)) {
		if (sizeof($template_data_rrds) > 1) {

		/* draw the data source tabs on the top of the page */
		print "	<table class='tabs' width='98%' cellspacing='0' cellpadding='3' align='center'>
				<tr>\n";

				foreach ($template_data_rrds as $template_data_rrd) {
					$i++;
					print "	<td " . (($template_data_rrd["id"] == $_GET["view_rrd"]) ? "bgcolor='silver'" : "bgcolor='#DFDFDF'") . " nowrap='nowrap' width='" . ((strlen($template_data_rrd["data_source_name"]) * 9) + 50) . "' align='center' class='tab'>
							<span class='textHeader'><a href='data_templates.php?action=template_edit&id=" . $_GET["id"] . "&view_rrd=" . $template_data_rrd["id"] . "'>$i: " . $template_data_rrd["data_source_name"] . "</a> <a href='data_templates.php?action=rrd_remove&id=" . $template_data_rrd["id"] . "&data_template_id=" . $_GET["id"] . "'><img src='images/delete_icon.gif' border='0' alt='Delete'></a></span>
						</td>\n
						<td width='1'></td>\n";
				}

				print "
				<td></td>\n
				</tr>
			</table>\n";

		}elseif (sizeof($template_data_rrds) == 1) {
			$_GET["view_rrd"] = $template_data_rrds[0]["id"];
		}
	}

	html_start_box("", "98%", $colors["header"], "3", "center", "");

	print "	<tr>
			<td bgcolor='#" . $colors["header"] . "' class='textHeaderDark'>
				<strong>Data Source Item</strong> [" . (isset($template_rrd) ? $template_rrd["data_source_name"] : "") . "]
			</td>
			<td class='textHeaderDark' align='right' bgcolor='" . $colors["header"] . "'>
				" . (!empty($_GET["id"]) ? "<strong><a class='linkOverDark' href='data_templates.php?action=rrd_add&id=" . $_GET["id"] . "'>New</a>&nbsp;</strong>" : "") . "
			</td>
		</tr>\n";

	/* data input fields list */
	if ((empty($template_data["data_input_id"])) || (db_fetch_cell("select type_id from data_input where id=" . $template_data["data_input_id"]) > "1")) {
		unset($struct_data_source_item["data_input_field_id"]);
	}else{
		$struct_data_source_item["data_input_field_id"]["sql"] = "select id,CONCAT(data_name,' - ',name) as name from data_input_fields where data_input_id=" . $template_data["data_input_id"] . " and input_output='out' and update_rra='on' order by data_name,name";
	}

	$form_array = array();

	while (list($field_name, $field_array) = each($struct_data_source_item)) {
		$form_array += array($field_name => $struct_data_source_item[$field_name]);

		$form_array[$field_name]["description"] = "";
		$form_array[$field_name]["value"] = (isset($template_rrd) ? $template_rrd[$field_name] : "");
		$form_array[$field_name]["sub_checkbox"] = array(
			"name" => "t_" . $field_name,
			"friendly_name" => "Use Per-Data Source Value (Ignore this Value)",
			"value" => (isset($template_rrd) ? $template_rrd{"t_" . $field_name} : "")
			);
	}

	draw_edit_form(
		array(
			"config" => array(
				"no_form_tag" => true
				),
			"fields" => $form_array + array(
				"data_template_rrd_id" => array(
					"method" => "hidden",
					"value" => (isset($template_rrd) ? $template_rrd["id"] : "0")
				)
			)
			)
		);

	html_end_box();

	$i = 0;
	if (!empty($_GET["id"])) {
		/* get each INPUT field for this data input source */
		$fields = db_fetch_assoc("select * from data_input_fields where data_input_id=" . $template_data["data_input_id"] . " and input_output='in' order by name");

		html_start_box("<strong>Custom Data</strong> [data input: " . db_fetch_cell("select name from data_input where id=" . $template_data["data_input_id"]) . "]", "98%", $colors["header"], "3", "center", "");

		/* loop through each field found */
		if (sizeof($fields) > 0) {
		foreach ($fields as $field) {
			$data_input_data = db_fetch_row("select t_value,value from data_input_data where data_template_data_id=" . $template_data["id"] . " and data_input_field_id=" . $field["id"]);

			if (sizeof($data_input_data) > 0) {
				$old_value = $data_input_data["value"];
			}else{
				$old_value = "";
			}

			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); ?>
				<td width="50%">
					<strong><?php print $field["name"];?></strong><br>
					<?php form_checkbox("t_value_" . $field["data_name"],$data_input_data["t_value"],"Use Per-Data Source Value (Ignore this Value)","",$_GET["id"]);?>
				</td>
				<td>
					<?php form_text_box("value_" . $field["data_name"],$old_value,"","");?>
					<?php if ((eregi('^' . VALID_HOST_FIELDS . '$', $field["type_code"])) && ($data_input_data["t_value"] == "")) { print "<br><em>Value will be derived from the host if this field is left empty.</em>\n"; } ?>
				</td>
			</tr>
			<?php

			$i++;
		}
		}else{
			print "<tr><td><em>No Input Fields for the Selected Data Input Source</em></td></tr>";
		}

		html_end_box();
	}

	form_save_button("data_templates.php");
}

function template() {
	global $colors, $ds_actions;

	html_start_box("<strong>Data Templates</strong>", "98%", $colors["header"], "5", "center", "data_templates.php?action=template_edit");

	html_header_checkbox(array("Template Name", "Data Input Method", "Status"));

	$template_list = db_fetch_assoc("SELECT DISTINCT
		data_template.id,
		data_template.name,
		data_input.name as data_input_method,
		data_template_data.active as active
		FROM (data_template_data
		INNER JOIN data_template
		ON (data_template.id = data_template_data.data_template_id)
			and (data_template_data.data_template_id = data_template.id))
		INNER JOIN data_input on (data_template_data.data_input_id = data_input.id)
			and (data_template_data.data_input_id = data_input.id)
		ORDER BY data_template.name;");

	$i = 0;
	if (sizeof($template_list) > 0) {
	foreach ($template_list as $template) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="data_templates.php?action=template_edit&id=<?php print $template["id"];?>"><?php print $template["name"];?></a>
			</td>
			<td>
					<?php print $template["data_input_method"];?>
			</td>
			<td>
					<?php if ($template["active"] == "on") print "Active"; else print "Disabled";?>
			</td>
			<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
				<input type='checkbox' style='margin: 0px;' name='chk_<?php print $template["id"];?>' title="<?php print $template["name"];?>">
			</td>
		</tr>
		<?php
		$i++;
	}
	}else{
		print "<tr><td><em>No Data Templates</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($ds_actions);

	print "</form>\n";
}

?>