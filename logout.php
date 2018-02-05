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
	print "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>\n";
	print "<html>\n";
	print "<head>\n";
	html_common_header(__('Logout of Cacti'));
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

	print "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>\n";
	print "<html>\n";
	print "<head>\n";
	html_common_header(__('Logout of Cacti'));
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

