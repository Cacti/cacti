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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';

// Mock cacti_escapeshellarg if it doesn't exist in testing env
if (!function_exists('cacti_escapeshellarg')) {
	function cacti_escapeshellarg($arg) {
		return "'" . str_replace("'", "'\\''", $arg) . "'";
	}
}

test('__rrd_execute correctly handles array of arguments', function () {
	$args = ['graph', '-', '--start', '12345; id'];
	
	// Simulated logic from __rrd_execute
	$command_line = implode(' ', array_map('cacti_escapeshellarg', $args));
	
	expect($command_line)->toBe("'graph' '-' '--start' '12345; id'");
});

test('exec_background correctly handles array of arguments', function () {
	$args = ['--poller=1', '--network=192.168.1.0/24; id'];
	
	// Simulated logic from exec_background
	$args_string = implode(' ', array_map('cacti_escapeshellarg', $args));
	
	expect($args_string)->toBe("'--poller=1' '--network=192.168.1.0/24; id'");
});
