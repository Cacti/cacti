<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Rates that dsstats_poller_output() derives from COUNTER and DERIVE samples,
 * computed by the real lib/dsstats.php against SQLite copies of its tables.
 * dsstats_poller_output() caches data source metadata in a static, so every
 * test uses its own local_data_id.
 */

require_once dirname(__DIR__) . '/Helpers/DsstatsTestDatabase.php';

beforeEach(function () {
	$this->pdo = dsstats_test_connect(['dsstats_enable' => 'on', 'poller_interval' => 300]);
});

afterEach(function () {
	dsstats_test_restore();
});

/**
 * @param mixed $last The stored last value, or null for none
 */
function dsstats_rate_fixture(PDO $pdo, int $local_data_id, string $rrd_name, int $type, int $step, mixed $last = null) : void {
	$pdo->prepare('INSERT INTO data_template_data (local_data_id, rrd_step) VALUES (?, ?)')->execute([$local_data_id, $step]);
	$pdo->prepare('INSERT INTO data_template_rrd (local_data_id, data_source_name, data_source_type_id, rrd_maximum) VALUES (?, ?, ?, ?)')
		->execute([$local_data_id, $rrd_name, $type, 'U']);

	if ($last !== null) {
		$pdo->prepare('INSERT INTO data_source_stats_hourly_last (local_data_id, rrd_name, value) VALUES (?, ?, ?)')
			->execute([$local_data_id, $rrd_name, $last]);
	}
}

/**
 * @return array<int, mixed> The cached rates for one data source, oldest first
 */
function dsstats_rate_values(PDO $pdo, int $local_data_id) : array {
	$query = $pdo->prepare('SELECT value FROM data_source_stats_hourly_cache WHERE local_data_id = ? ORDER BY time');
	$query->execute([$local_data_id]);

	return $query->fetchAll(PDO::FETCH_COLUMN);
}

test('a DERIVE sample after an unknown sample is unknown rather than a TypeError', function () {
	dsstats_rate_fixture($this->pdo, 104, 'drv', 3, 300);

	// The unknown sample is remembered as 'NULL' for the next timestamp,
	// which arithmetic on PHP 8 rejects.
	$updates = [
		['local_data_id' => 104, 'times' => [1000 => ['drv' => 'U'], 1300 => ['drv' => 1600], 1600 => ['drv' => 2200]]],
	];

	dsstats_poller_output($updates);

	expect(dsstats_rate_values($this->pdo, 104))->toBe([null, null, 2.0]);
});

test('a 32-bit COUNTER that wraps adds 2^32 to the delta', function () {
	// 2^32 - 4294967000 = 296, plus 704 after the wrap, over 100 seconds
	dsstats_rate_fixture($this->pdo, 105, 'ctr32', 2, 100, '4294967000');

	$updates = [['local_data_id' => 105, 'times' => [2000 => ['ctr32' => '704']]]];

	dsstats_poller_output($updates);

	expect(dsstats_rate_values($this->pdo, 105))->toBe([10.0]);
});

test('a 64-bit COUNTER that wraps keeps a small delta exact', function () {
	// 2^64 - 6144 is exact as a double, and the delta must not lose its low digits
	dsstats_rate_fixture($this->pdo, 106, 'ctr64', 2, 1, '18446744073709545472');

	$updates = [['local_data_id' => 106, 'times' => [2000 => ['ctr64' => '1000']]]];

	dsstats_poller_output($updates);

	expect(dsstats_rate_values($this->pdo, 106))->toBe([7144.0]);
});

test('unsigned 64-bit counters preserve low bits between separate polls', function () {
	dsstats_rate_fixture($this->pdo, 107, 'precise', 2, 1);

	foreach ([2000 => '18446744073709551614', 2001 => '18446744073709551615', 2002 => '10'] as $time => $value) {
		$updates = [['local_data_id' => 107, 'times' => [$time => ['precise' => $value]]]];
		dsstats_poller_output($updates);
		expect($this->pdo->query('SELECT value FROM data_source_stats_hourly_last WHERE local_data_id = 107')->fetchColumn())->toBe($value);
	}

	expect(dsstats_rate_values($this->pdo, 107))->toBe([null, 1.0, 11.0]);
});

test('decimal counter strings with leading zeros are not parsed as octal', function () {
	expect(dsstats_counter_delta('099', '0100', 'U'))->toBe(1.0);
	expect(dsstats_counter_delta('18446744073709551615', '10', 5))->toBe('NULL');
});

test('floating counter samples retain fractional deltas and wrap limits', function () {
	expect(dsstats_counter_delta('100.25', '101.75', 'U'))->toBe(1.5);
	expect(dsstats_counter_delta('4294967294.5', '0.25', 'U'))->toBe(1.75);
	expect(dsstats_counter_delta('4294967294.5', '0.25', 0.5))->toBe('NULL');
});
