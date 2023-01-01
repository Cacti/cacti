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

/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

/* utility requires input parameters */
if (cacti_sizeof($parms) == 0) {
	print "ERROR: You must supply input parameters\n\n";
	display_help();

	exit(1);
}

$debug    = false;
$template = '';
$hostid   = '';

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--host-template':
			case '--host-template-id':
				$template = $value;

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
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();

				exit(1);
		}
	}
}

/* determine the hosts to reindex */
if (strtolower($host_id) == 'all') {
	$sql_where = '';
} elseif (is_numeric($host_id)) {
	$sql_where = ' WHERE id=' . $host_id;
} else {
	print "ERROR: You must specify either a host_id or 'all' to proceed.\n\n";
	display_help();

	exit(1);
}

/* determine data queries to rerun */
if (is_numeric($template) && $template > 0) {
	$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . " host_template_id=$template";
} else {
	print "ERROR: You must specify a Host Template to proceed.\n\n";
	display_help();

	exit(1);
}

/* verify that the host template is accurate */
$exists = db_fetch_cell_prepared('SELECT id
	FROM host_template
	WHERE id = ?',
	array($template));

if ($exists > 0) {
	$hosts = db_fetch_assoc("SELECT * FROM host $sql_where");

	if (cacti_sizeof($hosts)) {
		foreach ($hosts as $host) {
			print "NOTE: Updating Host '" . $host['description'] . "'\n";

			$snmp_queries = db_fetch_assoc_prepared('SELECT snmp_query_id
				FROM host_template_snmp_query
				WHERE host_template_id = ?',
				array($host['host_template_id']));

			if (cacti_sizeof($snmp_queries) > 0) {
				print "NOTE: Updating Data Queries. There were '" . cacti_sizeof($snmp_queries) . "' Found\n";

				foreach ($snmp_queries as $snmp_query) {
					print "NOTE: Updating Data Query ID '" . $snmp_query['snmp_query_id'] . "'\n";

					db_execute_prepared('INSERT IGNORE INTO host_snmp_query
						(host_id, snmp_query_id, reindex_method)
						VALUES (?, ?, ?)',
						array($host['id'], $snmp_query['snmp_query_id'], DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME));

					/* recache snmp data */
					run_data_query($host['id'], $snmp_query['snmp_query_id']);
				}
			}

			$graph_templates = db_fetch_assoc_prepared('SELECT graph_template_id
				FROM host_template_graph
				WHERE host_template_id = ?',
				array($host['host_template_id']));

			if (cacti_sizeof($graph_templates) > 0) {
				print "NOTE: Updating Graph Templates. There were '" . cacti_sizeof($graph_templates) . "' Found\n";

				foreach ($graph_templates as $graph_template) {
					db_execute_prepared('INSERT IGNORE INTO host_graph
						(host_id, graph_template_id)
						VALUES (?, ?)',
						array($host['id'], $graph_template['graph_template_id']));

					automation_hook_graph_template($host['id'], $graph_template['graph_template_id']);

					api_plugin_hook_function('add_graph_template_to_host', array('host_id' => $host['id'], 'graph_template_id' => $graph_template['graph_template_id']));
				}
			}
		}
	}
} else {
	print "ERROR: The selected Host Template does not exist, try --list-host-templates\n\n";

	exit(1);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();

	print "Cacti Retemplate Host Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help() {
	display_version();

	print "\nusage: host_update_template.php --host-id=[host-id|all] [--host-template=[ID]] [--debug]\n\n";
	print "A utility to update Cacti devices with the latest Device Template\n\n";
	print "Required:\n";
	print "    --host-id=host_id|all - The host_id to have templates reapplied 'all' to do all hosts\n";
	print "    --host-template=ID    - Which Host Template to Refresh\n\n";
	print "Optional:\n";
	print "    --debug               - Display verbose output during execution\n\n";
	print "List Options:\n";
	print "    --list-host-templates - Lists all available Host Templates\n\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		print('DEBUG: ' . $message . "\n");
	}
}
