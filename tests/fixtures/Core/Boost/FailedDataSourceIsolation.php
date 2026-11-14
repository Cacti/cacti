<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

// Drives the bulk Boost drain and the parent's archive cleanup with one data
// source whose RRD update is always rejected. The production functions and the
// parent's cleanup block are compiled at their original line numbers into this
// namespace, so the stand-ins below replace only their database, RRDtool and
// logging boundaries. Prints one JSON verdict with every scenario's result.

namespace {
	require dirname(__DIR__, 3) . '/Helpers/FixtureCoverage.php';
	require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

	/**
	 * Stand-in for the error handler the drain installs by its global name.
	 *
	 * @return bool Always false, so PHP's own handling continues
	 */
	function boost_error_handler() : bool {
		return false;
	}
}

namespace CactiFixture\BoostIsolation {
	/**
	 * Reset the recorded boundary calls and choose the next scenario's failures.
	 *
	 * @param array<string, mixed> $scenario Rejected data sources and failing statements
	 *
	 * @return void
	 */
	function reset_boundary(array $scenario) : void {
		$GLOBALS['probe'] = $scenario + [
			'reject'       => [],
			'fail_prefix'  => '',
			'parked'       => 0,
			'expired'      => 0,
			'complete'     => true,
			'writes'       => [],
			'statements'   => [],
			'logs'         => []
		];
	}

	/**
	 * Return two data sources with three timestamps each, in drain order.
	 *
	 * @param string $sql The archive select
	 *
	 * @return array<int, array<string, int|string>> The archive rows
	 */
	function db_fetch_assoc(string $sql) : array {
		$rows = [];

		foreach ([1, 2] as $id) {
			for ($i = 0; $i < 3; $i++) {
				$rows[] = ['local_data_id' => $id, 'data_template_id' => 0, 'timestamp' => (string) (1700000000 + $i * 60), 'rrd_name' => 'in', 'output' => '42'];
			}
		}

		return $rows;
	}

	/**
	 * Record each update and reject the data sources the scenario names.
	 *
	 * @param int    $id     The data source being written
	 * @param string $path   The RRD path
	 * @param string $tmpl   The update template
	 * @param string $values The update values
	 * @param mixed  $pipe   The RRDtool pipe, unused
	 *
	 * @return string OK, or an RRDtool error for a rejected data source
	 */
	function boost_rrdtool_function_update(int $id, string $path, string $tmpl, string &$values, mixed $pipe = null) : string {
		$GLOBALS['probe']['writes'][] = $id . '@' . $values;

		return in_array($id, $GLOBALS['probe']['reject'], true) ? 'ERROR: rejected' : 'OK';
	}

	/**
	 * Record a write statement and fail the one the scenario names.
	 *
	 * @param string            $sql    The statement
	 * @param array<int, mixed> $params The bound parameters
	 *
	 * @return bool Whether the statement succeeded
	 */
	function db_execute_prepared(string $sql, array $params = []) : bool {
		$sql = (string) preg_replace('/\s+/', ' ', trim($sql));

		$GLOBALS['probe']['statements'][] = [$sql, $params];

		return $GLOBALS['probe']['fail_prefix'] === '' || !str_starts_with($sql, $GLOBALS['probe']['fail_prefix']);
	}

	/**
	 * Record an unprepared write statement.
	 *
	 * @param string $sql The statement
	 *
	 * @return bool Always true
	 */
	function db_execute(string $sql) : bool {
		return db_execute_prepared($sql);
	}

	/**
	 * Return the number of parked data sources, or false for a failed read.
	 *
	 * @param string $sql The count query
	 *
	 * @return int|false The parked data source count
	 */
	function db_fetch_cell(string $sql) : int|false {
		return $GLOBALS['probe']['parked'];
	}

	/**
	 * Return the number of expired samples, or false for a failed read.
	 *
	 * @param string            $sql    The count query
	 * @param array<int, mixed> $params The bound parameters
	 *
	 * @return int|false The expired sample count
	 */
	function db_fetch_cell_prepared(string $sql, array $params = []) : int|false {
		return $GLOBALS['probe']['expired'];
	}

	/**
	 * Record a log message.
	 *
	 * @param string $message The message
	 * @param mixed  ...$args The remaining cacti_log() arguments
	 *
	 * @return void
	 */
	function cacti_log(string $message, mixed ...$args) : void {
		$GLOBALS['probe']['logs'][] = $message;
	}

	/**
	 * Report whether every child recorded completion.
	 *
	 * @param string $run_id The run identifier
	 *
	 * @return int The completed child count
	 */
	function boost_completed_children(string $run_id) : int {
		return $GLOBALS['probe']['complete'] ? 2 : 1;
	}

	/** @return array<int, string> One archive table */
	function boost_get_arch_table_names(mixed ...$args) : array {
		return ['poller_output_boost_arch_1'];
	}

	/** @return bool Whether the table name is a Boost archive table */
	function boost_is_valid_archive_table(mixed $table) : bool {
		return is_string($table) && str_starts_with($table, 'poller_output_boost_arch_');
	}

	/** @return string A placeholder select */
	function boost_archive_select_sql(mixed ...$args) : string {
		return 'SELECT 1';
	}

	/** @return array<int, mixed> No field mappings */
	function db_fetch_assoc_prepared(mixed ...$args) : array {
		return [];
	}

	/** @return array<string, string> No unused data source names */
	function poller_get_unused_data_source_names(mixed ...$args) : array {
		return [];
	}

	/** @return array<string, string> The single field name */
	function poller_get_nt_rrd_field_names(mixed ...$args) : array {
		return ['in' => 'in'];
	}

	/** @return array{rrd_path: string, rrd_template: string} The RRD for any data source */
	function boost_get_rrd_filename_and_template(mixed ...$args) : array {
		return ['rrd_path' => 'source.rrd', 'rrd_template' => 'in'];
	}

	/** @return string A release above the 1.5 floor */
	function get_rrdtool_version() : string {
		return '1.8.0';
	}

	/** @return string The same release as installed */
	function get_installed_rrdtool_version() : string {
		return '1.8.0';
	}

	/**
	 * Compare RRDtool versions.
	 *
	 * @param string $version1 The first version
	 * @param string $version2 The second version
	 * @param string $operator The comparison operator
	 *
	 * @return bool The comparison result
	 */
	function cacti_version_compare(string $version1, string $version2, string $operator = '>') : bool {
		return version_compare($version1, $version2, $operator);
	}

	/** @return string A one byte update string length, so every timestamp flushes */
	function read_config_option(string $key) : string {
		return $key === 'boost_rrd_update_string_length' ? '1' : '100';
	}

	/** No-op stand-in for a Boost runtime function the drain calls. */
	function boost_timer(mixed ...$args) : void {
	}

	/** No-op stand-in for a Boost runtime function the drain calls. */
	function boost_debug(mixed ...$args) : void {
	}

	/** No-op stand-in for a hook the parent cleanup calls. */
	function dsstats_boost_bottom() : void {
	}

	/** No-op stand-in for a hook the parent cleanup calls. */
	function rrdcheck_boost_bottom() : void {
	}

	/** No-op stand-in for a hook the parent cleanup calls. */
	function api_plugin_hook(mixed ...$args) : void {
	}

	/**
	 * Run the parent's archive cleanup block from poller_boost.php.
	 *
	 * @param string $block Path of the compiled cleanup block
	 *
	 * @return array{dropped: array<int, string>, logs: array<int, string>} The tables dropped and messages logged
	 */
	function run_parent_cleanup(string $block) : array {
		$run_id                = 'run';
		$rrd_updates           = 6;
		$failed_children       = 0;
		$expected_children     = 2;
		$boost_run_arch_tables = ['poller_output_boost_arch_1'];

		require $block;

		$dropped = [];

		foreach ($GLOBALS['probe']['statements'] as [$sql]) {
			if (str_starts_with($sql, 'DROP TABLE')) {
				$dropped[] = $sql;
			}
		}

		return ['dropped' => $dropped, 'logs' => $GLOBALS['probe']['logs']];
	}

	/**
	 * Compile the parent's archive cleanup block at its original line numbers.
	 *
	 * @param string $file poller_boost.php
	 *
	 * @return string Path of the compiled block
	 */
	function compile_parent_cleanup(string $file) : string {
		$lines = explode("\n", (string) file_get_contents($file));
		$open  = "\t\t\tif (\$rrd_updates > 0 && \$failed_children === 0) {";
		$start = array_search($open, $lines, true);

		if ($start === false) {
			throw new \RuntimeException('Cannot locate the Boost archive cleanup block');
		}

		$end = $start + 1;

		while (isset($lines[$end]) && $lines[$end] !== "\t\t\t}") {
			$end++;
		}

		$path = (string) tempnam(sys_get_temp_dir(), 'cacti-boost-cleanup-');
		file_put_contents($path, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $start) . implode("\n", array_slice($lines, $start, $end - $start + 1)) . "\n");
		$GLOBALS['cacti_fixture_source_map'][$path] = realpath($file);

		return $path;
	}

	foreach (['BOOST_TIMER_START' => 0, 'BOOST_TIMER_END' => 1, 'POLLER_VERBOSITY_HIGH' => 4, 'SQL_NO_CACHE' => ''] as $name => $value) {
		define($name, $value);
	}

	// The drain require_once()s lib/rrd.php; point it at an empty include.
	define('CACTI_PATH_LIBRARY', dirname(__DIR__, 2) . '/../performance/fixtures');

	$root = dirname(__DIR__, 4);

	foreach (['cacti_sizeof', 'array_rekey'] as $name) {
		cacti_fixture_source_function($root . '/lib/functions.php', $name, __NAMESPACE__);
	}

	foreach (['boost_process_local_data_ids', 'boost_process_output', 'boost_defer_failed_data_sources', 'boost_carry_forward_failed_data_sources'] as $name) {
		cacti_fixture_source_function($root . '/poller_boost.php', $name, __NAMESPACE__);
	}

	$GLOBALS['archive_table'] = 'poller_output_boost_arch_1';
	$GLOBALS['get_memory']    = false;

	$verdict = [];

	foreach ([
		'clean'        => [],
		'isolate'      => ['reject' => [1]],
		'isolate_last' => ['reject' => [2]],
		'park_fails'   => ['reject' => [1], 'fail_prefix' => 'UPDATE']
	] as $name => $scenario) {
		reset_boundary($scenario);

		$verdict[$name] = [
			'result'     => boost_process_local_data_ids(2, 3, null),
			'writes'     => $GLOBALS['probe']['writes'],
			'statements' => $GLOBALS['probe']['statements'],
			'logs'       => $GLOBALS['probe']['logs']
		];
	}

	foreach ([
		'carry_none'         => ['parked' => 0],
		'carry_some'         => ['parked' => 2, 'expired' => 3],
		'carry_count_fails'  => ['parked' => false],
		'carry_expiry_fails' => ['parked' => 1, 'expired' => false],
		'carry_insert_fails' => ['parked' => 1, 'fail_prefix' => 'INSERT']
	] as $name => $scenario) {
		reset_boundary($scenario);

		$before = time();

		$verdict[$name] = [
			'result'     => boost_carry_forward_failed_data_sources(['poller_output_boost_arch_1', 'not_an_archive']),
			'before'     => $before,
			'statements' => $GLOBALS['probe']['statements'],
			'logs'       => $GLOBALS['probe']['logs']
		];
	}

	$block = compile_parent_cleanup($root . '/poller_boost.php');

	try {
		foreach ([
			'parent_drops'      => ['parked' => 1],
			'parent_keeps'      => ['parked' => 1, 'fail_prefix' => 'INSERT'],
			'parent_incomplete' => ['parked' => 1, 'complete' => false]
		] as $name => $scenario) {
			reset_boundary($scenario);

			$verdict[$name] = run_parent_cleanup($block);
		}
	} finally {
		unlink($block);
	}

	print json_encode($verdict, JSON_THROW_ON_ERROR);
}
