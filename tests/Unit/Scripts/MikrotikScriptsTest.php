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
 * Behaviour of the MikroTik script server data collection scripts, which are
 * kept identical to the copies in the mikrotik plugin.  They read the plugin
 * tables, so each test runs against an in-memory copy of them.
 */

global $called_by_script_server;
$called_by_script_server = true;

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/Helpers/MikrotikScriptDatabase.php';

foreach (['cpu', 'disk', 'health', 'interfaces', 'mem', 'procs', 'qcount', 'qtrees', 'queues', 'qusers', 'snmpget', 'trees', 'uptime', 'users', 'wapcount', 'wireless_reg', 'wrcount'] as $script) {
	require_once dirname(__DIR__, 3) . '/scripts/ss_mikrotik_' . $script . '.php';
}

function mikrotik_script_output(callable $call): string {
	ob_start();
	$call();

	return (string) ob_get_clean();
}

test('device scripts return the collected values', function () {
	mikrotik_script_database(function () {
		expect(ss_mikrotik_cpu(1))->toBe('cpu:20 max:30')
			->and(ss_mikrotik_mem(1))->toBe('used:250 size:1000')
			->and(ss_mikrotik_disk(1))->toBe('used:1000 size:2000')
			->and(ss_mikrotik_procs(1))->toBe('120')
			->and(ss_mikrotik_users(1))->toBe('4')
			->and(ss_mikrotik_uptime(1))->toBe('1')
			->and(ss_mikrotik_trees(1))->toBe('2')
			->and(ss_mikrotik_qcount(1))->toBe('1')
			->and(ss_mikrotik_wapcount(1))->toBe('1')
			->and(ss_mikrotik_wrcount(1))->toBe('2');
	});
});

test('device scripts return U when the device has no data', function () {
	mikrotik_script_database(function () {
		expect(ss_mikrotik_cpu(5))->toBe('cpu:U max:U')
			->and(ss_mikrotik_mem(2))->toBe('used:U size:U')
			->and(ss_mikrotik_disk(5))->toBe('used:U size:U')
			->and(ss_mikrotik_procs(5))->toBe('U')
			->and(ss_mikrotik_users(5))->toBe('U')
			->and(ss_mikrotik_uptime(5))->toBe('U');
	});
});

test('health prefers the RouterOS 7 sensor and falls back to the legacy column', function () {
	mikrotik_script_database(function () {
		expect(ss_mikrotik_health(1, 'HlVoltage'))->toBe('23.9')
			->and(ss_mikrotik_health(1, 'HlCpuTemperature'))->toBe('48')
			->and(ss_mikrotik_health(1, 'HlTemperature'))->toBe('40')
			->and(ss_mikrotik_health(1, 'processorTemperature'))->toBe('48')
			->and(ss_mikrotik_health(1, 'HlProcessorTemperature'))->toBe('U')
			->and(ss_mikrotik_health(1, 'HlTwelveVoltage'))->toBe('U')
			->and(ss_mikrotik_health(5, 'HlVoltage'))->toBe('U')
			->and(ss_mikrotik_health(1, 'host_id'))->toBe('U');
	});
});

test('interfaces are indexed by interface index and prefer the present row', function () {
	mikrotik_script_database(function () {
		expect(mikrotik_script_output(fn () => ss_mikrotik_interfaces(1, 'index')))->toBe('7' . PHP_EOL . '3' . PHP_EOL)
			->and(ss_mikrotik_interfaces(1, 'num_indexes'))->toBe('2')
			->and(mikrotik_script_output(fn () => ss_mikrotik_interfaces(1, 'query', 'intName')))->toBe('7!ether2' . PHP_EOL . '3!wan uplink' . PHP_EOL)
			->and(mikrotik_script_output(fn () => ss_mikrotik_interfaces(1, 'query', 'ifAlias')))->toBe('')
			->and(ss_mikrotik_interfaces(1, 'get', 'RxBytes', '3'))->toBe('1500')
			->and(ss_mikrotik_interfaces(1, 'get', 'TxBytes', 'wan_uplink'))->toBe('900')
			->and(ss_mikrotik_interfaces(1, 'get', 'RxBytes', '42'))->toBe('U')
			->and(ss_mikrotik_interfaces(1, 'get', 'x`y', '3'))->toBe('U')
			->and(ss_mikrotik_interfaces(1, 'bogus'))->toBe('');
	});
});

test('simple queues are indexed without spaces', function () {
	mikrotik_script_database(function () {
		expect(mikrotik_script_output(fn () => ss_mikrotik_queues(1, 'index')))->toBe('guest_wifi' . PHP_EOL)
			->and(ss_mikrotik_queues(1, 'num_indexes'))->toBe('1')
			->and(mikrotik_script_output(fn () => ss_mikrotik_queues(1, 'query', 'qsInterface')))->toBe('guest_wifi!bridge' . PHP_EOL)
			->and(mikrotik_script_output(fn () => ss_mikrotik_queues(1, 'query', 'qsBogus')))->toBe('')
			->and(ss_mikrotik_queues(1, 'get', 'qsOutOctets', 'guest_wifi'))->toBe('200')
			->and(ss_mikrotik_queues(1, 'get', 'qsInOctets', 'gone'))->toBe('U')
			->and(ss_mikrotik_queues(1, 'get', 'bogus', 'guest_wifi'))->toBe('U');
	});
});

test('queue trees are indexed without spaces and resolve every stored name form', function () {
	mikrotik_script_database(function () {
		expect(mikrotik_script_output(fn () => ss_mikrotik_qtrees(1, 'index')))->toBe('<down>' . PHP_EOL . 'customer_tree' . PHP_EOL)
			->and(ss_mikrotik_qtrees(1, 'num_indexes'))->toBe('2')
			->and(mikrotik_script_output(fn () => ss_mikrotik_qtrees(1, 'query', 'qtFlow')))->toBe('<down>!down' . PHP_EOL . 'customer_tree!up' . PHP_EOL)
			->and(mikrotik_script_output(fn () => ss_mikrotik_qtrees(1, 'query', 'qtBogus')))->toBe('')
			->and(ss_mikrotik_qtrees(1, 'get', 'qtBytes', 'customer_tree'))->toBe('4096')
			->and(ss_mikrotik_qtrees(1, 'get', 'qtPackets', 'down'))->toBe('4')
			->and(ss_mikrotik_qtrees(1, 'get', 'qtBytes', 'gone'))->toBe('U')
			->and(ss_mikrotik_qtrees(1, 'get', 'bogus', 'down'))->toBe('U');
	});
});

test('queue users are indexed by name', function () {
	mikrotik_script_database(function () {
		expect(mikrotik_script_output(fn () => ss_mikrotik_qusers(1, 'index')))->toBe('alice' . PHP_EOL)
			->and(ss_mikrotik_qusers(1, 'num_indexes'))->toBe('1')
			->and(mikrotik_script_output(fn () => ss_mikrotik_qusers(1, 'query', 'ip')))->toBe('alice!10.0.0.9' . PHP_EOL)
			->and(mikrotik_script_output(fn () => ss_mikrotik_qusers(1, 'query', 'password')))->toBe('')
			->and(ss_mikrotik_qusers(1, 'get', 'bytesOut', 'alice'))->toBe('600')
			->and(ss_mikrotik_qusers(1, 'get', 'curBytesIn', 'bob'))->toBe('U')
			->and(ss_mikrotik_qusers(1, 'get', 'bogus', 'alice'))->toBe('U');
	});
});

test('wireless registrations map the data query fields and graph uptime in seconds', function () {
	mikrotik_script_database(function () {
		expect(mikrotik_script_output(fn () => ss_mikrotik_wireless_reg(1, 'index')))->toBe('11:22:33:44:55:66' . PHP_EOL . 'AA:BB:CC:DD:EE:FF' . PHP_EOL)
			->and(ss_mikrotik_wireless_reg(1, 'num_indexes'))->toBe('2')
			->and(mikrotik_script_output(fn () => ss_mikrotik_wireless_reg(1, 'query', 'macAddress')))->toContain('AA:BB:CC:DD:EE:FF!AA:BB:CC:DD:EE:FF')
			->and(ss_mikrotik_wireless_reg(1, 'get', 'TxBytes', 'AA:BB:CC:DD:EE:FF'))->toBe('5000')
			->and(ss_mikrotik_wireless_reg(1, 'get', 'RxStrengthCh1', 'AA:BB:CC:DD:EE:FF'))->toBe('-61')
			->and(ss_mikrotik_wireless_reg(1, 'get', 'Uptime', 'AA:BB:CC:DD:EE:FF'))->toBe('93784')
			->and(ss_mikrotik_wireless_reg(1, 'get', 'Uptime', '11:22:33:44:55:66'))->toBe('120')
			->and(ss_mikrotik_wireless_reg(1, 'get', 'apTxRate', 'AA:BB:CC:DD:EE:FF'))->toBe('U')
			->and(ss_mikrotik_wireless_reg(1, 'get', 'TxBytes', 'gone'))->toBe('U')
			->and(ss_mikrotik_wireless_reg(2, 'get', 'RxStrengthCh1', 'DE:AD:BE:EF:00:01'))->toBe('U')
			->and(ss_mikrotik_wireless_reg_uptime('02:03:04.50'))->toBe('7384')
			->and(ss_mikrotik_wireless_reg_uptime(false))->toBe('U');
	});
});

test('snmpget returns U for a bad device or OID', function () {
	mikrotik_script_database(function () {
		expect(ss_mikrotik_snmpget(0, '.1.3.6.1.2.1.1.3.0'))->toBe('U')
			->and(ss_mikrotik_snmpget(1, '.1.3; ls'))->toBe('U')
			->and(ss_mikrotik_snmpget(1, '.1.3.6.1.2.1.1.3.0'))->toBe('U');
	});
});
