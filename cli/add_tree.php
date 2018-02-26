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
include_once($config['base_path'].'/lib/api_tree.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms)) {
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
			case '--host-group-style':
				$hostGroupStyle = trim($value);

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
			echo "ERROR: You must supply a tree_id before you can list its nodes\n";
			echo "Try --list-trees\n";
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
			echo "ERROR: You must supply a host_id before you can list its graphs\n";
			echo "Try --list-hosts\n";
			exit(1);
		}

		displayHostGraphs($hostId, $quietMode);
		exit(0);
	}

	if ($type == 'tree') {
		# Add a new tree
		if (empty($name)) {
			echo "ERROR: You must supply a name with --name\n";
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
			echo "ERROR: Invalid sort-method: ($sortMethod)\n";
			display_help();
			exit(1);
		}

		$existsAlready = db_fetch_cell("SELECT id FROM graph_tree WHERE name = '$name'");
		if ($existsAlready) {
			echo "ERROR: Not adding tree - it already exists - tree-id: ($existsAlready)\n";
			exit(1);
		}

		$treeId = sql_save($treeOpts, 'graph_tree');

		api_tree_sort_branch(0, $treeId);

		echo "Tree Created - tree-id: ($treeId)\n";

		exit(0);
	} elseif ($type == 'node') {
		# Add a new node to a tree
		if ($nodeType == 'header'||
			$nodeType == 'graph' ||
			$nodeType == 'site' ||
			$nodeType == 'host') {
			$itemType = $nodeTypes[$nodeType];
		} else {
			echo "ERROR: Invalid node-type: ($nodeType)\n";
			display_help();
			exit(1);
		}

		if (!is_numeric($parentNode)) {
			echo "ERROR: parent-node $parentNode must be numeric > 0\n";
			display_help();
			exit(1);
		} elseif ($parentNode > 0 ) {
			$parentNodeExists = db_fetch_cell("SELECT id
				FROM graph_tree_items
				WHERE graph_tree_id=$treeId
				AND id=$parentNode");

			if (!isset($parentNodeExists)) {
				echo "ERROR: parent-node $parentNode does not exist\n";
				exit(1);
			}
		}

		if ($nodeType == 'header') {
			# Header --name must be given
			if (empty($name)) {
				echo "ERROR: You must supply a name with --name\n";
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
		
			if (!sizeof($graphs)) {
				echo "ERROR: No such graph-id ($graphId) exists. Try --list-graphs\n";
				exit(1);
			}
		}else if ($nodeType == 'site') {
			# Blank out graphId, name fields
			$graphId        = 0;
			$hostId         = 0;
			$name           = '';

			if (!isset($sites[$siteId])) {
				echo "ERROR: No such site-id ($siteId) exists. Try --list-sites\n";
				exit(1);
			}
		}else if ($nodeType == 'host') {
			# Blank out graphId, name fields
			$graphId        = 0;
			$siteId         = 0;
			$name           = '';

			if (!isset($hosts[$hostId])) {
				echo "ERROR: No such host-id ($hostId) exists. Try --list-hosts\n";
				exit(1);
			}

			if ($hostGroupStyle != 1 && $hostGroupStyle != 2) {
				echo "ERROR: Host Group Style must be 1 or 2 (Graph Template or Data Query Index)\n";
				display_help();
				exit(1);
			}
		}

		# $nodeId could be a Header Node, a Graph Node, or a Host node.
		$nodeId = api_tree_item_save(0, $treeId, $itemType, $parentNode, $name, $graphId, $hostId, $siteId, $hostGroupStyle, $sortMethods[$sortMethod], false);

		echo "Added Node node-id: ($nodeId)\n";

		exit(0);
	} else {
		echo "ERROR: Unknown type: ($type)\n";
		display_help();
		exit(1);
	}
} else {
	display_help();
	exit(0);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Add Tree Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	echo "\nusage: add_tree.php  --type=[tree|node] [type-options] [--quiet]\n\n";
	echo "Tree options:\n";
	echo "    --name=[Tree Name]\n";
	echo "    --sort-method=[manual|alpha|natural|numeric]\n\n";
	echo "Node options:\n";
	echo "    --node-type=[header|site|host|graph]\n";
	echo "    --tree-id=[ID]\n";
	echo "    [--parent-node=[ID] [Node Type Options]]\n\n";
	echo "Header node options:\n";
	echo "    --name=[Name]\n\n";
	echo "Site node options:\n";
	echo "    --site-id=[ID]\n";
	echo "Host node options:\n";
	echo "    --host-id=[ID]\n";
	echo "    [--host-group-style=[1|2]]\n";
	echo "    (host group styles:\n";
	echo "     1 = Graph Template,\n";
	echo "     2 = Data Query Index)\n\n";
	echo "Graph node options:\n";
	echo "    --graph-id=[ID]\n\n";
	echo "List Options:\n";
	echo "    --list-sites\n";
	echo "    --list-hosts\n";
	echo "    --list-trees\n";
	echo "    --list-nodes --tree-id=[ID]\n";
	echo "    --list-graphs --host-id=[ID]\n";
}
