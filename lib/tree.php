<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

function tree_tier($order_key, $chars_per_tier) {
	$root_test = str_pad('',$chars_per_tier,'0');
	
	if (preg_match("/^$root_test/",$order_key)) {
		$tier = 0;
	}else{
		$tier = ceil(strlen(preg_replace("/0+$/",'',$order_key)) / $chars_per_tier);
	}
	
	return($tier);
}

function get_parent_id($id, $table, $where = "") {
	$parent_root = 0;
	
	$order_key = db_fetch_cell("select order_key from $table where id=$id and $where");
	$tier = tree_tier($order_key,'2');
	
    	if ($tier > 1) {
		$parent_root = substr($order_key,0,(($tier - 1) * 2) );
	}
	
	return db_fetch_cell("select id from $table where order_key='" . str_pad($parent_root,60,'0') . "' and $where");
}

function get_next_tree_id($order_key, $table, $field, $where) {
	if (preg_match("/^00/",$order_key)) {
		$tier = 0;
		$parent_root = '';
	}else{
		$tier = tree_tier($order_key,'2');
		$parent_root = substr($order_key,0,($tier * 2));
	}
    	
    	$order_key = db_fetch_cell("SELECT $field FROM $table WHERE $where AND $field LIKE '$parent_root%' ORDER BY $field DESC LIMIT 1");
    	
	$complete_root = substr($order_key,0,($tier * 2) + 2);
  	$order_key_suffix = (substr($complete_root, -2) + 1);
	$order_key_suffix = str_pad($order_key_suffix,2,'0',STR_PAD_LEFT);
	$order_key_suffix = str_pad($parent_root . $order_key_suffix,60,'0',STR_PAD_RIGHT);
	
	return $order_key_suffix;
}


function branch_up($order_key, $table, $field, $where) { 
	move_branch('up',$order_key, $table, $field, $where); 
}

function branch_down($order_key, $table, $field, $where) { 
	move_branch('down',$order_key, $table, $field, $where); 
}

function move_branch($dir,$order_key, $table, $field, $where) {
	$tier = tree_tier($order_key,'2');
	
	if ($where != '') { $where = " AND $where"; }
	$where = "";
	$arrow = $dir == 'up' ? '<' : '>';
	$order = $dir == 'up' ? 'DESC' : 'ASC';
	
	$sql = "SELECT * FROM $table WHERE $field $arrow $order_key AND $field LIKE '%".substr($order_key,($tier * 2))."' 
		AND $field NOT LIKE '%00".substr($order_key,($tier * 2))."' $where ORDER BY $field $order";
	
	$displaced_row = db_fetch_row($sql);
	
	if (sizeof($displaced_row) > 0) {
		$old_root = substr($order_key,0,($tier * 2));
		$new_root = substr($displaced_row[$field],0,($tier * 2));
		
		db_execute("LOCK TABLES $table WRITE");
		$sql = "UPDATE $table SET $field = CONCAT('".str_pad('',($tier * 2),'Z')."',SUBSTRING($field,".(($tier * 2) + 1).")) WHERE $field LIKE '$new_root%'$where";
		db_execute($sql);
		$sql = "UPDATE $table SET $field = CONCAT('$new_root',SUBSTRING($field,".(($tier * 2) + 1).")) WHERE $field LIKE '$old_root%' $where";
		db_execute($sql);
		$sql = "UPDATE $table SET $field = CONCAT('$old_root',SUBSTRING($field,".(($tier * 2) + 1).")) WHERE $field LIKE '".str_pad('',($tier * 2),'Z')."%' $where";
		db_execute($sql);
		db_execute("UNLOCK TABLES $table");
	}
}

function reparent_branch($new_parent_id, $tree_item_id) {
	if (empty($tree_item_id)) { return 0; }
	
	/* get the current tree_id */
	$graph_tree_id = db_fetch_cell("select graph_tree_id from graph_tree_items where id=$tree_item_id");
	
	/* get current key so we can do a sql select on it */
	$old_order_key = db_fetch_cell("select order_key from graph_tree_items where id=$tree_item_id");
	$new_order_key = get_next_tree_id(db_fetch_cell("select order_key from graph_tree_items where id=$new_parent_id"),"graph_tree_items","order_key","graph_tree_id=$graph_tree_id");
	
	/* yeah, this would be really bad */
	if (empty($old_order_key)) { return 0; }
	
	$old_starting_tier = tree_tier($old_order_key, 2);
	$new_starting_tier = tree_tier($new_order_key, 2);
	
	$new_base_tier = substr($new_order_key, 0, ($new_starting_tier*2));
	$old_base_tier = substr($old_order_key, 0, ($old_starting_tier*2));
	
	$padding = "";
	
	$tree = db_fetch_assoc("select 
		graph_tree_items.id, graph_tree_items.order_key
		from graph_tree_items
		where graph_tree_items.order_key like '$old_base_tier%%'
		and graph_tree_items.graph_tree_id=$graph_tree_id
		order by graph_tree_items.order_key");
	
	/* since we are building the order_key based on two unrelated tiers, we must be sure the final product
	always adds up to 60 characters */
	if ((($old_starting_tier * 2) + 1) > strlen($new_base_tier)) {
		$padding = ",'" . str_repeat('0', (($old_starting_tier * 2) + 1) - strlen($new_base_tier)) . "'";
	}
	
	db_execute("update graph_tree_items set order_key = CONCAT('$new_base_tier',SUBSTRING(order_key," . (($old_starting_tier * 2) + 1) . ")$padding) where order_key like '$old_base_tier%%' and graph_tree_id=$graph_tree_id");
}

function delete_branch($tree_item_id) {
	if (empty($tree_item_id)) { return 0; }
	
	/* if this item is a graph, it will have NO children, so we can just delete the
	graph and exit. */
	if (db_fetch_cell("select local_graph_id from graph_tree_items where id=$tree_item_id") > 0) {
		db_execute("delete from graph_tree_items where id=$tree_item_id");
		return 0;
	}
	
	/* get current key so we can do a sql select on it */
	$order_key = db_fetch_cell("select order_key from graph_tree_items where id=$tree_item_id");
	
	/* get the current tree_id */
	$graph_tree_id = db_fetch_cell("select graph_tree_id from graph_tree_items where id=$tree_item_id");
	
	/* yeah, this would be really bad */
	if (empty($order_key)) { return 0; }
	
	$starting_tier = tree_tier($order_key, 2);
	$order_key = substr($order_key, 0, (2 * $starting_tier));
	
	$tree = db_fetch_assoc("select 
		graph_tree_items.id, graph_tree_items.order_key
		from graph_tree_items
		where graph_tree_items.order_key like '$order_key%%'
		and graph_tree_items.graph_tree_id=$graph_tree_id
		order by graph_tree_items.order_key");
	
	if (sizeof($tree) > 0) {
	foreach ($tree as $tree_item) {
		/* delete the folder */
		db_execute("delete from graph_tree_items where id=" . $tree_item["id"]);
	}
	}
	
	/* CLEANUP - reorder the tier that this branch lies in */
	$order_key = substr($order_key, 0, (2 * ($starting_tier-1)));
	
	$tree = db_fetch_assoc("select 
		graph_tree_items.id, graph_tree_items.order_key
		from graph_tree_items
		where graph_tree_items.order_key like '$order_key%%'
		and graph_tree_items.graph_tree_id=$graph_tree_id
		order by graph_tree_items.order_key");
	
	$i = 0; $ctr = 0; $_suffix_order_key = 0;
	if (sizeof($tree) > 0) {
	foreach ($tree as $tree_item) {
		/* ignore first entry */
		if ($ctr > 0) {
			$suffix_order_key = substr($tree_item["order_key"], (2 * $starting_tier));
			
			if ((!ereg("[1-9]+",$suffix_order_key)) || ($suffix_order_key < $_suffix_order_key) || ($ctr==1 && $i==0)) {
				$i++;
			}
			
			$prefix_order_key = substr($tree_item["order_key"], 0, (2 * ($starting_tier-1)));
			$prefix_order_key .= str_pad($i,2,'0',STR_PAD_LEFT);
			$prefix_order_key .= $suffix_order_key;
			
			db_execute("update graph_tree_items set order_key='$prefix_order_key' where id=" . $tree_item["id"]);
			
			$_suffix_order_key = $suffix_order_key;
		}
		
		$ctr++;
	}
	}
}

?>
