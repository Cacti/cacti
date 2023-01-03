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
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/api_graph.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');

/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	$host_template_ids   = array();
	$host_ids            = array();
	$graph_template_ids  = array();
	$regex               = array();
	$graph_ids           = array();

	$graphTemplates      = getGraphTemplates();
	$hostTemplates       = getHostTemplates();
	$all                 = false;
	$force               = false;
	$list                = false;
	$preserve            = false;

	$listHosts           = false;
	$listGraphTemplates  = false;
	$listHostTemplates   = false;

	$quietMode          = false;

	$shortopts = 'VvHh';

	$longopts = array(
		'host-id::',
		'graph-type::',
		'graph-template-id::',
		'host-template-id::',
		'graph-id::',
		'graph-regex::',
		'all',
		'preserve',
		'quiet',

		'list',
		'list-hosts',
		'list-host-templates',
		'list-graph-templates',
		'force',
		'version',
		'help'
	);

	$options = getopt($shortopts, $longopts);

	foreach ($options as $arg => $value) {
		switch($arg) {
			case 'graph-regex':
				if (!is_array($value)) {
					$value = array($value);
				}

				$regex = $value;

				foreach ($value as $item) {
					if (!validate_is_regex($item)) {
						print "ERROR: Regex specified '$item', is not a valid Regex!" . PHP_EOL;

						exit(1);
					}
				}

				break;
			case 'graph-template-id':
				if (!is_array($value)) {
					$value = array($value);
				}

				$graph_template_ids = $value;

				break;
			case 'host-template-id':
				if (!is_array($value)) {
					$value = array($value);
				}

				$host_template_ids = $value;

				break;
			case 'graph-id':
				if (!is_array($value)) {
					$value = array($value);
				}

				$graph_ids = $value;

				break;
			case 'host-id':
				if (!is_array($value)) {
					$value = array($value);
				}

				$host_ids = $value;

				break;
			case 'all':
				$all = true;

				break;
			case 'list':
				$list = true;

				break;
			case 'list-hosts':
				$listHosts = true;

				break;
			case 'list-graph-templates':
				$listGraphTemplates = true;

				break;
			case 'list-host-templates':
				$listHostTemplates = true;

				break;
			case 'force':
				$force = true;

				break;
			case 'version':
			case 'V':
			case 'v':
				display_version();

				exit(0);
			case 'help':
			case 'H':
			case 'h':
				display_help();

				exit(0);

			default:
				print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
		}
	}
} else {
	display_help();

	exit(0);
}

if ($list && $force) {
	print 'The --list and --force options are mutually exclusive.  Pick one or the other.' . PHP_EOL;

	exit(1);
}

if (cacti_sizeof($host_template_ids)) {
	foreach ($host_template_ids as $id) {
		if (!is_numeric($id) || $id <= 0) {
			print "FATAL: Host Template ID $id is invalid" . PHP_EOL;

			exit(1);
		}
	}
}

if (cacti_sizeof($graph_template_ids)) {
	foreach ($graph_template_ids as $id) {
		if (!is_numeric($id) || $id <= 0) {
			print "FATAL: Graph Template ID $id is invalid" . PHP_EOL;

			exit(1);
		}
	}
}

if (cacti_sizeof($host_ids)) {
	foreach ($host_ids as $id) {
		if (!is_numeric($id) || $id <= 0) {
			print "FATAL: Host ID $id is invalid" . PHP_EOL;

			exit(1);
		}
	}
}

if (cacti_sizeof($graph_ids)) {
	foreach ($graph_ids as $id) {
		if (!is_numeric($id) || $id <= 0) {
			print "FATAL: Graph ID $id is invalid" . PHP_EOL;

			exit(1);
		}
	}
}

if ($listHosts) {
	$hosts = getHosts($host_template_ids);

	displayHosts($hosts, $quietMode);

	exit(0);
}

if ($listHostTemplates) {
	$hostTemplates = getHostTemplates();

	displayHostTemplates($hostTemplates, $quietMode);

	exit(0);
}

if ($listGraphTemplates) {
	$graphTemplates = getGraphTemplatesByHostTemplate($host_template_ids);

	displayGraphTemplates($graphTemplates, $quietMode);

	exit(0);
} else {
	$sql_where  = 'WHERE gl.id > 0';
	$all_option = true;

	if (cacti_sizeof($host_ids) && $all === false) {
		$sql_where .= ' AND gl.host_id IN (' . implode(',', $host_ids). ')';
		$all_option = false;
	}

	if (cacti_sizeof($host_template_ids) && $all === false) {
		$sql_where .= ' AND h.host_template_id IN (' . implode(',', $host_template_ids). ')';
		$all_option = false;
	}

	if (cacti_sizeof($graph_template_ids) && $all === false) {
		$sql_where .= ' AND gl.graph_template_id IN (' . implode(',', $graph_template_ids). ')';
		$all_option = false;
	}

	if (cacti_sizeof($regex) && $all === false) {
		$sql_where .= ' AND (';
		$sql_cwhere = '';

		foreach ($regex as $r) {
			$sql_cwhere .= ($sql_cwhere == '' ? '':' OR ') . 'title_cache RLIKE "' . $r . '"';
		}

		$sql_where .= $sql_cwhere . ')';
		$all_option = false;
	}

	if ($all_option && $all === false && $list === false) {
		print 'ERROR: The options specified will remove all graphs.  To do this you must use the --all option.  Exiting' . PHP_EOL;

		exit(1);
	}

	$graphs = db_fetch_assoc("SELECT gl.id, gtg.title_cache
		FROM graph_local AS gl
		INNER JOIN graph_templates_graph AS gtg
		ON gl.id=gtg.local_graph_id
		INNER JOIN host AS h
		ON h.id = gl.host_id
		$sql_where");

	if ($graphs != false && cacti_sizeof($graphs)) {
		print 'There are ' . cacti_sizeof($graphs) . ' Graphs to Remove.' . (!$force ? '  Use the --force option to remove these Graphs.':'');

		if ($list) {
			print PHP_EOL . "ID\tGraphName" . PHP_EOL;

			foreach ($graphs as $graph) {
				print $graph['id'] . "\t" . $graph['title_cache'] . PHP_EOL;
			}
		} elseif ($force) {
			$local_graph_ids = array_rekey($graphs, 'id', 'id');

			if ($preserve) {
				print '  Data Sources will be preserved.' . PHP_EOL;
				$delete_type = 1;
			} else {
				print '  Data Sources will be removed if possible.' . PHP_EOL;
				$delete_type = 2;
			}

			api_delete_graphs($local_graph_ids, $delete_type);

			print 'Delete Operation Completed' . PHP_EOL;
		} else {
			print PHP_EOL . '  Use the --list option to view the list of Graphs' . PHP_EOL;
		}
	} else {
		print 'No matching Graphs found.' . PHP_EOL;

		exit(1);
	}
}

exit(0);

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Remove Graphs Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print PHP_EOL . 'usage: remove_graphs.php --graph-template-id=ID [--host-template-id=ID' . PHP_EOL;
	print '    [--host-id=ID] [--graph-regex=R]' . PHP_EOL;
	print '    [--force] [--preserve]' . PHP_EOL . PHP_EOL;

	print 'Cacti utility for removing Graphs through the command line.' . PHP_EOL . PHP_EOL;

	print 'Options:' . PHP_EOL;
	print '    --graph-template-id=ID  Mandatory list of Graph Templates.' . PHP_EOL;
	print '    --host-template-id=ID   Optional list of Device Templates.' . PHP_EOL;
	print '    --host-id=ID            Optional list of Device IDs.' . PHP_EOL;
	print '    --graph-id=ID           Optional list of Graphs.' . PHP_EOL;
	print '    --graph-regex=R         Optional Graph name regular expression.' . PHP_EOL;
	print '    --all                   Remove all Graphs.  Ignore other settings.' . PHP_EOL;
	print '    --force                 Actually remove the Graphs, dont just list.' . PHP_EOL;
	print '    --preserve              Preserve the Data Sources.  Default is to remove.' . PHP_EOL . PHP_EOL;

	print 'By default, you must provide from one to many graph-template-id.  Device Template IDs' . PHP_EOL;
	print 'Device IDs and the regular expression are optional.  If you wish to specify multiple' . PHP_EOL;
	print 'IDs, just repeat the parameter ex: --host-template-id=X --host-template-id=Y' . PHP_EOL . PHP_EOL;

	print 'By default, this utility will only report on the number of Graphs that will be removed.  If you' . PHP_EOL;
	print 'provide the --force option, the Graphs will actually be removed.  If you use the --list option' . PHP_EOL;
	print 'each of the Graphs to be removed, will be listed.  Options --list and --force are' . PHP_EOL;
	print 'mutually exclusive.' . PHP_EOL . PHP_EOL;

	print 'List Options:' . PHP_EOL;
	print '    --list' . PHP_EOL;
	print '    --list-hosts [--host-template-id=ID]' . PHP_EOL;
	print '    --list-graph-templates [--host-template-id=ID]' . PHP_EOL;
	print '    --list-host-templates' . PHP_EOL . PHP_EOL;
}
