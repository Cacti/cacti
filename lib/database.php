<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

/**
 * db_connect_real - makes a connection to the database server
 *
 * @param  (string) The hostname of the database server, 'localhost'
 *                  if the database server is running on this machine
 * @param  (string) The username to connect to the database server as
 * @param  (string) The password to connect to the database server with
 * @param  (string) The name of the database to connect to
 * @param  (string) The type of database server.  Only 'mysql' is currently supported
 * @param  (int)    The port to communicate with MySQL/MariaDB on
 * @param  (int)    The number a time the server should attempt to connect before failing
 * @param  (bool)   A boolean true or false
 * @param  (string) String that points to the client ssl key file
 * @param  (string) String that points to the client ssl cert file
 * @param  (string) String that points to the ssl ca file
 * @param mixed $device
 * @param mixed $user
 * @param mixed $pass
 * @param mixed $db_name
 * @param mixed $db_type
 * @param mixed $port
 * @param mixed $retries
 * @param mixed $db_ssl
 * @param mixed $db_ssl_key
 * @param mixed $db_ssl_cert
 * @param mixed $db_ssl_ca
 * @param mixed $persist
 *
 * @returns (bool|object) connection object on success, false for error
 */
function db_connect_real($device, $user, $pass, $db_name, $db_type = 'mysql', $port = '3306', $retries = 20,
	$db_ssl = false, $db_ssl_key = '', $db_ssl_cert = '', $db_ssl_ca = '', $persist = false) {
	global $database_sessions, $database_details, $database_total_queries, $database_persist, $config;

	$database_total_queries = 0;

	$i = 0;

	if (isset($database_sessions["$device:$port:$db_name"])) {
		return $database_sessions["$device:$port:$db_name"];
	}

	$odevice = $device;

	$flags = array();

	if ($db_type == 'mysql') {
		/**
		 * Using 'localhost' will force unix sockets mode, which breaks when
		 * attempting to use mysql on a different port
		 */
		if ($device == 'localhost' && $port != '3306') {
			$device = '127.0.0.1';
		}

		if (!defined('PDO::MYSQL_ATTR_FOUND_ROWS')) {
			if (!empty($config['DEBUG_READ_CONFIG_OPTION'])) {
				$prefix = get_debug_prefix();
				file_put_contents(sys_get_temp_dir() . '/cacti-option.log',
					"$prefix\n$prefix ************* DATABASE MODULE MISSING ****************\n" .
					"$prefix session name: $odevice:$port:$db_name\n$prefix\n", FILE_APPEND);
			}

			return false;
		}

		if (isset($database_persist) && $database_persist == true || $persist) {
			$flags[PDO::ATTR_PERSISTENT] = true;
		}

		$flags[PDO::MYSQL_ATTR_FOUND_ROWS] = true;

		if ($db_ssl) {
			if ($db_ssl_ca != '') {
				if (file_exists($db_ssl_ca)) {
					$flags[PDO::MYSQL_ATTR_SSL_CA] = $db_ssl_ca;
				}
			}

			if ($db_ssl_key != '' && $db_ssl_cert != '') {
				if (file_exists($db_ssl_key) && file_exists($db_ssl_cert)) {
					$flags[PDO::MYSQL_ATTR_SSL_KEY]  = $db_ssl_key;
					$flags[PDO::MYSQL_ATTR_SSL_CERT] = $db_ssl_cert;
				}
			}
		}
	}

	/* set connection timout for down servers */
	$flags[PDO::ATTR_TIMEOUT] = 2;
	$flage[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

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

			$object_hash = spl_object_hash($cnn_id);

			$database_details[$object_hash] = array(
				'database_conn'     => $cnn_id,
				'database_hostname' => $device,
				'database_username' => $user,
				'database_password' => $pass,
				'database_default'  => $db_name,
				'database_type'     => $db_type,
				'database_port'     => $port,
				'database_retries'  => $retries,
				'database_ssl'      => $db_ssl,
				'database_ssl_key'  => $db_ssl_key,
				'database_ssl_cert' => $db_ssl_cert,
				'database_ssl_ca'   => $db_ssl_ca,
				'database_persist'  => $persist,
			);

			$ver = db_get_global_variable('version', $cnn_id);

			if (strpos($ver, 'MariaDB') !== false) {
				$srv  = 'MariaDB';
				$ver  = str_replace('-MariaDB', '', $ver);
			} else {
				$srv = 'MySQL';
			}

			if (version_compare('8.0.0', $ver, '<=')) {
				$bad_modes[] = 'NO_AUTO_CREATE_USER';
			}

			// Get rid of bad modes
			$modes     = explode(',', db_fetch_cell('SELECT @@sql_mode', '', false));
			$new_modes = array();

			foreach ($modes as $mode) {
				if (array_search($mode, $bad_modes, true) === false) {
					$new_modes[] = $mode;
				}
			}

			// Add Required modes
			$required_modes[] = 'ALLOW_INVALID_DATES';
			$required_modes[] = 'NO_ENGINE_SUBSTITUTION';

			foreach ($required_modes as $mode) {
				if (array_search($mode, $new_modes, true) === false) {
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
				file_put_contents(sys_get_temp_dir() . '/cacti-option.log',
					"$prefix\n$prefix ************* DATABASE OPEN ****************\n" .
					"$prefix session name: $odevice:$port:$db_name\n$prefix\n", FILE_APPEND);
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
				'Code'  => $e->getCode(),
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

function db_check_reconnect(object|false $db_conn = false) {
	global $config, $database_details;

	include(CACTI_PATH_INCLUDE . '/config.php');

	if (cacti_sizeof($database_details) && $db_conn !== false) {
		foreach ($database_details as $det) {
			if (spl_object_hash($det['database_conn']) == spl_object_hash($db_conn)) {
				$database_hostname = $det['database_hostname'];
				$database_username = $det['database_username'];
				$database_password = $det['database_password'];
				$database_default  = $det['database_default'];
				$database_type     = $det['database_type'];
				$database_port     = $det['database_port'];
				$database_retries  = $det['database_retries'];
				$database_ssl      = $det['database_ssl'];
				$database_ssl_key  = $det['database_ssl_key'];
				$database_ssl_cert = $det['database_ssl_cert'];
				$database_ssl_ca   = $det['database_ssl_ca'];

				break;
			}
		}
	} else {
		if (!isset($database_ssl)) {
			$database_ssl      = false;
		}

		if (!isset($database_ssl_key)) {
			$database_ssl_key  = '';
		}

		if (!isset($database_ssl_cert)) {
			$database_ssl_cert = '';
		}

		if (!isset($database_ssl_ca)) {
			$database_ssl_ca   = '';
		}

		if (!isset($database_retries)) {
			$database_retries  = 2;
		}

		if (!isset($database_port)) {
			$database_port     = 3306;
		}
	}

	if ($db_conn !== false) {
		$version = db_fetch_cell('SELECT 1', '', false, $db_conn);
	} else {
		$version = db_fetch_cell('SELECT 1');
	}

	if ($version === false) {
		syslog(LOG_ALERT, 'CACTI: Database Connection went away.  Attempting to reconnect!');

		db_close();

		// Connect to the database server
		db_connect_real(
			$database_hostname,
			$database_username,
			$database_password,
			$database_default,
			$database_type,
			$database_port,
			$database_retries,
			$database_ssl,
			$database_ssl_key,
			$database_ssl_cert,
			$database_ssl_ca
		);
	}
}

function db_warning_handler($errno, $errstr, $errfile, $errline, $errcontext = array()) {
	throw new Exception($errstr, $errno);
}

/**
 * db_binlog_enabled - Checks to see if binary logging is enabled on the server
 *
 * @return (bool) true if enabled, else false
 */
function db_binlog_enabled() {
	$enabled = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "log_bin"');

	if (cacti_sizeof($enabled)) {
		if (strtolower($enabled['Value']) == 'on' || $enabled['Value'] == 1) {
			return true;
		}
	}

	return false;
}

/**
 * db_get_active_replicas - Returns the hostnames of all active replicas
 *
 * @return (array) The list of active replicas as an array of hostnames
 */
function db_get_active_replicas() {
	return array_rekey(
		db_fetch_assoc("SELECT SUBSTRING_INDEX(HOST, ':', 1) AS host
			FROM information_schema.processlist
			WHERE command = 'Binlog Dump'"),
		'host', 'host'
	);
}

/**
 * db_close - closes the open connection
 *
 * @param  (bool|resource) Either the connection to use of false to use the default
 * @param mixed $db_conn
 *
 * @return (bool) the result of the close command
 */
function db_close($db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$db_conn                                                                  = null;
	$database_sessions["$database_hostname:$database_port:$database_default"] = null;

	return true;
}

/**
 * db_execute - run an sql query and do not return any output
 *
 * @param  (string)        The SQL query to execute
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false for the default
 * @param mixed $sql
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool) '1' for success, false on error
 */
function db_execute($sql, $log = true, $db_conn = false) {
	return db_execute_prepared($sql, array(), $log, $db_conn);
}

/**
 * db_execute_prepared - run an sql query and do not return any output
 *
 * @param  (string)        The SQL query to execute
 * @param  (array)         An array of values to be prepared into the SQL
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false for the default
 * @param  (string)        The database action/function to run
 * @param  (bool)          To Be Completed
 * @param  (string)        To Be Completed
 * @param  (array)         To Be Completed
 * @param mixed $sql
 * @param mixed $params
 * @param mixed $log
 * @param mixed $db_conn
 * @param mixed $execute_name
 * @param mixed $default_value
 * @param mixed $return_func
 * @param mixed $return_params
 *
 * @return (bool) '1' for success, false for failed
 */
function db_execute_prepared($sql, $params = array(), $log = true, $db_conn = false, $execute_name = 'Exec', $default_value = true, $return_func = 'no_return_function', $return_params = array()) {
	global $database_sessions, $database_default, $config, $database_hostname, $database_port, $database_total_queries, $database_last_error, $database_log, $affected_rows;

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

	$affected_rows[spl_object_hash($db_conn)] = 0;

	while (true) {
		$query = $db_conn->prepare($sql);

		$code = 0;
		$en   = '';

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
			$code      = $ex->getCode();
			$en        = $code;
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
				$en        = $errorinfo[1];
			} else {
				$code = $db_conn->errorCode();

				if ($code != '00000' && $code != '01000') {
					$errorinfo = $db_conn->errorInfo();
					$en        = $errorinfo[1];
				}
			}
		}

		if ($en == '') {
			$affected_rows[spl_object_hash($db_conn)] = $query->rowCount();

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

			if (!db_column_exists('settings', 'name')) {
				$log = false;
			}

			if ($log) {
				if ($en == 1213 || $en == 1205) {
					$errors++;

					if ($errors > 30) {
						cacti_log("ERROR: Too many Lock/Deadlock errors occurred! SQL:'" . clean_up_lines($sql) . "'", true, 'DBCALL', POLLER_VERBOSITY_DEBUG);
						$database_last_error = 'Too many Lock/Deadlock errors occurred!';
					} else {
						usleep(200000);

						continue;
					}
				} elseif ($en == 1153) {
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

/**
 * db_fetch_cell - run a 'select' sql query and return the first column of the
 *   first row found
 *
 * @param  (string)        The SQL query to execute
 * @param  (string)        Use this column name instead of the first one
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $sql
 * @param mixed $col_name
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool)  The output of the sql query as a single variable
 */
function db_fetch_cell($sql, $col_name = '', $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_cell($sql, $col_name = \'' . $col_name . '\', $log = true, $db_conn = false)' . "\n");
	}

	return db_fetch_cell_prepared($sql, array(), $col_name, $log, $db_conn);
}

/**
 * db_fetch_cell_prepared - run a 'select' sql query and return the first column of the
 *   first row found
 *
 * @param  (string)        The SQL query to execute
 * @param  (array)         An array of values to be prepared into the SQL
 * @param  (string)        Use this column name instead of the first one
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $sql
 * @param mixed $params
 * @param mixed $col_name
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool) The output of the sql query as a single variable
 */
function db_fetch_cell_prepared($sql, $params = array(), $col_name = '', $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_cell_prepared($sql, $params = ' . clean_up_lines(var_export($params, true)) . ', $col_name = \'' . $col_name . '\', $log = true, $db_conn = false)' . "\n");
	}

	return db_execute_prepared($sql, $params, $log, $db_conn, 'Cell', false, 'db_fetch_cell_return', $col_name);
}

/**
 * db_fetch_cell_return - Function to process and return data from the
 *   db_fetch_cell_prepared function
 *
 * @param  (string) The SQL query to run
 * @param  (string) The column to return if the query is more row or associative
 *                  in the case of associated, returns the column from the first
 *                  row.
 * @param mixed $query
 * @param mixed $col_name
 *
 * @return (bool|string) The value of the column or false if failed
 */
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

/**
 * db_fetch_row - run a 'select' sql query and return the first row found
 *
 * @param  (string)        The SQL query to execute
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $sql
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool|array) The first row of the result or false if failed
 */
function db_fetch_row($sql, $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_row(\'' . clean_up_lines($sql) . '\', $log = ' . $log . ', $db_conn = ' . ($db_conn ? 'true' : 'false') .')' . "\n");
	}

	return db_fetch_row_prepared($sql, array(), $log, $db_conn);
}

/**
 * db_fetch_row_prepared - run a 'select' sql query and return the first row found
 *
 * @param  (string)        The SQL query to execute
 * @param  (array)         An array of values to be prepared into the SQL
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $sql
 * @param mixed $params
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool|array) The first row of the result or false if failed
 */
function db_fetch_row_prepared($sql, $params = array(), $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_row_prepared(\'' . clean_up_lines($sql) . '\', $params = (\'' . implode('\', \'', $params) . '\'), $log = ' . $log . ', $db_conn = ' . ($db_conn ? 'true' : 'false') .')' . "\n");
	}

	return db_execute_prepared($sql, $params, $log, $db_conn, 'Row', false, 'db_fetch_row_return');
}

/**
 * db_fetch_row_return - Function to execute and process the results for the
 *   db_fetch_row_prepared() function.
 *
 * @param  (string) The prepared Query
 * @param mixed $query
 *
 * @return (array) The row, or false on failure
 */
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

/**
 * db_fetch_assoc - run a 'select' sql query and return all rows found
 *
 * @param  (string)        The SQL query to execute
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $sql
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool|array)    The entire result set or false on error
 */
function db_fetch_assoc($sql, $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_assoc($sql, $log = true, $db_conn = false)' . "\n");
	}

	return db_fetch_assoc_prepared($sql, array(), $log, $db_conn);
}

/**
 * db_fetch_assoc_prepared - run a 'select' sql query and return all rows found
 *
 * @param  (string)        The sql query to execute
 * @param  (array)         An array of values to be prepared into the SQL
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $sql
 * @param mixed $params
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool|array)    The entire result or false on error
 */
function db_fetch_assoc_prepared($sql, $params = array(), $log = true, $db_conn = false) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_assoc_prepared($sql, $params = array(), $log = true, $db_conn = false)' . "\n");
	}

	return db_execute_prepared($sql, $params, $log, $db_conn, 'Row', array(), 'db_fetch_assoc_return');
}

/**
 * db_fetch_assoc_return - Function to execute and process the results for the
 *   db_fetch_assoc_prepared() function.
 *
 * @param  (string)     The prepared Query
 * @param mixed $query
 *
 * @return (bool|array) The associated array of data, or false on failure
 */
function db_fetch_assoc_return($query) {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_assoc_return($query)' . "\n");
	}

	$r = $query->fetchAll(PDO::FETCH_ASSOC);

	return (is_array($r)) ? $r : array();
}

/**
 * db_fetch_insert_id - get the last insert_id or auto increment
 *
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $db_conn
 *
 * @return (bool|int)      The id of the last auto increment row or false on error
 */
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

/**
 * db_affected_rows - return the number of rows affected by the last transaction
 *
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $db_conn
 *
 * @return (bool|int)      The number of rows affected by the last transaction,
 *                         or false on error
 */
function db_affected_rows($db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port, $affected_rows;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	return $affected_rows[spl_object_hash($db_conn)];
}

/**
 * db_add_column - add a column to table
 *
 * @param  (string)        The name of the table
 * @param  (string)        Array of column data ex: array('name' => 'test' .
 *                         rand(1, 200), 'type' => 'varchar (255)', 'NULL' => false)
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $table
 * @param mixed $column
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool) '1' for success, false for error
 */
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

	foreach ($result as $arr) {
		$columns[] = $arr['Field'];
	}

	if (isset($column['name'])) {
		if (!in_array($column['name'], $columns, true)) {
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
				if (in_array(strtolower($column['type']), array('timestamp','datetime','date'), true) && $column['default'] === 'CURRENT_TIMESTAMP') {
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

	return false;
}

/* db_change_column - update a column to table
   @param $table - the name of the table
   @param $column - array of column data ex: array('old_name' => 'test', 'name' => 'newtest' . rand(1, 200), 'type' => 'varchar (255)', 'NULL' => false)
   @param $log - whether to log error messages, defaults to true
   @returns - '1' for success, '0' for error */
function db_change_column($table, $column, $log = true, $db_conn = false) {
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

	foreach ($result as $arr) {
		$columns[] = $arr['Field'];
	}

	if (isset($column['name'])) {
		if (!isset($column['old_name'])) {
			$column['old_name'] = $column['name'];
		}

		if (in_array($column['old_name'], $columns, true)) {
			if ($column['old_name'] == $column['name'] || !in_array('name', $columns, true)) {
				$sql = 'ALTER TABLE `' . $table . '` CHANGE `' . $column['old_name'] . '` `' . $column['name'] . '`';

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
		}
	}

	return false;
}

/**
 * db_remove_column - remove a column to table
 *
 * @param  (string)        The name of the table
 * @param  (string)        The name of the column
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $table
 * @param mixed $column
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool) '1' for success, false for error
 */
function db_remove_column($table, $column, $log = true, $db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$result  = db_fetch_assoc('SHOW columns FROM `' . $table . '`', $log, $db_conn);
	$columns = array();

	foreach ($result as $arr) {
		$columns[] = $arr['Field'];
	}

	if (isset($column) && in_array($column, $columns, true)) {
		$sql = 'ALTER TABLE `' . $table . '` DROP `' . $column . '`';

		return db_execute($sql, $log, $db_conn);
	}

	return true;
}

/**
 * db_add_index - adds a new index to a table
 *
 * @param  (string)        The name of the table
 * @param  (string)        The type of the index
 * @param  (string)        The name of the index
 * @param  (array)         An array that defines the columns to include in the index
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $table
 * @param mixed $type
 * @param mixed $key
 * @param mixed $columns
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool)   The result of the operation true or false
 */
function db_add_index($table, $type, $key, $columns, $log = true, $db_conn = false) {
	if (!is_array($columns)) {
		$columns = array($columns);
	}

	$sql = 'ALTER TABLE `' . $table . '` ADD ' . $type . ' `' . $key . '`(`' . implode('`,`', $columns) . '`)';

	if (db_index_exists($table, $key, false, $db_conn)) {
		$type = str_ireplace('UNIQUE ', '', $type);

		if (!db_execute("ALTER TABLE $table DROP $type $key", $log, $db_conn)) {
			return false;
		}
	}

	return db_execute($sql, $log, $db_conn);
}

/**
 * db_index_exists - checks whether an index exists
 *
 * @param  (string)        The name of the table
 * @param  (string)        The name of the index
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $table
 * @param mixed $index
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool) The output of the sql query as a single variable
 */
function db_index_exists($table, $index, $log = true, $db_conn = false) {
	global $database_log, $config;

	if (!isset($database_log)) {
		$database_log = false;
	}

	$_log         = $database_log;
	$database_log = false;

	$_data = db_fetch_assoc("SHOW KEYS FROM `$table`", $log, $db_conn);
	$_keys = array_rekey($_data, 'Key_name', 'Key_name');

	$database_log = $_log;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_index_exists(\'' . $table . '\', \'' . $index .'\'): '
			. in_array($index, $_keys, true) . ' - '
			. clean_up_lines(var_export($_keys, true)));
	}

	return in_array($index, $_keys, true);
}

/**
 * db_index_exists - checks whether an index exists
 *
 * @param  (string)        The name of the table
 * @param  (string)        The name of the index
 * @param  (array)         The columns of the index that should match
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $table
 * @param mixed $index
 * @param mixed $columns
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool) the output of the sql query as a single variable
 */
function db_index_matches($table, $index, $columns, $log = true, $db_conn = false) {
	global $database_log, $config;

	if (!isset($database_log)) {
		$database_log = false;
	}

	if (!is_array($columns)) {
		$columns = array($columns);
	}

	$_log         = $database_log;
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
		if (!in_array($column, $_cols, true)) {
			$status = -1;

			break;
		}
	}

	if ($status == 0) {
		foreach ($_cols as $column) {
			if (!in_array($column, $columns, true)) {
				$status = 1;
			}
		}
	}

	$database_log = $_log;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_index_matches(\'' . $table . '\', \'' . $index .'\'): '
			. $status . "\n ::: "
			. clean_up_lines(var_export($columns, true))
			. ' ::: '
			. clean_up_lines(var_export($_cols, true)));
	}

	return $status;
}

/**
 * db_table_exists - checks whether a table exists
 *
 * @param  (string)        The name of the table
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $table
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool) The output of the sql query as a single variable
 */
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

/**
 * db_cacti_initialized - checks whether cacti has been initialized properly and if not exits with a message
 *
 * @param  (bool) Is the session a web session.
 * @param mixed $is_web
 *
 * @return (bool) true if the database is initialized else false
 */
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
		print($is_web ? '<head><link href="' . CACTI_PATH_URL . 'include/themes/modern/main.css" type="text/css" rel="stylesheet"></head>':'');
		print($is_web ? '<table style="height:40px;"><tr><td></td></tr></table>':'');
		print($is_web ? '<table style="margin-left:auto;margin-right:auto;width:80%;border:1px solid rgba(98,125,77,1)" class="cactiTable"><tr class="cactiTableTitle"><td style="color:snow;font-weight:bold;">Fatal Error - Cacti Database Not Initialized</td></tr>':'');
		print($is_web ? '<tr class="installArea"><td>':'');
		print($is_web ? '<p>':'') . 'The Cacti Database has not been initialized.  Please initialize it before continuing.' . ($is_web ? '</p>':"\n");
		print($is_web ? '<p>':'') . 'To initialize the Cacti database, issue the following commands either as root or using a valid account.' . ($is_web ? '</p>':"\n");
		print($is_web ? '<p style="font-weight:bold;padding-left:25px;">':'') . '  mysqladmin -uroot -p create cacti' . ($is_web ? '</p>':"\n");
		print($is_web ? '<p style="font-weight:bold;padding-left:25px;">':'') . '  mysql -uroot -p -e "grant all on cacti.* to \'someuser\'@\'localhost\' identified by \'somepassword\'"' . ($is_web ? '</p>':"\n");
		print($is_web ? '<p style="font-weight:bold;padding-left:25px;">':'') . '  mysql -uroot -p -e "grant select on mysql.time_zone_name to \'someuser\'@\'localhost\' identified by \'somepassword\'"' . ($is_web ? '</p>':"\n");
		print($is_web ? '<p style="font-weight:bold;padding-left:25px;">':'') . '  mysql -uroot -p cacti < /pathcacti/cacti.sql' . ($is_web ? '</p>':"\n");
		print($is_web ? '<p>':'') . 'Where <b>/pathcacti/</b> is the path to your Cacti install location.' . ($is_web ? '</p>':"\n");
		print($is_web ? '<p>':'') . 'Change <b>someuser</b> and <b>somepassword</b> to match your site preferences.  The defaults are <b>cactiuser</b> for both user and password.' . ($is_web ? '</p>':"\n");
		print($is_web ? '<p>':'') . '<b>NOTE:</b> When installing a remote poller, the <b>config.php</b> file must be writable by the Web Server account, and must include valid connection information to the main Cacti server.  The file should be changed to read only after the install is completed.' . ($is_web ? '</p>':"\n");
		print($is_web ? '</td></tr></table>':'');

		exit;
	}
}

/**
 * db_column_exists - checks whether a column exists
 *
 * @param  (string)        The name of the table
 * @param  (string)        The name of the column
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $table
 * @param mixed $column
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool) The output of the sql query as a single variable
 */
function db_column_exists($table, $column, $log = true, $db_conn = false) {
	static $results = array();

	if (isset($results[$table][$column]) && !defined('IN_CACTI_INSTALL') && !defined('IN_PLUGIN_INSTALL')) {
		return $results[$table][$column];
	}

	$result = false;

	if (db_table_exists($table, $log, $db_conn)) {
		$result = (db_fetch_cell("SHOW columns FROM `$table` LIKE '$column'", '', $log, $db_conn) ? true : false);
	}

	$results[$table][$column] = $result;

	return $results[$table][$column];
}

/**
 * db_get_table_column_types - returns all the types for each column of a table
 *
 * @param  (string)        The name of the table
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $table
 * @param mixed $db_conn
 *
 * @return (array) An array of column types indexed by the column names
 */
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
		foreach ($columns as $col) {
			$cols[$col['Field']] = array('type' => $col['Type'], 'null' => $col['Null'], 'default' => $col['Default'], 'extra' => $col['Extra']);
		}
	}

	return $cols;
}

/**
 * db_update_table - a function that will update the table structure based upon
 *   a Cacti specific array specification constructed by the sqltable_to_php.php
 *   script.  That script will construct an array from the table definition.
 *   The script is very handy for both Cacti table construction and for plugins.
 *
 * @param  (string)        The name of the table
 * @param  (array)         Table definition as a Cacti specific array
 * @param  (bool)          Remove any existing columns that are not in the specification
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $table
 * @param mixed $data
 * @param mixed $removecolumns
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (array) An array of column types indexed by the column names
 */
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

	if (isset($data['charset'])) {
		$charset = ' DEFAULT CHARSET = ' . $data['charset'];
		db_execute("ALTER TABLE `$table` " . $charset, $log, $db_conn);
	}

	if (isset($data['collate'])) {
		$charset = ' COLLATE = ' . $data['collate'];
		db_execute("ALTER TABLE `$table` " . $charset, $log, $db_conn);
	}

	$info = db_fetch_row("SELECT ENGINE, TABLE_COMMENT
		FROM information_schema.TABLES
		WHERE TABLE_SCHEMA = SCHEMA()
		AND TABLE_NAME = '$table'", $log, $db_conn);

	if (isset($info['ENGINE']) && isset($data['type']) && strtolower($info['ENGINE']) != strtolower($data['type'])) {
		if (!db_execute("ALTER TABLE `$table` ENGINE = " . $data['type'], $log, $db_conn)) {
			return false;
		}
	}

	if (isset($data['row_format']) && db_get_global_variable('innodb_file_format', $db_conn) == 'Barracuda') {
		db_execute("ALTER TABLE `$table` ROW_FORMAT = " . $data['row_format'], $log, $db_conn);
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

			if (strpos(strtolower($arr['Type']), ' unsigned') !== false) {
				$arr['Type']     = str_ireplace(' unsigned', '', $arr['Type']);
				$arr['unsigned'] = true;
			}

			if ($column['type'] != $arr['Type'] || (isset($column['NULL']) && ($column['NULL'] ? 'YES' : 'NO') != $arr['Null'])
				|| (((!isset($column['unsigned']) || !$column['unsigned']) && isset($arr['unsigned']))
					|| (isset($column['unsigned']) && $column['unsigned'] && !isset($arr['unsigned'])))
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

		foreach ($result as $arr) {
			if (!in_array($arr['Field'], $allcolumns, true)) {
				if (!db_remove_column($table, $arr['Field'], $log, $db_conn)) {
					return false;
				}
			}
		}
	}

	if (isset($info['TABLE_COMMENT']) && isset($data['comment']) && str_replace("'", '', $info['TABLE_COMMENT']) != str_replace("'", '', $data['comment'])) {
		if (!db_execute("ALTER TABLE `$table` COMMENT '" . str_replace("'", '', $data['comment']) . "'", $log, $db_conn)) {
			return false;
		}
	}

	// Correct any indexes
	$indexes    = db_fetch_assoc("SHOW INDEX FROM `$table`", $log, $db_conn);
	$allindexes = array();

	foreach ($indexes as $index) {
		$allindexes[$index['Key_name']][$index['Seq_in_index'] - 1] = $index['Column_name'];
	}

	foreach ($allindexes as $n => $index) {
		if ($n != 'PRIMARY' && isset($data['keys'])) {
			$removeindex = true;

			foreach ($data['keys'] as $k) {
				if ($k['name'] == $n) {
					$removeindex = false;
					$add         = array_diff($k['columns'], $index);
					$del         = array_diff($index, $k['columns']);

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

	return true;
}

/**
 * db_format_index_create - Converts and array of indexes to a string
 *   that is compatible with the cacti database table creation array.
 *
 * @param  (array) An array of indexes to process
 * @param mixed $indexes
 *
 * @return (string) A list of preprocessed indexes into a form
 *                  compatible with the array definition
 */
function db_format_index_create($indexes) {
	if (is_array($indexes)) {
		$outindex = '';

		foreach ($indexes as $index) {
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

/**
 * db_table_create - checks whether a table exists
 *
 * @param  (string)        The name of the table
 * @param  (array)         The table creation array as defined by sqltable_to_php.php script
 * @param  (bool)          Whether to log error messages, defaults to true
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $table
 * @param mixed $data
 * @param mixed $log
 * @param mixed $db_conn
 *
 * @return (bool) The output of the sql query as a single variable
 */
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
		$c   = 0;
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

		if (isset($data['row_format']) && db_get_global_variable('innodb_file_format', $db_conn) == 'Barracuda') {
			$sql .= ' ROW_FORMAT = ' . $data['row_format'];
		}

		if (db_execute($sql, $log, $db_conn)) {
			if (isset($data['charset'])) {
				db_execute("ALLTER TABLE `$table` CHARSET = " . $data['charset']);
			}

			if (isset($data['collate'])) {
				db_execute("ALTER TABLE `$table` COLLATE = " . $data['collate']);
			}

			return true;
		} else {
			return false;
		}
	}
}

/**
 * db_get_global_variable - get the value of a global variable
 *
 * @param  (string)        The GLOBAL variable to obtain
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $variable
 * @param mixed $db_conn
 *
 * @returns - (string) the value of the variable if found
 */
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

/**
 * db_get_session_variable - get the value of a session variable
 *
 * @param  (string)        The variable to obtain
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $variable
 * @param mixed $db_conn
 *
 * @return (string) The value of the variable if found
 */
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

/**
 * db_begin_transaction - start a transaction
 *
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $db_conn
 *
 * @return (bool) If the begin transaction was successful
 */
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

/** db_commit_transaction - commit a transaction
 *
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $db_conn
 *
 * @return (bool) If the commit transaction was successful
 */
function db_commit_transaction($db_conn = false) {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	/* check for a connection being passed, if not use legacy behavior */
	if (!is_object($db_conn)) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];

		if (!is_object($db_conn)) {
			return false;
		}
	}

	if (db_fetch_cell('SELECT @@in_transaction') > 0) {
		return $db_conn->commit();
	}
}

/**
 * db_rollback_transaction - rollback a transaction
 *
 * @param  (bool|resource) The connection to use or false to use the default
 * @param mixed $db_conn
 *
 * @return (bool) if the rollback transaction was successful
 */
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

/**
 * array_to_sql_or - loops through a single dimensional array and converts each
 *   item to a string that can be used in the OR portion of an sql query in the
 *   following form:
 *
 *   column=item1 OR column=item2 OR column=item2 ...
 *
 * @param  (array)  The array to convert
 * @param  (string) The column to set each item in the array equal to
 * @param mixed $array
 * @param mixed $sql_column
 *
 * @return (string) A string that can be placed in a SQL OR statement
 */
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

/**
 * db_replace - replaces the data contained in a particular row
 *
 * @param $table_name - the name of the table to make the replacement in
 * @param $array_items - an array containing each column -> value mapping in the row
 * @param $keyCols - a string or array of primary keys
 * @param $autoQuote - whether to use intelligent quoting or not
 * @param mixed $db_conn
 *
 * @return - the auto increment id column (if applicable)
 */
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

/**
 * _db_replace - Internal function used as a part of the db_replace public function
 *
 * @param  (resource)     The database connection to use
 * @param  (string)       The table name to use
 * @param  (array)        An array of field values
 * @param  (string|array) A string of a key column or an array of key columns
 * @param mixed $db_conn
 * @param mixed $table
 * @param mixed $fieldArray
 * @param mixed $keyCols
 *
 * @return (bool|int) Either the insert id of the replace of false on error
 */
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

	foreach ($fieldArray as $k => $v) {
		if (!$first) {
			$sql .= ', ';
			$sql2 .= ', ';
		}
		$sql .= "`$k`";
		$sql2 .= $v;
		$first  = false;

		if (in_array($k, $keyCols, true)) {
			continue;
		} // skip UPDATE if is key

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

/**
 * sql_save - saves data to an sql table
 *
 * @param  (array)        An array containing each column -> value mapping in the row
 * @param  (string)       The name of the table to make the replacement in
 * @param  (string|array) The primary key(s) for the table
 * @param mixed $array_items
 * @param mixed $table_name
 * @param mixed $key_cols
 * @param mixed $autoinc
 * @param mixed $db_conn
 *
 * @return (bool|int)     The auto increment id column (if applicable)
 */
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

/**
 * db_qstr - Quote a string using the PDO function and also enclose
 *   the remainder of the string in single quotes.
 *
 * @param  (string)        The SQL to be escaped
 * @param  (bool|resource) The database connection or false if to use the default
 * @param mixed $s
 * @param mixed $db_conn
 *
 * @return (string) The escaped SQL string
 */
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

	return "'" . $s . "'";
}

/**
 * db_strip_control_chars - Strip control characters from SQL command
 *
 * @param  (string) The SQL command to loose it's control chars
 * @param mixed $sql
 *
 * @return (string) The SQL command
 */
function db_strip_control_chars($sql) {
	return trim(clean_up_lines($sql), ';');
}

/**
 * db_get_column_attributes - Get the attributes for a column or columns
 *
 * @param  (string) The name of the table
 * @param  (string) A comma separated list of columns
 * @param mixed $table
 * @param mixed $columns
 *
 * @return (array|bool) An array of column attributes on success or false if failed
 */
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

/**
 * db_get_columns_length - Get the length of a array of columns in a table
 *
 * @param  (string) The name of the table
 * @param  (array)  An array of column names
 * @param mixed $table
 * @param mixed $columns
 *
 * @return (array|bool) An array of column lengths on success or false if failed
 */
function db_get_columns_length($table, $columns) {
	$column_data = db_get_column_attributes($table, $columns);

	if (!empty($column_data)) {
		return array_rekey($column_data, 'COLUMN_NAME', 'CHARACTER_MAXIMUM_LENGTH');
	}

	return false;
}

/**
 * db_get_column_length - Get the length of a column in a table
 *
 * @param  (string) The name of the table
 * @param  (string) The name of the table column
 * @param mixed $table
 * @param mixed $column
 *
 * @return (int|bool) The length on success or false if failed
 */
function db_get_column_length($table, $column) {
	$column_data = db_get_columns_length($table, $column);

	if (!empty($column_data) && isset($column_data[$column])) {
		return $column_data[$column];
	}

	return false;
}

/**
 * db_check_password_length - Get the length of the password column in the
 *   user_auth table and adjust if the password length to 80 chars
 *
 * @return (void)
 */
function db_check_password_length() {
	$len = db_get_column_length('user_auth', 'password');

	if ($len === false) {
		die(__('Failed to determine password field length, can not continue as may corrupt password'));
	}

	if ($len < 80) {
		/* Ensure that the password length is increased before we start updating it */
		db_execute("ALTER TABLE user_auth MODIFY COLUMN password varchar(256) NOT NULL default ''");

		$len = db_get_column_length('user_auth','password');

		if ($len < 80) {
			die(__('Failed to alter password field length, can not continue as may corrupt password'));
		}
	}
}

/**
 * db_echo_sql - log the database call SQL to the systems tmpdir
 *
 * @param  (string) The SQL data to be executed
 * @param  (bool)   Not used
 * @param mixed $line
 * @param mixed $force
 *
 * @return (string) the last database error if any
 */
function db_echo_sql($line, $force = false) {
	global $config;

	file_put_contents(sys_get_temp_dir() . '/cacti-sql.log', get_debug_prefix() . $line, FILE_APPEND);
}

/**
 * db_error - return the last error from the database
 *
 * @return (string) the last database error if any
 */
function db_error() {
	global $database_last_error;

	return $database_last_error;
}

/**
 * db_get_default_database - Get the database name of the current database or
 *  return the default database name
 *
 * @param  (bool|resource) The connection name or false if one is not passed
 * @param mixed $db_conn
 *
 * @return (string) either current db name or  default database if no connection/name
 */
function db_get_default_database($db_conn = false) {
	global $database_default;

	$database = db_fetch_cell('SELECT DATABASE()', '', true, $db_conn);

	if (empty($database)) {
		$database = $database_default;
	}
}

/**
 * db_force_remote_cnn - alias for db_switch_remote_to_main()
 *
 * Switches the local database connection to the main server
 * This is required for CLI script that wish to talk to the main
 * database server since by default they are connected to the local
 * database server.
 *
 * @return (void)
 */
function db_force_remote_cnn() {
	return db_switch_remote_to_main();
}

/**
 * db_switch_remote_to_main - force the local connection to the main database connection
 *
 * This function needs to be used with caution.  It is for switching a database connection
 * from the remote connection or the main Cacti poller back to the local connection
 * for all db* calls that do not require the connection to be passed.  It's to be used
 * by CLI script, that by default connect to the local database, back and forth to the
 * remote or main database server.
 *
 * @returns (bool) If the switch was successful
 */
function db_switch_remote_to_main() {
	global $config, $database_sessions, $database_hostname, $database_port, $database_default;
	global $remote_db_cnn_id, $local_db_cnn_id;

	if ($config['poller_id'] > 1) {
		$database_sessions["$database_hostname:$database_port:$database_default"] = $remote_db_cnn_id;

		return true;
	}

	return false;
}

/**
 * db_switch_main_to_local - force the main cacti connection to the local poller
 *
 * This function needs to be used with caution.  It is for switching a database connection
 * from the remote connection or the main Cacti poller back to the local connection
 * for all db* calls that do not require the connection to be passed.  It's to be used
 * by CLI script, that by default connect to the local database, back and forth to the
 * remote or main database server.
 *
 * @returns (bool) If the switch was successful
 */
function db_switch_main_to_local() {
	global $config, $database_sessions, $database_hostname, $database_port, $database_default;
	global $remote_db_cnn_id, $local_db_cnn_id;

	if ($config['poller_id'] > 1) {
		$database_sessions["$database_hostname:$database_port:$database_default"] = $local_db_cnn_id;

		return true;
	}

	return false;
}

/**
 * db_dump_data - dump data into a file by mysqldump, minimize password be caught.
 *
 * @param  (string)     $database - default $database_default
 * @param  (string)     $tables - default all tables
 * @param  (array)      $credentials - array($name => value, ...) for user, password, host, port, ssl ...
 * @param  (sting|bool) $output_file - dump file name, default /tmp/cacti.dump.sql
 * @param  (string)     $options - option strings for mysqldump, if --defaults-extra-file set, dump the data directly
 *
 * @return (int) return status of the executed command
 */
function db_dump_data($database = '', $tables = '', $credentials = array(), $output_file = false, $options = '--extended-insert=FALSE') {
	global $database_default, $database_username, $database_password;
	$credentials_string = '';

	if ($database == '') {
		$database = $database_default;
	}

	if (cacti_sizeof($credentials)) {
		foreach ($credentials as $key => $value) {
			$name = trim($key);

			if (strstr($name, '--') !== false) {      //name like --host
				if ($name == '--password') {
					$password = $value;
				} elseif ($name == '--user') {
					$username = $value;
				} else {
					$credentials_string .= $name . '=' . $value . ' ';
				}
			} elseif (strstr($name, '-') !== false) { //name like -h
				if ($name == '-p') {
					$password = $value;
				} elseif ($name == '-u') {
					$username = $value;
				} else {
					$credentials_string .= $name . $value . ' ';
				}
			} else {                                  //name like host
				if ($name == 'password') {
					$password = $value;
				} elseif ($name == 'user') {
					$username = $value;
				} else {
					$credentials_string .= '--' . $name . '=' . $value . ' ';
				}
			}
		}
	}

	if (!isset($password)) {
		$password = $database_password;
	}

	if (!isset($username)) {
		$username = $database_username;
	}

	if (strstr($options, '--defaults-extra-file') !== false) {
		exec("mysqldump $options $credentials_string $database $tables > " . $output_file, $output, $retval);
	} else {
		exec("mysqldump $options $credentials_string " . $database . ' version >/dev/null 2>&1', $output, $retval);

		if ($retval) {
			exec("mysqldump $options $credentials_string -u" . $username . ' -p' . $password . ' ' . $database . " $tables > " . $output_file, $output, $retval);
		} else {
			exec("mysqldump $options $credentials_string $database $tables > " . $output_file, $output, $retval);
		}
	}

	return $retval;
}

function db_create_permissions_array($default = false) {
	return array(
		'ALTER'                   => $default,
		'ALTER ROUTINE'           => $default,
		'CREATE'                  => $default,
		'CREATE ROLE'             => $default,
		'CREATE ROUTINE'          => $default,
		'CREATE TABLESPACE'       => $default,
		'CREATE TEMPORARY TABLES' => $default,
		'CREATE USER'             => $default,
		'CREATE VIEW'             => $default,
		'DELETE'                  => $default,
		'DROP'                    => $default,
		'DROP ROLE'               => $default,
		'EVENT'                   => $default,
		'EXECUTE'                 => $default,
		'FILE'                    => $default,
		'GRANT OPTION'            => $default,
		'INDEX'                   => $default,
		'INSERT'                  => $default,
		'LOCK TABLES'             => $default,
		'PROCESS'                 => $default,
		'PROXY'                   => $default,
		'REFERENCES'              => $default,
		'RELOAD'                  => $default,
		'REPLICATION CLIENT'      => $default,
		'REPLICATION SLAVE'       => $default,
		'SELECT'                  => $default,
		'SHOW DATABASES'          => $default,
		'SHOW VIEW'               => $default,
		'SHUTDOWN'                => $default,
		'SUPER'                   => $default,
		'TRIGGER'                 => $default,
		'UPDATE'                  => $default,
		'USAGE'                   => $default,
	);
}

function db_get_grants($log = false, $db_conn = false) {
	$db_grants = db_fetch_assoc('SHOW GRANTS FOR CURRENT_USER', $log, $db_conn);

	return $db_grants;
}

function db_get_permissions($include_unknown = false, $log = false, $db_conn = false) {
	$perms = db_create_permissions_array(false);

	$db_name   = db_fetch_cell('SELECT DATABASE()', $log, $db_conn);
	$db_grants = db_fetch_assoc('SHOW GRANTS FOR CURRENT_USER', $log, $db_conn);

	if (cacti_sizeof($db_grants)) {
		foreach ($db_grants as $db_grants_user) {
			foreach ($db_grants_user as $db_grant) {
				// We are only interested in GRANT lines
				if (preg_match('/GRANT (.*) ON (.+)\.(.+) TO/i', $db_grant, $db_grant_match)) {
					// Replace any * used with .* for preg_match
					// Replace any % used with .* for preg_match
					$db_grant_regex = str_replace(array('*', '%'), array('.*', '.*'), $db_grant_match[2]);

					// See if we match the database name
					$db_regex_match = preg_match('/' . $db_grant_regex . '/', '`' . $db_name . '`');

					// Yes, we did
					if ($db_regex_match) {
						// Lets get all the permissions assigned.
						$db_grant_perms = preg_split('/,[ ]*/', $db_grant_match[1]);

						if (cacti_sizeof($db_grant_perms)) {
							foreach ($db_grant_perms as $db_grant_perm) {
								$db_grant_perm = strtoupper($db_grant_perm);

								if ($db_grant_perm == 'ALL' ||
									$db_grant_perm == 'ALL PRIVILEGES') {
									$perms = db_create_permissions_array(true);

									break 3;
								}

								if (array_key_exists($db_grant_perm, $perms)) {
									$perms[$db_grant_perm] = true;
								} elseif ($include_unknown) {
									$perms[$db_grant_perm.'*'] = true;
								}
							}
						}
					}
				}
			}
		}
	}

	return $perms;
}

function db_has_permissions($permissions, $log = false, $db_conn = false) {
	$perms = db_get_permissions(false, $log, $db_conn);

	if (!is_array($permissions)) {
		$permissions = array($permissions);
	}

	$result = true;

	foreach ($permissions as $permission) {
		if (empty($perms[$permission])) {
			$result = false;
		}
	}

	return $result;
}
