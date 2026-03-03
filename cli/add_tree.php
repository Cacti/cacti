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
require_once(CACTI_PATH_LIBRARY . '/api_tree.php');

// switch to main database for cli's
if (POLLER_ID > 1) {
	db_switch_remote_to_main();
}

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	// setup defaults
	$type       = '';  // tree or node
	$name       = '';  // Name of a tree or node
	$sortMethod = 'alpha'; // manual, alpha, natural, numeric
	$parentNode = 0;   // When creating a node, the parent node of this node (or zero for root-node)
	$treeId     = 0;   // When creating a node, it has to go in a tree
	$nodeType   = '';  // Should be 'header', 'graph' or 'host' when creating a node
	$graphId    = 0;   // The ID of the graph to add (gets added to parentNode)
	$siteId     = 0;   // The ID of the site to add

	$sortMethods = ['manual' => 1, 'alpha' => 2, 'natural' => 4, 'numeric' => 3];
	$nodeTypes   = ['header' => 1, 'graph' => 2, 'host' => 3];

	$hostId         = 0;
	$hostGroupStyle = 1; // 1 = Graph Template,  2 = Data Query Index

	$quietMode      = false;
	$displayHosts   = false;
	$displayTrees   = false;
	$displayNodes   = false;
	$displayRRAs    = false;
	$displayGraphs  = false;
	$displaySites   = false;

	$hosts          = getHosts();
	$sites          = getSites();

	foreach ($parms as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--type':
				$type = trim($value);

				break;
			case '--name':
				$name = trim($value);

				break;
			case '--sort-method':
				$sortMethod = trim($value);

				break;
			case '--parent-node':
				$parentNode = intval($value);

				break;
			case '--tree-id':
				$treeId = intval($value);

				break;
			case '--node-type':
				$nodeType = trim($value);

				break;
			case '--graph-id':
				$graphId = intval($value);

				break;
			case '--host-id':
				$hostId = intval($value);

				break;
			case '--quiet':
				$quietMode = true;

				break;
			case '--list-hosts':
				$displayHosts = true;

				break;
			case '--list-trees':
				$displayTrees = true;

				break;
			case '--list-nodes':
				$displayNodes = true;

				break;
			case '--list-graphs':
				$displayGraphs = true;

				break;
			case '--list-sites':
				$displaySites = true;

				break;
			case '--host-group-style':
				$hostGroupStyle = trim($value);

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

	if ($displaySites) {
		displaySites($sites, $quietMode);

		exit(0);
	}

	if ($displayHosts) {
		displayHosts($hosts, $quietMode);

		exit(0);
	}

	if ($displayTrees) {
		displayTrees($quietMode);

		exit(0);
	}

	if ($displayNodes) {
		if ($treeId == 0) {
			print 'ERROR: You must supply a tree_id before you can list its nodes' . PHP_EOL;
			print 'Try --list-trees' . PHP_EOL;

			exit(1);
		}

		displayTreeNodes($treeId, $nodeType, $parentNode, $quietMode);

		exit(0);
	}

	if ($displayRRAs) {
		displayRRAs($quietMode);

		exit(0);
	}

	if ($displayGraphs) {
		if ($hostId <= 0) {
			print 'ERROR: You must supply a host_id before you can list its graphs' . PHP_EOL;
			print 'Try --list-hosts' . PHP_EOL;

			exit(1);
		}

		displayHostGraphs($hostId, $quietMode);

		exit(0);
	}

	if ($type == 'tree') {
		// Add a new tree
		if (empty($name)) {
			print 'ERROR: You must supply a name with --name' . PHP_EOL;
			display_help();

			exit(1);
		}

		$treeOpts              = [];
		$treeOpts['id']        = 0; // Zero means create a new one rather than save over an existing one
		$treeOpts['name']      = $name;

		if ($sortMethod == 'manual' ||
			$sortMethod == 'alpha' ||
			$sortMethod == 'numeric' ||
			$sortMethod == 'natural') {
			$treeOpts['sort_type'] = $sortMethods[$sortMethod];
		} else {
			print "ERROR: Invalid sort-method: ($sortMethod)" . PHP_EOL;
			display_help();

			exit(1);
		}

		$existsAlready = db_fetch_cell("SELECT id FROM graph_tree WHERE name = '$name'");

		if ($existsAlready) {
			print "ERROR: Not adding tree - it already exists - tree-id: ($existsAlready)" . PHP_EOL;

			exit(1);
		}

		$treeId = sql_save($treeOpts, 'graph_tree');

		api_tree_sort_branch(0, $treeId);

		print "Tree Created - tree-id: ($treeId)" . PHP_EOL;

		exit(0);
	}

	if ($type == 'node') {
		// Add a new node to a tree
		if ($nodeType == 'header' ||
			$nodeType == 'graph' ||
			$nodeType == 'site' ||
			$nodeType == 'host') {
			$itemType = $nodeTypes[$nodeType];
		} else {
			print "ERROR: Invalid node-type: ($nodeType)" . PHP_EOL;
			display_help();

			exit(1);
		}

		if ($parentNode < 0) {
			print "ERROR: parent-node $parentNode must be numeric > 0" . PHP_EOL;
			display_help();

			exit(1);
		}

		if ($parentNode > 0) {
			$parentNodeExists = db_fetch_cell("SELECT id
				FROM graph_tree_items
				WHERE graph_tree_id=$treeId
				AND id=$parentNode");

			if (!isset($parentNodeExists)) {
				print "ERROR: parent-node $parentNode does not exist" . PHP_EOL;

				exit(1);
			}
		}

		if ($nodeType == 'header') {
			// Header --name must be given
			if (empty($name)) {
				print 'ERROR: You must supply a name with --name' . PHP_EOL;
				display_help();

				exit(1);
			}

			// Blank out the graphId, hostID and host_grouping_style  fields
			$graphId        = 0;
			$hostId         = 0;
			$siteId         = 0;
			$hostGroupStyle = 1;
		} elseif ($nodeType == 'graph') {
			// Blank out name, hostID, host_grouping_style
			$name           = '';
			$hostId         = 0;
			$siteId         = 0;
			$hostGroupStyle = 1;

			$graphs = db_fetch_assoc('SELECT id
				FROM graph_local
				WHERE graph_local.id=' . $graphId);

			if (!cacti_sizeof($graphs)) {
				print "ERROR: No such graph-id ($graphId) exists. Try --list-graphs" . PHP_EOL;

				exit(1);
			}
		} elseif ($nodeType == 'site') {
			// Blank out graphId, name fields
			$graphId        = 0;
			$hostId         = 0;
			$name           = '';

			if (!isset($sites[$siteId])) {
				print "ERROR: No such site-id ($siteId) exists. Try --list-sites" . PHP_EOL;

				exit(1);
			}
		} elseif ($nodeType == 'host') {
			// Blank out graphId, name fields
			$graphId        = 0;
			$siteId         = 0;
			$name           = '';

			if (!isset($hosts[$hostId])) {
				print "ERROR: No such host-id ($hostId) exists. Try --list-hosts" . PHP_EOL;

				exit(1);
			}

			if ($hostGroupStyle != 1 && $hostGroupStyle != 2) {
				print 'ERROR: Host Group Style must be 1 or 2 (Graph Template or Data Query Index)' . PHP_EOL;
				display_help();

				exit(1);
			}
		}

		// $nodeId could be a Header Node, a Graph Node, or a Host node.
		$nodeId = api_tree_item_save(0, $treeId, $itemType, $parentNode, $name, $graphId, $hostId, $siteId, $hostGroupStyle, $sortMethods[$sortMethod], false);

		print "Added Node node-id: ($nodeId)" . PHP_EOL;

		exit(0);
	} else {
		print "ERROR: Unknown type: ($type)" . PHP_EOL;
		display_help();

		exit(1);
	}
} else {
	display_help();

	exit(0);
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Add Tree Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays help information
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL;
	print 'usage: add_tree.php  --type=[tree|node] [type-options] [--quiet]' . PHP_EOL . PHP_EOL;

	print 'Tree options:' . PHP_EOL;
	print '    --name=[Tree Name]' . PHP_EOL;
	print '    --sort-method=[manual|alpha|natural|numeric]' . PHP_EOL . PHP_EOL;

	print 'Node options:' . PHP_EOL;
	print '    --node-type=[header|site|host|graph]' . PHP_EOL;
	print '    --tree-id=[ID]' . PHP_EOL;
	print '    [--parent-node=[ID] [Node Type Options]]' . PHP_EOL . PHP_EOL;

	print 'Header node options:' . PHP_EOL;
	print '    --name=[Name]' . PHP_EOL . PHP_EOL;

	print 'Site node options:' . PHP_EOL;
	print '    --site-id=[ID]' . PHP_EOL . PHP_EOL;

	print 'Host node options:' . PHP_EOL;
	print '    --host-id=[ID]' . PHP_EOL;
	print '    [--host-group-style=[1|2]]' . PHP_EOL;
	print '    (host group styles:' . PHP_EOL;
	print '     1 = Graph Template,' . PHP_EOL;
	print '     2 = Data Query Index)' . PHP_EOL . PHP_EOL;

	print 'Graph node options:' . PHP_EOL;
	print '    --graph-id=[ID]' . PHP_EOL . PHP_EOL;

	print 'List Options:' . PHP_EOL;
	print '    --list-sites' . PHP_EOL;
	print '    --list-hosts' . PHP_EOL;
	print '    --list-trees' . PHP_EOL;
	print '    --list-nodes --tree-id=[ID]' . PHP_EOL;
	print '    --list-graphs --host-id=[ID]' . PHP_EOL;
}
