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

require_once dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

test('the migration and already-upgraded repair preserve Boost rows and report failed DDL (#8718)', function () {
	$result = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/InstallerRuntimeTables.php');

	expect($result['cli_status'])->toBe(0)
		->and($result['cli_repaired'])->toBeTrue()
		->and($result['before'])->toContain('poller_output_boost_local_data_ids', 'poller_output_boost_failures')
		->and($result['migrated'])->toBeTrue()
		->and($result['replayed'])->toBeTrue()
		->and($result['healthy'])->toBe('')
		->and($result['healthy_queries'])->toBe(0)
		->and($result['failed'])->toContain('poller_output_boost_local_data_ids')
		->and($result['repaired'])->toBe('')
		->and($result['repair_silent'])->toBeTrue()
		->and($result['process_rows'])->toBe(1)
		->and($result['version'])->toBe('1.2.32')
		->and($result['tables'])->toBeGreaterThan(100)
		->and($result['first'])->toBe('')
		->and($result['second'])->toBe('')
		->and($result['columns'])->toBe([
		'poller_output_boost_local_data_ids' => 5,
		'poller_output_boost_failures'       => 12,
	])
		->and($result['rows'])->toBe(1)
		->and($result['stopped'])->toBeTrue()
		->and($result['compare'])->toBe([['string', 'string']])
		->and($result['deleted'])->toBe(1);
});
