<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/lib/boost.php';

class BoostNativeHandoffPDO extends FakeMySQLPDO {
	public function prepare(string $query, array $options = []): PDOStatement|false {
		if (preg_match('/^SELECT (?:GET_LOCK|RELEASE_LOCK)\(/', $query)) {
			return parent::prepare('SELECT 1', $options);
		}

		$query = preg_replace('/CREATE TEMPORARY TABLE (`[^`]+`) LIKE poller_output_boost/', 'CREATE TEMP TABLE $1 AS SELECT * FROM poller_output_boost WHERE 0', $query);
		$query = str_replace(['INSERT IGNORE INTO', 'DROP TEMPORARY TABLE', 'FROM_UNIXTIME(?)'], ['INSERT OR IGNORE INTO', 'DROP TABLE', "datetime(?, 'unixepoch')"], $query);

		return parent::prepare($query, $options);
	}
}

test('Boost drains an empty archive without creating an RRD or leaving a temporary table', function () {
	$keys  = ['database_sessions', 'config', '_SESSION', 'boost_stats_log', 'archive_table', 'get_memory'];
	$saved = [];

	foreach ($keys as $key) {
		$saved[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
	}

	try {
		$db = new BoostNativeHandoffPDO();
		$db->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
		$db->exec('CREATE TABLE poller_time (start_time TEXT, end_time TEXT)');
		$db->exec('CREATE TABLE data_local (id INTEGER PRIMARY KEY, data_template_id INTEGER)');
		$db->exec('CREATE TABLE poller_output_boost (local_data_id INTEGER, time TEXT, rrd_name TEXT, output TEXT)');
		$db->exec('CREATE TABLE poller_output_boost_arch_1700000000 AS SELECT * FROM poller_output_boost');
		$key                            = $GLOBALS['database_hostname'] . ':' . $GLOBALS['database_port'] . ':' . $GLOBALS['database_default'];
		$GLOBALS['database_sessions']   = [$key => $db];
		$options                        = ['rrdtool_version' => 'rrd-1.8.x', 'storage_location' => '', 'client_timezone_support' => '', 'boost_rrd_update_max_records_per_select' => '1000'];
		$GLOBALS['config'][OPTIONS_CLI] = $options;
		$_SESSION[OPTIONS_WEB]          = $options;
		$GLOBALS['archive_table']       = '';
		$GLOBALS['get_memory']          = false;

		expect(boost_process_poller_output(1))->toBe(0);
		expect($db->query("SELECT name FROM sqlite_temp_master WHERE type = 'table'")->fetchAll())->toBe([]);
		expect($db->query('SELECT COUNT(*) FROM poller_output_boost_arch_1700000000')->fetchColumn())->toBe(0);
	} finally {
		foreach ($saved as $key => [$exists, $value]) {
			if ($exists) {
				$GLOBALS[$key] = $value;
			} else {
				unset($GLOBALS[$key]);
			}
		}
	}
});
