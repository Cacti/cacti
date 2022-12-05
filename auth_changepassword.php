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

include('./include/global.php');

set_default_action();

$action = get_request_var('action');
switch ($action) {
	case 'checkpass':
		$error = secpass_check_pass(get_nfilter_request_var('password'));

		if ($error != '') {
			print $error;
		} else {
			print 'ok';
		}

		exit;

		break;
	default:
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
}

$user = db_fetch_row_prepared('SELECT *
	FROM user_auth
	WHERE id = ?',
	array($_SESSION['sess_user_id']));

$version = CACTI_VERSION;

if (!cacti_sizeof($user) || $user['realm'] != 0) {
	if (!cacti_sizeof($user)) {
		raise_message(44);
	} else {
		raise_message('nodomainpassword');
	}

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
	cacti_cookie_logout();

	if (isset($_SERVER['HTTP_REFERER'])) {
		header('Location: ' . $_SERVER['HTTP_REFERER']);
	} else {
		header('Location: index.php');
	}
	exit;
}

/* find out if we are logged in as a 'guest user' or not, if we are redirect away from password change */
if (cacti_sizeof($user) && $user['id'] === get_guest_account()) {
	header('Location: graph_view.php');
	exit;
}

/* default to !bad_password */
$bad_password = false;
$errorMessage = '';

switch ($action) {
case 'changepassword':
	// Get current user
	$user_id = intval($_SESSION['sess_user_id']);

	// Get passwords entered for change
	$password         = get_nfilter_request_var('password');
	$password_confirm = get_nfilter_request_var('password_confirm');

	// Get current password as entered
	$current_password = get_nfilter_request_var('current_password');

	// Secpass checking
	$error = secpass_check_pass($password);

	// Check new password passes basic checks
	if ($error != 'ok') {
		$bad_password = true;
		$errorMessage = "<span class='badpassword_message'>$error</span>";
		break;
	}

	// Check user password history
	if (!secpass_check_history($user_id, $password)) {
		$bad_password = true;
		$errorMessage = "<span class='badpassword_message'>" . __('You cannot use a previously entered password!') . "</span>";
		break;
	}

	// Password and Confirmed password checks
	if ($password !== $password_confirm) {
		$bad_password = true;
		$errorMessage = "<span class='badpassword_message'>" . __('Your new passwords do not match, please retype.') . "</span>";
		break;
	}

	// Compare current password with stored password
	if ((!empty($user['password']) || !empty($current_password)) && !compat_password_verify($current_password, $user['password'])) {
		$bad_password = true;
		$errorMessage = "<span class='badpassword_message'>" . __('Your current password is not correct. Please try again.') . "</span>";
		break;
	}

	// Check new password does not match stored password
	if (compat_password_verify($password, $user['password'])) {
		$bad_password = true;
		$errorMessage = "<span class='badpassword_message'>" . __('Your new password cannot be the same as the old password. Please try again.') . "</span>";
		break;
	}

	// If password isn't blank, password change is good to go
	if ($password != '') {
		if (read_config_option('secpass_expirepass') > 0) {
			db_execute_prepared("UPDATE user_auth
				SET lastchange = ?
				WHERE id = ?
				AND realm = 0
				AND enabled = 'on'",
				array(time(), $user_id));
		}

		$history = intval(read_config_option('secpass_history'));
		if ($history > 0) {
			$h = db_fetch_row_prepared("SELECT password, password_history
				FROM user_auth
				WHERE id = ?
				AND realm = 0
				AND enabled = 'on'",
				array($user_id));

			$op = $h['password'];
			$h = explode('|', $h['password_history']);

			while (cacti_count($h) > $history - 1) {
				array_shift($h);
			}

			$h[] = $op;
			$h = implode('|', $h);

			db_execute_prepared("UPDATE user_auth
				SET password_history = ?
				WHERE id = ?
				AND realm = 0
				AND enabled = 'on'",
				array($h, $user_id));
		}

		db_execute_prepared('INSERT IGNORE INTO user_log
			(username, result, time, ip)
			VALUES (?, 3, NOW(), ?)',
			array($user['username'], get_client_addr()));

		db_check_password_length();

		db_execute_prepared("UPDATE user_auth
			SET must_change_password = '', password = ?
			WHERE id = ?",
			array(compat_password_hash($password,PASSWORD_DEFAULT), $user_id));

		// Clear the auth cache for the user
		$token = '';
		if (isset($_SERVER['HTTP_COOKIE']) && strpos($_SERVER['HTTP_COOKIE'], 'cacti_remembers') !== false) {
			$parts = explode(';', $_SERVER['HTTP_COOKIE']);
			foreach($parts as $p) {
				if (strpos($p, 'cacti_remembers') !== false) {
					$pparts = explode('%2C', $p);
					if (isset($pparts[1])) {
						$token = $pparts[1];
						break;
					}
				}
			}
		}

		if ($token != '') {
			$sql_where = 'AND token != ' . db_qstr(hash('sha512', $token, false));
		} else {
			$sql_where = '';
		}

		db_execute_prepared("DELETE FROM user_auth_cache
			WHERE user_id = ?
			$sql_where",
			array($_SESSION['sess_user_id']));

		kill_session_var('sess_change_password');

		raise_message('password_success');

		/* ok, at the point the user has been successfully authenticated; so we must decide what to do next */

		/* if no console permissions show graphs otherwise, pay attention to user setting */
		$realm_id    = $user_auth_realm_filenames['index.php'];
		$has_console = db_fetch_cell_prepared('SELECT realm_id
			FROM user_auth_realm
			WHERE user_id = ? AND realm_id = ?',
			array($user_id, $realm_id));

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

$skip_current = (empty($user['password']));

if ($skip_current) {
	$title_message = __('Please enter your current password and your new<br>Cacti password.');
} else {
	$title_message = __('Please enter your new Cacti password.');
}

html_auth_header('change_password', __('Change Password'), __('Change Password'), $title_message);
?>
	<tr style='display:none'>
		<td>
			<input type='hidden' name='action' value='changepassword'>
			<input type='hidden' name='ref' value='<?php print html_escape(get_request_var('ref')); ?>'>
			<input type='hidden' name='name' value='<?php print isset($user['username']) ? html_escape($user['username']) : '';?>'>
			<input type='text'><input type='password'></td>
		</td>
	</tr>
	<tr>
<?php if ($skip_current) { ?>
		<td><?php print __('Username');?></td>
		<td class='nowrap'><input type='hidden' id='current' name='current_password' value=''><?php print $user['username'];?></td>
<?php } else { ?>
		<td><?php print __('Current password');?></td>
		<td class='nowrap'><input type='password' class='ui-state-default ui-corner-all' id='current' name='current_password' autocomplete='off' size='20' placeholder='********'></td>
<?php } ?>
	</tr>
	<tr>
		<td><?php print __('New password');?></td>
		<td class='nowrap'><input type='password' class='ui-state-default ui-corner-all' id='password' name='password' autocomplete='off' size='20' placeholder='********'><?php display_tooltip($secpass_tooltip);?></td>
	</tr>
	<tr>
		<td><?php print __('Confirm new password');?></td>
		<td class='nowrap'><input type='password' class='ui-state-default ui-corner-all' id='password_confirm' name='password_confirm' autocomplete='off' size='20' placeholder='********'></td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td class='nowrap'><input type='submit' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Save'); ?>'>
			<?php print $user['must_change_password'] != 'on' ? "<input type='button' class='ui-button ui-corner-all ui-widget' onClick='window.history.go(-1)' value='".  __esc('Return') . "'>":"";?>
		</td>
	</tr>
<?php
$secpass_minlen = read_config_option('secpass_minlen');

html_auth_footer('change_password', $errorMessage, "
	<script>
	var password_change = $('#password_change').is(':checked');

	$(function() {
		$('#current').focus();

		/* clear passwords */
		$('#password').val('');
		$('#password_confirm').val('');
	});
	</script>");
