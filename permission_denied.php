<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

$version = CACTI_VERSION_TEXT;;

if (isset($_SERVER['HTTP_REFERER'])) {
	$goBack = "<td colspan='2' class='center'>[<a href='" . $_SERVER['HTTP_REFERER'] . "'>" . __('Return') . "</a> | <a href='" . $config['url_path'] . "logout.php'>" . __('Login Again') . "</a>]</td>";
} else {
	$goBack = "<td colspan='2' class='center'>[<a href='#' onClick='window.history.back()'>" . __('Return') . "</a> | <a href='" . $config['url_path'] . "logout.php'>" . __('Login Again') . "</a>]</td>";
}

/* allow for plugin based permission denied page */
if (api_plugin_hook_function('custom_denied', OPER_MODE_NATIVE) === OPER_MODE_RESKIN) {
	exit;
}

raise_ajax_permission_denied();

print "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>\n";
print "<html>\n";
print "<head>\n";
html_common_header(__('Permission Denied'));
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

