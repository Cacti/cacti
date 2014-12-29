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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

define("CHARS_PER_TIER", 3);
define("MAX_TREE_DEPTH", 30);

define("SORT_TYPE_TREE", 1);
define("SORT_TYPE_TREE_ITEM", 2);

/* get_tree_item_type - gets the type of tree item
   @arg $tree_item_id - the id of the tree item to fetch the type for
   @returns - a string reprenting the type of the tree item. valid return
     values are 'header', 'graph', and 'host' */
function get_tree_item_type($tree_item_id) {
	$tree_item = db_fetch_row("SELECT title,local_graph_id,host_id FROM graph_tree_items WHERE id=$tree_item_id");

	if (!sizeof($tree_item)) {
		return "";
	}elseif ($tree_item["local_graph_id"] > 0) {
		return "graph";
	}elseif ($tree_item["title"] != "") {
		return "header";
	}elseif ($tree_item["host_id"] > 0) {
		return "host";
	}

	return "";
}

/* sort_branch - sorts the child items a branch using a specified sorting algorithm
   @arg $sort_type - the type of sorting to perform. available options are:
     SORT_TYPE_TREE (1) - sort the entire tree
     SORT_TYPE_TREE_ITEM (2) - sort a single tree branch
   @arg $item_id - the id tree or tree item to sort
   @arg $sort_style - the type of sorting to perform. available options are:
     TREE_ORDERING_NONE (1) - no sorting
     TREE_ORDERING_ALPHABETIC (2) - alphabetic sorting
     TREE_ORDERING_NUMERIC (3) - numeric sorting */
function sort_tree($sort_type, $branch_id, $sort_style) {
	global $config;

	include_once($config["library_path"] . "/sort.php");

	if (empty($branch_id)) { 
		return 0; 
	}

	if ($sort_style == TREE_ORDERING_NONE) { 
		return 0; 
	}

	if ($sort_type == SORT_TYPE_TREE_ITEM) {
		$tree_id = db_fetch_cell("SELECT graph_tree_id FROM graph_tree_items WHERE id=$branch_id");

		$sql_where = "WHERE gti.graph_tree_id=$tree_id AND parent=$branch_id";
	}else if ($sort_type == SORT_TYPE_TREE) {
		$sql_where = "WHERE gti.graph_tree_id='$branch_id'";

		$tree_id = $branch_id;
	}else{
		return 0;
	}

	$hier_sql = "SELECT gti.id, gti.title, gti.local_graph_id, gti.host_id, 
		gtg.title_cache AS graph_title, CONCAT_WS('',description,' (',hostname,')') AS hostname
		FROM graph_tree_items AS gti
		LEFT JOIN graph_templates_graph AS gtg
		ON gti.local_graph_id=gtg.local_graph_id AND gtg.local_graph_id>0
		LEFT JOIN host AS h
		ON h.id=gti.host_id
		$sql_where
		ORDER BY gti.position";

	$hierarchy = db_fetch_assoc($hier_sql);

	$sort_array = array();
	if (sizeof($hierarchy)) {
	foreach ($hierarchy as $leaf) {
		if ($leaf["local_graph_id"] > 0) {
			$sort_array[$leaf["id"]] = $leaf["graph_title"];
		}elseif ($leaf["title"] != "") {
			$sort_array[$leaf["id"]] = $leaf["title"];
		}elseif ($leaf["host_id"] > 0) {
			$sort_array[$leaf["id"]] = $leaf["hostname"];
		}
	}
	}

	/* do the actual sort */
	if ($sort_style == TREE_ORDERING_NUMERIC) {
		uasort($sort_array, "usort_numeric");
	}elseif ($sort_style == TREE_ORDERING_ALPHABETIC) {
		uasort($sort_array, "usort_alphabetic");
	}elseif ($sort_style == TREE_ORDERING_NATURAL) {
		uasort($sort_array, "usort_natural");
	}

	$position = 0;

	/* prepend all order keys will 'x' so they don't collide during the REPLACE process */
	foreach($sort_array as $id => $item) {
		db_execute("UPDATE graph_tree_items 
			SET position=$position 
			WHERE id=$id AND graph_tree_id=$tree_id");

		$position++;
	}
}

