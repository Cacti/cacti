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
require_once($config['base_path'] . '/lib/api_data_source.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/utility.php');
require_once($config['base_path'] . '/lib/template.php');

/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

unset($host_id);
unset($graph_template_id);
unset($data_template_id);

foreach($parms as $parameter) {
	if (strpos($parameter, '=')) {
		list($arg, $value) = explode('=', $parameter);
	} else {
		$arg = $parameter;
		$value = '';
	}

	switch ($arg) {
	case '--host-id':
		$host_id = trim($value);
		if (!is_numeric($host_id)) {
			print 'ERROR: You must supply a valid host-id to run this script!' . PHP_EOL;
			exit(1);
		}
		break;
	case '--data-template-id':
		$data_template_id = $value;
		if (!is_numeric($data_template_id)) {
			print 'ERROR: You must supply a numeric data-template-id!' . PHP_EOL;
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
	default:
		print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
		display_help();
		exit(1);
	}
}

if (!isset($host_id)) {
	print "ERROR: You must supply a valid host-id!\n";
	exit(1);
}

if (!isset($data_template_id)) {
	print "ERROR: You must supply a valid data-template-id!\n";
	exit(1);
}

//Following code was copied from data_sources.php->function form_save->save_component_data_source_new

$save["id"] = "0";
$save["data_template_id"] = $data_template_id;
$save["host_id"] = $host_id;

$local_data_id = sql_save($save, "data_local");

change_data_template($local_data_id, $data_template_id);

/* update the title cache */
update_data_source_title_cache($local_data_id);

/* update host data */
if (!empty($host_id)) {
	push_out_host($host_id, $local_data_id);
}

print "DS Added - DS[$local_data_id]\n";

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Add Data Source, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();
	print "usage: add_datasource.php --host-id=[ID] --data-template-id=[ID]\n\n";
	print "Cacti utility for adding datasources via a command line interface.\n\n";
	print "--host-id=id - The host id\n";
	print "--data-template-id=id - The numerical ID of the data template to be added\n";
}

