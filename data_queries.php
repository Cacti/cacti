<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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

define("MAX_DISPLAY_PAGES", 21);

$dq_actions = array(
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
	case 'item_moveup_dssv':
		data_query_item_moveup_dssv();

		header("Location: data_queries.php?header=false&action=item_edit&id=" . $_REQUEST["snmp_query_graph_id"] . "&snmp_query_id=" . $_REQUEST["snmp_query_id"]);
		break;
	case 'item_movedown_dssv':
		data_query_item_movedown_dssv();

		header("Location: data_queries.php?header=false&action=item_edit&id=" . $_REQUEST["snmp_query_graph_id"] . "&snmp_query_id=" . $_REQUEST["snmp_query_id"]);
		break;
	case 'item_remove_dssv':
		data_query_item_remove_dssv();

		header("Location: data_queries.php?header=false&action=item_edit&id=" . $_REQUEST["snmp_query_graph_id"] . "&snmp_query_id=" . $_REQUEST["snmp_query_id"]);
		break;
	case 'item_moveup_gsv':
		data_query_item_moveup_gsv();

		header("Location: data_queries.php?header=false&action=item_edit&id=" . $_REQUEST["snmp_query_graph_id"] . "&snmp_query_id=" . $_REQUEST["snmp_query_id"]);
		break;
	case 'item_movedown_gsv':
		data_query_item_movedown_gsv();

		header("Location: data_queries.php?header=false&action=item_edit&id=" . $_REQUEST["snmp_query_graph_id"] . "&snmp_query_id=" . $_REQUEST["snmp_query_id"]);
		break;
	case 'item_remove_gsv':
		data_query_item_remove_gsv();

		header("Location: data_queries.php?header=false&action=item_edit&id=" . $_REQUEST["snmp_query_graph_id"] . "&snmp_query_id=" . $_REQUEST["snmp_query_id"]);
		break;
	case 'item_remove':
		data_query_item_remove();

		header("Location: data_queries.php?header=false&action=edit&id=" . $_REQUEST["snmp_query_id"]);
		break;
	case 'item_edit':
		top_header();

		data_query_item_edit();

		bottom_footer();
		break;
	case 'remove':
		data_query_remove();

		header ("Location: data_queries.php");
		break;
	case 'edit':
		top_header();

		data_query_edit();

		bottom_footer();
		break;
	default:
		top_header();

		data_query();

		bottom_footer();
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

		header("Location: data_queries.php?action=edit&id=" . (empty($snmp_query_id) ? $_POST["id"] : $snmp_query_id));
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
					db_execute("DELETE FROM snmp_query_graph_rrd_sv WHERE snmp_query_graph_id=$snmp_query_graph_id");
					db_execute("DELETE FROM snmp_query_graph_sv WHERE snmp_query_graph_id=$snmp_query_graph_id");
					$redirect_back = true;
				}

				db_execute("DELETE FROM snmp_query_graph_rrd WHERE snmp_query_graph_id=$snmp_query_graph_id");

				while (list($var, $val) = each($_POST)) {
					if (preg_match("/^dsdt_([0-9]+)_([0-9]+)_check/i", $var)) {
						$data_template_id = preg_replace("/^dsdt_([0-9]+)_([0-9]+).+/", "\\1", $var);
						$data_template_rrd_id = preg_replace("/^dsdt_([0-9]+)_([0-9]+).+/", "\\2", $var);

						db_execute ("REPLACE INTO snmp_query_graph_rrd (snmp_query_graph_id,data_template_id,data_template_rrd_id,snmp_field_name) values($snmp_query_graph_id,$data_template_id,$data_template_rrd_id,'" . $_POST{"dsdt_" . $data_template_id . "_" . $data_template_rrd_id . "_snmp_field_output"} . "')");
					}elseif ((preg_match("/^svds_([0-9]+)_x/i", $var, $matches)) && (!empty($_POST{"svds_" . $matches[1] . "_text"})) && (!empty($_POST{"svds_" . $matches[1] . "_field"}))) {
						/* suggested values -- data templates */
						$sequence = get_sequence(0, "sequence", "snmp_query_graph_rrd_sv", "snmp_query_graph_id=" . $_POST["id"]  . " AND data_template_id=" . $matches[1] . " AND field_name='" . $_POST{"svds_" . $matches[1] . "_field"} . "'");
						$hash = get_hash_data_query(0, "data_query_sv_data_source");
						db_execute("INSERT INTO snmp_query_graph_rrd_sv (hash,snmp_query_graph_id,data_template_id,sequence,field_name,text) VALUES ('$hash'," . $_POST["id"] . "," . $matches[1] . ",$sequence,'" . $_POST{"svds_" . $matches[1] . "_field"} . "','" . $_POST{"svds_" . $matches[1] . "_text"} . "')");

						$redirect_back = true;
						clear_messages();
					}elseif ((preg_match("/^svg_x/i", $var)) && (!empty($_POST{"svg_text"})) && (!empty($_POST{"svg_field"}))) {
						/* suggested values -- graph templates */
						$sequence = get_sequence(0, "sequence", "snmp_query_graph_sv", "snmp_query_graph_id=" . $_POST["id"] . " AND field_name='" . $_POST{"svg_field"} . "'");
						$hash = get_hash_data_query(0, "data_query_sv_graph");
						db_execute("INSERT INTO snmp_query_graph_sv (hash,snmp_query_graph_id,sequence,field_name,text) VALUES ('$hash'," . $_POST["id"] . ",$sequence,'" . $_POST{"svg_field"} . "','" . $_POST{"svg_text"} . "')");

						$redirect_back = true;
						clear_messages();
					}
				}

				if (isset($_POST['header']) && $_POST['header'] == 'false') {
					$header = '&header=false';
				}else{
					$header = '';
				}
			}else{
				raise_message(2);
				$header = '';
			}
		}

		header("Location: data_queries.php?action=item_edit" . $header . "&id=" . (empty($snmp_query_graph_id) ? $_POST["id"] : $snmp_query_graph_id) . "&snmp_query_id=" . $_POST["snmp_query_id"]);
	}
}

function form_actions() {
	global $dq_actions;

	/* ================= input validation ================= */
	input_validate_input_regex(get_request_var_post('drp_action'), "^([a-zA-Z0-9_]+)$");
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* delete */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				 data_query_remove($selected_items[$i]);
			}
		}

		header("Location: data_queries.php");
		exit;
	}

	/* setup some variables */
	$dq_list = ""; $i = 0;

	/* loop through each of the data queries and process them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$dq_list .= "<li>" . htmlspecialchars(db_fetch_cell("SELECT snmp_query.name FROM snmp_query WHERE id='" . $matches[1] . "'")) . "<br>";
			$dq_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	html_start_box("<strong>" . $dq_actions{$_POST["drp_action"]} . "</strong>", "60%", "", "3", "center", "");

	print "<form action='data_queries.php' method='post'>\n";

	if (isset($dq_array) && sizeof($dq_array)) {
		if ($_POST["drp_action"] == "1") { /* delete */
			$graphs = array();

			print "
				<tr>
					<td class='textArea' class='odd'>
						<p>When you click \"Continue\" the following Data Querie(s) will be deleted.</p>
						<p><ul>$dq_list</ul></p>
					</td>
				</tr>\n";
		}

		$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Data Querie(s)'>";
	}else{
		print "<tr><td class='odd'><span class='textError'>You must select at least one data query.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($dq_array) ? serialize($dq_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	bottom_footer();
}

/* ----------------------------
    Data Query Graph Functions
   ---------------------------- */

function data_query_item_movedown_gsv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	input_validate_input_number(get_request_var_request("snmp_query_graph_id"));
	/* ==================================================== */

	move_item_down("snmp_query_graph_sv", $_REQUEST["id"], "snmp_query_graph_id=" . $_REQUEST["snmp_query_graph_id"] . " AND field_name='" . $_REQUEST["field_name"] . "'");
}

function data_query_item_moveup_gsv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	input_validate_input_number(get_request_var_request("snmp_query_graph_id"));
	/* ==================================================== */

	move_item_up("snmp_query_graph_sv", $_REQUEST["id"], "snmp_query_graph_id=" . $_REQUEST["snmp_query_graph_id"] . " AND field_name='" . $_REQUEST["field_name"] . "'");
}

function data_query_item_remove_gsv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	/* ==================================================== */

	db_execute("DELETE FROM snmp_query_graph_sv WHERE id=" . $_REQUEST["id"]);
}

function data_query_item_movedown_dssv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	input_validate_input_number(get_request_var_request("data_template_id"));
	input_validate_input_number(get_request_var_request("snmp_query_graph_id"));
	/* ==================================================== */

	move_item_down("snmp_query_graph_rrd_sv", $_REQUEST["id"], "data_template_id=" . $_REQUEST["data_template_id"] . " AND snmp_query_graph_id=" . $_REQUEST["snmp_query_graph_id"] . " AND field_name='" . $_REQUEST["field_name"] . "'");
}

function data_query_item_moveup_dssv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	input_validate_input_number(get_request_var_request("data_template_id"));
	input_validate_input_number(get_request_var_request("snmp_query_graph_id"));
	/* ==================================================== */

	move_item_up("snmp_query_graph_rrd_sv", $_REQUEST["id"], "data_template_id=" . $_REQUEST["data_template_id"] . " AND snmp_query_graph_id=" . $_REQUEST["snmp_query_graph_id"] . " AND field_name='" . $_REQUEST["field_name"] . "'");
}

function data_query_item_remove_dssv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	/* ==================================================== */

	db_execute("DELETE FROM snmp_query_graph_rrd_sv WHERE id=" . $_REQUEST["id"]);
}

function data_query_item_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	input_validate_input_number(get_request_var_request("snmp_query_id"));
	/* ==================================================== */

	if ((read_config_option("deletion_verification") == "on") && (!isset($_REQUEST["confirm"]))) {
		top_header();

		form_confirm("Are You Sure?", "Are you sure you want to delete the Data Query Graph <strong>'" . htmlspecialchars(db_fetch_cell("SELECT name FROM snmp_query_graph WHERE id=" . $_REQUEST["id"]), ENT_QUOTES) . "'</strong>?", htmlspecialchars("data_queries.php?action=edit&id=" . $_REQUEST["snmp_query_id"]), htmlspecialchars("data_queries.php?action=item_remove&id=" . $_REQUEST["id"] . "&snmp_query_id=" . $_REQUEST["snmp_query_id"]));

		bottom_footer();
		exit;
	}

	if ((read_config_option("deletion_verification") == "") || (isset($_REQUEST["confirm"]))) {
		db_execute("DELETE FROM snmp_query_graph WHERE id=" . $_REQUEST["id"]);
		db_execute("DELETE FROM snmp_query_graph_rrd WHERE snmp_query_graph_id=" . $_REQUEST["id"]);
		db_execute("DELETE FROM snmp_query_graph_rrd_sv WHERE snmp_query_graph_id=" . $_REQUEST["id"]);
		db_execute("DELETE FROM snmp_query_graph_sv WHERE snmp_query_graph_id=" . $_REQUEST["id"]);
	}
}

function data_query_item_edit() {
	global $fields_data_query_item_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	input_validate_input_number(get_request_var_request("snmp_query_id"));
	/* ==================================================== */

	if (!empty($_REQUEST["id"])) {
		$snmp_query_item = db_fetch_row("SELECT * FROM snmp_query_graph WHERE id=" . $_REQUEST["id"]);
	}

	$snmp_query = db_fetch_row("SELECT name,xml_path FROM snmp_query WHERE id=" . $_REQUEST["snmp_query_id"]);
	$header_label = "[edit: " . htmlspecialchars($snmp_query["name"]) . "]";

	html_start_box("<strong>Associated Graph/Data Templates</strong> $header_label", "100%", "", "3", "center", "");

	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_data_query_item_edit, (isset($snmp_query_item) ? $snmp_query_item : array()), $_REQUEST)
		));

	html_end_box();

	?>
	<script type='text/javascript'>
	$('#graph_template_id').change(function() {
		$('#name').val($(this).children(':selected').text());
	});
	</script>
	<?php

	if (!empty($snmp_query_item["id"])) {
		html_start_box("<strong>Associated Data Templates</strong>", "100%", "", "3", "center", "");

		$data_templates = db_fetch_assoc("SELECT
			data_template.id,
			data_template.name
			FROM (data_template, data_template_rrd, graph_templates_item)
			WHERE graph_templates_item.task_item_id=data_template_rrd.id
			AND data_template_rrd.data_template_id=data_template.id
			AND data_template_rrd.local_data_id=0
			AND graph_templates_item.local_graph_id=0
			AND graph_templates_item.graph_template_id=" . $snmp_query_item["graph_template_id"] . "
			GROUP BY data_template.id
			ORDER BY data_template.name");

		$i = 0;
		if (sizeof($data_templates)) {
		foreach ($data_templates as $data_template) {
			print "<tr class='tableHeader'>
					<th>Data Template - " . $data_template["name"] . "</th>
				</tr>";

			$data_template_rrds = db_fetch_assoc("SELECT
				data_template_rrd.id,
				data_template_rrd.data_source_name,
				snmp_query_graph_rrd.snmp_field_name,
				snmp_query_graph_rrd.snmp_query_graph_id
				FROM data_template_rrd
				LEFT JOIN snmp_query_graph_rrd on (snmp_query_graph_rrd.data_template_rrd_id=data_template_rrd.id AND snmp_query_graph_rrd.snmp_query_graph_id=" . $_REQUEST["id"] . " AND snmp_query_graph_rrd.data_template_id=" . $data_template["id"] . ")
				WHERE data_template_rrd.data_template_id=" . $data_template["id"] . "
				AND data_template_rrd.local_data_id=0
				ORDER BY data_template_rrd.data_source_name");

			$i = 0;
			if (sizeof($data_template_rrds) > 0) {
			foreach ($data_template_rrds as $data_template_rrd) {
				if (empty($data_template_rrd["snmp_query_graph_id"])) {
					$old_value = "";
				}else{
					$old_value = "on";
				}

				form_alternate_row();
				?>
					<td>
						<table cellspacing="0" cellpadding="2" border="0">
							<tr>
								<td width="200">
									<strong>Data Source:</strong>
								</td>
								<td width="200">
									<?php print $data_template_rrd["data_source_name"];?>
								</td>
								<td>
									<?php
									$snmp_queries = get_data_query_array($_REQUEST["snmp_query_id"]);
									$xml_outputs = array();

									while (list($field_name, $field_array) = each($snmp_queries["fields"])) {
										if ($field_array["direction"] == "output") {
											$xml_outputs[$field_name] = $field_name . " (" . $field_array["name"] . ")";;
										}
									}

									form_dropdown("dsdt_" . $data_template["id"] . "_" . $data_template_rrd["id"] . "_snmp_field_output",$xml_outputs,"","",$data_template_rrd["snmp_field_name"],"","");?>
								</td>
								<td align="right">
									<?php form_checkbox("dsdt_" . $data_template["id"] . "_" . $data_template_rrd["id"] . "_check", $old_value, "", "", "", $_REQUEST["id"]); print "<br>";?>
								</td>
							</tr>
						</table>
					</td>
				<?php
				form_end_row();
			}
			}
		}
		}

		html_end_box();

		html_start_box("<strong>Suggested Values - Graph Names</strong>", "100%", "", "3", "center", "");

		/* suggested values for graphs templates */
		$suggested_values = db_fetch_assoc('SELECT text, field_name, id
			FROM snmp_query_graph_sv
			WHERE snmp_query_graph_id=' . $_REQUEST['id'] . '
			ORDER BY field_name,sequence');

		html_header(array('Name', '', 'Equation'), 2);

		if (sizeof($suggested_values)) {
			foreach ($suggested_values as $suggested_value) {
				form_alternate_row();
				?>
					<td width='120'>
						<strong><?php print htmlspecialchars($suggested_value['field_name']);?></strong>
					</td>
					<td width='40' align='center'>
						<span class='remover' style='pointer:cursor;' href='<?php print htmlspecialchars('data_queries.php?action=item_movedown_gsv&snmp_query_graph_id=' . $_REQUEST['id'] . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id'] . '&field_name=' . $suggested_value['field_name']);?>'><img src='images/move_down.gif' border='0' alt='Move Down'></span>
						<span class='remover' style='pointer:cursor;' href='<?php print htmlspecialchars('data_queries.php?action=item_moveup_gsv&snmp_query_graph_id=' . $_REQUEST['id'] . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id'] . '&field_name=' . $suggested_value['field_name']);?>'><img src='images/move_up.gif' border='0' alt='Move Up'></span>
					</td>
					<td>
						<?php print htmlspecialchars($suggested_value['text']);?>
					</td>
					<td align='right'>
						<span class='remover' style='pointer:cursor;' href='<?php print htmlspecialchars('data_queries.php?action=item_remove_gsv&snmp_query_graph_id=' . $_REQUEST['id'] . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id']);?>'><img src='images/delete_icon.gif' style='height:10px;width:10px;' border='0' alt='Delete'></span>
					</td>
				</tr>
				<?php
			}
		}

		form_alternate_row();
		?>
		<td colspan='4'>
			<table cellspacing='0' cellpadding='3' border='0'>
				<tr>
					<td style='white-space:nowrap;'>
						Field Name:
					</td>
					<td>
						<input type='text' id='svg_field' size='15'>
					</td>
					<td style='white-space:nowrap;'>
						Suggested Value:
					</td>
					<td width='1'>
						<input type='text' id='svg_text' size='60'>
					</td>
					<td>
						<input id='svg_x' type='button' name='svg_x' value='Add' title='Add Graph Title Suggested Name'>
					</td>
				</tr>
			</table>
		</td>
		<?php
		form_end_row();

		html_end_box();
		html_start_box("<strong>Suggested Values - Data Source Names</strong>", "100%", "", "3", "center", "");

		reset($data_templates);

		/* suggested values for data templates */
		if (sizeof($data_templates)) {
		foreach ($data_templates as $data_template) {
			$suggested_values = db_fetch_assoc('SELECT text, field_name, id
				FROM snmp_query_graph_rrd_sv
				WHERE snmp_query_graph_id=' . $_REQUEST['id'] . '
				AND data_template_id=' . $data_template['id'] . '
				ORDER BY field_name,sequence');

			html_header(array('Name', '', 'Equation'),2);

			if (sizeof($suggested_values)) {
				$prev_name = '';
				foreach ($suggested_values as $suggested_value) {
					form_alternate_row();
					?>
						<td width='120'>
							<strong><?php print htmlspecialchars($suggested_value['field_name']);?></strong>
						</td>
						<td width='40' align='center'>
							<span class='remover' style='pointer:cursor;' href='<?php print htmlspecialchars('data_queries.php?action=item_movedown_dssv&snmp_query_graph_id=' . $_REQUEST['id'] . '&id='. $suggested_value['id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id'] . '&data_template_id=' . $data_template['id'] . '&field_name=' . $suggested_value['field_name']);?>'><img src='images/move_down.gif' border='0' alt='Move Down'></span>
							<span class='remover' style='pointer:cursor;' href='<?php print htmlspecialchars('data_queries.php?action=item_moveup_dssv&snmp_query_graph_id=' . $_REQUEST['id'] . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id'] . '&data_template_id=' . $data_template['id'] . '&field_name=' . $suggested_value['field_name']);?>'><img src='images/move_up.gif' border='0' alt='Move Up'></span>
						</td>
						<td style='white-space:nowrap'>
							<?php print htmlspecialchars($suggested_value['text']);?>
						</td>
						<td align='right'>
							<span class='remover' style='pointer:cursor;' href='<?php print htmlspecialchars('data_queries.php?action=item_remove_dssv&snmp_query_graph_id=' . $_REQUEST['id'] . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id'] . '&data_template_id=' . $data_template['id']);?>'><img src='images/delete_icon.gif' width='10' style='height:10px;width:10px;' border='0' alt='Delete'></span>
						</td>
					</tr>
					<?php
					$prev_name = $suggested_value['field_name'];
				}
			}

			form_alternate_row();
			?>
			<td colspan='4'>
				<table cellspacing='0' cellpadding='3' border='0'>
					<tr>
						<td style='white-space:nowrap;'>
							Field Name:
						</td>
						<td>
							<input id='svds_field' type='text' name='svds_<?php print $data_template['id'];?>_field' size='15'>
						</td>
						<td style='white-space:nowrap;'>
							Suggested Value:
						</td>
						<td width='1'>
							<input id='svds_text' type='text' name='svds_<?php print $data_template['id'];?>_text' size='60'>
						</td>
						<td>
							<input id='svds_x' type='button' name='svds_<?php print $data_template["id"];?>_x' value='Add' title='Add Data Source Name Suggested Name'>
						</td>
					</tr>
				</table>
				<script type='text/javascript'>
				$('.remover').click(function() {
					href=$(this).attr('href');
					$.get(href, function(data) {
						$('form[action="data_queries.php"]').unbind();
						$('#main').html(data);
						applySkin();
					});
				});

				$('input[id="svg_x"]').click(function() {
					$.post('data_queries.php', { 
						'_graph_template_id':$('#_graph_template_id').val(), 
						'action':'save',
						'name':$('#name').val(),
						'graph_template_id':$('#graph_template_id').val(), 
						'id':$('#id').val(),
						'header':'false',
						'save_component_snmp_query_item':'1', 
						'snmp_query_id':$('#snmp_query_id').val(), 
						'svg_field':$('#svg_field').val(), 
						'svg_text':$('#svg_text').val(), 
						'svg_x':'Add'
					}).done(function(data) {
						$('#main').html(data);
						applySkin();
					});
				});

				$('input[id="svds_x"]').click(function() {
					var svds_text_name=$('#svds_text').attr('name');
					var svds_field_name=$('#svds_field').attr('name');
					var svds_x_name=$('#svds_x').attr('name');
					var jSON = $.parseJSON('{ ' + 
						'"_graph_template_id":"'+$('#_graph_template_id').val() + '", ' +
						'"action":"save", ' +
						'"name":"'+$('#name').val() + '", ' +
						'"graph_template_id":"'+$('#graph_template_id').val() + '", ' +
						'"id":"'+$('#id').val() + '", ' +
						'"header":"false", ' +
						'"save_component_snmp_query_item":"1", ' +
						'"snmp_query_id":"'+$('#snmp_query_id').val() + '", ' +
						'"'+svds_field_name+'":"'+$('#svds_field').val() + '", ' +
						'"'+svds_text_name+'":"'+$('#svds_text').val() + '", ' +
						'"'+svds_x_name+'":"Add" }');

					$.post('data_queries.php', jSON).done(function(data) {
						$('#main').html(data);
						applySkin();
					});
				});
				</script>
			</td>
			<?php
			form_end_row();
		}
		}
		html_end_box();

	}

	form_save_button('data_queries.php?action=edit&id=' . $_REQUEST['snmp_query_id'], 'return');
}

/* ---------------------
    Data Query Functions
   --------------------- */

function data_query_remove($id) {
	$snmp_query_graph = db_fetch_assoc('select id FROM snmp_query_graph WHERE snmp_query_id=' . $id);

	if (sizeof($snmp_query_graph) > 0) {
	foreach ($snmp_query_graph as $item) {
		db_execute('DELETE FROM snmp_query_graph_rrd WHERE snmp_query_graph_id=' . $item['id']);
	}
	}

	db_execute('DELETE FROM snmp_query WHERE id=' . $id);
	db_execute('DELETE FROM snmp_query_graph WHERE snmp_query_id=' . $id);
	db_execute('DELETE FROM host_template_snmp_query WHERE snmp_query_id=' . $id);
	db_execute('DELETE FROM host_snmp_query WHERE snmp_query_id=' . $id);
	db_execute('DELETE FROM host_snmp_cache WHERE snmp_query_id=' . $id);
}

function data_query_edit() {
	global $fields_data_query_edit, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	if (!empty($_REQUEST['id'])) {
		$snmp_query = db_fetch_row("SELECT * FROM snmp_query WHERE id=" . $_REQUEST["id"]);
		$header_label = "[edit: " . htmlspecialchars($snmp_query["name"]) . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>Data Queries</strong> $header_label", "100%", "", "3", "center", "");

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
			$text = "<font class='txtErrorText'><strong>Could not locate XML file.</strong></font>";
			$xml_file_exists = false;
		}

		html_start_box("", "100%", "", "3", "center", "");
		print "<tr class='tableRow'><td>$text</td></tr>";
		html_end_box();

		if ($xml_file_exists == true) {
			html_start_box("<strong>Associated Graph Templates</strong>", "100%", "", "3", "center", "data_queries.php?action=item_edit&snmp_query_id=" . $snmp_query["id"]);

			print "<tr class='tableHeader'>
					<td class='textSubHeaderDark'>Name</td>
					<td class='textSubHeaderDark'>Graph Template Name</td>
					<td></td>
				</tr>";

			$snmp_query_graphs = db_fetch_assoc("SELECT
				snmp_query_graph.id,
				graph_templates.name as graph_template_name,
				snmp_query_graph.name
				from snmp_query_graph
				left join graph_templates on (snmp_query_graph.graph_template_id=graph_templates.id)
				where snmp_query_graph.snmp_query_id=" . $snmp_query["id"] . "
				ORDER BY snmp_query_graph.name");

			$i = 0;
			if (sizeof($snmp_query_graphs) > 0) {
				foreach ($snmp_query_graphs as $snmp_query_graph) {
					form_alternate_row();
					?>
						<td>
							<strong><a href="<?php print htmlspecialchars("data_queries.php?action=item_edit&id=" . $snmp_query_graph["id"] . "&snmp_query_id=" . $snmp_query["id"]);?>"><?php print htmlspecialchars($snmp_query_graph["name"]);?></a></strong>
						</td>
						<td>
							<?php print htmlspecialchars($snmp_query_graph["graph_template_name"]);?>
						</td>
						<td align="right">
							<a href="<?php print htmlspecialchars("data_queries.php?action=item_remove&id=" . $snmp_query_graph["id"] . "&snmp_query_id=" . $snmp_query["id"]);?>"><img src="images/delete_icon.gif" style="height:10px;width:10px;" border="0" alt="Delete"></a>
						</td>
					</tr>
					<?php
				}
			}else{
				print "<tr class='tableRow'><td><em>No Graph Templates Defined.</em></td></tr>";
			}

			html_end_box();
		}
	}

	form_save_button("data_queries.php", "return");
}

function data_query() {
	global $dq_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("rows"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var_request("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var_request("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_data_queries_filter");
		kill_session_var("sess_default_rows");
		kill_session_var("sess_data_queries_sort_column");
		kill_session_var("sess_data_queries_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
		$_REQUEST["page"] = 1;
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("sort_column", "sess_data_queries_sort_column", "name");
	load_current_session_value("sort_direction", "sess_data_queries_sort_direction", "ASC");
	load_current_session_value("page", "sess_data_queries_current_page", "1");
	load_current_session_value("filter", "sess_data_queries_filter", "");
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	html_start_box("<strong>Data Queries</strong>", "100%", "", "3", "center", "data_queries.php?action=edit");

	?>
	<tr class='even noprint'>
		<td class="noprint">
		<form id="form_data_queries" method="get" action="data_queries.php">
			<table cellpadding="2" cellspacing="0">
				<tr class="noprint">
					<td width="50">
						Search:
					</td>
					<td>
						<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
					</td>
					<td style='white-space:nowrap;'>
						Data Queries:
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type="button" id='refresh' value="Go" title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" id='clear' name="clear_x" value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL = 'data_queries.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&header=false';
			$.get(strURL, function(data) {
				$('#main').html(data);
				applySkin();
			});
		}

		function clearFilter() {
			strURL = 'data_queries.php?clear_x=1&header=false';
			$.get(strURL, function(data) {
				$('#main').html(data);
				applySkin();
			});
		}

		$(function() {
			$('#refresh').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});
	
			$('#form_data_queries').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
		</td>
	</tr>
	<?php

	html_end_box();

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='data_queries.php'>\n";

	html_start_box("", "100%", "", "3", "center", "");

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$sql_where = "WHERE (sq.name like '%%" . get_request_var_request("filter") . "%%' OR di.name like '%%" . get_request_var_request("filter") . "%%')";
	}else{
		$sql_where = "";
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM snmp_query AS sq
		INNER JOIN data_input AS di
		ON (sq.data_input_id=di.id)
		$sql_where");

	$snmp_queries = db_fetch_assoc("SELECT sq.id, sq.name,
		di.name AS data_input_method, 
		COUNT(DISTINCT gl.id) AS graphs,
		COUNT(DISTINCT sqg.graph_template_id) AS templates
		FROM snmp_query AS sq
		LEFT JOIN snmp_query_graph AS sqg
		ON sq.id=sqg.snmp_query_id
		LEFT JOIN data_input AS di
		ON (sq.data_input_id=di.id)
		LEFT JOIN graph_local AS gl
		ON gl.snmp_query_id=sq.id
		$sql_where
		GROUP BY sq.id
		ORDER BY " . get_request_var_request("sort_column") . " " . get_request_var_request("sort_direction") . "
		LIMIT " . (get_request_var_request("rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("rows"));

	$nav = html_nav_bar("data_queries.php?filter=" . get_request_var_request("filter"), MAX_DISPLAY_PAGES, get_request_var_request("page"), get_request_var_request("rows"), $total_rows, 5, 'Data Queries', 'page', 'main');
	print $nav;

	$display_text = array(
		"name" => array("Name", "ASC"),
		"graphs" => array('display' => "Graphs", 'align' => 'right', 'sort' => "DESC"),
		"templates" => array('display' => "Graph Templates", 'align' => 'right', 'sort' => "DESC"),
		"data_input_method" => array("Data Input Method", "ASC"));

	html_header_sort_checkbox($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), false);

	$i = 0;
	if (sizeof($snmp_queries) > 0) {
		foreach ($snmp_queries as $snmp_query) {
			if ($snmp_query['graphs'] == 0 && $snmp_query['templates'] == 0) {
				$disabled = false;
			}else{
				$disabled = true;
			}

			form_alternate_row('line' . $snmp_query["id"], true, $disabled);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("data_queries.php?action=edit&id=" . $snmp_query["id"]) . "'>" . (strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span class='filteredValue'>\\1</span>", htmlspecialchars($snmp_query["name"])) : htmlspecialchars($snmp_query["name"])) . "</a>", $snmp_query["id"]);
			form_selectable_cell(number_format($snmp_query['graphs']), $snmp_query['id'], '', 'text-align:right');
			form_selectable_cell(number_format($snmp_query['templates']), $snmp_query['id'], '', 'text-align:right');
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span class='filteredValue'>\\1</span>", $snmp_query["data_input_method"]) : $snmp_query["data_input_method"]), $snmp_query["id"]);
			form_checkbox_cell($snmp_query["name"], $snmp_query["id"], $disabled);
			form_end_row();
		}

		print $nav;
	}else{
		print "<tr class='tableRow'><td colspan='5'><em>No Data Queries</em></td></tr>";
	}

	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($dq_actions);
}
?>

