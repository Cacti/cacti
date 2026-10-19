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

			return match ($operation) {
				'file_exists' => file_exists($path),
				'is_dir'      => is_dir($path),
				'mkdir'       => mkdir($path, 0775, true),
			};
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
	} finally {
		\set_config_option('storage_location', $settings[0]);
		\set_config_option('extended_paths', $settings[1]);
		unset($GLOBALS['library_storage_commands'], $GLOBALS['library_storage_root']);
	}
}
