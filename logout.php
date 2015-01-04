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

include('./include/auth.php');

global $config;

api_plugin_hook('logout_pre_session_destroy');

/* Clear session */
setcookie(session_name(),'',time() - 3600,'/');
session_destroy();

$version = db_fetch_cell('SELECT cacti FROM version');

api_plugin_hook('logout_post_session_destroy');

/* Check to see if we are using Web Basic Auth */
if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'timeout') {
	print "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>\n";
	print "<html>\n";
	print "<head>\n";
	print "\t<title>Logout of Cacti</title>\n";
	print "\t<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>\n";
	print "\t<link href='" . $config['url_path'] . "include/themes/" . read_config_option('selected_theme') . "/main.css' type='text/css' rel='stylesheet'>\n";
    print "\t<link href='" . $config['url_path'] . "include/themes/" . read_config_option('selected_theme') . "/jquery-ui.css' type='text/css' rel='stylesheet'>\n";
	print "\t<link href='" . $config['url_path'] . "images/favicon.ico' rel='shortcut icon'>\n";
	print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.js' language='javascript'></script>\n";
	print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery-ui.js' language='javascript'></script>\n";
	print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.cookie.js' language='javascript'></script>\n";
	print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.hotkeys.js'></script>\n";
	print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/layout.js'></script>\n";
	print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/themes/" . read_config_option('selected_theme') . "/main.js'></script>\n";
	print "<script type='text/javascript'>var theme='" . read_config_option('selected_theme') . "';</script>\n";
	print "</head>\n";
	print "<body class='logoutBody'>
	<div class='logoutLeft'></div>
	<div class='logoutCenter'>
		<div class='logoutArea'>
			<div class='cactiLogoutLogo'></div>
			<legend>Automatic Logout</legend>
			<div class='logoutTitle'>
				<p>You have been logged out of Cacti due to a session timeout.</p>
				<p>Please close your broser or</p>
				<center>[<a href='index.php'>Login Again</a>]</center>
			</div>
			<div class='logoutErrors'></div>
		</div>
		<div class='versionInfo'>Version " . $version . " | " . COPYRIGHT_YEARS_SHORT . "</div>
	</div>
	<div class='logoutRight'></div>
	<script type='text/javascript'>
	$(function() {
		$('.loginLeft').css('width',parseInt($(window).width()*0.33)+'px');
		$('.loginRight').css('width',parseInt($(window).width()*0.33)+'px');
	});
	</script>
	</body>
	</html>\n";
}elseif (read_config_option('auth_method') == '2') {
	clear_auth_cookie();

	if (api_plugin_hook_function('custom_logout_message', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
		exit;
	}

	print "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>\n";
	print "<html>\n";
	print "<head>\n";
	print "\t<title>Logout of Cacti</title>\n";
	print "\t<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>\n";
	print "\t<link href='" . $config['url_path'] . "include/themes/" . read_config_option('selected_theme') . "/main.css' type='text/css' rel='stylesheet'>\n";
    print "\t<link href='" . $config['url_path'] . "include/themes/" . read_config_option('selected_theme') . "/jquery-ui.css' type='text/css' rel='stylesheet'>\n";
	print "\t<link href='" . $config['url_path'] . "images/favicon.ico' rel='shortcut icon'>\n";
	print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.js' language='javascript'></script>\n";
	print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery-ui.js' language='javascript'></script>\n";
	print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.cookie.js' language='javascript'></script>\n";
	print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.hotkeys.js'></script>\n";
	print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/layout.js'></script>\n";
	print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/themes/" . read_config_option('selected_theme') . "/main.js'></script>\n";
	print "<script type='text/javascript'>var theme='" . read_config_option('selected_theme') . "';</script>\n";
	print "</head>\n";
	print "<body class='logoutBody'>
	<div class='logoutLeft'></div>
	<div class='logoutCenter'>
		<div class='logoutArea'>
			<div class='cactiLogoutLogo'></div>
			<legend>Automatic Logout</legend>
			<div class='logoutTitle'>
				<p>You have been logged out of Cacti. To end your session,</p>
				<p>Please close your broser or</p>
				<center>[<a href='index.php'>Return to Cacti</a>]</center>
			</div>
			<div class='logoutErrors'></div>
		</div>
		<div class='versionInfo'>Version " . $version . " | " . COPYRIGHT_YEARS_SHORT . "</div>
	</div>
	<div class='logoutRight'></div>
	<script type='text/javascript'>
	$(function() {
		$('.loginLeft').css('width',parseInt($(window).width()*0.33)+'px');
		$('.loginRight').css('width',parseInt($(window).width()*0.33)+'px');
	});
	</script>
	</body>
	</html>\n";
}else{
	/* Default action */
	clear_auth_cookie();

	header('Location: index.php');
}

