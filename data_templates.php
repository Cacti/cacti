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
	include_once ('include/form.php');
	
	if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
	if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) {
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;
	case 'data_edit':
		include_once ("include/top_header.php");
		
		data_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'template_remove':
		template_remove();
		
		header ("Location: graph_templates.php");
		break;
	case 'template_edit':
		include_once ("include/top_header.php");
		
		template_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		template();
		
		include_once ("include/bottom_footer.php");
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function draw_data_form_select($main_action) { 
	global $colors, $args; ?>
	<tr bgcolor="<?print $colors[panel];?>">
		<form name="form_graph_id">
		<td colspan="6">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="1%">
						<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
							<option value="data_templates.php?action=template_edit&data_template_id=<?print $args[data_template_id];?>"<?if (strstr($args[action],"template")) {?> selected<?}?>>Graph Template Configuration</option>
							<option value="data_templates.php?action=data_edit&data_template_id=<?print $args[data_template_id];?>"<?if (strstr($args[action],"data")) {?> selected<?}?>>Custom Data Configuration</option>
						</select>
					</td>
					<td>
						&nbsp;<a href="data_templates.php<?print $main_action;?>"><img src="images/button_go.gif" alt="Go" border="0" align="absmiddle"></a><br>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
<?}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $form, $config;
	
	if (isset($form[save_component_template])) {
		template_save();
		return "data_templates.php?action=template_edit&data_template_id=$form[data_template_id]&view_rrd=$form[current_rrd]";
	}elseif (isset($form[save_component_data])) {
		data_save();
		
		if ($config[full_view_graph_template][value] == "") {
			return "data_templates.php?action=template_edit&data_template_id=$form[data_template_id]&view_rrd=$form[current_rrd]";
		}elseif ($config[full_view_graph_template][value] == "on") {
			return "data_templates.php";
		}
	}
}

/* ----------------------------
    data - Custom Data
   ---------------------------- */

function data_save() {
	global $form;
	
	/* ok, first pull out all 'input' values so we know how much to save */
	$input_fields = db_fetch_assoc("select
		data_template_data.data_input_id,
		data_input_fields.id,
		data_input_fields.input_output,
		data_input_fields.data_name 
		from data_template_data
		left join data_input_fields
		on data_input_fields.data_input_id=data_template_data.data_input_id
		where data_template_data.id=$form[data_template_data_id]
		and data_input_fields.input_output='in'");
	
	if (sizeof($input_fields) > 0) {
	foreach ($input_fields as $input_field) {
		/* then, check and see if this value already exists */
		$data_input_data_id = db_fetch_cell("select id from data_input_data where data_input_field_id=$input_field[id] and data_template_data_id=$form[data_template_data_id]");
		
		/* use id 0 if it doesn't; previd if it does */
		if (empty($data_input_data_id)) {
			$new_id = 0;
		}else{
			$new_id = $data_input_data_id;
		}
		
		/* save the data into the 'data_input_data' table */
		$form_value = "value_" . $input_field[data_name];
		$form_value = $form[$form_value];
		
		db_execute("replace into data_input_data (id,data_input_field_id,data_template_data_id,value) values
			($new_id,$input_field[id],$form[data_template_data_id],'$form_value')");
	}
	}
}

function data_edit() {
	global $args, $config, $colors;
	
	if ($config[full_view_data_template][value] == "") {
		start_box("<strong>Data Template Management [edit]</strong>", "", "");
		draw_data_form_select("?action=data_edit&data_template_id=$args[data_template_id]");
		end_box();
	}
	
	if (isset($args[data_template_id])) {
		$template_data = db_fetch_row("select id,data_input_id from data_template_data where data_template_id=$args[data_template_id]");
	}else{
		unset($template_data);
	}
	
	?>
	<form method="post" action="data_templates.php">
	<?
	
	$i = 0;
	if (!empty($template_data[data_input_id])) {
		$fields = db_fetch_assoc("select * from data_input_fields where data_input_id=$template_data[data_input_id] and input_output='in' order by name");
		
		start_box("Custom Data [" . db_fetch_cell("select name from data_input where id=$template_data[data_input_id]") . "]", "", "");
		
		if (sizeof($fields) > 0) {
		foreach ($fields as $field) {
			$data_input_data = db_fetch_row("select * from data_input_data where data_template_data_id=$template_data[id] and data_input_field_id=$field[id]");
			
			if (sizeof($data_input_data) > 0) {
				$old_value = $data_input_data[value];
			}else{
				$old_value = "";
			}
			
			DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); ?>
				<td width="50%">
					<strong><?print $field[name];?></strong>
				</td>
				<?DrawFormItemTextBox("value_" . $field[data_name],$old_value,"","");?>
			</tr>
			<?
			
			$i++;
		}
		}else{
		}
		
		end_box();
	}
	
	DrawFormItemHiddenIDField("data_template_id",$args[data_template_id]);
	DrawFormItemHiddenIDField("data_template_data_id",$template_data[id]);
	DrawFormItemHiddenTextBox("save_component_data","1","");
	
	if ($config[full_view_data_template][value] == "") {
		start_box("", "", "");
		?>
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right">
				<?DrawFormSaveButton("save", "data_templates.php");?>
			</td>
		</tr>
		</form>
		<?
		end_box();
	}
}

   
/* ----------------------------
    template - Graph Templates 
   ---------------------------- */

function template_remove() {
	global $args, $config;
	
	if (($config["remove_verification"]["value"] == "on") && ($args[confirm] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the graph template <strong>'" . db_fetch_cell("select name from graph_templates where id=$args[graph_template_id]") . "'</strong>? This is generally not a good idea if you have graphs attached to this template even though it should not affect any graphs.", getenv("HTTP_REFERER"), "graph_templates.php?action=template_remove&graph_template_id=$args[graph_template_id]");
		include ('include/bottom_footer.php');
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($args[confirm] == "yes")) {
		db_execute("delete from graph_templates where id=$args[graph_template_id]");
		
		$graph_template_input = db_fetch_assoc("select id from graph_template_input where graph_template_id=$args[graph_template_id]");
		
		if (sizeof($graph_template_input) > 0) {
		foreach ($graph_template_input as $item) {
			db_execute("delete from graph_template_input_defs where graph_template_input_id=$item[id]");
		}
		}
		
		db_execute("delete from graph_template_input where graph_template_id=$args[graph_template_id]");
		db_execute("delete from graph_templates_graph where graph_template_id=$args[graph_template_id] and local_graph_id=0");
		db_execute("delete from graph_templates_item where graph_template_id=$args[graph_template_id] and local_graph_id=0");
		
		/* "undo" any graph that is currently using this template */
		db_execute("update graph_templates_graph set local_graph_template_graph_id=0,graph_template_id=0 where graph_template_id=$args[graph_template_id]");
		db_execute("update graph_templates_item set local_graph_template_item_id=0,graph_template_id=0 where graph_template_id=$args[graph_template_id]");
	}	
}

function template_save() {
	global $form;
	
	if ($form[rrd_maximum] == "") { $form[rrd_maximum] = 0; }
	if ($form[rrd_minimum] == "") { $form[rrd_minimum] = 0; }
	
	/* save: data_template */
	
	$save["id"] = $form["data_template_id"];
	$save["name"] = $form["template_name"];
	$save["graph_template_id"] = 0;
	
	$data_template_id = sql_save($save, "data_template");
	unset ($save);
	
	/* save: data_template_data */
	$save["id"] = $form["data_template_data_id"];
	$save["local_data_template_data_id"] = 0;
	$save["local_data_id"] = 0;
	$save["data_template_id"] = $data_template_id;
	$save["data_input_id"] = $form["data_input_id"];
	$save["t_name"] = $form["t_name"];
	$save["name"] = $form["name"];
	$save["data_source_path"] = $form["data_source_path"];
	$save["t_active"] = $form["t_active"];
	$save["active"] = $form["active"];
	$save["t_rrd_step"] = $form["t_rrd_step"];
	$save["rrd_step"] = $form["rrd_step"];
	
	$data_template_data_id = sql_save($save, "data_template_data");
	unset ($save);
	
	/* save: data_template_rrd */
	$save["id"] = $form["data_template_rrd_id"];
	$save["local_data_template_rrd_id"] = 0;
	$save["local_data_id"] = 0;
	$save["data_template_id"] = $data_template_id;
	$save["t_rrd_maximum"] = $form["t_rrd_maximum"];
	$save["rrd_maximum"] = $form["rrd_maximum"];
	$save["t_rrd_minimum"] = $form["t_rrd_minimum"];
	$save["rrd_minimum"] = $form["rrd_minimum"];
	$save["t_rrd_heartbeat"] = $form["t_rrd_heartbeat"];
	$save["rrd_heartbeat"] = $form["rrd_heartbeat"];
	$save["t_data_source_type_id"] = $form["t_data_source_type_id"];
	$save["data_source_type_id"] = $form["data_source_type_id"];
	$save["t_data_source_name"] = $form["t_data_source_name"];
	$save["data_source_name"] = $form["data_source_name"];
	$save["script_output_argument"] = $form["script_output_argument"];
	
	$data_template_rrd_id = sql_save($save, "data_template_rrd");
	
	//include_once ("include/utility_functions.php");
	//push_out_graph($graph_template_graph_id);
}

function template_edit() {
	global $args, $config, $colors;
	
	if ($config[full_view_data_template][value] == "") {
		start_box("<strong>Data Template Management [edit]</strong>", "", "");
		draw_data_form_select("?action=template_edit&data_template_id=$args[data_template_id]");
		end_box();
	}
	
	if (isset($args[data_template_id])) {
		$template_data = db_fetch_row("select * from data_template_data where data_template_id=$args[data_template_id]");
		$template = db_fetch_row("select * from data_template where id=$args[data_template_id]");
	}else{
		unset($template_data);
		unset($template);
	}
	
	start_box("Template Configuration", "", "");
	?>
	
	<form method="post" action="data_templates.php">
		
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			The name given to this data template.
		</td>
		<?DrawFormItemTextBox("template_name",$template[name],"","150", "40");?>
	</tr>
	
	<?
	end_box();
	
	start_box("Data Template Configuration", "", "");
	?>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			<?DrawStrippedFormItemCheckBox("t_name",$template_data[t_name],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemTextBox("name",$template_data[name],"","250", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Data Source Path</font><br>
			Specify the full path to the rrd file containing the data.
		</td>
		<?DrawFormItemTextBox("data_source_path",$template_rrd[data_source_path],"","255", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Data Input Source</font><br>
			The script/source used to gather data for this data source.
		</td>
		<?DrawFormItemDropdownFromSQL("data_input_id",db_fetch_assoc("select id,name from data_input order by name"),"name","id",$template_data[data_input_id],"","1");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Step</font><br>
			<?DrawStrippedFormItemCheckBox("t_rrd_step",$template_data[t_rrd_step],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemTextBox("rrd_step",$template_data[rrd_step],"300","5","20");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Data Source Active</font><br>
			<?DrawStrippedFormItemCheckBox("t_active",$template_data[t_active],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemCheckBox("active",$template_data[active],"Data Source Active","on",$args[data_template_id]);?>
	</tr>
	
	<?
	end_box();
	
	/* fetch ALL rrd's for this data source */
	if (isset($args[data_template_id])) {
		$template_data_rrds = db_fetch_assoc("select id,data_source_name from data_template_rrd where data_template_id=$args[data_template_id] and local_data_id=0 order by data_source_name");
	}
	
	/* select the first "rrd" of this data source by default */
	if (empty($args[view_rrd])) {
		$args[view_rrd] = $template_data_rrds[0][id];
	}
	
	/* get more information about the rrd we chose */
	if (!empty($args[view_rrd])) {
		$template_rrd = db_fetch_row("select * from data_template_rrd where id=$args[view_rrd]");
	}
	
	start_box("Data Source Configuration [" . $template_rrd[data_source_name] . "]", "", "");
	
	if (sizeof($template_data_rrds) > 1) {
		?>
		<tr height="33">
			<td valign="bottom" colspan="3" background="images/tab_back_light.gif">
				<table border="0" cellspacing="0" cellpadding="0">
					<tr>
						<?
						foreach ($template_data_rrds as $template_data_rrd) {
						$i++;
						?>
						<td nowrap class="textTab" align="center" background="images/tab_middle.gif">
							<img src="images/tab_left.gif" border="0" align="absmiddle"><a class="linkTabs" href="data_templates.php?action=template_edit&data_template_id=<?print $args[data_template_id];?>&view_rrd=<?print $template_data_rrd[id];?>"><?print "$i: $template_data_rrd[data_source_name]";?></a><img src="images/tab_right.gif" border="0" align="absmiddle">
						</td>
						<?
						}
						?>
					</tr>
				</table>
			</td>
		</tr>
		<?
	}elseif (sizeof($template_data_rrds) == 1) {
		$args[view_rrd] = $template_data_rrds[0][id];
	}
	
	?>
	
	<form method="post" action="data_templates.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Internal Data Source Name</font><br>
			<?DrawStrippedFormItemCheckBox("t_data_source_name",$template_rrd[t_data_source_name],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemTextBox("data_source_name",$template_rrd[data_source_name],"","19", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Maximum Value</font><br>
			<?DrawStrippedFormItemCheckBox("t_rrd_maximum",$template_rrd[t_rrd_maximum],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemTextBox("rrd_maximum",$template_rrd[rrd_maximum],"1","20","30");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Minimum Value</font><br>
			<?DrawStrippedFormItemCheckBox("t_rrd_minimum",$template_rrd[t_rrd_minimum],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemTextBox("rrd_minimum",$template_rrd[rrd_minimum],"0","20","30");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Data Source Type</font><br>
			<?DrawStrippedFormItemCheckBox("t_data_source_type_id",$template_rrd[t_data_source_type_id],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemDropdownFromSQL("data_source_type_id",db_fetch_assoc("select * from def_ds order by Name"),"Name","ID",$template_rrd[data_source_type_id],"","1");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Heartbeat</font><br>
			<?DrawStrippedFormItemCheckBox("t_rrd_heartbeat",$template_rrd[t_rrd_heartbeat],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemTextBox("rrd_heartbeat",$template_rrd[rrd_heartbeat],"600","5","30");?>
	</tr>
	
	<?
	end_box();
	
	DrawFormItemHiddenIDField("data_template_id",$args[data_template_id]);
	DrawFormItemHiddenIDField("host_id",$args[host_id]);
	DrawFormItemHiddenIDField("data_template_data_id",$template_data[id]);
	DrawFormItemHiddenIDField("data_template_rrd_id",$template_rrd[id]);
	DrawFormItemHiddenIDField("current_rrd",$args[view_rrd]);
	DrawFormItemHiddenTextBox("save_component_template","1","");
	
	if ($config[full_view_data_template][value] == "on") {
		data_edit();	
	}
	
	start_box("", "", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "data_templates.php");?>
		</td>
	</tr>
	</form>
	<?
	end_box();
}

function template() {
	global $colors;
	
	start_box("<strong>Data Template Management</strong>", "", "data_templates.php?action=template_edit");
	
	print "<tr bgcolor='#$colors[header_panel]'>";
		DrawMatrixHeaderItem("Template Title",$colors[header_text],2);
	print "</tr>";
	
	$template_list = db_fetch_assoc("select 
		data_template.id,
		data_template.name
		from data_template
		order by data_template.name");
       
	if (sizeof($template_list) > 0) {
	foreach ($template_list as $template) {
		DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
			?>
			<td>
				<a class="linkEditMain" href="data_templates.php?action=template_edit&data_template_id=<?print $template[id];?>"><?print $template[name];?></a>
			</td>
			<td width="1%" align="right">
				<a href="data_templates.php?action=template_remove&data_template_id=<?print $template[id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
		<?
		$i++;
	}
	}
	end_box();
}

?>
