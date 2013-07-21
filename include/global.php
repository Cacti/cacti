<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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

/*
   !!! IMPORTANT !!!

   The following defaults are not to be altered.  Please refer to
   include/config.php for user configurable database settings.

*/

/* Default database settings*/
$database_type = "mysql";
$database_default = "cacti";
$database_hostname = "localhost";
$database_username = "cactiuser";
$database_password = "cactiuser";
$database_port = "3306";
$database_ssl = false;

/* Default session name - Session name must contain alpha characters */
$cacti_session_name = "Cacti";

/* define default url path */
$url_path = "/cacti/";

/* Include configuration */
include(dirname(__FILE__) . "/config.php");

if (isset($config["cacti_version"])) {
	die("Invalid include/config.php file detected.");
	exit;
}

/* display ALL errors,
 * but suppress deprecated warnings as a workaround until 088 */
if (defined("E_DEPRECATED")) {
	error_reporting(E_ALL ^ E_DEPRECATED);
}else{
	error_reporting(E_ALL);
}

/* Files that do not need http header information - Command line scripts */
$no_http_header_files = array(
	"cmd.php",
	"poller.php",
	"poller_commands.php",
	"script_server.php",
	"query_host_cpu.php",
	"query_host_partitions.php",
	"sql.php",
	"ss_host_cpu.php",
	"ss_host_disk.php",
	"ss_sql.php",
	"add_device.php",
	"add_graphs.php",
	"add_perms.php",
	"add_tree.php",
	"copy_user.php",
	"host_update_template.php",
	"poller_export.php",
	"poller_graphs_reapply_names.php",
	"poller_output_empty.php",
	"poller_reindex_hosts.php",
	"rebuild_poller_cache.php",
	"repair_database.php",
	"structure_rra_paths.php"
);

$config = array();
$colors = array();

/* this should be auto-detected, set it manually if needed */
$config["cacti_server_os"] = (strstr(PHP_OS, "WIN")) ? "win32" : "unix";

/* built-in snmp support */
$config["php_snmp_support"] = function_exists("snmpget");

/* set URL path */
if (! isset($url_path)) {
	$url_path = "";
}
$config['url_path'] = $url_path;
define('URL_PATH', $url_path);

/* used for includes */
if ($config["cacti_server_os"] == "win32") {
	$config["base_path"]    = str_replace("\\", "/", substr(dirname(__FILE__),0,-8));
	$config["library_path"] = $config["base_path"] . "/lib";
}else{
	$config["base_path"]    = preg_replace("/(.*)[\/]include/", "\\1", dirname(__FILE__));
	$config["library_path"] = preg_replace("/(.*[\/])include/", "\\1lib", dirname(__FILE__));
}
$config["include_path"] = dirname(__FILE__);
$config["rra_path"] = $config["base_path"] . '/rra';

/* colors */
$colors["dark_outline"] = "454E53";
$colors["dark_bar"] = "AEB4B7";
$colors["panel"] = "E5E5E5";
$colors["panel_text"] = "000000";
$colors["panel_link"] = "000000";
$colors["light"] = "F5F5F5";
$colors["alternate"] = "E7E9F2";
$colors["panel_dark"] = "C5C5C5";

$colors["header"] = "00438C";
$colors["header_panel"] = "6d88ad";
$colors["header_text"] = "ffffff";
$colors["form_background_dark"] = "E1E1E1";

$colors["form_alternate1"] = "F5F5F5";
$colors["form_alternate2"] = "E5E5E5";

if ((!in_array(basename($_SERVER["PHP_SELF"]), $no_http_header_files, true)) && ($_SERVER["PHP_SELF"] != "")) {
	/* Sanity Check on "Corrupt" PHP_SELF */
	if ($_SERVER["SCRIPT_NAME"] != $_SERVER["PHP_SELF"]) {
		echo "\nInvalid PHP_SELF Path \n";
		exit;
	}

	/* we don't want these pages cached */
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");
	/* prevent IE from silently rejects cookies sent from third party sites. */
	header('P3P: CP="CAO PSA OUR"');

	/* initilize php session */
	session_name($cacti_session_name);
	session_start();

	/* detect and handle get_magic_quotes */
	if (!get_magic_quotes_gpc()) {
		function addslashes_deep($value) {
			$value = is_array($value) ? array_map('addslashes_deep', $value) : addslashes($value);
			return $value;
		}

		$_POST   = array_map('addslashes_deep', $_POST);
		$_GET    = array_map('addslashes_deep', $_GET);
		$_COOKIE = array_map('addslashes_deep', $_COOKIE);
	}

	/* make sure to start only only Cacti session at a time */
	if (!isset($_SESSION["cacti_cwd"])) {
		$_SESSION["cacti_cwd"] = $config["base_path"];
	}else{
		if ($_SESSION["cacti_cwd"] != $config["base_path"]) {
			session_unset();
			session_destroy();
		}
	}
}

/* emulate 'register_globals' = 'off' if turned on */
if ((bool)ini_get("register_globals")) {
	$not_unset = array("_GET", "_POST", "_COOKIE", "_SERVER", "_SESSION", "_ENV", "_FILES", "database_type", "database_default", "database_hostname", "database_username", "database_password", "config", "colors");

	/* Not only will array_merge give a warning if a parameter is not an array, it will
	* actually fail. So we check if HTTP_SESSION_VARS has been initialised. */
	if (!isset($_SESSION)) {
		$_SESSION = array();
	}

	/* Merge all into one extremely huge array; unset this later */
	$input = array_merge($_GET, $_POST, $_COOKIE, $_SERVER, $_SESSION, $_ENV, $_FILES);

	unset($input["input"]);
	unset($input["not_unset"]);

	while (list($var,) = @each($input)) {
		if (!in_array($var, $not_unset)) {
			unset($$var);
		}
	}

	unset($input);
}

/* include base modules */
include_once($config["library_path"] . "/adodb/adodb.inc.php");
include_once($config["library_path"] . "/database.php");

/* connect to the database server */
db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port, $database_ssl);

/* include additional modules */
include_once($config["library_path"] . "/functions.php");
include_once($config["include_path"] . "/global_constants.php");
include_once($config["library_path"] . "/plugins.php");
include_once($config["include_path"] . "/plugins.php");
include_once($config["include_path"] . "/global_arrays.php");
include_once($config["include_path"] . "/global_settings.php");
include_once($config["include_path"] . "/global_form.php");
include_once($config["library_path"] . "/html.php");
include_once($config["library_path"] . "/html_form.php");
include_once($config["library_path"] . "/html_utility.php");
include_once($config["library_path"] . "/html_validate.php");
include_once($config["library_path"] . "/variables.php");
include_once($config["library_path"] . "/auth.php");

api_plugin_hook("config_insert");

/* current cacti version */
$config["cacti_version"] = "0.8.8b";

?>
