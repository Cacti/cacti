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

$row_counter = 0;

include_once ("include/functions.php");
include_once ("include/config_arrays.php");
include_once ('include/form.php');

switch ($_REQUEST["action"]) {
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;
	case 'new_graphs':
		include_once ("include/top_header.php");
		
		host_new_graphs();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'remove':
		host_remove();
		
		header ("Location: host.php");
		break;
	case 'edit':
		include_once ("include/top_header.php");
		
		host_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		host();
		
		include_once ("include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_host"])) {
		host_save();
		return "host.php";
	}elseif (isset($_POST["save_component_new_graphs"])) {
		host_new_graphs_save();
		return "host.php?action=edit&id=" . $_POST["host_id"];
	}
	
	
}

/* ---------------------
    Host Functions
   --------------------- */

function host_remove() {
	global $config;
	
	if ((read_config_option("remove_verification") == "on") && ($_GET["confirm"] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the host <strong>'" . db_fetch_cell("select description from host where id=" . $_GET["id"]) . "'</strong>?", getenv("HTTP_REFERER"), "host.php?action=remove&id=" . $_GET["id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || ($_GET["confirm"] == "yes")) {
		db_execute("delete from host where id=" . $_GET["id"]);
	}
}

function host_new_graphs_save() {
	global $struct_graph, $struct_data_source, $struct_graph_item, $struct_data_source_item;
	
	include_once ("include/utility_functions.php");
	
	$selected_graphs = unserialize(stripslashes($_POST["host_selected_graphs"]));
	
	for ($i=0; ($i < count($selected_graphs)); $i++) {
		unset($save);
		
		$save["id"] = 0;
		$save["graph_template_id"] = $selected_graphs[$i];
		
		$local_graph_id = sql_save($save, "graph_local");
		
		change_graph_template($local_graph_id, $selected_graphs[$i], true);
		
		/* just a friendly note: this model of storing graph template->local_graph_id data relies
		on the fact that each graph template will be unique. this is true for now, but something to
		keep in mind if going to a multi-graph template model */
		$new_graph_templates{$selected_graphs[$i]} = $local_graph_id;
		
		$data_templates = db_fetch_assoc("select
			data_template.id,
			data_template.name
			from host_template_graph_data, data_template, data_template_rrd
			where host_template_graph_data.data_template_rrd_id=data_template_rrd.id
			and data_template_rrd.data_template_id=data_template.id
			and data_template_rrd.local_data_id=0
			and host_template_graph_data.host_template_id=" . $_POST["host_template_id"] . "
			and host_template_graph_data.graph_template_id=" . $selected_graphs[$i] . "
			group by data_template.id
			order by data_template.name");
		
		if (sizeof($data_templates) > 0) {
		foreach ($data_templates as $data_template) {
			unset($save);
			
			$save["id"] = 0;
			$save["data_template_id"] = $data_template["id"];
			$save["host_id"] = $_POST["host_id"];
			
			$local_data_id = sql_save($save, "data_local");
			
			change_data_template($local_data_id, $data_template["id"]);
			
			/* same deal as above, cache data_template->local_data_id mapping for below */
			$new_data_templates{$data_template["id"]} = $local_data_id;
		}
		}
		
		/* connect the dots: graph -> data source(s) */
		$graph_inputs = db_fetch_assoc("select
			host_template_graph_data.data_template_rrd_id,
			host_template_graph_data.graph_template_input_id,
			data_template_rrd.data_template_id
			from
			host_template_graph_data,data_template_rrd
			where host_template_graph_data.data_template_rrd_id=data_template_rrd.id
			and host_template_graph_data.host_template_id=" . $_POST["host_template_id"] . "
			and host_template_graph_data.graph_template_id=" . $selected_graphs[$i]);
		
		if (sizeof($graph_inputs) > 0) {
		foreach ($graph_inputs as $input) {
			/* we need to find out which graph items will be affected by saving this particular item */
			$item_list = db_fetch_assoc("select
				graph_templates_item.id
				from graph_template_input_defs,graph_templates_item
				where graph_template_input_defs.graph_template_item_id=graph_templates_item.local_graph_template_item_id
				and graph_templates_item.local_graph_id=" . $new_graph_templates{$selected_graphs[$i]} . "
				and graph_template_input_defs.graph_template_input_id=" . $input["graph_template_input_id"]);
			
			/* loop through each item affected and update column data */
			if (sizeof($item_list) > 0) {
			foreach ($item_list as $item) {
				$data_template_rrd_id = db_fetch_cell("select id from data_template_rrd where local_data_template_rrd_id=" . $input["data_template_rrd_id"] . " and local_data_id=" . $new_data_templates{$input["data_template_id"]});
				db_execute("update graph_templates_item set task_item_id='$data_template_rrd_id' where id=" . $item["id"]);
			}
			}
		}
		}
	}
	
	/* go ahead and write out values from the POST form to our new data */
	while (list($var, $val) = each($_POST)) {
		if (preg_match("/^g_(\d+)_(\w+)/", $var, $matches)) {
			db_execute("update graph_templates_graph set " . $matches[2] . "='$val' where local_graph_id=" . $new_graph_templates{$matches[1]});
		}elseif (preg_match("/^gi_(\d+)_(\d+)_(\w+)/", $var, $matches)) {
			/* we need to find out which graph items will be affected by saving this particular item */
			$item_list = db_fetch_assoc("select
				graph_templates_item.id
				from graph_template_input_defs,graph_templates_item
				where graph_template_input_defs.graph_template_item_id=graph_templates_item.local_graph_template_item_id
				and graph_templates_item.local_graph_id=" . $new_graph_templates{$matches[1]} . "
				and graph_template_input_defs.graph_template_input_id=" . $matches[2]);
			
			/* loop through each item affected and update column data */
			if (sizeof($item_list) > 0) {
			foreach ($item_list as $item) {
				db_execute("update graph_templates_item set " . $matches[3] . "='$val' where id=" . $item["id"]);
			}
			}
		}elseif (preg_match("/^d_(\d+)_(\d+)_(\w+)/", $var, $matches)) {
			db_execute("update data_template_data set " . $matches[3] . "='$val' where local_data_id=" . $new_data_templates{$matches[2]});
		}elseif (preg_match("/^di_(\d+)_(\d+)_(\d+)_(\w+)/", $var, $matches)) {
			db_execute("update data_template_rrd set " . $matches[4] . "='$val' where local_data_id=" . $new_data_templates{$matches[2]} . " and local_data_template_rrd_id=" . $matches[3]);
		}
	}
}

function host_save() {
	include_once ("include/utility_functions.php");
	include_once ("include/snmp_functions.php");
	
	$save["id"] = $_POST["id"];
	$save["host_template_id"] = $_POST["host_template_id"];
	$save["description"] = $_POST["description"];
	$save["hostname"] = $_POST["hostname"];
	$save["management_ip"] = $_POST["management_ip"];
	$save["snmp_community"] = $_POST["snmp_community"];
	$save["snmp_version"] = $_POST["snmp_version"];
	$save["snmp_username"] = $_POST["snmp_username"];
	$save["snmp_password"] = $_POST["snmp_password"];
	
	$host_id = sql_save($save, "host");
	
	if ($host_id) {
		/* push out relavant fields to data sources using this host */
		push_out_host($host_id);
	}
	
	while (list($var, $val) = each($_POST)) {
		if (preg_match('/^cg_\d+$/', $var)) {
			$graph_template_id = preg_replace('/^cg_(\d+)$/', "\\1", $var);
			
			$selected_graphs["cg"][$graph_template_id][$graph_template_id] = true;
		}elseif (preg_match('/^sg_\d+_\d+_\d+$/', $var)) {
			$graph_template_id = preg_replace('/^sg_(\d+)_(\d+)_(\d+)$/', "\\1", $var);
			$snmp_query_id = preg_replace('/^sg_(\d+)_(\d+)_(\d+)$/', "\\2", $var);
			$snmp_index = preg_replace('/^sg_(\d+)_(\d+)_(\d+)$/', "\\3", $var);
			
			$selected_graphs["sg"][$snmp_query_id][$graph_template_id][$snmp_index] = true;
		}
	}
	
	if (isset($_POST["add_y"])) {
		db_execute("replace into host_snmp_query (host_id,snmp_query_id) values ($host_id," . $_POST["snmp_query_id"] . ")");
		
		/* recache snmp data */
		query_snmp_host($host_id, $_POST["snmp_query_id"]);
		
		header("Location: host.php?action=edit&id=" . $host_id);
		exit;
	}
	
	if (isset($selected_graphs)) {
		//$_SESSION["sess_host_selected_graphs"] = serialize($selected_graphs);
		//header("Location: host.php?action=new_graphs&host_template_id=" . $_POST["host_template_id"] . "&host_id=$host_id");
		host_new_graphs($host_id, $_POST["host_template_id"], $selected_graphs);
		exit;
	}
	
	if ($host_id) {
		raise_message(1);
	}else{
		raise_message(2);
		header("Location: " . $_SERVER["HTTP_REFERER"]);
		exit;
	}
}

function host_new_graphs($host_id, $host_template_id, $selected_graphs_array) {
	include_once ("include/top_header.php");
	
	global $colors, $row_counter, $struct_graph, $struct_data_source, $struct_graph_item, $struct_data_source_item;
	
	//print "<pre>";print_r($selected_graphs_array);print "</pre>";
	//$selected_graphs = unserialize($_SESSION["sess_host_selected_graphs"]);
	
	while (list($form_type, $form_array) = each($selected_graphs_array)) {
		while (list($form_id1, $form_array2) = each($form_array)) {
			//print $form_id1;
			
			if ($form_type == "cg") {
				$graph_template_id = $form_id1;
				
				start_box("", "98%", $colors["header"], "3", "center", "");
			}elseif ($form_type == "sg") {
				while (list($form_id2, $form_array3) = each($form_array2)) {
					$snmp_query_id = $form_id1;
					$graph_template_id = $form_id2;
					$num_graphs = sizeof($form_array3);
					
					$snmp_query = db_fetch_row("select
						snmp_query.name
						from snmp_query
						where snmp_query.id=$snmp_query_id");
				}
				
				/* DRAW: SNMP Query */
				start_box("<strong>SNMP Query</strong> [" . $snmp_query["name"] . "]", "98%", $colors["header"], "3", "center", "");
			}
			
			$data_templates = db_fetch_assoc("select
				data_template.name as data_template_name,
				data_template_data.*
				from host_template_graph_data, data_template, data_template_rrd, data_template_data
				where host_template_graph_data.data_template_rrd_id=data_template_rrd.id
				and data_template_rrd.data_template_id=data_template.id
				and data_template.id=data_template_data.data_template_id
				and data_template_data.local_data_id=0
				and host_template_graph_data.host_template_id=$host_template_id
				and host_template_graph_data.graph_template_id=" . $graph_template_id . "
				group by data_template.id
				order by data_template.name");
			
			$graph_template = db_fetch_row("select
				graph_templates.name as graph_template_name,
				graph_templates_graph.*
				from graph_templates, graph_templates_graph
				where graph_templates.id=graph_templates_graph.graph_template_id
				and graph_templates.id=" . $graph_template_id . "
				and graph_templates_graph.local_graph_id=0");
			$graph_template_name = db_fetch_cell("select name from graph_templates where id=" . $graph_template_id);
			
			$graph_inputs = db_fetch_assoc("select
				*
				from graph_template_input
				where graph_template_id=" . $graph_template_id . "
				and column_name != 'task_item_id'
				order by name");
			
			print "<form method='post' action='host.php'>\n";
			
			
			
			reset($struct_graph);
			$drew_items = false;
			
			while (list($field_name, $field_array) = each($struct_graph)) {
				if ($graph_template{"t_" . $field_name} == "on") {
					if ($drew_items == false) {
						print "<tr><td colspan='2' bgcolor='#" . $colors["header"] . "' class='textHeaderDark'><strong>Graph</strong> [Template: " . $graph_template["graph_template_name"] . "]</td></tr>";
					}
					
					draw_templated_row($field_array, "g_" . $graph_template_id . "_" . $field_name, $graph_template[$field_name]);
					$row_counter = 0; $drew_items = true;
				}
			}
			
			/* DRAW: Graphs Inputs */
			if (sizeof($graph_inputs) > 0) {
			foreach ($graph_inputs as $graph_input) {
				if ($drew_items == false) {
					print "<tr><td colspan='2' bgcolor='#" . $colors["header"] . "' class='textHeaderDark'><strong>Graph Items</strong> [Template: " . $graph_template_name . "]</td></tr>";
				}
				
				$current_value = db_fetch_cell("select 
					graph_templates_item." . $graph_input["column_name"] . "
					from graph_templates_item,graph_template_input_defs 
					where graph_template_input_defs.graph_template_item_id=graph_templates_item.local_graph_template_item_id 
					and graph_template_input_defs.graph_template_input_id=" . $graph_input["id"] . "
					and graph_templates_item.graph_template_id=" . $graph_template_id . "
					limit 0,1");
				
				$field_name = $item["column_name"];
				
				DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0);
				
				print "	<td width='50%'>
						<font class='textEditTitle'>" . $graph_input["name"] . "</font>";
						if (!empty($graph_input["description"])) { print "<br>" . $graph_input["description"]; }
				print "	</td>\n";
				
				draw_nontemplated_item($struct_graph_item{$graph_input["column_name"]}, "gi_" . $graph_template_id . "_" . $graph_input["id"] . "_" . $graph_input["column_name"], $current_value);
				
				print "</tr>\n";
				
				$row_counter = 0; $drew_items = true;
			}
			}
			
			/* DRAW: Data Sources */
			if (sizeof($data_templates) > 0) {
			foreach ($data_templates as $data_template) {
				reset($struct_data_source);
				$drew_items = false;
				
				while (list($field_name, $field_array) = each($struct_data_source)) {
					if ($data_template{"t_" . $field_name} == "on") {
						if ($drew_items == false) {
							print "<tr><td colspan='2' bgcolor='#" . $colors["header"] . "' class='textHeaderDark'><strong>Data Source</strong> [Template: " . $data_template["data_template_name"] . "]</td></tr>";
						}
						
						draw_templated_row($field_array, "d_" . $graph_template_id . "_" . $data_template["data_template_id"] . "_" . $field_name, $data_template[$field_name]);
						$row_counter = 0; $drew_items = true;
					}
				}
				
				/* DRAW: Data Source Items */
				$data_template_items = db_fetch_assoc("select
					data_template_rrd.*
					from data_template_rrd
					where data_template_rrd.data_template_id=" . $data_template["data_template_id"] . "
					and local_data_id=0");
				
				if (sizeof($data_template_items) > 0) {
				foreach ($data_template_items as $data_template_item) {
					reset($struct_data_source_item);
					$row_counter = 0;
					
					while (list($field_name, $field_array) = each($struct_data_source_item)) {
						if ($data_template_item{"t_" . $field_name} == "on") {
							if ($drew_items == false) {
								print "<tr><td colspan='2' bgcolor='#" . $colors["header"] . "' class='textHeaderDark'><strong>Data Source Item</strong> - " . $data_template_item["data_source_name"] . " - [Template: " . $data_template["data_template_name"] . "]</td></tr>";
							}
							
							draw_templated_row($field_array, "di_" . $graph_template_id . "_" . $data_template["data_template_id"] . "_" . $data_template_item["id"] . "_" . $field_name, $data_template_item[$field_name]);
							$row_counter = 0; $drew_items = true;
						}
					}
				}
				}
			}
			}
			
			end_box();
		}
	}
	
	DrawFormItemHiddenIDField("host_template_id",$host_template_id);
	DrawFormItemHiddenIDField("host_id",$host_id);
	DrawFormItemHiddenTextBox("save_component_new_graphs","1","");
	print "<input type='hidden' name='host_selected_graphs' value='" . $_SESSION["sess_host_selected_graphs"] . "'>\n";
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "host.php?action=edit&id=$host_id");?>
		</td>
	</tr>
	</form>
	<?
	end_box();
	
	include_once ("include/bottom_footer.php");
}

function host_edit() {
	include_once ("include/xml_functions.php");
	
	global $colors, $snmp_versions, $paths;
	
	display_output_messages();
	
	start_box("<strong>Polling Hosts</strong> [edit]", "98%", $colors["header"], "3", "center", "");
	
	if (isset($_GET["id"])) {
		$host = db_fetch_row("select * from host where id=" . $_GET["id"]);
	}else{
		unset($host);
	}
	
	?>
	<form method="post" action="host.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Description</font><br>
			Give this host a meaningful description.
		</td>
		<?DrawFormItemTextBox("description",$host["description"],"","250", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Host Template</font><br>
			Choose what type of host, host template this is. The host template will govern what kinds
			of data should be gathered from this type of host.
		</td>
		<?DrawFormItemDropdownFromSQL("host_template_id",db_fetch_assoc("select id,name from host_template"),"name","id",$host["host_template_id"],"None","1");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Hostname</font><br>
			Fill in the fully qualified hostname for this device.
		</td>
		<?DrawFormItemTextBox("hostname",$host["hostname"],"","250", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Management IP</font><br>
			Choose the IP address that will be used to gather data from this host. The hostname will be
			used a fallback in case this fails.
		</td>
		<?DrawFormItemTextBox("management_ip",$host["management_ip"],"","15", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">SNMP Community</font><br>
			Fill in the SNMP read community for this device.
		</td>
		<?DrawFormItemTextBox("snmp_community",$host["snmp_community"],"","15", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">SNMP Username</font><br>
			Fill in the SNMP username for this device (v3).
		</td>
		<?DrawFormItemTextBox("snmp_username",$host["snmp_username"],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">SNMP Community</font><br>
			Fill in the SNMP password for this device (v3).
		</td>
		<?DrawFormItemTextBox("snmp_password",$host["snmp_password"],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">SNMP Version</font><br>
			Choose the SNMP version for this host.
		</td>
		<?DrawFormItemDropdownFromSQL("snmp_version",$snmp_versions,"","",$host["snmp_version"],"","1");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td colspan="2">
			<table cellspacing="0" cellpadding="0" border="0" width="100%">
				<tr>
					<td width="50%">
						<font class="textEditTitle">Associated SNMP Query</font><br>
						If you choose to add this SNMP query to this host, information will be queried from this
						host upon addition.
					</td>
					<td width="1">
						<?DrawStrippedFormItemDropdownFromSQL("snmp_query_id",db_fetch_assoc("select id,name from snmp_query order by name"),"name","id","","","");?>
					</td>
					<td>
						&nbsp;<input type="image" src="images/button_add.gif" alt="Add" name="add" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
	</tr>
	
	<?
	end_box();
	
	DrawFormItemHiddenIDField("id",$_GET["id"]);
	DrawFormItemHiddenTextBox("save_component_host","1","");
	
	$i = 0;
	if (!empty($host["host_template_id"])) {
		start_box("<strong>Host Template Items</strong>", "98%", $colors["header"], "3", "center", "");
		
		$graph_templates = db_fetch_assoc("select
			graph_templates.id as graph_template_id,
			data_template.id as data_template_id,
			graph_templates.name as graph_template_name,
			data_template.name as data_template_name
			from host_template_graph_data, graph_templates, data_template, data_template_rrd
			where host_template_graph_data.data_template_rrd_id=data_template_rrd.id
			and data_template_rrd.data_template_id=data_template.id
			and host_template_graph_data.graph_template_id=graph_templates.id
			and host_template_graph_data.host_template_id=" . $host["host_template_id"] . "
			group by data_template.id,graph_templates.id
			order by graph_templates.id,data_template.name");
		
		$j = 0;
		if (sizeof($graph_templates) > 0) {
		foreach ($graph_templates as $graph_template) {
			if ($graph_template["graph_template_id"] != $_graph_template_id) {
				DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
				print "<td width='50%'>";
				print "<font class='textEditTitle'>Create Graph: " . $graph_template["graph_template_name"] . "</font><br>";
			}
			
			print "&nbsp;&nbsp;&nbsp;+ " .  $graph_template["data_template_name"] . "<br>";
			
			$_graph_template_id = $graph_template["graph_template_id"];
			
			if ($graph_templates{$j+1}["graph_template_id"] != $_graph_template_id) {
				print "</td>\n";
				DrawFormItemCheckBox("cg_" . $graph_template["graph_template_id"],"","Create this Graph","");
				print "</tr>";
			}
			
			$j++;
		}
		}
		
		end_box();
	}
	
	$snmp_queries = db_fetch_assoc("select
		snmp_query.id,
		snmp_query.name,
		snmp_query.xml_path,
		snmp_query.graph_template_id
		from snmp_query,host_snmp_query
		where host_snmp_query.snmp_query_id=snmp_query.id
		and host_snmp_query.host_id=" . $_GET["id"] . "
		order by snmp_query.name");
	
	if (sizeof($snmp_queries) > 0) {
	foreach ($snmp_queries as $snmp_query) {
		start_box("<strong>SNMP Query</strong> [" . $snmp_query["name"] . "]", "98%", $colors["header"], "3", "center", "");
		
		$data = implode("",file(str_replace("<path_cacti>", $paths["cacti"], $snmp_query["xml_path"])));
		$snmp_queries = xml2array($data);
		$xml_outputs = array();
		
		print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		
		$i = 0;
		while (list($field_name, $field_array) = each($snmp_queries["fields"][0])) {
			$i++;
			$field_array = $field_array[0];
			
			if (($i+1) < count($snmp_queries["fields"][0])) { $colspan = 1; }else{ $colspan = 2; }
			
			if ($field_array["direction"] == "input") {
				DrawMatrixHeaderItem($field_array["name"],$colors["header_text"],$colspan);
				$raw_data = db_fetch_assoc("select field_value,snmp_index from host_snmp_cache where host_id=" . $_GET["id"] . " and field_name='$field_name'");
				
				if (sizeof($raw_data) > 0) {
				foreach ($raw_data as $data) {
					$snmp_query_data[$field_name]{$data["snmp_index"]} = $data["field_value"];
					$snmp_query_indexes{$data["snmp_index"]} = $data["snmp_index"];
				}
				}
			}
		}
		
		print "</tr>";
		
		if (sizeof($snmp_query_indexes) > 0) {
		while (list($snmp_index, $snmp_index) = each($snmp_query_indexes)) {
			DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i); $i++;
			
			reset($snmp_query_data);
			while (list($field_name, $field_array) = each($snmp_query_data)) {
				print "<td>" . $field_array[$snmp_index] . "</td>";
			}
			
			DrawFormItemCheckBox("sg_" . $snmp_query["graph_template_id"] . "_" . $snmp_query["id"] . "_" . $snmp_index,"","","");
			print "</tr>\n";
		}
		}
		
		end_box();
	}
	}
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "host.php");?>
		</td>
	</tr>
	</form>
	<?
	end_box();
}

function host() {
	global $colors;
	
	display_output_messages();
	
	start_box("<strong>Polling Hosts</strong>", "98%", $colors["header"], "3", "center", "host.php?action=edit");
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Description",$colors["header_text"],1);
		DrawMatrixHeaderItem("Hostname",$colors["header_text"],1);
		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
	print "</tr>";
    
	$hosts = db_fetch_assoc("select id,hostname,description from host order by description");
	
	if (sizeof($hosts) > 0) {
	foreach ($hosts as $host) {
		DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="host.php?action=edit&id=<?print $host["id"];?>"><?print $host["description"];?></a>
			</td>
			<td>
				<?print $host["hostname"];?>
			</td>
			<td width="1%" align="right">
				<a href="host.php?action=remove&id=<?print $host["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	}
	}
	end_box();
}
?>
