<?/* 
   +-------------------------------------------------------------------------+
   | Copyright (C) 2002 Ian Berry                                            |
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
   | cacti: the rrdtool frontend [php-auth, php-tree, php-form]              |
   +-------------------------------------------------------------------------+
   | This code is currently maintained and debugged by Ian Berry, any        |
   | questions or comments regarding this code should be directed to:        |
   | - iberry@raxnet.net                                                     |
   +-------------------------------------------------------------------------+
   | - raXnet - http://www.raxnet.net/                                       |
   +-------------------------------------------------------------------------+
   */?>
<?

function ReturnMatrixRowAlternateColorBegin($row_color1, $row_color2, $row_value) {
	if (($row_value % 2) == 1) {
		$current_color = $row_color1;
	}else{
		$current_color = $row_color2;
	}
	
	return "<tr bgcolor=\"#$current_color\">";
}

##  This function decides what 'tier' a given id is on based on the characters per tier.
##  For example:  Called with ('1000','1'), it would return '1'.
##                Called with ('1010','1'), it would return '3'.
##                Called with ('1010','2'), it would return '2'.
##
##  Note:  'tier' is determined from left to right.
function tree_tier($id,$chars_per_tier) {
    $root_test = str_pad('',$chars_per_tier,'0');
    if (preg_match("/^$root_test/",$id)) {
	$tier = 0;
    } else {
	$tier = ceil(strlen(preg_replace("/0+$/",'',$id)) / $chars_per_tier);
    }
    return($tier);
}


##  This takes in a heirarchical ID and increments it in its current tier
function increment_id($id) {
    $tier = tree_tier($id,'2');
    if ($tier > 1) {
	$parent_root = substr($id,0,(($tier - 1) * 2) );
    }
    $id_chunk = substr($id,(($tier - 1) * 2),'2');
    $id_chunk += 1;
    #  $id_chunk = (strrev($id_chunk) + 1);
    $id_chunk = str_pad($id_chunk,2,'0',STR_PAD_RIGHT);
    #  $id_chunk = strrev($id_chunk);
    $next_id = str_pad("$parent_root$id_chunk",60,'0');
    return($next_id);
}


##  This takes in a heirarchical ID and decrements it in its current tier
function decrement_id($id) {
    $tier = tree_tier($id,'2');
    if ($tier > 1) {
	$parent_root = substr($id,0,(($tier - 1) * 2) );
    }
    $id_chunk = substr($id,(($tier - 1) * 2),'2');
    $id_chunk -= 1;
    #  $id_chunk = (strrev($id_chunk) + 1);
    $id_chunk = str_pad($id_chunk,2,'0',STR_PAD_RIGHT);
    #  $id_chunk = strrev($id_chunk);
    $next_id = str_pad("$parent_root$id_chunk",60,'0');
    return($next_id);
}


##  This function queries the database with the table and field specified to calculate the next
##  available id ON THE SAME TIER.
function get_next_tree_id($id,$table,$field) {
	if (preg_match("/^00/",$id)) {
		$tier = 0;
		$parent_root = '';
	}else{
		$tier = tree_tier($id,'2');
		$parent_root = substr($id,0,($tier * 2));
	}
    
    	$order_key = db_fetch_cell("SELECT $field FROM $table WHERE $field LIKE '$parent_root%' ORDER BY $field DESC LIMIT 1");
    	
	$complete_root = substr($order_key,0,($tier * 2) + 2);
  	$order_key_suffix = (substr($complete_root, -2) + 1);
	$order_key_suffix = str_pad($order_key_suffix,2,'0',STR_PAD_LEFT);
	$order_key_suffix = str_pad($parent_root . $order_key_suffix,60,'0',STR_PAD_RIGHT);
	
	return $order_key_suffix;
}

function branch_up($order_key, $table, $field, $where = '', $primary_key = 'ID') { 
    move_branch('up',$order_key, $table, $field, $where, $primary_key); 
}


function branch_down($order_key, $table, $field, $where, $primary_key = 'ID') { 
    move_branch('down',$order_key, $table, $field, $where, $primary_key); 
}


function move_branch($dir,$order_key, $table, $field, $where) {
    $tier = tree_tier($order_key,'2');
    if ($where != '') { $where = " AND $where"; }
    $arrow = $dir == 'up' ? '<' : '>';
    $order = $dir == 'up' ? 'DESC' : 'ASC';
    $sql = "SELECT * FROM $table WHERE $field  $arrow $order_key AND $field LIKE '%".substr($order_key,($tier * 2))."' 
	    AND $field NOT LIKE '%00".substr($order_key,($tier * 2))."'ORDER BY $field $order $where";
    $displaced_row = db_fetch_row($sql);
    if (sizeof($displaced_row) > 0) {
	$old_root = substr($order_key,0,($tier * 2));
	$new_root = substr($displaced_row[$field],0,($tier * 2));

	db_execute("LOCK TABLES $table WRITE");
	$sql = "UPDATE $table SET $field = CONCAT('".str_pad('',($tier * 2),'Z')."',SUBSTRING($field,".(($tier * 2) + 1).")) WHERE $field LIKE '$new_root%'$where";
	db_execute($sql);
	$sql = "UPDATE $table SET $field = CONCAT('$new_root',SUBSTRING($field,".(($tier * 2) + 1).")) WHERE $field LIKE '$old_root%' $where";
	db_execute($sql);
	$sql = "UPDATE $table SET $field = CONCAT('$old_root',SUBSTRING($field,".(($tier * 2) + 1).")) WHERE $field LIKE '".str_pad('',($tier * 2),'Z')."%'$where";
	db_execute($sql);
	db_execute("UNLOCK TABLES $table");
    }
}

function spread_branches($order_key,$table,$field,$where = '') {
    $tier = tree_tier($order_key,'2');
    if ($where != '') { $where = " AND $where"; }
    $sql = "SELECT $field FROM $table WHERE $field >= $order_key AND $field LIKE '%".substr($order_key,($tier * 2))."' 
	    AND $field NOT LIKE '%00".substr($order_key,($tier * 2))."' $where ORDER by $field DESC";
    print "'$sql'<BR>\n";
    $br_to_move = db_fetch_assoc($sql);
    if (sizeof($br_to_move) > 0) {
	db_execute("LOCK TABLES $table WRITE");
	foreach ($br_to_move as $branch) {
	    if (! $cleared[$branch[$field]]) { 
		$next_id = get_next_tree_id($branch[$field]);
	    } else {
		$next_id = increment_id($branch[$field]);
	    }
	    $next_id = trim($next_id,"0");
	    $sql = "UPDATE $table SET $field = CONCAT($next_id, SUBSTRING($field,".(($tier * 2) + 1).")) WHERE $field LIKE '".substr($order_key,0,($tier * 2))."'";
	    print "'$sql'<BR>\n";
#	    db_execute($sql);
	}
	db_execute("UNLOCK TABLES $table");
    }
}

?>
