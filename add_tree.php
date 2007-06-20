#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2007 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

include(dirname(__FILE__)."/include/config.php");
include_once($config["base_path"]."/lib/api_automation_tools.php");
include_once($config["base_path"].'/lib/tree.php');

if ($_SERVER["argc"] == 1) {
	usage();
	return(1);
}else{
	$type       = '';  # tree or node
	$name       = '';  # Name of a tree or node
	$sortMethod = 'a'; # a = alpha, n = numeric, m = manual
	$parentNode = 0;   # When creating a node, the parent node of this node (or zero for root-node)
	$treeId     = 0;   # When creating a node, it has to go in a tree
	$nodeType   = '';  #  Should be 'header', 'graph' or 'host' when creating a node
	$graphId    = 0;   # The ID of the graph to add (gets added to parentNode)
	$rra_id     = 0;   # The rra_id for the graph to display: 1 = daily, 2 = weekly, 3 = monthly, 4 = yearly

	$sortTypes = array('a' => 2, 'n' => 3, 'm' => 1);
	$nodeTypes = array('header' => 1, 'graph' => 2, 'host' => 3);

	$hostId         = 0;
	$hostGroupStyle = 1; # 1 = Graph Template,  2 = Data Query Index

	$hosts          = getHosts();

	for ($i = 1; $i < $_SERVER["argc"]; $i++) {
		switch($_SERVER["argv"][$i]) {
		case "--type":
			$i++;
			$type = $_SERVER["argv"][$i];
			break;
		case "--name":
			$i++;
			$name = $_SERVER["argv"][$i];
			break;
		case "--sort-method":
			$i++;
			$sortMethod = $_SERVER["argv"][$i];
			break;
		case "--parent-node":
			$i++;
			$parentNode = $_SERVER["argv"][$i];
			break;
		case "--tree-id":
			$i++;
			$treeId = $_SERVER["argv"][$i];
			break;
		case "--node-type":
			$i++;
			$nodeType = $_SERVER["argv"][$i];
			break;
		case "--graph-id":
			$i++;
			$graphId = $_SERVER["argv"][$i];
			break;
		case "--rra-id":
			$i++;
			$rra_id = $_SERVER["argv"][$i];
			break;
		case "--host-id":
			$i++;
			$hostId = $_SERVER["argv"][$i];
			break;
		case "--list-hosts":
			displayHosts($hosts);
			return 0;
		case "--host-group-style":
			$i++;
			$hostGroupStyle = $_SERVER["argv"][$i];
			break;
		default:
			usage();
			return 0;
		}
	}

	if ($type == 'tree') {
		# Add a new tree
		if (empty($name)) {
			printf("You must supply a name with --name\n");
			return 1;
		}

		$treeOpts = array();
		$treeOpts["id"]        = 0; # Zero means create a new one rather than save over an existing one
		$treeOpts["name"]      = $name;
		$treeOpts["sort_type"] = $sortTypes[$sortMethod];

		if (!isset($treeOpts["sort_type"]) || empty($treeOpts["sort_type"])) {
			printf("Invalid sort-method: %s\n", $sortMethod);
			return 1;
		}

		$existsAlready = db_fetch_cell("select id from graph_tree where name = '$name'");
		if ($existsAlready) {
			printf("Not adding tree - it already exists - tree-id: (%d)\n", $existsAlready);
			return 1;
		}

		$treeId = sql_save($treeOpts, "graph_tree");

		sort_tree(SORT_TYPE_TREE, $treeId, $treeOpts["sort_type"]);

		printf("Tree Created - tree-id: (%d)\n", $treeId);

		return 0;
	}else if ($type == 'node') {
		# Add a new node to a tree
		$itemType = $nodeTypes[$nodeType];

		if (!isset($itemType) || empty($itemType)) {
			printf("Invalid node-type: %s\n", $nodeType);
			return 1;
		}

		if ($parentNode > 0) {
			$parentNodeExists = db_fetch_cell("SELECT id FROM graph_tree_items WHERE graph_tree_id = $treeId AND id = $parentNode");
			if (!isset($parentNodeExists)) {
				printf("parent-node %d does not exist\n");
				return 1;
			}
		}

		if ($nodeType == 'header') {
			# Blank out the graphId, rra_id, hostID and host_grouping_style  fields
			$graphId        = 0;
			$rra_id         = 0;
			$hostId         = 0;
			$hostGroupStyle = 1;
		}else if($nodeType == 'graph') {
			# Blank out name, hostID, host_grouping_style
			$name           = '';
			$hostId         = 0;
			$hostGroupStyle = 1;
		}else if ($nodeType == 'host') {
			# Blank out graphId, rra_id, name fields
			$graphId        = 0;
			$rra_id         = 0;
			$name           = '';

			if (!isset($hosts[$hostId])) {
				printf("No such host-id (%s) exists. Try --list-hosts\n", $hostId);
				return 1;
			}

			if ($hostGroupStyle != 1 && $hostGroupStyle != 2) {
				printf("Host Group Style must be 1 or 2 (Graph Template or Data Query Index)\n");
				return 1;
			}
		}

		# $nodeId could be a Header Node, a Graph Node, or a Host node.
		$nodeId = api_tree_item_save(0, $treeId, $itemType, $parentNode, $name, $graphId, $rra_id, $hostId, $hostGroupStyle, 1, false);
		printf("Added Node node-id: (%d)\n", $nodeId);
		return 0;
	}else{
		printf("Unknown type: $type\n");
		usage();
		return 1;
	}
}

function usage() {
	echo "Usage:\n";
	echo "add_tree.php --type [tree|node] [type-options]\n\n";
	echo "tree options: --name [Tree Name] --sort-method [a|n|m]\n";
	echo "(sort methods: a = Alphabetic, n = numeric, m = manual)\n\n";
	echo "node options:        --node-type [header|graph|host] --tree-id [ID] --parent-node [ID] [Node Type Options]\n";
	echo "header node options: --name [Name]\n";
	echo "graph node options:  --graph-id [ID] --rra-id [ID]\n";
	echo "host node options:   --host-id [ID] --host-group-style [1 | 2]\n";
	echo "(Host group styles: 1 = Graph template, 2 = Data Query Index)\n\n";
	echo "List Options: --list-hosts\n";
	echo "              --list-trees *\n";
	echo "              --tree-id [ID] --list-nodes *\n";
	echo "              --list-rras *\n";
	echo "              --host-id [ID] --list-graphs *\n\n";
	echo "* = not yet implemented - sorry.\n";
}

?>
