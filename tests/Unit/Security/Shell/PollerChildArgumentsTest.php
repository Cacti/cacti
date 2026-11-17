<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace PollerChildArgumentsTest;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 4);
cacti_fixture_source_function($root . '/poller_commands.php', 'commands_launch_child', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/functions.php', 'cacti_escapeshellarg', __NAMESPACE__);

function read_config_option($name, $force = false) {
	return '/fixture/php';
}
function commands_debug($message) : void {
}
function cacti_log($message, $output = false, $environ = 'SYSTEM', $verbosity = POLLER_VERBOSITY_NONE, $force = false) : void {
}
function exec_background($filename, $args = '') : void {
	$GLOBALS['poller_child_capture'][] = [$filename, $args];
}

test('command children preserve a quoted checkout path and one numeric host argument', function ($host, $expected, $debug) {
	global $config, $seebug;
	$old_config = $config;
	$old_debug = $seebug ?? null;
	try {
		$config = ['base_path' => "/fixture/path with ' quote", 'cacti_server_os' => 'unix'];
		$seebug = $debug;
		$GLOBALS['poller_child_capture'] = [];
		commands_launch_child($host);
		expect($GLOBALS['poller_child_capture'])->toBe([['/fixture/php', escapeshellarg($config['base_path'] . '/poller_commands.php') . ' --child=' . $expected . ($debug ? ' --debug' : '')]]);
	} finally {
		$config = $old_config;
		$seebug = $old_debug;
		unset($GLOBALS['poller_child_capture']);
	}
})->with([[7, 7, false], ['7 trailing text', 7, true], ['nonnumeric', 0, false], [-1, -1, true]]);
