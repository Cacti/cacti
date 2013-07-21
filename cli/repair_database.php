<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

include(dirname(__FILE__) . "/../include/global.php");

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

global $debug;

$debug = FALSE;
$form  = "";
$force = FALSE;

foreach($parms as $parameter) {
	@list($arg, $value) = @explode("=", $parameter);

	switch ($arg) {
	case "-d":
	case "--debug":
		$debug = TRUE;
		break;
	case "--force":
		$force = TRUE;
		break;
	case "-form":
	case "--form":
		$form = " USE_FRM";
		break;
	case "-h":
	case "-v":
	case "-V":
	case "--version":
	case "--help":
		display_help();
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
		exit;
	}
}
echo "Repairing All Cacti Database Tables\n";

db_execute("UNLOCK TABLES");

$tables = db_fetch_assoc("SHOW TABLES FROM " . $database_default);

if (sizeof($tables)) {
	foreach($tables AS $table) {
		echo "Repairing Table -> '" . $table['Tables_in_' . $database_default] . "'";
		$status = db_execute("REPAIR TABLE " . $table['Tables_in_' . $database_default] . $form);
		echo ($status == 0 ? " Failed" : " Successful") . "\n";
	}
}

echo "\nNOTE: Checking for Invalid Cacti Templates\n";

/* keep track of total rows */
$total_rows = 0;

/* remove invalid consolidation function and RRA's */
$rows = db_fetch_cell("SELECT count(*) FROM rra_cf LEFT JOIN rra ON rra_cf.rra_id=rra.id WHERE rra.id IS NULL;");
$total_rows += $rows;
if ($rows > 0) {
	if ($force) db_execute("DELETE FROM rra_cf WHERE rra_id NOT IN (SELECT id FROM rra)");
	echo "NOTE: $rows Invalid Consolidation Function Rows " . ($force ? "Removed from":"Found in") . " Database\n";
}

/* remove invalid RRA's from the Database */
$rows = db_fetch_cell("SELECT count(*) FROM data_template_data_rra LEFT JOIN rra ON data_template_data_rra.rra_id=rra.id WHERE rra.id IS NULL");
$total_rows += $rows;
if ($rows > 0) {
	if ($force) db_execute("DELETE FROM data_template_data_rra WHERE rra_id NOT IN (SELECT id FROM rra)");
	echo "NOTE: $rows Invalid Data Template Data RRA Rows " . ($force ? "Removed from":"Found in") . " Database\n";
}

/* remove invalid GPrint Presets from the Database */
$rows = db_fetch_cell("SELECT count(*) FROM graph_templates_item LEFT JOIN graph_templates_gprint ON graph_templates_item.gprint_id=graph_templates_gprint.id WHERE graph_templates_gprint.id IS NULL AND graph_templates_item.gprint_id>0");
$total_rows += $rows;
if ($rows > 0) {
	if ($force) db_execute("DELETE FROM graph_templates_item WHERE gprint_id NOT IN (SELECT id FROM graph_templates_gprint) AND gprint_id>0");
	echo "NOTE: $rows Invalid GPrint Preset Rows " . ($force ? "Removed from":"Found in") . " Graph Templates\n";
}

/* remove invalid CDEF Items from the Database */
$rows = db_fetch_cell("SELECT count(*) FROM cdef_items LEFT JOIN cdef ON cdef_items.cdef_id=cdef.id WHERE cdef.id IS NULL");
$total_rows += $rows;
if ($rows > 0) {
	if ($force) db_execute("DELETE FROM cdef_items WHERE cdef_id NOT IN (SELECT id FROM cdef)");
	echo "NOTE: $rows Invalid CDEF Item Rows " . ($force ? "Removed from":"Found in") . " Graph Templates\n";
}

/* remove invalid Data Templates from the Database */
$rows = db_fetch_cell("SELECT count(*) FROM data_template_data LEFT JOIN data_input ON data_template_data.data_input_id=data_input.id WHERE data_input.id IS NULL");
$total_rows += $rows;
if ($rows > 0) {
	if ($force) db_execute("DELETE FROM data_template_data WHERE data_input_id NOT IN (SELECT id FROM data_input)");
	echo "NOTE: $rows Invalid Data Input Rows " . ($force ? "Removed from":"Found in") . " Data Templates\n";
}

/* remove invalid Data Input Fields from the Database */
$rows = db_fetch_cell("SELECT count(*) FROM data_input_fields LEFT JOIN data_input ON data_input_fields.data_input_id=data_input.id WHERE data_input.id IS NULL");
$total_rows += $rows;
if ($rows > 0) {
	if ($force) db_execute("DELETE FROM data_input_fields WHERE data_input_fields.data_input_id NOT IN (SELECT id FROM data_input)");
	echo "NOTE: $rows Invalid Data Input Field Rows " . ($force ? "Removed from":"Found in") . " Data Templates\n";
}

/* remove invalid Data Input Data Rows from the Database in two passes */
$rows = db_fetch_cell("SELECT count(*) FROM data_input_data LEFT JOIN data_template_data ON data_template_data.data_input_id=data_input_data.data_template_data_id WHERE data_template_data.data_input_id IS NULL AND data_template_data.data_input_id>0");
$total_rows += $rows;
if ($rows > 0) {
	if ($force) db_execute("DELETE FROM data_input_data WHERE data_input_data.data_template_data_id NOT IN (SELECT data_input_id FROM data_template_data)");
	echo "NOTE: $rows Invalid Data Input Data Rows " . ($force ? "Removed from":"Found in") . " Data Templates\n";
}
$rows = db_fetch_cell("SELECT count(*) FROM data_input_data LEFT JOIN data_input_fields ON data_input_fields.id=data_input_data.data_input_field_id WHERE data_input_fields.id IS NULL");
$total_rows += $rows;
if ($rows > 0) {
	if ($force) db_execute("DELETE FROM data_input_data WHERE data_input_data.data_input_field_id NOT IN (SELECT id FROM data_input_fields)");
	echo "NOTE: $rows Invalid Data Input Data Rows " . ($force ? "Removed from":"Found in") . " Data Templates\n";
}

if ($total_rows > 0 && !$force) {
	echo "\nWARNING: Serious Cacti Template Problems found in your Database.  Using the '--force' option will remove\n";
	echo "the invalid records.  However, these changes can be catastrophic to existing data sources.  Therefore, you \n";
	echo "should contact your support organization prior to proceeding with that repair.\n\n";
}elseif ($total_rows == 0) {
	echo "NOTE: No Invalid Cacti Template Records found in your Database\n\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	print "Cacti Database Repair Tool 1.1, Copyright 2004-2013 - The Cacti Group\n\n";
	print "usage: repair_database.php [--debug] [--force] [--form] [--help]\n\n";
	print "--form  - Force rebuilding the indexes from the database creation syntax\n";
	print "--force - Remove Invalid Records from the Database\n";
	print "--debug - Display verbose output during execution\n";
	print "--help  - Display this help message\n";
}
?>
