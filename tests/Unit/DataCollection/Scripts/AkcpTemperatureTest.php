<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/Helpers/PhpSource.php';

use Cacti\Tests\Helpers\PhpSource;

const AKCP_TEMPERATURE_SCRIPT = __DIR__ . '/../../../../scripts/query_akcp_temperature.php';

// The script reads its arguments and talks to SNMP at the top level, so only the
// conversion function is loaded, unchanged, from the checked-in source.
cacti_fixture_source_function(AKCP_TEMPERATURE_SCRIPT, 'akcp_fahrenheit_tenths', 'CactiAkcpFixture');

test('readings below 100 F convert exactly as before', function (int $celsius, int $fahrenheit) {
	expect(\CactiAkcpFixture\akcp_fahrenheit_tenths($celsius))->toBe($fahrenheit);
})->with([
	'0.0 C'  => [0, 320],
	'25.0 C' => [250, 770],
	'30.0 C' => [300, 860],
	'37.5 C' => [375, 995],
]);

test('readings of 100 F or more keep all four digits', function (int $celsius, int $fahrenheit) {
	expect(\CactiAkcpFixture\akcp_fahrenheit_tenths($celsius))->toBe($fahrenheit);
})->with([
	'38.0 C'  => [380, 1004],
	'40.0 C'  => [400, 1040],
	'50.0 C'  => [500, 1220],
	'100.0 C' => [1000, 2120],
]);

test('readings below freezing convert', function (int $celsius, int $fahrenheit) {
	expect(\CactiAkcpFixture\akcp_fahrenheit_tenths($celsius))->toBe($fahrenheit);
})->with([
	'-10.0 C' => [-100, 140],
	'-30.0 C' => [-300, -220],
]);

test('the result tracks the true Fahrenheit value to one tenth, from 0 C to 200 C', function () {
	for ($celsius = 0; $celsius <= 2000; $celsius++) {
		$exact  = ((($celsius / 10) * (9 / 5)) + 32) * 10;
		$result = \CactiAkcpFixture\akcp_fahrenheit_tenths($celsius);

		// The value is truncated to a whole tenth, so it is never above the exact value and
		// never a full tenth below it. A cut-off digit would miss by hundreds.
		expect($result)->toBeLessThanOrEqual($exact + 1e-9)
			->and($exact - $result)->toBeLessThan(1 + 1e-9);
	}
});

test('a numeric string from SNMP converts like a number', function () {
	expect(\CactiAkcpFixture\akcp_fahrenheit_tenths('380'))->toBe(1004);
});

test('a reading that is not a number is unknown, not zero', function (mixed $reading) {
	expect(\CactiAkcpFixture\akcp_fahrenheit_tenths($reading))->toBe('U');
})->with([
	'empty string'  => [''],
	'text'          => ['abc'],
	'SNMP error'    => ['No Such Object'],
	'unknown'       => ['U'],
	'null'          => [null],
	'array'         => [[380]],
]);

test('the query and get paths both convert through the helper without cutting digits', function () {
	$source = file_get_contents(AKCP_TEMPERATURE_SCRIPT);

	expect(PhpSource::count($source, 'akcp_fahrenheit_tenths($arr[$i])'))->toBe(1)
		->and(PhpSource::count($source, 'akcp_fahrenheit_tenths($value)'))->toBe(1)
		->and(PhpSource::count($source, 'substr((string)'))->toBe(0);
});
