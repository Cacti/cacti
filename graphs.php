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
	case 'graph_diff':
		include_once ("include/top_header.php");
		
		graph_diff();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'item_remove':
		item_remove();
		
		header ("Location: graphs.php?action=item&local_graph_id=" . $_GET["local_graph_id"]);
		break;
	case 'item_edit':
		include_once ("include/top_header.php");
		
		item_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'graph_duplicate':
		include_once ('include/utility_functions.php');
		
		DuplicateGraph($id);
		
		header ("Location: graphs.php");
		break;
	case 'item':
		include_once ("include/top_header.php");
		
		item();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'item_movedown':
		item_movedown();
		
		header ("Location: " . getenv("HTTP_REFERER"));
		break;
	case 'item_moveup':
		item_moveup();
		
		header ("Location: " . getenv("HTTP_REFERER"));
		break;
	case 'graph_remove':
		graph_remove();
		
		header ("Location: graphs.php");
		break;
	case 'graph_edit':
		include_once ("include/top_header.php");
		
		graph_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		graph();
		
		include_once ("include/bottom_footer.php");
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function draw_graph_form_select($main_action) { 
	global $colors; ?>
	<tr bgcolor="#<?print $colors["panel"];?>">
		<form name="form_graph_id">
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="1%">
						<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
							<option value="graphs.php?action=graph_edit&local_graph_id=<?print $_GET["local_graph_id"];?>"<?if (($_GET["action"]=="") || (strstr($_GET["action"],"graph"))) {?> selected<?}?>>Graph Configuration</option>
							<option value="graphs.php?action=item&local_graph_id=<?print $_GET["local_graph_id"];?>"<?if (strstr($_GET["action"],"item")){?> selected<?}?>>Custom Graph Item Configuration</option>
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
<?
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $config;
	
	if ((isset($_POST["save_component_input"])) && (isset($_POST["save_component_graph"]))) {
		graph_save();
		input_save();
		return "graphs.php?action=graph_edit&local_graph_id=" . $_POST["local_graph_id"];
	}elseif (isset($_POST["save_component_graph"])) {
		graph_save();
		return "graphs.php?action=graph_edit&local_graph_id=" . $_POST["local_graph_id"];
	}elseif (isset($_POST["save_component_input"])) {
		input_save();
		return "graphs.php?action=item&local_graph_id=" . $_POST["local_graph_id"];
	}elseif (isset($_POST["save_component_graph_diff"])) {
		graph_diff_save();
		return "graphs.php?action=graph_edit&local_graph_id=" . $_POST["local_graph_id"];
	}elseif (isset($_POST["save_component_item"])) {
		item_save();
		
		if (empty($config["full_view_graph"]["value"])) {
			return "graphs.php?action=item&local_graph_id=" . $_POST["local_graph_id"];
		}else{
			return "graphs.php?action=graph_edit&local_graph_id=" . $_POST["local_graph_id"];
		}
	}
}
   
/* -----------------------
    item - Graph Items 
   ----------------------- */

function item() {
	global $colors, $config;
	
	if ($config["full_view_graph"]["value"] == "") {
		start_box("<strong>Graph Template Management [edit]</strong>", "98%", $colors["header"], "3", "center", "");
		draw_graph_form_select("?action=item&local_graph_id=" . $_GET["local_graph_id"]);
		end_box();
	}
	
	$graph_template_id = db_fetch_cell("select graph_template_id from graph_local where id=" . $_GET["local_graph_id"]);
	$graph_template_name = db_fetch_cell("select name from graph_templates where id=$graph_template_id");
	
	if ($graph_template_name == "") {
		$header_text = "Graph Item Configuration";
		$add_text = "graphs.php?action=item_edit&local_graph_id=" . $_GET["local_graph_id"];
	}else{
		$header_text = "Graph Item Configuration <strong>[Template: $graph_template_name]</strong>";
		$add_text = "";
	}
	
	start_box($header_text, "98%", $colors["header"], "3", "center", $add_text);
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Graph Item",$colors["header_text"],1);
		DrawMatrixHeaderItem("Task Name",$colors["header_text"],1);
		DrawMatrixHeaderItem("Graph Item Type",$colors["header_text"],1);
		DrawMatrixHeaderItem("CF Type",$colors["header_text"],1);
		DrawMatrixHeaderItem("Item Color",$colors["header_text"],4);
	print "</tr>";
	
	$template_item_list = db_fetch_assoc("select
		graph_templates_item.id,
		graph_templates_item.text_format,
		graph_templates_item.value,
		graph_templates_item.hard_return,
		CONCAT_WS(' - ',data_template_data.name,data_template_rrd.data_source_name) as data_source_name,
		cdef.name as cdef_name,
		def_cf.name as consolidation_function_name,
		def_colors.hex,
		def_graph_type.name as graph_type_name
		from graph_templates_item left join data_template_rrd on graph_templates_item.task_item_id=data_template_rrd.id
		left join data_local on data_template_rrd.local_data_id=data_local.id
		left join data_template_data on data_local.id=data_template_data.local_data_id
		left join cdef on cdef_id=cdef.id
		left join def_cf on consolidation_function_id=def_cf.id
		left join def_colors on color_id=def_colors.id
		left join def_graph_type on graph_type_id=def_graph_type.id
		where graph_templates_item.local_graph_id=" . $_GET["local_graph_id"] . "
		order by graph_templates_item.sequence");
	
	$group_counter = 0;
	if (sizeof($template_item_list) > 0) {
	foreach ($template_item_list as $item) {
		/* graph grouping display logic */
		$bold_this_row = false; $use_custom_row_color = false;
		
		if (($item["graph_type_name"] != "GPRINT") && ($item["graph_type_name"] != $_graph_type_name)) {
			$bold_this_row = true; $use_custom_row_color = true;
			
			if ($group_counter % 2 == 0) {
				$alternate_color_1 = "EEEEEE";
				$alternate_color_2 = "EEEEEE";
				$custom_row_color = "D5D5D5";
			}else{
				$alternate_color_1 = $colors["alternate"];
				$alternate_color_2 = $colors["alternate"];
				$custom_row_color = "D2D6E7";
			}
			
			$group_counter++;
		}
		
		$_graph_type_name = $item["graph_type_name"];
		
		/* alternating row color */
		if ($use_custom_row_color == false) { DrawMatrixRowAlternateColorBegin($alternate_color_1,$alternate_color_2,$i); }else{ print "<tr bgcolor=\"#$custom_row_color\">"; } $i++;
		
		print "<td" . ($graph_template_id ? "class='linkEditMain'" : "class='textEditTitle'") . ">";
		
		print "<td>";
		if (empty($graph_template_id)) { print "<a href='graphs.php?action=item_edit&graph_template_item_id=" . $item["id"] . "&local_graph_id=" . $_GET["local_graph_id"] . "'>"; }
		print "<strong>Item # " . ($i) . "</strong>";
		if (empty($graph_template_id)) { print "</a>"; }
		print "</td>\n";
		
		if (empty($item["descrip"])) { $item["descrip"] = "(No Task)"; }
			
		switch ($item["graph_type_name"]) {
		case 'AREA':
			$matrix_title = "(" . $item["data_source_name"] . "): " . $item["text_format"];
			break;
		case 'STACK':
			$matrix_title = "(" . $item["data_source_name"] . "): " . $item["text_format"];
			break;
		case 'COMMENT':
			$matrix_title = "COMMENT: " . $item["text_format"];
			break;
		case 'GPRINT':
			$matrix_title = "(" . $item["data_source_name"] . "): " . $item["text_format"];
			break;
		case 'HRULE':
			$matrix_title = "HRULE: " . $item["value"];
			break;
		case 'VRULE':
			$matrix_title = "VRULE: " . $item["value"];
			break;
		default:
			$matrix_title = "(" . $item["data_source_name"] . ")";
			break;
		}
		
		$hard_return = "";
		
		/* use the cdef name (if in use) if all else fails */
		if ($matrix_title == "") {
			if ($item["cdef_name"] != "") {
				$matrix_title .= "CDEF: " . $item["cdef_name"];
			}
		}
		
		if ($item["hard_return"] == "on") {
			$hard_return = "<strong><font color=\"#FF0000\">&lt;HR&gt;</font></strong>";
		}
		
		?>
		<td>
			<?if ($bold_this_row == true) { print "<strong>"; }?><?print htmlspecialchars($matrix_title) . $hard_return;?><?if ($bold_this_row == true) { print "</strong>"; }?>
		</td>
		<td>
			<?if ($bold_this_row == true) { print "<strong>"; }?><?print $item["graph_type_name"];?><?if ($bold_this_row == true) { print "</strong>"; }?>
		</td>
		<td>
			<?if ($bold_this_row == true) { print "<strong>"; }?><?print $item["consolidation_function_name"];?><?if ($bold_this_row == true) { print "</strong>"; }?>
		</td>
		<td<?if ($item["hex"] != "") { print ' bgcolor="#' .  $item["hex"] . '"'; }?> width="1%">
			&nbsp;
		</td>
		<td>
			<?if ($bold_this_row == true) { print "<strong>"; }?><?print $item["hex"];?><?if ($bold_this_row == true) { print "</strong>"; }?>
		</td>
		<td>
			<a href="graphs.php?action=item_movedown&graph_template_item_id=<?print $item["id"];?>&local_graph_id=<?print $_GET["local_graph_id"];?>"><img src="images/move_down.gif" border="0" alt="Move Down"></a>
			<a href="graphs.php?action=item_moveup&graph_template_item_id=<?print $item["id"];?>&local_graph_id=<?print $_GET["local_graph_id"];?>"><img src="images/move_up.gif" border="0" alt="Move Up"></a>
		</td>
		<td width="1%" align="right">
			<?if ($graph_template_id == "0") {?><a href="graphs.php?action=item_remove&graph_template_item_id=<?print $item["id"];?>&local_graph_id=<?print $_GET["local_graph_id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;<?}?>
		</td>
	</tr>
	<?
	
	}
	}else{
		DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
			<td colspan="7">
				<em>No Items</em>
			</td>
		</tr><?
	}
	end_box();
	
	/* only display the "inputs" area if we are using a graph template for this graph */
	if ($graph_template_id != "0") {
		start_box("Graph Item Inputs", "98%", $colors["header"], "3", "center", "");
		
		?>
		<form method="post" action="graphs.php">
		<?
		
		$input_item_list = db_fetch_assoc("select * from graph_template_input where graph_template_id=$graph_template_id order by name");
		
		if (sizeof($input_item_list) > 0) {
		foreach ($input_item_list as $item) {
			$current_def_value = db_fetch_row("select 
				graph_templates_item." . $item["column_name"] . ",
				graph_templates_item.id
				from graph_templates_item,graph_template_input_defs 
				where graph_template_input_defs.graph_template_item_id=graph_templates_item.local_graph_template_item_id 
				and graph_template_input_defs.graph_template_input_id=" . $item["id"] . "
				and graph_templates_item.local_graph_id=" . $_GET["local_graph_id"] . "
				limit 0,1");
			
			$column_name = $item["column_name"];
			
			DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
				<td width="50%">
					<font class="textEditTitle"><?print $item["name"];?></font>
					<?if ($item["description"] != "") { print "<br>" . $item["description"]; }?>
				</td>
				<?
				switch ($item["column_name"]) {
				case 'task_item_id':
					DrawFormItemDropdownFromSQL($item["id"],db_fetch_assoc("select
						CONCAT_WS('',case when host.description is null then 'No Host' when host.description is not null then host.description end,' - ',data_template_data.name,' (',data_template_rrd.data_source_name,')') as name,
						data_template_rrd.id 
						from data_template_data,data_template_rrd,data_local 
						left join host on data_local.host_id=host.id
						where data_template_rrd.local_data_id=data_local.id 
						and data_template_data.local_data_id=data_local.id"),"name","id",$current_def_value[$column_name],"None","");
					break;
				case 'color_id':
					DrawFormItemColorSelect($item["id"],$current_def_value[$column_name],"None","0");
					break;
				case 'graph_type_id':
					DrawFormItemDropdownFromSQL($item["id"],db_fetch_assoc("select id,name from def_graph_type order by name"),"name","id",$current_def_value[$column_name],"","");
					break;
				case 'consolidation_function_id':
					DrawFormItemDropdownFromSQL($item["id"],db_fetch_assoc("select id,name from def_cf order by name"),"name","id",$current_def_value[$column_name],"","");
					break;
				case 'cdef_id':
					DrawFormItemDropdownFromSQL($item["id"],db_fetch_assoc("select id,name from rrd_ds_cdef order by name"),"name","id","None",$current_def_value[$column_name],"");
					break;
				case 'value':
					DrawFormItemTextBox($item["id"],$current_def_value[$column_name],"","");
					break;
				case 'gprint_opts':
					DrawFormItemDropdownFromSQL("gprint_id",db_fetch_assoc("select id,name from graph_templates_gprint order by name"),"name","id",$template_item["gprint_id"],"Default","");
					break;
				case 'text_format':
					DrawFormItemTextBox($item["id"],$current_def_value[$column_name],"","","40");
					break;
				case 'hard_return':
					DrawFormItemCheckBox($item["id"],$current_def_value[$column_name],"Insert Hard Return","");
					break;
				}
				
				?>
			</tr>
			<?
		}
			DrawFormItemHiddenIDField("local_graph_id",$_GET["local_graph_id"]);
			DrawFormItemHiddenTextBox("save_component_input","1","");
		}else{
			DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
				<td colspan="1">
					<em>No Inputs</em>
				</td>
			</tr><?
		}
		
		end_box();
	}
	
	if (($config["full_view_graph"]["value"] == "") && (sizeof($input_item_list) > 0)) {
		start_box("", "98%", $colors["header"], "3", "center", "");
		?>
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right">
				<?DrawFormSaveButton("save", "graphs.php");?>
			</td>
		</tr>
		</form>
		<?
		end_box();
	}
}

function item_movedown() {
	include_once ("include/functions.php");
	
	move_item_down("graph_templates_item", $_GET["graph_template_item_id"], "local_graph_id=" . $_GET["local_graph_id"]);	
}

function item_moveup() {
	include_once ("include/functions.php");
	
	move_item_up("graph_templates_item", $_GET["graph_template_item_id"], "local_graph_id=" . $_GET["local_graph_id"]);	
}

function item_remove() {
	db_execute("delete from graph_templates_item where id=" . $_GET["graph_template_item_id"]);	
}

function item_save() {
	$save["id"] = $_POST["graph_template_item_id"];
	$save["graph_template_id"] = $_POST["graph_template_id"];
	$save["local_graph_id"] = $_POST["local_graph_id"];
	$save["task_item_id"] = $_POST["task_item_id"];
	$save["color_id"] = $_POST["color_id"];
	$save["graph_type_id"] = $_POST["graph_type_id"];
	$save["cdef_id"] = $_POST["cdef_id"];
	$save["consolidation_function_id"] = $_POST["consolidation_function_id"];
	$save["text_format"] = $_POST["text_format"];
	$save["value"] = $_POST["value"];
	$save["hard_return"] = $_POST["hard_return"];
	$save["gprint_id"] = $_POST["gprint_id"];
	$save["sequence"] = $_POST["sequence"];
	
	$graph_template_item_id = sql_save($save, "graph_templates_item");
}

function item_edit() {
	global $colors, $config;
	
	if ($config["full_view_graph"]["value"] == "") {
		start_box("Graph Template Management [edit]", "98%", $colors["header"], "3", "center", "");
		draw_graph_form_select("?action=item&local_graph_id=" . $_GET["local_graph_id"]);
		end_box();
	}
	
	if (isset($_GET["graph_template_item_id"])) {
		$template_item = db_fetch_row("select * from graph_templates_item where id=" . $_GET["graph_template_item_id"]);
	}else{
		unset($template_item);
	}
	
	start_box("Template Item Configuration", "98%", $colors["header"], "3", "center", "");
	?>
	<form method="post" action="graphs.php">
	
	<?
	/* by default, select the LAST DS chosen to make everyone's lives easier */
	$default = db_fetch_row("select task_item_id from graph_templates_item where local_graph_id=" . $_GET["local_graph_id"] . " order by sequence_parent DESC,sequence DESC");

	if (sizeof($default) > 0) {
		$default_item = $default["task_item_id"];
	}else{
		$default_item = 0;
	}
	
	DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Task Item</font><br>
			The task to use for this graph item; not used for COMMENT fields.
		</td>
		<?DrawFormItemDropdownFromSQL("task_item_id",db_fetch_assoc("select
			CONCAT_WS('',case when host.description is null then 'No Host' when host.description is not null then host.description end,' - ',data_template_data.name,' (',data_template_rrd.data_source_name,')') as name,
			data_template_rrd.id 
			from data_template_data,data_template_rrd,data_local 
			left join host on data_local.host_id=host.id
			where data_template_rrd.local_data_id=data_local.id 
			and data_template_data.local_data_id=data_local.id"),"name","id",$template_item["task_item_id"],"None",$default_item);?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Color</font><br>
			The color that is used for this item; not used for COMMENT fields.
		</td>
		<?DrawFormItemColorSelect("color_id",$template_item["color_id"],"None","0");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Graph Item Type</font><br>
			How data for this item is displayed.
		</td>
		<?DrawFormItemDropdownFromSQL("graph_type_id",db_fetch_assoc("select id,name from def_graph_type order by name"),"name","id",$template_item["graph_type_id"],"","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Consolidation Function</font><br>
			How data is to be represented on the graph.
		</td>
		<?DrawFormItemDropdownFromSQL("consolidation_function_id",db_fetch_assoc("select id,name from def_cf order by name"),"name","id",$template_item["consolidation_function_id"],"","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">CDEF Function</font><br>
			A CDEF Function to apply to this item on the graph.
		</td>
		<?DrawFormItemDropdownFromSQL("cdef_id",db_fetch_assoc("select id,name from cdef order by name"),"name","id",$template_item["cdef_id"],"None","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Value</font><br>
			For use with VRULE and HRULE, <i>numbers only</i>.
		</td>
		<?DrawFormItemTextBox("value",$template_item["value"],"","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">GPRINT String Type</font><br>
			Choose a GPRINT string to represent textual data on your graph. To add/edit these custom strings, select "GPRINT Presets" under "Graph Templates".
		</td>
		<?DrawFormItemDropdownFromSQL("gprint_id",db_fetch_assoc("select id,name from graph_templates_gprint order by name"),"name","id",$template_item["gprint_id"],"","2");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Text Format</font><br>
			The text of the comment or legend, input and output keywords are allowed.
		</td>
		<td>
		<?
			DrawStrippedFormItemTextBox("text_format",$template_item["text_format"],"","","40");
			print "<br>";
			DrawStrippedFormItemCheckBox("hard_return",$template_item["hard_return"],"Insert Hard Return","",false);
		?>
		</td>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("local_graph_id",$_GET["local_graph_id"]);
	DrawFormItemHiddenIDField("graph_template_item_id",$_GET["graph_template_item_id"]);
	DrawFormItemHiddenIDField("graph_template_id",$template_item["graph_template_id"]);
	DrawFormItemHiddenIDField("sequence",$template_item["sequence"]);
	DrawFormItemHiddenIDField("_graph_type_id",$template_item["graph_type_id"]);
	DrawFormItemHiddenTextBox("save_component_item","1","");
	
	end_box();
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", $_SERVER["HTTP_REFERER"]);?>
		</td>
	</tr>
	</form>
	<?
	end_box();
}

/* ------------------------------------
    input - Graph Template Item Inputs 
   ------------------------------------ */

function input_save() {
	/* first; get the current graph template id */
	$graph_template_id = db_fetch_cell("select graph_template_id from graph_local where id=" . $_POST["local_graph_id"]);
	
	/* get all inputs that go along with this graph template */
	$input_list = db_fetch_assoc("select id,column_name from graph_template_input where graph_template_id=$graph_template_id");
	
	if (sizeof($input_list) > 0) {
	foreach ($input_list as $input) {
		/* we need to find out which graph items will be affected by saving this particular item */
		$item_list = db_fetch_assoc("select
			graph_templates_item.id
			from graph_template_input_defs,graph_templates_item
			where graph_template_input_defs.graph_template_item_id=graph_templates_item.local_graph_template_item_id
			and graph_templates_item.local_graph_id=" . $_POST["local_graph_id"] . "
			and graph_template_input_defs.graph_template_input_id=" . $input["id"]);
		
		/* get some variables */
		$column_name = $input["column_name"];
		$graph_template_input_id = $input["id"];
		$column_value = $_POST[$graph_template_input_id];
		
		/* loop through each item affected and update column data */
		if (sizeof($item_list) > 0) {
		foreach ($item_list as $item) {
			db_execute("update graph_templates_item set $column_name=$column_value where id=" . $item["id"]);
		}
		}
	}
	}	
}

/* ------------------------------------
    graph - Graphs
   ------------------------------------ */

function graph_save() {
	include_once ("include/utility_functions.php");
	
	if ($_POST["lower_limit"] == "") { $_POST["lower_limit"] = 0; }
	if ($_POST["upper_limit"] == "") { $_POST["upper_limit"] = 0; }
	if ($_POST["unit_exponent_value"] == "") { $_POST["unit_exponent_value"] = 0; }
	
	$save["id"] = $_POST["local_graph_id"];
	$save["graph_template_id"] = $_POST["graph_template_id"];
	
	$local_graph_id = sql_save($save, "graph_local");
	unset($save);
	
	$save["id"] = $_POST["graph_template_graph_id"];
	$save["local_graph_template_graph_id"] = $_POST["local_graph_template_graph_id"];
	$save["local_graph_id"] = $local_graph_id;
	$save["graph_template_id"] = $_POST["graph_template_id"];
	$save["order_key"] = $_POST["order_key"];
	$save["image_format_id"] = $_POST["image_format_id"];
	$save["title"] = $_POST["title"];
	$save["height"] = $_POST["height"];
	$save["width"] = $_POST["width"];
	$save["upper_limit"] = $_POST["upper_limit"];
	$save["lower_limit"] = $_POST["lower_limit"];
	$save["vertical_label"] = $_POST["vertical_label"];
	$save["auto_scale"] = $_POST["auto_scale"];
	$save["auto_scale_opts"] = $_POST["auto_scale_opts"];
	$save["auto_scale_log"] = $_POST["auto_scale_log"];
	$save["auto_scale_rigid"] = $_POST["auto_scale_rigid"];
	$save["auto_padding"] = $_POST["auto_padding"];
	$save["base_value"] = $_POST["base_value"];
	$save["grouping"] = $_POST["grouping"];
	$save["export"] = $_POST["export"];
	$save["unit_value"] = $_POST["unit_value"];
	$save["unit_exponent_value"] = $_POST["unit_exponent_value"];
	
	sql_save($save, "graph_templates_graph");
	
	/* if template information chanegd, update all nessesary template information */
	if ($_POST["graph_template_id"] != $_POST["_graph_template_id"]) {
		/* check to see if the number of graph items differs, if it does; we need user input */
		if ((!empty($_POST["graph_template_id"])) && (sizeof(db_fetch_assoc("select id from graph_templates_item where local_graph_id=$local_graph_id")) != sizeof(db_fetch_assoc("select id from graph_templates_item where local_graph_id=0 and graph_template_id=" . $_POST["graph_template_id"])))) {
			/* set the template back, since the user may choose not to go through with the change
			at this point */
			db_execute("update graph_local set graph_template_id=" . $_POST["_graph_template_id"] . " where id=$local_graph_id");
			db_execute("update graph_templates_graph set graph_template_id=" . $_POST["_graph_template_id"] . " where local_graph_id=$local_graph_id");
			
			header("Location: graphs.php?action=graph_diff&local_graph_id=$local_graph_id&graph_template_id=" . $_POST["graph_template_id"]);
			exit;
		}
		
		change_graph_template($local_graph_id, $_POST["graph_template_id"], true);
	}
	
	/* so we get redirected to the correct page, not '&local_graph_id=0' */
	$_POST["local_graph_id"] = $local_graph_id;
}

function graph_diff_save() {
	include_once ("include/utility_functions.php");
	
	if ($_POST["type"] == "1") {
		$intrusive = true;
	}elseif ($_POST["type"] == "2") {
		$intrusive = false;
	}
	
	change_graph_template($_POST["local_graph_id"], $_POST["graph_template_id"], $intrusive);
}

function graph_diff() {
	global $colors;
	include("include/config_arrays.php");
	
	$template_query = "select
		graph_templates_item.id,
		graph_templates_item.text_format,
		graph_templates_item.value,
		graph_templates_item.hard_return,
		graph_templates_item.consolidation_function_id,
		graph_templates_item.graph_type_id,
		CONCAT_WS(' - ',data_template_data.name,data_template_rrd.data_source_name) as task_item_id,
		cdef.name as cdef_id,
		def_colors.hex as color_id
		from graph_templates_item left join data_template_rrd on graph_templates_item.task_item_id=data_template_rrd.id
		left join data_local on data_template_rrd.local_data_id=data_local.id
		left join data_template_data on data_local.id=data_template_data.local_data_id
		left join cdef on cdef_id=cdef.id
		left join def_colors on color_id=def_colors.id";
	
	/* first, get information about the graph template as that's what we're going to model this
	graph after */
	$graph_template_items = db_fetch_assoc("
		$template_query
		where graph_templates_item.graph_template_id=" . $_GET["graph_template_id"] . "
		and graph_templates_item.local_graph_id=0
		order by graph_templates_item.sequence");
	
	/* next, get information about the current graph so we can make the appropriate comparisons */
	$graph_items = db_fetch_assoc("
		$template_query
		where graph_templates_item.local_graph_id=" . $_GET["local_graph_id"] . "
		order by graph_templates_item.sequence");
	
	$graph_template_inputs = db_fetch_assoc("select
		graph_template_input.column_name,
		graph_template_input_defs.graph_template_item_id
		from graph_template_input,graph_template_input_defs
		where graph_template_input.id=graph_template_input_defs.graph_template_input_id
		and graph_template_input.graph_template_id=" . $_GET["graph_template_id"]);
	
	/* ok, we want to loop through the array with the GREATEST number of items so we don't have to worry
	about tacking items on the end */
	if (sizeof($graph_template_items) > sizeof($graph_items)) {
		$items = $graph_template_items;
	}else{
		$items = $graph_items;
	}
	
	?>
	<table style="background-color: #f5f5f5; border: 1px solid #aaaaaa;" width="98%" align="center">
		<tr>          
			<td class="textArea">
				The template you have selected requires some changes to be made to the structure of
				your graph. Below is a preview of your graph along with changes that need to be completed
				as shown in the left-hand column.
			</td>
		</tr>
	</table>
	<br>
	<?
	
	start_box("<strong>Graph Preview</strong>", "98%", $colors["header"], "3", "center", "");
	
	$graph_item_actions = array("normal" => "", "add" => "+", "delete" => "-");
	
	$group_counter = 0; $i = 0; $mode = "normal";
	if (sizeof($items) > 0) {
	foreach ($items as $item) {
		/* graph grouping display logic */
		$bold_this_row = false; $use_custom_row_color = false; unset($graph_preview_item_values);
		
		if ((sizeof($graph_template_items) > sizeof($graph_items)) && ($i >= sizeof($graph_items))) {
			$mode = "add";
			$user_message = "When you click save, the items marked with a '<strong>+</strong>' will be added <strong>(Recommended)</strong>.";
		}elseif ((sizeof($graph_template_items) < sizeof($graph_items)) && ($i >= sizeof($graph_template_items))) {
			$mode = "delete";
			$user_message = "When you click save, the items marked with a '<strong>-</strong>' will be removed <strong>(Recommended)</strong>.";
		}
		
		/* here is the fun meshing part. first we check the graph template to see if there is an input
		for each field of this row. if there is, we revert to the value stored in the graph, if not
		we revert to the value stored in the template. got that? ;) */
		for ($j=0; ($j < count($graph_template_inputs)); $j++) {
			if ($graph_template_inputs[$j]["graph_template_item_id"] == $graph_template_items[$i]["id"]) {
				/* if we find out that there is an "input" covering this field/item, use the 
				value from the graph, not the template */
				$graph_item_field_name = $graph_template_inputs[$j]["column_name"];
				$graph_preview_item_values[$graph_item_field_name] = $graph_items[$i][$graph_item_field_name];
			}
		}
		
		/* go back through each graph field and find out which ones haven't been covered by the 
		"inputs" above. for each one, use the value from the template */
		for ($j=0; ($j < count($struct_graph_item)); $j++) {
			$graph_item_field_name = $struct_graph_item[$j];
			
			if ($mode == "delete") {
				$graph_preview_item_values[$graph_item_field_name] = $graph_items[$i][$graph_item_field_name];
			}elseif (!isset($graph_preview_item_values[$graph_item_field_name])) {
				$graph_preview_item_values[$graph_item_field_name] = $graph_template_items[$i][$graph_item_field_name];
			}
		}
		
		/* "prepare" array values */
		$consolidation_function_id = $graph_preview_item_values["consolidation_function_id"];
		$graph_type_id = $graph_preview_item_values["graph_type_id"];
		
		/* color logic */
		if (($graph_item_types[$graph_type_id] != "GPRINT") && ($graph_item_types[$graph_type_id] != $_graph_type_name)) {
			$bold_this_row = true; $use_custom_row_color = true;
			
			if ($group_counter % 2 == 0) {
				$alternate_color_1 = "EEEEEE";
				$alternate_color_2 = "EEEEEE";
				$custom_row_color = "D5D5D5";
			}else{
				$alternate_color_1 = $colors["alternate"];
				$alternate_color_2 = $colors["alternate"];
				$custom_row_color = "D2D6E7";
			}
			
			$group_counter++;
		}
		
		$_graph_type_name = $graph_item_types[$graph_type_id];
		
		/* alternating row colors */
		if ($use_custom_row_color == false) {
			if ($i % 2 == 0) {
				$action_column_color = $alternate_color_1;
			}else{
				$action_column_color = $alternate_color_2;
			}
		}else{
			$action_column_color = $custom_row_color;
		}
		
		print "<tr bgcolor='#$action_column_color'>"; $i++;
		
		/* make the left-hand column blue or red depending on if "add"/"remove" mode is set */
		if ($mode == "add") {
			$action_column_color = $colors["header"];
			$action_css = "";
		}elseif ($mode == "delete") {
			$action_column_color = "C63636";
			$action_css = "text-decoration: line-through;";
		}
		
		/* draw the TD that shows the user whether we are going to: KEEP, ADD, or DROP the item */
		print "<td width='1%' bgcolor='#$action_column_color' style='font-weight: bold; color: white;'>" . $graph_item_actions[$mode] . "</td>";
		print "<td style='$action_css'><strong>Item # " . $i . "</strong></td>\n";
		
		if (empty($graph_preview_item_values["task_item_id"])) { $graph_preview_item_values["task_item_id"] = "No Task"; }
			
		switch ($graph_item_types[$graph_type_id]) {
		case 'AREA':
			$matrix_title = "(" . $graph_preview_item_values["task_item_id"] . "): " . $graph_preview_item_values["text_format"];
			break;
		case 'STACK':
			$matrix_title = "(" . $graph_preview_item_values["task_item_id"] . "): " . $graph_preview_item_values["text_format"];
			break;
		case 'COMMENT':
			$matrix_title = "COMMENT: " . $graph_preview_item_values["text_format"];
			break;
		case 'GPRINT':
			$matrix_title = "(" . $graph_preview_item_values["task_item_id"] . "): " . $graph_preview_item_values["text_format"];
			break;
		case 'HRULE':
			$matrix_title = "HRULE: " . $graph_preview_item_values["value"];
			break;
		case 'VRULE':
			$matrix_title = "VRULE: " . $graph_preview_item_values["value"];
			break;
		default:
			$matrix_title = "(" . $graph_preview_item_values["task_item_id"] . ")";
			break;
		}
		
		$hard_return = "";
		
		/* use the cdef name (if in use) if all else fails */
		if ($matrix_title == "") {
			if ($graph_preview_item_values["cdef_id"] != "") {
				$matrix_title .= "CDEF: " . $graph_preview_item_values["cdef_id"];
			}
		}
		
		if ($graph_preview_item_values["hard_return"] == "on") {
			$hard_return = "<strong><font color=\"#FF0000\">&lt;HR&gt;</font></strong>";
		}
		
		?>
		<td style="<?print $action_css;?>">
			<?if ($bold_this_row == true) { print "<strong>"; }?><?print htmlspecialchars($matrix_title) . $hard_return;?><?if ($bold_this_row == true) { print "</strong>"; }?>
		</td>
		<td style="<?print $action_css;?>">
			<?if ($bold_this_row == true) { print "<strong>"; }?><?print $graph_item_types[$graph_type_id];?><?if ($bold_this_row == true) { print "</strong>"; }?>
		</td>
		<td style="<?print $action_css;?>">
			<?if ($bold_this_row == true) { print "<strong>"; }?><?print $consolidation_functions[$consolidation_function_id];?><?if ($bold_this_row == true) { print "</strong>"; }?>
		</td>
		<td<?if ($graph_preview_item_values["color_id"] != "") { print ' bgcolor="#' .  $graph_preview_item_values["color_id"] . '"'; }?> width="1%">
			&nbsp;
		</td>
		<td>
			<?if ($bold_this_row == true) { print "<strong>"; }?><?print $graph_preview_item_values["hex"];?><?if ($bold_this_row == true) { print "</strong>"; }?>
		</td>
	</tr>
	<?
	
	}
	}else{
		DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
			<td colspan="7">
				<em>No Items</em>
			</td>
		</tr><?
	}
	end_box();	
	
	?>
	<form action="graphs.php" method="post">
	<table style="background-color: #f5f5f5; border: 1px solid #aaaaaa;" width="98%" align="center">
		<tr>          
			<td class="textArea">
				<input type='radio' name='type' value='1' checked>&nbsp;<?print $user_message;?><br>
				<input type='radio' name='type' value='2'>&nbsp;When you click save, the graph items will remain untouched (could cause inconsistencies).
			</td>
		</tr>
	</table>
	
	<br>
	<table style="background-color: #ffffff; border: 1px solid #aaaaaa;" width="98%" align="center">
		<tr>
			 <td colspan="2" align="right">
				<?DrawFormSaveButton("save", "graphs.php?action=graph_edit&local_graph_id=" . $_GET["local_graph_id"]);?>
			</td>
		</tr>
	</table>
	<input type="hidden" name="action" value="save">
	<input type="hidden" name="save_component_graph_diff" value="1">
	<input type="hidden" name="local_graph_id" value="<?print $_GET["local_graph_id"];?>">
	<input type="hidden" name="graph_template_id" value="<?print $_GET["graph_template_id"];?>">
	</form>
	<?
}

function graph_remove() {
	global $config;
	
	if (($config["remove_verification"]["value"] == "on") && ($_GET["confirm"] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the graph <strong>" . db_fetch_cell("select title from graph_templates_graph where local_graph_id=" . $_GET["local_graph_id"]) . "</strong>?", getenv("HTTP_REFERER"), "graphs.php?action=graph_remove&local_graph_id=" . $_GET["local_graph_id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($_GET["confirm"] == "yes")) {
		db_execute("delete from graph_templates_graph where local_graph_id=" . $_GET["local_graph_id"]);
		db_execute("delete from graph_templates_item where local_graph_id=" . $_GET["local_graph_id"]);
		db_execute("delete from graph_local where id=" . $_GET["local_graph_id"]);
	}	
}

function graph_edit() {
	global $config, $colors;
	
	if ($config["full_view_graph"]["value"] == "") {
		start_box("<strong>Graph Management [edit]</strong>", "98%", $colors["header"], "3", "center", "");
		draw_graph_form_select("?action=graph_edit&local_graph_id=" . $_GET["local_graph_id"]);
		end_box();
	}
	
	$use_graph_template = true;
	
	if (isset($_GET["local_graph_id"])) {
		$local_graph_template_graph_id = db_fetch_cell("select local_graph_template_graph_id from graph_templates_graph where local_graph_id=" . $_GET["local_graph_id"]);
		
		$graphs = db_fetch_row("select * from graph_templates_graph where local_graph_id=" . $_GET["local_graph_id"]);
		$graphs_template = db_fetch_row("select * from graph_templates_graph where id=$local_graph_template_graph_id");
	}else{
		unset($graphs);
		unset($graphs_template);
		
		$use_graph_template = false;
	}
	
	if ($graphs["graph_template_id"] == "0") {
		$use_graph_template = false;
	}
	
	$graph_template_name = db_fetch_cell("select  name from graph_templates where id=" . $graphs["graph_template_id"]);
	
	if (($config["full_view_graph"]["value"] == "on") && ($_GET["local_graph_id"] > 0)) {
		item();
	}
	
	start_box("Graph Template Selection", "98%", $colors["header"], "3", "center", "");
	?>
	
	<form method="post" action="graphs.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Selected Graph Template</font><br>
			Choose a graph template to apply to this graph. Please note that graph data may be lost if you 
			change the graph template after one is already applied.
		</td>
		<?DrawFormItemDropdownFromSQL("graph_template_id",db_fetch_assoc("select 
			graph_templates.id,graph_templates.name 
			from graph_templates
			where graph_templates.type='template'"),"name","id",$graphs["graph_template_id"],"None","0");?>
	</tr>
	
	<?
	end_box();
	start_box("Custom Graph Configuration", "98%", $colors["header"], "3", "center", "");
	
	DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Title</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_title"] == "on")) { print "The name that is printed on the graph."; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_title"] == "on")) {
			DrawFormItemTextBox("title",$graphs["title"],"","50", "40");
		}else{
			print "<td><em>" . $graphs["title"] . "</em></td>";
			DrawFormItemHiddenTextBox("title",$graphs["title"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Image Format</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_image_format_id"] == "on")) { print "The type of graph that is generated; GIF or PNG."; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_image_format_id"] == "on")) {
			DrawFormItemDropdownFromSQL("image_format_id",db_fetch_assoc("select * from def_image_type order by name"),"Name","ID",$graphs["image_format_id"],"","1");
		}else{
			print "<td><em>" . db_fetch_cell("select name from def_image_type where id=" . $graphs["image_format_id"]) . "</em></td>";
			DrawFormItemHiddenTextBox("image_format_id",$graphs["image_format_id"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Height</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_height"] == "on")) { print "The height (in pixels) that the graph is."; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_height"] == "on")) {
			DrawFormItemTextBox("height",$graphs["height"],"","50", "40");
		}else{
			print "<td><em>" . $graphs["height"] . "</em></td>";
			DrawFormItemHiddenTextBox("height",$graphs["height"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Width</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_width"] == "on")) { print "The width (in pixels) that the graph is."; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_width"] == "on")) {
			DrawFormItemTextBox("width",$graphs["width"],"","50", "40");
		}else{
			print "<td><em>" . $graphs["width"] . "</em></td>";
			DrawFormItemHiddenTextBox("width",$graphs["width"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Auto Scale</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_auto_scale"] == "on")) { print ""; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_auto_scale"] == "on")) {
			DrawFormItemCheckBox("auto_scale",$graphs["auto_scale"],"Auto Scale","on");
		}else{
			print "<td><em>" . html_boolean_friendly($graphs["auto_scale"]) . "</em></td>";
			DrawFormItemHiddenTextBox("auto_scale",$graphs["auto_scale"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Auto Scale Options</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_auto_scale_opts"] == "on")) { print ""; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_auto_scale_opts"] == "on")) {
			print "<td>";
			DrawStrippedFormItemRadioButton("auto_scale_opts", $graphs["auto_scale_opts"], "1", "Use --alt-autoscale","2",true);
			DrawStrippedFormItemRadioButton("auto_scale_opts", $graphs["auto_scale_opts"], "2", "Use --alt-autoscale-max","2",true);
			print "</td>";
		}else{
			print "<td><em>";
			if ($graphs_template["auto_scale_opts"] == "1") { print "Use --alt-autoscale"; }else{ print "Use --alt-autoscale-max";}
			print "</em></td>";
			DrawFormItemHiddenTextBox("auto_scale_opts",$graphs["auto_scale_opts"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Rigid Boundaries Mode</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_auto_scale_rigid"] == "on")) { print ""; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_auto_scale_rigid"] == "on")) {
			DrawFormItemCheckBox("auto_scale_rigid",$graphs["auto_scale_rigid"],"Use Rigid Boundaries Mode (--rigid)","");
		}else{
			print "<td><em>" . html_boolean_friendly($graphs["auto_scale_rigid"]) . "</em></td>";
			DrawFormItemHiddenTextBox("auto_scale_rigid",$graphs["auto_scale_rigid"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Logarithmic Auto Scaling</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_auto_scale_log"] == "on")) { print ""; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_auto_scale_log"] == "on")) {
			DrawFormItemCheckBox("auto_scale_log",$graphs["auto_scale_log"],"Logarithmic Auto Scaling (--logarithmic)","");
		}else{
			print "<td><em>" . html_boolean_friendly($graphs["auto_scale_log"]) . "</em></td>";
			DrawFormItemHiddenTextBox("auto_scale_logg",$graphs["auto_scale_log"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Auto Padding</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_auto_padding"] == "on")) { print "Pad text so that legend and graph data always line up. Note: this could cause graphs 
			to take longer to render because of the larger overhead. Also Auto Padding may not 
			be accurate on all types of graphs, consistant labeling usually helps."; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_auto_padding"] == "on")) {
			DrawFormItemCheckBox("auto_padding",$graphs["auto_padding"],"Auto Padding","on");
		}else{
			print "<td><em>" . html_boolean_friendly($graphs["auto_padding"]) . "</em></td>";
			DrawFormItemHiddenTextBox("auto_padding",$graphs["auto_padding"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Allow Grouping</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_grouping"] == "on")) { print "This will enable you to \"group\" items of your graph together for eaier manipulation. 
			Note when you check this box and save, cacti will automatically group the items in 
			your graph; you may have to re-group part of the graph manually."; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_grouping"] == "on")) {
			DrawFormItemCheckBox("grouping",$graphs["grouping"],"Allow Grouping","on");
		}else{
			print "<td><em>" . html_boolean_friendly($graphs["grouping"]) . "</em></td>";
			DrawFormItemHiddenTextBox("grouping",$graphs["grouping"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Allow Graph Export</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_export"] == "on")) { print "Choose whether this graph will be included in the static html/png export if you use 
			cacti's export feature."; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_export"] == "on")) {
			DrawFormItemCheckBox("export",$graphs["export"],"Allow Graph Export","on");
		}else{
			print "<td><em>" . html_boolean_friendly($graphs["export"]) . "</em></td>";
			DrawFormItemHiddenTextBox("export",$graphs["export"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Upper Limit</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_upper_limit"] == "on")) { print "The maximum vertical value for the rrd graph."; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_upper_limit"] == "on")) {
			DrawFormItemTextBox("upper_limit",$graphs["upper_limit"],"","50", "40");
		}else{
			print "<td><em>" . $graphs["upper_limit"] . "</em></td>";
			DrawFormItemHiddenTextBox("upper_limit",$graphs["upper_limit"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Lower Limit</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_lower_limit"] == "on")) { print "The minimum vertical value for the rrd graph."; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_lower_limit"] == "on")) {
			DrawFormItemTextBox("lower_limit",$graphs["lower_limit"],"","50", "40");
		}else{
			print "<td><em>" . $graphs["lower_limit"] . "</em></td>";
			DrawFormItemHiddenTextBox("lower_limit",$graphs["lower_limit"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Base Value</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_base_value"] == "on")) { print "Should be set to 1024 for memory and 1000 for traffic measurements."; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_base_value"] == "on")) {
			DrawFormItemTextBox("base_value",$graphs["base_value"],"","50", "40");
		}else{
			print "<td><em>" . $graphs["base_value"] . "</em></td>";
			DrawFormItemHiddenTextBox("base_value",$graphs["base_value"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Unit Value</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_unit_value"] == "on")) { print "(--unit) Sets the exponent value on the Y-axis for numbers. Note: This option was 
			recently added in rrdtool 1.0.36."; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_unit_value"] == "on")) {
			DrawFormItemTextBox("unit_value",$graphs["unit_value"],"","50", "40");
		}else{
			print "<td><em>" . $graphs["unit_value"] . "</em></td>";
			DrawFormItemHiddenTextBox("unit_value",$graphs["unit_value"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Unit Exponent Value</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_unit_exponent_value"] == "on")) { print "What unit cacti should use on the Y-axis. Use 3 to display everything in 'k' or -6 
			to display everything in 'u' (micro)."; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_unit_exponent_value"] == "on")) {
			DrawFormItemTextBox("unit_exponent_value",$graphs["unit_exponent_value"],"","50", "40");
		}else{
			print "<td><em>" . $graphs["unit_exponent_value"] . "</em></td>";
			DrawFormItemHiddenTextBox("unit_exponent_value",$graphs["unit_exponent_value"],"");
		}?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Vertical Label</font><br>
			<?if (($use_graph_template == false) || ($graphs_template["t_vertical_label"] == "on")) { print "The label vertically printed to the left of the graph."; }?>
		</td>
		<?if (($use_graph_template == false) || ($graphs_template["t_vertical_label"] == "on")) {
			DrawFormItemTextBox("vertical_label",$graphs["vertical_label"],"","50", "40");
		}else{
			print "<td><em>" . $graphs["vertical_label"] . "</em></td>";
			DrawFormItemHiddenTextBox("vertical_label",$graphs["vertical_label"],"");
		}?>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("graph_template_graph_id",$graphs["id"]);
	DrawFormItemHiddenIDField("local_graph_id",$graphs["local_graph_id"]);
	DrawFormItemHiddenIDField("order_key",$graphs["order_key"]);
	DrawFormItemHiddenIDField("local_graph_template_graph_id",$graphs["local_graph_template_graph_id"]);
	DrawFormItemHiddenIDField("_graph_template_id",$graphs["graph_template_id"]);
	DrawFormItemHiddenTextBox("save_component_graph","1","");
	
	end_box();
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "graphs.php");?>
		</td>
	</tr>
	</form>
	<?
	end_box();
}

function graph() {
	global $colors;
	
	start_box("<strong>Graph Management</strong>", "98%", $colors["header"], "3", "center", "graphs.php?action=graph_edit");
	
	?>
	<tr bgcolor="<?print $colors["panel"];?>">
		<form name="form_graph_id">
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="100">
						Filter by host:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
							<option value="data_sources.php?host_id=0"<?if ($_GET["host_id"] == "0") {?> selected<?}?>>None</option>
							
							<?
							$hosts = db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname");
							
							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='data_sources.php?host_id=" . $host["id"] . "'"; if ($_GET["host_id"] == $host["id"]) { print " selected"; } print ">" . $host["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="5"></td>
					<td width="1">
						<input type="text" name="filter" size="20">
					</td>
					<td>
						&nbsp;<a href="data_sources.php<?print $main_action;?>"><img src="images/button_go.gif" alt="Go" border="0" align="absmiddle"></a><br>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>	
	<?
	
	end_box();
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Graph Title",$colors["header_text"],1);
		DrawMatrixHeaderItem("Template Name",$colors["header_text"],1);
		DrawMatrixHeaderItem("Size",$colors["header_text"],2);
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
		DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="graphs.php?action=graph_edit&local_graph_id=<?print $graph["local_graph_id"];?>"><?print $graph["title"];?></a>
			</td>
			<td>
				<?if ($graph["name"] == "") { print "<em>None</em>"; }else{ print $graph["name"]; }?>
			</td>
			<td>
				<?print $graph["height"];?>x<?print $graph["width"];?>
			</td>
			<td width="1%" align="right">
				<a href="graphs.php?action=graph_remove&local_graph_id=<?print $graph["local_graph_id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	$i++;
	}
	}
	end_box();
}

?>