<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

include(__DIR__ . '/include/global.php');

/* set default action */
set_default_action();

if (!isset($_SESSION['sess_user_id'])) {
	header('Location: logout.php');
	exit;
}

$user = db_fetch_row_prepared('SELECT id, username, tfa_enabled, tfa_secret, login_opts
	FROM user_auth
	WHERE id = ?',
	array($_SESSION['sess_user_id']));

$message = '';
if (isset($_COOKIE[session_name() . '_otp'])) {
	$daysUntilInvalid = 0;
	$time = (string) floor((time() / (3600 * 24))); // get day number

	list($otpday, $hash) = explode(':', $_COOKIE[session_name() . '_otp']);

	if ($otpday >= $time - $daysUntilInvalid && $hash === hash_hmac('sha1', $user['username'].':'.$otpday.':'.$_SERVER['HTTP_USER_AGENT'], $user['tfa_secret'])) {
		$_SESSION['sess_user_2fa'] = true;
	}
}

/* Get the username */
if (get_nfilter_request_var('action') == 'login') {
	/* Auth token from Form */
	$token = get_nfilter_request_var('token');

	if (cacti_sizeof($user)) {
		if (empty($user['tfa_enabled'])) {
			cacti_log("DEBUG: User '" . $user['username'] . "' attempting to verify 2fa token, but not 2fa enabled", false, 'AUTH', POLLER_VERBOSITY_DEBUG);
			$_SESSION['sess_user_2fa'] = true;
		} else {
			cacti_log("DEBUG: User '" . $user['username'] . "' attempting to verify 2fa token", false, 'AUTH', POLLER_VERBOSITY_DEBUG);
			$g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
			$_SESSION['sess_user_2fa'] = $g->checkCode($user['tfa_secret'],  $token);
		        $time = floor(time() / (3600 * 24)); // get day number
		        //about using the user agent: It's easy to fake it, but it increases the barrier for stealing and reusing cookies nevertheless
		        // and it doesn't do any harm (except that it's invalid after a browser upgrade, but that may be even intented)
		        $cookie = $time.':'.hash_hmac('sha1', $user['username'].':'.$time.':'.$_SERVER['HTTP_USER_AGENT'], $user['tfa_secret']);
		        cacti_cookie_set(session_name() . '_otp', $cookie, time() + (30 * 24 * 3600));
		}
	} else {
		$_SESSION['sess_user_2fa'] = true;
	}

	/* Process the user  */
	if ($_SESSION['sess_user_2fa']) {
		if (isset($user['tfa_enabled'])) {
			cacti_log("LOGIN: User '" . $user['username'] . "' 2FA Authenticated", false, 'AUTH');

			$client_addr = get_client_addr('');

			db_execute_prepared('INSERT IGNORE INTO user_log
				(username, user_id, result, ip, time)
				VALUES (?, ?, 2, ?, NOW())',
				array($user['username'], $user['id'], $client_addr));
		}
	} else {
		/* BAD token */
		cacti_log("DEBUG: User '" . $user['username'] . "' failed to verify 2fa token", false, 'AUTH', POLLER_VERBOSITY_DEBUG);

		db_execute_prepared('INSERT IGNORE INTO user_log
			(username, user_id, result, ip, time)
			VALUES (?, 0, 3, ?, NOW())',
			array($user['username'], get_client_addr('')));

		$message = __('Failed to verify token');
	}
}

if (empty($user['tfa_enabled'])) {
	$_SESSION['sess_user_2fa'] = true;
}

if (!empty($_SESSION['sess_user_2fa'])) {
	auth_login_redirect($user['login_opts']);
	exit;
}

if (api_plugin_hook_function('custom_2fa_login', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
	return;
}

$selectedTheme = get_selected_theme();

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<?php html_common_header(api_plugin_hook_function('login_2fa_title', __('2nd Factor Authentication')));?>
</head>
<body class='loginBody'>
	<div class='loginLeft'></div>
	<div class='loginCenter'>
	<div class='loginArea'>
		<div class='cactiLoginLogo'></div>
			<legend><?php print __('2FA Verification');?></legend>
			<form name='login' method='post' action='<?php print get_current_page();?>'>
				<input type='hidden' name='action' value='login'>
				<?php api_plugin_hook_function('login_2fa_before',
					array(
						'username' => $user['username'],
						'action' => get_nfilter_request_var('action')));
				?>
				<div class='loginTitle'>
					<p><?php print __('Enter your token');?></p>
				</div>
				<div class='cactiLogin'>
					<table class='cactiLoginTable'>
						<tr>
							<td>
								<label for='login_token'><?php print __('Token');?></label>
							</td>
							<td>
								<input type='textbox' class='ui-state-default ui-corner-all' id='login_token' name='token' placeholder='<?php print __('Token');?>'>
							</td>
						</tr>
						<tr>
							<td>&nbsp;</td>
							<td>
								<span class='textError'><?php print $message; ?></span>
							</td>
						</tr>
						<tr>
							<td cospan='2'>
								<input type='submit' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Verify');?>'>
							</td>
						</tr>
					</table>
				</div>
			<?php api_plugin_hook('login_2fa_after'); ?>
			</form>
		</div>
		<div class='versionInfo'><?php print __('Version %1$s | %2$s', CACTI_VERSION_FULL, COPYRIGHT_YEARS_SHORT);?></div>
	</div>
	<div class='loginRight'></div>
	<script type='text/javascript'>
	$(function() {
		$('body').css('height', $(window).height());
		$('.loginLeft').css('width',parseInt($(window).width()*0.33)+'px');
		$('.loginRight').css('width',parseInt($(window).width()*0.33)+'px');
		$('#login_token').focus();
	});
	</script>
	<?php include_once(dirname(__FILE__) . '/include/global_session.php');?>
</body>
</html>
