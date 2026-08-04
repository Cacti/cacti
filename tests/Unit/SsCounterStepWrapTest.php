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
 * Tests for ss_counter_step() in scripts/ss_net_snmp_disk_io.php.
 *
 * The old inline wrap-handling subtracted $previous twice
 * (current + (modulus-1) - previous - previous), which produces a large
 * negative delta instead of the small positive one a wrapped counter
 * should yield. ss_counter_step() replaces that with current - previous,
 * or current + modulus - previous when the counter has wrapped.
 */

global $called_by_script_server;
$called_by_script_server = true;
require_once __DIR__ . '/../../scripts/ss_net_snmp_disk_io.php';

test('no wrap: delta is current minus previous', function () {
	expect(ss_counter_step('U', '105', '100', '4294967296'))->toBe('5');
});

test('32-bit counter wrap yields the correct small positive delta', function () {
	// previous=4294967290, current=5: counter wrapped past 2^32.
	// Correct: 5 + 4294967296 - 4294967290 = 11
	// Old buggy formula: current + (modulus-1) - previous - previous
	//   = 5 + 4294967295 - 4294967290 - 4294967290 = -4294967280
	$result = ss_counter_step('U', '5', '4294967290', '4294967296');

	expect($result)->toBe('11');

	$old_buggy = 5 + 4294967295 - 4294967290 - 4294967290;
	expect((string) $old_buggy)->not->toBe($result);
});

test('64-bit counter wrap yields the correct small positive delta', function () {
	// previous=18446744073709551610, current=5: Counter64 wrapped past 2^64.
	// Correct: 5 + 18446744073709551616 - 18446744073709551610 = 11
	expect(ss_counter_step('U', '5', '18446744073709551610', '18446744073709551616'))->toBe('11');
});

test('running total accumulates the wrap-corrected delta instead of replacing it', function () {
	// running=100, wrapped delta=11 -> 111
	expect(ss_counter_step('100', '5', '4294967290', '4294967296'))->toBe('111');
});

test('U running value is replaced by the first computed delta, not left as U', function () {
	expect(ss_counter_step('U', '105', '100', '4294967296'))->toBe('5');
});

test('non-numeric running value is treated the same as U', function () {
	expect(ss_counter_step('not-a-number', '105', '100', '4294967296'))->toBe('5');
});
