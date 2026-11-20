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

if ($argc !== 3) {
	exit(64);
}

define('CACTI_LOCALE', 'en-US');
define('POLLER_VERBOSITY_DEBUG', 5);
define('RRDTOOL_OUTPUT_STDOUT', 0);
define('RRDTOOL_OUTPUT_GRAPH_DATA', 1);
define('RRDTOOL_OUTPUT_NULL', 2);
define('RRDTOOL_OUTPUT_BOOLEAN', 3);
define('RRDTOOL_OUTPUT_STDERR', 4);
define('RRDTOOL_OUTPUT_RETURN_STDERR', 5);

$probe_rrdtool = $argv[1];
$probe_path    = $argv[2];
$config = array(
	'cacti_server_os' => 'unix',
	'is_web'          => false,
);

/**
 * Return the RRDtool binary selected by the protocol probe.
 *
 * @param string $name Setting name.
 *
 * @return string Configured binary or an empty setting.
 */
function read_config_option($name) {
	global $probe_rrdtool;

	return $name === 'path_rrdtool' ? $probe_rrdtool : '';
}

/**
 * Ignore Cacti log messages in the protocol probe.
 *
 * @return void No value is returned.
 */
function cacti_log() {
}

/**
 * Stand in for Cacti session cleanup in the CLI probe.
 *
 * @return void No value is returned.
 */
function cacti_session_close() {
}

/**
 * Quote one shell argument using the platform implementation.
 *
 * @param string $value Argument value.
 *
 * @return string Quoted argument.
 */
function cacti_escapeshellarg($value) {
	return escapeshellarg($value);
}

require dirname(__DIR__, 3) . '/lib/rrd.php';

$pipe = rrd_init(true, true);

if (!is_resource($pipe)) {
	exit(65);
}

$responses = array();
$responses[] = rrdtool_execute('create ' . escapeshellarg($probe_path) .
	' --start 1700000000 --step 60 DS:value:GAUGE:120:U:U RRA:AVERAGE:0.5:1:10',
	false, RRDTOOL_OUTPUT_RETURN_STDERR, $pipe, 'BOOST');
$responses[] = rrdtool_execute('update ' . escapeshellarg($probe_path) . ' 1700000060:1',
	false, RRDTOOL_OUTPUT_RETURN_STDERR, $pipe, 'BOOST');
$responses[] = rrdtool_execute('update ' . escapeshellarg($probe_path) . ' 1700000060:2',
	false, RRDTOOL_OUTPUT_RETURN_STDERR, $pipe, 'BOOST');
$responses[] = rrdtool_execute('update ' . escapeshellarg($probe_path) . ' 1700000120:3',
	false, RRDTOOL_OUTPUT_RETURN_STDERR, $pipe, 'BOOST');

rrd_close($pipe);
print json_encode($responses, JSON_THROW_ON_ERROR);
