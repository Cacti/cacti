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

namespace DsstatsStatisticsQueryTest;

$GLOBALS['dsstats_statistics_queries'] = [];
$GLOBALS['dsstats_statistics_row']     = [];

function db_fetch_row_prepared(string $sql, array $params = []) : array {
	$GLOBALS['dsstats_statistics_queries'][] = [$sql, $params];

	return $GLOBALS['dsstats_statistics_row'];
}

$source = file_get_contents(dirname(__DIR__, 2) . '/lib/dsstats.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read lib/dsstats.php for statistics query tests.');
}

if (preg_match('/function dsstats_fetch_statistics_totals\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract dsstats_fetch_statistics_totals() for query tests.');
}

$function = str_replace('function dsstats_fetch_statistics_totals(', 'function dsstats_fetch_statistics_totals_under_test(', $matches[0]);
eval('namespace DsstatsStatisticsQueryTest;' . $function);

beforeEach(function () {
	$GLOBALS['dsstats_statistics_queries'] = [];
	$GLOBALS['dsstats_statistics_row']     = [];
});

test('all DSStats totals are fetched in one conditional aggregate', function () {
	$GLOBALS['dsstats_statistics_row'] = [
		'rrd_user'  => '1.25', 'rrd_system' => '2.5', 'rrd_real' => '3.75',
		'rrd_files' => '4', 'dsses' => '5',
	];

	expect(dsstats_fetch_statistics_totals_under_test('%child_2%'))->toBe([
		'rrd_user'  => 1.25, 'rrd_system' => 2.5, 'rrd_real' => 3.75,
		'rrd_files' => 4.0, 'dsses' => 5.0,
	])
		->and($GLOBALS['dsstats_statistics_queries'])->toHaveCount(1)
		->and($GLOBALS['dsstats_statistics_queries'][0][0])->toContain('SUM(CASE WHEN name LIKE ? THEN value END)')
		->and($GLOBALS['dsstats_statistics_queries'][0][1])->toBe([
			'dsstats_rrd_user_%child_2%',
			'dsstats_rrd_system_%child_2%',
			'dsstats_rrd_real_%child_2%',
			'dsstats_total_rrds_%child_2%',
			'dsstats_total_dsses_%child_2%',
		]);
});

test('missing aggregate values are normalized to numeric zero', function () {
	expect(dsstats_fetch_statistics_totals_under_test('%bchild%'))->toBe([
		'rrd_user'  => 0.0, 'rrd_system' => 0.0, 'rrd_real' => 0.0,
		'rrd_files' => 0.0, 'dsses' => 0.0,
	]);
});
