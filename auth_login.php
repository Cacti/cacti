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

/* set default action */
set_default_action();

/* Get the username */
if (read_config_option('auth_method') == '2') {
	/* Get the Web Basic Auth username and set action so we login right away */
	set_request_var('action', 'login');

	if (isset($_SERVER['PHP_AUTH_USER'])) {
		$username = str_replace("\\", "\\\\", $_SERVER['PHP_AUTH_USER']);
	} elseif (isset($_SERVER['REMOTE_USER'])) {
		$username = str_replace("\\", "\\\\", $_SERVER['REMOTE_USER']);
	} elseif (isset($_SERVER['REDIRECT_REMOTE_USER'])) {
		$username = str_replace("\\", "\\\\", $_SERVER['REDIRECT_REMOTE_USER']);
	} elseif (isset($_SERVER['HTTP_PHP_AUTH_USER'])) {
		$username = str_replace("\\", "\\\\", $_SERVER['HTTP_PHP_AUTH_USER']);
	} elseif (isset($_SERVER['HTTP_REMOTE_USER'])) {
		$username = str_replace("\\", "\\\\", $_SERVER['HTTP_REMOTE_USER']);
	} elseif (isset($_SERVER['HTTP_REDIRECT_REMOTE_USER'])) {
		$username = str_replace("\\", "\\\\", $_SERVER['HTTP_REDIRECT_REMOTE_USER']);

	} else {
		/* No user - Bad juju! */
		$username = '';
		cacti_log('ERROR: No username passed with Web Basic Authentication enabled.', false, 'AUTH');
		auth_display_custom_error_message(__('Web Basic Authentication configured, but no username was passed from the web server. Please make sure you have authentication enabled on the web server.'));
		exit;
	}
} else {
	if (get_nfilter_request_var('action') == 'login') {
		/* LDAP and Builtin get username from Form */
		$username = get_nfilter_request_var('login_username');
	} else {
		$username = '';
	}
}

$username = sanitize_search_string($username);
$version  = get_cacti_version();

/* process login */
$user         = array();
$copy_user    = false;
$user_auth    = false;
$user_enabled = 1;
$ldap_error   = false;
$ldap_error_message = '';
$realm        = 0;

if (get_nfilter_request_var('action') == 'login') {
	$frv_realm = get_nfilter_request_var('realm');
	if ($frv_realm == '1') {
		$auth_method = 1;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	cacti_log("DEBUG: User '" . $username . "' attempting to login with realm ". $frv_realm . ", using method " . $auth_method, false, 'AUTH', POLLER_VERBOSITY_DEBUG);

	// Realms of 1 or below are internal
	$auth_local_required = ($frv_realm < 2);

	switch ($auth_method) {
	case '0':
		/* No auth, no action, also shouldn't get here */
		$auth_local_required = false;
		exit;

		break;
	case '2':
		/* Web Basic Auth */
		$auth_local_required = false;
		$copy_user = true;
		$user_auth = true;
		$realm = 2;

		/* Locate user in database */
		$user = db_fetch_row_prepared('SELECT *
			FROM user_auth
			WHERE username = ?
			AND realm = 2',
			array($username));

		if (!$user && read_config_option('user_template') == '0' && read_config_option('guest_user') == '0') {
			cacti_log("ERROR: User '" . $username . "' authenticated by Web Server, but both Template and Guest Users are not defined in Cacti.  Exiting.", false, 'AUTH');

			$username = htmlspecialchars($username);

			display_custom_error_message(__('%s authenticated by Web Server, but both Template and Guest Users are not defined in Cacti.', $username));
			header('Location: index.php?header=false');
			exit;
		}

		break;
	case '3':
		/* LDAP Auth */
 		if ($frv_realm == '2' && get_nfilter_request_var('login_password') != '') {
			/* include LDAP lib */
			include_once('./lib/ldap.php');

			/* get user DN */
			$ldap_dn_search_response = cacti_ldap_search_dn($username);
			if ($ldap_dn_search_response['error_num'] == '0') {
				$ldap_dn = $ldap_dn_search_response['dn'];
			} else {
				/* Error searching */
				cacti_log('LOGIN: LDAP Error: ' . $ldap_dn_search_response['error_text'], false, 'AUTH');
				$ldap_error = true;
				$ldap_error_message =  __('LDAP Search Error: %s', $ldap_dn_search_response['error_text']);
				$user_auth = false;
			}

			if (!$ldap_error) {
				/* auth user with LDAP */
				$ldap_auth_response = cacti_ldap_auth($username, get_nfilter_request_var('login_password'), $ldap_dn);

				if ($ldap_auth_response['error_num'] == '0') {
					/* User ok */
					$user_auth = true;
					$copy_user = true;
					$realm = 3;

					/* Locate user in database */
					cacti_log("LOGIN: LDAP User '" . $username . "' Authenticated", false, 'AUTH');

					$user = db_fetch_row_prepared('SELECT *
						FROM user_auth
						WHERE username = ?
						AND realm = 3',
						array($username));
				} else {
					/* error */
					cacti_log('LOGIN: LDAP Error: ' . $ldap_auth_response['error_text'], false, 'AUTH');
					$ldap_error = true;
					$ldap_error_message = __('LDAP Error: %s', $ldap_auth_response['error_text']);
					$user_auth = false;
				}
			}

		}

		break;
	case '4':
		cacti_log("DEBUG: User '" . $username . "' attempting domain lookup for realm " . $frv_realm . " with " . ($auth_local_required ? '':'no') . " local lookup", false, 'AUTH', POLLER_VERBOSITY_DEBUG);

		if ($frv_realm > 0) {
			domains_login_process($username);
		}

		break;
	default:
		$auth_local_required = true;

		break;
	}

	cacti_log("DEBUG: User '" . $username . "' attempt login locally? " . ($auth_local_required ? 'Yes':'No'), false, 'AUTH', POLLER_VERBOSITY_DEBUG);
	if ($auth_local_required) {
		secpass_login_process($username);

		if (!api_plugin_hook_function('login_process', false)) {
			/* Builtin Auth */
			if ((!$user_auth)) {
				$stored_pass = db_fetch_cell_prepared('SELECT password
					FROM user_auth
					WHERE username = ? AND realm = 0',
					array($username));

				if ($stored_pass != '') {
					$p = get_nfilter_request_var('login_password');
					$valid = compat_password_verify($p, $stored_pass);

					cacti_log("DEBUG: User '" . $username . "' password is " . ($valid?'':'in') . "valid", false, 'AUTH', POLLER_VERBOSITY_DEBUG);
					if ($valid) {
						$user = db_fetch_row_prepared('SELECT *
							FROM user_auth
							WHERE username = ? AND realm = 0',
							array($username));

						if (compat_password_needs_rehash($stored_pass, PASSWORD_DEFAULT)) {
							$p = compat_password_hash($p, PASSWORD_DEFAULT);
							db_execute_prepared('UPDATE user_auth
								SET password = ?
								WHERE username = ?',
								array($p, $username));
						}
					}
				}
			}
		}
	}
	/* end of switch */

	/* Create user from template if requested */
	if (!sizeof($user) && $copy_user && read_config_option('user_template') != '0' && $username != '') {
		cacti_log("WARN: User '" . $username . "' does not exist, copying template user", false, 'AUTH');

		/* check that template user exists */
		if (db_fetch_row_prepared('SELECT id FROM user_auth WHERE username = ? AND realm = 0', array(read_config_option('user_template')))) {
			/* template user found */
			user_copy(read_config_option('user_template'), $username, 0, $realm);

			/* requery newly created user */
			$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE username = ? AND realm = ?', array($username, $realm));
		} else {
			/* error */
			display_custom_error_message(__('Template user %s does not exist.', read_config_option('user_template')));
			cacti_log("LOGIN: Template user '" . read_config_option('user_template') . "' does not exist.", false, 'AUTH');
			header('Location: index.php?header=false');
			exit;
		}
	}

	/* Guest account checking - Not for builtin */
	$guest_user = false;
	if ((!sizeof($user)) && ($user_auth) && (read_config_option('guest_user') != '0')) {
		/* Locate guest user record */
		$user = db_fetch_row_prepared('SELECT *
			FROM user_auth
			WHERE username = ?',
			array(read_config_option('guest_user')));

		if ($user) {
			cacti_log("LOGIN: Authenticated user '" . $username . "' using guest account '" . $user['username'] . "'", false, 'AUTH');

			$guest_user = true;
		} else {
			/* error */
			display_custom_error_message(__('Guest user %s does not exist.', read_config_option('guest_user')));
			cacti_log("LOGIN: Unable to locate guest user '" . read_config_option('guest_user') . "'", false, 'AUTH');
			header('Location: index.php?header=false');
			exit;
		}
	}

	/* Process the user  */
	if (sizeof($user)) {
		cacti_log("LOGIN: User '" . $user['username'] . "' Authenticated", false, 'AUTH');

		if (isset($_SERVER['HTTP_CLIENT_IP'])) {
			$client_addr = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (isset($_SERVER['X-Forwarded-For'])) {
			$client_addr = $_SERVER['X-Forwarded-For'];
		} elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$client_addr = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
			$client_addr = $_SERVER['HTTP_FORWARDED_FOR'];
		} elseif (isset($_SERVER['HTTP_FORWARDED'])) {
			$client_addr = $_SERVER['HTTP_FORWARDED'];
		} elseif (isset($_SERVER['REMOTE_ADDR'])) {
			$client_addr = $_SERVER['REMOTE_ADDR'];
		} else {
			$client_addr = '';
		}

		db_execute_prepared('INSERT IGNORE INTO user_log
			(username, user_id, result, ip, time)
			VALUES (?, ?, 1, ?, NOW())',
			array($username, $user['id'], $client_addr));

		/* is user enabled */
		$user_enabled = $user['enabled'];
		if ($user_enabled != 'on') {
			/* Display error */
			display_custom_error_message(__('Access Denied, user account disabled.'));
			header('Location: index.php?header=false');
			exit;
		}

		/* remember this user */
		if (isset_request_var('remember_me') && read_config_option('auth_cache_enabled') == 'on') {
			set_auth_cookie($user);
		}

		/* set the php session */
		$_SESSION['sess_user_id'] = $user['id'];

		/* handle 'force change password' */
		if (($user['must_change_password'] == 'on') &&
			(read_config_option('auth_method') == 1) &&
			($user['password_change'] == 'on')) {

			$_SESSION['sess_change_password'] = true;
		}

		$group_options = db_fetch_cell_prepared('SELECT MAX(login_opts)
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id=uagm.group_id
			WHERE user_id=?', array($_SESSION['sess_user_id']));

		if ($group_options > 0) {
			$user['login_opts'] = $group_options;
		}

		$newtheme = false;
		if (user_setting_exists('selected_theme', $_SESSION['sess_user_id']) && read_config_option('selected_theme') != read_user_setting('selected_theme')) {
			unset($_SESSION['selected_theme']);
			$newtheme = true;
		}

		/* ok, at the point the user has been sucessfully authenticated; so we must
		decide what to do next */
		switch ($user['login_opts']) {
			case '1': /* referer */
				/* because we use plugins, we can't redirect back to graph_view.php if they don't
				 * have console access
				 */
				if (isset($_SERVER['REDIRECT_URL'])) {
					$referer = sanitize_uri($_SERVER['REDIRECT_URL']);
					if (isset($_SERVER['REDIRECT_QUERY_STRING'])) {
						$referer .= '?' . $_SERVER['REDIRECT_QUERY_STRING'] . ($newtheme ? '&newtheme=1':'');
					}
				} elseif (isset($_SERVER['HTTP_REFERER'])) {
					$referer = sanitize_uri($_SERVER['HTTP_REFERER']);
					if (basename($referer) == 'logout.php') {
						$referer = $config['url_path'] . 'index.php' . ($newtheme ? '?newtheme=1':'');
					}
				} elseif (isset($_SERVER['REQUEST_URI'])) {
					$referer = sanitize_uri($_SERVER['REQUEST_URI']);
					if (basename($referer) == 'logout.php') {
						$referer = $config['url_path'] . 'index.php' . ($newtheme ? '?newtheme=1':'');
					}
				} else {
					$referer = $config['url_path'] . 'index.php' . ($newtheme ? '?newtheme=1':'');
				}

				if (substr_count($referer, 'plugins')) {
					header('Location: ' . $referer);
				} elseif (!is_realm_allowed(8)) {
					header('Location: graph_view.php' . ($newtheme ? '?newtheme=1':''));
				} else {
					$param_char = '?';
					if (substr_count($referer, '?')) {
						$param_char = '&';
					}
					header('Location: ' . $referer . ($newtheme ? $param_char . 'newtheme=1':''));
				}

				break;
			case '2': /* default console page */
				header('Location: ' . $config['url_path'] . 'index.php' . ($newtheme ? '?newtheme=1':''));

				break;
			case '3': /* default graph page */
				header('Location: ' . $config['url_path'] . 'graph_view.php' . ($newtheme ? '?newtheme=1':''));

				break;
			default:
				api_plugin_hook_function('login_options_navigate', $user['login_opts']);
		}
		exit;
	} else {
		if ((!$guest_user) && ($user_auth)) {
			/* No guest account defined */
			display_custom_error_message(__('Access Denied, please contact you Cacti Administrator.'));
			cacti_log('LOGIN: Access Denied, No guest enabled or template user to copy', false, 'AUTH');
			header('Location: index.php');
			exit;
		} else {
			/* BAD username/password builtin and LDAP */
			db_execute_prepared('INSERT IGNORE INTO user_log
				(username, user_id, result, ip, time)
				VALUES (?, 0, 0, ?, NOW())',
				array($username, $_SERVER['REMOTE_ADDR']));
		}
	}
}

/* auth_display_custom_error_message - displays a custom error message to the browser that looks like
   the pre-defined error messages
   @arg $message - the actual text of the error message to display
*/
function auth_display_custom_error_message($message) {
	global $config;

	/* kill the session */
	setcookie(session_name(), '', time() - 3600, $config['url_path']);

	/* print error */
	print '<!DOCTYPE html>';
	print "<html>\n";
	print "<head>\n";
	html_common_header(__('Cacti'));
	print "</head>\n";
	print "<body>\n<br><br>\n";
	print $message . "\n";
	print "</body>\n</html>\n";
}

function domains_login_process() {
	global $user, $realm, $username, $user_auth, $ldap_error, $ldap_error_message;

	if (is_numeric(get_nfilter_request_var('realm')) && get_nfilter_request_var('login_password') != '') {
		/* include LDAP lib */
		include_once('./lib/ldap.php');

		/* get user DN */
		$ldap_dn_search_response = domains_ldap_search_dn($username, get_nfilter_request_var('realm'));
		if ($ldap_dn_search_response['error_num'] == '0') {
			$ldap_dn = $ldap_dn_search_response['dn'];
		} else {
			/* Error searching */
			cacti_log('LOGIN: LDAP Error: ' . $ldap_dn_search_response['error_text'], false, 'AUTH');
			$ldap_error = true;
			$ldap_error_message = __('LDAP Search Error: %s', $ldap_dn_search_response['error_text']);
			$user_auth = false;
		}

		if (!$ldap_error) {
			/* auth user with LDAP */
			$ldap_auth_response = domains_ldap_auth($username, get_nfilter_request_var('login_password'), $ldap_dn, get_nfilter_request_var('realm'));

			if ($ldap_auth_response['error_num'] == '0') {
				/* User ok */
				$user_auth   = true;
				$copy_user   = true;
				$realm       = get_nfilter_request_var('realm');

				$domain_name = db_fetch_cell_prepared('SELECT domain_name
					FROM user_domains
					WHERE domain_id = ?',
					array($realm-1000));

				/* Locate user in database */
				cacti_log("LOGIN: LDAP User '$username' Authenticated from Domain '$domain_name'", false, 'AUTH');

				$user = db_fetch_row_prepared('SELECT *
					FROM user_auth
					WHERE username = ?
					AND realm = ?',
					array($username, $realm));

				/* Create user from template if requested */
				$template_user = db_fetch_cell_prepared('SELECT user_id
					FROM user_domains
					WHERE domain_id = ?',
					array(get_nfilter_request_var('realm')-1000));

				$template_username = db_fetch_cell_prepared('SELECT username
					FROM user_auth
					WHERE id = ?',
					array($template_user));

				if (!sizeof($user) && $copy_user && !empty($template_user) && $username != '') {
					cacti_log("WARN: User '" . $username . "' does not exist, copying template user", false, 'AUTH');
					/* check that template user exists */
					if (db_fetch_row_prepared('SELECT id FROM user_auth WHERE id = ? AND realm = 0', array($template_user))) {
						/* template user found */
						user_copy($template_username, $username, 0, $realm);

						/* requery newly created user */
						$user = db_fetch_row_prepared('SELECT *
							FROM user_auth
							WHERE username = ?
							AND realm = ?',
							array($username, $realm));
					} else {
						/* error */
						display_custom_error_message(__('Template user %s does not exist.', $template_username));
						cacti_log("LOGIN: Template user '" . $template_username . "' does not exist.", false, 'AUTH');
						header('Location: index.php?header=false');
						exit;
					}
				}
			} else {
				/* error */
				cacti_log('LOGIN: LDAP Error: ' . $ldap_auth_response['error_text'], false, 'AUTH');
				$ldap_error = true;
				$ldap_error_message = __('LDAP Error: %s', $ldap_auth_response['error_text']);
				$user_auth = false;
			}
		}

	}
}

function domains_ldap_auth($username, $password = '', $dn = '', $realm) {
	$ldap = new Ldap;

	if (!empty($username)) $ldap->username = $username;
	if (!empty($password)) $ldap->password = $password;
	if (!empty($dn))       $ldap->dn       = $dn;

	$ld = db_fetch_row_prepared('SELECT *
		FROM user_domains_ldap
		WHERE domain_id = ?',
		array($realm-1000));

	if (sizeof($ld)) {
		if (!empty($ld['dn']))                $ldap->dn                = $ld['dn'];
		if (!empty($ld['server']))            $ldap->host              = $ld['server'];
		if (!empty($ld['port']))              $ldap->port              = $ld['port'];
		if (!empty($ld['port_ssl']))          $ldap->port_ssl          = $ld['port_ssl'];
		if (!empty($ld['proto_version']))     $ldap->version           = $ld['proto_version'];
		if (!empty($ld['encryption']))        $ldap->encryption        = $ld['encryption'];
		if (!empty($ld['referrals']))         $ldap->referrals         = $ld['referrals'];

		if (!empty($ld['mode']))              $ldap->mode              = $ld['mode'];
		if (!empty($ld['search_base']))       $ldap->search_base       = $ld['search_base'];
		if (!empty($ld['search_filter']))     $ldap->search_filter     = $ld['search_filter'];
		if (!empty($ld['specific_dn']))       $ldap->specific_dn       = $ld['specific_dn'];
		if (!empty($ld['specific_password'])) $ldap->specific_password = $ld['specific_password'];

		if ($ld['group_require'] == 'on') {
			$ldap->group_require = true;
		} else {
			$ldap->group_require = false;
		}
		if (!empty($ld['group_dn']))          $ldap->group_dn          = $ld['group_dn'];
		if (!empty($ld['group_attrib']))      $ldap->group_attrib      = $ld['group_attrib'];
		if (!empty($ld['group_member_type'])) $ldap->group_member_type = $ld['group_member_type'];

		return $ldap->Authenticate();
	} else {
		return false;
	}
}

function domains_ldap_search_dn($username, $realm) {
	$ldap = new Ldap;

	if (!empty($username)) $ldap->username = $username;

	$ld = db_fetch_row_prepared('SELECT *
		FROM user_domains_ldap
		WHERE domain_id = ?',
		array($realm-1000));

	if (sizeof($ld)) {
		if (!empty($ld['dn']))                $ldap->dn                = $ld['dn'];
		if (!empty($ld['server']))            $ldap->host              = $ld['server'];
		if (!empty($ld['port']))              $ldap->port              = $ld['port'];
		if (!empty($ld['port_ssl']))          $ldap->port_ssl          = $ld['port_ssl'];
		if (!empty($ld['proto_version']))     $ldap->version           = $ld['proto_version'];
		if (!empty($ld['encryption']))        $ldap->encryption        = $ld['encryption'];
		if (!empty($ld['referrals']))         $ldap->referrals         = $ld['referrals'];

		if (!empty($ld['mode']))              $ldap->mode              = $ld['mode'];
		if (!empty($ld['search_base']))       $ldap->search_base       = $ld['search_base'];
		if (!empty($ld['search_filter']))     $ldap->search_filter     = $ld['search_filter'];
		if (!empty($ld['specific_dn']))       $ldap->specific_dn       = $ld['specific_dn'];
		if (!empty($ld['specific_password'])) $ldap->specific_password = $ld['specific_password'];

		if ($ld['group_require'] == 'on') {
			$ldap->group_require = true;
		} else {
			$ldap->group_require = false;
		}

		if (!empty($ld['group_dn']))          $ldap->group_dn          = $ld['group_dn'];
		if (!empty($ld['group_attrib']))      $ldap->group_attrib      = $ld['group_attrib'];
		if (!empty($ld['group_member_type'])) $ldap->group_member_type = $ld['group_member_type'];

		return $ldap->Search();
	} else {
		return false;
	}
}

if (api_plugin_hook_function('custom_login', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
	return;
}

$selectedTheme = get_selected_theme();

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<?php html_common_header(api_plugin_hook_function('login_title', __('Login to Cacti')));?>
	<script type='text/javascript'>
	$(function() {
			$('#login_username').focus();
	});
	</script>
</head>
<body class='loginBody'>
	<div class='loginLeft'></div>
	<div class='loginCenter'>
	<div class='loginArea'>
		<div class='cactiLoginLogo'></div>
			<legend><?php print __('User Login');?></legend>
			<form name='login' method='post' action='<?php print get_current_page();?>'>
				<input type='hidden' name='action' value='login'>
				<?php api_plugin_hook_function('login_before',
					array(
						'ldap_error' => $ldap_error,
						'ldap_error_message' => $ldap_error_message,
						'username' => $username,
						'user_enabled' => $user_enabled,
						'action' => get_nfilter_request_var('action')));
				?>
				<div class='loginTitle'>
					<p><?php print __('Enter your Username and Password below');?></p>
				</div>
				<div class='cactiLogin'>
					<table class='cactiLoginTable'>
						<tr>
							<td>
								<label for='login_username'><?php print __('Username');?></label>
							</td>
							<td>
								<input type='text' id='login_username' name='login_username' value='<?php print htmlspecialchars($username, ENT_QUOTES); ?>' placeholder='<?php print __esc('Username');?>'>
							</td>
						</tr>
						<tr>
							<td>
								<label for='login_password'><?php print __('Password');?></label>
							</td>
							<td>
								<input type='password' id='login_password' name='login_password' placeholder='********'>
							</td>
						</tr>
						<?php
						if (read_config_option('auth_method') == '3' || read_config_option('auth_method') == '4') {
							if (read_config_option('auth_method') == '3') {
								$realms = api_plugin_hook_function('login_realms',
									array(
										'1' => array(
											'name' => __('Local'),
											'selected' => false
										),
										'2' => array(
											'name' => __('LDAP'),
											'selected' => true
										)
									)
								);
							} else {
								$realms = get_auth_realms(true);
							}
						?>
						<tr>
							<td>
								<label for='realm'><?php print __('Realm');?></label>
							</td>
							<td>
								<select id='realm' name='realm'><?php
									if (sizeof($realms)) {
										foreach ($realms as $index => $realm) {
											print "\t\t\t\t\t<option value='" . $index . "'" . ($realm['selected'] ? ' selected':'') . '>' . htmlspecialchars($realm['name']) . "</option>\n";
										}
									}
									?>
								</select>
							</td>
						</tr>
					<?php } if (read_config_option('auth_cache_enabled') == 'on') { ?>
						<tr>
							<td colspan='2'>
								<input style='vertical-align:-3px;' type='checkbox' id='remember_me' name='remember_me' <?php print (isset($_COOKIE['cacti_remembers']) ? 'checked':'');?>>
								<label for='remember_me'><?php print __('Keep me signed in');?></label>
							</td>
						</tr>
					<?php } ?>
						<tr>
							<td cospan='2'>
								<input type='submit' value='<?php print __esc('Login');?>'>
							</td>
						</tr>
					</table>
				</div>
			<?php api_plugin_hook('login_after'); ?>
			</form>
			<div class='loginErrors'>
				<?php
				if ($ldap_error) {
					print $ldap_error_message;
				} else {
					if (get_nfilter_request_var('action') == 'login') {
						print __('Invalid User Name/Password Please Retype');
					}
					if ($user_enabled == '0') {
						print __('User Account Disabled');
					}
				}
				?>
			</div>
		</div>
		<div class='versionInfo'><?php print __('Version %1$s | %2$s', $version, COPYRIGHT_YEARS_SHORT);?></div>
	</div>
	<div class='loginRight'></div>
	<script type='text/javascript'>
	$(function() {
		$('body').css('height', $(window).height());
		$('.loginLeft').css('width',parseInt($(window).width()*0.33)+'px');
		$('.loginRight').css('width',parseInt($(window).width()*0.33)+'px');
	});
	</script>
	<?php include_once('./include/global_session.php');?>
</body>
</html>
