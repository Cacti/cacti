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

require(__DIR__ . '/../include/cli_check.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/api_graph.php');

/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug;

$debug   = false;
$report  = false;
$remove  = false;
$columns = 80;

if (empty($github_actions) && $config['cacti_server_os'] == 'unix') {
	$stty  = shell_exec('stty size');
	$sizes = explode(' ', $stty);

	if (!empty($sizes[1])) {
		$columns = $sizes[1];
	}
}

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--debug':
				$debug = true;

				break;
			case '--report':
				$report = true;

				break;
			case '--remove':
				$remove = true;

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
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();

				exit(1);
		}
	}
}

if ($report && $remove) {
	print 'ERROR: The options --report and --remove are mutually exclusive' . PHP_EOL;
	display_help();

	exit(1);
}

if (!$report && !$remove) {
	$report = true;
}

print 'Running Query to find Broken Graphs.  This Query may run for some time depending the number of Graph in your System' . PHP_EOL;

$sql = "SELECT name, graph_template_id, graphs, local_graph_ids
	FROM (
		SELECT graph_template_id, GROUP_CONCAT(DISTINCT local_graph_id SEPARATOR ', ') AS local_graph_ids, COUNT(DISTINCT local_graph_id) AS graphs
		FROM graph_templates_item
		WHERE local_graph_id > 0
		AND graph_template_id > 0
		AND task_item_id > 0
		AND task_item_id NOT IN (
			SELECT id
			FROM data_template_rrd
			WHERE local_data_id > 0
		)
		GROUP BY graph_template_id
	) AS gti
	INNER JOIN graph_templates AS gt
	ON gt.id = gti.graph_template_id";

$entries = db_fetch_assoc($sql);

if (cacti_sizeof($entries)) {
	print 'There are ' . cacti_sizeof($entries) . ' Graph Templates with Broken Graphs.' . PHP_EOL;
} else {
	print 'There are no Graph Templates with Broken Graphs.' . PHP_EOL;
}

if (cacti_sizeof($entries)) {
	print '-------------------------------------------------------------------------------------' . PHP_EOL;

	if ($report) {
		print 'Broken Graph Report' . PHP_EOL;
	} else {
		print 'Broken Graph Report Removal' . PHP_EOL;
	}
	print '-------------------------------------------------------------------------------------' . PHP_EOL;

	foreach ($entries as $e) {
		if ($report) {
			printf('Graph Template: %s, Contains \'%s\' broken Graphs' . PHP_EOL, $e['name'], $e['graphs']);
			print 'Graphs:' . PHP_EOL;
			print '-------------------------------------------------------------------------------------' . PHP_EOL;
			print wordwrap($e['local_graph_ids'], $columns - 5, PHP_EOL) . PHP_EOL;
			print '-------------------------------------------------------------------------------------' . PHP_EOL;
		} else {
			print '-------------------------------------------------------------------------------------' . PHP_EOL;
			$local_graph_ids = explode(', ', $e['local_graph_ids']);
			printf('Started removing \'%s\' broken Graphs for Graph Template %s' . PHP_EOL, $e['graphs'], $e['name']);
			print '-------------------------------------------------------------------------------------' . PHP_EOL;

			if (cacti_sizeof($local_graph_ids)) {
				foreach ($local_graph_ids as $local_graph_id) {
					$title = get_graph_title_cache($local_graph_id);
					printf('Removing Graph %s, "%s"' . PHP_EOL, $title, $local_graph_id);
					$id                  = array();
					$id[$local_graph_id] = $local_graph_id;
					api_delete_graphs($id, '2');
				}
			}
			print '-------------------------------------------------------------------------------------' . PHP_EOL;
			printf('Completed removing Graphs for Graph Template %s' . PHP_EOL, $e['name']);
		}
	}
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Remove Broken Graphs Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*  display_help - displays the usage of the function */
function display_help() {
	display_version();

	print PHP_EOL . 'usage: remove_broken_graphs.php [--report | --remove] [-d|--debug]' . PHP_EOL . PHP_EOL;
	print 'A utility to remove broken graphs from Cacti.  A broken Graph is one that.' . PHP_EOL;
	print 'lacks Data Sources.  This can happen from time to time when working with and modifying templates.' . PHP_EOL;
	print 'It\'s important to periodically run this utility especially on larger systems.' . PHP_EOL . PHP_EOL;
	print 'Optional:' . PHP_EOL;
	print '--report  - Display the Graph Templates and Count of Broken Graphs' . PHP_EOL;
	print '--remove  - Remove the Broken Graphs as Reported using the --report Option' . PHP_EOL;
	print '--debug   - Display verbose output during execution' . PHP_EOL . PHP_EOL;
}

function debug($message) {
	global $debug;

	if ($debug) {
		print date('H:i:s') . ' DEBUG: ' . trim($message) . PHP_EOL;
	}
}
