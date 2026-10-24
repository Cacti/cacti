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
 * The ss_* ports of the Perl and Python data input scripts run inside the
 * long-lived script server. Loading one there must print nothing, and a
 * rejected argument must return U to the caller without ending the process.
 * Each script is loaded in a fresh PHP process so that this holds for the
 * script on its own.
 */

test('loads silently under the script server and returns U for a rejected argument', function (string $script, string $function, array $args) {
	$file  = dirname(__DIR__, 4) . '/scripts/' . $script;
	$probe = '$called_by_script_server = true;'
		. 'ob_start();'
		. 'require_once ' . var_export($file, true) . ';'
		. '$loaded = ob_get_clean();'
		. 'ob_start();'
		. '$value = call_user_func_array(' . var_export($function, true) . ', ' . var_export($args, true) . ');'
		. '$printed = ob_get_clean();'
		. 'print json_encode([$loaded, $printed, $value, "alive"]);';

	$process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', '-r', $probe], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$stdout  = stream_get_contents($pipes[1]);
	$stderr  = stream_get_contents($pipes[2]);

	fclose($pipes[1]);
	fclose($pipes[2]);

	expect(proc_close($process))->toBe(0)
		->and($stderr)->toBe('')
		->and(json_decode((string) $stdout, true))->toBe(['', '', 'U', 'alive']);
})->with([
	['ss_linux_memory.php', 'ss_linux_memory', ['bad key']],
	['ss_loadavg.php', 'ss_loadavg', ['x']],
	['ss_unix_users.php', 'ss_unix_users', ['a;b']],
	['ss_unix_tcp_connections.php', 'ss_unix_tcp_connections', ['a b']],
	['ss_ping.php', 'ss_ping', ['-f']],
	['ss_webhits.php', 'ss_webhits', ['/nonexistent/log']],
	['ss_query_unix_partitions.php', 'ss_query_unix_partitions', ['bogus']],
	['ss_motorola_surfboard_SB6141.php', 'ss_motorola_surfboard_SB6141', ['Bogus', 'x']]
]);

test('the parameterless ports return a value under the script server', function (string $script, string $function, string $pattern) {
	$file  = dirname(__DIR__, 4) . '/scripts/' . $script;
	$probe = '$called_by_script_server = true;'
		. 'ob_start();'
		. 'require_once ' . var_export($file, true) . ';'
		. '$value = ' . $function . '();'
		. '$printed = ob_get_clean();'
		. 'print json_encode([$printed, $value]);';

	$process = proc_open([PHP_BINARY, '-r', $probe], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$stdout  = stream_get_contents($pipes[1]);

	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($process);

	[$printed, $value] = json_decode((string) $stdout, true);

	expect($printed)->toBe('')->and($value)->toMatch($pattern);
})->with([
	['ss_loadavg_multi.php', 'ss_loadavg_multi', '/^1min:\S+ 5min:\S+ 10min:\S+$/'],
	['ss_unix_processes.php', 'ss_unix_processes', '/^\d+$/'],
	['ss_diskfree.php', 'ss_diskfree', '/^(megabytes:\d+ percent:\d+)+$/']
]);
