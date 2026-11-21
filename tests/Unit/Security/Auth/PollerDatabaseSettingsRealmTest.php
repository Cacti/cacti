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
 * The remote database connection of a Data Collector decides where Full Sync
 * and Auth Sync send the main database's tables. Setting it, testing it and
 * running either synchronization take the Settings permission, not only the
 * permission that opens pollers.php. The probe runs the page's own handlers.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

$collector_gate_probe = dirname(__DIR__, 3) . '/fixtures/Core/CollectorCleanupGates.php';

$remote_database_fields = ['dbdefault', 'dbhost', 'dbuser', 'dbpass', 'dbport', 'dbretries', 'dbssl', 'dbsslkey', 'dbsslcert', 'dbsslca', 'dbsslcapath', 'dbsslverifyservercert'];

test('the synchronization actions are only offered with the Settings permission', function () use ($collector_gate_probe) {
	$result = cacti_test_isolated_probe($collector_gate_probe, ['collector']);

	expect($result['allowed']['actions'])->toBe([1, 4, 6])
		->and($result['denied']['actions'])->toBe([1]);
});

test('the connection test only runs with the Settings permission', function () use ($collector_gate_probe) {
	$result = cacti_test_isolated_probe($collector_gate_probe, ['collector']);

	expect($result['allowed']['ping'])->toBe(['test_database_connection'])
		->and($result['denied']['ping'])->toBe([]);
});

test('saving a collector only stores the database fields with the Settings permission', function () use ($collector_gate_probe, $remote_database_fields) {
	$result = cacti_test_isolated_probe($collector_gate_probe, ['collector']);

	expect(array_intersect($remote_database_fields, $result['allowed']['saved']))->toBe($remote_database_fields)
		->and(array_intersect($remote_database_fields, $result['denied']['saved']))->toBe([])
		->and($result['denied']['saved'])->toContain('name', 'hostname', 'sync_interval')
		->and($result['allowed']['save_calls'])->toBe(['poller_host_duplicate'])
		->and($result['denied']['save_calls'])->toBe([]);
});

test('the edit form only shows the database fields with the Settings permission', function () use ($collector_gate_probe, $remote_database_fields) {
	$form = cacti_test_isolated_probe($collector_gate_probe, ['collector'])['form'];

	expect(array_intersect($remote_database_fields, $form['remote']))->toBe($remote_database_fields)
		->and($form['remote'])->toContain('spacer_remotedb', 'spacerssl', 'spacertest', 'sync_interval');

	foreach (['remote-denied', 'main', 'main-denied'] as $case) {
		expect(array_intersect($remote_database_fields, $form[$case]))->toBe([])
			->and($form[$case])->not->toContain('spacer_remotedb')
			->and($form[$case])->not->toContain('spacerssl')
			->and($form[$case])->toContain('name', 'hostname', 'processes');
	}

	expect($form['remote-denied'])->toContain('sync_interval')
		->and($form['remote-denied'])->not->toContain('spacertest')
		->and($form['main'])->not->toContain('sync_interval');
});

test('a synchronization is refused without the Settings permission', function (int $action) use ($collector_gate_probe) {
	$result = cacti_test_isolated_probe($collector_gate_probe, ['sync-denied', $action]);

	expect($result['messages'])->toBe(['poller_sync_permission'])
		->and($result['writes'])->toBe([])
		->and($result['queries'])->toBe([]);
})->with([
	'full sync' => [4],
	'auth sync' => [6],
]);

test('the permission check precedes the synchronization work', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/pollers.php');
	$deny   = strpos($source, '&& !is_realm_allowed(15)) {');
	$work   = strpos($source, "\$class = 'all';");

	expect($deny)->not->toBeFalse()
		->and($work)->not->toBeFalse()
		->and($deny)->toBeLessThan($work);
});
