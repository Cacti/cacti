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
		print "Update stock interface traffic legends to show Max average and Max peak.\n";
		print "\n";
		print "Why use this command?\n";
		print "  Stock traffic graphs can show peak lines whose highest values differ\n";
		print "  from the value labelled \"Maximum\" in the legend. That legend reports\n";
		print "  the maximum of the averaged series, not the MAX series used by the\n";
		print "  peak line.\n";
		print "\n";
		print "  This command labels them separately as \"Max average\" and \"Max peak\",\n";
		print "  making the legend easier to compare with the plotted lines. It changes\n";
		print "  the legends, not how traffic is collected or stored. Neither value is\n";
		print "  an instantaneous measurement between polls.\n";
		print "\n";
		print "Usage:\n";
		print "  php cli/update_traffic_legends_max_peak.php [options]\n";
		print "\n";
		print "Options:\n";
		print "  --dry-run          Preview changes without saving (default).\n";
		print "  --apply            Apply changes to recognised templates and graphs.\n";
		print "  --batch-size=N     Read graph IDs in batches of 1-1000 (default: 100).\n";
		print "  --help, -h         Show this help.\n";
		print "\n";
		print "Examples:\n";
		print "  php cli/update_traffic_legends_max_peak.php\n";
		print "  php cli/update_traffic_legends_max_peak.php --apply\n";
		print "\n";
		print "Unsupported customised layouts and already-updated graphs are skipped.\n";
		print "RRD data and history are unchanged. Use either --dry-run or --apply,\n";
		print "not both.\n";
		print "\n";
		print "Back up your database and review the preview before using --apply.\n";
		print "Do not edit or import the affected templates while this command runs.\n";
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
