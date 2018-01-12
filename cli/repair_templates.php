#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

$no_http_headers = true;

include(dirname(__FILE__) . '/../include/global.php');
include_once('../lib/utility.php');
include_once('../lib/template.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$execute = false;

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
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

if ($execute) {
	echo "NOTE: Repairing All Duplicated Templates\n";
} else {
	echo "NOTE: Performing Check of Templates\n";
}

/* repair data templates first */
if ($execute) {
	echo "NOTE: Repairing Data Templates\n";
} else {
	echo "NOTE: Performing Check of Data Templates\n";
}

$damaged_template_ids = db_fetch_assoc("SELECT DISTINCT data_template_id FROM data_template_rrd WHERE hash='' AND local_data_id=0");
if (sizeof($damaged_template_ids)) {
	foreach($damaged_template_ids as $id) {
		$template_name = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $id['data_template_id']);
		echo "NOTE: Data Template '$template_name' is Damaged and can be repaired\n";
	}

	$damaged_templates = db_fetch_assoc("SELECT * FROM data_template_rrd WHERE hash='' AND local_data_id=0");
	if (sizeof($damaged_templates)) {
		echo "NOTE: -- Damaged Data Templates Objects Found is '" . sizeof($damaged_templates) . "'\n";
		if ($execute) {
			foreach($damaged_templates as $template) {
				$hash = get_hash_data_template($template['local_data_template_rrd_id'], 'data_template_item');
				db_execute("UPDATE data_template_rrd SET hash='$hash' WHERE id=" . $template['id']);
			}
		}
	}
} else {
	echo "NOTE: No Damaged Data Templates Found\n";
}

/* reset the array */
$damaged_templates = array();

/* repair graph templates */
if ($execute) {
	echo "NOTE: Repairing Graph Templates\n";
} else {
	echo "NOTE: Performing Check of Graph Templates\n";
}

$damaged_template_ids = db_fetch_assoc("SELECT DISTINCT graph_template_id FROM graph_template_input WHERE hash=''");
if (sizeof($damaged_template_ids)) {
	foreach($damaged_template_ids as $id) {
		$template_name = db_fetch_cell('SELECT name FROM graph_templates WHERE id=' . $id['graph_template_id']);
		echo "NOTE: Graph Template '$template_name' is Damaged and can be repaired\n";
	}

	$damaged_templates = db_fetch_assoc("SELECT * FROM graph_template_input WHERE hash=''");
	if (sizeof($damaged_templates)) {
		echo "NOTE: -- Damaged Graph Templates Objects Found is '" . sizeof($damaged_templates) . "'\n";
		if ($execute) {
			foreach($damaged_templates as $template) {
				$hash = get_hash_graph_template(0, 'graph_template_input');
				db_execute("UPDATE graph_template_input SET hash='$hash' WHERE id=" . $template['id']);
			}
		}
	}
} else {
	echo "NOTE: No Damaged Graph Templates Found\n";
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Database Template Repair Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/* display_help - displays the usage of the function */
function display_help () {
	display_version();

	echo "\nusage: repair_templates.php [--execute]\n\n";
	echo "A utility designed to repair any damaged Cacti Graph and Data Templates.  That lacked a hash.\n";
	echo "This utility should not need to be used in any modern Cacti install.\n\n";
	echo "Optional:\n";
	echo "    --execute  - Perform the repair.  Otherwise check for errors.\n";
}
