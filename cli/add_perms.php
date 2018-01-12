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

include(dirname(__FILE__).'/../include/global.php');
include_once($config['base_path'].'/lib/api_automation_tools.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms) == 0) {
	display_help();

	exit(1);
} else {
	$userId    = 0;

	/* TODO replace magic numbers by global constants, treat user_admin as well */
	$itemTypes = array('graph' => 1, 'tree' => 2, 'host' => 3, 'graph_template' => 4);

	$itemType = 0;
	$itemId   = 0;
	$hostId   = 0;

	$quietMode				= false;
	$displayGroups			= false;
	$displayUsers			= false;
	$displayTrees			= false;
	$displayHosts			= false;
	$displayGraphs			= false;
	$displayGraphTemplates 	= false;

	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
		case '--user-id':
			$userId = $value;

			break;
		case '--item-type':
			/* TODO replace magic numbers by global constants, treat user_admin as well */
			if ( ($value == 'graph') || ($value == 'tree') || ($value == 'host') || ($value == 'graph_template')) {
				$itemType = $itemTypes[$value];
			} else {
				echo "ERROR: Invalid Item Type: ($value)\n\n";
				display_help();
				exit(1);
			}

			break;
		case '--item-id':
			$itemId = $value;

			break;
		case '--host-id':
			$hostId = $value;

			break;
		case '--list-groups':
			$displayGroups = true;

			break;
		case '--list-users':
			$displayUsers = true;

			break;
		case '--list-trees':
			$displayTrees = true;

			break;
		case '--list-hosts':
			$displayHosts = true;

			break;
		case '--list-graphs':
			$displayGraphs = true;

			break;
		case '--list-graph-templates':
			$displayGraphTemplates = true;

			break;
		case '--quiet':
			$quietMode = true;

			break;
		case '--version':
		case '-V':
		case '-v':
			display_version();
			exit;
		case '--help':
		case '-H':
		case '-h':
			display_help();
			exit;
		default:
			echo "ERROR: Invalid Argument: ($arg)\n\n";
			display_help();
			exit(1);
		}
	}

	if ($displayGroups) {
		displayGroups($quietMode);
		exit(1);
	}

	if ($displayUsers) {
		displayUsers($quietMode);
		exit(1);
	}

	if ($displayTrees) {
		displayTrees($quietMode);
		exit(1);
	}

	if ($displayHosts) {
		$hosts = getHosts();
		displayHosts($hosts, $quietMode);
		exit(1);
	}

	if ($displayGraphs) {
		if (!isset($hostId) || ($hostId === 0) || (!db_fetch_cell("SELECT id FROM host WHERE id=$hostId"))) {
			echo "ERROR: You must supply a valid host_id before you can list its graphs\n";
			echo "Try --list-hosts\n";
			display_help();
			exit(1);
		} else {
			displayHostGraphs($hostId, $quietMode);
			exit(1);
		}
	}

	if ($displayGraphTemplates) {
		$graphTemplates = getGraphTemplates();
		displayGraphTemplates($graphTemplates, $quietMode);
		exit(1);
	}

	/* verify, that a valid userid is provided */
	$userIds = array();

	if (isset($userId) && $userId > 0) {
		/* verify existing user id */
		if ( db_fetch_cell("SELECT id FROM user_auth WHERE id=$userId") ) {
			array_push($userIds, $userId);
		} else {
			echo "ERROR: Invalid Userid: ($value)\n\n";
			display_help();
			exit(1);
		}
	}
	/* now, we should have at least one verified userid */

	/* verify --item-id */
	if ($itemType == 0) {
		echo "ERROR: --item-type missing. Please specify.\n\n";
		display_help();
		exit(1);
	}

	if ($itemId == 0) {
		echo "ERROR: --item-id missing. Please specify.\n\n";
		display_help();
		exit(1);
	}

	/* TODO replace magic numbers by global constants, treat user_admin as well */
	switch ($itemType) {
		case 1: /* graph */
			if (!db_fetch_cell("SELECT local_graph_id FROM graph_templates_graph WHERE local_graph_id=$itemId") ) {
				echo "ERROR: Invalid Graph item id: ($itemId)\n\n";
				display_help();
				exit(1);
			}
			break;
		case 2: /* tree */
			if (!db_fetch_cell("SELECT id FROM graph_tree WHERE id=$itemId") ) {
				echo "ERROR: Invalid Tree item id: ($itemId)\n\n";
				display_help();
				exit(1);
			}
			break;
		case 3: /* host */
			if (!db_fetch_cell("SELECT id FROM host WHERE id=$itemId") ) {
				echo "ERROR: Invalid Host item id: ($itemId)\n\n";
				display_help();
				exit(1);
			}
			break;
		case 4: /* graph_template */
			if (!db_fetch_cell("SELECT id FROM graph_templates WHERE id=$itemId") ) {
				print "ERROR: Invalid Graph Template item id: ($itemId)\n\n";
				display_help();
				exit(1);
			}
			break;
	}
	/* verified item-id */

	foreach ($userIds as $id) {
		db_execute("REPLACE INTO user_auth_perms (user_id, item_id, type) VALUES ($id, $itemId, $itemType)");
	}
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Add Permissions Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	echo "\nusage: add_perms.php [ --user-id=[ID] ]\n";
	echo "    --item-type=[graph|tree|host|graph_template]\n";
	echo "    --item-id [--quiet]\n\n";
	echo "Where item-id is the id of the object of type item-type\n\n";
	echo "List Options:\n";
	echo "    --list-users\n";
	echo "    --list-trees\n";
	echo "    --list-graph-templates\n";
	echo "    --list-graphs --host-id=[ID]\n";
}

function displayGroups() {
    /**
     * Todo implement
     */
	echo 'This option has not yet been implemented';
}