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

/* if no database name is specified; used default */
if ($database == "") { $database = $database_default; }
db_connect();

function db_connect() {
	global $database_hostname,$database_username,$database_password,$database;
	db_connect_real($database_hostname,$database_username,$database_password,$database); 
}

function db_connect_real($host,$user,$pass,$db_name) {
	global $cnn_id;
	
	$cnn_id = mysql_connect($host,$user,$pass);
	
	if ($cnn_id) {
		if (mysql_selectdb($db_name)) {
			return(1);
		}else{
			die("Cannot find the database $db_name");
			return(0);
		}
	}else{
		die("Cannot connect to MySQL server on $host");
		return(0);
	}
}


function db_execute($sql) {
	global $cnn_id;
	
	if (!$cnn_id) { db_connect(); }
	
	$query = mysql_query($sql);
	
	if ($query) {
		return(1); 
	}else{
		return(0);
	}
}


function db_fetch_cell($sql,$col_name = '') {
	global $cnn_id;
	
	if (!$cnn_id) { db_connect(); }
	
	$row = array();
	$query = mysql_query($sql);
	
	if ($query) {
		$rows = mysql_numrows($query);
		
		if ($rows > 0) {
			if ($col_name != '') {
				$row = mysql_fetch_assoc($query);
				return($row[$col_name]);
			}else{
				return(mysql_result($query,0,0));
			}
		}
	}
}

function db_fetch_row($sql) {
	global $cnn_id;
	
	if (!$cnn_id) { db_connect(); }
	
	$row = array();
	$query = mysql_query($sql);
	
	if ($query) {
		$rows = mysql_numrows($query);
		
		if ($rows > 0) {
			return(mysql_fetch_assoc($query));
		}
	}
}

function db_fetch_assoc($sql) {
	global $cnn_id;
	
	if (!$cnn_id) { db_connect(); }
	
	$data = array();
	$query = mysql_query($sql);
	
	if ($query) {
		$rows = mysql_numrows($query);
		
		if ($rows > 0) {
			while($row = mysql_fetch_assoc($query)) {
				$data{sizeof($data)} = $row;
			}
			
			return($data);
		}
	}
}

function array_to_sql_or($array, $sql_column) {
	/* if the last item is null; pop it off */
	if (empty($array{count($array)})) {
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
	$sql_save .= "replace into $table_name (";
	
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
	if (db_fetch_cell("select LAST_INSERT_ID()") == "0") {
		if (isset($array_items["id"])) {
			return $array_items["id"];
		}else{
			return 1;
		}
	}else{
		return db_fetch_cell("select LAST_INSERT_ID()");
	}
}

?>
