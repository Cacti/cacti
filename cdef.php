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
include_once ('include/form.php');

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

$current_script_name = basename($HTTP_SERVER_VARS["SCRIPT_NAME"]);

switch ($action) {
 case 'save':
 	$save["ID"] = $form["ID"];
	$save["Hex"] = $form["Hex"];
	
	sql_save($save, "def_colors");

	header ("Location: cdef.php");
	break;
 case 'remove':
    	db_execute("delete from def_colors where id=$args[id]");
    
    	header ("Location: cdef.php");
	break;
 case 'edit':
	include_once ("include/top_header.php");
	
	start_box("<strong>CDEF's [edit]</strong>", "", "");
	
	if (isset($args[id])) {
		$cdef = db_fetch_row("select * from cdef where id=$args[id]");
	}else{
		unset($cdef);
	}
    
	DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			A useful name for this CDEF.
		</td>
		<?DrawFormItemTextBox("name",$cdef[name],"","255", "40");?>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("id",$args[id]);
	
	?>
	
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right" background="images/blue_line.gif">
			<?DrawFormSaveButton("save", "color.php");?>
			</form>
		</td>
	</tr>
	<?
	
	end_box();
	start_box("", "", "");
	
	print "<tr bgcolor='#$colors[header_panel]'>";
		DrawMatrixHeaderItem("Item",$colors[header_text],1);
		DrawMatrixHeaderItem("Item Value",$colors[header_text],1);
		DrawMatrixHeaderItem("&nbsp;",$colors[header_text],1);
	print "</tr>";
    
	$cdef_items = db_fetch_assoc("select * from cdef_items where order by sequence");
	
	if (sizeof($cdef_items) > 0) {
	foreach ($cdef_items as $cdef_item) {
		DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="cdef.php?action=edit_item&id=<?print $cdef_item[id];?>">Item #<?print $i;?></a>
			</td>
			<td>
				Value
			</td>
			<td width="1%" align="right">
				<a href="cdef.php?action=remove_item&id=<?print $cdef_item[id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	}
	}
	end_box();
	
	include_once ("include/bottom_footer.php");
	
    	break;
 default:
	include_once ("include/top_header.php");
	
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
	
	include_once ("include/bottom_footer.php");
	
   	break;
} ?>
