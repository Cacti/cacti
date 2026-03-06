<?php

error_reporting(0);

include(__DIR__ . '/../include/cli_check.php');

global $database_hostname, $database_username, $database_password;

$cmd = 'mysqladmin -h ' . escapeshellarg($database_hostname) . ' -u ' . escapeshellarg($database_username);

if ($database_password != '') {
	$cmd .= ' -p' . escapeshellarg($database_password);
}

$cmd .= " status | awk '{print \$6 }'";

$sql = shell_exec($cmd);

print trim($sql ?? '');
