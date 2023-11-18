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
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');
require_once(CACTI_PATH_LIBRARY . '/template.php');

/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$execute = false;

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--execute':
				$execute = true;

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

if ($execute) {
	print "NOTE: Repairing All Duplicated Templates\n";
} else {
	print "NOTE: Performing Check of Templates\n";
}

/* repair data templates first */
if ($execute) {
	print "NOTE: Repairing Data Templates\n";
} else {
	print "NOTE: Performing Check of Data Templates\n";
}

$damaged_template_ids = db_fetch_assoc("SELECT DISTINCT data_template_id FROM data_template_rrd WHERE hash='' AND local_data_id=0");

if (cacti_sizeof($damaged_template_ids)) {
	foreach ($damaged_template_ids as $id) {
		$template_name = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $id['data_template_id']);
		print "NOTE: Data Template '$template_name' is Damaged and can be repaired\n";
	}

	$damaged_templates = db_fetch_assoc("SELECT * FROM data_template_rrd WHERE hash='' AND local_data_id=0");

	if (cacti_sizeof($damaged_templates)) {
		print "NOTE: -- Damaged Data Templates Objects Found is '" . cacti_sizeof($damaged_templates) . "'\n";

		if ($execute) {
			foreach ($damaged_templates as $template) {
				$hash = get_hash_data_template($template['local_data_template_rrd_id'], 'data_template_item');
				db_execute("UPDATE data_template_rrd SET hash='$hash' WHERE id=" . $template['id']);
			}
		}
	}
} else {
	print "NOTE: No Damaged Data Templates Found\n";
}

/* reset the array */
$damaged_templates = array();

/* repair graph templates */
if ($execute) {
	print "NOTE: Repairing Graph Templates\n";
} else {
	print "NOTE: Performing Check of Graph Templates\n";
}

$damaged_template_ids = db_fetch_assoc("SELECT DISTINCT graph_template_id FROM graph_template_input WHERE hash=''");

if (cacti_sizeof($damaged_template_ids)) {
	foreach ($damaged_template_ids as $id) {
		$template_name = db_fetch_cell('SELECT name FROM graph_templates WHERE id=' . $id['graph_template_id']);
		print "NOTE: Graph Template '$template_name' is Damaged and can be repaired\n";
	}

	$damaged_templates = db_fetch_assoc("SELECT * FROM graph_template_input WHERE hash=''");

	if (cacti_sizeof($damaged_templates)) {
		print "NOTE: -- Damaged Graph Templates Objects Found is '" . cacti_sizeof($damaged_templates) . "'\n";

		if ($execute) {
			foreach ($damaged_templates as $template) {
				$hash = get_hash_graph_template(0, 'graph_template_input');
				db_execute("UPDATE graph_template_input SET hash='$hash' WHERE id=" . $template['id']);
			}
		}
	}
} else {
	print "NOTE: No Damaged Graph Templates Found\n";
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Database Template Repair Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/* display_help - displays the usage of the function */
function display_help() {
	display_version();

	print "\nusage: repair_templates.php [--execute]\n\n";
	print "A utility designed to repair any damaged Cacti Graph and Data Templates.  That lacked a hash.\n";
	print "This utility should not need to be used in any modern Cacti install.\n\n";
	print "Optional:\n";
	print "    --execute  - Perform the repair.  Otherwise check for errors.\n";
}
