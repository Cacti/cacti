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

	function draw_graph_form_select($main_action) { 
		global $colors, $args; ?>
		<tr bgcolor="<?print $colors[panel];?>">
			<form name="form_graph_id">
			<td colspan="6">
				<table width="100%" cellpadding="0" cellspacing="0">
					<tr>
						<td width="1%">
							<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
								<option value="graph_templates.php?action=template_edit&graph_template_id=<?print $args[graph_template_id];?>"<?if (strstr($args[action],"template")) {?> selected<?}?>>Graph Template Configuration</option>
								<option value="graph_templates.php?action=item&graph_template_id=<?print $args[graph_template_id];?>"<?if ((strstr($args[action],"item")) || (strstr($args[action],"input"))) {?> selected<?}?>>Graph Item Template Configuration</option>
							</select>
						</td>
						<td>
							&nbsp;<a href="graph_templates.php<?print $main_action;?>"><img src="images/button_go.gif" alt="Go" border="0" align="absmiddle"></a><br>
						</td>
					</tr>
				</table>
			</td>
			</form>
		</tr>
	<?}
	
switch ($action) {
	case 'input_save':
		$save["id"] = $form["graph_template_input_id"];
		$save["graph_template_id"] = $form["graph_template_id"];
		$save["name"] = $form["name"];
		$save["description"] = $form["description"];
		$save["column_name"] = $form["column_name"];
		
		$graph_template_input_id = sql_save($save, "graph_template_input");
		
		db_execute("delete from graph_template_input_defs where graph_template_input_id=$graph_template_input_id");
		
		while (list($var, $val) = each($form)) {
			if (eregi("^i_", $var)) {
				$var = str_replace("i_", "", $var);
				
				unset($save);
				$save["graph_template_input_id"] = $graph_template_input_id;
				$save["graph_template_item_id"] = $var;
				
				sql_save($save, "graph_template_input_defs");
				
			}
		}
		
		header ("Location: graph_templates.php?action=item&graph_template_id=$form[graph_template_id]");
		break;
	case 'item_save':
		$save["id"] = $form["graph_template_item_id"];
		$save["graph_template_id"] = $form["graph_template_id"];
		$save["local_graph_id"] = 0;
		$save["task_item_id"] = $form["task_item_id"];
		$save["color_id"] = $form["color_id"];
		$save["graph_type_id"] = $form["graph_type_id"];
		$save["cdef_id"] = $form["cdef_id"];
		$save["consolidation_function_id"] = $form["consolidation_function_id"];
		$save["text_format"] = $form["text_format"];
		$save["value"] = $form["value"];
		$save["hard_return"] = $form["hard_return"];
		$save["gprint_opts"] = $form["gprint_opts"];
		$save["gprint_custom"] = $form["gprint_custom"];
		$save["custom"] = $form["custom"];
		$save["sequence"] = $form["sequence"];
		$save["sequence_parent"] = $form["sequence_parent"];
		$save["parent"] = $form["parent"];
		
		$graph_template_item_id = sql_save($save, "graph_templates_item");
		
		include_once ("include/utility_functions.php");
		update_graph_item_groups($graph_template_item_id, $form[graph_template_item_id], $form[_graph_type_id], $form[_parent]);
		
		header ("Location: graph_templates.php?action=item&graph_template_id=$form[graph_template_id]");
		break;
	case 'template_save':
		if ($form[upper_limit] == "") { $form[upper_limit] = 0; }
		if ($form[lower_limit] == "") { $form[lower_limit] = 0; }
		if ($form[unit_exponent_value] == "") { $form[unit_exponent_value] = 0; }
		
		$save["id"] = $form["graph_template_id"];
		$save["name"] = $form["name"];
		
		$graph_template_id = sql_save($save, "graph_templates");
		unset ($save);
		
		$save["id"] = $form["graph_template_graph_id"];
		$save["local_graph_template_graph_id"] = 0;
		$save["local_graph_id"] = 0;
		$save["graph_template_id"] = $graph_template_id;
		$save["t_image_format_id"] = $form["t_image_format_id"];
		$save["image_format_id"] = $form["image_format_id"];
		$save["t_title"] = $form["t_title"];
		$save["title"] = $form["title"];
		$save["t_height"] = $form["t_height"];
		$save["height"] = $form["height"];
		$save["t_width"] = $form["t_width"];
		$save["width"] = $form["width"];
		$save["t_upper_limit"] = $form["t_upper_limit"];
		$save["upper_limit"] = $form["upper_limit"];
		$save["t_lower_limit"] = $form["t_lower_limit"];
		$save["lower_limit"] = $form["lower_limit"];
		$save["t_vertical_label"] = $form["t_vertical_label"];
		$save["vertical_label"] = $form["vertical_label"];
		$save["t_auto_scale"] = $form["t_auto_scale"];
		$save["auto_scale"] = $form["auto_scale"];
		$save["t_auto_scale_opts"] = $form["t_auto_scale_opts"];
		$save["auto_scale_opts"] = $form["auto_scale_opts"];
		$save["t_auto_scale_log"] = $form["t_auto_scale_log"];
		$save["auto_scale_log"] = $form["auto_scale_log"];
		$save["t_auto_scale_rigid"] = $form["t_auto_scale_rigid"];
		$save["auto_scale_rigid"] = $form["auto_scale_rigid"];
		$save["t_auto_padding"] = $form["t_auto_padding"];
		$save["auto_padding"] = $form["auto_padding"];
		$save["t_base_value"] = $form["t_base_value"];
		$save["base_value"] = $form["base_value"];
		$save["t_grouping"] = $form["t_grouping"];
		$save["grouping"] = $form["grouping"];
		$save["t_export"] = $form["t_export"];
		$save["export"] = $form["export"];
		$save["t_unit_value"] = $form["t_unit_value"];
		$save["unit_value"] = $form["unit_value"];
		$save["t_unit_exponent_value"] = $form["t_unit_exponent_value"];
		$save["unit_exponent_value"] = $form["unit_exponent_value"];
		
		sql_save($save, "graph_templates_graph");
		
		header ("Location: graph_templates.php");
		break;
	case 'input_edit':
		include_once ("include/top_header.php");
		$title_text = "Graph Template Management [edit]";
		include_once ("include/top_table_header.php");
		
		draw_graph_form_select("?action=item&graph_template_id=$args[graph_template_id]");
		
		new_table();
		
		$graph_template_items[short_names] = array(
			"color_id",
			"task_item_id",
			"graph_type_id",
			"cdef_id",
			"consolidation_function_id",
			"text_format",
			"value",
			"hard_return",
			"gprint",
			"custom");
		$graph_template_items[long_names] = array(
			"Item Color",
			"Item Task (Data Source)",
			"Graph Item Type",
			"CDEF",
			"Consolidation Function",
			"Text Format",
			"Value",
			"Hard Return",
			"GPRINT Options",
			"Custom Field");
			
		if (isset($args[graph_template_input_id])) {
			$graph_template_input = db_fetch_row("select * from graph_template_input where id=$args[graph_template_input_id]");
		}else{
			unset($graph_template_input);
		}
		
		?>
		<tr>
			<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Graph Item Input Configuration</td>
		</tr>
		
		<form method="post" action="graph_templates.php">
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Name</font><br>
				Enter a name for this graph item input, make sure it is something you recognize.
			</td>
			<?DrawFormItemTextBox("name",$graph_template_input[name],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Description</font><br>
				Enter a description for this graph item input to describe what this input is used for.
			</td>
			<?DrawFormItemTextArea("description",$graph_template_input[description],5,40,"");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Field Type</font><br>
				How data is to be represented on the graph.
			</td>
			<?DrawFormItemDropdownFromSQL("column_name",$graph_template_items,"long_names","short_names",$graph_template_input[column_name],"","");?>
		</tr>
		
		<?
		if (!(isset($args[graph_template_input_id]))) { $args[graph_template_input_id] = 0; }
		
		$item_list = db_fetch_assoc("select
			def_graph_type.name as graph_type_name,
			def_cf.name as consolidation_function_name,
			polling_items.descrip,
			graph_templates_item.text_format,
			graph_templates_item.id as graph_templates_item_id,
			graph_template_input_defs.graph_template_input_id
			from graph_templates_item left join graph_template_input_defs on (graph_template_input_defs.graph_template_item_id=graph_templates_item.id and graph_template_input_defs.graph_template_input_id=$args[graph_template_input_id])
			left join def_cf on graph_templates_item.consolidation_function_id=def_cf.id
			left join def_graph_type on graph_templates_item.graph_type_id=def_graph_type.id
			left join polling_items on graph_templates_item.task_item_id=polling_items.item_id
			where graph_templates_item.local_graph_id=0
			and graph_templates_item.graph_template_id=$args[graph_template_id]
			order by graph_templates_item.sequence_parent,graph_templates_item.sequence");
		
		DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Associated Graph Items</font><br>
				Select the graph items that you want to accept user input for.
			</td>
			<td>
			<?if (sizeof($item_list) > 0) {
				foreach ($item_list as $item) {
				    if ($item[graph_template_input_id] == "") {
					$old_value = "";
				    }else{
					$old_value = "on";
				    }
				    
				    if ($item[graph_type_name] == "GPRINT") {
					    $start_bold = "";
					    $end_bold = "";
				    }else{
					    $start_bold = "<strong>";
					    $end_bold = "</strong>";
				    }
				    
				    $name = "$start_bold Item #" . ($i+1) . ": $item[graph_type_name] ($item[consolidation_function_name])$end_bold";
				    DrawStrippedFormItemCheckBox("i_" . $item[graph_templates_item_id], $old_value, $name,"",true);
				    
				    $i++;
				}
			}
			?>
			</td>
		</tr>
		
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right" background="images/blue_line.gif">
				<?DrawFormSaveButton("input_save");?>
			</td>
		</tr>
		<?
		
		DrawFormItemHiddenIDField("graph_template_id",$args[graph_template_id]);
		DrawFormItemHiddenIDField("graph_template_input_id",$args[graph_template_input_id]);
		
		include_once ("include/bottom_table_footer.php");
		include_once ("include/bottom_footer.php");
		
		break;
	case 'item_edit':
		include_once ("include/top_header.php");
		$title_text = "Graph Template Management [edit]";
		include_once ("include/top_table_header.php");
		
		draw_graph_form_select("?action=item&graph_template_id=$args[graph_template_id]");
		
		new_table();
		
		if (isset($args[graph_template_item_id])) {
			$template_item = db_fetch_row("select * from graph_templates_item where id=$args[graph_template_item_id]");
		}else{
			unset($template_item);
		}
		
		/* get current graph name for the header text */
		$graph_parameters = db_fetch_row("select title,grouping from graph_templates_graph where id=$args[graph_template_id]");
		
		?>
		<tr>
			<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Template Item Configuration</td>
		</tr>
		
		<form method="post" action="graph_templates.php">
		
		<?
		/* by default, select the LAST DS chosen to make everyone's lives easier */
		$default = db_fetch_row("select task_item_id from graph_templates_item where graph_template_id=$args[graph_template_id] order by sequence_parent DESC,sequence DESC");
    
		if (sizeof($default) > 0) {
			$default_item = $default[task_item_id];
		}else{
			$default_item = 0;
		}
		
		DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Task Item</font><br>
				The task to use for this graph item; not used for COMMENT fields.
			</td>
			<?DrawFormItemDropdownFromSQL("task_item_id",db_fetch_assoc("select item_id,descrip from polling_items order by descrip"),"descrip","item_id",$template_item[task_item_id],"None",$default_item);?>
		</tr>
		
		<?
		if ($graph_parameters[grouping] == "on") {
			/* default item (last item) */
			$groups = db_fetch_assoc("select 
				CONCAT_WS('',def_graph_type.name,' (',def_cf.name,'): ',polling_items.descrip,' - \"',graph_templates_item.text_format,'\"') as name,
				graph_templates_item.id
				from graph_templates_item left join def_graph_type on graph_templates_item.graph_type_id=def_graph_type.id
				left join polling_items on graph_templates_item.task_item_id=polling_items.item_id
				left join def_cf on graph_templates_item.consolidation_function_id=def_cf.id
				where graph_templates_item.graph_template_id=$args[graph_template_id]
				and (def_graph_type.name = 'AREA' or def_graph_type.name = 'STACK' or def_graph_type.name = 'LINE1'
				or def_graph_type.name = 'LINE2' or def_graph_type.name = 'LINE3') order by graph_templates_item.sequence_parent");
			
			if (sizeof($groups) == 0) {
				DrawFormItemHiddenIDField("parent","0");
			}else{
				if (!(isset($args[graph_template_id_graph]))) {
					$rows = (sizeof($groups) - 1);
					$default_item = $groups[$rows][id];
				}
				
				DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
					<td width="50%">
						<font class="textEditTitle">Item Group</font><br>
						Choose which graph item this GPRINT is associated with. NOTE: This field
						will be ignored if it is not a GPRINT.
					</td>
					<?DrawFormItemDropdownFromSQL("parent",$groups,"name","id",$template_item[parent],"",$default_item);?>
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
			<?DrawFormItemColorSelect("color_id",$template_item[color_id],"None","0");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Graph Item Type</font><br>
				How data for this item is displayed.
			</td>
			<?DrawFormItemDropdownFromSQL("graph_type_id",db_fetch_assoc("select id,name from def_graph_type order by name"),"name","id",$template_item[graph_type_id],"","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Consolidation Function</font><br>
				How data is to be represented on the graph.
			</td>
			<?DrawFormItemDropdownFromSQL("consolidation_function_id",db_fetch_assoc("select id,name from def_cf order by name"),"name","id",$template_item[consolidation_function_id],"","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">CDEF Function</font><br>
				A CDEF Function to apply to this item on the graph.
			</td>
			<?DrawFormItemDropdownFromSQL("cdef_id",db_fetch_assoc("select id,name from rrd_ds_cdef order by name"),"name","id",$template_item[cdef_id],"None","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Value</font><br>
				For use with VRULE and HRULE, <i>numbers only</i>.
			</td>
			<?DrawFormItemTextBox("value",$template_item[value],"","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">GPRINT Options</font><br>
				These options only apply to the GPRINT function. Choose an option or enter a custom
				GPRINT string in the textbox (overides checkboxes).
			</td>
			<td>
			<?
				DrawStrippedFormItemRadioButton("gprint_opts", $template_item[gprint_opts], "1", "Normal","1",true);
				DrawStrippedFormItemRadioButton("gprint_opts", $template_item[gprint_opts], "2", "Exact Numbers","1",true);
				DrawStrippedFormItemTextBox("gprint_custom",$template_item[gprint_custom],"","", "40");
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
				DrawStrippedFormItemTextBox("text_format",$template_item[text_format],"","","40");
				print "<br>";
				DrawStrippedFormItemCheckBox("hard_return",$template_item[hard_return],"Insert Hard Return","",false);
			?>
			</td>
		</tr>
		
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right" background="images/blue_line.gif">
				<?DrawFormSaveButton("item_save");?>
			</td>
		</tr>
		<?
		
		DrawFormItemHiddenIDField("graph_template_item_id",$args[graph_template_item_id]);
		DrawFormItemHiddenIDField("graph_template_id",$args[graph_template_id]);
		DrawFormItemHiddenIDField("sequence",$template_item[sequence]);
		DrawFormItemHiddenIDField("sequence_parent",$template_item[sequence_parent]);
		DrawFormItemHiddenIDField("_parent",$template_item[parent]);
		DrawFormItemHiddenIDField("_graph_type_id",$template_item[graph_type_id]);
		
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
			<td colspan="6" class="textSubHeaderDark" bgcolor="#00438C">Graph Template Item Configuration</td>
			<td class="textHeaderDark" align="right" bgcolor="#00438C"><strong><a class="linkOverDark" href="graph_templates.php?action=item_edit&graph_template_id=<?print $args[graph_template_id];?>">Add</a>&nbsp;</strong></td>
		</tr>
		<?
		
		print "<tr bgcolor='#$colors[header_panel]'>";
			DrawMatrixHeaderItem("Graph Item",$colors[header_text],1);
			DrawMatrixHeaderItem("Task Name",$colors[header_text],1);
			DrawMatrixHeaderItem("Graph Item Type",$colors[header_text],1);
			DrawMatrixHeaderItem("CF Type",$colors[header_text],1);
			DrawMatrixHeaderItem("Item Color",$colors[header_text],3);
		print "</tr>";
		
		$allow_grouping = db_fetch_cell("select grouping from graph_templates_graph where graph_template_id=$args[graph_template_id]");
		
		$template_item_list = db_fetch_assoc("select
			graph_templates_item.id,
			graph_templates_item.text_format,
			graph_templates_item.value,
			graph_templates_item.hard_return,
			polling_items.descrip,
			rrd_ds_cdef.name as cdef_name,
			def_cf.name as consolidation_function_name,
			def_colors.hex,
			def_graph_type.name as graph_type_name
			from graph_templates_item left join polling_items on graph_templates_item.task_item_id=polling_items.item_id
			left join rrd_ds_cdef on cdef_id=rrd_ds_cdef.id
			left join def_cf on consolidation_function_id=def_cf.id
			left join def_colors on color_id=def_colors.id
			left join def_graph_type on graph_type_id=def_graph_type.id
			where graph_templates_item.graph_template_id=$args[graph_template_id]
			and graph_templates_item.local_graph_id=0
			order by graph_templates_item.sequence_parent,graph_templates_item.sequence");
		
		$group_counter = 0;
		if (sizeof($template_item_list) > 0) {
		foreach ($template_item_list as $item) {
			//if ($allow_grouping == "on") {
				$bold_this_row = false; $use_custom_row_color = false;
				
				if (($item[graph_type_name] != "GPRINT") && ($item[graph_type_name] != $_graph_type_name)) {
					$bold_this_row = true; $use_custom_row_color = true;
					
					if ($group_counter % 2 == 0) {
						$alternate_color_1 = "EEEEEE";
						$alternate_color_2 = "EEEEEE";
						$custom_row_color = "D5D5D5";
					}else{
						$alternate_color_1 = $colors[alternate];
						$alternate_color_2 = $colors[alternate];
						$custom_row_color = "D2D6E7";
					}
					
					$group_counter++;
				}
				
				$_graph_type_name = $item[graph_type_name];
				
			//}
			
			if ($use_custom_row_color == false) { DrawMatrixRowAlternateColorBegin($alternate_color_1,$alternate_color_2,$i); }else{ print "<tr bgcolor=\"#$custom_row_color\">"; }			?>
				<td class="linkEditMain">
					<a href="graph_templates.php?action=item_edit&graph_template_item_id=<?print $item[id];?>&graph_template_id=<?print $args[graph_template_id];?>">Item # <?print ($i+1);?></a>
				</td>
				<?
				if ($item[descrip] == "") { $item[descrip] = "(No Task)"; }
				
				switch ($item[graph_type_name]) {
				 case 'AREA':
				    $matrix_title = $item[descrip] . ": " . $item[text_format];
				    break;
				 case 'STACK':
				    $matrix_title = $item[descrip] . ": " . $item[text_format];
				    break;
				 case 'COMMENT':
				    $matrix_title = "COMMENT: $item[text_format]";
				    break;
				 case 'GPRINT':
				    $matrix_title = $item[descrip] . ": " . $item[text_format];
				    break;
				 case 'HRULE':
				    $matrix_title = "HRULE: $item[value]";
				    break;
				 case 'VRULE':
				    $matrix_title = "VRULE: $item[value]";
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
				
				$hard_return = "";
				if ($item[hard_return] == "on") {
				    $hard_return = "<strong><font color=\"#FF0000\">&lt;HR&gt;</font></strong>";
				}
				
				?>
				<td>
					<?if ($bold_this_row == true) { print "<strong>"; }?><?print htmlspecialchars($matrix_title) . $hard_return;?><?if ($bold_this_row == true) { print "</strong>"; }?>
				</td>
				<td>
					<?if ($bold_this_row == true) { print "<strong>"; }?><?print $item[graph_type_name];?><?if ($bold_this_row == true) { print "</strong>"; }?>
				</td>
				<td>
					<?if ($bold_this_row == true) { print "<strong>"; }?><?print $item[consolidation_function_name];?><?if ($bold_this_row == true) { print "</strong>"; }?>
				</td>
				<td<?if ($item[hex] != "") { print ' bgcolor="#' .  $item[hex] . '"'; }?> width="1%">
					&nbsp;
				</td>
				<td>
					<?if ($bold_this_row == true) { print "<strong>"; }?><?print $item[hex];?><?if ($bold_this_row == true) { print "</strong>"; }?>
				</td>
				<td width="1%" align="right">
					<a href="graph_templates.php?action=item_remove&graph_template_id_graph=<?print $item[id];?>&graph_template_id=<?print $args[graph_template_id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
				</td>
			</tr>
		<?
		$i++;
		}
		}
		
		new_table();
		
		?>
		<tr>
			<td colspan="1" class="textSubHeaderDark" bgcolor="#00438C">Graph Template User Input Configuration</td>
			<td class="textHeaderDark" align="right" bgcolor="#00438C"><strong><a class="linkOverDark" href="graph_templates.php?action=input_edit&graph_template_id=<?print $args[graph_template_id];?>">Add</a>&nbsp;</strong></td>
		</tr>
		<?
		
		print "<tr bgcolor='#$colors[header_panel]'>";
			DrawMatrixHeaderItem("Name",$colors[header_text],2);
		print "</tr>";
		
		$template_item_list = db_fetch_assoc("select id,name from graph_template_input where graph_template_id=$args[graph_template_id] order by name");
		
		if (sizeof($template_item_list) > 0) {
		foreach ($template_item_list as $item) {
			DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
		?>
				<td class="linkEditMain">
					<a href="graph_templates.php?action=input_edit&graph_template_input_id=<?print $item[id];?>&graph_template_id=<?print $args[graph_template_id];?>"><?print $item[name];?></a>
				</td>
				<td width="1%" align="right">
					<a href="graph_templates.php?action=item_remove&graph_template_id_graph=<?print $item[id];?>&graph_template_id=<?print $args[graph_template_id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
				</td>
			</tr>
		<?
		$i++;
		}
		}
		
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
		
		<form method="post" action="graph_templates.php">
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Name</font><br>
				The name given to this graph template.
			</td>
			<?DrawFormItemTextBox("name",$template[name],"","50", "40");?>
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
			<?DrawFormItemTextBox("title",$template_graph[title],"","255","40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Image Format</font><br>
				<?DrawStrippedFormItemCheckBox("t_image_format_id",$template_graph[t_image_format_id],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemDropdownFromSQL("image_format_id",db_fetch_assoc("select * from def_image_type order by Name"),"Name","ID",$template_graph[image_format_id],"","1");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Height</font><br>
				<?DrawStrippedFormItemCheckBox("t_height",$template_graph[t_height],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("height",$template_graph[height],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Width</font><br>
				<?DrawStrippedFormItemCheckBox("t_width",$template_graph[t_width],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("width",$template_graph[width],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Auto Scale</font><br>
				<?DrawStrippedFormItemCheckBox("t_auto_scale",$template_graph[t_auto_scale],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemCheckBox("auto_scale",$template_graph[auto_scale],"Auto Scale","on");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Auto Scale Options</font><br>
				<?DrawStrippedFormItemCheckBox("t_auto_scale_opts",$template_graph[t_auto_scale_opts],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<td>
			<?
				DrawStrippedFormItemRadioButton("auto_scale_opts", $template_graph[auto_scale_opts], "1", "Use --alt-autoscale","2",true);
				DrawStrippedFormItemRadioButton("auto_scale_opts", $template_graph[auto_scale_opts], "2", "Use --alt-autoscale-max","2",true);
			?>
			</td>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Logarithmic Auto Scaling</font><br>
				<?DrawStrippedFormItemCheckBox("t_auto_scale_log",$template_graph[t_auto_scale_log],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemCheckBox("auto_scale_log",$template_graph[auto_scale_log],"Logarithmic Auto Scaling (--logarithmic)","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Rigid Boundaries Mode</font><br>
				<?DrawStrippedFormItemCheckBox("t_auto_scale_rigid",$template_graph[t_auto_scale_rigid],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemCheckBox("auto_scale_rigid",$template_graph[auto_scale_rigid],"Use Rigid Boundaries Mode (--rigid)","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Auto Padding</font><br>
				<?DrawStrippedFormItemCheckBox("t_auto_padding",$template_graph[t_auto_padding],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemCheckBox("auto_padding",$template_graph[auto_padding],"Auto Padding","");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Allow Grouping</font><br>
				<?DrawStrippedFormItemCheckBox("t_grouping",$template_graph[t_grouping],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemCheckBox("grouping",$template_graph[grouping],"Allow Grouping","on");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Allow Graph Export</font><br>
				<?DrawStrippedFormItemCheckBox("t_export",$template_graph[t_export],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemCheckBox("export",$template_graph[export],"Allow Graph Export","on");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Upper Limit</font><br>
				<?DrawStrippedFormItemCheckBox("t_upper_limit",$template_graph[t_upper_limit],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("upper_limit",$template_graph[upper_limit],"0","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Lower Limit</font><br>
				<?DrawStrippedFormItemCheckBox("t_lower_limit",$template_graph[t_lower_limit],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("lower_limit",$template_graph[lower_limit],"0","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Base Value</font><br>
				<?DrawStrippedFormItemCheckBox("t_base_value",$template_graph[t_base_value],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("base_value",$template_graph[base_value],"1000","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Unit Value</font><br>
				<?DrawStrippedFormItemCheckBox("t_unit_value",$template_graph[t_unit_value],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("unit_value",$template_graph[unit_value],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
			<td width="50%">
				<font class="textEditTitle">Unit Exponent Value</font><br>
				<?DrawStrippedFormItemCheckBox("t_unit_exponent_value",$template_graph[t_unit_exponent_value],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("unit_exponent_value",$template_graph[unit_exponent_value],"","50", "40");?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Vertical Label</font><br>
				<?DrawStrippedFormItemCheckBox("t_vertical_label",$template_graph[t_vertical_label],"Use Per-Graph Value (Ignore this Value)","",false);?>
			</td>
			<?DrawFormItemTextBox("vertical_label",$template_graph[vertical_label],"","200","40");?>
		</tr>
		
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right" background="images/blue_line.gif">
				<?DrawFormSaveButton("template_save");?>
			</td>
		</tr>
		<?
		
		DrawFormItemHiddenIDField("graph_template_id",$args[graph_template_id]);
		DrawFormItemHiddenIDField("graph_template_graph_id",$template_graph[id]);
		
		include_once ("include/bottom_table_footer.php");
		include_once ("include/bottom_footer.php");
		
		break;
	default:
		include_once ("include/top_header.php");
		$title_text = "Graph Template Management"; $add_text = "graph_templates.php?action=template_edit";
		include_once ("include/top_table_header.php");
		
		print "<tr bgcolor='#$colors[header_panel]'>";
			DrawMatrixHeaderItem("Template Title",$colors[header_text],2);
		print "</tr>";
		
		$template_list = db_fetch_assoc("select id,name from graph_templates order by name");
                if (sizeof($template_list) > 0) {
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
		}
		include_once ("include/bottom_table_footer.php");
		include_once ("include/bottom_footer.php");
		
		break;
} ?>
