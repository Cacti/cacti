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

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	case 'remove':
		template_remove();
		
		header("Location: host_templates.php");
		break;
	case 'item_remove_gt':
		template_item_remove_gt();
		
		header("Location: host_templates.php?action=edit&id=" . $_GET["host_template_id"]);
		break;
	case 'item_remove_dq':
		template_item_remove_dq();
		
		header("Location: host_templates.php?action=edit&id=" . $_GET["host_template_id"]);
		break;
	case 'edit':
		include_once("./include/top_header.php");
		
		template_edit();
		
		include_once("./include/bottom_footer.php");
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
		$redirect_back = false;
		
		$save["id"] = $_POST["id"];
		$save["hash"] = get_hash_host_template($_POST["id"]);
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		
		if (!is_error_message()) {
			$host_template_id = sql_save($save, "host_template");
			
			if ($host_template_id) {
				raise_message(1);
				
				if (isset($_POST["add_gt_x"])) {
					db_execute("replace into host_template_graph (host_template_id,graph_template_id) values($host_template_id," . $_POST["graph_template_id"] . ")");
					$redirect_back = true;
				}elseif (isset($_POST["add_dq_x"])) {
					db_execute("replace into host_template_snmp_query (host_template_id,snmp_query_id) values($host_template_id," . $_POST["snmp_query_id"] . ")");
					$redirect_back = true;
				}
			}else{
				raise_message(2);
			}
		}
		
		if ((is_error_message()) || (empty($_POST["id"])) || ($redirect_back == true)) {
			header("Location: host_templates.php?action=edit&id=" . (empty($host_template_id) ? $_POST["id"] : $host_template_id));
		}else{
			header("Location: host_templates.php");
		}
	}
}

/* ---------------------
    Template Functions
   --------------------- */

function template_item_remove_gt() {
	db_execute("delete from host_template_graph where graph_template_id=" . $_GET["id"] . " and host_template_id=" . $_GET["host_template_id"]);
}

function template_item_remove_dq() {
	db_execute("delete from host_template_snmp_query where snmp_query_id=" . $_GET["id"] . " and host_template_id=" . $_GET["host_template_id"]);
}

function template_remove() {
	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the host template <strong>'" . db_fetch_cell("select name from host_template where id=" . $_GET["id"]) . "'</strong>?", $_SERVER["HTTP_REFERER"], "host_templates.php?action=remove&id=" . $_GET["id"]);
		include("./include/bottom_footer.php");
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from host_template where id=" . $_GET["id"]);
		db_execute("delete from host_template_snmp_query where host_template_id=" . $_GET["id"]);
		db_execute("delete from host_template_graph where host_template_id=" . $_GET["id"]);
	}
}

function template_edit() {
	global $colors, $fields_host_template_edit;
	
	display_output_messages();
	
	if (!empty($_GET["id"])) {
		$host_template = db_fetch_row("select * from host_template where id=" . $_GET["id"]);
		$header_label = "[edit: " . $host_template["name"] . "]";
	}else{
		$header_label = "[new]";
		$_GET["id"] = 0;
	}
	
	start_box("<strong>Host Templates</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	
	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_host_template_edit, (isset($host_template) ? $host_template : array()))
		));
	
	end_box();
	
	start_box("<strong>Associated Graph Templates</strong>", "98%", $colors["header"], "3", "center", "");
	
	$selected_graph_templates = db_fetch_assoc("select 
		graph_templates.id,
		graph_templates.name
		from graph_templates,host_template_graph
		where graph_templates.id=host_template_graph.graph_template_id
		and host_template_graph.host_template_id=" . $_GET["id"] . "
		order by graph_templates.name");
	
	$i = 0;
	if (sizeof($selected_graph_templates) > 0) {
	foreach ($selected_graph_templates as $item) {
		$i++;
		?>
		<tr>
			<td style="padding: 4px;">
				<strong><?php print $i;?>)</strong> <?php print $item["name"];?>
			</td>
			<td width='1%' align='right'>
				<a href='host_templates.php?action=item_remove_gt&id=<?php print $item["id"];?>&host_template_id=<?php print $_GET["id"];?>'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a>&nbsp;
			</td>
		</tr>
		<?php
	}
	}else{ print "<tr><td><em>No associated graph templates.</em></td></tr>"; }
	
	?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td colspan="2">
			<table cellspacing="0" cellpadding="1" width="100%">
				<td nowrap>Add Graph Template:&nbsp;
					<?php form_dropdown("graph_template_id",db_fetch_assoc("select 
						graph_templates.id,
						graph_templates.name
						from graph_templates left join host_template_graph
						on (graph_templates.id=host_template_graph.graph_template_id and host_template_graph.host_template_id=" . $_GET["id"] . ")
						where host_template_graph.host_template_id is null
						order by graph_templates.name"),"name","id","","","");?>
				</td>
				<td align="right">
					&nbsp;<input type="image" src="images/button_add.gif" alt="Add" name="add_gt" align="absmiddle">
				</td>
			</table>
		</td>
	</tr>
	
	<?php
	end_box();
	
	start_box("<strong>Associated Data Queries</strong>", "98%", $colors["header"], "3", "center", "");
	
	$selected_data_queries = db_fetch_assoc("select 
		snmp_query.id,
		snmp_query.name
		from snmp_query,host_template_snmp_query
		where snmp_query.id=host_template_snmp_query.snmp_query_id
		and host_template_snmp_query.host_template_id=" . $_GET["id"] . "
		order by snmp_query.name");
	
	$i = 0;
	if (sizeof($selected_data_queries) > 0) {
	foreach ($selected_data_queries as $item) {
		$i++;
		?>
		<tr>
			<td style="padding: 4px;">
				<strong><?php print $i;?>)</strong> <?php print $item["name"];?>
			</td>
			<td width='1%' align='right'>
				<a href='host_templates.php?action=item_remove_dq&id=<?php print $item["id"];?>&host_template_id=<?php print $_GET["id"];?>'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a>&nbsp;
			</td>
		</tr>
		<?php
	}
	}else{ print "<tr><td><em>No associated data queries.</em></td></tr>"; }
	
	?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td colspan="2">
			<table cellspacing="0" cellpadding="1" width="100%">
				<td nowrap>Add Graph Template:&nbsp;
					<?php form_dropdown("snmp_query_id",db_fetch_assoc("select 
						snmp_query.id,
						snmp_query.name
						from snmp_query left join host_template_snmp_query
						on (snmp_query.id=host_template_snmp_query.snmp_query_id and host_template_snmp_query.host_template_id=" . $_GET["id"] . ")
						where host_template_snmp_query.host_template_id is null
						order by snmp_query.name"),"name","id","","","");?>
				</td>
				<td align="right">
					&nbsp;<input type="image" src="images/button_add.gif" alt="Add" name="add_dq" align="absmiddle">
				</td>
			</table>
		</td>
	</tr>
	
	<?php
	end_box();
	
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
