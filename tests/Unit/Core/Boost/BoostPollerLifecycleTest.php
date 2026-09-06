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
		->and($body)->toContain('failed to reset its process-distribution table')
		->and($body)->toContain('failed to stage archive data-source identifiers')
		->and($body)->toContain('failed to assign data sources to a child process');
});

test('legacy RRDtool locking acquires each data-source lock only once', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');
	$body   = boostLifecycleFunctionBody($source, 'function boost_process_local_data_ids(');

	expect(substr_count($body, "SELECT GET_LOCK('boost.single_ds."))->toBe(1);
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
		->and($source)->toContain('exit($completion_recorded === false ? 1 : 0)');
});

test('child identifiers are positive integers before reaching SQL and process state', function () use ($root) {
	$source = file_get_contents($root . '/poller_boost.php');

	expect($source)->toContain("preg_match('/^[1-9]\\d*$/D', \$value)")
		->and($source)->toContain('$child = (int) $value;');
});
