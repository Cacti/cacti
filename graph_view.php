<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

include_once ("include/rrd_functions.php");
include_once ("include/functions.php");
include ("include/top_graph_header.php");

//if (isset($args[hide]) == true) {
	/* find out if the current user has rights here */
//	$graph_settings = db_fetch_cell("select graph_settings from user where id=$user_id");
	
	/* only update expand/contract info is this user has rights to keep their own settings */
//	if ($graph_settings == "on") {
//		db_execute("delete from settings_viewing_tree where treeitemid=$args[branch_id] and userid=$user_id");
//		db_execute("insert into settings_viewing_tree (treeitemid,userid,status) values ($args[branch_id],$user_id,$args[hide])");
//	}
//}

switch ($_REQUEST["action"]) {
case 'tree':
	include_once ('include/tree_view_functions.php');
	
	if ($current_user["show_tree"] == "") {
		print "<strong><font size='+1' color='FF0000'>YOU DO NOT HAVE RIGHTS FOR TREE VIEW</font></strong>"; exit;
	}
	
	/* if cacti's builtin authentication is turned on then make sure to take 
	graph permissions into account here. if a user does not have rights to a 
	particular graph; do not show it. they will get an access denied message
	if they try and view the graph directly. */
	
	if (read_config_option("global_auth") == "on") {
		/* take tree permissions into account here, if the user does not have permission
		give an "access denied" message */
		$user_auth = db_fetch_row("select user_id from user_auth_tree where tree_id=" . $_SESSION["sess_view_tree_id"] . " and user_id=" . $_SESSION["sess_user_id"]);
		
		if ($current_user["graph_policy"] == "1") {
			if (sizeof($user_auth) > 0) { $access_denied = true; }
		}elseif ($current_user["graph_policy"] == "2") {
			if (sizeof($user_auth) == 0) { $access_denied = true; }
		}
		
		if ($access_denied == true) {
			print "<strong><font size='+1' color='FF0000'>ACCESS DENIED</font></strong>"; exit;
		}
	}
	
	print "	<table width='98%' align='center'>
			<tr>
				<td>
					<strong><a href='graph_view.php?action=tree&tree_id=" . $_SESSION["sess_view_tree_id"] . "'>[root]</a> - " . db_fetch_cell("select name from graph_tree where id=" . $_SESSION["sess_view_tree_id"]) . "</strong>
				</td>
			</tr>
		</table>
		<br>\n";
	
	grow_graph_tree($_SESSION["sess_view_tree_id"], $start_branch, $user_id, $tree_parameters);
	
	break;
case 'preview':
	if ($current_user["show_preview"] == "") {
		print "<strong><font size='+1' color='FF0000'>YOU DO NOT HAVE RIGHTS FOR PREVIEW VIEW</font></strong>"; exit;
	}
	
	/* if no host_id is specified, use the session one */
	if (!isset($_REQUEST["host_id"])) {
		$_REQUEST["host_id"] = $_SESSION["sess_graph_view_host"];
	}
	
	/* if no filter is specified, use the session one */
	if (!isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = $_SESSION["sess_graph_view_filter"];
	}
	
	/* remember the last used vars */
	$_SESSION["sess_graph_view_host"] = $_REQUEST["host_id"];
	$_SESSION["sess_graph_view_filter"] = $_REQUEST["filter"];
	
	/* graph permissions */
	if (read_config_option("global_auth") == "on") {
		if ($current_user["graph_policy"] == "1") {
			$sql_where = "where user_auth_graph.user_id is null";
		}elseif ($current_user["graph_policy"] == "2") {
			$sql_where = "where user_auth_graph.user_id is not null";
		}
		
		$sql_join = "left join user_auth_graph on (graph_templates_graph.local_graph_id=user_auth_graph.local_graph_id and user_auth_graph.user_id=" . $_SESSION["sess_user_id"] . ")";
	}
	
	if (empty($_REQUEST["host_id"])) {
		$graphs = db_fetch_assoc("select 
			graph_templates_graph.local_graph_id,
			graph_templates_graph.title
			from graph_templates_graph 
			$sql_join
			$sql_where
			" . (empty($sql_where) ? "where" : "and") . " graph_templates_graph.local_graph_id > 0
			and graph_templates_graph.title like '%%" . $_REQUEST["filter"] . "%%'
			order by graph_templates_graph.title");
	}else{
		$graphs = db_fetch_assoc("select 
			graph_templates_graph.local_graph_id,
			graph_templates_graph.title
			from graph_templates_graph 
			left join graph_templates on graph_templates_graph.graph_template_id=graph_templates.id
			left join graph_local on graph_templates_graph.local_graph_id=graph_local.id
			left join graph_templates_item on graph_local.id=graph_templates_item.local_graph_id
			left join data_template_rrd on graph_templates_item.task_item_id=data_template_rrd.id
			left join data_local on data_template_rrd.local_data_id=data_local.id
			$sql_join
			$sql_where
			" . (empty($sql_where) ? "where" : "and") . " data_local.host_id=" . $_REQUEST["host_id"] . "
			and graph_templates_graph.local_graph_id>0
			and graph_templates_graph.title like '%%" . $_REQUEST["filter"] . "%%'
			group by graph_templates_graph.id
			order by graph_templates_graph.title");
	}
			
	print "<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='3'>";
	
	?>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_graph_id" method="post">
		<td colspan='<?php print read_graph_config_option("num_columns");?>'>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="100">
						Filter by host:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
							<option value="graph_view.php?action=preview&host_id=0&filter=<?php print $_REQUEST["filter"];?>"<?php if ($_REQUEST["host_id"] == "0") {?> selected<?php }?>>None</option>
							
							<?php
							$hosts = db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname");
							
							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='graph_view.php?action=preview&host_id=" . $host["id"] . "&filter=" . $_REQUEST["filter"] . "'"; if ($_REQUEST["host_id"] == $host["id"]) { print " selected"; } print ">" . $host["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="5"></td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td>
						&nbsp;<input type="image" src="images/button_go.gif" alt="Go" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>	
	<?php
	
	
	print "<tr>";
	
	if (sizeof($graphs) > 0) {
	foreach ($graphs as $graph) {
		print "<td align='center' width='" . (98 / read_graph_config_option("num_columns")) . "%'><a href='graph.php?rra_id=all&local_graph_id=" . $graph["local_graph_id"] . "'><img src='graph_image.php?local_graph_id=" . $graph["local_graph_id"] . "&rra_id=" . read_graph_config_option("default_rra_id") . "&graph_start=-" . read_graph_config_option("timespan") . "&graph_height=" . read_graph_config_option("default_height") . "&graph_width=" . read_graph_config_option("default_width") . "&graph_nolegend=true' border='0' alt='" . $graph["title"] . "'></a></td>\n";
		
		$i++;
		$k++;
		
		if (($i == read_graph_config_option("num_columns")) && ($k < sizeof($graphs))) {
			$i = 0;
			print "</tr><tr>";
		}
	}
	}else{
		print "<td><em>No Graphs Found.</em></td>";
	}
	
	print "</tr></table>";
	
	break;
case 'list':
	include_once("include/form.php");
	
	if ($current_user["show_list"] == "") {
		print "<strong><font size='+1' color='FF0000'>YOU DO NOT HAVE RIGHTS FOR LIST VIEW</font></strong>"; exit;
	}
	
	/* graph permissions */
	if (read_config_option("global_auth") == "on") {
		if ($current_user["graph_policy"] == "1") {
			$sql_where = "where user_auth_graph.user_id is null";
		}elseif ($current_user["graph_policy"] == "2") {
			$sql_where = "where user_auth_graph.user_id is not null";
		}
		
		$graphs = db_fetch_assoc("select 
			graph_templates_graph.local_graph_id,
			graph_templates_graph.title,
			graph_templates_graph.height,
			graph_templates_graph.width
			from graph_templates_graph 
			left join user_auth_graph on (graph_templates_graph.local_graph_id=user_auth_graph.local_graph_id and user_auth_graph.user_id=" . $_SESSION["sess_user_id"] . ") 
			$sql_where
			and graph_templates_graph.local_graph_id > 0
			order by graph_templates_graph.title");
	}else{
		$graphs = db_fetch_assoc("select 
			local_graph_id,title,height,width
			from graph_templates_graph 
			where local_graph_id > 0
			order by title");
	}
	
	print "<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>";
	
	if (sizeof($graphs) > 0) {
	foreach ($graphs as $graph) {
		form_alternate_row_color("f5f5f5", "ffffff", $i);
		
		print "<td width='1%'>";
		form_base_checkbox("graph_" . $graph["local_graph_id"], "", "", "", 0, false);
		print "</td>";
		
		print "<td><strong><a href='graph.php?local_graph_id=" . $graph["local_graph_id"] . "&rra_id=all'>" . $graph["title"] . "</a></strong></td>\n";
		print "<td>" . $graph["height"] . "x" . $graph["width"] . "</td>\n";
		print "</tr>";
		
		$i++;
	}
	}
	
	print "</table>";
	
	print "	<table align='center' width='98%'>
			<tr>
				<td width='1'>
					<img src='images/arrow.gif' alt='' align='absmiddle'>&nbsp;
				</td>
				<td width='1'>";
					form_base_dropdown("rra_id", db_fetch_assoc("select id,name from rra order by name"), "name", "id", "1", "", "");
	print "			</td>
				<td>	
					<input type='image' src='images/button_view.gif' alt='View'>
				</td>
			</tr>
		</table><br><br>\n";
	
	
	break;
}

print "<pre>";print $_SESSION["sess_debug_buffer"];print "</pre>";session_unregister("sess_debug_buffer");

include_once ("include/bottom_footer.php");
