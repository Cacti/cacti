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

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
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
	if (isset($_POST["save_component_rra"])) {
		$save["id"] = $_POST["id"];
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save["x_files_factor"] = form_input_validate($_POST["x_files_factor"], "x_files_factor", "^[0-9]+(\.[0-9])?$", false, 3);
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
						db_execute("insert into rra_cf (rra_id,consolidation_function_id) 
							values ($rra_id," . $_POST["consolidation_function_id"][$i] . ")");
					}
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


function graphs() {
	global $colors;
	
	/* use the first host in the list as the default */
	if (!isset($_REQUEST["host_id"])) {
		$_REQUEST["host_id"] = db_fetch_cell("select id from host order by description,hostname limit 1");
	}
	
	$host = db_fetch_row("select id,description,hostname,host_template_id from host where id=" . $_REQUEST["host_id"]);
	
	?>
	<form name="form_graph_id">
	<table width="98%" align="center">
		<tr bgcolor="<?php print $colors["light"];?>">
			<td class="textArea" style="padding: 3px;">
				Create new graphs for the following host:
				
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
				
				<strong>(<a href="host.php?action=edit&id=<?php print $_REQUEST["host_id"];?>">Edit this Host</a>)</strong>
			</td>
		</tr>
		<tr>
			<td>
			</td>
		</tr>
		<tr>
			<td class="textInfo">
				<?php print $host["description"];?> (<?php print $host["hostname"];?>)
			</td>
		</tr>
	</table>
	</form>
	<form name="chk" method="post" action="graphs_new.php">
	<?php
	
	start_box("<strong>Create Graphs + Data Sources</strong>", "98%", $colors["header"], "3", "center", "");
	
	print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
			<td class='textSubHeaderDark'>Graph Template Name</td>
			<td width='1%' align='center' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAll(\"cg\")'></td>\n
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
		
	if (sizeof($template_graphs) > 0) {
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
	
	/* create a row at the bottom that lets the user create any graph they choose */
	form_alternate_row_color($colors["alternate"],$colors["light"],$i);
	print "		<td width='60' nowrap>
				<strong>Create:</strong>&nbsp;";
				form_dropdown("cg_g", db_fetch_assoc("select id,name from graph_templates order by name"), "name", "id", "", "", "");
	print "		</td>
			<td align='right'>";
				form_checkbox("ccg","","","",0);
	print "		</td>
		</tr>";
	
	end_box();
	
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
			while (list($field_name, $field_array) = each($xml_array["fields"][0])) {
				if ($field_array[0]["direction"] == "input") {
					$num_input_fields++;
				}
			}
			
			reset($xml_array["fields"][0]);
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
					and data_input_data.value='" . $snmp_query_graph["id"] . "'");
				
				
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
									<a href='host.php?action=query_reload&id=" . $snmp_query["id"] . "&host_id=" . $host["id"] . "'><img src='images/reload_icon_small.gif' alt='Reload Associated Query' border='0' align='absmiddle'></a>&nbsp;
									<a href='host.php?action=query_remove&id=" . $snmp_query["id"] . "&host_id=" . $host["id"] . "'><img src='images/delete_icon_large.gif' alt='Delete Associated Query' border='0' align='absmiddle'></a>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr bgcolor='#" . $colors["header_panel"] . "'>";
		
		if ($xml_array != false) {
			while (list($field_name, $field_array) = each($xml_array["fields"][0])) {
				$field_array = $field_array[0];
				
				if ($field_array["direction"] == "input") {
					$i++;
					
					$raw_data = db_fetch_assoc("select field_value,snmp_index from host_snmp_cache where host_id=" . $host["id"] . " and field_name='$field_name'");
					
					/* don't even both to display the column if it has no data */
					if (sizeof($raw_data) > 0) {
						/* draw each header item <TD> */
						DrawMatrixHeaderItem($field_array["name"],$colors["header_text"],1);
						
						/* draw the 'check all' box if we are at the end of the row */
						if ($i >= $num_input_fields) {
							print "<td width='1%' align='center' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAll(\"sg_" . $snmp_query["id"] . "\")'></td>\n";
						}
						
						foreach ($raw_data as $data) {
							$snmp_query_data[$field_name]{$data["snmp_index"]} = $data["field_value"];
							$snmp_query_indexes{$data["snmp_index"]} = $data["snmp_index"];
						}
						
						$num_visible_fields{$snmp_query["id"]}++;
					}elseif (sizeof($raw_data) == 0) {
						/* we are choosing to not display this column, so unset the associated
						field in the xml array so it is not drawn */
						unset($xml_array["fields"][0][$field_name]);
					}
				}
			}
			
			print "</tr>";
			
			 $row_counter = 0;
			if (sizeof($snmp_query_indexes) > 0) {
			while (list($snmp_index, $snmp_index) = each($snmp_query_indexes)) {
				$query_row = $snmp_query["id"] . "_" . $snmp_index;
				
				print "<tr id='line$query_row' bgcolor='#" . (($i % 2 == 0) ? "ffffff" : $colors["light"]) . "'>"; $i++;
				
				$column_counter = 0;
				reset($xml_array["fields"][0]);
				while (list($field_name, $field_array) = each($xml_array["fields"][0])) {
					if ($field_array[0]["direction"] == "input") {
						if (isset($snmp_query_data[$field_name][$snmp_index])) {
							print "<td onClick='dq_select_line(" . $snmp_query["id"] . ",\"$snmp_index\");'><span id='text$query_row" . "_" . $column_counter . "'>" . $snmp_query_data[$field_name][$snmp_index] . "</span></td>";
						}else{
							print "<td></td>";
						}
						
						$column_counter++;
					}
				}
				
				print "<td align='right'>";
				form_checkbox("sg_$query_row","","","",0);
				print "</td>";
				print "</tr>\n";
				
				$row_counter++;
			}
			}
		}else{
			print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td colspan='2' style='color: red; font-size: 12px; font-weight: bold;'>Error in data query.</td></tr>\n";
		}
		
		/* draw the graph template drop down here */
		print "	</table>
			<table align='center' width='98%'>
				<tr>
					<td width='1' valign='top'>
						<img src='images/arrow.gif' alt='' align='absmiddle'>&nbsp;
					</td>
					<td align='right'>
						<select name='sgg_" . $snmp_query["id"] . "' id='sgg_" . $snmp_query["id"] . "' onChange='dq_update_deps(" . $snmp_query["id"] . "," . $num_visible_fields{$snmp_query["id"]} . ");'>
							"; create_list(db_fetch_assoc("select snmp_query_graph.id,snmp_query_graph.name from snmp_query_graph where snmp_query_graph.snmp_query_id=" . $snmp_query["id"] . " order by snmp_query_graph.name"),"name","id","0"); print "
						</select>
					</td>
				</tr>
			</table>
			<br>";
	}
	}
	
	form_save_button("graphs_new.php");
	
	reset($snmp_queries);
	
	if (sizeof($snmp_queries) > 0) {
	foreach ($snmp_queries as $snmp_query) {
		$num_input_fields = $num_visible_fields{$snmp_query["id"]};
		
		print "<script type='text/javascript'>dq_update_deps(" . $snmp_query["id"] . "," . ($num_input_fields) . ");</script>\n";
	}
	}
}
?>
