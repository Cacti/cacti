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
<? 	$section = "Add/Edit Graphs"; include ('include/auth.php');
	header("Cache-control: no-cache");
	//include ('include/database.php');
	//include ('include/config.php');
	include_once ('include/form.php');
	
	if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
	if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }
	
	$current_script_name = basename($HTTP_SERVER_VARS["SCRIPT_NAME"]);
	
	function draw_main_form_select() { 
		global $current_script_name, $colors;?>
		<tr>
			<td valign="middle">
				<table cellspacing="0" cellpadding="0" border="0">
					<tr>
						<td bgcolor="#<?print $colors[panel];?>">
							<a href="<?print $current_script_name;?>"><img src="images/button_graph_management_<?if ($_GET[action]==""){ print "down.gif"; }else{ print "up.gif"; }?>" border="0" alt="Graph Management" align="absmiddle"></a>
						</td>
						<td>
							<a href="<?print $current_script_name;?>?action=tree"><img src="images/button_graph_trees_<?if (strstr($_GET[action],"tree")){ print "down.gif"; }else{ print "up.gif"; }?>" border="0" alt="Graph Management" align="absmiddle"></a>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	<?}
	
	function draw_graph_form_select() { 
		global $current_script_name, $colors; ?>
		<tr bgcolor="#<?print $colors[panel];?>">
			<form name="form_graph_id">
			<td>
				<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
					<option value="<?print $current_script_name;?>"<?if (($_GET[action]=="") || (strstr($_GET[action],"graph"))) {?> selected<?}?>>Graph Configuration</option>
					<option value="<?print $current_script_name;?>?action=graph"<?if (strstr($_GET[action],"graph")){?> selected<?}?>>Custom Graph Item Configuration</option>
				</select>
			
				<input type="image" src="images/button_go.gif" alt="Go" align="absmiddle">
			</td>
			</form>
		</tr>
	<?}
	
switch ($action) {
	case 'graph_duplicate':
		include_once ('include/utility_functions.php');
		
		DuplicateGraph($id);
		
		header ("Location: graphs.php");
		break;
	case 'graph_save':
		if ($lowerlimit==""){$lowerlimit=0;} /* no nulls; zeros */
		if ($upperlimit==""){$upperlimit=0;} /* no nulls; zeros */
		if ($unitexponentvalue==""){$unitexponentvalue=0;} /* no nulls; zeros */
		
		if ($id != 0) {
			$grouping = db_fetch_cell("select Grouping from rrd_graph where id=$id");
			
			if ($grouping != $grouping) { /* the value has changed */
				include_once ('include/utility_functions.php');
				
				if ($grouping == "on") {
					group_graph_items($id);
				}else{
					ungroup_graph_items($id);
				}
			}
		}
		
		db_execute("replace into rrd_graph (id,imageformatid,title,height,width,
			upperlimit,lowerlimit,verticallabel,autoscale,autopadding,autoscaleopts,rigid,
			basevalue,grouping,export,unitvalue,unitexponentvalue,autoscalelog) values ($id,$imageformatid,
			\"$title\",$height,$width,$upperlimit,$lowerlimit,\"$verticallabel\",\"$autoscale\",
			\"$autopadding\",$autoscaleopts,\"$rigid\",$basevalue,\"$grouping\",\"$export\",
			\"$unitvalue\",$unitexponentvalue,\"$autoscalelog\")");
		
		if ($id == 0) {
			/* get graphid if this is a new save */
			$db_id = db_fetch_cell("select LAST_INSERT_ID()");
			
			if ($db_id != 0) {
				$id = $db_id;
			}
		}
		
		header ("Location: graphs.php");
		break;
	case 'graph_remove':
		if (($config["remove_verification"]["value"] == "on") && ($confirm != "yes")) {
			include_once ('include/top_header.php');
			DrawConfirmForm("Are You Sure?", "Are you sure you want to delete this graph?", $current_script_name, "?action=remove&id=$id");
			exit;
		}
		
		if (($config["remove_verification"]["value"] == "") || ($confirm == "yes")) {
			db_execute("delete from rrd_graph where id=$id");
			db_execute("delete from rrd_graph_item where graphid=$id");
			db_execute("delete from graph_hierarchy_items where graphid=$id");
			db_execute("delete from auth_graph where graphid=$id");
		}
		
		header ("Location: graphs.php");
		break;
	case 'graph_edit':
		include_once ("include/top_header.php");
		$title_text = "Graph Management [edit]"; $add_text = "$current_script_name?action=graph_edit";
		include_once ("include/top_table_header.php");
		
		draw_main_form_select();
		
			?>
			<tr>
				<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Graph Configuration</td>
			</tr>
			<?
		
		draw_graph_form_select();
		
		if (isset($id)) {
			$graphs = db_fetch_row("select * from rrd_graph where id=$id", $cnn_id);
		}else{
			unset($graphs);
		}
		?>
		
		<tr>
			<td colspan="2" bgcolor="#00438C">
				<img src="images/transparent_line.gif" width="170" height="1" border="0"><br>
			</td>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Title</font><br>
				The name that is printed on the graph.
			</td>
			<?DrawFormItemTextBox("Title",$graphs[Title],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Image Format</font><br>
				The type of graph that is generated; GIF or PNG.
			</td>
			<?DrawFormItemDropdownFromSQL("ImageFormatID",$cnn_id,"select * from def_image_type order by name","Name","ID",$graphs[ImageFormatID],"","1");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Height</font><br>
				The height (in pixels) that the graph is.
			</td>
			<?DrawFormItemTextBox("Height",$graphs[Height],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Width</font><br>
				The width (in pixels) that the graph is.
			</td>
			<?DrawFormItemTextBox("Width",$graphs[Width],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Auto Scale</font><br>
				Auto scale vertical data instead of defining an upper and lower limit. Note: if this 
				is check both the Upper and Lower limit will be ignored.
			</td>
			<td>
			<?
			DrawStrippedFormItemCheckBox("Rigid",$graphs[Rigid],"Use Rigid Boundaries Mode (--rigid)","",true);
			DrawStrippedFormItemCheckBox("AutoScale",$graphs[AutoScale],"Auto Scale","on",true);
			DrawStrippedFormItemRadioButton("AutoScaleOpts", $graphs[AutoScaleOpts], "1", "Use --alt-autoscale","2",true);
			DrawStrippedFormItemRadioButton("AutoScaleOpts", $graphs[AutoScaleOpts], "2", "Use --alt-autoscale-max","2",true);
			DrawStrippedFormItemCheckBox("AutoScaleLog",$graphs[AutoScaleLog],"Logarithmic Auto Scaling (--logarithmic)","",true);
			?>
			</td>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Auto Padding</font><br>
				Pad text so that legend and graph data always line up. Note: this could cause graphs 
				to take longer to render because of the larger overhead. Also Auto Padding may not 
				be accurate on all types of graphs, consistant labeling usually helps.
			</td>
			<?DrawFormItemCheckBox("AutoPadding",$graphs[AutoPadding],"Auto Padding","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Allow Grouping</font><br>
				This will enable you to "group" items of your graph together for eaier manipulation. 
				Note when you check this box and save, cacti will automatically group the items in 
				your graph; you may have to re-group part of the graph manually.
			</td>
			<?DrawFormItemCheckBox("Grouping",$graphs[Grouping],"Allow Grouping","on");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Allow Graph Export</font><br>
				Choose whether this graph will be included in the static html/png export if you use 
				cacti's export feature.
			</td>
			<?DrawFormItemCheckBox("Export",$graphs[Export],"Allow Graph Export","on");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Upper Limit</font><br>
				The maximum vertical value for the rrd graph.
			</td>
			<?DrawFormItemTextBox("UpperLimit",$graphs[UpperLimit],"0","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Lower Limit</font><br>
				The minimum vertical value for the rrd graph.
			</td>
			<?DrawFormItemTextBox("LowerLimit",$graphs[LowerLimit],"0","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Base Value</font><br>
				Should be set to 1024 for memory and 1000 for traffic measurements.
			</td>
			<?DrawFormItemTextBox("BaseValue",$graphs[BaseValue],"1000","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Unit Value</font><br>
				(--unit) Sets the exponent value on the Y-axis for numbers. Note: This option was 
				recently added in rrdtool 1.0.36.
			</td>
			<?DrawFormItemTextBox("UnitValue",$graphs[UnitValue],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Unit Exponent Value</font><br>
				What unit cacti should use on the Y-axis. Use 3 to display everything in 'k' or -6 
				to display everything in 'u' (micro).
			</td>
			<?DrawFormItemTextBox("UnitExponentValue",$graphs[UnitExponentValue],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Vertical Label</font><br>
				The label vertically printed to the left of the graph.
			</td>
			<?DrawFormItemTextBox("VerticalLabel",$graphs[VerticalLabel],"","50", "40");?>
		</tr>
		
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right" background="images/blue_line.gif">
				<?DrawFormSaveButton("save");?>
			</td>
		</tr>
		<?
		
		DrawFormItemHiddenIDField("id",$id);
		DrawFormFooter();
		
		include_once ("include/bottom_footer.php");
		
		break;
	case 'tree_edit':
		include_once ("include/top_header.php");
		$title_text = "Graph Management - Graph Tree Configuration [edit]"; $add_text = "$current_script_name?action=edit_tree";
		include_once ("include/top_table_header.php");
		
		if (isset($id)) {
			$graph_tree = db_fetch_row("select * from rrd_graph_tree where id=$_GET[id]", $cnn_id);
		}else{
			unset($graph_tree);
		}
		
		draw_main_form_select();
		
		?>
		<tr>
			<td colspan="2" bgcolor="#00438C">
				<img src="images/transparent_line.gif" width="170" height="1" border="0"><br>
			</td>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="40%">
				<font class="textEditTitle">Tree Name</font><br>
				Enter a name for this tree.
			</td>
			<?DrawFormItemTextBox("name",$graph_tree[name],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="40%">
				<font class="textEditTitle">Show as Tab</font><br>
				Whether to show this tree as a tab on the graph management page.
			</td>
			<?DrawFormItemCheckBox("show_tab",$graph_tree[show_tab],"Show as Tab","",true);?>
		</tr>
		
		<tr bgcolor="#<?print $colors[form_alternate2];?>">
			 <td colspan="2" align="right" background="images/blue_line.gif">
				<?DrawFormSaveButton("save");?>
			</td>
		</tr>
		<?
		
		include_once ("include/bottom_footer.php");
		include_once ("include/bottom_table_footer.php");
		
		break;
	case 'tree':
		include_once ("include/top_header.php");
		$title_text = "Graph Management - Graph Tree Configuration"; $add_text = "$current_script_name?action=tree_edit";
		include_once ("include/top_table_header.php");
		
		draw_main_form_select();
		
		?>
		<tr>
			<td colspan="3" bgcolor="#00438C">
				<img src="images/transparent_line.gif" width="170" height="1" border="0"><br>
			</td>
		</tr>
		<?
		
		DrawMatrixRowBegin();
			DrawMatrixHeaderItem("Tree Name",$colors[panel],$colors[panel_text]);
			DrawMatrixHeaderItem("Display as Tab",$colors[panel],$colors[panel_text]);
		DrawMatrixRowEnd();
		
		$graph_tree_list = db_fetch_assoc("select * from rrd_graph_tree", $cnn_id);
		
		foreach ($graph_tree_list as $graph_tree) {
			DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
				?>
				<td>
					<a class="linkEditMain" href="graphs.php?action=tree_edit&id=<?print $graph_tree[id];?>"><?print $graph_tree[name];?></a>
				</td>
				<td>
					<?if ($graph_tree[show_tab] == "on") { print "Yes"; }else{ print "No"; }?>
				</td>
				<td width="1%" align="right">
					<a href="graphs.php?action=tree_remove&id=<?print $graph_tree[id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
				</td>
			</tr>
		<?
		$i++;
		}
		
		include_once ("include/bottom_footer.php");
		include_once ("include/bottom_table_footer.php");
		
		break;
	default:
		include_once ("include/top_header.php");
		$title_text = "Graph Management"; $add_text = "$current_script_name?action=graph_edit";
		include_once ("include/top_table_header.php");
		
		draw_main_form_select();
		?>
			

			<tr height="33">
				<td valign="bottom" colspan="2" background="images/tab_back.gif">
					<table border="0" cellspacing="0" cellpadding="0">
						<tr>
							<td nowrap class="textTab" align="center" background="images/tab_middle.gif">
								<img src="images/tab_left.gif" border="0" align="absmiddle">Core Routers <img src="images/delete_icon_dark_back.gif" border="0" alt="Remove this Tree's Tab" align="absmiddle"><img src="images/tab_right.gif" border="0" align="absmiddle">
							</td>
							<td nowrap class="textTab" align="center" background="images/tab_middle.gif">
								<img src="images/tab_left.gif" border="0" align="absmiddle">Catalyst Fabric <img src="images/delete_icon_dark_back.gif" border="0" alt="Remove this Tree's Tab" align="absmiddle"><img src="images/tab_right.gif" border="0" align="absmiddle">
							</td>
							<td nowrap class="textTab" align="center" background="images/tab_middle.gif">
								<img src="images/tab_left.gif" border="0" align="absmiddle">Server Farm (1) <img src="images/delete_icon_dark_back.gif" border="0" alt="Remove this Tree's Tab" align="absmiddle"><img src="images/tab_right.gif" border="0" align="absmiddle">
							</td>
							<td nowrap class="textTab" align="center" background="images/tab_middle.gif">
								<img src="images/tab_left.gif" border="0" align="absmiddle">Web Server <img src="images/delete_icon_dark_back.gif" border="0" alt="Remove this Tree's Tab" align="absmiddle"><img src="images/tab_right.gif" border="0" align="absmiddle">
							</td>
						</tr>
					</table>
				</td>
				<form name="form_graph_tree">
				<td align="right" valign="middle" background="images/tab_back.gif">
					<select class="cboSmall" name="cbo_graph_tree" onChange="window.location=document.form_graph_tree.cbo_graph_tree.options[document.form_graph_tree.cbo_graph_tree.selectedIndex].value">
						<option value="<?print $current_script_name;?>" selected>Core Routers</option>
						<option value="<?print $current_script_name;?>">Catalyst Fabric</option>
						<option value="<?print $current_script_name;?>">Server Farm (1)</option>
						<option value="<?print $current_script_name;?>">Web Server</option>
					</select>
				</td>
				</form>
			</tr>
			
		<?
		
		DrawMatrixRowBegin();
			DrawMatrixHeaderItem("Graph Title",$colors[panel],$colors[panel_text]);
			DrawMatrixHeaderItem("Size",$colors[panel],$$colors[panel_text]);
		DrawMatrixRowEnd();
		
		$graph_list = db_fetch_assoc("select ID,Height,Width,Title from rrd_graph order by Title", $cnn_id);
		
		foreach ($graph_list as $graph) {
			DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
				?>
				<td>
					<a class="linkEditMain" href="graphs.php?action=graph_edit&id=<?print $graph[ID];?>"><?print $graph[Title];?></a>
				</td>
				<td>
					<?print $graph[Height];?>x<?print $graph[Width];?>
				</td>
				<td width="1%" align="right">
					<a href="graphs.php?action=graph_remove&id=<?print $graph[ID];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
				</td>
			</tr>
		<?
		$i++;
		}
		
		include_once ("include/bottom_footer.php");
		include_once ("include/bottom_table_footer.php");
		
		break;
} ?>