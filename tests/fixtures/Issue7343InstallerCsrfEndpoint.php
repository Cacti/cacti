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

$root = dirname(__DIR__, 2);

define('CACTI_PATH_INCLUDE', $root . '/include');
define('CACTI_PATH_LIBRARY', $root . '/lib');
define('CACTI_PATH_URL', '/');
define('CACTI_WEB', true);
define('CACTI_CSRF_SECRET', '');
define('IN_CACTI_INSTALL', 1);
define('POLLER_VERBOSITY_DEBUG', 5);

require_once CACTI_PATH_INCLUDE . '/vendor/autoload.php';

if (!function_exists('__')) {
	function __($text, ...$args) {
		return $args ? vsprintf($text, $args) : $text;
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = 0) {
		return false;
	}
}

$auth_json       = true;
$is_request_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
	strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') === 0;

// The guard keeps its tokens in $_SESSION, and csrf_error_callback() rotates
// the session id before minting the replacement, so the session has to be
// live first.  include/global.php calls cacti_session_start() the same way.
session_start();

// Match step_json.php's outer response buffer before CSRF validation runs.
ob_start();
require_once CACTI_PATH_INCLUDE . '/csrf.php';

// csrf-magic ran its check from the bottom of its own file; the guard is
// invoked explicitly, as include/global.php does.
csrf_startup();

// Reaching this point means csrf_check() accepted the follow-up token. The
// production endpoint would now construct and serialize the Installer.
$response = json_encode(
	[
		'csrfValidated'  => true,
		'csrfMagicToken' => csrf_get_tokens(),
	],
	JSON_INVALID_UTF8_SUBSTITUTE
);

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($response));
print $response;
