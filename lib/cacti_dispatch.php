<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

/**
 * Centralized action dispatcher with method and realm enforcement.
 *
 * Replaces ad-hoc switch/case blocks on $_REQUEST['action'] with a
 * declarative action table that enforces HTTP method, realm
 * permission, and optional object-level ACL before dispatching.
 */

/**
 * Dispatch the current request to an action handler.
 *
 * Each entry in $actions is keyed by action name and contains:
 *   'callback'   => callable  (required)
 *   'method'     => string    HTTP method: 'GET', 'POST', or 'ANY' (default 'ANY')
 *   'realm'      => int|null  Realm ID required (null = no check, default null)
 *   'object_acl' => callable|null  Extra ACL callback returning bool (default null)
 *
 * @param  array  $actions  Action dispatch table
 * @param  string $default  Action name to use when request var is missing
 *
 * @return void
 */
function cacti_dispatch($actions, $default = '') {
	$action = get_nfilter_request_var('action');

	if ($action === '' || $action === null) {
		$action = $default;
	}

	if (!isset($actions[$action])) {
		cacti_log('WARNING: cacti_dispatch: unknown action "' . $action . '" from ' . get_client_addr(), false, 'WEBUI');

		raise_ajax_permission_denied();

		return;
	}

	$entry = $actions[$action];

	/* enforce HTTP method */
	$method = isset($entry['method']) ? strtoupper($entry['method']) : 'ANY';

	if ($method !== 'ANY' && $_SERVER['REQUEST_METHOD'] !== $method) {
		cacti_log('WARNING: cacti_dispatch: method mismatch for action "' . $action . '" (expected ' . $method . ', got ' . $_SERVER['REQUEST_METHOD'] . ')', false, 'WEBUI');

		header('HTTP/1.1 405 Method Not Allowed');
		header('Allow: ' . $method);

		return;
	}

	/* enforce realm permission */
	if (isset($entry['realm']) && $entry['realm'] !== null) {
		if (!is_realm_allowed($entry['realm'])) {
			cacti_log('WARNING: cacti_dispatch: realm ' . $entry['realm'] . ' denied for action "' . $action . '"', false, 'WEBUI');

			raise_ajax_permission_denied();

			return;
		}
	}

	/* enforce object-level ACL */
	if (isset($entry['object_acl']) && is_callable($entry['object_acl'])) {
		if (!call_user_func($entry['object_acl'])) {
			cacti_log('WARNING: cacti_dispatch: object ACL denied for action "' . $action . '"', false, 'WEBUI');

			raise_ajax_permission_denied();

			return;
		}
	}

	/* dispatch */
	if (is_callable($entry['callback'])) {
		call_user_func($entry['callback']);
	} else {
		cacti_log('ERROR: cacti_dispatch: callback for action "' . $action . '" is not callable', false, 'WEBUI');
	}
}

/**
 * Return a 403 response for permission failures.
 *
 * Wraps the existing raise_ajax_permission_denied() or falls back
 * to a plain header if the function is not yet loaded.
 *
 * @return void
 */
if (!function_exists('raise_ajax_permission_denied')) {
	function raise_ajax_permission_denied() {
		header('HTTP/1.1 403 Forbidden');
		print 'Access Denied';
	}
}
