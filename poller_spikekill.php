#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require(__DIR__ . '/include/cli_check.php');

ini_set('memory_limit', '-1');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug, $start, $seed, $forcerun;

$debug     = false;
$forcerun  = false;
$templates = false;
$kills     = 0;

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-d':
			case '--debug':
				$debug = true;
				break;
			case '--templates':
				$templates = $value;
				break;
			case '-f':
			case '--force':
				$forcerun = true;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit(0);
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit(0);
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit(1);
		}
	}
}

/* silently end if the registered process is still running, or process table missing */
if (!register_process_start('spikekill', 'master', 0, read_config_option('spikekill_timeout'))) {
	exit(0);
}

print "NOTE: SpikeKill Running" . PHP_EOL;

if (!$templates) {
	$templates = db_fetch_cell("SELECT value FROM settings WHERE name='spikekill_templates'");
	if ($templates != '') {
		$templates = explode(',', $templates);
	}
} else {
	$templates = explode(',', $templates);
}

if (!cacti_sizeof($templates)) {
	print "ERROR: No valid Graph Templates selected" . PHP_EOL . PHP_EOL;
	unregister_process('spikekill', 'master', 0);
	exit(1);
} else {
	foreach($templates as $template) {
		if (!is_numeric($template)) {
			print "ERROR: Graph Template '" . $template . "' Invalid" . PHP_EOL . PHP_EOL;
			unregister_process('spikekill', 'master', 0);
			exit(1);
		}
	}
}

if (timeToRun()) {
	debug('Starting Spikekill Process');

	$start   = microtime(true);

	$graphs = kill_spikes($templates, $kills);

	$purges = 0;
	if (read_config_option('spikekill_purge') > 0) {
		$purges = purge_spike_backups();
	}

	$end  = microtime(true);

    $cacti_stats = sprintf(
        'Time:%01.4f ' .
        'Graphs:%s ' .
        'Purges:%s ' .
		'Kills:%s',
        round($end-$start,2),
        $graphs,
        $purges,
		$kills);

    /* log to the database */
    db_execute_prepared('REPLACE INTO settings (name,value) VALUES ("stats_spikekill", ?)', array($cacti_stats));

    /* log to the logfile */
    cacti_log('SPIKEKILL STATS: ' . $cacti_stats , true, 'SYSTEM');
}

print "NOTE: SpikeKill Finished" . PHP_EOL;

unregister_process('spikekill', 'master', 0);

exit(0);

function timeToRun() {
	global $forcerun;

	$lastrun   = read_config_option('spikekill_lastrun');
	$frequency = read_config_option('spikekill_batch') * 3600;
	$basetime  = strtotime(read_config_option('spikekill_basetime'));
	$baseupper = 300;
	$baselower = $frequency - 300;
	$now       = time();

	debug("LastRun:'$lastrun', Frequency:'$frequency', BaseTime:'" . date('Y-m-d H:i:s', $basetime) . "', BaseUpper:'$baseupper', BaseLower:'$baselower', Now:'" . date('Y-m-d H:i:s', $now) . "'");
	if ($frequency > 0 && ($now - $lastrun > $frequency)) {
		debug("Frequency is '$frequency' Seconds");

		$nowfreq = $now % $frequency;
		debug("Now Frequency is '$nowfreq'");

		if ((empty($lastrun)) && ($nowfreq > $baseupper) && ($nowfreq < $baselower)) {
			debug('Time to Run');
			db_execute_prepared('REPLACE INTO settings (name,value) VALUES ("spikekill_lastrun", ?)', array(time()));
			return true;
		} elseif (($now - $lastrun > 3600) && ($nowfreq > $baseupper) && ($nowfreq < $baselower)) {
			debug('Time to Run');
			db_execute_prepared('REPLACE INTO settings (name,value) VALUES ("spikekill_lastrun", ?)', array(time()));
			return true;
		} else {
			debug('Not Time to Run');
			return false;
		}
	} elseif ($forcerun) {
		debug('Force to Run');
		db_execute_prepared('REPLACE INTO settings (name,value) VALUES ("spikekill_lastrun", ?', array(time()));
		return true;
	} else {
		debug('Not time to Run');
		return false;
	}
}

function debug($message) {
	global $debug;

	if ($debug) {
		print 'DEBUG: ' . trim($message) . PHP_EOL;
	}
}


function purge_spike_backups() {
	$directory = read_config_option('spikekill_backupdir');
	$retention = read_config_option('spikekil_backup');

	$purges = 0;

	if (empty($retention)) {
		return false;
	}

	$earlytime = time() - $retention;

	if ($directory != '' && is_dir($directory) && is_writable($directory)) {
		$files = array_diff(scandir($directory), array('.', '..'));

		if (cacti_sizeof($files)) {
			foreach($files as $file) {
				$filepath = $directory . '/' . $file;

				if (is_file($filepath) && strpos($filepath, 'rrd') !== false) {
					$mtime = filemtime($filepath);

					if ($mtime < $earlytime) {
						if (is_writable($filepath)) {
							unlink($filepath);
							$purges++;
						} else {
							cacti_log('Unable to remove ' . $filepath . ' due to write permissions', 'SPIKES');
						}
					}
				}
			}
		}
	}

	return $purges;
}

function kill_spikes($templates, &$found) {
	global $debug, $config;

	$rrdfiles = array_rekey(db_fetch_assoc('SELECT DISTINCT rrd_path
		FROM graph_templates AS gt
		INNER JOIN graph_templates_item AS gti
		ON gt.id=gti.graph_template_id
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id=dtr.id
		INNER JOIN poller_item AS pi ON pi.local_data_id=dtr.local_data_id
		WHERE gt.id IN (' . implode(',', $templates) . ')'), 'rrd_path', 'rrd_path');

	if (cacti_sizeof($rrdfiles)) {
		foreach($rrdfiles as $f) {
			debug("Removing Spikes from '$f'");

			$response = exec(cacti_escapeshellcmd(read_config_option('path_php_binary')) . ' -q ' .
				cacti_escapeshellarg($config['base_path'] . '/cli/removespikes.php') . ' --rrdfile=' . $f . ($debug ? ' --debug':''));

			if (substr_count($response, 'Spikes Found and Remediated')) {
				$found++;
			}

			debug(str_replace('NOTE: ', '', $response));
		}
	}

	return cacti_sizeof($rrdfiles);
}

/*  display_version - displays version information */
function display_version() {
	$version = CACTI_VERSION_TEXT;
	echo "Cacti SpikeKiller Batch Poller, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print PHP_EOL . "usage: poller_spikekill.php [--templates=N,N,...] [--force] [--debug]" . PHP_EOL . PHP_EOL;
	print "Cacti's SpikeKill batch removal poller.  This poller will remove spikes" . PHP_EOL;
	print "in Cacti's RRDfiles based upon the settings maintained in Cacti's database." . PHP_EOL . PHP_EOL;
	print "Optional:" . PHP_EOL;
	print "    --templates=N,N,... - Only despike the templates provided." . PHP_EOL;
	print "    --force             - Force running the despiking immediately." . PHP_EOL;
	print "    --debug             - Display verbose output during execution." . PHP_EOL;
}
