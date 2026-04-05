<?php

error_reporting(0);

include(dirname(__FILE__) . '/../include/cli_check.php');

global $database_hostname, $database_username, $database_password;

$cmd = 'mysqladmin -h ' . cacti_escapeshellarg($database_hostname) . ' -u ' . cacti_escapeshellarg($database_username);

if ($database_password != '') {
	$cmd .= ' -p' . cacti_escapeshellarg($database_password);
}

$cmd .= ' status';

$output = shell_exec($cmd);

if ($output === null || $output === '') {
	print 'U';
} else {
	// Extract the 6th field (Queries per second avg), matching original awk '{print $6}'
	$parts = preg_split('/\s+/', trim($output));
	print isset($parts[5]) ? $parts[5] : 'U';
}
