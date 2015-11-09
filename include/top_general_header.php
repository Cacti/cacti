<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2015 The Cacti Group                                 |
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

//<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv='X-UA-Compatible' content='IE=edge'>
	<meta content='width=720, initial-scale=1.2, maximum-scale=1.2, minimum-scale=1.2' name='viewport'>
	<title><?php echo $page_title; ?></title>
	<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/main.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/jquery.zoom.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/jquery-ui.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/default/style.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/jquery.multiselect.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/jquery.timepicker.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/jquery.colorpicker.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/pace.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/fa/css/font-awesome.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>images/favicon.ico' rel='shortcut icon'>
	<?php include($config['base_path'] . '/include/global_session.php');?>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery-ui.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.cookie.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.storageapi.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jstree.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.hotkeys.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.zoom.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.multiselect.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.timepicker.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.colorpicker.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/pace.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/realtime.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/layout.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/main.js'></script>
	<?php api_plugin_hook('page_head'); ?>
</head>
<body>
<div id='cactiPageHead' class='cactiPageHead'>
	<?php if ($oper_mode == OPER_MODE_NATIVE) { ;?>
	<div id='tabs'><?php html_show_tabs_left(true);?></div>
	<div class='cactiConsolePageHeadBackdrop'></div>
</div>
<div id='breadCrumbBar' class='breadCrumbBar'>
	<div id='navBar' class='navBar'><?php echo draw_navigation_text();?></div>
	<div class='scrollBar'></div>
	<div class='infoBar'><?php echo draw_login_status($using_guest_account);?></div>
</div>
<div id='cactiContent' class='cactiContent'>
	<?php if (isset($user_menu) && is_array($user_menu)) {?>
	<div id='navigation' class='cactiConsoleNavigationArea'>
		<table style='width:100%;'>
			<?php draw_menu($user_menu);?>
			<tr>
				<td style='text-align:center;'>
					<div class='cactiLogo' onClick='document.location="about.php";'></div>
				</td>
			</tr>
		</table>
	</div>
	<div id='navigation_right' class='cactiConsoleContentArea'>
		<div style='display:none;' id='message_container'><?php display_output_messages();?></div>
		<div style='position:relative;' id='main'>
	<?php } else { ?>
	<div id='navigation_right' class='cactiConsoleContentArea' style='margin-left:0px;'>
		<div style='display:none;' id='message_container'><?php display_output_messages();?></div>
		<div style='position:relative;' id='main'>
	<?php } ?>
<?php } else { ?>
	<div id='navigation_right' class='cactiConsoleContentArea'>
		<div style='display:none;' id='message_container'><?php display_output_messages();?></div>
		<div style='position:relative;' id='main'>
<?php } ?>
