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
$section = "Add/Edit Round Robin Archives"; include ('include/auth.php');
header("Cache-control: no-cache");
include_once ('include/form.php');

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

$current_script_name = basename($HTTP_SERVER_VARS["SCRIPT_NAME"]);

switch ($action) {
 case 'save':
    $sql_id = db_execute("replace into rrd_rra (id,name,xfilesfactor,steps,rows) 
      values ($id,\"$form[Name]\",$form[XFilesFactor],$form[Steps],$form[RRA_Rows])");
    
    if ($id == 0) {
	/* get rraid if this is a new save */
	$id = db_fetch_cell("select LAST_INSERT_ID()");
    }
    
    $sql_id = db_execute("delete from lnk_rra_cf where rraid=$id"); 
    $i = 0;
    while ($i < count($form[ConsolidationFunctionID])) {
	db_execute("insert into lnk_rra_cf (rraid,consolidationfunctionid) 
	  values ($id,".$form[ConsolidationFunctionID][$i].")");
	$i++;
    }
    
    header ("Location: rra.php");
    break;
 case 'remove':
	if (($config["remove_verification"]["value"] == "on") && ($confirm != "yes")) {
		include_once ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete this round robin archive?", $current_script_name, "?action=remove&id=$id");
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($confirm == "yes")) {
		db_execute("delete from rrd_rra where id=$id");
		db_execute("delete from lnk_ds_rra where rraid=$id");
    }
	
    header ("Location: rra.php");
    break;
 case 'edit':
	include_once ("include/top_header.php");
	$title_text = "Round Robin Archive Management [edit]";
	include_once ("include/top_table_header.php");
	
	if (isset($args[id])) {
		$rra = db_fetch_row("select * from rrd_rra where id=$id");
	}else{
		unset($rra);
	}
	
	DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			How data is to be entered in RRA's.
		</td>
		<?DrawFormItemTextBox("Hex",$rra[Name],"","100", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Consolidation Functions</font><br>
			How data is to be entered in RRA's.
		</td>
		<?DrawFormItemMultipleList("ConsolidationFunctionID","select * from def_cf","Name","ID","select * from lnk_rra_cf where rraid=$id","ConsolidationFunctionID","");?>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">X-Files Factor</font><br>
			The amount of unknown data that can still be regarded as known.
		</td>
		<?DrawFormItemTextBox("XFilesFactor",$rra[XFilesFactor],"","100", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Steps</font><br>
			How many data points are needed to put data into the RRA.
		</td>
		<?DrawFormItemTextBox("Steps",$rra[Steps],"","100", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Rows</font><br>
			How many generations data is kept in the RRA.
		</td>
		<?DrawFormItemTextBox("Rows",$rra[Rows],"","100", "40");?>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("ID",$args[id]);
	?>
	
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right" background="images/blue_line.gif">
			<?DrawFormSaveButton("save", "rra.php");?>
		</td>
	</tr>
	<?
	
	include_once ("include/bottom_table_footer.php");
	include_once ("include/bottom_footer.php");
	
	break;
 default:
	include_once ("include/top_header.php");
	$title_text = "Round Robin Archive Management"; $add_text = "$current_script_name?action=edit";
	include_once ("include/top_table_header.php");
	
	print "<tr bgcolor='#$colors[header_panel]'>";
		DrawMatrixHeaderItem("Name",$colors[header_text],1);
		DrawMatrixHeaderItem("Steps",$colors[header_text],1);
		DrawMatrixHeaderItem("Rows",$colors[header_text],2);
	print "</tr>";
    
	$rra_list = db_fetch_assoc("select ID,Name,Rows,Steps from rrd_rra order by steps");
	$rows = sizeof($color_list);
	
	foreach ($rra_list as $rra) {
		DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
			?>
			<td>
				<a class="linkEditMain" href="<?print $current_script_name;?>?action=edit&id=<?print $rra[ID];?>"><?print $rra[Name];?></a>
			</td>
			<td>
				<?print $rra[Steps];?>
			</td>
			<td>
				<?print $rra[Rows];?>
			</td>
			<td width="1%" align="right">
				<a href="<?print $current_script_name;?>?action=remove&id=<?print $rra[ID];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	$i++;
	}
	
	include_once ("include/bottom_table_footer.php");
	include_once ("include/bottom_footer.php");
	
	break;
} ?>
