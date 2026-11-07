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
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

global $called_by_script_server;
$called_by_script_server = true;
require_once __DIR__ . '/../../../scripts/ss_cisco_health.php';

function ciscoHealthFixture(array $values = [], array $walks = []) : array {
	return [
		static fn ($oid) => $values[$oid] ?? 'No Such Instance currently exists at this OID',
		static fn ($oid) => $walks[$oid] ?? [],
	];
}

test('unsupported cisco health tables produce no selectable rows', function () {
	[$get, $walk] = ciscoHealthFixture();

	foreach (['memory', 'qfp_memory', 'temperature'] as $kind) {
		$this->assertSame('', cisco_health_query($kind, 'index', '', '', $get, $walk));
		$this->assertSame('0', cisco_health_query($kind, 'num_indexes', '', '', $get, $walk));
	}
});

test('enhanced memory wins over overlapping legacy pools', function () {
	[$get, $walk] = ciscoHealthFixture([], [
		'.1.3.6.1.4.1.9.9.221.1.1.1.1.3' => ['7.1' => 'Processor'],
		'.1.3.6.1.4.1.9.9.48.1.1.1.2'    => ['1' => 'Processor'],
	]);
	$this->assertSame("7.1\n", cisco_health_query('memory', 'index', '', '', $get, $walk));
});

test('legacy memory is discoverable and zero free memory remains zero', function () {
	[$get, $walk] = ciscoHealthFixture([
		'.1.3.6.1.4.1.9.9.48.1.1.1.4.1' => '1',
		'.1.3.6.1.4.1.9.9.48.1.1.1.6.1' => '0',
	], ['.1.3.6.1.4.1.9.9.48.1.1.1.2' => ['1' => 'Processor']]);
	$this->assertSame("1\n", cisco_health_query('memory', 'index', '', '', $get, $walk));
	$this->assertSame('0', cisco_health_query('memory', 'get', 'free', '1', $get, $walk));
});

test('memory uses 64 bit gauges and rejects invalid pools', function () {
	[$get, $walk] = ciscoHealthFixture([
		'.1.3.6.1.4.1.9.9.221.1.1.1.1.6.7.1'  => '1',
		'.1.3.6.1.4.1.9.9.221.1.1.1.1.18.7.1' => '8589934592',
	]);
	$this->assertSame('8589934592', cisco_health_query('memory', 'get', 'used', '7.1', $get, $walk));
	$this->assertSame('U', cisco_health_query('memory', 'get', 'used', '7.2', $get, $walk));
});

test('temperature discovery merges sensor tables without duplicates', function () {
	[$get, $walk] = ciscoHealthFixture([], [
		'.1.3.6.1.2.1.99.1.1.1.1'       => ['10' => '8', '12' => '6'],
		'.1.3.6.1.4.1.9.9.91.1.1.1.1.1' => ['10' => '8', '11' => '8', '12' => '8'],
	]);
	$this->assertSame("10\n11\n", cisco_health_query('temperature', 'index', '', '', $get, $walk));
});

test('cisco fallback normalises temperature and rejects unavailable samples', function () {
	$base         = '.1.3.6.1.4.1.9.9.91.1.1.1.1.';
	[$get, $walk] = ciscoHealthFixture([
		$base . '1.11' => 8, $base . '2.11' => 9, $base . '3.11' => 1,
		$base . '4.11' => -125, $base . '5.11' => 1,
	]);
	$this->assertSame('-12.5', cisco_health_query('temperature', 'get', 'temperature', '11', $get, $walk));
	$this->assertSame('U', cisco_health_query('temperature', 'get', 'temperature', '12', $get, $walk));
	$this->assertSame('U', cisco_health_temperature(420, 9, 1, 2));
	$this->assertSame('U', cisco_health_temperature(1000000000, 9, 0, 1));
	$this->assertSame('42', cisco_health_temperature(42, 9, -2, 1));
});

test('cisco exa and peta scales differ from standard sensor scales', function () {
	$this->assertSame(1.0e18, (float)cisco_health_temperature(1, 14, 0, 1, true));
	$this->assertSame(1.0e15, (float)cisco_health_temperature(1, 15, 0, 1, true));
	$this->assertSame(1.0e15, (float)cisco_health_temperature(1, 14, 0, 1));
});

test('qfp units are explicit and transport errors do not become kilobytes', function () {
	$base = '.1.3.6.1.4.1.9.9.715.1.1.7.1.';

	foreach ([4 => '1024', 8 => 'U', 'timeout' => 'U', 'No Such Object' => '1048576'] as $unit => $expected) {
		[$get, $walk] = ciscoHealthFixture([$base . '16.1.1' => $unit, $base . '11.1.1' => 1024]);
		$this->assertSame($expected, cisco_health_query('qfp_memory', 'get', 'used', '1.1', $get, $walk));
	}
});

test('raw QFP reader preserves missing columns without treating empty replies as legacy units', function () {
	$base = '.1.3.6.1.4.1.9.9.715.1.1.7.1.';

	foreach (['No Such Object available on this agent at this OID' => '1048576', 'No Such Instance currently exists at this OID' => '1048576', '' => 'U', 'U' => 'U', 'INTEGER: 4' => '1024'] as $raw => $expected) {
		[$get, $walk] = ciscoHealthFixture([$base . '16.1.1' => cisco_health_unit_response($raw), $base . '11.1.1' => 1024]);
		expect(cisco_health_query('qfp_memory', 'get', 'used', '1.1', $get, $walk))->toBe($expected);
	}
});

test('index discovery never fetches entity names', function () {
	$walks = [
		'.1.3.6.1.4.1.9.9.221.1.1.1.1.3' => ['7.1' => 'Processor'],
		'.1.3.6.1.4.1.9.9.715.1.1.7.1.9' => ['7.1' => '1024'],
		'.1.3.6.1.2.1.99.1.1.1.1'        => ['7' => '8'],
	];
	$get  = static function ($oid) { throw new RuntimeException('Unnecessary GET: ' . $oid); };
	$walk = static fn ($oid) => $walks[$oid] ?? [];

	foreach (['memory', 'qfp_memory', 'temperature'] as $kind) {
		expect(cisco_health_query($kind, 'num_indexes', '', '', $get, $walk))->toBe('1');
		expect(cisco_health_query($kind, 'index', '', '', $get, $walk))->not->toBe('');
		expect(cisco_health_query($kind, 'query', 'index', '', $get, $walk))->not->toBe('');
	}
});

test('memory validity accepts Net-SNMP enum values without accepting error text', function () {
	foreach (['true(1)' => '123', 'false(2)' => 'U', 'timeout' => 'U'] as $valid => $expected) {
		[$get, $walk] = ciscoHealthFixture([
			'.1.3.6.1.4.1.9.9.48.1.1.1.4.1' => $valid,
			'.1.3.6.1.4.1.9.9.48.1.1.1.5.1' => '123',
		]);
		expect(cisco_health_query('memory', 'get', 'used', '1', $get, $walk))->toBe($expected);
	}
});

test('enhanced memory falls back to legacy gauges only for missing HC columns', function () {
	$base = '.1.3.6.1.4.1.9.9.221.1.1.1.1.';

	foreach ([['No Such Object', 'No Such Object', '123'], ['No Such Object', '2', '8589934715'], ['U', '2', 'U'], ['No Such Object', 'U', 'U']] as [$hc, $high, $expected]) {
		[$get, $walk] = ciscoHealthFixture([$base . '6.7.1' => 1, $base . '18.7.1' => $hc,
			$base . '7.7.1'                                    => 123, $base . '17.7.1' => $high]);
		expect(cisco_health_query('memory', 'get', 'used', '7.1', $get, $walk))->toBe($expected);
	}
});

test('both sensor tables discover numeric and named Celsius without including other types', function () {
	foreach (['.1.3.6.1.2.1.99.1.1.1.', '.1.3.6.1.4.1.9.9.91.1.1.1.1.'] as $base) {
		[$get, $walk] = ciscoHealthFixture([], [$base . '1' => ['1'=>8, '2'=>'celsius(8)', '3'=>'celsius', '4'=>'voltsAC(3)', '5'=>'U']]);
		expect(cisco_health_query('temperature', 'index', '', '', $get, $walk))->toBe("1\n2\n3\n");
		expect(cisco_health_query('temperature', 'num_indexes', '', '', $get, $walk))->toBe('3');
	}
});

test('original QFP gauges are discoverable and poll with overflow without masking failures', function () {
	$base = '.1.3.6.1.4.1.9.9.715.1.1.7.1.';

	foreach (['No Such Object'=>true, 'U'=>false, ''=>false] as $hc => $supported) {
		[$get, $walk] = ciscoHealthFixture([$base . '9.7.1'=> $hc, $base . '2.7.1'=>1024], [$base . '2'=>['7.1'=>1024]]);
		expect(cisco_health_query('qfp_memory', 'index', '', '', $get, $walk))->toBe($supported ? "7.1\n" : '');
	}

	foreach (['used'=>[11, 3, 10], 'free'=>[13, 4, 12]] as $field => [$hc, $low, $high]) {
		foreach (['No Such Object'=>'2048', '1'=>'4398046513152', 'U'=>'U'] as $overflow => $expected) {
			[$get, $walk] = ciscoHealthFixture([$base . $low . '.7.1'=> 2, $base . $high . '.7.1'=>$overflow]);
			expect(cisco_health_query('qfp_memory', 'get', $field, '7.1', $get, $walk))->toBe($expected);
		}
		[$get, $walk] = ciscoHealthFixture([$base . $hc . '.7.1'=> 'U', $base . $low . '.7.1'=>2]);
		expect(cisco_health_query('qfp_memory', 'get', $field, '7.1', $get, $walk))->toBe('U');
	}
});
