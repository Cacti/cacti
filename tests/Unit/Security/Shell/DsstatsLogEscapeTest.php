<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression: the path_dsstats_log setting is written into a shell redirect
 * when relaunching poller_dsstats.php, and must be escaped like the identical
 * path_rrdcheck_log redirect. An unescaped admin-set path could inject shell
 * metacharacters into the background command.
 */

$dsstatsSource = file_get_contents(dirname(__DIR__, 4) . '/lib/dsstats.php');

test('path_dsstats_log is wrapped in cacti_escapeshellarg in the log redirect', function () use ($dsstatsSource) {
	expect($dsstatsSource)->not->toBeFalse();
	// both the unix (>> ... 2>&1) and windows branches must escape the setting
	expect(substr_count($dsstatsSource, "cacti_escapeshellarg(read_config_option('path_dsstats_log'))"))->toBe(2);
	// the raw, unescaped redirect must be gone
	expect($dsstatsSource)->not->toContain("'/poller_dsstats.php >> ' . read_config_option('path_dsstats_log')");
});
