<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

function boostSource(string $path): string {
	$source = file_get_contents(CACTI_PATH_BASE . '/' . $path);

	if ($source === false) {
		throw new RuntimeException("Unable to read Boost source file: $path");
	}

	return $source;
}

test('Boost hand-off batches expose database acknowledgement', function () {
	$boost = boostSource('lib/boost.php');

	expect($boost)->toContain('function boost_flush_output_batch(array $value_tuples, mixed $conn = false) : bool');
	expect($boost)->toContain('$acknowledged = db_execute($sql_prefix . $out_buffer, true, $conn) !== false;');
	expect($boost)->toContain('$return_value = !boost_flush_output_batch($value_tuples, $conn);');
});

test('Recovery deletes only the exact rows acknowledged by the main collector', function () {
	$recovery = boostSource('poller_recovery.php');

	expect($recovery)->toContain('function recovery_delete_acknowledged_rows(array $rows, mixed $conn) : bool');
	expect($recovery)->toContain('function recovery_transfer_rows(array $rows, mixed $remote_conn, mixed $local_conn) : int|false');
	expect($recovery)->toContain('(local_data_id = ? AND rrd_name = ? AND time = ?)');
	expect($recovery)->toContain('if (!boost_flush_output_batch($sql_array, $remote_conn))');
	expect($recovery)->toContain('array_slice($rows, $offset, $transfer_chunk_size)');
	expect($recovery)->toContain('recovery_transfer_rows($chunk, $remote_db_cnn_id, $local_db_cnn_id)');
	expect($recovery)->toContain("ORDER BY time ASC, local_data_id ASC\n\t\t\t\tLIMIT %d', (int) \$record_limit)");
	expect($recovery)->not->toContain('DELETE FROM poller_output_boost WHERE time <=');
});

test('Recovery status SQL remains valid when ANSI_QUOTES is enabled', function () {
	$recovery = boostSource('poller_recovery.php');

	expect($recovery)->toContain('SET status=2')
		->and($recovery)->toContain('SET status = 5')
		->and($recovery)->toContain("VALUES (\\'recovery_pid\\', ?)")
		->and($recovery)->not->toContain("if (!\$transfer_failed) {\n\t\t// let the console know you are in online mode")
		->and($recovery)->not->toMatch('/(?:status\s*=|VALUES\s*\()\s*"/');
});

test('Scheduled Boost retains shard ownership after an RRD update failure', function () {
	$poller = boostSource('poller_boost.php');

	expect($poller)->toContain('function boost_process_output(int $local_data_id, array $outarray, string $rrd_path, array $rrd_tmplp, mixed $rrdtool_pipe) : bool');
	expect($poller)->toContain('if ($updates_ok && $results !== false)');
	expect($poller)->toContain('return $updates_ok && $results !== false ? cacti_sizeof($results) : -1;');
	expect($poller)->toContain('if ($pass_rows < 0)');
	expect($poller)->toContain('if ($total_rows == 0) {');
	expect($poller)->toContain('return 0;');
	expect($poller)->not->toContain('elseif ($rrd_updates == -1)');
	expect(boostSource('lib/boost.php'))->toContain("return is_string(\$result) && \$result !== '' ? \$result : 'ERROR: RRDtool did not acknowledge the update';");
});

test('Boost completion-table upgrades recover when the legacy table is missing', function () {
	$upgrade = boostSource('install/upgrades/1_3_0.php');

	expect($upgrade)->toContain("if (!db_table_exists('poller_output_boost_processes'))")
		->and($upgrade)->toContain('CREATE TABLE `poller_output_boost_processes`')
		->and($upgrade)->toContain('UNIQUE KEY `run_child` (`run_id`, `child_id`)');
});

test('Boost child completion and archive deletion are scoped to a parent run', function () {
	$poller = boostSource('poller_boost.php');
	$schema = boostSource('cacti.sql');

	expect($poller)->toContain('bin2hex(random_bytes(16));');
	expect($poller)->toContain("'--run-id=' . \$run_id");
	expect($poller)->toContain('function boost_completed_children(string $run_id) : int');
	expect($poller)->toContain('function boost_failed_children(string $run_id) : int');
	expect($poller)->toContain('if ($rrd_updates > 0 && $failed_children === 0)');
	expect($schema)->toContain('UNIQUE KEY `run_child` (`run_id`,`child_id`)');
	expect($schema)->toContain('`child_id` int(10) unsigned NOT NULL default 0');
});

test('direct Boost redirection does not stage the same rows twice', function () {
	$boost = boostSource('lib/boost.php');

	expect($boost)->toContain("if (read_config_option('boost_redirect') == 'on')")
		->and($boost)->toContain('cmd.php already staged these rows');
});

test('Archive discovery validates every dynamic table identifier', function () {
	$boost = boostSource('lib/boost.php');

	expect($boost)->toContain("preg_match('/^poller_output_boost_arch_\\d+$/D', \$table)");
	expect($boost)->toContain("array_filter(\$tableNames, 'boost_is_valid_archive_table')");
});

test('Remote tuples are quoted by their destination connection', function () {
	$cmd      = boostSource('cmd.php');
	$recovery = boostSource('poller_recovery.php');

	expect($cmd)->toContain("db_qstr(\$item['rrd_name'], \$poller_db_cnn_id)");
	expect($cmd)->toContain('db_qstr($output, $poller_db_cnn_id)');
	expect($cmd)->toContain("db_qstr('U', \$poller_db_cnn_id)");
	expect($cmd)->toContain("IFNULL(s.disabled, \\'\\') != \\'on\\'");
	expect($recovery)->toContain("db_qstr(\$row['rrd_name'], \$remote_conn)");
});

test('Graph cache names are opaque and writes are atomically published', function () {
	$boost = boostSource('lib/boost.php');

	expect($boost)->toContain("hash_hmac('sha256', \$cache_key, \$secret) . '.png'");
	expect($boost)->toContain("tempnam(dirname(\$cache_file), '.boost-')");
	expect($boost)->toContain("PHP_OS_FAMILY === 'Windows'");
	expect($boost)->toContain('boost_replace_cache_file_on_windows($temp_file, $cache_file)');
	expect($boost)->toContain('if (is_string($output) && strlen($output) > 10)');
	expect($boost)->toContain('if (!$flushed || !chmod($temp_file, 0644))');
	expect($boost)->toContain("read_config_option('boost_png_cache_secret', true)");
	expect($boost)->not->toContain("get_selected_theme() . '_lgi_'");
});

test('Boost restores both the caller error handler and reporting level', function () {
	$boost = boostSource('lib/boost.php');

	expect(substr_count($boost, '$previous_error_reporting = error_reporting();'))->toBeGreaterThanOrEqual(4);
	expect(substr_count($boost, 'error_reporting($previous_error_reporting);'))->toBeGreaterThanOrEqual(8);
});
