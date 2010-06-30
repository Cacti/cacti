<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2010 The Cacti Group                                 |
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
include_once("./lib/import.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();

		break;
	default:
		include_once("./include/top_header.php");

		import();

		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_import"])) {
		if (trim($_POST["import_text"] != "")) {
			/* textbox input */
			$xml_data = $_POST["import_text"];
		}elseif (($_FILES["import_file"]["tmp_name"] != "none") && ($_FILES["import_file"]["tmp_name"] != "")) {
			/* file upload */
			$fp = fopen($_FILES["import_file"]["tmp_name"],"r");
			$xml_data = fread($fp,filesize($_FILES["import_file"]["tmp_name"]));
			fclose($fp);
		}else{
			header("Location: templates_import.php"); exit;
		}

		if ($_POST["import_rra"] == "1") {
			$import_custom_rra_settings = false;
		}else{
			$import_custom_rra_settings = true;
		}

		/* obtain debug information if it's set */
		$debug_data = import_xml_data($xml_data, $import_custom_rra_settings);
		if(sizeof($debug_data) > 0) {
			$_SESSION["import_debug_info"] = $debug_data;
		}

		header("Location: templates_import.php");
	}
}

/* ---------------------------
    Template Import Functions
   --------------------------- */

function import() {
	global $colors, $hash_type_names;

	?>
	<form method="post" action="templates_import.php" enctype="multipart/form-data">
	<?php

	if ((isset($_SESSION["import_debug_info"])) && (is_array($_SESSION["import_debug_info"]))) {
		html_start_box("<strong>Import Results</strong>", "100%", "aaaaaa", "3", "center", "");

		print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td><p class='textArea'>Cacti has imported the following items:</p>";

		while (list($type, $type_array) = each($_SESSION["import_debug_info"])) {
			print "<p><strong>" . $hash_type_names[$type] . "</strong></p>";

			while (list($index, $vals) = each($type_array)) {
				if ($vals["result"] == "success") {
					$result_text = "<span style='color: green;'>[success]</span>";
				}else{
					$result_text = "<span style='color: red;'>[fail]</span>";
				}

				if ($vals["type"] == "update") {
					$type_text = "<span style='color: gray;'>[update]</span>";
				}else{
					$type_text = "<span style='color: blue;'>[new]</span>";
				}

				print "<span style='font-family: monospace;'>$result_text " . htmlspecialchars($vals["title"]) . " $type_text</span><br>\n";

				$dep_text = ""; $there_are_dep_errors = false;
				if ((isset($vals["dep"])) && (sizeof($vals["dep"]) > 0)) {
					while (list($dep_hash, $dep_status) = each($vals["dep"])) {
						if ($dep_status == "met") {
							$dep_status_text = "<span style='color: navy;'>Found Dependency:</span>";
						}else{
							$dep_status_text = "<span style='color: red;'>Unmet Dependency:</span>";
							$there_are_dep_errors = true;
						}

						$dep_text .= "<span style='font-family: monospace;'>&nbsp;&nbsp;&nbsp;+ $dep_status_text " . hash_to_friendly_name($dep_hash, true) . "</span><br>\n";
					}
				}

				/* only print out dependency details if they contain errors; otherwise it would get too long */
				if ($there_are_dep_errors == true) {
					print $dep_text;
				}
			}
		}

		print "</td></tr>";

		html_end_box();

		kill_session_var("import_debug_info");
	}

	html_start_box("<strong>Import Templates</strong>", "100%", $colors["header"], "3", "center", "");

	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Import Template from Local File</font><br>
			If the XML file containing template data is located on your local machine, select it here.
		</td>
		<td>
			<input type="file" name="import_file">
		</td>
	</tr>

	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Import Template from Text</font><br>
			If you have the XML file containing template data as text, you can paste it into this box to
			import it.
		</td>
		<td>
			<?php form_text_area("import_text", "", "10	", "50", "");?>
		</td>
	</tr>

	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Import RRA Settings</font><br>
			Choose whether to allow Cacti to import custom RRA settings from imported templates or whether to use the defaults for this installation.
		</td>
		<td>
			<?php
			form_radio_button("import_rra", 1, 1, "Use defaults for this installation (Recommended)", 1); echo "<br />";
			form_radio_button("import_rra", 1, 2, "Use custom RRA settings from the template", 1);
			form_hidden_box("save_component_import","1","");
			?>
		</td>
	</tr>

	<?php


	html_end_box();

	form_save_button("", "import");
}
?>
