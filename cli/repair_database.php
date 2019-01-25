#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug;

$debug   = false;
$form    = '';
$force   = false;
$dynamic = false;

if (cacti_sizeof($parms)) {
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
				$debug = true;
				break;
			case '--force':
				$force = true;
				break;
			case '--dynamic':
				$dynamic = true;
				break;
			case '-form':
			case '--form':
				$form = ' USE_FRM';
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
print "Repairing All Cacti Database Tables\n";

db_execute('UNLOCK TABLES');

$tables = db_fetch_assoc('SHOW TABLES FROM ' . $database_default);

if (cacti_sizeof($tables)) {
	foreach($tables AS $table) {
		print "Repairing Table -> '" . $table['Tables_in_' . $database_default] . "'";
		$status = db_execute('REPAIR TABLE ' . $table['Tables_in_' . $database_default] . $form);
		print ($status == 0 ? ' Failed' : ' Successful') . "\n";

		if ($dynamic) {
			print "Changing Table Row Format to Dynamic -> '" . $table['Tables_in_' . $database_default] . "'";
			$status = db_execute('ALTER TABLE ' . $table['Tables_in_' . $database_default] . ' ROW_FORMAT=DYNAMIC');
			print ($status == 0 ? ' Failed' : ' Successful') . "\n";
		}
	}
}

print "\nNOTE: Checking for Invalid Cacti Templates\n";

/* keep track of total rows */
$total_rows = 0;

/* remove invalid GPrint Presets from the Database, validated */
$rows = db_fetch_cell('SELECT count(*)
	FROM graph_templates_item
	LEFT JOIN graph_templates_gprint
	ON graph_templates_item.gprint_id=graph_templates_gprint.id
	WHERE graph_templates_gprint.id IS NULL
	AND graph_templates_item.gprint_id>0');

$total_rows += $rows;
if ($rows > 0) {
	if ($force) {
		db_execute('DELETE FROM graph_templates_item
			WHERE gprint_id NOT IN (SELECT id FROM graph_templates_gprint) AND gprint_id>0');
	}
	print "NOTE: $rows Invalid GPrint Preset Rows " . ($force ? 'removed from':'found in') . " Graph Templates\n";
}

/* remove invalid CDEF Items from the Database, validated */
$rows = db_fetch_cell("SELECT count(*)
	FROM cdef_items
	LEFT JOIN cdef
	ON cdef_items.cdef_id=cdef.id
	WHERE cdef.id IS NULL");

$total_rows += $rows;
if ($rows > 0) {
	if ($force) {
		db_execute('DELETE FROM cdef_items WHERE cdef_id NOT IN (SELECT id FROM cdef)');
	}
	print "NOTE: $rows Invalid CDEF Item Rows " . ($force ? 'removed from':'found in') . " Graph Templates\n";
}

/* remove invalid Data Templates from the Database, validated */
$rows = db_fetch_cell('SELECT count(*)
	FROM data_template_data
	LEFT JOIN data_input
	ON data_template_data.data_input_id=data_input.id
	WHERE data_input.id IS NULL');

$total_rows += $rows;
if ($rows > 0) {
	if ($force) {
		db_execute('DELETE FROM data_template_data WHERE data_input_id NOT IN (SELECT id FROM data_input)');
	}
	print "NOTE: $rows Invalid Data Input Rows " . ($force ? 'removed from':'found in') . " Data Templates\n";
}

/* remove invalid Data Input Fields from the Database, validated */
$rows = db_fetch_cell('SELECT count(*)
	FROM data_input_fields
	LEFT JOIN data_input
	ON data_input_fields.data_input_id=data_input.id
	WHERE data_input.id IS NULL');

$total_rows += $rows;
if ($rows > 0) {
	if ($force) {
		db_execute('DELETE FROM data_input_fields
			WHERE data_input_fields.data_input_id NOT IN (SELECT id FROM data_input)');

		update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
	}
	print "NOTE: $rows Invalid Data Input Field Rows " . ($force ? 'removed from':'found in') . " Data Templates\n";
}

/* --------------------------------------------------------------------*/

/* remove invalid Data Input Data Rows from the Database in two passes */
$rows = db_fetch_cell('SELECT count(*)
	FROM data_input_data
	LEFT JOIN data_template_data
	ON data_input_data.data_template_data_id=data_template_data.id
	WHERE data_template_data.id IS NULL');

$total_rows += $rows;
if ($rows > 0) {
	if ($force) {
		db_execute('DELETE FROM data_input_data
			WHERE data_input_data.data_template_data_id NOT IN (SELECT id FROM data_template_data)');
	}
	print "NOTE: $rows Invalid Data Input Data Rows based upon template mappings " . ($force ? 'removed from':'found in') . " Data Templates\n";
}

$rows = db_fetch_cell('SELECT count(*)
	FROM data_input_data
	LEFT JOIN data_input_fields
	ON data_input_fields.id=data_input_data.data_input_field_id
	WHERE data_input_fields.id IS NULL');

$total_rows += $rows;
if ($rows > 0) {
	if ($force) {
		db_execute('DELETE FROM data_input_data
			WHERE data_input_data.data_input_field_id NOT IN (SELECT id FROM data_input_fields)');
	}
	print "NOTE: $rows Invalid Data Input Data rows based upon field mappings " . ($force ? 'removed from':'found in') . " Data Templates\n";
}

if ($total_rows > 0 && !$force) {
	print "\nWARNING: Cacti Template Problems found in your Database.  Using the '--force' option will remove\n";
	print "the invalid records.  However, these changes can be catastrophic to existing data sources.  Therefore, you \n";
	print "should contact your support organization prior to proceeding with that repair.\n\n";
} elseif ($total_rows == 0) {
	print "NOTE: No Invalid Cacti Template Records found in your Database\n\n";
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Database Repair Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: repair_database.php [--dynamic] [--debug] [--force] [--form]\n\n";
	print "A utility designed to repair the Cacti database if damaged, and optionally repair any\n";
	print "corruption found in the Cacti databases various Templates.\n\n";
	print "Optional:\n";
	print "    --dynamic - Convert a table to Dynamic row format if available\n";
	print "    --form    - Force rebuilding the indexes from the database creation syntax.\n";
	print "    --force   - Remove Invalid Template records from the database.\n";
	print "    --debug   - Display verbose output during execution.\n\n";
}
