<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-rjvj-r52f-8v5q (script-query sink). On Windows the data-query script
 * runs through cmd.exe, which ignores \" and toggles quote-state on every ",
 * so cacti_escapeshellarg cannot stop & | ^ < > ( ). A rogue monitored device
 * supplies an SNMP index / system field that reaches get_script_query_path();
 * those must be stripped before the argument is quoted.
 */

$source = file_get_contents(dirname(__DIR__, 4) . '/lib/data_query.php');

test('get_script_query_path strips cmd.exe metacharacters on win32 before quoting', function () use ($source) {
	$start = strpos($source, 'function get_script_query_path(');
	expect($start)->not->toBeFalse();
	$body = substr($source, $start, 1200);

	// the strip is gated to win32 and runs before cacti_escapeshellarg
	$strip = strpos($body, "str_replace(array('\"', '&', '|', '^', '<', '>', '(', ')'), '', \$part)");
	$quote = strpos($body, 'cacti_escapeshellarg($part)');
	expect($strip)->not->toBeFalse();
	expect($quote)->not->toBeFalse();
	expect($strip)->toBeLessThan($quote);
	expect($body)->toContain("\$config['cacti_server_os'] == 'win32'");
});

test('a device value with an injected command is neutralised by the strip', function () {
	// model the win32 strip applied in get_script_query_path
	$device = 'eth0" & calc.exe & ';
	$stripped = str_replace(array('"', '&', '|', '^', '<', '>', '(', ')'), '', $device);
	expect($stripped)->toBe('eth0  calc.exe  ');
	expect($stripped)->not->toContain('"');
	expect($stripped)->not->toContain('&');
});
