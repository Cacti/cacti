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
 * poller_dsstats.php names each retired statistics table after the period it
 * holds, and dsstats_get_best_partition() must find that table again from the
 * start of the requested time range.  A week is named by its ISO year.
 */

require_once dirname(__DIR__) . '/Helpers/DsstatsTestDatabase.php';

// poller_dsstats.php runs when it is loaded, so evaluate its partition naming alone
if (!function_exists('DsstatsPartitionNameTest\dsstats_create_partitions')) {
	$source = file_get_contents(dirname(__DIR__, 2) . '/poller_dsstats.php');
	$parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
	$node   = (new \PhpParser\NodeFinder())->findFirst($parser->parse($source), static function ($node) {
		return $node instanceof \PhpParser\Node\Stmt\Function_ && $node->name->toString() === 'dsstats_create_partitions';
	});

	if ($node === null) {
		throw new RuntimeException('Unable to find dsstats_create_partitions() in poller_dsstats.php');
	}

	eval('namespace DsstatsPartitionNameTest;
		function dsstats_create_partition_from_table(string $table_name, string $suffix) : void {
			$GLOBALS[\'dsstats_partitions\'][$table_name] = $suffix;
		}' .
		substr($source, $node->getStartFilePos(), $node->getEndFilePos() - $node->getStartFilePos() + 1));
}

beforeEach(function () {
	$this->pdo = dsstats_test_connect();

	$GLOBALS['dsstats_partitions'] = [];
});

afterEach(function () {
	dsstats_test_restore();
});

test('a week that starts in December is named by its ISO year', function () {
	// Monday 30 December 2024 is in ISO week 1 of 2025
	\DsstatsPartitionNameTest\dsstats_create_partitions(mktime(12, 0, 0, 12, 30, 2024), mktime(12, 0, 0, 1, 6, 2025));

	expect($GLOBALS['dsstats_partitions'])->toBe([
		'data_source_stats_daily'   => '_v2024364',
		'data_source_stats_weekly'  => '_v2025001',
		'data_source_stats_monthly' => '_v2024012',
		'data_source_stats_yearly'  => '_v2024',
	]);
});

test('the best partition is the one that holds the start of the range', function (int $start, int $length, string $table) {
	$this->pdo->exec("CREATE TABLE $table (local_data_id INTEGER)");

	expect(dsstats_get_best_partition($start, $start + $length))->toBe($table);
})->with([
	'day'      => [mktime(12, 0, 0, 3, 10, 2025), 3600, 'data_source_stats_daily_v2025068'],
	'ISO week' => [mktime(12, 0, 0, 12, 30, 2024), 3 * 86400, 'data_source_stats_weekly_v2025001'],
	'month'    => [mktime(12, 0, 0, 2, 3, 2025), 20 * 86400, 'data_source_stats_monthly_v2025002'],
]);
