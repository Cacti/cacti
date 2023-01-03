#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

require(__DIR__ . '/../include/cli_check.php');

ini_set('max_execution_time', '0');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug = false;
$local = false;

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-d':
			case '--debug':
				$debug = true;

				break;
			case '--local':
				$local = true;

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

if (!$local && $config['poller_id'] > 1) {
	db_switch_remote_to_main();

	print 'NOTE: Fixing MediumInt Columns for Main Database' . PHP_EOL;
} else {
	print 'NOTE: Fixing MediumInt Columns for Local Database' . PHP_EOL;
}

$total = database_fix_mediumint_columns();

print "NOTE: Column widths adjusted on $total Tables!" . PHP_EOL;

function database_fix_mediumint_columns() {
	global $database_default;

	$total = 0;

	// Known Tables
	$tables = array(
		'data_input_data' => 'data_template_data_id',

		'data_template_data' => 'id, local_data_template_data_id, local_data_id',
		'data_template_rrd'  => 'id, local_data_template_rrd_id, local_data_id',

		'graph_local' => 'id',
		'data_local'  => 'id',

		'data_source_purge_action'       => 'local_data_id',
		'data_source_purge_temp'         => 'local_data_id',
		'data_source_stats_daily'        => 'local_data_id',
		'data_source_stats_hourly'       => 'local_data_id',
		'data_source_stats_hourly_cache' => 'local_data_id',
		'data_source_stats_hourly_last'  => 'local_data_id',
		'data_source_stats_monthly'      => 'local_data_id',
		'data_source_stats_weekly'       => 'local_data_id',
		'data_source_stats_yearly'       => 'local_data_id',

		'graph_templates_graph'     => 'id, local_graph_id, local_graph_template_graph_id',
		'graph_template_input_defs' => 'graph_template_item_id',
		'graph_templates_item'      => 'id, local_graph_template_item_id, local_graph_id, task_item_id',
		'graph_tree_items'          => 'local_graph_id',

		'poller_item'            => 'local_data_id',
		'poller_output'          => 'local_data_id',
		'poller_output_boost'    => 'local_data_id',
		'poller_output_realtime' => 'local_data_id',

		'settings_tree'        => 'graph_tree_item_id',
		'snmp_query_graph_rrd' => 'data_template_rrd_id'
	);

	$known_columns['graph_id'] = 'graph_id';
	$known_columns['data_id']  = 'data_id';

	foreach ($tables as $table => $columns) {
		$columns = explode(',', $columns);

		$sql = 'ALTER TABLE ' . $table;
		$i   = 0;

		foreach ($columns as $c) {
			$c = trim($c);

			$attribs = database_get_column_attribs($table, $c);

			if (cacti_sizeof($attribs)) {
				if (strpos($attribs['Type'], 'mediumint') === false) {
					if (strpos($attribs['Type'], 'int(10) unsigned') !== false) {
						debug("Column $c in Table $table already converted.");

						continue;
					}
				}

				if (strtolower($attribs['Extra']) == 'auto_increment') {
					$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $c . ' int(10) unsigned NOT NULL AUTO_INCREMENT';
				} else {
					if ($c != 'id') {
						$known_columns[$c] = $c;
					}

					if ($attribs['Default'] != '') {
						$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $c . ' int(10) unsigned NOT NULL default "' . $attribs['Default'] . '"';
					} elseif ($attribs['Null'] == 'NO') {
						$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $c . ' int(10) unsigned NOT NULL';
					} else {
						$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $c . ' int(10) unsigned DEFAULT NULL';
					}
				}

				$i++;
			} else {
				debug("ERROR: Attributes missing for $table and column $c.");
			}
		}

		if ($i > 0) {
			debug("Updating Table $table.");
			db_execute($sql);
			$total++;
		}
	}

	$other_tables = db_fetch_assoc('SHOW TABLES');

	foreach ($other_tables as $t) {
		$table   = $t['Tables_in_' . $database_default];
		$columns = array();

		//print "Checking $table" . PHP_EOL;

		if (!array_key_exists($table, $tables)) {
			$columns = array_rekey(
				db_fetch_assoc('SHOW COLUMNS FROM ' . $table),
				'Field', array('Type', 'Null', 'Key', 'Default', 'Extra')
			);

			foreach ($columns as $field => $attribs) {
				if (array_key_exists($field, $known_columns)) {
					if (strpos($attribs['Type'], 'mediumint') === false) {
						if (strpos($attribs['Type'], 'int(10) unsigned') !== false) {
							debug("Column $field in Table $table already converted.");

							continue;
						}
					}

					if (strtolower($attribs['Extra']) == 'auto_increment') {
						$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $field . ' int(10) unsigned NOT NULL AUTO_INCREMENT';
					} else {
						if ($attribs['Default'] != '') {
							$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $field . ' int(10) unsigned NOT NULL default "' . $attribs['Default'] . '"';
						} elseif ($attribs['Null'] == 'NO') {
							$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $field . ' int(10) unsigned NOT NULL';
						} else {
							$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $field . ' int(10) unsigned DEFAULT NULL';
						}
					}

					debug("Updating Table $table.");
					db_execute($sql);
					$total++;
				}
			}
		}
	}

	return $total;
}

function database_get_column_attribs($table, $column) {
	return db_fetch_row("SHOW COLUMNS FROM $table LIKE '$column'");
}

function debug($string) {
	global $debug;

	if ($debug) {
		print 'DEBUG: ' . trim($string) . PHP_EOL;
	}
}

function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Fix Database Range Issue, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help() {
	display_version();
	print 'usage: fix_mediumint.php [--debug]' . PHP_EOL . PHP_EOL;
	print 'Options:' . PHP_EOL;
	print '--debug    - Display verbose output during execution' . PHP_EOL;
	print '--local    - Perform the action on the Remote Data Collector if run from there' . PHP_EOL . PHP_EOL;
	print 'This utility is used to increase the size of key Cacti columns to accomodate' . PHP_EOL;
	print 'systems with over a million graphs and that have been in service for years.' . PHP_EOL;
	print 'After some long amount of time, Cacti can run out of auto_increment fields.' . PHP_EOL;
}
