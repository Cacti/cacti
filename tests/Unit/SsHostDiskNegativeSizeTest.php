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
 * Tests for the negative-disk-size reconstruction in ss_host_disk()
 * (scripts/ss_host_disk.php).
 *
 * hrStorageSize/hrStorageUsed are unsigned 32-bit SNMP counters. When the
 * true value exceeds PHP_INT32_MAX, net-snmp/PHP hand it back as a negative
 * signed 32-bit integer. The old code reconstructed it as
 * `abs($snmp_data) + 2147483647`, which is wrong: the correct unsigned
 * value is `$snmp_data + 4294967296` (2^32). abs()+2^31-1 produces a
 * value roughly half of the real one. The fix also returns 'U' instead of
 * silently multiplying by an empty/non-numeric allocation unit.
 *
 * The fixed if/elseif/else chain is pulled directly out of the real
 * source file and wrapped in a throwaway function, following the
 * extract-and-eval pattern used in Issue7070PercentileContractTest.php
 * and DbFetchCellReturnGuardTest.php.
 */

$source = file_get_contents(__DIR__ . '/../../scripts/ss_host_disk.php');

if ($source === false) {
	throw new RuntimeException('Unable to read scripts/ss_host_disk.php');
}

preg_match('/if \(\$snmp_data != \'\'.*?\n\t\t\t\t\}\n(?=\t\t\t\} else \{)/s', $source, $matches);

test('the negative-size reconstruction chain is present in the source', function () use ($matches) {
	expect($matches)->not->toBeEmpty();
	expect($matches[0])->toContain('4294967296');
});

/**
 * compute - runs the real disk-size reconstruction chain extracted from
 * ss_host_disk() against the given SNMP counter and allocation unit
 *
 * @param  mixed $snmp_data Raw hrStorageSize/hrStorageUsed SNMP value
 * @param  mixed $sau       Allocation unit to multiply the size by
 *
 * @return mixed Reconstructed unsigned size, or 'U' when sau is missing
 */
$compute = function (mixed $snmp_data, mixed $sau) use ($matches) {
	// eval() here only wraps a chain regex-extracted from this repo's own
	// scripts/ss_host_disk.php (not external/user input) into a throwaway
	// named function. Test-only.
	$name = 'ss_host_disk_get_' . str_replace('.', '_', uniqid('', true));
	eval("function $name(\$snmp_data, \$sau) { {$matches[0]} }");

	return $name($snmp_data, $sau);
};

test('reconstructs the correct unsigned value for a wrapped negative size', function () use ($compute) {
	// True unsigned value 4294967200 appears as a signed 32-bit int as
	// 4294967200 - 2^32 = -96.
	$result = $compute(-96, 1);

	expect($result)->toEqual(4294967200);

	// The pre-fix formula: abs(-96) + 2147483647 = 2147483743 -- roughly
	// half the correct value.
	expect($result)->not->toEqual(2147483743);
});

test('multiplies the reconstructed size by the allocation unit', function () use ($compute) {
	// unsigned value 4294967200, allocation unit 4096 (4K blocks)
	$result = $compute(-96, 4096);

	expect($result)->toEqual(4294967200 * 4096);
});

test('returns U instead of silently zeroing out a missing allocation unit', function () use ($compute) {
	expect($compute(-96, ''))->toBe('U');
	expect($compute(-96, null))->toBe('U');
});

test('non-negative values are unaffected and still multiply normally', function () use ($compute) {
	expect($compute(500, 4096))->toEqual(500 * 4096);
});
