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
 * The hourly statistics are rolled up from the hourly cache.  A data source
 * whose whole window is unknown must become unknown, not keep the average
 * of an earlier window.
 */

// poller_dsstats.php runs when it is loaded, so evaluate the rollup alone
if (!function_exists('DsstatsHourlyRollupTest\dsstats_insert_hourly_data_into_cache')) {
	$source = file_get_contents(dirname(__DIR__, 2) . '/poller_dsstats.php');
	$parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
	$node   = (new \PhpParser\NodeFinder())->findFirst($parser->parse($source), static function ($node) {
		return $node instanceof \PhpParser\Node\Stmt\Function_ && $node->name->toString() === 'dsstats_insert_hourly_data_into_cache';
	});

	if ($node === null) {
		throw new RuntimeException('Unable to find dsstats_insert_hourly_data_into_cache() in poller_dsstats.php');
	}

	// the MySQL upsert is run by SQLite, which spells it ON CONFLICT ... DO UPDATE
	eval('namespace DsstatsHourlyRollupTest;
		function db_execute(string $sql) : bool {
			$sql = preg_replace(\'/ON DUPLICATE KEY UPDATE.*$/s\',
				\'ON CONFLICT (local_data_id, rrd_name, cf) DO UPDATE SET average = excluded.average, peak = excluded.peak\', $sql);

			return $GLOBALS[\'dsstats_rollup_pdo\']->exec($sql) !== false;
		}' .
		substr($source, $node->getStartFilePos(), $node->getEndFilePos() - $node->getStartFilePos() + 1));
}

beforeEach(function () {
	$pdo = new PDO('sqlite::memory:');
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->exec('CREATE TABLE data_source_stats_hourly_cache (local_data_id INTEGER, rrd_name TEXT, time TEXT, value REAL,
		PRIMARY KEY (local_data_id, time, rrd_name))');
	$pdo->exec('CREATE TABLE data_source_stats_hourly (local_data_id INTEGER, rrd_name TEXT, cf INTEGER DEFAULT 0,
		average REAL, peak REAL, PRIMARY KEY (local_data_id, rrd_name, cf))');

	$GLOBALS['dsstats_rollup_pdo'] = $pdo;
});

test('a window of only unknown samples replaces the previous hourly average with NULL', function () {
	$pdo = $GLOBALS['dsstats_rollup_pdo'];

	// the previous rollup, then a window in which one source went unknown
	$pdo->exec("INSERT INTO data_source_stats_hourly VALUES (1, 'traffic', 0, 50, 90), (2, 'traffic', 0, 5, 9)");
	$pdo->exec("INSERT INTO data_source_stats_hourly_cache VALUES
		(1, 'traffic', '2026-01-01 10:00:00', NULL), (1, 'traffic', '2026-01-01 10:05:00', NULL),
		(2, 'traffic', '2026-01-01 10:00:00', 10), (2, 'traffic', '2026-01-01 10:05:00', NULL), (2, 'traffic', '2026-01-01 10:10:00', 30)");

	\DsstatsHourlyRollupTest\dsstats_insert_hourly_data_into_cache();

	expect($pdo->query('SELECT local_data_id, average, peak FROM data_source_stats_hourly ORDER BY local_data_id')->fetchAll(PDO::FETCH_ASSOC))
		->toBe([
			['local_data_id' => 1, 'average' => null, 'peak' => null],
			['local_data_id' => 2, 'average' => 20.0, 'peak' => 30.0],
		]);
});
