<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

function prime_default_settings() {
	global $settings;

	if (is_array($settings) && !isset($_SESSION['settings_primed'])) {
		foreach ($settings as $tab_array) {
			if (cacti_sizeof($tab_array)) {
				foreach ($tab_array as $setting => $attributes) {
					if (isset($attributes['default'])) {
						$current = db_fetch_cell_prepared('SELECT value
							FROM settings
							WHERE name = ?',
							array($setting));

						if ($current == '' || $current == null) {
							db_execute_prepared('INSERT IGNORE INTO settings
								(name, value) VALUES (?, ?)',
								array($setting, $attributes['default']));
						}
					} elseif (isset($attributes['items'])) {
						foreach ($attributes['items'] as $isetting => $iattributes) {
							if (isset($iattributes['default'])) {
								$current = db_fetch_cell_prepared('SELECT value
									FROM settings
									WHERE name = ?',
									array($isetting));

								if ($current == '' || $current == null) {
									db_execute_prepared('INSERT IGNORE INTO settings
										(name, value)
										VALUES (?, ?)',
										array($isetting, $iattributes['default']));
								}
							}
						}
					}
				}
			}
		}
	}

	$_SESSION['settings_primed'] = true;
}

function install_create_csrf_secret($file) {
	if (!file_exists($file)) {
		if (is_resource_writable($file)) {
			// Write the file
			$fh = fopen($file, 'w');
			fwrite($fh, csrf_get_secret());
			fclose($fh);

			return true;
		} else {
			return false;
		}
	}

	return true;
}

function install_unlink($file) {
	if (file_exists(CACTI_PATH_BASE . '/' . $file) && is_writable(CACTI_PATH_BASE . '/' . $file)) {
		log_install_high('file', "Unlinking file: $file");
		unlink(CACTI_PATH_BASE . '/' . $file);
	} else {
		log_install_high('file', "Unlinking file: $file failed due to permission errors.");
	}
}

function install_test_local_database_connection() {
	global $database_type, $database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port, $database_retries, $database_ssl, $database_ssl_key, $database_ssl_cert, $database_ssl_ca, $database_ssl_capath, $database_ssl_verify_server_cert;

	if (!isset($database_ssl)) {
		$database_ssl        = false;
	}

	if (!isset($database_ssl_key)) {
		$database_ssl_key    = false;
	}

	if (!isset($database_ssl_cert)) {
		$database_ssl_cert   = false;
	}

	if (!isset($database_ssl_ca)) {
		$database_ssl_ca     = false;
	}

	if (!isset($database_ssl_capath)) {
		$database_ssl_capath = false;
	}

	if (!isset($database_ssl_verify_server_cert)) {
		$database_ssl_verify_server_cert = false;
	}

	$connection = db_connect_real(
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

	if (is_object($connection)) {
		db_close($connection);

		return json_encode(array('status' => 'true'));
	} else {
		return json_encode(array('status' => 'false'));
	}
}

function install_test_remote_database_connection() {
	global $rdatabase_type, $rdatabase_hostname, $rdatabase_username, $rdatabase_password, $rdatabase_default, $rdatabase_type, $rdatabase_port, $rdatabase_retries, $rdatabase_ssl, $rdatabase_ssl_key, $rdatabase_ssl_cert, $rdatabase_ssl_ca, $rdatabase_ssl_capath, $rdatabase_ssl_verify_server_cert;

	if (!isset($rdatabase_ssl)) {
		$rdatabase_ssl        = false;
	}

	if (!isset($rdatabase_ssl_key)) {
		$rdatabase_ssl_key    = false;
	}

	if (!isset($rdatabase_ssl_cert)) {
		$rdatabase_ssl_cert   = false;
	}

	if (!isset($rdatabase_ssl_ca)) {
		$rdatabase_ssl_ca     = false;
	}

	if (!isset($rdatabase_ssl_capath)) {
		$rdatabase_ssl_capath = false;
	}

	if (!isset($rdatabase_ssl_verify_server_cert)) {
		$rdatabase_ssl_verify_server_cert = false;
	}
        
	$connection = db_connect_real(
		$rdatabase_hostname,
		$rdatabase_username,
		$rdatabase_password,
		$rdatabase_default,
		$rdatabase_type,
		$rdatabase_port,
		$rdatabase_retries,
		$rdatabase_ssl,
		$rdatabase_ssl_key,
		$rdatabase_ssl_cert,
		$rdatabase_ssl_ca,
		$rdatabase_ssl_capath,
		$rdatabase_ssl_verify_server_path                
	);

	if (is_object($connection)) {
		db_close($connection);

		return json_encode(array('status' => 'true'));
	} else {
		return json_encode(array('status' => 'false'));
	}
}

function install_test_temporary_table() {
	$table = 'test_temp_' . rand();

	if (!db_execute('CREATE TEMPORARY TABLE ' . $table . ' (`cacti` char(20) NOT NULL DEFAULT "", PRIMARY KEY (`cacti`)) ENGINE=InnoDB')) {
		return false;
	} else {
		if (!db_execute('DROP TABLE ' . $table)) {
			return false;
		}
	}

	return true;
}

function db_install_execute($sql, $params = array(), $log = true) {
	$status = (db_execute_prepared($sql, $params, $log) ? DB_STATUS_SUCCESS : DB_STATUS_ERROR);

	if ($log) {
		db_install_add_cache($status, $sql, $params);
	}

	return $status;
}

/**
 * Provides database fetch functions during install
 *
 * @param  string   $func
 * @param  string   $sql
 * @param  array    $params
 * @param  boolean  $log
 *
 * @return array
 */
function db_install_fetch_function(string $func, string $sql, array $params = array(), bool $log = true): array {
	global $database_last_error;

	$database_last_error = false;
	$data                = false;

	if (!is_callable($func) || !function_exists($func)) {
		$status = DB_STATUS_ERROR;
	}

	if ($func == 'db_fetch_cell_prepared') {
		$data = $func($sql, $params, '', $log);
	} else {
		$data = $func($sql, $params, $log);
	}
	$status = ($database_last_error ? DB_STATUS_ERROR : DB_STATUS_SUCCESS);

	if ($log || $status == DB_STATUS_ERROR) {
		db_install_add_cache($status, $sql, $params);
	}

	return array('status' => $status, 'data' => $data);
}

function db_install_fetch_assoc($sql, $params = array(), $log = true) {
	return db_install_fetch_function('db_fetch_assoc_prepared', $sql, $params, $log);
}

function db_install_fetch_cell($sql, $params = array(), $log = true) {
	return db_install_fetch_function('db_fetch_cell_prepared', $sql, $params, $log);
}

function db_install_fetch_row($sql, $params = array(), $log = true) {
	return db_install_fetch_function('db_fetch_row_prepared', $sql, $params, $log);
}

function db_install_add_column($table, $column, $ignore = true) {
	// Example: db_install_add_column ('plugin_config', array('name' => 'test' . rand(1, 200), 'type' => 'varchar (255)', 'NULL' => false));
	global $database_last_error;
	$status = DB_STATUS_SKIPPED;

	$sql = 'ALTER TABLE `' . $table . '` ADD `' . $column['name'] . '`';

	if (!db_table_exists($table)) {
		$database_last_error = 'Table \'' . $table . '\' missing, cannot add column \'' . $column['name'] . '\'';
		$status              = DB_STATUS_WARNING;
	} elseif (!db_column_exists($table, $column['name'], false)) {
		$status = db_add_column($table, $column, false) ? DB_STATUS_SUCCESS : DB_STATUS_ERROR;
	} elseif (!$ignore) {
		$status = DB_STATUS_SKIPPED;
	} else {
		$status = DB_STATUS_SUCCESS;
	}

	db_install_add_cache($status, $sql);

	return $status;
}

function db_install_change_column($table, $column, $ignore = true) {
	// Example: db_install_add_column ('plugin_config', array('name' => 'test' . rand(1, 200), 'type' => 'varchar (255)', 'NULL' => false));
	global $database_last_error;
	$status = DB_STATUS_SKIPPED;

	if (!isset($column['old_name'])) {
		$column['old_name'] = $column['name'];
	}

	$sql = 'ALTER TABLE `' . $table . '` CHANGE `' . $column['old_name'] . '` `' . $column['name'] . '`';

	if (!db_table_exists($table)) {
		$database_last_error = 'Table \'' . $table . '\' missing, cannot change column \'' . $column['name'] . '\'';
		$status              = DB_STATUS_WARNING;
	} elseif ($column['old_name'] == $column['name'] || !db_column_exists($table, $column['name'], false)) {
		$status = db_change_column($table, $column, false) ? DB_STATUS_SUCCESS : DB_STATUS_ERROR;
	} elseif (db_column_exists($table, $column['old_name'], false)) {
		$status = DB_STATUS_WARNING;
	} elseif (!$ignore) {
		$status = DB_STATUS_SKIPPED;
	} else {
		$status = DB_STATUS_SUCCESS;
	}

	db_install_add_cache($status, $sql);

	return $status;
}

function db_install_add_key($table, $type, $key, $columns, $using = '') {
	if (!is_array($columns)) {
		$columns = array($columns);
	}

	$type = strtoupper($type);

	if ($type == 'KEY' && $key == 'PRIMARY') {
		$sql = 'ALTER TABLE `' . $table . '` ADD ' . $key . ' ' . $type . '(' . implode(',', $columns) . ')';
	} else {
		$sql = 'ALTER TABLE `' . $table . '` ADD ' . $type . ' ' . $key . '(' . implode(',', $columns) . ')';
	}

	if (!empty($using)) {
		$sql .= ' USING ' . $using;
	}

	$status = DB_STATUS_SKIPPED;

	if (db_index_matches($table, $key, $columns, false) !== 0) {
		if (db_index_exists($table, $key)) {
			$status = db_install_drop_key($table, $type, $key);
		}

		if ($status != DB_STATUS_ERROR) {
			$status = db_install_execute($sql);
		}
	}

	db_install_add_cache($status, $sql);

	return $status;
}

function db_install_drop_key($table, $type, $key) {
	$type = strtoupper(str_ireplace('UNIQUE ', '', $type));

	if ($type == 'KEY' && $key == 'PRIMARY') {
		$sql = "ALTER TABLE $table DROP $key $type;";
	} else {
		$sql = "ALTER TABLE $table DROP $type $key";
	}

	$status = DB_STATUS_SKIPPED;

	if (db_index_exists($table, $key, false)) {
		$status = db_install_execute($sql);
	}

	db_install_add_cache($status, $sql);

	return $status;
}

function db_install_drop_table($table) {
	$sql = 'DROP TABLE `' . $table . '`';

	$status = DB_STATUS_SKIPPED;

	if (db_table_exists($table, false)) {
		$status = db_install_execute($sql, array(), false) ? DB_STATUS_SUCCESS : DB_STATUS_ERROR;
	}

	db_install_add_cache($status, $sql);

	return $status;
}

function db_install_rename_table($table, $newname) {
	$sql = 'RENAME TABLE `' . $table . '` TO `' . $newname . '`';

	$status = DB_STATUS_SKIPPED;

	if (db_table_exists($table, false) && !db_table_exists($newname, false)) {
		$status = db_install_execute($sql, array(), false) ? DB_STATUS_SUCCESS : DB_STATUS_ERROR;
	}

	db_install_add_cache($status, $sql);

	return $status;
}

function db_install_drop_column($table, $column) {
	$sql = 'ALTER TABLE `' . $table . '` DROP `' . $column . '`';

	$status = DB_STATUS_SKIPPED;

	if (db_column_exists($table, $column, false)) {
		$status = db_remove_column($table, $column) ? DB_STATUS_SUCCESS : DB_STATUS_ERROR;
	}

	db_install_add_cache($status, $sql);

	return $status;
}

function db_install_add_cache($status, $sql, $params = null) {
	global $cacti_upgrade_version, $database_last_error, $database_upgrade_status;

	set_config_option('install_updated', microtime(true));

	$status_char  = '?';
	$status_array = array(
		DB_STATUS_SKIPPED => '-',
		DB_STATUS_SUCCESS => '+',
		DB_STATUS_WARNING => '!',
		DB_STATUS_ERROR   => 'x',
	);

	if (array_key_exists($status, $status_array)) {
		$status_char = $status_array[$status];
	}

	print $status_char;

	if (!isset($database_upgrade_status)) {
		$database_upgrade_status = array();
	}

	// add query to upgrade results array by version to the cli global session
	if (!isset($database_upgrade_status[$cacti_upgrade_version])) {
		$database_upgrade_status[$cacti_upgrade_version] = array();
	}

	$query    = clean_up_lines($sql);
	$actual   = 0;
	$expected = substr_count($query, '?');

	if (cacti_sizeof($params)) {
		foreach ($params as $arg) {
			$pos = strpos($query, '?');

			if ($pos !== false) {
				$actual++;
				$query = substr_replace($query, "'$arg'", $pos, 1);
			}
		}
	}

	$sql = clean_up_lines($query);

	if ($actual !== $expected) {
		$sql .= "\n [[ WARNING: $expected parameters expected, $actual provided ]]";
	}

	$database_upgrade_status[$cacti_upgrade_version][] = array('status' => $status, 'sql' => $sql, 'error' => $database_last_error);

	$cacheFile = '';

	if (isset($database_upgrade_status['file'])) {
		$cacheFile = $database_upgrade_status['file'];
	}

	if (!empty($cacheFile)) {
		log_install_high('cache','<[version]> ' . $cacti_upgrade_version . ' <[status]> ' . $status . ' <[sql]> ' . clean_up_lines($sql) . ' <[error]> ' . $database_last_error);
		file_put_contents($cacheFile, '<[version]> ' . $cacti_upgrade_version . ' <[status]> ' . $status . ' <[sql]> ' . clean_up_lines($sql) . ' <[error]> ' . $database_last_error . PHP_EOL, FILE_APPEND);
	}
}

function find_search_paths($os = 'unix') {
	global $config;

	if ($os == 'win32') {
		$search_suffix = ';';
		$search_slash  = '\\';
		$search_paths  = array(
			'c:/usr/bin',
			'c:/cacti',
			'c:/rrdtool',
			'c:/spine',
			'c:/php',
			'c:/net-snmp/bin',
			'c:/progra~1/net-snmp/bin',
			'c:/progra~1/php',
			'c:/progra~1/spine',
			'c:/progra~1/spine/bin',
			'd:/usr/bin',
			'd:/cacti',
			'd:/rrdtool',
			'd:/spine',
			'd:/php',
			'd:/net-snmp/bin',
			'd:/progra~1/net-snmp/bin',
			'd:/progra~1/php',
			'd:/progra~1/spine',
			'd:/progra~1/spine/bin'
		);
	} else {
		$search_suffix = ':';
		$search_slash  = '';
		$search_paths  = array(
			'/bin',
			'/sbin',
			'/usr/bin',
			'/usr/sbin',
			'/usr/local/bin',
			'/usr/local/sbin',
			'/usr/local/spine/bin',
			'/usr/spine/bin'
		);
	}

	$env_path = getenv('PATH');

	if ($env_path) {
		$env_paths = explode($search_suffix,$env_path);

		if (!empty($search_slash)) {
			foreach ($env_paths as $env_key => $env_folder) {
				$env_paths[$env_key] = str_replace($search_slash, '/', $env_folder);
			}
		}
		$search_paths = array_merge($env_paths, $search_paths);
	}

	$env_php = getenv('PHP_BINDIR');

	if ($env_php) {
		$search_paths = array_merge(explode($search_suffix,$env_php), $search_paths);
	}

	if (!empty(CACTI_PATH_PHP)) {
		$search_paths = array_merge(explode($search_suffix, CACTI_PATH_PHP), $search_paths);
	}

	// Filter out any blank lines and then make sure those remaining are unique
	$search_paths = array_unique(array_filter($search_paths, function ($value) { return !is_null($value) && $value !== ''; }));

	return $search_paths;
}

function db_install_swap_setting($old_setting, $new_setting) {
	$exists = db_install_fetch_cell('SELECT COUNT(*) FROM settings WHERE name = ?', array($new_setting));

	if (empty($exists['data'])) {
		db_install_execute('UPDATE `settings` SET name = ? WHERE name = ?', array($new_setting, $old_setting));
	} else {
		$old_value = db_install_fetch_cell('SELECT value FROM settings WHERE NAME = ?', array($old_setting));
		db_install_execute('UPDATE `settings` SET value = ? WHERE name = ?', array($old_value['data'], $new_setting));
		db_install_execute('DELETE FROM `settings` WHERE name = ?', array($old_setting));
	}
}

function find_best_path($binary_name) {
	global $config;

	$search_paths = find_search_paths($config['cacti_server_os']);

	if (cacti_sizeof($search_paths)) {
		foreach ($search_paths as $path) {
			$desired_path = $path . '/' . $binary_name;

			if ((@file_exists($desired_path)) && (@is_readable($desired_path))) {
				return $desired_path;
			}
		}
	}

	return '';
}

function install_setup_get_templates() {
	global $config;

	if (CACTI_WEB) {
		ini_set('zlib.output_compression', '0');
	}

	$templates = array(
		'ACME.xml.gz',
		'AKCP_Device.xml.gz',
		'APC_InfraStruXure_InRow_CRAC.xml.gz',
		'APC_InfraStruXure_PDU.xml.gz',
		'Apache_Webserver.xml.gz',
		'ArubaOS_switch.xml.gz',
		'Aruba_Instant_AP_Cluster.xml.gz',
		'Aruba_OSCX_switch_6x00.xml.gz',
		'Aruba_Wireless_Controller.xml.gz',
		'BayTech_PDU.xml.gz',
		'Cacti_Stats.xml.gz',
		'Cisco_Router.xml.gz',
		'Citrix_NetScaler_VPX.xml.gz',
		'ESXi_Device.xml.gz',
		'Fortigate.xml.gz',
		'HPE_iLO.xml.gz',
		'Generic_SNMP_Device.xml.gz',
		'Local_Linux_Machine.xml.gz',
		'MikroTik_Device.xml.gz',
		'MikroTik_Switch_SWOS.xml.gz',
		'Motorola_SB6141.xml.gz',
		'NetSNMP_Device.xml.gz',
		'PING_Advanced_Ping.xml.gz',
		'SNMP_Printer.xml.gz',
		'SNMP_UPS.xml.gz',
		'Synology_NAS.xml.gz',
		'Windows_Device.xml.gz'
	);

	$path      = CACTI_PATH_INSTALL . '/templates';
	$info      = array();
	$canUnpack = (extension_loaded('simplexml') && extension_loaded('zlib'));

	foreach ($templates as $xmlfile) {
		if ($canUnpack) {
			//Loading Template Information from package
			$filename = "compress.zlib://$path/$xmlfile";

			$xml    = file_get_contents($filename);
			$xmlget = simplexml_load_string($xml);
			$data   = to_array($xmlget);

			if (is_array($data['info']['author'])) {
				$data['info']['author'] = '1';
			}

			if (is_array($data['info']['email'])) {
				$data['info']['email'] = '2';
			}

			if (is_array($data['info']['description'])) {
				$data['info']['description'] = '3';
			}

			if (is_array($data['info']['homepage'])) {
				$data['info']['homepage'] = '4';
			}

			$data['info']['filename'] = $xmlfile;
			$data['info']['name']     = $xmlfile;
			$info[]                   = $data['info'];
		} else {
			// Loading Template Information from package
			$myinfo             = @json_decode(shell_exec(cacti_escapeshellcmd(read_config_option('path_php_binary')) . ' -q ' . cacti_escapeshellarg(CACTI_PATH_CLI . '/import_package.php') . ' --filename=' . cacti_escapeshellarg("/$path/$xmlfile") . ' --info-only'), true);
			$myinfo['filename'] = $xmlfile;
			$myinfo['name']     = $xmlfile;
			$info[]             = $myinfo;
		}
	}

	return $info;
}

function install_setup_get_tables() {
	/* ensure all tables are utf8 enabled */
	$db_tables = db_fetch_assoc('SHOW TABLES');

	if ($db_tables === false) {
		return false;
	}

	$t = array();

	foreach ($db_tables as $tables) {
		foreach ($tables as $table) {
			$table_status = db_fetch_row("SHOW TABLE STATUS LIKE '$table'");

			$collation  = '';
			$engine     = '';
			$rows       = 0;
			$row_format = '';

			if ($table_status !== false) {
				if (isset($table_status['Collation']) && $table_status['Collation'] != 'utf8mb4_unicode_ci') {
					$collation = $table_status['Collation'];
				}

				if (isset($table_status['Engine']) && $table_status['Engine'] == 'MyISAM') {
					$engine = $table_status['Engine'];
				}

				if (isset($table_status['Rows'])) {
					$rows = $table_status['Rows'];
				}

				if (isset($table_status['Row_format']) && $table_status['Row_format'] == 'Compact' && $table_status['Engine'] == 'InnoDB') {
					$row_format = 'Dynamic';
				}
			}

			if ($table_status === false || $collation != '' || $engine != '' || $row_format != '') {
				$t[$table]['Name']       = $table;
				$t[$table]['Collation']  = $table_status['Collation'];
				$t[$table]['Engine']     = $table_status['Engine'];
				$t[$table]['Rows']       = $rows;
				$t[$table]['Row_format'] = $table_status['Row_format'];
			}
		}
	}

	return $t;
}

function to_array($data) {
	if (is_object($data)) {
		$data = get_object_vars($data);
	}

	return (is_array($data)) ? array_map(__FUNCTION__,$data) : $data;
}

/* Here, we define each name, default value, type, and path check for each value
we want the user to input. The "name" field must exist in the 'settings' table for
this to work. Cacti also uses different default values depending on what OS it is
running on. */

function install_tool_path($name, $defaultPaths) {
	global $config, $settings;

	$os = $config['cacti_server_os'];

	if (!isset($defaultPaths[$os])) {
		return false;
	}

	$tool = array(
		'friendly_name' => $name,
		'description'   => __('Path for %s', $name),
		'method'        => 'filepath',
		'max_length'    => 255,
		'default'       => ''
	);

	log_install_debug('file', "$name: Locations ($os), Paths: " . clean_up_lines(var_export($defaultPaths, true)));

	if (isset($settings) && isset($settings['path']) && isset($settings['path']['path_'.$name])) {
		$tool = $settings['path']['path_'.$name];
	} elseif (isset($settings) && isset($settings['mail']) && isset($settings['mail'][$name])) {
		$tool = $settings['mail'][$name];
	}

	$which_tool = '';

	if (config_value_exists('path_' . $name)) {
		$which_tool = read_config_option('path_'.$name, true);
		log_install_high('file', "Using config location: $which_tool");
	}

	if (empty($which_tool) && isset($defaultPaths[$os])) {
		$defaultPath = $defaultPaths[$os];
		$basename    = basename($defaultPath);
		log_install_debug('file', "Searching best path with location: $defaultPath");
		$which_tool = find_best_path($basename);
		log_install_debug('file', "Searching best path with location return: $which_tool");
	}

	if (empty($which_tool)) {
		$which_tool = $defaultPath;
		log_install_high('file', "Nothing found defaulting to $defaultPath");
	}

	$tool['default'] = $which_tool;

	return $tool;
}

function install_file_paths() {
	global $config, $settings;

	$input = array();

	/* PHP Binary Path */
	$input['path_php_binary'] = install_tool_path('php_binary',
		array(
			'unix'  => '/bin/php',
			'win32' => 'c:/php/php.exe'
		)
	);

	// Workaround to support xampp
	if ($config['cacti_server_os'] == 'win32') {
		$paths = array('c:/php/php.exe', 'd:/php/php.exe', 'c:/xampp/php/php.exe', 'd:/xampp/php/php.exe');

		foreach ($paths as $path) {
			if (file_exists($path)) {
				$input['path_php_binary']['default'] = $path;

				break;
			}
		}
	}

	/* RRDtool Binary Path */
	$input['path_rrdtool'] = install_tool_path('rrdtool',
		array(
			'unix'  => '/usr/bin/rrdtool',
			'win32' => 'c:/rrdtool/rrdtool.exe'
		)
	);

	/* snmpwalk Binary Path */
	$input['path_snmpwalk'] = install_tool_path('snmpwalk',
		array(
			'unix'  => '/usr/bin/snmpwalk',
			'win32' => 'c:/net-snmp/bin/snmpwalk.exe'
		)
	);

	/* snmpget Binary Path */
	$input['path_snmpget'] = install_tool_path('snmpget',
		array(
			'unix'  => '/usr/bin/snmpget',
			'win32' => 'c:/net-snmp/bin/snmpget.exe'
		)
	);

	/* snmpbulkwalk Binary Path */
	$input['path_snmpbulkwalk'] = install_tool_path('snmpbulkwalk',
		array(
			'unix'  => '/usr/bin/snmpbulkwalk',
			'win32' => 'c:/net-snmp/bin/snmpbulkwalk.exe'
		)
	);

	/* snmpgetnext Binary Path */
	$input['path_snmpgetnext'] = install_tool_path('snmpgetnext',
		array(
			'unix'  => '/usr/bin/snmpgetnext',
			'win32' => 'c:/net-snmp/bin/snmpgetnext.exe'
		)
	);

	/* snmptrap Binary Path */
	$input['path_snmptrap'] = install_tool_path('snmptrap',
		array(
			'unix'  => '/usr/bin/snmptrap',
			'win32' => 'c:/net-snmp/bin/snmptrap.exe'
		)
	);

	/* fping Binary Path */
	$input['path_fping'] = install_tool_path('fping',
		array(
			'unix'  => '/usr/sbin/fping',
			'win32' => 'c:/fping/fping.exe'
		)
	);

	/* sendmail Binary Path */
	$input['settings_sendmail_path'] = install_tool_path('settings_sendmail_path',
		array(
			'unix'  => '/usr/sbin/sendmail',
		)
	);

	/* spine Binary Path */
	$input['path_spine'] = install_tool_path('spine',
		array(
			'unix'  => '/usr/local/spine/bin/spine',
			'win32' => 'c:/spine/bin/spine.exe'
		)
	);

	// Workaround to support *BSD systems
	if ($config['cacti_server_os'] == 'unix') {
		$paths = array('/usr/local/spine/bin/spine', '/usr/local/bin/spine');

		foreach ($paths as $path) {
			if (file_exists($path)) {
				$input['path_spine']['default'] = $path;

				break;
			}
		}
	}

	$input['path_spine_config'] = $settings['path']['path_spine_config'];

	/* log file path */
	if (!config_value_exists('path_cactilog')) {
		$input['path_cactilog'] = $settings['path']['path_cactilog'];
	} else {
		$input['path_cactilog']            = $settings['path']['path_cactilog'];
		$input['path_cactilog']['default'] = read_config_option('path_cactilog');
	}

	if (empty($input['path_cactilog']['default'])) {
		$input['path_cactilog']['default'] = CACTI_PATH_LOG . '/cacti.log';
	}

	/* stderr log file path */
	if (!config_value_exists('path_cactilog')) {
		$input['path_stderrlog'] = $settings['path']['path_stderrlog'];

		if (empty($input['path_stderrlog']['default'])) {
			$input['path_stderrlog']['default'] = CACTI_PATH_LOG . '/cacti.stderr.log';
		}
	} else {
		$input['path_stderrlog']            = $settings['path']['path_stderrlog'];
		$input['path_stderrlog']['default'] = read_config_option('path_stderrlog');
	}

	/* RRDtool Version */
	if ((@file_exists($input['path_rrdtool']['default'])) && (($config['cacti_server_os'] == 'win32') || (is_executable($input['path_rrdtool']['default'])))) {
		$input['rrdtool_version'] = $settings['general']['rrdtool_version'] ?? array();

		$temp_ver = get_installed_rrdtool_version();

		if (!empty($temp_ver)) {
			$input['rrdtool_version']['default'] = $temp_ver;
		}
	}

	foreach (array_keys($input) as $key) {
		if ($input[$key] === false) {
			unset($input[$key]);
		}
	}

	return $input;
}

function remote_update_config_file() {
	global $config, $rdatabase_type, $rdatabase_hostname, $rdatabase_username,
	$rdatabase_password, $rdatabase_default, $rdatabase_type, $rdatabase_port, $rdatabase_retries,
	$rdatabase_ssl, $rdatabase_ssl_key, $rdatabase_ssl_cert, $rdatabase_ssl_ca, $rdatabase_ssl_capath, $rdatabase_verify_server_cert;

	global $database_type, $database_hostname, $database_username,
	$database_password, $database_default, $database_type, $database_port, $database_retries,
	$database_ssl, $database_ssl_key, $database_ssl_cert, $database_ssl_ca,
	$database_ssl_capath, $database_verify_server_cert;

	$failure     = '';
	$newfile     = array();
	$config_file = CACTI_PATH_INCLUDE . '/config.php';

	$connection = db_connect_real(
		$rdatabase_hostname,
		$rdatabase_username,
		$rdatabase_password,
		$rdatabase_default,
		$rdatabase_type,
		$rdatabase_port,
		$rdatabase_retries,
		$rdatabase_ssl,
		$rdatabase_ssl_key,
		$rdatabase_ssl_cert,
		$rdatabase_ssl_ca,
                $rdatabase_ssl_capath,
                $rdatabase_ssl_verify_server_cert
	);

	if (is_object($connection)) {
		if (function_exists('gethostname')) {
			$hostname = gethostname();
		} else {
			$hostname = php_uname('n');
		}

		// Check for an existing poller
		$poller_id = db_fetch_cell_prepared('SELECT id
			FROM poller
			WHERE hostname = ?',
			array($hostname), true, $connection);

		if (empty($poller_id)) {
			$save['name']        = __('New Poller');
			$save['hostname']    = $hostname;
			$save['dbdefault']   = $database_default;
			$save['dbhost']      = $database_hostname;
			$save['dbuser']      = $database_username;
			$save['dbpass']      = $database_password;
			$save['dbport']      = $database_port;
			$save['dbretries']   = $database_retries;
			$save['dbssl']       = $database_ssl ? 'on' : '';
			$save['dbsslkey']    = $database_ssl_key;
			$save['dbsslcert']   = $database_ssl_cert;
			$save['dbsslca']     = $database_ssl_ca;
			$save['dbsslcapath'] = $database_ssl_ca_path;
			$save['dbsslverifyservercert'] = $database_ssl_verify_server_cert ? 'on' : '';

			$poller_id = sql_save($save, 'poller', 'id', true, $connection);
		}

		if (!empty($poller_id)) {
			if (is_writable($config_file)) {
				$file_array = file($config_file);

				if (cacti_sizeof($file_array)) {
					foreach ($file_array as $line) {
						if (strpos(trim($line), '$poller_id') !== false) {
							$newfile[] = "\$poller_id = $poller_id;" . PHP_EOL;
						} else {
							$newfile[] = $line;
						}
					}

					$fp = fopen($config_file, 'w');

					foreach ($newfile as $line) {
						fwrite($fp, $line);
					}
					fclose($fp);
				} else {
					$failure = 'Failed to read configuration file';
				}
			} else {
				$failure = 'Configuration file is not writable';
			}
		} else {
			$failure = 'Unable to obtain poller id for this server';
		}

		db_close($connection);
	} else {
		$failure = 'Failed to connect database';
	}

	return $failure;
}

function import_colors() {
	global $config;

	if (!file_exists(__DIR__ . '/colors.csv')) {
		return false;
	}

	$contents = file(__DIR__ . '/colors.csv');

	if (cacti_count($contents)) {
		foreach ($contents as $line) {
			$line    = trim($line);
			$parts   = explode(',',$line);
			$natural = $parts[0];
			$hex     = $parts[1];
			$name    = $parts[2];

			$id = db_fetch_cell("SELECT hex FROM colors WHERE hex='$hex'");

			if (!empty($id)) {
				db_execute("UPDATE colors SET name='$name', read_only='on' WHERE hex='$hex'");
			} else {
				db_execute("INSERT IGNORE INTO colors (name, hex, read_only) VALUES ('$name', '$hex', 'on')");
			}
		}
	}

	return true;
}

function log_install_debug($section, $text, $background = false) {
	log_install_and_file(POLLER_VERBOSITY_DEBUG, $text, $section, $background);
}

function log_install_low($section, $text, $background = false) {
	log_install_and_file(POLLER_VERBOSITY_LOW, $text, $section, $background);
}

function log_install_medium($section, $text, $background = false) {
	log_install_and_file(POLLER_VERBOSITY_MEDIUM, $text, $section, $background);
}

function log_install_high($section, $text, $background = false) {
	log_install_and_file(POLLER_VERBOSITY_HIGH, $text, $section, $background);
}

function log_install_always($section, $text, $background = false) {
	log_install_and_file(POLLER_VERBOSITY_NONE, $text, $section, $background);
}

function log_install_and_file($level, $text, $section = '', $background = false) {
	$level = log_install_level_sanitize($level);
	$name  = 'INSTALL:';

	if (!empty($section)) {
		$name = 'INSTALL-' . strtoupper($section) . ':';
	}
	cacti_log(log_install_level_name($level) . ': ' . $text, false, $name, $level);
	log_install_to_file($section, $text, FILE_APPEND, $level);

	if ($background) {
		set_config_option('install_updated', microtime(true));
	}
}

function log_install_section_level($section) {
	$log_level   = POLLER_VERBOSITY_NONE;
	$log_install = log_install_level('log_install', POLLER_VERBOSITY_NONE);
	$log_section = log_install_level('log_install_'.$section, POLLER_VERBOSITY_NONE);

	if ($log_install > $log_level) {
		$log_level = $log_install;
	}

	if ($log_section > $log_level) {
		$log_level = $log_section;
	}

	return $log_level;
}

function log_install_level($option, $default_level) {
	$level = read_config_option($option, true);

	return log_install_level_sanitize($level, $default_level, $option);
}

function log_install_level_sanitize($level, $default_level = POLLER_VERBOSITY_NONE, $option = '') {
	if (empty($level) || !is_numeric($level)) {
		$level = $default_level;
	}

	if ($level < POLLER_VERBOSITY_NONE) {
		print 'Level too low - "' . $level . '"' . PHP_EOL;
		$level = POLLER_VERBOSITY_NONE;
	} elseif ($level > POLLER_VERBOSITY_DEBUG) {
		print 'Level too high - "' . $level . '"' . PHP_EOL;
		$level = POLLER_VERBOSITY_DEBUG;
	}

	return $level;
}

function log_install_level_name($level) {
	$name = 'Unknown (' . $level . ')';

	switch ($level) {
		case POLLER_VERBOSITY_NONE:
			$name = 'always';

			break;
		case POLLER_VERBOSITY_LOW:
			$name = 'general';

			break;
		case POLLER_VERBOSITY_MEDIUM:
			$name = 'info';

			break;
		case POLLER_VERBOSITY_HIGH:
			$name = 'notice';

			break;
		case POLLER_VERBOSITY_DEBUG:
			$name = 'debug';

			break;
	}

	return $name;
}

function log_install_to_file($section, $data, $flags = FILE_APPEND, $level = POLLER_VERBOSITY_DEBUG, $force = false) {
	global $config, $debug;
	$log_level = log_install_section_level($section);

	$can_log     = $level <= $log_level;
	$day         = date('Y-m-d');
	$time        = date('H:i:s');
	$levelname   = log_install_level_name($level);
	$sectionname = empty($section) ? 'global' : $section;

	$format_cli  = '[%s] [ %15s %-7s ] %s%s';
	$format_log1 = '[%s %s] [ %-7s ] %s%s';
	$format_log2 = '[%s %s] [ %15s %-7s ] %s%s';

	if (($force || $can_log) && defined('log_install_echo')) {
		printf($format_cli, $time, $sectionname, $levelname, $data, PHP_EOL);
	}

	if ($can_log) {
		if (empty($section)) {
			$section = 'general';
		}
		$logfile = 'install' . '-' . $section;
		file_put_contents(CACTI_PATH_LOG . '/' . $logfile . '.log', sprintf($format_log1, $day, $time, $levelname, $data, PHP_EOL), $flags);
		file_put_contents(CACTI_PATH_LOG . '/install-complete.log', sprintf($format_log2, $day, $time, $sectionname, $levelname, $data, PHP_EOL), $flags);
	}
}

/** repair_automation() - Repairs mangled automation graph rules based
 *  upon the change in the way that Cacti imports the Graph Templates after
 *  Cacti 1.2.4.
 **/
function repair_automation() {
	log_install_always('', 'Repairing Automation Rules');

	$hash_array = array(
		array(
			'name'                 => 'Traffic 64 bit Server',
			'automation_id'        => 1,
			'snmp_query_graph_id'  => 9,
			'snmp_query_id'        => 1,
			'snmp_query_hash'      => 'd75e406fdeca4fcef45b8be3a9a63cbc',
			'snmp_query_graph_hash'=> 'ab93b588c29731ab15db601ca0bc9dec',
		),
		array(
			'name'                 => 'Traffic 64 bit Server Linux',
			'automation_id'        => 2,
			'snmp_query_graph_id'  => 9,
			'snmp_query_id'        => 1,
			'snmp_query_hash'      => 'd75e406fdeca4fcef45b8be3a9a63cbc',
			'snmp_query_graph_hash'=> 'ab93b588c29731ab15db601ca0bc9dec',
		),
		array(
			'name'                 => 'Disk Space',
			'automation_id'        => 3,
			'snmp_query_graph_id'  => 18,
			'snmp_query_id'        => 8,
			'snmp_query_hash'      => '9343eab1f4d88b0e61ffc9d020f35414',
			'snmp_query_graph_hash'=> '46c4ee688932cf6370459527eceb8ef3',
		)
	);

	foreach ($hash_array as $item) {
		$exists = db_fetch_row_prepared('SELECT *
			FROM automation_graph_rules
			WHERE id = ?
			AND name = ?',
			array(
				$item['automation_id'],
				$item['name']
			)
		);

		if (cacti_sizeof($exists)) {
			$exists_snmp_query_id = db_fetch_cell_prepared('SELECT id
				FROM snmp_query
				WHERE hash = ?',
				array($item['snmp_query_hash']));

			$exists_snmp_query_graph_id = db_fetch_cell_prepared('SELECT id
				FROM snmp_query_graph
				WHERE hash = ?',
				array($item['snmp_query_graph_hash']));

			db_execute_prepared('UPDATE automation_graph_rules
				SET snmp_query_id = ?, graph_type_id = ?
				WHERE id = ?',
				array(
					$exists_snmp_query_id,
					$exists_snmp_query_graph_id,
					$item['automation_id']
				)
			);
		}
	}
}

function install_full_sync() {
	global $config;

	include_once(CACTI_PATH_LIBRARY . '/poller.php');

	$pinterval = read_config_option('poller_interval');
	$gap_time  = $pinterval * 2;

	/* counter arrays */
	$failed    = array();
	$success   = array();
	$skipped   = array();
	$timeout   = array();

	$pollers = db_fetch_assoc('SELECT id, status, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(last_update) as gap
		FROM poller
		WHERE id > 1
		AND disabled = ""');

	log_install_always('sync', 'Found ' . cacti_sizeof($pollers) . ' poller(s) to sync');

	if (cacti_sizeof($pollers)) {
		foreach ($pollers as $poller) {
			log_install_debug('sync', 'Poller ' . $poller['id'] . ' has a status of ' . $poller['status'] . ' with gap ' . $poller['gap']);

			if (($poller['status'] == POLLER_STATUS_NEW) ||
				($poller['status'] == POLLER_STATUS_DOWN) ||
				($poller['status'] == POLLER_STATUS_DISABLED)) {
				$skipped[] = $poller['id'];
			} elseif ($poller['gap'] < $gap_time) {
				log_install_medium('sync', 'Replicating to Poller ' . $poller['id']);

				if (read_config_option('disable_full_sync_on_upgrade') == '') {
					if (replicate_out($poller['id'])) {
						log_install_debug('sync', 'Completed replication to Poller ' . $poller['id']);
						$success[] = $poller['id'];

						db_execute_prepared('UPDATE poller
							SET last_sync = NOW()
							WHERE id = ?',
							array($poller['id']));
					} else {
						log_install_debug('sync', 'Failed replication to Poller ' . $poller['id']);
						$failed[] = $poller['id'];
					}
				} else {
					log_install_low('sync', 'Database replication skipped for Poller ' . $poller['id']);
				}
			} else {
				$timeout[] = $poller['id'];
			}
		}
	}
	log_install_debug('sync', 'Success: ' . cacti_sizeof($success) . ', Failed: ' . cacti_sizeof($failed) . ', Skipped: ' . cacti_sizeof($skipped) . ', Total: ' . cacti_sizeof($pollers));

	return array(
		'success' => $success,
		'failed'  => $failed,
		'skipped' => $skipped,
		'timeout' => $timeout,
		'total'   => cacti_sizeof($pollers)
	);
}
