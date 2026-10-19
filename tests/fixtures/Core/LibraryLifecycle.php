<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiLibraryLifecycleFixture;

/** Substitute time and process boundaries, retaining production launch and wait algorithms. */
function time(): int {
	return $GLOBALS['library_lifecycle_clock'];
}
function sleep(int $seconds): int {
	$GLOBALS['library_lifecycle_sleeps'][] = $seconds;
	$GLOBALS['library_lifecycle_clock'] += $seconds;

	if ($GLOBALS['library_lifecycle_clock'] > 100) {
		throw new \RuntimeException('Worker barrier exceeded its deadline');
	}

	return 0;
}
function dsstats_processes_running(string $type): int {
	return running($type);
}
function rrdcheck_processes_running(string $type): int {
	return running($type);
}
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
function exec_background(string $command, string $arguments): void {
	$GLOBALS['library_lifecycle_launches'][] = [$command, $arguments];
}
function cacti_log(string $message, bool $print = false, string $category = '', int $level = -1): void {
	$GLOBALS['library_lifecycle_logs'][] = [$message, $category];
}
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
