<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_resources(): void {
	global $remote_db_cnn_id, $database_sessions, $database_hostname, $database_port, $database_default;
	$type             = 'cov_' . bin2hex(random_bytes(6));
	$relative         = 'plugins/' . $type;
	$directory        = CACTI_PATH_BASE . '/' . $relative;
	$previousRemote   = $remote_db_cnn_id ?? null;
	$remote_db_cnn_id = $database_sessions["$database_hostname:$database_port:$database_default"];
	library_expect(mkdir($directory, 0700), true, 'Replication fixture owns its destination directory');
	$valid = '<?php return 17;';
	$path  = $directory . '/worker.php';

	try {
		library_expect(file_put_contents($path, $valid), strlen($valid), 'Replication begins with a valid owned script');
		cache_in_path($directory, $type);
		$row = db_fetch_row_prepared('SELECT * FROM poller_resource_cache WHERE path = ?', [$relative . '/worker.php']);
		library_expect(base64_decode($row['contents'], true), $valid, 'Source cache contains the original script bytes');
		library_expect($row['md5sum'], md5($valid), 'Source cache digest identifies the exact script');
		cache_in_path($directory, $type);
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_resource_cache WHERE path = ?', [$relative . '/worker.php']), 1, 'Unchanged replication is idempotent');

		foreach (['<?php invalid syntax {' => false, '<?php return 23;' => true] as $contents => $accepted) {
			db_execute_prepared('UPDATE poller_resource_cache SET contents = ?, md5sum = ? WHERE id = ?', [base64_encode($contents), md5($contents), $row['id']]);
			set_config_option('md5dirsum_' . $type, 'force fixture comparison');
			ob_start();

			try {
				resource_cache_out($type, ['path' => $directory, 'recursive' => true]);
			} finally {
				ob_end_clean();
			}
			library_expect(file_get_contents($path), $accepted ? $contents : $valid, 'Replication rejects invalid PHP and accepts a corrected retry');
		}
		$script = '<?php if (($argv[1] ?? "") === "fail") { fwrite(STDERR, "fixture failed"); exit(7); } echo "0";';
		library_expect(file_put_contents($path, $script), strlen($script), 'Poller execution fixture writes its complete owned producer');
		$command = cacti_escapeshellarg(PHP_BINARY) . ' ' . cacti_escapeshellarg($path);
		library_expect(exec_poll($command), '0', 'A zero measurement remains zero at the poller boundary');
		library_expect(exec_poll($command . ' fail'), 'U', 'A failed producer with no stdout yields an unknown sample');
		$task = 'coverage_' . bin2hex(random_bytes(5));

		try {
			register_process($task, 'expired', 1, 0, 1);
			db_execute_prepared('UPDATE processes SET started = ? WHERE tasktype = ?', ['2000-01-01 00:00:00', $task]);
			library_expect(register_process_start($task, 'expired', 1, 1), false, 'Invalid expired PID cannot be signaled or silently replaced');
			library_expect(timeout_kill_registered_processes($task, 'expired', 1, 0), true, 'Expired absent process can be removed without a signal');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM processes WHERE tasktype = ?', [$task]), 0, 'Expired process cleanup removes only the owned registry record');
		} finally {
			db_execute_prepared('DELETE FROM processes WHERE tasktype = ?', [$task]);
		}
	} finally {
		db_execute_prepared('DELETE FROM poller_resource_cache WHERE resource_type = ?', [$type]);
		db_execute_prepared('DELETE FROM settings WHERE name = ?', ['md5dirsum_' . $type]);
		unset($GLOBALS['config'][OPTIONS_CLI]['md5dirsum_' . $type]);
		$remote_db_cnn_id = $previousRemote;

		if (is_file($path)) {
			unlink($path);
		}
		rmdir($directory);
	}
}
