<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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
   @param $device - the hostname of the database server, 'localhost' if the database server is running
      on this machine
   @param $user - the username to connect to the database server as
   @param $pass - the password to connect to the database server with
   @param $db_name - the name of the database to connect to
   @param $db_type - the type of database server to connect to, only 'mysql' is currently supported
   @param $retries - the number a time the server should attempt to connect before failing
   @returns - (bool) '1' for success, '0' for error */
function db_connect_real($device, $user, $pass, $db_name, $db_type, $port = "3306", $db_ssl = false, $retries = 20) {
	global $database_sessions;

	$flags = null;
	if ($db_ssl && $db_type == "mysql") {
		$flags = MYSQL_CLIENT_SSL;
	}

	$i = 0;
	$device = $device . ":" . $port;
	while ($i <= $retries) {
		$cnn_id = @mysql_pconnect($device, $user, $pass, $flags);
		if ($cnn_id) {
			$database_sessions[$db_name] = $cnn_id;
			if (mysql_select_db($db_name)) {
				return TRUE;
			} else {
				return FALSE;
			}
		}
		$i++;
		usleep(40000);
	}
	//die("FATAL: Cannot connect to MySQL server on '$device'. Please make sure you have specified a valid MySQL database name in 'include/config.php'\n");
	return FALSE;
}

/* db_close - closes the open connection
   @returns - the result of the close command */
function db_close($db_conn = FALSE) {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}
	if (!$db_conn) return FALSE;
	return mysql_close($db_conn);
}

/* db_execute - run an sql query and do not return any output
   @param $sql - the sql query to execute
   @param $log - whether to log error messages, defaults to true
   @returns - '1' for success, '0' for error */
function db_execute($sql, $log = TRUE, $db_conn = FALSE) {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}
	if (!$db_conn) return FALSE;

	$sql = str_replace("\n", '', str_replace("\r", '', str_replace("\t", ' ', $sql)));

	if (read_config_option('log_verbosity') == POLLER_VERBOSITY_DEVDBG) {
		cacti_log("DEVEL: SQL Exec: \"" . $sql . "\"", FALSE);
	}
	$errors = 0;
	while (1) {
		$query = mysql_query($sql, $db_conn);
		$en = mysql_errno($db_conn);
		if ($query) {
			return TRUE;
		} else if ($en == 1049 || $en == 1051) {
			printf('FATAL: Database or Table does not exist');
			exit;
		} else if (($log) || (read_config_option('log_verbosity') >= POLLER_VERBOSITY_DEBUG)) {
			if (substr_count(mysql_error($db_conn), 'Deadlock') || $en == 1213 || $en == 1205) {
				$errors++;
				if ($errors > 30) {
					cacti_log("ERROR: Too many Lock/Deadlock errors occurred! SQL:'" . str_replace("\n", '', str_replace("\r", '', str_replace("\t", ' ', $sql))) ."'", TRUE);
					return FALSE;
				} else {
					usleep(500000);
					continue;
				}
			} else {
				cacti_log("ERROR: A DB Exec Failed!, Error:$en, SQL:\"" . str_replace("\n", '', str_replace("\r", '', str_replace("\t", ' ', $sql))) . "'", FALSE);
				return FALSE;
			}
		}
	}
	return FALSE;
}

/* db_fetch_cell - run a 'select' sql query and return the first column of the
     first row found
   @param $sql - the sql query to execute
   @param $col_name - use this column name instead of the first one
   @param $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function db_fetch_cell($sql, $col_name = '', $log = TRUE, $db_conn = FALSE) {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}
	if (!$db_conn) return FALSE;
	$sql = str_replace("\n", '', str_replace("\r", '', str_replace("\t", ' ', $sql)));

	if (read_config_option('log_verbosity') == POLLER_VERBOSITY_DEVDBG) {
		cacti_log("DEVEL: SQL Cell: \"" . $sql . "\"", FALSE);
	}
	$query = mysql_query($sql, $db_conn);
	$en = mysql_errno($db_conn);
	if ($en == 0 || $en == 1032) {
		$q = mysql_fetch_array($query);
		if ($query) {
			mysql_free_result($query);
		}
		if ($q) {
			if ($col_name != '') {
				return $q[$col_name];
			} else {
				return $q[0];
			}
		}
	}else if ($en == 1049 || $en == 1051) {
		printf('FATAL: Database or Table does not exist');
		exit;
	}else if (($log) || (read_config_option('log_verbosity') >= POLLER_VERBOSITY_DEBUG)) {
		cacti_log("ERROR: SQL Cell Failed!, Error:$en, SQL:\"" . str_replace("\n", '', str_replace("\r", '', str_replace("\t", ' ', $sql))) . "\"", FALSE);
	}
	return FALSE;
}

/* db_fetch_row - run a 'select' sql query and return the first row found
   @param $sql - the sql query to execute
   @param $log - whether to log error messages, defaults to true
   @returns - the first row of the result as a hash */
function db_fetch_row($sql, $log = TRUE, $db_conn = FALSE) {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}
	if (!$db_conn) return FALSE;

	$sql = str_replace("\n", '', str_replace("\r", '', str_replace("\t", ' ', $sql)));

	if (($log) && (read_config_option('log_verbosity') == POLLER_VERBOSITY_DEVDBG)) {
		cacti_log("DEVEL: SQL Row: \"" . $sql . "\"", FALSE);
	}

	$query = mysql_query($sql, $db_conn);
	$en = mysql_errno($db_conn);

	if ($en == 0 || $en == 1032) {
		$q = mysql_fetch_assoc($query);
		mysql_free_result($query);
		if ($q) {
			return $q;
		} else {
			return array();
		}
	}else if ($en == 1049 || $en == 1051) {
		printf('FATAL: Database or Table does not exist');
		exit;
	}else if (($log) || (read_config_option('log_verbosity') >= POLLER_VERBOSITY_DEBUG)) {
		cacti_log("ERROR: SQL Row Failed!, Error:$en, SQL:\"" . str_replace("\n", '', str_replace("\r", '', str_replace("\t", ' ', $sql))) . "\"", FALSE);
	}
	if ($query) {
		mysql_free_result($query);
	}
	return array();
}

/* db_fetch_assoc - run a 'select' sql query and return all rows found
   @param $sql - the sql query to execute
   @param $log - whether to log error messages, defaults to true
   @returns - the entire result set as a multi-dimensional hash */
function db_fetch_assoc($sql, $log = TRUE, $db_conn = FALSE) {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}
	if (!$db_conn) return FALSE;

	$sql = str_replace("\n", '', str_replace("\r", '', str_replace("\t", ' ', $sql)));

	if (read_config_option('log_verbosity') == POLLER_VERBOSITY_DEVDBG) {
		cacti_log("DEVEL: SQL Assoc: \"" . $sql . "\"", FALSE);
	}

	$data = array();
	$query = mysql_query($sql, $db_conn);
	$en = mysql_errno($db_conn);

	if ($en == 0 || $en == 1032) {
		$a = array();
		while ($row = mysql_fetch_assoc($query)) {
			$a[] = $row;
		}
		mysql_free_result($query);
		return $a;
	}else if ($en == 1049 || $en == 1051) {
		printf('FATAL: Database or Table does not exist');
		exit;
	}else if (($log) || (read_config_option('log_verbosity') >= POLLER_VERBOSITY_DEBUG)) {
		cacti_log("ERROR: SQL Assoc Failed!, Error:$en, SQL:\"" . str_replace("\n", '', str_replace("\r", '', str_replace("\t", ' ', $sql))) . "\"");
	}
	if ($query) {
		mysql_free_result($query);
	}
	return array();
}

/* db_fetch_insert_id - get the last insert_id or auto incriment
   @returns - the id of the last auto incriment row that was created */
function db_fetch_insert_id($db_conn = FALSE) {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}
	if ($db_conn) {
		return mysql_insert_id($db_conn);
	}
	return FALSE;
}

/* array_to_sql_or - loops through a single dimentional array and converts each
     item to a string that can be used in the OR portion of an sql query in the
     following form:
        column=item1 OR column=item2 OR column=item2 ...
   @param $array - the array to convert
   @param $sql_column - the column to set each item in the array equal to
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
   @param $table_name - the name of the table to make the replacement in
   @param $array_items - an array containing each column -> value mapping in the row
   @param $keyCols - the name of the column containing the primary key
   @param $autoQuote - whether to use intelligent quoting or not
   @returns - the auto incriment id column (if applicable) */
function db_replace($table_name, $array_items, $keyCols, $db_conn = FALSE) {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}

	if (read_config_option("log_verbosity") == POLLER_VERBOSITY_DEVDBG) {
		cacti_log("DEVEL: SQL Replace on table '$table_name': \"" . serialize($array_items) . "\"", FALSE);
	}

	_db_replace($db_conn, $table_name, $array_items, $keyCols);
	return db_fetch_insert_id($db_conn);
}


// FIXME:  Need to Rename and cleanup a bit

function _db_replace($db_conn, $table, $fieldArray, $keyCol, $has_autoinc) {


	if (!is_array($keyCol)) {
		$keyCol = array($keyCol);
	}

	$sql  = "INSERT INTO `$table` (";
	$sql2 = '';
	$sql3 = '';

	$first = true;
	$first3 = true;
	foreach($fieldArray as $k => $v) {
		if (!$first) {
			$sql .= ', ';
			$sql2 .= ', ';
		}
		$sql .= "`$k`";
		$sql2 .= $v;
		$first = false;
		if (in_array($k, $keyCol)) continue; // skip UPDATE if is key
		if (!$first3) {
			$sql3 .= ', ';
		}
		$sql3 .= "`$k`=VALUES(`$k`)";
		$first3 = false;
	}
	$sql .= ") VALUES ($sql2) ON DUPLICATE KEY UPDATE $sql3";
	@db_execute($sql);
	return db_fetch_insert_id();
}

/* sql_save - saves data to an sql table
   @param $array_items - an array containing each column -> value mapping in the row
   @param $table_name - the name of the table to make the replacement in
   @param $key_cols - the primary key(s)
   @returns - the auto incriment id column (if applicable) */
function sql_save($array_items, $table_name, $key_cols = "id", $autoinc = TRUE, $db_conn = FALSE) {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}

	if (read_config_option('log_verbosity') == POLLER_VERBOSITY_DEVDBG) {
		cacti_log("DEVEL: SQL Save on table '$table_name': \"" . serialize($array_items) . "\"", FALSE);
	}

	while (list($key, $value) = each($array_items)) {
		$array_items[$key] = "\"" . sql_sanitize($value) . "\"";
	}

	$replace_result = _db_replace($db_conn, $table_name, $array_items, $key_cols, $autoinc);

	if ($replace_result === false) {
		cacti_log("ERROR: SQL Save Command Failed for Table '$table_name'.  Error was '" . mysql_error($db_conn) . "'", false);
		return FALSE;
	}

	/* get the last AUTO_ID and return it */
	if (!$replace_result || db_fetch_insert_id($db_conn) == '0') {
		if (!is_array($key_cols)) {
			if (isset($array_items[$key_cols])) {
				return str_replace("\"", '', $array_items[$key_cols]);
			}
		}
		return FALSE;
	} else {
		return $replace_result;
	}
}

/* sql_sanitize - removes and quotes unwanted chars in values passed for use in SQL statements
   @param $value - value to sanitize
   @return - fixed value */
function sql_sanitize($value) {
	//$value = str_replace("'", "''", $value);
	$value = str_replace(";", "\;", $value);

	return $value;
}

/* sql_column_exists - checks if a named column exists in the table specified
   @param $table_name - table to check
   @param $column_name - column name
   @return true or false; */
function sql_column_exists($table_name, $column_name, $db_conn = "") {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}

	$columns = db_fetch_assoc("SHOW COLUMNS FROM `$table_name`", false);

	foreach ($columns as $column) {
		if ($column_name === $column['name']) {
			return true;
		}
	}

	return false;
}

/* sql_function_timestamp - abstracts timestamp function across databases
   @return - fixed value */
function sql_function_timestamp($db_conn = "") {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}

	return 'NOW()';

	//if (isset($db_conn->sysTimeStamp)) {
	//	return $db_conn->sysTimeStamp;
	//}

	//return "'".date('Y-m-d H:i:s')."'";
}

/* sql_function_substr - abstracts substring function across databases
   @return - fixed value */
function sql_function_substr($db_conn = "") {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}

	return 'substring';

//	if (isset($db_conn->substr)) {
//		return $db_conn->substr;
//	}

//	switch($db_conn->databaseType) {
//		case 'oci805':
//		case 'oci8':
//		case 'oci8po':
//		case 'oracle':
//			return 'substr';
//			break;
//		case 'postgres64':
//		case 'postgres7':
//		case 'postgres':
//			return 'substr';
//			break;
//		case 'db2':
//		case 'fbsql':
//		case 'firebird':
//		case 'ibase':
//			default:
//			return 'substr';
//	}
}

/* sql_function_concat - abstracts concatenation function across databases
   @return - fixed value */
function sql_function_concat($db_conn = "") {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}

	//if (method_exists($db_conn, 'Concat')) {
	//	$args = func_get_args();
	//	return call_user_func_array(array(&$db_conn, 'Concat'), $args);
	//}

	return "concat('".implode("','", func_get_args())."')";
}

/* sql_function_replace - abstracts replace function across databases
   @return - fixed value */
function sql_function_replace($db_conn = "") {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}

	return 'replace';

//	switch($db_conn->databaseType) {
//		case 'mssql':
//		case 'mssqlpo':
//			return 'replace';
//			break;
//		case 'mysql':
//		case 'mysqli':
//		case 'mysqlt':
//			return 'replace';
//			break;
//		case 'oci805':
//		case 'oci8':
//		case 'oci8po':
//		case 'oracle':
//			return 'replace';
//			break;
//		case 'postgres64':
//		case 'postgres7':
//		case 'postgres':
//			return 'replace';
//			break;
//		case 'db2':
//		case 'firebird':
//		case 'ibase':
//		default:
//			return 'replace';
//	}
}

/* sql_function_dateformat - abstracts dateformat function across databases
   @return - fixed value */
function sql_function_dateformat($fmt, $col = false, $db_conn = "") {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}

	return 'date_format';

//	if (method_exists($db_conn, 'SQLDate')) {
//		return call_user_func_array(array(&$db_conn, 'SQLDate'), array($fmt,$col));
//	}
//
//	switch($db_conn->databaseType) {
//		default:
//			return 'date_format';
//	}
}

function db_qstr($s, $db_conn = '') {
	global $database_sessions, $database_default;

	/* check for a connection being passed, if not use legacy behavior */
	if (!$db_conn) {
		$db_conn = $database_sessions[$database_default];
	}

	$replaceQuote = "\\'";

	if (is_null($s)) return 'NULL';

	if (is_resource($db_conn))
		return "'" . mysql_real_escape_string($s, $db_conn) . "'";
	if ($replaceQuote == '\\') {
		$s = str_replace(array('\\', "\0"), array('\\\\', "\\\0"), $s);
	}
	return  "'" . str_replace("'",$replaceQuote, $s) . "'"; 
}






