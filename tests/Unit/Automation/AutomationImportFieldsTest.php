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
 * Runs a probe script in a child PHP process and returns its JSON verdict.
 *
 * A test that has to fake a Cacti runtime function must declare a name that
 * lib/ already owns. PHP function declarations are process-global and cannot
 * be undone, so the fake and the real declaration cannot coexist: whichever
 * lands first wins, and the loser fatals with "Cannot redeclare". Because
 * PHPUnit loads every file of a suite into one process, that turns the suite
 * into a load-order lottery -- a new test file that requires lib/database.php
 * is enough to break an unrelated test that fakes db_execute_prepared().
 *
 * Probe scripts sidestep the whole problem by owning their own process. They
 * live under a fixtures/ directory so PHPUnit does not collect them, declare
 * their fakes freely, and print a JSON verdict the parent asserts against.
 */

require_once dirname(__DIR__, 2) . '/Helpers/IsolatedProbe.php';
test('automation imports reject malformed fields before writes and preserve exported identifiers (#7470)', function () {
	$result = cacti_test_isolated_probe(dirname(__DIR__, 2) . '/fixtures/Core/AutomationImportFields.php');
	expect($result['cases'])->toBe(56);
});

test('every import field alias is the alias the matching SQL joins that table under (#7470)', function () {
	require_once dirname(__DIR__, 3) . '/lib/api_automation.php';

	$source  = file_get_contents(dirname(__DIR__, 3) . '/lib/api_automation.php');
	$aliases = automation_query_field_table_aliases();

	expect($aliases)->toHaveCount(7)
		->and(array_unique($aliases))->toBe($aliases);

	foreach ($aliases as $table => $alias) {
		expect($source)->toContain($table . ' AS ' . $alias . "\n");
	}
});
