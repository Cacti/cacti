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
$section = "Add/Edit Graphs"; include ('include/auth.php');
include_once ('include/form.php');

switch ($_REQUEST["action"]) {
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;          
	case 'remove':
		color_remove();
	    
		header ("Location: color.php");
		break;
	case 'edit':
		include_once ("include/top_header.php");
		
		color_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		color();
		
		include_once ("include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_color"])) {
		color_save();
		return "color.php";
	}
}

/* -----------------------
    Color Functions
   ----------------------- */

function color_save() {
	$save["ID"] = $_POST["ID"];
	$save["Hex"] = $_POST["Hex"];
	
	sql_save($save, "def_colors");	
}

function color_remove() {
	db_execute("delete from def_colors where id=" . $_GET["id"]);	
}

function color_edit() {
	global $colors;
	
	if (isset($_GET["id"])) {
		$color = db_fetch_row("select * from def_colors where id=" . $_GET["id"]);
	}else{
		unset($color);
	}
	
	start_box("<strong>Color Management [edit]</strong>", "", "");
    	
	?>
	<form method="post" action="color.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Hex Value</font><br>
			The hex value for this color; valid range: 000000-FFFFFF.
		</td>
		<?DrawFormItemTextBox("Hex",$color["Hex"],"","6", "40");?>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("ID",$_GET["id"]);
	DrawFormItemHiddenTextBox("save_component_color","1","");
	?>
	
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right" background="images/blue_line.gif">
			<?DrawFormSaveButton("save", "color.php");?>
			</form>
		</td>
	</tr>
	<?
	end_box();	
}

function color() {
	global $colors;
	
	start_box("<strong>Color Management</strong>", "", "color.php?action=edit");
	
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
    
	$color_list = db_fetch_assoc("select * from def_colors order by hex");
	
	if (sizeof($color_list) > 0) {
		$j=0; ## even/odd counter
		foreach ($color_list as $color) {
			$j++;
			if ($j % 2 == 1) {
				DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i); $i++;
					?>
					<td>
						<a class="linkEditMain" href="color.php?action=edit&id=<?print $color["ID"];?>"><?print $color["Hex"];?></a>
					</td>
					<td bgcolor="#<?print $color["Hex"];?>" width="1%">&nbsp;</td>
					<td width="1%" align="right">
						<a href="color.php?action=remove&id=<?print $color["ID"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
					</td>
				<?	$j=1;
			} else { ?>
					<td></td>
					<td>
						<a class="linkEditMain" href="color.php?action=edit&id=<?print $color["ID"];?>"><?print $color["Hex"];?></a>
					</td>
					<td bgcolor="#<?print $color["Hex"];?>" width="1%">&nbsp;</td>
					<td width="1%" align="right">
						<a href="color.php?action=remove&id=<?print $color["ID"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
					</td>
				</tr>
			<?
			}
		}
		## check for completion of odd number second column:
		if ($j == 1) {
			?>
				<td colspan=4></td>
				</tr>
			<?
		}
	}
	end_box();	
}
   
?>
