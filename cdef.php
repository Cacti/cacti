<?/* 
   +-------------------------------------------------------------------------+
   | Copyright (C) 2002 Ian Berry                                            |
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
   | cacti: the rrdtool frontend [php-auth, php-tree, php-form]              |
   +-------------------------------------------------------------------------+
   | This code is currently maintained and debugged by Ian Berry, any        |
   | questions or comments regarding this code should be directed to:        |
   | - iberry@raxnet.net                                                     |
   +-------------------------------------------------------------------------+
   | - raXnet - http://www.raxnet.net/                                       |
   +-------------------------------------------------------------------------+
   */?>
<?
$section = "Add/Edit Graphs"; 
include ('include/auth.php');
header("Cache-control: no-cache");

include_once ("include/functions.php");
include_once ("include/cdef_functions.php");
include_once ("include/config_arrays.php");
include_once ('include/form.php');

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) {
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;
	case 'item_movedown':
		item_movedown();
		
		header ("Location: cdef.php?action=edit&id=$args[cdef_id]");
		break;
	case 'item_moveup':
		item_moveup();
		
		header ("Location: cdef.php?action=edit&id=$args[cdef_id]");
		break;
	case 'item_remove':
		item_remove();
	    
		header ("Location: cdef.php?action=edit&id=$args[cdef_id]");
		break;
	case 'item_edit':
		include_once ("include/top_header.php");
		
		item_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'remove':
		cdef_remove();
		
		header ("Location: cdef.php");
		break;
	case 'edit':
		include_once ("include/top_header.php");
		
		cdef_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		cdef();
		
		include_once ("include/bottom_footer.php");
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function draw_cdef_preview($cdef_id) {
	global $colors; ?>
	<tr bgcolor="#<?print $colors[panel];?>">
		<td>
			<pre><?print get_cdef($cdef_id);?></pre>
		</td>
	</tr>	
<?}


/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $form;
	
	if (isset($form[save_component_cdef])) {
		cdef_save();
		return "cdef.php";
	}elseif (isset($form[save_component_item])) {
		item_save();
		return "cdef.php?action=edit&id=$form[cdef_id]";
	}
}

/* --------------------------
    CDEF Item Functions
   -------------------------- */

function item_movedown() {
	global $args;
	
	move_item_down("cdef_items", $args[id], "cdef_id=$args[cdef_id]");	
}

function item_moveup() {
	global $args;
	
	move_item_up("cdef_items", $args[id], "cdef_id=$args[cdef_id]");	
}

function item_remove() {
	global $args;
	
	db_execute("delete from cdef_items where id=$args[id]");	
}

function item_save() {
	global $form;
	
	if ($form[value_function] != "0") { $current_type = 1; $current_value = $form[value_function]; }
	if ($form[value_operator] != "0") { $current_type = 2; $current_value = $form[value_operator]; }
	if ($form[value_data_source] != "0") { $current_type = 3; $current_value = $form[value_data_source]; }
	if ($form[value_special_data_source] != "0") { $current_type = 4; $current_value = $form[value_special_data_source]; }
	if ($form[value_cdef] != "0") { $current_type = 5; $current_value = $form[value_cdef]; }
	if ($form[value_custom] != "") { $current_type = 6; $current_value = $form[value_custom]; }
	
	if (!(isset($current_type))) {
		/* YOU MUST SELECT SOMETHING */
		header ("Location: cdef.php?action=edit&id=$form[cdef_id]"); exit;
	}
	
	$sequence = get_sequence($form[id], "sequence", "cdef_items", "cdef_id=$form[cdef_id]");
	
 	$save["id"] = $form["id"];
	$save["cdef_id"] = $form["cdef_id"];
	$save["sequence"] = $sequence;
	$save["type"] = $current_type;
	$save["value"] = $current_value;
	
	sql_save($save, "cdef_items");	
}

function item_edit() {
	global $args, $colors, $cdef_functions, $cdef_operators, $custom_data_source_types;
	
	if (isset($args[id])) {
		$cdef = db_fetch_row("select * from cdef_items where id=$args[id]");
		$current_type = $cdef[type];
		$values[$current_type] = $cdef[value];
	}else{
		unset($cdef);
	}
	
	start_box("", "", "");
	draw_cdef_preview($args[cdef_id]);
	end_box();
	
	start_box("<strong>CDEF Items [edit]</strong> (" . db_fetch_cell("select name from cdef where id=$cdef_id") . ")", "", "");
	
	?>
	<form method="post" action="cdef.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td colspan="2">
			<font class="textHeader">Choose any one of these items:</font>
		</td>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Function</font>
		</td>
		<?DrawFormItemDropdownFromSQL("value_function",$cdef_functions,"","",$values[1],"None","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Operator</font>
		</td>
		<?DrawFormItemDropdownFromSQL("value_operator",$cdef_operators,"","",$values[2],"None","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Data Source</font>
		</td>
		<?DrawFormItemDropdownFromSQL("value_data_source",db_fetch_assoc("select descrip,item_id from polling_items"),"descrip","item_id",$values[3],"None","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Special Data Source</font>
		</td>
		<?DrawFormItemDropdownFromSQL("value_special_data_source",$custom_data_source_types,"","",$values[4],"None","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Another CDEF</font>
		</td>
		<?DrawFormItemDropdownFromSQL("value_cdef",db_fetch_assoc("select name,id from cdef"),"name","id",$values[5],"None","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Custom String</font>
		</td>
		<?DrawFormItemTextBox("value_custom",$values[6],"","150", "40");?>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("id",$args[id]);
	DrawFormItemHiddenIDField("cdef_id",$args[cdef_id]);
	DrawFormItemHiddenTextBox("save_component_item","1","");
	end_box();
	
	start_box("", "", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "cdef.php");?>
		</td>
	</tr>
	</form>
	<?
	end_box();	
}
   
/* ---------------------
    CDEF Functions
   --------------------- */

function cdef_remove() {
	global $args;
	
    	db_execute("delete from cdef where id=$args[id]");
	db_execute("delete from cdef_items where cdef_id=$args[id]");
}

function cdef_save() {
	global $form;
	
	$save["id"] = $form["id"];
	$save["name"] = $form["name"];
	
	sql_save($save, "cdef");	
}

function cdef_edit() {
	global $args, $colors, $cdef_item_types;
	
	start_box("<strong>CDEF's [edit]</strong>", "", "");
	
	if (isset($args[id])) {
		$cdef = db_fetch_row("select * from cdef where id=$args[id]");
	}else{
		unset($cdef);
	}
	
	?>
	<form method="post" action="cdef.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			A useful name for this CDEF.
		</td>
		<?DrawFormItemTextBox("name",$cdef[name],"","255", "40");?>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("id",$args[id]);
	end_box();
	
	start_box("", "", "");
	draw_cdef_preview($args[id]);
	end_box();
	
	start_box("CDEF Items", "", "cdef.php?action=item_edit&cdef_id=$cdef[id]");
	
	print "<tr bgcolor='#$colors[header_panel]'>";
		DrawMatrixHeaderItem("Item",$colors[header_text],1);
		DrawMatrixHeaderItem("Item Value",$colors[header_text],1);
		DrawMatrixHeaderItem("&nbsp;",$colors[header_text],2);
	print "</tr>";
    
	$cdef_items = db_fetch_assoc("select * from cdef_items where cdef_id=$args[id] order by sequence");
	
	if (sizeof($cdef_items) > 0) {
	foreach ($cdef_items as $cdef_item) {
		DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="cdef.php?action=item_edit&id=<?print $cdef_item[id];?>&cdef_id=<?print $cdef[id];?>">Item #<?print $i;?></a>
			</td>
			<td>
				<em><?$cdef_item_type = $cdef_item[type]; print $cdef_item_types[$cdef_item_type];?></em>: <strong><?print get_cdef_item_name($cdef_item[id]);?></strong>
			</td>
			<td>
				<a href="cdef.php?action=item_movedown&id=<?print $cdef_item[id];?>&cdef_id=<?print $cdef[id];?>"><img src="images/move_down.gif" border="0" alt="Move Down"></a>
				<a href="cdef.php?action=item_moveup&id=<?print $cdef_item[id];?>&cdef_id=<?print $cdef[id];?>"><img src="images/move_up.gif" border="0" alt="Move Up"></a>
			</td>
			<td width="1%" align="right">
				<a href="cdef.php?action=item_remove&id=<?print $cdef_item[id];?>&cdef_id=<?print $cdef[id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	}
	}
	end_box();
	
	DrawFormItemHiddenTextBox("save_component_cdef","1","");
	
	start_box("", "", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "cdef.php");?>
		</td>
	</tr>
	</form>
	<?
	end_box();	
}

function cdef() {
	global $colors;
	
	start_box("<strong>CDEF's</strong>", "", "cdef.php?action=edit");
	                         
	print "<tr bgcolor='#$colors[header_panel]'>";
		DrawMatrixHeaderItem("Name",$colors[header_text],1);
		DrawMatrixHeaderItem("&nbsp;",$colors[header_text],1);
	print "</tr>";
    
	$cdefs = db_fetch_assoc("select * from cdef order by name");
	
	if (sizeof($cdefs) > 0) {
	foreach ($cdefs as $cdef) {
		DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="cdef.php?action=edit&id=<?print $cdef[id];?>"><?print $cdef[name];?></a>
			</td>
			<td width="1%" align="right">
				<a href="cdef.php?action=remove&id=<?print $cdef[id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	}
	}
	end_box();	
}
?>
