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

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';
require_once dirname(__DIR__, 4) . '/lib/utility.php';

test('an empty engine bounds array means supported for every version', function () {
	$caps = utilities_mysql_variable_capabilities();
	$cap  = $caps['innodb_buffer_pool_size'];

	expect(utilities_mysql_variable_status($cap, 'MariaDB', '10.5.27'))->toBe('ok')
		->and(utilities_mysql_variable_status($cap, 'MySQL', '9.1.0'))->toBe('ok');
});

test('a missing engine key renders as not applicable', function () {
	$caps = utilities_mysql_variable_capabilities();
	$cap  = $caps['innodb_use_atomic_writes'];

	expect(utilities_mysql_variable_status($cap, 'MySQL', '8.0.40'))->toBe('na')
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '10.5.27'))->toBe('ok');
});

test('deprecated and removed boundaries are evaluated per engine and version', function () {
	$caps = utilities_mysql_variable_capabilities();
	$cap  = $caps['innodb_file_format'];

	// MariaDB: deprecated 10.2.2, removed 10.3.1.
	expect(utilities_mysql_variable_status($cap, 'MariaDB', '10.2.1'))->toBe('ok')
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '10.2.2'))->toBe('deprecated')
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '10.3.1'))->toBe('removed')
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '10.5.27'))->toBe('removed');

	// MySQL: deprecated 5.7.7, removed 8.0.0.
	expect(utilities_mysql_variable_status($cap, 'MySQL', '5.7.6'))->toBe('ok')
		->and(utilities_mysql_variable_status($cap, 'MySQL', '5.7.7'))->toBe('deprecated')
		->and(utilities_mysql_variable_status($cap, 'MySQL', '8.0.40'))->toBe('removed');
});

test('innodb_flush_neighbors is deprecated in MySQL 8.0.20 and removed in 8.4', function () {
	$caps = utilities_mysql_variable_capabilities();
	$cap  = $caps['innodb_flush_neighbors'];

	expect(utilities_mysql_variable_status($cap, 'MariaDB', '11.8.2'))->toBe('ok')
		->and(utilities_mysql_variable_status($cap, 'MySQL', '8.0.19'))->toBe('ok')
		->and(utilities_mysql_variable_status($cap, 'MySQL', '8.0.20'))->toBe('deprecated')
		->and(utilities_mysql_variable_status($cap, 'MySQL', '8.4.0'))->toBe('removed')
		->and(utilities_mysql_variable_status($cap, 'MySQL', '9.1.0'))->toBe('removed');
});

test('innodb_buffer_pool_instances follows the MariaDB lifecycle and stays on MySQL', function () {
	$caps = utilities_mysql_variable_capabilities();
	$cap  = $caps['innodb_buffer_pool_instances'];

	expect(utilities_mysql_variable_status($cap, 'MariaDB', '10.5.27'))->toBe('deprecated')
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '10.6.21'))->toBe('removed')
		->and(utilities_mysql_variable_status($cap, 'MySQL', '8.0.40'))->toBe('ok');
});

test('innodb_snapshot_isolation models branch-specific back-ports', function () {
	$caps = utilities_mysql_variable_capabilities();
	$cap  = $caps['innodb_snapshot_isolation'];

	// 10.5 never received the back-port.
	expect(utilities_mysql_variable_status($cap, 'MariaDB', '10.5.27'))->toBe('na')
		// 10.6 branch gained it in 10.6.18.
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '10.6.17'))->toBe('na')
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '10.6.18'))->toBe('ok')
		// 11.4 branch gained it in 11.4.2.
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '11.4.1'))->toBe('na')
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '11.4.2'))->toBe('ok')
		// Newer branches ship it natively.
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '11.8.2'))->toBe('ok')
		// MySQL never has it.
		->and(utilities_mysql_variable_status($cap, 'MySQL', '9.1.0'))->toBe('na');
});

test('utilities_mysql_version_introduced accepts a scalar or a per-branch list', function () {
	expect(utilities_mysql_version_introduced('11.4.2', '11.4.0'))->toBeTrue()
		->and(utilities_mysql_version_introduced('11.3.9', '11.4.0'))->toBeFalse()
		->and(utilities_mysql_version_introduced('10.6.18', ['10.6.18', '10.11.8', '11.4.2']))->toBeTrue()
		->and(utilities_mysql_version_introduced('10.11.7', ['10.6.18', '10.11.8', '11.4.2']))->toBeFalse()
		->and(utilities_mysql_version_introduced('12.0.0', ['10.6.18', '10.11.8', '11.4.2']))->toBeTrue();
});

test('version strings with non-numeric suffixes are normalized before comparison', function () {
	expect(utilities_mysql_normalize_version('10.11.2-MariaDB-1:10.11.2+maria~ubu2204'))->toBe('10.11.2')
		->and(utilities_mysql_normalize_version('8.0.35-0ubuntu0.22.04.1'))->toBe('8.0.35');

	$caps = utilities_mysql_variable_capabilities();

	// A suffixed build on or past a removal boundary must still read removed.
	$ff = $caps['innodb_file_format'];
	expect(utilities_mysql_variable_status($ff, 'MariaDB', '10.5.27-1:10.5.27+maria~ubu2004'))->toBe('removed')
		->and(utilities_mysql_variable_status($ff, 'MySQL', '8.0.35-0ubuntu0.22.04.1'))->toBe('removed');

	// The suffix must not tip a build just under a boundary across it.
	$fn = $caps['innodb_flush_neighbors'];
	expect(utilities_mysql_variable_status($fn, 'MySQL', '8.0.19-log'))->toBe('ok')
		->and(utilities_mysql_variable_status($fn, 'MySQL', '8.0.20-log'))->toBe('deprecated');
});

test('an unknown (null or empty) version is treated as supported', function () {
	$caps = utilities_mysql_variable_capabilities();
	$cap  = $caps['innodb_file_format'];

	expect(utilities_mysql_variable_status($cap, 'MariaDB', null))->toBe('ok')
		->and(utilities_mysql_variable_status($cap, 'MySQL', ''))->toBe('ok');
});
