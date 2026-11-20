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
define('POLLER_VERBOSITY_MEDIUM', 3);

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

$probe_logs = array();

/**
 * Record validation diagnostics with their verbosity level.
 *
 * @param string $message Diagnostic message.
 *
 * @return void No value is returned.
 */
function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['probe_logs'][] = array('message' => $message, 'level' => $level);
}

/**
 * Apply file permissions and return the result with the last log entry.
 *
 * @param string $file RRD file.
 * @param string $base RRA path.
 *
 * @return array Result, log message and log level.
 */
function probe_apply($file, $base = '') {
	$GLOBALS['probe_logs'] = array();
	$result = rrdtool_apply_file_permissions($file, 'BOOST', $base);
	$log    = end($GLOBALS['probe_logs']);

	return array('result' => $result, 'message' => $log ? $log['message'] : '', 'level' => $log ? $log['level'] : null);
}

require dirname(__DIR__, 3) . '/lib/rrd.php';

$root    = $argv[1];
$base    = $root . '/rra';
$outside = $root . '/outside';
mkdir($base, 0700, true);
mkdir($outside, 0700, true);
touch($outside . '/target.rrd');
mkdir($base . '/dir.rrd');
$symlink_available = @symlink($outside, $base . '/pivot') && @symlink($outside . '/target.rrd', $base . '/link.rrd');
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
	'file_missing'  => probe_apply($base . '/missing.rrd', $base),
	'file_no_base'  => probe_apply($base . '/missing.rrd'),
	'file_dotdot'   => probe_apply($base . '/host/../../outside/missing.rrd', $base),
	'file_outside'  => probe_apply($outside . '/target.rrd', $base),
	'file_symlink'  => $symlink_available ? probe_apply($base . '/link.rrd', $base) : null,
	'file_pivot'    => $symlink_available ? probe_apply($base . '/pivot/target.rrd', $base) : null,
	'file_dir'      => probe_apply($base . '/dir.rrd', $base),
), JSON_THROW_ON_ERROR);
