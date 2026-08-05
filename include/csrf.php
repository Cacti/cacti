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
  | Cacti: The Complete RRDTool-based Graphing Solution                     |
  +-------------------------------------------------------------------------+
  | This code is designed, written, and maintained by the Cacti Group. See  |
  | about.php and/or the AUTHORS file for specific developer information.   |
  +-------------------------------------------------------------------------+
  | http://www.cacti.net/                                                   |
  +-------------------------------------------------------------------------+
*/

require_once(CACTI_PATH_INCLUDE . '/vendor/csrf/csrf-conf.php');

// cross site request forgery library
function csrf_startup() : void {
	if (CACTI_WEB) {
		// If you need to debug CSRF, uncomment the following line
		// csrf_conf('log_file', dirname(read_config_option('path_cactilog')) . '/csrf.log');
		if (CACTI_CSRF_SECRET != '') {
			csrf_conf('path_secret', CACTI_CSRF_SECRET);
		}

		csrf_conf('rewrite-js', CACTI_PATH_URL . 'include/vendor/csrf/csrf-magic.js');
		csrf_conf('callback', 'csrf_error_callback');
		csrf_conf('expires', 7200);
	} else {
		csrf_conf('disable',true);
	}
}

function csrf_error_callback() : void {
	// Resolve session fixation for PHP 5.4
	session_regenerate_id();

	if (defined('IN_CACTI_INSTALL') &&
		isset($GLOBALS['auth_json']) &&
		$GLOBALS['auth_json'] === true &&
		!empty($GLOBALS['is_request_ajax'])) {
		$response = json_encode(
			[
				'error'          => 'csrf_timeout',
				'title'          => __('Session Expired'),
				'message'        => __('Your installer session expired due to inactivity. Reload the installer and try again.'),
				'csrfMagicToken' => csrf_get_tokens(),
			],
			JSON_INVALID_UTF8_SUBSTITUTE
		);

		if ($response === false) {
			$response = '{"error":"csrf_timeout","title":"Session Expired","message":"Reload the installer and try again."}';
		}

		ob_end_clean();
		http_response_code(403);
		header('Content-Type: application/json');
		header('Cache-Control: no-store');
		header('X-Content-Type-Options: nosniff');
		header('Content-Length: ' . strlen($response));
		print $response;
		csrf_log(__FUNCTION__, 'Timeout, returning JSON response for the installer');

		exit;
	}

	raise_message('csrf_timeout');
	ob_end_clean();
	header('Location: ' . sanitize_uri($_SERVER['REQUEST_URI']));
	csrf_log(__FUNCTION__, 'Timeout, redirecting to ' . sanitize_uri($_SERVER['REQUEST_URI']));

	exit;
}

include_once(CACTI_PATH_INCLUDE . '/vendor/csrf/csrf-magic.php');
