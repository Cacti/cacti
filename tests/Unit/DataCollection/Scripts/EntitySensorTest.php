<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit tests for the ENTITY-SENSOR-MIB collector (issue #7355).
 * Runs under the no-DB bootstrap. The collector's SNMP I/O is injected through
 * its $walker seam, so every branch is exercised without a live device.
 */

// The script guards its CLI/script-server dispatch behind $called_by_script_server;
// set it so requiring the file only defines the functions.
$called_by_script_server = true;
if (!defined('SNMP_POLLER')) {
	define('SNMP_POLLER', 'SNMP');
}
if (!function_exists('cacti_count')) {
	function cacti_count(mixed $a): int { return is_array($a) ? count($a) : 0; }
}
require_once dirname(__DIR__, 4) . '/scripts/ss_entity_sensor.php';

/** A fake walker backed by an OID-keyed fixture. */
function _es_walker(array $fixture): callable {
	return static function (string $oid) use ($fixture): array {
		return $fixture[$oid] ?? [];
	};
}

$OIDS = ss_entity_sensor_oids();

$FIXTURE = [
	$OIDS['type']      => ['1' => '8',  '2' => '10'],  // celsius, rpm
	$OIDS['value']     => ['1' => '235', '2' => '4200'],
	$OIDS['scale']     => ['1' => '9',  '2' => '9'],   // units
	$OIDS['precision'] => ['1' => '1',  '2' => '0'],
	$OIDS['status']    => ['1' => '1',  '2' => '1'],
	$OIDS['descr']     => ['1' => 'Inlet Temp', '2' => 'Fan 1'],
	$OIDS['name']      => ['1' => 'Temp/1', '2' => 'Fan/1'],
];

test('scale: units precision one divides by ten', function () {
	expect(ss_entity_sensor_scale(235, 9, 1))->toBe(23.5);
});

test('scale: milli magnitude applies a thousandths multiplier', function () {
	expect(ss_entity_sensor_scale(5000, 8, 0))->toBe(5.0); // 5000 * 1000^-1 = milli
});

test('scale: out-of-range scale is treated as units and precision zero is a no-op', function () {
	expect(ss_entity_sensor_scale(42, 99, 0))->toBe(42);
	expect(ss_entity_sensor_scale(42, 9, 0))->toBe(42);
});

test('index prints one line per sensor', function () use ($FIXTURE) {
	ob_start();
	ss_entity_sensor('h', 1, '', 'index', '', '', _es_walker($FIXTURE));
	expect(trim(ob_get_clean()))->toBe("1\n2");
});

test('num_indexes counts the sensors', function () use ($FIXTURE) {
	expect(ss_entity_sensor('h', 1, '', 'num_indexes', '', '', _es_walker($FIXTURE)))->toBe(2);
});

test('query value returns scaled index!value pairs', function () use ($FIXTURE) {
	ob_start();
	ss_entity_sensor('h', 1, '', 'query', 'value', '', _es_walker($FIXTURE));
	expect(trim(ob_get_clean()))->toBe("1!23.5\n2!4200");
});

test('query description returns the ENTITY-MIB label', function () use ($FIXTURE) {
	ob_start();
	ss_entity_sensor('h', 1, '', 'query', 'descr', '', _es_walker($FIXTURE));
	expect(trim(ob_get_clean()))->toBe("1!Inlet Temp\n2!Fan 1");
});

test('query index maps each sensor index to itself', function () use ($FIXTURE) {
	ob_start();
	ss_entity_sensor('h', 1, '', 'query', 'index', '', _es_walker($FIXTURE));
	expect(trim(ob_get_clean()))->toBe("1!1\n2!2");
});

test('query of an unknown field yields nothing', function () use ($FIXTURE) {
	ob_start();
	ss_entity_sensor('h', 1, '', 'query', 'bogus', '', _es_walker($FIXTURE));
	expect(ob_get_clean())->toBe('');
});

test('get returns one scaled value for one index', function () use ($FIXTURE) {
	expect(ss_entity_sensor('h', 1, '', 'get', 'value', '1', _es_walker($FIXTURE)))->toBe('23.5');
});

test('get for a missing index returns U', function () use ($FIXTURE) {
	expect(ss_entity_sensor('h', 1, '', 'get', 'value', '9', _es_walker($FIXTURE)))->toBe('U');
});

test('get with a non-numeric raw value scales from zero', function () use ($OIDS) {
	$fx = [$OIDS['value'] => ['1' => 'n/a'], $OIDS['scale'] => ['1' => '9'], $OIDS['precision'] => ['1' => '0']];
	expect(ss_entity_sensor('h', 1, '', 'get', 'value', '1', _es_walker($fx)))->toBe('0');
});

test('an unknown command returns empty', function () use ($FIXTURE) {
	expect(ss_entity_sensor('h', 1, '', 'reboot', '', '', _es_walker($FIXTURE)))->toBe('');
});

test('flatten keys rows by the trailing OID component and drops empties', function () {
	$rows = [
		['oid' => '.1.3.6.1.2.1.99.1.1.1.4.7', 'value' => '  100  '],
		['oid' => '', 'value' => 'x'],
	];
	expect(ss_entity_sensor_flatten('.1.3.6.1.2.1.99.1.1.1.4', $rows))->toBe(['7' => '100']);
});

test('with no injected walker the default SNMP walker is constructed', function () {
	// An unknown command builds the default walker but never calls it, so this
	// exercises the walker === null branch without live network I/O.
	expect(ss_entity_sensor('127.0.0.1', 1, '2:161:500:0:10:public', 'noop'))->toBe('');
});

test('the SNMP walker factory returns a callable', function () {
	expect(ss_entity_sensor_snmp_walker('127.0.0.1', ['2', '161']))->toBeCallable();
});
