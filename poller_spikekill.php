#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

$no_http_headers = true;

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
    die('<br><strong>This script is only meant to run at the command line.</strong>');
}

include('./include/global.php');

ini_set('memory_limit', '512M');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug, $start, $seed, $forcerun;

$debug          = FALSE;
$forcerun       = FALSE;
$templates      = FALSE;
$kills          = 0;

if (sizeof($parms)) {
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
				$debug = TRUE;
				break;
			case '--templates':
				$templates = $value;
				break;
			case '-f':
			case '--force':
				$forcerun = TRUE;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

echo "NOTE: SpikeKill Running\n";

if (!$templates) {
	$templates = db_fetch_cell("SELECT value FROM settings WHERE name='spikekill_templates'");
	$templates = explode(',', $templates);
} else {
	$templates = explode(',', $templates);
}

if (!sizeof($templates)) {
	print "ERROR: No valid Graph Templates selected\n\n";
	exit(1);
} else {
	foreach($templates as $template) {
		if (!is_numeric($template)) {
			print "ERROR: Graph Template '" . $template . "' Invalid\n\n";
			exit(1);
		}
	}
}

if (timeToRun()) {
	debug('Starting Spikekill Process');

	list($micro,$seconds) = explode(' ', microtime());
	$start   = $seconds + $micro;

	$graphs = kill_spikes($templates, $kills);

	list($micro,$seconds) = explode(' ', microtime());
	$end  = $seconds + $micro;

    $cacti_stats = sprintf(
        'Time:%01.4f ' .
        'Graphs:%s ' . 
		'Kills:%s',
        round($end-$start,2),
        $graphs,
		$kills);

    /* log to the database */
    db_execute_prepared('REPLACE INTO settings (name,value) VALUES ("stats_spikekill", ?)', array($cacti_stats));

    /* log to the logfile */
    cacti_log('SPIKEKILL STATS: ' . $cacti_stats , TRUE, 'SYSTEM');
}

echo "NOTE: SpikeKill Finished\n";

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
		echo 'DEBUG: ' . trim($message) . "\n";
	}
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

	if (sizeof($rrdfiles)) {
	foreach($rrdfiles as $f) {
		debug("Removing Spikes from '$f'");
		$response = exec(read_config_option('path_php_binary') . ' -q ' . 
			$config['base_path'] . '/cli/removespikes.php --rrdfile=' . $f . ($debug ? ' --debug':''));
		if (substr_count($response, 'Spikes Found and Remediated')) {
			$found++;
		}

		debug(str_replace('NOTE: ', '', $response));
	}
	}

	return sizeof($rrdfiles);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti SpikeKiller Batch Poller, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	echo "\nusage: poller_spikekill.php [--templates=N,N,...] [--force] [--debug]\n\n";
	echo "Cacti's SpikeKill batch removal poller.  This poller will remove spikes\n";
	echo "in Cacti's RRDfiles based upon the settings maintained in Cacti's database.\n\n";
	echo "Optional:\n";
	echo "    --templates=N,N,... - Only despike the templates provided.\n";
	echo "    --force             - Force running the despiking immediately.\n";
	echo "    --debug             - Display verbose output during execution.\n";
}
