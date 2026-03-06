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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require(__DIR__ . '/../include/cli_check.php');
require_once(CACTI_PATH_LIBRARY . '/api_automation_tools.php');
require_once(CACTI_PATH_LIBRARY . '/api_automation.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/api_graph.php');
require_once(CACTI_PATH_LIBRARY . '/api_device.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/snmp.php');
require_once(CACTI_PATH_LIBRARY . '/sort.php');
require_once(CACTI_PATH_LIBRARY . '/template.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

ini_set('max_execution_time', '0');

// switch to main database for cli's
if (POLLER_ID > 1) {
	db_switch_remote_to_main();
}

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

// utility requires input parameters
if (cacti_sizeof($parms) == 0) {
	print 'ERROR: You must supply input parameters' . PHP_EOL . PHP_EOL;
	display_help();

	exit(1);
}

$debug             = false;
$host_template_id  = '';
$host_id           = '';
$params            = [];

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--host-template':
			case '--host-template-id':
				$host_template_id = $value;

				break;
			case '--host-id':
				$host_id = $value;

				break;
			case '--list-host-templates':
				displayHostTemplates(getHostTemplates());

				exit(0);
			case '-d':
			case '--debug':
				$debug = true;

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

// determine the hosts to reindex
if (cacti_strtolower($host_id) == 'all') {
	$sql_where = '';
} elseif ($host_id > 0) {
	$sql_where = ' WHERE id = ?';
	$params[]  = $host_id;
} else {
	print "ERROR: You must specify either a host_id or 'all' to proceed." . PHP_EOL . PHP_EOL;
	display_help();

	exit(1);
}

// determine data queries to rerun
if ($host_template_id > 0) {
	$sql_where .= ($sql_where != '' ? ' AND' : 'WHERE') . ' host_template_id = ?';
	$params[] = $host_template_id;
} else {
	print 'ERROR: You must specify a Host Template to proceed.' . PHP_EOL . PHP_EOL;
	display_help();

	exit(1);
}

// verify that the host template is accurate
$exists = db_fetch_cell_prepared('SELECT id
	FROM host_template
	WHERE id = ?',
	[$host_template_id]);

if ($exists > 0) {
	$hosts = db_fetch_assoc_prepared("SELECT * FROM host $sql_where", $params);

	if (cacti_sizeof($hosts)) {
		foreach ($hosts as $host) {
			print "NOTE: Updating Host '" . $host['description'] . "'" . PHP_EOL;

			$snmp_queries = db_fetch_assoc_prepared('SELECT snmp_query_id
				FROM host_template_snmp_query
				WHERE host_template_id = ?',
				[$host['host_template_id']]);

			if (cacti_sizeof($snmp_queries) > 0) {
				print "NOTE: Updating Data Queries. There were '" . cacti_sizeof($snmp_queries) . "' Found" . PHP_EOL;

				foreach ($snmp_queries as $snmp_query) {
					print "NOTE: Updating Data Query ID '" . $snmp_query['snmp_query_id'] . "'" . PHP_EOL;

					db_execute_prepared('INSERT IGNORE INTO host_snmp_query
						(host_id, snmp_query_id, reindex_method)
						VALUES (?, ?, ?)',
						[$host['id'], $snmp_query['snmp_query_id'], DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME]);

					// recache snmp data
					run_data_query($host['id'], $snmp_query['snmp_query_id']);
				}
			}

			if ($host['id'] > 0) {
				object_cache_get_totals('device_state', $host['id']);
			}

			$graph_templates = db_fetch_assoc_prepared('SELECT graph_template_id
				FROM host_template_graph
				WHERE host_template_id = ?',
				[$host['host_template_id']]);

			if (cacti_sizeof($graph_templates) > 0) {
				print "NOTE: Updating Graph Templates. There were '" . cacti_sizeof($graph_templates) . "' Found" . PHP_EOL;

				foreach ($graph_templates as $graph_template) {
					db_execute_prepared('INSERT IGNORE INTO host_graph
						(host_id, graph_template_id)
						VALUES (?, ?)',
						[$host['id'], $graph_template['graph_template_id']]);

					automation_hook_graph_template($host['id'], $graph_template['graph_template_id']);

					api_plugin_hook_function('add_graph_template_to_host', ['host_id' => $host['id'], 'graph_template_id' => $graph_template['graph_template_id']]);
				}
			}

			if ($host['id'] > 0) {
				object_cache_get_totals('device_state', $host['id'], true);
				object_cache_update_totals('diff');
			}
		}
	}
} else {
	print 'ERROR: The selected Host Template does not exist, try --list-host-templates' . PHP_EOL . PHP_EOL;

	exit(1);
}

function debug(string $message) : void {
	global $debug;

	if ($debug) {
		print('DEBUG: ' . trim($message) . PHP_EOL);
	}
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();

	print "Cacti Retemplate Host Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays the usage of the function
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL;
	print 'usage: host_update_template.php --host-id=[host-id|all] [--host-template=[ID]] [--debug]' . PHP_EOL . PHP_EOL;

	print 'A utility to update Cacti devices with the latest Device Template' . PHP_EOL . PHP_EOL;

	print 'Required:' . PHP_EOL;
	print "    --host-id=host_id|all - The host_id to have templates reapplied 'all' to do all hosts" . PHP_EOL;
	print '    --host-template=ID    - Which Host Template to Refresh' . PHP_EOL . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print '    --debug               - Display verbose output during execution' . PHP_EOL;

	print 'List Options:' . PHP_EOL;
	print '    --list-host-templates - Lists all available Host Templates' . PHP_EOL;
}
