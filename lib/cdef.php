<?php/*
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
<?php

$cdef_item_types = array(
	1 => "Function",
	2 => "Operator",
	4 => "Special Data Source",
	5 => "Another CDEF",
	6 => "Custom String");
			      
$custom_data_source_types = array(
	"CURRENT_DATA_SOURCE" => "Current Graph Item Data Source",
	"ALL_DATA_SOURCES_NODUPS" => "All Data Sources (Don't Include Duplicates)",
	"ALL_DATA_SOURCES_DUPS" => "All Data Sources (Include Duplicates)");
		      
function get_cdef_item_name($cdef_item_id) {
	include("config_arrays.php");
	
	$cdef_item = db_fetch_row("select type,value from cdef_items where id=$cdef_item_id");
	$current_cdef_value = $cdef_item[value];
	
	switch ($cdef_item[type]) {
		case '1': return $cdef_functions[$current_cdef_value]; break;
		case '2': return $cdef_operators[$current_cdef_value]; break;
		case '4': return $current_cdef_value; break;
		case '5': return db_fetch_cell("select name from cdef where id=$current_cdef_value"); break;
		case '6': return $current_cdef_value; break;
	}
}

function get_cdef($cdef_id) {
	$cdef_items = db_fetch_assoc("select * from cdef_items where cdef_id=$cdef_id order by sequence");
	
	$i = 0;
	if (sizeof($cdef_items) > 0) {
	foreach ($cdef_items as $cdef_item) {
		if ($i > 0) {
			$cdef_string .= ",";
		}
		
		if ($cdef_item[type] == 5) {
			$current_cdef_id = $cdef_item[value];
			$cdef_string .= "(" . get_cdef($current_cdef_id) . ")";
		}else{
			$cdef_string .= get_cdef_item_name($cdef_item[id]);
		}
		
		$i++;
	}
	}
	
	return $cdef_string;
}

?>
