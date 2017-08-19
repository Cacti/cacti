<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

include('./include/auth.php');

$version = get_cacti_version();

if (isset($_SERVER['HTTP_REFERER'])) {
	$goBack = "<td colspan='2' align='center'>[<a href='" . htmlspecialchars($_SERVER['HTTP_REFERER']) . "'>" . __('Return') . "</a> | <a href='" . $config['url_path'] . "logout.php'>" . __('Login Again') . "</a>]</td>";
} else {
	$goBack = "<td colspan='2' align='center'>[<a href='#' onClick='window.history.back()'>" . __('Return') . "</a> | <a href='" . $config['url_path'] . "logout.php'>" . __('Login Again') . "</a>]</td>";
}

$selectedTheme = get_selected_theme();

print "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>\n";
print "<html>\n";
print "<head>\n";
print "\t<title>Permission Denied</title>\n";
print "\t<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>\n";
print "\t<link href='" . $config['url_path'] . "include/themes/" . $selectedTheme . "/main.css' type='text/css' rel='stylesheet'>\n";
print "\t<link href='" . $config['url_path'] . "include/themes/" . $selectedTheme . "/jquery-ui.css' type='text/css' rel='stylesheet'>\n";
print "\t<link href='" . $config['url_path'] . "include/fa/css/font-awesome.css' type='text/css' rel='stylesheet'>\n";
print "\t<link href='" . $config['url_path'] . "images/favicon.ico' rel='shortcut icon'>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.js' language='javascript'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery-migrate.js' language='javascript'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery-ui.js' language='javascript'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.tablesorter.js'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.tablesorter.widgets.js'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.tablesorter.pager.js'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/js.storage.js'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.cookie.js' language='javascript'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.hotkeys.js'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/layout.js'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/themes/" . $selectedTheme . "/main.js'></script>\n";
print "<script type='text/javascript'>var theme='" . $selectedTheme . "';</script>\n";
print "</head>\n";
print "<body class='logoutBody'>
	<div class='logoutLeft'></div>
	<div class='logoutCenter'>
		<div class='logoutArea'>
			<div class='cactiLogoutLogo'></div>
			<legend>" . __('Permission Denied') . "</legend>
			<div class='logoutTitle'>
				<p>" . __('You are not permitted to access this section of Cacti.') . '</p><p>' . __('If you feel that this is an error. Please contact your Cacti Administrator.') . "</p>
				<center>" . $goBack . "</center>
			</div>
			<div class='logoutErrors'></div>
		</div>
		<div class='versionInfo'>" . __('Version') . " " . $version . " | " . COPYRIGHT_YEARS_SHORT . "</div>
	</div>
	<div class='logoutRight'></div>
	<script type='text/javascript'>
	$(function() {
		$('.loginLeft').css('width',parseInt($(window).width()*0.33)+'px');
		$('.loginRight').css('width',parseInt($(window).width()*0.33)+'px');
	});
	</script>";

include_once('./include/global_session.php');

print "</body>
</html>\n";
exit;

