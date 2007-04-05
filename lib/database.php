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

/* db_connect_real - makes a connection to the database server
   @arg $host - the hostname of the database server, 'localhost' if the database server is running
      on this machine
   @arg $user - the username to connect to the database server as
   @arg $pass - the password to connect to the database server with
   @arg $db_name - the name of the database to connect to
   @arg $db_type - the type of database server to connect to, only 'mysql' is currently supported
   @arg $retries - the number a time the server should attempt to connect before failing
   @returns - (bool) '1' for success, '0' for error */
function db_connect_real($host,$user,$pass,$db_name,$db_type, $port = "3306", $retries = 20) {
	global $cnn_id;

	$i = 0;
	$cnn_id = NewADOConnection($db_type);

	$hostport = $host . ":" . $port;

	while ($i <= $retries) {
		if ($cnn_id->PConnect($hostport,$user,$pass,$db_name)) {
			return(1);
		}

		$i++;

		usleep(40000);
	}

	cacti_log("FATAL: Cannot connect to MySQL server on '$host'. Please make sure you have specified a valid MySQL database name in 'include/config.php'.", TRUE);

	return(0);
}

/* db_execute - run an sql query and do not return any output
   @arg $sql - the sql query to execute
   @returns - '1' for success, '0' for error */
function db_execute($sql, $log = TRUE) {
	global $cnn_id;

	if (read_config_option("log_verbosity", TRUE) == POLLER_VERBOSITY_DEBUG) {
		cacti_log("DEBUG: SQL Exec: \"" . str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql))) . "\"", FALSE);
	}

	$query = $cnn_id->Execute($sql);

	if ($query) {
		return(1);
	}else if (($log) || (read_config_option("log_verbosity", TRUE) == POLLER_VERBOSITY_DEBUG)) {
		cacti_log("ERROR: SQL Exec Failed \"" . str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql))) . "\"", FALSE);
		return(0);
	}
}

/* db_fetch_cell - run a 'select' sql query and return the first column of the
     first row found
   @arg $sql - the sql query to execute
   @arg $col_name - use this column name instead of the first one
   @returns - (bool) the output of the sql query as a single variable */
function db_fetch_cell($sql,$col_name = '', $log = TRUE) {
	global $cnn_id;

	if (read_config_option("log_verbosity", TRUE) == POLLER_VERBOSITY_DEBUG) {
		cacti_log("DEBUG: SQL Cell: \"" . str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql))) . "\"", FALSE);
	}

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
	}else if (($log) || (read_config_option("log_verbosity", TRUE) == POLLER_VERBOSITY_DEBUG)) {
		cacti_log("ERROR: SQL Cell Failed \"" . str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql))) . "\"", FALSE);
	}
}

/* db_fetch_row - run a 'select' sql query and return the first row found
   @arg $sql - the sql query to execute
   @returns - the first row of the result as a hash */
function db_fetch_row($sql, $log = TRUE) {
	global $cnn_id;

	if (($log) && (read_config_option("log_verbosity", TRUE) == POLLER_VERBOSITY_DEBUG)) {
		cacti_log("DEBUG: SQL Row: \"" . str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql))) . "\"\n", FALSE);
	}

	$cnn_id->SetFetchMode(ADODB_FETCH_ASSOC);
	$query = $cnn_id->Execute($sql);

	if ($query) {
		if (!$query->EOF) {
			return($query->fields);
		}
	}else if (($log) || (read_config_option("log_verbosity", TRUE) == POLLER_VERBOSITY_DEBUG)) {
		cacti_log("ERROR: SQL Row Failed \"" . str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql))) . "\"", FALSE);
	}
}

/* db_fetch_assoc - run a 'select' sql query and return all rows found
   @arg $sql - the sql query to execute
   @returns - the entire result set as a multi-dimensional hash */
function db_fetch_assoc($sql, $log = TRUE) {
	global $cnn_id;

	if (read_config_option("log_verbosity", TRUE) == POLLER_VERBOSITY_DEBUG) {
		cacti_log("DEBUG: SQL Assoc: \"" . str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql))) . "\"", FALSE);
	}

	$data = array();
	$cnn_id->SetFetchMode(ADODB_FETCH_ASSOC);
	$query = $cnn_id->Execute($sql);

	if ($query) {
		while ((!$query->EOF) && ($query)) {
			$data{sizeof($data)} = $query->fields;
			$query->MoveNext();
		}
		return($data);
	}else if (($log) || (read_config_option("log_verbosity", TRUE) == POLLER_VERBOSITY_DEBUG)) {
		cacti_log("ERROR: SQL Assoc Failed \"" . str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql))) . "\"");
	}
}

/* db_fetch_insert_id - get the last insert_id or auto incriment
   @returns - the id of the last auto incriment row that was created */
function db_fetch_insert_id() {
	global $cnn_id;

	return $cnn_id->Insert_ID();
}

/* array_to_sql_or - loops through a single dimentional array and converts each
     item to a string that can be used in the OR portion of an sql query in the
     following form:
        column=item1 OR column=item2 OR column=item2 ...
   @arg $array - the array to convert
   @arg $sql_column - the column to set each item in the array equal to
   @returns - a string that can be placed in a SQL OR statement */
function array_to_sql_or($array, $sql_column) {
	/* if the last item is null; pop it off */
	if ((empty($array{count($array)-1})) && (sizeof($array) > 1)) {
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

/* db_replace - replaces the data contained in a particular row
   @arg $table_name - the name of the table to make the replacement in
   @arg $array_items - an array containing each column -> value mapping in the row
   @arg $keyCols - the name of the column containing the primary key
   @arg $autoQuote - whether to use intelligent quoting or not
   @returns - the auto incriment id column (if applicable) */
function db_replace($table_name, $array_items, $keyCols) {
	global $cnn_id;
	$cnn_id->Replace($table_name, $array_items, $keyCols);

	return $cnn_id->Insert_ID();
}

/* sql_save - saves data to an sql table
   @arg $array_items - an array containing each column -> value mapping in the row
   @arg $table_name - the name of the table to make the replacement in
   @arg $key_cols - the primary key(s)
   @returns - the auto incriment id column (if applicable) */
function sql_save($array_items, $table_name, $key_cols = "id", $autoinc = true) {
	global $cnn_id;

	while (list ($key, $value) = each ($array_items)) {
		$array_items[$key] = "\"" . sql_sanitize($value) . "\"";
	}

	$replace_result = $cnn_id->Replace($table_name, $array_items, $key_cols, FALSE, $autoinc);

	if ($replace_result == 0) {
		return 0;
	}

	/* get the last AUTO_ID and return it */
	if (($cnn_id->Insert_ID() == "0") || ($replace_result == 1)) {
		if (!is_array($key_cols)) {
			if (isset($array_items[$key_cols])) {
				return str_replace("\"", "", $array_items[$key_cols]);
			}
		}

		return 0;
	}else{
		return $cnn_id->Insert_ID();
	}
}

/* sql_sanitize - removes and quotes unwanted chars in values passed for use in SQL statements
   @arg $value - value to sanitize
   @return - fixed value */
function sql_sanitize($value) {
	//$value = str_replace("'", "''", $value);
	$value = str_replace(";", "", $value);

	return $value;
}

?>
