<?php

error_reporting(0);

include(__DIR__ . '/../include/cli_check.php');

global $database_hostname, $database_username, $database_password;

require_once(__DIR__ . '/../lib/CactiProcess.php');

$argv = ['mysqladmin', '-h', $database_hostname, '-u', $database_username, 'status'];
$env = [];
if ($database_password != '') {
	$env['MYSQL_PWD'] = $database_password;
}

try {
	$process = \Cacti\Process\CactiProcess::run($argv, $env);
	$parts = preg_split('/\s+/', trim($process->getOutput()));
	$sql = $parts[5] ?? '';
} catch (\Exception $e) {
	$sql = '';
}

// Cacti expects 'U' on error, not empty string or 0.
print trim($sql ?? '') ?: 'U';
