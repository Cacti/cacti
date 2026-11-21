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
 * The weekly, monthly and yearly statistics must come from the finest RRA
 * that covers the period, not from the RRA that suits a 400 pixel graph.
 */

require_once dirname(__DIR__) . '/Helpers/DsstatsTestDatabase.php';

beforeEach(function () {
	$this->rrdtool = trim((string) shell_exec('command -v rrdtool 2>/dev/null'));
	$this->pdo     = dsstats_test_connect(['path_rrdtool' => $this->rrdtool]);
	$this->rrdfile = tempnam(sys_get_temp_dir(), 'dsstats');
});

afterEach(function () {
	dsstats_test_restore();

	@unlink($this->rrdfile);
});

test('the graph is one pixel per step wide', function (string $interval, int $step, string $start, int $width) {
	$this->pdo->exec("INSERT INTO data_source_stats_command_cache VALUES (31, 'DEF:aa_a=\"f.rrd\":traffic:AVERAGE')");

	$pipe = dsstats_test_rrdtool("0x0\ntraffic-avg_avg=1.000000\nOK u:0.00 s:0.00 r:0.00\n");

	dsstats_obtain_data_source_avgpeak_values(31, $this->rrdfile, $interval, 0, false, $pipe, $step);

	expect(dsstats_test_commands($pipe)[0])->toStartWith("graph x --start now-1$start --end now --width=$width DEF:");
})->with([
	['daily', 300, 'day', 288],
	['weekly', 300, 'week', 2016],
	['monthly', 300, 'month', 8928],
	['yearly', 60, 'year', 527040],
	['weekly', 0, 'week', 2016],
]);

test('weekly statistics keep the five minute detail that a week long RRA holds', function () {
	if ($this->rrdtool == '' || CACTI_SERVER_OS != 'unix') {
		$this->markTestSkipped('RRDtool is not installed');
	}

	// a five minute RRA that covers ten days, then 30 minute, 2 hour and daily RRAs
	$now   = time() - (time() % 300);
	$start = $now - (10 * 86400);

	exec(escapeshellarg($this->rrdtool) . ' create ' . escapeshellarg($this->rrdfile) . ' --start ' . ($start - 300) .
		' --step 300 DS:traffic:GAUGE:600:U:U RRA:AVERAGE:0.5:1:3000 RRA:AVERAGE:0.5:6:700 RRA:AVERAGE:0.5:24:775 RRA:AVERAGE:0.5:288:797');

	// 10 with a spike of 1000 every 100th sample, so 1% of the samples
	$updates = [];

	for ($time = $start, $i = 0; $time <= $now; $time += 300, $i++) {
		$updates[] = $time . ':' . ($i % 100 == 0 ? 1000 : 10);
	}

	foreach (array_chunk($updates, 500) as $chunk) {
		exec(escapeshellarg($this->rrdtool) . ' update ' . escapeshellarg($this->rrdfile) . ' ' . implode(' ', $chunk));
	}

	$rrd_process = dsstats_rrdtool_init();
	$values      = dsstats_obtain_data_source_avgpeak_values(32, $this->rrdfile, 'weekly', 1, false, $rrd_process, 300);

	dsstats_rrdtool_close($rrd_process);

	// the 30 minute averages report 175 for both, (1000 + 5 * 10) / 6
	expect((float) $values['avg']['traffic']['p95n'])->toBe(10.0)
		->and((float) $values['avg']['traffic']['peak'])->toBe(1000.0);
});
