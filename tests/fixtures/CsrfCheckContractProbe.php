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

/*
 * Exercises csrf_check() in its own process so include/csrf.php's control
 * flow runs for real, exit() included, instead of being inferred from source
 * text. The default $fatal=true path calls exit() on a failed check, which
 * would kill the Pest process itself if invoked in-process; a subprocess is
 * the only way to observe that path actually running.
 *
 * Constants and stubs are all guard-defined so this also tolerates running
 * after a real include/global.php has already declared them, though in
 * practice it always runs standalone.
 *
 * Modes, selected by argv[1]:
 *   get           - non-POST request
 *   nonfatal-fail - POST with a bad token, csrf_check(false)
 *   fatal-fail    - POST with a bad token, csrf_check() (default $fatal)
 */

$root = dirname(__DIR__, 2);

if (!defined('CACTI_PATH_INCLUDE')) {
	define('CACTI_PATH_INCLUDE', $root . '/include');
}

if (!defined('CACTI_PATH_LIBRARY')) {
	define('CACTI_PATH_LIBRARY', $root . '/lib');
}

if (!defined('CACTI_PATH_URL')) {
	define('CACTI_PATH_URL', '/');
}

if (!defined('CACTI_WEB')) {
	// Disabled-guard branch: csrf_guard() short-circuits on !CACTI_WEB before
	// touching Symfony, so validate() always fails without a real token
	// manager. That is exactly what a probe of the bad-token paths needs.
	define('CACTI_WEB', false);
}

if (!defined('CACTI_CSRF_SECRET')) {
	define('CACTI_CSRF_SECRET', '');
}

if (!defined('POLLER_VERBOSITY_DEBUG')) {
	define('POLLER_VERBOSITY_DEBUG', 5);
}

require_once CACTI_PATH_INCLUDE . '/vendor/autoload.php';

if (!function_exists('__')) {
	function __($text, ...$args) : string {
		return ($args ? vsprintf($text, $args) : $text);
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = 0) {
		return false;
	}
}

if (!function_exists('raise_message')) {
	function raise_message($message_id, $message = '', $message_level = 0, $message_title = null) {
		return true;
	}
}

if (!function_exists('sanitize_uri')) {
	function sanitize_uri($uri) {
		return $uri;
	}
}

$mode = ($argv[1] ?? '');

$_SERVER['REQUEST_URI'] = '/test';

if ($mode === 'get') {
	$_SERVER['REQUEST_METHOD'] = 'GET';
} else {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST                     = ['__csrf_magic' => 'not-a-real-token'];
}

session_start();
ob_start();

require_once CACTI_PATH_INCLUDE . '/csrf.php';

fwrite(STDERR, "before\n");

$result = match ($mode) {
	'get'           => csrf_check(),
	'nonfatal-fail' => csrf_check(false),
	'fatal-fail'    => csrf_check(),
	default         => (function () {
		throw new InvalidArgumentException('unknown mode');
	})(),
};

fwrite(STDERR, 'result:' . var_export($result, true) . "\n");
fwrite(STDERR, "after\n");
