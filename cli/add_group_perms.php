#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
require_once(CACTI_PATH_LIBRARY . '/api_automation_tools.php');

// switch to main database for cli's
if (POLLER_ID > 1) {
	db_switch_remote_to_main();
}

// process calling arguments
$params = $_SERVER['argv'];
array_shift($params);

if (cacti_sizeof($params) == 0) {
	display_help();

	exit(1);
} else {
	$groupId = 0;

	// TODO replace magic numbers by global constants, treat user_admin as well
	$itemTypes = ['graph' => 1, 'tree' => 2, 'host' => 3, 'graph_template' => 4];

	$itemType     = 0;
	$itemTypeName = 'UNKNOWN';
	$itemId       = 0;
	$hostId       = 0;

	$quietMode             = false;
	$displayGroups         = false;
	$displayUsers          = false;
	$displayTrees          = false;
	$displayHosts          = false;
	$displayGraphs         = false;
	$displayGraphTemplates = false;

	foreach ($params as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--group-id':
				$groupId = intval($value);

				break;
			case '--item-type':
				// TODO replace magic numbers by global constants, treat user_admin as well
				if (($value == 'graph') || ($value == 'tree') || ($value == 'host') || ($value == 'graph_template')) {
					$itemType     = $itemTypes[$value];
					$itemTypeName = $value;
				} else {
					print "ERROR: Invalid Item Type: ($value)" . PHP_EOL . PHP_EOL;
					display_help();

					exit(1);
				}

				break;
			case '--item-id':
				$itemId = intval($value);

				break;
			case '--host-id':
				$hostId = intval($value);

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

				exit(0);
			case '--help':
			case '-H':
			case '-h':
				display_help();

				exit(0);

			default:
				print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
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
		if ($hostId == 0 || (!db_fetch_cell_prepared('SELECT id FROM host WHERE id = ?', [$hostId]))) {
			print 'ERROR: You must supply a valid host_id before you can list its graphs' . PHP_EOL;
			print 'Try --list-hosts' . PHP_EOL;

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

	// verify, that a valid groupid is provided
	$groupIds = [];

	if ($groupId > 0) {
		// verify existing user id
		if (db_fetch_cell_prepared('SELECT id FROM user_auth_group WHERE id = ?', [$groupId])) {
			array_push($groupIds, $groupId);
		} else {
			print "ERROR: Invalid Groupid: ($groupId)" . PHP_EOL . PHP_EOL;
			display_help();

			exit(1);
		}
	}
	// now, we should have at least one verified groupid

	// verify --item-id
	if ($itemType == 0) {
		print 'ERROR: --item-type missing. Please specify.' . PHP_EOL . PHP_EOL;
		display_help();

		exit(1);
	}

	if ($itemId == 0) {
		print 'ERROR: --item-id missing. Please specify.' . PHP_EOL . PHP_EOL;
		display_help();

		exit(1);
	}

	// TODO replace magic numbers by global constants, treat user_admin as well
	switch ($itemType) {
		case 1: // graph
			if (!db_fetch_cell_prepared('SELECT local_graph_id FROM graph_templates_graph WHERE local_graph_id = ?', [$itemId])) {
				print "ERROR: Invalid Graph item id: ($itemId)" . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
			}

			break;
		case 2: // tree
			if (!db_fetch_cell_prepared('SELECT id FROM graph_tree WHERE id = ?', [$itemId])) {
				print "ERROR: Invalid Tree item id: ($itemId)" . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
			}

			break;
		case 3: // host
			if (!db_fetch_cell_prepared('SELECT id FROM host WHERE id = ?', [$itemId])) {
				print "ERROR: Invalid Host item id: ($itemId)" . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
			}

			break;
		case 4: // graph_template
			if (!db_fetch_cell_prepared('SELECT id FROM graph_templates WHERE id = ?', [$itemId])) {
				print "ERROR: Invalid Graph Template item id: ($itemId)" . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
			}

			break;
	}
	// verified

	$groupOpts              = [];
	$groupOpts['group_id']  = $groupId;
	$groupOpts['item_id']   = $itemId;
	$groupOpts['type']      = $itemType;

	$groupUp   = sql_save($groupOpts, 'user_auth_group_perms');
	$groupName = db_fetch_cell_prepared('SELECT name FROM user_auth_group WHERE id = ?', [$groupId]);

	/* This needs to be replace earlier up to define idName for each case
	 * Right now it only does hostname */
	$idName = db_fetch_cell_prepared('SELECT hostname FROM host WHERE id = ?', [$itemId]);

	print "Group Permissions Created - Group-Name: ($groupId - \"$groupName\" ) Item-Type: ($itemType - $itemTypeName) Item-ID: ($itemId - $idName)" . PHP_EOL;

	exit(0);
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Add Group Permissions Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays help information
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL;
	print 'usage: add_group_perms.php [ --group-id=[ID] ]' . PHP_EOL;
	print '    --item-type=[graph|tree|host|graph_template]' . PHP_EOL;
	print '    --item-id [--quiet]' . PHP_EOL . PHP_EOL;
	print 'Where item-id is the id of the object of type item-type' . PHP_EOL . PHP_EOL;
	print 'List Options:' . PHP_EOL;
	print '    --list-users' . PHP_EOL;
	print '    --list-trees' . PHP_EOL;
	print '    --list-hosts' . PHP_EOL;
	print '    --list-groups' . PHP_EOL;
	print '    --list-graph-templates' . PHP_EOL;
	print '    --list-graphs --host-id=[ID]' . PHP_EOL;
}
