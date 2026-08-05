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
define('CACTI_PATH_URL', '/');
define('CACTI_WEB', true);
define('CACTI_CSRF_SECRET', '');
define('IN_CACTI_INSTALL', 1);

if (!function_exists('__')) {
	function __($text, ...$args) {
		return $args ? vsprintf($text, $args) : $text;
	}
}

$auth_json       = true;
$is_request_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
	strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') === 0;

require_once CACTI_PATH_INCLUDE . '/vendor/csrf/csrf-conf.php';

$GLOBALS['csrf']['secret'] = 'issue-7343-integration-test-secret';

// Match step_json.php's outer response buffer before CSRF validation runs.
ob_start();
require_once CACTI_PATH_INCLUDE . '/csrf.php';

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
