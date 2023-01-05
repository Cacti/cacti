<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

/* include ldap support */
include_once(__DIR__ . '/lib/ldap.php');

/* set default action */
set_default_action();

/**
 * get the username from the post variable
 * For all but basic, this means that two post variables must be
 * set.  Additionally, for basic authentication verify the user,
 * and if not valid generate a fatal error.
 */
$username = auth_get_username(); // Get the username from either basic auth or the login form

/* initialize some variables */
$user          = array();                             // An array that will include all user details
$user_enabled  = true;                                // A variable to let plugins know that the user is enabled
$guest_user    = false;                               // Indicates the Guest account is being used
$realm         = 0;                                   // The compensated realm used for template and user validation
$frv_realm     = get_nfilter_request_var('realm', 0); // The dropdown value for realm
$auth_method   = read_config_option('auth_method');   // The authentication method for Cacti
$error         = false;                               // Global variable, will be true if any errors occur
$error_msg     = '';                                  // The errors message in case there was a login error

/* global variables for exception handling */
global $error, $error_msg;

if (get_nfilter_request_var('action') == 'login' || $auth_method == AUTH_METHOD_BASIC) {
	if ($auth_method > AUTH_METHOD_BASIC && $frv_realm <= 1) {
		// User picked 'local' from dropdown;
		$auth_method = AUTH_METHOD_CACTI;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	// Compensate as the dropdown for LDAP is off by one
	if ($frv_realm == 2) {
		$realm = 3;
	} elseif ($auth_method == AUTH_METHOD_BASIC) {
		$realm = $auth_method;
	} else {
		$realm = $frv_realm;
	}

	cacti_log("DEBUG: User '" . $username . "' attempting to login with realm " . $frv_realm . ', using method ' . $auth_method, false, 'AUTH', POLLER_VERBOSITY_DEBUG);

	switch ($auth_method) {
		case AUTH_METHOD_NONE: // No authentication, should not be reachable
			$error     = true;
			$error_msg = __esc('Cacti no longer supports No Authentication mode. Please contact your System Administrator.');
			cacti_log('FATAL: No authentication attempted and not supported.', false, 'AUTH');

			auth_display_custom_error_message($error_msg);

			exit;

			break;
		case AUTH_METHOD_CACTI: // Local authentication
			cacti_log("DEBUG: Local User '" . $username . "' to attempt login.", false, 'AUTH', POLLER_VERBOSITY_DEBUG);

			$user = local_auth_login_process($username);

			break;
		case AUTH_METHOD_BASIC: // Basic authentication
			cacti_log("DEBUG: Basic Auth User '" . $username . "' attempting to login.", false, 'AUTH', POLLER_VERBOSITY_DEBUG);

			$user = basic_auth_login_process($username);

			break;
		case AUTH_METHOD_LDAP: // LDAP Authentication
		case AUTH_METHOD_DOMAIN: // LDAP Domains login
			cacti_log("DEBUG: Domains User '" . $username . "' to attempt login.", false, 'AUTH', POLLER_VERBOSITY_DEBUG);

			$user = domains_login_process($username);

			break;

		default: // Login Realm not determined
			$error     = true;
			$error_msg = __esc('Unable to determine user Login Realm or Domain. Please contact your System Administrator.');

			cacti_log("LOGIN FAILED: User '" . $username . "' Unable to determine Login Realm.  Exiting.", false, 'AUTH');

			auth_display_custom_error_message($error_msg);

			exit;

			break;
	}

	/* Create user from template if available */
	if (!$error && !cacti_sizeof($user) && get_template_account($username) > 0 && $username != '') {
		$user = auth_login_create_user_from_template($username, $realm);
	}

	/* Guest account checking - Not for builtin */
	if (!$error && !cacti_sizeof($user) && get_guest_account() > 0) {
		/* Locate guest user record */
		$user = db_fetch_row_prepared(
			'SELECT *
			FROM user_auth
			WHERE id = ?',
			array(get_guest_account())
		);

		if ($user) {
			cacti_log("LOGIN: Authenticated user '" . $username . "' using guest account '" . $user['username'] . "'", false, 'AUTH');

			if ($username != '' && get_template_account($username) == 0) {
				raise_message('template_disabled', __('User was Authenticated, but the Template Account is disabled.  Using Guest Account'), MESSAGE_LEVEL_WARN);
			}

			$guest_user = true;
		} else {
			/* error */
			$error     = true;
			$error_msg = __('Access Denied!  Guest user id %s does not exist.  Please contact your Administrator.', read_config_option('guest_user'));

			cacti_log("LOGIN FAILED: Unable to locate guest user '" . read_config_option('guest_user') . "'", false, 'AUTH');

			if ($auth_method == AUTH_METHOD_BASIC) {
				auth_display_custom_error_message($error_msg);

				exit;
			}
		}
	}

	/* We have a valid user, do final checks, log their login attempt, and redirect as required */
	if (!$error && cacti_sizeof($user)) {
		if (!$guest_user) {
			cacti_log("LOGIN: User '" . $user['username'] . "' authenticated", false, 'AUTH');
		} else {
			cacti_log("LOGIN: Guest User '" . $user['username'] . "' in use", false, 'AUTH');
		}

		$client_addr = get_client_addr();

		db_execute_prepared(
			'INSERT IGNORE INTO user_log
			(username, user_id, result, ip, time)
			VALUES (?, ?, 1, ?, NOW())',
			array($username, $user['id'], $client_addr)
		);

		/* check if the user account is enabled with the exception of guest users */
		$user_enabled = true;

		if (!$guest_user && isset($user['enabled'])) {
			$user_enabled = ($user['enabled'] == 'on' ? true : false);
		}

		/* check if the user is enabled */
		if (!$user_enabled) {
			$error     = true;
			$error_msg = __('Access Denied!  User account disabled.');

			if ($auth_method == AUTH_METHOD_BASIC) {
				auth_display_custom_error_message($error_msg);

				exit;
			}
		}

		if (!$error && !auth_user_has_access($user)) {
			/* error */
			$error     = true;
			$error_msg = __('You do not have access to any area of Cacti.  Contact your administrator.');

			cacti_log(sprintf('LOGIN FAILED: User %s with id %s does not have access to any area of Cacti.', $user['username'], $user['id']), false, 'AUTH');

			if ($auth_method == AUTH_METHOD_BASIC) {
				auth_display_custom_error_message($error_msg);

				exit;
			}
		}

		/* remember me support.  Not for guest of basic auth */
		if ($auth_method != AUTH_METHOD_BASIC && $user['id'] !== get_guest_account()) {
			if (!$error && isset_request_var('remember_me') && read_config_option('auth_cache_enabled') == 'on') {
				set_auth_cookie($user);
			}
		}

		if (!$error) {
			/* set the php session */
			$_SESSION[SESS_USER_ID]     = $user['id'];
			$_SESSION[SESS_USER_AGENT]  = $_SERVER['HTTP_USER_AGENT'];
			$_SESSION[SESS_CLIENT_ADDR] = get_client_addr();

			/* handle 'force change password' */
			if ($user['must_change_password'] == 'on' && $auth_method == AUTH_METHOD_CACTI && $user['password_change'] == 'on') {
				$_SESSION[SESS_CHANGE_PASSWORD] = true;
			}

			if (db_table_exists('user_auth_group')) {
				$group_options = db_fetch_cell_prepared(
					'SELECT MAX(login_opts)
					FROM user_auth_group AS uag
					INNER JOIN user_auth_group_members AS uagm
					ON uag.id=uagm.group_id
					WHERE user_id = ?
					AND login_opts != 4',
					array($_SESSION[SESS_USER_ID])
				);

				if (!empty($group_options)) {
					$user['login_opts'] = $group_options;
				}
			}

			if (user_setting_exists('user_language', $_SESSION[SESS_USER_ID])) {
				$_SESSION[SESS_USER_LANGUAGE] = read_user_setting('user_language');
			}

			cacti_log("DEBUG: User '" . $username . "' about to re-direct to preferred login page", false, 'AUTH', POLLER_VERBOSITY_DEBUG);

			auth_login_redirect($user['login_opts']);
		}
	} else {
		$id = db_fetch_cell_prepared(
			'SELECT id
			FROM user_auth
			WHERE username = ?
			AND realm = ?',
			array($username, $frv_realm)
		);

		switch ($frv_realm) {
			case '0':
			case '1':
				$realm_name = 'Local';

				break;
			case '2':
				$realm_name = 'LDAP';

				break;

			default:
				$realm_name = 'Domains LDAP';

				break;
		}

		/* BAD username/password builtin and LDAP */
		db_execute_prepared(
			'INSERT IGNORE INTO user_log
			(username, user_id, result, ip, time)
			VALUES (?, ?, 0, ?, NOW())',
			array($username, !empty($id) ? $id : 0, get_client_addr())
		);

		cacti_log('LOGIN FAILED: ' . $realm_name . " Login Failed for user '" . $username . "' from IP Address '" . get_client_addr() . "'.", false, 'AUTH');
	}
}

if (api_plugin_hook_function('custom_login', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
	return;
}

$selectedTheme = get_selected_theme();

html_auth_header(
	'login',
	__('Login to Cacti'),
	__('User Login'),
	__('Enter your Username and Password below'),
	array(
		'error'        => $error,
		'error_msg'    => $error_msg,
		'username'     => $username,
		'user_enabled' => $user_enabled,
		'action'       => get_nfilter_request_var('action')
	)
);
?>
<tr>
	<td>
		<label for='login_username'><?php print __('Username'); ?></label>
	</td>
	<td>
		<input type='text' class='ui-state-default ui-corner-all' id='login_username' name='login_username' value='<?php print html_escape($username); ?>' placeholder='<?php print __esc('Username'); ?>'>
	</td>
</tr>
<tr>
	<td>
		<label for='login_password'><?php print __('Password'); ?></label>
	</td>
	<td>
		<input type='password' autocomplete='new-password' class='ui-state-default ui-corner-all' id='login_password' name='login_password' placeholder='********'>
	</td>
</tr>
<?php
if (read_config_option('auth_method') == AUTH_METHOD_LDAP || read_config_option('auth_method') == AUTH_METHOD_DOMAIN) {
	$realms = get_auth_realms(true);

	// try and remember previously selected realm
	if ($frv_realm && array_key_exists($frv_realm, $realms)) {
		foreach ($realms as $key => $realm) {
			$realms[$key]['selected'] = ($frv_realm == $key);
		}
	}
	?>
	<tr>
		<td>
			<label for='realm'><?php print __('Realm'); ?></label>
		</td>
		<td>
			<select id='realm' name='realm' class='ui-state-default ui-corner-all'>
				<?php
						if (cacti_sizeof($realms)) {
							foreach ($realms as $index => $realm) {
								print "\t\t\t\t\t<option value='" . $index . "'" . ($realm['selected'] ? ' selected="selected"' : '') . '>' . html_escape($realm['name']) . "</option>\n";
							}
						}
	?>
			</select>
		</td>
	</tr>
<?php
}

if (read_config_option('auth_cache_enabled') == 'on') { ?>
	<tr>
		<td>&nbsp;</td>
		<td>
			<input style='vertical-align:-3px;' type='checkbox' id='remember_me' name='remember_me' <?php print(isset($_COOKIE['cacti_remembers']) || !isempty_request_var('remember_me') ? 'checked' : ''); ?>>
			<label for='remember_me'><?php print __('Keep me signed in'); ?></label>
		</td>
	</tr>
<?php
} ?>
<tr>
	<td>&nbsp;</td>
	<td>
		<input type='submit' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Login'); ?>'>
	</td>
</tr>
<?php
$error_message = '';

if ($error_msg) {
	$error_message = $error_msg;
} else {
	if (get_nfilter_request_var('action') == 'login') {
		$error_message = __('Invalid User Name/Password Please Retype');
	}

	if ($user_enabled == '0') {
		$error_message =  __('User Account Disabled');
	}
}

$focus_control = (empty($username)) ? 'username' : 'password';
html_auth_footer('login', $error_message, "
	<script>
		var storage = Storages.localStorage;

		$(function() {
			$('body').css('height', $(window).height());
			$('.cactiAuthLeft').css('width',parseInt($(window).width()*0.33)+'px');
			$('.cactiAuthRight').css('width',parseInt($(window).width()*0.33)+'px');
			$('#login_${focus_control}').focus();

			if (storage.isSet('user_realm')) {
				var preferredRealm = storage.get('user_realm');
			} else {
				var preferredRealm = null;
			}

			if (preferredRealm == null) {
				preferredRealm = $('#realm option:selected').val();
			}

			// Restore the preferred realm
			if ($('#realm').length) {
				if (preferredRealm !== null) {
					$('#realm').val(preferredRealm);
					if ($('#realm').selectmenu('instance') !== undefined) {
						$('#realm').selectmenu('refresh');
					}
				}
			}

			// Control submit in order to store preferred realm
			$('#login').submit(function(event) {
				event.preventDefault();
				if ($('#realm').length) {
					storage.set('user_realm', $('#realm').val());
				}
				$('#login').off('submit').trigger('submit');
			});

		});
	</script>
");
