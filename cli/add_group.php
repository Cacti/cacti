#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2025 The Cacti Group                                 |
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

/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

/* process calling arguments */
$params = $_SERVER['argv'];
array_shift($params);

if (cacti_sizeof($params) == 0) {
	display_help();

	exit(1);
} else {
	$userId        = 0;
	$quietMode     = false;
	$displayGroups = false;
	$displayUsers  = false;
	$displayHosts  = false;

	foreach ($params as $parameter) {
		if (strpos($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--type':
				$type = trim($value);

				break;
			case '--group-id':
				$groupId = $value;

				break;
			case '--name':
				$name = trim($value);

				break;
			case '--description':
				$description = trim($value);

				break;
			case '--list-groups':
				$displayGroups = true;

				break;
			case '--list-users':
				$displayUsers = true;

				break;
			case '--list-hosts':
				$displayHosts = true;

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
				print "ERROR: Invalid Argument: ($arg)\n\n";
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

	if ($displayHosts) {
		$hosts = getHosts();
		displayHosts($hosts, $quietMode);

		exit(1);
	}

	/* verify, that a valid userid is provided */
	$groupIds = [];

	if (isset($groupId) && $groupId > 0) {
		/* verify existing user id */
		if (db_fetch_cell_prepared('SELECT id FROM user_auth_group WHERE id = ?', [$groupId])) {
			array_push($groupIds, $groupId);
		} else {
			print "ERROR: Invalid Groupid: ($value)\n\n";
			display_help();

			exit(1);
		}
	}
}

if ($type == 'add_group') {
	# Add a new group
	if (empty($name)) {
		print "ERROR: You must supply a name with --name\n";
		display_help();

		exit(1);
	}

	if (empty($description)) {
		print "ERROR: You must supply a description with --description\n";
		display_help();

		exit(1);
	}

	$groupOpts                           = [];
	$groupOpts['id']                     = 0; # Zero means create a new one rather than save over an existing one
	$groupOpts['name']                   = $name;
	$groupOpts['description']            = $description;
	$groupOpts['graph_settings']         = 'on'; # Default
	$groupOpts['login_opts']             = 1; # Default - needs option
	$groupOpts['show_tree']              = 2; # Default - needs option
	$groupOpts['show_list']              = 3; # Default - needs option
	$groupOpts['show_preview']           = 2; # Default - needs option
	$groupOpts['policy_graphs']          = 2; # Default - needs option
	$groupOpts['policy_trees']           = 2; # Default - needs option
	$groupOpts['policy_hosts']           = 2; # Default - needs option
	$groupOpts['policy_graph_templates'] = 1; # Default - needs option
	$groupOpts['enabled']                = 'on'; # Default - needs option

	# Default - needs option
	$existsAlready = db_fetch_cell_prepared('SELECT id FROM user_auth_group WHERE name = ?', [$name]);

	if ($existsAlready) {
		print "ERROR: Not adding group - it already exists - group-id: ($existsAlready)\n";

		exit(1);
	}

	$groupId = sql_save($groupOpts, 'user_auth_group');

	print "Group Created - Group-id: ($groupId)\n";

	exit(0);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Add Permissions Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	print "\nusage: add_groups.php --type=[add_group|group_perm] --name=[name] --description=[description]\n";
	print "[--quiet]\n\n";
	print "List Options:\n";
	print "    --list-users\n";
	print "    --list-groups\n";
	print "    --list-hosts\n";
}
