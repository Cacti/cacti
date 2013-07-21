<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

global $menu;
$using_guest_account = false;
$show_console_tab = true;

$oper_mode = api_plugin_hook_function('top_graph_header', OPER_MODE_NATIVE);
if ($oper_mode == OPER_MODE_RESKIN) {
	return;
}

/* ================= input validation ================= */
input_validate_input_number(get_request_var_request("local_graph_id"));
input_validate_input_number(get_request_var_request("graph_start"));
input_validate_input_number(get_request_var_request("graph_end"));
/* ==================================================== */

if (read_config_option("auth_method") != 0) {
	/* at this point this user is good to go... so get some setting about this
	user and put them into variables to save excess SQL in the future */
	$current_user = db_fetch_row("select * from user_auth where id=" . $_SESSION["sess_user_id"]);

	/* find out if we are logged in as a 'guest user' or not */
	if (db_fetch_cell("select id from user_auth where username='" . read_config_option("guest_user") . "'") == $_SESSION["sess_user_id"]) {
		$using_guest_account = true;
	}

	/* find out if we should show the "console" tab or not, based on this user's permissions */
	if (sizeof(db_fetch_assoc("select realm_id from user_auth_realm where realm_id=8 and user_id=" . $_SESSION["sess_user_id"])) == 0) {
		$show_console_tab = false;
	}
}

/* need to correct $_SESSION["sess_nav_level_cache"] in zoom view */
if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "zoom") {
	$_SESSION["sess_nav_level_cache"][2]["url"] = "graph.php?local_graph_id=" . $_REQUEST["local_graph_id"] . "&rra_id=all";
}

$page_title = api_plugin_hook_function('page_title', draw_navigation_text("title"));

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=EmulateIE7">
	<title><?php echo $page_title; ?></title>
	<?php
	if (isset($_SESSION["custom"]) && $_SESSION["custom"] == true) {
		print "<meta http-equiv=refresh content='99999'>";
	}else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == 'zoom') {
		print "<meta http-equiv=refresh content='99999'>";
	}else{
		$refresh = api_plugin_hook_function('top_graph_refresh', htmlspecialchars(read_graph_config_option("page_refresh"),ENT_QUOTES));
		if (is_array($refresh)) {
			print "<meta http-equiv=refresh content='" . htmlspecialchars($refresh["seconds"],ENT_QUOTES) . "'; url='" . htmlspecialchars($refresh["page"],ENT_QUOTES) . "'>\r\n";
		}else{
			print "<meta http-equiv=refresh content='" . htmlspecialchars($refresh,ENT_QUOTES) . "'>\r\n";
		}
	}
	?>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
	<link href="<?php echo $config['url_path']; ?>include/main.css" type="text/css" rel="stylesheet">
	<link href="<?php echo $config['url_path']; ?>images/favicon.ico" rel="shortcut icon"/>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/layout.js"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/treeview/ua.js"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/treeview/ftiens4.js"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/jscalendar/calendar.js"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/jscalendar/lang/calendar-en.js"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/jscalendar/calendar-setup.js"></script>
	<?php api_plugin_hook('page_head'); ?>
</head>

<?php if ($oper_mode == OPER_MODE_NATIVE) {?>
<body <?php print api_plugin_hook_function("body_style", "");?>>
<a name='page_top'></a>
<?php }else{?>
<body <?php print api_plugin_hook_function("body_style", "");?>>
<?php }?>

<table style="width:100%;height:100%;" cellspacing="0" cellpadding="0">
<?php if ($oper_mode == OPER_MODE_NATIVE) { ;?>
	<tr style="height:25px;" bgcolor="#a9a9a9" class="noprint">
		<td colspan="2" valign="bottom" nowrap>
			<table width="100%" cellspacing="0" cellpadding="0">
				<tr style="background: transparent url('<?php echo $config['url_path']; ?>images/cacti_backdrop2.gif') no-repeat center right;">
					<td id="tabs" nowrap>
						&nbsp;<?php if ($show_console_tab == true) {?><a href="<?php echo $config['url_path']; ?>index.php"><img src="<?php echo $config['url_path']; ?>images/tab_console.gif" alt="Console" align="absmiddle" border="0"></a><?php }?><a href="<?php echo $config['url_path']; ?>graph_view.php"><img src="<?php echo $config['url_path']; ?>images/tab_graphs<?php if ((substr(basename($_SERVER["PHP_SELF"]),0,5) == "graph") || (basename($_SERVER["PHP_SELF"]) == "graph_settings.php")) { print "_down"; } print ".gif";?>" alt="Graphs" align="absmiddle" border="0"></a><?php
						api_plugin_hook('top_graph_header_tabs');
						?>
					</td>
					<td id="gtabs" align="right" nowrap>
						<?php if ((!isset($_SESSION["sess_user_id"])) || ($current_user["graph_settings"] == "on")) { print '<a href="' . $config['url_path'] . 'graph_settings.php"><img src="' . $config['url_path'] . 'images/tab_settings'; if (basename($_SERVER["PHP_SELF"]) == "graph_settings.php") { print "_down"; } print '.gif" border="0" alt="Settings" align="absmiddle"></a>';}?>&nbsp;&nbsp;<?php if ((!isset($_SESSION["sess_user_id"])) || ($current_user["show_tree"] == "on")) {?><a href="<?php print htmlspecialchars($config['url_path'] . "graph_view.php?action=tree");?>"><img src="<?php echo $config['url_path']; ?>images/tab_mode_tree<?php if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "tree") { print "_down"; }?>.gif" border="0" title="Tree View" alt="Tree View" align="absmiddle"></a><?php }?><?php if ((!isset($_SESSION["sess_user_id"])) || ($current_user["show_list"] == "on")) {?><a href="<?php print htmlspecialchars($config['url_path'] . "graph_view.php?action=list");?>"><img src="<?php echo $config['url_path']; ?>images/tab_mode_list<?php if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "list") { print "_down"; }?>.gif" border="0" title="List View" alt="List View" align="absmiddle"></a><?php }?><?php if ((!isset($_SESSION["sess_user_id"])) || ($current_user["show_preview"] == "on")) {?><a href="<?php print htmlspecialchars($config['url_path'] . "graph_view.php?action=preview");?>"><img src="<?php echo $config['url_path']; ?>images/tab_mode_preview<?php if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "preview") { print "_down"; }?>.gif" border="0" title="Preview View" alt="Preview View" align="absmiddle"></a><?php }?>&nbsp;<br>
					</td>
				</tr>
			</table>
		</td>
	</tr>
<?php } elseif ($oper_mode == OPER_MODE_NOTABS) { api_plugin_hook_function('print_top_header'); } ?>
	<tr style="height:2px;" bgcolor="#183c8f" class="noprint">
		<td colspan="2">
			<img src="<?php echo $config['url_path']; ?>images/transparent_line.gif" style="height:2px;width:170px;" border="0"><br>
		</td>
	</tr>
	<tr style="height:5px;" bgcolor="#e9e9e9" class="noprint">
		<td colspan="2">
			<table width="100%">
				<tr>
					<td>
						<?php echo draw_navigation_text();?>
					</td>
					<td align="right">
						<?php if ((isset($_SESSION["sess_user_id"])) && ($using_guest_account == false)) { api_plugin_hook('nav_login_before'); ?>
						Logged in as <strong><?php print db_fetch_cell("select username from user_auth where id=" . $_SESSION["sess_user_id"]);?></strong> (<a href="<?php echo $config['url_path']; ?>logout.php">Logout</a>)&nbsp;
						<?php api_plugin_hook('nav_login_after'); } ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr class="noprint">
		<td bgcolor="#efefef" colspan="1" style="height:8px;background-image: url(<?php echo $config['url_path']; ?>images/shadow_gray.gif); background-repeat: repeat-x; border-right: #aaaaaa 1px solid;">
			<img src="<?php echo $config['url_path']; ?>images/transparent_line.gif" width="<?php print htmlspecialchars(read_graph_config_option("default_dual_pane_width"));?>" style="height:2px;" border="0"><br>
		</td>
		<td bgcolor="#ffffff" colspan="1" style="height:8px;background-image: url(<?php echo $config['url_path']; ?>images/shadow.gif); background-repeat: repeat-x;">

		</td>
	</tr>

	<?php if ((basename($_SERVER["PHP_SELF"]) == "graph.php") && ($_REQUEST["action"] == "properties")) {?>
	<tr>
		<td valign="top" style="height:1px;" colspan="3" bgcolor="#efefef">
			<?php
			$graph_data_array["print_source"] = true;

			/* override: graph start time (unix time) */
			if (!empty($_GET["graph_start"])) {
				$graph_data_array["graph_start"] = get_request_var_request("graph_start");
			}

			/* override: graph end time (unix time) */
			if (!empty($_GET["graph_end"])) {
				$graph_data_array["graph_end"] = get_request_var_request("graph_end");
			}

			print trim(@rrdtool_function_graph(get_request_var_request("local_graph_id"), get_request_var_request("rra_id"), $graph_data_array));
			?>
		</td>
	</tr>
	<?php }

	global $graph_views;
	load_current_session_value("action", "sess_cacti_graph_action", $graph_views[read_graph_config_option("default_tree_view_mode")]);
	?>
	<tr>
		<?php if (basename($_SERVER["PHP_SELF"]) == "graph_view.php" && (read_graph_config_option("default_tree_view_mode") == 2) && ($_REQUEST["action"] == "tree" || (isset($_REQUEST["view_type"]) && $_REQUEST["view_type"] == "tree"))) { ?>
		<td valign="top" style="padding: 5px; border-right: #aaaaaa 1px solid;background-repeat:repeat-y;background-color:#efefef;" bgcolor='#efefef' width='<?php print htmlspecialchars(read_graph_config_option("default_dual_pane_width"));?>' class='noprint'>
			<table border=0 cellpadding=0 cellspacing=0><tr><td><a style="font-size:7pt;text-decoration:none;color:silver" href="http://www.treemenu.net/" target=_blank></a></td></tr></table>
			<?php grow_dhtml_trees(); ?>
			<script type="text/javascript">initializeDocument();</script>

			<?php if (isset($_GET["select_first"])) { ?>
			<script type="text/javascript">
			var tobj;
			tobj = findObj(1);

			if (tobj) {
				if (!tobj.isOpen) {
					clickOnNode(1);
				}
			}
			</script>
			<?php } ?>
		</td>
		<?php } ?>
		<td valign="top" style="padding: 5px; border-right: #aaaaaa 1px solid;"><div style='position:static;' id='main'>
