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
	
	function draw_main_form_select() { 
		global $colors, $args;?>
		<tr>
			<td valign="middle">
				<table cellspacing="0" cellpadding="0" border="0">
					<tr>
						<td bgcolor="#<?print $colors[panel];?>">
							<a href="graphs.php"><img src="images/button_graph_management_<?if (($args[action]=="") || (strstr($args[action],"graph"))) { print "down.gif"; }else{ print "up.gif"; }?>" border="0" alt="Graph Management" align="absmiddle"></a>
						</td>
						<td>
							<a href="graphs.php?action=tree"><img src="images/button_graph_trees_<?if (strstr($args[action],"tree")){ print "down.gif"; }else{ print "up.gif"; }?>" border="0" alt="Graph Management" align="absmiddle"></a>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	<?}
	
	function draw_graph_form_select($main_action) { 
		global $colors, $args; ?>
		<tr bgcolor="#<?print $colors[panel];?>">
			<form name="form_graph_id">
			<td>
				<table width="100%" cellpadding="0" cellspacing="0">
					<tr>
						<td width="1%">
							<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
								<option value="graphs.php?action=graph_edit&local_graph_id=<?print $args[local_graph_id];?>"<?if (($args[action]=="") || (strstr($args[action],"graph"))) {?> selected<?}?>>Graph Configuration</option>
								<option value="graphs.php?action=item&local_graph_id=<?print $args[local_graph_id];?>"<?if (strstr($args[action],"item")){?> selected<?}?>>Custom Graph Item Configuration</option>
							</select>
						</td>
						<td>
							&nbsp;<a href="graphs.php<?print $main_action;?>"><img src="images/button_go.gif" alt="Go" border="0" align="absmiddle"></a><br>
						</td>
					</tr>
				</table>
			</td>
			</form>
		</tr>
	<?}
	
switch ($action) {
	case 'item_remove':
		db_execute("delete from graph_templates_item where id=$args[graph_template_item_id]");
		
		header ("Location: graphs.php?action=item&local_graph_id=$args[local_graph_id]");
		break;
	case 'item_save':
		$save["id"] = $form["graph_template_item_id"];
		$save["graph_template_id"] = $form["graph_template_id"];
		$save["local_graph_id"] = $form["local_graph_id"];
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
		
		header ("Location: graphs.php?action=item&local_graph_id=$form[local_graph_id]");
		break;
	case 'item_edit':
		include_once ("include/top_header.php");
		$title_text = "Graph Template Management [edit]";
		include_once ("include/top_table_header.php");
		
		draw_graph_form_select("?action=item&local_graph_id=$args[local_graph_id]");
		
		new_table();
		
		if (isset($args[graph_template_item_id])) {
			$template_item = db_fetch_row("select * from graph_templates_item where id=$args[graph_template_item_id]");
		}else{
			unset($template_item);
		}
		
		?>
		<tr>
			<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Template Item Configuration</td>
		</tr>
		
		<form method="post" action="graphs.php">
		
		<?
		/* by default, select the LAST DS chosen to make everyone's lives easier */
		$default = db_fetch_row("select task_item_id from graph_templates_item where local_graph_id=$args[local_graph_id] order by sequence_parent DESC,sequence DESC");
    
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
		/* default item (last item) */
		$groups = db_fetch_assoc("select 
			CONCAT_WS('',def_graph_type.name,' (',def_cf.name,'): ',polling_items.descrip,' - \"',graph_templates_item.text_format,'\"') as name,
			graph_templates_item.id
			from graph_templates_item left join def_graph_type on graph_templates_item.graph_type_id=def_graph_type.id
			left join polling_items on graph_templates_item.task_item_id=polling_items.item_id
			left join def_cf on graph_templates_item.consolidation_function_id=def_cf.id
			where graph_templates_item.local_graph_id=$args[local_graph_id]
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
		
		DrawFormItemHiddenIDField("local_graph_id",$args[local_graph_id]);
		DrawFormItemHiddenIDField("graph_template_item_id",$args[graph_template_item_id]);
		DrawFormItemHiddenIDField("graph_template_id",$template_item[graph_template_id]);
		DrawFormItemHiddenIDField("sequence",$template_item[sequence]);
		DrawFormItemHiddenIDField("sequence_parent",$template_item[sequence_parent]);
		DrawFormItemHiddenIDField("_parent",$template_item[parent]);
		DrawFormItemHiddenIDField("_graph_type_id",$template_item[graph_type_id]);
		
		include_once ("include/bottom_table_footer.php");
		include_once ("include/bottom_footer.php");
		
		break;
	case 'input_save':
		/* first; get the current graph template id */
		$graph_template_id = db_fetch_cell("select graph_template_id from graph_local where id=$form[local_graph_id]");
		
		/* get all inputs that go along with this graph template */
		$input_list = db_fetch_assoc("select id,column_name from graph_template_input where graph_template_id=$graph_template_id");
		
		if (sizeof($input_list) > 0) {
		foreach ($input_list as $input) {
			/* we need to find out which graph items will be affected by saving this particular item */
			$item_list = db_fetch_assoc("select
				graph_templates_item.id
				from graph_template_input_defs,graph_templates_item
				where graph_template_input_defs.graph_template_item_id=graph_templates_item.local_graph_template_item_id
				and graph_templates_item.local_graph_id=$form[local_graph_id]
				and graph_template_input_defs.graph_template_input_id=$input[id]");
			
			/* get some variables */
			$column_name = $input[column_name];
			$graph_template_input_id = $input[id];
			$column_value = $form[$graph_template_input_id];
			
			/* loop through each item affected and update column data */
			if (sizeof($item_list) > 0) {
			foreach ($item_list as $item) {
				db_execute("update graph_templates_item set $column_name=$column_value where id=$item[id]");
			}
			}
		}
		}
		
		header ("Location: graphs.php?action=item&local_graph_id=$form[local_graph_id]");
		break;
	case 'graph_duplicate':
		include_once ('include/utility_functions.php');
		
		DuplicateGraph($id);
		
		header ("Location: graphs.php");
		break;
	case 'item':
		include_once ("include/top_header.php");
		$title_text = "Graph Template Management [edit]";
		include_once ("include/top_table_header.php");
		
		draw_graph_form_select("?action=item&local_graph_id=$args[local_graph_id]");
		
		new_table();
		
		$graph_template_id = db_fetch_cell("select graph_template_id from graph_local where id=$args[local_graph_id]");
		$graph_template_name = db_fetch_cell("select  name from graph_templates where id=$graph_template_id");
		
		?>
		<tr>
			<td colspan="6" class="textSubHeaderDark" bgcolor="#00438C">Graph Item Configuration<?if ($graph_template_id != 0) { print " <strong>[Template: $graph_template_name]</strong>"; }?></td>
			<td class="textHeaderDark" align="right" bgcolor="#00438C"><?if ($graph_template_id == "0") {?><strong><a class="linkOverDark" href="graphs.php?action=item_edit&local_graph_id=<?print $args[local_graph_id];?>">Add</a>&nbsp;</strong><?}?></td>
		</tr>
		<?
		
		print "<tr bgcolor='#$colors[header_panel]'>";
			DrawMatrixHeaderItem("Graph Item",$colors[header_text],1);
			DrawMatrixHeaderItem("Task Name",$colors[header_text],1);
			DrawMatrixHeaderItem("Graph Item Type",$colors[header_text],1);
			DrawMatrixHeaderItem("CF Type",$colors[header_text],1);
			DrawMatrixHeaderItem("Item Color",$colors[header_text],3);
		print "</tr>";
		
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
			where graph_templates_item.local_graph_id=$args[local_graph_id]
			order by graph_templates_item.sequence_parent,graph_templates_item.sequence");
		
		$group_counter = 0;
		if (sizeof($template_item_list) > 0) {
		foreach ($template_item_list as $item) {
			/* graph grouping display logic */
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
			
			if ($use_custom_row_color == false) { DrawMatrixRowAlternateColorBegin($alternate_color_1,$alternate_color_2,$i); }else{ print "<tr bgcolor=\"#$custom_row_color\">"; }			?>
				<td class="linkEditMain">
					<?if ($graph_template_id == "0") {?><a href="graphs.php?action=item_edit&graph_template_item_id=<?print $item[id];?>&local_graph_id=<?print $args[local_graph_id];?>"><?}?>Item # <?print ($i+1);?><?if ($graph_template_id == "0") {?></a><?}?>
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
					<?if ($graph_template_id == "0") {?><a href="graphs.php?action=item_remove&graph_template_item_id=<?print $item[id];?>&local_graph_id=<?print $args[local_graph_id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;<?}?>
				</td>
			</tr>
		<?
		$i++;
		}
		}
		
		/* only display the "inputs" area if we are using a graph template for this graph */
		if ($graph_template_id != "0") {
			new_table();
			
			?>
			<tr>
				<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Graph Item Inputs</td>
			</tr>
			
			<form method="post" action="graphs.php">
			<?
			
			$input_item_list = db_fetch_assoc("select * from graph_template_input where graph_template_id=$graph_template_id order by name");
			
			if (sizeof($input_item_list) > 0) {
			foreach ($input_item_list as $item) {
				$current_def_value = db_fetch_row("select 
					graph_templates_item.$item[column_name],
					graph_templates_item.id
					from graph_templates_item,graph_template_input_defs 
					where graph_template_input_defs.graph_template_item_id=graph_templates_item.local_graph_template_item_id 
					and graph_template_input_defs.graph_template_input_id=$item[id]
					and graph_templates_item.local_graph_id=$args[local_graph_id]
					limit 0,1");
				
				$column_name = $item[column_name];
				
				DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
					<td width="50%">
						<font class="textEditTitle"><?print $item[name];?></font>
						<?if ($item[description] != "") { print "<br>$item[description]"; }?>
					</td>
					<?
					switch ($item[column_name]) {
					case 'task_item_id':
						DrawFormItemDropdownFromSQL("$item[id]",db_fetch_assoc("select item_id,descrip from polling_items order by descrip"),"descrip","item_id",$current_def_value[$column_name],"None","");
						break;
					case 'color_id':
						DrawFormItemColorSelect("$item[id]",$current_def_value[$column_name],"None","0");
						break;
					case 'graph_type_id':
						DrawFormItemDropdownFromSQL("$item[id]",db_fetch_assoc("select id,name from def_graph_type order by name"),"name","id",$current_def_value[$column_name],"","");
						break;
					case 'consolidation_function_id':
						DrawFormItemDropdownFromSQL("$item[id]",db_fetch_assoc("select id,name from def_cf order by name"),"name","id",$current_def_value[$column_name],"","");
						break;
					case 'cdef_id':
						DrawFormItemDropdownFromSQL("$item[id]",db_fetch_assoc("select id,name from rrd_ds_cdef order by name"),"name","id","None",$current_def_value[$column_name],"");
						break;
					case 'value':
						DrawFormItemTextBox("$item[id]",$current_def_value[$column_name],"","");
						break;
					case 'gprint_opts':
						print "<td>";
						DrawStrippedFormItemRadioButton("$item[id]", $current_def_value[$column_name], "1", "Normal","1",true);
						DrawStrippedFormItemRadioButton("$item[id]", $current_def_value[$column_name], "2", "Exact Numbers","1",true);
						//DrawStrippedFormItemTextBox("gprint_custom",$template_item[gprint_custom],"","", "40");
						print "</td>";
						break;
					case 'text_format':
						DrawFormItemTextBox("$item[id]",$current_def_value[$column_name],"","","40");
						break;
					case 'hard_return':
						DrawFormItemCheckBox("$item[id]",$current_def_value[$column_name],"Insert Hard Return","");
						break;
					}
					
					?>
				</tr>
			<?}?>
			<tr bgcolor="#FFFFFF">
				 <td colspan="2" align="right" background="images/blue_line.gif">
					<?DrawFormSaveButton("input_save");?>
				</td>
			</tr>
			<?
			DrawFormItemHiddenIDField("local_graph_id",$args[local_graph_id]);
			}
		}
		
		include_once ("include/bottom_table_footer.php");
		include_once ("include/bottom_footer.php");
		
		break;
	case 'graph_save':
		if ($form[lower_limit] == "") { $form[lower_limit] = 0; }
		if ($form[upper_limit] == "") { $form[upper_limit] = 0; }
		if ($form[unit_exponent_value] == "") { $form[unit_exponent_value] = 0; }
		
		$save["id"] = $form["local_graph_id"];
		$save["graph_template_id"] = $form["graph_template_id"];
		
		$local_graph_id = sql_save($save, "graph_local");
		unset($save);
		
		$save["id"] = $form["graph_template_graph_id"];
		$save["local_graph_template_graph_id"] = $form["local_graph_template_graph_id"];
		$save["local_graph_id"] = $local_graph_id;
		$save["graph_template_id"] = $form["graph_template_id"];
		$save["order_key"] = $form["order_key"];
		$save["image_format_id"] = $form["image_format_id"];
		$save["title"] = $form["title"];
		$save["height"] = $form["height"];
		$save["width"] = $form["width"];
		$save["upper_limit"] = $form["upper_limit"];
		$save["lower_limit"] = $form["lower_limit"];
		$save["vertical_label"] = $form["vertical_label"];
		$save["auto_scale"] = $form["auto_scale"];
		$save["auto_scale_opts"] = $form["auto_scale_opts"];
		$save["auto_scale_log"] = $form["auto_scale_log"];
		$save["auto_scale_rigid"] = $form["auto_scale_rigid"];
		$save["auto_padding"] = $form["auto_padding"];
		$save["base_value"] = $form["base_value"];
		$save["grouping"] = $form["grouping"];
		$save["export"] = $form["export"];
		$save["unit_value"] = $form["unit_value"];
		$save["unit_exponent_value"] = $form["unit_exponent_value"];
		
		sql_save($save, "graph_templates_graph");
		
		if ($form[graph_template_id] == $form[_graph_template_id]) {
			header ("Location: graphs.php");
		}else{
			/* update all nessesary template information */
			include_once ("include/utility_functions.php");
			$return_status = change_graph_template($local_graph_id, $form[graph_template_id], $form[_graph_template_id]);
			
			header ("Location: graphs.php?action=graph_edit&local_graph_id=$local_graph_id");
		}
		
		break;
	case 'graph_remove':
		if (($config["remove_verification"]["value"] == "on") && ($args[confirm] != "yes")) {
			include_once ('include/top_header.php');
			DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the graph <strong>" . db_fetch_cell("select title from graph_templates_graph where local_graph_id=$args[local_graph_id]") . "</strong>?", "graphs.php", "?action=graph_remove&local_graph_id=$args[local_graph_id]");
			exit;
		}
		
		if (($config["remove_verification"]["value"] == "") || ($args[confirm] == "yes")) {
			db_execute("delete from graph_templates_graph where local_graph_id=$args[local_graph_id]");
			db_execute("delete from graph_templates_item where local_graph_id=$args[local_graph_id]");
			db_execute("delete from graph_local where id=$args[local_graph_id]");
		}
		
		header ("Location: graphs.php");
		break;
	case 'graph_edit':
		include_once ("include/top_header.php");
		$title_text = "Graph Management [edit]";
		include_once ("include/top_table_header.php");
		
		draw_graph_form_select("?action=graph_edit&local_graph_id=$args[local_graph_id]");
		new_table();
		
		$use_graph_template = true;
		
		if (isset($args[local_graph_id])) {
			$local_graph_template_graph_id = db_fetch_cell("select local_graph_template_graph_id from graph_templates_graph where local_graph_id=$args[local_graph_id]");
			
			$graphs = db_fetch_row("select * from graph_templates_graph where local_graph_id=$args[local_graph_id]");
			$graphs_template = db_fetch_row("select * from graph_templates_graph where id=$local_graph_template_graph_id");
		}else{
			unset($graphs);
			unset($graphs_template);
			
			$use_graph_template = false;
		}
		
		if ($graphs[graph_template_id] == "0") {
			$use_graph_template = false;
		}
		
		$graph_template_name = db_fetch_cell("select  name from graph_templates where id=$graphs[graph_template_id]");
		?>
		
		<form method="post" action="graphs.php">
		
		<tr>
			<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Graph Template Selection</td>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
			<td width="50%">
				<font class="textEditTitle">Selected Graph Template</font><br>
				Choose a graph template to apply to this graph. Please note that graph data may be lost if you 
				change the graph template after one is already applied.
			</td>
			<?DrawFormItemDropdownFromSQL("graph_template_id",db_fetch_assoc("select id,name from graph_templates order by name"),"name","id",$graphs[graph_template_id],"None","0");?>
		</tr>
		
		<?new_table();?>
		
		<tr>
			<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Custom Graph Configuration<?if ($graph_template_name != "") { print " <strong>[Template: $graph_template_name]</strong>"; }?></td>
		</tr>
		
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Title</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_title] == "on")) { print "The name that is printed on the graph."; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_title] == "on")) {
				DrawFormItemTextBox("title",$graphs[title],"","50", "40");
			}else{
				print "<td><em>$graphs_template[title]</em></td>";
				DrawFormItemHiddenTextBox("title",$graphs_template[title],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Image Format</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_image_format_id] == "on")) { print "The type of graph that is generated; GIF or PNG."; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_image_format_id] == "on")) {
				DrawFormItemDropdownFromSQL("image_format_id",db_fetch_assoc("select * from def_image_type order by name"),"Name","ID",$graphs[image_format_id],"","1");
			}else{
				print "<td><em>" . db_fetch_cell("select name from def_image_type where id=$graphs_template[image_format_id]") . "</em></td>";
				DrawFormItemHiddenTextBox("image_format_id",$graphs_template[image_format_id],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Height</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_height] == "on")) { print "The height (in pixels) that the graph is."; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_height] == "on")) {
				DrawFormItemTextBox("height",$graphs[height],"","50", "40");
			}else{
				print "<td><em>$graphs_template[height]</em></td>";
				DrawFormItemHiddenTextBox("height",$graphs_template[height],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Width</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_width] == "on")) { print "The width (in pixels) that the graph is."; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_width] == "on")) {
				DrawFormItemTextBox("width",$graphs[width],"","50", "40");
			}else{
				print "<td><em>$graphs_template[width]</em></td>";
				DrawFormItemHiddenTextBox("width",$graphs_template[width],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Auto Scale</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_auto_scale] == "on")) { print ""; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_auto_scale] == "on")) {
				DrawFormItemCheckBox("auto_scale",$graphs[auto_scale],"Auto Scale","on");
			}else{
				print "<td><em>" . html_boolean_friendly($graphs_template[auto_scale]) . "</em></td>";
				DrawFormItemHiddenTextBox("auto_scale",$graphs_template[auto_scale],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Auto Scale Options</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_auto_scale_opts] == "on")) { print ""; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_auto_scale_opts] == "on")) {
				print "<td>";
				DrawStrippedFormItemRadioButton("auto_scale_opts", $graphs[auto_scale_opts], "1", "Use --alt-autoscale","2",true);
				DrawStrippedFormItemRadioButton("auto_scale_opts", $graphs[auto_scale_opts], "2", "Use --alt-autoscale-max","2",true);
				print "</td>";
			}else{
				print "<td><em>";
				if ($graphs_template[auto_scale_opts] == "1") { print "Use --alt-autoscale"; }else{ print "Use --alt-autoscale-max";}
				print "</em></td>";
				DrawFormItemHiddenTextBox("auto_scale_opts",$graphs_template[auto_scale_opts],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Rigid Boundaries Mode</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_auto_scale_rigid] == "on")) { print ""; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_auto_scale_rigid] == "on")) {
				DrawFormItemCheckBox("auto_scale_rigid",$graphs[auto_scale_rigid],"Use Rigid Boundaries Mode (--rigid)","");
			}else{
				print "<td><em>" . html_boolean_friendly($graphs_template[auto_scale_rigid]) . "</em></td>";
				DrawFormItemHiddenTextBox("auto_scale_rigid",$graphs_template[auto_scale_rigid],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Logarithmic Auto Scaling</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_auto_scale_log] == "on")) { print ""; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_auto_scale_log] == "on")) {
				DrawFormItemCheckBox("auto_scale_log",$graphs[auto_scale_log],"Logarithmic Auto Scaling (--logarithmic)","");
			}else{
				print "<td><em>" . html_boolean_friendly($graphs_template[auto_scale_log]) . "</em></td>";
				DrawFormItemHiddenTextBox("auto_scale_logg",$graphs_template[auto_scale_log],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Auto Padding</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_auto_padding] == "on")) { print "Pad text so that legend and graph data always line up. Note: this could cause graphs 
				to take longer to render because of the larger overhead. Also Auto Padding may not 
				be accurate on all types of graphs, consistant labeling usually helps."; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_auto_padding] == "on")) {
				DrawFormItemCheckBox("auto_padding",$graphs[auto_padding],"Auto Padding","on");
			}else{
				print "<td><em>" . html_boolean_friendly($graphs_template[auto_padding]) . "</em></td>";
				DrawFormItemHiddenTextBox("auto_padding",$graphs_template[auto_padding],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Allow Grouping</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_grouping] == "on")) { print "This will enable you to \"group\" items of your graph together for eaier manipulation. 
				Note when you check this box and save, cacti will automatically group the items in 
				your graph; you may have to re-group part of the graph manually."; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_grouping] == "on")) {
				DrawFormItemCheckBox("grouping",$graphs[grouping],"Allow Grouping","on");
			}else{
				print "<td><em>" . html_boolean_friendly($graphs_template[grouping]) . "</em></td>";
				DrawFormItemHiddenTextBox("grouping",$graphs_template[grouping],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Allow Graph Export</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_export] == "on")) { print "Choose whether this graph will be included in the static html/png export if you use 
				cacti's export feature."; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_export] == "on")) {
				DrawFormItemCheckBox("export",$graphs[export],"Allow Graph Export","on");
			}else{
				print "<td><em>" . html_boolean_friendly($graphs_template[export]) . "</em></td>";
				DrawFormItemHiddenTextBox("export",$graphs_template[export],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Upper Limit</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_upper_limit] == "on")) { print "The maximum vertical value for the rrd graph."; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_upper_limit] == "on")) {
				DrawFormItemTextBox("upper_limit",$graphs[upper_limit],"","50", "40");
			}else{
				print "<td><em>$graphs_template[upper_limit]</em></td>";
				DrawFormItemHiddenTextBox("upper_limit",$graphs_template[upper_limit],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Lower Limit</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_lower_limit] == "on")) { print "The minimum vertical value for the rrd graph."; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_lower_limit] == "on")) {
				DrawFormItemTextBox("lower_limit",$graphs[lower_limit],"","50", "40");
			}else{
				print "<td><em>$graphs_template[lower_limit]</em></td>";
				DrawFormItemHiddenTextBox("lower_limit",$graphs_template[lower_limit],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Base Value</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_base_value] == "on")) { print "Should be set to 1024 for memory and 1000 for traffic measurements."; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_base_value] == "on")) {
				DrawFormItemTextBox("base_value",$graphs[base_value],"","50", "40");
			}else{
				print "<td><em>$graphs_template[base_value]</em></td>";
				DrawFormItemHiddenTextBox("base_value",$graphs_template[base_value],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Unit Value</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_unit_value] == "on")) { print "(--unit) Sets the exponent value on the Y-axis for numbers. Note: This option was 
				recently added in rrdtool 1.0.36."; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_unit_value] == "on")) {
				DrawFormItemTextBox("unit_value",$graphs[unit_value],"","50", "40");
			}else{
				print "<td><em>$graphs_template[unit_value]</em></td>";
				DrawFormItemHiddenTextBox("unit_value",$graphs_template[unit_value],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Unit Exponent Value</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_unit_exponent_value] == "on")) { print "What unit cacti should use on the Y-axis. Use 3 to display everything in 'k' or -6 
				to display everything in 'u' (micro)."; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_unit_exponent_value] == "on")) {
				DrawFormItemTextBox("unit_exponent_value",$graphs[unit_exponent_value],"","50", "40");
			}else{
				print "<td><em>$graphs_template[unit_exponent_value]</em></td>";
				DrawFormItemHiddenTextBox("unit_exponent_value",$graphs_template[unit_exponent_value],"");
			}?>
		</tr>
		
		<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); $i++; ?>
			<td width="50%">
				<font class="textEditTitle">Vertical Label</font><br>
				<?if (($use_graph_template == false) || ($graphs_template[t_vertical_label] == "on")) { print "The label vertically printed to the left of the graph."; }?>
			</td>
			<?if (($use_graph_template == false) || ($graphs_template[t_vertical_label] == "on")) {
				DrawFormItemTextBox("vertical_label",$graphs[vertical_label],"","50", "40");
			}else{
				print "<td><em>$graphs_template[vertical_label]</em></td>";
				DrawFormItemHiddenTextBox("vertical_label",$graphs_template[vertical_label],"");
			}?>
		</tr>
		
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right" background="images/blue_line.gif">
				<?DrawFormSaveButton("graph_save");?>
			</td>
		</tr>
		<?
		
		DrawFormItemHiddenIDField("graph_template_graph_id",$graphs[id]);
		DrawFormItemHiddenIDField("local_graph_id",$graphs[local_graph_id]);
		DrawFormItemHiddenIDField("order_key",$graphs[order_key]);
		DrawFormItemHiddenIDField("local_graph_template_graph_id",$graphs[local_graph_template_graph_id]);
		DrawFormItemHiddenIDField("_graph_template_id",$graphs[graph_template_id]);
		
		include_once ("include/bottom_table_footer.php");
		include_once ("include/bottom_footer.php");
		
		break;
	case 'tree_edit':
		include_once ("include/top_header.php");
		$title_text = "Graph Management [edit]"; $add_text = "graphs.php?action=edit_tree";
		include_once ("include/top_table_header.php");
		
		if (isset($id)) {
			$graph_tree = db_fetch_row("select * from rrd_graph_tree where id=$_GET[id]", $cnn_id);
		}else{
			unset($graph_tree);
		}
		
		draw_main_form_select();
		new_table();
		?>
		<tr>
			<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Graph Tree Configuration</td>
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
		
		include_once ("include/bottom_table_footer.php");
		include_once ("include/bottom_footer.php");
		
		break;
	case 'tree':
		include_once ("include/top_header.php");
		$title_text = "Graph Management"; $add_text = "graphs.php?action=tree_edit";
		include_once ("include/top_table_header.php");
		
		draw_main_form_select();
		new_table();
		?>
		<tr>
			<td colspan="3" class="textSubHeaderDark" bgcolor="#00438C">Graph Tree Configuration</td>
		</tr>
		<?
		
		print "<tr bgcolor='#$colors[header_panel]'>";
			DrawMatrixHeaderItem("Tree Name",$colors[header_text],1);
			DrawMatrixHeaderItem("Display as Tab",$colors[header_text],2);
		print "</tr>";
		
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
		
		include_once ("include/bottom_table_footer.php");
		include_once ("include/bottom_footer.php");
		
		break;
	default:
		include_once ("include/top_header.php");
		$title_text = "Graph Management"; $add_text = "graphs.php?action=graph_edit";
		include_once ("include/top_table_header.php");
		
		draw_main_form_select();
		new_table();
		?>
			

			<tr height="33">
				<td valign="bottom" colspan="3" background="images/tab_back.gif">
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
						<option value="graphs.php" selected>Core Routers</option>
						<option value="graphs.php">Catalyst Fabric</option>
						<option value="graphs.php">Server Farm (1)</option>
						<option value="graphs.php">Web Server</option>
					</select>
				</td>
				</form>
			</tr>
			
		<?
		
		print "<tr bgcolor='#$colors[panel]'>";
			DrawMatrixHeaderItem("Graph Title",$colors[panel_text],1);
			DrawMatrixHeaderItem("Template Name",$colors[panel_text],1);
			DrawMatrixHeaderItem("Size",$$colors[panel_text],2);
		print "</tr>";
		
		$graph_list = db_fetch_assoc("select 
			graph_templates_graph.id,
			graph_templates_graph.local_graph_id,
			graph_templates_graph.height,
			graph_templates_graph.width,
			graph_templates_graph.title,
			graph_templates.name
			from graph_templates_graph left join graph_templates on graph_templates_graph.graph_template_id=graph_templates.id
			where graph_templates_graph.local_graph_id!=0
			order by graph_templates_graph.title");
		
		if (sizeof($graph_list) > 0) {
		foreach ($graph_list as $graph) {
			DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
				?>
				<td>
					<a class="linkEditMain" href="graphs.php?action=graph_edit&local_graph_id=<?print $graph[local_graph_id];?>"><?print $graph[title];?></a>
				</td>
				<td>
					<?if ($graph[name] == "") { print "<em>None</em>"; }else{ print $graph[name]; }?>
				</td>
				<td>
					<?print $graph[height];?>x<?print $graph[width];?>
				</td>
				<td width="1%" align="right">
					<a href="graphs.php?action=graph_remove&local_graph_id=<?print $graph[local_graph_id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
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