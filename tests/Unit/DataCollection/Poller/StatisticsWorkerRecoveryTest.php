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
 * Recovery paths of the statistics workers: reaping a registered worker that
 * is still alive, surviving an RRDtool pipe that never opened, and failing
 * closed when the process registry lock cannot be created or acquired.
 */

require_once dirname(__DIR__, 3) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/lib/database.php';
require_once dirname(__DIR__, 4) . '/lib/poller.php';
require_once dirname(__DIR__, 4) . '/lib/dsstats.php';
require_once dirname(__DIR__, 4) . '/lib/rrdcheck.php';

/**
 * Make $pdo the default Cacti connection and return the previous globals.
 */
function statistics_worker_connect(PDO $pdo) : array {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$saved             = [$database_sessions, $database_hostname, $database_port, $database_default];
	$database_hostname = 'statistics-worker-host';
	$database_port     = 3306;
	$database_default  = 'cacti_statistics_worker';

	$database_sessions["$database_hostname:$database_port:$database_default"] = $pdo;

	return $saved;
}

/**
 * Start a PHP child from this interpreter so the identity check accepts it.
 */
function statistics_worker_spawn() : array {
	$process = proc_open([PHP_BINARY, '-n', '-r', 'sleep(60);'], [0 => ['pipe', 'r']], $pipes);

	expect($process)->toBeResource();

	$deadline = microtime(true) + 5;

	// The child execs the interpreter asynchronously; wait until its exe link
	// names PHP so the reaper sees the same binary this process runs.
	while (microtime(true) < $deadline && @readlink('/proc/' . proc_get_status($process)['pid'] . '/exe') !== @readlink('/proc/' . getmypid() . '/exe')) {
		usleep(10000);
	}

	return [$process, $pipes, proc_get_status($process)['pid']];
}

/**
 * Wait for a child to exit and return its final status.
 * @param mixed $process
 */
function statistics_worker_wait($process) : array {
	$deadline = microtime(true) + 10;

	do {
		$status = proc_get_status($process);

		if (!$status['running']) {
			return $status;
		}

		usleep(20000);
	} while (microtime(true) < $deadline);

	proc_terminate($process, SIGKILL);

	return $status;
}

beforeEach(function () {
	$this->pdo = new FakeMySQLPDO();
	$this->pdo->exec("CREATE TABLE processes (pid INTEGER, tasktype TEXT, taskname TEXT, taskid INTEGER, timeout INTEGER DEFAULT 300, started TEXT DEFAULT '', last_update TEXT DEFAULT '', PRIMARY KEY (pid, tasktype, taskname, taskid))");
	$this->pdo->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$this->saved = statistics_worker_connect($this->pdo);
	$this->type  = $GLOBALS['type'] ?? null;
});

afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->saved;
	$GLOBALS['type']                                                            = $this->type;
});

test('an overrunning worker that is still alive is signalled and unregistered', function (string $system, string $type, string $taskname) {
	[$process, $pipes, $pid] = statistics_worker_spawn();
	$GLOBALS['type']         = $type;

	try {
		$insert = $this->pdo->prepare('INSERT INTO processes (pid, tasktype, taskname, taskid) VALUES (?, ?, ?, ?)');
		$insert->execute([$pid, $system, $taskname, 3]);
		$insert->execute([getmypid(), $system, $taskname, 4]);

		($system . '_kill_running_processes')();

		$status = statistics_worker_wait($process);

		expect($status['running'])->toBeFalse()
			->and($status['signaled'])->toBeTrue()
			->and($status['termsig'])->toBe(SIGTERM)
			->and($this->pdo->query('SELECT pid FROM processes ORDER BY pid')->fetchAll(PDO::FETCH_COLUMN))->toBe([getmypid()]);
	} finally {
		fclose($pipes[0]);
		proc_close($process);
	}
})->with([
	'rrdcheck boost child' => ['rrdcheck', 'bmaster', 'bchild'],
	'rrdcheck child'       => ['rrdcheck', 'master', 'child'],
	'dsstats boost child'  => ['dsstats', 'bmaster', 'bchild'],
	'dsstats daily child'  => ['dsstats', 'master', 'dchild'],
]);

test('a statistics command sent without an RRDtool reader returns no output', function () {
	$stdin = fopen('php://memory', 'w+');
	$pipes = [$stdin, null];

	expect(rrdcheck_rrdtool_execute(['info', '/var/lib/cacti/rra/missing.rrd'], $pipes))->toBeNull()
		->and(rrdcheck_rrdtool_execute('info missing.rrd', $pipes))->toBeNull()
		->and(dsstats_rrdtool_execute('info missing.rrd', [null, [$stdin, null]]))->toBe('')
		->and(dsstats_rrdtool_execute('info again.rrd', [null, [$stdin, null]]))->toBe('');

	rewind($stdin);

	expect(stream_get_contents($stdin))->toBe("info '/var/lib/cacti/rra/missing.rrd'\r\ninfo missing.rrd\r\ninfo missing.rrd\r\ninfo again.rrd\r\n");

	fclose($stdin);
});

test('a registry lock cannot be created over a connection that fails', function () {
	$broken = new class() extends FakeMySQLPDO {
		public function getAttribute(int $attribute) : mixed {
			throw new PDOException('connection lost');
		}
	};

	// The failure is logged, and logging reads its settings over the same connection.
	$broken->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	statistics_worker_connect($broken);

	expect(cacti_process_registry_lock('dsstats', 'master', 0))->toBeFalse();
});

test('a process cannot start when its registry lock cannot be acquired', function () {
	// A lock table from an incompatible schema makes every acquire fail.
	$this->pdo->exec('CREATE TABLE process_locks (name TEXT)');

	expect(register_process_start('rrdcheck', 'master', 0, 300))->toBeFalse()
		->and((int) $this->pdo->query('SELECT COUNT(*) FROM processes')->fetchColumn())->toBe(0);
});
