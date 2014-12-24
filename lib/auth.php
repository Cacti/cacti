<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/


/* clear_auth_cookie - clears a users security token
 * @return - NULL */
function clear_auth_cookie() {
	if (isset($_COOKIE['cacti_remembers']) && read_config_option('auth_cache_enabled') == 'on') {
		$parts = explode(',', $_COOKIE['cacti_remembers']);
		$user  = $parts[0];

		if ($user != '') {
			$user_id = db_fetch_cell_prepared('SELECT id FROM user_auth WHERE username = ?', array($user));

			if (!empty($user_id)) {
				if (isset($parts[1])) {
					$nssecret  = $parts[1];
					$secret = hash('sha512', $nssecret, false);
					setcookie('cacti_remembers', '', time() - 3600, '/cacti/');
					db_execute_prepared('DELETE FROM user_auth_cache WHERE user_id = ? AND token = ?', array($user_id, $secret));
				}
			}
		}
	}
}

/* set_auth_cookie - sets a users security token
 * @arg - (string) $user - The user_auth row for the user
 * @return - (boolean) True if token set worked, otherwise false */
function set_auth_cookie($user) {
	clear_auth_cookie();

	$nssecret = md5($_SERVER['REQUEST_TIME'] .  mt_rand(10000,10000000)) . md5($_SERVER['REMOTE_ADDR']);

	$secret = hash('sha512', $nssecret, false);

	db_execute_prepared('REPLACE INTO user_auth_cache 
		(user_id, hostname, last_update, token) 
		VALUES 
		(?, ?, NOW(), ?);', array($user['id'], $_SERVER['HTTP_HOST'], $secret));

	setcookie('cacti_remembers', $user['username'] . "," . $nssecret, time()+(86400*30), '/cacti/');
}

/* check_auth_cookie - clears a users security token
 * @return - (int) The user of the session cookie, otherwise false */
function check_auth_cookie() {
	if (isset($_COOKIE['cacti_remembers']) && read_config_option('auth_cache_enabled') == 'on') {
		$parts = explode(',', $_COOKIE['cacti_remembers']);
		$user  = $parts[0];

		if ($user != '') {
			$user_info = db_fetch_row_prepared('SELECT id, username 
				FROM user_auth 
				WHERE username = ?', array($user));

			if (!empty($user_info)) {
				if (isset($parts[1])) {
					$nssecret = $parts[1];

					$secret = hash('sha512', $nssecret, false);

					$found  = db_fetch_cell_prepared('SELECT user_id 
						FROM user_auth_cache 
						WHERE user_id = ? AND token = ?', array($user_info['id'], $secret));

					if (empty($found)) {
						return false;
					}else{
						set_auth_cookie($user_info);

						cacti_log("LOGIN: User '" . $user_info['username'] . "' Authenticated via Authentication Cookie", false, 'AUTH');

						db_execute_prepared('INSERT INTO user_log 
							(username, user_id, result, ip, time) 
							VALUES 
							(?, ?, 2, ?, NOW())', array($user, $user_info['id'], $_SERVER['REMOTE_ADDR']));
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
   @return - True on copy, False on no copy */
function user_copy($template_user, $new_user, $template_realm = 0, $new_realm = 0, $overwrite = false, $data_override = array()) {

	/* ================= input validation ================= */
	input_validate_input_number($template_realm);
	input_validate_input_number($new_realm);
	/* ==================================================== */

	/* Check get template users array */
	$user_auth = db_fetch_row("SELECT * FROM user_auth WHERE username='" . $template_user . "' AND realm=" . $template_realm);
	if (! isset($user_auth)) {
		return false;
	}
	$template_id = $user_auth["id"];

	/* Create update/insert for new/existing user */
	$user_exist = db_fetch_row("SELECT * FROM user_auth WHERE username='" . $new_user . "' AND realm=" . $new_realm);
	if (sizeof($user_exist)) {
		if ($overwrite) {
			/* Overwrite existing user */
			$user_auth["id"]        = $user_exist["id"];
			$user_auth["username"]  = $user_exist["username"];
			$user_auth["password"]  = $user_exist["password"];
			$user_auth["realm"]     = $user_exist["realm"];
			$user_auth["full_name"] = $user_exist["full_name"];
			$user_auth["must_change_password"] = $user_exist["must_change_password"];
			$user_auth["enabled"]   = $user_exist["enabled"];
		}else{
			/* User already exists, duplicate users are bad */
			raise_message(19);
			return false;
		}
	} else {
		/* new user */
		$user_auth["id"] = 0;
		$user_auth["username"] = $new_user;
		$user_auth["password"] = "!";
		$user_auth["realm"] = $new_realm;
	}

	/* Update data_override fields */
	if (is_array($data_override)) {
		foreach ($data_override as $field => $value) {
			if ((isset($user_auth[$field])) && ($field != "id") && ($field != "username")) {
				$user_auth[$field] = $value;
			}
		}
	}

	/* Save the user */
	$new_id = sql_save($user_auth, 'user_auth');

	/* Create/Update permissions and settings */
	if ((isset($user_exist)) && ($overwrite )) {
		db_execute("DELETE FROM user_auth_perms WHERE user_id=" . $user_exist["id"]);
		db_execute("DELETE FROM user_auth_realm WHERE user_id=" . $user_exist["id"]);
		db_execute("DELETE FROM settings_graphs WHERE user_id=" . $user_exist["id"]);
		db_execute("DELETE FROM settings_tree WHERE user_id=" . $user_exist["id"]);
	}

	$user_auth_perms = db_fetch_assoc("SELECT * FROM user_auth_perms WHERE user_id=" . $template_id);
	if (isset($user_auth_perms)) {
		foreach ($user_auth_perms as $row) {
			$row['user_id'] = $new_id;
			sql_save($row, 'user_auth_perms', array('user_id', 'item_id', 'type'), false);
		}
	}

	$user_auth_realm = db_fetch_assoc("SELECT * FROM user_auth_realm WHERE user_id=" . $template_id);
	if (isset($user_auth_realm)) {
		foreach ($user_auth_realm as $row) {
			$row['user_id'] = $new_id;
			sql_save($row, 'user_auth_realm', array('realm_id', 'user_id'), false);
		}
	}

	$settings_graphs = db_fetch_assoc("SELECT * FROM settings_graphs WHERE user_id=" . $template_id);
	if (isset($settings_graphs)) {
		foreach ($settings_graphs as $row) {
			$row['user_id'] = $new_id;
			sql_save($row, 'settings_graphs', array('user_id', 'name'), false);
		}
	}

	$settings_tree = db_fetch_assoc("SELECT * FROM settings_tree WHERE user_id=" . $template_id);
	if (isset($settings_tree)) {
		foreach ($settings_tree as $row) {
			$row['user_id'] = $new_id;
			sql_save($row, 'settings_tree', array('user_id', 'graph_tree_item_id'), false);
		}
	}

	/* apply group permissions for the user */
	$groups = db_fetch_assoc("SELECT group_id FROM user_auth_group_members WHERE user_id=" . $template_id);
	if (sizeof($groups)) {
		foreach($groups as $g) {
			$sql[] = "(" . $new_id . ", " . $g['group_id'] . ")";
		}

		db_execute("INSERT IGNORE INTO user_auth_group_members (user_id, group_id) VALUES " . implode(',', $sql));
	}

	api_plugin_hook_function('copy_user', array('template_id' => $template_id, 'new_id' => $new_id));

	return true;
}


/* user_remove - remove a user account
   @arg $user_id - Id os the user account to remove */
function user_remove($user_id) {
	/* ================= input validation ================= */
	input_validate_input_number($user_id);
	/* ==================================================== */

	/* check for guest or template user */
	$username = db_fetch_cell("SELECT username FROM user_auth WHERE id=" . $user_id);
	if ($username != get_request_var_post("username")) {
		if ($username == read_config_option("user_template")) {
			raise_message(21);
			return;
		}
		if ($username == read_config_option("guest_user")) {
			raise_message(21);
			return;
		}
	}

	db_execute("DELETE FROM user_auth WHERE id=" . $user_id);
	db_execute("DELETE FROM user_auth_realm WHERE user_id=" . $user_id);
	db_execute("DELETE FROM user_auth_perms WHERE user_id=" . $user_id);
	db_execute("DELETE FROM user_auth_group_members WHERE user_id=" . $user_id);
	db_execute("DELETE FROM settings_graphs WHERE user_id=" . $user_id);
	db_execute("DELETE FROM settings_tree WHERE user_id=" . $user_id);

	api_plugin_hook_function('user_remove', $user_id);
}

/* user_disable - disable a user account
   @arg $user_id - Id of the user account to disable */
function user_disable($user_id) {
	/* ================= input validation ================= */
	input_validate_input_number($user_id);
	/* ==================================================== */

	db_execute("UPDATE user_auth SET enabled='' WHERE id=" . $user_id);
}

/* user_enable - enable a user account
   @arg $user_id - Id of the user account to enable */
function user_enable($user_id) {
	/* ================= input validation ================= */
	input_validate_input_number($user_id);
	/* ==================================================== */

	db_execute("UPDATE user_auth SET enabled='on' WHERE id=" . $user_id);
}


/* get_graph_permissions_sql - creates SQL that reprents the current graph, host and graph
     template policies
   @arg $policy_graphs - (int) the current graph policy
   @arg $policy_hosts - (int) the current host policy
   @arg $policy_graph_templates - (int) the current graph template policy
   @returns - an SQL "where" statement */
function get_graph_permissions_sql($policy_graphs, $policy_hosts, $policy_graph_templates) {
	$sql = "";
	$sql_or = "";
	$sql_and = "";
	$sql_policy_or = "";
	$sql_policy_and = "";

	if ($policy_graphs == "1") {
		$sql_policy_and .= "$sql_and(user_auth_perms.type != 1 OR user_auth_perms.type is null)";
		$sql_and = " AND ";
		$sql_null = "is null";
	}elseif ($policy_graphs == "2") {
		$sql_policy_or .= "$sql_or(user_auth_perms.type = 1 OR user_auth_perms.type is not null)";
		$sql_or = " OR ";
		$sql_null = "is not null";
	}

	if ($policy_hosts == "1") {
		$sql_policy_and .= "$sql_and((user_auth_perms.type != 3) OR (user_auth_perms.type is null))";
		$sql_and = " AND ";
	}elseif ($policy_hosts == "2") {
		$sql_policy_or .= "$sql_or((user_auth_perms.type = 3) OR (user_auth_perms.type is not null))";
		$sql_or = " OR ";
	}

	if ($policy_graph_templates == "1") {
		$sql_policy_and .= "$sql_and((user_auth_perms.type != 4) OR (user_auth_perms.type is null))";
		$sql_and = " AND ";
	}elseif ($policy_graph_templates == "2") {
		$sql_policy_or .= "$sql_or((user_auth_perms.type = 4) OR (user_auth_perms.type is not null))";
		$sql_or = " OR ";
	}

	$sql_and = "";

	if (!empty($sql_policy_or)) {
		$sql_and = "AND ";
		$sql .= $sql_policy_or;
	}

	if (!empty($sql_policy_and)) {
		$sql .= "$sql_and$sql_policy_and";
	}

	if (empty($sql)) {
		return "";
	}else{
		return "(" . $sql . ")";
	}
}

/* is_graph_allowed - determines whether the current user is allowed to view a certain graph
   @arg $local_graph_id - (int) the ID of the graph to check permissions for
   @returns - (bool) whether the current user is allowed the view the specified graph or not */
function is_graph_allowed($local_graph_id) {
	$rows  = 0;
	$graph = get_allowed_graphs('', '', '', $rows, 0, $local_graph_id);

	if ($rows > 0) {
		return true;
	}else{
		return false;
	}
}

function auth_check_perms(&$objects, $policy) {
	/* policy == allow AND matches = DENY */
	if (sizeof($objects) && $policy == 1) {
		return false;
	/* policy == deny AND matches = ALLOW */
	}elseif (sizeof($objects) && $policy == 2) {
		return true;
	/* policy == allow AND no matches = ALLOW */
	}elseif (!sizeof($objects) && $policy == 1) {
		return true;
	/* policy == deny AND no matches = DENY */
	}elseif (!sizeof($objects) && $policy == 2) {
		return false;
	}
}

/* is_tree_allowed - determines whether the current user is allowed to view a certain graph tree
   @arg $tree_id - (int) the ID of the graph tree to check permissions for
   @returns - (bool) whether the current user is allowed the view the specified graph tree or not */
function is_tree_allowed($tree_id) {
	if (read_config_option("auth_method") != 0 && (isset($_SESSION["sess_user_id"]))) {
		$user   = $_SESSION['sess_user_id'];
		$policy = db_fetch_cell("SELECT policy_trees FROM user_auth WHERE id=$user");
		$trees  = db_fetch_assoc("SELECT user_id
			FROM user_auth_perms
			WHERE user_id=$user AND type=2 AND item_id=$tree_id");

		$authorized = auth_check_perms($trees, $policy);

		/* check for group perms */
		if (!$authorized) {
			$groups = db_fetch_assoc("SELECT uag.* 
				FROM user_auth_group AS uag
				INNER JOIN user_auth_group_members AS uagm
				ON uag.id=uagm.group_id
				WHERE uag.enabled='on' AND uagm.user_id=$user");

			if (sizeof($groups)) {
				foreach($groups as $g) {
					$policy = $g['policy_trees'];
					$trees  = db_fetch_assoc("SELECT user_id
						FROM user_auth_perms
						WHERE user_id=$user AND type=2 AND item_id=$tree_id");

					$authorized = auth_check_perms($trees, $policy);

					if ($authorized) {
						return true;
					}
				}

				return false;
			}else{
				return false;
			}
		}else{
			return true;
		}
	}else{
		return true;
	}
}

/* is_device_allowed - determines whether the current user is allowed to view a certain device
   @arg $host_id - (int) the ID of the device to check permissions for
   @returns - (bool) whether the current user is allowed the view the specified device or not */
function is_device_allowed($host_id) {
	$total_rows = 0;
	$host = get_allowed_devices('', '', '', $total_rows, $user = 0, $host_id);

	if ($total_rows > 0) {
		return true;
	}else{
		return false;
	}
}

/* is_graph_template_allowed - determines whether the current user is allowed to view a certain graph template
   @arg $graph_template_id - (int) the ID of the graph template to check permissions for
   @returns - (bool) whether the current user is allowed the view the specified graph template or not */
function is_graph_template_allowed($graph_template_id) {
	$total_rows = 0;
	$template = get_allowed_graph_templates('', '', '', $total_rows, $user = 0, $graph_template_id);

	if ($total_rows > 0) {
		return true;
	}else{
		return false;
	}
}

/* is_view_allowed - Returns a true or false as to wether or not a specific view type is allowed
 *                   View options include 'show_tree', 'show_list', 'show_preview', 'graph_settings'
 */
function is_view_allowed($view = 'show_tree') {
	$values = array_rekey(db_fetch_assoc("SELECT DISTINCT $view
		FROM user_auth_group AS uag
		INNER JOIN user_auth_group_members AS uagm
		ON uag.id=uagm.user_id
		WHERE uag.enabled='on' 
		AND uagm.user_id=" . $_SESSION["sess_user_id"]), $view, $view);

	if (isset($values[3])) {
		return false;
	}elseif (isset($values['on'])) {
		return true;
	}elseif (isset($values[2])) {
		return true;
	}else{
		$value = db_fetch_cell("SELECT $view FROM user_auth WHERE id=" . $_SESSION['sess_user_id']);

		if ($value == 'on') {
			return true;
		}else{
			return false;
		}
	}
}

function is_realm_allowed($realm) {
	global $user_auth_realms;

	/* list all realms that this user has access to */
	if (isset($_SESSION['sess_user_id'])) {
		if (isset($_SESSION['sess_user_realms'][$realm])) {
			return true;
		}elseif (read_config_option("auth_method") != 0) {
			$user_realm = db_fetch_cell("SELECT realm_id 
				FROM user_auth_realm 
				WHERE user_id=" . $_SESSION['sess_user_id'] . "
				AND realm_id=$realm
				UNION
				SELECT realm_id
				FROM user_auth_group_realm AS uagr
				INNER JOIN user_auth_group AS uag
				ON uag.id=uagr.group_id
				INNER JOIN user_auth_group_members AS uagm
				ON uag.id=uagm.group_id
				WHERE uag.enabled='on' AND uagr.realm_id=$realm
				AND uagm.user_id=" . $_SESSION['sess_user_id'], false);
	

			if (!empty($user_realm)) {
				$_SESSION['sess_user_realms'][$realm] = $realm;

				return true;
			}else{
				return false;
			}
		}else{
			$_SESSION['sess_user_realms'][$realm] = $realm;
		}
	}else{
		return false;
	}
}

function get_allowed_tree_content($tree_id, $graphs = false, $edit = false, $sql_where = '', $order_by = '', $limit = '', &$total_rows = 0, $user = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($sql_where != '') {
		$sql_where = "WHERE (gti.graph_tree_id=$tree_id) AND (" . $sql_where . ")" . ($edit == false ? " AND (gt.enabled='on')":"");
	}else{
		$sql_where = "WHERE (gti.graph_tree_id=$tree_id)" . ($edit == false ? " AND (gt.enabled='on')":"");
	}

	if (!$graphs) {
		$sql_where .= " AND gti.local_graph_id=0";
	}

	$hierarchy = db_fetch_assoc("SELECT gti.id, gti.title, gti.order_key, gti.host_id, 
		gti.local_graph_id, gti.host_grouping_type, h.description as hostname
		FROM graph_tree_items AS gti
		INNER JOIN graph_tree AS gt
		ON gt.id=gti.graph_tree_id
		LEFT JOIN host AS h
		ON h.id=gti.host_id
		$sql_where
		ORDER BY gti.order_key");

	if (read_config_option('auth_method') != 0) {
		$new_hierarchy = array();
		if (sizeof($hierarchy)) {
		foreach($hierarchy as $h) {
			if ($h['host_id'] > 0) {
				if (is_device_allowed($h['host_id'])) {
					$new_hierarchy[] = $h;
				}
			}else{
				$new_hierarchy[] = $h;
			}
		}
		}

		return $new_hierarchy;
	}else{
		return $heirarchy;
	}
}

function get_allowed_tree_header_graphs($tree_id, $search_key, $sql_where = '', $order_by = 'gti.order_key', $limit = '', &$total_rows = 0, $user = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if (strlen($sql_where)) {
		$sql_where = " AND ($sql_where)";
	}

	$sql_where = "WHERE (gti.graph_tree_id=$tree_id
		AND gti.order_key LIKE '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "')" . $sql_where;

	$i          = 0;
	$sql_having = '';
	$sql_select = '';
	$sql_join   = '';

	if (read_config_option("auth_method") != 0) {
		if ($user == 0) {
			$user = $_SESSION['sess_user_id'];
		}

		if (read_config_option("graph_auth_method") == 1) {
			$sql_operator = "OR";
		}else{
			$sql_operator = "AND";
		}

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc("SELECT uag.id, 'group' AS type, policy_graphs, policy_hosts, policy_graph_templates FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id=uagm.group_id
			WHERE uag.enabled='on' AND uagm.user_id=$user");
		$policies[] = db_fetch_row("SELECT id, 'user' AS type, policy_graphs, policy_hosts, policy_graph_templates FROM user_auth WHERE id=$user");
		
		foreach($policies as $policy) {
			if ($policy['policy_graphs'] == 1) {
				$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i IS NULL";
			}else{
				$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i=" . $policy['id'];
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_hosts'] == 1) {
				$sql_having .= " OR (user$i IS NULL";
			}else{
				$sql_having .= " OR (user$i=" . $policy['id'];
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_graph_templates'] == 1) {
				$sql_having .= " $sql_operator user$i IS NULL))";
			}else{
				$sql_having .= " $sql_operator user$i=" . $policy['id'] . "))";
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		$sql_having = "HAVING $sql_having";

		$graphs = db_fetch_assoc("SELECT gti.id, gti.title, gti.rra_id, gti.order_key, gtg.local_graph_id, 
			h.description, gt.name AS template_name, gtg.title_cache, 
			gtg.width, gtg.height, gl.snmp_index, gl.snmp_query_id,
			$sql_select
			FROM graph_templates_graph AS gtg 
			INNER JOIN graph_local AS gl 
			ON gl.id=gtg.local_graph_id 
			INNER JOIN graph_tree_items AS gti
			ON gti.local_graph_id=gl.id
			LEFT JOIN graph_templates AS gt 
			ON gt.id=gl.graph_template_id 
			LEFT JOIN host AS h 
			ON h.id=gl.host_id 
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
				ON gl.id=gtg.local_graph_id 
				INNER JOIN graph_tree_items AS gti
				ON gti.local_graph_id=gl.id
				LEFT JOIN graph_templates AS gt 
				ON gt.id=gl.graph_template_id 
				LEFT JOIN host AS h 
				ON h.id=gl.host_id 
				$sql_join
				$sql_where
				$sql_having
			) AS rower");
	}else{
		$graphs = db_fetch_assoc("SELECT 
			gti.id, gti.title, 
			gti.rra_id, gti.order_key,
			gtg.local_graph_id, 
			host.description, 
			gt.name AS template_name, 
			gtg.title_cache, 
			gtg.width, 
			gtg.height,
			gl.snmp_index,
			gl.snmp_query_id
			FROM graph_templates_graph AS gtg 
			INNER JOIN graph_local AS gl 
			ON gl.id=gtg.local_graph_id 
			INNER JOIN graph_tree_items AS gti
			ON gti.local_graph_id=gl.id
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
		$sql_where .= (strlen($sql_where) ? ' AND ':' ') . " gl.id=$graph_id";
	}

	if (strlen($sql_where)) {
		$sql_where = "WHERE $sql_where";
	}

	$i          = 0;
	$sql_having = '';
	$sql_select = '';
	$sql_join   = '';

	if (read_config_option("auth_method") != 0) {
		if ($user == 0) {
			$user = $_SESSION['sess_user_id'];
		}

		if (read_config_option("graph_auth_method") == 1) {
			$sql_operator = "OR";
		}else{
			$sql_operator = "AND";
		}

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc("SELECT uag.id, 'group' AS type, policy_graphs, policy_hosts, policy_graph_templates FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id=uagm.group_id
			WHERE uag.enabled='on' AND uagm.user_id=$user");
		$policies[] = db_fetch_row("SELECT id, 'user' AS type, policy_graphs, policy_hosts, policy_graph_templates FROM user_auth WHERE id=$user");
		
		foreach($policies as $policy) {
			if ($policy['policy_graphs'] == 1) {
				$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i IS NULL";
			}else{
				$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i=" . $policy['id'];
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_hosts'] == 1) {
				$sql_having .= " OR (user$i IS NULL";
			}else{
				$sql_having .= " OR (user$i=" . $policy['id'];
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_graph_templates'] == 1) {
				$sql_having .= " $sql_operator user$i IS NULL))";
			}else{
				$sql_having .= " $sql_operator user$i=" . $policy['id'] . "))";
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		$sql_having = "HAVING $sql_having";

		$graphs = db_fetch_assoc("SELECT gtg.local_graph_id, h.description, gt.name AS template_name, 
			gtg.title_cache, gtg.width, gtg.height, gl.snmp_index, gl.snmp_query_id,
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
			$sql_having
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(*)
			FROM (
				SELECT $sql_select
				FROM graph_templates_graph AS gtg 
				INNER JOIN graph_local AS gl 
				ON gl.id=gtg.local_graph_id 
				LEFT JOIN graph_templates AS gt 
				ON gt.id=gl.graph_template_id 
				LEFT JOIN host AS h 
				ON h.id=gl.host_id 
				$sql_join
				$sql_where
				$sql_having
			) AS rower");
	}else{
		$graphs = db_fetch_assoc("SELECT 
			gtg.local_graph_id, 
			host.description, 
			gt.name AS template_name, 
			gtg.title_cache, 
			gtg.width, 
			gtg.height,
			gl.snmp_index,
			gl.snmp_query_id
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

function get_allowed_trees($edit = false, $return_sql = false, $sql_where = '', $order_by = 'name', $limit = '', &$total_rows = 0, $user = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	$i          = 0;
	$sql_where1 = '';
	$sql_select = '';
	$sql_join   = '';

	if (read_config_option("auth_method") != 0) {
		if ($user == 0) {
			$user = $_SESSION['sess_user_id'];
		}

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc("SELECT uag.id, 'group' AS type, policy_trees FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id=uagm.group_id
			WHERE uag.enabled='on' AND uagm.user_id=$user");
		$policies[] = db_fetch_row("SELECT id, 'user' as type, policy_trees FROM user_auth WHERE id=$user");

		foreach($policies as $policy) {
			if ($policy['policy_trees'] == "1") {
				$sql_where1 .= (strlen($sql_where1) ? ' OR':'') . " uap$i." . $policy['type'] . "_id IS NULL";
			}elseif ($policy['policy_trees'] == "2") {
				$sql_where1 .= (strlen($sql_where1) ? ' OR':'') . " uap$i." . $policy['type'] . "_id IS NOT NULL";
			}

			$sql_join .= "LEFT JOIN user_auth_" . ($policy['type'] == 'group' ? 'group_':'') . "perms AS uap$i
				ON (gt.id=uap$i.item_id AND uap$i.type=2 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";

			$i++;
		}

		if (strlen($sql_where)) {
			$sql_where = 'WHERE ' . ($edit == false ? '(gt.enabled="on") AND ':'') . '(' . $sql_where . ') AND (' . $sql_where1 . ')';
		}else{
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
		}else{
			$trees = db_fetch_assoc($sql);

			$total_rows = db_fetch_cell("SELECT COUNT(gt.id) 
				FROM graph_tree AS gt
				$sql_join
				$sql_where");
		}
	}else{
		if (strlen($sql_where)) {
			$sql_where = "WHERE $sql_where";
		}

		if ($return_sql) {
			return "SELECT id, name FROM graph_tree $sql_where $order_by";
		}else{
			$templates  = db_fetch_assoc("SELECT id, name FROM graph_tree $sql_where $order_by");
			$total_rows = db_fetch_cell("SELECT COUNT(*) FROM graph_tree $sql_where");
		}
	}

	return $trees;
}

function get_allowed_devices($sql_where = '', $order_by = 'description', $limit = '', &$total_rows = 0, $user = 0, $host_id = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($host_id > 0) {
		$sql_where .= (strlen($sql_where) ? ' AND ':' ') . " gl.host_id=$host_id";
	}

	if (strlen($sql_where)) {
		$sql_where = "WHERE $sql_where";
	}

	$i          = 0;
	$sql_having = '';
	$sql_select = '';
	$sql_join   = '';

	if (read_config_option("auth_method") != 0) {
		if ($user == 0) {
			$user = $_SESSION['sess_user_id'];
		}

		if (read_config_option("graph_auth_method") == 1) {
			$sql_operator = "OR";
		}else{
			$sql_operator = "AND";
		}

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc("SELECT uag.id, 'group' AS type, policy_graphs, policy_hosts, policy_graph_templates FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id=uagm.group_id
			WHERE uag.enabled='on' AND uagm.user_id=$user");
		$policies[] = db_fetch_row("SELECT id, 'user' AS type, policy_graphs, policy_hosts, policy_graph_templates FROM user_auth WHERE id=$user");
		
		foreach($policies as $policy) {
			if ($policy['policy_graphs'] == 1) {
				$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i IS NULL";
			}else{
				$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i=" . $policy['id'];
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_hosts'] == 1) {
				$sql_having .= " OR (user$i IS NULL";
			}else{
				$sql_having .= " OR (user$i=" . $policy['id'];
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_graph_templates'] == 1) {
				$sql_having .= " $sql_operator user$i IS NULL))";
			}else{
				$sql_having .= " $sql_operator user$i=" . $policy['id'] . "))";
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		$sql_having = "HAVING $sql_having";

		$host_list = db_fetch_assoc("SELECT h1.*
			FROM host AS h1
			INNER JOIN (
				SELECT DISTINCT id FROM (
					SELECT h.*, $sql_select
					FROM graph_templates_graph AS gtg 
					INNER JOIN graph_local AS gl 
					ON gl.id=gtg.local_graph_id 
					LEFT JOIN graph_templates AS gt 
					ON gt.id=gl.graph_template_id 
					LEFT JOIN host AS h 
					ON h.id=gl.host_id 
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
				FROM graph_templates_graph AS gtg 
				INNER JOIN graph_local AS gl 
				ON gl.id=gtg.local_graph_id 
				LEFT JOIN graph_templates AS gt 
				ON gt.id=gl.graph_template_id 
				LEFT JOIN host AS h 
				ON h.id=gl.host_id 
				$sql_join
				$sql_where
				$sql_having
			) AS rower");
	}else{
		if (strlen($sql_where)) {
			$sql_where = "WHERE $sql_where";
		}

		$host_list  = db_fetch_assoc("SELECT * 
			FROM host AS h
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(*) FROM host AS h $sql_where");
	}

	return $host_list;
}

function get_allowed_graph_templates($sql_where = '', $order_by = 'name', $limit = '', &$total_rows = 0, $user = 0, $graph_template_id = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($graph_template_id > 0) {
		$sql_where .= (strlen($sql_where) ? ' AND ':' ') . " gl.graph_template_id=$graph_template_id";
	}

	if (strlen($sql_where)) {
		$sql_where = "WHERE $sql_where";
	}

	$i          = 0;
	$sql_having = '';
	$sql_select = '';
	$sql_join   = '';

	if (read_config_option("auth_method") != 0) {
		if ($user == 0) {
			$user = $_SESSION['sess_user_id'];
		}

		if (read_config_option("graph_auth_method") == 1) {
			$sql_operator = "OR";
		}else{
			$sql_operator = "AND";
		}

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc("SELECT uag.id, 'group' AS type, policy_graphs, policy_hosts, policy_graph_templates FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id=uagm.group_id
			WHERE uag.enabled='on' AND uagm.user_id=$user");
		$policies[] = db_fetch_row("SELECT id, 'user' AS type, policy_graphs, policy_hosts, policy_graph_templates FROM user_auth WHERE id=$user");
		
		foreach($policies as $policy) {
			if ($policy['policy_graphs'] == 1) {
				$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i IS NULL";
			}else{
				$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i=" . $policy['id'];
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_hosts'] == 1) {
				$sql_having .= " OR (user$i IS NULL";
			}else{
				$sql_having .= " OR (user$i=" . $policy['id'];
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_graph_templates'] == 1) {
				$sql_having .= " $sql_operator user$i IS NULL))";
			}else{
				$sql_having .= " $sql_operator user$i=" . $policy['id'] . "))";
			}
			$sql_join   .= "LEFT JOIN user_auth_" . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		$sql_having = "HAVING $sql_having";

		$templates = db_fetch_assoc("SELECT gt1.*
			FROM graph_templates AS gt1
			INNER JOIN (
				SELECT DISTINCT id FROM (
					SELECT gt.*, $sql_select
					FROM graph_templates_graph AS gtg 
					INNER JOIN graph_local AS gl 
					ON gl.id=gtg.local_graph_id 
					LEFT JOIN graph_templates AS gt 
					ON gt.id=gl.graph_template_id 
					LEFT JOIN host AS h 
					ON h.id=gl.host_id 
					$sql_join
					$sql_where
					$sql_having
				) AS rs1
			) AS rs2
			ON rs2.id=gt1.id
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT id)
			FROM (
				SELECT gt.id, $sql_select
				FROM graph_templates_graph AS gtg 
				INNER JOIN graph_local AS gl 
				ON gl.id=gtg.local_graph_id 
				LEFT JOIN graph_templates AS gt 
				ON gt.id=gl.graph_template_id 
				LEFT JOIN host AS h 
				ON h.id=gl.host_id 
				$sql_join
				$sql_where
				$sql_having
			) AS rower");
	}else{
		if (strlen($sql_where)) {
			$sql_where = "WHERE $sql_where";
		}

		$host_list  = db_fetch_assoc("SELECT * 
			FROM graph_templates AS gt 
			$order_by
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(*) FROM graph_templates AS gt $sql_where");
	}

	return $templates;
}

/* get_host_array - returns a list of hosts taking permissions into account if necessary
   @returns - (array) an array containing a list of hosts */
function get_host_array() {
	$hosts = get_allowed_devices();

	foreach($hosts as $host) {
		$return_devices[] = $host['description'] . ' (' . $host['hostname'] . ')';
	}

	return $return_devices;
}

