<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 4);

function boostLifecycleFunctionBody($source, $signature) {
	$start = strpos($source, $signature);

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, $end === false ? null : $end - $start);
}

test('the parent repairs completion schema before truncating shared completion state', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	expect($source)->not->toBeFalse();

	$repair   = strpos($source, 'boost_ensure_process_table(true)');
	$truncate = strpos($source, "db_execute('TRUNCATE TABLE poller_output_boost_processes')");

	expect($repair)->not->toBeFalse()
		->and($truncate)->not->toBeFalse()
		->and($repair)->toBeLessThan($truncate);
});

test('the parent waits for both process exit and run-scoped completion', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_drain_archive(');

	expect($body)->toContain('$running_children   = boost_processes_running()')
		->and($body)->toContain('$completed_children = boost_completed_children($run_id)')
		->and($body)->toContain('$running_children === false || $completed_children === false')
		->and($body)->toContain('$running_children === 0')
		->and($body)->toContain('boost_failed_children($run_id)')
		->and($body)->toContain('$complete = $completed_children >= $expected_children && $failed_children === 0;');
});

test('the worker barrier removes dead registrations before counting active children', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_processes_running(');

	expect($body)->toContain('boost_child_process_still_running($process[\'pid\'])')
		->and($body)->toContain('unregister_process($process[\'tasktype\'], $process[\'taskname\'], $process[\'taskid\'], $process[\'pid\'])')
		->and($body)->toContain('$running++')
		->and($body)->not->toContain('SELECT COUNT(*)');
});

test('Boost treats Linux zombie children as exited while preserving the platform fallback', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_child_process_still_running(');

	expect($body)->toContain('cacti_process_still_running($pid)')
		->and($body)->toContain("'/proc/' . (int) \$pid . '/stat'")
		->and($body)->toContain("\$state === 'Z' || \$state === 'X'")
		->and($body)->toContain('return true;');
});

test('only the parent-owned archive is dropped after every child has stopped', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_drain_archive(');
	$barrier = strpos($body, 'if ($running_children === false || $running_children > 0)');
	$drop    = strpos($body, 'DROP TABLE IF EXISTS `$table`', $barrier);

	expect($barrier)->not->toBeFalse()
		->and($drop)->not->toBeFalse()
		->and($barrier)->toBeLessThan($drop)
		->and($body)->toContain('if (!$complete)')
		->and($body)->not->toContain('boost_get_arch_table_names()');
});

test('data-source cleanup leaves enabled Boost queues to their owner', function () use ($root) {
	$source     = file_get_contents($root . '/lib/data_query.php');
	$single_api = boostLifecycleFunctionBody(
		file_get_contents($root . '/lib/api_data_source.php'),
		'function api_data_source_remove('
	);
	$multi_api = boostLifecycleFunctionBody(
		file_get_contents($root . '/lib/api_data_source.php'),
		'function api_data_source_remove_multi('
	);
	$body = boostLifecycleFunctionBody($source, 'function data_query_remove_disabled_items(');

	expect($body)->toContain("read_config_option('boost_rrd_update_enable') != 'on'")
		->and($body)->toContain('Enabled Boost owns its live and sealed rows')
		->and($body)->not->toContain('poller_output_boost_arch')
		->and($body)->not->toContain('information_schema.TABLES')
		->and(substr_count($single_api, "read_config_option('boost_rrd_update_enable') != 'on'"))->toBe(2)
		->and(substr_count($multi_api, "read_config_option('boost_rrd_update_enable') != 'on'"))->toBe(2)
		->and($single_api)->not->toContain('poller_output_boost_arch')
		->and($multi_api)->not->toContain('poller_output_boost_arch');
});

test('child completion is idempotent within a run and records failures', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_failed_children(');

	expect($source)->toContain('ON DUPLICATE KEY UPDATE status = VALUES(status)')
		->and($source)->toContain('array($run_id, $child, $rrd_updates)')
		->and($body)->toContain('WHERE run_id = ?')
		->and($body)->toContain('CAST(status AS SIGNED) < 0');
});

test('individual RRD failures are isolated while a systemic failure retains the archive', function () use ($root) {
	$poller = file_get_contents($root . '/poller_boost.php');
	$boost  = file_get_contents($root . '/lib/boost.php');

	expect($poller)->toContain('boost_queue_rrd_failure(')
		->and($poller)->toContain("if (!\$disposition['handled'])")
		->and($poller)->toContain("\$boost_backend_circuit['open'] = true")
		->and($poller)->toContain('return $updates_ok && $results !== false ? cacti_sizeof($results) : -1;')
		->and($boost)->toContain('if ($updates_ok && cacti_sizeof($results))')
		->and($boost)->toContain('return $updates_ok ? cacti_sizeof($results) : -1;');
});

test('a deleted data source is an explicit discard and cannot retain its stale retry', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_process_output(');
	$deleted = strpos($body, "if (\$rrd_path == '')");
	$write   = strpos($body, 'boost_rrdtool_function_update(');

	expect($deleted)->not->toBeFalse()
		->and($deleted)->toBeLessThan($write)
		->and($body)->toContain("\$boost_worker_accounting['discarded_samples'] += \$measure['count']")
		->and($body)->toContain('boost_clear_superseded_rrd_failure($local_data_id, $measure[\'latest\'])')
		->and($body)->toContain('after Local Data ID %d was removed.');
});

test('permanent destination errors do not trip the backend-wide circuit', function () use ($root) {
	$source     = file_get_contents($root . '/poller_boost.php');
	$boost      = file_get_contents($root . '/lib/boost.php');
	$classifier = boostLifecycleFunctionBody($boost, 'function boost_is_isolated_rrd_failure(');
	$circuit    = boostLifecycleFunctionBody($source, 'function boost_record_backend_result(');
	$retry      = boostLifecycleFunctionBody($boost, 'function boost_retry_rrd_failures(');

	expect($classifier)->toContain('not an rrd file')
		->and($classifier)->toContain('while loading header')
		->and($classifier)->not->toContain('no such file or directory')
		->and($classifier)->toContain('illegal attempt to update')
		->and($classifier)->toContain('invalid rrd update')
		->and($circuit)->toContain('boost_is_isolated_rrd_failure($error)')
		->and($circuit)->toContain("\$boost_backend_circuit['isolated_failures']++")
		->and($source)->toContain('boost_record_backend_result(false, $return_value)')
		->and($retry)->toContain('boost_backend_failure_window_open(')
		->and($retry)->toContain('pending destinations remain queued.')
		->and($retry)->toContain('break;');
});

test('dynamic archive identifiers are validated before select delete analyze and drop use', function () use ($root) {
	$boost  = file_get_contents($root . '/lib/boost.php');
	$poller = file_get_contents($root . '/poller_boost.php');

	expect($boost)->toContain("preg_match('/^poller_output_boost_arch_\\d+$/D', \$table)")
		->and($boost)->toContain("array_filter(\$tableNames, 'boost_is_valid_archive_table')")
		->and($poller)->toContain('if (!boost_is_valid_archive_table($table) || !boost_dynamic_table_exists_uncached($table))')
		->and($poller)->toContain('DROP TABLE IF EXISTS `$table`');
});

test('newly rotated tables bypass the generic table-existence cache', function () use ($root) {
	$boost  = file_get_contents($root . '/lib/boost.php');
	$poller = file_get_contents($root . '/poller_boost.php');
	$rotate = boostLifecycleFunctionBody($poller, 'function boost_rotate_output_table(');
	$stage  = boostLifecycleFunctionBody($poller, 'function boost_prepare_process_table(');

	expect($boost)->toContain('function boost_dynamic_table_exists_uncached(')
		->and($rotate)->toContain('boost_dynamic_table_exists_uncached($archive_table)')
		->and($rotate)->toContain('boost_dynamic_table_exists_uncached($interim_table)')
		->and($stage)->toContain('boost_dynamic_table_exists_uncached($table)')
		->and($rotate)->not->toContain('db_table_exists($archive_table)')
		->and($stage)->not->toContain('db_table_exists($table)');
});

test('recovery reads are bounded and leave recovery status visible after transfer failure', function () use ($root) {
	$source = file_get_contents($root . '/poller_recovery.php');

	expect($source)->toContain("LIMIT ' . (int) \$record_limit")
		->and($source)->toContain('ORDER BY time ASC, local_data_id ASC, rrd_name ASC')
		->and($source)->toContain('if (!$transfer_failed) {')
		->and($source)->toContain('exit($transfer_failed ? 1 : 0)');
});

test('RRD creation and update failures cannot acknowledge queued samples', function () use ($root) {
	$boost  = file_get_contents($root . '/lib/boost.php');
	$poller = file_get_contents($root . '/poller_boost.php');

	expect($boost)->toContain("return 'ERROR: Unable to create RRD file';")
		->and($boost)->toContain("rrdtool_execute_path_command('file_exists', \$rrd_path")
		->and(substr_count($boost, "trim((string) \$return_value) !== 'OK'"))->toBe(2)
		->and($poller)->toContain("trim((string) \$return_value) !== 'OK'");
});

test('a temporary RRDtool outage cannot downgrade replay capability', function () use ($root) {
	$boost     = file_get_contents($root . '/lib/boost.php');
	$functions = file_get_contents($root . '/lib/functions.php');
	$poller    = file_get_contents($root . '/poller_boost.php');
	$refresh   = boostLifecycleFunctionBody($boost, 'function boost_refresh_rrdtool_version(');

	expect($refresh)->toContain('if ($installed === false)')
		->and($refresh)->toContain('return $configured;')
		->and($refresh)->toContain("set_config_option('rrdtool_version', \$installed)")
		->and($refresh)->toContain('get_rrdtool_version(true)')
		->and($functions)->toContain("if (\$version == '' || \$force)")
		->and(substr_count($poller, 'boost_refresh_rrdtool_version()'))->toBe(2);
});

test('pre-1.5 replay treats the last acknowledged timestamp as already applied', function () use ($root) {
	$boost  = file_get_contents($root . '/lib/boost.php');
	$poller = file_get_contents($root . '/poller_boost.php');
	$guard  = "if (\$item['timestamp'] <= \$last_update && cacti_version_compare(get_rrdtool_version(), '1.5', '<'))";

	expect($boost)->toContain($guard)
		->and($poller)->toContain($guard);
});

test('Boost SQL string literals do not depend on MySQL double-quote mode', function () use ($root) {
	$unsafe = array(
		'tasktype = "',
		'taskname = "',
		'disabled = "',
		'LIKE "',
		'VALUES ("',
		'SET status = "',
		'SET status="',
		'CONCAT(data_template_id, "',
		'REPLACE(name, "',
		'end_time="',
	);

	foreach (array('lib/boost.php', 'poller_boost.php', 'poller_recovery.php') as $file) {
		$source = file_get_contents($root . '/' . $file);

		foreach ($unsafe as $needle) {
			expect($source)->not->toContain($needle, $file . ': ' . $needle);
		}
	}
});

test('table rotation and shard preparation fail closed on database errors', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$rotate = boostLifecycleFunctionBody($source, 'function boost_rotate_output_table(');
	$stage  = boostLifecycleFunctionBody($source, 'function boost_prepare_process_table(');

	expect($rotate)->toContain('failed to create its interim output table')
		->and($rotate)->toContain('failed to rotate its output table')
		->and($rotate)->toContain('could not inspect the live output table before rotation')
		->and($stage)->toContain('failed to create its process-distribution table')
		->and($stage)->toContain('failed to stage archive data-source ranges')
		->and($stage)->toContain('could not verify its staged archive ranges')
		->and($stage)->toContain('could not verify exclusive RRD path ownership');
});

test('database errors cannot be cast to an empty archive and dropped', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_drain_archive(');
	$failure = strpos($body, 'if ($data_ids === false)');
	$empty   = strpos($body, 'if ($data_ids === 0)');
	$drop    = strpos($body, 'DROP TABLE IF EXISTS `$table`');

	expect($failure)->not->toBeFalse()
		->and($empty)->toBeGreaterThan($failure)
		->and($drop)->toBeGreaterThan($empty)
		->and($body)->toContain("return array('complete' => false, 'updates' => 0, 'failed_children' => 0);");
});

test('scheduled Boost pages use bounded adjacent primary-key reads', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$page   = boostLifecycleFunctionBody($source, 'function boost_fetch_archive_page(');
	$worker = boostLifecycleFunctionBody($source, 'function boost_process_local_data_ids(');

	expect($page)->toContain('$limit   = (int) $max_rows + 1;')
		->and($page)->toContain('WHERE archive.local_data_id = ?')
		->and($page)->toContain('archive.time > ?')
		->and($page)->toContain('archive.rrd_name > ?')
		->and($page)->toContain('if ($next_id === $range_end)')
		->and($page)->toContain('WHERE archive.local_data_id BETWEEN ? AND ?')
		->and($page)->toContain('FORCE INDEX (PRIMARY)')
		->and($page)->toContain('LEFT JOIN data_local AS dl')
		->and($page)->not->toContain('UNION ALL')
		->and($worker)->toContain('boost_fetch_archive_page(')
		->and($worker)->toContain('boost_limit_complete_timestamp_page($results, $max_rows)');
});

test('worker partitioning fails closed when distinct local data IDs share one RRD file', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_prepare_process_table(');

	expect($body)->toContain('GROUP BY pi.rrd_path')
		->and($body)->toContain('HAVING COUNT(DISTINCT pi.local_data_id) > 1')
		->and($body)->toContain('the archive was retained to prevent competing or out-of-order writers')
		->and($body)->toContain('admin_email(')
		->and($body)->toContain('return false;');
});

test('worker launch includes only staged non-empty ranges', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$stage  = boostLifecycleFunctionBody($source, 'function boost_prepare_process_table(');
	$launch = boostLifecycleFunctionBody($source, 'function boost_launch_children(');

	expect($stage)->toContain('cumulative_rows - source_rows')
		->and($launch)->toContain('GROUP BY process_handler')
		->and($launch)->toContain('foreach($ranges as $range)')
		->and($launch)->toContain('$launched++')
		->and($launch)->toContain('return $launched;')
		->and($launch)->not->toContain('for($i = 1; $i <= $processes; $i++)');
});

test('on-demand Boost defers oversized result sets without deleting them', function () use ($root) {
	$source = file_get_contents($root . '/lib/boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_process_poller_output_unlocked(');

	expect($body)->toContain('LIMIT \' . ($max_rows + 1)')
		->and($body)->toContain('if (cacti_sizeof($results) > $max_rows)')
		->and($body)->toContain('rows were retained for scheduled processing')
		->and(strpos($body, 'return -1;'))->toBeLessThan(strpos($body, 'DELETE FROM poller_output_boost'));
});

test('scheduled and on-demand parsers share cached data-source metadata', function () use ($root) {
	$boost  = file_get_contents($root . '/lib/boost.php');
	$poller = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($boost, 'function boost_process_poller_output_unlocked(');

	expect($boost)->toContain('function boost_get_unused_data_source_names(')
		->and($boost)->toContain('function boost_get_input_field_names(')
		->and($body)->toContain('boost_get_unused_data_source_names($local_data_id)')
		->and($body)->toContain('boost_get_input_field_names($item[\'local_data_id\'], true)')
		->and($poller)->toContain('boost_get_unused_data_source_names($item[\'local_data_id\'])')
		->and($poller)->toContain('boost_get_input_field_names($item[\'local_data_id\'], true)');
});

test('Boost takeover must confirm process death before shared table preparation', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_kill_running_processes(');

	expect($source)->toContain('if (!boost_kill_running_processes())')
		->and($body)->toContain('boost_child_process_still_running')
		->and($body)->toContain('microtime(true) + max(1, (int) $wait_seconds)')
		->and($body)->toContain('did not terminate before the takeover deadline')
		->and($body)->toContain('return false;');
});

test('legacy RRD locks stop at the configured Boost runtime deadline', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_process_local_data_ids(');

	expect($body)->toContain('microtime(true) + max(1, min(30, (int) $max_run_duration))')
		->and($body)->toContain('microtime(true) >= $lock_deadline')
		->and($body)->toContain('timed out acquiring the RRD lock')
		->and($body)->toContain('return -1;');
});

test('Boost runtime status uses a numeric start timestamp with legacy fallback', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_begin_master_run(');

	expect($body)->toContain("read_config_option('boost_poller_started')")
		->and($body)->toContain("set_config_option('boost_poller_started', \$start_time)")
		->and($body)->toContain('if (!$previous_start_time && substr_count($boost_poller_status, \'running\'))');
});

test('only the Boost master mutates global lifecycle status from a signal', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function sig_handler(');
	$child  = strpos($body, 'if ($child)');
	$status = strpos($body, "set_config_option('boost_poller_status'");
	$master = strpos($body, '} else {', $child);

	expect($child)->not->toBeFalse()
		->and($master)->not->toBeFalse()
		->and($status)->toBeGreaterThan($master);
});

test('legacy RRDtool locking acquires each data-source lock only once', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_process_local_data_ids(');

	$flush   = strpos($body, '$flush_ok   = boost_process_output');
	$release = strpos($body, 'SELECT RELEASE_LOCK', $flush);
	$acquire = strpos($body, 'SELECT GET_LOCK', $release);

	expect(substr_count($body, "SELECT GET_LOCK('boost.single_ds."))->toBe(1)
		->and($flush)->not->toBeFalse()
		->and($release)->toBeGreaterThan($flush)
		->and($acquire)->toBeGreaterThan($release)
		->and($body)->toContain("SELECT RELEASE_LOCK('boost.single_ds.\$current_lock')")
		->and($body)->toContain('if (!$flush_ok && boost_backend_circuit_open())');
});

test('malformed child statistics are ignored instead of iterated', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_log_statistics(');

	expect($body)->toContain("json_decode(\$stat['value'], true)")
		->and($body)->toContain('if (!is_array($stat))');
});

test('preparation and completion-record failures are externally visible', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$ui     = file_get_contents($root . '/utilities.php');

	expect($source)->toContain("set_config_option('boost_poller_status', 'failed - end time:'")
		->and($source)->toContain('Boost stopped at the oldest incomplete archive')
		->and($source)->toContain('Boost child could not record its completion status.')
		->and($source)->toContain('exit($completion_recorded === false || $rrd_updates < 0 ? 1 : 0)')
		->and($source)->toContain('exit($exit_code)')
		->and($ui)->toContain("read_config_option('boost_failure_queue_depth', true)")
		->and($ui)->toContain("__('Failed RRD Destinations Pending:')")
		->and($ui)->toContain("__('Sealed Boost Archives:')")
		->and($ui)->toContain('html_escape($failure_error)');
});

test('scheduled and on-demand archive ownership is serialized without blocking collectors', function () use ($root) {
	$boost  = file_get_contents($root . '/lib/boost.php');
	$poller = file_get_contents($root . '/poller_boost.php');
	$wrapper = boostLifecycleFunctionBody($boost, 'function boost_process_poller_output(');

	expect($boost)->toContain("\$lock_name = 'boost.archive.lifecycle'")
		->and($wrapper)->toContain('boost_acquire_archive_lifecycle_lock(0)')
		->and($wrapper)->toContain("read_config_option('boost_poller_started') > 0")
		->and($wrapper)->toContain('boost_acquire_rrd_path_lock($metadata[\'rrd_path\'])')
		->and($wrapper)->toContain('boost_release_rrd_path_lock($path_lock)')
		->and($wrapper)->toContain('boost_release_archive_lifecycle_lock($archive_lock)')
		->and($poller)->toContain('boost_acquire_archive_lifecycle_lock(30)')
		->and($poller)->toContain('boost_release_archive_lifecycle_lock($archive_lifecycle_lock)')
		->and($poller)->toContain('boost_kill_running_processes(30)')
		->and($poller)->toContain('RENAME TABLE poller_output_boost TO `$archive_table`, `$interim_table` TO poller_output_boost');
});

test('child identifiers are positive integers before reaching SQL and process state', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');

	expect($source)->toContain("preg_match('/^[1-9]\\d*$/D', \$value)")
		->and($source)->toContain('$child = (int) $value;');
});
