<?php

declare(strict_types = 1);

require_once dirname(__DIR__, 4) . '/lib/snmp.php';

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

test('every system uptime call path uses the shared selection rule', function (): void {
	$root = dirname(__DIR__, 4);

	expect(file_get_contents($root . '/cmd.php'))->toContain('cacti_snmp_select_uptime($system_uptime, $engine_time)')
		->and(file_get_contents($root . '/lib/poller.php'))->toContain('cacti_snmp_select_uptime($system_uptime, $engine_time)')
		->and(file_get_contents($root . '/lib/api_device.php'))->toContain('cacti_snmp_select_uptime($snmp_system_uptime, $snmp_engine_time)');
});
