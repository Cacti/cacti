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

global $current_user;

$included_files = get_included_files();
require_once('global.php');
//cacti_log('After global.php (' . implode(', ', $included_files) . ')', true, 'AUTH_NONE', POLLER_VERBOSITY_DEVDBG);

if (!isset($config['cacti_db_version'])) {
	$version = get_cacti_db_version();
	$config['cacti_db_version'] = $version;
	if (!defined('CACTI_DB_VERSION')) {
		define('CACTI_DB_VERSION', $version);
	}
} else {
	$version = $config['cacti_db_version'];
}

$auth_method = read_config_option('auth_method', true);
if (read_config_option('auth_method') == 0) {
	$admin_id = db_execute_prepared('SELECT id
		FROM user_auth
		WHERE id = ?',
		array(read_config_option('admin_user')));

	cacti_log('Admin User (' . read_config_option('admin_user') . ' vs ' . $admin_id . ')', true, 'AUTH_NONE', POLLER_VERBOSITY_DEVDBG);
	if (!$admin_id) {
		$admin_sql_query = 'SELECT TOP 1 id FROM (
			SELECT ua.id
			FROM user_auth AS ua
			INNER JOIN user_auth_realm AS uar
			ON uar.user_id = ua.id
			WHERE uar.realm_id = ?';

		$admin_sql_params = array(15);

		if (db_table_exists('user_auth_group_realm')) {
			$admin_sql_query .= '
			UNION
			SELECT ua.id
			FROM user_auth AS ua
			INNER JOIN user_auth_group_members AS uagm
			ON uagm.user_id = ua.id
			INNER JOIN user_auth_group AS uag
			ON uag.id = uagm.group_id
			INNER JOIN user_auth_group_realm AS uagr
			ON uagr.group_id=uag.group_id
			WHERE uag.enabled="on" AND ua.enabled="on"
			AND uagr.realm_id = ?';

			$admin_sql_params[] = 15;
		}

		$admin_sql_query .= '
			) AS id';

		cacti_log('SQL query ' . $admin_sql_query, true, 'AUTH_NONE', POLLER_VERBOSITY_DEVDBG);
		cacti_log('SQL param ' . implode(',', $admin_sql_params), true, 'AUTH_NONE', POLLER_VERBOSITY_DEVDBG);
		$admin_id = db_fetch_cell_prepared($admin_sql_query, $admin_sql_params);
		cacti_log('SQL result ' . $admin_id, true, 'AUTH_NONE', POLLER_VERBOSITY_DEVDBG);
	}

	if (!$admin_id) {
		$admin_id = db_fetch_cell('SELECT id FROM user_auth WHERE username = \'admin\'');
		cacti_log('Final attempt ' . $admin_id, true, 'AUTH_NONE', POLLER_VERBOSITY_DEVDBG);
	}

	if (!$admin_id) {
		die('Failed to find a valid admin account with console access');
	}

	// Authentication method is currently set to none
	// lets switch this to basic and allow setting of
	// a password.
	db_execute_prepared("UPDATE user_auth SET
		password = '',
		must_change_password = 'on',
		password_change = 'on'
		WHERE id = ?",
		array($admin_id));

	set_config_option('auth_method', 1);

	$_SESSION['sess_user_id'] = $admin_id;
	$_SESSION['sess_change_password'] = true;
	header ('Location: ' . $config['url_path'] . 'auth_changepassword.php?action=force&ref=' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php'));
	exit;
}

if (is_install_needed() && !defined('IN_CACTI_INSTALL')) {
	header ('Location: ' . $config['url_path'] . 'install/');
	exit;
}

if (get_current_page() == 'logout.php') {
	return true;
}

/* handle alternate authentication realms */
api_plugin_hook_function('auth_alternate_realms');

/* handle change password dialog */
if ((isset($_SESSION['sess_change_password'])) && (read_config_option('webbasic_enabled') != 'on')) {
	header ('Location: ' . $config['url_path'] . 'auth_changepassword.php?ref=' . (isset($_SERVER['HTTP_REFERER']) ? sanitize_uri($_SERVER['HTTP_REFERER']) : 'index.php'));
	exit;
}

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
		print __('FATAL: You must be logged in to access this area of Cacti.');
	} else {
		require_once($config['base_path'] . '/auth_login.php');
	}

	exit;
}

if (empty($_SESSION['sess_user_2fa'])) {
	if (db_column_exists('user_auth', 'tfa_enabled')) {
		$user_2fa = db_fetch_cell_prepared('SELECT tfa_enabled
			FROM user_auth
			WHERE id = ?',
			array($_SESSION['sess_user_id']));

		if (!empty($user_2fa)) {
			header('Location: ' . $config['url_path'] . 'auth_2fa.php');
			exit;
		} else {
			$_SESSION['sess_user_2fa'] = true;
		}
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

	/* Because we now expect installation to be done by authorized users, check the group_realm *
	 * exists before using it as this may not be present if upgrading from pre-1.x              */
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
	if (api_plugin_hook_function('custom_denied', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
		exit;
	}

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
	if (isset($_SERVER['HTTP_REFERER'])) {
		$goBack = "<td colspan='2' class='center'>[<a href='" . sanitize_uri($_SERVER['HTTP_REFERER']) . "'>" . __('Return') . "</a> | <a href='" . $config['url_path'] . "logout.php'>" . __('Login Again') . "</a>]</td>";
	} else {
		$goBack = "<td colspan='2' class='center'>[<a href='" . $config['url_path'] . "logout.php'>" . __('Login Again') . "</a>]</td>";
	}

	$title_header = __('Permission Denied');
	$title_body = '<p>' . __('You are not permitted to access this section of Cacti.') . '</p><p>' . __('If you feel that this is an error. Please contact your Cacti Administrator.');

	if ($realm_id == 26) {
		$title_header = __('Installation In Progress');
		$title_body = '<p>' . __('There is an Installation or Upgrade in progress.') . '</p><p>' . __('Only Cacti Administrators with Install/Upgrade privilege may login at this time') . '</p>';
	}

	raise_ajax_permission_denied();

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

$current_user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));
