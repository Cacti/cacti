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
$config = [
	'php_snmp_support' => false,
	'include_path'     => $root . '/include',
];

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
			->and(substr_count($source, 'cacti_snmp_select_uptime('))->toBe($count);
	}
});
