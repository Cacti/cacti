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

foreach ([['/cmd.php', 'http_check_hosts'], ['/cmd.php', 'http_only_poll'], ['/cmd.php', 'http_prefetch_items'], ['/poller.php', 'poller_start_http_handoff']] as [$file, $function]) {
	\cacti_fixture_source_function(dirname(__DIR__, 4) . $file, $function, __NAMESPACE__);
}

function stub_reset(array $rows = [], mixed $cell = false) : void {
	$GLOBALS['handoff'] = ['rows' => $rows, 'cell' => $cell, 'queries' => [], 'executes' => [], 'launches' => [], 'pings' => []];
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

function db_qstr($value, $db_conn = false) {
	return "'" . addslashes((string) $value) . "'";
}

function db_column_exists($table, $column, $log = true) {
	return true;
}

function read_config_option($name, $force = false) {
	return $name === 'http_poller_concurrency' ? '8' : '';
}

function cacti_log($message, $print = false, $type = '', $level = 0) {
}

function boost_flush_output_batch($values, $db_conn = false) {
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

	stub_reset([], 1);

	expect(poller_start_http_handoff(1, '>> /tmp/err 2>&1'))->toBeTrue()
		->and($GLOBALS['handoff']['launches'])->toHaveCount(1)
		->and($GLOBALS['handoff']['launches'][0][1])->toContain('cmd.php')
		->and($GLOBALS['handoff']['launches'][0][1])->toEndWith('--poller=1 --http-only')
		->and($GLOBALS['handoff']['launches'][0][2])->toBe('>> /tmp/err 2>&1');
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
		->and($GLOBALS['handoff']['executes'][0])->toContain("(9, 'value', CURRENT_TIMESTAMP(), '5')")
		->and($GLOBALS['handoff']['executes'][0])->toContain("(10, 'value', CURRENT_TIMESTAMP(), 'U')")
		->and(implode(' ', $GLOBALS['handoff']['executes']))->not->toContain('UPDATE');
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
	$base   = \cacti_http_test_server()['url'];
	$poller = new \CactiHttpPoller();

	$start   = microtime(true);
	$reasons = [];
	$outputs = $poller->collect([http_row(2, 9, "$base/slow"), http_row(5, 10, "$base/stats")], $reasons, $start + 0.3);

	expect(microtime(true) - $start)->toBeLessThan(0.9)
		->and($outputs)->toBe(['9:value' => 'U', '10:value' => '5'])
		->and($reasons['9:value'])->toBe('Timed out');

	// a request that leaves the queue after the deadline is not sent
	$reasons = [];
	$seen    = count(\cacti_http_test_requests());
	$outputs = $poller->collect([http_row(2, 13, "$base/stats")], $reasons, microtime(true) - 1);

	expect($outputs)->toBe(['13:value' => 'U'])
		->and($reasons['13:value'])->toBe('Timed out')
		->and(count(\cacti_http_test_requests()))->toBe($seen);
});
