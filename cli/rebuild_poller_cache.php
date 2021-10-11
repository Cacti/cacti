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
require_once($config['library_path'] . '/poller.php');
require_once($config['library_path'] . '/utility.php');
require_once($config['library_path'] . '/api_data_source.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug = false;
$verbose = false;
$host_id = 0;

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
				$verbose = true;
				break;
			case '--host-id':
				$host_id = trim($value);

				if (!is_numeric($host_id)) {
					print "ERROR: You must supply a valid device id to run this script!\n";
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
			case '--verbose':
				$verbose = true;
				break;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit(1);
		}
	}
}

/* obtain timeout settings */
$max_execution = ini_get('max_execution_time');

/* set new timeout */
ini_set('max_execution_time', '0');

/* prepare some variables that we're going to use. */
$poller_items    = array();
$local_data_ids  = array();
$hosts           = array();
$data_template_fields = array();

print CLI_CSI . CLI_FG_RED . CLI_SGR_END . 'WARNING' . CLI_CSI . CLI_SGR_RESET . CLI_SGR_END .
	": Do not interrupt this script.  Rebuilding the Poller Cache can take quite some time\n";

verbose('Querying for data sources...');
/* first of all, get all data sources and their corresponding information.
	any param of get_data_sources() set to zero means as 'all'
*/
$data_sources = get_data_sources($host_id);
verbose("There are " . cacti_sizeof($data_sources) . " data source elements to update.");

/* initialize some variables */
$current_ds = 1;
$total_ds = cacti_sizeof($data_sources);

/* start rebuilding the poller cache */
if (cacti_sizeof($data_sources)) {
	if (!$debug) {
		$tcount = 0;
		print "\n";
	}
	verbose("Combing through all Data Sources, preparing data");
	foreach ($data_sources as $data_source) {
		if (!$debug) {
			$tcount++;
			print CLI_CSI . CLI_EL_WHOLE . CLI_CR . "$tcount / " . count($data_sources) .
			' (' . round($tcount/count($data_sources)*100,1) .  '%)';
		}

		/* fill in hosts array, if not already present */
		if (!isset($hosts[$data_source['host_id']])) {
			$hosts[$data_source['host_id']] = db_fetch_row_prepared('SELECT *
				FROM host
				WHERE id = ?',
				array($data_source['host_id']));
		}

		/* get field information FROM the data template */
		if (!isset($data_template_fields[$data_source['local_data_template_data_id']])) {
			# we must briefly construct an array out of $data_source for get_data_template_fields()
			$data_source_arr = array($data_source);
			$data_template_fields[$data_source['local_data_template_data_id']] =
				get_data_template_fields($data_source_arr)[$data_source['local_data_template_data_id']];
		}

		/* push out host value if necessary */
		if (cacti_sizeof($data_template_fields[$data_source['local_data_template_data_id']])) {
			push_out_data_input_data($data_template_fields, $data_source, $host);
		}

		/* note this source's id for later */
		$local_data_ids[] = $data_source['id'];

		/* create a compatible structure for update_poller_cache().
		   also call update_poller_cache without commit (note: currently the default),
		   so it just returs the massaged poller_items, and so that we can do the update
		   in a huge chunk by ourselves. */
		$data = $data_source;
		$data['id'] = $data['local_data_id'];
		$poller_items = array_merge($poller_items, update_poller_cache($data));

		debug("Data Source Item '$current_ds' of '$total_ds' processed");
		$current_ds++;
	}
	print "\n";
	verbose('Preparation done');
	verbose("Updating poller cache for ". count($local_data_ids) . " ID's / " .
		count($poller_items) . " items." );
	if (cacti_sizeof($local_data_ids)) {
		poller_update_poller_cache_from_buffer($local_data_ids, $poller_items);
	}

	verbose("Updating API data source cache to inform remote pollers");
	api_data_source_cache_crc_update($poller_id);
}
if (!$debug) print "\n";

/* poller cache rebuilt, restore runtime parameters */
ini_set('max_execution_time', $max_execution);

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Rebuild Poller Cache Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: rebuild_poller_cache.php [--host-id=ID] [--debug]\n\n";
	print "A utility to repopulate Cacti's poller cache for a host or a system.  Note: That when performing\n";
	print "for an entire Cacti system, expecially a large one, this may take some time.\n\n";
	print "Optional:\n";
	print "    --host-id=ID - Limit the repopulation to a single Cacti Device\n";
	print "    --debug      - Display verbose output during execution\n\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		print 'DEBUG: ' . trim($message) . "\n";
	}
}

function verbose($message) {
	global $verbose;
	if ($verbose) {
		print 'INFO: ' . trim($message) . "\n";
	}
}
