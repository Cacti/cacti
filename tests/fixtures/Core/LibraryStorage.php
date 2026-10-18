<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

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
		library_expect($preview->remove_spikes(), true, 'Spike preview completes');
		library_expect(hash_file('sha256', $working), $before, 'A dry run preserves the RRD byte for byte');
		library_expect($preview->get_output() !== '', true, 'A dry run reports its analysis');

		foreach ([['method' => 'unsupported'], ['avgnan' => 'unsupported'], ['stddev' => 0], ['numspike' => -1], ['absmax' => -1], ['out_start' => 20, 'out_end' => 10], ['out_start' => 20, 'out_end' => 0]] as $invalid) {
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
