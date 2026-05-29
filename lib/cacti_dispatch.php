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
 * cacti_dispatch - Run the current request against a declarative action table.
 *
 * Each entry in $actions is keyed by action name and contains:
 *   'callback'   => callable  (required; the handler)
 *   'method'     => string    'GET', 'POST', or 'ANY' (default 'ANY')
 *   'realm'      => int|null  Realm id that must be allowed (default null)
 *   'object_acl' => callable  Optional per-row ACL returning bool
 *
 * Guards run in method -> realm -> object-ACL order. A fail at any
 * guard logs with category 'WEBUI', emits the matching HTTP status
 * (405 for method, 403 for realm/ACL), and returns. A mis-declared
 * ACL (non-callable) or method (anything other than GET/POST/ANY)
 * denies rather than silently bypasses.
 *
 * NOTE: CSRF is intentionally out of scope. State-changing handlers
 * still need their own form_security() / nonce check; the method gate
 * narrows the attack surface but does not replace it.
 *
 * @param array  $actions Action table (see shape above).
 * @param string $default Action name to use when the request has none.
 *
 * @return void
 */
function cacti_dispatch(array $actions, string $default = ''): void {
	$action  = get_nfilter_request_var('action');

	/* Reject array / non-scalar action inputs before using as an offset
	 * so `?action[]=x` cannot produce a TypeError on isset(). The
	 * strspn() whitelist further rejects any character outside the
	 * action-name alphabet so a hostile key cannot reach the table. */
	if (!is_string($action) || $action === '') {
		$action = $default;
	}

	if ($action !== '' && strspn($action, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-') !== strlen($action)) {
		$action = $default;
	}

	if ($action === '' || !isset($actions[$action])) {
		cacti_log('WARNING: cacti_dispatch: unknown action "' . $action . '" from ' . get_client_addr(), false, 'WEBUI');
		cacti_dispatch_deny(403);

		return;
	}

	$entry = $actions[$action];

	/* Enforce HTTP method. cacti_strtoupper() is preferred over strtoupper()
	 * so the locale does not affect the comparison, and REQUEST_METHOD is
	 * defaulted because CLI contexts (tests) do not set it. A typoed verb
	 * (e.g. 'PUT ' or 'gET') would otherwise silently soft-brick the action
	 * because no real request would match; treat it as misdeclaration. */
	$method         = isset($entry['method']) ? cacti_strtoupper((string) $entry['method']) : 'ANY';
	$request_method = isset($_SERVER['REQUEST_METHOD']) ? cacti_strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';

	if (!in_array($method, ['GET', 'POST', 'ANY'], true)) {
		cacti_log('WARNING: cacti_dispatch: invalid method "' . $method . '" declared for action "' . $action . '"; failing closed', false, 'WEBUI');
		cacti_dispatch_deny(403);

		return;
	}

	if ($method !== 'ANY' && $request_method !== $method) {
		cacti_log('WARNING: cacti_dispatch: method mismatch for action "' . $action . '" (expected ' . $method . ', got ' . $request_method . ')', false, 'WEBUI');
		header('HTTP/1.1 405 Method Not Allowed');
		header('Allow: ' . $method);

		return;
	}

	// Enforce realm permission.
	if (isset($entry['realm']) && !is_realm_allowed($entry['realm'])) {
		cacti_log('WARNING: cacti_dispatch: realm ' . $entry['realm'] . ' denied for action "' . $action . '"', false, 'WEBUI');
		cacti_dispatch_deny(403);

		return;
	}

	/* Enforce object-level ACL. A declared-but-non-callable ACL is
	 * treated as a misdeclaration and fails closed rather than silently
	 * bypassing authorisation. */
	if (array_key_exists('object_acl', $entry) && $entry['object_acl'] !== null) {
		if (!is_callable($entry['object_acl'])) {
			cacti_log('ERROR: cacti_dispatch: object_acl for action "' . $action . '" is not callable', false, 'WEBUI');
			cacti_dispatch_deny(403);

			return;
		}

		if (!call_user_func($entry['object_acl'])) {
			cacti_log('WARNING: cacti_dispatch: object ACL denied for action "' . $action . '"', false, 'WEBUI');
			cacti_dispatch_deny(403);

			return;
		}
	}

	// Dispatch.
	if (!isset($entry['callback']) || !is_callable($entry['callback'])) {
		cacti_log('ERROR: cacti_dispatch: callback for action "' . $action . '" is not callable', false, 'WEBUI');
		cacti_dispatch_deny(500);

		return;
	}

	call_user_func($entry['callback']);
}

/**
 * cacti_dispatch_deny - Emit the denial response for a failed guard.
 *
 * Uses raise_ajax_permission_denied() from lib/functions.php when that
 * helper is already loaded so AJAX callers see the right 401 envelope,
 * and always follows with an explicit 403 Forbidden (or the requested
 * status) header so non-AJAX callers do not fall through to a 200.
 *
 * The helper is prefixed with cacti_dispatch_ to avoid colliding with
 * the existing raise_ajax_permission_denied() function that Cacti
 * loads unconditionally from lib/functions.php.
 *
 * @param int $status HTTP status code to emit. Defaults to 403.
 *
 * @return void
 */
function cacti_dispatch_deny($status = 403) {
	$status = (int) $status;

	if ($status < 400 || $status >= 600) {
		$status = 403;
	}

	$xrw     = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? (string) $_SERVER['HTTP_X_REQUESTED_WITH'] : '';
	$is_ajax = defined('AJAX_REQUEST') || cacti_strtolower($xrw) === 'xmlhttprequest';

	if ($is_ajax && function_exists('raise_ajax_permission_denied')) {
		raise_ajax_permission_denied();
	}

	if (!headers_sent()) {
		http_response_code($status);
	}
}
