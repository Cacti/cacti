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

global $current_user;

include('./include/global.php');

if (!isset($config['cacti_db_version'])) {
	$version = get_cacti_version();
	$config['cacti_db_version'] = $version;
} else {
	$version = $config['cacti_db_version'];
}

if ($version != CACTI_VERSION && $config['poller_id'] == 1) {
	header ('Location: ' . $config['url_path'] . 'install/');
	exit;
}

if (get_current_page() == 'logout.php') {
	return true;
}

if (read_config_option('auth_method') != 0) {
	/* handle alternate authentication realms */
	api_plugin_hook_function('auth_alternate_realms');

	/* handle change password dialog */
	if ((isset($_SESSION['sess_change_password'])) && (read_config_option('webbasic_enabled') != 'on')) {
		header ('Location: ' . $config['url_path'] . 'auth_changepassword.php?ref=' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php'));
		exit;
	}

	/* check for remember me function ality */
	if (!isset($_SESSION['sess_user_id'])) {
		$cookie_user = check_auth_cookie();
		if ($cookie_user !== false) {
			$_SESSION['sess_user_id'] = $cookie_user;
		}
	}

	/* don't even bother with the guest code if we're already logged in */
	if ((isset($guest_account)) && (empty($_SESSION['sess_user_id']))) {
		$guest_user_id = db_fetch_cell_prepared('SELECT id FROM user_auth WHERE username = ? AND realm = 0 AND enabled = "on"', array(read_config_option('guest_user')));

		/* cannot find guest user */
		if (!empty($guest_user_id)) {
			$_SESSION['sess_user_id'] = $guest_user_id;
			return true;
		}
	}

	/* if we are a guest user in a non-guest area, wipe credentials */
	if (!empty($_SESSION['sess_user_id'])) {
		if ((!isset($guest_account)) && (db_fetch_cell_prepared('SELECT id FROM user_auth WHERE username = ?', array(read_config_option('guest_user'))) == $_SESSION['sess_user_id'])) {
			kill_session_var('sess_user_id');
		}
	}

	if (empty($_SESSION['sess_user_id'])) {
		include($config['base_path'] . '/auth_login.php');
		exit;
	} elseif (!empty($_SESSION['sess_user_id'])) {
		$realm_id = 0;

		if (isset($user_auth_realm_filenames[get_current_page()])) {
			$realm_id = $user_auth_realm_filenames[get_current_page()];
		}

		if ($realm_id > 0) {
			$authorized = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM (
					SELECT realm_id
					FROM user_auth_realm AS uar
					WHERE uar.user_id = ?
					AND uar.realm_id = ?
					UNION
					SELECT realm_id
					FROM user_auth_group_realm AS uagr
					INNER JOIN user_auth_group_members AS uagm
					ON uagr.group_id=uagm.group_id
					INNER JOIN user_auth_group AS uag
					ON uag.id=uagr.group_id
					WHERE uag.enabled="on"
					AND uagm.user_id = ?
					AND uagr.realm_id = ?
				) AS authorized',
				array($_SESSION['sess_user_id'], $realm_id, $_SESSION['sess_user_id'], $realm_id));
		} else {
			$authorized = false;
		}

		if ($realm_id != -1 && !$authorized) {
			if (isset($_SERVER['HTTP_REFERER'])) {
				$goBack = "<td colspan='2' align='center'>[<a href='" . htmlspecialchars($_SERVER['HTTP_REFERER']) . "'>" . __('Return') . "</a> | <a href='" . $config['url_path'] . "logout.php'>" . __('Login Again') . "</a>]</td>";
			} else {
				$goBack = "<td colspan='2' align='center'>[<a href='" . $config['url_path'] . "logout.php'>" . __('Login Again') . "</a>]</td>";
			}

			$selectedTheme = get_selected_theme();

			print "<!DOCTYPE html>\n";
			print "<html>\n";
			print "<head>\n";
			print "\t<title>" . __('Permission Denied') . "</title>\n";
			print "\t<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>\n";
			print "\t<meta name='robots' content='noindex,nofollow'>\n";
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
					<legend>" . __('Permission Denied') . "</legend>
					<div class='logoutTitle'>
						<p>" . __('You are not permitted to access this section of Cacti.') . '</p><p>' . __('If you feel that this is an error. Please contact your Cacti Administrator.') .
						"</p>
						<center>" . $goBack . "</center>
					</div>
					<div class='logoutErrors'></div>
				</div>
				<div class='versionInfo'>" . __('Version') . ' ' . $version . " | " . COPYRIGHT_YEARS_SHORT . "</div>
			</div>
			<div class='logoutRight'></div>
			<script type='text/javascript'>
			$(function() {
				$('.loginLeft').css('width',parseInt($(window).width()*0.33)+'px');
				$('.loginRight').css('width',parseInt($(window).width()*0.33)+'px');
			});
			</script>\n";
			include_once('./include/global_session.php');
			print "</body>
			</html>\n";
			exit;
		}

		$current_user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));
	}
}

