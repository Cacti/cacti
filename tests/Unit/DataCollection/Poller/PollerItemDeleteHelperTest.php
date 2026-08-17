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

require_once dirname(__DIR__, 3) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 4) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 4) . '/lib/database.php';
require_once dirname(__DIR__, 4) . '/lib/poller.php';

// poller_item_delete_for_data_source() / poller_item_delete_for_host() are the
// single chokepoint every "DELETE FROM poller_item" call site now goes
// through (#7535). Neither function resolves its own remote connection --
// callers pass an already-open $rcnn_id, exactly like every other
// remote-mirror call site in lib/api_device.php already does -- so these
// tests exercise them the same way DbExecutePreparedTest.php exercises
// db_execute_prepared(): two plain PDO sqlite::memory: handles standing in
// for the local/default connection and a remote Data Collector connection.

beforeEach(function () {
	global $database_hostname, $database_port, $database_default, $database_sessions;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];

	$database_hostname = 'unit-local';
	$database_port     = 0;
	$database_default  = 'unit-local';

	$this->local = new PDO('sqlite::memory:');
	$this->local->exec('CREATE TABLE poller_item (local_data_id INTEGER, host_id INTEGER, poller_id INTEGER)');

	$database_sessions = ["$database_hostname:$database_port:$database_default" => $this->local];

	$this->remote = new PDO('sqlite::memory:');
	$this->remote->exec('CREATE TABLE poller_item (local_data_id INTEGER, host_id INTEGER, poller_id INTEGER)');
});

// Put the default db_* connection back. Left in place, the sqlite handle
// answers every later read_config_option() in the run and throws on Cacti's
// MySQL SQL, aborting the suite.
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

function seedPollerItem(PDO $conn, int $localDataId, int $hostId, int $pollerId): void {
	$stmt = $conn->prepare('INSERT INTO poller_item (local_data_id, host_id, poller_id) VALUES (?, ?, ?)');
	$stmt->execute([$localDataId, $hostId, $pollerId]);
}

function pollerItemCount(PDO $conn, string $where = '1=1'): int {
	return (int) $conn->query("SELECT COUNT(*) FROM poller_item WHERE $where")->fetchColumn();
}

// --- poller_item_delete_for_data_source() ---

it('deletes the local row and leaves other data sources alone (local-only, no rcnn_id)', function () {
	seedPollerItem($this->local, 10, 1, 1);
	seedPollerItem($this->local, 11, 1, 1);

	poller_item_delete_for_data_source(10);

	expect(pollerItemCount($this->local))->toBe(1)
		->and(pollerItemCount($this->local, 'local_data_id = 11'))->toBe(1);
});

it('does not touch the remote connection when $rcnn_id is omitted', function () {
	seedPollerItem($this->local, 10, 1, 1);
	seedPollerItem($this->remote, 10, 1, 7);

	poller_item_delete_for_data_source(10);

	expect(pollerItemCount($this->local))->toBe(0)
		->and(pollerItemCount($this->remote))->toBe(1);
});

it('mirrors the delete to the remote connection when $rcnn_id is supplied', function () {
	seedPollerItem($this->local, 10, 1, 1);
	seedPollerItem($this->remote, 10, 1, 7);

	poller_item_delete_for_data_source(10, $this->remote);

	expect(pollerItemCount($this->local))->toBe(0)
		->and(pollerItemCount($this->remote))->toBe(0);
});

it('skips the local delete when $delete_local is false, mirroring only remotely', function () {
	seedPollerItem($this->local, 10, 1, 1);
	seedPollerItem($this->remote, 10, 1, 7);

	poller_item_delete_for_data_source(10, $this->remote, false);

	expect(pollerItemCount($this->local))->toBe(1)
		->and(pollerItemCount($this->remote))->toBe(0);
});

it('accepts an array of local_data_ids and deletes all of them on both sides', function () {
	seedPollerItem($this->local, 10, 1, 1);
	seedPollerItem($this->local, 11, 1, 1);
	seedPollerItem($this->local, 12, 1, 1);
	seedPollerItem($this->remote, 10, 1, 7);
	seedPollerItem($this->remote, 11, 1, 7);

	poller_item_delete_for_data_source([10, 11], $this->remote);

	expect(pollerItemCount($this->local))->toBe(1)
		->and(pollerItemCount($this->local, 'local_data_id = 12'))->toBe(1)
		->and(pollerItemCount($this->remote))->toBe(0);
});

it('is a no-op on an empty array of ids', function () {
	seedPollerItem($this->local, 10, 1, 1);
	seedPollerItem($this->remote, 10, 1, 7);

	poller_item_delete_for_data_source([], $this->remote);

	expect(pollerItemCount($this->local))->toBe(1)
		->and(pollerItemCount($this->remote))->toBe(1);
});

it('treats an explicit false $rcnn_id the same as omitting it', function () {
	seedPollerItem($this->local, 10, 1, 1);
	seedPollerItem($this->remote, 10, 1, 7);

	poller_item_delete_for_data_source(10, false);

	expect(pollerItemCount($this->local))->toBe(0)
		->and(pollerItemCount($this->remote))->toBe(1);
});

// --- poller_item_delete_for_host() ---

it('deletes local poller_item rows for a host, local-only by default', function () {
	seedPollerItem($this->local, 10, 5, 1);
	seedPollerItem($this->local, 11, 5, 1);
	seedPollerItem($this->local, 12, 6, 1);
	seedPollerItem($this->remote, 10, 5, 7);

	poller_item_delete_for_host(5);

	expect(pollerItemCount($this->local))->toBe(1)
		->and(pollerItemCount($this->local, 'host_id = 6'))->toBe(1)
		->and(pollerItemCount($this->remote))->toBe(1);
});

it('mirrors a host-keyed delete to an open remote connection', function () {
	seedPollerItem($this->local, 10, 5, 1);
	seedPollerItem($this->remote, 10, 5, 7);
	seedPollerItem($this->remote, 11, 5, 7);

	poller_item_delete_for_host(5, $this->remote);

	expect(pollerItemCount($this->local))->toBe(0)
		->and(pollerItemCount($this->remote))->toBe(0);
});

it('supports remote-only deletes (delete_local=false) for callers that already hold an open connection', function () {
	// This is the api_device_purge_from_remote() shape: the local row is
	// already gone by the time that function runs, and it reuses a
	// connection it already opened for eight other tables.
	seedPollerItem($this->local, 10, 5, 1);
	seedPollerItem($this->remote, 10, 5, 7);

	poller_item_delete_for_host(5, $this->remote, false);

	expect(pollerItemCount($this->local))->toBe(1)
		->and(pollerItemCount($this->remote))->toBe(0);
});

it('accepts an array of host_ids', function () {
	seedPollerItem($this->local, 10, 5, 1);
	seedPollerItem($this->local, 11, 6, 1);
	seedPollerItem($this->local, 12, 9, 1);

	poller_item_delete_for_host([5, 6]);

	expect(pollerItemCount($this->local))->toBe(1)
		->and(pollerItemCount($this->local, 'host_id = 9'))->toBe(1);
});
