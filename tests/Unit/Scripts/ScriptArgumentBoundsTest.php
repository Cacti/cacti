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
 * Argument bounds in the script server scripts: ss_fping limits the number
 * of sweeps and sees the script server flag that ends a run before the
 * script timeout, and ss_count_oids only walks a well-formed OID.
 */

namespace CactiScriptArgumentBoundsFixture;

require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$GLOBALS['ss_bounds_db_reads'] = 0;

function db_fetch_row_prepared($sql, $params = array()) {
	$GLOBALS['ss_bounds_db_reads']++;

	return array();
}

if (!function_exists(__NAMESPACE__ . '\\ss_fping_sweeps')) {
	$root = dirname(__DIR__, 3);

	\cacti_fixture_source_function($root . '/scripts/ss_fping.php', 'ss_fping_sweeps', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/scripts/ss_count_oids.php', 'ss_count_oids', __NAMESPACE__);
}

test('ss_fping keeps the sweep count between 1 and 20', function () {
	expect(ss_fping_sweeps('6'))->toBe(6);
	expect(ss_fping_sweeps(1))->toBe(1);
	expect(ss_fping_sweeps('20'))->toBe(20);
	expect(ss_fping_sweeps('21'))->toBe(20);
	expect(ss_fping_sweeps('100000'))->toBe(20);
	expect(ss_fping_sweeps('0'))->toBe(1);
	expect(ss_fping_sweeps('-3'))->toBe(1);
	expect(ss_fping_sweeps('2.9'))->toBe(2);
});

test('ss_fping uses the default sweep count for a non-numeric value', function () {
	expect(ss_fping_sweeps(''))->toBe(6);
	expect(ss_fping_sweeps('six'))->toBe(6);
	expect(ss_fping_sweeps(null))->toBe(6);
});

test('ss_fping reads the script server flag from global scope', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/scripts/ss_fping.php');

	preg_match('/^function ss_fping\(.*?^\}/ms', $source, $match);

	expect($match[0])->toContain('global $called_by_script_server;');
	expect($match[0])->toContain('$ping_sweeps = ss_fping_sweeps($ping_sweeps);');
});

test('ss_count_oids refuses an OID that is not numeric or MIB::name form', function () {
	$GLOBALS['ss_bounds_db_reads'] = 0;

	foreach (array('', '-Cc', '--version', '.1.3 .1.4', ".1.3\n", '.1.3;') as $oid) {
		expect(ss_count_oids(1, $oid))->toBe('0');
	}

	expect($GLOBALS['ss_bounds_db_reads'])->toBe(0);
});

test('ss_count_oids accepts numeric and MIB::name OIDs', function () {
	$GLOBALS['ss_bounds_db_reads'] = 0;

	foreach (array('.1.3.6.1.2.1.2.2.1.1', '1.3.6.1', 'IF-MIB::ifIndex') as $oid) {
		expect(ss_count_oids(1, $oid))->toBe('0');
	}

	expect($GLOBALS['ss_bounds_db_reads'])->toBe(3);
});

test('ss_count_oids walks with the poller SNMP environment', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/scripts/ss_count_oids.php');

	expect($source)->toContain('SNMP_POLLER');
	expect($source)->not->toContain('SNMP_WEBUI');
});
