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
function get_next_tree_id($id,$table,$field,$where = '') {
    if (preg_match("/^00/",$id)) {
	$tree_tier = 0;
	$parent_root = '';
    } else {
	$tree_tier = tree_tier($id,'2');
	$parent_root = substr($id,0,($tree_tier * 2));
    }
    if ($where != '') { $where = " AND $where"; }
    $sql = "SELECT $field FROM $table WHERE $field LIKE '$parent_root%' AND $where ORDER BY $field DESC LIMIT 1";
    $tmp = db_fetch_assoc($sql);
    $last_id = $tmp[0][$field];
    #  if ($last_id == '') { $last_id = '00'; }
    if (substr($last_id,($tree_tier * 2),'2') != '00') {
	$next_id = increment_id($last_id);
    } else {
	$next_id = str_pad($parent_root."01",60,'0',STR_PAD_RIGHT);
    }
    
    return($next_id);
}


function branch_up($id, $table, $field, $where = '', $primary_key = 'ID') {
    if ($where != '') { $where = " AND $where"; }
    $sql = "SELECT * FROM $table WHERE $field  < $id '$where";
    $displaced_row = db_fetch_row($sql);
    $tier = tree_tier($id);
    $old_root = substr($id,0,($tier * 2));
    $new_root = substr($displaced_row[$field],0,($tier * 2));
    db_execute("LOCK TABLES $table WRITE");
    db_execute("UPDATE $table SET $field = CONCAT('".str_pad('',($tier * 2),'Z')."',SUBSTR($field,".(($tier * 2) + 1).")) WHERE $field LIKE '$new_root%'$where");
    db_execute("UPDATE $table SET $field = CONCAT('$new_root',SUBSTR($field,".(($tier * 2) + 1).")) WHERE $field LIKE '$old_root%' $where");
    db_execute("UPDATE $table SET $field = CONCAT('$old_root',SUBSTR($field,".(($tier * 2) + 1).")) WHERE $field LIKE '".str_pad('',($tier * 2),'Z')."%'$where");
    db_execute("UNLOCK TABLES $table");
}


function branch_down($id, $table, $field, $where, $primary_key = 'ID') {
    if ($where != '') { $where = " AND $where"; }
    $sql = "SELECT * FROM $table WHERE $field  > $id '$where";
    $displaced_row = db_fetch_row($sql);
    $tier = tree_tier($id);
    $old_root = substr($id,0,($tier * 2));
    $new_root = substr($displaced_row[$field],0,($tier * 2));
    db_execute("LOCK TABLES $table WRITE");
    db_execute("UPDATE $table SET $field = CONCAT('".str_pad('',($tier * 2),'Z')."',SUBSTR($field,".(($tier * 2) + 1).")) WHERE $field LIKE '$new_root%'$where");
    db_execute("UPDATE $table SET $field = CONCAT('$new_root',SUBSTR($field,".(($tier * 2) + 1).")) WHERE $field LIKE '$old_root%' $where");
    db_execute("UPDATE $table SET $field = CONCAT('$old_root',SUBSTR($field,".(($tier * 2) + 1).")) WHERE $field LIKE '".str_pad('',($tier * 2),'Z')."%'$where");
    db_execute("UNLOCK TABLES $table");
}


function spread_branches($id,$table,$field,$where = '') {
    $tier = tree_tier($id,'2');
    $wcard = str_pad(substr($id,0,(($tier - 1) * 2)). '%',60,'0',STR_PAD_RIGHT);
    if ($where != '') { $where = " AND $where"; }
    $sql = "SELECT $field FROM $table WHERE $field LIKE '$wcard' AND $field >= $id $where ORDER by $field DESC";
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
	    $sql = "UPDATE $table SET $field = CONCAT($next_id, SUBSTR($field,".(($tier * 2) + 1).")) WHERE $field LIKE '".substr($id,0,($tier * 2))."'";
	    print "'$sql'<BR>\n";
	}
	db_execute("UNLOCK TABLES $table");
    }
}

?>
