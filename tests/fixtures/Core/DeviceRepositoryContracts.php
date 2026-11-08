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
*/

use Cacti\Repository\DeviceRepository;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once dirname(__DIR__, 3) . '/lib/ping.php';

// Keep the fixture usable in-process by Pest as well as by the native coverage
// runner. Only the connection boundary changes; all db_* helpers stay real.
(static function () : void {
	$names = ['database_sessions', 'database_hostname', 'database_port', 'database_default',
		'database_total_queries', 'database_last_error', 'database_log', 'affected_rows',
		'error_logged', 'config'];
	$saved = [];

	foreach ($names as $name) {
		if (array_key_exists($name, $GLOBALS)) {
			$saved[$name] = $GLOBALS[$name];
		}
	}

	$passed = 0;
	$check  = static function ($actual, $expected, string $label) use (&$passed) : void {
		if ($actual !== $expected) {
			throw new RuntimeException('Device repository contract failed: ' . $label);
		}
		$passed++;
	};

	try {
		$conn = new PDO('sqlite::memory:');
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$conn->exec('CREATE TABLE host (
			id INTEGER PRIMARY KEY AUTOINCREMENT, hostname TEXT NOT NULL,
			description TEXT, poller_id INTEGER, disabled TEXT DEFAULT \'\'
		)');
		$GLOBALS['database_hostname']           = 'repository-fixture';
		$GLOBALS['database_port']               = 0;
		$GLOBALS['database_default']            = 'repository-fixture';
		$GLOBALS['database_sessions']           = ['repository-fixture:0:repository-fixture' => $conn];
		$GLOBALS['database_total_queries']      = 0;
		$GLOBALS['config']['DEBUG_SQL_CONNECT'] = false;

		$repo = DeviceRepository::instance();
		$check(DeviceRepository::instance(), $repo, 'shared instance');
		$check($repo->all(), [], 'empty collection');
		$first  = $repo->save(['hostname' => 'router.example.net', 'description' => "West's router", 'poller_id' => 1]);
		$second = $repo->save(['id' => 0, 'hostname' => 'router.example.net', 'description' => 'East router', 'poller_id' => 1]);
		$third  = $repo->save(['hostname' => 'switch.example.net', 'description' => 'Access switch', 'poller_id' => 2]);
		$check([$first, $second, $third], [1, 2, 3], 'generated identities');
		$check($repo->find($first)['description'], "West's router", 'text round trip');
		$check($repo->find(99), null, 'missing identity');
		$check($repo->findByHostname('switch.example.net')['id'], $third, 'hostname lookup');
		$check($repo->findByHostname('missing.example.net'), null, 'missing hostname');
		$check(count($repo->all()), 3, 'all devices');
		$check(array_column($repo->all(['poller_id' => 1, 'disabled' => ''], 'description asc, id DESC', 1), 'id'), [$second], 'criteria, ordering and limit');
		$check(array_column($repo->all([], 'id'), 'id'), [1, 2, 3], 'default sort direction');
		$check(count($repo->all([], '  ')), 3, 'blank ordering');
		$check($repo->all(['description' => "West's router"])[0]['id'], $first, 'quoted criteria text');
		$check($repo->save(['id' => $second, 'description' => 'Updated router', 'disabled' => 'on']), $second, 'update identity');
		$check($repo->find($second)['description'], 'Updated router', 'updated value');
		$check($repo->find($first)['description'], "West's router", 'same-hostname sibling unchanged');
		$check($repo->save(['id' => $second, 'description' => 'Updated router']), $second, 'unchanged values still update');
		$check($repo->save(['id' => 99, 'description' => 'Ghost']), 0, 'update of a missing identity');
		$check($repo->find(99), null, 'missing identity is not created');
		$check($repo->save(['id' => 99]), 0, 'identity-only update of a missing identity');
		$conn->exec('UPDATE host SET description = NULL WHERE id = ' . $third);
		$check(array_column($repo->all(['description' => null]), 'id'), [$third], 'null criteria match IS NULL');
		$queries = $GLOBALS['database_total_queries'];
		$check($repo->save(['id' => $second]), $second, 'identity-only update');
		$check($GLOBALS['database_total_queries'], $queries + 1, 'identity-only update only checks existence');
		$queries = $GLOBALS['database_total_queries'];

		$invalid = [
			static fn () => $repo->all(['unknown_column' => 'value']),
			static fn () => $repo->all([], 'unknown_column'),
			static fn () => $repo->all([], 'id SIDEWAYS'),
			static fn () => $repo->all([], 'id ASC extra'),
			static fn () => $repo->all([], 'id,'),
			static fn () => $repo->all([], null, 0),
			static fn () => $repo->all([], null, -1),
			static fn () => $repo->save(['unknown_column' => 'value']),
			static fn () => $repo->save([]),
			static fn () => $repo->save(['id' => 0]),
		];

		foreach ($invalid as $index => $operation) {
			$rejected = false;

			try {
				$operation();
			} catch (InvalidArgumentException $exception) {
				$rejected = true;
			}
			$check($rejected, true, 'invalid argument ' . $index);
		}
		$check($GLOBALS['database_total_queries'], $queries, 'invalid arguments never reach the database');

		// The migrated status reader must stop cleanly when a device disappears.
		$GLOBALS['config']['config_options_array']['ping_failure_count']  = 1;
		$GLOBALS['config']['config_options_array']['ping_recovery_count'] = 1;
		$ping                                                             = new Net_Ping();
		update_host_status(HOST_DOWN, 99, $ping, AVAIL_PING, false);
		$check($GLOBALS['database_total_queries'], $queries + 1, 'status reader performs one lookup');
		$check(count($repo->all()), 3, 'missing status device leaves rows unchanged');

		// Exercise the established db_* failure returns without a live connection.
		$GLOBALS['database_sessions'] = [];
		$check($repo->find(1), null, 'failed identity read');
		$check($repo->findByHostname('router.example.net'), null, 'failed hostname read');
		$check($repo->all(), [], 'failed collection read');
		$check($repo->save(['hostname' => 'new.example.net']), 0, 'failed insert');
		$check($repo->save(['id' => 1, 'description' => 'Unavailable']), 0, 'failed update');
	} finally {
		foreach ($names as $name) {
			if (array_key_exists($name, $saved)) {
				$GLOBALS[$name] = $saved[$name];
			} else {
				unset($GLOBALS[$name]);
			}
		}
	}

	print json_encode(['passed' => $passed], JSON_THROW_ON_ERROR) . "\n";
})();
