<?php

$no_http_headers = true;

/* display ALL errors */
error_reporting(E_ALL);

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . "/../include/config.php");

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

	$result = ereg_replace(": ", ":", $result);
	$result = ereg_replace("  ", " ", $result);
	$result = ereg_replace("Slow queries", "SlowQueries", $result);
	$result = ereg_replace("Open tables", "OpenTables", $result);
	$result = ereg_replace("Queries per second avg", "QPS", $result);
	$result = ereg_replace("Flush tables", "FlushTables", $result);

	return trim($result);
}

?>