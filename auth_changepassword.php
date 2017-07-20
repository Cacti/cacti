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

include('./include/global.php');

// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['sess_user_id'])) {
	if (isset($_SERVER['HTTP_REFERER'])) {
		header('Location: ' . $_SERVER['HTTP_REFERER']);
	} else {
		header('Location: index.php');
	}
	header('Location: index.php');
	exit;
}

$user        = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));
$version     = get_cacti_version();
$auth_method = read_config_option('auth_method');

if ($auth_method != 1 && $user['realm'] != 0) {
	raise_message('nodomainpassword');
	if (isset($_SERVER['HTTP_REFERER'])) {
		header('Location: ' . $_SERVER['HTTP_REFERER']);
	} else {
		header('Location: index.php');
	}
	exit;
}

if ($user['password_change'] != 'on') {
	raise_message('nopassword');

	/* destroy session information */
	kill_session_var('sess_user_id');
	unset($_COOKIE[$cacti_session_name]);
	setcookie($cacti_session_name, null, -1, $config['url_path']);

	if (isset($_SERVER['HTTP_REFERER'])) {
		header('Location: ' . $_SERVER['HTTP_REFERER']);
	} else {
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
		$errorMessage = "<span class='badpassword_message'>$error</span>";
	}

	if (!secpass_check_history($_SESSION['sess_user_id'], get_nfilter_request_var('password'))) {
		$bad_password = true;
		$errorMessage = "<span class='badpassword_message'>" . __('You cannot use a previously entered password!') . "</span>";
	}

	// Get password options for the new password
	if (function_exists('password_hash')) {
		$password_new = password_hash(get_nfilter_request_var('password'), PASSWORD_DEFAULT);
	} else {
		$password_new = '';
	}
	$password_old = md5(get_nfilter_request_var('password'));

	$current_password_old = md5(get_nfilter_request_var('current_password'));

	// Password and Confirmed password checks
	if (function_exists('password_verify')) {
		if ((!password_verify(get_nfilter_request_var('current_password'), $user['password'])) && $user['password'] != $current_password_old) {
			$bad_password = true;
			$errorMessage = "<span class='badpassword_message'>" . __('Your current password is not correct. Please try again.') . "</span>";
		}

		if (password_verify(get_nfilter_request_var('password'), $user['password']) || $user['password'] == $password_old) {
			$bad_password = true;
			$errorMessage = "<span class='badpassword_message'>" . __('Your new password cannot be the same as the old password. Please try again.') . "</span>";
		}
	} else {
		if ($user['password'] != $current_password_old) {
			$bad_password = true;
			$errorMessage = "<span class='badpassword_message'>" . __('Your current password is not correct. Please try again.') . "</span>";
		}

		if ($user['password'] == $password_old) {
			$bad_password = true;
			$errorMessage = "<span class='badpassword_message'>" . __('Your new password cannot be the same as the old password. Please try again.') . "</span>";
		}
	}

	if (get_nfilter_request_var('password') !== (get_nfilter_request_var('confirm'))) {
	    $bad_password = true;
		$errorMessage = "<span class='badpassword_message'>" . __('Your new passwords do not match, please retype.') . "</span>";
	}

	if ($bad_password == false && get_nfilter_request_var('password') == get_nfilter_request_var('confirm') && get_nfilter_request_var('password') != '') {
		// Password change is good to go
		if (read_config_option('secpass_expirepass') > 0) {
			db_execute_prepared("UPDATE user_auth
				SET lastchange = ?
				WHERE id = ?
				AND realm = 0
				AND enabled = 'on'",
				array(time(), intval($_SESSION['sess_user_id'])));
		}

		$history = intval(read_config_option('secpass_history'));
		if ($history > 0) {
				$h = db_fetch_row_prepared("SELECT password, password_history
					FROM user_auth
					WHERE id = ?
					AND realm = 0
					AND enabled = 'on'",
					array($_SESSION['sess_user_id']));

				$op = $h['password'];
				$h = explode('|', $h['password_history']);
				while (count($h) > $history - 1) {
					array_shift($h);
				}
				$h[] = $op;
				$h = implode('|', $h);

				db_execute_prepared("UPDATE user_auth
					SET password_history = ? WHERE id = ? AND realm = 0 AND enabled = 'on'",
					array($h, $_SESSION['sess_user_id']));
		}

		db_execute_prepared('INSERT IGNORE INTO user_log
			(username, result, time, ip)
			VALUES (?, 3, NOW(), ?)',
			array($user['username'], $_SERVER['REMOTE_ADDR']));

		db_execute_prepared("UPDATE user_auth
			SET must_change_password = '', password = ?
			WHERE id = ?",
			array($password_new != '' ? $password_new:$password_old, $_SESSION['sess_user_id']));

		kill_session_var('sess_change_password');

		/* ok, at the point the user has been sucessfully authenticated; so we must decide what to do next */

		/* if no console permissions show graphs otherwise, pay attention to user setting */
		$realm_id    = $user_auth_realm_filenames['index.php'];
		$has_console = db_fetch_cell_prepared('SELECT realm_id
			FROM user_auth_realm
			WHERE user_id = ? AND realm_id = ?',
			array($_SESSION['sess_user_id'], $realm_id));

		if (basename(get_nfilter_request_var('ref')) == 'auth_changepassword.php' || basename(get_nfilter_request_var('ref')) == '') {
			if ($has_console) {
				set_request_var('ref', 'index.php');
			} else {
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
		} else {
			header('Location: graph_view.php');
		}
		exit;

	} else {
		$bad_password = true;
	}

	break;
}

if (api_plugin_hook_function('custom_password', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
	exit;
}

if (get_request_var('action') == 'force') {
	$errorMessage = "<span class='loginErrors'>*** " . __('Forced password change') . " ***</span>";
}

/* Create tooltip for password complexity */
$secpass_tooltip = "<span style='font-weight:normal;'>" . __('Password requirements include:') . "</span><br>";
$secpass_body    = '';

if (read_config_option('secpass_minlen') > 0) {
	$secpass_body .= __('Must be at least %d characters in length', read_config_option('secpass_minlen'));
}

if (read_config_option('secpass_reqmixcase') == 'on') {
	$secpass_body .= ($secpass_body != '' ? '<br>':'') . __('Must include mixed case');
}

if (read_config_option('secpass_reqnum') == 'on') {
	$secpass_body .= ($secpass_body != '' ? '<br>':'') . __('Must include at least 1 number');
}

if (read_config_option('secpass_reqspec') == 'on') {
	$secpass_body .= ($secpass_body != '' ? '<br>':'') . __('Must include at least 1 special character');
}

if (read_config_option('secpass_history') != '0') {
	$secpass_body .= ($secpass_body != '' ? '<br>':'') . __('Cannot be reused for %d password changes', read_config_option('secpass_history')+1);
}

$secpass_tooltip .= $secpass_body;

$selectedTheme = get_selected_theme();

print "<!DOCTYPE html>\n";
print "<html>\n";
print "<head>\n";
print "\t<title>" . __('Change Password') . "</title>\n";
print "\t<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>\n";
print "\t<meta content='width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0' name='viewport'>\n";
print "\t<meta name='apple-mobile-web-app-capable' content='yes'>\n";
print "\t<meta name='mobile-web-app-capable' content='yes'>\n";
print "\t<meta http-equiv='X-UA-Compatible' content='IE=Edge,chrome=1'>\n";
print "\t<link href='" . $config['url_path'] . "include/themes/" . $selectedTheme . "/jquery-ui.css' type='text/css' rel='stylesheet'>\n";
print "\t<link href='" . $config['url_path'] . "include/" .  "/fa/css/font-awesome.css' type='text/css' rel='stylesheet'>\n";
print "\t<link href='" . $config['url_path'] . "include/themes/" . $selectedTheme . "/main.css' type='text/css' rel='stylesheet'>\n";
print "\t<link href='" . $config['url_path'] . "include/themes/" . $selectedTheme . "/images/favicon.ico' rel='shortcut icon'>\n";
print "\t<link href='" . $config['url_path'] . "include/themes/" . $selectedTheme . "/images/cacti_logo.gif' rel='icon' sizes='96x96'>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.js' language='javascript'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery-migrate.js' language='javascript'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery-ui.js' language='javascript'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.cookie.js' language='javascript'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.tablesorter.js' language='javascript'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/js/jquery.hotkeys.js'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/layout.js'></script>\n";
print "\t<script type='text/javascript' src='" . $config['url_path'] . "include/themes/" . $selectedTheme . "/main.js'></script>\n";
print "<script type='text/javascript'>var theme='" . $selectedTheme . "';</script>\n";
print "</head>\n";
print "<body class='loginBody'>
	<div class='loginLeft'></div>
	<div class='loginCenter'>
		<div class='loginArea'>
			<div class='cactiLogoutLogo'></div>
			<legend>" . __('Change Password') . "</legend>
			<form name='login' method='post' action='" . get_current_page() . "'>
				<input type='hidden' name='action' value='changepassword'>
				<input type='hidden' name='ref' value='" . sanitize_uri(get_request_var('ref')) . "'>
				<input type='hidden' name='name' value='" . (isset($user['username']) ? $user['username'] : '') . "'>
				<div class='loginTitle'>
					<p>" . __('Please enter your current password and your new<br>Cacti password.') . "</p>
				</div>
				<div class='cactiLogin'>
					<table class='cactiLoginTable'>
						<tr>
							<td>" . __('Current password') . "</td>
							<td><input type='password' id='current' name='current_password' autocomplete='off' size='20' placeholder='********'></td>
						</tr>
						<tr>
							<td>" . __('New password') . "</td>
							<td><input type='password' name='password' autocomplete='off' size='20' placeholder='********'>" . display_tooltip($secpass_tooltip) ."</td>
						</tr>
						<tr>
							<td>" . __('Confirm new password') . "</td>
							<td><input type='password' name='confirm' autocomplete='off' size='20' placeholder='********'></td>
						</tr>
						<tr>
							<td class='nowrap' colspan='2'><input type='submit' value='" . __esc('Save') . "'>
						" . ($user['must_change_password'] != 'on' ? "<input type='button' onClick='window.history.go(-1)' value='" . __esc('Return') . "'>":"") . "
							</td>
						</tr>
					</table>
				</div>
			</form>
			<div class='loginErrors'>" . $errorMessage . "</div>
		</div>
		<div class='versionInfo'>" . __('Version %1$s | %2$s', $version, COPYRIGHT_YEARS_SHORT) . "</div>
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
