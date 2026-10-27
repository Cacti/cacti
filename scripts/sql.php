<?php

error_reporting(0);

include(__DIR__ . '/../include/cli_check.php');

global $database_hostname, $database_username, $database_password, $database_port;

$defaults_file = db_client_defaults_file($database_username, $database_password, $database_hostname, (int) $database_port);

if ($defaults_file === false) {
	print 'U';

	exit;
}

try {
	$cmd = 'mysqladmin --defaults-file=' . cacti_escapeshellarg($defaults_file) . " status | awk '{print \$6 }'";
	$sql = shell_exec($cmd);
} finally {
	unlink($defaults_file);
}

// Cacti expects 'U' on error, not empty string or 0.
print trim((string) $sql) ?: 'U';
