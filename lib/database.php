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

include('adodb/adodb.inc.php');
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
	
	$query = $cnn_id->Execute($sql);
	
	if ($query) {
		if (!$query->EOF) {
			if ($col_name != '') {
				$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
				return($query->fields[$col_name]);
			}else{
				$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
				return($query->fields[0]);
			}
		}
	}
}

function db_fetch_row($sql) {
	global $cnn_id;
	
	if (!$cnn_id) { db_connect(); }
	
	$query = $cnn_id->Execute($sql);
	
	if ($query) {
		if (!$query->EOF) {
			$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
			return($query->fields);
		}
	}
}

function db_fetch_assoc($sql) {
	global $cnn_id;
	
	if (!$cnn_id) { db_connect(); }
	
	$data = array();
	$query = $cnn_id->Execute($sql);
	
	if ($query) {
		while ((!$query->EOF) && ($query)) {
			$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
			$data{sizeof($data)} = $query->fields;
			$query->MoveNext();
		}
		return($data);
	}
}

function array_to_sql_or($array, $sql_column) {
	/* if the last item is null; pop it off */
	if (empty($array{count($array)-1})) {
		array_pop($array);
	}
	
	if (count($array) > 0) {
		$sql_or = "(";
		
		for ($i=0;($i<count($array));$i++) {
			$sql_or .= $sql_column . "=" . $array[$i];
			
			if (($i+1) < count($array)) {
				$sql_or .= " OR ";
			}
		}
		
		$sql_or .= ")";
		
		return $sql_or;
	}
}

function sql_save($array_items, $table_name) {
	$sql_save = "replace into $table_name (";
	
	$sql_save_fields = ""; $sql_save_values = "";
	
	while (list($field_name, $field_value) = each($array_items)) {
	 	$sql_save_fields .= "$field_name,";
		
		if (eregi("(PASSWORD\()|(MD5\()|(NOW\()", $field_value)) {
			$quote = "";
		}else{
			$quote = "\"";
		}
		
		$sql_save_values .= "$quote$field_value$quote,";
	}
	
	/* chop off the last ',' */
	$sql_save_fields = substr($sql_save_fields, 0, (strlen($sql_save_fields)-1));
	$sql_save_values = substr($sql_save_values, 0, (strlen($sql_save_values)-1));
	
	/* form the SQL string */
	$sql_save = "replace into $table_name ($sql_save_fields) values ($sql_save_values)";
	
	//print $sql_save . "<br>";
	if (!db_execute($sql_save)) { return 0; }
	
	/* get the last AUTO_ID and return it */
	if ($cnn_id->Insert_ID() == "0") {
		if (isset($array_items["id"])) {
			return $array_items["id"];
		}else{
			return 1;
		}
	}else{
		return $cnn_id->Insert_ID();
	}
}

?>
