<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

include('./include/global.php');

// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['sess_user_id'])) {
	if (isset($_SERVER['HTTP_REFERER'])) {
		header('Location: ' . $_SERVER['HTTP_REFERER']);
	}else{
		header('Location: index.php');
	}
	header('Location: index.php');
	exit;
}

$user        = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));
$version     = db_fetch_cell('SELECT cacti FROM version');
$auth_method = read_config_option('auth_method');

if ($auth_method != 1 && $user['realm'] != 0) {
	raise_message('nodomainpassword');
	if (isset($_SERVER['HTTP_REFERER'])) {
		header('Location: ' . $_SERVER['HTTP_REFERER']);
	}else{
		header('Location: index.php');
	}
	exit;
}

if ($user['password_change'] != 'on') {
	raise_message('nopassword');
	if (isset($_SERVER['HTTP_REFERER'])) {
		header('Location: ' . $_SERVER['HTTP_REFERER']);
	}else{
		header('Location: index.php');
	}
	exit;
}

/* find out if we are logged in as a 'guest user' or not, if we are redirect away from password change */
if (sizeof($user) && $user['username'] == read_config_option('guest_user')) {
	header('Location: graph_view.php');
	exit;
}

/* default to !bad_password */
$bad_password = false;
$errorMessage = '';

/* set default action */
set_default_action();

switch (get_request_var('action')) {
case 'changepassword':


	// Secpass checking

	$error = secpass_check_pass(get_nfilter_request_var('password'));
	
	if ($error != 'ok') {
		$bad_password = true;
		$errorMessage = "<span color='#FF0000'>$error</span>";

	}
			
	if (!secpass_check_history($_SESSION['sess_user_id'], get_nfilter_request_var('password'))) {
		$bad_password = true;
		$errorMessage = "<span color='#FF0000'>You can not use a previously entered password!</span>";
	}

	// Password and Confirmed password checks
		if ($user['password'] != md5(get_nfilter_request_var('current_password'))) {
		$bad_password = true;
		$errorMessage = "<span color='#FF0000'>Your current password is not correct.  Please try again.</span>";
	}

	if ($user['password'] == md5(get_nfilter_request_var('password'))) {
		$bad_password = true;
		$errorMessage = "<span color='#FF0000'>Your new password can not be the same as the old password.  Please try again.</span>";
	}
	
		if (get_nfilter_request_var('password') !== (get_nfilter_request_var('confirm'))) {
	    $bad_password = true;
		$errorMessage = "<span color='#FF0000'>Your new passwords do not match, please retype.</span>";
	}
	
		if ($bad_password == false && get_nfilter_request_var('password') == get_nfilter_request_var('confirm') && get_nfilter_request_var('password') != '') {
		// Password change is good to go
		if (read_config_option('secpass_expirepass') > 0) {
				db_execute("UPDATE user_auth SET lastchange = " . time() . " WHERE id = " . intval($_SESSION['sess_user_id']) . " AND realm = 0 AND enabled = 'on'");
		}
		$history = intval(read_config_option('secpass_history'));
		if ($history > 0) {
				$h = db_fetch_row_prepared("SELECT password, password_history FROM user_auth WHERE id = ? AND realm = 0 AND enabled = 'on'", array($_SESSION['sess_user_id']));
				$op = $h['password'];
				$h = explode('|', $h['password_history']);
				while (count($h) > $history - 1) {
					array_shift($h);
			}
				$h[] = $op;
				$h = implode('|', $h);
				db_execute_prepared("UPDATE user_auth SET password_history = ? WHERE id = ? AND realm = 0 AND enabled = 'on'", array($h, $_SESSION['sess_user_id']));
		}

		db_execute_prepared('INSERT IGNORE INTO user_log (username, result, ip) VALUES (?, 3, ?)', array($user['username'], $_SERVER['REMOTE_ADDR']));
		db_execute_prepared("UPDATE user_auth SET must_change_password = '', password = ? WHERE id = ?", array(md5(get_nfilter_request_var('password')), $_SESSION['sess_user_id']));

		kill_session_var('sess_change_password');

		/* ok, at the point the user has been sucessfully authenticated; so we must
		decide what to do next */

		/* if no console permissions show graphs otherwise, pay attention to user setting */
		$realm_id    = $user_auth_realm_filenames['index.php'];
		$has_console = db_fetch_cell('SELECT realm_id FROM user_auth_realm WHERE user_id = ? AND realm_id = ?', array($_SESSION['sess_user_id'], $realm_id));

		if (basename(get_nfilter_request_var('ref')) == 'auth_changepassword.php' || basename(get_nfilter_request_var('ref')) == '') {
			if ($has_console) {
				set_request_var('ref', 'index.php');
			}else{
				set_request_var('ref', 'graph_view.php');
			}
		}

		if (!empty($has_console)) {
			switch ($user['login_opts']) {
				case '1': /* referer */
					header('Location: ' . sanitize_uri(get_nfilter_request_var('ref'))); break;
				case '2': /* default console page */
					header('Location: index.php'); break;
				case '3': /* default graph page */
					header('Location: graph_view.php'); break;
				default:
					api_plugin_hook_function('login_options_navigate', $user['login_opts']);
			}
		}else{
			header('Location: graph_view.php');
		}
		exit;

	}else{
		$bad_password = true;
	}

	break;
}

if (api_plugin_hook_function('custom_password', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
	exit;
}

if (get_request_var('action') == 'force') {
	$errorMessage = "<span color='#FF0000'>*** Forced password change ***</span>";
}

print "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>\n";
print "<html>\n";
print "<head>\n";
print "\t<title>Change Password</title>\n";
print "\t<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>\n";
print "\t<link href='" . $config['url_path'] . "include/themes/" . get_selected_theme() . "/main.css' type='text/css' rel='stylesheet'>\n";
   print "\t<link href='" . $config['url_path'] . "include/themes/" . get_selected_theme() . "/jquery-ui.css' type='text/css' rel='stylesheet'>\n";
print "\t<link href='" . $config['url_path'] . "images/favicon.ico' rel='shortcut icon'>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.js' language='javascript'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery-ui.js' language='javascript'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.cookie.js' language='javascript'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.hotkeys.js'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/layout.js'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/themes/" . get_selected_theme() . "/main.js'></script>\n";
print "<script type='text/javascript'>var theme='" . get_selected_theme() . "';</script>\n";
print "</head>\n";
print "<body class='loginBody'>
	<div class='loginLeft'></div>
	<div class='loginCenter'>
		<div class='loginArea'>
			<div class='cactiLogoutLogo'></div>
			<legend>Change Password</legend>
			<form name='login' method='post' action='" . basename($_SERVER['PHP_SELF']) . "'>
				<input type='hidden' name='action' value='changepassword'>
				<input type='hidden' name='ref' value='" . sanitize_uri(get_request_var('ref')) . "'>
				<input type='hidden' name='name' value='" . (isset($user['username']) ? $user['username'] : '') . "'>
				<div class='loginTitle'>
					<p>Please enter your current password and your new<br>Cacti password.</p>
					Password Policy: <br>
					* Minimum length - 8 Characters <br>
					* At least 1 Special Character [!@#$%^&*(){}}]<br>
					* At least 1 Uppercase Character <br>
					* Not previously used password <br><br>
				</div>
				<div class='cactiLogin'>
					<table class='cactiLoginTable'>
						<tr>
							<td>Current password</td>
							<td><input type='password' id='current' name='current_password' autocomplete='off' size='20' placeholder='********'></td>
						</tr>
						<tr>
							<td>New password</td>
							<td><input type='password' name='password' autocomplete='off' size='20' placeholder='********'></td>
						</tr>
						<tr>
							<td>Confirm new password</td>
							<td><input type='password' id='confirm' name='confirm' autocomplete='off' size='20' placeholder='********'></td>
						</tr>
						<tr>
							<td><input type='submit' value='Save'></td>
						</tr>
					</table>
				</div>
			</form>
			<div class='loginErrors'>" . $errorMessage . "</div>
		</div>
		<div class='versionInfo'>Version " . $version . " | " . COPYRIGHT_YEARS_SHORT  . "</div>
	</div>
	<div class='loginRight'></div>
	<script type='text/javascript'>
	$(function() {
		$('#current').focus();
		$('.loginLeft').css('width',parseInt($(window).width()*0.33)+'px');
		$('.loginRight').css('width',parseInt($(window).width()*0.33)+'px');
	});
	</script>
	</body>
	</html>\n";
