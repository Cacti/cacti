<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

if ($argc !== 4) {
	exit(64);
}

define('CACTI_LOCALE', 'en-US');

/**
 * Return an empty setting for the isolated identity probe.
 *
 * @param string $name Setting name.
 *
 * @return string Empty setting value.
 */
function read_config_option($name) {
	return '';
}

/**
 * Write identity-probe diagnostics to standard error.
 *
 * @param string $message Diagnostic message.
 *
 * @return void No value is returned.
 */
function cacti_log($message) {
	fwrite(STDERR, $message . PHP_EOL);
}

require dirname(__DIR__, 3) . '/lib/rrd.php';

$base = $argv[1];
$directory = $argv[2];
$file = $argv[3];
$old_umask = umask(0077);
$permissions_ok = null;

if (!rrdtool_create_structured_directory($base, $directory, 'BOOST', $permissions_ok) || !$permissions_ok) {
	exit(65);
}

if (file_put_contents($file, 'first-writer') === false || !rrdtool_apply_file_permissions($file, 'BOOST', $base)) {
	exit(66);
}

umask($old_umask);
clearstatcache(true);

print json_encode(array(
	'directory_owner' => fileowner($directory),
	'directory_group' => filegroup($directory),
	'directory_mode'  => fileperms($directory) & 07777,
	'file_owner'      => fileowner($file),
	'file_group'      => filegroup($file),
	'file_mode'       => fileperms($file) & 0777,
), JSON_THROW_ON_ERROR);
