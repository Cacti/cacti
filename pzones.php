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
$section = "Polling Zones";
include ('include/auth.php');
include_once ("include/form.php");

if ($form[action]) { $action = $form[action]; } else { $action = $args[action]; }
if ($form[ID]) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) { 
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;  
	case 'remove':
 		pzones_remove();
    
    		header ("Location: pzones.php");
		break;
	case 'edit':
		include_once ("include/top_header.php");
		
		pzones_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	 default:
	 	include_once ("include/top_header.php");
	    
	    	pzones();
	    
	    	include_once ("include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $form;
	
	if (isset($form[save_component_pzones])) {
		pzones_save();
		return "pzones.php";
	}
}

/* --------------------------
    Polling Zones Functions
   -------------------------- */

function pzones_save() {
	global $form;
	
	unset($form[action],$form[x],$form[y]);
	sql_save($form, "polling_zones");	
}

function pzones_remove() {
	global $args;
	
	if (($config["remove_verification"]["value"] == "on") && ($args[confirm] != "yes")) {
		include_once ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete this polling zone?", getenv("HTTP_REFERER"), "pzones.php?action=remove&id=$args[id]");
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($args[confirm] == "yes")) {
		db_execute("delete from polling_zones where pz_id=$args[id]");
	}	
}

function pzones_edit() {
	global $args, $colors;
	
	start_box("Polling Zones [edit]", "", "");
	
	if (isset($args[id])) {
		$zone = db_fetch_row("select * from polling_zones where pz_id=$args[id]");
	}else{
		unset($zone);
	}
	
	print "<form method='post' action='".basename($HTTP_SERVER_VARS["SCRIPT_NAME"])."'>\n";
	DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Zone Name</font><br>
			
		</td>
		<?DrawFormItemTextBox('zone_name',$zone[zone_name],"","");?>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("pz_id",$args[id]);
	DrawFormItemHiddenTextBox("save_component_pzones","1","");
	?>
	
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right" background="images/blue_line.gif">
			<?DrawFormSaveButton("save", "pzones.php");?>
			</form>
		</td>
	</tr>
	
	<?
	end_box();	
}

function pzones() {
	global $colors;
	
	start_box("Polling Zones", "", "pzones.php?action=edit");
	
	print "<tr bgcolor='#$colors[header_panel]'>";
	DrawMatrixHeaderItem("Zone Name",$colors[header_text],1);
	DrawMatrixHeaderItem("&nbsp;",$colors[header_text],1);
	print "</tr>";
	
	$zone_list = db_fetch_assoc("select * from polling_zones order by zone_name");
	
	if (sizeof($zone_list) > 0) {
	foreach ($zone_list as $zone) {
	    DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
	    print "<td>
		    <a class='linkEditMain' href='pzones.php?action=edit&id=$zone[pz_id]'>$zone[zone_name]</a>
		    </td>
		    <td width='1%' align='right'>
		    <a href='pzones.php?action=remove&id=$zone[pz_id]'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a>&nbsp;
		  </td>	
		    </tr>\n";
	    
	    $i++;
	}
	}else{
		print "<tr><td colspan=2><p align=center><b>No Zones Defined</b></p></td></tr>\n";
	}
	
	end_box();
}

?>
