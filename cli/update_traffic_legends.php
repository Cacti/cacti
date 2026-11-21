#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

if (PHP_SAPI !== 'cli') {
	exit(1);
}

$apply = false;
$dry_run = false;
$batch_size = 100;
foreach (array_slice($_SERVER['argv'], 1) as $argument) {
	if ($argument === '--help' || $argument === '-h') {
		print "Usage: php cli/update_traffic_legends.php [--dry-run | --apply] [--batch-size=100]\n";
		print "Preview by default. Only recognised stock traffic layouts are changed.\n";
		exit(0);
	} elseif ($argument === '--apply') {
		$apply = true;
	} elseif ($argument === '--dry-run') {
		$dry_run = true;
	} elseif (preg_match('/^--batch-size=([0-9]+)$/', $argument, $match) && (int) $match[1] >= 1 && (int) $match[1] <= 1000) {
		$batch_size = (int) $match[1];
	} else {
		fwrite(STDERR, "Unknown or invalid argument: $argument\n");
		exit(1);
	}
}
if ($apply && $dry_run) {
	fwrite(STDERR, "Choose --apply or --dry-run, not both.\n");
	exit(1);
}

require __DIR__ . '/../include/cli_check.php';
require_once __DIR__ . '/../lib/traffic_legend.php';
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}
if ((int) db_fetch_cell_prepared('SELECT GET_LOCK(?, 0)', array('cacti_traffic_legends')) !== 1) {
	fwrite(STDERR, "Another traffic legend update is running.\n");
	exit(1);
}
$status = 0;
try {
	$counts = update_interface_traffic_legends($apply, $batch_size, static function ($tid, $gid, $changed) use ($apply) {
		$action = $changed ? ($apply ? 'Updated' : 'Would update') : 'Skipped (already updated or customised)';
		print "$action: template $tid, graph $gid\n";
	});
	print ($apply ? 'Updated: ' : 'Would update: ') . $counts['changed'] . '; skipped: ' . $counts['skipped'] . "\n";
} catch (Throwable $error) {
	fwrite(STDERR, $error->getMessage() . "\nStopped. Earlier graphs remain committed; rerun to resume.\n");
	$status = 1;
} finally {
	db_fetch_cell_prepared('SELECT RELEASE_LOCK(?)', array('cacti_traffic_legends'));
}
exit($status);
