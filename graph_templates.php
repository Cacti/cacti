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
	include_once ('include/form.php');
	
	if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
	if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }
	
	$current_script_name = basename($HTTP_SERVER_VARS["SCRIPT_NAME"]);

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
	case 'template_edit':
		include_once ("include/top_header.php");
		$title_text = "Template Management [edit]"; $add_text = "$current_script_name?action=graph_edit";
		include_once ("include/top_table_header.php");
		
		draw_graph_form_select();
		
		if (isset($id)) {
			$template = db_fetch_row("select * from graph_templates where id=$args[id]");
			$template_graph = db_fetch_row("select * from graph_templates_graph where graph_template_id=$args[id]");
		}else{
			unset($template);
			unset($template_graph);
		}
		
		?>
		<tr>
			<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Template Configuration</td>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Name</font><br>
				The name given to this graph template.
			</td>
			<?DrawFormItemTextBox("Name",$template[name],"","50", "40");?>
		</tr>
		
		<tr>
			<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Graph Template Configuration</td>
		</tr>
		
		<form method="post" action="graph_templates.php">
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Title</font><br>
				<?DrawStrippedFormItemCheckBox("t_title",$template_graph[t_title],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("v_title",$template_graph[v_title],"","255","40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Image Format</font><br>
				<?DrawStrippedFormItemCheckBox("t_image_format_id",$template_graph[t_title],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemDropdownFromSQL("v_image_format_id",db_fetch_assoc("select * from def_image_type order by Name"),"Name","ID",$template_graph[v_image_format_id],"","1");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Height</font><br>
				<?DrawStrippedFormItemCheckBox("t_height",$template_graph[t_height],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("v_height",$template_graph[v_height],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Width</font><br>
				<?DrawStrippedFormItemCheckBox("t_width",$template_graph[t_width],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("v_width",$template_graph[v_width],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Auto Scale</font><br>
				<?DrawStrippedFormItemCheckBox("t_auto_scale",$template_graph[t_auto_scale],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemCheckBox("v_auto_scale",$template_graph[v_auto_scale],"Auto Scale","on");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Auto Scale Options</font><br>
				<?DrawStrippedFormItemCheckBox("t_auto_scale_opts",$template_graph[t_auto_scale_opts],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<td>
			<?
				DrawStrippedFormItemRadioButton("v_auto_scale_opts", $template_graph[v_auto_scale_opts], "1", "Use --alt-autoscale","2",true);
				DrawStrippedFormItemRadioButton("v_auto_scale_opts", $template_graph[v_auto_scale_opts], "2", "Use --alt-autoscale-max","2",true);
			?>
			</td>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Logarithmic Auto Scaling</font><br>
				<?DrawStrippedFormItemCheckBox("t_auto_scale_log",$template_graph[t_auto_scale_log],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemCheckBox("v_auto_scale_log",$template_graph[v_auto_scale_log],"Logarithmic Auto Scaling (--logarithmic)","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Rigid Boundaries Mode</font><br>
				<?DrawStrippedFormItemCheckBox("t_auto_scale_rigid",$template_graph[t_auto_scale_rigid],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemCheckBox("v_auto_scale_rigid",$template_graph[v_auto_scale_rigid],"Use Rigid Boundaries Mode (--rigid)","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Auto Padding</font><br>
				<?DrawStrippedFormItemCheckBox("t_auto_padding",$template_graph[t_auto_padding],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemCheckBox("v_auto_padding",$template_graph[v_auto_padding],"Auto Padding","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Allow Grouping</font><br>
				<?DrawStrippedFormItemCheckBox("t_grouping",$template_graph[t_grouping],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemCheckBox("v_grouping",$template_graph[v_grouping],"Allow Grouping","on");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Allow Graph Export</font><br>
				<?DrawStrippedFormItemCheckBox("t_export",$template_graph[t_export],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemCheckBox("v_export",$template_graph[v_export],"Allow Graph Export","on");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Upper Limit</font><br>
				<?DrawStrippedFormItemCheckBox("t_upper_limit",$template_graph[t_upper_limit],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("v_upper_limit",$template_graph[v_upper_limit],"0","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Lower Limit</font><br>
				<?DrawStrippedFormItemCheckBox("t_lower_limit",$template_graph[t_lower_limit],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("v_lower_limit",$template_graph[v_lower_limit],"0","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Base Value</font><br>
				<?DrawStrippedFormItemCheckBox("t_base_value",$template_graph[t_base_value],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("v_base_value",$template_graph[v_base_value],"1000","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Unit Value</font><br>
				<?DrawStrippedFormItemCheckBox("t_unit_value",$template_graph[t_unit_value],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("v_unit_value",$template_graph[v_unit_value],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Unit Exponent Value</font><br>
				<?DrawStrippedFormItemCheckBox("t_unit_exponent_value",$template_graph[t_unit_exponent_value],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("v_unit_exponent_value",$template_graph[v_unit_exponent_value],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Vertical Label</font><br>
				<?DrawStrippedFormItemCheckBox("t_vertical_label",$template_graph[t_vertical_label],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("v_vertical_label",$template_graph[v_vertical_label],"","200","40");?>
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
	default:
		include_once ("include/top_header.php");
		$title_text = "Graph Template Management"; $add_text = "$current_script_name?action=template_edit";
		include_once ("include/top_table_header.php");
		
		DrawMatrixRowBegin();
			DrawMatrixHeaderItem("Template Title",$colors[panel],$colors[panel_text]);
		DrawMatrixRowEnd();
		
		$template_list = db_fetch_assoc("select id,name from graph_templates order by name");
		
		foreach ($template_list as $template) {
			DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
				?>
				<td>
					<a class="linkEditMain" href="graph_templates.php?action=template_edit&id=<?print $template[id];?>"><?print $template[name];?></a>
				</td>
				<td width="1%" align="right">
					<a href="graph_templates.php?action=template_remove&id=<?print $template[id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
				</td>
			</tr>
		<?
		$i++;
		}
		
		include_once ("include/bottom_footer.php");
		include_once ("include/bottom_table_footer.php");
		
		break;
} ?>
