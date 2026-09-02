<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// A pid read from the processes table may have been recycled by an unrelated
// process after a crash, so every kill_running_processes() variant has to
// confirm the pid is still ours before signalling it (issue #7425).
test('every kill_running_processes signals only a process confirmed still running', function () {
	$root = dirname(__DIR__, 3);

	$files = [
		'/lib/dsstats.php',
		'/lib/rrdcheck.php',
		'/poller_boost.php',
		'/poller_commands.php',
		'/cli/float_rrdfiles.php',
		'/cli/poller_reindex_hosts.php',
		'/cli/rebuild_poller_cache.php',
	];

	foreach ($files as $file) {
		$source = file_get_contents($root . $file);

		expect($source)->not->toBeFalse();

		$offset = 0;

		while (($at = strpos($source, "posix_kill(\$p['pid'], SIGTERM)", $offset)) !== false) {
			$preceding = substr($source, max(0, $at - 400), min(400, $at));

			expect($preceding)->toContain(
				'cacti_process_still_running',
				'unguarded posix_kill in ' . $file
			);

			$offset = $at + 1;
		}
	}
});
