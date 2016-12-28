<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

function test_database_connection() {
	global $rdatabase_type, $rdatabase_hostname, $rdatabase_username, $rdatabase_password, $rdatabase_default, $rdatabase_type, $rdatabase_port, $rdatabase_ssl;

	$connection = db_connect_real($rdatabase_hostname, $rdatabase_username, $rdatabase_password, $rdatabase_default, $rdatabase_type, $rdatabase_port, $rdatabase_ssl);

	if (is_object($connection)) {
		db_close($connection);
		print 'Connection Successful';
	}else{
		print 'Connection Failed';
	}
}

function verify_php_extensions($extensions) {
	for ($i = 0; $i < count($extensions); $i++) {
		if (extension_loaded($extensions[$i]['name'])){
			$extensions[$i]['installed'] = true;
		}
	}
	return $extensions;
}

function db_install_execute($sql) {
	$status = (db_execute($sql) ? 1 : 0);
	db_install_add_cache ($status, $sql);
}

function db_install_add_column ($table, $column) {
	// Example: db_install_add_column ('plugin_config', array('name' => 'test' . rand(1, 200), 'type' => 'varchar (255)', 'NULL' => false));
	$sql = 'ALTER TABLE `' . $table . '` ADD `' . $column['name'] . '`';
	if (!db_column_exists($table, $column['name'], false)) {
		$status = db_add_column($table, $column, false);
	} else {
		$status = 2;
	}
	db_install_add_cache ($status, $sql);
}

function db_install_add_key ($table, $type, $key, $columns) {
	if (!is_array($columns)) {
		$columns = array($columns);
	}
	
	$sql = 'ALTER TABLE `' . $table . '` ADD ' . $type . ' ' . $key . '(' . implode(',', $columns) . ')';
	if (!db_index_exists($table, $key, false)) {
		$status = db_install_execute($sql);
	} else {
		db_install_add_cache (2, $sql);
	}
}

function db_install_drop_table ($table) {
	$sql = 'DROP TABLE `' . $table . '`';
	if (db_table_exists($table, false)) {
		db_install_execute ($sql);
	} else {
		db_install_add_cache (2, $sql);
	}
}

function db_install_rename_table ($table, $newname) {
	$sql = 'RENAME TABLE `' . $table . '` TO `' . $newname . '`';
	if (db_table_exists($table, false) && !db_table_exists($newname, false)) {
		db_install_execute ($sql);
	} else {
		db_install_add_cache (2, $sql);
	}
}

function db_install_drop_column ($table, $column) {
	$sql = 'ALTER TABLE `' . $table . '` DROP `' . $column . '`';
	if (db_column_exists($table, $column, false)) {
		$status = (db_remove_column ($table, $column) ? 1 : 0);
	} else {
		$status = 2;
	}
	db_install_add_cache ($status, $sql);
}

function db_install_add_cache ($status, $sql) {
	global $upgrade_version, $session;

	if (isset($_SESSION)) {
		$sql_install_cache = (isset($_SESSION['sess_sql_install_cache']) ? $_SESSION['sess_sql_install_cache'] : array());
		$sql_install_cache{sizeof($sql_install_cache)}[$upgrade_version][$status] = $sql;
		$_SESSION['sess_sql_install_cache'] = $sql_install_cache;
	}else{
		$sql_install_cache = (isset($session['sess_sql_install_cache']) ? $session['sess_sql_install_cache'] : array());
		$sql_install_cache{sizeof($sql_install_cache)}[$upgrade_version][$status] = $sql;
		$session['sess_sql_install_cache'] = $sql_install_cache;
	}
}

function find_best_path($binary_name) {
	global $config;
	if ($config['cacti_server_os'] == 'win32') {
		$search_paths = array('c:/usr/bin', 'c:/cacti', 'c:/rrdtool', 'c:/spine', 'c:/php', 'c:/progra~1/php', 'c:/net-snmp/bin', 'c:/progra~1/net-snmp/bin', 'd:/usr/bin', 'd:/net-snmp/bin', 'd:/progra~1/net-snmp/bin', 'd:/cacti', 'd:/rrdtool', 'd:/spine', 'd:/php', 'd:/progra~1/php');
	}else{
		$search_paths = array('/bin', '/sbin', '/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/local/sbin');
	}

	for ($i=0; $i<count($search_paths); $i++) {
		if ((file_exists($search_paths[$i] . '/' . $binary_name)) && (is_readable($search_paths[$i] . '/' . $binary_name))) {
			return $search_paths[$i] . '/' . $binary_name;
		}
	}
}

function plugin_setup_get_templates() {
	global $config;
	$templates = array(
		'Disk IO Usage.xml.gz'
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

function plugin_setup_install_template($xmlfile, $opt = 0, $interval = 5) {
	global $config;
	if ($opt) {
		$path = $config['base_path'] . '/install/templates/';
	} else {
		$path = $config['base_path'] . '/install/templates/';
	}

	if ($interval == 1) {
		$interval = array(1, 2, 3, 4, 5);
	} else {
		$interval = array(1, 2, 3, 4);
	}

	/* set new timeout and memory settings */
	ini_set("max_execution_time", "5");
	ini_set("memory_limit", "64M");

	$public_key = <<<EOD
-----BEGIN PUBLIC KEY-----
MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAMbPpuQfwmg93oOGjdLKrAqwEPwvvNjC
bk2YZiDglh8lQJxNQI9glG1Z/ptvqprFO3iSx9rTP4vzZ0Ek2+EMYTMCAwEAAQ==
-----END PUBLIC KEY-----
EOD;

	$filename = "compress.zlib://$path/$xmlfile";
	$binary_signature = "";

	$f = fopen($filename, 'r');
	$xml = "";
	while (!feof($f)) {
		$x = fgets($f);
		if (strpos($x, "<signature>") !== FALSE) {
			$binary_signature =  base64_decode(trim(str_replace(array('<signature>', '</signature>'), '', $x)));
			$x = "	<signature></signature>\n";
		}
		$xml .= "$x";
	}
	fclose($f); 

	// Verify Signature
	$ok = openssl_verify($xml, $binary_signature, $public_key);
	if ($ok == 1) {
		//print "	File is signed correctly\n";
	} elseif ($ok == 0) {
		//print "	ERROR: File has been tampered with\n";
		//exit;
		return;
	} else {
		//print "	ERROR: Could not verify signature!\n";
		//exit;
		return;
	}

	//print "Loading Plugin Information from package\n";
	$xmlget = simplexml_load_string($xml); 
	$data = to_array($xmlget);

	$plugin = $data['info']['name'];

	//print "Verifying each files signature\n";
	if (isset($data['files']['file']['data'])) {
		$data['files']['file'] = array($data['files']['file']);
	}

	foreach ($data['files']['file'] as $f) {

		$binary_signature = base64_decode($f['filesignature']);
		$fdata = base64_decode($f['data']);
		$ok = openssl_verify($fdata, $binary_signature, $public_key);
		if ($ok == 1) {
			//print "	File OK : " . $f['name'] . "\n";
		} else {
			//print "	ERROR: Could not verify signature for file: " . $f['name'] . "\n";
			//exit;
			return;
		}
	}
	include_once($config['base_path'] . "/lib/import.php");

	$p = $config['base_path'];
	$error = false;
	//print "Writing Files\n";
	foreach ($data['files']['file'] as $f) {
		$fdata = base64_decode($f['data']);
		$name = $f['name'];
		if (substr($name, 0, 8) == 'scripts/' || substr($name, 0, 9) == 'resource/') {
			$filename = "$p/$name";
			//print "	Writing $filename\n";
			$file = fopen($filename,'wb');
			fwrite($file ,$fdata, strlen($fdata));
			fclose($file);
			clearstatcache();
			if (!file_exists($filename)) {
				//print "	Unable to create directory: $filename\n";
			}
		} else {
			$debug_data = import_xml_data($fdata, false, 1);
		}
	}
	//print "File creation complete\n";
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

function install_file_paths () {
	global $config, $settings;

	/* RRDTool Binary Path */
	$input = array();
	$input['path_rrdtool'] = $settings['path']['path_rrdtool'];

	if ($config['cacti_server_os'] == 'unix') {
		$which_rrdtool = find_best_path('rrdtool');

		if (config_value_exists('path_rrdtool')) {
			$input['path_rrdtool']['default'] = read_config_option('path_rrdtool');
		}else if (!empty($which_rrdtool)) {
			$input['path_rrdtool']['default'] = $which_rrdtool;
		}else{
			$input['path_rrdtool']['default'] = '/usr/local/bin/rrdtool';
		}
	}elseif ($config['cacti_server_os'] == 'win32') {
		$which_rrdtool = find_best_path('rrdtool.exe');

		if (config_value_exists('path_rrdtool')) {
			$input['path_rrdtool']['default'] = read_config_option('path_rrdtool');
		}else if (!empty($which_rrdtool)) {
			$input['path_rrdtool']['default'] = $which_rrdtool;
		}else{
			$input['path_rrdtool']['default'] = 'c:/rrdtool/rrdtool.exe';
		}
	}

	/* PHP Binary Path */
	$input['path_php_binary'] = $settings['path']['path_php_binary'];

	if ($config['cacti_server_os'] == 'unix') {
		$which_php = find_best_path('php');

		if (config_value_exists('path_php_binary')) {
			$input['path_php_binary']['default'] = read_config_option('path_php_binary');
		}else if (!empty($which_php)) {
			$input['path_php_binary']['default'] = $which_php;
		}else{
			$input['path_php_binary']['default'] = '/usr/bin/php';
		}
	}elseif ($config['cacti_server_os'] == 'win32') {
		$which_php = find_best_path('php.exe');

		if (config_value_exists('path_php_binary')) {
			$input['path_php_binary']['default'] = read_config_option('path_php_binary');
		}else if (!empty($which_php)) {
			$input['path_php_binary']['default'] = $which_php;
		}else{
			$input['path_php_binary']['default'] = 'c:/php/php.exe';
		}
	}

	/* snmpwalk Binary Path */
	$input['path_snmpwalk'] = $settings['path']['path_snmpwalk'];

	if ($config['cacti_server_os'] == 'unix') {
		$which_snmpwalk = find_best_path('snmpwalk');

		if (config_value_exists('path_snmpwalk')) {
			$input['path_snmpwalk']['default'] = read_config_option('path_snmpwalk');
		}else if (!empty($which_snmpwalk)) {
			$input['path_snmpwalk']['default'] = $which_snmpwalk;
		}else{
			$input['path_snmpwalk']['default'] = '/usr/local/bin/snmpwalk';
		}
	}elseif ($config['cacti_server_os'] == 'win32') {
		$which_snmpwalk = find_best_path('snmpwalk.exe');

		if (config_value_exists('path_snmpwalk')) {
			$input['path_snmpwalk']['default'] = read_config_option('path_snmpwalk');
		}else if (!empty($which_snmpwalk)) {
			$input['path_snmpwalk']['default'] = $which_snmpwalk;
		}else{
			$input['path_snmpwalk']['default'] = 'c:/net-snmp/bin/snmpwalk.exe';
		}
	}

	/* snmpget Binary Path */
	$input['path_snmpget'] = $settings['path']['path_snmpget'];

	if ($config['cacti_server_os'] == 'unix') {
		$which_snmpget = find_best_path('snmpget');

		if (config_value_exists('path_snmpget')) {
			$input['path_snmpget']['default'] = read_config_option('path_snmpget');
		}else if (!empty($which_snmpget)) {
			$input['path_snmpget']['default'] = $which_snmpget;
		}else{
			$input['path_snmpget']['default'] = '/usr/local/bin/snmpget';
		}
	}elseif ($config['cacti_server_os'] == 'win32') {
		$which_snmpget = find_best_path('snmpget.exe');

		if (config_value_exists('path_snmpget')) {
			$input['path_snmpget']['default'] = read_config_option('path_snmpget');
		}else if (!empty($which_snmpget)) {
			$input['path_snmpget']['default'] = $which_snmpget;
		}else{
			$input['path_snmpget']['default'] = 'c:/net-snmp/bin/snmpget.exe';
		}
	}

	/* snmpbulkwalk Binary Path */
	$input['path_snmpbulkwalk'] = $settings['path']['path_snmpbulkwalk'];

	if ($config['cacti_server_os'] == 'unix') {
		$which_snmpbulkwalk = find_best_path('snmpbulkwalk');

		if (config_value_exists('path_snmpbulkwalk')) {
			$input['path_snmpbulkwalk']['default'] = read_config_option('path_snmpbulkwalk');
		}else if (!empty($which_snmpbulkwalk)) {
			$input['path_snmpbulkwalk']['default'] = $which_snmpbulkwalk;
		}else{
			$input['path_snmpbulkwalk']['default'] = '/usr/local/bin/snmpbulkwalk';
		}
	}elseif ($config['cacti_server_os'] == 'win32') {
		$which_snmpbulkwalk = find_best_path('snmpbulkwalk.exe');

		if (config_value_exists('path_snmpbulkwalk')) {
			$input['path_snmpbulkwalk']['default'] = read_config_option('path_snmpbulkwalk');
		}else if (!empty($which_snmpbulkwalk)) {
			$input['path_snmpbulkwalk']['default'] = $which_snmpbulkwalk;
		}else{
			$input['path_snmpbulkwalk']['default'] = 'c:/net-snmp/bin/snmpbulkwalk.exe';
		}
	}

	/* snmpgetnext Binary Path */
	$input['path_snmpgetnext'] = $settings['path']['path_snmpgetnext'];

	if ($config['cacti_server_os'] == 'unix') {
		$which_snmpgetnext = find_best_path('snmpgetnext');

		if (config_value_exists('path_snmpgetnext')) {
			$input['path_snmpgetnext']['default'] = read_config_option('path_snmpgetnext');
		}else if (!empty($which_snmpgetnext)) {
			$input['path_snmpgetnext']['default'] = $which_snmpgetnext;
		}else{
			$input['path_snmpgetnext']['default'] = '/usr/local/bin/snmpgetnext';
		}
	}elseif ($config['cacti_server_os'] == 'win32') {
		$which_snmpgetnext = find_best_path('snmpgetnext.exe');

		if (config_value_exists('path_snmpgetnext')) {
			$input['path_snmpgetnext']['default'] = read_config_option('path_snmpgetnext');
		}else if (!empty($which_snmpgetnext)) {
			$input['path_snmpgetnext']['default'] = $which_snmpgetnext;
		}else{
			$input['path_snmpgetnext']['default'] = 'c:/net-snmp/bin/snmpgetnext.exe';
		}
	}

	/* snmptrap Binary Path */
	$input['path_snmptrap'] = $settings['path']['path_snmptrap'];

	if ($config['cacti_server_os'] == 'unix') {
		$which_snmptrap = find_best_path('snmptrap');

		if (config_value_exists('path_snmptrap')) {
			$input['path_snmptrap']['default'] = read_config_option('path_snmptrap');
		}else if (!empty($which_snmptrap)) {
			$input['path_snmptrap']['default'] = $which_snmptrap;
		}else{
			$input['path_snmptrap']['default'] = '/usr/local/bin/snmptrap';
		}
	}elseif ($config['cacti_server_os'] == 'win32') {
		$which_snmptrap = find_best_path('snmptrap.exe');

		if (config_value_exists('path_snmptrap')) {
			$input['path_snmptrap']['default'] = read_config_option('path_snmptrap');
		}else if (!empty($which_snmptrap)) {
			$input['path_snmptrap']['default'] = $which_snmptrap;
		}else{
			$input['path_snmptrap']['default'] = 'c:/net-snmp/bin/snmptrap.exe';
		}
	}

	/* log file path */
	$input['path_cactilog'] = $settings['path']['path_cactilog'];
	$input['path_cactilog']['description'] = 'The path to your Cacti log file.';
	if (config_value_exists('path_cactilog')) {
		$input['path_cactilog']['default'] = read_config_option('path_cactilog');
	} else {
		$input['path_cactilog']['default'] = $config['base_path'] . '/log/cacti.log';
	}

	/* Theme */
	$input['selected_theme'] = $settings['visual']['selected_theme'];
	$input['selected_theme']['description'] = 'Please select one of the available Themes to skin your Cacti with.';
	if (config_value_exists('selected_theme')) {
		$input['selected_theme']['default'] = read_config_option('selected_theme');
	} else {
		$input['selected_theme']['default'] = 'modern';
	}

	/* RRDTool Version */
	if ((file_exists($input['path_rrdtool']['default'])) && (($config['cacti_server_os'] == 'win32') || (is_executable($input['path_rrdtool']['default']))) ) {
		$input['rrdtool_version'] = $settings['general']['rrdtool_version'];

		$out_array = array();

		exec("\"" . $input['path_rrdtool']['default'] . "\"", $out_array);

		if (sizeof($out_array) > 0) {
			if (preg_match('/^RRDtool 1\.5/', $out_array[0])) {
				$input['rrdtool_version']['default'] = 'rrd-1.5.x';
			}else if (preg_match('/^RRDtool 1\.4\./', $out_array[0])) {
				$input['rrdtool_version']['default'] = 'rrd-1.4.x';
			}else if (preg_match('/^RRDtool 1\.3\./', $out_array[0])) {
				$input['rrdtool_version']['default'] = 'rrd-1.3.x';
			}else if (preg_match('/^RRDtool 1\.2\./', $out_array[0])) {
				$input['rrdtool_version']['default'] = 'rrd-1.2.x';
			}else if (preg_match('/^RRDtool 1\.0\./', $out_array[0])) {
				$input['rrdtool_version']['default'] = 'rrd-1.0.x';
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

	$written     = false;
	$newfile     = array();
	$config_file = $config['base_path'] . '/include/config.php';

	$connection = db_connect_real($rdatabase_hostname, $rdatabase_username, $rdatabase_password, $rdatabase_default, $rdatabase_type, $rdatabase_port, $rdatabase_ssl);

	if (is_object($connection)) {
		if (function_exists('gethostname')) {
			$hostname = gethostname();
		}else{
			$hostname = php_uname('n');
		}

		// Check for an existing poller
		$poller_id = db_fetch_cell_prepared('SELECT id FROM poller WHERE hostname = ?', array($hostname), true, $connection);

		if (empty($poller_id)) {
			$save['name'] = 'New Poller';
			$save['hostname']  = $hostname;
			$save['dbdefault'] = $database_default;
			$save['dbhost']    = $database_hostname;
			$save['dbuser']    = $database_username;
			$save['dbpass']    = $database_password;
			$save['dbport']    = $database_port;
			$save['dbssl']     = $database_ssl;

			$poller_id = sql_save($save, 'poller', 'id', TRUE, $connection);
		}

		if (!empty($poller_id)) {
			if (is_writable($config_file)) {
				$file_array = file($config_file);

				if (sizeof($file_array)) {
					foreach($file_array as $line) {
						if (strpos(trim($line), "\$poller_id") !== false) {
							$newfile[] = "\$poller_id = $poller_id;\n";
						}else{
							$newfile[] = $line;
						}
					}

					$fp = fopen($config_file, 'w');
					foreach($newfile as $line) {
						fwrite($fp, $line);
					}
					fclose($fp);

					$written = true;
				}
			}
		}

		db_close($connection);
	}

	return $written;
}
