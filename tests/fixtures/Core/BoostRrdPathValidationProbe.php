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

/**
 * Return an empty setting for the isolated path-validation probe.
 *
 * @param string $name Setting name.
 *
 * @return string Empty setting value.
 */
function read_config_option($name) {
	return '';
}

/**
 * Ignore expected validation diagnostics.
 *
 * @param string $message Diagnostic message.
 *
 * @return void No value is returned.
 */
function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
}

require dirname(__DIR__, 3) . '/lib/rrd.php';

$root    = $argv[1];
$base    = $root . '/rra';
$outside = $root . '/outside';
mkdir($base, 0700, true);
mkdir($outside, 0700, true);
$symlink_available = @symlink($outside, $base . '/pivot');
$permissions_ok = null;

print json_encode(array(
	'invalid_empty' => rrdtool_local_path_is_valid('', ''),
	'missing_base'  => rrdtool_local_path_is_valid($root . '/missing', $root . '/missing/file'),
	'must_exist'    => rrdtool_local_path_is_valid($base, $base . '/missing.rrd', true),
	'existing_base' => rrdtool_local_path_is_valid($base, $base, true),
	'valid_missing' => rrdtool_local_path_is_valid($base, $base . '/host/graph'),
	'dotdot'        => rrdtool_local_path_is_valid($base, $base . '/host/../escape'),
	'prefix'        => rrdtool_local_path_is_valid($base, $base . '-other/escape'),
	'symlink'       => $symlink_available ? rrdtool_local_path_is_valid($base, $base . '/pivot/file') : null,
	'create_outside'=> rrdtool_create_structured_directory($base, $outside . '/escape', 'BOOST', $permissions_ok),
	'create_existing' => rrdtool_create_structured_directory($base, $base, 'BOOST', $permissions_ok),
	'file_missing'  => rrdtool_apply_file_permissions($base . '/missing.rrd', 'BOOST', $base),
	'file_no_base'  => rrdtool_apply_file_permissions($base . '/missing.rrd', 'BOOST'),
), JSON_THROW_ON_ERROR);
