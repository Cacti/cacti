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
    The Save Function
   -------------------------- */

function form_save() {
	global $config;
	
	if (isset($_POST["save_component_template"])) {
		template_save();
		return "data_templates.php?action=template_edit&data_template_id=" . $_POST["data_template_id"] . "&view_rrd=" . $_POST["current_rrd"];
	}
}
   
/* ----------------------------
    template - Graph Templates 
   ---------------------------- */

function template_remove() {
	global $config;
	
	if (($config["remove_verification"]["value"] == "on") && ($_GET["confirm"] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the graph template <strong>'" . db_fetch_cell("select name from graph_templates where id=" . $_GET["graph_template_id"]) . "'</strong>? This is generally not a good idea if you have graphs attached to this template even though it should not affect any graphs.", getenv("HTTP_REFERER"), "graph_templates.php?action=template_remove&graph_template_id=" . $_GET["graph_template_id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($_GET["confirm"] == "yes")) {
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
	include_once ("include/utility_functions.php");
	
	if ($_POST["rrd_maximum"] == "") { $_POST["rrd_maximum"] = 0; }
	if ($_POST["rrd_minimum"] == "") { $_POST["rrd_minimum"] = 0; }
	
	/* save: data_template */
	$save["id"] = $_POST["data_template_id"];
	$save["name"] = $_POST["template_name"];
	$save["graph_template_id"] = 0;
	
	$data_template_id = sql_save($save, "data_template");
	unset ($save);
	
	/* save: data_template_data */
	$save["id"] = $_POST["data_template_data_id"];
	$save["local_data_template_data_id"] = 0;
	$save["local_data_id"] = 0;
	$save["data_template_id"] = $data_template_id;
	$save["data_input_id"] = $_POST["data_input_id"];
	$save["t_name"] = $_POST["t_name"];
	$save["name"] = $_POST["name"];
	$save["data_source_path"] = $_POST["data_source_path"];
	$save["t_active"] = $_POST["t_active"];
	$save["active"] = $_POST["active"];
	$save["t_rrd_step"] = $_POST["t_rrd_step"];
	$save["rrd_step"] = $_POST["rrd_step"];
	
	$data_template_data_id = sql_save($save, "data_template_data");
	unset ($save);
	
	/* save: data_template_rrd */
	$save["id"] = $_POST["data_template_rrd_id"];
	$save["local_data_template_rrd_id"] = 0;
	$save["local_data_id"] = 0;
	$save["data_template_id"] = $data_template_id;
	$save["t_rrd_maximum"] = $_POST["t_rrd_maximum"];
	$save["rrd_maximum"] = $_POST["rrd_maximum"];
	$save["t_rrd_minimum"] = $_POST["t_rrd_minimum"];
	$save["rrd_minimum"] = $_POST["rrd_minimum"];
	$save["t_rrd_heartbeat"] = $_POST["t_rrd_heartbeat"];
	$save["rrd_heartbeat"] = $_POST["rrd_heartbeat"];
	$save["t_data_source_type_id"] = $_POST["t_data_source_type_id"];
	$save["data_source_type_id"] = $_POST["data_source_type_id"];
	$save["t_data_source_name"] = $_POST["t_data_source_name"];
	$save["data_source_name"] = $_POST["data_source_name"];
	$save["data_input_field_id"] = $_POST["data_input_field_id"];
	
	$data_template_rrd_id = sql_save($save, "data_template_rrd");
	
	/* push out all data source settings to child data source using this template */
	push_out_data_source($data_template_data_id);
	push_out_data_source_item($data_template_rrd_id);
}

function template_edit() {
	global $config, $colors;
	
	if (isset($_GET["data_template_id"])) {
		$template_data = db_fetch_row("select * from data_template_data where data_template_id=" . $_GET["data_template_id"]);
		$template = db_fetch_row("select * from data_template where id=" . $_GET["data_template_id"]);
	}else{
		unset($template_data);
		unset($template);
	}
	
	start_box("Template Configuration", "", "");
	?>
	
	<form method="post" action="data_templates.php">
		
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			The name given to this data template.
		</td>
		<?DrawFormItemTextBox("template_name",$template["name"],"","150", "40");?>
	</tr>
	
	<?
	end_box();
	
	start_box("Data Template Configuration", "", "");
	?>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			<?DrawStrippedFormItemCheckBox("t_name",$template_data["t_name"],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemTextBox("name",$template_data["name"],"","250", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Data Source Path</font><br>
			Specify the full path to the rrd file containing the data.
		</td>
		<?DrawFormItemTextBox("data_source_path",$template_rrd["data_source_path"],"","255", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Data Input Source</font><br>
			The script/source used to gather data for this data source.
		</td>
		<?DrawFormItemDropdownFromSQL("data_input_id",db_fetch_assoc("select id,name from data_input order by name"),"name","id",$template_data["data_input_id"],"","1");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Step</font><br>
			<?DrawStrippedFormItemCheckBox("t_rrd_step",$template_data["t_rrd_step"],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemTextBox("rrd_step",$template_data["rrd_step"],"300","5","20");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Data Source Active</font><br>
			<?DrawStrippedFormItemCheckBox("t_active",$template_data["t_active"],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemCheckBox("active",$template_data["active"],"Data Source Active","on",$_GET["data_template_id"]);?>
	</tr>
	
	<?
	end_box();
	
	/* fetch ALL rrd's for this data source */
	if (isset($_GET["data_template_id"])) {
		$template_data_rrds = db_fetch_assoc("select id,data_source_name from data_template_rrd where data_template_id=" . $_GET["data_template_id"] . " and local_data_id=0 order by data_source_name");
	}
	
	/* select the first "rrd" of this data source by default */
	if (empty($_GET["view_rrd"])) {
		$_GET["view_rrd"] = $template_data_rrds[0]["id"];
	}
	
	/* get more information about the rrd we chose */
	if (!empty($_GET["view_rrd"])) {
		$template_rrd = db_fetch_row("select * from data_template_rrd where id=" . $_GET["view_rrd"]);
	}
	
	start_box("Data Source Configuration [" . $template_rrd["data_source_name"] . "]", "", "");
	
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
							<img src="images/tab_left.gif" border="0" align="absmiddle"><a class="linkTabs" href="data_templates.php?action=template_edit&data_template_id=<?print $_GET["data_template_id"];?>&view_rrd=<?print $template_data_rrd["id"];?>"><?print "$i: " . $template_data_rrd["data_source_name"];?></a><img src="images/tab_right.gif" border="0" align="absmiddle">
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
		$_GET["view_rrd"] = $template_data_rrds[0]["id"];
	}
	
	?>
	
	<form method="post" action="data_templates.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Internal Data Source Name</font><br>
			<?DrawStrippedFormItemCheckBox("t_data_source_name",$template_rrd["t_data_source_name"],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemTextBox("data_source_name",$template_rrd["data_source_name"],"","19", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Maximum Value</font><br>
			<?DrawStrippedFormItemCheckBox("t_rrd_maximum",$template_rrd["t_rrd_maximum"],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemTextBox("rrd_maximum",$template_rrd["rrd_maximum"],"1","20","30");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Minimum Value</font><br>
			<?DrawStrippedFormItemCheckBox("t_rrd_minimum",$template_rrd["t_rrd_minimum"],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemTextBox("rrd_minimum",$template_rrd["rrd_minimum"],"0","20","30");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Data Source Type</font><br>
			<?DrawStrippedFormItemCheckBox("t_data_source_type_id",$template_rrd["t_data_source_type_id"],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemDropdownFromSQL("data_source_type_id",db_fetch_assoc("select * from def_ds order by Name"),"Name","ID",$template_rrd["data_source_type_id"],"","1");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Heartbeat</font><br>
			<?DrawStrippedFormItemCheckBox("t_rrd_heartbeat",$template_rrd["t_rrd_heartbeat"],"Use Per-Graph Value (Ignore this Value)","",false);?>
		</td>
		<?DrawFormItemTextBox("rrd_heartbeat",$template_rrd["rrd_heartbeat"],"600","5","30");?>
	</tr>
	
	<?
	end_box();
	
	DrawFormItemHiddenIDField("data_template_id",$_GET["data_template_id"]);
	DrawFormItemHiddenIDField("host_id",$_GET["host_id"]);
	DrawFormItemHiddenIDField("data_template_data_id",$template_data["id"]);
	DrawFormItemHiddenIDField("data_template_rrd_id",$template_rrd["id"]);
	DrawFormItemHiddenIDField("current_rrd",$_GET["view_rrd"]);
	DrawFormItemHiddenTextBox("save_component_template","1","");
	
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
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Template Title",$colors["header_text"],2);
	print "</tr>";
	
	$template_list = db_fetch_assoc("select 
		data_template.id,
		data_template.name
		from data_template
		order by data_template.name");
       
	if (sizeof($template_list) > 0) {
	foreach ($template_list as $template) {
		DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="data_templates.php?action=template_edit&data_template_id=<?print $template["id"];?>"><?print $template["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="data_templates.php?action=template_remove&data_template_id=<?print $template["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
		<?
		$i++;
	}
	}
	end_box();
}

?>
