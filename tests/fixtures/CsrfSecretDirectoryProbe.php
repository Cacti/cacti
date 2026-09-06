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

$root      = dirname(__DIR__, 2);
$directory = sys_get_temp_dir() . '/cacti-csrf-directory-' . bin2hex(random_bytes(8));
$file      = $directory . '/csrf-secret.php';
$secret    = '<?php $secret = "' . str_repeat('ab', 20) . '";' . PHP_EOL;

mkdir($directory, 0700);
file_put_contents($file, $secret);

define('CACTI_PATH_INCLUDE', $root . '/include');
define('CACTI_PATH_LIBRARY', $root . '/lib');
define('CACTI_PATH_URL', '/');
define('CACTI_WEB', true);
define('CACTI_CSRF_SECRET', $directory);
define('POLLER_VERBOSITY_DEBUG', 5);

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = 0) {
	return false;
}

require_once CACTI_PATH_INCLUDE . '/vendor/autoload.php';
require_once CACTI_PATH_INCLUDE . '/csrf.php';

session_start();

$time  = time();
$inner = hash_hmac('sha1', $time . ':' . session_id(), $secret);
$outer = hash_hmac('sha1', $inner, $secret);
$token = 'sid:' . $outer . ',' . $time;

try {
	fwrite(STDERR, 'path:' . csrf_secret_file_path(CACTI_CSRF_SECRET) . PHP_EOL);
	fwrite(STDERR, 'reader:' . (csrf_get_secret() === $secret ? 'true' : 'false') . PHP_EOL);
	fwrite(STDERR, 'legacy:' . (csrf_guard()->validate($token) ? 'true' : 'false') . PHP_EOL);
} finally {
	session_write_close();
	unlink($file);
	rmdir($directory);
}
