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
	return file_get_contents(CACTI_PATH_BASE . '/' . $path);
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
	expect($recovery)->not->toContain('DELETE FROM poller_output_boost WHERE time <=');
});

test('Scheduled Boost retains shard ownership after an RRD update failure', function () {
	$poller = boostSource('poller_boost.php');

	expect($poller)->toContain('function boost_process_output(int $local_data_id, array $outarray, string $rrd_path, array $rrd_tmplp, mixed $rrdtool_pipe) : bool');
	expect($poller)->toContain('if ($updates_ok && $results !== false)');
	expect($poller)->toContain('return $updates_ok && $results !== false ? cacti_sizeof($results) : -1;');
	expect($poller)->toContain('if ($pass_rows < 0)');
	expect($poller)->toContain('if ($total_rows == 0) {');
	expect($poller)->toContain('return 0;');
	expect(boostSource('lib/boost.php'))->toContain("return is_string(\$result) && \$result !== '' ? \$result : 'ERROR: RRDtool did not acknowledge the update';");
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

test('Remote hand-offs validate poller ownership before staging', function () {
	$boost    = boostSource('lib/boost.php');
	$recovery = boostSource('poller_recovery.php');

	expect($boost)->toContain('function boost_validate_poller_ownership(array $results, int $poller_id, mixed $conn = false) : bool');
	expect($boost)->toContain('WHERE poller_id = ?');
	expect($boost)->toContain('POLLER_ID > 1 && !boost_validate_poller_ownership');
	expect($recovery)->toContain('!boost_validate_poller_ownership($rows, $poller_id, $remote_db_cnn_id)');
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
	expect($recovery)->toContain("db_qstr(\$row['rrd_name'], \$remote_conn)");
});

test('Graph cache names are opaque and writes are atomically published', function () {
	$boost = boostSource('lib/boost.php');

	expect($boost)->toContain("hash_hmac('sha256', \$cache_key, \$secret) . '.png'");
	expect($boost)->toContain("tempnam(dirname(\$cache_file), '.boost-')");
	expect($boost)->toContain('if (!$flushed || !rename($temp_file, $cache_file))');
	expect($boost)->toContain('chmod($temp_file, 0640)');
	expect($boost)->not->toContain("get_selected_theme() . '_lgi_'");
});

test('Boost restores both the caller error handler and reporting level', function () {
	$boost = boostSource('lib/boost.php');

	expect(substr_count($boost, '$previous_error_reporting = error_reporting();'))->toBeGreaterThanOrEqual(4);
	expect(substr_count($boost, 'error_reporting($previous_error_reporting);'))->toBeGreaterThanOrEqual(8);
});
