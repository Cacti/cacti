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

	expect($source)->toContain('boost_processes_running() > 0 || boost_completed_children($run_id) < $expected_children')
		->and($source)->toContain('boost_failed_children($run_id)')
		->and($source)->toContain('$run_complete       = $failed_children == 0 && $completed_children >= $expected_children;');
});

test('archive tables are dropped only after every child succeeds', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$start  = strpos($source, '$run_complete       =');
	$drop   = strpos($source, 'DROP TABLE IF EXISTS `$table`', $start);

	expect($start)->not->toBeFalse()
		->and($drop)->not->toBeFalse();

	$segment = substr($source, $start, $drop - $start);

	expect($segment)->toContain('if (!$run_complete)')
		->and($segment)->toContain('if ($rrd_updates > 0 && $run_complete)')
		->and($segment)->toContain("set_config_option('boost_last_run_time', \$last_run_time)");
});

test('child completion is idempotent within a run and records failures', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_failed_children(');

	expect($source)->toContain('ON DUPLICATE KEY UPDATE status = VALUES(status)')
		->and($source)->toContain('array($run_id, $child, $rrd_updates)')
		->and($body)->toContain('WHERE run_id = ?')
		->and($body)->toContain('CAST(status AS SIGNED) < 0');
});

test('failed RRD updates retain both scheduled shards and on-demand rows', function () use ($root) {
	$poller = file_get_contents($root . '/poller_boost.php');
	$boost  = file_get_contents($root . '/lib/boost.php');

	expect($poller)->toContain('if ($updates_ok && $results !== false)')
		->and($poller)->toContain('return $updates_ok && $results !== false ? cacti_sizeof($results) : -1;')
		->and($boost)->toContain('if ($updates_ok && cacti_sizeof($results))')
		->and($boost)->toContain('return $updates_ok ? cacti_sizeof($results) : -1;');
});

test('dynamic archive identifiers are validated before select delete analyze and drop use', function () use ($root) {
	$boost  = file_get_contents($root . '/lib/boost.php');
	$poller = file_get_contents($root . '/poller_boost.php');

	expect($boost)->toContain("preg_match('/^poller_output_boost_arch_\\d+$/D', \$table)")
		->and($boost)->toContain("array_filter(\$tableNames, 'boost_is_valid_archive_table')")
		->and($poller)->toContain('if (boost_is_valid_archive_table($table))')
		->and($poller)->toContain('DROP TABLE IF EXISTS `$table`');
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
	$body   = boostLifecycleFunctionBody($source, 'function boost_prepare_process_table(');

	expect($body)->toContain('failed to create its interim output table')
		->and($body)->toContain('failed to rotate its output table')
		->and($body)->toContain('failed to create its process-distribution table')
		->and($body)->toContain('failed to stage archive data-source identifiers')
		->and($body)->toContain('failed to assign data sources to a child process');
});

test('scheduled Boost pages are hard bounded and advance run-scoped primary-key cursors', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_process_local_data_ids(');

	expect($body)->toContain('LIMIT \' . ($max_rows + 1)')
		->and($body)->toContain('boost_limit_complete_timestamp_page($results, $max_rows)')
		->and($body)->toContain('SET cursor_time = ?, cursor_rrd_name = ?')
		->and($body)->toContain('WHERE run_id = ?')
		->and($body)->not->toContain('WHERE local_data_id <= ?');
});

test('on-demand Boost defers oversized result sets without deleting them', function () use ($root) {
	$source = file_get_contents($root . '/lib/boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_process_poller_output(');

	expect($body)->toContain('LIMIT \' . ($max_rows + 1)')
		->and($body)->toContain('if (cacti_sizeof($results) > $max_rows)')
		->and($body)->toContain('rows were retained for scheduled processing')
		->and(strpos($body, 'return -1;'))->toBeLessThan(strpos($body, 'DELETE FROM poller_output_boost'));
});

test('scheduled and on-demand parsers share cached data-source metadata', function () use ($root) {
	$boost  = file_get_contents($root . '/lib/boost.php');
	$poller = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($boost, 'function boost_process_poller_output(');

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
		->and($body)->toContain('cacti_process_still_running')
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
	$body   = boostLifecycleFunctionBody($source, 'function boost_prepare_process_table(');

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
		->and($body)->toContain('if (!$flush_ok)');
});

test('malformed child statistics are ignored instead of iterated', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_log_statistics(');

	expect($body)->toContain("json_decode(\$stat['value'], true)")
		->and($body)->toContain('if (!is_array($stat))');
});

test('preparation and completion-record failures are externally visible', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');

	expect($source)->toContain("set_config_option('boost_poller_status', 'failed - end time:'")
		->and($source)->toContain('Boost preparation failed; no child processes were launched.')
		->and($source)->toContain('Boost child could not record its completion status.')
		->and($source)->toContain('exit($completion_recorded === false || $rrd_updates < 0 ? 1 : 0)')
		->and($source)->toContain('exit($exit_code)');
});

test('child identifiers are positive integers before reaching SQL and process state', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');

	expect($source)->toContain("preg_match('/^[1-9]\\d*$/D', \$value)")
		->and($source)->toContain('$child = (int) $value;');
});
