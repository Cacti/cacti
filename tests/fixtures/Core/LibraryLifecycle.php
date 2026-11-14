<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiLibraryLifecycleFixture;

/** Substitute time and process boundaries, retaining production launch and wait algorithms. */
function time(): int {
	return $GLOBALS['library_lifecycle_clock'];
}
/** Advance the fixture clock instead of sleeping and fail when the barrier exceeds its deadline. */
function sleep(int $seconds): int {
	$GLOBALS['library_lifecycle_sleeps'][] = $seconds;
	$GLOBALS['library_lifecycle_clock'] += $seconds;

	if ($GLOBALS['library_lifecycle_clock'] > 100) {
		throw new \RuntimeException('Worker barrier exceeded its deadline');
	}

	return 0;
}
/** Report the scheduled dsstats worker count for the fixture clock. */
function dsstats_processes_running(string $type): int {
	return running($type);
}
/** Report the scheduled rrdcheck worker count for the fixture clock. */
function rrdcheck_processes_running(string $type): int {
	return running($type);
}
/** Return the number of workers registered at or before the fixture clock. */
function running(string $type): int {
	\library_expect($type, 'bmaster', 'Barrier observes the requested worker family');
	$count = 0;

	foreach ($GLOBALS['library_lifecycle_schedule'] as $at => $registered) {
		if ($at <= time()) {
			$count = $registered;
		}
	}

	return $count;
}
/** Record a background launch instead of starting a process. */
function exec_background(string $command, string $arguments): void {
	$GLOBALS['library_lifecycle_launches'][] = [$command, $arguments];
}
/** Record a log message and category instead of writing to the Cacti log. */
function cacti_log(string $message, bool $print = false, string $category = '', int $level = -1): void {
	$GLOBALS['library_lifecycle_logs'][] = [$message, $category];
}
/** Exercise the dsstats and rrdcheck launch and wait loops against the substituted clock. */
function run(): void {
	global $debug;
	require_once __DIR__ . '/../../Helpers/SourceSlice.php';
	$priorDebug = $debug ?? null;
	$saved      = [];

	try {
		foreach (['dsstats', 'rrdcheck'] as $system) {
			require_once CACTI_PATH_LIBRARY . '/' . $system . '.php';

			foreach (['launch_children', 'wait_for_children', 'poller_bottom'] as $operation) {
				\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/' . $system . '.php', $system . '_' . $operation, __NAMESPACE__);
			}

			foreach ([$system . '_parallel', $system . '_enable', 'path_' . $system . '_log'] as $key) {
				$saved[$key] = \db_fetch_row_prepared('SELECT * FROM settings WHERE name = ?', [$key]);
			}

			foreach ([0, 3] as $parallel) {
				$debug = $parallel === 3;
				\set_config_option($system . '_parallel', (string) $parallel);
				$GLOBALS['library_lifecycle_clock']    = 0;
				$GLOBALS['library_lifecycle_launches'] = [];
				ob_start();

				try {
					$launched = (__NAMESPACE__ . '\\' . $system . '_launch_children')('bmaster');
				} finally {
					ob_end_clean();
				}
				\library_expect($launched, $parallel ?: 1, 'Launch count matches configured workers including the default');
				$expected = [];

				for ($i = 1; $i <= $launched; $i++) {
					$expected[] = [\read_config_option('path_php_binary'), CACTI_PATH_BASE . '/poller_' . $system . '.php --type=bchild --child=' . $i . ($debug ? ' --debug' : '')];
				}
				\library_expect($GLOBALS['library_lifecycle_launches'], $expected, 'Every worker receives its own identity and debug setting');
			}
			$debug = false;

			foreach ([[[0 => 0, 1 => 1, 2 => 2, 3 => 1, 5 => 0], 6, false], [[0 => 1, 31 => 0], 32, true]] as [$schedule, $elapsed, $warn]) {
				$GLOBALS['library_lifecycle_clock']    = 0;
				$GLOBALS['library_lifecycle_sleeps']   = [];
				$GLOBALS['library_lifecycle_logs']     = [];
				$GLOBALS['library_lifecycle_schedule'] = $schedule;
				(__NAMESPACE__ . '\\' . $system . '_wait_for_children')('bmaster', 2);
				\library_expect(time(), $elapsed, 'Barrier waits for registration and then drains surviving workers');
				\library_expect(count($GLOBALS['library_lifecycle_logs']), $warn ? 1 : 0, 'Only incomplete worker startup logs a timeout');

				if ($warn) {
					\library_expect(str_contains($GLOBALS['library_lifecycle_logs'][0][0], '1 of 2 bmaster children'), true, 'Timeout reports actual and expected registration counts');
				}
			}
			$GLOBALS['library_lifecycle_clock']  = 0;
			$GLOBALS['library_lifecycle_sleeps'] = [];
			(__NAMESPACE__ . '\\' . $system . '_wait_for_children')('bmaster', 0);
			\library_expect($GLOBALS['library_lifecycle_sleeps'], [], 'No launched workers require no barrier wait');
			\set_config_option($system . '_enable', 'on');

			foreach (['', sys_get_temp_dir() . "/fixture log '; literal"] as $log) {
				\set_config_option('path_' . $system . '_log', $log);
				$GLOBALS['library_lifecycle_launches'] = [];
				(__NAMESPACE__ . '\\' . $system . '_poller_bottom')();
				$arguments = '-q ' . CACTI_PATH_BASE . '/poller_' . $system . '.php' . ($log === '' ? '' : ' >> ' . \cacti_escapeshellarg($log) . ' 2>&1');
				\library_expect($GLOBALS['library_lifecycle_launches'], [[\read_config_option('path_php_binary'), $arguments]], 'Scheduled worker quotes the configured log destination');
			}
			\set_config_option($system . '_enable', '');
			$GLOBALS['library_lifecycle_launches'] = [];
			(__NAMESPACE__ . '\\' . $system . '_poller_bottom')();
			\library_expect($GLOBALS['library_lifecycle_launches'], [], 'Disabled statistics do not start a worker');
		}
		\CactiLibraryWindowsFixture\run();
		\CactiLibraryRrdtool18Fixture\load();
	} finally {
		foreach ($saved as $key => $row) {
			\db_execute_prepared('DELETE FROM settings WHERE name = ?', [$key]);

			if ($row) {
				\set_config_option($key, $row['value']);
			} else {
				unset($GLOBALS['config'][OPTIONS_CLI][$key]);
			}
		}
		$debug = $priorDebug;

		foreach (['clock', 'sleeps', 'launches', 'schedule', 'logs'] as $key) {
			unset($GLOBALS['library_lifecycle_' . $key]);
		}
	}
}

namespace CactiLibraryWindowsFixture;

// The unchanged scheduler takes its Windows branch here; global code keeps the host OS.
\define(__NAMESPACE__ . '\CACTI_SERVER_OS', 'win32');

/** Record a background launch instead of starting a process. */
function exec_background(string $command, string $arguments): void {
	$GLOBALS['library_windows_launches'][] = [$command, $arguments];
}
/** On Windows the scheduled statistics worker appends to its log without a shell stream redirect. */
function run(): void {
	\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/dsstats.php', 'dsstats_poller_bottom', __NAMESPACE__);
	$saved = [];

	foreach (['dsstats_enable', 'path_dsstats_log'] as $key) {
		$saved[$key] = \read_config_option($key);
	}
	$log = sys_get_temp_dir() . '/windows statistics.log';

	try {
		\set_config_option('dsstats_enable', 'on');
		\set_config_option('path_dsstats_log', $log);
		$GLOBALS['library_windows_launches'] = [];
		dsstats_poller_bottom();
		\library_expect($GLOBALS['library_windows_launches'], [[\read_config_option('path_php_binary'), '-q ' . CACTI_PATH_BASE . '/poller_dsstats.php >> ' . \cacti_escapeshellarg($log)]], 'Windows statistics worker appends to its log without redirecting stderr');
	} finally {
		foreach ($saved as $key => $value) {
			\set_config_option($key, $value);
		}
		unset($GLOBALS['library_windows_launches']);
	}
}

namespace CactiLibraryRrdtool18Fixture;

/** Report RRDtool 1.8 so the unchanged graph compiler emits its 1.8-only options. */
function get_rrdtool_version(): string {
	return '1.8.0';
}
/** Load the unchanged graph compiler into this namespace. */
function load(): void {
	\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/rrd.php', 'rrdtool_function_graph', __NAMESPACE__);
}
/** Return the RRDtool 1.8 command for a graph without executing it. */
function graph_source(int $graph, array $options): string {
	ob_start();

	try {
		rrdtool_function_graph($graph, '', $options + ['print_source' => true]);

		return html_entity_decode((string) ob_get_contents(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	} finally {
		ob_end_clean();
	}
}

namespace CactiLibraryResourceFixture;

/** A plugin directory without an INFO file is reported and never cached for remote collectors. */
function run(): void {
	$root = sys_get_temp_dir() . '/cacti-resource-' . bin2hex(random_bytes(6));
	\library_expect(mkdir($root . '/plugins/coverage_noinfo', 0700, true), true, 'Resource fixture owns an installation tree');

	// The unchanged scanner reads this tree instead of the installation under test.
	foreach (['CACTI_PATH_BASE' => $root, 'CACTI_PATH_SCRIPTS' => $root . '/scripts', 'CACTI_PATH_RESOURCE' => $root . '/resource'] as $name => $value) {
		\define(__NAMESPACE__ . '\\' . $name, $value);
	}
	\cacti_fixture_source_function(\CACTI_PATH_LIBRARY . '/poller.php', 'update_resource_cache', __NAMESPACE__);
	$backup = 'coverage_resource_cache_' . bin2hex(random_bytes(4));
	\library_expect(\db_execute('CREATE TEMPORARY TABLE `' . $backup . '` SELECT * FROM poller_resource_cache'), true, 'Resource fixture preserves the existing cache');
	$settings = \db_fetch_assoc("SELECT * FROM settings WHERE name LIKE 'md5dirsum%'");

	try {
		\library_expect((int) \db_fetch_cell("SELECT COUNT(*) FROM poller WHERE disabled = ''") > 1, true, 'A remote collector consumes the resource cache');
		$log = \library_captured_log(static function (): void {
			update_resource_cache(1);
		});
		\library_expect(str_contains($log, "WARNING: INFO file does not exist for plugin directory '" . $root . "/plugins/coverage_noinfo'"), true, 'A plugin without INFO is reported');
		\library_expect((int) \db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_resource_cache WHERE `path` LIKE ?', ['%coverage_noinfo%']), 0, 'A plugin without INFO is never cached');
	} finally {
		\db_execute('DELETE FROM poller_resource_cache');
		\db_execute('INSERT INTO poller_resource_cache SELECT * FROM `' . $backup . '`');
		\db_execute('DROP TEMPORARY TABLE `' . $backup . '`');
		\db_execute("DELETE FROM settings WHERE name LIKE 'md5dirsum%'");

		foreach ($settings as $row) {
			\sql_save($row, 'settings', 'name', false);
		}
		rmdir($root . '/plugins/coverage_noinfo');
		rmdir($root . '/plugins');
		rmdir($root);
	}
}

namespace CactiLibraryBoostDuplicateFixture;

/** Return the staged samples with each row repeated, as a storage engine without the key would. */
function db_fetch_assoc_prepared(string $sql, array $params = [], bool $log = true, mixed $db_conn = false): array|false {
	$rows = \db_fetch_assoc_prepared($sql, $params, $log, $db_conn);

	if (!is_array($rows) || !str_contains($sql, 'FROM poller_output_boost AS po')) {
		return $rows;
	}
	$repeated = [];

	foreach ($rows as $row) {
		array_push($repeated, $row, $row);
	}

	return $repeated;
}
/** Record a Boost diagnostic instead of writing to the Cacti log. */
function cacti_log(mixed $string, bool $output = false, string $environ = 'CMDPHP', mixed $level = ''): bool {
	$GLOBALS['library_boost_duplicate_logs'][] = $string;

	return true;
}
/** A repeated sample is stored once and reported. */
function run(int $id, array $names, int $time): void {
	\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/boost.php', 'boost_process_poller_output', __NAMESPACE__);
	\library_boost_stage($id, $names, [$time], '43');
	$GLOBALS['library_boost_duplicate_logs'] = [];

	try {
		\library_expect(boost_process_poller_output($id), 2 * count($names), 'Every repeated row is read: ' . implode(' | ', $GLOBALS['library_boost_duplicate_logs']));
		\library_expect(count(preg_grep('/^WARNING: Skipping ' . $id . ':\S+ due to duplicate record\.\.\.$/', $GLOBALS['library_boost_duplicate_logs'])), count($names), 'Each repeated sample is reported once');
	} finally {
		unset($GLOBALS['library_boost_duplicate_logs']);
	}
}
