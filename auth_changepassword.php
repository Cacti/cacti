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

require('./include/global.php');

set_default_action();

$action = grv('action');

switch ($action) {
	case 'checkpass':
		$error = secpass_check_pass(gnrv('password'));

		if ($error != '') {
			print $error;
		} else {
			print 'ok';
		}

		exit;
	default:
		/**
		 * If the user is not logged in, redirect them back to the page
		 * they came from, or to the login page.
		 */
		if (!isset($_SESSION[SESS_USER_ID])) {
			$referer = validate_redirect_url($_SERVER['HTTP_REFERER'] ?? '', 'index.php');
			header("Location: $referer");
			exit;
		}
}

$return = validate_redirect_url($_SERVER['HTTP_REFERER'] ?? '', 'index.php');

if (basename($return) != 'auth_changepassword.php') {
	if (strpos($return, '/plugins/') !== false) {
		$parts  = explode('/plugins/', $return);
		$return = CACTI_PATH_URL . 'plugins/' . $parts[1];
	} else {
		$return = CACTI_PATH_URL . basename($return);
	}

	$_SESSION['acp_return'] = $return;
} else {
	if (isset($_SESSION['acp_return'])) {
		$return = $_SESSION['acp_return'];
	} else {
		$return = CACTI_PATH_URL . 'index.php';
	}
}

$user = db_fetch_row_prepared('SELECT *
	FROM user_auth
	WHERE id = ?',
	[$_SESSION[SESS_USER_ID]]);

$version = CACTI_VERSION;

global $user_auth_realm_filenames;

if (!cacti_sizeof($user) || $user['realm'] != 0) {
	if (!cacti_sizeof($user)) {
		raise_message(44);
	} else {
		raise_message('nodomainpassword');
	}

	$return = validate_redirect_url($_SERVER['HTTP_REFERER'] ?? '', 'index.php');

	header("Location: $return");

	exit;
}

if ($user['password_change'] != 'on') {
	raise_message('nopassword');

	// destroy session information
	kill_session_var(SESS_USER_ID);
	cacti_cookie_logout();

	$return = validate_redirect_url($_SERVER['HTTP_REFERER'] ?? '', 'index.php');

	header("Location: $return");

	exit;
}

// find out if we are logged in as a 'guest user' or not, if we are redirect away from password change
if (cacti_sizeof($user) && $user['id'] === get_guest_account()) {
	header('Location: graph_view.php');

	exit;
}

// default to !bad_password
$bad_password = false;
$errorMessage = '';

switch ($action) {
	case 'changepassword':
		// Get current user
		$user_id = intval($_SESSION[SESS_USER_ID]);

		// Get passwords entered for change
		$password         = gnrv('password');
		$password_confirm = gnrv('password_confirm');

		// Get current password as entered
		$current_password = gnrv('current_password');

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
			$errorMessage = "<span class='badpassword_message'>" . __('You cannot use a previously entered password!') . '</span>';

			break;
		}

		// Password and Confirmed password checks
		if ($password !== $password_confirm) {
			$bad_password = true;
			$errorMessage = "<span class='badpassword_message'>" . __('Your new passwords do not match, please retype.') . '</span>';

			break;
		}

		// Compare current password with stored password
		if ((!empty($user['password']) || !empty($current_password)) && !compat_password_verify($current_password, $user['password'])) {
			$bad_password = true;
			$errorMessage = "<span class='badpassword_message'>" . __('Your current password is not correct. Please try again.') . '</span>';

			break;
		}

		// Check new password does not match stored password
		if (compat_password_verify($password, $user['password'])) {
			$bad_password = true;
			$errorMessage = "<span class='badpassword_message'>" . __('Your new password cannot be the same as the old password. Please try again.') . '</span>';

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
					[time(), $user_id]);
			}

			$history = intval(read_config_option('secpass_history'));

			if ($history > 0) {
				$h = db_fetch_row_prepared("SELECT password, password_history
					FROM user_auth
					WHERE id = ?
					AND realm = 0
					AND enabled = 'on'",
					[$user_id]);

				$op = $h['password'];
				$h  = explode('|', $h['password_history']);

				while (cacti_count($h) > $history - 1) {
					array_shift($h);
				}

				$h[] = $op;
				$h   = implode('|', $h);

				db_execute_prepared("UPDATE user_auth
					SET password_history = ?
					WHERE id = ?
					AND realm = 0
					AND enabled = 'on'",
					[$h, $user_id]);
			}

			db_execute_prepared('INSERT IGNORE INTO user_log
				(username, result, time, ip)
				VALUES (?, 3, NOW(), ?)',
				[$user['username'], get_client_addr()]);

			db_check_password_length();

			db_execute_prepared("UPDATE user_auth
				SET must_change_password = '', password = ?
				WHERE id = ?",
				[compat_password_hash($password,PASSWORD_DEFAULT), $user_id]);

			// Clear the auth cache for the user
			db_execute_prepared('DELETE FROM user_auth_cache
				WHERE user_id = ?',
				[$_SESSION[SESS_USER_ID]]);

			// Delete any user login sessions if using database sessions
			db_execute_prepared('DELETE FROM sessions
				WHERE user_id = ?',
				[$_SESSION[SESS_USER_ID]]);

			kill_session_var(SESS_CHANGE_PASSWORD);
			kill_session_var(SESS_USER_ID);

			raise_message('password_success');

			header('Location: logout.php');

			exit;
		} else {
			$bad_password = true;
		}

		break;
}

if (api_plugin_hook_function('custom_password', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
	exit;
}

if (grv('action') == 'force') {
	$errorMessage = "<span class='loginErrors'>*** " . __('Forced password change') . ' ***</span>';
}

// Create tooltip for password complexity
$secpass_tooltip = "<span style='font-weight:normal;'>" . __('Password requirements include:') . '</span><br>';
$secpass_body    = '';

if (read_config_option('secpass_minlen') > 0) {
	$secpass_body .= __('Must be at least %d characters in length', read_config_option('secpass_minlen'));
}

if (read_config_option('secpass_reqmixcase') == 'on') {
	$secpass_body .= ($secpass_body != '' ? '<br>' : '') . __('Must include mixed case');
}

if (read_config_option('secpass_reqnum') == 'on') {
	$secpass_body .= ($secpass_body != '' ? '<br>' : '') . __('Must include at least 1 number');
}

if (read_config_option('secpass_reqspec') == 'on') {
	$secpass_body .= ($secpass_body != '' ? '<br>' : '') . __('Must include at least 1 special character');
}

if (read_config_option('secpass_history') != '0') {
	$secpass_body .= ($secpass_body != '' ? '<br>' : '') . __('Cannot be reused for %d password changes', read_config_option('secpass_history') + 1);
}

$secpass_tooltip .= $secpass_body;

$selectedTheme = get_selected_theme();

$skip_current = (empty($user['password']));

if (isrv('ref')) {
	$ref_parts   = parse_url(gnrv('ref'));
	$valid       = false;

	// It's an array, so valid URL
	if (is_array($ref_parts)) {
		$valid = true;
	}

	// Someone trying to login via a get is bad!
	if (isset($ref_parts['user']) || isset($ref_parts['pass'])) {
		$valid = false;
	}

	// Someone trying to send an invalid host
	if (!isset($ref_parts['host'])) {
		$valid = false;
	}

	if ($valid) {
		$server_addr = $_SERVER['SERVER_ADDR'];

		if (!filter_var($_SERVER['SERVER_NAME'], FILTER_VALIDATE_IP)) {
			$server_info = dns_get_record($_SERVER['SERVER_NAME'], DNS_ANY);
			$server_ref  = gethostbyname($ref_parts['host']);

			if ($server_ref != $server_addr) {
				$valid = false;
			}

			if (!$valid && cacti_sizeof($server_info)) {
				foreach ($server_info as $record) {
					if (isset($record['host']) && $record['host'] == $server_ref) {
						$valid = true;

						break;
					}

					if (isset($record['target']) && $record['target'] == $server_ref) {
						$valid = true;

						break;
					}

					if (isset($record['ip']) && $record['ip'] == $server_addr) {
						$valid = true;

						break;
					}
				}
			}
		} else {
			$server_ip   = gethostbyname($_SERVER['SERVER_NAME']);
			$server_ref  = gethostbyname($ref_parts['host']);

			if ($server_ip == $server_ref) {
				$valid = true;
			}
		}
	} else {
		$valid = false;
	}

	if (!$valid) {
		cacti_log('WARNING: User attempted to access Cacti from unknown URL', false, 'AUTH');

		raise_message('problems_with_page', __('There are problems with the Change Password page.  Contact your Cacti administrator right away.'), MESSAGE_LEVEL_ERROR);

		header('Location:index.php');

		exit;
	}
}

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
			<input type='hidden' name='ref' value='<?php print htmle(grv('ref')); ?>'>
			<input type='hidden' name='name' value='<?php print isset($user['username']) ? htmle($user['username']) : ''; ?>'>
			<input type='text'><input type='password'></td>
		</td>
	</tr>
	<tr>
<?php if ($skip_current) { ?>
		<td><?php print __('Username'); ?></td>
		<td class='nowrap'><input type='hidden' id='current' name='current_password' value=''><?php print $user['username']; ?></td>
<?php } else { ?>
		<td><?php print __('Current password'); ?></td>
		<td class='nowrap'><input type='password' class='ui-state-default ui-corner-all' id='current' name='current_password' autocomplete='current-password' size='15' maxlength='25' placeholder='********'></td><td></td>
<?php } ?>
	</tr>
	<tr>
		<td><?php print __('New password'); ?></td>
		<td class='nowrap'><input type='password' class='ui-state-default ui-corner-all' id='password' name='password' autocomplete='off' size='15' maxlength='25' placeholder='********'></td><td id='pass_details'><div id='pass' style='float:left;'></div><?php print display_tooltip($secpass_tooltip); ?></td>
	</tr>
	<tr>
		<td><?php print __('Confirm password'); ?></td>
		<td class='nowrap'><input type='password' class='ui-state-default ui-corner-all' id='password_confirm' name='password_confirm' autocomplete='off' size='15' maxlength='25' placeholder='********'></td><td id='pass_details_conf'><div id='passconfirm' style='float:left;'></div></td>
	</tr>
	<tr>
		<td colspan='3' class='nowrap'><button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' value='save'><?php print __esc('Save'); ?></button>
			<?php if ($user['must_change_password'] != 'on') { ?>
			<button type='button' class='ui-button ui-corner-all ui-widget' onClick='document.location=<?php print json_encode((string) $return); ?>'><?php print __esc('Return'); ?></button>
			<?php } ?>
		</td>
	</tr>
<?php
$secpass_minlen = read_config_option('secpass_minlen');

html_auth_footer('change_password', $errorMessage, "
	<script type='text/javascript'>
	var password_change = $('#password_change').is(':checked');

	$(function() {
		$('#current').focus();

		/* clear passwords */
		$('#password').val('');
		$('#password_confirm').val('');
	});
	</script>");
