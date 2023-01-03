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
require_once($config['base_path'] . '/lib/api_automation_tools.php');
require_once($config['base_path'] . '/lib/api_tree.php');

/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	/* setup defaults */
	$type       = '';  # tree or node
	$name       = '';  # Name of a tree or node
	$sortMethod = 'alpha'; # manual, alpha, natural, numeric
	$parentNode = 0;   # When creating a node, the parent node of this node (or zero for root-node)
	$treeId     = 0;   # When creating a node, it has to go in a tree
	$nodeType   = '';  # Should be 'header', 'graph' or 'host' when creating a node
	$graphId    = 0;   # The ID of the graph to add (gets added to parentNode)
	$siteId     = 0;   # The ID of the site to add

	$sortMethods = array('manual' => 1, 'alpha' => 2, 'natural' => 4, 'numeric' => 3);
	$nodeTypes   = array('header' => 1, 'graph' => 2, 'host' => 3);

	$hostId         = 0;
	$hostGroupStyle = 1; # 1 = Graph Template,  2 = Data Query Index

	$quietMode      = false;
	$displayHosts   = false;
	$displayTrees   = false;
	$displayNodes   = false;
	$displayRRAs    = false;
	$displayGraphs  = false;
	$displaySites   = false;

	$hosts          = getHosts();
	$sites          = getSites();

	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
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
				$parentNode = $value;

				break;
			case '--tree-id':
				$treeId = $value;

				break;
			case '--node-type':
				$nodeType = trim($value);

				break;
			case '--graph-id':
				$graphId = $value;

				break;
			case '--host-id':
				$hostId = $value;

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
				print "ERROR: Invalid Argument: ($arg)\n\n";
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
		if (!isset($treeId)) {
			print "ERROR: You must supply a tree_id before you can list its nodes\n";
			print "Try --list-trees\n";
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
		if (!isset($hostId) || $hostId == 0) {
			print "ERROR: You must supply a host_id before you can list its graphs\n";
			print "Try --list-hosts\n";
			exit(1);
		}

		displayHostGraphs($hostId, $quietMode);
		exit(0);
	}

	if ($type == 'tree') {
		# Add a new tree
		if (empty($name)) {
			print "ERROR: You must supply a name with --name\n";
			display_help();
			exit(1);
		}

		$treeOpts = array();
		$treeOpts['id']        = 0; # Zero means create a new one rather than save over an existing one
		$treeOpts['name']      = $name;

		if ($sortMethod == 'manual'||
			$sortMethod == 'alpha' ||
			$sortMethod == 'numeric' ||
			$sortMethod == 'natural') {
			$treeOpts['sort_type'] = $sortMethods[$sortMethod];
		} else {
			print "ERROR: Invalid sort-method: ($sortMethod)\n";
			display_help();
			exit(1);
		}

		$existsAlready = db_fetch_cell("SELECT id FROM graph_tree WHERE name = '$name'");
		if ($existsAlready) {
			print "ERROR: Not adding tree - it already exists - tree-id: ($existsAlready)\n";
			exit(1);
		}

		$treeId = sql_save($treeOpts, 'graph_tree');

		api_tree_sort_branch(0, $treeId);

		print "Tree Created - tree-id: ($treeId)\n";

		exit(0);
	} elseif ($type == 'node') {
		# Add a new node to a tree
		if ($nodeType == 'header'||
			$nodeType == 'graph' ||
			$nodeType == 'site' ||
			$nodeType == 'host') {
			$itemType = $nodeTypes[$nodeType];
		} else {
			print "ERROR: Invalid node-type: ($nodeType)\n";
			display_help();
			exit(1);
		}

		if (!is_numeric($parentNode)) {
			print "ERROR: parent-node $parentNode must be numeric > 0\n";
			display_help();
			exit(1);
		} elseif ($parentNode > 0 ) {
			$parentNodeExists = db_fetch_cell("SELECT id
				FROM graph_tree_items
				WHERE graph_tree_id=$treeId
				AND id=$parentNode");

			if (!isset($parentNodeExists)) {
				print "ERROR: parent-node $parentNode does not exist\n";
				exit(1);
			}
		}

		if ($nodeType == 'header') {
			# Header --name must be given
			if (empty($name)) {
				print "ERROR: You must supply a name with --name\n";
				display_help();
				exit(1);
			}

			# Blank out the graphId, hostID and host_grouping_style  fields
			$graphId        = 0;
			$hostId         = 0;
			$siteId         = 0;
			$hostGroupStyle = 1;
		}else if($nodeType == 'graph') {
			# Blank out name, hostID, host_grouping_style
			$name           = '';
			$hostId         = 0;
			$siteId         = 0;
			$hostGroupStyle = 1;

			$graphs = db_fetch_assoc('SELECT id
				FROM graph_local
				WHERE graph_local.id=' . $graphId);

			if (!cacti_sizeof($graphs)) {
				print "ERROR: No such graph-id ($graphId) exists. Try --list-graphs\n";
				exit(1);
			}
		}else if ($nodeType == 'site') {
			# Blank out graphId, name fields
			$graphId        = 0;
			$hostId         = 0;
			$name           = '';

			if (!isset($sites[$siteId])) {
				print "ERROR: No such site-id ($siteId) exists. Try --list-sites\n";
				exit(1);
			}
		}else if ($nodeType == 'host') {
			# Blank out graphId, name fields
			$graphId        = 0;
			$siteId         = 0;
			$name           = '';

			if (!isset($hosts[$hostId])) {
				print "ERROR: No such host-id ($hostId) exists. Try --list-hosts\n";
				exit(1);
			}

			if ($hostGroupStyle != 1 && $hostGroupStyle != 2) {
				print "ERROR: Host Group Style must be 1 or 2 (Graph Template or Data Query Index)\n";
				display_help();
				exit(1);
			}
		}

		# $nodeId could be a Header Node, a Graph Node, or a Host node.
		$nodeId = api_tree_item_save(0, $treeId, $itemType, $parentNode, $name, $graphId, $hostId, $siteId, $hostGroupStyle, $sortMethods[$sortMethod], false);

		print "Added Node node-id: ($nodeId)\n";

		exit(0);
	} else {
		print "ERROR: Unknown type: ($type)\n";
		display_help();
		exit(1);
	}
} else {
	display_help();
	exit(0);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Add Tree Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	print "\nusage: add_tree.php  --type=[tree|node] [type-options] [--quiet]\n\n";
	print "Tree options:\n";
	print "    --name=[Tree Name]\n";
	print "    --sort-method=[manual|alpha|natural|numeric]\n\n";
	print "Node options:\n";
	print "    --node-type=[header|site|host|graph]\n";
	print "    --tree-id=[ID]\n";
	print "    [--parent-node=[ID] [Node Type Options]]\n\n";
	print "Header node options:\n";
	print "    --name=[Name]\n\n";
	print "Site node options:\n";
	print "    --site-id=[ID]\n";
	print "Host node options:\n";
	print "    --host-id=[ID]\n";
	print "    [--host-group-style=[1|2]]\n";
	print "    (host group styles:\n";
	print "     1 = Graph Template,\n";
	print "     2 = Data Query Index)\n\n";
	print "Graph node options:\n";
	print "    --graph-id=[ID]\n\n";
	print "List Options:\n";
	print "    --list-sites\n";
	print "    --list-hosts\n";
	print "    --list-trees\n";
	print "    --list-nodes --tree-id=[ID]\n";
	print "    --list-graphs --host-id=[ID]\n";
}
