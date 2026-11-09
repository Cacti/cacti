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

namespace DsstatsRrdtoolResponseTest;

const CACTI_SERVER_OS = 'unix';

$GLOBALS['dsstats_responses']     = [];
$GLOBALS['dsstats_commands']      = [];
$GLOBALS['dsstats_cached']        = '';
$GLOBALS['dsstats_prepared']      = [];
$GLOBALS['dsstats_writes']        = [];

function read_config_option(string $name) : mixed {
	return match ($name) {
		'storage_location' => 0,
		default            => false,
	};
}

function set_config_option(string $name, mixed $value) : void {
}

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

function cacti_escapeshellarg(string $value) : string {
	return "'" . $value . "'";
}

function cacti_log(string $message, bool $print = false, string $tag = '') : void {
}

function file_exists(string $path) : bool {
	return true;
}

function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	return $GLOBALS['dsstats_cached'];
}

function db_fetch_cell(string $sql) : mixed {
	return false;
}

function db_execute_prepared(string $sql, array $params = []) : bool {
	$GLOBALS['dsstats_prepared'][] = [$sql, $params];

	return true;
}

function db_execute(string $sql) : bool {
	$GLOBALS['dsstats_writes'][] = $sql;

	return true;
}

function dsstats_rrdtool_execute(string $command, array $rrd_process) : string {
	$GLOBALS['dsstats_commands'][] = $command;

	return array_shift($GLOBALS['dsstats_responses']) ?? '';
}

$source = file_get_contents(CACTI_PATH_LIBRARY . '/dsstats.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read lib/dsstats.php for the RRDtool response tests.');
}

$code = 'namespace DsstatsRrdtoolResponseTest;';

foreach (['dsstats_sql_value', 'dsstats_write_buffer', 'dsstats_obtain_data_source_avgpeak_values', 'dsstats_get_stats_command', 'dsstats_rrdtool_execute'] as $name) {
	if (preg_match('/^function ' . $name . '\(.*?^}\R/ms', $source, $matches) !== 1) {
		throw new \RuntimeException("Unable to extract $name() for the RRDtool response tests.");
	}

	// the reader is tested directly, everything else talks to the stub above
	if ($name == 'dsstats_rrdtool_execute') {
		$matches[0] = str_replace('function dsstats_rrdtool_execute(', 'function dsstats_rrdtool_execute_under_test(', $matches[0]);
	}

	$code .= $matches[0];
}

// the code is lib/dsstats.php from this checkout, as in the other DSStats tests
eval($code);

function rrdtool_pipe(string $output) : array {
	$stdin  = fopen('php://memory', 'w+');
	$stdout = fopen('php://temp', 'w+');

	fwrite($stdout, $output);
	rewind($stdout);

	return [null, [$stdin, $stdout]];
}

beforeEach(function () {
	$GLOBALS['dsstats_responses'] = [];
	$GLOBALS['dsstats_commands']  = [];
	$GLOBALS['dsstats_cached']    = '';
	$GLOBALS['dsstats_prepared']  = [];
	$GLOBALS['dsstats_writes']    = [];
});

test('the pipe reader stops at the status line, not at OK inside a data source name', function () {
	$info   = "filename = \"ok.rrd\"\nds[LOOKUPS].index = 0\nds[LOOKUPS].type = \"GAUGE\"\nrra[0].cf = \"AVERAGE\"\nOK u:0.00 s:0.00 r:0.00\n";
	$second = "ERROR: opening 'missing.rrd': No such file or directory\n";
	$pipe   = rrdtool_pipe($info . $second);

	expect(dsstats_rrdtool_execute_under_test('info ok.rrd', $pipe))->toBe($info)
		->and(dsstats_rrdtool_execute_under_test('info missing.rrd', $pipe))->toBe($second);
});

test('negative statistics keep their sign and unknown statistics become NULL', function () {
	$GLOBALS['dsstats_cached']    = 'DEF:aa_a="neg.rrd":temp:AVERAGE PRINT:aa_a_aa:temp-avg_avg=%lf';
	$GLOBALS['dsstats_responses'] = ["0x0\ntemp-avg_avg=-14.526132\ntemp-peak_avg=-10.000000\nfan-avg_avg=-nan\nOK u:0.00 s:0.00 r:0.00\n"];

	$values = dsstats_obtain_data_source_avgpeak_values(1, 'neg.rrd', 'daily', 0, false, []);

	expect($values)->toBe([
		'avg' => [
			'temp' => ['avg' => '-14.526132', 'peak' => '-10.000000'],
			'fan'  => ['avg' => 'NULL'],
		],
	]);

	$stats = [1 => ['avg' => ['temp' => ['avg' => '-14.526132', 'peak' => '-10.000000'], 'fan' => ['avg' => 'NULL', 'peak' => 'NULL']]]];

	dsstats_write_buffer($stats, 'daily', 0);

	expect($GLOBALS['dsstats_writes'])->toHaveCount(1)
		->and($GLOBALS['dsstats_writes'][0])
		->toContain("('1','temp','0',-14.526132,-10.000000)")
		->toContain("('1','fan','0',NULL,NULL)");
});

test('only numbers are written unquoted and anything else is NULL', function () {
	expect(dsstats_sql_value('-1.5e+3'))->toBe('-1.5e+3')
		->and(dsstats_sql_value('0'))->toBe('0')
		->and(dsstats_sql_value('NULL'))->toBe('NULL')
		->and(dsstats_sql_value(''))->toBe('NULL')
		->and(dsstats_sql_value("1'); DROP"))->toBe('NULL');
});

test('an info response without data sources is not cached as a stats command', function () {
	$GLOBALS['dsstats_responses'] = ["ERROR: opening 'gone.rrd': No such file or directory\n"];

	expect(dsstats_get_stats_command(9, 'gone.rrd', false, 0, false, []))->toBeFalse()
		->and($GLOBALS['dsstats_prepared'])->toBe([]);
});

test('a blank cached stats command is rebuilt instead of executed', function () {
	$GLOBALS['dsstats_cached']    = ' ';
	$GLOBALS['dsstats_responses'] = [
		"ds[traffic].index = 0\nrra[0].cf = \"AVERAGE\"\nOK u:0.00 s:0.00 r:0.00\n",
		"0x0\ntraffic-avg_avg=300.000000\ntraffic-peak_avg=300.000000\nOK u:0.00 s:0.00 r:0.00\n",
	];

	$values = dsstats_obtain_data_source_avgpeak_values(21, 'c300.rrd', 'daily', 0, false, []);

	expect($GLOBALS['dsstats_commands'][0])->toBe("info 'c300.rrd'")
		->and($GLOBALS['dsstats_commands'][1])->toContain('DEF:aa_a="c300.rrd":traffic:AVERAGE')
		->and($GLOBALS['dsstats_prepared'][0][0])->toContain('REPLACE INTO data_source_stats_command_cache')
		->and($values)->toBe(['avg' => ['traffic' => ['avg' => '300.000000', 'peak' => '300.000000']]]);
});
