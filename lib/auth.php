<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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

/* clear_auth_cookie - clears a users security token
 * @return - NULL */
function clear_auth_cookie() {
	global $config;

	if (isset($_COOKIE['cacti_remembers']) && read_config_option('auth_cache_enabled') == 'on') {
		$parts = explode(',', $_COOKIE['cacti_remembers']);
		$user  = $parts[0];

		if ($user != '') {
			$user_id = db_fetch_cell_prepared('SELECT id
				FROM user_auth
				WHERE username = ?',
				array($user));

			if (!empty($user_id)) {
				if (isset($parts[1])) {
					$secret = hash('sha512', $parts[1], false);
					cacti_cookie_session_logout();
					db_execute_prepared('DELETE FROM user_auth_cache
						WHERE user_id = ?
						AND token = ?',
						array($user_id, $secret));
				}
			}
		}
	}
}

/* set_auth_cookie - sets a users security token
 * @arg - (string) $user - The user_auth row for the user
 * @return - (boolean) True if token set worked, otherwise false */
function set_auth_cookie($user) {
	global $config;

	if (db_table_exists('user_auth_cache')) {
		clear_auth_cookie();

		$nssecret = md5($_SERVER['REQUEST_TIME'] .  mt_rand(10000,10000000)) . md5(get_client_addr(''));

		$secret = hash('sha512', $nssecret, false);

		db_execute_prepared('REPLACE INTO user_auth_cache
			(user_id, hostname, last_update, token)
			VALUES
			(?, ?, NOW(), ?);',
			array($user['id'], get_client_addr(''), $secret));

		cacti_cookie_session_set($user['username'], $nssecret);
	}
}

/* check_auth_cookie - clears a users security token
 * @return - (int) The user of the session cookie, otherwise false */
function check_auth_cookie() {
	if (isset($_COOKIE['cacti_remembers']) &&
		read_config_option('auth_cache_enabled') == 'on' &&
		db_table_exists('user_auth_cache')) {

		$parts = explode(',', $_COOKIE['cacti_remembers']);
		$user  = $parts[0];

		if ($user != '') {
			$user_info = db_fetch_row_prepared('SELECT id, username
				FROM user_auth
				WHERE username = ?',
				array($user)
			);

			if (!empty($user_info)) {
				if (isset($parts[1])) {
					$secret = hash('sha512', $parts[1], false);

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
							array($user, $user_info['id'], get_client_addr(''))
						);

						return $user_info['id'];
					}
				}
			}
		}
	}

	return false;
}

/* user_copy - copies user account
   @arg $template_user - username of the user account that should be used as the template
   @arg $new_user - new username of the account to be created/overwritten
   @arg $new_realm - new realm of the account to be created, overwrite not affected, but is used for lookup
   @arg $overwrite - Allow overwrite of existing user, preserves username, fullname, password and realm
   @arg $data_override - Array of user_auth field and values to override on the new user
   @return - the new users id, or false on no copy */
function user_copy($template_user, $new_user, $template_realm = 0, $new_realm = 0, $overwrite = false, $data_override = array()) {

	/* ================= input validation ================= */
	input_validate_input_number($template_realm);
	input_validate_input_number($new_realm);
	/* ==================================================== */

	/* Check get template users array */
	$user_auth = db_fetch_row_prepared('SELECT *
		FROM user_auth
		WHERE username = ?
		AND realm = ?',
		array($template_user, $template_realm));

	if (!isset($user_auth)) {
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


/* user_remove - remove a user account
   @arg $user_id - Id os the user account to remove */
function user_remove($user_id) {
	/* ================= input validation ================= */
	input_validate_input_number($user_id);
	/* ==================================================== */

	/* check for guest or template user */
	$username = db_fetch_cell_prepared('SELECT username
		FROM user_auth
		WHERE id = ?',
		array($user_id));

	if ($username != get_nfilter_request_var('username')) {
		if ($user_id == get_template_account()) {
			raise_message(21);
			return;
		}

		if ($user_id == get_guest_account()) {
			raise_message(21);
			return;
		}
	}

	db_execute_prepared('DELETE FROM user_auth WHERE id = ?', array($user_id));
	db_execute_prepared('DELETE FROM user_auth_realm WHERE user_id = ?', array($user_id));
	db_execute_prepared('DELETE FROM user_auth_perms WHERE user_id = ?', array($user_id));
	db_execute_prepared('DELETE FROM user_auth_group_members WHERE user_id = ?', array($user_id));
	db_execute_prepared('DELETE FROM settings_user WHERE user_id = ?', array($user_id));
	db_execute_prepared('DELETE FROM settings_tree WHERE user_id = ?', array($user_id));

	api_plugin_hook_function('user_remove', $user_id);
}

/* user_disable - disable a user account
   @arg $user_id - Id of the user account to disable */
function user_disable($user_id) {
	/* ================= input validation ================= */
	input_validate_input_number($user_id);
	/* ==================================================== */

	db_execute_prepared("UPDATE user_auth SET enabled = '' WHERE id = ?", array($user_id));

	reset_user_perms($user_id);
}

/* user_enable - enable a user account
   @arg $user_id - Id of the user account to enable */
function user_enable($user_id) {
	/* ================= input validation ================= */
	input_validate_input_number($user_id);
	/* ==================================================== */

	db_execute_prepared("UPDATE user_auth SET enabled = 'on' WHERE id = ?", array($user_id));

	reset_user_perms($user_id);
}

/* get_auth_realms - return a list of system user authentication realms */
function get_auth_realms($login = false) {
	if (read_config_option('auth_method') == 4) {
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

	return array(
		'0' => __('Local'),
		'3' => __('LDAP'),
		'2' => __('Web Basic')
	);
}

/* get_graph_permissions_sql - creates SQL that reprents the current graph, host and graph
     template policies
   @arg $policy_graphs - (int) the current graph policy
   @arg $policy_hosts - (int) the current host policy
   @arg $policy_graph_templates - (int) the current graph template policy
   @returns - an SQL "where" statement */
function get_graph_permissions_sql($policy_graphs, $policy_hosts, $policy_graph_templates) {
	$sql = '';
	$sql_or = '';
	$sql_and = '';
	$sql_policy_or = '';
	$sql_policy_and = '';

	if ($policy_graphs == '1') {
		$sql_policy_and .= "$sql_and(user_auth_perms.type != 1 OR user_auth_perms.type IS NULL)";
		$sql_and = ' AND ';
	} elseif ($policy_graphs == '2') {
		$sql_policy_or .= "$sql_or(user_auth_perms.type = 1 OR user_auth_perms.type IS NOT NULL)";
		$sql_or = ' OR ';
	}

	if ($policy_hosts == '1') {
		$sql_policy_and .= "$sql_and((user_auth_perms.type != 3) OR (user_auth_perms.type IS NULL))";
		$sql_and = ' AND ';
	} elseif ($policy_hosts == '2') {
		$sql_policy_or .= "$sql_or((user_auth_perms.type = 3) OR (user_auth_perms.type IS NOT NULL))";
		$sql_or = ' OR ';
	}

	if ($policy_graph_templates == '1') {
		$sql_policy_and .= "$sql_and((user_auth_perms.type != 4) OR (user_auth_perms.type IS NULL))";
	} elseif ($policy_graph_templates == '2') {
		$sql_policy_or .= "$sql_or((user_auth_perms.type = 4) OR (user_auth_perms.type IS NOT NULL))";
	}

	$sql_and = '';

	if (!empty($sql_policy_or)) {
		$sql_and = 'AND ';
		$sql .= $sql_policy_or;
	}

	if (!empty($sql_policy_and)) {
		$sql .= "$sql_and$sql_policy_and";
	}

	if (empty($sql)) {
		return '';
	} else {
		return '(' . $sql . ')';
	}
}

/* is_graph_allowed - determines whether the current user is allowed to view a certain graph
   @arg $local_graph_id - (int) the ID of the graph to check permissions for
   @returns - (bool) whether the current user is allowed the view the specified graph or not */
function is_graph_allowed($local_graph_id, $user = 0) {
	$rows  = 0;

	get_allowed_graphs('', '', '', $rows, $user, $local_graph_id);

	return ($rows > 0);
}

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

function auth_augment_roles($role_name, $files) {
	global $user_auth_roles, $user_auth_realm_filenames;

	foreach($files as $file) {
		if (array_search($file, $user_auth_realm_filenames) !== false) {
			if (array_search($user_auth_realm_filenames[$file], $user_auth_roles[$role_name]) === false) {
				$user_auth_roles[$role_name][] = $user_auth_realm_filenames[$file];
			}
		} else {
			$realm_id = db_fetch_cell_prepared('SELECT id+100 AS realm
				FROM plugin_realms
				WHERE file LIKE ?',
				array('%' . $file . '%'));

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

/* is_tree_allowed - determines whether the current user is allowed to view a certain graph tree
   @arg $tree_id - (int) the ID of the graph tree to check permissions for
   @returns - (bool) whether the current user is allowed the view the specified graph tree or not */
function is_tree_allowed($tree_id, $user = 0) {
	if ($user == -1) {
		return true;
	}

	if (read_config_option('auth_method') != 0) {
		if ($user == 0) {
			if (isset($_SESSION['sess_user_id'])) {
				$user = $_SESSION['sess_user_id'];
			} else {
				return array();
			}
		}

		$policy = db_fetch_cell_prepared('SELECT policy_trees
			FROM user_auth
			WHERE id = ?',
			array($user));

		$trees  = db_fetch_assoc_prepared('SELECT user_id
			FROM user_auth_perms
			WHERE user_id = ?
			AND type = 2
			AND item_id = ?',
			array($user, $tree_id));

		if (auth_check_perms($trees, $policy)) {
			return true;
		}

		/* check for group perms */
		$groups = db_fetch_assoc_prepared("SELECT uag.policy_trees
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on'
			AND uagm.user_id = ?",
			array($user));

		if (!cacti_sizeof($groups)) {
			return false;
		}

		foreach ($groups as $g) {
			if (auth_check_perms($trees, $g['policy_trees'])) {
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
			array($user, $tree_id));

		foreach ($groups as $g) {
			if (auth_check_perms($gtrees, $g['policy_trees'])) {
				return true;
			}
		}

		return false;
	} else {
		return true;
	}
}

/* is_device_allowed - determines whether the current user is allowed to view a certain device
   @arg $host_id - (int) the ID of the device to check permissions for
   @returns - (bool) whether the current user is allowed the view the specified device or not */
function is_device_allowed($host_id, $user = 0) {
	$total_rows = -2;
	get_allowed_devices('', '', '', $total_rows, $user, $host_id);
	return ($total_rows > 0);
}

/* is_graph_template_allowed - determines whether the current user is allowed to view a certain graph template
   @arg $graph_template_id - (int) the ID of the graph template to check permissions for
   @returns - (bool) whether the current user is allowed the view the specified graph template or not */
function is_graph_template_allowed($graph_template_id, $user = 0) {
	$total_rows = -2;
	get_allowed_graph_templates('', '', '', $total_rows, $user, $graph_template_id);

	return ($total_rows > 0);
}

/* is_view_allowed - Returns a true or false as to wether or not a specific view type is allowed
 *                   View options include 'show_tree', 'show_list', 'show_preview', 'graph_settings'
 */
function is_view_allowed($view = 'show_tree') {
	if (read_config_option('auth_method') != 0) {
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
		} else {
			$value = db_fetch_cell_prepared("SELECT $view
				FROM user_auth
				WHERE id = ?",
				array($_SESSION['sess_user_id'])
			);

			return ($value == 'on');
		}
	} else {
		return true;
	}
}

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

	if (cacti_sizeof($graphs) && cacti_sizeof(get_allowed_graphs('gl.id IN(' . implode(',', $graphs) . ')')) > 0) {
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

function is_realm_allowed($realm) {
	global $config;

	/* list all realms that this user has access to */
	if (read_config_option('auth_method') != 0) {
		if (!isset($_SESSION['sess_user_id'])) {
			return false;
		}

		if (!is_user_perms_valid($_SESSION['sess_user_id'])) {
			if (db_table_exists('user_auth_cache')) {
				$enabled = db_fetch_cell_prepared('SELECT enabled
					FROM user_auth
					WHERE id = ?',
					array($_SESSION['sess_user_id']));

				if ($enabled == '' && get_guest_account() != $_SESSION['sess_user_id']) {
					db_execute_prepared('DELETE FROM user_auth_cache
						WHERE user_id = ?',
						array($_SESSION['sess_user_id']));

					kill_session_var('sess_user_id');
					kill_session_var('sess_user_realms');
					kill_session_var('sess_user_config_array');
					kill_session_var('sess_config_array');

					print '<span style="display:none;">cactiLoginSuspend</span>';
					exit;
				}
			} else {
				kill_session_var('sess_user_realms');
				kill_session_var('sess_user_config_array');
				kill_session_var('sess_config_array');
			}
		}

		if (isset($_SESSION['sess_user_realms'][$realm])) {
			return true;
		}

		if (read_config_option('auth_method') != 0) {
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
					array($_SESSION['sess_user_id'], $realm, $realm, $_SESSION['sess_user_id']));
			} else {
				$user_realm = db_fetch_cell_prepared('SELECT realm_id
					FROM user_auth_realm
					WHERE user_id = ?
					AND realm_id = ?',
					array($_SESSION['sess_user_id'], $realm));
			}

			if (!empty($user_realm)) {
				$_SESSION['sess_user_realms'][$realm] = $realm;
				return true;
			} else {
				return false;
			}
		} else {
			$_SESSION['sess_user_realms'][$realm] = $realm;
		}
	} else {
		return true;
	}
}

function get_allowed_tree_level($tree_id, $parent_id, $editing = false, $user = 0) {
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
					if (!is_device_allowed($item['host_id'], $user)) {
						unset($items[$i]);
					}
				} elseif($item['local_graph_id'] > 0) {
					if (!is_graph_allowed($item['local_graph_id'], $user)) {
						unset($items[$i]);
					}
				}

				$i++;
			}
		}
	}

	return $items;
}

function get_allowed_tree_content($tree_id, $parent = 0, $sql_where = '', $order_by = '', $limit = '', &$total_rows = 0, $user = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if (!is_numeric($tree_id)) {
		return array();
	}

	if (!is_numeric($parent)) {
		return array();
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
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
		get_allowed_trees(false, false, '', '', '', $total_rows, $user),
		'id', 'name'
	);

	if ($tree_id > 0) {
		if (cacti_sizeof($trees)) {
			$sql_where .= ' AND gt.id IN (' . implode(', ', array_keys($trees)) . ')';
		}

		$heirarchy = db_fetch_assoc("SELECT gti.graph_tree_id AS tree_id, gti.id, gti.title, gti.host_id, gti.site_id,
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
		$heirarchy = db_fetch_assoc("SELECT gt.id AS tree_id, '0' AS id, gt.name AS title, '0' AS host_id, '0' AS site_id,
			'0' AS local_graph_id, '1' AS host_grouping_type, '' AS hostname, '' AS sitename
			FROM graph_tree AS gt
			WHERE enabled='on'
			AND gt.id IN (" . implode(', ', array_keys($trees)) . ")
			ORDER BY gt.sequence");
	}

	if (read_config_option('auth_method') != 0) {
		$new_heirarchy = array();
		if (cacti_sizeof($heirarchy)) {
			foreach($heirarchy as $h) {
				if ($h['host_id'] > 0) {
					if (is_device_allowed($h['host_id'])) {
						$new_heirarchy[] = $h;
					}
				} elseif ($h['id'] == 0) {
					if (!is_tree_branch_empty($h['tree_id'], $h['id'])) {
						if (is_tree_allowed($h['tree_id'])) {
							$new_heirarchy[] = $h;
						}
					}
				} elseif ($h['site_id'] > 0) {
					$new_heirarchy[] = $h;
				} elseif (!is_tree_branch_empty($h['tree_id'], $h['id'])) {
					$new_heirarchy[] = $h;
				}
			}
		}

		return $new_heirarchy;
	} else {
		return $heirarchy;
	}
}

function get_allowed_tree_header_graphs($tree_id, $leaf_id = 0, $sql_where = '', $order_by = 'gti.position', $limit = '', &$total_rows = 0, $user = 0) {
	if (!is_numeric($tree_id)) {
		return array();
	}

	if (!is_numeric($leaf_id)) {
		return array();
	}

	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($sql_where != '') {
		$sql_where = " AND ($sql_where)";
	}

	$sql_where = "WHERE (gti.graph_tree_id=$tree_id AND gti.parent=$leaf_id)" . $sql_where;

	if ($user == -1) {
		$auth_method = 0;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method != 0) {
		if ($user == 0) {
			if (isset($_SESSION['sess_user_id'])) {
				$user = $_SESSION['sess_user_id'];
			} else {
				return array();
			}
		}

		if (read_config_option('graph_auth_method') == 1) {
			$sql_operator = 'OR';
		} else {
			$sql_operator = 'AND';
		}

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc_prepared("SELECT uag.id, 'group' AS type,
			uag.policy_graphs, uag.policy_hosts, uag.policy_graph_templates
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on'
			AND uagm.user_id = ?",
			array($user));

		$policies[] = db_fetch_row_prepared("SELECT id, 'user' AS type,
			policy_graphs, policy_hosts, policy_graph_templates
			FROM user_auth
			WHERE id = ?",
			array($user));

		$i          = 0;
		$sql_having = '';
		$sql_select = '';
		$sql_join   = '';

		foreach($policies as $policy) {
			if ($policy['policy_graphs'] == 1) {
				$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NULL";
			} else {
				$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NOT NULL";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_hosts'] == 1) {
				$sql_having .= " OR (user$i IS NULL";
			} else {
				$sql_having .= " OR (user$i IS NOT NULL";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_graph_templates'] == 1) {
				$sql_having .= " $sql_operator user$i IS NULL))";
			} else {
				$sql_having .= " $sql_operator user$i IS NOT NULL))";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		$sql_having = "HAVING $sql_having";

		$graphs = db_fetch_assoc("SELECT gti.id, gti.title, gtg.local_graph_id,
			h.description, gt.name AS template_name, gtg.title_cache,
			gtg.width, gtg.height, gl.snmp_index, gl.snmp_query_id,
			$sql_select
			FROM graph_templates_graph AS gtg
			INNER JOIN graph_local AS gl
			ON gl.id = gtg.local_graph_id
			INNER JOIN graph_tree_items AS gti
			ON gti.local_graph_id = gl.id
			LEFT JOIN graph_templates AS gt
			ON gt.id = gl.graph_template_id
			LEFT JOIN host AS h
			ON h.id = gl.host_id
			$sql_join
			$sql_where
			$sql_having
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(*)
			FROM (
				SELECT $sql_select
				FROM graph_templates_graph AS gtg
				INNER JOIN graph_local AS gl
				ON gl.id = gtg.local_graph_id
				INNER JOIN graph_tree_items AS gti
				ON gti.local_graph_id = gl.id
				LEFT JOIN graph_templates AS gt
				ON gt.id = gl.graph_template_id
				LEFT JOIN host AS h
				ON h.id = gl.host_id
				$sql_join
				$sql_where
				$sql_having
			) AS rower");
	} else {
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
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(*)
			FROM graph_templates_graph AS gtg
			INNER JOIN graph_local AS gl
			ON gl.id=gtg.local_graph_id
			INNER JOIN graph_tree_items AS gti
			ON gti.local_graph_id=gl.id
			LEFT JOIN graph_templates AS gt
			ON gt.id=gl.graph_template_id
			LEFT JOIN host AS h
			ON h.id=gl.host_id
			$sql_where");
	}

	return $graphs;
}

function get_allowed_graphs($sql_where = '', $order_by = 'gtg.title_cache', $limit = '', &$total_rows = 0, $user = 0, $graph_id = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($graph_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' ') . " gl.id = $graph_id";
	}

	if (read_user_setting('hide_disabled') == 'on') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . '(IFNULL(h.disabled,"")="")';
	}

	if ($sql_where != '') {
		$sql_where = "WHERE IFNULL(h.deleted,'') = '' AND $sql_where";
	} else {
		$sql_where = "WHERE IFNULL(h.deleted,'') = ''";
	}

	if ($user == -1) {
		$auth_method = 0;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method != 0) {
		if ($user == 0) {
			if (isset($_SESSION['sess_user_id'])) {
				$user = $_SESSION['sess_user_id'];
			} else {
				return array();
			}
		}

		if (read_config_option('graph_auth_method') == 1) {
			$sql_operator = 'OR';
		} else {
			$sql_operator = 'AND';
		}

		/* get policies for all groups and user */
		$policies = db_fetch_assoc_prepared("SELECT uag.id,
			'group' AS type, uag.policy_graphs, uag.policy_hosts, uag.policy_graph_templates
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on'
			AND uagm.user_id = ?",
			array($user));

		$policies[] = db_fetch_row_prepared("SELECT id, 'user' AS type, policy_graphs,
			policy_hosts, policy_graph_templates
			FROM user_auth
			WHERE id = ?",
			array($user));

		$i          = 0;
		$sql_having = '';
		$sql_select = '';
		$sql_join   = '';

		foreach($policies as $policy) {
			if ($policy['policy_graphs'] == 1) {
				$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NULL";
			} else {
				$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NOT NULL";
			}

			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '' : 'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_hosts'] == 1) {
				$sql_having .= " OR (user$i IS NULL";
			} else {
				$sql_having .= " OR (user$i IS NOT NULL";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '' : 'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_graph_templates'] == 1) {
				$sql_having .= " $sql_operator user$i IS NULL))";
			} else {
				$sql_having .= " $sql_operator user$i IS NOT NULL))";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '' : 'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		$sql_having = "HAVING $sql_having";

		$graphs_sql = "SELECT gtg.local_graph_id, h.description, gt.name AS template_name,
			gtg.title_cache, gtg.width, gtg.height, gl.snmp_index, gl.snmp_query_id,
			IF(gl.graph_template_id=0, 0, IF(gl.snmp_query_id=0, 2, 1)) AS graph_source,
			$sql_select
			FROM graph_templates_graph AS gtg
			INNER JOIN graph_local AS gl
			ON gl.id=gtg.local_graph_id
			LEFT JOIN graph_templates AS gt
			ON gt.id=gl.graph_template_id
			LEFT JOIN host AS h
			ON h.id=gl.host_id
			$sql_join
			$sql_where
			$sql_having";

		$graphs = db_fetch_assoc("$graphs_sql
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(*)
			FROM (
				$graphs_sql
			) AS rower");
	} else {
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
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(*)
			FROM graph_templates_graph AS gtg
			INNER JOIN graph_local AS gl
			ON gl.id=gtg.local_graph_id
			LEFT JOIN graph_templates AS gt
			ON gt.id=gl.graph_template_id
			LEFT JOIN host AS h
			ON h.id=gl.host_id
			$sql_where");
	}

	return $graphs;
}

function get_allowed_aggregate_graphs($sql_where = '', $order_by = 'gtg.title_cache', $limit = '', &$total_rows = 0, $user = 0, $graph_id = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($graph_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' ') . " gl.id = $graph_id";
	}

	if (read_user_setting('hide_disabled') == 'on') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . '(IFNULL(h.disabled,"")="")';
	}

	if ($sql_where != '') {
		$sql_where = "WHERE IFNULL(h.deleted,'') = '' AND $sql_where";
	} else {
		$sql_where = "WHERE IFNULL(h.deleted,'') = ''";
	}

	if ($user == -1) {
		$auth_method = 0;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method != 0) {
		if ($user == 0) {
			if (isset($_SESSION['sess_user_id'])) {
				$user = $_SESSION['sess_user_id'];
			} else {
				return array();
			}
		}

		if (read_config_option('graph_auth_method') == 1) {
			$sql_operator = 'OR';
		} else {
			$sql_operator = 'AND';
		}

		/* get policies for all groups and user */
		$policies = db_fetch_assoc_prepared("SELECT uag.id,
			'group' AS type, uag.policy_graphs, uag.policy_hosts, uag.policy_graph_templates
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on'
			AND uagm.user_id = ?",
			array($user));

		$policies[] = db_fetch_row_prepared("SELECT id, 'user' AS type, policy_graphs,
			policy_hosts, policy_graph_templates
			FROM user_auth
			WHERE id = ?",
			array($user));

		$i          = 0;
		$sql_having = '';
		$sql_select = '';
		$sql_join   = '';

		foreach($policies as $policy) {
			if ($policy['policy_graphs'] == 1) {
				$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NULL";
			} else {
				$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NOT NULL";
			}

			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '' : 'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_hosts'] == 1) {
				$sql_having .= " OR (user$i IS NULL";
			} else {
				$sql_having .= " OR (user$i IS NOT NULL";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '' : 'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_graph_templates'] == 1) {
				$sql_having .= " $sql_operator user$i IS NULL))";
			} else {
				$sql_having .= " $sql_operator user$i IS NOT NULL))";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '' : 'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		$sql_having = "HAVING $sql_having";

		$graphs_sql = "SELECT DISTINCT gtg.local_graph_id, '' AS description, gt.name AS template_name,
			gtg.title_cache, gtg.width, gtg.height, '' AS snmp_index, gl.snmp_query_id,
			$sql_select
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
			INNER JOIN aggregate_graphs AS ag
			ON gl.id=ag.local_graph_id
			LEFT JOIN graph_templates AS gt
			ON gt.id=gl.graph_template_id
			LEFT JOIN host AS h
			ON h.id=gl.host_id
			$sql_join
			$sql_where
			$sql_having";

		$graphs = db_fetch_assoc("$graphs_sql
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT rower.local_graph_id)
			FROM (
				$graphs_sql
			) AS rower");
	} else {
		$graphs = db_fetch_assoc("SELECT DISTINCT gtg.local_graph_id, '' AS description, gt.name AS template_name,
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
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT gl.id)
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
			$sql_where");
	}

	return $graphs;
}

function get_allowed_type_hash($type, $init_where, $init_order, $limit, $item, $user) {
	if ($item == 0) {
		return md5($type . '_' . $init_where . '_' . $init_order . '_' . $limit . '_' . $user);
	} else {
		return false;
	}
}

function prime_devices_type_cache($hash, $user) {
	$init_rows = -1;

	if (!isset($_SESSION['sess_allowed_templates'][$hash])) {
		cacti_log('Priming \'Device\' cache', false, 'WEBUI', POLLER_VERBOSITY_HIGH);
		$devices = get_allowed_devices('', 'description', -1, $init_rows, 0, $user);
		set_cached_allowed_type('devices', $devices, $hash, $init_rows);
	}
}

function get_cached_allowed_type($hash, $init_rows) {
	if ($hash !== false) {
		// verify if the user needs to reset cache
		$last_change = read_config_option('sess_allowed_templates_lastchange', true);
		$user_change = read_user_setting('sess_allowed_templates_lastchange', 0, true);

		if ($user_change < $last_change || empty($user_change)) {
			cacti_log('Detected need for forced template cache refresh:', false, 'WEBUI', POLLER_VERBOSITY_HIGH);
			clear_cached_allowed_types();
			set_user_setting('sess_allowed_templates_lastchange', time());

			return array();
		}

		cacti_log('Fetch InitRows:' . $init_rows . ', Hash:' . $hash, false, 'WEBUI', POLLER_VERBOSITY_HIGH);

		if (isset($_SESSION['sess_allowed_templates'][$hash]) && $init_rows == -1) {
			return $_SESSION['sess_allowed_templates'][$hash];
		}
	}

	return array();
}

function set_cached_allowed_type($type, &$items, $hash, $init_rows) {
	if ($hash !== false && $init_rows == -1) {
		cacti_log('Store InitRows:' . $init_rows . ', Hash:' . $hash, false, 'WEBUI', POLLER_VERBOSITY_HIGH);

		if ($type == 'devices') {
			$_SESSION['sess_allowed_templates'][$hash] = array_rekey($items, 'id', 'id');

			// Handle special case of host_id 0
			$_SESSION['sess_allowed_templates'][$hash][0] = '0';
		} else {
			$_SESSION['sess_allowed_templates'][$hash] = $items;
		}
	}
}

function clear_cached_allowed_types() {
	cacti_log('Killing Session Cache due to device or template change', false, 'WEBUI', POLLER_VERBOSITY_HIGH);
	kill_session_var('sess_allowed_templates');
	set_config_option('sess_allowed_templates_lastchange', time());
}

function get_allowed_graph_templates($sql_where = '', $order_by = 'gt.name', $limit = '', &$total_rows = 0, $user = 0, $graph_template_id = 0) {
	$hash      = get_allowed_type_hash('graph_templates', $sql_where, $order_by, $limit, $graph_template_id, $user);
	$init_rows = $total_rows;

	if ($hash !== false) {
		cacti_log('Obtaining \'Graph Template\' cache', false, 'WEBUI', POLLER_VERBOSITY_HIGH);
		$cached = get_cached_allowed_type($hash, $init_rows);

		if (is_array($cached) && sizeof($cached)) {
			cacti_log('Found Valid \'Graph Template\' priming cache', false, 'WEBUI', POLLER_VERBOSITY_HIGH);
			return $cached;
		}
	}

	$templates = array();

	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($graph_template_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' ') . "(gl.graph_template_id = $graph_template_id)";
	}

	$sql_where = 'WHERE ' . ($sql_where != '' ? $sql_where . ' AND ':' ') . '(gt.id IS NOT NULL) ';

	if ($user == -1) {
		$auth_method = 0;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method != 0) {
		if ($user == 0) {
			if (isset($_SESSION['sess_user_id'])) {
				$user = $_SESSION['sess_user_id'];
			} else {
				return array();
			}
		}

		if (read_config_option('graph_auth_method') == 1) {
			$sql_operator = 'OR';
		} else {
			$sql_operator = 'AND';
		}

		/* get policies for all groups and user */
		$policies = db_fetch_assoc_prepared("SELECT uag.id, 'group' AS type, uag.policy_graphs,
			uag.policy_hosts, uag.policy_graph_templates
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on'
			AND uagm.user_id = ?",
			array($user));

		$policies[] = db_fetch_row_prepared("SELECT id, 'user' AS type, policy_graphs,
			policy_hosts, policy_graph_templates
			FROM user_auth
			WHERE id = ?",
			array($user));

		$i        = 0;
		$sql_user = '';
		$sql_join = '';

		foreach($policies as $policy) {
			if ($policy['policy_graphs'] == 1) {
				$sql_user .= ($sql_user != '' ? ' OR ' : '') . "(uap$i." . $policy['type'] . "_id IS NULL";
			} else {
				$sql_user .= ($sql_user != '' ? ' OR ' : '') . "(uap$i." . $policy['type'] . "_id IS NOT NULL";
			}

			$sql_join .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$i++;

			if ($policy['policy_hosts'] == 1) {
				$sql_user .= " OR (uap$i." . $policy['type'] . "_id IS NULL";
			} else {
				$sql_user .= " OR (uap$i." . $policy['type'] . "_id IS NOT NULL";
			}

			$sql_join .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '' : 'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$i++;

			if ($policy['policy_graph_templates'] == 1) {
				$sql_user .= " $sql_operator uap$i." . $policy['type'] . "_id IS NULL))";
			} else {
				$sql_user .= " $sql_operator uap$i." . $policy['type'] . "_id IS NOT NULL))";
			}

			$sql_join .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '' : 'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$i++;
		}

		if ($sql_user != '') {
			$sql_where .= ' AND (' . $sql_user . ')';
		}

		if ($total_rows != -2) {
			$templates = db_fetch_assoc("SELECT DISTINCT gt.id, gt.name
				FROM graph_local AS gl
				LEFT JOIN graph_templates AS gt
				ON gt.id = gl.graph_template_id
				LEFT JOIN host AS h
				ON h.id=gl.host_id
				$sql_join
				$sql_where
				$order_by
				$limit");
		}

		if ($total_rows >= 0 || $total_rows == -2) {
			$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT gl.graph_template_id)
				FROM graph_local AS gl
				LEFT JOIN graph_templates AS gt
				ON gt.id=gl.graph_template_id
				LEFT JOIN host AS h
				ON h.id=gl.host_id
				$sql_join
				$sql_where") + 3;
		}
	} else {
		if ($total_rows != -2) {
			$templates = db_fetch_assoc("SELECT DISTINCT gt.id, gt.name
				FROM graph_local AS gl
				LEFT JOIN graph_templates AS gt
				ON gt.id=gl.graph_template_id
				LEFT JOIN host AS h
				ON h.id=gl.host_id
				$sql_where
				$order_by
				$limit");
		}

		if ($total_rows >= 0 || $total_rows == -2) {
			$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT gl.graph_template_id) AS id
				FROM graph_local AS gl
				LEFT JOIN graph_templates AS gt
				ON gt.id=gl.graph_template_id
				LEFT JOIN host AS h
				ON h.id=gl.host_id
				$sql_where");
		}
	}

	if ($templates === false) {
		$templates = array();
	}

	set_cached_allowed_type('graph_templates', $templates, $hash, $init_rows);

	return $templates;
}

function get_allowed_trees($edit = false, $return_sql = false, $sql_where = '', $order_by = 'name', $limit = '', &$total_rows = 0, $user = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($user == -1) {
		$auth_method = 0;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method != 0) {
		if ($user == 0) {
			if (isset($_SESSION['sess_user_id'])) {
				$user = $_SESSION['sess_user_id'];
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
			array($user));

		$policies[] = db_fetch_row_prepared("SELECT id, 'user' as type, policy_trees FROM user_auth WHERE id = ?", array($user));

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
			$order_by
			$limit";

		if ($return_sql) {
			return $sql;
		} else {
			$trees = db_fetch_assoc($sql);

			$total_rows = db_fetch_cell("SELECT COUNT(gt.id)
				FROM graph_tree AS gt
				$sql_join
				$sql_where");
		}
	} else {
		if ($sql_where != '') {
			$sql_where = "WHERE enabled='on' AND $sql_where";
		} else {
			$sql_where = "WHERE enabled='on'";
		}

		if ($return_sql) {
			return "SELECT id, name FROM graph_tree $sql_where $order_by";
		} else {
			$trees      = db_fetch_assoc("SELECT id, name FROM graph_tree AS gt $sql_where $order_by");
			$total_rows = db_fetch_cell("SELECT COUNT(*) FROM graph_tree AS gt $sql_where");
		}
	}

	return $trees;
}

function get_allowed_branches($sql_where = '', $order_by = 'name', $limit = '', &$total_rows = 0, $user = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($user == -1) {
		$auth_method = 0;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	// suppress total rows
	$total_rows = -1;

	$hosts = get_allowed_devices('', 'description', '', $total_rows);
	$sql_hosts_where = '';
	if (cacti_sizeof($hosts) > 0) {
		$sql_hosts_where =  'AND h.id IN (' . implode(',', array_keys(array_rekey($hosts, 'id', 'description'))) . ')';
	}

	if ($auth_method != 0) {
		if ($user == 0) {
			if (isset($_SESSION['sess_user_id'])) {
				$user = $_SESSION['sess_user_id'];
			} else {
				return array();
			}
		}

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc_prepared("SELECT uag.id, 'group' AS type, policy_trees
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on'
			AND uagm.user_id = ?",
			array($user));

		$policies[] = db_fetch_row_prepared("SELECT id, 'user' as type, policy_trees
			FROM user_auth
			WHERE id = ?",
			array($user));

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
			$sql_where = 'WHERE gt.enabled="on" AND (' . $sql_where . ') AND (' . $sql_where1 . ')';
		} else {
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
			$order_by
			$limit";

		$branches   = db_fetch_assoc($sql);
		$total_rows = db_fetch_cell('SELECT COUNT(*) FROM (' . $sql . ') AS rower');
	} else {
		if ($sql_where != '') {
			$sql_where = "WHERE gt.enabled='on' AND h.disabled=''  AND h.deleted = '' AND $sql_where";
		} else {
			$sql_where = "WHERE gt.enabled='on' AND h.disabled='on' AND h.deleted = ''";
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
			$order_by
			$limit";

		$branches   = db_fetch_assoc($sql);
		$total_rows = db_fetch_cell('SELECT COUNT(*) FROM (' . $sql . ') AS rower');
	}

	return $branches;
}

function get_allowed_devices($sql_where = '', $order_by = 'description', $limit = '', &$total_rows = 0, $user = 0, $host_id = 0) {
	$hash      = get_allowed_type_hash('devices', '', '', '', 0, $user);
	$init_rows = $total_rows;
	$cached    = array();

	if ($limit != -1) {
		prime_devices_type_cache($hash, $user);

		if ($hash !== false) {
			$cached = get_cached_allowed_type($hash, $init_rows);
		}
	} else {
		$limit  = '';
	}

	$host_list = array();

	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if (read_user_setting('hide_disabled') == 'on') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . '(IFNULL(h.disabled,"")="")';
	}

	if ($sql_where != '') {
		$sql_where = "WHERE IFNULL(h.deleted,'') = '' AND $sql_where";
	} else {
		$sql_where = "WHERE IFNULL(h.deleted,'') = ''";
	}

	if ($host_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . " h.id=$host_id";
	} else {
		if (sizeof($cached)) {
			return db_fetch_assoc("SELECT *
				FROM host AS h
				$sql_where" .
				($sql_where != '' ? ' AND ':' WHERE ') . ' id IN (' . implode(', ', $cached) . ")
				$order_by
				$limit");
		}
	}

	if ($user == -1) {
		$auth_method = 0;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method != 0) {
		if ($user == 0) {
			if (isset($_SESSION['sess_user_id'])) {
				$user = $_SESSION['sess_user_id'];
			} else {
				return array();
			}
		}

		if (read_config_option('graph_auth_method') == 1) {
			$sql_operator = 'OR';
		} else {
			$sql_operator = 'AND';
		}

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc_prepared("SELECT uag.id, 'group' AS type,
			uag.policy_graphs, uag.policy_hosts, uag.policy_graph_templates
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on'
			AND uagm.user_id = ?",
			array($user));

		$policies[] = db_fetch_row_prepared("SELECT id, 'user' AS type,
			policy_graphs, policy_hosts, policy_graph_templates
			FROM user_auth
			WHERE id = ?",
			array($user));

		$i          = 0;
		$sql_select = '';
		$sql_join   = '';
		$sql_having = '';

		foreach($policies as $policy) {
			if ($policy['policy_graphs'] == 1) {
				$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NULL";
			} else {
				$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NOT NULL";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_hosts'] == 1) {
				$sql_having .= " OR (user$i IS NULL";
			} else {
				$sql_having .= " OR (user$i IS NOT NULL";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_graph_templates'] == 1) {
				$sql_having .= " $sql_operator user$i IS NULL))";
			} else {
				$sql_having .= " $sql_operator user$i IS NOT NULL))";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		$sql_having = "HAVING $sql_having";

		if ($total_rows != -2) {
			$host_list = db_fetch_assoc("SELECT h1.*
				FROM host AS h1
				INNER JOIN (
					SELECT DISTINCT id FROM (
						SELECT h.id, $sql_select
						FROM host AS h
						LEFT JOIN graph_local AS gl
						ON h.id=gl.host_id
						LEFT JOIN graph_templates AS gt
						ON gt.id=gl.graph_template_id
						LEFT JOIN host_template AS ht
						ON h.host_template_id=ht.id
						$sql_join
						$sql_where
						$sql_having
					) AS rs1
				) AS rs2
				ON rs2.id=h1.id
				$order_by
				$limit");
		}

		if ($total_rows >= 0 || $total_rows == -2) {
			$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT id)
				FROM (
					SELECT h.id, $sql_select
					FROM host AS h
					LEFT JOIN graph_local AS gl
					ON h.id=gl.host_id
					LEFT JOIN graph_templates AS gt
					ON gt.id=gl.graph_template_id
					LEFT JOIN host_template AS ht
					ON h.host_template_id=ht.id
					$sql_join
					$sql_where
					$sql_having
				) AS rower");
		}
	} else {
		if ($total_rows != -2) {
			$host_list = db_fetch_assoc("SELECT h1.*
				FROM host AS h1
				INNER JOIN (
					SELECT DISTINCT id FROM (
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
				$order_by
				$limit");
		}

		if ($total_rows >= 0 || $total_rows == -2) {
			$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT id)
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
				) AS rower");
		}
	}

	return $host_list;
}

function get_allowed_sites($sql_where = '', $order_by = 'name', $limit = '', &$total_rows = 0, $user = 0, $site_id = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($sql_where != '') {
		$sql_where = "WHERE $sql_where";
	}

	if ($site_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . " s.id=$site_id";
	}

	if ($user == -1) {
		$auth_method = 0;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($user == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$user = $_SESSION['sess_user_id'];
		} else {
			return array();
		}
	}

	$sites = db_fetch_assoc("SELECT DISTINCT s.id, s.name
		FROM sites AS s
		INNER JOIN host AS h
		ON s.id=h.site_id
		$sql_where
		$order_by
		$limit");

	$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT s.id)
		FROM sites AS s
		INNER JOIN host AS h
		ON s.id=h.site_id
		$sql_where");

	return $sites;
}

function get_allowed_site_devices($site_id, $sql_where = '', $order_by = 'description', $limit = '', &$total_rows = 0, $user = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if (read_user_setting('hide_disabled') == 'on') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . '(IFNULL(h.disabled,"")="")';
	}

	if ($sql_where != '') {
		$sql_where = "WHERE IFNULL(h.deleted,'') = '' AND $sql_where";
	} else {
		$sql_where = "WHERE IFNULL(h.deleted,'') = ''";
	}

	if ($site_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . " h.site_id=$site_id";
	}

	if ($user == -1) {
		$auth_method = 0;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method != 0) {
		if ($user == 0) {
			if (isset($_SESSION['sess_user_id'])) {
				$user = $_SESSION['sess_user_id'];
			} else {
				return array();
			}
		}

		if (read_config_option('graph_auth_method') == 1) {
			$sql_operator = 'OR';
		} else {
			$sql_operator = 'AND';
		}

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc_prepared("SELECT uag.id, 'group' AS type,
			uag.policy_graphs, uag.policy_hosts, uag.policy_graph_templates
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on'
			AND uagm.user_id = ?",
			array($user));

		$policies[] = db_fetch_row_prepared("SELECT id, 'user' AS type,
			policy_graphs, policy_hosts, policy_graph_templates
			FROM user_auth
			WHERE id = ?",
			array($user));

		$i          = 0;
		$sql_select = '';
		$sql_join   = '';
		$sql_having = '';

		foreach($policies as $policy) {
			if ($policy['policy_graphs'] == 1) {
				$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NULL";
			} else {
				$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NOT NULL";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1 AND uap$i." . $policy['type'] . '_id=' . $policy['id'] . ') ';
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_hosts'] == 1) {
				$sql_having .= " OR (user$i IS NULL";
			} else {
				$sql_having .= " OR (user$i IS NOT NULL";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3 AND uap$i." . $policy['type'] . '_id=' . $policy['id'] . ') ';
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_graph_templates'] == 1) {
				$sql_having .= " $sql_operator user$i IS NULL))";
			} else {
				$sql_having .= " $sql_operator user$i IS NOT NULL))";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4 AND uap$i." . $policy['type'] . '_id=' . $policy['id'] . ') ';
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		$sql_having = "HAVING $sql_having";

		$host_list = db_fetch_assoc("SELECT h1.*, ht.name AS host_template_name
			FROM host AS h1
			LEFT JOIN host_template AS ht
			ON h1.host_template_id=ht.id
			INNER JOIN (
				SELECT DISTINCT id FROM (
					SELECT h.*, $sql_select
					FROM host AS h
					LEFT JOIN graph_local AS gl
					ON h.id=gl.host_id
					LEFT JOIN graph_templates_graph AS gtg
					ON gl.id=gtg.local_graph_id
					LEFT JOIN graph_templates AS gt
					ON gt.id=gl.graph_template_id
					LEFT JOIN host_template AS ht
					ON h.host_template_id=ht.id
					$sql_join
					$sql_where
					$sql_having
				) AS rs1
			) AS rs2
			ON rs2.id=h1.id
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT id)
			FROM (
				SELECT h.id, $sql_select
				FROM host AS h
				LEFT JOIN graph_local AS gl
				ON h.id=gl.host_id
				LEFT JOIN graph_templates_graph AS gtg
				ON gl.id=gtg.local_graph_id
				LEFT JOIN graph_templates AS gt
				ON gt.id=gl.graph_template_id
				LEFT JOIN host_template AS ht
				ON h.host_template_id=ht.id
				$sql_join
				$sql_where
				$sql_having
			) AS rower");
	} else {
		$host_list = db_fetch_assoc("SELECT h1.*, ht.name AS host_template_name
			FROM host AS h1
			LEFT JOIN host_template AS ht
			ON h1.host_template_id=ht.id
			INNER JOIN (
				SELECT DISTINCT id FROM (
					SELECT h.*
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
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT id)
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
			) AS rower");
	}

	return $host_list;
}

function get_allowed_graph_templates_normalized($sql_where = '', $order_by = 'name', $limit = '', &$total_rows = 0, $user = 0, $graph_template_id = 0) {
	$templates = array_rekey(get_allowed_graph_templates($sql_where, $order_by, $limit, $total_rows, $user, $graph_template_id), 'id', 'name');

	if (!cacti_sizeof($templates)) {
		return array();
	}

	if ($sql_where != '') {
		$sql_where = ' WHERE (' . $sql_where . ') AND gl.graph_template_id IN(' . implode(', ', array_keys($templates)) . ') AND (gl.snmp_query_graph_id=0 OR sqg.name IS NOT NULL) AND (gt.name IS NOT NULL)';
	} else {
		$sql_where = ' WHERE gl.graph_template_id IN(' . implode(', ', array_keys($templates)) . ') AND (gl.snmp_query_graph_id=0 OR sqg.name IS NOT NULL) AND (gt.name IS NOT NULL)';
	}

	if ($limit != '') {
		$sql_limit = 'LIMIT ' . $limit;
	} else {
		$sql_limit = '';
	}

	$sql_order = 'ORDER BY ' . $order_by;

	$templates = db_fetch_assoc("SELECT DISTINCT
		IF(snmp_query_graph_id=0, CONCAT('cg_',gl.graph_template_id), CONCAT('dq_', gl.snmp_query_graph_id)) AS id,
		IF(snmp_query_graph_id=0, gt.name, CONCAT(gt.name, ' [', sqg.name, ']')) AS name
		FROM graph_local AS gl
		LEFT JOIN graph_templates AS gt
		ON gt.id=gl.graph_template_id
		LEFT JOIN snmp_query_graph AS sqg
		ON gl.snmp_query_graph_id=sqg.id
		AND gl.graph_template_id=sqg.graph_template_id
		$sql_where
		$sql_order
		$sql_limit");

	return $templates;
}

/* get_host_array - returns a list of hosts taking permissions into account if necessary
   @returns - (array) an array containing a list of hosts */
function get_host_array() {
	$total_rows = -1;

	$hosts = get_allowed_devices('', 'description', '', $total_rows);

	foreach($hosts as $host) {
		$return_devices[] = strip_domain($host['description']) . ' (' . strip_domain($host['hostname']) . ')';
	}

	return $return_devices;
}

function get_allowed_ajax_hosts($include_any = true, $include_none = true, $sql_where = '') {
	$return    = array();

	$term = get_filter_request_var('term', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));
	if ($term != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : '') . "hostname LIKE '%$term%' OR description LIKE '%$term%' OR notes LIKE '%$term%'";
	}

	if (get_request_var('term') == '') {
		if ($include_any) {
			$return[] = array('label' => __('Any'), 'value' => __('Any'), 'id' => '-1');
		}
		if ($include_none) {
			$return[] = array('label' => __('None'), 'value' => __('None'), 'id' => '0');
		}
	}

	$total_rows = -1;

	$hosts = get_allowed_devices($sql_where, 'description', read_config_option('autocomplete_rows'), $total_rows);
	if (cacti_sizeof($hosts)) {
		foreach($hosts as $host) {
			$return[] = array('label' => strip_domain($host['description']), 'value' => $host['description'], 'id' => $host['id']);
		}
	}

	print json_encode($return);
}

function get_allowed_ajax_graph_items($include_none = true, $sql_where = '') {
	$return    = array();

	$term = get_filter_request_var('term', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));
	if ($term != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : '') . "name_cache LIKE '%$term%' OR data_source_name LIKE '%$term%'";
	}

	if (get_request_var('term') == '') {
		if ($include_none) {
			$return[] = array('label' => 'None', 'value' => 'None', 'id' => '0');
		}
	}

	$graph_items = get_allowed_graph_items($sql_where, 'name_cache', read_config_option('autocomplete_rows'));
	if (cacti_sizeof($graph_items)) {
		foreach($graph_items as $gi) {
			$return[] = array('label' => $gi['name'], 'value' => $gi['name'], 'id' => $gi['id']);
		}
	}

	print json_encode($return);
}

function get_allowed_graph_items($sql_where, $sort = 'name' , $limit = 20, $user = 0) {
	$return = array();

	if ($user == 0) {
		$user = $_SESSION['sess_user_id'];
	}

	if ($sql_where != '') {
		$sql_where = 'WHERE ' . $sql_where;
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
		ORDER BY $sort
		LIMIT $limit");

	if (cacti_sizeof($items)) {
		foreach($items as $i) {
			if (is_device_allowed($i['host_id'], $user)) {
				$return[] = array('id' => $i['id'], 'name' => $i['name']);
			}
		}
	}

	return $return;
}

function secpass_login_process($username) {
	// Mark failed login attempts
	$secPassLockFailed = read_config_option('secpass_lockfailed');
	if ($secPassLockFailed > 0) {
		$max = intval($secPassLockFailed);
		if ($max > 0) {
			$p = get_nfilter_request_var('login_password');
			$user = db_fetch_row_prepared("SELECT id, username, lastfail, failed_attempts, `locked`, password
				FROM user_auth
				WHERE username = ?
				AND realm = 0
				AND enabled = 'on'",
				array($username));

			if (isset($user['username'])) {
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
						AND realm = 0
						AND enabled = 'on'",
						array($username));

					$user['failed_attempts'] = $user['lastfail'] = 0;
					$user['locked'] = '';
				}

				$valid_pass = compat_password_verify($p, $user['password']);
				cacti_log('DEBUG: User \'' . $username . '\' valid password = ' . $valid_pass, false, 'AUTH', POLLER_VERBOSITY_DEBUG);

				if (!$valid_pass) {
					$failed = intval($user['failed_attempts']) + 1;
					cacti_log('LOGIN: WARNING: User \'' . $username . '\' failed authentication, incrementing lockout (' . $failed . ' of ' . $max . ')', false, 'AUTH', POLLER_VERBOSITY_LOW);

					if ($failed >= $max) {
						db_execute_prepared("UPDATE user_auth
							SET `locked` = 'on'
							WHERE username = ?
							AND realm = 0
							AND enabled = 'on'",
							array($username));

						$user['locked'] = 'on';
					}

					$user['lastfail'] = time();

					db_execute_prepared("UPDATE user_auth
						SET lastfail = ?, failed_attempts = ?
						WHERE username = ?
						AND realm = 0
						AND enabled = 'on'",
						array($user['lastfail'], $failed, $username));

					// Log the invalid password attempt
					db_execute_prepared('INSERT IGNORE INTO user_log
						(username, user_id, result, ip, time)
						VALUES (?, ?, 0, ?, NOW())',
						array($username, isset($user['id']) ? $user['id']:0, get_client_addr('')));

					cacti_log("LOGIN: Local Login Failed for user '" . $username . "' from IP Address '" . get_client_addr('') . "'.", false, 'AUTH');

					if ($user['locked'] != '') {
						display_custom_error_message(__('This account has been locked.'));
						header('Location: index.php');
						exit;
					}

					return false;
				}

				if ($user['locked'] != '') {
					display_custom_error_message(__('This account has been locked.'));
					header('Location: index.php');
					exit;
				}
			}
		}
	}

	// Check if old password doesn't meet specifications and must be changed
	if (read_config_option('secpass_forceold') == 'on') {
		$p = get_nfilter_request_var('login_password');
		$message = secpass_check_pass($p);

		if ($message != 'ok') {
			db_execute_prepared("UPDATE user_auth
				SET must_change_password = 'on'
				WHERE username = ?
				AND realm = 0
				AND enabled = 'on'",
				array($username));

			$message = __('Your Cacti administrator has forced complex passwords for logins and your current Cacti password does not match the new requirements.  Therefore, you must change your password now.');

			display_custom_error_message($message);
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

	return true;
}

function secpass_check_pass($p) {
	$minlen = read_config_option('secpass_minlen');
	if (strlen($p) < $minlen) {
		return __('Password must be at least %d characters!', $minlen);
	}

	if (read_config_option('secpass_reqnum') == 'on' &&
		str_replace(array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'), '', $p) == $p
	) {
		return __('Your password must contain at least 1 numerical character!');
	}

	if (read_config_option('secpass_reqmixcase') == 'on' && strtolower($p) == $p) {
		return __('Your password must contain a mix of lower case and upper case characters!');
	}

	if (read_config_option('secpass_reqspec') == 'on' &&
		str_replace(array('~', '`', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '_', '+', '=', '[', '{', ']', '}', ';', ':', '<', ',', '.', '>', '?', '|', '/', '\\'), '', $p) == $p
	) {
		return __('Your password must contain at least 1 special character!');
	}

	return 'ok';
}

function secpass_check_history($id, $p) {
	$history = intval(read_config_option('secpass_history'));

	if ($history > 0) {
		$user = db_fetch_row_prepared("SELECT password, password_history
			FROM user_auth
			WHERE id = ?
			AND realm = 0
			AND enabled = 'on'",
			array($id));

		if (compat_password_verify($p,$user['password'])) {
			return false;
		}
		$passes = explode('|', $user['password_history']);
		// Double check this incase the password history setting was changed
		while (cacti_count($passes) > $history) {
			array_shift($passes);
		}

		if (!empty($passes)) {
			foreach ($passes as $hash) {
				if (compat_password_verify($p, $hash)) {
					return false;
				}
			}
		}
	}
	return true;
}

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

/* reset_group_perms - sets a flag for all users of a group logged in that their perms need to be reloaded from the database
   @arg $user_id - (int) the id of the current user
   @returns - null */
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

/* reset_user_perms - sets a flag for all users logged in as this user that their perms need to be reloaded from the database
   @arg $user_id - (int) the id of the current user
   @returns - null */
function reset_user_perms($user_id) {
	db_execute_prepared('UPDATE user_auth
		SET reset_perms=FLOOR(RAND() * 4294967295) + 1
		WHERE id = ?',
		array($user_id));
}

/* is_user_perms_valid - checks to see if the admin has changed users permissions
   @arg $user_id - (int) the id of the current user
   @returns - true if still valid, false otherwise */
function is_user_perms_valid($user_id) {
	global $config;

	static $valid = NULL;
	static $key = NULL;

	if (isset($_SESSION['sess_user_perms_key'])) {
		$key = $_SESSION['sess_user_perms_key'];
	} else {
		$_SESSION['sess_user_perms_key'] = false;
	}

	if ($valid === NULL) {
		if (cacti_version_compare($config['cacti_db_version'], '1.0.0', '>=')) {
			$key = db_fetch_cell_prepared('SELECT reset_perms
				FROM user_auth
				WHERE id = ?',
				array($user_id));
		}
	}

	$valid = ($_SESSION['sess_user_perms_key'] === $key);
	$_SESSION['sess_user_perms_key'] = $key;

	return $valid;
}

/* compat_password_verify - if the secure function exists, verify against that
   first.  If that checks fails or does not exist, check against older md5
   version
   @arg $password - (string) password to verify
   @arg $hash     - (string) current password hash
   @returns - true if password hash matches, false otherwise */
function compat_password_verify($password, $hash) {
	if (function_exists('password_verify')) {
		if (password_verify($password, $hash)) {
			return true;
		}
	}

	$md5 = md5($password);

	return ($md5 == $hash);
}

/* compat_password_hash - if the secure function exists, hash using that.
   If that does not exist, hash older md5 function instead
   @arg $password - (string) password to hash
   @arg $algo     - (string) algorithm to use (PASSWORD_DEFAULT)
   @returns - true if password hash matches, false otherwise */
function compat_password_hash($password, $algo, $options = array()) {
	if (function_exists('password_hash')) {
		// Check if options array has anything, only pass when required
		return (cacti_sizeof($options) > 0) ?
			password_hash($password, $algo, $options) :
			password_hash($password, $algo);
	}

	return md5($password);
}


/* compat_password_needs_rehash - if the secure function exists, check hash
   using that. If that does not exist, return false as md5 doesn't need a
   rehash
   @arg $password - (string) password to hash
   @arg $algo     - (string) algorithm to use (PASSWORD_DEFAULT)
   @returns - true if password hash needs changing, false otherwise */
function compat_password_needs_rehash($password, $algo, $options = array()) {
	if (function_exists('password_needs_rehash')) {
		// Check if options array has anything, only pass when required
		return (cacti_sizeof($options) > 0) ?
			password_needs_rehash($password, $algo, $options) :
			password_needs_rehash($password, $algo);
	}

	return true;
}

function disable_2fa($user_id) {
	$current_user = db_fetch_row_prepared('SELECT *
		FROM user_auth
		WHERE id = ?',
		array($user_id));

	$result = array('status' => 500, 'text' => __('Unknown error'));
	if (!cacti_sizeof($current_user)) {
		$result['status'] = 404;
		$result['text'] = __('ERROR: Unable to find user');
	} else {
		db_execute_prepared('UPDATE user_auth SET tfa_enabled = \'\', tfa_secret = \'\' WHERE id = ?', array($user_id));

		$current_user = db_fetch_row_prepared('SELECT *
			FROM user_auth
			WHERE id = ?',
			array($_SESSION['sess_user_id']));

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
	$current_user = db_fetch_row_prepared('SELECT *
		FROM user_auth
		WHERE id = ?',
		array($user_id));

	$result = array('status' => 500, 'text' => __('Unknown error'));
	if (!cacti_sizeof($current_user)) {
		$result['status'] = 404;
		$result['text'] = __('ERROR: Unable to find user');
	} else {
		$g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
		$secret = $g->generateSecret();
		db_execute_prepared('UPDATE user_auth SET tfa_secret = ? WHERE id = ?', array($secret, $user_id));

		$current_user = db_fetch_row_prepared('SELECT *
			FROM user_auth
			WHERE id = ?',
			array($_SESSION['sess_user_id']));

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
	$current_user = db_fetch_row_prepared('SELECT *
		FROM user_auth
		WHERE id = ?',
		array($user_id));

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

function is_2fa_enabled($user) {
	$current_user = db_fetch_row_prepared('SELECT *
		FROM user_auth
		WHERE id = ?',
		array($user_id));

	return isset($current_user['2fa_enabled']) && ($current_user['2fa_enabled'] != '');
}

function auth_login($user) {
	cacti_log("LOGIN: User '" . $user['username'] . "' Authenticated", false, 'AUTH');

	$client_addr = get_client_addr('');

	db_execute_prepared('INSERT IGNORE INTO user_log
		(username, user_id, result, ip, time)
		VALUES (?, ?, 1, ?, NOW())',
		array($user['username'], $user['id'], $client_addr));

	/* is user enabled */
	if (isset($user['enabled'])) {
		$user_enabled = $user['enabled'];
	} else {
		$user_enabled = 'on';
	}

	if ($user_enabled != 'on') {
		/* Display error */
		display_custom_error_message(__('Access Denied, user account disabled.'));
		header('Location: index.php');
		exit;
	}

	/* remember this user */
	if (isset_request_var('remember_me') && read_config_option('auth_cache_enabled') == 'on') {
		set_auth_cookie($user);
	}

	/* set the php session */
	$_SESSION['sess_user_id'] = $user['id'];
}

function auth_post_login_redirect($user) {
	global $config;

	/* handle 'force change password' */
	if (($user['must_change_password'] == 'on') &&
		(read_config_option('auth_method') == 1) &&
		($user['password_change'] == 'on')) {

		$_SESSION['sess_change_password'] = true;
	}

	if (db_table_exists('user_auth_group')) {
		$group_options = db_fetch_cell_prepared('SELECT MAX(login_opts)
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id=uagm.group_id
			WHERE user_id=?', array($_SESSION['sess_user_id']));

		if ($group_options > 0) {
			$user['login_opts'] = $group_options;
		}
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
				if (basename($referer) == 'logout.php' || basename($referer) == 'auth_2fa.php') {
					$referer = $config['url_path'] . 'index.php' . ($newtheme ? '?newtheme=1':'');
				}
			} elseif (isset($_SERVER['REQUEST_URI'])) {
				$referer = sanitize_uri($_SERVER['REQUEST_URI']);
				if (basename($referer) == 'logout.php' || basename($referer) == 'auth_2fa.php') {
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
}
