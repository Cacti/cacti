<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 4);

(function () use ($root) {
	$config = array(
		'php_snmp_support' => false,
		'include_path'     => $root . '/include'
	);

	require_once $root . '/lib/snmp.php';
})();

test('issue 7342 rejects an snmpEngineTime value that is the Unix clock', function () {
	$now = 1784363931;

	expect(cacti_snmp_select_uptime(3015, $now, $now))->toBe(3015);
});

test('normal engine time still covers a wrapped sysUpTime value', function () {
	$now = 1784363931;

	expect(cacti_snmp_select_uptime(250000, 50000000, $now))->toBe(5000000000)
		->and(cacti_snmp_select_uptime(4000000, 600, $now))->toBe(4000000)
		->and(cacti_snmp_select_uptime(false, 600, $now))->toBe(60000)
		->and(cacti_snmp_select_uptime('U', 'U', $now))->toBeFalse();
});

test('prefer_engine_time matches spine\'s own unconditional engine-OID preference', function () {
	$now = 1784363931;

	// spine (poller.c) always prefers a numeric engine time over sysUpTime with no
	// magnitude comparison of its own; a smaller-but-legitimate engine time (e.g. the
	// SNMP agent restarted more recently than the OS) must not fall back to sysUpTime
	// here, or the recache baseline permanently disagrees with spine's live re-check
	expect(cacti_snmp_select_uptime(999999999, 600, $now, true))->toBe(60000)
		->and(cacti_snmp_select_uptime(4000000, 600, $now, true))->toBe(60000)
		->and(cacti_snmp_select_uptime(false, 600, $now, true))->toBe(60000)
		// the wall-clock rejection still applies regardless of $prefer_engine_time
		->and(cacti_snmp_select_uptime(3015, $now, $now, true))->toBe(3015)
		->and(cacti_snmp_select_uptime('U', 'U', $now, true))->toBeFalse();
});

test('the recache baseline call opts into spine-compatible engine preference', function () use ($root) {
	$source = file_get_contents($root . '/lib/poller.php');

	expect($source)->toContain('cacti_snmp_select_uptime($system_uptime, $engine_time, null, true)');
});

test('cmd.php uptime call sites opt into spine-compatible engine preference', function () use ($root) {
	$source = file_get_contents($root . '/cmd.php');

	expect(substr_count($source, 'cacti_snmp_select_uptime($system_uptime, $engine_time, null, true)'))->toBe(2);
});

test('every system uptime call path uses the shared selection rule', function () use ($root) {
	$call_counts = array(
		'cmd.php'                => 2,
		'lib/poller.php'         => 1,
		'lib/api_device.php'     => 1,
		'lib/api_automation.php' => 1
	);

	foreach ($call_counts as $path => $count) {
		expect(substr_count(file_get_contents($root . '/' . $path), 'cacti_snmp_select_uptime('))->toBeGreaterThanOrEqual($count);
	}
});

test('device display reuses the uptime reads and shows an unknown placeholder', function () use ($root) {
	$source = file_get_contents($root . '/lib/api_device.php');
	$start  = strpos($source, 'function api_device_ping_device(');
	$body   = substr($source, $start, strpos($source, 'function api_duplicate_device_template', $start) - $start);

	expect(substr_count($body, '.1.3.6.1.6.3.10.2.1.3.0'))->toBe(1)
		->and($body)->toContain('if ($snmp_uptime === false)')
		->and($body)->toContain('"</strong> $snmp_uptime<br>"')
		->and($body)->toContain("print '</span>';");
});
