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
include_once("./lib/cdef.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	case 'item_movedown':
		item_movedown();
		
		header("Location: cdef.php?action=edit&id=" . $_GET["cdef_id"]);
		break;
	case 'item_moveup':
		item_moveup();
		
		header("Location: cdef.php?action=edit&id=" . $_GET["cdef_id"]);
		break;
	case 'item_remove':
		item_remove();
	    
		header("Location: cdef.php?action=edit&id=" . $_GET["cdef_id"]);
		break;
	case 'item_edit':
		include_once("./include/top_header.php");
		
		item_edit();
		
		include_once("./include/bottom_footer.php");
		break;
	case 'remove':
		cdef_remove();
		
		header ("Location: cdef.php");
		break;
	case 'edit':
		include_once("./include/top_header.php");
		
		cdef_edit();
		
		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");
		
		cdef();
		
		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function draw_cdef_preview($cdef_id) {
	global $colors; ?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
			<pre>cdef=<?php print get_cdef($cdef_id, true);?></pre>
		</td>
	</tr>	
<?php }


/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_cdef"])) {
		$save["id"] = $_POST["id"];
		$save["hash"] = get_hash_cdef($_POST["id"]);
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		
		if (!is_error_message()) {
			$cdef_id = sql_save($save, "cdef");
			
			if ($cdef_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
		
		if ((is_error_message()) || (empty($_POST["id"]))) {
			header("Location: cdef.php?action=edit&id=" . (empty($cdef_id) ? $_POST["id"] : $cdef_id));
		}else{
			header("Location: cdef.php");
		}
	}elseif (isset($_POST["save_component_item"])) {
		$sequence = get_sequence($_POST["id"], "sequence", "cdef_items", "cdef_id=" . $_POST["cdef_id"]);
		
		$save["id"] = $_POST["id"];
		$save["hash"] = get_hash_cdef($_POST["id"], "cdef_item");
		$save["cdef_id"] = $_POST["cdef_id"];
		$save["sequence"] = $sequence;
		$save["type"] = $_POST["type"];
		$save["value"] = $_POST["value"];
		
		if (!is_error_message()) {
			$cdef_item_id = sql_save($save, "cdef_items");
			
			if ($cdef_item_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
		
		if (is_error_message()) {
			header("Location: cdef.php?action=item_edit&cdef_id=" . $_POST["cdef_id"] . "&id=" . (empty($cdef_item_id) ? $_POST["id"] : $cdef_item_id));
		}else{
			header("Location: cdef.php?action=edit&id=" . $_POST["cdef_id"]);
		}
	}
}

/* --------------------------
    CDEF Item Functions
   -------------------------- */

function item_movedown() {
	move_item_down("cdef_items", $_GET["id"], "cdef_id=" . $_GET["cdef_id"]);	
}

function item_moveup() {
	move_item_up("cdef_items", $_GET["id"], "cdef_id=" . $_GET["cdef_id"]);	
}

function item_remove() {
	db_execute("delete from cdef_items where id=" . $_GET["id"]);	
}

function item_edit() {
	global $colors, $cdef_item_types, $cdef_functions, $cdef_operators, $custom_data_source_types;
	
	if (!empty($_GET["id"])) {
		$cdef = db_fetch_row("select * from cdef_items where id=" . $_GET["id"]);
		$current_type = $cdef["type"];
		$values[$current_type] = $cdef["value"];
	}
	
	start_box("", "98%", "aaaaaa", "3", "center", "");
	draw_cdef_preview($_GET["cdef_id"]);
	end_box();
	
	start_box("<strong>CDEF Items</strong> [edit: " . db_fetch_cell("select name from cdef where id=" . $_GET["cdef_id"]) . "]", "98%", $colors["header"], "3", "center", "");
	
	if (isset($_GET["type_select"])) {
		$current_type = $_GET["type_select"];
	}elseif (isset($cdef["type"])) {
		$current_type = $cdef["type"];
	}else{
		$current_type = "1";
	}
	
	print "<form method='post' action='cdef.php' name='form_cdef'>\n";
	
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">CDEF Item Type</font><br>
			Choose what type of CDEF item this is.
		</td>
		<td>
			<select name="type_select" onChange="window.location=document.form_cdef.type_select.options[document.form_cdef.type_select.selectedIndex].value">
				<?php
				while (list($var, $val) = each($cdef_item_types)) {
					print "<option value='cdef.php?action=item_edit" . (isset($_GET["id"]) ? "&id=" . $_GET["id"] : "") . "&cdef_id=" . $_GET["cdef_id"] . "&type_select=$var'"; if ($var == $current_type) { print " selected"; } print ">$val</option>\n";
				}
				?>
			</select>
		</td>
	</tr>
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">CDEF Item Value</font><br>
			Enter a value for this CDEF item.
		</td>
		<td>
			<?php
			switch ($current_type) {
			case '1':
				form_dropdown("value", $cdef_functions, "", "", (isset($cdef["value"]) ? $cdef["value"] : ""), "", "");
				break;
			case '2':
				form_dropdown("value", $cdef_operators, "", "", (isset($cdef["value"]) ? $cdef["value"] : ""), "", "");
				break;
			case '4':
				form_dropdown("value", $custom_data_source_types, "", "", (isset($cdef["value"]) ? $cdef["value"] : ""), "", "");
				break;
			case '5':
				form_dropdown("value", db_fetch_assoc("select name,id from cdef"), "name", "id", (isset($cdef["value"]) ? $cdef["value"] : ""), "", "");
				break;
			case '6':
				form_text_box("value", (isset($cdef["value"]) ? $cdef["value"] : ""), "", "255", 30, "text", (isset($_GET["id"]) ? $_GET["id"] : "0"));
				break;
			}
			?>
		</td>
	</tr>
	<?php
	
	form_hidden_box("id", (isset($_GET["id"]) ? $_GET["id"] : "0"), "");
	form_hidden_box("type", $current_type, "");
	form_hidden_box("cdef_id", $_GET["cdef_id"], "");
	form_hidden_box("save_component_item", "1", "");
	
	end_box();
	
	form_save_button("cdef.php?action=edit&id=" . $_GET["cdef_id"]);
}
   
/* ---------------------
    CDEF Functions
   --------------------- */

function cdef_remove() {
	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the CDEF <strong>'" . db_fetch_cell("select name from cdef where id=" . $_GET["id"]) . "'</strong>?", "cdef.php", "cdef.php?action=remove&id=" . $_GET["id"]);
		include("./include/bottom_footer.php");
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from cdef where id=" . $_GET["id"]);
		db_execute("delete from cdef_items where cdef_id=" . $_GET["id"]);
	}
}

function cdef_edit() {
	global $colors, $cdef_item_types, $fields_cdef_edit;
	
	if (!empty($_GET["id"])) {
		$cdef = db_fetch_row("select * from cdef where id=" . $_GET["id"]);
		$header_label = "[edit: " . $cdef["name"] . "]";
	}else{
		$header_label = "[new]";
	}
	
	start_box("<strong>CDEF's</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	
	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_cdef_edit, (isset($cdef) ? $cdef : array()))
		));
	
	end_box();
	
	if (!empty($_GET["id"])) {
		start_box("", "98%", "aaaaaa", "3", "center", "");
		draw_cdef_preview($_GET["id"]);
		end_box();
		
		start_box("<strong>CDEF Items</strong>", "98%", $colors["header"], "3", "center", "cdef.php?action=item_edit&cdef_id=" . $cdef["id"]);
		
		print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
			DrawMatrixHeaderItem("Item",$colors["header_text"],1);
			DrawMatrixHeaderItem("Item Value",$colors["header_text"],1);
			DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],2);
		print "</tr>";
	    
		$cdef_items = db_fetch_assoc("select * from cdef_items where cdef_id=" . $_GET["id"] . " order by sequence");
		
		$i = 0;
		if (sizeof($cdef_items) > 0) {
		foreach ($cdef_items as $cdef_item) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td>
					<a class="linkEditMain" href="cdef.php?action=item_edit&id=<?php print $cdef_item["id"];?>&cdef_id=<?php print $cdef["id"];?>">Item #<?php print $i;?></a>
				</td>
				<td>
					<em><?php $cdef_item_type = $cdef_item["type"]; print $cdef_item_types[$cdef_item_type];?></em>: <strong><?php print get_cdef_item_name($cdef_item["id"]);?></strong>
				</td>
				<td>
					<a href="cdef.php?action=item_movedown&id=<?php print $cdef_item["id"];?>&cdef_id=<?php print $cdef["id"];?>"><img src="images/move_down.gif" border="0" alt="Move Down"></a>
					<a href="cdef.php?action=item_moveup&id=<?php print $cdef_item["id"];?>&cdef_id=<?php print $cdef["id"];?>"><img src="images/move_up.gif" border="0" alt="Move Up"></a>
				</td>
				<td align="right">
					<a href="cdef.php?action=item_remove&id=<?php print $cdef_item["id"];?>&cdef_id=<?php print $cdef["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>
				</td>
			</tr>
		<?php
		}
		}
		end_box();
	}
	
	form_save_button("cdef.php");	
}

function cdef() {
	global $colors;
	
	start_box("<strong>CDEF's</strong>", "98%", $colors["header"], "3", "center", "cdef.php?action=edit");
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Name",$colors["header_text"],1);
		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
	print "</tr>";
    	
	$cdefs = db_fetch_assoc("select * from cdef order by name");
	
	$i = 0;
	if (sizeof($cdefs) > 0) {
	foreach ($cdefs as $cdef) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="cdef.php?action=edit&id=<?php print $cdef["id"];?>"><?php print $cdef["name"];?></a>
			</td>
			<td align="right">
				<a href="cdef.php?action=remove&id=<?php print $cdef["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>
			</td>
		</tr>
	<?php
	}
	}
	end_box();	
}
?>
