<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
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

	expect(utilities_mysql_variable_status($cap, 'MariaDB', '10.5.27'))->toBe('na')
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '10.6.17'))->toBe('na')
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '10.6.18'))->toBe('ok')
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '11.4.1'))->toBe('na')
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '11.4.2'))->toBe('ok')
		->and(utilities_mysql_variable_status($cap, 'MariaDB', '11.8.2'))->toBe('ok')
		->and(utilities_mysql_variable_status($cap, 'MySQL', '9.1.0'))->toBe('na');
});

test('utilities_mysql_version_introduced accepts a scalar or a per-branch list', function () {
	expect(utilities_mysql_version_introduced('11.4.2', '11.4.0'))->toBeTrue()
		->and(utilities_mysql_version_introduced('11.3.9', '11.4.0'))->toBeFalse()
		->and(utilities_mysql_version_introduced('10.6.18', array('10.6.18', '10.11.8', '11.4.2')))->toBeTrue()
		->and(utilities_mysql_version_introduced('10.11.7', array('10.6.18', '10.11.8', '11.4.2')))->toBeFalse()
		->and(utilities_mysql_version_introduced('12.0.0', array('10.6.18', '10.11.8', '11.4.2')))->toBeTrue();
});
