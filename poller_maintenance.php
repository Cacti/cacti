<?php

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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
*/

/* do NOT run this script through a web browser */
if (!isset ($_SERVER['argv'][0]) || isset ($_SERVER['REQUEST_METHOD']) || isset ($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* We are not talking to the browser */
$no_http_headers = true;

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');

error_reporting(E_ALL);
$dir = dirname(__FILE__);
chdir($dir);

/* record the start time */
list($micro,$seconds) = explode(" ", microtime());
$poller_start         = $seconds + $micro;

include ('./include/global.php');

global $config, $database_default;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug    = FALSE;
$force    = FALSE;
$archived = 0;
$purged   = 0;

foreach ($parms as $parameter) {
	@list ($arg, $value) = @explode('=', $parameter);

	switch ($arg) {
		case '-h' :
		case '-v' :
		case '--version' :
		case '--help' :
			display_help();
			exit;
		case '--force' :
			$force = true;
			break;
		case '--debug' :
			$debug = true;
			break;
		default :
			print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
			display_help();
			exit;
	}
}

maint_debug("Checking for Purge Actions");

/* are my tables already present? */
$purge = db_fetch_cell("SELECT count(*) FROM data_source_purge_action");

/* if the table that holds the actions is present, work on it */
if (($purge)) {
	maint_debug("Purging Required - Files Found $purge");

	/* take the purge in steps */
	while (true) {
		maint_debug("Grabbing 1000 RRDfiles to Remove");

		$file_array = db_fetch_assoc('SELECT id, name, local_data_id, action 
			FROM data_source_purge_action
			ORDER BY name
			LIMIT 1000');

		if (sizeof($file_array) == 0) {
			break;
		}
	
		if (sizeof($file_array) || $force) {
			/* there's something to do for us now */
			remove_files($file_array);
	
			if ($force) {
				cleanup_ds_and_graphs();
			}
		}
	}

	/* record the start time */
	list($micro,$seconds) = explode(" ", microtime());
	$poller_end         = $seconds + $micro;

	$string = sprintf("MAINT STATS: Time:%4.4f Purged:%i Archived:%i RRDfiles", ($poller_end - $poller_start), $purged, $archived);
	cacti_log($string, true, 'SYSTEM');
}

/*
 * remove_files
 * remove all unwanted files; the list is given by table data_source_purge_action
 */
function remove_files($file_array) {
	global $config, $debug, $archived, $purged;

	include_once ($config['library_path'] . '/api_graph.php');
	include_once ($config['library_path'] . '/api_data_source.php');

	maint_debug('RRDClean is now running on ' . sizeof($file_array) . ' items');

	/* determine the location of the RRA files */
	if (isset ($config['rra_path'])) {
		$rra_path = $config['rra_path'];
	} else {
		$rra_path = $config['base_path'] . '/rra';
	}

	/* let's prepare the archive directory */
	$rrd_archive = read_config_option('rrd_archive', TRUE);
	if ($rrd_archive == '') {
		$rrd_archive = $rra_path . '/archive';
	}
	rrdclean_create_path($rrd_archive);

	/* now scan the files */
	foreach ($file_array as $file) {
		$source_file = $rra_path . '/' . $file['name'];
		switch ($file['action']) {
		case '1' :
			if (unlink($source_file)) {
				maint_debug('Deleted: ' . $file['name']);
			} else {
				cacti_log($file['name'] . " Error: unable to delete from $rra_path!", true, 'MAINT');
			}
			$purged++;
			break;
		case '3' :
			$target_file = $rrd_archive . '/' . $file['name'];
			$target_dir = dirname($target_file);
			if (!is_dir($target_dir)) {
				rrdclean_create_path($target_dir);
			}

			if (rename($source_file, $target_file)) {
				maint_debug('Moved: ' . $file['name'] . ' to: ' . $rrd_archive);
			} else {
				cacti_log($file['name'] . " Error: unable to move to $rrd_archive!", true, 'MAINT');
			}
			$archived++;
			break;
		}

		/* drop from data_source_purge_action table */
		db_execute("DELETE FROM `data_source_purge_action` WHERE name = '" . $file['name'] . "'");

		maint_debug('Delete from data_source_purge_action: ' . $file['name']);

		//fetch all local_graph_id's according to this data source
		$lgis = db_fetch_assoc('SELECT DISTINCT gl.id
			FROM graph_local AS gl
			INNER JOIN graph_templates_item AS gti
			ON gl.id = gti.local_graph_id
			INNER JOIN data_template_rrd AS dtr
			ON dtr.id=gti.task_item_id
			INNER JOIN data_local AS dl
			ON dtr.local_data_id=dl.id
			WHERE (local_data_id=' . $file['local_data_id'] .	')');

		if (sizeof($lgis)) {
			/* anything found? */
			cacti_log('Processing ' . sizeof($lgis) . ' Graphs for data source id: ' . $file['local_data_id'], true, 'MAINT');

			/* get them all */
			foreach ($lgis as $item) {
				$remove_lgis[] = $item['id'];
				cacti_log('remove local_graph_id=' . $item['id'], true, 'MAINT');
			}

			/* and remove them in a single run */
			if (!empty ($remove_lgis)) {
				api_graph_remove_multi($remove_lgis);
			}
		}

		/* remove related data source if any */
		if ($file['local_data_id'] > 0) {
			cacti_log('removing data source: ' . $file['local_data_id'], true, 'MAINT');
			api_data_source_remove($file['local_data_id']);
		}
	}

	cacti_log('RRDClean has finished a purge pass of ' . sizeof($file_array) . ' items', true, 'MAINT');
}

function rrdclean_create_path($path) {
	global $config;

	if (!is_dir($path)) {
		if (mkdir($path, 0775)) {
			if ($config['cacti_server_os'] != 'win32') {
				$owner_id      = fileowner($config['rra_path']);
				$group_id      = filegroup($config['rra_path']);

				// NOTE: chown/chgrp fails for non-root users, checking their
				// result is therefore irrevelevant
				@chown($path, $owner_id);
				@chgrp($path, $group_id);
			}
		}else{
			cacti_log("ERROR: Unable to create directory '" . $path . "'", FALSE);
		}
	}

	// if path existed, we can return true
	return is_dir($path) && is_writable($path);
}

/*
 * cleanup_ds_and_graphs - courtesy John Rembo
 */
function cleanup_ds_and_graphs() {
	global $config;

	include_once ($config['library_path'] . '/rrd.php');
	include_once ($config['library_path'] . '/utility.php');
	include_once ($config['library_path'] . '/api_graph.php');
	include_once ($config['library_path'] . '/api_data_source.php');
	include_once ($config['library_path'] . '/functions.php');

	$remove_ldis = array ();
	$remove_lgis = array ();

	cacti_log('RRDClean now cleans up all data sources and graphs', true, 'MAINT');
	//fetch all local_data_id's which have appropriate data-sources
	$rrds = db_fetch_assoc("SELECT local_data_id, name_cache, data_source_path 
		FROM data_template_data 
		WHERE name_cache >''");

	//filter those whose rrd files doesn't exist
	foreach ($rrds as $item) {
		$ldi = $item['local_data_id'];
		$name = $item['name_cache'];
		$ds_pth = $item['data_source_path'];
		$real_pth = str_replace('<path_rra>', $config['rra_path'], $ds_pth);
		if (!file_exists($real_pth)) {
			if (!in_array($ldi, $remove_ldis)) {
				$remove_ldis[] = $ldi;
				cacti_log("RRD file is missing for data source name: $name (local_data_id=$ldi)", true, 'MAINT');
			}
		}
	}

	if (empty ($remove_ldis)) {
		cacti_log('No missing rrd files found', true, 'MAINT');
		return 0;
	}

	cacti_log('Processing Graphs', true, 'MAINT');
	//fetch all local_graph_id's according to filtered rrds
	$lgis = db_fetch_assoc('SELECT DISTINCT gl.id
		FROM graph_local AS gl
		INNER JOIN graph_templates_item AS gti
		ON gl.id=gti.local_graph_id
		INNER JOIN data_template_rrd AS dtr
		ON dtr.id=gti.task_item_id
		INNER JOIN data_local AS dl
		ON dtr.local_data_id=dl.id
		WHERE (' . array_to_sql_or($remove_ldis, 'local_data_id') . ')');

	foreach ($lgis as $item) {
		$remove_lgis[] = $item['id'];
		cacti_log('RRD file missing for local_graph_id=' . $item['id'], true, 'MAINT');
	}

	if (!empty ($remove_lgis)) {
		cacti_log('removing graphs', true, 'MAINT');
		api_graph_remove_multi($remove_lgis);
	}

	cacti_log('removing data sources', true, 'MAINT');
	api_data_source_remove_multi($remove_ldis);

	cacti_log('removed graphs:' . count($remove_lgis) . ' removed data-sources:' . count($remove_ldis), true, 'MAINT');
}

function maint_debug($message) {
	global $debug;

	if ($debug) {
		echo trim($message) . "\n";
	}
}

/*
 * display_help
 * displays the usage of the function
 */
function display_help() {
	$version = db_fetch_cell('SELECT cacti FROM version');
	print "Cacti Maintenance Script, Version $version, " . COPYRIGHT_YEARS . "\n\n";
	print "usage: poller_maintenance.php [--force] [--debug] [--help] [--version]\n\n";
	print "--force       - force execution, e.g. for testing\n";
	print "--debug       - debug execution, e.g. for testing\n\n";
	print "-v --version  - Display this help message\n";
	print "-h --help     - display this help message\n";
}

?>
