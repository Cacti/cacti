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

define("ROWS_PER_PAGE", 30);

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
		
		if (read_config_option("full_view_graph") == "") {
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
	global $colors, $consolidation_functions, $graph_item_types, $struct_graph_item;
	
	if (empty($_GET["local_graph_id"])) {
		$header_label = "[new]";
	}else{
		$header_label = "[edit: " . db_fetch_cell("select title from graph_templates_graph where local_graph_id=" . $_GET["local_graph_id"]) . "]";
	}
	
	if (read_config_option("full_view_graph") == "") {
		start_box("<strong>Graph Management</strong> $header_label", "98%", $colors["header"], "3", "center", "");
		draw_graph_form_select("?action=item&local_graph_id=" . $_GET["local_graph_id"]);
		end_box();
	}
	
	$graph_template_id = db_fetch_cell("select graph_template_id from graph_local where id=" . $_GET["local_graph_id"]);
	
	if (empty($graph_template_id)) {
		$add_text = "graphs.php?action=item_edit&local_graph_id=" . $_GET["local_graph_id"];
	}else{
		$add_text = "";
	}
	
	start_box("<strong>Graph Items</strong> $header_label", "98%", $colors["header"], "3", "center", $add_text);
	
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
		where graph_templates_item.local_graph_id=" . $_GET["local_graph_id"] . "
		order by graph_templates_item.sequence");
	
	$group_counter = 0;
	if (sizeof($template_item_list) > 0) {
	foreach ($template_item_list as $item) {
		/* graph grouping display logic */
		$this_row_style = ""; $use_custom_row_color = false; $hard_return = "";
		
		if (($graph_item_types{$item["graph_type_id"]} != "GPRINT") && ($graph_item_types{$item["graph_type_id"]} != $_graph_type_name)) {
			$this_row_style = "font-weight: bold;"; $use_custom_row_color = true;
			
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
		
		/* alternating row color */
		if ($use_custom_row_color == false) {
			DrawMatrixRowAlternateColorBegin($alternate_color_1,$alternate_color_2,$i);
		}else{
			print "<tr bgcolor='#$custom_row_color'>";
		}
		
		print "<td>";
		if (empty($graph_template_id)) { print "<a href='graphs.php?action=item_edit&graph_template_item_id=" . $item["id"] . "&local_graph_id=" . $_GET["local_graph_id"] . "'>"; }
		print "<strong>Item # " . ($i+1) . "</strong>";
		if (empty($graph_template_id)) { print "</a>"; }
		print "</td>\n";
		
		if (empty($item["data_source_name"])) { $item["data_source_name"] = "No Task"; }
		
		switch (true) {
		case ereg("(AREA|STACK|GPRINT|LINE[123])", $_graph_type_name):
			$matrix_title = "(" . $item["data_source_name"] . "): " . $item["text_format"];
			break;
		case ereg("(HRULE|VRULE)", $_graph_type_name):
			$matrix_title = "HRULE: " . $item["value"];
			break;
		case ereg("(COMMENT)", $_graph_type_name):
			$matrix_title = "COMMENT: " . $item["text_format"];
			break;
		}
		
		if ($item["hard_return"] == "on") {
			$hard_return = "<strong><font color=\"#FF0000\">&lt;HR&gt;</font></strong>";
		}
		
		print "<td style='$this_row_style'>" . htmlspecialchars($matrix_title) . $hard_return . "</td>\n";
		print "<td style='$this_row_style'>" . $graph_item_types{$item["graph_type_id"]} . "</td>\n";
		print "<td style='$this_row_style'>" . $consolidation_functions{$item["consolidation_function_id"]} . "</td>\n";
		print "<td" . ((!empty($item["hex"])) ? " bgcolor='#" . $item["hex"] . "'" : "") . " width='1%'>&nbsp;</td>\n";
		print "<td style='$this_row_style'>" . $item["hex"] . "</td>\n";
		print "<td><a href='graph_templates.php?action=item_movedown&graph_template_item_id=" . $item["id"] . "&local_graph_id=" . $_GET["local_graph_id"] . "'><img src='images/move_down.gif' border='0' alt='Move Down'></a>
		       	   <a href='graph_templates.php?action=item_moveup&graph_template_item_id=" . $item["id"] . "&local_graph_id=" . $_GET["local_graph_id"] . "'><img src='images/move_up.gif' border='0' alt='Move Up'></a></td>\n";
		print "<td width='1%' align='right'><a href='graph_templates.php?action=item_remove&graph_template_item_id=" . $item["id"] . "&local_graph_id=" . $_GET["local_graph_id"] . "'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a>&nbsp;</td>\n";
		
		print "</tr>";
		
		$i++;
	}
	}else{
		print "<tr bgcolor='#" . $colors["form_alternate2"] . "'><td><td colspan='2'>No Items</em></td></tr>";
	}
	end_box();
	
	/* only display the "inputs" area if we are using a graph template for this graph */
	if ($graph_template_id != "0") {
		start_box("<strong>Graph Item Inputs</strong>", "98%", $colors["header"], "3", "center", "");
		
		print "<form method='post' action='graphs.php'>\n";
		
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
			
			$field_name = $item["column_name"];
			
			DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
			
			print "	<td width='50%'>
					<font class='textEditTitle'>" . $item["name"] . "</font>";
					if (!empty($item["description"])) { print "<br>" . $item["description"]; }
			print "	</td>\n";
			
			draw_nontemplated_item($struct_graph_item{$item["column_name"]}, $field_name . "_" . $item["id"], $current_def_value[$field_name]);
			
			print "</tr>\n";
		}
		}else{
			print "<tr bgcolor='#" . $colors["form_alternate2"] . "'><td><td colspan='2'>No Inputs</em></td></tr>";
		}
		
		end_box();
	}
	
	DrawFormItemHiddenIDField("local_graph_id",$_GET["local_graph_id"]);
	DrawFormItemHiddenTextBox("save_component_input","1","");
	
	if ((read_config_option("full_view_graph") == "") && (sizeof($input_item_list) > 0)) {
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
	global $colors, $struct_graph_item, $graph_item_types, $consolidation_functions;
	
	if (read_config_option("full_view_graph") == "") {
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
		$column_value = $_POST{$column_name . "_" . $input["id"]};
		
		/* loop through each item affected and update column data */
		if (sizeof($item_list) > 0) {
		foreach ($item_list as $item) {
			db_execute("update graph_templates_item set $column_name='$column_value' where id=" . $item["id"]);
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
		colors.hex as color_id
		from graph_templates_item left join data_template_rrd on graph_templates_item.task_item_id=data_template_rrd.id
		left join data_local on data_template_rrd.local_data_id=data_local.id
		left join data_template_data on data_local.id=data_template_data.local_data_id
		left join cdef on cdef_id=cdef.id
		left join colors on color_id=colors.id";
	
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
		reset($struct_graph_item);
		
		/* graph grouping display logic */
		$bold_this_row = false; $use_custom_row_color = false; $action_css = ""; unset($graph_preview_item_values);
		
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
		while (list($field_name, $field_array) = each($struct_graph_item)) {
			if ($mode == "delete") {
				$graph_preview_item_values[$field_name] = $graph_items[$i][$field_name];
			}elseif (!isset($graph_preview_item_values[$field_name])) {
				$graph_preview_item_values[$field_name] = $graph_template_items[$i][$field_name];
			}
		}
		
		/* "prepare" array values */
		$consolidation_function_id = $graph_preview_item_values["consolidation_function_id"];
		$graph_type_id = $graph_preview_item_values["graph_type_id"];
		
		/* color logic */
		if (($graph_item_types[$graph_type_id] != "GPRINT") && ($graph_item_types[$graph_type_id] != $_graph_type_name)) {
			$bold_this_row = true; $use_custom_row_color = true; $hard_return = "";
			
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
		
		if ($bold_this_row == true) {
			$action_css .= " font-weight:bold;";
		}
		
		/* draw the TD that shows the user whether we are going to: KEEP, ADD, or DROP the item */
		print "<td width='1%' bgcolor='#$action_column_color' style='font-weight: bold; color: white;'>" . $graph_item_actions[$mode] . "</td>";
		print "<td style='$action_css'><strong>Item # " . $i . "</strong></td>\n";
		
		if (empty($graph_preview_item_values["task_item_id"])) { $graph_preview_item_values["task_item_id"] = "No Task"; }
		
		switch (true) {
		case ereg("(AREA|STACK|GPRINT|LINE[123])", $_graph_type_name):
			$matrix_title = "(" . $graph_preview_item_values["task_item_id"] . "): " . $graph_preview_item_values["text_format"];
			break;
		case ereg("(HRULE|VRULE)", $_graph_type_name):
			$matrix_title = "HRULE: " . $graph_preview_item_values["value"];
			break;
		case ereg("(COMMENT)", $_graph_type_name):
			$matrix_title = "COMMENT: " . $graph_preview_item_values["text_format"];
			break;
		}
		
		/* use the cdef name (if in use) if all else fails */
		if ($matrix_title == "") {
			if ($graph_preview_item_values["cdef_id"] != "") {
				$matrix_title .= "CDEF: " . $graph_preview_item_values["cdef_id"];
			}
		}
		
		if ($graph_preview_item_values["hard_return"] == "on") {
			$hard_return = "<strong><font color=\"#FF0000\">&lt;HR&gt;</font></strong>";
		}
		
		print "<td style='$action_css'>" . htmlspecialchars($matrix_title) . $hard_return . "</td>\n";
		print "<td style='$action_css'>" . $graph_item_types{$graph_preview_item_values["graph_type_id"]} . "</td>\n";
		print "<td style='$action_css'>" . $consolidation_functions{$graph_preview_item_values["consolidation_function_id"]} . "</td>\n";
		print "<td" . ((!empty($graph_preview_item_values["color_id"])) ? " bgcolor='#" . $graph_preview_item_values["color_id"] . "'" : "") . " width='1%'>&nbsp;</td>\n";
		print "<td style='$action_css'>" . $graph_preview_item_values["color_id"] . "</td>\n";
		
		print "</tr>";
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
	if ((read_config_option("remove_verification") == "on") && ($_GET["confirm"] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the graph <strong>" . db_fetch_cell("select title from graph_templates_graph where local_graph_id=" . $_GET["local_graph_id"]) . "</strong>?", getenv("HTTP_REFERER"), "graphs.php?action=graph_remove&local_graph_id=" . $_GET["local_graph_id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || ($_GET["confirm"] == "yes")) {
		db_execute("delete from graph_templates_graph where local_graph_id=" . $_GET["local_graph_id"]);
		db_execute("delete from graph_templates_item where local_graph_id=" . $_GET["local_graph_id"]);
		db_execute("delete from graph_local where id=" . $_GET["local_graph_id"]);
	}	
}

function graph_edit() {
	global $colors, $struct_graph, $image_types;
	
	if (read_config_option("full_view_graph") == "") {
		start_box("<strong>Graph Management [edit]</strong>", "98%", $colors["header"], "3", "center", "");
		draw_graph_form_select("?action=graph_edit&local_graph_id=" . $_GET["local_graph_id"]);
		end_box();
	}
	
	$use_graph_template = true;
	
	if (isset($_GET["local_graph_id"])) {
		$local_graph_template_graph_id = db_fetch_cell("select local_graph_template_graph_id from graph_templates_graph where local_graph_id=" . $_GET["local_graph_id"]);
		
		$graphs = db_fetch_row("select * from graph_templates_graph where local_graph_id=" . $_GET["local_graph_id"]);
		$graphs_template = db_fetch_row("select * from graph_templates_graph where id=$local_graph_template_graph_id");
		
		$header_label = "[edit: " . $graphs["title"] . "]";
	}else{
		$header_label = "[new]";
		$use_graph_template = false;
	}
	
	if ($graphs["graph_template_id"] == "0") {
		$use_graph_template = false;
	}
	
	if ((read_config_option("full_view_graph") == "on") && ($_GET["local_graph_id"] > 0)) {
		item();
	}
	
	start_box("<strong>Graph Template Selection</strong> $header_label", "98%", $colors["header"], "3", "center", "");
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
			from graph_templates"),"name","id",$graphs["graph_template_id"],"None","0");?>
	</tr>
	
	<?
	end_box();
	start_box("<strong>Graph Configuration</strong>", "98%", $colors["header"], "3", "center", "");
	
	while (list($field_name, $field_array) = each($struct_graph)) {
		DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
		
		print "<td width='50%'><font class='textEditTitle'>" . $field_array["title"] . "</font><br>\n";
		
		if (($use_graph_template == false) || ($graphs_template{"t_" . $field_name} == "on")) {
			print $field_array["description"];
		}
		
		print "</td>\n";
		
		if (($use_graph_template == false) || ($graphs_template{"t_" . $field_name} == "on")) {
			draw_nontemplated_item($field_array, $field_name, $graphs[$field_name]);
		}else{
			draw_templated_item($field_array, $field_name, $graphs[$field_name]);
		}
		
		print "</tr>\n";
	}
	
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
		<form name="form_graph_id" method="post">
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="100">
						Filter by host:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
							<option value="graphs.php?host_id=0&filter=<?print $_REQUEST["filter"];?>"<?if ($_REQUEST["host_id"] == "0") {?> selected<?}?>>None</option>
							
							<?
							$hosts = db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname");
							
							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='graphs.php?host_id=" . $host["id"] . "&filter=" . $_REQUEST["filter"] . "'"; if ($_REQUEST["host_id"] == $host["id"]) { print " selected"; } print ">" . $host["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="5"></td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?print $_REQUEST["filter"];?>">
					</td>
					<td>
						&nbsp;<input type="image" src="images/button_go.gif" alt="Go" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>	
	<?
	
	end_box();
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	
	/* default to page 1 */
	if (empty($_GET["page"])) {
		$_GET["page"] = "1";
	}
	
	/* set the current page num as a session var */
	$_SESSION["sess_graph_current_page"] = $_GET["page"];
	
	/* restore the page num from the session var if it is currently stored */
	if (!empty($_SESSION["sess_graph_current_page"])) {
		$_GET["page"] = $_SESSION["sess_graph_current_page"];
	}
	
	if (!empty($_REQUEST["host_id"])) {
		$sql_base = "from graph_templates_graph
			left join graph_templates on graph_templates_graph.graph_template_id=graph_templates.id
			left join graph_local on graph_templates_graph.local_graph_id=graph_local.id
			left join graph_templates_item on graph_local.id=graph_templates_item.local_graph_id
			left join data_template_rrd on graph_templates_item.task_item_id=data_template_rrd.id
			left join data_local on data_template_rrd.local_data_id=data_local.id
			where data_local.host_id=" . $_REQUEST["host_id"] . "
			and graph_templates_graph.local_graph_id>0
			and graph_templates_graph.title like '%%" . $_REQUEST["filter"] . "%%'
			group by graph_templates_graph.id";
		
		$total_rows = sizeof(db_fetch_assoc("select
			graph_templates_graph.local_graph_id
			$sql_base"));
		$graph_list = db_fetch_assoc("select
			graph_templates_graph.id,
			graph_templates_graph.local_graph_id,
			graph_templates_graph.height,
			graph_templates_graph.width,
			graph_templates_graph.title,
			graph_templates.name
			$sql_base
			order by graph_templates_graph.title
			limit " . (ROWS_PER_PAGE*($_GET["page"]-1)) . "," . ROWS_PER_PAGE);
	}else{
		$total_rows = db_fetch_cell("select
			COUNT(id)
			from graph_templates_graph
			where graph_templates_graph.local_graph_id!=0
			and graph_templates_graph.title like '%%" . $_REQUEST["filter"] . "%%'");
		$graph_list = db_fetch_assoc("select 
			graph_templates_graph.id,
			graph_templates_graph.local_graph_id,
			graph_templates_graph.height,
			graph_templates_graph.width,
			graph_templates_graph.title,
			graph_templates.name
			from graph_templates_graph left join graph_templates on graph_templates_graph.graph_template_id=graph_templates.id
			where graph_templates_graph.local_graph_id!=0
			and graph_templates_graph.title like '%%" . $_REQUEST["filter"] . "%%'
			order by graph_templates_graph.title
			limit " . (ROWS_PER_PAGE*($_GET["page"]-1)) . "," . ROWS_PER_PAGE);
	}
		
	print "	<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='4'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_GET["page"] > 1) { print "<a class='linkOverDark' href='graphs.php?filter=" . $_REQUEST["filter"] . "&host_id=" . $_REQUEST["host_id"] . "&page=" . ($_GET["page"]-1) . "'>"; } print "Previous"; if ($_GET["page"] > 1) { print "</a>"; } print "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . (ROWS_PER_PAGE*($_GET["page"]-1)) . " to " . (($total_rows < ROWS_PER_PAGE) ? $total_rows : (ROWS_PER_PAGE*$_GET["page"])) . " of $total_rows
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_GET["page"] * ROWS_PER_PAGE) < $total_rows) { print "<a class='linkOverDark' href='graphs.php?filter=" . $_REQUEST["filter"] . "&host_id=" . $_REQUEST["host_id"] . "&page=" . ($_GET["page"]+1) . "'>"; } print "Next"; if (($_GET["page"] * ROWS_PER_PAGE) < $total_rows) { print "</a>"; } print " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Graph Title",$colors["header_text"],1);
		DrawMatrixHeaderItem("Template Name",$colors["header_text"],1);
		DrawMatrixHeaderItem("Size",$colors["header_text"],2);
	print "</tr>";
	
	if (sizeof($graph_list) > 0) {
	foreach ($graph_list as $graph) {
		DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="graphs.php?action=graph_edit&local_graph_id=<?print $graph["local_graph_id"];?>"><?print eregi_replace("(" . $_REQUEST["filter"] . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $graph["title"]);?></a>
			</td>
			<td>
				<?print ((empty($graph["name"])) ? "<em>None</em>" : $graph["name"]); ?>
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
	}else{
		print "<tr><td><em>No Graphs Found</em></td></tr>";
	}
	end_box();
}

?>