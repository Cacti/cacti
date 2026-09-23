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

define('CACTI_PATH_LIBRARY', $root . '/lib');

function is_resource_writable(string $path) : bool {
	return is_writable(dirname($path));
}

function csrf_get_secret() : string {
	return str_repeat('a', 64);
}

function csrf_secret_file_path(string $path) : string {
	return (is_dir($path) ? rtrim($path, '/\\') . '/csrf-secret.php' : $path);
}

require_once $root . '/install/functions.php';

$directory = sys_get_temp_dir() . '/cacti-csrf-permissions-' . bin2hex(random_bytes(8));
$file      = $directory . '/csrf-secret.php';
$old_umask = umask(0000);

mkdir($directory, 0700);
umask(0022);

try {
	$created = install_create_csrf_secret($directory);
	clearstatcache(true, $file);
	$mode = (fileperms($file) & 0777);

	fwrite(STDERR, 'created:' . ($created ? 'true' : 'false') . PHP_EOL);
	fwrite(STDERR, 'mode:' . decoct($mode) . PHP_EOL);
} finally {
	umask($old_umask);

	if (file_exists($file)) {
		unlink($file);
	}

	if (is_dir($directory)) {
		rmdir($directory);
	}
}
