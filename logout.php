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

include('./include/auth.php');

global $config;

set_default_action();

api_plugin_hook('logout_pre_session_destroy');

/* Clear session */
setcookie(session_name(), '', time() - 3600, $config['url_path']);
session_destroy();

$version = get_cacti_version();

api_plugin_hook('logout_post_session_destroy');

/* Check to see if we are using Web Basic Auth */
if (get_request_var('action') == 'timeout') {
	$selectedTheme = get_selected_theme();

	print "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>\n";
	print "<html>\n";
	print "<head>\n";
	print "\t<title>Logout of Cacti</title>\n";
	print "\t<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>\n";
	print "\t<link href='" . $config['url_path'] . "include/themes/" . $selectedTheme . "/images/favicon.ico' rel='shortcut icon'>";
	print "\t<link href='" . $config['url_path'] . "include/themes/" . $selectedTheme . "/images/cacti_logo.gif' rel='icon' sizes='96x96'>";
	print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery.zoom.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery-ui.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/default/style.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery.multiselect.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery.timepicker.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/jquery.colorpicker.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/c3.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/pace.css');
	print get_md5_include_css('include/fa/css/font-awesome.css');
	print get_md5_include_css('include/themes/' . $selectedTheme .'/main.css');
	print get_md5_include_js('include/js/screenfull.js');
	print get_md5_include_js('include/js/jquery.js');
	print get_md5_include_js('include/js/jquery-migrate.js');
	print get_md5_include_js('include/js/jquery-ui.js');
	print get_md5_include_js('include/js/jquery.ui.touch.punch.js');
	print get_md5_include_js('include/js/jquery.cookie.js');
	print get_md5_include_js('include/js/js.storage.js');
	print get_md5_include_js('include/js/jstree.js');
	print get_md5_include_js('include/js/jquery.hotkeys.js');
	print get_md5_include_js('include/js/jquery.tablednd.js');
	print get_md5_include_js('include/js/jquery.zoom.js');
	print get_md5_include_js('include/js/jquery.multiselect.js');
	print get_md5_include_js('include/js/jquery.multiselect.filter.js');
	print get_md5_include_js('include/js/jquery.timepicker.js');
	print get_md5_include_js('include/js/jquery.colorpicker.js');
	print get_md5_include_js('include/js/jquery.tablesorter.js');
	print get_md5_include_js('include/js/jquery.tablesorter.widgets.js');
	print get_md5_include_js('include/js/jquery.tablesorter.pager.js');
	print get_md5_include_js('include/js/jquery.metadata.js');
	print get_md5_include_js('include/js/jquery.sparkline.js');
	print get_md5_include_js('include/js/Chart.js');
	print get_md5_include_js('include/js/dygraph-combined.js');
	print get_md5_include_js('include/js/d3.js');
	print get_md5_include_js('include/js/c3.js');
	print get_md5_include_js('include/js/pace.js');
	print get_md5_include_js('include/realtime.js');
	print get_md5_include_js('include/layout.js');
	print get_md5_include_js('include/themes/' . $selectedTheme .'/main.js');
	print "<script type='text/javascript'>var theme='" . $selectedTheme . "';</script>\n";
	print "</head>\n";
	print "<body class='logoutBody'>
	<div class='logoutLeft'></div>
	<div class='logoutCenter'>
		<div class='logoutArea'>
			<div class='cactiLogoutLogo'></div>
			<legend>" . __('Automatic Logout') . "</legend>
			<div class='logoutTitle'>
				<p>" . __('You have been logged out of Cacti due to a session timeout.') . "</p>
				<p>" . __('Please close your browser or %sLogin Again%s', '</p><center>[<a href="index.php">', '</a>]</center>') . "
			</div>
			<div class='logoutErrors'></div>
		</div>
		<div class='versionInfo'>" . __('Version %s', $version) . " | " . COPYRIGHT_YEARS_SHORT . "</div>
	</div>
	<div class='logoutRight'></div>
	<script type='text/javascript'>
	$(function() {
		$('.loginLeft').css('width',parseInt($(window).width()*0.33)+'px');
		$('.loginRight').css('width',parseInt($(window).width()*0.33)+'px');
	});
	</script>";
	include('./include/global_session.php');
	print "</body>
	</html>\n";
} elseif (read_config_option('auth_method') == '2') {
	clear_auth_cookie();

	if (api_plugin_hook_function('custom_logout_message', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
		exit;
	}

	$selectedTheme = get_selected_theme();

	print "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>\n";
	print "<html>\n";
	print "<head>\n";
	print "\t<title>Logout of Cacti</title>\n";
	print "\t<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>\n";
	print "\t" . get_md5_include_css('include/themes/" . $selectedTheme . "/main.css')."\n";
	print "\t" . get_md5_include_css('include/themes/" . $selectedTheme . "/jquery-ui.css')."\n";
	print "\t<link href='images/favicon.ico' rel='shortcut icon'>\n";
	print "\t" . get_md5_include_js('include/js/jquery.js') . "\n";
	print "\t" . get_md5_include_js('include/js/jquery-migrate.js') . "\n";
	print "\t" . get_md5_include_js('include/js/jquery-ui.js') . "\n";
	print "\t" . get_md5_include_js('include/js/jquery.cookie.js') . "\n";
	print "\t" . get_md5_include_js('include/js/jquery.tablesorter.js') . "\n";
	print "\t" . get_md5_include_js('include/js/jquery.tablesorter.widgets.js') . "\n";
	print "\t" . get_md5_include_js('include/js/jquery.tablesorter.pager.js') . "\n";
	print "\t" . get_md5_include_js('include/js/js.storage.js') . "\n";
	print "\t" . get_md5_include_js('include/js/jquery.hotkeys.js') . "\n";
	print "\t" . get_md5_include_js('include/layout.js') . "\n";
	print "\t" . get_md5_include_js('include/themes/" . $selectedTheme . "/main.js') . "\n";
	print "<script type='text/javascript'>var theme='" . $selectedTheme . "';</script>\n";
	print "</head>\n";
	print "<body class='logoutBody'>
	<div class='logoutLeft'></div>
	<div class='logoutCenter'>
		<div class='logoutArea'>
			<div class='cactiLogoutLogo'></div>
			<legend>" . __('Automatic Logout') . "</legend>
			<div class='logoutTitle'>
				<p>" . __('You have been logged out of Cacti due to a session timeout.') . "</p>
				<p>" . __('Please close your browser or %sLogin Again%s', '</p><center>[<a href="index.php">', '</a>]</center>') . "
			</div>
			<div class='logoutErrors'></div>
		</div>
	</div>
	<div class='logoutRight'></div>
	<script type='text/javascript'>
	$(function() {
		$('.loginLeft').css('width',parseInt($(window).width()*0.33)+'px');
		$('.loginRight').css('width',parseInt($(window).width()*0.33)+'px');
	});
	</script>";
	include('./include/global_session.php');
	print "</body>
	</html>\n";
} else {
	/* Default action */
	clear_auth_cookie();

	header('Location: index.php');
}

