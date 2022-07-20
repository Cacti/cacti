#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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
require_once($config['base_path'] . '/lib/api_automation_tools.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	$displayHosts 			= false;
	$displayGraphTemplates 	= false;
	$quietMode				= false;
	unset($host_id);
	unset($graph_template_id);

	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
		case '-d':
			$debug = true;

			break;
		case '--host-id':
			$host_id = trim($value);
			if (!is_numeric($host_id)) {
				print "ERROR: You must supply a valid host-id to run this script!\n";
				exit(1);
			}

			break;
		case '--graph-template-id':
			$graph_template_id = $value;
			if (!is_numeric($graph_template_id)) {
				print "ERROR: You must supply a numeric graph-template-id for all hosts!\n";
				exit(1);
			}

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
		case '--list-hosts':
			$displayHosts = true;
			break;
		case '--list-graph-templates':
			$displayGraphTemplates = true;
			break;
		case '--quiet':
			$quietMode = true;
			break;
		default:
			print "ERROR: Invalid Argument: ($arg)\n\n";
			display_help();
			exit(1);
		}
	}

	/* list options, recognizing $quiteMode */
	if ($displayHosts) {
		$hosts = getHosts();
		displayHosts($hosts, $quietMode);
		exit(0);
	}

	if ($displayGraphTemplates) {
		$graphTemplates = getGraphTemplates();
		displayGraphTemplates($graphTemplates, $quietMode);
		exit(0);
	}

	/*
	 * verify required parameters
	 * for update / insert options
	 */
	if (!isset($host_id)) {
		print "ERROR: You must supply a valid host-id for all hosts!\n";
		exit(1);
	}

	if (!isset($graph_template_id)) {
		print "ERROR: You must supply a valid data-query-id for all hosts!\n";
		exit(1);
	}

	/*
	 * verify valid host id and get a name for it
	 */
	$host_name = db_fetch_cell("SELECT hostname FROM host WHERE id = " . $host_id);
	if (!isset($host_name)) {
		print "ERROR: Unknown Host Id ($host_id)\n";
		exit(1);
	}

	/*
	 * verify valid graph template and get a name for it
	 */
	$graph_template_name = db_fetch_cell("SELECT name FROM graph_templates WHERE id = " . $graph_template_id);
	if (!isset($graph_template_name)) {
		print "ERROR: Unknown Graph Template Id ($graph_template_id)\n";
		exit(1);
	}

	/* check, if graph template was already associated */
	$exists_already = db_fetch_cell("SELECT host_id FROM host_graph WHERE graph_template_id=$graph_template_id AND host_id=$host_id");
	if ((isset($exists_already)) &&
		($exists_already > 0)) {
		print "ERROR: Graph Template is already associated for host: ($host_id: $host_name) - graph-template: ($graph_template_id: $graph_template_name)\n";
		exit(1);
	} else {
		db_execute("replace into host_graph (host_id,graph_template_id) values (" . $host_id . "," . $graph_template_id . ")");

		automation_hook_graph_template($host_id, $graph_template_id);

		api_plugin_hook_function('add_graph_template_to_host', array("host_id" => $host_id, "graph_template_id" => $graph_template_id));
	}

	if (is_error_message()) {
		print "ERROR: Failed to add this graph template for host: ($host_id: $host_name) - graph-template: ($graph_template_id: $graph_template_name)\n";
		exit(1);
	} else {
		print "Success: Graph Template associated for host: ($host_id: $host_name) - graph-template: ($graph_template_id: $graph_template_name)\n";
		exit(0);
	}
} else {
	display_help();
	exit(0);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Add Graph Template Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	print "\nusage: add_graph_template.php --host-id=[ID] --graph-template-id=[ID]\n";
	print "    [--quiet]\n\n";
	print "Required:\n";
	print "    --host-id             the numerical ID of the host\n";
	print "    --graph-template-id   the numerical ID of the graph template to be added\n\n";
	print "List Options:\n";
	print "    --list-hosts\n";
	print "    --list-graph-templates\n";
	print "    --quiet - batch mode value return\n\n";
}
