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
require_once(__DIR__ . '/../lib/template.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug;

$debug   = false;
$form    = '';
$force   = false;
$rtables = false;
$dynamic = false;
$local   = false;

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
			case '--tables':
				$rtables = true;
				break;
			case '--force':
				$force = true;
				break;
			case '--dynamic':
				$dynamic = true;
				break;
			case '--local':
				$local = true;
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
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit(1);
		}
	}
}

print '------------------------------------------------------------------------' . PHP_EOL;

if (!$local && $config['poller_id'] > 1) {
	db_switch_remote_to_main();

	print "NOTE: Repairing Tables for Main Database" . PHP_EOL;
} else {
	print "NOTE: Repairing Tables for Local Database" . PHP_EOL;
}

$tables = db_fetch_assoc('SHOW TABLES FROM ' . $database_default);

if ($rtables) {
	printf('NOTE: Repairing All %s Cacti Database Tables' . PHP_EOL, cacti_sizeof($tables));

	db_execute('UNLOCK TABLES');

	$tables = db_fetch_assoc('SHOW TABLES FROM ' . $database_default);

	if (cacti_sizeof($tables)) {
		foreach($tables AS $table) {
			print "Repairing Table '" . $table['Tables_in_' . $database_default] . "'";
			$status = db_execute('REPAIR TABLE ' . $table['Tables_in_' . $database_default] . ' QUICK' . $form);
			print ($status == 0 ? ' Failed' : ' Successful') . PHP_EOL;

			if ($dynamic) {
				print "Changing Table Row Format to Dynamic '" . $table['Tables_in_' . $database_default] . "'";
				$status = db_execute('ALTER TABLE ' . $table['Tables_in_' . $database_default] . ' ROW_FORMAT=DYNAMIC');
				print ($status == 0 ? ' Failed' : ' Successful') . PHP_EOL;
			}
		}
	}
} else {
	print 'NOTE: Skipping Data Table Physical Repair' . PHP_EOL;
	printf('NOTE: %s Cacti Tables would be checked/repaired if using --tables option.' . PHP_EOL, cacti_sizeof($tables));
}

print PHP_EOL . '------------------------------------------------------------------------' . PHP_EOL;
print 'Simple Checks.  Automatically repair if Found' . PHP_EOL . PHP_EOL;

print 'NOTE: Repairing some possibly corrupted Data Query IDs and Indexes.' . PHP_EOL;

db_execute('UPDATE graph_local AS gl
	INNER JOIN graph_templates_item AS gti
	ON gti.local_graph_id = gl.id
	INNER JOIN data_template_rrd AS dtr
	ON gti.task_item_id = dtr.id
	INNER JOIN data_local AS dl
	ON dl.id = dtr.local_data_id
	SET gl.snmp_query_id = dl.snmp_query_id, gl.snmp_index = dl.snmp_index
	WHERE gl.graph_template_id IN (SELECT graph_template_id FROM snmp_query_graph)
	AND (gl.snmp_query_id != dl.snmp_query_id OR gl.snmp_index != dl.snmp_index)
	AND gl.snmp_query_id = 0');

$fixes = db_affected_rows();
if ($fixes) {
	printf('NOTE: Found and Repaired %s Problems with Data Query IDs and Indexes' . PHP_EOL, $fixes);
} else {
	print 'NOTE: Found No Problems with Data Query Indexes or IDs' . PHP_EOL;
}

print 'NOTE: Repairing Incorrectly Set Data Query Graph IDs' . PHP_EOL;

db_execute("UPDATE graph_local AS gl
	INNER JOIN (
		SELECT DISTINCT local_graph_id, task_item_id
		FROM graph_templates_item
	) AS gti
	ON gl.id = gti.local_graph_id
	INNER JOIN data_template_rrd AS dtr
	ON gti.task_item_id = dtr.id
	INNER JOIN data_template_data AS dtd
	ON dtr.local_data_id = dtd.local_data_id
	INNER JOIN data_input_fields AS dif
	ON dif.data_input_id = dtd.data_input_id
	INNER JOIN data_input_data AS did
	ON did.data_template_data_id = dtd.id
	AND did.data_input_field_id = dif.id
	INNER JOIN snmp_query_graph_rrd AS sqgr
	ON sqgr.snmp_query_graph_id = did.value
	SET gl.snmp_query_graph_id = did.value
	WHERE input_output = 'in'
	AND type_code = 'output_type'
	AND gl.graph_template_id IN (SELECT graph_template_id FROM snmp_query_graph)
	AND gl.snmp_query_graph_id != CAST(did.value AS signed)");

$fixes = db_affected_rows();
if ($fixes) {
	printf('NOTE: Found and Repaired %s Problems with Data Query Graph IDs' . PHP_EOL, $fixes);
} else {
	print 'NOTE: Found No Problems with Data Query Graph IDs' . PHP_EOL;
}

print 'NOTE: Repairing Data Input Data hostname or host_id Type Code issues' . PHP_EOL;

// Correct bad hostnames and host_id's in the data_input_data table
$entries = db_fetch_assoc("SELECT did.*, dif.type_code
	FROM data_input_data AS did
	INNER JOIN data_input_fields AS dif
	ON did.data_input_field_id = dif.id
	WHERE data_input_field_id in (
		SELECT id
		FROM data_input_fields
		WHERE type_code != ''
	)
	AND data_template_data_id IN (
		SELECT id
		FROM data_template_data
		WHERE local_data_id > 0
		AND data_template_id > 0
	)
	AND type_code in ('host_id', 'hostname')
	AND value = ''");

$fixes = 0;

if (cacti_sizeof($entries)) {
	foreach($entries as $e) {
		$data_template_data = db_fetch_row_prepared('SELECT *
			FROM data_template_data
			WHERE id = ?',
			array($e['data_template_data_id']));

		if (cacti_sizeof($data_template_data)) {
			$local_data = db_fetch_row_prepared('SELECT *
				FROM data_local
				WHERE id = ?',
				array($data_template_data['local_data_id']));

			if (cacti_sizeof($local_data)) {
				switch($e['type_code']) {
					case 'hostname':
						$hostname = db_fetch_cell_prepared('SELECT hostname
							FROM host
							WHERE id = ?',
							array($local_data['host_id']));

						db_execute_prepared('UPDATE data_input_data
							SET value = ?
							WHERE data_input_field_id = ?
							AND data_template_data_id = ?',
							array($hostname, $e['data_input_field_id'], $e['data_template_data_id']));

						$fixes++;

						break;
					case 'host_id':
						db_execute_prepared('UPDATE data_input_data
							SET value = ?
							WHERE data_input_field_id = ?
							AND data_template_data_id = ?',
							array($local_data['host_id'], $e['data_input_field_id'], $e['data_template_data_id']));

						$fixes++;

						break;
				}
			}
		}
	}
}

if ($fixes) {
	print "NOTE: Found and Repaired $fixes invalid Data Input hostname or host_id Type Code issues" . PHP_EOL;
} else {
	print 'NOTE: Found No Data Input Data hostname or host_id Type Code issues' . PHP_EOL;
}

print PHP_EOL . '------------------------------------------------------------------------' . PHP_EOL;
print 'Detailed Checks.  Use --force to repair if found.' . PHP_EOL . PHP_EOL;

print 'NOTE: Searching for Invalid Cacti GPRINT Presets' . PHP_EOL;

/* keep track of total rows */
$total_rows = 0;

/* remove invalid GPrint Presets from the Database, validated */
$rows = db_fetch_cell('SELECT COUNT(*)
	FROM graph_templates_item
	LEFT JOIN graph_templates_gprint
	ON graph_templates_item.gprint_id = graph_templates_gprint.id
	WHERE graph_templates_gprint.id IS NULL
	AND graph_templates_item.gprint_id > 0');

$total_rows += $rows;
if ($rows > 0) {
	if ($force) {
		db_execute('DELETE FROM graph_templates_item
			WHERE gprint_id NOT IN (SELECT id FROM graph_templates_gprint)
			AND gprint_id>0');
	}

	print 'NOTE: Found ' . ($force ? 'and Fixed ':'') . "$rows Invalid GPrint Preset Rows in Graph Templates" . PHP_EOL;
} else {
	print 'NOTE: Found No Invalid Cacti GPRINT Presets' . PHP_EOL;
}

print 'NOTE: Searching for Invalid Cacti CDEFs Presets' . PHP_EOL;

/* remove invalid CDEF Items from the Database, validated */
$rows = db_fetch_cell('SELECT COUNT(*)
	FROM cdef_items
	LEFT JOIN cdef
	ON cdef_items.cdef_id=cdef.id
	WHERE cdef.id IS NULL');

$total_rows += $rows;
if ($rows > 0) {
	if ($force) {
		db_execute('DELETE FROM cdef_items
			WHERE cdef_id NOT IN (SELECT id FROM cdef)');
	}

	print 'NOTE: Found ' . ($force ? 'and Fixed ':'') . "$rows Invalid CDEF Rows in Graph Templates" . PHP_EOL;
} else {
	print 'NOTE: Found No Invalid Cacti CDEFs' . PHP_EOL;
}

print 'NOTE: Searching for Invalid Cacti Data Inputs' . PHP_EOL;

/* remove invalid Data Templates from the Database, validated */
$rows = db_fetch_cell('SELECT COUNT(*)
	FROM data_template_data
	LEFT JOIN data_input
	ON data_template_data.data_input_id=data_input.id
	WHERE data_input.id IS NULL');

$total_rows += $rows;
if ($rows > 0) {
	if ($force) {
		db_execute('DELETE FROM data_template_data
			WHERE data_input_id NOT IN (SELECT id FROM data_input)');
	}

	print 'NOTE: Found ' . ($force ? 'and Fixed ':'') . "$rows Invalid Data Input Rows in Data Templates" . PHP_EOL;
} else {
	print 'NOTE: Found No Invalid Cacti Data Inputs' . PHP_EOL;
}

print 'NOTE: Searching for Graph Templates whose Graphs have invalid item counts' . PHP_EOL;

$rows = db_fetch_assoc('SELECT gt.graph_template_id, gt.items, gt1.graph_items, gt1.graphs
	FROM (
		SELECT graph_template_id, COUNT(*) AS items
		FROM graph_templates_item
		WHERE local_graph_id=0
		GROUP BY graph_template_id
	) AS gt
	INNER JOIN (
		SELECT graph_template_id, MAX(items) AS graph_items, COUNT(*) AS graphs
		FROM (
			SELECT graph_template_id, COUNT(*) AS items
			FROM graph_templates_item
			WHERE local_graph_id > 0
			AND graph_template_id > 0
			GROUP BY local_graph_id
		) AS rs
		GROUP BY graph_template_id
	) AS gt1
	ON gt.graph_template_id = gt1.graph_template_id
	HAVING graph_items != items');

$total_rows += cacti_sizeof($rows);

if (cacti_sizeof($rows)) {
	$total_graphs = 0;

	if ($force) {
		foreach($rows as $row) {
			retemplate_graphs($row['graph_template_id'], 0, true);
			$total_graphs += $row['graphs'];
		}
	} else {
		foreach($rows as $row) {
			$total_graphs += $row['graphs'];
		}
	}

	print 'NOTE: Found ' . ($force ? 'and Fixed ':'') . cacti_sizeof($rows) . ' Graph Templates with ' . $total_graphs . ' Graphs whose item counts that were incorrect' . PHP_EOL;
} else {
	print 'NOTE: Found No Graph Templates whose Graphs had incorrect item counts' . PHP_EOL;
}

print 'NOTE: Searching for Invalid Cacti Data Input Fields' . PHP_EOL;

/* remove invalid Data Input Fields from the Database, validated */
$rows = db_fetch_cell('SELECT COUNT(*)
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

	print 'NOTE: Found ' . ($force ? 'and Fixed ':'') . "$rows Invalid Data Input Field Rows in Data Templates" . PHP_EOL;
} else {
	print 'NOTE: Found No Invalid Cacti Data Input Fields' . PHP_EOL;
}

/* --------------------------------------------------------------------*/

print 'NOTE: Searching for Invalid Cacti Data Input Data Rows (Pass 1)' . PHP_EOL;

/* remove invalid Data Input Data Rows from the Database in two passes */
$rows = db_fetch_cell('SELECT COUNT(*)
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

	print 'NOTE: Found ' . ($force ? 'and Fixed ':'') . "$rows Invalid Data Input Data Rows in Data Templates" . PHP_EOL;
} else {
	print 'NOTE: Found No Invalid Cacti Data Input Data Rows (Pass 1)' . PHP_EOL;
}

print 'NOTE: Searching for Invalid Cacti Data Input Data Rows (Pass 2)' . PHP_EOL;

$rows = db_fetch_cell('SELECT COUNT(*)
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

	print 'NOTE: Found ' . ($force ? 'and Fixed ':'') . "$rows Invalid Data Input Data Rows based upon field mappings in Data Templates" . PHP_EOL;
} else {
	print 'NOTE: Found No Invalid Cacti Data Input Data Rows (Pass 2)' . PHP_EOL;
}

print PHP_EOL . '------------------------------------------------------------------------' . PHP_EOL;

if ($total_rows > 0 && !$force) {
	print 'WARNING: Cacti Template Problems found in your Database.  Using the \'--force\' option will remove' . PHP_EOL;
	print 'the invalid records.  However, these changes can be catastrophic to existing data sources.  Therefore, you' . PHP_EOL;
	print 'should contact your support organization prior to proceeding with that repair.' . PHP_EOL . PHP_EOL;
} elseif ($total_rows == 0) {
	print 'NOTE: No Invalid Cacti Template Records found in your Database' . PHP_EOL . PHP_EOL;
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Database Repair Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL . 'usage: repair_database.php [--dynamic] [--debug] [--force] [--form]' . PHP_EOL . PHP_EOL;
	print 'A utility designed to repair the Cacti database if damaged, and optionally repair any' . PHP_EOL;
	print 'corruption found in the Cacti databases various Templates.' . PHP_EOL . PHP_EOL;
	print 'Optional:' . PHP_EOL;
	print '    --dynamic - Convert a table to Dynamic row format if available' . PHP_EOL;
	print '    --form    - Force rebuilding the indexes from the database creation syntax.' . PHP_EOL;
	print '    --tables  - Repair Tables as well as possible database corruptions.' . PHP_EOL;
	print '    --local   - Perform the action on the Remote Data Collector if run from there' . PHP_EOL;
	print '    --force   - Remove Invalid Template records from the database.' . PHP_EOL;
	print '    --debug   - Display verbose output during execution.' . PHP_EOL . PHP_EOL;
}
