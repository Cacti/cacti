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
<?	include ('include/config.php');
	include ('include/config_arrays.php');
	
	$section = "View Graphs"; include_once ("include/auth.php");
	
	session_start();
	
	/* at this point this user is good to go... so get some setting about this
	user and put them into variables to save excess SQL in the future */
	$current_user = db_fetch_cell("select * from user where id=" . $_SESSION["sess_user_id"]);
		
	/* set the default action if none has been set */
	if (!ereg('^(tree|list|preview)$', $_GET["action"])) {
		if (read_graph_config_option("default_view_mode") == "1") {
			$_GET["action"] = "tree";
		}elseif (read_graph_config_option("default_view_mode") == "2") {
			$_GET["action"] = "list";
		}elseif (read_graph_config_option("default_view_mode") == "2") {
			$_GET["action"] = "preview";
		}
	}
	
	?>
<html>
<head>
	<title>cacti</title>
	<?print "<meta http-equiv=refresh content='" . read_graph_config_option("page_refresh") . "'; url='" . $_SERVER["SCRIPT_NAME"] . "'>\r\n";?>
	<STYLE TYPE="text/css">
	<!--	
		BODY, TABLE, TR, TD {font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10px;}
		BODY {background-color: #F5F5F5;}
		A {text-decoration: none;}
		A:active { text-decoration: none;}
		A:hover {text-decoration: underline; color: #333333;}
		A:visited {color: Blue;}
		.textHeader {font-size: 12px; font-weight: bold;}
		.textHeaderDark {font-size: 12px; font-weight: bold; color: #ffffff;}
		.textArea {font-size: 12px;}
	-->
	</style>
</head>

<body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0">

<map name="tabs">
	<area alt="Console" coords="7,5,87,35" href="index.php">
	<area alt="Graphs" coords="88,5,165,32" href="graph_view.php?action=tree" shape="RECT">
</map>

<table width="100%" cellspacing="0" cellpadding="0">
	<tr>
		<td bgcolor="#454E53" nowrap>
			<table border=0 cellpadding=0 cellspacing=0 width='100%'><tr><td valign=bottom width=36></td><td width=250 valign=bottom><img src="images/top_tabs_main.gif" border="0" width=250 height=32 usemap="#tabs"></td></tr></table></td>
		<td bgcolor="#454E53" align="right" nowrap width='99%'>
			<?if (isset($_SESSION["sess_user_id"])){?><a href="logout.php"><img src="images/top_tabs_logout.gif" border="0" alt="Logout"></a><?}?><a href="graph_settings.php"><img src="images/top_tabs_graph_settings<?if (basename($_SERVER["SCRIPT_FILENAME"]) == "graph_settings.php") { print "_down"; }?>.gif" border="0" alt="Settings"></a><a href="graph_view.php?action=tree"><img src="images/top_tabs_graph_tree<?if ($action == "tree") { print "_down"; }?>.gif" border="0" alt="Tree View"></a><a href="graph_view.php?action=list"><img src="images/top_tabs_graph_list<?if ($action == "list") { print "_down"; }?>.gif" border="0" alt="List View"></a><a href="graph_view.php?action=preview"><img src="images/top_tabs_graph_preview<?if ($action == "preview") { print "_down"; }?>.gif" border="0" alt="Preview View"></a><br>
		</td>
	</tr>
	<tr>
		<td colspan="3" bgcolor="#<?print $colors["panel"];?>">
			<img src="images/transparent_line.gif" width="170" height="5" border="0"><br>
		</td>
	</tr>
	<tr>
	<?
	if ($_GET["action"] == "tree") {
		print "<form name='form_tree_id'>";
		
		if (read_config_option("global_auth") == "on") {
			if ($current_user["graph_policy"] == "1") {
				$sql_where = "where user_auth_tree.user_id is null";
			}elseif ($current_user["graph_policy"] == "2") {
				$sql_where = "where user_auth_tree.user_id is not null";
			}
			
			$tree_list = db_fetch_assoc("select
				graph_tree_view.id,
				graph_tree_view.name,
				user_auth_tree.user_id
				from graph_tree_view
				left join user_auth_tree on (graph_tree_view.id=user_auth_tree.tree_id and user_auth_tree.user_id=" . $_SESSION["sess_user_id"] . ") 
				$sql_where
				order by graph_tree_view.name");
		}else{
			$tree_list = db_fetch_assoc("select * from graph_tree_view order by name");
		}
		
		if (isset($_GET["tree_id"])) {
			$_SESSION["sess_view_tree_id"] = $_GET["tree_id"];
		}
		
		/* set a default tree if none is already selected */
		if (empty($_SESSION["sess_view_tree_id"])) {
			if (read_graph_config_option("default_tree_id")) {
				$_SESSION["sess_view_tree_id"] = read_graph_config_option("default_tree_id");
			}else{
				if (sizeof($tree_list) > 0) {
					$_SESSION["sess_view_tree_id"] = $tree_list[0]["id"];
				}
			}
		}
		
		/* make the dropdown list of trees */
		if (sizeof($tree_list) > 1) {
			print "	<td valign='middle' height='30' colspan='3' bgcolor='#" . $colors["panel"] . "'>
					&nbsp;&nbsp;Select a Graph Hierarchy:&nbsp;
					<select name='cbo_tree_id' onChange='window.location=document.form_tree_id.cbo_tree_id.options[document.form_tree_id.cbo_tree_id.selectedIndex].value'>";
			
			foreach ($tree_list as $tree) {
				print "	<option value='graph_view.php?action=tree&tree_id=" . $tree["id"] . "'";
					if ($_SESSION["sess_view_tree_id"] == $tree["id"]) { print " selected"; }
					print ">" . $tree["name"] . "</option>\n";
				}
			
			print "</select>\n";
		}elseif (sizeof($tree_list) == 1) {
			/* there is only one tree; use it */
			print "	<td valign='middle' height='5' colspan='3' bgcolor='#" . $colors["panel"] . "'>";
		}
		
		print "</td></form>\n";
	}else{
		print "<td height='5' colspan='3' bgcolor='#" . $colors["panel"] . "'></td>\n";
	}
	?>
	</tr>
	<?if ($_GET["showinfo"] == true) {?>
	<tr>
		<td valign="top" height="1" colspan="3" bgcolor="#<?print $colors[panel];?>">
			<?
			$graph_data_array["print_source"] = true;
			print trim(rrdtool_function_graph($graphid, $rraid, $graph_data_array));
			?>
		</td>
	</tr>
	<?}?>
</table>

<table width="100%" cellspacing="0" cellpadding="0">
	<tr height="5"><td>&nbsp;</td></tr>
	<tr>
		<td valign="top">
