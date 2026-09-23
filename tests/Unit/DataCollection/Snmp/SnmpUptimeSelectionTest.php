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

declare(strict_types = 1);

$root = dirname(__DIR__, 4);

require_once $root . '/lib/snmp.php';

test('issue 7342 rejects an snmpEngineTime value that is the Unix clock', function (): void {
	$now = 1784363931;

	expect(cacti_snmp_select_uptime(3015, $now, $now))->toBe(3015);
});

test('normal engine time still covers a wrapped sysUpTime value', function (): void {
	$now = 1784363931;

	expect(cacti_snmp_select_uptime(250000, 50000000, $now))->toBe(5000000000)
		->and(cacti_snmp_select_uptime(4000000, 600, $now))->toBe(4000000)
		->and(cacti_snmp_select_uptime(false, 600, $now))->toBe(60000)
		->and(cacti_snmp_select_uptime('U', 'U', $now))->toBeFalse();
});

test('prefer_engine_time matches spine\'s own unconditional engine-OID preference', function (): void {
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

test('every system uptime consumer delegates to the shared selector', function () use ($root): void {
	$callCounts = [
		'cmd.php'                => 2,
		'lib/poller.php'         => 1,
		'lib/api_device.php'     => 1,
		'lib/api_automation.php' => 1,
	];

	foreach ($callCounts as $path => $count) {
		$source = file_get_contents($root . '/' . $path);

		expect($source)->not->toBeFalse("$path must be readable")
			->and(substr_count($source, 'cacti_snmp_select_uptime('))->toBeGreaterThanOrEqual($count);
	}
});

test('the recache baseline and cmd.php reindex re-check opt into spine-compatible engine preference', function () use ($root): void {
	$pollerSource = file_get_contents($root . '/lib/poller.php');
	$cmdSource    = file_get_contents($root . '/cmd.php');

	expect($pollerSource)->toContain('cacti_snmp_select_uptime($system_uptime, $engine_time, null, true)')
		->and(substr_count($cmdSource, 'cacti_snmp_select_uptime($system_uptime, $engine_time, null, true)'))->toBe(2);
});
