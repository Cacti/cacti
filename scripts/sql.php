<?php

error_reporting(0);

include(dirname(__FILE__) . '/../include/cli_check.php');

global $database_hostname, $database_username, $database_password;

$defaults_file = db_client_defaults_file($database_username, $database_password, $database_hostname);

if ($defaults_file === false) {
	print 'U';
	exit;
}

$args = array('--defaults-file=' . $defaults_file, 'status');

try {
	$output = cacti_exec_string('mysqladmin', $args);
} finally {
	unlink($defaults_file);
}

if ($output === null || $output === '') {
	print 'U';
} else {
	// Retain the original sixth-field polling output.
	$parts = preg_split('/\s+/', trim($output));
	print isset($parts[5]) ? $parts[5] : 'U';
}
