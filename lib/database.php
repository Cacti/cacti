<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
 * @param string $device                    The hostname of the database server, 'localhost'
 *                                          if the database server is running on this machine
 * @param string $user                      The username to connect to the database server as
 * @param string $pass                      The password to connect to the database server with
 * @param string $db_name                   The name of the database to connect to
 * @param string $db_type                   The type of database server.  Only 'mysql' is currently supported
 * @param int    $port                      The port to communicate with MySQL/MariaDB on
 * @param int    $retries                   The number a time the server should attempt to connect before failing
 * @param bool   $db_ssl                    A boolean true or false
 * @param string $db_ssl_key                String that points to the client ssl key file
 * @param string $db_ssl_cert               String that points to the client ssl cert file
 * @param string $db_ssl_ca                 String that points to the ssl ca file
 * @param string $db_ssl_capath             The path to the CA if required
 * @param bool   $db_ssl_verify_server_cert Set to true if you wish to validate the cert
 * @param bool   $persist                   Set to true if you wish to use a persistent connection
 *
 * @return bool|object connection object on success, false for error
 */
function db_connect_real(string $device, string $user, string $pass, string $db_name, string $db_type = 'mysql', int $port = 3306, int $retries = 20,
	bool $db_ssl = false, string $db_ssl_key = '', string $db_ssl_cert = '', string $db_ssl_ca = '', string $db_ssl_capath = '',
	bool $db_ssl_verify_server_cert = true, bool $persist = false) : mixed {
	global $database_sessions, $database_details, $database_total_queries, $database_persist, $config;

	$database_total_queries = 0;

	$i = 0;

	if (isset($database_sessions["$device:$port:$db_name"])) {
		if (!empty($config['DEBUG_SQL_CONNECT'])) {
			error_log(sprintf('NOTE: Connect using cached connection %s:%s/%s.', $device, $port, $db_name));
		}

		return $database_sessions["$device:$port:$db_name"];
	}

	$odevice = $device;

	$flags = [];

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
			} elseif ($db_ssl_capath != '') {
				if (is_dir($db_ssl_capath)) {
					$flags[PDO::MYSQL_ATTR_SSL_CAPATH] = $db_ssl_capath;
				}
			}

			if ($db_ssl_verify_server_cert) {
				$flags[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $db_ssl_verify_server_cert;
			}

			if ($db_ssl_key != '' && $db_ssl_cert != '') {
				if (file_exists($db_ssl_key) && file_exists($db_ssl_cert)) {
					$flags[PDO::MYSQL_ATTR_SSL_KEY]  = $db_ssl_key;
					$flags[PDO::MYSQL_ATTR_SSL_CERT] = $db_ssl_cert;
				}
			}
		}
	}

	// set connection timeout for down servers
	$flags[PDO::ATTR_TIMEOUT] = 2;

	while ($i <= $retries) {
		try {
			if (str_contains($device, '/') && filetype($device) == 'socket') {
				$cnn_id = new PDO("$db_type:unix_socket=$device;dbname=$db_name;charset=utf8", $user, $pass, $flags);
			} else {
				$cnn_id = new PDO("$db_type:host=$device;port=$port;dbname=$db_name;charset=utf8", $user, $pass, $flags);
			}

			if (!empty($config['DEBUG_SQL_CONNECT'])) {
				error_log(sprintf('NOTE: New connection to %s:%s/%s.', $device, $port, $db_name));
			}

			$bad_modes = [
				'STRICT_TRANS_TABLES',
				'STRICT_ALL_TABLES',
				'TRADITIONAL',
				'NO_ZERO_DATE',
				'NO_ZERO_IN_DATE',
				'ONLY_FULL_GROUP_BY',
				'NO_AUTO_VALUE_ON_ZERO'
			];

			$database_sessions["$odevice:$port:$db_name"] = $cnn_id;

			$object_hash = spl_object_hash($cnn_id);

			$database_details[$object_hash] = [
				'database_conn'                     => $cnn_id,
				'database_hostname'                 => $device,
				'database_username'                 => $user,
				'database_password'                 => $pass,
				'database_default'                  => $db_name,
				'database_type'                     => $db_type,
				'database_port'                     => $port,
				'database_retries'                  => $retries,
				'database_ssl'                      => $db_ssl,
				'database_ssl_key'                  => $db_ssl_key,
				'database_ssl_cert'                 => $db_ssl_cert,
				'database_ssl_ca'                   => $db_ssl_ca,
				'database_ssl_capath'               => $db_ssl_capath,
				'database_ssl_verify_server_cert'   => $db_ssl_verify_server_cert,
				'database_persist'                  => $persist,
			];

			// test if cacti database is imported from SQL file. Skip only under the
			// combined test bootstrap (PHP_TESTING + CACTI_TEST_BOOTSTRAP=1, local_only);
			// PHP_TESTING alone must not bypass schema validation in a deployed setup.
			$skip_schema_check = function_exists('cacti_is_test_bootstrap')
				? cacti_is_test_bootstrap()
				: (defined('PHP_TESTING') && getenv('CACTI_TEST_BOOTSTRAP', true) === '1');

			if (!$skip_schema_check) {
				$table_exists = db_fetch_cell("SELECT count(*) FROM information_schema.tables
					WHERE table_schema = DATABASE() and TABLE_NAME = 'version'");

				if (!$table_exists) {
					error_log(sprintf('NOTE: Database %s does not contain cacti tables. Import cacti.sql first', $db_name));
					die('ERROR: Database does not contain cacti tables. If this is a new installation, first import the SQL dump');
				}
			}

			$ver = db_get_global_variable('version', $cnn_id);

			if (str_contains($ver, 'MariaDB')) {
				$srv              = 'MariaDB';
				$ver              = str_replace('-MariaDB', '', $ver);
				$required_modes[] = 'NO_ENGINE_SUBSTITUTION';
			} else {
				$srv = 'MySQL';

				if (version_compare('8.0.0', $ver, '<=')) {
					$bad_modes[]      = 'NO_AUTO_CREATE_USER';
					$required_modes[] = 'NO_ENGINE_SUBSTITUTION';
				}

				if (version_compare('8.1.0', $ver, '<=')) {
					$bad_modes[] = 'NO_ENGINE_SUBSTITUTION';
				}
			}

			$database_details[$object_hash]['database_server']  = $srv;
			$database_details[$object_hash]['database_version'] = $ver;

			// Get rid of bad modes
			$modes     = explode(',', db_fetch_cell('SELECT @@sql_mode', '', false));
			$new_modes = [];

			foreach ($modes as $mode) {
				if (array_search($mode, $bad_modes, true) === false) {
					$new_modes[] = $mode;
				}
			}

			// Add Required modes
			$required_modes[] = 'ALLOW_INVALID_DATES';

			foreach ($required_modes as $mode) {
				if (array_search($mode, $new_modes, true) === false) {
					$new_modes[] = $mode;
				}
			}

			$sql_mode = implode(',', $new_modes);

			db_execute_prepared('SET SESSION sql_mode = ?', [$sql_mode], false);

			if (db_column_exists('poller', 'timezone')) {
				$timezone = db_fetch_cell_prepared('SELECT timezone
					FROM poller
					WHERE id = ?',
					[POLLER_ID], '', false);
			} else {
				$timezone = '';
			}

			if ($timezone != '') {
				db_execute_prepared('SET SESSION time_zone = ?', [$timezone], false);
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
				$config['DATABASE_ERROR'] = [];
			}

			$config['DATABASE_ERROR'][] = [
				'Code'  => $e->getCode(),
				'Error' => $e->getMessage(),
			];

			// Must catch this exception or else PDO will display an error with our username/password
			// print $e->getMessage();
			// exit;
		}

		$i++;
		usleep(40000);
	}

	return false;
}

/**
 * db_check_reconnect - Check the database connection.  If the connection is gone
 *  attempt to reconnect, otherwise return the connection
 *
 * @param mixed $db_conn The connection to check
 * @param bool  $log     Whether or not to log the connection check
 *
 * @return bool The database true is the database is connected else false
 */
function db_check_reconnect(mixed $db_conn = false, bool $log = true) : bool {
	global $database_details;
	global $database_hostname;
	global $database_username;
	global $database_password;
	global $database_default;
	global $database_type;
	global $database_port;
	global $database_retries;
	global $database_ssl;
	global $database_ssl_key;
	global $database_ssl_cert;
	global $database_ssl_ca;
	global $database_ssl_capath;
	global $database_ssl_verify_server_cert;

	if (file_exists(CACTI_PATH_INCLUDE . '/config.php')) {
		include(CACTI_PATH_INCLUDE . '/config.php');
	}

	if (cacti_sizeof($database_details) && $db_conn !== false) {
		foreach ($database_details as $det) {
			if (spl_object_hash($det['database_conn']) == spl_object_hash($db_conn)) {
				$database_hostname               = $det['database_hostname'];
				$database_username               = $det['database_username'];
				$database_password               = $det['database_password'];
				$database_default                = $det['database_default'];
				$database_type                   = $det['database_type'];
				$database_port                   = $det['database_port'];
				$database_retries                = $det['database_retries'];
				$database_ssl                    = $det['database_ssl'];
				$database_ssl_key                = $det['database_ssl_key'];
				$database_ssl_cert               = $det['database_ssl_cert'];
				$database_ssl_ca                 = $det['database_ssl_ca'];
				$database_ssl_capath             = $det['database_ssl_capath'];
				$database_ssl_verify_server_cert = $det['database_ssl_verify_server_cert'];

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

		if (!isset($database_ssl_capath)) {
			$database_ssl_capath   = '';
		}

		if (!isset($database_ssl_verify_server_cert)) {
			$database_ssl_verify_server_cert   = false;
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
		if ($log) {
			syslog(LOG_ALERT, 'CACTI: Database Connection went away.  Attempting to reconnect!');
		}

		db_close();

		// Connect to the database server
		$cnn_id = db_connect_real(
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
			$database_ssl_ca,
			$database_ssl_capath,
			$database_ssl_verify_server_cert
		);

		if ($cnn_id !== false) {
			return true;
		} else {
			return false;
		}
	} else {
		return true;
	}
}

function db_warning_handler(int $errno, string $errstr, string $errfile, int $errline, array $errcontext = []) : bool|null {
	throw new Exception($errstr, $errno);
}

/**
 * db_binlog_enabled - Checks to see if binary logging is enabled on the server
 *
 * @return bool true if enabled, else false
 */
function db_binlog_enabled() : bool {
	$enabled = db_fetch_row('SHOW GLOBAL VARIABLES LIKE "log_bin"');

	if (cacti_sizeof($enabled)) {
		if (cacti_strtolower($enabled['Value']) == 'on' || $enabled['Value'] == 1) {
			return true;
		}
	}

	return false;
}

/**
 * db_get_active_replicas - Returns the hostnames of all active replicas
 *
 * @return array The list of active replicas as an array of hostnames
 */
function db_get_active_replicas() : array {
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
 * @param mixed $db_conn Either the connection to use of false to use the default
 *
 * @return bool the result of the close command
 */
function db_close(mixed &$db_conn = false) : bool {
	global $config, $database_sessions, $error_logged, $database_default, $database_hostname, $database_port, $database_persist, $database_details;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (!empty($config['DEBUG_SQL_CONNECT'])) {
			error_log(sprintf('NOTE: Disconnecting from %s:%s/%s.', $database_hostname, $database_port, $database_default));
		}

		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

		if (!is_object($db_conn)) {
			if (!empty($config['DEBUG_SQL_CONNECT'])) {
				error_log(sprintf('WARNING: Disconnect issues.  Non-object for %s:%s/%s.', $database_hostname, $database_port, $database_default));
			}

			return false;
		}

		$database_sessions["$database_hostname:$database_port:$database_default"] = null;

		if (isset($error_logged["$database_hostname:$database_port:$database_default"])) {
			unset($error_logged["$database_hostname:$database_port:$database_default"]);
		}
	} elseif (!empty($config['DEBUG_SQL_CONNECT'])) {
		$id   = spl_object_id($db_conn);
		$hash = spl_object_hash($db_conn);

		if (isset($database_details[$hash])) {
			$det = $database_details[$hash];

			error_log(sprintf('NOTE: Disconnecting from %s:%s/%s.', $det['database_hostname'], $det['database_port'], $det['database_default']));
		} else {
			error_log("WARNING: Disconnecting from unregistered Object ID: $id.");
		}

		if (isset($error_logged[$id])) {
			unset($error_logged[$id]);
		}
	}

	// forcibly close connection if not persistent
	if (!$database_persist) {
		db_execute('KILL CONNECTION CONNECTIION_ID()', false, $db_conn);
	}

	// unset the variables which should do the same
	$db_conn = null;

	return true;
}

/**
 * db_sql_apply_timeout - wrap a statement so the server enforces a per-statement
 *   execution limit.  MariaDB (>= 10.1) uses 'SET STATEMENT MAX_STATEMENT_TIME='
 *   in seconds; MySQL (>= 5.7.8) uses the MAX_EXECUTION_TIME() optimizer hint in
 *   milliseconds and only on a top-level SELECT.  Anything that cannot honor the
 *   limit is returned unchanged so the caller can run it untimed.
 *
 * @param string $sql     The statement to wrap
 * @param float  $timeout The limit in seconds, decimals allowed; <= 0 is a no-op
 * @param string $server  The server family, 'MariaDB' or 'MySQL'
 * @param string $version The server version, e.g. '10.6.12'
 *
 * @return string The wrapped statement, or the original when no limit applies
 */
function db_sql_apply_timeout(string $sql, float $timeout, string $server, string $version) : string {
	if ($timeout <= 0) {
		return $sql;
	}

	if (!is_finite($timeout)) {
		return $sql;
	}

	if ($server === 'MariaDB') {
		if (version_compare($version, '10.1', '>=')) {
			// number_format forces a '.' decimal separator regardless of locale;
			// trim trailing zeros so a whole number reads '5', not '5.000'.
			// Clamp to 1ms floor: MAX_STATEMENT_TIME=0 means no limit in MariaDB,
			// so sub-millisecond values must not round down to zero.
			$seconds = rtrim(rtrim(number_format(max($timeout, 0.001), 3, '.', ''), '0'), '.');

			return 'SET STATEMENT MAX_STATEMENT_TIME=' . $seconds . ' FOR ' . $sql;
		}
	} elseif ($server === 'MySQL') {
		if (version_compare($version, '5.7.8', '>=') && preg_match('/^\s*SELECT\b/i', $sql) && !preg_match('/^\s*SELECT\s*\/\*\+/i', $sql)) {
			$ms = max(1, (int) round($timeout * 1000));

			return preg_replace('/^(\s*SELECT)\b/i', '$1 /*+ MAX_EXECUTION_TIME(' . $ms . ') */', $sql, 1);
		}
	}

	return $sql;
}

/**
 * db_execute - run an sql query and do not return any output
 *
 * @param string $sql     The SQL query to execute
 * @param bool   $log     Whether to log error messages, defaults to true
 * @param mixed  $db_conn The connection to use or false for the default
 * @param float  $timeout Server-side statement timeout in seconds, 0 disables
 *
 * @return mixed '1' for success, false on error
 */
function db_execute(string $sql, bool $log = true, mixed $db_conn = false, float $timeout = 0) : mixed {
	return db_execute_prepared($sql, [], $log, $db_conn, 'Exec', true, 'no_return_function', [], $timeout);
}

/**
 * db_execute_prepared - run an sql query and do not return any output
 *
 * @param string $sql           The SQL query to execute
 * @param array  $params        An array of values to be prepared into the SQL
 * @param bool   $log           Whether to log error messages, defaults to true
 * @param mixed  $db_conn       The connection to use or false for the default
 * @param string $execute_name  The database action/function to run
 * @param mixed  $default_value To Be Completed
 * @param string $return_func   To Be Completed
 * @param mixed  $return_params To Be Completed
 * @param float  $timeout       Server-side statement timeout in seconds, 0 disables
 *
 * @return mixed '1' for success, false for failed, or the return value of the return function
 */
function db_execute_prepared(string $sql, array $params = [], bool $log = true, mixed $db_conn = false, string $execute_name = 'Exec', mixed $default_value = true, string $return_func = 'no_return_function', mixed $return_params = [], float $timeout = 0) : mixed {
	global $database_sessions, $error_logged, $database_default, $config, $database_hostname, $database_port, $database_total_queries, $database_last_error, $database_log, $affected_rows, $database_details;

	$database_total_queries++;

	if (!isset($database_log)) {
		$database_log = false;
	}

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		} elseif (!isset($error_logged["$database_hostname:$database_port:$database_default"])) {
			if (!empty($config['DEBUG_SQL_CONNECT'])) {
				error_log(sprintf('WARNING: Execute unable to find connection for %s:%s/%s.', $database_hostname, $database_port, $database_default));
				$error_logged["$database_hostname:$database_port:$database_default"] = true;
			}
		}

		if (!is_object($db_conn)) {
			if (!empty($config['DEBUG_SQL_CONNECT'])) {
				error_log('FATAL: Unable to find connection Object ID.');
			}

			$database_last_error = 'DB ' . $execute_name . ' -- No connection found';

			return false;
		}
	} elseif (!empty($config['DEBUG_SQL_CONNECT'])) {
		$id   = spl_object_id($db_conn);
		$hash = spl_object_hash($db_conn);

		if (!isset($error_logged[$id])) {
			if (isset($database_details[$hash])) {
				$det = $database_details[$hash];

				error_log(sprintf('NOTE: Execute Using %s:%s/%s.', $det['database_hostname'], $det['database_port'], $det['database_default']));
			} else {
				error_log("WARNING: Execute Using Object ID: $id.");
			}

			$error_logged[$id] = true;
		}
	}

	$sql = db_strip_control_chars($sql);

	if ($timeout > 0) {
		$timeout_hash    = spl_object_hash($db_conn);
		$timeout_server  = $database_details[$timeout_hash]['database_server'] ?? '';
		$timeout_version = $database_details[$timeout_hash]['database_version'] ?? '';
		$timeout_sql     = db_sql_apply_timeout($sql, $timeout, $timeout_server, $timeout_version);

		if ($timeout_sql === $sql) {
			$timeout_secs = rtrim(rtrim(number_format($timeout, 3, '.', ''), '0'), '.');

			cacti_log(sprintf('DEBUG: SQL statement timeout of %s seconds not applied for %s %s (unsupported engine/version or non-SELECT)', $timeout_secs, $timeout_server, $timeout_version), false, 'DBCALL', POLLER_VERBOSITY_DEBUG);
		} else {
			$sql = $timeout_sql;
		}
	}

	if (!empty($config['DEBUG_SQL_CMD'])) {
		db_echo_sql('db_' . $execute_name . ': "' . $sql . "\"\n");
	}

	$errors = 0;

	$affected_rows[spl_object_hash($db_conn)] = 0;

	while (true) {
		$code  = 0;
		$en    = '';
		$query = $db_conn->prepare($sql);

		if (!empty($config['DEBUG_SQL_CMD'])) {
			db_echo_sql('db_' . $execute_name . ' Memory [Before]: ' . memory_get_usage() . ' / ' . memory_get_peak_usage() . "\n");
		}

		set_error_handler('db_warning_handler', E_WARNING | E_NOTICE);

		try {
			if (empty($params) || cacti_count($params) == 0) {
				$query->execute();
			} else {
				$query->execute($params);
			}
		} catch (Exception $ex) {
			$code      = $ex->getCode();
			$en        = $code;
			$errorinfo = [1 => $code, 2 => $ex->getMessage()];
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
				$return_array = [$query];

				if (!empty($return_params)) {
					if (!is_array($return_params)) {
						$return_params = [$return_params];
					}

					$return_array = array_merge($return_array, $return_params);
				}

				if (!empty($config['DEBUG_SQL_FLOW'])) {
					db_echo_sql('db_' . $execute_name . '_return_func: \'' . $return_func . '\' (' . $return_func . ")\n");
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
			$database_last_error = 'DB ' . $execute_name . ' Failed!, Error ' . $en . ': ' . ($errorinfo[2] ?? '<no error>');

			if (isset($query)) {
				$query->closeCursor();
			}

			unset($query);

			if ($en == 2002 || $en == 2006) {
				$log = false;
			}

			if ($en == 1213 || $en == 1205 || $en == 1020) {
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

				if ($log) {
					cacti_log('ERROR: A DB ' . $execute_name . ' Too Large!, Error: ' . $en . ', SQL: \'' . clean_up_lines($sql) . '\'', false, 'DBCALL', POLLER_VERBOSITY_DEBUG);
					cacti_log('ERROR: A DB ' . $execute_name . ' Too Large!, Error: ' . ($errorinfo[2] ?? '<no error>'), false, 'DBCALL', POLLER_VERBOSITY_DEBUG);
					cacti_debug_backtrace('SQL', false, true, 0, 1);

					$database_last_error = 'DB ' . $execute_name . ' Too Large!, Error ' . $en . ': ' . ($errorinfo[2] ?? '<no error>');
				}
			} elseif ($en == 2002 || $en == 2006) {
				$errors++;

				syslog(LOG_WARNING, 'WARNING: The Cacti Database has gone away during a query.  Attempting to re-connect and query in 5 seconds.');

				sleep(5);

				if (db_check_reconnect($db_conn)) {
					if ($errors < 5) {
						continue;
					}
				}
			} elseif ($log) {
				cacti_log('ERROR: A DB ' . $execute_name . ' Failed!, Error: ' . $en . ', SQL: \'' . clean_up_lines($sql) . '\'', false, 'DBCALL', POLLER_VERBOSITY_DEBUG);
				cacti_log('ERROR: A DB ' . $execute_name . ' Failed!, Error: ' . ($errorinfo[2] ?? '<no error>'), false);
				cacti_debug_backtrace('SQL', false, true, 0, 1);

				$database_last_error = 'DB ' . $execute_name . ' Failed!, Error ' . $en . ': ' . ($errorinfo[2] ?? '<no error>');
			}

			if (!empty($config['DEBUG_SQL_FLOW'])) {
				db_echo_sql($database_last_error);
			}

			return false;
		}
	}
}

/**
 * db_fetch_cell - run a 'select' sql query and return the first column of the
 *   first row found
 *
 * @param string $sql      The SQL query to execute
 * @param string $col_name Use this column name instead of the first one
 * @param bool   $log      Whether to log error messages, defaults to true
 * @param mixed  $db_conn  The connection to use or false to use the default
 * @param float  $timeout  Server-side statement timeout in seconds, 0 disables
 *
 * @return mixed The output of the sql query as a single variable
 */
function db_fetch_cell(string $sql, string $col_name = '', bool $log = true, mixed $db_conn = false, float $timeout = 0) : mixed {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_cell($sql, $col_name = \'' . $col_name . '\', $log = true, $db_conn = false)' . "\n");
	}

	return db_fetch_cell_prepared($sql, [], $col_name, $log, $db_conn, $timeout);
}

/**
 * db_fetch_cell_prepared - run a 'select' sql query and return the first column of the
 *   first row found
 *
 * @param string $sql      The SQL query to execute
 * @param array  $params   An array of values to be prepared into the SQL
 * @param string $col_name Use this column name instead of the first one
 * @param bool   $log      Whether to log error messages, defaults to true
 * @param mixed  $db_conn  The connection to use or false to use the default
 * @param float  $timeout  Server-side statement timeout in seconds, 0 disables
 *
 * @return mixed output of the sql query as a single variable
 */
function db_fetch_cell_prepared(string $sql, array $params = [], string $col_name = '', bool $log = true, mixed $db_conn = false, float $timeout = 0) : mixed {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_cell_prepared($sql, $params = ' . clean_up_lines(var_export($params, true)) . ', $col_name = \'' . $col_name . '\', $log = true, $db_conn = false)' . "\n");
	}

	return db_execute_prepared($sql, $params, $log, $db_conn, 'Cell', false, 'db_fetch_cell_return', $col_name, $timeout);
}

/**
 * db_fetch_cell_return - Function to process and return data from the
 * db_fetch_cell function
 *
 * @param PDOStatement $query    The SQL query to run
 * @param string       $col_name The column to return if the query is more row or associative
 *                               in the case of associated, returns the column from the first row.
 *
 * @return mixed The value of the column or false if failed
 */
function db_fetch_cell_return(PDOStatement $query, string $col_name = '') : mixed {
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
 * @param string $sql     The SQL query to execute
 * @param bool   $log     Whether to log error messages, defaults to true
 * @param mixed  $db_conn The connection to use or false to use the default
 * @param float  $timeout Server-side statement timeout in seconds, 0 disables
 *
 * @return bool|array The first row of the result or false if failed
 */
function db_fetch_row(string $sql, bool $log = true, mixed $db_conn = false, float $timeout = 0) : bool|array {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_row(\'' . clean_up_lines($sql) . '\', $log = ' . $log . ', $db_conn = ' . ($db_conn ? 'true' : 'false') . ')' . "\n");
	}

	return db_fetch_row_prepared($sql, [], $log, $db_conn, $timeout);
}

/**
 * db_fetch_row_prepared - run a 'select' sql query and return the first row found
 *
 * @param string $sql     The SQL query to execute
 * @param array  $params  An array of values to be prepared into the SQL
 * @param bool   $log     Whether to log error messages, defaults to true
 * @param mixed  $db_conn The connection to use or false to use the default
 * @param float  $timeout Server-side statement timeout in seconds, 0 disables
 *
 * @return bool|array The first row of the result or false if failed
 */
function db_fetch_row_prepared(string $sql, array $params = [], bool $log = true, mixed $db_conn = false, float $timeout = 0) : bool|array {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_row_prepared(\'' . clean_up_lines($sql) . '\', $params = (\'' . implode('\', \'', $params) . '\'), $log = ' . $log . ', $db_conn = ' . ($db_conn ? 'true' : 'false') . ')' . "\n");
	}

	return db_execute_prepared($sql, $params, $log, $db_conn, 'Row', false, 'db_fetch_row_return', [], $timeout);
}

/**
 * db_fetch_row_return - Function to execute and process the results for the
 * db_fetch_row_prepared() function.
 *
 * @param PDOStatement $query The prepared Query
 *
 * @return array The row, or false on failure
 */
function db_fetch_row_return(PDOStatement $query) : array {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_row_return($query)' . "\n");
	}

	if ($query->rowCount()) {
		$r = $query->fetchAll(PDO::FETCH_ASSOC);
	}

	return $r[0] ?? [];
}

/**
 * db_fetch_assoc - run a 'select' sql query and return all rows found
 *
 * @param string $sql     The SQL query to execute
 * @param bool   $log     Whether to log error messages, defaults to true
 * @param mixed  $db_conn The connection to use or false to use the default
 * @param float  $timeout Server-side statement timeout in seconds, 0 disables
 *
 * @return bool|array The entire result set or false on error
 */
function db_fetch_assoc(string $sql, bool $log = true, mixed $db_conn = false, float $timeout = 0) : mixed {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_assoc($sql, $log = true, $db_conn = false)' . "\n");
	}

	return db_fetch_assoc_prepared($sql, [], $log, $db_conn, $timeout);
}

/**
 * db_fetch_assoc_prepared - run a 'select' sql query and return all rows found
 *
 * @param string $sql     The sql query to execute
 * @param array  $params  An array of values to be prepared into the SQL
 * @param bool   $log     Whether to log error messages, defaults to true
 * @param mixed  $db_conn The connection to use or false to use the default
 * @param float  $timeout Server-side statement timeout in seconds, 0 disables
 *
 * @return mixed The entire result or false on error
 */
function db_fetch_assoc_prepared(string $sql, array $params = [], bool $log = true, mixed $db_conn = false, float $timeout = 0) : mixed {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_assoc_prepared($sql, $params = array(), $log = true, $db_conn = false)' . "\n");
	}

	return db_execute_prepared($sql, $params, $log, $db_conn, 'Row', [], 'db_fetch_assoc_return', [], $timeout);
}

/**
 * db_fetch_assoc_return - Function to execute and process the results for the
 *   db_fetch_assoc_prepared() function.
 *
 * @param PDOStatement $query The prepared Query
 *
 * @return array The associated array of data, or false on failure
 */
function db_fetch_assoc_return(PDOStatement $query) : array {
	global $config;

	if (!empty($config['DEBUG_SQL_FLOW'])) {
		db_echo_sql('db_fetch_assoc_return($query)' . "\n");
	}

	$r = $query->fetchAll(PDO::FETCH_ASSOC);

	return (cacti_sizeof($r)) ? $r : [];
}

/**
 * db_fetch_insert_id - get the last insert_id or auto increment
 *
 * @param mixed $db_conn The connection to use or false to use the default
 *
 * @return mixed The id of the last auto increment row or false on error
 */
function db_fetch_insert_id(mixed $db_conn = false) : mixed {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}
	}

	if (is_object($db_conn)) {
		return $db_conn->lastInsertId();
	}

	return false;
}

/**
 * db_affected_rows - return the number of rows affected by the last transaction
 *
 * @param mixed $db_conn The connection to use or false to use the default
 *
 * @return mixed The number of rows affected by the last transaction, or false on error
 */
function db_affected_rows(mixed $db_conn = false) : mixed {
	global $database_sessions, $database_default, $database_hostname, $database_port, $affected_rows;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

		if (!is_object($db_conn)) {
			return false;
		}
	}

	return $affected_rows[spl_object_hash($db_conn)];
}

/**
 * db_add_column - add a column to table
 *
 * @param string $table   The name of the table
 * @param array  $column  Array of column data ex: array('name' => 'test' .
 *                        rand(1, 200), 'type' => 'varchar (255)', 'NULL' => false)
 * @param bool   $log     Whether to log error messages, defaults to true
 * @param mixed  $db_conn The connection to use or false to use the default
 *
 * @return mixed '1' for success, false for error
 */
function db_add_column(string $table, array $column, bool $log = true, mixed $db_conn = false) : mixed {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$result = db_fetch_assoc('SHOW columns FROM `' . $table . '`', $log, $db_conn);

	if ($result === false) {
		return false;
	}

	$columns = [];

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
				if (in_array(cacti_strtolower($column['type']), ['timestamp', 'datetime', 'date'], true) && str_contains($column['default'], 'CURRENT_TIMESTAMP')) {
					$sql .= ' default ' . $column['default'];
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
			} elseif (isset($column['first'])) {
				$sql .= ' FIRST';
			}

			return db_execute($sql, $log, $db_conn);
		}

		return true;
	}

	return false;
}

/**
 * db_change_column - update a column to table
 *
 * @param string $table   the name of the table
 * @param array  $column  array of column data ex: array('old_name' => 'test', 'name' => 'newtest' . rand(1, 200), 'type' => 'varchar (255)', 'NULL' => false)
 * @param bool   $log     whether to log error messages, defaults to true
 * @param mixed  $db_conn
 *
 * @return bool '1' for success, '0' for error
 */
function db_change_column(string $table, array $column, bool $log = true, mixed $db_conn = false) : bool {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$result = db_fetch_assoc('SHOW columns FROM `' . $table . '`', $log, $db_conn);

	if ($result === false) {
		return false;
	}

	$columns = [];

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
					if (cacti_strtolower($column['type']) == 'timestamp' && $column['default'] === 'CURRENT_TIMESTAMP') {
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
 * @param string $table   The name of the table
 * @param string $column  The name of the column
 * @param bool   $log     Whether to log error messages, defaults to true
 * @param mixed  $db_conn The connection to use or false to use the default
 *
 * @return mixed '1' for success, false for error
 */
function db_remove_column(string $table, string $column, bool $log = true, mixed $db_conn = false) : mixed {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$result  = db_fetch_assoc('SHOW columns FROM `' . $table . '`', $log, $db_conn);
	$columns = [];

	foreach ($result as $arr) {
		$columns[] = $arr['Field'];
	}

	if (in_array($column, $columns, true)) {
		$sql = 'ALTER TABLE `' . $table . '` DROP `' . $column . '`';

		return db_execute($sql, $log, $db_conn);
	}

	return true;
}

/**
 * db_add_index - adds a new index to a table
 *
 * @param string $table   The name of the table
 * @param string $type    The type of the index
 * @param string $key     The name of the index
 * @param array  $columns An array that defines the columns to include in the index
 * @param bool   $log     Whether to log error messages, defaults to true
 * @param mixed  $db_conn The connection to use or false to use the default
 *
 * @return bool The result of the operation true or false
 */
function db_add_index(string $table, string $type, string $key, array $columns, bool $log = true, mixed $db_conn = false) : bool {
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
 * @param string $table   The name of the table
 * @param string $index   The name of the index
 * @param bool   $log     Whether to log error messages, defaults to true
 * @param mixed  $db_conn The connection to use or false to use the default
 *
 * @return bool The output of the sql query as a single variable
 */
function db_index_exists(string $table, string $index, bool $log = true, mixed $db_conn = false) : bool {
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
		db_echo_sql('db_index_exists(\'' . $table . '\', \'' . $index . '\'): '
			. in_array($index, $_keys, true) . ' - '
			. clean_up_lines(var_export($_keys, true)));
	}

	return in_array($index, $_keys, true);
}

/**
 * db_index_exists - checks whether an index exists
 *
 * @param string $table   The name of the table
 * @param string $index   The name of the index
 * @param array  $columns The columns of the index that should match
 * @param bool   $log     Whether to log error messages, defaults to true
 * @param mixed  $db_conn The connection to use or false to use the default
 *
 * @return int The output of the sql query as a single variable
 */
function db_index_matches(string $table, string $index, array $columns, bool $log = true, mixed $db_conn = false) : int {
	global $database_log, $config;

	if (!isset($database_log)) {
		$database_log = false;
	}

	$_log         = $database_log;
	$database_log = false;

	$_data = db_fetch_assoc("SHOW KEYS FROM `$table`", $log, $db_conn);
	$_cols = [];

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
		db_echo_sql('db_index_matches(\'' . $table . '\', \'' . $index . '\'): '
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
 * @param string $table   The name of the table
 * @param bool   $log     Whether to log error messages, defaults to true
 * @param mixed  $db_conn The connection to use or false to use the default
 *
 * @return bool The output of the sql query as a single variable
 */
function db_table_exists(string $table, bool $log = true, mixed $db_conn = false) : bool {
	static $results;

	if ($db_conn === false) {
		$index = '-1';
	} else {
		$index = md5(json_encode($db_conn));
	}

	if (isset($results[$index][$table]) && !defined('IN_CACTI_INSTALL') && !defined('IN_PLUGIN_INSTALL')) {
		return $results[$index][$table];
	}

	// Separate the database from the table and remove backticks
	preg_match("/([`]{0,1}(?<database>[\w_]+)[`]{0,1}\.){0,1}[`]{0,1}(?<table>[\w_]+)[`]{0,1}/", $table, $matches);

	if (cacti_sizeof($matches) && array_key_exists('table', $matches)) {
		$sql = 'SHOW TABLES LIKE \'' . $matches['table'] . '\'';

		$results[$index][$table] = (db_fetch_cell($sql, '', $log, $db_conn) ? true : false);

		return $results[$index][$table];
	}

	return false;
}

/**
 * db_cacti_initialized - checks whether cacti has been initialized properly and if not exits with a message
 *
 * @param bool $is_web Is the session a web session.
 *
 * @return bool true if the database is initialized else false
 */
function db_cacti_initialized(bool $is_web = true) : bool {
	global $database_sessions, $database_default, $config, $database_hostname, $database_port, $config;

	if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
		$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
	} else {
		$db_conn = false;
	}

	if (!is_object($db_conn)) {
		return false;
	}

	try {
		$query = $db_conn->prepare('SELECT cacti FROM version');
		$query->execute();
		$errorinfo = $query->errorInfo();
		$query->closeCursor();
	} catch (PDOException $e) {
		$errorinfo = [0 => $e->getCode(), 1 => $e->getCode(), 2 => $e->getMessage()];
	}

	if ($errorinfo[1] != 0) {
		print($is_web ? '<head><link href="' . CACTI_PATH_URL . 'include/themes/modern/main.css" type="text/css" rel="stylesheet"></head>' : '');
		print($is_web ? '<table style="height:40px;"><tr><td></td></tr></table>' : '');
		print($is_web ? '<table style="margin-left:auto;margin-right:auto;width:80%;border:1px solid rgba(98,125,77,1)" class="cactiTable"><tr class="cactiTableTitle"><td style="color:snow;font-weight:bold;">Fatal Error - Cacti Database Not Initialized</td></tr>' : '');
		print($is_web ? '<tr class="installArea"><td>' : '');
		print ($is_web ? '<p>' : '') . 'The Cacti Database has not been initialized.  Please initialize it before continuing.' . ($is_web ? '</p>' : "\n");
		print ($is_web ? '<p>' : '') . 'To initialize the Cacti database, issue the following commands either as root or using a valid account.' . ($is_web ? '</p>' : "\n");
		print ($is_web ? '<p style="font-weight:bold;padding-left:25px;">' : '') . '  mysqladmin -uroot -p create cacti' . ($is_web ? '</p>' : "\n");
		print ($is_web ? '<p style="font-weight:bold;padding-left:25px;">' : '') . '  mysql -uroot -p -e "grant all on cacti.* to \'someuser\'@\'localhost\' identified by \'somepassword\'"' . ($is_web ? '</p>' : "\n");
		print ($is_web ? '<p style="font-weight:bold;padding-left:25px;">' : '') . '  mysql -uroot -p -e "grant select on mysql.time_zone_name to \'someuser\'@\'localhost\' identified by \'somepassword\'"' . ($is_web ? '</p>' : "\n");
		print ($is_web ? '<p style="font-weight:bold;padding-left:25px;">' : '') . '  mysql -uroot -p cacti < /pathcacti/cacti.sql' . ($is_web ? '</p>' : "\n");
		print ($is_web ? '<p>' : '') . 'Where <b>/pathcacti/</b> is the path to your Cacti install location.' . ($is_web ? '</p>' : "\n");
		print ($is_web ? '<p>' : '') . 'Change <b>someuser</b> and <b>somepassword</b> to match your site preferences.  The defaults are <b>cactiuser</b> for both user and password.' . ($is_web ? '</p>' : "\n");
		print ($is_web ? '<p>' : '') . '<b>NOTE:</b> When installing a remote poller, the <b>config.php</b> file must be writable by the Web Server account, and must include valid connection information to the main Cacti server.  The file should be changed to read only after the install is completed.' . ($is_web ? '</p>' : "\n");
		print($is_web ? '</td></tr></table>' : '');

		exit;
	}

	return true;
}

/**
 * db_column_exists - checks whether a column exists
 *
 * @param string $table   The name of the table
 * @param string $column  The name of the column
 * @param bool   $log     Whether to log error messages, defaults to true
 * @param mixed  $db_conn The connection to use or false to use the default
 *
 * @return bool The output of the sql query as a single variable
 */
function db_column_exists(string $table, string $column, bool $log = true, mixed $db_conn = false) : bool {
	static $results = [];

	if ($db_conn === false) {
		$index = '-1';
	} else {
		$index = md5(json_encode($db_conn));
	}

	if (isset($results[$index][$table][$column]) && !defined('IN_CACTI_INSTALL') && !defined('IN_PLUGIN_INSTALL')) {
		return $results[$index][$table][$column];
	}

	$results[$index][$table][$column] = (db_fetch_cell("SHOW columns FROM `$table` LIKE '$column'", '', $log, $db_conn) ? true : false);

	return $results[$index][$table][$column];
}

/**
 * db_get_table_column_types - returns all the types for each column of a table
 *
 * @param string $table   The name of the table
 * @param mixed  $db_conn The connection to use or false to use the default
 *
 * @return mixed An array of column types indexed by the column names or false if failed
 */
function db_get_table_column_types(string $table, mixed $db_conn = false) : mixed {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

		if (!is_object($db_conn)) {
			return false;
		}
	}

	$columns = db_fetch_assoc("SHOW COLUMNS FROM $table", false, $db_conn);
	$cols    = [];

	if (cacti_sizeof($columns)) {
		foreach ($columns as $col) {
			$cols[$col['Field']] = ['type' => $col['Type'], 'null' => $col['Null'], 'default' => $col['Default'], 'extra' => $col['Extra']];
		}
	}

	return $cols;
}

/**
 * db_update_table - a function that will update the table structure based upon
 * a Cacti specific array specification constructed by the sqltable_to_php.php
 * script.  That script will construct an array from the table definition.
 * The script is very handy for both Cacti table construction and for plugins.
 *
 * @param string $table         The name of the table
 * @param array  $data          Table definition as a Cacti specific array
 * @param bool   $removecolumns Remove any existing columns that are not in the specification
 * @param bool   $log           Whether to log error messages, defaults to true
 * @param mixed  $db_conn       The connection to use or false to use the default
 *
 * @return mixed An array of column types indexed by the column names or false on error
 */
function db_update_table(string $table, array $data, bool $removecolumns = false, bool $log = true, mixed $db_conn = false) : mixed {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

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

	if (isset($info['ENGINE']) && isset($data['type']) && cacti_strtolower($info['ENGINE']) != cacti_strtolower($data['type'])) {
		if (!db_execute("ALTER TABLE `$table` ENGINE = " . $data['type'], $log, $db_conn)) {
			return false;
		}
	}

	if (isset($data['row_format']) && cacti_strtolower(db_get_global_variable('innodb_file_format', $db_conn)) == 'barracuda') {
		db_execute("ALTER TABLE `$table` ROW_FORMAT = " . $data['row_format'], $log, $db_conn);
	}

	$allcolumns  = [];
	$prev_column = false;

	foreach ($data['columns'] as $column) {
		$allcolumns[] = $column['name'];

		if (!db_column_exists($table, $column['name'], $log, $db_conn)) {
			if ($prev_column !== false) {
				$column['after'] = $prev_column;
			} else {
				$column['first'] = true;
			}

			if (!db_add_column($table, $column, $log, $db_conn)) {
				return false;
			}
		} else {
			// Check that column is correct and fix it
			// FIXME: Need to still check default value
			$arr = db_fetch_row("SHOW columns FROM `$table` LIKE '" . $column['name'] . "'", $log, $db_conn);

			if (str_contains(cacti_strtolower($arr['Type']), ' unsigned')) {
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
					if (cacti_strtolower($column['type']) == 'timestamp' && $column['default'] === 'CURRENT_TIMESTAMP') {
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

		$prev_column = $column['name'];
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
	$allindexes = [];

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
							!db_execute("ALTER TABLE `$table` ADD" . (isset($k['unique']) ? ' UNIQUE' : '') . " INDEX `$n` (" . $k['name'] . '` (' . db_format_index_create($k['columns']) . ')', $log, $db_conn)) {
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
				if (!db_execute("ALTER TABLE `$table` ADD" . (isset($k['unique']) ? ' UNIQUE' : '') . ' INDEX `' . $k['name'] . '` (' . db_format_index_create($k['columns']) . ')', $log, $db_conn)) {
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
			if (!is_array($data['primary'])) {
				$data['primary'] = [$data['primary']];
			}

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
 * that is compatible with the cacti database table creation array.
 *
 * @param mixed $indexes An array of indexes to process
 *
 * @return string A list of preprocessed indexes into a form
 *                compatible with the array definition
 */
function db_format_index_create(mixed $indexes) : string {
	if (is_array($indexes)) {
		$outindex = '';

		foreach ($indexes as $index) {
			$index = trim($index);

			if (str_ends_with($index, ')')) {
				$outindex .= ($outindex != '' ? ',' : '') . $index;
			} else {
				$outindex .= ($outindex != '' ? ',' : '') . '`' . $index . '`';
			}
		}

		return $outindex;
	} else {
		$indexes = trim($indexes);

		if (str_ends_with($indexes, ')')) {
			return $indexes;
		} else {
			return '`' . trim($indexes, ' `') . '`';
		}
	}
}

/**
 * db_table_create - checks whether a table exists
 *
 * @param string $table   The name of the table
 * @param array  $data    The table creation array as defined by sqltable_to_php.php script
 * @param bool   $log     Whether to log error messages, defaults to true
 * @param mixed  $db_conn The connection to use or false to use the default
 *
 * @return bool The output of the sql query as a single variable
 */
function db_table_create(string $table, array $data, bool $log = true, mixed $db_conn = false) : bool {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

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
					if (cacti_strtolower($column['type']) == 'timestamp' && $column['default'] === 'CURRENT_TIMESTAMP') {
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
				$sql .= ",\n PRIMARY KEY (`" . implode('`,`', $data['primary']) . '`)';
			} else {
				$sql .= ",\n PRIMARY KEY (`" . $data['primary'] . '`)';
			}
		}

		if (isset($data['keys']) && cacti_sizeof($data['keys'])) {
			foreach ($data['keys'] as $key) {
				if (isset($key['name'])) {
					if (is_array($key['columns'])) {
						$sql .= ",\n " . (isset($key['unique']) ? ' UNIQUE' : '') . ' INDEX `' . $key['name'] . '` (`' . implode('`,`', $key['columns']) . '`)';
					} else {
						$sql .= ",\n " . (isset($key['unique']) ? ' UNIQUE' : '') . ' INDEX `' . $key['name'] . '` (`' . $key['columns'] . '`)';
					}
				}
			}
		}
		$sql .= ') ENGINE = ' . $data['type'];

		if (isset($data['comment'])) {
			$sql .= " COMMENT = '" . $data['comment'] . "'";
		}

		if (isset($data['row_format']) && cacti_strtolower(db_get_global_variable('innodb_file_format', $db_conn)) == 'barracuda') {
			$sql .= ' ROW_FORMAT = ' . $data['row_format'];
		}

		if (db_execute($sql, $log, $db_conn)) {
			if (isset($data['charset'])) {
				db_execute("ALTER TABLE `$table` CHARSET = " . $data['charset']);
			}

			if (isset($data['collate'])) {
				db_execute("ALTER TABLE `$table` COLLATE = " . $data['collate']);
			}

			return true;
		} else {
			return false;
		}
	}

	return true;
}

/**
 * db_get_global_variable - get the value of a global variable
 *
 * @param string $variable The GLOBAL variable to obtain
 * @param mixed  $db_conn  The connection to use or false to use the default
 *
 * @return mixed The value of the variable if found or false if failed to locate
 */
function db_get_global_variable(string $variable, mixed $db_conn = false) : mixed {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

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
 * @param string $variable The variable to obtain
 * @param mixed  $db_conn  The connection to use or false to use the default
 *
 * @return mixed The value of the variable if found or false if failed to locate
 */
function db_get_session_variable(string $variable, mixed $db_conn = false) : mixed {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

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
 * @param mixed $db_conn The connection to use or false to use the default
 *
 * @return bool If the begin transaction was successful
 */
function db_begin_transaction(mixed $db_conn = false) : bool {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

		if (!is_object($db_conn)) {
			return false;
		}
	}

	return $db_conn->beginTransaction();
}

/**
 * db_commit_transaction - commit a transaction
 *
 * @param mixed $db_conn The connection to use or false to use the default
 *
 * @return bool If the commit transaction was successful
 */
function db_commit_transaction(mixed $db_conn = false) : bool {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

		if (!is_object($db_conn)) {
			return false;
		}
	}

	if (db_fetch_cell('SELECT @@in_transaction') > 0) {
		return $db_conn->commit();
	} else {
		return false;
	}
}

/**
 * db_rollback_transaction - rollback a transaction
 *
 * @param mixed $db_conn The connection to use or false to use the default
 *
 * @return bool If the rollback transaction was successful
 */
function db_rollback_transaction(mixed $db_conn = false) : bool {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

		if (!is_object($db_conn)) {
			return false;
		}
	}

	return $db_conn->rollBack();
}

/**
 * array_to_sql_or - loops through a single dimensional array and converts each
 * item to a string that can be used in the OR portion of an sql query in the
 * following form:
 *
 * column=item1 OR column=item2 OR column=item2 ...
 *
 * @param array  $array      The array to convert
 * @param string $sql_column The column to set each item in the array equal to
 *
 * @return mixed A string that can be placed in a SQL OR statement or null
 */
function array_to_sql_or(array $array, string $sql_column) : mixed {
	// if the last item is null; pop it off
	if (end($array) === null) {
		array_pop($array);
	}

	if (cacti_sizeof($array)) {
		$sql_or = '(' . $sql_column . ' IN(' . implode(',', array_map('db_qstr', $array)) . '))';

		return $sql_or;
	}

	return '';
}

/**
 * db_in_clause - build a safe "column IN (...)" predicate from caller-supplied
 * values. Numeric mode keeps only numeric elements and casts them with
 * intval(), so injected text such as '1) UNION SELECT' or 'abc' is dropped
 * rather than coerced to a real id. String mode quotes each element with
 * db_qstr(). An empty list yields "column IN (NULL)" so the predicate matches
 * nothing rather than producing invalid SQL.
 *
 * @param string $sql_column The column to test
 * @param mixed  $values     An array, or a comma-separated string, of values
 * @param bool   $numeric    Treat values as integer ids (default) or strings
 *
 * @return string A parenthesised IN() predicate
 */
function db_in_clause(string $sql_column, mixed $values, bool $numeric = true) : string {
	if (!is_array($values)) {
		$values = ($values === '' || $values === null) ? [] : explode(',', (string) $values);
	}

	$values = array_filter($values, fn ($v) => $v !== '' && $v !== null);

	if ($numeric) {
		// drop non-numeric garbage instead of letting intval() coerce it to 0,
		// which is a meaningful id in the graph_template_id codepaths
		$values = array_filter(array_map('trim', $values), 'is_numeric');
		$list   = array_values(array_unique(array_map('intval', $values)));
	} else {
		$list = array_map('db_qstr', $values);
	}

	if (cacti_sizeof($list) == 0) {
		return '(' . $sql_column . ' IN (NULL))';
	}

	return '(' . $sql_column . ' IN (' . implode(',', $list) . '))';
}

/**
 * db_replace - replaces the data contained in a particular row
 *
 * @param string $table_name  The name of the table to make the replacement in
 * @param array  $array_items An array containing each column -> value mapping in the row
 * @param string $keyCols     A string or array of primary keys
 * @param mixed  $db_conn     db connection object of false
 *
 * @return int The auto increment id column (if applicable)
 */
function db_replace(string $table_name, array $array_items, string $keyCols, mixed $db_conn = false) : int {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}
	}

	cacti_log("DEVEL: SQL Replace on table '$table_name': '" . serialize($array_items) . "'", false, 'DBCALL', POLLER_VERBOSITY_DEVDBG);

	_db_replace($db_conn, $table_name, $array_items, $keyCols);

	return db_fetch_insert_id($db_conn);
}

/**
 * _db_replace - Internal function used as a part of the db_replace public function
 *
 * @param mixed  $db_conn    The database connection to use
 * @param string $table      The table name to use
 * @param array  $fieldArray An array of field values
 * @param mixed  $keyCols    A string of a key column or an array of key columns
 *
 * @return mixed Either the insert id of the replace of false on error
 */
function _db_replace(mixed $db_conn, string $table, array $fieldArray, mixed $keyCols) : mixed {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}

		if (!is_object($db_conn)) {
			return false;
		}
	}

	if (!is_array($keyCols)) {
		$keyCols = [$keyCols];
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
 * @param array  $array_items An array containing each column -> value mapping in the row
 * @param string $table_name  The name of the table to make the replacement in
 * @param mixed  $key_cols    The primary key(s) for the table
 * @param bool   $autoinc     Use autoinc if available
 * @param mixed  $db_conn     Database connection to use
 *
 * @return mixed The auto increment id column (if applicable)
 */
function sql_save(array $array_items, string $table_name, mixed $key_cols = 'id', bool $autoinc = true, mixed $db_conn = false) : mixed {
	global $database_sessions, $database_default, $database_hostname, $database_port, $database_last_error;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}
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

		if (str_contains($cols[$key]['type'], 'int') ||
			str_contains($cols[$key]['type'], 'float') ||
			str_contains($cols[$key]['type'], 'double') ||
			str_contains($cols[$key]['type'], 'decimal')) {
			if ($value == '') {
				if ($cols[$key]['null'] == 'YES') {
					// TODO: We should make 'NULL', but there are issues that need to be addressed first
					$array_items[$key] = 0;
				} elseif (str_contains($cols[$key]['extra'], 'auto_increment')) {
					$array_items[$key] = 0;
				} elseif ($cols[$key]['default'] == '') {
					// TODO: We should make 'NULL', but there are issues that need to be addressed first
					$array_items[$key] = 0;
				} else {
					$array_items[$key] = $cols[$key]['default'];
				}
			} elseif (empty($value)) {
				$array_items[$key] = 0;
			} elseif (is_numeric($value)) {
				$array_items[$key] = $value;
			} else {
				cacti_log('ERROR: Column: ' . $key . ' contains an invalid value: ' . $value, false, 'DBCALL');
				$array_items[$key] = 0;
			}
		} else {
			$array_items[$key] = db_qstr($value);
		}
	}

	$replace_result = _db_replace($db_conn, $table_name, $array_items, $key_cols);

	// get the last AUTO_ID and return it
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
 * the remainder of the string in single quotes.
 *
 * @param mixed $s       The SQL to be escaped.  Can include a null
 * @param mixed $db_conn The database connection or false if to use the default
 *
 * @return string The escaped SQL string
 */
function db_qstr(mixed $s, mixed $db_conn = false) : string {
	global $database_sessions, $database_default, $database_hostname, $database_port;

	// check for a connection being passed, if not use legacy behavior
	if (!is_object($db_conn)) {
		if (isset($database_sessions["$database_hostname:$database_port:$database_default"])) {
			$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
		}
	}

	if (is_null($s)) {
		return 'NULL';
	}

	if (is_object($db_conn)) {
		return $db_conn->quote($s);
	}

	$s = str_replace(['\\', "\0", "\x1a", "'"], ['\\\\', "\\\0", '\\Z', "\\'"], $s);

	return "'" . $s . "'";
}

/**
 * db_strip_control_chars - Strip control characters from SQL command
 *
 * @param string $sql The SQL command to loose it's control chars
 *
 * @return string The SQL command
 */
function db_strip_control_chars(string $sql) : string {
	return trim(clean_up_lines($sql), ';');
}

/**
 * db_get_column_attributes - Get the attributes for a column or columns
 *
 * @param string $table   The name of the table
 * @param mixed  $columns A comma separated list of columns
 *
 * @return mixed An array of column attributes on success or false if failed
 */
function db_get_column_attributes(string $table, mixed $columns) : mixed {
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

	$column_names = [];

	foreach ($columns as $column) {
		if (!empty($column)) {
			$sql .= (cacti_sizeof($column_names) ? ',' : '') . '?';
			$column_names[] = $column;
		}
	}
	$sql .= ')';

	$params = array_merge([$table], $column_names);

	return db_fetch_assoc_prepared($sql, $params);
}

/**
 * db_get_columns_length - Get the length of a array of columns in a table
 *
 * @param string $table   The name of the table
 * @param mixed  $columns An array of column names
 *
 * @return mixed An array of column lengths on success or false if failed
 */
function db_get_columns_length(string $table, mixed $columns) : mixed {
	$column_data = db_get_column_attributes($table, $columns);

	if (!empty($column_data)) {
		return array_rekey($column_data, 'COLUMN_NAME', 'CHARACTER_MAXIMUM_LENGTH');
	}

	return false;
}

/**
 * db_get_column_length - Get the length of a column in a table
 *
 * @param string $table  The name of the table
 * @param string $column The name of the table column
 *
 * @return mixed The length on success or false if failed
 */
function db_get_column_length(string $table, string $column) : mixed {
	$column_data = db_get_columns_length($table, $column);

	if (!empty($column_data) && isset($column_data[$column])) {
		return $column_data[$column];
	}

	return false;
}

/**
 * db_check_password_length - Get the length of the password column in the
 * user_auth table and adjust if the password length to 80 chars
 *
 * @return void
 */
function db_check_password_length() : void {
	$len = db_get_column_length('user_auth', 'password');

	if ($len === false) {
		die(__('Failed to determine password field length, can not continue as may corrupt password'));
	}

	if ($len < 80) {
		// Ensure that the password length is increased before we start updating it
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
 * @param string $line  The SQL data to be executed
 * @param bool   $force Not used
 *
 * @return void
 */
function db_echo_sql(string $line, bool $force = false) : void {
	global $config;

	file_put_contents(sys_get_temp_dir() . '/cacti-sql.log', get_debug_prefix() . $line, FILE_APPEND);
}

/**
 * db_error - return the last error from the database
 *
 * @return mixed The last database error if any
 */
function db_error() : mixed {
	global $database_last_error;

	return $database_last_error;
}

/**
 * db_get_default_database - Get the database name of the current database or
 * return the default database name
 *
 * @param mixed $db_conn The connection name or false if one is not passed
 *
 * @return string Either current db name or default database if no connection/name
 */
function db_get_default_database(mixed $db_conn = false) : string {
	global $database_default;

	$database = db_fetch_cell('SELECT DATABASE()', '', true, $db_conn);

	if (empty($database)) {
		$database = $database_default;
	}

	return $database;
}

/**
 * db_force_remote_cnn - alias for db_switch_remote_to_main()
 *
 * Switches the local database connection to the main server
 * This is required for CLI script that wish to talk to the main
 * database server since by default they are connected to the local
 * database server.
 *
 * @return bool The status of the switch
 */
function db_force_remote_cnn() : bool {
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
 * @return bool If the switch was successful
 */
function db_switch_remote_to_main() : bool {
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
 * @return bool If the switch was successful
 */
function db_switch_main_to_local() : bool {
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
 * @param string $database    default $database_default
 * @param string $tables      default all tables
 * @param array  $credentials array($name => value, ...) for user, password, host, port, ssl ...
 * @param mixed  $output_file dump file name, default /tmp/cacti.dump.sql
 * @param string $options     option strings for mysqldump, if --defaults-extra-file set, dump the data directly
 *
 * @return int Return status of the executed command
 */
function db_dump_data(string $database = '', string $tables = '', array $credentials = [], mixed $output_file = false, string $options = '--extended-insert=FALSE') : int {
	global $database_default, $database_username, $database_password;

	$credentials_string = '';

	if ($database == '') {
		$database = $database_default;
	}

	if (cacti_sizeof($credentials)) {
		foreach ($credentials as $key => $value) {
			$name = trim($key);

			if (str_contains($name, '--')) {      // name like --host
				if ($name == '--password') {
					$password = $value;
				} elseif ($name == '--user') {
					$username = $value;
				} else {
					$credentials_string .= $name . '=' . $value . ' ';
				}
			} elseif (str_contains($name, '-')) { // name like -h
				if ($name == '-p') {
					$password = $value;
				} elseif ($name == '-u') {
					$username = $value;
				} else {
					$credentials_string .= $name . $value . ' ';
				}
			} else {                                  // name like host
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

	if (file_exists('/usr/bin/mariadb-dump')) {
		$dump = '/usr/bin/mariadb-dump';
	} else {
		$dump = 'mysqldump';
	}

	$dump_esc   = cacti_escapeshellcmd($dump);
	$output_esc = cacti_escapeshellarg((string) $output_file);
	$tables_esc = $tables !== '' ? implode(' ', array_map('cacti_escapeshellarg', preg_split('/\s+/', trim($tables)))) : '';

	if (str_contains($options, '--defaults-extra-file')) {
		exec("$dump_esc $options $credentials_string " . cacti_escapeshellarg($database) . ($tables_esc !== '' ? ' ' . $tables_esc : '') . " > $output_esc", $output, $retval);
	} else {
		exec("$dump_esc $options $credentials_string " . cacti_escapeshellarg($database) . ' version >/dev/null 2>&1', $output, $retval);

		if ($retval) {
			exec("$dump_esc $options $credentials_string --user=" . cacti_escapeshellarg($username) . ' --password=' . cacti_escapeshellarg($password) . ' ' . cacti_escapeshellarg($database) . ($tables_esc !== '' ? ' ' . $tables_esc : '') . " > $output_esc", $output, $retval);
		} else {
			exec("$dump_esc $options $credentials_string " . cacti_escapeshellarg($database) . ($tables_esc !== '' ? ' ' . $tables_esc : '') . " > $output_esc", $output, $retval);
		}
	}

	return $retval;
}

function db_create_permissions_array(string $database, bool $default = false) : array {
	$permissions = [
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
	];

	return [$database => $permissions];
}

function db_get_grants(bool $log = false, mixed $db_conn = false) : array {
	$db_grants = db_fetch_assoc('SHOW GRANTS FOR CURRENT_USER', $log, $db_conn);

	return $db_grants;
}

function db_get_permissions(bool $include_unknown = false, bool $log = false, mixed $db_conn = false) : array {
	global $database_default;

	$perms = db_create_permissions_array($database_default, false);

	$db_names  = [$database_default, 'mysql'];
	$db_grants = db_fetch_assoc('SHOW GRANTS FOR CURRENT_USER', $log, $db_conn);

	if (cacti_sizeof($db_grants)) {
		foreach ($db_grants as $db_grants_user) {
			foreach ($db_grants_user as $db_grant) {
				// We are only interested in GRANT lines
				if (preg_match('/GRANT (.*) ON (.+)\.(.+) TO/i', $db_grant, $db_grant_match)) {
					// Replace any * used with .* for preg_match
					// Replace any % used with .* for preg_match
					$db_grant_regex = str_replace(['*', '%'], ['.*', '.*'], $db_grant_match[2]);

					foreach ($db_names as $db_name) {
						// See if we match the database name
						$db_regex_match = preg_match('/' . $db_grant_regex . '/', '`' . $db_name . '`');

						// Yes, we did
						if ($db_regex_match) {
							// Lets get all the permissions assigned.
							$db_grant_perms = preg_split('/,[ ]*/', $db_grant_match[1]);

							if (cacti_sizeof($db_grant_perms)) {
								foreach ($db_grant_perms as $db_grant_perm) {
									$db_grant_perm = cacti_strtoupper($db_grant_perm);

									if ($db_grant_perm == 'ALL' ||
										$db_grant_perm == 'ALL PRIVILEGES') {
										$perms = db_create_permissions_array($db_name, true);

										break 3;
									}

									if (array_key_exists($db_grant_perm, $perms)) {
										if (str_contains($db_grant, "`$database_default`.*")) {
											$perms[$db_name][$db_grant_perm . ' ON *'] = true;
										} else {
											$perms[$db_name][$db_grant_perm] = true;
										}
									} elseif ($include_unknown) {
										$gs                                                = explode('.', $db_grant);
										$table                                             = explode(' ', $gs[1]);
										$table                                             = str_replace('`', '', $table[0]);
										$perms[$db_name][$db_grant_perm . ' ON ' . $table] = true;
									}
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

function db_has_permissions(mixed $permissions, mixed $database = false, bool $log = false, mixed $db_conn = false) : bool {
	global $database_default;

	if ($database == false) {
		$database = $database_default;
	}

	$perms = db_get_permissions(false, $log, $db_conn);

	if (!is_array($permissions)) {
		$permissions = [$permissions];
	}

	$found = false;

	foreach ($permissions as $permission) {
		foreach ($perms as $db => $perm) {
			if ($database == $db || $database == 'all') {
				if (!empty($perm[$permission])) {
					$found = true;

					break 2;
				}
			}
		}
	}

	return $found;
}

/**
 * Legacy wrapper function for Cacti plugins that may still
 * be using the function under it's old name
 *
 * @param int $poller_id The poller to get server info from
 *
 * @return array Information about the database server
 */
function utilities_get_mysql_info(int $poller_id = 1) : array {
	return get_mysql_info($poller_id);
}

/**
 * Function that returns information about mysql or mariadb
 *
 * @param int $poller_id The poller to get information from
 *
 * @return array Information about the database server
 */
function get_mysql_info(int $poller_id = 1) : array {
	global $local_db_cnn_id;

	if ($poller_id == 1) {
		$variables = array_rekey(db_fetch_assoc('SHOW GLOBAL VARIABLES'), 'Variable_name', 'Value');
	} else {
		$variables = array_rekey(db_fetch_assoc('SHOW GLOBAL VARIABLES', false, $local_db_cnn_id), 'Variable_name', 'Value');
	}

	if (str_contains($variables['version'], 'MariaDB')) {
		$database = 'MariaDB';
		$version  = str_replace('-MariaDB', '', $variables['version']);

		if (isset($variables['innodb_version'])) {
			$link_ver = substr($variables['innodb_version'], 0, 3);
		} else {
			$link_ver = $version;
		}
	} else {
		$database = 'MySQL';
		$version  = $variables['version'];
		$link_ver = substr($variables['version'], 0, 3);
	}

	return [
		'database'  => $database,
		'version'   => $version,
		'link_ver'  => $link_ver,
		'variables' => $variables
	];
}
