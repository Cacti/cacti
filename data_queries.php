<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2007 The Cacti Groupi                                |
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
include_once("./lib/data_query.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();

		break;
	case 'item_moveup_dssv':
		data_query_item_moveup_dssv();

		header("Location: data_queries.php?action=item_edit&id=" . $_GET["snmp_query_graph_id"] . "&snmp_query_id=" . $_GET["snmp_query_id"]);
		break;
	case 'item_movedown_dssv':
		data_query_item_movedown_dssv();

		header("Location: data_queries.php?action=item_edit&id=" . $_GET["snmp_query_graph_id"] . "&snmp_query_id=" . $_GET["snmp_query_id"]);
		break;
	case 'item_remove_dssv':
		data_query_item_remove_dssv();

		header("Location: data_queries.php?action=item_edit&id=" . $_GET["snmp_query_graph_id"] . "&snmp_query_id=" . $_GET["snmp_query_id"]);
		break;
	case 'item_moveup_gsv':
		data_query_item_moveup_gsv();

		header("Location: data_queries.php?action=item_edit&id=" . $_GET["snmp_query_graph_id"] . "&snmp_query_id=" . $_GET["snmp_query_id"]);
		break;
	case 'item_movedown_gsv':
		data_query_item_movedown_gsv();

		header("Location: data_queries.php?action=item_edit&id=" . $_GET["snmp_query_graph_id"] . "&snmp_query_id=" . $_GET["snmp_query_id"]);
		break;
	case 'item_remove_gsv':
		data_query_item_remove_gsv();

		header("Location: data_queries.php?action=item_edit&id=" . $_GET["snmp_query_graph_id"] . "&snmp_query_id=" . $_GET["snmp_query_id"]);
		break;
	case 'item_remove':
		data_query_item_remove();

		header("Location: data_queries.php?action=edit&id=" . $_GET["snmp_query_id"]);
		break;
	case 'item_edit':
		include_once("./include/top_header.php");

		data_query_item_edit();

		include_once("./include/bottom_footer.php");
		break;
	case 'remove':
		data_query_remove();

		header ("Location: data_queries.php");
		break;
	case 'edit':
		include_once("./include/top_header.php");

		data_query_edit();

		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");

		data_query();

		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_snmp_query"])) {
		$save["id"] = $_POST["id"];
		$save["hash"] = get_hash_data_query($_POST["id"]);
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save["description"] = form_input_validate($_POST["description"], "description", "", true, 3);
		$save["xml_path"] = form_input_validate($_POST["xml_path"], "xml_path", "", false, 3);
		$save["data_input_id"] = $_POST["data_input_id"];

		if (!is_error_message()) {
			$snmp_query_id = sql_save($save, "snmp_query");

			if ($snmp_query_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if ((is_error_message()) || (empty($_POST["id"]))) {
			header("Location: data_queries.php?action=edit&id=" . (empty($snmp_query_id) ? $_POST["id"] : $snmp_query_id));
		}else{
			header("Location: data_queries.php");
		}
	}elseif (isset($_POST["save_component_snmp_query_item"])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		/* ==================================================== */

		$redirect_back = false;

		$save["id"] = $_POST["id"];
		$save["hash"] = get_hash_data_query($_POST["id"], "data_query_graph");
		$save["snmp_query_id"] = $_POST["snmp_query_id"];
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save["graph_template_id"] = $_POST["graph_template_id"];

		if (!is_error_message()) {
			$snmp_query_graph_id = sql_save($save, "snmp_query_graph");

			if ($snmp_query_graph_id) {
				raise_message(1);

				/* if the user changed the graph template, go through and delete everything that
				was associated with the old graph template */
				if ($_POST["graph_template_id"] != $_POST["_graph_template_id"]) {
					db_execute("delete from snmp_query_graph_rrd_sv where snmp_query_graph_id=$snmp_query_graph_id");
					db_execute("delete from snmp_query_graph_sv where snmp_query_graph_id=$snmp_query_graph_id");
					$redirect_back = true;
				}

				db_execute("delete from snmp_query_graph_rrd where snmp_query_graph_id=$snmp_query_graph_id");

				while (list($var, $val) = each($_POST)) {
					if (eregi("^dsdt_([0-9]+)_([0-9]+)_check", $var)) {
						$data_template_id = ereg_replace("^dsdt_([0-9]+)_([0-9]+).+", "\\1", $var);
						$data_template_rrd_id = ereg_replace("^dsdt_([0-9]+)_([0-9]+).+", "\\2", $var);

						db_execute ("replace into snmp_query_graph_rrd (snmp_query_graph_id,data_template_id,data_template_rrd_id,snmp_field_name) values($snmp_query_graph_id,$data_template_id,$data_template_rrd_id,'" . $_POST{"dsdt_" . $data_template_id . "_" . $data_template_rrd_id . "_snmp_field_output"} . "')");
					}elseif ((eregi("^svds_([0-9]+)_x", $var, $matches)) && (!empty($_POST{"svds_" . $matches[1] . "_text"})) && (!empty($_POST{"svds_" . $matches[1] . "_field"}))) {
						/* suggested values -- data templates */
						$sequence = get_sequence(0, "sequence", "snmp_query_graph_rrd_sv", "snmp_query_graph_id=" . $_POST["id"]  . " and data_template_id=" . $matches[1] . " and field_name='" . $_POST{"svds_" . $matches[1] . "_field"} . "'");
						$hash = get_hash_data_query(0, "data_query_sv_data_source");
						db_execute("insert into snmp_query_graph_rrd_sv (hash,snmp_query_graph_id,data_template_id,sequence,field_name,text) values ('$hash'," . $_POST["id"] . "," . $matches[1] . ",$sequence,'" . $_POST{"svds_" . $matches[1] . "_field"} . "','" . $_POST{"svds_" . $matches[1] . "_text"} . "')");

						$redirect_back = true;
						clear_messages();
					}elseif ((eregi("^svg_x", $var)) && (!empty($_POST{"svg_text"})) && (!empty($_POST{"svg_field"}))) {
						/* suggested values -- graph templates */
						$sequence = get_sequence(0, "sequence", "snmp_query_graph_sv", "snmp_query_graph_id=" . $_POST["id"] . " and field_name='" . $_POST{"svg_field"} . "'");
						$hash = get_hash_data_query(0, "data_query_sv_graph");
						db_execute("insert into snmp_query_graph_sv (hash,snmp_query_graph_id,sequence,field_name,text) values ('$hash'," . $_POST["id"] . ",$sequence,'" . $_POST{"svg_field"} . "','" . $_POST{"svg_text"} . "')");

						$redirect_back = true;
						clear_messages();
					}
				}
			}else{
				raise_message(2);
			}
		}

		if ((is_error_message()) || (empty($_POST["id"])) || ($redirect_back == true)) {
			header("Location: data_queries.php?action=item_edit&id=" . (empty($snmp_query_graph_id) ? $_POST["id"] : $snmp_query_graph_id) . "&snmp_query_id=" . $_POST["snmp_query_id"]);
		}else{
			header("Location: data_queries.php?action=edit&id=" . $_POST["snmp_query_id"]);
		}
	}
}

/* ----------------------------
    Data Query Graph Functions
   ---------------------------- */

function data_query_item_movedown_gsv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("snmp_query_graph_id"));
	/* ==================================================== */

	move_item_down("snmp_query_graph_sv", $_GET["id"], "snmp_query_graph_id=" . $_GET["snmp_query_graph_id"] . " and field_name='" . $_GET["field_name"] . "'");
}

function data_query_item_moveup_gsv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("snmp_query_graph_id"));
	/* ==================================================== */

	move_item_up("snmp_query_graph_sv", $_GET["id"], "snmp_query_graph_id=" . $_GET["snmp_query_graph_id"] . " and field_name='" . $_GET["field_name"] . "'");
}

function data_query_item_remove_gsv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	db_execute("delete from snmp_query_graph_sv where id=" . $_GET["id"]);
}

function data_query_item_movedown_dssv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("data_template_id"));
	input_validate_input_number(get_request_var("snmp_query_graph_id"));
	/* ==================================================== */

	move_item_down("snmp_query_graph_rrd_sv", $_GET["id"], "data_template_id=" . $_GET["data_template_id"] . " and snmp_query_graph_id=" . $_GET["snmp_query_graph_id"] . " and field_name='" . $_GET["field_name"] . "'");
}

function data_query_item_moveup_dssv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("data_template_id"));
	input_validate_input_number(get_request_var("snmp_query_graph_id"));
	/* ==================================================== */

	move_item_up("snmp_query_graph_rrd_sv", $_GET["id"], "data_template_id=" . $_GET["data_template_id"] . " and snmp_query_graph_id=" . $_GET["snmp_query_graph_id"] . " and field_name='" . $_GET["field_name"] . "'");
}

function data_query_item_remove_dssv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	db_execute("delete from snmp_query_graph_rrd_sv where id=" . $_GET["id"]);
}

function data_query_item_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("snmp_query_id"));
	/* ==================================================== */

	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the Data Query Graph <strong>'" . db_fetch_cell("select name from snmp_query_graph where id=" . $_GET["id"]) . "'</strong>?", "data_queries.php?action=edit&id=" . $_GET["snmp_query_id"], "data_queries.php?action=item_remove&id=" . $_GET["id"] . "&snmp_query_id=" . $_GET["snmp_query_id"]);
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from snmp_query_graph where id=" . $_GET["id"]);
		db_execute("delete from snmp_query_graph_rrd where snmp_query_graph_id=" . $_GET["id"]);
		db_execute("delete from snmp_query_graph_rrd_sv where snmp_query_graph_id=" . $_GET["id"]);
		db_execute("delete from snmp_query_graph_sv where snmp_query_graph_id=" . $_GET["id"]);
	}
}

function data_query_item_edit() {
	global $colors, $fields_data_query_item_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("snmp_query_id"));
	/* ==================================================== */

	if (!empty($_GET["id"])) {
		$snmp_query_item = db_fetch_row("select * from snmp_query_graph where id=" . $_GET["id"]);
	}

	$snmp_query = db_fetch_row("select name,xml_path from snmp_query where id=" . $_GET["snmp_query_id"]);
	$header_label = "[edit: " . $snmp_query["name"] . "]";

	html_start_box("<strong>Associated Graph/Data Templates</strong> $header_label", "98%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_data_query_item_edit, (isset($snmp_query_item) ? $snmp_query_item : array()), $_GET)
		));

	html_end_box();

	if (!empty($snmp_query_item["id"])) {
		html_start_box("<strong>Associated Data Templates</strong>", "98%", $colors["header"], "3", "center", "");

		$data_templates = db_fetch_assoc("select
			data_template.id,
			data_template.name
			from (data_template, data_template_rrd, graph_templates_item)
			where graph_templates_item.task_item_id=data_template_rrd.id
			and data_template_rrd.data_template_id=data_template.id
			and data_template_rrd.local_data_id=0
			and graph_templates_item.local_graph_id=0
			and graph_templates_item.graph_template_id=" . $snmp_query_item["graph_template_id"] . "
			group by data_template.id
			order by data_template.name");

		$i = 0;
		if (sizeof($data_templates) > 0) {
		foreach ($data_templates as $data_template) {
			print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
					<td><span style='color: white; font-weight: bold;'>Data Template - " . $data_template["name"] . "</span></td>
				</tr>";

			$data_template_rrds = db_fetch_assoc("select
				data_template_rrd.id,
				data_template_rrd.data_source_name,
				snmp_query_graph_rrd.snmp_field_name,
				snmp_query_graph_rrd.snmp_query_graph_id
				from data_template_rrd
				left join snmp_query_graph_rrd on (snmp_query_graph_rrd.data_template_rrd_id=data_template_rrd.id and snmp_query_graph_rrd.snmp_query_graph_id=" . $_GET["id"] . " and snmp_query_graph_rrd.data_template_id=" . $data_template["id"] . ")
				where data_template_rrd.data_template_id=" . $data_template["id"] . "
				and data_template_rrd.local_data_id=0
				order by data_template_rrd.data_source_name");

			$i = 0;
			if (sizeof($data_template_rrds) > 0) {
			foreach ($data_template_rrds as $data_template_rrd) {
				if (empty($data_template_rrd["snmp_query_graph_id"])) {
					$old_value = "";
				}else{
					$old_value = "on";
				}

				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
				?>
					<td>
						<table cellspacing="0" cellpadding="0" border="0" width="100%">
							<tr>
								<td width="200">
									<strong>Data Source:</strong>
								</td>
								<td width="200">
									<?php print $data_template_rrd["data_source_name"];?>
								</td>
								<td width="1">
									<?php
									$snmp_queries = get_data_query_array($_GET["snmp_query_id"]);
									$xml_outputs = array();

									while (list($field_name, $field_array) = each($snmp_queries["fields"])) {
										if ($field_array["direction"] == "output") {
											$xml_outputs[$field_name] = $field_name . " (" . $field_array["name"] . ")";;
										}
									}

									form_dropdown("dsdt_" . $data_template["id"] . "_" . $data_template_rrd["id"] . "_snmp_field_output",$xml_outputs,"","",$data_template_rrd["snmp_field_name"],"","");?>
								</td>
								<td align="right">
									<?php form_checkbox("dsdt_" . $data_template["id"] . "_" . $data_template_rrd["id"] . "_check", $old_value, "", "",$_GET["id"]); print "<br>";?>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<?php
			}
			}
		}
		}

		html_end_box();

		html_start_box("<strong>Suggested Values</strong>", "98%", $colors["header"], "0", "center", "");

		reset($data_templates);

		/* suggested values for data templates */
		if (sizeof($data_templates) > 0) {
		foreach ($data_templates as $data_template) {
			$suggested_values = db_fetch_assoc("select
				text,
				field_name,
				id
				from snmp_query_graph_rrd_sv
				where snmp_query_graph_id=" . $_GET["id"] . "
				and data_template_id=" . $data_template["id"] . "
				order by field_name,sequence");

			print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
					<td style='padding: 3px;'><span style='color: white; font-weight: bold;'>Data Template - " . $data_template["name"] . "</span></td>
				</tr>";

			$i = 0;
			if (sizeof($suggested_values) > 0) {
				print "<tr><td><table cellspacing='0' cellpadding='3' border='0' width='100%'>\n";

				foreach ($suggested_values as $suggested_value) {
					form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
					?>
						<td width="120">
							<strong><?php print $suggested_value["field_name"];?></strong>
						</td>
						<td>
							<?php print $suggested_value["text"];?>
						</td>
						<td width="70">
							<a href="data_queries.php?action=item_movedown_dssv&snmp_query_graph_id=<?php print $_GET["id"];?>&id=<?php print $suggested_value["id"];?>&snmp_query_id=<?php print $_GET["snmp_query_id"];?>&data_template_id=<?php print $data_template["id"];?>&field_name=<?php print $suggested_value["field_name"];?>"><img src="images/move_down.gif" border="0" alt="Move Down"></a>
							<a href="data_queries.php?action=item_moveup_dssv&snmp_query_graph_id=<?php print $_GET["id"];?>&id=<?php print $suggested_value["id"];?>&snmp_query_id=<?php print $_GET["snmp_query_id"];?>&data_template_id=<?php print $data_template["id"];?>&field_name=<?php print $suggested_value["field_name"];?>"><img src="images/move_up.gif" border="0" alt="Move Up"></a>
						</td>
						<td align="right">
							<a href="data_queries.php?action=item_remove_dssv&snmp_query_graph_id=<?php print $_GET["id"];?>&id=<?php print $suggested_value["id"];?>&snmp_query_id=<?php print $_GET["snmp_query_id"];?>&data_template_id=<?php print $data_template["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>
						</td>
					</tr>
					<?php
				}

				print "</table></td></tr>\n";
			}

			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
			?>
				<td>
					<table cellspacing="0" cellpadding="3" border="0" width="100%">
						<tr>
							<td width="1">
								<input type="text" name="svds_<?php print $data_template["id"];?>_text" size="30">
							</td>
							<td width="220" nowrap>
								&nbsp;Field Name:&nbsp;<input type="text" name="svds_<?php print $data_template["id"];?>_field" size="15">
							</td>
							<td>
								&nbsp;<input type="image" src="images/button_add.gif" name="svds_<?php print $data_template["id"];?>" alt="Add" align="absmiddle">
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<?php
		}
		}

		/* suggested values for graphs templates */
		$suggested_values = db_fetch_assoc("select
			text,
			field_name,
			id
			from snmp_query_graph_sv
			where snmp_query_graph_id=" . $_GET["id"] . "
			order by field_name,sequence");

		print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
				<td style='padding: 3px;'><span style='color: white; font-weight: bold;'>Graph Template - " . db_fetch_cell("select name from graph_templates where id=" . $snmp_query_item["graph_template_id"]) . "</span></td>
			</tr>";

		$i = 0;
		if (sizeof($suggested_values) > 0) {
			print "<tr><td><table cellspacing='0' cellpadding='3' border='0' width='100%'>\n";

			foreach ($suggested_values as $suggested_value) {
				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
				?>
					<td width="120">
						<strong><?php print $suggested_value["field_name"];?></strong>
					</td>
					<td>
						<?php print $suggested_value["text"];?>
					</td>
					<td width="70">
						<a href="data_queries.php?action=item_movedown_gsv&snmp_query_graph_id=<?php print $_GET["id"];?>&id=<?php print $suggested_value["id"];?>&snmp_query_id=<?php print $_GET["snmp_query_id"];?>&field_name=<?php print $suggested_value["field_name"];?>"><img src="images/move_down.gif" border="0" alt="Move Down"></a>
						<a href="data_queries.php?action=item_moveup_gsv&snmp_query_graph_id=<?php print $_GET["id"];?>&id=<?php print $suggested_value["id"];?>&snmp_query_id=<?php print $_GET["snmp_query_id"];?>&field_name=<?php print $suggested_value["field_name"];?>"><img src="images/move_up.gif" border="0" alt="Move Up"></a>
					</td>
					<td align="right">
						<a href="data_queries.php?action=item_remove_gsv&snmp_query_graph_id=<?php print $_GET["id"];?>&id=<?php print $suggested_value["id"];?>&snmp_query_id=<?php print $_GET["snmp_query_id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>
					</td>
				</tr>
				<?php
			}

			print "</table></td></tr>\n";
		}

		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
		?>
			<td>
				<table cellspacing="0" cellpadding="3" border="0" width="100%">
					<tr>
						<td width="1">
							<input type="text" name="svg_text" size="30">
						</td>
						<td width="220" nowrap>
							&nbsp;Field Name:&nbsp;<input type="text" name="svg_field" size="15">
						</td>
						<td>
							&nbsp;<input type="image" src="images/button_add.gif" name="svg" alt="Add" align="absmiddle">
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<?php

		html_end_box();
	}

	form_save_button("data_queries.php?action=edit&id=" . $_GET["snmp_query_id"]);
}

/* ---------------------
    Data Query Functions
   --------------------- */

function data_query_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the Data Query <strong>'" . db_fetch_cell("select name from snmp_query where id=" . $_GET["id"]) . "'</strong>?", "data_queries.php", "data_queries.php?action=remove&id=" . $_GET["id"]);
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		$snmp_query_graph = db_fetch_assoc("select id from snmp_query_graph where snmp_query_id=" . $_GET["id"]);

		if (sizeof($snmp_query_graph) > 0) {
		foreach ($snmp_query_graph as $item) {
			db_execute("delete from snmp_query_graph_rrd where snmp_query_graph_id=" . $item["id"]);
		}
		}

		db_execute("delete from snmp_query where id=" . $_GET["id"]);
		db_execute("delete from snmp_query_graph where snmp_query_id=" . $_GET["id"]);
		db_execute("delete from host_template_snmp_query where snmp_query_id=" . $_GET["id"]);
		db_execute("delete from host_snmp_query where snmp_query_id=" . $_GET["id"]);
		db_execute("delete from host_snmp_cache where snmp_query_id=" . $_GET["id"]);
	}
}

function data_query_edit() {
	global $colors, $fields_data_query_edit, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if (!empty($_GET["id"])) {
		$snmp_query = db_fetch_row("select * from snmp_query where id=" . $_GET["id"]);
		$header_label = "[edit: " . $snmp_query["name"] . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>Data Queries</strong> $header_label", "98%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_data_query_edit, (isset($snmp_query) ? $snmp_query : array()))
		));

	html_end_box();

	if (!empty($snmp_query["id"])) {
		$xml_filename = str_replace("<path_cacti>", $config["base_path"], $snmp_query["xml_path"]);

		if ((file_exists($xml_filename)) && (is_file($xml_filename))) {
			$text = "<font color='#0d7c09'><strong>Successfully located XML file</strong></font>";
			$xml_file_exists = true;
		}else{
			$text = "<font color='#ff0000'><strong>Could not locate XML file.</strong></font>";
			$xml_file_exists = false;
		}

		html_start_box("", "98%", "aaaaaa", "3", "center", "");
		print "<tr bgcolor='#f5f5f5'><td>$text</td></tr>";
		html_end_box();

		if ($xml_file_exists == true) {
			html_start_box("<strong>Associated Graph Templates</strong>", "98%", $colors["header"], "3", "center", "data_queries.php?action=item_edit&snmp_query_id=" . $snmp_query["id"]);

			print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
					<td><span style='color: white; font-weight: bold;'>Name</span></td>
					<td><span style='color: white; font-weight: bold;'>Graph Template Name</span></td>
					<td></td>
				</tr>";

			$snmp_query_graphs = db_fetch_assoc("select
				snmp_query_graph.id,
				graph_templates.name as graph_template_name,
				snmp_query_graph.name
				from snmp_query_graph
				left join graph_templates on (snmp_query_graph.graph_template_id=graph_templates.id)
				where snmp_query_graph.snmp_query_id=" . $snmp_query["id"] . "
				order by snmp_query_graph.name");

			$i = 0;
			if (sizeof($snmp_query_graphs) > 0) {
			foreach ($snmp_query_graphs as $snmp_query_graph) {
				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
				?>
					<td>
						<strong><a href="data_queries.php?action=item_edit&id=<?php print $snmp_query_graph["id"];?>&snmp_query_id=<?php print $snmp_query["id"];?>"><?php print $snmp_query_graph["name"];?></a></strong>
					</td>
					<td>
						<?php print $snmp_query_graph["graph_template_name"];?>
					</td>
					<td align="right">
						<a href="data_queries.php?action=item_remove&id=<?php print $snmp_query_graph["id"];?>&snmp_query_id=<?php print $snmp_query["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>
					</td>
				</tr>
				<?php
			}
			}else{
				print "<tr><td><em>No Graph Templates Defined.</em></td></tr>";
			}

			html_end_box();
		}
	}

	form_save_button("data_queries.php");
}

function data_query() {
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
	load_current_session_value("sort_column", "sess_data_queries_sort_column", "name");
	load_current_session_value("sort_direction", "sess_data_queries_sort_direction", "ASC");

	html_start_box("<strong>Data Queries</strong>", "98%", $colors["header"], "3", "center", "data_queries.php?action=edit");

	$display_text = array(
		"name" => array("Name", "ASC"),
		"data_input_method" => array("Data Input Method", "ASC"));

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"], 3);

	$snmp_queries = db_fetch_assoc("SELECT
			snmp_query.id,
			snmp_query.name,
			data_input.name AS data_input_method
			FROM snmp_query INNER JOIN data_input ON (snmp_query.data_input_id = data_input.id)
			ORDER BY " . $_REQUEST['sort_column'] . " " . $_REQUEST['sort_direction']);

	$i = 0;
	if (sizeof($snmp_queries) > 0) {
	foreach ($snmp_queries as $snmp_query) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="data_queries.php?action=edit&id=<?php print $snmp_query["id"];?>"><?php print $snmp_query["name"];?></a>
			</td>
			<td>
				<?php print $snmp_query["data_input_method"]; ?>
			</td>
			<td align="right">
				<a href="data_queries.php?action=remove&id=<?php print $snmp_query["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>
			</td>
		</tr>
	<?php
	}
	}
	html_end_box();
}
?>