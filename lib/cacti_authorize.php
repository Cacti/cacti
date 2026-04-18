<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * cacti_authorize_resource - returns true iff the given user has ownership
 * or admin-level access to a specific resource row.
 *
 * Root-cause mitigation for IDOR (Insecure Direct Object Reference) bugs:
 * endpoints that accept a resource ID from the request and act on it
 * without checking that the current user is allowed to touch that row.
 *
 * Applies to:
 *   GHSA-8p2f-6jvx-j75j (Reports IDOR — any authenticated user can modify
 *                        reports owned by other users)
 *
 * The helper is intentionally strict:
 *   - unknown resource_type returns false (fail closed)
 *   - a user who is not the owner AND not a system admin returns false
 *   - missing resource row returns false (don't leak existence)
 *
 * Extend by adding a new case to the resource-type switch below; the same
 * ownership predicate then applies to every endpoint that calls this helper.
 *
 * @param int    $user_id        The id of the acting user (from $_SESSION[SESS_USER_ID])
 * @param int    $resource_id    The id of the row being acted on
 * @param string $resource_type  A short string naming the resource (e.g. 'reports')
 *
 * @return bool  true if the user may act on the resource, false otherwise
 */
function cacti_authorize_resource($user_id, $resource_id, $resource_type) {
	$user_id     = (int) $user_id;
	$resource_id = (int) $resource_id;

	if ($user_id <= 0 || $resource_id <= 0) {
		return false;
	}

	// Admins bypass ownership for any resource they can reach via realm perms.
	if (cacti_authorize_is_admin($user_id)) {
		return true;
	}

	switch ($resource_type) {
		case 'reports':
			$owner = db_fetch_cell_prepared(
				'SELECT user_id FROM reports WHERE id = ?',
				array($resource_id)
			);

			return $owner !== false && $owner !== null && (int) $owner === $user_id;

		case 'graph_tree':
			$owner = db_fetch_cell_prepared(
				'SELECT user_id FROM graph_tree WHERE id = ?',
				array($resource_id)
			);

			return $owner !== false && $owner !== null && (int) $owner === $user_id;

		case 'settings_user':
			// Users may only read/write their own settings row.
			return $resource_id === $user_id;

		default:
			// Unknown type — fail closed. Extend this function to opt in.
			return false;
	}
}

/**
 * cacti_authorize_is_admin - returns true iff the user holds the system
 * admin realm (realm 1 in Cacti's user_auth_realm table).
 *
 * Cached per-request to avoid hammering the DB on hot paths. Intentionally
 * a private helper — callers should use cacti_authorize_resource() which
 * consults this internally.
 *
 * @param int $user_id
 *
 * @return bool
 */
function cacti_authorize_is_admin($user_id) {
	static $admin_cache = array();

	$user_id = (int) $user_id;

	if (isset($admin_cache[$user_id])) {
		return $admin_cache[$user_id];
	}

	$is_admin = (bool) db_fetch_cell_prepared(
		'SELECT 1 FROM user_auth_realm WHERE user_id = ? AND realm_id = 1',
		array($user_id)
	);

	$admin_cache[$user_id] = $is_admin;

	return $is_admin;
}
