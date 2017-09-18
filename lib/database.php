<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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
function db_connect_real($device, $user, $pass, $db_name, $db_type = 'mysql', $port = '3306', $db_ssl = false, $retries = 20) {
	global $database_sessions, $database_total_queries;
	$database_total_queries = 0;

	$i = 0;
	if (isset($database_sessions["$device:$port:$db_name"])) {
		return $database_sessions["$device:$port:$db_name"];
	}

	$odevice = $device;

	$flags = array();
	if ($db_type == 'mysql') {
		// Using 'localhost' will force unix sockets mode, which breaks when attempting to use mysql on a different port
		if ($device == 'localhost' && $port != '3306') {
			$device = '127.0.0.1';
		}

		$flags[PDO::ATTR_PERSISTENT] = true;
		$flags[PDO::MYSQL_ATTR_FOUND_ROWS] = true;
		if ($db_ssl) {
			// PDO requires paths to certificates for SSL support, will have to figure out the best way to handle this
			// I believe they can instead setup these parameters in their mysql config file in [client]
			//$flags[PDO::MYSQL_ATTR_SSL_KEY]  = '/path/to/client-key.pem';
			//$flags[PDO::MYSQL_ATTR_SSL_CERT] = '/path/to/client-cert.pem';
			//$flags[PDO::MYSQL_ATTR_SSL_CA]   = '/path/to/ca-cert.pem';
		}
	}

	while ($i <= $retries) {
		try {
			if (file_exists($device)) {
				$cnn_id = new PDO("$db_type:unix_socket=$device;dbname=$db_name;charset=utf8", $user, $pass, $flags);
			} else {
				$cnn_id = new PDO("$db_type:host=$device;port=$port;dbname=$db_name;charset=utf8", $user, $pass, $flags);
			}
			$cnn_id->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

			$bad_modes = array(
				'STRICT_TRANS_TABLES', 
				'STRICT_ALL_TABLES', 
				'TRADITIONAL', 
				'NO_ZERO_DATE', 
				'NO_ZERO_IN_DATE', 
				'ONLY_FULL_GROUP_BY', 
				'NO_AUTO_VALUE_ON_ZERO'
			);

			$database_sessions["$odevice:$port:$db_name"] = $cnn_id;

			// Get rid of bad modes
			$modes = explode(',', db_fetch_cell('SELECT @@sql_mode'));

			foreach($modes as $mode) {
				if (array_search($mode, $bad_modes) === false) {
					$new_modes[] = $mode;
				}
			}
			$sql_mode = implode(',', $new_modes);

			db_execute('SET SESSION sql_mode = "' . $sql_mode . '"');

			return $cnn_id;
		} catch (PDOException $e) {
			// Must catch this exception or else PDO will display an error with our username/password
			//print $e->getMessage();
			//exit;
		}

		$i++;
		usleep(40000);
	}

	return FALSE;
}

/* db_close - closes the open connection
   @returns - the result of the close command */
function db_close($db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$db_conn = null;
	$database_sessions["$database_hostname:$database_port:$database_default"] = null;

	return true;
}

/* db_execute - run an sql query and do not return any output
   @param $sql - the sql query to execute
   @param $log - whether to log error messages, defaults to true
   @returns - '1' for success, '0' for error */
function db_execute($sql, $log = true, $db_conn = false) {
	return db_execute_prepared($sql, array(), $log, $db_conn);
}

/* db_execute_prepared - run an sql query and do not return any output
   @param $sql - the sql query to execute
   @param $log - whether to log error messages, defaults to true
   @returns - '1' for success, '0' for error */
function db_execute_prepared($sql, $parms = array(), $log = true, $db_conn = false) {
	global $database_sessions, $database_default, $config, $database_hostname, $database_port, $database_total_queries;
	$database_total_queries++;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$sql = db_strip_control_chars($sql);

	cacti_log('DEVEL: SQL Exec: "' . $sql . '"', false, 'DBCALL', POLLER_VERBOSITY_DEVDBG);

	$errors = 0;
	$db_conn->affected_rows = 0;

	while (true) {
		$query = $db_conn->prepare($sql);

		$query->execute($parms);
		if ($query->errorCode()) {
			$errorinfo = $query->errorInfo();
			$en = $errorinfo[1];
		} elseif ($db_conn->errorCode()) {
			$errorinfo = $db_conn->errorInfo();
			$en = $errorinfo[1];
		} else {
			$en = '';
		}

		if ($en == '') {
			// With PDO, we have to free this up
			$db_conn->affected_rows = $query->rowCount();
			$query->closeCursor();
			unset($query);

			return true;
		} elseif ($log) {
			if ($en == 1213 || $en == 1205) {
				$errors++;
				if ($errors > 30) {
					cacti_log("ERROR: Too many Lock/Deadlock errors occurred! SQL:'" . $sql . "'", true, 'DBCALL', POLLER_VERBOSITY_DEBUG);

					return false;
				} else {
					usleep(500000);

					continue;
				}
			} else if ($en == 1153) {
				if (strlen($sql) > 1024) {
					$sql = substr($sql, 0, 1024) . '...';
				}

				cacti_log("ERROR: A DB Exec Failed!, Error:$en, SQL:'" . $sql . "'", false, 'DBCALL', POLLER_VERBOSITY_DEBUG);
				cacti_log('ERROR: A DB Exec Failed!, Error: ' . $errorinfo[2], false, 'DBCALL', POLLER_VERBOSITY_DEBUG);
				cacti_debug_backtrace('SQL');

				return false;
			} else {
				cacti_log("ERROR: A DB Exec Failed!, Error:$en, SQL:'" . $sql . "'", FALSE, 'DBCALL');
				cacti_log('ERROR: A DB Exec Failed!, Error: ' . $errorinfo[2], false);
				cacti_debug_backtrace('SQL');

				return false;
			}
		} else {
			$query->closeCursor();
			unset($query);

			return false;
		}
	}

	unset($query);

	return false;
}


/* db_fetch_cell - run a 'select' sql query and return the first column of the
     first row found
   @param $sql - the sql query to execute
   @param $col_name - use this column name instead of the first one
   @param $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function db_fetch_cell($sql, $col_name = '', $log = TRUE, $db_conn = FALSE) {
	return db_fetch_cell_prepared($sql, array(), $col_name, $log, $db_conn);
}

/* db_fetch_cell_prepared - run a 'select' sql query and return the first column of the
     first row found
   @param $sql - the sql query to execute
   @param $col_name - use this column name instead of the first one
   @param $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function db_fetch_cell_prepared($sql, $parms = array(), $col_name = '', $log = true, $db_conn = false) {
	global $database_sessions, $database_default, $config, $database_hostname, $database_port, $database_total_queries;
	$database_total_queries++;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$sql = db_strip_control_chars($sql);

	cacti_log('DEVEL: SQL Cell: "' . $sql . '"', false, 'DBCALL', POLLER_VERBOSITY_DEVDBG);

	$db_conn->affected_rows = 0;
	$query = $db_conn->prepare($sql);
	$query->execute($parms);
	$errorinfo = $query->errorInfo();
	$en = $errorinfo[1];
	if ($en == '') {
		$db_conn->affected_rows = $query->rowCount();
		$q = $query->fetchAll(PDO::FETCH_BOTH);
		$query->closeCursor();
		unset($query);
		if (isset($q[0]) && is_array($q[0])) {
			if ($col_name != '') {
				return $q[0][$col_name];
			} else {
				return reset($q[0]);
			}
		}
		return false;
	}else if ($log) {
		cacti_log("ERROR: SQL Cell Failed!, Error:$en, SQL:'" . $sql . "'", false, 'DBCALL', POLLER_VERBOSITY_DEVDBG);
		cacti_log('ERROR: SQL Cell Failed!, Error: ' . $errorinfo[2], false, 'DBCALL', POLLER_VERBOSITY_DEVDBG);
		cacti_debug_backtrace('SQL');
	}

	if (isset($query)) {
		unset($query);
	}

	return false;
}

/* db_fetch_row - run a 'select' sql query and return the first row found
   @param $sql - the sql query to execute
   @param $log - whether to log error messages, defaults to true
   @returns - the first row of the result as a hash */
function db_fetch_row($sql, $log = true, $db_conn = false) {
	return db_fetch_row_prepared($sql, array(), $log, $db_conn);
}

/* db_fetch_row_prepared - run a 'select' sql query and return the first row found
   @param $sql - the sql query to execute
   @param $log - whether to log error messages, defaults to true
   @returns - the first row of the result as a hash */
function db_fetch_row_prepared($sql, $parms = array(), $log = true, $db_conn = false) {
	global $database_sessions, $database_default, $config, $database_hostname, $database_port, $database_total_queries;
	$database_total_queries++;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$sql = db_strip_control_chars($sql);

	if ($log) {
		cacti_log('DEVEL: SQL Row: "' . $sql . '"', false, 'DBCALL', POLLER_VERBOSITY_DEVDBG);
	}

	$db_conn->affected_rows = 0;
	$query = $db_conn->prepare($sql);
	$query->execute($parms);
	$errorinfo = $query->errorInfo();
	$en = $errorinfo[1];
	if ($en == '') {
		$db_conn->affected_rows = $query->rowCount();

		if ($query->rowCount()) {
			$q = $query->fetchAll(PDO::FETCH_ASSOC);
			$query->closeCursor();
			unset($query);
			if (isset($q[0])) {
				return $q[0];
			} else {
				return array();
			}
		} else {
			$query->closeCursor();
			return array();
		}
	} elseif ($log) {
		cacti_log("ERROR: SQL Row Failed!, Error:$en, SQL:'" . $sql . "'", false, 'DBCALL', POLLER_VERBOSITY_DEVDBG);
		cacti_log('ERROR: SQL Row Failed!, Error: ' . $errorinfo[2], false, 'DBCALL', POLLER_VERBOSITY_DEVDBG);
		cacti_debug_backtrace('SQL');
	}

	if (isset($query)) {
		unset($query);
	}

	return array();
}

/* db_fetch_assoc - run a 'select' sql query and return all rows found
   @param $sql - the sql query to execute
   @param $log - whether to log error messages, defaults to true
   @returns - the entire result set as a multi-dimensional hash */
function db_fetch_assoc($sql, $log = true, $db_conn = false) {
	return db_fetch_assoc_prepared($sql, array(), $log, $db_conn);
}

/* db_fetch_assoc_prepared - run a 'select' sql query and return all rows found
   @param $sql - the sql query to execute
   @param $log - whether to log error messages, defaults to true
   @returns - the entire result set as a multi-dimensional hash */
function db_fetch_assoc_prepared($sql, $parms = array(), $log = true, $db_conn = false) {
	global $database_sessions, $database_default, $config, $database_hostname, $database_port, $database_total_queries;
	$database_total_queries++;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$sql = db_strip_control_chars($sql);

	cacti_log('DEVEL: SQL Assoc: "' . $sql . '"', false, 'DBCALL', POLLER_VERBOSITY_DEVDBG);

	$db_conn->affected_rows = 0;
	$query = $db_conn->prepare($sql);
	$query->execute($parms);
	$errorinfo = $query->errorInfo();
	$en = $errorinfo[1];
	if ($en == '') {
		$db_conn->affected_rows = $query->rowCount();
		$a = $query->fetchAll(PDO::FETCH_ASSOC);
		$query->closeCursor();
		unset($query);
		if (!is_array($a)) {
			$a = array();
		}
		return $a;
	} elseif ($log) {
		cacti_log("ERROR: SQL Assoc Failed!, Error:$en, SQL:'" . $sql . "'", false, 'DBCALL');
		cacti_log('ERROR: SQL Assoc Failed!, Error: ' . $errorinfo[2], false, 'DBCALL');
		cacti_debug_backtrace('SQL');
	}

	if (isset($query)) unset($query);

	return array();
}

/* db_fetch_insert_id - get the last insert_id or auto incriment
   @returns - the id of the last auto increment row that was created */
function db_fetch_insert_id($db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
	}

	if (is_object($db_conn)) {
		return $db_conn->lastInsertId();
	}

	return false;
}

/* db_affected_rows - return the number of rows affected by the last transaction
 * @returns - the number of rows affected by the last transaction */
function db_affected_rows($db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	return $db_conn->affected_rows;
}

/* db_add_column - add a column to table
   @param $table - the name of the table
   @param $column - array of column data ex: array('name' => 'test' . rand(1, 200), 'type' => 'varchar (255)', 'NULL' => false)
   @param $log - whether to log error messages, defaults to true
   @returns - '1' for success, '0' for error */
function db_add_column($table, $column, $log = true, $db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$result = db_fetch_assoc('SHOW columns FROM `' . $table . '`', $log, $db_conn);
	$columns = array();
	foreach($result as $arr) {
		$columns[] = $arr['Field'];
	}

	if (isset($column['name']) && !in_array($column['name'], $columns)) {
		$sql = 'ALTER TABLE `' . $table . '` ADD `' . $column['name'] . '`';
		if (isset($column['type']))
			$sql .= ' ' . $column['type'];

		if (isset($column['unsigned']))
			$sql .= ' unsigned';

		if (isset($column['NULL']) && $column['NULL'] === false)
			$sql .= ' NOT NULL';

		if (isset($column['NULL']) && $column['NULL'] === true && !isset($column['default']))
			$sql .= ' default NULL';

		if (isset($column['default'])) {
			if (strtolower($column['type']) == 'timestamp' && $column['default'] === 'CURRENT_TIMESTAMP') {
				$sql .= ' default CURRENT_TIMESTAMP';
			} else {
				$sql .= ' default ' . (is_numeric($column['default']) ? $column['default'] : "'" . $column['default'] . "'");
			}
		}

		if (isset($column['on_update']))
			$sql .= ' ON UPDATE ' . $column['on_update'];

		if (isset($column['auto_increment']))
			$sql .= ' auto_increment';

		if (isset($column['comment']))
			$sql .= " COMMENT '" . $column['comment'] . "'";

		if (isset($column['after']))
			$sql .= ' AFTER ' . $column['after'];

		return db_execute($sql, $log, $db_conn);
	}

	return true;
}

/* db_remove_column - remove a column to table
   @param $table - the name of the table
   @param $column - column name
   @param $log - whether to log error messages, defaults to true
   @returns - '1' for success, '0' for error */
function db_remove_column($table, $column, $log = true, $db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$result = db_fetch_assoc('SHOW columns FROM `' . $table . '`', $log, $db_conn);
	$columns = array();
	foreach($result as $arr) {
		$columns[] = $arr['Field'];
	}

	if (isset($column) && in_array($column, $columns)) {
		$sql = 'ALTER TABLE `' . $table . '` DROP `' . $column . '`';
		return db_execute($sql, $log, $db_conn);
	}

	return true;
}

/* db_add_index - adds a new index to a table
   @param $table - the name of the table
   @param $type - the type of the index
   @param $key - the name of the index
   @param $columns - an array that defines the columns to include in the index
   @returns - (bool) the result of the operation true or false */
function db_add_index($table, $type, $key, $columns) {
	if (!is_array($columns)) {
		$columns = array($columns);
	}

	$sql = 'ALTER TABLE `' . $table . '` ADD ' . $type . ' `' . $key . '`(`' . implode('`,`', $columns) . '`)';

	if (db_index_exists($table, $key, false)) {
		$type = str_ireplace('UNIQUE ', '', $type);
		db_execute("ALTER TABLE $table DROP $type $key");
	}

	return db_execute($sql);
}

/* db_index_exists - checks whether an index exists
   @param $table - the name of the table
   @param $index - the name of the index
   @param $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function db_index_exists($table, $index, $log = true, $db_conn = false) {
	$_keys = array_rekey(db_fetch_assoc("SHOW KEYS FROM `$table`", $log, $db_conn), "Key_name", "Key_name");
	return in_array($index, $_keys);
}

/* db_table_exists - checks whether a table exists
   @param $table - the name of the table
   @param $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function db_table_exists($table, $log = true, $db_conn = false) {
	return (db_fetch_cell("SHOW TABLES LIKE '$table'", '', $log, $db_conn) ? true : false);
}

/* db_cacti_initialized - checks whether cacti has been initialized properly and if not exits with a message
   @param $is_web - is the session a web session.
   @returns - (null)  */
function db_cacti_initialized($is_web = true) {
	global $database_sessions, $database_default, $config, $database_hostname, $database_port, $config;

	$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

	if (!is_object($db_conn)) {
		return FALSE;
	}

	$query = $db_conn->prepare('SELECT cacti FROM version');
	$query->execute();
	$errorinfo = $query->errorInfo();
	$query->closeCursor();

	if ($errorinfo[1] != 0) {
		print ($is_web ? '<head><link href="' . $config['url_path'] . 'include/themes/modern/main.css" type="text/css" rel="stylesheet"></head>':'');
		print ($is_web ? '<table style="height:40px;"><tr><td></td></tr></table>':'');
		print ($is_web ? '<table style="margin-left:auto;margin-right:auto;width:80%;border:1px solid rgba(98,125,77,1)" class="cactiTable"><tr class="cactiTableTitle"><td style="color:snow;font-weight:bold;">Fatal Error - Cacti Database Not Initialized</td></tr>':'');
		print ($is_web ? '<tr class="installArea"><td>':'');
		print ($is_web ? '<p>':'') . 'The Cacti Database has not been initialized.  Please initilize it before continuing.' . ($is_web ? '</p>':"\n");
		print ($is_web ? '<p>':'') . 'To initilize the Cacti database, issue the following commands either as root or using a valid account.' . ($is_web ? '</p>':"\n");
		print ($is_web ? '<p style="font-weight:bold;padding-left:25px;">':'') . '  mysqladmin -uroot -p create cacti' . ($is_web ? '</p>':"\n");
		print ($is_web ? '<p style="font-weight:bold;padding-left:25px;">':'') . '  mysql -uroot -p -e "grant all on cacti.* to \'someuser\'@\'localhost\' identified by \'somepassword\'"' . ($is_web ? '</p>':"\n");
		print ($is_web ? '<p style="font-weight:bold;padding-left:25px;">':'') . '  mysql -uroot -p -e "grant select on mysql.time_zone_name to \'someuser\'@\'localhost\' identified by \'somepassword\'"' . ($is_web ? '</p>':"\n");
		print ($is_web ? '<p style="font-weight:bold;padding-left:25px;">':'') . '  mysql -uroot -p cacti < /pathcacti/cacti.sql' . ($is_web ? '</p>':"\n");
		print ($is_web ? '<p>':'') . 'Where <b>/pathcacti/</b> is the path to your Cacti install location.' . ($is_web ? '</p>':"\n");
		print ($is_web ? '<p>':'') . 'Change <b>someuser</b> and <b>somepassword</b> to match your site preferences.  The defaults are <b>cactiuser</b> for both user and password.' . ($is_web ? '</p>':"\n");
		print ($is_web ? '<p>':'') . '<b>NOTE:</b> When installing a remote poller, the <b>config.php</b> file must be writable by the Web Server account, and must include valid connection information to the main Cacti server.  The file should be changed to read only after the install is completed.' . ($is_web ? '</p>':"\n");
		print ($is_web ? '</td></tr></table>':'');
		exit;
	}
}

/* db_column_exists - checks whether a column exists
   @param $table - the name of the table
   @param $column - the name of the column
   @param $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function db_column_exists($table, $column, $log = true, $db_conn = false) {
	return (db_fetch_cell("SHOW columns FROM `$table` LIKE '$column'", '', $log, $db_conn) ? true : false);
}

/* db_get_table_column_types - returns all the types for each column of a table
   @param $table - the name of the table
   @returns - (array) an array of column types indexed by the column names */
function db_get_table_column_types($table, $db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$columns = db_fetch_assoc("SHOW COLUMNS FROM $table", false, $db_conn);
	$cols    = array();
	if (sizeof($columns)) {
		foreach($columns as $col) {
			$cols[$col['Field']] = array('type' => $col['Type'], 'null' => $col['Null'], 'default' => $col['Default'], 'extra' => $col['Extra']);;
		}
	}

	return $cols;
}

function db_update_table($table, $data, $removecolumns = false, $log = true, $db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	if (!db_table_exists($table, $log, $db_conn)) {
		return db_table_create ($table, $data, $log, $db_conn);
	}

	$allcolumns = array();
	foreach ($data['columns'] as $column) {
		$allcolumns[] = $column['name'];
		if (!db_column_exists($table, $column['name'], $log, $db_conn)) {
			db_add_column ($table, $column, $log, $db_conn);
		} else {
			// Check that column is correct and fix it
			// FIXME: Need to still check default value
			$arr = db_fetch_row("SHOW columns FROM `$table` LIKE '" . $column['name'] . "'", $log, $db_conn);
			if ($column['type'] != $arr['Type'] || (isset($column['NULL']) && ($column['NULL'] ? 'YES' : 'NO') != $arr['Null'])
							    || (isset($column['auto_increment']) && ($column['auto_increment'] ? 'auto_increment' : '') != $arr['Extra'])) {
				$sql = 'ALTER TABLE `' . $table . '` CHANGE `' . $column['name'] . '` `' . $column['name'] . '`';
				if (isset($column['type']))
					$sql .= ' ' . $column['type'];
				if (isset($column['unsigned']))
					$sql .= ' unsigned';
				if (isset($column['NULL']) && $column['NULL'] == false)
					$sql .= ' NOT NULL';
				if (isset($column['NULL']) && $column['NULL'] == true && !isset($column['default']))
					$sql .= ' default NULL';
				if (isset($column['default']))
					$sql .= ' default ' . (is_numeric($column['default']) ? $column['default'] : "'" . $column['default'] . "'");
				if (isset($column['on_update']))
					$sql .= ' ON UPDATE ' . $column['on_update'];
				if (isset($column['auto_increment']))
					$sql .= ' auto_increment';
				if (isset($column['comment']))
					$sql .= " COMMENT '" . $column['comment'] . "'";
				db_execute($sql, $log, $db_conn);
			}
		}
	}

	if ($removecolumns) {
		$result = db_fetch_assoc('SHOW columns FROM `' . $table . '`', $log, $db_conn);
		foreach($result as $arr) {
			if (!in_array($arr['Field'], $allcolumns)) {
				db_remove_column ($table, $arr['Field'], $log, $db_conn);
			}
		}
	}

	$info = db_fetch_row("SELECT ENGINE, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_NAME = '$table'", $log, $db_conn);
	if (isset($info['TABLE_COMMENT']) && str_replace("'", '', $info['TABLE_COMMENT']) != str_replace("'", '', $data['comment'])) {
		db_execute("ALTER TABLE `$table` COMMENT '" . str_replace("'", '', $data['comment']) . "'", $log, $db_conn);
	}

	if (isset($info['ENGINE']) && strtolower($info['ENGINE']) != strtolower($data['type'])) {
		db_execute("ALTER TABLE `$table` ENGINE = " . $data['type'], $log, $db_conn);
	}

	// Correct any indexes
	$indexes = db_fetch_assoc("SHOW INDEX FROM `$table`", $log, $db_conn);
	$allindexes = array();

	foreach ($indexes as $index) {
		$allindexes[$index['Key_name']][$index['Seq_in_index']-1] = $index['Column_name'];
	}

	foreach ($allindexes as $n => $index) {
		if ($n != 'PRIMARY' && isset($data['keys'])) {
			$removeindex = true;
			foreach ($data['keys'] as $k) {
				if ($k['name'] == $n) {
					$removeindex = false;
					$add = array_diff($k['columns'], $index);
					$del = array_diff($index, $k['columns']);
					if (!empty($add) || !empty($del)) {
						db_execute("ALTER TABLE `$table` DROP INDEX `$n`", $log, $db_conn);
						db_execute("ALTER TABLE `$table` ADD INDEX `$n` (`" . (is_array($k['columns']) ? implode('`,`', $k['columns']) : $k['columns']) . '`)', $log, $db_conn);
					}
					break;
				}
			}
			if ($removeindex) {
				db_execute("ALTER TABLE `$table` DROP INDEX `$n`", $log, $db_conn);
			}
		}
	}

	// Add any indexes
	if (isset($data['keys'])) {
		foreach ($data['keys'] as $k) {
			if (!isset($allindexes[$k['name']])) {
				db_execute("ALTER TABLE `$table` ADD INDEX `" . $k['name'] . '` (`' . (is_array($k['columns']) ? implode('`,`', $k['columns']) : $k['columns']) . '`)', $log, $db_conn);
			}
		}
	}

	// FIXME: It won't allow us to drop a primary key that is set to auto_increment

	// Check Primary Key
	if (!isset($data['primary']) && isset($allindexes['PRIMARY'])) {
		db_execute("ALTER TABLE `$table` DROP PRIMARY KEY", $log, $db_conn);
		unset($allindexes['PRIMARY']);
	}

	if (isset($data['primary'])) {
		if (!isset($allindexes['PRIMARY'])) {
			// No current primary key, so add it
			if (is_array($data['primary'])) {
				$data['primary'] = implode(',', $data['primary']);
			}
			db_execute("ALTER TABLE `$table` ADD PRIMARY KEY(" . $data['primary'] . ")", $log, $db_conn);
		} else {
			$add = array_diff($data['primary'], $allindexes['PRIMARY']);
			$del = array_diff($allindexes['PRIMARY'], $data['primary']);
			if (!empty($add) || !empty($del)) {
				db_execute("ALTER TABLE `$table` DROP PRIMARY KEY", $log, $db_conn);
				db_execute("ALTER TABLE `$table` ADD PRIMARY KEY(`" . (is_array($data['primary']) ? implode('`,`', $data['primary']) : $data['primary']) . "`)", $log, $db_conn);
			}
		}
	}

	return true;
}


/* db_table_create - checks whether a table exists
   @param $table - the name of the table
   @param $data - data array
   @param $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function db_table_create($table, $data, $log = true, $db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	if (!db_table_exists($table, $log, $db_conn)) {
		$c = 0;
		$sql = 'CREATE TABLE `' . $table . "` (\n";
		foreach ($data['columns'] as $column) {
			if (isset($column['name'])) {
				if ($c > 0)
					$sql .= ",\n";
				$sql .= '`' . $column['name'] . '`';
				if (isset($column['type']))
					$sql .= ' ' . $column['type'];
				if (isset($column['unsigned']))
					$sql .= ' unsigned';
				if (isset($column['NULL']) && $column['NULL'] == false)
					$sql .= ' NOT NULL';
				if (isset($column['NULL']) && $column['NULL'] == true && !isset($column['default']))
					$sql .= ' default NULL';
				if (isset($column['default']))
					$sql .= ' default ' . (is_numeric($column['default']) ? $column['default'] : "'" . $column['default'] . "'");
				if (isset($column['on_update']))
					$sql .= ' ON UPDATE ' . $column['on_update'];
				if (isset($column['comment']))
					$sql .= " COMMENT '" . $column['comment'] . "'";
				if (isset($column['auto_increment']))
					$sql .= ' auto_increment';
				$c++;
			}
		}

		if (isset($data['primary'])) {
			if (is_array($data['primary'])) {
				$sql .= ",\n PRIMARY KEY (`" . implode('`,`'. $data['primary']) . '`)';
			} else {
				$sql .= ",\n PRIMARY KEY (`" . $data['primary'] . '`)';
			}
		}

		if (isset($data['keys']) && sizeof($data['keys'])) {
			foreach ($data['keys'] as $key) {
				if (isset($key['name'])) {
					if (is_array($key['columns'])) {
						$sql .= ",\n KEY `" . $key['name'] . '` (`' . implode('`,`', $key['columns']) . '`)';
					} else {
						$sql .= ",\n KEY `" . $key['name'] . '` (`' . $key['columns'] . '`)';
					}
				}
			}
		}
		$sql .= ') ENGINE = ' . $data['type'];

		if (isset($data['comment'])) {
			$sql .= " COMMENT = '" . $data['comment'] . "'";
		}

		if (db_execute($sql, $log, $db_conn)) {
			return true;
		}

		return false;
	}
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
	if (end($array) === null) {
		array_pop($array);
	}

	if (sizeof($array)) {
		$sql_or = "($sql_column IN('" . implode("','", $array) . "'))";

		return $sql_or;
	}
}

/* db_replace - replaces the data contained in a particular row
   @param $table_name - the name of the table to make the replacement in
   @param $array_items - an array containing each column -> value mapping in the row
   @param $keyCols - a string or array of primary keys
   @param $autoQuote - whether to use intelligent quoting or not
   @returns - the auto incriment id column (if applicable) */
function db_replace($table_name, $array_items, $keyCols, $db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
	}

	cacti_log("DEVEL: SQL Replace on table '$table_name': '" . serialize($array_items) . "'", false, 'DBCALL', POLLER_VERBOSITY_DEVDBG);

	_db_replace($db_conn, $table_name, $array_items, $keyCols);

	return db_fetch_insert_id($db_conn);
}


// FIXME:  Need to Rename and cleanup a bit

function _db_replace($db_conn, $table, $fieldArray, $keyCols, $has_autoinc) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	if (!is_array($keyCols)) {
		$keyCols = array($keyCols);
	}

	$sql  = "INSERT INTO $table (";
	$sql2 = '';
	$sql3 = '';

	$first  = true;
	$first3 = true;
	foreach($fieldArray as $k => $v) {
		if (!$first) {
			$sql  .= ', ';
			$sql2 .= ', ';
		}
		$sql   .= "`$k`";
		$sql2  .= $v;
		$first  = false;

		if (in_array($k, $keyCols)) continue; // skip UPDATE if is key

		if (!$first3) {
			$sql3 .= ', ';
		}

		$sql3 .= "`$k`=VALUES(`$k`)";

		$first3 = false;
	}

	$sql .= ") VALUES ($sql2)" . ($sql3 != '' ? " ON DUPLICATE KEY UPDATE $sql3" : '');

	$return_code = db_execute($sql, true, $db_conn);

	if (!$return_code) {
		cacti_log("ERROR: SQL Save Failed for Table '$table'.  SQL:'" . $sql . "'", false, 'DBCALL');
	}

	return db_fetch_insert_id($db_conn);
}

/* sql_save - saves data to an sql table
   @param $array_items - an array containing each column -> value mapping in the row
   @param $table_name - the name of the table to make the replacement in
   @param $key_cols - the primary key(s)
   @returns - the auto incriment id column (if applicable) */
function sql_save($array_items, $table_name, $key_cols = 'id', $autoinc = TRUE, $db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
	}

	$cols = db_get_table_column_types($table_name, $db_conn);

	cacti_log("DEVEL: SQL Save on table '$table_name': '" . serialize($array_items) . "'", false, 'DBCALL', POLLER_VERBOSITY_DEVDBG);

	foreach ($array_items as $key => $value) {
		if (strstr($cols[$key]['type'], 'int') !== false ||
			strstr($cols[$key]['type'], 'float') !== false ||
			strstr($cols[$key]['type'], 'double') !== false ||
			strstr($cols[$key]['type'], 'decimal') !== false) {
			if ($value == '') {
				if ($cols[$key]['null'] == 'YES') {
					// TODO: We should make 'NULL', but there are issues that need to be addressed first
					$array_items[$key] = 0;
				} elseif (strpos($cols[$key]['extra'], 'auto_increment') !== false) {
					$array_items[$key] = 0;
				} elseif ($cols[$key]['default'] == '') {
					// TODO: We should make 'NULL', but there are issues that need to be addressed first
					$array_items[$key] = 0;
				} else {
					$array_items[$key] = $cols[$key]['default'];
				}
			} elseif (empty($value)) {
				$array_items[$key] = 0;
			} else {
				$array_items[$key] = $value;
			}
		} else {
			$array_items[$key] = db_qstr($value);
		}
	}

	$replace_result = _db_replace($db_conn, $table_name, $array_items, $key_cols, $autoinc);

	/* get the last AUTO_ID and return it */
	if (!$replace_result || db_fetch_insert_id($db_conn) == '0') {
		if (!is_array($key_cols)) {
			if (isset($array_items[$key_cols])) {
				return str_replace('"', '', $array_items[$key_cols]);
			}
		}
		return false;
	} else {
		return $replace_result;
	}
}

function db_qstr($s, $db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
	}

	if (is_null($s)) {
		return 'NULL';
	}

	if (is_object($db_conn)) {
		return $db_conn->quote($s);
	}

	$s = str_replace(array('\\', "\0", "'"), array('\\\\', "\\\0", "\\'"), $s);

	return  "'" . $s . "'";
}

function db_strip_control_chars($sql) {
	return trim(str_replace(array("\t", "\r", "\n"), array(' ', '', ''), $sql), ';');
}
