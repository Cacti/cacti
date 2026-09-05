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
$config = array(
	'php_snmp_support' => false,
	'include_path'     => $root . '/include'
);

require_once $root . '/lib/snmp.php';

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

test('every system uptime call path uses the shared selection rule', function () use ($root) {
	$call_counts = array(
		'cmd.php'                => 2,
		'lib/poller.php'         => 1,
		'lib/api_device.php'     => 1,
		'lib/api_automation.php' => 1
	);

	foreach ($call_counts as $path => $count) {
		expect(substr_count(file_get_contents($root . '/' . $path), 'cacti_snmp_select_uptime('))->toBe($count);
	}
});

test('device display reuses the uptime reads and shows an unknown placeholder', function () use ($root) {
	$source = file_get_contents($root . '/lib/api_device.php');
	$start  = strpos($source, 'function api_device_ping_device(');
	$body   = substr($source, $start, strpos($source, 'function api_duplicate_device_template', $start) - $start);

	expect(substr_count($body, '.1.3.6.1.6.3.10.2.1.3.0'))->toBe(1)
		->and($body)->toContain("\$snmp_uptime = 'U';");
});
