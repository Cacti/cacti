<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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
include_once("./lib/snmp.php");
include_once("./lib/utility.php");
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
			if (preg_match('/^cg_\d+$/', $var)) {
				$graph_template_id = preg_replace('/^cg_(\d+)$/', "\\1", $var);
				
				$selected_graphs["cg"][$graph_template_id][$graph_template_id] = true;
			}elseif (preg_match('/^ccg$/', $var)) {
				$selected_graphs["cg"]{$_POST["cg_g"]}{$_POST["cg_g"]} = true;
			}elseif (preg_match('/^sg_\d+_\S+$/', $var)) {
				$snmp_query_id = preg_replace('/^sg_(\d+)_(\S+)$/', "\\1", $var);
				$snmp_query_graph_id = $_POST{"sgg_" . $snmp_query_id}; 
				$snmp_index = preg_replace('/^sg_(\d+)_(\S+)$/', "\\2", $var);
				
				$selected_graphs["sg"][$snmp_query_id][$snmp_query_graph_id][$snmp_index] = true;
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
	data_query($_GET["host_id"], $_GET["id"]);
}

/* -------------------
    New Graph Functions
   ------------------- */

function host_new_graphs_save() {
	global $struct_graph, $struct_data_source, $struct_graph_item, $struct_data_source_item, $paths;
	
	$selected_graphs_array = unserialize(stripslashes($_POST["selected_graphs_array"]));
	
	/* form an array that contains all of the data on the previous form */
	while (list($var, $val) = each($_POST)) {
		if (preg_match("/^g_(\d+)_(\d+)_([^_]+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: (opt) snmp index, 4: field_name */
			if (empty($matches[1])) { /* this is a new graph from template field */
				$values["cg"]{$matches[2]}["graph_template"]{$matches[4]} = $val;
			}else{ /* this is a data query field */
				$values["sg"]{$matches[1]}{$matches[2]}["graph_template"]{$matches[4]} = $val;
			}
		}elseif (preg_match("/^gi_(\d+)_(\d+)_(\d+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: graph_template_input_id, 4:field_name */
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
		}elseif (preg_match("/^d_(\d+)_(\d+)_(\d+)_([^_]+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4: (opt) snmp_index, 5:field_name */
			if (empty($matches[1])) { /* this is a new graph from template field */
				$values["cg"]{$matches[2]}["data_template"]{$matches[5]} = $val;
			}else{ /* this is a data query field */
				$values["sg"]{$matches[1]}{$matches[2]}["data_template"]{$matches[5]} = $val;
			}
		}elseif (preg_match("/^di_(\d+)_(\d+)_(\d+)_(\d+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:local_data_template_rrd_id, 5:field_name */
			if (empty($matches[1])) { /* this is a new graph from template field */
				$values["cg"]{$matches[2]}["data_template_item"]{$matches[4]}{$matches[5]} = $val;
			}else{ /* this is a data query field */
				$values["sg"]{$matches[1]}{$matches[2]}["data_template_item"]{$matches[4]}{$matches[5]} = $val;
			}
		}
	}
	
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
					$snmp_query_array["snmp_index_on"] = $_POST{"sg_" . $form_id1};
					$snmp_query_array["snmp_query_graph_id"] = $form_id2;
				}
				
				$graph_template_id = db_fetch_cell("select graph_template_id from snmp_query_graph where id=" . $snmp_query_array["snmp_query_graph_id"]);
			}
			
			if ($current_form_type == "cg") {
				$return_array = create_complete_graph_from_template($graph_template_id, $_POST["host_id"], "", $values["cg"]);
			}elseif ($current_form_type == "sg") {
				while (list($snmp_index, $true) = each($snmp_index_array)) {
					$snmp_query_array["snmp_index"] = $snmp_index;
					
					$return_array = create_complete_graph_from_template($graph_template_id, $_POST["host_id"], $snmp_query_array, $values["sg"]{$snmp_query_array["snmp_query_id"]});
				}
			}
		}
	}
	
	/* lastly push host-specific information to our data sources */
	push_out_host($_POST["host_id"],0);
}

function host_new_graphs($host_id, $host_template_id, $selected_graphs_array) {
	include_once("./include/top_header.php");
	
	global $colors, $paths, $struct_graph, $row_counter, $struct_data_source, $struct_graph_item, $struct_data_source_item;
	
	print "<form method='post' action='graphs_new.php'>\n";
	
	$snmp_query_id = 0;
	while (list($form_type, $form_array) = each($selected_graphs_array)) {
		while (list($form_id1, $form_array2) = each($form_array)) {
			if ($form_type == "cg") {
				$graph_template_id = $form_id1;
				
				start_box("<strong>Create 1 Graph from Host Template", "98%", $colors["header"], "3", "center", "");
			}elseif ($form_type == "sg") {
				while (list($form_id2, $form_array3) = each($form_array2)) {
					$snmp_query_id = $form_id1;
					$snmp_query_graph_id = $form_id2;
					$num_graphs = sizeof($form_array3);
					
					$snmp_query = db_fetch_row("select
						snmp_query.name,
						snmp_query.xml_path
						from snmp_query
						where snmp_query.id=$snmp_query_id");
					
					$graph_template_id = db_fetch_cell("select graph_template_id from snmp_query_graph where id=$snmp_query_graph_id");
					
					/* list each index that was selected */
					$i = 0;
					while (list($snmp_index, $one) = each($form_array3)) {
						$snmp_indexes[$snmp_query_id][$i] = $snmp_index;
						$i++;
					}
				}
				
				/* DRAW: Data Query */
				start_box("<strong>Create $num_graphs Graph" . (($num_graphs>1) ? "s" : "") . " from Data Query", "98%", $colors["header"], "3", "center", "");
				
				print "<tr><td colspan='2' bgcolor='#" . $colors["header_panel"] . "'><span style='font-size: 10px; color: white;'><strong>Data Query</strong> [" . $snmp_query["name"] . "]</span></td></tr>";
				
				$fields = db_fetch_assoc("select
					data_template.id as data_template_id,
					data_template.name,
					data_input_fields.name as field_name,
					data_input_fields.id as data_input_field_id
					from snmp_query_field,data_input_fields,data_template,snmp_query_graph,snmp_query_graph_rrd
					where snmp_query_field.data_input_field_id=data_input_fields.id
					and snmp_query_field.snmp_query_id=snmp_query_graph.snmp_query_id
					and snmp_query_graph.id=snmp_query_graph_rrd.snmp_query_graph_id
					and snmp_query_graph_rrd.data_template_id=data_template.id
					and snmp_query_field.snmp_query_id=$snmp_query_id
					and snmp_query_graph_rrd.snmp_query_graph_id=$snmp_query_graph_id
					and snmp_query_field.action_id=1
					group by snmp_query_graph_rrd.data_template_id");
				
				if (sizeof($fields) > 0) {
				foreach ($fields as $field) {
					print "	<tr bgcolor='#" . $colors["form_alternate1"] . "'>
							<td width='50%'>
								<font class='textEditTitle'>" . $field["name"] . " -> " . $field["field_name"] . "</font><br>
								The data for each new graph will be indexed off of the following field. Certain fields may
								have been removed from the list if they do not contain unique data. It is best to choose a field whose
								value never changes.
							</td>";
					
					$snmp_queries = get_data_query_array($snmp_query_id);
					
					$xml_outputs = array();
					
					/* create an SQL string that contains each index in this snmp_index_id */
					$sql_or = array_to_sql_or($snmp_indexes[$snmp_query_id], "snmp_index");
					
					/* list each of the input fields for this snmp query */
					while (list($field_name, $field_array) = each($snmp_queries["fields"])) {
						if ($field_array["direction"] == "input") {
							/* create a list of all values for this index */
							$field_values = db_fetch_assoc("select field_value from host_snmp_cache where host_id=$host_id and snmp_query_id=$snmp_query_id and field_name='$field_name' and $sql_or");
							
							/* aggregate the above list so there is no duplicates */
							$aggregate_field_values = array_rekey($field_values, "field_value", "field_value");
							
							/* fields that contain duplicate or empty values are not suitable to index off of */
							if (!((sizeof($aggregate_field_values) < sizeof($field_values)) || (in_array("", $aggregate_field_values) == true) || (sizeof($aggregate_field_values) == 0))) {
								$xml_outputs[$field_name] = $field_name . " (" . $field_array["name"] . ")";
							}
						}
					}
					
					print "<td>";
					form_dropdown("sg_" . $snmp_query_id,$xml_outputs,"","","","","");
					print "</td>";
					
					print "</tr>\n";
				}
				}else{ print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td colspan='2' style='font-weight: bold; color: red;'>There appears to be a problem with your data query. Please make sure you have made at least one data template association.</td></tr>";
				}
			}
			
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
			
			$graph_inputs = db_fetch_assoc("select
				*
				from graph_template_input
				where graph_template_id=" . $graph_template_id . "
				and column_name != 'task_item_id'
				order by name");
			
			reset($struct_graph);
			$drew_items = false;
			
			while (list($field_name, $field_array) = each($struct_graph)) {
				if ($graph_template{"t_" . $field_name} == "on") {
					if ($drew_items == false) {
						print "<tr><td colspan='2' bgcolor='#" . $colors["header_panel"] . "'><span style='font-size: 10px; color: white;'><strong>Graph</strong> [Template: " . $graph_template["graph_template_name"] . "]</span></td></tr>";
					}
					
					$row_counter = 1; /* so we have an all 'light' background */
					
					/* SUGGESTED VALUES: we must treat suggested values for snmp queries different because
					one entry here might might 20 graphs... so we can't automatically fill in the values. 
					if it is not an snmp query, automatically fill in the values right here. */
					if ((!empty($snmp_query_id)) && (sizeof(db_fetch_assoc("select id from snmp_query_graph_sv where snmp_query_graph_id=$snmp_query_graph_id and field_name='$field_name'")) > 0)) {
						print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>";
						print "<td><strong>" . $struct_graph[$field_name]["friendly_name"] . "</strong></td>";
						print "<td><em>Using Suggested Values</em> (see Data Query)</td>"; 
						print "</td></tr>\n";
					}else{
						draw_edit_form_row($field_array, "g_" . $snmp_query_id . "_" . $graph_template_id . "_0_" . $field_name, (isset($graph_template[$field_name]) ? $graph_template[$field_name] : ""));
					}
					
					$drew_items = true;
				}
			}
			
			/* DRAW: Graphs Inputs */
			if (sizeof($graph_inputs) > 0) {
			foreach ($graph_inputs as $graph_input) {
				if ($drew_items == false) {
					print "<tr><td colspan='2' bgcolor='#" . $colors["header_panel"] . "'><span style='font-size: 10px; color: white;'><strong>Graph Items</strong> [Template: " . $graph_template_name . "]</span></td></tr>";
				}
				
				$row_counter = 1; /* so we have an all 'light' background */
				
				$current_value = db_fetch_cell("select 
					graph_templates_item." . $graph_input["column_name"] . "
					from graph_templates_item,graph_template_input_defs 
					where graph_template_input_defs.graph_template_item_id=graph_templates_item.id 
					and graph_template_input_defs.graph_template_input_id=" . $graph_input["id"] . "
					and graph_templates_item.graph_template_id=" . $graph_template_id . "
					limit 0,1");
				
				print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>";
				
				/* fill in the field array with some values from the graph input */
				$struct_graph_item{$graph_input["column_name"]}["friendly_name"] = $graph_input["name"];
				$struct_graph_item{$graph_input["column_name"]}["description"] = $graph_input["description"];
				
				draw_edit_form_row($struct_graph_item{$graph_input["column_name"]}, "gi_" . $snmp_query_id . "_" . $graph_template_id . "_" . $graph_input["id"] . "_" . $graph_input["column_name"], $current_value);
				
				print "</tr>\n";
				
				$drew_items = true;
			}
			}
			
			/* DRAW: Data Sources */
			if (sizeof($data_templates) > 0) {
			foreach ($data_templates as $data_template) {
				reset($struct_data_source);
				$drew_items = false;
				
				while (list($field_name, $field_array) = each($struct_data_source)) {
					if (isset($data_template{"t_" . $field_name})) {
						if ($data_template{"t_" . $field_name} == "on") {
							if ($drew_items == false) {
								print "<tr><td colspan='2' bgcolor='#" . $colors["header_panel"] . "'><span style='font-size: 10px; color: white;'><strong>Data Source</strong> [Template: " . $data_template["data_template_name"] . "]</span></td></tr>";
							}
							
							$row_counter = 1; /* so we have an all 'light' background */
							
							/* SUGGESTED VALUES: we must treat suggested values for snmp queries different because
							one entry here might might 20 graphs... so we can't automatically fill in the values. 
							if it is not an snmp query, automatically fill in the values right here. */
							if ((!empty($snmp_query_id)) && (sizeof(db_fetch_assoc("select id from snmp_query_graph_rrd_sv where snmp_query_graph_id=$snmp_query_graph_id and data_template_id=" . $data_template["data_template_id"] . " and field_name='$field_name'")) > 0)) {
								print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>";
								print "<td><strong>" . $struct_data_source[$field_name]["friendly_name"] . "</strong></td>";
								print "<td><em>Using Suggested Values</em> (see Data Query)</td>"; 
								print "</td></tr>\n";
							}else{
								draw_edit_form_row($field_array, "d_" . $snmp_query_id . "_" . $graph_template_id . "_" . $data_template["data_template_id"] . "_0_" . $field_name, $data_template[$field_name]);
							}
							
							$drew_items = true;
						}
					}
				}
				
				/* DRAW: Data Source Items */
				$data_template_items = db_fetch_assoc("select
					data_template_rrd.*
					from data_template_rrd
					where data_template_rrd.data_template_id=" . $data_template["data_template_id"] . "
					and local_data_id=0");
				
				if (sizeof($data_template_items) > 0) {
				foreach ($data_template_items as $data_template_item) {
					reset($struct_data_source_item);
					$drew_items = false;
					
					while (list($field_name, $field_array) = each($struct_data_source_item)) {
						if ($data_template_item{"t_" . $field_name} == "on") {
							$row_counter = 1; /* so we have an all 'light' background */
							
							/* SUGGESTED VALUES: we must treat suggested values for snmp queries different because
							one entry here might might 20 graphs... so we can't automatically fill in the values. 
							if it is not an snmp query, automatically fill in the values right here. */
							if ((!empty($snmp_query_id)) && (sizeof(db_fetch_assoc("select id from snmp_query_graph_rrd_sv where snmp_query_graph_id=$snmp_query_graph_id and data_template_id=" . $data_template["data_template_id"] . " and field_name='$field_name'")) > 0)) {
								print "<tr bgcolor='#" . $colors["form_alternate1"] . "'>";
								print "<td><strong>" . $struct_data_source_item[$field_name]["friendly_name"] . "</strong></td>";
								print "<td><em>Using Suggested Values</em> (see Data Query)</td>"; 
								print "</td></tr>\n";
							}else{
								draw_edit_form_row($field_array, "di_" . $snmp_query_id . "_" . $graph_template_id . "_" . $data_template["data_template_id"] . "_" . $data_template_item["id"] . "_" . $field_name, $data_template_item[$field_name]);
							}
							
							$drew_items = true;
						}
					}
				}
				}
			}
			}
			
			end_box();
		}
	}
	
	form_hidden_id("host_template_id",$host_template_id);
	form_hidden_id("host_id",$host_id);
	form_hidden_box("save_component_new_graphs","1","");
	print "<input type='hidden' name='selected_graphs_array' value='" . serialize($selected_graphs_array) . "'>\n";
	
	form_save_button("graphs_new.php?id=$host_id");
	
	include_once("./include/bottom_footer.php");
}

/* -------------------
    Graph Functions
   ------------------- */

function graphs() {
	global $colors;
	
	/* use the first host in the list as the default */
	if (!isset($_SESSION["sess_graphs_new_host_id"])) {
		$_REQUEST["host_id"] = db_fetch_cell("select id from host order by description,hostname limit 1");
	}
	
	/* remember these search fields in session vars so we don't have to keep passing them around */
	if (isset($_REQUEST["host_id"])) { $_SESSION["sess_graphs_new_host_id"] = $_REQUEST["host_id"]; }else{ $_REQUEST["host_id"] = $_SESSION["sess_graphs_new_host_id"]; }
	
	$host = db_fetch_row("select id,description,hostname,host_template_id from host where id=" . $_REQUEST["host_id"]);
	
	?>
	<form name="form_graph_id">
	<table width="98%" align="center">
		<tr>
			<td class="textInfo" colspan="2">
				<?php print $host["description"];?> (<?php print $host["hostname"];?>)
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
	
	if (!empty($host["host_template_id"])) {
		start_box("<strong>Host Template</strong> [" . db_fetch_cell("select name from host_template where id=" . $host["host_template_id"]) . "]", "98%", $colors["header"], "3", "center", "");
		
		print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
				<td class='textSubHeaderDark'>Graph Template Name</td>
				<td width='1%' align='center' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAll(\"cg\");gt_update_selection_indicators();'></td>\n
			</tr>\n";
		
		$graph_templates = db_fetch_assoc("select
			graph_templates.id as graph_template_id,
			graph_templates.name as graph_template_name
			from host_template_graph, graph_templates
			where host_template_graph.graph_template_id=graph_templates.id
			and host_template_graph.host_template_id=" . $host["host_template_id"] . "
			order by graph_templates.name");
		
		$i = 0;
		
		$template_graphs = db_fetch_assoc("select graph_local.graph_template_id from graph_local,host_template_graph where graph_local.graph_template_id=host_template_graph.graph_template_id and graph_local.host_id=" . $host["id"] . " group by graph_local.graph_template_id");
		
		print "<script type='text/javascript'>\n<!--\n";
		print "var gt_created_graphs = new Array(";
		
		if (sizeof($template_graphs) > 0) {
			$cg_ctr = 0;
			foreach ($template_graphs as $template_graph) {
				print (($cg_ctr > 0) ? "," : "") . "'" . $template_graph["graph_template_id"] . "'"; 
				
				$cg_ctr++;
			}
		}
		
		print ")\n";
		print "//-->\n</script>\n";
		
		/* create a row for each graph template associated with the host template */
		if (sizeof($graph_templates) > 0) {
		foreach ($graph_templates as $graph_template) {
			$query_row = $graph_template["graph_template_id"];
			
			print "<tr id='gt_line$query_row' bgcolor='#" . (($i % 2 == 0) ? "ffffff" : $colors["light"]) . "'>"; $i++;
			
			print "		<td onClick='gt_select_line(" . $graph_template["graph_template_id"] . ");'><span id='gt_text$query_row" . "_0'>
						<span id='gt_text$query_row" . "_0'><strong>Create:</strong> " . $graph_template["graph_template_name"] . "</span>
					</td>
					<td align='right'>";
						form_checkbox("cg_" . $graph_template["graph_template_id"],"","","",0);
			print "		</td>
				</tr>";
		}
		}
		
		print "<script type='text/javascript'>gt_update_deps(1);</script>\n";
		
		end_box();
	}
	
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
		$xml_array = get_data_query_array($snmp_query["id"]);
		$xml_outputs = array();
		
		$num_input_fields = 0;
		
		if ($xml_array != false) {
			/* loop through once so we can find out how many input fields there are */
			while (list($field_name, $field_array) = each($xml_array["fields"])) {
				if ($field_array["direction"] == "input") {
					$num_input_fields++;
				}
			}
			
			reset($xml_array["fields"]);
			$snmp_query_indexes = array();
			$num_visible_fields{$snmp_query["id"]} = 0;
			$i = 0;
		}
		
		$snmp_query_graphs = db_fetch_assoc("select snmp_query_graph.id,snmp_query_graph.name from snmp_query_graph where snmp_query_graph.snmp_query_id=" . $snmp_query["id"] . " order by snmp_query_graph.name");
		
		if (sizeof($snmp_query_graphs) > 0) {
			print "<script type='text/javascript'>\n<!--\n";
			
			foreach ($snmp_query_graphs as $snmp_query_graph) {
				$created_graphs = db_fetch_assoc("select
					data_local.snmp_index
					from data_local,data_template_data
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
					print (($cg_ctr > 0) ? "," : "") . "'" . $created_graph["snmp_index"] . "'"; 
					
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
			
			while (list($field_name, $field_array) = each($xml_array["fields"])) {
				if ($field_array["direction"] == "input") {
					$i++;
					
					$raw_data = db_fetch_assoc("select field_value,snmp_index from host_snmp_cache where host_id=" . $host["id"] . " and field_name='$field_name' and snmp_query_id=" . $snmp_query["id"]);
					
					/* don't even both to display the column if it has no data */
					if (sizeof($raw_data) > 0) {
						/* draw each header item <TD> */
						$html_dq_header .= "<td height='1'><strong><font color='#" . $colors["header_text"] . "'>" . $field_array["name"] . "</font></strong></td>\n";
						
						foreach ($raw_data as $data) {
							$snmp_query_data[$field_name]{$data["snmp_index"]} = $data["field_value"];
							$snmp_query_indexes{$data["snmp_index"]} = $data["snmp_index"];
						}
						
						$num_visible_fields{$snmp_query["id"]}++;
					}elseif (sizeof($raw_data) == 0) {
						/* we are choosing to not display this column, so unset the associated
						field in the xml array so it is not drawn */
						unset($xml_array["fields"][$field_name]);
					}
				}
			}
			
			if ($num_visible_fields{$snmp_query["id"]} == 0) {
				print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td>This data query returned 0 rows, perhaps there was a problem executing this
					data query. You can <a href='host.php?action=query_verbose&id=" . $snmp_query["id"] . "&host_id=" . $host["id"] . "'>run this data 
					query in debug mode</a> to get more information.</td></tr>\n";
			}else{
				print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
						$html_dq_header
						<td width='1%' align='center' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAll(\"sg_" . $snmp_query["id"] . "\");dq_update_selection_indicators();'></td>\n
					</tr>\n";
				
			}
			
			 $row_counter = 0;
			if (sizeof($snmp_query_indexes) > 0) {
			while (list($snmp_index, $snmp_index) = each($snmp_query_indexes)) {
				$query_row = $snmp_query["id"] . "_" . $snmp_index;
				
				print "<tr id='line$query_row' bgcolor='#" . (($i % 2 == 0) ? "ffffff" : $colors["light"]) . "'>"; $i++;
				
				$column_counter = 0;
				reset($xml_array["fields"]);
				while (list($field_name, $field_array) = each($xml_array["fields"])) {
					if ($field_array["direction"] == "input") {
						if (isset($snmp_query_data[$field_name][$snmp_index])) {
							print "<td onClick='dq_select_line(" . $snmp_query["id"] . ",\"$snmp_index\");'><span id='text$query_row" . "_" . $column_counter . "'>" . $snmp_query_data[$field_name][$snmp_index] . "</span></td>";
						}else{
							print "<td onClick='dq_select_line(" . $snmp_query["id"] . ",\"$snmp_index\");'><span id='text$query_row" . "_" . $column_counter . "'></span></td>";
						}
						
						$column_counter++;
					}
				}
				
				print "<td align='right'>";
				print "<input type='checkbox' name='sg_$query_row' id='sg_$query_row' onClick='dq_update_selection_indicators();'>";
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
							<select name='sgg_" . $snmp_query["id"] . "' id='sgg_" . $snmp_query["id"] . "' onChange='dq_update_deps(" . $snmp_query["id"] . "," . $num_visible_fields{$snmp_query["id"]} . ");'>
								"; create_list($data_query_graphs,"name","id","0"); print "
							</select>
						</td>
					</tr>
				</table>";
		}
		
		print "<br>";
	}
	}
	
	form_hidden_box("save_component_graph", "1", "");
	form_hidden_box("host_id", $host["id"], "0");
	form_hidden_box("host_template_id", $host["host_template_id"], "0");
	
	form_save_button("graphs_new.php");
	
	print "<script type='text/javascript'>dq_update_selection_indicators();</script>\n";
	print "<script type='text/javascript'>gt_update_selection_indicators();</script>\n";
	
	reset($snmp_queries);
	
	if (sizeof($snmp_queries) > 0) {
	foreach ($snmp_queries as $snmp_query) {
		$num_input_fields = $num_visible_fields{$snmp_query["id"]};
		
		print "<script type='text/javascript'>dq_update_deps(" . $snmp_query["id"] . "," . ($num_input_fields) . ");</script>\n";
	}
	}
}
?>
