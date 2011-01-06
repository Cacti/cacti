<?php

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

/* display ALL errors */
error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . "/../include/global.php");

	print call_user_func("ss_sql");
}

function ss_sql() {
	global $database_username;
	global $database_password;
	global $database_hostname;

	if ($database_password != "") {
		$result = `mysqladmin --host=$database_hostname --user=$database_username --password=$database_password status`;
	}else{
		$result = `mysqladmin --host=$database_hostname --user=$database_username status`;
	}

	$result = preg_replace("/: /", ":", $result);
	$result = preg_replace("/  /", " ", $result);
	$result = preg_replace("/Slow queries/", "SlowQueries", $result);
	$result = preg_replace("/Open tables/", "OpenTables", $result);
	$result = preg_replace("/Queries per second avg/", "QPS", $result);
	$result = preg_replace("/Flush tables/", "FlushTables", $result);

	return trim($result);
}

?>
