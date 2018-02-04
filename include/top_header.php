<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
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

$selectedTheme = get_selected_theme();
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv='X-UA-Compatible' content='IE=edge'>
	<meta content='width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0' name='viewport'>
	<meta name='apple-mobile-web-app-capable' content='yes'>
	<meta name='mobile-web-app-capable' content='yes'>
	<title><?php echo $page_title; ?></title>
	<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print $selectedTheme;?>/images/favicon.ico' rel='shortcut icon'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print $selectedTheme;?>/images/cacti_logo.gif' rel='icon' sizes='96x96'>
	<?php print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery.zoom.css'); ?>
	<?php print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery-ui.css'); ?>
	<?php print get_md5_include_css('include/themes/' . $selectedTheme .'/default/style.css'); ?>
	<?php print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery.multiselect.css'); ?>
	<?php print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery.timepicker.css'); ?>
	<?php print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery.colorpicker.css'); ?>
	<?php print get_md5_include_css('include/themes/' . $selectedTheme .'/c3.css'); ?>
	<?php print get_md5_include_css('include/themes/' . $selectedTheme .'/pace.css'); ?>
	<?php print get_md5_include_css('include/fa/css/font-awesome.css'); ?>
	<?php print get_md5_include_css('include/themes/' . $selectedTheme .'/main.css'); ?>
	<?php print get_md5_include_js('include/js/screenfull.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.js'); ?>
	<?php print get_md5_include_js('include/js/jquery-migrate.js'); ?>
	<?php print get_md5_include_js('include/js/jquery-ui.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.ui.touch.punch.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.cookie.js'); ?>
	<?php print get_md5_include_js('include/js/js.storage.js'); ?>
	<?php print get_md5_include_js('include/js/jstree.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.hotkeys.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.tablednd.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.zoom.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.multiselect.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.multiselect.filter.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.timepicker.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.colorpicker.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.tablesorter.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.tablesorter.widgets.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.tablesorter.pager.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.metadata.js'); ?>
	<?php print get_md5_include_js('include/js/jquery.sparkline.js'); ?>
	<?php print get_md5_include_js('include/js/Chart.js'); ?>
	<?php print get_md5_include_js('include/js/dygraph-combined.js'); ?>
	<?php print get_md5_include_js('include/js/d3.js'); ?>
	<?php print get_md5_include_js('include/js/c3.js'); ?>
	<?php print get_md5_include_js('include/js/pace.js'); ?>
	<?php print get_md5_include_js('include/realtime.js'); ?>
	<?php print get_md5_include_js('include/layout.js');?>
	<?php print get_md5_include_js('include/themes/' . $selectedTheme .'/main.js'); ?>
	<script type='text/javascript'>var theme='<?php print $selectedTheme;?>';</script>
	<?php api_plugin_hook('page_head'); ?>
</head>
<body>
<div id='cactiPageHead' class='cactiPageHead' role='banner'>
	<?php if ($oper_mode == OPER_MODE_NATIVE) { ;?>
	<div id='tabs'><?php html_show_tabs_left();?></div>
	<div class='cactiConsolePageHeadBackdrop'></div>
</div>
<div id='breadCrumbBar' class='breadCrumbBar'>
	<div id='navBar' class='navBar'><?php echo draw_navigation_text();?></div>
	<div class='scrollBar'></div>
	<?php if (read_config_option('auth_method') != 0) {?><div class='infoBar'><?php echo draw_login_status($using_guest_account);?></div><?php }?>
</div>
<div class='cactiShadow'></div>
<div id='cactiContent' class='cactiContent'>
	<div class='cactiConsoleNavigationArea' style='overflow:hidden'>
		<div style='display:none;' id='navigation'>
			<table style='width:100%;'>
				<?php draw_menu();?>
				<tr>
					<td style='text-align:center;'>
						<div class='cactiLogo' onclick='loadPage("<?php print $config['url_path'];?>about.php")'></div>
					</td>
				</tr>
			</table>
		</div>
	</div>
	<div id='navigation_right' class='cactiConsoleContentArea'>
		<div class='messageContainer' id='message_container'><?php display_output_messages();?></div>
		<div style='position:relative;display:none;' id='main' role='main'>
<?php } else { ?>
	<div id='navigation_right' class='cactiConsoleContentArea'>
		<div class='messageContainer' id='message_container'><?php display_output_messages();?></div>
		<div style='position:relative;display:none;' id='main' role='main'>
<?php } ?>
