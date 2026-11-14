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

test('schema validation passes on MariaDB once the installer has created the Boost run-time tables (#8718)', function () {
	$result = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/InstallerRuntimeTables.php');

	expect($result['tables'])->toBeGreaterThan(100)
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
