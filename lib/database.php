<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
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
   @param $port - the port to communicate with MySQL/MariaDB on
   @param $retries - the number a time the server should attempt to connect before failing
   @param $db_ssl - boolean true or false
   @param $db_ssl_key - the client ssl key
   @param $db_ssl_cert - the client ssl cert
   @param $db_ssl_ca - the ssl ca
   @returns - (bool) '1' for success, '0' for error */
function db_connect_real($device, $user, $pass, $db_name, $db_type = 'mysql', $port = '3306', $retries = 20,
	$db_ssl = false, $db_ssl_key = '', $db_ssl_cert = '', $db_ssl_ca = '') {
	global $database_sessions, $database_total_queries, $config;
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

		if (!defined('PDO::MYSQL_ATTR_FOUND_ROWS')) {
			if (!empty($config['DEBUG_READ_CONFIG_OPTION'])) {
				$prefix = get_debug_prefix();
				file_put_contents(sys_get_temp_dir() . '/cacti-option.log', "$prefix\n$prefix ************* DATABASE MODULE MISSING ****************\n$prefix session name: $odevice:$port:$db_name\n$prefix\n", FILE_APPEND);
			}

			return false;
		}

		$flags[PDO::ATTR_PERSISTENT] = true;
		$flags[PDO::MYSQL_ATTR_FOUND_ROWS] = true;
		if ($db_ssl) {
			if ($db_ssl_key != '' && $db_ssl_cert != '' && $db_ssl_ca != '') {
				if (file_exists($db_ssl_key) && file_exists($db_ssl_cert) && file_exists($db_ssl_ca)) {
					$flags[PDO::MYSQL_ATTR_SSL_KEY]  = $db_ssl_key;
					$flags[PDO::MYSQL_ATTR_SSL_CERT] = $db_ssl_cert;
					$flags[PDO::MYSQL_ATTR_SSL_CA]   = $db_ssl_ca;
				} elseif (file_exists($db_ssl_key) && file_exists($db_ssl_cert)) {
					$flags[PDO::MYSQL_ATTR_SSL_KEY]  = $db_ssl_key;
					$flags[PDO::MYSQL_ATTR_SSL_CERT] = $db_ssl_cert;
				}
			}
		}
	}

	while ($i <= $retries) {
		try {
			if (strpos($device, '/') !== false && filetype($device) == 'socket') {
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

			$ver = db_get_global_variable('version', $cnn_id);

			if (strpos($ver, 'MariaDB') !== false) {
				$srv = 'MariaDB';
				$ver  = str_replace('-MariaDB', '', $ver);
			} else {
				$srv = 'MySQL';
			}

			if (version_compare('8.0.0', $ver, '<=')) {
				$bad_modes[] = 'NO_AUTO_CREATE_USER';
			}

			// Get rid of bad modes
			$modes = explode(',', db_fetch_cell('SELECT @@sql_mode', '', false));
			$new_modes = array();

			foreach($modes as $mode) {
				if (array_search($mode, $bad_modes) === false) {
					$new_modes[] = $mode;
				}
			}

			// Add Required modes
			$required_modes[] = 'ALLOW_INVALID_DATES';
			$required_modes[] = 'NO_ENGINE_SUBSTITUTION';

			foreach($required_modes as $mode) {
				if (array_search($mode, $new_modes) === false) {
					$new_modes[] = $mode;
				}
			}

			$sql_mode = implode(',', $new_modes);

			db_execute_prepared('SET SESSION sql_mode = ?', array($sql_mode), false);

			if (db_column_exists('poller', 'timezone')) {
				$timezone = db_fetch_cell_prepared('SELECT timezone
					FROM poller
					WHERE id = ?',
					array($config['poller_id']), false);
			} else {
				$timezone = '';
			}

			if ($timezone != '') {
				db_execute_prepared('SET SESSION time_zone = ?', array($timezone), false);
			}

			if (!empty($config['DEBUG_READ_CONFIG_OPTION'])) {
				$prefix = get_debug_prefix();
				file_put_contents(sys_get_temp_dir() . '/cacti-option.log', "$prefix\n$prefix ************* DATABASE OPEN ****************\n$prefix session name: $odevice:$port:$db_name\n$prefix\n", FILE_APPEND);
			}

			if (!empty($config['DEBUG_READ_CONFIG_OPTION_DB_OPEN'])) {
				$config['DEBUG_READ_CONFIG_OPTION'] = false;
			}
			return $cnn_id;
		} catch (PDOException $e) {
			if (!isset($config['DATABASE_ERROR'])) {
				$config['DATABASE_ERROR'] = array();
			}

			$config['DATABASE_ERROR'][] = array(
				'Code' => $e->getCode(),
				'Error' => $e->getMessage(),
			);
			// Must catch this exception or else PDO will display an error with our username/password
			//print $e->getMessage();
			//exit;
		}

		$i++;
		usleep(40000);
	}

	return false;
}

function db_warning_handler($errno, $errstr, $errfile, $errline, $errcontext) {
	throw new Exception($errstr, $errno);
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
function db_execute_prepared($sql, $params = array(), $log = true, $db_conn = false, $execute_name = 'Exec', $default_value = true, $return_func = 'no_return_function', $return_params = array()) {
	global $database_sessions, $database_default, $config, $database_hostname, $database_port, $database_total_queries, $database_last_error, $database_log;
	$database_total_queries++;

	if (!isset($database_log)) {
		$database_log = false;
	}

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

		if (!is_object($db_conn)) {
			$database_last_error = 'DB ' . $execute_name . ' -- No connection found';
			return false;
		}
	}

	$sql = db_strip_control_chars($sql);

	if (!empty($config['DEBUG_SQL_CMD'])) {
		db_echo_sql('db_' . $execute_name . ': "' . $sql . "\"\n");
	}

	$errors = 0;
	$db_conn->affected_rows = 0;

	while (true) {
		$query = $db_conn->prepare($sql);

		$code = 0;
		$en = '';

		if (!empty($config['DEBUG_SQL_CMD'])) {
			db_echo_sql('db_' . $execute_name . ' Memory [Before]: ' . memory_get_usage() . ' / ' . memory_get_peak_usage() . "\n");
		}

		set_error_handler('db_warning_handler',E_WARNING | E_NOTICE);
		try {
			if (empty($params) || cacti_count($params) == 0) {
				$query->execute();
			} else {
				$query->execute($params);
			}
		} catch (Exception $ex) {
			$code = $ex->getCode();
			$en = $code;
			$errorinfo = array(1=>$code, 2=>$ex->getMessage());
		}
		restore_error_handler();

		if (!empty($config['DEBUG_SQL_CMD'])) {
			db_echo_sql('db_' . $execute_name . ' Memory [ After]: ' . memory_get_usage() . ' / ' . memory_get_peak_usage() . "\n");
		}

		if ($code == 0) {
			$code = $query->errorCode();
			if ($code != '00000' && $code != '01000') {
				$errorinfo = $query->errorInfo();
				$en = $errorinfo[1];
			}  else {
				$code = $db_conn->errorCode();
				if ($code != '00000' && $code != '01000') {
					$errorinfo = $db_conn->errorInfo();
					$en = $errorinfo[1];
				}
			}
		}

		if ($en == '') {
			// With PDO, we have to free this up
			$db_conn->affected_rows = $query->rowCount();

			$return_value = $default_value;
			if (function_exists($return_func)) {
				$return_array = array($query);
				if (!empty($return_params)) {
					if (!is_array($return_params)) {
						$return_params = array($return_params);
					}
					$return_array = array_merge($return_array, $return_params);
				}

				if (!empty($config['DEBUG_SQL_FLOW'])) {
					db_echo_sql('db_' . $execute_name . '_return_func: \'' . $return_func .'\' (' . function_exists($return_func) . ")\n");
					db_echo_sql('db_' . $execute_name . '_return_func: params ' . clean_up_lines(var_export($return_array, true)) . "\n");
				}

				$return_value = call_user_func_array($return_func, $return_array);
			}
			$query->closeCursor();
			unset($query);

			if (!empty($config['DEBUG_SQL_FLOW'])) {
				db_echo_sql('db_' . $execute_name . ': returns ' . clean_up_lines(var_export($return_value, true)) . "\n", true);
			}
			return $return_value;
		} else {
			$database_last_error = 'DB ' . $execute_name . ' Failed!, Error ' . $en . ': ' . (isset($errorinfo[2]) ? $errorinfo[2] : '<no error>');
			if (isset($query)) {
				$query->closeCursor();
			}
			unset($query);

			if ($log) {
				if ($en == 1213 || $en == 1205) {
					$errors++;
					if ($errors > 30) {
						cacti_log("ERROR: Too many Lock/Deadlock errors occurred! SQL:'" . clean_up_lines($sql) . "'", true, 'DBCALL', POLLER_VERBOSITY_DEBUG);
						$database_last_error = "Too many Lock/Deadlock errors occurred!";
					} else {
						usleep(200000);

						continue;
					}
				} else if ($en == 1153) {
					if (strlen($sql) > 1024) {
						$sql = substr($sql, 0, 1024) . '...';
					}

					cacti_log('ERROR: A DB ' . $execute_name . ' Too Large!, Error: ' . $en . ', SQL: \'' . clean_up_lines($sql) . '\'', false, 'DBCALL', POLLER_VERBOSITY_DEBUG);
					cacti_log('ERROR: A DB ' . $execute_name . ' Too Large!, Error: ' . $errorinfo[2], false, 'DBCALL', POLLER_VERBOSITY_DEBUG);
					cacti_debug_backtrace('SQL', false, true, 0, 1);

					$database_last_error = 'DB ' . $execute_name . ' Too Large!, Error ' . $en . ': ' . $errorinfo[2];
				} else {
					cacti_log('ERROR: A DB ' . $execute_name . ' Failed!, Error: ' . $en . ', SQL: \'' . clean_up_lines($sql) . '\'', false, 'DBCALL', POLLER_VERBOSITY_DEBUG);
					cacti_log('ERROR: A DB ' . $execute_name . ' Failed!, Error: ' . $errorinfo[2], false);
					cacti_debug_backtrace('SQL', false, true, 0, 1);

					$database_last_error = 'DB ' . $execute_name . ' Failed!, Error ' . $en . ': ' . (isset($errorinfo[2]) ? $errorinfo[2] : '<no error>');
				}
			}

			if (!empty($config['DEBUG_SQL_FLOW'])) {
				db_echo_sql($database_last_error);
			}
			return false;
		}
	}

	unset($query);

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql($database_last_error);
	}

	return false;
}


/* db_fetch_cell - run a 'select' sql query and return the first column of the
     first row found
   @param $sql - the sql query to execute
   @param $col_name - use this column name instead of the first one
   @param $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function db_fetch_cell($sql, $col_name = '', $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_cell($sql, $col_name = \'' . $col_name . '\', $log = true, $db_conn = false)' . "\n");
	}

	return db_fetch_cell_prepared($sql, array(), $col_name, $log, $db_conn);
}

/* db_fetch_cell_prepared - run a 'select' sql query and return the first column of the
     first row found
   @param $sql - the sql query to execute
   @param $col_name - use this column name instead of the first one
   @param $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function db_fetch_cell_prepared($sql, $params = array(), $col_name = '', $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_cell_prepared($sql, $params = ' . clean_up_lines(var_export($params, true)) . ', $col_name = \'' . $col_name . '\', $log = true, $db_conn = false)' . "\n");
	}

	return db_execute_prepared($sql, $params, $log, $db_conn, 'Cell', false, 'db_fetch_cell_return', $col_name);
}

function db_fetch_cell_return($query, $col_name = '') {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_cell_return($query, $col_name = \'' . $col_name . '\')' . "\n");
	}

	$r = $query->fetchAll(PDO::FETCH_BOTH);
	if (isset($r[0]) && is_array($r[0])) {
		if ($col_name != '') {
			return $r[0][$col_name];
		} else {
			return reset($r[0]);
		}
	}
	return false;
}

/* db_fetch_row - run a 'select' sql query and return the first row found
   @param $sql - the sql query to execute
   @param $log - whether to log error messages, defaults to true
   @returns - the first row of the result as a hash */
function db_fetch_row($sql, $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_row(\'' . clean_up_lines($sql) . '\', $log = ' . $log . ', $db_conn = ' . ($db_conn ? 'true' : 'false') .')' . "\n");
	}

	return db_fetch_row_prepared($sql, array(), $log, $db_conn);
}

/* db_fetch_row_prepared - run a 'select' sql query and return the first row found
   @param $sql - the sql query to execute
   @param $log - whether to log error messages, defaults to true
   @returns - the first row of the result as a hash */
function db_fetch_row_prepared($sql, $params = array(), $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_row_prepared(\'' . clean_up_lines($sql) . '\', $params = (\'' . implode('\', \'', $params) . '\'), $log = ' . $log . ', $db_conn = ' . ($db_conn ? 'true' : 'false') .')' . "\n");
	}

	return db_execute_prepared($sql, $params, $log, $db_conn, 'Row', false, 'db_fetch_row_return');
}

function db_fetch_row_return($query) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_row_return($query)' . "\n");
	}

	if ($query->rowCount()) {
		$r = $query->fetchAll(PDO::FETCH_ASSOC);
	}

	return (isset($r[0])) ? $r[0] : array();
}

/* db_fetch_assoc - run a 'select' sql query and return all rows found
   @param $sql - the sql query to execute
   @param $log - whether to log error messages, defaults to true
   @returns - the entire result set as a multi-dimensional hash */
function db_fetch_assoc($sql, $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_assoc($sql, $log = true, $db_conn = false)' . "\n");
	}

	return db_fetch_assoc_prepared($sql, array(), $log, $db_conn);
}

/* db_fetch_assoc_prepared - run a 'select' sql query and return all rows found
   @param $sql - the sql query to execute
   @param $log - whether to log error messages, defaults to true
   @returns - the entire result set as a multi-dimensional hash */
function db_fetch_assoc_prepared($sql, $params = array(), $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_assoc_prepared($sql, $params = array(), $log = true, $db_conn = false)' . "\n");
	}

	return db_execute_prepared($sql, $params, $log, $db_conn, 'Row', array(), 'db_fetch_assoc_return');
}

function db_fetch_assoc_return($query) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_assoc_return($query)' . "\n");
	}

	$r = $query->fetchAll(PDO::FETCH_ASSOC);
	return (is_array($r)) ? $r : array();
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
	if ($result === false) {
		return false;
	}

	$columns = array();
	foreach($result as $arr) {
		$columns[] = $arr['Field'];
	}

	if (isset($column['name']) && !in_array($column['name'], $columns)) {
		$sql = 'ALTER TABLE `' . $table . '` ADD `' . $column['name'] . '`';
		if (isset($column['type'])) {
			$sql .= ' ' . $column['type'];
		}

		if (isset($column['unsigned'])) {
			$sql .= ' unsigned';
		}

		if (isset($column['NULL']) && $column['NULL'] === false) {
			$sql .= ' NOT NULL';
		}

		if (isset($column['NULL']) && $column['NULL'] === true && !isset($column['default'])) {
			$sql .= ' default NULL';
		}

		if (isset($column['default'])) {
			if (strtolower($column['type']) == 'timestamp' && $column['default'] === 'CURRENT_TIMESTAMP') {
				$sql .= ' default CURRENT_TIMESTAMP';
			} else {
				$sql .= ' default ' . (is_numeric($column['default']) ? $column['default'] : "'" . $column['default'] . "'");
			}
		}

		if (isset($column['on_update'])) {
			$sql .= ' ON UPDATE ' . $column['on_update'];
		}

		if (isset($column['auto_increment'])) {
			$sql .= ' auto_increment';
		}

		if (isset($column['comment'])) {
			$sql .= " COMMENT '" . $column['comment'] . "'";
		}

		if (isset($column['after'])) {
			$sql .= ' AFTER ' . $column['after'];
		}

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
		if (!db_execute("ALTER TABLE $table DROP $type $key")) {
			return false;
		}
	}

	return db_execute($sql);
}

/* db_index_exists - checks whether an index exists
   @param $table - the name of the table
   @param $index - the name of the index
   @param $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function db_index_exists($table, $index, $log = true, $db_conn = false) {
	global $database_log, $config;

	if (!isset($database_log)) {
		$database_log = false;
	}

	$_log  = $database_log;
	$database_log = false;

	$_data = db_fetch_assoc("SHOW KEYS FROM `$table`", $log, $db_conn);
	$_keys = array_rekey($_data, "Key_name", "Key_name");

	$database_log = $_log;
	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_index_exists(\'' . $table . '\', \'' . $index .'\'): '
			. in_array($index, $_keys) . ' - '
			. clean_up_lines(var_export($_keys, true)));
	}

	return in_array($index, $_keys);
}

/* db_index_exists - checks whether an index exists
   @param $table - the name of the table
   @param $index - the name of the index
   @param $columns - the columns of the index that should match
   @param $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function db_index_matches($table, $index, $columns, $log = true, $db_conn = false) {
	global $database_log, $config;

	if (!isset($database_log)) {
		$database_log = false;
	}

	if (!is_array($columns)) {
		$columns = array($columns);
	}

	$_log  = $database_log;
	$database_log = false;

	$_data = db_fetch_assoc("SHOW KEYS FROM `$table`", $log, $db_conn);
	$_cols = array();
	if ($_data !== false) {
		foreach ($_data as $key_col) {
			$key = $key_col['Key_name'];
			if ($key == $index) {
				$_cols[] = $key_col['Column_name'];
			}
		}
	}

	$status = 0;
	foreach ($columns as $column) {
		if (!in_array($column, $_cols)) {
			$status = -1;
			break;
		}
	}

	if ($status == 0) {
		foreach ($_cols as $column) {
			if (!in_array($column, $columns)) {
				$status = 1;
			}
		}
	}

	$database_log = $_log;
	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_index_matches(\'' . $table . '\', \'' . $index .'\'): '
			. $status . "\n ::: "
			. clean_up_lines(var_export($columns, true))
			. " ::: "
			. clean_up_lines(var_export($_cols, true)));
	}

	return $status;
}

/* db_table_exists - checks whether a table exists
   @param $table - the name of the table
   @param $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function db_table_exists($table, $log = true, $db_conn = false) {
	static $results;

	if (isset($results[$table]) && !defined('IN_CACTI_INSTALL') && !defined('IN_PLUGIN_INSTALL')) {
		return $results[$table];
	}

	// Separate the database from the table and remove backticks
	preg_match("/([`]{0,1}(?<database>[\w_]+)[`]{0,1}\.){0,1}[`]{0,1}(?<table>[\w_]+)[`]{0,1}/", $table, $matches);

	if ($matches !== false && array_key_exists('table', $matches)) {
		$sql = 'SHOW TABLES LIKE \'' . $matches['table'] . '\'';

		$results[$table] = (db_fetch_cell($sql, '', $log, $db_conn) ? true : false);

		return $results[$table];
	}

	return false;
}

/* db_cacti_initialized - checks whether cacti has been initialized properly and if not exits with a message
   @param $is_web - is the session a web session.
   @returns - (null)  */
function db_cacti_initialized($is_web = true) {
	global $database_sessions, $database_default, $config, $database_hostname, $database_port, $config;

	$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

	if (!is_object($db_conn)) {
		return false;
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
	static $results = array();

	if (isset($results[$table][$column]) && !defined('IN_CACTI_INSTALL') && !defined('IN_PLUGIN_INSTALL')) {
		return $results[$table][$column];
	}

	$results[$table][$column] = (db_fetch_cell("SHOW columns FROM `$table` LIKE '$column'", '', $log, $db_conn) ? true : false);

	return $results[$table][$column];
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
	if (cacti_sizeof($columns)) {
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
		return db_table_create($table, $data, $log, $db_conn);
	}

	$allcolumns = array();
	foreach ($data['columns'] as $column) {
		$allcolumns[] = $column['name'];
		if (!db_column_exists($table, $column['name'], $log, $db_conn)) {
			if (!db_add_column($table, $column, $log, $db_conn)) {
				return false;
			}
		} else {
			// Check that column is correct and fix it
			// FIXME: Need to still check default value
			$arr = db_fetch_row("SHOW columns FROM `$table` LIKE '" . $column['name'] . "'", $log, $db_conn);
			if ($column['type'] != $arr['Type'] || (isset($column['NULL']) && ($column['NULL'] ? 'YES' : 'NO') != $arr['Null'])
			    || (isset($column['auto_increment']) && ($column['auto_increment'] ? 'auto_increment' : '') != $arr['Extra'])) {
				$sql = 'ALTER TABLE `' . $table . '` CHANGE `' . $column['name'] . '` `' . $column['name'] . '`';
				if (isset($column['type'])) {
					$sql .= ' ' . $column['type'];
				}

				if (isset($column['unsigned'])) {
					$sql .= ' unsigned';
				}

				if (isset($column['NULL']) && $column['NULL'] == false) {
					$sql .= ' NOT NULL';
				}

				if (isset($column['NULL']) && $column['NULL'] == true && !isset($column['default'])) {
					$sql .= ' default NULL';
				}

				if (isset($column['default'])) {
					if (strtolower($column['type']) == 'timestamp' && $column['default'] === 'CURRENT_TIMESTAMP') {
						$sql .= ' default CURRENT_TIMESTAMP';
					} else {
						$sql .= ' default ' . (is_numeric($column['default']) ? $column['default'] : "'" . $column['default'] . "'");
					}
				}

				if (isset($column['on_update'])) {
					$sql .= ' ON UPDATE ' . $column['on_update'];
				}

				if (isset($column['auto_increment'])) {
					$sql .= ' auto_increment';
				}

				if (isset($column['comment'])) {
					$sql .= " COMMENT '" . $column['comment'] . "'";
				}

				if (!db_execute($sql, $log, $db_conn)) {
					return false;
				}
			}
		}
	}

	if ($removecolumns) {
		$result = db_fetch_assoc('SHOW columns FROM `' . $table . '`', $log, $db_conn);
		foreach($result as $arr) {
			if (!in_array($arr['Field'], $allcolumns)) {
				if (!db_remove_column($table, $arr['Field'], $log, $db_conn)) {
					return false;
				}
			}
		}
	}

	$info = db_fetch_row("SELECT ENGINE, TABLE_COMMENT
		FROM information_schema.TABLES
		WHERE TABLE_SCHEMA = SCHEMA()
		AND TABLE_NAME = '$table'", $log, $db_conn);

	if (isset($info['TABLE_COMMENT']) && isset($data['comment']) && str_replace("'", '', $info['TABLE_COMMENT']) != str_replace("'", '', $data['comment'])) {
		if (!db_execute("ALTER TABLE `$table` COMMENT '" . str_replace("'", '', $data['comment']) . "'", $log, $db_conn)) {
			return false;
		}
	}

	if (isset($info['ENGINE']) && isset($data['type']) && strtolower($info['ENGINE']) != strtolower($data['type'])) {
		if (!db_execute("ALTER TABLE `$table` ENGINE = " . $data['type'], $log, $db_conn)) {
			return false;
		}
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
						if (!db_execute("ALTER TABLE `$table` DROP INDEX `$n`", $log, $db_conn) ||
						    !db_execute("ALTER TABLE `$table` ADD INDEX `$n` (" . $k['name'] . '` (' . db_format_index_create($k['columns']) . ')', $log, $db_conn)) {
							return false;
						}
					}
					break;
				}
			}

			if ($removeindex) {
				if (!db_execute("ALTER TABLE `$table` DROP INDEX `$n`", $log, $db_conn)) {
					return false;
				}
			}
		}
	}

	// Add any indexes
	if (isset($data['keys'])) {
		foreach ($data['keys'] as $k) {
			if (!isset($allindexes[$k['name']])) {
				if (!db_execute("ALTER TABLE `$table` ADD INDEX `" . $k['name'] . '` (' . db_format_index_create($k['columns']) . ')', $log, $db_conn)) {
					return false;
				}
			}
		}
	}

	// FIXME: It won't allow us to drop a primary key that is set to auto_increment

	// Check Primary Key
	if (!isset($data['primary']) && isset($allindexes['PRIMARY'])) {
		if (!db_execute("ALTER TABLE `$table` DROP PRIMARY KEY", $log, $db_conn)) {
			return false;
		}
		unset($allindexes['PRIMARY']);
	}

	if (isset($data['primary'])) {
		if (!isset($allindexes['PRIMARY'])) {
			// No current primary key, so add it
			if (!db_execute("ALTER TABLE `$table` ADD PRIMARY KEY(" . db_format_index_create($data['primary']) . ')', $log, $db_conn)) {
				return false;
			}
		} else {
			$add = array_diff($data['primary'], $allindexes['PRIMARY']);
			$del = array_diff($allindexes['PRIMARY'], $data['primary']);
			if (!empty($add) || !empty($del)) {
				if (!db_execute("ALTER TABLE `$table` DROP PRIMARY KEY", $log, $db_conn) ||
				    !db_execute("ALTER TABLE `$table` ADD PRIMARY KEY(" . db_format_index_create($data['primary']) . ')', $log, $db_conn)) {
					return false;
				}
			}
		}
	}

	if (isset($data['row_format']) && db_get_global_variable('innodb_file_format', $db_conn) == 'Barracuda') {
		db_execute("ALTER TABLE `$table` ROW_FORMAT=" . $data['row_format'], $log, $db_conn);
	}

	$charset= '';
	if (isset($data['charset'])) {
		$charset = ' DEFAULT CHARSET=' . $data['charset'];
	}

	if ($charset != '') {
		db_execute("ALTER TABLE `$table` " . $charset, $log, $db_conn);
	}

	return true;
}

function db_format_index_create($indexes) {
	if (is_array($indexes)) {
		$outindex = '';
		foreach($indexes as $index) {
			$index = trim($index);
			if (substr($index, -1) == ')') {
				$outindex .= ($outindex != '' ? ',':'') . $index;
			} else {
				$outindex .= ($outindex != '' ? ',':'') . '`' . $index . '`';
			}
		}

		return $outindex;
	} else {
		$indexes = trim($indexes);
		if (substr($indexes, -1) == ')') {
			return $indexes;
		} else {
			return '`' . trim($indexes, ' `') . '`';
		}
	}
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
				if ($c > 0) {
					$sql .= ",\n";
				}

				$sql .= '`' . $column['name'] . '`';

				if (isset($column['type'])) {
					$sql .= ' ' . $column['type'];
				}

				if (isset($column['unsigned'])) {
					$sql .= ' unsigned';
				}

				if (isset($column['NULL']) && $column['NULL'] == false) {
					$sql .= ' NOT NULL';
				}

				if (isset($column['NULL']) && $column['NULL'] == true && !isset($column['default'])) {
					$sql .= ' default NULL';
				}

				if (isset($column['default'])) {
					if (strtolower($column['type']) == 'timestamp' && $column['default'] === 'CURRENT_TIMESTAMP') {
						$sql .= ' default CURRENT_TIMESTAMP';
					} else {
						$sql .= ' default ' . (is_numeric($column['default']) ? $column['default'] : "'" . $column['default'] . "'");
					}
				}

				if (isset($column['on_update'])) {
					$sql .= ' ON UPDATE ' . $column['on_update'];
				}

				if (isset($column['comment'])) {
					$sql .= " COMMENT '" . $column['comment'] . "'";
				}

				if (isset($column['auto_increment'])) {
					$sql .= ' auto_increment';
				}

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

		if (isset($data['keys']) && cacti_sizeof($data['keys'])) {
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

		if (isset($data['charset'])) {
			$sql .= ' DEFAULT CHARSET=' . $data['charset'];
		}

		if (isset($data['row_format']) && db_get_global_variable('innodb_file_format', $db_conn) == 'Barracuda') {
			$sql .= ' ROW_FORMAT=' . $data['row_format'];
		}

		return db_execute($sql, $log, $db_conn);
	}
}

/* db_get_global_variable - get the value of a global variable
   @param $variable - the variable to obtain
   @param $db_conn - the database connection to use
   @returns - (string) the value of the variable if found */
function db_get_global_variable($variable, $db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$data = db_fetch_row("SHOW GLOBAL VARIABLES LIKE '$variable'", true, $db_conn);

	if (cacti_sizeof($data)) {
		return $data['Value'];
	} else {
		return false;
	}
}

/* db_get_session_variable - get the value of a session variable
   @param $variable - the variable to obtain
   @param $db_conn - the database connection to use
   @returns - (string) the value of the variable if found */
function db_get_session_variable($variable, $db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$data = db_fetch_row("SHOW SESSION VARIABLES LIKE '$variable'", true, $db_conn);

	if (cacti_sizeof($data)) {
		return $data['Value'];
	} else {
		return false;
	}
}

/* db_begin_transaction - start a transaction
   @param $db_conn - the database connection to use
   @returns - (bool) if the begin transaction was successful */
function db_begin_transaction($db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	return $db_conn->beginTransaction();
}

/* db_commit_transaction - commit a transaction
   @param $db_conn - the database connection to use
   @returns - (bool) if the commit transaction was successful */
function db_commit_transaction($db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	return $db_conn->commit();
}

/* db_rollback_transaction - rollback a transaction
   @param $db_conn - the database connection to use
   @returns - (bool) if the rollback transaction was successful */
function db_rollback_transaction($db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	return $db_conn->rollBack();
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

	if (cacti_sizeof($array)) {
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

function _db_replace($db_conn, $table, $fieldArray, $keyCols) {
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
		cacti_log("ERROR: SQL Save Failed for Table '$table'.  SQL:'" . clean_up_lines($sql) . "'", false, 'DBCALL');
	}

	return db_fetch_insert_id($db_conn);
}

/* sql_save - saves data to an sql table
   @param $array_items - an array containing each column -> value mapping in the row
   @param $table_name - the name of the table to make the replacement in
   @param $key_cols - the primary key(s)
   @returns - the auto incriment id column (if applicable) */
function sql_save($array_items, $table_name, $key_cols = 'id', $autoinc = true, $db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port, $database_last_error;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
	}

	$log = true;
	if (!db_table_exists($table_name, $log, $db_conn)) {
		$error_message = "SQL Save on table '$table_name': Table does not exist, unable to save!";
		raise_message('sql_save_table', $error_message, MESSAGE_LEVEL_ERROR);
		cacti_log('ERROR: ' . $error_message, false, 'DBCALL');
		cacti_debug_backtrace('SQL', false, true, 0, 1);
		return false;
	}

	$cols = db_get_table_column_types($table_name, $db_conn);

	cacti_log("DEVEL: SQL Save on table '$table_name': '" . serialize($array_items) . "'", false, 'DBCALL', POLLER_VERBOSITY_DEVDBG);

	foreach ($array_items as $key => $value) {
		if (!isset($cols[$key])) {
			$error_message = "SQL Save on table '$table_name': Column '$key' does not exist, unable to save!";
			raise_message('sql_save_key', $error_message, MESSAGE_LEVEL_ERROR);
			cacti_log('ERROR: ' . $error_message, false, 'DBCALL');
			cacti_debug_backtrace('SQL', false, true, 0, 1);
			return false;
		}

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

	$replace_result = _db_replace($db_conn, $table_name, $array_items, $key_cols);

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
	return trim(clean_up_lines($sql), ';');
}

function db_get_column_attributes($table, $columns) {
	if (empty($columns) || empty($table)) {
		return false;
	}

	if (!is_array($columns)) {
		$columns = explode(',', $columns);
	}

	$sql = 'SELECT * FROM information_schema.columns
		WHERE table_schema = SCHEMA()
		AND table_name = ?
		AND column_name IN (';

	$column_names = array();
	foreach ($columns as $column) {
		if (!empty($column)) {
			$sql .= (cacti_sizeof($column_names) ? ',' : '') . '?';
			$column_names[] = $column;
		}
	}
	$sql .= ')';

	$params = array_merge(array($table), $column_names);

	return db_fetch_assoc_prepared($sql, $params);
}

function db_get_columns_length($table, $columns) {
	$column_data = db_get_column_attributes($table, $columns);

	if (!empty($column_data)) {
		return array_rekey($column_data, 'COLUMN_NAME','CHARACTER_MAXIMUM_LENGTH');
	}

	return false;
}

function db_get_column_length($table, $column) {
	$column_data = db_get_columns_length($table, $column);

	if (!empty($column_data) && isset($column_data[$column])) {
		return $column_data[$column];
	}

	return false;
}

function db_check_password_length() {
	$len = db_get_column_length('user_auth', 'password');

	if ($len === false) {
		die(__('Failed to determine password field length, can not continue as may corrupt password'));
	} else if ($len < 80) {
		/* Ensure that the password length is increased before we start updating it */
		db_execute("ALTER TABLE user_auth MODIFY COLUMN password varchar(256) NOT NULL default ''");
		$len = db_get_column_length('user_auth','password');
		if ($len < 80) {
			die(__('Failed to alter password field length, can not continue as may corrupt password'));
		}
	}
}

function db_echo_sql($line, $force = false) {
	global $config;

	file_put_contents(sys_get_temp_dir() . '/cacti-sql.log', get_debug_prefix() . $line, FILE_APPEND);
}

/* db_error - return the last error from the database
   @returns - string - the last database error if any */
function db_error() {
	global $database_last_error;

	return $database_last_error;
}

/* db_get_default_database - Get the database name of the current database or return the default database name
   @returns - string - either current db name or configuration default if no connection/name */
function db_get_default_database($db_conn = false) {
	global $database_default;

	$database = db_fetch_cell('SELECT DATABASE()', '', true, $db_conn);
	if (empty($database)) {
		$database = $database_default;
	}
}

/* db_force_remote_cnn - force the remote collector to use main data collector connection
   @returns - null */
function db_force_remote_cnn() {
	global $database_default, $database_hostname, $database_username, $database_password;
	global $database_port, $database_ssl, $database_ssl_key, $database_ssl_cert, $database_ssl_ca;

	global $rdatabase_default, $rdatabase_hostname, $rdatabase_username, $rdatabase_password;
	global $rdatabase_port, $rdatabase_ssl, $rdatabase_ssl_key, $rdatabase_ssl_cert, $rdatabase_ssl_ca;

	// Connection worked, so now override the default settings so that it will always utilize the remote connection
	$database_default   = $rdatabase_default;
	$database_hostname  = $rdatabase_hostname;
	$database_username  = $rdatabase_username;
	$database_password  = $rdatabase_password;
	$database_port      = $rdatabase_port;
	$database_ssl       = $rdatabase_ssl;
	$database_ssl_key   = $rdatabase_ssl_key;
	$database_ssl_cert  = $rdatabase_ssl_cert;
	$database_ssl_ca    = $rdatabase_ssl_ca;
}

