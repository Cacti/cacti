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
		gprint_presets_remove();
		
		header("Location: gprint_presets.php");
		break;
	case 'edit':
		include_once("./include/top_header.php");
		
		gprint_presets_edit();
		
		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");
		
		gprint_presets();
		
		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_gprint_presets"])) {
		$save["id"] = $_POST["id"];
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save["gprint_text"] = form_input_validate($_POST["gprint_text"], "gprint_text", "", false, 3);
		
		if (!is_error_message()) {
			$gprint_preset_id = sql_save($save, "graph_templates_gprint");
			
			if ($gprint_preset_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
		
		if (is_error_message()) {
			header("Location: gprint_presets.php?action=edit&id=" . (empty($gprint_preset_id) ? $_POST["id"] : $gprint_preset_id));
			exit;
		}else{
			header("Location: gprint_presets.php");
			exit;
		}
	}
}

/* -----------------------------------
    gprint_presets - GPRINT Presets 
   ----------------------------------- */

function gprint_presets_remove() {
	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include_once("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the GPRINT preset <strong>'" . db_fetch_cell("select name from graph_templates_gprint where id=" . $_GET["id"]) . "'</strong>? This could affect every graph that uses this preset, make sure you know what you are doing first!", $_SERVER["HTTP_REFERER"], "gprint_presets.php?action=remove&gprint_preset_id=" . $_GET["id"]);
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from graph_templates_gprint where id=" . $_GET["id"]);
	}
}

function gprint_presets_edit() {
	global $colors;
	
	if (!empty($_GET["id"])) {
		$gprint_preset = db_fetch_row("select * from graph_templates_gprint where id=" . $_GET["id"]);
		$header_label = "[edit: " . $gprint_preset["name"] . "]";
	}else{
		$header_label = "[new]";
	}
	
	start_box("<strong>GPRINT Presets</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	
	draw_edit_form(
		array(
			"config" => array(
				),
			"fields" => array(
				"name" => array(
					"method" => "textbox",
					"friendly_name" => "Name",
					"description" => "Enter a name for this GPRINT preset, make sure it is something you recognize.",
					"value" => (isset($gprint_preset) ? $gprint_preset["name"] : ""),
					"max_length" => "50",
					),
				"gprint_text" => array(
					"method" => "textbox",
					"friendly_name" => "GPRINT Text",
					"description" => "Enter the custom GPRINT string here.",
					"value" => (isset($gprint_preset) ? $gprint_preset["gprint_text"] : ""),
					"max_length" => "50",
					),
				"id" => array(
					"method" => "hidden",
					"value" => (isset($gprint_preset) ? $gprint_preset["id"] : "0")
					),
				"save_component_gprint_presets" => array(
					"method" => "hidden",
					"value" => "1"
					)
				)
			)
		);
	
	end_box();
	
	form_save_button("gprint_presets.php");
}
   
function gprint_presets() {
	global $colors;
	
	start_box("<strong>GPRINT Presets</strong>", "98%", $colors["header"], "3", "center", "gprint_presets.php?action=edit");
	
	print "	<tr bgcolor='#" . $colors["header_panel"] . "'>
			<td colspan='2' class='textSubHeaderDark'>GPRINT Preset Title</td>
		</tr>";
	
	$template_list = db_fetch_assoc("select 
		graph_templates_gprint.id,
		graph_templates_gprint.name 
		from graph_templates_gprint");
	
	$i = 0;
	if (sizeof($template_list) > 0) {
	foreach ($template_list as $template) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="gprint_presets.php?action=edit&id=<?php print $template["id"];?>"><?php print $template["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="gprint_presets.php?action=remove&id=<?php print $template["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
		<?php
		$i++;
	}
	}else{
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
			<td colspan="2">
				<em>No Items</em>
			</td>
		</tr><?php
	}
	end_box();	
}
