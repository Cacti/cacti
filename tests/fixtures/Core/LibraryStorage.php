<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Exercise Boost RRD creation and storage for a data template on an owned device. */
function library_storage(int $template): void {
	global $archive_table, $get_memory, $memory_used;
	require_once CACTI_PATH_LIBRARY . '/boost.php';
	$archive_table = '';
	$get_memory    = false;
	$memory_used   = 0;
	$device        = library_save_device(['description' => 'Boost storage fixture', 'snmp_version' => 0, 'disabled' => '']);
	library_expect($device > 0, true, 'Boost fixture owns its device and data sources');
	$suggested     = [];
	$created       = create_complete_graph_from_template($template, $device, [], $suggested);
	library_expect(is_array($created) && $created['local_graph_id'] > 0, true, 'Boost fixture graph exists');
	$paths      = [];
	$pollerTime = 0;
	set_config_option('boost_rrd_update_string_length', '8192');

	try {
		foreach ($created['local_data_id'] as $id) {
			$id   = (int) $id;
			$path = get_data_source_path($id, true);
			update_poller_cache($id, true);
			library_expect(boost_get_rrd_filename_and_template($id)['rrd_path'], $path, 'Poller metadata directs Boost to the owned RRD');
			library_expect(str_starts_with($path, CACTI_PATH_RRA . '/') && !file_exists($path), true, 'Boost owns a new disposable RRD path');
			$paths[] = $path;
			$source  = boost_rrdtool_function_create($id, true, null);
			library_expect(is_string($source) && str_contains($source, 'RRA:AVERAGE'), true, 'Boost create preview includes consolidation archives');
			library_expect(boost_rrdtool_function_create($id, false, null) !== false, true, 'Boost creates the actual RRD');
			library_expect(is_file($path), true, 'Boost creation produces a file');
			$info  = rrdtool_function_info($id);
			$step  = (int) $info['step'];
			$start = (intdiv(max(time(), (int) $info['last_update']), $step) + 1) * $step;
			$end   = $start + 4 * $step;
			library_expect(boost_rrdtool_function_create($id, false, null), -1, 'Boost never recreates an existing RRD');
			$pollerTime = (int) sql_save(['id' => 0, 'pid' => getmypid(), 'poller_id' => 1, 'start_time' => date('Y-m-d H:i:s', $end + $step)], 'poller_time');
			library_expect($pollerTime > 0, true, 'Fixture supplies a deterministic poll-cycle boundary');
			$tuples = [];

			foreach (range(0, 3) as $offset) {
				foreach (array_keys($info['ds']) as $name) {
					$tuples[] = '(' . $id . ',' . db_qstr($name) . ',FROM_UNIXTIME(' . ($start + $offset * $step) . '),' . db_qstr('17') . ')';
				}
			}
			library_expect(boost_flush_output_batch($tuples), true, 'Boost stages every sample');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_output_boost WHERE local_data_id = ?', [$id]), count($tuples), 'Staging persists every exact timestamp and field');
			library_expect(boost_process_poller_output($id), count($tuples), 'Boost drains staged samples through RRDtool');
			library_expect((int) boost_rrdtool_get_last_update_time($path, null), $end - $step, 'RRDtool acknowledges the last staged sample');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_output_boost WHERE local_data_id = ?', [$id]), 0, 'Acknowledged samples leave the queue');
			$fetched = rrdtool_function_fetch($id, $start, $end, $step, true);

			foreach ($fetched['values'] as $samples) {
				library_expect((float) $samples[$start + 2 * $step], 17.0, 'Stored Boost samples reach the graph fetch boundary');
			}
			$backup = $path . '.fixture-backup';
			library_expect(!file_exists($backup) && rename($path, $backup), true, 'Fixture retains its valid RRD before failure injection');

			try {
				library_expect(file_put_contents($path, 'invalid fixture RRD'), 19, 'Fixture installs a malformed destination');
				$retry = [];

				foreach (array_keys($info['ds']) as $name) {
					$retry[] = '(' . $id . ',' . db_qstr($name) . ',FROM_UNIXTIME(' . $end . '),' . db_qstr('19') . ')';
				}
				library_expect(boost_flush_output_batch($retry), true, 'Retry sample reaches durable staging');
				library_expect(boost_process_poller_output($id), -1, 'Malformed RRD cannot report successful storage');
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_output_boost WHERE local_data_id = ?', [$id]), count($retry), 'Failed RRD update preserves staged samples');
			} finally {
				unlink($path);
				rename($backup, $path);
			}
			library_expect(boost_process_poller_output($id), count($retry), 'Restored RRD accepts the retained samples');
			library_expect((int) boost_rrdtool_get_last_update_time($path, null), $end, 'Retry advances the real RRD');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_output_boost WHERE local_data_id = ?', [$id]), 0, 'Successful retry clears staging');
			library_expect(boost_process_poller_output($id), 0, 'An empty retry is idempotent');
			library_boost_recovery($id, $path, array_keys($info['ds']), $end, $step);
			library_storage_boundaries($id, $path);
			library_dsdebug_permissions($id, $path);
			library_rrd_diagnostics($id);
			library_dsstats($id);
			library_poller_batches($id, $path, array_keys($info['ds']), $end + 10 * $step, $step);
			db_execute_prepared('DELETE FROM poller_time WHERE id = ?', [$pollerTime]);
			$pollerTime = 0;
		}
	} finally {
		if ($pollerTime > 0) {
			db_execute_prepared('DELETE FROM poller_time WHERE id = ?', [$pollerTime]);
		}
		$graphs = [(int) $created['local_graph_id']];
		api_delete_graphs($graphs, 2);
		api_device_remove($device);

		foreach ($paths as $path) {
			if (is_file($path)) {
				unlink($path);
			}
		}
	}
}

/** Run an action with the Cacti log redirected to a private file and return what it logged. */
function library_captured_log(callable $action): string {
	$log      = tempnam(sys_get_temp_dir(), 'cacti-log-');
	$previous = read_config_option('path_cactilog');
	set_config_option('path_cactilog', $log);

	try {
		$action();

		return (string) file_get_contents($log);
	} finally {
		set_config_option('path_cactilog', $previous);
		unlink($log);
	}
}

/** Stage one Boost sample per series and timestamp for a fixture data source. */
function library_boost_stage(int $id, array $names, array $times, string $value): void {
	$tuples = [];

	foreach ($times as $time) {
		foreach ($names as $name) {
			$tuples[] = '(' . $id . ',' . db_qstr($name) . ',FROM_UNIXTIME(' . $time . '),' . db_qstr($value) . ')';
		}
	}
	library_expect(boost_flush_output_batch($tuples), true, 'Boost stages the recovery samples');
}

/** Boost keeps staged samples when an update, a forwarded archive row or a sample format is rejected. */
function library_boost_recovery(int $id, string $path, array $names, int $last, int $step): void {
	$archive   = 'poller_output_boost_arch_' . random_int(100000000, 999999999);
	$future    = $last + 2 * $step;
	$processed = null;
	library_expect(db_execute('CREATE TABLE `' . $archive . '` LIKE poller_output_boost'), true, 'Boost fixture owns an archive table');

	try {
		foreach ($names as $name) {
			foreach ([[$last + 3, '29'], [$future, '31']] as [$time, $value]) {
				db_execute_prepared('INSERT INTO `' . $archive . '` (local_data_id, rrd_name, time, output) VALUES (?, ?, FROM_UNIXTIME(?), ?)', [$id, $name, $time, $value]);
			}
		}
		// A collector already staged the open-cycle sample, so the archive copy must not replace it.
		library_boost_stage($id, $names, [$future], '37');
		$log = library_captured_log(static function () use ($id, &$processed): void {
			$processed = boost_process_poller_output($id);
		});
		library_expect($processed, count($names), 'Boost stores the archived sample that precedes the poll boundary');
		library_expect((int) boost_rrdtool_get_last_update_time($path, null), $last + 3, 'The archived sample reaches the RRD');
		library_expect(str_contains($log, 'ignored ' . count($names) . ' of ' . count($names) . ' duplicate sample keys for Local Data ID ' . $id), true, 'Archive forwarding reports the colliding open-cycle samples');
		library_expect(db_fetch_assoc_prepared('SELECT DISTINCT output FROM poller_output_boost WHERE local_data_id = ?', [$id]), [['output' => '37']], 'The first staged open-cycle sample is kept');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM `' . $archive . '` WHERE local_data_id = ?', [$id]), 0, 'Forwarded archive rows are released');
	} finally {
		db_execute('DROP TABLE IF EXISTS `' . $archive . '`');
		db_execute_prepared('DELETE FROM poller_output_boost WHERE local_data_id = ?', [$id]);
	}
	$length = read_config_option('boost_rrd_update_string_length');
	$backup = $path . '.recovery-backup';
	library_expect(rename($path, $backup) && file_put_contents($path, 'invalid fixture RRD') === 19, true, 'Fixture installs a malformed destination for a multi-sample replay');

	try {
		// A one-byte limit flushes every completed timestamp before reading the next.
		set_config_option('boost_rrd_update_string_length', '1');
		library_boost_stage($id, $names, [$last + 4, $last + 5], '41');
		$log = library_captured_log(static function () use ($id, &$processed): void {
			$processed = boost_process_poller_output($id);
		});
		library_expect($processed, -1, 'A rejected intermediate update fails the replay');
		library_expect(str_contains($log, 'RRD Update Warning'), true, 'The rejected intermediate update is reported');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_output_boost WHERE local_data_id = ?', [$id]), 2 * count($names), 'Every sample of the failed replay stays staged');
	} finally {
		unlink($path);
		rename($backup, $path);
	}

	try {
		library_expect(boost_process_poller_output($id), 2 * count($names), 'The restored RRD accepts the retained replay one timestamp at a time');
		library_expect((int) boost_rrdtool_get_last_update_time($path, null), $last + 5, 'The replay advances the RRD to its final sample');
	} finally {
		set_config_option('boost_rrd_update_string_length', $length);
	}

	try {
		library_boost_stage($id, $names, [$last + 6], 'unparsable');
		$log = library_captured_log(static function () use ($id): void {
			boost_process_poller_output($id);
		});
		library_expect(str_contains($log, 'Invalid output! MULTI DS[' . $id . '] Encountered [unparsable]'), true, 'A sample that is neither a number nor a field list is reported');
		library_expect((int) boost_rrdtool_get_last_update_time($path, null), $last + 5, 'An unparsable sample is never stored as a reading');
	} finally {
		db_execute_prepared('DELETE FROM poller_output_boost WHERE local_data_id = ?', [$id]);
	}
	\CactiLibraryBoostDuplicateFixture\run($id, $names, $last + 7);
	library_expect((int) boost_rrdtool_get_last_update_time($path, null), $last + 7, 'A repeated sample is stored once');
}

/** Creation, tuning and repair refuse to act on an RRD they cannot trust. */
function library_storage_boundaries(int $id, string $path): void {
	require_once CACTI_PATH_LIBRARY . '/dsdebug.php';
	$info = rrdtool_function_info($id);
	$log  = library_captured_log(static function () use ($id): void {
		rrdtool_function_tune(['data_source_id' => $id, 'heartbeat' => 'never', 'minimum' => '', 'maximum' => '', 'data-source-type' => 1, 'data-source-rename' => '']);
	});
	library_expect(str_contains($log, 'ERROR: Unable to tune '), true, 'A rejected tune is reported');
	library_expect(rrdtool_function_info($id)['ds'], $info['ds'], 'A rejected tune leaves every series definition intact');

	// The configured path still names a file inside the RRA directory, but the file is a link out of it.
	$outside = tempnam(sys_get_temp_dir(), 'cacti-escape-');
	$backup  = $path . '.escape-backup';
	library_expect(copy($path, $outside) && rename($path, $backup) && symlink($outside, $path), true, 'Fixture replaces its RRD with a link leaving the RRA directory');

	try {
		$escaped = hash_file('sha256', $outside);
		library_expect(rrdtool_function_create($id, false), false, 'RRD creation refuses a path that resolves outside the RRA directory');
		library_expect(boost_rrdtool_function_create($id, false, null), false, 'Boost creation refuses a path that resolves outside the RRA directory');
		library_expect(hash_file('sha256', $outside), $escaped, 'The link target is never rewritten');
	} finally {
		unlink($path);
		rename($backup, $path);
		unlink($outside);
	}
	$heartbeats = db_fetch_assoc_prepared('SELECT id, rrd_heartbeat FROM data_template_rrd WHERE local_data_id = ?', [$id]);
	library_expect(rename($path, $backup), true, 'Fixture removes its RRD before a rejected creation');

	try {
		// RRDtool requires a positive heartbeat and Cacti passes the stored value through.
		db_execute_prepared('UPDATE data_template_rrd SET rrd_heartbeat = 0 WHERE local_data_id = ?', [$id]);
		$log = library_captured_log(static function () use ($id): void {
			rrdtool_function_create($id, false);
		});
		library_expect(file_exists($path), false, 'A definition RRDtool rejects creates no file');
		library_expect(str_contains($log, 'WARNING: RRDCreate failed for '), true, 'A rejected creation is reported');
	} finally {
		foreach ($heartbeats as $heartbeat) {
			db_execute_prepared('UPDATE data_template_rrd SET rrd_heartbeat = ? WHERE id = ?', [$heartbeat['rrd_heartbeat'], $heartbeat['id']]);
		}

		if (file_exists($path)) {
			unlink($path);
		}
		rename($backup, $path);
	}
	db_execute_prepared('DELETE FROM data_debug WHERE datasource = ?', [$id]);
	$repaired = null;
	$log      = library_captured_log(static function () use ($id, &$repaired): void {
		$repaired = dsdebug_run_repair($id);
	});
	library_expect($repaired, false, 'Repair without a debug record does nothing');
	library_expect(str_contains($log, "DS[$id] Output[No Data Source debug information found]"), true, 'Repair names the missing debug record');
	$check = (int) sql_save(['id' => 0, 'started' => time(), 'done' => 1, 'user' => 0, 'datasource' => $id, 'info' => serialize(['rrd_match_array' => []]), 'issue' => ''], 'data_debug');
	library_expect($check > 0, true, 'Repair fixture owns a completed debug record');

	try {
		$log = library_captured_log(static function () use ($id, &$repaired): void {
			$repaired = dsdebug_run_repair($id);
		});
		library_expect($repaired, false, 'Repair without a tune recommendation does nothing');
		library_expect(str_contains($log, "DS[$id] Output[No tune recommendation found]"), true, 'Repair names the missing recommendation');
	} finally {
		db_execute_prepared('DELETE FROM data_debug WHERE id = ?', [$check]);
	}
}

/** The debugger running as the unprivileged poller account diagnoses RRD files it cannot write. */
function library_dsdebug_permissions(int $id, string $path): void {
	$account = posix_getpwnam('www-data');
	library_expect(is_array($account) && posix_geteuid() === 0, true, 'Permission fixture can start a child as the poller account');
	$evidence = sys_get_temp_dir() . '/cacti-dsdebug-' . bin2hex(random_bytes(8));
	library_expect(mkdir($evidence, 0700) && chown($evidence, $account['uid']), true, 'The poller account owns the child evidence directory');
	$stored = db_fetch_cell_prepared('SELECT data_source_path FROM data_template_data WHERE local_data_id = ?', [$id]);
	$source = <<<'PHP'
require 'tests/Helpers/RequestCoverage.php';
require 'include/global.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';
require_once CACTI_PATH_LIBRARY . '/dsdebug.php';
$account = posix_getpwnam('www-data');
if (!posix_setgid($account['gid']) || !posix_setuid($account['uid'])) {
	exit(3);
}
print dsdebug_poller_bottom() ? 'checked' : 'failed';
PHP;
	$environment                          = getenv();
	$environment['CACTI_PR_RAW_COVERAGE'] = $evidence;
	$environment['XDEBUG_MODE']           = 'coverage';
	$owned                                = [];
	$template                             = ['rrd_folder_writable'  => '', 'rrd_exists' => '', 'rrd_writable' => '', 'active' => '', 'owner' => '', 'runas_poller' => '',
		'runas_website'                                                => 'root', 'last_result' => '', 'valid_data' => '', 'rra_timestamp' => '', 'rra_timestamp2' => '', 'rrd_match' => ''];

	try {
		// A root-owned copy is unwritable for the poller; only the folder owner differs between cases.
		foreach (['folder' => [0, 'RRDfile Folder (rra) is not writable by Poller.  Folder owner: root:root.  Poller runs as: www-data'],
			'file'            => [$account['uid'], 'RRDfile is not writable by Poller.  RRDfile owner: root:root.  Poller runs as www-data']] as $case => [$owner, $issue]) {
			$directory = CACTI_PATH_RRA . '/coverage-dsdebug-' . $case . '-' . bin2hex(random_bytes(4));
			library_expect(mkdir($directory, 0755) && chown($directory, $owner), true, 'Permission fixture owns its RRA folder');
			$owned[] = $directory;
			$copy    = $directory . '/' . $id . '.rrd';
			library_expect(copy($path, $copy) && chmod($copy, 0644), true, 'Root owns a readable copy of the RRD');
			db_execute_prepared('UPDATE data_template_data SET data_source_path = ? WHERE local_data_id = ?', [str_replace(CACTI_PATH_RRA, '<path_rra>', $copy), $id]);
			db_execute_prepared('DELETE FROM data_debug WHERE datasource = ?', [$id]);
			library_expect((int) sql_save(['id' => 0, 'started' => time(), 'done' => 0, 'user' => 0, 'datasource' => $id, 'info' => serialize($template), 'issue' => ''], 'data_debug') > 0, true, 'Permission check is queued');
			$process = proc_open([PHP_BINARY, '-d', 'auto_prepend_file=', '-r', $source], [0 => ['pipe', 'r'], 1 => ['file', $evidence . '/output.txt', 'w'], 2 => ['file', $evidence . '/error.txt', 'w']], $pipes, CACTI_PATH_BASE, $environment);
			library_expect(is_resource($process), true, 'Unprivileged debugger starts');
			fclose($pipes[0]);
			library_expect(proc_close($process), 0, 'Unprivileged debugger completes: ' . file_get_contents($evidence . '/error.txt'));
			// The debugger prints its statistics line before the probe's result.
			library_expect(str_ends_with(file_get_contents($evidence . '/output.txt'), "\nchecked"), true, 'Debugger processes the queued check');
			$row = db_fetch_row_prepared('SELECT done, issue FROM data_debug WHERE datasource = ?', [$id]);
			library_expect((int) $row['done'], 1, 'A permission problem completes the check');
			library_expect(str_contains($row['issue'], $issue), true, 'Debugger names the ' . $case . ' permission problem: ' . $row['issue']);
		}
		$reports = glob($evidence . '/*.json');
		library_expect(count($reports), 2, 'Each unprivileged check produces coverage');
		library_import_coverage($reports);
	} finally {
		db_execute_prepared('UPDATE data_template_data SET data_source_path = ? WHERE local_data_id = ?', [$stored, $id]);
		db_execute_prepared('DELETE FROM data_debug WHERE datasource = ?', [$id]);

		foreach ($owned as $directory) {
			unlink($directory . '/' . $id . '.rrd');
			rmdir($directory);
		}

		foreach (glob($evidence . '/*') as $file) {
			unlink($file);
		}
		rmdir($evidence);
	}
}

/** Collector output that does not match its data template is stored as unknown and reported. */
function library_poller_batches(int $id, string $path, array $names, int $time, int $step): void {
	require_once CACTI_PATH_LIBRARY . '/dsstats.php';
	$copy     = CACTI_PATH_RRA . '/coverage-poller-' . bin2hex(random_bytes(6)) . '.rrd';
	$template = (int) db_fetch_cell_prepared('SELECT data_template_id FROM data_local WHERE id = ?', [$id]);
	$enabled  = read_config_option('dsstats_enable');
	$last     = db_fetch_assoc_prepared('SELECT * FROM data_source_stats_hourly_last WHERE local_data_id = ?', [$id]);
	$pipe     = null;
	$batch    = [];
	library_expect($template > 0 && copy($path, $copy), true, 'Collector fixture owns a templated RRD copy');

	foreach (['unmapped:5' => $time, 'unparsable' => $time + $step] as $output => $at) {
		$batch[] = ['output' => $output, 'time' => date('Y-m-d H:i:s', $at), 'unix_time' => $at, 'local_data_id' => $id,
			'data_template_id'  => $template, 'rrd_path' => $copy, 'rrd_name' => $names[0], 'rrd_num' => 1];
	}

	try {
		$processed = null;
		$log       = library_captured_log(static function () use (&$pipe, $batch, &$processed): void {
			$blocked   = [];
			$processed = process_poller_output_batch($pipe, $batch, $blocked);
		});
		library_expect($processed, 2, 'RRDtool acknowledges an update for each unusable sample');
		library_expect(str_contains($log, 'WARNING: Invalid output! MULTI DS[' . $id . '] Encountered [unparsable]'), true, 'Output that is neither a number nor a field list is reported');
		library_expect(str_contains($log, 'Encountered [unmapped:5]'), false, 'A field list is never reported as unparsable');
		library_expect((int) trim(library_rrd_command(['last', $copy])), $time + $step, 'Unusable samples are stored as unknown readings');
		set_config_option('dsstats_enable', 'on');
		$updates = [$copy => ['local_data_id' => $id, 'times' => [$time => [$names[0] => '7', 'coverage_unknown' => '9']]]];
		$log     = library_captured_log(static function () use (&$updates): void {
			dsstats_poller_output($updates);
		});
		library_expect(str_contains($log, "WARNING: Unknown RRDtool Data Type '0', For 'coverage_unknown'"), true, 'A sample for a series the template does not define is reported');
		$stored = array_column(db_fetch_assoc_prepared('SELECT rrd_name, `value` FROM data_source_stats_hourly_cache WHERE local_data_id = ? AND time = FROM_UNIXTIME(?)', [$id, $time]), 'value', 'rrd_name');
		library_expect(array_key_exists('coverage_unknown', $stored) && $stored['coverage_unknown'] === null, true, 'A sample of an undefined series has no statistic');
		library_expect((float) ($stored[$names[0]] ?? -1), 7.0, 'The defined series contributes its sample');
	} finally {
		set_config_option('dsstats_enable', $enabled);
		db_execute_prepared('DELETE FROM data_source_stats_hourly_cache WHERE local_data_id = ? AND time >= FROM_UNIXTIME(?)', [$id, $time]);
		db_execute_prepared('DELETE FROM data_source_stats_hourly_last WHERE local_data_id = ?', [$id]);

		foreach ($last as $row) {
			sql_save($row, 'data_source_stats_hourly_last', ['local_data_id', 'rrd_name'], false);
		}
		unlink($copy);
	}
}

/** Compare real metadata drift and repair only this fixture's RRD. */
function library_rrd_diagnostics(int $id): void {
	$path   = get_data_source_path($id, true);
	$backup = $path . '.diagnostics-backup';
	library_expect(!file_exists($backup) && copy($path, $backup), true, 'Diagnostics retain the original samples');
	$original = hash_file('sha256', $path);
	$expected = rrdtool_function_info_from_ds($id);
	$name     = array_key_first($expected['ds']);

	try {
		$info = rrdtool_function_info($id);
		library_expect(rrdtool_cacti_compare($id, $info), [], 'Freshly created RRD matches its stored definition');
		library_expect(hash_file('sha256', $path), $original, 'Inspection preserves the actual RRD');
		db_execute_prepared('UPDATE data_template_rrd SET rrd_heartbeat = 1 WHERE local_data_id = ? AND data_source_name = ?', [$id, $name]);
		rrdtool_cacti_compare($id, $info);
		library_expect((int) db_fetch_cell_prepared('SELECT rrd_heartbeat FROM data_template_rrd WHERE local_data_id = ? AND data_source_name = ?', [$id, $name]), (int) $expected['ds'][$name]['minimal_heartbeat'], 'Diagnostics repair a stale database heartbeat from the authoritative profile');
		library_expect(hash_file('sha256', $path), $original, 'Database heartbeat repair preserves existing RRD samples');
		library_duplicate_profile($id);
		library_expect(rrdtool_function_contains_cf($id, 'AVERAGE'), true, 'Observed RRD contains its average archive');
		library_expect(rrdtool_function_contains_cf($id, 'UNSUPPORTED'), false, 'Missing archive is not reported as present');

		library_rrd_command(['tune', $path, '--data-source-type', $name . ':DERIVE',
			'--heartbeat', $name . ':1', '--minimum', $name . ':7']);
		$info = rrdtool_function_info($id);
		library_expect($info['ds'][$name]['type'], 'DERIVE', 'Drift exists in the actual file before comparison');
		$drifted = hash_file('sha256', $path);
		$diff    = rrdtool_cacti_compare($id, $info);

		foreach (['type', 'minimal_heartbeat', 'min'] as $field) {
			library_expect(isset($diff['ds'][$name][$field]), true, 'Comparison identifies changed ' . $field);
		}
		library_expect(count($diff['tune']), 3, 'Each changed field has one repair recommendation');
		library_expect(hash_file('sha256', $path), $drifted, 'Recommendations do not mutate the file');
		rrdtool_tune_execute($path, $diff);
		$repaired = rrdtool_function_info($id);
		library_expect(rrdtool_cacti_compare($id, $repaired), [], 'Applying advice restores the stored definition');
		library_expect($repaired['last_update'], $info['last_update'], 'Tuning preserves the sample timestamp');

		ob_start();
		rrdtool_info2html($info, $diff);
		$html        = ob_get_clean();
		$dom         = new DOMDocument();
		$priorErrors = libxml_use_internal_errors(true);

		try {
			library_expect($dom->loadHTML($html), true, 'Real RRD diagnostics render as HTML');
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors($priorErrors);
		}
		$xpath = new DOMXPath($dom);
		library_expect($xpath->query('//table')->length, 3, 'Diagnostics include header, data sources and archives');
		library_expect(str_contains($dom->textContent, $name), true, 'Diagnostics identify the affected data source');
		library_expect(str_contains($dom->textContent, 'DERIVE'), true, 'Diagnostics display the observed type');

		// Change the database step while retaining the file, as after a profile edit.
		library_expect(db_execute_prepared('UPDATE data_template_data SET rrd_step = ? WHERE local_data_id = ?', [(int) $expected['step'] * 2, $id]), true, 'Profile drift persists');

		try {
			$info = rrdtool_function_info($id);
			library_expect(isset(rrdtool_cacti_compare($id, $info)['step']), true, 'Step mismatch is diagnosed');
		} finally {
			db_execute_prepared('UPDATE data_template_data SET rrd_step = ? WHERE local_data_id = ?', [$expected['step'], $id]);
		}

		// A file copied from a different template can contain an extra series.
		library_rrd_command(['tune', $path, 'DS:orphan:GAUGE:600:U:U']);
		$info = rrdtool_function_info($id);
		library_expect(isset($info['ds']['orphan']), true, 'Extra series really exists before comparison');
		$diff = rrdtool_cacti_compare($id, $info);
		library_expect(str_contains(implode(' ', $diff['tune'] ?? []), 'DEL:orphan'), true, 'Extra series receives a removal recommendation');
		rrdtool_tune_execute($path, $diff);
		$info = rrdtool_function_info($id);
		library_expect(isset($info['ds']['orphan']), false, 'Applying the recommendation removes the extra series');
		library_expect(isset($info['ds'][$name]), true, 'Repair retains the configured series');

		// Recreate only the owned fixture file to model archive/profile drift.
		foreach (['grow', 'shrink', 'xff', 'steps', 'duplicate', 'missing'] as $case) {
			$archives = $expected['rra'];

			if ($case === 'grow' || $case === 'shrink') {
				$archives[0]['rows'] += $case === 'grow' ? -5 : 5;
			} elseif ($case === 'xff') {
				$archives[0]['xff'] = 0.25;
			} elseif ($case === 'steps') {
				$archives[0]['steps'] += 1;
			} elseif ($case === 'duplicate') {
				$archives[] = $archives[0];
				$archives[0]['rows'] -= 5;
			} else {
				array_pop($archives);
			}
			$arguments = ['create', $path, '--step', (string) $expected['step']];

			foreach ($expected['ds'] as $series => $ds) {
				$arguments[] = rrdtool_tune_ds_add_spec($series, $ds['type'], $ds['minimal_heartbeat'], $ds['min'], $ds['max']);
			}

			foreach ($archives as $archive) {
				$arguments[] = implode(':', ['RRA', $archive['cf'], $archive['xff'], $archive['steps'], $archive['rows']]);
			}
			library_expect(unlink($path), true, 'Only the owned RRD is replaced for archive drift');
			library_rrd_command($arguments);
			$info   = rrdtool_function_info($id);
			$before = hash_file('sha256', $path);
			$diff   = rrdtool_cacti_compare($id, $info);

			if ($case === 'grow' || $case === 'shrink') {
				library_expect($diff['resize'], [cacti_escapeshellarg($path) . ' 0 ' . strtoupper($case) . ' 5'], 'Resize advice uses the exact archive and row difference');
			} elseif ($case === 'xff') {
				library_expect(isset($diff['rra'][0]['xff']), true, 'Changed unknown-sample threshold is diagnosed');
			} elseif ($case === 'steps') {
				library_expect(isset($diff['rra'][0]['pdp_per_row_error']), true, 'Changed archive resolution requires operator attention');
			} elseif ($case === 'duplicate') {
				library_expect(isset($diff['rra'][0]['error']), true, 'Duplicate archive definitions are diagnosed');
				library_expect(isset($diff['resize']), false, 'Ambiguous duplicate archives suppress resize advice');
			} else {
				library_expect(isset($diff['rra']['cacti_' . array_key_last($expected['rra'])]['error']), true, 'Missing configured archive is diagnosed');
			}
			library_expect(hash_file('sha256', $path), $before, 'Archive comparison leaves the RRD unchanged');
		}

		// Renaming a series models a file belonging to another data template.
		library_rrd_command(['tune', $path, '--data-source-rename', $name . ':other_sensor']);
		$info = rrdtool_function_info($id);
		$diff = rrdtool_cacti_compare($id, $info);
		library_expect(str_contains(implode(' ', $diff['tune'] ?? []), 'DS:' . $name . ':'), true, 'Missing configured series receives an add recommendation');
		library_expect(str_contains(implode(' ', $diff['tune'] ?? []), 'DEL:other_sensor'), true, 'Unrelated renamed series receives a removal recommendation');
		library_expect($info['ds'][$name]['seen'], true, 'Missing series remains visible to the diagnostic renderer');
	} finally {
		library_expect(rename($backup, $path), true, 'Diagnostic fixture restores its original RRD');
		library_expect(hash_file('sha256', $path), $original, 'Original stored samples are restored byte for byte');
	}
}

/** Exercise business-hours graph options with temporarily overridden settings and time zone. */
function library_business_hours(int $graph): void {
	global $_CACTI_REQUEST;
	$requests = [$_CACTI_REQUEST ?? [], $_REQUEST, $_GET, $_POST];
	$zone     = date_default_timezone_get();
	$options  = ['business_hours_enable' => 'on', 'business_hours_start' => '09:00',
		'business_hours_end'                => '17:00', 'business_hours_max_days' => '7',
		'business_hours_color'              => '0', 'business_hours_opacity' => '', 'business_hours_hide_weekends' => 'on'];
	$previous = [];

	foreach ($options as $key => $value) {
		$previous[$key] = read_config_option($key);
		set_config_option($key, $value);
	}
	date_default_timezone_set('UTC');
	srv('business_hours', 'true');

	try {
		foreach (['2026-10-16' => true, '2026-10-17' => false] as $day => $weekday) {
			$start  = strtotime($day . ' 08:00 UTC');
			$end    = strtotime($day . ' 18:00 UTC');
			$data   = ['start' => $start, 'end' => $end, 'graph_defs' => ''];
			$meta   = [];
			$result = add_business_hours($data, $meta);
			library_expect(str_contains($result['graph_defs'], ',TIME,' . strtotime($day . ' 09:00 UTC') . ',LT'), true, 'Business shading starts at configured local time');
			library_expect(str_contains($result['graph_defs'], ',TIME,' . strtotime($day . ' 17:00 UTC') . ',GT'), true, 'Business shading ends at configured local time');
			library_expect(str_contains($result['graph_defs'], 'AREA:dslimit0#CCCCCC7F'), $weekday, 'Weekend preference controls the actual area');
			library_expect($meta['ignoreItems'], 1, 'Decorative business series is excluded from data export');
			$image = rrdtool_function_graph($graph, '', ['graph_start' => $start, 'graph_end' => $end, 'image_format' => 'png']);
			library_expect(is_string($image) && str_starts_with($image, "\x89PNG\r\n\x1a\n"), true, 'RRDtool executes the business-hour graph definitions');
		}
		set_config_option('business_hours_hide_weekends', '');
		set_config_option('business_hours_opacity', '40');
		$meta   = ['ignoreItems' => 3];
		$result = add_business_hours($data, $meta);
		library_expect(str_contains($result['graph_defs'], 'AREA:dslimit0#CCCCCC40'), true, 'Configured opacity and weekend inclusion reach the graph');
		library_expect($meta['ignoreItems'], 4, 'Business overlay preserves prior decorative series count');
		$color = db_fetch_row('SELECT id, hex FROM colors ORDER BY id LIMIT 1');
		library_expect(isset($color['id'], $color['hex']), true, 'Installed palette supplies a real overlay color');
		set_config_option('business_hours_color', (string) $color['id']);
		set_config_option('business_hours_opacity', 'invalid');
		$meta    = [];
		$clipped = ['start' => strtotime('2026-10-16 10:00 UTC'), 'end' => strtotime('2026-10-16 15:00 UTC'), 'graph_defs' => ''];
		$result  = add_business_hours($clipped, $meta);
		library_expect(str_contains($result['graph_defs'], 'AREA:dslimit0#' . $color['hex'] . '7F'), true, 'Invalid opacity falls back while preserving the selected palette color');
		library_expect(str_contains($result['graph_defs'], ',TIME,' . $clipped['start'] . ',LT'), true, 'Business overlay is clipped to the graph start');
		library_expect(str_contains($result['graph_defs'], ',TIME,' . $clipped['end'] . ',GT'), true, 'Business overlay is clipped to the graph end');
		$relative = ['start' => -3600, 'end' => 0, 'graph_defs' => ''];
		$meta     = [];
		library_expect(str_contains(add_business_hours($relative, $meta)['graph_defs'], 'CDEF:officehours0='), true, 'Relative graph ranges generate executable business-hour definitions');
		set_config_option('business_hours_max_days', '1');
		$meta = [];
		$long = ['start' => $start, 'end' => $end + 86400, 'graph_defs' => 'existing'];
		library_expect(add_business_hours($long, $meta), $long, 'Long ranges avoid expanding business-hour definitions');
		library_expect($meta, [], 'Skipped overlay does not change export metadata');
		// An overnight shift that already began before the graph window starts again the next evening.
		set_config_option('business_hours_start', '21:00');
		set_config_option('business_hours_end', '08:00');
		$meta      = [];
		$overnight = ['start' => strtotime('2026-10-16 22:00 UTC'), 'end' => strtotime('2026-10-16 23:00 UTC'), 'graph_defs' => ''];
		$result    = add_business_hours($overnight, $meta);
		library_expect(str_contains($result['graph_defs'], ',TIME,' . strtotime('2026-10-17 21:00 UTC') . ',LT'), true, 'An overnight shift resumes at the next configured start');
		library_expect(str_contains($result['graph_defs'], ',TIME,' . $overnight['end'] . ',GT'), true, 'An overnight shift is clipped to the graph end');
		srv('business_hours', 'false');
		library_expect(add_business_hours($data, $meta), $data, 'Viewer can disable the overlay');
	} finally {
		foreach ($previous as $key => $value) {
			set_config_option($key, $value);
		}
		[$_CACTI_REQUEST, $_REQUEST, $_GET, $_POST] = $requests;
		date_default_timezone_set($zone);
	}
}

/** Run the installed RRDtool against files owned by this disposable fixture. */
function library_rrd_command(array $arguments): string {
	$process = proc_open(array_merge([read_config_option('path_rrdtool')], $arguments), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	library_expect(is_resource($process), true, 'RRDtool starts');
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]);
	$error  = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	library_expect(proc_close($process), 0, 'RRDtool succeeds: ' . $error);
	library_expect(is_string($output), true, 'RRDtool output is readable');

	return $output;
}

/** Run spike repair on a private RRD copy with or without the windowed standard deviation. */
function library_spike_repair(bool $windowedStddev = true): void {
	$directory = tempnam(sys_get_temp_dir(), 'cacti-spike-');
	library_expect(is_string($directory) && unlink($directory) && mkdir($directory, 0700), true, 'Spike fixture owns a private directory');
	$previous = read_config_option('spikekill_backupdir');
	set_config_option('spikekill_backupdir', $directory);
	$original = $directory . '/original.rrd';
	$working  = $directory . '/working.rrd';
	$step     = 300;
	$start    = intdiv(time(), $step) * $step - 80 * $step;
	$spike    = $start + 30 * $step;
	$gap      = $start + 40 * $step;

	try {
		library_rrd_command(['create', $original, '--start', (string) $start, '--step', (string) $step,
			'DS:sensor:GAUGE:600:U:U', 'DS:control:GAUGE:600:U:U', 'RRA:AVERAGE:0.5:1:100', 'RRA:MAX:0.5:1:100']);
		$samples = [];

		foreach (range(1, 60) as $offset) {
			$value     = $offset === 30 ? '1000' : ($offset === 40 ? 'U' : '10');
			$samples[] = ($start + $offset * $step) . ':' . $value . ':5';
		}
		library_rrd_command(array_merge(['update', $original], $samples));

		foreach (['stddev', 'float', 'fill', 'absolute'] as $method) {
			foreach (['last', 'avg', 'nan'] as $replacement) {
				library_expect(copy($original, $working), true, 'Each repair starts from the same observed samples');
				$window         = $method !== 'stddev' || $windowedStddev;
				$repair         = new spikekill($working, $method, $replacement, '2', $window ? (string) ($spike - $step) : '', $window ? (string) ($gap + $step) : '', '10', 'sensor', '100');
				$repair->html   = false;
				$repair->backup = true;
				library_expect($repair->remove_spikes(), true, $method . ' repair succeeds: ' . $repair->get_errors());
				library_expect($repair->get_errors(), '', 'Successful repair has no errors');
				$output = library_rrd_command(['fetch', $working, 'AVERAGE', '--start', (string) $start, '--end', (string) ($start + 60 * $step)]);
				$target = $method === 'fill' ? $gap : $spike;
				library_expect(preg_match('/^' . $target . ':\s+(\S+)\s+(\S+)/m', $output, $match), 1, 'Fetch exposes the repaired timestamp');
				library_expect((float) $match[2], 5.0, $method . '/' . $replacement . ' preserves the control series: ' . $match[2]);

				if ($replacement === 'nan' && $method === 'float') {
					// Float and fill explicitly do not implement NaN replacement.
					library_expect((float) $match[1], 1000.0, 'Unsupported float/NaN replacement preserves the sample');
				} elseif ($replacement === 'nan') {
					library_expect(str_contains(strtolower($match[1]), 'nan'), true, $method . '/' . $replacement . ' remains unknown in the actual RRD, got ' . $match[1]);
				} else {
					library_expect(is_numeric($match[1]) && (float) $match[1] > 0 && (float) $match[1] < 100, true, $method . '/' . $replacement . ' restores the normal sample range, got ' . $match[1] . ': ' . $repair->get_output());
				}
				library_expect(preg_match('/^' . ($start + 20 * $step) . ':\s+(\S+)/m', $output, $unaffected), 1, 'Fetch exposes an unaffected timestamp');
				library_expect((float) $unaffected[1], 10.0, 'Repair preserves samples outside its selected window');

				if ($method === 'stddev' || $method === 'absolute') {
					library_expect(preg_match('/^' . $gap . ':\s+(\S+)/m', $output, $missing), 1, 'Fetch exposes the missing sample');
					library_expect(str_contains(strtolower($missing[1]), 'nan'), true, $method . '/' . $replacement . ' preserves unknown samples');
				}
			}
		}
		library_expect(copy($original, $working), true, 'Dry run starts from the original samples');
		$before          = hash_file('sha256', $working);
		$preview         = new spikekill($working, 'stddev', 'last', '2', '', '', '10', 'sensor', '100');
		$preview->dryrun = true;
		$preview->debug  = true;
		$completed       = null;
		$log             = library_captured_log(static function () use ($preview, &$completed): void {
			ob_start();
			$completed = $preview->remove_spikes();
			ob_end_clean();
		});
		library_expect($completed, true, 'Spike preview completes');
		library_expect(str_contains($log, "Spikes from '" . $working . "', Method:'"), true, 'A debug preview records its analysis in the log');
		library_expect(hash_file('sha256', $working), $before, 'A dry run preserves the RRD byte for byte');
		library_expect($preview->get_output() !== '', true, 'A dry run reports its analysis');

		foreach ([['method' => 'unsupported'], ['avgnan' => 'unsupported'], ['stddev' => 0], ['numspike' => -1], ['absmax' => -1], ['out_start' => 20, 'out_end' => 10], ['out_start' => 20, 'out_end' => 0],
			['method' => 'float'], ['method'                => 'fill'], ['method' => 'absolute'], ['method' => 'float', 'out_start' => 'not a date', 'out_end' => 'not a time']] as $invalid) {
			$repair = new spikekill($working, 'stddev', 'last', '2', '', '', '10', 'sensor', '100');

			foreach ($invalid as $field => $value) {
				$repair->$field = $value;
			}
			library_expect($repair->remove_spikes(), false, 'Invalid spike parameters are rejected');
			library_expect($repair->get_errors() !== '', true, 'Invalid parameters have an actionable error');
			library_expect(hash_file('sha256', $working), $before, 'Validation failure preserves stored samples');
		}
	} finally {
		set_config_option('spikekill_backupdir', $previous);

		foreach (glob($directory . '/*') as $owned) {
			unlink($owned);
		}
		rmdir($directory);
	}
}

/** Follow real RRDtool statistics through the cache and database writer. */
function library_dsstats(int $id): void {
	require_once CACTI_PATH_LIBRARY . '/dsstats.php';
	$directory = sys_get_temp_dir() . '/cacti-dsstats-' . bin2hex(random_bytes(8));
	library_expect(mkdir($directory, 0700), true, 'Statistics fixture owns its RRD directory');
	$path     = $directory . '/samples.rrd';
	$process  = null;
	$options  = ['dsstats_mode', 'dsstats_peak', 'dsstats_temp_mode', 'dsstats_temp_peak'];
	$previous = [];

	foreach ($options as $option) {
		$previous[$option] = read_config_option($option);
	}

	try {
		$step  = 300;
		$end   = intdiv(time(), $step) * $step;
		$start = $end - 289 * $step;
		library_rrd_command(['create', $path, '--start', (string) $start, '--step', (string) $step,
			'DS:sensor:GAUGE:600:U:U', 'RRA:AVERAGE:0.5:1:1440', 'RRA:MAX:0.5:1:1440']);
		$samples = [];

		foreach (range(1, 289) as $value) {
			$samples[] = ($start + $value * $step) . ':' . $value;
		}
		library_rrd_command(array_merge(['update', $path], $samples));
		$process = dsstats_rrdtool_init();
		library_expect(is_resource($process[0]), true, 'Statistics start the installed RRDtool');
		library_expect(dsstats_rrdtool_execute('', $process), '', 'Empty statistics command produces no result');

		foreach ([[0, false, 'daily'], [0, true, 'daily'], [1, false, 'daily'], [1, true, 'daily']] as [$mode, $peak, $interval]) {
			set_config_option('dsstats_mode', (string) $mode);
			set_config_option('dsstats_peak', $peak ? 'on' : '');
			db_execute_prepared('DELETE FROM data_source_stats_command_cache WHERE local_data_id = ?', [$id]);
			$values = dsstats_obtain_data_source_avgpeak_values($id, $path, $interval, $mode, $peak, $process);
			library_expect(array_keys($values), $peak ? ['avg', 'max'] : ['avg'], 'Statistics respect the configured consolidation functions');
			$command = db_fetch_cell_prepared('SELECT stats_command FROM data_source_stats_command_cache WHERE local_data_id = ?', [$id]);
			library_expect(is_string($command) && str_contains($command, $path), true, 'Statistics cache targets the actual fixture RRD');
			$again = dsstats_obtain_data_source_avgpeak_values($id, $path, $interval, $mode, $peak, $process);
			library_expect($again, $values, 'Cached statistics reproduce the same samples');
			db_execute_prepared('DELETE FROM data_source_stats_' . $interval . ' WHERE local_data_id = ?', [$id]);
			$buffer = [$id => $values];
			dsstats_write_buffer($buffer, $interval, $mode);
			$rows = db_fetch_assoc_prepared('SELECT * FROM data_source_stats_' . $interval . ' WHERE local_data_id = ? ORDER BY cf', [$id]);
			library_expect(count($rows), $peak ? 2 : 1, 'Database stores one row per requested consolidation function');

			foreach ($rows as $row) {
				$stats = $values[(int) $row['cf'] === 0 ? 'avg' : 'max']['sensor'];
				library_expect($row['rrd_name'], 'sensor', 'Stored statistics retain the RRD series identity');
				library_expect((float) $row['average'] >= 140 && (float) $row['average'] <= 150, true, 'Observed average matches the seeded daily ramp');
				library_expect((float) $row['peak'], 289.0, 'Observed peak matches the largest stored sample');

				foreach (['avg' => 'average', 'peak' => 'peak'] + ($mode === 1 ? array_combine(['p95n', 'p90n', 'p75n', 'p50n', 'p25n', 'sum', 'stddev', 'lslslope', 'lslint', 'lslcorrel'], ['p95n', 'p90n', 'p75n', 'p50n', 'p25n', 'sum', 'stddev', 'lslslope', 'lslint', 'lslcorrel']) : []) as $metric => $column) {
					library_expect(is_numeric($stats[$metric]) && abs((float) $row[$column] - (float) $stats[$metric]) < max(0.001, abs((float) $stats[$metric]) * 0.000001), true, 'Database preserves RRDtool metric ' . $metric);
				}

				if ($mode === 1) {
					library_expect((float) $stats['p90n'] > 250 && (float) $stats['p90n'] < 270, true, 'Percentile follows the seeded ramp');
					library_expect((float) $stats['lslcorrel'] > 0.99, true, 'Trend correlation recognizes the increasing samples');
				}
			}
			dsstats_write_buffer($buffer, $interval, $mode);
			library_expect(db_fetch_assoc_prepared('SELECT * FROM data_source_stats_' . $interval . ' WHERE local_data_id = ? ORDER BY cf', [$id]), $rows, 'Repeated statistics write is idempotent');
		}

		// Longer windows consolidate the ramp at the resolution chosen by RRDtool.
		foreach (['weekly', 'monthly', 'yearly'] as $interval) {
			$values = dsstats_obtain_data_source_avgpeak_values($id, $path, $interval, 1, true, $process);
			library_expect(array_keys($values), ['avg', 'max'], 'Long-window statistics retain both consolidation functions');

			foreach ($values as $statistics) {
				$average = (float) $statistics['sensor']['avg'];
				$peak    = (float) $statistics['sensor']['peak'];
				library_expect($average > 0 && $average <= $peak && $peak <= 289, true, 'Consolidated values remain within the seeded sample range');
			}
		}
		library_expect(dsstats_obtain_data_source_avgpeak_values($id, $directory . '/missing.rrd', 'daily', 0, false, $process), [], 'Missing RRD cannot fabricate statistics');
	} finally {
		if (is_array($process) && is_resource($process[0])) {
			dsstats_rrdtool_close($process);
		}

		foreach (['daily', 'weekly', 'monthly', 'yearly'] as $interval) {
			db_execute_prepared('DELETE FROM data_source_stats_' . $interval . ' WHERE local_data_id = ?', [$id]);
		}
		db_execute_prepared('DELETE FROM data_source_stats_command_cache WHERE local_data_id = ?', [$id]);

		foreach ($previous as $option => $value) {
			set_config_option($option, $value);
		}

		if (is_file($path)) {
			unlink($path);
		}
		rmdir($directory);
	}
}
