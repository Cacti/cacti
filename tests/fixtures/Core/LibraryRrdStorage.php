<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiLibraryRrdStorageFixture;

/** Adapt the remote filesystem boundary to owned local files and real RRDtool. */
function rrdtool_execute(string $command, bool $print, int $format, mixed $pipe = null, string $category = 'WEBLOG'): mixed {
	$GLOBALS['library_storage_commands'][] = $command;

	foreach (['file_exists', 'is_dir', 'mkdir'] as $operation) {
		if (str_starts_with($command, $operation . ' ')) {
			$path = trim(substr($command, strlen($operation) + 1), "'");
			\library_expect(str_starts_with($path . '/', $GLOBALS['library_storage_root'] . '/'), true, 'Remote filesystem command remains inside the owned fixture directory');

			switch ($operation) {
				case 'file_exists':
					return file_exists($path);
				case 'is_dir':
					return is_dir($path);
				default:
					return mkdir($path, 0775, true);
			}
		}
	}
	$prior                              = $GLOBALS['config']['local_storage'] ?? null;
	$GLOBALS['config']['local_storage'] = true;

	try {
		return \rrdtool_execute($command, $print, $format, $pipe, $category);
	} finally {
		if ($prior === null) {
			unset($GLOBALS['config']['local_storage']);
		} else {
			$GLOBALS['config']['local_storage'] = $prior;
		}
	}
}

/** Exercise remote RRD creation from source with the storage handoff adapted to owned files. */
function run(int $template): void {
	require_once CACTI_PATH_LIBRARY . '/boost.php';
	require_once CACTI_PATH_LIBRARY . '/dsstats.php';
	require_once __DIR__ . '/../../Helpers/SourceSlice.php';

	foreach (['functions.php' => ['get_data_source_path'], 'rrd.php' => ['rrdtool_function_create', 'rrdtool_file_exists', 'rrdtool_function_info'], 'boost.php' => ['boost_rrdtool_function_create', 'boost_rrdtool_function_update', 'boost_rrdtool_get_last_update_time'], 'dsstats.php' => ['dsstats_obtain_data_source_avgpeak_values', 'dsstats_get_stats_command']] as $file => $functions) {
		foreach ($functions as $function) {
			\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/' . $file, $function, __NAMESPACE__);
		}
	}
	$settings = [(string) \read_config_option('storage_location'), (string) \read_config_option('extended_paths')];
	\set_config_option('storage_location', '1');
	\set_config_option('extended_paths', 'on');

	try {
		foreach (['rrdtool_function_create', 'boost_rrdtool_function_create'] as $create) {
			$device    = \library_save_device(['description' => 'Remote storage boundary fixture', 'snmp_version' => 0]);
			$suggested = [];
			$created   = \create_complete_graph_from_template($template, $device, [], $suggested);
			$paths     = [];

			try {
				foreach ($created['local_data_id'] as $id) {
					$id = (int) $id;
					// Configure a new owned path explicitly: the process may have already
					// cached its default path layout before this fixture starts.
					$storedPath = '<path_rra>/coverage-storage-' . $device . '/' . $id . '.rrd';
					\library_expect(\db_execute_prepared('UPDATE data_template_data SET data_source_path = ? WHERE local_data_id = ?', [$storedPath, $id]), true, 'Owned data source receives its configured storage path');
					$path                            = get_data_source_path($id, true);
					$paths[]                         = $path;
					$GLOBALS['library_storage_root'] = dirname($path);
					\library_expect(str_starts_with(dirname($path), CACTI_PATH_RRA . '/') && !file_exists($path), true, 'Storage fixture owns a new structured RRD path');
					$GLOBALS['library_storage_commands'] = [];
					\library_expect((__NAMESPACE__ . '\\' . $create)($id, false, null) !== false, true, 'Remote storage command handoff creates a valid RRD');
					\library_expect(is_file($path), true, 'RRDtool creates the owned destination');
					$info = rrdtool_function_info($id);
					\library_expect(count($info['ds']) > 0, true, 'Storage fixture has configured data sources');
					$commands = implode("\n", $GLOBALS['library_storage_commands']);
					\library_expect(str_contains($commands, 'file_exists ') && str_contains($commands, 'is_dir ') && str_contains($commands, 'create '), true, 'Create handoff checks the remote file and directory before creation');
					$hash = hash_file('sha256', $path);
					\library_expect((__NAMESPACE__ . '\\' . $create)($id, false, null), -1, 'Existing remote RRD is never recreated');
					\library_expect(hash_file('sha256', $path), $hash, 'Existing samples remain byte-for-byte intact');
					$timestamp = time() + 600;
					$values    = $timestamp . ':' . implode(':', array_fill(0, count($info['ds']), '17'));
					\library_expect(boost_rrdtool_function_update($id, $path, '', $values), 'OK', 'Untemplated remote update reaches real RRDtool');
					\library_expect((int) boost_rrdtool_get_last_update_time($path, null), $timestamp, 'Remote last-update lookup observes the acknowledged sample');
					$stats = dsstats_get_stats_command($id, $path, true, 1, true, []);
					\library_expect(is_string($stats) && str_contains($stats, 'DEF:'), true, 'Remote statistics command is built from the real RRD metadata');
					$values = dsstats_obtain_data_source_avgpeak_values($id, $path, 'daily', 1, true, []);
					\library_expect(is_array($values) && count($values) > 0, true, 'Remote statistics executes against the owned RRD');

					if (!isset($GLOBALS['library_statistics_checked'])) {
						$GLOBALS['library_statistics_checked'] = true;
						\CactiLibraryRrdStatisticsFixture\run($id, $path, array_keys($info['ds']));
					}
				}
			} finally {
				// Cleanup uses the local file boundary after all remote handoff assertions.
				\set_config_option('storage_location', '');
				$ids = [(int) $created['local_graph_id']];
				\api_delete_graphs($ids, 2);
				\api_device_remove($device);

				foreach ($paths as $path) {
					if (is_file($path)) {
						unlink($path);
					}

					if (is_dir(dirname($path)) && count(scandir(dirname($path))) === 2) {
						rmdir(dirname($path));
					}
				}
				\set_config_option('storage_location', '1');
			}
		}
		\CactiLibraryRrdProxyFixture\run();
	} finally {
		\set_config_option('storage_location', $settings[0]);
		\set_config_option('extended_paths', $settings[1]);
		unset($GLOBALS['library_storage_commands'], $GLOBALS['library_storage_root'], $GLOBALS['library_statistics_checked']);
	}
}

/** A crashed persistent RRDtool process is relaunched and the restart is logged. */
function restart(): void {
	$binary = trim((string) \shell_exec('command -v rrdtool'));
	\library_expect($binary !== '', true, 'The fixture has RRDtool');
	$saved = \read_config_option('path_rrdtool');
	\set_config_option('path_rrdtool', $binary);

	try {
		$process = \__rrd_init(false);
		\library_expect(\rrdtool_is_process($process), true, 'RRDtool starts as a persistent process');
		// Terminate the process as a crash would, so the next command must relaunch it.
		\proc_terminate($process->process);
		$process->alive = false;
		$result         = [];
		$log            = \library_log_written_by(static function () use ($process, &$result): void {
			$result = \rrdtool_process_command($process, 'pwd');
		});
		\library_expect([$process->alive, $process->restarts, $result['success']], [true, 1, true], 'A crashed process is relaunched and the command still succeeds');
		\library_expect(str_contains($log, 'Detected RRDtool Crash, restarted the process (attempt 1 of ' . RRDTOOL_MAX_RESTARTS . ')'), true, 'The relaunch is logged with its attempt count');
		\rrd_close($process);
	} finally {
		\set_config_option('path_rrdtool', (string) $saved);
	}
}

namespace CactiLibraryRrdStatisticsFixture;

/** Route remote storage commands through the owned-file adapter. */
function rrdtool_execute(string $command, bool $print, int $format, mixed $pipe = null, string $category = 'WEBLOG'): mixed {
	return \CactiLibraryRrdStorageFixture\rrdtool_execute($command, $print, $format, $pipe, $category);
}
/** Commands go through the adapter, so no proxy connection is opened. */
function rrd_init(bool $output_to_term = true): mixed {
	return [];
}
/** No proxy connection was opened, so there is nothing to close. */
function rrd_close(mixed $pipe = null): void {
}
/** Limit the check to the owned RRD instead of every installed data source. */
function get_rrdfiles(int $thread_id = 1, int $max_threads = 1): array {
	return $GLOBALS['library_statistics_rrdfiles'];
}
/** Supply the owned RRD and a damaged data source row without a path. */
function get_rrdfile_names(int $thread_id = 1, int $max_threads = 1): array {
	return $GLOBALS['library_statistics_names'];
}
/** Remote statistics and checks read the owned RRD through the storage handoff. */
function run(int $id, string $path, array $names): void {
	global $type;

	// A private path cache resolves the owned path even if the process cached this id earlier.
	foreach (['functions.php' => ['get_data_source_path'], 'rrdcheck.php' => ['do_rrdcheck'], 'dsstats.php' => ['dsstats_get_and_store_ds_avgpeak_values', 'dsstats_obtain_data_source_avgpeak_values']] as $file => $functions) {
		require_once CACTI_PATH_LIBRARY . '/' . $file;

		foreach ($functions as $function) {
			\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/' . $file, $function, __NAMESPACE__);
		}
	}
	$row = \db_fetch_row_prepared('SELECT dtd.data_source_profile_id, dtd.rrd_step, dsp.heartbeat AS profile_heartbeat, dsp.step AS profile_step, MAX(dtr.rrd_heartbeat) AS rrd_heartbeat, dtd.name_cache
		FROM data_template_data AS dtd
		INNER JOIN data_template_rrd AS dtr ON dtr.local_data_id = dtd.local_data_id
		INNER JOIN data_source_profiles AS dsp ON dsp.id = dtd.data_source_profile_id
		WHERE dtd.local_data_id = ?
		GROUP BY dtd.local_data_id', [$id]);
	$GLOBALS['library_statistics_rrdfiles'] = [$row + ['local_data_id' => $id, 'data_source_path' => $path, 'data_source_names' => implode(',', $names)]];
	$GLOBALS['library_statistics_names']    = [['local_data_id' => $id, 'data_source_path' => '<path_rra>/' . basename($path), 'dsses' => count($names), 'dsnames' => implode(',', $names)],
		['local_data_id' => $id, 'data_source_path' => '', 'dsses' => 0, 'dsnames' => '']];
	$priorType                           = $type ?? null;
	$type                                = '';
	$GLOBALS['library_storage_commands'] = [];

	try {
		\db_execute_prepared('DELETE FROM rrdcheck WHERE local_data_id = ?', [$id]);
		do_rrdcheck(1);
		$commands = implode("\n", $GLOBALS['library_storage_commands']);
		\library_expect(str_contains($commands, 'file_exists ' . \cacti_escapeshellarg($path)), true, 'The remote check asks the storage handoff whether the RRD exists');
		\library_expect(str_contains($commands, 'fetch ' . \cacti_escapeshellarg($path) . ' LAST -s '), true, 'The remote check reads recent samples through the storage handoff');
		\library_expect((int) \db_fetch_cell_prepared("SELECT COUNT(*) FROM rrdcheck WHERE local_data_id = ? AND message LIKE 'RRDfile does not exist%'", [$id]), 0, 'The remote check finds the owned RRD');
		$log = \library_captured_log(static function (): void {
			dsstats_get_and_store_ds_avgpeak_values('daily', '', 1);
		});
		\library_expect(str_contains($log, "WARNING: Data Source '" . $row['name_cache'] . "' is damaged and contains no path."), true, 'A data source without a path is reported by name');
		\library_expect((int) \db_fetch_cell_prepared('SELECT COUNT(*) FROM data_source_stats_daily WHERE local_data_id = ?', [$id]) > 0, true, 'Remote daily statistics are stored for the owned RRD');
		$empty = [$id => []];
		$log   = \library_captured_log(static function () use (&$empty): void {
			\dsstats_write_buffer($empty, 'daily', 1);
		});
		\library_expect(str_contains($log, 'WARNING: Problem with Data Source for Device[') && str_contains($log, 'DS[' . $id . '], Interval[Daily]'), true, 'A data source without statistics is reported');
	} finally {
		$type = $priorType;
		\db_execute_prepared('DELETE FROM rrdcheck WHERE local_data_id = ?', [$id]);
		\db_execute_prepared('DELETE FROM data_source_stats_daily WHERE local_data_id = ?', [$id]);
		unset($GLOBALS['library_statistics_rrdfiles'], $GLOBALS['library_statistics_names']);
	}
}

namespace CactiLibraryRrdProxyFixture;

/** Fail the socket allocations the case selects and allocate real sockets otherwise. */
function socket_create(int $domain, int $type, int $protocol): mixed {
	$attempt = ++$GLOBALS['library_proxy_sockets'];

	return in_array($attempt, $GLOBALS['library_proxy_failed_sockets'], true) ? false : \socket_create(AF_INET, $type, $protocol);
}
/** Report whether the selected proxy server accepts the connection. */
function rrdtool_proxy_connect(mixed $socket, string $server, int $port): bool {
	return $GLOBALS['library_proxy_connects'];
}
/** Report that the proxy closed the connection before the key exchange. */
function rrdtool_proxy_write(mixed $socket, string $data): bool {
	return false;
}
/** Record a proxy diagnostic instead of writing to the Cacti log. */
function cacti_log(string $message, bool $output = false, string $environ = 'CMDPHP', mixed $level = ''): bool {
	$GLOBALS['library_proxy_logs'][] = $message;

	return true;
}
/** The proxy client gives up and reports each socket and key exchange failure. */
function run(): void {
	\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/rrd.php', '__rrd_proxy_init', __NAMESPACE__);
	$keys  = ['rrdp_load_balancing', 'rrdp_server', 'rrdp_server_backup', 'rrdp_port', 'rrdp_port_backup'];
	$saved = [];

	foreach ($keys as $key) {
		$saved[$key] = \read_config_option($key);
	}
	\set_config_option('rrdp_server', '127.0.0.1');
	\set_config_option('rrdp_server_backup', '127.0.0.2');
	\set_config_option('rrdp_port', '40301');
	\set_config_option('rrdp_port_backup', '40302');

	try {
		foreach ([['', [1], true, 'CACTI2RRDP ERROR: Unable to create socket to connect to RRDtool Proxy Server'],
			['on', [2], false, 'CACTI2RRDP ERROR: Unable to create socket to connect to RRDtool Proxy Server #'],
			['', [], true, 'CACTI2RRDP ERROR: Public RSA Key Exchange - unable to send local key.']] as [$balancing, $failed, $connects, $message]) {
			\set_config_option('rrdp_load_balancing', $balancing);
			$GLOBALS['library_proxy_sockets']        = 0;
			$GLOBALS['library_proxy_failed_sockets'] = $failed;
			$GLOBALS['library_proxy_connects']       = $connects;
			$GLOBALS['library_proxy_logs']           = [];
			\library_expect(__rrd_proxy_init('POLLER'), false, 'The proxy client refuses to continue: ' . $message);
			\library_expect(str_starts_with((string) end($GLOBALS['library_proxy_logs']), $message), true, 'The proxy failure is reported: ' . implode(' | ', $GLOBALS['library_proxy_logs']));
		}
	} finally {
		foreach ($saved as $key => $value) {
			\set_config_option($key, $value);
		}
		unset($GLOBALS['library_proxy_sockets'], $GLOBALS['library_proxy_failed_sockets'], $GLOBALS['library_proxy_connects'], $GLOBALS['library_proxy_logs']);
	}
}
