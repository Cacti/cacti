<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Requires a disposable MariaDB server and permission to create databases/users.
if (PHP_SAPI !== 'cli') {
	exit;
}
$root = dirname(__DIR__, 4);
define('IN_CACTI_INSTALL', true);
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
require $root . '/lib/database.php';
require $root . '/lib/poller.php';
require $root . '/install/upgrades/1_2_32.php';

function read_config_option($name, $force = false) { return ''; }
function cacti_log($message, $output = false, $facility = 'SYSTEM', $level = 2) {}
function cacti_debug_backtrace($message, $output = false, $log = true, $level = 0, $skip = 0) {
	throw new RuntimeException('Unexpected SQL failure');
}
function db_install_add_cache($status, $sql, $params = []) {
	if ($status === DB_STATUS_ERROR) {
		throw new RuntimeException('Migration failed');
	}
}
foreach (['cacti_sizeof', 'cacti_count', 'cacti_object_hash', 'clean_up_lines', 'array_rekey', 'cacti_has_control_chars'] as $function) {
	cacti_fixture_source_function($root . '/lib/functions.php', $function, '');
}
foreach (['db_install_execute', 'db_install_add_column', 'db_install_add_key', 'db_install_drop_key'] as $function) {
	cacti_fixture_source_function($root . '/install/functions.php', $function, '');
}
function check($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	$GLOBALS['passed']++;
}
$passed = 0;
$config = ['is_web' => false];
$database_hostname = getenv('PROCESS_DB_HOST') ?: '127.0.0.1';
$database_port = getenv('PROCESS_DB_PORT') ?: '3306';
$database_default = 'cacti_process_' . bin2hex(random_bytes(8));
$dsn = "mysql:host=$database_hostname;port=$database_port;charset=utf8mb4";
$pdo = new PDO($dsn, getenv('PROCESS_DB_USER') ?: 'root', getenv('PROCESS_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

if (($argv[1] ?? '') === 'worker') {
	echo $pdo->query('SELECT CONNECTION_ID()')->fetchColumn(), "\n";
	flush();
	// 1317 is a killed statement; 2006 and 2013 are a killed connection
	try {
		$pdo->query('SELECT SLEEP(30)');
	} catch (PDOException $error) {
		if (!in_array((int) ($error->errorInfo[1] ?? 0), [1317, 2006, 2013], true)) {
			exit(2);
		}
	}
	// the statement is killed first, so give the connection kill time to land
	usleep(500000);
	try {
		$pdo->query('SELECT 1');
		exit(1);
	} catch (PDOException $error) {
		exit(in_array((int) ($error->errorInfo[1] ?? 0), [2006, 2013], true) ? 0 : 2);
	}
}

$database_total_queries = 0;
db_execute_prepared("CREATE DATABASE `$database_default`", [], true, $pdo);
db_execute_prepared("USE `$database_default`", [], true, $pdo);
$database_sessions = ["$database_hostname:$database_port:$database_default" => $pdo];
$other_user = 'cacti_test_' . bin2hex(random_bytes(6));
$other_password = bin2hex(random_bytes(16));
$worker = null;
try {
	$schema = file_get_contents($root . '/cacti.sql');
	check((bool) preg_match('/CREATE TABLE `?processes`? \(.*?;\n/s', $schema, $match), 'Missing fresh-install DDL');
	$pdo->exec($match[0]);
	check((bool) $pdo->query("SHOW COLUMNS FROM processes LIKE 'connection_id'")->fetch(), 'Fresh install lacks connection id');
	// Simulate the old schema, then exercise the actual upgrade helpers twice.
	$pdo->exec('ALTER TABLE processes DROP COLUMN connection_id');
	register_process('test', 'legacy', 1, getmypid(), 300);
	upgrade_to_1_2_32();
	upgrade_to_1_2_32();
	check((int) $pdo->query("SELECT connection_id FROM processes WHERE taskname='legacy'")->fetchColumn() === 0, 'Upgrade lost or changed a legacy process');
	register_process('test', 'new', 2, getmypid(), 300);
	$self = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
	check((int) $pdo->query("SELECT connection_id FROM processes WHERE taskname='new'")->fetchColumn() === $self, 'Registration did not save the real connection');
	$started = $pdo->query('SELECT NOW()')->fetchColumn();
	check(cacti_process_kill_db_connection(['connection_id' => $self, 'started' => $started]) === false, 'Helper killed its own connection');
	check(cacti_process_kill_db_connection(['connection_id' => 0, 'started' => $started]) === false, 'Zero connection id was accepted');
	db_execute_prepared("CREATE USER ?@'%' IDENTIFIED BY ?", [$other_user, $other_password]);
	$other = new PDO($dsn, $other_user, $other_password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
	$other_id = (int) $other->query('SELECT CONNECTION_ID()')->fetchColumn();
	check(cacti_process_kill_db_connection(['connection_id' => $other_id, 'started' => $started]) === false, 'Helper killed another database user');
	check((int) $other->query('SELECT 1')->fetchColumn() === 1, 'Other user connection was lost');
	$worker = proc_open([PHP_BINARY, __FILE__, 'worker'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$id = (int) fgets($pipes[1]);
	check($id > 0, 'Worker did not connect');
	$query = $pdo->prepare('SELECT INFO FROM information_schema.PROCESSLIST WHERE ID = ?');
	$deadline = microtime(true) + 5;
	do {
		$query->execute([$id]);
		$running = $query->fetchColumn() === 'SELECT SLEEP(30)';
		if (!$running) {
			usleep(10000);
		}
	} while (!$running && microtime(true) < $deadline);
	check($running, 'Worker statement never started');
	check(cacti_process_kill_db_connection(['connection_id' => $id, 'started' => '2000-01-01 00:00:00']) === false, 'A registration predating server startup was accepted');
	$begin = microtime(true);
	check(cacti_process_kill_db_connection(['connection_id' => $id, 'started' => $started]) === true, 'Live worker was not killed');
	foreach ($pipes as $pipe) {
		fclose($pipe);
	}
	$status = proc_close($worker);
	$worker = null;
	check($status === 0 && microtime(true) - $begin < 5, 'Statement continued running after termination');
	check(cacti_process_kill_db_connection(['connection_id' => $id, 'started' => $started]) === false, 'A missing connection was killed again');
} finally {
	if (is_resource($worker)) {
		proc_terminate($worker);
		proc_close($worker);
	}
	db_execute_prepared("DROP USER IF EXISTS ?@'%'", [$other_user]);
	db_execute_prepared("DROP DATABASE `$database_default`");
}
echo json_encode(['passed' => $passed]), "\n";
