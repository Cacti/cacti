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

include_once ("include/form.php");
include_once ("include/config_arrays.php");

switch ($_REQUEST["action"]) {
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;
	case 'gprint_presets_remove':
		if ((read_config_option("remove_verification") == "on") && ($_GET["confirm"] != "yes")) {
			include_once ('include/top_header.php');
			DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the GPRINT preset <strong>'" . db_fetch_cell("select name from graph_templates_gprint where id=" . $_GET["gprint_preset_id"]) . "'</strong>? This could affect every graph that uses this preset, make sure you know what you are doing first!", getenv("HTTP_REFERER"), "graph_templates.php?action=gprint_presets_remove&gprint_preset_id=" . $_GET["gprint_preset_id"]);
			exit;
		}
		
		gprint_presets_remove();
		
		header ("Location: graph_templates.php?action=gprint_presets");
		break;
	case 'gprint_presets_edit':
		include_once ("include/top_header.php");
		
		gprint_presets_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'gprint_presets':
		include_once ("include/top_header.php");
		
		gprint_presets();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'item_presets_item_remove':
		item_remove(); /* from graph items */
		
		header ("Location: graph_templates.php?action=item_presets_edit&graph_template_id=" . $_GET["graph_template_id"]);
		break;
	case 'item_presets_item_edit':
		include_once ("include/top_header.php");
		
		item_presets_item_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'item_presets_remove':
		if ((read_config_option("remove_verification") == "on") && ($_GET["confirm"] != "yes")) {
			include_once ('include/top_header.php');
			DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the item preset <strong>'" . db_fetch_cell("select name from graph_templates where id=" . $_GET["graph_template_id"]) . "'</strong>?", getenv("HTTP_REFERER"), "graph_templates.php?action=item_presets_remove&graph_template_id=" . $_GET["graph_template_id"]);
			exit;
		}
		
		template_remove();
		
		header ("Location: graph_templates.php?action=item_presets");
		break;
	case 'item_presets_edit':
		include_once ("include/top_header.php");
		
		item_presets_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'item_presets':
		include_once ("include/top_header.php");
		
		item_presets();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'template_remove':
		template_remove();
		
		header ("Location: graph_templates.php");
		break;
	case 'input_remove':
		input_remove();
		
		header ("Location: " . getenv("HTTP_REFERER"));
		break;
	case 'item_remove':
		item_remove();
		
		header ("Location: " . getenv("HTTP_REFERER"));
		break;
	case 'input_edit':
		include_once ("include/top_header.php");
		
		input_edit();
		
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
	case 'item_edit':
		include_once ("include/top_header.php");
		
		item_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'item':
		include_once ("include/top_header.php");
		
		item();
		
		include_once ("include/bottom_footer.php");
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

function draw_graph_form_select($main_action) { 
	global $colors; ?>
	<tr bgcolor="<?print $colors["panel"];?>">
		<form name="form_graph_id">
		<td colspan="6">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="1%">
						<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
							<option value="graph_templates.php?action=template_edit&graph_template_id=<?print $_GET["graph_template_id"];?>"<?if (strstr($_GET["action"],"template")) {?> selected<?}?>>Graph Template Configuration</option>
							<option value="graph_templates.php?action=item&graph_template_id=<?print $_GET["graph_template_id"];?>"<?if ((strstr($_GET["action"],"item")) || (strstr($_GET["action"],"input"))) {?> selected<?}?>>Graph Item Template Configuration</option>
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

function draw_tabs() {
	global $action;
	?>
	<table height="20" cellspacing="0" cellpadding="0" width="98%" align="center">
		<tr>
			<td valign="bottom">
				<?if ($action != "") {?><a href="graph_templates.php"><?}?><img src="images/tab_con_graph_templates<?if ((strstr($action,"template") == true) || (empty($action)) || ($action == "item_edit")) { print "_down"; }?>.gif" alt="Data Sources" border="0" align="absmiddle"><?if ($action != "") {?></a><?}?>
				<?if ($action != "item_presets") {?><a href="graph_templates.php?action=item_presets"><?}?><img src="images/tab_con_graph_item_presets<?if (strstr($action,"item_presets") == true) { print "_down"; }?>.gif" alt="Data Source Tree" border="0" align="absmiddle"><?if ($action != "item_presets") {?></a><?}?>
				<?if ($action != "gprint_presets") {?><a href="graph_templates.php?action=gprint_presets"><?}?><img src="images/tab_con_gprint_presets<?if (strstr($action,"gprint_presets") == true) { print "_down"; }?>.gif" alt="Data Source Tree" border="0" align="absmiddle"><?if ($action != "gprint_presets") {?></a><?}?>
			</td>
		</tr>
	</table>
	<?	
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $config;
	
	if (isset($_POST["save_component_template"])) {
		template_save();
		return "graph_templates.php";
	}elseif (isset($_POST["save_component_item"])) {
		item_save();

		if (read_config_option("full_view_graph_template") == "") {
			return "graph_templates.php?action=item&graph_template_id=" . $_POST["graph_template_id"];
		}elseif (read_config_option("full_view_graph_template") == "on") {
			return "graph_templates.php?action=template_edit&graph_template_id=" . $_POST["graph_template_id"];
		}
	}elseif (isset($_POST["save_component_input"])) {
		input_save();
		
		if (read_config_option("full_view_graph_template") == "") {
			return "graph_templates.php?action=item&graph_template_id=" . $_POST["graph_template_id"];
		}elseif (read_config_option("full_view_graph_template") == "on") {
			return "graph_templates.php?action=template_edit&graph_template_id=" . $_POST["graph_template_id"];
		}
	}elseif (isset($_POST["save_component_item_presets"])) {
		item_presets_save();
		return "graph_templates.php?action=item_presets";
	}elseif (isset($_POST["save_component_item_presets_item"])) {
		item_save(); /* from graph items */
		return "graph_templates.php?action=item_presets_edit&graph_template_id=" . $_POST["graph_template_id"];
	}elseif (isset($_POST["save_component_gprint_presets"])) {
		gprint_presets_save();
		return "graph_templates.php?action=gprint_presets";
	}
}

/* ------------------------------
    Shared Graph Item Functions
   ------------------------------ */

function draw_graph_items($template_item_list, $action_prefix) {
	global $colors, $consolidation_functions, $graph_item_types;
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Graph Item",$colors["header_text"],1);
		DrawMatrixHeaderItem("Task Name",$colors["header_text"],1);
		DrawMatrixHeaderItem("Graph Item Type",$colors["header_text"],1);
		DrawMatrixHeaderItem("CF Type",$colors["header_text"],1);
		DrawMatrixHeaderItem("Item Color",$colors["header_text"],4);
	print "</tr>";
	
	$group_counter = 0;
	if (sizeof($template_item_list) > 0) {
	foreach ($template_item_list as $item) {
		$bold_this_row = false; $use_custom_row_color = false;
		
		if (($graph_item_types{$item["graph_type_id"]} != "GPRINT") && ($graph_item_types{$item["graph_type_id"]} != $_graph_type_name)) {
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
		
		$_graph_type_name = $graph_item_types{$item["graph_type_id"]};
		
		if ($use_custom_row_color == false) { DrawMatrixRowAlternateColorBegin($alternate_color_1,$alternate_color_2,$i); }else{ print "<tr bgcolor=\"#$custom_row_color\">"; } ?>
			<td>
				<a class="linkEditMain" href="graph_templates.php?action=<?print $action_prefix;?>_edit&graph_template_item_id=<?print $item["id"];?>&graph_template_id=<?print $_GET["graph_template_id"];?>">Item # <?print ($i+1);?></a>
			</td>
			<?
			if ($item["data_source_name"] == "") { $item["data_source_name"] = "No Task"; }
			
			switch ($graph_item_types{$item["graph_type_id"]}) {
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
			    $matrix_title = $item["data_source_name"];
			    break;
			}
			
			/* use the cdef name (if in use) if all else fails */
			if ($matrix_title == "") {
			    if ($item["cdef_name"] != "") {
				$matrix_title .= "CDEF: " . $item["cdef_name"];
			    }
			}
			
			$hard_return = "";
			if ($item["hard_return"] == "on") {
			    $hard_return = "<strong><font color=\"#FF0000\">&lt;HR&gt;</font></strong>";
			}
			
			?>
			<td>
				<?if ($bold_this_row == true) { print "<strong>"; }?><?print htmlspecialchars($matrix_title) . $hard_return;?><?if ($bold_this_row == true) { print "</strong>"; }?>
			</td>
			<td>
				<?if ($bold_this_row == true) { print "<strong>"; }?><?print $graph_item_types{$item["graph_type_id"]};?><?if ($bold_this_row == true) { print "</strong>"; }?>
			</td>
			<td>
				<?if ($bold_this_row == true) { print "<strong>"; }?><?print $consolidation_functions{$item["consolidation_function_id"]};?><?if ($bold_this_row == true) { print "</strong>"; }?>
			</td>
			<td<?if ($item["hex"] != "") { print ' bgcolor="#' .  $item["hex"] . '"'; }?> width="1%">
				&nbsp;
			</td>
			<td>
				<?if ($bold_this_row == true) { print "<strong>"; }?><?print $item["hex"];?><?if ($bold_this_row == true) { print "</strong>"; }?>
			</td>
			<td>
				<a href="graph_templates.php?action=item_movedown&graph_template_item_id=<?print $item["id"];?>&graph_template_id=<?print $_GET["graph_template_id"];?>"><img src="images/move_down.gif" border="0" alt="Move Down"></a>
				<a href="graph_templates.php?action=item_moveup&graph_template_item_id=<?print $item["id"];?>&graph_template_id=<?print $_GET["graph_template_id"];?>"><img src="images/move_up.gif" border="0" alt="Move Up"></a>
			</td>
			<td width="1%" align="right">
				<a href="graph_templates.php?action=<?print $action_prefix;?>_remove&graph_template_item_id=<?print $item["id"];?>&graph_template_id=<?print $_GET["graph_template_id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	$i++;
	}
	}else{
		DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
			<td colspan="7">
				<em>No Items</em>
			</td>
		</tr><?
	}	
}

function draw_item_edit() {
	global $colors, $struct_graph_item, $graph_item_types, $consolidation_functions;
	
	if (isset($_GET["graph_template_item_id"])) {
		$template_item = db_fetch_row("select * from graph_templates_item where id=" . $_GET["graph_template_item_id"]);
	}else{
		unset($template_item);
	}
	
	/* get current graph name for the header text */
	$graph_parameters = db_fetch_row("select title,grouping from graph_templates_graph where id=" . $_GET["graph_template_id"]);
	
	?>
	<form method="post" action="graph_templates.php">
	<?
	/* by default, select the LAST DS chosen to make everyone's lives easier */
	$default = db_fetch_row("select task_item_id from graph_templates_item where graph_template_id=" . $_GET["graph_template_id"] . " order by sequence_parent DESC,sequence DESC");

	if (sizeof($default) > 0) {
		$default_item = $default["task_item_id"];
	}else{
		$default_item = 0;
	}
	
	while (list($field_name, $field_array) = each($struct_graph_item)) {
		DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
		
		print "<td width='50%'><font class='textEditTitle'>" . $field_array["title"] . "</font><br>\n";
		
		if (($use_graph_template == false) || ($template_item_template{"t_" . $field_name} == "on")) {
			print $field_array["description"];
		}
		
		print "</td>\n";
		
		draw_nontemplated_item($field_array, $field_name, $template_item[$field_name]);
		
		print "</tr>\n";
	}
	
	DrawFormItemHiddenIDField("graph_template_item_id",$_GET["graph_template_item_id"]);
	DrawFormItemHiddenIDField("graph_template_id",$_GET["graph_template_id"]);
	DrawFormItemHiddenIDField("sequence",$template_item["sequence"]);
	DrawFormItemHiddenIDField("_graph_type_id",$template_item["graph_type_id"]);
}

/* -----------------------------------
    gprint_presets - GPRINT Presets 
   ----------------------------------- */

function gprint_presets_remove() {
	db_execute("delete from graph_templates_gprint where id=" . $_GET["gprint_preset_id"]);
}

function gprint_presets_save() {
	$save["id"] = $_POST["gprint_preset_id"];
	$save["name"] = $_POST["name"];
	$save["gprint_text"] = $_POST["gprint_text"];
	
	sql_save($save, "graph_templates_gprint");
}
   
function gprint_presets_edit() {
	global $colors;
	
	if (isset($_GET["gprint_preset_id"])) {
		$gprint_preset = db_fetch_row("select * from graph_templates_gprint where id=" . $_GET["gprint_preset_id"]);
	}else{
		unset($gprint_preset);
	}
	
	draw_tabs();
	start_box("<strong>GPRINT Presets [edit]</strong>", "98%", $colors["header"], "3", "center", "");
	
	?>
	<form method="post" action="graph_templates.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			Enter a name for this GPRINT preset, make sure it is something you recognize.
		</td>
		<?DrawFormItemTextBox("name",$gprint_preset["name"],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">GPRINT Text</font><br>
			Enter the custom GPRINT string here.
		</td>
		<?DrawFormItemTextBox("gprint_text",$gprint_preset["gprint_text"],"","50", "40");?>
	</tr>
	
	<?
	end_box();
	
	DrawFormItemHiddenIDField("gprint_preset_id",$gprint_preset["id"]);
	DrawFormItemHiddenTextBox("save_component_gprint_presets","1","");
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "graph_templates.php?action=gprint_presets");?>
		</td>
	</tr>
	</form>
	<?
	end_box();
}
   
function gprint_presets() {
	global $colors;
	
	draw_tabs();
	start_box("<strong>GPRINT Presets</strong>", "98%", $colors["header"], "3", "center", "graph_templates.php?action=gprint_presets_edit");
	
	print "<tr bgcolor='#" . $colors["panel"] . "'>";
		DrawMatrixHeaderItem("GPRINT Preset Title",$colors["panel_text"],2);
	print "</tr>";
	
	$template_list = db_fetch_assoc("select 
		graph_templates_gprint.id,
		graph_templates_gprint.name 
		from graph_templates_gprint");
	
	if (sizeof($template_list) > 0) {
	foreach ($template_list as $template) {
		DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="graph_templates.php?action=gprint_presets_edit&gprint_preset_id=<?print $template["id"];?>"><?print $template["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="graph_templates.php?action=gprint_presets_remove&gprint_preset_id=<?print $template["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
		<?
		$i++;
	}
	}else{
		DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
			<td colspan="2">
				<em>No Items</em>
			</td>
		</tr><?
	}
	end_box();	
}

/* -----------------------------------
    item_presets - Graph Item Presets 
   ----------------------------------- */

function item_presets() {
	global $colors;
	
	draw_tabs();
	start_box("<strong>Graph Item Presets</strong>", "98%", $colors["header"], "3", "center", "graph_templates.php?action=item_presets_edit");
	
	print "<tr bgcolor='#" . $colors["panel"] . "'>";
		DrawMatrixHeaderItem("Item Preset Title",$colors["panel_text"],2);
	print "</tr>";
	
	$template_list = db_fetch_assoc("select 
		graph_templates.id,graph_templates.name 
		from graph_templates
		where graph_templates.type='item_preset'");
	
	if (sizeof($template_list) > 0) {
	foreach ($template_list as $template) {
		DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="graph_templates.php?action=item_presets_edit&graph_template_id=<?print $template["id"];?>"><?print $template["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="graph_templates.php?action=item_presets_remove&graph_template_id=<?print $template["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
		<?
		$i++;
	}
	}else{
		DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
			<td colspan="2">
				<em>No Items</em>
			</td>
		</tr><?
	}
	end_box();	
}

function item_presets_save() {
	$save["id"] = $_POST["graph_template_id"];
	$save["name"] = $_POST["name"];
	$save["type"] = "item_preset";
	
	sql_save($save, "graph_templates");
}

function item_presets_edit() {
	global $colors;
	
	if (isset($_GET["graph_template_id"])) {
		$item_preset = db_fetch_row("select * from graph_templates where id=" . $_GET["graph_template_id"]);
	}else{
		unset($item_preset);
	}
	
	draw_tabs();
	start_box("<strong>Graph Item Presets</strong> [edit]", "98%", $colors["header"], "3", "center", "");
	?>
	<form method="post" action="graph_templates.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			Enter a name for this GPRINT preset, make sure it is something you recognize.
		</td>
		<?DrawFormItemTextBox("name",$item_preset["name"],"","50", "40");?>
	</tr>
	<?
	end_box();
	
	$template_item_list = db_fetch_assoc("select
		graph_templates_item.id,
		graph_templates_item.text_format,
		graph_templates_item.value,
		graph_templates_item.hard_return,
		graph_templates_item.graph_type_id,
		graph_templates_item.consolidation_function_id,
		CONCAT_WS(' - ',data_template_data.name,data_template_rrd.data_source_name) as data_source_name,
		cdef.name as cdef_name,
		colors.hex
		from graph_templates_item
		left join data_template_rrd on graph_templates_item.task_item_id=data_template_rrd.id
		left join data_local on data_template_rrd.local_data_id=data_local.id
		left join data_template_data on data_local.id=data_template_data.local_data_id
		left join cdef on cdef_id=cdef.id
		left join colors on color_id=colors.id
		where graph_templates_item.graph_template_id=" . $_GET["graph_template_id"] . "
		and graph_templates_item.local_graph_id=0
		order by graph_templates_item.sequence");
	
	start_box("<strong>Graph Item Presets Configuration</strong>", "98%", $colors["header"], "3", "center", "graph_templates.php?action=item_presets_item_edit&graph_template_id=" . $_GET["graph_template_id"]);
	draw_graph_items($template_item_list, "item_presets_item");
	end_box();
	
	DrawFormItemHiddenIDField("graph_template_id",$_GET["graph_template_id"]);
	DrawFormItemHiddenTextBox("save_component_item_presets","1","");
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "graph_templates.php?action=item_presets");?>
		</td>
	</tr>
	</form>
	<?
	end_box();
}

function item_presets_item_edit() {
	global $config, $colors;
	
	draw_tabs();
	start_box("<strong>Graph Item Presets</strong> - Item [edit]", "98%", $colors["header"], "3", "center", "");
	draw_item_edit();
	end_box();
	
	DrawFormItemHiddenTextBox("save_component_item_presets_item","1","");
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "graph_templates.php?action=item_presets_edit&graph_template_id=" . $_GET["graph_template_id"]);?>
		</td>
	</tr>
	</form>
	<?
	end_box();
}


/* -----------------------
    item - Graph Items 
   ----------------------- */

function item_movedown() {
	include_once ("include/functions.php");
	include_once ("include/utility_functions.php");
	
	/* this is so we know the "other" graph item to propagate the changes to */
	$next_item = get_next_item("graph_templates_item", "sequence", $_GET["graph_template_item_id"], "graph_template_id=" . $_GET["graph_template_id"] . " and local_graph_id=0");
	
	move_item_down("graph_templates_item", $_GET["graph_template_item_id"], "graph_template_id=" . $_GET["graph_template_id"] . " and local_graph_id=0");	
	
	/* propagate sequence changes to child graphs */
	push_out_graph_item($_GET["graph_template_item_id"]);
	push_out_graph_item($next_item);
}

function item_moveup() {
	include_once ("include/functions.php");
	include_once ("include/utility_functions.php");
	
	/* this is so we know the "other" graph item to propagate the changes to */
	$last_item = get_last_item("graph_templates_item", "sequence", $_GET["graph_template_item_id"], "graph_template_id=" . $_GET["graph_template_id"] . " and local_graph_id=0");
	
	move_item_up("graph_templates_item", $_GET["graph_template_item_id"], "graph_template_id=" . $_GET["graph_template_id"] . " and local_graph_id=0");	
	
	/* propagate sequence changes to child graphs */
	push_out_graph_item($_GET["graph_template_item_id"]);
	push_out_graph_item($last_item);
}

function item() {
	global $colors, $config;
	
	if (read_config_option("full_view_graph_template") == "") {
		start_box("<strong>Graph Template Management [edit]</strong>", "98%", $colors["header"], "3", "center", "");
		draw_graph_form_select("?action=item&graph_template_id=" . $_GET["graph_template_id"]);
		end_box();
	}
	
	$template_item_list = db_fetch_assoc("select
		graph_templates_item.id,
		graph_templates_item.text_format,
		graph_templates_item.value,
		graph_templates_item.hard_return,
		graph_templates_item.graph_type_id,
		graph_templates_item.consolidation_function_id,
		CONCAT_WS(' - ',data_template_data.name,data_template_rrd.data_source_name) as data_source_name,
		cdef.name as cdef_name,
		colors.hex
		from graph_templates_item
		left join data_template_rrd on graph_templates_item.task_item_id=data_template_rrd.id
		left join data_local on data_template_rrd.local_data_id=data_local.id
		left join data_template_data on data_local.id=data_template_data.local_data_id
		left join cdef on cdef_id=cdef.id
		left join colors on color_id=colors.id
		where graph_templates_item.graph_template_id=" . $_GET["graph_template_id"] . "
		and graph_templates_item.local_graph_id=0
		order by graph_templates_item.sequence");
	
	start_box("Graph Template Item Configuration", "98%", $colors["header"], "3", "center", "graph_templates.php?action=item_edit&graph_template_id=" . $_GET["graph_template_id"]);
	draw_graph_items($template_item_list, "item");
	end_box();
	
	start_box("Graph Template User Input Configuration", "98%", $colors["header"], "3", "center", "graph_templates.php?action=input_edit&graph_template_id=" . $_GET["graph_template_id"]);
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Name",$colors["header_text"],2);
	print "</tr>";
	
	$template_item_list = db_fetch_assoc("select id,name from graph_template_input where graph_template_id=" . $_GET["graph_template_id"] . " order by name");
	
	if (sizeof($template_item_list) > 0) {
	foreach ($template_item_list as $item) {
		DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i);
	?>
			<td>
				<a class="linkEditMain" href="graph_templates.php?action=input_edit&graph_template_input_id=<?print $item["id"];?>&graph_template_id=<?print $_GET["graph_template_id"];?>"><?print $item["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="graph_templates.php?action=input_remove&graph_template_input_id=<?print $item["id"];?>&graph_template_id=<?print $_GET["graph_template_id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	$i++;
	}
	}else{
		DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
			<td colspan="2">
				<em>No Inputs</em>
			</td>
		</tr><?
	}
	
	end_box();
}

function item_remove() {
	db_execute("delete from graph_templates_item where id=" . $_GET["graph_template_item_id"]);
}

function item_save() {
	include_once ("include/utility_functions.php");
	
	$save["id"] = $_POST["graph_template_item_id"];
	$save["graph_template_id"] = $_POST["graph_template_id"];
	$save["local_graph_id"] = 0;
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
	
	push_out_graph_item($graph_template_item_id);
}

function item_edit() {
	global $config, $colors;
	
	draw_tabs();
	
	if (read_config_option("full_view_graph_template") == "") {
		start_box("<strong>Graph Template Management [edit]</strong>", "98%", $colors["header"], "3", "center", "");
		draw_graph_form_select("?action=item&graph_template_id=" . $_GET["graph_template_id"]);
		end_box();
	}
	
	start_box("Template Item Configuration", "98%", $colors["header"], "3", "center", "");
	draw_item_edit();
	end_box();
	
	DrawFormItemHiddenTextBox("save_component_item","1","");
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "graph_templates.php?action=template_edit&graph_template_id=" . $_GET["graph_template_id"]);?>
		</td>
	</tr>
	</form>
	<?
	end_box();
}

/* ----------------------------
    template - Graph Templates 
   ---------------------------- */

function template_remove() {
	global $config;
	
	if ((read_config_option("remove_verification") == "on") && ($_GET["confirm"] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the graph template <strong>'" . db_fetch_cell("select name from graph_templates where id=" . $_GET["graph_template_id"]) . "'</strong>? This is generally not a good idea if you have graphs attached to this template even though it should not affect any graphs.", getenv("HTTP_REFERER"), "graph_templates.php?action=template_remove&graph_template_id=" . $_GET["graph_template_id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || ($_GET["confirm"] == "yes")) {
		db_execute("delete from graph_templates where id=" . $_GET["graph_template_id"]);
		
		$graph_template_input = db_fetch_assoc("select id from graph_template_input where graph_template_id=" . $_GET["graph_template_id"]);
		
		if (sizeof($graph_template_input) > 0) {
		foreach ($graph_template_input as $item) {
			db_execute("delete from graph_template_input_defs where graph_template_input_id=" . $item["id"]);
		}
		}
		
		db_execute("delete from graph_template_input where graph_template_id=" . $_GET["graph_template_id"]);
		db_execute("delete from graph_templates_graph where graph_template_id=" . $_GET["graph_template_id"] . " and local_graph_id=0");
		db_execute("delete from graph_templates_item where graph_template_id=" . $_GET["graph_template_id"] . " and local_graph_id=0");
		
		/* "undo" any graph that is currently using this template */
		db_execute("update graph_templates_graph set local_graph_template_graph_id=0,graph_template_id=0 where graph_template_id=" . $_GET["graph_template_id"]);
		db_execute("update graph_templates_item set local_graph_template_item_id=0,graph_template_id=0 where graph_template_id=" . $_GET["graph_template_id"]);
	}	
}

function template_save() {
	if ($_POST["upper_limit"] == "") { $_POST["upper_limit"] = 0; }
	if ($_POST["lower_limit"] == "") { $_POST["lower_limit"] = 0; }
	if ($_POST["unit_exponent_value"] == "") { $_POST["unit_exponent_value"] = 0; }
	
	$save["id"] = $_POST["graph_template_id"];
	$save["type"] = "template";
	$save["name"] = $_POST["name"];
	
	$graph_template_id = sql_save($save, "graph_templates");
	unset ($save);
	
	$save["id"] = $_POST["graph_template_graph_id"];
	$save["local_graph_template_graph_id"] = 0;
	$save["local_graph_id"] = 0;
	$save["graph_template_id"] = $graph_template_id;
	$save["t_image_format_id"] = $_POST["t_image_format_id"];
	$save["image_format_id"] = $_POST["image_format_id"];
	$save["t_title"] = $_POST["t_title"];
	$save["title"] = $_POST["title"];
	$save["t_height"] = $_POST["t_height"];
	$save["height"] = $_POST["height"];
	$save["t_width"] = $_POST["t_width"];
	$save["width"] = $_POST["width"];
	$save["t_upper_limit"] = $_POST["t_upper_limit"];
	$save["upper_limit"] = $_POST["upper_limit"];
	$save["t_lower_limit"] = $_POST["t_lower_limit"];
	$save["lower_limit"] = $_POST["lower_limit"];
	$save["t_vertical_label"] = $_POST["t_vertical_label"];
	$save["vertical_label"] = $_POST["vertical_label"];
	$save["t_auto_scale"] = $_POST["t_auto_scale"];
	$save["auto_scale"] = $_POST["auto_scale"];
	$save["t_auto_scale_opts"] = $_POST["t_auto_scale_opts"];
	$save["auto_scale_opts"] = $_POST["auto_scale_opts"];
	$save["t_auto_scale_log"] = $_POST["t_auto_scale_log"];
	$save["auto_scale_log"] = $_POST["auto_scale_log"];
	$save["t_auto_scale_rigid"] = $_POST["t_auto_scale_rigid"];
	$save["auto_scale_rigid"] = $_POST["auto_scale_rigid"];
	$save["t_auto_padding"] = $_POST["t_auto_padding"];
	$save["auto_padding"] = $_POST["auto_padding"];
	$save["t_base_value"] = $_POST["t_base_value"];
	$save["base_value"] = $_POST["base_value"];
	$save["t_grouping"] = $_POST["t_grouping"];
	$save["grouping"] = $_POST["grouping"];
	$save["t_export"] = $_POST["t_export"];
	$save["export"] = $_POST["export"];
	$save["t_unit_value"] = $_POST["t_unit_value"];
	$save["unit_value"] = $_POST["unit_value"];
	$save["t_unit_exponent_value"] = $_POST["t_unit_exponent_value"];
	$save["unit_exponent_value"] = $_POST["unit_exponent_value"];
	
	$graph_template_graph_id = sql_save($save, "graph_templates_graph");
	
	include_once ("include/utility_functions.php");
	push_out_graph($graph_template_graph_id);
}

function template_edit() {
	global $colors, $struct_graph, $image_types;
	
	draw_tabs();
	
	if (read_config_option("full_view_graph_template") == "") {
		start_box("<strong>Graph Template Management [edit]</strong>", "98%", $colors["header"], "3", "center", "");
		draw_graph_form_select("?action=template_edit&graph_template_id=" . $_GET["graph_template_id"]);
		end_box();
	}
	
	if (isset($_GET["graph_template_id"])) {
		$template = db_fetch_row("select * from graph_templates where id=" . $_GET["graph_template_id"]);
		$template_graph = db_fetch_row("select * from graph_templates_graph where graph_template_id=" . $_GET["graph_template_id"] . " and local_graph_id=0");
	}else{
		unset($template);
		unset($template_graph);
	}
	
	if (read_config_option("full_view_graph_template") == "on") {
		item();	
	}
	
	start_box("Template Configuration", "98%", $colors["header"], "3", "center", "");
	?>
	
	<form method="post" action="graph_templates.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			The name given to this graph template.
		</td>
		<?DrawFormItemTextBox("name",$template["name"],"","50", "40");?>
	</tr>
	
	<?
	end_box();
	start_box("Graph Template Configuration", "98%", $colors["header"], "3", "center", "");
	
	while (list($field_name, $field_array) = each($struct_graph)) {
		DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
		
		print "<td width='50%'><font class='textEditTitle'>" . $field_array["title"] . "</font><br>\n";
		DrawStrippedFormItemCheckBox("t_" . $field_name,$template_graph{"t_" . $field_name},"Use Per-Graph Value (Ignore this Value)","",false);
		print "</td>\n";
		
		draw_nontemplated_item($field_array, $field_name, $template_graph[$field_name]);
		
		print "</tr>\n";
	}
	
	end_box();
	
	DrawFormItemHiddenIDField("graph_template_id",$_GET["graph_template_id"]);
	DrawFormItemHiddenIDField("graph_template_graph_id",$template_graph["id"]);
	DrawFormItemHiddenTextBox("save_component_template","1","");
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "graph_templates.php");?>
		</td>
	</tr>
	</form>
	<?
	end_box();
}

function template() {
	global $colors;
	
	draw_tabs();
	start_box("<strong>Graph Template Management</strong>", "98%", $colors["header"], "3", "center", "graph_templates.php?action=template_edit");
	
	print "<tr bgcolor='#" . $colors["panel"] . "'>";
		DrawMatrixHeaderItem("Template Title",$colors["panel_text"],2);
	print "</tr>";
	
	$template_list = db_fetch_assoc("select 
		graph_templates.id,graph_templates.name 
		from graph_templates
		where graph_templates.type='template'");
       
	if (sizeof($template_list) > 0) {
	foreach ($template_list as $template) {
		DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="graph_templates.php?action=template_edit&graph_template_id=<?print $template["id"];?>"><?print $template["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="graph_templates.php?action=template_remove&graph_template_id=<?print $template["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
		<?
		$i++;
	}
	}
	end_box();
}

/* ------------------------------------
    input - Graph Template Item Inputs 
   ------------------------------------ */

function input_remove() {
	global $config;
	
	if ((read_config_option("remove_verification") == "on") && ($_GET["confirm"] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the input item <strong>'" . db_fetch_cell("select name from graph_template_input where id=" . $_GET["graph_template_input_id"]) . "'</strong>? NOTE: Deleting this item will NOT affect graphs that use this template.", getenv("HTTP_REFERER"), "graph_templates.php?action=input_remove&graph_template_input_id=" . $_GET["graph_template_input_id"] . "&graph_template_id=" . $_GET["graph_template_id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || ($_GET["confirm"] == "yes")) {
		db_execute("delete from graph_template_input where id=" . $_GET["graph_template_input_id"]);
		db_execute("delete from graph_template_input_defs where graph_template_input_id=" . $_GET["graph_template_input_id"]);
	}
}

function input_save() {
	$save["id"] = $_POST["graph_template_input_id"];
	$save["graph_template_id"] = $_POST["graph_template_id"];
	$save["name"] = $_POST["name"];
	$save["description"] = $_POST["description"];
	$save["column_name"] = $_POST["column_name"];
	
	$graph_template_input_id = sql_save($save, "graph_template_input");
	
	db_execute("delete from graph_template_input_defs where graph_template_input_id=$graph_template_input_id");
	
	while (list($var, $val) = each($_POST)) {
		if (eregi("^i_", $var)) {
			$var = str_replace("i_", "", $var);
			
			unset($save);
			$save["graph_template_input_id"] = $graph_template_input_id;
			$save["graph_template_item_id"] = $var;
			
			sql_save($save, "graph_template_input_defs");
			
		}
	}
}

function input_edit() {
	global $colors, $consolidation_functions, $graph_item_types;
	
	draw_tabs();
	
	if (read_config_option("full_view_graph_template") == "") {
		start_box("<strong>Graph Template Management [edit]</strong>", "98%", $colors["header"], "3", "center", "");
		draw_graph_form_select("?action=item&graph_template_id=" . $_GET["graph_template_id"]);
		end_box();
	}
	
	$graph_template_items = array(
		"color_id" => "Item Color",
		"task_item_id" => "Item Task (Data Source)",
		"graph_type_id" => "Graph Item Type",
		"cdef_id" => "CDEF",
		"consolidation_function_id" => "Consolidation Function",
		"text_format" => "Text Format",
		"value" => "Value",
		"hard_return" => "Hard Return",
		"gprint" => "GPRINT Options",
		"custom" => "Custom Field");
	
	if (isset($_GET["graph_template_input_id"])) {
		$graph_template_input = db_fetch_row("select * from graph_template_input where id=" . $_GET["graph_template_input_id"]);
	}else{
		unset($graph_template_input);
	}
	
	start_box("Graph Item Input Configuration", "98%", $colors["header"], "3", "center", "");
	
	?>
	<form method="post" action="graph_templates.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			Enter a name for this graph item input, make sure it is something you recognize.
		</td>
		<?DrawFormItemTextBox("name",$graph_template_input["name"],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Description</font><br>
			Enter a description for this graph item input to describe what this input is used for.
		</td>
		<?DrawFormItemTextArea("description",$graph_template_input["description"],5,40,"");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Field Type</font><br>
			How data is to be represented on the graph.
		</td>
		<?DrawFormItemDropdownFromSQL("column_name",$graph_template_items,"","",$graph_template_input["column_name"],"","");?>
	</tr>
	
	<?
	if (!(isset($_GET["graph_template_input_id"]))) { $_GET["graph_template_input_id"] = 0; }
	
	$item_list = db_fetch_assoc("select
		CONCAT_WS(' - ',data_template_data.name,data_template_rrd.data_source_name) as data_source_name,
		graph_templates_item.text_format,
		graph_templates_item.id as graph_templates_item_id,
		graph_templates_item.graph_type_id,
		graph_templates_item.consolidation_function_id,
		graph_template_input_defs.graph_template_input_id
		from graph_templates_item
		left join graph_template_input_defs on (graph_template_input_defs.graph_template_item_id=graph_templates_item.id and graph_template_input_defs.graph_template_input_id=" . $_GET["graph_template_input_id"] . ")
		left join data_template_rrd on graph_templates_item.task_item_id=data_template_rrd.id
		left join data_local on data_template_rrd.local_data_id=data_local.id
		left join data_template_data on data_local.id=data_template_data.local_data_id
		where graph_templates_item.local_graph_id=0
		and graph_templates_item.graph_template_id=" . $_GET["graph_template_id"] . "
		order by graph_templates_item.sequence");
	
	DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Associated Graph Items</font><br>
			Select the graph items that you want to accept user input for.
		</td>
		<td>
		<?if (sizeof($item_list) > 0) {
			foreach ($item_list as $item) {
			    if ($item["graph_template_input_id"] == "") {
				$old_value = "";
			    }else{
				$old_value = "on";
			    }
			    
			    if ($graph_item_types{$item["graph_type_id"]} == "GPRINT") {
				    $start_bold = "";
				    $end_bold = "";
			    }else{
				    $start_bold = "<strong>";
				    $end_bold = "</strong>";
			    }
			    
			    $name = "$start_bold Item #" . ($i+1) . ": " . $graph_item_types{$item["graph_type_id"]} . " (" . $consolidation_functions{$item["consolidation_function_id"]} . ")$end_bold";
			    DrawStrippedFormItemCheckBox("i_" . $item["graph_templates_item_id"], $old_value, $name,"",true);
			    
			    $i++;
			}
		}
		?>
		</td>
	</tr>
	
	<?
	end_box();
	
	DrawFormItemHiddenIDField("graph_template_id",$_GET["graph_template_id"]);
	DrawFormItemHiddenIDField("graph_template_input_id",$_GET["graph_template_input_id"]);
	DrawFormItemHiddenTextBox("save_component_input","1","");
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "graph_templates.php?action=template_edit&graph_template_id=" . $_GET["graph_template_id"]);?>
		</td>
	</tr>
	</form>
	<?
	end_box();
}

?>
