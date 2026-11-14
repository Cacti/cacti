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

test('the upgrade adds the connection column to the process table', function () {
	expect(results()['upgrade'])->toHaveCount(1)
		->and(results()['upgrade'][0][1])->toMatchArray(['name' => 'connection_id', 'NULL' => false, 'default' => '0']);
});
