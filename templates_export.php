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
include_once("./lib/export.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

$export_types = array(
	"graph_template" => array(
		"name" => "Graph Template",
		"title_sql" => "select name from graph_templates where id=|id|",
		"dropdown_sql" => "select id,name from graph_templates order by name"
		),
	"data_template" => array(
		"name" => "Data Template",
		"title_sql" => "select name from data_template where id=|id|",
		"dropdown_sql" => "select id,name from data_template order by name"
		),
	"host_template" => array(
		"name" => "Host Template",
		"title_sql" => "select name from host_template where id=|id|",
		"dropdown_sql" => "select id,name from host_template order by name"
		),
	"data_query" => array(
		"name" => "Data Query",
		"title_sql" => "select name from snmp_query where id=|id|",
		"dropdown_sql" => "select id,name from snmp_query order by name"
		)
	);

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	default:
		include_once("./include/top_header.php");
		
		export();
		
		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $export_types;
	
	if (isset($_POST["save_component_export"])) {
		$xml_data = get_item_xml($_POST["export_type"], $_POST["export_item_id"], (((isset($_POST["include_deps"]) ? $_POST["include_deps"] : "") == "") ? false : true));
		
		if ($_POST["output_format"] == "1") {
			include_once("./include/top_header.php");
			print "<table width='98%' align='center'><tr><td><pre>" . htmlspecialchars($xml_data) . "</pre></td></tr></table>";
			include_once("./include/bottom_footer.php");
		}elseif ($_POST["output_format"] == "2") {
			header("Content-type: application/xml");
			print $xml_data;
		}elseif ($_POST["output_format"] == "3") {
			header("Content-type: application/xml");
			header("Content-Disposition: attachment; filename=cacti_" . $_POST["export_type"] . "_" . strtolower(clean_up_name(db_fetch_cell(str_replace("|id|", $_POST["export_item_id"], $export_types{$_POST["export_type"]}["title_sql"])))) . ".xml");
			print $xml_data;
		}
	}
}

/* ---------------------------
    Template Export Functions
   --------------------------- */

function export() {
	global $colors, $export_types;
	
	/* 'graph_template' should be the default */
	if (!isset($_REQUEST["export_type"])) {
		$_REQUEST["export_type"] = "graph_template";
	}
	
	?>
	<form name="form_graph_id">
	<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>
		<tr bgcolor="<?php print $colors["light"];?>">
			<td class="textArea" style="padding: 3px;">
				What would you like to export?&nbsp;
				
				<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
					<?php
					while (list($key, $array) = each($export_types)) {
						print "<option value='templates_export.php?export_type=$key'"; if ($_REQUEST["export_type"] == $key) { print " selected"; } print ">" . $array["name"] . "</option>\n";
					}
					?>
				</select>
			</td>
		</tr>
	</table>
	</form>
	<form method="post" action="templates_export.php">
	<?php
	
	start_box("<strong>Export Template</strong> [" . $export_types{$_REQUEST["export_type"]}["name"] . "]", "98%", $colors["header"], "3", "center", "");
	
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle"><?php print $export_types{$_REQUEST["export_type"]}["name"];?> to Export</font><br>
			Choose the exact item to export to XML.
		</td>
		<td>
			<?php form_dropdown("export_item_id",db_fetch_assoc($export_types{$_REQUEST["export_type"]}["dropdown_sql"]),"name","id","","","0");?>
		</td>
	</tr>
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Include Dependencies</font><br>
			Some templates rely on other items in Cacti to function properly. It is highly recommended that you select
			this box or the resulting import may fail.
		</td>
		<td>
			<?php form_checkbox("include_deps", "on", "Include Dependencies", "on", "", true);?>
		</td>
	</tr>
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Output Format</font><br>
			Choose the format to output the resulting XML file in.
		</td>
		<td>
			<?php
			form_radio_button("output_format", "3", "1", "Output to the Browser (within Cacti)","1",true); print "<br>";
			form_radio_button("output_format", "3", "2", "Output to the Browser (raw XML)","1",true); print "<br>";
			form_radio_button("output_format", "3", "3", "Save File Locally","1",true);
			?>
		</td>
	</tr>
	<?php
	
	form_hidden_box("export_type", $_REQUEST["export_type"], "");
	form_hidden_box("save_component_export","1","");
	
	end_box();
	
	form_save_button("templates_export.php");
}
?>
