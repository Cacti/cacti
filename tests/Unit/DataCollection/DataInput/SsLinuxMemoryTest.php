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
 * scripts/ss_linux_memory.php, the PHP port of linux_memory.pl.
 */

$called_by_script_server = true;

require_once dirname(__DIR__, 4) . '/scripts/ss_linux_memory.php';

const SS_LINUX_MEMORY_FIXTURE = "MemTotal:       16314612 kB\n"
	. "MemFree:          812344 kB\n"
	. "Active:          7012340 kB\n"
	. "Active(anon):    5123400 kB\n"
	. "Active(file):    1888940 kB\n"
	. "SwapFree:        2097148 kB\n"
	. "HugePages_Total:       0\n";

test('parse returns the value of the exact key', function () {
	expect(ss_linux_memory_parse(SS_LINUX_MEMORY_FIXTURE, 'MemFree'))->toBe('812344')
		->and(ss_linux_memory_parse(SS_LINUX_MEMORY_FIXTURE, 'SwapFree'))->toBe('2097148');
});

test('parse does not run Active together with Active(anon) and Active(file)', function () {
	expect(ss_linux_memory_parse(SS_LINUX_MEMORY_FIXTURE, 'Active'))->toBe('7012340')
		->and(ss_linux_memory_parse(SS_LINUX_MEMORY_FIXTURE, 'Active(anon)'))->toBe('5123400');
});

test('parse returns U for a key that is absent or has no kB value', function () {
	expect(ss_linux_memory_parse(SS_LINUX_MEMORY_FIXTURE, 'Shmem'))->toBe('U')
		->and(ss_linux_memory_parse(SS_LINUX_MEMORY_FIXTURE, 'HugePages_Total'))->toBe('U')
		->and(ss_linux_memory_parse(SS_LINUX_MEMORY_FIXTURE, 'Mem'))->toBe('U');
});

test('rejected keys return U with a failing exit status', function (string $key) {
	expect(ss_linux_memory_collect($key))->toBe(['U', 1])
		->and(ss_linux_memory($key))->toBe('U');
})->with(['', 'Mem Free', 'MemFree;id', '-x', '../MemFree', 'MemFree::']);

test('a valid key reads /proc/meminfo where it exists', function () {
	[$value, $status] = ss_linux_memory_collect('MemTotal:');

	if (is_readable('/proc/meminfo')) {
		expect($status)->toBe(0)->and($value)->toMatch('/^\d+$/');
	} else {
		expect([$value, $status])->toBe(['U', 1]);
	}
});
