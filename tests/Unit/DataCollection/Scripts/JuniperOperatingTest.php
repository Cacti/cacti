<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit tests for the JUNIPER-MIB jnxOperatingTable collector (issue #7355).
 * The SNMP walk is injected through the $walker seam so every branch runs
 * without a device. jnxOperatingTable is multi-indexed, so the fixture uses
 * composite index suffixes.
 */

$called_by_script_server = true;
if (!defined('SNMP_POLLER')) {
	define('SNMP_POLLER', 'SNMP');
}
if (!function_exists('cacti_count')) {
	function cacti_count(mixed $a): int { return is_array($a) ? count($a) : 0; }
}
require_once dirname(__DIR__, 4) . '/scripts/ss_juniper_operating.php';

function _jnx_walker(array $fixture): callable {
	return static function (string $oid) use ($fixture): array {
		return $fixture[$oid] ?? [];
	};
}

$OIDS = ss_juniper_operating_oids();

// Two operating subjects: Routing Engine 0 (9.1.0.0) and FPC 0 (7.1.0.0).
$FIXTURE = [
	$OIDS['descr'] => ['9.1.0.0' => 'Routing Engine 0', '7.1.0.0' => 'FPC: 0'],
	$OIDS['cpu']   => ['9.1.0.0' => '12', '7.1.0.0' => '5'],
	$OIDS['temp']  => ['9.1.0.0' => '41', '7.1.0.0' => '38'],
	$OIDS['state'] => ['9.1.0.0' => '2',  '7.1.0.0' => '2'],
];

test('index prints one line per operating subject', function () use ($FIXTURE) {
	ob_start();
	ss_juniper_operating('h', 1, '', 'index', '', '', _jnx_walker($FIXTURE));
	expect(trim(ob_get_clean()))->toBe("9.1.0.0\n7.1.0.0");
});

test('num_indexes counts the subjects', function () use ($FIXTURE) {
	expect(ss_juniper_operating('h', 1, '', 'num_indexes', '', '', _jnx_walker($FIXTURE)))->toBe(2);
});

test('query cpu returns composite-index!value pairs', function () use ($FIXTURE) {
	ob_start();
	ss_juniper_operating('h', 1, '', 'query', 'cpu', '', _jnx_walker($FIXTURE));
	expect(trim(ob_get_clean()))->toBe("9.1.0.0!12\n7.1.0.0!5");
});

test('query descr returns the subject label', function () use ($FIXTURE) {
	ob_start();
	ss_juniper_operating('h', 1, '', 'query', 'descr', '', _jnx_walker($FIXTURE));
	expect(trim(ob_get_clean()))->toBe("9.1.0.0!Routing Engine 0\n7.1.0.0!FPC: 0");
});

test('query index maps each subject index to itself', function () use ($FIXTURE) {
	ob_start();
	ss_juniper_operating('h', 1, '', 'query', 'index', '', _jnx_walker($FIXTURE));
	expect(trim(ob_get_clean()))->toBe("9.1.0.0!9.1.0.0\n7.1.0.0!7.1.0.0");
});

test('query of an unknown field yields nothing', function () use ($FIXTURE) {
	ob_start();
	ss_juniper_operating('h', 1, '', 'query', 'bogus', '', _jnx_walker($FIXTURE));
	expect(ob_get_clean())->toBe('');
});

test('get returns one field for one index', function () use ($FIXTURE) {
	expect(ss_juniper_operating('h', 1, '', 'get', 'temp', '9.1.0.0', _jnx_walker($FIXTURE)))->toBe('41');
});

test('get for a missing index returns U', function () use ($FIXTURE) {
	expect(ss_juniper_operating('h', 1, '', 'get', 'cpu', '3.1.0.0', _jnx_walker($FIXTURE)))->toBe('U');
});

test('an unknown command returns empty', function () use ($FIXTURE) {
	expect(ss_juniper_operating('h', 1, '', 'restart', '', '', _jnx_walker($FIXTURE)))->toBe('');
});

test('with no injected walker the default SNMP walker is constructed', function () {
	expect(ss_juniper_operating('127.0.0.1', 1, '2:161:500:0:10:public', 'noop'))->toBe('');
});

test('the SNMP walker factory returns a callable', function () {
	expect(ss_juniper_operating_snmp_walker('127.0.0.1', ['2', '161']))->toBeCallable();
});

test('flatten keys rows by the full suffix after the base and skips foreign rows', function () use ($OIDS) {
	$rows = [
		['oid' => $OIDS['cpu'] . '.9.1.0.0', 'value' => ' 12 '],
		['oid' => '.1.3.6.1.2.1.1.3.0',      'value' => 'x'],   // outside the base, dropped
		['oid' => $OIDS['cpu'],              'value' => 'y'],   // exactly the base, no suffix, dropped
	];
	expect(ss_juniper_operating_flatten($OIDS['cpu'], $rows))->toBe(['9.1.0.0' => '12']);
});
