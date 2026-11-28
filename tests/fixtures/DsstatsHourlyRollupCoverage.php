<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Request-coverage probe for the hourly rollup in poller_dsstats.php.
// poller_dsstats.php runs when it is loaded, so the rollup function
// is sliced out at its original line numbers and run against an in-memory
// SQLite database. The result is printed for DsstatsHourlyRollupTest to assert,
// and RequestCoverage remaps the hits to the real file.

namespace DsstatsHourlyRollupCoverageFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';

// the MySQL upsert is run by SQLite, which spells it ON CONFLICT ... DO UPDATE
function db_execute($sql) {
	$sql = preg_replace('/ON DUPLICATE KEY UPDATE.*$/s',
		'ON CONFLICT (local_data_id, rrd_name, cf) DO UPDATE SET average = excluded.average, peak = excluded.peak', $sql);

	return $GLOBALS['dsstats_rollup_pdo']->exec($sql) !== false;
}

cacti_fixture_source_function(dirname(__DIR__, 2) . '/poller_dsstats.php', 'dsstats_insert_hourly_data_into_cache', __NAMESPACE__);

$pdo = new \PDO('sqlite::memory:');
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE data_source_stats_hourly_cache (local_data_id INTEGER, rrd_name TEXT, time TEXT, value REAL,
	PRIMARY KEY (local_data_id, time, rrd_name))');
$pdo->exec('CREATE TABLE data_source_stats_hourly (local_data_id INTEGER, rrd_name TEXT, cf INTEGER DEFAULT 0,
	average REAL, peak REAL, PRIMARY KEY (local_data_id, rrd_name, cf))');

$GLOBALS['dsstats_rollup_pdo'] = $pdo;

// the previous rollup, then a window in which one source went unknown
$pdo->exec("INSERT INTO data_source_stats_hourly VALUES (1, 'traffic', 0, 50, 90), (2, 'traffic', 0, 5, 9)");
$pdo->exec("INSERT INTO data_source_stats_hourly_cache VALUES
	(1, 'traffic', '2026-01-01 10:00:00', NULL), (1, 'traffic', '2026-01-01 10:05:00', NULL),
	(2, 'traffic', '2026-01-01 10:00:00', 10), (2, 'traffic', '2026-01-01 10:05:00', NULL), (2, 'traffic', '2026-01-01 10:10:00', 30)");

dsstats_insert_hourly_data_into_cache();

print json_encode($pdo->query('SELECT local_data_id, average, peak FROM data_source_stats_hourly ORDER BY local_data_id')->fetchAll(\PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR) . "\n";
