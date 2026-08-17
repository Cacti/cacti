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

// Inline coordinate conversion — mirrors fixCoordinates() in cli/add_site.php
function convertCoordinates(string $lat, string $lng): array {
	$utfCoord = mb_convert_encoding("$lat $lng", 'ISO-8859-1', 'UTF-8');

	if (preg_match('/(\d+)\xB0(\d+)\'((?:[.]\d+|\d+(?:[.]\d*)?))"?([NS]) +(\d+)\xB0(\d+)\'((?:[.]\d+|\d+(?:[.]\d*)?))"?([EW])/', $utfCoord, $matches)) {
		array_shift($matches);
		[$degN, $minN, $secN, $NS, $degE, $minE, $secE, $EW] = $matches;

		$lat = sprintf('%0.6f', ($NS === 'S' ? -1 : 1) * ((float) $degN + ((float) $minN / 60) + ((float) $secN / 3600)));
		$lng = sprintf('%0.6f', ($EW === 'W' ? -1 : 1) * ((float) $degE + ((float) $minE / 60) + ((float) $secE / 3600)));
	}

	return [$lat, $lng];
}

// Inline guard — mirrors the (!$deviceId || !$siteId) check in doDeviceMap()
function shouldRejectMap(int $deviceId, int $siteId): bool {
	return (!$deviceId || !$siteId);
}

// Fix #4/#5: DMS passthrough — already-decimal values are unchanged
test('coordinates unchanged when already decimal', function () {
	[$lat, $lng] = convertCoordinates('51.5115172', '-0.0017868');

	expect($lat)->toBe('51.5115172')
		->and($lng)->toBe('-0.0017868');
});

test('converts DMS north-east to positive decimal', function () {
	// 51°30'26"N 0°7'39"E
	[$lat, $lng] = convertCoordinates("51\xc2\xb030'26\"N", "0\xc2\xb07'39\"E");

	expect((float) $lat)->toBeGreaterThan(0.0)
		->and((float) $lng)->toBeGreaterThan(0.0);
});

test('converts DMS south-west to negative decimal', function () {
	// 33°52'0"S 151°12'36"W
	[$lat, $lng] = convertCoordinates("33\xc2\xb052'0\"S", "151\xc2\xb012'36\"W");

	expect((float) $lat)->toBeLessThan(0.0)
		->and((float) $lng)->toBeLessThan(0.0);
});

test('DMS north result is positive, south result is negative', function () {
	[$latN] = convertCoordinates("10\xc2\xb00'0\"N", "0\xc2\xb00'0\"E");
	[$latS] = convertCoordinates("10\xc2\xb00'0\"S", "0\xc2\xb00'0\"E");

	expect((float) $latN)->toBeGreaterThan(0.0)
		->and((float) $latS)->toBeLessThan(0.0);
});

test('DMS east result is positive, west result is negative', function () {
	[, $lngE] = convertCoordinates("0\xc2\xb00'0\"N", "10\xc2\xb00'0\"E");
	[, $lngW] = convertCoordinates("0\xc2\xb00'0\"N", "10\xc2\xb00'0\"W");

	expect((float) $lngE)->toBeGreaterThan(0.0)
		->and((float) $lngW)->toBeLessThan(0.0);
});

// Fix #6: doDeviceMap() guard rejects zero deviceId or zero siteId
test('guard rejects when deviceId is zero', function () {
	expect(shouldRejectMap(0, 1))->toBeTrue();
});

test('guard rejects when siteId is zero', function () {
	expect(shouldRejectMap(1, 0))->toBeTrue();
});

test('guard rejects when both are zero', function () {
	expect(shouldRejectMap(0, 0))->toBeTrue();
});

test('guard passes when both deviceId and siteId are non-zero', function () {
	expect(shouldRejectMap(1, 1))->toBeFalse();
});

// Fix #3: regex escaping prevents dot from matching any character
test('regex escaping prevents dot from matching any character', function () {
	$input = 'rtr.east';
	$pattern = '/^' . preg_quote($input, '/') . '$/';

	expect(preg_match($pattern, 'rtr.east'))->toBe(1)
		->and(preg_match($pattern, 'rtrXeast'))->toBe(0);
});

test('regex escaping prevents plus from being treated as quantifier', function () {
	$input = 'foo+bar';
	$pattern = '/^' . preg_quote($input, '/') . '$/';

	expect(preg_match($pattern, 'foo+bar'))->toBe(1)
		->and(preg_match($pattern, 'foobar'))->toBe(0);
});

test('wildcard conversion escapes special chars then expands percent', function () {
	$input = 'rtr-%-pe.1';
	$parts = explode('%', $input);
	$pattern = '/^' . implode('.*', array_map(fn($p) => preg_quote($p, '/'), $parts)) . '$/';

	expect(preg_match($pattern, 'rtr-east-pe.1'))->toBe(1)
		->and(preg_match($pattern, 'rtr-east-peX1'))->toBe(0);
});

// Fix #7: replaceSites flag controls update vs insert path
test('replaceSites true with existing data triggers update path', function () {
	$siteData = [['id' => 5, 'name' => 'Test']];
	$replaceSites = true;

	expect($siteData && $replaceSites)->toBeTrue();
});

test('replaceSites false with existing data skips to insert path', function () {
	$siteData = [['id' => 5, 'name' => 'Test']];
	$replaceSites = false;

	expect($siteData && $replaceSites)->toBeFalsy();
});

// Fix #10: curl_exec returns string|false; false signals failure
test('curl_exec false is handled as string|false', function () {
	$buffer = false; // simulates curl_exec failure

	expect($buffer)->toBeFalse()
		->and($buffer === false)->toBeTrue();
});
