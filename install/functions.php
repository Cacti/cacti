<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

include_once(dirname(__FILE__) . '/../include/global_settings.php');

function prime_default_settings() {
	global $settings;

	if (is_array($settings) && !isset($_SESSION['settings_primed'])) {
		foreach ($settings as $tab_array) {
			if (sizeof($tab_array)) {
				foreach($tab_array as $setting => $attributes) {
					if (isset($attributes['default'])) {
						db_execute_prepared('INSERT IGNORE INTO settings 
							(name, value) VALUES (?, ?)', 
							array($setting, $attributes['default']));
					} elseif (isset($attributes['items'])) {
						foreach($attributes['items'] as $isetting => $iattributes) {
							if (isset($iattributes['default'])) {
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

	$_SESSION['settings_primed'] = true;
}

function install_test_local_database_connection() {
	global $database_type, $database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port, $database_ssl;

	$connection = db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port, $database_ssl);

	if (is_object($connection)) {
		db_close($connection);
		print __('Local Connection Successful');
	} else {
		print __('Local Connection Failed');
	}
}

function install_test_remote_database_connection() {
	global $rdatabase_type, $rdatabase_hostname, $rdatabase_username, $rdatabase_password, $rdatabase_default, $rdatabase_type, $rdatabase_port, $rdatabase_ssl;

	$connection = db_connect_real($rdatabase_hostname, $rdatabase_username, $rdatabase_password, $rdatabase_default, $rdatabase_type, $rdatabase_port, $rdatabase_ssl);

	if (is_object($connection)) {
		db_close($connection);
		print __('Remote Connection Successful');
	} else {
		print __('Remote Connection Failed');
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

function verify_php_extensions($extensions) {
	//FIXME: More to foreach loop
	for ($i = 0; $i < count($extensions); $i++) {
		if (extension_loaded($extensions[$i]['name'])){
			$extensions[$i]['installed'] = true;
		}
	}

	return $extensions;
}

function db_install_execute($sql, $log = true) {
	$status = (db_execute($sql, $log) ? DB_STATUS_SUCCESS : DB_STATUS_ERROR);

	if ($log) {
		db_install_add_cache($status, $sql);
	}

	return $status;
}

function db_install_add_column($table, $column, $ignore = true) {
	// Example: db_install_add_column ('plugin_config', array('name' => 'test' . rand(1, 200), 'type' => 'varchar (255)', 'NULL' => false));
	global $database_last_error;
	$status = DB_STATUS_SKIPPED;

	$sql = 'ALTER TABLE `' . $table . '` ADD `' . $column['name'] . '`';

	if (!db_table_exists($table)) {
		$database_last_error = 'Table \'' . $table . '\' missing, cannot add column \'' . $column['name'] . '\'';
		$status = DB_STATUS_WARNING;
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
		$status = db_install_execute($sql, false) ? DB_STATUS_SUCCESS : DB_STATUS_ERROR;
	}

	db_install_add_cache($status, $sql);
	return $status;
}

function db_install_rename_table($table, $newname) {
	$sql = 'RENAME TABLE `' . $table . '` TO `' . $newname . '`';

	$status = DB_STATUS_SKIPPED;
	if (db_table_exists($table, false) && !db_table_exists($newname, false)) {
		$status = db_install_execute($sql, false) ? DB_STATUS_SUCCESS : DB_STATUS_ERROR;
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

function db_install_add_cache($status, $sql) {
	echo ".";
	global $cacti_upgrade_version, $database_last_error, $database_upgrade_status;

	if (!isset($database_upgrade_status)) {
		$database_upgrade_status = array();
	}

	// add query to upgrade results array by version to the cli global session
	if (!isset($database_upgrade_status[$cacti_upgrade_version])) {
		$database_upgrade_status[$cacti_upgrade_version] = array();
	}

	$database_upgrade_status[$cacti_upgrade_version][] = array('status' => $status, 'sql' => $sql, 'error' => $database_last_error);

	$cacheFile = '';
	if (isset($database_upgrade_status['file'])) {
		$cacheFile = $database_upgrade_status['file'];
	}

	if (!empty($cacheFile)) {
		if (function_exists('log_install')) {
			log_install('cache','<[version]> ' . $cacti_upgrade_version . ' <[status]> ' . $status . ' <[sql]> ' . clean_up_lines($sql) . ' <[error]> ' . $database_last_error);
		}
		file_put_contents($cacheFile, '<[version]> ' . $cacti_upgrade_version . ' <[status]> ' . $status . ' <[sql]> ' . clean_up_lines($sql) . ' <[error]> ' . $database_last_error . PHP_EOL, FILE_APPEND);
	}
}

function find_best_path($binary_name) {
	global $config;
	if ($config['cacti_server_os'] == 'win32') {
		$search_paths = array(
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
		$search_paths = array(
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

	for ($i=0; $i<count($search_paths); $i++) {
		if ((file_exists($search_paths[$i] . '/' . $binary_name)) && (is_readable($search_paths[$i] . '/' . $binary_name))) {
			return $search_paths[$i] . '/' . $binary_name;
		}
	}
}

function install_setup_get_templates() {
	global $config;

	@ini_set('zlib.output_compression', '0');

	$templates = array(
		'Cisco_Router.xml.gz',
		'Generic_SNMP_Device.xml.gz',
		'Local_Linux_Machine.xml.gz',
		'NetSNMP_Device.xml.gz',
		'Windows_Device.xml.gz'
	);

	$path = $config['base_path'] . '/install/templates';
	$info = array();
	foreach ($templates as $xmlfile) {
		$filename = "compress.zlib://$path/$xmlfile";
		$xml = file_get_contents($filename);;
		//Loading Template Information from package
		$xmlget = simplexml_load_string($xml);
		$data = to_array($xmlget);
		if (is_array($data['info']['author'])) $data['info']['author'] = '1';
		if (is_array($data['info']['email'])) $data['info']['email'] = '2';
		if (is_array($data['info']['description'])) $data['info']['description'] = '3';
		if (is_array($data['info']['homepage'])) $data['info']['homepage'] = '4';

		$data['info']['filename'] = $xmlfile;
		$info[] = $data['info'];
	}

	return $info;
}

function install_setup_get_tables() {
	/* ensure all tables are utf8 enabled */
	$db_tables = db_fetch_assoc("SHOW TABLES");
	if ($db_tables === false) {
		return false;
	}

	$t = array();
	foreach ($db_tables as $tables) {
		foreach ($tables as $table) {
			$table_status = db_fetch_row("SHOW TABLE STATUS LIKE '$table'");

			$collation = '';
			$engine = '';
			$rows = 0;

			if ($table_status !== false) {
				$collation = ($table_status['Collation'] != 'utf8mb4_unicode_ci') ? $table_status['Collation'] : '';
				$engine    = ($table_status['Engine']    == 'MyISAM')             ? $table_status['Engine']    : '';
				$rows      = $table_status['Rows'];
			}

			if ($table_status === false || $collation != '' || $engine != '') {
				$t[$table]['Name'] = $table;
				$t[$table]['Collation'] = $collation;
				$t[$table]['Engine'] = $engine;
				$t[$table]['Rows'] = $rows;
			}
		}
	}

	return $t;
}

function to_array ($data) {
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

	$tool = array(
		'friendly_name' => $name,
		'description' => __('Path for %s', $name),
		'method' => 'filepath',
		'max_length' => 255,
		'default' => ''
	);

	log_install('file', "$name: Locations ($os), Paths: " . clean_up_lines(var_export($defaultPaths, true)));
	if (isset($settings) && isset($settings['path']) && isset($settings['path']['path_'.$name])) {
		$tool = $settings['path']['path_'.$name];
	} elseif (isset($settings) && isset($settings['mail']) && isset($settings['mail'][$name])) {
		$tool = $settings['mail'][$name];
	}

	$which_tool = '';
	if (config_value_exists('path_'.$name)) {
		$which_tool = read_config_option('path_'.$name, true);
		log_install('file', "Using config location: $which_tool");
	}

	if (empty($which_tool) && isset($defaultPaths[$os])) {
		$defaultPath = $defaultPaths[$config['cacti_server_os']];
		$basename = basename($defaultPath);
		log_install('file', "Searching best path with location: $defaultPath");
		$which_tool = find_best_path($basename);
		log_install('file', "Searching best path with location return: $which_tool");
	}

	if (empty($which_tool)) {
		$which_tool = $defaultPath;
		log_install('file', "Nothing found defaulting to $defaultPath");
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
			'unix'  => '/usr/bin/php',
			'win32' => 'c:/php/php.exe'
		));

	/* RRDtool Binary Path */
	$input['path_rrdtool'] = install_tool_path('rrdtool',
		array(
			'unix'  => '/usr/local/bin/rrdtool',
			'win32' => 'c:/rrdtool/rrdtool.exe'
		));

	/* snmpwalk Binary Path */
	$input['path_snmpwalk'] = install_tool_path('snmpwalk',
		array(
			'unix'  => '/usr/local/bin/snmpwalk',
			'win32' => 'c:/net-snmp/bin/snmpwalk.exe'
		));

	/* snmpget Binary Path */
	$input['path_snmpget'] = install_tool_path('snmpget',
		array(
			'unix'  => '/usr/local/bin/snmpget',
			'win32' => 'c:/net-snmp/bin/snmpget.exe'
		));

	/* snmpbulkwalk Binary Path */
	$input['path_snmpbulkwalk'] = install_tool_path('snmpbulkwalk',
		array(
			'unix'  => '/usr/local/bin/snmpbulkwalk',
			'win32' => 'c:/net-snmp/bin/snmpbulkwalk.exe'
		));

	/* snmpgetnext Binary Path */
	$input['path_snmpgetnext'] = install_tool_path('snmpgetnext',
		array(
			'unix'  => '/usr/local/bin/snmpgetnext',
			'win32' => 'c:/net-snmp/bin/snmpgetnext.exe'
		));

	/* snmptrap Binary Path */
	$input['path_snmptrap'] = install_tool_path('snmptrap',
		array(
			'unix'  => '/usr/local/bin/snmptrap',
			'win32' => 'c:/net-snmp/bin/snmptrap.exe'
		));

	/* spine Binary Path */
	$input['path_spine'] = install_tool_path('spine',
		array(
			'unix'  => '/usr/local/spine/bin/spine',
			'win32' => 'c:/spine/bin/spine.exe'
		));

	/* sendmail Binary Path */
	$input['settings_sendmail_path'] = install_tool_path('settings_sendmail_path',
		array(
			'unix'  => '/usr/sbin/sendmail',
			'win32' => ''
		));

	/* log file path */
	$input['path_cactilog'] = $settings['path']['path_cactilog'];
	if (empty($input['path_cactilog']['default'])) {
		$input['path_cactilog']['default'] = $config['base_path'] . '/log/cacti.log';
	}

	/* RRDtool Version */
	if ((@file_exists($input['path_rrdtool']['default'])) && (($config['cacti_server_os'] == 'win32') || (is_executable($input['path_rrdtool']['default']))) ) {
		$input['rrdtool_version'] = $settings['general']['rrdtool_version'];

		$out_array = array();

		exec("\"" . $input['path_rrdtool']['default'] . "\"", $out_array);

		if (sizeof($out_array) > 0) {
			if (preg_match('/^RRDtool ([0-9.]+) /', $out_array[0], $m)) {
				global $rrdtool_versions;
				foreach ($rrdtool_versions as $rrdtool_version => $rrdtool_version_text) {
					if (cacti_version_compare($rrdtool_version, $m[1], '<=')) {
						$input['rrdtool_version']['default'] = $rrdtool_version;
					}
				}
			}
		}
	}

	return $input;
}

function remote_update_config_file() {
	global $config, $rdatabase_type, $rdatabase_hostname, $rdatabase_username,
		$rdatabase_password, $rdatabase_default, $rdatabase_type, $rdatabase_port, $rdatabase_ssl;

	global $database_type, $database_hostname, $database_username,
		$database_password, $database_default, $database_type, $database_port, $database_ssl;

	$failure     = '';
	$newfile     = array();
	$config_file = $config['base_path'] . '/include/config.php';

	$connection = db_connect_real($rdatabase_hostname, $rdatabase_username, $rdatabase_password, $rdatabase_default, $rdatabase_type, $rdatabase_port, $rdatabase_ssl);

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
			$save['name'] = __('New Poller');
			$save['hostname']  = $hostname;
			$save['dbdefault'] = $database_default;
			$save['dbhost']    = $database_hostname;
			$save['dbuser']    = $database_username;
			$save['dbpass']    = $database_password;
			$save['dbport']    = $database_port;
			$save['dbssl']     = $database_ssl;

			$poller_id = sql_save($save, 'poller', 'id', true, $connection);
		}

		if (!empty($poller_id)) {
			if (is_writable($config_file)) {
				$file_array = file($config_file);

				if (sizeof($file_array)) {
					foreach($file_array as $line) {
						if (strpos(trim($line), "\$poller_id") !== false) {
							$newfile[] = "\$poller_id = $poller_id;" . PHP_EOL;
						} else {
							$newfile[] = $line;
						}
					}

					$fp = fopen($config_file, 'w');
					foreach($newfile as $line) {
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

	if (!file_exists(dirname(__FILE__) . '/colors.csv')) {
		return false;
	}

	$contents = file(dirname(__FILE__) . '/colors.csv');

	if (count($contents)) {
		foreach($contents as $line) {
			$line    = trim($line);
			$parts   = explode(',',$line);
			$natural = $parts[0];
			$hex     = $parts[1];
			$name    = $parts[2];

			$id = db_fetch_cell("SELECT hex FROM colors WHERE hex='$hex'");
			if ($id === false) {
				return $false;
			}

			if (!empty($id)) {
				if (!db_execute("UPDATE colors SET name='$name', read_only='on' WHERE hex='$hex'")) {
					return false;
				}
			} else {
				if (!db_execute("INSERT INTO colors (name, hex, read_only) VALUES ('$name', '$hex', 'on')")) {
					return false;
				}
			}
		}
	}

	return true;
}

