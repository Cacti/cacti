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
 * dsstats_write_buffer() batches the statistics into bulk inserts of up to
 * about 256 KB each.
 */

require_once dirname(__DIR__) . '/Helpers/DsstatsTestDatabase.php';

beforeEach(function () {
	$this->pdo = dsstats_test_connect();
});

afterEach(function () {
	dsstats_test_restore();
});

test('40000 statistics are written in batches close to the packet size', function () {
	$stats = [];

	for ($local_data_id = 1; $local_data_id <= 20000; $local_data_id++) {
		$stats[$local_data_id]['avg'] = [
			'traffic_in'  => ['avg' => '1234.567890', 'peak' => '9876.543210'],
			'traffic_out' => ['avg' => '1234.567890', 'peak' => '9876.543210'],
		];
	}

	dsstats_write_buffer($stats, 'daily', 0);

	$inserts = $this->pdo->statementsLike('INSERT INTO data_source_stats_daily');
	$bytes   = array_sum(array_map('strlen', $inserts));

	expect($this->pdo->query('SELECT COUNT(*) FROM data_source_stats_daily')->fetchColumn())->toBe(40000)
		->and(count($inserts))->toBeLessThanOrEqual((int) ceil($bytes / 256000) + 1)
		->and(min(array_map('strlen', array_slice($inserts, 0, -1))))->toBeGreaterThan(200000);
});
