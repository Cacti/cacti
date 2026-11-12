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
 * A DSStats worker writes its statistics as it goes rather than holding the
 * statistics of its whole slice in memory until the end.  The worker runs
 * against a shell script that answers like 'rrdtool -'.
 */

require_once dirname(__DIR__) . '/Helpers/DsstatsTestDatabase.php';

beforeEach(function () {
	if (CACTI_SERVER_OS != 'unix') {
		$this->markTestSkipped('The RRDtool stand-in is a shell script');
	}

	$this->dir     = 'dsstats_flush_' . getmypid();
	$this->rrdtool = tempnam(sys_get_temp_dir(), 'rrdtool');

	file_put_contents($this->rrdtool, "#!/bin/sh\n" .
		"while read -r line; do\n" .
		"\tcase \"\$line\" in\n" .
		"\t\tinfo*) printf 'step = 300\\nds[traffic].index = 0\\nrra[0].cf = \"AVERAGE\"\\nOK u:0.00 s:0.00 r:0.00\\n' ;;\n" .
		"\t\t*) printf '0x0\\ntraffic-avg_avg=1.000000\\ntraffic-peak_avg=2.000000\\nOK u:0.00 s:0.00 r:0.00\\n' ;;\n" .
		"\tesac\n" .
		"done\n");
	chmod($this->rrdtool, 0700);

	mkdir(CACTI_PATH_RRA . '/' . $this->dir);

	$this->pdo = dsstats_test_connect([
		'dsstats_parallel' => 1,
		'dsstats_mode'     => 0,
		'dsstats_peak'     => '',
		'path_rrdtool'     => $this->rrdtool,
	]);
});

afterEach(function () {
	dsstats_test_restore();

	if (isset($this->dir)) {
		array_map('unlink', glob(CACTI_PATH_RRA . '/' . $this->dir . '/*.rrd'));
		rmdir(CACTI_PATH_RRA . '/' . $this->dir);
		unlink($this->rrdtool);
	}
});

test('a worker with 2100 files writes after every 1000 files', function () {
	// get_data_source_path() caches by local_data_id, so use ids no other test uses
	$insert = $this->pdo->prepare('INSERT INTO data_template_data (local_data_id, data_source_path, rrd_step, rrd_num) VALUES (?, ?, 300, 1)');

	for ($local_data_id = 41001; $local_data_id <= 43100; $local_data_id++) {
		touch(CACTI_PATH_RRA . '/' . $this->dir . "/$local_data_id.rrd");

		$insert->execute([$local_data_id, '<path_rra>/' . $this->dir . "/$local_data_id.rrd"]);
		$this->pdo->exec("INSERT INTO data_template_rrd (local_data_id, data_source_name) VALUES ($local_data_id, 'traffic')");
		$this->pdo->exec("INSERT INTO poller_item (local_data_id, rrd_name) VALUES ($local_data_id, 'traffic')");
	}

	dsstats_get_and_store_ds_avgpeak_values('daily', '', 1);

	$inserts = $this->pdo->statementsLike('INSERT INTO data_source_stats_daily');

	expect($this->pdo->query('SELECT COUNT(*) FROM data_source_stats_daily')->fetchColumn())->toBe(2100)
		->and($inserts)->toHaveCount(3)
		->and(substr_count($inserts[0], "'traffic'"))->toBe(1000)
		->and(substr_count($inserts[2], "'traffic'"))->toBe(100);
});
