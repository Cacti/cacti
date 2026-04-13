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

// Original script is located here https://forums.cacti.net/viewtopic.php?t=35816, but this one was modified quite a lot

require(__DIR__ . '/../include/cli_check.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');
require_once(CACTI_PATH_LIBRARY . '/template.php');

// switch to main database for cli's
if (POLLER_ID > 1) {
	db_switch_remote_to_main();
}

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

$execute  = false;
$show_sql = false;

unset($host_id);
unset($graph_template_id);
unset($data_template_id);

foreach ($parms as $parameter) {
	if (str_contains($parameter, '=')) {
		[$arg, $value] = explode('=', $parameter, 2);
	} else {
		$arg   = $parameter;
		$value = '';
	}

	switch ($arg) {
		case '--execute':
			$execute = true;

			break;
		case '--show-sql':
			$show_sql = true;

			break;
		case '--host-id':
			$host_id = trim($value);

			if (!is_numeric($host_id)) {
				print 'ERROR: You must supply a valid host-id to run this script!' . PHP_EOL;

				exit(1);
			}

			break;
		case '--graph-template-id':
			$graph_template_id = $value;

			if (!is_numeric($graph_template_id)) {
				print 'ERROR: You must supply a numeric graph-template-id!' . PHP_EOL;

				exit(1);
			}

			break;
		case '--data-template-id':
			$data_template_id = $value;

			if (!is_numeric($data_template_id)) {
				print 'ERROR: You must supply a numeric data-template-id!' . PHP_EOL;

				exit(1);
			}

			break;
		case '--version':
		case '-v':
		case '-V':
			display_version();

			exit(0);
		case '--help':
		case '-h':
		case '-H':
			display_help();

			exit(0);

		default:
			print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
			display_help();

			exit(1);
	}
}

if (!$show_sql && !$execute) {
	display_help();

	exit(1);
}

if (!isset($data_template_id)) {
	print 'ERROR: You must supply a valid data-template-id!' . PHP_EOL;

	exit(1);
}

if (!isset($graph_template_id)) {
	print 'ERROR: You must supply a valid graph-template-id!' . PHP_EOL;

	exit(1);
}

if ($execute) {
	print 'NOTE: Repairing Graphs' . PHP_EOL;
} else {
	print 'NOTE: Performing Check of Graphs' . PHP_EOL;
}

// Get all graphs for supplied graph template
$graph = db_fetch_assoc('SELECT *
	FROM graph_local
	WHERE ' . (!isset($host_id) ? '' : 'host_id=' . $host_id . ' AND ') . ' graph_template_id=' . $graph_template_id . '');

if (cacti_sizeof($graph)) {
	if (!$show_sql) {
		print PHP_EOL;
		print 'Corrupted graphs:' . PHP_EOL;
	}

	foreach ($graph as $g) {
		$graph_templates_item = [];
		$task_item_id         = 0;

		// Get datasource for supplied data template for current host
		$ds = db_fetch_assoc('SELECT * FROM data_local where host_id=' . $g['host_id'] . ' and data_template_id=' . $data_template_id);

		if (!cacti_sizeof($ds)) {
			continue;
		}
		$ds = $ds[0];

		// Get rrd for found datasource
		$rrd_data = db_fetch_assoc('SELECT * FROM data_template_rrd where local_data_id=' . $ds['id']);

		if (!cacti_sizeof($rrd_data)) {
			print 'Could not get correct rrd id for datasource=' . $ds['id'] . PHP_EOL;

			continue;
		}

		/*
		// Here we will find graph items that should point to our data template
		// Get templated rrd id for given data template
		select id from data_template_rrd where local_data_template_rrd_id=0 and local_data_id=0 and data_template_id=520
		// Get templated graph->rrd association
		select id from graph_templates_item where local_graph_template_item_id=0 and local_graph_id=0 and task_item_id=
		// Get graph associations which corresponds to supplied data template
		select id from graph_templates_item where local_graph_id=$g["id"] and local_graph_template_item_id in
		// But I'm too lazy to write such a lot of code, so let's better make one long query below
		*/

		$graph_templates_items_wrong = db_fetch_assoc_prepared('SELECT id, task_item_id
			FROM graph_templates_item
			WHERE task_item_id != ?
			AND graph_template_id = ?
			AND local_graph_id = ?
			AND local_graph_template_item_id IN (
				SELECT id
				FROM graph_templates_item
				WHERE local_graph_template_item_id = 0
				AND local_graph_id=0
				AND task_item_id = (
					SELECT id
					FROM data_template_rrd
					WHERE local_data_template_rrd_id = 0
					AND local_data_id = 0
					AND data_template_id = ?
				)
			)', [$rrd_data[0]['id'], $graph_template_id, $g['id'], $data_template_id]);

		if (!cacti_sizeof($graph_templates_items_wrong)) {
			// Everything correct here.
			continue;
		} else {
			foreach ($graph_templates_items_wrong as $graph_templates_item_wrong) {
				// Here is a list of graph_templates_item ids to be fixed and their wrong task_item_id
				$graph_templates_item[] = $graph_templates_item_wrong['id'];
				$task_item_id[]         = $graph_templates_item_wrong['task_item_id'];
			}
		}

		print 'Host ' . $g['host_id'] .
			', Graph ' . $g['id'] .
			', Graph Item ' . implode(',',$graph_templates_item) .
			', Task Item ID ' . implode(',',$task_item_id) . '->' . $rrd_data[0]['id'] . PHP_EOL;

		$query = 'UPDATE graph_templates_item SET task_item_id =' . $rrd_data[0]['id'] . ' WHERE task_item_id != ' . $rrd_data[0]['id'] . ' AND graph_template_id = ' . $graph_template_id . ' AND local_graph_id=' . $g['id'] . ' AND id IN (' . implode(',', $graph_templates_item) . ')';

		if ($show_sql) {
			print $query . ';' . PHP_EOL;
		}

		if ($execute) {
			db_execute($query);
		}
	}
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Graph Repair Tool, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays the usage of the function
 *
 * @return void
 */
function display_help() : void {
	print 'usage: repair_graphs.php [--host-id=ID] --data-template-id=[ID]' . PHP_EOL;
	print '	--graph-template-id=[ID] [--show-sql] [--execute]' . PHP_EOL . PHP_EOL;

	print 'Cacti utility for repairing graph<->datasource relationship via a command line interface.' . PHP_EOL . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print '--execute - Perform the repair' . PHP_EOL;
	print '--show-sql - Show SQL lines for the repair (optional)' . PHP_EOL;
	print '--host-id=id - The host_id to repair or leave empty to process all hosts' . PHP_EOL;
	print '--data-template-id=id - The numerical ID of the data template to be fixed' . PHP_EOL;
	print '--graph-template-id=id - The numerical ID of the graph template to be fixed' . PHP_EOL . PHP_EOL;
}
