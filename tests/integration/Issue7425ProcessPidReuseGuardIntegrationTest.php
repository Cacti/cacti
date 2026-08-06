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

/*
 * Integration coverage for issue #7425. The unit test covers
 * cacti_process_still_running() on its own; these drive the registry functions
 * that call it, against a real (sqlite-backed) connection, because the bug was
 * about what those functions then do: refuse to start a task whose pid has been
 * recycled, or send SIGTERM to whatever inherited it.
 *
 * The child processes here are php, so the /proc command-name comparison sees
 * the same name it sees for the test runner and the kill paths are reached on
 * Linux as well as where /proc is absent.
 */

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/include/global_constants.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';

/* include/global.php resolves these from config and the settings table, which
   would need a live install; the registry only logs through them. */
foreach ([
	'CACTI_WEB'      => false,
	'POLLER_ID'      => 1,
	'CACTI_PATH_LOG' => sys_get_temp_dir() . '/cacti-issue-7425-test.log',
] as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
}

require_once dirname(__DIR__, 2) . '/lib/poller.php';

/**
 * A FakeMySQLPDO that also understands the two MySQL constructs the registry
 * queries use. Kept local to this file rather than added to the shared helper,
 * which every other integration test depends on.
 */
class ProcessRegistryPDO extends FakeMySQLPDO {
	public function __construct() {
		parent::__construct();
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [ProcessRegistryStatement::class]);
	}

	public function prepare(string $query, array $options = []) : PDOStatement|false {
		return parent::prepare($this->rewrite($query), $options);
	}

	public function query(string $statement, ?int $fetchMode = null, mixed ...$args) : PDOStatement|false {
		$rewritten = $this->rewrite($statement);

		return $fetchMode === null
			? parent::query($rewritten)
			: parent::query($rewritten, $fetchMode, ...$args);
	}

	public function exec(string $statement) : int|false {
		return parent::exec($this->rewrite($statement));
	}

	/**
	 * Rewrites the MySQL spellings sqlite does not share.
	 *
	 * @param string $sql The statement to rewrite.
	 *
	 * @return string The sqlite equivalent.
	 */
	private function rewrite(string $sql) : string {
		/* Both forms are cast, and that matters: strftime() returns TEXT, and
		   sqlite orders every integer before every string, so the unwrapped
		   "started + timeout < now" comparison the registry makes is true
		   whatever the clock says. The shared helper's own UNIX_TIMESTAMP
		   rewrite has the same hazard. */
		$sql = preg_replace('/UNIX_TIMESTAMP\s*\(\s*\)/i', "CAST(strftime('%s','now') AS INTEGER)", $sql);
		$sql = preg_replace('/UNIX_TIMESTAMP\s*\(([^)]+)\)/i', "CAST(strftime('%s', $1) AS INTEGER)", $sql);

		$sql = preg_replace('/\bNOW\s*\(\s*\)/i', "datetime('now')", $sql);
		$sql = preg_replace('/FROM_UNIXTIME\s*\(([^)]+)\)/i', "datetime($1, 'unixepoch')", $sql);

		// IF(a, b, c); the word boundary keeps IFNULL and friends intact
		return preg_replace('/\bIF\s*\(/i', 'iif(', $sql);
	}
}

/**
 * sqlite reports rowCount() as 0 for SELECT, which db_fetch_row_return() reads
 * as no rows at all.
 */
class ProcessRegistryStatement extends PDOStatement {
	/** @var array<int,array<string,mixed>>|null rows of a SELECT, null otherwise */
	private ?array $rows = null;

	protected function __construct() {
	}

	public function execute(?array $params = null) : bool {
		$executed = parent::execute($params);

		$this->rows = $this->columnCount() > 0 ? parent::fetchAll(PDO::FETCH_ASSOC) : null;

		return $executed;
	}

	public function rowCount() : int {
		return $this->rows === null ? parent::rowCount() : count($this->rows);
	}

	public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args) : array {
		return $this->rows ?? parent::fetchAll($mode, ...$args);
	}
}

/**
 * Creates the processes table.
 *
 * @return ProcessRegistryPDO A connection holding the seeded schema.
 */
function registry_seed() : ProcessRegistryPDO {
	$conn = new ProcessRegistryPDO();

	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');

	// the column list from cacti.sql, so a missing one does not surface as an
	// unrelated SQL error partway through a registry call
	$conn->exec("CREATE TABLE processes (
		id INTEGER PRIMARY KEY AUTOINCREMENT, pid INTEGER NOT NULL DEFAULT 0,
		tasktype TEXT NOT NULL DEFAULT '', taskname TEXT NOT NULL DEFAULT '',
		taskid INTEGER NOT NULL DEFAULT 0, timeout INTEGER DEFAULT 300,
		started TEXT NOT NULL DEFAULT '', last_update TEXT NOT NULL DEFAULT ''
	)");

	return $conn;
}

/**
 * Registers a row by hand so the state under test is explicit.
 *
 * @param PDO $conn    The seeded connection.
 * @param int $pid     The pid to record.
 * @param int $age     How many seconds ago the process started.
 * @param int $timeout The registered timeout.
 *
 * @return void
 */
function registry_register(PDO $conn, int $pid, int $age, int $timeout = 300) : void {
	/* A timed-out row is the one the registry signals, so letting a test point
	   one at the runner would have it SIGTERM itself and take the suite down
	   with no output at all. */
	if ($age > $timeout && $pid === getmypid()) {
		throw new LogicException('refusing to register the test runner as a timed-out process');
	}

	$started = gmdate('Y-m-d H:i:s', time() - $age);

	$conn->exec("INSERT INTO processes (pid, tasktype, taskname, taskid, started, timeout)
		VALUES ($pid, 'poller', 'child', 1, '$started', $timeout)");
}

/**
 * Starts a long-running php child, whose command name matches the runner's.
 *
 * @param resource|null $handle Receives the process handle.
 *
 * @return int The child's pid.
 */
function registry_spawn_php(&$handle) : int {
	$pipes  = [];
	/* The array form matters: a string command is run through sh, so the child
	   would be sh and its /proc comm would not match the runner's. */
	$handle = proc_open([PHP_BINARY, '-r', 'sleep(30);'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

	$pid = (int) proc_get_status($handle)['pid'];

	foreach ($pipes as $pipe) {
		fclose($pipe);
	}

	return $pid;
}

/**
 * A pid that is certain to be gone, obtained by starting and reaping a child.
 *
 * @return int The pid of an exited process.
 */
function registry_dead_pid() : int {
	$pipes  = [];
	$handle = proc_open([PHP_BINARY, '-r', 'exit(0);'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$pid    = (int) proc_get_status($handle)['pid'];

	foreach ($pipes as $pipe) {
		fclose($pipe);
	}

	proc_close($handle);

	return $pid;
}

function registry_wire(PDO $conn) : void {
	$GLOBALS['database_hostname']      = 'unittest';
	$GLOBALS['database_port']          = 0;
	$GLOBALS['database_default']       = 'unittest';
	$GLOBALS['database_total_queries'] = 0;
	$GLOBALS['config']                 = $GLOBALS['config'] ?? [];
	$GLOBALS['database_sessions']      = ['unittest:0:unittest' => $conn];
}

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];
});

/* Left in place, the fake handle answers every later read_config_option() in
   the run and throws on Cacti's MySQL SQL, aborting the suite. */
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

test('a registration held by a live process of ours still blocks a new start', function () {
	$conn = registry_seed();
	registry_wire($conn);

	registry_register($conn, getmypid(), 10);

	expect(register_process_start('poller', 'child', 1, 300))->toBeFalse();
});

test('a registration whose pid has died no longer blocks a new start', function () {
	$conn = registry_seed();
	registry_wire($conn);

	// the recycled-pid case: the row survives its process
	registry_register($conn, registry_dead_pid(), 10);

	expect(register_process_start('poller', 'child', 1, 300))->toBeTrue();

	$row = $conn->query('SELECT pid FROM processes WHERE tasktype = "poller"')->fetch(PDO::FETCH_ASSOC);

	expect((int) $row['pid'])->toBe(getmypid());
});

test('a timed-out registration whose pid has died is not signalled', function () {
	$conn = registry_seed();
	registry_wire($conn);

	registry_register($conn, registry_dead_pid(), 600, 300);

	// nothing to kill, but the row must still be replaced
	expect(register_process_start('poller', 'child', 1, 300))->toBeTrue();

	$row = $conn->query('SELECT pid FROM processes WHERE tasktype = "poller"')->fetch(PDO::FETCH_ASSOC);

	expect((int) $row['pid'])->toBe(getmypid());
});

test('a timed-out registration held by a live process is signalled', function () {
	$conn = registry_seed();
	registry_wire($conn);

	$handle = null;
	$pid    = registry_spawn_php($handle);

	registry_register($conn, $pid, 600, 300);

	expect(register_process_start('poller', 'child', 1, 300))->toBeTrue();

	// give the signal a moment to land before asking whether it did
	for ($wait = 0; $wait < 50 && proc_get_status($handle)['running']; $wait++) {
		usleep(20000);
	}

	expect(proc_get_status($handle)['running'])->toBeFalse();

	proc_close($handle);
});

test('the timeout sweep only signals pids that are still ours', function () {
	$conn = registry_seed();
	registry_wire($conn);

	$handle = null;
	$live   = registry_spawn_php($handle);

	registry_register($conn, $live, 600, 300);

	expect(timeout_kill_registered_processes('poller', 'child', 1))->toBeTrue();

	for ($wait = 0; $wait < 50 && proc_get_status($handle)['running']; $wait++) {
		usleep(20000);
	}

	expect(proc_get_status($handle)['running'])->toBeFalse()
		->and($conn->query('SELECT COUNT(*) AS c FROM processes')->fetch(PDO::FETCH_ASSOC)['c'])->toBe(0);

	proc_close($handle);
});

test('is_process_running reports a live registration running and reaps a dead one', function () {
	$conn = registry_seed();
	registry_wire($conn);

	registry_register($conn, getmypid(), 10);

	expect(is_process_running('poller', 'child', 1))->toBeTrue();

	$conn->exec('DELETE FROM processes');
	registry_register($conn, registry_dead_pid(), 10);

	// 97 is the "exited but never unregistered" answer
	expect(is_process_running('poller', 'child', 1))->toBe(97)
		->and($conn->query('SELECT COUNT(*) AS c FROM processes')->fetch(PDO::FETCH_ASSOC)['c'])->toBe(0);
});

/**
 * The sweep's predicate read FROM_UNIXTIME(started) where every other registry
 * query reads UNIX_TIMESTAMP(started). started is a timestamp column, so that
 * expression never matched and nothing was ever timed out, which also left the
 * pid guard above it unreachable.
 */
test('the sweep compares started as an epoch, not as a date', function () {
	$poller = file_get_contents(dirname(__DIR__, 2) . '/lib/poller.php');

	expect($poller)->toContain('UNIX_TIMESTAMP() > UNIX_TIMESTAMP(started) + timeout')
		->and($poller)->not->toContain('FROM_UNIXTIME(started)');
});

test('a registration inside its timeout survives the sweep', function () {
	$conn = registry_seed();
	registry_wire($conn);

	$handle = null;
	$live   = registry_spawn_php($handle);

	registry_register($conn, $live, 10, 300);

	expect(timeout_kill_registered_processes('poller', 'child', 1))->toBeTrue()
		->and(proc_get_status($handle)['running'])->toBeTrue()
		->and($conn->query('SELECT COUNT(*) AS c FROM processes')->fetch(PDO::FETCH_ASSOC)['c'])->toBe(1);

	proc_terminate($handle);
	proc_close($handle);
});
