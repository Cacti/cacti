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
	
	session_start();
	//print $HTTP_SESSION_VARS['user_id'];
	
	$tree_id = $HTTP_GET_VARS["tree_id"];
	
	$user_id = GetCurrentUserID($HTTP_SESSION_VARS['user_id'], $config["guest_user"]["value"]);
	
	/* at this point this user is good to go... so get some setting about this
	user and put them into variables to save excess SQL in the future */
	$graph_policy = db_fetch_cell("select GraphPolicy from auth_users where id=$user_id");
	$config["graph_policy"]["auth"] = $graph_policy;
	
	/* load all of the custom per-user graph settings */
	$array_settings = LoadSettingsIntoArray($HTTP_SESSION_VARS['user_id'], $config["guest_user"]["value"]); 
	
	/* set the default action if none has been set */
	if (($action != "tree") && ($action != "list") && ($action != "preview")) {
		switch ($array_settings["global"]["defaultviewmode"]) {
			case '1':
				$action = "tree"; break;
			case '2':
				$action = "list"; break;
			case '3':
				$action = "preview"; break;
		}
	}
	
	/* if cookie has been set, use it */
	if (isset($tree_id) == false) {
		if (isset($HTTP_SESSION_VARS["tree_id"]) == true) {
			$tree_id = $HTTP_SESSION_VARS["tree_id"];
		}
	}else{
		$HTTP_SESSION_VARS["tree_id"] = $tree_id;
	}
	
	$current_script_name = basename($HTTP_SERVER_VARS["SCRIPT_NAME"]);
	?>
<html>
<head>
	<title>cacti</title>
	<?
	$page_refresh = $array_settings["preview"]["pagerefresh"];
	echo "<meta http-equiv=refresh content=\"$page_refresh\"; url=\"$PHP_SELF?$QUERY_STRING\">\r\n";
	?>
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

<table width="100%" cellspacing="0" cellpadding="0">
	<tr>
		<td bgcolor="#454E53" colspan="<?print $array_settings["preview"]["columnnumber"];?>" nowrap>
			<map name="tabs">
				<area alt="Console" coords="7,5,87,35" href="index.php">
				<area alt="Graphs" coords="166,5,241,32" href="graph_view.php?action=tree" shape="RECT">
				<area alt="Documentation" coords="88,5,165,32" href="docs/MANUAL.htm">
			</map>
			<table border=0 cellpadding=0 cellspacing=0 width='100%'><tr><td valign=bottom width=36><a href='about.php'><img
	        src="images/cactus.png" border=0 height=54 width=36></a></td><td width=250 valign=bottom><img
	        src="images/top_tabs_main.gif" border="0" width=250 height=32 usemap="#tabs"></td><td align=right><img src="images/cacti_raxnet.gif" border=0></td></tr></table></td>
		<td bgcolor="#454E53" align="right" nowrap>
			<?if (isset($HTTP_SESSION_VARS["user_id"])){?><a href="logout.php"><img src="images/top_tabs_logout.gif" border="0" alt="Logout"></a><?}?><a href="graph_settings.php"><img src="images/top_tabs_graph_settings<?if (basename($SCRIPT_FILENAME) == "graph_settings.php") { print "_down"; }?>.gif" border="0" alt="Settings"></a><a href="graph_view.php?action=tree"><img src="images/top_tabs_graph_tree<?if ($action == "tree") { print "_down"; }?>.gif" border="0" alt="Tree View"></a><a href="graph_view.php?action=list"><img src="images/top_tabs_graph_list<?if ($action == "list") { print "_down"; }?>.gif" border="0" alt="List View"></a><a href="graph_view.php?action=preview"><img src="images/top_tabs_graph_preview<?if ($action == "preview") { print "_down"; }?>.gif" border="0" alt="Preview View"></a><br>
		</td>
	</tr>
	<tr>
		<td colspan="3" bgcolor="#<?print $colors[panel];?>">
			<img src="images/transparent_line.gif" width="170" height="5" border="0"><br>
		</td>
	</tr>
	<tr>
	<?if ($action == "tree") {?>
		<form name="form_tree_id">
			<?
			//include ("include/database.php");
			
			if ($config["global_auth"]["value"] == "on") {
				if ($config["graph_policy"]["auth"] == "1") {
					$sql_where = "where agh.userid is null";
				}elseif ($config["graph_policy"]["auth"] == "2") {
					$sql_where = "where agh.userid is not null";
				}
				
				$tree_list = db_fetch_assoc("select gh.*,agh.userid
					from graph_tree_view gh 
					left join auth_graph_hierarchy agh on (gh.id=agh.hierarchyid and agh.userid=" . GetCurrentUserID($HTTP_SESSION_VARS['user_id'], $config["guest_user"]["value"]) . ") 
					$sql_where
					order by gh.name");
			}else{
				$tree_list = db_fetch_assoc("select * from graph_tree_view order by name");
			}
			
			//$rows = mysql_num_rows($sql_id); $i = 0;
			
			/* set a default tree if none is already selected */
			if (isset($tree_id) == false) {
				if (isset($array_settings["hierarchical"]["treeid"]) == true) {
					$tree_id = $array_settings["hierarchical"]["treeid"];
				}else{
					if (sizeof($tree_list) != 0) {
						$tree_id = db_fetch_cell("select id from graph_tree_view");
					}
				}
			}
			
			/* make the dropdown list of trees */
			if (sizeof($tree_list) > 1) {
				?>
			<td valign="middle" height="30" colspan="3" bgcolor="#<?print $colors[panel];?>">
				&nbsp;&nbsp;Select a Graph Hierarchy:&nbsp;
				<select name="cbo_tree_id" onChange="window.location=document.form_tree_id.cbo_tree_id.options[document.form_tree_id.cbo_tree_id.selectedIndex].value">
					<?foreach ($tree_list as $tree) {?>
						<option value="graph_view.php?action=tree&tree_id=<?print $tree[id]; ?>"
						<?if ($tree_id == $tree[id]) { ?>
						selected <? $tree_name = $tree[name]; } ?>><? print $tree[name]; ?></option>
						<?
					} ?>
				</select>
				<?
			/* there is only one tree; use it */
			}elseif (sizeof($tree_list) == 1) {?>
			<td valign="middle" height="5" colspan="3" bgcolor="#<?print $colors[panel];?>">
				<?$tree_id = db_fetch_cell("select id from graph_tree_view");;
				$tree_name = db_fetch_cell("select name from graph_tree_view");;
			}
			?>
		</td>
		</form>
	<?}else{?>
		<td height="5" colspan="3" bgcolor="#<?print $colors[panel];?>">
			
		</td>
	<?}?>
	</tr>
	<?if ($showinfo == true) {?>
	<tr>
		<td valign="top" height="1" colspan="3" bgcolor="#<?print $colors[panel];?>">
			<?$graph_data_array["print_source"] = true;
			print trim(rrdtool_function_graph($graphid, $rraid, $graph_data_array));?>
		</td>
	</tr>
	<tr height="5" bgcolor="#<?print $color_dark_outline;?>"><td colspan="3"><img src="images/transparent_line.gif" width="20" height="5" border="0"></td></tr>
	<?}?>
</table>



<table width="100%" cellspacing="0" cellpadding="0"
	<tr height="5"><td colspan="3">&nbsp;</td></tr>
	<tr>
		<td bgcolor="#<?print $colors[light];?>" width="1%"></td>
		<td valign="top" colspan="2">
