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
include_once ("include/functions.php");
include_once ('include/form.php');

switch ($_REQUEST["action"]) {
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;
	case 'remove':
		template_remove();
		
		header ("Location: host_templates.php");
		break;
	case 'edit':
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
	if (isset($_POST["save_component_template"])) {
		template_save();
		
		return "host_templates.php";
	}
}

/* ---------------------
    Template Functions
   --------------------- */

function template_remove() {
	global $config;
	
	if ((read_config_option("remove_verification") == "on") && ($_GET["confirm"] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the host template <strong>'" . db_fetch_cell("select name from host_template where id=" . $_GET["id"]) . "'</strong>?", getenv("HTTP_REFERER"), "host_templates.php?action=remove&id=" . $_GET["id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || ($_GET["confirm"] == "yes")) {
		db_execute("delete from host_template where id=" . $_GET["id"]);
	}
}

function template_save() {
	$save["id"] = $_POST["id"];
	$save["name"] = $_POST["name"];
	
	$host_template_id = sql_save($save, "host_template");
	
	if ($host_template_id) {
		raise_message(1);
	}else{
		raise_message(2);
		header("Location: " . $_SERVER["HTTP_REFERER"]);
		exit;
	}
	
	db_execute ("delete from host_template_data_template where host_template_id=$host_template_id");
	db_execute ("delete from host_template_graph_template where host_template_id=$host_template_id");
	
	while (list($var, $val) = each($_POST)) {
		if (eregi("^gt_", $var)) {
			db_execute ("replace into host_template_graph_template (host_template_id,graph_template_id,suggested_values) values($host_template_id," . substr($var, 3) . ",'" . $_POST{"ogt_suggested_values_" . substr($var, 3)} . "')");
		}elseif (eregi("^odt_suggested_values_", $var)) {
			$data_template_id = ereg_replace("^odt_suggested_values_([0-9]+)_[0-9]+$", "\\1", $var);
			$graph_template_id = ereg_replace("^odt_suggested_values_[0-9]+_([0-9]+)$", "\\1", $var);
			
			if (!empty($val)) {
				db_execute ("replace into host_template_data_template (host_template_id,data_template_id,graph_template_id,suggested_values) values($host_template_id,$data_template_id,$graph_template_id,'$val')");
			}
		}
	}
}

function template_edit() {
	global $colors;
	
	display_output_messages();
	
	start_box("<strong>Host Templates [edit]</strong>", "98%", $colors["header"], "3", "center", "");
	
	if (isset($_GET["id"])) {
		$host_template = db_fetch_row("select * from host_template where id=" . $_GET["id"]);
	}else{
		unset($host_template);
	}
	
	?>
	<form method="post" action="host_templates.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			A useful name for this host template.
		</td>
		<?DrawFormItemTextBox("name",$host_template["name"],"","255", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Selected Graph Templates</font><br>
			Select one or more graph templates to associate with this host template.
		</td>
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td align="top" width="50%">
						<?
						$graph_templates = db_fetch_assoc("select 
							host_template_graph_template.host_template_id,
							host_template_graph_template.suggested_values,
							graph_templates.id,
							graph_templates.name
							from graph_templates left join host_template_graph_template
							on (graph_templates.id=host_template_graph_template.graph_template_id and host_template_graph_template.host_template_id=" . $_GET["id"] . ") 
							where graph_templates.type='template'
							order by graph_templates.name");
						
						$i = 0;
						if (sizeof($graph_templates) > 0) {
						foreach($graph_templates as $graph_template) {
							$column1 = floor((sizeof($graph_templates) / 2) + (sizeof($graph_templates) % 2));
							
							if (empty($graph_template["host_template_id"])) {
								$old_value = "";
							}else{
								$old_value = "on";
							}
							
							if ($i == $column1) {
								print "</td><td valign='top' width='50%'>";
							}
							DrawStrippedFormItemCheckBox("gt_".$graph_template["id"], $old_value, $graph_template["name"], "",true);
							$i++;
						}
						}
						?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?
	end_box();
	
	reset($graph_templates);
	
	if (sizeof($graph_templates) > 0) {
	foreach($graph_templates as $graph_template) {
		if (!empty($graph_template["host_template_id"])) {
			$i = 0;
			start_box("<strong>Graph Template:</strong> " . $graph_template["name"], "98%", "777777", "3", "center", "");
			
			DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
				<td width="50%">
					<font class="textEditTitle">Suggested Values</font> <em>(<?print $graph_template["name"];?>)</em><br>
					You can use this field to suggest defaults to the user or even over ride 
					the non-template bit. For a list of valid field names, see the documentation.
				</td>
				<?DrawFormItemTextBox("ogt_suggested_values_" . $graph_template["id"],$graph_template["suggested_values"],"","255", "40");?>
			</tr>
			<?
			
			$data_templates = db_fetch_assoc("select
				data_template.id,
				data_template.name
				from data_template left join data_template_rrd
				on data_template_rrd.data_template_id=data_template.id
				left join graph_templates_item
				on graph_templates_item.task_item_id=data_template_rrd.id
				left join host_template_data_template
				on (data_template_rrd.data_template_id=host_template_data_template.data_template_id and host_template_data_template.host_template_id=" . $_GET["id"] . " and host_template_data_template.graph_template_id=" . $graph_template["id"] . ")
				where data_template_rrd.local_data_id=0
				and graph_templates_item.local_graph_id=0
				and graph_templates_item.graph_template_id=" . $graph_template["id"] . "
				group by data_template.id
				order by data_template.name");
				
			if (sizeof($data_templates) > 0) {
			foreach ($data_templates as $data_template) {
				DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
					<td width="50%">
						<font class="textEditTitle">Suggested Values</font> <em>(<?print $data_template["name"];?>)</em><br>
						You can use this field to suggest defaults to the user or even over ride 
						the non-template bit. For a list of valid field names, see the documentation.
					</td>
					<?DrawFormItemTextBox("odt_suggested_values_" . $data_template["data_template_id"] . "_" . $graph_template["id"],$data_template["suggested_values"],"","255", "40");?>
				</tr>
				<?
			}
			}
			
			end_box();
		}
	}
	}
	
	DrawFormItemHiddenIDField("id",$_GET["id"]);
	DrawFormItemHiddenTextBox("save_component_template","1","");
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "host_templates.php");?>
		</td>
	</tr>
	</form>
	<?
	end_box();	
}

function template() {
	global $colors;
	
	display_output_messages();
	
	start_box("<strong>Host Templates</strong>", "98%", $colors["header"], "3", "center", "host_templates.php?action=edit");
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Name",$colors["header_text"],1);
		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
	print "</tr>";
    
	$host_templates = db_fetch_assoc("select * from host_template order by name");
	
	if (sizeof($host_templates) > 0) {
	foreach ($host_templates as $host_template) {
		DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="host_templates.php?action=edit&id=<?print $host_template["id"];?>"><?print $host_template["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="host_templates.php?action=remove&id=<?print $host_template["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	}
	}
	end_box();	
}
?>
