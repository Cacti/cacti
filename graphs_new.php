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

include("./include/auth.php");
include_once("./lib/data_query.php");
include_once("./lib/utility.php");
include_once("./lib/sort.php");
include_once("./lib/html_form_template.php");
include_once("./lib/template.php");

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
			}elseif ($current_form_type == "sg") {
				while (list($snmp_index, $true) = each($snmp_index_array)) {
					$snmp_query_array["snmp_index"] = decode_data_query_index($snmp_index, $snmp_query_array["snmp_query_id"], $_POST["host_id"]);

					$return_array = create_complete_graph_from_template($graph_template_id, $_POST["host_id"], $snmp_query_array, $values["sg"]{$snmp_query_array["snmp_query_id"]});

					debug_log_insert("new_graphs", "Created graph: " . get_graph_title($return_array["local_graph_id"]));
				}
			}
		}
	}

	/* lastly push host-specific information to our data sources */
	push_out_host($_POST["host_id"],0);
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

				html_start_box("<strong>Create Graph from '" . db_fetch_cell("select name from graph_templates where id=$graph_template_id") . "'", "98%", $colors["header"], "3", "center", "");
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
				html_start_box("<strong>Create $num_graphs Graph" . (($num_graphs>1) ? "s" : "") . " from '" . db_fetch_cell("select name from snmp_query where id=$snmp_query_id") . "'", "98%", $colors["header"], "3", "center", "");
			}

			/* ================= input validation ================= */
			input_validate_input_number($graph_template_id);
			/* ==================================================== */

			$data_templates = db_fetch_assoc("select
				data_template.name as data_template_name,
				data_template_rrd.data_source_name,
				data_template_data.*
				from data_template, data_template_rrd, data_template_data, graph_templates_item
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
				from graph_templates, graph_templates_graph
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

	form_save_button("graphs_new.php?host_id=$host_id");

	include_once("./include/bottom_footer.php");
}

/* -------------------
    Graph Functions
   ------------------- */

function graphs() {
	global $colors;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("host_id"));
	/* ==================================================== */

	/* use the first host in the list as the default */
	if ((!isset($_SESSION["sess_graphs_new_host_id"])) && (empty($_REQUEST["host_id"]))) {
		$_REQUEST["host_id"] = db_fetch_cell("select id from host order by description,hostname limit 1");
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	if (isset($_REQUEST["host_id"])) { $_SESSION["sess_graphs_new_host_id"] = $_REQUEST["host_id"]; }else{ $_REQUEST["host_id"] = $_SESSION["sess_graphs_new_host_id"]; }

	$host = db_fetch_row("select id,description,hostname,host_template_id from host where id=" . $_REQUEST["host_id"]);

	$debug_log = debug_log_return("new_graphs");

	if (!empty($debug_log)) {
		debug_log_clear("new_graphs");
		?>
		<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>
			<tr bgcolor="<?php print $colors["light"];?>">
				<td style="padding: 3px; font-family: monospace;">
					<?php print $debug_log;?>
				</td>
			</tr>
		</table>
		<br>
		<?php
	}
	?>

	<form name="form_graph_id">
	<table width="98%" align="center">
		<tr>
			<td class="textInfo" colspan="2">
				<?php print $host["description"];?> (<?php print $host["hostname"];?>)
			</td>
			<td align="right" class="textInfo" style="color: #aaaaaa;">
				<?php
				if (!empty($host["host_template_id"])) {
					print db_fetch_cell("select name from host_template where id=" . $host["host_template_id"]);
				}
				?>
			</td>
		</tr>
		<tr>
			<td>
			</td>
		</tr>

		<tr>
			<td class="textArea" style="padding: 3px;" width="300" nowrap>
				Create new graphs for the following host:
			</td>
			<td class="textInfo" rowspan="2" valign="top">
				<span style="color: #c16921;">*</span><a href="host.php?action=edit&id=<?php print $_REQUEST["host_id"];?>">Edit this Host</a><br>
				<span style="color: #c16921;">*</span><a href="host.php?action=edit">Create New Host</a>
			</td>
		</tr>
			<td>
				<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
					<?php
					$hosts = db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname");

					if (sizeof($hosts) > 0) {
					foreach ($hosts as $item) {
						print "<option value='graphs_new.php?host_id=" . $item["id"] . "'"; if ($_REQUEST["host_id"] == $item["id"]) { print " selected"; } print ">" . $item["name"] . "</option>\n";
					}
					}
					?>
				</select>
			</td>
		</tr>
	</table>


	</form>
	<form name="chk" method="post" action="graphs_new.php">
	<?php
	$total_rows = sizeof(db_fetch_assoc("select graph_template_id from host_graph where host_id=" . $_REQUEST["host_id"]));

	/* we give users the option to turn off the javascript features for data queries with lots of rows */
	if (read_config_option("max_data_query_javascript_rows") >= $total_rows) {
		$use_javascript = true;
	}else{
		$use_javascript = false;
	}

	html_start_box("<strong>Graph Templates</strong>", "98%", $colors["header"], "3", "center", "");

	print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
			<td class='textSubHeaderDark'>Graph Template Name</td>
			<td width='1%' align='center' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all_cg' title='Select All' onClick='SelectAll(\"cg\",this.checked);gt_update_selection_indicators();'></td>\n
		</tr>\n";

	$graph_templates = db_fetch_assoc("select
		graph_templates.id as graph_template_id,
		graph_templates.name as graph_template_name
		from host_graph,graph_templates
		where host_graph.graph_template_id=graph_templates.id
		and host_graph.host_id=" . $_REQUEST["host_id"] . "
		order by graph_templates.name");

	$template_graphs = db_fetch_assoc("select
		graph_local.graph_template_id
		from graph_local,host_graph
		where graph_local.graph_template_id=host_graph.graph_template_id
		and graph_local.host_id=host_graph.host_id
		and graph_local.host_id=" . $host["id"] . "
		group by graph_local.graph_template_id");

	print "<script type='text/javascript'>\nvar gt_created_graphs = new Array()\n</script>\n";

	if ((sizeof($template_graphs) > 0) && ($use_javascript == true)) {
		print "<script type='text/javascript'>\n<!--\n";
		print "var gt_created_graphs = new Array(";

		$cg_ctr = 0;
		foreach ($template_graphs as $template_graph) {
			print (($cg_ctr > 0) ? "," : "") . "'" . $template_graph["graph_template_id"] . "'";

			$cg_ctr++;
		}

		print ")\n";
		print "//-->\n</script>\n";
	}

	/* create a row for each graph template associated with the host template */
	$i = 0;
	if (sizeof($graph_templates) > 0) {
	foreach ($graph_templates as $graph_template) {
		$query_row = $graph_template["graph_template_id"];

		print "<tr id='gt_line$query_row' bgcolor='#" . (($i % 2 == 0) ? "ffffff" : $colors["light"]) . "'>"; $i++;

		print "		<td" . (($use_javascript == true) ? " onClick='gt_select_line(" . $graph_template["graph_template_id"] . ");'" : "") . "><span id='gt_text$query_row" . "_0'>
					<span id='gt_text$query_row" . "_0'><strong>Create:</strong> " . $graph_template["graph_template_name"] . "</span>
				</td>
				<td align='right'>
					<input type='checkbox' name='cg_$query_row' id='cg_$query_row'" . (($use_javascript == true) ? " onClick='gt_update_selection_indicators();'" : "") . ">
				</td>
			</tr>";
	}
	}

	if ($use_javascript == true) {
		print "<script type='text/javascript'>gt_update_deps(1);</script>\n";
	}

	$available_graph_templates = db_fetch_assoc("SELECT
		graph_templates.id, graph_templates.name
		FROM snmp_query_graph RIGHT JOIN graph_templates
		ON snmp_query_graph.graph_template_id = graph_templates.id
		WHERE (((snmp_query_graph.name) Is Null)) ORDER BY graph_templates.name");

	/* create a row at the bottom that lets the user create any graph they choose */
	print "	<tr bgcolor='#" . (($i % 2 == 0) ? "ffffff" : $colors["light"]) . "'>
			<td colspan='2' width='60' nowrap>
				<strong>Create:</strong>&nbsp;";
				form_dropdown("cg_g", $available_graph_templates, "name", "id", "", "(Select a graph type to create)", "", "font-size: 10px;");
	print "		</td>
		</tr>";

	html_end_box();

	$snmp_queries = db_fetch_assoc("select
		snmp_query.id,
		snmp_query.name,
		snmp_query.xml_path
		from snmp_query,host_snmp_query
		where host_snmp_query.snmp_query_id=snmp_query.id
		and host_snmp_query.host_id=" . $host["id"] . "
		order by snmp_query.name");

	print "<script type='text/javascript'>\nvar created_graphs = new Array()\n</script>\n";

	if (sizeof($snmp_queries) > 0) {
	foreach ($snmp_queries as $snmp_query) {
		unset($total_rows);

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
						$total_rows = db_fetch_cell("select count(*) from host_snmp_cache where host_id=" . $host["id"] . " and snmp_query_id=" . $snmp_query["id"] . " and field_name='$field_name'");
					}
				}
			}
		}

		if (!isset($total_rows)) {
			$total_rows = 0;
		}

		/* we give users the option to turn off the javascript features for data queries with lots of rows */
		if (read_config_option("max_data_query_javascript_rows") >= $total_rows) {
			$use_javascript = true;
		}else{
			$use_javascript = false;
		}

		$snmp_query_graphs = db_fetch_assoc("select snmp_query_graph.id,snmp_query_graph.name from snmp_query_graph where snmp_query_graph.snmp_query_id=" . $snmp_query["id"] . " order by snmp_query_graph.name");

		if ((sizeof($snmp_query_graphs) > 0) && ($use_javascript == true)) {
			print "<script type='text/javascript'>\n<!--\n";

			foreach ($snmp_query_graphs as $snmp_query_graph) {
				$created_graphs = db_fetch_assoc("select distinct
					data_local.snmp_index
					from (data_local,data_template_data)
					left join data_input_data on data_template_data.id=data_input_data.data_template_data_id
					left join data_input_fields on data_input_data.data_input_field_id=data_input_fields.id
					where data_local.id=data_template_data.local_data_id
					and data_input_fields.type_code='output_type'
					and data_input_data.value='" . $snmp_query_graph["id"] . "'
					and data_local.host_id=" . $host["id"]);

				print "created_graphs[" . $snmp_query_graph["id"] . "] = new Array(";

				$cg_ctr = 0;
				if (sizeof($created_graphs) > 0) {
				foreach ($created_graphs as $created_graph) {
					print (($cg_ctr > 0) ? "," : "") . "'" . encode_data_query_index($created_graph["snmp_index"]) . "'";

					$cg_ctr++;
				}
				}

				print ")\n";
			}

			print "//-->\n</script>\n";
		}

		print "	<table width='98%' style='background-color: #" . $colors["form_alternate2"] . "; border: 1px solid #" . $colors["header"] . ";' align='center' cellpadding='3' cellspacing='0'>\n
				<tr>
					<td bgcolor='#" . $colors["header"] . "' colspan='" . ($num_input_fields+1) . "'>
						<table  cellspacing='0' cellpadding='0' width='100%' >
							<tr>
								<td class='textHeaderDark'>
									<strong>Data Query</strong> [" . $snmp_query["name"] . "]
								</td>
								<td align='right' nowrap>
									<a href='graphs_new.php?action=query_reload&id=" . $snmp_query["id"] . "&host_id=" . $host["id"] . "'><img src='images/reload_icon_small.gif' alt='Reload Associated Query' border='0' align='absmiddle'></a>
								</td>
							</tr>
						</table>
					</td>
				</tr>";

		if ($xml_array != false) {
			$html_dq_header = "";
			$snmp_query_indexes = array();

			reset($xml_array["fields"]);
			while (list($field_name, $field_array) = each($xml_array["fields"])) {
				if ($field_array["direction"] == "input") {
					$raw_data = db_fetch_assoc("select field_value,snmp_index from host_snmp_cache where host_id=" . $host["id"] . " and field_name='$field_name' and snmp_query_id=" . $snmp_query["id"]);

					/* don't even both to display the column if it has no data */
					if (sizeof($raw_data) > 0) {
						/* draw each header item <TD> */
						$html_dq_header .= "<td height='1'><strong><font color='#" . $colors["header_text"] . "'>" . $field_array["name"] . "</font></strong></td>\n";

						foreach ($raw_data as $data) {
							$snmp_query_data[$field_name]{$data["snmp_index"]} = $data["field_value"];

							if (!in_array($data["snmp_index"], $snmp_query_indexes,TRUE)) {
								array_push($snmp_query_indexes, $data["snmp_index"]);
							}
						}

						$num_visible_fields++;
					}elseif (sizeof($raw_data) == 0) {
						/* we are choosing to not display this column, so unset the associated
						field in the xml array so it is not drawn */
						unset($xml_array["fields"][$field_name]);
					}
				}
			}

			/* if the user specified a prefered sort order; sort the list of indexes before displaying them */
			if (isset($xml_array["index_order_type"])) {
				if ($xml_array["index_order_type"] == "numeric") {
					usort($snmp_query_indexes, "usort_numeric");
				}else if ($xml_array["index_order_type"] == "alphabetic") {
					usort($snmp_query_indexes, "usort_alphabetic");
				}
			}

			if ($num_visible_fields == 0) {
				print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td>This data query returned 0 rows, perhaps there was a problem executing this
					data query. You can <a href='host.php?action=query_verbose&id=" . $snmp_query["id"] . "&host_id=" . $host["id"] . "'>run this data
					query in debug mode</a> to get more information.</td></tr>\n";
			}else{
				print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
						$html_dq_header
						<td width='1%' align='center' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all_" . $snmp_query["id"] . "' title='Select All' onClick='SelectAll(\"sg_" . $snmp_query["id"] . "\",this.checked);" . (($use_javascript == true) ? "dq_update_selection_indicators();" : "") . "'></td>\n
					</tr>\n";
			}

			$row_counter = 0;
			if (sizeof($snmp_query_indexes) > 0) {
			while (list($id, $snmp_index) = each($snmp_query_indexes)) {
				$query_row = $snmp_query["id"] . "_" . encode_data_query_index($snmp_index);

				print "<tr id='line$query_row' bgcolor='#" . (($row_counter % 2 == 0) ? "ffffff" : $colors["light"]) . "'>"; $i++;

				$column_counter = 0;
				reset($xml_array["fields"]);
				while (list($field_name, $field_array) = each($xml_array["fields"])) {
					if ($field_array["direction"] == "input") {
						if (isset($snmp_query_data[$field_name][$snmp_index])) {
							print "<td " . (($use_javascript == true) ? "onClick='dq_select_line(" . $snmp_query["id"] . ",\"" . encode_data_query_index($snmp_index) . "\");'" : "")  ."><span id='text$query_row" . "_" . $column_counter . "'>" . $snmp_query_data[$field_name][$snmp_index] . "</span></td>";
						}else{
							print "<td " . (($use_javascript == true) ? "onClick='dq_select_line(" . $snmp_query["id"] . ",\"" . encode_data_query_index($snmp_index) . "\");'" : "") . "><span id='text$query_row" . "_" . $column_counter . "'></span></td>";
						}

						$column_counter++;
					}
				}

				print "<td align='right'>";
				print "<input type='checkbox' name='sg_$query_row' id='sg_$query_row' " . (($use_javascript == true) ? "onClick='dq_update_selection_indicators();'" : "") . ">";
				print "</td>";
				print "</tr>\n";

				$row_counter++;
			}
			}
		}else{
			print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td colspan='2' style='color: red; font-size: 12px; font-weight: bold;'>Error in data query.</td></tr>\n";
		}

		print "</table>";

		/* draw the graph template drop down here */
		$data_query_graphs = db_fetch_assoc("select snmp_query_graph.id,snmp_query_graph.name from snmp_query_graph where snmp_query_graph.snmp_query_id=" . $snmp_query["id"] . " order by snmp_query_graph.name");

		if (sizeof($data_query_graphs) == 1) {
			form_hidden_box("sgg_" . $snmp_query["id"] . "' id='sgg_" . $snmp_query["id"], $data_query_graphs[0]["id"], "");
		}elseif (sizeof($data_query_graphs) > 1) {
			print "	<table align='center' width='98%'>
					<tr>
						<td width='1' valign='top'>
							<img src='images/arrow.gif' alt='' align='absmiddle'>&nbsp;
						</td>
						<td align='right'>
							<span style='font-size: 12px; font-style: italic;'>Select a graph type:</span>&nbsp;
							<select name='sgg_" . $snmp_query["id"] . "' id='sgg_" . $snmp_query["id"] . "' " . (($use_javascript == true) ? "onChange='dq_update_deps(" . $snmp_query["id"] . "," . $num_visible_fields . ");'" : "") . ">
								"; html_create_list($data_query_graphs,"name","id","0"); print "
							</select>
						</td>
					</tr>
				</table>";
		}

		print "<br>";

		if ($use_javascript == true) {
			print "<script type='text/javascript'>dq_update_deps(" . $snmp_query["id"] . "," . ($num_visible_fields) . ");</script>\n";
		}
	}
	}

	form_hidden_box("save_component_graph", "1", "");
	form_hidden_box("host_id", $host["id"], "0");
	form_hidden_box("host_template_id", $host["host_template_id"], "0");

	form_save_button("graphs_new.php");

	print "<script type='text/javascript'>dq_update_selection_indicators();</script>\n";
	print "<script type='text/javascript'>gt_update_selection_indicators();</script>\n";
}
?>