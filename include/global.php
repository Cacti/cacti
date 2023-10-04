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

/*
   !!! IMPORTANT !!!

   The following defaults are not to be altered.  Please refer to
   include/config.php for user configurable settings.

*/
global $config;
$config = array();

/* define if cacti is in CLI mode */
define('CACTI_CLI', (php_sapi_name() == 'cli'));
define('CACTI_WEB', (php_sapi_name() != 'cli'));

if (defined('CACTI_CLI_ONLY') && CACTI_WEB) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* this should be auto-detected, set it manually if needed */
$config['cacti_server_os'] = (strstr(PHP_OS, 'WIN')) ? 'win32' : 'unix';
$config['is_web']          = CACTI_WEB;

/* load cacti version from file */
$cacti_version_file = __DIR__ . '/cacti_version';

if (!file_exists($cacti_version_file)) {
	die('ERROR: failed to find cacti version file');
}

$cacti_version = file_get_contents($cacti_version_file, false);

if ($cacti_version === false) {
	die('ERROR: failed to load cacti version file');
}
$cacti_version = trim($cacti_version);

// define documentation table of contents
define('CACTI_DOCUMENTATION_TOC', 'docs/Table-of-Contents.html');

//By default, we assume that it is not
//an AJAX request.
global $is_request_ajax;
$is_request_ajax = false;

//If HTTP_X_REQUESTED_WITH is equal to xmlhttprequest
//We assume this is an ajax call
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
	strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') == 0) {
	$is_request_ajax = true;
}

// NOTE: Cannot use isset_request_var() as that is in lib/html_utility.php
//       which is not included yet!
//if (isset($_REQUEST['headercontent'])) {
//	$is_request_ajax = false;
//}

/* Default database settings*/
$database_type     = 'mysql';
$database_default  = 'cacti';
$database_hostname = 'localhost';
$database_username = 'cactiuser';
$database_password = 'cactiuser';
$database_port     = '3306';
$database_retries  = 2;

$database_ssl        = false;
$database_ssl_key    = '';
$database_ssl_cert   = '';
$database_ssl_ca     = '';
$database_ssl_capath = '';
$database_ssl_verify_server_cert = true;
$database_persist    = true;

/* Default session name - Session name must contain alpha characters */
$cacti_session_name = 'Cacti';

/* define default url path */
$url_path = '/cacti/';

/* disable log rotation setting */
$disable_log_rotation = false;

/* Include configuration, or use the defaults */
if (file_exists(__DIR__ . '/config.php')) {
	if (!is_readable(__DIR__ . '/config.php')) {
		die('Configuration file include/config.php is present, but unreadable.' . PHP_EOL);
	}
	include(__DIR__ . '/config.php');
}

if (isset($config['cacti_version'])) {
	die('Invalid include/config.php file detected.' . PHP_EOL);

	exit;
}

/* Define global paths */
include_once(__DIR__ . '/global_path.php');

/* Should we allow proxy ip headers? */
$config['proxy_headers'] = $proxy_headers ?? array();

/* Set the poller_id */
if (isset($poller_id)) {
	$config['poller_id'] = $poller_id;
} else {
	$config['poller_id'] = 1;
}

$db_var_defaults = array(
	'database_type'       => 'mysql',
	'database_default'    => null,
	'database_hostname'   => null,
	'database_username'   => null,
	'database_password'   => null,
	'database_port'       => '3306',
	'database_retries'    => 2,
	'database_ssl'        => false,
	'database_ssl_key'    => '',
	'database_ssl_cert'   => '',
	'database_ssl_ca'     => '',
        'database_ssl_capath' => '',
        'database_ssl_verify_server_cert' => true,
);

$db_var_prefixes = array('');

if ($config['poller_id'] > 1 || isset($rdatabase_hostname)) {
	$db_var_prefixes[] = 'r';
}

$db_missing_vars = '';

foreach ($db_var_prefixes as $db_var_prefix) {
	foreach ($db_var_defaults as $db_var_name => $db_var_default) {
		$db_var_full = $db_var_prefix . $db_var_name;

		if (!isset($$db_var_full)) {
			if ($db_var_default !== null) {
				$$db_var_full = $db_var_default;
			} else {
				$db_missing_vars .= (($db_missing_vars == '') ? 'missing ' : ', ') . $db_var_full;
			}
		}
	}
}

if (!empty($db_missing_vars)) {
	die("config.php is $db_missing_vars" . PHP_EOL);
}

/* set the local for international users */
setlocale(LC_CTYPE, 'en_US.UTF-8');

$colors = array();

/* required for Windows */
if ($config['cacti_server_os'] == 'win32') {
	putenv('MIB_DIRS=c:/usr/share/snmp/mibs');
}

if (!empty($path_csrf_secret)) {
	$config['path_csrf_secret'] = $path_csrf_secret;
}

/* built-in snmp support */
if ((isset($php_snmp_support) && $php_snmp_support == false) || !function_exists('snmpget')) {
	$config['php_snmp_support'] = false;
} else {
	$config['php_snmp_support'] = class_exists('SNMP');
}

/* PHP binary location */
if (isset($php_path)) {
	$config['php_path'] = $php_path;
}

/* Set various debug fields */
$config['DEBUG_READ_CONFIG_OPTION']         = defined('DEBUG_READ_CONFIG_OPTION');
$config['DEBUG_READ_CONFIG_OPTION_DB_OPEN'] = defined('DEBUG_READ_CONFIG_OPTION_DB_OPEN');
$config['DEBUG_SQL_CMD']                    = defined('DEBUG_SQL_CMD');
$config['DEBUG_SQL_FLOW']                   = defined('DEBUG_SQL_FLOW');

/* check for an empty database port */
if (empty($database_port)) {
	$database_port = '3306';
}

if (isset($input_whitelist)) {
	$config['input_whitelist'] = $input_whitelist;
}

/* define required path as constants */

/* define any additional paths as constants */
foreach ($config as $key => $value) {
	if (substr($key, -5) == '_path') {
		$path_name     = substr($key, 0, -5);
		$constant_name = 'CACTI_PATH_' . strtoupper($path_name);

		if (!defined($constant_name)) {
			define($constant_name, $value);
		}
	}
}

if (isset($i18n_handler)) {
	$config['i18n_language_handler'] = $i18n_handler;
}

if (isset($i18n_force_language)) {
	$config['i18n_force_language'] = $i18n_force_language;
}

if (isset($i18n_log)) {
	$config['i18n_log'] = $i18n_log;
}

if (isset($i18n_text_log)) {
	$config['i18n_text_log'] = $i18n_text_log;
}

/* include base modules */
include_once(CACTI_PATH_LIBRARY . '/database.php');
include_once(CACTI_PATH_LIBRARY . '/functions.php');
include_once(CACTI_PATH_INCLUDE . '/global_constants.php');

define('CACTI_VERSION', format_cacti_version($cacti_version, CACTI_VERSION_FORMAT_SHORT));
define('CACTI_VERSION_FULL', format_cacti_version($cacti_version, CACTI_VERSION_FORMAT_FULL));

include_once(CACTI_PATH_LIBRARY . '/html.php');
include_once(CACTI_PATH_LIBRARY . '/html_utility.php');
include_once(CACTI_PATH_LIBRARY . '/html_validate.php');
include_once(CACTI_PATH_LIBRARY . '/html_filter.php');

$filename = get_current_page();

if (isset($no_http_headers) && $no_http_headers == true) {
	$config['is_web'] = false;
}

if ($config['is_web'] && ini_get('session.auto_start') == 1) {
	print 'FATAL: PHP setting session.auto_start NOT supported.  Disable in your php.ini file and then restart your Web Service' . PHP_EOL;

	exit;
}

/* set poller mode */
global $local_db_cnn_id, $remote_db_cnn_id, $conn_mode;

$config['connection'] = 'online';

$ps = $config['is_web'] ? '<p>' : '';
$sp = $config['is_web'] ? '</p>' : PHP_EOL;
$ul = $config['is_web'] ? '<ul>' : PHP_EOL;
$li = $config['is_web'] ? '<li>' : PHP_EOL . '  - ';
$lu = $config['is_web'] ? '</ul>' : '';
$il = $config['is_web'] ? '</li>' : '';

if ($config['poller_id'] > 1 || isset($rdatabase_hostname)) {
	$local_db_cnn_id = db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port, $database_retries, $database_ssl, $database_ssl_key, $database_ssl_cert, $database_ssl_ca, $database_ssl_capath, $database_ssl_verify_server_cert);

	if (!isset($rdatabase_retries)) {
		$rdatabase_retries  = 2;
	}

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
		$rdatabase_ssl_verify_server_cert = true;
	}

	// Check for recovery
	if (is_object($local_db_cnn_id)) {
		$boost_records = db_fetch_cell('SELECT COUNT(*)
			FROM poller_output_boost', '', true, $local_db_cnn_id);

		if ($boost_records > 0) {
			$config['connection'] = 'recovery';
		}
	}

	/* gather the existing cactidb version */
	$config['cacti_db_version'] = db_fetch_cell('SELECT cacti FROM version LIMIT 1', false, $local_db_cnn_id);

	/**
	 * If we have not been forced offline by the $conn_mode global and since we are
	 * a remote poller, let's attempt to get back online.
	 */
	if ($conn_mode != 'offline') {
		$remote_db_cnn_id = db_connect_real($rdatabase_hostname, $rdatabase_username, $rdatabase_password, $rdatabase_default, $rdatabase_type, $rdatabase_port, $database_retries, $rdatabase_ssl, $rdatabase_ssl_key, $rdatabase_ssl_cert, $rdatabase_ssl_ca, $rdatabase_ssl_capath, $rdatabase_ssl_verify_server_cert);
	}

	if ($config['is_web'] && is_object($remote_db_cnn_id) &&
		$config['connection']       != 'recovery' &&
		$config['cacti_db_version'] != 'new_install') {
		// Connection worked, so now override the default settings so that it will always utilize the remote connection
		$database_default    = $rdatabase_default;
		$database_hostname   = $rdatabase_hostname;
		$database_username   = $rdatabase_username;
		$database_password   = $rdatabase_password;
		$database_port       = $rdatabase_port;
		$database_ssl        = $rdatabase_ssl;
		$database_ssl_key    = $rdatabase_ssl_key;
		$database_ssl_cert   = $rdatabase_ssl_cert;
		$database_ssl_ca     = $rdatabase_ssl_ca;
		$database_ssl_capath = $rdatabase_ssl_capath;
		$database_ssl_verify_server_cert = $rdatabase_verify_server_cert;
	} elseif (is_object($remote_db_cnn_id)) {
		if ($config['connection'] != 'recovery') {
			$config['connection'] = 'online';
		}
	} else {
		$config['connection'] = 'offline';
	}
} else {
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

	if (!db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port, $database_retries, $database_ssl, $database_ssl_key, $database_ssl_cert, $database_ssl_ca, $database_ssl_capath, $database_ssl_verify_server_cert)) {
		print $ps . 'FATAL: Connection to Cacti database failed. Please ensure: ' . $ul;
		print $li . 'the PHP MySQL module is installed and enabled.' . $il;
		print $li . 'the database is running.' . $il;
		print $li . 'the credentials in config.php are valid.' . $il;
		print $lu . $sp;

		if (isset($_REQUEST['display_db_errors']) && !empty($config['DATABASE_ERROR'])) {
			print $ps . 'The following database errors occurred: ' . $ul;

			foreach ($config['DATABASE_ERROR'] as $e) {
				print $li . $e['Code'] . ': ' . $e['Error'] . $il;
			}
			print $lu . $sp;
		}

		exit;
	}

	if (!db_table_exists('settings') || !db_table_exists('version')) {
		print $ps . 'FATAL: Connection to Cacti database succeed but `Settings` table not found. Please ensure: ' . $ul;
		print $li . 'the PHP MySQL module is installed and enabled.' . $il;
		print $li . 'the database is running.' . $il;
		print $li . 'the cacti.sql has been imported.' . $il;
		print $li . 'the credentials in config.php are valid and correct.' . $il;
		print $lu . $sp;

		if (isset($_REQUEST['display_db_errors']) && !empty($config['DATABASE_ERROR'])) {
			print $ps . 'The following database errors occurred: ' . $ul;

			foreach ($config['DATABASE_ERROR'] as $e) {
				print $li . $e['Code'] . ': ' . $e['Error'] . $il;
			}
			print $lu . $sp;
		}

		exit;
	}

	/* gather the existing cactidb version */
	$config['cacti_db_version'] = db_fetch_cell('SELECT cacti FROM version LIMIT 1');
}

/* check cacti log is available */
$log_filename = cacti_log_file();

if (!is_resource_writable($log_filename)) {
	print $ps . 'FATAL: System log file is not available for writing. Please ensure: ' . $ul;
	print $li . 'the log folder is correctly set.' . $il;

	if (CACTI_CLI) {
		print $li . 'the script was run as the website user. ' . $il;
	}
	print $li . 'the log folder is writable by the website user.' . $il;
	print $li . 'there is enough disk space.' . $il;
	print $lu . $sp;

	if (CACTI_CLI) {
		print $ps . 'To run as the website user, use sudo -u <website user> php -q <script file>' . $sp;
	}
	print $ps . 'Log: ' . $log_filename . $sp;
}

/* prime the most popular config settings */
cache_common_config_settings();

if ($config['poller_id'] > 1) {
	$timezone = db_fetch_cell_prepared('SELECT timezone
		FROM poller
		WHERE id = ?',
		array($config['poller_id']));

	if ($timezone != '') {
		db_execute_prepared('SET time_zone = ?', array($timezone));
	}

	if (db_column_exists('poller', 'log_level')) {
		$poller_log_level = db_fetch_cell_prepared('SELECT log_level
			FROM poller
			WHERE id = ?',
			array($config['poller_id']));

		define('POLLER_LOG_LEVEL', $poller_log_level);
	} else {
		define('POLLER_LOG_LEVEL', '-1');
	}
} else {
	define('POLLER_LOG_LEVEL', '-1');
}

if (!defined('IN_CACTI_INSTALL')) {
	set_error_handler('CactiErrorHandler');
	register_shutdown_function('CactiShutdownHandler');
}

/* verify the cacti database is initialized before moving past here */
db_cacti_initialized($config['is_web']);

if ($config['is_web']) {
	if (read_config_option('force_https') == 'on') {
		if (!isset($_SERVER['HTTPS']) && isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
			header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . PHP_EOL . PHP_EOL);

			exit;
		}
	}

	/* set the maximum post size */
	ini_set('post_max_size', '8M');

	/* add additional cookie directives */
	ini_set('session.cookie_httponly', true);
	ini_set('session.cookie_path', CACTI_PATH_URL);
	ini_set('session.use_strict_mode', true);

	$options = array(
		COOKIE_OPTIONS_HTTPONLY => true,
		COOKIE_OPTIONS_PATH     => CACTI_PATH_URL,
		COOKIE_OPTIONS_STRICT   => true
	);

	if (isset($cacti_cookie_domain) && $cacti_cookie_domain != '') {
		ini_set('session.cookie_domain', $cacti_cookie_domain);
		$options[COOKIE_OPTIONS_DOMAIN] = $cacti_cookie_domain;
	}

	// SameSite php7.3+ behavior
	if (version_compare(PHP_VERSION, '7.3', '>=')) {
		ini_set('session.cookie_samesite', 'Strict');
		$options[COOKIE_OPTIONS_SAMESITE] = 'Strict';
	}

	if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
		ini_set('session.cookie_secure', true);
		$options[COOKIE_OPTIONS_SECURE] = true;
	}

	$config[COOKIE_OPTIONS]     = $options;
	$config[CACTI_SESSION_NAME] = $cacti_session_name;

	if (isset($cacti_db_session) && $cacti_db_session && db_table_exists('sessions') && $config['connection'] == 'online') {
		include(__DIR__ . '/session.php');
	} else {
		$cacti_db_session = false;
	}

	/* we don't want these pages cached */
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('X-Frame-Options: SAMEORIGIN');

	/* increased web hardening */
	$script_policy = read_config_option('content_security_policy_script');

	if ($script_policy == 'unsafe-eval') {
		$script_policy = "'$script_policy'";
	} else {
		$script_policy = '';
	}
	$alternates = html_escape(read_config_option('content_security_alternate_sources'));

	header("Content-Security-Policy: default-src *; img-src 'self' https://api.qrserver.com $alternates data: blob:; style-src 'self' 'unsafe-inline' $alternates; script-src 'self' $script_policy 'unsafe-inline' $alternates; frame-ancestors 'self'; worker-src 'self' $alternates;");

	/* prevent IE from silently rejects cookies sent from third party sites. */
	header('P3P: CP="CAO PSA OUR"');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Cache-Control: max-age=31536000');

	cacti_session_start();

	/* make sure to start only Cacti session at a time */
	if (!isset($_SESSION[CACTI_CWD])) {
		$_SESSION[CACTI_CWD] = CACTI_PATH_BASE;
	} else {
		if ($_SESSION[CACTI_CWD] != CACTI_PATH_BASE) {
			cacti_session_destroy();
		}
	}

	/* Sanitize the http referer */
	if (isset($_SERVER['HTTP_REFERER'])) {
		$_SERVER['HTTP_REFERER'] = sanitize_uri($_SERVER['HTTP_REFERER']);
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
		if (!in_array($var, $not_unset, true)) {
			unset($$var);
		}
	}

	unset($input);
}

define('CACTI_DATE_TIME_FORMAT', date_time_format());

include_once(CACTI_PATH_INCLUDE . '/global_languages.php');

define('CACTI_VERSION_BRIEF', get_cacti_version_text(false,CACTI_VERSION));
define('CACTI_VERSION_BRIEF_FULL', get_cacti_version_text(false,CACTI_VERSION_FULL));
define('CACTI_VERSION_TEXT', get_cacti_version_text(true,CACTI_VERSION));
define('CACTI_VERSION_TEXT_FULL', get_cacti_version_text(true,CACTI_VERSION_FULL));
define('CACTI_VERSION_TEXT_CLI', get_cacti_cli_version(true,CACTI_VERSION_FULL));

include_once(CACTI_PATH_LIBRARY . '/auth.php');
include_once(CACTI_PATH_LIBRARY . '/plugins.php');
include_once(CACTI_PATH_INCLUDE . '/plugins.php');
include_once(CACTI_PATH_INCLUDE . '/global_arrays.php');
include_once(CACTI_PATH_INCLUDE . '/global_settings.php');
include_once(CACTI_PATH_INCLUDE . '/global_form.php');
include_once(CACTI_PATH_LIBRARY . '/html_form.php');
include_once(CACTI_PATH_LIBRARY . '/html_filter.php');
include_once(CACTI_PATH_LIBRARY . '/variables.php');
include_once(CACTI_PATH_LIBRARY . '/mib_cache.php');
include_once(CACTI_PATH_LIBRARY . '/poller.php');
include_once(CACTI_PATH_LIBRARY . '/snmpagent.php');
include_once(CACTI_PATH_LIBRARY . '/aggregate.php');
include_once(CACTI_PATH_LIBRARY . '/api_automation.php');

if ($config['is_web']) {
	include_once(CACTI_PATH_INCLUDE . '/csrf.php');

	/* raise a message and perform a page refresh if we've changed modes */
	if ($config['poller_id'] > 1) {
		if (isset($_SESSION['connection_mode'])) {
			$previous_mode = $_SESSION['connection_mode'];
			$reload        = false;

			cacti_log('Connection: ' . $config['connection'] . ', Previous Mode: ' . $previous_mode . ', Page: ' . $_SERVER['SCRIPT_NAME'], false, 'WEBUI', POLLER_VERBOSITY_DEBUG);

			if ($config['connection'] == 'online' && ($config['connection'] != $previous_mode)) {
				$reload  = true;
				$message = __('The Main Data Collector has returned to an Online Status');
				$level   = MESSAGE_LEVEL_INFO;
			} elseif ($config['connection'] != 'online' && $previous_mode == 'online') {
				$reload  = true;
				$message = __('The Main Data Collector has gone to an Offline or Recovering Status');
				$level   = MESSAGE_LEVEL_ERROR;
			}

			if ($reload) {
				$_SESSION['connection_mode'] = $config['connection'];

				raise_message('connection_state', $message, $level);

				session_destroy();

				print '<div style="display:none">cactiRemoteState</div>';

				exit;
			}
		} else {
			cacti_log('Connection: ' . $config['connection'] . ', Previous Mode: notset', false, 'WEBUI', POLLER_VERBOSITY_DEBUG);

			$previous_mode = $config['connection'];

			$_SESSION['connection_mode'] = $config['connection'];
		}
	}

	if (isset_request_var('newtheme')) {
		$newtheme    =get_nfilter_request_var('newtheme');
		$newtheme_css=__DIR__ . "/themes/$newtheme/main.css";

		if (is_valid_theme($newtheme)) {
			set_config_option('selected_theme', $newtheme);
			$_SESSION['selected_theme'] = $newtheme;
		} else {
			unset($_SESSION['selected_theme']);
		}
	}

	if (isset_request_var('csrf_timeout')) {
		raise_message('csrf_ptimeout');
	}

	/* check for save actions using GET */
	if (isset_request_var('action')) {
		$action = get_nfilter_request_var('action');

		$bad_actions = array('save', 'update_data', 'changepassword');

		foreach ($bad_actions as $bad) {
			if ($action == $bad && !isset($_POST['__csrf_magic'])) {
				cacti_log('WARNING: Attempt to use GET method for POST operations from IP ' . get_client_addr(), false, 'WEBUI');

				exit;
			}
		}
	}

	if (isset($_COOKIE['CactiTimeZone'])) {
		$gmt_offset = $_COOKIE['CactiTimeZone'];

		cacti_time_zone_set($gmt_offset);
	}
}

api_plugin_hook('config_insert');

/* set config cacti_version for plugins */
$config['cacti_version'] = CACTI_VERSION;
