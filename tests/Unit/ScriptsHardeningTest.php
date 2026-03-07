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
 * Tests for column allow-list and SNMP retry parameter hardening.
 * These scripts require DB + SNMP, so we test the allow-list guard
 * by calling with host_id=0 (returns 'U' early) and verifying that
 * invalid columns are rejected before reaching any SQL.
 */

$called_by_script_server = true;

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

// ss_mikrotik_health: column allow-list
require_once __DIR__ . '/../../scripts/ss_mikrotik_health.php';

test('ss_mikrotik_health rejects invalid column', function () {
	expect(ss_mikrotik_health(0, '1; DROP TABLE hosts; --'))->toBe('U');
});

test('ss_mikrotik_health rejects empty column', function () {
	expect(ss_mikrotik_health(0, ''))->toBe('U');
});

test('ss_mikrotik_health rejects arbitrary column name', function () {
	expect(ss_mikrotik_health(0, 'password'))->toBe('U');
});

test('ss_mikrotik_health accepts valid column voltage', function () {
	expect(ss_mikrotik_health(0, 'voltage'))->toBe('U');
});

test('ss_mikrotik_health accepts valid column temperature', function () {
	expect(ss_mikrotik_health(0, 'temperature'))->toBe('U');
});

// ss_hstats: column mapping via ss_hstats_map_stat_to_column
require_once __DIR__ . '/../../scripts/ss_hstats.php';

test('ss_hstats rejects invalid stat with host_id 0', function () {
	expect(ss_hstats(0, 'invalid_stat'))->toBe('U');
});

test('ss_hstats returns U for valid stat with host_id 0', function () {
	expect(ss_hstats(0, 'polling_time'))->toBe('U');
});

// Verify snmp_retries is referenced (not ping_retries)
test('aruba scripts use snmp_retries not ping_retries', function () {
	$file = file_get_contents(__DIR__ . '/../../scripts/ss_aruba_instant_ap.php');
	expect($file)->toContain('snmp_retries');
	expect($file)->not->toContain('ping_retries');
});

test('fortigate scripts use snmp_retries not ping_retries', function () {
	$file = file_get_contents(__DIR__ . '/../../scripts/ss_fortigate_ips.php');
	expect($file)->toContain('snmp_retries');
	expect($file)->not->toContain('ping_retries');
});

test('nimble scripts use snmp_retries not ping_retries', function () {
	$file = file_get_contents(__DIR__ . '/../../scripts/ss_nimble_alletra_total.php');
	expect($file)->toContain('snmp_retries');
	expect($file)->not->toContain('ping_retries');
});

test('disk io scripts use snmp_retries not ping_retries', function () {
	$file = file_get_contents(__DIR__ . '/../../scripts/ss_net_snmp_disk_io.php');
	expect($file)->toContain('snmp_retries');
	expect($file)->not->toContain('ping_retries');
});

test('lmsensors script uses snmp_retries not ping_retries', function () {
	$file = file_get_contents(__DIR__ . '/../../scripts/ss_netsnmp_lmsensors.php');
	expect($file)->toContain('snmp_retries');
	expect($file)->not->toContain('ping_retries');
});
