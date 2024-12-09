<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

set_default_action('formidentity');

$action = get_request_var('action');
$errorMessage = '';
$return = CACTI_PATH_URL . 'index.php';

switch ($action) {
	case 'checkpass': /* check if inserted password match password complexivity only */
		$error = secpass_check_pass(get_nfilter_request_var('password'));

		if ($error != '') {
			print $error;
		} else {
			print 'ok';
		}

		exit;

		break;
	case 'formreset': /* check correct hash, if incorrect lets start again */
		$user_hash = get_filter_request_var('hash', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-zA-Z0-9]+$/')));

		$hash = db_fetch_row_prepared('SELECT *
			FROM user_auth_reset_hashes
			WHERE hash = ?
			LIMIT 1',
			array($user_hash));

		if (!$hash || $hash['hash'] != $user_hash) {
			$errorMessage = "<span class='badpassword_message'>" . __('Incorrect resetlink hash') . '</span>';
			$action = 'formidentity';
		}

		break;
	case 'resetrequest': /* try to find user in db. If yes and has email, send resetlink */
		$user = array();
		$identity = get_nfilter_request_var('identity');

		if (filter_var($identity, FILTER_VALIDATE_EMAIL) ||
			get_filter_request_var('identity', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-zA-Z0-9]+$/')))) {

			$user = db_fetch_row_prepared('SELECT id, username, email_address
				FROM user_auth WHERE username = ? or email_address = ? LIMIT 1',
				array($identity, $identity));
		}

		if (cacti_sizeof($user) && $user['email_address'] != '') {
			$hash = generate_hash();

			db_execute_prepared('INSERT INTO user_auth_reset_hashes
				(user_id, hash, expiry)
				VALUES (?, ?, date_add(now(), interval ? minute))',
				array ($user['id'], $hash, read_config_option('secnotify_resetlink_timeout')));

			$replacement = array(
				read_config_option('base_url') . CACTI_PATH_URL,
				$user['username'],
				read_config_option('base_url') . CACTI_PATH_URL . 'auth_resetpassword.php?action=formreset&hash=' . $hash
			);

			$search = array('<CACTIURL>', '<USERNAME>', '<PWDRESETLINK>');

			$body = str_replace($search, $replacement, read_config_option('secnotify_chpass_message'));

			send_mail($user['email_address'], null, read_config_option('secnotify_chpass_subject'), $body, array(), array(),  true);
			cacti_log(sprintf('NOTE: Reset password request for user %s from IP %s', $user['username'], get_client_addr()), false, 'SYSTEM');

		} else {
			cacti_log(sprintf('NOTE: Reset password request for unknown user "%s" from IP %s', db_qstr($identity), get_client_addr()), false, 'SYSTEM');
		}

		$errorMessage = "<span class='badpassword_message'>" . __('Reset password token was sent. Check your mailbox') . "</span>";
		$action = 'formidentity';

		break;
	case 'resetpassword': /* check and save new password */
		$user_hash = get_filter_request_var('hash', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-zA-Z0-9]+$/')));

		$hash = db_fetch_row_prepared('SELECT *
			FROM user_auth_reset_hashes
			WHERE hash = ?
			LIMIT 1',
			array($user_hash));

		if (!$hash || $hash['hash'] != $user_hash) {
			$errorMessage = "<span class='badpassword_message'>" . __('Incorrect resetlink hash') . '</span>';
			$action = 'formidentity';
		}

		$user = db_fetch_row_prepared('SELECT *
			FROM user_auth
			WHERE id = ?',
			array($hash['user_id']));

		// Get passwords entered for change
		$password         = get_nfilter_request_var('password');
		$password_confirm = get_nfilter_request_var('password_confirm');

		// Secpass checking
		$error = secpass_check_pass($password);

		// Check new password passes basic checks
		if ($error != 'ok') {
			$errorMessage = "<span class='badpassword_message'>$error</span>";
			$action = 'formreset';

			break;
		}

		// Check user password history
		if (!secpass_check_history($user['id'], $password)) {
			$action = 'formreset';

			break;
		}

		// Password and Confirmed password checks
		if ($password !== $password_confirm) {
			$errorMessage = "<span class='badpassword_message'>" . __('Your new passwords do not match, please retype.') . '</span>';
			$action = 'formreset';

			break;
		}

		// Check new password does not match stored password
		if (compat_password_verify($password, $user['password'])) {
			$errorMessage = "<span class='badpassword_message'>" . __('Your new password cannot be the same as the old password. Please try again.') . '</span>';
			$action = 'formreset';

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
					array(time(), $user['id']));
			}

			$history = intval(read_config_option('secpass_history'));

			if ($history > 0) {
				$h = db_fetch_row_prepared("SELECT password, password_history
					FROM user_auth
					WHERE id = ?
					AND realm = 0
					AND enabled = 'on'",
					array($user['id']));

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
					array($h, $user['id']));
			}

			db_execute_prepared('INSERT IGNORE INTO user_log
				(username, result, time, ip)
				VALUES (?, 3, NOW(), ?)',
				array($user['username'], get_client_addr()));

			db_check_password_length();

			db_execute_prepared("UPDATE user_auth
				SET must_change_password = '', password = ?
				WHERE id = ?",
				array(compat_password_hash($password,PASSWORD_DEFAULT), $user['id']));

			db_execute_prepared('DELETE FROM user_auth_reset_hashes
				WHERE hash = ?',
				array($user_hash));

			raise_message('password_success');

			header('Location: index.php');

			exit;
		} else {
			$action = 'resetpassword';
			$return = CACTI_PATH_URL . 'resetpassword.php';
		}

		break;
}

/* show identity form or reset password form */
if (api_plugin_hook_function('custom_password', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
	exit;
}

/* Create tooltip for password complexity */
$secpass_tooltip = "<span style='font-weight:normal;'>" . __('Password requirements include:') . '</span><br>';
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
	$secpass_body .= ($secpass_body != '' ? '<br>':'') . __('Cannot be reused for %d password changes', read_config_option('secpass_history') + 1);
}

$secpass_tooltip .= $secpass_body;

$selectedTheme = get_selected_theme();

if ($action == 'formidentity') {
	$title_message = __('Please enter your Cacti username or email address.');
} elseif ($action == 'formreset') {
	$title_message = __('Please enter your new Cacti password.');
} else {
	$title_message = __('Reset password problem.');
}

html_auth_header('reset_password', __('Reset Password'), __('Reset Password'), $title_message);
?>

<?php if ($action == 'formidentity') { ?>
		<tr>
		<td class='nowrap' colspan='2'>
			<input type='text' class='ui-state-default ui-corner-all' id='identity' name='identity' size='25' maxlength='50'></td>
		</tr>
	<tr>
		<td colspan='2' class='nowrap'>
			<input type='submit' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Send reset token by email'); ?>'>
			<input type='button' class='ui-button ui-corner-all ui-widget' onClick='document.location="<?php echo $return;?>"' value='<?php echo __esc('Return');?>'>
		</td>
	</tr>
	<tr>
		<td colspan='2'>
			<input type='hidden' name='action' value='resetrequest'>
			<input type='hidden' name='hash' value='<?php echo get_nfilter_request_var('hash');?>'>
		</td>
	</tr>
<?php }
	if ($action == 'formreset') {?>
	<tr>
		<td><?php print __('New password');?></td>
		<td class='nowrap'>
			<input type='password' class='ui-state-default ui-corner-all' id='password' name='password' autocomplete='off' size='15' maxlength='25' placeholder='********'><?php print display_tooltip($secpass_tooltip);?>
		</td>
	</tr>
	<tr>
		<td><?php print __('Confirm password');?></td>
		<td class='nowrap'>
			<input type='password' class='ui-state-default ui-corner-all' id='password_confirm' name='password_confirm' autocomplete='off' size='15' maxlength='25' placeholder='********'>
		</td>
	</tr>
	<tr>
		<td colspan='2' class='nowrap'><input type='submit' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Save'); ?>'>
			<input type='button' class='ui-button ui-corner-all ui-widget' onClick='document.location="<?php echo $return;?>"' value='<?php echo __esc('Return');?>'>
		</td>
	</tr>
	<tr>
		<td colspan='2'>
			<input type='hidden' name='action' value='resetpassword'>
			<input type='hidden' name='hash' value='<?php echo get_nfilter_request_var('hash');?>'>
		</td>
	</tr>
<?php
}

$secpass_minlen = read_config_option('secpass_minlen');

html_auth_footer('reset_password', $errorMessage, "
	<script type='text/javascript'>

	$(function() {
		if ($('#identity').length) {
			$('#identity').focus();
		} else {
			$('#password').focus();
		}

		/* clear passwords */
		$('#password').val('');
		$('#password_confirm').val('');
	});
	</script>");
