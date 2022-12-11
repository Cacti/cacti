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

include(__DIR__ . '/../include/vendor/GoogleAuthenticator/FixedBitNotation.php');
include(__DIR__ . '/../include/vendor/GoogleAuthenticator/GoogleAuthenticatorInterface.php');
include(__DIR__ . '/../include/vendor/GoogleAuthenticator/GoogleAuthenticator.php');
include(__DIR__ . '/../include/vendor/GoogleAuthenticator/GoogleQrUrl.php');
include(__DIR__ . '/../include/vendor/GoogleAuthenticator/RuntimeException.php');

/**
 * clear_auth_cookie - clears a users security token
 *
 * @return (void)
 */
function clear_auth_cookie() {
	global $config;

	if (isset($_COOKIE['cacti_remembers']) && read_config_option('auth_cache_enabled') == 'on') {
		$parts = explode(',', $_COOKIE['cacti_remembers']);

		if (cacti_sizeof($parts) == 2) {
			$user_id  = $parts[0];
			$realm_id = -1;
			$token    = $parts[1];
		} else {
			$user_id  = $parts[0];
			$realm_id = $parts[1];
			$token    = $parts[2];
		}

		// Legacy support which leaked usernames
		if (!is_numeric($user_id)) {
			$user_id = db_fetch_cell_prepared('SELECT id
				FROM user_auth
				WHERE username = ?',
				array($user_id));
		}

		if ($user_id > 0) {
			$secret = hash('sha512', $token, false);

			cacti_cookie_session_logout();

			db_execute_prepared('DELETE FROM user_auth_cache
				WHERE user_id = ?
				AND token = ?',
				array($user_id, $secret));
		}
	}
}

/**
 * set_auth_cookie - sets a users security token
 *
 * @param  (array) user is the user_auth row for the user
 *
 * @return (bool) True if token set worked, otherwise false
 */
function set_auth_cookie($user) {
	global $config;

	if (db_table_exists('user_auth_cache')) {
		clear_auth_cookie();

		$nssecret = md5($_SERVER['REQUEST_TIME'] .  mt_rand(10000,10000000)) . md5(get_client_addr());

		$secret = hash('sha512', $nssecret, false);

		db_execute_prepared('REPLACE INTO user_auth_cache
			(user_id, hostname, last_update, token)
			VALUES
			(?, ?, NOW(), ?);',
			array($user['id'], get_client_addr(), $secret));

		cacti_cookie_session_set($user['id'], $user['realm'], $nssecret);
	}
}

/**
 * check_auth_cookie - clears a users security token
 *
 * @return (int) The user of the session cookie, otherwise false
 */
function check_auth_cookie() {
	if (isset($_COOKIE['cacti_remembers']) &&
		read_config_option('auth_cache_enabled') == 'on' &&
		db_table_exists('user_auth_cache')) {

		$parts = explode(',', $_COOKIE['cacti_remembers']);

		if (cacti_sizeof($parts) == 2) {
			$user_id  = $parts[0];
			$realm_id = -1;
			$token    = $parts[1];
		} else {
			$user_id  = $parts[0];
			$realm_id = $parts[1];
			$token    = $parts[2];
		}

		if (!is_numeric($user_id)) {
			$user_id = db_fetch_cell_prepared('SELECT id
				FROM user_auth
				WHERE username = ?',
				array($user_id));
		}

		if ($user_id > 0 && $user_id !== get_guest_account()) {
			if ($realm_id == -1) {
				$user_info = db_fetch_row_prepared('SELECT id, realm, username
					FROM user_auth
					WHERE id = ?',
					array($user_id));
			} else {
				$user_info = db_fetch_row_prepared('SELECT id, realm, username
					FROM user_auth
					WHERE id = ?
					AND realm = ?',
					array($user_id, $realm_id));
			}

			if (cacti_sizeof($user_info)) {
				$secret = hash('sha512', $token, false);

				$found  = db_fetch_cell_prepared('SELECT user_id
					FROM user_auth_cache
					WHERE user_id = ?
					AND token = ?',
					array($user_info['id'], $secret)
				);

				if (empty($found)) {
					return false;
				} else {
					set_auth_cookie($user_info);

					cacti_log("LOGIN: User '" . $user_info['username'] . "' Authenticated via Authentication Cookie", false, 'AUTH');

					db_execute_prepared('INSERT IGNORE INTO user_log
						(username, user_id, result, ip, time)
						VALUES
						(?, ?, 2, ?, NOW())',
						array($user_info['username'], $user_info['id'], get_client_addr())
					);

					return $user_info['id'];
				}
			}
		}
	}

	return false;
}

/**
 * is_template_account - given a username or user_id test if this is a template account
 *   Template accounts could be accounts used for the administrative email, for both
 *   the guest and template accounts, or a user that is specified by a plugin as a
 *   template account.
 *
 * @param  (int|string) user_id is either the user_id or a username
 *
 * @return (bool) true if template account, false otherwise
 */
function is_template_account($user_id) {
	if (!is_numeric($user_id)) {
		$user_id = db_fetch_cell_prepared('SELECT id FROM user_auth WHERE username = ?', array($user_id));
	}

	if (empty($user_id)) {
		return false;
	}

	if (read_config_option('admin_user') == $user_id) {
		return true;
	} elseif (read_config_option('guest_user') == $user_id) {
		return true;
	} elseif (read_config_option('user_template') == $user_id) {
		return true;
	} else {
		$domain_template = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM user_domains
			WHERE user_id = ?',
			array($user_id));

		if ($domain_template > 0) {
			return true;
		} else {
			$plugin_template = get_template_account($user_id);

			if ($plugin_template == $user_id) {
				return true;
			}
		}
	}

	return false;
}

/**
 * get_basic_auth_username - If basic auth is used, return the valid username
 *
 * @return (string) the new username, or false if one was not passed
 */
function get_basic_auth_username() {
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
		$username = false;
	}

	if ($username !== false) {
		if (strpos($username, '@') !== false) {
			$upart = explode('@', $username);
			$username = $upart[0];
		}

		/* Handle mapping basic accounts to shortform accounts.
		 * Format of map file is CSV: basic,shortform */
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
	}

	return $username;
}

/**
 * user_copy - copies user account
 *
 * @param  (string)  $template_user - username of the user account that should be used as the template
 * @param  (string)  $new_user - new username of the account to be created/overwritten
 * @param  (int)     $template_realm - new realm of the account
 * @param  (int)     $new_realm - new realm of the account to be created, overwrite not affected, but is used for lookup
 * @param  (bool)    $overwrite - Allow overwrite of existing user, preserves username, fullname, password and realm
 * @param  (array)   $data_override - Array of user_auth field and values to override on the new user
 *
 * @return (int|bool) the new users id, or false on no copy
 */
function user_copy($template_user, $new_user, $template_realm = 0, $new_realm = 0, $overwrite = false, $data_override = array()) {
	/* ================= input validation ================= */
	input_validate_input_number($template_realm, 'template_realm');
	input_validate_input_number($new_realm, 'new_realm');
	/* ==================================================== */

	/* Check get template users array */
	$user_auth = db_fetch_row_prepared('SELECT *
		FROM user_auth
		WHERE username = ?
		AND realm = ?',
		array($template_user, $template_realm));

	if (!cacti_sizeof($user_auth)) {
		return false;
	}

	$template_id = $user_auth['id'];

	/* Create update/insert for new/existing user */
	$user_exist = db_fetch_row_prepared('SELECT *
		FROM user_auth
		WHERE username = ?
		AND realm = ?',
		array($new_user, $new_realm));

	if (cacti_sizeof($user_exist)) {
		if ($overwrite) {
			/* Overwrite existing user */
			$user_auth['id']            = $user_exist['id'];
			$user_auth['username']      = $user_exist['username'];
			$user_auth['password']      = $user_exist['password'];
			$user_auth['realm']         = $user_exist['realm'];
			$user_auth['full_name']     = $user_exist['full_name'];
			$user_auth['email_address'] = $user_exist['email_address'];
 			$user_auth['must_change_password'] = $user_exist['must_change_password'];
			$user_auth['enabled']       = $user_exist['enabled'];
		} else {
			/* User already exists, duplicate users are bad */
			raise_message(19);

			return false;
		}
	} else {
		/* new user */
		$user_auth['id']            = 0;
		$user_auth['username']      = $new_user;
		$user_auth['enabled']       = 'on';
		$user_auth['password']      = mt_rand(100000, 10000000);
		$user_auth['email_address'] = '';
 		$user_auth['realm']         = $new_realm;
	}

	/* Update data_override fields */
	if (is_array($data_override)) {
		foreach ($data_override as $field => $value) {
			if (isset($user_auth[$field]) && $field != 'id' && $field != 'username') {
				$user_auth[$field] = $value;
			}
		}
	}

	/* Save the user */
	$new_id = sql_save($user_auth, 'user_auth');

	/* Create/Update permissions and settings */
	if (cacti_sizeof($user_exist) && $overwrite) {
		db_execute_prepared('DELETE FROM user_auth_perms WHERE user_id = ?', array($user_exist['id']));
		db_execute_prepared('DELETE FROM user_auth_realm WHERE user_id = ?', array($user_exist['id']));
		db_execute_prepared('DELETE FROM settings_user WHERE user_id = ?', array($user_exist['id']));
		db_execute_prepared('DELETE FROM settings_tree WHERE user_id = ?', array($user_exist['id']));
	}

	$user_auth_perms = db_fetch_assoc_prepared('SELECT *
		FROM user_auth_perms
		WHERE user_id = ?',
		array($template_id));

	if (cacti_sizeof($user_auth_perms)) {
		foreach ($user_auth_perms as $row) {
			$row['user_id'] = $new_id;
			sql_save($row, 'user_auth_perms', array('user_id', 'item_id', 'type'), false);
		}
	}

	$user_auth_realm = db_fetch_assoc_prepared('SELECT *
		FROM user_auth_realm
		WHERE user_id = ?',
		array($template_id));

	if (cacti_sizeof($user_auth_realm)) {
		foreach ($user_auth_realm as $row) {
			$row['user_id'] = $new_id;
			sql_save($row, 'user_auth_realm', array('realm_id', 'user_id'), false);
		}
	}

	$settings_user = db_fetch_assoc_prepared('SELECT *
		FROM settings_user
		WHERE user_id = ?',
		array($template_id));

	if (cacti_sizeof($settings_user)) {
		foreach ($settings_user as $row) {
			$row['user_id'] = $new_id;
			sql_save($row, 'settings_user', array('user_id', 'name'), false);
		}
	}

	$settings_tree = db_fetch_assoc_prepared('SELECT *
		FROM settings_tree
		WHERE user_id = ?',
		array($template_id));

	if (cacti_sizeof($settings_tree)) {
		foreach ($settings_tree as $row) {
			$row['user_id'] = $new_id;
			sql_save($row, 'settings_tree', array('user_id', 'graph_tree_item_id'), false);
		}
	}

	/* apply group permissions for the user */
	$groups = db_fetch_assoc_prepared('SELECT group_id
		FROM user_auth_group_members
		WHERE user_id = ?',
		array($template_id));

	if (cacti_sizeof($groups)) {
		foreach($groups as $g) {
			$sql[] = '(' . $new_id . ', ' . $g['group_id'] . ')';
		}

		db_execute('INSERT IGNORE INTO user_auth_group_members
			(user_id, group_id) VALUES ' . implode(',', $sql));
	}

	api_plugin_hook_function('copy_user', array('template_id' => $template_id, 'new_id' => $new_id));

	return $new_id;
}


/**
 * user_remove - remove a user account
 *
 * @param  (int) $user_id - Id os the user account to remove
 *
 * @return (void)
 */
function user_remove($user_id) {
	/* ================= input validation ================= */
	input_validate_input_number($user_id, 'user_id');
	/* ==================================================== */

	/* check for guest or template user */
	$username = db_fetch_cell_prepared('SELECT username
		FROM user_auth
		WHERE id = ?',
		array($user_id));

	if ($username != get_nfilter_request_var('username')) {
		if (is_template_account($user_id)) {
			raise_message(21);
			return;
		}

		if ($user_id === get_guest_account()) {
			raise_message(21);
			return;
		}
	}

	db_execute_prepared('DELETE FROM user_auth WHERE id = ?', array($user_id));
	db_execute_prepared('DELETE FROM user_auth_realm WHERE user_id = ?', array($user_id));
	db_execute_prepared('DELETE FROM user_auth_cache WHERE user_id = ?', array($user_id));
	db_execute_prepared('DELETE FROM user_auth_perms WHERE user_id = ?', array($user_id));
	db_execute_prepared('DELETE FROM user_auth_row_cache WHERE user_id = ?', array($user_id));
	db_execute_prepared('DELETE FROM user_auth_group_members WHERE user_id = ?', array($user_id));
	db_execute_prepared('DELETE FROM settings_user WHERE user_id = ?', array($user_id));
	db_execute_prepared('DELETE FROM settings_tree WHERE user_id = ?', array($user_id));
	db_execute_prepared('DELETE FROM sessions WHERE user_id = ?', array($user_id));

	api_plugin_hook_function('user_remove', $user_id);
}

/**
 * user_disable - disable a user account
 *
 * @param  (int) $user_id - Id of the user account to disable
 *
 * @return (void)
 */
function user_disable($user_id) {
	/* ================= input validation ================= */
	input_validate_input_number($user_id, 'user_id');
	/* ==================================================== */

	db_execute_prepared("UPDATE user_auth SET enabled = '' WHERE id = ?", array($user_id));

	reset_user_perms($user_id);
}

/**
 * user_enable - enable a user account
 *
 * @param  (int) $user_id - Id of the user account to enable
 *
 * @return (void)
 */
function user_enable($user_id) {
	/* ================= input validation ================= */
	input_validate_input_number($user_id, 'user_id');
	/* ==================================================== */

	db_execute_prepared("UPDATE user_auth SET enabled = 'on' WHERE id = ?", array($user_id));

	reset_user_perms($user_id);
}

/**
 * get_auth_realms - return a list of system user authentication realms
 *
 * @param  (bool) $login - If true, we also set the local login realm
 *
 * @return (array) Array of login realms
 */
function get_auth_realms($login = false) {
	if (read_config_option('auth_method') == AUTH_METHOD_DOMAIN) {
		$drealms = db_fetch_assoc('SELECT domain_id, domain_name
			FROM user_domains
			WHERE enabled="on"
			ORDER BY domain_name');

		if (cacti_sizeof($drealms)) {
			if ($login) {
				$new_realms['0'] = array(
					'name' => __('Local'),
					'selected' => false
				);

				foreach($drealms as $realm) {
					$new_realms[1000+$realm['domain_id']] = array(
						'name' => $realm['domain_name'],
						'selected' => false
					);
				}

				$default_realm = db_fetch_cell('SELECT domain_id
					FROM user_domains
					WHERE defdomain=1
					AND enabled="on"');

				if (!empty($default_realm)) {
					$new_realms[1000+$default_realm]['selected'] = true;
				} else {
					$new_realms['0']['selected'] = true;
				}
			} else {
				$new_realms['0'] = __('Local');
				foreach($drealms as $realm) {
					$new_realms[1000+$realm['domain_id']] = $realm['domain_name'];
				}
			}

			return $new_realms;
		}
	}

	// TODO: Verify this array
	return array(
		'0' => __('Local'),
		'3' => __('LDAP'),
		'2' => __('Web Basic')
	);
}

/**
 * is_graph_allowed - determines whether the current user is allowed to view a certain graph
 *
 * @param  (int) $local_graph_id - the ID of the graph to check permissions for
 *
 * @return (bool) whether the current user is allowed the view the specified graph or not
 */
function is_graph_allowed($local_graph_id, $user_id = 0) {
	$rows  = 0;

	get_allowed_graphs('', '', '', $rows, $user_id, $local_graph_id);

	return ($rows > 0);
}

/**
 * auth_check_perms - A helper function to checking Tree permissions
 *
 * @param  (array) A set of tree objects
 * @param  (int)   $policy - The policy to check
 *
 * @return (bool) true if there is access else false
 */
function auth_check_perms($objects, $policy) {
	$objectSize = cacti_sizeof($objects);

	/* policy == allow AND matches = DENY */
	if ($objectSize && $policy == 1) {
		return false;
	/* policy == deny AND matches = ALLOW */
	} elseif ($objectSize && $policy == 2) {
		return true;
	/* policy == allow AND no matches = ALLOW */
	} elseif (!$objectSize && $policy == 1) {
		return true;
	/* policy == deny AND no matches = DENY */
	} elseif (!$objectSize && $policy == 2) {
		return false;
	}
}

/**
 * auth_augment_roles - A helper function to extend Cacti roles with additional realms
 *   or to add a new role.
 *
 * @param  (string) $role_name - The role to extend or add
 * @param  (array)  $files - The filenames to add to the role
 *
 * @return (void)
 */
function auth_augment_roles($role_name, $files) {
	global $user_auth_roles, $user_auth_realm_filenames;

	foreach($files as $file) {
		if (array_search($file, $user_auth_realm_filenames, true) !== false) {
			if (array_search($user_auth_realm_filenames[$file], $user_auth_roles[$role_name], true) === false) {
				$user_auth_roles[$role_name][] = $user_auth_realm_filenames[$file];
			}
		} else {
			if (isset($_SESSION['sess_auth_names'][$role_name])) {
				$realm_id = $_SESSION['sess_auth_names'][$role_name];
			} else {
				$realm_id = db_fetch_cell_prepared('SELECT id+100 AS realm
					FROM plugin_realms
					WHERE file = ?
					OR file LIKE ?
					OR file LIKE ?
					OR file LIKE ?',
					array(
						$file,
						$file . ',%',
						'%,' . $file . ',%',
						'%,' . $file
					)
				);

				if ($realm_id > 0) {
					$_SESSION['sess_auth_names'][$role_name] = $realm_id;
				}
			}

			if (!empty($realm_id)) {
				if (!isset($user_auth_roles[$role_name])) {
					$user_auth_roles[$role_name][] = $realm_id;
				} elseif (array_search($realm_id, $user_auth_roles[$role_name]) === false) {
					$user_auth_roles[$role_name][] = $realm_id;
				}
			}
		}
	}
}

/**
 * auth_augment_roles_byname - A helper function to extend Cacti roles with additional realms
 *   or to add a new role.
 *
 * @param  (string)  $role_name - The role to extend or add
 * @param  (string)  $auth_name - The name that must be mapped
 *
 * @return (void)
 */
function auth_augment_roles_byname($role_name, $auth_name) {
	global $user_auth_roles, $user_auth_realm_filenames;

	if (isset($_SESSION['sess_auth_names'][$auth_name])) {
		$realm_id = $_SESSION['sess_auth_names'][$auth_name];
	} else {
		$realm_id = db_fetch_cell_prepared('SELECT id+100 AS realm
			FROM plugin_realms
			WHERE display = ?',
			array($auth_name));

		if ($realm_id > 0) {
			$_SESSION['sess_auth_names'][$auth_name] = $realm_id;
		}
	}

	if (!empty($realm_id)) {
		if (!isset($user_auth_roles[$role_name])) {
			$user_auth_roles[$role_name][] = $realm_id;
		} elseif (array_search($realm_id, $user_auth_roles[$role_name]) === false) {
			$user_auth_roles[$role_name][] = $realm_id;
		}
	}
}

/**
 * is_tree_allowed - determines whether the current user is allowed to view a certain graph tree
 *
 * @param  (int)  $tree_id the ID of the graph tree to check permissions for
 * @param  (int)  If checking a user, specify the user_id otherwise for the current user leave blank
 *
 * @return (bool) whether the current user is allowed the view the specified graph tree or not
 */
function is_tree_allowed($tree_id, $user_id = 0) {
	if ($user_id == -1) {
		return true;
	}

	if (isset($_SESSION['sess_tree_perms'][$tree_id])) {
		return $_SESSION['sess_tree_perms'][$tree_id];
	}

	if (read_config_option('auth_method') != AUTH_METHOD_NONE) {
		if ($user_id == 0) {
			if (isset($_SESSION['sess_user_id'])) {
				$user_id = $_SESSION['sess_user_id'];
			} else {
				$_SESSION['sess_tree_perms'][$tree_id] = false;

				return false;
			}
		}

		$policy = db_fetch_cell_prepared('SELECT policy_trees
			FROM user_auth
			WHERE id = ?',
			array($user_id));

		$trees  = db_fetch_assoc_prepared('SELECT user_id
			FROM user_auth_perms
			WHERE user_id = ?
			AND type = 2
			AND item_id = ?',
			array($user_id, $tree_id));

		if (auth_check_perms($trees, $policy)) {
			$_SESSION['sess_tree_perms'][$tree_id] = true;

			return true;
		}

		/* check for group perms */
		$groups = db_fetch_assoc_prepared("SELECT uag.policy_trees
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on'
			AND uagm.user_id = ?",
			array($user_id));

		if (!cacti_sizeof($groups)) {
			$_SESSION['sess_tree_perms'][$tree_id] = false;

			return false;
		}

		foreach ($groups as $g) {
			if (auth_check_perms($trees, $g['policy_trees'])) {
				$_SESSION['sess_tree_perms'][$tree_id] = true;

				return true;
			}
		}

		/* check for group trees */
		$gtrees = db_fetch_assoc_prepared("SELECT uagm.user_id
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			INNER JOIN user_auth_group_perms as uagp
			ON uagp.group_id = uag.id
			WHERE uag.enabled = 'on'
			AND uagm.user_id = ?
			AND uagp.item_id = ?",
			array($user_id, $tree_id));

		foreach ($groups as $g) {
			if (auth_check_perms($gtrees, $g['policy_trees'])) {
				$_SESSION['sess_tree_perms'][$tree_id] = true;
				return true;
			}
		}

		$_SESSION['sess_tree_perms'][$tree_id] = false;

		return false;
	} else {
		$_SESSION['sess_tree_perms'][$tree_id] = true;

		return true;
	}
}

/**
 * is_device_allowed - determines whether the current user is allowed to view a certain device
 *
 * @param  (int)  $device_id - the ID of the device to check permissions for
 * @param  (int)  If checking a user, specify the user_id otherwise for the current user leave blank
 *
 * @return (bool) whether the current user is allowed the view the specified device or not
 */
function is_device_allowed($device_id, $user_id = 0) {
	$total_rows = -2;
	get_allowed_devices('', '', '', $total_rows, $user_id, $device_id);
	return ($total_rows > 0);
}

/**
 * is_graph_template_allowed - determines whether the current user is allowed to view a certain graph template
 *
 * @param  (int)  $graph_template_id - The ID of the graph template to check permissions for
 * @param  (int)  If checking a user, specify the user_id otherwise for the current user leave blank
 *
 * @return (bool) whether the current user is allowed the view the specified graph template or not
 */
function is_graph_template_allowed($graph_template_id, $user = 0) {
	$total_rows = -2;
	get_allowed_graph_templates('', '', '', $total_rows, $user, $graph_template_id);

	return ($total_rows > 0);
}

/**
 * is_view_allowed - Returns a true or false as to whether or not a specific view type is allowed
 *   View options include 'show_tree', 'show_list', 'show_preview', 'graph_settings'
 *
 * @param  (string) $view - the view to check for permissions on
 *
 * @return (bool) True if allowed, else false
 */
function is_view_allowed($view = 'show_tree') {
	if (read_config_option('auth_method') != AUTH_METHOD_NONE) {
		if (!isset($_SESSION['sess_user_id'])) {
			return false;
		}

		if (db_table_exists('user_auth_group')) {
			$values = array_rekey(
				db_fetch_assoc_prepared("SELECT DISTINCT $view
					FROM user_auth_group AS uag
					INNER JOIN user_auth_group_members AS uagm
					ON uag.id = uagm.user_id
					WHERE uag.enabled = 'on'
					AND uagm.user_id = ?",
					array($_SESSION['sess_user_id'])
				), $view, $view
			);

			if (isset($values[3])) {
				return false;
			} elseif (isset($values['on'])) {
				return true;
			} elseif (isset($values[2])) {
				return true;
			}
		}
		$value = db_fetch_cell_prepared("SELECT $view
			FROM user_auth
			WHERE id = ?",
			array($_SESSION['sess_user_id'])
		);

		return ($value == 'on');
	} else {
		return true;
	}
}

/**
 * is_tree_branch_empty - Given a tree id and a branch id, check if it's empty
 *
 * @param  (int)  $tree_id - The Cacti Tree id
 * @param  (int)  $parent  - The Cacti Tree branch id
 *
 * @return (bool) True if empty, else false
 */
function is_tree_branch_empty($tree_id, $parent = 0) {
	$graphs = array_rekey(
		db_fetch_assoc_prepared('SELECT local_graph_id
			FROM graph_tree_items
			WHERE graph_tree_id = ?
			AND local_graph_id > 0
			AND parent = ?',
			array($tree_id, $parent)
		), 'local_graph_id', 'local_graph_id'
	);

	$simple_perms = get_simple_graph_perms($_SESSION['sess_user_id']);

	if (cacti_sizeof($graphs) && ($simple_perms || cacti_sizeof(get_allowed_graphs('gl.id IN(' . implode(',', $graphs) . ')'))) > 0) {
		return false;
	}

	$hosts = array_rekey(
		db_fetch_assoc_prepared('SELECT host_id
			FROM graph_tree_items
			WHERE graph_tree_id = ?
			AND host_id > 0
			AND parent = ?',
			array($tree_id, $parent)
		), 'host_id', 'host_id'
	);

	$sites = array_rekey(
		db_fetch_assoc_prepared('SELECT site_id
			FROM graph_tree_items
			WHERE graph_tree_id = ?
			AND site_id > 0
			AND parent = ?',
			array($tree_id, $parent)
		), 'site_id', 'site_id'
	);

	if (!cacti_sizeof($sites)) {
		if (cacti_sizeof($hosts) && cacti_sizeof(get_allowed_devices('h.id IN(' . implode(',', $hosts) . ')'), 'description', '', -1) > 0) {
			return false;
		}
	} else {
		$site_hosts = array();
		foreach($sites as $site) {
			$site_hosts += array_rekey(
				db_fetch_assoc_prepared('SELECT id
					FROM host
					WHERE site_id = ?',
					array($site)
				), 'id', 'id'
			);
		}

		if (cacti_sizeof($site_hosts) && cacti_sizeof(get_allowed_devices('h.id IN(' . implode(',', $site_hosts) . ')'), 'description', '', -1) > 0) {
			return false;
		}
	}

	$branches = db_fetch_assoc_prepared('SELECT id, graph_tree_id
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND parent = ?
		AND local_graph_id = 0
		AND host_id = 0',
		array($tree_id, $parent));

	if (cacti_sizeof($branches)) {
		foreach($branches as $b) {
			if (!is_tree_branch_empty($b['graph_tree_id'], $b['id'])) {
				return false;
			}
		}
	}

	return true;
}

/**
 * is_realm_allowed - Given a realm and a user, check their permissions if the
 *   admin changed a users settings, setup the case to redirect by clearing
 *   session variables so that when an admin makes a change, the user does not
 *   have to login again to receive them.
 *
 * @param  (int)      $realm      The realm to check
 * @param  (int|bool) $check_user The either false or the user id to check
 *
 * @return (bool) True if allowed, otherwise false
 */
function is_realm_allowed($realm, $check_user = false) {
	global $config;

	/* list all realms that this user has access to */
	if (read_config_option('auth_method') != AUTH_METHOD_NONE) {
		/* if we are only checking another users permission, don't check cache */
		if ($check_user == false) {
			/* user is not set, no permissions */
			if (!isset($_SESSION['sess_user_id'])) {
				return false;
			}

			/* check to see if the admin invalidated a permission */
			if (!is_user_perms_valid($_SESSION['sess_user_id'])) {
				if (db_table_exists('user_auth_cache')) {
					$enabled = db_fetch_cell_prepared('SELECT enabled
						FROM user_auth
						WHERE id = ?',
						array($_SESSION['sess_user_id']));

					if ($enabled == '' && get_guest_account() !== $_SESSION['sess_user_id']) {
						db_execute_prepared('DELETE FROM user_auth_cache
							WHERE user_id = ?',
							array($_SESSION['sess_user_id']));

						kill_session_var('sess_user_id');
						kill_session_var('sess_user_realms');
						kill_session_var('sess_user_config_array');
						kill_session_var('sess_config_array');
						kill_session_var('sess_auth_names');
						kill_session_var('sess_tree_perms');
						kill_session_var('sess_simple_perms');
						kill_session_var('sess_simple_template_perms');

						print '<span style="display:none;">cactiLoginSuspend</span>';
						exit;
					} else {
						kill_session_var('sess_user_realms');
						kill_session_var('sess_user_config_array');
						kill_session_var('sess_config_array');
						kill_session_var('sess_auth_names');
						kill_session_var('sess_tree_perms');
						kill_session_var('sess_simple_perms');
						kill_session_var('sess_simple_template_perms');
					}

					print '<span style="display:none;">cactiRedirect</span>';
					exit;
				} else {
					kill_session_var('sess_user_realms');
					kill_session_var('sess_user_config_array');
					kill_session_var('sess_config_array');
					kill_session_var('sess_auth_names');
					kill_session_var('sess_tree_perms');
					kill_session_var('sess_simple_perms');
					kill_session_var('sess_simple_template_perms');
				}
			}

			/* if the permission is already valid, the session variable will be set */
			if (isset($_SESSION['sess_user_realms'][$realm])) {
				return $_SESSION['sess_user_realms'][$realm];
			}

			$user_id = $_SESSION['sess_user_id'];
		} else {
			$user_id = $check_user;
		}

		/**
		 * check the permissions from the table, should only happen once per login
		 * of after a permission change by the administrator.
		 */
		if (read_config_option('auth_method') != AUTH_METHOD_NONE) {
			if (cacti_version_compare($config['cacti_db_version'], '1.0.0', '>=')) {
				$user_realm = db_fetch_cell_prepared("SELECT realm_id
					FROM user_auth_realm
					WHERE user_id = ?
					AND realm_id = ?
					UNION
					SELECT realm_id
					FROM user_auth_group_realm AS uagr
					INNER JOIN user_auth_group AS uag
					ON uag.id = uagr.group_id
					INNER JOIN user_auth_group_members AS uagm
					ON uag.id = uagm.group_id
					WHERE uag.enabled = 'on'
					AND uagr.realm_id = ?
					AND uagm.user_id = ?",
					array($user_id, $realm, $realm, $user_id));
			} else {
				$user_realm = db_fetch_cell_prepared('SELECT realm_id
					FROM user_auth_realm
					WHERE user_id = ?
					AND realm_id = ?',
					array($user_id, $realm));
			}

			if (!empty($user_realm)) {
				if ($check_user == false) {
					$_SESSION['sess_user_realms'][$realm] = true;
				} else {
					return true;
				}
			} else {
				if ($check_user == false) {
					$_SESSION['sess_user_realms'][$realm] = false;
				} else {
					return false;
				}
			}
		} else {
			if ($check_user == false) {
				$_SESSION['sess_user_realms'][$realm] = true;
			} else {
				return true;
			}
		}
	} else {
		if ($check_user == false) {
			$_SESSION['sess_user_realms'][$realm] = true;
		} else {
			return true;
		}
	}

	return $_SESSION['sess_user_realms'][$realm];
}

/**
 * get_allowed_tree_level - Get the permitted tree branch data available to the user
 *
 * @param  (int)  The tree id to check
 * @param  (int)  The branch id, 0 is the root of the tree
 * @param  (bool) Tells the function that the user is editing
 * @param  (int)  The user id to check for permissions. if 0 then check the current user
 *
 * @return (array) An array of Tree branch items that a re allowed (graphs, devices)
 */
function get_allowed_tree_level($tree_id, $parent_id, $editing = false, $user_id = 0) {
	$items = db_fetch_assoc_prepared('SELECT gti.id, gti.title, gti.host_id,
		gti.site_id, gti.local_graph_id, gti.host_grouping_type,
		h.description AS hostname, s.name AS sitename
		FROM graph_tree_items AS gti
		INNER JOIN graph_tree AS gt
		ON gt.id = gti.graph_tree_id
		LEFT JOIN host AS h
		ON h.id = gti.host_id
		LEFT JOIN sites AS s
		ON s.id = gti.site_id
		WHERE gti.graph_tree_id = ?
		AND gti.parent = ?
		ORDER BY gti.position ASC',
		array($tree_id, $parent_id));

	if (!$editing) {
		$i = 0;
		if (cacti_sizeof($items)) {
			foreach($items as $item) {
				if ($item['host_id'] > 0) {
					if (!is_device_allowed($item['host_id'], $user_id)) {
						unset($items[$i]);
					}
				} elseif($item['local_graph_id'] > 0) {
					if (!is_graph_allowed($item['local_graph_id'], $user_id)) {
						unset($items[$i]);
					}
				}

				$i++;
			}
		}
	}

	return $items;
}

/**
 * get_allowed_tree_content - A function that gathers items and statistics of those items
 *   that are permitted on the tree specified
 *
 * @param  (int)    The tree id to check
 * @param  (int)    The branch id, 0 is the root of the tree
 * @param  (string) The Tree SQL where when searching for specific content
 * @param  (string) The SQL Order clause to use for the sorting of items
 * @param  (int)    The limit on items to return.  If empty or -1, return all items
 * @param  (int)    The number of rows found, to be returned to the caller
 * @param  (int)    If checking a user, specify the user_id otherwise for the current user leave blank
 *
 * @return (array) An array of Tree branch items that a re allowed (graphs, devices)
 */
function get_allowed_tree_content($tree_id, $parent = 0, $sql_where = '', $sql_order = '', $sql_limit = '', &$total_rows = 0, $user_id = 0) {
	if ($sql_limit != '' && $sql_limit != -1) {
		$sql_limit = "LIMIT $sql_limit";
	} else {
		$sql_limit = '';
	}

	if (!is_numeric($tree_id)) {
		return array();
	}

	if (!is_numeric($parent)) {
		return array();
	}

	if ($sql_order != '') {
		$sql_order = "ORDER BY $sql_order";
	}

	if ($sql_where != '') {
		$sql_where = "WHERE gti.local_graph_id=0
			AND gti.parent=$parent
			AND gti.graph_tree_id=$tree_id
			AND (" . $sql_where . ')';
	} else {
		$sql_where = "WHERE gti.local_graph_id=0
			AND gti.parent=$parent
			AND gti.graph_tree_id=$tree_id";
	}

	$trees = array_rekey(
		get_allowed_trees(false, false, '', '', '', $total_rows, $user_id),
		'id', 'name'
	);

	if ($tree_id > 0) {
		if (cacti_sizeof($trees)) {
			$sql_where .= ' AND gt.id IN (' . implode(', ', array_keys($trees)) . ')';
		}

		$hierarchy = db_fetch_assoc("SELECT gti.graph_tree_id AS tree_id, gti.id, gti.title, gti.host_id, gti.site_id,
			gti.local_graph_id, gti.host_grouping_type, h.description AS hostname, s.name AS sitename
			FROM graph_tree_items AS gti
			INNER JOIN graph_tree AS gt
			ON gt.id = gti.graph_tree_id
			LEFT JOIN host AS h
			ON h.id = gti.host_id
			LEFT JOIN sites AS s
			ON gti.site_id=s.id
			$sql_where
			ORDER BY gti.position");
	} elseif (cacti_sizeof($trees)) {
		$hierarchy = db_fetch_assoc("SELECT gt.id AS tree_id, '0' AS id, gt.name AS title, '0' AS host_id, '0' AS site_id,
			'0' AS local_graph_id, '1' AS host_grouping_type, '' AS hostname, '' AS sitename
			FROM graph_tree AS gt
			WHERE enabled='on'
			AND gt.id IN (" . implode(', ', array_keys($trees)) . ")
			ORDER BY gt.sequence");
	}

	if (read_config_option('auth_method') != AUTH_METHOD_NONE) {
		$new_hierarchy = array();
		if (cacti_sizeof($hierarchy)) {
			foreach($hierarchy as $h) {
				if ($h['host_id'] > 0) {
					if (is_device_allowed($h['host_id'])) {
						$new_hierarchy[] = $h;
					}
				} elseif ($h['id'] == 0) {
					if (!is_tree_branch_empty($h['tree_id'], $h['id'])) {
						if (is_tree_allowed($h['tree_id'])) {
							$new_hierarchy[] = $h;
						}
					}
				} elseif ($h['site_id'] > 0) {
					$new_hierarchy[] = $h;
				} elseif (!is_tree_branch_empty($h['tree_id'], $h['id'])) {
					$new_hierarchy[] = $h;
				}
			}
		}

		return $new_hierarchy;
	} else {
		return $hierarchy;
	}
}

/**
 * get_policies - Searches both the users and the users groups and returns a policy
 *   array to be used for determining object permissions.
 *
 * @param  (int)  If checking a user, specify the user_id otherwise for the current user leave blank
 *
 * @return (array) An array of policy objects comprising the users permissions
 */
function get_policies($user_id) {
	/* get policies for all user groups */
	$policies = db_fetch_assoc_prepared("SELECT uag.id, 'group' AS type, uag.name,
		uag.policy_graphs, uag.policy_hosts, uag.policy_graph_templates, uag.policy_trees
		FROM user_auth_group AS uag
		INNER JOIN user_auth_group_members AS uagm
		ON uag.id = uagm.group_id
		WHERE uag.enabled = 'on'
		AND uagm.user_id = ?",
		array($user_id));

	/* get policies for the user */
	$policies[] = db_fetch_row_prepared("SELECT id, 'user' AS type, 'user' AS name,
		policy_graphs, policy_hosts, policy_graph_templates, policy_trees
		FROM user_auth
		WHERE id = ?",
		array($user_id));

	return $policies;
}

/**
 * get_allowed_tree_header_graphs - Returns the graphs that are permitted at the branch/leaf id specified
 *
 * @param  (int)    The tree id to check
 * @param  (int)    The branch id, 0 is the root of the tree
 * @param  (string) The Tree SQL where when searching for specific content
 * @param  (string) The SQL Order clause to use for the sorting of items
 * @param  (int)    The limit on items to return.  If empty or -1, return all items
 * @param  (int)    The number of rows found, to be returned to the caller
 * @param  (int)    If checking a user, specify the user_id otherwise for the current user leave blank
 *
 * @return (array) Array of tree header graphs to display
 */
function get_allowed_tree_header_graphs($tree_id, $leaf_id = 0, $sql_where = '', $sql_order = 'gti.position', $sql_limit = '', &$total_rows = 0, $user_id = 0) {
	if (!is_numeric($tree_id)) {
		return array();
	}

	if (!is_numeric($leaf_id)) {
		return array();
	}

	if ($sql_limit != '' && $sql_limit != -1) {
		$sql_limit = "LIMIT $sql_limit";
	} else {
		$sql_limit = '';
	}

	if ($sql_order != '') {
		$sql_order = "ORDER BY $sql_order";
	}

	if ($sql_where != '') {
		$sql_where = " AND ($sql_where)";
	}

	$sql_where = "WHERE (gti.graph_tree_id=$tree_id AND gti.parent=$leaf_id)" . $sql_where;

	if (read_user_setting('hide_disabled') == 'on') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . '(h.disabled = "" OR h.disabled IS NULL)';
	}

	if ($user_id == -1) {
		$auth_method = AUTH_METHOD_NONE;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method > AUTH_METHOD_NONE && $user_id == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$user_id = $_SESSION['sess_user_id'];
		} else {
			return array();
		}
	}

	$graph_auth_method = read_config_option('graph_auth_method');

	/* get policies for all groups and user */
	$policies = get_policies($user_id);

	if ($auth_method != AUTH_METHOD_NONE) {
		$sql_where = get_policy_where($graph_auth_method, $policies, $sql_where);
	}

	$graphs = db_fetch_assoc("SELECT gti.id, gti.title, gtg.local_graph_id, h.description,
		gt.name AS template_name, gtg.title_cache, gtg.width, gtg.height,
		gl.snmp_index, gl.snmp_query_id
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gl.id = gtg.local_graph_id
		INNER JOIN graph_tree_items AS gti
		ON gti.local_graph_id = gl.id
		LEFT JOIN graph_templates AS gt
		ON gt.id = gl.graph_template_id
		LEFT JOIN host AS h
		ON h.id = gl.host_id
		$sql_where
		$sql_order
		$sql_limit");

	$sql = "SELECT COUNT(*)
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gl.id=gtg.local_graph_id
		INNER JOIN graph_tree_items AS gti
		ON gti.local_graph_id=gl.id
		LEFT JOIN graph_templates AS gt
		ON gt.id=gl.graph_template_id
		LEFT JOIN host AS h
		ON h.id=gl.host_id
		$sql_where";

	$total_rows = get_total_row_data($user_id, $sql, array(), 'graph');

	return $graphs;
}

/**
 * get_allowed_graphs - Returns the graphs that are permitted by the user.  Used for table displays
 *   where users will view the graphs that they are permitted to access
 *
 * @param  (string) The SQL where when searching for specific content
 * @param  (string) The SQL Order clause to use for the sorting of graphs
 * @param  (int)    The limit on items to return.  If empty or -1, return all items
 * @param  (int)    The number of rows found, to be returned to the caller
 * @param  (int)    If checking a user, specify the user_id otherwise for the current user leave blank
 * @param  (int)    If just searching for if a single graph is permitted, the id of that graph
 *
 * @return (array) Array of allowed graphs
 */
function get_allowed_graphs($sql_where = '', $sql_order = 'gtg.title_cache', $sql_limit = '', &$total_rows = 0, $user_id = 0, $graph_id = 0) {
	if ($sql_limit != '') {
		$sql_limit = "LIMIT $sql_limit";
	} else {
		$sql_limit = '';
	}

	if ($sql_order != '') {
		$sql_order = "ORDER BY $sql_order";
	}

	if ($graph_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' ') . " gl.id = $graph_id";
	}

	if (read_user_setting('hide_disabled') == 'on') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . '(h.disabled = "" OR h.disabled IS NULL)';
	}

	if ($sql_where != '') {
		$sql_where = "WHERE ((h.id > 0 AND h.deleted = '') OR h.id IS NULL) AND $sql_where";
	} else {
		$sql_where = "WHERE ((h.id > 0 AND h.deleted = '') OR h.id IS NULL)";
	}

	if ($user_id == -1) {
		$auth_method = AUTH_METHOD_NONE;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method > AUTH_METHOD_NONE && $user_id == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$user_id = $_SESSION['sess_user_id'];
		} else {
			return array();
		}
	}

	/* see if permissions are simple */
	$simple_perms = get_simple_graph_perms($user_id);

	/* in case we need to review get the graph_auth_method */
	$graph_auth_method = read_config_option('graph_auth_method');

	/* get policies for all groups and user */
	$policies = get_policies($user_id);

	if (!$simple_perms && $auth_method != AUTH_METHOD_NONE) {
		$sql_where = get_policy_where($graph_auth_method, $policies, $sql_where);
	}

	$graphs = db_fetch_assoc("SELECT gtg.local_graph_id, h.description, gt.name AS template_name,
		gtg.title_cache, gtg.width, gtg.height, gl.snmp_index, gl.snmp_query_id,
		IF(gl.graph_template_id=0, 0, IF(gl.snmp_query_id=0, 2, 1)) AS graph_source
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gl.id=gtg.local_graph_id
		LEFT JOIN graph_templates AS gt
		ON gt.id=gl.graph_template_id
		LEFT JOIN host AS h
		ON h.id=gl.host_id
		$sql_where
		$sql_order
		$sql_limit");

	$sql = "SELECT COUNT(*)
		FROM graph_templates_graph AS gtg
		INNER JOIN graph_local AS gl
		ON gl.id=gtg.local_graph_id
		LEFT JOIN graph_templates AS gt
		ON gt.id=gl.graph_template_id
		LEFT JOIN host AS h
		ON h.id=gl.host_id
		$sql_where";

	if ($graph_id == 0) {
		$total_rows = get_total_row_data($user_id, $sql, array(), 'graph');
	} else {
		$total_rows = db_fetch_cell($sql);
	}

	return $graphs;
}

/**
 * get_allowed_aggregate_graphs - Returns the aggregate graphs that are permitted by the user.
 *   Used for table displays where users will view the graphs that they are permitted to access
 *
 * @param  (string) The SQL where when searching for specific content
 * @param  (string) The SQL Order clause to use for the sorting of graphs
 * @param  (int)    The limit on items to return.  If empty or -1, return all items
 * @param  (int)    The number of rows found, to be returned to the caller
 * @param  (int)    If checking a user, specify the user_id otherwise for the current user leave blank
 * @param  (int)    If just searching for if a single graph is permitted, the id of that graph
 *
 * @return (array) Array of allowed graphs
 */
function get_allowed_aggregate_graphs($sql_where = '', $sql_order = 'gtg.title_cache', $sql_limit = '', &$total_rows = 0, $user_id = 0, $graph_id = 0) {
	if ($sql_limit != '' && $sql_limit != -1) {
		$sql_limit = "LIMIT $sql_limit";
	} else {
		$sql_limit = '';
	}

	if ($sql_order != '') {
		$sql_order = "ORDER BY $sql_order";
	}

	if ($graph_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' ') . " gl.id = $graph_id";
	}

	if (read_user_setting('hide_disabled') == 'on') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . '(h.disabled = "" OR h.disabled IS NULL)';
	}

	if ($sql_where != '') {
		$sql_where = "WHERE ((h.id > 0 AND h.deleted = '') OR h.id IS NULL) AND $sql_where";
	} else {
		$sql_where = "WHERE ((h.id > 0 AND h.deleted = '') OR h.id IS NULL)";
	}

	if ($user_id == -1) {
		$auth_method = AUTH_METHOD_NONE;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method > AUTH_METHOD_NONE && $user_id == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$user_id = $_SESSION['sess_user_id'];
		} else {
			return array();
		}
	}

	/* see if permissions are simple */
	$simple_perms = get_simple_graph_perms($user_id);

	/* in case we need to review get the graph_auth_method */
	$graph_auth_method = read_config_option('graph_auth_method');

	/* get policies for all groups and user */
	$policies = get_policies($user_id);

	if (!$simple_perms && $auth_method != AUTH_METHOD_NONE) {
		$sql_where = get_policy_where($graph_auth_method, $policies, $sql_where);
	}

	$graphs = db_fetch_assoc("SELECT gtg.local_graph_id, '' AS description, gt.name AS template_name,
		gtg.title_cache, gtg.width, gtg.height, gl.snmp_index, gl.snmp_query_id
		FROM graph_templates_graph AS gtg
		INNER JOIN (
			SELECT ag.local_graph_id AS id, gl.host_id, gl.graph_template_id,
			gl.snmp_query_id, gl.snmp_query_graph_id, gl.snmp_index
			FROM aggregate_graphs AS ag
			INNER JOIN aggregate_graphs_items AS agi
			ON ag.id=agi.aggregate_graph_id
			INNER JOIN graph_local AS gl
			ON gl.id=agi.local_graph_id
		) AS gl
		ON gl.id=gtg.local_graph_id
		LEFT JOIN graph_templates AS gt
		ON gt.id=gl.graph_template_id
		LEFT JOIN host AS h
		ON h.id=gl.host_id
		$sql_where
		GROUP BY gtg.local_graph_id
		$sql_order
		$sql_limit");

	$sql = "SELECT COUNT(DISTINCT gl.id)
		FROM graph_templates_graph AS gtg
		INNER JOIN (
			SELECT ag.local_graph_id AS id, gl.host_id, gl.graph_template_id,
			gl.snmp_query_id, gl.snmp_query_graph_id, gl.snmp_index
			FROM aggregate_graphs AS ag
			INNER JOIN aggregate_graphs_items AS agi
			ON ag.id=agi.aggregate_graph_id
			INNER JOIN graph_local AS gl
			ON gl.id=agi.local_graph_id
		) AS gl
		ON gl.id=gtg.local_graph_id
		LEFT JOIN graph_templates AS gt
		ON gt.id=gl.graph_template_id
		LEFT JOIN host AS h
		ON h.id=gl.host_id
		$sql_where";

	$total_rows = get_total_row_data($user_id, $sql, array(), 'aggregate_graph');

	return $graphs;
}

/**
 * get_simple_device_perms - Returns a boolean true or false if the user has full access to
 *   all devices in the system.  This function is used to shortcut complex queries that may
 *   take multiple seconds to return an answer.
 *
 * @param  (int)    The user id to check for permissions for
 *
 * @return (bool)   True if simple permissions are in place, otherwise false
 */
function get_simple_device_perms($user) {
	$policy_hosts = db_fetch_cell_prepared('SELECT policy_hosts
		FROM user_auth
		WHERE id = ?',
		array($user));

	$perm_count = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM user_auth_perms
		WHERE user_id = ?
		AND type = 2',
		array($user));

	if ($policy_hosts == 1 && $perm_count == 0) {
		return true;
	} else {
		$policies = db_fetch_assoc_prepared('SELECT policy_hosts, COUNT(*) AS exceptions
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_perms AS uagp
			ON uag.id = uagp.group_id
			INNER JOIN user_auth_group_members AS uagm
			ON uagm.group_id = uag.id
			WHERE uagp.type = 2
			AND uagm.user_id = ?
			GROUP BY uag.id',
			array($user));

		if (cacti_sizeof($policies)) {
			foreach($policies as $p) {
				if ($p['policy_hosts'] == 1 && $p['exceptions'] == 0) {
					return true;
				}
			}
		}

		return false;
	}
}

/**
 * get_simple_graph_perms - Returns a boolean true or false if the user has full access to
 *   all graphs in the system.  This function is used to shortcut complex queries that may
 *   take multiple seconds to return an answer.
 *
 * @param  (int)    The user id to check for permissions for
 *
 * @return (bool)   True if simple permissions are in place, otherwise false
 */
function get_simple_graph_perms($user_id) {
	if (isset($_SESSION['sess_simple_perms'])) {
		return $_SESSION['sess_simple_perms'];
	}

	$policy_graphs = db_fetch_cell_prepared('SELECT policy_graphs
		FROM user_auth
		WHERE id = ?',
		array($user_id));

	$perm_count = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM user_auth_perms
		WHERE user_id = ?
		AND type = 1',
		array($user_id));

	if ($policy_graphs == 1 && $perm_count == 0) {
		$_SESSION['sess_simple_perms'] = true;

		return true;
	} else {
		$policies = db_fetch_assoc_prepared('SELECT policy_graphs, COUNT(*) AS exceptions
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_perms AS uagp
			ON uag.id = uagp.group_id
			INNER JOIN user_auth_group_members AS uagm
			ON uagm.group_id = uag.id
			WHERE uagp.type = 1
			AND uagm.user_id = ?
			GROUP BY uag.id',
			array($user_id));

		if (cacti_sizeof($policies)) {
			foreach($policies as $p) {
				if ($p['policy_graphs'] == 1 && $p['exceptions'] == 0) {
					$_SESSION['sess_simple_perms'] = true;

					return true;
				}
			}
		}

		$_SESSION['sess_simple_perms'] = false;

		return false;
	}
}

/**
 * get_simple_graph_template_perms - Returns a boolean true or false if the user has full access to
 *   all graphs templates in the system.  This function is used to shortcut complex queries that may
 *   take multiple seconds to return an answer.
 *
 * @param  (int)    The user id to check for permissions for
 *
 * @return (bool)   True if simple permissions are in place, otherwise false
 */
function get_simple_graph_template_perms($user_id) {
	if (isset($_SESSION['sess_simple_template_perms'])) {
		return $_SESSION['sess_simple_template_perms'];
	}

	$policy_graph_templates = db_fetch_cell_prepared('SELECT policy_graph_templates
		FROM user_auth
		WHERE id = ?',
		array($user_id));

	$perm_count = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM user_auth_perms
		WHERE user_id = ?
		AND type = 4',
		array($user_id));

	if ($policy_graph_templates == 1 && $perm_count == 0) {
		$_SESSION['sess_simple_template_perms'] = true;

		return true;
	} else {
		$policies = db_fetch_assoc_prepared('SELECT policy_graph_templates, COUNT(*) AS exceptions
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_perms AS uagp
			ON uag.id = uagp.group_id
			INNER JOIN user_auth_group_members AS uagm
			ON uagm.group_id = uag.id
			WHERE uagp.type = 4
			AND uagm.user_id = ?
			GROUP BY uag.id',
			array($user_id));

		if (cacti_sizeof($policies)) {
			foreach($policies as $p) {
				if ($p['policy_graph_templates'] == 1 && $p['exceptions'] == 0) {
					$_SESSION['sess_simple_template_perms'] = true;

					return true;
				}
			}
		}

		$_SESSION['sess_simple_template_perms'] = false;

		return false;
	}
}

/**
 * get_allowed_graph_templates - returns the list of Graph Templates that the user is allowed
 *   To access.  This function is generally intended for both listbox and table displays.
 *
 * @param  (string) The SQL where when searching for specific content
 * @param  (string) The SQL Order clause to use for the sorting of graphs
 * @param  (int)    The limit on items to return.  If empty or -1, return all items
 * @param  (int)    The number of rows found, to be returned to the caller
 * @param  (int)    If checking a user, specify the user_id otherwise for the current user leave blank
 * @param  (int)    If just searching for if a single graph template is permitted, the id of that graph template
 *
 * @return (array)  An array of permitted Graph Templates
 */
function get_allowed_graph_templates($sql_where = '', $sql_order = 'gt.name', $sql_limit = '', &$total_rows = 0, $user_id = 0, $graph_template_id = 0) {
	if ($user_id == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$user_id = $_SESSION['sess_user_id'];
		} else {
			return array();
		}
	}

	// For timing
	$start = microtime(true);

	$init_rows = $total_rows;

	$templates = array();

	if ($sql_limit != '' && $sql_limit != -1) {
		$sql_limit = "LIMIT $sql_limit";
	} else {
		$sql_limit = '';
	}

	if ($sql_order != '') {
		$sql_order = "ORDER BY $sql_order";
	}

	if ($graph_template_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' ') . "(gl.graph_template_id = $graph_template_id)";
	}

	$sql_where = 'WHERE ' . ($sql_where != '' ? $sql_where . ' AND ':' ') . '(gt.id IS NOT NULL) ';

	if ($user_id == -1) {
		$auth_method = AUTH_METHOD_NONE;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method > AUTH_METHOD_NONE && $user_id == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$user_id = $_SESSION['sess_user_id'];
		} else {
			return array();
		}
	}

	/* see if permissions are simple */
	$simple_perms = get_simple_graph_perms($user_id);

	/* in case we need to review get the graph_auth_method */
	$graph_auth_method = read_config_option('graph_auth_method');

	/* get policies for all groups and user */
	$policies = get_policies($user_id);

	/* short circuit if we don't have a user */
	if ($auth_method > AUTH_METHOD_NONE && $user_id == 0) {
		return array();
	}

	if (!$simple_perms && $auth_method != AUTH_METHOD_NONE) {
		$sql_where = get_policy_where($graph_auth_method, $policies, $sql_where);
	}

	if ($total_rows != -2) {
		$templates = db_fetch_assoc("SELECT gt.id, gt.name, COUNT(*) AS graphs
			FROM graph_local AS gl
			LEFT JOIN graph_templates AS gt
			ON gt.id = gl.graph_template_id
			LEFT JOIN host AS h
			ON h.id = gl.host_id
			$sql_where
			GROUP BY gl.graph_template_id
			$sql_order
			$sql_limit");
	}

	if ($total_rows >= 0 || $total_rows == -2) {
		$sql = "SELECT COUNT(DISTINCT gl.graph_template_id) AS id
			FROM graph_local AS gl
			LEFT JOIN graph_templates AS gt
			ON gt.id = gl.graph_template_id
			LEFT JOIN host AS h
			ON h.id = gl.host_id
			$sql_where";

		if ($graph_template_id == 0) {
			$total_rows = get_total_row_data($user_id, $sql, array(), 'graph');
		} else {
			$total_rows = db_fetch_cell($sql);
		}
	}

	// For timing
	$end = microtime(true);

	cacti_log(sprintf('The Get Templates total time was %4.2f', $end - $start), false, 'AUTH', POLLER_VERBOSITY_DEBUG);

	if ($templates === false) {
		$templates = array();
	}

	return $templates;
}

/**
 * get_policy_join_select - Parse the policies in order to visually display user permissions
 *
 * @param  (array) $policies  The list of user and group policies.  Will be reversed to
 *   show user permissions first.
 *
 * @return (array)  Array containing both $sql_select and $sql_join
 */
function get_policy_join_select($policies) {
	$sql_join   = '';
	$sql_select = '';

	$i = 1;
	$j = 1;
	foreach($policies as $p) {
		$sql_join   .= "LEFT JOIN (SELECT * FROM user_auth_" . ($p['type'] == 'user' ? '' : 'group_') . "perms WHERE " . $p['type'] . '_id = ' . $p['id'] . ") AS uap$j ON (gl.id = uap$j.item_id AND uap$j.type = 1) ";
		$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$j." . $p['type'] . "_id AS graph$i";
		$j++;

		$sql_join   .= 'LEFT JOIN (SELECT * FROM user_auth_' . ($p['type'] == 'user' ? '' : 'group_') . "perms WHERE " . $p['type'] . '_id = ' . $p['id'] . ") AS uap$j ON (gl.host_id = uap$j.item_id AND uap$j.type = 3) ";
		$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$j." . $p['type'] . "_id AS device$i";
		$j++;

		$sql_join   .= 'LEFT JOIN (SELECT * FROM user_auth_' . ($p['type'] == 'user' ? '' : 'group_') . "perms WHERE " . $p['type'] . '_id = ' . $p['id'] . ") AS uap$j ON (gl.graph_template_id = uap$j.item_id AND uap$j.type = 4) ";
		$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$j." . $p['type'] . "_id AS template$i";
		$j++;

		$i++;
	}

	return array('sql_select' => $sql_select, 'sql_join' => $sql_join);
}

/**
 * get_policy_where - Parse the policies in order to downselect matching graphs
 *   without the use of the SQL Having clause which is very inefficient
 *
 * @param  (int)    $graph_auth_method - The graph auth method: permissive, restrictive, device, graph_template
 * @param  (array)  $policies - The list of user and group policies.  Will be reversed to
 * @param  (string) $sql_where - The SQL where filter provided by the calling function.
 *
 * @return (string) - Updated sql_where value
 */
function get_policy_where($graph_auth_method, $policies, $sql_where) {
	if ($graph_auth_method == 1) {
		// Policy Rows include
		// id, type (group|user), policy_graphs, policy_hosts, policy_graph_templates

		// Table user_auth_perms has user_id, item_id, type
		// Types can be:
		// 1 - Graph
		// 2 - Trees
		// 3 - Device
		// 4 - Graph Template

		// Policies are
		// 1 - Default Allow, all exceptions are denied
		// 2 - Default Deny, all exceptions are allowed

		// Permissive means access to the graph, or access to either the host or template

		$sql_where .= ($sql_where != '' ? ' AND (':'WHERE (');

		foreach($policies as $index => $p) {
			$sql_where .= ($index > 0 ? ' OR (':'(');

			if ($p['type'] == 'user') {
				if ($p['policy_graphs'] == 1) {
					$sql_where .= 'gl.id NOT IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 1)';
				} else {
					$sql_where .= 'gl.id IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 1)';
				}

				$sql_where .= ' OR ';

				if ($p['policy_hosts'] == 1) {
					$sql_where .= 'h.id NOT IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 3)';
				} else {
					$sql_where .= 'h.id IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 3)';
				}

				$sql_where .= ' OR ';

				if ($p['policy_graph_templates'] == 1) {
					$sql_where .= 'gl.graph_template_id NOT IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 4)';
				} else {
					$sql_where .= 'gl.graph_template_id IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 4)';
				}
			} else {
				if ($p['policy_graphs'] == 1) {
					$sql_where .= 'gl.id NOT IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 1)';
				} else {
					$sql_where .= 'gl.id IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 1)';
				}

				$sql_where .= ' OR ';

				if ($p['policy_hosts'] == 1) {
					$sql_where .= 'h.id NOT IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 3)';
				} else {
					$sql_where .= 'h.id IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 3)';
				}

				$sql_where .= ' OR ';

				if ($p['policy_graph_templates'] == 1) {
					$sql_where .= 'gl.graph_template_id NOT IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 4)';
				} else {
					$sql_where .= 'gl.graph_template_id IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 4)';
				}
			}

			$sql_where .= ')';
		}

		$sql_where .= ')';
	} elseif ($graph_auth_method == 2) {
		// Policy Rows include
		// id, type (group|user), policy_graphs, policy_hosts, policy_graph_templates

		// Table user_auth_perms has user_id, item_id, type
		// Types can be:
		// 1 - Graph
		// 2 - Trees
		// 3 - Device
		// 4 - Graph Template

		// Policies are
		// 1 - Default Allow, all exceptions are denied
		// 2 - Default Deny, all exceptions are allowed

		// Restrictive means access to the graph, or access to both the host and template

		$sql_where .= ($sql_where != '' ? ' AND (':'WHERE (');

		foreach($policies as $index => $p) {
			$sql_where .= ($index == 0 ? '((':' OR ((');

			if ($p['type'] == 'user') {
				if ($p['policy_graphs'] == 1) {
					$sql_where .= 'gl.id NOT IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 1)';
				} else {
					$sql_where .= 'gl.id IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 1)';
				}

				$sql_where .= ') OR (';

				if ($p['policy_hosts'] == 1) {
					$sql_where .= 'h.id NOT IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 3)';
				} else {
					$sql_where .= 'h.id IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 3)';
				}

				$sql_where .= ' AND ';

				if ($p['policy_graph_templates'] == 1) {
					$sql_where .= ' gl.graph_template_id NOT IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 4)';
				} else {
					$sql_where .= ' gl.graph_template_id IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 4)';
				}

				$sql_where .= ')';
			} else {
				if ($p['policy_graphs'] == 1) {
					$sql_where .= 'gl.id NOT IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 1)';
				} else {
					$sql_where .= 'gl.id IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 1)';
				}

				$sql_where .= ') OR (';

				if ($p['policy_hosts'] == 1) {
					$sql_where .= 'h.id NOT IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 3)';
				} else {
					$sql_where .= 'h.id IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 3)';
				}

				$sql_where .= ' AND ';

				if ($p['policy_graph_templates'] == 1) {
					$sql_where .= 'gl.graph_template_id NOT IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 4)';
				} else {
					$sql_where .= 'gl.graph_template_id IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 4)';
				}

				$sql_where .= ')';
			}

			$sql_where .= ')';
		}

		$sql_where .= ')';
	} elseif ($graph_auth_method == 3) {
		// Policy Rows include
		// id, type (group|user), policy_graphs, policy_hosts, policy_graph_templates

		// Table user_auth_perms has user_id, item_id, type
		// Types can be:
		// 1 - Graph
		// 2 - Trees
		// 3 - Device
		// 4 - Graph Template

		// Policies are
		// 1 - Default Allow, all exceptions are denied
		// 2 - Default Deny, all exceptions are allowed

		$sql_where .= ($sql_where != '' ? ' AND (':'WHERE (');

		foreach($policies as $index => $p) {
			$sql_where .= ($index == 0 ? '((' : ') OR ((');

			if ($p['type'] == 'user') {
				if ($p['policy_graphs'] == 1) {
					$sql_where .= 'gl.id NOT IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 1)';
				} else {
					$sql_where .= 'gl.id IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 1)';
				}

				$sql_where .= ') OR (';

				if ($p['policy_hosts'] == 1) {
					$sql_where .= 'h.id NOT IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 3)';
				} else {
					$sql_where .= 'h.id IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 3)';
				}
			} else {
				if ($p['policy_graphs'] == 1) {
					$sql_where .= 'gl.id NOT IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 1)';
				} else {
					$sql_where .= 'gl.id IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 1)';
				}

				$sql_where .= ') OR (';

				if ($p['policy_hosts'] == 1) {
					$sql_where .= 'h.id NOT IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 3)';
				} else {
					$sql_where .= 'h.id IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 3)';
				}
			}

			$sql_where .= ')';
		}

		$sql_where .= '))';
	} elseif ($graph_auth_method == 4) {
		// Policy Rows include
		// id, type (group|user), policy_graphs, policy_hosts, policy_graph_templates

		// Table user_auth_perms has user_id, item_id, type
		// Types can be:
		// 1 - Graph
		// 2 - Trees
		// 3 - Device
		// 4 - Graph Template

		// Policies are
		// 1 - Default Allow, all exceptions are denied
		// 2 - Default Deny, all exceptions are allowed

		$sql_where .= ($sql_where != '' ? ' AND (':'WHERE (');

		foreach($policies as $index => $p) {
			$sql_where .= ($index == 0 ? '((' : ') OR ((');

			if ($p['type'] == 'user') {
				if ($p['policy_graphs'] == 1) {
					$sql_where .= 'gl.id NOT IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 1)';
				} else {
					$sql_where .= 'gl.id IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 1)';
				}

				$sql_where .= ') OR (';

				if ($p['policy_graph_templates'] == 1) {
					$sql_where .= 'gl.graph_template_id NOT IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 4)';
				} else {
					$sql_where .= 'gl.graph_template_id IN (SELECT item_id FROM user_auth_perms WHERE user_id = ' . $p['id'] . ' AND type = 4)';
				}
			} else {
				if ($p['policy_graphs'] == 1) {
					$sql_where .= 'gl.id NOT IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 1)';
				} else {
					$sql_where .= 'gl.id IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 1)';
				}

				$sql_where .= ') OR (';

				if ($p['policy_graph_templates'] == 1) {
					$sql_where .= 'gl.graph_template_id NOT IN (SELECT item_id FROM user_auth_group_perms WHERE group_id = ' . $p['id'] . ' AND type = 4)';
				} else {
					$sql_where .= 'gl.graph_template_id IN (SELECT item_id FROM user_auth_group_perms WHERE group_id =' . $p['id'] . ' AND type = 4)';
				}
			}

			$sql_where .= ')';
		}

		$sql_where .= '))';
	}

	return $sql_where;
}

/**
 * get_permission_string - get the effective permission string for the graph in question.  The
 *   logic for this is somewhat complex, but understandable.  First, the $graph object will include
 *   three columns generally graphX, deviceX, and templateX for each of the user or groups in the collection.
 *   The way we assign a restrictive or permissive value is based upon the graph permission setting
 *   in Cacti, but also whether or not the default access for the object type is either 'Allow' or 'Deny'.
 *
 *   - If the 'default access' for the object type is 'Deny', then a numeric value in userX means
 *     the user has permission to an object.
 *   - If the default access for the object is 'Allow', then a numeric
 *     value in userX means that the object is blocked.
 *
 *   Again, each of the graphX, deviceX, and templateX will always come in three's.  They equate to:
 *
 *   - graphX    - The user does or does not have permission to the Graph at the Graph Level
 *   - deviceX   - The user does or does not have permission to the Graph at the Device Level
 *   - templateX - The user does or does not have permission to the Graph at the Graph Template Level
 *
 *   Then, the effective permission are calculated by the Graph Permission Model in Cacti.  They are
 *
 *   - Permissive - If the user has access to the Graph, the Device, or Graph Template, then the
 *     user will have access to the Graph.
 *
 *   - Restrictive - If the user has access to the Graph, or both the Device and Graph Template,
 *     then the user will have access to the Graph
 *
 *   - Device - If the user has access to the Graph, or the Device, then the user will have
 *     access to the Graph
 *
 *   - Graph Template - If the user has access to the Graph, or the Graph Template, then the user
 *     will have access to the Graph.
 *
 *   This function will apply this logic, and then respond to the user a 'Granted' or 'Restricted'
 *   column value, and a Tooltip, that shows how the permissions were evaluated.  In other words
 *   why was the user either permitted to or denied access to the Graph.
 */
function get_permission_string(&$graph, &$policies) {
	$grantStr   = '';
	$rejectStr  = '';
	$reasonStr  = '';
	$drejectStr = '';

	// Methods:
	// 1 - Permissive
	// 2 - Restrictive
	// 3 - Device
	// 4 - Graph Template
	$method = read_config_option('graph_auth_method');

	if ($graph['disabled'] == 'on' && read_user_setting('hide_disabled', false, false, get_request_var('user_id'))) {
		$drejectStr .= __esc('Device:(Hide Disabled)');
	}

	// Policies:
	// 1 - Default Allow All - Numeric means blocked
	// 2 - Default Deny All  - Numeric means allowed

	$i = 1;
	foreach($policies as $p) {
		$allowed  = 0;
		$rejected = 0;

		// Perform the Graph Check first
		// If a user has access at the Graph Access, they always get Access
		if ($p['policy_graphs'] == 1) {
			// Default is to allow
			if (empty($graph["graph$i"])) {
				// Allow the access at the level
				$grantStr .= ($grantStr != '' ? ', ':'') . __esc('Graph:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
			} else {
				$rejectStr .= ($rejectStr != '' ? ', ':'') . __esc('Graph:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
				$rejected++;
			}
		} else {
			// Default is to Deny
			if (!empty($graph["graph$i"])) {
				$grantStr .= ($grantStr != '' ? ', ':'') . __esc('Graph:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
			} else {
				$rejectStr .= ($rejectStr != '' ? ', ':'') . __esc('Graph:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
				$rejected++;
			}
		}

		/**
		 * Now we check at the Device and Graph Template Level.  Here the permission get a bit more dicey, so we will
		 * Use case logic to make it read more simply.
		 */
		switch($method) {
			case 1: // Permissive
				if ($p['policy_hosts'] == 1) {
					if (empty($graph["device$i"])) {
						$grantStr .= ($grantStr != '' ? ', ':'') . __esc('Device:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
					} else {
						$rejected++;
					}
				} else {
					if (!empty($graph["device$i"])) {
						$grantStr = $grantStr . ($grantStr != '' ? ', ':'') . __esc('Device:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
					} else {
						$rejected++;
					}
				}

				if ($p['policy_graph_templates'] == 1) {
					if (empty($graph["template$i"])) {
						$grantStr .= ($grantStr != '' ? ', ':'') . __esc('Template:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
					} else {
						$rejected++;
					}
				} else {
					if (!empty($graph["template$i"])) {
						$grantStr .= ($grantStr != '' ? ', ':'') . __esc('Template:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
					} else {
						$rejected++;
					}
				}

				if ($rejected == 3) {
					$rejectStr .= ($rejectStr != '' ? ', ':'') . __esc('Graph+Device+Template:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
				}

				break;
			case 2: // Restrictive
				$allowed  = 0;
				$rejected = 0;

				if ($p['policy_hosts'] == 1) {
					if (empty($graph["device$i"])) {
						$allowed++;
					} else {
						$rejected++;
					}
				} else {
					if (!empty($graph["device$i"])) {
						$allowed++;
					} else {
						$rejected++;
					}
				}

				if ($p['policy_graph_templates'] == 1) {
					if (empty($graph["template$i"])) {
						$allowed++;
					} else {
						$rejected++;
					}
				} else {
					if (!empty($graph["template$i"])) {
						$rejected++;
					} else {
						$allowed++;
					}
				}

				if ($allowed == 2) {
					$grantStr = $grantStr . ($grantStr != '' ? ', ':'') . __esc('Device+Template:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
				} else {
					$rejectStr = $rejectStr . ($rejectStr != '' ? ', ':'') . __esc('Device+Template:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
				}

				break;
			case 3: // Device
				if ($p['policy_hosts'] == 1) {
					if (empty($graph["device$i"])) {
						$grantStr = $grantStr . ($grantStr != '' ? ', ':'') . __esc('Device:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
					} else {
						$rejectStr = $rejectStr . ($rejectStr != '' ? ', ':'') . __esc('Device:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
					}
				} else {
					if (!empty($graph["device$i"])) {
						$grantStr = $grantStr . ($grantStr != '' ? ', ':'') . __esc('Device:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
					} else {
						$rejectStr = $rejectStr . ($rejectStr != '' ? ', ':'') . __esc('Device:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
					}
				}

				break;
			case 4: // Graph Template
				if ($p['policy_graph_templates'] == 1) {
					if (empty($graph["template$i"])) {
						$grantStr = $grantStr . ($grantStr != '' ? ', ':'') . __esc('Template:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
					} else {
						$rejectStr = $rejectStr . ($rejectStr != '' ? ', ':'') . __esc('Template:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
					}
				} else {
					if (!empty($graph["template$i"])) {
						$grantStr = $grantStr . ($grantStr != '' ? ', ':'') . __esc('Template:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
					} else {
						$rejectStr = $rejectStr . ($rejectStr != '' ? ', ':'') . __esc('Template:(%s%s)', ucfirst($p['type']), ($p['type'] != 'user' ? '/' . $p['name']:''));
					}
				}

				break;
		}

		$i++;
	}

	$permStr = '';

	if ($drejectStr != '') {
		$reasonStr .= ($reasonStr != '' ? ', ':'') . __esc('Restricted By: ') . $drejectStr;
	}

	if ($grantStr != '') {
		$reasonStr .= ($reasonStr != '' ? ', ':'') . __esc('Granted By: ') . trim($grantStr, ',');

		if ($rejectStr != '') {
			$reasonStr .= ', ' . __esc('Restricted By: ') . trim($rejectStr, ',');
		}

		if ($drejectStr == '') {
			$permStr = "<span data-tooltip='" . trim($reasonStr) . "' class='accessGranted'>" . __('Granted') . '</span>';
		} else {
			$permStr = "<span data-tooltip='" . trim($reasonStr) . "' class='accessRestricted'>" . __('Restricted') . '</span>';
		}
	} elseif ($rejectStr != '') {
		$reasonStr .= ($reasonStr != '' ? ', ':'') . __esc('Restricted By: ') . trim($rejectStr, ',');

		$permStr   = "<span data-tooltip='" . $reasonStr . "' class='accessRestricted'>" . __('Restricted') . '</span>';
	} else {
		$permStr = __('Unknown');
	}

	return $permStr;
}

/**
 * get_allowed_trees - returns the list of Trees that the user is allowed
 *   To access.  This function is generally intended for both listbox and table displays as
 *   well as to build out the tree for a user.
 *
 * @param  (bool)   Is the Tree in Edit mode or not
 * @param  (bool)   Return either the SQL used to get the values or the values
 * @param  (string) The SQL Order clause to use for the sorting of graphs
 * @param  (int)    The limit on items to return.  If empty or -1, return all items
 * @param  (int)    The number of rows found, to be returned to the caller
 * @param  (int)    If checking a user, specify the user_id otherwise for the current user leave blank
 * @param  (int)    If just searching for if a single graph template is permitted, the id of that graph template
 *
 * @return (string|array)  An array of permitted Trees or the SQL to gather them
 */
function get_allowed_trees($edit = false, $return_sql = false, $sql_where = '', $sql_order = 'name', $sql_limit = '', &$total_rows = 0, $user_id = 0) {
	if ($sql_limit != '' && $sql_limit != -1) {
		$sql_limit = "LIMIT $sql_limit";
	} else {
		$sql_limit = '';
	}

	if ($sql_order != '') {
		$sql_order = "ORDER BY $sql_order";
	}

	if ($user_id == -1) {
		$auth_method = AUTH_METHOD_NONE;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method != AUTH_METHOD_NONE) {
		if ($user_id == 0) {
			if (isset($_SESSION['sess_user_id'])) {
				$user_id = $_SESSION['sess_user_id'];
			} else {
				return array();
			}
		}

		/* get policies for all groups and user */
		$policies = db_fetch_assoc_prepared("SELECT uag.id, 'group' AS type, policy_trees FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on'
			AND uagm.user_id = ?",
			array($user_id));

		$policies[] = db_fetch_row_prepared("SELECT id, 'user' as type, policy_trees FROM user_auth WHERE id = ?", array($user_id));

		$i          = 0;
		$sql_join   = '';
		$sql_where1 = '';

		foreach($policies as $policy) {
			if ($policy['policy_trees'] == '1') {
				$sql_where1 .= ($sql_where1 != '' ? ' OR':'') . " uap$i." . $policy['type'] . "_id IS NULL";
			} elseif ($policy['policy_trees'] == '2') {
				$sql_where1 .= ($sql_where1 != '' ? ' OR':'') . " uap$i." . $policy['type'] . "_id IS NOT NULL";
			}

			$sql_join .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'group' ? 'group_':'') . "perms AS uap$i
				ON (gt.id=uap$i.item_id AND uap$i.type=2 AND uap$i." . $policy['type'] . '_id=' . $policy['id'] . ') ';

			$i++;
		}

		if ($sql_where != '') {
			$sql_where = 'WHERE ' . ($edit == false ? '(gt.enabled="on") AND ':'') . '(' . $sql_where . ') AND (' . $sql_where1 . ')';
		} else {
			$sql_where = 'WHERE ' . ($edit == false ? '(gt.enabled="on") AND ':'') . '(gt.enabled="on") AND (' . $sql_where1 . ')';
		}

		$sql = "SELECT id, name
			FROM graph_tree AS gt
			$sql_join
			$sql_where
			$sql_order
			$sql_limit";

		if ($return_sql) {
			return $sql;
		} else {
			$trees = db_fetch_assoc($sql);

			$sql = "SELECT COUNT(gt.id)
				FROM graph_tree AS gt
				$sql_join
				$sql_where";

			$total_rows = get_total_row_data($user_id, $sql, array(), 'tree');
		}
	} else {
		if ($sql_where != '') {
			$sql_where = "WHERE enabled='on' AND $sql_where";
		} else {
			$sql_where = "WHERE enabled='on'";
		}

		if ($return_sql) {
			return "SELECT id, name FROM graph_tree $sql_where $sql_order";
		} else {
			$trees = db_fetch_assoc("SELECT id, name FROM graph_tree AS gt $sql_where $sql_order");

			$sql = "SELECT COUNT(*) FROM graph_tree AS gt $sql_where";

			$total_rows = get_total_row_data($user_id, $sql, array(), 'tree');
		}
	}

	return $trees;
}

/**
 * get_allowed_branches - returns the list of Tree branches that the user is allowed
 *   To access.  This function is generally intended for both listbox and table displays as
 *   well as to build out the tree for a user.
 *
 * @param  (bool)   Is the Tree in Edit mode or not
 * @param  (string) The SQL Where used to get the values or the values
 * @param  (string) The SQL Order clause to use for the sorting of branches
 * @param  (int)    The limit on items to return.  If empty or -1, return all items
 * @param  (int)    The number of rows found, to be returned to the caller
 * @param  (int)    If checking a user, specify the user_id otherwise for the current user leave blank
 *
 * @return (array)  An array of permitted Tree branches
 */
function get_allowed_branches($sql_where = '', $sql_order = 'name', $sql_limit = '', &$total_rows = 0, $user_id = 0) {
	$sql_join = '';
	if ($sql_limit != '' && $sql_limit != -1) {
		$sql_limit = "LIMIT $sql_limit";
	} else {
		$sql_limit = '';
	}

	if ($sql_order != '') {
		$sql_order = "ORDER BY $sql_order";
	}

	// suppress total rows
	$total_rows = -1;

	if ($user_id == -1) {
		$auth_method = AUTH_METHOD_NONE;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method > AUTH_METHOD_NONE && $user_id == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$user_id = $_SESSION['sess_user_id'];
		} else {
			return array();
		}
	}

	$simple_perms = get_simple_device_perms($user_id);

	/* get policies for all groups and user */
	$policies = get_policies($user_id);

	$sql_hosts_where = '';

	if (!$simple_perms) {
		$hosts = get_allowed_devices('', 'description', '', $total_rows);

		if (cacti_sizeof($hosts)) {
			$hosts = array_rekey($hosts, 'id', 'description');
		}

		$tree_hosts = db_fetch_assoc("SELECT DISTINCT h.id, h.description
			FROM graph_tree_items AS gti
			INNER JOIN graph_tree AS gt
			ON gti.graph_tree_id = gt.id
			INNER JOIN host AS h
			ON gti.host_id = h.id
			WHERE gti.host_id > 0
			AND $sql_where", false);

		if (cacti_sizeof($tree_hosts)) {
			$tree_hosts = array_rekey($tree_hosts, 'id', 'description');
		}

		$hosts = array_intersect_key($hosts, $tree_hosts);

		if (cacti_sizeof($hosts) > 0) {
			$sql_hosts_where =  'AND h.id IN (' . implode(',', array_keys($hosts)) . ')';
		}
	}

	if ($auth_method != AUTH_METHOD_NONE) {
		$i          = 0;
		$sql_join   = '';
		$sql_where1 = '';

		foreach($policies as $policy) {
			if ($policy['policy_trees'] == '1') {
				$sql_where1 .= ($sql_where1 != '' ? ' OR':'') . " uap$i." . $policy['type'] . "_id IS NULL";
			} elseif ($policy['policy_trees'] == '2') {
				$sql_where1 .= ($sql_where1 != '' ? ' OR':'') . " uap$i." . $policy['type'] . "_id IS NOT NULL";
			}

			$sql_join .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'group' ? 'group_':'') . "perms AS uap$i
				ON (gt.id=uap$i.item_id AND uap$i.type=2 AND uap$i." . $policy['type'] . '_id=' . $policy['id'] . ') ';

			$i++;
		}

		if ($sql_where != '') {
			$sql_where = 'WHERE gt.enabled="on" AND (' . $sql_where . ')' . ($sql_where1 != '' ? ' AND (' . $sql_where1 . ')':'');
		} elseif ($sql_where1 != '') {
			$sql_where = 'WHERE gt.enabled="on" AND (' . $sql_where1 . ')';
		}

		$sql = "(SELECT gti.id, CONCAT('". __('Branch:') . " ', gti.title) AS name
			FROM graph_tree AS gt
			INNER JOIN graph_tree_items AS gti
			ON gti.graph_tree_id = gt.id
			AND gti.host_id = 0
			AND gti.local_graph_id=0
			$sql_join
			$sql_where
			) UNION (
			SELECT gti.id, CONCAT('" . __('Device:') . " ', h.description) AS name
			FROM graph_tree AS gt
			INNER JOIN graph_tree_items AS gti
			ON gti.graph_tree_id = gt.id
			INNER JOIN host AS h
			ON h.id=gti.host_id
			$sql_hosts_where
			$sql_join
			$sql_where
 			)
			$sql_order
			$sql_limit";

		$branches = db_fetch_assoc($sql);

		$sql = 'SELECT COUNT(*) FROM (' . $sql . ') AS rower';

		$total_rows = get_total_row_data($user_id, $sql, array(), 'branch');
	} else {
		if ($sql_where != '') {
			$sql_where = "WHERE gt.enabled='on' AND h.disabled='' AND ((h.id > 0 AND h.deleted = '') OR h.id IS NULL) AND $sql_where";
		} else {
			$sql_where = "WHERE gt.enabled='on' AND h.disabled='on' AND ((h.id > 0 AND h.deleted = '') OR h.id IS NULL)";
		}

		$sql = "(
			SELECT gti.id, CONCAT('". __('Branch:') . " ', gti.title) AS name
			FROM graph_tree AS gt
			INNER JOIN graph_tree_items AS gti
			ON gti.graph_tree_id = gt.id
			AND gti.host_id=0
			AND gti.local_graph_id=0
			$sql_where
			) UNION (
			SELECT gti.id, CONCAT('" . __('Device:') . " ', h.description) AS name
			FROM graph_tree AS gt
			INNER JOIN graph_tree_items AS gti
			ON gti.graph_tree_id = gt.id
			INNER JOIN host AS h
			ON h.id=gti.host_id
			$sql_join
			$sql_where
			)
			$sql_order
			$sql_limit";

		$branches = db_fetch_assoc($sql);

		$sql = 'SELECT COUNT(*) FROM (' . $sql . ') AS rower';

		$total_rows = get_total_row_data($user_id, $sql, array(), 'branch');
	}

	return $branches;
}

/**
 * get_allowed_devices - returns the list of devices that the user is allowed
 *   To access.  This function is generally intended for both listbox and table displays as
 *   well as other tasks.
 *
 * @param  (string) The SQL Where used to get the values or the values
 * @param  (string) The SQL Order clause to use for the sorting of devices
 * @param  (int)    The limit on items to return.  If empty or -1, return all items
 * @param  (int)    The number of rows found, to be returned to the caller
 * @param  (int)    If checking a user, specify the user_id otherwise for the current user leave blank
 *
 * @return (array)  An array of permitted devices
 */
function get_allowed_devices($sql_where = '', $sql_order = 'description', $sql_limit = '', &$total_rows = 0, $user_id = 0, $device_id = 0) {
	if ($user_id == -1) {
		$auth_method = AUTH_METHOD_NONE;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method > AUTH_METHOD_NONE && $user_id == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$user_id = $_SESSION['sess_user_id'];
		} else {
			return array();
		}
	}

	$simple_perms = get_simple_device_perms($user_id);

	$init_rows = $total_rows;

	$host_list = array();

	if ($sql_limit != '' && $sql_limit != -1) {
		$sql_limit = "LIMIT $sql_limit";
	} else {
		$sql_limit = '';
	}

	if ($sql_order != '') {
		$sql_order = "ORDER BY $sql_order";
	}

	if (read_user_setting('hide_disabled') == 'on') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . '(h.disabled = "" OR h.disabled IS NULL)';
	}

	if ($sql_where != '') {
		$sql_where = "WHERE ((h.id > 0 AND h.deleted = '') OR h.id IS NULL) AND $sql_where";
	} else {
		$sql_where = "WHERE ((h.id > 0 AND h.deleted = '') OR h.id IS NULL) ";
	}

	if ($device_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . " h.id = $device_id";
	}

	$graph_auth_method = read_config_option('graph_auth_method');

	/* get policies for all groups and user */
	$policies = get_policies($user_id);

	if (!$simple_perms && $auth_method != AUTH_METHOD_NONE) {
		$sql_where = get_policy_where($graph_auth_method, $policies, $sql_where);
	}

	if ($total_rows != -2) {
		$host_list = db_fetch_assoc("SELECT h1.*
			FROM host AS h1
			INNER JOIN (
				SELECT DISTINCT id
				FROM (
					SELECT h.id
					FROM host AS h
					LEFT JOIN graph_local AS gl
					ON h.id=gl.host_id
					LEFT JOIN graph_templates AS gt
					ON gt.id=gl.graph_template_id
					LEFT JOIN host_template AS ht
					ON h.host_template_id=ht.id
					$sql_where
				) AS rs1
			) AS rs2
			ON rs2.id=h1.id
			$sql_order
			$sql_limit");
	}

	if ($total_rows >= 0 || $total_rows == -2) {
		$sql = "SELECT COUNT(DISTINCT id)
			FROM (
				SELECT h.id
				FROM host AS h
				LEFT JOIN graph_local AS gl
				ON h.id=gl.host_id
				LEFT JOIN graph_templates AS gt
				ON gt.id=gl.graph_template_id
				LEFT JOIN host_template AS ht
				ON h.host_template_id=ht.id
				$sql_where
			) AS rower";

		if ($device_id == 0) {
			$total_rows = get_total_row_data($user_id, $sql, array(), 'device');
		} else {
			$total_rows = db_fetch_cell($sql);
		}
	}

	return $host_list;
}

/**
 * get_allowed_sites - returns the list of sites that the user is allowed
 *   To access.  This function is generally intended for both listbox and table displays as
 *   well as other tasks.
 *
 * @param  (string) The SQL Where used to get the values or the values
 * @param  (string) The SQL Order clause to use for the sorting of devices
 * @param  (int)    The limit on items to return.  If empty or -1, return all items
 * @param  (int)    The number of rows found, to be returned to the caller
 * @param  (int)    If checking a user, specify the user_id otherwise for the current user leave blank
 * @param  (int)    If checking a single site, specify the site_id
 *
 * @return (array)  An array of permitted sites
 */
function get_allowed_sites($sql_where = '', $sql_order = 'name', $sql_limit = '', &$total_rows = 0, $user_id = 0, $site_id = 0) {
	if ($sql_limit != '' && $sql_limit != -1) {
		$sql_limit = "LIMIT $sql_limit";
	} else {
		$sql_limit = '';
	}

	if ($sql_order != '') {
		$sql_order = "ORDER BY $sql_order";
	}

	if ($sql_where != '') {
		$sql_where = "WHERE $sql_where";
	}

	if ($site_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . " s.id=$site_id";
	}

	if ($user_id == -1) {
		$auth_method = AUTH_METHOD_NONE;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if (isset($_SESSION['sess_user_id']) && $user_id == 0) {
		$user_id = $_SESSION['sess_user_id'];
	} elseif ($auth_method > AUTH_METHOD_NONE) {
		return array();
	}

	$sites = db_fetch_assoc("SELECT s.id, s.name
		FROM sites AS s
		INNER JOIN host AS h
		ON s.id=h.site_id
		$sql_where
		GROUP BY s.id
		$sql_order
		$sql_limit");

	$sql = "SELECT COUNT(DISTINCT s.id)
		FROM sites AS s
		INNER JOIN host AS h
		ON s.id=h.site_id
		$sql_where";

	$total_rows = get_total_row_data($user_id, $sql, array(), 'site_device');

	return $sites;
}

/**
 * get_allowed_site_devices - returns the list of devices in a site that the user is allowed
 *   To access.  This function is generally intended for both listbox and table displays as
 *   well as other tasks.
 *
 * @param  (int)    The site id for the site
 * @param  (string) The SQL Where used to get the values or the values
 * @param  (string) The SQL Order clause to use for the sorting of devices
 * @param  (int)    The limit on items to return.  If empty or -1, return all items
 * @param  (int)    The number of rows found, to be returned to the caller
 * @param  (int)    If checking a user, specify the user_id otherwise for the current user leave blank
 *
 * @return (array)  An array of permitted site devices
 */
function get_allowed_site_devices($site_id, $sql_where = '', $sql_order = 'description', $sql_limit = '', &$total_rows = 0, $user_id = 0) {
	if ($user_id == -1) {
		$auth_method = AUTH_METHOD_NONE;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method > AUTH_METHOD_NONE && $user_id == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$user_id = $_SESSION['sess_user_id'];
		} else {
			return array();
		}
	}

	$simple_perms = get_simple_device_perms($user_id);

	$policies = get_policies($user_id);

	$graph_auth_method = read_config_option('graph_auth_method');

	if ($sql_limit != '' && $sql_limit != -1) {
		$sql_limit = "LIMIT $sql_limit";
	} else {
		$sql_limit = '';
	}

	if ($sql_order != '') {
		$sql_order = "ORDER BY $sql_order";
	}

	if (read_user_setting('hide_disabled') == 'on') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . '(h.disabled = "" OR h.disabled IS NULL)';
	}

	if ($sql_where != '') {
		$sql_where = "WHERE ((h.id > 0 AND h.deleted = '') OR h.id IS NULL) AND $sql_where";
	} else {
		$sql_where = "WHERE ((h.id > 0 AND h.deleted = '') OR h.id IS NULL) ";
	}

	if ($site_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . " h.site_id=$site_id";
	}

	if ($auth_method != AUTH_METHOD_NONE && !$simple_perms) {
		$sql_where = get_policy_where($graph_auth_method, $policies, $sql_where);
	}

	$host_list = db_fetch_assoc("SELECT h1.*, ht.name AS host_template_name
		FROM host AS h1
		LEFT JOIN host_template AS ht
		ON h1.host_template_id=ht.id
		INNER JOIN (
			SELECT DISTINCT id
			FROM (
				SELECT h.id
				FROM host AS h
				LEFT JOIN graph_local AS gl
				ON h.id=gl.host_id
				LEFT JOIN graph_templates_graph AS gtg
				ON gl.id=gtg.local_graph_id
				LEFT JOIN graph_templates AS gt
				ON gt.id=gl.graph_template_id
				LEFT JOIN host_template AS ht
				ON h.host_template_id=ht.id
				$sql_where
			) AS rs1
		) AS rs2
		ON rs2.id=h1.id
		$sql_order
		$sql_limit");

	$sql = "SELECT COUNT(DISTINCT id)
		FROM (
			SELECT h.id
			FROM host AS h
			LEFT JOIN graph_local AS gl
			ON h.id=gl.host_id
			LEFT JOIN graph_templates_graph AS gtg
			ON gl.id=gtg.local_graph_id
			LEFT JOIN graph_templates AS gt
			ON gt.id=gl.graph_template_id
			LEFT JOIN host_template AS ht
			ON h.host_template_id=ht.id
			$sql_where
		) AS rower";

	$total_rows = get_total_row_data($user_id, $sql, array(), 'site_device');

	return $host_list;
}

/**
 * get_allowed_graph_templates_normalized - returns the list of graph templates aligned with the
 *   To be able to differentiate between Graph Templates based on a non-data query data input mode
 *   and those related to data queries.
 *
 * @param  (string) The SQL Where used to get the values or the values
 * @param  (string) The SQL Order clause to use for the sorting of devices
 * @param  (int)    The limit on items to return.  If empty or -1, return all items
 * @param  (int)    The number of rows found, to be returned to the caller
 * @param  (int)    If checking a user, specify the user_id otherwise for the current user leave blank
 * @param  (int)    If checking a single graph template, specify the graph_template_id
 *
 * @return (array)  An array of permitted and normalized graph templates
 */
function get_allowed_graph_templates_normalized($sql_where = '', $sql_order = 'name', $sql_limit = '', &$total_rows = 0, $user_id = 0, $graph_template_id = 0) {
	$templates = array_rekey(get_allowed_graph_templates($sql_where, $sql_order, $sql_limit, $total_rows, $user_id, $graph_template_id), 'id', 'name');

	if (!cacti_sizeof($templates)) {
		return array();
	}

	if ($sql_where != '') {
		$sql_where = ' WHERE (' . $sql_where . ') AND gl.graph_template_id IN(' . implode(', ', array_keys($templates)) . ') AND (gl.snmp_query_graph_id=0 OR sqg.name IS NOT NULL) AND (gt.name IS NOT NULL)';
	} else {
		$sql_where = ' WHERE gl.graph_template_id IN(' . implode(', ', array_keys($templates)) . ') AND (gl.snmp_query_graph_id=0 OR sqg.name IS NOT NULL) AND (gt.name IS NOT NULL)';
	}

	if ($sql_limit != '' && $sql_limit != -1) {
		$sql_limit = "LIMIT $sql_limit";
	} else {
		$sql_limit = '';
	}

	if ($sql_order != '') {
		$sql_order = "ORDER BY $sql_order";
	}

	$templates = db_fetch_assoc("SELECT
		IF(snmp_query_graph_id=0, CONCAT('cg_',gl.graph_template_id), CONCAT('dq_', gl.snmp_query_graph_id)) AS id,
		IF(snmp_query_graph_id=0, gt.name, CONCAT(gt.name, ' [', sqg.name, ']')) AS name
		FROM graph_local AS gl
		LEFT JOIN graph_templates AS gt
		ON gt.id=gl.graph_template_id
		LEFT JOIN snmp_query_graph AS sqg
		ON gl.snmp_query_graph_id=sqg.id
		AND gl.graph_template_id=sqg.graph_template_id
		$sql_where
		GROUP BY id, name
		$sql_order
		$sql_limit");

	return $templates;
}

/**
 * get_total_row_data - returns the total rows based upon a set of criteria
 *
 * This function will hash the $sql, and then search for the total
 * row counter based upon that criteria and if it finds a unexpired
 * match for that data, it will return the row count in the table
 * otherwise, it will execute the SQL and return the data.
 *
 * @param  (int)    The user id making the request
 * @param  (string) The sql to be executed, either prepared or otherwise
 * @param  (array)  In the case of a prepared statement the
 * @param  (string) The user defined class of data
 * @param  (int)    The timeout for the Class if not controlled by Cacti
 *
 * @return (array) an array containing a list of hosts
 */
function get_total_row_data($user_id, $sql, $sql_params = array(), $class = '', $timeout = 86400) {
	$execute  = true;
	$now_time = time();

	if (cacti_sizeof($sql_params)) {
		$nsql = str_replace('?', "'%s'", $sql);
		$nsql = vsprintf($nsql, $sql_params);

		$hash = md5($nsql);
	} else {
		$hash = md5($sql);
	}

	$row_data = db_fetch_row_prepared('SELECT total_rows, UNIX_TIMESTAMP(time) AS time
		FROM user_auth_row_cache
		WHERE user_id = ?
		AND class = ?
		AND hash = ?',
		array($user_id, $class, $hash));

	$cached = false;

	if (cacti_sizeof($row_data)) {
		$cached    = true;
		$last_time = read_config_option('time_last_change_' . $class);

		if (!empty($last_time)) {
			if ($row_data['time'] >= $last_time) {
				return $row_data['total_rows'];
			}
		} elseif ($now_time - $row_data['time'] < $timeout) {
			return $row_data['total_rows'];
		}
	}

	if (cacti_sizeof($sql_params)) {
		$rows = db_fetch_cell($sql, $sql_params);
	} else {
		$rows = db_fetch_cell($sql);
	}

	if ($user_id > 0) {
		db_execute_prepared('REPLACE INTO user_auth_row_cache
			(user_id, class, hash, total_rows, time)
			VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))',
			array($user_id, $class, $hash, $rows, $now_time));
	}

	return $rows;
}

/**
 * get_host_array - returns a list of hosts taking permissions into account if necessary
 *
 * @return (array) an array containing a list of hosts
 */
function get_host_array() {
	$total_rows = -1;

	$hosts = get_allowed_devices('', 'description', '', $total_rows);

	foreach($hosts as $host) {
		$return_devices[] = strip_domain($host['description']) . ' (' . strip_domain($host['hostname']) . ')';
	}

	return $return_devices;
}

/**
 * get_allowed_ajax_hosts - returns a list of hosts in a way that can be easily read through
 *   a callback, in JSON.  The 'term' request variable will include an optional search term.
 *
 * @param  (bool)   Include the 'Any' item as the first in the list
 @ @param  (bool)   Include the 'None' item as the first or second in the list
 * @param  (string) SQL Where expression to use to gather the hosts in addition to the 'term'
 *   request variable.
 *
 * @return (string) A json array of matching devices upto a limit specified in the system
 *   settings
 */
function get_allowed_ajax_hosts($include_any = true, $include_none = true, $sql_where = '') {
	$return = array();

	$term = get_filter_request_var('term', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));
	if ($term != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : '') .
			'(hostname LIKE ' . db_qstr("%$term%") .
			' OR description LIKE ' . db_qstr("%$term%") .
			' OR notes LIKE ' . db_qstr("%$term%") . ')';
	}

	if (get_request_var('term') == '') {
		if ($include_any) {
			$return[] = array('label' => __('Any'), 'value' => 'Any', 'id' => '-1');
		}
		if ($include_none) {
			$return[] = array('label' => __('None'), 'value' => 'None', 'id' => '0');
		}
	}

	$total_rows = -1;

	$hosts = get_allowed_devices($sql_where, 'description', read_config_option('autocomplete_rows'), $total_rows);

	if (cacti_sizeof($hosts)) {
		foreach($hosts as $host) {
			$return[] = array('label' => html_escape(strip_domain($host['description'])), 'value' => html_escape($host['description']), 'id' => $host['id']);
		}
	}

	print json_encode($return);
}

/**
 * get_allowed_ajax_graph_templates - returns a list of graph_template in a way that can be easily
 *   read through a callback, in JSON.  The 'term' request variable will include an optional search term.
 *
 * @param  (bool)   Include the 'Any' item as the first in the list
 @ @param  (bool)   Include the 'None' item as the first or second in the list
 * @param  (string) SQL Where expression to use to gather the graph templates in addition to the 'term'
 *   request variable.
 *
 * @return (string) A json array of matching graph templates upto a limit specified in the system
 *   settings
 */
function get_allowed_ajax_graph_templates($include_any = true, $include_none = true, $sql_where = '') {
	$return = array();

	$term = get_filter_request_var('term', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));
	if ($term != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'name LIKE ' . db_qstr("%$term%");
	}

	if (get_request_var('term') == '') {
		if ($include_any) {
			$return[] = array('label' => __('Any'), 'value' => 'Any', 'id' => '-1');
		}
		if ($include_none) {
			$return[] = array('label' => __('None'), 'value' => 'None', 'id' => '0');
		}
	}

	$total_rows = -1;

	$templates = get_allowed_graph_templates($sql_where, 'gt.name', read_config_option('autocomplete_rows'), $total_rows);
	if (cacti_sizeof($templates)) {
		foreach($templates as $template) {
			$return[] = array('label' => html_escape($template['name']), 'value' => html_escape($template['name']), 'id' => $template['id']);
		}
	}

	print json_encode($return);
}

/**
 * get_allowed_ajax_graph_items - returns a list of graph items in a way that can be easily
 *   read through a callback, in JSON.  The 'term' request variable will include an optional search term.
 *
 @ @param  (bool)   Include the 'None' item as the first item in the list
 * @param  (string) SQL Where expression to use to gather the hosts in addition to the 'term'
 *   request variable.
 *
 * @return (string) A json array of matching graph items upto a limit specified in the system
 *   settings
 */
function get_allowed_ajax_graph_items($include_none = true, $sql_where = '') {
	$return    = array();

	$term = get_filter_request_var('term', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));
	if ($term != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : '') .
			'name_cache LIKE ' . db_qstr("%$term%") .
			' OR data_source_name LIKE ' . db_qstr("%$term%");
	}

	if (get_request_var('term') == '') {
		if ($include_none) {
			$return[] = array('label' => __('None'), 'value' => 'None', 'id' => '0');
		}
	}

	$graph_items = get_allowed_graph_items($sql_where, 'name_cache', read_config_option('autocomplete_rows'));
	if (cacti_sizeof($graph_items)) {
		foreach($graph_items as $gi) {
			$return[] = array('label' => html_escape($gi['name']), 'value' => html_escape($gi['name']), 'id' => $gi['id']);
		}
	}

	print json_encode($return);
}

/**
 * get_allowed_ajax_graph - returns a list of allowed graphs in a way that can be easily
 *   read through a callback, in JSON.  The 'term' request variable will include an optional search term.
 *
 * @param  (string) SQL Where expression to use to gather the graphs in addition to the 'term'
 *   request variable.
 *
 * @return (string) A json array of matching graphs upto a limit specified in the system
 *   settings
 */
function get_allowed_ajax_graphs($sql_where = '') {
	$return = array();

	$term = get_filter_request_var('term', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));
	if ($term != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'title_cache LIKE ' . db_qstr("%$term%");
	}

	$total_rows = -1;

	$graphs = get_allowed_graphs($sql_where, 'gtg.title_cache', read_config_option('autocomplete_rows'), $total_rows);
	if (cacti_sizeof($graphs)) {
		foreach($graphs as $graph) {
			$return[] = array('label' => html_escape($graph['title_cache']), 'value' => html_escape($graph['title_cache']), 'id' => $graph['local_graph_id']);
		}
	}

	print json_encode($return);
}

/**
 * get_allowed_graph_items - returns a array of allowed graph items in a way that can be easily
 *   use in a table or list.
 *
 * @param  (string) The SQL Where expression to use to gather the graph items
 * @param  (string) The SQL Order clause to use for the sorting of devices
 * @param  (int)    The limit on items to return.  If empty or -1, return all items
 * @param  (int)    The number of rows found, to be returned to the caller
 * @param  (int)    If checking a user, specify the user_id otherwise for the current user leave blank
 *
 * @return (array) An array of permitted graph items
 */
function get_allowed_graph_items($sql_where, $sql_order = 'name', $sql_limit = 20, $user_id = 0) {
	$return = array();

	if ($user_id == 0 && isset($_SESSION['sess_user_id'])) {
		$user_id = $_SESSION['sess_user_id'];
	}

	if ($sql_where != '') {
		$sql_where = 'WHERE ' . $sql_where;
	}

	if ($sql_limit != '' && $sql_limit != -1) {
		$sql_limit = 'LIMIT ' . $sql_limit;
	} else {
		$sql_limit = '';
	}

	if ($sql_order != '') {
		$sql_order = "ORDER BY $sql_order";
	}

	$items = db_fetch_assoc("SELECT
		CONCAT_WS('', dtd.name_cache,' (', dtr.data_source_name, ')') as name, dtr.id, dl.host_id
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dtd.local_data_id=dl.id
		INNER JOIN data_template_rrd AS dtr
		ON dtr.local_data_id=dl.id
		LEFT JOIN host AS h
		ON dl.host_id=h.id
		$sql_where
		$sql_order
		$sql_limit");

	if (cacti_sizeof($items)) {
		foreach($items as $i) {
			if (is_device_allowed($i['host_id'], $user_id)) {
				$return[] = array('id' => $i['id'], 'name' => $i['name']);
			}
		}
	}

	return $return;
}

/**
 * auth_get_username - returns the login username for the user attempting to login
 *
 * @return (string) the username attempting to login
 */
function auth_get_username() {
	$auth_method = read_config_option('auth_method');

	if ($auth_method == AUTH_METHOD_BASIC) {
		$username = get_basic_auth_username();

		/* Get the Web Basic Auth username and set action so we login right away */
		set_request_var('action', 'login');

	} elseif (get_nfilter_request_var('action') == 'login') {
		$username = get_nfilter_request_var('login_username');
	} else {
		$username = '';
	}

	$username = sanitize_search_string($username);

	return $username;
}

/**
 * auth_checkclear_lockout - checks the lockout status of a user and unlocks if necessary
 *
 * @param  (string) $username The username of the user to check
 * @param  (int)    $realm The realm of the user to check
 *
 * @return (void)
 */
function auth_checkclear_lockout($username, $realm) {
	// Unlock the user account if timing permits
	$secPassLockFailed = read_config_option('secpass_lockfailed');
	if ($secPassLockFailed > 0) {
		$max = intval($secPassLockFailed);
		if ($max > 0) {
			$user = db_fetch_row_prepared("SELECT id, username, lastfail, failed_attempts, `locked`, password
				FROM user_auth
				WHERE username = ?
				AND realm = ?
				AND enabled = 'on'",
				array($username, $realm));

			if (cacti_sizeof($user)) {
				$unlock = intval(read_config_option('secpass_unlocktime'));
				if ($unlock > 1440) {
					$unlock = 1440;
				}

				$secs_unlock = $unlock * 60;
				$secs_fail = time() - $user['lastfail'];

				cacti_log('DEBUG: User \'' . $username . '\' secs_fail = ' . $secs_fail . ', secs_unlock = ' . $secs_unlock, false, 'AUTH', POLLER_VERBOSITY_DEBUG);

				if ($unlock > 0 && ($secs_fail > $secs_unlock)) {
					db_execute_prepared("UPDATE user_auth
						SET lastfail = 0, failed_attempts = 0, `locked` = ''
						WHERE username = ?
						AND realm = ?
						AND enabled = 'on'",
						array($username, $realm));
				}
			}
		}
	}
}

/**
 * auth_process_lockout_check - checks to see if the user is locked out of their account
 *   if there is an error, the globals error and error_msg will be set to notify the caller
 *   that a lockout is present and not to proceed with login.
 *
 * @param  (string) $username - The name of the user account
 * @param  (int)    $realm - The logging realm for the user
 *
 * @return (bool)   True if locked out, otherwise false
 */
function auth_process_lockout_check($username, $realm) {
	global $error, $error_msg;

	// Mark failed login attempts
	$secPassLockFailed = read_config_option('secpass_lockfailed');
	if ($secPassLockFailed > 0) {
		$max = intval($secPassLockFailed);
		if ($max > 0) {
			$user = db_fetch_row_prepared("SELECT id, username, lastfail, failed_attempts, `locked`, password
				FROM user_auth
				WHERE username = ?
				AND realm = ?
				AND enabled = 'on'",
				array($username, $realm));

			if (cacti_sizeof($user)) {
				if ($user['locked'] == 'on') {
					$error     = true;
					$error_msg = __('Your account has been locked.  Please contact your Administrator.');

					return true;
				}
			}
		}
	}

	return false;
}

/**
 * auth_process_lockout - called when a user login attempt fails to increment or lockout the user
 *   if there is an error, the globals error and error_msg will be set to notify the caller
 *   that a lockout is present and not to proceed with login.
 *
 * @param  (string) $username - The name of the user account
 * @param  (int)    $realm - The logging realm for the user
 *
 * @return (void)
 */
function auth_process_lockout($username, $realm) {
	global $error, $error_msg;

	// Mark failed login attempts
	$secPassLockFailed = read_config_option('secpass_lockfailed');
	if ($secPassLockFailed > 0) {
		$max = intval($secPassLockFailed);
		if ($max > 0) {
			$user = db_fetch_row_prepared("SELECT id, username, enabled, lastfail, failed_attempts, `locked`, password
				FROM user_auth
				WHERE username = ?
				AND realm = ?",
				array($username, $realm));

			if (cacti_sizeof($user)) {
				if ($user['enabled'] == '') {
					cacti_log("LOGIN FAILED: Local Login Failed for user '" . $username . "' from IP Address '" . get_client_addr() . "'.  User account Disabled.", false, 'AUTH');

					$error     = true;
					$error_msg = __('Access Denied!  Login Disabled.');
				}

				$failed = intval($user['failed_attempts']) + 1;

				cacti_log('LOGIN FAILED: User \'' . $username . '\' failed authentication, incrementing lockout (' . $failed . ' of ' . $max . ')', false, 'AUTH', POLLER_VERBOSITY_LOW);

				if ($failed >= $max) {
					db_execute_prepared("UPDATE user_auth
						SET `locked` = 'on'
						WHERE username = ?
						AND realm = ?
						AND enabled = 'on'",
						array($username, $realm));

					$user['locked'] = 'on';
				}

				$user['lastfail'] = time();

				db_execute_prepared("UPDATE user_auth
					SET lastfail = ?, failed_attempts = ?
					WHERE username = ?
					AND realm = ?
					AND enabled = 'on'",
					array($user['lastfail'], $failed, $username, $realm));

				// Log the invalid password attempt
				db_execute_prepared('INSERT IGNORE INTO user_log
					(username, user_id, result, ip, time)
					VALUES (?, ?, 0, ?, NOW())',
					array($username, isset($user['id']) ? $user['id']:0, get_client_addr()));

				if ($user['locked'] == 'on') {
					cacti_log("LOGIN FAILED: Local Login Failed for user '" . $username . "' from IP Address '" . get_client_addr() . "'.  Account is locked out.", false, 'AUTH');

					$error     = true;
					$error_msg = __('Your account has been locked.  Please contact your Administrator.');
				} else {
					cacti_log("LOGIN FAILED: Local Login Failed for user '" . $username . "' from IP Address '" . get_client_addr() . "'.", false, 'AUTH');

					/* error */
					$error     = true;
					$error_msg = __('Access Denied!  Login Failed.');
				}
			} elseif ($user['locked'] == 'on') {
				cacti_log("LOGIN FAILED: Local Login Failed for user '" . $username . "' from IP Address '" . get_client_addr() . "'.", false, 'AUTH');

				$error     = true;
				$error_msg = __('Access Denied!  Login Failed.');
			}
		}
	}
}

/**
 * basic_auth_login_process - login a basic auth account or generate an error
 *   if there is an error, the globals error and error_msg will be set to notify the caller
 *   that a lockout is present and not to proceed with login.  This function will also
 *   exit and return html to the display to notify the user of critical errors.
 *
 * @param  (string) $username The user to process
 *
 * @return (array|void) $user The valid user, an empty array if the user must be created
 *   or void in the case of an exit condition
 */
function basic_auth_login_process($username) {
	global $error, $error_msg;

	if (empty($username)) {
		cacti_log('ERROR: No username passed with Web Basic Authentication enabled.', false, 'AUTH');
		auth_display_custom_error_message(__('Web Basic Authentication configured, but no username was passed from the web server. Please make sure you have authentication enabled on the web server.'));

		exit;
	}

	/* Locate user in database */
	$user = db_fetch_row_prepared('SELECT *
		FROM user_auth
		WHERE username = ?
		AND realm = 2',
		array($username));

	if (!$user && get_template_account($username) == 0 && get_guest_account() === 0) {
		$error     = true;
		$error_msg = __esc('%s authenticated by Web Server, but both Template and Guest Users are not defined in Cacti.', $username);

		cacti_log("LOGIN FAILED: User '" . $username . "' authenticated by Web Server, but both Template and Guest Users are not defined in Cacti.  Exiting.", false, 'AUTH');

		auth_display_custom_error_message($error_msg);
		exit;
	}

	return $user;
}

/**
 * local_auth_login_process - login a local account or generate an error
 *   if there is an error, the globals error and error_msg will be set to notify the caller
 *   that error and not to proceed with login.
 *
 * @param  (string) $username - The user to process
 *
 * @return (array)  $user - The valid user information, or empty array if user must be created
 */
function local_auth_login_process($username) {
	$user = array();

	if (!api_plugin_hook_function('login_process', false)) {
		$user = secpass_login_process($username);

		/**
		 * If the password needs to be rehashed for security purposes,
		 * do that now.
		 */
		$stored_pass = db_fetch_cell_prepared('SELECT password
			FROM user_auth
			WHERE username = ?
			AND realm = 0',
			array($username));

		if ($stored_pass != '') {
			$password = get_nfilter_request_var('login_password');

			$valid = compat_password_verify($password, $stored_pass);

			cacti_log("DEBUG: User '" . $username . "' password for rehash is " . ($valid ? '':'in') . 'valid', false, 'AUTH', POLLER_VERBOSITY_DEBUG);

			if ($valid) {
				$user = db_fetch_row_prepared('SELECT *
					FROM user_auth
					WHERE username = ?
					AND realm = 0',
					array($username));

				if (compat_password_needs_rehash($stored_pass, PASSWORD_DEFAULT)) {
					$password = compat_password_hash($password, PASSWORD_DEFAULT);
					db_check_password_length();
					db_execute_prepared('UPDATE user_auth
						SET password = ?
						WHERE username = ?',
						array($password, $username));
				}
			}
		}
	}

	return $user;
}

/**
 * ldap_login_process - login to an LDAP account or generate an error
 *   if there is an error, the globals error and error_msg will be set to notify the caller
 *   that error and not to proceed with login.
 *
 * @param  (string) $username - The user to process
 *
 * @return (array)  $user - The valid user information, or empty array if user must be created
 */
function ldap_login_process($username) {
	global $error, $error_msg;

	$password = get_nfilter_request_var('login_password');

	if ($username == '') {
		$error     = true;
		$error_msg = __('Access Denied!  Login Failed.');

		cacti_log('LOGIN FAILED: Empty LDAP Username provided', false, 'AUTH');

		return array();
	}

	auth_checkclear_lockout($username, 3);

	if (auth_process_lockout_check($username, 3)) {
		return array();
	}

	$user  = array();
	$realm = 3;

	if ($password != '') {
		/* get user DN */
		$ldap_dn_search_response = cacti_ldap_search_dn($username);

		if ($ldap_dn_search_response['error_num'] == '0') {
			$ldap_dn = $ldap_dn_search_response['dn'];
		} else {
			/* error searching */
			$error     = true;
			$error_msg =  __('Access Denied!  LDAP Search Error: %s', $ldap_dn_search_response['error_text']);

			cacti_log('LOGIN FAILED: LDAP Error: ' . $ldap_dn_search_response['error_text'], false, 'AUTH');
		}

		if (!$error) {
			/* auth user with LDAP */
			$ldap_auth_response = cacti_ldap_auth($username, $password, $ldap_dn);

			if ($ldap_auth_response['error_num'] == '0') {
				/* Locate user in database */
				cacti_log("LOGIN: LDAP User '" . $username . "' Authenticated", false, 'AUTH');

				$user = db_fetch_row_prepared('SELECT *
					FROM user_auth
					WHERE username = ?
					AND realm = ?',
					array($username, $realm));
			} else {
				/* error */
				$error     = true;
				$error_msg = __('Access Denied!  LDAP Error: %s', $ldap_auth_response['error_text']);

				cacti_log('LOGIN FAILED: LDAP Error: ' . $ldap_auth_response['error_text'], false, 'AUTH');

				if ($ldap_auth_response['error_num'] == 1) {
					auth_process_lockout($username, $realm);
				}
			}
		}
	} else {
		/* error */
		$error     = true;
		$error_msg = __('Access Denied!  No password provided by user.');

		cacti_log(sprintf('LOGIN FAILED: LDAP No password provided for user %s', $username), false, 'AUTH');

		auth_process_lockout($username, $realm);
	}

	return $user;
}

/**
 * domains_login_process - login to an LDAP domain account or generate an error
 *   if there is an error, the globals error and error_msg will be set to notify the caller
 *   that error and not to proceed with login.
 *
 * @param  (string) $username - The user to process
 *
 * @return (array)  $user - The valid user information, or empty array if user must be created
 */
function domains_login_process($username) {
	global $realm, $error, $error_msg;

	$realm    = get_nfilter_request_var('realm');
	$password = get_nfilter_request_var('login_password');

	if ($username == '') {
		$error     = true;
		$error_msg = __('Access Denied!  Login Failed.');

		cacti_log('LOGIN FAILED: Empty Domains Username provided', false, 'AUTH');

		return array();
	}

	auth_checkclear_lockout($username, $realm);

	if (auth_process_lockout_check($username, $realm)) {
		return array();
	}

	$user = array();

	if ($realm > 3 && $password != '') {
		/* get user DN */
		$ldap_dn_search_response = domains_ldap_search_dn($username, $realm);
		if ($ldap_dn_search_response['error_num'] == '0') {
			$ldap_dn = $ldap_dn_search_response['dn'];
		} else {
			/* error searching */
			$error     = true;
			$error_msg = __('LDAP Search Error: %s', $ldap_dn_search_response['error_text']);

			cacti_log('LOGIN FAILED: LDAP Error: ' . $ldap_dn_search_response['error_text'], false, 'AUTH');
		}

		if (!$error) {
			/* auth user with LDAP */
			$ldap_auth_response = domains_ldap_auth($username, $password, $ldap_dn, $realm);

			if ($ldap_auth_response['error_num'] == '0') {
				/* User ok */
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
					array($realm-1000));

				$template_username = db_fetch_cell_prepared('SELECT username
					FROM user_auth
					WHERE id = ?',
					array($template_user));

				if (!cacti_sizeof($user) && $template_user > 0 && $username != '') {
					cacti_log("NOTE: User '" . $username . "' does not exist, copying template user", false, 'AUTH');

					/* check that template user exists */
					$user_template = db_fetch_row_prepared('SELECT *
						FROM user_auth
						WHERE id = ?',
						array($template_user));

					if (cacti_sizeof($user_template)) {
						/* template user found */
						$cn_full_name = db_fetch_cell_prepared('SELECT cn_full_name
							FROM user_domains_ldap
							WHERE domain_id = ?',
							array($realm-1000));

						$cn_email = db_fetch_cell_prepared('SELECT cn_email
							FROM user_domains_ldap
							WHERE domain_id = ?',
							array($realm-1000));

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
						$error     = true;
						$error_msg = __('Access Denied!  Template user id %s does not exist.  Please contact your Administrator.', $template_user);

						cacti_log("LOGIN FAILED: Template user id '" . $template_user . "' does not exist.", false, 'AUTH');
					}
				}
			} else {
				/* error */
				$error     = true;
				$error_msg = __('Access Denied!  LDAP Error: %s', $ldap_auth_response['error_text']);

				cacti_log('LOGIN FAILED: LDAP Error: ' . $ldap_auth_response['error_text'], false, 'AUTH');

				if ($ldap_auth_response['error_text'] == 1) {
					auth_lockout_process($username, $realm);
				}
			}
		}
	} elseif ($password == '') {
		/* error */
		$error     = true;
		$error_msg = __('Access Denied!  No password provided by user.');

		cacti_log(sprintf('LOGIN FAILED: LDAP No password provided for user %s', $username), false, 'AUTH');

		auth_process_lockout($username, $realm);
	}

	return $user;
}

/**
 * domains_ldap_auth - authentications a LDAP domain login
 *
 * @param  (string) $username  - The user to process
 * @param  (string) $password  - The users password
 * @param  (string) $dn        - The domain name
 * @param  (int)    $realm     - The LDAP Realm number
 *
 * @return (array)  $response - The ldap response of false on a general error
 */
function domains_ldap_auth($username, $password = '', $dn = '', $realm = 0) {
	$ldap = new Ldap;

	if (!empty($username)) $ldap->username = $username;
	if (!empty($password)) $ldap->password = $password;

	$ldap->dn = $dn;

	$ld = db_fetch_row_prepared('SELECT *
		FROM user_domains_ldap
		WHERE domain_id = ?',
		array($realm-1000));

	if (cacti_sizeof($ld)) {
		if (empty($dn) && !empty($ld['dn'])) {
			$ldap->dn = $ld['dn'];
		}

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

/**
 * domains_ldap_search_dn - searches the user dn for existence
 *
 * @param  (string) $username  - The user to process
 * @param  (int)    $realm     - The LDAP Realm number
 *
 * @return (array)  $response - The ldap response, or false on general error
 */
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

/**
 * secpass_login_process - process a local login checking for triggers
 *   such as those that would force a password check and take the appropriate action.
 *   if there is an error, the globals error and error_msg will be set to notify the caller
 *   that error and not to proceed with login.
 *
 * @param  (string) $username  - The user to process
 *
 * @return (array)  $user - The login user or an empty array if the user does not exist
 */
function secpass_login_process($username) {
	global $error, $error_msg;

	$password = get_nfilter_request_var('login_password');

	if ($username == '') {
		$error     = true;
		$error_msg = __('Access Denied!  Login Failed.');

		cacti_log('LOGIN FAILED: Empty Local Username provided', false, 'AUTH');

		return array();
	}

	auth_checkclear_lockout($username, 0);

	if (auth_process_lockout_check($username, 0)) {
		return array();
	}

	if (db_column_exists('user_auth', 'lastfail')) {
		$user = db_fetch_row_prepared("SELECT id, username, lastfail, failed_attempts, `locked`, password
			FROM user_auth
			WHERE username = ?
			AND realm = 0
			AND enabled = 'on'",
			array($username));
	} else {
		$user = db_fetch_row_prepared("SELECT id, username, password
			FROM user_auth
			WHERE username = ?
			AND realm = 0
			AND enabled = 'on'",
			array($username));
	}

	if (cacti_sizeof($user)) {
		if (trim($password) == '') {
			/* error */
			$error     = true;
			$error_msg = __('Access Denied!  No password provided by user.');

			cacti_log(sprintf('LOGIN FAILED: No password provided for user %s', $username), false, 'AUTH');

			$valid_pass = false;
		} else {
			$valid_pass = compat_password_verify($password, $user['password']);
		}

		cacti_log('DEBUG: User \'' . $username . '\' valid password = ' . $valid_pass, false, 'AUTH', POLLER_VERBOSITY_DEBUG);

		if (!$valid_pass) {
			auth_process_lockout($username, 0);

			if (!$error) {
				$error     = true;
				$error_msg = __('Access Denied! Login failed.');
			}

			return array();
		}
	} else {
		/* error */
		$error     = true;
		$error_msg = __('Access Denied!  Login Failed.');

		cacti_log(sprintf('LOGIN FAILED: Invalid user %s specified.', $username), false, 'AUTH');
	}

	/**
	 * Check if old password doesn't meet specifications and must be changed
	 * This only applies to local logins where we store the actual hashed
	 * password.
	 */
	if (read_config_option('secpass_forceold') == 'on') {
		$message = secpass_check_pass($password);

		if ($message != 'ok') {
			db_execute_prepared("UPDATE user_auth
				SET must_change_password = 'on'
				WHERE username = ?
				AND realm = 0
				AND enabled = 'on'",
				array($username));

			$error_msg = __('Your Cacti administrator has forced complex passwords for logins and your current Cacti password does not match the new requirements.  Therefore, you must change your password now.');

			raise_message('forced_password', $error_msg, MESSAGE_LEVEL_INFO);
			header('Location: auth_changepassword.php');
			exit;
		}
	}

	// Set the last Login time
	if (read_config_option('secpass_expireaccount') > 0) {
		db_execute_prepared("UPDATE user_auth
			SET lastlogin = ?
			WHERE username = ?
			AND realm = 0
			AND enabled = 'on'",
			array(time(), $username));
	}

	return $user;
}

/**
 * secpass_check_pass - Validate a given password for various password rules
 *
 * @param  (string) $password - The user password
 *
 * @return (string) Either 'ok', or an error message to present to the user
 */
function secpass_check_pass($password) {
	$minlen = read_config_option('secpass_minlen');
	if (strlen($password) < $minlen) {
		return __('Password must be at least %d characters!', $minlen);
	}

	if (read_config_option('secpass_reqnum') == 'on' &&
		str_replace(array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'), '', $password) == $password
	) {
		return __('Your password must contain at least 1 numerical character!');
	}

	if (read_config_option('secpass_reqmixcase') == 'on' && strtolower($password) == $password) {
		return __('Your password must contain a mix of lower case and upper case characters!');
	}

	if (read_config_option('secpass_reqspec') == 'on' &&
		str_replace(array('~', '`', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '_', '+', '=', '[', '{', ']', '}', ';', ':', '<', ',', '.', '>', '?', '|', '/', '\\'), '', $password) == $password
	) {
		return __('Your password must contain at least 1 special character!');
	}

	if (read_config_option('secpass_pwnedcheck') == 'on') {
		$sha1 = strtoupper(sha1($password));
		$suffix = substr($sha1,5);
		$options = array(
			CURLOPT_RETURNTRANSFER => true,   // return web page
			CURLOPT_HEADER	       => false,  // don't return headers
			CURLOPT_FOLLOWLOCATION => true,   // follow redirects
			CURLOPT_MAXREDIRS      => 10,     // stop after 10 redirects
			CURLOPT_ENCODING       => '',     // handle compressed
			CURLOPT_USERAGENT      => 'test', // name of client
			CURLOPT_AUTOREFERER    => true,   // set referrer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,    // time-out on connect
			CURLOPT_TIMEOUT	       => 120,    // time-out on response
		);

		$ch = curl_init('https://api.pwnedpasswords.com/range/'.substr($sha1,0,5));
		curl_setopt_array($ch, $options);

		$content  = curl_exec($ch);

		curl_close($ch);
		$lines = explode("\r\n", $content);
		$count = 0;
		foreach ($lines as $line) {
			$result = explode(':', $line);
			if ($result[0] == $suffix) {
				$count = $result[1];
			}
		}

		if ($count >= read_config_option('secpass_pwnedcount')) {
			return __('This password appears to be a well known password, please use a different one');
		}
	}

	return 'ok';
}

/**
 * secpass_check_history - Checks for password reuse for local accounts
 *
 * @param  (int)    $id - The user id to check
 * @param  (string) $password  - The user password
 *
 * @return (bool)   True if the user password provided meets history rules
 */
function secpass_check_history($id, $password) {
	$history = intval(read_config_option('secpass_history'));

	if ($history > 0) {
		$user = db_fetch_row_prepared("SELECT password, password_history
			FROM user_auth
			WHERE id = ?
			AND realm = 0
			AND enabled = 'on'",
			array($id));

		if (compat_password_verify($password, $user['password'])) {
			return false;
		}

		$passes = explode('|', $user['password_history']);
		// Double check this incase the password history setting was changed
		while (cacti_count($passes) > $history) {
			array_shift($passes);
		}

		if (!empty($passes)) {
			foreach ($passes as $hash) {
				if (compat_password_verify($password, $hash)) {
					return false;
				}
			}
		}
	}

	return true;
}

/**
 * rsa_check_keypair - Checks that Cacti ras_public_key is present.  If not
 *   it will insert the information into the Cacti database.
 *
 * @return (void)
 */
function rsa_check_keypair() {
	global $config;

	set_include_path($config['include_path'] . '/vendor/phpseclib/');
	include('Crypt/Base.php');
	include('Math/BigInteger.php');
	include('Crypt/Hash.php');
	include('Crypt/RSA.php');
	include('Crypt/Rijndael.php');
	include('Crypt/AES.php');

	$public_key = read_config_option('rsa_public_key');

	if(!$public_key) {
		$rsa = new phpseclib\Crypt\RSA();
		$keys = $rsa->createKey(2048);
		$rsa->loadKey($keys['publickey']);
		$fingerprint = $rsa->getPublicKeyFingerprint();

		db_execute_prepared("INSERT INTO settings
			(`name`, `value`)
			VALUES
			('rsa_public_key', '" . $keys['publickey'] . "'),
			('rsa_private_key', '" . $keys['privatekey'] . "'),
			('rsa_fingerprint', '" . $fingerprint . "')"
		);
	}
}

/**
 * reset_group_perms - sets a flag for all users of a group logged in that their perms
 *   need to be reloaded from the database
 *
 * @param  (int) $group_id - the id of the group to check
 *
 * @return (void)
 */
function reset_group_perms($group_id) {
	$users = array_rekey(db_fetch_assoc_prepared('SELECT user_id
		FROM user_auth_group_members
		WHERE group_id = ?',
		array($group_id)), 'user_id', 'user_id');

	if (cacti_sizeof($users)) {
		db_execute('UPDATE user_auth
			SET reset_perms=FLOOR(RAND() * 4294967295) + 1
			WHERE id IN (' . implode(',', $users) . ')');
	}
}

/**
 * reset_user_perms - sets a flag for all users logged in as this user that their perms
 *   need to be reloaded from the database
 *
 * @param  (int) $user_id - the id of the current user
 *
 * @return (void)
 */
function reset_user_perms($user_id) {
	db_execute_prepared('UPDATE user_auth
		SET reset_perms=FLOOR(RAND() * 4294967295) + 1
		WHERE id = ?',
		array($user_id));

	if ($user_id == $_SESSION['sess_user_id']) {
		kill_session_var('sess_user_realms');
		kill_session_var('sess_user_config_array');
		kill_session_var('sess_config_array');
		kill_session_var('sess_auth_names');
	}
}

/**
 * is_user_perms_valid - checks to see if the admin has changed users permissions
 *
 *  @param  (int)  $user_id - the id of the current user
 *
 *  @return (bool) true if still valid, false otherwise
 */
function is_user_perms_valid($user_id) {
	global $config;

	static $valid = null;
	static $key   = null;

	if ($valid === null && cacti_version_compare($config['cacti_db_version'], '1.0.0', '>=')) {
		$key = db_fetch_cell_prepared('SELECT reset_perms
			FROM user_auth
			WHERE id = ?',
			array($user_id));
	}

	if (isset($_SESSION['sess_user_perms_key'])) {
		if ($key != $_SESSION['sess_user_perms_key']) {
			$valid = false;
		} else {
			$valid = true;
		}
	} else {
		$valid = true;
	}

	$_SESSION['sess_user_perms_key'] = $key;

	return $valid;
}

/**
 * compat_password_verify - if the secure function exists, verify against that
 *   first.  If that checks fails or does not exist, check against older md5
 *   version
 *
 * @param  (string) $password - password to verify
 * @param  (string) $hash     - current password hash
 *
 * @return (bool)   true if password hash matches, false otherwise
 */
function compat_password_verify($password, $hash) {
	if (function_exists('password_verify')) {
		if (password_verify($password, $hash)) {
			return true;
		}
	}

	$md5 = md5($password);

	return ($md5 == $hash);
}

/**
 * compat_password_hash - if the secure function exists, hash using that.
 *   If that does not exist, hash older md5 function instead
 *
 * @param  (string) $password - password to hash
 * @param  (string) $algo     - algorithm to use (PASSWORD_DEFAULT)
 *
 * @return (bool)   true if password hash matches, false otherwise
 */
function compat_password_hash($password, $algo, $options = array()) {
	if (function_exists('password_hash')) {
		// Check if options array has anything, only pass when required
		return (cacti_sizeof($options) > 0) ?
			password_hash($password, $algo, $options) :
			password_hash($password, $algo);
	}

	return md5($password);
}

/**
 * compat_password_needs_rehash - if the secure function exists, check hash
 *   using that. If that does not exist, return false as md5 doesn't need a
 *   rehash
 *
 * @param  (string) $password - password to hash
 * @param  (string) $algo     - algorithm to use (PASSWORD_DEFAULT)
 *
 * @return (bool)   true if password hash needs changing, false otherwise
 */
function compat_password_needs_rehash($password, $algo, $options = array()) {
	if (function_exists('password_needs_rehash')) {
		// Check if options array has anything, only pass when required
		return (cacti_sizeof($options) > 0) ?
			password_needs_rehash($password, $algo, $options) :
			password_needs_rehash($password, $algo);
	}

	return true;
}

/**
 * auth_user_has_access - Verify that the user account has some access to cacti
 *
 * @param  (int)  $user - The user id of the account to check
 *
 * @return (bool) True if the user has access false otherwise
 */
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

/**
 * auth_display_custom_error_message - displays a custom error message to the browser that looks like
 *   the pre-defined error messages
 *
 * @param  (string) $message - the actual text of the error message to display
 *
 * @return (void)
 */
function auth_display_custom_error_message($message) {
	global $config;

	$auth_method = read_config_option('auth_method');

	if ($auth_method == AUTH_METHOD_BASIC) {
		$custom_message = read_config_option('basic_auth_fail_message');
	} else {
		$custom_message = '';
	}

	/* kill the session */
	cacti_cookie_logout();

	/* print error */
	print '<!DOCTYPE html>';
	print '<html>';
	print '<head>';

	html_common_header(__('Cacti Login Failure'));

	print '</head>';
	print '<body><center>';
	print '<div class="ui-state-error ui-corner-all" style="width:50%;margin-left:auto;margin-right:auto;margin-top:200px;padding:20px"><p>' . $message . '</p><p>' . $custom_message . '</p></div>';

	if ($auth_method != AUTH_METHOD_BASIC) {
		print '<div class="ui-corner-all" style="width:50%;margin:auto;padding:20px"><a href="index.php">' . __('Login Again') . '</a></div><script type="text/javascript">$(function() { $("a").button(); });</script>';
	}

	print '</center></body></html>';
}

/**
 * auth_login_redirect - provide default page re-direction when a user first logs in.
 *
 * @param  (string|array) $login_opts - optional array of user details
 *
 * @return (void)
 */
function auth_login_redirect($login_opts = '') {
	global $config;

	if ($login_opts == '') {
		$login_opts = db_fetch_cell_prepared('SELECT login_opts
			FROM user_auth
			WHERE id = ?',
			array($_SESSION['sess_user_id']));
	}

	$newtheme = false;
	if (user_setting_exists('selected_theme', $_SESSION['sess_user_id']) && read_config_option('selected_theme') != read_user_setting('selected_theme')) {
		unset($_SESSION['selected_theme']);
		$newtheme = true;
	}

	// Decide what to do with an authenticated user
	switch ($login_opts) {
		case '1': /* referer */
			/**
			 * because we use plugins, we can't redirect back to graph_view.php if they don't
			 * have console access
			 */
			if (isset($_SERVER['REDIRECT_URL'])) {
				$referer = sanitize_uri($_SERVER['REDIRECT_URL']);

				if (isset($_SERVER['REDIRECT_QUERY_STRING'])) {
					$referer .= '?' . $_SERVER['REDIRECT_QUERY_STRING'];
				}

				cacti_log(sprintf("DEBUG: Referer from REDIRECT_URL with Value: '%s', Effective: '%s'", $_SERVER['REDIRECT_URL'], $referer), false, 'AUTH', POLLER_VERBOSITY_DEBUG);
			} elseif (isset($_SERVER['HTTP_REFERER'])) {
				$referer = $_SERVER['HTTP_REFERER'];

				if (auth_basename($referer) == 'logout.php') {
					$referer = $config['url_path'] . 'index.php';
				} elseif (strpos($referer, $config['url_path']) === false) {
					if (!is_realm_allowed(8)) {
						$referer = $config['url_path'] . 'graph_view.php' . ($newtheme ? '?newtheme=1':'');
					} else {
						$referer = $config['url_path'] . 'index.php' . ($newtheme ? '?newtheme=1':'');
					}
				}

				cacti_log(sprintf("DEBUG: Referer from HTTP_REFERER with Value: '%s', Effective: '%s'", $_SERVER['HTTP_REFERER'], $referer), false, 'AUTH', POLLER_VERBOSITY_DEBUG);
			} elseif (isset($_SERVER['REQUEST_URI'])) {
				$referer = sanitize_uri($_SERVER['REQUEST_URI']);

				if (auth_basename($referer) == 'logout.php') {
					$referer = $config['url_path'] . 'index.php';
				}

				cacti_log(sprintf("DEBUG: Referer from REQUEST_URI with Value: '%s', Effective: '%s'", $_SERVER['REQUEST_URI'], $referer), false, 'AUTH', POLLER_VERBOSITY_DEBUG);
			} else {
				$referer = $config['url_path'] . 'index.php';

				cacti_log(sprintf("DEBUG: Referer Short Circuit to '%s'", 'index.php'), false, 'AUTH', POLLER_VERBOSITY_DEBUG);
			}

			$referer .= ($newtheme ? (strpos($referer, '?') === false ? '?':'&') . 'newtheme=1':'');

			/* Strip out the login from the referer if present */
			$referer  = str_replace('?action=login', '', $referer);

			if (api_user_realm_auth(auth_basename($referer))) {
				header('Location: ' . $referer);
			} elseif (!is_realm_allowed(8)) {
				cacti_log(sprintf("DEBUG: Referer Overridden Due to Permissions to '%s'", 'graph_view.php'), false, 'AUTH', POLLER_VERBOSITY_DEBUG);

				header('Location: graph_view.php');
			} else {
				cacti_log(sprintf("DEBUG: Referer Overridden Due to Permissions to '%s'", 'index.php'), false, 'AUTH', POLLER_VERBOSITY_DEBUG);

				header('Location: index.php');
			}

			break;
		case '2': /* default console page */
			if (!is_realm_allowed(8)) {
				header('Location: ' . $config['url_path'] . 'graph_view.php' . ($newtheme ? '?newtheme=1':''));
			} else {
				header('Location: ' . $config['url_path'] . 'index.php' . ($newtheme ? '?newtheme=1':''));
			}

			break;
		case '3': /* default graph page */
			header('Location: ' . $config['url_path'] . 'graph_view.php' . ($newtheme ? '?newtheme=1':''));

			break;
		default:
			api_plugin_hook_function('login_options_navigate', $login_opts);
	}

	exit;
}

/**
 * auth_basename - provides a URL knowledgable basename function
 *
 * @param  (string) $referer - a URL that will included a basename
 *
 * @return (string) the file name without the arguments
 */
function auth_basename($referer) {
	$parts = explode('?', $referer);

	return basename($parts[0]);
}

/**
 * auth_login_create_user_from_template - creates a new user account from a template account
 *   if there is an error that would block login, the function set's the globals
 *   error and error_msg to inform the caller not to proceed with the login.
 *   in special cases, such as basic auth, the function will print out a custom
 *   error message and exit.
 *
 * @param  (string) $username - The username to use for the copy
 * @param  (int)    $realm - The login realm to use for the copy
 *
 * @return (array|void)  The copied new user account details or void on exit
 */
function auth_login_create_user_from_template($username, $realm) {
	global $error, $error_msg;

	cacti_log("NOTE: User '" . $username . "' does not exist, copying template user", false, 'AUTH');

	$user = array();

	$user_template = db_fetch_row_prepared('SELECT *
		FROM user_auth
		WHERE id = ?',
		array(get_template_account($username)));

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

					user_copy($user_template['username'], $username, $user_template['realm'], $realm, false, $data_override);
				} else {
					$ldap_response = (isset($ldap_cn_search_response[0]) ? $ldap_cn_search_response[0] : '(no response given)');
					$ldap_code = (isset($ldap_cn_search_response['error_num']) ? $ldap_cn_search_response['error_num'] : '(no code given)');
					cacti_log('LOGIN: Email Address and Full Name fields not found, reason: ' . $ldap_response . 'code: ' . $ldap_code, false, 'AUTH');
					user_copy($user_template['username'], $username, $user_template['realm'], $realm);
				}
			} else {
				user_copy($user_template['username'], $username, $user_template['realm'], $realm);
			}
		} else {
			user_copy($user_template['username'], $username, $user_template['realm'], $realm);
		}

		/* requery newly created user */
		$user = db_fetch_row_prepared('SELECT *
			FROM user_auth
			WHERE username = ?
			AND realm = ?',
			array($username, $realm));
	} else {
		/* error */
		$error     = true;
		$error_msg = __('Access Denied!  Template user id %s does not exist.  Please contact your Administrator.', read_config_option('user_template'));

		cacti_log("LOGIN FAILED: Template user id '" . read_config_option('user_template') . "' does not exist.", false, 'AUTH');

		if (read_config_option('auth_method') == AUTH_METHOD_BASIC) {
			auth_display_custom_error_message($error_msg);
			exit;
		}
	}

	return $user;
}

/**
 * check_reset_no_authentication - Attempts to switch Cacti from No Authentication to Local
 *   authentication, or generate an error on failure through the globals error, and error_msg.
 *
 * @param  (int)  $auth_method - The current auth method
 *
 * @return (bool) Returns false on failure to set user account, otherwise redirects
 */
function check_reset_no_authentication($auth_method) {
	global $error, $error_msg, $config;

	if ($auth_method == AUTH_METHOD_NONE) {
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
			$error     = true;
			$error_msg = __('Authentication was previously not set.  Attempted to set to Local Authentication, but no Administrative account was found.');

			return false;
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

		$auth_method = AUTH_METHOD_CACTI;
		set_config_option('auth_method', $auth_method, true);

		$_SESSION['sess_user_id'] = $admin_id;
		$_SESSION['sess_change_password'] = true;
		header('Location: ' . $config['url_path'] . 'auth_changepassword.php?action=force&ref=' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php'));
		exit;
	}
}

function disable_2fa($user_id) {
	$current_user = db_fetch_row_prepared(
		'SELECT *
		FROM user_auth
		WHERE id = ?',
		array($user_id)
	);

	$result = array('status' => 500, 'text' => __('Unknown error'));
	if (!cacti_sizeof($current_user)) {
		$result['status'] = 404;
		$result['text'] = __('ERROR: Unable to find user');
	} else {
		db_execute_prepared('UPDATE user_auth SET tfa_enabled = \'\', tfa_secret = \'\' WHERE id = ?', array($user_id));

		$current_user = db_fetch_row_prepared(
			'SELECT *
			FROM user_auth
			WHERE id = ?',
			array($_SESSION['sess_user_id'])
		);

		if ($current_user['tfa_enabled'] != '') {
			$result['status'] = '501';
			$result['text'] = __('2FA failed to be disabled');
		} else {
			$result['status'] = 200;
			$result['text'] = __('2FA is now disabled');
		}
	}

	return json_encode($result);
}

function enable_2fa($user_id) {
	$current_user = db_fetch_row_prepared(
		'SELECT *
		FROM user_auth
		WHERE id = ?',
		array($user_id)
	);

	$result = array('status' => 500, 'text' => __('Unknown error'));
	if (!cacti_sizeof($current_user)) {
		$result['status'] = 404;
		$result['text'] = __('ERROR: Unable to find user');
	} else {
		$g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
		$secret = $g->generateSecret();
		db_execute_prepared('UPDATE user_auth SET tfa_secret = ? WHERE id = ?', array($secret, $user_id));

		$current_user = db_fetch_row_prepared(
			'SELECT *
			FROM user_auth
			WHERE id = ?',
			array($_SESSION['sess_user_id'])
		);

		if ($current_user['tfa_secret'] != $secret) {
			$result['status'] = '501';
			$result['text'] = __('2FA secret failed to be generated/updated');
		} else {
			$result['status'] = 200;
			$result['text'] = __('2FA secret has needs verification');
			$result['link'] = \Sonata\GoogleAuthenticator\GoogleQrUrl::generate($current_user['username'] . '@' . $_SERVER['HTTP_HOST'], $current_user['tfa_secret'], 'Cacti');
		}
	}

	return json_encode($result);
}

function verify_2fa($user_id, $code) {
	$current_user = db_fetch_row_prepared(
		'SELECT *
		FROM user_auth
		WHERE id = ?',
		array($user_id)
	);

	$result = array('status' => 500, 'text' => __('Unknown error'));
	if (!cacti_sizeof($current_user)) {
		$result['status'] = 404;
		$result['text'] = __('ERROR: Unable to find user');
	} else {
		$result['secret'] = $current_user['tfa_secret'];
		$g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
		$isValid = $g->checkCode($current_user['tfa_secret'], $code);

		if (!$isValid) {
			$result['status'] = 301;
			$result['text'] = __('ERROR: Code was not verified, please try again');
		} else {
			db_execute_prepared('UPDATE user_auth SET tfa_enabled = ? WHERE id = ?', array('on', $user_id));

			$result['status'] = 200;
			$result['text'] = __('2FA has been enabled and verified');
		}
	}
	return json_encode($result);
}

function is_2fa_enabled($user_id) {
	$current_user = db_fetch_row_prepared(
		'SELECT *
		FROM user_auth
		WHERE id = ?',
		array($user_id)
	);

	return isset($current_user['2fa_enabled']) && ($current_user['2fa_enabled'] != '');
}
