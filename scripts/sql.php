<?php

error_reporting(0);

include(__DIR__ . '/../include/cli_check.php');

global $database_hostname, $database_username, $database_password;

$cmd = 'mysqladmin -h ' . cacti_escapeshellarg($database_hostname) . ' -u ' . cacti_escapeshellarg($database_username);

if ($database_password != '') {
	$cmd .= ' -p' . cacti_escapeshellarg($database_password);
}

$cmd .= " status | awk '{print \$6 }'";

$sql = shell_exec($cmd);

// Cacti expects 'U' on error, not empty string or 0.
print trim($sql ?? '') ?: 'U';
