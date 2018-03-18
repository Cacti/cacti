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

/*
   !!! IMPORTANT !!!

   The following defaults are not to be altered.  Please refer to
   include/config.php for user configurable settings.

*/

/* load cacti version from file */
$cacti_version_file = dirname(__FILE__) . '/cacti_version';

if (! file_exists($cacti_version_file)) {
	die ('ERROR: failed to find cacti version file');
}

$cacti_version = file_get_contents($cacti_version_file, false);
if ($cacti_version === false) {
	die ('ERROR: failed to load cacti version file');
}
$cacti_version = trim($cacti_version);

/* define cacti version */
define('CACTI_VERSION', $cacti_version);

/* Default database settings*/
$database_type = 'mysql';
$database_default = 'cacti';
$database_hostname = 'localhost';
$database_username = 'cactiuser';
$database_password = 'cactiuser';
$database_port = '3306';
$database_ssl = false;

/* Default session name - Session name must contain alpha characters */
$cacti_session_name = 'Cacti';

/* define default url path */
$url_path = '/cacti/';

/* allow upto 5000 items to be selected */
ini_set('max_input_vars', '5000');

/* Include configuration, or use the defaults */
if (file_exists(dirname(__FILE__) . '/config.php')) {
	include(dirname(__FILE__) . '/config.php');
}

if (isset($config['cacti_version'])) {
	die('Invalid include/config.php file detected.');
	exit;
}

/* set the local for international users */
setlocale(LC_CTYPE, 'en_US.UTF-8');

/* Files that do not need http header information - Command line scripts */
$no_http_header_files = array(
	'cmd.php',
	'poller.php',
	'poller_commands.php',
	'script_server.php',
	'query_host_cpu.php',
	'query_host_partitions.php',
	'sql.php',
	'ss_host_cpu.php',
	'ss_host_disk.php',
	'ss_sql.php',
	'add_device.php',
	'add_graphs.php',
	'add_perms.php',
	'add_tree.php',
	'copy_user.php',
	'host_update_template.php',
	'poller_export.php',
	'poller_graphs_reapply_names.php',
	'poller_output_empty.php',
	'poller_reindex_hosts.php',
	'rebuild_poller_cache.php',
	'repair_database.php',
	'structure_rra_paths.php'
);

$config = array();
$colors = array();

/* this should be auto-detected, set it manually if needed */
$config['cacti_server_os'] = (strstr(PHP_OS, 'WIN')) ? 'win32' : 'unix';

/* built-in snmp support */
$config['php_snmp_support'] = function_exists('snmpget');

/* Set the poller_id */
if (isset($poller_id)) {
	$config['poller_id'] = $poller_id;
} else {
	$config['poller_id'] = 1;
}

/* check for an empty database port */
if (empty($database_port)) {
	$database_port = '3306';
}

/* set URL path */
if (! isset($url_path)) {
	$url_path = '';
}
$config['url_path'] = $url_path;
define('URL_PATH', $url_path);

/* used for includes */
if ($config['cacti_server_os'] == 'win32') {
	$config['base_path']    = str_replace("\\", "/", substr(dirname(__FILE__),0,-8));
	$config['library_path'] = $config['base_path'] . '/lib';
} else {
	$config['base_path']    = preg_replace("/(.*)[\/]include/", "\\1", dirname(__FILE__));
	$config['library_path'] = preg_replace("/(.*[\/])include/", "\\1lib", dirname(__FILE__));
}
$config['include_path'] = dirname(__FILE__);
$config['rra_path'] = $config['base_path'] . '/rra';

/* for multiple pollers, we need to know this location */
if (!isset($scripts_path)) {
	$config['scripts_path'] = $config['base_path'] . '/scripts';
} else {
	$config['scripts_path'] = $scripts_path;
}

if (!isset($resource_path)) {
	$config['resource_path'] = $config['base_path'] . '/resource';
} else {
	$config['resource_path'] = $resource_path;
}

/* colors */
$colors['dark_outline'] = '454E53';
$colors['dark_bar'] = 'AEB4B7';
$colors['panel'] = 'E5E5E5';
$colors['panel_text'] = '000000';
$colors['panel_link'] = '000000';
$colors['light'] = 'F5F5F5';
$colors['alternate'] = 'E7E9F2';
$colors['panel_dark'] = 'C5C5C5';

$colors['header'] = '00438C';
$colors['header_panel'] = '6d88ad';
$colors['header_text'] = 'ffffff';
$colors['form_background_dark'] = 'E1E1E1';

$colors['form_alternate1'] = 'F5F5F5';
$colors['form_alternate2'] = 'E5E5E5';

/* include base modules */
include_once($config['library_path'] . '/database.php');
include_once($config['library_path'] . '/functions.php');
include_once($config['include_path'] . '/global_constants.php');

$filename = get_current_page();

$config['is_web'] = true;
if ((isset($no_http_headers) && $no_http_headers == true) || in_array($filename, $no_http_header_files, true)) {
	$config['is_web'] = false;
}

/* set poller mode */
global $local_db_cnn_id, $remote_db_cnn_id;

$config['connection'] = 'online';
if ($config['poller_id'] > 1 || isset($rdatabase_hostname)) {
	$local_db_cnn_id = db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port, $database_ssl);

	if (!isset($rdatabase_ssl)) $rdatabase_ssl = false;

	/* gather the existing cactidb version */
	$config['cacti_db_version'] = db_fetch_cell('SELECT cacti FROM version LIMIT 1', false, $local_db_cnn_id);

	// We are a remote poller also try to connect to the remote database
	$remote_db_cnn_id = db_connect_real($rdatabase_hostname, $rdatabase_username, $rdatabase_password, $rdatabase_default, $rdatabase_type, $rdatabase_port, $rdatabase_ssl);

	if ($remote_db_cnn_id && $config['connection'] != 'recovery' && $config['cacti_db_version'] != 'new_install') {
		// Connection worked, so now override the default settings so that it will always utilize the remote connection
		$database_default   = $rdatabase_default;
		$database_hostname  = $rdatabase_hostname;
		$database_username  = $rdatabase_username;
		$database_password  = $rdatabase_password;
		$database_port      = $rdatabase_port;
		$database_ssl       = $rdatabase_ssl;

		$config['connection'] = 'online';
	} else {
		$config['connection'] = 'offline';
	}
} elseif (!db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port, $database_ssl)) {
	print $config['is_web'] ? '<p>':'';
	print 'FATAL: Connection to Cacti database failed. Please ensure the database is running and your credentials in config.php are valid.';
	print $config['is_web'] ? '</p>':'';
	exit;
} else {
	/* gather the existing cactidb version */
	$config['cacti_db_version'] = db_fetch_cell('SELECT cacti FROM version LIMIT 1');
}

if ($config['poller_id'] > 1 && $config['connection'] == 'online') {
	$boost_records = db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost', '', true, $local_db_cnn_id);
	if ($boost_records > 0) {
		$config['connection'] = 'recovery';
	}
}

if (isset($cacti_db_session) && $cacti_db_session && db_table_exists('sessions')) {
	include(dirname(__FILE__) . '/session.php');
} else {
	$cacti_db_session = false;
}

if (!defined('IN_CACTI_INSTALL')) {
	set_error_handler('CactiErrorHandler');
	register_shutdown_function('CactiShutdownHandler');
}

/* verify the cacti database is initialized before moving past here */
db_cacti_initialized($config['is_web']);

if ($config['is_web']) {
	/* set the maximum post size */
	ini_set('post_max_size', '8M');
	ini_set('max_input_vars', '5000');
	ini_set('session.cookie_httponly', '1');

	/* we don't want these pages cached */
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Cache-Control: post-check=0, pre-check=0', false);
	header('Pragma: no-cache');
	header('X-Frame-Options: SAMEORIGIN');
	/* prevent IE from silently rejects cookies sent from third party sites. */
	header('P3P: CP="CAO PSA OUR"');

	/* initialize php session */
	session_name($cacti_session_name);
	if (!session_id()) session_start();

	/* we never run with magic quotes on */
	if (get_magic_quotes_gpc()) {
		$process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
		foreach ($process as $key => $val) {
			foreach ($val as $k => $v) {
				unset($process[$key][$k]);
				if (is_array($v)) {
					$process[$key][stripslashes($k)] = $v;
					$process[] = &$process[$key][stripslashes($k)];
				} else {
					$process[$key][stripslashes($k)] = stripslashes($v);
				}
			}
		}
		unset($process);
	}

	/* make sure to start only only Cacti session at a time */
	if (!isset($_SESSION['cacti_cwd'])) {
		$_SESSION['cacti_cwd'] = $config['base_path'];
	} else {
		if ($_SESSION['cacti_cwd'] != $config['base_path']) {
			session_unset();
			session_destroy();
		}
	}
}

/* emulate 'register_globals' = 'off' if turned on */
if ((bool)ini_get('register_globals')) {
	$not_unset = array('_GET', '_POST', '_COOKIE', '_SERVER', '_SESSION', '_ENV', '_FILES', 'database_type', 'database_default', 'database_hostname', 'database_username', 'database_password', 'config', 'colors');

	/* Not only will array_merge give a warning if a parameter is not an array, it will
	* actually fail. So we check if HTTP_SESSION_VARS has been initialised. */
	if (!isset($_SESSION)) {
		$_SESSION = array();
	}

	/* Merge all into one extremely huge array; unset this later */
	$input = array_merge($_GET, $_POST, $_COOKIE, $_SERVER, $_SESSION, $_ENV, $_FILES);

	unset($input['input']);
	unset($input['not_unset']);

	foreach ($input as $var => $val) {
		if (!in_array($var, $not_unset)) {
			unset($$var);
		}
	}

	unset($input);
}

define('CACTI_DATE_TIME_FORMAT', date_time_format());

include_once($config['include_path'] . '/global_languages.php');
include_once($config['library_path'] . '/auth.php');
include_once($config['library_path'] . '/plugins.php');
include_once($config['include_path'] . '/plugins.php');
include_once($config['library_path'] . '/html_validate.php');
include_once($config['library_path'] . '/html_utility.php');
include_once($config['include_path'] . '/global_arrays.php');
include_once($config['include_path'] . '/global_settings.php');
include_once($config['include_path'] . '/global_form.php');
include_once($config['library_path'] . '/html.php');
include_once($config['library_path'] . '/html_form.php');
include_once($config['library_path'] . '/html_filter.php');
include_once($config['library_path'] . '/variables.php');
include_once($config['library_path'] . '/mib_cache.php');
include_once($config['library_path'] . '/snmpagent.php');
include_once($config['library_path'] . '/aggregate.php');
include_once($config['library_path'] . '/api_automation.php');

/* cross site request forgery library */
if ($config['is_web']) {
	function csrf_startup() {
		global $config;
		csrf_conf('rewrite-js', $config['url_path'] . 'include/csrf/csrf-magic.js');
	}
	include_once($config['include_path'] . '/csrf/csrf-magic.php');

	if (isset_request_var('newtheme')) {
		unset($_SESSION['selected_theme']);
	}

	if (read_config_option('force_https') == 'on') {
		if (!isset($_SERVER['HTTPS']) && isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
			Header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "\n\n");
			exit;
		}
	}
}

api_plugin_hook('config_insert');

/* set config cacti_version for plugins */
$config['cacti_version'] = CACTI_VERSION;;

