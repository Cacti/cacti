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

/*
 * Tests for command injection hardening in graph_realtime.php.
 *
 * grv('local_graph_id') was interpolated into shell_exec via sprintf without
 * escaping. The fix casts to (int), uses cacti_escapeshellcmd for the PHP
 * binary, and cacti_escapeshellarg for the script path.
 */

$graphRealtimePath = __DIR__ . '/../../graph_realtime.php';

// --- graph_realtime.php: shell escaping for poller invocation ---

test('graph_realtime.php uses cacti_escapeshellcmd for PHP binary', function () use ($graphRealtimePath) {
	$contents = file_get_contents($graphRealtimePath);

	expect($contents)->toContain("cacti_escapeshellcmd(read_config_option('path_php_binary')");
});

test('graph_realtime.php uses cacti_escapeshellarg for poller_realtime script path', function () use ($graphRealtimePath) {
	$contents = file_get_contents($graphRealtimePath);

	expect($contents)->toContain('cacti_escapeshellarg(CACTI_PATH_BASE');
	expect($contents)->toContain('poller_realtime.php');
});

test('graph_realtime.php casts local_graph_id to int before shell_exec', function () use ($graphRealtimePath) {
	$contents = file_get_contents($graphRealtimePath);

	expect($contents)->toMatch('/\(int\)\s+gfrv\s*\(\s*[\'"]local_graph_id[\'"]\s*\)/');
});

test('graph_realtime.php quotes the realtime poller token', function () use ($graphRealtimePath) {
	$contents = file_get_contents($graphRealtimePath);

	expect($contents)->toContain('cacti_escapeshellarg($poller_id)');
	expect($contents)->toContain("hash('sha256', session_id())");
});

test('graph_realtime.php does not pass raw grv local_graph_id to sprintf for shell', function () use ($graphRealtimePath) {
	$contents = file_get_contents($graphRealtimePath);

	expect($contents)->not->toMatch('/sprintf\s*\([^)]*grv\s*\(\s*[\'"]local_graph_id[\'"]\s*\)/');
});

test('poller_realtime.php keeps poller_id as a validated string token', function () {
	$contents = file_get_contents(__DIR__ . '/../../poller_realtime.php');

	expect($contents)->toContain('$poller_id = (string) $value;');
	expect($contents)->toContain("preg_match('/^(?:[0-9]+|[A-Fa-f0-9]{64})$/', \$poller_id)");
	expect($contents)->toContain('function process_poller_output_rt(mixed $rrdtool_pipe, string $poller_id, int $interval) : int');
	expect($contents)->not->toContain('$poller_id = intval($value);');
});
