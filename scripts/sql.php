<?php

error_reporting(0);

include(dirname(__FILE__) . '/../include/cli_check.php');

global $database_hostname, $database_username, $database_password;

$args = array(
	'--host=' . $database_hostname,
	'--user=' . $database_username
);

if ($database_password != '') {
	$args[] = '--password=' . $database_password;
}

$args[] = 'status';

$output = cacti_exec_string('mysqladmin', $args);

if ($output === null || $output === '') {
	print 'U';
} else {
	// Extract the 6th field (Queries per second avg), matching original awk '{print $6}'
	$parts = preg_split('/\s+/', trim($output));
	print isset($parts[5]) ? $parts[5] : 'U';
}
