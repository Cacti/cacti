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

include ('include/auth.php');
include_once ("include/functions.php");
include_once ('include/form.php');

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	case 'remove':
		template_remove();
		
		header ("Location: host_templates.php");
		break;
	case 'item_remove_gsv':
		template_item_remove_gsv();
		
		header ("Location: host_templates.php?action=edit&id=" . $_GET["host_template_id"]);
		break;
	case 'item_remove_dssv':
		template_item_remove_dssv();
		
		header ("Location: host_templates.php?action=edit&id=" . $_GET["host_template_id"]);
		break;
	case 'edit':
		include_once ("include/top_header.php");
		
		template_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		template();
		
		include_once ("include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_template"])) {
		$redirect_back = false;
		
		$save["id"] = $_POST["id"];
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		
		if (!is_error_message()) {
			$host_template_id = sql_save($save, "host_template");
			
			if ($host_template_id) {
				raise_message(1);
				
				db_execute ("delete from host_template_graph where host_template_id=$host_template_id");
				db_execute ("delete from host_template_snmp_query where host_template_id=$host_template_id");
				
				/* stale entries check -- walk through each graph template in the the db, and make sure that
				there is a cooresponding POST entry for it */
				$graph_templates = db_fetch_assoc("select id from graph_templates");
				
				if (sizeof($graph_templates) > 0) {
				foreach ($graph_templates as $graph_template) {
					if (!isset($_POST{"gt_" . $graph_template["id"]})) {
						/* this graph template does not exist as a POST var, therefore it is
						a stale entry. get rid of it. */
						db_execute("delete from host_template_graph where graph_template_id=" . $graph_template["id"] . " and host_template_id=$host_template_id");
						db_execute("delete from host_template_graph_sv where graph_template_id=" . $graph_template["id"] . " and host_template_id=$host_template_id");
						db_execute("delete from host_template_data_sv where graph_template_id=" . $graph_template["id"] . " and host_template_id=$host_template_id");
					}
				}
				}
				
				while (list($var, $val) = each($_POST)) {
					if (eregi("^gt_", $var)) {
						db_execute ("replace into host_template_graph (host_template_id,graph_template_id) values($host_template_id," . substr($var, 3) . ")");
					}elseif (eregi("^sq_", $var)) {
						db_execute ("replace into host_template_snmp_query (host_template_id,snmp_query_id) values($host_template_id," . substr($var, 3) . ")");
					}elseif ((eregi("^svds_([0-9]+)_([0-9]+)_x", $var, $matches)) && (!empty($_POST{"svds_" . $matches[1] . "_" . $matches[2] . "_text"})) && (!empty($_POST{"svds_" . $matches[1] . "_" . $matches[2] . "_field"}))) {
						/* suggested values -- data templates */
						db_execute("insert into host_template_data_sv (host_template_id,data_template_id,graph_template_id,field_name,text) values (" . $_POST["id"] . "," . $matches[2] . "," . $matches[1] . ",'" . $_POST{"svds_" . $matches[1] . "_" . $matches[2] . "_field"} . "','" . $_POST{"svds_" . $matches[1] . "_" . $matches[2] . "_text"} . "')"); 
						
						$redirect_back = true;
						clear_messages();
					}elseif ((eregi("^svg_([0-9]+)_x", $var, $matches)) && (!empty($_POST{"svg_" . $matches[1] . "_text"})) && (!empty($_POST{"svg_" . $matches[1] . "_field"}))) {
						/* suggested values -- graph templates */
						db_execute("insert into host_template_graph_sv (host_template_id,graph_template_id,field_name,text) values (" . $_POST["id"] . "," . $matches[1] . ",'" . $_POST{"svg_" . $matches[1] . "_field"} . "','" . $_POST{"svg_" . $matches[1] . "_text"} . "')"); 
						
						$redirect_back = true;
						clear_messages();
					}
				}
			}else{
				raise_message(2);
			}
		}
		
		if ((is_error_message()) || (empty($_POST["id"])) || ($redirect_back == true)) {
			header ("Location: host_templates.php?action=edit&id=" . (empty($host_template_id) ? $_POST["id"] : $host_template_id));
		}else{
			header ("Location: host_templates.php");
		}
	}
}

/* ---------------------
    Template Functions
   --------------------- */

function template_item_remove_gsv() {
	db_execute("delete from host_template_graph_sv where host_template_id=" . $_GET["host_template_id"] . " and graph_template_id=" . $_GET["graph_template_id"] . " and field_name='" . $_GET["field_name"] . "'");
}

function template_item_remove_dssv() {
	db_execute("delete from host_template_data_sv where host_template_id=" . $_GET["host_template_id"] . " and data_template_id=" . $_GET["data_template_id"] . " and graph_template_id=" . $_GET["graph_template_id"] . " and field_name='" . $_GET["field_name"] . "'");
}

function template_remove() {
	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include ('include/top_header.php');
		form_confirm("Are You Sure?", "Are you sure you want to delete the host template <strong>'" . db_fetch_cell("select name from host_template where id=" . $_GET["id"]) . "'</strong>?", $_SERVER["HTTP_REFERER"], "host_templates.php?action=remove&id=" . $_GET["id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from host_template where id=" . $_GET["id"]);
		db_execute("delete from host_template_snmp_query where host_template_id=" . $_GET["id"]);
		db_execute("delete from host_template_graph_template where host_template_id=" . $_GET["id"]);
		db_execute("delete from host_template_data_template where host_template_id=" . $_GET["id"]);
	}
}

function template_edit() {
	global $colors;
	
	display_output_messages();
	
	if (!empty($_GET["id"])) {
		$host_template = db_fetch_row("select * from host_template where id=" . $_GET["id"]);
		$header_label = "[edit: " . $host_template["name"] . "]";
	}else{
		$header_label = "[new]";
		$_GET["id"] = 0;
	}
	
	start_box("<strong>Host Templates</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	
	?>
	<form method="post" action="host_templates.php">
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="30%">
			<font class="textEditTitle">Name</font><br>
			A useful name for this host template.
		</td>
		<?php form_text_box("name",(isset($host_template) ? $host_template["name"] : ""),"","255", "40");?>
	</tr>
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="30%">
			<font class="textEditTitle">Associated Graph Templates</font><br>
			Select one or more graph templates to associate with this host template.
		</td>
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td align="top" width="50%">
						<?php
						$graph_templates = db_fetch_assoc("select 
							host_template_graph.host_template_id,
							graph_templates.id,
							graph_templates.name
							from graph_templates left join host_template_graph
							on (graph_templates.id=host_template_graph.graph_template_id and host_template_graph.host_template_id=" . $_GET["id"] . ") 
							order by graph_templates.name");
						
						$i = 0;
						if (sizeof($graph_templates) > 0) {
						foreach($graph_templates as $graph_template) {
							$column1 = floor((sizeof($graph_templates) / 2) + (sizeof($graph_templates) % 2));
							
							if (empty($graph_template["host_template_id"])) {
								$old_value = "";
							}else{
								$old_value = "on";
							}
							
							if ($i == $column1) {
								print "</td><td valign='top' width='50%'>";
							}
							form_base_checkbox("gt_".$graph_template["id"], $old_value, $graph_template["name"], "",$_GET["id"],true);
							$i++;
						}
						}
						?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php
	
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="30%">
			<font class="textEditTitle">Associated SNMP Queries</font><br>
			Select one or more SNMP queries to associate with this host template.
		</td>
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td align="top" width="50%">
						<?php
						$snmp_queries = db_fetch_assoc("select 
							host_template_snmp_query.host_template_id,
							snmp_query.id,
							snmp_query.name
							from snmp_query left join host_template_snmp_query
							on (snmp_query.id=host_template_snmp_query.snmp_query_id and host_template_snmp_query.host_template_id=" . $_GET["id"] . ") 
							order by snmp_query.name");
						
						$i = 0;
						if (sizeof($snmp_queries) > 0) {
						foreach($snmp_queries as $snmp_query) {
							$column1 = floor((sizeof($snmp_queries) / 2) + (sizeof($snmp_queries) % 2));
							
							if (empty($snmp_query["host_template_id"])) {
								$old_value = "";
							}else{
								$old_value = "on";
							}
							
							if ($i == $column1) {
								print "</td><td valign='top' width='50%'>";
							}
							form_base_checkbox("sq_".$snmp_query["id"], $old_value, $snmp_query["name"], "",$_GET["id"],true);
							$i++;
						}
						}
						?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php
	end_box();
	
	reset($graph_templates);
	
	if (sizeof($graph_templates) > 0) {
	foreach($graph_templates as $graph_template) {
		if (!empty($graph_template["host_template_id"])) {
			$data_templates = db_fetch_assoc("select
				data_template.id,
				data_template.name
				from data_template, data_template_rrd, graph_templates_item
				where graph_templates_item.task_item_id=data_template_rrd.id
				and data_template_rrd.data_template_id=data_template.id
				and data_template_rrd.local_data_id=0
				and graph_templates_item.local_graph_id=0
				and graph_templates_item.graph_template_id=" . $graph_template["id"] . "
				group by data_template.id
				order by data_template.name");
			
			start_box("<strong>Suggested Values</strong> - " . $graph_template["name"], "98%", $colors["header"], "3", "center", "");
			
			/* suggested values for data templates */
			if (sizeof($data_templates) > 0) {
			foreach ($data_templates as $data_template) {
				$suggested_values = db_fetch_assoc("select
					text,
					field_name
					from host_template_data_sv
					where host_template_id=" . $_GET["id"] . "
					and graph_template_id=" . $graph_template["id"] . "
					and data_template_id=" . $data_template["id"] . "
					order by field_name");
				
				print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
						<td><span style='color: white; font-weight: bold;'>Data Template - " . $data_template["name"] . "</span></td>
					</tr>";
					
				$i = 0;
				if (sizeof($suggested_values) > 0) {
				foreach ($suggested_values as $suggested_value) {
					form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
					?>
						<td>
							<table cellspacing="0" cellpadding="0" border="0" width="100%">
								<tr>
									<td width="120">
										<strong><?php print $suggested_value["field_name"];?></strong>
									</td>
									<td>
										<?php print $suggested_value["text"];?>
									</td>
									<td width="1%" align="right">
										<a href="host_templates.php?action=item_remove_dssv&host_template_id=<?php print $_GET["id"];?>&field_name=<?php print $suggested_value["field_name"];?>&graph_template_id=<?php print $graph_template["id"];?>&data_template_id=<?php print $data_template["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<?php
				}
				}
				
				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
				?>
					<td>
						<table cellspacing="0" cellpadding="0" border="0" width="100%">
							<tr>
								<td width="1">
									<input type="text" name="svds_<?php print $graph_template["id"];?>_<?php print $data_template["id"];?>_text" size="30">
								</td>
								<td width="200">
									&nbsp;Field Name: <input type="text" name="svds_<?php print $graph_template["id"];?>_<?php print $data_template["id"];?>_field" size="15">
								</td>
								<td>
									&nbsp;<input type="image" src="images/button_add.gif" name="svds_<?php print $graph_template["id"];?>_<?php print $data_template["id"];?>" alt="Add" align="absmiddle">
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
				field_name
				from host_template_graph_sv
				where host_template_id=" . $_GET["id"] . "
				and graph_template_id=" . $graph_template["id"] . "
				order by field_name");
			
			print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
					<td><span style='color: white; font-weight: bold;'>Graph Template - " . $graph_template["name"] . "</span></td>
				</tr>";
			
			$i = 0;
			if (sizeof($suggested_values) > 0) {
			foreach ($suggested_values as $suggested_value) {
				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
				?>
					<td>
						<table cellspacing="0" cellpadding="0" border="0" width="100%">
							<tr>
								<td width="120">
									<strong><?php print $suggested_value["field_name"];?></strong>
								</td>
								<td>
									<?php print $suggested_value["text"];?>
								</td>
								<td width="1%" align="right">
									<a href="host_templates.php?action=item_remove_gsv&host_template_id=<?php print $_GET["id"];?>&graph_template_id=<?php print $graph_template["id"];?>&field_name=<?php print $suggested_value["field_name"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<?php
			}
			}
			
			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
			?>
				<td>
					<table cellspacing="0" cellpadding="0" border="0" width="100%">
						<tr>
							<td width="1">
								<input type="text" name="svg_<?php print $graph_template["id"];?>_text" size="30">
							</td>
							<td width="200">
								&nbsp;Field Name: <input type="text" name="svg_<?php print $graph_template["id"];?>_field" size="15">
							</td>
							<td>
								&nbsp;<input type="image" src="images/button_add.gif" name="svg_<?php print $graph_template["id"];?>" alt="Add" align="absmiddle">
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<?php
			
			end_box();
		}
	}
	}
	
	form_hidden_id("id",(isset($host_template) ? $host_template["id"] : "0"));
	form_hidden_box("save_component_template","1","");
	
	form_save_button("host_templates.php");	
}

function template() {
	global $colors;
	
	display_output_messages();
	
	start_box("<strong>Host Templates</strong>", "98%", $colors["header"], "3", "center", "host_templates.php?action=edit");
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Template Title",$colors["header_text"],1);
		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
	print "</tr>";
    
	$host_templates = db_fetch_assoc("select * from host_template order by name");
	
	$i = 0;
	if (sizeof($host_templates) > 0) {
	foreach ($host_templates as $host_template) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="host_templates.php?action=edit&id=<?php print $host_template["id"];?>"><?php print $host_template["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="host_templates.php?action=remove&id=<?php print $host_template["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?php
	}
	}else{
		print "<tr><td><em>No Host Templates</em></td></tr>\n";
	}
	end_box();	
}
?>
