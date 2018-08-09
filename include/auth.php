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

global $current_user;

require_once('global.php');

if (!isset($config['cacti_db_version'])) {
	$version = get_cacti_version();
	$config['cacti_db_version'] = $version;
} else {
	$version = $config['cacti_db_version'];
}

if ($version != CACTI_VERSION && $config['poller_id'] == 1 && !defined('IN_CACTI_INSTALL')) {
	header ('Location: ' . $config['url_path'] . 'install/');
	exit;
}

if (get_current_page() == 'logout.php') {
	return true;
}

if (read_config_option('auth_method') != 0) {
	/* handle alternate authentication realms */
	api_plugin_hook_function('auth_alternate_realms');

	/* check for remember me function ality */
	if (!isset($_SESSION['sess_user_id'])) {
		$cookie_user = check_auth_cookie();
		if ($cookie_user !== false) {
			$_SESSION['sess_user_id'] = $cookie_user;
		}
	}

	/* don't even bother with the guest code if we're already logged in */
	if (isset($guest_account) && empty($_SESSION['sess_user_id'])) {
		$guest_user_id = get_guest_account();

		/* cannot find guest user */
		if (!empty($guest_user_id)) {
			$_SESSION['sess_user_id'] = $guest_user_id;
			return true;
		}
	}

	/* if we are a guest user in a non-guest area, wipe credentials */
	if (!empty($_SESSION['sess_user_id'])) {
		$guest_user_id = get_guest_account();

		if (!isset($guest_account) && $guest_user_id == $_SESSION['sess_user_id']) {
			kill_session_var('sess_user_id');
		}
	}

	if (empty($_SESSION['sess_user_id'])) {
		header('Cacti-FullScreen: true');
		if (isset($auth_json) && $auth_json == true) {
			print json_encode(
				array(
					'status' => '500',
					'statusText' => __('Not Logged In'),
					'responseText' => __('You must be logged in to access this area of Cacti.')
				)
			);
		} elseif (isset($auth_text) && $auth_text == true) {
			print __('FATAL: You must be logged in to access this area of Cacti.');
		} else {
			require_once($config['base_path'] . '/include/auth/auth_login.php');
		}

		exit;
	} elseif (!empty($_SESSION['sess_user_id'])) {
		$realm_id = 0;

		if (isset($user_auth_realm_filenames[get_current_page()])) {
			$realm_id = $user_auth_realm_filenames[get_current_page()];
		}

		/* Are we upgrading from a version before 1.2 which has the Install/Upgrade realm 26 */
		if ($realm_id == 26 && cacti_version_compare(get_cacti_version(), '1.2', '<')) {
			/* See if we can find any users that are allowed to upgrade */
			$install_sql_query = '
					SELECT COUNT(*)
					FROM (
						SELECT realm_id
						FROM user_auth_realm AS uar
						WHERE uar.realm_id = ?';
			$install_sql_params = array($realm_id);

			/* See if the group realms exist and if so, check if permission exists there too */
			if (db_table_exists('user_auth_group_realm')) {
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
			if (db_table_exists('user_auth_group_realm')) {
				$auth_sql_query .= '
						UNION
						SELECT realm_id
						FROM user_auth_group_realm AS uagr
						INNER JOIN user_auth_group_members AS uagm
						ON uagr.group_id=uagm.group_id
						INNER JOIN user_auth_group AS uag
						ON uag.id=uagr.group_id
						WHERE uag.enabled="on"
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
			$title_hook = 'permission_denied';
			$title_header = __('Permission Denied');
			$title_body = '<p>' . __('You are not permitted to access this section of Cacti.') . '</p><p>' . __('If you feel that this is an error. Please contact your Cacti Administrator.') . '</p>';
			$title_text = __('You are not permitted to access this section of Cacti.') . "\n" . __('If you feel that this is an error. Please contact your Cacti Administrator.');

			if ($realm_id == 26) {
				$title_hook = 'installation_in_progress';
				$title_header = __('Installation In Progress');
				$title_body = '<p>' . __('There is an Installation or Upgrade in progress.') . '</p><p>' . __('Only Cacti Administrators with Install/Upgrade privilege may login at this time') . '</p>';
				$title_text = __('There is an Installation or Upgrade in progress.') . "\n" . __('Only Cacti Administrators with Install/Upgrade privilege may login at this time');
			}

			header('Cacti-FullScreen: true');
			if (isset($auth_json) && $auth_json == true) {
				print json_encode(
					array(
						'status' => '500',
						'statusText' => $title_header,
						'responseText' => $title_text
					)
				);
			} elseif (isset($auth_text) && $auth_text == true) {
				print $title_text;
			} else {
				html_common_login_header($title_hook, $title_header, $title_header, '');
				print $title_body;
				html_common_login_footer('');
			}
			exit;
		}

		$current_user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));
		if ($current_user['must_change_password'] == 'on') {
			$_SESSION['sess_change_password'] = true;
		}

	}

	if (isset($_SESSION['sess_change_password'])) {
		header('Cacti-FullScreen: true');
		if (isset($auth_json) && $auth_json == true) {
			print json_encode(
				array(
					'status' => '501',
					'statusText' => __('Must Change Password'),
					'responseText' => __('You must change your password to access this area of Cacti.')
				)
			);
		} elseif (isset($auth_text) && $auth_text == true) {
			print __('FATAL: You must change your password to access this area of Cacti.');
		} else {
			require_once($config['base_path'] . '/include/auth/auth_changepassword.php');
		}
		exit;
	}
}
