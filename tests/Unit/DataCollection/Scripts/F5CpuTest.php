<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit tests for the F5 BIG-IP sysMultiHostCpuTable collector (issue #7355).
 * The SNMP walk is injected through the $walker seam so every branch runs
 * without a device. The table is multi-indexed, so the fixture uses composite
 * index suffixes (host id . cpu index).
 */

$called_by_script_server = true;
if (!defined('SNMP_POLLER')) {
	define('SNMP_POLLER', 'SNMP');
}
if (!function_exists('cacti_count')) {
	function cacti_count(mixed $a): int { return is_array($a) ? count($a) : 0; }
}
require_once dirname(__DIR__, 4) . '/scripts/ss_f5_cpu.php';

function _f5_walker(array $fixture): callable {
	return static function (string $oid) use ($fixture): array {
		return $fixture[$oid] ?? [];
	};
}

$OIDS = ss_f5_cpu_oids();

// Two CPUs on host 0: index 0 and 1.
$FIXTURE = [
	$OIDS['name']  => ['0.0' => 'cpu0', '0.1' => 'cpu1'],
	$OIDS['avg1m'] => ['0.0' => '18', '0.1' => '22'],
	$OIDS['avg5s'] => ['0.0' => '25', '0.1' => '30'],
];

test('index prints one line per cpu', function () use ($FIXTURE) {
	ob_start();
	ss_f5_cpu('h', 1, '', 'index', '', '', _f5_walker($FIXTURE));
	expect(trim(ob_get_clean()))->toBe("0.0\n0.1");
});

test('num_indexes counts the cpus', function () use ($FIXTURE) {
	expect(ss_f5_cpu('h', 1, '', 'num_indexes', '', '', _f5_walker($FIXTURE)))->toBe(2);
});

test('query avg1m returns composite-index!value pairs', function () use ($FIXTURE) {
	ob_start();
	ss_f5_cpu('h', 1, '', 'query', 'avg1m', '', _f5_walker($FIXTURE));
	expect(trim(ob_get_clean()))->toBe("0.0!18\n0.1!22");
});

test('query name returns the cpu label', function () use ($FIXTURE) {
	ob_start();
	ss_f5_cpu('h', 1, '', 'query', 'name', '', _f5_walker($FIXTURE));
	expect(trim(ob_get_clean()))->toBe("0.0!cpu0\n0.1!cpu1");
});

test('query index maps each cpu index to itself', function () use ($FIXTURE) {
	ob_start();
	ss_f5_cpu('h', 1, '', 'query', 'index', '', _f5_walker($FIXTURE));
	expect(trim(ob_get_clean()))->toBe("0.0!0.0\n0.1!0.1");
});

test('query of an unknown field yields nothing', function () use ($FIXTURE) {
	ob_start();
	ss_f5_cpu('h', 1, '', 'query', 'bogus', '', _f5_walker($FIXTURE));
	expect(ob_get_clean())->toBe('');
});

test('get returns one field for one index', function () use ($FIXTURE) {
	expect(ss_f5_cpu('h', 1, '', 'get', 'avg1m', '0.1', _f5_walker($FIXTURE)))->toBe('22');
});

test('get for a missing index returns U', function () use ($FIXTURE) {
	expect(ss_f5_cpu('h', 1, '', 'get', 'avg1m', '9.9', _f5_walker($FIXTURE)))->toBe('U');
});

test('an unknown command returns empty', function () use ($FIXTURE) {
	expect(ss_f5_cpu('h', 1, '', 'reload', '', '', _f5_walker($FIXTURE)))->toBe('');
});

test('with no injected walker the default SNMP walker is constructed', function () {
	expect(ss_f5_cpu('127.0.0.1', 1, '2:161:500:0:10:public', 'noop'))->toBe('');
});

test('the SNMP walker factory returns a callable', function () {
	expect(ss_f5_cpu_snmp_walker('127.0.0.1', ['2', '161']))->toBeCallable();
});

test('flatten keys rows by the full suffix and skips foreign rows', function () use ($OIDS) {
	$rows = [
		['oid' => $OIDS['avg1m'] . '.0.0', 'value' => ' 18 '],
		['oid' => '.1.3.6.1.2.1.1.3.0',    'value' => 'x'],
		['oid' => $OIDS['avg1m'],          'value' => 'y'],
	];
	expect(ss_f5_cpu_flatten($OIDS['avg1m'], $rows))->toBe(['0.0' => '18']);
});
