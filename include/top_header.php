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

global $config, $menu;

$oper_mode = api_plugin_hook_function('top_header', OPER_MODE_NATIVE);
if ($oper_mode == OPER_MODE_RESKIN) {
	return;
}

$page_title = api_plugin_hook_function('page_title', draw_navigation_text('title'));
$using_guest_account = false;

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<meta http-equiv='X-UA-Compatible' content='edge'>
	<title><?php echo $page_title; ?></title>
	<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/main.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/jquery.zoom.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/jquery-ui.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/default/style.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/fa/css/font-awesome.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>images/favicon.ico' rel='shortcut icon'>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.js' language='javascript'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery-ui.js' language='javascript'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.cookie.js' language='javascript'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jstree.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.hotkeys.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/jscalendar/calendar.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/jscalendar/lang/calendar-en.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/jscalendar/calendar-setup.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/layout.js'></script>
	<script type='text/javascript'>var theme='<?php print read_config_option('selected_theme');?>';</script>
	<?php include($config['base_path'] . '/include/global_session.php'); api_plugin_hook('page_head'); ?>
</head>

<?php if ($oper_mode == OPER_MODE_NATIVE) {?>
<body class='cactiConsoleBody' <?php print api_plugin_hook_function('body_style', '');?>>
<?php }else{?>
<body class='cactiConsoleBody' <?php print api_plugin_hook_function('body_style', '');?>>
<?php }?>

<table style='width:100%' cellspacing='0' cellpadding='0'>
<?php if ($oper_mode == OPER_MODE_NATIVE) { ;?>
	<tr class='cactiPageHead noprint'>
		<td class='cactiConsolePageHeadBackdrop' valign='bottom' colspan='3'>
			<table width='100%' cellspacing='0' cellpadding='0'>
				<tr>
					<td id='tabs' valign='bottom'>
						<?php html_show_tabs_left(true);?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr class='breadCrumbBar noprint'>
		<td colspan='3'>
			<table width='100%'>
				<tr>
					<td>
						<div id='navBar' class='navBar'>
						<?php echo draw_navigation_text();?>
						</div>
						<div class='scrollBar'></div>
						<div class='infoBar' style='float:right;'>
						<?php echo draw_login_status($using_guest_account);?>
						</div>
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
					<td align='center'>
						<div class='cactiLogo' onClick='document.location="about.php";'>
						</div>
					</td>
				</tr>
			</table>
		</td>
		<td class='cactiConsoleContentArea' width='100%' valign='top'><div style='display:none;' id='message_container'><?php display_output_messages();?></div><div style='position:relative;' id='main'>
<?php }else{ ?>
	<tr>
		<td class='cactiConsoleContentArea' width='100%' valign='top'><div style='display:none;' id='message_container'><?php display_output_messages();?></div><div style='position:relative;' id='main'>
<?php } ?>
