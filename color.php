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
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	db_execute("delete from colors where id=" . $_GET["id"]);
}

function color_edit() {
	global $colors, $fields_color_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if (!empty($_GET["id"])) {
		$color = db_fetch_row("select * from colors where id=" . $_GET["id"]);
		$header_label = "[edit: " . $color["hex"] . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>Colors</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_color_edit, (isset($color) ? $color : array()))
		));

	html_end_box();

	form_save_button("color.php");
}

function color() {
	global $colors;

	html_start_box("<strong>Colors</strong>", "100%", $colors["header"], "3", "center", "color.php?action=edit");

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Hex Value",$colors["header_text"],1);
		DrawMatrixHeaderItem("Color",$colors["header_text"],1);
		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);

		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
		DrawMatrixHeaderItem("Hex Value",$colors["header_text"],1);
		DrawMatrixHeaderItem("Color",$colors["header_text"],1);
		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);

		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
		DrawMatrixHeaderItem("Hex Value",$colors["header_text"],1);
		DrawMatrixHeaderItem("Color",$colors["header_text"],1);
		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);

		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
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
			if ($j % 4 == 1) {
				form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
					?>
					<td width='1'>
						<a class="linkEditMain" style='display:block;' href="<?php print htmlspecialchars("color.php?action=edit&id=" . $color["id"]);?>"><?php print $color["hex"];?></a>
					</td>
					<td bgcolor="#<?php print $color["hex"];?>" width="10%">&nbsp;</td>
					<td align="right">
						<a href="<?php print htmlspecialchars("color.php?action=remove&id=" . $color["id"]);?>"><img src="images/delete_icon.gif" style="height:10px;width:10px;" border="0" alt="Delete"></a>
					</td>
				<?php	$j=1;
			}elseif (($j % 4 == 2) || ($j % 4 == 3)) {
					?>
					<td></td>
					<td width='1'>
						<a class="linkEditMain" style='display:block;' href="<?php print htmlspecialchars("color.php?action=edit&id=" . $color["id"]);?>"><?php print $color["hex"];?></a>
					</td>
					<td bgcolor="#<?php print $color["hex"];?>" width="10%">&nbsp;</td>
					<td align="right">
						<a href="<?php print htmlspecialchars("color.php?action=remove&id=" . $color["id"]);?>"><img src="images/delete_icon.gif" style="height:10px;width:10px;" border="0" alt="Delete"></a>
					</td>
				<?php	$j=$j++;
			} else { ?>
					<td></td>
					<td width='1'>
						<a class="linkEditMain" style='display:block;' href="<?php print htmlspecialchars("color.php?action=edit&id=" . $color["id"]);?>"><?php print $color["hex"];?></a>
					</td>
					<td bgcolor="#<?php print $color["hex"];?>" width="10%">&nbsp;</td>
					<td align="right">
						<a href="<?php print htmlspecialchars("color.php?action=remove&id=" . $color["id"]);?>"><img src="images/delete_icon.gif" style="height:10px;width:10px;" border="0" alt="Delete"></a>
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
	html_end_box();
}

?>
