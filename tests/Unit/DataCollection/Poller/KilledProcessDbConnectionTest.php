<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/* A signal ends a Cacti process but not the statement it was running: the server keeps running it,
 * holding its locks, until it finishes. Issue #3698. A process records its connection when it
 * registers, and whoever retires its row for a timeout or an exit ends that connection. */

namespace KilledProcessDbConnectionTest;

/** Run the real lib/poller.php functions in their own process, with fake database rows. */
function results() {
	static $results = null;

	if ($results === null) {
		$fixture = dirname(__DIR__, 3) . '/fixtures/Core/Poller/KilledProcessDbConnection.php';
		$pipes   = [];
		$process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', $fixture], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

		expect($process)->not->toBeFalse();

		$output = stream_get_contents($pipes[1]);
		$error  = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);

		expect(proc_close($process))->toBe(0, $error);

		$results = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
	}

	return $results;
}

test('a process records its database connection when it registers', function () {
	expect(results()['register'][0])->toContain('connection_id')->toContain('CONNECTION_ID()')
		->and(results()['register'][1])->toBe(['boost', 'master', 1, 4242, 300]);
});

test('registering still works before the upgrade adds the column', function () {
	expect(results()['register_old'][0])->not->toContain('connection_id');
});

test('only a live connection of this user other than our own is ended', function () {
	expect(results()['kill_none'])->toBe([false, []])
		->and(results()['kill_gone'][0])->toBeFalse()
		->and(results()['kill_gone'][1])->toBe([])
		->and(results()['kill_gone'][2])->toContain('ID != CONNECTION_ID()')->toContain('CURRENT_USER()')
		->and(results()['kill_live'][0])->toBeTrue()
		->and(results()['kill_live'][1])->toBe(['KILL CONNECTION 77'])
		->and(results()['kill_live'][2][0])->toContain('connection 77');
});

test('a recorded connection is left alone once the server has restarted since the process registered', function () {
	expect(results()['kill_restarted'])->toBe([false, []])
		->and(results()['kill_no_uptime'])->toBe([false, []]);
});

test('the connection of a timed out or exited process is ended when its row is retired', function () {
	expect(results()['start_timed_out'])->toBe([true, ['KILL CONNECTION 88']])
		->and(results()['start_exited'])->toBe([true, ['KILL CONNECTION 88']])
		->and(results()['sweep'])->toBe(['KILL CONNECTION 88']);
});

test('a row registered before the upgrade has no connection to end', function () {
	expect(results()['start_old_row'])->toBe([true, []]);
});

test('the timeout sweep signals a process that is still running and ends its connection', function () {
	[$kills, $log, $signaled] = results()['sweep_running'];

	expect($kills)->toBe(['KILL CONNECTION 88'])
		->and($log)->toContain('Process killed due to timeout')
		->and($signaled)->toBeTrue();
});

/* The same rules again in this process, against a database that answers the server-side queries the way
 * MySQL does. A recorded KILL is all the fake does with one. */
function killed_process_db(int $uptime) : \FakeMySQLPDO {
	require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
	require_once dirname(__DIR__, 4) . '/lib/poller.php';

	$db = new class extends \FakeMySQLPDO {
		public array $sql = [];

		public int $uptime = 0;

		public function prepare(string $query, array $options = []): \PDOStatement|false {
			$this->sql[] = preg_replace('/\s+/', ' ', trim($query));

			// record the bound connection id, which only execute() sees
			if (trim($query) === 'KILL CONNECTION ?') {
				return parent::prepare('INSERT INTO killed (id) VALUES (?)', $options);
			}

			if (str_starts_with(trim($query), 'SHOW GLOBAL STATUS')) {
				return parent::prepare("SELECT 'Uptime' AS Variable_name, " . $this->uptime . ' AS Value', $options);
			}

			$query = str_replace(['information_schema.PROCESSLIST', 'CONNECTION_ID()', 'SUBSTRING_INDEX(CURRENT_USER(), "@", 1)'], ['processlist', '99', "'cactiuser'"], $query);
			$query = str_replace('NOW()', 'CURRENT_TIMESTAMP', $query);

			return parent::prepare($query, $options);
		}
	};

	$db->uptime = $uptime;
	$db->exec('CREATE TABLE processlist (ID INTEGER, USER TEXT, TIME INTEGER)');
	$db->exec('CREATE TABLE killed (id INTEGER)');
	$db->exec("INSERT INTO processlist VALUES (77, 'cactiuser', 40), (78, 'other', 40), (99, 'cactiuser', 0)");
	$db->exec('CREATE TABLE processes (tasktype TEXT, taskname TEXT, taskid INTEGER, pid INTEGER, timeout INTEGER, connection_id INTEGER, started TEXT, last_update TEXT)');

	$GLOBALS['database_sessions'][$GLOBALS['database_hostname'] . ':' . $GLOBALS['database_port'] . ':' . $GLOBALS['database_default']] = $db;

	return $db;
}

function killed_process_kills(\FakeMySQLPDO $db) : array {
	return array_map(fn ($id) => "KILL CONNECTION $id", $db->query('SELECT id FROM killed')->fetchAll(\PDO::FETCH_COLUMN));
}

beforeEach(function () {
	$this->killedProcessSessions = $GLOBALS['database_sessions'] ?? [];
});

afterEach(function () {
	$GLOBALS['database_sessions'] = $this->killedProcessSessions;
});

test('in process, only a live connection of this user from since the server started is ended', function () {
	$started = gmdate('Y-m-d H:i:s', time() - 600);
	$db      = killed_process_db(86400);

	expect(cacti_process_kill_db_connection(['connection_id' => 77, 'started' => $started]))->toBeTrue()
		->and(cacti_process_kill_db_connection(['connection_id' => 78, 'started' => $started]))->toBeFalse()
		->and(cacti_process_kill_db_connection(['connection_id' => 99, 'started' => $started]))->toBeFalse()
		->and(cacti_process_kill_db_connection(['connection_id' => 0, 'started' => $started]))->toBeFalse()
		->and(killed_process_kills($db))->toBe(['KILL CONNECTION 77']);

	$db = killed_process_db(60);

	expect(cacti_process_kill_db_connection(['connection_id' => 77, 'started' => $started]))->toBeFalse()
		->and(killed_process_kills($db))->toBe([]);
});

test('in process, a command line process records its connection', function () {
	$db = killed_process_db(86400);

	register_process('boost', 'master', 1, 4242, 300);

	expect(db_fetch_cell('SELECT connection_id FROM processes WHERE taskname = "master"'))->toEqual(99);
});
