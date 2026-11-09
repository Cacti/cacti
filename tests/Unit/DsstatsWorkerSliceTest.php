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
 * Each parallel DSStats worker reads one slice of the data sources from
 * get_rrdfile_names().  Together the slices must cover every data source
 * exactly once.
 */

require_once dirname(__DIR__) . '/Helpers/DsstatsTestDatabase.php';

beforeEach(function () {
	$this->pdo = dsstats_test_connect();

	// inserted out of order, the slices must not depend on the storage order
	foreach ([7, 3, 10, 1, 5, 9, 2, 8, 4, 6] as $id) {
		$this->pdo->exec("INSERT INTO data_template_data (local_data_id, data_source_path) VALUES ($id, '<path_rra>/$id.rrd')");
		$this->pdo->exec("INSERT INTO data_template_rrd (local_data_id, data_source_name) VALUES ($id, 'traffic_in'), ($id, 'traffic_out')");
		$this->pdo->exec("INSERT INTO poller_item (local_data_id, rrd_name) VALUES ($id, 'traffic_in'), ($id, 'traffic_out')");
	}
});

afterEach(function () {
	dsstats_test_restore();
});

test('three workers process each of ten data sources exactly once', function () {
	$seen = [];

	for ($thread = 1; $thread <= 3; $thread++) {
		foreach (get_rrdfile_names($thread, 3) as $row) {
			$seen[] = (int) $row['local_data_id'];
		}
	}

	sort($seen);

	expect($seen)->toBe(range(1, 10));
});

test('a worker slice is a contiguous run of data source ids', function () {
	$ids = array_map(function ($row) {
		return (int) $row['local_data_id'];
	}, get_rrdfile_names(2, 3));

	expect($ids)->toBe([5, 6, 7, 8]);
});
