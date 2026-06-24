<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

require(__DIR__ . '/include/global.php');

// set default action
set_default_action();

if (!isset($_SESSION[SESS_USER_ID])) {
	header('Location: logout.php');

	exit;
}

$user = db_fetch_row_prepared('SELECT id, username, tfa_enabled, tfa_secret, login_opts
	FROM user_auth
	WHERE id = ?',
	[$_SESSION[SESS_USER_ID]]);

$message = '';
$tfaMins = intval(read_config_option('secpass_2fatime'));

if ($tfaMins <= 0) {
	$tfaMins = 60;
}

$tfaTime = time() - ($tfaMins * 60);

// See if we have no 2FA time set, and if so, lets try and get it from the cookie
if (empty($_SESSION[SESS_USER_2FA]) && isset($_COOKIE[session_name() . '_otp'])) {
	$tfaCookie = explode(':', $_COOKIE[session_name() . '_otp'], 2);

	if (cacti_count($tfaCookie) == 2) {
		[$tfaCookieTime, $tfaCookeHash] = $tfaCookie;
	} else {
		$tfaCookieTime = false;
		$tfaCookeHash  = false;
	}

	if ($tfaCookieTime && $tfaCookeHash === hash_hmac('sha1', $user['username'] . ':' . $tfaMins . ':' . $tfaCookieTime . ':' . $_SERVER['HTTP_USER_AGENT'], $user['tfa_secret'])) {
		$_SESSION[SESS_USER_2FA] = $tfaCookieTime;
	}
}

// Is the current session 2FA time expired?
if (!empty($_SESSION[SESS_USER_2FA]) && $_SESSION[SESS_USER_2FA] < $tfaTime) {
	// Yes, lets unset the session variable and recheck
	unset($_SESSION[SESS_USER_2FA]);
}

// Are we being asked to login with a 2FA token?
if (gnrv('action') == 'login_2fa') {
	// Auth token from Form
	$token = gnrv('token');

	if (cacti_sizeof($user)) {
		if (empty($user['tfa_enabled'])) {
			cacti_log("DEBUG: User '" . $user['username'] . "' attempting to verify 2fa token, but not 2fa enabled", false, 'AUTH', POLLER_VERBOSITY_DEBUG);
			$_SESSION[SESS_USER_2FA] = true;
		} else {
			cacti_log("DEBUG: User '" . $user['username'] . "' attempting to verify 2fa token", false, 'AUTH', POLLER_VERBOSITY_DEBUG);
			$g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();

			if ($g->checkCode($user['tfa_secret'],  $token)) {
				$_SESSION[SESS_USER_2FA] = time();

				// About using the user agent: It's easy to fake it, but it increases the barrier for stealing and reusing cookies nevertheless
				// and it doesn't do any harm (except that it's invalid after a browser upgrade, but that may be even intended)
				$cookie = $_SESSION[SESS_USER_2FA] . ':' . hash_hmac('sha1', $user['username'] . ':' . $tfaMins . ':' . $_SESSION[SESS_USER_2FA] . ':' . $_SERVER['HTTP_USER_AGENT'], $user['tfa_secret']);

				$cookie_lifetime = read_config_option('secpass_2fatime') * 60;

				cacti_cookie_set(session_name() . '_otp', $cookie, time() + ($cookie_lifetime));
			} else {
				$_SESSION[SESS_USER_2FA] = false;
			}
		}
	} else {
		$_SESSION[SESS_USER_2FA] = time();
	}

	// Process the user
	if ($_SESSION[SESS_USER_2FA]) {
		if (isset($user['tfa_enabled'])) {
			cacti_log("LOGIN: User '" . $user['username'] . "' 2FA Authenticated", false, 'AUTH');

			$client_addr = get_client_addr();

			db_execute_prepared('INSERT IGNORE INTO user_log
				(username, user_id, result, ip, time)
				VALUES (?, ?, 2, ?, NOW())',
				[$user['username'], $user['id'], $client_addr]);
		}
	} else {
		// BAD token
		cacti_log("DEBUG: User '" . $user['username'] . "' failed to verify 2fa token", false, 'AUTH', POLLER_VERBOSITY_DEBUG);

		db_execute_prepared('INSERT IGNORE INTO user_log
			(username, user_id, result, ip, time)
			VALUES (?, 0, 3, ?, NOW())',
			[$user['username'], get_client_addr()]);

		$message = __('Failed to verify token');
	}
}

if (empty($user['tfa_enabled'])) {
	$_SESSION[SESS_USER_2FA] = true;
}

if (!empty($_SESSION[SESS_USER_2FA])) {
	auth_login_redirect($user['login_opts']);

	exit;
}

if (api_plugin_hook_function('custom_2fa_login', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
	return;
}

$selectedTheme = get_selected_theme();

html_auth_header('login_2fa', __('2nd Factor Authentication'), __('2FA Verification'), __('Enter your token'),
	[
		'username' => $user['username'],
		'action'   => gnrv('action')
	]
);
?>
<tr>
	<td>
		<label for='login_token'><?php print __('Token'); ?></label>
	</td>
	<td>
		<input type='textbox' class='ui-state-default ui-corner-all' id='login_token' name='token' placeholder='<?php print __('Token'); ?>'>
	</td>
</tr>
<tr>
	<td>&nbsp;</td>
	<td>
		<span class='textError'><?php print $message; ?></span>
	</td>
</tr>
<tr>
	<td>&nbsp;</td>
	<td>
		<button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' value='verify'><?php print __esc('Verify'); ?></button>
	</td>
</tr>
<?php
	html_auth_footer('login_2fa', $message, "
		<script>
			$(function() {
				$('#login_token').focus();
			});
		</script>
	");
