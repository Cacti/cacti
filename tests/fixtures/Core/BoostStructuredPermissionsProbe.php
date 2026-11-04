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

if ($argc !== 2) {
	exit(64);
}

define('CACTI_LOCALE', 'en-US');

$probe_root = $argv[1];
$probe_logs = array();

/**
 * Return an empty setting for the structured-permission probe.
 *
 * @param string $name Setting name.
 *
 * @return string Empty setting value.
 */
function read_config_option($name) {
	return '';
}

/**
 * Capture one permission diagnostic for assertion output.
 *
 * @param string $message Diagnostic message.
 *
 * @return void No value is returned.
 */
function cacti_log($message) {
	global $probe_logs;

	$probe_logs[] = $message;
}

require dirname(__DIR__, 3) . '/lib/rrd.php';

$base   = $probe_root . '/rra';
$nested = $base . '/host/graph';
$file   = $nested . '/sample.rrd';

mkdir($base, 0770, true);

if (function_exists('posix_getgid')) {
	chgrp($base, posix_getgid());
}

chmod($base, 02770);
$expected_group = filegroup($base);
$old_umask = umask(0077);
$permissions_ok = null;
$created = rrdtool_create_structured_directory($base, $nested, 'BOOST', $permissions_ok);
file_put_contents($file, 'rrd');
chmod($file, 0600);
$file_ok = rrdtool_apply_file_permissions($file, 'BOOST', $base);
umask($old_umask);
clearstatcache(true);

print json_encode(array(
	'created'          => $created,
	'permissions_ok'   => $permissions_ok,
	'nested_group'     => filegroup($nested),
	'expected_group'   => $expected_group,
	'nested_mode'      => fileperms($nested) & 07777,
	'file_group'       => filegroup($file),
	'file_mode'        => fileperms($file) & 0777,
	'file_permissions' => $file_ok,
	'logs'             => $probe_logs,
), JSON_THROW_ON_ERROR);
