<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for #8574: RRDcheck did not delete old findings when Boost
 * was enabled.
 *
 * When Boost is active, poller_rrdcheck.php's rrdcheck_master_handler() skips
 * its periodic rollup ("Boost will handle the Periodic Roll-up Cycle") and the
 * handoff runs through rrdcheck_boost_bottom() in lib/rrdcheck.php instead --
 * poller_boost.php calls that function directly rather than launching
 * poller_rrdcheck.php --type=bmaster. The original fix added the retention
 * DELETE to the unreachable 'bmaster' switch case in poller_rrdcheck.php, so
 * Boost runs still never purged and old rrdcheck rows accumulated forever.
 *
 * The fix moves the retention DELETE into rrdcheck_boost_bottom(), after its
 * children finish writing findings and alongside the existing BOOST statistics
 * logging (BOOST, not HOURLY, so the bchild counters are aggregated/cleared).
 */

$rrdcheckLibPath    = __DIR__ . '/../../../../lib/rrdcheck.php';
$pollerRrdcheckPath = __DIR__ . '/../../../../poller_rrdcheck.php';

if (!function_exists('rrdcheck_boost_purge_extract_function')) {
	function rrdcheck_boost_purge_extract_function(string $contents, string $signature) : string {
		$func_pos = strpos($contents, $signature);

		if ($func_pos === false) {
			return '';
		}

		$func_end = strpos($contents, "\nfunction ", $func_pos + 1);

		if ($func_end === false) {
			$func_end = strlen($contents);
		}

		return substr($contents, $func_pos, $func_end - $func_pos);
	}
}

$rrdcheck_retention_delete = "db_execute('DELETE FROM rrdcheck WHERE test_date < DATE_SUB(NOW(), INTERVAL 7 DAY)');";

test('rrdcheck_boost_bottom purges old findings on the Boost handoff', function () use ($rrdcheckLibPath, $rrdcheck_retention_delete) {
	$contents  = file_get_contents($rrdcheckLibPath);
	$func_body = rrdcheck_boost_purge_extract_function($contents, 'function rrdcheck_boost_bottom()');

	expect($func_body)->not->toBe('');
	expect($func_body)->toContain($rrdcheck_retention_delete);
});

test('rrdcheck_boost_bottom purges only after its children finish writing findings', function () use ($rrdcheckLibPath, $rrdcheck_retention_delete) {
	$contents  = file_get_contents($rrdcheckLibPath);
	$func_body = rrdcheck_boost_purge_extract_function($contents, 'function rrdcheck_boost_bottom()');

	$drain_pos  = strpos($func_body, 'while ($running = rrdcheck_processes_running(\'bmaster\'))');
	$delete_pos = strpos($func_body, $rrdcheck_retention_delete);

	expect($drain_pos)->not->toBeFalse();
	expect($delete_pos)->not->toBeFalse();
	expect($drain_pos)->toBeLessThan($delete_pos);
});

test('rrdcheck_boost_bottom logs BOOST statistics so bchild counters are aggregated and cleared', function () use ($rrdcheckLibPath) {
	$contents  = file_get_contents($rrdcheckLibPath);
	$func_body = rrdcheck_boost_purge_extract_function($contents, 'function rrdcheck_boost_bottom()');

	expect($func_body)->toContain("rrdcheck_log_statistics('BOOST');");
	expect($func_body)->not->toContain("rrdcheck_log_statistics('HOURLY');");
});

test('the unreachable bmaster switch case does not carry the retention purge', function () use ($pollerRrdcheckPath, $rrdcheck_retention_delete) {
	// rrdcheck_launch_children('bmaster') forks poller_rrdcheck.php --type=bchild,
	// never --type=bmaster, so the 'bmaster' case here never runs during a Boost
	// cycle. The purge must live in rrdcheck_boost_bottom(), not here.
	$contents = file_get_contents($pollerRrdcheckPath);

	$bmaster_pos = strpos($contents, "case 'bmaster':");
	$bchild_pos  = strpos($contents, "case 'bchild':");

	expect($bmaster_pos)->not->toBeFalse();
	expect($bchild_pos)->not->toBeFalse();

	$bmaster_case = substr($contents, $bmaster_pos, $bchild_pos - $bmaster_pos);

	expect($bmaster_case)->not->toContain($rrdcheck_retention_delete);
});
