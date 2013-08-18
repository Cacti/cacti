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

include("../include/global.php");

/* allow the upgrade script to run for as long as it needs to */
ini_set("max_execution_time", "0");

/* verify all required php extensions */
if (!verify_php_extensions()) {exit;}

$cacti_versions = array("0.8", "0.8.1", "0.8.2", "0.8.2a", "0.8.3", "0.8.3a", "0.8.4", "0.8.5", "0.8.5a",
	"0.8.6", "0.8.6a", "0.8.6b", "0.8.6c", "0.8.6d", "0.8.6e", "0.8.6f", "0.8.6g", "0.8.6h", "0.8.6i", "0.8.6j", "0.8.6k",
	"0.8.7", "0.8.7a", "0.8.7b", "0.8.7c", "0.8.7d", "0.8.7e", "0.8.7f", "0.8.7g", "0.8.7h", "0.8.7i",
	"0.8.8", "0.8.8a", "0.8.8b");

$old_cacti_version = db_fetch_cell("select cacti from version");

/* try to find current (old) version in the array */
$old_version_index = array_search($old_cacti_version, $cacti_versions);

/* do a version check */
if ($old_cacti_version == $config["cacti_version"]) {
	print "	<p style='font-family: Verdana, Arial; font-size: 16px; font-weight: bold; color: red;'>Error</p>
		<p style='font-family: Verdana, Arial; font-size: 12px;'>This installation is already up-to-date. Click <a href='../index.php'>here</a> to use cacti.</p>";
	exit;
}elseif (preg_match("/^0\.6/", $old_cacti_version)) {
	print "	<p style='font-family: Verdana, Arial; font-size: 16px; font-weight: bold; color: red;'>Error</p>
		<p style='font-family: Verdana, Arial; font-size: 12px;'>You are attempting to install cacti " . $config["cacti_version"] . "
		onto a 0.6.x database. To continue, you must create a new database, import 'cacti.sql' into it, and
		update 'include/config.php' to point to the new database.</p>";
	exit;
}elseif (empty($old_cacti_version)) {
	print "	<p style='font-family: Verdana, Arial; font-size: 16px; font-weight: bold; color: red;'>Error</p>
		<p style='font-family: Verdana, Arial; font-size: 12px;'>You have created a new database, but have not yet imported
		the 'cacti.sql' file. At the command line, execute the following to continue:</p>
		<p><pre>mysql -u $database_username -p $database_default < cacti.sql</pre></p>
		<p>This error may also be generated if the cacti database user does not have correct permissions on the cacti database.
		Please ensure that the cacti database user has the ability to SELECT, INSERT, DELETE, UPDATE, CREATE, ALTER, DROP, INDEX
		on the cacti database.</p>";
	exit;
}

function verify_php_extensions() {
	$extensions = array("session", "sockets", "mysql", "xml");
	$ok = true;
	$missing_extension = "	<p style='font-family: Verdana, Arial; font-size: 16px; font-weight: bold; color: red;'>Error</p>
							<p style='font-family: Verdana, Arial; font-size: 12px;'>The following PHP extensions are missing:</p><ul>";
	foreach ($extensions as $extension) {
		if (!extension_loaded($extension)){
			$ok = false;
			$missing_extension .= "<li style='font-family: Verdana, Arial; font-size: 12px;'>$extension</li>";
		}
	}
	if (!$ok) {
		print $missing_extension . "</ul><p style='font-family: Verdana, Arial; font-size: 12px;'>Please install those PHP extensions and retry</p>";
	}
	return $ok;
}

function db_install_execute($cacti_version, $sql) {
	$sql_install_cache = (isset($_SESSION["sess_sql_install_cache"]) ? $_SESSION["sess_sql_install_cache"] : array());

	if (db_execute($sql)) {
		$sql_install_cache{sizeof($sql_install_cache)}[$cacti_version][1] = $sql;
	}else{
		$sql_install_cache{sizeof($sql_install_cache)}[$cacti_version][0] = $sql;
	}

	$_SESSION["sess_sql_install_cache"] = $sql_install_cache;
}

function find_best_path($binary_name) {
	global $config;
	if ($config["cacti_server_os"] == "win32") {
		$search_paths = array("c:/usr/bin", "c:/cacti", "c:/rrdtool", "c:/spine", "c:/php", "c:/progra~1/php", "c:/net-snmp/bin", "c:/progra~1/net-snmp/bin", "d:/usr/bin", "d:/net-snmp/bin", "d:/progra~1/net-snmp/bin", "d:/cacti", "d:/rrdtool", "d:/spine", "d:/php", "d:/progra~1/php");
	}else{
		$search_paths = array("/bin", "/sbin", "/usr/bin", "/usr/sbin", "/usr/local/bin", "/usr/local/sbin");
	}

	for ($i=0; $i<count($search_paths); $i++) {
		if ((file_exists($search_paths[$i] . "/" . $binary_name)) && (is_readable($search_paths[$i] . "/" . $binary_name))) {
			return $search_paths[$i] . "/" . $binary_name;
		}
	}
}

/* Here, we define each name, default value, type, and path check for each value
we want the user to input. The "name" field must exist in the 'settings' table for
this to work. Cacti also uses different default values depending on what OS it is
running on. */

/* RRDTool Binary Path */
$input["path_rrdtool"] = $settings["path"]["path_rrdtool"];

if ($config["cacti_server_os"] == "unix") {
	$which_rrdtool = find_best_path("rrdtool");

	if (config_value_exists("path_rrdtool")) {
		$input["path_rrdtool"]["default"] = read_config_option("path_rrdtool");
	}else if (!empty($which_rrdtool)) {
		$input["path_rrdtool"]["default"] = $which_rrdtool;
	}else{
		$input["path_rrdtool"]["default"] = "/usr/local/bin/rrdtool";
	}
}elseif ($config["cacti_server_os"] == "win32") {
	$which_rrdtool = find_best_path("rrdtool.exe");

	if (config_value_exists("path_rrdtool")) {
		$input["path_rrdtool"]["default"] = read_config_option("path_rrdtool");
	}else if (!empty($which_rrdtool)) {
		$input["path_rrdtool"]["default"] = $which_rrdtool;
	}else{
		$input["path_rrdtool"]["default"] = "c:/rrdtool/rrdtool.exe";
	}
}

/* PHP Binary Path */
$input["path_php_binary"] = $settings["path"]["path_php_binary"];

if ($config["cacti_server_os"] == "unix") {
	$which_php = find_best_path("php");

	if (config_value_exists("path_php_binary")) {
		$input["path_php_binary"]["default"] = read_config_option("path_php_binary");
	}else if (!empty($which_php)) {
		$input["path_php_binary"]["default"] = $which_php;
	}else{
		$input["path_php_binary"]["default"] = "/usr/bin/php";
	}
}elseif ($config["cacti_server_os"] == "win32") {
	$which_php = find_best_path("php.exe");

	if (config_value_exists("path_php_binary")) {
		$input["path_php_binary"]["default"] = read_config_option("path_php_binary");
	}else if (!empty($which_php)) {
		$input["path_php_binary"]["default"] = $which_php;
	}else{
		$input["path_php_binary"]["default"] = "c:/php/php.exe";
	}
}

/* snmpwalk Binary Path */
$input["path_snmpwalk"] = $settings["path"]["path_snmpwalk"];

if ($config["cacti_server_os"] == "unix") {
	$which_snmpwalk = find_best_path("snmpwalk");

	if (config_value_exists("path_snmpwalk")) {
		$input["path_snmpwalk"]["default"] = read_config_option("path_snmpwalk");
	}else if (!empty($which_snmpwalk)) {
		$input["path_snmpwalk"]["default"] = $which_snmpwalk;
	}else{
		$input["path_snmpwalk"]["default"] = "/usr/local/bin/snmpwalk";
	}
}elseif ($config["cacti_server_os"] == "win32") {
	$which_snmpwalk = find_best_path("snmpwalk.exe");

	if (config_value_exists("path_snmpwalk")) {
		$input["path_snmpwalk"]["default"] = read_config_option("path_snmpwalk");
	}else if (!empty($which_snmpwalk)) {
		$input["path_snmpwalk"]["default"] = $which_snmpwalk;
	}else{
		$input["path_snmpwalk"]["default"] = "c:/net-snmp/bin/snmpwalk.exe";
	}
}

/* snmpget Binary Path */
$input["path_snmpget"] = $settings["path"]["path_snmpget"];

if ($config["cacti_server_os"] == "unix") {
	$which_snmpget = find_best_path("snmpget");

	if (config_value_exists("path_snmpget")) {
		$input["path_snmpget"]["default"] = read_config_option("path_snmpget");
	}else if (!empty($which_snmpget)) {
		$input["path_snmpget"]["default"] = $which_snmpget;
	}else{
		$input["path_snmpget"]["default"] = "/usr/local/bin/snmpget";
	}
}elseif ($config["cacti_server_os"] == "win32") {
	$which_snmpget = find_best_path("snmpget.exe");

	if (config_value_exists("path_snmpget")) {
		$input["path_snmpget"]["default"] = read_config_option("path_snmpget");
	}else if (!empty($which_snmpget)) {
		$input["path_snmpget"]["default"] = $which_snmpget;
	}else{
		$input["path_snmpget"]["default"] = "c:/net-snmp/bin/snmpget.exe";
	}
}

/* snmpbulkwalk Binary Path */
$input["path_snmpbulkwalk"] = $settings["path"]["path_snmpbulkwalk"];

if ($config["cacti_server_os"] == "unix") {
	$which_snmpbulkwalk = find_best_path("snmpbulkwalk");

	if (config_value_exists("path_snmpbulkwalk")) {
		$input["path_snmpbulkwalk"]["default"] = read_config_option("path_snmpbulkwalk");
	}else if (!empty($which_snmpbulkwalk)) {
		$input["path_snmpbulkwalk"]["default"] = $which_snmpbulkwalk;
	}else{
		$input["path_snmpbulkwalk"]["default"] = "/usr/local/bin/snmpbulkwalk";
	}
}elseif ($config["cacti_server_os"] == "win32") {
	$which_snmpbulkwalk = find_best_path("snmpbulkwalk.exe");

	if (config_value_exists("path_snmpbulkwalk")) {
		$input["path_snmpbulkwalk"]["default"] = read_config_option("path_snmpbulkwalk");
	}else if (!empty($which_snmpbulkwalk)) {
		$input["path_snmpbulkwalk"]["default"] = $which_snmpbulkwalk;
	}else{
		$input["path_snmpbulkwalk"]["default"] = "c:/net-snmp/bin/snmpbulkwalk.exe";
	}
}

/* snmpgetnext Binary Path */
$input["path_snmpgetnext"] = $settings["path"]["path_snmpgetnext"];

if ($config["cacti_server_os"] == "unix") {
	$which_snmpgetnext = find_best_path("snmpgetnext");

	if (config_value_exists("path_snmpgetnext")) {
		$input["path_snmpgetnext"]["default"] = read_config_option("path_snmpgetnext");
	}else if (!empty($which_snmpgetnext)) {
		$input["path_snmpgetnext"]["default"] = $which_snmpgetnext;
	}else{
		$input["path_snmpgetnext"]["default"] = "/usr/local/bin/snmpgetnext";
	}
}elseif ($config["cacti_server_os"] == "win32") {
	$which_snmpgetnext = find_best_path("snmpgetnext.exe");

	if (config_value_exists("path_snmpgetnext")) {
		$input["path_snmpgetnext"]["default"] = read_config_option("path_snmpgetnext");
	}else if (!empty($which_snmpgetnext)) {
		$input["path_snmpgetnext"]["default"] = $which_snmpgetnext;
	}else{
		$input["path_snmpgetnext"]["default"] = "c:/net-snmp/bin/snmpgetnext.exe";
	}
}

/* log file path */
$input["path_cactilog"] = $settings["path"]["path_cactilog"];
$input["path_cactilog"]["description"] = "The path to your Cacti log file.";
if (config_value_exists("path_cactilog")) {
	$input["path_cactilog"]["default"] = read_config_option("path_cactilog");
} else {
	$input["path_cactilog"]["default"] = $config["base_path"] . "/log/cacti.log";
}

/* SNMP Version */
if ($config["cacti_server_os"] == "unix") {
	$input["snmp_version"] = $settings["general"]["snmp_version"];
	$input["snmp_version"]["default"] = "net-snmp";
}

/* RRDTool Version */
if ((file_exists($input["path_rrdtool"]["default"])) && (($config["cacti_server_os"] == "win32") || (is_executable($input["path_rrdtool"]["default"]))) ) {
	$input["rrdtool_version"] = $settings["general"]["rrdtool_version"];

	$out_array = array();

	exec("\"" . $input["path_rrdtool"]["default"] . "\"", $out_array);

	if (sizeof($out_array) > 0) {
		if (preg_match("/^RRDtool 1\.4/", $out_array[0])) {
			$input["rrdtool_version"]["default"] = "rrd-1.4.x";
		}else if (preg_match("/^RRDtool 1\.3\./", $out_array[0])) {
			$input["rrdtool_version"]["default"] = "rrd-1.3.x";
		}else if (preg_match("/^RRDtool 1\.2\./", $out_array[0])) {
			$input["rrdtool_version"]["default"] = "rrd-1.2.x";
		}else if (preg_match("/^RRDtool 1\.0\./", $out_array[0])) {
			$input["rrdtool_version"]["default"] = "rrd-1.0.x";
		}
	}
}

/* default value for this variable */
if (!isset($_REQUEST["install_type"])) {
	$_REQUEST["install_type"] = 0;
}

/* defaults for the install type dropdown */
if ($old_cacti_version == "new_install") {
	$default_install_type = "1";
}else{
	$default_install_type = "3";
}

/* pre-processing that needs to be done for each step */
if (isset($_REQUEST["step"]) && $_REQUEST["step"] > 0) {
	$step = intval($_REQUEST["step"]);
	if ($step == "1") {
		$step = "2";
	} elseif (($step == "2") && ($_REQUEST["install_type"] == "1")) {
		$step = "3";
	} elseif (($step == "2") && ($_REQUEST["install_type"] == "3")) {
		$step = "8";
	} elseif (($step == "8") && ($old_version_index <= array_search("0.8.5a", $cacti_versions))) {
		$step = "9";
	} elseif ($step == "8") {
		$step = "3";
	} elseif ($step == "9") {
		$step = "3";
	} elseif ($step == "3") {
		$step = "4";
	}
} else {
	$step = 1;
}

if ($step == "4") {
	include_once("../lib/data_query.php");
	include_once("../lib/utility.php");

	$i = 0;

	/* get all items on the form and write values for them  */
	while (list($name, $array) = each($input)) {
		if (isset($_POST[$name])) {
			db_execute("replace into settings (name,value) values ('$name','" . $_POST[$name] . "')");
		}
	}

	setcookie(session_name(),"",time() - 3600,"/");

	kill_session_var("sess_config_array");
	kill_session_var("sess_host_cache_array");

	/* pre-fill poller cache with initial data on a new install only */
	if ($old_cacti_version == "new_install") {
		/* just in case we have hard drive graphs to deal with */
		$host_id = db_fetch_cell("select id from host where hostname='127.0.0.1'");

		if (!empty($host_id)) {
			run_data_query($host_id, 6);
		}

		/* it's always a good idea to re-populate the poller cache to make sure everything is refreshed and up-to-date */ 	 
		repopulate_poller_cache(); 	 
	}
	
	db_execute("delete from version");
	db_execute("insert into version (cacti) values ('" . $config["cacti_version"] . "')");

	header ("Location: ../index.php");
	exit;
}elseif (($step == "8") && ($_REQUEST["install_type"] == "3")) {
	/* if the version is not found, die */
	if (!is_int($old_version_index)) {
		print "	<p style='font-family: Verdana, Arial; font-size: 16px; font-weight: bold; color: red;'>Error</p>
			<p style='font-family: Verdana, Arial; font-size: 12px;'>Invalid Cacti version
			<strong>$old_cacti_version</strong>, cannot upgrade to <strong>" . $config["cacti_version"] . "
			</strong></p>";
		exit;
	}

	/* loop from the old version to the current, performing updates for each version in between */
	for ($i=($old_version_index+1); $i<count($cacti_versions); $i++) {
		if ($cacti_versions[$i] == "0.8.1") {
			include ("0_8_to_0_8_1.php");
			upgrade_to_0_8_1();
		}elseif ($cacti_versions[$i] == "0.8.2") {
			include ("0_8_1_to_0_8_2.php");
			upgrade_to_0_8_2();
		}elseif ($cacti_versions[$i] == "0.8.2a") {
			include ("0_8_2_to_0_8_2a.php");
			upgrade_to_0_8_2a();
		}elseif ($cacti_versions[$i] == "0.8.3") {
			include ("0_8_2a_to_0_8_3.php");
			include_once("../lib/utility.php");
			upgrade_to_0_8_3();
		}elseif ($cacti_versions[$i] == "0.8.4") {
			include ("0_8_3_to_0_8_4.php");
			upgrade_to_0_8_4();
		}elseif ($cacti_versions[$i] == "0.8.5") {
			include ("0_8_4_to_0_8_5.php");
			upgrade_to_0_8_5();
		}elseif ($cacti_versions[$i] == "0.8.6") {
			include ("0_8_5a_to_0_8_6.php");
			upgrade_to_0_8_6();
		}elseif ($cacti_versions[$i] == "0.8.6a") {
			include ("0_8_6_to_0_8_6a.php");
			upgrade_to_0_8_6a();
		}elseif ($cacti_versions[$i] == "0.8.6d") {
			include ("0_8_6c_to_0_8_6d.php");
			upgrade_to_0_8_6d();
		}elseif ($cacti_versions[$i] == "0.8.6e") {
			include ("0_8_6d_to_0_8_6e.php");
			upgrade_to_0_8_6e();
		}elseif ($cacti_versions[$i] == "0.8.6g") {
			include ("0_8_6f_to_0_8_6g.php");
			upgrade_to_0_8_6g();
		}elseif ($cacti_versions[$i] == "0.8.6h") {
			include ("0_8_6g_to_0_8_6h.php");
			upgrade_to_0_8_6h();
		}elseif ($cacti_versions[$i] == "0.8.6i") {
			include ("0_8_6h_to_0_8_6i.php");
			upgrade_to_0_8_6i();
		}elseif ($cacti_versions[$i] == "0.8.7") {
			include ("0_8_6j_to_0_8_7.php");
			upgrade_to_0_8_7();
		}elseif ($cacti_versions[$i] == "0.8.7a") {
			include ("0_8_7_to_0_8_7a.php");
			upgrade_to_0_8_7a();
		}elseif ($cacti_versions[$i] == "0.8.7b") {
			include ("0_8_7a_to_0_8_7b.php");
			upgrade_to_0_8_7b();
		}elseif ($cacti_versions[$i] == "0.8.7c") {
			include ("0_8_7b_to_0_8_7c.php");
			upgrade_to_0_8_7c();
		}elseif ($cacti_versions[$i] == "0.8.7d") {
			include ("0_8_7c_to_0_8_7d.php");
			upgrade_to_0_8_7d();
		}elseif ($cacti_versions[$i] == "0.8.7e") {
			include ("0_8_7d_to_0_8_7e.php");
			upgrade_to_0_8_7e();
		}elseif ($cacti_versions[$i] == "0.8.7f") {
			include ("0_8_7e_to_0_8_7f.php");
			upgrade_to_0_8_7f();
		}elseif ($cacti_versions[$i] == "0.8.7g") {
			include ("0_8_7f_to_0_8_7g.php");
			upgrade_to_0_8_7g();
		}elseif ($cacti_versions[$i] == "0.8.7h") {
			include ("0_8_7g_to_0_8_7h.php");
			upgrade_to_0_8_7h();
		}elseif ($cacti_versions[$i] == "0.8.7i") {
			include ("0_8_7h_to_0_8_7i.php");
			upgrade_to_0_8_7i();
		}elseif ($cacti_versions[$i] == "0.8.8") {
			include ("0_8_7i_to_0_8_8.php");
			upgrade_to_0_8_8();
		}elseif ($cacti_versions[$i] == "0.8.8a") {
			include ("0_8_8_to_0_8_8a.php");
			upgrade_to_0_8_8a();
		}elseif ($cacti_versions[$i] == "0.8.8b") {
			include ("0_8_8_to_0_8_8b.php");
			upgrade_to_0_8_8b();
		}
	}
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title>cacti</title>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
	<style>
	<!--
		BODY,TABLE,TR,TD
		{
			font-size: 10pt;
			font-family: Verdana, Arial, sans-serif;
		}

		.code
		{
			font-family: Courier New, Courier;
		}

		.header-text
		{
			color: white;
			font-weight: bold;
		}
	-->
	</style>
</head>

<body>

<form method="post" action="index.php">

<table width="500" align="center" cellpadding="1" cellspacing="0" border="0" bgcolor="#104075">
	<tr bgcolor="#FFFFFF" style="height:10px;">
		<td>&nbsp;</td>
	</tr>
	<tr>
		<td width="100%">
			<table cellpadding="3" cellspacing="0" border="0" bgcolor="#E6E6E6" width="100%">
				<tr>
					<td bgcolor="#104075" class="header-text">Cacti Installation Guide</td>
				</tr>
				<tr>
					<td width="100%" style="font-size: 12px;">
						<?php if ($step == "1") { ?>

						<p>Thanks for taking the time to download and install cacti, the complete graphing
						solution for your network. Before you can start making cool graphs, there are a few
						pieces of data that cacti needs to know.</p>

						<p>Make sure you have read and followed the required steps needed to install cacti
						before continuing. Install information can be found for
						<a href="../docs/html/install_unix.html">Unix</a> and <a href="../docs/html/install_windows.html">Win32</a>-based operating systems.</p>

						<p>Also, if this is an upgrade, be sure to reading the <a href="../docs/html/upgrade.html">Upgrade</a> information file.</p>

						<p>Cacti is licensed under the GNU General Public License, you must agree
						to its provisions before continuing:</p>

						<p class="code">This program is free software; you can redistribute it and/or
						modify it under the terms of the GNU General Public License
						as published by the Free Software Foundation; either version 2
						of the License, or (at your option) any later version.</p>

						<p class="code">This program is distributed in the hope that it will be useful,
						but WITHOUT ANY WARRANTY; without even the implied warranty of
						MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
						GNU General Public License for more details.</p>

						<?php }elseif ($step == "2") { ?>

						<p>Please select the type of installation</p>

						<p>
						<select name="install_type">
							<option value="1"<?php print ($default_install_type == "1") ? " selected" : "";?>>New Install</option>
							<option value="3"<?php print ($default_install_type == "3") ? " selected" : "";?>>Upgrade from cacti 0.8.x</option>
						</select>
						</p>

						<p>The following information has been determined from Cacti's configuration file.
						If it is not correct, please edit 'include/config.php' before continuing.</p>

						<p class="code">
						<?php	print "Database User: $database_username<br>";
							print "Database Hostname: $database_hostname<br>";
							print "Database: $database_default<br>";
							print "Server Operating System Type: " . $config["cacti_server_os"] . "<br>"; ?>
						</p>

						<?php }elseif ($step == "3") { ?>

						<p>Make sure all of these values are correct before continuing.</p>
						<?php
						$i = 0;
						/* find the appropriate value for each 'config name' above by config.php, database,
						or a default for fall back */
						while (list($name, $array) = each($input)) {
							if (isset($input[$name])) {
								$current_value = $array["default"];

								/* run a check on the path specified only if specified above, then fill a string with
								the results ('FOUND' or 'NOT FOUND') so they can be displayed on the form */
								$form_check_string = "";

								if (($array["method"] == "textbox") ||
									($array["method"] == "filepath")) {
									if (@file_exists($current_value)) {
										$form_check_string = "<font color='#008000'>[FOUND]</font> ";
									}else{
										$form_check_string = "<font color='#FF0000'>[NOT FOUND]</font> ";
									}
								}

								/* draw the acual header and textbox on the form */
								print "<p><strong>" . $form_check_string . $array["friendly_name"] . "</strong>";

								if (!empty($array["friendly_name"])) {
									print ": " . $array["description"];
								}else{
									print "<strong>" . $array["description"] . "</strong>";
								}

								print "<br>";

								switch ($array["method"]) {
								case 'textbox':
									form_text_box($name, $current_value, "", "", "40", "text");
									break;
								case 'filepath':
									form_filepath_box($name, $current_value, "", "", "40", "text");
									break;
								case 'drop_array':
									form_dropdown($name, $array["array"], "", "", $current_value, "", "");
									break;
								}

								print "<br></p>";
							}

							$i++;
						}?>

						<p><strong><font color="#FF0000">NOTE:</font></strong> Once you click "Finish",
						all of your settings will be saved and your database will be upgraded if this
						is an upgrade. You can change any of the settings on this screen at a later
						time by going to "Cacti Settings" from within Cacti.</p>

						<?php }elseif ($step == "8") { ?>

						<p>Upgrade results:</p>

						<?php
						$current_version  = "";
						$upgrade_results = "";
						$failed_sql_query = false;

						$fail_text = "<span style='color: red; font-weight: bold; font-size: 12px;'>[Fail]</span>&nbsp;";
						$success_text = "<span style='color: green; font-weight: bold; font-size: 12px;'>[Success]</span>&nbsp;";

						if (isset($_SESSION["sess_sql_install_cache"])) {
							while (list($index, $arr1) = each($_SESSION["sess_sql_install_cache"])) {
								while (list($version, $arr2) = each($arr1)) {
									while (list($status, $sql) = each($arr2)) {
										if ($current_version != $version) {
											$version_index = array_search($version, $cacti_versions);
											$upgrade_results .= "<p><strong>" . $cacti_versions{$version_index-1}  . " -> " . $cacti_versions{$version_index} . "</strong></p>\n";
										}

										$upgrade_results .= "<p class='code'>" . (($status == 0) ? $fail_text : $success_text) . nl2br($sql) . "</p>\n";

										/* if there are one or more failures, make a note because we are going to print
										out a warning to the user later on */
										if ($status == 0) {
											$failed_sql_query = true;
										}

										$current_version = $version;
									}
								}
							}

							kill_session_var("sess_sql_install_cache");
						}else{
							print "<em>No SQL queries have been executed.</em>";
						}

						if ($failed_sql_query == true) {
							print "<p><strong><font color='#FF0000'>WARNING:</font></strong> One or more of the SQL queries needed to
								upgraded your Cacti installation has failed. Please see below for more details. Your
								Cacti MySQL user must have <strong>SELECT, INSERT, UPDATE, DELETE, ALTER, CREATE, and DROP</strong>
								permissions. You should try executing the failed queries as 'root' to ensure that you do not have
								a permissions problem.</p>\n";
						}

						print $upgrade_results;
						?>

						<?php }elseif ($step == "9") { ?>

						<p style='font-size: 16px; font-weight: bold; color: red;'>Important Upgrade Notice</p>

						<p>Before you continue with the installation, you <strong>must</strong> update your <tt>/etc/crontab</tt> file to point to <tt>poller.php</tt> instead of <tt>cmd.php</tt>.</p>

						<p>See the sample crontab entry below with the change made in red. Your crontab line will look slightly different based upon your setup.</p>

						<p><tt>*/5 * * * * cactiuser php /var/www/html/cacti/<span style='font-weight: bold; color: red;'>poller.php</span> &gt; /dev/null 2&gt;&amp;1</tt></p>

						<p>Once you have made this change, please click Next to continue.</p>

						<?php }?>

						<p align="right"><input type="image" src="install_<?php if ($step == "3") {?>finish<?php }else{?>next<?php }?>.gif" alt="<?php if ($step == "3"){?>Finish<?php }else{?>Next<?php }?>"></p>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<input type="hidden" name="step" value="<?php print $step;?>">

</form>

</body>
</html>
