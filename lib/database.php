<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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
function db_connect_real($host, $user, $pass, $db_name, $db_type, $port = "3306", $db_ssl = false, $retries = 20) {
	global $cnn_id;

	$i = 0;
	$dsn = "$db_type://" . rawurlencode($user) . ":" . rawurlencode($pass) . "@" . rawurlencode($host) . "/" . rawurlencode($db_name) . "?persist";

	if ($db_ssl && $db_type == "mysql") {
		$dsn .= "&clientflags=" . MYSQL_CLIENT_SSL;
	}elseif ($db_ssl && $db_type == "mysqli") {
		$dsn .= "&clientflags=" . MYSQLI_CLIENT_SSL;
	}

	if ($port != "3306") {
		$dsn .= "&port=" . $port;
	}

	while ($i <= $retries) {
		$cnn_id = ADONewConnection($dsn);
		if ($cnn_id) {
			return($cnn_id);
		}

		$i++;

		usleep(40000);
	}

	die("FATAL: Cannot connect to MySQL server on '$host'. Please make sure you have specified a valid MySQL database name in 'include/config.php'\n");

	return(0);
}

/* db_close - closes the open connection
   @returns - the result of the close command */
function db_close($db_conn = FALSE) {
	global $cnn_id;

	if (!$db_conn) {
		return $cnn_id->Close();
	}else{
		return $db_conn->Close();
	}
}

/* db_execute - run an sql query and do not return any output
   @arg $sql - the sql query to execute
   @arg $log - whether to log error messages, defaults to true
   @returns - '1' for success, '0' for error */
function db_execute($sql, $log = TRUE, $db_conn = FALSE) {
	global $cnn_id;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $cnn_id;
	}
	$sql = str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql)));

	if (read_config_option("log_verbosity") == POLLER_VERBOSITY_DEVDBG) {
		cacti_log("DEVEL: SQL Exec: \"" . $sql . "\"", FALSE);
	}

	$errors = 0;
	while (1) {
		$query = $db_conn->Execute($sql);

		if (($db_conn->ErrorNo() == 0) || ($db_conn->ErrorNo() == 1032)) {
			return(1);
		}else if (($db_conn->ErrorNo() == 1049) || ($db_conn->ErrorNo() == 1051)) {
			printf("FATAL: Database or Table does not exist");
			exit;
		}else if (($log) || (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG)) {
			if ((substr_count($db_conn->ErrorMsg(), "Deadlock")) || ($db_conn->ErrorNo() == 1213) || ($db_conn->ErrorNo() == 1205)) {
				$errors++;
				if ($errors > 30) {
					cacti_log("ERROR: Too many Lock/Deadlock errors occurred! SQL:'" . str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql))) ."'", TRUE);
					return(0);
				}else{
					usleep(500000);
					continue;
				}
			}else{
				cacti_log("ERROR: A DB Exec Failed!, Error:'" . $db_conn->ErrorNo() . "', SQL:\"" . str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql))) . "'", FALSE);
				return(0);
			}
		}
	}
}

/* db_fetch_cell - run a 'select' sql query and return the first column of the
     first row found
   @arg $sql - the sql query to execute
   @arg $col_name - use this column name instead of the first one
   @arg $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function db_fetch_cell($sql, $col_name = '', $log = TRUE, $db_conn = FALSE) {
	global $cnn_id;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $cnn_id;
	}

	$sql = str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql)));

	if (read_config_option("log_verbosity") == POLLER_VERBOSITY_DEVDBG) {
		cacti_log("DEVEL: SQL Cell: \"" . $sql . "\"", FALSE);
	}

	if ($col_name != '') {
		$db_conn->SetFetchMode(ADODB_FETCH_ASSOC);
	}else{
		$db_conn->SetFetchMode(ADODB_FETCH_NUM);
	}

	$query = $db_conn->Execute($sql);

	if (($db_conn->ErrorNo() == 0) || ($db_conn->ErrorNo() == 1032)) {
		if (!$query->EOF) {
			if ($col_name != '') {
				$column = $query->fields[$col_name];
			}else{
				$column = $query->fields[0];
			}

			$query->close();

			return($column);
		}
	}else if (($db_conn->ErrorNo() == 1049) || ($db_conn->ErrorNo() == 1051)) {
		printf("FATAL: Database or Table does not exist");
		exit;
	}else if (($log) || (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG)) {
		cacti_log("ERROR: SQL Cell Failed!, Error:'" . $db_conn->ErrorNo() . "', SQL:\"" . str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql))) . "\"", FALSE);
	}
}

/* db_fetch_row - run a 'select' sql query and return the first row found
   @arg $sql - the sql query to execute
   @arg $log - whether to log error messages, defaults to true
   @returns - the first row of the result as a hash */
function db_fetch_row($sql, $log = TRUE, $db_conn = FALSE) {
	global $cnn_id;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $cnn_id;
	}

	$sql = str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql)));

	if (($log) && (read_config_option("log_verbosity") == POLLER_VERBOSITY_DEVDBG)) {
		cacti_log("DEVEL: SQL Row: \"" . $sql . "\"", FALSE);
	}

	$db_conn->SetFetchMode(ADODB_FETCH_ASSOC);
	$query = $db_conn->Execute($sql);

	if (($db_conn->ErrorNo() == 0) || ($db_conn->ErrorNo() == 1032)) {
		if (!$query->EOF) {
			$fields = $query->fields;

			$query->close();

			return($fields);
		}
	}else if (($db_conn->ErrorNo() == 1049) || ($db_conn->ErrorNo() == 1051)) {
		printf("FATAL: Database or Table does not exist");
		exit;
	}else if (($log) || (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG)) {
		cacti_log("ERROR: SQL Row Failed!, Error:'" . $db_conn->ErrorNo() . "', SQL:\"" . str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql))) . "\"", FALSE);
	}
}

/* db_fetch_assoc - run a 'select' sql query and return all rows found
   @arg $sql - the sql query to execute
   @arg $log - whether to log error messages, defaults to true
   @returns - the entire result set as a multi-dimensional hash */
function db_fetch_assoc($sql, $log = TRUE, $db_conn = FALSE) {
	global $cnn_id;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $cnn_id;
	}

	$sql = str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql)));

	if (read_config_option("log_verbosity") == POLLER_VERBOSITY_DEVDBG) {
		cacti_log("DEVEL: SQL Assoc: \"" . $sql . "\"", FALSE);
	}

	$data = array();
	$db_conn->SetFetchMode(ADODB_FETCH_ASSOC);
	$query = $db_conn->Execute($sql);

	if (($db_conn->ErrorNo() == 0) || ($db_conn->ErrorNo() == 1032)) {
		while ((!$query->EOF) && ($query)) {
			$data{sizeof($data)} = $query->fields;
			$query->MoveNext();
		}

		$query->close();

		return($data);
	}else if (($db_conn->ErrorNo() == 1049) || ($db_conn->ErrorNo() == 1051)) {
		printf("FATAL: Database or Table does not exist");
		exit;
	}else if (($log) || (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG)) {
		cacti_log("ERROR: SQL Assoc Failed!, Error:'" . $db_conn->ErrorNo() . "', SQL:\"" . str_replace("\n", "", str_replace("\r", "", str_replace("\t", " ", $sql))) . "\"");
	}
}

/* db_fetch_insert_id - get the last insert_id or auto incriment
   @returns - the id of the last auto incriment row that was created */
function db_fetch_insert_id($db_conn = FALSE) {
	global $cnn_id;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $cnn_id;
	}

	return $db_conn->Insert_ID();
}

/* array_to_sql_or - loops through a single dimensional array and creates an sql like
 *                   (sql_column in (value1, value2, value3, ...))
   @arg $array - the array to convert
   @arg $sql_column - the column to set each item in the array equal to
   @returns - a string that can be placed in a SQL OR statement */
function array_to_sql_or($array, $sql_column) {
	/* if the last item is null; pop it off */
	if ((empty($array{count($array)-1})) && (sizeof($array) > 1)) {
		array_pop($array);
	}

	if (count($array) > 0) {
		$sql_or = "($sql_column IN(";

		for ($i=0;($i<count($array));$i++) {
			if (is_array($array[$i]) && array_key_exists($sql_column, $array[$i])) {
				$sql_or .= (($i == 0) ? "'":",'") . $array[$i][$sql_column] . "'";
			} else {
				$sql_or .= (($i == 0) ? "'":",'") . $array[$i] . "'";
			}
		}

		$sql_or .= "))";

		return $sql_or;
	}
}

/* db_replace - replaces the data contained in a particular row
   @arg $table_name - the name of the table to make the replacement in
   @arg $array_items - an array containing each column -> value mapping in the row
   @arg $keyCols - the name of the column containing the primary key
   @arg $autoQuote - whether to use intelligent quoting or not
   @returns - the auto incriment id column (if applicable) */
function db_replace($table_name, $array_items, $keyCols, $db_conn = FALSE) {
	global $cnn_id;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $cnn_id;
	}

	$db_conn->Replace($table_name, $array_items, $keyCols);

	return $db_conn->Insert_ID();
}

/* sql_save - saves data to an sql table
   @arg $array_items - an array containing each column -> value mapping in the row
   @arg $table_name - the name of the table to make the replacement in
   @arg $key_cols - the primary key(s)
   @returns - the auto incriment id column (if applicable) */
function sql_save($array_items, $table_name, $key_cols = "id", $autoinc = TRUE, $db_conn = FALSE) {
	global $cnn_id;

	if (read_config_option("log_verbosity") == POLLER_VERBOSITY_DEVDBG) {
		cacti_log("DEVEL: SQL Save on table '$table_name': \"" . serialize($array_items) . "\"", FALSE);
	}

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $cnn_id;
	}

	while (list($key, $value) = each($array_items)) {
		$array_items[$key] = "\"" . sql_sanitize($value) . "\"";
	}

	$replace_result = $db_conn->Replace($table_name, $array_items, $key_cols, FALSE, $autoinc);

	if ($replace_result == 0) {
		cacti_log("ERROR: SQL Save Command Failed for Table '$table_name'.  Error was '" . $cnn_id->ErrorMsg() . "'", false);
		return 0;
	}

	/* get the last AUTO_ID and return it */
	if (($db_conn->Insert_ID() == "0") || ($replace_result == 1)) {
		if (!is_array($key_cols)) {
			if (isset($array_items[$key_cols])) {
				return str_replace("\"", "", $array_items[$key_cols]);
			}
		}

		return 0;
	}else{
		return $db_conn->Insert_ID();
	}
}

/* sql_sanitize - removes and quotes unwanted chars in values passed for use in SQL statements
   @arg $value - value to sanitize
   @return - fixed value */
function sql_sanitize($value) {
	//$value = str_replace("'", "''", $value);
	$value = str_replace(";", "\;", $value);

	return $value;
}

?>
