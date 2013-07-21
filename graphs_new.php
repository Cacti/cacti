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
include_once("./lib/data_query.php");
include_once("./lib/utility.php");
include_once("./lib/sort.php");
include_once("./lib/html_form_template.php");
include_once("./lib/template.php");

define("MAX_DISPLAY_PAGES", 21);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }
switch ($_REQUEST["action"]) {
	case 'save':
		form_save();

		break;
	case 'query_reload':
		host_reload_query();

		header("Location: graphs_new.php?host_id=" . $_GET["host_id"]);
		break;
	default:
		include_once("./include/top_header.php");

		graphs();

		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_graph"])) {
		/* summarize the 'create graph from host template/snmp index' stuff into an array */
		while (list($var, $val) = each($_POST)) {
			if (preg_match('/^cg_(\d+)$/', $var, $matches)) {
				$selected_graphs["cg"]{$matches[1]}{$matches[1]} = true;
			}elseif (preg_match('/^cg_g$/', $var)) {
				if ($_POST["cg_g"] > 0) {
					$selected_graphs["cg"]{$_POST["cg_g"]}{$_POST["cg_g"]} = true;
				}
			}elseif (preg_match('/^sg_(\d+)_([a-f0-9]{32})$/', $var, $matches)) {
				$selected_graphs["sg"]{$matches[1]}{$_POST{"sgg_" . $matches[1]}}{$matches[2]} = true;
			}
		}

		if (isset($selected_graphs)) {
			host_new_graphs($_POST["host_id"], $_POST["host_template_id"], $selected_graphs);
			exit;
		}

		header("Location: graphs_new.php?host_id=" . $_POST["host_id"]);
	}

	if (isset($_POST["save_component_new_graphs"])) {
		host_new_graphs_save();

		header("Location: graphs_new.php?host_id=" . $_POST["host_id"]);
	}
}

/* ---------------------
    Misc Functions
   --------------------- */

function draw_edit_form_row($field_array, $field_name, $previous_value) {
	$field_array["value"] = $previous_value;

	draw_edit_form(
		array(
			"config" => array(
				"no_form_tag" => true,
				"force_row_color" => "F5F5F5"
				),
			"fields" => array(
				$field_name => $field_array
				)
			)
		);
}

/* -------------------
    Data Query Functions
   ------------------- */

function host_reload_query() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("host_id"));
	/* ==================================================== */

	run_data_query($_GET["host_id"], $_GET["id"]);
}

/* -------------------
    New Graph Functions
   ------------------- */

function host_new_graphs_save() {
	$selected_graphs_array = unserialize(stripslashes($_POST["selected_graphs_array"]));

	/* form an array that contains all of the data on the previous form */
	while (list($var, $val) = each($_POST)) {
		if (preg_match("/^g_(\d+)_(\d+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: field_name */
			if (empty($matches[1])) { /* this is a new graph from template field */
				$values["cg"]{$matches[2]}["graph_template"]{$matches[3]} = $val;
			}else{ /* this is a data query field */
				$values["sg"]{$matches[1]}{$matches[2]}["graph_template"]{$matches[3]} = $val;
			}
		}elseif (preg_match("/^gi_(\d+)_(\d+)_(\d+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: graph_template_input_id, 4:field_name */
			/* ================= input validation ================= */
			input_validate_input_number($matches[3]);
			/* ==================================================== */

			/* we need to find out which graph items will be affected by saving this particular item */
			$item_list = db_fetch_assoc("select
				graph_template_item_id
				from graph_template_input_defs
				where graph_template_input_id=" . $matches[3]);

			/* loop through each item affected and update column data */
			if (sizeof($item_list) > 0) {
			foreach ($item_list as $item) {
				if (empty($matches[1])) { /* this is a new graph from template field */
					$values["cg"]{$matches[2]}["graph_template_item"]{$item["graph_template_item_id"]}{$matches[4]} = $val;
				}else{ /* this is a data query field */
					$values["sg"]{$matches[1]}{$matches[2]}["graph_template_item"]{$item["graph_template_item_id"]}{$matches[4]} = $val;
				}
			}
			}
		}elseif (preg_match("/^d_(\d+)_(\d+)_(\d+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:field_name */
			if (empty($matches[1])) { /* this is a new graph from template field */
				$values["cg"]{$matches[2]}["data_template"]{$matches[3]}{$matches[4]} = $val;
			}else{ /* this is a data query field */
				$values["sg"]{$matches[1]}{$matches[2]}["data_template"]{$matches[3]}{$matches[4]} = $val;
			}
		}elseif (preg_match("/^c_(\d+)_(\d+)_(\d+)_(\d+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:data_input_field_id */
			if (empty($matches[1])) { /* this is a new graph from template field */
				$values["cg"]{$matches[2]}["custom_data"]{$matches[3]}{$matches[4]} = $val;
			}else{ /* this is a data query field */
				$values["sg"]{$matches[1]}{$matches[2]}["custom_data"]{$matches[3]}{$matches[4]} = $val;
			}
		}elseif (preg_match("/^di_(\d+)_(\d+)_(\d+)_(\d+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:local_data_template_rrd_id, 5:field_name */
			if (empty($matches[1])) { /* this is a new graph from template field */
				$values["cg"]{$matches[2]}["data_template_item"]{$matches[4]}{$matches[5]} = $val;
			}else{ /* this is a data query field */
				$values["sg"]{$matches[1]}{$matches[2]}["data_template_item"]{$matches[4]}{$matches[5]} = $val;
			}
		}
	}

	debug_log_clear("new_graphs");

	while (list($form_type, $form_array) = each($selected_graphs_array)) {
		$current_form_type = $form_type;

		while (list($form_id1, $form_array2) = each($form_array)) {
			/* enumerate information from the arrays stored in post variables */
			if ($form_type == "cg") {
				$graph_template_id = $form_id1;
			}elseif ($form_type == "sg") {
				while (list($form_id2, $form_array3) = each($form_array2)) {
					$snmp_index_array = $form_array3;

					$snmp_query_array["snmp_query_id"] = $form_id1;
					$snmp_query_array["snmp_index_on"] = get_best_data_query_index_type($_POST["host_id"], $form_id1);
					$snmp_query_array["snmp_query_graph_id"] = $form_id2;
				}

				$graph_template_id = db_fetch_cell("select graph_template_id from snmp_query_graph where id=" . $snmp_query_array["snmp_query_graph_id"]);
			}

			if ($current_form_type == "cg") {
				$return_array = create_complete_graph_from_template($graph_template_id, $_POST["host_id"], "", $values["cg"]);

				debug_log_insert("new_graphs", "Created graph: " . get_graph_title($return_array["local_graph_id"]));

				/* lastly push host-specific information to our data sources */
				if (sizeof($return_array["local_data_id"])) { # we expect at least one data source associated
					foreach($return_array["local_data_id"] as $item) {
						push_out_host($_POST["host_id"], $item);
					}
				} else {
					debug_log_insert("new_graphs", "ERROR: no Data Source associated. Check Template");
				}
			}elseif ($current_form_type == "sg") {
				while (list($snmp_index, $true) = each($snmp_index_array)) {
					$snmp_query_array["snmp_index"] = decode_data_query_index($snmp_index, $snmp_query_array["snmp_query_id"], $_POST["host_id"]);

					$return_array = create_complete_graph_from_template($graph_template_id, $_POST["host_id"], $snmp_query_array, $values["sg"]{$snmp_query_array["snmp_query_id"]});

					debug_log_insert("new_graphs", "Created graph: " . get_graph_title($return_array["local_graph_id"]));

					/* lastly push host-specific information to our data sources */
					if (sizeof($return_array["local_data_id"])) { # we expect at least one data source associated
						foreach($return_array["local_data_id"] as $item) {
							push_out_host($_POST["host_id"], $item);
						}
					} else {
						debug_log_insert("new_graphs", "ERROR: no Data Source associated. Check Template");
					}
				}
			}
		}
	}
}

function host_new_graphs($host_id, $host_template_id, $selected_graphs_array) {
	global $colors;

	/* we use object buffering on this page to allow redirection to another page if no
	fields are actually drawn */
	ob_start();

	include_once("./include/top_header.php");

	print "<form method='post' action='graphs_new.php'>\n";

	$snmp_query_id = 0;
	$num_output_fields = array();

	while (list($form_type, $form_array) = each($selected_graphs_array)) {
		while (list($form_id1, $form_array2) = each($form_array)) {
			if ($form_type == "cg") {
				$graph_template_id = $form_id1;

				html_start_box("<strong>Create Graph from '" . db_fetch_cell("select name from graph_templates where id=$graph_template_id") . "'", "100%", $colors["header"], "3", "center", "");
			}elseif ($form_type == "sg") {
				while (list($form_id2, $form_array3) = each($form_array2)) {
					/* ================= input validation ================= */
					input_validate_input_number($snmp_query_id);
					/* ==================================================== */

					$snmp_query_id = $form_id1;
					$snmp_query_graph_id = $form_id2;
					$num_graphs = sizeof($form_array3);

					$snmp_query = db_fetch_row("select
						snmp_query.name,
						snmp_query.xml_path
						from snmp_query
						where snmp_query.id=$snmp_query_id");

					$graph_template_id = db_fetch_cell("select graph_template_id from snmp_query_graph where id=$snmp_query_graph_id");
				}

				/* DRAW: Data Query */
				html_start_box("<strong>Create $num_graphs Graph" . (($num_graphs>1) ? "s" : "") . " from '" . db_fetch_cell("select name from snmp_query where id=$snmp_query_id") . "'", "100%", $colors["header"], "3", "center", "");
			}

			/* ================= input validation ================= */
			input_validate_input_number($graph_template_id);
			/* ==================================================== */

			$data_templates = db_fetch_assoc("select
				data_template.name as data_template_name,
				data_template_rrd.data_source_name,
				data_template_data.*
				from (data_template, data_template_rrd, data_template_data, graph_templates_item)
				where graph_templates_item.task_item_id=data_template_rrd.id
				and data_template_rrd.data_template_id=data_template.id
				and data_template_data.data_template_id=data_template.id
				and data_template_rrd.local_data_id=0
				and data_template_data.local_data_id=0
				and graph_templates_item.local_graph_id=0
				and graph_templates_item.graph_template_id=" . $graph_template_id . "
				group by data_template.id
				order by data_template.name");

			$graph_template = db_fetch_row("select
				graph_templates.name as graph_template_name,
				graph_templates_graph.*
				from (graph_templates, graph_templates_graph)
				where graph_templates.id=graph_templates_graph.graph_template_id
				and graph_templates.id=" . $graph_template_id . "
				and graph_templates_graph.local_graph_id=0");
			$graph_template_name = db_fetch_cell("select name from graph_templates where id=" . $graph_template_id);

			array_push($num_output_fields, draw_nontemplated_fields_graph($graph_template_id, $graph_template, "g_$snmp_query_id" . "_" . $graph_template_id . "_|field|", "<strong>Graph</strong> [Template: " . $graph_template["graph_template_name"] . "]", false, false, (isset($snmp_query_graph_id) ? $snmp_query_graph_id : 0)));
			array_push($num_output_fields, draw_nontemplated_fields_graph_item($graph_template_id, 0, "gi_" . $snmp_query_id . "_" . $graph_template_id . "_|id|_|field|", "<strong>Graph Items</strong> [Template: " . $graph_template_name . "]", false));

			/* DRAW: Data Sources */
			if (sizeof($data_templates) > 0) {
			foreach ($data_templates as $data_template) {
				array_push($num_output_fields, draw_nontemplated_fields_data_source($data_template["data_template_id"], 0, $data_template, "d_" . $snmp_query_id . "_" . $graph_template_id . "_" . $data_template["data_template_id"] . "_|field|", "<strong>Data Source</strong> [Template: " . $data_template["data_template_name"] . "]", false, false, (isset($snmp_query_graph_id) ? $snmp_query_graph_id : 0)));

				$data_template_items = db_fetch_assoc("select
					data_template_rrd.*
					from data_template_rrd
					where data_template_rrd.data_template_id=" . $data_template["data_template_id"] . "
					and local_data_id=0");

				array_push($num_output_fields, draw_nontemplated_fields_data_source_item($data_template["data_template_id"], $data_template_items, "di_" . $snmp_query_id . "_" . $graph_template_id . "_" . $data_template["data_template_id"] . "_|id|_|field|", "", false, false, false, (isset($snmp_query_graph_id) ? $snmp_query_graph_id : 0)));
				array_push($num_output_fields, draw_nontemplated_fields_custom_data($data_template["id"], "c_" . $snmp_query_id . "_" . $graph_template_id . "_" . $data_template["data_template_id"] . "_|id|", "<strong>Custom Data</strong> [Template: " . $data_template["data_template_name"] . "]", false, false, $snmp_query_id));
			}
			}

			html_end_box();
		}
	}

	/* no fields were actually drawn on the form; just save without prompting the user */
	if (array_sum($num_output_fields) == 0) {
		ob_end_clean();

		/* since the user didn't actually click "Create" to POST the data; we have to
		pretend like they did here */
		$_POST["host_template_id"] = $host_template_id;
		$_POST["host_id"] = $host_id;
		$_POST["save_component_new_graphs"] = "1";
		$_POST["selected_graphs_array"] = serialize($selected_graphs_array);

		host_new_graphs_save();

		header("Location: graphs_new.php?host_id=" . $_POST["host_id"]);
		exit;
	}

	/* flush the current output buffer to the browser */
	ob_end_flush();

	form_hidden_box("host_template_id", $host_template_id, "0");
	form_hidden_box("host_id", $host_id, "0");
	form_hidden_box("save_component_new_graphs", "1", "");
	print "<input type='hidden' name='selected_graphs_array' value='" . serialize($selected_graphs_array) . "'>\n";

	if (!substr_count($_SERVER["HTTP_REFERER"], "graphs_new")) {
		$_REQUEST["returnto"] = basename($_SERVER["HTTP_REFERER"]);
	}
	load_current_session_value("returnto", "sess_graphs_new_returnto", "");

	form_save_button($_REQUEST["returnto"]);

	include_once("./include/bottom_footer.php");
}

/* -------------------
    Graph Functions
   ------------------- */

function graphs() {
	global $colors;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("host_id"));
	input_validate_input_number(get_request_var_request("graph_type"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_graphs_new_filter");
		unset($_REQUEST["filter"]);
		$changed = true;
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = false;
		$changed += check_changed("host_id",    "sess_graphs_new_host_id");
		$changed += check_changed("graph_type", "sess_graphs_new_graph_type");
		$changed += check_changed("filter",     "sess_graphs_new_filter");
	}

	load_current_session_value("host_id",    "sess_graphs_new_host_id",    db_fetch_cell("select id from host order by description,hostname limit 1"));
	load_current_session_value("graph_type", "sess_graphs_new_graph_type", read_config_option("default_graphs_new_dropdown"));
	load_current_session_value("filter",     "sess_graphs_new_filter",     "");

	$host      = db_fetch_row("select id,description,hostname,host_template_id from host where id=" . $_REQUEST["host_id"]);
	$row_limit = read_config_option("num_rows_data_query");
	$debug_log = debug_log_return("new_graphs");

	if (!empty($debug_log)) {
		debug_log_clear("new_graphs");
		if (read_config_option("cacti_popup_messages") == "on") { ?>
		<div id='message'>
			<?php print "<table align='center' style='width:100%;background-color:#" . $colors["header"] . ";'><tr><td style='align:center;padding:3px;font-weight:bold;font-size:10pt;text-align:center;'>Graphs Created</td><td style='width:1px;align:right;'><input type='button' value='Clear' onClick='javascript:document.getElementById(\"message\").style.display=\"none\"' style='align=right;'></td></tr></table>";?>
			<?php print "<table align='left' style='width:100%;'><tr><td><ul style='text-align:left;white-space:nowrap;color:#000000;padding:2px 10px;margin:10px;'>" . $debug_log . "</ul></td></tr></table>";?>
		</div>
		<?php }else{ ?>
		<table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>
			<tr bgcolor="<?php print $colors["light"];?>">
				<td style="padding: 3px; font-family: monospace;">
					<ul style='margin:0px 5px;padding-left:10px'><?php print $debug_log;?></ul>
				</td>
			</tr>
		</table>
		<br><?php }
	}
	?>
	<script type="text/javascript">
	<!--
	<?php if (read_config_option("cacti_popup_messages") == "on") {?>
	var obj = document.getElementById('message');

	if (obj) {
		if (window.innerHeight) {
			height = window.innerHeight;
			width  = window.innerWidth;
		}else{
			height = document.body.clientHeight;
			width  = document.body.clientWidth;
		}
		obj.style.position = "absolute";
		obj.style.padding = "0px";
		obj.style.display = "";
		obj.style.overflow = "auto";
		obj.style.color = "#FFFFFF";
		obj.style.backgroundColor = "#<?php print $colors["light"];?>";
		obj.style.border = "1px solid #<?php print $colors["header"];?>";
		cw = obj.offsetWidth;
		// Adjust for IE6
		if (!cw) cw = 150;
		ch = obj.offsetHeight;
		obj.style.top = '65px';
		obj.style.left = ((width/2) - (cw/2) - 88)+'px';
	}
	<?php } ?>

	function applyGraphsNewFilterChange(objForm) {
		strURL = '?graph_type=' + objForm.graph_type.value;
		strURL = strURL + '&host_id=' + objForm.host_id.value;
		strURL = strURL + '&filter=' + objForm.filter.value;;
		document.location = strURL;
	}

	-->
	</script>
	<form name="form_graphs_new" action="graphs_new.php">
	<table width="100%" cellpadding="4" align="center">
		<tr>
			<td nowrap style='white-space: nowrap;' width="30%" class="textInfo">
				<?php print htmlspecialchars($host["description"]);?> (<?php print htmlspecialchars($host["hostname"]);?>)
			</td>
			<td align="left" class="textInfo" colspan="2" style="color: #aaaaaa;">
				<?php
				if (!empty($host["host_template_id"])) {
					print htmlspecialchars(db_fetch_cell("select name from host_template where id=" . $host["host_template_id"]));
				}
				?>
			</td>
		</tr>
	</table>
	<table width="100%" cellpadding="0" align="center">
		<tr>
			<td nowrap style='white-space: nowrap;' width="55" class="textArea">
				Host:&nbsp;
			</td>
			<td width="1">
				<select name="host_id" onChange="applyGraphsNewFilterChange(document.form_graphs_new)">
				<?php
				$hosts = db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname");

				if (sizeof($hosts) > 0) {
					foreach ($hosts as $item) {
						print "<option value='" . $item["id"] . "'"; if ($_REQUEST["host_id"] == $item["id"]) { print " selected"; } print ">" . htmlspecialchars($item["name"]) . "</option>\n";
					}
				}
				?>
				</select>
			</td>
			<td nowrap style='white-space: nowrap;' width="100" class="textArea">
				&nbsp;Graph Types:&nbsp;
			</td>
			<td width="1">
				<select name="graph_type" onChange="applyGraphsNewFilterChange(document.form_graphs_new)">
				<option value="-2"<?php if ($_REQUEST["graph_type"] == "-2") {?> selected<?php }?>>All</option>
				<option value="-1"<?php if ($_REQUEST["graph_type"] == "-1") {?> selected<?php }?>>Graph Template Based</option>
				<?php

				$snmp_queries = db_fetch_assoc("SELECT
					snmp_query.id,
					snmp_query.name,
					snmp_query.xml_path
					FROM (snmp_query,host_snmp_query)
					WHERE host_snmp_query.snmp_query_id=snmp_query.id
					AND host_snmp_query.host_id=" . $host["id"] . "
					ORDER BY snmp_query.name");

				if (sizeof($snmp_queries) > 0) {
				foreach ($snmp_queries as $query) {
					print "<option value='" . $query["id"] . "'"; if ($_REQUEST["graph_type"] == $query["id"]) { print " selected"; } print ">" . $query["name"] . "</option>\n";
				}
				}
				?>
				</select>
			</td>
			<td nowrap style='white-space: nowrap;' class="textInfo" align="center" valign="top">
				<span style="white-space: nowrap; color: #c16921;">*</span><a href="<?php print htmlspecialchars("host.php?action=edit&id=" . $_REQUEST["host_id"]);?>">Edit this Host</a><br>
				<span style="white-space: nowrap; color: #c16921;">*</span><a href="<?php print htmlspecialchars("host.php?action=edit");?>">Create New Host</a><br>
				<?php api_plugin_hook('graphs_new_top_links'); ?>
			</td>
		</tr>
	</table>
	<?php if ($_REQUEST["graph_type"] > 0) {?>
	<table width="100%" cellpadding="0" align="center">
		<tr>
			<td nowrap style='white-space: nowrap;' width="55" class="textArea">
				Search:&nbsp;
			</td>
			<td nowrap style='white-space: nowrap;' width="200">
				<input type="text" name="filter" size="30" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
			</td>
			<td align="left" nowrap style='white-space: nowrap;'>
				&nbsp;<input type="submit" value="Go" title="Set/Refresh Filters">
				<input type="submit" name="clear_x" value="Clear" title="Clear Filters">
			</td>
		</tr>
	</table>
	<?php }else{
		form_hidden_box("filter", $_REQUEST["filter"], "");
	}?>
	</form>

	<form name="chk" method="post" action="graphs_new.php">
	<?php
	$total_rows = sizeof(db_fetch_assoc("select graph_template_id from host_graph where host_id=" . $_REQUEST["host_id"]));

	$i = 0;

	if ($changed) {
		foreach($snmp_queries as $query) {
			kill_session_var("sess_graphs_new_page" . $query["id"]);
			unset($_REQUEST["page" . $query["id"]]);
			load_current_session_value("page" . $query["id"], "sess_graphs_new_page" . $query["id"], "1");
		}
	}

	if ($_REQUEST["graph_type"] > 0) {
		load_current_session_value("page" . $_REQUEST["graph_type"], "sess_graphs_new_page" . $_REQUEST["graph_type"], "1");
	}else if ($_REQUEST["graph_type"] == -2) {
		foreach($snmp_queries as $query) {
			load_current_session_value("page" . $query["id"], "sess_graphs_new_page" . $query["id"], "1");
		}
	}

	$script = "<script type='text/javascript'>\nvar gt_created_graphs = new Array();\nvar created_graphs = new Array()\n";

	if ($_REQUEST["graph_type"] < 0) {
		html_start_box("<strong>Graph Templates</strong>", "100%", $colors["header"], "3", "center", "");

		print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
				<td class='textSubHeaderDark'>Graph Template Name</td>
				<td width='1%' align='center' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all_cg' title='Select All' onClick='SelectAll(\"cg\",this.checked);gt_update_selection_indicators();'></td>\n
			</tr>\n";

		$graph_templates = db_fetch_assoc("SELECT
			graph_templates.id AS graph_template_id,
			graph_templates.name AS graph_template_name
			FROM (host_graph,graph_templates)
			WHERE host_graph.graph_template_id=graph_templates.id
			AND host_graph.host_id=" . $_REQUEST["host_id"] . "
			ORDER BY graph_templates.name");

		$template_graphs = db_fetch_assoc("SELECT
			graph_local.graph_template_id
			FROM (graph_local,host_graph)
			WHERE graph_local.graph_template_id=host_graph.graph_template_id
			AND graph_local.host_id=host_graph.host_id
			AND graph_local.host_id=" . $host["id"] . "
			GROUP BY graph_local.graph_template_id");

		if (sizeof($template_graphs) > 0) {
			$script .= "var gt_created_graphs = new Array(";

			$cg_ctr = 0;
			foreach ($template_graphs as $template_graph) {
				$script .= (($cg_ctr > 0) ? "," : "") . "'" . $template_graph["graph_template_id"] . "'";

				$cg_ctr++;
			}

			$script .= ")\n";
		}

		/* create a row for each graph template associated with the host template */
		if (sizeof($graph_templates) > 0) {
		foreach ($graph_templates as $graph_template) {
			$query_row = $graph_template["graph_template_id"];

			print "<tr id='gt_line$query_row' bgcolor='#" . (($i % 2 == 0) ? "ffffff" : $colors["light"]) . "'>"; $i++;

			print "		<td onClick='gt_select_line(" . $graph_template["graph_template_id"] . ");'>
						<span id='gt_text$query_row" . "_0'><strong>Create:</strong> " . htmlspecialchars($graph_template["graph_template_name"]) . "</span>
					</td>
					<td align='right'>
						<input type='checkbox' name='cg_$query_row' id='cg_$query_row' onClick='gt_update_selection_indicators();'>
					</td>
				</tr>";
		}
		}

		$script .= "gt_update_deps(1);\n";

		$available_graph_templates = db_fetch_assoc("SELECT
			graph_templates.id, graph_templates.name
			FROM snmp_query_graph RIGHT JOIN graph_templates
			ON (snmp_query_graph.graph_template_id = graph_templates.id)
			WHERE (((snmp_query_graph.name) Is Null)) ORDER BY graph_templates.name");

		/* create a row at the bottom that lets the user create any graph they choose */
		print "	<tr bgcolor='#" . (($i % 2 == 0) ? "ffffff" : $colors["light"]) . "'>
				<td colspan='2' width='60' nowrap>
					<strong>Create:</strong>&nbsp;";
					form_dropdown("cg_g", $available_graph_templates, "name", "id", "", "(Select a graph type to create)", "", "textArea");
		print "		</td>
			</tr>";

		html_end_box();
	}

	if ($_REQUEST["graph_type"] != -1) {
		$snmp_queries = db_fetch_assoc("SELECT
			snmp_query.id,
			snmp_query.name,
			snmp_query.xml_path
			FROM (snmp_query,host_snmp_query)
			WHERE host_snmp_query.snmp_query_id=snmp_query.id
			AND host_snmp_query.host_id=" . $host["id"] .
			($_REQUEST["graph_type"] != -2 ? " AND snmp_query.id=" . $_REQUEST["graph_type"] : '') . "
			ORDER BY snmp_query.name");

		if (sizeof($snmp_queries) > 0) {
		foreach ($snmp_queries as $snmp_query) {
			unset($total_rows);

			if (!$changed) {
				$page = $_REQUEST["page" . $snmp_query["id"]];
			}else{
				$page = 1;
			}

			$xml_array = get_data_query_array($snmp_query["id"]);

			$num_input_fields = 0;
			$num_visible_fields = 0;

			if ($xml_array != false) {
				/* loop through once so we can find out how many input fields there are */
				reset($xml_array["fields"]);
				while (list($field_name, $field_array) = each($xml_array["fields"])) {
					if ($field_array["direction"] == "input") {
						$num_input_fields++;

						if (!isset($total_rows)) {
							$total_rows = db_fetch_cell("SELECT count(*) FROM host_snmp_cache WHERE host_id=" . $host["id"] . " and snmp_query_id=" . $snmp_query["id"] . " AND field_name='$field_name'");
						}
					}
				}
			}

			if (!isset($total_rows)) {
				$total_rows = 0;
			}

			$snmp_query_graphs = db_fetch_assoc("SELECT snmp_query_graph.id,snmp_query_graph.name FROM snmp_query_graph WHERE snmp_query_graph.snmp_query_id=" . $snmp_query["id"] . " ORDER BY snmp_query_graph.name");

			if (sizeof($snmp_query_graphs) > 0) {
				foreach ($snmp_query_graphs as $snmp_query_graph) {
					$created_graphs = db_fetch_assoc("SELECT DISTINCT
						data_local.snmp_index
						FROM (data_local,data_template_data)
						LEFT JOIN data_input_data ON (data_template_data.id=data_input_data.data_template_data_id)
						LEFT JOIN data_input_fields ON (data_input_data.data_input_field_id=data_input_fields.id)
						WHERE data_local.id=data_template_data.local_data_id
						AND data_input_fields.type_code='output_type'
						AND data_input_data.value='" . $snmp_query_graph["id"] . "'
						AND data_local.host_id=" . $host["id"]);

					$script .= "created_graphs[" . $snmp_query_graph["id"] . "] = new Array(";

					$cg_ctr = 0;
					if (sizeof($created_graphs) > 0) {
					foreach ($created_graphs as $created_graph) {
						$script .= (($cg_ctr > 0) ? "," : "") . "'" . encode_data_query_index($created_graph["snmp_index"]) . "'";

						$cg_ctr++;
					}
					}

					$script .= ")\n";
				}
			}

			print "	<table width='100%' style='background-color: #" . $colors["form_alternate2"] . "; border: 1px solid #" . $colors["header"] . ";' align='center' cellpadding='3' cellspacing='0'>\n
					<tr>
						<td bgcolor='#" . $colors["header"] . "' colspan='" . ($num_input_fields+1) . "'>
							<table  cellspacing='0' cellpadding='0' width='100%' >
								<tr>
									<td class='textHeaderDark'>
										<strong>Data Query</strong> [" . $snmp_query["name"] . "]
									</td>
									<td align='right' nowrap>
										<a href='" . htmlspecialchars("graphs_new.php?action=query_reload&id=" . $snmp_query["id"] . "&host_id=" . $host["id"]) . "'><img src='images/reload_icon_small.gif' title='Reload Associated Query' alt='' border='0' align='middle'></a>
									</td>
								</tr>
							</table>
						</td>
					</tr>";

			if ($xml_array != false) {
				$html_dq_header = "";
				$snmp_query_indexes = array();

				reset($xml_array["fields"]);

				/* if there is a where clause, get the matching snmp_indexes */
				$sql_where = "";
				if (strlen($_REQUEST["filter"])) {
					$sql_where = "";
					$indexes = db_fetch_assoc("SELECT DISTINCT snmp_index
						FROM host_snmp_cache
						WHERE field_value LIKE '%%" . $_REQUEST["filter"] . "%%'
						AND snmp_query_id=" . $snmp_query["id"] . "
						AND host_id=" . $host["id"]);

					if (sizeof($indexes)) {
						foreach($indexes as $index) {
							if (strlen($sql_where)) {
								$sql_where .= ", '" . $index["snmp_index"] . "'";
							}else{
								$sql_where .= " AND snmp_index IN('" . $index["snmp_index"] . "'";
							}
						}

						$sql_where .= ")";
					}
				}

				if ((strlen($_REQUEST["filter"]) == 0) ||
					((strlen($_REQUEST["filter"])) && (sizeof($indexes)))) {
					/* determine the sort order */
					if (isset($xml_array["index_order_type"])) {
						if ($xml_array["index_order_type"] == "numeric") {
							$sql_order = "ORDER BY CAST(snmp_index AS unsigned)";
						}else if ($xml_array["index_order_type"] == "alphabetic") {
							$sql_order = "ORDER BY snmp_index";
						}else if ($xml_array["index_order_type"] == "natural") {
							$sql_order = "ORDER BY INET_ATON(snmp_index)";
						}else{
							$sql_order = "";
						}
					}else{
						$sql_order = "";
					}

					/* get the unique field values from the database */
					$field_names = db_fetch_assoc("SELECT DISTINCT field_name
						FROM host_snmp_cache
						WHERE host_id=" . $host["id"] . "
						AND snmp_query_id=" . $snmp_query["id"]);

					/* build magic query */
					$sql_query  = "SELECT host_id, snmp_query_id, snmp_index";
					$num_visible_fields = sizeof($field_names);
					$i = 0;
					if (sizeof($field_names) > 0) {
						foreach($field_names as $column) {
							$field_name = $column["field_name"];
							$sql_query .= ", MAX(CASE WHEN field_name='$field_name' THEN field_value ELSE NULL END) AS '$field_name'";
							$i++;
						}
					}

					$sql_query .= " FROM host_snmp_cache
						WHERE host_id=" . $host["id"] . "
						AND snmp_query_id=" . $snmp_query["id"] . "
						$sql_where
						GROUP BY host_id, snmp_query_id, snmp_index
						$sql_order
						LIMIT " . ($row_limit*($page-1)) . "," . $row_limit;

					$rows_query = "SELECT host_id, snmp_query_id, snmp_index
						FROM host_snmp_cache
						WHERE host_id=" . $host["id"] . "
						AND snmp_query_id=" . $snmp_query["id"] . "
						$sql_where
						GROUP BY host_id, snmp_query_id, snmp_index";

					$snmp_query_indexes = db_fetch_assoc($sql_query);

					$total_rows = sizeof(db_fetch_assoc($rows_query));

					if (($page - 1) * $row_limit > $total_rows) {
						$page = 1;
						$_REQUEST["page" . $query["id"]] = $page;
						load_current_session_value("page" . $query["id"], "sess_graphs_new_page" . $query["id"], "1");
					}

					if ($total_rows > $row_limit) {
						/* generate page list */
						$url_page_select = get_page_list($page, MAX_DISPLAY_PAGES, $row_limit, $total_rows, "graphs_new.php?", "page" . $snmp_query["id"]);

						$nav = "<tr bgcolor='#" . $colors["header"] . "' class='noprint'>
									<td colspan='15'>
										<table width='100%' cellspacing='0' cellpadding='0' border='0'>
											<tr>
												<td align='left' class='textHeaderDark'>
													<strong>&lt;&lt; "; if ($page > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("graphs_new.php?page" . $snmp_query["id"] . "=" . ($page-1)) . "'>"; } $nav .= "Previous"; if ($page > 1) { $nav .= "</a>"; } $nav .= "</strong>
												</td>\n
												<td align='center' class='textHeaderDark'>
													Showing Rows " . (($row_limit*($page-1))+1) . " to " . ((($total_rows < $row_limit) || ($total_rows < ($row_limit*$page))) ? $total_rows : ($row_limit*$page)) . " of $total_rows [$url_page_select]
												</td>\n
												<td align='right' class='textHeaderDark'>
													<strong>"; if (($page * $row_limit) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("graphs_new.php?page" . $snmp_query["id"] . "=" . ($page+1)) . "'>"; } $nav .= "Next"; if (($page * $row_limit) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
												</td>\n
											</tr>
										</table>
									</td>
								</tr>\n";

						print $nav;
					}

					while (list($field_name, $field_array) = each($xml_array["fields"])) {
						if ($field_array["direction"] == "input" && sizeof($field_names)) {
							foreach($field_names as $row) {
								if ($row["field_name"] == $field_name) {
									$html_dq_header .= "<td style='height:1px;'><strong><font color='#" . $colors["header_text"] . "'>" . $field_array["name"] . "</font></strong></td>\n";
									break;
								}
							}
						}
					}

					if (!sizeof($snmp_query_indexes)) {
						print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td>This data query returned 0 rows, perhaps there was a problem executing this
							data query. You can <a href='" . htmlspecialchars("host.php?action=query_verbose&id=" . $snmp_query["id"] . "&host_id=" . $host["id"]) . "'>run this data
							query in debug mode</a> to get more information.</td></tr>\n";
					}else{
						print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
								$html_dq_header
								<td width='1%' align='center' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all_" . $snmp_query["id"] . "' title='Select All' onClick='SelectAll(\"sg_" . $snmp_query["id"] . "\",this.checked);dq_update_selection_indicators();'></td>\n
							</tr>\n";
					}

					$row_counter    = 0;
					$column_counter = 0;
					$fields         = array_rekey($field_names, "field_name", "field_name");
					if (sizeof($snmp_query_indexes) > 0) {
					foreach($snmp_query_indexes as $row) {
						$query_row = $snmp_query["id"] . "_" . encode_data_query_index($row["snmp_index"]);

						print "<tr id='line$query_row' bgcolor='#" . (($row_counter % 2 == 0) ? "ffffff" : $colors["light"]) . "'>"; $i++;

						$column_counter = 0;
						reset($xml_array["fields"]);
						while (list($field_name, $field_array) = each($xml_array["fields"])) {
							if ($field_array["direction"] == "input") {
								if (in_array($field_name, $fields)) {
									if (isset($row[$field_name])) {
										print "<td onClick='dq_select_line(" . $snmp_query["id"] . ",\"" . encode_data_query_index($row["snmp_index"]) . "\");'><span id='text$query_row" . "_" . $column_counter . "'>" . (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $row[$field_name]) : $row[$field_name]) . "</span></td>";
									}else{
										print "<td onClick='dq_select_line(" . $snmp_query["id"] . ",\"" . encode_data_query_index($row["snmp_index"]) . "\");'><span id='text$query_row" . "_" . $column_counter . "'></span></td>";
									}

									$column_counter++;
								}
							}
						}

						print "<td align='right'>";
						print "<input type='checkbox' name='sg_$query_row' id='sg_$query_row' onClick='dq_update_selection_indicators();'>";
						print "</td>";
						print "</tr>\n";

						$row_counter++;
					}
					}

					if ($total_rows > $row_limit) {
						print $nav;
					}
				}else{
					print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td colspan='2' style='color: red; font-size: 12px; font-weight: bold;'>Search Returned no Rows.</td></tr>\n";
				}
			}else{
				print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td colspan='2' style='color: red; font-size: 12px; font-weight: bold;'>Error in data query.</td></tr>\n";
			}

			print "</table>";

			/* draw the graph template drop down here */
			$data_query_graphs = db_fetch_assoc("select snmp_query_graph.id,snmp_query_graph.name from snmp_query_graph where snmp_query_graph.snmp_query_id=" . $snmp_query["id"] . " order by snmp_query_graph.name");

			if (sizeof($data_query_graphs) == 1) {
				echo "<input type='hidden' id='sgg_" . $snmp_query["id"] . "' name='sgg_" . $snmp_query["id"] . "' value='" . $data_query_graphs[0]["id"] . "'>\n";
			}elseif (sizeof($data_query_graphs) > 1) {
				print "	<table align='center' width='100%'>
						<tr>
							<td width='1' valign='top'>
								<img src='images/arrow.gif' alt=''>&nbsp;
							</td>
							<td align='right'>
								<span style='font-size: 12px; font-style: italic;'>Select a graph type:</span>&nbsp;
								<select name='sgg_" . $snmp_query["id"] . "' id='sgg_" . $snmp_query["id"] . "' onChange='dq_update_deps(" . $snmp_query["id"] . "," . (isset($column_counter) ? $column_counter:"") . ");'>
									"; html_create_list($data_query_graphs,"name","id","0"); print "
								</select>
							</td>
						</tr>
					</table>";
			}

			print "<br>";

			$script .= "dq_update_deps(" . $snmp_query["id"] . "," . $num_visible_fields . ");\n";
		}
		}
	}

	if (strlen($script)) {
		$script .= "</script>\n";
		print $script;
	}

	form_hidden_box("save_component_graph", "1", "");
	form_hidden_box("host_id", $host["id"], "0");
	form_hidden_box("host_template_id", $host["host_template_id"], "0");

	if (isset($_SERVER["HTTP_REFERER"]) && !substr_count($_SERVER["HTTP_REFERER"], "graphs_new")) {
		$_REQUEST["returnto"] = basename($_SERVER["HTTP_REFERER"]);
	}
	load_current_session_value("returnto", "sess_graphs_new_returnto", "");

	form_save_button($_REQUEST["returnto"]);

	print "<script type='text/javascript'>dq_update_selection_indicators();</script>\n";
	print "<script type='text/javascript'>gt_update_selection_indicators();</script>\n";
}
?>
