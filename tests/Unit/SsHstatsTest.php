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

/*
 * Tests for scripts/ss_hstats.php switch case consolidation.
 *
 * Verifies that consolidating 8 duplicate case branches into a single
 * fall-through case maintains identical behavior for all stat types.
 *
 * The function ss_hstats() had 8 cases that all executed `$column = $stat;`,
 * and one special case 'uptime' that maps to 'snmp_sysUpTimeInstance'.
 * After consolidation, the 8 cases use fall-through pattern.
 */

// Set script server mode to prevent CLI initialization
// Declared as global to ensure proper scope when loaded by PHPUnit
global $called_by_script_server;
$called_by_script_server = true;
require_once __DIR__ . '/../../scripts/ss_hstats.php';

// Test stat-to-column mapping function directly (verifies switch logic)
test('polling_time maps to polling_time column', function () {
	expect(ss_hstats_map_stat_to_column('polling_time'))->toBe('polling_time');
});

test('min_time maps to min_time column', function () {
	expect(ss_hstats_map_stat_to_column('min_time'))->toBe('min_time');
});

test('max_time maps to max_time column', function () {
	expect(ss_hstats_map_stat_to_column('max_time'))->toBe('max_time');
});

test('cur_time maps to cur_time column', function () {
	expect(ss_hstats_map_stat_to_column('cur_time'))->toBe('cur_time');
});

test('avg_time maps to avg_time column', function () {
	expect(ss_hstats_map_stat_to_column('avg_time'))->toBe('avg_time');
});

test('failed_polls maps to failed_polls column', function () {
	expect(ss_hstats_map_stat_to_column('failed_polls'))->toBe('failed_polls');
});

test('availability maps to availability column', function () {
	expect(ss_hstats_map_stat_to_column('availability'))->toBe('availability');
});

test('current_errors maps to current_errors column', function () {
	expect(ss_hstats_map_stat_to_column('current_errors'))->toBe('current_errors');
});

test('uptime maps to snmp_sysUpTimeInstance column', function () {
	expect(ss_hstats_map_stat_to_column('uptime'))->toBe('snmp_sysUpTimeInstance');
});

test('invalid stat returns null', function () {
	expect(ss_hstats_map_stat_to_column('invalid_stat'))->toBeNull();
});

test('empty stat returns null', function () {
	expect(ss_hstats_map_stat_to_column(''))->toBeNull();
});

test('all identity-mapped stats return the stat name as column', function () {
	$identity_stats = [
		'polling_time',
		'min_time',
		'max_time',
		'cur_time',
		'avg_time',
		'failed_polls',
		'availability',
		'current_errors',
	];

	foreach ($identity_stats as $stat) {
		expect(ss_hstats_map_stat_to_column($stat))->toBe($stat);
	}
});

// Test ss_hstats() wrapper behavior with host_id=0
test('ss_hstats returns U when host_id is zero', function () {
	expect(ss_hstats(0, 'polling_time'))->toBe('U')
		->and(ss_hstats(0, 'uptime'))->toBe('U')
		->and(ss_hstats(0, 'invalid'))->toBe('U');
});
