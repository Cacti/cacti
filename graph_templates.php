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

	function draw_graph_form_select($main_action) { 
		global $current_script_name, $colors, $args; ?>
		<tr bgcolor="<?print $colors[panel];?>">
			</form>
			<form name="form_graph_id">
			<td colspan="6">
				<table width="100%" cellpadding="0" cellspacing="0">
					<tr>
						<td width="1%">
							<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
								<option value="<?print $current_script_name;?>?action=template_edit&graph_template_id=<?print $args[graph_template_id];?>"<?if (strstr($args[action],"template")) {?> selected<?}?>>Graph Template Configuration</option>
								<option value="<?print $current_script_name;?>?action=item&graph_template_id=<?print $args[graph_template_id];?>"<?if (strstr($args[action],"item")){?> selected<?}?>>Graph Item Template Configuration</option>
							</select>
						</td>
						<td>
							<a href="graph_templates.php<?print $main_action;?>"><img src="images/button_go.gif" alt="Go" border="0" align="absmiddle"></a><br>
						</td>
					</tr>
				</table>
			</td>
			</form>
		</tr>
	<?}
	
switch ($action) {
	case 'item_edit':
		include_once ("include/top_header.php");
		$title_text = "Graph Template Management [edit]";
		include_once ("include/top_table_header.php");
		
		draw_graph_form_select("?action=item&graph_template_id=$args[graph_template_id]");
		
		new_table();
		
		if (isset($args[graph_template_id_graph])) {
			$template_item = db_fetch_row("select * from graph_templates_item where id=$args[graph_template_id_graph]");
		}else{
			unset($template_item);
		}
		
		/* get current graph name for the header text */
		$graph_parameters = db_fetch_row("select v_title,v_grouping from graph_templates_graph where id=$args[graph_template_id]");
		
		?>
		<tr>
			<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Template Item Configuration</td>
		</tr>
		
		<?
		/* by default, select the LAST DS chosen to make everyone's lives easier */
		$default = db_fetch_row("select v_task_item_id from graph_templates_item where graph_template_id=$args[graph_template_id] order by sequence_parent DESC,sequence DESC");
    
		if (sizeof($default) > 0) {
			$default_item = $default[v_task_item_id];
		}else{
			$default_item = 0;
		}
		
		DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Task Item</font><br>
				The task to use for this graph item; not used for COMMENT fields.
			</td>
			<?DrawFormItemDropdownFromSQL("v_task_item_id",db_fetch_assoc("select id,descrip from polling_items order by descrip"),"descrip","id",$template_item[parent],"None",$default_item);?>
		</tr>
		
		<?
		if ($graph_parameters[v_grouping] == "on") {
			/* default item (last item) */
			$groups = db_fetch_assoc("select 
				CONCAT_WS('',def_graph_type.name,': ',polling_items.descrip) as name,
				graph_templates_item.id
				from graph_templates_item left join def_graph_type on graph_templates_item.v_graph_type_id=def_graph_type.id
				left join polling_items on graph_templates_item.v_task_item_id=polling_items.item_id
				where graph_templates_item.graph_template_id=$args[graph_template_id]
				and (def_graph_type.name = 'AREA' or def_graph_type.name = 'STACK' or def_graph_type.name = 'LINE1'
				or def_graph_type.name = 'LINE2' or def_graph_type.name = 'LINE3') order by graph_templates_item.sequence_parent DESC");
		
			if (sizeof($groups) == 0) {
				DrawFormItemHiddenIDField("parent","0");
			}else{
				$default_item = $groups[0][id];
				
				DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
					<td width="50%">
						<font class="textEditTitle">Item Group</font><br>
						Choose which graph item this GPRINT is associated with. NOTE: This field
						will be ignored if it is not a GPRINT.
					</td>
					<?DrawFormItemDropdownFromSQL("parent",$groups,"name","id","","",$default_item);?>
				</tr>
				<?
			}
		}else{
			DrawFormItemHiddenIDField("parent","0");
		}
    
		DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Color</font><br>
				The color that is used for this item; not used for COMMENT fields.
			</td>
			<?DrawFormItemColorSelect("v_color_id",$template_item[v_color_id],"None","0");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Graph Item Type</font><br>
				How data for this item is displayed.
			</td>
			<?DrawFormItemDropdownFromSQL("v_graph_type_id",db_fetch_assoc("select id,name from def_graph_type order by name"),"name","id",$template_item[v_graph_type_id],"","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Consolidation Function</font><br>
				How data is to be represented on the graph.
			</td>
			<?DrawFormItemDropdownFromSQL("v_consolidation_function_id",db_fetch_assoc("select id,name from def_cf order by name"),"name","id",$template_item[v_consolidation_function_id],"","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">CDEF Function</font><br>
				A CDEF Function to apply to this item on the graph.
			</td>
			<?DrawFormItemDropdownFromSQL("v_cdef_id",db_fetch_assoc("select id,name from rrd_ds_cdef order by name"),"name","id","None",$template_item[v_cdef_id],"");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Value</font><br>
				For use with VRULE and HRULE, <i>numbers only</i>.
			</td>
			<?DrawFormItemTextBox("v_value",$template_item[v_value],"","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">GPRINT Options</font><br>
				These options only apply to the GPRINT function. Choose an option or enter a custom
				GPRINT string in the textbox (overides checkboxes).
			</td>
			<td>
			<?
				DrawStrippedFormItemRadioButton("v_gprint_opts", $template_item[v_gprint_opts], "1", "Normal","1",true);
				DrawStrippedFormItemRadioButton("v_gprint_opts", $template_item[v_gprint_opts], "2", "Exact Numbers","1",true);
				DrawStrippedFormItemTextBox("v_gprint_custom",$template_item[v_gprint_custom],"","", "40");
			?>
			</td>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Text Format</font><br>
				The text of the comment or legend, input and output keywords are allowed.
			</td>
			<td>
			<?
				DrawStrippedFormItemTextBox("v_text_format",$template_item[v_text_format],"","","40");
				print "<br>";
				DrawStrippedFormItemCheckBox("v_hard_return",$template_item[v_hard_return],"Insert Hard Return","",false);
			?>
			</td>
		</tr>
		
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right" background="images/blue_line.gif">
				<?DrawFormSaveButton("save");?>
			</td>
		</tr>
		<?
		
		DrawFormItemHiddenIDField("graph_template_graph_id",$args[graph_template_graph_id]);
		DrawFormItemHiddenIDField("graph_template_id",$args[graph_template_id]);
		DrawFormItemHiddenIDField("sequence",$template_item[sequence]);
		DrawFormItemHiddenIDField("sequence_parent",$template_item[sequence_parent]);
		DrawFormItemHiddenIDField("_parent",$template_item[parent]);
		DrawFormFooter();
		
		include_once ("include/bottom_table_footer.php");
		include_once ("include/bottom_footer.php");
		
		break;
	case 'item':
		include_once ("include/top_header.php");
		$title_text = "Graph Template Management [edit]";
		include_once ("include/top_table_header.php");
		
		draw_graph_form_select("?action=item&graph_template_id=$args[graph_template_id]");
		
		new_table();
		
		?>
		<tr>
			<td colspan="5" class="textSubHeaderDark" bgcolor="#00438C">Graph Template Item Configuration</td>
			<td class="textHeaderDark" align="right" bgcolor="#00438C"><strong><a class="linkOverDark" href="graph_templates.php?action=item_edit">Add</a>&nbsp;</strong></td>
		</tr>
		<?
		
		DrawMatrixRowBegin();
			DrawMatrixHeaderItem("Graph Item",$colors[panel],$colors[panel_text]);
			DrawMatrixHeaderItem("Task Name",$colors[panel],$colors[panel_text]);
			DrawMatrixHeaderItem("Graph Item Type",$colors[panel],$colors[panel_text]);
			DrawMatrixCustom("<td bgcolor=\"$colors[panel]\" colspan=\"2\"><strong>Item Color</strong></td>");
		DrawMatrixRowEnd();
		
		$template_item_list = db_fetch_assoc("select
			graph_templates_item.id,
			graph_templates_item.v_text_format,
			graph_templates_item.v_value,
			graph_templates_item.v_hard_return,
			polling_items.descrip,
			rrd_ds_cdef.name as cdef_name,
			def_cf.name as v_consolidation_function_name,
			def_colors.hex,
			def_graph_type.name as graph_type_name
			from graph_templates_item left join polling_items on graph_templates_item.v_task_item_id=polling_items.item_id
			left join rrd_ds_cdef on v_cdef_id=rrd_ds_cdef.id
			left join def_cf on v_consolidation_function_id=def_cf.id
			left join def_colors on v_color_id=def_colors.id
			left join def_graph_type on v_graph_type_id=def_graph_type.id
			where graph_templates_item.graph_template_id=$args[graph_template_id]");
		
		foreach ($template_item_list as $item) {
			DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
				?>
				<td class="linkEditMain">
					<a href="graph_templates.php?action=item_edit&graph_template_id_graph=<?print $item[id];?>&graph_template_id=<?print $args[graph_template_id];?>">Item # <?print ($i+1);?></a>
				</td>
				<?
				switch ($item[graph_type_name]) {
				 case 'COMMENT':
				    $matrix_title = "COMMENT: $item[v_text_format]";
				    break;
				 case 'GPRINT':
				    $matrix_title = $item[descrip] . ": " . $item[v_text_format];
				    break;
				 case 'HRULE':
				    $matrix_title = "HRULE: $item[v_value]";
				    break;
				 case 'VRULE':
				    $matrix_title = "VRULE: $item[v_value]";
				    break;
				 default:
				    $matrix_title = $item[descrip];
				    break;
				}
				
				/* use the cdef name (if in use) if all else fails */
				if ($matrix_title == "") {
				    if ($item[cdef_name] != "") {
					$matrix_title .= "CDEF: $item[cdef_name]";
				    }
				}
				
				if ($item[hard_return] == "on") {
				    $matrix_title .= "<strong><font color=\"#FF0000\">&lt;HR&gt;</font></strong>";
				}
				
				if ($item[graph_type_name] != "GPRINT") {
				    if ($item[v_consolidation_function_name] != "AVERAGE") {
					$matrix_title .= " (" . $item[v_consolidation_function_name] . ")";
				    }
				}
				?>
				<td>
					<?print htmlspecialchars($matrix_title);?>
				</td>
				<td>
					<?print $item[graph_type_name];?>
				</td>
				<td<?if ($item[hex] != "") { print ' bgcolor="#' .  $item[hex] . '"'; }?> width="1%">
					&nbsp;
				</td>
				<td>
					<?print $item[hex];?>
				</td>
				<td width="1%" align="right">
					<a href="graph_templates.php?action=item_remove&graph_template_id_graph=<?print $item[id];?>&graph_template_id=<?print $args[graph_template_id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
				</td>
			</tr>
		<?
		$i++;
		}
		
		new_table();
		
		?>
		<tr>
			<td colspan="5" class="textSubHeaderDark" bgcolor="#00438C">Graph Template User Input Configuration</td>
			<td class="textHeaderDark" align="right" bgcolor="#00438C"><strong><a class="linkOverDark" href="graph_templates.php?action=input_edit">Add</a>&nbsp;</strong></td>
		</tr>
		<?
		
		DrawMatrixRowBegin();
			DrawMatrixHeaderItem("Name",$colors[panel],$colors[panel_text]);
		DrawMatrixRowEnd();
		
		$template_item_list = db_fetch_assoc("");
		
		include_once ("include/bottom_table_footer.php");
		include_once ("include/bottom_footer.php");
		
		break;
	case 'template_edit':
		include_once ("include/top_header.php");
		$title_text = "Graph Template Management [edit]";
		include_once ("include/top_table_header.php");
		
		draw_graph_form_select("?action=template_edit&graph_template_id=$args[graph_template_id]");
		
		new_table();
		
		if (isset($args[graph_template_id])) {
			$template = db_fetch_row("select * from graph_templates where id=$args[graph_template_id]");
			$template_graph = db_fetch_row("select * from graph_templates_graph where graph_template_id=$args[graph_template_id]");
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
		
		<?new_table();?>
		
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
		
		DrawFormItemHiddenIDField("graph_template_id",$args[graph_template_id]);
		DrawFormFooter();
		
		include_once ("include/bottom_table_footer.php");
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
					<a class="linkEditMain" href="graph_templates.php?action=template_edit&graph_template_id=<?print $template[id];?>"><?print $template[name];?></a>
				</td>
				<td width="1%" align="right">
					<a href="graph_templates.php?action=template_remove&graph_template_id=<?print $template[id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
				</td>
			</tr>
		<?
		$i++;
		}
		
		include_once ("include/bottom_table_footer.php");
		include_once ("include/bottom_footer.php");
		
		break;
} ?>
