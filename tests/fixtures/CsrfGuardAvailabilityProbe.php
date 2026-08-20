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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 2);
$mode = ($argv[1] ?? '');

define('CACTI_PATH_INCLUDE', $root . '/include');
define('CACTI_PATH_LIBRARY', $root . '/lib');
define('CACTI_PATH_URL', '/');
define('CACTI_WEB', true);
define('CACTI_CSRF_SECRET', '');
define('POLLER_VERBOSITY_DEBUG', 5);

if ($mode === 'installer') {
	define('IN_CACTI_INSTALL', 1);
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = 0) {
	fwrite(STDERR, 'log:' . $message . PHP_EOL);

	return false;
}

register_shutdown_function(function () {
	fwrite(STDERR, 'status:' . http_response_code() . PHP_EOL);
});

/* Deliberately do not register Composer's autoloader: this probe exercises
 * the missing/partial vendor-tree branch rather than Symfony itself. */
require_once CACTI_PATH_INCLUDE . '/csrf.php';

$guard = csrf_guard();

fwrite(STDERR, 'enabled:' . ($guard->isEnabled() ? 'true' : 'false') . PHP_EOL);
