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
print "\t<title>" . __('Permission Denied') . "</title>\n";
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
print "<script type='text/javascript'>var theme='" . $selectedTheme . "';<script>\n";
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

