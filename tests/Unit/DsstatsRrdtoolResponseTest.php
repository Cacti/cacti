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
 * Runs the real lib/dsstats.php against an SQLite copy of the DSStats tables
 * and an RRDtool stand-in whose responses are written in advance.
 */

require_once dirname(__DIR__) . '/Helpers/DsstatsTestDatabase.php';

beforeEach(function () {
	$this->pdo     = dsstats_test_connect();
	$this->rrdfile = tempnam(sys_get_temp_dir(), 'dsstats');
});

afterEach(function () {
	dsstats_test_restore();

	@unlink($this->rrdfile);
});

test('the pipe reader stops at the status line, not at OK inside a data source name', function () {
	$info   = "filename = \"ok.rrd\"\nds[LOOKUPS].index = 0\nds[LOOKUPS].type = \"GAUGE\"\nrra[0].cf = \"AVERAGE\"\nOK u:0.00 s:0.00 r:0.00\n";
	$second = "ERROR: opening 'missing.rrd': No such file or directory\n";
	$pipe   = dsstats_test_rrdtool($info . $second);

	expect(dsstats_rrdtool_execute('info ok.rrd', $pipe))->toBe($info)
		->and(dsstats_rrdtool_execute('info missing.rrd', $pipe))->toBe($second);
});

test('the pipe reader accepts the bare OK status line of an RRDtool built without getrusage', function () {
	$info   = "filename = \"ok.rrd\"\nds[LOOKUPS].index = 0\nrra[0].cf = \"AVERAGE\"\nOK\n";
	$second = "0x0\ntraffic-avg_avg=1.000000\nOK\r\n";
	$pipe   = dsstats_test_rrdtool($info . $second);

	expect(dsstats_rrdtool_execute('info ok.rrd', $pipe))->toBe($info)
		->and(dsstats_rrdtool_execute('graph -', $pipe))->toBe($second);
});

test('negative statistics keep their sign and unknown statistics become NULL', function () {
	$this->pdo->exec("INSERT INTO data_source_stats_command_cache VALUES (1, 'DEF:aa_a=\"neg.rrd\":temp:AVERAGE PRINT:aa_a_aa:temp-avg_avg=%lf')");

	$pipe   = dsstats_test_rrdtool("0x0\ntemp-avg_avg=-14.526132\ntemp-peak_avg=-10.000000\nfan-avg_avg=-nan\nOK u:0.00 s:0.00 r:0.00\n");
	$values = dsstats_obtain_data_source_avgpeak_values(1, $this->rrdfile, 'daily', 0, false, $pipe);

	expect($values)->toBe([
		'avg' => [
			'temp' => ['avg' => '-14.526132', 'peak' => '-10.000000'],
			'fan'  => ['avg' => 'NULL'],
		],
	]);

	$stats = [1 => $values];

	dsstats_write_buffer($stats, 'daily', 0);

	$rows = $this->pdo->query('SELECT rrd_name, average, peak FROM data_source_stats_daily ORDER BY rrd_name')->fetchAll();

	expect($this->pdo->statementsLike('INSERT INTO data_source_stats_daily'))->toHaveCount(1)
		->and($rows)->toBe([
			['rrd_name' => 'fan', 'average' => null, 'peak' => null],
			['rrd_name' => 'temp', 'average' => -14.526132, 'peak' => -10.0],
		]);
});

test('only numbers are written unquoted and anything else is NULL', function () {
	expect(dsstats_sql_value('-1.5e+3'))->toBe('-1.5e+3')
		->and(dsstats_sql_value('0'))->toBe('0')
		->and(dsstats_sql_value('NULL'))->toBe('NULL')
		->and(dsstats_sql_value(''))->toBe('NULL')
		->and(dsstats_sql_value("1'); DROP"))->toBe('NULL');
});

test('an info response without data sources is not cached as a stats command', function () {
	$pipe = dsstats_test_rrdtool("ERROR: opening 'gone.rrd': No such file or directory\n");

	expect(dsstats_get_stats_command(9, 'gone.rrd', false, 0, false, $pipe))->toBeFalse()
		->and($this->pdo->query('SELECT COUNT(*) FROM data_source_stats_command_cache')->fetchColumn())->toBe(0);
});

test('a blank cached stats command is rebuilt instead of executed', function () {
	$this->pdo->exec("INSERT INTO data_source_stats_command_cache VALUES (21, ' ')");

	$pipe = dsstats_test_rrdtool(
		"ds[traffic].index = 0\nrra[0].cf = \"AVERAGE\"\nOK u:0.00 s:0.00 r:0.00\n" .
		"0x0\ntraffic-avg_avg=300.000000\ntraffic-peak_avg=300.000000\nOK u:0.00 s:0.00 r:0.00\n"
	);

	$values   = dsstats_obtain_data_source_avgpeak_values(21, $this->rrdfile, 'daily', 0, false, $pipe);
	$commands = dsstats_test_commands($pipe);
	$cached   = $this->pdo->query('SELECT stats_command FROM data_source_stats_command_cache WHERE local_data_id = 21')->fetchColumn();

	expect($commands[0])->toBe("info '" . $this->rrdfile . "'")
		->and($commands[1])->toContain('DEF:aa_a="' . $this->rrdfile . '":traffic:AVERAGE')
		->and($cached)->toContain('DEF:aa_a="' . $this->rrdfile . '":traffic:AVERAGE')
		->and($values)->toBe(['avg' => ['traffic' => ['avg' => '300.000000', 'peak' => '300.000000']]]);
});
