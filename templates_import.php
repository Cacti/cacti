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
			$rra_array = (isset($_POST["rra_id"]) ? $_POST["rra_id"] : array());
		}else{
			$import_custom_rra_settings = true;
			$rra_array = array();
		}

		/* obtain debug information if it's set */
		$debug_data = import_xml_data($xml_data, $import_custom_rra_settings, $rra_array);
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
	global $colors, $hash_type_names, $fields_template_import;

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

	draw_edit_form(array(
		"config" => array("no_form_tag" => true),
		"fields" => $fields_template_import
		));

	html_end_box();
	form_hidden_box("save_component_import","1","");

	form_save_button("", "import");
?>
<script language="JavaScript">

changeRRA();

function changeRRA() {
	//alert("changed to '" + document.getElementById('import_rra_1').checked + "'");
	
	if ((document.getElementById('import_rra_1').checked == '1')) {
		document.getElementById('row_rra_id').style.display="";
	}else{
		document.getElementById('row_rra_id').style.display="none";
	}
}
</script>
<?php
}

