<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Shared fixture for the DSStats tests that run the real lib/dsstats.php.
 * The default db_* connection becomes an in-memory SQLite database with the
 * DSStats tables, through FakeMySQLPDO, and every statement is recorded so a
 * test can count the writes.  Call dsstats_test_restore() after each test:
 * a fake handle left in place answers every later read_config_option().
 */

require_once __DIR__ . '/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/dsstats.php';

if (!class_exists('DsstatsTestPDO')) {
	class DsstatsTestPDO extends FakeMySQLPDO {
		/** @var array<int, string> */
		public array $statements = [];

		public function prepare(string $query, array $options = []): PDOStatement|false {
			$this->statements[] = $query;

			return parent::prepare($query, $options);
		}

		public function exec(string $statement): int|false {
			$this->statements[] = $statement;

			return parent::exec($statement);
		}

		/**
		 * @return array<int, string> The recorded statements that start with $prefix
		 */
		public function statementsLike(string $prefix): array {
			return array_values(array_filter($this->statements, function ($sql) use ($prefix) {
				return stripos(ltrim($sql), $prefix) === 0;
			}));
		}
	}
}

if (!function_exists('dsstats_test_connect')) {
	/**
	 * Replace the default connection with the DSStats SQLite fixture.
	 *
	 * @param array<string, mixed> $options Config options read through read_config_option()
	 */
	function dsstats_test_connect(array $options = []) : DsstatsTestPDO {
		global $config, $database_sessions, $database_hostname, $database_port, $database_default;

		$GLOBALS['dsstats_test_saved'] = [
			$database_sessions, $database_hostname, $database_port, $database_default,
			$config[OPTIONS_CLI] ?? null,
		];

		$pdo = new DsstatsTestPDO();

		$pdo->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
		$pdo->exec('CREATE TABLE data_source_stats_command_cache (local_data_id INTEGER PRIMARY KEY, stats_command TEXT)');
		$pdo->exec('CREATE TABLE data_template_data (local_data_id INTEGER, name TEXT, name_cache TEXT, data_source_path TEXT, rrd_step INTEGER)');
		$pdo->exec('CREATE TABLE data_template_rrd (id INTEGER PRIMARY KEY, local_data_id INTEGER, data_source_name TEXT,
			data_source_type_id INTEGER, rrd_maximum TEXT, data_input_field_id INTEGER DEFAULT 0)');
		$pdo->exec('CREATE TABLE poller_item (local_data_id INTEGER, rrd_name TEXT)');
		$pdo->exec('CREATE TABLE data_local (id INTEGER PRIMARY KEY, host_id INTEGER)');
		$pdo->exec('CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY, task_item_id INTEGER)');
		$pdo->exec('CREATE TABLE data_input_fields (id INTEGER PRIMARY KEY, data_name TEXT)');
		$pdo->exec('CREATE TABLE data_source_stats_hourly_cache (local_data_id INTEGER, rrd_name TEXT, time TEXT, value REAL,
			PRIMARY KEY (local_data_id, time, rrd_name))');
		$pdo->exec('CREATE TABLE data_source_stats_hourly_last (local_data_id INTEGER, rrd_name TEXT, value REAL, calculated REAL,
			PRIMARY KEY (local_data_id, rrd_name))');

		foreach (['daily', 'weekly', 'monthly', 'yearly'] as $interval) {
			$pdo->exec("CREATE TABLE data_source_stats_$interval (local_data_id INTEGER, rrd_name TEXT, cf INTEGER DEFAULT 0,
				average REAL, peak REAL, p95n REAL, p90n REAL, p75n REAL, p50n REAL, p25n REAL, sum REAL, stddev REAL,
				lslslope REAL, lslint REAL, lslcorrel REAL, PRIMARY KEY (local_data_id, rrd_name, cf))");
		}

		$database_hostname = 'dsstats_test_host';
		$database_port     = '0';
		$database_default  = 'dsstats_test_db';

		$database_sessions["$database_hostname:$database_port:$database_default"] = $pdo;

		// read_config_option() answers from this cache before it asks the database
		$config[OPTIONS_CLI] = array_merge(['storage_location' => 0], $options);

		$pdo->statements = [];

		return $pdo;
	}

	function dsstats_test_restore() : void {
		global $config, $database_sessions, $database_hostname, $database_port, $database_default;

		if (!isset($GLOBALS['dsstats_test_saved'])) {
			return;
		}

		[$database_sessions, $database_hostname, $database_port, $database_default, $options] = $GLOBALS['dsstats_test_saved'];

		if ($options === null) {
			unset($config[OPTIONS_CLI]);
		} else {
			$config[OPTIONS_CLI] = $options;
		}

		unset($GLOBALS['dsstats_test_saved']);
	}

	/**
	 * An RRDtool process stand-in for dsstats_rrdtool_execute().  stdout is a
	 * real socket, so stream_select() works on it, that already holds $output.
	 * The commands written to stdin can be read back with dsstats_test_commands().
	 *
	 * @return array{0: null, 1: array{0: resource, 1: resource}}
	 */
	function dsstats_test_rrdtool(string $output) : array {
		$pair  = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
		$stdin = fopen('php://memory', 'w+');

		fwrite($pair[0], $output);
		fclose($pair[0]);

		stream_set_blocking($pair[1], false);

		return [null, [$stdin, $pair[1]]];
	}

	/**
	 * @param array{0: mixed, 1: array{0: resource, 1: resource}} $rrd_process
	 *
	 * @return array<int, string> The commands sent to the stand-in, without line endings
	 */
	function dsstats_test_commands(array $rrd_process) : array {
		rewind($rrd_process[1][0]);

		return array_values(array_filter(array_map('trim', explode("\n", stream_get_contents($rrd_process[1][0])))));
	}
}
