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
		color_remove();
	    
		header ("Location: color.php");
		break;
	case 'edit':
		include_once("./include/top_header.php");
		
		color_edit();
		
		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");
		
		color();
		
		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_color"])) {
		$save["id"] = $_POST["id"];
		$save["hex"] = form_input_validate($_POST["hex"], "hex", "^[a-fA-F0-9]+$", false, 3);
		
		if (!is_error_message()) {
			$color_id = sql_save($save, "colors");
			
			if ($color_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
		
		if (is_error_message()) {
			header("Location: color.php?action=edit&id=" . (empty($color_id) ? $_POST["id"] : $color_id));
		}else{
			header("Location: color.php");
		}
	}
}

/* -----------------------
    Color Functions
   ----------------------- */

function color_remove() {
	db_execute("delete from colors where id=" . $_GET["id"]);	
}

function color_edit() {
	global $colors;
	
	if (!empty($_GET["id"])) {
		$color = db_fetch_row("select * from colors where id=" . $_GET["id"]);
		$header_label = "[edit: " . $color["hex"] . "]";
	}else{
		$header_label = "[new]";
	}
	
	start_box("<strong>Colors</strong> $header_label", "98%", $colors["header"], "3", "center", "");
    	
	draw_edit_form(
		array(
			"config" => array(
				),
			"fields" => array(
				"hex" => array(
					"method" => "textbox",
					"friendly_name" => "Hex Value",
					"description" => "The hex value for this color; valid range: 000000-FFFFFF.",
					"value" => (isset($color) ? $color["hex"] : ""),
					"max_length" => "6",
					),
				"id" => array(
					"method" => "hidden",
					"value" => (isset($color) ? $color["id"] : "0")
					),
				"save_component_color" => array(
					"method" => "hidden",
					"value" => "1"
					)
				)
			)
		);
	
	end_box();
	
	form_save_button("color.php");
}

function color() {
	global $colors;
	
	start_box("<strong>Colors</strong>", "98%", $colors["header"], "3", "center", "color.php?action=edit");
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Hex Value",$colors["header_text"],1);
		DrawMatrixHeaderItem("Color",$colors["header_text"],1);
		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
		## Space
		DrawMatrixHeaderItem("&nbsp; &nbsp; ",$colors["header_text"],1);
		DrawMatrixHeaderItem("Hex Value",$colors["header_text"],1);
                DrawMatrixHeaderItem("Color",$colors["header_text"],1);
		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
	print "</tr>";
    
	$color_list = db_fetch_assoc("select * from colors order by hex");
	
	$i = 0;
	if (sizeof($color_list) > 0) {
		$j=0; ## even/odd counter
		foreach ($color_list as $color) {
			$j++;
			if ($j % 2 == 1) {
				form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
					?>
					<td>
						<a class="linkEditMain" href="color.php?action=edit&id=<?php print $color["id"];?>"><?php print $color["hex"];?></a>
					</td>
					<td bgcolor="#<?php print $color["hex"];?>" width="1%">&nbsp;</td>
					<td width="1%" align="right">
						<a href="color.php?action=remove&id=<?php print $color["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
					</td>
				<?php	$j=1;
			} else { ?>
					<td></td>
					<td>
						<a class="linkEditMain" href="color.php?action=edit&id=<?php print $color["id"];?>"><?php print $color["hex"];?></a>
					</td>
					<td bgcolor="#<?php print $color["hex"];?>" width="1%">&nbsp;</td>
					<td width="1%" align="right">
						<a href="color.php?action=remove&id=<?php print $color["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
					</td>
				</tr>
			<?php
			}
		}
		## check for completion of odd number second column:
		if ($j == 1) {
			?>
				<td colspan=4></td>
				</tr>
			<?php
		}
	}
	end_box();	
}
   
?>
