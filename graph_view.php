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

include_once ("include/functions.php");
include_once ("include/form.php");
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

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

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
	
	$access_denied = false;
	$tree_parameters = array();
	
	print "<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='3'><tr>\n";
	draw_tree_dropdown((isset($_GET["tree_id"]) ? $_GET["tree_id"] : "0"));
	print "</tr></table>";
	
	print "<br>";
	
	if (isset($_SESSION["sess_view_tree_id"])) {
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
		
		grow_graph_tree($_SESSION["sess_view_tree_id"], (!empty($start_branch) ? $start_branch : 0), $_SESSION["sess_user_id"], $tree_parameters);
	}
	
	print "<br><br>";
	
	break;
case 'preview':
	define("ROWS_PER_PAGE", 10);
	
	$sql_or = "";
	
	if ($current_user["show_preview"] == "") {
		print "<strong><font size='+1' color='FF0000'>YOU DO NOT HAVE RIGHTS FOR PREVIEW VIEW</font></strong>"; exit;
	}
	
	if (isset($_REQUEST["host_id"])) {
		$_SESSION["sess_graph_view_host"] = $_REQUEST["host_id"];
		$_REQUEST["page"] = "1";
	}elseif (isset($_SESSION["sess_graph_view_host"])) {
		$_REQUEST["host_id"] = $_SESSION["sess_graph_view_host"];
	}else{
		$_REQUEST["host_id"] = "0"; /* default value */
	}
	
	if (isset($_REQUEST["filter"])) {
		$_SESSION["sess_graph_view_filter"] = $_REQUEST["filter"];
		$_REQUEST["page"] = "1";
	}elseif (isset($_SESSION["sess_graph_view_filter"])) {
		$_REQUEST["filter"] = $_SESSION["sess_graph_view_filter"];
	}else{
		$_REQUEST["filter"] = ""; /* default value */
	}
	
	/* restore the page num from the session var if it is currently stored */
	if (isset($_REQUEST["page"])) {
		$_SESSION["sess_graph_view_current_page"] = $_REQUEST["page"];
	}elseif (isset($_SESSION["sess_graph_view_current_page"])) {
		$_REQUEST["page"] = $_SESSION["sess_graph_view_current_page"];
	}else{
		$_REQUEST["page"] = "1"; /* default value */
	}
	
	/* graph permissions */
	if (read_config_option("global_auth") == "on") {
		if ($current_user["graph_policy"] == "1") {
			$sql_where = "where user_auth_graph.user_id is null";
		}elseif ($current_user["graph_policy"] == "2") {
			$sql_where = "where user_auth_graph.user_id is not null";
		}
		
		$sql_join = "left join user_auth_graph on (graph_templates_graph.local_graph_id=user_auth_graph.local_graph_id and user_auth_graph.user_id=" . $_SESSION["sess_user_id"] . ")";
	}
	
	/* the user select a bunch of graphs of the 'list' view and wants them dsplayed here */
	if (isset($_GET["style"])) {
		if ($_GET["style"] == "selective") {
			$i = 0;
			while (list($var, $val) = each($_GET)) {
				if (ereg('^graph_([0-9]+)$', $var, $matches)) {
					$graph_array[$i] = $matches[1];
					$i++;
				}
			}
			
			if (sizeof($graph_array) > 0) {
				/* build sql string including each graph the user checked */
				$sql_or = "and " . array_to_sql_or($graph_array, "graph_templates_graph.local_graph_id");
				
				/* clear the filter vars so they don't affect our results */
				$_REQUEST["filter"] = "";
				$_REQUEST["host_id"] = "0";
				
				$set_rra_id = $_GET["rra_id"];
			}
		}
	}
	
	if (empty($_REQUEST["host_id"])) {
		$sql_base = "from graph_templates_graph 
			$sql_join
			$sql_where
			" . (empty($sql_where) ? "where" : "and") . " graph_templates_graph.local_graph_id > 0
			and graph_templates_graph.title like '%%" . $_REQUEST["filter"] . "%%'
			$sql_or";
	}else{
		$sql_base = "from graph_templates_graph 
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
			group by graph_templates_graph.id";
	}
	
	$total_rows = count(db_fetch_assoc("select 
		graph_templates_graph.local_graph_id
		$sql_base"));
	$graphs = db_fetch_assoc("select 
		graph_templates_graph.local_graph_id,
		graph_templates_graph.title
		$sql_base
		order by graph_templates_graph.title
		limit " . (ROWS_PER_PAGE*($_REQUEST["page"]-1)) . "," . ROWS_PER_PAGE);
			
	print "<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='3'>";
	
	?>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_graph_id" method="post">
		<td colspan='<?php print read_graph_config_option("num_columns");?>'>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="120" class="textHeader">
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
	
	/* do some fancy navigation url construction so we don't have to try and rebuild the url string */
	if (ereg("page=[0-9]+",basename($_SERVER["QUERY_STRING"]))) {
		$nav_url = str_replace("page=" . $_REQUEST["page"], "page=<PAGE>", basename($_SERVER["PHP_SELF"]) . "?" . $_SERVER["QUERY_STRING"]);
	}else{
		$nav_url = basename($_SERVER["PHP_SELF"]) . "?" . $_SERVER["QUERY_STRING"] . "&page=<PAGE>";
	}
	
	$nav_url = ereg_replace("((\?|&)host_id=[0-9]+|(\?|&)filter=[a-zA-Z0-9]*)", "", $nav_url);
	
	print "	</table>
		<br>
		<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>
			<tr bgcolor='#" . $colors["header_panel"] . "'>
				<td colspan='" . read_graph_config_option("num_columns") . "'>
					<table width='100%' cellspacing='0' cellpadding='3' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { print "<a class='linkOverDark' href='" . str_replace("<PAGE>", ($_REQUEST["page"]-1), $nav_url) . "'>"; } print "Previous"; if ($_REQUEST["page"] > 1) { print "</a>"; } print "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . ((ROWS_PER_PAGE*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < ROWS_PER_PAGE) || ($total_rows < (ROWS_PER_PAGE*$_REQUEST["page"]))) ? $total_rows : (ROWS_PER_PAGE*$_REQUEST["page"])) . " of $total_rows
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * ROWS_PER_PAGE) < $total_rows) { print "<a class='linkOverDark' href='" . str_replace("<PAGE>", ($_REQUEST["page"]+1), $nav_url) . "'>"; } print "Next"; if (($_REQUEST["page"] * ROWS_PER_PAGE) < $total_rows) { print "</a>"; } print " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n
			<tr>";
	
	$i = 0; $k = 0;
	if (sizeof($graphs) > 0) {
	foreach ($graphs as $graph) {
		print "<td align='center' width='" . (98 / read_graph_config_option("num_columns")) . "%'><a href='graph.php?rra_id=all&local_graph_id=" . $graph["local_graph_id"] . "'><img src='graph_image.php?local_graph_id=" . $graph["local_graph_id"] . "&rra_id=" . (empty($set_rra_id) ? read_graph_config_option("default_rra_id") : $set_rra_id) . "&graph_start=-" . (empty($set_rra_id) ? read_graph_config_option("timespan") : "0") . "&graph_height=" . read_graph_config_option("default_height") . "&graph_width=" . read_graph_config_option("default_width") . "&graph_nolegend=true' border='0' alt='" . $graph["title"] . "'></a></td>\n";
		
		$i++;
		$k++;
		
		if (($i == read_graph_config_option("num_columns")) && ($k < count($graphs))) {
			$i = 0;
			print "</tr><tr>";
		}
	}
	}else{
		print "<td><em>No Graphs Found.</em></td>";
	}
	
	print "</tr></table>";
	print "<br><br>";
	
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
	
	print "<form action='graph_view.php' method='get'>\n";
	print "<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>";
	print "<tr bgcolor='#" . $colors["header_panel"] . "'><td colspan='3'><table cellspacing='0' cellpadding='3' width='100%'><tr><td class='textHeaderDark'><strong>Displaying " . sizeof($graphs) . " Graph" . ((sizeof($graphs) == 1) ? "" : "s") . "</strong></td></tr></table></td></tr>";
	
	$i = 0;
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
					form_base_dropdown("rra_id", db_fetch_assoc("select id,name,(rra.rows*rra.steps) as rra_order from rra order by rra_order,name"), "name", "id", "1", "", "");
	print "			</td>
				<td>	
					<input type='image' src='images/button_view.gif' alt='View'>
				</td>
			</tr>
		</table><br><br>\n
	<input type='hidden' name='page' value='1'>
	<input type='hidden' name='style' value='selective'>\n
	<input type='hidden' name='action' value='preview'>\n
	</form>\n";
	
	break;
}

//print "<pre>";print $_SESSION["sess_debug_buffer"];print "</pre>";session_unregister("sess_debug_buffer");

include_once ("include/bottom_footer.php");
