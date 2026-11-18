<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Probe the versioned Boost table creation and repair against MariaDB, including
// repeated runs, denied DDL and preservation of queue/process data. Also exercise
// the completed-install branch with a missing install_version row.
require dirname(__DIR__) . '/Helpers/RequestCoverage.php';

define('CACTI_VERSION', '1.2.32');
require dirname(__DIR__, 2) . '/include/global_constants.php';
define('PROBE_STOP', 'probe-stop-after-version-check');

$settings = [];
$compare  = [];
$deleted  = [];
$pdo      = null;

function read_config_option($name, $force = false) { return $GLOBALS['settings'][$name] ?? null; }
function get_cacti_version() { return CACTI_VERSION; }
function cacti_version_compare($version1, $version2, $operator = '>') {
	$GLOBALS['compare'][] = [gettype($version1), gettype($version2)];

	return version_compare($version1, $version2, $operator);
}
function log_install_debug(...$args) {}
function log_install_medium(...$args) {}
function log_install_always(...$args) {}
function log_install_high($section, $message) {
	// the constructor logs 'After:' right after the version check; nothing later is under test
	if (str_starts_with($message, 'After:')) {
		throw new RuntimeException(PROBE_STOP);
	}
}
function clean_up_lines($value) { return $value; }
function cacti_sizeof($value) { return is_countable($value) ? count($value) : 0; }
function __($text, ...$args) { return $args ? sprintf($text, ...$args) : $text; }
function db_fetch_assoc_prepared($sql, $params = []) {
	$statement = $GLOBALS['pdo']->prepare($sql);
	$statement->execute($params);

	return $statement->fetchAll(PDO::FETCH_ASSOC);
}
function db_execute($sql) {
	if (str_starts_with($sql, 'DELETE FROM settings')) {
		$GLOBALS['deleted'][] = $sql;

		return true;
	}

	return $GLOBALS['pdo']->exec($sql) !== false;
}

function db_table_exists($table, $log = true, $db_conn = false) {
	$statement = $GLOBALS['pdo']->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
	$statement->execute([$table]);

	return (bool) $statement->fetchColumn();
}
function db_install_execute($sql, $params = array(), $log = true) {
	$GLOBALS['ddl_log_flags'][] = $log;

	if (($GLOBALS['reject_ddl'] ?? false) && str_contains($sql, '`poller_output_boost_local_data_ids`')) {
		return DB_STATUS_ERROR;
	}

	return db_execute($sql) ? DB_STATUS_SUCCESS : DB_STATUS_ERROR;
}

require dirname(__DIR__, 2) . '/lib/installer.php';
require dirname(__DIR__, 2) . '/install/upgrades/1_2_32.php';

$root     = dirname(__DIR__, 2);
$database = 'cacti_installer_probe';
$pdo      = new PDO(
	'mysql:host=' . (getenv('BOOST_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('BOOST_DB_PORT') ?: '3306') . ';charset=utf8mb4',
	getenv('BOOST_DB_USER') ?: 'root',
	getenv('BOOST_DB_PASSWORD') ?: '',
	[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("DROP DATABASE IF EXISTS `$database`");
$pdo->exec("CREATE DATABASE `$database`");
$pdo->exec("USE `$database`");

$config           = ['base_path' => $root];
$database_default = $database;
$tables           = ['poller_output_boost_local_data_ids', 'poller_output_boost_failures'];
$schema           = file_get_contents($root . '/cacti.sql');

preg_match_all('/^CREATE TABLE .*?;$/ms', $schema, $statements);

foreach ($statements[0] as $statement) {
	$pdo->exec($statement);
}

$pdo->exec('DROP TABLE poller_output_boost_local_data_ids, poller_output_boost_failures');

$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
$validate  = new ReflectionMethod(Installer::class, 'validateCoreSchema');
$before = $validate->invoke($installer);
$migrated = upgrade_1_2_32_boost_tables();
$first = $validate->invoke($installer);
$pdo->exec("INSERT INTO version (cacti) VALUES ('1.2.32')");
$pdo->exec("INSERT INTO poller_output_boost_failures (local_data_id, rrd_template, update_values) VALUES (1, 'a', '1:2')");
$pdo->exec("INSERT INTO poller_output_boost_processes (sock_int_value, run_id, child_id) VALUES (9876, 'preserve', 1)");
$second = $validate->invoke($installer);
$replayed = upgrade_1_2_32_boost_tables();

$repair = new ReflectionMethod(Installer::class, 'repairRuntimeTables');
(new ReflectionProperty(Installer::class, 'old_cacti_version'))->setValue($installer, CACTI_VERSION);
$ddl_log_flags = [];
$healthy = $repair->invoke($installer);
$healthy_queries = count($ddl_log_flags);
$pdo->exec('DROP TABLE poller_output_boost_local_data_ids');
$reject_ddl = true;
$failed = $repair->invoke($installer);
$reject_ddl = false;
$repaired = $repair->invoke($installer);
$repair_silent = !in_array(true, $ddl_log_flags, true);
// Run the public CLI entry point with the already-stamped database. Restore
// the disposable workspace configuration even if the child fails.
$config_path = $root . '/include/config.php';
$config_backup = is_file($config_path) ? file_get_contents($config_path) : false;
$cli_config = file_get_contents($root . '/include/config.php.dist');
$cli_config .= "\n\$database_default = 'cacti_installer_probe';\n" .
	"\$database_hostname = getenv('BOOST_DB_HOST') ?: '127.0.0.1';\n" .
	"\$database_port = getenv('BOOST_DB_PORT') ?: 3306;\n" .
	"\$database_username = getenv('BOOST_DB_USER') ?: 'root';\n" .
	"\$database_password = getenv('BOOST_DB_PASSWORD') ?: '';\n";
$pdo->exec('DROP TABLE poller_output_boost_local_data_ids');

try {
	file_put_contents($config_path, $cli_config);
	$output = [];
	exec(escapeshellarg(PHP_BINARY) . ' -d auto_prepend_file=' . escapeshellarg($root . '/tests/Helpers/RequestCoverage.php') .
		' ' . escapeshellarg($root . '/cli/install_cacti.php') . ' --accept-eula --install --force 2>&1', $output, $cli_status);

	if ($cli_status !== 0) {
		throw new RuntimeException('Already-upgraded CLI repair failed: ' . implode(PHP_EOL, $output));
	}
} finally {
	if ($config_backup === false) {
		unlink($config_path);
	} else {
		file_put_contents($config_path, $config_backup);
	}
}

$process_rows = (int) $pdo->query('SELECT COUNT(*) FROM poller_output_boost_processes WHERE sock_int_value = 9876')->fetchColumn();
$version = $pdo->query('SELECT cacti FROM version')->fetchColumn();

$columns = [];

$count = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = SCHEMA() AND TABLE_NAME = ?');

foreach ($tables as $table) {
	$count->execute([$table]);
	$columns[$table] = (int) $count->fetchColumn();
}
$rows = (int) $pdo->query('SELECT COUNT(*) FROM poller_output_boost_failures')->fetchColumn();
$cli_repaired = db_table_exists('poller_output_boost_local_data_ids');
$pdo->exec("DROP DATABASE `$database`");

// A completed install whose install_version row is missing (NULL) must reach the
// version comparison as a string and be treated as a mismatch.
$settings['install_step'] = Installer::STEP_COMPLETE;
$stopped = false;

try {
	new Installer(['Runtime' => 'Cli']);
} catch (RuntimeException $e) {
	$stopped = $e->getMessage() === PROBE_STOP;
}

print json_encode([
	'cli_status' => $cli_status,
	'cli_repaired' => $cli_repaired,
	'before' => $before,
	'migrated' => $migrated,
	'replayed' => $replayed,
	'healthy' => $healthy,
	'healthy_queries' => $healthy_queries,
	'failed' => $failed,
	'repaired' => $repaired,
	'repair_silent' => $repair_silent,
	'process_rows' => $process_rows,
	'version' => $version,
	'tables'  => count($statements[0]),
	'first'   => $first,
	'second'  => $second,
	'columns' => $columns,
	'rows'    => $rows,
	'stopped' => $stopped,
	'compare' => $compare,
	'deleted' => count($deleted),
]);
