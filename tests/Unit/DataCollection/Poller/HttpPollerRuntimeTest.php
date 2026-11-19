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
 * The HTTP action next to spine, and the HTTP prefetch's down-device and
 * time-budget rules in cmd.php.
 *
 * Spine has no HTTP action, so poller.php starts cmd.php --http-only next to
 * it.  The production functions are loaded unchanged into this namespace; the
 * database, logging, availability check and process launch are stubbed at
 * their boundaries, while the HTTP client is real against the loopback test
 * server.
 */

namespace CactiHttpPollerRuntimeTest;

require_once dirname(__DIR__, 4) . '/lib/functions.php';
require_once dirname(__DIR__, 4) . '/lib/CactiHttpPoller.php';
require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/Helpers/HttpTestServer.php';

class_alias(\CactiHttpPoller::class, __NAMESPACE__ . '\CactiHttpPoller');
class_alias(\CactiHttpPollItem::class, __NAMESPACE__ . '\CactiHttpPollItem');

foreach ([['/cmd.php', 'http_check_hosts'], ['/cmd.php', 'http_only_poll'], ['/cmd.php', 'http_prefetch_items'], ['/poller.php', 'poller_start_http_handoff'], ['/poller.php', 'poller_spine_version']] as [$file, $function]) {
	\cacti_fixture_source_function(dirname(__DIR__, 4) . $file, $function, __NAMESPACE__);
}

function stub_reset(array $rows = [], mixed $cell = false, array $config = []) : void {
	$GLOBALS['handoff'] = ['rows' => $rows, 'cell' => $cell, 'config' => $config + ['http_poller_concurrency' => '8'], 'queries' => [], 'executes' => [], 'execute_params' => [], 'boost' => [], 'launches' => [], 'pings' => [], 'logs' => []];
}

/**
 * A stand-in spine binary that prints $version the way spine --version does
 * and counts how often it was run.
 *
 * @return array{0: string, 1: string} the binary and its run counter
 */
function fake_spine(string $version) : array {
	$binary  = tempnam(sys_get_temp_dir(), 'cacti-fake-spine-');
	$counter = $binary . '.runs';

	file_put_contents($binary, "#!/bin/sh\necho run >> '$counter'\necho 'SPINE $version  Copyright 2004-2026 by The Cacti Group'\n");
	chmod($binary, 0700);

	return [$binary, $counter];
}

function runs(string $counter) : int {
	return is_file($counter) ? count(file($counter)) : 0;
}

function db_fetch_assoc_prepared($sql, $params = [], $log = true) {
	$GLOBALS['handoff']['queries'][] = [$sql, $params];

	return $GLOBALS['handoff']['rows'];
}

function db_fetch_cell_prepared($sql, $params = [], $col_name = '', $log = true) {
	$GLOBALS['handoff']['queries'][] = [$sql, $params];

	return $GLOBALS['handoff']['cell'];
}

function db_execute($sql, $log = true, $db_conn = false) {
	$GLOBALS['handoff']['executes'][] = $sql;

	return true;
}

function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false) {
	$GLOBALS['handoff']['executes'][]       = $sql;
	$GLOBALS['handoff']['execute_params'][] = $params;

	return true;
}

function db_qstr($value, $db_conn = false) {
	return "'" . addslashes((string) $value) . "'";
}

function db_column_exists($table, $column, $log = true) {
	return true;
}

function read_config_option($name, $force = false) {
	return $GLOBALS['handoff']['config'][$name] ?? '';
}

function set_config_option($name, $value, $remote = false) {
	$GLOBALS['handoff']['config'][$name] = $value;
}

function cacti_log($message, $print = false, $type = '', $level = 0) {
	$GLOBALS['handoff']['logs'][] = $message;
}

function boost_flush_output_batch($values, $db_conn = false) {
	$GLOBALS['handoff']['boost'][] = $values;

	return true;
}

function exec_background($filename, $args = '', $redirect_args = '') {
	$GLOBALS['handoff']['launches'][] = [$filename, $args, $redirect_args];
}

// host 3 is down; host 2 reports an agent restart, which arms spike kill
function ping_and_reindex_check(array &$item, bool $mibs, int $script_timeout) : bool {
	$GLOBALS['handoff']['pings'][] = $item['local_data_id'];

	if ($item['host_id'] == 2) {
		$GLOBALS['set_spike_kill'] = true;
	}

	return $item['host_id'] == 3;
}

function http_row(int $host_id, int $local_data_id, string $url, string $pointer = '/in') : array {
	return ['host_id' => $host_id, 'local_data_id' => $local_data_id, 'rrd_name' => 'value', 'action' => POLLER_ACTION_HTTP, 'arg1' => $url, 'arg2' => $pointer, 'arg3' => ''];
}

test('poller.php starts one cmd.php --http-only process only when the poller has HTTP items', function () {
	stub_reset([], false);

	expect(poller_start_http_handoff(4, ''))->toBeFalse()
		->and($GLOBALS['handoff']['launches'])->toBe([])
		->and($GLOBALS['handoff']['queries'][0][1])->toBe([4, POLLER_ACTION_HTTP]);

	[$spine] = fake_spine('1.3.0');

	stub_reset([], 1, ['path_spine' => $spine]);

	expect(poller_start_http_handoff(1, '>> /tmp/err 2>&1'))->toBeTrue()
		->and($GLOBALS['handoff']['launches'])->toHaveCount(1)
		->and($GLOBALS['handoff']['launches'][0][1])->toContain('cmd.php')
		->and($GLOBALS['handoff']['launches'][0][1])->toEndWith('--poller=1 --http-only')
		->and($GLOBALS['handoff']['launches'][0][2])->toBe('>> /tmp/err 2>&1');
});

test('the handoff stays off with one warning for a spine older than the first that skips HTTP items', function (string $version) {
	[$spine] = fake_spine($version);

	stub_reset([], 1, ['path_spine' => $spine]);

	expect(poller_start_http_handoff(1, ''))->toBeFalse()
		->and($GLOBALS['handoff']['launches'])->toBe([])
		->and($GLOBALS['handoff']['logs'])->toHaveCount(1)
		->and($GLOBALS['handoff']['logs'][0])->toContain('need spine >= ' . SPINE_HTTP_HANDOFF_MIN_VERSION . ' or cmd.php')
		->and($GLOBALS['handoff']['logs'][0])->toContain("spine $version ");
})->with(['1.2.31', '1.2.9']);

test('the handoff stays off with one warning when the spine version is unknown', function () {
	[$spine] = fake_spine('');

	// a binary that does not report a version, and a path that does not exist
	foreach ([$spine, sys_get_temp_dir() . '/cacti-no-such-spine'] as $path) {
		stub_reset([], 1, ['path_spine' => $path]);

		expect(poller_start_http_handoff(1, ''))->toBeFalse()
			->and($GLOBALS['handoff']['launches'])->toBe([])
			->and($GLOBALS['handoff']['logs'])->toHaveCount(1)
			->and($GLOBALS['handoff']['logs'][0])->toContain('spine of unknown version');
	}
});

test('spine is asked for its version once, and again only when the binary changes', function () {
	[$spine, $counter] = fake_spine('1.3.0');

	stub_reset([], 1, ['path_spine' => $spine]);

	expect(poller_spine_version())->toBe('1.3.0')
		->and(poller_spine_version())->toBe('1.3.0')
		->and(runs($counter))->toBe(1)
		->and($GLOBALS['handoff']['config']['spine_version'])->toBe('1.3.0');

	file_put_contents($spine, "#!/bin/sh\necho run >> '$counter'\necho 'SPINE 1.2.31  Copyright'\n");
	clearstatcache();

	expect(poller_spine_version())->toBe('1.2.31')
		->and(runs($counter))->toBe(2);
});

test('the spine-side pass writes HTTP output, skips devices marked down and leaves scheduling to spine', function () {
	$base = \cacti_http_test_server()['url'];

	stub_reset([http_row(2, 9, "$base/stats"), http_row(2, 10, "$base/missing")]);

	$written = http_only_poll(1, 60, 1, microtime(true));

	[$sql, $params] = $GLOBALS['handoff']['queries'][0];

	expect($written)->toBe(2)
		->and($sql)->toContain('pi.action = ?')
		->and($sql)->toContain('h.status != ?')
		->and($sql)->not->toContain('rrd_next_step')
		->and($params)->toBe([1, POLLER_ACTION_HTTP, HOST_DOWN])
		->and($GLOBALS['handoff']['executes'])->toHaveCount(1)
		->and($GLOBALS['handoff']['executes'][0])->toContain('VALUES (?, ?, CURRENT_TIMESTAMP(), ?), (?, ?, CURRENT_TIMESTAMP(), ?)')
		->and($GLOBALS['handoff']['execute_params'][0])->toBe([9, 'value', '5', 10, 'value', 'U'])
		->and($GLOBALS['handoff']['boost'])->toBe([])
		->and(implode(' ', $GLOBALS['handoff']['executes']))->not->toContain('UPDATE');
});

test('with boost enabled the spine-side pass also hands the rows to boost', function () {
	$base = \cacti_http_test_server()['url'];

	stub_reset([http_row(2, 9, "$base/stats")], false, ['boost_rrd_update_enable' => 'on']);

	expect(http_only_poll(1, 60, 1, microtime(true)))->toBe(1)
		->and($GLOBALS['handoff']['boost'])->toBe([["(9, 'value', CURRENT_TIMESTAMP(), '5')"]]);
});

test('with several profiles the spine-side pass picks due items from the polling slot', function () {
	stub_reset([]);

	// 1200 is a multiple of 300, so a 300 second item is due in the slot that starts there
	expect(http_only_poll(1, 60, 2, 1230.5))->toBe(0);

	[$sql, $params] = $GLOBALS['handoff']['queries'][0];

	expect($sql)->toContain('MOD(?, pi.rrd_step) < ?')
		->and($params)->toBe([1, POLLER_ACTION_HTTP, HOST_DOWN, 60, 1200, 60])
		->and($GLOBALS['handoff']['executes'])->toBe([]);
});

test('cmd.php checks each HTTP device once, as the walk would, and keeps its spike kill state', function () {
	stub_reset();

	$script       = ['host_id' => 2, 'local_data_id' => 8, 'action' => POLLER_ACTION_SCRIPT];
	$poller_items = [$script, http_row(2, 9, 'http://a/'), http_row(3, 11, 'http://b/'), ['host_id' => 4, 'local_data_id' => 12, 'action' => POLLER_ACTION_SCRIPT]];
	$http_items   = [$poller_items[1], $poller_items[2]];

	$checks = http_check_hosts($poller_items, $http_items, false, 25);

	// the first item of each device is the one pinged, devices without HTTP items wait for the walk
	expect($GLOBALS['handoff']['pings'])->toBe([8, 11])
		->and($checks)->toBe([2 => ['down' => false, 'spike_kill' => true], 3 => ['down' => true, 'spike_kill' => false]])
		->and($GLOBALS['set_spike_kill'])->toBeFalse();
});

test('the prefetch stops at its deadline and unfinished items report U', function () {
	$servers = \cacti_http_test_server();
	$base    = $servers['url'];
	$quick   = $servers['inflight'] . '/peak';
	$peak    = (string) json_decode(file_get_contents($quick), true, 512, JSON_THROW_ON_ERROR)['peak'];
	$poller  = new \CactiHttpPoller();

	$start   = microtime(true);
	$reasons = [];
	// Independent listeners keep the slow request from occupying the worker serving the quick response.
	$outputs = $poller->collect([http_row(2, 9, "$base/slower"), http_row(5, 10, $quick, '/peak')], $reasons, $start + 1.0);

	expect(microtime(true) - $start)->toBeLessThan(2.5)
		->and($outputs)->toBe(['9:value' => 'U', '10:value' => $peak])
		->and($reasons['9:value'])->toBe('Timed out');

	// a request that leaves the queue after the deadline is not sent
	$reasons = [];
	$seen    = count(\cacti_http_test_requests());
	$outputs = $poller->collect([http_row(2, 13, "$base/stats")], $reasons, microtime(true) - 1);

	expect($outputs)->toBe(['13:value' => 'U'])
		->and($reasons['13:value'])->toBe('Timed out')
		->and(count(\cacti_http_test_requests()))->toBe($seen);
});
