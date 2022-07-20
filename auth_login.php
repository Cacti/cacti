<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

	if (strpos($username, '@') !== false) {
		$upart = explode('@', $username);
		$username = $upart[0];
	}

	/* Handle mapping basic accounts to shortform accounts.
	 * Fromat of map file is CSV: basic,shortform */
	$mapfile = read_config_option('path_basic_mapfile');
	if ($mapfile != '' && file_exists($mapfile) && is_readable($mapfile)) {
		$records = file($mapfile);
		$found   = false;

		if (cacti_sizeof($records)) {
			foreach($records as $r) {
				list($basic, $shortform) = str_getcsv($r);

				if (trim($basic) == $username) {
					$username = trim($shortform);
					$found    = true;

					break;
				}
			}
		}

		if (!$found) {
			cacti_log("WARNING: Username $username not found in basic mapfile.", false, 'AUTH');
		}
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

/* process login */
$user         = array();
$copy_user    = false;
$user_auth    = false;
$user_enabled = 1;
$ldap_error   = false;
$ldap_error_message = '';
$realm        = 0;
$frv_realm    = get_nfilter_request_var('realm');

if (get_nfilter_request_var('action') == 'login') {
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

		if (!$user && get_template_account() == '0' && get_guest_account() == '0') {
			cacti_log("ERROR: User '" . $username . "' authenticated by Web Server, but both Template and Guest Users are not defined in Cacti.  Exiting.", false, 'AUTH');
			$username = html_escape($username);

			display_custom_error_message(__('%s authenticated by Web Server, but both Template and Guest Users are not defined in Cacti.', $username));
			header('Location: index.php');
			exit;
		}

		break;
	case '3':
		/* LDAP Auth */
 		if ($frv_realm == '2' && get_nfilter_request_var('login_password') != '') {
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
							db_check_password_length();
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
	if (!cacti_sizeof($user) && $copy_user && get_template_account() != '0' && $username != '') {
		cacti_log("NOTE: User '" . $username . "' does not exist, copying template user", false, 'AUTH');

		$user_template = db_fetch_row_prepared('SELECT *
			FROM user_auth
			WHERE id = ?',
			array(get_template_account()));

		/* check that template user exists */
		if (!empty($user_template)) {
			if ($realm == 3) { // This is an ldap login
				/* get user CN*/
				$cn_full_name = read_config_option('cn_full_name');
				$cn_email     = read_config_option('cn_email');

				if ($cn_full_name != '' || $cn_email != '') {
					$ldap_cn_search_response = cacti_ldap_search_cn($username, array($cn_full_name, $cn_email));

   					if (isset($ldap_cn_search_response['cn'])) {
						$data_override = array();

						if (array_key_exists($cn_full_name, $ldap_cn_search_response['cn'])) {
							$data_override['full_name'] = $ldap_cn_search_response['cn'][$cn_full_name];
						} else {
							$data_override['full_name'] = '';
						}

						if (array_key_exists($cn_email, $ldap_cn_search_response['cn'])) {
							$data_override['email_address'] = $ldap_cn_search_response['cn'][$cn_email];
						} else {
							$data_override['email_address'] = '';
						}

						user_copy($user_template['username'], $username, 0, $realm, false, $data_override);
					} else {
						$ldap_response = (isset($ldap_cn_search_response[0]) ? $ldap_cn_search_response[0] : '(no response given)');
						$ldap_code = (isset($ldap_cn_search_response['error_num']) ? $ldap_cn_search_response['error_num'] : '(no code given)');
						cacti_log('LOGIN: Email Address and Full Name fields not found, reason: ' . $ldap_response . 'code: ' . $ldap_code, false, 'AUTH');
						user_copy($user_template['username'], $username, 0, $realm);
					}
				} else {
					user_copy($user_template['username'], $username, 0, $realm);
				}
			} else {
				user_copy($user_template['username'], $username, 0, $realm);
			}

			/* requery newly created user */
			$user = db_fetch_row_prepared('SELECT *
				FROM user_auth
				WHERE username = ?
				AND realm = ?',
				array($username, $realm));
		} else {
			/* error */
			display_custom_error_message(__('Template user id %s does not exist.', read_config_option('user_template')));
			cacti_log("LOGIN: Template user id '" . read_config_option('user_template') . "' does not exist.", false, 'AUTH');
			header('Location: index.php');
			exit;
		}
	}

	/* Guest account checking - Not for builtin */
	$guest_user = false;
	if ((!cacti_sizeof($user)) && ($user_auth) && (get_guest_account() != '0')) {
		/* Locate guest user record */
		$user = db_fetch_row_prepared('SELECT id, username, enabled
			FROM user_auth
			WHERE id = ?',
			array(get_guest_account()));

		if ($user) {
			cacti_log("LOGIN: Authenticated user '" . $username . "' using guest account '" . $user['username'] . "'", false, 'AUTH');

			$guest_user = true;
		} else {
			/* error */
			display_custom_error_message(__('Guest user id %s does not exist.', read_config_option('guest_user')));
			cacti_log("LOGIN: Unable to locate guest user '" . read_config_option('guest_user') . "'", false, 'AUTH');
			header('Location: index.php');
			exit;
		}
	}

	/* Process the user  */
	if (cacti_sizeof($user)) {
		auth_login($user);
		if ($user['tfa_enabled'] != '') {
			header('Location: auth_2fa.php');
			exit;
		} else {
			auth_post_login_redirect($user);
		}

		if (!auth_user_has_access($user)) {
			/* error */
			display_custom_error_message(__('You do not have access to any area of Cacti.  Contact your administrator.'));
			cacti_log(sprintf("LOGIN: User %s with id %s does not have access to any area of Cacti", $user['username'], $user['id']), false, 'AUTH');
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
		if ($user['must_change_password'] == 'on' && read_config_option('auth_method') == 1 && $user['password_change'] == 'on') {
			$_SESSION['sess_change_password'] = true;
		}

		if (db_table_exists('user_auth_group')) {
			$group_options = db_fetch_cell_prepared('SELECT MAX(login_opts)
				FROM user_auth_group AS uag
				INNER JOIN user_auth_group_members AS uagm
				ON uag.id=uagm.group_id
				WHERE user_id = ?
				AND login_opts != 4',
				array($_SESSION['sess_user_id']));

			if (!empty($group_options)) {
				$user['login_opts'] = $group_options;
			}
		}

		if (user_setting_exists('user_language', $_SESSION['sess_user_id'])) {
			$_SESSION['sess_user_language'] = read_user_setting('user_language');
		}

		auth_login_redirect($user['login_opts']);
	} elseif (!$guest_user && $user_auth) {
		/* No guest account defined */
		display_custom_error_message(__('Access Denied, please contact you Cacti Administrator.'));
		cacti_log('LOGIN: Access Denied, Either Guest account is not enabled or there is no User Template defined to copy', false, 'AUTH');
		header('Location: index.php');
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
				array($username, get_client_addr('')));
		}
	}
}

function auth_user_has_access($user) {
	$access = false;

	// See if they have access to any realms
	$realms = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM user_auth_realm
		WHERE user_id = ?',
		array($user['id']));

	if ($realms > 0) {
		return true;
	}

	// See if they have general graph access as a guest account
	if (read_config_option('guest_user') > 0) {
		if ($user['show_tree'] == 'on' || $user['show_list'] == 'on' || $user['show_preview'] == 'on') {
			return true;
		}
	}

	// See if they have access to any group realms
	$user_groups = db_fetch_assoc_prepared('SELECT *
		FROM user_auth_group_members
		WHERE user_id = ?',
		array($user['id']));

	if (cacti_sizeof($user_groups)) {
		foreach($user_groups as $g) {
			$realms = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM user_auth_group_realm
				WHERE group_id = ?',
				array($g['group_id']));

			if ($realms > 0) {
				return true;
			}

			// See if they have general graph access as a guest account
			if (read_config_option('guest_user') > 0) {
				if ($g['show_tree'] == 'on' || $g['show_list'] == 'on' || $g['show_preview'] == 'on') {
					return true;
				}
			}
		}
	}

	return false;
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

				if (!cacti_sizeof($user) && $copy_user && $template_user > 0 && $username != '') {
					cacti_log("WARN: User '" . $username . "' does not exist, copying template user", false, 'AUTH');

					/* check that template user exists */
					$user_template = db_fetch_row_prepared('SELECT *
						FROM user_auth
						WHERE id = ?',
						array(get_template_account()));

					if (!empty($user_template['id']) && $user_template['id'] > 0) {
						/* template user found */
						$cn_full_name = db_fetch_cell_prepared('SELECT cn_full_name
							FROM user_domains_ldap
							WHERE domain_id = ?',
							array(get_nfilter_request_var('realm')-1000));

						$cn_email = db_fetch_cell_prepared('SELECT cn_email
							FROM user_domains_ldap
							WHERE domain_id = ?',
							array(get_nfilter_request_var('realm')-1000));

						if ($cn_full_name != '' || $cn_email != '') {
							$ldap_cn_search_response = domain_ldap_search_cn($username, array($cn_full_name,$cn_email),get_nfilter_request_var('realm')-1000);
							if (isset($ldap_cn_search_response['cn'])) {
								$data_override = array();

								if (array_key_exists($cn_full_name, $ldap_cn_search_response['cn'])) {
									$data_override['full_name'] = $ldap_cn_search_response['cn'][$cn_full_name];
								} else {
									$data_override['full_name'] = '';
								}

								if (array_key_exists($cn_email, $ldap_cn_search_response['cn'])) {
									$data_override['email_address'] = $ldap_cn_search_response['cn'][$cn_email];
								} else {
									$data_override['email_address'] = '';
								}

								user_copy($user_template['username'], $username, 0, $realm, false, $data_override);
							} else {
								cacti_log('LOGIN: fields not found ' . $ldap_cn_search_response[0] . 'code: ' . $ldap_cn_search_response['error_num'], false, 'AUTH');
								user_copy($user_template['username'], $username, 0, $realm);
							}
						} else {
							user_copy($user_template['username'], $username, 0, $realm);
						}

						/* requery newly created user */
						$user = db_fetch_row_prepared('SELECT *
							FROM user_auth
							WHERE username = ?
							AND realm = ?',
							array($username, $realm));
					} else {
						/* error */
						display_custom_error_message(__('Template user id %s does not exist.', $template_user));
						cacti_log("LOGIN: Template user id '" . $template_user . "' does not exist.", false, 'AUTH');
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

function domains_ldap_auth($username, $password = '', $dn = '', $realm = 0) {
	$ldap = new Ldap;

	if (!empty($username)) $ldap->username = $username;
	if (!empty($password)) $ldap->password = $password;
	if (!empty($dn))       $ldap->dn       = $dn;

	$ld = db_fetch_row_prepared('SELECT *
		FROM user_domains_ldap
		WHERE domain_id = ?',
		array($realm-1000));

	if (cacti_sizeof($ld)) {
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

		/* If the server list is a space delimited set of servers
		 * process each server until you get a bind, or fail
		 */
		$ldap_servers = preg_split('/\s+/', $ldap->host);

		foreach($ldap_servers as $ldap_server) {
			$ldap->host = $ldap_server;

			$response = $ldap->Authenticate();

			if ($response['error_num'] == 0) {
				return $response;
			}
		}

		return $response;
	} else {
		return false;
	}
}

function domain_ldap_search_cn($username, $cn = array(), $realm) {
	$ldap = new Ldap;

	if (!empty($username)) { $ldap->username = $username; }

	$ld = db_fetch_row_prepared('SELECT *
		FROM user_domains_ldap
		WHERE domain_id = 1',
		array($realm-1000));
	
	if (cacti_sizeof($ld)) {
		if (!empty($ld['dn']))                { $ldap->dn                = $ld['dn'];                }
		if (!empty($ld['server']))            { $ldap->host              = $ld['server'];            }
		if (!empty($ld['port']))              { $ldap->port              = $ld['port'];              }
		if (!empty($ld['port_ssl']))          { $ldap->port_ssl          = $ld['port_ssl'];          }
		if (!empty($ld['proto_version']))     { $ldap->version           = $ld['proto_version'];     }
		if (!empty($ld['encryption']))        { $ldap->encryption        = $ld['encryption'];        }
		if (!empty($ld['referrals']))         { $ldap->referrals         = $ld['referrals'];         }
		if (!empty($ld['mode']))              { $ldap->mode              = $ld['mode'];              }
		if (!empty($ld['search_base']))       { $ldap->search_base       = $ld['search_base'];       }
		if (!empty($ld['search_filter']))     { $ldap->search_filter     = $ld['search_filter'];     }
		if (!empty($ld['specific_dn']))       { $ldap->specific_dn       = $ld['specific_dn'];       }
		if (!empty($ld['specific_password'])) { $ldap->specific_password = $ld['specific_password']; }

		if ($ld['group_require'] == 'on') {
			$ldap->group_require = true;
		} else {
			$ldap->group_require = false;
		}

		if (!empty($ld['group_dn']))          { $ldap->group_dn          = $ld['group_dn'];          }
		if (!empty($ld['group_attrib']))      { $ldap->group_attrib      = $ld['group_attrib'];      }
		if (!empty($ld['group_member_type'])) { $ldap->group_member_type = $ld['group_member_type']; }
	}

	if (!empty($cn)) { $ldap->cn = $cn; }

	return $ldap->Getcn();
}

function domains_ldap_search_dn($username, $realm) {
	$ldap = new Ldap;

	if (!empty($username)) $ldap->username = $username;

	$ld = db_fetch_row_prepared('SELECT *
		FROM user_domains_ldap
		WHERE domain_id = ?',
		array($realm-1000));

	if (cacti_sizeof($ld)) {
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

		/* If the server list is a space delimited set of servers
		 * process each server until you get a bind, or fail
		 */
		$ldap_servers = preg_split('/\s+/', $ldap->host);

		foreach($ldap_servers as $ldap_server) {
			$ldap->host = $ldap_server;

			$response = $ldap->Search();

			if ($response['error_num'] == 0) {
				return $response;
			}
		}

		return $response;
	} else {
		return false;
	}
}

if (api_plugin_hook_function('custom_login', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
	return;
}

$selectedTheme = get_selected_theme();

$auth_method = read_config_option('auth_method');
$auth_cache_enabled = read_config_option('auth_cache_enabled');
$checked = (isset($_COOKIE['cacti_remembers']) || !isempty_request_var('remember_me'));

if (read_config_option('auth_method') == '3') {
	$realms = api_plugin_hook_function('login_realms',
		array(
			'1' => array('name' => __('Local'), 'selected' => false),
			'2' => array('name' => __('LDAP'),  'selected' => true)
		)
	);
} else {
	$realms = get_auth_realms(true);
}

// try and remember previously selected realm
if ($frv_realm && array_key_exists($frv_realm, $realms)) {
	foreach ($realms as $key => $realm) {
		$realms[$key]['selected'] = ($frv_realm == $key);
	}
}

$focus_control = (empty($username)) ? 'username' : 'password';
$error_message = "";
if ($ldap_error) {
	$error_message = $ldap_error;
} else {
	if (get_nfilter_request_var('action') == 'login') {
		$error_message = __('Invalid User Name/Password Please Retype');
	}
	if ($user_enabled == '0') {
		$error_message =  __('User Account Disabled');
	}
}

global $twig_vars;
$twig_auth = empty($twig_vars['auth']) ? [] : $twig_vars['auth'];
$twig_auth = array_merge($twig_auth, [
	'action'      => 'login',
	'page'        => get_current_page(),
	'cache'       => $auth_cache_enabled,
	'remember'    => $checked,
	'realms'      => $realms,
	'focus'       => $focus_control,
	'error'       => $error_message,
	'legend'      => __('User Login'),
	'title'       => __('Enter your Username and Password below'),
]);

$twig_vars['auth'] = $twig_auth;

html_auth_header('login', __('Login to Cacti'), __('User Login'), __('Enter your Username and Password below'),
	array(
		'ldap_error' => $ldap_error,
		'ldap_error_message' => $ldap_error_message,
		'user_enabled' => $user_enabled,
		'action' => get_nfilter_request_var('action')
	));

echo $twig->render('auth/login.html.twig', $twig_vars);

html_auth_footer('login', $error_message, "
	<script>
		$(function() {
			$('body').css('height', $(window).height());
			$('.cactiAuthLeft').css('width',parseInt($(window).width()*0.33)+'px');
			$('.cactiAuthRight').css('width',parseInt($(window).width()*0.33)+'px');
			$('#login_${focus_control}').focus();
		});
	</script>
");
