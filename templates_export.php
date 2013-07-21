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
include_once("./lib/export.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

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
	global $export_types, $export_errors;

    /* ================= input validation ================= */
    input_validate_input_number(get_request_var_post("export_item_id"));
    /* ==================================================== */

	if (isset($_POST["save_component_export"])) {
		$export_errors = 0;
		$xml_data = get_item_xml($_POST["export_type"], $_POST["export_item_id"], (((isset($_POST["include_deps"]) ? $_POST["include_deps"] : "") == "") ? false : true));

		if ($_POST["output_format"] == "1") {
			include_once("./include/top_header.php");
			print "<table width='100%' align='center'><tr><td><pre>" . htmlspecialchars($xml_data) . "</pre></td></tr></table>";
			include_once("./include/bottom_footer.php");
		}elseif ($_POST["output_format"] == "2") {
			header("Content-type: application/xml");
			if ($export_errors) echo "WARNING: Export Errors Encountered.  Refresh Browser Window for Details!\n";
			print $xml_data;
		}elseif ($_POST["output_format"] == "3") {
			if ($export_errors) {
				header("Location: templates_export.php");
			}else{
				header("Content-type: application/xml");
				header("Content-Disposition: attachment; filename=cacti_" . $_POST["export_type"] . "_" . strtolower(clean_up_file_name(db_fetch_cell(str_replace("|id|", $_POST["export_item_id"], $export_types{$_POST["export_type"]}["title_sql"])))) . ".xml");
				print $xml_data;
			}
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
	<form name="form_graph_id" action="templates_export.php">
	<table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>
		<tr bgcolor="#<?php print $colors["light"];?>">
			<td class="textArea" style="padding: 3px;">
				What would you like to export?&nbsp;

				<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
					<?php
					while (list($key, $array) = each($export_types)) {
						print "<option value='templates_export.php?export_type=" . htmlspecialchars($key) . "'"; if ($_REQUEST["export_type"] == $key) { print " selected"; } print ">" . $array["name"] . "</option>\n";
					}
					?>
				</select>
			</td>
		</tr>
	</table>
	</form>
	<form method="post" action="templates_export.php">
	<?php

	html_start_box("<strong>Export Template</strong> [" . $export_types{$_REQUEST["export_type"]}["name"] . "]", "100%", $colors["header"], "3", "center", "");

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
			form_hidden_box("export_type", $_REQUEST["export_type"], "");
			form_hidden_box("save_component_export","1","");
			?>
		</td>
	</tr>
	<?php

	html_end_box();

	form_save_button("", "export");
}
?>
