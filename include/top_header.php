<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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

global $config, $menu, $refresh;

$oper_mode = api_plugin_hook_function('top_header', OPER_MODE_NATIVE);
if ($oper_mode == OPER_MODE_RESKIN) {
	return;
}

$page_title = api_plugin_hook_function('page_title', draw_navigation_text("title"));

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<meta http-equiv="X-UA-Compatible" content="edge">
	<title><?php echo $page_title; ?></title>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
	<link href="<?php echo $config['url_path']; ?>include/main.css" type="text/css" rel="stylesheet">
	<link href="<?php echo $config['url_path']; ?>include/js/jquery-ui.css" type="text/css" rel="stylesheet">
	<link href="<?php echo $config['url_path']; ?>include/js/themes/proton/style.css" type="text/css" rel="stylesheet">
	<link href="<?php echo $config['url_path']; ?>images/favicon.ico" rel="shortcut icon">
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/layout.js"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/js/jquery.js" language="javascript"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/js/jquery-ui.js" language="javascript"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/js/jquery.cookie.js" language="javascript"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/js/jstree.js"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/jscalendar/calendar.js"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/jscalendar/lang/calendar-en.js"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/jscalendar/calendar-setup.js"></script>
	<?php
	if (isset($refresh)) {
		if (is_array($refresh)) {
			print "<meta http-equiv=refresh content='" . htmlspecialchars($refresh["seconds"],ENT_QUOTES) . "'; url='" . htmlspecialchars($refresh["page"],ENT_QUOTES) . "'>\r\n";
		}else{
			print "<meta http-equiv=refresh content='" . htmlspecialchars($refresh,ENT_QUOTES) . "'>\r\n";
		}
	}
	api_plugin_hook('page_head'); ?>
</head>

<?php if ($oper_mode == OPER_MODE_NATIVE) {?>
<body style="background-image:url('<?php print $config['url_path'];?>images/left_border.gif');background-repeat:repeat-y;" <?php print api_plugin_hook_function("body_style", "");?>>
<?php }else{?>
<body style="background-image:url('<?php print $config['url_path'];?>images/left_border.gif');background-repeat:repeat-y;" <?php print api_plugin_hook_function("body_style", "");?>>
<?php }?>

<table style="width:100%" cellspacing="0" cellpadding="0">
<?php if ($oper_mode == OPER_MODE_NATIVE) { ;?>
	<tr class='cactiPageHead noprint'>
		<td valign="bottom" colspan="3">
			<table width="100%" cellspacing="0" cellpadding="0">
				<tr class='cactiConsolePageHeadBackdrop'>
					<td id="tabs" valign="bottom">
						&nbsp;<a href="<?php echo $config['url_path']; ?>index.php"><img src="<?php echo $config['url_path']; ?>images/tab_console_down.gif" alt="Console" align="absmiddle" border="0"></a><a href="<?php echo $config['url_path']; ?>graph_view.php"><img src="<?php echo $config['url_path']; ?>images/tab_graphs.gif" alt="Graphs" align="absmiddle" border="0"></a><?php
						api_plugin_hook('top_header_tabs');
					?></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr class='breadCrumbBar noprint'>
		<td colspan="3">
			<table width="100%">
				<tr>
					<td>
						<?php echo draw_navigation_text();?>
					</td>
					<td align="right">
						<?php if (read_config_option("auth_method") != 0) { api_plugin_hook('nav_login_before'); ?>
							Logged in as <strong><?php print db_fetch_cell("select username from user_auth where id=" . $_SESSION["sess_user_id"]);?></strong> (<a href="<?php echo $config['url_path']; ?>logout.php">Logout</a>)&nbsp;
							<?php api_plugin_hook('nav_login_after'); } ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td id='navigation' class='cactiConsoleNavigationArea' valign='top'>
			<table>
				<?php draw_menu();?>
				<tr>
					<td>
						<p style="width:135px;" align="center"><a href='<?php echo $config['url_path']; ?>about.php'><img src="<?php echo $config['url_path']; ?>images/cacti_logo.gif" border="0"></a></p>
					</td>
				</tr>
			</table>
		</td>
		<td class='cactiConsoleContentArea' width="100%" valign="top"><?php display_output_messages();?><div style='position:relative;' id='main'>
<?php }else{ ?>
	<tr>
		<td class='cactiConsoleContentArea' width="100%" valign="top"><?php display_output_messages();?><div style='position:relative;' id='main'>
<?php } ?>
