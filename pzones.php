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

$current_script_name = basename($HTTP_SERVER_VARS["SCRIPT_NAME"]);

switch ($action) { 
 case 'save':    
    unset($form[action],$form[x],$form[y]);
    sql_save($form, "polling_zones");
    
    header ("Location: $current_script_name");
	break;
 case 'remove':
	if (($config["remove_verification"]["value"] == "on") && ($confirm != "yes")) {
		include_once ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete this polling zone?", $current_script_name, "?action=remove&id=$id");
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($confirm == "yes")) {
	    db_execute("delete from polling_zones where pz_id=$id");
	}
    
    header ("Location: $current_script_name");
    break;
 case 'edit':
	include_once ("include/top_header.php");
	$title_text = "Polling Zone";
	include_once ("include/top_table_header.php");
	
	if (isset($args[id])) {
		$zone = db_fetch_row("select * from polling_zones where pz_id=$id");
	}else{
		unset($zone);
	}
	
	print "<form method='post' action='".basename($HTTP_SERVER_VARS["SCRIPT_NAME"])."'>\n";
	DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%" align='right'>
			<font class="textEditTitle">Zone Name</font><br>
			
		</td>
		<?DrawFormItemTextBox('zone_name',$zone[zone_name],"","");?>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("pz_id",$args[id]);
	?>
	
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right" background="images/blue_line.gif">
			<?DrawFormSaveButton("save", "pzones.php");?>
			</form>
		</td>
	</tr>
	
	<?
	include_once ("include/bottom_table_footer.php");
	include_once ("include/bottom_footer.php");
	
	break;
 default:
    include_once ("include/top_header.php");
    $title_text = "Polling Zones";
    $add_text = "$current_script_name?action=edit";
    include_once ("include/top_table_header.php");
    
    print "<tr bgcolor='#$colors[header_panel]'>";
    DrawMatrixHeaderItem("Zone Name",$colors[header_text],1);
    DrawMatrixHeaderItem("&nbsp;",$colors[header_text],1);
    print "</tr>";
    
    $zone_list = db_fetch_assoc("select * from polling_zones order by zone_name");
    
    if (sizeof($zone_list) > 0) {
	foreach ($zone_list as $zone) {
	    DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
	    print "<td>
		    <a class='linkEditMain' href='$current_script_name?action=edit&id=$zone[pz_id]'>$zone[zone_name]</a>
		    </td>
		    <td width='1%' align='right'>
		    <a href='$current_script_name?action=remove&id=$zone[pz_id]'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a>&nbsp;
		  </td>	
		    </tr>\n";
	    
	    $i++;
	}
    } else {
	print "<tr><td colspan=2><p align=center><b>No Zones Defined</b></p></td></tr>\n";
    }
    
    include_once ("include/bottom_table_footer.php");
    include_once ("include/bottom_footer.php");
    
    break;
} 
?>
