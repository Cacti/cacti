<?/*
+-------------------------------------------------------------------------+
| raXnet Database Wrappers                                                |
+-------------------------------------------------------------------------+
| This code was crafted by Ian Berry, make sure any questions             |
| about the structure or integrity of this code be directed to:           |
| - rax@kuhncom.net                                                       |
| - iberry@onion.dyndns.org                                                 |
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
	    	die("Cannot find the database $database");
	    	return(0);
		}
    }else{
		die("Cannot connect to MySQL server on $database_hostname");
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
				$data[sizeof($data)] = $row;
	    	}
	    	return($data);
		}
    }
}

function get_table_fields($table) {
    global $cnn_id;
    if (!$cnn_id) { db_connect(); }
  	static $cols;
	
	if (! is_array($cols[$table])) {
		$sql2="DESC $table";
	    $columnquery=MYSQL_QUERY($sql2);
		
	    # determines weather or not to put the value in ""'s
		
	    while ($col = MYSQL_FETCH_ARRAY($columnquery)){
	      	$cols[$table][$col[Field]] = $col;
			if (strstr($col[Type],"int")      ||
				strstr($col[Type],"bigint")   ||
				strstr($col[Type],"tinyint")  ||
				strstr($col[Type],"float")    ||
				strstr($col[Type],"decimal")  ||
				strstr($col[Type],"double")   ||
				strstr($col[Type],"real")     ||
				strstr($col[Type],"numeric")  ){
	       		$cols[$table][$col[Field]][quoted] = 0;
	     	}else{
	       		$cols[$table][$col[Field]][quoted] = 1;
	     	}
	    }
	}
	
	return($cols[$table]);
}


##  This function adds records to the specified table based on the data supplied in $newdata.

##  NOTE:  a field value that starts with &mysql- denotes a mysql function to be used.
##  The '&mysql-' will be stripped and the resulting string WILL NOT be quoted.

function addrec ($newdata, $table) {
    global $cnn_id,$defaults,$fieldlist,$fields;
    if (!$cnn_id) { db_connect(); }
   	
	$sql="INSERT INTO $table VALUES(";
   
  	$cols = get_table_fields($table);
	
  	foreach (array_keys($cols) as $key) {
	    if (! isset($newdata[$key])) { 
	      	$newdata[$key] = $cols[$key]["Default"];
	    }
	    
		if ($cols[$key][quoted]) {
	      	if (! isset($newdata[$key]) || $newdata[$key] == '') { 
	        	$sql .= "NULL,";
	      	}else{
	        	if (preg_match("/^\&mysql-/",$newdata[$key])) {
		  			$newdata[$key] = preg_replace("/^&mysql-/","",$newdata[$key]);
	          		$sql .= "$newdata[$key],";
				}else{
	          		$sql .= "\"$newdata[$key]\",";
				}
	      	}
	    }else{
	      	if (! isset($newdata[$key]) || $newdata[$key] == '') { $newdata[$key] = "0"; }
	      	$sql .= "$newdata[$key],";
	    }
	}
	
	# this is to replace the last , with nothing
	$sql = ereg_replace(",$","",$sql).")";
	
	##  Run the query.  If it fails, return -1.  If it works, either return the value it
	##  used for an AUTO_INCREMENT field (if there was one) or just 0.  I'd return a '1' 
	##  but that's a valid id number to return so '0' it must be.
	if ($addquery = MYSQL_QUERY($sql)) {
		$sql = "SELECT LAST_INSERT_ID()";
	    $lastquery = mysql_query($sql);
	    $res_tmp = mysql_fetch_array($lastquery);
	    $result = $res_tmp[0];
	    
		if (! $result) { $result =  0; }
	    return $result;
	}else{
		return -1;
	}
}


##  This function is very similar to addrec but instead of just adding $newdata to the
##  specified table, it compares the data in $newdata to the data in $olddata and selects
##  which fields to change.  Also an argument is the WHERE clause to use in the query with 
##  the word 'WHERE' (i.e.:  'id = 45', for example).  We also automatically decide which 
##  fields get quotes by what kind of field it is.

##  NOTE:  a field value that starts with &mysql- denotes a mysql function to be used.
##  The '&mysql-' will be stripped and the resulting string WILL NOT be quoted.

function modrec ($olddata, $newdata, $table, $where) {
	global $cnn_id,$defaults,$fieldlist,$fields;
    if (!$cnn_id) {   db_connect(); }
  	$cols = get_table_fields($table);
  	
  	$sql="UPDATE $table SET ";
  	
	foreach(array_keys($cols) as $key){
    	if ($olddata[$key] != $newdata[$key]) {
      		++$fieldchange;
      		if ($cols[$key][quoted]) {
        		if (preg_match("/^\&mysql-/",$newdata[$key])) {
	  				$newdata[$key] = preg_replace("/^&mysql-/","",$newdata[$key]);
          			$update .= "$key = $newdata[$key],";
				}else{
          			$update .= "$key = \"$newdata[$key]\",";
				}
      		}else{
        		$update .= "$key = $newdata[$key],";
      		}
    	}
  	}
	
  	if ($update) {
	    $sql .= $update;
		
	    # this is to replace the last , with nothing
	    $sql = ereg_replace(",$","",$sql)." WHERE $where";
		
	    ##  Run the query.  Return 0 if there were no changes to make, -1 for a failed query, 
	    ##  or a number representing the number of fields that were changed.
	    if ($modquery = MYSQL_QUERY($sql)) {
			return $fieldchange;
	    }else{
			return -1;
	    }
		
	}else{
		return 0;
	}
}

##  This function is delete records from $table based on the passed WHERE clause ($where).
function delrec ($table, $where) {
    global $cnn_id;
    if (!$cnn_id) { db_connect(); }
	$sql = "DELETE FROM $table WHERE $where";
	
	##  Run the query.  Return 0 if there were no changes to make, -1 for a failed query, 
	##  or a number representing the number of fields that were changed.
	if ($delquery = MYSQL_QUERY($sql)) {
		return 1;
	}else{
		return -1;
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
	
	db_execute($sql_save);
	
	/* get the last AUTO_ID and return it */
	return db_fetch_cell("select LAST_INSERT_ID()");
}

?>
