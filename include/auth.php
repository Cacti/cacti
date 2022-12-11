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

global $current_user;

require_once('global.php');

if (!isset($config['cacti_db_version'])) {
	$version = get_cacti_db_version();
	$config['cacti_db_version'] = $version;
	if (!defined('CACTI_DB_VERSION')) {
		define('CACTI_DB_VERSION', $version);
	}
} else {
	$version = $config['cacti_db_version'];
}

$auth_method = read_config_option('auth_method');

/**
 * Check to see if Cacti authentication is disabled
 * and force Local Authentication on if found
 */
check_reset_no_authentication($auth_method);

/**
 * Check to see if the Database Cacti version is different
 * from the installed Cacti version and start the install
 * process if found to be different.
 */
if (is_install_needed() && !defined('IN_CACTI_INSTALL')) {
	header('Location: ' . $config['url_path'] . 'install/');
	exit;
}

/**
 * The logout page does not require authentication
 * so, short cut the process.
 */
if (get_current_page() == 'logout.php') {
	return true;
}

if ($auth_method != AUTH_METHOD_NONE) {
	/* handle alternate authentication realms */
	api_plugin_hook_function('auth_alternate_realms');

	/**
	 * handle change password dialog and auth cookie if not using basic auth
	 */
	if ($auth_method != AUTH_METHOD_BASIC) {
		if (isset($_SESSION['sess_change_password'])) {
			header('Location: ' . $config['url_path'] . 'auth_changepassword.php?ref=' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php'));
			exit;
		}

		/* check for remember me functionality */
		if (!isset($_SESSION['sess_user_id'])) {
			$cookie_user = check_auth_cookie();
			if ($cookie_user !== false) {
				$_SESSION['sess_user_id']     = $cookie_user;
				$_SESSION['sess_user_agent']  = $_SERVER['HTTP_USER_AGENT'];
				$_SESSION['sess_client_addr'] = get_client_addr();
			}
		}
	}

	/**
	 * Check for basic auth, and if the user has been logged in via the server
	 * but their user_id is not set, include the auth_login.php script to
	 * process their log in.
	 */
	if ($auth_method == AUTH_METHOD_BASIC && !isset($_SESSION['sess_user_id'])) {
		$username = get_basic_auth_username();
		if ($username !== false) {
			$current_user = db_fetch_row_prepared('SELECT *
				FROM user_auth
				WHERE realm = 2
				AND username = ?',
				array($username));

			if (cacti_sizeof($current_user)) {
				$_SESSION['sess_user_id']     = $current_user['id'];;
				$_SESSION['sess_user_agent']  = $_SERVER['HTTP_USER_AGENT'];
				$_SESSION['sess_client_addr'] = get_client_addr();

				return true;
			} else {
				require_once($config['base_path'] . '/auth_login.php');
			}
		}
	}

	/**
	 * If the special boolean $guest_account is set for a page, then the guest
	 * account can be used.  Where this may not be the case is with basic auth
	 * where to enter the Cacti website, you must first have a valid account.
	 * if that is the case, then use that valid accounts permissions and not
	 * the guest account.
	 */
	if (isset($guest_account)) {
		$guest_user_id = get_guest_account();

		/* find guest user */
		if (!empty($guest_user_id)) {
			if (empty($_SESSION['sess_user_id'])) {
				$_SESSION['sess_user_id']     = $guest_user_id;
				$_SESSION['sess_user_agent']  = $_SERVER['HTTP_USER_AGENT'];
				$_SESSION['sess_client_addr'] = get_client_addr();
			}

			$current_user = db_fetch_row_prepared('SELECT *
				FROM user_auth
				WHERE id = ?',
				array($_SESSION['sess_user_id']));

			return true;
		}
	}

	/**
	 * If we are a guest user in a non-guest area, wipe credentials
	 * user will be redirected back to the login page.
	 */
	if (!isset($guest_account) && isset($_SESSION['sess_user_id'])) {
		if (get_guest_account() === $_SESSION['sess_user_id']) {
			kill_session_var('sess_user_id');
			cacti_session_destroy();
			cacti_session_start();
		}
	}

	if (empty($_SESSION['sess_user_id'])) {
		if (isset($auth_json) && $auth_json == true) {
			print json_encode(
				array(
					'status' => '500',
					'statusText' => __('Not Logged In'),
					'responseText' => __('You must be logged in to access this area of Cacti.')
				)
			);
		} elseif (isset($auth_text) && $auth_text == true) {
			/* handle graph_image.php to respond with text. */
			print __('FATAL: You must be logged in to access this area of Cacti.');
		} else {
			require_once($config['base_path'] . '/auth_login.php');
		}

		exit;
	} else {

		if (empty($_SESSION['sess_user_2fa'])) {
			$user_2fa = db_fetch_cell_prepared(
				'SELECT tfa_enabled
					FROM user_auth
					WHERE id = ?',
				array($_SESSION['sess_user_id'])
			);

			if (!empty($user_2fa)) {
				header('Location: ' . $config['url_path'] . '/auth_2fa.php');
				exit;
			} else {
				$_SESSION['sess_user_2fa'] = true;
			}
		}

		$realm_id = 0;

		if (isset($user_auth_realm_filenames[get_current_page()])) {
			$realm_id = $user_auth_realm_filenames[get_current_page()];
		}

		/* Are we upgrading from a version before 1.2 which has the Install/Upgrade realm 26 */
		if ($realm_id == 26) {
			/* See if we can find any users that are allowed to upgrade */
			$install_sql_query = '
				SELECT COUNT(*)
				FROM (
					SELECT realm_id
					FROM user_auth_realm AS uar
					WHERE uar.realm_id = ?';

			$install_sql_params = array($realm_id);

			/* See if the group realms exist and if so, check if permission exists there too */
			if (db_table_exists('user_auth_group_realm') &&
				db_table_exists('user_auth_group') &&
				db_table_exists('user_auth_group_members')) {
				$install_sql_query .= '
					UNION
					SELECT realm_id
					FROM user_auth_group_realm AS uagr
					INNER JOIN user_auth_group_members AS uagm
					ON uagr.group_id=uagm.group_id
					INNER JOIN user_auth_group AS uag
					ON uag.id=uagr.group_id
					WHERE uag.enabled="on"
					AND uagr.realm_id = ?';

				$install_sql_params = array_merge($install_sql_params, array($realm_id));
			}

			$install_sql_query .= '
				) AS authorized';

			$has_install_user = db_fetch_cell_prepared($install_sql_query, $install_sql_params);

			if (!$has_install_user) {
				/* We did not find any existing users who can upgrade/install so add any admin *
				 * who has access to the system settings (realm 15) by default                 */
				db_execute('INSERT INTO `user_auth_realm` (realm_id, user_id)
					SELECT 26 as realm_id, ua.id
					FROM user_auth ua
					INNER JOIN user_auth_realm uar
					ON uar.user_id=ua.id
					LEFT JOIN user_auth_realm uar2
					ON uar2.user_id=ua.id
					AND uar2.realm_id=26
					WHERE uar.realm_id=15
					AND uar2.user_id IS NULL');
			}
		}

		if ($realm_id > 0) {
			$auth_sql_query = '
				SELECT COUNT(*)
				FROM (
					SELECT realm_id
					FROM user_auth_realm AS uar
					WHERE uar.user_id = ?
					AND uar.realm_id = ?';

			$auth_sql_params = array($_SESSION['sess_user_id'], $realm_id);

			/* Because we now expect installation to be done by authorized users, check the group_realm *
			 * exists before using it as this may not be present if upgrading from pre-1.x              */
			if (db_table_exists('user_auth_group_realm') &&
				db_table_exists('user_auth_group') &&
				db_table_exists('user_auth_group_members')) {
				$auth_sql_query .= '
					UNION
					SELECT realm_id
					FROM user_auth_group_realm AS uagr
					INNER JOIN user_auth_group_members AS uagm
					ON uagr.group_id = uagm.group_id
					INNER JOIN user_auth_group AS uag
					ON uag.id = uagr.group_id
					WHERE uag.enabled = "on"
					AND uagm.user_id = ?
					AND uagr.realm_id = ?';

				$auth_sql_params = array_merge($auth_sql_params, array($_SESSION['sess_user_id'], $realm_id));
			}

			$auth_sql_query .= '
				) AS authorized';

			$authorized = db_fetch_cell_prepared($auth_sql_query, $auth_sql_params);
		} else {
			$authorized = false;
		}

		if ($realm_id != -1 && !$authorized) {
			if (api_plugin_hook_function('custom_denied', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
				exit;
			}

			if (isset($_SERVER['HTTP_REFERER'])) {
				$goBack = "<td colspan='2' class='center'>[<a href='" . $_SERVER['HTTP_REFERER'] . "'>" . __('Return') . "</a> | <a href='" . $config['url_path'] . "logout.php'>" . __('Login Again') . "</a>]</td>";
			} elseif ($auth_method != AUTH_METHOD_BASIC && $auth_method > AUTH_METHOD_NONE) {
				$goBack = "<td colspan='2' class='center'>[<a href='" . $config['url_path'] . "logout.php'>" . __('Login Again') . "</a>]</td>";
			}

			raise_ajax_permission_denied();

			$title_header = __('Permission Denied');
			$title_body = '<p>' . __('You are not permitted to access this section of Cacti.') . '</p><p>' . __('If you feel that this is an error. Please contact your Cacti Administrator.');

			if ($realm_id == 26) {
				$title_header = __('Installation In Progress');
				$title_body = '<p>' . __('There is an Installation or Upgrade in progress.') . '</p><p>' . __('Only Cacti Administrators with Install/Upgrade privilege may login at this time') . '</p>';
			}
			print "<!DOCTYPE html>\n";
			print "<html>\n";
			print "<head>\n";
			html_common_header($title_header);
			print "</head>\n";
			print "<body class='logoutBody'>
			<div class='logoutLeft'></div>
			<div class='logoutCenter'>
				<div class='logoutArea'>
					<div class='cactiLogoutLogo'></div>
					<legend>" . $title_header . "</legend>
					<div class='logoutTitle'>
						" . $title_body . "
						</p>
						<center>" . $goBack . "</center>
					</div>
					<div class='logoutErrors'></div>
				</div>
				<div class='versionInfo'>" . __('Version') . ' ' . $version . " | " . COPYRIGHT_YEARS_SHORT . "</div>
			</div>
			<div class='logoutRight'></div>
			<script type='text/javascript'>
			$(function() {
				$('.loginLeft').css('width',parseInt($(window).width()*0.33)+'px');
				$('.loginRight').css('width',parseInt($(window).width()*0.33)+'px');
			});
			</script>\n";
			include_once('global_session.php');
			print "</body>
			</html>\n";
			exit;
		}

		$current_user = db_fetch_row_prepared('SELECT *
			FROM user_auth
			WHERE id = ?',
			array($_SESSION['sess_user_id']));
	}
}
