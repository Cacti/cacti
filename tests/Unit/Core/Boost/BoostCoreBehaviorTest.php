<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 4) . '/lib/boost.php';

test('archive table validation accepts only generated numeric identifiers', function () {
	$valid = array(
		'poller_output_boost_arch_0',
		'poller_output_boost_arch_1234567890',
		'poller_output_boost_arch_0001',
	);

	foreach ($valid as $table) {
		expect(boost_is_valid_archive_table($table))->toBeTrue($table);
	}

	$invalid = array(
		'',
		null,
		123,
		'poller_output_boost_arch_',
		'poller_output_boost_arch_-1',
		'poller_output_boost_arch_1.0',
		'poller_output_boost_arch_1_extra',
		"poller_output_boost_arch_1\n",
		'poller_output_boost_arch_1; DROP TABLE settings',
		'../poller_output_boost_arch_1',
	);

	foreach ($invalid as $table) {
		expect(boost_is_valid_archive_table($table))->toBeFalse(var_export($table, true));
	}
});

test('multi-column ordering honors each requested direction', function () {
	$rows = array(
		array('group' => 'b', 'value' => 1, 'id' => 'third'),
		array('group' => 'a', 'value' => 1, 'id' => 'second'),
		array('group' => 'a', 'value' => 3, 'id' => 'first'),
	);

	$sorted = boost_array_orderby($rows, 'group', SORT_ASC, 'value', SORT_DESC);

	expect(array_column($sorted, 'id'))->toBe(array('first', 'second', 'third'));
});

test('bounded Boost pages never split one data-source timestamp', function () {
	$rows = array(
		array('local_data_id' => 1, 'timestamp' => 100, 'rrd_name' => 'a'),
		array('local_data_id' => 1, 'timestamp' => 100, 'rrd_name' => 'b'),
		array('local_data_id' => 1, 'timestamp' => 101, 'rrd_name' => 'a'),
		array('local_data_id' => 1, 'timestamp' => 101, 'rrd_name' => 'b'),
	);

	$page = boost_limit_complete_timestamp_page($rows, 3);

	expect($page)->toHaveCount(2)
		->and(array_unique(array_column($page, 'timestamp')))->toBe(array(100));
});

test('a timestamp wider than the configured Boost page fails closed', function () {
	$rows = array(
		array('local_data_id' => 1, 'timestamp' => 100, 'rrd_name' => 'a'),
		array('local_data_id' => 1, 'timestamp' => 100, 'rrd_name' => 'b'),
		array('local_data_id' => 1, 'timestamp' => 100, 'rrd_name' => 'c'),
	);

	expect(boost_limit_complete_timestamp_page($rows, 2))->toBeFalse();
});

test('a complete page at the configured limit is retained intact', function () {
	$rows = array(
		array('local_data_id' => 1, 'timestamp' => 100, 'rrd_name' => 'a'),
		array('local_data_id' => 1, 'timestamp' => 100, 'rrd_name' => 'b'),
	);

	expect(boost_limit_complete_timestamp_page($rows, 2))->toBe($rows);
});

test('atomic cache publication writes all bytes replaces an old file and leaves no temporary files', function () {
	$directory  = sys_get_temp_dir() . '/cacti-boost-core-' . bin2hex(random_bytes(8));
	$cache_file = $directory . '/cache.png';
	$first      = str_repeat('first-payload-', 4096);
	$second     = str_repeat('second-payload-', 8192);

	expect(mkdir($directory, 0700))->toBeTrue();

	try {
		expect(boost_atomic_write_cache($cache_file, $first))->toBeTrue()
			->and(file_get_contents($cache_file))->toBe($first)
			->and(boost_atomic_write_cache($cache_file, $second))->toBeTrue()
			->and(file_get_contents($cache_file))->toBe($second)
			->and(glob($directory . '/.boost-*'))->toBe(array());
	} finally {
		@unlink($cache_file);
		@rmdir($directory);
	}
});

test('atomic cache publication supports an empty payload without leaking a temporary file', function () {
	$directory  = sys_get_temp_dir() . '/cacti-boost-empty-' . bin2hex(random_bytes(8));
	$cache_file = $directory . '/cache.png';

	expect(mkdir($directory, 0700))->toBeTrue();

	try {
		expect(boost_atomic_write_cache($cache_file, ''))->toBeTrue()
			->and(file_get_contents($cache_file))->toBe('')
			->and(glob($directory . '/.boost-*'))->toBe(array());
	} finally {
		@unlink($cache_file);
		@rmdir($directory);
	}
});

test('Windows cache replacement preserves the new payload and removes its backup', function () {
	$directory  = sys_get_temp_dir() . '/cacti-boost-replace-' . bin2hex(random_bytes(8));
	$cache_file = $directory . '/cache.png';
	$temp_file  = $directory . '/pending.png';

	expect(mkdir($directory, 0700))->toBeTrue();
	file_put_contents($cache_file, 'old');
	file_put_contents($temp_file, 'new');

	try {
		expect(boost_replace_cache_file_on_windows($temp_file, $cache_file))->toBeTrue()
			->and(file_get_contents($cache_file))->toBe('new')
			->and(file_exists($temp_file))->toBeFalse()
			->and(glob($directory . '/.boost-old-*'))->toBe(array());
	} finally {
		@unlink($temp_file);
		@unlink($cache_file);
		foreach (glob($directory . '/.boost-old-*') ?: array() as $backup) {
			@unlink($backup);
		}
		@rmdir($directory);
	}
});

test('Windows cache replacement rejects a missing source or destination', function () {
	$missing = sys_get_temp_dir() . '/cacti-boost-missing-' . bin2hex(random_bytes(8));

	expect(boost_replace_cache_file_on_windows($missing . '-source', $missing . '-destination'))->toBeFalse();
});

test('Boost timer records complete cycles and ignores an unmatched end', function () {
	if (!defined('BOOST_TIMER_START')) {
		define('BOOST_TIMER_START', 0);
	}

	if (!defined('BOOST_TIMER_END')) {
		define('BOOST_TIMER_END', 1);
	}

	if (!defined('BOOST_TIMER_TOTAL')) {
		define('BOOST_TIMER_TOTAL', 2);
	}

	if (!defined('BOOST_TIMER_CYCLES')) {
		define('BOOST_TIMER_CYCLES', 3);
	}

	$GLOBALS['boost_stats_log'] = array();

	boost_timer('unmatched', BOOST_TIMER_END);
	boost_timer('query', BOOST_TIMER_START);
	usleep(1000);
	boost_timer('query', BOOST_TIMER_END);
	boost_timer('query', BOOST_TIMER_START);
	usleep(1000);
	boost_timer('query', BOOST_TIMER_END);

	expect($GLOBALS['boost_stats_log'])->not->toHaveKey('unmatched')
		->and($GLOBALS['boost_stats_log']['query'][BOOST_TIMER_CYCLES])->toBe(2)
		->and($GLOBALS['boost_stats_log']['query'][BOOST_TIMER_TOTAL])->toBeGreaterThan(0.0)
		->and($GLOBALS['boost_stats_log']['query'])->not->toHaveKey(BOOST_TIMER_START);
});
