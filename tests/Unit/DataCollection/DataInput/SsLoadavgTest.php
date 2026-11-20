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
 * scripts/ss_loadavg.php and scripts/ss_loadavg_multi.php, the PHP ports of
 * loadavg.pl and loadavg_multi.pl.
 */

$called_by_script_server = true;

require_once dirname(__DIR__, 4) . '/scripts/ss_loadavg.php';
require_once dirname(__DIR__, 4) . '/scripts/ss_loadavg_multi.php';

test('split takes the three averages from /proc/loadavg', function () {
	expect(ss_loadavg_split("0.14 0.13 0.10 1/234 5678\n"))->toBe(['0.14', '0.13', '0.10'])
		->and(ss_loadavg_multi_split("12.50 3.07 0.00 2/99 1\n"))->toBe(['12.50', '3.07', '0.00']);
});

test('split rejects malformed /proc/loadavg text', function (string $text) {
	expect(ss_loadavg_split($text))->toBeNull()
		->and(ss_loadavg_multi_split($text))->toBeNull();
})->with(['', "0.14 0.13\n", "0,14 0,13 0,10 1/2 3\n", 'a b c d e']);

test('loadavg selects the 1, 5 and 15 minute averages with its 5, 15 and 30 labels', function () {
	$averages = ['0.14', '0.13', '0.10'];

	expect(ss_loadavg_format($averages, '5', 'Linux'))->toBe('0.14')
		->and(ss_loadavg_format($averages, '15', 'Linux'))->toBe('0.13')
		->and(ss_loadavg_format($averages, '30', 'Linux'))->toBe('0.10');
});

test('loadavg without a selector prints uptime minus its first comma', function () {
	$averages = ['0.14', '0.13', '0.10'];

	expect(ss_loadavg_format($averages, '', 'Linux'))->toBe('0.14 0.13, 0.10')
		->and(ss_loadavg_format($averages, '1', 'BSD'))->toBe('0.14 0.13, 0.10')
		->and(ss_loadavg_format($averages, '', 'Darwin'))->toBe('0.14 0.13 0.10');
});

test('loadavg rejects a selector that is not a number', function (string $minutes) {
	expect(ss_loadavg_collect($minutes))->toBe(['U', 1])
		->and(ss_loadavg($minutes))->toBe('U');
})->with(['x', '5;id', '-5', '5 15', '1.5']);

test('loadavg reads the running host', function () {
	[$value, $status] = ss_loadavg_collect('5');

	expect($status)->toBe(0)->and($value)->toMatch('/^\d+\.\d{2}$/');

	[$value, $status] = ss_loadavg_collect();

	expect($status)->toBe(0)->and($value)->toMatch('/^\d+\.\d{2},? \d+\.\d{2},? \d+\.\d{2}$/');
});

test('loadavg_multi labels the three averages as loadavg_multi.pl does', function () {
	expect(ss_loadavg_multi_format(['0.14', '0.13', '0.10']))->toBe('1min:0.14 5min:0.13 10min:0.10');
});

test('loadavg_multi reads the running host', function () {
	[$value, $status] = ss_loadavg_multi_collect();

	expect($status)->toBe(0)
		->and($value)->toMatch('/^1min:\d+\.\d{2} 5min:\d+\.\d{2} 10min:\d+\.\d{2}$/')
		->and(ss_loadavg_multi())->toMatch('/^1min:/');
});

test('missing load average facilities return unknown without terminating the script server', function () {
	$root = dirname(__DIR__, 4);
	$probe = '$called_by_script_server = true;'
		. 'require ' . var_export($root . '/scripts/ss_loadavg.php', true) . ';'
		. 'require ' . var_export($root . '/scripts/ss_loadavg_multi.php', true) . ';'
		. 'print json_encode([ss_loadavg_collect(), ss_loadavg_multi_collect(), "alive"]);';

	// Hide /proc and remove the function that PHP does not provide on Windows.
	$process = proc_open([PHP_BINARY, '-d', 'disable_functions=sys_getloadavg', '-d', 'open_basedir=' . $root, '-r', $probe], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	expect(proc_close($process))->toBe(0)
		->and($stderr)->toBe('')
		->and(json_decode($stdout, true))->toBe([['U', 1], ['1min:U 5min:U 10min:U', 1], 'alive']);
});
