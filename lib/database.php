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

include($config["include_path"] . "/adodb/adodb.inc.php");
db_connect();

function db_connect() {
	global $database_hostname,$database_username,$database_password,$database_default, $database_type;
	db_connect_real($database_hostname,$database_username,$database_password,$database_default, $database_type); 
}

function db_connect_real($host,$user,$pass,$db_name,$db_type) {
	global $cnn_id;
	
	$cnn_id = NewADOConnection($db_type);
	if ($cnn_id->Connect($host,$user,$pass,$db_name)) {
		return(1);
	}else{
		die("<br>Cannot connect to MySQL server on '$host'. Please make sure you have specified a valid MySQL 
		database name in 'include/config.php'.");
		
		return(0);
	}
}


function db_execute($sql) {
	global $cnn_id;
	
	if (!$cnn_id) { db_connect(); }
	
	$query = $cnn_id->Execute($sql);
	
	if ($query) {
		return(1); 
	}else{
		return(0);
	}
}


function db_fetch_cell($sql,$col_name = '') {
	global $cnn_id;
	
	if (!$cnn_id) { db_connect(); }
	
	if ($col_name != '') {
		$cnn_id->SetFetchMode(ADODB_FETCH_ASSOC);
	}else{
		$cnn_id->SetFetchMode(ADODB_FETCH_NUM);
	}
	$query = $cnn_id->Execute($sql);
	
	if ($query) {
		if (!$query->EOF) {
			if ($col_name != '') {
				return($query->fields[$col_name]);
			}else{
				return($query->fields[0]);
			}
		}
	}
}

function db_fetch_row($sql) {
	global $cnn_id;
	
	if (!$cnn_id) { db_connect(); }

	$cnn_id->SetFetchMode(ADODB_FETCH_ASSOC);	
	$query = $cnn_id->Execute($sql);
	
	if ($query) {
		if (!$query->EOF) {
			return($query->fields);
		}
	}
}

function db_fetch_assoc($sql) {
	global $cnn_id;
	
	if (!$cnn_id) { db_connect(); }
	
	$data = array();
	$cnn_id->SetFetchMode(ADODB_FETCH_ASSOC);
	$query = $cnn_id->Execute($sql);
	
	if ($query) {
		while ((!$query->EOF) && ($query)) {
			$data{sizeof($data)} = $query->fields;
			$query->MoveNext();
		}
		return($data);
	}
}

function db_fetch_insert_id() {
	global $cnn_id;
	
	return $cnn_id->Insert_ID();
}

function array_to_sql_or($array, $sql_column) {
	/* if the last item is null; pop it off */
	if (empty($array{count($array)-1})) {
		array_pop($array);
	}
	
	if (count($array) > 0) {
		$sql_or = "(";
		
		for ($i=0;($i<count($array));$i++) {
			$sql_or .= $sql_column . "='" . $array[$i] . "'";
			
			if (($i+1) < count($array)) {
				$sql_or .= " OR ";
			}
		}
		
		$sql_or .= ")";
		
		return $sql_or;
	}
}

function db_replace($table_name, $array_items, $keyCols, $autoQuote=false) {
	global $cnn_id;
	$cnn_id->Replace($table_name, $array_items, $keyCols,$autoQuote);

	return $cnn_id->Insert_ID();
}

function sql_save($array_items, $table_name) {
	global $cnn_id;
	
	while (list ($key, $value) = each ($array_items)) {
		if (eregi("(PASSWORD\()|(MD5\()|(NOW\()", $value)) {
			$quote = "";
		}else{
			$quote = "\"";
		}
		$array_items[$key] = "$quote$value$quote";
	}
	
	if (!$cnn_id->Replace($table_name, $array_items, 'id', $autoQuote=false)) { return 0; }
	
	/* get the last AUTO_ID and return it */
	if ($cnn_id->Insert_ID() == "0") {
		if (isset($array_items["id"])) {
			return str_replace("\"", "", $array_items["id"]);
		}else{
			return 0;
		}
	}else{
		return $cnn_id->Insert_ID();
	}
}

?>
