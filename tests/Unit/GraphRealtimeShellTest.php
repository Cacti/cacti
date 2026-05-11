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
 * Tests for command-injection hardening in graph_realtime.php.
 *
 * History: grv('local_graph_id') used to be interpolated into a shell_exec
 * string via sprintf without escaping. The original fix wrapped values in
 * cacti_escapeshellcmd / cacti_escapeshellarg. The current fix routes the
 * spawn through CactiProcess::run([...]) in array-mode argv, which removes
 * the shell entirely and makes interpretation of metacharacters impossible.
 *
 * These tests assert on the structural invariants that close the bug, not
 * on a specific escaping idiom. Either escapeshellarg-style or array-argv
 * style satisfies the contract.
 */

$graphRealtimePath = __DIR__ . '/../../graph_realtime.php';

test('graph_realtime.php casts local_graph_id to int', function () use ($graphRealtimePath) {
	$contents = file_get_contents($graphRealtimePath);

	expect($contents)->toMatch('/\(int\)\s+gfrv\s*\(\s*[\'"]local_graph_id[\'"]\s*\)/');
});

test('graph_realtime.php does not pass raw grv local_graph_id to sprintf for shell', function () use ($graphRealtimePath) {
	$contents = file_get_contents($graphRealtimePath);

	expect($contents)->not->toMatch('/sprintf\s*\([^)]*grv\s*\(\s*[\'"]local_graph_id[\'"]\s*\)/');
});

test('graph_realtime.php does not invoke shell_exec for the poller spawn', function () use ($graphRealtimePath) {
	$contents = file_get_contents($graphRealtimePath);

	expect($contents)->not->toContain('shell_exec(');
});

test('graph_realtime.php spawns the poller through CactiProcess in array-argv mode', function () use ($graphRealtimePath) {
	$contents = file_get_contents($graphRealtimePath);

	expect($contents)->toContain('CactiProcess::run');
	expect($contents)->toContain('poller_realtime.php');
});

test('graph_realtime.php disables the default process timeout for realtime poller spawn', function () use ($graphRealtimePath) {
	$contents = file_get_contents($graphRealtimePath);

	expect($contents)->toMatch("/CactiProcess::run\\s*\\([\\s\\S]*'timeout'\\s*=>\\s*null[\\s\\S]*'expected_exit_codes'/");
});
